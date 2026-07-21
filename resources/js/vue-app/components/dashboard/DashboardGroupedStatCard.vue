<template>
    <article
        class="dashboard-stat-card"
        :class="[
            `dashboard-stat-card--${tone}`,
            { 'dashboard-stat-card--clickable': isInteractive },
        ]"
        :role="isInteractive ? 'button' : undefined"
        :tabindex="isInteractive ? 0 : undefined"
        @click="onClick"
        @keydown.enter.prevent="onClick"
        @keydown.space.prevent="onClick"
    >
        <div class="dashboard-stat-card__top">
            <div class="dashboard-stat-card__icon-wrap">
                <i :class="icon" aria-hidden="true"></i>
            </div>
            <div class="dashboard-stat-card__headline">
                <h3 class="dashboard-stat-card__title">{{ title }}</h3>
                <div class="dashboard-stat-card__total-row">
                    <span class="dashboard-stat-card__total">{{ formatValue(total, format) }}</span>
                    <span class="dashboard-stat-card__total-label">{{ totalLabel }}</span>
                </div>
            </div>
            <i v-if="isInteractive" class="fas fa-arrow-up-right-from-square dashboard-stat-card__action" aria-hidden="true"></i>
        </div>

        <div v-if="items.length" class="dashboard-stat-card__metrics">
            <div
                v-for="item in items"
                :key="item.key"
                class="dashboard-stat-card__metric"
                :class="{ 'dashboard-stat-card__metric--clickable': Boolean(item.to) }"
                :role="item.to ? 'button' : undefined"
                :tabindex="item.to ? 0 : undefined"
                @click="onItemClick(item, $event)"
                @keydown.enter.prevent="onItemClick(item, $event)"
                @keydown.space.prevent="onItemClick(item, $event)"
            >
                <span class="dashboard-stat-card__metric-value">{{ formatValue(item.value, item.format ?? 'number') }}</span>
                <span class="dashboard-stat-card__metric-label">{{ item.label }}</span>
            </div>
        </div>

        <p v-if="hint" class="dashboard-stat-card__hint">{{ hint }}</p>
    </article>
</template>

<script setup>
import { computed } from 'vue';
import { useRouter } from 'vue-router';
import { formatMoney } from '../../utils/formatMoney';

const router = useRouter();

const props = defineProps({
    title: {
        type: String,
        required: true,
    },
    total: {
        type: Number,
        default: 0,
    },
    totalLabel: {
        type: String,
        default: 'Total',
    },
    icon: {
        type: String,
        default: 'fas fa-chart-simple',
    },
    tone: {
        type: String,
        default: 'primary',
    },
    hint: {
        type: String,
        default: '',
    },
    items: {
        type: Array,
        default: () => [],
    },
    clickable: {
        type: Boolean,
        default: false,
    },
    to: {
        type: [Object, String],
        default: null,
    },
    format: {
        type: String,
        default: 'number',
    },
});

const emit = defineEmits(['click']);

const isInteractive = computed(() => props.clickable || Boolean(props.to));

function onClick() {
    if (props.to) {
        router.push(props.to);

        return;
    }

    if (!props.clickable) {
        return;
    }

    emit('click');
}

function onItemClick(item, event) {
    if (!item?.to) {
        return;
    }

    event?.stopPropagation?.();
    router.push(item.to);
}

function formatNumber(value) {
    return new Intl.NumberFormat('en-IN').format(Number(value ?? 0));
}

function formatValue(value, valueFormat) {
    if (valueFormat === 'currency') {
        return formatMoney(value);
    }

    return formatNumber(value);
}
</script>

<style scoped>
.dashboard-stat-card {
    display: flex;
    flex-direction: column;
    gap: 1rem;
    height: 100%;
    padding: 1.25rem;
    border: 1px solid rgba(226, 232, 240, 0.8);
    border-radius: 1.25rem;
    background: linear-gradient(185deg, #ffffff 0%, #f8fafc 100%);
    box-shadow: 0 4px 18px rgba(15, 23, 42, 0.02);
    transition: transform 0.3s cubic-bezier(0.34, 1.56, 0.64, 1), 
                box-shadow 0.3s ease, 
                border-color 0.3s ease;
    position: relative;
    overflow: hidden;
}

.dashboard-stat-card--primary {
    border-top: 4px solid var(--brand-primary, #2fb36f);
}

.dashboard-stat-card--info {
    border-top: 4px solid var(--brand-info, #2d8cff);
}

.dashboard-stat-card--success {
    border-top: 4px solid var(--brand-primary, #2fb36f);
}

.dashboard-stat-card--warning {
    border-top: 4px solid var(--brand-warning, #d97706);
}

.dashboard-stat-card--clickable {
    cursor: pointer;
}

.dashboard-stat-card--clickable:hover,
.dashboard-stat-card--clickable:focus {
    transform: translateY(-4px) scale(1.015);
    outline: none;
}

.dashboard-stat-card--primary.dashboard-stat-card--clickable:hover {
    border-color: rgba(47, 179, 111, 0.35);
    box-shadow: 0 12px 28px rgba(47, 179, 111, 0.08), 0 2px 8px rgba(47, 179, 111, 0.04);
}

.dashboard-stat-card--info.dashboard-stat-card--clickable:hover {
    border-color: rgba(59, 130, 246, 0.35);
    box-shadow: 0 12px 28px rgba(59, 130, 246, 0.08), 0 2px 8px rgba(59, 130, 246, 0.04);
}

.dashboard-stat-card--success.dashboard-stat-card--clickable:hover {
    border-color: rgba(47, 179, 111, 0.35);
    box-shadow: 0 12px 28px rgba(47, 179, 111, 0.08), 0 2px 8px rgba(47, 179, 111, 0.04);
}

.dashboard-stat-card--warning.dashboard-stat-card--clickable:hover {
    border-color: rgba(217, 119, 6, 0.35);
    box-shadow: 0 12px 28px rgba(217, 119, 6, 0.08), 0 2px 8px rgba(217, 119, 6, 0.04);
}

.dashboard-stat-card__top {
    display: flex;
    align-items: flex-start;
    gap: 0.85rem;
}

.dashboard-stat-card__icon-wrap {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 3rem;
    height: 3rem;
    border-radius: 0.85rem;
    background: var(--brand-soft-primary, rgba(47, 179, 111, 0.1));
    color: var(--brand-primary, #2fb36f);
    font-size: 1.15rem;
    flex-shrink: 0;
}

.dashboard-stat-card--info .dashboard-stat-card__icon-wrap {
    background: var(--brand-soft-info, rgba(59, 130, 246, 0.1));
    color: var(--brand-info, #2d8cff);
}

.dashboard-stat-card--success .dashboard-stat-card__icon-wrap {
    background: var(--brand-soft-primary, rgba(47, 179, 111, 0.1));
    color: var(--brand-primary, #2fb36f);
}

.dashboard-stat-card--warning .dashboard-stat-card__icon-wrap {
    background: var(--brand-soft-warning, rgba(245, 158, 11, 0.1));
    color: var(--brand-warning, #d97706);
}

.dashboard-stat-card__headline {
    flex: 1;
    min-width: 0;
}

.dashboard-stat-card__title {
    margin: 0 0 0.2rem;
    font-size: 0.92rem;
    font-weight: 600;
    color: var(--brand-text-muted, #64748b);
}

.dashboard-stat-card__total-row {
    display: flex;
    align-items: baseline;
    gap: 0.45rem;
}

.dashboard-stat-card__total {
    font-size: 1.85rem;
    line-height: 1.1;
    font-weight: 700;
    color: #0f172a;
}

.dashboard-stat-card__total-label {
    font-size: 0.72rem;
    font-weight: 700;
    letter-spacing: normal;
    text-transform: none;
    color: var(--brand-text-muted, #64748b);
}

.dashboard-stat-card__action {
    color: var(--brand-text-muted, #64748b);
    font-size: 0.85rem;
    margin-top: 0.15rem;
}

.dashboard-stat-card__metrics {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(5.5rem, 1fr));
    gap: 0.55rem;
}

.dashboard-stat-card__metric {
    display: flex;
    flex-direction: column;
    gap: 0.1rem;
    padding: 0.55rem 0.65rem;
    border-radius: 0.65rem;
    background: #fff;
    border: 1px solid #edf2f7;
}

.dashboard-stat-card__metric-value {
    font-size: 1.05rem;
    font-weight: 700;
    color: #0f172a;
    line-height: 1.2;
}

.dashboard-stat-card__metric-label {
    font-size: 0.72rem;
    font-weight: 600;
    color: var(--brand-text-muted, #64748b);
    line-height: 1.2;
}

.dashboard-stat-card__metric--clickable {
    cursor: pointer;
    transition: border-color 0.18s ease, background-color 0.18s ease;
}

.dashboard-stat-card__metric--clickable:hover,
.dashboard-stat-card__metric--clickable:focus {
    border-color: rgba(47, 179, 111, 0.35);
    background: rgba(47, 179, 111, 0.04);
    outline: none;
}

.dashboard-stat-card__hint {
    margin: auto 0 0;
    font-size: 0.78rem;
    color: var(--brand-text-muted, #64748b);
}
</style>
