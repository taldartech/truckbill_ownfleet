<template>
    <aside class="app-sidebar sidebar shadow">
        <div class="sidebar-brand">
            <a href="/app/dashboard" class="brand-link">
                <img :src="logoUrl" alt="TruckBill Logo" class="brand-image" />
            </a>
        </div>

        <div class="sidebar-wrapper">
            <nav v-if="items.length > 0" class="mt-2">
                <ul class="nav sidebar-menu flex-column" role="menu">
                    <template v-for="(item, index) in items" :key="index">
                        <li v-if="!item.children" class="nav-item">
                            <RouterLink
                                :to="item.to ?? item.href"
                                class="nav-link"
                                :class="{ active: isItemActive(item) }"
                            >
                                <i :class="item.icon" class="nav-icon"></i>
                                <p>{{ item.label }}</p>
                            </RouterLink>
                        </li>
                        <li v-else class="nav-item" :class="{ 'menu-open': isSubmenuOpen(index, item) }">
                            <a href="#" class="nav-link" @click.prevent="toggleSubmenu(index)">
                                <i :class="item.icon" class="nav-icon"></i>
                                <p>
                                    {{ item.label }}
                                    <i class="nav-arrow bi bi-chevron-right"></i>
                                </p>
                            </a>
                            <ul class="nav nav-treeview">
                                <li v-for="(child, childIndex) in item.children" :key="childIndex" class="nav-item">
                                    <RouterLink
                                        v-if="child.to"
                                        :to="child.to"
                                        class="nav-link"
                                        :class="{ active: isChildActive(child) }"
                                    >
                                        <i :class="child.icon"></i>
                                        <p>{{ child.label }}</p>
                                    </RouterLink>
                                    <a v-else :href="child.href" class="nav-link">
                                        <i :class="child.icon"></i>
                                        <p>{{ child.label }}</p>
                                    </a>
                                </li>
                            </ul>
                        </li>
                    </template>
                </ul>
            </nav>
        </div>
    </aside>
</template>

<script setup>
import { reactive, watch } from 'vue';
import { RouterLink, useRoute } from 'vue-router';

const props = defineProps({
    items: {
        type: Array,
        required: true,
    },
});

const route = useRoute();
const logoUrl = window.__LOGO_URL__ ?? '/logo.png';
const openMenus = reactive({});

function toggleSubmenu(index) {
    openMenus[index] = !openMenus[index];
}

function isSubmenuOpen(index) {
    return Boolean(openMenus[index]);
}

function isItemActive(item) {
    if (item.exact) {
        return route.path === '/dashboard' || route.path === '/';
    }

    if (item.to) {
        return route.path === item.to || route.path.startsWith(`${item.to}/`);
    }

    return false;
}

function childPath(child) {
    if (!child.to) {
        return '';
    }

    if (typeof child.to === 'string') {
        return child.to;
    }

    if (typeof child.to === 'object' && child.to.path) {
        return child.to.path;
    }

    return '';
}

function isChildActive(child) {
    const path = childPath(child);

    if (!path) {
        return route.name === child.to?.name;
    }

    return route.path === path || route.path.startsWith(`${path}/`);
}

function hasActiveChild(item) {
    return item.children?.some((child) => isChildActive(child)) ?? false;
}

function syncOpenMenusWithRoute() {
    props.items.forEach((item, index) => {
        if (!item.children) {
            return;
        }

        openMenus[index] = hasActiveChild(item);
    });
}

watch(() => route.path, syncOpenMenusWithRoute, { immediate: true });
</script>
