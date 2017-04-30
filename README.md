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

Vor dem erstmaligen Aufrufen der `update.php` müsst Ihr einen Hash für euer gewähltes Passwort erstellen.
Dafür zuerst durch den Aufruf von <https://churchtools_domain.xyz/createHash.php?EUER_PASSWORT> einen Passwort-Hash erzeugen!
Danach müsst ihr den dort erstellten Hash in der `update.php` bei `define('HASH', 'PUT IN YOUR OWN HASH HERE');` eintragen.
Wenn ihr jetzt noch bei `define('SEAFILE_DIR', '/d/xyz1234567/');` den Pfad auf die Stelle anpasst, bei der ihr die ChruchTools-Updates herunter ladet, ist das Skript einsatzbereit!
(Ihr bekommt bei Updates per E-Mail einen Link zu einer SeaFile-Seite, die i.d.R. so aussieht: https://seafile.churchtools.de/d/xyz1234567/, kopiert davon einfach den hinteren Teil!) 

Nun das Update-Skript im Root-Verzeichnis der ChurchTools Installation (neben `index.php, system, files, etc.`) ablegen und per Cronejob einmal am Tag (z.B. bei uns 4:00 Uhr) so aufrufen:
<https://churchtools_domain.xyz/update.php?EUER_PASSWORT>

Die meisten Hosting-Provider bieten Cronjobs für ihre Kunden an.
Wenn ihr keine Cronjobs anlegen könnt, könnt ihr auch einen kostenlosen externen Dienst wie https://www.cron-job.org/ dafür verwenden.
