<?php

namespace App\Model;

/**
 * Structured error of a render run: stage, message and (if known) template, file and line.
 * The file is relative to src/Templates/, never absolute.
 */
final class RenderError
{
    public const STAGE_REQUEST = 'request';
    public const STAGE_AUTH = 'auth';
    public const STAGE_CONTEXT = 'context';
    public const STAGE_TEXT_MODULES = 'textModules';
    public const STAGE_TWIG = 'twig';
    public const STAGE_PDF = 'pdf';

    /** @var string */
    private $stage;

    /** @var string */
    private $message;

    /** @var string|null */
    private $template;

    /** @var string|null */
    private $file;

    /** @var int|null */
    private $line;

    public function __construct(
        string $stage,
        string $message,
        ?string $template = null,
        ?string $file = null,
        ?int $line = null
    ) {
        $this->stage = $stage;
        $this->message = $message;
        $this->template = $template;
        $this->file = $file;
        $this->line = $line;
    }

    public function getStage(): string
    {
        return $this->stage;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function getTemplate(): ?string
    {
        return $this->template;
    }

    public function getFile(): ?string
    {
        return $this->file;
    }

    public function getLine(): ?int
    {
        return $this->line;
    }

    /**
     * @return array{stage: string, message: string, template: ?string, file: ?string, line: ?int}
     */
    public function toArray(): array
    {
        return [
            'stage' => $this->stage,
            'message' => $this->message,
            'template' => $this->template,
            'file' => $this->file,
            'line' => $this->line,
        ];
    }
}
