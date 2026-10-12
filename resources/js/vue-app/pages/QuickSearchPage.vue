<template>
    <div class="quick-search-page">
        <div class="card mb-4 mt-2">
            <div class="card-header border-0 pb-0">
                <h3 class="card-title">Quick Search</h3>
            </div>
            <div class="card-body pt-3 pb-4">
                <div class="input-group input-group-lg">
                    <span class="input-group-text text-muted">
                        <i class="fas fa-search"></i>
                    </span>
                    <input ref="searchInputRef" v-model="localQuery" type="text" class="form-control"
                        placeholder="Search Trip No. (Ctrl+K to focus)" autofocus />
                    <button v-if="localQuery" class="btn btn-light border" type="button" @click="clearSearch">
                        <i class="fas fa-times text-muted"></i>
                    </button>
                </div>
                <div v-if="loading" class="text-muted mt-2 small">
                    <i class="fas fa-spinner fa-spin me-1"></i> Searching...
                </div>
            </div>
        </div>

        <div v-if="detail" class="detail-dashboard">
            <div class="row" v-if="detail && detail.trip">
                <div class="col-12 mb-3">
                    <div class="card shadow-sm charge-card">
                        <div
                            class="card-header d-flex justify-content-between align-items-center py-2 px-3 w-100 flex-wrap gap-2">
                            <div class="d-flex align-items-center gap-2 flex-wrap">
                                <strong>
                                    Trip Information (
                                    <RouterLink :to="{ name: 'trips.show', params: { id: detail.trip.id } }"
                                        class="text-info-emphasis text-decoration-none" title="Open Trip View">
                                        {{ detail.trip.trip_number }}
                                    </RouterLink>
                                    )
                                </strong>
                                <span class="badge ms-1" :class="statusClass(detail.trip.status)">
                                    {{ String(detail.trip.status || '').replace(/_/g, ' ').toUpperCase() }}
                                </span>
                            </div>
                            <div class="d-flex align-items-center gap-2 ms-auto">
                                <RouterLink
                                    :to="{ name: 'trips.show', params: { id: detail.trip.id }, query: { printBilty: '1' } }"
                                    class="btn btn-sm btn-primary" title="Print Trip / Lorry Receipt">
                                    <i class="fas fa-print me-1"></i> Print
                                </RouterLink>
                            </div>
                        </div>
                        <div class="card-body trip-info-grid small">
                            <div class="trip-info-grid__item">
                                <span class="trip-info-grid__label">Start Station</span>
                                <div class="trip-info-grid__value">{{ startStationLabel }}</div>
                            </div>
                            <div class="trip-info-grid__item">
                                <span class="trip-info-grid__label">Trip Type</span>
                                <div class="trip-info-grid__value">{{ tripTypeLabel(detail.trip.trip_type) }}</div>
                            </div>
                            <div class="trip-info-grid__item">
                                <span class="trip-info-grid__label">Start Date</span>
                                <div class="trip-info-grid__value">{{ formatDate(detail.trip.start_at) }}</div>
                            </div>
                            <div class="trip-info-grid__item">
                                <span class="trip-info-grid__label d-flex align-items-center gap-1 flex-wrap">
                                    End Date
                                    <span v-if="arrivalTiming === 'on-time'" class="badge bg-info text-dark py-1"
                                        title="Arrived on planned end date">On Time</span>
                                    <span v-else-if="arrivalTiming === 'early'" class="badge bg-success py-1"
                                        title="Arrived before planned end date">Early</span>
                                    <span v-else-if="arrivalTiming === 'late'" class="badge bg-danger py-1"
                                        title="Arrived after planned end date">Late</span>
                                </span>
                                <div class="trip-info-grid__value">{{ formatDate(detail.trip.end_at) }}</div>
                            </div>
                            <div class="trip-info-grid__item">
                                <span class="trip-info-grid__label">Actual Arrival</span>
                                <div class="trip-info-grid__value">{{ formatDate(detail.trip.actual_arrival_at) }}</div>
                            </div>
                            <div class="trip-info-grid__item">
                                <span class="trip-info-grid__label">Truck</span>
                                <div class="trip-info-grid__value">
                                    <RouterLink v-if="detail.trip.vehicle?.id"
                                        :to="{ name: 'trucks.show', params: { vehicleId: detail.trip.vehicle.id } }"
                                        class="text-decoration-none fw-semibold">
                                        {{ detail.trip.vehicle.registration_number }}
                                    </RouterLink>
                                    <span v-else>{{ detail.trip.vehicle?.registration_number || '—' }}</span>
                                </div>
                                <div v-if="vehicleMeta" class="trip-info-grid__sub">{{ vehicleMeta }}</div>
                            </div>
                            <div class="trip-info-grid__item">
                                <span class="trip-info-grid__label">Truck Type</span>
                                <div class="trip-info-grid__value">{{ detail.trip.vehicle?.vehicle_type_label ??
                                    detail.trip.vehicle?.vehicle_type ?? '—' }}</div>
                            </div>
                            <div class="trip-info-grid__item">
                                <span class="trip-info-grid__label">Truck Running Meter</span>
                                <div class="trip-info-grid__value">{{ formatKm(detail.trip.vehicle?.odometer_km) }}
                                </div>
                            </div>
                            <div class="trip-info-grid__item">
                                <span class="trip-info-grid__label">Expected Mileage</span>
                                <div class="trip-info-grid__value">{{ detail.trip.planned_mileage_km_per_liter>0 ?
                                    `${formatKm(detail.trip.planned_mileage_km_per_liter)} KM/LTR` : '—' }}</div>
                            </div>
                            <div class="trip-info-grid__item">
                                <span class="trip-info-grid__label">Max Load</span>
                                <div class="trip-info-grid__value">{{ detail.trip.max_load_ton>0 ?
                                    `${formatKm(detail.trip.max_load_ton)} Ton` : '—' }}</div>
                            </div>
                            <div class="trip-info-grid__item">
                                <span class="trip-info-grid__label">Expected Diesel</span>
                                <div class="trip-info-grid__value">{{ detail.trip.planned_diesel_liters ?
                                    `${formatKm(detail.trip.planned_diesel_liters)} LTR` : '—' }}</div>
                            </div>
                            <div class="trip-info-grid__item">
                                <span class="trip-info-grid__label">Diesel Rate</span>
                                <div class="trip-info-grid__value">{{ detail.trip.diesel_rate_per_liter ?
                                    `₹${formatMoney(detail.trip.diesel_rate_per_liter)}/LTR` : '—' }}</div>
                            </div>
                            <div class="trip-info-grid__item">
                                <span class="trip-info-grid__label">Start Meter</span>
                                <div class="trip-info-grid__value">{{
                                    formatOdometerReading(detail.trip.start_meter_reading_km) }}</div>
                            </div>
                            <div class="trip-info-grid__item">
                                <span class="trip-info-grid__label">End Meter</span>
                                <div class="trip-info-grid__value">{{
                                    formatOdometerReading(detail.trip.end_meter_reading_km) }}</div>
                            </div>
                            <div class="trip-info-grid__item">
                                <span class="trip-info-grid__label">Primary Driver</span>
                                <div class="trip-info-grid__value fw-semibold">{{ detail.trip.driver?.name ?? '—' }}
                                </div>
                                <div v-if="detail.trip.driver?.phone" class="trip-info-grid__sub">{{
                                    detail.trip.driver.phone }}</div>
                            </div>
                            <div class="trip-info-grid__item">
                                <span class="trip-info-grid__label">Substitute Driver</span>
                                <div class="trip-info-grid__value">{{ detail.trip.substitute_driver?.name ?? '—' }}
                                </div>
                                <div v-if="detail.trip.substitute_driver?.phone" class="trip-info-grid__sub">
                                    {{ detail.trip.substitute_driver.phone }}
                                </div>
                            </div>
                            <div class="trip-info-grid__item">
                                <span class="trip-info-grid__label">Customer</span>
                                <div class="trip-info-grid__value">{{ detail.trip.customer?.name ||
                                    detail.trip.customer_name || '—' }}</div>
                            </div>
                            <div class="trip-info-grid__item trip-info-grid__item--ref-status">
                                <div class="trip-info-ref-status">
                                    <div class="trip-info-ref-status__ref">
                                        <span class="trip-info-grid__label">Reference</span>
                                        <div class="trip-info-grid__value">{{ detail.trip.broker_name ||
                                            detail.trip.broker?.name || '—' }}</div>
                                    </div>
                                </div>
                            </div>
                            <div v-if="detail.trip.notes?.trim()"
                                class="trip-info-grid__item trip-info-grid__item--full">
                                <span class="trip-info-grid__label">Notes</span>
                                <div class="trip-info-grid__value" style="white-space: pre-wrap">{{ detail.trip.notes }}
                                </div>
                            </div>
                            <div v-if="detail.trip.status === 'CANCELLED'" class="trip-info-grid__item">
                                <span class="trip-info-grid__label">Cancelled On</span>
                                <div class="trip-info-grid__value">{{ formatDate(detail.trip.cancelled_at) }}</div>
                            </div>
                            <div v-if="detail.trip.status === 'CANCELLED'"
                                class="trip-info-grid__item trip-info-grid__item--full">
                                <div class="alert alert-danger mb-0 py-2">
                                    <div class="small">
                                        <strong>Cancellation reason:</strong>
                                        {{ detail.trip.cancellation_reason || '—' }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-12 mb-4">
                    <div class="card shadow-sm charge-card">
                        <div class="card-header trip-route-header py-2 px-3 bg-secondary text-white">
                            <strong class="trip-route-header__title">
                                <i class="fas fa-map-marker-alt me-2"></i>
                                {{ isMultiPoint ? 'Pickups & Drops' : 'Route Stops' }} <span
                                    class="badge bg-secondary-subtle text-secondary-emphasis border ms-2">
                                    {{ isMultiPoint ? 'MULTI-POINT' : 'SINGLE-POINT' }}
                                </span>
                            </strong>
                            <span v-if="detail.trip.route_legs?.length || detail.trip.expenses?.length"
                                class="trip-route-header__totals">
                                <span class="badge bg-success p-1 px-2 me-2 fs-8">₹{{
                                    formatMoney(detail.trip.total_freight_amount) }} freight</span>
                                <template v-if="Number(detail.trip.total_custom_amount) > 0">
                                    <span class="badge bg-secondary p-1 px-2 me-2 fs-8">₹{{
                                        formatMoney(detail.trip.total_custom_amount) }} additional</span>
                                </template>
                                <span class="badge bg-danger p-1 px-2 fs-8">₹{{
                                    formatMoney(detail.trip.total_expense_amount) }} expenses</span>
                            </span>
                        </div>
                        <div class="card-body p-3">
                            <div v-if="routePreview" class="alert alert-light border mb-3 py-2 small">
                                <strong>Route:</strong> {{ routePreview }}
                            </div>

                            <!-- Multi-Point Tree & Stops Hierarchy -->
                            <template v-if="isMultiPoint && detail.trip.pickups && detail.trip.pickups.length">
                                <!-- Tree overview matching TripDetailPage -->
                                <div class="trip-mp-tree mb-3">
                                    <div v-for="pickup in detail.trip.pickups" :key="pickup.id || pickup.pickup_code"
                                        class="trip-mp-tree__pickup">
                                        <div class="trip-mp-tree__pickup-head">
                                            <strong>{{ pickup.pickup_code || 'Pickup' }}</strong>
                                            <span class="text-muted">
                                                {{ pickup.station?.name || pickup.station_name || '—' }}
                                                · {{ Number(pickup.weight_ton ?? 0).toLocaleString('en-IN', {
                                                maximumFractionDigits: 3 }) }} ton
                                            </span>
                                            <span class="badge ms-auto" :class="{
                                                'bg-success': pickup.delivery_status === 'delivered',
                                                'bg-warning text-dark': pickup.delivery_status === 'partial',
                                                'bg-secondary': !pickup.delivery_status || pickup.delivery_status === 'pending',
                                            }">
                                                {{ pickup.delivery_status || 'pending' }}
                                            </span>
                                        </div>
                                        <div class="trip-mp-tree__pickup-meta small text-muted mb-2">
                                            Delivered {{ Number(pickup.delivered_weight_ton ?? pickup.delivered_weight
                                                ?? 0).toLocaleString('en-IN', { maximumFractionDigits: 3 }) }}
                                            · Remaining {{ Number(pickup.remaining_weight_ton ?? pickup.remaining_weight
                                                ?? 0).toLocaleString('en-IN', { maximumFractionDigits: 3 }) }}
                                            <template v-if="pickup.consignor"> · {{ pickup.consignor }}</template>
                                        </div>
                                        <div v-for="drop in getDropsForPickup(pickup)" :key="drop.id || drop.drop_code"
                                            class="trip-mp-tree__drop">
                                            <span class="trip-mp-tree__drop-code">{{ drop.drop_code || 'Drop' }}</span>
                                            <span>
                                                → {{ drop.to_city || drop.to_station?.name || '—' }}
                                                · {{ Number(drop.weight_ton ?? 0).toLocaleString('en-IN', {
                                                maximumFractionDigits: 3 }) }} ton
                                                · ₹{{ formatMoney(drop.freight_amount) }}
                                            </span>
                                            <span v-if="drop.lr_number" class="text-muted small">LR {{ drop.lr_number
                                                }}</span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Detailed Cards for Pickups & Linked Drops in trip-detail-stop style -->
                                <div class="trip-detail-stops">
                                    <div v-for="(pickup, pIndex) in detail.trip.pickups" :key="pickup.id || pIndex"
                                        class="trip-detail-stop mb-3">
                                        <div class="trip-detail-stop__head">
                                            <div class="trip-detail-stop__head-main">
                                                <span class="badge bg-primary fw-bold">{{ pickup.pickup_code || ('P' +
                                                    (pIndex + 1)) }}</span>
                                                <span class="trip-detail-stop__sep">·</span>
                                                <strong class="text-dark">
                                                    {{ pickup.station?.name || pickup.from_address || 'Pickup Station'
                                                    }}
                                                    <span
                                                        v-if="pickup.station?.city?.name && pickup.station?.city?.name !== pickup.station?.name"
                                                        class="text-muted small fw-normal">
                                                        ({{ pickup.station.city.name }})
                                                    </span>
                                                </strong>
                                                <span v-if="pickup.pickup_date" class="text-muted small">
                                                    <i class="bi bi-calendar3 me-1"></i>{{
                                                    formatDate(pickup.pickup_date) }}
                                                </span>
                                            </div>
                                            <div class="trip-detail-stop__head-meta">
                                                <span class="badge bg-light text-dark border">
                                                    {{ formatKm(pickup.weight_ton) }} Ton
                                                </span>
                                                <span class="badge" :class="{
                                                    'bg-success': pickup.delivery_status === 'delivered',
                                                    'bg-warning text-dark': pickup.delivery_status === 'partial',
                                                    'bg-secondary': !pickup.delivery_status || pickup.delivery_status === 'pending',
                                                }">
                                                    {{ pickup.delivery_status || 'pending' }}
                                                </span>
                                            </div>
                                        </div>

                                        <div class="trip-detail-stop__body">
                                            <div class="trip-detail-stop__fields">
                                                <div class="trip-detail-stop__field">
                                                    <span class="trip-detail-stop__label">Consignor</span>
                                                    <span class="trip-detail-stop__value">
                                                        {{ pickup.consignor || '—' }}
                                                        <template v-if="pickup.from_address">
                                                            <br>
                                                            <span class="text-muted small mt-1"><i
                                                                    class="bi bi-geo-alt me-1"></i>{{
                                                                pickup.from_address }}</span>
                                                        </template>
                                                    </span>
                                                </div>
                                                <div class="trip-detail-stop__field">
                                                    <span class="trip-detail-stop__label">Pickup Date</span>
                                                    <span class="trip-detail-stop__value">{{
                                                        formatDate(pickup.pickup_date) }}</span>
                                                </div>
                                                <div class="trip-detail-stop__field">
                                                    <span class="trip-detail-stop__label">Goods Type</span>
                                                    <span class="trip-detail-stop__value">{{ pickup.goods_name || '—'
                                                        }}</span>
                                                </div>
                                                <div class="trip-detail-stop__field">
                                                    <span class="trip-detail-stop__label">Goods Value</span>
                                                    <span class="trip-detail-stop__value">{{ pickup.goods_value ?
                                                        `₹${formatMoney(pickup.goods_value)}`
                                                        : '—' }}</span>
                                                </div>
                                                <div class="trip-detail-stop__field">
                                                    <span class="trip-detail-stop__label">No. of Units</span>
                                                    <span class="trip-detail-stop__value">{{ pickup.no_of_units ?? '—'
                                                        }}</span>
                                                </div>
                                                <div class="trip-detail-stop__field">
                                                    <span class="trip-detail-stop__label">Weight (Ton)</span>
                                                    <span class="trip-detail-stop__value">{{ pickup.weight_ton ?
                                                        formatKm(pickup.weight_ton) : '—'
                                                        }}</span>
                                                </div>
                                                <div class="trip-detail-stop__field">
                                                    <span class="trip-detail-stop__label">Delivered / Remaining</span>
                                                    <span class="trip-detail-stop__value">
                                                        Del: {{ Number(pickup.delivered_weight_ton ??
                                                            pickup.delivered_weight ??
                                                        0).toLocaleString('en-IN', { maximumFractionDigits: 3 }) }} Ton
                                                        ·
                                                        Rem: {{ Number(pickup.remaining_weight_ton ??
                                                            pickup.remaining_weight ??
                                                        0).toLocaleString('en-IN', { maximumFractionDigits: 3 }) }} Ton
                                                    </span>
                                                </div>
                                            </div>

                                            <div class="trip-detail-stop__meta">
                                                <span>
                                                    E-Way Bill: <strong>{{ pickup.ewaybill_no || '—' }}</strong>
                                                </span>
                                                <span>
                                                    Expiry: <strong>{{ formatDate(pickup.ewaybill_expiry) }}</strong>
                                                    <span v-if="ewayExpiryStatus(pickup.ewaybill_expiry)"
                                                        :class="['badge ms-1', ewayExpiryStatus(pickup.ewaybill_expiry).badgeClass]"
                                                        style="font-size: 0.65rem;">
                                                        {{ ewayExpiryStatus(pickup.ewaybill_expiry).label }}
                                                    </span>
                                                </span>
                                            </div>

                                            <!-- Associated Drops -->
                                            <div class="p-3 bg-light bg-opacity-25 border-top">
                                                <div
                                                    class="text-uppercase text-muted fw-bold small mb-2 d-flex justify-content-between align-items-center">
                                                    <span>Associated Drops ({{ getDropsForPickup(pickup).length
                                                        }})</span>
                                                </div>
                                                <div v-if="getDropsForPickup(pickup).length === 0"
                                                    class="text-muted small fst-italic py-2">
                                                    No drops linked to this pickup.
                                                </div>
                                                <div v-else class="trip-detail-stops">
                                                    <div v-for="(drop, dIndex) in getDropsForPickup(pickup)"
                                                        :key="drop.id || dIndex"
                                                        class="trip-detail-stop shadow-none border">
                                                        <div class="trip-detail-stop__head">
                                                            <div class="trip-detail-stop__head-main">
                                                                <span
                                                                    class="badge bg-success-subtle text-success border border-success-subtle fw-semibold">
                                                                    {{ drop.drop_code || ('Drop ' + (dIndex + 1)) }}
                                                                </span>
                                                                <span class="trip-detail-stop__sep">·</span>
                                                                <strong class="text-dark">
                                                                    → {{ drop.to_station?.name || drop.to_city || '—' }}
                                                                    <span
                                                                        v-if="drop.to_station?.city?.name && drop.to_station?.city?.name !== drop.to_station?.name"
                                                                        class="text-muted small fw-normal">
                                                                        ({{ drop.to_station.city.name }})
                                                                    </span>
                                                                </strong>
                                                                <span v-if="drop.lr_number"
                                                                    class="badge bg-primary-subtle text-primary border border-primary-subtle ms-1">
                                                                    LR: {{ drop.lr_number }}
                                                                </span>
                                                            </div>
                                                            <div class="trip-detail-stop__head-meta">
                                                                <span class="trip-detail-stop__freight"
                                                                    :class="{ 'is-empty': !(Number(drop.freight_amount) > 0) }">
                                                                    {{ Number(drop.freight_amount) > 0 ?
                                                                    `₹${formatMoney(drop.freight_amount)}` : 'No freight' }}
                                                                </span>
                                                            </div>
                                                        </div>
                                                        <div class="trip-detail-stop__body">
                                                            <div class="trip-detail-stop__fields">
                                                                <div class="trip-detail-stop__field">
                                                                    <span
                                                                        class="trip-detail-stop__label">Customer</span>
                                                                    <span class="trip-detail-stop__value">{{
                                                                        drop.customer?.name ||
                                                                        detail.trip.customer_name || '—' }}</span>
                                                                </div>
                                                                <div class="trip-detail-stop__field">
                                                                    <span class="trip-detail-stop__label">Consignee
                                                                        (To)</span>
                                                                    <span class="trip-detail-stop__value">
                                                                        {{ drop.consignee || '—' }}
                                                                        <template v-if="drop.to_address">
                                                                            <br>
                                                                            <span class="text-muted small mt-1"><i
                                                                                    class="bi bi-geo-alt me-1"></i>{{
                                                                                drop.to_address }}</span>
                                                                        </template>
                                                                    </span>
                                                                </div>
                                                                <div class="trip-detail-stop__field">
                                                                    <span class="trip-detail-stop__label">Goods
                                                                        Type</span>
                                                                    <span class="trip-detail-stop__value">{{
                                                                        drop.goods_name || pickup.goods_name || '—'
                                                                        }}</span>
                                                                </div>
                                                                <div class="trip-detail-stop__field">
                                                                    <span class="trip-detail-stop__label">Goods
                                                                        Value</span>
                                                                    <span class="trip-detail-stop__value">{{
                                                                        drop.goods_value ?
                                                                        `₹${formatMoney(drop.goods_value)}` : '—'
                                                                        }}</span>
                                                                </div>
                                                                <div class="trip-detail-stop__field">
                                                                    <span class="trip-detail-stop__label">No. of
                                                                        Units</span>
                                                                    <span class="trip-detail-stop__value">{{
                                                                        drop.no_of_units ?? '—' }}</span>
                                                                </div>
                                                                <div class="trip-detail-stop__field">
                                                                    <span class="trip-detail-stop__label">Weight
                                                                        (Ton)</span>
                                                                    <span class="trip-detail-stop__value">{{
                                                                        drop.weight_ton ? formatKm(drop.weight_ton)
                                                                        : '—' }}</span>
                                                                </div>
                                                                <div class="trip-detail-stop__field">
                                                                    <span class="trip-detail-stop__label">Rate
                                                                        (₹/Ton)</span>
                                                                    <span class="trip-detail-stop__value">{{
                                                                        drop.freight_rate ?
                                                                        formatMoney(drop.freight_rate) : '—' }}</span>
                                                                </div>
                                                                <div
                                                                    class="trip-detail-stop__field trip-detail-stop__field--freight">
                                                                    <span class="trip-detail-stop__label">Freight
                                                                        (₹)</span>
                                                                    <span class="trip-detail-stop__value">₹{{
                                                                        formatMoney(drop.freight_amount) }}</span>
                                                                </div>
                                                                <div class="trip-detail-stop__field">
                                                                    <span class="trip-detail-stop__label">Advance /
                                                                        Balance</span>
                                                                    <span class="trip-detail-stop__value">
                                                                        <span
                                                                            :class="Number(drop.advance_received) > 0 ? 'text-success' : 'text-muted'">Adv:
                                                                            {{ Number(drop.advance_received) > 0 ?
                                                                            `₹${formatMoney(drop.advance_received)}` :
                                                                            '—' }}</span>
                                                                        <span class="mx-1">·</span>
                                                                        <span
                                                                            :class="Number(drop.balance_amount) > 0 ? 'text-danger' : 'text-muted'">Bal:
                                                                            {{ Number(drop.balance_amount) > 0 ?
                                                                            `₹${formatMoney(drop.balance_amount)}`
                                                                            : '—' }}</span>
                                                                    </span>
                                                                </div>
                                                                <div class="trip-detail-stop__field">
                                                                    <span class="trip-detail-stop__label">Remarks</span>
                                                                    <span class="trip-detail-stop__value">{{
                                                                        drop.remarks || '—' }}</span>
                                                                </div>
                                                            </div>
                                                            <div class="trip-detail-stop__meta">
                                                                <span>
                                                                    E-Way Bill: <strong>{{ drop.ewaybill_no || '—'
                                                                        }}</strong>
                                                                </span>
                                                                <span>
                                                                    Expiry: <strong>{{ formatDate(drop.ewaybill_expiry)
                                                                        }}</strong>
                                                                    <span v-if="ewayExpiryStatus(drop.ewaybill_expiry)"
                                                                        :class="['badge ms-1', ewayExpiryStatus(drop.ewaybill_expiry).badgeClass]"
                                                                        style="font-size: 0.65rem;">
                                                                        {{ ewayExpiryStatus(drop.ewaybill_expiry).label
                                                                        }}
                                                                    </span>
                                                                </span>
                                                                <span>
                                                                    KM/LTR: <strong>{{
                                                                        drop.expected_mileage_km_per_liter > 0?
                                                                        formatKm(drop.expected_mileage_km_per_liter) :
                                                                        '—' }}</strong>
                                                                </span>
                                                                <span>
                                                                    Distance: <strong>{{ getLegDistance(drop) ?
                                                                        `${formatKm(getLegDistance(drop))} KM` :
                                                                        '—' }}</strong>
                                                                </span>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Unallocated Drops if any -->
                                    <div v-if="unallocatedDrops.length" class="mt-3">
                                        <div class="text-uppercase text-muted fw-bold small mb-2">Additional Stops /
                                            Drops</div>
                                        <div class="trip-detail-stops">
                                            <div v-for="(leg, index) in unallocatedDrops" :key="leg.id || index"
                                                class="trip-detail-stop">
                                                <div class="trip-detail-stop__head">
                                                    <div class="trip-detail-stop__head-main">
                                                        <span class="badge bg-secondary">{{ leg.drop_code || ('Drop ' +
                                                            (index + 1)) }}</span>
                                                        <span class="trip-detail-stop__sep">·</span>
                                                        <strong class="text-dark">
                                                            → {{ leg.to_station?.name || leg.to_city || '—' }}
                                                            <span
                                                                v-if="leg.to_station?.city?.name && leg.to_station?.city?.name !== leg.to_station?.name"
                                                                class="text-muted small fw-normal">
                                                                ({{ leg.to_station.city.name }})
                                                            </span>
                                                        </strong>
                                                        <span v-if="leg.lr_number"
                                                            class="badge bg-primary-subtle text-primary border border-primary-subtle ms-1">
                                                            LR: {{ leg.lr_number }}
                                                        </span>
                                                    </div>
                                                    <div class="trip-detail-stop__head-meta">
                                                        <span class="trip-detail-stop__freight"
                                                            :class="{ 'is-empty': !(Number(leg.freight_amount) > 0) }">
                                                            {{ Number(leg.freight_amount) > 0 ?
                                                            `₹${formatMoney(leg.freight_amount)}` : 'No freight' }}
                                                        </span>
                                                    </div>
                                                </div>
                                                <div class="trip-detail-stop__body">
                                                    <div class="trip-detail-stop__fields">
                                                        <div class="trip-detail-stop__field">
                                                            <span class="trip-detail-stop__label">Customer</span>
                                                            <span class="trip-detail-stop__value">{{ leg.customer?.name
                                                                || detail.trip.customer_name ||
                                                                '—' }}</span>
                                                        </div>
                                                        <div class="trip-detail-stop__field">
                                                            <span class="trip-detail-stop__label">Consignee (To)</span>
                                                            <span class="trip-detail-stop__value">
                                                                {{ leg.consignee || '—' }}
                                                                <template v-if="leg.to_address">
                                                                    <br>
                                                                    <span class="text-muted small mt-1"><i
                                                                            class="bi bi-geo-alt me-1"></i>{{
                                                                        leg.to_address }}</span>
                                                                </template>
                                                            </span>
                                                        </div>
                                                        <div class="trip-detail-stop__field">
                                                            <span class="trip-detail-stop__label">Goods Type</span>
                                                            <span class="trip-detail-stop__value">{{ leg.goods_name ||
                                                                '—' }}</span>
                                                        </div>
                                                        <div class="trip-detail-stop__field">
                                                            <span class="trip-detail-stop__label">Goods Value</span>
                                                            <span class="trip-detail-stop__value">{{ leg.goods_value ?
                                                                `₹${formatMoney(leg.goods_value)}` : '—' }}</span>
                                                        </div>
                                                        <div class="trip-detail-stop__field">
                                                            <span class="trip-detail-stop__label">No. of Units</span>
                                                            <span class="trip-detail-stop__value">{{ leg.no_of_units ??
                                                                '—' }}</span>
                                                        </div>
                                                        <div class="trip-detail-stop__field">
                                                            <span class="trip-detail-stop__label">Weight (Ton)</span>
                                                            <span class="trip-detail-stop__value">{{ leg.weight_ton ?
                                                                formatKm(leg.weight_ton) : '—'
                                                                }}</span>
                                                        </div>
                                                        <div class="trip-detail-stop__field">
                                                            <span class="trip-detail-stop__label">Rate (₹/Ton)</span>
                                                            <span class="trip-detail-stop__value">{{ leg.freight_rate ?
                                                                formatMoney(leg.freight_rate) :
                                                                '—' }}</span>
                                                        </div>
                                                        <div
                                                            class="trip-detail-stop__field trip-detail-stop__field--freight">
                                                            <span class="trip-detail-stop__label">Freight (₹)</span>
                                                            <span class="trip-detail-stop__value">₹{{
                                                                formatMoney(leg.freight_amount) }}</span>
                                                        </div>
                                                        <div class="trip-detail-stop__field">
                                                            <span class="trip-detail-stop__label">Advance /
                                                                Balance</span>
                                                            <span class="trip-detail-stop__value">
                                                                <span
                                                                    :class="Number(leg.advance_received) > 0 ? 'text-success' : 'text-muted'">Adv:
                                                                    {{
                                                                        Number(leg.advance_received) > 0 ?
                                                                    `₹${formatMoney(leg.advance_received)}` : '—'
                                                                    }}</span>
                                                                <span class="mx-1">·</span>
                                                                <span
                                                                    :class="Number(leg.balance_amount) > 0 ? 'text-danger' : 'text-muted'">Bal:
                                                                    {{
                                                                        Number(leg.balance_amount) > 0 ?
                                                                    `₹${formatMoney(leg.balance_amount)}` : '—'
                                                                    }}</span>
                                                            </span>
                                                        </div>
                                                        <div class="trip-detail-stop__field">
                                                            <span class="trip-detail-stop__label">Remarks</span>
                                                            <span class="trip-detail-stop__value">{{ leg.remarks || '—'
                                                                }}</span>
                                                        </div>
                                                    </div>
                                                    <div class="trip-detail-stop__meta">
                                                        <span>
                                                            E-Way Bill: <strong>{{ leg.ewaybill_no || '—' }}</strong>
                                                        </span>
                                                        <span>
                                                            Expiry: <strong>{{ formatDate(leg.ewaybill_expiry)
                                                                }}</strong>
                                                            <span v-if="ewayExpiryStatus(leg.ewaybill_expiry)"
                                                                :class="['badge ms-1', ewayExpiryStatus(leg.ewaybill_expiry).badgeClass]"
                                                                style="font-size: 0.65rem;">
                                                                {{ ewayExpiryStatus(leg.ewaybill_expiry).label }}
                                                            </span>
                                                        </span>
                                                        <span>
                                                            KM/LTR: <strong>{{ leg.expected_mileage_km_per_liter>0 ?
                                                                formatKm(leg.expected_mileage_km_per_liter) : '—'
                                                                }}</strong>
                                                        </span>
                                                        <span>
                                                            Distance: <strong>{{ getLegDistance(leg) ?
                                                                `${formatKm(getLegDistance(leg))} KM` : '—'
                                                                }}</strong>
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </template>

                            <!-- Standard (Linear) Route Stops Layout matching TripDetailPage -->
                            <template v-else-if="detail.trip.route_legs && detail.trip.route_legs.length">
                                <div class="trip-detail-stops">
                                    <div v-for="(leg, index) in detail.trip.route_legs" :key="leg.id || index"
                                        class="trip-detail-stop">
                                        <div class="trip-detail-stop__head">
                                            <div class="trip-detail-stop__head-main">
                                                <span class="trip-detail-stop__num">Stop {{ leg.sequence_no ?? (index +
                                                    1) }}</span>
                                                <span class="trip-detail-stop__sep">·</span>
                                                <span class="trip-detail-stop__route">
                                                    {{ leg.from_station?.name || leg.from_city || (index === 0 ?
                                                        detail.trip.start_station?.name : (detail.trip.route_legs[index -
                                                    1]?.to_station?.name || detail.trip.route_legs[index - 1]?.to_city
                                                    || 'Start')) }}
                                                    →
                                                    {{ leg.to_station?.name || leg.to_city || '—' }}
                                                </span>
                                                <template v-if="legLoadMeta(leg.weight_ton)">
                                                    <span class="trip-detail-stop__sep">·</span>
                                                    <span class="trip-route-load-tag"
                                                        :class="`trip-route-load-tag--${legLoadMeta(leg.weight_ton).category}`">
                                                        {{ legLoadMeta(leg.weight_ton).label }}
                                                    </span>
                                                </template>
                                                <span v-if="leg.lr_number"
                                                    class="badge bg-primary-subtle text-primary border border-primary-subtle ms-1">
                                                    LR: {{ leg.lr_number }}
                                                </span>
                                            </div>
                                            <div class="trip-detail-stop__head-meta">
                                                <span class="trip-detail-stop__freight"
                                                    :class="{ 'is-empty': !(Number(leg.freight_amount) > 0) }">
                                                    {{ Number(leg.freight_amount) > 0 ?
                                                        `₹${formatMoney(leg.freight_amount)}` : 'No freight' }}
                                                </span>
                                            </div>
                                        </div>

                                        <div class="trip-detail-stop__body">
                                            <div class="trip-detail-stop__fields">
                                                <div class="trip-detail-stop__field">
                                                    <span class="trip-detail-stop__label">Customer</span>
                                                    <span class="trip-detail-stop__value">{{ leg.customer_name ||
                                                        leg.customer?.name ||
                                                        detail.trip.customer_name || '—' }}</span>
                                                </div>
                                                <div class="trip-detail-stop__field">
                                                    <span class="trip-detail-stop__label">Consignor (From)</span>
                                                    <span class="trip-detail-stop__value">
                                                        {{ leg.consignor || '—' }}
                                                        <template v-if="leg.from_address">
                                                            <br>
                                                            <span class="text-muted small mt-1"><i
                                                                    class="bi bi-geo-alt me-1"></i>{{ leg.from_address
                                                                }}</span>
                                                        </template>
                                                    </span>
                                                </div>
                                                <div class="trip-detail-stop__field">
                                                    <span class="trip-detail-stop__label">Consignee (To)</span>
                                                    <span class="trip-detail-stop__value">
                                                        {{ leg.consignee || '—' }}
                                                        <template v-if="leg.to_address">
                                                            <br>
                                                            <span class="text-muted small mt-1"><i
                                                                    class="bi bi-geo-alt me-1"></i>{{ leg.to_address
                                                                }}</span>
                                                        </template>
                                                    </span>
                                                </div>
                                                <div class="trip-detail-stop__field">
                                                    <span class="trip-detail-stop__label">Pickup Date</span>
                                                    <span class="trip-detail-stop__value">{{ formatDate(leg.pickup_date)
                                                        }}</span>
                                                </div>
                                                <div class="trip-detail-stop__field">
                                                    <span class="trip-detail-stop__label">Goods Type</span>
                                                    <span class="trip-detail-stop__value">{{ leg.goods_name || '—'
                                                        }}</span>
                                                </div>
                                                <div class="trip-detail-stop__field">
                                                    <span class="trip-detail-stop__label">Goods Value</span>
                                                    <span class="trip-detail-stop__value">
                                                        {{ leg.goods_value ? `₹${formatMoney(leg.goods_value)}` : '—' }}
                                                    </span>
                                                </div>
                                                <div class="trip-detail-stop__field">
                                                    <span class="trip-detail-stop__label">No. of Units</span>
                                                    <span class="trip-detail-stop__value">{{ leg.no_of_units ?? '—'
                                                        }}</span>
                                                </div>
                                                <div class="trip-detail-stop__field">
                                                    <span class="trip-detail-stop__label">Weight (Ton)</span>
                                                    <span class="trip-detail-stop__value">{{ leg.weight_ton ?
                                                        formatKm(leg.weight_ton) : '—' }}</span>
                                                </div>
                                                <div class="trip-detail-stop__field">
                                                    <span class="trip-detail-stop__label">Rate (₹/Ton)</span>
                                                    <span class="trip-detail-stop__value">{{ leg.freight_rate ?
                                                        formatMoney(leg.freight_rate) : '—' }}</span>
                                                </div>
                                                <div class="trip-detail-stop__field trip-detail-stop__field--freight">
                                                    <span class="trip-detail-stop__label">Freight (₹)</span>
                                                    <span class="trip-detail-stop__value">₹{{
                                                        formatMoney(leg.freight_amount) }}</span>
                                                </div>
                                                <div class="trip-detail-stop__field">
                                                    <span class="trip-detail-stop__label">Advance / Balance</span>
                                                    <span class="trip-detail-stop__value">
                                                        <span
                                                            :class="Number(leg.advance_received) > 0 ? 'text-success' : 'text-muted'">Adv:
                                                            {{
                                                                Number(leg.advance_received) > 0 ?
                                                            `₹${formatMoney(leg.advance_received)}` : '—' }}</span>
                                                        <span class="mx-1">·</span>
                                                        <span
                                                            :class="Number(leg.balance_amount) > 0 ? 'text-danger' : 'text-muted'">Bal:
                                                            {{
                                                                Number(leg.balance_amount) > 0 ?
                                                            `₹${formatMoney(leg.balance_amount)}` : '—' }}</span>
                                                    </span>
                                                </div>
                                                <div class="trip-detail-stop__field">
                                                    <span class="trip-detail-stop__label">Remarks</span>
                                                    <span class="trip-detail-stop__value">{{ leg.remarks || '—'
                                                        }}</span>
                                                </div>
                                            </div>

                                            <div class="trip-detail-stop__meta">
                                                <span>
                                                    E-Way Bill: <strong>{{ leg.ewaybill_no || '—' }}</strong>
                                                </span>
                                                <span>
                                                    Expiry: <strong>{{ formatDate(leg.ewaybill_expiry) }}</strong>
                                                    <span v-if="ewayExpiryStatus(leg.ewaybill_expiry)"
                                                        :class="['badge ms-1', ewayExpiryStatus(leg.ewaybill_expiry).badgeClass]"
                                                        style="font-size: 0.65rem;">
                                                        {{ ewayExpiryStatus(leg.ewaybill_expiry).label }}
                                                    </span>
                                                </span>
                                                <span>
                                                    KM/LTR: <strong>{{ leg.expected_mileage_km_per_liter>0 ?
                                                        formatKm(leg.expected_mileage_km_per_liter) : '—'
                                                        }}</strong>
                                                </span>
                                                <span>
                                                    Distance: <strong>{{ getLegDistance(leg) ?
                                                        `${formatKm(getLegDistance(leg))} KM` : '—' }}</strong>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </template>
                            <div v-else class="text-muted text-center py-4">No route legs recorded for this trip.</div>
                        </div>

                        <!-- Footer matching TripDetailPage km bar -->
                        <div v-if="detail.trip.route_legs?.length || detail.trip.expenses?.length"
                            class="card-footer bg-light p-0 border-top">
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
                                    <div class="trip-detail-km-bar__label">Total Distance</div>
                                    <strong>{{ getTripTotalDistance() ? `${formatKm(getTripTotalDistance())} KM` : '—'
                                        }}</strong>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-md-6 col-lg-6 mb-4">
                    <div class="card h-100 shadow-sm charge-card">
                        <div
                            class="card-header detail-section-header rounded-top bg-info text-white d-flex justify-content-between align-items-center py-2 px-3">
                            <span>
                                <i class="fas fa-money-bill-wave me-2"></i>
                                EXPENSES
                            </span>
                        </div>
                        <div class="card-body p-0">
                            <div v-if="detail.expenses && detail.expenses.length" class="table-responsive">
                                <table class="table table-sm table-hover align-middle mb-0 small">
                                    <thead class="table-light">
                                        <tr>
                                            <th class="ps-3 py-1">Date</th>
                                            <th class="py-1"> Stop</th>
                                            <th class="py-1">Type</th>
                                            <th class="py-1">Details</th>
                                            <th class="pe-3 py-1 text-end">Amount</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-for="exp in detail.expenses" :key="exp.id">
                                            <td class="ps-3 text-nowrap text-muted" style="font-size: 0.78rem;">
                                                {{ formatDate(exp.expense_date) || '—' }}
                                            </td>
                                            <td class="text-nowrap" style="font-size: 0.78rem;">
                                                <div v-if="exp.route_leg" class="text-muted small">
                                                    <i class="bi bi-geo-alt me-1"></i>
                                                    {{ exp.route_leg.to_station?.name || exp.route_leg.to_city ||
                                                        exp.route_leg.drop_code || 'Stop' }}
                                                </div>
                                                <div v-else class="text-muted small">General</div>
                                            </td>
                                            <td class="fw-medium text-dark text-nowrap">
                                                {{ formatType(exp.expense_type) }}
                                            </td>
                                            <td class="text-muted" style="font-size: 0.78rem;">
                                                <span v-if="exp.fuel_liters || exp.fuel_rate">
                                                    {{ exp.fuel_liters ? exp.fuel_liters + ' Ltr' : '' }}
                                                    {{ exp.fuel_rate ? '@ ₹' + exp.fuel_rate : '' }}
                                                </span>
                                                <span v-if="exp.fuel_odometer_km" class="ms-1">
                                                    ({{ exp.fuel_odometer_km }} KM)
                                                </span>
                                                <span v-if="exp.notes" class="ms-1 text-truncate d-inline-block"
                                                    style="max-width: 140px;" :title="exp.notes">
                                                    - {{ exp.notes }}
                                                </span>
                                                <span
                                                    v-if="!exp.fuel_liters && !exp.fuel_rate && !exp.fuel_odometer_km && !exp.notes">—</span>
                                            </td>
                                            <td class="pe-3 text-end text-nowrap fw-semibold text-danger">
                                                ₹ {{ formatMoney(exp.amount) }}
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                            <div v-else class="p-3 text-muted small">No expenses recorded for this trip.</div>
                        </div>
                        <div
                            class="card-footer bg-light d-flex justify-content-between align-items-center px-3 py-2 border-top small mt-auto">
                            <span class="text-muted fw-bold">EXPENSES: {{ detail.expenses?.length || 0 }}</span>
                            <div class="text-end">
                                <span class="text-muted fw-bold me-2">TOTAL EXPENSES</span>
                                <span class="fw-bold text-danger">₹ {{
                                    formatMoney(detail.trip.total_expense_amount) }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-md-6 col-lg-6 mb-4">
                    <div class="card h-100 shadow-sm charge-card">
                        <div
                            class="card-header detail-section-header rounded-top bg-success text-white d-flex justify-content-between align-items-center py-2 px-3">
                            <span><i class="fas fa-file-invoice me-2"></i> INVOICES</span>
                            <a v-if="detail.invoices && detail.invoices.length" :href="pdfUrl(detail.invoices[0].id)"
                                target="_blank" rel="noopener" class="btn btn-sm btn-primary py-0 px-2"
                                title="Print Invoice">
                                <i class="fas fa-print me-1"></i> Print
                            </a>
                        </div>
                        <div class="card-body p-0">
                            <template v-if="detail.invoices && detail.invoices.length">
                                <ul class="list-group list-group-flush">
                                    <li class="list-group-item p-3" v-for="inv in detail.invoices" :key="inv.id">
                                        <div class="d-flex justify-content-between align-items-start mb-2">
                                            <div>
                                                <RouterLink :to="{ name: 'invoices.show', params: { id: inv.id } }"
                                                    class="fw-bold fs-6 text-decoration-none">
                                                    {{ inv.invoice_number }}
                                                </RouterLink>
                                                <div class="text-muted small mt-1"><i class="bi bi-calendar3 me-1"></i>
                                                    {{ formatDate(inv.invoice_date) }}</div>
                                            </div>
                                            <div class="d-flex align-items-center gap-2">
                                                <span class="badge" :class="invoiceStatusClass(inv.payment_status)">
                                                    {{ String(inv.payment_status || '').replace(/_/g, ' ').toUpperCase()
                                                    }}
                                                </span>
                                            </div>
                                        </div>

                                        <div class="row g-2 text-sm mt-2">
                                            <div class="col-4">
                                                <div class="text-muted" style="font-size: 0.75rem;">TOTAL</div>
                                                <div class="fw-semibold">₹ {{ formatMoney(inv.total_amount) }}</div>
                                            </div>
                                            <div class="col-4">
                                                <div class="text-muted" style="font-size: 0.75rem;">PAID</div>
                                                <div class="text-success fw-semibold">₹ {{ formatMoney(inv.paid_amount
                                                    || 0) }}</div>
                                            </div>
                                            <div class="col-4">
                                                <div class="text-muted" style="font-size: 0.75rem;">BALANCE</div>
                                                <div class="text-danger fw-semibold">₹ {{ formatMoney((inv.total_amount
                                                    || 0) - (inv.paid_amount || 0)) }}</div>
                                            </div>
                                        </div>

                                        <template v-if="inv.payments && inv.payments.length > 0">
                                            <hr class="my-3 text-black-50">
                                            <div class="text-muted small fw-semibold mb-2">PAYMENT HISTORY</div>
                                            <div class="d-flex flex-column gap-2">
                                                <div v-for="payment in inv.payments" :key="payment.id"
                                                    class="d-flex justify-content-between align-items-center text-sm border p-2 rounded bg-body-tertiary">
                                                    <div>
                                                        <div class="fw-medium">{{ formatDate(payment.paid_at) }}</div>
                                                        <div class="text-muted" style="font-size: 0.75rem;">{{
                                                            String(payment.payment_method || '').replace(/_/g,
                                                                '').toUpperCase() }}</div>
                                                    </div>
                                                    <div class="text-end">
                                                        <div class="text-success fw-semibold">₹ {{
                                                            formatMoney(payment.amount) }}</div>
                                                        <div v-if="Number(payment.tds_amount) > 0 || Number(payment.deduction_amount) > 0"
                                                            class="text-muted" style="font-size: 0.75rem;">
                                                            (+ ₹{{ formatMoney((Number(payment.tds_amount) || 0) +
                                                                (Number(payment.deduction_amount) || 0)) }} ded.)
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </template>
                                    </li>
                                </ul>
                            </template>
                            <p v-else class="na-text p-3 mb-0">No invoices generated.</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <!-- Diesel & Fuel Performance if available -->
                <div v-if="detail.diesel_performance" class="col-12 mb-4">
                    <div class="card shadow-sm charge-card">
                        <div
                            class="card-header detail-section-header rounded-top bg-dark text-white d-flex justify-content-between align-items-center py-2 px-3">
                            <span><i class="fas fa-gas-pump me-2"></i> DIESEL & PERFORMANCE SUMMARY</span>
                            <span class="badge" :class="fuelPerformanceBadge.class">
                                {{ fuelPerformanceBadge.label }}
                            </span>
                        </div>
                        <div class="card-body p-3">
                            <div class="row g-2 text-center">
                                <div class="col-6 col-md-3 border-end">
                                    <div class="detail-label">Expected Running</div>
                                    <div class="detail-value fw-bold">
                                        {{ formatNum(detail.diesel_performance.expected_total_km) }} KM
                                    </div>
                                    <div v-if="detail.diesel_performance.actual_total_km" class="text-muted small mt-1">
                                        Actual: <span class="fw-semibold text-dark">{{
                                            formatNum(detail.diesel_performance.actual_total_km) }} KM</span>
                                    </div>
                                    <div v-if="detail.diesel_performance.extra_km" class="text-danger small">
                                        +{{ formatNum(detail.diesel_performance.extra_km) }} KM extra
                                    </div>
                                    <div v-else-if="detail.diesel_performance.unused_km" class="text-success small">
                                        -{{ formatNum(detail.diesel_performance.unused_km) }} KM unused
                                    </div>
                                </div>
                                <div class="col-6 col-md-3 border-end">
                                    <div class="detail-label">Planned Diesel</div>
                                    <div class="detail-value fw-bold">
                                        {{ formatNum(detail.diesel_performance.planned_diesel_liters) }} LTR
                                    </div>
                                    <div v-if="detail.diesel_performance.extra_diesel_liters"
                                        class="text-danger small mt-1">
                                        +{{ formatNum(detail.diesel_performance.extra_diesel_liters) }} Ltr excess
                                    </div>
                                    <div v-else-if="detail.diesel_performance.unused_diesel_liters"
                                        class="text-success small mt-1">
                                        {{ formatNum(detail.diesel_performance.unused_diesel_liters) }} Ltr saved
                                    </div>
                                </div>
                                <div class="col-6 col-md-3 border-end">
                                    <div class="detail-label">Target Mileage</div>
                                    <div class="detail-value fw-bold">
                                        {{ formatNum(detail.diesel_performance.planned_mileage_km_per_liter) }} KM/LTR
                                    </div>
                                    <div v-if="actualMileage" class="text-muted small mt-1">
                                        Actual: <span class="fw-semibold text-dark">{{ actualMileage }} KM/LTR</span>
                                    </div>
                                </div>
                                <div class="col-6 col-md-3">
                                    <div class="detail-label">Rate / Cost per KM</div>
                                    <div class="detail-value fw-bold">
                                        {{ detail.diesel_performance.rate_per_km ? '₹ ' +
                                            formatMoney(detail.diesel_performance.rate_per_km) : '—' }}
                                    </div>
                                    <div v-if="detail.diesel_performance.extra_diesel_cost"
                                        class="text-danger small mt-1">
                                        Extra: ₹ {{ formatMoney(detail.diesel_performance.extra_diesel_cost) }}
                                    </div>
                                    <div v-else-if="detail.diesel_performance.remaining_amount"
                                        class="text-success small mt-1">
                                        Saved: ₹ {{ formatMoney(detail.diesel_performance.remaining_amount) }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>



                <!-- Financial & Billing Summary -->
                <div class="col-12 mb-4">
                    <div class="card shadow-sm charge-card">
                        <div class="card-header detail-section-header rounded-top bg-primary text-white">
                            <i class="fas fa-chart-pie me-2"></i> SUMMARY
                        </div>
                        <div class="card-body p-3">
                            <div class="row text-center g-2">
                                <div class="col-6 col-md border-end">
                                    <div class="detail-label">Total Freight</div>
                                    <div class="detail-value fs-5 text-success">₹ {{
                                        formatMoney(detail.trip.total_freight_amount)
                                    }}</div>
                                    <div v-if="Number(detail.trip.total_custom_amount) > 0"
                                        class="text-muted small mt-1">
                                        + Additional: ₹ {{ formatMoney(detail.trip.total_custom_amount) }}
                                    </div>
                                </div>
                                <div class="col-6 col-md border-end">
                                    <div class="detail-label">Total Expenses</div>
                                    <div class="detail-value fs-5 text-danger">₹ {{
                                        formatMoney(detail.trip.total_expense_amount) }}
                                    </div>
                                    <div class="text-muted small mt-1">
                                        {{ detail.expenses?.length || 0 }} items recorded
                                    </div>
                                </div>
                                <div class="col-6 col-md border-end">
                                    <div class="detail-label">Estimated Profit</div>
                                    <div class="detail-value fs-5 fw-bold" :class="profitClass(profitAmount)">
                                        ₹ {{ formatMoney(profitAmount) }}
                                    </div>
                                    <div v-if="profitMargin" class="small mt-1" :class="profitClass(profitAmount)">
                                        Margin: {{ profitMargin }}%
                                    </div>
                                </div>
                                <div class="col-6 col-md">
                                    <div class="detail-label">Invoiced & Balance</div>
                                    <div class="detail-value fs-5 text-primary">₹ {{ formatMoney(invoicedTotal) }}</div>
                                    <div class="text-muted small mt-1">
                                        Received: <span class="text-success fw-medium">₹ {{ formatMoney(paidTotal)
                                        }}</span>
                                        <span v-if="balanceReceivable > 0" class="ms-1">· Due: <span
                                                class="text-danger fw-medium">₹
                                                {{ formatMoney(balanceReceivable) }}</span></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow-sm border-0" v-if="!detail && trips && trips.length > 0">
            <div class="card-header bg-white py-2 px-3 border-bottom d-flex justify-content-between align-items-center">
                <span class="fw-semibold text-secondary small text-uppercase">Matched Trips ({{ trips.length }})</span>
                <span class="text-muted small">Click a trip to view details</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-striped table-hover align-middle mb-0 app-index-table">
                        <thead class="table-light">
                            <tr>
                                <th>Trip No</th>
                                <th class="d-none d-sm-table-cell">Date</th>
                                <th class="d-none d-md-table-cell">Vehicle</th>
                                <th>Driver</th>
                                <th class="d-none d-lg-table-cell">Customer</th>
                                <th class="d-none d-lg-table-cell text-end">Expenses</th>
                                <th class="d-none d-sm-table-cell">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="t in trips" :key="t.id" class="cursor-pointer" title="Click to view details"
                                @click="selectTrip(t)">
                                <td class="fw-semibold">
                                    <span class="text-primary cursor-pointer" title="Search Trip"
                                        @click.stop="selectTrip(t)">
                                        {{ t.trip_number || 'N/A' }}
                                    </span>
                                </td>
                                <td class="text-nowrap d-none d-sm-table-cell">{{ formatDate(t.start_at) }}</td>
                                <td class="text-nowrap d-none d-md-table-cell text-uppercase">{{
                                    t.vehicle?.registration_number
                                    || 'N/A' }}</td>
                                <td>
                                    {{ t.substitute_driver?.name || t.driver?.name || 'N/A' }}
                                    <span v-if="t.substitute_driver?.name"
                                        class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle ms-1"
                                        style="font-size: 0.65rem;">Sub</span>
                                </td>
                                <td class="d-none d-lg-table-cell">{{ t.customer?.name || 'N/A' }}</td>
                                <td class="d-none d-lg-table-cell text-end text-nowrap fw-semibold text-danger">
                                    ₹ {{ formatMoney(t.total_expense_amount) }}
                                </td>
                                <td class="d-none d-sm-table-cell">
                                    <span class="badge" :class="statusClass(t.status)">
                                        {{ String(t.status || '').replace(/_/g, ' ').toUpperCase() }}
                                    </span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div v-else-if="!detail && localQuery && !loading && trips && trips.length === 0"
            class="text-center py-5 text-muted">
            <i class="fas fa-search fa-3x mb-3 text-light"></i>
            <p>No trips found matching "{{ localQuery }}"</p>
        </div>
    </div>
</template>

<script setup>
import { ref, watch, computed, onMounted, onBeforeUnmount, nextTick } from 'vue';
import { useRouter, useRoute, RouterLink } from 'vue-router';
import api from '../api';
import { formatDisplay, dateOnlyFromDatetimeLocal } from '../utils/datetime';
import { pdfUrl } from '../api/invoices';
import {
    tripTypeLabel,
    legLoadCategoryMeta,
    summarizeTripForm,
} from '../api/trips';

const router = useRouter();
const route = useRoute();

const localQuery = ref(route.query.q || '');
const trips = ref([]);
const detail = ref(null);
const loading = ref(false);
const searchInputRef = ref(null);

let debounceTimer = null;

const profitAmount = computed(() => {
    if (!detail.value?.trip) return 0;
    const freight = Number(detail.value.trip.total_freight_amount || 0);
    const custom = Number(detail.value.trip.total_custom_amount || 0);
    const expense = Number(detail.value.trip.total_expense_amount || 0);
    return freight + custom - expense;
});

const currentDriver = computed(() => {
    const trip = detail.value?.trip;
    return trip?.substitute_driver?.name ? trip.substitute_driver : trip?.driver;
});

const isMultiPoint = computed(() => {
    const trip = detail.value?.trip;
    if (!trip) return false;
    return String(trip.trip_type || '').toUpperCase() === 'MULTI_POINT' || Boolean(trip.pickups?.length);
});

const getDropsForPickup = (pickup) => {
    if (pickup?.drops && pickup.drops.length > 0) {
        return pickup.drops;
    }
    const pickupId = pickup?.id;
    if (!pickupId) return [];
    return (detail.value?.trip?.route_legs || []).filter(
        (leg) => leg.trip_pickup_id && String(leg.trip_pickup_id) === String(pickupId)
    );
};

const unallocatedDrops = computed(() => {
    if (!isMultiPoint.value) return [];
    const pickupIds = new Set((detail.value?.trip?.pickups || []).map((p) => String(p.id)));
    return (detail.value?.trip?.route_legs || []).filter(
        (leg) => !leg.trip_pickup_id || !pickupIds.has(String(leg.trip_pickup_id))
    );
});


const arrivalTimingBadge = computed(() => {
    const trip = detail.value?.trip;
    if (!trip?.actual_arrival_at || !trip?.end_at) return null;
    const actual = new Date(trip.actual_arrival_at).getTime();
    const planned = new Date(trip.end_at).getTime();
    const actualDay = new Date(trip.actual_arrival_at).toDateString();
    const plannedDay = new Date(trip.end_at).toDateString();
    if (actualDay === plannedDay) {
        return { label: 'On Time', class: 'bg-info text-dark' };
    }
    if (actual < planned) {
        return { label: 'Early', class: 'bg-success' };
    }
    return { label: 'Late', class: 'bg-danger' };
});

const fuelPerformanceBadge = computed(() => {
    const perf = detail.value?.diesel_performance;
    if (!perf) return { label: 'Pending', class: 'bg-secondary' };
    if (perf.performance_status === 'over') {
        return { label: 'Excess Fuel', class: 'bg-danger' };
    }
    if (perf.performance_status === 'under') {
        return { label: 'Fuel Saved', class: 'bg-success' };
    }
    return { label: 'Within Target', class: 'bg-success' };
});

const actualMileage = computed(() => {
    const perf = detail.value?.diesel_performance;
    if (perf?.actual_total_km && perf?.planned_diesel_liters) {
        const totalFuelLiters = (detail.value?.expenses || [])
            .filter((e) => ['DIESEL', 'FUEL'].includes(String(e.expense_type).toUpperCase()) && Number(e.fuel_liters) > 0)
            .reduce((sum, e) => sum + Number(e.fuel_liters), 0);
        const km = Number(perf.actual_total_km);
        if (totalFuelLiters > 0 && km > 0) {
            return (km / totalFuelLiters).toFixed(2);
        }
    }
    return null;
});

const invoicedTotal = computed(() => {
    return (detail.value?.invoices || []).reduce((sum, inv) => sum + (Number(inv.total_amount) || 0), 0);
});

const paidTotal = computed(() => {
    return (detail.value?.invoices || []).reduce((sum, inv) => sum + (Number(inv.paid_amount) || 0), 0);
});

const balanceReceivable = computed(() => {
    return Math.max(0, invoicedTotal.value - paidTotal.value);
});

const profitMargin = computed(() => {
    const freight = Number(detail.value?.trip?.total_freight_amount || 0);
    if (freight <= 0) return null;
    return ((profitAmount.value / freight) * 100).toFixed(1);
});

const performSearch = async (query) => {
    loading.value = true;
    try {
        const { data } = await api.get('/app/api/quick-search', { params: { q: query } });
        trips.value = data.trips || [];
        detail.value = data.detail || null;
    } catch (e) {
        console.error(e);
        trips.value = [];
        detail.value = null;
    } finally {
        loading.value = false;
    }
};

watch(localQuery, (val) => {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => {
        const query = val.trim();
        if (query) {
            router.replace({ name: 'quick-search', query: { q: query } });
            performSearch(query);
        } else {
            router.replace({ name: 'quick-search' });
            trips.value = [];
            detail.value = null;
        }
    }, 400);
});

watch(() => route.query.q, (val) => {
    if (val !== localQuery.value) {
        localQuery.value = val || '';
    }
});

watch(() => route.fullPath, () => {
    focusSearchInput();
});

const focusSearchInput = () => {
    nextTick(() => {
        searchInputRef.value?.focus();
        searchInputRef.value?.select?.();
    });
    setTimeout(() => {
        searchInputRef.value?.focus();
    }, 80);
};

onMounted(() => {
    if (localQuery.value) {
        performSearch(localQuery.value);
    }
    focusSearchInput();
    window.addEventListener('keydown', handleGlobalKeydown);
    window.addEventListener('focus-quick-search', focusSearchInput);
});

onBeforeUnmount(() => {
    window.removeEventListener('keydown', handleGlobalKeydown);
    window.removeEventListener('focus-quick-search', focusSearchInput);
});

const handleGlobalKeydown = (e) => {
    if (e.key === 'F2' || ((e.ctrlKey || e.metaKey) && (e.key === 'k' || e.key === 'K'))) {
        e.preventDefault();
        focusSearchInput();
    }
};

const clearSearch = () => {
    localQuery.value = '';
    searchInputRef.value?.focus();
};

const selectTrip = (t) => {
    if (!t?.trip_number) return;
    clearTimeout(debounceTimer);
    localQuery.value = t.trip_number;
    router.replace({ name: 'quick-search', query: { q: t.trip_number } });
    performSearch(t.trip_number);
};

const formatDate = (dateStr) => {
    if (!dateStr) return 'N/A';
    return formatDisplay(dateStr, 'date', 'N/A');
};

const ewayExpiryStatus = (dateStr) => {
    if (!dateStr) return null;
    const d = new Date(dateStr);
    if (isNaN(d.getTime())) return null;
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    const target = new Date(d.getFullYear(), d.getMonth(), d.getDate()).getTime();
    const now = today.getTime();
    if (target < now) {
        return { expired: true, label: 'Expired', badgeClass: 'bg-danger-subtle text-danger border border-danger-subtle' };
    }
    if (target === now) {
        return { expiringToday: true, label: 'Today', badgeClass: 'bg-warning-subtle text-warning-emphasis border border-warning-subtle' };
    }
    return null;
};

const statusClass = (status) => {
    const s = String(status || '').toLowerCase();
    return {
        'bg-warning text-dark': s === 'pending',
        'bg-info': s === 'in_progress',
        'bg-success': s === 'completed' || s === 'invoiced',
        'bg-danger': s === 'cancelled',
        'bg-secondary': !['pending', 'in_progress', 'completed', 'invoiced', 'cancelled'].includes(s),
    };
};

const invoiceStatusClass = (status) => {
    const s = String(status || '').toLowerCase();
    if (s === 'paid') return 'bg-success';
    if (s === 'partial') return 'bg-info text-dark';
    if (s === 'cancelled') return 'bg-secondary';
    return 'bg-warning text-dark';
};

const profitClass = (amount) => {
    if (amount > 0) return 'text-success';
    if (amount < 0) return 'text-danger';
    return 'text-muted';
};

const formatType = (val) => {
    if (!val) return 'N/A';
    return String(val).replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase());
};

const formatMoney = (value) => {
    if (value === null || value === undefined || value === '') return '0.00';
    const num = Number(value);
    if (isNaN(num)) return String(value);
    return num.toLocaleString('en-IN', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });
};

const formatNum = (value, maxFractionDigits = 3) => {
    if (value === null || value === undefined || value === '') return '—';
    const num = Number(value);
    if (isNaN(num)) return String(value);
    return Number.isInteger(num)
        ? String(num)
        : num.toLocaleString('en-IN', {
            minimumFractionDigits: 0,
            maximumFractionDigits: maxFractionDigits,
        });
};

const getLegDistance = (leg) => {
    if (!leg) return null;
    const legDist = Number(leg.distance_km);
    return (!isNaN(legDist) && legDist > 0) ? legDist : null;
};

const getTripTotalDistance = () => {
    const trip = detail.value?.trip;
    if (!trip) return null;
    const totalDist = Number(trip.total_distance_km);
    if (!isNaN(totalDist) && totalDist > 0) return totalDist;
    if (trip.end_meter_reading_km != null && trip.start_meter_reading_km != null) {
        const diff = Number(trip.end_meter_reading_km) - Number(trip.start_meter_reading_km);
        if (diff > 0) return diff;
    }
    if (trip.route_legs && trip.route_legs.length > 0) {
        const sum = trip.route_legs.reduce((acc, leg) => acc + (Number(leg.distance_km) || 0), 0);
        if (sum > 0) return sum;
    }
    return null;
};

const startStationLabel = computed(() => {
    const station = detail.value?.trip?.start_station;
    if (station?.label) return station.label;
    if (station?.name) return station.name;
    const firstLeg = (detail.value?.trip?.route_legs || [])[0];
    return firstLeg?.from_city || firstLeg?.from_station?.name || '—';
});

const vehicleMeta = computed(() => {
    const vehicle = detail.value?.trip?.vehicle;
    if (!vehicle) return '';
    const parts = [vehicle.make, vehicle.model].filter(Boolean);
    return parts.join(' / ');
});

const routePreview = computed(() => {
    if (!startStationLabel.value || startStationLabel.value === '—') return '';
    const points = [startStationLabel.value];
    (detail.value?.trip?.route_legs || []).forEach((leg) => {
        const label = leg.to_city || leg.to_station?.name;
        if (label && !points.includes(label)) {
            points.push(label);
        }
    });
    return points.length > 1 ? points.join(' → ') : '';
});

const arrivalTiming = computed(() => {
    const trip = detail.value?.trip;
    if (!trip?.end_at || !trip?.actual_arrival_at) return null;
    const endDate = dateOnlyFromDatetimeLocal(trip.end_at);
    const arrivalDate = dateOnlyFromDatetimeLocal(trip.actual_arrival_at);
    if (!endDate || !arrivalDate) return null;
    if (arrivalDate < endDate) return 'early';
    if (arrivalDate > endDate) return 'late';
    return 'on-time';
});

const routeSummary = computed(() => {
    const trip = detail.value?.trip;
    if (!trip) {
        return { fullLoadStops: 0, halfLoadStops: 0, emptyStops: 0, expectedMileageDisplay: '—' };
    }
    const summary = summarizeTripForm({
        route_legs: trip.route_legs || [],
        planned_mileage_km_per_liter: trip.planned_mileage_km_per_liter,
        max_load_ton: trip.max_load_ton,
        start_meter_reading_km: trip.start_meter_reading_km,
        end_meter_reading_km: trip.end_meter_reading_km,
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

const legLoadMeta = (weightTon) => {
    return legLoadCategoryMeta(weightTon, detail.value?.trip?.max_load_ton);
};

const formatOdometerReading = (value) => {
    if (value === null || value === undefined || value === '') {
        return '—';
    }
    const reading = Number(value);
    if (Number.isNaN(reading)) {
        return '—';
    }
    return Number.isInteger(reading) ? String(reading) : reading.toFixed(2).replace(/\.?0+$/, '');
};

const formatKm = (value) => {
    if (value === null || value === undefined || value === '') return '—';
    return Number(value ?? 0).toLocaleString('en-IN', { maximumFractionDigits: 2 });
};
</script>

<style scoped>
.quick-search-page {
    padding-bottom: 2rem;
}

.cursor-pointer {
    cursor: pointer;
}

.charge-card {
    border: 1px solid var(--bs-border-color);
}

.card-header::after,
.card-footer::after,
:deep(.card-header::after),
:deep(.card-footer::after) {
    display: none !important;
    content: none !important;
}

.detail-section-header {
    font-size: 0.875rem;
    font-weight: 600;
}

.detail-section-header.bg-primary {
    background: var(--brand-gradient, #0d6efd) !important;
    color: #ffffff !important;
}

.detail-label {
    font-size: 0.72rem;
    color: var(--bs-secondary-color);
    text-transform: uppercase;
    letter-spacing: 0.04em;
    margin-bottom: 0.1rem;
}

.detail-value {
    font-size: 0.85rem;
    font-weight: 500;
    color: var(--bs-body-color);
}

.na-text {
    font-size: 0.85rem;
    color: var(--bs-tertiary-color);
    font-style: italic;
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

.trip-mp-tree__pickup {
    border: 1px solid rgba(0, 0, 0, 0.08);
    border-radius: 0.5rem;
    padding: 0.75rem;
    margin-bottom: 0.65rem;
    background: #fff;
}

.trip-mp-tree__pickup-head {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin-bottom: 0.25rem;
}

.trip-mp-tree__drop {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    flex-wrap: wrap;
    margin-left: 0.75rem;
    padding: 0.35rem 0.5rem;
    border-left: 3px solid #0d6efd;
    background: rgba(13, 110, 253, 0.04);
    border-radius: 0.35rem;
    margin-bottom: 0.35rem;
    font-size: 0.875rem;
}

.trip-mp-tree__drop-code {
    font-weight: 700;
    font-size: 0.75rem;
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

.trip-detail-stop__freight.is-empty {
    color: #94a3b8;
    font-weight: 600;
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