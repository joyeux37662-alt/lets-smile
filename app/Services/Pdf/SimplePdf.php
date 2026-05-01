<?php

declare(strict_types=1);

namespace App\Services\Pdf;

final class SimplePdf
{
    private const WIDTH = 595.28;
    private const HEIGHT = 841.89;

    private array $pages = [];
    private int $currentPage = -1;

    public function __construct()
    {
        $this->addPage();
    }

    public function addPage(): void
    {
        $this->pages[] = '';
        $this->currentPage = count($this->pages) - 1;
    }

    public function width(): float
    {
        return self::WIDTH;
    }

    public function height(): float
    {
        return self::HEIGHT;
    }

    public function text(float $x, float $y, string $text, float $size = 10, bool $bold = false, string $color = '#0f172a', string $align = 'left'): void
    {
        $encoded = $this->encode($text);
        $width = $this->textWidth($text, $size);

        if ($align === 'right') {
            $x -= $width;
        } elseif ($align === 'center') {
            $x -= $width / 2;
        }

        [$r, $g, $b] = $this->rgb($color);
        $font = $bold ? 'F2' : 'F1';
        $this->append(sprintf(
            "%.3F %.3F %.3F rg BT /%s %.2F Tf 1 0 0 1 %.2F %.2F Tm (%s) Tj ET\n",
            $r,
            $g,
            $b,
            $font,
            $size,
            $x,
            self::HEIGHT - $y,
            $this->escape($encoded)
        ));
    }

    public function wrappedText(float $x, float $y, float $width, string $text, float $size = 10, bool $bold = false, string $color = '#334155', float $lineHeight = 14): float
    {
        $paragraphs = preg_split('/\R/', trim($text)) ?: [];

        foreach ($paragraphs as $paragraph) {
            $words = preg_split('/\s+/', trim((string) $paragraph)) ?: [];
            $line = '';

            foreach ($words as $word) {
                $candidate = $line === '' ? $word : $line . ' ' . $word;

                if ($this->textWidth($candidate, $size) > $width && $line !== '') {
                    $this->text($x, $y, $line, $size, $bold, $color);
                    $y += $lineHeight;
                    $line = $word;
                    continue;
                }

                $line = $candidate;
            }

            if ($line !== '') {
                $this->text($x, $y, $line, $size, $bold, $color);
                $y += $lineHeight;
            }
        }

        return $y;
    }

    public function line(float $x1, float $y1, float $x2, float $y2, string $color = '#e2e8f0', float $width = 1): void
    {
        [$r, $g, $b] = $this->rgb($color);
        $this->append(sprintf(
            "%.3F %.3F %.3F RG %.2F w %.2F %.2F m %.2F %.2F l S\n",
            $r,
            $g,
            $b,
            $width,
            $x1,
            self::HEIGHT - $y1,
            $x2,
            self::HEIGHT - $y2
        ));
    }

    public function rect(float $x, float $y, float $width, float $height, ?string $fill = null, ?string $stroke = '#e2e8f0'): void
    {
        $commands = '';

        if ($fill !== null) {
            [$r, $g, $b] = $this->rgb($fill);
            $commands .= sprintf("%.3F %.3F %.3F rg ", $r, $g, $b);
        }

        if ($stroke !== null) {
            [$r, $g, $b] = $this->rgb($stroke);
            $commands .= sprintf("%.3F %.3F %.3F RG ", $r, $g, $b);
        }

        $operator = $fill !== null && $stroke !== null ? 'B' : ($fill !== null ? 'f' : 'S');
        $this->append($commands . sprintf("%.2F %.2F %.2F %.2F re %s\n", $x, self::HEIGHT - $y - $height, $width, $height, $operator));
    }

    public function output(): string
    {
        $pageCount = count($this->pages);
        $objects = [
            1 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>',
            2 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>',
        ];

        $pagesId = 3 + ($pageCount * 2);
        $catalogId = $pagesId + 1;
        $kids = [];

        foreach ($this->pages as $index => $content) {
            $contentId = 3 + ($index * 2);
            $pageId = $contentId + 1;
            $kids[] = $pageId . ' 0 R';
            $objects[$contentId] = "<< /Length " . strlen($content) . " >>\nstream\n" . $content . "endstream";
            $objects[$pageId] = sprintf(
                '<< /Type /Page /Parent %d 0 R /MediaBox [0 0 %.2F %.2F] /Resources << /Font << /F1 1 0 R /F2 2 0 R >> >> /Contents %d 0 R >>',
                $pagesId,
                self::WIDTH,
                self::HEIGHT,
                $contentId
            );
        }

        $objects[$pagesId] = '<< /Type /Pages /Kids [' . implode(' ', $kids) . '] /Count ' . $pageCount . ' >>';
        $objects[$catalogId] = '<< /Type /Catalog /Pages ' . $pagesId . ' 0 R >>';
        ksort($objects);

        $pdf = "%PDF-1.4\n";
        $offsets = [0 => 0];

        foreach ($objects as $id => $object) {
            $offsets[$id] = strlen($pdf);
            $pdf .= $id . " 0 obj\n" . $object . "\nendobj\n";
        }

        $xref = strlen($pdf);
        $maxId = max(array_keys($objects));
        $pdf .= "xref\n0 " . ($maxId + 1) . "\n";
        $pdf .= "0000000000 65535 f \n";

        for ($id = 1; $id <= $maxId; $id++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$id] ?? 0);
        }

        $pdf .= "trailer\n<< /Size " . ($maxId + 1) . " /Root " . $catalogId . " 0 R >>\n";
        $pdf .= "startxref\n" . $xref . "\n%%EOF";

        return $pdf;
    }

    private function append(string $content): void
    {
        $this->pages[$this->currentPage] .= $content;
    }

    private function textWidth(string $text, float $size): float
    {
        return strlen($this->encode($text)) * $size * 0.48;
    }

    private function encode(string $text): string
    {
        $text = str_replace(["\r", "\n", "\t"], ' ', $text);

        if (function_exists('iconv')) {
            $converted = @iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $text);

            if ($converted !== false) {
                return $converted;
            }
        }

        return preg_replace('/[^\x20-\x7E]/', '?', $text) ?? '';
    }

    private function escape(string $text): string
    {
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text);
    }

    private function rgb(string $hex): array
    {
        $hex = ltrim($hex, '#');

        if (strlen($hex) !== 6) {
            $hex = '0f172a';
        }

        return [
            hexdec(substr($hex, 0, 2)) / 255,
            hexdec(substr($hex, 2, 2)) / 255,
            hexdec(substr($hex, 4, 2)) / 255,
        ];
    }
}
