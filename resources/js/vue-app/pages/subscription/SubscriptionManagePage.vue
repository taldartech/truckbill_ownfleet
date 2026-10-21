<template>
    <div class="container mt-2 mt-md-4 app-index-page">
        <RouterLink to="/subscription" class="btn btn-link btn-sm p-0 mb-2 mb-md-3 text-decoration-none d-inline-flex align-items-center">
            <i class="bi bi-arrow-left me-1"></i> Back to Subscriptions
        </RouterLink>

        <div v-if="error" class="alert alert-danger">{{ error }}</div>
        <div v-if="loading" class="text-center py-5 text-muted">Loading plan details…</div>

        <template v-else-if="company">
            <div v-if="!catalogAvailable" class="alert alert-warning">
                Unable to load catalog pricing from admin. Plan details below reflect your subscription record only.
            </div>

            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3 mb-md-4">
                <div>
                    <h4 class="fw-bold mb-0 fs-5 fs-md-4">{{ company.name }}</h4>
                    <p class="text-muted small mb-0">Current plan details, usage, and purchase history.</p>
                </div>
                <div class="d-flex flex-wrap align-items-center gap-1.5 gap-md-2">
                    <StatusBadge :status="subscription?.display_status ?? 'none'" />
                    <RouterLink
                        :to="{ path: '/subscription/checkout', query: { company_id: company.id, purchase_type: 'renewal' } }"
                        class="btn btn-primary btn-sm py-1 px-2.5"
                    >
                        Renew
                    </RouterLink>
                    <RouterLink
                        v-if="!isExpired"
                        :to="{ path: '/subscription/checkout', query: { company_id: company.id, purchase_type: 'addon' } }"
                        class="btn btn-outline-secondary btn-sm py-1 px-2.5"
                    >
                        Add-ons
                    </RouterLink>
                    <button v-else type="button" class="btn btn-outline-secondary btn-sm py-1 px-2.5" disabled>
                        Add-ons
                    </button>
                </div>
            </div>

            <div class="row g-3 g-md-4">
                <div class="col-lg-6">
                    <div class="card h-100 shadow-sm border-0">
                        <div class="card-header bg-primary text-white py-2 px-3 py-md-2.5 px-md-3 rounded-top">
                            <div class="small opacity-75" style="font-size: 0.72rem;">Current plan</div>
                            <h5 class="mb-1 fs-6 fs-md-5 fw-bold">{{ productName }}</h5>
                            <div class="small opacity-75" style="font-size: 0.72rem;">
                                {{ formatDate(plan.starts_on) }} – {{ formatDate(plan.ends_on) }}
                            </div>
                        </div>
                        <div class="card-body p-2.5 p-md-3">
                            <div class="row g-2 g-md-3">
                                <div v-for="item in planItems" :key="item.label" class="col-6 col-sm-4 col-lg-6">
                                    <div class="text-muted small text-truncate" style="font-size: 0.72rem;">{{ item.label }}</div>
                                    <div class="fw-semibold text-dark fs-6">{{ item.value }}</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6">
                    <div class="card h-100 shadow-sm border-0">
                        <div class="card-header py-2 px-3 py-md-2.5 px-md-3">
                            <h5 class="card-title mb-0 fs-6 fs-md-5 fw-bold">Live usage</h5>
                        </div>
                        <div class="card-body p-2.5 p-md-3">
                            <UsageBar v-for="metric in usageMetrics" :key="metric.label" :metric="metric" />
                        </div>
                    </div>
                </div>

                <div v-if="upcoming" class="col-12">
                    <div class="card shadow-sm border-0">
                        <div class="card-header py-2 px-3 py-md-2.5 px-md-3">
                            <h5 class="card-title mb-0 fs-6 fs-md-5 fw-bold">Upcoming renewal</h5>
                        </div>
                        <div class="card-body p-2.5 p-md-3">
                            <div class="row g-2 g-md-3">
                                <div class="col-4">
                                    <div class="text-muted small text-truncate" style="font-size: 0.72rem;">Branches</div>
                                    <div class="fw-semibold fs-6">{{ upcoming.branches }}</div>
                                </div>
                                <div class="col-4">
                                    <div class="text-muted small text-truncate" style="font-size: 0.72rem;">Add-on users</div>
                                    <div class="fw-semibold fs-6">{{ upcoming.addon_users }}</div>
                                </div>
                                <div class="col-4">
                                    <div class="text-muted small text-truncate" style="font-size: 0.72rem;">Add-on vehicles</div>
                                    <div class="fw-semibold fs-6">{{ upcoming.addon_trucks }}</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-12">
                    <div class="card app-index-list-card shadow-sm border-0">
                        <div class="card-header py-2 px-3 py-md-2.5 px-md-3 d-flex justify-content-between align-items-center">
                            <h5 class="card-title mb-0 subscription-card-title">Purchase history</h5>
                            <RouterLink
                                :to="{ path: '/subscription/history', query: { company_id: company.id } }"
                                class="btn btn-outline-secondary btn-sm header-action-btn"
                            >
                                View all
                            </RouterLink>
                        </div>
                        <div class="card-body p-0 table-responsive">
                            <table class="table table-hover align-middle mb-0 subscription-table">
                                <thead class="table-light">
                                    <tr>
                                        <th>Date</th>
                                        <th>Type</th>
                                        <th class="text-end">Amount</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="payment in payments" :key="payment.id">
                                        <td class="text-nowrap">{{ formatDate(payment.created_at) }}</td>
                                        <td class="text-capitalize">{{ payment.type.replace(/_/g, ' ') }}</td>
                                        <td class="text-end fw-semibold text-nowrap">₹{{ formatMoney(payment.total_amount) }}</td>
                                    </tr>
                                    <tr v-if="payments.length === 0">
                                        <td colspan="3" class="text-center py-4 text-muted">No completed payments yet.</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </template>
    </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import { RouterLink, useRoute } from 'vue-router';
import StatusBadge from '../../components/subscription/StatusBadge.vue';
import UsageBar from '../../components/subscription/UsageBar.vue';
import { fetchSubscriptionManage } from '../../api/subscriptions';

const route = useRoute();
const loading = ref(true);
const error = ref('');
const company = ref(null);
const subscription = ref(null);
const isExpired = ref(false);
const catalogAvailable = ref(true);
const productName = ref('');
const plan = ref({});
const usageMetrics = ref([]);
const payments = ref([]);
const upcoming = ref(null);

const planItems = computed(() => [
    { label: 'Included branches', value: plan.value.included_branches },
    { label: 'Add-on branches', value: plan.value.addon_branches },
    { label: 'Users per branch', value: plan.value.users_per_branch },
    { label: 'Add-on users', value: plan.value.addon_users },
    { label: 'Included vehicles', value: plan.value.included_trucks },
    { label: 'Add-on vehicles', value: plan.value.addon_trucks },
]);

onMounted(async () => {
    const companyId = route.query.company_id;

    if (!companyId) {
        error.value = 'Company is required.';
        loading.value = false;
        return;
    }

    try {
        const data = await fetchSubscriptionManage(companyId);
        company.value = data.company;
        subscription.value = data.subscription;
        isExpired.value = data.isExpired;
        catalogAvailable.value = data.catalogAvailable;
        productName.value = data.productName;
        plan.value = data.plan ?? {};
        usageMetrics.value = data.usageMetrics ?? [];
        payments.value = data.payments ?? [];
        upcoming.value = data.upcoming;
    } catch (err) {
        error.value = err.response?.data?.message || 'Unable to load subscription details.';
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

function formatMoney(value) {
    return Number(value).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}
</script>

<style scoped>
/* Mobile styling (< 768px) */
@media (max-width: 767.98px) {
    .header-action-btn {
        font-size: 0.72rem !important;
        padding: 0.18rem 0.5rem !important;
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
}

/* Tablet / Middle screen (768px - 1199.98px) */
@media (min-width: 768px) and (max-width: 1199.98px) {
    .header-action-btn {
        font-size: 0.78rem !important;
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
}
</style>
