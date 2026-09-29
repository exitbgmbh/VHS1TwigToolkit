<?php

namespace App\Mcp\Tool\Render;

use App\Mcp\Render\RenderFileStore;
use App\Mcp\Render\SourceSnippet;
use App\Mcp\Tool\ToolContext;
use App\Mcp\Tool\ToolException;
use App\Mcp\Tool\ToolInterface;
use App\Mcp\Tool\ToolResult;
use App\Model\RenderError;
use App\Model\RenderRequest;
use App\Model\RenderResult;
use App\Service\RenderService;
use App\Service\TemplateStore;
use App\Service\TypesService;

/**
 * Renders a template via RenderService::tryRender. Emails return HTML; for PDFs the agent chooses
 * status, HTML before mPDF or a PDF file. Render errors are structured tool results with isError.
 */
class RenderTemplateTool implements ToolInterface
{
    /** HTML up to this size is returned inline, larger HTML is written to a file. */
    public const MAX_INLINE_HTML_BYTES = 100000;

    public const OUTPUT_STATUS = 'status';
    public const OUTPUT_HTML = 'html';
    public const OUTPUT_PDF = 'pdf';

    private const SNIPPET_RADIUS = 3;

    private ?RenderFileStore $fileStore;

    private ?TemplateStore $templateStore;

    /**
     * Both stores are optional for tests; ToolRegistry uses the defaults.
     */
    public function __construct(?RenderFileStore $fileStore = null, ?TemplateStore $templateStore = null)
    {
        $this->fileStore = $fileStore;
        $this->templateStore = $templateStore;
    }

    public function getName(): string
    {
        return 'render_template';
    }

    public function getDescription(): string
    {
        return 'Renders a template with real VHS1 data of the selected config. "template" is the name from '
            . 'list_templates (e.g. "shopcartel|default_invoice.html"). Outputs: "status" (PDF default) runs Twig '
            . 'and mPDF and reports only success, bytes and pages; "html" (email default) returns the HTML before '
            . 'mPDF; "pdf" writes the PDF file. Files (PDF, HTML over 100 KB) are stored below '
            . '/tmp/vhs1-mcp-renders/<session>/ and are readable on the host under the same path; they are deleted '
            . 'after 24 hours. On isError, error.file (relative to src/Templates) and error.line point to the spot '
            . 'to fix with read_template and write_template; error.snippet shows the lines around it.';
    }

    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'config' => [
                    'type' => 'string',
                    'description' => 'Config slug for this call only; defaults to the slug selected via select_config.',
                ],
                'kind' => [
                    'type' => 'string',
                    'enum' => ['pdf', 'email'],
                    'description' => 'Document kind.',
                ],
                'type' => [
                    'type' => 'string',
                    'description' => 'Document type from list_document_types, e.g. "invoice" (a "###" suffix is allowed).',
                ],
                'template' => [
                    'type' => 'string',
                    'description' => 'Template name from list_templates, e.g. "shopcartel|default_invoice.html".',
                ],
                'identifiers' => [
                    'type' => 'string',
                    'description' => 'Document identifier(s), e.g. the invoice number.',
                ],
                'productId' => [
                    'type' => 'string',
                    'description' => 'Optional product id for product-related documents (default "").',
                ],
                'advertisingMediumCode' => [
                    'type' => 'string',
                    'description' => 'Advertising medium code for the text modules (default "").',
                ],
                'language' => [
                    'type' => 'string',
                    'description' => 'Language code for the text modules (default "").',
                ],
                'format' => [
                    'type' => 'string',
                    'enum' => ['P', 'L'],
                    'description' => 'PDF orientation: P portrait, L landscape (default "P").',
                ],
                'size' => [
                    'type' => 'string',
                    'description' => 'PDF page size, e.g. "A4" (default "A4").',
                ],
                'forceReload' => [
                    'type' => 'boolean',
                    'description' => 'Bypass the toolkit cache and fetch fresh data from VHS1 (default false).',
                ],
                'output' => [
                    'type' => 'string',
                    'enum' => [self::OUTPUT_STATUS, self::OUTPUT_HTML, self::OUTPUT_PDF],
                    'description' => '"status", "html" or "pdf" (PDF-only). Default: "status" for kind pdf, '
                        . '"html" for kind email.',
                ],
            ],
            'required' => ['kind', 'type', 'template', 'identifiers'],
            'additionalProperties' => false,
        ];
    }

    public function call(array $arguments, ToolContext $context): ToolResult
    {
        $templateStore = $this->templateStore ?? new TemplateStore();

        try {
            return $this->_call($arguments, $context, $templateStore);
        } catch (ToolException $e) {
            throw new ToolException($templateStore->sanitizePaths($e->getMessage()), 0, $e);
        } catch (\Throwable $e) {
            throw new ToolException($templateStore->sanitizePaths(sprintf(
                '%s: %s',
                (new \ReflectionClass($e))->getShortName(),
                $e->getMessage()
            )), 0, $e);
        }
    }

    /**
     * Builds the tool result from a render result. Public for tests with a prepared RenderResult.
     *
     * @param array{config: string, kind: string, type: string, template: string, output: string} $meta
     * @throws ToolException
     */
    public function buildResult(
        RenderResult $result,
        array $meta,
        string $sessionId,
        RenderFileStore $fileStore,
        TemplateStore $templateStore
    ): ToolResult {
        $warnings = array_map(
            static fn (string $w): string => $templateStore->sanitizePaths($w),
            $result->getWarnings()
        );

        if (!$result->isOk()) {
            return ToolResult::errorData($this->_errorData($result, $meta, $warnings, $sessionId, $fileStore, $templateStore));
        }

        $html = (string) $result->getHtml();
        $data = $meta + [
            'ok' => true,
            'warnings' => $warnings,
            'htmlBytes' => strlen($html),
        ];

        $output = $meta['output'];
        $pdf = $result->getPdf();

        if ($output === self::OUTPUT_HTML) {
            if (strlen($html) <= self::MAX_INLINE_HTML_BYTES) {
                $data['html'] = $html;
            } else {
                $data['htmlFile'] = $fileStore->store($sessionId, $meta['template'], 'html', $html);
                $data['hint'] = 'html too large, read htmlFile';
            }
        } elseif ($output === self::OUTPUT_PDF) {
            if ($pdf === null) {
                throw new ToolException('render returned no PDF');
            }
            $data['pdfFile'] = $fileStore->store($sessionId, $meta['template'], 'pdf', $pdf);
            $data['pdfBytes'] = strlen($pdf);
            $data['pages'] = self::countPages($pdf);
        } elseif ($pdf !== null) {
            $data['pdfBytes'] = strlen($pdf);
            $data['pages'] = self::countPages($pdf);
        }

        return ToolResult::ok($data);
    }

    public static function countPages(string $pdf): int
    {
        return (int) preg_match_all('/\/Type\s*\/Page[^s]/', $pdf);
    }

    /**
     * @throws ToolException
     */
    private function _call(array $arguments, ToolContext $context, TemplateStore $templateStore): ToolResult
    {
        $kind = (string) $arguments['kind'];
        $typeArgument = (string) $arguments['type'];
        $template = (string) $arguments['template'];
        $identifiers = (string) $arguments['identifiers'];
        $output = (string) ($arguments['output']
            ?? ($kind === TypesService::TEMPLATE_TYPE_EMAIL_NAME ? self::OUTPUT_HTML : self::OUTPUT_STATUS));

        if ($output === self::OUTPUT_PDF && $kind !== TypesService::TEMPLATE_TYPE_DOCUMENT_NAME) {
            throw new ToolException('output "pdf" requires kind "pdf"');
        }

        $slug = $context->resolveSlug($arguments);

        $fileStore = $this->fileStore ?? new RenderFileStore();
        $fileStore->gc();

        $container = $context->getContainer($slug);
        /** @var TypesService $typesService */
        $typesService = $container->get('types_service');
        /** @var RenderService $renderService */
        $renderService = $container->get('render_service');

        // the render route receives the type without the "###" suffix
        $request = new RenderRequest(
            $kind,
            $typesService->getRealType($typeArgument),
            $template,
            $identifiers,
            (string) ($arguments['productId'] ?? ''),
            (string) ($arguments['advertisingMediumCode'] ?? ''),
            (string) ($arguments['language'] ?? ''),
            (string) ($arguments['format'] ?? 'P'),
            (string) ($arguments['size'] ?? 'A4'),
            (bool) ($arguments['forceReload'] ?? false)
        );

        $withPdf = $kind === TypesService::TEMPLATE_TYPE_DOCUMENT_NAME && $output !== self::OUTPUT_HTML;
        // strict: a missing template or include is an error instead of a red placeholder
        $result = $renderService->tryRender($request, $withPdf, true);

        $meta = [
            'config' => $slug,
            'kind' => $kind,
            'type' => $typeArgument,
            'template' => $template,
            'output' => $output,
        ];

        return $this->buildResult($result, $meta, $context->getSession()->getId(), $fileStore, $templateStore);
    }

    /**
     * @param string[] $warnings
     * @throws ToolException
     */
    private function _errorData(
        RenderResult $result,
        array $meta,
        array $warnings,
        string $sessionId,
        RenderFileStore $fileStore,
        TemplateStore $templateStore
    ): array {
        $error = $result->getError();
        if ($error === null) {
            $error = new RenderError(RenderError::STAGE_REQUEST, 'render failed without error details');
        }

        $errorData = $error->toArray();
        $errorData['message'] = $templateStore->sanitizePaths($errorData['message']);
        if (is_string($errorData['template'])) {
            $errorData['template'] = $templateStore->sanitizePaths($errorData['template']);
        }

        $file = $error->getFile();
        $line = $error->getLine();
        if ($file !== null && $line !== null) {
            try {
                $snippet = SourceSnippet::around(
                    $templateStore->read($file)['content'],
                    $line,
                    self::SNIPPET_RADIUS
                );
                if ($snippet !== '') {
                    $errorData['snippet'] = $snippet;
                }
            } catch (\Throwable) {
                // no snippet if the file cannot be read
            }
        }

        $data = $meta + [
            'ok' => false,
            'error' => $errorData,
            'warnings' => $warnings,
        ];

        $html = $result->getHtml();
        if ($error->getStage() === RenderError::STAGE_PDF && $html !== null) {
            $data['htmlFile'] = $fileStore->store($sessionId, $meta['template'], 'html', $html);
        }

        return $data;
    }
}
