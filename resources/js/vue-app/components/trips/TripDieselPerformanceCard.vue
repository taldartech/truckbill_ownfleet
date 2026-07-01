<template>
    <div
        v-if="performance"
        class="card mb-4 trip-performance-card"
        :class="themeClass"
    >
        <div class="trip-performance-card__header">
            <h3 class="trip-performance-card__title mb-0">
                <i class="bi bi-speedometer2 me-2" aria-hidden="true"></i>Expected vs Actual Performance
            </h3>
            <span v-if="hasActual" class="trip-performance-card__badge">
                <i :class="badgeIcon" aria-hidden="true"></i>
                {{ badgeLabel }}
            </span>
        </div>

        <div class="trip-performance-card__body">
            <div class="trip-performance-metrics">
                <div class="trip-performance-metrics__item">
                    <div class="trip-performance-metrics__label">Expected Running</div>
                    <div class="trip-performance-metrics__value">{{ formatKm(performance.expected_total_km) }}</div>
                </div>

                <div class="trip-performance-metrics__divider" aria-hidden="true"></div>

                <div class="trip-performance-metrics__item">
                    <div class="trip-performance-metrics__label">Actual Running</div>
                    <div class="trip-performance-metrics__value">{{ formatKm(performance.actual_total_km) }}</div>
                </div>

                <div class="trip-performance-metrics__divider" aria-hidden="true"></div>

                <div class="trip-performance-metrics__item">
                    <div class="trip-performance-metrics__label">Difference KM</div>
                    <div class="trip-performance-metrics__value" :class="valueToneClass">{{ differenceKmDisplay }}</div>
                </div>

                <div class="trip-performance-metrics__divider" aria-hidden="true"></div>

                <div class="trip-performance-metrics__item">
                    <div class="trip-performance-metrics__label">Rate / KM</div>
                    <div class="trip-performance-metrics__value">{{ formatTripRate(performance.rate_per_km) }}</div>
                </div>

                <div class="trip-performance-metrics__divider trip-performance-metrics__divider--amount" aria-hidden="true"></div>

                <div class="trip-performance-metrics__item trip-performance-metrics__item--amount">
                    <div class="trip-performance-metrics__label">Difference Amount</div>
                    <div class="trip-performance-metrics__value" :class="amountToneClass">{{ differenceAmountDisplay }}</div>
                </div>
            </div>

            <div v-if="statusMessage" class="trip-performance-status" :class="statusBoxClass">
                <i :class="statusIcon" aria-hidden="true"></i>
                <div>
                    <div class="trip-performance-status__title">{{ statusTitle }}</div>
                    <div v-if="statusSubtitle" class="trip-performance-status__subtitle">{{ statusSubtitle }}</div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
    performance: { type: Object, default: null },
});

const hasActual = computed(() => props.performance?.actual_total_km != null && props.performance.actual_total_km > 0);
const isUnder = computed(() => hasActual.value && props.performance.performance_status === 'under');
const isOver = computed(() => hasActual.value && props.performance.performance_status === 'over');
const isWithin = computed(() => hasActual.value && props.performance.performance_status === 'within');

const themeClass = computed(() => {
    if (!hasActual.value) {
        return 'trip-performance-card--pending';
    }

    if (isUnder.value) {
        return 'trip-performance-card--under';
    }

    if (isWithin.value) {
        return 'trip-performance-card--within';
    }

    return 'trip-performance-card--over';
});

const badgeLabel = computed(() => {
    if (isUnder.value) {
        return 'Actual is less than expected';
    }

    if (isWithin.value) {
        return 'Actual is equal to expected';
    }

    return 'Actual is more than expected';
});

const badgeIcon = computed(() => {
    if (isUnder.value) {
        return 'bi bi-exclamation-circle-fill';
    }

    if (isWithin.value) {
        return 'bi bi-dash-circle-fill';
    }

    return 'bi bi-check-circle-fill';
});

const differenceKmDisplay = computed(() => {
    if (!hasActual.value) {
        return '—';
    }

    if (isUnder.value) {
        return formatKm(props.performance.unused_km);
    }

    if (isOver.value) {
        return `+${formatNumber(props.performance.extra_km)} KM`;
    }

    return '0 KM';
});

const differenceAmountDisplay = computed(() => {
    if (!hasActual.value) {
        return '—';
    }

    if (isUnder.value) {
        return formatCost(props.performance.remaining_amount);
    }

    return formatCost(0);
});

const valueToneClass = computed(() => {
    if (!hasActual.value) {
        return 'trip-performance-metrics__value--muted';
    }

    if (isUnder.value) {
        return 'trip-performance-metrics__value--under';
    }

    if (isOver.value) {
        return 'trip-performance-metrics__value--over';
    }

    return 'trip-performance-metrics__value--within';
});

const amountToneClass = computed(() => {
    if (!hasActual.value) {
        return 'trip-performance-metrics__value--muted';
    }

    if (isUnder.value) {
        return 'trip-performance-metrics__value--under';
    }

    if (isOver.value) {
        return 'trip-performance-metrics__value--over';
    }

    return 'trip-performance-metrics__value--within';
});

const statusTitle = computed(() => {
    if (!hasActual.value) {
        return 'Actual running KM will appear after trip completion with end meter reading.';
    }

    if (isUnder.value) {
        return 'Customer has unused booked KM.';
    }

    if (isWithin.value) {
        return 'Perfect Trip.';
    }

    return 'Truck exceeded expected running.';
});

const statusSubtitle = computed(() => {
    if (!hasActual.value) {
        return '';
    }

    if (isUnder.value) {
        return `Remaining amount payable: ${formatCost(props.performance.remaining_amount)}`;
    }

    return 'No remaining amount payable.';
});

const statusMessage = computed(() => statusTitle.value);

const statusBoxClass = computed(() => {
    if (!hasActual.value) {
        return 'trip-performance-status--pending';
    }

    if (isUnder.value) {
        return 'trip-performance-status--under';
    }

    if (isWithin.value) {
        return 'trip-performance-status--within';
    }

    return 'trip-performance-status--over';
});

const statusIcon = computed(() => {
    if (!hasActual.value) {
        return 'bi bi-info-circle';
    }

    if (isUnder.value) {
        return 'bi bi-exclamation-circle-fill';
    }

    if (isWithin.value) {
        return 'bi bi-info-circle-fill';
    }

    return 'bi bi-check-circle-fill';
});

function formatNumber(value) {
    if (value == null || value === '') {
        return '—';
    }

    return Number(value).toLocaleString('en-IN', { maximumFractionDigits: 2 });
}

function formatKm(value) {
    if (value == null || value === '') {
        return '—';
    }

    return `${formatNumber(value)} KM`;
}

function formatTripRate(value) {
    if (value == null || value === '') {
        return '—';
    }

    return `₹${formatNumber(value)}`;
}

function formatCost(value) {
    if (value == null || value === '') {
        return '—';
    }

    return `₹${formatNumber(value)}`;
}
</script>
