export function EmptyState() {
    return (
        <div className="rounded-lg border border-dashed border-slate-300 bg-white py-12 text-center">
            <p className="text-sm font-medium text-slate-700">No funds match these filters.</p>
            <p className="mt-1 text-sm text-slate-500">
                Try broadening your criteria or clearing the filter.
            </p>
        </div>
    );
}
