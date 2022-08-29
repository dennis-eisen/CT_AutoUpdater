# ChurchTools AutoUpdater
Das hier geht an alle, die ChurchTools selbst hosten...

## Allgemein
Michael Lux (@milux) und ich hatten keine Lust mehr, ChurchTools ständig von Hand per FTP zu aktualisieren.
Deshalb haben wir einen "kleinen" Auto Updater für ChurchTools entwickelt.
Die Benutzung unseres Updaters ist denkbar einfach und sollte auch ohne Programmier-Kenntnisse nicht überfordern.

### WICHTIG: Update ab ChurchTools 3.89.0
Das `.tar.gz`-Archiv mit den Updates enthält nun **Dateinamen mit >= 99 Zeichen Länge**!
Dies wird vom alten TAR-Standard "ustar" nicht unterstützt, weshalb das Archiv im Format "pax" (POSIX 2001)
geliefert wird. Unglücklicherweise gibt es derzeit **keine** PHP-Library, die Dateien aus pax-TARs mit vollständigen
Namen korrekt entpacken kann.
Die aktuelle Updater-Version hat jetzt eine Heuristik zur Erkennung falsch entpackter Update-TARs
(99 <= Dateiname <= 100 Zeichen, keine File-Extension) und bricht in diesem Fall das Update ab.

Aus diesem Grund gibt es in der aktuellen Version jetzt die Möglichkeit, das native `tar`-Programm per Kommandozeile
zu verwenden, sofern es vorhanden ist.
Auf allen typischen Linux-Installationen sollte dieses Programm vorhanden sein. Um das "native Entpacken" zu aktivieren,
muss in der `config.php` folgendes deklariert werden: `const NATIVE_EXTRACT = true;` (s. unten).
Diese Zeile kann auch nachträglich problemlos hinzugefügt werden.

## Funktionen
Folgender **Funktionsumfang** ist in unserem Skript momentan enthalten:

+ Schutz des Skripts mit per Query-String übergebenem Passwort.
+ Verifikation, ob ein Update verfügbar ist.
+ Herunterladen der ZIP-Datei vom ChurchTools SeaFile Server
+ Entpacken der ZIP-Datei und Ersetzung der index.php und des system-Verzeichnisses (Einspielen des Updates)
+ Lock, der verhindert, dass das Update mehrmals zur gleichen Zeit gestartet wird.
+ Push-Benachrichtigung über Erfolg/Misserfolg des Updates (via Pushover)

Bei Fragen stehen wir gerne zur Verfügung! :)

## Installation
Hiermit wird ein `update`-Verzeichnis in eurem ChurchTools-Verzeichnis angelegt welches den Updater
und die von euch zu erstellende Konfigurationsdatei enthält und ganz einfach geupdated werden kann.
```bash
cd <CHURCHTOOLS_DIR>
git clone https://github.com/dennis-eisen/CT_AutoUpdater.git ./update
```

## Update
```bash
cd <CHURCHTOOLS_DIR>/update
git stash -u # ggf. lokal durchgeführte Änderungen werden getrennt gespeichert
git pull -v # holt die letzte Version vom Server
git reset --hard # Ersetzt alle lokalen files mit denen vom Server - die config.php ist hier nicht betroffen
git stash pop # ggf. durchgeführte lokale Änderungen wieder zurück holen
```

## Configuration
Vor dem ersten Update müsst Ihr eure Konfiguration initialisieren und ein Passwort wählen.
Dafür zuerst durch den Aufruf von <https://churchtools_domain.xyz/update/?EUER_PASSWORT> einen Passwort-Hash erzeugen!

Den Inhalt der bei diesem Aufruf angezeigt wird als `update/config.php` speichern und noch bei
`const SEAFILE_CODE = 'xyz1234567';` das `xyz1234567` ersetzen, dann ist das Skript einsatzbereit!
(Ihr erhaltet für Updates per E-Mail einen Link zu einer SeaFile-Seite, die i.d.R. so aussieht:
https://seafile.churchtools.de/d/**xyz1234567**/, verwendet davon den hinteren Teil, hier fett gedruckt.)

### Push
Defaultmäßig ist Push deaktiviert, kann aber über die `const`-Deklarationen am Beginn der `push.inc.php` eingerichtet
und über das Anpassen von `const ENABLE_PUSH = false;` (`false` zu `true`) aktiviert werden.

**config.php:**
```php
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
```

Nun könnt ihr das script per Cronjob einmal am Tag (z.B. bei uns 4:00 Uhr) oder bei Bedarf manuell so aufrufen:
<https://churchtools_domain.xyz/update/?EUER_PASSWORT>

Die meisten Hosting-Provider bieten Cronjobs für ihre Kunden an.
Wenn ihr keine Cronjobs anlegen könnt, könnt ihr auch einen kostenlosen externen Dienst wie https://www.cron-job.org/
dafür verwenden.
