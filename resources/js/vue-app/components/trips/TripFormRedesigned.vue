<template>
    <div class="trf" @keydown.enter.prevent="handleEnterKey">

        <!-- Sticky validation bar -->
        <Transition name="trf-slide">
            <div v-if="formError || validationMessages.length > 0" class="trf-validation-bar">
                <div class="trf-validation-bar__inner">
                    <div v-if="formError" class="trf-validation-bar__row trf-validation-bar__row--error">
                        <i class="bi bi-exclamation-octagon-fill"></i>
                        <span>{{ formError }}</span>
                    </div>
                    <div v-if="validationMessages.length > 0" class="trf-validation-bar__row trf-validation-bar__row--warn">
                        <i class="bi bi-exclamation-triangle-fill"></i>
                        <div>
                            <span class="fw-semibold">Fix these issues before saving: </span>
                            {{ validationMessages.join(' • ') }}
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
                        <p class="trf-section__desc">Assign vehicle, driver, customer and set dates</p>
                    </div>
                </div>
                <!--<div class="d-flex align-items-center gap-2">
                    <span v-if="tripNumberDisplay" class="trf-pill trf-pill--blue">{{ tripNumberDisplay }}</span>
                    <span v-if="effectiveTripStatus" class="trf-pill" :class="statusPillClass">{{ currentStatusBadge.label }}</span>
                </div> -->
            </div>

            <div class="trf-section__body">
                <!-- ── ROW 1: Truck | Driver | Customer | Trip Type | Start Date ── -->
                <div class="trf-picker-grid trf-picker-grid--5">
                    <!-- Truck -->
                    <div class="trf-pf">
                        <div class="trf-pf__lbl">
                            <div class="trf-pf__lbl-icon trf-pf__lbl-icon--blue"><i class="bi bi-truck-front-fill"></i></div>
                            <span>Truck</span>
                            <span class="trf-pf__req">*</span>
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
                    <div class="trf-pf">
                        <div class="trf-pf__lbl">
                            <div class="trf-pf__lbl-icon trf-pf__lbl-icon--indigo"><i class="bi bi-person-badge-fill"></i></div>
                            <span>Driver</span>
                            <span class="trf-pf__req">*</span>
                            <label v-if="!isFormLocked && hasPrimaryDriver" class="trf-check-label trf-field-head__check trf-pf__subst ms-auto">
                                <input id="subst-sw" v-model="showSubstituteDriver" type="checkbox" />
                                <span>Substitute</span>
                            </label>
                        </div>
                        <template v-if="showSubstituteDriverColumn && !isFormLocked">
                            <SearchablePickerRedesigned
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
                        </template>
                        <div
                            v-else
                            class="trf-synced-field"
                            :class="{ 'trf-synced-field--empty': !hasDriverDisplay && !loadingDriver }"
                        >
                            <div class="trf-synced-field__main">
                                {{ loadingDriver ? 'Syncing from truck…' : (hasDriverDisplay ? driverDisplayName : 'Select truck first') }}
                            </div>
                            <div v-if="hasDriverDisplay || loadingDriver" class="trf-synced-field__sub">
                                {{ selectedDriver?.license_number || 'Auto-synced with truck' }}
                            </div>
                        </div>
                    </div>

                    <!-- Customer -->
                    <div class="trf-pf">
                        <div class="trf-pf__lbl">
                            <div class="trf-pf__lbl-icon trf-pf__lbl-icon--purple"><i class="bi bi-building-fill"></i></div>
                            <span>Customer</span>
                            <span class="trf-pf__req">*</span>
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
                    <div class="trf-pf">
                        <div class="trf-pf__lbl">
                            <div class="trf-pf__lbl-icon trf-pf__lbl-icon--teal"><i class="bi bi-calendar-check-fill"></i></div>
                            <span>Start Date</span>
                            <span class="trf-pf__req">*</span>
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
                    <div class="trf-pf">
                        <div class="trf-pf__lbl">
                            <div class="trf-pf__lbl-icon trf-pf__lbl-icon--rose"><i class="bi bi-calendar-x-fill"></i></div>
                            <span>End Date</span>
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

                <!-- ── ROW 2: End Date | Start Meter | End Meter | Diesel Rate | Fuel Tank ── -->
                <div class="trf-picker-grid trf-picker-grid--5 mt-3">
                    
                     <!-- Trip Type -->
                    <div class="trf-pf">
                        <div class="trf-pf__lbl">
                            <div class="trf-pf__lbl-icon trf-pf__lbl-icon--orange"><i class="bi bi-arrow-left-right"></i></div>
                            <span>Trip Type</span>
                            <span class="trf-pf__req">*</span>
                        </div>
                        <div class="trf-select-wrap">
                            <select v-if="!isFormLocked" v-model="form.trip_type" class="trf-select" @change="saveProgress">
                                <option value="ROUND_TRIP">↩ Round Trip</option>
                                <option value="ONE_WAY">→ One Way</option>
                            </select>
                            <div v-else class="trf-ro-val"><span class="trf-ro-val__main">{{ tripTypeDisplay }}</span></div>
                        </div>
                    </div>

                    <!-- Start Meter Reading -->
                    <div class="trf-pf">
                        <div class="trf-pf__lbl">
                            <div class="trf-pf__lbl-icon trf-pf__lbl-icon--orange"><i class="bi bi-speedometer"></i></div>
                            <span>Start Meter Reading</span>
                        </div>
                        <input
                            v-if="!isFormLocked"
                            v-model.number="form.start_meter_reading_km"
                            v-no-scroll-change
                            type="number"
                            min="0"
                            step="0.01"
                            class="trf-input"
                            :class="{ 'trf-input--error': startMeterWarning }"
                            placeholder="0"
                            @input="saveProgress"
                        />
                        <div v-else class="trf-ro-val"><span class="trf-ro-val__main">{{ startMeterDisplay }}</span></div>
                    </div>

                    <!-- End Meter Reading -->
                    <div class="trf-pf">
                        <div class="trf-pf__lbl">
                            <div class="trf-pf__lbl-icon trf-pf__lbl-icon--teal"><i class="bi bi-speedometer2"></i></div>
                            <span>End Meter Reading</span>
                        </div>
                        <input
                            v-if="!isFormLocked"
                            v-model.number="form.end_meter_reading_km"
                            v-no-scroll-change
                            type="number"
                            min="0"
                            step="0.01"
                            class="trf-input"
                            placeholder="0"
                            @input="saveProgress"
                        />
                        <div v-else class="trf-ro-val"><span class="trf-ro-val__main">{{ endMeterDisplay }}</span></div>
                    </div>

                    <!-- Diesel Rate -->
                    <div class="trf-pf">
                        <div class="trf-pf__lbl">
                            <div class="trf-pf__lbl-icon trf-pf__lbl-icon--purple"><i class="bi bi-currency-rupee"></i></div>
                            <span>Diesel Rate (₹/Ltr)</span>
                        </div>
                        <input
                            v-if="!isFormLocked"
                            v-model.number="form.diesel_rate_per_liter"
                            v-no-scroll-change
                            type="number"
                            min="0"
                            step="0.01"
                            class="trf-input"
                            placeholder="0.00"
                            @input="saveProgress"
                        />
                        <div v-else class="trf-ro-val"><span class="trf-ro-val__main">{{ form.diesel_rate_per_liter ? '₹' + formatMoney(form.diesel_rate_per_liter) : '—' }}</span></div>
                    </div>

                    <!-- Fuel Tank Capacity -->
                    <div class="trf-pf">
                        <div class="trf-pf__lbl">
                            <div class="trf-pf__lbl-icon trf-pf__lbl-icon--blue"><i class="bi bi-fuel-pump"></i></div>
                            <span>Fuel Tank Capacity (Ltr)</span>
                        </div>
                        <input
                            v-if="!isFormLocked"
                            v-model.number="fuelTankCapacityLtr"
                            v-no-scroll-change
                            type="number"
                            min="0"
                            step="0.01"
                            class="trf-input"
                            placeholder="0"
                            @input="saveProgress"
                        />
                        <div v-else class="trf-ro-val"><span class="trf-ro-val__main">{{ fuelTankCapacityLtr || 0 }}</span></div>
                    </div>
                </div>

                <!-- Driver Warning -->
                <div v-if="driverWarning" class="trf-banner trf-banner--warn mt-4">
                    <i class="bi bi-exclamation-triangle-fill"></i><span>{{ driverWarning }}</span>
                </div>

                <!-- Vehicle Information -->
                <div class="trf-vehicle-info mt-4">
                    <div class="trf-vehicle-info__item">
                        <div class="trf-vehicle-info__icon" style="--ic:#6b7280;--bg:#f3f4f6"><i class="bi bi-speedometer2"></i></div>
                        <div>
                            <span class="trf-vehicle-info__name">Truck Running Meter</span>
                            <span class="trf-vehicle-info__val">{{ truckRunningMeterDisplay || '—' }}</span>
                        </div>
                    </div>
                    <div class="trf-vehicle-info__item trf-vehicle-info__item--editable">
                        <div class="trf-vehicle-info__icon" style="--ic:#2563eb;--bg:#eff6ff"><i class="bi bi-speedometer"></i></div>
                        <div class="trf-vehicle-info__field">
                            <span class="trf-vehicle-info__name">Fuel Average (KM/L)</span>
                            <input
                                v-if="!isFormLocked"
                                v-model.number="form.planned_mileage_km_per_liter"
                                v-no-scroll-change
                                type="number"
                                min="0"
                                step="0.01"
                                class="trf-vehicle-info__input"
                                placeholder="0"
                                @input="onPlannedMileageInput"
                            />
                            <span v-else class="trf-vehicle-info__val">{{ fuelAvgDisplay }}</span>
                        </div>
                    </div>
                    <div class="trf-vehicle-info__item trf-vehicle-info__item--editable">
                        <div class="trf-vehicle-info__icon" style="--ic:#7c3aed;--bg:#faf5ff"><i class="bi bi-box-seam-fill"></i></div>
                        <div class="trf-vehicle-info__field">
                            <span class="trf-vehicle-info__name">Max Load Capacity (Ton)</span>
                            <input
                                v-if="!isFormLocked"
                                v-model.number="form.max_load_ton"
                                v-no-scroll-change
                                type="number"
                                min="0"
                                step="0.001"
                                class="trf-vehicle-info__input"
                                placeholder="0"
                                @input="saveProgress"
                            />
                            <span v-else class="trf-vehicle-info__val">{{ maxLoadDisplay }}</span>
                        </div>
                    </div>
                    <div class="trf-vehicle-info__item trf-vehicle-info__item--editable">
                        <div class="trf-vehicle-info__icon" style="--ic:#059669;--bg:#ecfdf5"><i class="bi bi-droplet-fill"></i></div>
                        <div class="trf-vehicle-info__field">
                            <span class="trf-vehicle-info__name">Expected Diesel (Ltr)</span>
                            <input
                                v-if="!isFormLocked"
                                v-model.number="form.planned_diesel_liters"
                                v-no-scroll-change
                                type="number"
                                min="0"
                                step="0.01"
                                class="trf-vehicle-info__input"
                                placeholder="0"
                                @input="onPlannedDieselInput"
                            />
                            <span v-else class="trf-vehicle-info__val">{{ expectedDieselDisplay }}</span>
                        </div>
                    </div>
                </div>

                <div v-if="startMeterWarning" class="trf-banner trf-banner--warn mt-3">
                    <i class="bi bi-exclamation-triangle-fill"></i>
                    <span>Start Meter entered ({{ form.start_meter_reading_km }} KM) is lower than current truck odometer ({{ truckRunningMeterDisplay }}). Please verify before saving.</span>
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
                        <p class="trf-section__desc">Define the pickup and delivery locations</p>
                    </div>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <button v-if="!isFormLocked && canReturnToStart" type="button" class="trf-outline-btn" @click="$emit('return-to-start')">
                        <i class="bi bi-arrow-return-left me-1"></i>Return to Start
                    </button>
                    <button v-if="!isFormLocked" type="button" class="trf-primary-btn" @click="$emit('add-stop')">
                        <i class="bi bi-plus-lg me-1"></i>Add Stop
                    </button>
                </div>
            </div>

            <div class="trf-section__body">
                <!-- Home Base Row -->
                <div class="trf-route-home">
                    <div class="trf-route-home__timeline">
                        <div class="trf-route-home__dot"><i class="bi bi-house-door-fill"></i></div>
                        <div v-if="form.route_legs.length > 0" class="trf-route-home__line"></div>
                    </div>
                    <div class="trf-route-home__content">
                        <div class="trf-route-home__info">
                            <span class="trf-route-home__label">Home Base <span class="text-muted">(Start)</span></span>
                            <span class="trf-route-home__req">*</span>
                        </div>
                        <div class="trf-route-home__picker">
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
                                :invalid="Boolean(fieldErrors.start_station_id)"
                                :error-message="fieldErrorMessage('start_station_id')"
                                :can-quick-add="canQuickAddStation"
                                @quick-add="openQuickAddForLeg(null)"
                                @update:model-value="handleStartStationPickerChange"
                            />
                            <div v-else class="trf-ro-val"><span class="trf-ro-val__main">{{ stationLabel(form.start_station_id) }}</span></div>
                        </div>
                    </div>
                </div>

                <!-- Empty state -->
                <div v-if="form.route_legs.length === 0" class="trf-route-empty">
                    <div class="trf-route-empty__dashed">
                        <i class="bi bi-map"></i>
                        <p>Select home base then click <strong>+ Add Stop</strong> to build the route</p>
                    </div>
                </div>

                <!-- Leg rows -->
                <div v-else class="trf-legs">
                    <div v-for="(leg, index) in form.route_legs" :key="index" class="trf-leg">
                        <!-- Timeline Left -->
                        <div class="trf-leg__timeline">
                            <div class="trf-leg__dot">{{ index + 1 }}</div>
                        </div>

                        <!-- Station Selector + Remove -->
                        <div class="trf-leg__station">
                            <div class="trf-leg__station-head">
                                <span class="trf-leg__station-tag">STOP {{ index + 1 }}</span>
                                <button
                                    v-if="!isFormLocked"
                                    type="button"
                                    class="trf-leg__remove-btn"
                                    :title="stopRemoveTitle(index)"
                                    :disabled="isStopRemoveBlocked(index)"
                                    @click="openRemoveStopConfirm(index)"
                                >
                                    <i class="bi bi-trash3"></i>
                                    Remove
                                </button>
                            </div>
                            <SearchablePickerRedesigned
                                v-if="!isFormLocked"
                                v-model="leg.to_station_id"
                                :items="stations"
                                attach-dropdown-to-body
                                placeholder="To station"
                                search-placeholder="Search station…"
                                empty-message="No station found."
                                view-title="Location details"
                                quick-add-title="Add location"
                                :invalid="Boolean(legFieldError(index, 'to_station_id'))"
                                :error-message="legFieldError(index, 'to_station_id')"
                                :can-quick-add="canQuickAddStation"
                                @quick-add="openQuickAddForLeg(index)"
                                @update:model-value="handleLegStopChange(index)"
                            />
                            <div v-else class="trf-ro-val"><span class="trf-ro-val__main">{{ stationLabel(leg.to_station_id) }}</span></div>
                        </div>

                        <!-- Leg Detail Card -->
                        <div class="trf-leg__card">
                            <div class="trf-leg__fields">
                                <!-- Customer -->
                                <div class="trf-lf">
                                    <div class="trf-field-head trf-field-head--with-picker">
                                        <label class="trf-lf__lbl">Customer <span class="trf-pf__req">*</span></label>
                                        <label v-if="!isFormLocked" class="trf-check-label trf-field-head__check">
                                            <input v-model="leg.use_same_customer" type="checkbox" @change="onLegSameCustomerChange(index); saveProgress()" />
                                            <span>Same as customer</span>
                                        </label>
                                    </div>
                                    <div v-if="!isFormLocked && !leg.use_same_customer" class="trf-lf__picker">
                                        <SearchablePickerRedesigned
                                            v-model="leg.customer_id"
                                            :items="customers"
                                            attach-dropdown-to-body
                                            placeholder="Select customer"
                                            search-placeholder="Search customer…"
                                            empty-message="No customer found."
                                            view-title="Customer details"
                                            quick-add-title="Add customer"
                                            :invalid="Boolean(legFieldError(index, 'customer_id'))"
                                            :error-message="legFieldError(index, 'customer_id')"
                                            :can-quick-add="canQuickAddCustomer"
                                            :show-subtext="false"
                                            title-case-label
                                            compact-actions
                                            @quick-add="openQuickAddForLegCustomer(index)"
                                            @update:model-value="saveProgress"
                                        />
                                    </div>
                                    <div v-else-if="!isFormLocked" class="trf-same-cust">{{ customerLabel(leg.customer_id, snapshotCustomerName) || 'Same as main' }}</div>
                                    <div v-else class="trf-ro-val"><span class="trf-ro-val__main">{{ customerLabel(leg.customer_id, snapshotCustomerName) }}</span></div>
                                </div>

                                <!-- Goods -->
                                <div class="trf-lf">
                                    <label class="trf-lf__lbl">Goods Type <span class="trf-pf__req">*</span></label>
                                    <SearchablePickerRedesigned
                                        v-if="!isFormLocked"
                                        v-model="leg.goods_name"
                                        :items="goodsTypePickerItems"
                                        attach-dropdown-to-body
                                        placeholder="Select goods"
                                        search-placeholder="Search goods…"
                                        empty-message="No goods found."
                                        quick-add-title="Add goods type"
                                        :invalid="Boolean(legFieldError(index, 'goods_name'))"
                                        :error-message="legFieldError(index, 'goods_name')"
                                        :can-quick-add="canQuickAddGoodsType"
                                        :can-view="false"
                                        compact-actions
                                        @quick-add="openQuickAddForLegGoodsType(index)"
                                        @update:model-value="saveProgress"
                                    />
                                    <div v-else class="trf-ro-val"><span class="trf-ro-val__main">{{ leg.goods_name || '—' }}</span></div>
                                </div>

                                <!-- KM/Ltr -->
                                <div class="trf-lf">
                                    <label class="trf-lf__lbl">KM/Ltr</label>
                                    <input v-if="!isFormLocked" v-model.number="leg.expected_mileage_km_per_liter" v-no-scroll-change type="number" min="0" step="0.01" class="trf-input" placeholder="0.00" @input="onLegMileageInput(index); saveProgress()" />
                                    <span v-else class="trf-ro-val__main">{{ leg.expected_mileage_km_per_liter || '—' }}</span>
                                </div>

                                <!-- Weight -->
                                <div class="trf-lf">
                                    <label class="trf-lf__lbl">Weight (Ton) <span class="trf-pf__req">*</span></label>
                                    <div class="trf-num-field">
                                        <input v-if="!isFormLocked" v-model.number="leg.weight_ton" v-no-scroll-change type="number" min="0" step="0.001" class="trf-input" :class="{ 'trf-input--error': legFieldError(index, 'weight_ton') }" placeholder="0.000" @input="onLegWeightInput(index); saveProgress()" />
                                        <span v-else class="trf-ro-val__main">{{ leg.weight_ton ? formatNum(leg.weight_ton) : '—' }}</span>
                                    </div>
                                </div>

                                <!-- ₹/Ton Rate -->
                                <div class="trf-lf">
                                    <label class="trf-lf__lbl">Rate (₹/Ton)</label>
                                    <input v-if="!isFormLocked" v-model.number="leg.freight_rate" v-no-scroll-change type="number" min="0" step="0.01" class="trf-input" placeholder="0.00" @input="onLegRateInput(index); saveProgress()" />
                                    <span v-else class="trf-ro-val__main">{{ leg.freight_rate ? '₹' + formatMoney(leg.freight_rate) : '—' }}</span>
                                </div>

                                <!-- Freight -->
                                <div class="trf-lf">
                                    <div class="trf-freight-head">
                                        <label class="trf-lf__lbl">Freight (₹) <span class="trf-pf__req">*</span></label>
                                        <label v-if="!isFormLocked" class="trf-check-label trf-freight-auto">
                                            <input v-model="leg.freight_auto_calculate" type="checkbox" @change="onLegAutoCalculateChange(index); saveProgress()" />
                                            <span>Auto Calculate</span>
                                        </label>
                                    </div>
                                    <div class="trf-num-field trf-num-field--prefix">
                                        <span class="trf-num-field__prefix">₹</span>
                                        <input v-if="!isFormLocked" v-model.number="leg.freight_amount" v-no-scroll-change type="number" min="0" step="0.01" class="trf-input" :class="{ 'trf-input--error': legFieldError(index, 'freight_amount') }" placeholder="0.00" @input="onLegFreightManualInput(index); saveProgress()" />
                                        <span v-else class="trf-ro-val__main">₹{{ formatMoney(leg.freight_amount) }}</span>
                                    </div>
                                </div>
                            </div>

                            <div class="trf-leg__meta">
                                <div class="trf-leg__pricing">
                                    <span class="trf-leg__meta-label">Pricing Status</span>
                                    <span
                                        class="trf-leg__pricing-status"
                                        :class="{ 'trf-leg__pricing-status--missing': !(leg.freight_rate > 0) }"
                                    >
                                        <i :class="leg.freight_rate > 0 ? 'bi bi-check-circle-fill' : 'bi bi-exclamation-triangle-fill'"></i>
                                        {{ leg.freight_rate > 0 ? `₹${formatMoney(leg.freight_rate)} / Ton` : 'Rate Not Configured' }}
                                    </span>
                                </div>
                                <span class="trf-leg__load"><strong>Load Type :</strong> {{ legLoadMeta(leg.weight_ton).label }}</span>
                                <span class="trf-leg__dist"><strong>Distance :</strong> {{ leg.distance_km ?? '—' }} KM</span>
                            </div>
                        </div>
                    </div>

                    <!-- Add another stop -->
                    <button v-if="!isFormLocked" type="button" class="trf-add-stop-row" @click="$emit('add-stop')">
                        <i class="bi bi-plus-circle-fill text-success me-2"></i>
                        <span>Add Stop</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- ─────────────────────────────────────────────────────────────────
             SECTION 3 — TRIP SUMMARY
        ───────────────────────────────────────────────────────────────── -->
        <div class="trf-section">
            <div class="trf-section__head">
                <div class="trf-section__head-left">
                    <div class="trf-step-num trf-step-num--summary">3</div>
                    <div>
                        <h2 class="trf-section__title">Trip Summary</h2>
                        <p class="trf-section__desc">Auto-calculated totals based on route and cargo</p>
                    </div>
                </div>
            </div>
            <div class="trf-section__body">
                <div class="trf-metrics">
                    <div class="trf-metric">
                        <div class="trf-metric__icon" style="--c:#4f46e5;--bg:#eef2ff"><i class="bi bi-geo-alt-fill"></i></div>
                        <span class="trf-metric__lbl">Distance</span>
                        <span class="trf-metric__val">{{ summary.totalKm || 0 }} KM</span>
                    </div>
                    <div class="trf-metric">
                        <div class="trf-metric__icon" style="--c:#0d9488;--bg:#f0fdfa"><i class="bi bi-box-seam-fill"></i></div>
                        <span class="trf-metric__lbl">Total Load</span>
                        <span class="trf-metric__val">{{ summary.totalWeightTon ? formatNum(summary.totalWeightTon) : '0' }} Ton</span>
                    </div>
                    <div class="trf-metric">
                        <div class="trf-metric__icon" style="--c:#ea580c;--bg:#fff7ed"><i class="bi bi-fuel-pump-fill"></i></div>
                        <span class="trf-metric__lbl">Expected Diesel</span>
                        <span class="trf-metric__val">{{ form.planned_diesel_liters ? formatNum(form.planned_diesel_liters) : '0' }} Ltr</span>
                    </div>
                    <div class="trf-metric">
                        <div class="trf-metric__icon" style="--c:#16a34a;--bg:#f0fdf4"><i class="bi bi-speedometer2"></i></div>
                        <span class="trf-metric__lbl">Mileage</span>
                        <span class="trf-metric__val">{{ expectedMileageDisplay }}</span>
                    </div>
                    <div class="trf-metric">
                        <div class="trf-metric__icon" style="--c:#be185d;--bg:#fdf2f8"><i class="bi bi-currency-rupee"></i></div>
                        <span class="trf-metric__lbl">Total Freight</span>
                        <span class="trf-metric__val">₹{{ formatMoney(summary.freightTotal) }}</span>
                    </div>
                    <div class="trf-metric">
                        <div class="trf-metric__icon" style="--c:#2563eb;--bg:#eff6ff"><i class="bi bi-graph-up-arrow"></i></div>
                        <span class="trf-metric__lbl">Est. Profit</span>
                        <span class="trf-metric__val" :style="summary.profit >= 0 ? 'color:#16a34a' : 'color:#dc2626'">₹{{ formatMoney(summary.profit) }}</span>
                    </div>
                </div>
            </div>
        </div>

        <TripDieselPerformanceCard :performance="dieselPerformance" />

        <!-- ─────────────────────────────────────────────────────────────────
             SECTION 4 — REFERENCE & NOTES
        ───────────────────────────────────────────────────────────────── -->
        <div class="trf-section trf-section--collapsible" :class="{ 'trf-section--open': showAdvanced }">
            <div class="trf-section__head trf-section__head--plain trf-section__head--toggle" @click="showAdvanced = !showAdvanced">
                <div class="trf-section__head-left">
                    <div class="trf-step-num trf-step-num--gray">4</div>
                    <div>
                        <h2 class="trf-section__title">Reference &amp; Notes</h2>
                        <p class="trf-section__desc">Optional reference party and trip notes</p>
                    </div>
                </div>
                <div class="trf-collapse-arrow" :class="{ 'trf-collapse-arrow--open': showAdvanced }">
                    <i class="bi bi-chevron-down"></i>
                </div>
            </div>

            <div v-show="showAdvanced" class="trf-section__body">
                <div class="row g-3">
                    <div class="col-md-4">
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
                    </div>
                    <div class="col-md-8">
                        <label class="trf-lf__lbl">Trip Notes</label>
                        <textarea
                            v-if="!isFormLocked"
                            v-model="form.notes"
                            class="trf-input trf-input--textarea"
                            rows="3"
                            placeholder="Optional notes, instructions, or remarks for this trip…"
                            @input="saveProgress"
                        ></textarea>
                        <div v-else class="trf-ro-val" style="min-height:3rem;white-space:pre-wrap">{{ form.notes && form.notes.trim() ? form.notes.trim() : '—' }}</div>
                    </div>
                    <div v-if="form.status === 'CANCELLED'" class="col-12">
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
import { computed, onMounted, ref, watch } from 'vue';
import {
    calculateLegFreight, legLoadCategoryMeta, stopHasExpenses,
    summarizeTripForm, tripStatusMeta, tripTypeLabel,
    computeDieselPerformance,
    MAX_EXPECTED_MILEAGE_KM_PER_LITER, MAX_FREIGHT_RATE_PER_TON,
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
    statusLocked: { type: Boolean, default: false },
    displayStatus: { type: String, default: '' },
    statusActionsInFooter: { type: Boolean, default: false },
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
    'status-updated','clear-field-error',
]);

const toast = useAppToast();
const maxExpectedMileage = MAX_EXPECTED_MILEAGE_KM_PER_LITER;
const maxFreightRate = MAX_FREIGHT_RATE_PER_TON;

const showAdvanced = ref(false);
const showSubstituteDriver = ref(false);
const showStationQuickAdd = ref(false);
const showBrokerQuickAdd = ref(false);
const showCustomerQuickAdd = ref(false);
const showGoodsTypeQuickAdd = ref(false);
const showRemoveStopConfirm = ref(false);
const quickAddLegIndex = ref(null);
const quickAddCustomerLegIndex = ref(null);
const quickAddGoodsTypeLegIndex = ref(null);
const pendingRemoveStopIndex = ref(null);

const isFormLocked = computed(() => props.readonly);
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
const currentStatusBadge = computed(() => tripStatusMeta(effectiveTripStatus.value));
const statusPillClass = computed(() => { const s = String(effectiveTripStatus.value ?? '').toUpperCase(); if (s === 'IN_PROGRESS') return 'trf-pill--orange'; if (s === 'COMPLETED') return 'trf-pill--green'; if (s === 'CANCELLED') return 'trf-pill--red'; return 'trf-pill--blue'; });
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
const isRoundTrip = computed(() => String(props.form.trip_type ?? 'ROUND_TRIP').toUpperCase() !== 'ONE_WAY');
const tripTypeDisplay = computed(() => tripTypeLabel(props.form.trip_type));
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
const maxLoadDisplay = computed(() => {
    const v = props.form.max_load_ton;

    if (v === '' || v == null) {
        return '0';
    }

    const n = Number(v);

    return Number.isFinite(n) ? formatNum(n) : '0';
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
const canReturnToStart = computed(() => { if (!isRoundTrip.value || !props.form.start_station_id || props.form.route_legs.length === 0) return false; const last = props.form.route_legs[props.form.route_legs.length - 1]; return String(last.to_station_id) !== String(props.form.start_station_id); });
const validationMessages = computed(() => { const msgs = []; let hasRoute = false; Object.entries(props.fieldErrors).forEach(([k, v]) => { if (k.startsWith('route_legs.')) { hasRoute = true; return; } const m = Array.isArray(v) ? v : [v]; m.forEach((x) => { if (x) msgs.push(x); }); }); if (hasRoute) msgs.push('Route: fix highlighted stop fields.'); return [...new Set(msgs)].slice(0, 5); });

function legLoadMeta(w) { return legLoadCategoryMeta(w, props.form.max_load_ton); }
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
function confirmRemoveStop() { const i = pendingRemoveStopIndex.value; if (i === null) { cancelRemoveStopConfirm(); return; } cancelRemoveStopConfirm(); emit('remove-route-leg', i); toast.success('Stop removed.'); saveProgress(); }
function applyLegFreightAuto(i) { const leg = props.form.route_legs[i]; if (!leg || !leg.freight_auto_calculate) return; leg.freight_amount = calculateLegFreight(leg.weight_ton, leg.freight_rate); }
function onLegWeightInput(i) { applyLegFreightAuto(i); emit('clear-field-error', 'route_legs.' + i + '.weight_ton'); emit('clear-field-error', 'route_legs.' + i + '.freight_amount'); }
function onLegMileageInput(i) { const leg = props.form.route_legs[i]; if (leg) leg.expected_mileage_km_per_liter = clamp(leg.expected_mileage_km_per_liter, maxExpectedMileage); emit('clear-field-error', 'route_legs.' + i + '.expected_mileage_km_per_liter'); }
function onLegRateInput(i) { const leg = props.form.route_legs[i]; if (leg) leg.freight_rate = clamp(leg.freight_rate, maxFreightRate); applyLegFreightAuto(i); emit('clear-field-error', 'route_legs.' + i + '.freight_rate'); }
function onLegAutoCalculateChange(i) { if (props.form.route_legs[i]?.freight_auto_calculate) applyLegFreightAuto(i); }
function onLegFreightManualInput(i) { const leg = props.form.route_legs[i]; if (leg) leg.freight_auto_calculate = false; }
function onLegSameCustomerChange(i) { const leg = props.form.route_legs[i]; if (!leg) return; if (leg.use_same_customer) { leg.customer_id = props.form.customer_id ?? ''; return; } leg.customer_id = ''; }
function syncAutoPlannedDiesel() { if (props.form.planned_diesel_manually_set || isFormLocked.value) return; const rk = calculateExpectedRunningKm(props.form.start_meter_reading_km, props.form.end_meter_reading_km || props.form.start_meter_reading_km); if (rk === null) return; const p = calculatePlannedDiesel(rk, props.form.planned_mileage_km_per_liter); if (p !== null) props.form.planned_diesel_liters = p; }
function onPlannedMileageInput() { props.form.planned_mileage_km_per_liter = clamp(props.form.planned_mileage_km_per_liter, maxExpectedMileage); emit('clear-field-error', 'planned_mileage_km_per_liter'); syncAutoPlannedDiesel(); saveProgress(); }
function onPlannedDieselInput() { props.form.planned_diesel_manually_set = true; saveProgress(); }
watch(() => [props.form.start_meter_reading_km, props.form.end_meter_reading_km, props.form.planned_mileage_km_per_liter], syncAutoPlannedDiesel);
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
function clamp(r, max) { if (r === '' || r == null) return r; const v = Number(r); if (!Number.isFinite(v)) return ''; if (v < 0) return 0; return v > max ? max : v; }
function stationLabel(id) { if (!id) return '—'; return props.stations.find((s) => String(s.id) === String(id))?.name ?? ('#' + id); }
function partyLabel(items, id, fallback) { if (!id) return fallback || '—'; return items.find((i) => String(i.id) === String(id))?.name ?? (fallback ?? ('#' + id)); }
function customerLabel(id, fallback) { return String(partyLabel(props.customers, id, fallback)).toLocaleLowerCase().replace(/(^|\s)\S/g, (character) => character.toLocaleUpperCase()); }
function fieldErrorMessage(f) { const e = props.fieldErrors[f]; return Array.isArray(e) ? e[0] : e; }
function legFieldError(i, f) { const e = props.fieldErrors['route_legs.' + i + '.' + f]; return Array.isArray(e) ? e[0] : e; }
function formatNum(v) { return Number(v ?? 0).toLocaleString('en-IN', { maximumFractionDigits: 3 }); }
function formatMoney(v) { return Number(v ?? 0).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }
function openQuickAddForLeg(i) { quickAddLegIndex.value = i; showStationQuickAdd.value = true; }
function openQuickAddForTripCustomer() { quickAddCustomerLegIndex.value = null; showCustomerQuickAdd.value = true; }
function openQuickAddForLegCustomer(i) { quickAddCustomerLegIndex.value = i; showCustomerQuickAdd.value = true; }
function openQuickAddForLegGoodsType(i) { quickAddGoodsTypeLegIndex.value = i; showGoodsTypeQuickAdd.value = true; }
function onStationCreated(s) { emit('station-created', { station: s, legIndex: quickAddLegIndex.value }); quickAddLegIndex.value = null; saveProgress(); }
function onBrokerCreated(b) { emit('broker-created', b); saveProgress(); }
function onCustomerCreated(customer) { emit('customer-created', { customer, legIndex: quickAddCustomerLegIndex.value }); quickAddCustomerLegIndex.value = null; saveProgress(); }
function onGoodsTypeCreated(g) { emit('goods-type-created', { goodsType: g, legIndex: quickAddGoodsTypeLegIndex.value }); quickAddGoodsTypeLegIndex.value = null; saveProgress(); }
function handleVehicleSelection(v) { emit('vehicle-selected', v); saveProgress(); }
function handleStartStationPickerChange() { emit('start-station-change'); emit('clear-field-error', 'start_station_id'); saveProgress(); }
function handleLegStopChange(i) { emit('stop-change', i); saveProgress(); }
function handleEnterKey(e) { const t = e.target; if (t.tagName === 'TEXTAREA' || t.tagName === 'BUTTON' || t.type === 'submit') return; const all = Array.from(document.querySelectorAll('input:not([disabled]):not([readonly]),select:not([disabled])')); const idx = all.indexOf(t); if (idx > -1 && idx < all.length - 1) all[idx + 1].focus(); }
function saveProgress() { if (props.mode === 'create') { try { localStorage.setItem('truckbill_trip_create_draft', JSON.stringify(props.form)); } catch {} } }
function loadProgressDraft() { if (props.mode === 'create') { const d = localStorage.getItem('truckbill_trip_create_draft'); if (d) { try { const p = JSON.parse(d); Object.keys(p).forEach((k) => { if (props.form[k] !== undefined) props.form[k] = p[k]; }); toast.info('Restored unsaved draft.'); } catch {} } } }
onMounted(() => { loadProgressDraft(); });
defineExpose({ changeStatus: () => {} });
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
    font-family: system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif;
    color: var(--text);
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
    padding: 18px 24px;
    border-bottom: 1px solid var(--border);
    background: linear-gradient(135deg, #e7f1ff 0%, #f8f9fa 100%);
    gap: 16px;
}
.trf-section__head--green { background: linear-gradient(135deg, #d1e7dd 0%, #f8f9fa 100%); border-bottom-color: #badbcc; }
.trf-section__head--plain { background: #fafafa; }
.trf-section__head--toggle { cursor: pointer; user-select: none; }
.trf-section__head--toggle:hover { background: #f3f4f6; }
.trf-section__head-left { display: flex; align-items: center; gap: 14px; }
.trf-section__title { font-size: 1rem; font-weight: 700; color: var(--text); margin: 0; line-height: 1.3; }
.trf-section__desc { font-size: .78rem; color: var(--muted); margin: 2px 0 0; }
.trf-section__body { padding: 24px; }

/* ── Step Numbers ───────────────────── */
.trf-step-num {
    width: 36px; height: 36px; border-radius: 50%;
    background: var(--primary); color: #fff;
    font-weight: 800; font-size: .9rem;
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
.trf-picker-grid--3 { grid-template-columns: repeat(3, minmax(0, 1fr)); }
.trf-picker-grid--6 { grid-template-columns: repeat(6, minmax(0, 1fr)); }
@media (max-width: 1399px) {
    .trf-picker-grid--5 { grid-template-columns: repeat(3, minmax(0, 1fr)); }
    .trf-picker-grid--6 { grid-template-columns: repeat(3, minmax(0, 1fr)); }
}
@media (max-width: 1199px) {
    .trf-picker-grid,
    .trf-picker-grid--5 { grid-template-columns: repeat(3, minmax(0, 1fr)); }
}
@media (max-width: 767px)  {
    .trf-picker-grid,
    .trf-picker-grid--3,
    .trf-picker-grid--5,
    .trf-picker-grid--6 { grid-template-columns: repeat(2, minmax(0, 1fr)); }
}
@media (max-width: 480px)  {
    .trf-picker-grid,
    .trf-picker-grid--3,
    .trf-picker-grid--5,
    .trf-picker-grid--6 { grid-template-columns: 1fr; }
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
    min-height: 24px;
    font-size: .75rem; font-weight: var(--form-label-weight, 600); color: var(--form-label-color, #475569);
    text-transform: uppercase; letter-spacing: .04em;
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
.trf-pf__req { color: var(--danger); font-size: .85rem; font-weight: 700; margin-left: 2px; }
.trf-pf__subst {
    flex-shrink: 0;
    white-space: nowrap;
    font-size: .64rem;
    text-transform: none;
    letter-spacing: 0;
}
.trf-pf__subst-toggle {
    display: inline-flex; align-items: center; gap: 5px;
    margin-left: auto; cursor: pointer;
    font-size: .68rem; color: var(--muted);
    font-weight: 600; text-transform: none; letter-spacing: 0;
    padding: 2px 7px; border-radius: 5px;
    border: 1px solid #e5e7eb; background: #f9fafb;
    transition: all .12s;
}
.trf-pf__subst-toggle:hover { background: #eff6ff; border-color: #bfdbfe; color: #1d4ed8; }
.trf-pf__toggle { display: flex; align-items: center; gap: 5px; margin-left: auto; }
.trf-pf__toggle-cb { cursor: pointer; }
.trf-pf__toggle-lbl { font-size: .68rem; color: var(--muted); cursor: pointer; text-transform: none; letter-spacing: 0; }
.trf-pf__err { font-size: .73rem; color: var(--danger); font-weight: 600; }

/* ── Form Inputs ────────────────────── */
.trf-input {
    width: 100%;
    min-height: 44px;
    padding: 10px 13px;
    font-size: .9rem;
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
.trf-input--error { border-color: var(--danger); }
.trf-input--textarea { resize: vertical; line-height: 1.6; min-height: 3rem; }
.trf-input--readonly {
    background: #f8f9fa;
    color: #94a3b8;
    cursor: default;
}

.trf-date-input :deep(.app-date-input__text) {
    width: 100%;
    min-height: 52px;
    height: 52px;
    padding: 10px 40px 10px 13px;
    font-size: .9rem;
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
    min-height: 44px;
    padding: 10px 36px 10px 13px;
    font-size: .9rem;
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
    min-height: 44px; padding: 10px 13px;
    border: 1.5px solid var(--border); border-radius: 8px;
    background: #f9fafb;
}
.trf-ro-val__main { font-weight: 600; font-size: .9rem; color: var(--text); }
.trf-ro-val__sub { font-size: .74rem; color: var(--muted); margin-top: 2px; }

/* Driver auto-sync box — matches Truck/Customer picker height & look */
.trf-synced-field {
    display: flex;
    flex-direction: column;
    justify-content: center;
    width: 100%;
    max-width: 100%;
    min-width: 0;
    height: 52px;
    min-height: 52px;
    padding: 8px 13px;
    border: 1.5px solid var(--border);
    border-radius: 8px;
    background: #fff;
    box-sizing: border-box;
    overflow: hidden;
}
.trf-synced-field--empty {
    background: #f8f9fa;
}
.trf-synced-field__main {
    display: block;
    font-weight: 600;
    font-size: .9rem;
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
    margin-top: 2px;
    font-size: 12px;
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
    height: 52px;
    min-height: 52px !important;
    box-sizing: border-box;
    overflow: hidden;
}
.trf-pf :deep(.picker-trigger-content-wrap) {
    min-width: 0;
    max-width: 100%;
    overflow: hidden;
}
.trf-pf :deep(.picker-trigger-main-text),
.trf-pf :deep(.picker-trigger-sub-text) {
    max-width: 100%;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

/* ── Vehicle Information Cards ──────── */
.trf-vehicle-info {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 12px;
}
.trf-vehicle-info__item {
    display: flex;
    align-items: center;
    gap: 12px;
    min-height: 72px;
    padding: 14px 16px;
    border: 1.5px solid var(--border);
    border-radius: 10px;
    background: #fafafa;
}
.trf-vehicle-info__icon {
    width: 40px;
    height: 40px;
    border-radius: 10px;
    flex-shrink: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.1rem;
    background: var(--bg);
    color: var(--ic);
}
.trf-vehicle-info__name {
    display: block;
    font-size: .68rem;
    font-weight: 700;
    color: var(--muted);
    text-transform: uppercase;
    letter-spacing: .04em;
}
.trf-vehicle-info__val {
    display: block;
    margin-top: 2px;
    font-size: .95rem;
    font-weight: 800;
    color: var(--text);
}
.trf-vehicle-info__field {
    min-width: 0;
    flex: 1;
}
.trf-vehicle-info__input {
    display: block;
    width: 100%;
    max-width: 120px;
    margin-top: 2px;
    padding: 0;
    border: none;
    border-bottom: 2px solid transparent;
    background: transparent;
    outline: none;
    font-size: .95rem;
    font-weight: 800;
    color: var(--text);
}
.trf-vehicle-info__input:focus {
    border-bottom-color: var(--primary);
}
.trf-vehicle-info__item--editable {
    background: #fff;
}
@media (max-width: 1199px) {
    .trf-vehicle-info { grid-template-columns: repeat(2, minmax(0, 1fr)); }
}
@media (max-width: 575.98px) {
    .trf-vehicle-info { grid-template-columns: 1fr; }
}

/* ── Banners ────────────────────────── */
.trf-banner {
    display: flex; align-items: flex-start; gap: 10px;
    padding: 12px 16px; border-radius: 8px; font-size: .875rem; line-height: 1.5;
}
.trf-banner--error { background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; }
.trf-banner--warn  { background: #fffbeb; border: 1px solid #fde68a; color: #92400e; }

/* ── Pills (Badges) ─────────────────── */
.trf-pill {
    display: inline-flex; align-items: center;
    padding: 3px 10px; border-radius: 999px;
    font-size: .7rem; font-weight: 700;
    letter-spacing: .03em; text-transform: uppercase;
}
.trf-pill--blue   { background: #cfe2ff; color: #084298; }
.trf-pill--green  { background: #d1e7dd; color: #0f5132; }
.trf-pill--red    { background: #f8d7da; color: #842029; }
.trf-pill--orange { background: #fff3cd; color: #664d03; }

/* ── Route Home Base ────────────────── */
.trf-route-home {
    display: grid;
    grid-template-columns: 36px minmax(210px, 240px) 1fr;
    gap: 12px;
    align-items: stretch;
    padding: 4px 0 0;
}
.trf-route-home__timeline { display: flex; flex-direction: column; align-items: center; min-height: 78px; }
.trf-route-home__dot {
    width: 36px; height: 36px; border-radius: 50%;
    background: var(--success); color: #fff;
    display: flex; align-items: center; justify-content: center;
    font-size: .95rem; flex-shrink: 0;
    position: relative; z-index: 1;
}
.trf-route-home__line {
    width: 2px;
    flex: 1;
    min-height: 42px;
    background: repeating-linear-gradient(
        to bottom,
        #6ee7b7,
        #6ee7b7 6px,
        transparent 6px,
        transparent 12px
    );
}
.trf-route-home__content { min-width: 0; padding-bottom: 14px; }
.trf-route-home__info { display: flex; align-items: center; gap: 4px; min-height: 24px; }
.trf-route-home__label { font-size: .7rem; font-weight: 800; color: #343a40; text-transform: uppercase; letter-spacing: .04em; }
.trf-route-home__req { font-size: .85rem; color: var(--danger); font-weight: 700; }
.trf-route-home__picker { width: 100%; }

/* ── Route Empty ────────────────────── */
.trf-route-empty { padding: 8px 0 0; }
.trf-route-empty__dashed {
    border: 2px dashed #d1d5db; border-radius: 10px;
    padding: 32px; text-align: center; color: var(--muted);
}
.trf-route-empty__dashed i { font-size: 2rem; display: block; margin-bottom: 10px; opacity: .35; }

/* ── Leg Layout ─────────────────────── */
.trf-legs { display: flex; flex-direction: column; gap: 0; }
.trf-leg {
    display: grid;
    grid-template-columns: 36px minmax(210px, 240px) minmax(0, 1fr);
    gap: 0 12px;
    align-items: stretch;
}
.trf-leg__timeline {
    position: relative;
    display: flex;
    justify-content: center;
    padding-top: 24px;
    min-height: 100%;
}
.trf-leg__timeline::before,
.trf-leg__timeline::after {
    content: '';
    position: absolute;
    left: 50%;
    width: 2px;
    transform: translateX(-50%);
    background: repeating-linear-gradient(
        to bottom,
        #6ee7b7,
        #6ee7b7 6px,
        transparent 6px,
        transparent 12px
    );
}
.trf-leg__timeline::before { top: 0; height: 24px; }
.trf-leg__timeline::after { top: 60px; bottom: 0; }
.trf-leg:last-child .trf-leg__timeline::after { display: none; }
.trf-leg__dot {
    width: 36px; height: 36px; border-radius: 50%;
    border: 2.5px solid var(--success); background: #fff; color: var(--success);
    font-weight: 800; font-size: .85rem;
    display: flex; align-items: center; justify-content: center; flex-shrink: 0;
    position: relative; z-index: 1;
}

.trf-leg__station { display: flex; flex-direction: column; gap: 6px; padding-top: 12px; padding-bottom: 18px; position: relative; }
.trf-leg__station-head {
    display: flex;
    align-items: center;
    justify-content: flex-start;
    gap: 10px;
    min-height: 24px;
    /* leave room on the right for the picker's eye/add actions */
    padding-right: 58px;
}
.trf-leg__station-tag { font-size: .68rem; font-weight: 800; color: var(--muted); text-transform: uppercase; letter-spacing: .04em; }
.trf-leg__remove-btn {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    min-height: 26px;
    padding: 2px 8px;
    border: 1px solid #f1aeb5;
    border-radius: 6px;
    background: #fff;
    color: #b02a37;
    font-size: .7rem;
    font-weight: 700;
    line-height: 1;
    cursor: pointer;
    transition: background .15s, border-color .15s;
}
.trf-leg__remove-btn:hover:not(:disabled) {
    border-color: #dc3545;
    background: #fff5f5;
}
.trf-leg__remove-btn:disabled {
    opacity: .45;
    cursor: not-allowed;
}

/* ── Leg Card ───────────────────────── */
.trf-leg__card {
    border: 1px solid #e9ecef;
    border-radius: 8px;
    padding: 12px 14px 0;
    background: #fff;
    margin-bottom: 12px;
}
.trf-leg__dist { font-size: .75rem; font-weight: 600; color: var(--muted); display: flex; align-items: center; gap: 4px; }
.trf-leg__fields {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    column-gap: 16px;
    row-gap: 14px;
    align-items: start;
}
@media (max-width: 767px)  { .trf-leg__fields { grid-template-columns: repeat(2,1fr); } }
.trf-leg__meta {
    display: flex;
    align-items: center;
    justify-content: flex-start;
    gap: 20px;
    margin: 10px -14px 0;
    padding: 8px 14px;
    border-top: 1px solid #eef1f4;
    background: #f8f9fa;
    font-size: .72rem;
    font-weight: 600;
    color: #6c757d;
}
.trf-leg__pricing { display: flex; align-items: center; gap: 8px; }
.trf-leg__meta-label { color: #495057; font-weight: 700; }
.trf-leg__pricing-status {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    color: #198754;
}
.trf-leg__pricing-status--missing { color: #b45309; }
.trf-leg__load {
    color: #495057;
}
@media (max-width: 991.98px) {
    .trf-route-home {
        grid-template-columns: 36px minmax(0, 1fr);
    }
    .trf-leg {
        grid-template-columns: 36px minmax(0, 1fr);
    }
    .trf-leg__timeline {
        grid-row: 1 / span 2;
    }
    .trf-leg__station,
    .trf-leg__card {
        grid-column: 2;
    }
    .trf-leg__station {
        padding-bottom: 8px;
    }
    .trf-leg__meta { justify-content: flex-start; flex-wrap: wrap; }
}
@media (max-width: 575.98px) {
    .trf-route-home,
    .trf-leg {
        grid-template-columns: 30px minmax(0, 1fr);
        gap: 8px;
    }
    .trf-route-home__dot,
    .trf-leg__dot {
        width: 30px;
        height: 30px;
    }
    .trf-leg__timeline::after {
        top: 54px;
    }
    .trf-leg__fields {
        grid-template-columns: 1fr;
    }
    .trf-leg__card {
        padding: 10px 10px 0;
    }
    .trf-leg__meta {
        margin-right: -10px;
        margin-left: -10px;
        padding-right: 10px;
        padding-left: 10px;
    }
    .trf-leg__pricing {
        align-items: flex-start;
        flex-direction: column;
        gap: 6px;
    }
}

/* ── Leg Fields ─────────────────────── */
.trf-lf { display: flex; min-width: 0; flex-direction: column; gap: 5px; }
.trf-lf--sm {}
.trf-lf__lbl { font-size: .7rem; font-weight: 700; color: var(--muted); text-transform: uppercase; letter-spacing: .03em; }
.trf-field-head,
.trf-freight-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 6px;
    min-width: 0;
}
.trf-field-head--with-picker {
    justify-content: flex-start;
    gap: 10px;
    /* leave room for SearchablePicker eye/add actions on the right */
    padding-right: 56px;
    min-height: 24px;
}
.trf-lf__picker {
    position: relative;
}
.trf-field-head__check,
.trf-freight-auto {
    flex-shrink: 0;
    white-space: nowrap;
    font-size: .64rem;
}
.trf-num-field { position: relative; }
.trf-leg__fields .trf-input,
.trf-leg__fields .trf-num-field,
.trf-leg__fields .trf-same-cust {
    width: 100%;
    min-height: 44px;
    box-sizing: border-box;
}
.trf-num-field--prefix .trf-input { padding-left: 24px; }
.trf-num-field__prefix { position: absolute; left: 9px; top: 50%; transform: translateY(-50%); font-size: .85rem; font-weight: 700; color: var(--muted); }
.trf-same-cust { padding: 9px 12px; font-size: .85rem; font-weight: 600; color: var(--muted); border: 1.5px dashed var(--border); border-radius: 8px; background: #f9fafb; }
.trf-check-label { display: flex; align-items: center; gap: 5px; font-size: .72rem; color: var(--muted); cursor: pointer; }

/* ── Add Stop Row ───────────────────── */
.trf-add-stop-row {
    width: 100%;
    display: flex; align-items: center; justify-content: center;
    min-height: 38px;
    border: 2px dashed #d1d5db; border-radius: 10px;
    padding: 7px 14px; cursor: pointer;
    background: transparent; font-size: .875rem; font-weight: 600; color: var(--success);
    margin-top: 4px; transition: all .15s;
}
.trf-add-stop-row:hover { border-color: var(--success); background: #d1e7dd33; }

/* ── Summary Metrics ────────────────── */
.trf-metrics {
    display: grid;
    grid-template-columns: repeat(6, 1fr);
    gap: 16px;
}
@media (max-width: 1199px) { .trf-metrics { grid-template-columns: repeat(3, 1fr); } }
@media (max-width: 767px)  { .trf-metrics { grid-template-columns: repeat(2, 1fr); } }

.trf-metric {
    display: flex; flex-direction: column; align-items: center;
    padding: 20px 12px; border: 1.5px solid var(--border);
    border-radius: 12px; background: #fff; text-align: center;
    transition: box-shadow .2s, transform .2s;
    cursor: default;
}
.trf-metric:hover { box-shadow: var(--shadow-md); transform: translateY(-2px); }
.trf-metric__icon {
    width: 48px; height: 48px; border-radius: 50%;
    background: var(--bg); color: var(--c);
    display: flex; align-items: center; justify-content: center;
    font-size: 1.25rem; margin-bottom: 10px;
}
.trf-metric__lbl { font-size: .68rem; font-weight: 700; color: var(--muted); text-transform: uppercase; letter-spacing: .05em; margin-bottom: 4px; }
.trf-metric__val { font-size: 1.1rem; font-weight: 800; color: var(--text); }

/* ── Collapse Arrow ─────────────────── */
.trf-collapse-arrow { color: var(--muted); font-size: .95rem; transition: transform .25s; }
.trf-collapse-arrow--open { transform: rotate(180deg); }

/* ── Action Buttons ─────────────────── */
.trf-primary-btn {
    display: inline-flex; align-items: center;
    padding: 8px 18px; border-radius: 8px;
    background: var(--success); color: #fff;
    border: none; font-weight: 600; font-size: .85rem; cursor: pointer;
    transition: background .15s, box-shadow .15s;
}
.trf-primary-btn:hover { background: var(--success-dark); box-shadow: 0 2px 8px rgba(25,135,84,.35); }
.trf-outline-btn {
    display: inline-flex; align-items: center;
    padding: 8px 16px; border-radius: 8px;
    background: transparent; color: var(--success);
    border: 1.5px solid var(--success); font-weight: 600; font-size: .85rem; cursor: pointer;
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
    padding: 12px 18px; font-size: .875rem; font-weight: 500; line-height: 1.5;
}
.trf-validation-bar__row--error { background: #dc2626; color: #fff; }
.trf-validation-bar__row--warn  { background: #f59e0b; color: #fff; }
.trf-validation-bar__row i { flex-shrink: 0; margin-top: 2px; }

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
