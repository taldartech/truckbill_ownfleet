<template>
    <div class="container mt-4 pb-5 app-index-page role-permissions-page">
        <div v-if="loading" class="text-center py-5 text-muted">
            <div class="spinner-border text-success mb-3" role="status" aria-hidden="true"></div>
            <div>Loading permissions…</div>
        </div>

        <template v-else>
            <div class="card role-permissions-hero mb-3">
                <div class="card-body">
                    <RouterLink :to="{ name: 'access.roles' }" class="role-permissions-back text-decoration-none">
                        <i class="bi bi-arrow-left"></i> Back to Roles List
                    </RouterLink>

                    <div class="d-flex flex-wrap align-items-start justify-content-between gap-3 mt-3">
                        <div>
                            <h3 class="role-permissions-title mb-2">
                                <i class="bi bi-shield-lock"></i>
                                {{ role?.label ?? 'Role' }}
                                <span v-if="role?.is_protected" class="badge bg-secondary align-middle ms-1">Protected</span>
                            </h3>
                            <div class="d-flex flex-wrap align-items-center gap-2">
                                <code v-if="role?.name" class="role-permissions-slug">{{ role.name }}</code>
                                <span v-if="!canManage" class="badge text-bg-light border">View only</span>
                            </div>
                        </div>

                        <div class="role-permissions-summary">
                            <div class="role-permissions-summary__count">{{ selectedCount }}</div>
                            <div class="role-permissions-summary__label">of {{ totalCount }} enabled</div>
                            <div class="progress role-permissions-summary__bar mt-2" role="progressbar">
                                <div
                                    class="progress-bar bg-success"
                                    :style="{ width: `${selectionPercent}%` }"
                                ></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <form novalidate @submit.prevent="handleSubmit">
                <div class="card app-index-list-card role-permissions-toolbar-card mb-3">
                    <div class="card-body py-3">
                        <div class="row g-2 align-items-center">
                            <div class="col-md">
                                <div class="input-group">
                                    <span class="input-group-text bg-white">
                                        <i class="bi bi-search text-muted"></i>
                                    </span>
                                    <input
                                        v-model="searchQuery"
                                        type="search"
                                        class="form-control"
                                        placeholder="Search permissions or groups…"
                                    />
                                </div>
                            </div>
                            <div v-if="canManage" class="col-md-auto d-flex flex-wrap gap-2">
                                <button type="button" class="btn btn-sm btn-outline-success" @click="selectAll">
                                    Select all
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-secondary" @click="clearAll">
                                    Clear all
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <div v-if="formError" class="alert alert-danger py-2">{{ formError }}</div>

                <div v-if="filteredGroupedPermissions.length === 0" class="card app-index-list-card">
                    <div class="card-body text-center text-muted py-5">
                        No permissions match your search.
                    </div>
                </div>

                <div
                    v-for="group in filteredGroupedPermissions"
                    :key="group.name"
                    class="card app-index-list-card role-permissions-group mb-3"
                >
                    <div class="card-header role-permissions-group__header">
                        <div class="d-flex align-items-center gap-2 min-w-0">
                            <span class="role-permissions-group__icon">
                                <i :class="groupIcon(group.name)"></i>
                            </span>
                            <div class="min-w-0">
                                <h3 class="card-title mb-0">{{ groupLabel(group.name) }}</h3>
                                <div class="text-muted small">
                                    {{ groupSelectedCount(group) }} / {{ group.items.length }} selected
                                </div>
                            </div>
                        </div>

                        <div v-if="canManage" class="role-permissions-group__actions">
                            <button
                                type="button"
                                class="btn btn-sm btn-link text-decoration-none px-2"
                                @click="toggleGroup(group, true)"
                            >
                                All
                            </button>
                            <span class="text-muted">|</span>
                            <button
                                type="button"
                                class="btn btn-sm btn-link text-decoration-none px-2"
                                @click="toggleGroup(group, false)"
                            >
                                None
                            </button>
                        </div>
                    </div>

                    <div class="card-body role-permissions-group__body">
                        <div class="row g-3">
                            <div
                                v-for="permission in group.items"
                                :key="permission.id"
                                class="col-md-6 col-xl-4"
                            >
                                <label
                                    class="permission-tile"
                                    :class="{
                                        'permission-tile--checked': isSelected(permission.id),
                                        'permission-tile--disabled': !canManage,
                                    }"
                                >
                                    <input
                                        v-model="selectedIds"
                                        class="permission-tile__input"
                                        type="checkbox"
                                        :value="permission.id"
                                        :disabled="!canManage"
                                    />
                                    <span class="permission-tile__indicator" aria-hidden="true">
                                        <i class="bi bi-check-lg"></i>
                                    </span>
                                    <span class="permission-tile__content">
                                        <span class="permission-tile__name">{{ permission.name }}</span>
                                        <code class="permission-tile__slug">{{ permission.slug }}</code>
                                    </span>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card shadow-sm sticky-bottom-bar role-permissions-footer">
                    <div class="card-body d-flex flex-wrap align-items-center justify-content-between gap-3 py-3">
                        <div class="text-muted small">
                            <span v-if="hasChanges" class="text-warning-emphasis">
                                <i class="bi bi-circle-fill me-1" style="font-size: 0.45rem"></i>
                                Unsaved changes
                            </span>
                            <span v-else>All changes saved</span>
                        </div>

                        <div class="d-flex flex-wrap gap-2">
                            <AppButton variant="secondary" outline label="Cancel" :to="{ name: 'access.roles' }" />
                            <AppButton
                                v-if="canManage"
                                type="submit"
                                variant="success"
                                icon="bi bi-check-lg"
                                label="Save Permissions"
                                :loading="saving"
                                :disabled="!hasChanges"
                            />
                        </div>
                    </div>
                </div>
            </form>
        </template>
    </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import { useRoute } from 'vue-router';
import { fetchUserRole, syncRolePermissions } from '../../api/userRoles';
import { listAllPermissions } from '../../api/permissions';
import AppButton from '../../components/ui/AppButton.vue';
import { usePermissions } from '../../composables/usePermissions';
import { useApiFormErrors } from '../../composables/useApiFormErrors';
import { useAppToast } from '../../composables/useAppToast';

const GROUP_LABELS = {
    access: 'Access Control',
    attendance: 'Attendance',
    companies: 'Companies',
    compliance: 'Compliance',
    dashboard: 'Dashboard',
    drivers: 'Drivers',
    locations: 'Locations',
    reports: 'Reports',
    trips: 'Trips & Sites',
    tyres: 'Tyres',
    vehicles: 'Vehicles',
    general: 'General',
};

const GROUP_ICONS = {
    access: 'bi bi-shield-lock',
    attendance: 'bi bi-calendar-check',
    companies: 'bi bi-building',
    compliance: 'bi bi-file-earmark-check',
    dashboard: 'bi bi-speedometer2',
    drivers: 'bi bi-person-badge',
    locations: 'bi bi-geo-alt',
    reports: 'bi bi-file-bar-graph',
    trips: 'bi bi-signpost-split',
    tyres: 'bi bi-circle',
    vehicles: 'bi bi-truck-front',
    general: 'bi bi-grid',
};

const route = useRoute();
const toast = useAppToast();
const { can } = usePermissions();
const canManage = computed(() => can('roles.manage'));

const { formError, clearErrors, applyApiError } = useApiFormErrors();

const loading = ref(true);
const saving = ref(false);
const role = ref(null);
const allPermissions = ref([]);
const selectedIds = ref([]);
const initialSelectedIds = ref([]);
const searchQuery = ref('');

const groupedPermissions = computed(() => {
    const groups = {};

    for (const permission of allPermissions.value) {
        const groupName = permission.group || 'general';

        if (!groups[groupName]) {
            groups[groupName] = [];
        }

        groups[groupName].push(permission);
    }

    return Object.keys(groups)
        .sort()
        .map((name) => ({
            name,
            items: groups[name].sort((a, b) => a.slug.localeCompare(b.slug)),
        }));
});

const filteredGroupedPermissions = computed(() => {
    const query = searchQuery.value.trim().toLowerCase();

    if (!query) {
        return groupedPermissions.value;
    }

    return groupedPermissions.value
        .map((group) => ({
            ...group,
            items: group.items.filter(
                (permission) =>
                    permission.name.toLowerCase().includes(query)
                    || permission.slug.toLowerCase().includes(query)
                    || groupLabel(group.name).toLowerCase().includes(query),
            ),
        }))
        .filter((group) => group.items.length > 0);
});

const totalCount = computed(() => allPermissions.value.length);

const selectedCount = computed(() => selectedIds.value.length);

const selectionPercent = computed(() => {
    if (totalCount.value === 0) {
        return 0;
    }

    return Math.round((selectedCount.value / totalCount.value) * 100);
});

const hasChanges = computed(() => {
    const current = [...selectedIds.value].sort((a, b) => a - b);
    const initial = [...initialSelectedIds.value].sort((a, b) => a - b);

    return JSON.stringify(current) !== JSON.stringify(initial);
});

function groupLabel(name) {
    return GROUP_LABELS[name] ?? name.replace(/_/g, ' ').replace(/\b\w/g, (char) => char.toUpperCase());
}

function groupIcon(name) {
    return GROUP_ICONS[name] ?? GROUP_ICONS.general;
}

function isSelected(permissionId) {
    return selectedIds.value.includes(permissionId);
}

function groupPermissionIds(group) {
    return group.items.map((permission) => permission.id);
}

function groupSelectedCount(group) {
    return groupPermissionIds(group).filter((id) => selectedIds.value.includes(id)).length;
}

function toggleGroup(group, select) {
    const ids = groupPermissionIds(group);

    if (select) {
        selectedIds.value = [...new Set([...selectedIds.value, ...ids])];

        return;
    }

    selectedIds.value = selectedIds.value.filter((id) => !ids.includes(id));
}

function selectAll() {
    selectedIds.value = allPermissions.value.map((permission) => permission.id);
}

function clearAll() {
    selectedIds.value = [];
}

function setSelection(ids) {
    selectedIds.value = [...ids];
    initialSelectedIds.value = [...ids];
}

async function loadData() {
    loading.value = true;
    clearErrors();

    try {
        const roleId = route.params.roleId;
        const [permissions, roleDetail] = await Promise.all([
            listAllPermissions(),
            fetchUserRole(roleId),
        ]);

        allPermissions.value = permissions;
        role.value = roleDetail;
        setSelection((roleDetail.permissions ?? []).map((permission) => permission.id));
    } catch {
        toast.error('Could not load role permissions.');
        allPermissions.value = [];
        role.value = null;
        setSelection([]);
    } finally {
        loading.value = false;
    }
}

async function handleSubmit() {
    if (!canManage.value || !role.value?.id || !hasChanges.value) {
        return;
    }

    saving.value = true;
    clearErrors();

    try {
        const updated = await syncRolePermissions(role.value.id, [...selectedIds.value]);
        role.value = updated;
        setSelection((updated.permissions ?? []).map((permission) => permission.id));
        toast.success('Role permissions updated.');
    } catch (error) {
        if (!applyApiError(error, 'Unable to update role permissions.')) {
            toast.error('Unable to update role permissions.');
        }
    } finally {
        saving.value = false;
    }
}

onMounted(loadData);
</script>

<style scoped>
.role-permissions-hero {
    border: 1px solid var(--bs-border-color, #dee2e6);
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
    overflow: hidden;
}

.role-permissions-back {
    color: var(--bs-secondary-color, #6c757d);
    font-size: 0.875rem;
}

.role-permissions-back:hover {
    color: var(--bs-success, #198754);
}

.role-permissions-title {
    font-size: 1.35rem;
    font-weight: 600;
}

.role-permissions-title .bi {
    color: var(--bs-success, #198754);
    margin-right: 0.35rem;
}

.role-permissions-slug {
    font-size: 0.8rem;
    color: var(--bs-secondary-color, #6c757d);
    background: rgba(0, 0, 0, 0.04);
    padding: 0.2rem 0.5rem;
    border-radius: 0.35rem;
}

.role-permissions-summary {
    min-width: 160px;
    text-align: right;
}

.role-permissions-summary__count {
    font-size: 1.75rem;
    font-weight: 700;
    line-height: 1;
    color: var(--bs-success, #198754);
}

.role-permissions-summary__label {
    font-size: 0.8rem;
    color: var(--bs-secondary-color, #6c757d);
}

.role-permissions-summary__bar {
    height: 0.35rem;
    border-radius: 999px;
}

.role-permissions-toolbar-card > .card-body {
    padding: 0.75rem 1rem;
}

.role-permissions-group__header {
    gap: 0.75rem;
}

.role-permissions-group__icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 2.25rem;
    height: 2.25rem;
    border-radius: 0.65rem;
    background: rgba(25, 135, 84, 0.1);
    color: var(--bs-success, #198754);
    flex-shrink: 0;
}

.role-permissions-group__body {
    padding: 1rem !important;
}

.role-permissions-group__actions {
    display: flex;
    align-items: center;
    gap: 0.15rem;
    flex-shrink: 0;
    font-size: 0.8rem;
}

.permission-tile {
    display: flex;
    align-items: flex-start;
    gap: 0.75rem;
    width: 100%;
    min-height: 4.5rem;
    margin: 0;
    padding: 0.85rem 0.9rem;
    border: 1px solid var(--bs-border-color, #dee2e6);
    border-radius: 0.75rem;
    background: #fff;
    cursor: pointer;
    transition:
        border-color 0.15s ease,
        background-color 0.15s ease,
        box-shadow 0.15s ease;
}

.permission-tile:hover:not(.permission-tile--disabled) {
    border-color: rgba(25, 135, 84, 0.45);
    box-shadow: 0 2px 8px rgba(25, 135, 84, 0.08);
}

.permission-tile--checked {
    border-color: rgba(25, 135, 84, 0.55);
    background: rgba(25, 135, 84, 0.05);
}

.permission-tile--disabled {
    cursor: default;
    opacity: 0.85;
}

.permission-tile__input {
    position: absolute;
    opacity: 0;
    pointer-events: none;
}

.permission-tile__indicator {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 1.25rem;
    height: 1.25rem;
    margin-top: 0.1rem;
    border: 2px solid var(--bs-border-color, #ced4da);
    border-radius: 0.35rem;
    background: #fff;
    color: transparent;
    flex-shrink: 0;
    transition:
        border-color 0.15s ease,
        background-color 0.15s ease,
        color 0.15s ease;
}

.permission-tile--checked .permission-tile__indicator {
    border-color: var(--bs-success, #198754);
    background: var(--bs-success, #198754);
    color: #fff;
}

.permission-tile__content {
    display: flex;
    flex-direction: column;
    gap: 0.2rem;
    min-width: 0;
}

.permission-tile__name {
    font-size: 0.9rem;
    font-weight: 500;
    line-height: 1.35;
    color: var(--bs-body-color, #212529);
}

.permission-tile__slug {
    font-size: 0.72rem;
    color: var(--bs-secondary-color, #6c757d);
    background: transparent;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.role-permissions-footer {
    border-radius: 12px;
}

.sticky-bottom-bar {
    position: sticky;
    bottom: 0.75rem;
    z-index: 20;
}
</style>
