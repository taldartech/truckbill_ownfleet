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
            @beforeinput="onBeforeInput"
            @input="onTextInput"
            @keydown="onKeyDown"
            @paste="onPaste"
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
import { computed, nextTick, ref, watch } from 'vue';
import {
    USER_DATE_INPUT,
    caretIndexForDigitCount,
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

const NAVIGATION_KEYS = new Set([
    'Backspace',
    'Delete',
    'Tab',
    'Escape',
    'Enter',
    'ArrowLeft',
    'ArrowRight',
    'ArrowUp',
    'ArrowDown',
    'Home',
    'End',
]);

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

function countDigitsBefore(value, caret) {
    const safeCaret = Math.max(0, Math.min(caret ?? 0, value.length));

    return value.slice(0, safeCaret).replace(/\D/g, '').length;
}

function applyFormattedValue(input, formatted, digitCaret) {
    displayValue.value = formatted;

    if (input.value !== formatted) {
        input.value = formatted;
    }

    const nextCaret = caretIndexForDigitCount(formatted, digitCaret);
    input.setSelectionRange(nextCaret, nextCaret);
}

function onBeforeInput(event) {
    if (props.readonly || props.disabled) {
        return;
    }

    const type = event.inputType ?? '';

    if (type.startsWith('delete') || type === 'historyUndo' || type === 'historyRedo') {
        return;
    }

    if (type === 'insertFromPaste' || type === 'insertFromDrop') {
        return;
    }

    const data = event.data ?? '';

    if (data && !/^\d+$/.test(data)) {
        event.preventDefault();
    }
}

function onKeyDown(event) {
    if (props.readonly || props.disabled) {
        return;
    }

    if (event.ctrlKey || event.metaKey || event.altKey) {
        return;
    }

    if (NAVIGATION_KEYS.has(event.key)) {
        return;
    }

    if (/^\d$/.test(event.key)) {
        return;
    }

    event.preventDefault();
}

function onPaste(event) {
    if (props.readonly || props.disabled) {
        return;
    }

    event.preventDefault();

    const input = event.target;
    const pasted = event.clipboardData?.getData('text') ?? '';
    const selectionStart = input.selectionStart ?? input.value.length;
    const selectionEnd = input.selectionEnd ?? selectionStart;
    const merged = `${input.value.slice(0, selectionStart)}${pasted}${input.value.slice(selectionEnd)}`;
    const formatted = formatDateInputTyping(merged);
    const digitCaret = countDigitsBefore(merged, selectionStart + pasted.length);

    isEditing.value = true;
    applyFormattedValue(input, formatted, digitCaret);
}

function onTextInput(event) {
    isEditing.value = true;

    const input = event.target;
    const raw = input.value;
    const caret = input.selectionStart ?? raw.length;
    const digitCaret = countDigitsBefore(raw, caret);
    const formatted = formatDateInputTyping(raw);

    applyFormattedValue(input, formatted, digitCaret);
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

        nextTick(() => {
            if (textInputRef.value) {
                textInputRef.value.value = displayValue.value;
            }
        });

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
