<template>
    <nav class="app-header navbar navbar-expand bg-body">
        <div class="container-fluid app-header-inner">
            <ul class="navbar-nav app-header-start">
                <li class="nav-item">
                    <a class="nav-link" data-lte-toggle="sidebar" href="#" role="button" aria-label="Toggle sidebar">
                        <i class="fa-solid fa-bars"></i>
                    </a>
                </li>
                <li
                    v-if="needsCompanySelection || companyName"
                    class="nav-item d-none d-md-flex align-items-center app-header-company-nav"
                >
                    <div class="app-header-company-context">
                        <RouterLink
                            v-if="needsCompanySelection"
                            class="app-header-company-context__select"
                            :to="{ name: 'companies.index' }"
                        >
                            <i class="fas fa-building"></i>Select company
                        </RouterLink>
                        <template v-else>
                            <span class="app-header-company-context__name">
                                <i class="fas fa-building"></i>{{ companyName }}
                            </span>
                            <button
                                v-if="showExitCompany"
                                type="button"
                                class="app-header-company-context__exit"
                                title="Exit company context"
                                @click="handleExitCompany"
                            >
                                <i class="fas fa-sign-out-alt"></i>Exit
                            </button>
                        </template>
                    </div>
                </li>
            </ul>

            <div class="app-header-end">
                <ul class="navbar-nav app-header-actions">
                    <li v-if="showPlanBadges" class="nav-item app-header-plan-badges d-flex align-items-center">
          
                    </li>

                    <li
                        v-if="showNotifications"
                        ref="notificationMenuRef"
                        class="nav-item dropdown app-header-notifications"
                        :class="{ show: notificationMenuOpen }"
                    >
                        <a
                            href="#"
                            class="nav-link app-header-icon-btn position-relative"
                            :class="{ 'is-active': notificationMenuOpen }"
                            role="button"
                            aria-label="Notifications"
                            :aria-expanded="notificationMenuOpen"
                            @click.prevent="toggleNotificationMenu"
                        >
                            <i class="far fa-bell"></i>
                            <span v-if="unreadCount > 0" class="app-header-notification-badge">
                                {{ unreadCount > 99 ? '99+' : unreadCount }}
                            </span>
                        </a>
                        <div
                            class="dropdown-menu dropdown-menu-end shadow app-header-notification-menu"
                            :class="{ show: notificationMenuOpen }"
                            @click.stop
                        >
                            <div class="app-header-notification-head">
                                <span class="fw-semibold">Notifications</span>
                                <button
                                    v-if="notifications.length"
                                    type="button"
                                    class="btn btn-link btn-sm p-0"
                                    @click="handleMarkAllRead"
                                >
                                    Mark all read
                                </button>
                            </div>

                            <div v-if="notificationsLoading" class="app-header-notification-empty">Loading…</div>
                            <div v-else-if="notifications.length === 0" class="app-header-notification-empty">
                                No notifications yet.
                            </div>
                            <div v-else class="app-header-notification-list">
                                <button
                                    v-for="notification in notifications"
                                    :key="notification.id"
                                    type="button"
                                    class="app-header-notification-item"
                                    :class="{ 'app-header-notification-item--unread': !notification.read_at }"
                                    @click="handleNotificationClick(notification)"
                                >
                                    <div class="app-header-notification-item__title">{{ notification.title }}</div>
                                    <div class="app-header-notification-item__body">{{ notification.body }}</div>
                                    <div class="app-header-notification-item__time">
                                        {{ formatNotificationTime(notification.created_at) }}
                                    </div>
                                </button>
                            </div>

                        </div>
                    </li>

                    <li ref="userMenuRef" class="nav-item dropdown user-menu" :class="{ show: userMenuOpen }">
                        <a
                            href="#"
                            class="nav-link dropdown-toggle app-header-user-toggle"
                            :aria-expanded="userMenuOpen"
                            @click.prevent="openUserMenu"
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
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { RouterLink, useRouter } from 'vue-router';
import { fetchCurrentUser } from '../api';
import { exitCompany } from '../api/companies';
import {
    fetchNotifications,
    fetchUnreadNotificationCount,
    markAllNotificationsRead,
    markNotificationRead,
} from '../api/notifications';
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
const notificationMenuOpen = ref(false);
const notificationMenuRef = ref(null);
const notifications = ref([]);
const notificationsLoading = ref(false);
const unreadCount = ref(0);
let pollTimer = null;

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
const showNotifications = computed(
    () => Boolean(props.user?.can_access_company_panel) && !needsCompanySelection.value,
);
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

watch(
    () => showNotifications.value,
    (enabled) => {
        if (enabled) {
            startPolling();
        } else {
            stopPolling();
        }
    },
    { immediate: true },
);

function onDocumentClick(event) {
    if (userMenuOpen.value) {
        const menu = userMenuRef.value;

        if (menu && !menu.contains(event.target)) {
            userMenuOpen.value = false;
        }
    }

    if (notificationMenuOpen.value) {
        const menu = notificationMenuRef.value;

        if (menu && !menu.contains(event.target)) {
            notificationMenuOpen.value = false;
        }
    }
}

function startPolling() {
    stopPolling();
    refreshUnreadCount();
    pollTimer = window.setInterval(refreshUnreadCount, 60000);
}

function stopPolling() {
    if (pollTimer !== null) {
        window.clearInterval(pollTimer);
        pollTimer = null;
    }
}

async function refreshUnreadCount() {
    if (!showNotifications.value) {
        return;
    }

    try {
        unreadCount.value = await fetchUnreadNotificationCount();
    } catch {
        unreadCount.value = 0;
    }
}

async function loadNotifications() {
    notificationsLoading.value = true;

    try {
        const data = await fetchNotifications({ per_page: 15 });
        notifications.value = data.data ?? [];
    } catch {
        notifications.value = [];
    } finally {
        notificationsLoading.value = false;
    }
}

async function toggleNotificationMenu() {
    notificationMenuOpen.value = !notificationMenuOpen.value;
    userMenuOpen.value = false;

    if (notificationMenuOpen.value) {
        await loadNotifications();
    }
}

function openUserMenu() {
    userMenuOpen.value = !userMenuOpen.value;
    notificationMenuOpen.value = false;
}

async function handleNotificationClick(notification) {
    if (!notification.read_at) {
        try {
            await markNotificationRead(notification.id);
            notification.read_at = new Date().toISOString();
            unreadCount.value = Math.max(0, unreadCount.value - 1);
        } catch {
            // ignore
        }
    }

    notificationMenuOpen.value = false;

    if (notification.type === 'document_expiry') {
        await router.push({ name: 'dashboard' });
    }
}

async function handleMarkAllRead() {
    try {
        await markAllNotificationsRead();
        notifications.value = notifications.value.map((notification) => ({
            ...notification,
            read_at: notification.read_at ?? new Date().toISOString(),
        }));
        unreadCount.value = 0;
    } catch {
        // ignore
    }
}

function formatNotificationTime(value) {
    if (!value) {
        return '';
    }

    return new Date(value).toLocaleString('en-GB', {
        day: 'numeric',
        month: 'short',
        hour: '2-digit',
        minute: '2-digit',
    });
}

onMounted(() => {
    document.addEventListener('click', onDocumentClick);
});

onBeforeUnmount(() => {
    document.removeEventListener('click', onDocumentClick);
    stopPolling();
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
