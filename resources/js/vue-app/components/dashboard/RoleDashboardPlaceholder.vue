<template>
    <div class="role-dashboard-placeholder">
        <div class="placeholder-center text-center py-2">
            <AppLottiePlayer src="/lottie/no-access.json" :width="280" :height="280" />

            <h3 class="fw-bold mb-2 mt-2">{{ headline }}</h3>
            <p class="text-muted mb-0 mx-auto placeholder-message">
                {{ message }}
            </p>

            <div v-if="showSelectCompanyCta" class="mt-4">
                <RouterLink :to="{ name: 'companies.index' }" class="btn btn-primary rounded-3 px-4">
                    <i class="fas fa-building me-2"></i>
                    Go to Companies
                </RouterLink>
            </div>
        </div>

        <div class="card glass-card mt-4">
            <div class="card-header bg-transparent border-0 py-3">
                <h6 class="fw-bold mb-0 text-dark">
                    <i class="fas fa-users-cog text-brand-secondary me-2"></i>
                    Role × tenant_id — what you see
                </h6>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0 align-middle role-guide-table">
                        <thead class="table-light">
                            <tr>
                                <th>Role</th>
                                <th>tenant_id</th>
                                <th>effective_tenant_id</th>
                                <th>dashboard.view</th>
                                <th>Screen</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="(row, index) in guideRows"
                                :key="`${row.role}-${index}`"
                                :class="{ 'table-primary': isCurrentGuideRow(row) }"
                            >
                                <td>
                                    <span class="fw-medium">{{ formatRole(row.role) }}</span>
                                </td>
                                <td><code class="guide-code">{{ row.tenant_id }}</code></td>
                                <td><code class="guide-code">{{ row.effective_tenant_id }}</code></td>
                                <td>
                                    <i
                                        :class="row.dashboard_view ? 'fas fa-check text-success' : 'fas fa-times text-danger'"
                                    ></i>
                                </td>
                                <td class="text-muted small">{{ row.screen }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { RouterLink } from 'vue-router';
import { ROLE_DASHBOARD_GUIDE } from '../../config/roleDashboardGuide';
import AppLottiePlayer from '../ui/AppLottiePlayer.vue';

const props = defineProps({
    role: {
        type: String,
        default: 'user',
    },
    user: {
        type: Object,
        default: null,
    },
});

const roleLabels = {
    master_admin: 'Master Admin',
    super_admin: 'Super Admin',
    admin: 'Admin',
    moderator: 'Moderator',
    accountant: 'Accountant',
};

const roleLabel = computed(() => roleLabels[props.role] ?? 'Your');

const showSelectCompanyCta = computed(
    () => props.role === 'super_admin' && props.user?.requires_company_selection === true,
);

const headline = computed(() => {
    if (showSelectCompanyCta.value) {
        return 'Select a company';
    }

    return `${roleLabel.value} dashboard`;
});

const message = computed(() => {
    if (props.role === 'super_admin' && props.user?.requires_company_selection) {
        return 'Super Admin has no tenant until you enter a company. Go to Companies, click Enter, and the KPI dashboard will load for that company.';
    }

    if (props.role === 'super_admin') {
        return 'Company context is ready. If you still see this screen, check your permissions.';
    }

    if (props.role === 'master_admin') {
        return 'Master Admin does not have dashboard.view permission—use Companies and platform tools instead.';
    }

    if (['transporter', 'customer'].includes(props.role)) {
        return 'This role does not have dashboard.view permission yet.';
    }

    return 'Role-specific KPIs will appear here once permissions are assigned.';
});

const guideRows = ROLE_DASHBOARD_GUIDE;

function formatRole(role) {
    return roleLabels[role] ?? role;
}

function isCurrentGuideRow(row) {
    if (row.role !== props.role) {
        return false;
    }

    if (props.role === 'super_admin') {
        const inCompany = !props.user?.requires_company_selection;

        return inCompany
            ? row.effective_tenant_id.includes('session')
            : row.effective_tenant_id.includes('until');
    }

    return true;
}
</script>

<style scoped>
.placeholder-center {
    max-width: 36rem;
    margin-left: auto;
    margin-right: auto;
}

.placeholder-message {
    max-width: 32rem;
}

.guide-code {
    font-size: 0.68rem;
    word-break: break-word;
}

.role-guide-table th {
    font-size: 0.75rem;
    white-space: nowrap;
}
</style>
