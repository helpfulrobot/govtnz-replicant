<?php
/**
 * Read a file to the output buffer
 */
class ReplicantActionReadFile extends ReplicantAction
{
	protected static $action = 'ReadFile';

	protected static $required_permission = ReplicantPermissions::RestoreDatabase;

	private static $singular_name = 'Read File';

	public function canCreate($member = null)
	{
		return false;
	}

	public function getCMSFields() {
		$fields = parent::getCMSFields();
		$fields->addFieldToTab('Root.Main', new DropdownField('Proxy', 'Proxy (if required, e.g. within CWP)', $this->getProxyList(true)));
		return $fields;
	}

	/**
	 * Validate according to rules:
	 *  RemoteHost, UserName, Password and FileName always required
	 *
	 * @return ValidationResult
	 */
	public function validate()
	{
		$result = parent::validate();
		if (!(Controller::curr() instanceof ReplicantController)) {
			if (CollectionTools::any_missing($this, array('RemoteHost', 'UserName', 'Password', 'FileName'))) {
				$result->error("Missing RemoteHost, UserName, Password or FileName");
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
				'UserName' => Member::currentUser()->Email,
				'FileName' => null
			)
		));
	}

	/**
	 * Read the file to the output buffer.
	 *
	 * Path to file is hard-coded as Replicant::asset_path() setting.
	 *
	 * Content-Type returned is text/plain.
	 *
	 * @return bool result from readfile (bytes output to buffer)
	 */
	public function execute()
	{
		$this->checkPerm();

		$fullPath = FileSystemTools::build_path($this->Path, "$this->FileName.sql");
		if (!file_exists($fullPath)) {
			$this->failed("File '$fullPath' doesn't exist");
			return false;
		}
		$this->step("Reading file '$fullPath'");

		ob_clean();
		Header('Content-Type: text/plain');
		$res = readfile($fullPath);
		if ($res === false) {
			$this->failed("Failed to read file '$fullPath'");
		} else {
			$this->success("Read #$res bytes");
		}
		return $res;
	}
}