<template>
    <div class="quick-pills-wrap">
        <div class="quick-pills">
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
    activeTypes: {
        type: Array,
        default: null,
    },
});

defineEmits(['add']);

const pills = computed(() => {
    const list = props.scope === 'whole-trip' ? WHOLE_TRIP_QUICK_EXPENSE_PILLS : QUICK_EXPENSE_PILLS;
    const knownValues = new Set(list.map(p => p.value));

    if (!props.activeTypes || props.activeTypes.length === 0) {
        return list;
    }

    const activeValues = new Set(props.activeTypes.map((t) => t.value));
    
    // Get known pills that are active
    const activeKnownPills = list.filter((pill) => activeValues.has(pill.value));

    // Get custom pills that are active but not known
    const customPills = props.activeTypes
        .filter((t) => !knownValues.has(t.value))
        .map((t, index) => ({
            value: t.value,
            label: t.label,
            icon: 'bi-tag', 
            className: 'pill-custom-' + ((index % 5) + 1), 
        }));

    // Separate OTHER to push it to the very end
    const finalPills = [...activeKnownPills, ...customPills];
    const otherIndex = finalPills.findIndex(p => p.value === 'OTHER');
    
    if (otherIndex !== -1) {
        const otherPill = finalPills.splice(otherIndex, 1)[0];
        finalPills.push(otherPill);
    }

    return finalPills;
});
</script>

<style scoped>
.quick-pills-wrap {
    background: #f8faf9;
    border-radius: 8px;
    padding: 8px 10px;
    margin-bottom: 12px;
    overflow: hidden;
}

.quick-pills {
    display: flex;
    flex-wrap: nowrap;
    align-items: center;
    gap: 6px;
    width: 100%;
    overflow-x: auto;
    overflow-y: hidden;
    scrollbar-width: thin;
    -webkit-overflow-scrolling: touch;
    padding-bottom: 2px;
}

.quick-pills::-webkit-scrollbar {
    height: 4px;
}

.quick-pills::-webkit-scrollbar-thumb {
    background: #cbd5e1;
    border-radius: 99px;
}

.quick-pill {
    display: inline-flex;
    align-items: center;
    flex: 0 0 auto;
    justify-content: center;
    gap: 0.28rem;
    min-width: max-content;
    border: 1px solid transparent;
    border-radius: 99px;
    padding: 5px 10px;
    font-size: 12px;
    font-weight: 600;
    line-height: 1.15;
    white-space: nowrap;
    transition: background-color 0.15s ease, border-color 0.15s ease;
}

.quick-pill__icon {
    font-size: 0.85rem;
}

@media (min-width: 1200px) {
    .quick-pill {
        flex: 1 1 0;
        min-width: 80px;
        padding: 5px 8px;
        font-size: 11.5px;
    }

    .quick-pill span {
        overflow: hidden;
        text-overflow: ellipsis;
    }
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
.pill-driver {
    background: #fff7ed;
    color: #c2410c;
}
.pill-driver:hover {
    background: #ffedd5;
    border-color: #fdba74;
}
.pill-repair {
    background: #f1f5f9;
    color: #334155;
}
.pill-repair:hover {
    background: #e2e8f0;
    border-color: #94a3b8;
}
.pill-parking {
    background: #ecfdf5;
    color: #047857;
}
.pill-parking:hover {
    background: #d1fae5;
    border-color: #6ee7b7;
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
.pill-custom-1 {
    background: #fdf4ff;
    color: #86198f;
}
.pill-custom-1:hover {
    background: #fae8ff;
    border-color: #f0abfc;
}
.pill-custom-2 {
    background: #f0fdfa;
    color: #0f766e;
}
.pill-custom-2:hover {
    background: #ccfbf1;
    border-color: #5eead4;
}
.pill-custom-3 {
    background: #fff1f2;
    color: #be123c;
}
.pill-custom-3:hover {
    background: #ffe4e6;
    border-color: #fda4af;
}
.pill-custom-4 {
    background: #f5f3ff;
    color: #6d28d9;
}
.pill-custom-4:hover {
    background: #ede9fe;
    border-color: #c4b5fd;
}
.pill-custom-5 {
    background: #f0f9ff;
    color: #0369a1;
}
.pill-custom-5:hover {
    background: #e0f2fe;
    border-color: #7dd3fc;
}
</style>
