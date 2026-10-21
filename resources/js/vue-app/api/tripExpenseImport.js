import api from '../api';

const base = '/app/api/trips/expense-import';

function unwrap(payload) {
    return payload?.data ?? payload;
}

export async function analyzeTripExpenseImport({ importType, file, headerRow = null }) {
    const form = new FormData();
    form.append('import_type', importType);
    form.append('file', file);
    if (headerRow !== null && headerRow !== '') {
        form.append('header_row', String(headerRow));
    }

    const { data } = await api.post(`${base}/analyze`, form, {
        headers: { 'Content-Type': 'multipart/form-data' },
    });

    return unwrap(data);
}

export async function previewTripExpenseImport({
    importType,
    file,
    statementDate,
    columnMap,
    headerRow = null,
}) {
    const form = new FormData();
    form.append('import_type', importType);
    form.append('file', file);
    form.append('statement_date', statementDate);
    form.append('column_map', JSON.stringify(columnMap ?? {}));
    if (headerRow !== null && headerRow !== '') {
        form.append('header_row', String(headerRow));
    }

    const { data } = await api.post(`${base}/preview`, form, {
        headers: { 'Content-Type': 'multipart/form-data' },
    });

    return unwrap(data);
}

export async function storeTripExpenseImport({ importType, rows }) {
    const { data } = await api.post(base, {
        import_type: importType,
        rows,
    });

    return unwrap(data);
}
