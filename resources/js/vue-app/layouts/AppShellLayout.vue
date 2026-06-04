<template>
    <div class="vue-app-shell layout-fixed sidebar-expand-lg bg-body-tertiary">
        <div class="app-wrapper">
            <AppHeader :user="user" @user-updated="onUserUpdated" />
            <AppSidebar :items="navigationItems" />
            <main class="app-main">
                <div class="app-content-header">
                    <div class="container-fluid">
                        <RouterView />
                    </div>
                </div>
            </main>
            <AppFooter />
        </div>
        <AppToastHost />
    </div>
</template>

<script setup>
import { onMounted, provide, ref } from 'vue';
import { useRouter } from 'vue-router';
import { fetchCurrentUser } from '../api';
import AppFooter from '../components/AppFooter.vue';
import AppHeader from '../components/AppHeader.vue';
import AppSidebar from '../components/AppSidebar.vue';
import AppToastHost from '../components/ui/AppToastHost.vue';
import { adminNavigation } from '../config/adminNavigation';
import { useFilteredNavigation } from '../composables/useFilteredNavigation';

const router = useRouter();
const user = ref(window.__AUTH_USER__ ?? null);

provide('currentUser', user);

const navigationItems = useFilteredNavigation(adminNavigation, user);

function onUserUpdated(updatedUser) {
    user.value = updatedUser;
}

onMounted(async () => {
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
</script>

