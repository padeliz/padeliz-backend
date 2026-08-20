<?php

declare(strict_types=1);

namespace App\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

trait HasAdvancedPagination
{
    /**
     * Default pagination limit
     */
    protected int $defaultPerPage = 15;

    /**
     * Maximum pagination limit
     */
    protected int $maxPerPage = 100;

    /**
     * Apply advanced pagination with search, filtering, and sorting
     */
    protected function applyAdvancedPagination(
        Builder $query,
        Request $request,
        array $searchableFields = [],
        array $filterableFields = [],
        array $sortableFields = [],
        ?callable $extendSearch = null,
    ): Builder {
        // Apply search
        if ($request->has('q') && ! empty($request->get('q')) && ! empty($searchableFields)) {
            $searchTerm = $request->get('q');
            $query->where(function (Builder $q) use ($searchTerm, $searchableFields, $extendSearch) {
                foreach ($searchableFields as $field) {
                    $q->orWhere($field, 'LIKE', "%{$searchTerm}%");
                }
                if ($extendSearch !== null) {
                    $extendSearch($q, $searchTerm);
                }
            });
        }

        // Apply filters
        if ($request->has('filters') && is_array($request->get('filters'))) {
            $filters = $request->get('filters');
            foreach ($filters as $field => $value) {
                if (in_array($field, $filterableFields) && ! empty($value)) {
                    if (is_array($value)) {
                        $query->whereIn($field, $value);
                    } else {
                        $query->where($field, $value);
                    }
                }
            }
        }

        // Apply sorting
        if ($request->has('sort_by') && is_array($request->get('sort_by'))) {
            $sortFields = $request->get('sort_by');
            foreach ($sortFields as $field => $direction) {
                if (in_array($field, $sortableFields) && in_array(strtolower($direction), ['asc', 'desc'])) {
                    $query->orderBy($field, strtolower($direction));
                }
            }
        }

        return $query;
    }

    /**
     * Pull a filter that is not a real column and remove it so applyAdvancedPagination skips it.
     */
    protected function pullJoinFilter(Request $request, string $field): mixed
    {
        $filters = $request->get('filters');
        if (! is_array($filters) || ! array_key_exists($field, $filters)) {
            return null;
        }

        $value = $filters[$field];
        unset($filters[$field]);
        $request->merge(['filters' => $filters]);

        if ($value === '' || $value === null) {
            return null;
        }

        return $value;
    }

    /**
     * Get pagination parameters from request
     */
    protected function getPaginationParams(Request $request): array
    {
        $page = max(1, (int) $request->get('page', 1));
        $perPage = min($this->maxPerPage, max(1, (int) $request->get('per_page', $this->defaultPerPage)));

        return [
            'page' => $page,
            'per_page' => $perPage,
        ];
    }

    /**
     * Get the default per page value
     */
    protected function getDefaultPerPage(): int
    {
        return $this->defaultPerPage;
    }

    /**
     * Get the maximum per page value
     */
    protected function getMaxPerPage(): int
    {
        return $this->maxPerPage;
    }

    /**
     * Set the default per page value
     */
    protected function setDefaultPerPage(int $value): void
    {
        $this->defaultPerPage = $value;
    }

    /**
     * Set the maximum per page value
     */
    protected function setMaxPerPage(int $value): void
    {
        $this->maxPerPage = $value;
    }
}
