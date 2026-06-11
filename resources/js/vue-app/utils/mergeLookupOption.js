/**
 * Keep the currently selected lookup value visible in edit forms
 * when it is no longer returned by active-only option lists.
 */
export function mergeLookupOption(options, selected, idKey = 'id') {
    if (!selected?.[idKey]) {
        return options;
    }

    const selectedId = String(selected[idKey]);

    if (options.some((option) => String(option[idKey]) === selectedId)) {
        return options;
    }

    return [...options, selected];
}
