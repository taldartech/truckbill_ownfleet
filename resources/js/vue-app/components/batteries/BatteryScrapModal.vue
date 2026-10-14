<template>
    <AppModal
        :show="show"
        :loading="saving"
        :form-error="formError"
        as-form
        size="lg"
        @close="close"
        @submit="submit"
    >
        <template #title>
            <i class="fas fa-recycle text-danger me-2"></i>
            Scrap Battery
        </template>

        <div v-if="battery" class="p-3 mb-3 rounded-3 bg-danger-subtle border border-danger-subtle">
            <div class="d-flex align-items-center justify-content-between mb-1">
                <span class="badge bg-danger">Mark as Scrapped</span>
                <span class="text-muted small">Status: {{ battery.status_label || battery.status }}</span>
            </div>
            <div class="fs-6 fw-bold text-dark mt-1">
                {{ battery.serial_number }}
                <span class="badge bg-secondary ms-2">{{ battery.brand }}</span>
            </div>
            <div class="text-muted small mt-1">
                Capacity: {{ Number(battery.capacity_ah) }} Ah | Purchased: {{ battery.purchase_date ? formatDate(battery.purchase_date) : '—' }}
                <span v-if="battery.purchase_cost"> | Cost: ₹{{ Number(battery.purchase_cost).toLocaleString() }}</span>
            </div>
        </div>

        <div class="alert alert-warning py-2 px-3 small d-flex align-items-center gap-2 mb-3">
            <i class="fas fa-exclamation-triangle fs-6 text-warning"></i>
            <div>
                Scrapping a battery removes it from active inventory. This action marks the battery as permanently retired.
            </div>
        </div>

        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label fw-semibold">Scrap Date <span class="text-danger">*</span></label>
                <input
                    v-model="form.scrapped_at"
                    type="date"
                    class="form-control"
                    :min="battery?.purchase_date || undefined"
                    :max="todayDate"
                    :class="{ 'is-invalid': fieldErrors.scrapped_at }"
                    required
                />
                <div v-if="fieldErrors.scrapped_at" class="invalid-feedback d-block">
                    {{ fieldErrors.scrapped_at[0] }}
                </div>
            </div>

            <div class="col-md-6">
                <label class="form-label fw-semibold">Scrap Sale Recovery (₹)</label>
                <div class="input-group">
                    <span class="input-group-text">₹</span>
                    <input
                        v-model.number="form.scrap_sale_amount"
                        type="number"
                        min="0"
                        step="0.01"
                        placeholder="0.00"
                        class="form-control"
                        :class="{ 'is-invalid': fieldErrors.scrap_sale_amount }"
                    />
                </div>
                <div v-if="fieldErrors.scrap_sale_amount" class="invalid-feedback d-block">
                    {{ fieldErrors.scrap_sale_amount[0] }}
                </div>
            </div>

            <div class="col-12">
                <label class="form-label fw-semibold">Scrap Notes / Reason</label>
                <textarea
                    v-model="form.scrap_notes"
                    rows="3"
                    class="form-control"
                    placeholder="Enter scrap buyer details, failure summary, or reasons for scrapping..."
                    :class="{ 'is-invalid': fieldErrors.scrap_notes }"
                ></textarea>
                <div v-if="fieldErrors.scrap_notes" class="invalid-feedback d-block">
                    {{ fieldErrors.scrap_notes[0] }}
                </div>
            </div>
        </div>

        <template #footer>
            <button
                type="button"
                class="btn btn-outline-secondary"
                :disabled="saving"
                @click="close"
            >
                Cancel
            </button>
            <button
                type="submit"
                class="btn btn-danger"
                :disabled="saving"
            >
                <span v-if="saving" class="spinner-border spinner-border-sm me-1" role="status"></span>
                <i v-else class="fas fa-trash-alt me-1"></i>
                Confirm Scrap
            </button>
        </template>
    </AppModal>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import AppModal from '../ui/AppModal.vue';
import { scrapBattery } from '../../api/batteries';
import { formatDate } from '../../utils/datetime';

const props = defineProps({
    show: {
        type: Boolean,
        default: false,
    },
    battery: {
        type: Object,
        default: null,
    },
});

const emit = defineEmits(['close', 'scrapped']);

const saving = ref(false);
const formError = ref('');
const fieldErrors = ref({});

function toDateInputValue(d = new Date()) {
    const pad = (n) => String(n).padStart(2, '0');
    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
}

const todayDate = computed(() => toDateInputValue());

const form = ref({
    scrapped_at: toDateInputValue(),
    scrap_sale_amount: null,
    scrap_notes: '',
});

watch(
    () => props.show,
    (open) => {
        if (!open) {
            return;
        }

        formError.value = '';
        fieldErrors.value = {};
        form.value = {
            scrapped_at: toDateInputValue(),
            scrap_sale_amount: null,
            scrap_notes: '',
        };
    },
);

function close() {
    if (!saving.value) {
        emit('close');
    }
}

async function submit() {
    if (!props.battery?.id) {
        return;
    }

    saving.value = true;
    formError.value = '';
    fieldErrors.value = {};

    try {
        const payload = {
            scrapped_at: form.value.scrapped_at,
            scrap_sale_amount: form.value.scrap_sale_amount,
            scrap_notes: form.value.scrap_notes || null,
        };
        const updated = await scrapBattery(props.battery.id, payload);
        emit('scrapped', updated);
        emit('close');
    } catch (err) {
        if (err.response?.status === 422 && err.response?.data?.errors) {
            fieldErrors.value = err.response.data.errors;
            formError.value = err.response.data.message || 'Please review highlighted fields.';
        } else {
            formError.value = err.response?.data?.message || 'Failed to scrap battery.';
        }
    } finally {
        saving.value = false;
    }
}
</script>
