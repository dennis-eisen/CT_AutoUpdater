<?php
/**	ChurchTools - Auto Updater
 *	@copyright: Copyright (c) 2016, Dennis Eisen & Michael Lux
 *	@version: 27.05.2016, 17:08
 */

// Put in your own password hash here
define('HASH', 'PUT IN YOUR OWN HASH HERE');
// Modify to correct seafile server URL here
define('SEAFILE_DIR', '/d/xyz1234567/');

// Should be fine, except if JMR decides to change the location of the SeaFile server... ;)
define('SEAFILE_URL', 'https://seafile.churchtools.de' . SEAFILE_DIR);

header('Content-Type: text/plain; charset=utf-8');

// Password protection via QUERY_STRING
if (!password_verify($_SERVER['QUERY_STRING'], HASH)) {
	exit('Try harder! ;)');
}

echo '### ChurchTools - Auto Updater ###', "\n\n";

// Keyword for cronejob e-mail notification. No e-mail will be sent if detected!
register_shutdown_function(function () {
	if (error_get_last() === null) {
		echo ' |--> UpdateSuccessful';
	}
});

try {
    $updateArchive = __DIR__ . '/update.zip';
	// Download zip file from Seafile server
	for ($tries = 0; $tries < 3 && !file_exists($updateArchive); $tries++) {
        list($downloadURL, $ext) = getDownloadURL();
        $updateArchive = __DIR__ . '/update' . $ext;
		copy($downloadURL, $updateArchive);
	}
	// Extract files
	updateSystem($updateArchive);
} catch(Exception $e) {
	echo $e->getMessage() . "\n";
	return;
}

// Build download link
function getDownloadURL($url = 'https://seafile.churchtools.de/d/2ff6acb81e/') {
	$html = file_get_contents(SEAFILE_URL);
	if (preg_match('#href="' . SEAFILE_DIR . '(files/\?p=/churchtools-(3\..+?)(\.zip|\.tar\.gz))".*?<time[^<]+title="([^"]+?)"#s', $html, $matches)) {
		// Parse SeaFile timestamp
		$ts = DateTime::createFromFormat(DateTime::RFC2822, $matches[4])->getTimeStamp();
		// If SeaFile archive is older than modification date of constants.php, don't perform update
		if (file_exists(__DIR__ . '/system/includes/constants.php') && filemtime(__DIR__ . '/system/includes/constants.php') > $ts) {
			throw new Exception('ChurchTools is already up-to-date (' . $matches[2] . ')!');
		}
		return array($url . $matches[1] . '&dl=1', $matches[3]);
	} else {
		throw new Exception('No valid ChurchTools 3 download found in HTML!');
	}
}

// Recursive deleting of directorys
function delTree($dir) { 
	$files = array_diff(scandir($dir), ['.','..']); 
	foreach ($files as $file) { 
		is_dir("$dir/$file") ? delTree("$dir/$file") : unlink("$dir/$file"); 
	}
	return rmdir($dir);
} 

// Extract 'system' and 'index.php' or trigger error
function updateSystem($updateArchive) {
	$zip = new PharData($updateArchive);
    $zip->extractTo(__DIR__);
    
    if (!(file_exists(__DIR__ . '/churchtools') && is_dir(__DIR__ . '/churchtools'))) {
        trigger_error('The ZIP archive does not contain directory "churchtools", or creation failed!', E_USER_ERROR);
        throw new Exception('The ZIP archive does not contain directory "churchtools", or creation failed!');
    }
    
    // Check if directory system exists, if yes, delete it
    if (file_exists(__DIR__ . '/system')) delTree(__DIR__ . '/system');
    
    rename(__DIR__ . '/churchtools/system', __DIR__ . '/system');
    rename(__DIR__ . '/churchtools/index.php', __DIR__ . '/index.php');
    delTree(__DIR__ . '/churchtools');
    
	if (file_exists($updateArchive)) {
		unlink($updateArchive);
	}
}
