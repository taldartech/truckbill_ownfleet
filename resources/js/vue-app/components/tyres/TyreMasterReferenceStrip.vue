<template>
    <div v-if="tyre" class="tyre-master-reference">
        <div class="small text-muted mb-2">Tyre Master</div>
        <div class="row g-2 small">
            <div class="col-6 col-md-4">
                <span class="text-muted">Brand</span><br />
                <strong>{{ tyre.brand || '—' }}</strong>
            </div>
            <div class="col-6 col-md-4">
                <span class="text-muted">Size</span><br />
                <strong>{{ tyre.size || '—' }}</strong>
            </div>
            <div class="col-6 col-md-4">
                <span class="text-muted">Type</span><br />
                <strong>{{ formatTyreType(tyre.tyre_type) }}</strong>
            </div>
            <div class="col-6 col-md-4">
                <span class="text-muted">Purchase</span><br />
                <strong>{{ formatDate(tyre.purchase_date) }} / ₹{{ tyre.purchase_cost ?? '—' }}</strong>
            </div>
            <div class="col-6 col-md-4">
                <span class="text-muted">Expected life</span><br />
                <strong>{{ tyre.expected_life_km ?? lifeSummary?.expected_life_km ?? 40000 }} km</strong>
            </div>
            <div class="col-6 col-md-4">
                <span class="text-muted">Total KM used</span><br />
                <strong>{{ lifeSummary?.total_km_used ?? tyre.current_km ?? 0 }} km</strong>
            </div>
        </div>
    </div>
</template>

<script setup>
import { formatDate } from '../../utils/datetime';
import { formatTyreType } from '../../utils/tyreDisplay';

defineProps({
    tyre: { type: Object, default: null },
    lifeSummary: { type: Object, default: null },
});
</script>

<style scoped>
.tyre-master-reference {
    padding: 0.65rem 0.85rem;
    margin-bottom: 0.75rem;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 0.5rem;
}
</style>
