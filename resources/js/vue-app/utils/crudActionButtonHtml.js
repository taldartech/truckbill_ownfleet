import { CRUD_ACTION_PRESETS, crudActionButtonClasses } from './crudActionPresets';

function escapeHtml(value) {
    return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

/**
 * DataTables: same icon buttons as <AppCrudActionButton />.
 *
 * @param {'edit'|'delete'|'driver'|'documents'|'markPaid'} action
 * @param {Record<string, string>} [data] data-* attributes (keys without "data-" prefix)
 */
export function crudActionButtonHtml({ action, className = '', data = {}, title = '' }) {
    const preset = CRUD_ACTION_PRESETS[action];

    if (!preset) {
        return '';
    }

    const classes = [crudActionButtonClasses(action), className].filter(Boolean).join(' ');
    const label = title || preset.title;

    const dataAttrs = Object.entries(data)
        .map(([key, value]) => `data-${key}="${escapeHtml(value)}"`)
        .join(' ');

    return `<button type="button" class="${classes}" title="${escapeHtml(label)}" aria-label="${escapeHtml(label)}" ${dataAttrs}><i class="${escapeHtml(preset.icon)}" aria-hidden="true"></i></button>`;
}

export function crudActionsRowHtml(buttonsHtml) {
    return `<div class="app-table-actions d-flex flex-wrap gap-1 justify-content-center">${buttonsHtml}</div>`;
}
