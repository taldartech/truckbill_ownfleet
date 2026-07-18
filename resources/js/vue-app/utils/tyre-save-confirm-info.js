import { formatDate } from './datetime';
import { formatTyreStatus, formatTyreType } from './tyreDisplay';

function formatKm(value) {
    if (value === '' || value == null || !Number.isFinite(Number(value))) {
        return '—';
    }

    return `${Number(value).toLocaleString('en-IN')} km`;
}

function formatCurrency(value) {
    if (value === '' || value == null || !Number.isFinite(Number(value))) {
        return '—';
    }

    return `₹${Number(value).toLocaleString('en-IN')}`;
}

export function buildTyreSaveConfirmSummary({ editing, form, editingTyre }) {
    const isEdit = Boolean(editing);
    const installation = editingTyre?.active_installation;

    const items = [
        `Tyre number will be saved as ${form.serial_number || '—'}`,
        `Brand will be saved as ${form.brand || '—'}`,
        `Size will be saved as ${form.size || '—'}`,
        `Type will be saved as ${formatTyreType(form.tyre_type)}`,
        `Purchase date will be saved as ${formatDate(form.purchase_date) || '—'}`,
        `Expected life will be saved as ${formatKm(form.expected_life_km)}`,
        `Initial running KM will be saved as ${formatKm(form.current_km ?? 0)}`,
    ];

    if (form.purchase_cost !== '' && form.purchase_cost != null && Number(form.purchase_cost) > 0) {
        items.push(`Purchase cost will be saved as ${formatCurrency(form.purchase_cost)}`);
    }

    if (form.warranty_valid_until) {
        items.push(`Warranty valid until ${formatDate(form.warranty_valid_until)}`);
    }

    if (form.warranty_km !== '' && form.warranty_km != null) {
        items.push(`Warranty KM limit will be saved as ${formatKm(form.warranty_km)}`);
    }

    if (form.notes) {
        items.push('Notes will be saved');
    }

    if (isEdit) {
        items.push(`Tyre record will be updated (${formatTyreStatus(editingTyre?.status)})`);
    } else {
        items.push('Tyre will be added to stock as In Stock');
    }

    return {
        title: isEdit ? 'Confirm Update Tyre' : 'Confirm Add Tyre',
        message: isEdit ? 'Are you sure you want to update this tyre?' : 'Are you sure you want to add this tyre?',
        confirmLabel: isEdit ? 'Yes, Update Tyre' : 'Yes, Add Tyre',
        iconTone: 'primary',
        tyreSerial: form.serial_number || '—',
        truckNumber: installation?.vehicle_registration ?? '',
        position: installation?.position_label ?? '',
        showTruck: Boolean(installation?.vehicle_registration),
        items,
    };
}
