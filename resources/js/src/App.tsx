import { FilterSidebar } from './components/FilterSidebar';
import { FundList } from './components/FundList';
import { useFilters } from './hooks/useFilters';

export function App() {
    const [filters, setFilters] = useFilters();

    return (
        <div className="min-h-full">
            <header className="border-b border-slate-200 bg-white">
                <div className="mx-auto max-w-7xl px-6 py-4">
                    <h1 className="text-2xl font-semibold text-slate-900">Fund Directory</h1>
                    <p className="text-sm text-slate-500">
                        Browse and filter funds sourced from AJ Bell.
                    </p>
                </div>
            </header>

            <div className="mx-auto flex max-w-7xl gap-8 px-6 py-8">
                <FilterSidebar filters={filters} onChange={setFilters} />
                <main className="flex-1 min-w-0">
                    <FundList filters={filters} />
                </main>
            </div>
        </div>
    );
}
