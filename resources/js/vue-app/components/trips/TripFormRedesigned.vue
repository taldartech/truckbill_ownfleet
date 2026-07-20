<template>
    <div class="trf" @keydown.enter.prevent="handleEnterKey">

        <!-- Sticky validation bar -->
        <Transition name="trf-slide">
            <div v-if="bannerMessage || routeErrorChips.length > 0" class="trf-validation-bar">
                <div class="trf-validation-bar__inner">
                    <div v-if="bannerMessage" class="trf-validation-bar__row trf-validation-bar__row--error">
                        <i class="bi bi-exclamation-octagon-fill"></i>
                        <span>{{ bannerMessage }}</span>
                    </div>
                    <div
                        v-else-if="routeErrorChips.length > 0"
                        class="trf-validation-bar__row trf-validation-bar__row--warn trf-validation-bar__row--chips"
                    >
                        <i class="bi bi-signpost-split-fill"></i>
                        <div class="trf-error-chips">
                            <button
                                v-for="chip in routeErrorChips"
                                :key="chip.key"
                                type="button"
                                class="trf-error-chip"
                                :title="chip.message"
                                @click="focusRouteError(chip)"
                            >
                                {{ chip.label }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </Transition>


        <!-- Section 1 starts below -->
        <!-- ─────────────────────────────────────────────────────────────────
             SECTION 1 — TRIP DETAILS
        ───────────────────────────────────────────────────────────────── -->
        <div class="trf-section">
            <div class="trf-section__head">
                <div class="trf-section__head-left">
                    <div class="trf-step-num">1</div>
                    <div>
                        <h2 class="trf-section__title">Trip Details</h2>
                        <p class="trf-section__desc">Vehicle, driver, customer and trip dates</p>
                    </div>
                </div>
                <div class="trf-section__head-right">
                    <div class="trf-trip-id trf-trip-id--head">
                        <template v-if="mode === 'create'">
                            <div class="trf-trip-id__meta">
                                <span class="trf-trip-id__label">Trip Number</span>
                                <span class="trf-trip-num-badge bg-warning text-dark">Pending</span>
                            </div>
                            <div class="trf-trip-id__value">
                                <input
                                    :value="tripNumberDisplay"
                                    type="text"
                                    class="trf-trip-id__preview"
                                    readonly
                                    placeholder="Preview…"
                                    tabindex="-1"
                                />
                                <input
                                    :value="form.trip_sequence"
                                    type="text"
                                    inputmode="numeric"
                                    class="trf-trip-id__seq"
                                    :class="{ 'trf-trip-id__seq--error': fieldErrors.trip_sequence }"
                                    placeholder="Seq"
                                    title="Change this number if you want a different trip number"
                                    @input="onTripSequenceInput"
                                />
                            </div>
                            <p class="trf-trip-id__hint">Change the last number if you want a different trip number</p>
                            <div v-if="fieldErrors.trip_sequence" class="trf-pf__err">{{ fieldErrorMessage('trip_sequence') }}</div>
                        </template>
                        <template v-else>
                            <div class="trf-trip-id__meta">
                                <span class="trf-trip-id__label">Trip Number</span>
                                <span
                                    class="trf-trip-num-badge"
                                    :class="currentStatusBadge.badge"
                                >
                                    {{ currentStatusBadge.label }}
                                </span>
                            </div>
                            <div class="trf-trip-id__preview trf-trip-id__preview--static">{{ tripNumberDisplay || '—' }}</div>
                        </template>
                    </div>
                </div>
            </div>

            <div class="trf-section__body">
                <!-- ── ROW 1: Truck | Driver | Customer | Start Date | End Date ── -->
                <div class="trf-picker-grid trf-picker-grid--details">
                    <!-- Truck -->
                    <div class="trf-pf">
                        <div class="trf-pf__lbl">
                            <span class="trf-pf__lbl-text">Truck<span class="trf-pf__req">*</span></span>
                        </div>
                        <AsyncVehicleTripPickerRedesigned
                            v-if="mode === 'create' && !isFormLocked"
                            v-model="form.vehicle_id"
                            placeholder="Select truck"
                            :invalid="Boolean(fieldErrors.vehicle_id)"
                            :as-of-date="tripStartDate"
                            :trip-end-date="tripEndDate"
                            :driver-label="driverDisplayName"
                            @vehicle-selected="handleVehicleSelection"
                        />
                        <div
                            v-else
                            class="trf-synced-field"
                            :class="{ 'trf-synced-field--empty': !snapshotVehicleLabel || snapshotVehicleLabel === '—' }"
                        >
                            <div class="trf-synced-field__main">{{ snapshotVehicleLabel || '—' }}</div>
                            <div v-if="vehicleMake || vehicleModel" class="trf-synced-field__sub">{{ vehicleMake }} {{ vehicleModel }}</div>
                        </div>
                        <div v-if="fieldErrors.vehicle_id" class="trf-pf__err">{{ fieldErrorMessage('vehicle_id') }}</div>
                    </div>

                    <!-- Driver -->
                    <div class="trf-pf trf-pf--driver">
                        <div class="trf-pf__lbl trf-pf__lbl--driver">
                            <label
                                v-if="!isFormLocked && hasPrimaryDriver"
                                class="trf-subst-toggle"
                                :class="{ 'is-on': showSubstituteDriver }"
                                for="subst-sw"
                            >
                                <input
                                    id="subst-sw"
                                    v-model="showSubstituteDriver"
                                    type="checkbox"
                                    class="trf-subst-toggle__input"
                                    role="switch"
                                    :aria-checked="showSubstituteDriver"
                                />
                                <span class="trf-subst-toggle__track" aria-hidden="true">
                                    <span class="trf-subst-toggle__thumb"></span>
                                </span>
                                <span class="trf-subst-toggle__text">Substitute Driver</span>
                            </label>
                        </div>
                        <div class="trf-driver-fields">
                            <template v-if="showSubstituteDriverColumn">
                                <SearchablePickerRedesigned
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
                                />
                                <div
                                    v-else
                                    class="trf-synced-field"
                                    :class="{ 'trf-synced-field--empty': !substituteDriverDisplay || substituteDriverDisplay === '—' }"
                                >
                                    <div class="trf-synced-field__main">{{ substituteDriverDisplay || '—' }}</div>
                                </div>
                            </template>
                            <template v-else>
                                <div
                                    class="trf-synced-field"
                                    :class="{
                                        'trf-synced-field--empty': !hasDriverDisplay && !loadingDriver,
                                        'trf-synced-field--error': Boolean(fieldErrors.driver_id),
                                    }"
                                >
                                    <div class="trf-synced-field__main">
                                        {{ loadingDriver ? 'Syncing from truck…' : (hasDriverDisplay ? driverDisplayName : 'Select truck first') }}
                                    </div>
                                    <div v-if="hasDriverDisplay || loadingDriver" class="trf-synced-field__sub">
                                        {{ selectedDriver?.license_number || 'Auto-synced with truck' }}
                                    </div>
                                </div>
                            </template>
                        </div>
                        <div v-if="!showSubstituteDriverColumn && fieldErrors.driver_id" class="trf-pf__err">{{ fieldErrorMessage('driver_id') }}</div>
                        <div v-if="showSubstituteDriverColumn && fieldErrors.substitute_driver_id" class="trf-pf__err">{{ fieldErrorMessage('substitute_driver_id') }}</div>
                    </div>

                    <!-- Customer -->
                    <div class="trf-pf trf-pf--customer">
                        <div class="trf-pf__lbl">
                            <span class="trf-pf__lbl-text">Customer<span class="trf-pf__req">*</span></span>
                        </div>
                        <SearchablePickerRedesigned
                            v-if="!isFormLocked"
                            v-model="form.customer_id"
                            :items="customers"
                            attach-dropdown-to-body
                            placeholder="Select customer"
                            search-placeholder="Search customer…"
                            empty-message="No customer found."
                            view-title="Customer details"
                            quick-add-title="Add customer"
                            :invalid="Boolean(fieldErrors.customer_id)"
                            :can-quick-add="canQuickAddCustomer"
                            subtext-key="gst_number"
                            :show-subtext="false"
                            title-case-label
                            @quick-add="openQuickAddForTripCustomer"
                            @update:model-value="saveProgress"
                        />
                        <div v-else class="trf-ro-val">
                            <span class="trf-ro-val__main">{{ customerLabel(form.customer_id, snapshotCustomerName) }}</span>
                            <span v-if="selectedCustomer && selectedCustomer.gst_number" class="trf-ro-val__sub">GST: {{ selectedCustomer.gst_number }}</span>
                        </div>
                        <div v-if="fieldErrors.customer_id" class="trf-pf__err">{{ fieldErrorMessage('customer_id') }}</div>
                    </div>

                    <!-- Start Date -->
                    <div class="trf-pf trf-pf--date">
                        <div class="trf-pf__lbl">
                            <span class="trf-pf__lbl-text">Start Date<span class="trf-pf__req">*</span></span>
                        </div>
                        <AppDateInput
                            v-if="!isFormLocked"
                            v-model="form.start_at"
                            class="trf-date-input"
                            :invalid="Boolean(fieldErrors.start_at)"
                            @change="saveProgress"
                        />
                        <div v-else class="trf-ro-val"><span class="trf-ro-val__main">{{ formatDate(form.start_at) }}</span></div>
                        <div v-if="fieldErrors.start_at" class="trf-pf__err">{{ fieldErrorMessage('start_at') }}</div>
                    </div>

                    <!-- End Date -->
                    <div class="trf-pf trf-pf--date">
                        <div class="trf-pf__lbl">
                            <span class="trf-pf__lbl-text">End Date</span>
                            <span v-if="arrivalTiming === 'on-time'" class="trf-timing-pill trf-timing-pill--ok"><i class="bi bi-check-circle-fill"></i> On Time</span>
                            <span v-else-if="arrivalTiming === 'early'" class="trf-timing-pill trf-timing-pill--early"><i class="bi bi-arrow-down-circle-fill"></i> Early</span>
                            <span v-else-if="arrivalTiming === 'late'" class="trf-timing-pill trf-timing-pill--late"><i class="bi bi-exclamation-circle-fill"></i> Late</span>
                        </div>
                        <AppDateInput
                            v-if="!isFormLocked"
                            v-model="form.end_at"
                            class="trf-date-input"
                            :invalid="Boolean(fieldErrors.end_at)"
                            :min="tripStartDate || undefined"
                            @change="saveProgress"
                        />
                        <div v-else class="trf-ro-val"><span class="trf-ro-val__main">{{ formatDate(form.end_at) || '—' }}</span></div>
                        <div v-if="fieldErrors.end_at" class="trf-pf__err">{{ fieldErrorMessage('end_at') }}</div>
                    </div>
                </div>

                <!-- ── ROW 2: Meter, diesel & fuel cards ── -->
                <div class="trf-vehicle-info trf-vehicle-info--metrics mt-3">
                    <div
                        class="trf-vehicle-info__item trf-vehicle-info__item--editable"
                        :class="{
                            'trf-vehicle-info__item--locked': requiresTruckSelection,
                            'trf-vehicle-info__item--error': startMeterWarning || fieldErrors.start_meter_reading_km,
                        }"
                    >
                        <div class="trf-vehicle-info__icon" style="--ic:#6b7280;--bg:#f3f4f6"><i class="bi bi-speedometer2"></i></div>
                        <div class="trf-vehicle-info__field">
                            <span class="trf-vehicle-info__name">
                                Start Meter Reading
                                <span v-if="isStartMeterOptional" class="trf-vehicle-info__optional">(optional)</span>
                            </span>
                            <div class="trf-vehicle-info__value">
                                <input
                                    v-if="!isFormLocked"
                                    v-model.number="form.start_meter_reading_km"
                                    v-no-scroll-change
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    class="trf-input trf-input--metric"
                                    :class="{ 'trf-input--error': startMeterWarning || fieldErrors.start_meter_reading_km }"
                                    placeholder="0"
                                    :disabled="requiresTruckSelection"
                                    :title="requiresTruckSelection ? 'Select a truck first' : undefined"
                                    @input="emit('clear-field-error', 'start_meter_reading_km'); saveProgress()"
                                />
                                <span v-else class="trf-input trf-input--metric trf-input--readonly">{{ startMeterDisplay }}</span>
                            </div>
                            <div class="trf-vehicle-info__meta-slot">
                                <div class="trf-vehicle-info__meta">
                                    <span class="trf-vehicle-info__meta-label">Truck Running Meter</span>
                                    <span class="trf-vehicle-info__meta-val">{{ truckRunningMeterDisplay || '—' }}</span>
                                </div>
                            </div>
                            <div v-if="fieldErrors.start_meter_reading_km" class="trf-pf__err">{{ fieldErrorMessage('start_meter_reading_km') }}</div>
                        </div>
                    </div>

                    <div
                        class="trf-vehicle-info__item trf-vehicle-info__item--editable"
                        :class="{
                            'trf-vehicle-info__item--locked': requiresTruckSelection,
                            'trf-vehicle-info__item--error': fieldErrors.planned_diesel_liters,
                        }"
                    >
                        <div class="trf-vehicle-info__icon" style="--ic:#0d9488;--bg:#f0fdfa"><i class="bi bi-fuel-pump-fill"></i></div>
                        <div class="trf-vehicle-info__field">
                            <span class="trf-vehicle-info__name">Expected Diesel (Ltr)</span>
                            <div class="trf-vehicle-info__value">
                                <input
                                    v-if="!isFormLocked"
                                    v-model.number="form.planned_diesel_liters"
                                    v-no-scroll-change
                                    type="number"
                                    min="0"
                                    :max="maxPlannedDieselLiters"
                                    step="0.01"
                                    class="trf-input trf-input--metric"
                                    :class="{ 'trf-input--error': fieldErrors.planned_diesel_liters }"
                                    placeholder="0"
                                    :disabled="requiresTruckSelection"
                                    :title="fieldErrorMessage('planned_diesel_liters') || (requiresTruckSelection ? 'Select a truck first' : undefined)"
                                    @input="onPlannedDieselInput"
                                />
                                <span v-else class="trf-input trf-input--metric trf-input--readonly">{{ expectedDieselDisplay }}</span>
                            </div>
                            <div class="trf-vehicle-info__meta-slot">
                                <div class="trf-vehicle-info__meta">
                                    <span class="trf-vehicle-info__meta-label">Fuel Tank Capacity (Ltr)</span>
                                    <span class="trf-vehicle-info__meta-val">{{ fuelTankCapacityDisplay }}</span>
                                </div>
                            </div>
                            <div v-if="fieldErrors.planned_diesel_liters" class="trf-pf__err">{{ fieldErrorMessage('planned_diesel_liters') }}</div>
                        </div>
                    </div>

                    <div
                        class="trf-vehicle-info__item trf-vehicle-info__item--editable"
                        :class="{
                            'trf-vehicle-info__item--locked': requiresTruckSelection,
                            'trf-vehicle-info__item--error': fieldErrors.diesel_rate_per_liter,
                        }"
                    >
                        <div class="trf-vehicle-info__icon" style="--ic:#be185d;--bg:#fdf2f8"><i class="bi bi-currency-rupee"></i></div>
                        <div class="trf-vehicle-info__field">
                            <span class="trf-vehicle-info__name">Diesel Rate (₹/Ltr)</span>
                            <div class="trf-vehicle-info__value">
                                <input
                                    v-if="!isFormLocked"
                                    v-model.number="form.diesel_rate_per_liter"
                                    v-no-scroll-change
                                    type="number"
                                    min="0"
                                    :max="maxDieselRatePerLiter"
                                    step="0.01"
                                    class="trf-input trf-input--metric"
                                    :class="{ 'trf-input--error': fieldErrors.diesel_rate_per_liter }"
                                    placeholder="0.00"
                                    :disabled="requiresTruckSelection"
                                    :title="fieldErrorMessage('diesel_rate_per_liter') || (requiresTruckSelection ? 'Select a truck first' : undefined)"
                                    @input="onDieselRateInput"
                                />
                                <span v-else class="trf-input trf-input--metric trf-input--readonly">{{ form.diesel_rate_per_liter ? '₹' + formatMoney(form.diesel_rate_per_liter) : '—' }}</span>
                            </div>
                            <div class="trf-vehicle-info__meta-slot" aria-hidden="true"></div>
                            <div v-if="fieldErrors.diesel_rate_per_liter" class="trf-pf__err">{{ fieldErrorMessage('diesel_rate_per_liter') }}</div>
                        </div>
                    </div>

                    <div
                        class="trf-vehicle-info__item trf-vehicle-info__item--editable"
                        :class="{
                            'trf-vehicle-info__item--locked': requiresTruckSelection,
                            'trf-vehicle-info__item--error': fieldErrors.planned_mileage_km_per_liter,
                        }"
                    >
                        <div class="trf-vehicle-info__icon" style="--ic:#2563eb;--bg:#eff6ff"><i class="bi bi-speedometer"></i></div>
                        <div class="trf-vehicle-info__field">
                            <span class="trf-vehicle-info__name">Fuel Average (KM/L)</span>
                            <div class="trf-vehicle-info__value">
                                <input
                                    v-if="!isFormLocked"
                                    v-model.number="form.planned_mileage_km_per_liter"
                                    v-no-scroll-change
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    class="trf-input trf-input--metric"
                                    :class="{ 'trf-input--error': fieldErrors.planned_mileage_km_per_liter }"
                                    placeholder="0"
                                    :disabled="requiresTruckSelection"
                                    :title="fieldErrorMessage('planned_mileage_km_per_liter') || (requiresTruckSelection ? 'Select a truck first' : undefined)"
                                    @input="onPlannedMileageInput"
                                />
                                <span v-else class="trf-input trf-input--metric trf-input--readonly">{{ fuelAvgDisplay }}</span>
                            </div>
                            <div class="trf-vehicle-info__meta-slot" aria-hidden="true"></div>
                            <div v-if="fieldErrors.planned_mileage_km_per_liter" class="trf-pf__err">{{ fieldErrorMessage('planned_mileage_km_per_liter') }}</div>
                        </div>
                    </div>
                </div>
                <p v-if="requiresTruckSelection" class="trf-pf__hint mt-2">Select a truck first to enter meter readings and diesel details.</p>

                <!-- Driver Warning -->
                <div v-if="driverWarning" class="trf-banner trf-banner--warn mt-4">
                    <i class="bi bi-exclamation-triangle-fill"></i><span>{{ driverWarning }}</span>
                </div>

                <div v-if="driverLicenseWarning" class="trf-banner trf-banner--warn mt-3">
                    <i class="bi bi-card-heading"></i>
                    <span>{{ driverLicenseWarning }}</span>
                </div>

                <div v-if="startMeterWarning" class="trf-banner trf-banner--warn mt-3">
                    <i class="bi bi-exclamation-triangle-fill"></i>
                    <span>Start Meter entered ({{ form.start_meter_reading_km }} KM) is lower than current truck odometer ({{ truckRunningMeterDisplay }}). Please verify before saving.</span>
                </div>

                <div v-if="dieselOverCapacityWarning" class="trf-banner trf-banner--warn mt-3">
                    <i class="bi bi-fuel-pump-fill"></i>
                    <span>{{ dieselOverCapacityWarning }}</span>
                </div>
            </div>
        </div>

        <!-- ─────────────────────────────────────────────────────────────────
             SECTION 2 — ROUTE & STOPS
        ───────────────────────────────────────────────────────────────── -->
        <div class="trf-section">
            <div class="trf-section__head trf-section__head--green">
                <div class="trf-section__head-left">
                    <div class="trf-step-num trf-step-num--green">2</div>
                    <div>
                        <h2 class="trf-section__title">Route &amp; Stops</h2>
                        <p class="trf-section__desc">Add stops, then return to source or end the trip</p>
                    </div>
                    <span
                        class="trf-route-type-badge"
                        :class="inferredTripTypeLabel
                            ? (isRoundTrip ? 'trf-route-type-badge--round' : 'trf-route-type-badge--oneway')
                            : 'trf-route-type-badge--placeholder'"
                        :title="inferredTripTypeHint || 'Trip type appears after you add stops'"
                    >
                        <template v-if="inferredTripTypeLabel">
                            <i :class="isRoundTrip ? 'bi bi-arrow-repeat' : 'bi bi-arrow-right'"></i>
                            {{ inferredTripTypeLabel }}
                        </template>
                        <template v-else>
                            <i class="bi bi-dash"></i>
                            Trip type
                        </template>
                    </span>
                </div>
                <div class="trf-section__head-right">
                    <div v-if="!isFormLocked" class="trf-route-actions">
                        <button
                            type="button"
                            class="trf-head-btn trf-head-btn--ghost"
                            :disabled="!canReturnToSource"
                            title="Add a return leg back to the start station (round trip)"
                            @click="handleReturnToSource"
                        >
                            <i class="bi bi-arrow-return-left"></i>
                            <span>Return to source</span>
                        </button>
                        <button
                            type="button"
                            class="trf-head-btn trf-head-btn--solid"
                            :disabled="requiresTruckSelection"
                            :title="requiresTruckSelection ? 'Select a truck first' : addStopActionTitle"
                            @click="onAddStopClick"
                        >
                            <i :class="hasStartStation ? 'bi bi-plus-lg' : 'bi bi-geo-alt-fill'"></i>
                            <span>{{ addStopActionLabel }}</span>
                        </button>
                        <button
                            type="button"
                            class="trf-head-btn trf-head-btn--ghost"
                            :disabled="!canEndTrip"
                            title="Finish the trip at the last stop without returning (one way)"
                            @click="handleEndTrip"
                        >
                            <i class="bi bi-flag-fill"></i>
                            <span>End trip</span>
                        </button>
                    </div>
                </div>
            </div>

            <div class="trf-section__body" :class="{ 'trf-section__body--locked': requiresTruckSelection }">
                <div v-if="requiresTruckSelection" class="trf-banner trf-banner--info mb-3">
                    <i class="bi bi-truck-front-fill"></i>
                    <span>Select a truck in Trip Details first, then build the route and stops.</span>
                </div>

                <div v-if="routeEndedOneWay && !isFormLocked && !requiresTruckSelection" class="trf-banner trf-banner--info mb-3">
                    <i class="bi bi-flag-fill"></i>
                    <span>
                        One way trip — ends at <strong>{{ lastStopStationName }}</strong>.
                        Add a stop or return to source if you need to change this.
                    </span>
                </div>

                <!-- Route groups: Home+Stop1 share one right card height; later stops pair 1:1 -->
                <div
                    class="trf-route-board"
                    :class="{
                        'trf-route-board--has-stops': hasRouteStops,
                        'trf-route-board--locked': requiresTruckSelection,
                    }"
                >
                    <div v-if="overloadWarning && hasRouteStops" class="trf-banner trf-banner--warn trf-route-board__banner">
                        <i class="bi bi-exclamation-triangle-fill"></i>
                        <span>{{ overloadWarning }}</span>
                    </div>

                    <div
                        v-for="group in routeGroups"
                        :key="group.id"
                        class="trf-route-group"
                        :class="[
                            `trf-route-group--${group.type}`,
                            {
                                'trf-route-group--no-card': !group.cardLeg && group.type !== 'primary',
                                'trf-route-group--empty-right': group.type === 'primary' && !group.cardLeg,
                            },
                        ]"
                    >
                        <div class="trf-route-group__left">
                            <div
                                v-for="item in group.stations"
                                :key="item.id"
                                class="trf-route-station"
                                :class="`trf-route-station--${item.kind}`"
                            >
                                <div class="trf-route-board__rail" :class="`trf-route-board__rail--${item.kind}`">
                                    <div
                                        v-if="item.kind === 'stop' && group.type === 'stop'"
                                        class="trf-route-board__line trf-route-board__line--lead"
                                    ></div>
                                    <div
                                        v-if="item.kind === 'home'"
                                        class="trf-route-board__dot trf-route-board__dot--home"
                                    >
                                        <i class="bi bi-house-door-fill"></i>
                                    </div>
                                    <div
                                        v-else
                                        class="trf-route-board__dot"
                                        :class="{ 'trf-route-board__dot--empty': isEmptyReturnLeg(item.leg, item.legIndex) }"
                                    >
                                        {{ item.legIndex + 1 }}
                                    </div>
                                    <div v-if="item.showLine" class="trf-route-board__line"></div>
                                </div>

                                <div class="trf-route-board__station" :class="`trf-route-board__station--${item.kind}`">
                                    <template v-if="item.kind === 'home'">
                                        <div class="trf-route-board__label">
                                            Source<span class="trf-pf__req">*</span>
                                        </div>
                                        <SearchablePickerRedesigned
                                            v-if="!isFormLocked"
                                            v-model="form.start_station_id"
                                            :items="stations"
                                            attach-dropdown-to-body
                                            placeholder="Select start station / depot"
                                            search-placeholder="Search station…"
                                            empty-message="No station found."
                                            view-title="Location details"
                                            quick-add-title="Add location"
                                            :disabled="requiresTruckSelection"
                                            :invalid="Boolean(fieldErrors.start_station_id)"
                                            :error-message="fieldErrorMessage('start_station_id')"
                                            :can-quick-add="canQuickAddStation && !requiresTruckSelection"
                                            @quick-add="openQuickAddForLeg(null)"
                                            @update:model-value="handleStartStationPickerChange"
                                        />
                                        <div v-else class="trf-ro-val"><span class="trf-ro-val__main">{{ stationLabel(form.start_station_id) }}</span></div>
                                    </template>
                                    <template v-else>
                                        <div class="trf-route-board__label-row">
                                            <span class="trf-route-board__label">
                                                Stop {{ item.legIndex + 1 }}<span class="trf-pf__req">*</span>
                                            </span>
                                            <button
                                                v-if="!isFormLocked"
                                                type="button"
                                                class="trf-leg__station-clear"
                                                :title="stopRemoveTitle(item.legIndex)"
                                                :disabled="requiresTruckSelection || isStopRemoveBlocked(item.legIndex)"
                                                @click="openRemoveStopConfirm(item.legIndex)"
                                            >
                                                <i class="bi bi-x-lg"></i>
                                            </button>
                                        </div>
                                        <div class="trf-leg__station-picker">
                                            <SearchablePickerRedesigned
                                                v-if="!isFormLocked"
                                                v-model="item.leg.to_station_id"
                                                :items="stations"
                                                attach-dropdown-to-body
                                                placeholder="To station"
                                                search-placeholder="Search station…"
                                                empty-message="No station found."
                                                view-title="Location details"
                                                quick-add-title="Add location"
                                                :disabled="requiresTruckSelection"
                                                :invalid="Boolean(legFieldError(item.legIndex, 'to_station_id'))"
                                                :error-message="legFieldError(item.legIndex, 'to_station_id')"
                                                :can-quick-add="canQuickAddStation && !requiresTruckSelection"
                                                @quick-add="openQuickAddForLeg(item.legIndex)"
                                                @update:model-value="handleLegStopChange(item.legIndex)"
                                            />
                                            <div v-else class="trf-ro-val"><span class="trf-ro-val__main">{{ stationLabel(item.leg.to_station_id) }}</span></div>
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </div>

                        <div class="trf-route-group__right">
                            <div
                                v-if="group.cardLeg"
                                :id="`trf-leg-card-${group.cardIndex}`"
                                class="trf-leg__card"
                                :class="{ 'trf-leg__card--collapsed': !isStopExpanded(group.cardIndex) }"
                            >
                                <button
                                    type="button"
                                    class="trf-leg__summary"
                                    :class="{ 'trf-leg__summary--error': stopHasFieldErrors(group.cardIndex) }"
                                    :aria-expanded="isStopExpanded(group.cardIndex)"
                                    :disabled="isFormLocked"
                                    @click="toggleStopExpanded(group.cardIndex)"
                                >
                                    <div class="trf-leg__summary-main">
                                        <span class="trf-leg__summary-stop">Stop {{ group.cardIndex + 1 }}</span>
                                        <span class="trf-leg__summary-sep">·</span>
                                        <span class="trf-leg__summary-route">{{ stopRouteLabel(group.cardLeg, group.cardIndex) }}</span>
                                        <span class="trf-leg__summary-sep">·</span>
                                        <span
                                            class="trf-leg__load-badge"
                                            :class="{ 'trf-leg__load-badge--over': isLegOverloaded(group.cardLeg) }"
                                        >
                                            Load Type: {{ legLoadMeta(group.cardLeg.weight_ton).label }}
                                            <template v-if="isLegOverloaded(group.cardLeg)"> · Over capacity</template>
                                        </span>
                                        <span class="trf-leg__summary-sep">·</span>
                                        <span class="trf-leg__summary-customer">{{ stopSummaryCustomer(group.cardLeg) }}</span>
                                    </div>
                                    <div class="trf-leg__summary-meta">
                                        <span
                                            class="trf-leg__summary-freight"
                                            :class="{ 'is-empty': !(Number(group.cardLeg.freight_amount) > 0) }"
                                        >
                                            {{ Number(group.cardLeg.freight_amount) > 0 ? `₹${formatMoney(group.cardLeg.freight_amount)}` : 'No freight' }}
                                        </span>
                                        <i
                                            class="bi"
                                            :class="isStopExpanded(group.cardIndex) ? 'bi-chevron-up' : 'bi-chevron-down'"
                                            aria-hidden="true"
                                        ></i>
                                    </div>
                                </button>

                                <template v-if="isStopExpanded(group.cardIndex)">
                                <div class="trf-leg__fields">
                                    <div class="trf-lf trf-lf--customer">
                                        <div class="trf-field-head trf-field-head--customer">
                                            <label
                                                v-if="!isFormLocked"
                                                class="trf-subst-toggle"
                                                :class="{ 'is-on': !group.cardLeg.use_same_customer }"
                                                :for="`same-cust-${group.cardIndex}`"
                                            >
                                                <input
                                                    :id="`same-cust-${group.cardIndex}`"
                                                    type="checkbox"
                                                    class="trf-subst-toggle__input"
                                                    role="switch"
                                                    :checked="!group.cardLeg.use_same_customer"
                                                    :aria-checked="!group.cardLeg.use_same_customer"
                                                    @change="onStopWiseCustomerToggle(group.cardIndex, $event)"
                                                />
                                                <span class="trf-subst-toggle__track" aria-hidden="true">
                                                    <span class="trf-subst-toggle__thumb"></span>
                                                </span>
                                                <span class="trf-subst-toggle__text">Stop-wise Customer<span class="trf-pf__req">*</span></span>
                                            </label>
                                            <span v-else class="trf-lf__lbl">Stop-wise Customer</span>
                                            <div class="trf-field-head__actions" aria-hidden="true"></div>
                                        </div>
                                        <template v-if="!isFormLocked">
                                            <div
                                                v-if="!group.cardLeg.use_same_customer"
                                                :id="`trf-leg-${group.cardIndex}-customer_id`"
                                                class="trf-lf__picker"
                                            >
                                                <SearchablePickerRedesigned
                                                    v-model="group.cardLeg.customer_id"
                                                    :items="customers"
                                                    attach-dropdown-to-body
                                                    placeholder="Select customer"
                                                    search-placeholder="Search customer…"
                                                    empty-message="No customer found."
                                                    view-title="Customer details"
                                                    quick-add-title="Add customer"
                                                    :invalid="Boolean(legFieldError(group.cardIndex, 'customer_id'))"
                                                    :error-message="legFieldError(group.cardIndex, 'customer_id')"
                                                    :can-quick-add="canQuickAddCustomer"
                                                    :show-subtext="false"
                                                    title-case-label
                                                    compact-actions
                                                    @quick-add="openQuickAddForLegCustomer(group.cardIndex)"
                                                    @update:model-value="saveProgress"
                                                />
                                            </div>
                                            <div
                                                v-else
                                                class="trf-same-cust"
                                            >
                                                {{ customerLabel(group.cardLeg.customer_id, snapshotCustomerName) || 'Same as main' }}
                                            </div>
                                        </template>
                                        <div v-else class="trf-ro-val">
                                            <span class="trf-ro-val__main">{{ customerLabel(group.cardLeg.customer_id, snapshotCustomerName) }}</span>
                                        </div>
                                    </div>

                                    <div :id="`trf-leg-${group.cardIndex}-consignor_id`" class="trf-lf">
                                        <label class="trf-lf__lbl">Consignor (From)</label>
                                        <div v-if="!isFormLocked" class="trf-lf__picker">
                                            <SearchablePickerRedesigned
                                                v-model="group.cardLeg.consignor_id"
                                                :items="customers"
                                                attach-dropdown-to-body
                                                placeholder="Select consignor"
                                                search-placeholder="Search customer…"
                                                empty-message="No customer found."
                                                view-title="Customer details"
                                                quick-add-title="Add customer"
                                                :can-quick-add="canQuickAddCustomer"
                                                :show-subtext="false"
                                                title-case-label
                                                compact-actions
                                                @quick-add="openQuickAddForLegConsignor(group.cardIndex)"
                                                @update:model-value="saveProgress"
                                            />
                                        </div>
                                        <div v-else class="trf-ro-val">
                                            <span class="trf-ro-val__main">{{ customerLabel(group.cardLeg.consignor_id) }}</span>
                                        </div>
                                    </div>

                                    <div :id="`trf-leg-${group.cardIndex}-consignee_id`" class="trf-lf">
                                        <label class="trf-lf__lbl">Consignee (To)</label>
                                        <div v-if="!isFormLocked" class="trf-lf__picker">
                                            <SearchablePickerRedesigned
                                                v-model="group.cardLeg.consignee_id"
                                                :items="customers"
                                                attach-dropdown-to-body
                                                placeholder="Select consignee"
                                                search-placeholder="Search customer…"
                                                empty-message="No customer found."
                                                view-title="Customer details"
                                                quick-add-title="Add customer"
                                                :can-quick-add="canQuickAddCustomer"
                                                :show-subtext="false"
                                                title-case-label
                                                compact-actions
                                                @quick-add="openQuickAddForLegConsignee(group.cardIndex)"
                                                @update:model-value="saveProgress"
                                            />
                                        </div>
                                        <div v-else class="trf-ro-val">
                                            <span class="trf-ro-val__main">{{ customerLabel(group.cardLeg.consignee_id) }}</span>
                                        </div>
                                    </div>

                                    <div :id="`trf-leg-${group.cardIndex}-pickup_date`" class="trf-lf">
                                        <label class="trf-lf__lbl">Pickup Date<span class="trf-pf__req">*</span></label>
                                        <input
                                            v-if="!isFormLocked"
                                            v-model="group.cardLeg.pickup_date"
                                            type="date"
                                            class="trf-input"
                                            :class="{ 'trf-input--error': legFieldError(group.cardIndex, 'pickup_date') }"
                                            :min="pickupDateMinForStop(group.cardIndex)"
                                            :max="tripEndDate || undefined"
                                            :title="legFieldError(group.cardIndex, 'pickup_date') || undefined"
                                            @change="emit('clear-field-error', `route_legs.${group.cardIndex}.pickup_date`); saveProgress()"
                                        />
                                        <span v-else class="trf-ro-val__main">{{ group.cardLeg.pickup_date || '—' }}</span>
                                        <div v-if="legFieldError(group.cardIndex, 'pickup_date')" class="trf-pf__err">
                                            {{ legFieldError(group.cardIndex, 'pickup_date') }}
                                        </div>
                                    </div>

                                    <div class="trf-leg__cargo-row">
                                    <div :id="`trf-leg-${group.cardIndex}-goods_name`" class="trf-lf">
                                        <div class="trf-field-head">
                                            <label class="trf-lf__lbl">Goods Type<span class="trf-pf__req">*</span></label>
                                            <div class="trf-field-head__actions" aria-hidden="true"></div>
                                        </div>
                                        <div v-if="!isFormLocked" class="trf-lf__picker">
                                            <SearchablePickerRedesigned
                                                v-model="group.cardLeg.goods_name"
                                                :items="goodsTypePickerItems"
                                                attach-dropdown-to-body
                                                placeholder="Select goods"
                                                search-placeholder="Search goods…"
                                                empty-message="No goods found."
                                                quick-add-title="Add goods type"
                                                :invalid="Boolean(legFieldError(group.cardIndex, 'goods_name'))"
                                                :error-message="legFieldError(group.cardIndex, 'goods_name')"
                                                :can-quick-add="canQuickAddGoodsType"
                                                :can-view="false"
                                                compact-actions
                                                @quick-add="openQuickAddForLegGoodsType(group.cardIndex)"
                                                @update:model-value="saveProgress"
                                            />
                                        </div>
                                        <div v-else class="trf-ro-val"><span class="trf-ro-val__main">{{ group.cardLeg.goods_name || '—' }}</span></div>
                                    </div>

                                    <div :id="`trf-leg-${group.cardIndex}-goods_value`" class="trf-lf">
                                        <label class="trf-lf__lbl">Goods Value</label>
                                        <div class="trf-num-field trf-num-field--prefix">
                                            <span class="trf-num-field__prefix">₹</span>
                                            <input
                                                v-if="!isFormLocked"
                                                v-model.number="group.cardLeg.goods_value"
                                                v-no-scroll-change
                                                type="number"
                                                min="0"
                                                step="0.01"
                                                class="trf-input"
                                                placeholder="0.00"
                                                @input="saveProgress"
                                            />
                                            <span v-else class="trf-ro-val__main">{{ group.cardLeg.goods_value ? formatMoney(group.cardLeg.goods_value) : '—' }}</span>
                                        </div>
                                    </div>

                                    <div :id="`trf-leg-${group.cardIndex}-no_of_units`" class="trf-lf">
                                        <label class="trf-lf__lbl">No. of Units</label>
                                        <input
                                            v-if="!isFormLocked"
                                            v-model.number="group.cardLeg.no_of_units"
                                            v-no-scroll-change
                                            type="number"
                                            min="0"
                                            max="100000"
                                            step="1"
                                            class="trf-input"
                                            :class="{ 'trf-input--error': legFieldError(group.cardIndex, 'no_of_units') }"
                                            :title="legFieldError(group.cardIndex, 'no_of_units') || undefined"
                                            placeholder="0"
                                            @input="emit('clear-field-error', `route_legs.${group.cardIndex}.no_of_units`); saveProgress()"
                                        />
                                        <span v-else class="trf-ro-val__main">{{ group.cardLeg.no_of_units ?? '—' }}</span>
                                    </div>

                                    <div :id="`trf-leg-${group.cardIndex}-weight_ton`" class="trf-lf">
                                        <label class="trf-lf__lbl">Weight (Ton)<span class="trf-pf__req">*</span></label>
                                        <div class="trf-num-field">
                                            <input
                                                v-if="!isFormLocked"
                                                v-model.number="group.cardLeg.weight_ton"
                                                v-no-scroll-change
                                                type="number"
                                                min="0"
                                                step="0.001"
                                                class="trf-input"
                                                :class="{ 'trf-input--error': legFieldError(group.cardIndex, 'weight_ton') }"
                                                :title="legFieldError(group.cardIndex, 'weight_ton') || undefined"
                                                placeholder="0.000"
                                                @input="onLegWeightInput(group.cardIndex); saveProgress()"
                                            />
                                            <span v-else class="trf-ro-val__main">{{ group.cardLeg.weight_ton ? formatNum(group.cardLeg.weight_ton) : '—' }}</span>
                                        </div>
                                    </div>

                                    <div :id="`trf-leg-${group.cardIndex}-freight_rate`" class="trf-lf">
                                        <label class="trf-lf__lbl">Rate (₹/Ton)</label>
                                        <input
                                            v-if="!isFormLocked"
                                            v-model.number="group.cardLeg.freight_rate"
                                            v-no-scroll-change
                                            type="number"
                                            min="0"
                                            step="0.01"
                                            class="trf-input"
                                            placeholder="0.00"
                                            @input="onLegRateInput(group.cardIndex); saveProgress()"
                                        />
                                        <span v-else class="trf-ro-val__main">{{ group.cardLeg.freight_rate ? '₹' + formatMoney(group.cardLeg.freight_rate) : '—' }}</span>
                                    </div>

                                    <div :id="`trf-leg-${group.cardIndex}-freight_amount`" class="trf-lf trf-lf--freight">
                                        <div class="trf-freight-head">
                                            <label class="trf-lf__lbl">Freight (₹)<span class="trf-pf__req">*</span></label>
                                            <label
                                                v-if="!isFormLocked"
                                                class="trf-subst-toggle"
                                                :class="{ 'is-on': group.cardLeg.freight_auto_calculate }"
                                                :for="`auto-calc-${group.cardIndex}`"
                                            >
                                                <input
                                                    :id="`auto-calc-${group.cardIndex}`"
                                                    v-model="group.cardLeg.freight_auto_calculate"
                                                    type="checkbox"
                                                    class="trf-subst-toggle__input"
                                                    role="switch"
                                                    :aria-checked="group.cardLeg.freight_auto_calculate"
                                                    @change="onLegAutoCalculateChange(group.cardIndex); saveProgress()"
                                                />
                                                <span class="trf-subst-toggle__track" aria-hidden="true">
                                                    <span class="trf-subst-toggle__thumb"></span>
                                                </span>
                                                <span class="trf-subst-toggle__text">Use Rate</span>
                                            </label>
                                        </div>
                                        <div class="trf-num-field trf-num-field--prefix">
                                            <span class="trf-num-field__prefix">₹</span>
                                            <input
                                                v-if="!isFormLocked"
                                                v-model.number="group.cardLeg.freight_amount"
                                                v-no-scroll-change
                                                type="number"
                                                min="0"
                                                step="0.01"
                                                class="trf-input trf-input--freight"
                                                :class="{ 'trf-input--error': legFieldError(group.cardIndex, 'freight_amount') }"
                                                :title="legFieldError(group.cardIndex, 'freight_amount') || undefined"
                                                placeholder="0.00"
                                                :disabled="group.cardLeg.freight_auto_calculate"
                                                @input="onLegFreightManualInput(group.cardIndex); saveProgress()"
                                            />
                                            <span v-else class="trf-ro-val__main trf-ro-val__main--freight">₹{{ formatMoney(group.cardLeg.freight_amount) }}</span>
                                        </div>
                                    </div>
                                    </div>
                                </div>

                                <div class="trf-leg__meta">
                                    <div class="trf-leg__meta-right">
                                        <span class="trf-leg__dist">
                                            E-Way Bill:
                                            <template v-if="!isFormLocked">
                                                <input
                                                    v-model="group.cardLeg.ewaybill_no"
                                                    type="text"
                                                    maxlength="64"
                                                    class="trf-leg__dist-input trf-leg__dist-input--wide"
                                                    placeholder="Optional"
                                                    @input="saveProgress"
                                                />
                                            </template>
                                            <template v-else>{{ group.cardLeg.ewaybill_no || '—' }}</template>
                                        </span>
                                        <span class="trf-leg__dist">
                                            Expiry:
                                            <template v-if="!isFormLocked">
                                                <input
                                                    v-model="group.cardLeg.ewaybill_expiry"
                                                    type="date"
                                                    class="trf-leg__dist-input trf-leg__dist-input--date"
                                                    @change="saveProgress"
                                                />
                                            </template>
                                            <template v-else>{{ group.cardLeg.ewaybill_expiry ? formatDate(group.cardLeg.ewaybill_expiry) : '—' }}</template>
                                        </span>
                                        <span class="trf-leg__dist">
                                            KM/Ltr:
                                            <template v-if="!isFormLocked">
                                                <input
                                                    v-model.number="group.cardLeg.expected_mileage_km_per_liter"
                                                    v-no-scroll-change
                                                    type="number"
                                                    min="0"
                                                    step="0.01"
                                                    class="trf-leg__dist-input"
                                                    placeholder="0.00"
                                                    @input="onLegMileageInput(group.cardIndex); saveProgress()"
                                                />
                                            </template>
                                            <template v-else>{{ group.cardLeg.expected_mileage_km_per_liter || '—' }}</template>
                                        </span>
                                        <span
                                            :id="`trf-leg-${group.cardIndex}-distance_km`"
                                            class="trf-leg__dist"
                                            :class="{ 'trf-leg__dist--error': legFieldError(group.cardIndex, 'distance_km') }"
                                        >
                                            Distance:
                                            <template v-if="!isFormLocked">
                                                <input
                                                    v-model.number="group.cardLeg.distance_km"
                                                    v-no-scroll-change
                                                    type="number"
                                                    min="0"
                                                    step="0.01"
                                                    class="trf-leg__dist-input"
                                                    :class="{ 'trf-leg__dist-input--error': legFieldError(group.cardIndex, 'distance_km') }"
                                                    :title="legFieldError(group.cardIndex, 'distance_km') || undefined"
                                                    placeholder="Optional"
                                                    @input="emit('clear-field-error', `route_legs.${group.cardIndex}.distance_km`); saveProgress()"
                                                />
                                                KM
                                            </template>
                                            <template v-else>{{ group.cardLeg.distance_km ?? '—' }} KM</template>
                                        </span>
                                    </div>
                                </div>
                                </template>
                            </div>
                            <div
                                v-else-if="group.type === 'primary'"
                                class="trf-route-empty-panel"
                            >
                                <div class="trf-route-empty-panel__icon">
                                    <i :class="requiresTruckSelection ? 'bi bi-truck' : 'bi bi-geo-alt'"></i>
                                </div>
                                <p class="trf-route-empty-panel__text">
                                    <template v-if="requiresTruckSelection">
                                        Select a truck first, then choose source and add stops.
                                    </template>
                                    <template v-else-if="!hasStartStation">
                                        Choose source, then click <strong>+ Add Stop</strong>.
                                    </template>
                                    <template v-else-if="!hasRouteStops">
                                        Click <strong>+ Add Stop</strong> to continue the route.
                                    </template>
                                    <template v-else>
                                        Select the stop station to add cargo details.
                                    </template>
                                </p>
                            </div>
                        </div>
                    </div>

                    <button
                        v-if="!isFormLocked"
                        type="button"
                        class="trf-add-stop-row"
                        :disabled="requiresTruckSelection"
                        @click="onAddStopClick"
                    >
                        <i :class="(hasStartStation ? 'bi bi-plus-circle-fill' : 'bi bi-geo-alt-fill') + ' text-success me-2'"></i>
                        <span>{{ addStopActionLabel }}</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- ─────────────────────────────────────────────────────────────────
             SECTION 3 — TRIP SUMMARY
        ───────────────────────────────────────────────────────────────── -->
        <div class="trf-section trf-section--summary">
            <div class="trf-section__head">
                <div class="trf-section__head-left">
                    <div class="trf-step-num trf-step-num--summary">3</div>
                    <div>
                        <h2 class="trf-section__title">Trip Summary</h2>
                        <p class="trf-section__desc">Auto-calculated totals based on route and cargo</p>
                    </div>
                </div>
            </div>
            <div class="trf-section__body trf-summary-compact">
                <TripDieselPerformanceCard :performance="dieselPerformance" compact />

                <div class="trf-summary-metrics">
                    <div class="trf-vehicle-info__item trf-summary-metrics__item">
                        <div class="trf-vehicle-info__icon" style="--ic:#4f46e5;--bg:#eef2ff"><i class="bi bi-geo-alt-fill"></i></div>
                        <div class="trf-summary-metrics__text">
                            <span class="trf-vehicle-info__name">Distance</span>
                            <span class="trf-vehicle-info__val">{{ summary.totalKm ? `${summary.totalKm} KM` : '—' }}</span>
                        </div>
                    </div>
                    <div class="trf-vehicle-info__item trf-summary-metrics__item">
                        <div class="trf-vehicle-info__icon" style="--ic:#0d9488;--bg:#ecfeff"><i class="bi bi-arrows-move"></i></div>
                        <div class="trf-summary-metrics__text">
                            <span class="trf-vehicle-info__name">Running KM (End − Start)</span>
                            <span class="trf-vehicle-info__val">{{ runningKmDisplay }}</span>
                        </div>
                    </div>
                    <div class="trf-vehicle-info__item trf-summary-metrics__item">
                        <div class="trf-vehicle-info__icon" style="--ic:#9333ea;--bg:#faf5ff"><i class="bi bi-signpost-2-fill"></i></div>
                        <div class="trf-summary-metrics__text">
                            <span class="trf-vehicle-info__name">Cost / KM</span>
                            <span class="trf-vehicle-info__val">{{ costPerKmDisplay }}</span>
                        </div>
                    </div>
                    <div class="trf-vehicle-info__item trf-summary-metrics__item">
                        <div class="trf-vehicle-info__icon" style="--ic:#be185d;--bg:#fdf2f8"><i class="bi bi-currency-rupee"></i></div>
                        <div class="trf-summary-metrics__text">
                            <span class="trf-vehicle-info__name">Total Freight</span>
                            <span class="trf-vehicle-info__val">{{ freightTotalDisplay }}</span>
                        </div>
                    </div>
                    <div class="trf-vehicle-info__item trf-summary-metrics__item">
                        <div class="trf-vehicle-info__icon" style="--ic:#f59e0b;--bg:#fffbeb"><i class="bi bi-box-seam"></i></div>
                        <div class="trf-summary-metrics__text">
                            <span class="trf-vehicle-info__name">Load Mix</span>
                            <div class="trf-summary-loadmix">
                                <div
                                    v-for="line in loadMixLines"
                                    :key="line.label"
                                    class="trf-summary-loadmix__line"
                                >
                                    <span class="trf-summary-loadmix__key">{{ line.label }}:</span>
                                    <span class="trf-summary-loadmix__val">{{ line.value }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="trf-vehicle-info__item trf-summary-metrics__item trf-summary-metrics__item--hero">
                        <div class="trf-vehicle-info__icon" style="--ic:#2563eb;--bg:#eff6ff"><i class="bi bi-graph-up-arrow"></i></div>
                        <div class="trf-summary-metrics__text">
                            <span class="trf-vehicle-info__name">Est. Profit</span>
                            <span
                                class="trf-vehicle-info__val"
                                :class="{
                                    'trf-vehicle-info__val--profit': hasFreightSummary && summary.profit >= 0,
                                    'trf-vehicle-info__val--loss': hasFreightSummary && summary.profit < 0,
                                }"
                            >{{ profitDisplay }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ─────────────────────────────────────────────────────────────────
             SECTION 4 — REFERENCE, END METER & NOTES
        ───────────────────────────────────────────────────────────────── -->
        <div class="trf-section trf-section--collapsible" :class="{ 'trf-section--open': showAdvanced }">
            <div class="trf-section__head trf-section__head--plain trf-section__head--toggle" @click="showAdvanced = !showAdvanced">
                <div class="trf-section__head-left">
                    <div class="trf-step-num trf-step-num--gray">4</div>
                    <div>
                        <h2 class="trf-section__title">Reference, End Meter &amp; Notes</h2>
                        <p class="trf-section__desc">End meter reading, optional reference party and trip notes</p>
                    </div>
                </div>
                <div class="trf-section__head-right">
                    <div class="trf-collapse-arrow" :class="{ 'trf-collapse-arrow--open': showAdvanced }">
                        <i class="bi bi-chevron-down"></i>
                    </div>
                </div>
            </div>

            <div v-show="showAdvanced" class="trf-section__body">
                <div class="trf-step4-fields">
                    <div class="trf-pf" :class="{ 'trf-pf--locked': requiresTruckSelection }">
                        <label class="trf-lf__lbl">End Meter Reading</label>
                        <input
                            v-if="!isFormLocked"
                            v-model.number="form.end_meter_reading_km"
                            v-no-scroll-change
                            type="number"
                            min="0"
                            step="0.01"
                            class="trf-input"
                            :class="{ 'trf-input--error': fieldErrors.end_meter_reading_km }"
                            placeholder="0"
                            :disabled="requiresTruckSelection"
                            :title="requiresTruckSelection ? 'Select a truck first' : undefined"
                            @input="emit('clear-field-error', 'end_meter_reading_km'); saveProgress()"
                        />
                        <div v-else class="trf-ro-val"><span class="trf-ro-val__main">{{ endMeterDisplay }}</span></div>
                        <div v-if="fieldErrors.end_meter_reading_km" class="trf-pf__err">{{ fieldErrorMessage('end_meter_reading_km') }}</div>
                    </div>

                    <div class="trf-pf">
                        <label class="trf-lf__lbl">Reference</label>
                        <SearchablePickerRedesigned
                            v-if="!isFormLocked"
                            v-model="form.broker_id"
                            :items="brokers"
                            attach-dropdown-to-body
                            placeholder="Select reference"
                            search-placeholder="Search…"
                            empty-message="No reference found."
                            view-title="Reference details"
                            quick-add-title="Add reference"
                            :invalid="Boolean(fieldErrors.broker_id)"
                            :error-message="fieldErrorMessage('broker_id')"
                            :can-quick-add="canQuickAddBroker"
                            @quick-add="showBrokerQuickAdd = true"
                            @update:model-value="saveProgress"
                        />
                        <div v-else class="trf-ro-val"><span class="trf-ro-val__main">{{ partyLabel(brokers, form.broker_id, snapshotBrokerName) }}</span></div>
                        <div v-if="fieldErrors.broker_id" class="trf-pf__err">{{ fieldErrorMessage('broker_id') }}</div>
                    </div>

                    <div class="trf-pf">
                        <label class="trf-lf__lbl">Trip Notes</label>
                        <textarea
                            v-if="!isFormLocked"
                            v-model="form.notes"
                            class="trf-input trf-input--textarea trf-input--single-line"
                            rows="1"
                            placeholder="Optional notes, instructions, or remarks for this trip…"
                            @input="saveProgress"
                        ></textarea>
                        <div v-else class="trf-ro-val trf-ro-val--single-line">{{ form.notes && form.notes.trim() ? form.notes.trim() : '—' }}</div>
                    </div>
                </div>

                <div v-if="form.status === 'CANCELLED'" class="trf-step4-cancelled mt-3">
                    <div class="trf-banner trf-banner--error">
                        <i class="bi bi-x-circle-fill"></i>
                        <div>
                            <strong>Trip Cancelled:</strong> {{ formatDate(form.cancelled_at) }}
                            <div v-if="form.cancellation_reason" class="mt-1"><strong>Reason:</strong> {{ form.cancellation_reason }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Add Modals -->
        <StationQuickAddModal
            v-if="showStationQuickAdd"
            :show="showStationQuickAdd"
            @close="showStationQuickAdd = false"
            @created="onStationCreated"
        />
        <CustomerQuickAddModal
            v-if="showCustomerQuickAdd"
            :show="showCustomerQuickAdd"
            :existing-customers="customers"
            @close="showCustomerQuickAdd = false"
            @created="onCustomerCreated"
        />
        <BrokerQuickAddModal
            v-if="showBrokerQuickAdd"
            :show="showBrokerQuickAdd"
            :existing-brokers="brokers"
            @close="showBrokerQuickAdd = false"
            @created="onBrokerCreated"
        />
        <GoodsTypeQuickAddModal
            v-if="showGoodsTypeQuickAdd"
            :show="showGoodsTypeQuickAdd"
            @close="showGoodsTypeQuickAdd = false"
            @created="onGoodsTypeCreated"
        />

        <AppModal
            v-if="showRemoveStopConfirm"
            :show="showRemoveStopConfirm"
            title="Remove Stop"
            @close="cancelRemoveStopConfirm"
        >
            <div class="text-center p-3">
                <div class="trf-modal-warn-icon"><i class="bi bi-exclamation-triangle"></i></div>
                <h5 class="fw-bold mt-3 mb-1">Remove Stop {{ pendingRemoveStopIndex !== null ? pendingRemoveStopIndex + 1 : '' }}?</h5>
                <p class="text-muted small mb-0">
                    {{ pendingRemoveStopLabel }} will be removed and the route will be recalculated.
                </p>
            </div>
            <template #footer>
                <button type="button" class="btn btn-outline-secondary btn-sm" @click="cancelRemoveStopConfirm">Cancel</button>
                <button type="button" class="btn btn-danger btn-sm" @click="confirmRemoveStop">
                    <i class="bi bi-trash3 me-1"></i>Remove Stop
                </button>
            </template>
        </AppModal>

    </div>
</template>

<script setup>
import { computed, nextTick, ref, watch } from 'vue';
import {
    calculateLegFreight, inferTripTypeFromRoute, legLoadCategoryMeta, stopHasExpenses,
    summarizeTripForm, tripStatusMeta,
    computeDieselPerformance,
    MAX_DIESEL_RATE_PER_LITER, MAX_EXPECTED_MILEAGE_KM_PER_LITER, MAX_FREIGHT_RATE_PER_TON, MAX_PLANNED_DIESEL_LITERS,
} from '../../api/trips';
import { useAppToast } from '../../composables/useAppToast';
import { calculatePlannedDiesel, calculateExpectedRunningKm } from '../../utils/tripFuelCalculator';
import { dateOnlyFromDatetimeLocal, formatDate } from '../../utils/datetime';
import { formatMeterKm } from '../../utils/formatMeter';

import AppDateInput from '../ui/AppDateInput.vue';
import AppModal from '../ui/AppModal.vue';
import SearchablePickerRedesigned from '../ui/SearchablePickerRedesigned.vue';
import AsyncVehicleTripPickerRedesigned from './AsyncVehicleTripPickerRedesigned.vue';
import TripDieselPerformanceCard from './TripDieselPerformanceCard.vue';
import BrokerQuickAddModal from './BrokerQuickAddModal.vue';
import CustomerQuickAddModal from './CustomerQuickAddModal.vue';
import StationQuickAddModal from './StationQuickAddModal.vue';
import GoodsTypeQuickAddModal from './GoodsTypeQuickAddModal.vue';

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
    goodsTypes: { type: Array, default: () => [] },
    snapshotCustomerName: { type: String, default: '' },
    snapshotBrokerName: { type: String, default: '' },
    formError: { type: String, default: '' },
    fieldErrors: { type: Object, default: () => ({}) },
    tripNumberDisplay: { type: String, default: '' },
    driverWarning: { type: String, default: '' },
    loadingDriver: { type: Boolean, default: false },
    primaryDriverName: { type: String, default: '' },
    displayStatus: { type: String, default: '' },
    readonly: { type: Boolean, default: false },
    showExpenses: { type: Boolean, default: false },
    expenseTotalsBySequence: { type: Object, default: () => ({}) },
    canQuickAddStation: { type: Boolean, default: false },
    canQuickAddBroker: { type: Boolean, default: false },
    canQuickAddCustomer: { type: Boolean, default: false },
    canQuickAddGoodsType: { type: Boolean, default: false },
    vehicleOdometerKm: { type: [Number, String], default: '' },
    vehicleDefaultMileage: { type: [Number, String], default: '' },
    vehicleExpectedMileage: { type: [Number, String], default: '' },
    vehicleMaxLoad: { type: [Number, String], default: '' },
    vehicleFuelTankCapacity: { type: [Number, String], default: '' },
    vehicleMake: { type: String, default: '' },
    vehicleModel: { type: String, default: '' },
});

const emit = defineEmits([
    'add-stop','return-to-start','remove-route-leg','add-expense','remove-expense',
    'vehicle-selected','start-station-change','stop-change',
    'station-created','broker-created','customer-created','goods-type-created',
    'clear-field-error','trip-sequence-change',
]);

function onTripSequenceInput(event) {
    const raw = String(event.target.value ?? '').replace(/\D/g, '');
    props.form.trip_sequence = raw;
    emit('clear-field-error', 'trip_sequence');
    emit('trip-sequence-change');
}

const toast = useAppToast();
const maxExpectedMileage = MAX_EXPECTED_MILEAGE_KM_PER_LITER;
const maxFreightRate = MAX_FREIGHT_RATE_PER_TON;
const maxPlannedDieselLiters = MAX_PLANNED_DIESEL_LITERS;
const maxDieselRatePerLiter = MAX_DIESEL_RATE_PER_LITER;

const showAdvanced = ref(false);
const showSubstituteDriver = ref(false);
const showStationQuickAdd = ref(false);
const showBrokerQuickAdd = ref(false);
const showCustomerQuickAdd = ref(false);
const expandedStopIndex = ref(0);

const LEG_FIELD_LABELS = {
    to_station_id: 'Station',
    customer_id: 'Customer',
    consignor_id: 'Consignor (From)',
    consignee_id: 'Consignee (To)',
    pickup_date: 'Pickup Date',
    goods_name: 'Goods Type',
    goods_value: 'Goods Value',
    no_of_units: 'No. of Units',
    weight_ton: 'Weight',
    freight_rate: 'Rate',
    freight_amount: 'Freight',
    distance_km: 'Distance',
};
const showGoodsTypeQuickAdd = ref(false);
const showRemoveStopConfirm = ref(false);
const quickAddLegIndex = ref(null);
const quickAddCustomerLegIndex = ref(null);
const quickAddCustomerField = ref('customer');
const quickAddGoodsTypeLegIndex = ref(null);
const pendingRemoveStopIndex = ref(null);
const routeEndedOneWay = ref(false);

const isFormLocked = computed(() => props.readonly);
const hasTruckSelected = computed(() => Boolean(String(props.form.vehicle_id ?? '').trim()));
const requiresTruckSelection = computed(() => props.mode === 'create' && !hasTruckSelected.value && !isFormLocked.value);
const goodsTypePickerItems = computed(() => {
    const names = new Set();
    const items = [];
    (props.goodsTypes ?? []).forEach((t) => { const n = String(t?.name ?? '').trim(); if (!n || names.has(n)) return; names.add(n); items.push({ id: n, name: n }); });
    (props.form.route_legs ?? []).forEach((leg) => { const n = String(leg?.goods_name ?? '').trim(); if (!n || names.has(n)) return; names.add(n); items.push({ id: n, name: n }); });
    return items;
});
const summary = computed(() => summarizeTripForm(props.form));
const dieselPerformance = computed(() =>
    computeDieselPerformance({
        plannedMileageKmPerLiter: props.form.planned_mileage_km_per_liter,
        plannedDieselLiters: props.form.planned_diesel_liters,
        dieselRatePerLiter: props.form.diesel_rate_per_liter,
        actualTotalKm: summary.value.totalKm,
    }),
);
const expectedMileageDisplay = computed(() => summary.value.expectedMileage == null ? '—' : formatNum(summary.value.expectedMileage) + ' KM/Ltr');
const tripStartDate = computed(() => dateOnlyFromDatetimeLocal(props.form.start_at));
const tripEndDate = computed(() => dateOnlyFromDatetimeLocal(props.form.end_at));
const arrivalTiming = computed(() => { const e = tripEndDate.value; const a = dateOnlyFromDatetimeLocal(props.form.actual_arrival_at); if (!e || !a) return null; if (e === a) return 'on-time'; return e > a ? 'early' : 'late'; });
const effectiveTripStatus = computed(() => props.displayStatus || props.form.status);
const isStartMeterOptional = computed(() => {
    if (props.mode === 'create') {
        return true;
    }

    return String(effectiveTripStatus.value ?? '').toUpperCase() === 'PENDING';
});
const currentStatusBadge = computed(() => tripStatusMeta(effectiveTripStatus.value));
const statusPillClass = computed(() => {
    const s = String(effectiveTripStatus.value ?? '').toUpperCase();
    if (s === 'IN_PROGRESS') return 'trf-pill--orange';
    if (s === 'COMPLETED' || s === 'INVOICED') return 'trf-pill--green';
    if (s === 'CANCELLED') return 'trf-pill--red';
    return 'trf-pill--blue';
});
const vehicleDefaultMileageResolved = computed(() => props.vehicleDefaultMileage || props.vehicleExpectedMileage || '');
const selectedDriver = computed(() => props.drivers.find((d) => String(d.id) === String(props.form.driver_id)) ?? null);
const selectedCustomer = computed(() => props.customers.find((c) => String(c.id) === String(props.form.customer_id)) ?? null);
const driverDisplayName = computed(() => { if (props.mode === 'edit') return props.snapshotDriverName || '—'; return props.primaryDriverName || (props.loadingDriver ? 'Loading…' : '—'); });
const hasDriverDisplay = computed(() => {
    const name = String(driverDisplayName.value ?? '').trim();

    return Boolean(name) && name !== '—' && name !== 'Loading…';
});
const primaryDriverId = computed(() => props.form.driver_id ?? '');
const hasPrimaryDriver = computed(() => Boolean(String(primaryDriverId.value).trim()));
const showSubstituteDriverColumn = computed(() => (isFormLocked.value && Boolean(props.form.substitute_driver_id)) || (!isFormLocked.value && hasPrimaryDriver.value && showSubstituteDriver.value));
const substituteDriverOptions = computed(() => props.drivers.filter((d) => String(d.id) !== String(primaryDriverId.value)));
const substituteDriverDisplay = computed(() => {
    if (!props.form.substitute_driver_id) {
        return '';
    }

    const match = props.drivers.find((driver) => String(driver.id) === String(props.form.substitute_driver_id));

    return match?.name ?? props.snapshotSubstituteDriverName ?? '—';
});
function legHasStationSelected(leg) {
    return Boolean(String(leg?.to_station_id ?? '').trim());
}
const routeGroups = computed(() => {
    const legs = props.form.route_legs ?? [];
    const groups = [];
    const primaryStations = [{
        id: 'home',
        kind: 'home',
        leg: null,
        legIndex: -1,
        showLine: legs.length > 0,
    }];

    if (legs[0]) {
        primaryStations.push({
            id: 'stop-0',
            kind: 'stop',
            leg: legs[0],
            legIndex: 0,
            showLine: legs.length > 1,
        });
    }

    groups.push({
        id: 'primary',
        type: 'primary',
        stations: primaryStations,
        cardLeg: legs[0] && legHasStationSelected(legs[0]) ? legs[0] : null,
        cardIndex: 0,
    });

    legs.slice(1).forEach((leg, offset) => {
        const index = offset + 1;
        groups.push({
            id: `stop-group-${index}`,
            type: 'stop',
            stations: [{
                id: `stop-${index}`,
                kind: 'stop',
                leg,
                legIndex: index,
                showLine: index < legs.length - 1,
            }],
            cardLeg: legHasStationSelected(leg) ? leg : null,
            cardIndex: index,
        });
    });

    return groups;
});
const hasRouteStops = computed(() => (props.form.route_legs ?? []).length > 0);
const isRoundTrip = computed(() => String(props.form.trip_type ?? 'ONE_WAY').toUpperCase() !== 'ONE_WAY');
const lastRouteLeg = computed(() => {
    const legs = props.form.route_legs ?? [];

    return legs.length > 0 ? legs[legs.length - 1] : null;
});
const lastStopStationId = computed(() => lastRouteLeg.value?.to_station_id ?? '');
const lastStopStationName = computed(() => stationLabel(lastStopStationId.value));
const returnsToSource = computed(() => {
    if (!props.form.start_station_id || !lastStopStationId.value) {
        return false;
    }

    return String(lastStopStationId.value) === String(props.form.start_station_id);
});
const inferredTripTypeLabel = computed(() => {
    if (!props.form.start_station_id || !(props.form.route_legs?.length) || !lastStopStationId.value) {
        return '';
    }

    return isRoundTrip.value ? 'Round trip' : 'One way';
});
const inferredTripTypeHint = computed(() => {
    if (!inferredTripTypeLabel.value) {
        return '';
    }

    if (isRoundTrip.value) {
        return 'Last stop is back at the start station';
    }

    return `Trip ends at ${lastStopStationName.value}`;
});
const canReturnToSource = computed(() => {
    if (isFormLocked.value || requiresTruckSelection.value) {
        return false;
    }

    if (props.mode === 'edit' && !isRoundTrip.value) {
        return false;
    }

    if (!props.form.start_station_id || !lastStopStationId.value) {
        return false;
    }

    return !returnsToSource.value;
});
const canEndTrip = computed(() => {
    if (isFormLocked.value || props.mode !== 'create' || routeEndedOneWay.value || requiresTruckSelection.value) {
        return false;
    }

    if (!props.form.start_station_id || !lastStopStationId.value || returnsToSource.value) {
        return false;
    }

    return true;
});
const hasStartStation = computed(() => Boolean(String(props.form.start_station_id ?? '').trim()));
const addStopActionLabel = computed(() => {
    if (requiresTruckSelection.value) {
        return 'Select Truck';
    }

    return hasStartStation.value ? 'Add stop' : 'Select Source';
});
const addStopActionTitle = computed(() => {
    if (requiresTruckSelection.value) {
        return 'Select a truck first';
    }

    return hasStartStation.value
        ? 'Add the next stop on this trip'
        : 'First choose the trip source station';
});
const truckRunningMeterDisplay = computed(() => formatMeterKm(props.vehicleOdometerKm));
const startMeterDisplay = computed(() => { const v = props.form.start_meter_reading_km; if (v === '' || v == null) return '—'; const r = Number(v); return Number.isFinite(r) && r > 0 ? formatMeterKm(r) : '—'; });
const endMeterDisplay = computed(() => formatMeterKm(props.form.end_meter_reading_km));
const fuelTankCapacityLtr = ref(0);
const fuelAvgDisplay = computed(() => {
    const v = props.form.planned_mileage_km_per_liter;

    if (v === '' || v == null) {
        return '0';
    }

    const n = Number(v);

    return Number.isFinite(n) ? formatNum(n) : '0';
});
const fuelTankCapacityDisplay = computed(() => {
    const capacity = Number(fuelTankCapacityLtr.value ?? 0);

    return Number.isFinite(capacity) && capacity > 0 ? formatNum(capacity) : '—';
});
const expectedDieselDisplay = computed(() => {
    const v = props.form.planned_diesel_liters;

    if (v === '' || v == null) {
        return '0';
    }

    const n = Number(v);

    return Number.isFinite(n) ? formatNum(n) : '0';
});
const startMeterWarning = computed(() => { if (isFormLocked.value) return false; const s = Number(props.form.start_meter_reading_km ?? 0), t = Number(props.vehicleOdometerKm ?? 0); return Number.isFinite(s) && s > 0 && Number.isFinite(t) && t > 0 && s < t; });
const validationMessages = computed(() => {
    const msgs = [];
    Object.entries(props.fieldErrors).forEach(([k, v]) => {
        if (k.startsWith('route_legs.')) {
            return;
        }
        const m = Array.isArray(v) ? v : [v];
        m.forEach((x) => {
            if (x) {
                msgs.push(x);
            }
        });
    });
    return [...new Set(msgs)];
});

const routeErrorChips = computed(() => {
    const chips = [];
    const seenMessages = new Set();
    Object.entries(props.fieldErrors).forEach(([key, val]) => {
        const match = key.match(/^route_legs\.(\d+)\.(\w+)$/);
        if (!match) {
            return;
        }
        const message = Array.isArray(val) ? val[0] : val;
        if (!message) {
            return;
        }
        const text = String(message);
        if (seenMessages.has(text)) {
            return;
        }
        seenMessages.add(text);
        const stopIndex = Number(match[1]);
        const field = match[2];
        chips.push({
            key,
            stopIndex,
            field,
            label: text,
            message: text,
        });
    });
    return chips;
});

const bannerMessage = computed(() => {
    if (props.formError) {
        return props.formError;
    }

    if (validationMessages.value.length > 0) {
        return validationMessages.value[0];
    }

    if (routeErrorChips.value.length === 1) {
        return routeErrorChips.value[0].message;
    }

    return '';
});

function isStopExpanded(index) {
    if (isFormLocked.value) {
        return true;
    }
    return expandedStopIndex.value === index;
}

function toggleStopExpanded(index) {
    if (isFormLocked.value) {
        return;
    }
    expandedStopIndex.value = expandedStopIndex.value === index ? -1 : index;
}

function expandStop(index) {
    expandedStopIndex.value = index;
}

function stopHasFieldErrors(index) {
    const prefix = `route_legs.${index}.`;
    return Object.keys(props.fieldErrors).some((key) => key.startsWith(prefix));
}

function stopSummaryCustomer(leg) {
    if (leg?.use_same_customer) {
        return customerLabel(leg.customer_id, props.snapshotCustomerName) || 'Trip customer';
    }
    return customerLabel(leg?.customer_id, props.snapshotCustomerName) || 'No customer';
}

function stopRouteLabel(leg, index) {
    const fromStationId =
        leg?.from_station_id
        || (index === 0 ? props.form.start_station_id : props.form.route_legs[index - 1]?.to_station_id);
    const fromLabel = stationLabel(fromStationId) || 'Source';
    const toLabel = stationLabel(leg?.to_station_id) || 'Destination';

    return `${fromLabel} → ${toLabel}`;
}

function focusRouteError(chip) {
    expandStop(chip.stopIndex);
    nextTick(() => {
        const target =
            document.getElementById(`trf-leg-${chip.stopIndex}-${chip.field}`)
            || document.getElementById(`trf-leg-card-${chip.stopIndex}`);
        target?.scrollIntoView({ behavior: 'smooth', block: 'center' });
    });
}

watch(
    () => (props.form.route_legs ?? []).length,
    (length, previous) => {
        if (length > previous) {
            expandedStopIndex.value = Math.max(length - 1, 0);
        } else if (expandedStopIndex.value >= length) {
            expandedStopIndex.value = Math.max(length - 1, 0);
        }
    },
);

watch(
    routeErrorChips,
    (chips, previous) => {
        if (chips.length > 0 && (!previous || previous.length === 0)) {
            expandStop(chips[0].stopIndex);
            nextTick(() => {
                focusRouteError(chips[0]);
            });
        }
    },
);

const dieselOverCapacityWarning = computed(() => {
    const planned = Number(props.form.planned_diesel_liters ?? 0);
    const capacity = Number(fuelTankCapacityLtr.value ?? 0);
    if (planned > 0 && capacity > 0 && planned > capacity) {
        const refuelStops = Math.ceil(planned / capacity) - 1;
        return `Expected diesel (${formatNum(planned)} Ltr) exceeds tank capacity (${formatNum(capacity)} Ltr). Trip may require ${refuelStops} refueling stop${refuelStops > 1 ? 's' : ''}.`;
    }
    return null;
});

const overloadedLegs = computed(() => {
    const maxLoad = Number(props.form.max_load_ton ?? 0);
    if (maxLoad <= 0) {
        return [];
    }
    return (props.form.route_legs ?? [])
        .map((leg, index) => ({ index, weight: Number(leg.weight_ton ?? 0) }))
        .filter(({ weight }) => weight > maxLoad);
});

const overloadWarning = computed(() => {
    if (overloadedLegs.value.length === 0) {
        return null;
    }
    const maxLoad = Number(props.form.max_load_ton ?? 0);
    const stops = overloadedLegs.value.map((l) => `Stop ${l.index + 1} (${formatNum(l.weight)} Ton)`).join(', ');
    return `Load exceeds max capacity (${formatNum(maxLoad)} Ton): ${stops}. Verify weight before proceeding.`;
});

const estimatedDurationHours = computed(() => {
    const km = summary.value.totalKm;
    const avgSpeedKmH = 40;
    if (km > 0) {
        return Math.ceil(km / avgSpeedKmH);
    }
    return null;
});

const estimatedDurationDisplay = computed(() => {
    const hours = estimatedDurationHours.value;
    if (hours === null) {
        return '—';
    }
    if (hours >= 24) {
        const days = Math.floor(hours / 24);
        const remainingHours = hours % 24;
        return remainingHours > 0 ? `${days}d ${remainingHours}h` : `${days}d`;
    }
    return `${hours} hrs`;
});

const costPerKm = computed(() => {
    const totalKm = summary.value.totalKm;
    const dieselCost = Number(props.form.planned_diesel_liters ?? 0) * Number(props.form.diesel_rate_per_liter ?? 0);
    if (totalKm > 0 && dieselCost > 0) {
        return dieselCost / totalKm;
    }
    return null;
});

const costPerKmDisplay = computed(() => {
    const cost = costPerKm.value;
    if (cost === null) {
        return '—';
    }
    return '₹' + formatMoney(cost);
});

const endMeterProvidedForRunningKm = computed(() => {
    const v = props.form.end_meter_reading_km;

    if (v === '' || v == null) {
        return false;
    }

    return String(v).trim() !== '';
});

const runningKmDisplay = computed(() => {
    if (!endMeterProvidedForRunningKm.value) {
        return '—';
    }

    return `${formatNum(summary.value.meterKm)} KM`;
});

const loadMixLines = computed(() => [
    { label: 'Full', value: summary.value.fullLoadStops ?? 0 },
    { label: 'Half', value: summary.value.halfLoadStops ?? 0 },
    { label: 'Empty', value: summary.value.emptyStops ?? 0 },
]);

const hasFreightSummary = computed(() => Number(summary.value.freightTotal ?? 0) > 0);

const freightTotalDisplay = computed(() => {
    if (!hasFreightSummary.value) {
        return '—';
    }
    return '₹' + formatMoney(summary.value.freightTotal);
});

const profitDisplay = computed(() => {
    if (!hasFreightSummary.value) {
        return '—';
    }
    return '₹' + formatMoney(summary.value.profit);
});

const driverLicenseWarning = computed(() => {
    const driver = selectedDriver.value;
    if (!driver || !driver.license_expiry_date) {
        return null;
    }
    const tripStart = tripStartDate.value;
    if (!tripStart) {
        return null;
    }
    const expiryDate = new Date(driver.license_expiry_date);
    const startDate = new Date(tripStart);
    const diffDays = Math.ceil((expiryDate - startDate) / (1000 * 60 * 60 * 24));
    if (diffDays < 0) {
        return `Driver's license has expired on ${formatDate(driver.license_expiry_date)}. Please assign a different driver or renew license before starting the trip.`;
    }
    if (diffDays <= 30) {
        return `Driver's license expires in ${diffDays} day${diffDays !== 1 ? 's' : ''} (${formatDate(driver.license_expiry_date)}). Consider renewing before this trip.`;
    }
    return null;
});

function legLoadMeta(w) { return legLoadCategoryMeta(w, props.form.max_load_ton); }
function isLegOverloaded(leg) {
    const maxLoad = Number(props.form.max_load_ton ?? 0);
    const weight = Number(leg?.weight_ton ?? 0);

    return maxLoad > 0 && weight > maxLoad;
}
function isEmptyReturnLeg(leg, index) {
    if (!isRoundTrip.value) {
        return false;
    }

    const weight = Number(leg?.weight_ton ?? 0);
    const isLast = index === props.form.route_legs.length - 1;
    const returnToStart = String(leg?.to_station_id ?? '') === String(props.form.start_station_id ?? '');

    return isLast && returnToStart && weight === 0;
}
function isStopRemoveBlocked(i) { return stopHasExpenses(props.expenseTotalsBySequence, i + 1); }
function stopRemoveTitle(i) { if (!isStopRemoveBlocked(i)) return 'Remove stop'; const t = props.expenseTotalsBySequence[i + 1] ?? 0; return 'Stop ' + (i + 1) + ' has expenses. Clear them first.'; }
const pendingRemoveStopLabel = computed(() => {
    if (pendingRemoveStopIndex.value === null) {
        return 'This stop';
    }

    const leg = props.form.route_legs[pendingRemoveStopIndex.value];
    const stationName = stationLabel(leg?.to_station_id);

    return stationName && stationName !== '—'
        ? `Stop ${pendingRemoveStopIndex.value + 1} (${stationName})`
        : `Stop ${pendingRemoveStopIndex.value + 1}`;
});
function openRemoveStopConfirm(i) { if (isStopRemoveBlocked(i)) { const t = props.expenseTotalsBySequence[i + 1] ?? 0; toast.error('Stop ' + (i + 1) + ' has ₹' + formatMoney(t) + ' expenses. Remove them first.'); return; } pendingRemoveStopIndex.value = i; showRemoveStopConfirm.value = true; }
function cancelRemoveStopConfirm() { pendingRemoveStopIndex.value = null; showRemoveStopConfirm.value = false; }
function confirmRemoveStop() {
    const i = pendingRemoveStopIndex.value;
    if (i === null) {
        cancelRemoveStopConfirm();
        return;
    }
    cancelRemoveStopConfirm();
    emit('remove-route-leg', i);
    routeEndedOneWay.value = false;
    toast.success('Stop removed.');
    saveProgress();
}
function applyLegFreightAuto(i) { const leg = props.form.route_legs[i]; if (!leg || !leg.freight_auto_calculate) return; leg.freight_amount = calculateLegFreight(leg.weight_ton, leg.freight_rate); }
function onLegWeightInput(i) { applyLegFreightAuto(i); emit('clear-field-error', 'route_legs.' + i + '.weight_ton'); emit('clear-field-error', 'route_legs.' + i + '.freight_amount'); }
function onLegMileageInput(i) { const leg = props.form.route_legs[i]; if (leg) leg.expected_mileage_km_per_liter = clamp(leg.expected_mileage_km_per_liter, maxExpectedMileage); emit('clear-field-error', 'route_legs.' + i + '.expected_mileage_km_per_liter'); }
function onLegRateInput(i) { const leg = props.form.route_legs[i]; if (leg) leg.freight_rate = clamp(leg.freight_rate, maxFreightRate); applyLegFreightAuto(i); emit('clear-field-error', 'route_legs.' + i + '.freight_rate'); }
function onLegAutoCalculateChange(i) { if (props.form.route_legs[i]?.freight_auto_calculate) applyLegFreightAuto(i); }
function onLegFreightManualInput(i) { const leg = props.form.route_legs[i]; if (leg) leg.freight_auto_calculate = false; }
function onStopWiseCustomerToggle(i, event) {
    const leg = props.form.route_legs[i];

    if (!leg) {
        return;
    }

    const stopWiseEnabled = Boolean(event.target.checked);
    leg.use_same_customer = !stopWiseEnabled;

    if (stopWiseEnabled) {
        leg.customer_id = '';
    } else {
        leg.customer_id = props.form.customer_id ?? '';
    }

    emit('clear-field-error', `route_legs.${i}.customer_id`);
    saveProgress();
}
function syncAutoPlannedDiesel() {
    if (props.form.planned_diesel_manually_set || isFormLocked.value) {
        return;
    }

    let runningKm = calculateExpectedRunningKm(
        props.form.start_meter_reading_km,
        props.form.end_meter_reading_km,
    );

    if (runningKm === null && summary.value.totalKm > 0) {
        runningKm = summary.value.totalKm;
    }

    if (runningKm === null) {
        return;
    }

    const plannedDiesel = calculatePlannedDiesel(runningKm, props.form.planned_mileage_km_per_liter);
    if (plannedDiesel !== null) {
        props.form.planned_diesel_liters = clamp(plannedDiesel, maxPlannedDieselLiters);
    }
}
function onPlannedMileageInput() { props.form.planned_mileage_km_per_liter = clamp(props.form.planned_mileage_km_per_liter, maxExpectedMileage); emit('clear-field-error', 'planned_mileage_km_per_liter'); syncAutoPlannedDiesel(); saveProgress(); }
function onPlannedDieselInput() {
    props.form.planned_diesel_manually_set = true;
    props.form.planned_diesel_liters = clamp(props.form.planned_diesel_liters, maxPlannedDieselLiters);
    emit('clear-field-error', 'planned_diesel_liters');
    saveProgress();
}
function onDieselRateInput() {
    props.form.diesel_rate_per_liter = clamp(props.form.diesel_rate_per_liter, maxDieselRatePerLiter);
    emit('clear-field-error', 'diesel_rate_per_liter');
    saveProgress();
}
watch(
    () => [
        props.form.start_meter_reading_km,
        props.form.end_meter_reading_km,
        props.form.planned_mileage_km_per_liter,
        summary.value.totalKm,
    ],
    syncAutoPlannedDiesel,
);
watch(() => vehicleDefaultMileageResolved.value, (v) => { if (props.mode !== 'create' || v === '' || v == null) return; const m = Number(v); if (!Number.isFinite(m) || m < 0) return; props.form.planned_mileage_km_per_liter = m; syncAutoPlannedDiesel(); });
watch(() => props.vehicleMaxLoad, (v) => { if (props.mode !== 'create' || v === '' || v == null) return; const m = Number(v); if (!Number.isFinite(m) || m < 0) return; props.form.max_load_ton = m; });
watch(
    () => props.vehicleFuelTankCapacity,
    (v) => {
        if (v === '' || v == null) {
            fuelTankCapacityLtr.value = 0;

            return;
        }

        const capacity = Number(v);

        if (!Number.isFinite(capacity) || capacity < 0) {
            return;
        }

        fuelTankCapacityLtr.value = capacity;
    },
    { immediate: true },
);
watch(() => props.form.substitute_driver_id, (v) => { if (v) showSubstituteDriver.value = true; }, { immediate: true });
watch(showSubstituteDriver, (e) => { if (!e && !isFormLocked.value) { props.form.substitute_driver_id = ''; emit('clear-field-error', 'substitute_driver_id'); } });
watch(() => props.form.driver_id, (id) => { if (!id) { showSubstituteDriver.value = false; return; } if (props.form.substitute_driver_id && String(props.form.substitute_driver_id) === String(id)) props.form.substitute_driver_id = ''; });
watch(
    () => [
        props.mode,
        props.form.start_station_id,
        (props.form.route_legs ?? []).map((leg) => String(leg?.to_station_id ?? '')).join('|'),
        (props.form.route_legs ?? []).length,
    ],
    (current, previous) => {
        if (props.mode !== 'create' || isFormLocked.value) {
            return;
        }

        const prevLength = previous?.[3] ?? 0;
        const nextLength = current?.[3] ?? 0;

        if (nextLength !== prevLength) {
            routeEndedOneWay.value = false;
        }

        syncInferredTripType();
    },
);
function clamp(r, max) { if (r === '' || r == null) return r; const v = Number(r); if (!Number.isFinite(v)) return ''; if (v < 0) return 0; return v > max ? max : v; }
function stationLabel(id) { if (!id) return '—'; return props.stations.find((s) => String(s.id) === String(id))?.name ?? ('#' + id); }
function partyLabel(items, id, fallback) { if (!id) return fallback || '—'; return items.find((i) => String(i.id) === String(id))?.name ?? (fallback ?? ('#' + id)); }
function customerLabel(id, fallback) { return String(partyLabel(props.customers, id, fallback)).toLocaleLowerCase().replace(/(^|\s)\S/g, (character) => character.toLocaleUpperCase()); }
function fieldErrorMessage(f) { const e = props.fieldErrors[f]; return Array.isArray(e) ? e[0] : e; }
function legFieldError(i, f) { const e = props.fieldErrors['route_legs.' + i + '.' + f]; return Array.isArray(e) ? e[0] : e; }
function pickupDateMinForStop(index) {
    const startDate = tripStartDate.value || '';
    let previousPickupDate = '';

    for (let previousIndex = index - 1; previousIndex >= 0; previousIndex -= 1) {
        const previousDate = String(props.form.route_legs?.[previousIndex]?.pickup_date ?? '').trim();

        if (/^\d{4}-\d{2}-\d{2}$/.test(previousDate)) {
            previousPickupDate = previousDate;
            break;
        }
    }

    if (startDate && previousPickupDate) {
        return startDate > previousPickupDate ? startDate : previousPickupDate;
    }

    return previousPickupDate || startDate || undefined;
}
function formatNum(v) { return Number(v ?? 0).toLocaleString('en-IN', { maximumFractionDigits: 3 }); }
function formatMoney(v) { return Number(v ?? 0).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }
function openQuickAddForLeg(i) { quickAddLegIndex.value = i; showStationQuickAdd.value = true; }
function openQuickAddForTripCustomer() { quickAddCustomerLegIndex.value = null; quickAddCustomerField.value = 'customer'; showCustomerQuickAdd.value = true; }
function openQuickAddForLegCustomer(i) { quickAddCustomerLegIndex.value = i; quickAddCustomerField.value = 'customer'; showCustomerQuickAdd.value = true; }
function openQuickAddForLegConsignor(i) { quickAddCustomerLegIndex.value = i; quickAddCustomerField.value = 'consignor'; showCustomerQuickAdd.value = true; }
function openQuickAddForLegConsignee(i) { quickAddCustomerLegIndex.value = i; quickAddCustomerField.value = 'consignee'; showCustomerQuickAdd.value = true; }
function openQuickAddForLegGoodsType(i) { quickAddGoodsTypeLegIndex.value = i; showGoodsTypeQuickAdd.value = true; }
function onStationCreated(s) { emit('station-created', { station: s, legIndex: quickAddLegIndex.value }); quickAddLegIndex.value = null; saveProgress(); }
function onBrokerCreated(b) { emit('broker-created', b); saveProgress(); }
function onCustomerCreated(customer) {
    const legIndex = quickAddCustomerLegIndex.value;
    const field = quickAddCustomerField.value;

    if (legIndex !== null && props.form.route_legs[legIndex]) {
        if (field === 'consignor') {
            props.form.route_legs[legIndex].consignor_id = customer.id;
        } else if (field === 'consignee') {
            props.form.route_legs[legIndex].consignee_id = customer.id;
        }
    }

    emit('customer-created', { customer, legIndex: field === 'customer' ? legIndex : null });
    quickAddCustomerLegIndex.value = null;
    quickAddCustomerField.value = 'customer';
    saveProgress();
}
function onGoodsTypeCreated(g) { emit('goods-type-created', { goodsType: g, legIndex: quickAddGoodsTypeLegIndex.value }); quickAddGoodsTypeLegIndex.value = null; saveProgress(); }
function handleVehicleSelection(v) { emit('vehicle-selected', v); saveProgress(); }
function handleStartStationPickerChange() { emit('start-station-change'); emit('clear-field-error', 'start_station_id'); syncInferredTripType(); saveProgress(); }
function handleLegStopChange(i) { emit('stop-change', i); routeEndedOneWay.value = false; syncInferredTripType(); saveProgress(); }
function handleReturnToSource() {
    if (!canReturnToSource.value) {
        return;
    }

    routeEndedOneWay.value = false;
    emit('return-to-start');
}
function onAddStopClick() {
    routeEndedOneWay.value = false;

    if (requiresTruckSelection.value) {
        toast.info('Select a truck first, then build the route.');
        document.querySelector('.trf-section')?.scrollIntoView({ behavior: 'smooth', block: 'start' });

        return;
    }

    if (!hasStartStation.value) {
        emit('clear-field-error', 'start_station_id');
        focusHomeBasePicker();
        toast.info('Select the source (starting point) first, then add stops.');

        return;
    }

    emit('add-stop');
}
function focusHomeBasePicker() {
    const homeBase = document.querySelector('.trf-route-board__station--home');

    if (homeBase) {
        homeBase.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }

    window.setTimeout(() => {
        const trigger = document.querySelector('.trf-route-board__station--home .redesigned-picker-trigger, .trf-route-board__station--home .picker-trigger');

        if (trigger instanceof HTMLElement) {
            trigger.focus();
            trigger.click();
        }
    }, 250);
}
function handleEndTrip() {
    if (!canEndTrip.value) {
        return;
    }

    routeEndedOneWay.value = true;
    props.form.trip_type = 'ONE_WAY';
    toast.info(`One way trip — ends at ${lastStopStationName.value}.`);
    saveProgress();
}
function syncInferredTripType() {
    if (props.mode !== 'create' || isFormLocked.value) {
        return;
    }

    props.form.trip_type = inferTripTypeFromRoute(props.form.start_station_id, props.form.route_legs);

    if (String(props.form.trip_type) === 'ROUND_TRIP') {
        routeEndedOneWay.value = false;
    }
}
function handleEnterKey(e) {
    const t = e.target;
    if (t.tagName === 'TEXTAREA' || t.tagName === 'BUTTON' || t.type === 'submit') {
        return;
    }
    const all = Array.from(
        document.querySelectorAll('input:not([disabled]):not([readonly]),select:not([disabled])'),
    );
    const idx = all.indexOf(t);
    if (idx > -1 && idx < all.length - 1) {
        all[idx + 1].focus();
    }
}

const TRIP_CREATE_DRAFT_KEY = 'truckbill_trip_create_draft';

function saveProgress() {
    if (props.mode !== 'create') {
        return;
    }

    try {
        localStorage.setItem(TRIP_CREATE_DRAFT_KEY, JSON.stringify(props.form));
    } catch {
        // Ignore quota / private-mode failures.
    }
}

function draftHasContent(draft) {
    if (!draft || typeof draft !== 'object') {
        return false;
    }

    return Boolean(
        draft.vehicle_id
        || draft.driver_id
        || draft.substitute_driver_id
        || draft.customer_id
        || draft.broker_id
        || draft.start_station_id
        || draft.end_at
        || draft.notes?.trim?.()
        || (draft.start_meter_reading_km !== '' && draft.start_meter_reading_km != null)
        || (draft.planned_diesel_liters !== '' && draft.planned_diesel_liters != null)
        || (draft.diesel_rate_per_liter !== '' && draft.diesel_rate_per_liter != null)
        || (Array.isArray(draft.route_legs) && draft.route_legs.length > 0)
        || (Array.isArray(draft.expenses) && draft.expenses.length > 0),
    );
}

function applyProgressDraft(draft) {
    if (!draft || typeof draft !== 'object') {
        return false;
    }

    Object.keys(props.form).forEach((key) => {
        if (!Object.prototype.hasOwnProperty.call(draft, key)) {
            return;
        }

        props.form[key] = draft[key];
    });

    if (props.form.substitute_driver_id) {
        showSubstituteDriver.value = true;
    }

    syncInferredTripType();

    if (
        String(props.form.trip_type) === 'ONE_WAY'
        && Array.isArray(props.form.route_legs)
        && props.form.route_legs.length > 0
    ) {
        const lastStopId = props.form.route_legs[props.form.route_legs.length - 1]?.to_station_id;
        routeEndedOneWay.value = Boolean(lastStopId)
            && String(lastStopId) !== String(props.form.start_station_id ?? '');
    }

    return draftHasContent(draft);
}

function restoreProgressDraft() {
    if (props.mode !== 'create') {
        return false;
    }

    const raw = localStorage.getItem(TRIP_CREATE_DRAFT_KEY);

    if (!raw) {
        return false;
    }

    try {
        const draft = JSON.parse(raw);

        if (!applyProgressDraft(draft)) {
            return false;
        }

        toast.info('Restored unsaved draft.');

        return true;
    } catch {
        return false;
    }
}

function clearProgressDraft() {
    try {
        localStorage.removeItem(TRIP_CREATE_DRAFT_KEY);
    } catch {
        // Ignore storage failures.
    }
}

defineExpose({ restoreProgressDraft, clearProgressDraft, saveProgress });
</script>

<style scoped>
/* ── Tokens ─────────────────────────── */
.trf {
    --radius: 12px;
    --border: #dee2e6;
    --shadow: 0 1px 3px rgba(0,0,0,.07), 0 1px 2px rgba(0,0,0,.04);
    --shadow-md: 0 4px 16px rgba(0,0,0,.08);
    --primary: #0d6efd;
    --primary-dark: #0a58ca;
    --success: #198754;
    --success-dark: #146c43;
    --warning: #fd7e14;
    --text: #212529;
    --muted: #6c757d;
    --danger: #dc3545;
    --fs-title: .9rem;
    --fs-desc: .72rem;
    --fs-label: .68rem;
    --fs-input: .82rem;
    --fs-value: .82rem;
    --fs-meta: .68rem;
    --fs-hint: .66rem;
    --fs-btn: .76rem;
    --fs-metric: .95rem;
    --input-min-h: 40px;
    font-family: system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif;
    color: var(--text);
    font-size: var(--fs-input);
}

/* ── Section Cards ──────────────────── */
.trf-section {
    background: #fff;
    border: 1px solid var(--border);
    border-radius: var(--radius);
    box-shadow: var(--shadow);
    margin-bottom: 20px;
    overflow: hidden;
}
.trf-section__head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 12px 20px;
    border-bottom: 1px solid var(--border);
    background: linear-gradient(135deg, #e7f1ff 0%, #f8f9fa 100%);
    gap: 14px;
    min-height: 62px;
}
.trf-section__head--green { background: linear-gradient(135deg, #d1e7dd 0%, #f8f9fa 100%); border-bottom-color: #badbcc; }
.trf-section__head--plain { background: #fafafa; }
.trf-section__head--toggle { cursor: pointer; user-select: none; }
.trf-section__head--toggle:hover { background: #f3f4f6; }
.trf-section__head-left {
    display: flex;
    align-items: center;
    gap: 14px;
    min-width: 0;
    flex: 0 1 auto;
}
.trf-section__head-right {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 10px;
    min-width: 0;
    flex: 1 1 auto;
    justify-content: flex-end;
}
.trf-section__title { font-size: var(--fs-title); font-weight: 700; color: var(--text); margin: 0; line-height: 1.3; }
.trf-section__desc { font-size: var(--fs-desc); color: var(--muted); margin: 2px 0 0; }
.trf-section__body { padding: 18px 20px; }

/* ── Step Numbers ───────────────────── */
.trf-step-num {
    width: 32px; height: 32px; border-radius: 50%;
    background: var(--primary); color: #fff;
    font-weight: 800; font-size: .8rem;
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0;
}
.trf-step-num--green { background: var(--success); }
.trf-step-num--summary { background: var(--warning); }
.trf-step-num--gray { background: #6c757d; }

/* ── Picker Grids ──────────────────── */
.trf-picker-grid {
    display: grid;
    grid-template-columns: repeat(5, minmax(0, 1fr));
    gap: 16px;
}
.trf-picker-grid--5 { grid-template-columns: repeat(5, minmax(0, 1fr)); }
.trf-picker-grid--4 { grid-template-columns: repeat(4, minmax(0, 1fr)); }
.trf-picker-grid--3 { grid-template-columns: repeat(3, minmax(0, 1fr)); }
.trf-picker-grid--6 { grid-template-columns: repeat(6, minmax(0, 1fr)); }
.trf-picker-grid--details {
    grid-template-columns:
        minmax(0, 1.15fr)
        minmax(0, 1.45fr)
        minmax(0, 1.55fr)
        minmax(8.5rem, 10rem)
        minmax(8.5rem, 10rem);
    align-items: start;
    --details-label-h: 28px;
    --details-control-h: 52px;
}
.trf-picker-grid--details > .trf-pf > .trf-pf__lbl {
    height: var(--details-label-h);
    min-height: var(--details-label-h);
}
.trf-picker-grid--details .trf-synced-field,
.trf-picker-grid--details .trf-pf :deep(.redesigned-picker-trigger),
.trf-picker-grid--details .trf-date-input :deep(.app-date-input__text) {
    height: var(--details-control-h);
    min-height: var(--details-control-h) !important;
    max-height: var(--details-control-h);
    box-sizing: border-box;
}
.trf-picker-grid--details .trf-synced-field {
    padding-top: 7px;
    padding-bottom: 7px;
}
.trf-picker-grid--details .trf-pf :deep(.redesigned-picker-trigger) {
    padding-top: 7px !important;
    padding-bottom: 7px !important;
}
.trf-picker-grid--details .trf-date-input :deep(.app-date-input__text) {
    display: flex;
    align-items: center;
}
.trf-pf--date {
    max-width: 10rem;
}
.trf-pf--date .trf-date-input :deep(.app-date-input__text) {
    padding-left: 10px;
    padding-right: 34px;
    font-size: var(--fs-input);
}
.trf-pf--customer {
    min-width: 0;
}
.trf-pf--driver {
    min-width: 0;
}
.trf-pf__lbl--driver {
    justify-content: flex-start;
}
.trf-pf__lbl--driver .trf-subst-toggle {
    margin-left: 0;
}
.trf-driver-fields {
    display: flex;
    min-width: 0;
}
.trf-driver-fields > * {
    flex: 1 1 100%;
    min-width: 0;
}
.trf-pf--customer :deep(.picker-trigger-main-text) {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
@media (max-width: 1399px) {
    .trf-picker-grid--4 { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    .trf-picker-grid--5 { grid-template-columns: repeat(3, minmax(0, 1fr)); }
    .trf-picker-grid--6 { grid-template-columns: repeat(3, minmax(0, 1fr)); }
    .trf-picker-grid--details {
        grid-template-columns:
            minmax(0, 1fr)
            minmax(0, 1fr)
            minmax(0, 1.4fr)
            minmax(8rem, 9.5rem)
            minmax(8rem, 9.5rem);
    }
}
@media (max-width: 1199px) {
    .trf-picker-grid,
    .trf-picker-grid--4,
    .trf-picker-grid--5 { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    .trf-picker-grid--details {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
    .trf-pf--date {
        max-width: none;
    }
}
@media (max-width: 767px)  {
    .trf-picker-grid,
    .trf-picker-grid--3,
    .trf-picker-grid--4,
    .trf-picker-grid--5,
    .trf-picker-grid--6,
    .trf-picker-grid--details { grid-template-columns: repeat(2, minmax(0, 1fr)); }
}
@media (max-width: 480px)  {
    .trf-picker-grid,
    .trf-picker-grid--3,
    .trf-picker-grid--4,
    .trf-picker-grid--5,
    .trf-picker-grid--6,
    .trf-picker-grid--details { grid-template-columns: 1fr; }
}

/* ── Picker Field ───────────────────── */
.trf-pf {
    display: flex;
    flex-direction: column;
    gap: 6px;
    min-width: 0;
    max-width: 100%;
}
.trf-pf__lbl {
    display: flex; align-items: center; gap: 7px;
    min-height: 20px;
    font-size: var(--fs-label); font-weight: var(--form-label-weight, 600); color: var(--form-label-color, #475569);
    text-transform: none;
    letter-spacing: 0;
}
.trf-pf__lbl-text {
    display: inline-flex;
    align-items: baseline;
    gap: 1px;
    min-width: 0;
}
.trf-pf__lbl-icon {
    width: 22px; height: 22px; border-radius: 6px;
    display: flex; align-items: center; justify-content: center;
    font-size: .75rem; flex-shrink: 0;
}
.trf-pf__lbl-icon--blue   { background: #eff6ff; color: #2563eb; }
.trf-pf__lbl-icon--indigo { background: #eef2ff; color: #4f46e5; }
.trf-pf__lbl-icon--purple { background: #faf5ff; color: #7c3aed; }
.trf-pf__lbl-icon--orange { background: #fff7ed; color: #ea580c; }
.trf-pf__lbl-icon--teal   { background: #f0fdfa; color: #0d9488; }
.trf-pf__lbl-icon--slate  { background: #f1f5f9; color: #475569; }
.trf-pf__req {
    color: inherit;
    font-size: inherit;
    font-weight: inherit;
    line-height: 1;
    margin: 0;
}
.trf-pf__hint { font-size: var(--fs-hint); color: var(--muted); margin-top: 4px; line-height: 1.35; }
.trf-pf__meta {
    display: flex;
    flex-wrap: wrap;
    align-items: baseline;
    gap: 4px 6px;
    margin-top: 5px;
    line-height: 1.3;
}
.trf-pf__meta-label {
    font-size: var(--fs-meta);
    font-weight: 600;
    color: var(--muted);
}
.trf-pf__meta-val {
    font-size: var(--fs-meta);
    font-weight: 700;
    color: #475569;
}

/* Trip number in section heading */
.trf-trip-id {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: flex-end;
    gap: 6px 10px;
    min-width: 0;
}
.trf-trip-id--head {
    margin: 0;
    padding: 0;
    background: transparent;
    border: none;
    border-radius: 0;
    max-width: min(100%, 420px);
}
.trf-trip-id__meta {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-shrink: 0;
}
.trf-trip-id__label {
    font-size: var(--fs-label);
    font-weight: 700;
    color: #64748b;
    text-transform: uppercase;
    letter-spacing: .04em;
}
.trf-trip-id__value {
    display: flex;
    align-items: stretch;
    min-width: 0;
    width: 220px;
    max-width: 100%;
}
.trf-trip-id__preview {
    flex: 1 1 auto;
    min-width: 0;
    min-height: 34px;
    padding: 6px 10px;
    border: 1.5px solid #e2e8f0;
    border-right: 0;
    border-radius: 8px 0 0 8px;
    background: #fff;
    color: #0f172a;
    font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
    font-size: .78rem;
    font-weight: 600;
    letter-spacing: .01em;
}
.trf-trip-id__preview--static {
    display: inline-flex;
    align-items: center;
    border-right: 1.5px solid #e2e8f0;
    border-radius: 8px;
    max-width: 260px;
}
.trf-trip-id__seq {
    width: 3.75rem;
    flex: 0 0 3.75rem;
    min-height: 34px;
    padding: 6px 4px;
    border: 1.5px solid #e2e8f0;
    border-radius: 0 8px 8px 0;
    background: #fff;
    color: #0f172a;
    font-size: .78rem;
    font-weight: 600;
    text-align: center;
}
.trf-trip-id__seq:focus,
.trf-trip-id__preview:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(13, 110, 253, .12);
    z-index: 1;
}
.trf-trip-id__seq--error {
    border-color: var(--danger);
}
.trf-trip-id__hint {
    flex: 1 1 100%;
    margin: 0;
    font-size: var(--fs-hint);
    color: #64748b;
    line-height: 1.3;
    text-align: right;
}
@media (max-width: 767px) {
    .trf-section__head {
        flex-wrap: wrap;
        align-items: flex-start;
    }
    .trf-section__head-right {
        flex: 1 1 100%;
        justify-content: flex-start;
    }
    .trf-trip-id--head {
        max-width: none;
        flex: 1;
    }
    .trf-trip-id__value {
        width: 100%;
        max-width: 280px;
    }
    .trf-trip-id__hint {
        text-align: left;
    }
}
.trf-trip-num-badge {
    display: inline-flex;
    align-items: center;
    padding: 2px 8px;
    border-radius: 999px;
    font-size: .65rem;
    font-weight: 700;
    letter-spacing: .02em;
    text-transform: none;
}
.trf-trip-num-badge--created {
    background: #cff4fc;
    color: #055160;
}
.trf-trip-num-group {
    display: flex;
    gap: 0;
    min-width: 0;
}
.trf-trip-num-group .trf-input {
    border-top-right-radius: 0;
    border-bottom-right-radius: 0;
    font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
    font-size: .82rem;
    letter-spacing: .01em;
}
.trf-trip-num-group__seq {
    width: 4.25rem;
    flex: 0 0 4.25rem;
    border-top-left-radius: 0 !important;
    border-bottom-left-radius: 0 !important;
    border-left: 0 !important;
    text-align: center;
    padding-left: 6px !important;
    padding-right: 6px !important;
}
.trf-subst-toggle {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    margin-left: auto;
    flex-shrink: 0;
    height: 24px;
    padding: 0 7px 0 3px;
    border: 1.5px solid var(--border);
    border-radius: 6px;
    background: #fff;
    cursor: pointer;
    user-select: none;
    text-transform: none;
    letter-spacing: 0;
    transition: border-color .15s, background .15s, box-shadow .15s;
}
.trf-subst-toggle:hover {
    border-color: #bfdbfe;
    background: #f8fbff;
}
.trf-subst-toggle.is-on {
    border-color: #86b7fe;
    background: #eff6ff;
    box-shadow: 0 0 0 2px rgba(13, 110, 253, .08);
}
.trf-subst-toggle__input {
    position: absolute;
    opacity: 0;
    width: 0;
    height: 0;
    pointer-events: none;
}
.trf-subst-toggle__track {
    position: relative;
    width: 24px;
    height: 14px;
    flex-shrink: 0;
    border-radius: 999px;
    background: #cbd5e1;
    transition: background .15s;
}
.trf-subst-toggle.is-on .trf-subst-toggle__track {
    background: var(--primary);
}
.trf-subst-toggle__thumb {
    position: absolute;
    top: 2px;
    left: 2px;
    width: 10px;
    height: 10px;
    border-radius: 50%;
    background: #fff;
    box-shadow: 0 1px 2px rgba(15, 23, 42, .2);
    transition: transform .15s;
}
.trf-subst-toggle.is-on .trf-subst-toggle__thumb {
    transform: translateX(10px);
}
.trf-subst-toggle__text {
    font-size: var(--fs-meta);
    font-weight: 600;
    color: var(--muted);
    line-height: 1;
    white-space: nowrap;
}
.trf-subst-toggle.is-on .trf-subst-toggle__text {
    color: var(--primary-dark);
}
.trf-subst-toggle__input:focus-visible + .trf-subst-toggle__track {
    outline: 2px solid rgba(13, 110, 253, .35);
    outline-offset: 2px;
}
.trf-field-head .trf-subst-toggle,
.trf-freight-head .trf-subst-toggle {
    margin-left: 0;
}
.trf-pf__err { font-size: var(--fs-hint); color: var(--danger); font-weight: 600; }

/* ── Form Inputs ────────────────────── */
.trf-input {
    width: 100%;
    min-height: var(--input-min-h);
    padding: 8px 11px;
    font-size: var(--fs-input);
    font-weight: var(--form-input-weight, 500);
    color: var(--form-input-color, #1f2937);
    background: #fff;
    border: 1.5px solid var(--border);
    border-radius: 8px;
    outline: none;
    box-sizing: border-box;
    transition: border-color .15s, box-shadow .15s;
}
.trf-input:focus { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(13,110,253,.15); }
.trf-input:disabled {
    background: #f1f5f9;
    color: #94a3b8;
    cursor: not-allowed;
    opacity: 1;
}
.trf-input--error { border-color: var(--danger); }
.trf-input--textarea { resize: vertical; line-height: 1.6; min-height: 3rem; }
.trf-input--single-line {
    min-height: var(--input-min-h);
    height: var(--input-min-h);
    resize: none;
    line-height: 1.2;
    padding-top: 9px;
    padding-bottom: 9px;
    overflow: hidden;
}
.trf-ro-val--single-line {
    min-height: var(--input-min-h);
    height: var(--input-min-h);
    display: flex;
    align-items: center;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.trf-step4-fields {
    display: grid;
    grid-template-columns: minmax(0, 0.9fr) minmax(0, 1fr) minmax(0, 1.4fr);
    gap: 12px 14px;
    align-items: start;
}
.trf-step4-fields .trf-pf {
    min-width: 0;
}
.trf-step4-fields .trf-lf__lbl {
    display: block;
    margin-bottom: 5px;
}
@media (max-width: 991.98px) {
    .trf-step4-fields {
        grid-template-columns: 1fr;
    }
}
.trf-input--readonly {
    background: #f8f9fa;
    color: #94a3b8;
    cursor: default;
}
.trf-pf--locked .trf-pf__lbl {
    color: #94a3b8;
}
.trf-vehicle-info__item--locked {
    opacity: .75;
}
.trf-vehicle-info__input:disabled {
    color: #94a3b8;
    cursor: not-allowed;
}
.trf-section__body--locked .trf-route-board,
.trf-route-board--locked {
    opacity: .72;
}
.trf-route-board--locked {
    pointer-events: none;
}
.trf-add-stop-row:disabled {
    opacity: .5;
    cursor: not-allowed;
    pointer-events: none;
}

.trf-date-input :deep(.app-date-input__text) {
    width: 100%;
    min-height: var(--input-min-h);
    height: var(--input-min-h);
    padding: 8px 36px 8px 11px;
    font-size: var(--fs-input);
    font-weight: 500;
    color: var(--text);
    background: #fff;
    border: 1.5px solid var(--border);
    border-radius: 8px;
    outline: none;
    box-sizing: border-box;
    transition: border-color .15s, box-shadow .15s;
}
.trf-date-input :deep(.app-date-input__text:focus) {
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(13,110,253,.15);
}
.trf-date-input :deep(.app-date-input__text.is-invalid) {
    border-color: var(--danger);
}

.trf-select-wrap { position: relative; }
.trf-select {
    width: 100%;
    min-height: var(--input-min-h);
    padding: 8px 36px 8px 11px;
    font-size: var(--fs-input);
    font-weight: 500;
    color: var(--text);
    background: #fff;
    border: 1.5px solid var(--border);
    border-radius: 8px;
    outline: none;
    appearance: none;
    box-sizing: border-box;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 16 16'%3E%3Cpath fill='%236b7280' d='M7.247 11.14 2.451 5.658C1.885 5.013 2.345 4 3.204 4h9.592a1 1 0 0 1 .753 1.659l-4.796 5.48a1 1 0 0 1-1.506 0z'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 12px center;
    transition: border-color .15s;
    cursor: pointer;
}
.trf-select:focus { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(13,110,253,.15); }

/* ── Readonly Values ────────────────── */
.trf-ro-val {
    display: flex; flex-direction: column; justify-content: center;
    min-height: var(--input-min-h); padding: 8px 11px;
    border: 1.5px solid var(--border); border-radius: 8px;
    background: #f9fafb;
}
.trf-ro-val__main { font-weight: 600; font-size: var(--fs-value); color: var(--text); }
.trf-ro-val__sub { font-size: var(--fs-meta); color: var(--muted); margin-top: 2px; }

/* Driver auto-sync box — matches Truck/Customer picker look; grows for make/model */
.trf-synced-field {
    display: flex;
    flex-direction: column;
    justify-content: center;
    width: 100%;
    max-width: 100%;
    min-width: 0;
    height: auto;
    min-height: var(--input-min-h);
    padding: 6px 11px;
    border: 1.5px solid var(--border);
    border-radius: 8px;
    background: #fff;
    box-sizing: border-box;
    overflow: hidden;
}
.trf-synced-field--empty {
    background: #f8f9fa;
}
.trf-synced-field--error {
    border-color: var(--danger, #dc2626);
    background: #fff5f5;
}
.trf-synced-field__main {
    display: block;
    font-weight: 600;
    font-size: var(--fs-value);
    line-height: 1.25;
    color: #1e293b;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.trf-synced-field--empty .trf-synced-field__main {
    font-weight: 400;
    color: #94a3b8;
}
.trf-synced-field__sub {
    display: block;
    margin-top: 1px;
    font-size: var(--fs-meta);
    line-height: 1.2;
    color: #64748b;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

/* Keep Truck / Driver / Customer controls the same size in the grid */
.trf-pf :deep(.async-vehicle-picker-redesigned),
.trf-pf :deep(.searchable-picker-redesigned),
.trf-pf :deep(.picker-main),
.trf-pf :deep(.picker-trigger-wrap) {
    width: 100%;
    min-width: 0;
    max-width: 100%;
}
.trf-pf :deep(.redesigned-picker-trigger) {
    width: 100% !important;
    max-width: 100%;
    min-width: 0;
    height: auto;
    min-height: var(--input-min-h) !important;
    padding-top: 6px !important;
    padding-bottom: 6px !important;
    box-sizing: border-box;
    overflow: hidden;
    font-size: var(--fs-input) !important;
}
.trf-pf :deep(.picker-trigger-content-wrap) {
    min-width: 0;
    max-width: 100%;
    overflow: hidden;
}
.trf-pf :deep(.picker-trigger-main-text) {
    font-size: var(--fs-input) !important;
    line-height: 1.25;
}
.trf-pf :deep(.picker-trigger-sub-text) {
    display: block;
    font-size: var(--fs-meta) !important;
    line-height: 1.25;
    margin-top: 1px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

/* ── Vehicle Information Cards ──────── */
.trf-vehicle-info {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 8px;
}
.trf-vehicle-info--metrics {
    --metrics-label-h: 1.75em;
    --metrics-value-h: 32px;
    --metrics-meta-h: 1.15em;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    align-items: stretch;
}
.trf-vehicle-info--metrics .trf-vehicle-info__item {
    display: grid;
    grid-template-columns: 28px minmax(0, 1fr);
    column-gap: 8px;
    align-items: start;
    height: 100%;
    padding: 6px 10px;
}
.trf-vehicle-info--metrics .trf-vehicle-info__icon {
    grid-column: 1;
    grid-row: 1;
    margin-top: 0;
}
.trf-vehicle-info--metrics .trf-vehicle-info__field {
    grid-column: 2;
    display: grid;
    grid-template-rows: var(--metrics-label-h) var(--metrics-value-h) var(--metrics-meta-h);
    align-content: start;
    align-items: start;
    min-width: 0;
}
.trf-vehicle-info--metrics .trf-vehicle-info__name {
    grid-row: 1;
    align-self: start;
    min-height: var(--metrics-label-h);
    margin: 0;
}
.trf-vehicle-info--metrics .trf-vehicle-info__value {
    grid-row: 2;
    display: flex;
    align-items: stretch;
    min-height: var(--metrics-value-h);
    height: var(--metrics-value-h);
}
.trf-vehicle-info--metrics .trf-input--metric {
    width: 100%;
    min-height: var(--metrics-value-h);
    height: var(--metrics-value-h);
    padding: 3px 8px;
    font-size: .78rem;
    font-weight: 600;
    line-height: 1.2;
    background: #fff;
    border: 1.5px solid var(--border);
    border-radius: 6px;
    box-shadow: inset 0 1px 2px rgba(15, 23, 42, 0.04);
}
.trf-vehicle-info--metrics .trf-input--metric:focus {
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(13, 110, 253, 0.12);
}
.trf-vehicle-info--metrics .trf-input--metric:disabled {
    background: #f1f5f9;
    color: #94a3b8;
    border-color: #e2e8f0;
    box-shadow: none;
    cursor: not-allowed;
}
.trf-vehicle-info--metrics .trf-input--metric.trf-input--readonly {
    display: flex;
    align-items: center;
    background: #f8fafc;
    color: #334155;
    border-color: #e2e8f0;
    box-shadow: none;
    cursor: default;
}
.trf-vehicle-info--metrics .trf-input--metric.trf-input--error {
    border-color: var(--danger);
    box-shadow: 0 0 0 3px rgba(220, 53, 69, 0.12);
}
.trf-vehicle-info--metrics .trf-vehicle-info__meta-slot {
    grid-row: 3;
    display: flex;
    align-items: flex-start;
    min-height: var(--metrics-meta-h);
}
.trf-vehicle-info--metrics .trf-vehicle-info__meta {
    margin-top: 0;
}
.trf-vehicle-info--metrics .trf-pf__err {
    grid-row: 4;
    margin-top: 2px;
}
.trf-vehicle-info__meta {
    display: flex;
    flex-wrap: wrap;
    align-items: baseline;
    gap: 4px 6px;
    margin-top: 4px;
    line-height: 1.25;
}
.trf-vehicle-info__meta-label {
    font-size: .62rem;
    font-weight: 600;
    color: #94a3b8;
}
.trf-vehicle-info__meta-val {
    font-size: .62rem;
    font-weight: 700;
    color: #64748b;
}
.trf-vehicle-info__item--editable {
    background: #fff;
}
.trf-vehicle-info__item--editable .trf-vehicle-info__input {
    max-width: 100%;
}
.trf-vehicle-info__item {
    display: flex;
    align-items: center;
    gap: 8px;
    min-height: 0;
    padding: 8px 12px;
    border: 1px solid var(--border);
    border-radius: 8px;
    background: #fafafa;
}
.trf-vehicle-info__icon {
    width: 28px;
    height: 28px;
    border-radius: 7px;
    flex-shrink: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: .85rem;
    background: var(--bg);
    color: var(--ic);
}
.trf-vehicle-info__name {
    display: block;
    font-size: .68rem;
    font-weight: 700;
    color: var(--muted);
    text-transform: none;
    letter-spacing: 0;
    line-height: 1.2;
}
.trf-vehicle-info__optional {
    font-weight: 500;
    color: #9ca3af;
    text-transform: none;
}
.trf-vehicle-info__val {
    display: block;
    margin-top: 1px;
    font-size: var(--fs-value);
    font-weight: 800;
    color: var(--text);
    line-height: 1.2;
}
.trf-vehicle-info__val--profit { color: #16a34a; }
.trf-vehicle-info__val--loss { color: #dc2626; }
.trf-summary-block {
    display: flex;
    flex-direction: column;
    gap: 8px;
}
.trf-section--summary .trf-section__body {
    padding: 10px 14px 12px;
}
.trf-summary-compact {
    display: flex;
    flex-direction: column;
    gap: 8px;
}
.trf-summary-metrics {
    display: grid;
    grid-template-columns: repeat(6, minmax(0, 1fr));
    gap: 8px;
}
.trf-summary-metrics__item {
    min-width: 0;
    padding: 6px 8px;
    gap: 6px;
}
.trf-summary-metrics__text {
    min-width: 0;
    flex: 1;
}
.trf-summary-metrics__item .trf-vehicle-info__icon {
    width: 22px;
    height: 22px;
    border-radius: 6px;
    font-size: 0.68rem;
}
.trf-summary-metrics__item .trf-vehicle-info__name {
    font-size: 0.58rem;
    line-height: 1.1;
}
.trf-summary-metrics__item .trf-vehicle-info__val {
    margin-top: 0;
    font-size: 0.74rem;
    font-weight: 800;
    line-height: 1.15;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.trf-summary-metrics__item--hero {
    background: #f8fbff;
    border-color: #bfdbfe;
}
.trf-summary-metrics__item--hero .trf-vehicle-info__val {
    font-size: 0.78rem;
}

.trf-summary-loadmix {
    display: flex;
    flex-direction: column;
    gap: 2px;
    min-width: 0;
}

.trf-summary-loadmix__line {
    display: flex;
    align-items: baseline;
    justify-content: space-between;
    gap: 10px;
    min-width: 0;
}

.trf-summary-loadmix__key {
    font-size: 0.58rem;
    font-weight: 700;
    color: var(--muted);
    line-height: 1.1;
}

.trf-summary-loadmix__val {
    font-size: 0.74rem;
    font-weight: 800;
    color: var(--text);
    line-height: 1.1;
    white-space: nowrap;
}
@media (max-width: 991.98px) {
    .trf-summary-metrics {
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }
}
@media (max-width: 575.98px) {
    .trf-summary-metrics {
        grid-template-columns: 1fr;
    }
}
.trf-summary-block + .trf-summary-block {
    margin-top: 14px;
}
.trf-summary-block__label {
    font-size: .68rem;
    font-weight: 700;
    letter-spacing: .04em;
    text-transform: uppercase;
    color: var(--muted);
}
.trf-vehicle-info--summary {
    grid-template-columns: repeat(4, minmax(0, 1fr));
}
.trf-vehicle-info--ops {
    grid-template-columns: repeat(5, minmax(0, 1fr));
}
.trf-vehicle-info--money {
    grid-template-columns: repeat(3, minmax(0, 1fr));
}
.trf-vehicle-info__item--hero {
    background: #f8fbff;
    border-color: #bfdbfe;
}
.trf-vehicle-info__item--hero .trf-vehicle-info__val {
    font-size: 1.05em;
}
@media (max-width: 1199px) {
    .trf-vehicle-info--ops { grid-template-columns: repeat(3, minmax(0, 1fr)); }
    .trf-vehicle-info--money { grid-template-columns: repeat(3, minmax(0, 1fr)); }
}
@media (max-width: 991px) {
    .trf-vehicle-info--ops,
    .trf-vehicle-info--money,
    .trf-vehicle-info--summary { grid-template-columns: repeat(2, minmax(0, 1fr)); }
}
@media (max-width: 575.98px) {
    .trf-vehicle-info--ops,
    .trf-vehicle-info--money,
    .trf-vehicle-info--summary { grid-template-columns: 1fr; }
}
.trf-vehicle-info__field {
    min-width: 0;
    flex: 1;
}
.trf-vehicle-info__input {
    display: block;
    width: 100%;
    max-width: 100px;
    margin-top: 1px;
    padding: 0;
    border: none;
    border-bottom: 1.5px solid transparent;
    background: transparent;
    outline: none;
    font-size: var(--fs-value);
    font-weight: 800;
    color: var(--text);
    line-height: 1.2;
}
.trf-vehicle-info__input:focus {
    border-bottom-color: var(--primary);
}
@media (max-width: 1199px) {
    .trf-vehicle-info,
    .trf-vehicle-info--metrics { grid-template-columns: repeat(2, minmax(0, 1fr)); }
}
@media (max-width: 575.98px) {
    .trf-vehicle-info,
    .trf-vehicle-info--metrics { grid-template-columns: 1fr; }
}

/* ── Banners ────────────────────────── */
.trf-banner {
    display: flex; align-items: flex-start; gap: 10px;
    padding: 10px 14px; border-radius: 8px; font-size: .8rem; line-height: 1.45;
}
.trf-banner--error { background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; }
.trf-banner--warn  { background: #fffbeb; border: 1px solid #fde68a; color: #92400e; }
.trf-banner--info  { background: #eff6ff; border: 1px solid #bfdbfe; color: #1e40af; }

.trf-route-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    align-items: center;
    justify-content: flex-end;
}
.trf-head-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    min-height: 34px;
    padding: 6px 12px;
    border-radius: 8px;
    font-size: var(--fs-btn);
    font-weight: 600;
    line-height: 1.2;
    font-family: inherit;
    cursor: pointer;
    white-space: nowrap;
    transition: background .15s, border-color .15s, box-shadow .15s, opacity .15s;
}
.trf-head-btn i {
    font-size: .85rem;
}
.trf-head-btn--solid {
    min-width: 8.5rem;
    background: #198754;
    color: #fff;
    border: 1.5px solid #198754;
    box-shadow: 0 1px 3px rgba(25, 135, 84, .25);
}
.trf-head-btn--solid:hover {
    background: #146c43;
    border-color: #146c43;
    box-shadow: 0 2px 8px rgba(25, 135, 84, .35);
}
.trf-head-btn--ghost {
    background: #fff;
    color: #146c43;
    border: 1.5px solid #198754;
    box-shadow: 0 1px 2px rgba(15, 23, 42, .06);
}
.trf-head-btn--ghost:hover:not(:disabled) {
    background: #f0fdf4;
    border-color: #146c43;
}
.trf-head-btn:disabled {
    opacity: .4;
    cursor: not-allowed;
    box-shadow: none;
}
@media (max-width: 991px) {
    .trf-section__head {
        flex-wrap: wrap;
        align-items: flex-start;
    }
    .trf-section__head-right {
        flex: 1 1 100%;
        justify-content: flex-start;
    }
    .trf-route-actions {
        justify-content: flex-start;
        width: 100%;
    }
}
.trf-route-type-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    min-width: 6.75rem;
    padding: 4px 9px;
    border-radius: 999px;
    font-size: var(--fs-meta);
    font-weight: 600;
    letter-spacing: 0;
    font-family: inherit;
    flex-shrink: 0;
    margin-left: 2px;
}
.trf-route-type-badge--round {
    background: #ecfdf5;
    color: #047857;
    border: 1px solid #a7f3d0;
}
.trf-route-type-badge--oneway {
    background: #eff6ff;
    color: #1d4ed8;
    border: 1px solid #bfdbfe;
}
.trf-route-type-badge--placeholder {
    background: #f8fafc;
    color: #94a3b8;
    border: 1px dashed #cbd5e1;
}

/* ── Pills (Badges) ─────────────────── */
.trf-pill {
    display: inline-flex; align-items: center;
    padding: 3px 10px; border-radius: 999px;
    font-size: var(--fs-meta); font-weight: 600;
    letter-spacing: 0; text-transform: none;
    font-family: inherit;
}
.trf-pill--blue   { background: #cfe2ff; color: #084298; }
.trf-pill--green  { background: #d1e7dd; color: #0f5132; }
.trf-pill--red    { background: #f8d7da; color: #842029; }
.trf-pill--orange { background: #fff3cd; color: #664d03; }

/* ── Route Board ────────────────────── */
.trf-route-board {
    display: flex;
    flex-direction: column;
    gap: 12px;
}
.trf-route-board__banner {
    margin: 0;
}
.trf-route-group {
    display: grid;
    grid-template-columns: minmax(236px, 250px) minmax(0, 1fr);
    column-gap: 14px;
    align-items: stretch;
}
.trf-route-group--no-card {
    grid-template-columns: minmax(236px, 250px);
}
.trf-route-group--empty-right .trf-route-group__right {
    min-height: 100%;
}
.trf-route-empty-panel {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 8px;
    width: 100%;
    min-height: 100%;
    padding: 16px 20px;
    border: 1px dashed #cbd5e1;
    border-radius: 10px;
    background: #f8fafc;
    text-align: center;
}
.trf-route-empty-panel__icon {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 36px;
    height: 36px;
    border-radius: 50%;
    background: #eef2ff;
    color: #4f46e5;
    font-size: 1rem;
}
.trf-route-empty-panel__text {
    margin: 0;
    max-width: 280px;
    color: var(--muted);
    font-size: var(--fs-hint);
    font-weight: 500;
    line-height: 1.4;
}
.trf-route-empty-panel__text strong {
    color: var(--text);
    font-weight: 700;
}
.trf-route-group__left {
    display: flex;
    flex-direction: column;
    gap: 10px;
    min-width: 0;
    height: 100%;
    align-self: stretch;
}
.trf-route-group__right {
    display: flex;
    min-width: 0;
    min-height: 100%;
    align-self: stretch;
}
.trf-route-station {
    display: grid;
    grid-template-columns: 36px minmax(0, 1fr);
    column-gap: 12px;
    align-items: start;
    flex: 0 0 auto;
    min-height: 0;
}
/* Source grows so Stop 1 sits on the section bottom; both pickers stay equal size */
.trf-route-group--primary:not(.trf-route-group--no-card) .trf-route-station--home {
    flex: 1 1 auto;
    align-items: stretch;
}
.trf-route-group--primary .trf-route-station--stop {
    flex: 0 0 auto;
    align-self: stretch;
}
/* Later stops: pin equal-sized picker to the section bottom */
.trf-route-group--stop:not(.trf-route-group--no-card) .trf-route-station {
    flex: 1 1 auto;
    align-items: stretch;
}
.trf-route-board__rail {
    display: flex;
    flex-direction: column;
    align-items: center;
    align-self: stretch;
    min-height: 100%;
    padding-top: 2px;
}
.trf-route-board__dot {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    background: var(--success);
    color: #fff;
    font-weight: 700;
    font-size: var(--fs-input);
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    position: relative;
    z-index: 1;
    box-shadow: 0 0 0 3px #fff;
}
.trf-route-board__dot--home {
    font-size: var(--fs-input);
}
.trf-route-board__dot--empty {
    background: #fd7e14;
}
.trf-route-board__line {
    width: 3px;
    flex: 1;
    min-height: 18px;
    margin-top: 2px;
    border-radius: 999px;
    background: #86efac;
}
.trf-route-board__line--lead {
    flex: 1 1 auto;
    min-height: 18px;
    margin-top: 0;
    margin-bottom: 2px;
}
.trf-route-group--stop:not(.trf-route-group--no-card) .trf-route-board__station {
    align-self: end;
}
.trf-route-board__station {
    min-width: 0;
    width: 100%;
}
.trf-route-board__label {
    display: inline-flex;
    align-items: baseline;
    gap: 1px;
    min-height: 20px;
    margin-bottom: 6px;
    font-size: var(--fs-label);
    font-weight: var(--form-label-weight, 600);
    color: var(--form-label-color, #475569);
    font-family: inherit;
    text-transform: none;
    letter-spacing: 0;
}
.trf-route-board__label-row {
    display: flex;
    align-items: center;
    gap: 8px;
    min-height: 20px;
    margin-bottom: 6px;
}
.trf-route-board__label-row .trf-route-board__label {
    margin-bottom: 0;
}
.trf-route-board__label-row .trf-leg__station-clear {
    margin-left: 0;
}
.trf-route-board__label-row .trf-pill {
    margin-left: auto;
}
.trf-route-board__hint {
    margin-left: 3px;
    font-size: inherit;
    font-weight: inherit;
    color: var(--muted);
}
.trf-route-board__station :deep(.redesigned-picker-trigger),
.trf-route-board__station :deep(.picker-trigger) {
    font-size: var(--fs-input) !important;
    font-family: inherit;
}
/* Source / Stop pickers share one fixed size & shape */
.trf-route-board__station :deep(.redesigned-picker-trigger),
.trf-route-board__station :deep(.picker-trigger) {
    display: flex;
    align-items: center;
    box-sizing: border-box;
    width: 100%;
    height: 52px;
    min-height: 52px;
    max-height: 52px;
    padding-top: 8px;
    padding-bottom: 8px;
}
.trf-route-board__station :deep(.picker-trigger-content-wrap) {
    justify-content: center;
    overflow: hidden;
}
.trf-route-board__station :deep(.picker-trigger-main-text) {
    font-size: var(--fs-input) !important;
    font-weight: 600;
}
.trf-route-board__station :deep(.picker-trigger-sub-text) {
    font-size: var(--fs-meta) !important;
    font-weight: 500;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.trf-route-board__station > .trf-ro-val {
    display: flex;
    align-items: center;
    box-sizing: border-box;
    width: 100%;
    height: 52px;
    min-height: 52px;
    padding: 8px 12px;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    background: #fff;
}
.trf-leg__station-picker {
    display: block;
    width: 100%;
    min-width: 0;
}
.trf-leg__station-clear {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 22px;
    height: 22px;
    flex-shrink: 0;
    padding: 0;
    border: 1px solid #e2e8f0;
    border-radius: 6px;
    background: #fff;
    color: #94a3b8;
    font-size: .65rem;
    cursor: pointer;
}
.trf-leg__station-clear:hover:not(:disabled) {
    color: #dc3545;
    border-color: #f1aeb5;
    background: #fff5f5;
}
.trf-leg__station-clear:disabled {
    opacity: .4;
    cursor: not-allowed;
}

/* Leg card stretches to match left column height */
.trf-leg__card {
    display: flex;
    flex-direction: column;
    width: 100%;
    height: 100%;
    min-height: 100%;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    background: #fff;
    overflow: hidden;
    box-shadow: 0 1px 2px rgba(15, 23, 42, .04);
}
.trf-leg__card--collapsed {
    height: auto;
    min-height: 0;
}
.trf-leg__summary {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    width: 100%;
    margin: 0;
    padding: 10px 14px;
    border: 0;
    border-bottom: 1px solid #eef2f7;
    background: #f8fafc;
    text-align: left;
    cursor: pointer;
    color: inherit;
    font: inherit;
}
.trf-leg__card--collapsed .trf-leg__summary {
    border-bottom: 0;
}
.trf-leg__summary:hover {
    background: #f1f5f9;
}
.trf-leg__summary--error {
    background: #fff5f5;
    border-bottom-color: #fecaca;
}
.trf-leg__summary--error .trf-leg__summary-stop {
    color: #dc2626;
}
.trf-leg__summary:disabled {
    cursor: default;
}
.trf-leg__summary:disabled:hover {
    background: #f8fafc;
}
.trf-leg__summary--error:disabled:hover {
    background: #fff5f5;
}
.trf-leg__summary-main {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 6px;
    min-width: 0;
    font-size: var(--fs-meta);
    font-weight: 600;
    color: var(--form-label-color, #475569);
}
.trf-leg__summary-stop {
    color: var(--primary-dark, #1d4ed8);
    font-weight: 700;
}
.trf-leg__summary-sep {
    color: #cbd5e1;
}
.trf-leg__summary-station,
.trf-leg__summary-customer {
    max-width: 12rem;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.trf-leg__summary-meta {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    flex-shrink: 0;
    color: #64748b;
}
.trf-leg__summary-freight {
    font-size: var(--fs-meta);
    font-weight: 700;
    color: #166534;
}
.trf-leg__summary-freight.is-empty {
    color: #94a3b8;
    font-weight: 600;
}
.trf-leg__fields {
    --leg-control-h: 34px;
    --leg-head-h: 20px;
    --leg-fs-label: .6rem;
    --leg-fs-input: .72rem;
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    column-gap: 12px;
    row-gap: 10px;
    align-items: stretch;
    flex: 1 1 auto;
    padding: 10px 12px 8px;
    min-width: 0;
}
.trf-leg__cargo-row {
    grid-column: 1 / -1;
    display: grid;
    grid-template-columns:
        minmax(0, 1.4fr)
        minmax(0, 0.85fr)
        minmax(0, 0.72fr)
        minmax(0, 0.82fr)
        minmax(0, 0.82fr)
        minmax(0, 1.15fr);
    column-gap: 10px;
    align-items: stretch;
    min-width: 0;
}
.trf-leg__fields .trf-lf__lbl,
.trf-leg__fields .trf-field-head .trf-lf__lbl,
.trf-leg__fields .trf-freight-head .trf-lf__lbl {
    min-height: var(--leg-head-h);
    font-size: var(--leg-fs-label);
}
.trf-leg__fields .trf-lf--freight .trf-num-field {
    background: linear-gradient(180deg, #f0fdf4 0%, #ecfdf5 100%);
    border: 1.5px solid #4ade80;
    border-radius: 8px;
    box-shadow: 0 1px 2px rgba(22, 101, 52, 0.08);
}
.trf-leg__fields .trf-lf--freight .trf-num-field .trf-input {
    font-size: calc(var(--leg-fs-input) + 2px);
    font-weight: 700;
    color: #14532d;
    background: transparent;
    border: none;
    box-shadow: none;
}
.trf-leg__fields .trf-lf--freight .trf-num-field .trf-input:focus {
    box-shadow: none;
}
.trf-leg__fields .trf-lf--freight .trf-num-field .trf-input:disabled {
    background: transparent;
    color: #166534;
    opacity: 1;
    cursor: default;
}
.trf-leg__fields .trf-lf--freight .trf-num-field__prefix {
    font-size: calc(var(--leg-fs-input) + 1px);
    font-weight: 700;
    color: #15803d;
}
.trf-leg__fields .trf-ro-val__main--freight {
    font-size: calc(var(--leg-fs-input) + 2px) !important;
    font-weight: 700 !important;
    color: #14532d !important;
}
/* Customer / Pickup / Goods / Units / KM/Ltr / Weight / Rate / Freight — same control size */
.trf-leg__fields :deep(.redesigned-picker-trigger),
.trf-leg__fields :deep(.picker-trigger) {
    align-items: center;
    box-sizing: border-box;
    width: 100%;
    height: var(--leg-control-h);
    min-height: var(--leg-control-h);
    max-height: var(--leg-control-h);
    padding-top: 0;
    padding-bottom: 0;
    font-size: var(--leg-fs-input) !important;
    font-family: inherit;
    line-height: 1.2;
}
.trf-leg__fields :deep(.picker-trigger-content-wrap) {
    justify-content: center;
    overflow: hidden;
}
.trf-leg__fields :deep(.picker-trigger-main-text) {
    font-size: var(--leg-fs-input) !important;
    font-weight: 600;
    line-height: 1.2;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.trf-leg__fields :deep(.picker-trigger-sub-text) {
    display: none;
}
.trf-leg__fields .trf-input,
.trf-leg__fields .trf-num-field,
.trf-leg__fields .trf-same-cust,
.trf-leg__fields .trf-ro-val {
    width: 100%;
    height: var(--leg-control-h);
    min-height: var(--leg-control-h);
    max-height: var(--leg-control-h);
    box-sizing: border-box;
    font-size: var(--leg-fs-input);
    font-family: inherit;
}
.trf-leg__fields .trf-input {
    padding: 6px 9px;
}
.trf-leg__fields .trf-num-field__prefix {
    font-size: var(--leg-fs-input);
}
.trf-leg__fields .trf-input[type='date'] {
    align-items: center;
}
.trf-leg__fields .trf-lf__picker {
    width: 100%;
    height: var(--leg-control-h);
    min-height: var(--leg-control-h);
    max-height: var(--leg-control-h);
    box-sizing: border-box;
    font-size: var(--leg-fs-input);
    font-family: inherit;
    overflow: visible;
}
.trf-leg__fields .trf-lf__picker :deep(.searchable-picker),
.trf-leg__fields .trf-lf__picker :deep(.searchable-picker-redesigned),
.trf-leg__fields .trf-lf__picker :deep(.picker-main),
.trf-leg__fields .trf-lf__picker :deep(.picker-trigger-wrap) {
    height: 100%;
}
.trf-leg__fields .trf-num-field .trf-input {
    height: 100%;
    min-height: 100%;
    max-height: 100%;
}
.trf-leg__fields .trf-same-cust,
.trf-leg__fields .trf-ro-val {
    display: flex;
    align-items: center;
    margin: 0;
    padding: 0 11px;
    overflow: hidden;
}
.trf-leg__fields .trf-ro-val__main {
    font-size: var(--leg-fs-input);
    line-height: 1.2;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.trf-leg__fields .trf-same-cust {
    font-size: var(--leg-fs-input);
}
.trf-leg__meta {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 8px 14px;
    margin-top: auto;
    padding: 8px 14px;
    border-top: 1px solid #eef2f7;
    background: #f8fafc;
    font-size: var(--fs-meta);
    font-weight: 600;
    color: var(--form-label-color, #475569);
    font-family: inherit;
}
.trf-leg__meta-left,
.trf-leg__meta-right {
    display: inline-flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 8px;
}
.trf-leg__meta-label {
    color: var(--form-label-color, #475569);
    font-size: inherit;
    font-weight: 600;
}
.trf-leg__rate-badge {
    display: inline-flex;
    align-items: center;
    padding: 3px 9px;
    border-radius: 999px;
    background: #dbeafe;
    color: #1d4ed8;
    font-weight: 600;
    font-size: var(--fs-meta);
}
.trf-leg__rate-badge--missing {
    background: #ffedd5;
    color: #c2410c;
}
.trf-leg__load-badge {
    display: inline-flex;
    align-items: center;
    padding: 3px 9px;
    border-radius: 999px;
    border: 1px solid #86efac;
    background: #f0fdf4;
    color: #166534;
    font-weight: 600;
    font-size: var(--fs-meta);
}
.trf-leg__load-badge--over {
    border-color: #fdba74;
    background: #fff7ed;
    color: #c2410c;
}
.trf-leg__dist {
    font-size: var(--fs-meta);
    font-weight: 600;
    color: var(--form-label-color, #475569);
    display: inline-flex;
    align-items: center;
    gap: 4px;
}
.trf-leg__dist-input {
    width: 4.5rem;
    margin: 0 2px;
    padding: 2px 6px;
    border: 1px solid #ced4da;
    border-radius: 4px;
    font-size: var(--fs-meta);
    font-weight: 600;
    font-family: inherit;
}
.trf-leg__dist--error,
.trf-leg__dist-input--error {
    color: var(--danger, #dc2626);
}
.trf-leg__dist-input--error {
    border-color: var(--danger, #dc2626);
    background: #fff5f5;
}
.trf-leg__dist-input--wide {
    width: 7.5rem;
}
.trf-leg__dist-input--date {
    width: 8.75rem;
}
.trf-leg__summary-route {
    font-weight: 600;
    color: #334155;
}
.trf-leg__meta-prefix {
    margin-right: -1px;
    font-weight: 600;
}
.trf-vehicle-info__input--warn {
    color: #b45309;
}
.trf-route-board > .trf-add-stop-row {
    width: calc(100% - 50px);
    margin-left: 50px;
    margin-top: 2px;
}
@media (max-width: 991.98px) {
    .trf-route-group {
        grid-template-columns: 1fr;
        row-gap: 10px;
    }
    .trf-route-group__left,
    .trf-route-group__right {
        min-height: 0;
        height: auto;
    }
    .trf-route-group--primary:not(.trf-route-group--no-card) .trf-route-station--home,
    .trf-route-group--stop:not(.trf-route-group--no-card) .trf-route-station {
        flex: 0 0 auto;
    }
    .trf-route-group--stop:not(.trf-route-group--no-card) .trf-route-board__station {
        align-self: start;
    }
    .trf-route-board__line--lead {
        display: none;
    }
    .trf-leg__card {
        height: auto;
        min-height: 0;
    }
    .trf-route-board > .trf-add-stop-row {
        width: 100%;
        margin-left: 0;
    }
}
@media (max-width: 1199.98px) {
    .trf-leg__fields {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
    .trf-leg__cargo-row {
        grid-template-columns: repeat(6, minmax(92px, 1fr));
        overflow-x: auto;
        padding-bottom: 2px;
        scrollbar-width: thin;
    }
}
@media (max-width: 767.98px) {
    .trf-leg__fields {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}
@media (max-width: 575.98px) {
    .trf-route-station {
        grid-template-columns: 28px minmax(0, 1fr);
        column-gap: 10px;
    }
    .trf-route-board__dot {
        width: 28px;
        height: 28px;
        font-size: .75rem;
    }
    .trf-leg__fields {
        grid-template-columns: 1fr;
        padding: 10px;
    }
}

/* ── Leg Fields ─────────────────────── */
.trf-lf {
    display: flex;
    min-width: 0;
    flex-direction: column;
    gap: 5px;
    height: 100%;
    min-height: 0;
    position: relative;
}
.trf-leg__fields .trf-lf {
    gap: 4px;
}
.trf-lf__lbl {
    display: inline-flex;
    align-items: center;
    gap: 1px;
    min-height: var(--leg-head-h, 24px);
    font-size: var(--fs-label);
    font-weight: var(--form-label-weight, 600);
    color: var(--form-label-color, #475569);
    font-family: inherit;
    text-transform: none;
    letter-spacing: 0;
}
.trf-field-head,
.trf-freight-head {
    display: flex;
    align-items: center;
    gap: 6px;
    min-width: 0;
    min-height: var(--leg-head-h, 24px);
    flex-wrap: nowrap;
}
.trf-field-head .trf-lf__lbl,
.trf-freight-head .trf-lf__lbl {
    min-height: 0;
    flex: 1 1 auto;
    min-width: 0;
}
.trf-field-head__actions {
    flex: 0 0 56px;
    width: 56px;
    min-width: 56px;
    height: var(--leg-head-h, 24px);
}
.trf-field-head .trf-subst-toggle,
.trf-freight-head .trf-subst-toggle {
    flex-shrink: 0;
    margin-left: 0;
}
.trf-freight-head {
    justify-content: space-between;
}
/*
 * Park view/add over the reserved field-head actions slot.
 * Keep picker + wrapper static so absolute actions anchor to .trf-lf, not the input.
 */
.trf-leg__fields .trf-lf :deep(.searchable-picker),
.trf-leg__fields .trf-lf :deep(.searchable-picker-redesigned) {
    position: static;
}
.trf-lf__picker {
    position: static;
    z-index: auto;
}
.trf-leg__fields .trf-lf :deep(.picker-header-actions) {
    position: absolute;
    top: 0;
    right: 0;
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 4px;
    width: 56px;
    height: var(--leg-head-h, 24px);
    z-index: 4;
}
.trf-field-head--customer {
    justify-content: space-between;
}
.trf-field-head--customer .trf-subst-toggle {
    margin-left: 0;
    height: 20px;
    padding: 0 5px 0 2px;
    gap: 4px;
}
.trf-field-head--customer .trf-subst-toggle__track,
.trf-freight-head .trf-subst-toggle__track {
    width: 20px;
    height: 12px;
}
.trf-field-head--customer .trf-subst-toggle__thumb,
.trf-freight-head .trf-subst-toggle__thumb {
    width: 8px;
    height: 8px;
}
.trf-field-head--customer .trf-subst-toggle.is-on .trf-subst-toggle__thumb,
.trf-freight-head .trf-subst-toggle.is-on .trf-subst-toggle__thumb {
    transform: translateX(8px);
}
.trf-field-head--customer .trf-subst-toggle__text,
.trf-freight-head .trf-subst-toggle__text {
    font-size: .58rem;
}
.trf-freight-head .trf-subst-toggle {
    height: 20px;
    padding: 0 5px 0 2px;
    gap: 4px;
}
.trf-field-head--customer .trf-subst-toggle__text .trf-pf__req {
    margin-left: 1px;
}
.trf-leg__fields .trf-lf :deep(.picker-header-btn) {
    flex: 0 0 24px;
    width: 24px;
    height: 24px;
}
/* Keep every control on the same baseline within a row */
.trf-lf > .trf-lf__picker,
.trf-lf > .trf-input,
.trf-lf > .trf-num-field,
.trf-lf > .trf-same-cust,
.trf-lf > .trf-ro-val,
.trf-lf > :deep(.searchable-picker) {
    margin-top: auto;
}
.trf-num-field { position: relative; }
.trf-num-field--prefix .trf-input { padding-left: 24px; }
.trf-num-field__prefix {
    position: absolute;
    left: 9px;
    top: 50%;
    transform: translateY(-50%);
    font-size: var(--fs-input);
    font-weight: 600;
    color: var(--muted);
    line-height: 1;
    pointer-events: none;
}
.trf-same-cust {
    font-size: var(--fs-input);
    font-weight: 500;
    color: var(--muted);
    border: 1.5px dashed var(--border);
    border-radius: 8px;
    background: #f9fafb;
}

/* ── Add Stop Row ───────────────────── */
.trf-add-stop-row {
    display: flex; align-items: center; justify-content: center;
    min-height: 38px;
    border: 2px dashed #d1d5db; border-radius: 10px;
    padding: 7px 14px; cursor: pointer;
    background: transparent;
    font-size: var(--fs-btn);
    font-weight: 600;
    font-family: inherit;
    color: var(--success);
    transition: all .15s;
}
.trf-add-stop-row:hover { border-color: var(--success); background: #d1e7dd33; }

/* ── Collapse Arrow ─────────────────── */
.trf-collapse-arrow { color: var(--muted); font-size: .95rem; transition: transform .25s; }
.trf-collapse-arrow--open { transform: rotate(180deg); }

/* ── Action Buttons ─────────────────── */
.trf-primary-btn {
    display: inline-flex; align-items: center;
    padding: 7px 14px; border-radius: 8px;
    background: var(--success); color: #fff;
    border: none; font-weight: 600; font-size: var(--fs-btn); cursor: pointer;
    transition: background .15s, box-shadow .15s;
}
.trf-primary-btn:hover { background: var(--success-dark); box-shadow: 0 2px 8px rgba(25,135,84,.35); }
.trf-outline-btn {
    display: inline-flex; align-items: center;
    padding: 7px 12px; border-radius: 8px;
    background: transparent; color: var(--success);
    border: 1.5px solid var(--success); font-weight: 600; font-size: var(--fs-btn); cursor: pointer;
    transition: all .15s;
}
.trf-outline-btn:hover { background: #d1e7dd; }

/* ── Modal warn icon ────────────────── */
.trf-modal-warn-icon {
    width: 52px; height: 52px; margin: 0 auto;
    background: #fff7ed; color: #ea580c;
    border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.4rem;
}

/* ── Validation Bar (top sticky) ────── */
.trf-validation-bar {
    position: sticky; top: 0; z-index: 100;
    margin-bottom: 16px;
    border-radius: 10px;
    overflow: hidden;
    box-shadow: 0 4px 20px rgba(0,0,0,.12);
}
.trf-validation-bar__inner { display: flex; flex-direction: column; }
.trf-validation-bar__row {
    display: flex; align-items: flex-start; gap: 10px;
    padding: 10px 16px; font-size: .8rem; font-weight: 500; line-height: 1.45;
}
.trf-validation-bar__row--error { background: #dc2626; color: #fff; }
.trf-validation-bar__row--warn  { background: #f59e0b; color: #fff; }
.trf-validation-bar__row i { flex-shrink: 0; margin-top: 2px; }
.trf-validation-bar__row--chips {
    align-items: center;
}
.trf-error-chips {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
}
.trf-error-chip {
    display: inline-flex;
    align-items: center;
    padding: 3px 10px;
    border: 1px solid rgba(255, 255, 255, .45);
    border-radius: 999px;
    background: rgba(255, 255, 255, .18);
    color: #fff;
    font-size: .72rem;
    font-weight: 700;
    line-height: 1.3;
    cursor: pointer;
}
.trf-error-chip:hover {
    background: rgba(255, 255, 255, .3);
}

/* ── Vue transition ─────────────────── */
.trf-slide-enter-active, .trf-slide-leave-active { transition: all .25s cubic-bezier(.4,0,.2,1); }
.trf-slide-enter-from, .trf-slide-leave-to { opacity: 0; transform: translateY(-8px); }

/* ── Rose label icon ────────────────── */
.trf-pf__lbl-icon--rose { background: #fff1f2; color: #e11d48; }

/* ── Arrival Timing Pills ───────────── */
.trf-timing-pill {
    display: inline-flex; align-items: center; gap: 3px;
    padding: 2px 8px; border-radius: 999px;
    font-size: .65rem; font-weight: 700; letter-spacing: .03em;
    margin-left: auto;
}
.trf-timing-pill--ok    { background: #ecfdf5; color: #059669; }
.trf-timing-pill--early { background: #eff6ff; color: #1d4ed8; }
.trf-timing-pill--late  { background: #fef2f2; color: #dc2626; }
</style>
