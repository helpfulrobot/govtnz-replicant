<?php
/**
 * This class looks after dumping a remote database when the user clicks 'Add' in the model admin CMS UI.
 */

class ReplicantActionDump extends ReplicantAction
{
	protected static $action = 'Dump';

	protected static $required_permission = ReplicantPermissions::DumpDatabase;

	private static $singular_name = 'Dump Database';

	public function getCMSFields()
	{
		$fields = parent::getCMSFields();

		// keep track of fields which will be hidden if RemoteHost is localhost
		$hide = array();

		$fields->addFieldToTab('Root.Main', new DropdownField('RemoteHost', 'Remote Host', $this->getHostsList(true)));
		$fields->addFieldToTab('Root.Main', $hide[] = new DropdownField('Protocol', 'Server-to-server request protocol', Replicant::config()->get('protocols')));
		$fields->addFieldToTab('Root.Main', $hide[] = new DropdownField('Proxy', 'Proxy (if required, e.g. within CWP)', $this->getProxyList(true)));
		$fields->addFieldToTab('Root.Main', $hide[] = new TextField('Database', 'Database', DatabaseTools::getDBCredential('Database')));
		$fields->addFieldToTab('Root.Main', $hide[] = new TextField('UserName', 'User name', Member::currentUser()->Email));
		$fields->addFieldToTab('Root.Main', $hide[] = new PasswordField('Password')); // this is not saved anywhere
		$fields->addFieldToTab('Root.Main', new CheckboxField('UseGZIP', "Use gzip compression", $this->isInDB() ? $this->UseGZIP : true));

		foreach ($hide as $field) {
			$field->hideIf('RemoteHost')->isEqualTo('localhost');
		}
		return $fields;
	}

	/**
	 * Validate according to rules:
	 *  RemoteHost is always required
	 *  Other fields required if remote host != 'localhost'
	 *
	 * @return ValidationResult
	 */
	public function validate()
	{
		$result = parent::validate();
		if (!(Controller::curr() instanceof ReplicantController)) {
			if (!$this->RemoteHost) {
				$result->error("Missing Remote Host");
			}
			if ($this->RemoteHost !== 'localhost') {
				if (CollectionTools::any_missing($this, array('Database', 'UserName', 'Password'))) {
					$result->error("Missing Database, UserName or Password");
				}
			}
		}
		return $result;
	}

	/**
	 * Override update with some usefull defaults and checks
	 * @param array $data
	 * @return DataObject
	 */
	public function update($data)
	{
		return parent::update(CollectionTools::options_from_array($data, array(
			'RemoteHost' => null,
			'Protocol' => 'http',
			'Proxy' => '',
			'Database' => DatabaseTools::getDBCredential('Database'),
			'UserName' => Member::currentUser() ? Member::currentUser()->Email : $_SERVER['PHP_AUTH_USER'],
			'Password' => '',
			'Path' => Replicant::asset_path(),
			'FileName' => FileSystemTools::filename_from_timestamp('.sql')
		)));
	}

	/**
	 * Dump database to the local filesystem via mysqldump command and optionally gzipping output.
	 *
	 * @SideEffects:
	 *  Dumps database to local filesystem.
	 *
	 * @return bool true on success, false on fail
	 * @throws PermissionFailureException
	 */
	public function execute()
	{
		$fullPath = FileSystemTools::build_path($this->Path, $this->FileName) . ($this->UseGZIP ? ".gz" : '');

		$this->step("Dumping database '$this->Database' to '$fullPath'");
		// local server dump requested, create paths and dump the file.
		if (!is_dir($this->Path)) {
			// path doesn't exist, create it recursively
			$this->step("Creating folder '$this->Path'");
			if (!FileSystemTools::make_path($this->Path)) {
				$this->failed("Failed to create path '$this->Path'");
				return false;
			};
		}

		$excludeTables = '';
		if (count(Replicant::config()->get('exclude_tables'))) {
			$excludeTables = " --ignore-table=$this->Database." . implode(" --ignore-table=$this->Database.", Replicant::config()->get('exclude_tables')) . " ";
		}

		$command = sprintf("%s --host=%s --user=%s --password=%s %s %s %s %s",
			Replicant::config()->get('path_to_mysqldump'),
			DatabaseTools::getDBCredential('Server'),
			DatabaseTools::getDBCredential('UserName'),
			DatabaseTools::getDBCredential('Password'),
			$excludeTables,
			$this->Database,
			$this->UseGZIP ? " | gzip > " : " > ",
			$fullPath
		);
		$ok = $this->system($command, $retval);
		if ($ok) {
			$this->success("Dumped Database to $fullPath");
		} else {
			$this->failed("Execute returned #$retval");
		}
		return $ok;
	}
}