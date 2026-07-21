const VARIANT_SUFFIX = {
    primary: 'brand-primary',
    secondary: 'secondary',
    success: 'success',
    warning: 'warning',
    danger: 'danger',
    info: 'info',
    purple: 'purple',
    save: 'success',
    create: 'success',
    complete: 'success',
    generate: 'success',
    update: 'warning',
    continue: 'info',
    next: 'info',
    print: 'purple',
    pdf: 'purple',
    cancel: 'secondary',
    close: 'secondary',
    delete: 'danger',
    'cancel-trip': 'danger',
};

/**
 * Shared Bootstrap classes for AppButton and DataTables action HTML.
 */
export function appButtonClasses({ variant = 'primary', size = '', outline = false } = {}) {
    const base = outline ? 'btn btn-outline-' : 'btn btn-';
    const suffix = VARIANT_SUFFIX[variant] ?? VARIANT_SUFFIX.primary;
    const sizeClass = size === 'sm' ? ' btn-sm' : size === 'lg' ? ' btn-lg' : '';

    return `${base}${suffix}${sizeClass}`;
}
