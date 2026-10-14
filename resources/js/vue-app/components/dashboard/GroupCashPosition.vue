<template>
    <section class="group-panel">
        <header class="group-panel__header">
            <div>
                <h5 class="group-panel__title">
                    <i class="fas fa-hand-holding-dollar me-2"></i>
                    Receivables
                </h5>
                <p class="group-panel__subtitle">Unpaid customer invoices as of today</p>
            </div>
        </header>

        <div class="group-cash__summary">
            <div class="group-cash__figure">
                <span class="group-stat__label">Outstanding</span>
                <span class="group-cash__value" :title="formatMoney(receivables.outstanding)">
                    {{ formatDynamicMoney(receivables.outstanding) }}
                </span>
            </div>
            <div class="group-cash__figure">
                <span class="group-stat__label">Due today</span>
                <span class="group-cash__value" :title="formatMoney(receivables.due_today)">
                    {{ formatDynamicMoney(receivables.due_today) }}
                </span>
            </div>
            <div class="group-cash__figure">
                <span class="group-stat__label">Overdue</span>
                <span class="group-cash__value text-danger" :title="formatMoney(receivables.overdue)">
                    {{ formatDynamicMoney(receivables.overdue) }}
                </span>
            </div>
        </div>

        <p class="group-panel__section-label">Top outstanding customers</p>
        <div v-if="!customers.length" class="group-panel__empty">
            <i class="fas fa-circle-check"></i>
            No unpaid invoices.
        </div>
        <ul v-else class="group-list">
            <li
                v-for="customer in customers"
                :key="`${customer.company_id}-${customer.customer_id}`"
                class="group-list__item"
            >
                <span class="group-list__primary">
                    {{ customer.name }}
                    <span class="group-list__secondary">
                        {{ customer.company_name }} · {{ customer.invoices_count }} invoice(s)
                    </span>
                </span>
                <span class="text-end">
                    <span class="group-list__value" :title="formatMoney(customer.outstanding)">
                        {{ formatDynamicMoney(customer.outstanding) }}
                    </span>
                    <span v-if="customer.overdue > 0" class="group-list__secondary text-danger">
                        {{ formatDynamicMoney(customer.overdue) }} overdue
                    </span>
                </span>
            </li>
        </ul>
    </section>
</template>

<script setup>
import { computed } from 'vue';
import { formatDynamicMoney, formatMoney } from '../../utils/formatMoney';
import './groupDashboard.css';

const props = defineProps({
    cash: {
        type: Object,
        required: true,
    },
});

const receivables = computed(() => props.cash.receivables ?? {});
const customers = computed(() => props.cash.top_outstanding_customers ?? []);
</script>

<style scoped>
.group-cash__summary {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 0.5rem;
    margin-bottom: 0.9rem;
    padding: 0.6rem 0.8rem;
    border-radius: 0.75rem;
    background: #f0fdf4;
}

.group-cash__figure {
    display: flex;
    flex-direction: column;
    gap: 0.15rem;
    min-width: 0;
}

.group-cash__value {
    font-size: clamp(0.92rem, 1.15vw, 1.1rem);
    font-weight: 700;
    color: #0f172a;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 100%;
}

.group-cash__value.text-danger {
    color: #dc2626;
}
</style>
