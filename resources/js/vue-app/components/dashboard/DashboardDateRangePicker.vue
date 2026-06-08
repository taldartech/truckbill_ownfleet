<template>
    <div ref="rootElement" class="dashboard-date-range">
        <label class="dashboard-date-range__label">Trip period</label>

        <button
            type="button"
            class="dashboard-date-range__trigger"
            :aria-expanded="isOpen"
            aria-haspopup="listbox"
            @click="toggleDropdown"
        >
            <i class="fas fa-calendar-days dashboard-date-range__icon" aria-hidden="true"></i>
            <span class="dashboard-date-range__value">{{ appliedRangeLabel }}</span>
            <i
                class="fas fa-chevron-down dashboard-date-range__chevron"
                :class="{ 'dashboard-date-range__chevron--open': isOpen }"
                aria-hidden="true"
            ></i>
        </button>

        <div v-if="isOpen" class="dashboard-date-range__panel">
            <div class="dashboard-date-range__presets" role="listbox" aria-label="Date range presets">
                <button
                    v-for="preset in selectablePresets"
                    :key="preset.key"
                    type="button"
                    class="dashboard-date-range__preset"
                    :class="{ 'dashboard-date-range__preset--active': draftPreset === preset.key }"
                    role="option"
                    :aria-selected="draftPreset === preset.key"
                    @click="selectPreset(preset.key)"
                >
                    {{ preset.label }}
                </button>

                <button
                    type="button"
                    class="dashboard-date-range__preset"
                    :class="{ 'dashboard-date-range__preset--active': draftPreset === 'custom' }"
                    role="option"
                    :aria-selected="draftPreset === 'custom'"
                    @click="selectPreset('custom')"
                >
                    Custom Range
                </button>
            </div>

            <div v-if="draftPreset === 'custom'" class="dashboard-date-range__custom">
                <div>
                    <label class="form-label small text-muted mb-1">From</label>
                    <input
                        v-model="draftFromDate"
                        type="date"
                        class="form-control form-control-sm"
                        @change="onCustomDateChange"
                    />
                </div>
                <div>
                    <label class="form-label small text-muted mb-1">To</label>
                    <input
                        v-model="draftToDate"
                        type="date"
                        class="form-control form-control-sm"
                        :min="draftFromDate"
                        @change="onCustomDateChange"
                    />
                </div>
            </div>

            <div class="dashboard-date-range__actions">
                <button type="button" class="btn btn-sm btn-primary px-4" @click="applyDraft">
                    Apply
                </button>
                <button type="button" class="btn btn-sm btn-outline-secondary px-4" @click="cancelDraft">
                    Cancel
                </button>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import {
    DATE_RANGE_PRESETS,
    detectDateRangePreset,
    formatDateRangeLabel,
    resolveDateRangePreset,
} from '../../utils/dateRangePresets';

const props = defineProps({
    fromDate: {
        type: String,
        required: true,
    },
    toDate: {
        type: String,
        required: true,
    },
});

const emit = defineEmits(['update:fromDate', 'update:toDate', 'change']);

const rootElement = ref(null);
const isOpen = ref(false);

const appliedFromDate = ref(props.fromDate);
const appliedToDate = ref(props.toDate);

const draftFromDate = ref(props.fromDate);
const draftToDate = ref(props.toDate);
const draftPreset = ref(detectDateRangePreset(props.fromDate, props.toDate));

const selectablePresets = DATE_RANGE_PRESETS.filter((preset) => preset.key !== 'custom');

const appliedRangeLabel = computed(() => formatDateRangeLabel(appliedFromDate.value, appliedToDate.value));

watch(
    () => [props.fromDate, props.toDate],
    ([fromDate, toDate]) => {
        appliedFromDate.value = fromDate;
        appliedToDate.value = toDate;

        if (!isOpen.value) {
            resetDraftFromApplied();
        }
    },
);

function resetDraftFromApplied() {
    draftFromDate.value = appliedFromDate.value;
    draftToDate.value = appliedToDate.value;
    draftPreset.value = detectDateRangePreset(appliedFromDate.value, appliedToDate.value);
}

function toggleDropdown() {
    if (isOpen.value) {
        cancelDraft();

        return;
    }

    resetDraftFromApplied();
    isOpen.value = true;
}

function selectPreset(presetKey) {
    draftPreset.value = presetKey;

    if (presetKey === 'custom') {
        return;
    }

    const range = resolveDateRangePreset(presetKey);
    draftFromDate.value = range.from_date;
    draftToDate.value = range.to_date;
}

function onCustomDateChange() {
    draftPreset.value = 'custom';

    if (draftToDate.value && draftFromDate.value && draftToDate.value < draftFromDate.value) {
        draftToDate.value = draftFromDate.value;
    }
}

function applyDraft() {
    appliedFromDate.value = draftFromDate.value;
    appliedToDate.value = draftToDate.value;
    draftPreset.value = detectDateRangePreset(draftFromDate.value, draftToDate.value);

    emit('update:fromDate', appliedFromDate.value);
    emit('update:toDate', appliedToDate.value);
    emit('change', {
        from_date: appliedFromDate.value,
        to_date: appliedToDate.value,
    });

    isOpen.value = false;
}

function cancelDraft() {
    resetDraftFromApplied();
    isOpen.value = false;
}

function onDocumentClick(event) {
    if (!isOpen.value || !rootElement.value) {
        return;
    }

    if (!rootElement.value.contains(event.target)) {
        cancelDraft();
    }
}

function onDocumentKeydown(event) {
    if (event.key === 'Escape' && isOpen.value) {
        cancelDraft();
    }
}

onMounted(() => {
    document.addEventListener('click', onDocumentClick);
    document.addEventListener('keydown', onDocumentKeydown);
});

onUnmounted(() => {
    document.removeEventListener('click', onDocumentClick);
    document.removeEventListener('keydown', onDocumentKeydown);
});
</script>

<style scoped>
.dashboard-date-range {
    position: relative;
    width: 100%;
    max-width: none;
    margin-left: auto;
}

.dashboard-date-range__label {
    display: block;
    margin-bottom: 0.35rem;
    font-size: 0.8rem;
    font-weight: 600;
    color: #64748b;
}

.dashboard-date-range__trigger {
    display: flex;
    align-items: center;
    gap: 0.65rem;
    width: 100%;
    padding: 0.65rem 0.85rem;
    border: 1px solid #dbe3ef;
    border-radius: 0.5rem;
    background: #fff;
    color: #0f172a;
    text-align: left;
    box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
}

.dashboard-date-range__trigger:hover,
.dashboard-date-range__trigger:focus {
    border-color: #94a3b8;
    outline: none;
}

.dashboard-date-range__icon {
    color: var(--brand-primary, #2fb36f);
    font-size: 0.95rem;
}

.dashboard-date-range__value {
    flex: 1;
    min-width: 0;
    font-size: 0.92rem;
    font-weight: 500;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.dashboard-date-range__chevron {
    color: #64748b;
    font-size: 0.75rem;
    transition: transform 0.15s ease;
}

.dashboard-date-range__chevron--open {
    transform: rotate(180deg);
}

.dashboard-date-range__panel {
    position: absolute;
    top: calc(100% + 0.35rem);
    right: 0;
    left: auto;
    z-index: 30;
    width: 16rem;
    padding: 0.75rem;
    border: 1px solid #dbe3ef;
    border-radius: 0.65rem;
    background: #fff;
    box-shadow: 0 12px 30px rgba(15, 23, 42, 0.12);
}

.dashboard-date-range__presets {
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
}

.dashboard-date-range__preset {
    width: 100%;
    padding: 0.55rem 0.75rem;
    border: 0;
    border-radius: 0.45rem;
    background: #f8fafc;
    color: var(--brand-primary, #2fb36f);
    font-size: 0.88rem;
    font-weight: 500;
    text-align: left;
}

.dashboard-date-range__preset:hover {
    background: var(--brand-active-bg, #e8f5ee);
}

.dashboard-date-range__preset--active {
    background: var(--brand-primary, #2fb36f);
    color: #fff;
}

.dashboard-date-range__custom {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 0.65rem;
    margin-top: 0.75rem;
    padding-top: 0.75rem;
    border-top: 1px solid #e2e8f0;
}

.dashboard-date-range__actions {
    display: flex;
    gap: 0.5rem;
    margin-top: 0.85rem;
}
</style>
