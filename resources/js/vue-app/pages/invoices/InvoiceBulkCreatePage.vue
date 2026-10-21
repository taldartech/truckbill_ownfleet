<template>
    <div class="container mt-4 invoice-bulk-create pb-4">
        <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
            <div>
                <RouterLink :to="{ name: 'invoices.index' }" class="text-decoration-none small">
                    <i class="bi bi-arrow-left"></i> Back to Invoices
                </RouterLink>
                <h3 class="mb-1 mt-1">Create Invoice</h3>
                <p class="text-muted small mb-0">
                    Select a customer, choose unbilled trips, then preview and generate one invoice.
                </p>
            </div>
        </div>

        <div v-if="needsCompanySelection" class="alert alert-warning">
            Select a company and click <strong>Enter</strong> to create invoices.
        </div>

        <div v-else class="row g-3">
            <div class="col-lg-8">
                <div class="card mb-3">
                    <div class="card-header"><strong>1. Customer</strong></div>
                    <div class="card-body">
                        <label class="form-label" for="bulkInvoice_customer">Customer</label>
                        <SearchablePicker
                            id="bulkInvoice_customer"
                            v-model="customerId"
                            :items="customers"
                            placeholder="Select customer…"
                            search-placeholder="Search customer…"
                            empty-message="No customer found."
                            view-title="Customer details"
                            :disabled="generating"
                            @update:model-value="onCustomerChange"
                        />
                        <p v-if="fieldErrors.customer_id" class="text-danger small mt-1 mb-0">
                            {{ fieldErrors.customer_id[0] }}
                        </p>
                    </div>
                </div>

                <div class="card mb-3">
                    <div class="card-header">
                        <strong>2. Eligible trips</strong>
                        <div v-if="eligibleTrips.length" class="d-flex align-items-center gap-2 flex-shrink-0">
                            <span class="small">
                                {{ selectedTripIds.length }} selected · ₹{{ formatMoney(selectedFreightTotal) }}
                            </span>
                            <button
                                type="button"
                                class="btn btn-light btn-sm"
                                :disabled="tripsLoading || generating"
                                @click="toggleSelectAll"
                            >
                                {{ allSelected ? 'Clear all' : 'Select all' }}
                            </button>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <div v-if="!customerId" class="text-muted text-center py-4">
                            Select a customer to load unbilled trips.
                        </div>
                        <div v-else-if="tripsLoading" class="text-muted text-center py-4">
                            Loading eligible trips…
                        </div>
                        <div v-else-if="eligibleTrips.length === 0" class="text-muted text-center py-4">
                            No unbilled completed trips for this customer.
                        </div>
                        <div v-else class="table-responsive">
                            <table class="table table-sm table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th style="width: 40px"></th>
                                        <th>Trip</th>
                                        <th>Vehicle</th>
                                        <th>Route</th>
                                        <th>Date</th>
                                        <th class="text-end">Unbilled freight</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr
                                        v-for="trip in eligibleTrips"
                                        :key="trip.id"
                                        class="invoice-bulk-create__trip-row"
                                        @click="toggleTrip(trip.id)"
                                    >
                                        <td @click.stop>
                                            <input
                                                class="form-check-input"
                                                type="checkbox"
                                                :checked="selectedTripIds.includes(trip.id)"
                                                :disabled="generating"
                                                @change="toggleTrip(trip.id)"
                                            />
                                        </td>
                                        <td>
                                            <div class="fw-semibold">{{ trip.trip_number }}</div>
                                            <div class="small text-muted">
                                                {{ trip.unbilled_leg_count }}
                                                route{{ trip.unbilled_leg_count === 1 ? '' : 's' }}
                                            </div>
                                        </td>
                                        <td>{{ trip.vehicle_registration_number || '—' }}</td>
                                        <td class="small">{{ trip.route_summary || '—' }}</td>
                                        <td class="small">
                                            {{ formatDate(trip.start_at) }}
                                            <template v-if="trip.end_at"> – {{ formatDate(trip.end_at) }}</template>
                                        </td>
                                        <td class="text-end">₹{{ formatMoney(trip.unbilled_freight_amount) }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <p v-if="fieldErrors.trip_ids" class="text-danger small px-3 py-2 mb-0">
                            {{ fieldErrors.trip_ids[0] }}
                        </p>
                    </div>
                </div>

                <div class="card mb-3">
                    <div class="card-header"><strong>3. Invoice details</strong></div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">Invoice date</label>
                                <AppDateInput
                                    v-model="invoiceDate"
                                    :disabled="generating"
                                    :invalid="Boolean(fieldErrors.invoice_date)"
                                    @update:model-value="schedulePreview"
                                />
                                <p v-if="fieldErrors.invoice_date" class="text-danger small mt-1 mb-0">
                                    {{ fieldErrors.invoice_date[0] }}
                                </p>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Due date</label>
                                <AppDateInput
                                    v-model="dueDate"
                                    :min="invoiceDate || undefined"
                                    :disabled="generating"
                                    :invalid="Boolean(fieldErrors.due_date)"
                                    @update:model-value="schedulePreview"
                                />
                                <p v-if="fieldErrors.due_date" class="text-danger small mt-1 mb-0">
                                    {{ fieldErrors.due_date[0] }}
                                </p>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Particulars</label>
                                <div class="d-flex flex-column gap-1 pt-1">
                                    <label class="form-check mb-0">
                                        <input
                                            v-model="particularsMode"
                                            class="form-check-input"
                                            type="radio"
                                            value="combined"
                                            :disabled="generating"
                                            @change="schedulePreview"
                                        />
                                        <span class="form-check-label small">One line per trip</span>
                                    </label>
                                    <label class="form-check mb-0">
                                        <input
                                            v-model="particularsMode"
                                            class="form-check-input"
                                            type="radio"
                                            value="separate"
                                            :disabled="generating"
                                            @change="schedulePreview"
                                        />
                                        <span class="form-check-label small">Separate line per route</span>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <div class="mt-3">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <strong class="small">Additional charges</strong>
                                <button
                                    type="button"
                                    class="btn btn-link btn-sm p-0"
                                    :disabled="generating"
                                    @click="addCustomItem"
                                >
                                    <i class="bi bi-plus-lg"></i> Add
                                </button>
                            </div>
                            <div
                                v-for="(item, index) in customItems"
                                :key="`custom-${index}`"
                                class="row g-2 mb-2"
                            >
                                <div class="col">
                                    <input
                                        v-model="item.description"
                                        type="text"
                                        class="form-control form-control-sm"
                                        placeholder="Description"
                                        :disabled="generating"
                                        @input="schedulePreview"
                                    />
                                </div>
                                <div class="col-auto" style="width: 140px">
                                    <input
                                        v-model="item.amount"
                                        type="number"
                                        min="0.01"
                                        step="0.01"
                                        class="form-control form-control-sm"
                                        placeholder="Amount"
                                        :disabled="generating"
                                        @input="schedulePreview"
                                    />
                                </div>
                                <div class="col-auto">
                                    <button
                                        type="button"
                                        class="btn btn-outline-danger btn-sm"
                                        :disabled="generating"
                                        @click="removeCustomItem(index)"
                                    >
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card sticky-top" style="top: 1rem">
                    <div class="card-header">
                        <strong>4. Preview</strong>
                        <span v-if="previewRefreshing" class="small flex-shrink-0">
                            <span class="spinner-border spinner-border-sm" role="status"></span>
                        </span>
                    </div>
                    <div class="card-body">
                        <div v-if="formError" class="alert alert-danger py-2 small">{{ formError }}</div>

                        <div v-if="!preview" class="text-muted small">
                            Select trips to see GST and totals.
                        </div>
                        <template v-else>
                            <div class="small text-muted mb-2">
                                {{ preview.document_type_label || preview.document_type }}
                                <span v-if="preview.invoice_number"> · {{ preview.invoice_number }}</span>
                            </div>
                            <div class="d-flex justify-content-between small mb-1">
                                <span>Route freight</span>
                                <span>₹{{ formatMoney(preview.route_freight_amount) }}</span>
                            </div>
                            <div v-if="Number(preview.custom_amount) > 0" class="d-flex justify-content-between small mb-1">
                                <span>Additional charges</span>
                                <span>₹{{ formatMoney(preview.custom_amount) }}</span>
                            </div>
                            <div v-if="preview.displays_gst" class="d-flex justify-content-between small mb-1">
                                <span>GST</span>
                                <span>₹{{ formatMoney(preview.gst_amount) }}</span>
                            </div>
                            <div class="d-flex justify-content-between fw-semibold border-top pt-2 mt-2">
                                <span>Total</span>
                                <span>₹{{ formatMoney(preview.total_amount) }}</span>
                            </div>

                            <div v-if="preview.invoice_lines?.length" class="mt-3">
                                <div class="small text-muted mb-1">Lines</div>
                                <div
                                    v-for="(line, index) in preview.invoice_lines"
                                    :key="`line-${index}`"
                                    class="small border rounded px-2 py-1 mb-1"
                                >
                                    <div>{{ line.goods_name || 'Freight' }}</div>
                                    <div class="text-muted">
                                        ₹{{ formatMoney(line.freight_amount) }}
                                    </div>
                                </div>
                            </div>
                        </template>
                    </div>
                    <div class="card-footer d-grid gap-2">
                        <AppButton
                            variant="primary"
                            label="Generate invoice"
                            icon="bi bi-receipt"
                            :loading="generating"
                            :disabled="!canGenerate"
                            @click="generate"
                        />
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { RouterLink, useRouter } from 'vue-router';
import { listAllCustomers } from '../../api/customers';
import {
    generateBulkInvoice,
    listEligibleTrips,
    previewBulkInvoice,
} from '../../api/invoices';
import AppButton from '../../components/ui/AppButton.vue';
import AppDateInput from '../../components/ui/AppDateInput.vue';
import SearchablePicker from '../../components/ui/SearchablePicker.vue';
import { useAppToast } from '../../composables/useAppToast';
import { useRequireCompanyContext } from '../../composables/useRequireCompanyContext';
import { formatDate } from '../../utils/datetime';

const router = useRouter();
const toast = useAppToast();
const { needsCompanySelection, guardCompanyContext } = useRequireCompanyContext();

const customers = ref([]);
const customerId = ref('');
const eligibleTrips = ref([]);
const selectedTripIds = ref([]);
const tripsLoading = ref(false);
const invoiceDate = ref(new Date().toISOString().slice(0, 10));
const dueDate = ref('');
const particularsMode = ref('combined');
const customItems = ref([]);
const preview = ref(null);
const previewRefreshing = ref(false);
const generating = ref(false);
const formError = ref('');
const fieldErrors = ref({});

let previewTimer = null;

const allSelected = computed(
    () => eligibleTrips.value.length > 0
        && selectedTripIds.value.length === eligibleTrips.value.length,
);

const selectedFreightTotal = computed(() =>
    eligibleTrips.value
        .filter((trip) => selectedTripIds.value.includes(trip.id))
        .reduce((sum, trip) => sum + Number(trip.unbilled_freight_amount ?? 0), 0),
);

const canGenerate = computed(
    () => Boolean(customerId.value)
        && selectedTripIds.value.length > 0
        && Boolean(preview.value)
        && !previewRefreshing.value
        && !generating.value,
);

function formatMoney(value) {
    return Number(value ?? 0).toLocaleString('en-IN', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });
}

function payload() {
    return {
        customer_id: customerId.value,
        trip_ids: [...selectedTripIds.value],
        invoice_date: invoiceDate.value || undefined,
        due_date: dueDate.value || undefined,
        particulars_mode: particularsMode.value,
        custom_items: customItems.value
            .filter((item) => String(item.description || '').trim() !== '' && Number(item.amount) > 0)
            .map((item) => ({
                description: String(item.description).trim(),
                amount: Number(item.amount),
            })),
    };
}

async function loadCustomers() {
    customers.value = await listAllCustomers();
}

async function loadEligibleTrips() {
    if (!customerId.value) {
        eligibleTrips.value = [];
        selectedTripIds.value = [];
        preview.value = null;

        return;
    }

    tripsLoading.value = true;
    formError.value = '';
    fieldErrors.value = {};

    try {
        eligibleTrips.value = await listEligibleTrips({ customerId: customerId.value });
        selectedTripIds.value = [];
        preview.value = null;
    } catch (error) {
        eligibleTrips.value = [];
        formError.value = error?.response?.data?.message || 'Unable to load eligible trips.';
    } finally {
        tripsLoading.value = false;
    }
}

function onCustomerChange() {
    loadEligibleTrips();
}

function toggleTrip(tripId) {
    if (generating.value) {
        return;
    }

    if (selectedTripIds.value.includes(tripId)) {
        selectedTripIds.value = selectedTripIds.value.filter((id) => id !== tripId);
    } else {
        selectedTripIds.value = [...selectedTripIds.value, tripId];
    }

    schedulePreview();
}

function toggleSelectAll() {
    if (allSelected.value) {
        selectedTripIds.value = [];
    } else {
        selectedTripIds.value = eligibleTrips.value.map((trip) => trip.id);
    }

    schedulePreview();
}

function addCustomItem() {
    customItems.value.push({ description: '', amount: '' });
}

function removeCustomItem(index) {
    customItems.value.splice(index, 1);
    schedulePreview();
}

function schedulePreview() {
    if (previewTimer) {
        clearTimeout(previewTimer);
    }

    previewTimer = setTimeout(() => {
        refreshPreview();
    }, 250);
}

async function refreshPreview() {
    if (!customerId.value || selectedTripIds.value.length === 0) {
        preview.value = null;

        return;
    }

    previewRefreshing.value = true;
    formError.value = '';
    fieldErrors.value = {};

    try {
        preview.value = await previewBulkInvoice(payload());
    } catch (error) {
        preview.value = null;
        fieldErrors.value = error?.response?.data?.errors || {};
        formError.value = error?.response?.data?.message
            || Object.values(fieldErrors.value).flat()[0]
            || 'Unable to preview invoice.';
    } finally {
        previewRefreshing.value = false;
    }
}

async function generate() {
    if (!canGenerate.value) {
        return;
    }

    generating.value = true;
    formError.value = '';
    fieldErrors.value = {};

    try {
        const invoice = await generateBulkInvoice(payload());
        toast.success(`Invoice ${invoice.invoice_number || ''} created.`);
        await router.push({ name: 'invoices.show', params: { id: invoice.id } });
    } catch (error) {
        fieldErrors.value = error?.response?.data?.errors || {};
        formError.value = error?.response?.data?.message
            || Object.values(fieldErrors.value).flat()[0]
            || 'Unable to generate invoice.';
    } finally {
        generating.value = false;
    }
}

watch(selectedTripIds, () => {
    if (selectedTripIds.value.length === 0) {
        preview.value = null;
    }
});

onMounted(async () => {
    if (!guardCompanyContext()) {
        return;
    }

    await loadCustomers();
});
</script>

<style scoped>
.invoice-bulk-create__trip-row {
    cursor: pointer;
}

.invoice-bulk-create__trip-row:hover {
    background: rgba(13, 110, 253, 0.04);
}
</style>
