<template>
    <div class="container mt-2 mt-md-4 app-index-page">
        <RouterLink to="/subscription" class="btn btn-link btn-sm p-0 mb-2 mb-md-3 text-decoration-none d-inline-flex align-items-center">
            <i class="bi bi-arrow-left me-1"></i> Back to Subscriptions
        </RouterLink>

        <div v-if="error" class="alert alert-danger">{{ error }}</div>
        <div v-if="loading" class="text-center py-5 text-muted">Loading payment history…</div>

        <template v-else>
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3 mb-md-4">
                <div>
                    <h4 class="fw-bold mb-0 fs-5 fs-md-4">Payment history</h4>
                    <p class="text-muted small mb-0">Review past subscription payments and their status.</p>
                </div>
                <span class="badge bg-light text-dark border px-2.5 py-1.5 px-md-3 py-md-2 total-paid-badge">
                    ₹{{ formatMoney(totalPaid) }} total
                </span>
            </div>

            <div class="card app-index-list-card shadow-sm">
                <div class="card-header py-2 px-3 py-md-2.5 px-md-3">
                    <h3 class="card-title mb-0 subscription-card-title">All payments</h3>
                </div>
                <div class="card-body p-0 table-responsive">
                    <table class="table table-hover align-middle mb-0 subscription-table">
                        <thead class="table-light">
                            <tr>
                                <th>Date</th>
                                <th>Company</th>
                                <th>Type</th>
                                <th>Status</th>
                                <th class="text-end">Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="payment in payments" :key="payment.id">
                                <td class="text-nowrap">{{ formatDate(payment.created_at) }}</td>
                                <td class="fw-semibold text-dark">{{ payment.tenant?.name ?? '—' }}</td>
                                <td class="text-capitalize">{{ payment.type.replace(/_/g, ' ') }}</td>
                                <td>
                                    <StatusBadge :status="paymentStatus(payment.status)" />
                                </td>
                                <td class="text-end fw-semibold text-nowrap">₹{{ formatMoney(payment.total_amount) }}</td>
                            </tr>
                            <tr v-if="payments.length === 0">
                                <td colspan="5" class="text-center py-4 text-muted">No payments recorded yet.</td>
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
import { RouterLink, useRoute } from 'vue-router';
import StatusBadge from '../../components/subscription/StatusBadge.vue';
import { fetchSubscriptionHistory } from '../../api/subscriptions';

const route = useRoute();
const loading = ref(true);
const error = ref('');
const payments = ref([]);
const totalPaid = ref(0);

onMounted(async () => {
    try {
        const data = await fetchSubscriptionHistory({
            company_id: route.query.company_id || undefined,
        });
        payments.value = data.payments?.data ?? [];
        totalPaid.value = data.totalPaid ?? 0;
    } catch (err) {
        error.value = err.response?.data?.message || 'Unable to load payment history.';
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

function paymentStatus(status) {
    if (['paid', 'success', 'completed'].includes(status)) {
        return 'active';
    }

    if (['failed', 'cancelled'].includes(status)) {
        return 'expired';
    }

    return 'none';
}
</script>

<style scoped>
/* Mobile styling (< 768px) */
@media (max-width: 767.98px) {
    .total-paid-badge {
        font-size: 0.72rem !important;
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
    .total-paid-badge {
        font-size: 0.82rem !important;
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
