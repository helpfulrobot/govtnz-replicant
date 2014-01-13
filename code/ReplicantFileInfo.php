<?php
/**
 * Extend SplFileInfo to provide a json serialization method and other helpers.
 */
class ReplicantFileInfo extends SplFileInfo
{

	/**
	 * Does this filename start with a '.'?
	 * @return bool
	 */
	public function isDot()
	{
		return substr($this->getFilename(), 0, 1) == '.';
	}

	/**
	 * Provide basic json representation of this object suitable for OTW transfer and reconstruction other end.
	 * @return string
	 */
	public function to_json()
	{
		return json_encode(array(
			"Path" => $this->getPath(),
			"FileName" => $this->getFilename(),
			"PathName" => $this->getPathname(),
			'Modified' => $this->getMTime()
		));
	}
}