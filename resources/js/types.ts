// Types mirror the shape returned by App\Http\Resources\FundResource. If
// you change the resource shape, update these too — there's no code
// generation, deliberately.

export type Category = 'growth' | 'responsible';
export type RiskLabel = 'cautious' | 'balanced' | 'adventurous' | 'responsible';

export interface Fund {
    sedol: string;
    name: string | null;
    market_code: string | null;
    category: Category;
    risk_label: RiskLabel | null;
    sector_name: string | null;
    currency: string | null;
    last_price: string | null;        // decimal cast → string on the server
    last_price_date: string | null;
    ongoing_charge: string | null;
    analyst_rating: number | null;
    analyst_rating_label: string | null;
    srri: number | null;
    objective: string | null;
    is_active: boolean;
    fetched_at: string | null;
    documents: FundDocument[];
    holdings: FundHolding[];
    asset_allocations: FundAssetAllocation[];
}

export interface FundDocument {
    type: string;
    external_id: string | null;
    url: string;
}

export interface FundHolding {
    position: number;
    name: string;
    weighting: string;
}

export interface FundAssetAllocation {
    label: string;
    value: string;
}

export interface Filters {
    category?: Category;
    risk?: RiskLabel;
    min_analyst_rating?: number;
    max_ongoing_charge?: number;
    sort?: string;
    per_page?: number;
}

export interface PaginatedResponse<T> {
    data: T[];
    meta: {
        current_page: number;
        last_page: number;
        total: number;
        per_page: number;
    };
    links: {
        first: string | null;
        last: string | null;
        prev: string | null;
        next: string | null;
    };
}
