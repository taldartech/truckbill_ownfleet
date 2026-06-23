<template>
    <div ref="pickerRef" class="async-vehicle-picker" :class="{ 'is-invalid-picker': invalid }">
        <div class="picker-trigger-wrap">
            <button
                :id="inputId"
                type="button"
                class="form-select text-start picker-trigger"
                :disabled="disabled"
                @click="toggleDropdown"
            >
                <span v-if="selectedVehicle">
                    <strong>{{ selectedVehicle.registration_number }}</strong>
                    <span v-if="selectedVehicle.primary_driver_name || driverLabel" class="text-muted small d-block">
                        Driver: {{ driverLabel || selectedVehicle.primary_driver_name }}
                    </span>
                </span>
                <span v-else class="text-muted">{{ placeholder }}</span>
            </button>
            <div v-if="modelValue && !disabled" class="picker-trigger-icons">
                <button
                    type="button"
                    class="btn btn-link picker-clear-btn"
                    title="Clear"
                    @click.stop="clearSelection"
                >
                    <i class="bi bi-x-circle-fill"></i>
                </button>
            </div>
        </div>
        <div v-if="errorMessage" class="invalid-feedback d-block">{{ errorMessage }}</div>

        <Teleport to="body">
            <div
                v-if="isOpen"
                ref="dropdownRef"
                class="picker-dropdown-menu shadow picker-dropdown-menu--fixed"
                :style="dropdownStyle"
            >
                <div class="picker-search-wrap">
                    <input
                        ref="searchInputRef"
                        v-model.trim="searchQuery"
                        type="text"
                        class="form-control form-control-sm picker-search-input"
                        placeholder="Search by registration or driver name (min 2 chars)…"
                        @click.stop
                        @keydown.down.prevent="moveHighlight(1)"
                        @keydown.up.prevent="moveHighlight(-1)"
                        @keydown.enter.prevent="selectHighlighted"
                        @keydown.esc.prevent="closeDropdown"
                    />
                </div>
                <div class="picker-list-scroll" @scroll="onListScroll">
                    <div v-if="loading" class="px-3 py-2 text-muted small">Searching…</div>
                    <div v-else-if="searchQuery.length < minSearchLength" class="px-3 py-2 text-muted small">
                        Type at least {{ minSearchLength }} characters to search.
                    </div>
                    <template v-else>
                        <button
                            v-for="(item, index) in items"
                            :key="item.vehicle_id"
                            type="button"
                            class="dropdown-item picker-option async-vehicle-option"
                            :class="{
                                active: index === highlightedIndex,
                                'picker-option--disabled': !item.selectable,
                            }"
                            :disabled="!item.selectable"
                            @click="selectItem(item)"
                        >
                            <span class="picker-option-name">{{ item.registration_number }}</span>
                            <span v-if="itemDriverLine(item)" class="picker-option-meta">{{ itemDriverLine(item) }}</span>
                            <span v-if="itemReasonLine(item)" class="picker-option-reason">
                                {{ itemReasonLine(item) }}
                            </span>
                        </button>
                        <div v-if="loadingMore" class="px-3 py-2 text-muted small text-center">Loading more…</div>
                        <div v-else-if="items.length === 0" class="px-3 py-2 text-muted small">No trucks found.</div>
                    </template>
                </div>
                <PickerListFooter
                    v-if="!loading && searchQuery.length >= minSearchLength && items.length > 0"
                    :visible-count="items.length"
                    :total-count="totalResults"
                    :load-more-available="currentPage < lastPage"
                />
            </div>
        </Teleport>
    </div>
</template>

<script setup>
import { nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { searchVehiclesForTripSelection } from '../../api/vehicles';
import PickerListFooter from '../ui/PickerListFooter.vue';

const props = defineProps({
    modelValue: { type: [String, Number], default: '' },
    placeholder: { type: String, default: 'Search truck…' },
    inputId: { type: String, default: '' },
    disabled: { type: Boolean, default: false },
    invalid: { type: Boolean, default: false },
    errorMessage: { type: String, default: '' },
    asOfDate: { type: String, default: '' },
    tripEndDate: { type: String, default: '' },
    driverLabel: { type: String, default: '' },
    minSearchLength: { type: Number, default: 2 },
});

const emit = defineEmits(['update:modelValue', 'vehicle-selected']);

const pickerRef = ref(null);
const dropdownRef = ref(null);
const searchInputRef = ref(null);
const isOpen = ref(false);
const searchQuery = ref('');
const items = ref([]);
const selectedVehicle = ref(null);
const loading = ref(false);
const loadingMore = ref(false);
const highlightedIndex = ref(-1);
const dropdownStyle = ref({});
const currentPage = ref(1);
const lastPage = ref(1);
const totalResults = ref(null);
let debounceTimer = null;

function itemDriverLine(item) {
    if (item.primary_driver_name) {
        return `Driver: ${item.primary_driver_name}`;
    }

    return '';
}

function itemReasonLine(item) {
    if (!item.selectable && item.disabled_reason) {
        if (item.reason_code === 'OPEN_TRIP_EXISTS' && item.open_trip_number) {
            return `Trip Already Scheduled (${item.open_trip_number})`;
        }

        if (item.primary_driver_name) {
            return item.disabled_reason;
        }

        return item.disabled_reason;
    }

    return '';
}

function updateDropdownPosition() {
    const el = pickerRef.value;

    if (!el) {
        return;
    }

    const rect = el.getBoundingClientRect();

    dropdownStyle.value = {
        position: 'fixed',
        top: `${rect.bottom + 4}px`,
        left: `${rect.left}px`,
        width: `${Math.max(rect.width, 320)}px`,
        zIndex: '2100',
    };
}

function focusWithoutScroll(element) {
    element?.focus({ preventScroll: true });
}

function focusTrigger() {
    const trigger = pickerRef.value?.querySelector('.picker-trigger');

    focusWithoutScroll(trigger);
}

function toggleDropdown() {
    if (props.disabled) {
        return;
    }

    isOpen.value = !isOpen.value;

    if (isOpen.value) {
        searchQuery.value = '';
        items.value = [];
        highlightedIndex.value = -1;
        nextTick(() => {
            updateDropdownPosition();
            focusWithoutScroll(searchInputRef.value);
        });
    }
}

function closeDropdown({ restoreTriggerFocus = false } = {}) {
    isOpen.value = false;

    if (restoreTriggerFocus) {
        nextTick(focusTrigger);
    }
}

function clearSelection() {
    selectedVehicle.value = null;
    emit('update:modelValue', '');
    emit('vehicle-selected', null);
    closeDropdown({ restoreTriggerFocus: true });
}

function selectItem(item) {
    if (!item.selectable) {
        return;
    }

    selectedVehicle.value = item;
    emit('update:modelValue', item.vehicle_id);
    emit('vehicle-selected', item);
    closeDropdown({ restoreTriggerFocus: true });
}

function moveHighlight(delta) {
    const selectableIndexes = items.value
        .map((item, index) => (item.selectable ? index : -1))
        .filter((index) => index >= 0);

    if (selectableIndexes.length === 0) {
        return;
    }

    const currentPos = selectableIndexes.indexOf(highlightedIndex.value);
    const nextPos =
        currentPos === -1
            ? (delta > 0 ? 0 : selectableIndexes.length - 1)
            : (currentPos + delta + selectableIndexes.length) % selectableIndexes.length;

    highlightedIndex.value = selectableIndexes[nextPos];
}

function selectHighlighted() {
    const item = items.value[highlightedIndex.value];

    if (item?.selectable) {
        selectItem(item);
    }
}

async function fetchPage(page = 1, append = false) {
    const search = searchQuery.value.trim();

    if (search.length < props.minSearchLength) {
        items.value = [];

        return;
    }

    if (page === 1) {
        loading.value = true;
    } else {
        loadingMore.value = true;
    }

    try {
        const result = await searchVehiclesForTripSelection({
            search,
            page,
            perPage: 20,
            asOfDate: props.asOfDate || null,
            endAt: props.tripEndDate || null,
        });

        items.value = append ? [...items.value, ...result.data] : result.data;
        currentPage.value = result.meta.current_page ?? page;
        lastPage.value = result.meta.last_page ?? page;
        totalResults.value = result.meta.total ?? items.value.length;
        highlightedIndex.value = items.value.findIndex((item) => item.selectable);
    } finally {
        loading.value = false;
        loadingMore.value = false;
    }
}

function scheduleSearch() {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => fetchPage(1, false), 300);
}

function onListScroll(event) {
    const el = event.target;
    const nearBottom = el.scrollTop + el.clientHeight >= el.scrollHeight - 24;

    if (nearBottom && !loadingMore.value && currentPage.value < lastPage.value) {
        fetchPage(currentPage.value + 1, true);
    }
}

function handleDocumentClick(event) {
    const target = event.target;

    if (pickerRef.value?.contains(target) || dropdownRef.value?.contains(target)) {
        return;
    }

    closeDropdown();
}

function handleReposition() {
    if (isOpen.value) {
        updateDropdownPosition();
    }
}

watch(searchQuery, scheduleSearch);

watch(
    () => [props.asOfDate, props.tripEndDate],
    () => {
        if (searchQuery.value.trim().length >= props.minSearchLength) {
            fetchPage(1, false);
        }
    },
);

watch(
    () => props.modelValue,
    async (value) => {
        if (!value) {
            selectedVehicle.value = null;

            return;
        }

        if (selectedVehicle.value && String(selectedVehicle.value.vehicle_id) === String(value)) {
            return;
        }

        const match = items.value.find((item) => String(item.vehicle_id) === String(value));

        if (match) {
            selectedVehicle.value = match;
        }
    },
    { immediate: true },
);

onMounted(() => {
    document.addEventListener('click', handleDocumentClick);
    window.addEventListener('resize', handleReposition);
    window.addEventListener('scroll', handleReposition, true);
});

onBeforeUnmount(() => {
    clearTimeout(debounceTimer);
    document.removeEventListener('click', handleDocumentClick);
    window.removeEventListener('resize', handleReposition);
    window.removeEventListener('scroll', handleReposition, true);
});
</script>

<style scoped>
.async-vehicle-picker {
    position: relative;
}
.is-invalid-picker .picker-trigger {
    border-color: #dc3545;
}
.picker-trigger {
    width: 100%;
    padding-right: 2.4rem;
    min-height: 2.375rem;
    --bs-form-select-bg-icon: none;
}
.picker-trigger-wrap:has(.picker-trigger-icons) .picker-trigger {
    padding-right: 3.6rem;
}
.picker-trigger-wrap {
    position: relative;
}
.picker-trigger-icons {
    position: absolute;
    top: 0;
    right: 2rem;
    bottom: 0;
    display: flex;
    align-items: center;
    pointer-events: none;
}
.picker-clear-btn {
    position: static;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    pointer-events: auto;
    color: #6c757d;
    text-decoration: none;
    padding: 0;
    line-height: 1;
    border: none;
}
.picker-clear-btn:hover,
.picker-clear-btn:focus {
    color: #dc3545;
    text-decoration: none;
}
</style>

<style>
.picker-dropdown-menu--fixed {
    background: #fff;
    border: 1px solid #dee2e6;
    border-radius: 0.375rem;
}
.picker-search-wrap {
    padding: 0.55rem;
    border-bottom: 1px solid #dee2e6;
}
.picker-list-scroll {
    max-height: 260px;
    overflow-y: auto;
}
.async-vehicle-option.picker-option {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    white-space: normal;
    gap: 0.1rem;
    padding-top: 0.55rem;
    padding-bottom: 0.55rem;
}
.async-vehicle-option .picker-option-name {
    display: block;
    font-weight: 600;
    line-height: 1.3;
}
.async-vehicle-option .picker-option-meta {
    display: block;
    font-size: 0.8rem;
    color: #6c757d;
    line-height: 1.3;
}
.picker-option--disabled {
    opacity: 0.72;
    cursor: not-allowed;
}
.async-vehicle-option .picker-option-reason {
    display: block;
    font-size: 0.75rem;
    color: #dc3545;
    font-weight: 600;
    line-height: 1.3;
    margin-top: 0.1rem;
}
</style>
