# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What This Project Is

VHS1TwigToolkit is a local development tool for previewing and testing Twig-based PDF and email templates against a live VHS1 (Blisstribute) REST API. It renders templates by fetching real context data (invoices, picklists, etc.) from the configured VHS1 instance and generating PDFs via mPDF or HTML email previews.

## Running the Application

```bash
# First-time setup
cp src/Config/config.json.dist src/Config/config.json   # then edit with VHS1 credentials
cp docker-compose.yml.dist docker-compose.yml

# Start
docker-compose up -d

# App runs at http://toolkit.blissdev.de/
```

Cache is stored in `/tmp/symfony-cache` inside the container. Clear it via the UI button or `rm -rf /tmp/symfony-cache`.

## Architecture

**Entry point:** `src/Web/index.php` - bootstraps Symfony HttpFoundation + Routing, loads the DI container (`src/Config/container.php`) and route definitions (`src/Config/routes.php`), dispatches to controllers.

**No framework kernel** - this is a micro-framework setup using individual Symfony components (Routing, DI, Cache, HttpFoundation) wired manually. All service wiring is explicit in `container.php`.

**Routes / controllers** (the two render routes plus `/mcp`):
- `GET /` → `AppController::index` - renders the toolbar UI (HTML, no VHS1 data fetched)
- `GET /{kind}/{type}/{template}/{identifiers}/{advertisingMediumCode}` → `AppController::generate` - fetches context + text modules, renders the Twig template, returns PDF or HTML
- `/api/v1/cache/*` → `ApiController` - cache inspection endpoints used by the frontend JS
- `/mcp` → `McpController` - MCP endpoint (Streamable HTTP) for AI agents, see below

**Request flow for `generate`:**
1. `ViewModelFactory` parses request parameters into a `RequestViewModel`
2. `SecurityService` obtains a JWT (cached 8h in filesystem)
3. `ContextService` fetches document context from VHS1 REST API (cached per type+identifier)
4. `TextModulesService` fetches text module translations (cached per advertising medium code + language)
5. `TwigService` renders the template using a `ChainLoader` combining `FilesystemLoader` (templates from `src/Templates/`) and `TextModuleLoader` (injects text modules as a virtual Twig template)
6. For `kind=pdf`: `PdfService` converts the HTML output to PDF via mPDF

**Template resolution in `TwigService`:**
- PDFs load from `src/Templates/slip/`, emails from `src/Templates/email/`
- Sub-directory sets: template name `project-2|invoice.html` resolves to `src/Templates/project-2/slip/invoice.html`
- Template paths can be symlinks (mount the symlink target path into docker-compose volumes)

**Custom Twig additions** (registered in `TwigService`):
- `exitbTm` function/token parser - renders text modules with fallback to key name
- `Barcode` extension - barcode generation via picqer/php-barcode-generator
- `QrCode` extension - QR code generation via ccarnivore/twig-qrcode-extension
- Custom filters: `appendToArrayByKey`, `calcAddToArrayKey`

**Configuration** (`src/Config/config.json`):
- `url`, `client`, `apiKey` - VHS1 REST API credentials
- `mapping.defaults` - override any context values per document type (e.g. `invoice.boxBarcode`)
- `mapping.textModuleMapping` - override text modules per advertising medium code or add `-LANGUAGE_CODE` suffix for language-specific overrides (e.g. `defaults-en`)

**Multiple config files** (`src/Config/config.*.json`) - multiple named configs exist for different environments/clients; `config.json` is the active one (gitignored).

## Key Files for Template Development

- `src/Service/TwigService.php` - template path resolution and Twig environment setup
- `src/Service/PdfService.php` - mPDF instantiation; edit here to add custom fonts
- `src/Twig/Loader/TextModuleLoader.php` - controls fallback behavior when a text module key has no translation
- `src/Templates/` - place or symlink template directories here

## MCP Endpoint

- Route `/mcp` → `McpController` (Streamable HTTP, session via `Mcp-Session-Id`, no auth, local use only). MCP server, session store, render file store and the 13 tools live under `src/Mcp/` (tools in `src/Mcp/Tool/*/`).
- Services added for it: `RenderService` (rendering shared with `AppController`), `TemplateStore` (file access limited to `src/Templates/`), `MappingStore` (reads/writes `mapping.*` only, with backups in `src/Config/.mcp-backups/`), `ConfigRegistry` (configs by slug, `default` = `config.json`) and `SecretRedactor` (strips `url`, `client`, `apiKey` and JWT from tool output).
- Check script: `docker compose exec -T -u www-data btt_dev php tests/mcp/mcp-check.php --config=bmo [--all-configs] [--scan=<file>]`, exit code 0 means no FAIL.
- `.mcp.json` registers the server as `vhs1-toolkit`; `.claude/settings.json` and `.claude/template-agent.settings.json` deny access to config files and backups. See README section "MCP-Endpunkt für KI-Agenten".
- Never read config files (`src/Config/config*.json`, `src/Config/.mcp-backups/`), not even with `cat` or `grep`. They contain credentials; use the MCP tools (`get_mapping`, `list_configs`) instead.
