<template>
    <div ref="pickerRef" class="searchable-picker" :class="{ 'is-invalid-picker': invalid }">
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
                    <button
                        v-if="modelValue && !disabled"
                        type="button"
                        class="btn btn-link picker-clear-btn"
                        title="Clear"
                        @click="clearSelection"
                    >
                        <i class="bi bi-x-circle-fill"></i>
                    </button>
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
                                class="dropdown-item picker-option"
                                @click="selectItem(item)"
                            >
                                <span class="picker-option-name">{{ item.name ?? item.label }}</span>
                                <span v-if="itemLocationLine(item)" class="picker-option-meta">
                                    {{ itemLocationLine(item) }}
                                </span>
                            </button>
                            <div v-if="filteredItems.length === 0" class="px-3 py-2 text-muted small">
                                No station found.
                            </div>
                        </div>
                    </div>
                </Teleport>
            </div>
            <div class="picker-actions">
                <button
                    type="button"
                    class="btn btn-outline-secondary btn-sm picker-action-btn"
                    :disabled="!selectedItem || disabled"
                    title="Station details"
                    @click.stop="toggleInfo"
                >
                    <i class="bi bi-eye"></i>
                </button>
                <button
                    v-if="canQuickAdd"
                    type="button"
                    class="btn btn-outline-success btn-sm picker-action-btn"
                    :disabled="disabled"
                    title="Quick add station"
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
                    <template v-if="!hasLocationDetails">
                        <dd class="text-muted mb-0">No location details on file.</dd>
                    </template>
                </dl>
            </div>
        </Teleport>
    </div>
</template>

<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';

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

const hasLocationDetails = computed(() => {
    const item = selectedItem.value;

    if (!item) {
        return false;
    }

    return Boolean(item.city_name || item.state_name || item.country_name);
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
        ]
            .filter(Boolean)
            .join(' ')
            .toLowerCase();

        return haystack.includes(query);
    });
});

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
    padding-right: 2.4rem;
    --bs-form-select-bg-icon: none;
}
.picker-trigger-wrap {
    position: relative;
}
.picker-clear-btn {
    position: absolute;
    right: 0.4rem;
    top: 50%;
    transform: translateY(-50%);
    color: #6c757d;
    text-decoration: none;
    padding: 0;
}
</style>

<style>
.picker-dropdown-menu {
    background: #fff;
    border: 1px solid #dee2e6;
    border-radius: 0.375rem;
}
.picker-dropdown-menu:not(.picker-dropdown-menu--fixed) {
    position: absolute;
    top: calc(100% + 4px);
    left: 0;
    width: 100%;
    z-index: 1060;
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
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    white-space: normal;
}
.picker-option-name {
    font-weight: 500;
}
.picker-option-meta {
    font-size: 0.75rem;
    color: #6c757d;
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
