<template>
    <div v-if="pills.length > 0" class="quick-pills-wrap">
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

const KNOWN_PILL_STYLES = {
    DIESEL: { icon: 'bi-fuel-pump', className: 'pill-diesel' },
    TOLL: { icon: 'bi-sign-turn-right', className: 'pill-toll' },
    LOADING_CHARGES: { icon: 'bi-box-arrow-in-down', className: 'pill-loading' },
    UNLOADING_CHARGES: { icon: 'bi-box-arrow-up', className: 'pill-unloading' },
    FOOD_EXPENSE: { icon: 'bi-cup-hot', className: 'pill-food' },
    DRIVER_EXPENSE: { icon: 'bi-person', className: 'pill-driver' },
    REPAIR: { icon: 'bi-wrench', className: 'pill-repair' },
    PARKING: { icon: 'bi-p-circle', className: 'pill-parking' },
    RTO_EXPENSE: { icon: 'bi-shield-check', className: 'pill-rto' },
    PERMIT_BORDER_TAX: { icon: 'bi-passport', className: 'pill-permit' },
    OTHER: { icon: 'bi-plus-lg', className: 'pill-other' },
};

const pills = computed(() => {
    if (!props.activeTypes || props.activeTypes.length === 0) {
        return [];
    }

    let types = props.activeTypes;
    if (props.scope === 'whole-trip') {
        types = types.filter((t) => !['LOADING_CHARGES', 'UNLOADING_CHARGES'].includes(t.value));
    }

    const items = types.map((t, index) => {
        const style = KNOWN_PILL_STYLES[t.value] || {
            icon: 'bi-tag',
            className: 'pill-custom-' + ((index % 5) + 1),
        };

        return {
            value: t.value,
            label: t.label,
            icon: style.icon,
            className: style.className,
        };
    });

    const otherIndex = items.findIndex((p) => p.value === 'OTHER');
    if (otherIndex !== -1) {
        const otherPill = items.splice(otherIndex, 1)[0];
        items.push(otherPill);
    }

    return items;
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
