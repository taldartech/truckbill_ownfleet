const VARIANT_SUFFIX = {
    primary: 'brand-primary',
    secondary: 'secondary',
    success: 'success',
    warning: 'warning',
    danger: 'danger',
    info: 'info',
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
