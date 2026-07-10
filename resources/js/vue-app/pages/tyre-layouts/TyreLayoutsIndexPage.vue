<template>
    <div class="container mt-4 app-index-page app-index-page--standard">
        <div v-if="needsCompanySelection" class="alert alert-warning">
            Select a company and click <strong>Enter</strong> before managing tyre layouts.
        </div>

        <AppIndexListCard title="Tyre Layouts" :loading="loading" loading-text="Loading layouts…">
            <template #toolbar>
                <AppIndexHeaderButton
                    icon="filter"
                    :aria-expanded="filtersOpen"
                    aria-controls="tyre-layouts-filters-panel"
                    aria-label="Toggle search filters"
                    @click="toggleFilters"
                >
                    Search filter
                </AppIndexHeaderButton>
                <AppIndexHeaderButton
                    v-if="canCreate"
                    icon="plus"
                    aria-label="Add Tyre Layout"
                    @click="openCreate"
                >
                    Add Tyre Layout
                </AppIndexHeaderButton>
            </template>

            <AppIndexCollapsibleFilters
                v-show="filtersOpen"
                panel-id="tyre-layouts-filters-panel"
                :loading="loading"
                @search="loadLayouts(1)"
                @reset="clearFilters"
            >
                <div>
                    <label class="form-label" for="tyreLayoutsIndex_search">Search</label>
                    <AppIndexSearchInput
                        v-model="search"
                        input-id="tyreLayoutsIndex_search"
                        placeholder="Search name or code"
                        max-width="100%"
                        :disabled="loading"
                        @keydown.enter="loadLayouts(1)"
                    />
                </div>
            </AppIndexCollapsibleFilters>

            <table class="table table-striped table-hover align-middle mb-0 app-index-table">
                <thead class="table-light">
                    <tr>
                        <th>Name</th>
                        <th>Code</th>
                        <th>Type</th>
                        <th>Positions</th>
                        <th>Spares</th>
                        <th>Status</th>
                        <th v-if="canEdit" class="app-index-actions-cell text-end" style="width: 120px">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="layouts.length === 0" class="app-index-empty-row">
                        <td :colspan="canEdit ? 7 : 6" class="text-center text-muted py-4">No layouts found.</td>
                    </tr>
                    <tr v-for="layout in layouts" :key="layout.id">
                        <td>{{ layout.name }}</td>
                        <td><code>{{ layout.code }}</code></td>
                        <td>{{ layoutTypeLabel(layout.layout_type) }}</td>
                        <td>{{ layout.position_count }}</td>
                        <td>{{ layout.layout_type === 'legacy_dual_rear' ? 1 : layout.spare_count }}</td>
                        <td>
                            <span class="badge" :class="layout.is_active ? 'bg-success' : 'bg-secondary'">
                                {{ layout.is_active ? 'Active' : 'Inactive' }}
                            </span>
                        </td>
                        <td v-if="canEdit" class="app-index-actions-cell text-end">
                            <div class="app-index-table-actions">
                                <button
                                    type="button"
                                    class="btn btn-sm btn-info text-white app-index-action-btn"
                                    title="Edit"
                                    aria-label="Edit"
                                    @click="openEdit(layout)"
                                >
                                    <i class="fas fa-edit" aria-hidden="true"></i>
                                </button>
                                <button
                                    type="button"
                                    class="btn btn-sm btn-danger app-index-action-btn"
                                    title="Delete"
                                    aria-label="Delete"
                                    @click="handleDelete(layout)"
                                >
                                    <i class="fas fa-trash" aria-hidden="true"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>

            <template #footer>
                <AppIndexPartloadPagination
                    :pagination="pagination"
                    :loading="loading"
                    item-label="layouts"
                    aria-label="Tyre layouts pagination"
                    @page-change="goToPage"
                />
            </template>
        </AppIndexListCard>

        <AppModal
            :show="showModal"
            :loading="saving"
            :form-error="formError"
            as-form
            size="lg"
            content-class="tyre-layout-form-modal__content"
            body-class="tyre-layout-form-modal__body"
            footer-class="tyre-layout-form-modal__footer"
            @close="closeModal"
            @submit="save"
        >
            <template #title>{{ editing ? 'Edit Tyre Layout' : 'Add Tyre Layout' }}</template>

            <div class="row g-2">
                <div class="col-md-8">
                    <label class="form-label">Layout name *</label>
                    <input
                        v-model="form.name"
                        type="text"
                        class="form-control form-control-sm"
                        :class="{ 'is-invalid': fieldErrors.name }"
                        placeholder="e.g. 10 Tyre Standard Truck"
                        required
                    />
                    <div v-if="fieldErrors.name" class="invalid-feedback d-block">
                        {{ fieldErrors.name[0] }}
                    </div>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Code</label>
                    <input
                        v-model="form.code"
                        type="text"
                        class="form-control form-control-sm"
                        :class="{ 'is-invalid': fieldErrors.code }"
                        placeholder="Auto from name"
                    />
                    <div v-if="fieldErrors.code" class="invalid-feedback d-block">
                        {{ fieldErrors.code[0] }}
                    </div>
                </div>
                <div :class="form.layout_type === 'standard' ? 'col-md-5' : 'col-md-10'">
                    <label class="form-label">Vehicle type *</label>
                    <AppSelect
                        v-model="form.layout_type"
                        :options="layoutTypeSelectOptions"
                        placeholder="Select vehicle type"
                        search-placeholder="Search type…"
                    />
                </div>
                <div v-if="form.layout_type === 'standard'" class="col-md-5">
                    <label class="form-label" for="tyreLayoutPreset">Quick start template</label>
                    <AppSelect
                        input-id="tyreLayoutPreset"
                        v-model="selectedPreset"
                        :options="presetSelectOptions"
                        placeholder="Choose a common layout…"
                        search-placeholder="Search template…"
                        :clearable="false"
                        @change="applyPreset"
                    />
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <div class="form-check mb-1">
                        <input id="layoutActive" v-model="form.is_active" class="form-check-input" type="checkbox" />
                        <label class="form-check-label" for="layoutActive">Active</label>
                    </div>
                </div>
            </div>

            <template v-if="form.layout_type === 'standard'">
                <TyreLayoutAxleFields v-model="form.axle_config" class="tyre-layout-form-modal__axle-fields" />
            </template>

            <div
                v-else-if="form.layout_type === 'legacy_dual_rear'"
                class="alert alert-secondary py-2 mt-2 mb-0 small"
            >
                Fixed layout: front left + front right, dual rear axle (4 wheels), and 1 spare — 7 positions total.
            </div>

            <div v-show="showAdvanced" class="tyre-layout-form-modal__advanced mt-2">
                <label class="form-label" for="layoutSortOrder">Sort order</label>
                <input
                    id="layoutSortOrder"
                    v-model.number="form.sort_order"
                    v-no-scroll-change
                    type="number"
                    min="0"
                    class="form-control form-control-sm"
                    style="max-width: 8rem"
                />
            </div>

            <template #footer>
                <div class="tyre-layout-form-modal__footer-inner">
                    <span class="tyre-layout-form-modal__total">
                        Total: <strong>{{ previewCount }}</strong> positions
                    </span>
                    <button
                        type="button"
                        class="btn btn-link btn-sm text-muted p-0 text-decoration-none tyre-layout-form-modal__advanced-toggle"
                        :aria-expanded="showAdvanced"
                        @click="showAdvanced = !showAdvanced"
                    >
                        <i
                            class="fas fa-chevron-right me-1 small tyre-layout-advanced-chevron"
                            :class="{ 'tyre-layout-advanced-chevron--open': showAdvanced }"
                            aria-hidden="true"
                        ></i>
                        Advanced
                    </button>
                    <div class="tyre-layout-form-modal__footer-actions">
                        <button type="button" class="btn btn-secondary btn-sm" :disabled="saving" @click="closeModal">
                            Cancel
                        </button>
                        <AppButton type="submit" label="Save" :loading="saving" />
                    </div>
                </div>
            </template>
        </AppModal>

        <MasterCrudSaveConfirmModal
            :show="showSaveConfirm"
            :summary="saveConfirmSummary"
            :saving="saving"
            @cancel="cancelSaveConfirm"
            @confirm="confirmSave"
        />
    </div>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import {
    computePositionCount,
    createTyreLayout,
    deleteTyreLayout,
    listTyreLayouts,
    updateTyreLayout,
} from '../../api/tyreLayouts';
import TyreLayoutAxleFields from '../../components/tyre-layouts/TyreLayoutAxleFields.vue';
import AppModal from '../../components/ui/AppModal.vue';
import AppButton from '../../components/ui/AppButton.vue';
import AppSelect from '../../components/ui/AppSelect.vue';
import AppIndexCollapsibleFilters from '../../components/ui/AppIndexCollapsibleFilters.vue';
import AppIndexHeaderButton from '../../components/ui/AppIndexHeaderButton.vue';
import AppIndexListCard from '../../components/ui/AppIndexListCard.vue';
import AppIndexPartloadPagination from '../../components/ui/AppIndexPartloadPagination.vue';
import AppIndexSearchInput from '../../components/ui/AppIndexSearchInput.vue';
import MasterCrudSaveConfirmModal from '../../components/ui/MasterCrudSaveConfirmModal.vue';
import { useListPagination } from '../../composables/useListPagination';
import { useApiFormErrors } from '../../composables/useApiFormErrors';
import { usePermissions } from '../../composables/usePermissions';
import { useRequireCompanyContext } from '../../composables/useRequireCompanyContext';
import { useAppToast } from '../../composables/useAppToast';
import { useMasterCrudSaveConfirm } from '../../composables/useMasterCrudSaveConfirm';
import { buildTyreLayoutFields } from '../../utils/masterCrudSaveConfirm';
import {
    LAYOUT_TYPE_OPTIONS,
    TYRE_LAYOUT_PRESETS,
    axleConfigFromPayload,
    emptyAxleConfig,
    findMatchingPresetId,
    layoutTypeLabel,
    payloadFromAxleConfig,
} from '../../utils/tyreLayoutForm';

const toast = useAppToast();
const {
    showSaveConfirm,
    saveConfirmSummary,
    pendingPayload,
    promptSaveConfirm,
    cancelSaveConfirm,
    finishSaveConfirm,
} = useMasterCrudSaveConfirm();
const { canCreateInCompany, canEditInCompany } = usePermissions();
const { needsCompanySelection, guardCompanyContext, handleCompanyContextError } = useRequireCompanyContext();
const canCreate = computed(() => canCreateInCompany('tyre_layouts'));
const canEdit = computed(() => canEditInCompany('tyre_layouts'));

const layouts = ref([]);
const loading = ref(true);
const saving = ref(false);
const search = ref('');
const filtersOpen = ref(false);
const showModal = ref(false);
const showAdvanced = ref(false);
const editing = ref(null);
const { fieldErrors, formError, clearErrors, applyApiError } = useApiFormErrors();

const form = ref(emptyForm());
const { pagination, syncFromMeta, reset: resetPagination } = useListPagination(10);

const layoutTypeSelectOptions = LAYOUT_TYPE_OPTIONS.map((option) => ({
    value: option.value,
    label: option.label,
}));

const presetSelectOptions = TYRE_LAYOUT_PRESETS.map((preset) => ({
    value: preset.id,
    label: preset.label,
}));

const selectedPreset = ref('');

watch(
    () => form.value.axle_config,
    (config) => {
        if (form.value.layout_type === 'standard') {
            selectedPreset.value = findMatchingPresetId(config);
        }
    },
    { deep: true },
);

function applyPreset(presetId) {
    const preset = TYRE_LAYOUT_PRESETS.find((entry) => entry.id === presetId);

    if (!preset?.config) {
        return;
    }

    form.value.axle_config = { ...preset.config };
}

function toggleFilters() {
    filtersOpen.value = !filtersOpen.value;
}

function clearFilters() {
    search.value = '';
    pagination.currentPage = 1;
    loadLayouts(1);
}

function emptyForm() {
    return {
        name: '',
        code: '',
        layout_type: 'standard',
        axle_config: emptyAxleConfig(),
        sort_order: 0,
        is_active: true,
    };
}

const previewCount = computed(() => {
    if (form.value.layout_type !== 'standard') {
        return computePositionCount({ layout_type: form.value.layout_type });
    }

    return computePositionCount({
        layout_type: 'standard',
        ...payloadFromAxleConfig(form.value.axle_config),
    });
});

function fillForm(layout) {
    form.value = {
        name: layout.name,
        code: layout.code,
        layout_type: layout.layout_type,
        axle_config:
            layout.layout_type === 'standard'
                ? axleConfigFromPayload(layout)
                : emptyAxleConfig(),
        sort_order: layout.sort_order,
        is_active: layout.is_active,
    };
}

async function loadLayouts(page = pagination.currentPage) {
    if (needsCompanySelection.value) {
        layouts.value = [];
        loading.value = false;
        resetPagination();
        return;
    }

    loading.value = true;
    try {
        guardCompanyContext();
        const result = await listTyreLayouts({
            page,
            perPage: pagination.perPage,
            search: search.value.trim() || undefined,
            activeOnly: false,
        });
        layouts.value = result.data;
        syncFromMeta(result.meta, result.data.length);
    } catch (e) {
        handleCompanyContextError(e);
        toast.error(e?.response?.data?.message ?? 'Could not load tyre layouts.');
    } finally {
        loading.value = false;
    }
}

function goToPage(page) {
    if (page < 1 || page > pagination.lastPage || page === pagination.currentPage) {
        return;
    }

    loadLayouts(page);
}

function openCreate() {
    editing.value = null;
    form.value = emptyForm();
    selectedPreset.value = findMatchingPresetId(form.value.axle_config);
    showAdvanced.value = false;
    clearErrors();
    showModal.value = true;
}

function openEdit(layout) {
    editing.value = layout;
    fillForm(layout);
    selectedPreset.value =
        layout.layout_type === 'standard' ? findMatchingPresetId(form.value.axle_config) : '';
    showAdvanced.value = layout.sort_order > 0;
    clearErrors();
    showModal.value = true;
}

function closeModal() {
    showModal.value = false;
    clearErrors();
}

function buildPayload() {
    const payload = {
        name: form.value.name.trim(),
        layout_type: form.value.layout_type,
        sort_order: form.value.sort_order,
        is_active: form.value.is_active,
    };

    if (form.value.code.trim()) {
        payload.code = form.value.code.trim().toUpperCase();
    }

    if (form.value.layout_type === 'standard') {
        Object.assign(payload, payloadFromAxleConfig(form.value.axle_config));
    }

    return payload;
}

function save() {
    clearErrors();

    try {
        guardCompanyContext();
    } catch (e) {
        handleCompanyContextError(e);

        return;
    }

    const payload = buildPayload();

    promptSaveConfirm({
        payload,
        entityLabel: 'Tyre Layout',
        mode: editing.value ? 'edit' : 'create',
        fields: buildTyreLayoutFields(payload, editing.value, { layoutTypeLabel }),
    });
}

async function confirmSave() {
    const payload = pendingPayload.value;

    if (!payload) {
        return;
    }

    saving.value = true;
    clearErrors();

    try {
        guardCompanyContext();

        if (editing.value) {
            await updateTyreLayout(editing.value.id, payload);
            toast.success('Layout updated.');
        } else {
            await createTyreLayout(payload);
            toast.success('Layout created.');
        }

        finishSaveConfirm();
        showModal.value = false;
        await loadLayouts();
    } catch (e) {
        finishSaveConfirm();
        applyApiError(e, 'Please check the highlighted fields.');
    } finally {
        saving.value = false;
    }
}

async function handleDelete(layout) {
    if (!window.confirm(`Delete layout "${layout.name}"?`)) {
        return;
    }

    try {
        guardCompanyContext();
        await deleteTyreLayout(layout.id);
        toast.success('Layout deleted.');
        await loadLayouts();
    } catch (e) {
        toast.error(
            e?.response?.data?.errors?.tyre_layout?.[0]
            ?? e?.response?.data?.message
            ?? 'Could not delete layout.',
        );
    }
}

onMounted(async () => {
    await loadLayouts();

    if (search.value.trim() !== '') {
        filtersOpen.value = true;
    }
});
</script>

<style scoped>
:deep(.app-modal:has(.tyre-layout-form-modal__content)) {
    max-width: 820px;
    max-height: none;
}

:deep(.app-modal:has(.tyre-layout-form-modal__content) .app-modal__content) {
    max-height: none;
}

.tyre-layout-form-modal__body {
    overflow: visible;
    padding: 0.65rem 1rem;
}

.tyre-layout-form-modal__axle-fields {
    margin-top: 0.5rem;
}

.tyre-layout-form-modal__footer {
    flex-shrink: 0;
    padding: 0.55rem 1rem;
    border-top: 1px solid #dee2e6;
    background: #fff;
}

.tyre-layout-form-modal__footer-inner {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    width: 100%;
    flex-wrap: wrap;
}

.tyre-layout-form-modal__total {
    font-size: 0.875rem;
    color: #475569;
    white-space: nowrap;
}

.tyre-layout-form-modal__total strong {
    color: #0f172a;
    font-size: 1rem;
}

.tyre-layout-form-modal__advanced-toggle {
    white-space: nowrap;
}

.tyre-layout-form-modal__footer-actions {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    margin-left: auto;
}

.tyre-layout-advanced-chevron {
    transition: transform 0.15s ease;
}

.tyre-layout-advanced-chevron--open {
    transform: rotate(90deg);
}

@media (max-width: 575.98px) {
    .tyre-layout-form-modal__footer-inner {
        gap: 0.5rem;
    }

    .tyre-layout-form-modal__footer-actions {
        width: 100%;
        margin-left: 0;
        justify-content: flex-end;
    }
}
</style>
