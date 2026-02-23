<?php

namespace App\Http\Responses;

use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

final class ApiMediaBuilder
{
    private ?string $path = null;

    private ?string $mimeType = null;

    private ?string $filename = null;

    private array $headers = [];

    private string $disposition = 'inline';

    /**
     * Set the storage path of the media file to stream.
     *
     * @param  string  $path  Storage path of the target file.
     * @return self Current instance for fluent chaining.
     */
    public function path(string $path): self
    {
        $this->path = $path;

        return $this;
    }

    /**
     * Set the MIME type used for the media response.
     *
     * @param  string  $mimeType  MIME type associated with the media response.
     * @return self Current instance for fluent chaining.
     */
    public function mimeType(string $mimeType): self
    {
        $this->mimeType = $mimeType;

        return $this;
    }

    /**
     * Set the filename exposed to the client.
     *
     * @param  string  $filename  Filename exposed to the client.
     * @return self Current instance for fluent chaining.
     */
    public function filename(string $filename): self
    {
        $this->filename = $filename;

        return $this;
    }

    /**
     * Set a single HTTP header on the media response.
     *
     * @param  string  $name  HTTP header name.
     * @param  string  $value  HTTP header value associated with the provided header name.
     * @return self Current instance for fluent chaining.
     */
    public function header(string $name, string $value): self
    {
        $this->headers[$name] = $value;

        return $this;
    }

    /**
     * Merge multiple HTTP headers into the media response.
     *
     * @param  array  $headers  HTTP headers to include in the response.
     * @return self Current instance for fluent chaining.
     */
    public function headers(array $headers): self
    {
        foreach ($headers as $k => $v) {
            $this->headers[$k] = $v;
        }

        return $this;
    }

    /**
     * Configure the media response to be displayed inline.
     *
     * @return self Current instance for fluent chaining.
     */
    public function inline(): self
    {
        $this->disposition = 'inline';

        return $this;
    }

    /**
     * Configure the media response to be downloaded as an attachment.
     *
     * @return self Current instance for fluent chaining.
     */
    public function attachment(): self
    {
        $this->disposition = 'attachment';

        return $this;
    }

    /**
     * Build the final file response using the configured media options.
     *
     * @return BinaryFileResponse|Response BinaryFileResponse instance returned after successful execution.
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
            $response->headers->set('Content-Disposition', $this->disposition);
        }

        return $response;
    }

    /**
     * Alias of build() for fluent response conversion in controllers.
     *
     * @return BinaryFileResponse|Response BinaryFileResponse instance returned after successful execution.
     */
    public function toResponse(): BinaryFileResponse|Response
    {
        return $this->build();
    }
}
