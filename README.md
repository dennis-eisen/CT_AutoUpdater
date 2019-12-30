# ChurchTools AutoUpdater
Das hier geht an alle, die ChurchTools selbst hosten...

## Allgemein

Michael Lux (@milux) und Ich hatten keine Lust mehr ChurchTools ständig von Hand per FTP zu aktualisieren, deshalb haben wir einen "kleinen" Auto Updater für ChurchTools entwickelt.
Die Benutzung unseres Updaters ist denkbar einfach und sollte auch ohne Programmier-Kenntnisse nicht überfordern.

## Funktionen

Folgender **Funktionsumfang** ist in unserem Skript momentan enthalten:

+ Schutz des Skripts mit per Query-String übergebenem Passwort.
+ Verifikation, ob ein Update verfügbar ist.
+ Herunterladen der ZIP-Datei vom ChurchTools SeaFile Server
+ Entpacken der ZIP-Datei und Ersetzung der index.php und des system-Verzeichnisses (Einspielen des Updates)
+ Lock, dieser verhindert, dass das Update mehrmals zur gleichen Zeit gestartet wird.
+ Push Benachrichtigung (über Pushover und / oder Pushbullet)

Bei Fragen stehen wir gerne zur Verfügung! :)

## Installation
Hiermit wird ein `update` Verzeichnis in eurem Curchtools verzeichnis angelegt welches den Updater und die von euch zu erstellende Konfigurationsdatei enthält und ganz einfach geupdated werden kann.
```bash
cd CHURCHTOOLS_DIR
git clone https://github.com/karl007/CT_AutoUpdater.git ./update
```

## Update
```bash
cd CHURCHTOOLS_DIR/update
git stash -u # ggf lokal gemachte änderungen werden als stash vorm überschreiben gespeichert
git pull -v # holt die letzte Version vom Server
git reset --hard # ersetzt alle lokalen files mit denen vom Server - die config.php ist hier nicht betroffen
```

## Configuration
Vor dem ersten Update müsst Ihr einen Hash für euer gewähltes Passwort erstellen.
Dafür zuerst durch den Aufruf von <https://churchtools_domain.xyz/update/?EUER_PASSWORT> einen Passwort-Hash erzeugen!

Den Inhalt der bei diesem Aufruf angezeigt wird als `update/config.php` speichern und noch bei `define('SEAFILE_DIR', 'd/xyz1234567/');` und `define('SEAFILE_JSON_PATH', 'api/v2.1/share-links/xyz1234567/dirents');` den  Teil `xyz1234567` anpasst, bei der ihr die ChruchTools-Updates herunter ladet, ist das Skript einsatzbereit!
(Ihr bekommt bei Updates per E-Mail einen Link zu einer SeaFile-Seite, die i.d.R. so aussieht: https://seafile.churchtools.de/d/xyz1234567/, verwendet davon einfach den hinteren Teil!)

### Push
Defaultmäßig ist Push deaktiviert, kann aber über die defines am beginn der `push.inc.php` eingerichtet und über das `define('ENABLE_PUSH', false);` aktiviert werden.

**config.php:** (aktivierte Einträge sind Pflicht und müssen passen, die auskommentierten optional)
```php
<?php
// Put in your own password hash here
define('HASH', 'PUT IN YOUR OWN HASH HERE');
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

```

Nun könnt ihr das script Cronejob einmal am Tag (z.B. bei uns 4:00 Uhr) oder bei bedarf manuell so aufrufen:
<https://churchtools_domain.xyz/update/?EUER_PASSWORT>

Die meisten Hosting-Provider bieten Cronjobs für ihre Kunden an.
Wenn ihr keine Cronjobs anlegen könnt, könnt ihr auch einen kostenlosen externen Dienst wie https://www.cron-job.org/ dafür verwenden.
