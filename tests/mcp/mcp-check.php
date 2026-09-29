<?php

declare(strict_types=1);

/**
 * Permanent check script for the MCP endpoint (/mcp). Runs inside the container:
 *
 *   docker compose exec -T -u www-data btt_dev php tests/mcp/mcp-check.php [--config=bmo] [--all-configs]
 *       [--template=shopcartel|default_invoice.html] [--type=invoice] [--identifiers=202636143468]
 *       [--endpoint=http://localhost/mcp] [--scan=<file>]
 *
 * Output: one line per check "PASS|FAIL|SKIP <id> <description>", then "RESULT pass=<n> fail=<n> skip=<n>".
 * Exit code: 0 without FAIL, 1 with at least one FAIL, 2 on invocation errors.
 * Every HTTP response of the MCP endpoint is stored below /tmp/vhs1-mcp-check/<timestamp>/.
 *
 * Secrets (url, client, apiKey, JWT) are only read into PHP variables to scan the responses for them.
 * They are never printed and never written to a file.
 */

require __DIR__ . '/../../vendor/autoload.php';

use App\DependencyInjection\ContainerFactory;
use App\Service\ConfigRegistry;

final class McpCheck
{
    public const EXPECTED_TOOLS = [
        'list_configs', 'select_config', 'list_document_types', 'list_languages', 'get_context',
        'get_text_modules', 'clear_cache', 'get_mapping', 'update_mapping', 'list_templates',
        'read_template', 'write_template', 'render_template',
    ];

    /** Tools that only have a success path in the check. */
    private const OK_ONLY_TOOLS = ['clear_cache', 'list_configs'];

    /** Tools whose answers may contain absolute container paths from mapping values (/var/www, /home/). */
    private const ABSOLUTE_PATH_TOOLS = ['get_mapping', 'update_mapping'];

    private const PROTOCOL_VERSION = '2025-06-18';
    private const CHECK_SLUG = 'zzmcpcheck';
    private const LINK_SLUG = 'zzmcplink';
    private const TEMPLATE_SET = 'zz-mcp-check';
    private const PROBE_PATH = 'zz-mcp-check/slip/probe.html';
    private const PROBE_NAME = 'zz-mcp-check|probe.html';
    private const PROBE_TM = 'probe-4711';
    private const PROBE_CTX = 'probe-4712';
    private const PROBE_LINK = 'probe-4713';
    /** Line of the probe template where the H errors are placed. */
    private const SLOT_LINE = 5;
    private const ARTIFACT_BASE = '/tmp/vhs1-mcp-check';
    private const RENDER_BASE = '/tmp/vhs1-mcp-renders/';
    private const SHA_ZERO = '0000000000000000000000000000000000000000000000000000000000000000';

    private array $opt;
    private ConfigRegistry $registry;
    private string $configDir;
    private string $templatesDir;
    private string $backupDir;
    private string $endpoint;
    private string $baseUrl;
    private ?string $artifactDir = null;
    private int $fileNr = 0;
    /** @var array<int, array{path: string, tool: ?string, group: string}> */
    private array $files = [];
    /** @var string[] */
    private array $lines = [];
    private array $counts = ['pass' => 0, 'fail' => 0, 'skip' => 0];
    /** @var string[] opened MCP sessions */
    private array $sessions = [];
    private ?string $session = null;
    private int $rpcId = 0;
    private array $toolOk = [];
    private array $toolErr = [];
    /** @var array<string, int> number of HTTP 500 answers per group */
    private array $status500 = [];
    private string $group = '';
    private bool $cleanedUp = false;
    private bool $resultWritten = false;
    private string $probeContent = '';
    private array $baseline = [];
    /** @var array<int, array{0: string, 1: string}> extra secrets for the main scan, e.g. the JWT of the test config */
    private array $extraSecrets = [];

    public function __construct(array $opt)
    {
        $this->opt = $opt;
        $this->registry = new ConfigRegistry();
        $this->configDir = (string) realpath($this->registry->getConfigDir());
        $this->templatesDir = (string) realpath(__DIR__ . '/../../src/Templates');
        $this->backupDir = $this->configDir . '/.mcp-backups';
        $this->endpoint = $opt['endpoint'];
        $this->baseUrl = (string) preg_replace('#/mcp/?$#', '', $this->endpoint);
    }

    // ------------------------------------------------------------------ entry points

    public function runScan(string $file): int
    {
        $skipped = [];
        $secrets = $this->collectSecrets($this->opt['config'], $skipped);
        $text = @file_get_contents($file);
        if (!is_string($text)) {
            fwrite(STDERR, "scan file not readable\n");

            return 2;
        }

        $hits = self::scanText($text, $secrets ?? [], false);
        if ($hits === []) {
            echo 'PASS scan ' . basename($file) . "\n";

            return 0;
        }

        echo 'FAIL scan ' . basename($file) . ' [hits: ' . implode(', ', $hits) . "]\n";

        return 1;
    }

    public function runFull(): int
    {
        register_shutdown_function([$this, 'onShutdown']);

        $this->artifactDir = self::ARTIFACT_BASE . '/' . date('Ymd-His');
        if (!is_dir($this->artifactDir) && !@mkdir($this->artifactDir, 0777, true)) {
            fwrite(STDERR, "artifact directory could not be created\n");

            return 2;
        }

        try {
            $setupOk = $this->runGroup('A', fn () => $this->groupA());
            if ($setupOk === true) {
                $this->runGroup('B', fn () => $this->groupB());
                $this->runGroup('C', fn () => $this->groupC());
                if ($this->session !== null) {
                    $this->runGroup('D', fn () => $this->groupD());
                    $this->runGroup('E', fn () => $this->groupE());
                    $this->runGroup('F', fn () => $this->groupF());
                    $this->runGroup('G', fn () => $this->groupG());
                    $this->runGroup('H', fn () => $this->groupH());
                } else {
                    $this->report('FAIL', 'C0', 'keine MCP-Session, D bis H nicht ausgefuehrt');
                }
                $this->runGroup('I', fn () => $this->groupI());
                if ($this->opt['all-configs']) {
                    $this->runGroup('J', fn () => $this->groupJ());
                }
                $this->runGroup('K', fn () => $this->groupK());
                $this->runGroup('L', fn () => $this->groupL());
            } else {
                $this->report('FAIL', 'A0', 'Setup abgebrochen, nur Aufraeumen');
            }
        } finally {
            $clean = $this->cleanup();
            $this->check('Z1', $clean, 'Aufraeumen: Test-Configs, Symlink, Backups zzmcp*, Template-Set entfernt');
        }

        $this->finish();

        return $this->counts['fail'] > 0 ? 1 : 0;
    }

    public function onShutdown(): void
    {
        if (!$this->cleanedUp) {
            $clean = $this->cleanup();
            $this->check('Z1', $clean, 'Aufraeumen nach Abbruch');
        }
        if (!$this->resultWritten && $this->artifactDir !== null) {
            $this->finish();
        }
    }

    // ------------------------------------------------------------------ groups

    private function groupA(): bool
    {
        $this->cleanup(true);

        $source = $this->registry->getConfigPath($this->opt['config']);
        $copy = $this->configDir . '/config.' . self::CHECK_SLUG . '.json';
        $link = $this->configDir . '/config.' . self::LINK_SLUG . '.json';

        $ok = @copy($source, $copy);
        $this->check('A1', $ok, 'config.' . self::CHECK_SLUG . '.json als Kopie der Quell-Config angelegt');
        if (!$ok) {
            return false;
        }

        $ok = @symlink('config.' . self::CHECK_SLUG . '.json', $link) && is_link($link);
        $this->check('A2', $ok, 'Symlink config.' . self::LINK_SLUG . '.json -> config.' . self::CHECK_SLUG . '.json');
        if (!$ok) {
            return false;
        }

        $dir = $this->templatesDir . '/' . self::TEMPLATE_SET . '/slip';
        $this->probeContent = self::probe('<p id="slot">ok</p>');
        $ok = (is_dir($dir) || @mkdir($dir, 0775, true))
            && @file_put_contents($this->templatesDir . '/' . self::PROBE_PATH, $this->probeContent) !== false;
        $this->check('A3', $ok, 'Template-Set ' . self::PROBE_PATH . ' angelegt');

        return $ok;
    }

    private function groupB(): void
    {
        $i = 0;
        foreach ($this->routeUrls() as $label => $url) {
            $i++;
            $response = $this->http('GET', $url);
            $this->baseline['routes'][$label] = [$response['status'], self::contentType($response)];
            $this->check(
                'B' . $i,
                $response['status'] > 0,
                sprintf('Baseline %s: %d %s', $label, $response['status'], self::contentType($response))
            );
        }

        $this->baseline['hashes'] = $this->realConfigHashes();
        $this->check('B6', $this->baseline['hashes'] !== [], sprintf('sha256 von %d echten Configs gemerkt', count($this->baseline['hashes'])));

        $this->baseline['readlink'] = @readlink($this->configDir . '/config.json');
        $this->check('B7', true, 'Ziel von config.json gemerkt');

        $this->baseline['backups'] = $this->listBackups();
        $this->check('B8', true, sprintf('.mcp-backups gemerkt (%d Dateien)', count($this->baseline['backups'])));

        $this->baseline['shopcartel'] = $this->readOnlySetHash();
        $this->check('B9', true, 'Hash des Template-Sets ' . $this->readOnlySet() . ' gemerkt');
    }

    private function groupC(): void
    {
        $init = $this->mcpPost(null, [
            'jsonrpc' => '2.0',
            'id' => ++$this->rpcId,
            'method' => 'initialize',
            'params' => [
                'protocolVersion' => self::PROTOCOL_VERSION,
                'capabilities' => new \stdClass(),
                'clientInfo' => ['name' => 'mcp-check', 'version' => '1.0'],
            ],
        ], 'initialize');
        $sessionId = $init['headers']['mcp-session-id'] ?? '';
        $ok = $init['status'] === 200 && $sessionId !== ''
            && ($init['json']['result']['protocolVersion'] ?? null) === self::PROTOCOL_VERSION;
        $this->check('C1', $ok, 'initialize ' . self::PROTOCOL_VERSION . ' liefert Mcp-Session-Id');
        if ($sessionId === '') {
            return;
        }
        $this->session = $sessionId;
        $this->sessions[] = $sessionId;

        $notified = $this->mcpPost($sessionId, ['jsonrpc' => '2.0', 'method' => 'notifications/initialized'], 'notifications-initialized');
        $this->check('C2', $notified['status'] === 202, 'notifications/initialized liefert 202 (' . $notified['status'] . ')');

        $list = $this->mcpPost($sessionId, ['jsonrpc' => '2.0', 'id' => ++$this->rpcId, 'method' => 'tools/list'], 'tools-list');
        $tools = $list['json']['result']['tools'] ?? [];
        $names = [];
        $strict = true;
        foreach (is_array($tools) ? $tools : [] as $tool) {
            $names[] = (string) ($tool['name'] ?? '');
            if (($tool['inputSchema']['additionalProperties'] ?? null) !== false) {
                $strict = false;
            }
        }
        $expected = self::EXPECTED_TOOLS;
        sort($names);
        sort($expected);
        $this->check('C3', $names === $expected && $strict, sprintf('tools/list: %d Tools, genau die 13 erwarteten, alle additionalProperties=false', count($names)));

        $unknown = $this->callTool('zz_unknown_tool', [], 'unknown-tool');
        $this->check('C4', $unknown['rpcError'] || $unknown['isError'] === true, 'unbekanntes Tool ist ein Fehler');

        $noSession = $this->mcpPost(null, ['jsonrpc' => '2.0', 'id' => ++$this->rpcId, 'method' => 'tools/list'], 'no-session');
        $this->check('C5', $noSession['status'] === 400, 'POST ohne Session liefert 400 (' . $noSession['status'] . ')');

        $get = $this->record('get', $this->http('GET', $this->endpoint, ['Accept' => 'text/event-stream']), null);
        $this->check('C6', $get['status'] === 405, 'GET /mcp liefert 405 (' . $get['status'] . ')');

        $evil = $this->mcpPost(
            $sessionId,
            ['jsonrpc' => '2.0', 'id' => ++$this->rpcId, 'method' => 'tools/list'],
            'origin-evil',
            ['Origin' => 'http://evil.example']
        );
        $this->check('C7', $evil['status'] === 403 && !isset($evil['json']['result']), 'Origin http://evil.example abgelehnt (' . $evil['status'] . ')');
    }

    private function groupD(): void
    {
        $list = $this->callTool('list_configs', [], 'list_configs');
        $configs = $list['data']['configs'] ?? null;
        $onlySlugs = is_array($configs) && $configs !== [];
        foreach (is_array($configs) ? $configs : [] as $entry) {
            if (!is_string($entry) || preg_match('/^[a-z0-9_-]+$/', $entry) !== 1) {
                $onlySlugs = false;
            }
        }
        $this->check('D1', $list['ok'] && $onlySlugs && in_array(self::CHECK_SLUG, $configs, true), 'list_configs enthaelt ' . self::CHECK_SLUG . ', nur Slugs');

        $none = $this->callTool('list_document_types', [], 'list_document_types-no-config');
        $this->check('D2', $none['isError'] === true, 'Tool ohne gewaehlte Config liefert isError');

        $bad = $this->callTool('select_config', ['config' => 'gibtesnicht'], 'select_config-unknown');
        $this->check('D3', $bad['isError'] === true, 'select_config gibtesnicht liefert isError');

        $select = $this->callTool('select_config', ['config' => self::CHECK_SLUG], 'select_config');
        $this->check('D4', $select['ok'] && ($select['data']['selected'] ?? null) === self::CHECK_SLUG, 'select_config ' . self::CHECK_SLUG . ' ist ok');
    }

    private function groupE(): void
    {
        $ref = $this->refArgs();

        $r = $this->callTool('list_document_types', [], 'list_document_types');
        $this->check('E1', $r['ok'], 'list_document_types ok');

        $r = $this->callTool('list_languages', [], 'list_languages');
        $this->check('E2', $r['ok'], 'list_languages ok');

        $r = $this->callTool('get_context', $ref + ['mode' => 'keys'], 'get_context-keys');
        $this->check('E3', $r['ok'], 'get_context Referenzfall mode=keys ok');

        $r = $this->callTool('get_text_modules', [], 'get_text_modules');
        $this->check('E4', $r['ok'], 'get_text_modules ok');

        $r = $this->callTool('clear_cache', [], 'clear_cache');
        $this->check('E5', $r['ok'], 'clear_cache ok');

        $r = $this->callTool('get_context', ['identifiers' => '0000000000'] + $ref + ['mode' => 'keys'], 'get_context-unknown-identifiers');
        $this->check('E6', $r['isError'] === true, 'get_context Identifier 0000000000 liefert isError');

        $r = $this->callTool('get_context', ['kind' => 'gibtesnicht'] + $ref, 'get_context-unknown-kind');
        $this->check('E7', $r['isError'] === true, 'get_context kind=gibtesnicht liefert isError');

        $r = $this->callTool('list_languages', ['config' => 'gibtesnicht'], 'list_languages-unknown-config');
        $this->check('E8', $r['isError'] === true, 'list_languages config=gibtesnicht liefert isError');

        $r = $this->callTool('get_text_modules', ['config' => 'gibtesnicht'], 'get_text_modules-unknown-config');
        $this->check('E9', $r['isError'] === true, 'get_text_modules config=gibtesnicht liefert isError');
    }

    private function groupF(): void
    {
        $ref = $this->refArgs();

        $get = $this->callTool('get_mapping', [], 'get_mapping');
        $sha = (string) ($get['data']['sha256'] ?? '');
        $this->check('F1', $get['ok'] && preg_match('/^[a-f0-9]{64}$/', $sha) === 1, 'get_mapping liefert sha256');

        $tm = $this->callTool('update_mapping', [
            'section' => 'textModuleMapping',
            'patch' => ['defaults' => ['tm.zzMcpProbe' => self::PROBE_TM]],
            'expectedSha256' => $sha,
        ], 'update_mapping-textModuleMapping');
        $this->check('F2', $tm['ok'] && ($tm['data']['changed'] ?? null) === true, 'update_mapping textModuleMapping mit expectedSha256: changed=true');

        $ctx = $this->callTool('update_mapping', [
            'section' => 'defaults',
            'patch' => ['system' => ['zzMcpProbe' => self::PROBE_CTX]],
        ], 'update_mapping-defaults');
        $this->check('F3', $ctx['ok'] && ($ctx['data']['changed'] ?? null) === true, 'update_mapping defaults: changed=true');

        $modules = $this->callTool('get_text_modules', ['prefix' => 'tm.zzMcpProbe'], 'get_text_modules-probe');
        $context = $this->callTool('get_context', $ref + ['path' => 'system.zzMcpProbe'], 'get_context-probe');
        $this->check(
            'F4',
            ($modules['data']['textModules']['tm.zzMcpProbe'] ?? null) === self::PROBE_TM
                && ($context['data']['data'] ?? null) === self::PROBE_CTX,
            'ohne clear_cache: get_text_modules und get_context liefern die Probe-Werte'
        );

        $render = $this->renderProbe('render-probe-values');
        $html = (string) ($render['data']['html'] ?? '');
        $this->check('F5', $render['ok'] && str_contains($html, self::PROBE_TM) && str_contains($html, self::PROBE_CTX), 'render_template ' . self::PROBE_NAME . ' html enthaelt ' . self::PROBE_TM . ' und ' . self::PROBE_CTX);

        $errors = [
            'veraltetes expectedSha256' => ['update_mapping', [
                'section' => 'textModuleMapping', 'patch' => ['defaults' => ['tm.zzMcpProbe' => 'x']], 'expectedSha256' => $sha,
            ]],
            '[redacted:url] im Patch' => ['update_mapping', [
                'section' => 'textModuleMapping', 'patch' => ['defaults' => ['tm.zzMcpProbe' => '[redacted:url]']],
            ]],
            'section=gibtesnicht' => ['update_mapping', ['section' => 'gibtesnicht', 'patch' => ['a' => 'b']]],
            '{"EXB": "x"} in textModuleMapping' => ['update_mapping', ['section' => 'textModuleMapping', 'patch' => ['EXB' => 'x']]],
            'patch als String' => ['update_mapping', ['section' => 'defaults', 'patch' => 'x']],
            'get_mapping config=gibtesnicht' => ['get_mapping', ['config' => 'gibtesnicht']],
        ];
        $i = 0;
        foreach ($errors as $label => [$tool, $args]) {
            $i++;
            $r = $this->callTool($tool, $args, $tool . '-error-' . $i);
            $this->check('F6.' . $i, $r['isError'] === true, $label . ' liefert isError');
        }

        $link = $this->configDir . '/config.' . self::LINK_SLUG . '.json';
        $copy = $this->configDir . '/config.' . self::CHECK_SLUG . '.json';
        $viaLink = $this->callTool('update_mapping', [
            'config' => self::LINK_SLUG,
            'section' => 'textModuleMapping',
            'patch' => ['defaults' => ['tm.zzMcpLink' => self::PROBE_LINK]],
        ], 'update_mapping-link');
        clearstatcache();
        $target = json_decode((string) @file_get_contents($copy), true);
        $this->check(
            'F7',
            $viaLink['ok'] && is_link($link) && @readlink($link) === 'config.' . self::CHECK_SLUG . '.json'
                && ($target['mapping']['textModuleMapping']['defaults']['tm.zzMcpLink'] ?? null) === self::PROBE_LINK,
            'Patch ueber Slug ' . self::LINK_SLUG . ': Symlink bleibt, Ziel geaendert'
        );

        $this->check('F8', $this->integrityOk($copy, $detail), 'Integritaet: ' . $detail);

        $removeTm = $this->callTool('update_mapping', [
            'section' => 'textModuleMapping',
            'patch' => ['defaults' => ['tm.zzMcpProbe' => null, 'tm.zzMcpLink' => null]],
        ], 'update_mapping-remove-tm');
        $removeCtx = $this->callTool('update_mapping', [
            'section' => 'defaults',
            'patch' => ['system' => ['zzMcpProbe' => null]],
        ], 'update_mapping-remove-ctx');
        $render = $this->renderProbe('render-probe-removed');
        $html = (string) ($render['data']['html'] ?? '');
        $this->check(
            'F9',
            $removeTm['ok'] && $removeCtx['ok'] && $render['ok'] && str_contains($html, 'tm-missing')
                && str_contains($html, 'ctx-missing') && !str_contains($html, self::PROBE_TM),
            'Proben per null entfernt, Rendern liefert tm-missing und ctx-missing'
        );
    }

    private function groupG(): void
    {
        $list = $this->callTool('list_templates', [], 'list_templates');
        $this->check('G1', $list['ok'] && str_contains($list['text'], self::PROBE_PATH), 'list_templates findet ' . self::TEMPLATE_SET);

        $bad = $this->callTool('list_templates', ['kind' => 'gibtesnicht'], 'list_templates-bad-kind');
        $this->check('G2', $bad['isError'] === true, 'list_templates kind=gibtesnicht liefert isError');

        $read = $this->callTool('read_template', ['path' => self::PROBE_PATH], 'read_template-probe');
        $sha = (string) ($read['data']['sha256'] ?? '');
        $this->check('G3', $read['ok'] && $sha === hash('sha256', $this->probeContent), 'read_template ' . self::PROBE_PATH . ' liefert sha256');

        $write = $this->callTool('write_template', [
            'path' => self::PROBE_PATH, 'content' => $this->probeContent, 'expectedSha256' => $sha,
        ], 'write_template-same');
        $this->check('G4', $write['ok'], 'write_template identischer Inhalt mit expectedSha256 ok');

        $stale = $this->callTool('write_template', [
            'path' => self::PROBE_PATH, 'content' => $this->probeContent, 'expectedSha256' => self::SHA_ZERO,
        ], 'write_template-stale');
        $this->check('G5', $stale['isError'] === true, 'write_template veraltetes expectedSha256 liefert isError');

        $readOnlyPath = $this->readOnlyTemplatePath();
        if ($readOnlyPath === null) {
            $this->report('SKIP', 'G6', 'Template ohne Set, kein Symlink-Set zu lesen');
        } else {
            $r = $this->callTool('read_template', ['path' => $readOnlyPath], 'read_template-readonly-set');
            $this->check('G6', $r['ok'], 'read_template ' . $readOnlyPath . ' ok (nur lesen)');
        }

        $slug = $this->opt['config'];
        $cases = [
            ['read_template', ['path' => '../Config/config.' . $slug . '.json']],
            ['read_template', ['path' => self::TEMPLATE_SET . '/../../Config/config.' . $slug . '.json']],
            ['read_template', ['path' => '/etc/passwd']],
            ['write_template', ['path' => '../Config/x.json', 'content' => '{}']],
        ];
        $secrets = $this->collectSecrets($slug, $unused) ?? [];
        $i = 6;
        foreach ($cases as [$tool, $args]) {
            $i++;
            $r = $this->callTool($tool, $args, $tool . '-outside-' . $i);
            $hits = self::scanText($r['text'], $secrets, false);
            $this->check('G' . $i, $r['isError'] === true && $hits === [], $tool . ' ' . $args['path'] . ' liefert isError ohne Secret und Host-Pfad');
        }
        $this->check('G11', !file_exists($this->configDir . '/x.json'), 'src/Config/x.json wurde nicht angelegt');
    }

    private function groupH(): void
    {
        $ref = $this->refArgs() + ['template' => $this->opt['template']];

        $status = $this->callTool('render_template', $ref + ['output' => 'status'], 'render_template-status');
        $this->check('H1', $status['ok'] && ($status['data']['ok'] ?? null) === true, 'Referenzfall output=status ok');

        $pdf = $this->callTool('render_template', $ref + ['output' => 'pdf'], 'render_template-pdf');
        $file = (string) ($pdf['data']['pdfFile'] ?? '');
        $head = ($file !== '' && str_starts_with($file, self::RENDER_BASE)) ? (string) @file_get_contents($file, false, null, 0, 4) : '';
        $this->check('H2', $pdf['ok'] && $head === '%PDF', 'Referenzfall output=pdf ok, Datei unter ' . self::RENDER_BASE . ' beginnt mit %PDF');

        $cases = [
            'a' => ['Syntaxfehler {% if %}', '<p id="slot">{% if %}</p>'],
            'b' => ['unbekannter Filter', '<p id="slot">{{ \'x\'|zzMcpUnknownFilter }}</p>'],
            'c' => ['fehlendes Include', '<p id="slot">{% include \'zz-mcp-missing-include.html\' %}</p>'],
        ];
        foreach ($cases as $key => [$label, $slot]) {
            $broken = self::probe($slot);
            $write = $this->callTool('write_template', ['path' => self::PROBE_PATH, 'content' => $broken], 'write_template-broken-' . $key);
            $r = $this->renderProbe('render-broken-' . $key);
            $error = $r['data']['error'] ?? [];
            $line = $error['line'] ?? null;
            $this->check(
                'H3' . $key,
                $write['ok'] && $r['isError'] === true && ($error['file'] ?? null) === self::PROBE_PATH && $line === self::SLOT_LINE,
                sprintf('%s: isError, error.file=%s, error.line=%s (erwartet %d)', $label, (string) ($error['file'] ?? '-'), var_export($line, true), self::SLOT_LINE)
            );
        }

        $restore = $this->callTool('write_template', ['path' => self::PROBE_PATH, 'content' => $this->probeContent], 'write_template-restore');
        $r = $this->renderProbe('render-restored');
        $this->check('H4', $restore['ok'] && $r['ok'], 'Originalinhalt zurueckgeschrieben, Rendern wieder ok');

        $count = 0;
        foreach (['C', 'D', 'E', 'F', 'G', 'H'] as $g) {
            $count += $this->status500[$g] ?? 0;
        }
        $this->check('H5', $count === 0, sprintf('keine Antwort in C bis H mit HTTP-Status 500 (%d)', $count));

        $missing = [];
        foreach (self::EXPECTED_TOOLS as $tool) {
            if (($this->toolOk[$tool] ?? 0) < 1) {
                $missing[] = $tool . ':ok';
            }
            if (!in_array($tool, self::OK_ONLY_TOOLS, true) && ($this->toolErr[$tool] ?? 0) < 1) {
                $missing[] = $tool . ':isError';
            }
        }
        $this->check('H6', $missing === [], 'alle 13 Tools erfolgreich und mit isError aufgerufen' . ($missing === [] ? '' : ' (fehlt: ' . implode(', ', $missing) . ')'));

        // JWT of the test config (own cache namespace, own login) for the leak scan
        $skipped = [];
        $checkSecrets = $this->collectSecrets(self::CHECK_SLUG, $skipped) ?? [];
        foreach ($checkSecrets as [$label, $value]) {
            if ($label === 'jwt') {
                $this->extraSecrets[] = ['jwt-' . self::CHECK_SLUG, $value];
            }
        }
    }

    private function groupI(): void
    {
        $skipped = [];
        $secrets = $this->collectSecrets($this->opt['config'], $skipped);
        if ($secrets === null) {
            $this->report('FAIL', 'I0', 'Secrets der Quell-Config nicht lesbar, kein Leak-Scan');

            return;
        }
        $secrets = array_merge($secrets, $this->extraSecrets);
        $this->report(
            $skipped === [] ? 'PASS' : 'SKIP',
            'I0',
            sprintf('%d Secret-Werte geladen, uebersprungen: %s', count($secrets), $skipped === [] ? 'keine' : implode(', ', $skipped))
        );

        foreach ($this->files as $nr => $file) {
            $text = (string) @file_get_contents($file['path']);
            $hits = self::scanText($text, $secrets, in_array($file['tool'], self::ABSOLUTE_PATH_TOOLS, true));
            $this->check('I' . $nr, $hits === [], 'Leak-Scan ' . basename($file['path']) . ($hits === [] ? '' : ' [hits: ' . implode(', ', $hits) . ']'));
        }
    }

    private function groupJ(): void
    {
        $n = 0;
        foreach ($this->registry->listSlugs() as $slug) {
            if (str_starts_with($slug, 'zz')) {
                continue;
            }
            $n++;
            $id = 'J' . $n;

            try {
                $this->quiet(fn () => ContainerFactory::createForSlug($slug, $this->registry));
            } catch (\Throwable) {
                $this->report('SKIP', $id, $slug . ' Container nicht baubar');
                continue;
            }

            $init = $this->mcpPost(null, [
                'jsonrpc' => '2.0', 'id' => ++$this->rpcId, 'method' => 'initialize',
                'params' => ['protocolVersion' => self::PROTOCOL_VERSION, 'capabilities' => new \stdClass(), 'clientInfo' => ['name' => 'mcp-check', 'version' => '1.0']],
            ], 'J-' . $slug . '-initialize');
            $session = $init['headers']['mcp-session-id'] ?? '';
            if ($session === '') {
                $this->report('FAIL', $id, $slug . ' initialize ohne Session');
                continue;
            }
            $this->sessions[] = $session;
            $this->mcpPost($session, ['jsonrpc' => '2.0', 'method' => 'notifications/initialized'], 'J-' . $slug . '-initialized');

            $fileStart = count($this->files);
            $calls = [
                ['select_config', ['config' => $slug]],
                ['list_document_types', []],
                ['list_languages', []],
                ['get_text_modules', []],
            ];
            $k = 0;
            foreach ($calls as [$tool, $args]) {
                $k++;
                $r = $this->callTool($tool, $args, 'J-' . $slug . '-' . $tool, $session);
                if ($r['status'] === 500 || $r['status'] === 0) {
                    $this->report('FAIL', $id . '.' . $k, $slug . ' ' . $tool . ' HTTP ' . $r['status']);
                } elseif ($r['isError'] !== false) {
                    $this->report('SKIP', $id . '.' . $k, $slug . ' ' . $tool);
                } else {
                    $this->report('PASS', $id . '.' . $k, $slug . ' ' . $tool);
                }
            }

            $skipped = [];
            $secrets = $this->collectSecrets($slug, $skipped);
            if ($secrets === null) {
                $this->report('SKIP', $id . '.scan', $slug . ' Secrets nicht lesbar');
                continue;
            }
            if ($skipped !== []) {
                $this->report('SKIP', $id . '.0', $slug . ' uebersprungen: ' . implode(', ', $skipped));
            }
            foreach (array_slice($this->files, $fileStart, null, true) as $nr => $file) {
                $text = (string) @file_get_contents($file['path']);
                $hits = self::scanText($text, $secrets, false);
                $this->check($id . '.I' . $nr, $hits === [], 'Leak-Scan ' . basename($file['path']) . ($hits === [] ? '' : ' [hits: ' . implode(', ', $hits) . ']'));
            }
        }
    }

    private function groupK(): void
    {
        $i = 0;
        foreach ($this->routeUrls() as $label => $url) {
            $i++;
            $response = $this->http('GET', $url);
            $now = [$response['status'], self::contentType($response)];
            $before = $this->baseline['routes'][$label] ?? null;
            $this->check('K' . $i, $before === $now, sprintf('%s unveraendert: %d %s', $label, $now[0], $now[1]));
        }

        $hashes = $this->realConfigHashes();
        $this->check('K6', isset($this->baseline['hashes']) && $hashes === $this->baseline['hashes'], sprintf('sha256 der %d echten Configs unveraendert', count($hashes)));

        clearstatcache();
        $this->check('K7', @readlink($this->configDir . '/config.json') === ($this->baseline['readlink'] ?? null), 'Ziel von config.json unveraendert');

        $new = array_diff($this->listBackups(), $this->baseline['backups'] ?? []);
        $foreign = array_filter($new, static fn (string $f): bool => !str_starts_with($f, 'zzmcp'));
        $this->check('K8', $foreign === [], sprintf('keine neuen Backups echter Slugs (%d)', count($foreign)));

        $this->check('K9', $this->readOnlySetHash() === ($this->baseline['shopcartel'] ?? null), 'Template-Set ' . $this->readOnlySet() . ' unveraendert');
    }

    private function groupL(): void
    {
        $i = 0;
        foreach ($this->sessions as $session) {
            $i++;
            $response = $this->record('delete-' . $i, $this->http('DELETE', $this->endpoint, ['Mcp-Session-Id' => $session]), null);
            $this->check('L' . $i, $response['status'] === 204, 'DELETE /mcp Session ' . $i . ' liefert 204 (' . $response['status'] . ')');
        }
        $this->sessions = [];
    }

    // ------------------------------------------------------------------ helpers: checks and output

    private function runGroup(string $group, callable $fn): mixed
    {
        $this->group = $group;
        try {
            return $fn();
        } catch (\Throwable $e) {
            $this->report('FAIL', $group . 'X', sprintf('Abbruch der Gruppe: %s in Zeile %d', (new \ReflectionClass($e))->getShortName(), $e->getLine()));

            return false;
        }
    }

    private function check(string $id, bool $ok, string $description): void
    {
        $this->report($ok ? 'PASS' : 'FAIL', $id, $description);
    }

    private function report(string $status, string $id, string $description): void
    {
        $this->counts[strtolower($status)]++;
        $line = $status . ' ' . $id . ' ' . $description;
        $this->lines[] = $line;
        echo $line . "\n";
    }

    private function finish(): void
    {
        $summary = sprintf('RESULT pass=%d fail=%d skip=%d', $this->counts['pass'], $this->counts['fail'], $this->counts['skip']);
        echo $summary . "\n";
        fwrite(STDERR, 'ARTIFACTS ' . $this->artifactDir . "\n");
        if ($this->artifactDir !== null) {
            @file_put_contents($this->artifactDir . '/result.txt', implode("\n", array_merge($this->lines, [$summary])) . "\n");
        }
        $this->resultWritten = true;
    }

    // ------------------------------------------------------------------ helpers: HTTP and MCP

    /**
     * @return array{status: int, headers: array<string, string>, rawHeaders: string, body: string}
     */
    private function http(string $method, string $url, array $headers = [], ?string $body = null): array
    {
        $headerLines = ['Expect:'];
        foreach ($headers as $name => $value) {
            $headerLines[] = $name . ': ' . $value;
        }

        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_CUSTOMREQUEST => $method,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HEADER => true,
                CURLOPT_HTTPHEADER => $headerLines,
                CURLOPT_TIMEOUT => 300,
                CURLOPT_FOLLOWLOCATION => false,
            ]);
            if ($body !== null) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
            }
            $raw = curl_exec($ch);
            if (!is_string($raw)) {
                return ['status' => 0, 'headers' => [], 'rawHeaders' => '', 'body' => ''];
            }
            $headerSize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
            $rawHeaders = substr($raw, 0, $headerSize);
            $responseBody = (string) substr($raw, $headerSize);
        } else {
            $context = stream_context_create(['http' => [
                'method' => $method,
                'header' => implode("\r\n", $headerLines),
                'content' => $body ?? '',
                'ignore_errors' => true,
                'timeout' => 300,
                'follow_location' => 0,
            ]]);
            $responseBody = @file_get_contents($url, false, $context);
            $responseHeaders = function_exists('http_get_last_response_headers')
                ? (http_get_last_response_headers() ?? [])
                : ($http_response_header ?? []);
            if (!is_string($responseBody) && $responseHeaders === []) {
                return ['status' => 0, 'headers' => [], 'rawHeaders' => '', 'body' => ''];
            }
            $rawHeaders = implode("\r\n", $responseHeaders);
            $responseBody = (string) $responseBody;
        }

        // keep the last header block only (e.g. after "100 Continue")
        $blocks = preg_split("/\r?\n\r?\n/", trim($rawHeaders));
        $lastBlock = (string) end($blocks);
        $lines = preg_split("/\r?\n/", $lastBlock) ?: [];
        $status = 0;
        if (isset($lines[0]) && preg_match('#^HTTP/\S+\s+(\d{3})#', $lines[0], $m)) {
            $status = (int) $m[1];
        }
        $parsed = [];
        foreach (array_slice($lines, 1) as $line) {
            $pos = strpos($line, ':');
            if ($pos !== false) {
                $parsed[strtolower(trim(substr($line, 0, $pos)))] = trim(substr($line, $pos + 1));
            }
        }

        return ['status' => $status, 'headers' => $parsed, 'rawHeaders' => $lastBlock, 'body' => $responseBody];
    }

    private function mcpPost(?string $session, array $message, string $name, array $extraHeaders = [], ?string $tool = null): array
    {
        $headers = ['Content-Type' => 'application/json', 'Accept' => 'application/json, text/event-stream'];
        if ($session !== null) {
            $headers['Mcp-Session-Id'] = $session;
            $headers['MCP-Protocol-Version'] = self::PROTOCOL_VERSION;
        }
        $headers += $extraHeaders;

        $response = $this->http('POST', $this->endpoint, $headers, (string) json_encode($message, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        $response = $this->record($name, $response, $tool);
        $response['json'] = json_decode($response['body'], true);

        return $response;
    }

    /**
     * Stores the response below the artifact dir and counts HTTP 500 per group.
     */
    private function record(string $name, array $response, ?string $tool): array
    {
        if ($response['status'] === 500) {
            $this->status500[$this->group] = ($this->status500[$this->group] ?? 0) + 1;
        }
        if ($this->artifactDir !== null) {
            $this->fileNr++;
            $path = sprintf('%s/%03d-%s.txt', $this->artifactDir, $this->fileNr, preg_replace('/[^A-Za-z0-9._-]/', '_', $name));
            @file_put_contents($path, $response['rawHeaders'] . "\n\n" . $response['body']);
            $this->files[$this->fileNr] = ['path' => $path, 'tool' => $tool, 'group' => $this->group];
        }

        return $response;
    }

    /**
     * @return array{status: int, ok: bool, isError: ?bool, rpcError: bool, text: string, data: mixed}
     */
    private function callTool(string $tool, array $arguments, string $name, ?string $session = null): array
    {
        $response = $this->mcpPost($session ?? $this->session, [
            'jsonrpc' => '2.0',
            'id' => ++$this->rpcId,
            'method' => 'tools/call',
            'params' => ['name' => $tool, 'arguments' => (object) $arguments],
        ], $name, [], $tool);

        $json = is_array($response['json']) ? $response['json'] : [];
        $result = $json['result'] ?? null;
        $isError = is_array($result) ? (($result['isError'] ?? false) === true) : null;
        $text = '';
        if (is_array($result)) {
            foreach ($result['content'] ?? [] as $content) {
                $text .= (string) ($content['text'] ?? '');
            }
        }

        if ($isError === true) {
            $this->toolErr[$tool] = ($this->toolErr[$tool] ?? 0) + 1;
        } elseif ($isError === false) {
            $this->toolOk[$tool] = ($this->toolOk[$tool] ?? 0) + 1;
        }

        return [
            'status' => $response['status'],
            'ok' => $response['status'] === 200 && $isError === false,
            'isError' => $isError,
            'rpcError' => isset($json['error']),
            'text' => $text,
            'data' => json_decode($text, true),
        ];
    }

    private function renderProbe(string $name): array
    {
        return $this->callTool('render_template', $this->refArgs() + [
            'template' => self::PROBE_NAME,
            'output' => 'html',
        ], $name);
    }

    private function refArgs(): array
    {
        return ['kind' => 'pdf', 'type' => $this->opt['type'], 'identifiers' => $this->opt['identifiers']];
    }

    private static function probe(string $slot): string
    {
        return implode("\n", [
            '<html>',
            '<body>',
            '<p id="tm">{% exitbTm \'tm.zzMcpProbe\' default \'tm-missing\' %}</p>',
            '<p id="ctx">{{ system.zzMcpProbe|default(\'ctx-missing\') }}</p>',
            $slot,
            '</body>',
            '</html>',
        ]) . "\n";
    }

    private static function contentType(array $response): string
    {
        return strtolower(str_replace(' ', '', (string) ($response['headers']['content-type'] ?? '')));
    }

    /**
     * @return array<string, string> label => URL of the existing routes, all with config=<slug>
     */
    private function routeUrls(): array
    {
        $config = rawurlencode($this->opt['config']);
        $identifiers = rawurlencode($this->opt['identifiers']);
        $type = rawurlencode($this->opt['type']);

        return [
            '/' => $this->baseUrl . '/?config=' . $config,
            'Render-Route Referenzfall' => sprintf('%s/pdf/%s/%s/%s?config=%s', $this->baseUrl, $type, rawurlencode($this->opt['template']), $identifiers, $config),
            'Render-Route gibtesnicht.html' => sprintf('%s/pdf/%s/gibtesnicht.html/%s?config=%s', $this->baseUrl, $type, $identifiers, $config),
            'hasContext' => sprintf('%s/api/v1/cache/hasContext?kind=pdf&type=%s&identifiers=%s&config=%s', $this->baseUrl, $type, $identifiers, $config),
            'getTextModules' => sprintf('%s/api/v1/cache/getTextModules?advertisingMediumCode=&language=&config=%s', $this->baseUrl, $config),
        ];
    }

    // ------------------------------------------------------------------ helpers: files

    /**
     * @return array<string, string> file name => sha256 of all real config*.json (no zz*)
     */
    private function realConfigHashes(): array
    {
        $hashes = [];
        foreach (glob($this->configDir . '/config*.json') ?: [] as $file) {
            $name = basename($file);
            if (str_starts_with($name, 'config.zz')) {
                continue;
            }
            $hashes[$name] = (string) @hash_file('sha256', $file);
        }
        ksort($hashes);

        return $hashes;
    }

    /**
     * @return string[]
     */
    private function listBackups(): array
    {
        if (!is_dir($this->backupDir)) {
            return [];
        }
        $files = array_values(array_diff(@scandir($this->backupDir) ?: [], ['.', '..']));
        sort($files);

        return $files;
    }

    private function readOnlySet(): string
    {
        $template = $this->opt['template'];

        return str_contains($template, '|') ? explode('|', $template, 2)[0] : '';
    }

    private function readOnlyTemplatePath(): ?string
    {
        $set = $this->readOnlySet();
        if ($set === '') {
            return null;
        }

        return $set . '/slip/' . explode('|', $this->opt['template'], 2)[1];
    }

    /**
     * Hash over path and content of all files in slip/ and email/ of the read-only set.
     */
    private function readOnlySetHash(): string
    {
        $set = $this->readOnlySet();
        if ($set === '') {
            return '';
        }
        $lines = [];
        foreach (['slip', 'email'] as $sub) {
            $dir = $this->templatesDir . '/' . $set . '/' . $sub;
            if (!is_dir($dir)) {
                continue;
            }
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS));
            foreach ($iterator as $file) {
                if ($file->isFile()) {
                    $lines[] = substr($file->getPathname(), strlen($dir)) . ':' . @hash_file('sha256', $file->getPathname());
                }
            }
        }
        sort($lines);

        return hash('sha256', implode("\n", $lines));
    }

    private function integrityOk(string $copy, ?string &$detail): bool
    {
        $source = json_decode((string) @file_get_contents($this->registry->getConfigPath($this->opt['config'])), true);
        $target = json_decode((string) @file_get_contents($copy), true);
        if (!is_array($source) || !is_array($target)) {
            $detail = 'Configs nicht lesbar';

            return false;
        }

        $problems = [];
        $keys = array_unique(array_merge(array_keys($source), array_keys($target)));
        foreach ($keys as $key) {
            if ($key === 'mapping') {
                continue;
            }
            if (!array_key_exists($key, $source) || !array_key_exists($key, $target) || $source[$key] !== $target[$key]) {
                $problems[] = 'Schluessel ' . $key;
            }
        }
        foreach (['url', 'client', 'apiKey'] as $key) {
            if (!array_key_exists($key, $source)) {
                $problems[] = $key . ' fehlt in der Quelle';
            }
        }
        $mappingSource = is_array($source['mapping'] ?? null) ? $source['mapping'] : [];
        $mappingTarget = is_array($target['mapping'] ?? null) ? $target['mapping'] : [];
        foreach (array_unique(array_merge(array_keys($mappingSource), array_keys($mappingTarget))) as $key) {
            if (in_array($key, ['defaults', 'textModuleMapping'], true)) {
                continue;
            }
            if (($mappingSource[$key] ?? null) !== ($mappingTarget[$key] ?? null)) {
                $problems[] = 'mapping.' . $key;
            }
        }

        clearstatcache();
        $backups = array_merge(
            glob($this->backupDir . '/' . self::CHECK_SLUG . '-*.json') ?: [],
            glob($this->backupDir . '/' . self::LINK_SLUG . '-*.json') ?: []
        );
        $checkBackups = glob($this->backupDir . '/' . self::CHECK_SLUG . '-*.json') ?: [];
        foreach ($backups as $backup) {
            if ((fileperms($backup) & 0777) !== 0600) {
                $problems[] = 'Rechte ' . basename($backup);
            }
        }
        if (count($checkBackups) < 2) {
            $problems[] = 'zu wenige Backups ' . self::CHECK_SLUG;
        }

        $detail = sprintf(
            'url, client, apiKey und %d weitere Top-Level-Schluessel gleich der Quelle, %d Backups mit 0600',
            max(0, count($keys) - 4),
            count($backups)
        );
        if ($problems !== []) {
            $detail .= ' - Abweichungen: ' . implode(', ', $problems);
        }

        return $problems === [];
    }

    /**
     * Removes everything the check created. With $silent the result is not relevant (setup pre-clean).
     */
    public function cleanup(bool $silent = false): bool
    {
        if (!$silent) {
            $this->cleanedUp = true;
        }

        // cache namespaces of the test configs (JWT and data of the copies)
        foreach ([self::CHECK_SLUG, self::LINK_SLUG] as $slug) {
            try {
                if ($this->registry->exists($slug)) {
                    $this->quiet(fn () => ContainerFactory::createForSlug($slug, $this->registry)->get('cache_service')->clear());
                }
            } catch (\Throwable) {
            }
        }

        $link = $this->configDir . '/config.' . self::LINK_SLUG . '.json';
        $copy = $this->configDir . '/config.' . self::CHECK_SLUG . '.json';
        if (is_link($link) || file_exists($link)) {
            @unlink($link);
        }
        if (file_exists($copy) || is_link($copy)) {
            @unlink($copy);
        }
        foreach (glob($this->backupDir . '/zzmcp*') ?: [] as $backup) {
            @unlink($backup);
        }
        if (is_dir($this->backupDir) && !is_link($this->backupDir) && $this->listBackups() === []) {
            @rmdir($this->backupDir);
        }

        $set = $this->templatesDir . '/' . self::TEMPLATE_SET;
        if (is_link($set)) {
            @unlink($set);
        } elseif (is_dir($set)) {
            self::removeTree($set);
        }

        clearstatcache();

        return !file_exists($link) && !is_link($link) && !file_exists($copy)
            && (glob($this->backupDir . '/zzmcp*') ?: []) === [] && !file_exists($set);
    }

    private static function removeTree(string $dir): void
    {
        foreach (array_diff(@scandir($dir) ?: [], ['.', '..']) as $entry) {
            $path = $dir . '/' . $entry;
            if (is_dir($path) && !is_link($path)) {
                self::removeTree($path);
            } else {
                @unlink($path);
            }
        }
        @rmdir($dir);
    }

    // ------------------------------------------------------------------ helpers: secrets and scan

    /**
     * Reads url, host, url without scheme, client, apiKey and JWT of a slug into memory.
     *
     * @param string[] $skipped labels of skipped secrets
     * @return array<int, array{0: string, 1: string}>|null [label, value] pairs, null if the container fails
     */
    private function collectSecrets(string $slug, ?array &$skipped): ?array
    {
        $skipped = [];
        try {
            $container = $this->quiet(fn () => ContainerFactory::createForSlug($slug, $this->registry));
        } catch (\Throwable) {
            return null;
        }

        $values = [];
        $configService = $container->get('config_service');
        try {
            $url = (string) $this->quiet(fn () => $configService->getRestEndpoint());
            $values['url'] = $url;
            $host = parse_url($url, PHP_URL_HOST);
            $values['host'] = is_string($host) ? $host : '';
            $values['url-no-scheme'] = (string) preg_replace('#^[A-Za-z][A-Za-z0-9+.-]*://#', '', $url);
        } catch (\Throwable) {
            $skipped[] = 'url (nicht lesbar)';
        }
        try {
            $client = (string) $this->quiet(fn () => $configService->getClient());
            if ($client === $slug) {
                $skipped[] = 'client (gleich Slug)';
            } else {
                $values['client'] = $client;
            }
        } catch (\Throwable) {
            $skipped[] = 'client (nicht lesbar)';
        }
        try {
            $values['apiKey'] = (string) $this->quiet(fn () => $configService->getApiKey());
        } catch (\Throwable) {
            $skipped[] = 'apiKey (nicht lesbar)';
        }
        try {
            $values['jwt'] = (string) $this->quiet(fn () => $container->get('security_service')->getJwt(false));
        } catch (\Throwable) {
            $skipped[] = 'jwt (getJwt fehlgeschlagen)';
        }

        $secrets = [];
        foreach ($values as $label => $value) {
            if (strlen($value) < 3) {
                $skipped[] = $label . ' (unter 3 Zeichen)';
                continue;
            }
            $secrets[] = [$label, $value];
        }

        return $secrets;
    }

    /**
     * Runs $fn without any PHP warning or output reaching the console (messages may contain secrets).
     */
    private function quiet(callable $fn): mixed
    {
        $level = ob_get_level();
        ob_start();
        set_error_handler(static fn (): bool => true);
        try {
            return $fn();
        } finally {
            restore_error_handler();
            while (ob_get_level() > $level) {
                ob_end_clean();
            }
        }
    }

    /**
     * @param array<int, array{0: string, 1: string}> $secrets
     * @return string[] labels of the hits (never the values)
     */
    public static function scanText(string $text, array $secrets, bool $allowAbsolutePaths): array
    {
        $hits = [];
        foreach ($secrets as [$label, $value]) {
            if (strlen($value) < 3) {
                continue;
            }
            foreach (array_unique([$value, str_replace('/', '\/', $value)]) as $needle) {
                if (str_contains($text, $needle)) {
                    $hits[] = $label;
                    break;
                }
            }
        }

        if (preg_match('/eyJ[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+/', $text) === 1) {
            $hits[] = 'jwt-pattern';
        }
        if (preg_match('/Bearer\s+[A-Za-z0-9._~+\/=-]{3,}/i', $text) === 1) {
            $hits[] = 'bearer';
        }

        // PHP error output in HTML form ("<br />\n<b>Warning</b>: ..."); a plain <br /> is normal
        // text module or template content
        if (preg_match('#<br />\s*<b>#i', $text) === 1) {
            $hits[] = '<br />';
        }

        $literals = ['.mcp-backups', 'Stack trace', 'Warning:', 'Fatal error'];
        if (!$allowAbsolutePaths) {
            // mapping values may hold absolute container paths (e.g. system.imagePath below /var/www
            // or the identically mounted /home/<user>/workspace); elsewhere they are leaks
            array_push($literals, '/var/www', '\/var\/www', '/home/', '\/home\/');
        }
        foreach ($literals as $literal) {
            if (str_contains($text, $literal)) {
                $hits[] = $literal;
            }
        }

        return array_values(array_unique($hits));
    }
}

// ---------------------------------------------------------------------- CLI

$options = [
    'config' => 'bmo',
    'all-configs' => false,
    'template' => 'shopcartel|default_invoice.html',
    'type' => 'invoice',
    'identifiers' => '202636143468',
    'endpoint' => 'http://localhost/mcp',
    'scan' => null,
];

foreach (array_slice($argv, 1) as $argument) {
    if ($argument === '--all-configs') {
        $options['all-configs'] = true;
        continue;
    }
    if (preg_match('/^--(config|template|type|identifiers|endpoint|scan)=(.*)$/s', $argument, $match) && $match[2] !== '') {
        $options[$match[1]] = $match[2];
        continue;
    }
    fwrite(STDERR, "unknown or empty argument: " . $argument . "\n"
        . "usage: php tests/mcp/mcp-check.php [--config=bmo] [--all-configs] [--template=set|file.html] [--type=invoice]"
        . " [--identifiers=...] [--endpoint=http://localhost/mcp] [--scan=<file>]\n");
    exit(2);
}

if (!(new ConfigRegistry())->exists($options['config']) || str_starts_with($options['config'], 'zz')) {
    fwrite(STDERR, "unknown config slug\n");
    exit(2);
}

$check = new McpCheck($options);

if ($options['scan'] !== null) {
    exit($check->runScan($options['scan']));
}

exit($check->runFull());
