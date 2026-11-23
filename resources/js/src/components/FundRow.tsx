import { useState } from 'react';
import type { Fund } from '../../types';
import { FundDetailPanel } from './FundDetailPanel';

interface Props {
    fund: Fund;
}

export function FundRow({ fund }: Props) {
    const [expanded, setExpanded] = useState(false);
    const toggle = () => setExpanded((value) => !value);

    return (
        <>
            <tr
                onClick={toggle}
                aria-expanded={expanded}
                className="cursor-pointer hover:bg-slate-50"
            >
                <td className="px-4 py-3">
                    <div className="font-medium text-slate-900">{fund.name ?? fund.sedol}</div>
                    <div className="text-xs text-slate-500">{fund.sedol}</div>
                </td>
                <td className="px-4 py-3 text-slate-700">
                    <span className="inline-flex items-center rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium">
                        {fund.category}
                        {fund.risk_label && ` · ${fund.risk_label}`}
                    </span>
                </td>
                <td className="px-4 py-3 text-slate-700">
                    {fund.ongoing_charge ? `${fund.ongoing_charge}%` : '—'}
                </td>
                <td className="px-4 py-3 text-slate-700">{fund.analyst_rating ?? '—'}</td>
                <td className="px-4 py-3 text-slate-700">
                    {fund.last_price ? `${fund.last_price} ${fund.currency ?? ''}` : '—'}
                </td>
            </tr>

            {expanded && (
                <tr>
                    <td colSpan={5} className="bg-slate-50 px-4 py-4">
                        <FundDetailPanel fund={fund} />
                    </td>
                </tr>
            )}
        </>
    );
}
