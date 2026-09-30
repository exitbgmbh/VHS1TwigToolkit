<?php

namespace App\Mcp\Render;

/**
 * Source excerpt around an error line, e.g. ">    7 | {{ foo( }}".
 */
class SourceSnippet
{
    /**
     * Lines line-radius .. line+radius as "%5d | <line>", the error line marked with ">" instead of a space.
     * Returns '' if the line is outside the content. Never throws.
     */
    public static function around(string $content, int $line, int $radius = 3): string
    {
        try {
            $lines = preg_split('/\r\n|\r|\n/', $content);
            if ($lines === false) {
                return '';
            }

            $count = count($lines);
            if ($line < 1 || $line > $count) {
                return '';
            }

            $radius = max(0, $radius);
            $from = max(1, $line - $radius);
            $to = min($count, $line + $radius);

            $out = [];
            for ($i = $from; $i <= $to; $i++) {
                $out[] = ($i === $line ? '>' : ' ') . sprintf('%5d | %s', $i, $lines[$i - 1]);
            }

            return implode("\n", $out);
        } catch (\Throwable) {
            return '';
        }
    }
}
