<template>
    <div class="container mt-3 mb-4 tei">
        <div class="tei-top card mb-2">
            <div class="tei-top__bar">
                <button
                    type="button"
                    class="btn btn-outline-secondary btn-sm tei-top__back"
                    title="Back to trips"
                    aria-label="Back to trips"
                    @click="goBackToTrips"
                >
                    <i class="fas fa-arrow-left"></i>
                </button>
                <div class="tei-top__copy">
                    <div class="d-flex flex-wrap align-items-center gap-2">
                        <h1 class="tei-top__title">Bulk upload expenses</h1>
                        <span v-if="step > 1" class="tei-chip" :class="isFuel ? 'tei-chip--fuel' : 'tei-chip--fastag'">
                            <i :class="isFuel ? 'fas fa-gas-pump' : 'fas fa-road'" class="me-1"></i>
                            {{ isFuel ? 'Fuel' : 'FASTag' }}
                        </span>
                        <span v-if="statementDate && step > 1" class="tei-chip tei-chip--muted">
                            {{ formatStatementDate(statementDate) }}
                        </span>
                    </div>
                </div>
                <nav class="tei-stepper" aria-label="Import steps">
                    <ol class="tei-stepper__list">
                        <li
                            v-for="item in stepItems"
                            :key="item.n"
                            class="tei-stepper__item"
                            :class="{
                                'tei-stepper__item--active': step === item.n,
                                'tei-stepper__item--done': step > item.n,
                            }"
                        >
                            <span class="tei-stepper__dot" aria-hidden="true">
                                <i v-if="step > item.n" class="fas fa-check"></i>
                                <template v-else>{{ item.n }}</template>
                            </span>
                            <span class="tei-stepper__label d-none d-sm-inline">{{ item.label }}</span>
                        </li>
                    </ol>
                </nav>
            </div>
        </div>

        <div v-if="formError" class="alert alert-danger py-2 px-3 tei-alert mb-2">
            {{ formError }}
        </div>

        <!-- ─── Step 1: Upload ─── -->
        <div v-if="step === 1" class="card tei-panel">
            <div class="tei-panel__body">
                <div class="tei-upload-grid">
                    <div>
                        <label class="form-label mb-1">Type *</label>
                        <div class="tei-type-toggle" role="group" aria-label="Import type">
                            <button
                                type="button"
                                class="tei-type-toggle__btn"
                                :class="{ 'tei-type-toggle__btn--active': importType === 'fastag' }"
                                @click="importType = 'fastag'"
                            >
                                <i class="fas fa-road me-1"></i> FASTag
                            </button>
                            <button
                                type="button"
                                class="tei-type-toggle__btn"
                                :class="{ 'tei-type-toggle__btn--active': importType === 'fuel' }"
                                @click="importType = 'fuel'"
                            >
                                <i class="fas fa-gas-pump me-1"></i> Fuel
                            </button>
                        </div>
                    </div>
                    <div>
                        <label class="form-label mb-1" for="statement_date">Statement date *</label>
                        <input
                            id="statement_date"
                            v-model="statementDate"
                            type="date"
                            class="form-control form-control-sm"
                            required
                        />
                        <div class="form-text mt-1">IST calendar date only</div>
                    </div>
                    <div class="tei-upload-grid__file">
                        <label class="form-label mb-1">Statement file *</label>
                        <div
                            class="tei-dropzone"
                            :class="{ 'tei-dropzone--active': dragOver, 'tei-dropzone--filled': Boolean(file) }"
                            @dragenter.prevent="dragOver = true"
                            @dragover.prevent="dragOver = true"
                            @dragleave.prevent="dragOver = false"
                            @drop.prevent="onDrop"
                            @click="triggerFilePicker"
                        >
                            <input
                                id="statement_file"
                                ref="fileInputRef"
                                type="file"
                                class="d-none"
                                accept=".csv,.txt,.xlsx,.xls"
                                @change="onFileChange"
                            />
                            <i
                                class="tei-dropzone__icon"
                                :class="file ? 'fas fa-file-excel text-success' : 'fas fa-cloud-upload-alt'"
                            ></i>
                            <div class="tei-dropzone__copy">
                                <template v-if="file">
                                    <strong>{{ file.name }}</strong>
                                    <span>{{ formatFileSize(file.size) }} · replace</span>
                                </template>
                                <template v-else>
                                    <strong>Drop or click to browse</strong>
                                    <span>CSV / XLSX / XLS · max 5 MB</span>
                                </template>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="tei-panel__foot">
                <span class="text-muted small d-none d-md-inline">Map columns on the next step</span>
                <button
                    type="button"
                    class="btn btn-brand-primary btn-sm"
                    :disabled="analyzing || !canContinueUpload"
                    @click="runAnalyze"
                >
                    <span v-if="analyzing" class="spinner-border spinner-border-sm me-1"></span>
                    Continue
                    <i v-if="!analyzing" class="fas fa-arrow-right ms-1"></i>
                </button>
            </div>
        </div>

        <!-- ─── Step 2: Mapping ─── -->
        <div v-else-if="step === 2" class="card tei-panel">
            <div class="tei-panel__body">
                <div class="tei-map-top">
                    <div>
                        <div class="tei-map-top__title">Map columns</div>
                        <div class="tei-map-top__meta">
                            <span class="tei-map-count">{{ mappedCount }}/{{ fields.length }} mapped</span>
                            <span v-if="unmappedRequiredCount > 0" class="tei-map-need">
                                {{ unmappedRequiredCount }} required left
                            </span>
                        </div>
                    </div>
                </div>

                <div class="tei-map-field tei-map-field--header mb-3">
                    <label class="tei-map-field__label" for="header_row">Header row</label>
                    <select
                        id="header_row"
                        v-model.number="headerRow"
                        class="form-select form-select-sm"
                        :disabled="analyzing"
                        @change="reAnalyzeHeader"
                    >
                        <option
                            v-for="candidate in headerCandidates"
                            :key="candidate.index"
                            :value="candidate.index"
                        >
                            {{ candidate.label }}
                        </option>
                    </select>
                    <div class="tei-map-field__help">
                        Pick the row with column titles — mappings refresh automatically.
                    </div>
                </div>

                <div v-if="analyzing" class="text-center text-muted py-3">
                    <span class="spinner-border spinner-border-sm me-2"></span>
                    Re-analyzing…
                </div>

                <template v-else>
                    <div class="tei-map-group tei-map-group--when mb-3">
                        <div class="tei-map-group__head">
                            <span class="tei-map-group__tag">When</span>
                            <span class="tei-map-group__note">Use date + time, or one combined column</span>
                        </div>
                        <div class="tei-map-grid">
                            <div
                                v-for="field in dateFields"
                                :key="field.key"
                                class="tei-map-field"
                                :class="mapFieldClass(field)"
                            >
                                <label class="tei-map-field__label">
                                    {{ field.label }}
                                    <span v-if="isRequiredVisual(field)" class="text-danger">*</span>
                                    <span v-if="isAutoMapped(field.key)" class="tei-map-auto">auto</span>
                                </label>
                                <select v-model="columnMap[field.key]" class="form-select form-select-sm">
                                    <option :value="null">Not mapped</option>
                                    <option
                                        v-for="column in columns"
                                        :key="column.index"
                                        :value="column.index"
                                    >
                                        {{ column.label }}
                                    </option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="tei-map-group">
                        <div class="tei-map-group__head">
                            <span class="tei-map-group__tag tei-map-group__tag--muted">Details</span>
                        </div>
                        <div class="tei-map-grid">
                            <div
                                v-for="field in detailFields"
                                :key="field.key"
                                class="tei-map-field"
                                :class="mapFieldClass(field)"
                            >
                                <label class="tei-map-field__label">
                                    {{ field.label }}
                                    <span v-if="isRequiredVisual(field)" class="text-danger">*</span>
                                    <span v-if="isAutoMapped(field.key)" class="tei-map-auto">auto</span>
                                </label>
                                <select v-model="columnMap[field.key]" class="form-select form-select-sm">
                                    <option :value="null">Not mapped</option>
                                    <option
                                        v-for="column in columns"
                                        :key="column.index"
                                        :value="column.index"
                                    >
                                        {{ column.label }}
                                    </option>
                                </select>
                            </div>
                        </div>
                    </div>
                </template>
            </div>
            <div class="tei-panel__foot tei-panel__foot--end">
                <button type="button" class="btn btn-outline-secondary btn-sm" @click="step = 1">
                    Back
                </button>
                <button
                    type="button"
                    class="btn btn-brand-primary btn-sm"
                    :disabled="previewing || analyzing"
                    @click="runPreview"
                >
                    <span v-if="previewing" class="spinner-border spinner-border-sm me-1"></span>
                    Preview rows
                </button>
            </div>
        </div>

        <!-- ─── Step 3: Preview ─── -->
        <div v-else class="card tei-panel tei-panel--preview">
            <div class="tei-kpi">
                <div class="tei-kpi__item">
                    <span class="tei-kpi__value">{{ rows.length }}</span>
                    <span class="tei-kpi__label">Rows</span>
                </div>
                <div class="tei-kpi__item tei-kpi__item--ok">
                    <span class="tei-kpi__value">{{ matchedCount }}</span>
                    <span class="tei-kpi__label">Matched</span>
                </div>
                <div class="tei-kpi__item tei-kpi__item--warn">
                    <span class="tei-kpi__value">{{ attentionCount }}</span>
                    <span class="tei-kpi__label">Attention</span>
                </div>
                <div class="tei-kpi__item">
                    <span class="tei-kpi__value">{{ otherDates }}</span>
                    <span class="tei-kpi__label">Other dates</span>
                </div>
                <div class="tei-kpi__item">
                    <span class="tei-kpi__value">{{ skipped }}</span>
                    <span class="tei-kpi__label">Skipped</span>
                </div>
                <div class="tei-kpi__item tei-kpi__item--select">
                    <span class="tei-kpi__value">{{ selectedCount }}</span>
                    <span class="tei-kpi__label">Selected · ₹ {{ formatMoney(selectedTotal) }}</span>
                </div>
            </div>

            <div class="tei-panel__body">
                <div class="tei-filters mb-2">
                    <div class="tei-filters__search">
                        <i class="fas fa-search tei-filters__icon"></i>
                        <input
                            v-model="filterSearch"
                            type="search"
                            class="form-control form-control-sm"
                            placeholder="Search vehicle, pump, plaza, reference…"
                        />
                    </div>
                    <div class="tei-filters__status">
                        <button
                            v-for="opt in statusFilters"
                            :key="opt.value"
                            type="button"
                            class="tei-filter-pill"
                            :class="{ 'tei-filter-pill--active': filterStatus === opt.value }"
                            @click="filterStatus = opt.value"
                        >
                            {{ opt.label }}
                        </button>
                    </div>
                </div>

                <div class="table-responsive tei-table-wrap">
                    <table class="table table-sm table-hover align-middle tei-table mb-0">
                        <thead>
                            <tr>
                                <th class="tei-table__check">
                                    <input
                                        type="checkbox"
                                        :checked="allVisibleSelected"
                                        :indeterminate.prop="someVisibleSelected && !allVisibleSelected"
                                        title="Select all visible"
                                        @change="toggleSelectAllVisible($event.target.checked)"
                                    />
                                </th>
                                <th role="button" @click="toggleSort('transacted_at')">
                                    Date-time{{ sortMark('transacted_at') }}
                                </th>
                                <th role="button" @click="toggleSort('truck')">
                                    Vehicle{{ sortMark('truck') }}
                                </th>
                                <th role="button" @click="toggleSort(isFuel ? 'pump_name' : 'plaza')">
                                    {{ isFuel ? 'Pump' : 'Plaza' }}{{ sortMark(isFuel ? 'pump_name' : 'plaza') }}
                                </th>
                                <th v-if="isFuel" role="button" @click="toggleSort('liters')">
                                    Liters{{ sortMark('liters') }}
                                </th>
                                <th v-if="isFuel" role="button" @click="toggleSort('rate')">
                                    Rate{{ sortMark('rate') }}
                                </th>
                                <th role="button" @click="toggleSort('amount')">
                                    Amount{{ sortMark('amount') }}
                                </th>
                                <th role="button" @click="toggleSort('trip_id')">
                                    Trip{{ sortMark('trip_id') }}
                                </th>
                                <th>Remark</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="row in visibleRows"
                                :key="row.row_number"
                                :class="{
                                    'tei-row--issue': row.issue || row.already_imported,
                                    'tei-row--selected': row.include,
                                }"
                            >
                                <td>
                                    <input v-model="row.include" type="checkbox" />
                                </td>
                                <td class="text-nowrap tei-mono">{{ formatDateTime(row.transacted_at) }}</td>
                                <td>
                                    <span class="tei-vrn">{{ row.truck || row.vrn || '—' }}</span>
                                </td>
                                <td class="tei-place">{{ isFuel ? (row.pump_name || '—') : (row.plaza || '—') }}</td>
                                <td v-if="isFuel">
                                    <input
                                        v-model="row.liters"
                                        type="number"
                                        step="0.001"
                                        min="0"
                                        class="form-control form-control-sm tei-num"
                                        @change="recalcFuelRow(row)"
                                    />
                                </td>
                                <td v-if="isFuel">
                                    <input
                                        v-model="row.rate"
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        class="form-control form-control-sm tei-num"
                                        @change="recalcFuelRow(row)"
                                    />
                                </td>
                                <td>
                                    <input
                                        v-model="row.amount"
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        class="form-control form-control-sm tei-num tei-num--amount"
                                        :readonly="isFuel"
                                    />
                                </td>
                                <td>
                                    <select v-model="row.trip_id" class="form-select form-select-sm tei-trip-select">
                                        <option :value="null">Select trip…</option>
                                        <option
                                            v-for="trip in tripsForRow(row)"
                                            :key="trip.id"
                                            :value="trip.id"
                                        >
                                            {{ trip.label }}
                                        </option>
                                    </select>
                                </td>
                                <td>
                                    <input
                                        v-model="row.remark"
                                        type="text"
                                        class="form-control form-control-sm"
                                        maxlength="255"
                                        placeholder="Optional"
                                    />
                                </td>
                                <td>
                                    <span
                                        v-if="row.already_imported"
                                        class="tei-status tei-status--dup"
                                    >Already imported</span>
                                    <span
                                        v-else-if="row.issue"
                                        class="tei-status tei-status--issue"
                                        :title="row.issue"
                                    >{{ shortIssue(row.issue) }}</span>
                                    <span
                                        v-else-if="row.trip_id"
                                        class="tei-status tei-status--ok"
                                    >Matched</span>
                                    <span v-else class="tei-status tei-status--muted">—</span>
                                </td>
                            </tr>
                            <tr v-if="visibleRows.length === 0">
                                <td :colspan="isFuel ? 10 : 8" class="text-center text-muted py-5">
                                    <i class="fas fa-inbox fa-lg d-block mb-2 opacity-50"></i>
                                    No rows match the current filter.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="tei-panel__foot tei-panel__foot--sticky">
                <div class="d-flex flex-wrap gap-2">
                    <button type="button" class="btn btn-outline-secondary btn-sm" @click="step = 2">
                        Back
                    </button>
                    <button type="button" class="btn btn-outline-secondary btn-sm" @click="resetAll">
                        Upload another
                    </button>
                </div>
                <button
                    type="button"
                    class="btn btn-brand-primary btn-sm"
                    :disabled="submitting || selectedCount === 0"
                    @click="runStore"
                >
                    <span v-if="submitting" class="spinner-border spinner-border-sm me-1"></span>
                    <i v-else class="fas fa-check me-1"></i>
                    Submit {{ selectedCount }}
                    <span v-if="selectedCount > 0" class="tei-submit-total">
                        · ₹ {{ formatMoney(selectedTotal) }}
                    </span>
                </button>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, reactive, ref } from 'vue';
import { useRouter } from 'vue-router';
import {
    analyzeTripExpenseImport,
    previewTripExpenseImport,
    storeTripExpenseImport,
} from '../../api/tripExpenseImport';
import { useAppToast } from '../../composables/useAppToast';

const router = useRouter();
const toast = useAppToast();

const step = ref(1);
const importType = ref('fastag');
const statementDate = ref('');
const file = ref(null);
const fileInputRef = ref(null);
const dragOver = ref(false);
const analyzing = ref(false);
const previewing = ref(false);
const submitting = ref(false);
const formError = ref('');

const headerRow = ref(1);
const headerCandidates = ref([]);
const columns = ref([]);
const fields = ref([]);
const columnMap = reactive({});
const suggestedMap = ref({});

const rows = ref([]);
const trips = ref([]);
const skipped = ref(0);
const otherDates = ref(0);
const filterSearch = ref('');
const filterStatus = ref('all');
const sortKey = ref('transacted_at');
const sortDir = ref('asc');

const DATE_FIELD_KEYS = ['transaction_date', 'transaction_time', 'date_time'];

const stepItems = [
    { n: 1, label: 'Upload', hint: 'File & date' },
    { n: 2, label: 'Map columns', hint: 'Match fields' },
    { n: 3, label: 'Preview', hint: 'Confirm & save' },
];

const statusFilters = [
    { value: 'all', label: 'All' },
    { value: 'selected', label: 'Selected' },
    { value: 'matched', label: 'Matched' },
    { value: 'attention', label: 'Attention' },
    { value: 'already', label: 'Imported' },
];

const isFuel = computed(() => importType.value === 'fuel');
const canContinueUpload = computed(() => Boolean(statementDate.value && file.value));
const dateFields = computed(() => fields.value.filter((field) => DATE_FIELD_KEYS.includes(field.key)));
const detailFields = computed(() => fields.value.filter((field) => !DATE_FIELD_KEYS.includes(field.key)));
const mappedCount = computed(() => fields.value.filter((field) => isFieldMapped(field.key)).length);
const unmappedRequiredCount = computed(
    () => fields.value.filter((field) => isRequiredVisual(field) && !isFieldMapped(field.key)).length,
);

const matchedCount = computed(() => rows.value.filter((row) => row.trip_id && !row.issue).length);
const attentionCount = computed(() => rows.value.filter((row) => row.issue || row.already_imported).length);
const selectedCount = computed(() => rows.value.filter((row) => row.include).length);
const selectedTotal = computed(() =>
    rows.value
        .filter((row) => row.include)
        .reduce((sum, row) => sum + (Number(row.amount) || 0), 0),
);

const visibleRows = computed(() => {
    const q = filterSearch.value.trim().toLowerCase();
    let list = rows.value.filter((row) => {
        if (filterStatus.value === 'selected' && !row.include) return false;
        if (filterStatus.value === 'matched' && (!row.trip_id || row.issue)) return false;
        if (filterStatus.value === 'attention' && !(row.issue || row.already_imported)) return false;
        if (filterStatus.value === 'already' && !row.already_imported) return false;

        if (!q) return true;

        const hay = [
            row.truck,
            row.vrn,
            row.plaza,
            row.pump_name,
            row.reference,
            row.remark,
            row.issue,
        ]
            .filter(Boolean)
            .join(' ')
            .toLowerCase();

        return hay.includes(q);
    });

    const key = sortKey.value;
    const dir = sortDir.value === 'asc' ? 1 : -1;
    list = [...list].sort((a, b) => {
        const av = a[key] ?? '';
        const bv = b[key] ?? '';
        if (av === bv) return 0;
        return av > bv ? dir : -dir;
    });

    return list;
});

const allVisibleSelected = computed(
    () => visibleRows.value.length > 0 && visibleRows.value.every((row) => row.include),
);
const someVisibleSelected = computed(() => visibleRows.value.some((row) => row.include));

function goBackToTrips() {
    router.push({ name: 'trips.index' });
}

function triggerFilePicker() {
    fileInputRef.value?.click();
}

function onFileChange(event) {
    const chosen = event.target.files?.[0] ?? null;
    file.value = chosen;
    formError.value = '';
}

function onDrop(event) {
    dragOver.value = false;
    const dropped = event.dataTransfer?.files?.[0] ?? null;
    if (!dropped) return;
    file.value = dropped;
    formError.value = '';
}

function isFieldMapped(key) {
    const value = columnMap[key];
    return value !== null && value !== undefined && value !== '';
}

function isAutoMapped(key) {
    if (!isFieldMapped(key)) return false;
    const suggested = suggestedMap.value?.[key];
    if (suggested === null || suggested === undefined || suggested === '') return false;
    return Number(columnMap[key]) === Number(suggested);
}

function isRequiredVisual(field) {
    if (field.key === 'transaction_date' || field.key === 'date_time') {
        return !isFieldMapped('transaction_date') && !isFieldMapped('date_time');
    }
    return Boolean(field.required);
}

function mapFieldClass(field) {
    return {
        'tei-map-field--mapped': isFieldMapped(field.key),
        'tei-map-field--needed': isRequiredVisual(field) && !isFieldMapped(field.key),
    };
}

function resetColumnMap(suggested = {}) {
    Object.keys(columnMap).forEach((key) => delete columnMap[key]);
    fields.value.forEach((field) => {
        const value = suggested[field.key];
        columnMap[field.key] = value === undefined ? null : value;
    });
}

async function runAnalyze() {
    if (!canContinueUpload.value) return;

    analyzing.value = true;
    formError.value = '';

    try {
        const result = await analyzeTripExpenseImport({
            importType: importType.value,
            file: file.value,
            headerRow: null,
        });
        applyAnalyzeResult(result);
        step.value = 2;
    } catch (error) {
        formError.value = extractError(error) || 'Unable to analyze statement.';
    } finally {
        analyzing.value = false;
    }
}

async function reAnalyzeHeader() {
    if (!file.value) return;

    analyzing.value = true;
    formError.value = '';

    try {
        const result = await analyzeTripExpenseImport({
            importType: importType.value,
            file: file.value,
            headerRow: headerRow.value,
        });
        applyAnalyzeResult(result, false);
    } catch (error) {
        formError.value = extractError(error) || 'Unable to re-analyze header row.';
    } finally {
        analyzing.value = false;
    }
}

function applyAnalyzeResult(result, replaceHeader = true) {
    columns.value = result.columns ?? [];
    fields.value = result.fields ?? [];
    headerCandidates.value = result.header_candidates ?? [];
    suggestedMap.value = { ...(result.suggested ?? {}) };
    if (replaceHeader) {
        headerRow.value = result.header_row ?? 1;
    }
    resetColumnMap(result.suggested ?? {});
}

async function runPreview() {
    if (!file.value) return;

    previewing.value = true;
    formError.value = '';

    try {
        const mapPayload = {};
        Object.entries(columnMap).forEach(([key, value]) => {
            mapPayload[key] = value === null || value === '' ? null : Number(value);
        });

        const result = await previewTripExpenseImport({
            importType: importType.value,
            file: file.value,
            statementDate: statementDate.value,
            columnMap: mapPayload,
            headerRow: headerRow.value,
        });

        rows.value = (result.rows ?? []).map((row) => ({
            ...row,
            include: Boolean(row.include),
            trip_id: row.trip_id ?? null,
            remark: row.remark ?? '',
        }));
        trips.value = result.trips ?? [];
        skipped.value = result.skipped ?? 0;
        otherDates.value = result.other_dates ?? 0;
        step.value = 3;
    } catch (error) {
        formError.value = extractError(error) || 'Unable to preview rows.';
    } finally {
        previewing.value = false;
    }
}

function tripsForRow(row) {
    if (!row.truck_id) {
        return trips.value;
    }

    return trips.value.filter((trip) => !trip.vehicle_id || String(trip.vehicle_id) === String(row.truck_id));
}

function toggleSelectAllVisible(checked) {
    visibleRows.value.forEach((row) => {
        row.include = checked;
    });
}

function toggleSort(key) {
    if (sortKey.value === key) {
        sortDir.value = sortDir.value === 'asc' ? 'desc' : 'asc';
    } else {
        sortKey.value = key;
        sortDir.value = 'asc';
    }
}

function sortMark(key) {
    if (sortKey.value !== key) return '';
    return sortDir.value === 'asc' ? ' ↑' : ' ↓';
}

function recalcFuelRow(row) {
    const liters = Number(row.liters);
    const rate = Number(row.rate);
    if (liters > 0 && rate > 0) {
        row.amount = (liters * rate).toFixed(2);
    }
}

async function runStore() {
    submitting.value = true;
    formError.value = '';

    try {
        const result = await storeTripExpenseImport({
            importType: importType.value,
            rows: rows.value,
        });
        toast.success(result.message || `Imported ${result.created ?? 0} expense(s).`);
        router.push({ name: 'trips.index', query: { expenses_imported: '1' } });
    } catch (error) {
        formError.value = extractError(error) || 'Unable to import selected rows.';
    } finally {
        submitting.value = false;
    }
}

function resetAll() {
    step.value = 1;
    file.value = null;
    formError.value = '';
    rows.value = [];
    trips.value = [];
    skipped.value = 0;
    otherDates.value = 0;
    columns.value = [];
    fields.value = [];
    headerCandidates.value = [];
    suggestedMap.value = {};
    Object.keys(columnMap).forEach((key) => delete columnMap[key]);
    if (fileInputRef.value) {
        fileInputRef.value.value = '';
    }
}

function formatMoney(value) {
    const num = Number(value);
    if (Number.isNaN(num)) return '0.00';
    return num.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function formatDateTime(value) {
    if (!value) return '—';
    return String(value).replace('T', ' ');
}

function formatStatementDate(value) {
    if (!value) return '';
    const [y, m, d] = String(value).split('-');
    if (!y || !m || !d) return value;
    return `${d}/${m}/${y}`;
}

function formatFileSize(bytes) {
    if (!bytes && bytes !== 0) return '';
    if (bytes < 1024) return `${bytes} B`;
    if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`;
    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}

function shortIssue(issue) {
    const text = String(issue || '');
    if (text.length <= 28) return text;
    return `${text.slice(0, 26)}…`;
}

function extractError(error) {
    const data = error?.response?.data;
    if (!data) return error?.message ?? '';
    if (typeof data.message === 'string' && data.message) return data.message;
    if (data.errors) {
        const first = Object.values(data.errors).flat()[0];
        if (first) return String(first);
    }
    return '';
}
</script>

<style scoped>
.tei {
    --tei-radius: 0.5rem;
    --tei-border: var(--brand-border, #E2E8F0);
    --tei-muted: var(--brand-text-muted, #64748B);
    --tei-text: var(--brand-text-primary, #475569);
    --tei-primary: var(--brand-primary, #2FB36F);
    --tei-primary-soft: var(--brand-soft-primary, rgba(47, 179, 111, 0.1));
}

.tei-top {
    border: 1px solid var(--tei-border);
    border-radius: var(--tei-radius);
    background: #fff;
}

.tei-top__bar {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 0.65rem 1rem;
    padding: 0.55rem 0.75rem;
}

.tei-top__back {
    width: 1.85rem;
    height: 1.85rem;
    padding: 0;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.tei-top__copy {
    min-width: 0;
    flex: 1 1 auto;
}

.tei-top__title {
    font-size: 0.95rem;
    font-weight: 700;
    color: var(--tei-text);
    margin: 0;
}

.tei-chip {
    display: inline-flex;
    align-items: center;
    font-size: 0.7rem;
    font-weight: 600;
    padding: 0.1rem 0.45rem;
    border-radius: 999px;
    border: 1px solid var(--tei-border);
    background: #fff;
    color: var(--tei-text);
}

.tei-chip--fastag {
    background: rgba(45, 140, 255, 0.1);
    border-color: rgba(45, 140, 255, 0.25);
    color: #1F6FE0;
}

.tei-chip--fuel {
    background: var(--tei-primary-soft);
    border-color: rgba(47, 179, 111, 0.3);
    color: var(--brand-primary-dark, #23965C);
}

.tei-chip--muted {
    background: #F8FAFC;
}

.tei-stepper {
    margin-left: auto;
}

.tei-stepper__list {
    list-style: none;
    margin: 0;
    padding: 0;
    display: flex;
    align-items: center;
    gap: 0.65rem;
}

.tei-stepper__item {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    opacity: 0.45;
}

.tei-stepper__item--active,
.tei-stepper__item--done {
    opacity: 1;
}

.tei-stepper__dot {
    width: 1.35rem;
    height: 1.35rem;
    border-radius: 50%;
    background: #E2E8F0;
    color: #64748B;
    font-size: 0.65rem;
    font-weight: 800;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}

.tei-stepper__item--active .tei-stepper__dot {
    background: var(--tei-primary);
    color: #fff;
}

.tei-stepper__item--done .tei-stepper__dot {
    background: var(--brand-primary-dark, #23965C);
    color: #fff;
}

.tei-stepper__label {
    font-size: 0.75rem;
    font-weight: 650;
    color: var(--tei-text);
}

.tei-panel {
    border: 1px solid var(--tei-border);
    border-radius: var(--tei-radius);
    overflow: hidden;
}

.tei-panel__body {
    padding: 0.85rem 1rem;
}

.tei-panel__foot {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
    padding: 0.55rem 1rem;
    border-top: 1px solid var(--tei-border);
    background: #F8FAFC;
}

.tei-panel__foot--end {
    justify-content: flex-end;
}

.tei-panel__foot--sticky {
    position: sticky;
    bottom: 0;
    z-index: 5;
}

.tei-upload-grid {
    display: grid;
    grid-template-columns: minmax(10rem, 12rem) minmax(9rem, 11rem) minmax(0, 1fr);
    gap: 0.75rem;
    align-items: start;
}

@media (max-width: 768px) {
    .tei-upload-grid {
        grid-template-columns: 1fr 1fr;
    }
    .tei-upload-grid__file {
        grid-column: 1 / -1;
    }
}

.tei-type-toggle {
    display: inline-flex;
    width: 100%;
    border: 1px solid var(--tei-border);
    border-radius: 0.4rem;
    overflow: hidden;
    background: #fff;
}

.tei-type-toggle__btn {
    flex: 1;
    border: 0;
    background: transparent;
    padding: 0.35rem 0.5rem;
    font-size: 0.8rem;
    font-weight: 650;
    color: var(--tei-muted);
}

.tei-type-toggle__btn--active {
    background: var(--tei-primary);
    color: #fff;
}

.tei-dropzone {
    min-height: 2.35rem;
    border: 1px dashed #CBD5E1;
    border-radius: 0.4rem;
    background: #F8FAFC;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.35rem 0.6rem;
    cursor: pointer;
}

.tei-dropzone:hover,
.tei-dropzone--active {
    border-color: var(--tei-primary);
    background: var(--tei-primary-soft);
}

.tei-dropzone--filled {
    border-style: solid;
    background: #fff;
}

.tei-dropzone__icon {
    color: #94A3B8;
    width: 1rem;
    text-align: center;
    flex-shrink: 0;
}

.tei-dropzone__copy {
    display: flex;
    flex-direction: column;
    min-width: 0;
    line-height: 1.2;
    font-size: 0.75rem;
    color: var(--tei-muted);
}

.tei-dropzone__copy strong {
    color: var(--tei-text);
    font-size: 0.8rem;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.tei-map-top {
    display: flex;
    align-items: baseline;
    justify-content: space-between;
    gap: 0.75rem;
    margin-bottom: 0.85rem;
}

.tei-map-top__title {
    font-size: 0.92rem;
    font-weight: 700;
    color: var(--tei-text);
}

.tei-map-top__meta {
    display: flex;
    flex-wrap: wrap;
    gap: 0.4rem;
    margin-top: 0.15rem;
}

.tei-map-count,
.tei-map-need {
    font-size: 0.7rem;
    font-weight: 700;
    padding: 0.12rem 0.45rem;
    border-radius: 999px;
}

.tei-map-count {
    background: var(--tei-primary-soft);
    color: var(--brand-primary-dark, #23965C);
}

.tei-map-need {
    background: rgba(217, 119, 6, 0.12);
    color: var(--brand-warning-dark, #B45309);
}

.tei-map-group {
    border: 1px solid var(--tei-border);
    border-radius: 0.55rem;
    padding: 0.65rem 0.75rem 0.75rem;
    background: #fff;
}

.tei-map-group--when {
    background: linear-gradient(180deg, rgba(45, 140, 255, 0.05) 0%, #fff 70%);
    border-color: rgba(45, 140, 255, 0.18);
}

.tei-map-group__head {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 0.45rem 0.65rem;
    margin-bottom: 0.55rem;
}

.tei-map-group__tag {
    font-size: 0.68rem;
    font-weight: 800;
    letter-spacing: 0.04em;
    text-transform: uppercase;
    color: #1F6FE0;
    background: rgba(45, 140, 255, 0.12);
    padding: 0.12rem 0.45rem;
    border-radius: 999px;
}

.tei-map-group__tag--muted {
    color: var(--tei-muted);
    background: #F1F5F9;
}

.tei-map-group__note {
    font-size: 0.72rem;
    color: var(--tei-muted);
}

.tei-map-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 0.65rem 0.75rem;
}

@media (max-width: 900px) {
    .tei-map-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media (max-width: 560px) {
    .tei-map-grid {
        grid-template-columns: 1fr;
    }
}

.tei-map-field {
    min-width: 0;
}

.tei-map-field--header .form-select {
    width: 100%;
}

.tei-map-field__label {
    display: flex;
    align-items: center;
    gap: 0.3rem;
    margin-bottom: 0.28rem;
    font-size: 0.78rem;
    font-weight: 650;
    color: var(--tei-muted);
}

.tei-map-field__help {
    margin-top: 0.28rem;
    font-size: 0.72rem;
    color: #94A3B8;
}

.tei-map-field--mapped .form-select {
    border-color: rgba(47, 179, 111, 0.45);
    background: rgba(47, 179, 111, 0.03);
}

.tei-map-field--needed .form-select {
    border-color: rgba(217, 119, 6, 0.55);
    background: rgba(217, 119, 6, 0.04);
}

.tei-map-auto {
    margin-left: auto;
    font-size: 0.62rem;
    font-weight: 800;
    letter-spacing: 0.03em;
    text-transform: uppercase;
    color: var(--brand-primary-dark, #23965C);
    background: var(--tei-primary-soft);
    padding: 0.08rem 0.3rem;
    border-radius: 999px;
}

.tei-kpi {
    display: grid;
    grid-template-columns: repeat(6, minmax(0, 1fr));
    border-bottom: 1px solid var(--tei-border);
}

@media (max-width: 992px) {
    .tei-kpi {
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }
}

.tei-kpi__item {
    padding: 0.45rem 0.5rem;
    text-align: center;
    border-right: 1px solid var(--tei-border);
}

.tei-kpi__item:last-child {
    border-right: 0;
}

.tei-kpi__value {
    display: block;
    font-size: 0.95rem;
    font-weight: 800;
    color: var(--tei-text);
    line-height: 1.1;
}

.tei-kpi__label {
    display: block;
    margin-top: 0.1rem;
    font-size: 0.65rem;
    font-weight: 600;
    color: var(--tei-muted);
    text-transform: uppercase;
}

.tei-kpi__item--ok .tei-kpi__value { color: var(--brand-primary-dark, #23965C); }
.tei-kpi__item--warn .tei-kpi__value { color: var(--brand-warning, #D97706); }
.tei-kpi__item--select { background: var(--tei-primary-soft); }
.tei-kpi__item--select .tei-kpi__value { color: var(--brand-primary-dark, #23965C); }

.tei-filters {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
    align-items: center;
    justify-content: space-between;
}

.tei-filters__search {
    position: relative;
    flex: 1 1 14rem;
    min-width: 10rem;
}

.tei-filters__icon {
    position: absolute;
    left: 0.6rem;
    top: 50%;
    transform: translateY(-50%);
    color: #94A3B8;
    font-size: 0.75rem;
    pointer-events: none;
}

.tei-filters__search .form-control {
    padding-left: 1.8rem;
}

.tei-filters__status {
    display: flex;
    flex-wrap: wrap;
    gap: 0.25rem;
}

.tei-filter-pill {
    border: 1px solid var(--tei-border);
    background: #fff;
    color: var(--tei-muted);
    font-size: 0.7rem;
    font-weight: 600;
    padding: 0.18rem 0.5rem;
    border-radius: 999px;
}

.tei-filter-pill--active {
    background: var(--tei-primary);
    border-color: var(--tei-primary);
    color: #fff;
}

.tei-table-wrap {
    border: 1px solid var(--tei-border);
    border-radius: 0.4rem;
    overflow: auto;
    max-height: min(58vh, 560px);
}

.tei-table thead th {
    position: sticky;
    top: 0;
    z-index: 2;
    background: var(--brand-table-header-bg, #F1F5F9);
    font-size: 0.72rem;
    font-weight: 700;
    color: var(--tei-muted);
    white-space: nowrap;
}

.tei-table__check { width: 2rem; }
.tei-mono { font-variant-numeric: tabular-nums; font-size: 0.78rem; }
.tei-vrn { font-weight: 700; font-size: 0.8rem; }
.tei-place {
    max-width: 9rem;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.tei-num { min-width: 4.5rem; max-width: 5.5rem; }
.tei-num--amount { min-width: 5rem; }
.tei-trip-select { min-width: 12rem; }
.tei-row--issue { background: rgba(217, 119, 6, 0.06); }
.tei-row--selected td:first-child { box-shadow: inset 3px 0 0 var(--tei-primary); }

.tei-status {
    display: inline-flex;
    max-width: 8.5rem;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    font-size: 0.65rem;
    font-weight: 700;
    padding: 0.12rem 0.4rem;
    border-radius: 999px;
}

.tei-status--ok { background: var(--tei-primary-soft); color: var(--brand-primary-dark, #23965C); }
.tei-status--issue { background: rgba(217, 119, 6, 0.12); color: var(--brand-warning-dark, #B45309); }
.tei-status--dup { background: rgba(100, 116, 139, 0.12); color: #475569; }
.tei-status--muted { color: #94A3B8; }
.tei-submit-total { font-weight: 700; }
.tei-alert { border-radius: 0.4rem; font-size: 0.85rem; }
</style>
