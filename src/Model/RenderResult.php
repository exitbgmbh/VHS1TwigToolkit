<?php

namespace App\Model;

/**
 * Result of a non-throwing render run: HTML (and optional PDF bytes) on success, a RenderError otherwise.
 * Collected PHP warnings are kept as a list of strings.
 */
final class RenderResult
{
    /** @var bool */
    private $ok;

    /** @var string|null */
    private $html;

    /** @var string|null */
    private $pdf;

    /** @var RenderError|null */
    private $error;

    /** @var string[] */
    private $warnings;

    /**
     * @param string[] $warnings
     */
    private function __construct(bool $ok, ?string $html, ?string $pdf, ?RenderError $error, array $warnings)
    {
        $this->ok = $ok;
        $this->html = $html;
        $this->pdf = $pdf;
        $this->error = $error;
        $this->warnings = array_values(array_map('strval', $warnings));
    }

    /**
     * @param string[] $warnings
     */
    public static function success(string $html, ?string $pdf, array $warnings = []): self
    {
        return new self(true, $html, $pdf, null, $warnings);
    }

    /**
     * @param string[] $warnings
     */
    public static function failure(RenderError $error, array $warnings = [], ?string $html = null): self
    {
        return new self(false, $html, null, $error, $warnings);
    }

    public function isOk(): bool
    {
        return $this->ok;
    }

    public function getHtml(): ?string
    {
        return $this->html;
    }

    public function getPdf(): ?string
    {
        return $this->pdf;
    }

    public function getError(): ?RenderError
    {
        return $this->error;
    }

    /**
     * @return string[]
     */
    public function getWarnings(): array
    {
        return $this->warnings;
    }

    /**
     * Never contains the PDF bytes, only their size.
     *
     * @return array{ok: bool, html: ?string, pdfSize: ?int, error: ?array, warnings: string[]}
     */
    public function toArray(): array
    {
        return [
            'ok' => $this->ok,
            'html' => $this->html,
            'pdfSize' => $this->pdf !== null ? strlen($this->pdf) : null,
            'error' => $this->error !== null ? $this->error->toArray() : null,
            'warnings' => $this->warnings,
        ];
    }
}
