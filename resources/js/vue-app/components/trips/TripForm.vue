<template>
    <div class="trip-form">
        <div v-if="formError" class="alert alert-danger">
            <strong>{{ formError }}</strong>
            <ul v-if="validationMessages.length > 0" class="mb-0 mt-2 small">
                <li v-for="(message, idx) in validationMessages" :key="idx">{{ message }}</li>
            </ul>
        </div>
        <div v-if="driverWarning" class="alert alert-warning">{{ driverWarning }}</div>

        <div class="card mb-4 trip-section-card trip-details-card">
            <div class="card-header bg-gradient-primary trip-section-card__header">
                <div class="trip-section-card__info">
                    <h3 class="card-title mb-0 trip-section-card__title">
                        <i class="bi bi-signpost-split me-2" aria-hidden="true"></i>{{ tripDetailsHeaderTitle }}
                    </h3>
                    <p
                        v-if="headerMetaText || mode === 'create' || mode === 'edit'"
                        class="trip-section-card__meta mb-0 mt-1 d-flex flex-wrap align-items-center gap-2"
                    >
                        <span v-if="headerMetaText">{{ headerMetaText }}</span>
                        <span
                            class="badge"
                            :class="mode === 'create' ? 'bg-light text-dark' : statusBadgeOnGreen"
                        >
                            {{ mode === 'create' ? 'Created' : currentStatusBadge.label }}
                        </span>
                    </p>
                </div>
                <div
                    v-if="showHeaderActions"
                    class="trip-header-actions"
                >
                    <div
                        v-if="hasNavActions"
                        class="trip-header-actions__group"
                    >
                        <slot name="trip-nav-actions" />
                    </div>
                    <div
                        v-if="mode === 'edit' && !statusActionsInFooter && !isFormLocked && !statusLocked && allowedNextStatuses.length > 0"
                        class="trip-header-actions__group"
                    >
                        <button
                            v-for="next in allowedNextStatuses"
                            :key="next.value"
                            type="button"
                            class="btn btn-sm trip-header-btn"
                            :class="statusActionButtonClass(next.value)"
                            @click="onStatusChange(next.value)"
                        >
                            <i :class="[statusActionIcon(next.value), 'me-1']" aria-hidden="true"></i>
                            {{ next.label }}
                        </button>
                    </div>
                    <div
                        v-if="hasSaveActions"
                        class="trip-header-actions__group"
                    >
                        <slot name="trip-save-actions" />
                    </div>
                </div>
            </div>

            <div class="card-body trip-form-fields">
                <div class="trip-details-rows mb-3">
                    <div
                        class="trip-details-row"
                        :class="showActualArrivalField ? 'trip-details-row--five' : ''"
                    >
                        <div>
                            <label class="form-label trip-number-label">
                                <span>Trip Number</span>
                                <span class="badge" :class="mode === 'create' ? 'bg-info text-dark' : currentStatusBadge.badge">
                                    {{ mode === 'create' ? 'Created' : currentStatusBadge.label }}
                                </span>
                            </label>
                            <input :value="tripNumberDisplay" type="text" class="form-control bg-light" readonly />
                        </div>
                        <div>
                            <label class="form-label">Trip Type</label>
                            <input :value="tripTypeDisplay" type="text" class="form-control bg-light" readonly />
                        </div>
                        <div>
                            <label class="form-label">Start Date *</label>
                            <AppDateInput
                                v-model="form.start_at"
                                :invalid="!!fieldErrors.start_at"
                                :readonly="isFormLocked"
                            />
                            <div v-if="fieldErrors.start_at" class="invalid-feedback d-block">
                                {{ fieldErrors.start_at[0] }}
                            </div>
                        </div>
                        <div>
                            <label class="form-label d-flex align-items-center gap-2 flex-wrap mb-1">
                                <span>End Date</span>
                                <span
                                    v-if="arrivalTiming === 'on-time'"
                                    class="arrival-timing arrival-timing--on-time"
                                    title="Arrived on planned end date"
                                >
                                    <i class="bi bi-check-circle-fill" aria-hidden="true"></i>
                                    <span>On Time</span>
                                </span>
                                <span
                                    v-else-if="arrivalTiming === 'early'"
                                    class="arrival-timing arrival-timing--early"
                                    title="Arrived before planned end date"
                                >
                                    <i class="bi bi-arrow-down-circle-fill" aria-hidden="true"></i>
                                    <span>Early</span>
                                </span>
                                <span
                                    v-else-if="arrivalTiming === 'late'"
                                    class="arrival-timing arrival-timing--late"
                                    title="Arrived after planned end date"
                                >
                                    <i class="bi bi-exclamation-circle-fill" aria-hidden="true"></i>
                                    <span>Late</span>
                                </span>
                            </label>
                            <AppDateInput
                                v-model="form.end_at"
                                :invalid="!!fieldErrors.end_at"
                                :min="endDateMinOnForm || undefined"
                                :readonly="isFormLocked"
                            />
                            <div v-if="fieldErrors.end_at" class="invalid-feedback d-block">
                                {{ fieldErrors.end_at[0] }}
                            </div>
                        </div>
                        <div v-if="showActualArrivalField">
                            <label class="form-label">Actual Arrival</label>
                            <AppDateInput
                                v-model="form.actual_arrival_at"
                                :invalid="!!fieldErrors.actual_arrival_at"
                                :min="tripStartDate || undefined"
                                :readonly="isFormLocked"
                                @input="$emit('clear-field-error', 'actual_arrival_at')"
                            />
                            <div v-if="fieldErrors.actual_arrival_at" class="invalid-feedback d-block">
                                {{ fieldErrorMessage('actual_arrival_at') }}
                            </div>
                        </div>
                    </div>

                    <div
                        class="trip-details-row"
                        :class="showSubstituteDriverColumn ? 'trip-details-row--four' : 'trip-details-row--three'"
                    >
                        <div>
                            <label class="form-label">Truck *</label>
                            <template v-if="mode === 'create'">
                                <AsyncVehicleTripPicker
                                    v-model="form.vehicle_id"
                                    :as-of-date="tripStartDate"
                                    :trip-end-date="tripEndDate"
                                    :driver-label="primaryDriverName"
                                    :invalid="Boolean(fieldErrors.vehicle_id)"
                                    :error-message="fieldErrorMessage('vehicle_id')"
                                    @vehicle-selected="$emit('vehicle-selected', $event)"
                                />
                            </template>
                            <template v-else>
                                <input
                                    :value="snapshotVehicleLabel"
                                    type="text"
                                    class="form-control bg-light"
                                    readonly
                                />
                            </template>
                        </div>
                        <div>
                            <div class="driver-field-header">
                                <label class="form-label mb-0">
                                    Primary Driver
                                    <span v-if="loadingDriver" class="text-muted small">(loading…)</span>
                                </label>
                                <div
                                    v-if="!readonly && hasPrimaryDriver"
                                    class="form-check form-switch substitute-toggle mb-0"
                                >
                                    <input
                                        id="use-substitute-driver"
                                        v-model="showSubstituteDriver"
                                        class="form-check-input"
                                        type="checkbox"
                                        role="switch"
                                    />
                                    <label class="form-check-label" for="use-substitute-driver">
                                        Substitute
                                    </label>
                                </div>
                            </div>
                            <input
                                :value="driverDisplayName"
                                type="text"
                                class="form-control bg-light"
                                :class="{ 'is-invalid': fieldErrors.driver_id && mode !== 'edit' }"
                                readonly
                            />
                            <div v-if="fieldErrors.driver_id && mode !== 'edit'" class="invalid-feedback d-block">
                                {{ fieldErrorMessage('driver_id') }}
                            </div>
                        </div>
                        <div v-if="showSubstituteDriverColumn" class="trip-details-row__substitute">
                            <label class="form-label">Substitute Driver</label>
                            <SearchablePicker
                                v-if="!isFormLocked"
                                v-model="form.substitute_driver_id"
                                :items="substituteDriverOptions"
                                attach-dropdown-to-body
                                placeholder="Select substitute driver"
                                search-placeholder="Search driver…"
                                empty-message="No driver found."
                                view-title="Driver details"
                                :invalid="Boolean(fieldErrors.substitute_driver_id)"
                                :error-message="fieldErrorMessage('substitute_driver_id')"
                                :can-quick-add="false"
                            />
                            <input
                                v-else
                                :value="substituteDriverDisplay"
                                type="text"
                                class="form-control bg-light"
                                readonly
                            />
                        </div>
                        <div>
                            <label class="form-label">Customer</label>
                            <SearchablePicker
                                v-if="!isFormLocked"
                                v-model="form.customer_id"
                                :items="customers"
                                attach-dropdown-to-body
                                placeholder="Select customer"
                                search-placeholder="Search customer…"
                                empty-message="No customer found."
                                view-title="Customer details"
                                quick-add-title="Quick add customer"
                                :invalid="Boolean(fieldErrors.customer_id)"
                                :error-message="fieldErrorMessage('customer_id')"
                                :can-quick-add="canQuickAddCustomer"
                                @quick-add="openQuickAddForTripCustomer"
                            />
                            <input
                                v-else
                                :value="partyLabel(customers, form.customer_id, snapshotCustomerName)"
                                type="text"
                                class="form-control form-input bg-light"
                                readonly
                            />
                        </div>
                    </div>

                    <div class="trip-details-row">
                        <div>
                            <label class="form-label">Truck Running Meter</label>
                            <input
                                :value="truckRunningMeterDisplay"
                                type="text"
                                class="form-control bg-light"
                                readonly
                            />
                        </div>
                        <div>
                            <label class="form-label">Start Meter Reading *</label>
                            <input
                                v-if="!isFormLocked"
                                v-model.number="form.start_meter_reading_km"
                                v-no-scroll-change
                                type="number"
                                min="0"
                                step="0.01"
                                class="form-control trip-meter-input"
                                :class="{ 'is-invalid': fieldErrors.start_meter_reading_km }"
                            />
                            <input
                                v-else
                                :value="startMeterDisplay"
                                type="text"
                                class="form-control bg-light"
                                readonly
                            />
                            <div v-if="fieldErrors.start_meter_reading_km" class="invalid-feedback d-block">
                                {{ fieldErrorMessage('start_meter_reading_km') }}
                            </div>
                        </div>
                        <div>
                            <label class="form-label">End Meter Reading</label>
                            <input
                                v-if="!isFormLocked"
                                v-model.number="form.end_meter_reading_km"
                                v-no-scroll-change
                                type="number"
                                min="0"
                                step="0.01"
                                class="form-control trip-meter-input"
                                :class="{ 'is-invalid': fieldErrors.end_meter_reading_km }"
                            />
                            <input
                                v-else
                                :value="endMeterDisplay"
                                type="text"
                                class="form-control bg-light"
                                readonly
                            />
                            <div v-if="fieldErrors.end_meter_reading_km" class="invalid-feedback d-block">
                                {{ fieldErrorMessage('end_meter_reading_km') }}
                            </div>
                        </div>
                        <div>
                            <label class="form-label">Total KM</label>
                            <input
                                :value="totalKmDisplay"
                                type="text"
                                class="form-control bg-light"
                                readonly
                            />
                        </div>
                    </div>

                    <div v-if="startMeterWarning" class="trip-details-row__full-width">
                        <div class="alert alert-warning py-2 mb-0 small">
                            <strong>Warning:</strong> The entered Start Meter Reading is lower than the current
                            Truck Running Meter Reading. Please verify the meter reading before continuing.
                        </div>
                    </div>

                    <div class="trip-details-row trip-details-row--five-compact">
                        <div class="trip-details-row__field--compact">
                            <label class="form-label">Expected Mileage (KM)</label>
                            <input
                                v-if="!isFormLocked"
                                v-model.number="form.planned_mileage_km_per_liter"
                                v-no-scroll-change
                                type="number"
                                min="0"
                                step="0.01"
                                class="form-control trip-meter-input"
                                @input="onPlannedMileageInput"
                            />
                            <input
                                v-else
                                :value="plannedMileageDisplay"
                                type="text"
                                class="form-control bg-light"
                                readonly
                            />
                            <div class="form-text trip-details-row__compact-hint">From truck profile by default; editable per trip.</div>
                        </div>
                        <div class="trip-details-row__field--compact">
                            <label class="form-label">Max Load (Ton)</label>
                            <input
                                v-if="!isFormLocked"
                                v-model.number="form.max_load_ton"
                                v-no-scroll-change
                                type="number"
                                min="0"
                                step="0.001"
                                class="form-control trip-meter-input"
                                :class="{ 'is-invalid': fieldErrors.max_load_ton }"
                            />
                            <input
                                v-else
                                :value="form.max_load_ton !== '' && form.max_load_ton != null ? formatNum(form.max_load_ton) : '—'"
                                type="text"
                                class="form-control bg-light"
                                readonly
                            />
                            <div v-if="fieldErrors.max_load_ton" class="invalid-feedback d-block">
                                {{ fieldErrorMessage('max_load_ton') }}
                            </div>
                        </div>
                        <div>
                            <label class="form-label">Expected Diesel (Ltr)</label>
                            <input
                                v-if="!isFormLocked"
                                v-model.number="form.planned_diesel_liters"
                                v-no-scroll-change
                                type="number"
                                min="0"
                                step="0.01"
                                class="form-control trip-meter-input"
                                :class="{ 'is-invalid': fieldErrors.planned_diesel_liters }"
                                @input="onPlannedDieselInput"
                            />
                            <input
                                v-else
                                :value="form.planned_diesel_liters ? formatNum(form.planned_diesel_liters) : '—'"
                                type="text"
                                class="form-control bg-light"
                                readonly
                            />
                            <div v-if="fieldErrors.planned_diesel_liters" class="invalid-feedback d-block">
                                {{ fieldErrorMessage('planned_diesel_liters') }}
                            </div>
                        </div>
                        <div>
                            <label class="form-label">Diesel Rate (₹/Ltr)</label>
                            <input
                                v-if="!isFormLocked"
                                v-model.number="form.diesel_rate_per_liter"
                                v-no-scroll-change
                                type="number"
                                min="0"
                                step="0.01"
                                class="form-control trip-meter-input"
                                :class="{ 'is-invalid': fieldErrors.diesel_rate_per_liter }"
                            />
                            <input
                                v-else
                                :value="form.diesel_rate_per_liter ? formatMoney(form.diesel_rate_per_liter) : '—'"
                                type="text"
                                class="form-control bg-light"
                                readonly
                            />
                            <div v-if="fieldErrors.diesel_rate_per_liter" class="invalid-feedback d-block">
                                {{ fieldErrorMessage('diesel_rate_per_liter') }}
                            </div>
                        </div>
                        <div>
                            <label class="form-label">Reference by (optional)</label>
                            <SearchablePicker
                                v-if="!isFormLocked"
                                v-model="form.broker_id"
                                :items="brokers"
                                attach-dropdown-to-body
                                placeholder="Select reference"
                                search-placeholder="Search reference…"
                                empty-message="No reference found."
                                view-title="Reference details"
                                quick-add-title="Quick add reference"
                                :invalid="Boolean(fieldErrors.broker_id)"
                                :error-message="fieldErrorMessage('broker_id')"
                                :can-quick-add="canQuickAddBroker"
                                @quick-add="showBrokerQuickAdd = true"
                            />
                            <input
                                v-else
                                :value="partyLabel(brokers, form.broker_id, snapshotBrokerName)"
                                type="text"
                                class="form-control form-input bg-light"
                                readonly
                            />
                        </div>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Notes</label>
                    <textarea
                        v-if="!isFormLocked"
                        v-model="form.notes"
                        class="form-control"
                        rows="2"
                        placeholder="Optional trip notes…"
                        :class="{ 'is-invalid': fieldErrors.notes }"
                    ></textarea>
                    <div
                        v-else
                        class="form-control bg-light"
                        style="min-height: 2.5rem; white-space: pre-wrap"
                    >
                        {{ form.notes?.trim() || '—' }}
                    </div>
                    <div v-if="fieldErrors.notes" class="invalid-feedback d-block">
                        {{ fieldErrorMessage('notes') }}
                    </div>
                </div>
                <div v-if="form.status === 'CANCELLED'" class="mb-3">
                    <div class="alert alert-danger mb-0 py-2">
                        <div class="small">
                            <strong>Cancelled:</strong> {{ formatDate(form.cancelled_at) }}
                        </div>
                        <div v-if="form.cancellation_reason" class="small mt-1">
                            <strong>Reason:</strong> {{ form.cancellation_reason }}
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card mb-4 trip-section-card trip-route-card">
            <div class="card-header bg-gradient-primary trip-section-card__header trip-route-card__header">
                <h3 class="card-title mb-0 trip-section-card__title">
                    <i class="bi me-2" :class="isRoundTrip ? 'bi-arrow-repeat' : 'bi-arrow-right'" aria-hidden="true"></i>{{ routeSectionTitle }}
                </h3>
                <div v-if="!isFormLocked" class="trip-header-actions">
                    <div class="trip-header-actions__group">
                        <AppIndexHeaderButton
                            icon="plus"
                            aria-label="Add route stop"
                            @click="$emit('add-stop')"
                        >
                            Add Stop
                        </AppIndexHeaderButton>
                        <AppIndexHeaderButton
                            v-if="isRoundTrip"
                            icon="return"
                            aria-label="Return route to start station"
                            :disabled="!canReturnToStart"
                            @click="$emit('return-to-start')"
                        >
                            Return to Start
                        </AppIndexHeaderButton>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label class="form-label">Start Station (Home Base) *</label>
                        <SearchablePicker
                            v-if="!isFormLocked"
                            v-model="form.start_station_id"
                            :items="stations"
                            attach-dropdown-to-body
                            placeholder="Select start station"
                            :invalid="Boolean(fieldErrors.start_station_id)"
                            :error-message="fieldErrorMessage('start_station_id')"
                            :can-quick-add="canQuickAddStation"
                            @update:model-value="handleStartStationPickerChange"
                            @quick-add="showStationQuickAdd = true"
                        />
                        <input
                            v-else
                            :value="stationLabel(form.start_station_id)"
                            type="text"
                            class="form-control bg-light"
                            readonly
                        />
                    </div>
                    <div class="col-md-6">
                        <div class="alert alert-light border mb-0 h-100">
                            <strong>{{ isRoundTrip ? 'Round trip flow:' : 'One way flow:' }}</strong>
                            <template v-if="isRoundTrip">
                                Start → Stop B → Stop C → back to Start.
                            </template>
                            <template v-else>
                                Start → one or more stops (return to start not required).
                            </template>
                            <div v-if="routePreview" class="small mt-1 text-muted">{{ routePreview }}</div>
                        </div>
                    </div>
                </div>

                <div v-if="routeLegsError" class="alert alert-danger py-2">{{ routeLegsError }}</div>

                <div v-if="form.route_legs.length === 0" class="text-muted">
                    Select start station, then click <strong>Add Stop</strong> to build the route.
                </div>

                <div class="table-responsive table-wrap" v-else>
                    <table class="table table-bordered align-middle mb-0 trip-route-table">
                        <thead class="table-light">
                            <tr>
                                <th class="trip-route-index-col">#</th>
                                <th class="trip-route-from-col">From</th>
                                <th class="trip-route-to-col">To Station *</th>
                                <th class="trip-route-customer-col">Customer</th>
                                <th class="trip-route-weight-col">Weight (Ton)</th>
                                <th class="trip-route-mileage-col">Expected Mileage</th>
                                <th class="trip-route-rate-col">Freight Rate (₹/Ton)</th>
                                <th class="trip-route-freight-col">Freight ₹</th>
                                <th v-if="!isFormLocked" class="trip-route-delete-col"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(leg, index) in form.route_legs" :key="index">
                                <td>{{ index + 1 }}</td>
                                <td>{{ stationLabel(leg.from_station_id) }}</td>
                                <td>
                                    <SearchablePicker
                                        v-if="!isFormLocked"
                                        v-model="leg.to_station_id"
                                        :items="stations"
                                        placeholder="Select station"
                                        attach-dropdown-to-body
                                        compact-actions
                                        :invalid="Boolean(legFieldError(index, 'to_station_id'))"
                                        :error-message="legFieldError(index, 'to_station_id')"
                                        @update:model-value="$emit('stop-change', index)"
                                        @quick-add="openQuickAddForLeg(index)"
                                    />
                                    <span v-else>{{ stationLabel(leg.to_station_id) }}</span>
                                </td>
                                <td>
                                    <div v-if="!isFormLocked" class="leg-customer-cell">
                                        <SearchablePicker
                                            v-model="leg.customer_id"
                                            :items="customers"
                                            :placeholder="leg.use_same_customer ? (form.customer_id ? 'Uses trip customer' : 'Select trip customer above') : 'Select customer'"
                                            attach-dropdown-to-body
                                            compact-actions
                                            view-title="Customer details"
                                            quick-add-title="Quick add customer"
                                            :disabled="leg.use_same_customer"
                                            :invalid="Boolean(legFieldError(index, 'customer_id'))"
                                            :error-message="legFieldError(index, 'customer_id')"
                                            @quick-add="openQuickAddForLegCustomer(index)"
                                        >
                                            <template #toolbar>
                                                <div class="form-check">
                                                    <input
                                                        :id="`same-customer-${index}`"
                                                        v-model="leg.use_same_customer"
                                                        class="form-check-input"
                                                        type="checkbox"
                                                        @change="onLegSameCustomerChange(index)"
                                                    />
                                                    <label
                                                        class="form-check-label small text-muted"
                                                        :for="`same-customer-${index}`"
                                                    >
                                                        Same customer
                                                    </label>
                                                </div>
                                            </template>
                                        </SearchablePicker>
                                    </div>
                                    <span v-else>{{ partyLabel(customers, leg.customer_id) }}</span>
                                </td>
                                <td class="trip-route-weight-col">
                                    <div class="trip-route-weight-field">
                                        <span
                                            class="trip-route-load-tag"
                                            :class="`trip-route-load-tag--${legLoadMeta(leg.weight_ton).category}`"
                                        >
                                            {{ legLoadMeta(leg.weight_ton).label }}
                                        </span>
                                        <input
                                            v-if="!isFormLocked"
                                            v-model.number="leg.weight_ton"
                                            v-no-scroll-change
                                            type="number"
                                            min="0"
                                            step="0.001"
                                            class="form-control form-control-sm trip-route-weight-input"
                                            :class="{ 'is-invalid': legFieldError(index, 'weight_ton') }"
                                            placeholder="Ton"
                                            @input="onLegWeightInput(index)"
                                        />
                                        <span v-else class="trip-route-weight-readonly">
                                            {{ leg.weight_ton ? formatNum(leg.weight_ton) : '—' }}
                                        </span>
                                        <div v-if="legFieldError(index, 'weight_ton')" class="invalid-feedback d-block">
                                            {{ legFieldError(index, 'weight_ton') }}
                                        </div>
                                    </div>
                                </td>
                                <td class="trip-route-mileage-col">
                                    <div class="trip-route-num-field">
                                        <input
                                            v-if="!isFormLocked"
                                            v-model.number="leg.expected_mileage_km_per_liter"
                                            v-no-scroll-change
                                            type="number"
                                            min="0"
                                            step="0.01"
                                            class="form-control form-control-sm trip-route-num-input"
                                            :class="{ 'is-invalid': legFieldError(index, 'expected_mileage_km_per_liter') }"
                                            placeholder="KM/Lt"
                                        />
                                        <span v-else class="trip-route-num-readonly">
                                            {{
                                                leg.expected_mileage_km_per_liter
                                                    ? `${formatNum(leg.expected_mileage_km_per_liter)} KM/Lt`
                                                    : '—'
                                            }}
                                        </span>
                                        <div
                                            v-if="legFieldError(index, 'expected_mileage_km_per_liter')"
                                            class="invalid-feedback d-block"
                                        >
                                            {{ legFieldError(index, 'expected_mileage_km_per_liter') }}
                                        </div>
                                    </div>
                                </td>
                                <td class="trip-route-rate-col">
                                    <div class="trip-route-num-field">
                                        <input
                                            v-if="!isFormLocked"
                                            v-model.number="leg.freight_rate"
                                            v-no-scroll-change
                                            type="number"
                                            min="0"
                                            step="0.01"
                                            class="form-control form-control-sm trip-route-num-input"
                                            :class="{ 'is-invalid': legFieldError(index, 'freight_rate') }"
                                            :disabled="Number(leg.weight_ton ?? 0) <= 0"
                                            placeholder="₹/Ton"
                                            @input="onLegRateInput(index)"
                                        />
                                        <span v-else class="trip-route-num-readonly">
                                            {{ leg.freight_rate ? formatMoney(leg.freight_rate) : '—' }}
                                        </span>
                                        <div v-if="legFieldError(index, 'freight_rate')" class="invalid-feedback d-block">
                                            {{ legFieldError(index, 'freight_rate') }}
                                        </div>
                                    </div>
                                </td>
                                <td class="trip-route-freight-col">
                                    <div v-if="!isFormLocked" class="d-flex flex-column gap-1">
                                        <label class="form-check-label small text-muted d-flex align-items-center gap-1 mb-0">
                                            <input
                                                v-model="leg.freight_auto_calculate"
                                                class="form-check-input m-0"
                                                type="checkbox"
                                                :disabled="Number(leg.weight_ton ?? 0) <= 0"
                                                @change="onLegAutoCalculateChange(index)"
                                            />
                                            Auto Calculate
                                        </label>
                                        <input
                                            v-model.number="leg.freight_amount"
                                            v-no-scroll-change
                                            type="number"
                                            min="0"
                                            step="0.01"
                                            class="form-control form-control-sm"
                                            :disabled="Number(leg.weight_ton ?? 0) <= 0 || leg.freight_auto_calculate"
                                            @input="onLegFreightManualInput(index)"
                                        />
                                    </div>
                                    <span v-else>₹{{ formatMoney(leg.freight_amount) }}</span>
                                </td>
                                <td v-if="!isFormLocked" class="trip-route-delete-col">
                                    <button
                                        type="button"
                                        class="btn btn-sm btn-outline-danger"
                                        :disabled="isStopRemoveBlocked(index)"
                                        :title="stopRemoveTitle(index)"
                                        @click="handleRemoveRouteLeg(index)"
                                    >
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card-body p-0 border-top">
                <div class="km-summary-bar">
                    <div>
                        <div class="small text-muted">Full Load Stops</div>
                        <strong class="text-success">{{ summary.fullLoadStops }}</strong>
                    </div>
                    <div>
                        <div class="small text-muted">Half Load Stops</div>
                        <strong class="text-warning">{{ summary.halfLoadStops }}</strong>
                    </div>
                    <div>
                        <div class="small text-muted">Empty Stops</div>
                        <strong class="text-secondary">{{ summary.emptyStops }}</strong>
                    </div>
                    <div>
                        <div class="small text-muted">Avg Expected Mileage</div>
                        <strong>{{ expectedMileageDisplay }}</strong>
                    </div>
                    <div>
                        <div class="small text-muted">Total KM</div>
                        <strong>{{ formatNum(summary.totalKm) }}</strong>
                    </div>
                    <div>
                        <div class="small text-muted">Total Freight</div>
                        <strong>₹{{ formatMoney(summary.freightTotal) }}</strong>
                    </div>
                </div>
            </div>
        </div>

        <TripDieselPerformanceCard :performance="dieselPerformance" />

        <div v-if="showExpenses" class="card mb-4 trip-expenses-card">
            <div class="card-header bg-gradient-primary trip-expenses-card__header">
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
                    <div>
                        <h3 class="card-title mb-0">
                            <i class="bi bi-receipt me-2"></i>Trip Expenses
                        </h3>
                        
                    </div>
                    <div class="trip-expenses-card__live-total">
                        Live total: ₹{{ formatMoney(summary.expenseTotal) }}
                    </div>
                </div>
            </div>
            <div class="card-body trip-expenses-card__body">
                <TripExpensesPanel
                    :expenses="form.expenses"
                    :route-legs="form.route_legs"
                    :stations="stations"
                    :max-load-ton="form.max_load_ton"
                    :readonly="readonly"
                    :trip-start-date="form.start_at"
                    :trip-end-date="form.end_at"
                    :trip-start-meter-km="form.start_meter_reading_km"
                    :trip-end-meter-km="form.end_meter_reading_km"
                    :field-errors="fieldErrors"
                    @add-expense="$emit('add-expense', $event)"
                    @remove-expense="$emit('remove-expense', $event)"
                />
            </div>
        </div>

        <StationQuickAddModal
            :show="showStationQuickAdd"
            @close="showStationQuickAdd = false"
            @created="onStationCreated"
        />

        <BrokerQuickAddModal
            :show="showBrokerQuickAdd"
            :existing-brokers="brokers"
            @close="showBrokerQuickAdd = false"
            @created="onBrokerCreated"
        />

        <CustomerQuickAddModal
            :show="showCustomerQuickAdd"
            :existing-customers="customers"
            @close="showCustomerQuickAdd = false"
            @created="onCustomerCreated"
        />

        <TripStatusChangeModal
            :show="showStatusModal"
            :status="pendingStatus"
            :from-status="form.status"
            :trip-number="form.trip_number || tripNumberDisplay"
            :truck-number="snapshotVehicleLabel"
            :start-at="form.start_at"
            :end-at-value="form.end_at"
            :actual-arrival-at-value="form.actual_arrival_at"
            :cancelled-at-value="form.cancelled_at"
            :cancellation-reason-value="form.cancellation_reason"
            :start-meter-reading-km="form.start_meter_reading_km"
            :truck-running-meter-km="vehicleOdometerKm"
            :end-meter-reading-km-value="form.end_meter_reading_km"
            @cancel="onStatusModalCancel"
            @confirm="onStatusModalConfirm"
        />
    </div>
</template>

<script setup>
import { computed, ref, useSlots, watch } from 'vue';
import {
    calculateLegFreight,
    computeDieselPerformance,
    legLoadCategoryMeta,
    stopHasExpenses,
    summarizeTripForm,
    tripStatusMeta,
    tripTypeLabel,
    tripAllowedNextStatuses,
} from '../../api/trips';
import { useAppToast } from '../../composables/useAppToast';
import { calculatePlannedDiesel, calculateExpectedRunningKm } from '../../utils/tripFuelCalculator';
import { dateOnlyFromDatetimeLocal, formatDate } from '../../utils/datetime';
import { formatMeterKm } from '../../utils/formatMeter';
import AppIndexHeaderButton from '../ui/AppIndexHeaderButton.vue';
import SearchablePicker from '../ui/SearchablePicker.vue';
import AsyncVehicleTripPicker from './AsyncVehicleTripPicker.vue';
import TripExpensesPanel from './TripExpensesPanel.vue';
import BrokerQuickAddModal from './BrokerQuickAddModal.vue';
import CustomerQuickAddModal from './CustomerQuickAddModal.vue';
import StationQuickAddModal from './StationQuickAddModal.vue';
import TripStatusChangeModal from './TripStatusChangeModal.vue';
import TripDieselPerformanceCard from './TripDieselPerformanceCard.vue';

const props = defineProps({
    form: { type: Object, required: true },
    mode: { type: String, default: 'create' },
    vehicles: { type: Array, default: () => [] },
    drivers: { type: Array, default: () => [] },
    snapshotVehicleLabel: { type: String, default: '' },
    snapshotDriverName: { type: String, default: '' },
    snapshotSubstituteDriverName: { type: String, default: '' },
    stations: { type: Array, default: () => [] },
    brokers: { type: Array, default: () => [] },
    customers: { type: Array, default: () => [] },
    snapshotCustomerName: { type: String, default: '' },
    snapshotBrokerName: { type: String, default: '' },
    formError: { type: String, default: '' },
    fieldErrors: { type: Object, default: () => ({}) },
    tripNumberDisplay: { type: String, default: '' },
    driverWarning: { type: String, default: '' },
    loadingDriver: { type: Boolean, default: false },
    primaryDriverName: { type: String, default: '' },
    statusLocked: { type: Boolean, default: false },
    displayStatus: { type: String, default: '' },
    statusActionsInFooter: { type: Boolean, default: false },
    readonly: { type: Boolean, default: false },
    showExpenses: { type: Boolean, default: false },
    expenseTotalsBySequence: { type: Object, default: () => ({}) },
    canQuickAddStation: { type: Boolean, default: false },
    canQuickAddBroker: { type: Boolean, default: false },
    canQuickAddCustomer: { type: Boolean, default: false },
    vehicleOdometerKm: { type: [Number, String], default: '' },
    vehicleDefaultMileage: { type: [Number, String], default: '' },
    vehicleExpectedMileage: { type: [Number, String], default: '' },
    vehicleMaxLoad: { type: [Number, String], default: '' },
});

const vehicleDefaultMileageResolved = computed(
    () => props.vehicleDefaultMileage || props.vehicleExpectedMileage || '',
);

const emit = defineEmits([
    'add-stop',
    'return-to-start',
    'remove-route-leg',
    'add-expense',
    'remove-expense',
    'start-station-change',
    'stop-change',
    'station-created',
    'broker-created',
    'customer-created',
    'vehicle-selected',
    'status-updated',
    'clear-field-error',
]);

const slots = useSlots();
const toast = useAppToast();

const isFormLocked = computed(() => props.readonly);

const showStationQuickAdd = ref(false);
const showBrokerQuickAdd = ref(false);
const showCustomerQuickAdd = ref(false);
const quickAddLegIndex = ref(null);
const quickAddCustomerLegIndex = ref(null);
const showStatusModal = ref(false);
const pendingStatus = ref('');

const summary = computed(() => summarizeTripForm(props.form));

const dieselPerformance = computed(() =>
    computeDieselPerformance({
        plannedMileageKmPerLiter: props.form.planned_mileage_km_per_liter,
        plannedDieselLiters: props.form.planned_diesel_liters,
        dieselRatePerLiter: props.form.diesel_rate_per_liter,
        actualTotalKm: summary.value.totalKm,
    }),
);

const expectedMileageDisplay = computed(() => {
    if (summary.value.expectedMileage == null) {
        return '—';
    }

    return `${formatNum(summary.value.expectedMileage)} KM/Ltr`;
});

const plannedMileageDisplay = computed(() => {
    const value = props.form.planned_mileage_km_per_liter;

    if (value === '' || value == null) {
        return '—';
    }

    const mileage = Number(value);

    if (!Number.isFinite(mileage) || mileage <= 0) {
        return '—';
    }

    return `${formatNum(mileage)} KM/Ltr`;
});

const tripStartDate = computed(() => dateOnlyFromDatetimeLocal(props.form.start_at));
const tripEndDate = computed(() => dateOnlyFromDatetimeLocal(props.form.end_at));

const showActualArrivalField = computed(() => {
    if (props.mode !== 'edit') {
        return false;
    }

    const status = String(props.form.status ?? '').toUpperCase();

    return status === 'COMPLETED' || status === 'IN_PROGRESS' || Boolean(props.form.actual_arrival_at);
});

const endDateMinOnForm = computed(() => {
    const arrival = dateOnlyFromDatetimeLocal(props.form.actual_arrival_at);

    return arrival || tripStartDate.value || '';
});

const arrivalTiming = computed(() => {
    const endDate = tripEndDate.value;
    const arrivalDate = dateOnlyFromDatetimeLocal(props.form.actual_arrival_at);

    if (!endDate || !arrivalDate) {
        return null;
    }

    if (endDate === arrivalDate) {
        return 'on-time';
    }

    if (endDate > arrivalDate) {
        return 'early';
    }

    return 'late';
});

const effectiveTripStatus = computed(() => {
    if (props.displayStatus) {
        return props.displayStatus;
    }

    return props.form.status;
});

const currentStatusBadge = computed(() => tripStatusMeta(effectiveTripStatus.value));

const tripDetailsHeaderTitle = computed(() => {
    if (props.mode === 'create') {
        return 'Create Trip';
    }

    if (props.readonly || props.statusLocked) {
        return 'View Trip';
    }

    return 'Edit Trip';
});

function isStopRemoveBlocked(index) {
    const sequence = index + 1;

    return stopHasExpenses(props.expenseTotalsBySequence, sequence);
}

function stopRemoveTitle(index) {
    if (!isStopRemoveBlocked(index)) {
        return 'Remove stop';
    }

    const sequence = index + 1;
    const total = props.expenseTotalsBySequence[sequence] ?? 0;

    return `Stop ${sequence} has ₹${formatMoney(total)} expenses. Clear them on Trip Expenses first.`;
}

function handleRemoveRouteLeg(index) {
    if (isStopRemoveBlocked(index)) {
        const sequence = index + 1;
        const total = props.expenseTotalsBySequence[sequence] ?? 0;
        toast.error(
            `Stop ${sequence} has ₹${formatMoney(total)} expenses. Remove them from Trip Expenses first.`,
        );

        return;
    }

    emit('remove-route-leg', index);
}

const headerMetaText = computed(() => {
    if (props.mode === 'edit') {
        const parts = [];

        if (props.tripNumberDisplay) {
            parts.push(props.tripNumberDisplay);
        }

        if (props.snapshotVehicleLabel) {
            parts.push(props.snapshotVehicleLabel);
        }

        return parts.join(' · ');
    }

    if (props.mode === 'create' && props.tripNumberDisplay) {
        return props.tripNumberDisplay;
    }

    return '';
});

const driverDisplayName = computed(() => {
    if (props.mode === 'edit') {
        return props.snapshotDriverName || '—';
    }

    return props.primaryDriverName || (props.loadingDriver ? 'Loading…' : '—');
});

const primaryDriverId = computed(() => props.form.driver_id ?? '');

const hasPrimaryDriver = computed(() => Boolean(String(primaryDriverId.value).trim()));

const showSubstituteDriver = ref(false);

const showSubstituteDriverColumn = computed(
    () => (isFormLocked.value && Boolean(props.form.substitute_driver_id))
        || (!isFormLocked.value && hasPrimaryDriver.value && showSubstituteDriver.value),
);

const substituteDriverOptions = computed(() =>
    props.drivers.filter((driver) => String(driver.id) !== String(primaryDriverId.value)),
);

const substituteDriverDisplay = computed(() => {
    if (!props.form.substitute_driver_id) {
        return '—';
    }

    const match = props.drivers.find(
        (driver) => String(driver.id) === String(props.form.substitute_driver_id),
    );

    return match?.name ?? props.snapshotSubstituteDriverName ?? '—';
});

watch(
    () => props.form.substitute_driver_id,
    (substituteDriverId) => {
        if (substituteDriverId) {
            showSubstituteDriver.value = true;
        }
    },
    { immediate: true },
);

watch(showSubstituteDriver, (enabled) => {
    if (!enabled && !isFormLocked.value) {
        props.form.substitute_driver_id = '';
        emit('clear-field-error', 'substitute_driver_id');
    }
});

watch(
    () => props.form.driver_id,
    (driverId) => {
        if (!driverId) {
            showSubstituteDriver.value = false;

            return;
        }

        if (
            props.form.substitute_driver_id
            && String(props.form.substitute_driver_id) === String(driverId)
        ) {
            props.form.substitute_driver_id = '';
        }
    },
);

const allowedNextStatuses = computed(() => tripAllowedNextStatuses(effectiveTripStatus.value));

const hasNavActions = computed(() => Boolean(slots['trip-nav-actions']));
const hasSaveActions = computed(() => Boolean(slots['trip-save-actions']));

const showHeaderActions = computed(
    () => hasNavActions.value
        || hasSaveActions.value
        || (
            props.mode === 'edit'
            && !props.statusActionsInFooter
            && !isFormLocked.value
            && !props.statusLocked
            && allowedNextStatuses.value.length > 0
        ),
);

const statusBadgeOnGreen = computed(() => {
    const normalized = String(effectiveTripStatus.value ?? '').toUpperCase();

    if (normalized === 'IN_PROGRESS') {
        return 'bg-warning text-dark';
    }

    if (normalized === 'COMPLETED') {
        return 'bg-light text-dark';
    }

    if (normalized === 'CANCELLED') {
        return 'bg-danger';
    }

    return 'bg-light text-dark';
});

function statusActionButtonClass(status) {
    const normalized = String(status ?? '').toUpperCase();

    if (normalized === 'COMPLETED') {
        return 'trip-header-btn--completed';
    }

    if (normalized === 'CANCELLED') {
        return 'trip-header-btn--cancelled';
    }

    if (normalized === 'IN_PROGRESS') {
        return 'trip-header-btn--progress';
    }

    return 'trip-header-btn--back';
}

function statusActionIcon(status) {
    const normalized = String(status ?? '').toUpperCase();

    if (normalized === 'COMPLETED') {
        return 'bi bi-check-circle-fill';
    }

    if (normalized === 'CANCELLED') {
        return 'bi bi-x-circle-fill';
    }

    if (normalized === 'IN_PROGRESS') {
        return 'bi bi-hourglass-split';
    }

    return 'bi bi-circle';
}

const validationMessages = computed(() => {
    const messages = [];

    Object.values(props.fieldErrors).forEach((value) => {
        if (Array.isArray(value)) {
            value.forEach((message) => messages.push(message));
        } else if (typeof value === 'string' && value) {
            messages.push(value);
        }
    });

    return [...new Set(messages)].slice(0, 8);
});

const routeLegsError = computed(() => {
    const err = props.fieldErrors.route_legs;

    return Array.isArray(err) ? err[0] : err;
});

const canReturnToStart = computed(() => {
    if (!isRoundTrip.value) {
        return false;
    }

    if (!props.form.start_station_id || props.form.route_legs.length === 0) {
        return false;
    }

    const last = props.form.route_legs[props.form.route_legs.length - 1];

    return String(last.to_station_id) !== String(props.form.start_station_id);
});

const isRoundTrip = computed(() => String(props.form.trip_type ?? 'ROUND_TRIP').toUpperCase() !== 'ONE_WAY');
const isOneWayTrip = computed(() => !isRoundTrip.value);
const tripTypeDisplay = computed(() => tripTypeLabel(props.form.trip_type));
const routeSectionTitle = computed(() => (isRoundTrip.value ? 'Round Trip Route' : 'One Way Route'));

const truckRunningMeterDisplay = computed(() => formatMeterKm(props.vehicleOdometerKm));

const startMeterDisplay = computed(() => {
    const value = props.form.start_meter_reading_km;

    if (value === '' || value == null) {
        return '—';
    }

    const reading = Number(value);

    if (!Number.isFinite(reading) || reading <= 0) {
        return '—';
    }

    return formatMeterKm(reading);
});

const endMeterDisplay = computed(() => formatMeterKm(props.form.end_meter_reading_km));

const totalKmDisplay = computed(() => {
    if (summary.value.totalKm > 0) {
        return formatMeterKm(summary.value.totalKm);
    }

    return '—';
});

const startMeterWarning = computed(() => {
    if (isFormLocked.value) {
        return false;
    }

    const start = Number(props.form.start_meter_reading_km ?? 0);
    const truck = Number(props.vehicleOdometerKm ?? 0);

    if (!Number.isFinite(start) || start <= 0 || !Number.isFinite(truck) || truck <= 0) {
        return false;
    }

    return start < truck;
});

const routePreview = computed(() => {
    if (!props.form.start_station_id) {
        return '';
    }

    const parts = [stationLabel(props.form.start_station_id)];

    props.form.route_legs.forEach((leg) => {
        if (leg.to_station_id) {
            parts.push(stationLabel(leg.to_station_id));
        }
    });

    return parts.join(' → ');
});

function stationLabel(stationId) {
    if (!stationId) {
        return '—';
    }

    const match = props.stations.find((s) => String(s.id) === String(stationId));

    return match?.name ?? `#${stationId}`;
}

function partyLabel(items, entityId, fallbackName = '') {
    if (!entityId) {
        return fallbackName || '—';
    }

    const match = items.find((item) => String(item.id) === String(entityId));

    return match?.name ?? fallbackName ?? `#${entityId}`;
}

function handleStartStationPickerChange() {
    emit('start-station-change');
    emit('clear-field-error', 'start_station_id');
}

function fieldErrorMessage(field) {
    const err = props.fieldErrors[field];

    return Array.isArray(err) ? err[0] : err;
}

function legFieldError(index, field) {
    const key = `route_legs.${index}.${field}`;
    const errors = props.fieldErrors[key];

    return Array.isArray(errors) ? errors[0] : errors;
}

function applyLegFreightAuto(index) {
    const leg = props.form.route_legs[index];

    if (!leg || !leg.freight_auto_calculate) {
        return;
    }

    leg.freight_amount = calculateLegFreight(leg.weight_ton, leg.freight_rate);
}

function legLoadMeta(weightTon) {
    return legLoadCategoryMeta(weightTon, props.form.max_load_ton);
}

function onLegWeightInput(index) {
    const leg = props.form.route_legs[index];
    const weight = Number(leg?.weight_ton ?? 0);

    if (weight <= 0 && Number(leg?.freight_amount ?? 0) <= 0) {
        leg.freight_amount = 0;
        leg.freight_auto_calculate = true;
    }

    applyLegFreightAuto(index);
    emit('clear-field-error', `route_legs.${index}.weight_ton`);
    emit('clear-field-error', `route_legs.${index}.freight_amount`);
}

function onLegRateInput(index) {
    applyLegFreightAuto(index);
}

function onLegAutoCalculateChange(index) {
    if (props.form.route_legs[index]?.freight_auto_calculate) {
        applyLegFreightAuto(index);
    }
}

function onLegFreightManualInput(index) {
    const leg = props.form.route_legs[index];

    if (leg) {
        leg.freight_auto_calculate = false;
    }
}

function syncAutoPlannedDiesel() {
    if (props.form.planned_diesel_manually_set || isFormLocked.value) {
        return;
    }

    const runningKm = calculateExpectedRunningKm(
        props.form.start_meter_reading_km,
        props.form.end_meter_reading_km || props.form.start_meter_reading_km,
    );

    if (runningKm === null) {
        return;
    }

    const plannedDiesel = calculatePlannedDiesel(runningKm, props.form.planned_mileage_km_per_liter);

    if (plannedDiesel !== null) {
        props.form.planned_diesel_liters = plannedDiesel;
    }
}

function onPlannedMileageInput() {
    syncAutoPlannedDiesel();
}

function onPlannedDieselInput() {
    props.form.planned_diesel_manually_set = true;
}

watch(
    () => [props.form.start_meter_reading_km, props.form.end_meter_reading_km, props.form.planned_mileage_km_per_liter],
    () => {
        syncAutoPlannedDiesel();
    },
);

watch(
    () => vehicleDefaultMileageResolved.value,
    (value) => {
        if (props.mode !== 'create') {
            return;
        }

        if (value === '' || value == null) {
            return;
        }

        const mileage = Number(value);

        if (!Number.isFinite(mileage) || mileage < 0) {
            return;
        }

        props.form.planned_mileage_km_per_liter = mileage;
        syncAutoPlannedDiesel();
    },
);

watch(
    () => props.vehicleMaxLoad,
    (value) => {
        if (props.mode !== 'create') {
            return;
        }

        if (value === '' || value == null) {
            return;
        }

        const maxLoad = Number(value);

        if (!Number.isFinite(maxLoad) || maxLoad < 0) {
            return;
        }

        props.form.max_load_ton = maxLoad;
    },
);

function formatNum(value) {
    return Number(value ?? 0).toLocaleString('en-IN', { maximumFractionDigits: 2 });
}

function formatMoney(value) {
    return Number(value ?? 0).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function onStatusChange(newStatus) {
    if (newStatus === props.form.status || props.statusLocked) {
        return;
    }

    if (newStatus === 'CANCELLED' || newStatus === 'COMPLETED' || newStatus === 'IN_PROGRESS') {
        pendingStatus.value = newStatus;
        showStatusModal.value = true;

        return;
    }

    props.form.status = newStatus;
    emit('status-updated', { status: newStatus });
}

function onStatusModalCancel() {
    showStatusModal.value = false;
    pendingStatus.value = '';
}

function onStatusModalConfirm(data) {
    props.form.status = pendingStatus.value;

    if (pendingStatus.value === 'CANCELLED') {
        props.form.cancelled_at = data.cancelled_at;
        props.form.cancellation_reason = data.cancellation_reason;
        props.form.end_meter_reading_km = data.end_meter_reading_km;
    } else if (pendingStatus.value === 'COMPLETED') {
        props.form.end_at = data.end_at;
        props.form.actual_arrival_at = data.actual_arrival_at;
        props.form.end_meter_reading_km = data.end_meter_reading_km;
    }

    showStatusModal.value = false;
    pendingStatus.value = '';

    emit('status-updated', {
        status: props.form.status,
        ...data,
    });
}

function onLegSameCustomerChange(index) {
    const leg = props.form.route_legs[index];

    if (!leg) {
        return;
    }

    if (leg.use_same_customer) {
        leg.customer_id = props.form.customer_id ?? '';

        return;
    }

    leg.customer_id = '';
}

function openQuickAddForLeg(index) {
    quickAddLegIndex.value = index;
    showStationQuickAdd.value = true;
}

function openQuickAddForTripCustomer() {
    quickAddCustomerLegIndex.value = null;
    showCustomerQuickAdd.value = true;
}

function openQuickAddForLegCustomer(index) {
    quickAddCustomerLegIndex.value = index;
    showCustomerQuickAdd.value = true;
}

function onStationCreated(station) {
    emit('station-created', { station, legIndex: quickAddLegIndex.value });
    quickAddLegIndex.value = null;
}

function onBrokerCreated(broker) {
    emit('broker-created', broker);
}

function onCustomerCreated(customer) {
    emit('customer-created', { customer, legIndex: quickAddCustomerLegIndex.value });
    quickAddCustomerLegIndex.value = null;
}

defineExpose({
    changeStatus: onStatusChange,
});
</script>

<style scoped>
.trip-expenses-card__header {
    padding: 14px 18px;
}
.trip-expenses-card__subtitle {
    font-size: 12px;
    opacity: 0.8;
    margin-top: 4px;
}
.trip-expenses-card__live-total {
    font-size: 14px;
    font-weight: 600;
    background: rgba(0, 0, 0, 0.18);
    border-radius: 99px;
    padding: 8px 14px;
    white-space: nowrap;
}
.trip-expenses-card__body {
    padding: 16px;
    background: #f9fafb;
}
.trip-number-label {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    flex-wrap: wrap;
}
.trip-details-rows {
    display: flex;
    flex-direction: column;
    gap: 16px;
}
.trip-details-row {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 16px;
}
.trip-details-row--three {
    grid-template-columns: repeat(3, minmax(0, 1fr));
}
.trip-details-row--four {
    grid-template-columns: repeat(4, minmax(0, 1fr));
}
.trip-details-row--five {
    grid-template-columns: repeat(5, minmax(0, 1fr));
}
.trip-details-row--five-compact {
    grid-template-columns: minmax(6.5rem, 0.6fr) minmax(5.5rem, 0.55fr) minmax(0, 1fr) minmax(0, 1fr) minmax(0, 1.4fr);
}
.trip-details-row__field--compact .form-label {
    font-size: 0.8125rem;
    line-height: 1.25;
}
.trip-details-row__compact-hint {
    font-size: 0.7rem;
    line-height: 1.25;
    margin-top: 0.2rem;
}
.trip-details-row > * {
    min-width: 0;
}
.trip-details-row__full-width {
    width: 100%;
}
.trip-details-rows .form-control,
.trip-details-rows .form-select,
.trip-details-rows .async-vehicle-picker {
    width: 100%;
    max-width: 100%;
}
.trip-end-date-arrival__label {
    font-size: 0.8125rem;
    color: #6c757d;
}
.trip-meter-input {
    width: 100%;
    max-width: 100%;
}
.trip-route-table {
    table-layout: auto;
    width: 100%;
    min-width: 1020px;
}
.trip-route-index-col {
    width: 2rem;
}
.trip-route-from-col {
    min-width: 7.5rem;
}
.trip-route-to-col {
    min-width: 10rem;
}
.trip-route-customer-col {
    min-width: 11rem;
}
.trip-route-weight-col {
    width: 7.5rem;
}
.trip-route-weight-field {
    display: flex;
    flex-direction: column;
    align-items: stretch;
    gap: 0.35rem;
}
.trip-route-weight-input,
.trip-route-weight-readonly {
    width: 100%;
}
.trip-route-mileage-col {
    width: 6.75rem;
    min-width: 6.75rem;
}
.trip-route-rate-col {
    width: 7.25rem;
    min-width: 7.25rem;
}
.trip-route-freight-col {
    width: 9rem;
    min-width: 9rem;
}
.trip-route-delete-col {
    width: 2.75rem;
}
.trip-route-table .leg-customer-cell {
    min-width: 0;
}
.arrival-timing {
    display: inline-flex;
    align-items: center;
    gap: 0.2rem;
    font-size: 0.7rem;
    font-weight: 600;
    line-height: 1;
    padding: 0.2rem 0.45rem;
    border-radius: 999px;
}
.arrival-timing--early {
    color: #0f5132;
    background: #d1e7dd;
}
.arrival-timing--on-time {
    color: #055160;
    background: #cff4fc;
}
.arrival-timing--late {
    color: #842029;
    background: #f8d7da;
}
.driver-field-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
    min-height: 1.5rem;
    margin-bottom: 0.5rem;
}
.trip-details-row__substitute {
    animation: substitute-field-in 0.2s ease-out;
}
.substitute-toggle {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding-left: 0;
    min-height: auto;
}
.substitute-toggle .form-check-input {
    width: 2em;
    height: 1.1em;
    margin: 0;
    cursor: pointer;
}
.substitute-toggle .form-check-label {
    font-size: 0.8125rem;
    font-weight: 500;
    color: #6c757d;
    cursor: pointer;
    white-space: nowrap;
    padding-top: 0;
}
.substitute-toggle .form-check-input:checked + .form-check-label {
    color: #146c43;
}
@media (max-width: 992px) {
    .trip-details-row {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .trip-details-row--five-compact {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}
@media (max-width: 768px) {
    .trip-details-row {
        grid-template-columns: 1fr;
    }
}
@keyframes substitute-field-in {
    from {
        opacity: 0;
        transform: translateY(-4px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}
</style>
