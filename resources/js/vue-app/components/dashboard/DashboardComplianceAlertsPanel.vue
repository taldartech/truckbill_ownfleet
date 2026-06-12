<template>
    <div class="dashboard-panel">
        <div class="dashboard-panel__head">
            <h6 class="dashboard-panel__title">
                <i class="fas fa-shield-halved me-2 text-warning"></i>
                Compliance Alerts
            </h6>
            <AppButton
                v-if="showViewAll"
                size="sm"
                outline
                variant="primary"
                label="View all"
                icon="fas fa-list-ul"
                @click="$emit('view-all')"
            />
        </div>

        <div v-if="loading" class="dashboard-panel__empty text-muted">Loading alerts…</div>
        <div v-else-if="isEmpty" class="dashboard-panel__empty text-muted">No compliance alerts right now.</div>

        <div v-else class="dashboard-panel__body">
            <div v-if="documents.length" class="mb-3">
                <div class="dashboard-panel__subtitle">Vehicle documents</div>
                <div class="table-responsive">
                    <table class="table table-sm table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Truck</th>
                                <th>Document</th>
                                <th>Expiry</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="document in documents"
                                :key="`doc-${document.id}`"
                                class="dashboard-panel__row"
                                tabindex="0"
                                role="button"
                                @click="$emit('edit-document', document)"
                                @keydown.enter.prevent="$emit('edit-document', document)"
                            >
                                <td class="fw-medium">{{ document.vehicle_registration ?? '—' }}</td>
                                <td>{{ document.document_type_label }}</td>
                                <td>{{ formatDate(document.expiry_date) }}</td>
                                <td>
                                    <span :class="['badge rounded-pill', statusBadgeClass(document.status)]">
                                        {{ statusLabel(document) }}
                                    </span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div v-if="licenses.length">
                <div class="dashboard-panel__subtitle">Driver licenses</div>
                <div class="table-responsive">
                    <table class="table table-sm table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Driver</th>
                                <th>License</th>
                                <th>Expiry</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="license in licenses"
                                :key="`lic-${license.driver_id}`"
                                :class="[
                                    'dashboard-panel__row',
                                    { 'dashboard-panel__row--disabled': !canManageLicenses },
                                ]"
                                :tabindex="canManageLicenses ? 0 : -1"
                                :role="canManageLicenses ? 'button' : undefined"
                                @click="canManageLicenses && $emit('edit-license', license)"
                                @keydown.enter.prevent="canManageLicenses && $emit('edit-license', license)"
                            >
                                <td class="fw-medium">{{ license.driver_name }}</td>
                                <td>{{ license.license_number ?? '—' }}</td>
                                <td>{{ formatDate(license.license_expiry_date) }}</td>
                                <td>
                                    <span :class="['badge rounded-pill', statusBadgeClass(license.status)]">
                                        {{ statusLabel(license) }}
                                    </span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import AppButton from '../ui/AppButton.vue';
import { formatDate } from '../../utils/datetime';

const props = defineProps({
    documents: {
        type: Array,
        default: () => [],
    },
    licenses: {
        type: Array,
        default: () => [],
    },
    totalCount: {
        type: Number,
        default: 0,
    },
    loading: {
        type: Boolean,
        default: false,
    },
    canManageLicenses: {
        type: Boolean,
        default: false,
    },
});

defineEmits(['view-all', 'edit-document', 'edit-license']);

const isEmpty = computed(() => props.documents.length === 0 && props.licenses.length === 0);

const showViewAll = computed(() => !props.loading && props.totalCount > 0);

function statusBadgeClass(status) {
    return status === 'expired' ? 'text-bg-danger' : 'text-bg-warning';
}

function statusLabel(item) {
    if (item.status === 'expired') {
        return 'Expired';
    }

    const days = item.days_remaining;

    if (days === 0) {
        return 'Expires today';
    }

    if (days != null && days > 0) {
        return `${days}d left`;
    }

    return 'Expiring';
}
</script>

<style scoped>
.dashboard-panel {
    display: flex;
    flex-direction: column;
    height: 100%;
    padding: 1.25rem;
    border: 1px solid rgba(226, 232, 240, 0.8);
    border-radius: 1.25rem;
    background: linear-gradient(185deg, #ffffff 0%, #f8fafc 100%);
    box-shadow: 0 4px 18px rgba(15, 23, 42, 0.02);
}

.dashboard-panel__head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    margin-bottom: 0.85rem;
    flex-shrink: 0;
}

.dashboard-panel__title {
    margin: 0;
    font-size: 0.92rem;
    font-weight: 700;
    color: #0f172a;
}

.dashboard-panel__body {
    flex: 1;
    min-height: 0;
    overflow-y: auto;
}

.dashboard-panel__subtitle {
    margin-bottom: 0.45rem;
    font-size: 0.78rem;
    font-weight: 600;
    color: var(--brand-text-muted, #64748b);
    text-transform: uppercase;
    letter-spacing: 0.04em;
}

.dashboard-panel__empty {
    padding: 0.75rem 0;
    font-size: 0.88rem;
}

.dashboard-panel__row {
    cursor: pointer;
}

.dashboard-panel__row:hover,
.dashboard-panel__row:focus {
    background: rgba(245, 158, 11, 0.06);
    outline: none;
}

.dashboard-panel__row--disabled {
    cursor: default;
}

.dashboard-panel__row--disabled:hover,
.dashboard-panel__row--disabled:focus {
    background: transparent;
}
</style>
