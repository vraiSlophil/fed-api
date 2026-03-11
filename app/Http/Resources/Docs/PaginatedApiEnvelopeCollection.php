<?php

namespace App\Http\Resources\Docs;

use App\Support\Pagination\OffsetPagination;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Pagination\LengthAwarePaginator;

abstract class PaginatedApiEnvelopeCollection extends ResourceCollection
{
    protected string $message = 'Ok';

    protected ?string $messageCode = null;

    /**
     * Return extra metadata appended after the standard pagination block.
     *
     * @return array<string, mixed>
     */
    protected function additionalMeta(Request $request): array
    {
        return [];
    }

    /**
     * Override the default Laravel pagination block to match the API contract.
     *
     * @param  array<string, mixed>  $paginated
     * @param  array<string, mixed>  $default
     * @return array<string, mixed>
     */
    public function paginationInformation(Request $request, array $paginated, array $default): array
    {
        $meta = [];

        if ($this->resource instanceof LengthAwarePaginator) {
            $meta = OffsetPagination::meta($this->resource);
        }

        return [
            'meta' => [
                ...$meta,
                ...$this->additionalMeta($request),
            ],
        ];
    }

    /**
     * Add the standard envelope metadata to the paginated response.
     *
     * @return array<string, mixed>
     */
    public function with(Request $request): array
    {
        $payload = [
            'status' => 'success',
            'message' => $this->message,
        ];

        if ($this->messageCode !== null) {
            $payload['message_code'] = $this->messageCode;
        }

        return $payload;
    }
}
