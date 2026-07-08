<template>
    <div class="trip-status-updates alert alert-info py-2 px-3 mb-0 small">
        <div class="trip-status-updates__title fw-semibold mb-1">
            <i class="bi bi-info-circle me-1" aria-hidden="true"></i>
            {{ title }}
        </div>
        <ul class="trip-status-updates__list mb-0 ps-3">
            <li v-for="(item, index) in items" :key="index">{{ item }}</li>
        </ul>
    </div>
</template>

<script setup>
defineProps({
    title: { type: String, default: 'What will be updated' },
    items: { type: Array, default: () => [] },
});
</script>

<style scoped>
.trip-status-updates {
    overflow: visible;
}

.trip-status-updates__list {
    margin-bottom: 0;
    overflow: visible;
}

.trip-status-updates__list li + li {
    margin-top: 0.1rem;
}
</style>
