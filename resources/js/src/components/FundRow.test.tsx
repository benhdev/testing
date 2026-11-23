import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it } from 'vitest';
import type { Fund } from '../../types';
import { FundRow } from './FundRow';

function makeFund(overrides: Partial<Fund> = {}): Fund {
    return {
        sedol: 'BYW8RV9',
        name: 'VT AJ Bell Cautious',
        market_code: 'FUND:BYW8RV9',
        category: 'growth',
        risk_label: 'cautious',
        sector_name: 'Mixed Investment',
        currency: 'GBX',
        last_price: '118.5610',
        last_price_date: '2024-06-04',
        ongoing_charge: '0.3100',
        analyst_rating: 4,
        analyst_rating_label: 'Neutral',
        srri: 4,
        objective: 'Long-term capital growth.',
        is_active: true,
        fetched_at: '2026-11-22T00:00:00Z',
        documents: [{ type: 'Factsheet', external_id: '1', url: 'https://doc.example' }],
        holdings: [{ position: 1, name: 'Vanguard Bond', weighting: '16.0041' }],
        asset_allocations: [{ label: 'Stock', value: '25.53738' }],
        ...overrides,
    };
}

function renderInTable(fund: Fund) {
    return render(
        <table>
            <tbody>
                <FundRow fund={fund} />
            </tbody>
        </table>,
    );
}

describe('FundRow', () => {
    it('renders the fund name and headline figures', () => {
        renderInTable(makeFund());

        expect(screen.getByText('VT AJ Bell Cautious')).toBeInTheDocument();
        expect(screen.getByText('BYW8RV9')).toBeInTheDocument();
        expect(screen.getByText('0.3100%')).toBeInTheDocument();
        expect(screen.getByText('4')).toBeInTheDocument();
    });

    it('expands to show the detail panel when clicked', async () => {
        renderInTable(makeFund());

        // Collapsed by default — objective is hidden.
        expect(screen.queryByText(/Long-term capital growth/)).not.toBeInTheDocument();

        await userEvent.click(screen.getByText('VT AJ Bell Cautious'));

        expect(screen.getByText(/Long-term capital growth/)).toBeInTheDocument();
        expect(screen.getByText('Vanguard Bond')).toBeInTheDocument();
    });

    it('falls back to the SEDOL when name is null (pre-refresh state)', () => {
        renderInTable(makeFund({ name: null }));

        // SEDOL appears twice when name is null: once as the fallback headline,
        // once as the usual subtitle. Both are intended.
        expect(screen.getAllByText('BYW8RV9')).toHaveLength(2);
    });
});
