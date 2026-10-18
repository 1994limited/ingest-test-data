<?php

declare(strict_types=1);

namespace Ingest\TestData;

use RuntimeException;

/**
 * Writes generated files and counts them.
 */
class Writer
{
    private int $files = 0;

    private int $bytes = 0;

    public function __construct(private string $root)
    {
    }

    /** Remove a generated top-level folder before writing it again. */
    public function clean(string $dir): void
    {
        $path = $this->root.'/'.$dir;
        if (! is_dir($path)) {
            return;
        }

        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($items as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($path);
    }

    public function json(string $path, mixed $data): void
    {
        $this->raw($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)."\n");
    }

    public function ndjson(string $path, array $items): void
    {
        $this->raw($path, implode("\n", array_map(fn ($i) => json_encode($i, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), $items))."\n");
    }

    public function xml(string $path, string $xml): void
    {
        // Every well-formed file is checked; broken ones go through raw().
        $previous = libxml_use_internal_errors(true);
        $doc = new \DOMDocument;
        if (! $doc->loadXML($xml, LIBXML_NONET)) {
            $error = libxml_get_last_error();
            throw new RuntimeException("Invalid XML for {$path}: ".trim($error ? $error->message : 'unknown'));
        }
        libxml_use_internal_errors($previous);

        $this->raw($path, $xml);
    }

    public function csv(string $path, array $rows, string $delimiter = ','): void
    {
        $this->raw($path, $this->csvString($rows, $delimiter));
    }

    public function csvString(array $rows, string $delimiter = ','): string
    {
        $handle = fopen('php://temp', 'r+');
        foreach ($rows as $row) {
            fputcsv($handle, $row, $delimiter, '"', '');
        }
        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return $csv;
    }

    /**
     * A small PNG: a flat colour from the seed text, with a label. Changing
     * the seed changes the colour, so the file's hash changes too.
     */
    public function image(string $path, int $width, int $height, string $label, string $seed): void
    {
        $hash = md5($seed);
        $img = imagecreatetruecolor($width, $height);
        $channel = fn (int $at) => intdiv((int) hexdec(substr($hash, $at, 2)), 2) + 64;
        $bg = imagecolorallocate($img, $channel(0), $channel(2), $channel(4));
        $fg = imagecolorallocate($img, 255, 255, 255);
        imagefilledrectangle($img, 0, 0, $width, $height, $bg);

        $y = 12;
        foreach (explode("\n", wordwrap($label, (int) floor(($width - 24) / 7), "\n", true)) as $line) {
            imagestring($img, 3, 12, $y, $line, $fg);
            $y += 16;
        }

        ob_start();
        imagepng($img, null, 9);
        $png = ob_get_clean();

        $this->raw($path, $png);
    }

    public function raw(string $path, string $contents): void
    {
        $full = $this->root.'/'.$path;
        if (! is_dir(dirname($full))) {
            mkdir(dirname($full), 0755, true);
        }
        file_put_contents($full, $contents);

        $this->files++;
        $this->bytes += strlen($contents);
    }

    public function report(): void
    {
        fwrite(STDOUT, sprintf("Wrote %d files (%.1f KB) to %s\n", $this->files, $this->bytes / 1024, $this->root));
    }
}
