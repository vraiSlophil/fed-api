<?php

namespace App\Http\Responses;

use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Builder for media/file responses.
 */
final class ApiMediaBuilder
{
    private ?string $path = null;

    private ?string $mimeType = null;

    private ?string $filename = null;

    private array $headers = [];

    private string $disposition = 'inline'; // 'inline' or 'attachment'

    public function path(string $path): self
    {
        $this->path = $path;

        return $this;
    }

    public function mimeType(string $mimeType): self
    {
        $this->mimeType = $mimeType;

        return $this;
    }

    public function filename(string $filename): self
    {
        $this->filename = $filename;

        return $this;
    }

    public function header(string $name, string $value): self
    {
        $this->headers[$name] = $value;

        return $this;
    }

    public function headers(array $headers): self
    {
        foreach ($headers as $k => $v) {
            $this->headers[$k] = $v;
        }

        return $this;
    }

    public function inline(): self
    {
        $this->disposition = 'inline';

        return $this;
    }

    public function attachment(): self
    {
        $this->disposition = 'attachment';

        return $this;
    }

    /**
     * Build the BinaryFileResponse or a 404 Response if the file doesn't exist.
     */
    public function build(): BinaryFileResponse|Response
    {
        if (empty($this->path) || ! file_exists($this->path)) {
            return response('', 404);
        }

        $headers = array_merge(
            ['Content-Type' => $this->mimeType ?: mime_content_type($this->path)],
            $this->headers
        );

        $response = response()->file($this->path, $headers);

        if ($this->filename) {
            $response->setContentDisposition($this->disposition, $this->filename);
        } else {
            // Keep disposition if requested even without a filename
            $response->headers->set('Content-Disposition', $this->disposition);
        }

        return $response;
    }

    /**
     * Convenience alias.
     */
    public function toResponse(): BinaryFileResponse|Response
    {
        return $this->build();
    }
}
