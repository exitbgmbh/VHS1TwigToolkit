<?php

namespace App\Service;

use App\Model\RenderError;
use App\Model\RenderOutput;
use App\Model\RenderRequest;
use App\Model\RenderResult;
use Mpdf\MpdfException;
use Psr\Cache\InvalidArgumentException;
use Twig\Error\Error as TwigError;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

/**
 * Renders a template without an HTTP request: JWT, context, text modules, Twig and (for PDFs) mPDF.
 * The methods loadRenderData, renderHtml, renderPdfFromHtml and render pass all exceptions through unchanged.
 * tryRender never throws and returns a structured RenderResult instead.
 */
class RenderService
{
    private const COLLECTED_ERROR_LEVELS = E_WARNING
        | E_NOTICE
        | E_USER_ERROR
        | E_USER_WARNING
        | E_USER_NOTICE
        | E_USER_DEPRECATED
        | E_DEPRECATED;

    /** @var SecurityService */
    private $_securityService;

    /** @var ContextService */
    private $_contextService;

    /** @var TextModulesService */
    private $_textModulesService;

    /** @var TwigService */
    private $_twigService;

    /** @var PdfService */
    private $_pdfService;

    /** @var SecretRedactor */
    private $_secretRedactor;

    public function __construct(
        SecurityService $securityService,
        ContextService $contextService,
        TextModulesService $textModulesService,
        TwigService $twigService,
        PdfService $pdfService,
        SecretRedactor $secretRedactor
    ) {
        $this->_securityService = $securityService;
        $this->_contextService = $contextService;
        $this->_textModulesService = $textModulesService;
        $this->_twigService = $twigService;
        $this->_pdfService = $pdfService;
        $this->_secretRedactor = $secretRedactor;
    }

    /**
     * @return array{context: array, textModules: array}
     * @throws InvalidArgumentException
     */
    public function loadRenderData(RenderRequest $r): array
    {
        $jwt = $this->_loadJwt($r);

        return [
            'context' => $this->_loadContext($r, $jwt),
            'textModules' => $this->_loadTextModules($r, $jwt),
        ];
    }

    /**
     * @throws InvalidArgumentException
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     */
    public function renderHtml(RenderRequest $r): string
    {
        $data = $this->loadRenderData($r);

        return $this->_renderTwig($r, $data['context'], $data['textModules']);
    }

    /**
     * @throws MpdfException
     */
    public function renderPdfFromHtml(string $html, RenderRequest $r): string
    {
        return $this->_pdfService->renderPdf($html, $r->getSize(), $r->getFormat());
    }

    /**
     * @throws InvalidArgumentException
     * @throws LoaderError
     * @throws MpdfException
     * @throws RuntimeError
     * @throws SyntaxError
     */
    public function render(RenderRequest $r): RenderOutput
    {
        $html = $this->renderHtml($r);
        $pdf = $r->isPdf() ? $this->renderPdfFromHtml($html, $r) : null;

        return new RenderOutput($html, $pdf);
    }

    /**
     * Non-throwing variant of render(). Every stage runs in its own try/catch, all messages and
     * warnings are passed through the SecretRedactor. PHP warnings during Twig and mPDF are collected.
     * With $strictTemplates a missing template or include is a Twig error instead of a red placeholder.
     */
    public function tryRender(RenderRequest $r, bool $withPdf = true, bool $strictTemplates = false): RenderResult
    {
        $warnings = [];

        try {
            $kind = $r->getKind();
            if ($kind !== TypesService::TEMPLATE_TYPE_DOCUMENT_NAME && $kind !== TypesService::TEMPLATE_TYPE_EMAIL_NAME) {
                return $this->_failure(RenderError::STAGE_REQUEST, sprintf(
                    'unsupported kind "%s", expected "%s" or "%s"',
                    $kind,
                    TypesService::TEMPLATE_TYPE_DOCUMENT_NAME,
                    TypesService::TEMPLATE_TYPE_EMAIL_NAME
                ), $warnings);
            }
        } catch (\Throwable $e) {
            return $this->_failure(RenderError::STAGE_REQUEST, $e->getMessage(), $warnings);
        }

        try {
            $jwt = $this->_loadJwt($r);
        } catch (\Throwable $e) {
            return $this->_failure(RenderError::STAGE_AUTH, $e->getMessage(), $warnings);
        }

        try {
            $context = $this->_loadContext($r, $jwt);
        } catch (\Throwable $e) {
            return $this->_failure(RenderError::STAGE_CONTEXT, $e->getMessage(), $warnings);
        }

        try {
            $textModules = $this->_loadTextModules($r, $jwt);
        } catch (\Throwable $e) {
            return $this->_failure(RenderError::STAGE_TEXT_MODULES, $e->getMessage(), $warnings);
        }

        $this->_startCollectingWarnings($warnings);
        try {
            $html = $this->_renderTwig($r, $context, $textModules, $strictTemplates);
        } catch (\Throwable $e) {
            return RenderResult::failure($this->_createTwigError($e, $r), $warnings);
        } finally {
            restore_error_handler();
        }

        $pdf = null;
        if ($r->isPdf() && $withPdf) {
            $this->_startCollectingWarnings($warnings);
            try {
                $pdf = $this->renderPdfFromHtml($html, $r);
            } catch (\Throwable $e) {
                return $this->_failure(RenderError::STAGE_PDF, $e->getMessage(), $warnings, $r->getTemplate(), $html);
            } finally {
                restore_error_handler();
            }
        }

        return RenderResult::success($html, $pdf, $warnings);
    }

    /**
     * @throws InvalidArgumentException
     */
    private function _loadJwt(RenderRequest $r): string
    {
        return $this->_securityService->getJwt($r->isForceReload());
    }

    /**
     * @throws InvalidArgumentException
     */
    private function _loadContext(RenderRequest $r, string $jwt): array
    {
        return $this->_contextService->getContext(
            $r->getKind(),
            $r->getType(),
            $r->getIdentifiers(),
            $r->getProductId(),
            $jwt,
            $r->isForceReload()
        );
    }

    /**
     * @throws InvalidArgumentException
     */
    private function _loadTextModules(RenderRequest $r, string $jwt): array
    {
        return $this->_textModulesService->getTextModules(
            $r->getTemplate(),
            $r->getAdvertisingMediumCode(),
            $r->getLanguage(),
            $jwt,
            $r->isForceReload()
        );
    }

    /**
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     */
    private function _renderTwig(
        RenderRequest $r,
        array $context,
        array $textModules,
        bool $strictTemplates = false
    ): string {
        return $this->_twigService->renderTemplate(
            $r->getTemplate(),
            $this->_restoreDateTimes($context),
            $textModules,
            $r->getKind(),
            $strictTemplates
        );
    }

    /**
     * The REST API delivers DateTime values JSON encoded as {date, timezone_type, timezone}, while VHS1
     * renders its templates with real DateTime objects (e.g. for the date filter), so they are restored here.
     */
    private function _restoreDateTimes(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = $this->_toDateTime($value) ?? $this->_restoreDateTimes($value);
            }
        }

        return $data;
    }

    private function _toDateTime(array $value): ?\DateTime
    {
        if (count($value) !== 3
            || !is_string($value['date'] ?? null)
            || !is_int($value['timezone_type'] ?? null)
            || !is_string($value['timezone'] ?? null)
        ) {
            return null;
        }

        try {
            return \DateTime::__set_state($value);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @param string[] $warnings
     */
    private function _failure(
        string $stage,
        string $message,
        array $warnings,
        ?string $template = null,
        ?string $html = null
    ): RenderResult {
        return RenderResult::failure(
            new RenderError($stage, $this->_secretRedactor->redact($message), $template),
            $warnings,
            $html
        );
    }

    private function _createTwigError(\Throwable $e, RenderRequest $r): RenderError
    {
        if (!$e instanceof TwigError) {
            return new RenderError(
                RenderError::STAGE_TWIG,
                $this->_secretRedactor->redact($e->getMessage()),
                $r->getTemplate()
            );
        }

        $message = $e->getRawMessage();
        $previous = $e->getPrevious();
        if ($previous !== null && !$previous instanceof TwigError) {
            $message .= sprintf(' (caused by: %s)', $previous->getMessage());
        }

        $line = $e->getTemplateLine();
        $source = $e->getSourceContext();

        return new RenderError(
            RenderError::STAGE_TWIG,
            $this->_secretRedactor->redact($message),
            $source !== null ? $source->getName() : $r->getTemplate(),
            $source !== null ? $this->_toTemplateRelativePath($source->getPath()) : null,
            $line >= 1 ? $line : null
        );
    }

    /**
     * Installs an error handler that collects PHP warnings/notices/deprecations as redacted strings
     * instead of printing them. Must be paired with restore_error_handler().
     *
     * @param string[] $warnings
     */
    private function _startCollectingWarnings(array &$warnings): void
    {
        set_error_handler(
            function (int $errno, string $errstr, string $errfile = '', int $errline = 0) use (&$warnings): bool {
                // respect the @ operator: suppressed messages are dropped silently
                if ((error_reporting() & $errno) === 0) {
                    return true;
                }

                // mPDF 8.1 on PHP 8.3: harmless, one per inline object, the value is still unserialized
                if (str_starts_with($errstr, 'unserialize(): Extra data') && str_contains($errfile, '/vendor/mpdf/')) {
                    return true;
                }

                $warnings[]= $this->_secretRedactor->redact(
                    sprintf('%s (%s:%d)', $errstr, basename($errfile), $errline)
                );

                return true;
            },
            self::COLLECTED_ERROR_LEVELS
        );
    }

    /**
     * Returns the path relative to src/Templates/ (symlinked template sets on level 1 and 2 included) or null.
     * Never returns absolute paths.
     */
    private function _toTemplateRelativePath(?string $path): ?string
    {
        if ($path === null || $path === '') {
            return null;
        }

        try {
            $relative = (new TemplateStore())->toRelativePath($path);
        } catch (\Throwable) {
            return null;
        }

        return $relative !== null && $relative !== '' ? $relative : null;
    }
}
