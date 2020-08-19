# BlisstributeTwigToolkit

## Installation & Konfiguration

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

    http://localhost:8085/{type}/{template}/{identifiers}/{advertisingMediumCode}

Die folgenden Query-Parameter sind erforderlich:

- `type`
    - `invoice`, Rechnung
    - `delivery`, Lieferschein
    - `return`, Retourenschein
    - `offer`, Angebot
    - `orderConfirmation`, Auftragsbestätigung
    - `pickBox`, Pick-Box Label
    - `picklist`, Pick-Liste
    - `posReport`, Kassenabschluss
    - `productLabel`, Produkt Label
    - `stockInventory`, Inventurbericht
    - `stockRelocation`, Lagernachfüllauftrag
    - `supplierOrder`, Lieferantenbestellung
    - `supplyNote`, Lieferantenbegleitdokument
    - `trayLabel`, Lager-Fach Label
    - `userCard`, Benutzer Login-Card 
- `template`, der Name des Templates, z.B. `foo.html.twig`
- `identifiers`, Identifikation abhängig vom verwendeten Typen (Rechnungsnummer bei Typ 'invoice', Picklistennummer bei Typ 'picklist' etc.)

Die folgenden Query-Parameter sind optional:

- `advertisingMediumCode`
    
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

Der `JWT` wird, da er für 8 Stunden gültig ist, für 8 Stunden auf der Platte gespeichert. Wird der `JWT` aus dem Cache
gelöscht, dann wird sich automatisch ein neuer geholt.

Der Kontext eines jeden Typs + Identifier wird ebenso auf der Festplatte gespeichert. Hier muss man aufpassen: Einmal im Cache,
kann der Kontext nur aktualisiert werden, wenn man an die URL folgenden Parameter hängt: `domain.tld/path?forceReload=true`.

Ähnlich verhält es sich mit den Textbausteinen. Diese werden pro Werbemittelcode, oder eben die Defaults, auf der Platte gespeichert.
Auch hier können wir diese nur aktualisieren, wenn wir den Reload forcen: `domain.tld/path?forceReload=true`.
Der Cache gilt natürlich nicht für die Textbausteine aus der `src/Config/config.json`, diese werden immer "über" den Cache geschrieben.

Der Parameter gilt `forceReload=true` gilt immer sowohl für den Kontext, als auch die Textbausteine, also die Aktualisierung des einen
oder anderen ist also nicht möglich. Es wird dann immer alles aktualisiert.

Standardmäßig wird der Cache in `/tmp/symfony-cache` geschrieben.