<template>
    <div
        ref="pickerRef"
        class="searchable-picker searchable-picker-redesigned"
        :class="{
            'is-invalid-picker': invalid,
            'is-open': isOpen,
            'has-selection': Boolean(modelValue),
            'searchable-picker--compact-actions': compactActions,
        }"
    >
        <div v-if="hasToolbar" class="picker-toolbar">
            <slot name="toolbar" />
        </div>
        <div v-if="(canView && selectedItem) || canQuickAdd" class="picker-header-actions">
            <button
                v-if="canView && selectedItem"
                type="button"
                class="picker-header-btn"
                :title="viewTitle"
                :aria-label="viewTitle"
                :aria-expanded="infoOpen"
                @click.stop="toggleInfo"
            >
                <i class="bi bi-eye"></i>
            </button>
            <button
                v-if="canQuickAdd"
                type="button"
                class="picker-header-btn picker-header-btn--add"
                :disabled="disabled"
                :title="quickAddTitle"
                :aria-label="quickAddTitle"
                @click.stop="openQuickAdd"
            >
                <i class="bi bi-plus-lg"></i>
            </button>
        </div>
        <div ref="pickerMainRef" class="picker-main">
                <div class="picker-trigger-wrap">
                    <button
                        :id="inputId"
                        type="button"
                        class="form-select text-start picker-trigger redesigned-picker-trigger"
                        :disabled="disabled"
                        ref="triggerRef"
                        @click="toggleDropdown"
                        @keydown.down.prevent="openDropdown"
                        @keydown.esc.prevent="closeDropdown"
                    >
                        <div class="picker-trigger-content-wrap">
                            <div class="picker-trigger-main-text" :class="{ 'text-placeholder': !modelValue }">
                                {{ displayLabel }}
                            </div>
                            <div v-if="modelValue && resolvedSubtext" class="picker-trigger-sub-text">
                                {{ resolvedSubtext }}
                            </div>
                        </div>
                    </button>
                    <div v-if="modelValue && !disabled" class="picker-suffix-actions" aria-label="Input actions">
                        <button
                            type="button"
                            class="picker-suffix-btn"
                            title="Clear"
                            aria-label="Clear selection"
                            @click.stop="clearSelection"
                        >
                            <i class="bi bi-x-lg"></i>
                        </button>
                    </div>
                </div>
                <div v-if="errorMessage" class="invalid-feedback d-block">{{ errorMessage }}</div>

                <Transition name="picker-info">
                    <div
                        v-if="infoOpen && selectedItem"
                        ref="infoRef"
                        class="picker-info-popover"
                        role="dialog"
                        :aria-label="viewTitle"
                    >
                        <div class="picker-info-title">{{ viewTitle }}</div>
                        <dl class="picker-info-dl mb-0">
                            <dt>Name</dt>
                            <dd>{{ formatLabel(selectedItem.name) }}</dd>
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
                            <template v-if="selectedItem.contact_number || selectedItem.phone">
                                <dt>Mobile</dt>
                                <dd>{{ selectedItem.contact_number || selectedItem.phone }}</dd>
                            </template>
                            <template v-if="selectedItem.gst_number">
                                <dt>GST</dt>
                                <dd>{{ selectedItem.gst_number }}</dd>
                            </template>
                            <template v-if="selectedItem.pan_number">
                                <dt>PAN</dt>
                                <dd>{{ selectedItem.pan_number }}</dd>
                            </template>
                            <template v-if="selectedItem.license_number">
                                <dt>Licence</dt>
                                <dd>{{ selectedItem.license_number }}</dd>
                            </template>
                            <template v-if="selectedItem.address">
                                <dt>Address</dt>
                                <dd>{{ selectedItem.address }}</dd>
                            </template>
                            <template v-if="!hasProfileDetails">
                                <dd class="text-muted mb-0">No additional details on file.</dd>
                            </template>
                        </dl>
                    </div>
                </Transition>

                <Teleport to="body" :disabled="!attachDropdownToBody">
                    <div
                        v-if="isOpen"
                        ref="dropdownRef"
                        class="picker-dropdown-menu shadow"
                        :class="{ 'picker-dropdown-menu--fixed': attachDropdownToBody }"
                        :style="attachDropdownToBody ? dropdownStyle : undefined"
                    >
                        <div v-if="showSearchInput" class="picker-search-wrap">
                            <input
                                ref="searchInputRef"
                                v-model.trim="searchQuery"
                                type="text"
                                class="form-control form-control-sm picker-search-input"
                                :placeholder="searchPlaceholder"
                                @click.stop
                                @keydown.down.prevent="moveHighlight(1)"
                                @keydown.up.prevent="moveHighlight(-1)"
                                @keydown.enter.prevent="selectHighlighted"
                                @keydown.esc.prevent="closeDropdown"
                            />
                        </div>
                        <div class="picker-list-scroll">
                            <button
                                v-for="(item, index) in filteredItems"
                                :key="item.id"
                                type="button"
                                class="picker-option"
                                :class="{ active: index === highlightedIndex }"
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
    </div>
</template>

<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, useSlots, watch } from 'vue';
import { claimExclusiveDropdown, releaseExclusiveDropdown } from '../../composables/useExclusiveDropdown';
import { shouldShowSelectSearch } from '../../utils/selectSearchThreshold';
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
    canView: { type: Boolean, default: true },
    attachDropdownToBody: { type: Boolean, default: false },
    emptyMessage: { type: String, default: 'No station found.' },
    viewTitle: { type: String, default: 'Station details' },
    quickAddTitle: { type: String, default: 'Quick add station' },
    compactActions: { type: Boolean, default: false },
    subtext: { type: String, default: '' },
    subtextKey: { type: String, default: '' }, // e.g. 'gst_number'
    showSubtext: { type: Boolean, default: true },
    titleCaseLabel: { type: Boolean, default: false },
});

const emit = defineEmits(['update:modelValue', 'quick-add']);

const slots = useSlots();
const hasToolbar = computed(() => Boolean(slots.toolbar));

const pickerRef = ref(null);
const pickerMainRef = ref(null);
const triggerRef = ref(null);
const searchInputRef = ref(null);
const dropdownRef = ref(null);
const infoRef = ref(null);
const isOpen = ref(false);
const infoOpen = ref(false);
const searchQuery = ref('');
const highlightedIndex = ref(-1);
const dropdownStyle = ref({});

const showSearchInput = computed(() => shouldShowSelectSearch(props.items.length));

const selectedItem = computed(() => {
    if (!props.modelValue) {
        return null;
    }

    return props.items.find((item) => String(item.id) === String(props.modelValue)) ?? null;
});

const resolvedSubtext = computed(() => {
    if (!props.showSubtext) {
        return '';
    }

    if (props.subtext) {
        return props.subtext;
    }
    if (selectedItem.value) {
        if (props.subtextKey && selectedItem.value[props.subtextKey]) {
            return selectedItem.value[props.subtextKey];
        }
        // Fallback checks
        if (selectedItem.value.gst_number) {
            return selectedItem.value.gst_number;
        }
        if (selectedItem.value.license_number) {
            return selectedItem.value.license_number;
        }
        if (selectedItem.value.phone) {
            return selectedItem.value.phone;
        }
        const location = [selectedItem.value.city_name, selectedItem.value.state_name].filter(Boolean).join(', ');
        if (location) {
            return location;
        }
    }
    return '';
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
            || item.phone
            || item.gst_number
            || item.pan_number
            || item.license_number
            || item.address,
    );
});

const displayLabel = computed(() => {
    if (!props.modelValue) {
        return props.placeholder;
    }

    const match = selectedItem.value;

    return formatLabel(match?.name ?? match?.label ?? props.placeholder);
});

function formatLabel(label) {
    const value = String(label ?? '');

    if (!props.titleCaseLabel) {
        return value;
    }

    return value.toLocaleLowerCase().replace(/(^|\s)\S/g, (character) => character.toLocaleUpperCase());
}

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
        return formatLabel(item.name);
    }

    if (!item.label) {
        return '';
    }

    const [namePart] = String(item.label).split(' — ');

    return formatLabel(namePart || item.label);
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

function closeDropdown() {
    if (!isOpen.value) {
        return;
    }

    isOpen.value = false;
    releaseExclusiveDropdown(closeDropdown);
}

function openDropdown() {
    if (props.disabled || isOpen.value) {
        return;
    }

    claimExclusiveDropdown(closeDropdown);
    infoOpen.value = false;
    searchQuery.value = '';
    highlightedIndex.value = props.items.length > 0 ? 0 : -1;
    isOpen.value = true;

    nextTick(() => {
        updateDropdownPosition();
        searchInputRef.value?.focus({ preventScroll: true });
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

function toggleInfo() {
    if (!selectedItem.value || props.disabled) {
        return;
    }

    closeDropdown();
    infoOpen.value = !infoOpen.value;
}

function selectItem(item) {
    emit('update:modelValue', item.id);
    closeDropdown();
    nextTick(() => triggerRef.value?.focus({ preventScroll: true }));
}

function clearSelection() {
    emit('update:modelValue', '');
    closeDropdown();
    infoOpen.value = false;
}

function openQuickAdd() {
    if (props.disabled || !props.canQuickAdd) {
        return;
    }

    closeDropdown();
    infoOpen.value = false;
    emit('quick-add');
}

function moveHighlight(delta) {
    const count = filteredItems.value.length;

    if (count === 0) {
        highlightedIndex.value = -1;

        return;
    }

    highlightedIndex.value = (highlightedIndex.value + delta + count) % count;
}

function selectHighlighted() {
    const item = filteredItems.value[highlightedIndex.value];

    if (item) {
        selectItem(item);
    }
}

function handleDocumentClick(event) {
    if (
        pickerRef.value?.contains(event.target)
        || dropdownRef.value?.contains(event.target)
        || infoRef.value?.contains(event.target)
    ) {
        return;
    }

    closeDropdown();
    infoOpen.value = false;
}

function onWindowScrollOrResize() {
    if (isOpen.value) {
        updateDropdownPosition();
    }
}

watch(isOpen, (newVal) => {
    if (newVal) {
        window.addEventListener('scroll', onWindowScrollOrResize, { passive: true });
        window.addEventListener('resize', onWindowScrollOrResize);
    } else {
        window.removeEventListener('scroll', onWindowScrollOrResize);
        window.removeEventListener('resize', onWindowScrollOrResize);
    }
});

watch(filteredItems, (items) => {
    highlightedIndex.value = items.length > 0 ? 0 : -1;
});

onMounted(() => {
    document.addEventListener('click', handleDocumentClick);

    if (props.attachDropdownToBody) {
        updateDropdownPosition();
    }
});

onBeforeUnmount(() => {
    closeDropdown();
    document.removeEventListener('click', handleDocumentClick);
    window.removeEventListener('scroll', onWindowScrollOrResize);
    window.removeEventListener('resize', onWindowScrollOrResize);
});
</script>

<style scoped>
.searchable-picker-redesigned,
.picker-main,
.picker-trigger-wrap {
    position: relative;
    width: 100%;
}
.picker-toolbar {
    margin-bottom: 0.25rem;
}
.searchable-picker-redesigned :deep(.redesigned-picker-trigger) {
    width: 100%;
    min-height: 44px;
    padding-top: 10px;
    padding-right: 2.4rem;
    padding-bottom: 10px;
    line-height: 1.25;
    background-color: #fff;
    border: 1px solid #dee2e6;
    border-radius: 8px;
    font-size: 14px;
    box-shadow: 0 1px 2px rgba(0,0,0,0.05);
}
.searchable-picker-redesigned.has-selection :deep(.redesigned-picker-trigger) {
    padding-right: 4.5rem;
}
.searchable-picker-redesigned :deep(.redesigned-picker-trigger):focus {
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
    font-weight: 400;
    color: #94a3b8;
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
    display: flex;
    align-items: center;
    gap: 3px;
    z-index: 3;
}
.picker-header-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 24px;
    height: 24px;
    padding: 0;
    border: 0;
    border-radius: 6px;
    background: transparent;
    color: #6c757d;
    font-size: 0.78rem;
    transition: background-color 150ms ease, color 150ms ease;
}
.picker-header-btn:hover,
.picker-header-btn:focus-visible {
    background: #e7f1ff;
    color: #0d6efd;
    outline: none;
}
.picker-header-btn--add {
    color: #198754;
}
.picker-header-btn--add:hover,
.picker-header-btn--add:focus-visible {
    background: #d1e7dd;
    color: #146c43;
}
.picker-header-btn:disabled {
    color: #adb5bd;
    cursor: not-allowed;
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
.picker-suffix-btn:hover:not(:disabled),
.picker-suffix-btn:focus-visible {
    background: #e7f1ff;
    color: #0d6efd;
    outline: none;
}
.picker-suffix-btn:hover:not(:disabled),
.picker-suffix-btn:focus-visible {
    background: #f8d7da;
    color: #dc3545;
}
.picker-suffix-btn:disabled {
    color: #adb5bd;
    cursor: not-allowed;
}
.picker-info-popover {
    width: min(300px, 100%);
    margin-top: 6px;
    padding: 14px 16px;
    border: 1px solid #dee2e6;
    border-radius: 10px;
    background: #fff;
    box-shadow: 0 10px 30px rgba(33, 37, 41, 0.12);
    position: relative;
    z-index: 5;
}
.picker-info-title {
    margin-bottom: 10px;
    padding-bottom: 8px;
    border-bottom: 1px solid #eef1f4;
    color: #212529;
    font-size: 0.875rem;
    font-weight: 700;
}
.picker-info-dl {
    display: grid;
    grid-template-columns: minmax(64px, auto) 1fr;
    gap: 6px 12px;
    font-size: 0.8125rem;
}
.picker-info-dl dt {
    margin: 0;
    color: #6c757d;
    font-weight: 600;
}
.picker-info-dl dd {
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
.picker-option.active {
    background: #e7f1ff;
}
@media (max-width: 575.98px) {
    .picker-suffix-btn {
        width: 32px;
        height: 32px;
    }
    .picker-info-popover {
        width: 100%;
    }
}
</style>
