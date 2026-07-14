<template>
    <div class="container mt-4 trip-detail-page pb-4">
        <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
            <div>
                <RouterLink :to="tripListReturnTo" class="text-decoration-none small">
                    <i class="bi bi-arrow-left"></i> {{ trip?.is_archived ? 'Back to Archived Trips' : 'Back to Trips' }}
                </RouterLink>
                <div class="d-flex flex-wrap align-items-center gap-2 mt-1">
                    <h3 class="mb-0">{{ trip?.trip_number ?? 'Trip Details' }}</h3>
                    <span v-if="trip" class="badge" :class="statusMeta.badge">{{ statusMeta.label }}</span>
                </div>
                <p v-if="trip" class="text-muted small mb-0">
                    <RouterLink
                        v-if="trip.vehicle?.id"
                        :to="{ name: 'trucks.show', params: { vehicleId: trip.vehicle.id } }"
                        class="text-decoration-none"
                    >
                        {{ trip.vehicle.registration_number }}
                    </RouterLink>
                    <span v-else>—</span>
                    <span v-if="trip.driver?.name"> · {{ trip.driver.name }}</span>
                    <span v-if="tripDuration"> · {{ tripDuration }}</span>
                </p>
            </div>
            <div v-if="trip" class="d-flex flex-wrap align-items-center gap-2">
                <TripInlineStatusActions
                    v-if="!isArchived"
                    :trip="trip"
                    :can-manage="canManage && canEditTripStatus"
                    :compact="false"
                    @updated="onTripUpdated"
                />
                <AppButton
                    v-if="canManage && canManageTripExpensesFor"
                    variant="success"
                    outline
                    size="sm"
                    icon="bi bi-receipt"
                    label="Manage Expenses"
                    :to="{ name: 'trips.expenses', params: { id: trip.id } }"
                />
                <AppButton
                    v-if="canGenerateInvoices"
                    variant="warning"
                    outline
                    size="sm"
                    icon="bi bi-file-earmark-text"
                    label="Generate Invoice(s)"
                    @click="openGenerateInvoices"
                />
                <AppButton
                    v-if="canPrintBilty"
                    variant="secondary"
                    outline
                    size="sm"
                    icon="bi bi-printer"
                    label="Print Bilty"
                    @click="openPrintBilty"
                />
                <AppButton
                    v-if="canManage && canEditTripPage"
                    variant="primary"
                    outline
                    size="sm"
                    icon="bi bi-pencil"
                    label="Edit"
                    :to="{ name: 'trips.edit', params: { id: trip.id } }"
                />
                <AppButton
                    v-if="canArchiveThisTrip && !isArchived"
                    variant="danger"
                    outline
                    size="sm"
                    icon="bi bi-archive"
                    label="Archive"
                    :disabled="archivingTrip"
                    @click="handleArchive"
                />
            </div>
        </div>

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
            </div>

            <div v-if="terminalAlert" class="alert alert-secondary mb-3">
                <i class="bi bi-lock me-1"></i>
                {{ terminalAlert }}
            </div>

            <div class="row g-3">
                <div class="col-lg-8">
                    <div class="card">
                        <div class="card-header">
                            <strong><i class="bi bi-signpost-split me-2" aria-hidden="true"></i>Trip Information</strong>
                            <span v-if="headerMetaText" class="text-muted small ms-2">{{ headerMetaText }}</span>
                        </div>
                        <div class="card-body row g-3 small">
                            <div class="col-md-4">
                                <span class="text-muted">Trip Number</span>
                                <div class="fw-semibold">{{ trip.trip_number || '—' }}</div>
                            </div>
                            <div class="col-md-4">
                                <span class="text-muted">Status</span>
                                <div>
                                    <span class="badge" :class="statusMeta.badge">{{ statusMeta.label }}</span>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <span class="text-muted">Start Station</span>
                                <div class="fw-semibold">{{ startStationLabel }}</div>
                            </div>
                            <div class="col-md-4">
                                <span class="text-muted">Trip Type</span>
                                <div>{{ tripTypeLabel(trip.trip_type) }}</div>
                            </div>
                            <div class="col-md-4">
                                <span class="text-muted">Start Date</span>
                                <div>{{ formatDateTime(trip.start_at) }}</div>
                            </div>
                            <div class="col-md-4">
                                <span class="text-muted d-flex align-items-center gap-1 flex-wrap">
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
                                <div>{{ formatDateTime(trip.end_at) }}</div>
                            </div>
                            <div class="col-md-4">
                                <span class="text-muted">Actual Arrival</span>
                                <div>{{ formatDateTime(trip.actual_arrival_at) }}</div>
                            </div>
                            <div class="col-md-4">
                                <span class="text-muted">Truck</span>
                                <div>
                                    <RouterLink
                                        v-if="trip.vehicle?.id"
                                        :to="{ name: 'trucks.show', params: { vehicleId: trip.vehicle.id } }"
                                        class="text-decoration-none fw-semibold"
                                    >
                                        {{ trip.vehicle.registration_number }}
                                    </RouterLink>
                                    <span v-else>—</span>
                                </div>
                                <div v-if="vehicleMeta" class="text-muted">{{ vehicleMeta }}</div>
                            </div>
                            <div class="col-md-4">
                                <span class="text-muted">Truck Type</span>
                                <div>{{ trip.vehicle?.vehicle_type_label ?? trip.vehicle?.vehicle_type ?? '—' }}</div>
                            </div>
                            <div class="col-md-4">
                                <span class="text-muted">Truck Running Meter Reading</span>
                                <div>{{ formatKm(trip.vehicle?.odometer_km) }}</div>
                            </div>
                            <div class="col-md-4">
                                <span class="text-muted">Expected Mileage (KM/Ltr)</span>
                                <div>{{ trip.planned_mileage_km_per_liter ? formatKm(trip.planned_mileage_km_per_liter) : '—' }}</div>
                            </div>
                            <div class="col-md-4">
                                <span class="text-muted">Max Load (Ton)</span>
                                <div>{{ trip.max_load_ton ? formatKm(trip.max_load_ton) : '—' }}</div>
                            </div>
                            <div class="col-md-4">
                                <span class="text-muted">Expected Diesel (Ltr)</span>
                                <div>{{ trip.planned_diesel_liters ? formatKm(trip.planned_diesel_liters) : '—' }}</div>
                            </div>
                            <div class="col-md-4">
                                <span class="text-muted">Diesel Rate (₹/Ltr)</span>
                                <div>{{ trip.diesel_rate_per_liter ? formatMoney(trip.diesel_rate_per_liter) : '—' }}</div>
                            </div>
                            <div class="col-md-4">
                                <span class="text-muted">Start Meter Reading</span>
                                <div>{{ formatOdometerReading(trip.start_meter_reading_km) }}</div>
                            </div>
                            <div class="col-md-4">
                                <span class="text-muted">End Meter Reading</span>
                                <div>{{ formatOdometerReading(trip.end_meter_reading_km) }}</div>
                            </div>
                            <div class="col-md-4">
                                <span class="text-muted">Primary Driver</span>
                                <div class="fw-semibold">{{ trip.driver?.name ?? '—' }}</div>
                                <div v-if="trip.driver?.phone" class="text-muted">{{ trip.driver.phone }}</div>
                            </div>
                            <div class="col-md-4">
                                <span class="text-muted">Substitute Driver</span>
                                <div>{{ trip.substitute_driver?.name ?? '—' }}</div>
                                <div v-if="trip.substitute_driver?.phone" class="text-muted">
                                    {{ trip.substitute_driver.phone }}
                                </div>
                            </div>
                            <div class="col-md-4">
                                <span class="text-muted">Customer</span>
                                <div>{{ trip.customer_name || '—' }}</div>
                            </div>
                            <div class="col-md-4">
                                <span class="text-muted">Reference</span>
                                <div>{{ trip.broker_name || '—' }}</div>
                            </div>
                            <div v-if="trip.notes?.trim()" class="col-12">
                                <span class="text-muted">Notes</span>
                                <div style="white-space: pre-wrap">{{ trip.notes }}</div>
                            </div>
                            <div v-if="trip.status === 'CANCELLED'" class="col-md-4">
                                <span class="text-muted">Cancelled On</span>
                                <div>{{ formatDateTime(trip.cancelled_at) }}</div>
                            </div>
                            <div v-if="trip.status === 'CANCELLED'" class="col-12">
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
                        <div class="card-header">
                            <strong><i class="bi bi-arrow-repeat me-2" aria-hidden="true"></i>{{ routeSectionTitle }}</strong>
                        </div>
                        <div class="card-body">
                            <div v-if="routePreview" class="alert alert-light border mb-3 py-2 small mb-3">
                                <strong>Route:</strong> {{ routePreview }}
                            </div>
                            <div v-if="routeLegs.length === 0" class="text-muted text-center py-4">
                                No route legs recorded.
                            </div>
                            <div v-else class="table-responsive">
                                <table class="table table-bordered align-middle mb-0 trip-route-table trip-route-table--readonly">
                                    <thead class="table-light">
                                        <tr>
                                            <th>#</th>
                                            <th>From</th>
                                            <th>To</th>
                                            <th>Customer</th>
                                            <th>Goods Type</th>
                                            <th>Weight (Ton)</th>
                                            <th>Expected Mileage</th>
                                            <th>Freight Rate (₹/Ton)</th>
                                            <th>Freight</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-for="leg in routeLegs" :key="leg.id ?? leg.sequence_no">
                                            <td>{{ leg.sequence_no }}</td>
                                            <td>
                                                <div>{{ leg.from_city || leg.from_location || '—' }}</div>
                                                <div v-if="leg.from_address" class="text-muted">{{ leg.from_address }}</div>
                                            </td>
                                            <td>
                                                <div>{{ leg.to_city || leg.to_location || '—' }}</div>
                                                <div v-if="leg.to_address" class="text-muted">{{ leg.to_address }}</div>
                                            </td>
                                            <td>{{ leg.customer_name || '—' }}</td>
                                            <td>{{ leg.goods_name || '—' }}</td>
                                            <td>
                                                <div class="trip-route-weight-field">
                                                    <span
                                                        class="trip-route-load-tag"
                                                        :class="`trip-route-load-tag--${legLoadCategoryMeta(leg.weight_ton, trip.max_load_ton).category}`"
                                                    >
                                                        {{ legLoadCategoryMeta(leg.weight_ton, trip.max_load_ton).label }}
                                                    </span>
                                                    <span class="trip-route-weight-readonly">
                                                        {{ leg.weight_ton ? formatKm(leg.weight_ton) : '—' }}
                                                    </span>
                                                </div>
                                            </td>
                                            <td>
                                                {{
                                                    leg.expected_mileage_km_per_liter
                                                        ? `${formatKm(leg.expected_mileage_km_per_liter)} KM/Lt`
                                                        : '—'
                                                }}
                                            </td>
                                            <td>{{ leg.freight_rate ? formatMoney(leg.freight_rate) : '—' }}</td>
                                            <td>₹{{ formatMoney(leg.freight_amount) }}</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <div v-if="routeLegs.length > 0" class="card-body p-0 border-top">
                            <div class="trip-detail-km-bar km-summary-bar">
                                <div>
                                    <div class="small text-muted">Full Load Stops</div>
                                    <strong class="text-success">{{ routeSummary.fullLoadStops }}</strong>
                                </div>
                                <div>
                                    <div class="small text-muted">Half Load Stops</div>
                                    <strong class="text-warning">{{ routeSummary.halfLoadStops }}</strong>
                                </div>
                                <div>
                                    <div class="small text-muted">Empty Stops</div>
                                    <strong class="text-secondary">{{ routeSummary.emptyStops }}</strong>
                                </div>
                                <div>
                                    <div class="small text-muted">Avg Expected Mileage</div>
                                    <strong>{{ routeSummary.expectedMileageDisplay }}</strong>
                                </div>
                                <div>
                                    <div class="small text-muted">Total KM</div>
                                    <strong>{{ formatKm(trip.total_distance_km) }}</strong>
                                </div>
                                <div>
                                    <div class="small text-muted">Total Freight</div>
                                    <strong>₹{{ formatMoney(trip.total_freight_amount) }}</strong>
                                </div>
                            </div>
                        </div>
                    </div>

                    <TripDieselPerformanceCard class="mt-3" :performance="dieselPerformance" />

                    <div class="card mt-3">
                        <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                            <strong><i class="bi bi-receipt me-1"></i>Trip Expenses</strong>
                            <span class="badge bg-secondary">₹{{ formatMoney(trip.total_expense_amount) }}</span>
                        </div>
                        <div class="card-body">
                            <div v-if="expenses.length === 0" class="text-muted text-center py-4">
                                No expenses recorded.
                            </div>
                            <template v-else>
                                <div
                                    v-for="group in expenseGroups"
                                    :key="group.key"
                                    class="trip-detail-expense-group"
                                >
                                    <div class="trip-detail-expense-group__header">
                                        <span>{{ group.label }}</span>
                                        <strong>₹{{ formatMoney(group.total) }}</strong>
                                    </div>
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
                                                <tr v-for="expense in group.items" :key="expense.id">
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
                            </template>
                        </div>
                    </div>

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
                </div>

                <div class="col-lg-4">
                    <div class="card">
                        <div class="card-header"><strong>Financial Summary</strong></div>
                        <div class="card-body small">
                            <div class="d-flex justify-content-between mb-2">
                                <span class="text-muted">Freight</span>
                                <span>₹{{ formatMoney(trip.total_freight_amount) }}</span>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span class="text-muted">Expenses</span>
                                <span>₹{{ formatMoney(trip.total_expense_amount) }}</span>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span class="text-muted">Profit</span>
                                <strong :class="Number(trip.profit_amount) >= 0 ? 'text-success' : 'text-danger'">
                                    ₹{{ formatMoney(trip.profit_amount) }}
                                </strong>
                            </div>
                            <div v-if="profitMargin !== null" class="d-flex justify-content-between mb-2">
                                <span class="text-muted">Profit Margin</span>
                                <span>{{ profitMargin }}%</span>
                            </div>
                            <hr class="my-2" />
                            <div class="d-flex justify-content-between">
                                <span class="text-muted">Total KM</span>
                                <strong>{{ formatKm(trip.total_distance_km) }}</strong>
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

        <AppModal
            :show="showGenerateInvoices"
            :loading="invoicePreviewLoading || invoiceGenerating"
            :form-error="invoiceFormError"
            size="xl"
            @close="closeGenerateInvoices"
        >
            <template #title>Generate Invoice(s)</template>

            <div v-if="trip" class="invoice-generate-trip-summary mb-3">
                <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                    <strong class="fs-5 mb-0">{{ trip.trip_number }}</strong>
                    <span class="badge" :class="statusMeta.badge">{{ statusMeta.label }}</span>
                    <span class="badge bg-light text-dark border">{{ tripTypeLabel(trip.trip_type) }}</span>
                </div>
                <div class="row g-2 small">
                    <div class="col-sm-6 col-lg-3">
                        <div class="text-muted">Truck</div>
                        <div class="fw-semibold">{{ trip.vehicle?.registration_number || '—' }}</div>
                    </div>
                    <div class="col-sm-6 col-lg-3">
                        <div class="text-muted">Driver</div>
                        <div class="fw-semibold">{{ trip.driver?.name || '—' }}</div>
                    </div>
                    <div class="col-sm-6 col-lg-3">
                        <div class="text-muted">Trip Date</div>
                        <div class="fw-semibold">
                            {{ formatDate(trip.start_at) }}
                            <span v-if="trip.end_at" class="text-muted"> → {{ formatDate(trip.end_at) }}</span>
                        </div>
                    </div>
                    <div class="col-sm-6 col-lg-3">
                        <div class="text-muted">Freight</div>
                        <div class="fw-semibold">₹{{ formatMoney(trip.total_freight_amount) }}</div>
                    </div>
                    <div class="col-12">
                        <div class="text-muted">Route</div>
                        <div class="fw-semibold">{{ routePreview || '—' }}</div>
                    </div>
                </div>
            </div>

            <div v-if="invoicePreviewLoading" class="text-center py-4 text-muted">Loading invoice preview…</div>

            <div v-else-if="invoicePreviewGroups.length === 0" class="alert alert-warning mb-0">
                No billable routes are available to invoice on this trip.
            </div>

            <div v-else class="d-flex flex-column gap-3">
                <p class="text-muted small mb-0">
                    Review each customer group below. Adjust invoice date and due date before generating. GST rate comes from company/branch GST settings.
                </p>

                <div
                    v-for="group in invoicePreviewGroups"
                    :key="group.customer_id"
                    class="card"
                >
                    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                        <div>
                            <strong>{{ group.customer_name }}</strong>
                            <span v-if="group.customer_gst_number" class="text-muted small ms-2">
                                GST: {{ group.customer_gst_number }}
                            </span>
                        </div>
                        <span class="badge bg-secondary">{{ group.document_type_label }}</span>
                    </div>
                    <div class="card-body">
                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Invoice Date</label>
                                <AppDateInput
                                    v-model="group.invoice_date"
                                    :invalid="Boolean(invoiceFieldErrors[`groups.${group.customer_id}.invoice_date`])"
                                />
                                <div
                                    v-if="invoiceFieldErrors[`groups.${group.customer_id}.invoice_date`]"
                                    class="invalid-feedback d-block"
                                >
                                    {{ invoiceFieldErrors[`groups.${group.customer_id}.invoice_date`][0] }}
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Due Date</label>
                                <AppDateInput
                                    v-model="group.due_date"
                                    :min="group.invoice_date || undefined"
                                    :invalid="Boolean(invoiceFieldErrors[`groups.${group.customer_id}.due_date`])"
                                />
                                <div
                                    v-if="invoiceFieldErrors[`groups.${group.customer_id}.due_date`]"
                                    class="invalid-feedback d-block"
                                >
                                    {{ invoiceFieldErrors[`groups.${group.customer_id}.due_date`][0] }}
                                </div>
                            </div>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-sm table-bordered mb-3">
                                <thead class="table-light">
                                    <tr>
                                        <th>Route</th>
                                        <th>Goods</th>
                                        <th class="text-end">Freight</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="route in group.routes" :key="route.id">
                                        <td>{{ route.from_location }} → {{ route.to_location }}</td>
                                        <td>{{ route.goods_name || '—' }}</td>
                                        <td class="text-end">₹{{ formatMoney(route.freight_amount) }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <div class="row g-2 small">
                            <div class="col-md-4">
                                <span class="text-muted">Freight</span>
                                <div class="fw-semibold">₹{{ formatMoney(group.freight_amount) }}</div>
                            </div>
                            <div v-if="group.displays_gst" class="col-md-4">
                                <span class="text-muted">GST ({{ group.gst_rate }}%)</span>
                                <div>₹{{ formatMoney(group.gst_amount) }}</div>
                            </div>
                            <div class="col-md-4">
                                <span class="text-muted">Total</span>
                                <div class="fw-semibold text-primary">₹{{ formatMoney(group.total_amount) }}</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <template #footer>
                <button type="button" class="btn btn-secondary" @click="closeGenerateInvoices">Cancel</button>
                <AppButton
                    label="Generate Invoice(s)"
                    :disabled="invoicePreviewLoading || invoiceGenerating || invoicePreviewGroups.length === 0"
                    @click="promptGenerateInvoicesConfirm"
                />
            </template>
        </AppModal>

        <AppConfirmModal
            :show="showInvoiceGenerateConfirm"
            title="Confirm invoice generation"
            message="Review the invoices below, then confirm to generate."
            icon="bi bi-file-earmark-text"
            icon-tone="warning"
            size="xl"
            :loading="invoiceGenerating"
            confirm-label="Confirm & Generate"
            loading-label="Generating…"
            confirm-variant="warning"
            confirm-icon="bi bi-check2-circle"
            content-class="app-confirm-modal__content app-confirm-modal__content--compact border-0 shadow-lg"
            body-class="app-confirm-modal__body--compact"
            @cancel="cancelInvoiceGenerateConfirm"
            @confirm="confirmGenerateInvoices"
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
                    <label class="form-label">Freight on bilty</label>
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
                    variant="primary"
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
    summarizeTripForm,
    legLoadCategoryMeta,
    tripArchiveDisabledTitle,
    tripStatusMeta,
    tripTypeLabel,
} from '../../api/trips';
import TripArchiveConfirmModal from '../../components/trips/TripArchiveConfirmModal.vue';
import TripInlineStatusActions from '../../components/trips/TripInlineStatusActions.vue';
import TripDieselPerformanceCard from '../../components/trips/TripDieselPerformanceCard.vue';
import AppButton from '../../components/ui/AppButton.vue';
import AppConfirmModal from '../../components/ui/AppConfirmModal.vue';
import AppDateInput from '../../components/ui/AppDateInput.vue';
import AppModal from '../../components/ui/AppModal.vue';
import { generateForTrip, previewForTrip } from '../../api/invoices';
import { useApiFormErrors } from '../../composables/useApiFormErrors';
import { usePermissions } from '../../composables/usePermissions';
import { useTripDetailListReturn } from '../../composables/useTripListReturn';
import { useRequireCompanyContext } from '../../composables/useRequireCompanyContext';
import { useAppToast } from '../../composables/useAppToast';
import { dateOnlyFromDatetimeLocal, formatDate, formatDateTime } from '../../utils/datetime';
import { buildTripArchiveConfirmViewModel } from '../../utils/tripArchiveConfirm';
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

const canManage = computed(() => canInCompany('trips.edit'));
const canArchive = computed(() => canInCompany('trips.archive') || canInCompany('trips.delete'));
const canArchiveThisTrip = computed(() => {
    return canArchive.value && trip.value && canArchiveTrip(trip.value.status, user.value);
});

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
                : `${Number(summary.expectedMileage).toLocaleString('en-IN', { maximumFractionDigits: 2 })} KM/Ltr`,
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
        && trip.value?.status === 'COMPLETED'
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

    const label = trip.value?.status === 'COMPLETED' ? 'completed' : 'cancelled';

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

const headerMetaText = computed(() => {
    const parts = [];

    if (trip.value?.vehicle?.registration_number) {
        parts.push(trip.value.vehicle.registration_number);
    }

    if (trip.value?.driver?.name) {
        parts.push(trip.value.driver.name);
    }

    if (trip.value?.start_at) {
        parts.push(formatDateTime(trip.value.start_at));
    }

    return parts.join(' · ') || '—';
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

const tripDuration = computed(() => {
    const start = dateOnlyFromDatetimeLocal(trip.value?.start_at);
    const end = dateOnlyFromDatetimeLocal(trip.value?.end_at || trip.value?.actual_arrival_at);

    if (!start || !end) {
        return '';
    }

    const startMs = Date.parse(start);
    const endMs = Date.parse(end);

    if (!Number.isFinite(startMs) || !Number.isFinite(endMs) || endMs < startMs) {
        return '';
    }

    const days = Math.round((endMs - startMs) / (1000 * 60 * 60 * 24)) + 1;

    return `${days} day${days === 1 ? '' : 's'}`;
});

const profitMargin = computed(() => {
    const freight = Number(trip.value?.total_freight_amount ?? 0);

    if (freight <= 0) {
        return null;
    }

    const profit = Number(trip.value?.profit_amount ?? 0);

    return ((profit / freight) * 100).toFixed(1);
});

const expenseGroups = computed(() => {
    const groups = [];
    const generalItems = expenses.value.filter(
        (expense) => expense.route_leg_sequence === '' || expense.route_leg_sequence == null,
    );

    if (generalItems.length > 0) {
        groups.push({
            key: 'general',
            label: 'Whole trip (general)',
            items: generalItems,
            total: sumExpenses(generalItems),
        });
    }

    routeLegs.value.forEach((leg, index) => {
        const sequence = leg.sequence_no ?? index + 1;
        const items = expenses.value.filter(
            (expense) => Number(expense.route_leg_sequence) === Number(sequence),
        );

        if (items.length === 0) {
            return;
        }

        const from = leg.from_city || leg.from_location || '—';
        const to = leg.to_city || leg.to_location || '—';

        groups.push({
            key: `stop-${sequence}`,
            label: `Stop ${sequence}: ${from} → ${to}`,
            items,
            total: sumExpenses(items),
        });
    });

    const unmatched = expenses.value.filter((expense) => {
        const seq = expense.route_leg_sequence;

        if (seq === '' || seq == null) {
            return generalItems.length === 0;
        }

        return !routeLegs.value.some(
            (leg, index) => Number(leg.sequence_no ?? index + 1) === Number(seq),
        );
    });

    if (unmatched.length > 0 && groups.length === 0) {
        groups.push({
            key: 'all',
            label: 'All expenses',
            items: unmatched,
            total: sumExpenses(unmatched),
        });
    }

    return groups;
});

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

function todayDateString() {
    const now = new Date();
    const month = String(now.getMonth() + 1).padStart(2, '0');
    const day = String(now.getDate()).padStart(2, '0');

    return `${now.getFullYear()}-${month}-${day}`;
}

function buildInvoicePreviewPayload() {
    const groups = {};

    invoicePreviewGroups.value.forEach((group) => {
        groups[group.customer_id] = {
            invoice_date: group.invoice_date || null,
            due_date: group.due_date || null,
        };
    });

    return { groups };
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
    showGenerateInvoices.value = false;
    showInvoiceGenerateConfirm.value = false;
    invoicePreviewGroups.value = [];
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

async function confirmGenerateInvoices() {
    if (!trip.value?.id || invoicePreviewGroups.value.length === 0) {
        return;
    }

    if (!validateInvoiceDates()) {
        showInvoiceGenerateConfirm.value = false;
        showGenerateInvoices.value = true;

        return;
    }

    invoiceGenerating.value = true;
    clearInvoiceErrors();

    try {
        const invoices = await generateForTrip(trip.value.id, buildInvoicePreviewPayload());
        const count = Array.isArray(invoices) ? invoices.length : 1;
        toast.success(`${count} invoice${count === 1 ? '' : 's'} generated.`);
        closeGenerateInvoices();
        await loadTrip();
    } catch (error) {
        showInvoiceGenerateConfirm.value = false;
        showGenerateInvoices.value = true;
        applyInvoiceApiError(error, 'Could not generate invoices.');
    } finally {
        invoiceGenerating.value = false;
    }
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
.invoice-generate-trip-summary {
    padding: 0.85rem 1rem;
    background: #f8fafc;
    border: 1px solid #e5e7eb;
    border-radius: 0.5rem;
}

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

.trip-detail-km-bar {
    display: flex;
    flex-wrap: wrap;
    gap: 1rem 1.5rem;
    padding: 0.85rem 1rem;
    background: #f8fafc;
}

.trip-detail-expense-group + .trip-detail-expense-group {
    margin-top: 1rem;
    padding-top: 1rem;
    border-top: 1px solid #e5e7eb;
}

.trip-detail-expense-group__header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 0.5rem;
    margin-bottom: 0.5rem;
    font-size: 0.875rem;
    font-weight: 600;
}
</style>
