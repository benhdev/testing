import type { Category, Filters, RiskLabel } from '../../types';

interface Props {
    filters: Filters;
    onChange: (next: Filters) => void;
}

const SORT_OPTIONS: Array<{ value: string; label: string }> = [
    { value: 'name', label: 'Name (A–Z)' },
    { value: '-name', label: 'Name (Z–A)' },
    { value: 'ongoing_charge', label: 'Ongoing charge ↑' },
    { value: '-ongoing_charge', label: 'Ongoing charge ↓' },
    { value: 'analyst_rating', label: 'Analyst rating ↑' },
    { value: '-analyst_rating', label: 'Analyst rating ↓' },
];

export function FilterSidebar({ filters, onChange }: Props) {
    const update = (patch: Partial<Filters>) => {
        const next: Filters = { ...filters, ...patch };
        // Strip undefined so the URL stays tidy.
        Object.keys(next).forEach((key) => {
            if (next[key as keyof Filters] === undefined) {
                delete next[key as keyof Filters];
            }
        });
        onChange(next);
    };

    return (
        <aside className="w-64 shrink-0 space-y-6 rounded-lg border border-slate-200 bg-white p-6">
            <h2 className="text-sm font-semibold uppercase tracking-wide text-slate-500">Filter</h2>

            <div className="space-y-1">
                <label htmlFor="filter-category" className="block text-sm font-medium text-slate-700">
                    Category
                </label>
                <select
                    id="filter-category"
                    value={filters.category ?? ''}
                    onChange={(e) => update({ category: (e.target.value || undefined) as Category | undefined })}
                    className="w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm"
                >
                    <option value="">All categories</option>
                    <option value="growth">Growth</option>
                    <option value="responsible">Responsible</option>
                </select>
            </div>

            <div className="space-y-1">
                <label htmlFor="filter-risk" className="block text-sm font-medium text-slate-700">
                    Risk level
                </label>
                <select
                    id="filter-risk"
                    value={filters.risk ?? ''}
                    onChange={(e) => update({ risk: (e.target.value || undefined) as RiskLabel | undefined })}
                    className="w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm"
                >
                    <option value="">All risk levels</option>
                    <option value="cautious">Cautious</option>
                    <option value="balanced">Balanced</option>
                    <option value="adventurous">Adventurous</option>
                    <option value="responsible">Responsible</option>
                </select>
            </div>

            <div className="space-y-1">
                <label htmlFor="filter-min-rating" className="block text-sm font-medium text-slate-700">
                    Minimum analyst rating
                </label>
                <input
                    id="filter-min-rating"
                    type="number"
                    min={1}
                    max={5}
                    value={filters.min_analyst_rating ?? ''}
                    onChange={(e) =>
                        update({
                            min_analyst_rating: e.target.value ? Number(e.target.value) : undefined,
                        })
                    }
                    className="w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm"
                />
            </div>

            <div className="space-y-1">
                <label htmlFor="filter-max-charge" className="block text-sm font-medium text-slate-700">
                    Max ongoing charge (%)
                </label>
                <input
                    id="filter-max-charge"
                    type="number"
                    step="0.01"
                    min={0}
                    value={filters.max_ongoing_charge ?? ''}
                    onChange={(e) =>
                        update({
                            max_ongoing_charge: e.target.value ? Number(e.target.value) : undefined,
                        })
                    }
                    className="w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm"
                />
            </div>

            <div className="space-y-1">
                <label htmlFor="filter-sort" className="block text-sm font-medium text-slate-700">
                    Sort by
                </label>
                <select
                    id="filter-sort"
                    value={filters.sort ?? ''}
                    onChange={(e) => update({ sort: e.target.value || undefined })}
                    className="w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm"
                >
                    <option value="">Default</option>
                    {SORT_OPTIONS.map((option) => (
                        <option key={option.value} value={option.value}>
                            {option.label}
                        </option>
                    ))}
                </select>
            </div>

            <button
                type="button"
                onClick={() => onChange({})}
                className="w-full rounded-md border border-slate-300 bg-slate-50 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-100"
            >
                Clear all
            </button>
        </aside>
    );
}
