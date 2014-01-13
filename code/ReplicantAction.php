<?php
/**
 * Extend standard ProgressLogEntry into models which perform actions.
 *
 * This may violate seperation of concerns somewhat, however ensures logging of all actions takes place and allows ModelAdmin to do all the routing, permissions etc.
 */
class ReplicantAction extends ProgressLogEntry
{
	// override in derived class
	protected static $required_permission = null;

	private static $db = array(
		'Path' => 'Varchar(255)',
		'FileName' => 'Varchar(255)', // most actions have an associated file name so track it
		'RemoteHost' => 'Varchar(255)', // hostname action being called on, maybe local or remote
		'Protocol' => 'Varchar(32)', // protocol used if remote call/transfer, might be e.g http/1.0 if local request
		'Database' => 'Varchar(255)', // database being dumped/restored etc
		'UserName' => 'Varchar(255)', // provided username for remote login
		'Proxy' => 'Varchar(255)' // proxy may be needed within some environments
	);

	private static $summary_fields = array(
		'Action',
		'LastEdited',
		'Who',
		'IPAddress',
		'ResultMessage',
		'ResultInfo',
		'RemoteHost',
		'Database',
		'UserName',
		'FileName',
	);

	protected static $default_sort = 'ID desc';

	/**
	 * Create an action.
	 *
	 * Unlike ProgressLogEntry which this class is derived from this doesn't immediately write to the database!
	 *
	 * @param null $action if not supplied then the derived class name
	 * @param null $task if not supplied then the derived class config::$action
	 * @param string $message
	 * @param null $info
	 * @return ReplicantAction
	 */
	public static function create($task = null, $action = null, $message = ProgressLogEntry::ResultMessageStarted, $info = null)
	{
		return new static(array(
			'Task' => $task ?: get_called_class(),
			'Action' => $action ?: static::$action,
			'ResultMessage' => $message,
			'ResultInfo' => $info
		));
	}

	/**
	 * If not logged in attempt HTTP auth and check permission, otherwise check logged in members permission
	 * @throws PermissionFailureException
	 * @return ReplicantAction this
	 */
	public function checkPerm()
	{
		if (!$member = Member::currentUserID()) {
			if ($member = BasicAuth::requireLogin("Replicant", static::$required_permission, true)) {
				$member->logIn();
				$res = true;
			}
		} else {
			$res = Permission::check(static::$required_permission);
		}
		if (!$res) {
			$this->failed("Permission Failure: " . static::$required_permission)->output();
			throw new PermissionFailureException("Not allowed to " . static::$required_permission);
		}
		return $this;
	}

	public function canCreate($member = null)
	{
		return Permission::check(static::$required_permission);
	}

	public function canEdit($member = null)
	{
		return Permission::check(static::$required_permission);
	}

	/**
	 * Replace default scaffolded fields with hidden fields.
	 *
	 * Derived class should append action-specific fields after calling this.
	 *
	 * @return FieldList
	 */
	public function getCMSFields()
	{
		$fields = parent::getCMSFields();
		$dataFields = $fields->dataFields();

		// Replace scaffolded fields with hidden fields if this is a new record
		// so can focus on values required for the action to be performed.
		// If not a new record show all fields as normal for review.
		if (!$this->isInDB()) {
			foreach ($dataFields as $field) {
				$fields->removeByName($field->getName());
				$fields->add(new HiddenField($field->getName(), $field->Title(), $field->Value()));
			}
		}
		return $fields;
	}


	/**
	 * Execute a command and puts the exit code in {@code $retval} where 0 is success.
	 *
	 * Returns boolean success/fail (retval == 0).
	 *
	 * SideEffects:
	 *  echos progress to output buffer
	 *
	 * @param string $command
	 * @param int $retval 0 for success, else a system/command specific error code returned by called command.
	 * @return bool true on success, false on failure so check retval.
	 */
	protected function system($command, &$retval)
	{
		$retval = '';
		system($command, $retval);
		return ($retval === 0);
	}

	/**
	 * Return an array of known hosts from Replicant::config::$remote_hosts. Key and value is host name.
	 *
	 * @param bool $includeLocalHost prepend list with 'localhost'
	 * @return array
	 */
	public function getHostsList($includeLocalHost)
	{
		$hosts = Replicant::config()->get('remote_hosts');
		if ($includeLocalHost) {
			array_unshift($hosts, 'localhost');
		}
		return array_combine($hosts, $hosts);
	}

	/**
	 * Return an array of known proxies from Replicant::config::$proxies. Key and value is proxy name except for optional '' => 'none'.
	 *
	 * @param bool $includeEmptyOption prepend list with '' => 'none'
	 * @return array
	 */
	public function getProxyList($includeEmptyOption = true) {
		$proxies = array_combine(Replicant::config()->get('proxies'), Replicant::config()->get('proxies'));

		if ($includeEmptyOption) {
			$proxies = array('' => 'none') + $proxies;
		}
		return $proxies;
	}


}