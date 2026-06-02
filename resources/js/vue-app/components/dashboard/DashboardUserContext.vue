<template>
    <div class="card glass-card mb-4 dashboard-user-context">
        <div class="card-body py-3">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                <h6 class="fw-bold mb-0 text-dark">
                    <i class="fas fa-id-badge text-brand-primary me-2"></i>
                    Your session
                </h6>
                <span :class="['badge rounded-pill', viewModeBadgeClass]">{{ viewModeLabel }}</span>
            </div>

            <div class="row g-2 g-md-3 small">
                <div class="col-6 col-md-3">
                    <span class="text-muted d-block">Role</span>
                    <strong class="text-dark">{{ roleLabel }}</strong>
                </div>
                <div class="col-6 col-md-3">
                    <span class="text-muted d-block">{{ homeTenantLabel }}</span>
                    <code class="user-tenant-code">{{ homeTenantDisplay }}</code>
                </div>
                <div class="col-6 col-md-3">
                    <span class="text-muted d-block">Active company ID</span>
                    <code class="user-tenant-code">{{ effectiveTenantDisplay }}</code>
                </div>
                <div class="col-6 col-md-3">
                    <span class="text-muted d-block">Company</span>
                    <strong class="text-dark">{{ companyName }}</strong>
                </div>
            </div>

            <div v-if="permissions.length" class="mt-3 pt-3 border-top">
                <span class="text-muted small d-block mb-1">Permissions</span>
                <div class="d-flex flex-wrap gap-1">
                    <span
                        v-for="slug in permissions"
                        :key="slug"
                        class="badge bg-soft-primary text-primary rounded-pill fw-normal"
                    >
                        {{ slug }}
                    </span>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
    user: {
        type: Object,
        default: null,
    },
    canViewDashboard: {
        type: Boolean,
        default: false,
    },
});

const roleLabels = {
    master_admin: 'Master Admin',
    super_admin: 'Super Admin',
    admin: 'Admin',
    moderator: 'Moderator',
    accountant: 'Accountant',
    transporter: 'Transporter',
    customer: 'Customer',
};

const roleLabel = computed(() => roleLabels[props.user?.role] ?? props.user?.role ?? '—');

const isPlatformSuperAdmin = computed(
    () => props.user?.role === 'super_admin' && props.user?.tenant_id == null,
);

const homeTenantLabel = computed(() =>
    isPlatformSuperAdmin.value ? 'Home tenant (DB)' : 'tenant_id',
);

const homeTenantDisplay = computed(() => {
    if (isPlatformSuperAdmin.value) {
        return '— (not fixed; use Enter on Company List)';
    }

    return formatId(props.user?.tenant_id);
});

const effectiveTenantDisplay = computed(() => formatId(props.user?.effective_tenant_id ?? props.user?.company_id));

const companyName = computed(() => props.user?.tenant_name ?? props.user?.company?.name ?? '—');

const permissions = computed(() => props.user?.permissions ?? []);

const viewModeLabel = computed(() => {
    if (!props.canViewDashboard) {
        if (props.user?.requires_company_selection) {
            return 'Select company first';
        }

        return 'No dashboard access';
    }

    return 'KPI dashboard active';
});

const viewModeBadgeClass = computed(() => {
    if (props.canViewDashboard) {
        return 'bg-soft-success text-success';
    }

    if (props.user?.requires_company_selection) {
        return 'bg-soft-warning text-warning';
    }

    return 'bg-soft-danger text-danger';
});

function formatId(value) {
    if (value === null || value === undefined || value === '') {
        return 'null';
    }

    return String(value);
}
</script>

<style scoped>
.user-tenant-code {
    font-size: 0.7rem;
    word-break: break-all;
    color: var(--brand-primary, #2563eb);
    background: rgba(37, 99, 235, 0.08);
    padding: 0.15rem 0.35rem;
    border-radius: 0.25rem;
}
</style>
