<template>
    <div class="tmp-builder">
        <div v-if="weightBalanceError" class="trf-banner trf-banner--warn mb-3">
            <i class="bi bi-exclamation-triangle-fill"></i>
            <span>{{ weightBalanceError }}</span>
        </div>
        <div v-else-if="balance.pickupTotal > 0" class="trf-banner trf-banner--info mb-3">
            <i class="bi bi-info-circle-fill"></i>
            <span>
                Pickup {{ formatNum(balance.pickupTotal) }} ton ·
                Allocated {{ formatNum(balance.dropTotal) }} ton ·
                Remaining {{ formatNum(balance.remainingPickup) }} ton.
                Link each drop to one or more pickups with weight.
            </span>
        </div>

        <h3 class="tmp-section-title">Pickups</h3>

        <div v-if="!pickups.length" class="tmp-builder__empty mb-3">
            <i class="bi bi-geo-alt"></i>
            <p>No pickups yet. Use <strong>Add Pickup</strong>.</p>
        </div>

        <section
            v-for="(pickup, pickupIndex) in pickups"
            :key="pickup._key"
            class="tmp-pickup trf-leg__card"
        >
            <header class="tmp-pickup__header">
                <div class="tmp-pickup__title">
                    <span class="tmp-pickup__code">{{ pickup.pickup_code || `P${String(pickupIndex + 1).padStart(3, '0')}` }}</span>
                    <span class="tmp-pickup__meta">
                        Weight {{ formatNum(pickup.weight_ton) }} ton ·
                        Remaining {{ formatNum(remainingForPickup(pickup._key)) }} ton
                    </span>
                </div>
                <button
                    type="button"
                    class="trf-leg__station-clear"
                    title="Remove pickup"
                    :disabled="disabled"
                    @click="removePickup(pickupIndex)"
                >
                    <i class="bi bi-x-lg"></i>
                </button>
            </header>

            <div class="tmp-fields tmp-fields--pickup">
                <div class="trf-lf">
                    <label class="trf-lf__lbl">Station<span class="trf-pf__req">*</span></label>
                    <div class="trf-lf__picker">
                        <SearchablePickerRedesigned
                            v-model="pickup.station_id"
                            :items="stations"
                            attach-dropdown-to-body
                            placeholder="Pickup station"
                            search-placeholder="Search station…"
                            empty-message="No station found."
                            view-title="Station details"
                            :disabled="disabled"
                            :can-quick-add="false"
                            compact-actions
                        />
                    </div>
                </div>
                <div class="trf-lf">
                    <label class="trf-lf__lbl">Consignor</label>
                    <div class="trf-lf__picker">
                        <SearchablePickerRedesigned
                            v-model="pickup.consignor_id"
                            :items="customers"
                            attach-dropdown-to-body
                            placeholder="Consignor"
                            search-placeholder="Search customer…"
                            empty-message="No customer found."
                            view-title="Customer details"
                            quick-add-title="Add customer"
                            :disabled="disabled"
                            :can-quick-add="canQuickAddCustomer"
                            :show-subtext="false"
                            title-case-label
                            compact-actions
                            @quick-add="emit('quick-add-consignor', pickupIndex)"
                        />
                    </div>
                </div>
                <div class="trf-lf">
                    <label class="trf-lf__lbl">Goods<span class="trf-pf__req">*</span></label>
                    <div class="trf-lf__picker">
                        <SearchablePickerRedesigned
                            v-model="pickup.goods_name"
                            :items="goodsTypePickerItems"
                            attach-dropdown-to-body
                            placeholder="Goods type"
                            search-placeholder="Search goods…"
                            empty-message="No goods found."
                            quick-add-title="Add goods type"
                            :disabled="disabled"
                            :can-quick-add="canQuickAddGoodsType"
                            :can-view="false"
                            compact-actions
                            @quick-add="emit('quick-add-goods-type', pickupIndex)"
                        />
                    </div>
                </div>
                <div class="trf-lf tmp-field--narrow">
                    <label class="trf-lf__lbl">Weight (t)<span class="trf-pf__req">*</span></label>
                    <div class="trf-num-field">
                        <input
                            v-model.number="pickup.weight_ton"
                            v-no-scroll-change
                            type="number"
                            min="0.001"
                            step="0.001"
                            class="trf-input"
                            :disabled="disabled"
                            placeholder="0.000"
                        />
                    </div>
                </div>
                <div class="trf-lf tmp-field--narrow">
                    <label class="trf-lf__lbl">Date</label>
                    <input
                        v-model="pickup.pickup_date"
                        type="date"
                        class="trf-input"
                        :disabled="disabled"
                    />
                </div>
            </div>
        </section>

        <h3 class="tmp-section-title tmp-section-title--drops">Drops</h3>

        <div v-if="!drops.length" class="tmp-builder__empty">
            <i class="bi bi-flag"></i>
            <p>No drops yet. Use <strong>Add Drop</strong>.</p>
            <p class="tmp-builder__empty-hint">
                Select which pickup(s) feed each drop. One drop can use multiple pickups; one pickup can feed multiple drops.
            </p>
        </div>

        <section
            v-for="(drop, dropIndex) in drops"
            :key="drop._key"
            class="tmp-drop-card trf-leg__card"
        >
            <header class="tmp-pickup__header">
                <div class="tmp-pickup__title">
                    <span class="tmp-pickup__code tmp-pickup__code--drop">
                        {{ drop.drop_code || `D${String(dropIndex + 1).padStart(3, '0')}` }}
                    </span>
                    <span class="tmp-pickup__meta">
                        Total {{ formatNum(drop.weight_ton) }} ton ·
                        Freight ₹{{ formatNum(drop.freight_amount) }}
                    </span>
                </div>
                <button
                    type="button"
                    class="trf-leg__station-clear"
                    title="Remove drop"
                    :disabled="disabled || drops.length <= 1"
                    @click="removeDrop(dropIndex)"
                >
                    <i class="bi bi-x-lg"></i>
                </button>
            </header>

            <div class="tmp-fields tmp-fields--drop">
                <div class="trf-lf">
                    <label class="trf-lf__lbl">Station<span class="trf-pf__req">*</span></label>
                    <div class="trf-lf__picker">
                        <SearchablePickerRedesigned
                            v-model="drop.to_station_id"
                            :items="stations"
                            attach-dropdown-to-body
                            placeholder="Drop station"
                            search-placeholder="Search station…"
                            empty-message="No station found."
                            view-title="Station details"
                            :disabled="disabled"
                            :can-quick-add="false"
                            compact-actions
                        />
                    </div>
                </div>
                <div class="trf-lf">
                    <label class="trf-lf__lbl">Consignee</label>
                    <div class="trf-lf__picker">
                        <SearchablePickerRedesigned
                            v-model="drop.consignee_id"
                            :items="customers"
                            attach-dropdown-to-body
                            placeholder="Consignee"
                            search-placeholder="Search customer…"
                            empty-message="No customer found."
                            view-title="Customer details"
                            quick-add-title="Add customer"
                            :disabled="disabled"
                            :can-quick-add="canQuickAddCustomer"
                            :show-subtext="false"
                            title-case-label
                            compact-actions
                            @quick-add="emit('quick-add-consignee', dropIndex)"
                        />
                    </div>
                </div>
            </div>

            <div class="tmp-allocations">
                <div class="tmp-allocations__head">
                    <span class="tmp-allocations__title">From pickups<span class="trf-pf__req">*</span></span>
                    <button
                        type="button"
                        class="trf-head-btn trf-head-btn--ghost"
                        :disabled="disabled || !pickups.length"
                        @click="addAllocation(drop)"
                    >
                        <i class="bi bi-plus-lg"></i>
                        <span>Add pickup source</span>
                    </button>
                </div>

                <div
                    v-for="(allocation, allocationIndex) in drop.allocations"
                    :key="allocation._key"
                    class="tmp-allocation"
                >
                    <div class="tmp-allocation__line tmp-allocation__line--primary">
                        <div class="trf-lf">
                            <label class="trf-lf__lbl">Pickup<span class="trf-pf__req">*</span></label>
                            <select
                                v-model="allocation.pickup_key"
                                class="trf-input"
                                :disabled="disabled"
                            >
                                <option value="">Select pickup</option>
                                <option
                                    v-for="option in pickupSelectOptions(drop, allocation)"
                                    :key="option.key"
                                    :value="option.key"
                                >
                                    {{ option.label }}
                                </option>
                            </select>
                        </div>
                        <div class="trf-lf tmp-field--narrow">
                            <label class="trf-lf__lbl">Weight (t)<span class="trf-pf__req">*</span></label>
                            <div class="trf-num-field">
                                <input
                                    v-model.number="allocation.weight_ton"
                                    v-no-scroll-change
                                    type="number"
                                    min="0.001"
                                    step="0.001"
                                    class="trf-input"
                                    :disabled="disabled"
                                    placeholder="0.000"
                                    @input="onAllocationWeightChange(drop, allocation)"
                                />
                            </div>
                        </div>
                        <div class="trf-lf tmp-field--narrow">
                            <label class="trf-lf__lbl">Rate (₹/t)</label>
                            <input
                                v-model.number="allocation.freight_rate"
                                v-no-scroll-change
                                type="number"
                                min="0"
                                step="0.01"
                                class="trf-input"
                                :disabled="disabled"
                                placeholder="0.00"
                                @input="onAllocationRateChange(drop, allocation)"
                            />
                        </div>
                        <div class="trf-lf tmp-field--narrow">
                            <label class="trf-lf__lbl">Freight (₹)</label>
                            <div class="trf-num-field trf-num-field--prefix">
                                <span class="trf-num-field__prefix">₹</span>
                                <input
                                    v-model.number="allocation.freight_amount"
                                    v-no-scroll-change
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    class="trf-input"
                                    :disabled="disabled"
                                    placeholder="0.00"
                                    @input="onAllocationFreightChange(drop, allocation)"
                                />
                            </div>
                        </div>
                        <button
                            type="button"
                            class="trf-leg__station-clear tmp-allocation__remove"
                            title="Remove source"
                            :disabled="disabled || drop.allocations.length <= 1"
                            @click="removeAllocation(drop, allocationIndex)"
                        >
                            <i class="bi bi-x-lg"></i>
                        </button>
                    </div>
                    <div class="tmp-allocation__line tmp-allocation__line--cargo">
                        <div class="trf-lf tmp-field--narrow">
                            <label class="trf-lf__lbl">Distance (km)</label>
                            <input
                                v-model.number="allocation.distance_km"
                                v-no-scroll-change
                                type="number"
                                min="0"
                                step="0.01"
                                class="trf-input"
                                :disabled="disabled"
                                placeholder="0"
                            />
                        </div>
                        <div class="trf-lf tmp-field--narrow">
                            <label class="trf-lf__lbl">Packages</label>
                            <input
                                v-model.number="allocation.no_of_units"
                                v-no-scroll-change
                                type="number"
                                min="0"
                                step="1"
                                class="trf-input"
                                :disabled="disabled"
                                placeholder="0"
                            />
                        </div>
                        <div class="trf-lf">
                            <label class="trf-lf__lbl">E-way bill</label>
                            <input
                                v-model="allocation.ewaybill_no"
                                type="text"
                                maxlength="64"
                                class="trf-input"
                                :disabled="disabled"
                                placeholder="E-way bill no."
                            />
                        </div>
                        <div class="trf-lf tmp-field--narrow">
                            <label class="trf-lf__lbl">E-way expiry</label>
                            <input
                                v-model="allocation.ewaybill_expiry"
                                type="date"
                                class="trf-input"
                                :disabled="disabled"
                            />
                        </div>
                        <div class="trf-lf tmp-field--narrow">
                            <label class="trf-lf__lbl">Goods / declared value (₹)</label>
                            <div class="trf-num-field trf-num-field--prefix">
                                <span class="trf-num-field__prefix">₹</span>
                                <input
                                    v-model.number="allocation.goods_value"
                                    v-no-scroll-change
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    class="trf-input"
                                    :disabled="disabled"
                                    placeholder="0.00"
                                />
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import SearchablePickerRedesigned from '../ui/SearchablePickerRedesigned.vue';
import {
    emptyAllocation,
    multiPointWeightBalance,
    syncDropWeightFromAllocations,
} from '../../utils/multiPointDrops';

const props = defineProps({
    pickups: { type: Array, required: true },
    drops: { type: Array, required: true },
    stations: { type: Array, default: () => [] },
    customers: { type: Array, default: () => [] },
    goodsTypes: { type: Array, default: () => [] },
    canQuickAddCustomer: { type: Boolean, default: false },
    canQuickAddGoodsType: { type: Boolean, default: false },
    disabled: { type: Boolean, default: false },
});

const emit = defineEmits(['quick-add-consignor', 'quick-add-consignee', 'quick-add-goods-type']);

const goodsTypePickerItems = computed(() => {
    const names = new Set();
    const items = [];

    (props.goodsTypes ?? []).forEach((type) => {
        const name = String(type?.name ?? '').trim();
        if (!name || names.has(name)) {
            return;
        }
        names.add(name);
        items.push({ id: name, name });
    });

    (props.pickups ?? []).forEach((pickup) => {
        const name = String(pickup?.goods_name ?? '').trim();
        if (!name || names.has(name)) {
            return;
        }
        names.add(name);
        items.push({ id: name, name });
    });

    return items;
});

const balance = computed(() => multiPointWeightBalance(props.pickups, props.drops));

const weightBalanceError = computed(() => {
    if (balance.value.excessPickups.length > 0) {
        const first = balance.value.excessPickups[0];

        return `${first.pickup_code || 'Pickup'}: allocated ${formatNum(first.allocated)} ton exceeds pickup ${formatNum(first.pickupWeight)} ton.`;
    }

    if (balance.value.excessDrop > 0.0005) {
        return `Total allocated drop weight exceeds total pickup weight by ${formatNum(balance.value.excessDrop)} ton.`;
    }

    return '';
});

let keySeq = 1;

function nextKey(prefix) {
    keySeq += 1;

    return `${prefix}-${keySeq}`;
}

function stationName(stationId) {
    if (!stationId) {
        return 'No station';
    }

    return props.stations.find((station) => String(station.id) === String(stationId))?.name ?? `#${stationId}`;
}

function remainingForPickup(pickupKey) {
    const row = balance.value.perPickup.find((item) => item.pickup_key === String(pickupKey));

    return row?.remaining ?? 0;
}

function pickupSelectOptions(drop, currentAllocation) {
    const selectedElsewhere = new Set(
        (drop.allocations ?? [])
            .filter((allocation) => allocation !== currentAllocation && allocation.pickup_key)
            .map((allocation) => String(allocation.pickup_key)),
    );

    return (props.pickups ?? []).map((pickup, index) => {
        const key = String(pickup._key);
        const code = pickup.pickup_code || `P${String(index + 1).padStart(3, '0')}`;
        const remaining = remainingForPickup(key);
        const disabledHint = selectedElsewhere.has(key) && String(currentAllocation.pickup_key) !== key
            ? ' (already on this drop)'
            : '';

        return {
            key,
            label: `${code} · ${stationName(pickup.station_id)} · ${formatNum(pickup.weight_ton)}t (rem ${formatNum(remaining)})${disabledHint}`,
        };
    }).filter((option) => {
        const selectedElsewhereOnDrop = (drop.allocations ?? []).some(
            (allocation) => allocation !== currentAllocation && String(allocation.pickup_key) === option.key,
        );

        return !selectedElsewhereOnDrop || String(currentAllocation.pickup_key) === option.key;
    });
}

function emptyDrop() {
    return {
        _key: nextKey('drop'),
        drop_code: '',
        to_station_id: '',
        consignee_id: '',
        to_address: '',
        weight_ton: '',
        freight_amount: 0,
        lr_number: '',
        remarks: '',
        allocations: [emptyAllocation()],
    };
}

function emptyPickup() {
    return {
        _key: nextKey('pickup'),
        pickup_code: '',
        station_id: '',
        consignor_id: '',
        from_address: '',
        goods_name: '',
        weight_ton: '',
        pickup_date: '',
    };
}

function renumberCodes() {
    props.pickups.forEach((pickup, index) => {
        pickup.pickup_code = `P${String(index + 1).padStart(3, '0')}`;
    });

    props.drops.forEach((drop, index) => {
        drop.drop_code = `D${String(index + 1).padStart(3, '0')}`;
    });
}

function ensureDropAllocations(drop) {
    if (!Array.isArray(drop.allocations) || drop.allocations.length === 0) {
        drop.allocations = [emptyAllocation()];

        return;
    }

    drop.allocations = drop.allocations.map((allocation) => {
        const defaults = emptyAllocation();

        return {
            ...defaults,
            ...allocation,
            _key: allocation._key || defaults._key,
            pickup_key: allocation.pickup_key ? String(allocation.pickup_key) : '',
        };
    });
}

function addPickup() {
    props.pickups.push(emptyPickup());
    renumberCodes();
}

function removePickup(index) {
    const [removed] = props.pickups.splice(index, 1);
    const removedKey = String(removed?._key ?? '');

    props.drops.forEach((drop) => {
        ensureDropAllocations(drop);
        drop.allocations = drop.allocations.filter(
            (allocation) => String(allocation.pickup_key) !== removedKey,
        );

        if (drop.allocations.length === 0) {
            drop.allocations = [emptyAllocation()];
        }

        syncDropWeightFromAllocations(drop);
    });

    renumberCodes();
}

function addDrop() {
    props.drops.push(emptyDrop());
    renumberCodes();
}

function removeDrop(index) {
    if (props.drops.length <= 1) {
        return;
    }

    props.drops.splice(index, 1);
    renumberCodes();
}

function addAllocation(drop) {
    ensureDropAllocations(drop);
    drop.allocations.push(emptyAllocation());
}

function removeAllocation(drop, allocationIndex) {
    ensureDropAllocations(drop);

    if (drop.allocations.length <= 1) {
        return;
    }

    drop.allocations.splice(allocationIndex, 1);
    syncDropWeightFromAllocations(drop);
}

function onAllocationWeightChange(drop, allocation) {
    if (allocation) {
        allocation.freight_auto_calculate = true;
    }
    syncDropWeightFromAllocations(drop);
}

function onAllocationRateChange(drop, allocation) {
    allocation.freight_auto_calculate = true;
    syncDropWeightFromAllocations(drop);
}

function onAllocationFreightChange(drop, allocation) {
    allocation.freight_auto_calculate = false;
    syncDropWeightFromAllocations(drop);
}

function formatNum(value) {
    const num = Number(value);

    if (!Number.isFinite(num)) {
        return '0';
    }

    return num.toLocaleString('en-IN', { maximumFractionDigits: 3 });
}

defineExpose({ addPickup, addDrop, emptyPickup, emptyDrop, renumberCodes });
</script>

<style scoped>
.tmp-section-title {
    margin: 0 0 0.65rem;
    font-size: 0.8rem;
    font-weight: 700;
    letter-spacing: 0.04em;
    text-transform: uppercase;
    color: #64748b;
}

.tmp-section-title--drops {
    margin-top: 1.15rem;
}

.tmp-builder__empty {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.35rem;
    padding: 1.5rem 1rem;
    border: 1.5px dashed #cbd5e1;
    border-radius: 12px;
    color: #64748b;
    font-size: 0.875rem;
    text-align: center;
    background: #f8fafc;
}

.tmp-builder__empty i {
    font-size: 1.35rem;
    color: #94a3b8;
}

.tmp-builder__empty-hint {
    margin: 0;
    font-size: 0.78rem;
    color: #94a3b8;
    max-width: 32rem;
}

.tmp-pickup,
.tmp-drop-card {
    margin-bottom: 0.85rem;
}

.tmp-pickup__header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
    padding: 0.4rem 0.65rem;
    border-bottom: 1px solid #e2e8f0;
}

.tmp-pickup__title {
    display: flex;
    flex-wrap: wrap;
    align-items: baseline;
    gap: 0.5rem 0.75rem;
    min-width: 0;
}

.tmp-pickup__code {
    font-size: 0.8rem;
    font-weight: 700;
    color: #0f172a;
}

.tmp-pickup__code--drop {
    color: #146c43;
}

.tmp-pickup__meta {
    font-size: 0.72rem;
    color: #64748b;
}

.tmp-fields {
    display: grid;
    gap: 0.4rem 0.55rem;
    padding: 0.55rem 0.65rem;
}

.tmp-fields--pickup {
    grid-template-columns: minmax(0, 1.3fr) minmax(0, 1.3fr) minmax(0, 1.1fr) minmax(5.5rem, 0.55fr) minmax(7.5rem, 0.7fr);
}

.tmp-fields--drop {
    grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
}

.tmp-allocations {
    border-top: 1px solid #e2e8f0;
    padding: 0.45rem 0.65rem 0.6rem;
    background: #f8fafc;
}

.tmp-allocations__head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
    margin-bottom: 0.4rem;
}

.tmp-allocations__title {
    font-size: 0.72rem;
    font-weight: 700;
    color: #334155;
}

.tmp-allocation {
    margin-bottom: 0.45rem;
    padding: 0.4rem 0.45rem;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    background: #fff;
}

.tmp-allocation:last-child {
    margin-bottom: 0;
}

.tmp-allocation__line {
    display: grid;
    gap: 0.35rem 0.5rem;
    align-items: end;
}

.tmp-allocation__line--primary {
    grid-template-columns: minmax(0, 1.5fr) minmax(5.5rem, 0.5fr) minmax(5.5rem, 0.5fr) minmax(6.5rem, 0.6fr) 28px;
}

.tmp-allocation__line--cargo {
    margin-top: 0.35rem;
    grid-template-columns: minmax(5.5rem, 0.55fr) minmax(5rem, 0.5fr) minmax(0, 1.1fr) minmax(7.5rem, 0.7fr) minmax(8rem, 0.85fr);
}

.tmp-allocation__remove {
    margin-bottom: 2px;
}

.trf-lf {
    display: flex;
    flex-direction: column;
    gap: 0.15rem;
    min-width: 0;
}

.trf-lf__lbl {
    font-size: 0.64rem;
    font-weight: 700;
    letter-spacing: 0.02em;
    color: #64748b;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.trf-pf__req {
    color: #dc2626;
    margin-left: 2px;
}

.trf-input {
    width: 100%;
    min-height: 30px;
    padding: 4px 8px;
    border: 1.5px solid #e2e8f0;
    border-radius: 7px;
    font-size: 0.8125rem;
    font-family: inherit;
    background: #fff;
    color: #0f172a;
}

.trf-input:focus {
    outline: none;
    border-color: #0d6efd;
    box-shadow: 0 0 0 3px rgba(13, 110, 253, 0.15);
}

.trf-input:disabled {
    background: #f8fafc;
    color: #94a3b8;
    cursor: not-allowed;
}

.trf-num-field {
    position: relative;
}

.trf-num-field--prefix .trf-input {
    padding-left: 1.5rem;
}

.trf-num-field__prefix {
    position: absolute;
    left: 0.55rem;
    top: 50%;
    transform: translateY(-50%);
    font-size: 0.8rem;
    color: #64748b;
    pointer-events: none;
}

.trf-lf__picker :deep(.searchable-picker-redesigned),
.trf-lf__picker :deep(.picker-main),
.trf-lf__picker :deep(.picker-trigger-wrap) {
    width: 100%;
}

.trf-lf__picker :deep(.redesigned-picker-trigger) {
    min-height: 30px;
    border-radius: 7px;
}

.trf-leg__card {
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    background: #fff;
    box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
    overflow: hidden;
}

.tmp-drop-card {
    border-left: 3px solid #198754;
}

.trf-leg__station-clear {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 28px;
    height: 28px;
    border: none;
    border-radius: 6px;
    background: transparent;
    color: #94a3b8;
    cursor: pointer;
    flex-shrink: 0;
}

.trf-leg__station-clear:hover:not(:disabled) {
    background: #fef2f2;
    color: #dc2626;
}

.trf-leg__station-clear:disabled {
    opacity: 0.35;
    cursor: not-allowed;
}

.trf-head-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    min-height: 30px;
    padding: 4px 10px;
    border-radius: 8px;
    font-size: 0.78rem;
    font-weight: 600;
    font-family: inherit;
    cursor: pointer;
    white-space: nowrap;
}

.trf-head-btn--ghost {
    background: #fff;
    color: #146c43;
    border: 1.5px solid #198754;
}

.trf-head-btn--ghost:hover:not(:disabled) {
    background: #f0fdf4;
}

.trf-head-btn:disabled {
    opacity: 0.4;
    cursor: not-allowed;
}

.trf-banner {
    display: flex;
    align-items: flex-start;
    gap: 0.5rem;
    padding: 0.65rem 0.85rem;
    border-radius: 8px;
    font-size: 0.82rem;
}

.trf-banner--warn {
    background: #fffbeb;
    border: 1px solid #fde68a;
    color: #92400e;
}

.trf-banner--info {
    background: #eff6ff;
    border: 1px solid #bfdbfe;
    color: #1e40af;
}

@media (max-width: 1100px) {
    .tmp-fields--pickup {
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }

    .tmp-fields--drop,
    .tmp-allocation__line--primary,
    .tmp-allocation__line--cargo {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .tmp-allocation__remove {
        grid-column: 2;
        justify-self: end;
    }
}

@media (max-width: 700px) {
    .tmp-fields--pickup,
    .tmp-fields--drop,
    .tmp-allocation__line--primary,
    .tmp-allocation__line--cargo {
        grid-template-columns: 1fr;
    }

    .tmp-allocation__remove {
        grid-column: 1;
        justify-self: end;
    }
}
</style>
