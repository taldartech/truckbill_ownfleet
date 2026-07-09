<template>
    <div
        ref="selectRef"
        class="searchable-select"
        :class="{ 'is-invalid-select': invalid, 'is-open': isOpen }"
    >
        <div class="select-trigger-wrap">
            <input
                v-if="editable"
                :id="inputId"
                ref="editableInputRef"
                type="text"
                class="form-control select-trigger select-trigger--editable"
                :inputmode="inputMode"
                :value="String(modelValue ?? '')"
                :placeholder="placeholder"
                :disabled="disabled"
                :maxlength="maxLength || undefined"
                :aria-expanded="isOpen"
                autocomplete="off"
                spellcheck="false"
                @input="onEditableInput"
                @focus="onEditableFocus"
                @keydown.down.prevent="openDropdown"
                @keydown.escape="closeDropdown"
                @blur="onEditableBlur"
            />
            <button
                v-else
                :id="inputId"
                type="button"
                class="form-select text-start select-trigger"
                :class="{ 'select-trigger--placeholder': !modelValue }"
                :disabled="disabled"
                :aria-expanded="isOpen"
                @click="toggleDropdown"
            >
                <span v-if="displayIcon && !plain" class="select-trigger-icon">
                    <i :class="displayIcon"></i>
                </span>
                <span
                    class="select-trigger-label"
                    :class="{ 'select-trigger-label--inactive': selectedItemInactive }"
                >
                    {{ displayLabel }}
                </span>
            </button>
            <button
                v-if="editable && !disabled"
                type="button"
                class="select-trigger-chevron select-trigger-chevron--button"
                :aria-expanded="isOpen"
                tabindex="-1"
                @mousedown.prevent
                @click="toggleDropdown"
            >
                <i class="bi" :class="isOpen ? 'bi-chevron-up' : 'bi-chevron-down'"></i>
            </button>
            <div v-else class="select-trigger-chevron" aria-hidden="true">
                <i class="bi" :class="isOpen ? 'bi-chevron-up' : 'bi-chevron-down'"></i>
            </div>
            <div v-if="modelValue && !disabled" class="select-trigger-icons">
                <button
                    type="button"
                    class="btn btn-link select-clear-btn"
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
                :class="{
                    'picker-dropdown-menu--fixed': attachDropdownToBody,
                    'picker-dropdown-menu--above': opensAbove,
                }"
                :style="attachDropdownToBody ? dropdownStyle : undefined"
            >
                <div
                    v-if="showDropdownSearchInput"
                    class="picker-search-wrap"
                    :class="{ 'picker-search-wrap--plain': plain }"
                >
                    <i v-if="!plain" class="bi bi-search picker-search-icon"></i>
                    <input
                        ref="searchInputRef"
                        v-model="searchQuery"
                        type="text"
                        class="form-control form-control-sm picker-search-input"
                        :class="{ 'picker-search-input--plain': plain }"
                        :placeholder="searchPlaceholder"
                        @click.stop
                    />
                </div>
                <div class="picker-list-scroll" :style="listScrollStyle">
                    <button
                        v-for="item in filteredItems"
                        :key="item.id"
                        type="button"
                        class="picker-option"
                        :class="{
                            'picker-option--selected': isSelected(item),
                            'picker-option-disabled': item.disabled,
                        }"
                        :disabled="item.disabled"
                        @mousedown.prevent
                        @click="selectItem(item)"
                    >
                        <span v-if="itemIcon(item) && !plain" class="picker-option-icon">
                            <i :class="itemIcon(item)"></i>
                        </span>
                        <span class="picker-option-body">
                            <span
                                class="picker-option-name"
                                :class="{ 'picker-option-name--inactive': item.inactive }"
                            >
                                {{ itemLabel(item) }}
                                <span v-if="item.badge" class="picker-option-badge">{{ item.badge }}</span>
                            </span>
                            <span v-if="itemHint(item) && !plain" class="picker-option-sub">{{ itemHint(item) }}</span>
                        </span>
                        <i v-if="isSelected(item) && !plain" class="bi bi-check2 picker-option-check"></i>
                    </button>
                    <div v-if="filteredItems.length === 0" class="picker-empty">
                        {{ emptyMessage }}
                    </div>
                </div>
                <PickerListFooter
                    :visible-count="filteredItems.length"
                    :total-count="items.length"
                />
            </div>
        </Teleport>
    </div>
</template>

<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { claimExclusiveDropdown, releaseExclusiveDropdown } from '../../composables/useExclusiveDropdown';
import {
    computeFixedDropdownPlacement,
    ESTIMATED_DROPDOWN_HEIGHT,
} from '../../utils/dropdownPlacement';
import { shouldShowSelectSearch } from '../../utils/selectSearchThreshold';
import PickerListFooter from './PickerListFooter.vue';

const props = defineProps({
    modelValue: { type: [String, Number], default: '' },
    items: { type: Array, default: () => [] },
    placeholder: { type: String, default: 'Select…' },
    searchPlaceholder: { type: String, default: 'Search…' },
    emptyMessage: { type: String, default: 'No results found.' },
    inputId: { type: String, default: '' },
    disabled: { type: Boolean, default: false },
    invalid: { type: Boolean, default: false },
    errorMessage: { type: String, default: '' },
    attachDropdownToBody: { type: Boolean, default: true },
    itemIconClass: { type: String, default: '' },
    plain: { type: Boolean, default: true },
    editable: { type: Boolean, default: false },
    inputMode: { type: String, default: 'text' },
    maxLength: { type: Number, default: null },
});

const emit = defineEmits(['update:modelValue', 'blur']);

const selectRef = ref(null);
const dropdownRef = ref(null);
const searchInputRef = ref(null);
const editableInputRef = ref(null);
const isOpen = ref(false);
const searchQuery = ref('');
const dropdownStyle = ref({});
const opensAbove = ref(false);
const listMaxHeight = ref(null);
const suspendedModalFocus = ref(null);
let modalStackObserver = null;
let ownerFormEl = null;

function resolveDropdownZIndex() {
    const ownerModal = selectRef.value?.closest('[data-app-modal]');

    if (!ownerModal) {
        return 1060;
    }

    const ownerZ = Number.parseInt(window.getComputedStyle(ownerModal).zIndex, 10) || 1055;

    return ownerZ + 5;
}

const showSearchInput = computed(() => shouldShowSelectSearch(props.items.length));

const showDropdownSearchInput = computed(() => showSearchInput.value && !props.editable);

const listScrollStyle = computed(() => {
    if (!listMaxHeight.value) {
        return undefined;
    }

    return {
        maxHeight: `${listMaxHeight.value}px`,
    };
});

const selectedItem = computed(() =>
    props.items.find((item) => String(item.id) === String(props.modelValue)) ?? null,
);

const displayLabel = computed(() => {
    if (!selectedItem.value) {
        return props.placeholder;
    }

    const label = itemLabel(selectedItem.value);
    const badge = selectedItem.value.badge;

    return badge ? `${label} ${badge}` : label;
});

const selectedItemInactive = computed(() => Boolean(selectedItem.value?.inactive));

const displayIcon = computed(() => {
    if (!selectedItem.value) {
        return props.itemIconClass || null;
    }

    return itemIcon(selectedItem.value);
});

function itemLabel(item) {
    return item.name ?? item.label ?? '—';
}

function itemHint(item) {
    return item.hint ?? item.subtitle ?? '';
}

function itemIcon(item) {
    return item.icon ?? props.itemIconClass ?? '';
}

function isSelected(item) {
    return String(item.id) === String(props.modelValue);
}

const filteredItems = computed(() => {
    const query = props.editable
        ? String(props.modelValue ?? '').trim().toLowerCase()
        : searchQuery.value.trim().toLowerCase();

    if (!query) {
        return props.items;
    }

    return props.items.filter((item) => {
        const label = String(itemLabel(item)).toLowerCase();
        const hint = String(itemHint(item)).toLowerCase();
        const extra = String(item.searchText ?? item.code ?? '').toLowerCase();

        return label.includes(query) || hint.includes(query) || extra.includes(query);
    });
});

function measureDropdownChromeHeight() {
    const dropdownEl = dropdownRef.value;

    if (!dropdownEl) {
        return showDropdownSearchInput.value ? 52 : 8;
    }

    const listEl = dropdownEl.querySelector('.picker-list-scroll');

    return dropdownEl.offsetHeight - (listEl?.offsetHeight ?? 0);
}

function updateDropdownPosition() {
    const el = selectRef.value;

    if (!el) {
        return;
    }

    const rect = el.getBoundingClientRect();
    const dropdownHeight = dropdownRef.value?.offsetHeight ?? ESTIMATED_DROPDOWN_HEIGHT;
    const placement = computeFixedDropdownPlacement(rect, dropdownHeight, {
        chromeHeight: measureDropdownChromeHeight(),
        zIndex: String(resolveDropdownZIndex()),
    });

    opensAbove.value = placement.opensAbove;
    listMaxHeight.value = placement.listMaxHeight;

    if (props.attachDropdownToBody) {
        dropdownStyle.value = placement.style;
    }
}

function scheduleDropdownPositionUpdate() {
    updateDropdownPosition();

    nextTick(() => {
        updateDropdownPosition();
    });
}

function suspendModalFocusTrap() {
    if (!props.attachDropdownToBody) {
        return;
    }

    const modalEl = selectRef.value?.closest('.modal');

    if (!modalEl || typeof window.bootstrap === 'undefined') {
        return;
    }

    const instance = window.bootstrap.Modal.getInstance(modalEl);

    if (!instance || instance._config.focus === false) {
        return;
    }

    suspendedModalFocus.value = instance;
    instance._config.focus = false;
}

function resumeModalFocusTrap() {
    if (!suspendedModalFocus.value) {
        return;
    }

    suspendedModalFocus.value._config.focus = true;
    suspendedModalFocus.value = null;
}

function closeDropdown() {
    if (!isOpen.value) {
        return;
    }

    isOpen.value = false;
    opensAbove.value = false;
    listMaxHeight.value = null;
    resumeModalFocusTrap();
    releaseExclusiveDropdown(closeDropdown);
}

function openDropdown() {
    if (props.disabled) {
        return;
    }

    claimExclusiveDropdown(closeDropdown);
    searchQuery.value = '';
    isOpen.value = true;

    nextTick(() => {
        suspendModalFocusTrap();
        scheduleDropdownPositionUpdate();

        if (showDropdownSearchInput.value) {
            searchInputRef.value?.focus({ preventScroll: true });
        }
    });
}

function onEditableFocus() {
    openDropdown();
}

function onEditableBlur() {
    emit('blur', props.modelValue);
}

function onEditableInput(event) {
    let value = event.target.value;

    if (props.inputMode === 'numeric') {
        value = value.replace(/\D/g, '');
    }

    if (props.maxLength) {
        value = value.slice(0, props.maxLength);
    }

    if (event.target.value !== value) {
        event.target.value = value;
    }

    emit('update:modelValue', value);

    if (!isOpen.value) {
        openDropdown();
    } else {
        nextTick(() => updateDropdownPosition());
    }
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
    emit('update:modelValue', '');
    closeDropdown();
}

function selectItem(item) {
    if (item.disabled) {
        return;
    }

    emit('update:modelValue', item.id);
    closeDropdown();

    if (props.editable) {
        nextTick(() => {
            editableInputRef.value?.focus({ preventScroll: true });
        });
    }
}

function handleDocumentClick(event) {
    const target = event.target;

    if (selectRef.value?.contains(target) || dropdownRef.value?.contains(target)) {
        return;
    }

    closeDropdown();
}

function handleFormSubmit() {
    closeDropdown();
}

function startModalStackObserver() {
    if (modalStackObserver || typeof document === 'undefined') {
        return;
    }

    const ownerModal = selectRef.value?.closest('[data-app-modal]');

    if (!ownerModal) {
        return;
    }

    const initialModalCount = document.querySelectorAll('[data-app-modal]').length;

    modalStackObserver = new MutationObserver(() => {
        if (!isOpen.value) {
            return;
        }

        const modalCount = document.querySelectorAll('[data-app-modal]').length;

        if (modalCount > initialModalCount) {
            closeDropdown();
        }
    });

    modalStackObserver.observe(document.body, { childList: true });
}

function stopModalStackObserver() {
    modalStackObserver?.disconnect();
    modalStackObserver = null;
}

function handleReposition() {
    if (isOpen.value) {
        scheduleDropdownPositionUpdate();
    }
}

function bindOwnerForm() {
    ownerFormEl = selectRef.value?.closest('form') ?? null;

    if (ownerFormEl) {
        ownerFormEl.addEventListener('submit', handleFormSubmit);
    }
}

function unbindOwnerForm() {
    if (ownerFormEl) {
        ownerFormEl.removeEventListener('submit', handleFormSubmit);
        ownerFormEl = null;
    }
}

onMounted(() => {
    document.addEventListener('click', handleDocumentClick);
    window.addEventListener('resize', handleReposition);
    window.addEventListener('scroll', handleReposition, true);
    bindOwnerForm();
    startModalStackObserver();
});

onBeforeUnmount(() => {
    document.removeEventListener('click', handleDocumentClick);
    window.removeEventListener('resize', handleReposition);
    window.removeEventListener('scroll', handleReposition, true);
    unbindOwnerForm();
    stopModalStackObserver();
    releaseExclusiveDropdown(closeDropdown);

    if (isOpen.value) {
        closeDropdown();
    } else {
        resumeModalFocusTrap();
    }
});

watch(
    () => props.disabled,
    (disabled) => {
        if (disabled) {
            closeDropdown();
        }
    },
);

watch([searchQuery, filteredItems], () => {
    if (isOpen.value) {
        nextTick(() => updateDropdownPosition());
    }
});
</script>

<style scoped>
.searchable-select {
    position: relative;
}

.is-invalid-select .select-trigger {
    border-color: #dc3545;
}

.searchable-select.is-open .select-trigger {
    border-color: #86b7fe;
    box-shadow: 0 0 0 0.2rem rgba(13, 110, 253, 0.15);
}

.select-trigger {
    width: 100%;
    min-height: 38px;
    padding: 0.45rem 3.2rem 0.45rem 0.65rem;
    display: flex;
    align-items: center;
    gap: 0.45rem;
    --bs-form-select-bg-img: none;
    --bs-form-select-bg-icon: none;
    background-image: none;
}

.select-trigger--placeholder .select-trigger-label {
    color: #6c757d;
}

.select-trigger--editable {
    cursor: text;
}

.select-trigger-chevron--button {
    pointer-events: auto;
    border: none;
    background: transparent;
    padding: 0;
    width: auto;
    cursor: pointer;
}

.select-trigger-chevron--button:hover,
.select-trigger-chevron--button:focus {
    color: #495057;
    outline: none;
}

.select-trigger-wrap:has(.select-trigger--editable) .select-trigger-chevron--button {
    right: 0.65rem;
}

.select-trigger-wrap:has(.select-trigger--editable):has(.select-trigger-icons) .select-trigger--editable {
    padding-right: 4.4rem;
}

.select-trigger-wrap:has(.select-trigger--editable):has(.select-trigger-icons) .select-trigger-chevron--button {
    right: 2.15rem;
}

.select-trigger-icon {
    color: #198754;
    line-height: 1;
    flex-shrink: 0;
}

.select-trigger-label {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.select-trigger-wrap {
    position: relative;
}

.select-trigger-chevron {
    position: absolute;
    top: 0;
    right: 0.65rem;
    bottom: 0;
    display: flex;
    align-items: center;
    pointer-events: none;
    color: #6c757d;
    font-size: 0.8rem;
}

.select-trigger-wrap:has(.select-trigger-icons) .select-trigger {
    padding-right: 4.4rem;
}

.select-trigger-wrap:has(.select-trigger-icons) .select-trigger-chevron {
    right: 2.15rem;
}

.select-trigger-icons {
    position: absolute;
    top: 0;
    right: 2.15rem;
    bottom: 0;
    display: flex;
    align-items: center;
    pointer-events: none;
}

.select-clear-btn {
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

.select-clear-btn:hover,
.select-clear-btn:focus {
    color: #dc3545;
    text-decoration: none;
}
</style>

<style>
.searchable-select .picker-dropdown-menu--fixed {
    z-index: inherit;
}

.searchable-select .picker-dropdown-menu:not(.picker-dropdown-menu--fixed) {
    position: absolute;
    top: calc(100% + 4px);
    left: 0;
    width: 100%;
    z-index: 1060;
}

.searchable-select .picker-dropdown-menu--above:not(.picker-dropdown-menu--fixed) {
    top: auto;
    bottom: calc(100% + 4px);
}

.picker-dropdown-menu {
    background: #fff;
    border: 1px solid #ced4da;
    border-radius: 0.5rem;
    overflow: hidden;
}

.picker-search-wrap {
    position: relative;
    padding: 0.55rem;
    border-bottom: 1px solid #e9ecef;
    background: #f8f9fa;
}

.picker-search-icon {
    position: absolute;
    left: 1rem;
    top: 50%;
    transform: translateY(-50%);
    color: #868e96;
    font-size: 0.8rem;
    pointer-events: none;
}

.picker-search-input {
    padding-left: 2rem;
    border-color: #dee2e6;
    background: #fff;
}

.picker-search-input:focus {
    border-color: #86b7fe;
    box-shadow: 0 0 0 0.15rem rgba(13, 110, 253, 0.12);
}

.picker-search-input--plain {
    padding-left: 0.65rem;
}

.picker-list-scroll {
    max-height: 240px;
    overflow-y: auto;
    padding: 0.35rem;
}

.picker-option {
    display: flex;
    align-items: center;
    gap: 0.55rem;
    width: 100%;
    padding: 0.5rem 0.55rem;
    margin-bottom: 0.15rem;
    cursor: pointer;
    border: 1px solid transparent;
    border-radius: 0.45rem;
    text-align: left;
    background: transparent;
    white-space: normal;
}

.picker-option:last-child {
    margin-bottom: 0;
}

.picker-option:hover,
.picker-option:focus {
    background: #f1f3f5;
    outline: none;
}

.picker-option--selected {
    background: #e8f5ee;
    border-color: #b8dfc9;
}

.picker-option--selected:hover,
.picker-option--selected:focus {
    background: #dff0e7;
}

.picker-option-disabled {
    opacity: 0.55;
    cursor: not-allowed;
}

.picker-option-icon {
    width: 1.75rem;
    height: 1.75rem;
    border-radius: 0.4rem;
    background: #e9ecef;
    color: #495057;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    font-size: 0.9rem;
}

.picker-option--selected .picker-option-icon {
    background: #198754;
    color: #fff;
}

.picker-option-body {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    gap: 0.1rem;
    min-width: 0;
    flex: 1;
}

.picker-option-name {
    font-size: 0.875rem;
    font-weight: 600;
    color: #212529;
    line-height: 1.2;
}

.picker-option-name--inactive {
    color: #dc3545;
}

.picker-option-badge {
    display: inline-block;
    margin-left: 0.35rem;
    font-size: 0.7rem;
    font-weight: 600;
    color: #dc3545;
}

.select-trigger-label--inactive {
    color: #dc3545;
}

.picker-option-sub {
    font-size: 0.75rem;
    color: #6c757d;
    line-height: 1.2;
}

.picker-option-check {
    color: #198754;
    font-size: 1rem;
    flex-shrink: 0;
}

.picker-empty {
    padding: 0.85rem 0.65rem;
    text-align: center;
    color: #6c757d;
    font-size: 0.85rem;
}
</style>
