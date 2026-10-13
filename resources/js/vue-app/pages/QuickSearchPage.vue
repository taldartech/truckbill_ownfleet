<template>
    <div class="quick-search-page">
        <div class="card card-outline card-primary shadow-sm mb-4">
            <div class="card-header border-0 pb-0">
                <h3 class="card-title">Quick Search</h3>
                <div class="card-tools">
                    <button type="button" class="btn btn-tool" data-lte-toggle="card-collapse">
                        <i class="fas fa-minus"></i>
                    </button>
                </div>
            </div>
            <div class="card-body pt-3 pb-4">
                <div class="input-group input-group-lg">
                    <span class="input-group-text text-muted">
                        <i class="fas fa-search"></i>
                    </span>
                    <input
                        ref="searchInputRef"
                        v-model="localQuery"
                        type="text"
                        class="form-control"
                        placeholder="Search Trip No. or Vehicle No. (Ctrl+K to focus)"
                        autofocus
                    />
                    <button
                        v-if="localQuery"
                        class="btn btn-light border"
                        type="button"
                        @click="clearSearch"
                    >
                        <i class="fas fa-times text-muted"></i>
                    </button>
                </div>
                <div v-if="loading" class="text-muted mt-2 small">
                    <i class="fas fa-spinner fa-spin me-1"></i> Searching...
                </div>
            </div>
        </div>

        <div v-if="detail" class="detail-dashboard">
            <div class="row">
                <div class="col-12 mb-3">
                    <div class="d-flex align-items-center justify-content-between">
                        <h4 class="mb-0">
                            <span class="text-muted fw-normal me-2">Trip Overview</span>
                            <span class="text-primary">{{ detail.trip.trip_number }}</span>
                        </h4>
                        <div class="bilty-table-actions">
                            <RouterLink 
                                :to="{ name: 'trips.show', params: { id: detail.trip.id } }"
                                class="btn btn-sm btn-outline-primary"
                            >
                                <i class="fas fa-external-link-alt me-1"></i> Full Details
                            </RouterLink>
                        </div>
                    </div>
                </div>

                <div class="col-md-6 col-lg-4 mb-4">
                    <div class="card h-100 shadow-sm charge-card">
                        <div class="card-header detail-section-header rounded-top">
                            <i class="fas fa-route me-2"></i> TRIP INFO
                        </div>
                        <div class="card-body p-3">
                            <div class="mb-2">
                                <div class="detail-label">Vehicle</div>
                                <div class="detail-value text-uppercase">{{ detail.trip.vehicle?.registration_number || 'N/A' }}</div>
                            </div>
                            <div class="mb-2">
                                <div class="detail-label">Driver</div>
                                <div class="detail-value">{{ detail.trip.driver?.name || 'N/A' }}</div>
                            </div>
                            <div class="mb-2">
                                <div class="detail-label">Customer</div>
                                <div class="detail-value">{{ detail.trip.customer?.name || 'N/A' }}</div>
                            </div>
                            <div class="mb-2">
                                <div class="detail-label">Route</div>
                                <div class="detail-value">
                                    {{ detail.trip.start_station?.name || 'N/A' }}
                                    <i class="fas fa-arrow-right text-muted small mx-1"></i>
                                    {{ detail.trip.route_legs?.[0]?.to_station?.name || 'N/A' }}
                                </div>
                            </div>
                            <div>
                                <div class="detail-label">Status</div>
                                <div class="detail-value">
                                    <span class="badge" :class="statusClass(detail.trip.status)">
                                        {{ String(detail.trip.status || '').replace(/_/g, ' ').toUpperCase() }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-md-6 col-lg-4 mb-4">
                    <div class="card h-100 shadow-sm charge-card">
                        <div class="card-header detail-section-header rounded-top bg-info text-white">
                            <i class="fas fa-money-bill-wave me-2"></i> EXPENSES
                        </div>
                        <div class="card-body p-0">
                            <template v-if="detail.expenses && detail.expenses.length">
                                <div class="table-responsive">
                                    <table class="table table-sm table-hover mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th class="ps-3">Type</th>
                                                <th>Amount</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr v-for="exp in detail.expenses" :key="exp.id">
                                                <td class="ps-3">{{ formatType(exp.expense_type) }}</td>
                                                <td class="fw-semibold">₹ {{ formatMoney(exp.amount) }}</td>
                                            </tr>
                                        </tbody>
                                        <tfoot class="table-light">
                                            <tr>
                                                <td class="ps-3 fw-bold text-end">Total:</td>
                                                <td class="fw-bold">₹ {{ formatMoney(detail.trip.total_expense_amount) }}</td>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                            </template>
                            <p v-else class="na-text p-3 mb-0">No expenses recorded.</p>
                        </div>
                    </div>
                </div>

                <div class="col-md-6 col-lg-4 mb-4">
                    <div class="card h-100 shadow-sm charge-card">
                        <div class="card-header detail-section-header rounded-top bg-success text-white">
                            <i class="fas fa-file-invoice me-2"></i> INVOICES
                        </div>
                        <div class="card-body p-0">
                            <template v-if="detail.invoices && detail.invoices.length">
                                <ul class="list-group list-group-flush">
                                    <li class="list-group-item p-3" v-for="inv in detail.invoices" :key="inv.id">
                                        <div class="d-flex justify-content-between align-items-start mb-2">
                                            <div>
                                                <RouterLink :to="{ name: 'invoices.show', params: { id: inv.id } }" class="fw-bold fs-6 text-decoration-none">
                                                    {{ inv.invoice_number }}
                                                </RouterLink>
                                                <div class="text-muted small mt-1"><i class="bi bi-calendar3 me-1"></i> {{ formatDate(inv.invoice_date) }}</div>
                                            </div>
                                            <span class="badge" :class="invoiceStatusClass(inv.payment_status)">
                                                {{ String(inv.payment_status || '').replace(/_/g, ' ').toUpperCase() }}
                                            </span>
                                        </div>
                                        
                                        <div class="row g-2 text-sm mt-2">
                                            <div class="col-4">
                                                <div class="text-muted" style="font-size: 0.75rem;">TOTAL</div>
                                                <div class="fw-semibold">₹ {{ formatMoney(inv.total_amount) }}</div>
                                            </div>
                                            <div class="col-4">
                                                <div class="text-muted" style="font-size: 0.75rem;">PAID</div>
                                                <div class="text-success fw-semibold">₹ {{ formatMoney(inv.paid_amount || 0) }}</div>
                                            </div>
                                            <div class="col-4">
                                                <div class="text-muted" style="font-size: 0.75rem;">BALANCE</div>
                                                <div class="text-danger fw-semibold">₹ {{ formatMoney((inv.total_amount || 0) - (inv.paid_amount || 0)) }}</div>
                                            </div>
                                        </div>

                                        <template v-if="inv.payments && inv.payments.length > 0">
                                            <hr class="my-3 text-black-50">
                                            <div class="text-muted small fw-semibold mb-2">PAYMENT HISTORY</div>
                                            <div class="d-flex flex-column gap-2">
                                                <div v-for="payment in inv.payments" :key="payment.id" class="d-flex justify-content-between align-items-center text-sm border p-2 rounded bg-body-tertiary">
                                                    <div>
                                                        <div class="fw-medium">{{ formatDate(payment.paid_at) }}</div>
                                                        <div class="text-muted" style="font-size: 0.75rem;">{{ String(payment.payment_method || '').replace(/_/g, ' ').toUpperCase() }}</div>
                                                    </div>
                                                    <div class="text-end">
                                                        <div class="text-success fw-semibold">₹ {{ formatMoney(payment.amount) }}</div>
                                                        <div v-if="Number(payment.tds_amount) > 0 || Number(payment.deduction_amount) > 0" class="text-muted" style="font-size: 0.75rem;">
                                                            (+ ₹{{ formatMoney((Number(payment.tds_amount) || 0) + (Number(payment.deduction_amount) || 0)) }} ded.)
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </template>
                                    </li>
                                </ul>
                            </template>
                            <p v-else class="na-text p-3 mb-0">No invoices generated.</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="row">
                <div class="col-12 mb-4">
                    <div class="card shadow-sm charge-card">
                        <div class="card-header detail-section-header rounded-top bg-primary text-white">
                            <i class="fas fa-chart-pie me-2"></i> SUMMARY
                        </div>
                        <div class="card-body p-3">
                            <div class="row text-center">
                                <div class="col-sm-4 mb-3 mb-sm-0 border-end">
                                    <div class="detail-label">Total Freight</div>
                                    <div class="detail-value fs-5 text-success">₹ {{ formatMoney(detail.trip.total_freight_amount) }}</div>
                                </div>
                                <div class="col-sm-4 mb-3 mb-sm-0 border-end">
                                    <div class="detail-label">Total Expenses</div>
                                    <div class="detail-value fs-5 text-danger">₹ {{ formatMoney(detail.trip.total_expense_amount) }}</div>
                                </div>
                                <div class="col-sm-4">
                                    <div class="detail-label">Estimated Profit</div>
                                    <div class="detail-value fs-4 fw-bold" :class="profitClass(profitAmount)">
                                        ₹ {{ formatMoney(profitAmount) }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow-sm border-0" v-if="!detail && trips && trips.length > 0">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-striped table-hover align-middle mb-0 app-index-table">
                        <thead class="table-light">
                            <tr>
                                <th>Trip No</th>
                                <th class="d-none d-sm-table-cell">Date</th>
                                <th class="d-none d-md-table-cell">Vehicle</th>
                                <th>Driver</th>
                                <th class="d-none d-lg-table-cell">Customer</th>
                                <th class="d-none d-sm-table-cell">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr 
                                v-for="t in trips" 
                                :key="t.id" 
                                class="cursor-pointer"
                                title="Click to view details" 
                                @click="selectTrip(t)"
                            >
                                <td class="fw-semibold text-primary">
                                    <RouterLink :to="{ name: 'trips.show', params: { id: t.id } }" @click.stop class="text-decoration-none">
                                        {{ t.trip_number || 'N/A' }}
                                    </RouterLink>
                                </td>
                                <td class="text-nowrap d-none d-sm-table-cell">{{ formatDate(t.start_at) }}</td>
                                <td class="text-nowrap d-none d-md-table-cell text-uppercase">{{ t.vehicle?.registration_number || 'N/A' }}</td>
                                <td>{{ t.driver?.name || 'N/A' }}</td>
                                <td class="d-none d-lg-table-cell">{{ t.customer?.name || 'N/A' }}</td>
                                <td class="d-none d-sm-table-cell">
                                    <span class="badge" :class="statusClass(t.status)">
                                        {{ String(t.status || '').replace(/_/g, ' ').toUpperCase() }}
                                    </span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <div v-else-if="!detail && localQuery && !loading && trips && trips.length === 0" class="text-center py-5 text-muted">
            <i class="fas fa-search fa-3x mb-3 text-light"></i>
            <p>No trips found matching "{{ localQuery }}"</p>
        </div>
    </div>
</template>

<script setup>
import { ref, watch, computed, onMounted, onBeforeUnmount } from 'vue';
import { useRouter, useRoute, RouterLink } from 'vue-router';
import api from '../api';
import { formatDisplay } from '../utils/datetime';

const router = useRouter();
const route = useRoute();

const localQuery = ref(route.query.q || '');
const trips = ref([]);
const detail = ref(null);
const loading = ref(false);
const searchInputRef = ref(null);

let debounceTimer = null;

const profitAmount = computed(() => {
    if (!detail.value?.trip) return 0;
    const freight = Number(detail.value.trip.total_freight_amount || 0);
    const custom = Number(detail.value.trip.total_custom_amount || 0);
    const expense = Number(detail.value.trip.total_expense_amount || 0);
    return freight + custom - expense;
});

const performSearch = async (query) => {
    loading.value = true;
    try {
        const { data } = await api.get('/app/api/quick-search', { params: { q: query } });
        trips.value = data.trips || [];
        detail.value = data.detail || null;
    } catch (e) {
        console.error(e);
        trips.value = [];
        detail.value = null;
    } finally {
        loading.value = false;
    }
};

watch(localQuery, (val) => {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => {
        const query = val.trim();
        if (query) {
            router.replace({ name: 'quick-search', query: { q: query } });
            performSearch(query);
        } else {
            router.replace({ name: 'quick-search' });
            trips.value = [];
            detail.value = null;
        }
    }, 400);
});

watch(() => route.query.q, (val) => {
    if (val !== localQuery.value) {
        localQuery.value = val || '';
    }
});

onMounted(() => {
    if (localQuery.value) {
        performSearch(localQuery.value);
    }
    window.addEventListener('keydown', handleGlobalKeydown);
});

onBeforeUnmount(() => {
    window.removeEventListener('keydown', handleGlobalKeydown);
});

const handleGlobalKeydown = (e) => {
    if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
        e.preventDefault();
        searchInputRef.value?.focus();
    }
};

const clearSearch = () => {
    localQuery.value = '';
    searchInputRef.value?.focus();
};

const selectTrip = (t) => {
    if (!t?.trip_number) return;
    localQuery.value = t.trip_number;
};

const formatDate = (dateStr) => {
    if (!dateStr) return 'N/A';
    return formatDisplay(dateStr, 'date', 'N/A');
};

const statusClass = (status) => {
    const s = String(status || '').toLowerCase();
    return {
        'bg-warning text-dark': s === 'pending',
        'bg-info': s === 'in_progress',
        'bg-success': s === 'completed' || s === 'invoiced',
        'bg-danger': s === 'cancelled',
        'bg-secondary': !['pending', 'in_progress', 'completed', 'invoiced', 'cancelled'].includes(s),
    };
};

const invoiceStatusClass = (status) => {
    const s = String(status || '').toLowerCase();
    return {
        'bg-success': s === 'paid',
        'bg-warning text-dark': s === 'unpaid' || s === 'partially_paid',
        'bg-danger': s === 'cancelled',
    };
};

const profitClass = (amount) => {
    if (amount > 0) return 'text-success';
    if (amount < 0) return 'text-danger';
    return 'text-muted';
};

const formatType = (val) => {
    if (!val) return 'N/A';
    return String(val).replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase());
};

const formatMoney = (value) => {
    if (value === null || value === undefined || value === '') return '0.00';
    const num = Number(value);
    if (isNaN(num)) return String(value);
    return num.toLocaleString('en-IN', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });
};
</script>

<style scoped>
.quick-search-page {
    padding-bottom: 2rem;
}
.cursor-pointer {
    cursor: pointer;
}
.charge-card {
    border: 1px solid var(--bs-border-color);
}
.detail-section-header {
    font-size: 0.875rem;
    font-weight: 600;
}
.detail-section-header.bg-primary {
    background: var(--brand-gradient, #0d6efd) !important;
    color: #ffffff !important;
}
.detail-label {
    font-size: 0.75rem;
    color: var(--bs-secondary-color);
    text-transform: uppercase;
    letter-spacing: 0.04em;
    margin-bottom: 0.15rem;
}
.detail-value {
    font-size: 0.9rem;
    font-weight: 500;
    color: var(--bs-body-color);
}
.na-text {
    font-size: 0.85rem;
    color: var(--bs-tertiary-color);
    font-style: italic;
}
</style>
