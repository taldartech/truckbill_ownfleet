<template>
    <div class="container mt-4 trip-detail-page pb-4">
        <div v-if="needsCompanySelection" class="alert alert-warning">
            Select a company and click <strong>Enter</strong> to view trip details.
        </div>

        <div v-if="loading" class="text-center py-5 text-muted">Loading trip details…</div>

        <template v-else-if="trip">
            <div v-if="isArchived" class="alert alert-warning mb-3">
                <i class="bi bi-archive me-1"></i>
                This trip has been archived and is read-only. It cannot be edited or modified.
                <span v-if="trip.archive_reason" class="d-block mt-1 small mb-0">
                    Reason: {{ trip.archive_reason }}
                </span>
                <span v-if="canRestoreThisTrip" class="d-block mt-1 small mb-0">
                    Use Restore to return it to the active trip list.
                </span>
            </div>

            <div v-if="terminalAlert" class="alert alert-secondary mb-3">
                <i class="bi bi-lock me-1"></i>
                {{ terminalAlert }}
            </div>

            <div class="row g-3">
                <div class="col-lg-8">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <strong>Trip Information ({{ trip.trip_number || '—' }})</strong>
                            <div
                                class="app-index-table-actions ms-auto"
                                style="margin-left: auto !important; display: inline-flex !important; gap: 0.375rem !important;"
                            >
                                <RouterLink
                                    :to="tripListReturnTo"
                                    class="btn btn-sm btn-outline-secondary btn-icon app-index-action-btn"
                                    :title="trip.is_archived ? 'Back to Archived Trips' : 'Back to Trips'"
                                    style="background-color: #fff; border-color: #cbd5e1; color: #475569;"
                                >
                                    <i class="fas fa-arrow-left" style="color: #475569 !important;" aria-hidden="true"></i>
                                </RouterLink>
                                <AppCrudActionButton
                                    v-if="canManage && canManageTripExpensesFor"
                                    action="expenses"
                                    title="Manage expenses"
                                    @click="goToExpenses"
                                />
                                <AppCrudActionButton
                                    v-if="canGenerateInvoices"
                                    action="invoice"
                                    title="Generate invoice(s)"
                                    @click="openGenerateInvoices"
                                />
                                <AppCrudActionButton
                                    v-if="canPrintBilty"
                                    action="bilty"
                                    title="Print bilty"
                                    @click="openPrintBilty"
                                />
                                <AppCrudActionButton
                                    v-if="canManage && canEditTripPage"
                                    action="edit"
                                    title="Edit trip"
                                    @click="goToEdit"
                                />
                                <AppCrudActionButton
                                    v-if="canArchiveThisTrip && !isArchived"
                                    action="archive"
                                    title="Archive trip"
                                    :disabled="archivingTrip"
                                    @click="handleArchive"
                                />
                                <AppCrudActionButton
                                    v-if="canRestoreThisTrip"
                                    action="restore"
                                    title="Restore trip"
                                    :disabled="restoringTrip"
                                    @click="openRestoreConfirm"
                                />
                            </div>
                        </div>
                        <div class="card-body trip-info-grid small">
                            <div class="trip-info-grid__item">
                                <span class="trip-info-grid__label">Start Station</span>
                                <div class="trip-info-grid__value">{{ startStationLabel }}</div>
                            </div>
                            <div class="trip-info-grid__item">
                                <span class="trip-info-grid__label">Trip Type</span>
                                <div class="trip-info-grid__value">{{ tripTypeLabel(trip.trip_type) }}</div>
                            </div>
                            <div class="trip-info-grid__item">
                                <span class="trip-info-grid__label">Start Date</span>
                                <div class="trip-info-grid__value">{{ formatDate(trip.start_at) }}</div>
                            </div>
                            <div class="trip-info-grid__item">
                                <span class="trip-info-grid__label d-flex align-items-center gap-1 flex-wrap">
                                    End Date
                                    <span
                                        v-if="arrivalTiming === 'on-time'"
                                        class="badge bg-info text-dark"
                                        title="Arrived on planned end date"
                                    >On Time</span>
                                    <span
                                        v-else-if="arrivalTiming === 'early'"
                                        class="badge bg-success"
                                        title="Arrived before planned end date"
                                    >Early</span>
                                    <span
                                        v-else-if="arrivalTiming === 'late'"
                                        class="badge bg-danger"
                                        title="Arrived after planned end date"
                                    >Late</span>
                                </span>
                                <div class="trip-info-grid__value">{{ formatDate(trip.end_at) }}</div>
                            </div>
                            <div class="trip-info-grid__item">
                                <span class="trip-info-grid__label">Actual Arrival</span>
                                <div class="trip-info-grid__value">{{ formatDate(trip.actual_arrival_at) }}</div>
                            </div>
                            <div class="trip-info-grid__item">
                                <span class="trip-info-grid__label">Truck</span>
                                <div class="trip-info-grid__value">
                                    <RouterLink
                                        v-if="trip.vehicle?.id"
                                        :to="{ name: 'trucks.show', params: { vehicleId: trip.vehicle.id } }"
                                        class="text-decoration-none fw-semibold"
                                    >
                                        {{ trip.vehicle.registration_number }}
                                    </RouterLink>
                                    <span v-else>—</span>
                                </div>
                                <div v-if="vehicleMeta" class="trip-info-grid__sub">{{ vehicleMeta }}</div>
                            </div>
                            <div class="trip-info-grid__item">
                                <span class="trip-info-grid__label">Truck Type</span>
                                <div class="trip-info-grid__value">{{ trip.vehicle?.vehicle_type_label ?? trip.vehicle?.vehicle_type ?? '—' }}</div>
                            </div>
                            <div class="trip-info-grid__item">
                                <span class="trip-info-grid__label">Truck Running Meter</span>
                                <div class="trip-info-grid__value">{{ formatKm(trip.vehicle?.odometer_km) }}</div>
                            </div>
                            <div class="trip-info-grid__item">
                                <span class="trip-info-grid__label">Expected Mileage</span>
                                <div class="trip-info-grid__value">{{ trip.planned_mileage_km_per_liter ? `${formatKm(trip.planned_mileage_km_per_liter)} KM/LTR` : '—' }}</div>
                            </div>
                            <div class="trip-info-grid__item">
                                <span class="trip-info-grid__label">Max Load</span>
                                <div class="trip-info-grid__value">{{ trip.max_load_ton ? `${formatKm(trip.max_load_ton)} Ton` : '—' }}</div>
                            </div>
                            <div class="trip-info-grid__item">
                                <span class="trip-info-grid__label">Expected Diesel</span>
                                <div class="trip-info-grid__value">{{ trip.planned_diesel_liters ? `${formatKm(trip.planned_diesel_liters)} LTR` : '—' }}</div>
                            </div>
                            <div class="trip-info-grid__item">
                                <span class="trip-info-grid__label">Diesel Rate</span>
                                <div class="trip-info-grid__value">{{ trip.diesel_rate_per_liter ? `₹${formatMoney(trip.diesel_rate_per_liter)}/LTR` : '—' }}</div>
                            </div>
                            <div class="trip-info-grid__item">
                                <span class="trip-info-grid__label">Start Meter</span>
                                <div class="trip-info-grid__value">{{ formatOdometerReading(trip.start_meter_reading_km) }}</div>
                            </div>
                            <div class="trip-info-grid__item">
                                <span class="trip-info-grid__label">End Meter</span>
                                <div class="trip-info-grid__value">{{ formatOdometerReading(trip.end_meter_reading_km) }}</div>
                            </div>
                            <div class="trip-info-grid__item">
                                <span class="trip-info-grid__label">Primary Driver</span>
                                <div class="trip-info-grid__value fw-semibold">{{ trip.driver?.name ?? '—' }}</div>
                                <div v-if="trip.driver?.phone" class="trip-info-grid__sub">{{ trip.driver.phone }}</div>
                            </div>
                            <div class="trip-info-grid__item">
                                <span class="trip-info-grid__label">Substitute Driver</span>
                                <div class="trip-info-grid__value">{{ trip.substitute_driver?.name ?? '—' }}</div>
                                <div v-if="trip.substitute_driver?.phone" class="trip-info-grid__sub">
                                    {{ trip.substitute_driver.phone }}
                                </div>
                            </div>
                            <div class="trip-info-grid__item">
                                <span class="trip-info-grid__label">Customer</span>
                                <div class="trip-info-grid__value">{{ trip.customer_name || '—' }}</div>
                            </div>
                            <div class="trip-info-grid__item trip-info-grid__item--ref-status">
                                <div class="trip-info-ref-status">
                                    <div class="trip-info-ref-status__ref">
                                        <span class="trip-info-grid__label">Reference</span>
                                        <div class="trip-info-grid__value">{{ trip.broker_name || '—' }}</div>
                                    </div>
                                    <div class="trip-info-ref-status__status">
                                        <span class="trip-info-grid__label">Status</span>
                                        <div class="trip-info-ref-status__actions">
                                            <TripInlineStatusActions
                                                v-if="!isArchived"
                                                :trip="trip"
                                                :can-manage="canManage && canEditTripStatus"
                                                compact
                                                @updated="onTripUpdated"
                                            />
                                            <span v-else class="badge" :class="statusMeta.badge">{{ statusMeta.label }}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div v-if="trip.notes?.trim()" class="trip-info-grid__item trip-info-grid__item--full">
                                <span class="trip-info-grid__label">Notes</span>
                                <div class="trip-info-grid__value" style="white-space: pre-wrap">{{ trip.notes }}</div>
                            </div>
                            <div v-if="trip.status === 'CANCELLED'" class="trip-info-grid__item">
                                <span class="trip-info-grid__label">Cancelled On</span>
                                <div class="trip-info-grid__value">{{ formatDateTime(trip.cancelled_at) }}</div>
                            </div>
                            <div v-if="trip.status === 'CANCELLED'" class="trip-info-grid__item trip-info-grid__item--full">
                                <div class="alert alert-danger mb-0 py-2">
                                    <div class="small">
                                        <strong>Cancellation reason:</strong>
                                        {{ trip.cancellation_reason || '—' }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card mt-3">
                        <div class="card-header trip-route-header">
                            <strong class="trip-route-header__title">{{ routeSectionTitle }}</strong>
                            <span
                                v-if="routeLegs.length > 0 || expenses.length > 0"
                                class="trip-route-header__totals"
                            >
                                ₹{{ formatMoney(trip.total_freight_amount) }} freight
                                · ₹{{ formatMoney(trip.total_expense_amount) }} expenses
                            </span>
                        </div>
                        <div class="card-body">
                            <div v-if="routePreview" class="alert alert-light border mb-3 py-2 small">
                                <strong>Route:</strong> {{ routePreview }}
                            </div>
                            <div
                                v-if="routeLegs.length === 0 && expenses.length === 0"
                                class="text-muted text-center py-4"
                            >
                                No route legs or expenses recorded.
                            </div>
                            <div v-else class="trip-detail-stops">
                                <div
                                    v-if="generalExpenseGroup"
                                    class="trip-detail-stop"
                                    :class="{ 'trip-detail-stop--collapsed': !isRouteSectionExpanded('general') }"
                                >
                                    <button
                                        type="button"
                                        class="trip-detail-stop__head trip-detail-stop__head--btn"
                                        :aria-expanded="isRouteSectionExpanded('general')"
                                        @click="toggleRouteSection('general')"
                                    >
                                        <div class="trip-detail-stop__head-main">
                                            <span class="trip-detail-stop__num">Whole trip</span>
                                            <span class="trip-detail-stop__sep">·</span>
                                            <span class="trip-detail-stop__customer">General expenses</span>
                                            <span class="trip-detail-stop__sep">·</span>
                                            <span class="trip-detail-stop__expense-count">
                                                {{ generalExpenseGroup.items.length }}
                                                expense{{ generalExpenseGroup.items.length === 1 ? '' : 's' }}
                                            </span>
                                        </div>
                                        <div class="trip-detail-stop__head-meta">
                                            <span class="trip-detail-stop__expense">
                                                Exp ₹{{ formatMoney(generalExpenseGroup.total) }}
                                            </span>
                                            <i
                                                class="bi"
                                                :class="isRouteSectionExpanded('general') ? 'bi-chevron-up' : 'bi-chevron-down'"
                                                aria-hidden="true"
                                            ></i>
                                        </div>
                                    </button>
                                    <div v-if="isRouteSectionExpanded('general')" class="trip-detail-stop__body">
                                        <div class="trip-detail-stop__expenses">
                                            <div class="trip-detail-stop__expenses-title">Expenses</div>
                                            <div class="table-responsive">
                                                <table class="table table-sm table-hover align-middle mb-0">
                                                    <thead class="table-light">
                                                        <tr>
                                                            <th>Date</th>
                                                            <th>Type</th>
                                                            <th>Meter Reading(KM)</th>
                                                            <th>Liters</th>
                                                            <th>Rate</th>
                                                            <th>Amount</th>
                                                            <th>Notes</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <tr
                                                            v-for="expense in generalExpenseGroup.items"
                                                            :key="expense.id"
                                                        >
                                                            <td>{{ formatDate(expense.expense_date) }}</td>
                                                            <td>{{ expenseTypeLabel(expense.expense_type, expense.other_type) }}</td>
                                                            <td>{{ formatOdometerReading(expense.fuel_odometer_km) }}</td>
                                                            <td>{{ expense.fuel_liters ?? '—' }}</td>
                                                            <td>{{ expense.fuel_rate ?? '—' }}</td>
                                                            <td>₹{{ formatMoney(expense.amount) }}</td>
                                                            <td class="text-muted">{{ expense.notes || '—' }}</td>
                                                        </tr>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div
                                    v-for="stop in routeStopGroups"
                                    :key="stop.key"
                                    class="trip-detail-stop"
                                    :class="{ 'trip-detail-stop--collapsed': !isRouteSectionExpanded(stop.key) }"
                                >
                                    <button
                                        type="button"
                                        class="trip-detail-stop__head trip-detail-stop__head--btn"
                                        :aria-expanded="isRouteSectionExpanded(stop.key)"
                                        @click="toggleRouteSection(stop.key)"
                                    >
                                        <div class="trip-detail-stop__head-main">
                                            <span class="trip-detail-stop__num">Stop {{ stop.sequence }}</span>
                                            <span class="trip-detail-stop__sep">·</span>
                                            <span class="trip-detail-stop__route">
                                                {{ stop.from }}
                                                →
                                                {{ stop.to }}
                                            </span>
                                            <span class="trip-detail-stop__sep">·</span>
                                            <span
                                                class="trip-route-load-tag"
                                                :class="`trip-route-load-tag--${legLoadCategoryMeta(stop.leg.weight_ton, trip.max_load_ton).category}`"
                                            >
                                                {{ legLoadCategoryMeta(stop.leg.weight_ton, trip.max_load_ton).label }}
                                            </span>
                                            <span class="trip-detail-stop__sep">·</span>
                                            <span class="trip-detail-stop__customer">
                                                {{ stop.leg.customer_name || trip.customer_name || '—' }}
                                            </span>
                                        </div>
                                        <div class="trip-detail-stop__head-meta">
                                            <span
                                                class="trip-detail-stop__freight"
                                                :class="{ 'is-empty': !(Number(stop.leg.freight_amount) > 0) }"
                                            >
                                                {{ Number(stop.leg.freight_amount) > 0 ? `₹${formatMoney(stop.leg.freight_amount)}` : 'No freight' }}
                                            </span>
                                            <span
                                                class="trip-detail-stop__expense"
                                                :class="{ 'is-empty': !(stop.expenseTotal > 0) }"
                                            >
                                                {{ stop.expenseTotal > 0 ? `Exp ₹${formatMoney(stop.expenseTotal)}` : 'No expenses' }}
                                            </span>
                                            <i
                                                class="bi"
                                                :class="isRouteSectionExpanded(stop.key) ? 'bi-chevron-up' : 'bi-chevron-down'"
                                                aria-hidden="true"
                                            ></i>
                                        </div>
                                    </button>

                                    <div v-if="isRouteSectionExpanded(stop.key)" class="trip-detail-stop__body">
                                        <div class="trip-detail-stop__fields">
                                            <div class="trip-detail-stop__field">
                                                <span class="trip-detail-stop__label">Customer</span>
                                                <span class="trip-detail-stop__value">{{ stop.leg.customer_name || trip.customer_name || '—' }}</span>
                                            </div>
                                            <div class="trip-detail-stop__field">
                                                <span class="trip-detail-stop__label">Consignor (From)</span>
                                                <span class="trip-detail-stop__value">{{ stop.leg.consignor || '—' }}</span>
                                            </div>
                                            <div class="trip-detail-stop__field">
                                                <span class="trip-detail-stop__label">Consignee (To)</span>
                                                <span class="trip-detail-stop__value">{{ stop.leg.consignee || '—' }}</span>
                                            </div>
                                            <div class="trip-detail-stop__field">
                                                <span class="trip-detail-stop__label">Pickup Date</span>
                                                <span class="trip-detail-stop__value">{{ formatDate(stop.leg.pickup_date) }}</span>
                                            </div>
                                            <div class="trip-detail-stop__field">
                                                <span class="trip-detail-stop__label">Goods Type</span>
                                                <span class="trip-detail-stop__value">{{ stop.leg.goods_name || '—' }}</span>
                                            </div>
                                            <div class="trip-detail-stop__field">
                                                <span class="trip-detail-stop__label">Goods Value</span>
                                                <span class="trip-detail-stop__value">
                                                    {{ stop.leg.goods_value ? `₹${formatMoney(stop.leg.goods_value)}` : '—' }}
                                                </span>
                                            </div>
                                            <div class="trip-detail-stop__field">
                                                <span class="trip-detail-stop__label">No. of Units</span>
                                                <span class="trip-detail-stop__value">{{ stop.leg.no_of_units ?? '—' }}</span>
                                            </div>
                                            <div class="trip-detail-stop__field">
                                                <span class="trip-detail-stop__label">Weight (Ton)</span>
                                                <span class="trip-detail-stop__value">{{ stop.leg.weight_ton ? formatKm(stop.leg.weight_ton) : '—' }}</span>
                                            </div>
                                            <div class="trip-detail-stop__field">
                                                <span class="trip-detail-stop__label">Rate (₹/Ton)</span>
                                                <span class="trip-detail-stop__value">{{ stop.leg.freight_rate ? formatMoney(stop.leg.freight_rate) : '—' }}</span>
                                            </div>
                                            <div class="trip-detail-stop__field trip-detail-stop__field--freight">
                                                <span class="trip-detail-stop__label">Freight (₹)</span>
                                                <span class="trip-detail-stop__value">₹{{ formatMoney(stop.leg.freight_amount) }}</span>
                                            </div>
                                        </div>

                                        <div class="trip-detail-stop__meta">
                                            <span>
                                                E-Way Bill:
                                                <strong>{{ stop.leg.ewaybill_no || '—' }}</strong>
                                            </span>
                                            <span>
                                                Expiry:
                                                <strong>{{ formatDate(stop.leg.ewaybill_expiry) }}</strong>
                                            </span>
                                            <span>
                                                KM/LTR:
                                                <strong>
                                                    {{
                                                        stop.leg.expected_mileage_km_per_liter
                                                            ? formatKm(stop.leg.expected_mileage_km_per_liter)
                                                            : '—'
                                                    }}
                                                </strong>
                                            </span>
                                            <span>
                                                Distance:
                                                <strong>{{ stop.leg.distance_km ? `${formatKm(stop.leg.distance_km)} KM` : '—' }}</strong>
                                            </span>
                                        </div>

                                        <div class="trip-detail-stop__expenses">
                                            <div class="trip-detail-stop__expenses-title">
                                                <span>Expenses</span>
                                                <strong>₹{{ formatMoney(stop.expenseTotal) }}</strong>
                                            </div>
                                            <div v-if="stop.expenses.length === 0" class="trip-detail-stop__expenses-empty">
                                                No expenses for this stop.
                                            </div>
                                            <div v-else class="table-responsive">
                                                <table class="table table-sm table-hover align-middle mb-0">
                                                    <thead class="table-light">
                                                        <tr>
                                                            <th>Date</th>
                                                            <th>Type</th>
                                                            <th>Meter Reading(KM)</th>
                                                            <th>Liters</th>
                                                            <th>Rate</th>
                                                            <th>Amount</th>
                                                            <th>Notes</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <tr
                                                            v-for="expense in stop.expenses"
                                                            :key="expense.id"
                                                        >
                                                            <td>{{ formatDate(expense.expense_date) }}</td>
                                                            <td>{{ expenseTypeLabel(expense.expense_type, expense.other_type) }}</td>
                                                            <td>{{ formatOdometerReading(expense.fuel_odometer_km) }}</td>
                                                            <td>{{ expense.fuel_liters ?? '—' }}</td>
                                                            <td>{{ expense.fuel_rate ?? '—' }}</td>
                                                            <td>₹{{ formatMoney(expense.amount) }}</td>
                                                            <td class="text-muted">{{ expense.notes || '—' }}</td>
                                                        </tr>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div
                            v-if="routeLegs.length > 0 || expenses.length > 0"
                            class="card-body p-0 border-top"
                        >
                            <div class="trip-detail-km-bar">
                                <div class="trip-detail-km-bar__loads">
                                    <div class="trip-detail-km-bar__label">Stops</div>
                                    <div class="trip-detail-km-bar__loads-values">
                                        <span class="trip-detail-km-bar__load trip-detail-km-bar__load--full">
                                            Full <strong>{{ routeSummary.fullLoadStops }}</strong>
                                        </span>
                                        <span class="trip-detail-km-bar__load trip-detail-km-bar__load--half">
                                            Half <strong>{{ routeSummary.halfLoadStops }}</strong>
                                        </span>
                                        <span class="trip-detail-km-bar__load trip-detail-km-bar__load--empty">
                                            Empty <strong>{{ routeSummary.emptyStops }}</strong>
                                        </span>
                                    </div>
                                </div>
                                <div class="trip-detail-km-bar__item">
                                    <div class="trip-detail-km-bar__label">Avg Mileage</div>
                                    <strong>{{ routeSummary.expectedMileageDisplay }}</strong>
                                </div>
                                <div class="trip-detail-km-bar__item">
                                    <div class="trip-detail-km-bar__label">Total KM</div>
                                    <strong>{{ formatKm(trip.total_distance_km) }}</strong>
                                </div>
                                <div class="trip-detail-km-bar__item">
                                    <div class="trip-detail-km-bar__label">Total Freight</div>
                                    <strong>₹{{ formatMoney(trip.total_freight_amount) }}</strong>
                                </div>
                                <div class="trip-detail-km-bar__item">
                                    <div class="trip-detail-km-bar__label">Total Expenses</div>
                                    <strong>₹{{ formatMoney(trip.total_expense_amount) }}</strong>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4">
                    <TripFinancialSummaryCard
                        :trip="trip"
                        :expenses="expenses"
                    />

                    <TripDieselPerformanceCard
                        class="mt-3"
                        :performance="dieselPerformance"
                        compact
                    />

                    <div v-if="statusHistories.length > 0" class="card mt-3">
                        <div class="card-header"><strong>Status History</strong></div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0 small">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Changed At</th>
                                            <th>From</th>
                                            <th>To</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-for="history in statusHistories" :key="history.id">
                                            <td>{{ formatDateTime(history.changed_at) }}</td>
                                            <td>
                                                <span class="badge" :class="tripStatusMeta(history.from_status).badge">
                                                    {{ tripStatusMeta(history.from_status).label }}
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge" :class="tripStatusMeta(history.to_status).badge">
                                                    {{ tripStatusMeta(history.to_status).label }}
                                                </span>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <div class="card mt-3">
                        <div class="card-header"><strong>Record Info</strong></div>
                        <div class="card-body small">
                            <div class="mb-2">
                                <span class="text-muted">Created</span>
                                <div>{{ formatDateTime(trip.created_at) }}</div>
                            </div>
                            <div>
                                <span class="text-muted">Last Updated</span>
                                <div>{{ formatDateTime(trip.updated_at) }}</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </template>

        <div v-else-if="!needsCompanySelection" class="alert alert-danger">
            Trip not found or you do not have access.
        </div>

        <TripGenerateInvoicesModal
            :show="showGenerateInvoices"
            :trip="trip"
            :groups="invoicePreviewGroups"
            :preview-loading="invoicePreviewLoading"
            :preview-refreshing="invoicePreviewRefreshing"
            :generating="invoiceGenerating"
            :form-error="invoiceFormError"
            :field-errors="invoiceFieldErrors"
            :status-meta="statusMeta"
            :route-preview="routePreview"
            @close="closeGenerateInvoices"
            @continue="promptGenerateInvoicesConfirm"
            @invoice-date-change="refreshInvoicePreviewDebounced"
            @sequence-input="onInvoiceSequenceInput"
        />


        <AppConfirmModal
            :show="showInvoiceGenerateConfirm"
            title="Confirm invoice generation"
            message="Review the invoices below, then confirm to generate."
            icon="bi bi-file-earmark-text"
            icon-tone="success"
            size="xl"
            :loading="invoiceGenerating"
            confirm-label="Generate"
            secondary-confirm-label="Generate & Print"
            loading-label="Generating…"
            confirm-variant="success"
            confirm-outline
            secondary-confirm-variant="purple"
            confirm-icon="bi bi-check2-circle"
            secondary-confirm-icon="bi bi-printer"
            content-class="app-confirm-modal__content app-confirm-modal__content--compact border-0 shadow-lg"
            body-class="app-confirm-modal__body--compact"
            @cancel="cancelInvoiceGenerateConfirm"
            @confirm="confirmGenerateInvoices"
            @secondary-confirm="confirmGenerateInvoicesAndPrint"
        >
            <div class="text-start invoice-generate-confirm">
                <div v-if="trip" class="app-confirm-card mb-2">
                    <div class="app-confirm-card__section-title">Trip</div>
                    <div class="app-confirm-card__body small">
                        <div class="d-flex flex-wrap gap-x-3 gap-y-1">
                            <span><strong>{{ trip.trip_number }}</strong></span>
                            <span class="text-muted">{{ trip.vehicle?.registration_number || '—' }}</span>
                            <span class="text-muted">{{ trip.driver?.name || '—' }}</span>
                            <span class="text-muted">{{ routePreview || '—' }}</span>
                        </div>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-sm table-bordered mb-0 align-middle invoice-generate-confirm__table">
                        <thead class="table-light">
                            <tr>
                                <th>Customer</th>
                                <th>Document</th>
                                <th>Invoice Date</th>
                                <th>Due Date</th>
                                <th class="text-end">Freight</th>
                                <th class="text-end">GST</th>
                                <th class="text-end">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="group in invoicePreviewGroups" :key="group.customer_id">
                                <td>
                                    <div class="fw-semibold">{{ group.customer_name }}</div>
                                    <div v-if="group.customer_gst_number" class="small text-muted">
                                        {{ group.customer_gst_number }}
                                    </div>
                                    <div class="small text-muted">
                                        {{ (group.routes || []).length }} route{{ (group.routes || []).length === 1 ? '' : 's' }}
                                    </div>
                                </td>
                                <td>{{ group.document_type_label || group.document_type || '—' }}</td>
                                <td>{{ formatDate(group.invoice_date) || '—' }}</td>
                                <td>{{ formatDate(group.due_date) || '—' }}</td>
                                <td class="text-end">₹{{ formatMoney(group.freight_amount) }}</td>
                                <td class="text-end">
                                    <template v-if="group.displays_gst">
                                        ₹{{ formatMoney(group.gst_amount) }}
                                        <div class="small text-muted">{{ group.gst_rate }}%</div>
                                    </template>
                                    <template v-else>—</template>
                                </td>
                                <td class="text-end fw-semibold">₹{{ formatMoney(group.total_amount) }}</td>
                            </tr>
                        </tbody>
                        <tfoot>
                            <tr class="table-light">
                                <th colspan="4" class="text-end">Grand total</th>
                                <th class="text-end">₹{{ formatMoney(invoiceConfirmTotals.freight) }}</th>
                                <th class="text-end">₹{{ formatMoney(invoiceConfirmTotals.gst) }}</th>
                                <th class="text-end text-primary">₹{{ formatMoney(invoiceConfirmTotals.total) }}</th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </AppConfirmModal>

        <AppModal
            :show="showPrintBilty"
            size="lg"
            @close="closePrintBilty"
        >
            <template #title>Print Bilty</template>

            <div v-if="trip" class="mb-3">
                <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                    <strong>{{ trip.trip_number }}</strong>
                    <span class="badge" :class="statusMeta.badge">{{ statusMeta.label }}</span>
                </div>
                <p class="text-muted small mb-0">
                    One bilty per customer. Same customer routes are combined. Each bilty prints consignor and driver copies on one page.
                </p>
            </div>

            <div v-if="biltyGroups.length === 0" class="alert alert-warning mb-0">
                No revenue routes with customers are available to print a bilty for this trip.
            </div>

            <template v-else>
                <div class="mb-3">
                    <label class="form-label">Freight On Bilty</label>
                    <div class="d-flex flex-wrap gap-3">
                        <div class="form-check">
                            <input
                                id="bilty-with-freight"
                                v-model="biltyShowFreight"
                                class="form-check-input"
                                type="radio"
                                :value="true"
                            />
                            <label class="form-check-label" for="bilty-with-freight">With freight</label>
                        </div>
                        <div class="form-check">
                            <input
                                id="bilty-without-freight"
                                v-model="biltyShowFreight"
                                class="form-check-input"
                                type="radio"
                                :value="false"
                            />
                            <label class="form-check-label" for="bilty-without-freight">Without freight</label>
                        </div>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-sm table-bordered mb-0 align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Customer</th>
                                <th class="text-end">Routes</th>
                                <th class="text-end">Weight (MT)</th>
                                <th class="text-end">Freight</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="group in biltyGroups" :key="group.customer_id">
                                <td>{{ group.customer_name }}</td>
                                <td class="text-end">{{ group.route_count }}</td>
                                <td class="text-end">{{ formatWeight(group.total_weight_ton) }}</td>
                                <td class="text-end">
                                    <template v-if="biltyShowFreight">₹{{ formatMoney(group.freight_amount) }}</template>
                                    <template v-else>—</template>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </template>

            <template #footer>
                <button type="button" class="btn btn-secondary" @click="closePrintBilty">Cancel</button>
                <AppButton
                    variant="purple"
                    icon="bi bi-printer"
                    label="Open PDF"
                    :disabled="biltyGroups.length === 0"
                    @click="confirmPrintBilty"
                />
            </template>
        </AppModal>

        <TripArchiveConfirmModal
            :show="showArchiveConfirm"
            :summary="archiveConfirmSummary"
            :archiving="archivingTrip"
            :field-errors="archiveFieldErrors"
            @cancel="closeArchiveConfirm"
            @confirm="confirmArchiveTrip"
        />

        <TripRestoreConfirmModal
            :show="showRestoreConfirm"
            :summary="restoreConfirmSummary"
            :restoring="restoringTrip"
            @cancel="closeRestoreConfirm"
            @confirm="confirmRestoreTrip"
        />
    </div>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { RouterLink, useRoute, useRouter } from 'vue-router';
import {
    archiveTrip,
    biltyPdfUrl,
    canArchiveTrip,
    canFullEditTrip,
    canManageTripExpenses,
    expenseTypeLabel,
    fetchTrip,
    fetchTripArchiveImpact,
    isTripArchived,
    isTerminalTripStatus,
    resolveTripEditAccess,
    restoreTrip,
    summarizeTripForm,
    legLoadCategoryMeta,
    tripArchiveDisabledTitle,
    tripStatusMeta,
    tripTypeLabel,
} from '../../api/trips';
import TripArchiveConfirmModal from '../../components/trips/TripArchiveConfirmModal.vue';
import TripRestoreConfirmModal from '../../components/trips/TripRestoreConfirmModal.vue';
import TripInlineStatusActions from '../../components/trips/TripInlineStatusActions.vue';
import TripDieselPerformanceCard from '../../components/trips/TripDieselPerformanceCard.vue';
import TripGenerateInvoicesModal from '../../components/trips/TripGenerateInvoicesModal.vue';
import TripFinancialSummaryCard from '../../components/trips/TripFinancialSummaryCard.vue';
import AppButton from '../../components/ui/AppButton.vue';
import AppCrudActionButton from '../../components/ui/AppCrudActionButton.vue';
import AppConfirmModal from '../../components/ui/AppConfirmModal.vue';
import AppModal from '../../components/ui/AppModal.vue';
import { generateForTrip, pdfUrl, previewForTrip } from '../../api/invoices';
import { useApiFormErrors } from '../../composables/useApiFormErrors';
import { usePermissions } from '../../composables/usePermissions';
import { useTripDetailListReturn } from '../../composables/useTripListReturn';
import { useRequireCompanyContext } from '../../composables/useRequireCompanyContext';
import { useAppToast } from '../../composables/useAppToast';
import { dateOnlyFromDatetimeLocal, formatDate, formatDateTime } from '../../utils/datetime';
import { buildTripArchiveConfirmViewModel } from '../../utils/tripArchiveConfirm';
import { buildTripRestoreConfirmViewModel } from '../../utils/tripRestoreConfirm';
import { friendlyApiErrorMessage } from '../../utils/formErrors';
import { invalidateOperationalData } from '../../composables/useOperationalDataInvalidation';

const route = useRoute();
const router = useRouter();
const toast = useAppToast();
const { canInCompany, user } = usePermissions();
const { needsCompanySelection, guardCompanyContext, handleCompanyContextError } =
    useRequireCompanyContext();
const {
    fieldErrors: invoiceFieldErrors,
    formError: invoiceFormError,
    clearErrors: clearInvoiceErrors,
    applyApiError: applyInvoiceApiError,
} = useApiFormErrors();

const trip = ref(null);
const loading = ref(true);
const showGenerateInvoices = ref(false);
const showInvoiceGenerateConfirm = ref(false);
const invoicePreviewLoading = ref(false);
const invoicePreviewRefreshing = ref(false);
const invoiceGenerating = ref(false);
const invoicePreviewGroups = ref([]);
const showPrintBilty = ref(false);
const biltyShowFreight = ref(false);
const { tripListReturnTo } = useTripDetailListReturn(trip);

const showArchiveConfirm = ref(false);
const archiveConfirmSummary = ref(null);
const archivingTrip = ref(false);
const archiveFieldErrors = ref({});
const tripPendingArchive = ref(null);

const showRestoreConfirm = ref(false);
const restoreConfirmSummary = ref(null);
const restoringTrip = ref(false);

const canManage = computed(() => canInCompany('trips.edit'));
const canArchive = computed(() => canInCompany('trips.archive') || canInCompany('trips.delete'));
const canRestore = computed(() => canInCompany('trips.restore'));
const canArchiveThisTrip = computed(() => {
    return canArchive.value && trip.value && canArchiveTrip(trip.value.status, user.value);
});
const canRestoreThisTrip = computed(() => canRestore.value && isArchived.value);

const routeLegs = computed(() => trip.value?.route_legs ?? []);
const biltyGroups = computed(() => {
    const groups = new Map();

    routeLegs.value.forEach((leg) => {
        const weight = Number(leg.weight_ton ?? 0);
        const customerId = leg.customer_id;

        if (!customerId || weight <= 0) {
            return;
        }

        const key = String(customerId);
        const existing = groups.get(key) ?? {
            customer_id: customerId,
            customer_name: leg.customer?.name || trip.value?.customer_name || '—',
            route_count: 0,
            total_weight_ton: 0,
            freight_amount: 0,
        };

        existing.route_count += 1;
        existing.total_weight_ton += weight;
        existing.freight_amount += Number(leg.freight_amount ?? 0);
        groups.set(key, existing);
    });

    return Array.from(groups.values());
});
const canPrintBilty = computed(
    () => !isArchived.value && canInCompany('trips.view') && biltyGroups.value.length > 0,
);
const routeSummary = computed(() => {
    const summary = summarizeTripForm({
        route_legs: routeLegs.value,
        planned_mileage_km_per_liter: trip.value?.planned_mileage_km_per_liter,
        max_load_ton: trip.value?.max_load_ton,
        start_meter_reading_km: trip.value?.start_meter_reading_km,
        end_meter_reading_km: trip.value?.end_meter_reading_km,
        expenses: [],
    });

    return {
        ...summary,
        expectedMileageDisplay:
            summary.expectedMileage == null
                ? '—'
                : `${Number(summary.expectedMileage).toLocaleString('en-IN', { maximumFractionDigits: 2 })} KM/LTR`,
    };
});
const dieselPerformance = computed(() => trip.value?.diesel_performance ?? null);
const expenses = computed(() => trip.value?.expenses ?? []);
const statusHistories = computed(() => trip.value?.status_histories ?? []);
const isTerminal = computed(() => isTerminalTripStatus(trip.value?.status));
const isArchived = computed(() => isTripArchived(trip.value));
const canGenerateInvoices = computed(
    () =>
        !isArchived.value
        && Boolean(trip.value?.can_generate_invoices)
        && canInCompany('invoices.create'),
);

const invoiceConfirmTotals = computed(() => {
    return invoicePreviewGroups.value.reduce(
        (totals, group) => {
            totals.freight += Number(group.freight_amount) || 0;
            totals.gst += group.displays_gst ? Number(group.gst_amount) || 0 : 0;
            totals.total += Number(group.total_amount) || 0;

            return totals;
        },
        { freight: 0, gst: 0, total: 0 },
    );
});
const tripEditOptions = computed(() => ({ has_invoices: Boolean(trip.value?.has_invoices) }));
const editAccess = computed(() => resolveTripEditAccess(trip.value?.status, user.value, tripEditOptions.value));
const canEditTripPage = computed(() => !isArchived.value && canFullEditTrip(trip.value?.status, user.value, tripEditOptions.value));
const canManageTripExpensesFor = computed(() => !isArchived.value && canManageTripExpenses(trip.value?.status, user.value, tripEditOptions.value));
const canEditTripStatus = computed(() => !isArchived.value && editAccess.value.fullEdit);
const terminalAlert = computed(() => {
    if (editAccess.value.invoiceLocked) {
        return editAccess.value.expensesOnly
            ? 'This trip has invoices and cannot be edited. Use Manage Expenses to update expenses.'
            : 'This trip has invoices and cannot be edited.';
    }

    if (!isTerminal.value) {
        return '';
    }

    if (editAccess.value.fullEdit) {
        return '';
    }

    if (editAccess.value.expensesOnly) {
        return 'This trip is completed. Trip details are read-only; use Manage Expenses to update expenses.';
    }

    const status = String(trip.value?.status ?? '').toUpperCase();
    const label = status === 'CANCELLED'
        ? 'cancelled'
        : status === 'COMPLETED' || status === 'INVOICED'
          ? 'completed'
          : 'read-only';

    return `This trip is ${label} and is read-only.`;
});
const statusMeta = computed(() => tripStatusMeta(trip.value?.status));

const routeSectionTitle = computed(() =>
    trip.value?.trip_type === 'ONE_WAY' ? 'One Way Route' : 'Round Trip Route',
);

const startStationLabel = computed(() => {
    const station = trip.value?.start_station;

    if (station?.label) {
        return station.label;
    }

    if (station?.name) {
        return station.name;
    }

    const firstLeg = routeLegs.value[0];

    return firstLeg?.from_city || firstLeg?.from_location || '—';
});

const routePreview = computed(() => {
    if (!startStationLabel.value || startStationLabel.value === '—') {
        return '';
    }

    const points = [startStationLabel.value];

    routeLegs.value.forEach((leg) => {
        const label = leg.to_city || leg.to_location;

        if (label) {
            points.push(label);
        }
    });

    return points.length > 1 ? points.join(' → ') : '';
});

const vehicleMeta = computed(() => {
    const vehicle = trip.value?.vehicle;

    if (!vehicle) {
        return '';
    }

    const parts = [vehicle.make, vehicle.model].filter(Boolean);

    return parts.join(' / ');
});

const arrivalTiming = computed(() => {
    const endDate = dateOnlyFromDatetimeLocal(trip.value?.end_at);
    const arrivalDate = dateOnlyFromDatetimeLocal(trip.value?.actual_arrival_at);

    if (!endDate || !arrivalDate) {
        return null;
    }

    if (arrivalDate < endDate) {
        return 'early';
    }

    if (arrivalDate > endDate) {
        return 'late';
    }

    return 'on-time';
});

const expandedRouteSectionKey = ref(null);

const generalExpenseGroup = computed(() => {
    const matchedSequences = new Set(
        routeLegs.value.map((leg, index) => Number(leg.sequence_no ?? index + 1)),
    );
    const items = expenses.value.filter((expense) => {
        const seq = expense.route_leg_sequence;

        if (seq === '' || seq == null) {
            return true;
        }

        return !matchedSequences.has(Number(seq));
    });

    if (items.length === 0) {
        return null;
    }

    return {
        key: 'general',
        items,
        total: sumExpenses(items),
    };
});

const routeStopGroups = computed(() =>
    routeLegs.value.map((leg, index) => {
        const sequence = leg.sequence_no ?? index + 1;
        const stopExpenses = expenses.value.filter(
            (expense) => Number(expense.route_leg_sequence) === Number(sequence),
        );

        return {
            key: `stop-${sequence}`,
            sequence,
            leg,
            from: leg.from_city || leg.from_location || '—',
            to: leg.to_city || leg.to_location || '—',
            expenses: stopExpenses,
            expenseTotal: sumExpenses(stopExpenses),
        };
    }),
);

function isRouteSectionExpanded(key) {
    return expandedRouteSectionKey.value === key;
}

function toggleRouteSection(key) {
    expandedRouteSectionKey.value = expandedRouteSectionKey.value === key ? null : key;
}

function sumExpenses(items) {
    return items.reduce((sum, expense) => sum + Number(expense.amount ?? 0), 0);
}

function formatMoney(value) {
    return Number(value ?? 0).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function formatWeight(value) {
    return Number(value ?? 0).toLocaleString('en-IN', { minimumFractionDigits: 0, maximumFractionDigits: 3 });
}

function formatKm(value) {
    return Number(value ?? 0).toLocaleString('en-IN', { maximumFractionDigits: 2 });
}

function formatOdometerReading(value) {
    if (value === null || value === undefined || value === '') {
        return '—';
    }

    const reading = Number(value);

    if (Number.isNaN(reading)) {
        return '—';
    }

    return Number.isInteger(reading) ? String(reading) : reading.toFixed(2).replace(/\.?0+$/, '');
}

function onTripUpdated(updated) {
    trip.value = updated;
}

function goToExpenses() {
    if (!trip.value?.id) {
        return;
    }

    router.push({ name: 'trips.expenses', params: { id: trip.value.id } });
}

function goToEdit() {
    if (!trip.value?.id) {
        return;
    }

    router.push({ name: 'trips.edit', params: { id: trip.value.id } });
}

function todayDateString() {
    const now = new Date();
    const month = String(now.getMonth() + 1).padStart(2, '0');
    const day = String(now.getDate()).padStart(2, '0');

    return `${now.getFullYear()}-${month}-${day}`;
}

function buildInvoicePreviewPayload() {
    const groups = {};

    invoicePreviewGroups.value.forEach((group) => {
        const sequenceRaw = group.invoice_sequence;
        const hasSequence = sequenceRaw !== '' && sequenceRaw != null && String(sequenceRaw).trim() !== '';

        groups[group.customer_id] = {
            invoice_date: group.invoice_date || null,
            due_date: group.due_date || null,
            ...(hasSequence ? { invoice_sequence: Number(sequenceRaw) } : {}),
        };
    });

    return { groups };
}

let invoicePreviewRefreshTimer = null;
let invoicePreviewRequestId = 0;

function onInvoiceSequenceInput(group, event) {
    const digits = String(event.target.value ?? '').replace(/\D/g, '');
    group.invoice_sequence = digits;
    clearInvoiceErrors();
    refreshInvoicePreviewDebounced();
}

function refreshInvoicePreviewDebounced() {
    if (invoicePreviewRefreshTimer) {
        clearTimeout(invoicePreviewRefreshTimer);
    }

    invoicePreviewRefreshTimer = setTimeout(() => {
        refreshInvoicePreview({ silent: true });
    }, 350);
}

async function refreshInvoicePreview({ silent = false } = {}) {
    if (!trip.value?.id || !showGenerateInvoices.value) {
        return;
    }

    const requestId = ++invoicePreviewRequestId;

    if (silent) {
        invoicePreviewRefreshing.value = true;
    } else {
        invoicePreviewLoading.value = true;
    }

    try {
        const groups = await previewForTrip(trip.value.id, buildInvoicePreviewPayload());

        if (requestId !== invoicePreviewRequestId) {
            return;
        }

        if (!Array.isArray(groups)) {
            return;
        }

        const byCustomerId = new Map(groups.map((group) => [String(group.customer_id), group]));

        invoicePreviewGroups.value.forEach((existing) => {
            const updated = byCustomerId.get(String(existing.customer_id));

            if (!updated) {
                return;
            }

            existing.invoice_number = updated.invoice_number;
            existing.freight_amount = updated.freight_amount;
            existing.gst_amount = updated.gst_amount;
            existing.total_amount = updated.total_amount;
            existing.gst_rate = updated.gst_rate;
            existing.displays_gst = updated.displays_gst;
            existing.document_type = updated.document_type;
            existing.document_type_label = updated.document_type_label;
            existing.routes = updated.routes;
        });
    } catch (error) {
        if (requestId === invoicePreviewRequestId) {
            applyInvoiceApiError(error, 'Could not refresh invoice preview.');
        }
    } finally {
        if (requestId === invoicePreviewRequestId) {
            invoicePreviewLoading.value = false;
            invoicePreviewRefreshing.value = false;
        }
    }
}

function validateInvoiceDates() {
    clearInvoiceErrors();

    const errors = {};

    invoicePreviewGroups.value.forEach((group) => {
        if (!group.invoice_date) {
            errors[`groups.${group.customer_id}.invoice_date`] = ['Invoice date is required.'];
        }

        if (group.due_date && group.invoice_date && group.due_date < group.invoice_date) {
            errors[`groups.${group.customer_id}.due_date`] = [
                'Due date cannot be earlier than the invoice date.',
            ];
        }
    });

    if (Object.keys(errors).length > 0) {
        invoiceFieldErrors.value = errors;

        return false;
    }

    return true;
}

async function openGenerateInvoices() {
    showGenerateInvoices.value = true;
    invoicePreviewGroups.value = [];
    clearInvoiceErrors();

    if (!trip.value?.id) {
        return;
    }

    invoicePreviewLoading.value = true;

    try {
        const groups = await previewForTrip(trip.value.id, buildInvoicePreviewPayload());
        const defaultInvoiceDate = todayDateString();
        invoicePreviewGroups.value = Array.isArray(groups)
            ? groups.map((group) => ({
                ...group,
                invoice_date: group.invoice_date || defaultInvoiceDate,
                invoice_sequence: group.invoice_sequence ?? '',
            }))
            : [];
    } catch (error) {
        invoicePreviewGroups.value = [];
        applyInvoiceApiError(error, 'Could not load invoice preview.');
    } finally {
        invoicePreviewLoading.value = false;
    }
}

function closeGenerateInvoices() {
    if (invoicePreviewRefreshTimer) {
        clearTimeout(invoicePreviewRefreshTimer);
        invoicePreviewRefreshTimer = null;
    }

    invoicePreviewRequestId += 1;
    showGenerateInvoices.value = false;
    showInvoiceGenerateConfirm.value = false;
    invoicePreviewGroups.value = [];
    invoicePreviewRefreshing.value = false;
    clearInvoiceErrors();
}

function promptGenerateInvoicesConfirm() {
    if (!trip.value?.id || invoicePreviewGroups.value.length === 0) {
        return;
    }

    if (!validateInvoiceDates()) {
        return;
    }

    showGenerateInvoices.value = false;
    showInvoiceGenerateConfirm.value = true;
}

function cancelInvoiceGenerateConfirm() {
    showInvoiceGenerateConfirm.value = false;
    showGenerateInvoices.value = true;
}

function openInvoicePdfTabs(invoices, pdfWindows = []) {
    const list = (Array.isArray(invoices) ? invoices : [invoices]).filter((invoice) => invoice?.id);

    list.forEach((invoice, index) => {
        const url = pdfUrl(invoice.id);
        const existingWindow = pdfWindows[index];

        if (existingWindow && !existingWindow.closed) {
            existingWindow.location.href = url;

            return;
        }

        window.open(url, '_blank', 'noopener');
    });

    for (let index = list.length; index < pdfWindows.length; index += 1) {
        pdfWindows[index]?.close();
    }
}

function prepareInvoicePdfWindows() {
    return invoicePreviewGroups.value.map(() => window.open('about:blank', '_blank', 'noopener'));
}

async function confirmGenerateInvoices({ openPdfs = false } = {}) {
    if (!trip.value?.id || invoicePreviewGroups.value.length === 0) {
        return;
    }

    if (!validateInvoiceDates()) {
        showInvoiceGenerateConfirm.value = false;
        showGenerateInvoices.value = true;

        return;
    }

    const pdfWindows = openPdfs ? prepareInvoicePdfWindows() : [];

    invoiceGenerating.value = true;
    clearInvoiceErrors();

    try {
        const invoices = await generateForTrip(trip.value.id, buildInvoicePreviewPayload());
        const count = Array.isArray(invoices) ? invoices.length : 1;

        if (openPdfs) {
            openInvoicePdfTabs(invoices, pdfWindows);
        }

        toast.success(`${count} invoice${count === 1 ? '' : 's'} generated.`);
        closeGenerateInvoices();
        await loadTrip();
    } catch (error) {
        pdfWindows.forEach((pdfWindow) => pdfWindow?.close());
        showInvoiceGenerateConfirm.value = false;
        showGenerateInvoices.value = true;
        applyInvoiceApiError(error, 'Could not generate invoices.');
    } finally {
        invoiceGenerating.value = false;
    }
}

function confirmGenerateInvoicesAndPrint() {
    return confirmGenerateInvoices({ openPdfs: true });
}

async function handleArchive() {
    if (!canArchiveThisTrip.value) {
        toast.error(tripArchiveDisabledTitle(trip.value?.status, user.value));

        return;
    }

    archiveFieldErrors.value = {};
    tripPendingArchive.value = trip.value;

    try {
        const impact = await fetchTripArchiveImpact(trip.value.id);
        archiveConfirmSummary.value = buildTripArchiveConfirmViewModel(impact);
        showArchiveConfirm.value = true;
    } catch (error) {
        toast.error(archiveTripErrorMessage(error));
    }
}

function closeArchiveConfirm() {
    if (archivingTrip.value) {
        return;
    }

    showArchiveConfirm.value = false;
    archiveConfirmSummary.value = null;
    tripPendingArchive.value = null;
    archiveFieldErrors.value = {};
}

function archiveTripErrorMessage(error) {
    if (error?.response?.status === 403) {
        return 'You do not have permission to archive trips.';
    }

    const tripError = error?.response?.data?.errors?.trip?.[0];

    if (tripError) {
        return tripError;
    }

    return friendlyApiErrorMessage(error, 'Unable to archive trip.');
}

async function confirmArchiveTrip({ archive_reason: archiveReason }) {
    if (!tripPendingArchive.value || archivingTrip.value) {
        return;
    }

    archivingTrip.value = true;
    archiveFieldErrors.value = {};

    try {
        await archiveTrip(tripPendingArchive.value.id, { archive_reason: archiveReason });
        toast.success('Trip archived successfully.');
        showArchiveConfirm.value = false;
        archiveConfirmSummary.value = null;
        tripPendingArchive.value = null;
        archiveFieldErrors.value = {};
        invalidateOperationalData(['trips', 'dashboard', 'trip-reports']);
        await loadTrip();
    } catch (error) {
        if (error?.response?.status === 422 && error?.response?.data?.errors) {
            archiveFieldErrors.value = error.response.data.errors;
        } else {
            toast.error(archiveTripErrorMessage(error));
        }
    } finally {
        archivingTrip.value = false;
    }
}

function openRestoreConfirm() {
    if (!canRestoreThisTrip.value || !trip.value) {
        return;
    }

    restoreConfirmSummary.value = buildTripRestoreConfirmViewModel(trip.value);
    showRestoreConfirm.value = true;
}

function closeRestoreConfirm() {
    if (restoringTrip.value) {
        return;
    }

    showRestoreConfirm.value = false;
    restoreConfirmSummary.value = null;
}

function restoreTripErrorMessage(error) {
    if (error?.response?.status === 403) {
        return 'You do not have permission to restore trips.';
    }

    const tripError = error?.response?.data?.errors?.trip?.[0];

    if (tripError) {
        return tripError;
    }

    return friendlyApiErrorMessage(error, 'Unable to restore trip.');
}

async function confirmRestoreTrip() {
    if (!trip.value || restoringTrip.value) {
        return;
    }

    restoringTrip.value = true;

    try {
        await restoreTrip(trip.value.id);
        toast.success('Trip restored successfully.');
        showRestoreConfirm.value = false;
        restoreConfirmSummary.value = null;
        invalidateOperationalData(['trips', 'dashboard', 'trip-reports']);
        await loadTrip();
    } catch (error) {
        toast.error(restoreTripErrorMessage(error));
    } finally {
        restoringTrip.value = false;
    }
}

async function loadTrip() {
    const tripId = route.params.id;

    if (needsCompanySelection.value || !tripId) {
        trip.value = null;
        loading.value = false;

        return;
    }

    loading.value = true;

    try {
        trip.value = await fetchTrip(tripId);
        await maybeOpenGenerateInvoicesFromQuery();
        maybeOpenPrintBiltyFromQuery();
    } catch (error) {
        trip.value = null;

        if (!handleCompanyContextError(error, 'Could not load trip details.')) {
            toast.error('Could not load trip details.');
        }
    } finally {
        loading.value = false;
    }
}

async function maybeOpenGenerateInvoicesFromQuery() {
    if (route.query.generateInvoices !== '1') {
        return;
    }

    if (canGenerateInvoices.value) {
        await openGenerateInvoices();
    }

    const nextQuery = { ...route.query };
    delete nextQuery.generateInvoices;
    router.replace({ query: nextQuery });
}

function openPrintBilty() {
    if (!canPrintBilty.value) {
        return;
    }

    biltyShowFreight.value = false;
    showPrintBilty.value = true;
}

function closePrintBilty() {
    showPrintBilty.value = false;
}

function confirmPrintBilty() {
    if (!trip.value?.id || biltyGroups.value.length === 0) {
        return;
    }

    window.open(
        biltyPdfUrl(trip.value.id, { showFreight: biltyShowFreight.value }),
        '_blank',
        'noopener',
    );
    closePrintBilty();
}

function maybeOpenPrintBiltyFromQuery() {
    if (route.query.printBilty !== '1') {
        return;
    }

    if (canPrintBilty.value) {
        openPrintBilty();
    }

    const nextQuery = { ...route.query };
    delete nextQuery.printBilty;
    router.replace({ query: nextQuery });
}

onMounted(async () => {
    const ok = await guardCompanyContext();

    if (ok === true) {
        await loadTrip();
    } else {
        loading.value = false;
    }
});

watch(() => route.params.id, () => {
    loadTrip();
});

watch(
    () => route.query.generateInvoices,
    async (value) => {
        if (value === '1' && trip.value && !loading.value) {
            await maybeOpenGenerateInvoicesFromQuery();
        }
    },
);

watch(
    () => route.query.printBilty,
    (value) => {
        if (value === '1' && trip.value && !loading.value) {
            maybeOpenPrintBiltyFromQuery();
        }
    },
);
</script>

<style scoped>
.invoice-generate-confirm__table {
    font-size: 0.875rem;
}

.invoice-generate-confirm__table th,
.invoice-generate-confirm__table td {
    padding: 0.4rem 0.55rem;
    white-space: nowrap;
}

.invoice-generate-confirm__table td:first-child,
.invoice-generate-confirm__table th:first-child {
    white-space: normal;
    min-width: 10rem;
}

.trip-info-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 0.65rem 0.85rem;
    padding: 0.85rem 1rem !important;
}

.trip-info-grid__item {
    min-width: 0;
}

.trip-info-grid__item--full,
.trip-info-grid__item--ref-status {
    grid-column: 1 / -1;
}

.trip-info-grid__label {
    display: block;
    margin-bottom: 0.1rem;
    font-size: 0.6875rem;
    font-weight: 700;
    color: #64748b;
    line-height: 1.2;
}

.trip-info-grid__value {
    font-size: 0.8125rem;
    font-weight: 600;
    color: #0f172a;
    line-height: 1.3;
    word-break: break-word;
}

.trip-info-grid__sub {
    margin-top: 0.1rem;
    font-size: 0.75rem;
    color: #64748b;
    line-height: 1.25;
}

.trip-info-ref-status {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 1rem;
    flex-wrap: wrap;
}

.trip-info-ref-status__ref {
    min-width: 0;
    flex: 1 1 auto;
}

.trip-info-ref-status__status {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    text-align: right;
    flex-shrink: 0;
    margin-left: auto;
}

.trip-info-ref-status__actions {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 0.35rem;
    flex-wrap: wrap;
}

@media (max-width: 1199.98px) {
    .trip-info-grid {
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }
}

@media (max-width: 767.98px) {
    .trip-info-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media (max-width: 575.98px) {
    .trip-info-grid {
        grid-template-columns: 1fr;
    }

    .trip-info-ref-status__status {
        align-items: flex-start;
        text-align: left;
        margin-left: 0;
        width: 100%;
    }

    .trip-info-ref-status__actions {
        justify-content: flex-start;
    }
}

.trip-route-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
}

.trip-route-header__title {
    min-width: 0;
}

.trip-route-header__totals {
    margin-left: auto;
    flex-shrink: 0;
    text-align: right;
    font-size: 0.8125rem;
    font-weight: 700;
    color: #fff;
    white-space: nowrap;
}

.trip-detail-km-bar {
    display: flex;
    flex-wrap: nowrap;
    align-items: stretch;
    gap: 0.65rem 1rem;
    padding: 0.7rem 1rem;
    background: #f8fafc;
    overflow-x: auto;
}

.trip-detail-km-bar__loads,
.trip-detail-km-bar__item {
    min-width: 0;
    flex: 1 1 0;
}

.trip-detail-km-bar__loads {
    flex: 1.4 1 0;
    min-width: 11rem;
}

.trip-detail-km-bar__label {
    margin-bottom: 0.15rem;
    font-size: 0.6875rem;
    font-weight: 600;
    color: #64748b;
    line-height: 1.2;
    white-space: nowrap;
}

.trip-detail-km-bar__item strong {
    display: block;
    font-size: 0.875rem;
    line-height: 1.25;
    color: #0f172a;
    white-space: nowrap;
}

.trip-detail-km-bar__loads-values {
    display: flex;
    align-items: center;
    flex-wrap: nowrap;
    gap: 0.45rem 0.65rem;
}

.trip-detail-km-bar__load {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    font-size: 0.75rem;
    font-weight: 600;
    color: #64748b;
    white-space: nowrap;
}

.trip-detail-km-bar__load strong {
    font-size: 0.875rem;
    font-weight: 800;
}

.trip-detail-km-bar__load--full strong {
    color: #15803d;
}

.trip-detail-km-bar__load--half strong {
    color: #b45309;
}

.trip-detail-km-bar__load--empty strong {
    color: #64748b;
}

.trip-detail-stops {
    display: flex;
    flex-direction: column;
    gap: 0.85rem;
}

.trip-detail-stop {
    border: 1px solid #e2e8f0;
    border-radius: 0.65rem;
    overflow: hidden;
    background: #fff;
}

.trip-detail-stop--collapsed {
    background: #fff;
}

.trip-detail-stop__head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    flex-wrap: wrap;
    padding: 0.65rem 0.85rem;
    background: #f8fafc;
    border-bottom: 1px solid #eef2f7;
}

.trip-detail-stop--collapsed .trip-detail-stop__head {
    border-bottom: 0;
}

.trip-detail-stop__head--btn {
    width: 100%;
    margin: 0;
    border: 0;
    text-align: left;
    cursor: pointer;
    appearance: none;
}

.trip-detail-stop__head--btn:hover {
    background: #f1f5f9;
}

.trip-detail-stop__head--btn:focus-visible {
    outline: 2px solid rgba(37, 99, 235, 0.35);
    outline-offset: -2px;
}

.trip-detail-stop__head-main {
    display: inline-flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 0.35rem 0.45rem;
    min-width: 0;
    font-size: 0.8125rem;
}

.trip-detail-stop__num {
    font-weight: 700;
    color: #0f172a;
}

.trip-detail-stop__sep {
    color: #94a3b8;
}

.trip-detail-stop__route,
.trip-detail-stop__customer {
    font-weight: 600;
    color: #334155;
}

.trip-detail-stop__expense-count {
    font-weight: 600;
    color: #64748b;
}

.trip-detail-stop__head-meta {
    display: inline-flex;
    align-items: center;
    gap: 0.65rem;
    flex-shrink: 0;
    margin-left: auto;
}

.trip-detail-stop__freight {
    font-size: 0.8125rem;
    font-weight: 700;
    color: #166534;
    white-space: nowrap;
}

.trip-detail-stop__freight.is-empty,
.trip-detail-stop__expense.is-empty {
    color: #94a3b8;
    font-weight: 600;
}

.trip-detail-stop__expense {
    font-size: 0.8125rem;
    font-weight: 700;
    color: #b45309;
    white-space: nowrap;
}

.trip-detail-stop__head-meta .bi {
    color: #64748b;
    font-size: 0.85rem;
}

.trip-detail-stop__body {
    border-top: 1px solid #eef2f7;
}

.trip-detail-stop__fields {
    display: grid;
    grid-template-columns: repeat(5, minmax(0, 1fr));
    gap: 0.65rem 0.75rem;
    padding: 0.85rem;
}

.trip-detail-stop__field {
    min-width: 0;
}

.trip-detail-stop__label {
    display: block;
    margin-bottom: 0.15rem;
    font-size: 0.6875rem;
    font-weight: 700;
    color: #64748b;
}

.trip-detail-stop__value {
    display: block;
    font-size: 0.8125rem;
    font-weight: 600;
    color: #0f172a;
    word-break: break-word;
}

.trip-detail-stop__field--freight .trip-detail-stop__value {
    color: #166534;
    font-weight: 800;
}

.trip-detail-stop__meta {
    display: flex;
    flex-wrap: wrap;
    gap: 0.45rem 1rem;
    padding: 0.55rem 0.85rem;
    border-top: 1px solid #eef2f7;
    background: #f8fafc;
    font-size: 0.75rem;
    color: #64748b;
}

.trip-detail-stop__meta strong {
    color: #334155;
    font-weight: 700;
}

.trip-detail-stop__expenses {
    padding: 0.75rem 0.85rem 0.9rem;
    border-top: 1px solid #eef2f7;
    background: #fff;
}

.trip-detail-stop__expenses-title {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
    margin-bottom: 0.55rem;
    font-size: 0.8125rem;
    font-weight: 700;
    color: #0f172a;
}

.trip-detail-stop__expenses-title strong {
    color: #b45309;
}

.trip-detail-stop__expenses-empty {
    padding: 0.55rem 0.15rem;
    font-size: 0.8125rem;
    color: #94a3b8;
}

@media (max-width: 1199.98px) {
    .trip-detail-stop__fields {
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }
}

@media (max-width: 767.98px) {
    .trip-detail-stop__fields {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media (max-width: 575.98px) {
    .trip-detail-stop__fields {
        grid-template-columns: 1fr;
    }

    .trip-detail-stop__head-meta {
        width: 100%;
        justify-content: flex-start;
        margin-left: 0;
    }
}
</style>
