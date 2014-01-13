<?php
/**
 * Implementation of ReplicantTransport that uses file_get_contents (instead of e.g. curl).
 *
 * @return string|bool either file contents or false if failed
 */
class ReplicantTransportPHP extends ReplicantTransport
{
	/**
	 * Read the file using file_get_contents from the url with an optional contentType Accept header.
	 *
	 * This may bork if file gets large, so may need to implement with curl and stream directly to output file.
	 *
	 * @param $url
	 * @param null $contentType
	 * @return string file contents or throws an exception on error
	 * @throws Exception
	 */
	protected function readFile($url, $contentType = null)
	{
		$headers = array();
		if ($contentType) {
			$headers[] = "Accept: $contentType";
		}
		if ($this->username) {
			$headers[] = "Authorization: Basic " . base64_encode("$this->username:$this->password");
		}
		$options = array();
		if (count($headers)) {
			$options['http'] = array('header' => implode("\r\n", $headers));
		}
		$context = stream_context_create($options);

		try {
// intercept any errors when reading file from url
			set_error_handler(function ($errno, $message, $file, $line) {
				throw new ErrorException($message, $errno, $errno, $file, $line);
			});
			$result = file_get_contents($url, null, $context);
			if ($result === false) {
				throw new ErrorException("file_get_contents returned false");
			}
		} catch (Exception $e) {
			restore_error_handler();
			throw $e;
		}

		restore_error_handler();
		return $result;
	}
}
