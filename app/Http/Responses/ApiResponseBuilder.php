<?php

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

    /**
     * Set the status label used in the response payload.
     *
     * @param  string  $status  Requested status value applied by this method.
     * @return self Current instance for fluent chaining.
     */
    public function status(string $status): self
    {
        $this->status = $status;

        return $this;
    }

    /**
     * Configure the builder for a successful API response.
     *
     * @param  int  $statusCode  HTTP status code applied to the response.
     * @param  string  $message  Human-readable message returned to the client.
     * @return self Current instance for fluent chaining.
     */
    public function success(int $statusCode = 200, string $message = 'OK'): self
    {
        $this->status = 'success';
        $this->statusCode = $statusCode;
        $this->message($message);

        return $this;
    }

    /**
     * Configure the builder for an error API response.
     *
     * @param  int  $statusCode  HTTP status code applied to the response.
     * @param  string  $message  Human-readable message returned to the client.
     * @return self Current instance for fluent chaining.
     */
    public function error(int $statusCode = 400, string $message = 'Error'): self
    {
        $this->status = 'error';
        $this->statusCode = $statusCode;
        $this->message($message);

        return $this;
    }

    /**
     * Set the human-readable message returned to the client.
     *
     * @param  ?string  $message  Human-readable message returned to the client.
     * @return self Current instance for fluent chaining.
     */
    public function message(?string $message): self
    {
        $this->message = $message;

        return $this;
    }

    /**
     * Set the machine-readable message code and interpolation parameters.
     *
     * @param  ?string  $code  Machine-readable message code.
     * @param  array  $params  Template parameters used to format the message code.
     * @return self Current instance for fluent chaining.
     */
    public function messageCode(?string $code, array $params = []): self
    {
        $this->messageCode = $code;
        $this->messageParams = $params;

        return $this;
    }

    /**
     * Set the HTTP status code applied to the response.
     *
     * @param  int  $statusCode  HTTP status code applied to the response.
     * @return self Current instance for fluent chaining.
     */
    public function code(int $statusCode): self
    {
        $this->statusCode = $statusCode;

        return $this;
    }

    /**
     * Set the response payload data.
     *
     * @param  mixed  $data  Domain payload stored under the `data` envelope key.
     * @return self Current instance for fluent chaining.
     */
    public function data(mixed $data): self
    {
        $this->data = $data;

        return $this;
    }

    /**
     * Merge additional fields into the current response payload.
     *
     * @param  array|object  $extra  Additional fields merged into the response payload.
     * @return self Current instance for fluent chaining.
     */
    public function mergeData(array|object $extra): self
    {
        if (is_array($this->data)) {
            $this->data = array_merge($this->data, (array) $extra);
        } elseif (is_object($this->data)) {
            $this->data = (object) array_merge((array) $this->data, (array) $extra);
        } else {
            $this->data = $extra;
        }

        return $this;
    }

    /**
     * Append a key/value pair to the response payload.
     *
     * @param  string  $key  Payload key that will be assigned in the response data object.
     * @param  mixed  $value  Value assigned to the payload key.
     * @return self Current instance for fluent chaining.
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

        $this->data = [$key => $value];

        return $this;
    }

    /**
     * Set the structured error payload.
     *
     * @param  mixed  $errors  Structured error payload exposed in non-production environments.
     * @return self Current instance for fluent chaining.
     */
    public function errors(mixed $errors): self
    {
        $this->errors = $errors;

        return $this;
    }

    /**
     * Append a single error item to the error payload.
     *
     * @param  mixed  $error  Error item appended to the error payload.
     * @return self Current instance for fluent chaining.
     */
    public function addError(mixed $error): self
    {
        if (is_null($this->errors)) {
            $this->errors = [];
        }

        if (! is_array($this->errors)) {
            $this->errors = [$this->errors];
        }

        $this->errors[] = $error;

        return $this;
    }

    /**
     * Merge metadata fields into the response payload.
     *
     * @param  array  $meta  Additional metadata to include in the response.
     * @return self Current instance for fluent chaining.
     */
    public function meta(array $meta): self
    {
        $this->meta = array_merge($this->meta, $meta);

        return $this;
    }

    /**
     * Set a single HTTP header on the response.
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
     * Merge multiple HTTP headers into the response.
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
     * Build the final JSON response from the builder state.
     *
     * @return JsonResponse JSON API response using the standard envelope.
     */
    public function build(): JsonResponse
    {

        $isProd = app()->environment('production');

        $payload = [
            'status' => $this->status,
            'message' => ($this->statusCode >= 500 && $isProd) ? 'Server error' : $this->message,
            'data' => $this->data,
        ];

        if (! ($this->statusCode >= 500 && $isProd)) {
            if (! empty($this->messageCode)) {
                $payload['message_code'] = $this->messageCode;
            }

            if (! empty($this->messageParams)) {
                $payload['message_params'] = $this->messageParams;
            }
        }

        if (! $isProd && ! empty($this->errors)) {
            $payload['errors'] = $this->errors;
        }

        if (! empty($this->meta)) {
            $payload['meta'] = $this->meta;
        }

        return response()->json($payload, $this->statusCode, $this->headers);
    }

    /**
     * Alias of build() that returns a JSON response.
     *
     * @return JsonResponse JSON API response using the standard envelope.
     */
    public function json(): JsonResponse
    {
        return $this->build();
    }

    /**
     * Alias of build() kept for expressive controller responses.
     *
     * @return Response|JsonResponse JSON API response using the standard envelope.
     */
    public function send(): Response|JsonResponse
    {
        return $this->build();
    }
}
