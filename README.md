# BlisstributeTwigToolkit

## Installation & Konfiguration

Nach dem klonen des Repositories muss die Standard Konfiguration auf die entsprechende Blisstribute-Instanz konfiguriert werden.
Dazu einfach die Konfiguration-Vorlage kopieren und anpassgen.

    $ cp src/Config/config.json.dist src/Config/config.json

Die Basiskonfiguration benötigt die folgenden Daten:
- Url, Frontend-Anwendungsurl
- Client
- User
- Password
- Api Key (noch nicht unterstützt)

Im Anschluss müssen die entsprechenden Templates eingebunden werden. 
Die generelle Struktur sieht in der Regel so aus:

- `src/Templates/email`
- `src/Templates/image`
- `src/Templates/slip`

Templates können entweder in `src/Templates` kopiert, oder gelinkt werden. Wenn du die Templates "symlinken" willst, musst
du den Pfad zu den Templates mit in den Container mounten. 

Dazu einfach in der `docker-compose.yml` einen weiteren Eintrag
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
Werte überschreiben. D.h. man kann in den Defaults Standardwerte eintragen und diese dann individuell für jedes Werbemittel einzeln, oder alle überschreiben.
Einige der Bausteine sind bereits Teil der `src/Config/config.json.dist`.  
Leere Bausteine, also ohne Übersetzung, werden mit ihrem "Schlüssel" im PDF angezeigt. Ebenso Bausteine für die es keinen Eintrag
in der Konfiguration gibt.  
Ist dieses Verhalten nicht gewünscht, kann man den Code in `src/Twig/Loader/TextModuleLoader.php` anpassen.