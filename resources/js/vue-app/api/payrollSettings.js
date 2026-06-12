import api from '../api';

const payrollSettingsBase = '/app/api/payroll-settings';

export async function fetchPayrollSettings() {
    const { data } = await api.get(payrollSettingsBase);

    return data.settings ?? {};
}

export async function updatePayrollSettings(payload) {
    const { data } = await api.put(payrollSettingsBase, payload);

    return data.settings ?? {};
}
