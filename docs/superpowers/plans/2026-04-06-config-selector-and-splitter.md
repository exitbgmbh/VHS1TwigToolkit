# Config-Selektor & Resizable Sidebar Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a per-instance config dropdown to the sidebar and a draggable splitter for sidebar width, with localStorage persistence.

**Architecture:** The active config name is read from `$_REQUEST['config']` in `index.php` before the DI container is built, then threaded as a constructor argument into `ConfigService` (for file resolution) and `FilesystemAdapter` (as cache namespace). The config name travels through `ViewModelFactory` → `RequestViewModel` → `TemplateFactory` into the Twig context, and is embedded in all generated URLs (iframe, AJAX) via JS. The splitter replaces Bootstrap's grid with a plain flex layout and uses vanilla JS + `localStorage`.

**Tech Stack:** PHP 8.3, Symfony DI/Cache/HttpFoundation, Twig 3, Bootstrap 5, vanilla JS

---

## Task 1: Add `.superpowers/` to .gitignore

**Files:**
- Modify: `.gitignore`

- [ ] **Step 1: Add entry**

Open `.gitignore` and append:
```
.superpowers/
```

- [ ] **Step 2: Commit**

```bash
git add .gitignore
git commit -m "chore: ignore .superpowers brainstorm directory"
```

---

## Task 2: Parameterize ConfigService

**Files:**
- Modify: `src/Service/ConfigService.php`

- [ ] **Step 1: Add constructor param and update `getConfig()`**

Replace the constructor and `getConfig()` method:

```php
/** @var string */
private $_configName;

/** @var JsonService */
private $_jsonService;

/**
 * @param JsonService $jsonService
 * @param string $configName
 */
public function __construct(JsonService $jsonService, string $configName = '')
{
    $this->_jsonService = $jsonService;
    $this->_configName = $configName;
}

/**
 * @return array
 * @throws Exception
 */
public function getConfig(): array
{
    $filename = !empty($this->_configName)
        ? sprintf('config.%s.json', $this->_configName)
        : 'config.json';

    $configPath = __DIR__ . '/../Config/' . $filename;
    if (!file_exists($configPath)) {
        throw new Exception(sprintf('config "%s" does not exist', $configPath));
    }

    return $this->_jsonService->parseJson(file_get_contents($configPath));
}
```

- [ ] **Step 2: Add `getConfigName()` and `getAvailableConfigs()`**

Add these two methods to `ConfigService` after the constructor:

```php
/**
 * @return string
 */
public function getConfigName(): string
{
    return $this->_configName;
}

/**
 * Returns sorted list of config slugs found in src/Config/config.*.json
 * e.g. ['fcm', 'hsn', 'stm']
 *
 * @return array
 */
public function getAvailableConfigs(): array
{
    $pattern = __DIR__ . '/../Config/config.*.json';
    $files = glob($pattern);
    if (empty($files)) {
        return [];
    }

    $slugs = [];
    foreach ($files as $file) {
        $basename = basename($file, '.json'); // e.g. "config.stm"
        $slug = substr($basename, strlen('config.')); // e.g. "stm"
        $slugs[] = $slug;
    }

    sort($slugs);

    return $slugs;
}
```

- [ ] **Step 3: Verify syntax inside container**

```bash
docker-compose exec btt_dev php -l /var/www/html/src/Service/ConfigService.php
```

Expected output: `No syntax errors detected`

- [ ] **Step 4: Commit**

```bash
git add src/Service/ConfigService.php
git commit -m "feat: parameterize ConfigService with config name, add getAvailableConfigs()"
```

---

## Task 3: Add config fields to RequestViewModel

**Files:**
- Modify: `src/ViewModel/RequestViewModel.php`

- [ ] **Step 1: Add fields, constructor params, and getters**

Add `$config` and `$availableConfigs` to the class. The constructor currently has 15 params — add two more at the end:

```php
/** @var string */
private $config;

/** @var array */
private $availableConfigs;
```

Extend the constructor signature (add after `string $size`):
```php
string $config = '',
array $availableConfigs = []
```

Add to the constructor body:
```php
$this->config = $config;
$this->availableConfigs = $availableConfigs;
```

Add getters at the end of the class:
```php
/**
 * @return string
 */
public function getConfig(): string
{
    return $this->config;
}

/**
 * @return array
 */
public function getAvailableConfigs(): array
{
    return $this->availableConfigs;
}
```

- [ ] **Step 2: Verify syntax**

```bash
docker-compose exec btt_dev php -l /var/www/html/src/ViewModel/RequestViewModel.php
```

Expected: `No syntax errors detected`

- [ ] **Step 3: Commit**

```bash
git add src/ViewModel/RequestViewModel.php
git commit -m "feat: add config and availableConfigs fields to RequestViewModel"
```

---

## Task 4: Thread config through ViewModelFactory

**Files:**
- Modify: `src/Factory/ViewModelFactory.php`

- [ ] **Step 1: Inject ConfigService**

Add import and constructor param:

```php
use App\Service\ConfigService;
```

Add private field:
```php
/** @var ConfigService */
private $_configService;
```

Add to constructor signature (after `ValidatorService $validatorService`):
```php
ConfigService $configService
```

Add to constructor body:
```php
$this->_configService = $configService;
```

- [ ] **Step 2: Read config from request and populate RequestViewModel**

In `createRequestViewModel()`, add after the existing `$size = ...` line:

```php
$config = $this->_configService->getConfigName();
$availableConfigs = $this->_configService->getAvailableConfigs();
```

Add `$config` to the iframe URL call — update `_generateIframeUrl()` signature and call:

```php
$iFrameSrc = $this->_generateIframeUrl(
    $kind,
    $realType,
    $template,
    $identifiers,
    $productId,
    $advertisingMediumCode,
    $forceReload,
    $language,
    $format,
    $size,
    $config          // <-- add this
);
```

Update `new RequestViewModel(...)` call — add at the end (after `$size`):
```php
$config,
$availableConfigs
```

- [ ] **Step 3: Update `_generateIframeUrl()` to include config param**

Add `string $config = ''` as last param to `_generateIframeUrl()`. Inside the method, add to the `$query` array after the `$productId` block:

```php
if (!empty($config)) {
    $query['config'] = $config;
}
```

- [ ] **Step 4: Verify syntax**

```bash
docker-compose exec btt_dev php -l /var/www/html/src/Factory/ViewModelFactory.php
```

Expected: `No syntax errors detected`

- [ ] **Step 5: Commit**

```bash
git add src/Factory/ViewModelFactory.php
git commit -m "feat: thread config name through ViewModelFactory into iframe URL and RequestViewModel"
```

---

## Task 5: Wire config name into the DI container

**Files:**
- Modify: `src/Config/container.php`

- [ ] **Step 1: Read and validate config name at top of file**

Add immediately after the `<?php` line (before the `use` statements are used):

```php
// Read and validate active config name from request
$configName = '';
$rawConfig = $_REQUEST['config'] ?? '';
if (preg_match('/^[a-z0-9_-]+$/', $rawConfig)) {
    $configName = $rawConfig;
}
```

- [ ] **Step 2: Pass config name to `cache_adapter` as namespace**

Change:
```php
$containerBuilder->register('cache_adapter', FilesystemAdapter::class);
```
To:
```php
$containerBuilder->register('cache_adapter', FilesystemAdapter::class)
    ->addArgument('vhs1_' . $configName);
```

- [ ] **Step 3: Pass config name to `config_service`**

Change:
```php
$containerBuilder->register('config_service', ConfigService::class)
    ->addArgument(new Reference('json_service'));
```
To:
```php
$containerBuilder->register('config_service', ConfigService::class)
    ->addArgument(new Reference('json_service'))
    ->addArgument($configName);
```

- [ ] **Step 4: Add ConfigService to `view_model_factory`**

Change:
```php
$containerBuilder->register('view_model_factory', ViewModelFactory::class)
    ->setArguments([
        new Reference('language_service'),
        new Reference('types_service'),
        new Reference('validator_service'),
    ]);
```
To:
```php
$containerBuilder->register('view_model_factory', ViewModelFactory::class)
    ->setArguments([
        new Reference('language_service'),
        new Reference('types_service'),
        new Reference('validator_service'),
        new Reference('config_service'),
    ]);
```

- [ ] **Step 5: Verify syntax**

```bash
docker-compose exec btt_dev php -l /var/www/html/src/Config/container.php
```

Expected: `No syntax errors detected`

- [ ] **Step 6: Smoke test — open app**

Visit `http://toolkit.blissdev.de/` — the page should still load without errors.

- [ ] **Step 7: Commit**

```bash
git add src/Config/container.php
git commit -m "feat: wire config name into container — cache namespace and ConfigService param"
```

---

## Task 6: Pass config data to Twig template via TemplateFactory

**Files:**
- Modify: `src/Factory/TemplateFactory.php`

- [ ] **Step 1: Add `config` and `availableConfigs` to the template context**

In `TemplateFactory::create()`, extend the context array passed to `new TemplateViewModel(...)`:

```php
return new TemplateViewModel(
    'index.html.twig',
    [
        'errors'                => implode(',', $requestViewModel->getErrors()),
        'kind'                  => $requestViewModel->getKind(),
        'kinds'                 => $requestViewModel->getKinds(),
        'selectedLanguage'      => $requestViewModel->getLanguage(),
        'languages'             => $requestViewModel->getLanguages(),
        'type'                  => $requestViewModel->getType(),
        'size'                  => $requestViewModel->getSize(),
        'format'                => $requestViewModel->getFormat(),
        'types'                 => json_encode($requestViewModel->getTypes()),
        'iframeSrc'             => $requestViewModel->getIFrameSrc(),
        'template'              => $requestViewModel->getTemplate(),
        'identifiers'           => $requestViewModel->getIdentifiers(),
        'productId'             => $requestViewModel->getProductId(),
        'advertisingMediumCode' => $requestViewModel->getAdvertisingMediumCode(),
        'forceReload'           => $requestViewModel->forceReload(),
        'year'                  => date('Y'),
        'activeConfig'          => $requestViewModel->getConfig(),
        'availableConfigs'      => $requestViewModel->getAvailableConfigs(),
    ],
    [],
    $requestViewModel->getKind()
);
```

- [ ] **Step 2: Verify syntax**

```bash
docker-compose exec btt_dev php -l /var/www/html/src/Factory/TemplateFactory.php
```

Expected: `No syntax errors detected`

- [ ] **Step 3: Commit**

```bash
git add src/Factory/TemplateFactory.php
git commit -m "feat: pass activeConfig and availableConfigs to Twig template context"
```

---

## Task 7: Update Twig template — config dropdown, flex layout, splitter

**Files:**
- Modify: `src/Templates/index.html.twig`

This task rewrites the layout and adds the config dropdown and splitter. Apply the changes below in order.

- [ ] **Step 1: Replace Bootstrap grid layout with flex**

Replace the entire outer structure. Change:
```html
<div class="container-fluid">
    <div class="row">
        <div class="col-3 min-vh-100 bg-dark pt-3 menu">
```
To:
```html
<div style="display:flex;height:100vh;overflow:hidden">
    <div id="sidebar" class="bg-dark pt-3 menu" style="width:400px;min-width:280px;max-width:800px;overflow-y:auto;flex-shrink:0">
```

Change the content column:
```html
        <div class="col min-vh-100 pl-0 pr-0">
```
To:
```html
        <div id="splitter" style="width:5px;background:#444;cursor:col-resize;flex-shrink:0"></div>
        <div id="content" style="flex:1;overflow:hidden">
```

Close the outer div — replace the closing `</div></div></div>` at the end of the layout section (the three divs closing container-fluid → row → col-content) with:
```html
        </div>
    </div>
```
(Two closing divs: one for `#content`, one for the outer flex container.)

- [ ] **Step 2: Add config dropdown as first form element**

Inside the `<form id="settingsForm" ...>`, directly after the title `<li>` (the one with "VHS1TwigToolkit v0.3"), add a new `<li>`:

```html
{% if availableConfigs is defined and availableConfigs is not empty %}
    <li class="nav-item mt-2">
        <div class="input-group">
            <div class="input-group-prepend">
                <label class="input-group-text" for="config">Instanz</label>
            </div>
            <select class="custom-select d-block w-10" id="config" name="config" onchange="this.form.submit()">
                <option value=""{% if activeConfig is not defined or activeConfig is empty %} selected="selected"{% endif %}>(Standard)</option>
                {% for slug in availableConfigs %}
                    <option value="{{ slug }}"{% if activeConfig is defined and activeConfig is same as(slug) %} selected="selected"{% endif %}>{{ slug }}</option>
                {% endfor %}
            </select>
        </div>
    </li>
    <li><hr style="border-color:#555;margin:6px 0"></li>
{% endif %}
```

- [ ] **Step 3: Add config to `getQueryParams()` in JS**

Find the `getQueryParams` JS function and add `config` to the returned `URLSearchParams`:

```javascript
const getQueryParams = function () {
    const configEl = document.getElementById('config');
    return new URLSearchParams({
        kind: document.getElementById('kind').options[document.getElementById('kind').selectedIndex].value,
        type: document.getElementById('type').options[document.getElementById('type').selectedIndex].value,
        identifiers: document.getElementById('identifiers').value,
        productId: document.getElementById('productId').value,
        advertisingMediumCode: document.getElementById('advertisingMediumCode').value,
        language: document.getElementById('language') ? document.getElementById('language').value : '',
        size: document.getElementById('size').value,
        format: document.getElementById('format').value,
        config: configEl ? configEl.options[configEl.selectedIndex].value : ''
    });
};
```

(Note: also guard `language` here since it may not exist when no languages are available.)

- [ ] **Step 4: Add splitter JS and localStorage logic**

Add the following script block just before the closing `</body>` tag (after the existing `<script>` block):

```html
<script>
    // --- Sidebar width: localStorage restore ---
    (function () {
        const saved = localStorage.getItem('vhs1_sidebar_width');
        if (saved) {
            const w = parseInt(saved, 10);
            if (w >= 280 && w <= 800) {
                document.getElementById('sidebar').style.width = w + 'px';
            }
        }
    })();

    // --- Splitter drag ---
    (function () {
        const splitter = document.getElementById('splitter');
        const sidebar = document.getElementById('sidebar');
        const iframe = document.querySelector('#content iframe');
        let dragging = false;

        splitter.addEventListener('mousedown', function (e) {
            dragging = true;
            document.body.style.userSelect = 'none';
            if (iframe) { iframe.style.pointerEvents = 'none'; }
            splitter.style.background = '#17a2b8';
        });

        document.addEventListener('mousemove', function (e) {
            if (!dragging) { return; }
            const newWidth = Math.min(800, Math.max(280, e.clientX));
            sidebar.style.width = newWidth + 'px';
        });

        document.addEventListener('mouseup', function (e) {
            if (!dragging) { return; }
            dragging = false;
            document.body.style.userSelect = '';
            if (iframe) { iframe.style.pointerEvents = ''; }
            splitter.style.background = '#444';
            const finalWidth = parseInt(sidebar.style.width, 10);
            localStorage.setItem('vhs1_sidebar_width', finalWidth);
        });

        splitter.addEventListener('mouseover', function () {
            if (!dragging) { splitter.style.background = '#17a2b8'; }
        });
        splitter.addEventListener('mouseout', function () {
            if (!dragging) { splitter.style.background = '#444'; }
        });
    })();
</script>
```

- [ ] **Step 5: Smoke test**

Restart the container and visit `http://toolkit.blissdev.de/`:

```bash
docker-compose restart
```

Check:
- [ ] Config dropdown appears at the top of the sidebar listing named configs
- [ ] Selecting a config reloads the page and keeps the selection
- [ ] The splitter is visible between sidebar and content area
- [ ] Dragging the splitter resizes the sidebar
- [ ] After a page reload, the sidebar is at the last saved width
- [ ] Visit `http://toolkit.blissdev.de/?config=stm` directly — correct config is selected

- [ ] **Step 6: Commit**

```bash
git add src/Templates/index.html.twig
git commit -m "feat: add config dropdown and resizable splitter to sidebar"
```

---

## Self-Review Checklist

- [x] **Spec coverage:**
  - Config dropdown top of sidebar ✓ Task 7 Step 2
  - Config name as URL/POST param ✓ Tasks 4, 5
  - Cache namespace per config ✓ Task 5 Step 2
  - Auto-clears cache on switch (via namespace) ✓ Task 5
  - Config list via `getAvailableConfigs()` ✓ Task 2
  - Config threaded into iframe URL ✓ Task 4 Step 3
  - Config threaded into AJAX calls ✓ Task 7 Step 3
  - Flex layout replacing Bootstrap grid ✓ Task 7 Step 1
  - Splitter div ✓ Task 7 Step 1
  - Drag-to-resize JS ✓ Task 7 Step 4
  - localStorage persist/restore ✓ Task 7 Step 4
  - Min 280px / Max 800px sidebar ✓ Task 7 Step 4
  - Validation of config name (no path traversal) ✓ Task 5 Step 1

- [x] **No placeholders** — all code is complete in every step.

- [x] **Type consistency:**
  - `getConfig(): string` → `getConfigName(): string` used as `string $configName` throughout ✓
  - `getAvailableConfigs(): array` → `availableConfigs` key in context → `availableConfigs` Twig variable ✓
  - `RequestViewModel::getConfig(): string` matches what `TemplateFactory` reads ✓
