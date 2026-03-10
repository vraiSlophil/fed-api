<?php

namespace App\Http\Resources\Docs;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

abstract class ApiEnvelopeResource extends JsonResource
{
    protected string $message = 'Ok';

    protected ?string $messageCode = null;

    /**
     * Build the data payload placed under the standard envelope.
     */
    abstract protected function responseData(Request $request): mixed;

    /**
     * Build optional response metadata merged into the standard envelope.
     *
     * @return array<string, mixed>
     */
    protected function responseMeta(Request $request): array
    {
        return [];
    }

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $payload = [
            'status' => 'success',
            'message' => $this->message,
            'data' => $this->responseData($request),
        ];

        if ($this->messageCode !== null) {
            $payload['message_code'] = $this->messageCode;
        }

        $meta = $this->responseMeta($request);

        if ($meta !== []) {
            $payload['meta'] = $meta;
        }

        return $payload;
    }
}
