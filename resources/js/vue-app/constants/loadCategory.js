export const LoadCategory = Object.freeze({
    EMPTY: 'empty',
    HALF: 'half',
    FULL: 'full',
});

export const LOAD_CATEGORY_META = Object.freeze({
    [LoadCategory.EMPTY]: { label: 'Empty', badge: 'bg-secondary' },
    [LoadCategory.HALF]: { label: 'Half Load', badge: 'bg-warning text-dark' },
    [LoadCategory.FULL]: { label: 'Full Load', badge: 'bg-success' },
});

export function loadCategoryMeta(category) {
    return LOAD_CATEGORY_META[category] ?? LOAD_CATEGORY_META[LoadCategory.EMPTY];
}
