import { useFunds } from '../hooks/useFunds';
import type { Filters } from '../../types';
import { EmptyState } from './EmptyState';
import { FundRow } from './FundRow';

interface Props {
    filters: Filters;
}

export function FundList({ filters }: Props) {
    const { data, loading, error } = useFunds(filters);

    if (loading && !data) {
        return <p className="text-sm text-slate-500">Loading funds…</p>;
    }

    if (error) {
        return (
            <p className="rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                Couldn't load funds — {error.message}
            </p>
        );
    }

    if (!data || data.data.length === 0) {
        return <EmptyState />;
    }

    return (
        <div className="overflow-hidden rounded-lg border border-slate-200 bg-white">
            <table className="w-full text-sm">
                <thead className="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                    <tr>
                        <th scope="col" className="px-4 py-3">Fund</th>
                        <th scope="col" className="px-4 py-3">Category</th>
                        <th scope="col" className="px-4 py-3">Ongoing charge</th>
                        <th scope="col" className="px-4 py-3">Analyst rating</th>
                        <th scope="col" className="px-4 py-3">Last price</th>
                    </tr>
                </thead>
                <tbody className="divide-y divide-slate-100">
                    {data.data.map((fund) => (
                        <FundRow key={fund.sedol} fund={fund} />
                    ))}
                </tbody>
            </table>

            <p className="border-t border-slate-200 bg-slate-50 px-4 py-2 text-xs text-slate-500">
                Showing {data.data.length} of {data.meta.total}
            </p>
        </div>
    );
}
