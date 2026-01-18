<?php

namespace App\Utils;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Pagination\LengthAwarePaginator;
use InvalidArgumentException;

class PaginationUtil
{
    public static function paginate($queryOrRelation, int $perPage, int $page): array
    {
        if ($queryOrRelation instanceof Relation) {
            $builder = $queryOrRelation->getQuery();
        } elseif ($queryOrRelation instanceof Builder) {
            $builder = $queryOrRelation;
        } else {
            throw new InvalidArgumentException('PaginationUtil::paginate attend un Builder ou une Relation Eloquent.');
        }

        $paginator = $builder->paginate($perPage, ['*'], 'page', $page);

        return [
            'items' => $paginator->items(),
            'pagination' => [
                'total' => $paginator->total(),
                'per_page' => (int) $paginator->perPage(),
                'current_page' => (int) $paginator->currentPage(),
                'last_page' => (int) $paginator->lastPage(),
                'from' => $paginator->firstItem() === null ? null : (int) $paginator->firstItem(),
                'to' => $paginator->lastItem() === null ? null : (int) $paginator->lastItem(),
            ],
        ];
    }
}
