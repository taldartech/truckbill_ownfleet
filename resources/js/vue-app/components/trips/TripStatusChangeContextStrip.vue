<template>
    <div class="trip-status-context">
        <div class="trip-status-context__item">
            <span class="trip-status-context__label">Trip Number</span>
            <span class="trip-status-context__value">{{ tripNumber || '—' }}</span>
        </div>
        <div class="trip-status-context__item">
            <span class="trip-status-context__label">Truck Number</span>
            <span class="trip-status-context__value">{{ truckNumber || '—' }}</span>
        </div>
    </div>
</template>

<script setup>
defineProps({
    tripNumber: { type: String, default: '' },
    truckNumber: { type: String, default: '' },
});
</script>

<style scoped>
.trip-status-context {
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

.trip-status-context__item {
    display: inline-flex;
    align-items: baseline;
    gap: 0.35rem;
    min-width: 0;
}

.trip-status-context__label {
    color: #64748b;
    white-space: nowrap;
}

.trip-status-context__label::after {
    content: ':';
}

.trip-status-context__value {
    font-weight: 700;
    color: var(--brand-accent-blue, #2d8cff);
}
</style>
