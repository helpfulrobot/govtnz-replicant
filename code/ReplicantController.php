<?php
/**
 * Handles requests to list and stream a file via url endpoint.
 *
 * Paths are hardcoded here to point to replicant root directory instead of being passed in query.
 */
class ReplicantController extends Controller
{
	static $action_parameter_name = 'action';

	static $allowed_actions = array(
		'files' => true,
		'dump' => true,
		'restore' => true,
		'fetch' => true
	);


	/**
	 * Force SSL on live site.
	 */
	public function init()
	{
		if (Director::isLive()) {
            Director::forceSSL();
		}
		parent::init();
	}

	/**
	 * List all replicant files. Requires login with ReplicantPermissions::RestoreDatabase permissions.
	 *
	 * We can't return anything here other than data as output is meaningful to caller.
	 */
	public function files(SS_HTTPRequest $request)
	{
		if ($request->param('ID')) {
			// reads file into output stream
			$this->readFile($request);
		} else {
			$this->listFiles($request);
		}
		return '';
	}

	/**
	 * Dump the current SilverStripe database to the local filesystem.
	 *
	 * Returns true on successful execution of mysqldump command, false otherwise.
	 *
	 * @param SS_HTTPRequest $request
	 * @return bool
	 * @throws PermissionFailureException
	 */
	public function dump(SS_HTTPRequest $request)
	{
		$options = CollectionTools::options_from_array(
			$request->getVars(),
			array(
				'RemoteHost' => $request->getIP(),
				'Path' => Replicant::asset_path(),
				'FileName' => FileSystemTools::filename_from_timestamp('.sql'),
				'UseGZIP' => false
			)
		);
		$action = ReplicantActionDump::create();
		$action->checkPerm()->update($options)->execute();
		return $action->format();
	}

	/**
	 * Restore a file from the local filesystem to the current SilverStripe database.
	 *
	 * Returns true on successful execution of mysql restore command, false otherwise.
	 *
	 * @param SS_HTTPRequest $request
	 * @return bool
	 * @throws Exception
	 */
	public function restore(SS_HTTPRequest $request)
	{
		$options = CollectionTools::options_from_array(
			$request->getVars(),
			array(
				'RemoteHost' => $request->getIP(),
				'Path' => Replicant::asset_path(),
				'FileName' => null,
			)
		);
		$action = ReplicantActionRestore::create();
		$action->checkPerm()->update($options)->execute();
		return $action->format();
	}

	/**
	 * Fetch one or all remote dump files and writes to local filesystem.
	 *
	 * If filename is supplied as getVar then only that file will be retrieved, otherwise all files which don't exist locally will be retrieved up to number getVar.
	 *
	 * If filename is supplied as getVar then file will overwrite existing file.
	 *
	 * SideEffects:
	 *  Reads files from remote system.
	 *  Writes files to local filesystem.
	 *  Outputs results
	 *
	 * @param SS_HTTPRequest $request
	 * @return int number of files fetched
	 * @throws PermissionFailureException
	 */
	public function fetch(SS_HTTPRequest $request)
	{
		$options = CollectionTools::options_from_array($request->getVars(), array(
			'RemoteHost' => $request->getIP(),
			'Path' => Replicant::asset_path(),
			'FileName' => '',
			'UserName' => null, // username to connect to remote server
			'Password' => null, // password to connect to remote server
		));
		$action = ReplicantActionFetch::create();
		$action->checkPerm()->update($options)->execute();
		return $action->format();
	}

	/**
	 * Lists files either as json or as a list of anchors depending on Accept header containing application/json or other.
	 *
	 * SideEffects:
	 *  outputs the response to output buffer, so can't echo ReplicantProgressLogEntry here.
	 *
	 * @param SS_HTTPRequest $request
	 * @return the number of files listed
	 */
	protected function listFiles(SS_HTTPRequest $request)
	{
		return ReplicantActionListFiles::create()->checkPerm()->update(array())->execute($request->getAcceptMimetypes());
	}

	/**
	 * Read a file and output its contents.
	 *
	 * SideEffects:
	 *  Sets Content-Type: text/plain header.
	 *  Outputs file contents to output buffer via readfile, so can't echo ReplicantProgressLogEntry here.
	 *
	 * @param SS_HTTPRequest $request
	 * @internal param $id
	 * @return bool|int false if fails, otherwise number of bytes read
	 */
	protected function readFile(SS_HTTPRequest $request)
	{
		$options = array(
			'FileName' => $request->param('ID') . ($request->getExtension() ? '.' . $request->getExtension() : '')
		);
		return ReplicantActionReadFile::create()->checkPerm()->update($options)->execute();
	}


	/**
	 * Show basic help.
	 *
	 * @param SS_HTTPRequest $request
	 * @return string body containing help text
	 */
	public function help(SS_HTTPRequest $request)
	{
		$body = _t('Replicant.USAGE') . "\n\n";
		foreach (array_keys(static::$allowed_actions) as $action) {
			$body .= "$action:\t" . _t("Replicant." . strtoupper($action)) . "\n";
		}
		return $body;
	}



}