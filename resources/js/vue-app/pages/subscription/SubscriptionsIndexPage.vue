<template>
    <div class="container mt-2 mt-md-4 app-index-page">
        <AppIndexPageHeader title="Subscriptions">
            <template #actions>
                <RouterLink to="/subscription/history" class="btn btn-outline-secondary btn-sm header-action-btn">
                    <i class="bi bi-receipt me-1"></i> Payment history
                </RouterLink>
            </template>
        </AppIndexPageHeader>

        <div v-if="error" class="alert alert-danger">{{ error }}</div>
        <div v-if="loading" class="text-center py-5 text-muted">Loading subscriptions…</div>

        <template v-else>
            <!-- 3 Stat KPI Cards in 1 Row across mobile, tablet, and desktop -->
            <div class="row g-2 g-md-3 mb-3 mb-md-4 subscription-kpis">
                <div class="col-4">
                    <div class="card h-100 subscription-kpi-card shadow-sm border-0">
                        <div class="card-body p-2 p-md-3 text-center text-md-start">
                            <div class="text-muted small text-truncate subscription-kpi-label">Active plans</div>
                            <div class="fw-bold text-success subscription-kpi-value">{{ summary.active }}</div>
                        </div>
                    </div>
                </div>
                <div class="col-4">
                    <div class="card h-100 subscription-kpi-card shadow-sm border-0">
                        <div class="card-body p-2 p-md-3 text-center text-md-start">
                            <div class="text-muted small text-truncate subscription-kpi-label">Needs attention</div>
                            <div class="fw-bold text-danger subscription-kpi-value">{{ summary.expired }}</div>
                        </div>
                    </div>
                </div>
                <div class="col-4">
                    <div class="card h-100 subscription-kpi-card shadow-sm border-0">
                        <div class="card-body p-2 p-md-3 text-center text-md-start">
                            <div class="text-muted small text-truncate subscription-kpi-label">Total companies</div>
                            <div class="fw-bold text-dark subscription-kpi-value">{{ summary.total }}</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Company Subscriptions Table Card -->
            <div class="card app-index-list-card subscription-table-card shadow-sm">
                <div class="card-header py-2 px-3 py-md-2.5 px-md-3">
                    <h3 class="card-title mb-0 subscription-card-title">Company subscriptions</h3>
                </div>
                <div class="card-body p-0 table-responsive">
                    <table class="table table-hover align-middle mb-0 subscription-table">
                        <thead class="table-light">
                            <tr>
                                <th>Company</th>
                                <th>Status</th>
                                <th>Valid until</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="company in companies" :key="company.id">
                                <td class="fw-semibold text-dark company-name-cell">{{ company.name }}</td>
                                <td>
                                    <StatusBadge :status="company.subscription?.display_status ?? 'none'" />
                                </td>
                                <td class="text-nowrap valid-date-cell">{{ formatDate(company.subscription?.ends_on) }}</td>
                                <td class="text-end">
                                    <RouterLink
                                        :to="{ path: '/subscription/manage', query: { company_id: company.id } }"
                                        class="btn btn-outline-primary btn-sm manage-btn"
                                    >
                                        Manage
                                    </RouterLink>
                                </td>
                            </tr>
                            <tr v-if="companies.length === 0">
                                <td colspan="4" class="text-center py-4 text-muted">No companies found.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </template>
    </div>
</template>

<script setup>
import { onMounted, ref } from 'vue';
import { RouterLink } from 'vue-router';
import AppIndexPageHeader from '../../components/ui/AppIndexPageHeader.vue';
import StatusBadge from '../../components/subscription/StatusBadge.vue';
import { fetchSubscriptionIndex } from '../../api/subscriptions';

const loading = ref(true);
const error = ref('');
const companies = ref([]);
const summary = ref({ active: 0, expired: 0, total: 0 });

onMounted(async () => {
    try {
        const data = await fetchSubscriptionIndex();
        companies.value = data.companies ?? [];
        summary.value = data.summary ?? summary.value;
    } catch (err) {
        error.value = err.response?.data?.message || 'Unable to load subscriptions.';
    } finally {
        loading.value = false;
    }
});

function formatDate(value) {
    if (!value) return '—';

    return new Date(value).toLocaleDateString('en-GB', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    });
}
</script>

<style scoped>
/* Mobile styling (< 768px) */
@media (max-width: 767.98px) {
    .header-action-btn {
        font-size: 0.72rem !important;
        padding: 0.2rem 0.55rem !important;
    }

    .subscription-kpi-card {
        border-radius: 10px;
    }

    .subscription-kpi-label {
        font-size: 0.68rem;
        line-height: 1.2;
        margin-bottom: 0.15rem;
    }

    .subscription-kpi-value {
        font-size: 1.25rem;
        line-height: 1.1;
    }

    .subscription-card-title {
        font-size: 0.88rem !important;
    }

    .subscription-table th {
        font-size: 0.72rem !important;
        padding: 0.45rem 0.4rem !important;
        font-weight: 600;
        white-space: nowrap;
    }

    .subscription-table td {
        font-size: 0.75rem !important;
        padding: 0.45rem 0.4rem !important;
    }

    .company-name-cell {
        font-size: 0.78rem !important;
        max-width: 120px;
        word-break: break-word;
    }

    .valid-date-cell {
        font-size: 0.72rem !important;
    }

    .manage-btn {
        font-size: 0.68rem !important;
        padding: 0.15rem 0.45rem !important;
        border-radius: 0.35rem;
        font-weight: 500;
    }
}

/* Tablet / Middle screen (768px - 1199.98px) */
@media (min-width: 768px) and (max-width: 1199.98px) {
    .subscription-kpi-label {
        font-size: 0.78rem;
    }

    .subscription-kpi-value {
        font-size: 1.5rem;
    }

    .subscription-card-title {
        font-size: 0.98rem !important;
    }

    .subscription-table th {
        font-size: 0.8rem !important;
        padding: 0.55rem 0.65rem !important;
    }

    .subscription-table td {
        font-size: 0.82rem !important;
        padding: 0.55rem 0.65rem !important;
    }

    .company-name-cell {
        font-size: 0.84rem !important;
    }

    .manage-btn {
        font-size: 0.76rem !important;
        padding: 0.22rem 0.6rem !important;
    }
}

/* Desktop (>= 1200px) */
@media (min-width: 1200px) {
    .subscription-kpi-value {
        font-size: 1.85rem;
    }
}
</style>
