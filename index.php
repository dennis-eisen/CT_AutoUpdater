<?php
/**
 * ChurchTools - Auto Updater
 * @copyright: Copyright (c) 2020, Dennis Eisen & Michael Lux
 * @version  : 2020-03-07
 */

// Disable PHP time limit
set_time_limit(0);
// Increase PHP memory limit
ini_set('memory_limit', '1G');

header('Content-Type: text/plain; charset=utf-8');

// optional, add your data in a separat file
if (file_exists('config.php')) {
    require 'config.php';
}
// not configured - show config.php content with Hash
if (!defined('HASH')) {
    if (!empty($_SERVER['QUERY_STRING'])) {
        $hash = password_hash($_SERVER['QUERY_STRING'], PASSWORD_BCRYPT, array('cost' => 12));
        echo <<<EOF
<?php
// Put in your own password hash here
define('HASH', '$hash');
// Modify to correct SeaFile code here!
define('SEAFILE_CODE', 'xyz1234567');

// Should be fine, except if JMR decides to change the location of the SeaFile server... ;) - end with slash
define('SEAFILE_HOST', 'https://seafile.church.tools/');
// Switch message pushing via Pushover/PushBullet on/off
define('ENABLE_PUSH', false);
// the root directory of Churchtools - default is the parent of this
define('CT_ROOT_DIR', __DIR__ . '/..');
// Destination for the backup archives
define('BACKUP_DIR', __DIR__ . '/../_BACKUP');
// show more infos
define('DEBUG', false);
EOF;
    } else {
        echo "ChurchTools AutoUpdater is not configured\n";
        echo "*****************************************\n";
        echo "To configure the auto updater, call this script /update/index.php?YOUR_OWN_SECRET_PASSORD and "
                . "save the given data as config.php in your update directory to generate a secret hash and save it.";
    }
    exit();
}

// Pushover and Pushbullet integration
if (defined('ENABLE_PUSH') && ENABLE_PUSH && file_exists('push.inc.php')) {
    require 'push.inc.php';
}

if (!defined('SEAFILE_CODE') || SEAFILE_CODE === 'xyz1234567') {
    echo "ChurchTools AutoUpdater is not configured\n";
    echo "*****************************************\n";
    echo "Please check your settings in config.php, especially the SEAFILE_CODE!";
    exit();
}

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

$lockFile = __DIR__ . '/ctupdate.lock';
$ignoreLock = false;
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

    // Update is directly installed from local developer build
    $updateArchive = __DIR__ . '/churchtools-LATEST.tar.gz';
    $version = 'Developer-Build';
    // If no local dev build found, download ZIP file from SeaFile server
    for ($tries = 0; $tries < 3 && !file_exists($updateArchive); $tries++) {
        list($downloadURL, $version, $ext) = getDownloadURL();
        $updateArchive = __DIR__ . '/update' . $ext;
        copy($downloadURL, $updateArchive);
    }
    // Extract files
    updateSystem($updateArchive, $version);
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
    } elseif (isset($lock)) {
        // Always close the file handle
        fclose($lock);
    }
}

/**
 * Build download link
 * @return array
 * @throws Exception If something went wrong with the download
 */
function getDownloadURL() {
    $jsonUrl = SEAFILE_HOST . 'api/v2.1/share-links/' . SEAFILE_CODE . '/dirents';
    $json = json_decode(file_get_contents($jsonUrl));
    if ($json === null || !isset($json->dirent_list)) {
        if (function_exists('push')) {
            push('[Fehler] Download', "Kein gültiger ChurchTools 3 Download im JSON gefunden!"
                    . " <span style=\"color:red\">$jsonUrl</span>", 1);
        }
        throw new Exception('No valid ChurchTools 3 download found in JSON!');
    }
    // Find ChurchTools archive
    $item = null;
    $version = null;
    $ext = null;
    $file = null;
    $matches = [];
    foreach ($json->dirent_list as $item) {
        if (!isset($item->file_name) ||
                !preg_match('/churchtools-(3\..+?)(\.zip|\.tar\.gz)/', $item->file_name, $matches)) {
            continue;
        }
        list(, $version, $ext) = $matches;
        $file = $item->file_path;
        break;
    }

    // dont't find a matching file?
    if (!isset($version)) {
        if (function_exists('push')) {
            push('[Fehler] Download', "Kein gültiger ChurchTools 3 Download in der Dateiliste gefunden!"
                    . " <span style=\"color:red\">$jsonUrl</span>", 1);
        }
        throw new Exception('No valid ChurchTools 3 download found in FileList!');
    }

    $downloadUrl = SEAFILE_HOST . 'd/' . SEAFILE_CODE . '/files/?p=' . $file . '&dl=1';
    debugLog("Checking whether $version from $downloadUrl is newer than installed version...");
    // Parse SeaFile timestamp
    $timeStamp = DateTime::createFromFormat(DateTime::ATOM, $item->last_modified)->getTimeStamp();
    // If SeaFile archive is older than modification date of constants.php, don't perform update
    if (file_exists(CT_ROOT_DIR . '/system/includes/constants.php') &&
            filemtime(CT_ROOT_DIR . '/system/includes/constants.php') > $timeStamp) {
        throw new Exception('ChurchTools is already up-to-date (' . $version . ')!');
    }
    return [$downloadUrl, $version, $ext];
}

// Recursive deleting of directorys
function delTree($dir) {
    $files = array_diff(scandir($dir), ['.', '..']);
    foreach ($files as $file) {
        is_dir("$dir/$file") ? delTree("$dir/$file") : unlink("$dir/$file");
    }
    return rmdir($dir);
}

function makeBackup($dir = CT_ROOT_DIR, $dest_dir = BACKUP_DIR) {
    debugLog("Backup $dir to $dest_dir...");
    // Root folder
    $root = realpath($dir);
    if (!is_dir($dest_dir)) {
        mkdir($dest_dir);
    }

    // Initialize archive object
    $zip = new ZipArchive();
    $backup_file = $dest_dir . '/backup_' . time() . '.zip';
    $zip->open($backup_file, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    debugLog("Save backup to $backup_file...");

    // Backup systems folder
    if (file_exists($root . '/system')) {
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
    if (file_exists($dir . '/index.php')) {
        $zip->addFile($dir . '/index.php', 'index.php');
    }

    // Close ZIP, create archive
    $zip->close();
}

/**
 * Extract 'system' and 'index.php'
 * @param string $updateArchive Path to archive to unpack
 * @param string $version ChurchTools version from filename
 * @param string $targetDir Target directory for extraction
 * @throws Exception If the extraction process encountered an error
 */
function updateSystem($updateArchive, $version, $targetDir = CT_ROOT_DIR) {
    debugLog("Extract $updateArchive to $targetDir...");
    $zip = new PharData($updateArchive);
    $zip->extractTo($targetDir);
    $needle = 'churchtools';
    $dirName = null;
    foreach (scandir('phar:///' . $updateArchive) as $entry) {
        if (substr($entry, 0, strlen($needle)) === $needle) {
            $dirName = $entry;
        }
    }
    debugLog("... from directory $dirName in archive file");

    if (!(file_exists($targetDir . '/' . $dirName) && is_dir($targetDir . '/' . $dirName))) {
        trigger_error('The ZIP archive does not contain directory "churchtools", or creation failed!',
                E_USER_ERROR);
        if (function_exists('push')) {
            push('[Fehler] ZIP', 'Das Verzeichnis "churchtools" fehlt im ZIP Archiv!', 1);
        }
        // cleanup
        if (is_dir($targetDir . '/' . $dirName)) {
            delTree($targetDir . '/' . $dirName);
        }
        throw new Exception('The ZIP archive does not contain directory "churchtools", or creation failed!');
    }

    // Check if directory system and index.php exist, if yes, rename them for backup
    makeBackup();
    debugLog("Delete old files and dirs...");
    if (file_exists($targetDir . '/system')) {
        delTree($targetDir . '/system');
    }
    if (file_exists($targetDir . '/index.php')) {
        unlink($targetDir . '/index.php');
    }

    debugLog("Move new files/dirs to $targetDir...");
    rename($targetDir . '/' . $dirName . '/system', $targetDir . '/system');
    rename($targetDir . '/' . $dirName . '/index.php', $targetDir . '/index.php');
    delTree($targetDir . '/' . $dirName);

    if (function_exists('push')) {
        push('Update erfolgreich', 'Ein Update wurde erfolgreich installiert: <b>' . $version . '</b>!');
    }

    debugLog("Remove updateArchive...");
    if (file_exists($updateArchive)) {
        unlink($updateArchive);
    }
}

function debugLog($msg) {
    if (DEBUG) {
        echo "$msg\n";
    }
}
