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

Alternativ kann mit Unterverzeichnissen gearbeitet werden. Die o.g. Anleitung kann wie beschrieben abgearbeitet werden, mit dem Unterschied, dass im Ordner `src/Templates` ein Hauptordner, bspw. `project-1` gemeountet wird.
Der Ordner `project-1` muss als Unterordner (mindestens) die Struktur `email/` und `slip/` aufweisen. 

Um Templates aus einem Unterordner zu laden, muss in der Oberfläche der Unterordner mit einer Pipe vorangestellt werden. 
So kann bspw. folgendes abgebildet werden:

    - `src/Templates/project-1/slip/default_invoice.html`
    - `src/Templates/project-2/slip/default_invoice.html`

Um den Template-Satz aus dem `project-2`-Ordner zu nutzen, wird in der Oberfläche als Dateiname folgendes angegeben:
`project-2|default_invoice.html`

Werden nur Haupt-Ordner unter `src/Templates` eingebunden, ist die Angabe dieses Ordner im Dateinamen zwingend notwendig.

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

|Art| Typ                                     | Template (kann abweichen)                | Identifikation                           |Produkt ID (ja/nein)|
|---|-----------------------------------------|------------------------------------------|------------------------------------------|---|
|PDF| Rechnung                                | default_invoice.html                     | Rechnungsnummer                          ||
|PDF| Rechnungs (Email-Anhang)                | default_invoice_email.html               | Rechnungsnummer                          ||
|PDF| Retourenschein                          | default_return_slip.html                 | Rechnungsnummer                          ||
|PDF| Lieferschein                            | default_delivery_slip.html               | Rechnungsnummer                          ||
|PDF| Mehrpaket-Lieferschein                  | default_multi_package_delivery_slip.html | Versandauftragsnummer                          ||
|PDF| Pickliste                               | default_pick_list.html                   | Picklistennummer                         ||
|PDF| Nachfüllauftrag                         | default_stock_relocation.html            | Nachfüllauftragsnummer                   ||
|PDF| Umlagerungsauftrag                      | default_relocation.html                  | Umlagerungsnummer                        ||
|PDF| Mahnung                                 | default_motion_slip.html                 | ID der Mahnung                           ||
|PDF| Lieferantenbegleitdokument              | default_supply_note.html                 | Lieferantenbestellnummer                 ||
|PDF| Lieferantenbestellung                   | default_supplier_order_slip.html         | Lieferantenbestellnummer                 ||
|PDF| Anschreiben Herstellerreparatur         | default_repair_case_cover_letter.html    | Reparaturfallnummer                      ||
|PDF| Reparatur-Lieferschein                  | default_repair_case_return_form.html     | Reparaturfallnummer                      ||
|PDF| Auftragsbestätigung                     | default_order_confirmation.html          | Bestellnummer                            ||
|PDF| Angebot                                 | default_offer.html                       | Bestellnummer                            ||
|PDF| Produkt-Etikett                         | default_label_small/big.html             | Identifikation (z.B. EAN, Artikelnummer) ||
|PDF| Lagerfach-Etikett                       | default_tray_label.html                  | Lagerfachcode                            ||
|PDF| Benutzerausweis                         | default_user_id_card.html                | ID des Benutzers                         ||
|PDF| PickBox-Etikett                         | default_pick_box.html                    | Pickboxnummer/Identifikation             ||
|PDF| POS - Abschluss                         | default_pos_report.html                  | ID des Reports                           ||
|PDF| Inventurbeleg                           | default_stock_inventory.html             | Inventurnummer                           ||
|E-Mail| Lieferantenbestellung                   | default_supplier_order.html              | Lieferantenbestellnummer                 ||
|E-Mail| Lieferantenbestellung Bestelländerung   | default_supplier_order_changed.html      | Lieferantenbestellnummer                 ||
|E-Mail| Lieferantenbestellung Mahnung           | default_supplier_order_reminder.html     | Lieferantenbestellnummer                 ||
|E-Mail| Lieferantenbestellung Stornierung       | default_supplier_order_cancel.html       | Lieferantenbestellnummer                 ||
|E-Mail| Lieferantenbestellung Abschluss         | default_supplier_order_finished.html     | Lieferantenbestellnummer                 ||
|E-Mail| Lieferverzugsemail                      | default_order_delivery_delay.html        | ID der Bestellzeile                      ||
|E-Mail| Bestelleingang                          | default_order_received.html              | Bestellnummer                            |x||
|E-Mail| Mahnung                                 | default_monition.html                    | ID der Mahnung                           ||
|E-Mail| Rechnungsversand                        | default_email_invoice.html               | Bestellnummer                            ||
|E-Mail| Rechnungsversand (Buchhaltung)          | default_accountancy_invoice_email.html   | Bestellnummer                            |x|
|E-Mail| Bestelländerung                         | default_order_changed.html               | Bestellnummer                            |x|
|E-Mail| Bestellstornierung                      | default_order_canceled.html              | Bestellnummer                            |x|
|E-Mail| Bestellung Logistikanmeldung            | default_order_moved_to_logistic.html     | Bestellnummer                            |x|
|E-Mail| Versandbestätigung                      | default_order_shipped.html               | Versandauftragsnummer                    ||
|E-Mail| Lastschriftavis                         | default_order_payment_advise.html        | EREF (End2End Reference)                 |x|
|E-Mail| Zahlungseingang                         | default_payment_cleared.html             | Bestellnummer                            |x|
|E-Mail| Retoureneingang                         | default_incoming_return.html             | Retourennummer                           ||
|E-Mail| Zahlungseingang (Bankkonto)             | default_payment_cash_cleared.html        | Bestellnummer                            |x|
|E-Mail| Vorkasse-Erinnerung                     | default_pre_payment_reminder.html        | Bestellnummer                            |x|
|E-Mail| Retourenverarbeitung                    | default_return_processed.html            | Retourennummer                           ||
|E-Mail| Retoureninformationen                   | default_order_return_info.html           | Bestellnummer                            ||
|E-Mail| Produkt NLB                             | default_order_product_nlb.html           | Bestellnummer                            |x|
|E-Mail| Selbstabholerbenachrichtigung           | default_order_ready_for_collection.html  | Bestellnummer                            |x|
|E-Mail| Kommissionierungshinweis                | default_order_in_picking.html            | Bestellnummer                            |x|
|E-Mail| Streckengeschäft                        | default_drop_shipment_order.html         | Bestellnummer                            |x|
|E-Mail| Streckengeschäft Zuweisung Lieferant    |                                          | Lieferantenbestellnummer                 ||
|E-Mail| Streckengeschäft Kundenbenachrichtigung |                                          | Versandauftragsnummer                    ||

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

## MCP-Endpunkt für KI-Agenten

### Zweck

Das Toolkit stellt unter `/mcp` einen MCP-Endpunkt (Model Context Protocol) bereit. Damit können KI-Agenten wie Claude Code
Twig-Templates für PDFs und E-Mails selbst bearbeiten und prüfen, ohne die Configs unter `src/Config/*.json` zu sehen.
Zugangsdaten (`url`, `client`, `apiKey`) und das JWT bleiben serverseitig, der Agent kennt eine Config nur über ihren Slug.

### Betrieb

Der Endpunkt läuft in der bestehenden App im selben Container, es gibt keinen eigenen Prozess:

    docker-compose up -d

Transport ist Streamable HTTP unter `http://toolkit.blissdev.de/mcp` (bzw. dem Host und Port aus der lokalen
`docker-compose.yml`, z.B. `http://localhost:8087/mcp`). Die Session läuft über den Header `Mcp-Session-Id`.

Prüfen, ob der Endpunkt antwortet (HTTP 200 mit `serverInfo`):

    curl -s -X POST http://toolkit.blissdev.de/mcp \
      -H 'Content-Type: application/json' \
      -H 'Accept: application/json, text/event-stream' \
      -d '{"jsonrpc":"2.0","id":1,"method":"initialize","params":{"protocolVersion":"2025-06-18","capabilities":{},"clientInfo":{"name":"curl","version":"1"}}}'

### Anbindung an Claude Code

Im Repo liegt eine `.mcp.json`, die den Server `vhs1-toolkit` einträgt:

    {"mcpServers": {"vhs1-toolkit": {"type": "http", "url": "${VHS1_TOOLKIT_MCP_URL:-http://toolkit.blissdev.de/mcp}"}}}

Läuft das Toolkit unter einer anderen Adresse, setzt man vor dem Start die Umgebungsvariable, z.B.
`export VHS1_TOOLKIT_MCP_URL=http://localhost:8087/mcp`.

Die committete `.claude/settings.json` aktiviert den Server und sperrt `Read`, `Edit` und `Write` auf
`src/Config/config*.json` und `src/Config/.mcp-backups/**`. Bash bleibt dort bewusst erlaubt, weil die Datei für alle
Agenten in diesem Repo gilt.

Für Agenten, die nur Templates bearbeiten sollen, gibt es `.claude/template-agent.settings.json`: dieselben Deny-Regeln,
zusätzlich ist `Bash` gesperrt, erlaubt sind die Tools von `vhs1-toolkit` sowie `Read`, `Edit` und `Write` unter
`src/Templates/**`. Start im Repo-Root:

    claude --settings .claude/template-agent.settings.json

In Claude Code zeigt `/mcp` danach den Server `vhs1-toolkit` als verbunden.

Liegen die Templates in einem eigenen Repo außerhalb des Toolkits, legt man dort eine `.mcp.json` wie oben an und sperrt
die Configs des Toolkits mit absoluten Pfaden (`//` am Anfang) in `.claude/settings.json` des Template-Repos:

    {
      "enabledMcpjsonServers": ["vhs1-toolkit"],
      "permissions": {
        "deny": [
          "Bash",
          "Read(//<pfad-zum-toolkit>/src/Config/config*.json)",
          "Edit(//<pfad-zum-toolkit>/src/Config/config*.json)",
          "Write(//<pfad-zum-toolkit>/src/Config/config*.json)",
          "Read(//<pfad-zum-toolkit>/src/Config/.mcp-backups/**)",
          "Edit(//<pfad-zum-toolkit>/src/Config/.mcp-backups/**)",
          "Write(//<pfad-zum-toolkit>/src/Config/.mcp-backups/**)"
        ]
      }
    }

Hinweis zu den Pfadmustern: `/pfad` ist relativ zur Quelle der Settings (bei `.claude/settings.json` das Arbeitsverzeichnis,
bei einer Datei aus `--settings` deren Verzeichnis), `./pfad` ist relativ zum aktuellen Verzeichnis, `//pfad` ist absolut.
Deshalb nutzt `template-agent.settings.json` `./src/...` und setzt den Start im Repo-Root voraus.

### Tools

| Tool | Zweck |
| --- | --- |
| `list_configs` | Verfügbare Configs als Slugs auflisten, inkl. des in der Session gewählten Slugs |
| `select_config` | Config für die MCP-Session per Slug wählen |
| `list_document_types` | Dokumentarten (pdf, email) und Dokumenttypen der Config auflisten |
| `list_languages` | Sprachen der Config auflisten (ISO-Code für `get_text_modules`) |
| `get_context` | Kontextdaten eines Dokuments (Typ + Identifier) abrufen, bereits mit `mapping.defaults` gemerged |
| `get_text_modules` | Textbausteine für Werbemittel und Sprache abrufen, bereits mit `mapping.textModuleMapping` gemerged |
| `clear_cache` | Cache der gewählten Config leeren |
| `get_mapping` | `mapping.defaults` und `mapping.textModuleMapping` der Config lesen, inkl. `sha256` |
| `update_mapping` | Einen Mapping-Abschnitt per JSON Merge Patch ändern |
| `list_templates` | PDF- und E-Mail-Templates aller Template-Sets unter `src/Templates` auflisten |
| `read_template` | Datei unter `src/Templates` lesen |
| `write_template` | Datei unter `src/Templates` schreiben (Pfade außerhalb werden abgelehnt) |
| `render_template` | Template mit echten VHS1-Daten rendern, `output=status\|html\|pdf` |

Die ausführlichen Beschreibungen liefert der Endpunkt selbst (`tools/list`), sie stehen in `getDescription()` unter
`src/Mcp/Tool/*/`.

### Config-Auswahl

Configs werden nur über ihren Slug angesprochen: `config.<slug>.json` hat den Slug `<slug>`, der Slug `default` steht für
`config.json`. Der Slug wird entweder pro Aufruf über das Argument `config` übergeben oder pro Session mit `select_config`
festgelegt. `config.json` wird dabei nicht überschrieben, die UI ist von der Auswahl nicht betroffen.

### Typischer Ablauf

1. `select_config` mit dem gewünschten Slug.
2. `list_templates`, um den Template-Namen zu finden (z.B. `shopcartel|default_invoice.html`).
3. `render_template` mit `output=status`.
4. Bei `isError` zeigen `error.file` (relativ zu `src/Templates`) und `error.line` auf die Fehlerstelle, `error.snippet`
   zeigt die Zeilen drumherum. Die Datei mit `read_template`/`write_template` oder direkt im Dateisystem bearbeiten.
5. Erneut rendern, bis kein Fehler mehr kommt. Mit `output=pdf` entsteht die PDF-Datei, mit `output=html` das HTML vor mPDF.

### Mapping

`get_mapping` liefert `mapping.defaults` und `mapping.textModuleMapping` der Config sowie einen `sha256`.
`update_mapping` ändert einen Abschnitt per JSON Merge Patch (RFC 7396): Objekte werden rekursiv gemerged, `null` löscht
einen Schlüssel. Mit `expectedSha256` aus `get_mapping` werden parallele Änderungen nicht überschrieben. Die Änderung wirkt
sofort für `get_context`, `get_text_modules` und `render_template`, ein `clear_cache` ist nicht nötig. Zugangsdaten werden
nie verändert. Vor jeder Änderung wird ein Backup unter `src/Config/.mcp-backups/` angelegt (max. 20 pro Slug, Rechte
`0600`, gitignored).

### Dateien unter /tmp

- Sessions: `/tmp/vhs1-mcp-sessions`, sie verfallen nach 24 Stunden ohne Nutzung.
- Render-Dateien (PDFs, HTML über 100 KB): `/tmp/vhs1-mcp-renders/<session>/`, sie werden nach 24 Stunden gelöscht.
  Wegen des `/tmp`-Mounts sind sie auf dem Host unter demselben Pfad lesbar.

### Prüfskript

`tests/mcp/mcp-check.php` prüft alle Tools gegen den laufenden Endpunkt, inkl. Fehlerpfaden und Leak-Scan auf
Zugangsdaten und JWT:

    docker compose exec -T -u www-data btt_dev php tests/mcp/mcp-check.php --config=bmo

- `--all-configs` prüft zusätzlich alle vorhandenen Configs.
- `--template=`, `--type=`, `--identifiers=` und `--endpoint=` überschreiben die Standardwerte.
- `--scan=<datei>` prüft eine beliebige Datei (z.B. Ausgabe oder Transkript eines Agenten) auf Zugangsdaten und JWT.
  Zusätzlich gelten dabei `/home/`, `/var/www`, `.mcp-backups`, `Stack trace` usw. als Treffer. Für Transkripte von
  Claude Code, die das Arbeitsverzeichnis und `CLAUDE.md` enthalten, meldet `--secrets-only` nur Zugangsdaten, JWT und
  Bearer-Token.
- Ausgabe: je Check `PASS|FAIL|SKIP <id> <text>`, am Ende `RESULT pass=<n> fail=<n> skip=<n>`.
- Exit-Codes: `0` ohne FAIL, `1` bei mindestens einem FAIL, `2` bei Aufruffehlern.
- Artefakte (alle HTTP-Antworten) liegen unter `/tmp/vhs1-mcp-check/<zeitstempel>/`.

Empfehlung: Das Skript als Verify-Check eintragen, damit Änderungen am MCP-Code automatisch geprüft werden.

### Sicherheit

- Der Endpunkt hat keine Authentifizierung. Er ist nur für das lokale Docker-Setup gedacht.
- Empfehlung: In der lokalen `docker-compose.yml` den Port an `127.0.0.1` binden, z.B. `'127.0.0.1:8087:80'`.
- Anfragen mit `Origin`-Header werden nur von erlaubten Hosts angenommen (`localhost`, `127.0.0.1`,
  `toolkit.blissdev.de`). Weitere Hosts lassen sich per Umgebungsvariable `MCP_ALLOWED_ORIGIN_HOSTS` (kommagetrennt) im
  Container ergänzen.
- Grenzen der Deny-Regeln: Sie gelten für die Tools `Read`, `Edit` und `Write` von Claude Code. Ohne Bash-Sperre kann ein
  Agent Dateien trotzdem per Shell lesen. Für Template-Agenten deshalb `.claude/template-agent.settings.json` verwenden.

### Bekannte Grenzen

- VHS1-URLs, z.B. Bild-URLs, können in Render-Dateien unter `/tmp/vhs1-mcp-renders/` stehen.
- `client` wird als Teilstring redigiert und kann dadurch auch in anderen Wörtern ersetzt werden. Ist `client` gleich dem
  Slug, wird er nicht redigiert.
- Token-artige Strings in Template- und Mapping-Inhalten werden nicht zuverlässig erkannt.
- Absolute Container-Pfade in Mapping-Werten werden bewusst unverändert geliefert.
- Kundendaten im Kontext werden nicht maskiert.
- Es gibt keine PNG-Rasterung. PDFs liest der Agent direkt aus `/tmp/vhs1-mcp-renders/`.

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