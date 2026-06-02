<template>
    <div v-if="loading" class="py-5 text-center text-muted">Loading dashboard…</div>

    <div v-else-if="loadError" class="alert alert-danger">{{ loadError }}</div>

    <template v-else>
        <DashboardUserContext :user="user" :can-view-dashboard="!showRolePlaceholder" />

        <RoleDashboardPlaceholder
            v-if="showRolePlaceholder"
            :role="currentRole"
            :user="user"
        />

        <template v-else>
            <div class="row mb-4 align-items-center">
                <div class="col-md-8">
                    <h2 class="fw-bold mb-1">
                        <i class="fas fa-grid-2 text-brand-primary"></i> Fleet Dashboard
                    </h2>
                    <p class="text-muted mb-0">
                        <span v-if="user?.role === 'super_admin'">
                            Company:
                            <strong>{{ user?.tenant_name ?? user?.company?.name }}</strong>
                        </span>
                        <span v-else>Today's fleet snapshot for your company.</span>
                    </p>
                </div>
                <div class="col-md-4 text-md-end">
                    <div class="badge bg-brand-active text-brand-primary px-3 py-2 rounded-pill">
                        <i class="fas fa-calendar-alt me-1"></i> {{ todayLabel }}
                    </div>
                </div>
            </div>

            <div v-if="showCompanyRequiredNotice" class="alert alert-warning">
                Enter a company from
                <RouterLink :to="{ name: 'companies.index' }" class="alert-link">Company List</RouterLink>
                to see fleet statistics.
            </div>

            <div v-else class="row g-3">
                <div v-for="card in kpiCards" :key="card.key" class="col-12 col-sm-6 col-md-4 col-xl-3">
                    <div class="card glass-card info-box">
                        <div class="card-body p-0 d-flex align-items-center">
                            <div :class="['icon-circle', card.iconBg, 'me-3', 'shadow-sm']">
                                <i :class="[card.icon, card.iconColor]"></i>
                            </div>
                            <div>
                                <h6 class="text-muted fw-semi-bold mb-1">{{ card.title }}</h6>
                                <h3 class="fw-bold mb-1">{{ formatNumber(card.value) }}</h3>
                                <span :class="['badge', card.badgeClass, 'rounded-pill']">{{ card.badge }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </template>
    </template>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { RouterLink } from 'vue-router';
import api from '../api';
import DashboardUserContext from '../components/dashboard/DashboardUserContext.vue';
import RoleDashboardPlaceholder from '../components/dashboard/RoleDashboardPlaceholder.vue';
import { usePermissions } from '../composables/usePermissions';

const { user, canInCompany } = usePermissions();

const loading = ref(true);
const loadError = ref('');
const fleetStats = ref(null);
const requiresCompany = ref(false);

const currentRole = computed(() => user.value?.role ?? 'user');
const showRolePlaceholder = computed(() => !canInCompany('dashboard.view'));

const userHasCompanyContext = computed(() => {
    if (user.value?.requires_company_selection) {
        return false;
    }

    const tenantId = user.value?.effective_tenant_id ?? user.value?.company_id;

    return tenantId != null;
});

const showCompanyRequiredNotice = computed(
    () => requiresCompany.value && !userHasCompanyContext.value,
);

const todayLabel = new Date().toLocaleDateString('en-GB', {
    day: 'numeric',
    month: 'short',
    year: 'numeric',
});

const kpiCards = computed(() => {
    const stats = fleetStats.value ?? {};
    const cards = [
        {
            key: 'vehicles_count',
            title: 'Total Trucks',
            value: stats.vehicles_count,
            icon: 'fas fa-truck',
            iconBg: 'bg-soft-primary',
            iconColor: 'text-primary',
            badge: 'Fleet',
            badgeClass: 'bg-soft-primary text-primary',
        },
        {
            key: 'drivers_count',
            title: 'Active Drivers',
            value: stats.drivers_count,
            icon: 'fas fa-user-tie',
            iconBg: 'bg-soft-info',
            iconColor: 'text-info',
            badge: 'Active',
            badgeClass: 'bg-soft-info text-info',
        },
        {
            key: 'present_today_count',
            title: 'Present Today',
            value: stats.present_today_count,
            icon: 'fas fa-user-check',
            iconBg: 'bg-soft-success',
            iconColor: 'text-success',
            badge: 'Attendance',
            badgeClass: 'bg-soft-success text-success',
        },
        {
            key: 'absent_today_count',
            title: 'Absent Today',
            value: stats.absent_today_count,
            icon: 'fas fa-user-xmark',
            iconBg: 'bg-soft-danger',
            iconColor: 'text-danger',
            badge: 'Attendance',
            badgeClass: 'bg-soft-danger text-danger',
        },
        {
            key: 'attendance_marked_today_count',
            title: 'Marked Today',
            value: stats.attendance_marked_today_count,
            icon: 'fas fa-calendar-check',
            iconBg: 'bg-soft-warning',
            iconColor: 'text-warning',
            badge: 'Records',
            badgeClass: 'bg-soft-warning text-warning',
        },
    ];

    if (stats.expiring_documents_count != null) {
        cards.push({
            key: 'expiring_documents_count',
            title: 'Docs Expiring (30d)',
            value: stats.expiring_documents_count,
            icon: 'fas fa-file-circle-exclamation',
            iconBg: 'bg-soft-warning',
            iconColor: 'text-warning',
            badge: 'Compliance',
            badgeClass: 'bg-soft-warning text-warning',
        });
    }

    if (stats.license_expiring_count != null) {
        cards.push({
            key: 'license_expiring_count',
            title: 'Licenses Expiring (30d)',
            value: stats.license_expiring_count,
            icon: 'fas fa-id-card',
            iconBg: 'bg-soft-danger',
            iconColor: 'text-danger',
            badge: 'Drivers',
            badgeClass: 'bg-soft-danger text-danger',
        });
    }

    return cards;
});

function formatNumber(value) {
    return new Intl.NumberFormat('en-IN').format(Number(value ?? 0));
}

async function loadDashboard() {
    if (showRolePlaceholder.value) {
        loading.value = false;

        return;
    }

    loading.value = true;
    loadError.value = '';

    try {
        const { data } = await api.get('/app/dashboard/stats');
        requiresCompany.value =
            Boolean(data.requires_company) && !userHasCompanyContext.value;
        fleetStats.value = data.fleet ?? null;
    } catch {
        loadError.value = 'Could not load dashboard data.';
    } finally {
        loading.value = false;
    }
}

onMounted(loadDashboard);

watch(
    () => user.value?.effective_tenant_id ?? user.value?.company_id,
    () => {
        loadDashboard();
    },
);
</script>
