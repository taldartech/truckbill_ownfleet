<template>
    <div ref="selectRef" class="searchable-select" :class="{ 'is-invalid-select': invalid }">
        <div class="select-trigger-wrap">
            <button
                :id="inputId"
                type="button"
                class="form-select text-start select-trigger"
                :disabled="disabled"
                @click="toggleDropdown"
            >
                {{ displayLabel }}
            </button>
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
                        class="dropdown-item picker-option"
                        :class="{ 'picker-option-disabled': item.disabled }"
                        :disabled="item.disabled"
                        @click="selectItem(item)"
                    >
                        <span>{{ item.name ?? item.label }}</span>
                        <span v-if="item.hint" class="picker-option-hint"> — {{ item.hint }}</span>
                    </button>
                    <div v-if="filteredItems.length === 0" class="px-3 py-2 text-muted small">
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
    attachDropdownToBody: { type: Boolean, default: true },
});

const emit = defineEmits(['update:modelValue']);

const selectRef = ref(null);
const dropdownRef = ref(null);
const searchInputRef = ref(null);
const isOpen = ref(false);
const searchQuery = ref('');
const dropdownStyle = ref({});

const displayLabel = computed(() => {
    if (!props.modelValue) {
        return props.placeholder;
    }

    const match = props.items.find((item) => String(item.id) === String(props.modelValue));

    return match?.name ?? match?.label ?? props.placeholder;
});

const filteredItems = computed(() => {
    const query = searchQuery.value.trim().toLowerCase();

    if (!query) {
        return props.items;
    }

    return props.items.filter((item) => {
        const label = String(item.name ?? item.label ?? '').toLowerCase();
        const hint = String(item.hint ?? '').toLowerCase();

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
.select-trigger {
    width: 100%;
    padding-right: 2.4rem;
    --bs-form-select-bg-icon: none;
}
.select-trigger-wrap:has(.select-trigger-icons) .select-trigger {
    padding-right: 3.6rem;
}
.select-trigger-wrap {
    position: relative;
}
.select-trigger-icons {
    position: absolute;
    top: 0;
    right: 2rem;
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
    border: 1px solid #dee2e6;
    border-radius: 0.375rem;
}

.picker-search-wrap {
    padding: 0.55rem;
    border-bottom: 1px solid #dee2e6;
}

.picker-list-scroll {
    max-height: 220px;
    overflow-y: auto;
}

.picker-option {
    display: block;
    width: 100%;
    text-align: left;
    white-space: normal;
}

.picker-option-disabled {
    opacity: 0.65;
    cursor: not-allowed;
}

.picker-option-hint {
    color: #6c757d;
    font-size: 0.85em;
}
</style>
