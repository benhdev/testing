import { useCallback, useState } from 'react';
import type { Category, Filters, RiskLabel } from '../../types';

const CATEGORIES: readonly Category[] = ['growth', 'responsible'];
const RISKS: readonly RiskLabel[] = ['cautious', 'balanced', 'adventurous', 'responsible'];

/**
 * Parse the current URL search string into a typed Filters object.
 * Invalid / unknown values are silently dropped — the server will 422 them
 * anyway if forwarded, and this keeps the UI state sane across bookmarks.
 */
function parseFilters(search: string): Filters {
    const params = new URLSearchParams(search);
    const filters: Filters = {};

    const category = params.get('category');
    if (category && CATEGORIES.includes(category as Category)) {
        filters.category = category as Category;
    }

    const risk = params.get('risk');
    if (risk && RISKS.includes(risk as RiskLabel)) {
        filters.risk = risk as RiskLabel;
    }

    const minRating = params.get('min_analyst_rating');
    if (minRating && !Number.isNaN(Number(minRating))) {
        filters.min_analyst_rating = Number(minRating);
    }

    const maxCharge = params.get('max_ongoing_charge');
    if (maxCharge && !Number.isNaN(Number(maxCharge))) {
        filters.max_ongoing_charge = Number(maxCharge);
    }

    const sort = params.get('sort');
    if (sort) filters.sort = sort;

    return filters;
}

function serialiseFilters(filters: Filters): string {
    const params = new URLSearchParams();
    for (const [key, value] of Object.entries(filters)) {
        if (value === undefined || value === '') continue;
        params.set(key, String(value));
    }
    return params.toString();
}

/**
 * Read filter state from the URL and write it back on change. Using
 * `replaceState` rather than `pushState` means filter tweaks don't spam the
 * browser history; a single back-tap returns the user to wherever they came
 * from, not through every tweak they made on the page.
 */
export function useFilters(): [Filters, (next: Filters) => void] {
    const [filters, setFiltersState] = useState<Filters>(
        () => parseFilters(window.location.search),
    );

    const setFilters = useCallback((next: Filters) => {
        setFiltersState(next);
        const search = serialiseFilters(next);
        const url = search ? `${window.location.pathname}?${search}` : window.location.pathname;
        window.history.replaceState(null, '', url);
    }, []);

    return [filters, setFilters];
}
