# BlisstributeTwigToolkit

## Info

Kompatibel mit Blisstribute ab Version `2020.1929`.

## Installation, Konfiguration & Verwenund

Nach dem klonen des Repositories muss die Standard Konfiguration auf die entsprechende Blisstribute-Instanz konfiguriert werden.
Dazu einfach die Konfiguration-Vorlage kopieren und anpassen.

    $ cp src/Config/config.json.dist src/Config/config.json

Die Basiskonfiguration benötigt die folgenden Daten:
- Url, Rest-API-Anwendungs-Url
- Client
- Api Key

Im Anschluss müssen die entsprechenden Templates eingebunden werden. 
Die generelle Struktur sieht in der Regel so aus:

- `src/Templates/email`
- `src/Templates/image`
- `src/Templates/slip`

Templates können entweder in `src/Templates` kopiert, oder gelinkt werden. Wenn du die Templates "symlinken" willst, musst
du den Pfad zu den Templates mit in den Container mounten. 

Zunächst müssen wir aber, so oder so, die Vorlage der `docker-compose.yml.dist` kopieren:

    $ cp docker-compose.yml.dist docker-compose.yml

Die `docker-compose.yml` Datei wird absichtlich ignoriert von `git`, nämlich, wie oben erwähnt, wenn man die Templates per
Symlink einbinden will, diese Datei sonst immer als modifiziert auftauchen würde.

Um Symlinks zu nutzen, können wir einfach in der `docker-compose.yml` einen weiteren Eintrag
unter `volumes` hinzufügen, z.B. so:

    - '/home/benutzer/workspace/template:/home/benutzer/workspace/template'

Nun steht der Symlink-Pfad `/home/benutzer/workspace/template` im Container zur Verfuegung und es können folgende Symlinks aufgelöst werden.

    - 'email -> /home/benutzer/workspace/templateRepo/email'
    - 'slip -> /home/benutzer/workspace/templateRepo/slip'
    - 'image -> /home/benutzer/workspace/templateRepo/image'

Sollte ein Projekt von dieser Struktur abweichen, können die Pfade in folgender
Datei angepasst werden: `src/Service/TwigService.php`

Im Anschluss den Container starten (der erste Start führt die Erstellung des Containers durch, dass kann einige Zeit dauern.)

    $ docker-compose up -d

Nach der Installation kann die Anwendung folgendermaßen erreicht werden:

    http://localhost:8085

Oben auf der Seite sehen wir nun eine Toolbar. Über diese können wir bequem die Informationen angeben, die die Anwendung benötigt.
Art (Im Moment nur Dokument, keine E-Mails), den Typen, das Template und den Identifier. Dies sind sind Pflichtfelder.
Zusätzlich können wir noch ein Werbemittel angeben und steuern, ob die Daten aus dem lokalen Cache geladen, oder aktualisiert werden sollen.

Nach Absenden des Formulars, wird ein iFrame erzeugt welches folgende Route aufruft:

    http://localhost:8085/{kind}/{type}/{template}/{identifiers}/{advertisingMediumCode}?forceReload={bool}

Ein Beispiel für eine (gecachte) Rechnung würde so aussehen:

    http://localhost:8085/pdf/invoice/default_invoice.html/{Rechnungsnummer}

Wenn wir also möchten, können wir auch direkt die URL aus dem iFrame aufrufen.  
Je nach ausgewähltem Typen, sind die Identifier unterschiedlich. Rechnungsnummer bei Typ 'invoice', Picklistennummer bei Typ 'picklist' etc.

Hat das angeprochene System eine höhere Buildnummer als `1930`, werden sich die Typen über eine API des VHS geholt. In allen anderen Fällen,
werden auf statisch hinterlegte Werte zurückgegriffen. 
Hat das angesprochene System eine höhere Buildnummer als `1931`, werden sich zusätzlich noch die verfügbaren Sprachen des System geholt und
in einem Dropdown auf der Seite angezeigt. Mit der zusätzlichen Sprachauswahl, können so die Templates mit verschiedenen Sprachen getestet werden.
Denn bei einer angegebenen Sprache werden die Textbausteine, sofern vorganend, übermittelt.

## Context überschreiben

Es ist möglich in der Konfigurationsdatei `src/Config/config.json` sämtliche Werte des Kontextes zu überschreiben.
Dies kann erreicht werden unterhalb des Knotens: `mapping -> defaults`, aber hier muss der Aufbau des Kontextes 
nachgebaut werden, um die gewünschten Werte zu überschreiben.  
Also z.B. den Bildpfad des Box-Barcodes: `mapping -> defaults -> invoice -> boxBarcode`.  
Ein vollständiges Beispiel befindet sich in der `src/Config/config.json.dist` Datei.

## Textbausteine

Die Textbausteine können ähnlich überschrieben werden, wie die Werte des Kontextes. Dazu einfach die gewünschten Textbausteine
samt Übersetzung (Key/Value) in folgenden Knoten der `src/Config/config.json` einfügen: `mapping -> textModuleMapping -> defaults`.

Möchte man Übersetzungen pro Werbemittel anlegen, kann ein weiterer Knoten, auf derselben Ebene, wie die `defaults` hinzugefügt werden.
Also beispielsweise: `mapping -> textModuleMapping -> ABC`. Diese Werte pro Werbemittelcode können dann, sofern vorhanden in den Defaults,
Werte überschreiben. D.h. man kann in den Defaults Standardwerte eintragen und diese dann individuell für jedes Werbemittel einzeln, oder alle (`defaults`) überschreiben.
Leere Bausteine, also ohne Übersetzung, werden mit ihrem "Schlüssel" im PDF angezeigt. Ebenso Bausteine für die es keinen Eintrag
in der Konfiguration gibt.  
Ist dieses Verhalten nicht gewünscht, kann man den Code in `src/Twig/Loader/TextModuleLoader.php` anpassen.

## Cache

Folgende Daten werden lokal auf dem Filesystem gespeichert:

- JWT
- der Kontext
- die Textbausteine
- VHS Buildnummer
- Typen
- Sprachen

Der `JWT` wird, da er für 8 Stunden gültig ist, für 8 Stunden auf der Platte gespeichert. Wird der `JWT` aus dem Cache
gelöscht, dann wird sich automatisch ein neuer geholt.

Der Kontext eines jeden Typs + Identifier wird ebenso auf der Festplatte gespeichert. Hier muss man aufpassen: Einmal im Cache,
kann der Kontext nur aktualisiert werden, wenn man den `Cache leeren` Button benutzt. Dann wird der Cache geleert und die Seite frisch
geladen. Alternativ kann man auch den Cache manuell aus dem Dateisystem löschen: `rm -rf /tmp/smyfony-cache`.

Ähnlich verhält es sich mit den Textbausteinen. Diese werden pro Werbemittelcode, oder eben die Defaults, auf der Platte gespeichert.
Auch hier können wir diese nur aktualisieren, wenn wir den Reload forcen und den `Cache leeren` Button nutzen.
Der Cache gilt natürlich nicht für die Textbausteine aus der `src/Config/config.json`, diese werden immer "über" den Cache geschrieben.

Der Button `Cache leeren` gilt immer für alle Sachen die auf dem Dateisystem gecacht werden, also die Aktualisierung des einen
oder anderen ist somit nicht möglich. Es wird dann immer alles aktualisiert. (Es sei denn man löscht manuell z.B. nur den `jwt cache` aus dem Dateisystem).

Standardmäßig wird der Cache in `/tmp/symfony-cache` geschrieben.