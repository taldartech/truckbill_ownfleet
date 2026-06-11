<template>
    <div class="dashboard-panel">
        <div class="dashboard-panel__head">
            <h6 class="dashboard-panel__title">
                <i class="fas fa-shield-halved me-2 text-warning"></i>
                Compliance Alerts
            </h6>
            <button
                v-if="hasMore"
                type="button"
                class="btn btn-link dashboard-panel__link p-0"
                @click="$emit('view-all')"
            >
                View all
            </button>
        </div>

        <div v-if="loading" class="dashboard-panel__empty text-muted">Loading alerts…</div>
        <div v-else-if="isEmpty" class="dashboard-panel__empty text-muted">No compliance alerts right now.</div>

        <template v-else>
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
        </template>
    </div>
</template>

<script setup>
import { computed } from 'vue';

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

const hasMore = computed(() => {
    const shown = props.documents.length + props.licenses.length;

    return props.totalCount > shown;
});

function formatDate(value) {
    if (!value) {
        return '—';
    }

    return new Date(`${value}T00:00:00`).toLocaleDateString('en-GB', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    });
}

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
    padding: 0.9rem 1rem;
    border: 1px solid var(--brand-border, #e2e8f0);
    border-radius: 0.85rem;
    background: #fff;
}

.dashboard-panel__head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    margin-bottom: 0.75rem;
}

.dashboard-panel__title {
    margin: 0;
    font-size: 0.95rem;
    font-weight: 700;
    color: #0f172a;
}

.dashboard-panel__subtitle {
    margin-bottom: 0.45rem;
    font-size: 0.78rem;
    font-weight: 600;
    color: var(--brand-text-muted, #64748b);
    text-transform: uppercase;
    letter-spacing: 0.04em;
}

.dashboard-panel__link {
    font-size: 0.82rem;
    font-weight: 600;
    text-decoration: none;
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
