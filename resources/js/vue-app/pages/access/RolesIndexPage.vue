<template>
    <div class="container mt-4 app-index-page app-index-page--standard roles-index-page">
        <AppIndexListCard title="Role Management" :loading="loading" loading-text="Loading roles…">
            <table class="table table-striped table-hover align-middle mb-0 app-index-table">
                <thead class="table-light">
                    <tr>
                        <th>Role</th>
                        <th>System Name</th>
                        <th>Permissions</th>
                        <th class="app-index-actions-cell" style="width: 140px">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="roles.length === 0" class="app-index-empty-row">
                        <td colspan="4" class="text-center text-muted py-4">No roles found.</td>
                    </tr>
                    <tr v-for="role in roles" :key="role.id">
                        <td>
                            <strong>{{ role.label }}</strong>
                            <span v-if="role.is_protected" class="badge bg-secondary ms-1">Protected</span>
                        </td>
                        <td><code>{{ role.name }}</code></td>
                        <td>{{ role.permissions_count ?? 0 }}</td>
                        <td class="app-index-actions-cell">
                            <AppTableActions>
                                <AppCrudActionButton
                                    v-if="canManage"
                                    action="edit"
                                    title="Manage permissions"
                                    @click="openPermissions(role)"
                                />
                            </AppTableActions>
                        </td>
                    </tr>
                </tbody>
            </table>

            <template #footer>
                <AppIndexPartloadPagination
                    :pagination="pagination"
                    :loading="loading"
                    item-label="roles"
                    aria-label="Roles pagination"
                    @page-change="goToPage"
                />
            </template>
        </AppIndexListCard>
    </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import { listUserRoles } from '../../api/userRoles';
import AppCrudActionButton from '../../components/ui/AppCrudActionButton.vue';
import AppIndexListCard from '../../components/ui/AppIndexListCard.vue';
import AppIndexPartloadPagination from '../../components/ui/AppIndexPartloadPagination.vue';
import AppTableActions from '../../components/ui/AppTableActions.vue';
import { useAppToast } from '../../composables/useAppToast';
import { useListPagination } from '../../composables/useListPagination';
import { usePermissions } from '../../composables/usePermissions';

const router = useRouter();
const toast = useAppToast();
const { can } = usePermissions();
const canManage = computed(() => can('roles.manage'));

const allRoles = ref([]);
const loading = ref(true);
const { pagination, syncFromMeta } = useListPagination(10);

const roles = computed(() => {
    const start = (pagination.currentPage - 1) * pagination.perPage;

    return allRoles.value.slice(start, start + pagination.perPage);
});

function syncRolesPagination(page = pagination.currentPage) {
    const total = allRoles.value.length;
    const lastPage = Math.max(1, Math.ceil(total / pagination.perPage));
    const currentPage = Math.min(Math.max(1, page), lastPage);
    const from = total === 0 ? 0 : (currentPage - 1) * pagination.perPage + 1;
    const to = Math.min(currentPage * pagination.perPage, total);
    const pageLength = total === 0 ? 0 : to - from + 1;

    syncFromMeta(
        {
            current_page: currentPage,
            last_page: lastPage,
            per_page: pagination.perPage,
            total,
            from,
            to,
        },
        pageLength,
    );
}

async function loadRoles(page = pagination.currentPage) {
    loading.value = true;

    try {
        allRoles.value = await listUserRoles();
        syncRolesPagination(page);
    } catch {
        toast.error('Could not load roles.');
        allRoles.value = [];
        syncRolesPagination(1);
    } finally {
        loading.value = false;
    }
}

function goToPage(page) {
    if (page < 1 || page > pagination.lastPage || page === pagination.currentPage) {
        return;
    }

    pagination.currentPage = page;
    syncRolesPagination(page);
}

function openPermissions(role) {
    router.push({
        name: 'access.roles.permissions',
        params: { roleId: role.id },
    });
}

onMounted(() => loadRoles());
</script>
