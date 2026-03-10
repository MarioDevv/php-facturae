<?php

declare(strict_types=1);

namespace PhpFacturae\Entities;

use RuntimeException;

final class Attachment
{
    public readonly string $data;
    public readonly string $mimeType;
    public readonly string $description;

    private function __construct(string $data, string $mimeType, string $description)
    {
        $this->data = $data;
        $this->mimeType = $mimeType;
        $this->description = $description;
    }

    /**
     * Attach a file from disk.
     */
    public static function fromFile(string $path, string $description, ?string $mimeType = null): self
    {
        if (!file_exists($path)) {
            throw new RuntimeException("Attachment file not found: {$path}");
        }

        $content = file_get_contents($path);

        if ($content === false) {
            throw new RuntimeException("Failed to read attachment file: {$path}");
        }

        return new self(
            base64_encode($content),
                $mimeType ?? mime_content_type($path) ?: 'application/octet-stream',
            $description,
        );
    }

    /**
     * Attach raw data.
     */
    public static function fromData(string $data, string $mimeType, string $description): self
    {
        return new self(base64_encode($data), $mimeType, $description);
    }
}
