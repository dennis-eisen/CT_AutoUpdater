<?php 

/**	ChurchTools - Auto Updater
 *	@copyright: Copyright (c) 2016, Dennis Eisen & Michael Lux
 *	@version: 22.05.2016, 02:10
 */

header('Content-Type: text/plain; charset=utf-8');
// Put in your own hash here
define('HASH', 'PUT IN YOUR OWN HASH HERE');

echo '### ChurchTools - Auto Updater ###', "\n\n";

// Password protection via QUERY_STRING
if (!password_verify($_SERVER['QUERY_STRING'], HASH)) {
	exit('Try harder! ;)');
}

// Require 'constants.php' for current CurchTools version
if (file_exists(__DIR__ . '/system/includes/constants.php')) {
	require __DIR__ . '/system/includes/constants.php';
}

// Keyword for cronejob e-mail notification. No e-mail will be sent if detected!
register_shutdown_function(function () {
	if (error_get_last() === null) {
		echo ' |--> UpdateSuccessful';
	}
});

try {
	$updateZip = __DIR__ . '/update.zip';
	// Download zip file from Seafile server
	for ($tries = 0; $tries < 3 && !file_exists($updateZip); $tries++) {
		copy(getDownloadURL(), $updateZip);
	}
	// Extract files
	updateSystem($updateZip);
} catch(Exception $e) {
	echo $e->getMessage() . "\n";
	return;
}

// Build download link
function getDownloadURL($url = 'https://seafile.churchtools.de/d/2ff6acb81e/') {
	$html = file_get_contents($url);
	if (preg_match('#href="/d/2ff6acb81e/(files/\?p=/churchtools-(3\..+)\.zip)"#', $html, $matches)) {
		if (defined('CT_VERSION') && $matches[2] === CT_VERSION) {
			throw new Exception('ChurchTools is already up-to-date (' . $matches[2] . ')!');
		}
		return $url . $matches[1] . '&dl=1';
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
function updateSystem($zipPath) {
	$zip = new ZipArchive;
	$res = $zip->open($zipPath);
	
	if ($res === true) {
		$zip->extractTo(__DIR__);
		if (!(file_exists(__DIR__ . '/churchtools') && is_dir(__DIR__ . '/churchtools'))) {
			trigger_error('The ZIP archive does not contain directory "churchtools", or creation failed!', E_USER_ERROR);
			throw new Exception('The ZIP archive does not contain directory "churchtools", or creation failed!');
		}
		
		// Check if directory system exists, if yes, delete it
		if (file_exists(__DIR__ . '/system') == true) {
			delTree(__DIR__ . '/system');
		}
		
		rename(__DIR__ . '/churchtools/system', __DIR__ . '/system');
		rename(__DIR__ . '/churchtools/index.php', __DIR__ . '/index.php');
		delTree(__DIR__ . '/churchtools');
		$zip->close();
	}
	if (file_exists($zipPath)) {
		unlink($zipPath);
	}
}
