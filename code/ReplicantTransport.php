<?php
abstract class ReplicantTransport
{

	protected $protocol;
	protected $host;
	protected $proxy;
	protected $username;
	protected $password;

	public function __construct($protocol, $host, $proxy = null, $username = null, $password = null)
	{
		$this->protocol = TransportTools::parseProtocol($protocol);
		$this->host = $host;
		$this->proxy = $proxy;
		$this->username = $username;
		$this->password = $password;
	}

	/**
	 * Override in implementation to actually get the file using curl or whatever.
	 *
	 * @param $url
	 * @param $contentType e.g. 'application/json'
	 * @return mixed
	 * @throws Exception
	 */
	abstract protected function readFile($url, $contentType = null);

	/**
	 * Fetch a file from remote host and write to local filesystem.
	 *
	 * Set overwrite to true to force overwrite of existing files.
	 *
	 * @param string $remotePathName - e.g. replicant/files
	 * @param string $localPathName - e.g. assets/replicant/files
	 * @param bool $overwrite
	 * @param string $contentType
	 * @return int|bool number of bytes written or false if failed
	 * @throws Exception
	 */
	public function fetchFile($remotePathName, $localPathName, $overwrite = false, $contentType = '')
	{
		// most paths lead to null or error return
		$written = null;

		$fileInfo = new SplFileInfo($localPathName);
		$exists = $fileInfo->isFile();

		if ($exists && !$overwrite) {
			return false;
		}
		$url = $this->buildURL($remotePathName);

		$fileObject = $fileInfo->openFile("w");

		if ($fileObject) {
			// readFile will throw an exception on error so we'll not try and write a bad file
			$written = $fileObject->fwrite($this->readFile($url, $contentType));
		}
		return ($written > 0) ? $written : false;
	}


	/**
	 * Returns an array of remote files available for download. Makes request for application/json.
	 *
	 * @param string $path
	 * @return array
	 * @throws Exception
	 */
	public function fetchFileList($path)
	{
		// fetchPage will throw an exception so we should never have invalid json
		if ($json = $this->fetchPage($path, "application/json")) {
			return $this->decodeFileList($json);
		}
		return array();
	}

	/**
	 * Fetch and return a page.
	 *
	 * @param string $path
	 * @param string $contentType
	 * @return mixed
	 * @throws Exception
	 */
	public function fetchPage($path, $contentType = '')
	{
		$url = $this->buildURL($path);
		return $this->readFile($url, $contentType);
	}

	/**
	 * Decode json list of files and sort by Modified desc and return as array.
	 * @param string $json
	 * @return array
	 */
	protected function decodeFileList($json)
	{
		$files = json_decode($json, true);

		if (is_array($files)) {
			usort($files, function ($item1, $item2) {
				if ($item1['Modified'] == $item2['Modified']) {
					return 0;
				}
				return ($item1['Modified'] < $item2['Modified']) ? 1 : -1;

			});
		} else {
			$files = array();
		}
		return $files;
	}


	/**
	 * Build a url from instance vars set in ctor and provided parameters.
	 *
	 * @param string $path
	 * @return string
	 */
	public function buildURL($path)
	{
		return sprintf("%s://%s/%s",
			$this->protocol,
			$this->host,
			$path
		);
	}
}
