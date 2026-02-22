<?php

namespace App\Support\Pagination;

use Illuminate\Pagination\LengthAwarePaginator;

class OffsetPagination
{
    public const DEFAULT_PAGE = 1;

    public const DEFAULT_PER_PAGE = 15;

    public const MAX_PER_PAGE = 100;

    public static function queryRules(): array
    {
        return [
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:'.self::MAX_PER_PAGE],
            'offset' => ['sometimes', 'integer', 'min:0'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:'.self::MAX_PER_PAGE],
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array{page:int, per_page:int}
     */
    public static function extract(array $validated): array
    {
        $limit = isset($validated['limit']) ? (int) $validated['limit'] : null;
        $offset = isset($validated['offset']) ? (int) $validated['offset'] : null;

        if ($limit !== null) {
            $perPage = max(1, min(self::MAX_PER_PAGE, $limit));
            $page = $offset !== null ? (int) floor($offset / $perPage) + 1 : self::DEFAULT_PAGE;

            return [
                'page' => max(1, $page),
                'per_page' => $perPage,
            ];
        }

        return [
            'page' => max(1, (int) ($validated['page'] ?? self::DEFAULT_PAGE)),
            'per_page' => max(1, min(self::MAX_PER_PAGE, (int) ($validated['per_page'] ?? self::DEFAULT_PER_PAGE))),
        ];
    }

    /**
     * @return array<string, int|bool|null>
     */
    public static function meta(LengthAwarePaginator $paginator): array
    {
        return [
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
            'from' => $paginator->firstItem(),
            'to' => $paginator->lastItem(),
            'has_more' => $paginator->hasMorePages(),
        ];
    }
}
