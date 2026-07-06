/**
 * @param {Array<string|number|{ id?: string|number, value?: string|number, label?: string, name?: string, disabled?: boolean, inactive?: boolean, is_active?: boolean, badge?: string }>} options
 */
export function buildSimpleSelectOptions(options) {
    return [...(options ?? [])].map((option) => {
        if (option === null || option === undefined) {
            return { id: '', label: '—' };
        }

        if (typeof option === 'string' || typeof option === 'number') {
            return {
                id: option,
                label: String(option),
                searchText: String(option),
            };
        }

        const id = option.id ?? option.value ?? '';
        const label = option.label ?? option.name ?? String(id);
        const inactive = option.inactive ?? option.is_active === false;
        const badge = option.badge ?? (inactive ? '(Inactive)' : '');

        return {
            id,
            label,
            inactive,
            badge,
            disabled: Boolean(option.disabled),
            searchText: option.searchText ?? label,
        };
    });
}
