Dies geht an alle, die ChurchTools selbst hosten...

ALLGEMEIN

Michael Lux (@milux) und Ich hatten keine Lust mehr ChurchTools ständig von Hand zu aktualisieren, deshalb haben wir einen "kleinen" Auto Updater für ChurchTools entwickelt. Die Benutzung unseres Updaters ist denkbar einfach.

Folgendes PHP Skript (update.php) von GitHub im ROOT Verzeichnis eurer ChurchTools Installation ablegen und per Cronejob einmal am Tag (bei uns 4:00 Uhr) aufrufen: CT_AutoUpdater (GitHub)

Zur Absicherung des Skripts, wird ein Passwort als QUERY_STRING übergeben. Wenn Ihr die Datei also aufruft müsst Ihr das wie folgt tun: www.euredomain.de/upload.php?EUERPASSWORT

INSTALLATION

Vor dem erstmaligen Aufrufen der upload.php müsst Ihr einen Hash für euer gewähltes Passwort erstellen. Dafür könnt Ihr die createHash.php nutzen. Danach müsst Ihr den dort erstellten Hash in der upload.php unter define('HASH', 'PUT IN YOUR OWN HASH HERE'); eintragen. Ab dann ist das Skript einsatzbereit.

FUNKTIONEN

Folgender Funktionsumfang ist in unserem Skript momentan enthalten:

+ Schutz des Skripts mit QUERY_STRING Passwort.
+ Verifikation ob ein Update verfügbar ist.
+ Laden der Zip Datei vom Seafile Server
+ Löschen des Systemsordners
+ Entpacken der Zip Datei (Einspielen des Updates)
+ Bei Fragen, stehe ich euch gerne zur Verfügung!
