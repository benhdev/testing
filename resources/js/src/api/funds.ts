import type { Filters, Fund, PaginatedResponse } from '../../types';

/**
 * Fetch a paginated list of funds. Filters are serialised into query params;
 * only values that are not undefined / '' are sent so defaults stay implicit.
 */
export async function listFunds(filters: Filters): Promise<PaginatedResponse<Fund>> {
    const params = new URLSearchParams();

    for (const [key, value] of Object.entries(filters)) {
        if (value === undefined || value === '') continue;
        params.set(key, String(value));
    }

    const query = params.toString();
    const url = query ? `/api/funds?${query}` : '/api/funds';

    const response = await fetch(url, {
        headers: { Accept: 'application/json' },
    });

    if (!response.ok) {
        throw new Error(`Failed to load funds (${response.status})`);
    }

    return response.json();
}
