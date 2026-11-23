<?php

namespace App\Filters;

use Illuminate\Contracts\Database\Eloquent\Builder;

/**
 * Applies the validated query parameters from ListFundsRequest to a Fund
 * query builder. Kept separate from the controller so the filter logic is
 * trivially unit-testable and reusable (e.g. if we later add an admin
 * endpoint with similar filters).
 *
 * All inputs are assumed already validated by ListFundsRequest — this class
 * does not re-validate.
 */
class FundFilter
{
    private const DEFAULT_SORT = 'name';

    /**
     * @param  array<string, mixed>  $validated
     */
    public function __construct(
        private readonly array $validated,
    ) {}

    public function apply(Builder $query): Builder
    {
        $query->where('is_active', true);

        if (isset($this->validated['category'])) {
            $query->where('category', $this->validated['category']);
        }

        if (isset($this->validated['risk'])) {
            $query->where('risk_label', $this->validated['risk']);
        }

        if (isset($this->validated['min_analyst_rating'])) {
            $query->where('analyst_rating', '>=', $this->validated['min_analyst_rating']);
        }

        if (isset($this->validated['max_ongoing_charge'])) {
            $query->where('ongoing_charge', '<=', $this->validated['max_ongoing_charge']);
        }

        $this->applySort($query);

        return $query;
    }

    /**
     * Sort syntax: bare column asc, `-column` desc.
     * E.g. `?sort=-analyst_rating` sorts by analyst_rating descending.
     */
    private function applySort(Builder $query): void
    {
        $sort = $this->validated['sort'] ?? self::DEFAULT_SORT;
        $direction = 'asc';

        if (str_starts_with($sort, '-')) {
            $direction = 'desc';
            $sort = substr($sort, 1);
        }

        $query->orderBy($sort, $direction);
    }
}
