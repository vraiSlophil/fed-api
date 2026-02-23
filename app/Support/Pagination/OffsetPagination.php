<?php

namespace App\Support\Pagination;

use Illuminate\Pagination\LengthAwarePaginator;

final class OffsetPagination
{
    /**
     * Return validation rules for offset-based pagination parameters.
     *
     * @return array Validation rules for offset-based pagination query parameters.
     */
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
     * Extract normalized values from the provided input.
     *
     * @param  array  $validated  Validated payload extracted from the request.
     * @return array Normalized values extracted from input data.
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
     * Merge metadata into the response payload.
     *
     * @param  LengthAwarePaginator  $paginator  Paginator instance containing the query result set.
     * @return array Pagination metadata derived from the paginator state.
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

    /**
     * Resolve the default page index from configuration.
     *
     * @return int Minimum page number allowed for paginated endpoints.
     */
    private static function defaultPage(): int
    {
        return max(1, (int) config('api_pagination.default_page', 1));
    }

    /**
     * Resolve the default page size from configuration.
     *
     * @return int Default number of records returned per page when no value is provided.
     */
    private static function defaultPerPage(): int
    {
        return min(max(1, (int) config('api_pagination.default_per_page', 15)), self::maxPerPage());
    }

    /**
     * Resolve the maximum allowed page size from configuration.
     *
     * @return int Upper bound accepted for `per_page`/`limit` query parameters.
     */
    private static function maxPerPage(): int
    {
        return max(1, (int) config('api_pagination.max_per_page', 100));
    }
}
