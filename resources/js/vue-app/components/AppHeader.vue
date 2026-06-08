<template>
    <nav class="app-header navbar navbar-expand bg-body">
        <div class="container-fluid app-header-inner">
            <ul class="navbar-nav app-header-start">
                <li class="nav-item">
                    <a class="nav-link" data-lte-toggle="sidebar" href="#" role="button" aria-label="Toggle sidebar">
                        <i class="fa-solid fa-bars"></i>
                    </a>
                </li>
                <li v-if="needsCompanySelection" class="nav-item d-none d-md-block">
                    <RouterLink class="nav-link text-warning fw-semibold" :to="{ name: 'companies.index' }">
                        <i class="fas fa-building me-1"></i>Select company
                    </RouterLink>
                </li>
                <li v-else-if="companyName" class="nav-item d-none d-md-block">
                    <span class="nav-link">
                        <span class="align-middle ms-3 me-2 border-start ps-3 small text-muted" style="font-size: 1em">
                            <i class="fas fa-building me-1"></i>{{ companyName }}
                        </span>
                    </span>
                </li>
                <li v-if="showExitCompany" class="nav-item d-none d-md-block">
                    <button type="button" class="nav-link btn btn-link text-warning p-0 border-0" @click="handleExitCompany">
                        <i class="fas fa-sign-out-alt me-1"></i>Exit company
                    </button>
                </li>
            </ul>

            <div class="app-header-end">
                <ul class="navbar-nav app-header-actions">
                    <li v-if="showPlanBadges" class="nav-item app-header-plan-badges d-flex align-items-center">
                        <div class="credits-container">
                            <div class="credit-pill" title="Plan Expiry">
                                <i class="bi bi-calendar-event"></i>
                                <span class="label">Expiry:</span>
                                <span class="value">{{ planExpiryLabel }}</span>
                            </div>
                            <div class="credit-divider"></div>
                            <div class="credit-pill" title="LR Credits Remaining">
                                <i class="bi bi-coin"></i>
                                <span class="label">Credits:</span>
                                <span class="value">{{ lrCreditsLabel }}</span>
                            </div>
                        </div>
                    </li>

                    <li class="nav-item">
                        <a class="nav-link app-header-icon-btn" href="#" role="button" aria-label="Notifications" @click.prevent>
                            <i class="far fa-bell"></i>
                        </a>
                    </li>

                    <li ref="userMenuRef" class="nav-item dropdown user-menu" :class="{ show: userMenuOpen }">
                        <a
                            href="#"
                            class="nav-link dropdown-toggle app-header-user-toggle"
                            :aria-expanded="userMenuOpen"
                            @click.prevent="userMenuOpen = !userMenuOpen"
                        >
                            <i class="fas fa-user-circle fa-lg"></i>
                            <span v-if="displayName" class="app-header-user-name d-none d-md-inline">{{ displayName }}</span>
                        </a>
                        <div
                            class="dropdown-menu dropdown-menu-lg dropdown-menu-end shadow"
                            :class="{ show: userMenuOpen }"
                            @click.stop
                        >
                            <div class="user-header bg-primary text-center py-4">
                                <i class="fas fa-user-circle fa-3x text-white mb-2"></i>
                                <p class="text-white mb-0">
                                    {{ displayName }}
                                    <small v-if="memberSince" class="d-block mt-1">Member since {{ memberSince }}</small>
                                </p>
                            </div>
                            <a class="dropdown-item" href="/password/change" @click="userMenuOpen = false">
                                <i class="fas fa-user me-2"></i>Profile
                            </a>
                            <a class="dropdown-item" href="/password/change" @click="userMenuOpen = false">
                                <i class="fas fa-cog me-2"></i>Settings
                            </a>
                            <div class="dropdown-divider"></div>
                            <button type="button" class="dropdown-item text-danger" @click="signOut">
                                <i class="fas fa-sign-out-alt me-2"></i>Logout
                            </button>
                        </div>
                    </li>
                </ul>
            </div>
        </div>
    </nav>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { RouterLink, useRouter } from 'vue-router';
import { fetchCurrentUser } from '../api';
import { exitCompany } from '../api/companies';

const props = defineProps({
    user: {
        type: Object,
        default: null,
    },
});

const emit = defineEmits(['user-updated']);

const router = useRouter();

const userMenuOpen = ref(false);
const userMenuRef = ref(null);

const needsCompanySelection = computed(() => Boolean(props.user?.requires_company_selection));
const companyName = computed(() => props.user?.tenant_name ?? props.user?.company?.name ?? '');
const showExitCompany = computed(
    () => props.user?.role === 'super_admin' && (props.user?.effective_company_id ?? props.user?.company_id),
);
const showPlanBadges = computed(() => {
    if (!props.user) {
        return false;
    }

    return props.user.show_plan_badges === true || props.user.can_access_company_panel === true;
});
const displayName = computed(() => props.user?.name || props.user?.email || '');
const planExpiryLabel = computed(() => props.user?.company?.plan_end_date_formatted ?? 'N/A');
const lrCreditsLabel = computed(() => {
    const credits = props.user?.company?.lr_credits;

    return credits !== null && credits !== undefined ? String(credits) : 'N/A';
});

const memberSince = computed(() => {
    if (!props.user?.created_at) {
        return '';
    }

    return new Date(props.user.created_at).toLocaleDateString('en-US', {
        month: 'short',
        year: 'numeric',
    });
});

function onDocumentClick(event) {
    if (!userMenuOpen.value) {
        return;
    }

    const menu = userMenuRef.value;

    if (menu && !menu.contains(event.target)) {
        userMenuOpen.value = false;
    }
}

onMounted(() => {
    document.addEventListener('click', onDocumentClick);
});

onBeforeUnmount(() => {
    document.removeEventListener('click', onDocumentClick);
});

async function handleExitCompany() {
    try {
        const data = await exitCompany();
        const user = data?.user ?? (await fetchCurrentUser());
        window.__AUTH_USER__ = user;
        emit('user-updated', user);
        await router.push({ name: 'companies.index' });
    } catch {
        window.location.href = '/company/exit';
    }
}

function signOut() {
    userMenuOpen.value = false;
    window.location.href = '/logout';
}
</script>
