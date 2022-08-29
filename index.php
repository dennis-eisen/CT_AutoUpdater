<?php
/**
 * ChurchTools - Auto Updater 4.0
 * @copyright: Copyright (c) 2022, Michael Lux & Dennis Eisen
 * @version  : 2022-08-29
 */

// Enable error output
error_reporting(E_ALL);
ini_set('display_errors', '1');
// Disable PHP time limit
set_time_limit(0);
// Increase PHP memory limit
ini_set('memory_limit', '1G');

header('Content-Type: text/plain; charset=utf-8');

// Optional, add your data in a separat file
if (file_exists('config.php')) {
    require 'config.php';
}
// Not configured - show config.php content with Hash
if (!defined('HASH')) {
    if (!empty($_SERVER['QUERY_STRING'])) {
        $hash = password_hash($_SERVER['QUERY_STRING'], PASSWORD_BCRYPT, array('cost' => 12));
        echo <<<EOF
<?php
// Put in your own password hash here
const HASH = '$hash';
// Modify to correct SeaFile code here!
const SEAFILE_CODE = 'xyz1234567';

// Should be fine, except if JMR decides to change the location of the SeaFile server... ;) - end with slash
const SEAFILE_HOST = 'https://seafile.church.tools/';
// Switch message pushing via Pushover on/off
const ENABLE_PUSH = false;
// The root directory of Churchtools - default is the parent of this file's directory
const CT_ROOT_DIR = __DIR__ . '/..';
// Destination for the backup archives
const BACKUP_DIR = __DIR__ . '/../_BACKUP';
// Show debug information
const DEBUG = false;
// Use CLI for extraction
const NATIVE_EXTRACT = false;
EOF;
    } else {
        echo "ChurchTools AutoUpdater is not configured\n";
        echo "*****************************************\n";
        echo "To configure the auto updater, call this script /update/index.php?YOUR_OWN_SECRET_PASSWORD "
            . "to generate a secret hash and save the output with your desired configuration "
            . "as config.php in your update directory.";
    }
    exit();
}

// Pushover integration
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

    // Normalized ChurchTools root directory
    $ctRoot = realpath(CT_ROOT_DIR);
    // Temporary directory for extraction
    $tmpDir = __DIR__ . '/tmp';
    try {
        // Update is directly installed from local developer build archive, if existing
        $updateArchive = __DIR__ . '/churchtools-LATEST.tar.gz';
        $version = 'Developer-Build';
        // If no local dev build archive found, download archive from SeaFile server
        for ($tries = 0; $tries < 3 && !file_exists($updateArchive); $tries++) {
            list($downloadURL, $version, $ext) = getDownloadURL($ctRoot);
            $updateArchive = __DIR__ . '/update' . $ext;
            copy($downloadURL, $updateArchive);
        }

        // Extract files
        $updateDir = extractArchive($updateArchive, $tmpDir);

        // Create a backup of system folder and index.php
        makeBackup($ctRoot);

        debugLog("Delete old files and dirs...");
        if (file_exists($ctRoot . '/system')) {
            delTree($ctRoot . '/system');
        }
        if (file_exists($ctRoot . '/index.php')) {
            unlink($ctRoot . '/index.php');
        }

        debugLog('Move new files/dirs to ' . $ctRoot . '...');
        rename($updateDir . '/system', $ctRoot . '/system');
        rename($updateDir . '/index.php', $ctRoot . '/index.php');
        // Set mtime of constants.php to avoid endless updating
        touch($ctRoot . '/system/includes/constants.php');
    } finally {
        if (is_dir($tmpDir)) {
            debugLog("Remove temporary extraction archive...");
            delTree($tmpDir);
        }
        if (file_exists($updateArchive)) {
            debugLog("Remove updateArchive...");
            unlink($updateArchive);
        }
    }

    if (error_get_last() === null) {
        if (function_exists('push')) {
            push('Update successful', 'Update to version <b>' . $version . '</b> has been successfully applied!');
        }
        echo ' |--> UpdateSuccessful';
    } else {
        if (function_exists('push')) {
            push('Update with error(s)', 'Update completed with one or more error(s). Last error message: '
                . error_get_last()['message']);
        }
    }
} catch (Exception $e) {
    echo $e->getMessage(), "\n";
    if (function_exists('push')) {
        push('Update failed', 'Update aborted due to the following exception: ' . $e->getMessage());
    }
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
function getDownloadURL(string $ctRoot = CT_ROOT_DIR): array {
    $jsonUrl = SEAFILE_HOST . 'api/v2.1/share-links/' . SEAFILE_CODE . '/dirents';
    $json = json_decode(file_get_contents($jsonUrl));
    if ($json === null || !isset($json->dirent_list)) {
        throw new Exception('No valid contents list found in JSON from ' . $jsonUrl . '!');
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

    // Didn't find a matching file?
    if (!isset($version)) {
        throw new Exception('No valid ChurchTools 3 download found in FileList from ' . $jsonUrl . '!');
    }

    $downloadUrl = SEAFILE_HOST . 'd/' . SEAFILE_CODE . '/files/?p=' . $file . '&dl=1';
    debugLog("Checking whether $version from $downloadUrl is newer than installed version...");
    // Parse SeaFile timestamp
    $timeStamp = DateTime::createFromFormat(DateTimeInterface::ATOM, $item->last_modified)->getTimeStamp();
    // If SeaFile archive is older than modification date of constants.php, don't perform update
    if (file_exists($ctRoot . '/system/includes/constants.php') &&
            filemtime($ctRoot . '/system/includes/constants.php') > $timeStamp) {
        throw new Exception('ChurchTools is already up-to-date (' . $version . ')!');
    }
    return [$downloadUrl, $version, $ext];
}

// Recursive deleting of directorys
function delTree($dir): bool {
    $files = array_diff(scandir($dir), ['.', '..']);
    foreach ($files as $file) {
        is_dir("$dir/$file") ? delTree("$dir/$file") : unlink("$dir/$file");
    }
    return rmdir($dir);
}

function makeBackup($dir = CT_ROOT_DIR, $dest_dir = BACKUP_DIR): void {
    // Root folder
    $root = realpath($dir);
    if (!is_dir($dest_dir)) {
        mkdir($dest_dir, 0755, true);
    }

    // Initialize archive object
    $zip = new ZipArchive();
    $backup_file = $dest_dir . '/backup_' . time() . '.zip';
    $zip->open($backup_file, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    debugLog("Backup $dir to $backup_file...");

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
 * @throws Exception If extraction of update failed
 */
function extractArchive(string $updateArchive, string $targetDir): string {
    if (!is_dir($targetDir)) {
        mkdir($targetDir, 0755, true);
    }
    debugLog("Extract $updateArchive to $targetDir...");
    if (defined('NATIVE_EXTRACT') && NATIVE_EXTRACT) {
        $archivePath = realpath($updateArchive);
        $targetPath = realpath($targetDir);
        $ret = -1;
        if (str_ends_with($updateArchive, '.zip')) {
            system('unzip ' . escapeshellarg($archivePath) . ' -d ' . escapeshellarg($targetPath), $ret);
        } else {
            system('tar -xf ' . escapeshellarg($archivePath) . ' -C ' . escapeshellarg($targetPath), $ret);
        }
        if ($ret !== 0) {
            throw new Exception('Native extraction failed with error code ' . $ret . '!');
        }
    } else {
        if (str_ends_with($updateArchive, '.zip')) {
            $zip = new ZipArchive();
            $zip->open($updateArchive);
            $zip->extractTo($targetDir);
        } else {
            $tar = new PharData($updateArchive);
            $tar->extractTo($targetDir);
            $files = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($targetDir),
                RecursiveIteratorIterator::LEAVES_ONLY
            );
            /** @var SplFileInfo $file */
            foreach ($files as $file) {
                $filename = $file->getFilename();
                $filenameLength = strlen($filename);
                if ($filenameLength >= 99 && $filenameLength <= 100 && !str_contains($filename, '.')) {
                    throw new Exception('Detected filename with length >= 99 and without file extension.'
                        . ' This indicates an improperly extracted TAR archive. Exit update!');
                }
            }
        }
    }

    $needle = 'churchtools';
    foreach (scandir($targetDir) as $entry) {
        if (str_starts_with($entry, $needle)) {
            $updateDir = $targetDir . '/' . $entry;
            break;
        }
    }
    if (!(isset($updateDir) && file_exists($updateDir) && is_dir($updateDir))) {
        throw new Exception('Archive does not contain a directory starting with "churchtools", or creation failed!');
    }
    debugLog("Found extracted update directory $updateDir");

    return $updateDir;
}

function debugLog($msg): void {
    if (DEBUG) {
        echo "$msg\n";
    }
}
