<template>
    <div
        :id="modalId"
        ref="modalElement"
        class="modal fade"
        tabindex="-1"
        :aria-labelledby="`${modalId}Label`"
        aria-hidden="true"
    >
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-gradient-primary">
                    <h5 :id="`${modalId}Label`" class="modal-title">
                        <i class="fas fa-wallet me-2"></i>
                        Driver Advance — {{ driverName || '—' }}
                    </h5>
                    <button type="button" class="btn-close" aria-label="Close" @click="close"></button>
                </div>
                <div class="modal-body">
                    <div class="d-flex align-items-center justify-content-between mb-3 p-3 bg-light rounded">
                        <span class="text-muted">Wallet balance</span>
                        <span class="fs-4 fw-bold text-dark">{{ formatMoney(balance) }}</span>
                    </div>

                    <div v-if="canManage" class="card mb-3">
                        <div class="card-header py-2">
                            <strong>Add entry</strong>
                        </div>
                        <div class="card-body">
                            <form novalidate @submit.prevent="submit">
                                <div class="row g-2">
                                    <div class="col-md-3">
                                        <label :for="`${modalId}_entry_type`" class="form-label">Type *</label>
                                        <select
                                            :id="`${modalId}_entry_type`"
                                            v-model="form.entry_type"
                                            class="form-select"
                                            :class="{ 'is-invalid': fieldErrors.entry_type }"
                                            required
                                        >
                                            <option value="dr">Dr — Advance given</option>
                                            <option value="cr">Cr — Amount returned</option>
                                        </select>
                                        <div v-if="fieldErrors.entry_type" class="invalid-feedback d-block">
                                            {{ fieldErrors.entry_type[0] }}
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <label :for="`${modalId}_amount`" class="form-label">Amount *</label>
                                        <input
                                            :id="`${modalId}_amount`"
                                            v-model.number="form.amount"
                                            v-no-scroll-change
                                            type="number"
                                            step="0.01"
                                            min="0.01"
                                            class="form-control"
                                            :class="{ 'is-invalid': fieldErrors.amount }"
                                            required
                                        />
                                        <div v-if="fieldErrors.amount" class="invalid-feedback d-block">
                                            {{ fieldErrors.amount[0] }}
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <label :for="`${modalId}_entry_date`" class="form-label">Date *</label>
                                        <AppDateInput
                                            :input-id="`${modalId}_entry_date`"
                                            v-model="form.entry_date"
                                            :invalid="!!fieldErrors.entry_date"
                                            required
                                        />
                                        <div v-if="fieldErrors.entry_date" class="invalid-feedback d-block">
                                            {{ fieldErrors.entry_date[0] }}
                                        </div>
                                    </div>
                                    <div class="col-md-3 d-flex align-items-end">
                                        <button type="submit" class="btn btn-success w-100" :disabled="saving">
                                            {{ saving ? 'Saving…' : 'Add' }}
                                        </button>
                                    </div>
                                    <div class="col-12">
                                        <label :for="`${modalId}_remarks`" class="form-label">Remarks</label>
                                        <input
                                            :id="`${modalId}_remarks`"
                                            v-model="form.remarks"
                                            type="text"
                                            class="form-control"
                                            :class="{ 'is-invalid': fieldErrors.remarks }"
                                            maxlength="1000"
                                            placeholder="Optional note"
                                        />
                                        <div v-if="fieldErrors.remarks" class="invalid-feedback d-block">
                                            {{ fieldErrors.remarks[0] }}
                                        </div>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>

                    <div v-if="loading" class="text-center text-muted py-3">Loading entries…</div>
                    <div v-else class="table-responsive">
                        <table class="table table-sm table-bordered mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Date</th>
                                    <th>Type</th>
                                    <th class="text-end">Amount</th>
                                    <th>Remarks</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-if="entries.length === 0">
                                    <td colspan="4" class="text-center text-muted py-3">No entries yet.</td>
                                </tr>
                                <tr v-for="entry in entries" :key="entry.id">
                                    <td>{{ formatDate(entry.entry_date) }}</td>
                                    <td>
                                        <span
                                            class="badge"
                                            :class="entry.entry_type === 'dr' ? 'bg-danger' : 'bg-success'"
                                        >
                                            {{ entry.entry_type === 'dr' ? 'Dr' : 'Cr' }}
                                        </span>
                                    </td>
                                    <td class="text-end">{{ formatMoney(entry.amount) }}</td>
                                    <td>{{ entry.remarks || '—' }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" @click="close">Close</button>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, watch } from 'vue';
import { fetchDriverAdvanceLedger } from '../../api/driverAdvances';
import { formatDate, toDateInputValue } from '../../utils/datetime';
import { useAppToast } from '../../composables/useAppToast';

const toast = useAppToast();

const props = defineProps({
    modalId: { type: String, required: true },
    show: { type: Boolean, default: false },
    driverId: { type: String, default: null },
    driverName: { type: String, default: '' },
    canManage: { type: Boolean, default: false },
    saving: { type: Boolean, default: false },
    fieldErrors: { type: Object, default: () => ({}) },
});

const emit = defineEmits(['close', 'submit', 'loaded']);

const modalElement = ref(null);
const loading = ref(false);
const balance = ref(0);
const entries = ref([]);

const form = ref({
    entry_type: 'dr',
    amount: '',
    entry_date: toDateInputValue(),
    remarks: '',
});

function formatMoney(value) {
    const n = Number(value);

    if (Number.isNaN(n)) {
        return '0.00';
    }

    return n.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function resetForm() {
    form.value = {
        entry_type: 'dr',
        amount: '',
        entry_date: toDateInputValue(),
        remarks: '',
    };
}

async function loadLedger() {
    if (!props.driverId) {
        return;
    }

    loading.value = true;

    try {
        const data = await fetchDriverAdvanceLedger(props.driverId);
        balance.value = Number(data.balance ?? 0);
        entries.value = data.entries ?? [];
        emit('loaded', data);
    } catch {
        toast.error('Could not load advance ledger.');
        balance.value = 0;
        entries.value = [];
    } finally {
        loading.value = false;
    }
}

function close() {
    emit('close');
}

async function submit() {
    emit('submit', { ...form.value });
}

watch(
    () => props.show,
    async (visible) => {
        const Modal = window.bootstrap?.Modal;

        if (!modalElement.value || !Modal) {
            return;
        }

        const instance = Modal.getOrCreateInstance(modalElement.value);

        if (visible) {
            resetForm();
            await loadLedger();
            instance.show();
        } else {
            instance.hide();
        }
    },
);

defineExpose({ loadLedger, setBalance: (v) => { balance.value = v; } });
</script>
