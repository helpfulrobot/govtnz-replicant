<?php
/**
 * This class looks after fetching files from the remote server.
 *
 * It requires a remote host, username and password to be entered. Optionally a specific filename can provided to just fetch that file instead of all files.
 */
class ReplicantActionFetch extends ReplicantAction
{
	protected static $action = 'FetchFile';
	protected static $required_permission = ReplicantPermissions::RestoreDatabase;
	private static $singular_name = 'Fetch Remote Files';

	public function getCMSFields()
	{
		$fields = parent::getCMSFields();

		$fields->addFieldToTab('Root.Main', new DropdownField('RemoteHost', 'Fetch from server', $this->getHostsList(false)));
		$fields->addFieldToTab('Root.Main', new DropdownField('Protocol', 'Server-to-server file transfer protocol', Replicant::config()->get('protocols')));
		$fields->addFieldToTab('Root.Main', new DropdownField('Proxy', 'Proxy (if required, e.g. within CWP)', $this->getProxyList(true)));
		$fields->addFieldToTab('Root.Main', new TextField('UserName', null, Member::currentUser()->Email));
		$fields->addFieldToTab('Root.Main', new PasswordField('Password')); // this is not saved anywhere
		$fields->addFieldToTab('Root.Main', new TextField('FileName', 'Filename (leave blank for all remote files)'));
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
		return parent::update(CollectionTools::options_from_array(
			$data,
			array(
				'RemoteHost' => null,
				'Protocol' => null,
				'Proxy' => '',
				'UserName' => null,
				'Password' => null,
				'Path' => Replicant::config()->get('remote_path'),
				'FileName' => ''
			)
		));
	}

	/**
	 * Fetch a file or if no FileName set all files found at remote location.
	 *
	 * @SideEffects:
	 *  Writes files to local filesystem
	 *
	 * If all files then don't overwrite existing files, otherwise if a single file then overwrite it every time.
	 *
	 * @return int number of files fetched
	 */
	public function execute()
	{
		$this->checkPerm();
		$transport = Replicant::transportFactory($this->Protocol, $this->RemoteHost, $this->Proxy, $this->UserName, $this->Password);

		// if we have a FileName then only enqueue that file, otherwise get a list of files from remote host and enqueue all for fetching (existing files won't be refetched in this case).
		if (!$this->FileName) {
			$this->step("Fetching file list from '$this->Protocol://$this->UserName@$this->RemoteHost/$this->Path'");
			try {
				$files = $transport->fetchFileList($this->Path);
			} catch (Exception $e) {
				$this->failed("Failed to get file list: " . $e->getMessage());
				return 0;
			}
		} else {
			$fullPath = FileSystemTools::build_path($this->Path, $this->FileName);
			$this->step("Enqueuing file '$fullPath'");
			// create the files array as a single entry with path and name
			$files = array(
				array(
					'Path' => $this->Path,
					'FileName' => $this->FileName,
				)
			);
		}
		$numFiles = count($files);
		$numFetched = 0;

		$this->step("Fetching #$numFiles files");
		foreach ($files as $fileInfo) {
			// strip off extension here or alpha will reject request
			$fileName = $fileInfo['FileName'];

			$remotePathName = FileSystemTools::build_path(Replicant::config()->get('remote_path'), basename($fileName));
			$localPathName = FileSystemTools::build_path(Replicant::asset_path(), $fileName);

			$overwrite = ($this->FileName != '');

			$this->step("Fetching file '$remotePathName' with overwrite (" . (($overwrite) ? "set" : "not set") . ")");

			try {
				if (false !== $transport->fetchFile($remotePathName, $localPathName, $overwrite)) {
					$numFetched++;
				}
			} catch (Exception $e) {
				$this->failed("Failed to fetch file '$remotePathName': " . $e->getMessage());
				// EARLY EXIT!
				return false;
			}

		}
		$this->success("Fetched #$numFetched files of #$numFiles");
		return $numFetched;
	}
}