<template>
    <div class="container mt-4 app-index-page">
        <RouterLink to="/subscription" class="btn btn-link mb-3 text-decoration-none">
            <i class="bi bi-arrow-left me-1"></i> All companies
        </RouterLink>

        <div v-if="error" class="alert alert-danger">{{ error }}</div>
        <div v-if="loading" class="text-center py-5 text-muted">Loading plan details…</div>

        <template v-else-if="company">
            <div v-if="!catalogAvailable" class="alert alert-warning">
                Unable to load catalog pricing from admin. Plan details below reflect your subscription record only.
            </div>

            <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
                <div>
                    <h4 class="fw-bold mb-1">{{ company.name }}</h4>
                    <p class="text-muted mb-0">Current plan details, usage, and purchase history.</p>
                </div>
                <div class="d-flex flex-wrap align-items-center gap-2">
                    <StatusBadge :status="subscription?.display_status ?? 'none'" />
                    <RouterLink
                        :to="{ path: '/subscription/checkout', query: { company_id: company.id, purchase_type: 'renewal' } }"
                        class="btn btn-primary btn-sm"
                    >
                        Renew
                    </RouterLink>
                    <RouterLink
                        v-if="!isExpired"
                        :to="{ path: '/subscription/checkout', query: { company_id: company.id, purchase_type: 'addon' } }"
                        class="btn btn-outline-secondary btn-sm"
                    >
                        Add-ons
                    </RouterLink>
                    <button v-else type="button" class="btn btn-outline-secondary btn-sm" disabled>
                        Add-ons
                    </button>
                </div>
            </div>

            <div class="row g-4">
                <div class="col-lg-6">
                    <div class="card h-100">
                        <div class="card-header bg-primary text-white">
                            <div class="small opacity-75">Current plan</div>
                            <h5 class="mb-1">{{ productName }}</h5>
                            <div class="small opacity-75">
                                {{ formatDate(plan.starts_on) }} – {{ formatDate(plan.ends_on) }}
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="row g-3">
                                <div v-for="item in planItems" :key="item.label" class="col-6">
                                    <div class="text-muted small">{{ item.label }}</div>
                                    <div class="fw-semibold">{{ item.value }}</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6">
                    <div class="card h-100">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Live usage</h5>
                        </div>
                        <div class="card-body">
                            <UsageBar v-for="metric in usageMetrics" :key="metric.label" :metric="metric" />
                        </div>
                    </div>
                </div>

                <div v-if="upcoming" class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Upcoming renewal</h5>
                        </div>
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <div class="text-muted small">Branches</div>
                                    <div class="fw-semibold">{{ upcoming.branches }}</div>
                                </div>
                                <div class="col-md-4">
                                    <div class="text-muted small">Add-on users</div>
                                    <div class="fw-semibold">{{ upcoming.addon_users }}</div>
                                </div>
                                <div class="col-md-4">
                                    <div class="text-muted small">Add-on vehicles</div>
                                    <div class="fw-semibold">{{ upcoming.addon_trucks }}</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-12">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="card-title mb-0">Purchase history</h5>
                            <RouterLink
                                :to="{ path: '/subscription/history', query: { company_id: company.id } }"
                                class="btn btn-outline-secondary btn-sm"
                            >
                                View all
                            </RouterLink>
                        </div>
                        <div class="card-body p-0 table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Date</th>
                                        <th>Type</th>
                                        <th class="text-end">Amount</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="payment in payments" :key="payment.id">
                                        <td>{{ formatDate(payment.created_at) }}</td>
                                        <td class="text-capitalize">{{ payment.type.replace(/_/g, ' ') }}</td>
                                        <td class="text-end fw-semibold">₹{{ formatMoney(payment.total_amount) }}</td>
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
