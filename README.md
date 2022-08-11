# VHS1TwigToolkit

## Info

Kompatibel mit VHS1 ab Version `2020.1929`.

## Installation, Konfiguration & Verwendung

Nach dem klonen des Repositories muss die Standard Konfiguration auf die entsprechende VHS1-Instanz konfiguriert werden.
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

Dazu wie folgt vorgehen:

    $ cd src/Templates
    $ ln -s /absolute/path/to/templates-repo/email/
    $ ln -s /absolute/path/to/templates-repo/slip/
    $ ln -s /absolute/path/to/templates-repo/image/

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

## Bedienen

Um eine Vorschau der angepassten Templates zu sehen, müssen wir dem Tool mitteilen, was wir bearbeiten möchte und woher die Daten genommen werden sollen.  
Zunächst wählen wir also eine `Art` aus. Hier werden `PDF` und `E-Mail` unterstützt. Je nach ausgewählter Art, werden die verfügbaren `Typen` geladen.  
Im nächsten Schritt müssen wir den Namen des Templates angeben, welches für die Darstellung genommen werden soll. Dann müssen wir eine `Identifikation` angeben.  
Diese ist abhängig von dem ausgewählten `Typen`, also für eine `Rechnung` brauchen wir beispielsweise eine `Rechnungsnummer`. Weiter unten ist eine Liste zur Hilfe.  

Diese vier Felder `Art`, `Typ`, `Template` und `Identifikation` sind Pflichtfelder.

Die Produkt ID muss bei einigen `Templates` (E-Mail) zusätzlich angegeben werden. Siehe dazu wieder weiter unten die Liste.

Das Werbemittel ist auch optional und kann angegebeben werden. Z.B.: wenn wir "dynamische" Anzeigen im Template auf Basis des Werbemittels haben,
oder bestimmte Werte der Textbausteine an ein Werbemittel gebunden sind.

Mit der Sprache verhält es sich ähnlich wie mit den Werbemitteln. Die verfügbaren Sprachen werden automatisch geladen und beeinflusst die angezeigte Spreche - logisch.

|Art| Typ                                     | Template (kann abweichen)               | Identifikation                           |Produkt ID (ja/nein)|
|---|-----------------------------------------|-----------------------------------------|------------------------------------------|---|
|PDF| Rechnung                                | default_invoice.html                    | Rechnungsnummer                          ||
|PDF| Rechnungs (Email-Anhang)                | default_invoice_email.html              | Rechnungsnummer                          ||
|PDF| Retourenschein                          | default_return_slip.html                | Rechnungsnummer                          ||
|PDF| Lieferschein                            | default_delivery_slip.html              | Rechnungsnummer                          ||
|PDF| Pickliste                               | default_pick_list.html                  | Picklistennummer                         ||
|PDF| Nachfüllauftrag                         | default_stock_relocation.html           | Nachfüllauftragsnummer                   ||
|PDF| Umlagerungsauftrag                      | default_relocation.html                 | Umlagerungsnummer                        ||
|PDF| Mahnung                                 | default_motion_slip.html                | ID der Mahnung                           ||
|PDF| Lieferantenbegleitdokument              | default_supply_note.html                | Lieferantenbestellnummer                 ||
|PDF| Lieferantenbestellung                   | default_supplier_order_slip.html        | Lieferantenbestellnummer                 ||
|PDF| Anschreiben Herstellerreparatur         | default_repair_case_cover_letter.html   | Reparaturfallnummer                      ||
|PDF| Reparatur-Lieferschein                  | default_repair_case_return_form.html    | Reparaturfallnummer                      ||
|PDF| Auftragsbestätigung                     | default_order_confirmation.html         | Bestellnummer                            ||
|PDF| Angebot                                 | default_offer.html                      | Bestellnummer                            ||
|PDF| Produkt-Etikett                         | default_label_small/big.html            | Identifikation (z.B. EAN, Artikelnummer) ||
|PDF| Lagerfach-Etikett                       | default_tray_label.html                 | Lagerfachcode                            ||
|PDF| Benutzerausweis                         | default_user_id_card.html               | ID des Benutzers                         ||
|PDF| PickBox-Etikett                         | default_pick_box.html                   | Pickboxnummer/Identifikation             ||
|PDF| POS - Abschluss                         | default_pos_report.html                 | ID des Reports                           ||
|PDF| Inventurbeleg                           | default_stock_inventory.html            | Inventurnummer                           ||
|E-Mail| Lieferantenbestellung                   | default_supplier_order.html             | Lieferantenbestellnummer                 ||
|E-Mail| Lieferantenbestellung Bestelländerung   | default_supplier_order_changed.html     | Lieferantenbestellnummer                 ||
|E-Mail| Lieferantenbestellung Mahnung           | default_supplier_order_reminder.html    | Lieferantenbestellnummer                 ||
|E-Mail| Lieferantenbestellung Stornierung       | default_supplier_order_cancel.html      | Lieferantenbestellnummer                 ||
|E-Mail| Lieferantenbestellung Abschluss         | default_supplier_order_finished.html    | Lieferantenbestellnummer                 ||
|E-Mail| Lieferverzugsemail                      | default_order_delivery_delay.html       | ID der Bestellzeile                      ||
|E-Mail| Bestelleingang                          | default_order_received.html             | Bestellnummer                            |x||
|E-Mail| Mahnung                                 | default_monition.html                   | ID der Mahnung                           ||
|E-Mail| Rechnungsversand                        | default_email_invoice.html              | Bestellnummer                            ||
|E-Mail| Rechnungsversand (Buchhaltung)          | default_accountancy_invoice_email.html  | Bestellnummer                            |x|
|E-Mail| Bestelländerung                         | default_order_changed.html              | Bestellnummer                            |x|
|E-Mail| Bestellstornierung                      | default_order_canceled.html             | Bestellnummer                            |x|
|E-Mail| Bestellung Logistikanmeldung            | default_order_moved_to_logistic.html    | Bestellnummer                            |x|
|E-Mail| Versandbestätigung                      | default_order_shipped.html              | Versandauftragsnummer                    ||
|E-Mail| Lastschriftavis                         | default_order_payment_advise.html       | EREF (End2End Reference)                 |x|
|E-Mail| Zahlungseingang                         | default_payment_cleared.html            | Bestellnummer                            |x|
|E-Mail| Retoureneingang                         | default_incoming_return.html            | Retourennummer                           ||
|E-Mail| Zahlungseingang (Bankkonto)             | default_payment_cash_cleared.html       | Bestellnummer                            |x|
|E-Mail| Vorkasse-Erinnerung                     | default_pre_payment_reminder.html       | Bestellnummer                            |x|
|E-Mail| Retourenverarbeitung                    | default_return_processed.html           | Retourennummer                           ||
|E-Mail| Retoureninformationen                   | default_order_return_info.html          | Bestellnummer                            ||
|E-Mail| Produkt NLB                             | default_order_product_nlb.html          | Bestellnummer                            |x|
|E-Mail| Selbstabholerbenachrichtigung           | default_order_ready_for_collection.html | Bestellnummer                            |x|
|E-Mail| Kommissionierungshinweis                | default_order_in_picking.html           | Bestellnummer                            |x|
|E-Mail| Streckengeschäft                        | default_drop_shipment_order.html        | Bestellnummer                            |x|
|E-Mail| Streckengeschäft Zuweisung Lieferant    |                                         | Lieferantenbestellnummer                 ||
|E-Mail| Streckengeschäft Kundenbenachrichtigung |                                         | Versandauftragsnummer                    ||

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
Weiter kann man auch die `defaults` für eine bestimmte Sprache überschreiben, dazu beispielsweise einen Knoten `mapping -> textModuleMapping -> defaults-en` hinzufügen. Also
an das Werbemittel, oder eben den Defaults, ein `-LANGUAGE_CODE` anhängen.
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

## Fonts

Wenn wir Custom-Fonts brauchen, können wir diese ganz einfach einbinden. Zunächst brauchen wir die Fonts selber. Z.B.: ablegen unter `src/Templates/fonts`.  
Dann öffnen wir folgende Datei: `src/Service/PdfService.php`. Hier müssen wir kleine Anpassungen an der verwendeteten `mPDF` Instanz vornehmen:

    private function _getInstance(): Mpdf
    {
        $defaultConfig = (new \Mpdf\Config\ConfigVariables())->getDefaults();
        $fontDirs = $defaultConfig['fontDir'];

        $defaultFontConfig = (new \Mpdf\Config\FontVariables())->getDefaults();
        $fontData = $defaultFontConfig['fontdata'];

        $mPdf = new Mpdf([
            ...
            'fontDir' => array_merge($fontDirs, [
                __DIR__ . '/../Templates/fonts', # auf Basis von src/Templates/fonts, kann aber natürlich irgendein Pfad sein
            ]),
            'fontdata' => $fontData + [
                'font-name' => [ # css: font-family: font-name;
                    'R' => 'Font-Name.ttf',
                    'B' => 'Font-Name-Bold.ttf',
                    'I' => 'Font-Name-Italic.ttf',
                    'BI' => 'Font-Name-BoldItalic.ttf',
                ],
            ],
        ]);

        ...

    }

Für weiter Information über die Konfiguration von Fonts bei `mPDF`, bitte [hier klicken](https://mpdf.github.io/fonts-languages/fonts-in-mpdf-7-x.html).