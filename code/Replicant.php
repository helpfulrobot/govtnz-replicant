<?php
class Replicant extends Object
{
	private static $files_path = 'assets/replicant/';

	private static $path_to_mysqldump = '/usr/bin/mysqldump';

	private static $path_to_mysql = '/usr/bin/mysql';

	static $transport_class = 'ReplicantTransportPHP';

	static $remote_path = 'replicant/files';

	private static $known_servers = array('localhost');

	// these tables will be excluded from the database dump
	static $exclude_tables = array(
		'Member',
		'MemberPassword',
		'Roles',
		'Group',
		'Group_Members',
		'Group_Roles',
		'Permission',
		'PermissionRole',
		'PermissionRoleCode',
		'ProgressLogEntry',
		'ReplicantAction',
		'ReplicantActionDump',
		'ReplicantActionRestore',
		'ReplicantActionListFiles',
		'ReplicantActionFetch',
		'ReplicantActionReadFile'
	);

	/**
	 * Return absolute path to the folder where Replicant will be storing files.
	 *
	 * @return string
	 */
	public static function asset_path()
	{
		return Director::getAbsFile(Replicant::config()->get('files_path'));
	}

	/**
	 * Return a suitable transport for replicant to use to query and fetch remote files.
	 *
	 * @param $protocol
	 * @param $host
	 * @param null $username
	 * @param null $password
	 * @return ReplicantTransport
	 */
	public static function transportFactory($protocol, $host, $proxy = null, $username = null, $password = null)
	{
		return new static::$transport_class($protocol, $host, $proxy, $username, $password);
	}

}

