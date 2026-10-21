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
                    v-if="needsBranchSelection || companyName || branchName"
                    class="nav-item d-none d-md-flex align-items-center app-header-company-nav ms-2"
                >
                    <div class="app-header-company-context">
                        <div
                            v-if="needsBranchSelection"
                            ref="branchPickerRef"
                            class="app-header-branch-picker"
                        >
                            <button
                                type="button"
                                class="app-header-company-context__select app-header-branch-picker__toggle"
                                :aria-expanded="branchPickerOpen"
                                @click="toggleBranchPicker"
                            >
                                <i class="fas fa-code-branch"></i>Select branch
                                <i class="fas fa-chevron-down app-header-branch-picker__chevron"></i>
                            </button>
                            <div
                                v-if="branchPickerOpen"
                                class="shadow app-header-branch-picker__menu"
                                :style="branchPickerMenuStyle"
                                @click.stop
                                @keydown.esc="branchPickerOpen = false"
                            >
                                <div class="app-header-branch-picker__search">
                                    <input
                                        ref="branchSearchRef"
                                        v-model.trim="branchSearch"
                                        type="search"
                                        class="form-control form-control-sm"
                                        placeholder="Search branch or company…"
                                    />
                                </div>
                                <div v-if="branchPickerLoading" class="app-header-branch-picker__empty">Loading branches…</div>
                                <div v-else-if="!groupedPickerBranches.length" class="app-header-branch-picker__empty">
                                    No branches found.
                                </div>
                                <div v-else class="app-header-branch-picker__list">
                                    <div v-for="group in groupedPickerBranches" :key="group.companyId">
                                        <div class="app-header-branch-picker__company">{{ group.companyName }}</div>
                                        <button
                                            v-for="branch in group.branches"
                                            :key="branch.id"
                                            type="button"
                                            class="app-header-branch-picker__item"
                                            :disabled="enteringBranchId !== null"
                                            @click="handleEnterBranch(branch)"
                                        >
                                            <span class="text-truncate">
                                                {{ branch.branch_name }}
                                                <span v-if="branch.head_office" class="app-header-branch-picker__tag">Head office</span>
                                            </span>
                                            <i
                                                class="fas"
                                                :class="enteringBranchId === branch.id ? 'fa-spinner fa-spin' : 'fa-arrow-right-to-bracket'"
                                            ></i>
                                        </button>
                                    </div>
                                </div>
                                <RouterLink
                                    :to="{ name: 'branches.index' }"
                                    class="app-header-branch-picker__footer"
                                    @click="branchPickerOpen = false"
                                >
                                    Manage branches
                                </RouterLink>
                            </div>
                        </div>
                        <template v-else>
                            <span v-if="companyName" class="app-header-company-context__name">
                                <i class="fas fa-building"></i>{{ companyName }}
                            </span>
                            <span v-if="branchName" class="app-header-company-context__name ms-2">
                                <i class="fas fa-code-branch"></i>{{ branchName }}
                            </span>
                            <div v-if="showBranchSwitcher" class="ms-2 app-header-branch-switch">
                                <AppSelect
                                    v-model="selectedBranchId"
                                    :options="branchSwitchOptions"
                                    placeholder="Switch branch"
                                    search-placeholder="Search branch…"
                                    @change="handleBranchSwitch"
                                />
                            </div>
                            <button
                                v-if="showExitCompany"
                                type="button"
                                class="app-header-company-context__exit"
                                title="Exit branch context"
                                @click="showExitConfirm = true"
                            >
                                <i class="fas fa-sign-out-alt"></i>Exit
                            </button>
                        </template>
                    </div>
                </li>
            </ul>

            <div class="app-header-end">
                <ul class="navbar-nav app-header-actions gap-2 gap-md-3">
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
                                    <small v-if="displayRole" class="d-block mt-1">{{ displayRole }}</small>
                                </p>
                            </div>
                            <button type="button" class="dropdown-item text-danger" @click="signOut">
                                <i class="fas fa-sign-out-alt me-2"></i>Logout
                            </button>
                        </div>
                    </li>
                </ul>
            </div>
        </div>

        <AppConfirmModal
            :show="showExitConfirm"
            title="Exit branch context?"
            :loading="exitingCompany"
            icon="bi bi-box-arrow-right"
            icon-tone="warning"
            cancel-label="Stay"
            confirm-label="Yes, Exit"
            loading-label="Exiting…"
            confirm-variant="warning"
            @cancel="showExitConfirm = false"
            @confirm="handleExitCompany"
        >
            <template #message>
                You will leave <strong>{{ branchName || companyName }}</strong> and return to branch selection.
            </template>
        </AppConfirmModal>
    </nav>
</template>

<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { RouterLink, useRouter } from 'vue-router';
import { fetchCurrentUser } from '../api';
import { exitCompany } from '../api/companies';
import { enterBranch, listAllBranches, switchBranch } from '../api/branches';
import { useAppToast } from '../composables/useAppToast';
import {
    fetchNotifications,
    fetchUnreadNotificationCount,
    markAllNotificationsRead,
    markNotificationRead,
} from '../api/notifications';
import { formatDisplay, toAppTimezoneIso, toDateInputValue, addDaysToDateInput } from '../utils/datetime';
import AppSelect from './ui/AppSelect.vue';
import AppConfirmModal from './ui/AppConfirmModal.vue';
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
const switchableBranches = ref([]);
const selectedBranchId = ref('');
const showExitConfirm = ref(false);
const exitingCompany = ref(false);
const branchPickerRef = ref(null);
const branchSearchRef = ref(null);
const branchPickerOpen = ref(false);
const branchPickerLoading = ref(false);
const branchPickerBranches = ref(null);
const branchSearch = ref('');
const branchPickerMenuStyle = ref({});
const enteringBranchId = ref(null);
const toast = useAppToast();
let pollTimer = null;

const groupedPickerBranches = computed(() => {
    const term = branchSearch.value.toLowerCase();
    const groups = new Map();

    (branchPickerBranches.value ?? [])
        .filter((branch) => {
            const companyLabel = branch.company?.name ?? '';

            return (
                !term
                || branch.branch_name.toLowerCase().includes(term)
                || companyLabel.toLowerCase().includes(term)
            );
        })
        .forEach((branch) => {
            const companyId = branch.company_id;

            if (!groups.has(companyId)) {
                groups.set(companyId, { companyId, companyName: branch.company?.name ?? 'Company', branches: [] });
            }

            groups.get(companyId).branches.push(branch);
        });

    return [...groups.values()]
        .sort((a, b) => a.companyName.localeCompare(b.companyName))
        .map((group) => ({
            ...group,
            branches: [...group.branches].sort(
                (a, b) => Number(b.head_office) - Number(a.head_office) || a.branch_name.localeCompare(b.branch_name),
            ),
        }));
});

const needsBranchSelection = computed(
    () =>
        Boolean(props.user?.requires_branch_selection) ||
        Boolean(props.user?.requires_company_selection),
);
const companyName = computed(() => props.user?.tenant_name ?? props.user?.company?.name ?? '');
const branchName = computed(() => props.user?.branch_name ?? '');
const showExitCompany = computed(
    () => props.user?.role === 'super_admin' && (props.user?.effective_branch_id ?? props.user?.branch_id),
);
const showBranchSwitcher = computed(
    () => props.user?.role === 'super_admin' && switchableBranches.value.length > 1 && !needsBranchSelection.value,
);
const branchSwitchOptions = computed(() =>
    switchableBranches.value.map((branch) => ({
        value: branch.id,
        label: branch.branch_name,
    })),
);
const showPlanBadges = computed(() => {
    if (!props.user) {
        return false;
    }

    return props.user.show_plan_badges === true || props.user.can_access_company_panel === true;
});
const showNotifications = computed(
    () => Boolean(props.user?.can_access_company_panel) && !needsBranchSelection.value,
);
const displayName = computed(() => props.user?.name || props.user?.email || '');
const displayRole = computed(() => {
    if (props.user?.role_label) {
        return props.user.role_label;
    }

    const role = props.user?.role;

    if (!role) {
        return '';
    }

    return role.replace(/_/g, ' ').replace(/\b\w/g, (char) => char.toUpperCase());
});

watch(
    () => [props.user?.effective_company_id, props.user?.effective_branch_id],
    async () => {
        selectedBranchId.value = String(props.user?.effective_branch_id ?? props.user?.branch_id ?? '');

        if (props.user?.role === 'super_admin' && props.user?.effective_company_id) {
            try {
                switchableBranches.value = await listAllBranches({
                    company_id: props.user.effective_company_id,
                });
            } catch {
                switchableBranches.value = [];
            }
        } else {
            switchableBranches.value = [];
        }
    },
    { immediate: true },
);

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

    if (branchPickerOpen.value) {
        const picker = branchPickerRef.value;

        if (picker && !picker.contains(event.target)) {
            branchPickerOpen.value = false;
        }
    }
}

async function toggleBranchPicker() {
    branchPickerOpen.value = !branchPickerOpen.value;
    userMenuOpen.value = false;
    notificationMenuOpen.value = false;

    if (!branchPickerOpen.value) {
        return;
    }

    const toggleRect = branchPickerRef.value?.getBoundingClientRect();

    branchPickerMenuStyle.value = toggleRect
        ? { top: `${toggleRect.bottom + 12}px`, left: `${Math.max(toggleRect.left - 12, 8)}px` }
        : {};
    branchSearch.value = '';
    await nextTick();
    branchSearchRef.value?.focus();

    if (branchPickerBranches.value !== null) {
        return;
    }

    branchPickerLoading.value = true;

    try {
        branchPickerBranches.value = await listAllBranches();
    } catch {
        branchPickerBranches.value = null;
        toast.error('Could not load branches.');
    } finally {
        branchPickerLoading.value = false;
    }
}

async function handleEnterBranch(branch) {
    enteringBranchId.value = branch.id;

    try {
        const data = await enterBranch(branch.id);
        const user = data?.user ?? (await fetchCurrentUser());
        window.__AUTH_USER__ = user;
        emit('user-updated', user);
        branchPickerOpen.value = false;
        toast.success(`Entered ${branch.branch_name}.`);
        await router.push({ name: 'dashboard' });
    } catch (error) {
        toast.error(error.response?.data?.message ?? 'Could not enter branch.');
    } finally {
        enteringBranchId.value = null;
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
    branchPickerOpen.value = false;

    if (notificationMenuOpen.value) {
        await loadNotifications();
    }
}

function openUserMenu() {
    userMenuOpen.value = !userMenuOpen.value;
    notificationMenuOpen.value = false;
    branchPickerOpen.value = false;
}

async function handleNotificationClick(notification) {
    if (!notification.read_at) {
        try {
            await markNotificationRead(notification.id);
            notification.read_at = toAppTimezoneIso();
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
            read_at: notification.read_at ?? toAppTimezoneIso(),
        }));
        unreadCount.value = 0;
    } catch {
        // ignore
    }
}

function formatNotificationTime(value) {
    return formatDisplay(value, 'datetime', '');
}

onMounted(() => {
    document.addEventListener('click', onDocumentClick);
});

onBeforeUnmount(() => {
    document.removeEventListener('click', onDocumentClick);
    stopPolling();
});

async function handleBranchSwitch() {
    if (!selectedBranchId.value) {
        return;
    }

    try {
        await switchBranch(Number(selectedBranchId.value));
        window.location.reload();
    } catch {
        selectedBranchId.value = String(props.user?.effective_branch_id ?? props.user?.branch_id ?? '');
    }
}

async function handleExitCompany() {
    exitingCompany.value = true;

    try {
        const data = await exitCompany();
        const user = data?.user ?? (await fetchCurrentUser());
        window.__AUTH_USER__ = user;
        emit('user-updated', user);
        showExitConfirm.value = false;
        await router.push({ name: 'dashboard' });
    } catch {
        window.location.href = '/company/exit';
    } finally {
        exitingCompany.value = false;
    }
}

function signOut() {
    userMenuOpen.value = false;
    window.location.href = '/logout';
}
</script>

<style scoped>
.app-header-branch-picker {
    position: relative;
}

.app-header-branch-picker__toggle {
    padding: 0;
    border: none;
    background: transparent;
    cursor: pointer;
}

.app-header-company-context__select .app-header-branch-picker__chevron {
    font-size: 0.65rem;
    color: var(--brand-text-muted);
}

.app-header-branch-picker__menu {
    position: fixed;
    z-index: 1060;
    width: min(20rem, calc(100vw - 1rem));
    padding: 0;
    border: 1px solid var(--bs-border-color, #dee2e6);
    border-radius: 0.75rem;
    background: #ffffff;
    overflow: hidden;
}

.app-header-branch-picker__search {
    padding: 0.6rem;
    border-bottom: 1px solid var(--brand-border);
    background: #f8fafc;
}

.app-header-branch-picker__list {
    max-height: 20rem;
    overflow-y: auto;
    padding: 0.25rem 0;
}

.app-header-branch-picker__company {
    padding: 0.5rem 0.9rem 0.25rem;
    font-size: 0.66rem;
    font-weight: 700;
    color: var(--brand-text-muted);
    text-transform: uppercase;
    letter-spacing: 0.04em;
}

.app-header-branch-picker__item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
    width: 100%;
    padding: 0.45rem 0.9rem;
    border: none;
    background: transparent;
    font-size: 0.82rem;
    font-weight: 500;
    color: var(--brand-text-primary);
    text-align: left;
}

.app-header-branch-picker__item:hover:not(:disabled),
.app-header-branch-picker__item:focus-visible {
    background: var(--brand-active-bg);
    color: var(--brand-text-accent);
    outline: none;
}

.app-header-branch-picker__item:disabled {
    cursor: wait;
    opacity: 0.7;
}

.app-header-branch-picker__item i {
    flex-shrink: 0;
    color: var(--brand-primary);
    font-size: 0.75rem;
}

.app-header-branch-picker__tag {
    margin-left: 0.3rem;
    padding: 0.05rem 0.35rem;
    border-radius: 999px;
    background: #f1f5f9;
    font-size: 0.62rem;
    font-weight: 600;
    color: var(--brand-text-muted);
}

.app-header-branch-picker__empty {
    padding: 1rem;
    font-size: 0.8rem;
    color: var(--brand-text-muted);
    text-align: center;
}

.app-header-branch-picker__footer {
    display: block;
    padding: 0.55rem 0.9rem;
    border-top: 1px solid var(--brand-border);
    background: #f8fafc;
    font-size: 0.78rem;
    font-weight: 600;
    color: var(--brand-text-accent);
    text-align: center;
    text-decoration: none;
}
</style>
