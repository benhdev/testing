import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vitest';
import { FilterSidebar } from './FilterSidebar';

describe('FilterSidebar', () => {
    it('calls onChange with the selected category', async () => {
        const onChange = vi.fn();
        render(<FilterSidebar filters={{}} onChange={onChange} />);

        await userEvent.selectOptions(screen.getByLabelText(/category/i), 'responsible');

        expect(onChange).toHaveBeenCalledWith({ category: 'responsible' });
    });

    it('preserves existing filters when changing one', async () => {
        const onChange = vi.fn();
        render(
            <FilterSidebar
                filters={{ category: 'growth', min_analyst_rating: 4 }}
                onChange={onChange}
            />,
        );

        await userEvent.selectOptions(screen.getByLabelText(/risk level/i), 'balanced');

        expect(onChange).toHaveBeenCalledWith({
            category: 'growth',
            min_analyst_rating: 4,
            risk: 'balanced',
        });
    });

    it('clears all filters when the clear button is clicked', async () => {
        const onChange = vi.fn();
        render(
            <FilterSidebar
                filters={{ category: 'growth', min_analyst_rating: 4 }}
                onChange={onChange}
            />,
        );

        await userEvent.click(screen.getByRole('button', { name: /clear all/i }));

        expect(onChange).toHaveBeenCalledWith({});
    });
});
