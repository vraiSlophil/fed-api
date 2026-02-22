<?php

namespace App\Support\Pagination;

use Illuminate\Pagination\LengthAwarePaginator;

final class OffsetPagination
{
    public static function queryRules(): array
    {
        return [
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:'.self::maxPerPage()],
            'offset' => ['sometimes', 'integer', 'min:0'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:'.self::maxPerPage()],
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
            $perPage = min(max(1, $limit), self::maxPerPage());
            $page = $offset !== null ? (int) floor($offset / $perPage) + 1 : self::defaultPage();

            return [
                'page' => max(1, $page),
                'per_page' => $perPage,
            ];
        }

        $page = (int) ($validated['page'] ?? self::defaultPage());
        $perPage = (int) ($validated['per_page'] ?? self::defaultPerPage());

        return [
            'page' => max(1, $page),
            'per_page' => min(max(1, $perPage), self::maxPerPage()),
        ];
    }

    /**
     * @return array{current_page:int, per_page:int, total:int, last_page:int, from:int|null, to:int|null, has_next:bool}
     */
    public static function meta(LengthAwarePaginator $paginator): array
    {
        return [
            'current_page' => (int) $paginator->currentPage(),
            'per_page' => (int) $paginator->perPage(),
            'total' => (int) $paginator->total(),
            'last_page' => (int) $paginator->lastPage(),
            'from' => $paginator->firstItem() === null ? null : (int) $paginator->firstItem(),
            'to' => $paginator->lastItem() === null ? null : (int) $paginator->lastItem(),
            'has_next' => $paginator->hasMorePages(),
        ];
    }

    private static function defaultPage(): int
    {
        return max(1, (int) config('api_pagination.default_page', 1));
    }

    private static function defaultPerPage(): int
    {
        return min(max(1, (int) config('api_pagination.default_per_page', 15)), self::maxPerPage());
    }

    private static function maxPerPage(): int
    {
        return max(1, (int) config('api_pagination.max_per_page', 100));
    }
}
