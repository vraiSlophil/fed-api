<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

/**
 * Builder for standard JSON API responses.
 */
final class ApiResponseBuilder
{
    private string $status = 'success';
    private ?string $message = 'OK';
    private int $statusCode = 200;
    private mixed $data = null;
    private mixed $errors = null;
    private array $headers = [];
    private array $meta = [];

    /**
     * Sets the response status string ('success'|'error' or custom).
     */
    public function status(string $status): self
    {
        $this->status = $status;
        return $this;
    }

    /**
     * Shortcut to set status = success and HTTP status code.
     */
    public function success(int $statusCode = 200, string $message = 'OK'): self
    {
        $this->status = 'success';
        $this->statusCode = $statusCode;
        $this->message($message);
        return $this;
    }

    /**
     * Shortcut to set status = error and HTTP status code.
     */
    public function error(int $statusCode = 400, string $message = 'Une erreur est survenue'): self
    {
        $this->status = 'error';
        $this->statusCode = $statusCode;
        $this->message($message);
        return $this;
    }

    /**
     * Set or override the message.
     */
    public function message(string $message): self
    {
        $this->message = $message;
        return $this;
    }

    /**
     * Set HTTP status code.
     */
    public function code(int $statusCode): self
    {
        $this->statusCode = $statusCode;
        return $this;
    }

    /**
     * Replace the data payload.
     */
    public function data(mixed $data): self
    {
        $this->data = $data;
        return $this;
    }

    /**
     * Merge an array/object into the existing data when data is an array/object.
     * If data was null or scalar, it will be replaced.
     */
    public function mergeData(array|object $extra): self
    {
        if (is_array($this->data)) {
            $this->data = array_merge($this->data, (array)$extra);
        } elseif (is_object($this->data)) {
            $this->data = (object)array_merge((array)$this->data, (array)$extra);
        } else {
            $this->data = $extra;
        }
        return $this;
    }

    /**
     * Append a value under a specific key in the data payload (creates array/object as needed).
     */
    public function appendData(string $key, mixed $value): self
    {
        if (is_null($this->data)) {
            $this->data = [$key => $value];
            return $this;
        }

        if (is_array($this->data)) {
            $this->data[$key] = $value;
            return $this;
        }

        if (is_object($this->data)) {
            $this->data->{$key} = $value;
            return $this;
        }

        // scalar, turn into array
        $this->data = [$key => $value];
        return $this;
    }

    /**
     * Replace the errors payload.
     */
    public function errors(mixed $errors): self
    {
        $this->errors = $errors;
        return $this;
    }

    /**
     * Add a single error entry (useful for validation style).
     * If errors is null, make it an array.
     */
    public function addError(mixed $error): self
    {
        if (is_null($this->errors)) {
            $this->errors = [];
        }

        if (!is_array($this->errors)) {
            // normalize into array
            $this->errors = [$this->errors];
        }

        $this->errors[] = $error;
        return $this;
    }

    /**
     * Add meta information to the response (will appear inside top-level 'meta' key).
     */
    public function meta(array $meta): self
    {
        $this->meta = array_merge($this->meta, $meta);
        return $this;
    }

    /**
     * Add a header to the JSON response.
     */
    public function header(string $name, string $value): self
    {
        $this->headers[$name] = $value;
        return $this;
    }

    /**
     * Add multiple headers.
     */
    public function headers(array $headers): self
    {
        foreach ($headers as $k => $v) {
            $this->headers[$k] = $v;
        }
        return $this;
    }

    /**
     * Build the JsonResponse instance.
     */
    public function build(): JsonResponse
    {
        $payload = [
            'status' => $this->status,
            'message' => $this->message,
            'data' => $this->data,
        ];

        if (!empty($this->errors)) {
            $payload['errors'] = $this->errors;
        }

        if (!empty($this->meta)) {
            $payload['meta'] = $this->meta;
        }

        return response()->json($payload, $this->statusCode, $this->headers);
    }

    /**
     * Alias for build().
     */
    public function json(): JsonResponse
    {
        return $this->build();
    }

    /**
     * Send the built response immediately.
     * (Useful for controllers that want to return the response directly.)
     */
    public function send(): Response|JsonResponse
    {
        return $this->build();
    }
}
