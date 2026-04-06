# Design: Config-Selektor & Resizable Sidebar

**Datum:** 2026-04-06  
**Status:** Approved

## Ziel

Zwei unabhängige UX-Verbesserungen für das VHS1TwigToolkit:

1. **Config-Selektor**: Wechsel zwischen VHS1-Instanzen (`config.*.json`) direkt im Frontend, ohne Docker-Neustart und ohne manuelles Symlinken.
2. **Resizable Sidebar**: Die linke Eingabe-Sidebar kann per Drag-Splitter in der Breite angepasst werden. Die Breite wird in `localStorage` gespeichert.

---

## Feature 1: Config-Selektor

### Verhalten

- Ein `<select>`-Dropdown erscheint oben in der Sidebar, direkt unterhalb des Titels "VHS1TwigToolkit v0.3", vor allen anderen Formularfeldern.
- Das Dropdown listet alle Dateien der Form `config.*.json` im Verzeichnis `src/Config/` auf. Der angezeigte Name ist der Slug (z.B. `config.stm.json` → `stm`).
- Der aktuell aktive Config-Name wird als URL-Parameter `?config=<name>` mitgeführt.
- Beim Wechsel der Config wird die Seite neu geladen — mit dem neuen `?config=`-Parameter.
- Kein explizites Cache-Leeren: Jede Config bekommt ihren eigenen Cache-Namespace (siehe Cache-Strategie).

### Config-Parameter-Durchreichung

- Das Dropdown ist ein `<select name="config">` innerhalb des bestehenden Formulars — wird beim Form-Submit als POST-Parameter mitgeschickt.
- PHP liest den Config-Namen via `$_REQUEST['config']` (deckt POST und GET ab).
- Für iframe-URL und AJAX-Calls: JS liest den aktuell gewählten Wert aus dem Dropdown und hängt `?config=<name>` an die URL an (analog zu den bestehenden `getQueryParams()`-Aufrufen).
- Fehlt der Parameter (erster Aufruf ohne Param), wird `config.json` als Fallback genutzt (bisheriges Verhalten).

### Backend: ConfigService

- `ConfigService::getConfig()` nimmt einen optionalen Config-Namen entgegen.
- Auflösung: `config.{name}.json` wenn Name übergeben, sonst `config.json`.
- Der Config-Name wird in `index.php` aus `$_GET['config']` gelesen und dem Container als Parameter übergeben, bevor er gebaut wird.
- Validierung: Nur Dateinamen der Form `config.[a-z0-9_-]+.json` sind erlaubt (kein Path-Traversal möglich).

### Backend: Cache-Strategie

- `FilesystemAdapter` wird mit einem Namespace initialisiert: `vhs1_{config_name}` (z.B. `vhs1_stm`, `vhs1_hsn`).
- Wechselt der Benutzer die Config, wird implizit ein anderer Cache-Namespace verwendet — kein explizites Löschen nötig.
- Kehrt er zu einer früheren Config zurück, ist deren Cache noch vorhanden (warm).
- Der "Cache leeren"-Button leert nur den Cache des aktuell aktiven Namespaces.

### Backend: Config-Liste für Dropdown

- `AppController::index` liest alle `config.*.json`-Dateien aus `src/Config/` und übergibt die Slug-Liste an das Twig-Template.
- Dafür wird eine neue Methode in `ConfigService` ergänzt: `getAvailableConfigs(): array` — gibt ein sortiertes Array von Slugs zurück (z.B. `['fcm', 'hsn', 'stm', ...]`).

---

## Feature 2: Resizable Sidebar

### Layout-Änderung

- Das bisherige Bootstrap-Grid (`col-3` / `col`) wird durch ein `display: flex`-Layout auf dem äußeren Container ersetzt.
- Die Sidebar erhält eine explizite Pixel-Breite (`width`), die per JS gesetzt wird. Standardbreite: `400px` (bisheriges `min-width`).
- Zwischen Sidebar und Preview-Bereich wird ein schmales `<div id="splitter">` eingefügt (Breite: 5px, Cursor: `col-resize`, Hover-Farbe: `#17a2b8`).

### Splitter-Verhalten (JS)

- `mousedown` auf dem Splitter startet den Drag-Modus.
- `mousemove` auf `document` passt die Sidebar-Breite an: `sidebar.style.width = (e.clientX) + 'px'`.
- `mouseup` auf `document` beendet den Drag-Modus und speichert die neue Breite in `localStorage` unter dem Key `vhs1_sidebar_width`.
- Während des Drags wird `user-select: none` auf `body` gesetzt, damit kein Text selektiert wird.
- `iframe` erhält während des Drags `pointer-events: none`, damit keine Mouse-Events in den iframe "fallen".

### localStorage

- Key: `vhs1_sidebar_width`
- Gespeicherter Wert: Pixel-Breite als Integer (z.B. `420`).
- Beim Laden der Seite: Wenn der Key vorhanden ist, wird die Sidebar-Breite damit initialisiert. Sonst: `400px`.
- Min-Breite der Sidebar: `280px` (damit die Felder lesbar bleiben).
- Max-Breite: `800px` (damit die Preview nicht komplett verschwindet).

---

## Betroffene Dateien

| Datei | Änderung |
|---|---|
| `src/Config/container.php` | Config-Name als Parameter an `CacheService` und `ConfigService` übergeben |
| `src/Service/ConfigService.php` | `getConfig()` mit optionalem Config-Namen; neue Methode `getAvailableConfigs()` |
| `src/Service/CacheService.php` | Cache-Namespace wird beim Konstruktor übergeben |
| `src/Controller/AppController.php` | `index()` übergibt Config-Liste und aktiven Config-Namen ans Template |
| `src/Templates/index.html.twig` | Config-Dropdown, Flex-Layout, Splitter-Div, Splitter-JS, localStorage-Logik |

---

## Nicht im Scope

- Editieren von Config-Dateien im Browser.
- Anlegen neuer Config-Dateien über die UI.
- Multi-Tab-Synchronisation des Config-States.
