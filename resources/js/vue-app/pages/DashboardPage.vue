<template>
    <div v-if="loading" class="py-5 text-center text-muted">Loading dashboard…</div>

    <div v-else-if="loadError" class="alert alert-danger">{{ loadError }}</div>

    <template v-else>
        <div class="row mb-4 align-items-center">
            <div class="col-md-8">
                <h2 class="fw-bold mb-1">
                    <i class="fas fa-grid-2 text-brand-primary"></i> Dashboard
                </h2>
                <p class="text-muted mb-0">Welcome back! Here's what's happening today.</p>
            </div>
            <div class="col-md-4 text-md-end">
                <div class="badge bg-brand-active text-brand-primary px-3 py-2 rounded-pill">
                    <i class="fas fa-calendar-alt me-1"></i> {{ todayLabel }}
                </div>
            </div>
        </div>

        <div class="row g-3">
            <div v-for="card in kpiCards" :key="card.key" class="col-12 col-sm-6 col-md-3">
                <div class="card glass-card info-box">
                    <div class="card-body p-0 d-flex align-items-center">
                        <div :class="['icon-circle', card.iconBg, 'me-3', 'shadow-sm']">
                            <i :class="[card.icon, card.iconColor]"></i>
                        </div>
                        <div>
                            <h6 class="text-muted fw-semi-bold mb-1">{{ card.title }}</h6>
                            <h3 class="fw-bold mb-1">{{ formatValue(card) }}</h3>
                            <span :class="['badge', card.badgeClass, 'rounded-pill']">
                                <i v-if="card.badgeIcon" :class="[card.badgeIcon, 'me-1']"></i>
                                {{ card.badge }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div v-if="paymentStatuses" class="row mt-4 mb-4">
            <div class="col-md-12">
                <div class="card glass-card">
                    <div class="card-header bg-transparent border-0 py-3">
                        <h5 class="mb-0 fw-bold text-dark">
                            <i class="fas fa-chart-pie text-brand-secondary me-2"></i>
                            Payment Status Overview
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row text-center">
                            <div class="col-md-4 border-end">
                                <div class="p-3">
                                    <h2 class="text-warning fw-bold mb-1">
                                        {{ formatNumber(paymentStatuses.pending) }}
                                    </h2>
                                    <p class="text-muted mb-0 fw-medium">Pending</p>
                                    <div class="progress mt-2" style="height: 6px; border-radius: 3px">
                                        <div class="progress-bar bg-warning" style="width: 100%"></div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4 border-end">
                                <div class="p-3">
                                    <h2 class="text-info fw-bold mb-1">
                                        {{ formatNumber(paymentStatuses.partial) }}
                                    </h2>
                                    <p class="text-muted mb-0 fw-medium">Partial</p>
                                    <div class="progress mt-2" style="height: 6px; border-radius: 3px">
                                        <div class="progress-bar bg-info" style="width: 100%"></div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="p-3">
                                    <h2 class="text-success fw-bold mb-1">
                                        {{ formatNumber(paymentStatuses.paid) }}
                                    </h2>
                                    <p class="text-muted mb-0 fw-medium">Paid</p>
                                    <div class="progress mt-2" style="height: 6px; border-radius: 3px">
                                        <div class="progress-bar bg-success" style="width: 100%"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </template>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import api from '../api';

const loading = ref(true);
const loadError = ref('');
const monthlyStats = ref(null);
const paymentStatuses = ref(null);

const todayLabel = new Date().toLocaleDateString('en-GB', {
    day: 'numeric',
    month: 'short',
    year: 'numeric',
});

const kpiCards = computed(() => {
    const stats = monthlyStats.value ?? {};

    return [
        {
            key: 'total_bookings',
            title: 'Total Bookings',
            value: stats.total_bookings,
            decimals: 0,
            icon: 'fas fa-truck',
            iconBg: 'bg-soft-primary',
            iconColor: 'text-primary',
            badge: 'Monthly',
            badgeClass: 'bg-soft-success text-success',
            badgeIcon: 'fas fa-arrow-up',
        },
        {
            key: 'active_trips',
            title: 'Active Trips',
            value: stats.active_trips,
            decimals: 0,
            icon: 'fas fa-route',
            iconBg: 'bg-soft-success',
            iconColor: 'text-success',
            badge: 'In Progress',
            badgeClass: 'bg-soft-info text-info',
        },
        {
            key: 'pending_lrs',
            title: 'Pending LRs',
            value: stats.pending_lrs,
            decimals: 0,
            icon: 'fas fa-clock',
            iconBg: 'bg-soft-warning',
            iconColor: 'text-warning',
            badge: 'Pending',
            badgeClass: 'bg-soft-warning text-warning',
        },
        {
            key: 'delivered_today',
            title: 'Delivered Today',
            value: stats.delivered_today,
            decimals: 0,
            icon: 'fas fa-check-circle',
            iconBg: 'bg-soft-info',
            iconColor: 'text-info',
            badge: 'Today',
            badgeClass: 'bg-soft-success text-success',
        },
        {
            key: 'lrs_pending_invoice',
            title: 'Pending Invoice',
            value: stats.lrs_pending_invoice,
            decimals: 0,
            icon: 'fas fa-file-invoice',
            iconBg: 'bg-soft-danger',
            iconColor: 'text-danger',
            badge: 'Not Invoiced',
            badgeClass: 'bg-soft-danger text-danger',
        },
        {
            key: 'eway_bills_expiring_today',
            title: 'E-Way Expiring',
            value: stats.eway_bills_expiring_today,
            decimals: 0,
            icon: 'fas fa-exclamation-triangle',
            iconBg: 'bg-soft-warning',
            iconColor: 'text-warning',
            badge: 'Expiring Today',
            badgeClass: 'bg-soft-warning text-warning',
        },
        {
            key: 'expired_eway_bills',
            title: 'Expired E-Way',
            value: stats.expired_eway_bills,
            decimals: 0,
            icon: 'fas fa-times-circle',
            iconBg: 'bg-soft-danger',
            iconColor: 'text-danger',
            badge: 'Urgent Action',
            badgeClass: 'bg-soft-danger text-danger',
        },
        {
            key: 'outstanding_amount',
            title: 'Outstanding Amount',
            value: stats.outstanding_amount,
            decimals: 2,
            icon: 'fas fa-indian-rupee-sign',
            iconBg: 'bg-soft-warning',
            iconColor: 'text-warning',
            badge: 'Invoice Balance',
            badgeClass: 'bg-soft-warning text-warning',
        },
    ];
});

function formatNumber(value) {
    return new Intl.NumberFormat('en-IN').format(Number(value ?? 0));
}

function formatValue(card) {
    const num = Number(card.value ?? 0);

    if (card.decimals > 0) {
        return new Intl.NumberFormat('en-IN', {
            minimumFractionDigits: card.decimals,
            maximumFractionDigits: card.decimals,
        }).format(num);
    }

    return formatNumber(num);
}

async function loadDashboard() {
    loading.value = true;
    loadError.value = '';

    try {
        const { data } = await api.get('/app/dashboard/stats');
        monthlyStats.value = data.monthlyStats;
        paymentStatuses.value = data.invoicePaymentStatuses;
    } catch {
        loadError.value = 'Could not load dashboard data.';
    } finally {
        loading.value = false;
    }
}

onMounted(loadDashboard);
</script>
