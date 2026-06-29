<template>
    <div class="container mt-4 app-index-page">
        <RouterLink to="/subscription" class="btn btn-link mb-3 text-decoration-none">
            <i class="bi bi-arrow-left me-1"></i> Subscriptions
        </RouterLink>

        <div v-if="error" class="alert alert-danger">{{ error }}</div>
        <div v-if="loading" class="text-center py-5 text-muted">Loading payment history…</div>

        <template v-else>
            <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
                <div>
                    <h4 class="fw-bold mb-1">Payment history</h4>
                    <p class="text-muted mb-0">Review past subscription payments and their status.</p>
                </div>
                <span class="badge bg-light text-dark border px-3 py-2">
                    ₹{{ formatMoney(totalPaid) }} total
                </span>
            </div>

            <div class="card app-index-list-card">
                <div class="card-header">
                    <h3 class="card-title mb-0">All payments</h3>
                </div>
                <div class="card-body p-0 table-responsive">
                    <table class="table table-hover mb-0">
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
                                <td>{{ formatDate(payment.created_at) }}</td>
                                <td>{{ payment.tenant?.name ?? '—' }}</td>
                                <td class="text-capitalize">{{ payment.type.replace(/_/g, ' ') }}</td>
                                <td>
                                    <StatusBadge :status="paymentStatus(payment.status)" />
                                </td>
                                <td class="text-end fw-semibold">₹{{ formatMoney(payment.total_amount) }}</td>
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
