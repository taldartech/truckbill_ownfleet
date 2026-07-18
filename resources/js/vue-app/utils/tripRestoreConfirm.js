/**
 * @param {object} trip
 * @returns {object}
 */
export function buildTripRestoreConfirmViewModel(trip) {
    return {
        title: 'Restore Trip',
        notice: 'This trip will return to active lists and can be edited again.',
        confirmLabel: 'Restore Trip',
        restoringLabel: 'Restoring…',
        tripSummary: [
            { label: 'Trip No', value: trip?.trip_number || '—' },
            { label: 'Truck', value: trip?.vehicle?.registration_number || '—' },
            { label: 'Driver', value: trip?.driver?.name || '—' },
            { label: 'Archive Reason', value: trip?.archive_reason || '—' },
        ],
    };
}
