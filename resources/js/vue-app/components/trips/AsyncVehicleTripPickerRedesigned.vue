<template>
    <div ref="pickerRef" class="async-vehicle-picker async-vehicle-picker-redesigned" :class="{ 'is-invalid-picker': invalid }">
        <div v-if="selectedVehicle" class="picker-header-actions">
            <button
                type="button"
                class="picker-header-btn picker-header-btn--view"
                title="Truck details"
                aria-label="Truck details"
                :aria-expanded="infoOpen"
                @click.stop="toggleInfo"
            >
                <i class="bi bi-eye-fill"></i>
            </button>
        </div>
        <div class="picker-trigger-wrap">
            <button
                :id="inputId"
                type="button"
                class="form-select text-start picker-trigger redesigned-picker-trigger"
                :disabled="disabled"
                @click="toggleDropdown"
            >
                <div class="picker-trigger-content-wrap">
                    <template v-if="selectedVehicle">
                        <div class="picker-trigger-main-text">
                            {{ selectedVehicle.registration_number }}
                        </div>
                        <div class="picker-trigger-sub-text text-muted small d-block">
                            <span v-if="selectedVehicle.make || selectedVehicle.model">
                                {{ selectedVehicle.make || '' }} {{ selectedVehicle.model || '' }}
                            </span>
                            <span v-else>
                                Truck
                            </span>
                            <span v-if="selectedVehicle.primary_driver_name || driverLabel">
                                &bull; Driver: {{ driverLabel || selectedVehicle.primary_driver_name }}
                            </span>
                        </div>
                    </template>
                    <span v-else class="text-muted picker-trigger-main-text text-placeholder">{{ placeholder }}</span>
                </div>
            </button>
            <div v-if="modelValue && selectedVehicle && !disabled" class="picker-suffix-actions" aria-label="Input actions">
                <button
                    type="button"
                    class="picker-suffix-btn"
                    title="Clear"
                    aria-label="Clear truck"
                    @click.stop="clearSelection"
                >
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>
        </div>
        <div v-if="errorMessage" class="invalid-feedback d-block">{{ errorMessage }}</div>

        <Teleport to="body">
            <Transition name="picker-info">
                <div
                    v-if="infoOpen && selectedVehicle"
                    ref="infoRef"
                    class="vehicle-info-popover vehicle-info-popover--overlay"
                    role="dialog"
                    aria-label="Truck details"
                    :style="infoStyle"
                    @click.stop
                >
                    <div class="vehicle-info-title">Truck Details</div>
                    <dl class="vehicle-info-dl mb-0">
                        <dt>Registration</dt>
                        <dd>{{ selectedVehicle.registration_number }}</dd>
                        <template v-if="selectedVehicle.make || selectedVehicle.model">
                            <dt>Vehicle</dt>
                            <dd>{{ [selectedVehicle.make, selectedVehicle.model].filter(Boolean).join(' ') }}</dd>
                        </template>
                        <template v-if="selectedVehicle.primary_driver_name || driverLabel">
                            <dt>Driver</dt>
                            <dd>{{ driverLabel || selectedVehicle.primary_driver_name }}</dd>
                        </template>
                        <template v-if="selectedVehicle.odometer_km != null">
                            <dt>Odometer</dt>
                            <dd>{{ selectedVehicle.odometer_km }} KM</dd>
                        </template>
                        <template v-if="selectedVehicle.max_load_ton != null">
                            <dt>Max Load</dt>
                            <dd>{{ selectedVehicle.max_load_ton }} Ton</dd>
                        </template>
                    </dl>
                </div>
            </Transition>
        </Teleport>

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
                            @click="selectItem(item)"
                        >
                            <span
                                class="picker-option-name"
                                :class="{ 'picker-option-name--inactive': item.vehicle_status === 'inactive' }"
                            >
                                {{ item.registration_number }}
                                <span v-if="item.vehicle_status === 'inactive'" class="picker-option-badge">
                                    (Inactive)
                                </span>
                            </span>
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
import { searchVehiclesForTripSelection, fetchVehicle } from '../../api/vehicles';
import { claimExclusiveDropdown, releaseExclusiveDropdown } from '../../composables/useExclusiveDropdown';
import { useAppToast } from '../../composables/useAppToast';
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
const toast = useAppToast();

const pickerRef = ref(null);
const dropdownRef = ref(null);
const infoRef = ref(null);
const searchInputRef = ref(null);
const isOpen = ref(false);
const infoOpen = ref(false);
const searchQuery = ref('');
const items = ref([]);
const selectedVehicle = ref(null);
const loading = ref(false);
const loadingMore = ref(false);
const highlightedIndex = ref(-1);
const dropdownStyle = ref({});
const infoStyle = ref({});
const currentPage = ref(1);
const lastPage = ref(1);
const totalResults = ref(null);
let debounceTimer = null;

function sortItemsBySelectable(list) {
    return [...list].sort((left, right) => {
        if (left.selectable === right.selectable) {
            return 0;
        }

        return left.selectable ? -1 : 1;
    });
}

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

function updateInfoPosition() {
    const el = pickerRef.value;

    if (!el) {
        return;
    }

    const rect = el.getBoundingClientRect();
    const width = Math.min(320, Math.max(rect.width, 240));
    let left = rect.left;

    if (left + width > window.innerWidth - 8) {
        left = Math.max(8, window.innerWidth - width - 8);
    }

    let top = rect.bottom + 6;
    const estimatedHeight = 220;

    if (top + estimatedHeight > window.innerHeight - 8 && rect.top > estimatedHeight + 8) {
        top = Math.max(8, rect.top - estimatedHeight - 6);
    }

    infoStyle.value = {
        position: 'fixed',
        top: `${top}px`,
        left: `${left}px`,
        width: `${width}px`,
        zIndex: '2200',
    };
}

function focusWithoutScroll(element) {
    element?.focus({ preventScroll: true });
}

function focusTrigger() {
    const trigger = pickerRef.value?.querySelector('.picker-trigger');

    focusWithoutScroll(trigger);
}

function closeDropdown({ restoreTriggerFocus = false } = {}) {
    if (!isOpen.value) {
        return;
    }

    isOpen.value = false;
    releaseExclusiveDropdown(closeDropdown);

    if (restoreTriggerFocus) {
        nextTick(focusTrigger);
    }
}

function openDropdown() {
    claimExclusiveDropdown(closeDropdown);
    infoOpen.value = false;
    searchQuery.value = '';
    items.value = [];
    highlightedIndex.value = -1;
    isOpen.value = true;

    nextTick(() => {
        updateDropdownPosition();
        focusWithoutScroll(searchInputRef.value);
    });
}

function toggleDropdown() {
    if (props.disabled) {
        return;
    }

    if (isOpen.value) {
        closeDropdown();
    } else {
        openDropdown();
    }
}

function clearSelection() {
    selectedVehicle.value = null;
    infoOpen.value = false;
    emit('update:modelValue', '');
    emit('vehicle-selected', null);
    closeDropdown({ restoreTriggerFocus: true });
}

function toggleInfo() {
    if (!selectedVehicle.value) {
        return;
    }

    closeDropdown();
    infoOpen.value = !infoOpen.value;

    if (infoOpen.value) {
        nextTick(() => updateInfoPosition());
    }
}

function selectItem(item) {
    if (!item.selectable) {
        const reason = itemReasonLine(item) || item.disabled_reason || 'This truck is not available for the selected dates.';
        toast.warning(reason);

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

        const merged = append ? [...items.value, ...result.data] : result.data;
        items.value = sortItemsBySelectable(merged);
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

    if (
        pickerRef.value?.contains(target)
        || dropdownRef.value?.contains(target)
        || infoRef.value?.contains(target)
    ) {
        return;
    }

    closeDropdown();
    infoOpen.value = false;
}

function handleReposition() {
    if (isOpen.value) {
        updateDropdownPosition();
    }

    if (infoOpen.value) {
        updateInfoPosition();
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

            return;
        }

        try {
            const vehicle = await fetchVehicle(value);

            if (String(props.modelValue) !== String(value)) {
                return;
            }

            selectedVehicle.value = {
                vehicle_id: vehicle.id,
                registration_number: vehicle.registration_number,
                make: vehicle.make,
                model: vehicle.model,
                odometer_km: vehicle.odometer_km,
                max_load_ton: vehicle.max_load_ton,
                default_mileage_km_per_liter: vehicle.default_mileage_km_per_liter,
                expected_mileage_km_per_liter: vehicle.expected_mileage_km_per_liter,
                fuel_tank_capacity_ltr: vehicle.fuel_tank_capacity_ltr,
                selectable: true,
                primary_driver_id: vehicle.primary_driver_id ?? null,
                primary_driver_name: vehicle.primary_driver_name ?? vehicle.primary_driver?.name ?? '',
            };
        } catch {
            if (String(props.modelValue) === String(value)) {
                selectedVehicle.value = null;
            }
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
    releaseExclusiveDropdown(closeDropdown);

    if (isOpen.value) {
        closeDropdown();
    }
});
</script>

<style scoped>
.async-vehicle-picker-redesigned,
.picker-trigger-wrap {
    position: relative;
    width: 100%;
}
.async-vehicle-picker-redesigned :deep(.redesigned-picker-trigger) {
    width: 100%;
    min-height: 44px;
    padding-top: 10px;
    padding-right: 4.5rem;
    padding-bottom: 10px;
    line-height: 1.25;
    background-color: #fff;
    border: 1px solid #dee2e6;
    border-radius: 8px;
    font-size: 14px;
    box-shadow: 0 1px 2px rgba(0,0,0,0.05);
}
.async-vehicle-picker-redesigned :deep(.redesigned-picker-trigger):focus {
    border-color: #86b7fe;
    box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
}
.picker-trigger-content-wrap {
    display: flex;
    flex-direction: column;
    width: 100%;
}
.picker-trigger-main-text {
    font-weight: 600;
    color: #1e293b;
    font-size: 15px;
}
.picker-trigger-main-text.text-placeholder {
    font-weight: var(--form-placeholder-weight, 400);
    color: var(--form-placeholder-color, #9ca3af);
}
.picker-trigger-sub-text {
    font-size: 12px;
    color: #64748b;
    margin-top: 2px;
}
.picker-header-actions {
    position: absolute;
    top: -29px;
    right: 0;
    z-index: 3;
}
.picker-header-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 26px;
    height: 26px;
    padding: 0;
    border: 1px solid #e2e8f0;
    border-radius: 7px;
    background: #f8fafc;
    color: #64748b;
    font-size: 0.78rem;
    transition: background-color 150ms ease, color 150ms ease, border-color 150ms ease, box-shadow 150ms ease;
}
.picker-header-btn:hover,
.picker-header-btn:focus-visible {
    outline: none;
}
.picker-header-btn--view {
    color: #2563eb;
    background: #eff6ff;
    border-color: #bfdbfe;
}
.picker-header-btn--view:hover,
.picker-header-btn--view:focus-visible,
.picker-header-btn--view[aria-expanded='true'] {
    background: #dbeafe;
    color: #1d4ed8;
    border-color: #93c5fd;
}
.picker-header-btn--view[aria-expanded='true'] {
    box-shadow: 0 0 0 2px rgba(37, 99, 235, 0.12);
}
.picker-suffix-actions {
    position: absolute;
    top: 0;
    right: 2rem;
    bottom: 0;
    display: flex;
    align-items: center;
    gap: 2px;
    z-index: 2;
}
.picker-suffix-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 30px;
    height: 30px;
    padding: 0;
    border: 0;
    border-radius: 6px;
    background: transparent;
    color: #6c757d;
    font-size: 0.85rem;
    transition: background-color 150ms ease, color 150ms ease;
}
.picker-suffix-btn:hover,
.picker-suffix-btn:focus-visible {
    background: #e7f1ff;
    color: #0d6efd;
    outline: none;
}
.picker-suffix-btn:hover,
.picker-suffix-btn:focus-visible {
    background: #f8d7da;
    color: #dc3545;
}
.vehicle-info-popover {
    position: relative;
    z-index: 5;
    width: min(300px, 100%);
    margin-top: 6px;
    padding: 14px 16px;
    border: 1px solid #dee2e6;
    border-radius: 10px;
    background: #fff;
    box-shadow: 0 10px 30px rgba(33, 37, 41, 0.12);
}
.vehicle-info-popover--overlay {
    position: fixed;
    margin-top: 0;
    z-index: 2200;
    box-shadow: 0 12px 32px rgba(15, 23, 42, 0.16);
}
.vehicle-info-title {
    margin-bottom: 10px;
    padding-bottom: 8px;
    border-bottom: 1px solid #eef1f4;
    color: #212529;
    font-size: 0.875rem;
    font-weight: 700;
}
.vehicle-info-dl {
    display: grid;
    grid-template-columns: minmax(76px, auto) 1fr;
    gap: 6px 12px;
    font-size: 0.8125rem;
}
.vehicle-info-dl dt {
    margin: 0;
    color: #6c757d;
    font-weight: 600;
}
.vehicle-info-dl dd {
    min-width: 0;
    margin: 0;
    overflow-wrap: anywhere;
    color: #212529;
}
.picker-info-enter-active,
.picker-info-leave-active {
    transition: opacity 150ms ease, transform 150ms ease;
}
.picker-info-enter-from,
.picker-info-leave-to {
    opacity: 0;
    transform: translateY(-4px);
}
.async-vehicle-option .picker-option-name {
    display: block;
    font-weight: 600;
    color: #1e293b;
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
@media (max-width: 575.98px) {
    .picker-suffix-btn {
        width: 32px;
        height: 32px;
    }
    .vehicle-info-popover {
        width: 100%;
    }
}
</style>
