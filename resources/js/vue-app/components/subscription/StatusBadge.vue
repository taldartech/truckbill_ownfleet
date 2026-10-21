<template>
    <span class="badge text-capitalize subscription-badge" :class="badgeClass">{{ label }}</span>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
    status: {
        type: String,
        default: 'none',
    },
});

const label = computed(() => props.status.replace(/_/g, ' '));

const badgeClass = computed(() => {
    switch (props.status) {
        case 'active':
        case 'trial':
            return 'bg-success';
        case 'expired':
        case 'cancelled':
            return 'bg-danger';
        case 'grace':
            return 'bg-warning text-dark';
        default:
            return 'bg-secondary';
    }
});
</script>

<style scoped>
.subscription-badge {
    font-weight: 600;
    letter-spacing: 0.01em;
}

@media (max-width: 767.98px) {
    .subscription-badge {
        font-size: 0.65rem !important;
        padding: 0.18rem 0.42rem !important;
        border-radius: 0.35rem;
    }
}

@media (min-width: 768px) and (max-width: 1199.98px) {
    .subscription-badge {
        font-size: 0.72rem !important;
        padding: 0.22rem 0.48rem !important;
    }
}
</style>
