<template>
    <div
        ref="selectRef"
        class="searchable-select"
        :class="{ 'is-invalid-select': invalid, 'is-open': isOpen }"
    >
        <div class="select-trigger-wrap">
            <button
                :id="inputId"
                type="button"
                class="form-select text-start select-trigger"
                :class="{ 'select-trigger--placeholder': !modelValue }"
                :disabled="disabled"
                :aria-expanded="isOpen"
                @click="toggleDropdown"
            >
                <span v-if="displayIcon" class="select-trigger-icon">
                    <i :class="displayIcon"></i>
                </span>
                <span class="select-trigger-label">{{ displayLabel }}</span>
            </button>
            <div class="select-trigger-chevron" aria-hidden="true">
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
                :class="{ 'picker-dropdown-menu--fixed': attachDropdownToBody }"
                :style="attachDropdownToBody ? dropdownStyle : undefined"
            >
                <div class="picker-search-wrap">
                    <i class="bi bi-search picker-search-icon"></i>
                    <input
                        ref="searchInputRef"
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
                        :class="{
                            'picker-option--selected': isSelected(item),
                            'picker-option-disabled': item.disabled,
                        }"
                        :disabled="item.disabled"
                        @click="selectItem(item)"
                    >
                        <span v-if="itemIcon(item)" class="picker-option-icon">
                            <i :class="itemIcon(item)"></i>
                        </span>
                        <span class="picker-option-body">
                            <span class="picker-option-name">{{ itemLabel(item) }}</span>
                            <span v-if="itemHint(item)" class="picker-option-sub">{{ itemHint(item) }}</span>
                        </span>
                        <i v-if="isSelected(item)" class="bi bi-check2 picker-option-check"></i>
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
    attachDropdownToBody: { type: Boolean, default: false },
    itemIconClass: { type: String, default: '' },
});

const emit = defineEmits(['update:modelValue']);

const selectRef = ref(null);
const dropdownRef = ref(null);
const searchInputRef = ref(null);
const isOpen = ref(false);
const searchQuery = ref('');
const dropdownStyle = ref({});

const selectedItem = computed(() =>
    props.items.find((item) => String(item.id) === String(props.modelValue)) ?? null,
);

const displayLabel = computed(() => {
    if (!selectedItem.value) {
        return props.placeholder;
    }

    return itemLabel(selectedItem.value);
});

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
    const query = searchQuery.value.trim().toLowerCase();

    if (!query) {
        return props.items;
    }

    return props.items.filter((item) => {
        const label = String(itemLabel(item)).toLowerCase();
        const hint = String(itemHint(item)).toLowerCase();

        return label.includes(query) || hint.includes(query);
    });
});

function updateDropdownPosition() {
    if (!props.attachDropdownToBody) {
        return;
    }

    const el = selectRef.value;

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

function toggleDropdown() {
    if (props.disabled) {
        return;
    }

    isOpen.value = !isOpen.value;

    if (isOpen.value) {
        searchQuery.value = '';
        nextTick(() => {
            updateDropdownPosition();
            searchInputRef.value?.focus();
        });
    }
}

function clearSelection() {
    emit('update:modelValue', '');
    isOpen.value = false;
}

function selectItem(item) {
    if (item.disabled) {
        return;
    }

    emit('update:modelValue', item.id);
    isOpen.value = false;
}

function handleDocumentClick(event) {
    const target = event.target;

    if (selectRef.value?.contains(target) || dropdownRef.value?.contains(target)) {
        return;
    }

    isOpen.value = false;
}

function handleReposition() {
    if (isOpen.value) {
        updateDropdownPosition();
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
    () => props.disabled,
    (disabled) => {
        if (disabled) {
            isOpen.value = false;
        }
    },
);
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
    --bs-form-select-bg-icon: none;
}

.select-trigger--placeholder .select-trigger-label {
    color: #6c757d;
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
.searchable-select .picker-dropdown-menu:not(.picker-dropdown-menu--fixed) {
    position: absolute;
    top: calc(100% + 4px);
    left: 0;
    width: 100%;
    z-index: 1060;
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
