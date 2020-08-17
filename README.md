# BlisstributeTwigToolkit

## Installation

ACHTUNG: Wenn du die Templates nicht in den Templateordner kopieren, sondern nur "symlinken" möchtest, dann musst du
den Pfad zu den Templates in den Container mounten. Ist dies nicht der Fall, einfach ab "Repo klonen" weiterlesen.
Dazu einfach in der `docker-compose.yml` einen weiteren Eintrag
unter `volumes` hinzufügen, z.B. so:

    - '/path/to/other/project/templates:/path/to/other/project/templates'

Sollte der Container bereits gebaut sein, einfach noch mal bauen:

    $ docker-compose up -d --build

Repo klonen und dann im Root-Verzeichnis folgenden Befehl ausführen:

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

## Konfiguration

    $ cp src/config/config.json.dist src/config/config.json

Die Datei öffnen und die Basiskonfiguration vornehmen:

- Url, Frontend-Anwendungsurl
- Client
- User
- Password
- Api Key (noch nicht unterstützt)

Templates können entweder in `src/templates` kopiert, oder gelinkt werden. Wenn du die Templates "symlinken" willst, musst
du den Pfad zu den Templates mit in den Container mounten. Siehe dazu weiter oben "Konfiguration".

Die generelle Struktur sieht in der Regel so aus:

- `src/templates/email`
- `src/templates/image`
- `src/templates/slip`

Sollte ein Projekt von dieser Struktur abweichen, können die Pfade in folgender
Datei angepasst werden: `src/service/TwigService.php`

## Context überschreiben

Es ist möglich in der Konfigurationsdatei `src/config/config.json` sämtliche Werte des Kontextes zu überschreiben.
Dies kann erreicht werden unterhalb des Knotens: `mapping -> defaults`, aber hier muss der Aufbau des Kontextes 
nachgebaut werden, um die gewünschten Werte zu überschreiben.  
Also z.B. den Bildpfad des Box-Barcodes: `mapping -> defaults -> invoice -> boxBarcode`.  
Ein vollständiges Beispiel befindet sich in der `src/config/config.json.dist` Datei.

## Textbausteine

Die Textbausteine können ähnlich überschrieben werden, wie die Werte des Kontextes. Dazu einfach die gewünschten Textbausteine
samt Übersetzung (Key/Value) in folgenden Knoten der `src/config/config.json` einfügen: `mapping -> textModuleMapping -> defaults`.
Möchte man Übersetzungen pro Werbemittel anlegen, kann ein weiterer Knoten, auf derselben Ebene, wie die `defaults` hinzugefügt werden.
Also beispielsweise: `mapping -> textModuleMapping -> ABC`. Diese Werte pro Werbemittelcode können dann, sofern vorhanden in den Defaults,
Werte überschreiben. D.h. man kann in den Defaults Standardwerte eintragen und diese dann individuell für jedes Werbemittel einzeln, oder alle überschreiben.
Einige der Bausteine sind bereits Teil der `src/config/config.json.dist`.  
Leere Bausteine, also ohne Übersetzung, werden mit ihrem "Schlüssel" im PDF angezeigt. Ebenso Bausteine für die es keinen Eintrag
in der Konfiguration gibt.  
Ist dieses Verhalten nicht gewünscht, kann man den Code in `src/twig/loader/TextModuleLoader.php` anpassen.