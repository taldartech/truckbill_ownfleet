/**
 * Table row CRUD icons — same as Drivers index (btn-edit blue, btn-danger red).
 */
export const CRUD_ACTION_PRESETS = {
    view: {
        btnClass: 'btn-view',
        icon: 'fas fa-eye',
        title: 'View details',
    },
    edit: {
        btnClass: 'btn-edit',
        icon: 'fas fa-pencil-alt',
        title: 'Edit',
    },
    delete: {
        btnClass: 'btn-danger',
        icon: 'fas fa-trash',
        title: 'Delete',
    },
    driver: {
        btnClass: 'btn-view',
        icon: 'fas fa-user-tie',
        title: 'Driver assignment',
    },
    documents: {
        btnClass: 'btn-primary',
        icon: 'fas fa-folder-open',
        title: 'Documents',
    },
    markPaid: {
        btnClass: 'btn-success',
        icon: 'fas fa-money-bill-wave',
        title: 'Mark paid',
    },
    advance: {
        btnClass: 'btn-warning',
        icon: 'fas fa-wallet',
        title: 'Advance wallet',
    },
    expenses: {
        btnClass: 'btn-warning',
        icon: 'fas fa-receipt',
        title: 'Trip expenses',
    },
    tyres: {
        btnClass: 'btn-outline-secondary',
        icon: 'bi bi-circle',
        title: 'Tyre positions',
    },
};

export function crudActionButtonClasses(action) {
    const preset = CRUD_ACTION_PRESETS[action];

    if (!preset) {
        return 'btn btn-secondary btn-sm';
    }

    return `btn btn-icon ${preset.btnClass} btn-sm`;
}
