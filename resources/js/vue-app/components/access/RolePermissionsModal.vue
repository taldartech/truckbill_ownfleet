<template>
    <div
        :id="modalId"
        ref="modalElement"
        class="modal fade"
        tabindex="-1"
        :aria-labelledby="`${modalId}Label`"
        aria-hidden="true"
    >
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 :id="`${modalId}Label`" class="modal-title">
                        <i class="bi bi-shield-lock"></i>
                        Permissions — {{ role?.label ?? role?.name }}
                    </h5>
                    <button type="button" class="btn-close btn-close-white" aria-label="Close" @click="close"></button>
                </div>
                <form novalidate @submit.prevent="submit">
                    <div class="modal-body">
                        <div v-if="formError" class="alert alert-danger py-2">{{ formError }}</div>
                        <div v-if="loading" class="text-center py-4 text-muted">Loading permissions…</div>
                        <div v-else>
                            <div v-for="group in groupedPermissions" :key="group.name" class="mb-3">
                                <h6 class="text-uppercase text-muted small mb-2">{{ group.name }}</h6>
                                <div class="row">
                                    <div
                                        v-for="permission in group.items"
                                        :key="permission.id"
                                        class="col-md-6"
                                    >
                                        <div class="form-check">
                                            <input
                                                :id="`perm_${permission.id}`"
                                                v-model="selectedIds"
                                                class="form-check-input"
                                                type="checkbox"
                                                :value="permission.id"
                                                :disabled="!canEdit"
                                            />
                                            <label class="form-check-label" :for="`perm_${permission.id}`">
                                                {{ permission.name }}
                                                <small class="text-muted d-block">{{ permission.slug }}</small>
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" @click="close">Cancel</button>
                        <button v-if="canEdit" type="submit" class="btn btn-success" :disabled="saving || loading">
                            <span v-if="saving" class="spinner-border spinner-border-sm me-1"></span>
                            Save Permissions
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import { fetchUserRole } from '../../api/userRoles';
import { listAllPermissions } from '../../api/permissions';

const props = defineProps({
    modalId: { type: String, default: 'rolePermissionsModal' },
    show: { type: Boolean, default: false },
    role: { type: Object, default: null },
    canEdit: { type: Boolean, default: false },
    saving: { type: Boolean, default: false },
    formError: { type: String, default: '' },
});

const emit = defineEmits(['close', 'submit']);

const modalElement = ref(null);
let modalInstance = null;

const loading = ref(false);
const allPermissions = ref([]);
const selectedIds = ref([]);

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

async function loadData() {
    if (!props.role?.id) {
        return;
    }

    loading.value = true;

    try {
        const [permissions, roleDetail] = await Promise.all([
            listAllPermissions(),
            fetchUserRole(props.role.id),
        ]);

        allPermissions.value = permissions;
        selectedIds.value = (roleDetail.permissions ?? []).map((p) => p.id);
    } finally {
        loading.value = false;
    }
}

function close() {
    emit('close');
}

function submit() {
    emit('submit', [...selectedIds.value]);
}

watch(
    () => props.show,
    async (visible) => {
        if (!modalElement.value) {
            return;
        }

        const { Modal } = await import('bootstrap');

        if (!modalInstance) {
            modalInstance = new Modal(modalElement.value, { backdrop: 'static' });
            modalElement.value.addEventListener('hidden.bs.modal', () => emit('close'));
        }

        if (visible) {
            await loadData();
            modalInstance.show();
        } else {
            modalInstance.hide();
        }
    },
);
</script>
