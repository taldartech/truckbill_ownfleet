<template>
    <div
        ref="pickerRef"
        class="searchable-picker"
        :class="{ 'is-invalid-picker': invalid, 'is-open': isOpen }"
    >
        <div class="picker-row">
            <div ref="pickerMainRef" class="picker-main">
                <div class="picker-trigger-wrap">
                    <button
                        :id="inputId"
                        type="button"
                        class="form-select text-start picker-trigger"
                        :disabled="disabled"
                        ref="triggerRef"
                        @click="toggleDropdown"
                    >
                        {{ displayLabel }}
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

                <Teleport to="body" :disabled="!attachDropdownToBody">
                    <div
                        v-if="isOpen"
                        ref="dropdownRef"
                        class="picker-dropdown-menu shadow"
                        :class="{ 'picker-dropdown-menu--fixed': attachDropdownToBody }"
                        :style="attachDropdownToBody ? dropdownStyle : undefined"
                    >
                        <div class="picker-search-wrap">
                            <input
                                v-model.trim="searchQuery"
                                type="text"
                                class="form-control form-control-sm picker-search-input"
                                :placeholder="searchPlaceholder"
                                @click.stop
                            />
                        </div>
                        <div class="picker-list-scroll">
                            <button
                                v-for="item in filteredItems"
                                :key="item.id"
                                type="button"
                                class="picker-option"
                                @click="selectItem(item)"
                            >
                                <span class="picker-option-name">{{ optionDisplayName(item) }}</span>
                                <span v-if="itemLocationLine(item)" class="picker-option-sub">
                                    {{ itemLocationLine(item) }}
                                </span>
                            </button>
                            <div v-if="filteredItems.length === 0" class="px-3 py-2 text-muted small">
                                {{ emptyMessage }}
                            </div>
                        </div>
                        <PickerListFooter
                            :visible-count="filteredItems.length"
                            :total-count="items.length"
                            :scroll-threshold="8"
                        />
                    </div>
                </Teleport>
            </div>
            <div class="picker-actions">
                <button
                    type="button"
                    class="btn btn-outline-secondary btn-sm picker-action-btn"
                    :disabled="!selectedItem || disabled"
                    :title="viewTitle"
                    @click.stop="toggleInfo"
                >
                    <i class="bi bi-eye"></i>
                </button>
                <button
                    v-if="canQuickAdd"
                    type="button"
                    class="btn btn-outline-success btn-sm picker-action-btn"
                    :disabled="disabled"
                    :title="quickAddTitle"
                    @click.stop="openQuickAdd"
                >
                    <i class="bi bi-plus-lg"></i>
                </button>
            </div>
        </div>

        <Teleport to="body">
            <div
                v-if="infoOpen && selectedItem"
                ref="infoRef"
                class="picker-info-popover shadow-sm"
                :style="infoStyle"
            >
                <div class="picker-info-title">{{ selectedItem.name }}</div>
                <dl class="picker-info-dl mb-0">
                    <template v-if="selectedItem.city_name">
                        <dt>City</dt>
                        <dd>{{ selectedItem.city_name }}</dd>
                    </template>
                    <template v-if="selectedItem.state_name">
                        <dt>State</dt>
                        <dd>{{ selectedItem.state_name }}</dd>
                    </template>
                    <template v-if="selectedItem.country_name">
                        <dt>Country</dt>
                        <dd>{{ selectedItem.country_name }}</dd>
                    </template>
                    <template v-if="selectedItem.contact_number">
                        <dt>Contact</dt>
                        <dd>{{ selectedItem.contact_number }}</dd>
                    </template>
                    <template v-if="selectedItem.gst_number">
                        <dt>GST</dt>
                        <dd>{{ selectedItem.gst_number }}</dd>
                    </template>
                    <template v-if="selectedItem.pan_number">
                        <dt>PAN</dt>
                        <dd>{{ selectedItem.pan_number }}</dd>
                    </template>
                    <template v-if="selectedItem.address">
                        <dt>Address</dt>
                        <dd>{{ selectedItem.address }}</dd>
                    </template>
                    <template v-if="!hasProfileDetails">
                        <dd class="text-muted mb-0">No details on file.</dd>
                    </template>
                </dl>
            </div>
        </Teleport>
    </div>
</template>

<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import PickerListFooter from './PickerListFooter.vue';

const props = defineProps({
    modelValue: { type: [String, Number], default: '' },
    items: { type: Array, default: () => [] },
    placeholder: { type: String, default: 'Select station…' },
    searchPlaceholder: { type: String, default: 'Search station…' },
    inputId: { type: String, default: '' },
    disabled: { type: Boolean, default: false },
    invalid: { type: Boolean, default: false },
    errorMessage: { type: String, default: '' },
    canQuickAdd: { type: Boolean, default: false },
    attachDropdownToBody: { type: Boolean, default: false },
    emptyMessage: { type: String, default: 'No station found.' },
    viewTitle: { type: String, default: 'Station details' },
    quickAddTitle: { type: String, default: 'Quick add station' },
});

const emit = defineEmits(['update:modelValue', 'quick-add']);

const pickerRef = ref(null);
const pickerMainRef = ref(null);
const triggerRef = ref(null);
const dropdownRef = ref(null);
const infoRef = ref(null);
const isOpen = ref(false);
const infoOpen = ref(false);
const searchQuery = ref('');
const dropdownStyle = ref({});
const infoStyle = ref({});

const selectedItem = computed(() => {
    if (!props.modelValue) {
        return null;
    }

    return props.items.find((item) => String(item.id) === String(props.modelValue)) ?? null;
});

const hasProfileDetails = computed(() => {
    const item = selectedItem.value;

    if (!item) {
        return false;
    }

    return Boolean(
        item.city_name
            || item.state_name
            || item.country_name
            || item.contact_number
            || item.gst_number
            || item.pan_number
            || item.address,
    );
});

const displayLabel = computed(() => {
    if (!props.modelValue) {
        return props.placeholder;
    }

    const match = selectedItem.value;

    return match?.name ?? match?.label ?? props.placeholder;
});

const filteredItems = computed(() => {
    const query = searchQuery.value.trim().toLowerCase();

    if (!query) {
        return props.items;
    }

    return props.items.filter((item) => {
        const haystack = [
            item.name,
            item.label,
            item.city_name,
            item.state_name,
            item.country_name,
            item.contact_number,
            item.gst_number,
            item.pan_number,
            item.address,
        ]
            .filter(Boolean)
            .join(' ')
            .toLowerCase();

        return haystack.includes(query);
    });
});

function optionDisplayName(item) {
    if (item.name) {
        return item.name;
    }

    if (!item.label) {
        return '';
    }

    const [namePart] = String(item.label).split(' — ');

    return namePart || item.label;
}

function itemLocationLine(item) {
    const parts = [item.city_name, item.state_name].filter(Boolean);

    return parts.length > 0 ? parts.join(', ') : '';
}

function updateDropdownPosition() {
    if (!props.attachDropdownToBody) {
        return;
    }

    const el = pickerMainRef.value;

    if (!el) {
        return;
    }

    const rect = el.getBoundingClientRect();

    dropdownStyle.value = {
        position: 'fixed',
        top: `${rect.bottom + 4}px`,
        left: `${rect.left}px`,
        width: `${rect.width}px`,
        zIndex: '2100',
    };
}

function updateInfoPosition() {
    const row = pickerRef.value?.querySelector('.picker-actions');

    if (!row) {
        return;
    }

    const rect = row.getBoundingClientRect();

    infoStyle.value = {
        position: 'fixed',
        top: `${rect.bottom + 6}px`,
        right: `${window.innerWidth - rect.right}px`,
        zIndex: '2101',
        minWidth: '200px',
    };
}

function toggleDropdown() {
    if (props.disabled) {
        return;
    }

    infoOpen.value = false;
    isOpen.value = !isOpen.value;

    if (isOpen.value) {
        searchQuery.value = '';
        nextTick(() => updateDropdownPosition());
    }
}

function toggleInfo() {
    if (!selectedItem.value || props.disabled) {
        return;
    }

    isOpen.value = false;
    infoOpen.value = !infoOpen.value;

    if (infoOpen.value) {
        nextTick(() => updateInfoPosition());
    }
}

function clearSelection() {
    emit('update:modelValue', '');
    isOpen.value = false;
    infoOpen.value = false;
}

function selectItem(item) {
    emit('update:modelValue', item.id);
    isOpen.value = false;
    infoOpen.value = false;
}

function openQuickAdd() {
    isOpen.value = false;
    infoOpen.value = false;
    emit('quick-add');
}

function handleDocumentClick(event) {
    const target = event.target;

    if (
        pickerRef.value?.contains(target) ||
        dropdownRef.value?.contains(target) ||
        infoRef.value?.contains(target)
    ) {
        return;
    }

    isOpen.value = false;
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

onMounted(() => {
    document.addEventListener('click', handleDocumentClick);
    window.addEventListener('resize', handleReposition);
    window.addEventListener('scroll', handleReposition, true);
});

onBeforeUnmount(() => {
    document.removeEventListener('click', handleDocumentClick);
    window.removeEventListener('resize', handleReposition);
    window.removeEventListener('scroll', handleReposition, true);
});

watch(
    () => props.modelValue,
    () => {
        if (!props.modelValue) {
            infoOpen.value = false;
        }
    },
);
</script>

<style scoped>
.searchable-picker {
    position: relative;
}
.picker-row {
    display: flex;
    align-items: flex-start;
    gap: 0.35rem;
}
.picker-main {
    flex: 1;
    min-width: 0;
    position: relative;
}
.picker-actions {
    display: flex;
    flex-shrink: 0;
    gap: 0.25rem;
    padding-top: 0.1rem;
}
.picker-action-btn {
    width: 2.1rem;
    height: 2.1rem;
    padding: 0;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}
.is-invalid-picker .picker-trigger {
    border-color: #dc3545;
}
.picker-trigger {
    width: 100%;
    min-height: 42px;
    font-size: 14px;
    padding: 8px 2.4rem 8px 12px;
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
.picker-dropdown-menu {
    background: #fff;
    border: 1px solid #dee2e6;
    border-radius: 0.375rem;
}
.picker-dropdown-menu--fixed {
    z-index: 2100;
}

.picker-dropdown-menu:not(.picker-dropdown-menu--fixed) {
    position: absolute;
    top: calc(100% + 4px);
    left: 0;
    width: 100%;
    z-index: 1060;
}

.searchable-picker.is-open .picker-main {
    position: relative;
    z-index: 1060;
}
.picker-search-wrap {
    padding: 0.55rem;
    border-bottom: 1px solid #dee2e6;
}
.picker-list-scroll {
    max-height: 280px;
    overflow-y: auto;
}
.picker-option {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    width: 100%;
    padding: 8px 12px;
    cursor: pointer;
    border: 0;
    border-radius: 6px;
    gap: 2px;
    text-align: left;
    background: transparent;
    white-space: normal;
}
.picker-option:hover,
.picker-option:focus {
    background: var(--color-background-secondary, #f8f9fa);
    outline: none;
}
.picker-option-name {
    font-size: 14px;
    font-weight: 500;
    color: var(--color-text-primary, #212529);
    line-height: 1.3;
}
.picker-option-sub {
    font-size: 12px;
    color: var(--color-text-secondary, #6c757d);
    line-height: 1.3;
    margin-top: 1px;
}
.picker-info-popover {
    background: #fff;
    border: 1px solid #dee2e6;
    border-radius: 0.375rem;
    padding: 0.65rem 0.85rem;
}
.picker-info-title {
    font-weight: 600;
    margin-bottom: 0.35rem;
}
.picker-info-dl {
    display: grid;
    grid-template-columns: auto 1fr;
    gap: 0.15rem 0.65rem;
    font-size: 0.875rem;
}
.picker-info-dl dt {
    color: #6c757d;
    font-weight: 500;
    margin: 0;
}
.picker-info-dl dd {
    margin: 0;
}
</style>
