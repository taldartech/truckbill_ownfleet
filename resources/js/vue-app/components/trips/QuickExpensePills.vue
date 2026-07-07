<template>
    <div class="quick-pills-wrap">
        <div class="quick-pills d-flex flex-wrap gap-2">
            <button
                v-for="pill in pills"
                :key="pill.value"
                type="button"
                class="quick-pill"
                :class="pill.className"
                @click="$emit('add', pill.value)"
            >
                <i :class="['bi', pill.icon, 'quick-pill__icon']"></i>
                <span>{{ pill.label }}</span>
            </button>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { QUICK_EXPENSE_PILLS, WHOLE_TRIP_QUICK_EXPENSE_PILLS } from '../../api/trips';

const props = defineProps({
    scope: {
        type: String,
        default: 'stop',
        validator: (value) => ['stop', 'whole-trip'].includes(value),
    },
});

defineEmits(['add']);

const pills = computed(() => (props.scope === 'whole-trip' ? WHOLE_TRIP_QUICK_EXPENSE_PILLS : QUICK_EXPENSE_PILLS));
</script>

<style scoped>
.quick-pills-wrap {
    background: #f8faf9;
    border-radius: 8px;
    padding: 10px 12px;
    margin-bottom: 12px;
}
.quick-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    border: 1px solid transparent;
    border-radius: 99px;
    padding: 6px 14px;
    font-size: 13px;
    font-weight: 600;
    line-height: 1.2;
    transition: background-color 0.15s ease, border-color 0.15s ease;
}
.quick-pill__icon {
    font-size: 0.95rem;
}
.pill-diesel {
    background: #eff6ff;
    color: #1d4ed8;
}
.pill-diesel:hover {
    background: #dbeafe;
    border-color: #93c5fd;
}
.pill-toll {
    background: #fefce8;
    color: #854d0e;
}
.pill-toll:hover {
    background: #fef08a;
    border-color: #facc15;
}
.pill-loading {
    background: #f0fdf4;
    color: #166534;
}
.pill-loading:hover {
    background: #dcfce7;
    border-color: #86efac;
}
.pill-unloading {
    background: #fef2f2;
    color: #991b1b;
}
.pill-unloading:hover {
    background: #fee2e2;
    border-color: #fca5a5;
}
.pill-food {
    background: #faf5ff;
    color: #6b21a8;
}
.pill-food:hover {
    background: #f3e8ff;
    border-color: #d8b4fe;
}
.pill-rto {
    background: #ecfeff;
    color: #0e7490;
}
.pill-rto:hover {
    background: #cffafe;
    border-color: #67e8f9;
}
.pill-permit {
    background: #eef2ff;
    color: #4338ca;
}
.pill-permit:hover {
    background: #e0e7ff;
    border-color: #a5b4fc;
}
.pill-other {
    background: #f9fafb;
    color: #374151;
}
.pill-other:hover {
    background: #f3f4f6;
    border-color: #d1d5db;
}
</style>
