<template>
    <div class="maintenance-form trip-form">
        <div v-if="displayFormError" class="alert alert-danger">
            <strong>{{ displayFormError }}</strong>
        </div>

        <div class="card mb-4 trip-section-card">
            <div class="card-header bg-gradient-primary trip-section-card__header">
                <div class="trip-section-card__info">
                    <h3 class="card-title mb-0 trip-section-card__title">
                        <i class="bi bi-wrench-adjustable me-2" aria-hidden="true"></i>
                        {{ headerTitle }}
                    </h3>
                    
                </div>
                <div v-if="hasHeaderActions" class="trip-header-actions">
                    <div v-if="hasNavActions" class="trip-header-actions__group">
                        <slot name="nav-actions" />
                    </div>
                    <div v-if="hasSaveActions" class="trip-header-actions__group">
                        <slot name="save-actions" />
                    </div>
                </div>
            </div>

            <div class="card-body trip-form-fields maintenance-form-fields">
                <div class="maintenance-form-section">
                    <div class="maintenance-form-section__title">
                        <i class="bi bi-info-circle me-1"></i>Maintenance Information
                    </div>
                    <div class="maintenance-form-row maintenance-form-row--four">
                        <div>
                            <label :for="`${formId}_vehicle`" class="form-label">Truck *</label>
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
                        </div>
                        <div>
                            <label :for="`${formId}_service_date`" class="form-label">Service Date *</label>
                            <AppDateInput
                                :input-id="`${formId}_service_date`"
                                v-model="form.service_date"
                                :invalid="!!displayFieldErrors.service_date"
                                :max="today"
                                :readonly="readonly"
                                required
                                @update:model-value="$emit('clear-field-error', 'service_date')"
                            />
                            <div v-if="displayFieldErrors.service_date" class="invalid-feedback d-block">
                                {{ displayFieldErrors.service_date[0] }}
                            </div>
                        </div>
                        <div>
                            <label :for="`${formId}_odometer_km`" class="form-label">
                                Meter Reading (KM) *
                                
                            </label>
                            <input
                                :id="`${formId}_odometer_km`"
                                v-model="form.odometer_km"
                                v-no-scroll-change
                                type="number"
                                min="0"
                                step="0.01"
                                class="form-control"
                                :class="{ 'is-invalid': displayFieldErrors.odometer_km }"
                                placeholder="Enter meter reading at service"
                                :readonly="readonly"
                                required
                                @input="$emit('clear-field-error', 'odometer_km')"
                            />
                            <div v-if="truckRunningMeterDisplay" class="form-text">
                                Truck Running: {{ truckRunningMeterDisplay }} km
                            </div>
                            <div v-if="displayFieldErrors.odometer_km" class="invalid-feedback d-block">
                                {{ displayFieldErrors.odometer_km[0] }}
                            </div>
                        </div>
                        <div>
                            <label :for="`${formId}_cost_amount`" class="form-label">Cost Amount (₹) *</label>
                            <input
                                :id="`${formId}_cost_amount`"
                                v-model="form.cost_amount"
                                v-no-scroll-change
                                type="number"
                                min="0.01"
                                step="0.01"
                                class="form-control"
                                :class="{ 'is-invalid': displayFieldErrors.cost_amount }"
                                :readonly="readonly"
                                required
                                @input="$emit('clear-field-error', 'cost_amount')"
                            />
                            <div v-if="displayFieldErrors.cost_amount" class="invalid-feedback d-block">
                                {{ displayFieldErrors.cost_amount[0] }}
                            </div>
                        </div>
                    </div>
                </div>

                <div class="maintenance-form-section">
                    <div class="maintenance-form-section__title">
                        <i class="bi bi-gear me-1"></i>Service Information
                    </div>
                    <div class="maintenance-form-row maintenance-form-row--service">
                        <div>
                            <label :for="`${formId}_service_type`" class="form-label">Service Type *</label>
                            <MaintenanceServiceTypeSelect
                                :input-id="`${formId}_service_type`"
                                v-model="form.maintenance_service_type_id"
                                :service-types="serviceTypes"
                                :lookup-service-type="lookupServiceType"
                                placeholder="Select type"
                                :disabled="readonly"
                                :invalid="Boolean(displayFieldErrors.maintenance_service_type_id)"
                                :error-message="displayFieldErrors.maintenance_service_type_id?.[0] ?? ''"
                                @update:model-value="$emit('clear-field-error', 'maintenance_service_type_id')"
                            />
                        </div>
                        <div>
                            <label :for="`${formId}_service_provider`" class="form-label">Service Provider *</label>
                            <input
                                :id="`${formId}_service_provider`"
                                v-model="form.service_provider"
                                type="text"
                                class="form-control"
                                :class="{ 'is-invalid': displayFieldErrors.service_provider }"
                                placeholder="Garage / workshop name"
                                :readonly="readonly"
                                required
                                @input="$emit('clear-field-error', 'service_provider')"
                            />
                            <div v-if="displayFieldErrors.service_provider" class="invalid-feedback d-block">
                                {{ displayFieldErrors.service_provider[0] }}
                            </div>
                        </div>
                        <div>
                            <label :for="`${formId}_next_service_due_km`" class="form-label">
                                Next Due KM <span class="text-muted fw-normal">(optional)</span>
                            </label>
                            <input
                                :id="`${formId}_next_service_due_km`"
                                v-model="form.next_service_due_km"
                                v-no-scroll-change
                                type="number"
                                min="0"
                                step="0.01"
                                class="form-control"
                                :class="{ 'is-invalid': displayFieldErrors.next_service_due_km }"
                                :readonly="readonly"
                                @input="$emit('clear-field-error', 'next_service_due_km')"
                            />
                            <div v-if="displayFieldErrors.next_service_due_km" class="invalid-feedback d-block">
                                {{ displayFieldErrors.next_service_due_km[0] }}
                            </div>
                        </div>
                        <div>
                            <label :for="`${formId}_next_service_due_date`" class="form-label">
                                Next Due Date <span class="text-muted fw-normal">(optional)</span>
                            </label>
                            <AppDateInput
                                :input-id="`${formId}_next_service_due_date`"
                                v-model="form.next_service_due_date"
                                :invalid="!!displayFieldErrors.next_service_due_date"
                                :min="minNextDueDate || undefined"
                                :disabled="!form.service_date || readonly"
                                @update:model-value="$emit('clear-field-error', 'next_service_due_date')"
                            />
                            <div v-if="displayFieldErrors.next_service_due_date" class="invalid-feedback d-block">
                                {{ displayFieldErrors.next_service_due_date[0] }}
                            </div>
                        </div>
                    </div>
                </div>

                <div class="maintenance-form-section maintenance-form-section--last">
                    <div class="maintenance-form-section__title">
                        <i class="bi bi-chat-left-text me-1"></i>Notes
                    </div>
                    <div class="maintenance-form-row maintenance-form-row--full">
                        <div>
                            <label :for="`${formId}_remarks`" class="form-label">
                                Remarks <span class="text-muted fw-normal">(optional)</span>
                            </label>
                            <textarea
                                :id="`${formId}_remarks`"
                                v-model="form.remarks"
                                class="form-control"
                                :class="{ 'is-invalid': displayFieldErrors.remarks }"
                                rows="2"
                                :readonly="readonly"
                                @input="$emit('clear-field-error', 'remarks')"
                            ></textarea>
                            <div v-if="displayFieldErrors.remarks" class="invalid-feedback d-block">
                                {{ displayFieldErrors.remarks[0] }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, useSlots } from 'vue';
import AppDateInput from '../ui/AppDateInput.vue';
import MaintenanceServiceTypeSelect from '../ui/MaintenanceServiceTypeSelect.vue';
import VehicleSelect from '../ui/VehicleSelect.vue';
import { toDateInputValue } from '../../utils/datetime';
import { resolveVehicleOdometer } from '../../utils/tyreStint';

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

const slots = useSlots();

const today = computed(() => toDateInputValue());
const displayFieldErrors = computed(() => props.fieldErrors ?? {});
const displayFormError = computed(() => props.formError ?? '');
const headerTitle = computed(() => (props.mode === 'edit' ? 'Edit Maintenance Record' : 'Add Maintenance Record'));
const truckRunningMeterDisplay = computed(() => {
    if (!props.form.vehicle_id) {
        return '';
    }

    const odometer = resolveVehicleOdometer(props.vehicles, props.form.vehicle_id);

    if (odometer === null || odometer === undefined) {
        return '';
    }

    return Number(odometer).toLocaleString('en-IN');
});

const hasNavActions = computed(() => Boolean(slots['nav-actions']));
const hasSaveActions = computed(() => Boolean(slots['save-actions']));
const hasHeaderActions = computed(() => hasNavActions.value || hasSaveActions.value);
</script>

<style scoped>
.maintenance-form-fields {
    padding-top: 0.75rem;
    padding-bottom: 0.75rem;
}

.maintenance-form-section {
    padding-top: 0.85rem;
    padding-bottom: 0.85rem;
    border-top: 1px solid #e9ecef;
}

.maintenance-form-section:first-child {
    border-top: 0;
    padding-top: 0;
}

.maintenance-form-section--last {
    padding-bottom: 0;
}

.maintenance-form-section__title {
    font-size: 0.8125rem;
    font-weight: 700;
    color: #495057;
    margin-bottom: 0.75rem;
}

.maintenance-form-row {
    display: grid;
    gap: 12px 16px;
    align-items: start;
}

.maintenance-form-row--four {
    grid-template-columns: minmax(0, 1.1fr) minmax(0, 0.85fr) minmax(0, 0.85fr) minmax(0, 0.75fr);
}

.maintenance-form-row--service {
    grid-template-columns: minmax(0, 1fr) minmax(0, 1.35fr) minmax(0, 0.8fr) minmax(0, 0.8fr);
}

.maintenance-form-row--full {
    grid-template-columns: minmax(0, 1fr);
}

.maintenance-form-row > * {
    min-width: 0;
}

.maintenance-form-row .form-control,
.maintenance-form-row :deep(.form-control),
.maintenance-form-row :deep(.searchable-select),
.maintenance-form-row :deep(.app-date-input) {
    width: 100%;
    max-width: 100%;
}

@media (max-width: 1200px) {
    .maintenance-form-row--four,
    .maintenance-form-row--service {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media (max-width: 768px) {
    .maintenance-form-row--four,
    .maintenance-form-row--service {
        grid-template-columns: minmax(0, 1fr);
    }
}
</style>
