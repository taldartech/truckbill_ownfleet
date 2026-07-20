<template>
    <div
        v-if="performance"
        class="card trip-performance-card"
        :class="[themeClass, { 'trip-performance-card--compact': compact, 'mb-4': !compact }]"
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
                    <div class="trip-performance-metrics__label trip-performance-metrics__label--with-info">
                        <span>Expected Running</span>
                        <button
                            type="button"
                            class="trip-performance-info-btn"
                            :aria-expanded="activeInfoKey === 'expected'"
                            aria-label="How Expected Running is calculated"
                            @click.stop="toggleMetricInfo('expected', $event)"
                        >
                            <i class="bi bi-info-circle" aria-hidden="true"></i>
                        </button>
                    </div>
                    <div class="trip-performance-metrics__value">{{ formatKm(performance.expected_total_km) }}</div>
                </div>

                <div class="trip-performance-metrics__divider" aria-hidden="true"></div>

                <div class="trip-performance-metrics__item">
                    <div class="trip-performance-metrics__label trip-performance-metrics__label--with-info">
                        <span>Actual Running</span>
                        <button
                            type="button"
                            class="trip-performance-info-btn"
                            :aria-expanded="activeInfoKey === 'actual'"
                            aria-label="How Actual Running is calculated"
                            @click.stop="toggleMetricInfo('actual', $event)"
                        >
                            <i class="bi bi-info-circle" aria-hidden="true"></i>
                        </button>
                    </div>
                    <div class="trip-performance-metrics__value">{{ formatKm(performance.actual_total_km) }}</div>
                </div>

                <div class="trip-performance-metrics__divider" aria-hidden="true"></div>

                <div class="trip-performance-metrics__item">
                    <div class="trip-performance-metrics__label trip-performance-metrics__label--with-info">
                        <span>Difference KM</span>
                        <button
                            type="button"
                            class="trip-performance-info-btn"
                            :aria-expanded="activeInfoKey === 'difference-km'"
                            aria-label="How Difference KM is calculated"
                            @click.stop="toggleMetricInfo('difference-km', $event)"
                        >
                            <i class="bi bi-info-circle" aria-hidden="true"></i>
                        </button>
                    </div>
                    <div class="trip-performance-metrics__value" :class="valueToneClass">{{ differenceKmDisplay }}</div>
                </div>

                <div class="trip-performance-metrics__divider" aria-hidden="true"></div>

                <div class="trip-performance-metrics__item">
                    <div class="trip-performance-metrics__label trip-performance-metrics__label--with-info">
                        <span>Difference LTR</span>
                        <button
                            type="button"
                            class="trip-performance-info-btn"
                            :aria-expanded="activeInfoKey === 'difference-ltr'"
                            aria-label="How Difference LTR is calculated"
                            @click.stop="toggleMetricInfo('difference-ltr', $event)"
                        >
                            <i class="bi bi-info-circle" aria-hidden="true"></i>
                        </button>
                    </div>
                    <div class="trip-performance-metrics__value" :class="valueToneClass">{{ differenceLtrDisplay }}</div>
                </div>

                <div class="trip-performance-metrics__divider" aria-hidden="true"></div>

                <div class="trip-performance-metrics__item trip-performance-metrics__item--rate">
                    <div class="trip-performance-metrics__label trip-performance-metrics__label--with-info">
                        <span>Rate / KM</span>
                        <button
                            type="button"
                            class="trip-performance-info-btn"
                            :aria-expanded="activeInfoKey === 'rate'"
                            aria-label="How Rate / KM is calculated"
                            @click.stop="toggleMetricInfo('rate', $event)"
                        >
                            <i class="bi bi-info-circle" aria-hidden="true"></i>
                        </button>
                    </div>
                    <div class="trip-performance-metrics__value">{{ formatTripRate(performance.rate_per_km) }}</div>
                </div>

                <div class="trip-performance-metrics__divider trip-performance-metrics__divider--amount" aria-hidden="true"></div>

                <div class="trip-performance-metrics__item trip-performance-metrics__item--amount">
                    <div class="trip-performance-metrics__label trip-performance-metrics__label--with-info">
                        <span>Difference Amount</span>
                        <button
                            type="button"
                            class="trip-performance-info-btn"
                            :aria-expanded="activeInfoKey === 'difference-amount'"
                            aria-label="How Difference Amount is calculated"
                            @click.stop="toggleMetricInfo('difference-amount', $event)"
                        >
                            <i class="bi bi-info-circle" aria-hidden="true"></i>
                        </button>
                    </div>
                    <div class="trip-performance-metrics__value" :class="amountToneClass">{{ differenceAmountDisplay }}</div>
                </div>
            </div>

            <Teleport to="body">
                <div
                    v-if="activeInfoKey && activeMetricInfo"
                    ref="infoPopoverRef"
                    class="trip-performance-info-popover shadow-sm"
                    :style="infoPopoverStyle"
                    role="dialog"
                    :aria-label="activeMetricInfo.title"
                    @click.stop
                >
                    <div class="trip-performance-info-popover__title">{{ activeMetricInfo.title }}</div>
                    <ol v-if="activeMetricInfo.steps.length" class="trip-performance-info-popover__steps">
                        <li v-for="(step, index) in activeMetricInfo.steps" :key="index">{{ step }}</li>
                    </ol>
                    <p v-else class="trip-performance-info-popover__empty mb-0">
                        {{ activeMetricInfo.emptyMessage }}
                    </p>
                </div>
            </Teleport>

            <div
                v-if="statusMessage"
                class="trip-performance-status"
                :class="[statusBoxClass, { 'trip-performance-status--compact': compact }]"
            >
                <i :class="statusIcon" aria-hidden="true"></i>
                <div class="trip-performance-status__content">
                    <span class="trip-performance-status__title">{{ statusTitle }}</span>
                    <span v-if="statusSubtitle" class="trip-performance-status__subtitle">{{ statusSubtitle }}</span>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, nextTick, onMounted, onUnmounted, ref } from 'vue';

const props = defineProps({
    performance: { type: Object, default: null },
    compact: { type: Boolean, default: false },
});

const activeInfoKey = ref(null);
const infoPopoverRef = ref(null);
const infoPopoverStyle = ref({});
let activeInfoButton = null;

const hasActual = computed(() => props.performance?.actual_total_km != null && props.performance.actual_total_km > 0);
const isUnder = computed(() => hasActual.value && props.performance.performance_status === 'under');
const isOver = computed(() => hasActual.value && props.performance.performance_status === 'over');
const isWithin = computed(() => hasActual.value && props.performance.performance_status === 'within');

function updateInfoPopoverPosition() {
    const button = activeInfoButton;

    if (!button) {
        return;
    }

    const rect = button.getBoundingClientRect();
    const popoverWidth = Math.min(340, window.innerWidth * 0.9);
    const centeredLeft = rect.left + rect.width / 2 - popoverWidth / 2;
    const left = Math.max(12, Math.min(centeredLeft, window.innerWidth - popoverWidth - 12));

    infoPopoverStyle.value = {
        position: 'fixed',
        top: `${rect.bottom + 8}px`,
        left: `${left}px`,
        width: `${popoverWidth}px`,
        zIndex: '2101',
    };
}

function toggleMetricInfo(key, event) {
    if (activeInfoKey.value === key) {
        activeInfoKey.value = null;
        activeInfoButton = null;

        return;
    }

    activeInfoKey.value = key;
    activeInfoButton = event.currentTarget;
    nextTick(() => updateInfoPopoverPosition());
}

function closeMetricInfo() {
    activeInfoKey.value = null;
    activeInfoButton = null;
}

function onDocumentClick(event) {
    const target = event.target;

    if (infoPopoverRef.value?.contains(target)) {
        return;
    }

    if (target.closest?.('.trip-performance-info-btn')) {
        return;
    }

    closeMetricInfo();
}

function onReposition() {
    if (activeInfoKey.value) {
        updateInfoPopoverPosition();
    }
}

onMounted(() => {
    document.addEventListener('click', onDocumentClick);
    window.addEventListener('resize', onReposition);
    window.addEventListener('scroll', onReposition, true);
});

onUnmounted(() => {
    document.removeEventListener('click', onDocumentClick);
    window.removeEventListener('resize', onReposition);
    window.removeEventListener('scroll', onReposition, true);
});

const activeMetricInfo = computed(() => {
    const performance = props.performance;
    const key = activeInfoKey.value;

    if (!performance || !key) {
        return null;
    }

    const plannedDiesel = Number(performance.planned_diesel_liters ?? 0);
    const mileage = Number(performance.planned_mileage_km_per_liter ?? 0);
    const expectedKm = Number(performance.expected_total_km ?? 0);
    const dieselRate = Number(performance.diesel_rate_per_liter ?? 0);
    const totalCost = Number(performance.total_trip_cost ?? 0);
    const actualKm = Number(performance.actual_total_km ?? 0);

    if (key === 'expected') {
        if (!plannedDiesel || !mileage) {
            return {
                title: 'Expected Running KM',
                steps: [],
                emptyMessage: 'Enter Planned Diesel and Expected Mileage in trip details.',
            };
        }

        return {
            title: 'Expected Running KM',
            steps: [
                'Planned Diesel × Expected Mileage (trip details)',
                `${formatLiters(plannedDiesel)} × ${formatNumber(mileage)} KM/Ltr = ${formatKm(expectedKm)}`,
            ],
            emptyMessage: '',
        };
    }

    if (key === 'actual') {
        return {
            title: 'Actual Running KM',
            steps: hasActual.value
                ? ['End Meter Reading − Start Meter Reading', `= ${formatKm(actualKm)}`]
                : [],
            emptyMessage: 'Shown after start and end meter readings are entered.',
        };
    }

    if (key === 'difference-km') {
        if (!hasActual.value) {
            return {
                title: 'Difference KM',
                steps: [],
                emptyMessage: 'Available after actual running KM is known.',
            };
        }

        if (isUnder.value) {
            const unusedKm = Number(performance.unused_km ?? 0);

            return {
                title: 'Difference KM',
                steps: [
                    'Expected Running − Actual Running',
                    `${formatNumber(expectedKm)} − ${formatNumber(actualKm)} = ${formatNumber(unusedKm)} KM`,
                ],
                emptyMessage: '',
            };
        }

        if (isOver.value) {
            const extraKm = Number(performance.extra_km ?? 0);

            return {
                title: 'Difference KM',
                steps: [
                    'Actual Running − Expected Running',
                    `${formatNumber(actualKm)} − ${formatNumber(expectedKm)} = +${formatNumber(extraKm)} KM`,
                ],
                emptyMessage: '',
            };
        }

        return {
            title: 'Difference KM',
            steps: ['Expected Running − Actual Running = 0 KM'],
            emptyMessage: '',
        };
    }

    if (key === 'difference-ltr') {
        if (!hasActual.value) {
            return {
                title: 'Difference LTR',
                steps: [],
                emptyMessage: 'Available after actual running KM is known.',
            };
        }

        if (isUnder.value) {
            const unusedKm = Number(performance.unused_km ?? 0);
            const unusedDiesel = Number(performance.unused_diesel_liters ?? 0);

            return {
                title: 'Difference LTR',
                steps: [
                    'Difference KM ÷ Expected Mileage',
                    `${formatNumber(unusedKm)} KM ÷ ${formatNumber(mileage)} KM/Ltr = ${formatLiters(unusedDiesel)}`,
                ],
                emptyMessage: '',
            };
        }

        if (isOver.value) {
            const extraKm = Number(performance.extra_km ?? 0);
            const extraDiesel = Number(performance.extra_diesel_liters ?? 0);

            return {
                title: 'Difference LTR',
                steps: [
                    'Difference KM ÷ Expected Mileage',
                    `${formatNumber(extraKm)} KM ÷ ${formatNumber(mileage)} KM/Ltr = +${formatLiters(extraDiesel)}`,
                ],
                emptyMessage: '',
            };
        }

        return {
            title: 'Difference LTR',
            steps: ['Difference KM ÷ Expected Mileage = 0 Ltr'],
            emptyMessage: '',
        };
    }

    if (key === 'rate') {
        if (!performance.rate_per_km) {
            return {
                title: 'Rate / KM',
                steps: [],
                emptyMessage: 'Enter Planned Diesel and Diesel Rate (₹/Ltr) in trip details.',
            };
        }

        return {
            title: 'Rate / KM',
            steps: [
                'Total Trip Cost ÷ Expected Running KM',
                `Total Trip Cost = Planned Diesel × Diesel Rate = ${formatLiters(plannedDiesel)} × ${formatTripRate(dieselRate)}/Ltr = ${formatCost(totalCost)}`,
                `Rate / KM = ${formatCost(totalCost)} ÷ ${formatNumber(expectedKm)} KM = ${formatTripRate(performance.rate_per_km)}/KM`,
            ],
            emptyMessage: '',
        };
    }

    if (key === 'difference-amount') {
        if (!hasActual.value) {
            return {
                title: 'Difference Amount',
                steps: [],
                emptyMessage: 'Available after actual running KM is known.',
            };
        }

        if (isUnder.value && performance.remaining_amount != null) {
            const unusedKm = Number(performance.unused_km ?? 0);

            return {
                title: 'Difference Amount',
                steps: [
                    'Difference KM × Rate / KM',
                    `${formatNumber(unusedKm)} KM × ${formatTripRate(performance.rate_per_km)} = ${formatCost(performance.remaining_amount)}`,
                ],
                emptyMessage: '',
            };
        }

        return {
            title: 'Difference Amount',
            steps: ['No remaining amount when actual running meets or exceeds expected running.'],
            emptyMessage: '',
        };
    }

    return null;
});

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

const differenceLtrDisplay = computed(() => {
    if (!hasActual.value) {
        return '—';
    }

    if (isUnder.value) {
        return formatLiters(props.performance.unused_diesel_liters);
    }

    if (isOver.value) {
        return `+${formatNumber(props.performance.extra_diesel_liters)} Ltr`;
    }

    return '0 Ltr';
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

function formatLiters(value) {
    if (value == null || value === '') {
        return '—';
    }

    return `${formatNumber(value)} Ltr`;
}
</script>
