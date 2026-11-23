import type { Fund } from '../../types';

interface Props {
    fund: Fund;
}

export function FundDetailPanel({ fund }: Props) {
    return (
        <div className="space-y-6 text-sm">
            {fund.objective && (
                <section>
                    <h3 className="mb-2 font-semibold text-slate-900">Objective</h3>
                    <p className="text-slate-700">{fund.objective}</p>
                </section>
            )}

            {fund.asset_allocations.length > 0 && (
                <section>
                    <h3 className="mb-2 font-semibold text-slate-900">Asset allocation</h3>
                    <div className="space-y-1">
                        {fund.asset_allocations.map((allocation) => (
                            <div key={allocation.label} className="flex items-center gap-3">
                                <span className="w-20 text-slate-600">{allocation.label}</span>
                                <div className="flex-1 overflow-hidden rounded-full bg-slate-200">
                                    <div
                                        className="h-2 bg-slate-600"
                                        style={{ width: `${Math.min(100, Number(allocation.value))}%` }}
                                    />
                                </div>
                                <span className="w-16 text-right tabular-nums text-slate-700">
                                    {Number(allocation.value).toFixed(2)}%
                                </span>
                            </div>
                        ))}
                    </div>
                </section>
            )}

            {fund.holdings.length > 0 && (
                <section>
                    <h3 className="mb-2 font-semibold text-slate-900">Top holdings</h3>
                    <ol className="space-y-1">
                        {fund.holdings.map((holding) => (
                            <li key={holding.position} className="flex gap-3">
                                <span className="w-6 tabular-nums text-slate-400">{holding.position}.</span>
                                <span className="flex-1 text-slate-700">{holding.name}</span>
                                <span className="tabular-nums text-slate-700">
                                    {Number(holding.weighting).toFixed(2)}%
                                </span>
                            </li>
                        ))}
                    </ol>
                </section>
            )}

            {fund.documents.length > 0 && (
                <section>
                    <h3 className="mb-2 font-semibold text-slate-900">Documents</h3>
                    <ul className="space-y-1">
                        {fund.documents.map((doc) => (
                            <li key={`${doc.type}-${doc.external_id ?? doc.url}`}>
                                <a
                                    href={doc.url}
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    className="text-slate-700 underline hover:text-slate-900"
                                >
                                    {doc.type}
                                </a>
                            </li>
                        ))}
                    </ul>
                </section>
            )}
        </div>
    );
}
