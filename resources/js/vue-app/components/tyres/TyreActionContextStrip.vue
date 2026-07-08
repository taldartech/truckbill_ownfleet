<template>
    <div class="tyre-action-context">
        <div class="tyre-action-context__item">
            <span class="tyre-action-context__label">Tyre Serial</span>
            <span class="tyre-action-context__value">{{ tyreSerial || '—' }}</span>
        </div>
        <div v-if="showTruck" class="tyre-action-context__item">
            <span class="tyre-action-context__label">Truck Number</span>
            <RouterLink
                v-if="vehicleId && truckNumber && truckNumber !== '—'"
                :to="{ name: 'vehicles.tyres', params: { vehicleId } }"
                class="tyre-action-context__value tyre-action-context__value--link"
            >
                {{ truckNumber }}
            </RouterLink>
            <span v-else class="tyre-action-context__value">{{ truckNumber || '—' }}</span>
        </div>
        <div v-if="position" class="tyre-action-context__item">
            <span class="tyre-action-context__label">Position</span>
            <span class="tyre-action-context__value">{{ position }}</span>
        </div>
    </div>
</template>

<script setup>
defineProps({
    tyreSerial: { type: String, default: '' },
    truckNumber: { type: String, default: '' },
    vehicleId: { type: [String, Number], default: '' },
    position: { type: String, default: '' },
    showTruck: { type: Boolean, default: true },
});
</script>

<style scoped>
.tyre-action-context {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 0.5rem 1.25rem;
    padding: 0.65rem 0.85rem;
    background: rgba(45, 140, 255, 0.08);
    border: 1px solid rgba(45, 140, 255, 0.18);
    border-radius: 0.5rem;
    font-size: 0.8125rem;
}

.tyre-action-context__item {
    display: inline-flex;
    align-items: baseline;
    gap: 0.35rem;
    min-width: 0;
}

.tyre-action-context__label {
    color: #64748b;
    white-space: nowrap;
}

.tyre-action-context__label::after {
    content: ':';
}

.tyre-action-context__value {
    font-weight: 700;
    color: var(--brand-accent-blue, #2d8cff);
}

.tyre-action-context__value--link {
    text-decoration: none;
}

.tyre-action-context__value--link:hover {
    text-decoration: underline;
}
</style>
