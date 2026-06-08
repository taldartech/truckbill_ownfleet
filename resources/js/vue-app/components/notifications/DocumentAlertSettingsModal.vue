<template>
    <div
        id="documentAlertSettingsModal"
        ref="modalElement"
        class="modal fade"
        tabindex="-1"
        aria-labelledby="documentAlertSettingsModalLabel"
        aria-hidden="true"
    >
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <form @submit.prevent="save">
                    <div class="modal-header">
                        <h5 id="documentAlertSettingsModalLabel" class="modal-title fw-bold">
                            <i class="fas fa-bell me-2 text-brand-primary"></i>
                            Document Alert Notifications
                        </h5>
                        <button type="button" class="btn-close" aria-label="Close" @click="close"></button>
                    </div>
                    <div class="modal-body">
                        <div v-if="loading" class="text-center py-4 text-muted">Loading settings…</div>
                        <div v-else-if="loadError" class="alert alert-danger mb-0">{{ loadError }}</div>
                        <template v-else>
                            <p class="text-muted small">
                                Choose who receives document expiry alerts. Notifications appear in the header bell and can
                                also be sent by email.
                            </p>

                            <div class="row g-3 mb-4">
                                <div class="col-md-6">
                                    <div class="form-check form-switch">
                                        <input
                                            id="notify_live"
                                            v-model="form.notify_live"
                                            class="form-check-input"
                                            type="checkbox"
                                            role="switch"
                                        />
                                        <label class="form-check-label" for="notify_live">Live notifications</label>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-check form-switch">
                                        <input
                                            id="notify_email"
                                            v-model="form.notify_email"
                                            class="form-check-input"
                                            type="checkbox"
                                            role="switch"
                                        />
                                        <label class="form-check-label" for="notify_email">Email notifications</label>
                                    </div>
                                </div>
                            </div>

                            <h6 class="fw-bold mb-2">Recipients</h6>
                            <p class="text-muted small mb-3">
                                Select users who should receive document alerts. If none are selected, admins and moderators
                                with document access are notified by default.
                            </p>

                            <div v-if="availableUsers.length === 0" class="alert alert-warning py-2">
                                No company users found.
                            </div>
                            <div v-else class="list-group">
                                <label
                                    v-for="availableUser in availableUsers"
                                    :key="availableUser.id"
                                    class="list-group-item list-group-item-action d-flex align-items-center gap-2"
                                >
                                    <input
                                        v-model="form.recipient_user_ids"
                                        class="form-check-input mt-0"
                                        type="checkbox"
                                        :value="availableUser.id"
                                    />
                                    <div class="flex-grow-1">
                                        <div class="fw-medium">{{ availableUser.name }}</div>
                                        <div class="small text-muted">
                                            {{ availableUser.email }}
                                            <span v-if="availableUser.role"> · {{ availableUser.role }}</span>
                                        </div>
                                    </div>
                                </label>
                            </div>

                            <div v-if="saveError" class="alert alert-danger mt-3 mb-0 py-2">{{ saveError }}</div>
                        </template>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" @click="close">Cancel</button>
                        <button type="submit" class="btn btn-brand-primary" :disabled="saving || loading">
                            {{ saving ? 'Saving…' : 'Save settings' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>

<script setup>
import { reactive, ref, watch } from 'vue';
import { fetchDocumentAlertSettings, updateDocumentAlertSettings } from '../../api/documentAlertSettings';

const props = defineProps({
    show: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['close', 'saved']);

const modalElement = ref(null);
let bootstrapModal = null;

const loading = ref(false);
const saving = ref(false);
const loadError = ref('');
const saveError = ref('');
const availableUsers = ref([]);

const form = reactive({
    notify_live: true,
    notify_email: true,
    recipient_user_ids: [],
});

watch(
    () => props.show,
    async (visible) => {
        if (!modalElement.value || typeof window.bootstrap === 'undefined') {
            return;
        }

        if (!bootstrapModal) {
            bootstrapModal = new window.bootstrap.Modal(modalElement.value, { backdrop: true });
        }

        if (visible) {
            await loadSettings();
            bootstrapModal.show();
        } else {
            bootstrapModal.hide();
        }
    },
);

async function loadSettings() {
    loading.value = true;
    loadError.value = '';
    saveError.value = '';

    try {
        const data = await fetchDocumentAlertSettings();
        form.notify_live = data.settings?.notify_live ?? true;
        form.notify_email = data.settings?.notify_email ?? true;
        form.recipient_user_ids = [...(data.settings?.recipient_user_ids ?? [])];
        availableUsers.value = data.available_users ?? [];
    } catch {
        loadError.value = 'Could not load notification settings.';
    } finally {
        loading.value = false;
    }
}

async function save() {
    saving.value = true;
    saveError.value = '';

    try {
        await updateDocumentAlertSettings({
            notify_live: form.notify_live,
            notify_email: form.notify_email,
            recipient_user_ids: form.recipient_user_ids,
        });
        emit('saved');
        close();
    } catch {
        saveError.value = 'Could not save notification settings.';
    } finally {
        saving.value = false;
    }
}

function close() {
    emit('close');
}
</script>
