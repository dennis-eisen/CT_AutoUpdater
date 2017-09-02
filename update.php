<?php
/**
 * ChurchTools - Auto Updater
 * @copyright: Copyright (c) 2016, Dennis Eisen & Michael Lux
 * @version  : 29.05.2016
 */

header('Content-Type: text/plain; charset=utf-8');

// Pushover and Pushbullet integration
if (file_exists('push.inc.php')) {
    require 'push.inc.php';
}

// Put in your own password hash here
define('HASH',          '...');
// Modify to correct seafile server URL here
define('SEAFILE_DIR',   'd/.../');
// Should be fine, except if JMR decides to change the location of the SeaFile server... ;)
define('SEAFILE_URL',   'https://seafile.churchtools.de/' . SEAFILE_DIR);

echo '### ChurchTools - Auto Updater ###', "\n\n";

// Password protection via QUERY_STRING
if (!password_verify($_SERVER['QUERY_STRING'], HASH)) {
    exit('Try harder! ;)');
}

// Keyword for cronejob e-mail notification. No e-mail will be sent if detected!
register_shutdown_function(function () {
    if (error_get_last() === null) {
        echo ' |--> UpdateSuccessful';
    }
});

$lockFile     = __DIR__ . '/ctupdate.lock';
$ignoreLock   = false;
$acquiredLock = false;
try {
    // Check whether lock should be ignored because it's old (older than 10 minutes)
    if (file_exists($lockFile) && filemtime($lockFile) < time() - 600) {
        echo 'Lock is older than 10 minutes, continue ignoring lock.', "\n";
        $ignoreLock = true;
    }
    $lock = fopen($lockFile, 'w');
    // Proceed if lock can be acquired or lock should be ignored
    $acquiredLock = flock($lock, LOCK_EX | LOCK_NB);
    if (!$acquiredLock && !$ignoreLock) {
        throw new Exception('Update already in progress!');
    }

    $updateArchive = __DIR__ . '/churchtools-LATEST.tar.gz';
    // If Update is directly installed from local developer build
    if (file_exists($updateArchive)) {
        define('CT_VERSION', 'Developer-Build');
    }
    // Download zip file from Seafile server
    for ($tries = 0; $tries < 3 && !file_exists($updateArchive); $tries++) {
        list($downloadURL, $ext) = getDownloadURL();
        $updateArchive = __DIR__ . '/update' . $ext;
        copy($downloadURL, $updateArchive);
    }
    // Extract files
    updateSystem($updateArchive);
} catch (Exception $e) {
    echo $e->getMessage(), "\n";
} finally {
    if ($acquiredLock || $ignoreLock) {
        // Unlock script if lock was actually acquired or ignored
        if (isset($lock)) {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
        unlink($lockFile);
    } else if (isset($lock)) {
        // Always close the file handle
        fclose($lock);
    }
}

// Build download link
function getDownloadURL($url = SEAFILE_URL) {
    $html = file_get_contents($url);
    if (preg_match('#href="/d/2ff6acb81e/(files/\?p=/churchtools-(3\..+?)(\.zip|\.tar\.gz))"'
        . '.*?<time[^<]+title="([^"]+?)"#s', $html, $matches)) {
        define('CT_VERSION', $matches[2]);
        // Parse SeaFile timestamp
        $ts = DateTime::createFromFormat(DateTime::RFC2822, $matches[4])->getTimeStamp();
        // If SeaFile archive is older than modification date of constants.php, don't perform update
        if (file_exists(__DIR__ . '/system/includes/constants.php') &&
            filemtime(__DIR__ . '/system/includes/constants.php') > $ts) {
            throw new Exception('ChurchTools is already up-to-date (' . $matches[2] . ')!');
        }
        return array($url . $matches[1] . '&dl=1', $matches[3]);
    } else {
        if (function_exists('push')) {
            push('[Fehler] Download', "Kein gültiger ChurchTools 3 Download im HTML gefunden!"
                . " <span style=\"color:red\">$url</span>", 1);
        }
        throw new Exception('No valid ChurchTools 3 download found in HTML!');
    }
}

// Recursive deleting of directorys
function delTree($dir) {
    $files = array_diff(scandir($dir), ['.', '..']);
    foreach ($files as $file) {
        is_dir("$dir/$file") ? delTree("$dir/$file") : unlink("$dir/$file");
    }
    return rmdir($dir);
}

function makeBackup() {
    // Root folder
    $root = realpath(__DIR__);

    // Initialize archive object
    $zip = new ZipArchive();
    $zip->open('backup_' . time() . '.zip', ZipArchive::CREATE | ZipArchive::OVERWRITE);

    // Backup systems folder
    if (file_exists(__DIR__ . '/system')) {
        // Recursive directory iterator for "system"
        /** @var SplFileInfo[] $files */
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root . '/system'),
            RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($files as $file) {
            // Skip directories (they would be added automatically)
            if (!$file->isDir()) {
                // Get real and relative path for current file
                $filePath = $file->getRealPath();
                $relativePath = substr($filePath, strlen($root) + 1);

                // Add current file to archive
                $zip->addFile($filePath, $relativePath);
            }
        }
    }

    // Backup index.php
    if (file_exists(__DIR__ . '/index.php')) {
        $zip->addFile(__DIR__ . '/index.php', 'index.php');
    }

    // Close ZIP, create archive
    $zip->close();
}

// Extract 'system' and 'index.php' or trigger error
function updateSystem($updateArchive) {
    $zip = new PharData($updateArchive);
    $zip->extractTo(__DIR__);
    $needle = 'churchtools';
    $dirName = null;
    foreach (scandir('phar:///' . $updateArchive) as $entry) {
        if (substr($entry, 0, strlen($needle)) === $needle) {
            $dirName = $entry;
        }
    }

    if (!(file_exists(__DIR__ . '/' . $dirName) && is_dir(__DIR__ . '/' . $dirName))) {
        trigger_error('The ZIP archive does not contain directory "churchtools", or creation failed!',
            E_USER_ERROR);
        if (function_exists('push')) {
            push('[Fehler] ZIP', 'Das Verzeichnis "churchtools" fehlt im ZIP Archiv!', 1);
        }
        throw new Exception('The ZIP archive does not contain directory "churchtools", or creation failed!');
    }

    // Check if directory system and index.php exist, if yes, rename them for backup
    makeBackup();
    if (file_exists(__DIR__ . '/system')) {
        delTree(__DIR__ . '/system');
    }
    if (file_exists(__DIR__ . '/index.php')) {
        unlink(__DIR__ . '/index.php');
    }

    rename(__DIR__ . '/' . $dirName . '/system', __DIR__ . '/system');
    rename(__DIR__ . '/' . $dirName . '/index.php', __DIR__ . '/index.php');
    delTree(__DIR__ . '/' . $dirName);

    if (function_exists('push')) {
        push('Update erfolgreich', 'Ein Update wurde erfolgreich installiert: <b>' . CT_VERSION . '</b>!');
    }

    if (file_exists($updateArchive)) {
        unlink($updateArchive);
    }
}
