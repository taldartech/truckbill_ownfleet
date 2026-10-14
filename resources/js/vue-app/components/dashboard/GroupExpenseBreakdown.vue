<template>
    <section class="group-panel">
        <header class="group-panel__header">
            <div>
                <h5 class="group-panel__title">
                    <i class="fas fa-chart-pie me-2"></i>
                    Expense Breakdown
                </h5>
                <p class="group-panel__subtitle">All spending in the period · click a category for the company split</p>
            </div>
            <div class="group-expense__total">
                <strong :title="formatMoney(expenses.total)">{{ formatMoneyCompact(expenses.total) }}</strong>
                total
            </div>
        </header>

        <div v-if="!categories.length" class="group-panel__empty">
            <i class="fas fa-receipt"></i>
            No expenses recorded in this period.
        </div>

        <template v-else>
            <div class="group-expense__bar" role="img" :aria-label="barLabel">
                <span
                    v-for="category in categories"
                    :key="category.key"
                    class="group-expense__segment"
                    :style="{ width: `${category.percent}%`, background: colorFor(category.key) }"
                    :title="`${category.label}: ${category.percent}%`"
                ></span>
            </div>

            <ul class="group-list">
                <li v-for="category in categories" :key="category.key" class="group-expense__category">
                    <button
                        type="button"
                        class="group-expense__row"
                        :aria-expanded="expandedKey === category.key"
                        @click="toggle(category.key)"
                    >
                        <span class="group-expense__label">
                            <span class="group-dot" :style="{ background: colorFor(category.key) }"></span>
                            {{ category.label }}
                        </span>
                        <span class="group-expense__percent">{{ category.percent }}%</span>
                        <span class="group-list__value" :title="formatMoney(category.amount)">{{ formatMoneyCompact(category.amount) }}</span>
                        <i class="fas fa-chevron-down group-expense__chevron" :class="{ 'group-expense__chevron--open': expandedKey === category.key }"></i>
                    </button>

                    <ul v-if="expandedKey === category.key" class="group-expense__companies">
                        <li v-for="company in category.companies" :key="company.company_id">
                            <span class="group-list__primary">{{ company.company_name }}</span>
                            <div class="group-share-bar flex-grow-1">
                                <div
                                    class="group-share-bar__fill"
                                    :style="{ width: `${shareOf(company.amount, category.amount)}%`, background: colorFor(category.key) }"
                                ></div>
                            </div>
                            <span class="group-list__value" :title="formatMoney(company.amount)">{{ formatMoneyCompact(company.amount) }}</span>
                        </li>
                    </ul>
                </li>
            </ul>

            <p class="group-expense__note">
                Direct trip cost {{ formatMoneyCompact(expenses.direct_cost) }} · the rest covers driver wages, maintenance,
                tyres, office expenses and challans.
            </p>
        </template>
    </section>
</template>

<script setup>
import { computed, ref } from 'vue';
import { formatMoney, formatMoneyCompact } from '../../utils/formatMoney';
import './groupDashboard.css';

const CATEGORY_COLORS = {
    fuel: '#f97316',
    driver: '#0284c7',
    maintenance: '#ef4444',
    tyres: '#6366f1',
    toll: '#14b8a6',
    loading: '#eab308',
    office: '#a855f7',
    other: '#94a3b8',
};

const props = defineProps({
    expenses: {
        type: Object,
        required: true,
    },
});

const expandedKey = ref(null);

const categories = computed(() => (props.expenses.categories ?? []).filter((category) => Number(category.amount) > 0));

const barLabel = computed(() => categories.value.map((category) => `${category.label} ${category.percent}%`).join(', '));

function colorFor(key) {
    return CATEGORY_COLORS[key] ?? CATEGORY_COLORS.other;
}

function shareOf(amount, total) {
    return Number(total) > 0 ? (Number(amount) / Number(total)) * 100 : 0;
}

function toggle(key) {
    expandedKey.value = expandedKey.value === key ? null : key;
}
</script>

<style scoped>
.group-expense__total {
    font-size: 0.8rem;
    color: #64748b;
}

.group-expense__total strong {
    font-size: 1.35rem;
    color: #0f172a;
    margin-right: 0.15rem;
}

.group-expense__bar {
    display: flex;
    height: 12px;
    margin-bottom: 0.9rem;
    border-radius: 999px;
    background: #e2e8f0;
    overflow: hidden;
}

.group-expense__segment {
    height: 100%;
}

.group-expense__category {
    border-bottom: 1px solid #f1f5f9;
}

.group-expense__category:last-child {
    border-bottom: none;
}

.group-expense__row {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    width: 100%;
    padding: 0.55rem 0;
    border: none;
    background: transparent;
    font-size: 0.82rem;
    text-align: left;
    cursor: pointer;
}

.group-expense__label {
    display: inline-flex;
    flex: 1;
    align-items: center;
    gap: 0.45rem;
    min-width: 0;
    font-weight: 600;
    color: #0f172a;
}

.group-expense__percent {
    min-width: 3rem;
    font-size: 0.75rem;
    color: #64748b;
    text-align: right;
}

.group-expense__chevron {
    font-size: 0.65rem;
    color: #94a3b8;
    transition: transform 0.2s ease;
}

.group-expense__chevron--open {
    transform: rotate(180deg);
}

.group-expense__companies {
    display: flex;
    flex-direction: column;
    gap: 0.4rem;
    margin: 0 0 0.6rem;
    padding: 0.6rem 0.75rem;
    border-radius: 0.75rem;
    background: #f8fafc;
    list-style: none;
}

.group-expense__companies li {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    font-size: 0.78rem;
}

.group-expense__companies .group-list__primary {
    flex: 0 0 40%;
    font-weight: 500;
}

.group-expense__note {
    margin: 0.75rem 0 0;
    font-size: 0.7rem;
    color: #94a3b8;
}
</style>
