<?php
/**
 * This class looks after restoring a database from the local filesystem.
 *
 * Unlike dump this only works locally.
 */
class ReplicantActionRestore extends ReplicantAction
{
	protected static $action = 'Restore';

	protected static $required_permission = ReplicantPermissions::RestoreDatabase;

	private static $singular_name = 'Restore Database Dump';

	public function getCMSFields()
	{
		$fields = parent::getCMSFields();
		$localFileNames = array_values(FilesystemTools::nodot_files(FileSystemTools::build_path(Director::baseFolder(), Replicant::config()->get('files_path'))));
		$fields->addFieldToTab('Root.Main', new DropdownField('FileName', 'Database dump to restore', array_combine($localFileNames, $localFileNames)));
		return $fields;
	}

	/**
	 * Validate according to rules:
	 *  FileName is always required
	 *
	 * @return ValidationResult
	 */
	public function validate()
	{
		$result = parent::validate();
		if (!(Controller::curr() instanceof ReplicantController)) {
			if (CollectionTools::any_missing($this, array('FileName'))) {
				$result->error("Missing FileName");
			}
		}
		return $result;
	}

	/**
	 * Update with some sensible defaults.
	 * @param array $data
	 * @return DataObject
	 */
	public function update($data)
	{
		return parent::update(CollectionTools::options_from_array(
			$data,
			array(
				'RemoteHost' => $_SERVER['SERVER_NAME'],
				'Protocol' => $_SERVER['SERVER_PROTOCOL'],
				'Proxy' => '',
				'Path' => Replicant::asset_path(),
				'FileName' => null,
				'Database' => DatabaseTools::getDBCredential('Database')
			)
		));
	}

	/**
	 * Restore the database from a local file.
	 *
	 * @SideEffects:
	 *  Restores dump file to local database
	 *
	 * @return bool true on success, false on fail
	 * @throws PermissionFailureException
	 * @throws Exception
	 */
	public function execute()
	{
		$this->checkPerm();

		$fullPath = FileSystemTools::build_path($this->Path, $this->FileName);
		if (!file_exists($fullPath)) {
			$this->failed("No such file '$fullPath'");
			throw new Exception("No such file $fullPath");
		}
		// detect if we should use GZIP from the file name terminal extension being '.gz'
		if (substr($this->FileName, -3) == '.gz') {
			$this->UseGZIP = true;
			$command = sprintf("gunzip < %s | %s --host=%s --user=%s --password=%s %s ",
				$fullPath,
				Replicant::config()->get('path_to_mysql'),
				DatabaseTools::getDBCredential('Server'),
				DatabaseTools::getDBCredential('UserName'),
				DatabaseTools::getDBCredential('Password'),
				$this->Database
			);
		} else {
			$command = sprintf("%s --host=%s --user=%s --password=%s %s < %s",
				Replicant::config()->get('path_to_mysql'),
				DatabaseTools::getDBCredential('Server'),
				DatabaseTools::getDBCredential('UserName'),
				DatabaseTools::getDBCredential('Password'),
				$this->Database,
				$fullPath
			);
		}
		$this->step("Restoring database '$this->UserName@$this->RemoteHost:$this->Database' from '$fullPath'");
		if ($this->system($command, $retval)) {
			// we need a new one here as existing one will be gone when database
//            ReplicantActionRestore::create(static::ActionRestore, "", static::ResultMessageSuccess, "Restoring database '$this->UserName@$this->RemoteHost:$this->Database' from '$fullPath'");
			$this->success("Restored database '$this->Database' from '$fullPath'");
			return true;
		} else {
			$this->failed("Failed, command returned #$retval");
			return false;
		}
	}
}