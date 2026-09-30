<template>
    <section class="group-panel">
        <header class="group-panel__header">
            <div>
                <h5 class="group-panel__title">
                    <i class="fas fa-bell me-2"></i>
                    Attention Required
                </h5>
                <p class="group-panel__subtitle">Only items that need action today</p>
            </div>
            <span v-if="items.length" class="badge rounded-pill text-bg-danger">{{ totalCount }}</span>
        </header>

        <div v-if="!items.length" class="group-panel__empty">
            <i class="fas fa-circle-check text-success"></i>
            All clear — nothing needs your attention right now.
        </div>

        <ul v-else class="group-attention">
            <li
                v-for="item in items"
                :key="item.key"
                class="group-attention__item"
                :class="`group-attention__item--${item.severity}`"
            >
                <button type="button" class="group-attention__row" @click="toggle(item.key)">
                    <span class="group-attention__icon"><i :class="iconFor(item.key)"></i></span>
                    <span class="group-attention__count">{{ item.count }}</span>
                    <span class="group-attention__label">{{ item.label }}</span>
                    <i class="fas fa-chevron-down group-attention__chevron" :class="{ 'group-attention__chevron--open': expandedKey === item.key }"></i>
                </button>

                <ul v-if="expandedKey === item.key" class="group-attention__companies">
                    <li v-for="company in item.companies" :key="company.company_id">
                        <span class="group-list__primary">{{ company.company_name }}</span>
                        <span class="group-attention__company-count">{{ company.count }}</span>
                        <button
                            type="button"
                            class="group-chip-btn"
                            :disabled="!canOpen(item, company) || enteringBranchId !== null"
                            @click="openItem(item, company)"
                        >
                            <i
                                class="fas"
                                :class="enteringBranchId && enteringBranchId === company.default_branch_id ? 'fa-spinner fa-spin' : 'fa-arrow-right'"
                            ></i>
                            Open
                        </button>
                    </li>
                </ul>
            </li>
        </ul>
    </section>
</template>

<script setup>
import { computed, ref } from 'vue';
import { useRouter } from 'vue-router';
import { useEnterBranch } from '../../composables/useEnterBranch';
import './groupDashboard.css';

const ICONS = {
    subscription_expired: 'fas fa-ban',
    idle_trucks: 'fas fa-hourglass-half',
    services_overdue: 'fas fa-screwdriver-wrench',
    documents_expiring: 'fas fa-file-shield',
    licenses_expiring: 'fas fa-id-card',
    eway_bills_expiring: 'fas fa-file-contract',
    invoices_overdue: 'fas fa-file-invoice-dollar',
    emis_due: 'fas fa-building-columns',
    renewal_due: 'fas fa-rotate',
};

const COMPANY_ROUTES = {
    idle_trucks: { name: 'trucks.index' },
    services_overdue: { name: 'maintenance.due' },
    documents_expiring: { name: 'alerts.index' },
    licenses_expiring: { name: 'alerts.index' },
    eway_bills_expiring: { name: 'alerts.index' },
    invoices_overdue: { name: 'invoices.index' },
    emis_due: { name: 'dashboard' },
};

const SUBSCRIPTION_KEYS = ['subscription_expired', 'renewal_due'];

const props = defineProps({
    items: {
        type: Array,
        default: () => [],
    },
});

const router = useRouter();
const { enteringBranchId, enterBranchAndOpen } = useEnterBranch();

const expandedKey = ref(null);

const totalCount = computed(() => props.items.reduce((sum, item) => sum + Number(item.count ?? 0), 0));

function iconFor(key) {
    return ICONS[key] ?? 'fas fa-circle-exclamation';
}

function toggle(key) {
    expandedKey.value = expandedKey.value === key ? null : key;
}

function canOpen(item, company) {
    return SUBSCRIPTION_KEYS.includes(item.key) || Boolean(company.default_branch_id);
}

function openItem(item, company) {
    if (SUBSCRIPTION_KEYS.includes(item.key)) {
        router.push({ name: 'subscription.manage', query: { company_id: company.company_id } });

        return;
    }

    enterBranchAndOpen(company.default_branch_id, {
        label: company.company_name,
        to: COMPANY_ROUTES[item.key] ?? { name: 'dashboard' },
    });
}
</script>

<style scoped>
.group-attention {
    display: flex;
    flex-direction: column;
    gap: 0.4rem;
    max-height: 24rem;
    margin: 0;
    padding: 0;
    overflow-y: auto;
    list-style: none;
}

@container (min-width: 620px) {
    .group-attention {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        align-items: start;
    }
}

.group-attention__item {
    --attention-accent: #f59e0b;
    --attention-bg: #fffbeb;
    border-radius: 0.85rem;
    background: var(--attention-bg);
}

.group-attention__item--danger {
    --attention-accent: #dc2626;
    --attention-bg: #fef2f2;
}

.group-attention__row {
    display: flex;
    align-items: center;
    gap: 0.65rem;
    width: 100%;
    padding: 0.45rem 0.7rem;
    border: none;
    background: transparent;
    text-align: left;
    cursor: pointer;
}

.group-attention__icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    width: 1.6rem;
    height: 1.6rem;
    border-radius: 0.55rem;
    background: #ffffff;
    color: var(--attention-accent);
    font-size: 0.78rem;
}

.group-attention__count {
    min-width: 1.6rem;
    font-size: 1.05rem;
    font-weight: 800;
    color: var(--attention-accent);
}

.group-attention__label {
    flex: 1;
    min-width: 0;
    font-size: 0.78rem;
    font-weight: 600;
    color: #1e293b;
}

.group-attention__chevron {
    font-size: 0.65rem;
    color: #94a3b8;
    transition: transform 0.2s ease;
}

.group-attention__chevron--open {
    transform: rotate(180deg);
}

.group-attention__companies {
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
    margin: 0;
    padding: 0 0.8rem 0.7rem 3.2rem;
    list-style: none;
}

.group-attention__companies li {
    display: flex;
    align-items: center;
    gap: 0.6rem;
    font-size: 0.78rem;
}

.group-attention__companies .group-list__primary {
    flex: 1;
    font-weight: 500;
}

.group-attention__company-count {
    font-weight: 700;
    color: #0f172a;
}
</style>
