<?php
/**
 * Setup permissions, members, groups in database if not there and tailors for the environment (dev/test/live)
 *
 * Permissions are:
 *
 * {@code REPLICANT_Dump_Database}
 * - can dump the database to local file
 *
 * {@code REPLICANT_Restore_Database}
 * - can restore the database from a local file
 * - can get list of dumps available
 * - can get a dump over the wire
 *
 */
class ReplicantPermissions extends DataObject implements PermissionProvider
{
	const DumpDatabase = 'REPLICANT_Dump_Database';
	const RestoreDatabase = 'REPLICANT_Restore_Database';

	// set to true to create members 'replicantc' and 'replicantp' in the consumer/producer groups.
	static $create_members_and_assign_to_groups = false;

	/**
	 * Groups to create where the value is the index of the parent group in this array (not database ID which we don't know)
	 * @var array
	 */
	static $groups = array(
		'Replicant Producer' => 'Can dump the local database to a file',
		'Replicant Consumer' => 'Can restore the local database from a file'
	);
	/**
	 * Permission records to add.
	 * @var array
	 */
	static $permissions = array(
		self::DumpDatabase => 'Replicant Producer',
		self::RestoreDatabase => 'Replicant Consumer'
	);
	/**
	 *
	 * @var array
	 */
	static $members = array(
		array(
			'Email' => 'replicantp',
			'FirstName' => 'Replicant',
			'Surname' => 'Producer',
		),
		array(
			'Email' => 'replicantc',
			'FirstName' => 'Replicant',
			'Surname' => 'Consumer',
		)
	);
	static $member_groups = array(
		'replicantp' => array(
			'Replicant Producer'
		),
		'replicantc' => array(
			'Replicant Consumer'
		)
	);

	/**
	 * Return a map of permission codes to add to the dropdown shown in the Security section of the CMS.
	 */
	public function providePermissions()
	{
		return self::$permissions;
	}

	/**
	 * This class isn't persistant, so don't create a table.
	 */
	public function requireTable()
	{
		DB::dontRequireTable($this->class);
	}

	/**
	 * Create permissions, groups and member records if they don't exist.
	 */
	public function requireDefaultRecords()
	{
		parent::requireDefaultRecords();

		$groups = array();

		// create or update groups, cache id by title
		foreach (self::$groups as $title => $description) {
			if (!$group = DataObject::get_one('Group', " Title = '$title'")) {
				$group = new Group(array(
					'Title' => $title,
				));
			}
			// update description if exists, otherwise set
			$group->Description = $description;
			$group->write();
			$groups[$title] = $group->ID;
		}
		// create or update permissions and assign to associated group
		foreach (self::$permissions as $code => $groupTitle) {
			if (!$perm = DataObject::get_one('Permission', " Code = '$code' ")) {
				$perm = new Permission(array(
					'Code' => $code,
				));
			}
			$perm->GroupID = $groups[$groupTitle];
			$perm->write();
		}
		// if config option is true create or update members, then add Member to group
		if ($this->config()->get('create_members_and_assign_to_groups') === true) {
			foreach (self::$members as $memberInfo) {
				$email = $memberInfo['Email'];

				if (!$member = DataObject::get_one('Member', " Email = '$email' ")) {
					$member = new Member();
				}
				// set or update data
				$member->update($memberInfo);
				$member->write();

				foreach (self::$member_groups[$email] as $groupTitle) {
					// if not in the group already add it

					$groupID = $groups[$groupTitle];
					if (!$member->Groups()->filter('ID', $groupID)->first()) {
						$member->Groups()->add($groupID);
					}
					$member->write();
				}
			}
		}
	}

}