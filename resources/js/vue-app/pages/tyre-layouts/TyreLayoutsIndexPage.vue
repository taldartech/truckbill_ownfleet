<template>
    <div class="container mt-4 app-index-page">
        <AppIndexPageHeader title="Tyre Layouts">
            <template v-if="canManage" #actions>
                <AppButton icon="bi bi-plus" label="Create" @click="openCreate" />
            </template>
        </AppIndexPageHeader>

        <div v-if="needsCompanySelection" class="alert alert-warning">
            Select a company and click <strong>Enter</strong> before managing tyre layouts.
        </div>

        <AppIndexFiltersCard :active="filtersActive" @apply="loadLayouts(1)" @clear="clearFilters">
            <div>
                <label class="form-label">Search</label>
                <AppIndexSearchInput
                    v-model="search"
                    input-id="tyreLayoutsIndex_search"
                    placeholder="Search name or code"
                    max-width="100%"
                    :disabled="loading"
                />
            </div>
        </AppIndexFiltersCard>

        <AppIndexListCard :loading="loading" loading-text="Loading layouts…">
            <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Name</th>
                            <th>Code</th>
                            <th>Type</th>
                            <th>Positions</th>
                            <th>Spares</th>
                            <th>Status</th>
                            <th v-if="canManage" class="app-index-actions-cell" style="width: 120px">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="layouts.length === 0">
                            <td :colspan="canManage ? 7 : 6" class="text-center text-muted">No layouts found.</td>
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
                            <td v-if="canManage" class="app-index-actions-cell">
                                <AppTableActions>
                                    <AppCrudActionButton action="edit" @click="openEdit(layout)" />
                                    <AppCrudActionButton action="delete" @click="handleDelete(layout)" />
                                </AppTableActions>
                            </td>
                        </tr>
                    </tbody>
            </table>
            <template #footer>
                <AppIndexPaginationFooter
                    page-id="tyreLayoutsIndex"
                    :loading="loading"
                    :current-page="pagination.currentPage"
                    :last-page="pagination.lastPage"
                    :per-page="pagination.perPage"
                    :total="pagination.total"
                    :from="pagination.from"
                    :to="pagination.to"
                    item-label="layouts"
                    :per-page-options="[10, 15, 25, 50]"
                    @page-change="loadLayouts"
                    @update:per-page="onPerPageUpdate"
                />
            </template>
        </AppIndexListCard>

        <div
            v-if="showModal"
            class="modal fade show d-block"
            tabindex="-1"
            style="background: rgba(0, 0, 0, 0.45)"
            @click.self="closeModal"
        >
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header bg-gradient-primary">
                        <h5 class="modal-title">{{ editing ? 'Edit Tyre Layout' : 'Add Tyre Layout' }}</h5>
                        <button type="button" class="btn-close btn-close-white" @click="closeModal"></button>
                    </div>
                    <form novalidate @submit.prevent="save">
                        <div class="modal-body">
                            <div v-if="formError" class="alert alert-danger py-2">{{ formError }}</div>

                            <div class="row g-3">
                                <div class="col-md-8">
                                    <label class="form-label">Name *</label>
                                    <input
                                        v-model="form.name"
                                        type="text"
                                        class="form-control"
                                        :class="{ 'is-invalid': fieldErrors.name }"
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
                                        class="form-control"
                                        :class="{ 'is-invalid': fieldErrors.code }"
                                        placeholder="Auto from name"
                                    />
                                    <div v-if="fieldErrors.code" class="invalid-feedback d-block">
                                        {{ fieldErrors.code[0] }}
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Layout type *</label>
                                    <AppSelect
                                        v-model="form.layout_type"
                                        :options="layoutTypeSelectOptions"
                                        placeholder="Select layout type"
                                        search-placeholder="Search type…"
                                    />
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Sort order</label>
                                    <input v-model.number="form.sort_order" v-no-scroll-change type="number" min="0" class="form-control" />
                                </div>
                                <div class="col-md-3 d-flex align-items-end">
                                    <div class="form-check">
                                        <input id="layoutActive" v-model="form.is_active" class="form-check-input" type="checkbox" />
                                        <label class="form-check-label" for="layoutActive">Active</label>
                                    </div>
                                </div>
                            </div>

                            <template v-if="form.layout_type === 'standard'">
                                <hr />
                                <div class="row g-3">
                                    <div class="col-md-3">
                                        <label class="form-label">Steer axles</label>
                                        <input v-model.number="form.steer_axles" v-no-scroll-change type="number" min="0" max="2" class="form-control" />
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">Spare count</label>
                                        <input v-model.number="form.spare_count" v-no-scroll-change type="number" min="0" max="10" class="form-control" />
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Dual axles</label>
                                        <input
                                            v-model="form.dual_axles_text"
                                            type="text"
                                            class="form-control"
                                            placeholder="e.g. 1, 2, 3"
                                        />
                                        <div class="form-text">Comma-separated axle numbers with inner+outer wheels.</div>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Single-wheel axles</label>
                                        <input
                                            v-model="form.single_axles_text"
                                            type="text"
                                            class="form-control"
                                            placeholder="e.g. 1"
                                        />
                                        <div class="form-text">For small trucks — one tyre per side per axle.</div>
                                    </div>
                                </div>
                            </template>

                            <div class="alert alert-info py-2 mt-3 mb-0 small">
                                Total positions: <strong>{{ previewCount }}</strong>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" @click="closeModal">Cancel</button>
                            <AppButton type="submit" label="Save" :loading="saving" />
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import {
    computePositionCount,
    createTyreLayout,
    deleteTyreLayout,
    formatAxleList,
    listTyreLayouts,
    parseAxleList,
    updateTyreLayout,
} from '../../api/tyreLayouts';
import AppButton from '../../components/ui/AppButton.vue';
import AppSelect from '../../components/ui/AppSelect.vue';
import AppCrudActionButton from '../../components/ui/AppCrudActionButton.vue';
import AppIndexFiltersCard from '../../components/ui/AppIndexFiltersCard.vue';
import AppIndexListCard from '../../components/ui/AppIndexListCard.vue';
import AppIndexPageHeader from '../../components/ui/AppIndexPageHeader.vue';
import AppIndexPaginationFooter from '../../components/ui/AppIndexPaginationFooter.vue';
import AppIndexSearchInput from '../../components/ui/AppIndexSearchInput.vue';
import AppTableActions from '../../components/ui/AppTableActions.vue';
import { useListPagination } from '../../composables/useListPagination';
import { useApiFormErrors } from '../../composables/useApiFormErrors';
import { usePermissions } from '../../composables/usePermissions';
import { useRequireCompanyContext } from '../../composables/useRequireCompanyContext';
import { useAppToast } from '../../composables/useAppToast';

const toast = useAppToast();
const { canInCompany } = usePermissions();
const { needsCompanySelection, guardCompanyContext, handleCompanyContextError } = useRequireCompanyContext();
const canManage = computed(() => canInCompany('tyre_layouts.manage'));

const layouts = ref([]);
const loading = ref(true);
const saving = ref(false);
const search = ref('');
const showModal = ref(false);
const editing = ref(null);
const { fieldErrors, formError, clearErrors, applyApiError } = useApiFormErrors();

const form = ref(emptyForm());
const { pagination, syncFromMeta, reset: resetPagination } = useListPagination(15);

const filtersActive = computed(() => search.value.trim() !== '');

const layoutTypeSelectOptions = [
    { value: 'standard', label: 'Standard (steer + axles + spares)' },
    { value: 'legacy_dual_rear', label: 'Legacy (front + dual rear + 1 spare)' },
];

function clearFilters() {
    search.value = '';
    loadLayouts(1);
}

function emptyForm() {
    return {
        name: '',
        code: '',
        layout_type: 'standard',
        steer_axles: 1,
        spare_count: 0,
        dual_axles_text: '1, 2',
        single_axles_text: '',
        sort_order: 0,
        is_active: true,
    };
}

function layoutTypeLabel(type) {
    return type === 'legacy_dual_rear' ? 'Legacy' : 'Standard';
}

const previewCount = computed(() =>
    computePositionCount({
        layout_type: form.value.layout_type,
        steer_axles: form.value.steer_axles,
        dual_axles: parseAxleList(form.value.dual_axles_text),
        single_axles: parseAxleList(form.value.single_axles_text),
        spare_count: form.value.spare_count,
    }),
);

function fillForm(layout) {
    form.value = {
        name: layout.name,
        code: layout.code,
        layout_type: layout.layout_type,
        steer_axles: layout.steer_axles,
        spare_count: layout.spare_count,
        dual_axles_text: formatAxleList(layout.dual_axles),
        single_axles_text: formatAxleList(layout.single_axles),
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

function onPerPageUpdate(perPage) {
    pagination.perPage = perPage;
    loadLayouts(1);
}

function openCreate() {
    editing.value = null;
    form.value = emptyForm();
    clearErrors();
    showModal.value = true;
}

function openEdit(layout) {
    editing.value = layout;
    fillForm(layout);
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
        payload.steer_axles = form.value.steer_axles;
        payload.spare_count = form.value.spare_count;
        payload.dual_axles = parseAxleList(form.value.dual_axles_text);
        payload.single_axles = parseAxleList(form.value.single_axles_text);
    }

    return payload;
}

async function save() {
    saving.value = true;
    clearErrors();
    try {
        guardCompanyContext();
        const payload = buildPayload();
        if (editing.value) {
            await updateTyreLayout(editing.value.id, payload);
            toast.success('Layout updated.');
        } else {
            await createTyreLayout(payload);
            toast.success('Layout created.');
        }
        showModal.value = false;
        await loadLayouts();
    } catch (e) {
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

onMounted(loadLayouts);
</script>
