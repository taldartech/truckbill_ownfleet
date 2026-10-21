<template>
    <div class="maintenance-form trf">
        <div v-if="displayFormError" class="trf-validation-bar" role="alert">
            <i class="bi bi-exclamation-circle-fill" aria-hidden="true"></i>
            <strong>{{ displayFormError }}</strong>
        </div>

        <section class="trf-section">
            <div class="trf-section__head">
                <div class="trf-section__head-left">
                    <div class="trf-step-num">1</div>
                    <div>
                        <h2 class="trf-section__title">Maintenance Details</h2>
                        <p class="trf-section__desc">Select the truck and record the service date, meter reading, and cost</p>
                    </div>
                </div>
                <div class="trf-section__head-right">
                    <span class="trf-mode-badge">
                        <i class="bi bi-wrench-adjustable" aria-hidden="true"></i>
                        {{ headerTitle }}
                    </span>
                </div>
            </div>

            <div class="trf-section__body">
                <div class="trf-field-grid trf-field-grid--primary">
                    <div class="trf-pf">
                        <label :for="`${formId}_vehicle`" class="trf-pf__lbl">
                            <span class="trf-pf__lbl-icon trf-pf__lbl-icon--blue">
                                <i class="bi bi-truck" aria-hidden="true"></i>
                            </span>
                            <span>Truck <span class="trf-pf__req">*</span></span>
                        </label>
                        <VehicleSelect
                            :input-id="`${formId}_vehicle`"
                            v-model="form.vehicle_id"
                            :vehicles="vehicles"
                            :lookup-vehicle="lookupVehicle"
                            placeholder="Select truck"
                            :disabled="lockVehicle || readonly"
                            :invalid="Boolean(displayFieldErrors.vehicle_id)"
                            :error-message="displayFieldErrors.vehicle_id?.[0] ?? ''"
                            @update:model-value="$emit('clear-field-error', 'vehicle_id')"
                        />
                        <div v-if="truckModelDisplay" class="trf-pf__meta">
                            <span class="trf-pf__meta-label">Truck Model</span>
                            <span class="trf-pf__meta-val">{{ truckModelDisplay }}</span>
                        </div>
                    </div>

                    <div class="trf-pf">
                        <label :for="`${formId}_service_date`" class="trf-pf__lbl">
                            <span class="trf-pf__lbl-icon trf-pf__lbl-icon--indigo">
                                <i class="bi bi-calendar-check" aria-hidden="true"></i>
                            </span>
                            <span>Service Date <span class="trf-pf__req">*</span></span>
                        </label>
                        <AppDateInput
                            :input-id="`${formId}_service_date`"
                            v-model="form.service_date"
                            :invalid="!!displayFieldErrors.service_date"
                            :max="today"
                            :readonly="readonly"
                            required
                            @update:model-value="$emit('clear-field-error', 'service_date')"
                        />
                        <div v-if="displayFieldErrors.service_date" class="trf-pf__err">
                            {{ displayFieldErrors.service_date[0] }}
                        </div>
                    </div>

                    <div class="trf-pf">
                        <label :for="`${formId}_odometer_km`" class="trf-pf__lbl">
                            <span class="trf-pf__lbl-icon trf-pf__lbl-icon--orange">
                                <i class="bi bi-speedometer2" aria-hidden="true"></i>
                            </span>
                            <span>Meter Reading (KM) <span class="trf-pf__req">*</span></span>
                        </label>
                        <input
                            :id="`${formId}_odometer_km`"
                            v-model="form.odometer_km"
                            v-no-scroll-change
                            type="number"
                            min="0"
                            step="0.01"
                            class="trf-input"
                            :class="{ 'trf-input--error': displayFieldErrors.odometer_km }"
                            placeholder="Reading at service"
                            :readonly="readonly"
                            required
                            @input="$emit('clear-field-error', 'odometer_km')"
                        />
                        <div v-if="truckRunningMeterDisplay" class="trf-pf__meta">
                            <span class="trf-pf__meta-label">Current Truck Reading</span>
                            <span class="trf-pf__meta-val">{{ truckRunningMeterDisplay }} KM</span>
                        </div>
                        <div v-if="displayFieldErrors.odometer_km" class="trf-pf__err">
                            {{ displayFieldErrors.odometer_km[0] }}
                        </div>
                    </div>

                    <div class="trf-pf">
                        <label :for="`${formId}_cost_amount`" class="trf-pf__lbl">
                            <span class="trf-pf__lbl-icon trf-pf__lbl-icon--teal">
                                <i class="bi bi-currency-rupee" aria-hidden="true"></i>
                            </span>
                            <span>Cost Amount <span class="trf-pf__req">*</span></span>
                        </label>
                        <div class="trf-input-prefix">
                            <span class="trf-input-prefix__addon">₹</span>
                            <input
                                :id="`${formId}_cost_amount`"
                                v-model="form.cost_amount"
                                v-no-scroll-change
                                type="number"
                                min="0.01"
                                step="0.01"
                                class="trf-input trf-input--prefixed"
                                :class="{ 'trf-input--error': displayFieldErrors.cost_amount }"
                                placeholder="0.00"
                                :readonly="readonly"
                                required
                                @input="$emit('clear-field-error', 'cost_amount')"
                            />
                        </div>
                        <div v-if="displayFieldErrors.cost_amount" class="trf-pf__err">
                            {{ displayFieldErrors.cost_amount[0] }}
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="trf-section">
            <div class="trf-section__head trf-section__head--green">
                <div class="trf-section__head-left">
                    <div class="trf-step-num trf-step-num--green">2</div>
                    <div>
                        <h2 class="trf-section__title">Service &amp; Next Due</h2>
                        <p class="trf-section__desc">Record the workshop and optionally schedule the next service</p>
                    </div>
                </div>
            </div>

            <div class="trf-section__body">
                <div class="trf-field-grid trf-field-grid--service">
                    <div class="trf-pf">
                        <label :for="`${formId}_service_type`" class="trf-pf__lbl">
                            <span class="trf-pf__lbl-icon trf-pf__lbl-icon--purple">
                                <i class="bi bi-gear" aria-hidden="true"></i>
                            </span>
                            <span>Service Type <span class="trf-pf__req">*</span></span>
                        </label>
                        <MaintenanceServiceTypeSelect
                            :input-id="`${formId}_service_type`"
                            v-model="form.maintenance_service_type_id"
                            :service-types="serviceTypes"
                            :lookup-service-type="lookupServiceType"
                            placeholder="Select service type"
                            :disabled="readonly"
                            :invalid="Boolean(displayFieldErrors.maintenance_service_type_id)"
                            :error-message="displayFieldErrors.maintenance_service_type_id?.[0] ?? ''"
                            @update:model-value="$emit('clear-field-error', 'maintenance_service_type_id')"
                        />
                    </div>

                    <div class="trf-pf">
                        <label :for="`${formId}_service_provider`" class="trf-pf__lbl">
                            <span class="trf-pf__lbl-icon trf-pf__lbl-icon--slate">
                                <i class="bi bi-building" aria-hidden="true"></i>
                            </span>
                            <span>Service Provider <span class="trf-pf__req">*</span></span>
                        </label>
                        <input
                            :id="`${formId}_service_provider`"
                            v-model="form.service_provider"
                            type="text"
                            class="trf-input"
                            :class="{ 'trf-input--error': displayFieldErrors.service_provider }"
                            placeholder="Garage or workshop name"
                            :readonly="readonly"
                            required
                            @input="$emit('clear-field-error', 'service_provider')"
                        />
                        <div v-if="displayFieldErrors.service_provider" class="trf-pf__err">
                            {{ displayFieldErrors.service_provider[0] }}
                        </div>
                    </div>

                    <div class="trf-pf">
                        <label :for="`${formId}_next_service_due_km`" class="trf-pf__lbl">
                            <span class="trf-pf__lbl-icon trf-pf__lbl-icon--orange">
                                <i class="bi bi-signpost-2" aria-hidden="true"></i>
                            </span>
                            <span>Next Due KM <span class="trf-pf__optional">Optional</span></span>
                        </label>
                        <input
                            :id="`${formId}_next_service_due_km`"
                            v-model="form.next_service_due_km"
                            v-no-scroll-change
                            type="number"
                            min="0"
                            step="0.01"
                            class="trf-input"
                            :class="{ 'trf-input--error': displayFieldErrors.next_service_due_km }"
                            placeholder="Enter next due reading"
                            :readonly="readonly"
                            @input="$emit('clear-field-error', 'next_service_due_km')"
                        />
                        <div v-if="displayFieldErrors.next_service_due_km" class="trf-pf__err">
                            {{ displayFieldErrors.next_service_due_km[0] }}
                        </div>
                    </div>

                    <div class="trf-pf">
                        <label :for="`${formId}_next_service_due_date`" class="trf-pf__lbl">
                            <span class="trf-pf__lbl-icon trf-pf__lbl-icon--indigo">
                                <i class="bi bi-calendar-event" aria-hidden="true"></i>
                            </span>
                            <span>Next Due Date <span class="trf-pf__optional">Optional</span></span>
                        </label>
                        <AppDateInput
                            :input-id="`${formId}_next_service_due_date`"
                            v-model="form.next_service_due_date"
                            :invalid="!!displayFieldErrors.next_service_due_date"
                            :min="minNextDueDate || undefined"
                            :disabled="!form.service_date || readonly"
                            @update:model-value="$emit('clear-field-error', 'next_service_due_date')"
                        />
                        <div v-if="displayFieldErrors.next_service_due_date" class="trf-pf__err">
                            {{ displayFieldErrors.next_service_due_date[0] }}
                        </div>
                    </div>
                </div>

                <div class="trf-notes-row">
                    <div class="trf-pf">
                        <label :for="`${formId}_remarks`" class="trf-pf__lbl">
                            <span class="trf-pf__lbl-icon trf-pf__lbl-icon--slate">
                                <i class="bi bi-chat-left-text" aria-hidden="true"></i>
                            </span>
                            <span>Remarks <span class="trf-pf__optional">Optional</span></span>
                        </label>
                        <textarea
                            :id="`${formId}_remarks`"
                            v-model="form.remarks"
                            class="trf-input trf-input--textarea"
                            :class="{ 'trf-input--error': displayFieldErrors.remarks }"
                            rows="2"
                            placeholder="Work completed, parts replaced, observations…"
                            :readonly="readonly"
                            @input="$emit('clear-field-error', 'remarks')"
                        ></textarea>
                        <div v-if="displayFieldErrors.remarks" class="trf-pf__err">
                            {{ displayFieldErrors.remarks[0] }}
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import AppDateInput from '../ui/AppDateInput.vue';
import MaintenanceServiceTypeSelect from '../ui/MaintenanceServiceTypeSelect.vue';
import VehicleSelect from '../ui/VehicleSelect.vue';
import { toDateInputValue } from '../../utils/datetime';

const props = defineProps({
    mode: { type: String, default: 'create' },
    form: { type: Object, required: true },
    vehicles: { type: Array, default: () => [] },
    serviceTypes: { type: Array, default: () => [] },
    lookupVehicle: { type: Object, default: null },
    lookupServiceType: { type: Object, default: null },
    lockVehicle: { type: Boolean, default: false },
    readonly: { type: Boolean, default: false },
    fieldErrors: { type: Object, default: () => ({}) },
    formError: { type: String, default: '' },
    minNextDueDate: { type: String, default: '' },
    formId: { type: String, default: 'maintenanceForm' },
});

defineEmits(['clear-field-error']);

const today = computed(() => toDateInputValue());
const displayFieldErrors = computed(() => props.fieldErrors ?? {});
const displayFormError = computed(() => props.formError ?? '');
const headerTitle = computed(() => (props.mode === 'edit' ? 'Edit Maintenance Record' : 'Add Maintenance Record'));
const selectedVehicle = computed(() => {
    const vehicle = props.vehicles.find((item) => String(item.id) === String(props.form.vehicle_id));

    if (vehicle) {
        return vehicle;
    }

    return String(props.lookupVehicle?.id ?? '') === String(props.form.vehicle_id)
        ? props.lookupVehicle
        : null;
});
const truckModelDisplay = computed(() =>
    [selectedVehicle.value?.make, selectedVehicle.value?.model].filter(Boolean).join(' '),
);
const truckRunningMeterDisplay = computed(() => {
    const odometer = selectedVehicle.value?.odometer_km;

    if (odometer === null || odometer === undefined) {
        return '';
    }

    return Number(odometer).toLocaleString('en-IN');
});

</script>

<style scoped>
.trf {
    --radius: 12px;
    --border: var(--brand-border, #e2e8f0);
    --shadow: 0 1px 3px rgba(0, 0, 0, 0.07), 0 1px 2px rgba(0, 0, 0, 0.04);
    --primary: #0d6efd;
    --success: #198754;
    --text: var(--brand-text-primary, #475569);
    --muted: var(--brand-text-muted, #64748b);
    --danger: #dc3545;
    --fs-title: var(--app-font-size-section-title, 0.9rem);
    --fs-desc: var(--app-font-size-meta, 0.75rem);
    --fs-label: var(--app-font-size-label, 0.8rem);
    --fs-input: var(--app-font-size-input, 0.875rem);
    --fs-meta: var(--app-font-size-meta, 0.75rem);
    --fs-hint: var(--app-font-size-meta, 0.75rem);
    --input-min-h: 40px;
    color: var(--text);
    font-family: var(--app-font-family);
    font-size: var(--fs-input);
}

.trf-validation-bar {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin-bottom: 12px;
    padding: 8px 12px;
    border: 1px solid #fca5a5;
    border-radius: 8px;
    background: #fff5f5;
    color: #b91c1c;
    font-size: 0.76rem;
}

.trf-section {
    margin-bottom: 20px;
    overflow: hidden;
    border: 1px solid var(--border);
    border-radius: var(--radius);
    background: #fff;
    box-shadow: var(--shadow);
}

.trf-section__head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    min-height: 62px;
    gap: 14px;
    padding: 12px 20px;
    border-bottom: 1px solid var(--border);
    background: linear-gradient(135deg, #e7f1ff 0%, #f8f9fa 100%);
}

.trf-section__head--green {
    border-bottom-color: #badbcc;
    background: linear-gradient(135deg, #d1e7dd 0%, #f8f9fa 100%);
}

.trf-section__head--plain {
    background: #fafafa;
}

.trf-section__head-left {
    display: flex;
    align-items: center;
    min-width: 0;
    gap: 14px;
}

.trf-section__head-right {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    min-width: 0;
}

.trf-section__title {
    margin: 0;
    color: var(--text);
    font-size: var(--fs-title);
    font-weight: 700;
    line-height: 1.3;
}

.trf-section__desc {
    margin: 2px 0 0;
    color: var(--muted);
    font-size: var(--fs-desc);
}

.trf-section__body {
    padding: 18px 20px;
}

.trf-step-num {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 32px;
    height: 32px;
    flex-shrink: 0;
    border-radius: 50%;
    background: var(--primary);
    color: #fff;
    font-size: 0.8rem;
    font-weight: 800;
}

.trf-step-num--green {
    background: var(--success);
}

.trf-step-num--gray {
    background: #6c757d;
}

.trf-mode-badge,
.trf-optional-badge {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 4px 9px;
    border: 1px solid #bfdbfe;
    border-radius: 999px;
    background: #eff6ff;
    color: #1d4ed8;
    font-size: 0.68rem;
    font-weight: 700;
    white-space: nowrap;
}

.trf-optional-badge {
    border-color: #e2e8f0;
    background: #f8fafc;
    color: #64748b;
}

.trf-field-grid {
    display: grid;
    align-items: start;
    gap: 14px 16px;
}

.trf-field-grid--primary {
    grid-template-columns: minmax(0, 1.2fr) minmax(0, 0.85fr) minmax(0, 1fr) minmax(0, 0.75fr);
}

.trf-field-grid--service {
    grid-template-columns: minmax(0, 1fr) minmax(0, 1.25fr) minmax(0, 0.85fr) minmax(0, 0.85fr);
}

.trf-notes-row {
    margin-top: 16px;
    padding-top: 14px;
    border-top: 1px solid #e9ecef;
}

.trf-pf {
    display: flex;
    flex-direction: column;
    min-width: 0;
    max-width: 100%;
    gap: 6px;
}

.trf-pf__lbl {
    display: flex;
    align-items: center;
    min-height: 22px;
    gap: 7px;
    margin: 0;
    color: var(--form-label-color, #475569);
    font-size: var(--fs-label);
    font-weight: var(--form-label-weight, 600);
}

.trf-pf__lbl-icon {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 22px;
    height: 22px;
    flex-shrink: 0;
    border-radius: 6px;
    font-size: 0.75rem;
}

.trf-pf__lbl-icon--blue { background: #eff6ff; color: #2563eb; }
.trf-pf__lbl-icon--indigo { background: #eef2ff; color: #4f46e5; }
.trf-pf__lbl-icon--purple { background: #faf5ff; color: #7c3aed; }
.trf-pf__lbl-icon--orange { background: #fff7ed; color: #ea580c; }
.trf-pf__lbl-icon--teal { background: #f0fdfa; color: #0d9488; }
.trf-pf__lbl-icon--slate { background: #f1f5f9; color: #475569; }

.trf-pf__req {
    color: var(--danger);
}

.trf-pf__optional {
    margin-left: 3px;
    color: #94a3b8;
    font-size: var(--fs-hint);
    font-weight: 500;
}

.trf-input {
    box-sizing: border-box;
    width: 100%;
    min-height: var(--input-min-h);
    padding: 8px 11px;
    border: 1.5px solid var(--border);
    border-radius: 8px;
    outline: none;
    background: #fff;
    color: var(--form-input-color, #1f2937);
    font-size: var(--fs-input);
    font-weight: var(--form-input-weight, 500);
    transition: border-color 0.15s, box-shadow 0.15s;
}

.trf-input:focus {
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(13, 110, 253, 0.15);
}

.trf-input[readonly],
.trf-input:disabled {
    cursor: default;
    background: #f1f5f9;
    color: #64748b;
}

.trf-input--error {
    border-color: var(--danger);
}

.trf-input--textarea {
    min-height: 76px;
    resize: vertical;
    line-height: 1.5;
}

.trf-input-prefix {
    position: relative;
}

.trf-input-prefix__addon {
    position: absolute;
    top: 50%;
    left: 11px;
    z-index: 1;
    color: #64748b;
    font-weight: 700;
    transform: translateY(-50%);
}

.trf-input--prefixed {
    padding-left: 26px;
}

.trf-pf__meta {
    display: flex;
    flex-wrap: wrap;
    align-items: baseline;
    gap: 4px 6px;
    margin-top: 1px;
    line-height: 1.3;
}

.trf-pf__meta-label {
    color: var(--muted);
    font-size: var(--fs-meta);
    font-weight: 600;
}

.trf-pf__meta-val {
    color: #475569;
    font-size: var(--fs-meta);
    font-weight: 700;
}

.trf-pf__err {
    color: var(--danger);
    font-size: var(--fs-hint);
    font-weight: 600;
}

.trf-pf :deep(.searchable-select),
.trf-pf :deep(.app-date-input) {
    width: 100%;
    max-width: 100%;
}

.trf-pf :deep(.select-trigger),
.trf-pf :deep(.app-date-input__text) {
    min-height: var(--input-min-h);
    border-width: 1.5px;
    border-radius: 8px;
    font-size: var(--fs-input);
}

@media (max-width: 1199.98px) {
    .trf-field-grid--primary,
    .trf-field-grid--service {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media (max-width: 767.98px) {
    .trf {
        --input-min-h: 34px;
        --fs-title: 0.84rem;
        --fs-desc: 0.68rem;
        --fs-label: 0.72rem;
        --fs-input: 0.76rem;
        --fs-meta: 0.65rem;
        --fs-hint: 0.65rem;
    }

    .trf-section {
        margin-bottom: 12px;
        border-radius: 10px;
    }

    .trf-section__head {
        min-height: 44px;
        padding: 8px 10px;
        gap: 8px;
    }

    .trf-section__head-left {
        gap: 8px;
    }

    .trf-step-num {
        width: 24px;
        height: 24px;
        font-size: 0.72rem;
    }

    .trf-section__title {
        font-size: 0.82rem;
    }

    .trf-section__desc {
        font-size: 0.65rem;
        display: -webkit-box;
        -webkit-line-clamp: 1;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }

    .trf-mode-badge,
    .trf-optional-badge {
        padding: 2px 7px;
        font-size: 0.62rem;
    }

    .trf-section__body {
        padding: 10px 10px 12px;
    }

    /* 2 input boxes per row on mobile as requested */
    .trf-field-grid--primary,
    .trf-field-grid--service {
        grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
        gap: 9px 8px !important;
    }

    .trf-pf {
        gap: 4px;
    }

    .trf-pf__lbl {
        min-height: 18px;
        gap: 4px;
        font-size: 0.7rem;
        line-height: 1.15;
    }

    .trf-pf__lbl > span:not(.trf-pf__lbl-icon) {
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .trf-pf__lbl-icon {
        width: 17px;
        height: 17px;
        border-radius: 4px;
        font-size: 0.62rem;
    }

    .trf-pf__optional {
        font-size: 0.6rem;
        margin-left: 2px;
    }

    .trf-input {
        min-height: 33px;
        padding: 5px 8px;
        font-size: 0.75rem;
        border-radius: 6px;
    }

    .trf-input-prefix__addon {
        left: 7px;
        font-size: 0.74rem;
    }

    .trf-input--prefixed {
        padding-left: 19px !important;
    }

    .trf-input--textarea {
        min-height: 58px;
        padding: 6px 8px;
    }

    .trf-pf :deep(.select-trigger) {
        min-height: 33px !important;
        padding: 4px 24px 4px 7px !important;
        font-size: 0.75rem !important;
        border-radius: 6px !important;
    }

    .trf-pf :deep(.select-trigger-wrap:has(.select-trigger-icons) .select-trigger) {
        padding-right: 36px !important;
    }

    .trf-pf :deep(.select-trigger-label) {
        font-size: 0.75rem !important;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .trf-pf :deep(.select-trigger-chevron) {
        right: 5px !important;
        font-size: 0.7rem !important;
    }

    .trf-pf :deep(.select-trigger-wrap:has(.select-trigger-icons) .select-trigger-chevron) {
        right: 17px !important;
    }

    .trf-pf :deep(.select-trigger-icons) {
        right: 4px !important;
    }

    .trf-pf :deep(.select-clear-btn) {
        padding: 0 !important;
        font-size: 0.7rem !important;
    }

    .trf-pf :deep(.app-date-input__text) {
        min-height: 33px !important;
        padding: 4px 24px 4px 7px !important;
        font-size: 0.75rem !important;
        border-radius: 6px !important;
    }

    .trf-pf :deep(.app-date-input__picker-btn) {
        right: 4px !important;
        width: 18px !important;
        height: 18px !important;
        font-size: 0.7rem !important;
    }

    .trf-pf__meta {
        font-size: 0.64rem;
        gap: 2px 4px;
        margin-top: 1px;
    }

    .trf-pf__meta-label,
    .trf-pf__meta-val {
        font-size: 0.64rem;
    }

    .trf-pf__err {
        font-size: 0.64rem;
        line-height: 1.2;
    }

    .trf-notes-row {
        margin-top: 10px;
        padding-top: 10px;
    }
}

@media (max-width: 575.98px) {
    .trf-section__head {
        align-items: flex-start;
        flex-wrap: wrap;
        padding: 9px 10px;
    }

    .trf-section__head-right {
        justify-content: flex-start;
        width: 100%;
        padding-left: 32px;
    }

    .trf-section__body {
        padding: 9px 8px 12px;
    }
}

@media (max-width: 380px) {
    .trf-field-grid--primary,
    .trf-field-grid--service {
        gap: 8px 5px !important;
    }

    .trf-section__body {
        padding: 8px 5px 10px;
    }

    .trf-pf__lbl {
        font-size: 0.66rem;
        gap: 3px;
    }

    .trf-pf__lbl-icon {
        width: 15px;
        height: 15px;
        font-size: 0.58rem;
    }

    .trf-input,
    .trf-pf :deep(.select-trigger),
    .trf-pf :deep(.app-date-input__text) {
        font-size: 0.71rem !important;
        padding-left: 5px !important;
    }

    .trf-input--prefixed {
        padding-left: 17px !important;
    }

    .trf-input-prefix__addon {
        left: 5px;
        font-size: 0.7rem;
    }
}
</style>
