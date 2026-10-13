<template>
    <div class="vue-app-shell layout-fixed sidebar-expand-lg bg-body-tertiary">
        <div class="app-wrapper">
            <AppHeader :user="user" @user-updated="onUserUpdated" />
            <AppSidebar :items="navigationItems" :user="user" />
            <main class="app-main">
                <div class="app-content-header main-content">
                    <div class="container-fluid">
                        <SubscriptionAccessBanner />
                        <RouterView :key="branchContextKey" />
                    </div>
                </div>
            </main>
            <AppFooter />
        </div>
        <AppToastHost />
    </div>
</template>

<script setup>
import { computed, onMounted, onBeforeUnmount, provide, ref } from 'vue';
import { useRouter } from 'vue-router';
import { fetchCurrentUser } from '../api';
import AppFooter from '../components/AppFooter.vue';
import AppHeader from '../components/AppHeader.vue';
import AppSidebar from '../components/AppSidebar.vue';
import AppToastHost from '../components/ui/AppToastHost.vue';
import SubscriptionAccessBanner from '../components/subscription/SubscriptionAccessBanner.vue';
import { adminNavigation } from '../config/adminNavigation';
import { useAdminLtePushMenu } from '../composables/useAdminLtePushMenu';
import { useFilteredNavigation } from '../composables/useFilteredNavigation';
import { initBootstrapModalCleanup } from '../utils/bootstrapModal';

const router = useRouter();
const user = ref(window.__AUTH_USER__ ?? null);

const branchContextKey = computed(() => {
    const current = user.value;

    if (!current) {
        return 'guest';
    }

    return [
        current.effective_tenant_id ?? current.company_id ?? 'no-tenant',
        current.effective_branch_id ?? current.branch_id ?? 'no-branch',
        current.role ?? 'no-role',
    ].join(':');
});

provide('currentUser', user);

const navigationItems = useFilteredNavigation(adminNavigation, user);

useAdminLtePushMenu();

function onUserUpdated(updatedUser) {
    user.value = updatedUser;
    window.__AUTH_USER__ = updatedUser;
}

onMounted(async () => {
    initBootstrapModalCleanup();
    
    window.addEventListener('keydown', handleGlobalKeydown);

    if (user.value) {
        return;
    }

    try {
        user.value = await fetchCurrentUser();

        if (!user.value) {
            await router.replace({ name: 'login' });
        }
    } catch {
        await router.replace({ name: 'login' });
    }
});

onBeforeUnmount(() => {
    window.removeEventListener('keydown', handleGlobalKeydown);
});

function handleGlobalKeydown(e) {
    // F2 or Ctrl+K
    if (e.key === 'F2' || ((e.ctrlKey || e.metaKey) && (e.key === 'k' || e.key === 'K'))) {
        e.preventDefault();
        if (router.currentRoute.value.name !== 'quick-search') {
            router.push({ name: 'quick-search' });
        } else {
            window.dispatchEvent(new CustomEvent('focus-quick-search'));
        }
    }
}
</script>

