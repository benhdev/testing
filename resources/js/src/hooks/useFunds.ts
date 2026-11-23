import { useEffect, useState } from 'react';
import { listFunds } from '../api/funds';
import type { Filters, Fund, PaginatedResponse } from '../../types';

interface FundsState {
    data: PaginatedResponse<Fund> | null;
    loading: boolean;
    error: Error | null;
}

/**
 * Fetch funds whenever `filters` changes. The dep key is the JSON-stringified
 * filters so React only re-runs the effect when the actual filter values
 * change (not on every parent re-render that happens to pass a new object).
 */
export function useFunds(filters: Filters): FundsState {
    const [state, setState] = useState<FundsState>({
        data: null,
        loading: true,
        error: null,
    });

    const key = JSON.stringify(filters);

    useEffect(() => {
        let cancelled = false;
        setState((previous) => ({ ...previous, loading: true, error: null }));

        listFunds(filters)
            .then((data) => {
                if (!cancelled) setState({ data, loading: false, error: null });
            })
            .catch((error: Error) => {
                if (!cancelled) setState({ data: null, loading: false, error });
            });

        return () => {
            cancelled = true;
        };
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [key]);

    return state;
}
