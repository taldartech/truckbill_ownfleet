<template>
    <AppModal
        :show="show"
        :loading="saving"
        :form-error="formError"
        as-form
        size="lg"
        @close="$emit('close')"
        @submit="openSaveConfirm"
    >
        <template #title>Edit Additional Charges</template>

        <p class="text-muted small mb-3">
            Route freight from the linked Bilty is read-only. You can add, edit, or remove additional charges on this pending invoice.
        </p>

        <div class="invoice-charges-edit__summary card bg-light border-0 mb-3">
            <div class="card-body py-2 px-3 small">
                <div class="d-flex justify-content-between">
                    <span class="text-muted">Route freight (from Bilty)</span>
                    <strong>₹{{ formatMoney(routeFreightAmount) }}</strong>
                </div>
            </div>
        </div>

        <div class="invoice-charges-edit__custom">
            <div class="invoice-charges-edit__custom-header">
                <span class="invoice-charges-edit__custom-title">Additional charges</span>
                <button type="button" class="btn btn-link btn-sm p-0" @click="addItem">
                    <i class="bi bi-plus-lg" aria-hidden="true"></i>
                    Add
                </button>
            </div>

            <div v-if="items.length === 0" class="text-muted small py-2">
                No additional charges. Click Add to include loading, detention, or other charges.
            </div>

            <div
                v-for="(item, index) in items"
                :key="`custom-${index}`"
                class="invoice-charges-edit__row"
            >
                <input
                    v-model="item.description"
                    type="text"
                    class="form-control form-control-sm"
                    :class="{ 'is-invalid': Boolean(fieldErrors[`custom_items.${index}.description`]) }"
                    placeholder="Description"
                />
                <input
                    v-model="item.amount"
                    type="number"
                    min="0.01"
                    step="0.01"
                    class="form-control form-control-sm invoice-charges-edit__amount"
                    :class="{ 'is-invalid': Boolean(fieldErrors[`custom_items.${index}.amount`]) }"
                    placeholder="Amount"
                />
                <button
                    type="button"
                    class="btn btn-outline-secondary btn-sm"
                    title="Remove"
                    @click="requestRemoveItem(index)"
                >
                    <i class="bi bi-x-lg" aria-hidden="true"></i>
                </button>
            </div>
        </div>

        <div class="invoice-charges-edit__totals mt-3 pt-3 border-top small">
            <div class="d-flex justify-content-between mb-1">
                <span class="text-muted">Taxable amount</span>
                <span>₹{{ formatMoney(taxableAmount) }}</span>
            </div>
            <div v-if="displaysGst" class="d-flex justify-content-between mb-1">
                <span class="text-muted">GST ({{ gstRate }}%)</span>
                <span>₹{{ formatMoney(previewGstAmount) }}</span>
            </div>
            <div class="d-flex justify-content-between">
                <strong>Invoice total</strong>
                <strong>₹{{ formatMoney(previewTotalAmount) }}</strong>
            </div>
        </div>

        <template #footer>
            <button type="button" class="btn btn-secondary" @click="$emit('close')">Cancel</button>
            <AppButton type="submit" label="Save Changes" :loading="saving" />
        </template>
    </AppModal>

    <AppConfirmModal
        :show="showRemoveConfirm"
        title="Remove Charge"
        :message="removeConfirmMessage"
        confirm-label="Yes, Remove"
        confirm-variant="danger"
        confirm-icon="bi bi-trash"
        icon="bi bi-trash"
        icon-tone="danger"
        @cancel="closeRemoveConfirm"
        @confirm="confirmRemoveItem"
    />

    <AppConfirmModal
        :show="showSaveConfirm"
        title="Save Invoice Charges"
        message="This will update additional charges and recalculate GST and invoice total."
        confirm-label="Yes, Save Changes"
        confirm-variant="primary"
        confirm-icon="bi bi-check-lg"
        icon="bi bi-pencil-square"
        icon-tone="primary"
        :loading="saving"
        @cancel="closeSaveConfirm"
        @confirm="confirmSave"
    >
        <div v-if="invoice" class="text-start">
            <div class="app-confirm-card">
                <div class="app-confirm-card__section-title">Invoice</div>
                <div class="app-confirm-card__body small">
                    <div class="d-flex flex-wrap gap-x-3 gap-y-1 mb-2">
                        <span><strong>{{ invoice.invoice_number || '—' }}</strong></span>
                        <span v-if="invoice.customer?.name" class="text-muted">{{ invoice.customer.name }}</span>
                    </div>
                    <div class="d-flex flex-wrap justify-content-between gap-2">
                        <span>
                            <span class="text-muted">Current total</span>
                            <strong class="ms-1">₹{{ formatMoney(currentInvoiceTotal) }}</strong>
                        </span>
                        <span>
                            <span class="text-muted">New total</span>
                            <strong class="ms-1">₹{{ formatMoney(previewTotalAmount) }}</strong>
                        </span>
                    </div>
                    <hr class="my-2" />
                    <div class="d-flex flex-wrap justify-content-between gap-2">
                        <span>
                            <span class="text-muted">Additional charges</span>
                            <span class="ms-1">₹{{ formatMoney(currentCustomAmount) }} → ₹{{ formatMoney(customTotal) }}</span>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </AppConfirmModal>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import AppButton from '../ui/AppButton.vue';
import AppConfirmModal from '../ui/AppConfirmModal.vue';
import AppModal from '../ui/AppModal.vue';

const props = defineProps({
    show: { type: Boolean, default: false },
    invoice: { type: Object, default: null },
    saving: { type: Boolean, default: false },
    formError: { type: String, default: '' },
    fieldErrors: { type: Object, default: () => ({}) },
});

const emit = defineEmits(['close', 'submit']);

const items = ref([]);
const showSaveConfirm = ref(false);
const showRemoveConfirm = ref(false);
const pendingRemoveIndex = ref(null);

const routeFreightAmount = computed(() => Number(props.invoice?.route_freight_amount ?? 0));
const displaysGst = computed(() => props.invoice?.displays_gst !== false);
const collectsGst = computed(() => props.invoice?.collects_gst !== false);
const gstRate = computed(() => Number(props.invoice?.gst_rate ?? 0));
const currentInvoiceTotal = computed(() => Number(props.invoice?.total_amount ?? 0));
const currentCustomAmount = computed(() => Number(props.invoice?.custom_amount ?? 0));

const customTotal = computed(() =>
    items.value.reduce((sum, item) => {
        const amount = Number(item.amount);

        return sum + (Number.isFinite(amount) ? amount : 0);
    }, 0),
);

const taxableAmount = computed(() => routeFreightAmount.value + customTotal.value);

const previewGstAmount = computed(() => {
    if (!displaysGst.value || gstRate.value <= 0) {
        return 0;
    }

    return roundMoney(taxableAmount.value * (gstRate.value / 100));
});

const previewTotalAmount = computed(() => {
    if (!collectsGst.value) {
        return taxableAmount.value;
    }

    return taxableAmount.value + previewGstAmount.value;
});

const removeConfirmMessage = computed(() => {
    const index = pendingRemoveIndex.value;

    if (index === null || !items.value[index]) {
        return 'Remove this additional charge?';
    }

    const item = items.value[index];
    const description = String(item.description ?? '').trim() || 'this charge';
    const amount = Number(item.amount);

    if (Number.isFinite(amount) && amount > 0) {
        return `Remove "${description}" (₹${formatMoney(amount)}) from this invoice?`;
    }

    return `Remove "${description}" from this invoice?`;
});

watch(
    () => [props.show, props.invoice],
    () => {
        if (!props.show || !props.invoice) {
            showSaveConfirm.value = false;
            showRemoveConfirm.value = false;
            pendingRemoveIndex.value = null;

            return;
        }

        items.value = (props.invoice.items ?? [])
            .filter((item) => item.is_additional_charge)
            .map((item) => ({
                description: item.goods_name ?? '',
                amount: item.freight_amount ?? '',
            }));
    },
    { immediate: true },
);

watch(
    () => props.saving,
    (isSaving) => {
        if (!isSaving && showSaveConfirm.value) {
            showSaveConfirm.value = false;
        }
    },
);

function addItem() {
    items.value.push({ description: '', amount: '' });
}

function isBlankItem(item) {
    const description = String(item?.description ?? '').trim();
    const amount = String(item?.amount ?? '').trim();

    return description === '' && amount === '';
}

function requestRemoveItem(index) {
    const item = items.value[index];

    if (item && isBlankItem(item)) {
        items.value.splice(index, 1);

        return;
    }

    pendingRemoveIndex.value = index;
    showRemoveConfirm.value = true;
}

function closeRemoveConfirm() {
    showRemoveConfirm.value = false;
    pendingRemoveIndex.value = null;
}

function confirmRemoveItem() {
    if (pendingRemoveIndex.value === null) {
        closeRemoveConfirm();

        return;
    }

    items.value.splice(pendingRemoveIndex.value, 1);
    closeRemoveConfirm();
}

function openSaveConfirm() {
    showSaveConfirm.value = true;
}

function closeSaveConfirm() {
    if (props.saving) {
        return;
    }

    showSaveConfirm.value = false;
}

function confirmSave() {
    emit('submit');
}

function roundMoney(value) {
    return Math.round((Number(value) + Number.EPSILON) * 100) / 100;
}

function formatMoney(value) {
    return Number(value ?? 0).toLocaleString('en-IN', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });
}

function buildPayload() {
    return {
        custom_items: items.value
            .map((item) => ({
                description: String(item.description ?? '').trim(),
                amount: Number(item.amount),
            }))
            .filter((item) => item.description !== '' && Number.isFinite(item.amount) && item.amount >= 0.01),
    };
}

defineExpose({ buildPayload });
</script>

<style scoped>
.invoice-charges-edit__custom-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 0.5rem;
}

.invoice-charges-edit__custom-title {
    font-weight: 600;
    font-size: 0.875rem;
}

.invoice-charges-edit__row {
    display: grid;
    grid-template-columns: 1fr minmax(7rem, 8rem) auto;
    gap: 0.5rem;
    margin-bottom: 0.5rem;
}

.invoice-charges-edit__amount {
    text-align: right;
}
</style>
