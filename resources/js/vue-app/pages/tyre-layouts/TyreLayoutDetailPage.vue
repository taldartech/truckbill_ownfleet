<template>
    <div class="container mt-4 tyre-layout-detail-page">
        <div v-if="needsCompanySelection" class="alert alert-warning">
            Select a company and click <strong>Enter</strong> to view tyre layout details.
        </div>

        <div v-if="loading" class="text-center py-5 text-muted">Loading tyre layout details…</div>

        <template v-else-if="layout">
            <div class="row g-3">
                <div class="col-lg-8">
                    <div class="card">
                        <div class="card-header">
                            <strong>Tyre Layout Information ({{ layout?.name }})</strong>
                        </div>
                        <div class="card-body row g-3 small">
                            <div class="col-md-4">
                                <span class="text-muted">Layout Name</span>
                                <div class="fw-semibold">{{ layout.name }}</div>
                            </div>
                            <div class="col-md-4">
                                <span class="text-muted">Type</span>
                                <div>{{ layoutTypeLabel(layout.layout_type) }}</div>
                            </div>
                            <div class="col-md-4">
                                <span class="text-muted">Running Tyres</span>
                                <div class="fw-semibold">{{ layout.position_count }}</div>
                            </div>
                            <div class="col-md-4">
                                <span class="text-muted">Spares</span>
                                <div>{{ layout.layout_type === 'legacy_dual_rear' ? 1 : layout.spare_count }}</div>
                            </div>
                            <template v-if="layout.layout_type === 'standard'">
                                <div class="col-md-4">
                                    <span class="text-muted">Front Steering Axles</span>
                                    <div>{{ layout.steer_axles }}</div>
                                </div>
                                <div class="col-md-4">
                                    <span class="text-muted">Dual Axles</span>
                                    <div>{{ formatAxleList(layout.dual_axles) }}</div>
                                </div>
                                <div class="col-md-4">
                                    <span class="text-muted">Single Axles</span>
                                    <div>{{ formatAxleList(layout.single_axles) }}</div>
                                </div>
                            </template>
                        </div>
                        <div class="card-footer bg-white border-top-0 d-flex justify-content-between align-items-center pt-0 pb-3 pe-3 ps-3" style="display: flex !important; justify-content: space-between !important; align-items: center !important; width: 100% !important;">
                            <div class="d-flex align-items-center">
                                <span class="text-muted me-2 small">Status:</span>
                                <span
                                    class="badge"
                                    :class="layout.is_active ? 'bg-success' : 'bg-secondary'"
                                >
                                    {{ layout.is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </div>
                            <div class="d-flex gap-2 ms-auto" style="margin-left: auto !important;">
                                <button
                                    type="button"
                                    class="btn btn-sm btn-outline-secondary btn-icon app-index-action-btn"
                                    title="Back to Tyre Layouts"
                                    style="background-color: #fff; border-color: #cbd5e1; color: #475569;"
                                    @click="goBack"
                                >
                                    <i class="fas fa-arrow-left" style="color: #475569 !important;" aria-hidden="true"></i>
                                </button>
                                <AppCrudActionButton
                                    v-if="canEdit"
                                    action="edit"
                                    @click="openEdit"
                                />
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="card">
                        <div class="card-header"><strong>Record Info</strong></div>
                        <div class="card-body small">
                            <div class="mb-2">
                                <span class="text-muted">Created</span>
                                <div>{{ formatDateTime(layout.created_at) }}</div>
                            </div>
                            <div>
                                <span class="text-muted">Last Updated</span>
                                <div>{{ formatDateTime(layout.updated_at) }}</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </template>

        <div v-else-if="!needsCompanySelection" class="alert alert-danger">
            Tyre layout not found or you do not have access.
        </div>

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
                <div class="col-md-12">
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
                Fixed layout: front left + front right, dual rear axle (4 wheels), and 1 spare — 7 running tyres total.
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
                        Total: <strong>{{ previewCount }}</strong> Running Tyres
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
import { RouterLink, useRoute, useRouter } from 'vue-router';
import {
    computePositionCount,
    fetchTyreLayout,
    updateTyreLayout,
    formatAxleList,
} from '../../api/tyreLayouts';
import TyreLayoutAxleFields from '../../components/tyre-layouts/TyreLayoutAxleFields.vue';
import AppModal from '../../components/ui/AppModal.vue';
import AppButton from '../../components/ui/AppButton.vue';
import AppSelect from '../../components/ui/AppSelect.vue';
import AppCrudActionButton from '../../components/ui/AppCrudActionButton.vue';
import MasterCrudSaveConfirmModal from '../../components/ui/MasterCrudSaveConfirmModal.vue';
import { useApiFormErrors } from '../../composables/useApiFormErrors';
import { usePermissions } from '../../composables/usePermissions';
import { useRequireCompanyContext } from '../../composables/useRequireCompanyContext';
import { useAppToast } from '../../composables/useAppToast';
import { useMasterCrudSaveConfirm } from '../../composables/useMasterCrudSaveConfirm';
import { buildTyreLayoutFields } from '../../utils/masterCrudSaveConfirm';
import { formatDateTime } from '../../utils/datetime';
import {
    LAYOUT_TYPE_OPTIONS,
    TYRE_LAYOUT_PRESETS,
    axleConfigFromPayload,
    emptyAxleConfig,
    findMatchingPresetId,
    layoutTypeLabel,
    payloadFromAxleConfig,
} from '../../utils/tyreLayoutForm';

const route = useRoute();
const router = useRouter();
const toast = useAppToast();
const { canEditInCompany } = usePermissions();
const { needsCompanySelection, guardCompanyContext, handleCompanyContextError } = useRequireCompanyContext();
const canEdit = computed(() => canEditInCompany('tyre_layouts'));

const layout = ref(null);
const loading = ref(true);
const saving = ref(false);
const showModal = ref(false);
const showAdvanced = ref(false);
const editing = ref(null);
const { fieldErrors, formError, clearErrors, applyApiError } = useApiFormErrors();

const form = ref(emptyForm());
const {
    showSaveConfirm,
    saveConfirmSummary,
    pendingPayload,
    promptSaveConfirm,
    cancelSaveConfirm,
    finishSaveConfirm,
} = useMasterCrudSaveConfirm();

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

function fillForm(data) {
    form.value = {
        name: data.name,
        code: data.code,
        layout_type: data.layout_type,
        axle_config:
            data.layout_type === 'standard'
                ? axleConfigFromPayload(data)
                : emptyAxleConfig(),
        sort_order: data.sort_order,
        is_active: data.is_active,
    };
}

async function loadLayout() {
    const layoutId = route.params.id;

    if (needsCompanySelection.value || !layoutId) {
        layout.value = null;
        loading.value = false;
        return;
    }

    loading.value = true;
    try {
        guardCompanyContext();
        layout.value = await fetchTyreLayout(layoutId);
    } catch (error) {
        layout.value = null;
        if (!handleCompanyContextError(error, 'Could not load tyre layout details.')) {
            toast.error('Could not load tyre layout details.');
        }
    } finally {
        loading.value = false;
    }
}

function goBack() {
    if (window.history.length > 1) {
        router.back();
    } else {
        router.push({ name: 'tyre-layouts.index' });
    }
}

function openEdit() {
    if (!layout.value) return;
    if (layout.value.is_in_use) {
        toast.error('This layout is assigned to one or more trucks and cannot be modified.');
        return;
    }
    editing.value = layout.value;
    fillForm(layout.value);
    selectedPreset.value =
        layout.value.layout_type === 'standard' ? findMatchingPresetId(form.value.axle_config) : '';
    showAdvanced.value = layout.value.sort_order > 0;
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
        mode: 'edit',
        fields: buildTyreLayoutFields(payload, editing.value, { layoutTypeLabel }),
    });
}

async function confirmSave() {
    const payload = pendingPayload.value;

    if (!payload || !editing.value) {
        return;
    }

    saving.value = true;
    clearErrors();

    try {
        guardCompanyContext();
        await updateTyreLayout(editing.value.id, payload);
        toast.success('Layout updated.');
        finishSaveConfirm();
        showModal.value = false;
        await loadLayout();
    } catch (e) {
        finishSaveConfirm();
        applyApiError(e, 'Please check the highlighted fields.');
    } finally {
        saving.value = false;
    }
}

onMounted(async () => {
    const ok = await guardCompanyContext();
    if (ok === true) {
        await loadLayout();
    } else {
        loading.value = false;
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
