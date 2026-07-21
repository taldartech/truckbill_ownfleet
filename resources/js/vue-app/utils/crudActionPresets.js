/**
 * Shared listing / CRUD icon action presets.
 * Colors live in resources/css/crud-action-buttons.css.
 */
export const CRUD_ACTION_PRESETS = {
    view: {
        btnClass: 'btn-action-view',
        icon: 'fas fa-eye',
        title: 'View Details',
    },
    edit: {
        btnClass: 'btn-action-edit',
        icon: 'fas fa-pencil-alt',
        title: 'Edit',
    },
    delete: {
        btnClass: 'btn-action-delete',
        icon: 'fas fa-trash',
        title: 'Delete',
    },
    expenses: {
        btnClass: 'btn-action-expenses',
        icon: 'fas fa-wallet',
        title: 'Trip Expenses',
    },
    bilty: {
        btnClass: 'btn-action-bilty',
        icon: 'fas fa-print',
        title: 'Print Bilty',
    },
    invoice: {
        btnClass: 'btn-action-invoice',
        icon: 'fas fa-file-invoice',
        title: 'Generate Invoice',
    },
    documents: {
        btnClass: 'btn-action-documents',
        icon: 'fas fa-file-alt',
        title: 'Documents',
    },
    tyres: {
        btnClass: 'btn-action-tyres',
        icon: 'fas fa-dot-circle',
        title: 'Tyre Positions',
    },
    service: {
        btnClass: 'btn-action-service',
        icon: 'fas fa-wrench',
        title: 'Add Service Record',
    },
    driver: {
        btnClass: 'btn-action-driver',
        icon: 'fas fa-user-plus',
        title: 'Assign Driver',
    },
    fuel: {
        btnClass: 'btn-action-fuel',
        icon: 'fas fa-gas-pump',
        title: 'Fuel Entry',
    },
    complete: {
        btnClass: 'btn-action-complete',
        icon: 'fas fa-check-circle',
        title: 'Complete Trip',
    },
    cancel: {
        btnClass: 'btn-action-cancel',
        icon: 'fas fa-times-circle',
        title: 'Cancel Trip',
    },
    archive: {
        btnClass: 'btn-action-archive',
        icon: 'fas fa-archive',
        title: 'Archive',
    },
    download: {
        btnClass: 'btn-action-download',
        icon: 'fas fa-download',
        title: 'Download PDF',
    },
    history: {
        btnClass: 'btn-action-history',
        icon: 'fas fa-history',
        title: 'History',
    },
    restore: {
        btnClass: 'btn-action-restore',
        icon: 'fas fa-undo',
        title: 'Restore',
    },
    add: {
        btnClass: 'btn-action-add',
        icon: 'fas fa-wrench',
        title: 'Add Service Record',
    },
    advance: {
        btnClass: 'btn-action-advance',
        icon: 'fas fa-wallet',
        title: 'Advance Wallet',
    },
    markPaid: {
        btnClass: 'btn-action-mark-paid',
        icon: 'fas fa-money-bill-wave',
        title: 'Mark Paid',
    },
};

export function crudActionButtonClasses(action) {
    const preset = CRUD_ACTION_PRESETS[action];

    if (!preset) {
        return 'btn btn-secondary btn-sm';
    }

    return `btn btn-icon ${preset.btnClass} btn-sm`;
}

export function crudActionIcon(action) {
    return CRUD_ACTION_PRESETS[action]?.icon ?? 'fas fa-circle';
}
