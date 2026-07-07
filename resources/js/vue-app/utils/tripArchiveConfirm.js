function formatMoney(value) {
    return Number(value ?? 0).toLocaleString('en-IN', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });
}

const EXPENSE_TYPE_ICONS = {
    DIESEL: 'bi bi-fuel-pump',
    TOLL: 'bi bi-signpost-split',
    OTHER: 'bi bi-box-seam',
};

/**
 * @param {object} impact
 * @returns {object}
 */
export function buildTripArchiveConfirmViewModel(impact) {
    const trip = impact?.trip ?? {};
    const linked = impact?.linked_records ?? {};
    const warnings = impact?.warnings ?? {};
    const totalExpense = Number(linked.total_expense_amount ?? 0);
    const totalExpenseFormatted = formatMoney(totalExpense);

    const tripSummary = [
        { label: 'Trip No', value: trip.trip_number || '—' },
        { label: 'Truck', value: trip.truck_registration || '—' },
        { label: 'Driver', value: trip.driver_name || '—' },
        { label: 'Status', value: trip.status_label || trip.status || '—' },
    ];

    const linkedRecordRows = [];

    if ((linked.route_stops_count ?? 0) > 0) {
        linkedRecordRows.push({
            key: 'route-stops',
            label: 'Route Stops',
            value: linked.route_stops_count,
            icon: 'bi bi-geo-alt',
        });
    }

    if ((linked.expenses_count ?? 0) > 0) {
        linkedRecordRows.push({
            key: 'expenses',
            label: 'Expenses',
            value: linked.expenses_count,
            icon: 'bi bi-receipt',
        });
    }

    (linked.expenses_by_type ?? []).forEach((row) => {
        linkedRecordRows.push({
            key: `expense-type-${row.type}`,
            label: row.label,
            value: row.count,
            icon: EXPENSE_TYPE_ICONS[row.type] ?? 'bi bi-cash-stack',
        });
    });

    const totalExpenseRow = (linked.expenses_count ?? 0) > 0
        ? { label: 'Total Expense', value: `₹${totalExpenseFormatted}` }
        : null;

    return {
        title: 'Archive Trip',
        permanentNotice:
            'This trip will be removed from all active screens. This cannot be undone.',
        canArchive: Boolean(impact?.can_archive ?? impact?.can_delete),
        blockReason: impact?.block_reason ?? null,
        tripSummary,
        linkedRecordRows,
        totalExpenseRow,
        hasFinancialWarning: Boolean(warnings.has_financial_records),
        financialWarningMessage: warnings.has_financial_records
            ? 'Archiving this trip will remove operational financial records from active reports.'
            : null,
        confirmLabel: 'Archive Trip',
        archivingLabel: 'Archiving...',
    };
}
