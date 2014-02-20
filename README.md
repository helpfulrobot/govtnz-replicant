# replicant

Module to replicate data between SilverStripe installations.

## Installation

The simplest way to install this module is with composer:  `composer require govtnz/replicant`

## Security

**NOTE** the current version of replicant stores database dumps in the `assets/replicant` directory.
It is highly recommended that you restrict access to this directory by adding a `.htaccess` file containing the following:

	Order deny,allow
	Deny from all

If you do not put a restriction like this in place then your database dumps will be accessible to anyone that can view your website. This will be addressed in the next stable version of the module.

## Configuration

See the replicant module _config.yml for settings which affect the way replicant behaves.

## Usage

Replicant is exposed as a tab in the CMS which uses ModelAdmin type functionality.

There are 5 tabs in the replicant UI, each which shows a history of actions performed, and a button to perform a new action per tab as follows:

### Dump Database

Click the 'Dump Database' button and fill in the fields then press 'Save'. If you select a remote server then the dump action will be performed on that server instead of locally.

All tables will be dumped excluding those specified in replicant config config::exclude_tables array which defaults to:

Member, MemberPassword, Roles, Group, Group_Members, Group_Roles, Permission, PermissionRole, PermissionRoleCode, ProgressLogEntry, ReplicantAction, ReplicantActionDump, ReplicantActionRestore, ReplicantActionListFiles, ReplicantActionFetch,ReplicantActionReadFile

### Fetch Remote Files

Click the 'Fetch Files' button to request that remote database dumps be transferred to the local server. If a filename is provided then only that file will transfer, otherwise all remote files which do not already exist locally will be copied. Providing a filename will overwrite any existing file as a way to force a bad transfer to recur.

### Restore Database Dump

Click the 'Restore Database' button and select a local file to restore. Clicking save will restore this file to the local database.

### List Files

Shows a log of 'list file' actions performed on this server by a remote server.

### Read File

Shows a log of file transfers made from this server to a remote server.



