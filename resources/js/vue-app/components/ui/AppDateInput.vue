<template>
    <div class="app-date-input" :class="{ 'app-date-input--sm': size === 'sm' }">
        <input
            ref="textInputRef"
            :id="inputId"
            type="text"
            class="form-control app-date-input__text"
            :class="{
                'form-control-sm': size === 'sm',
                'is-invalid': invalid,
                'app-date-input__text--readonly': readonly,
            }"
            :value="displayValue"
            :placeholder="USER_DATE_INPUT.placeholder"
            :readonly="readonly"
            :disabled="disabled"
            :required="required"
            inputmode="numeric"
            autocomplete="off"
            :aria-invalid="invalid || undefined"
            @input="onTextInput"
            @blur="onBlur"
            @keydown.enter.prevent="onBlur"
        />
        <button
            v-if="showPickerButton"
            type="button"
            class="app-date-input__picker-btn"
            tabindex="-1"
            :disabled="disabled"
            aria-label="Open calendar"
            @click="openNativePicker"
        >
            <i class="bi bi-calendar3" aria-hidden="true"></i>
        </button>
        <input
            ref="nativeInputRef"
            type="date"
            class="app-date-input__native"
            :value="modelValue"
            :min="min"
            :max="max"
            tabindex="-1"
            aria-hidden="true"
            @input="onNativeInput"
            @change="onNativeChange"
        />
    </div>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import {
    USER_DATE_INPUT,
    formatDateInputTyping,
    fromDateInputDisplay,
    toDateInputDisplay,
} from '../../utils/datetime';

const props = defineProps({
    modelValue: {
        type: String,
        default: '',
    },
    inputId: {
        type: String,
        default: undefined,
    },
    min: {
        type: String,
        default: undefined,
    },
    max: {
        type: String,
        default: undefined,
    },
    disabled: {
        type: Boolean,
        default: false,
    },
    readonly: {
        type: Boolean,
        default: false,
    },
    required: {
        type: Boolean,
        default: false,
    },
    invalid: {
        type: Boolean,
        default: false,
    },
    size: {
        type: String,
        default: undefined,
        validator: (value) => !value || value === 'sm',
    },
});

const emit = defineEmits(['update:modelValue', 'input', 'change']);

const textInputRef = ref(null);
const nativeInputRef = ref(null);
const displayValue = ref(toDateInputDisplay(props.modelValue));
const isEditing = ref(false);

const showPickerButton = computed(() => !props.readonly && !props.disabled);

watch(
    () => props.modelValue,
    (value) => {
        if (isEditing.value) {
            return;
        }

        displayValue.value = toDateInputDisplay(value);
    },
);

function clampIso(iso) {
    if (!iso) {
        return '';
    }

    if (props.min && iso < props.min) {
        return props.min;
    }

    if (props.max && iso > props.max) {
        return props.max;
    }

    return iso;
}

function emitValue(iso) {
    const next = clampIso(iso);

    if (next !== props.modelValue) {
        emit('update:modelValue', next);
        emit('input', next);
        emit('change', next);
    }

    displayValue.value = toDateInputDisplay(next);
}

function onTextInput(event) {
    isEditing.value = true;
    displayValue.value = formatDateInputTyping(event.target.value);
}

function onBlur() {
    isEditing.value = false;

    const iso = fromDateInputDisplay(displayValue.value);

    if (!displayValue.value.trim()) {
        emitValue('');

        return;
    }

    if (!iso) {
        displayValue.value = toDateInputDisplay(props.modelValue);

        return;
    }

    emitValue(iso);
}

function onNativeInput(event) {
    isEditing.value = false;
    emitValue(event.target.value);
}

function onNativeChange(event) {
    onNativeInput(event);
}

function syncNativeValue() {
    const nativeInput = nativeInputRef.value;

    if (!nativeInput?.value) {
        return;
    }

    isEditing.value = false;
    emitValue(nativeInput.value);
}

async function openNativePicker() {
    const nativeInput = nativeInputRef.value;

    if (!nativeInput) {
        return;
    }

    if (typeof nativeInput.showPicker === 'function') {
        try {
            await nativeInput.showPicker();
            syncNativeValue();
        } catch {
            // User dismissed the picker.
        }

        return;
    }

    nativeInput.click();
}
</script>
