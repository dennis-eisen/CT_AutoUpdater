<?php
// disable PHP timelimit
set_time_limit(0);

/**
 * ChurchTools - Auto Updater
 * @copyright: Copyright (c) 2016, Dennis Eisen & Michael Lux
 * @version  : 30.12.2019
 */
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
// Modify to correct seafile server URL here - end with slash
define('SEAFILE_DIR', 'd/xyz1234567/');
// JsonPath to the file containing the file list
define('SEAFILE_JSON_PATH', 'api/v2.1/share-links/xyz1234567/dirents');
// Should be fine, except if JMR decides to change the location of the SeaFile server... ;) - end with slash
// define('SEAFILE_HOST', 'https://seafile.churchtools.de/');
// switch Push on or off - without setting the push.inc was autodetected
// define('ENABLE_PUSH', false);
// the root directory of Churchtools - default is the parent of this
// define('CT_ROOT_DIR', __DIR__.'/..');
// Destination for the backup archives
// define('BACKUP_DIR', __DIR__.'/../_BACKUP');
// show more infos
// define('DEBUG', false);
EOF;
    } else {
        echo "ChurchTools AuotUpdater is unconfigured\n";
        echo "*****************************************\n";
        echo "To configure the auto updater, call this script /update/index.php?YOUR_OWN_SECRET_PASSORD and save the given data as config.php in your update directory to generate a secret hash and save it.";
    }
    die();
}


// Pushover and Pushbullet integration
if (defined('ENABLE_PUSH') && ENABLE_PUSH && file_exists('push.inc.php')) {
    require 'push.inc.php';
}

// Modify to correct seafile server URL here
if (!defined('SEAFILE_DIR')) {
    define('SEAFILE_DIR', 'd/.../');
}
// Modify to correct seafile server URL here
if (!defined('SEAFILE_JSON_PATH')) {
    define('SEAFILE_JSON_PATH', 'api/v2.1/share-links/.../dirents');
}
// Should be fine, except if JMR decides to change the location of the SeaFile server... ;)
if (!defined('SEAFILE_HOST')) {
    define('SEAFILE_HOST', 'https://seafile.church.tools/');
}
// the root directory of Churchtools
if (!defined('CT_ROOT_DIR')) {
    define('CT_ROOT_DIR', __DIR__.'/..');
}
// Destination for the backup archives
if (!defined('BACKUP_DIR')) {
    define('BACKUP_DIR', __DIR__.'/../_BACKUP');
}
if (!defined('DEBUG')) {
    define('DEBUG', false);
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
    } elseif (isset($lock)) {
        // Always close the file handle
        fclose($lock);
    }
}

// Build download link
function getDownloadURL() {
    $json = json_decode(file_get_contents(SEAFILE_HOST . SEAFILE_JSON_PATH), true);
    if (!$json || !isset($json['dirent_list'])) {
        if (function_exists('push')) {
            push('[Fehler] Download', "Kein gültiger ChurchTools 3 Download im JSON gefunden!"
                . " <span style=\"color:red\">$url</span>", 1);
        }
        throw new Exception('No valid ChurchTools 3 download found in JSON!');
    }
    // find curchtools file in filelist
    $matches = [];
    foreach ($json['dirent_list'] as $item) {
        if ($item['is_dir'] == true || !preg_match('/churchtools-(3\..+?)(\.zip|\.tar\.gz)/', $item['file_name'], $matches))
            continue;
        define('CT_VERSION', $matches[1]);
        $ext = $matches[2];
        $file = $item['file_path'];
        break; // don't look furhter, take first matched file
    }

    // dont't find a matching file?
    if (!defined('CT_VERSION')) {
        if (function_exists('push')) {
            push('[Fehler] Download', "Kein gültiger ChurchTools 3 Download im FileList gefunden!"
                . " <span style=\"color:red\">$url</span>", 1);
        }
        throw new Exception('No valid ChurchTools 3 download found in FileList!');
    }

    $download_url = SEAFILE_HOST . SEAFILE_DIR . 'files/?p=' . $file . '&dl=1';
    logMsg("find new Version " . CT_VERSION . " from $download_url");
    // Parse SeaFile timestamp
    $ts = DateTime::createFromFormat(DateTime::ATOM, $item['last_modified'])->getTimeStamp();
    // If SeaFile archive is older than modification date of constants.php, don't perform update
    if (file_exists(CT_ROOT_DIR . '/system/includes/constants.php') &&
        filemtime(CT_ROOT_DIR . '/system/includes/constants.php') > $ts) {
        throw new Exception('ChurchTools is already up-to-date (' . CT_VERSION . ')!');
    }
    return [$download_url, $ext];
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
    logMsg("backup $dir to $dest_dir");
    // Root folder
    $root = realpath($dir);
    if (!is_dir($dest_dir))
        mkdir($dest_dir);

    // Initialize archive object
    $zip = new ZipArchive();
    $backup_file = $dest_dir . '/backup_' . time() . '.zip';
    $zip->open($backup_file, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    logMsg ("save backupfile " . basename($backup_file));

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

// Extract 'system' and 'index.php' or trigger error
function updateSystem($updateArchive, $target_dir = CT_ROOT_DIR) {
    logMsg ("extract $updateArchive to $target_dir");
    $zip = new PharData($updateArchive);
    $zip->extractTo($target_dir);
    $needle = 'churchtools';
    $dirName = null;
    foreach (scandir('phar:///' . $updateArchive) as $entry) {
        if (substr($entry, 0, strlen($needle)) === $needle) {
            $dirName = $entry;
        }
    }
    logMsg ("   ..with dir $dirName");

    if (!(file_exists($target_dir . '/' . $dirName) && is_dir($target_dir . '/' . $dirName))) {
        trigger_error('The ZIP archive does not contain directory "churchtools", or creation failed!',
            E_USER_ERROR);
        if (function_exists('push')) {
            push('[Fehler] ZIP', 'Das Verzeichnis "churchtools" fehlt im ZIP Archiv!', 1);
        }
        // cleanup
        if (is_dir($target_dir . '/' . $dirName))
            delTree($target_dir . '/' . $dirName);
        throw new Exception('The ZIP archive does not contain directory "churchtools", or creation failed!');
    }

    // Check if directory system and index.php exist, if yes, rename them for backup
    makeBackup();
    logMsg ("delete old files and dirs");
    if (file_exists($target_dir . '/system')) {
        delTree($target_dir . '/system');
    }
    if (file_exists($target_dir . '/index.php')) {
        unlink($target_dir . '/index.php');
    }

    logMsg ('move new files/dirs to ROOT');
    rename($target_dir . '/' . $dirName . '/system', $target_dir . '/system');
    rename($target_dir . '/' . $dirName . '/index.php', $target_dir . '/index.php');
    delTree($target_dir . '/' . $dirName);

    if (function_exists('push')) {
        push('Update erfolgreich', 'Ein Update wurde erfolgreich installiert: <b>' . CT_VERSION . '</b>!');
    }

    logMsg ("remove updateArchive");
    if (file_exists($updateArchive)) {
        unlink($updateArchive);
    }
}

function logMsg($msg) {
    if (DEBUG)
        echo $msg . "\n";
}
