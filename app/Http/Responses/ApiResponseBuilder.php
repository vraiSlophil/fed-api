<?php
// app/Http/Responses/ApiResponseBuilder.php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

final class ApiResponseBuilder
{
    private string $status = 'success';

    private ?string $message = null;

    private ?string $messageCode = null;
    private array $messageParams = [];

    private int $statusCode = 200;
    private mixed $data = null;
    private mixed $errors = null;
    private array $headers = [];
    private array $meta = [];

    public function status(string $status): self
    {
        $this->status = $status;
        return $this;
    }

    public function success(int $statusCode = 200, string $message = 'OK'): self
    {
        $this->status = 'success';
        $this->statusCode = $statusCode;
        $this->message($message);
        return $this;
    }

    public function error(int $statusCode = 400, string $message = 'Error'): self
    {
        $this->status = 'error';
        $this->statusCode = $statusCode;
        $this->message($message);
        return $this;
    }

    public function message(?string $message): self
    {
        $this->message = $message;
        return $this;
    }

    public function messageCode(?string $code, array $params = []): self
    {
        $this->messageCode = $code;
        $this->messageParams = $params;
        return $this;
    }

    public function code(int $statusCode): self
    {
        $this->statusCode = $statusCode;
        return $this;
    }

    public function data(mixed $data): self
    {
        $this->data = $data;
        return $this;
    }

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

        $this->data = [$key => $value];
        return $this;
    }

    public function errors(mixed $errors): self
    {
        $this->errors = $errors;
        return $this;
    }

    public function addError(mixed $error): self
    {
        if (is_null($this->errors)) {
            $this->errors = [];
        }

        if (!is_array($this->errors)) {
            $this->errors = [$this->errors];
        }

        $this->errors[] = $error;
        return $this;
    }

    public function meta(array $meta): self
    {
        $this->meta = array_merge($this->meta, $meta);
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

    public function build(): JsonResponse
    {
        $payload = [
            'status' => $this->status,
            'message' => $this->message,
            'data' => $this->data,
        ];

        if (!empty($this->messageCode)) {
            $payload['message_code'] = $this->messageCode;
        }

        if (!empty($this->messageParams)) {
            $payload['message_params'] = $this->messageParams;
        }

        if (!empty($this->errors)) {
            $payload['errors'] = $this->errors;
        }

        if (!empty($this->meta)) {
            $payload['meta'] = $this->meta;
        }

        return response()->json($payload, $this->statusCode, $this->headers);
    }

    public function json(): JsonResponse
    {
        return $this->build();
    }

    public function send(): Response|JsonResponse
    {
        return $this->build();
    }
}
