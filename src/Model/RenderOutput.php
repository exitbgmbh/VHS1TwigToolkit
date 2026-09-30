<?php

namespace App\Model;

/**
 * Result of a render run: rendered HTML and, for PDFs, the PDF bytes.
 */
final class RenderOutput
{
    /** @var string */
    private $html;

    /** @var string|null */
    private $pdf;

    public function __construct(string $html, ?string $pdf)
    {
        $this->html = $html;
        $this->pdf = $pdf;
    }

    public function getHtml(): string
    {
        return $this->html;
    }

    public function getPdf(): ?string
    {
        return $this->pdf;
    }
}
