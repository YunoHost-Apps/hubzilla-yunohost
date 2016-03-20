<?php
/**
 * @file include/reddav.php
 * @brief some DAV related functions for Hubzilla.
 *
 * This file contains some functions which did not fit into one of the RedDAV
 * classes.
 *
 * The extended SabreDAV classes you will find in the RedDAV namespace under
 * @ref includes/RedDAV/.
 * The original SabreDAV classes you can find under @ref vendor/sabre/dav/.
 * We need to use SabreDAV 1.8.x for PHP5.3 compatibility. SabreDAV >= 2.0
 * requires PHP >= 5.4.
 *
 * @todo split up the classes into own files.
 *
 * @link http://github.com/friendica/red
 * @license http://opensource.org/licenses/mit-license.php The MIT License (MIT)
 */

use Sabre\DAV;
use Zotlabs\Storage;

require_once('vendor/autoload.php');
require_once('include/attach.php');
//require_once('Zotlabs/Storage/File.php');
//require_once('Zotlabs/Storage/Directory.php');
//require_once('Zotlabs/Storage/BasicAuth.php');

/**
 * @brief Returns an array with viewable channels.
 *
 * Get a list of RedDirectory objects with all the channels where the visitor
 * has <b>view_storage</b> perms.
 *
 * @todo Is there any reason why this is not inside RedDirectory class?
 * @fixme function name looks like a class name, should we rename it?
 *
 * @param RedBasicAuth &$auth
 * @return array RedDirectory[]
 */
function RedChannelList(&$auth) {
	$ret = array();

	$r = q("SELECT channel_id, channel_address FROM channel WHERE channel_removed = 0 AND channel_system = 0 AND NOT (channel_pageflags & %d)>0",
		intval(PAGE_HIDDEN)
	);

	if ($r) {
		foreach ($r as $rr) {
			if (perm_is_allowed($rr['channel_id'], $auth->observer, 'view_storage')) {
				logger('found channel: /cloud/' . $rr['channel_address'], LOGGER_DATA);
				// @todo can't we drop '/cloud'? It gets stripped off anyway in RedDirectory
				$ret[] = new Zotlabs\Storage\Directory('/cloud/' . $rr['channel_address'], $auth);
			}
		}
	}
	return $ret;
}


/**
 * @brief TODO what exactly does this function?
 *
 * Array with all RedDirectory and RedFile DAV\Node items for the given path.
 *
 * @todo Is there any reason why this is not inside RedDirectory class? Seems
 * only to be used there and we could simplify it a bit there.
 * @fixme function name looks like a class name, should we rename it?
 *
 * @param string $file path to a directory
 * @param RedBasicAuth &$auth
 * @returns null|array \Sabre\DAV\INode[]
 * @throw \Sabre\DAV\Exception\Forbidden
 * @throw \Sabre\DAV\Exception\NotFound
 */
function RedCollectionData($file, &$auth) {
	$ret = array();

	$x = strpos($file, '/cloud');
	if ($x === 0) {
		$file = substr($file, 6);
	}

	// return a list of channel if we are not inside a channel
	if ((! $file) || ($file === '/')) {
		return RedChannelList($auth);
	}

	$file = trim($file, '/');
	$path_arr = explode('/', $file);

	if (! $path_arr)
		return null;

	$channel_name = $path_arr[0];

	$r = q("SELECT channel_id FROM channel WHERE channel_address = '%s' LIMIT 1",
		dbesc($channel_name)
	);

	if (! $r)
		return null;

	$channel_id = $r[0]['channel_id'];
	$perms = permissions_sql($channel_id);

	$auth->owner_id = $channel_id;

	$path = '/' . $channel_name;

	$folder = '';
	$errors = false;
	$permission_error = false;

	for ($x = 1; $x < count($path_arr); $x++) {
		$r = q("SELECT id, hash, filename, flags, is_dir FROM attach WHERE folder = '%s' AND filename = '%s' AND uid = %d AND is_dir != 0 $perms LIMIT 1",
			dbesc($folder),
			dbesc($path_arr[$x]),
			intval($channel_id)
		);
		if (! $r) {
			// path wasn't found. Try without permissions to see if it was the result of permissions.
			$errors = true;
			$r = q("select id, hash, filename, flags, is_dir from attach where folder = '%s' and filename = '%s' and uid = %d and is_dir != 0 limit 1",
				dbesc($folder),
				basename($path_arr[$x]),
				intval($channel_id)
			);
			if ($r) {
				$permission_error = true;
			}
			break;
		}

		if ($r && intval($r[0]['is_dir'])) {
			$folder = $r[0]['hash'];
			$path = $path . '/' . $r[0]['filename'];
		}
	}

	if ($errors) {
		if ($permission_error) {
			throw new DAV\Exception\Forbidden('Permission denied.');
		} else {
			throw new DAV\Exception\NotFound('A component of the request file path could not be found.');
		}
	}

	// This should no longer be needed since we just returned errors for paths not found
	if ($path !== '/' . $file) {
		logger("Path mismatch: $path !== /$file");
		return NULL;
	}
	if(ACTIVE_DBTYPE == DBTYPE_POSTGRES) {
		$prefix = 'DISTINCT ON (filename)';
		$suffix = 'ORDER BY filename';
	} else {
		$prefix = '';
		$suffix = 'GROUP BY filename';
	}
	$r = q("select $prefix id, uid, hash, filename, filetype, filesize, revision, folder, flags, is_dir, created, edited from attach where folder = '%s' and uid = %d $perms $suffix",
		dbesc($folder),
		intval($channel_id)
	);

	foreach ($r as $rr) {
		//logger('filename: ' . $rr['filename'], LOGGER_DEBUG);
		if (intval($rr['is_dir'])) {
			$ret[] = new Zotlabs\Storage\Directory($path . '/' . $rr['filename'], $auth);
		} else {
			$ret[] = new Zotlabs\Storage\File($path . '/' . $rr['filename'], $rr, $auth);
		}
	}

	return $ret;
}


/**
 * @brief TODO What exactly is this function for?
 *
 * @fixme function name looks like a class name, should we rename it?
 *
 * @param string $file
 *  path to file or directory
 * @param RedBasicAuth &$auth
 * @param boolean $test (optional) enable test mode
 * @return RedFile|RedDirectory|boolean|null
 * @throw \Sabre\DAV\Exception\Forbidden
 */
function RedFileData($file, &$auth, $test = false) {
	logger($file . (($test) ? ' (test mode) ' : ''), LOGGER_DATA);

	$x = strpos($file, '/cloud');
	if ($x === 0) {
		$file = substr($file, 6);
	}
	else {
		$x = strpos($file,'/dav');
		if($x === 0)
			$file = substr($file,4);
	}


	if ((! $file) || ($file === '/')) {
		return new Zotlabs\Storage\Directory('/', $auth);
	}

	$file = trim($file, '/');

	$path_arr = explode('/', $file);

	if (! $path_arr)
		return null;

	$channel_name = $path_arr[0];

	$r = q("select channel_id from channel where channel_address = '%s' limit 1",
		dbesc($channel_name)
	);

	if (! $r)
		return null;

	$channel_id = $r[0]['channel_id'];

	$path = '/' . $channel_name;

	$auth->owner_id = $channel_id;

	$permission_error = false;

	$folder = '';

	require_once('include/security.php');
	$perms = permissions_sql($channel_id);

	$errors = false;

	for ($x = 1; $x < count($path_arr); $x++) {		
		$r = q("select id, hash, filename, flags, is_dir from attach where folder = '%s' and filename = '%s' and uid = %d and is_dir != 0 $perms",
			dbesc($folder),
			dbesc($path_arr[$x]),
			intval($channel_id)
		);

		if ($r && intval($r[0]['is_dir'])) {
			$folder = $r[0]['hash'];
			$path = $path . '/' . $r[0]['filename'];
		}
		if (! $r) {
			$r = q("select id, uid, hash, filename, filetype, filesize, revision, folder, flags, is_dir, os_storage, created, edited from attach 
				where folder = '%s' and filename = '%s' and uid = %d $perms order by filename limit 1",
				dbesc($folder),
				dbesc(basename($file)),
				intval($channel_id)
			);
		}
		if (! $r) {
			$errors = true;
			$r = q("select id, uid, hash, filename, filetype, filesize, revision, folder, flags, is_dir, os_storage, created, edited from attach 
				where folder = '%s' and filename = '%s' and uid = %d order by filename limit 1",
				dbesc($folder),
				dbesc(basename($file)),
				intval($channel_id)
			);
			if ($r)
				$permission_error = true;
		}
	}

	if ($path === '/' . $file) {
		if ($test)
			return true;
		// final component was a directory.
		return new Zotlabs\Storage\Directory($file, $auth);
	}

	if ($errors) {
		logger('not found ' . $file);
		if ($test)
			return false;
		if ($permission_error) {
			logger('permission error ' . $file);
			throw new DAV\Exception\Forbidden('Permission denied.');
		}
		return;
	}

	if ($r) {
		if ($test)
			return true;

		if (intval($r[0]['is_dir'])) {
			return new Zotlabs\Storage\Directory($path . '/' . $r[0]['filename'], $auth);
		} else {
			return new Zotlabs\Storage\File($path . '/' . $r[0]['filename'], $r[0], $auth);
		}
	}
	return false;
}