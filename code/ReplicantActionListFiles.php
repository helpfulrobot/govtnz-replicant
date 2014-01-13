<?php
/**
 * Class to list local files to output in html or json format
 */
class ReplicantActionListFiles extends ReplicantAction
{
	protected static $action = 'ListFiles';

	protected static $required_permission = ReplicantPermissions::RestoreDatabase;

	private static $singular_name = 'List Files';

	public function canCreate($member = null)
	{
		return false;
	}

	public function getCMSFields()
	{
		$fields = parent::getCMSFields();

		$knownHosts = $this->getHostsList(true);

		$fields->addFieldToTab('Root.Main', new DropdownField('RemoteHost', 'Fetch from server', $knownHosts));
		$fields->addFieldToTab('Root.Main', new DropdownField('Protocol', 'Protocol to list files', Replicant::config()->get('protocols')));
		$fields->addFieldToTab('Root.Main', new DropdownField('Proxy', 'Proxy (if required, e.g. within CWP)', $this->getProxyList(true)));
		$fields->addFieldToTab('Root.Main', new TextField('UserName', null, Member::currentUser()->Email));
		$fields->addFieldToTab('Root.Main', new PasswordField('Password')); // this is not saved anywhere
		return $fields;
	}

	/**
	 * Validate according to rules:
	 *  RemoteHost, UserName and Password always required
	 *
	 * @return ValidationResult
	 */
	public function validate()
	{
		$result = parent::validate();
		if (!(Controller::curr() instanceof ReplicantController)) {
			if (CollectionTools::any_missing($this, array('RemoteHost', 'UserName', 'Password'))) {
				$result->error("Missing RemoteHost, UserName or Password");
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
		return parent::update(CollectionTools::options_from_array($data, array(
			'RemoteHost' => $_SERVER['SERVER_NAME'],
			'Protocol' => $_SERVER['SERVER_PROTOCOL'],
			'Proxy' => '',
			'Path' => Replicant::asset_path(),
			'UserName' => Member::currentUser()->Email,
		)));
	}

	/**
	 * Return a list of files from the provided as the requested mimeType (default text/html unordered list, otherwise application/json structure).
	 * @param $path
	 * @param array $mimeTypes
	 * @return number of files listed
	 */
	public function execute($mimeTypes = array('text/html'))
	{
		$this->checkPerm();

		$files = FileSystemTools::nodot_files($this->Path);
		if (in_array('application/json', $mimeTypes)) {
			$this->step("Sending results as json");
			$body = '';
			foreach ($files as $fullPath => $fileName) {
				$fileInfo = new ReplicantFileInfo($fullPath);
				$body .= "," . $fileInfo->to_json();
			}
			$body = "[" . substr($body, 1) . "]";
		} else {
			$this->step("Sending results as html");
			$body = "<ul>";
			foreach ($files as $this->Path => $fileName) {
				// strip extension, not needed
				$body .= '<li>' . $fileName . '&nbsp;&nbsp;
                            <a href="/replicant/files/' . FileSystemTools::strip_extension($fileName) . '">download</a>&nbsp;&nbsp;
                            <a onclick="javascript: return confirm("Are you sure you want to restore ' . $fileName . ' to this server?);" href="/dev/tasks/ReplicantTask?action=restore&filename=' . $fileName . '">restore to this server</a>
                          </li>';
			}
			$body .= "</ul>";
		}
		echo $body;
		$this->success("Output " . count($files) . " files");
		return count($files);
	}
}