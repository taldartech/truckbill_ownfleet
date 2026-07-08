<template>
    <div class="container mt-4">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
            <div>
                <RouterLink :to="{ name: 'tyres.index' }" class="text-decoration-none small">
                    <i class="bi bi-arrow-left"></i> Back to Tyres
                </RouterLink>
                <h3 class="mb-0 mt-1">{{ tyre?.serial_number ?? 'Tyre Detail' }}</h3>
            </div>
            <div v-if="canManage && tyre" class="d-flex flex-wrap gap-2">
                <button
                    v-if="!tyre.active_installation && tyre.status !== 'SCRAPPED'"
                    type="button"
                    class="btn btn-outline-primary btn-sm"
                    @click="openInstall"
                >
                    Install
                </button>
                <button
                    v-if="tyre.active_installation"
                    type="button"
                    class="btn btn-outline-warning btn-sm"
                    @click="openRotate"
                >
                    Rotate
                </button>
                <button
                    v-if="tyre.active_installation"
                    type="button"
                    class="btn btn-outline-success btn-sm"
                    @click="openReplace"
                >
                    Replace
                </button>
                <button
                    v-if="tyre.status !== 'SCRAPPED'"
                    type="button"
                    class="btn btn-outline-danger btn-sm"
                    @click="openDamage"
                >
                    Repair
                </button>
                <button
                    v-if="tyre.active_installation"
                    type="button"
                    class="btn btn-outline-secondary btn-sm"
                    @click="openRegrip"
                >
                    Regrip
                </button>
                <button
                    v-if="tyre.status !== 'SCRAPPED'"
                    type="button"
                    class="btn btn-outline-info btn-sm"
                    @click="openInspection"
                >
                    Inspect
                </button>
                <button
                    v-if="tyre.status !== 'SCRAPPED' && !tyre.active_installation"
                    type="button"
                    class="btn btn-outline-dark btn-sm"
                    @click="openScrap"
                >
                    Scrap / Sell
                </button>
            </div>
        </div>

        <div v-if="loading" class="text-center py-5 text-muted">Loading…</div>

        <template v-else-if="tyre">
            <div class="row g-3 mb-3">
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-header"><strong>Tyre Master</strong></div>
                        <div class="card-body row g-2 small">
                            <div class="col-md-4"><span class="text-muted">Brand</span><br />{{ tyre.brand || '—' }}</div>
                            <div class="col-md-4"><span class="text-muted">Size</span><br />{{ tyre.size || '—' }}</div>
                            <div class="col-md-4"><span class="text-muted">Type</span><br />{{ formatTyreType(tyre.tyre_type) }}</div>
                            <div class="col-md-4">
                                <span class="text-muted">Status</span><br />
                                <span class="badge" :class="tyreStatusBadgeClass(tyre.status)">
                                    {{ formatTyreStatus(tyre.status) }}
                                </span>
                            </div>
                            <div class="col-md-4">
                                <span class="text-muted">Purchase</span><br />{{ formatDate(tyre.purchase_date) }} /
                                ₹{{ tyre.purchase_cost }}
                            </div>
                            <div class="col-md-4"><span class="text-muted">Total KM used</span><br />{{ lifeSummary?.total_km_used ?? tyre.current_km }}</div>
                            <div class="col-md-4">
                                <span class="text-muted">Expected life</span><br />{{ tyre.expected_life_km ?? 40000 }} km
                            </div>
                            <div class="col-md-4">
                                <span class="text-muted">Life remaining</span><br />
                                <strong :class="lifeSummary?.is_early_wear_risk ? 'text-warning' : ''">
                                    {{ lifeSummary?.remaining_life_km ?? '—' }} km
                                </strong>
                            </div>
                            <div class="col-md-4">
                                <span class="text-muted">Spare used (times)</span><br />{{ lifeSummary?.spare_usage_count ?? 0 }}
                            </div>
                            <div class="col-md-4">
                                <span class="text-muted">Repair cost total</span><br />₹{{ lifeSummary?.total_repair_cost ?? 0 }}
                            </div>
                            <div class="col-12" v-if="lifeSummary">
                                <div class="d-flex justify-content-between small mb-1">
                                    <span>Life used {{ lifeSummary.life_used_percent }}%</span>
                                    <span>{{ lifeSummary.total_km_used }} / {{ lifeSummary.expected_life_km }} km</span>
                                </div>
                                <div class="progress" style="height: 10px">
                                    <div
                                        class="progress-bar"
                                        :class="lifeBarClass(lifeSummary.life_used_percent)"
                                        :style="{ width: Math.min(100, lifeSummary.life_used_percent) + '%' }"
                                    ></div>
                                </div>
                                <div class="small text-muted mt-2">
                                    <div>Prior usage (stock): {{ lifeSummary.stock_km ?? 0 }} km</div>
                                    <div v-if="tyre.active_installation">
                                        Current truck stint: {{ lifeSummary.active_stint_km ?? 0 }} km
                                    </div>
                                </div>
                                <div v-if="lifeSummary.is_early_wear_risk" class="alert alert-warning py-2 mt-2 mb-0 small">
                                    Tyre has most of its life left — avoid early scrap; check tread before removing.
                                </div>
                            </div>
                            <div class="col-md-4">
                                <span class="text-muted">Warranty</span><br />
                                <span class="badge" :class="warrantyBadgeClass(tyre)">{{ formatWarrantyLabel(tyre) }}</span>
                            </div>
                            <div v-if="tyre.warranty_valid_until" class="col-md-4">
                                <span class="text-muted">Valid Until</span><br />{{ formatWarrantyDate(tyre.warranty_valid_until) }}
                            </div>
                            <div v-if="tyre.warranty_km != null && tyre.warranty_km > 0" class="col-md-4">
                                <span class="text-muted">Warranty KM Limit</span><br />{{ formatWarrantyKm(tyre.warranty_km) }}
                            </div>
                            <div v-if="tyre.last_inspection_at" class="col-md-4">
                                <span class="text-muted">Last inspection</span><br />
                                {{ formatDate(tyre.last_inspection_at) }}
                                <span v-if="tyre.last_tread_depth_mm != null" class="text-muted">
                                    · {{ tyre.last_tread_depth_mm }} mm tread
                                </span>
                            </div>
                            <div v-if="tyre.notes?.trim()" class="col-12">
                                <span class="text-muted">Notes</span><br />{{ tyre.notes }}
                            </div>
                            <template v-if="tyre.status === 'SCRAPPED'">
                                <div class="col-md-4">
                                    <span class="text-muted">Scrapped on</span><br />{{ formatDate(tyre.scrapped_at) }}
                                </div>
                                <div class="col-md-4">
                                    <span class="text-muted">Scrap sale amount</span><br />
                                    {{ tyre.scrap_sale_amount != null ? `₹${Number(tyre.scrap_sale_amount).toLocaleString('en-IN')}` : '—' }}
                                </div>
                                <div v-if="tyre.scrap_notes?.trim()" class="col-12">
                                    <span class="text-muted">Scrap notes</span><br />{{ tyre.scrap_notes }}
                                </div>
                            </template>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-header"><strong>Current Position</strong></div>
                        <div class="card-body">
                            <template v-if="tyre.active_installation">
                                <RouterLink
                                    v-if="tyre.active_installation.vehicle_id"
                                    :to="{
                                        name: 'vehicles.tyres',
                                        params: { vehicleId: tyre.active_installation.vehicle_id },
                                    }"
                                    class="tyre-current-truck-link"
                                >
                                    {{ tyre.active_installation.vehicle_registration }}
                                </RouterLink>
                                <div v-else class="tyre-current-truck-link tyre-current-truck-link--static">
                                    {{ tyre.active_installation.vehicle_registration }}
                                </div>
                                <div class="text-muted">{{ tyre.active_installation.position_label }}</div>
                                <div class="small">
                                    Since {{ formatDate(tyre.active_installation.installed_at) }}
                                </div>
                                <div class="small mt-1">
                                    Meter Reading at Install: <strong>{{ tyre.active_installation.km_at_installation ?? 0 }}</strong> km
                                </div>
                                <div class="small">
                                    KM on truck: <strong>{{ lifeSummary?.active_stint_km ?? tyre.active_installation.stint_km ?? 0 }}</strong> km
                                </div>
                                <div v-if="canManage" class="d-flex flex-wrap gap-2 mt-2">
                                    <button type="button" class="btn btn-sm btn-outline-primary" @click="openUpdateKm">
                                        Update Meter Reading
                                    </button>
                                    <button type="button" class="btn btn-sm btn-warning" @click="openRemove">
                                        Remove from truck
                                    </button>
                                </div>
                            </template>
                            <span v-else class="text-muted">Not installed</span>
                        </div>
                    </div>
                </div>
            </div>

            <ul class="nav nav-tabs mb-3">
                <li class="nav-item">
                    <button
                        type="button"
                        class="nav-link"
                        :class="{ active: activeTab === 'timeline' }"
                        @click="activeTab = 'timeline'"
                    >
                        Full History
                    </button>
                </li>
                <li v-for="tab in tabs" :key="tab.key" class="nav-item">
                    <button
                        type="button"
                        class="nav-link"
                        :class="{ active: activeTab === tab.key }"
                        @click="activeTab = tab.key"
                    >
                        {{ tab.label }}
                    </button>
                </li>
            </ul>

            <div class="card">
                <div v-if="activeTab === 'timeline'" class="card-body">
                    <div class="row g-2 align-items-end mb-3">
                        <div class="col-sm-4 col-md-3">
                            <label class="form-label small mb-1" for="historyFromDate">From date</label>
                            <AppDateInput
                                input-id="historyFromDate"
                                v-model="historyFromDate"
                                size="sm"
                            />
                        </div>
                        <div class="col-sm-4 col-md-3">
                            <label class="form-label small mb-1" for="historyToDate">To date</label>
                            <AppDateInput
                                input-id="historyToDate"
                                v-model="historyToDate"
                                size="sm"
                            />
                        </div>
                        <div class="col-sm-4 col-md-3">
                            <label class="form-label small mb-1" for="historyTypeFilter">Event type</label>
                            <AppSelect
                                input-id="historyTypeFilter"
                                v-model="historyTypeFilter"
                                :options="historyTypeSelectOptions"
                                placeholder="All types"
                                search-placeholder="Search type…"
                            />
                        </div>
                        <div class="col-md-3">
                            <button
                                v-if="historyFromDate || historyToDate || historyTypeFilter"
                                type="button"
                                class="btn btn-outline-secondary btn-sm"
                                @click="clearHistoryFilters"
                            >
                                Clear filters
                            </button>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-sm table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 120px">Date</th>
                                    <th style="width: 90px">Time</th>
                                    <th style="width: 100px">Type</th>
                                    <th>Event</th>
                                    <th>Details</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-if="filteredTimeline.length === 0">
                                    <td colspan="5" class="text-center text-muted py-4">No history found.</td>
                                </tr>
                                <tr v-for="(event, i) in filteredTimeline" :key="`${event.sort_at}-${event.type}-${i}`">
                                    <td class="text-nowrap">{{ formatEventParts(event).date }}</td>
                                    <td class="text-nowrap text-muted">{{ formatEventParts(event).time }}</td>
                                    <td>
                                        <span class="badge" :class="historyTypeBadgeClass(event.type)">
                                            {{ event.type_label || formatHistoryType(event.type) }}
                                        </span>
                                    </td>
                                    <td class="fw-semibold">{{ event.title }}</td>
                                    <td class="text-muted small">{{ event.detail }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div v-if="filteredTimeline.length" class="small text-muted mt-2">
                        Showing {{ filteredTimeline.length }} of {{ timeline.length }} events · newest first
                    </div>
                </div>
                <div v-else class="card-body table-responsive">
                    <table class="table table-sm table-striped mb-0">
                        <thead v-if="activeColumns.length">
                            <tr>
                                <th v-for="col in activeColumns" :key="col">{{ col }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="activeRows.length === 0">
                                <td class="text-muted text-center">No records.</td>
                            </tr>
                            <tr v-for="(row, i) in activeRows" :key="i">
                                <td v-for="(cell, j) in row" :key="j">{{ cell }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </template>

        <TyreActionModal
            v-if="showInstall"
            title="Install Tyre"
            :form-error="formError"
            :saving="actionSaving"
            @close="closeInstall"
            @submit="requestActionConfirm('install')"
        >
            <TyreActionContextStrip
                class="mb-3"
                :tyre-serial="tyre?.serial_number"
                :truck-number="installContextTruckNumber"
                :vehicle-id="actionForm.vehicle_id"
                :position="installContextPosition"
                :show-truck="!!actionForm.vehicle_id"
            />
            <div class="mb-2">
                <label class="form-label" for="installTyre_vehicle">Truck</label>
                <VehicleSelect
                    input-id="installTyre_vehicle"
                    v-model="actionForm.vehicle_id"
                    :vehicles="vehicles"
                    placeholder="Select truck"
                />
            </div>
            <div class="mb-2">
                <label class="form-label">Position</label>
                <AppSelect
                    v-model="actionForm.position_code"
                    :options="installPositionSelectOptions"
                    :placeholder="loadingVehiclePositions ? 'Loading positions…' : 'Select truck first'"
                    search-placeholder="Search position…"
                    :disabled="!actionForm.vehicle_id || loadingVehiclePositions"
                />
                <div v-if="actionForm.vehicle_id && !loadingVehiclePositions" class="form-text">
                    ● Free position · ○ Already has a tyre
                </div>
            </div>
            <div v-if="lifeSummary" class="alert alert-info small py-2">
                <strong>{{ installLifeLabel }}</strong> — {{ lifeSummary.stock_km ?? 0 }} km used
                ({{ lifeSummary.life_used_percent }}% of {{ lifeSummary.expected_life_km }} km life)
            </div>
            <div class="mb-2">
                <label class="form-label">Installation Date</label>
                <AppDateInput v-model="actionForm.installed_at" required />
            </div>
            <div class="mb-2">
                <label class="form-label">Meter Reading at Install (km)</label>
                <input
                    v-model.number="actionForm.km_at_installation"
                    v-no-scroll-change
                    type="number"
                    min="0"
                    step="0.01"
                    class="form-control"
                    required
                />
                <div class="form-text">Truck meter reading when this tyre is fitted. Stint KM is calculated from this reading.</div>
            </div>
        </TyreActionModal>

        <TyreActionModal
            v-if="showReplace"
            title="Replace Tyre on Truck"
            :form-error="formError"
            :saving="actionSaving"
            @close="closeReplace"
            @submit="requestActionConfirm('replace')"
        >
            <TyreActionContextStrip
                v-if="tyre?.active_installation"
                class="mb-3"
                :tyre-serial="tyre.serial_number"
                :truck-number="tyre.active_installation.vehicle_registration"
                :vehicle-id="activeVehicleId"
                :position="tyre.active_installation.position_label"
            />
            <div class="mb-2">
                <label class="form-label">New tyre (from stock)</label>
                <AppSelect
                    v-model="actionForm.new_tyre_id"
                    :options="stockTyreSelectOptions"
                    placeholder="Remove only (no new tyre)"
                    search-placeholder="Search tyre…"
                />
            </div>
            <div class="mb-2">
                <label class="form-label">Replace date</label>
                <AppDateInput v-model="actionForm.replaced_at" required />
            </div>
            <div class="mb-2">
                <label class="form-label">Current Meter Reading (km) *</label>
                <input
                    v-model.number="actionForm.odometer_km"
                    v-no-scroll-change
                    type="number"
                    min="0"
                    step="0.01"
                    class="form-control"
                    required
                    @input="loadReplacePreview"
                />
            </div>
            <div v-if="calculatedStintKm != null" class="alert alert-secondary small py-2 mb-2">
                KM on truck: <strong>{{ calculatedStintKm }} km</strong>
                <span v-if="lifeSummary">
                    · Life used about <strong>{{ previewLifePercent(calculatedStintKm) }}%</strong>
                </span>
            </div>
            <div class="mb-2">
                <label class="form-label">Reason</label>
                <input v-model="actionForm.replace_reason" type="text" class="form-control" placeholder="Puncture, burst, rotation…" />
            </div>
            <div v-if="replacePreview" class="alert py-2 small" :class="replacePreview.level === 'warning' ? 'alert-warning' : 'alert-info'">
                {{ replacePreview.message }}
            </div>
        </TyreActionModal>

        <TyreActionModal
            v-if="showRotate"
            title="Rotate Tyre"
            :form-error="formError"
            :saving="actionSaving"
            @close="closeRotate"
            @submit="requestActionConfirm('rotate')"
        >
            <TyreActionContextStrip
                v-if="tyre?.active_installation"
                class="mb-3"
                :tyre-serial="tyre.serial_number"
                :truck-number="tyre.active_installation.vehicle_registration"
                :vehicle-id="activeVehicleId"
                :position="tyre.active_installation.position_label"
            />
            <div class="mb-2">
                <label class="form-label">Previous Position</label>
                <input :value="tyre?.active_installation?.position_label" class="form-control" disabled />
            </div>
            <div class="mb-2">
                <label class="form-label">New Position</label>
                <AppSelect
                    v-model="actionForm.new_position"
                    :options="rotatePositionSelectOptions"
                    :placeholder="
                        rotatePositionOptions.length === 0 ? 'No other positions on this truck' : 'Select position'
                    "
                    search-placeholder="Search position…"
                    :disabled="loadingVehiclePositions || rotatePositionOptions.length === 0"
                    :invalid="hasFieldError(fieldErrors, 'new_position')"
                    :error-message="fieldErrorMessage(fieldErrors, 'new_position')"
                />
            </div>
            <div v-if="selectedRotateTarget?.occupied" class="alert alert-warning small py-2 mb-2">
                Will swap with <strong>{{ selectedRotateTarget.tyre_serial }}</strong> currently at
                {{ selectedRotateTarget.label }}.
            </div>
            <div class="row g-2 mb-2">
                <div class="col-md-6">
                    <label class="form-label">Current Meter Reading (km) *</label>
                    <input
                        v-model.number="actionForm.odometer_km"
                        v-no-scroll-change
                        type="number"
                        min="0"
                        step="0.01"
                        class="form-control"
                        required
                    />
                </div>
                <div class="col-md-6">
                    <label class="form-label">Rotation Date</label>
                    <AppDateInput v-model="actionForm.rotation_date" required />
                </div>
            </div>
            <div v-if="calculatedStintKm != null" class="alert alert-secondary small py-2 mb-2">
                KM on truck: <strong>{{ calculatedStintKm }} km</strong>
            </div>
            <div class="row g-2 mb-2">
                <div class="col-md-6">
                    <label class="form-label">Rotation Cost (₹) *</label>
                    <input
                        v-model.number="actionForm.rotation_cost"
                        v-no-scroll-change
                        type="number"
                        min="0"
                        step="0.01"
                        class="form-control"
                        :class="{ 'is-invalid': hasFieldError(fieldErrors, 'cost') }"
                        required
                    />
                    <div v-if="fieldErrorMessage(fieldErrors, 'cost')" class="invalid-feedback d-block">
                        {{ fieldErrorMessage(fieldErrors, 'cost') }}
                    </div>
                </div>
            </div>
            <div class="mb-2">
                <label class="form-label">Reason</label>
                <input v-model="actionForm.reason" type="text" class="form-control" placeholder="Steer swap, wear balancing…" />
            </div>
        </TyreActionModal>

        <TyreActionModal
            v-if="showRemove"
            title="Remove Tyre from Truck"
            :form-error="formError"
            :saving="actionSaving"
            @close="closeRemove"
            @submit="requestActionConfirm('remove')"
        >
            <TyreActionContextStrip
                v-if="tyre?.active_installation"
                class="mb-3"
                :tyre-serial="tyre.serial_number"
                :truck-number="tyre.active_installation.vehicle_registration"
                :vehicle-id="activeVehicleId"
                :position="tyre.active_installation.position_label"
            />
            <div class="mb-2">
                <label class="form-label">Remove date</label>
                <AppDateInput v-model="actionForm.removed_at" required />
            </div>
            <div class="mb-2">
                <label class="form-label">Current Meter Reading (km) *</label>
                <input
                    v-model.number="actionForm.odometer_km"
                    v-no-scroll-change
                    type="number"
                    min="0"
                    step="0.01"
                    class="form-control"
                    required
                    @input="loadRemovePreview"
                />
            </div>
            <div v-if="calculatedStintKm != null" class="alert alert-secondary small py-2 mb-2">
                KM on truck: <strong>{{ calculatedStintKm }} km</strong>
            </div>
            <div v-if="removePreview" class="alert py-2 small" :class="removePreview.level === 'warning' ? 'alert-warning' : 'alert-info'">
                {{ removePreview.message }}
            </div>
        </TyreActionModal>

        <TyreActionModal
            v-if="showUpdateKm"
            title="Update from Meter Reading"
            :form-error="formError"
            :saving="actionSaving"
            @close="closeUpdateKm"
            @submit="requestActionConfirm('updateKm')"
        >
            <TyreActionContextStrip
                v-if="tyre?.active_installation"
                class="mb-3"
                :tyre-serial="tyre.serial_number"
                :truck-number="tyre.active_installation.vehicle_registration"
                :vehicle-id="activeVehicleId"
                :position="tyre.active_installation.position_label"
            />
            <div class="mb-2">
                <label class="form-label">Current Meter Reading (km) *</label>
                <input
                    v-model.number="actionForm.odometer_km"
                    v-no-scroll-change
                    type="number"
                    min="0"
                    step="0.01"
                    class="form-control"
                    required
                />
            </div>
            <div v-if="calculatedStintKm != null" class="alert alert-info small py-2 mb-0">
                KM on truck: <strong>{{ calculatedStintKm }} km</strong>
                <span v-if="lifeSummary">
                    · Life used about <strong>{{ previewLifePercent(calculatedStintKm) }}%</strong>
                </span>
            </div>
        </TyreActionModal>

        <TyreActionModal
            v-if="showScrap"
            title="Scrap / Sell Tyre"
            :form-error="formError"
            :saving="actionSaving"
            @close="closeScrap"
            @submit="requestActionConfirm('scrap')"
        >
            <TyreActionContextStrip
                class="mb-3"
                :tyre-serial="tyre?.serial_number"
                :show-truck="false"
            />
            <div v-if="lifeSummary" class="alert alert-secondary small py-2">
                <div><strong>Total KM used:</strong> {{ lifeSummary.total_km_used }} km</div>
                <div><strong>Life used:</strong> {{ lifeSummary.life_used_percent }}%</div>
                <div><strong>Remaining:</strong> {{ lifeSummary.remaining_life_km }} km</div>
            </div>
            <div class="mb-2">
                <label class="form-label">Scrap date</label>
                <AppDateInput v-model="actionForm.scrapped_at" required />
            </div>
            <div class="mb-2">
                <label class="form-label">Sale amount (recovery)</label>
                <input
                    v-model.number="actionForm.scrap_sale_amount"
                    v-no-scroll-change
                    type="number"
                    min="0"
                    step="0.01"
                    class="form-control"
                    placeholder="Amount received from scrap dealer"
                />
            </div>
            <div class="mb-2">
                <label class="form-label">Notes</label>
                <textarea
                    v-model="actionForm.scrap_notes"
                    class="form-control"
                    rows="2"
                    placeholder="Buyer name, condition, etc."
                />
            </div>
        </TyreActionModal>

        <TyreActionModal
            v-if="showInspection"
            title="Record Tyre Inspection"
            :form-error="formError"
            :saving="actionSaving"
            @close="closeInspection"
            @submit="requestActionConfirm('inspection')"
        >
            <TyreActionContextStrip
                v-if="tyre?.active_installation"
                class="mb-3"
                :tyre-serial="tyre.serial_number"
                :truck-number="tyre.active_installation.vehicle_registration"
                :vehicle-id="activeVehicleId"
                :position="tyre.active_installation.position_label"
            />
            <TyreActionContextStrip
                v-else
                class="mb-3"
                :tyre-serial="tyre?.serial_number"
                :show-truck="false"
            />
            <div class="mb-2">
                <label class="form-label">Inspection date</label>
                <AppDateInput v-model="actionForm.inspected_at" required />
            </div>
            <div class="row g-2 mb-2">
                <div class="col-md-6">
                    <label class="form-label">Tread depth (mm)</label>
                    <input
                        v-model.number="actionForm.tread_depth_mm"
                        v-no-scroll-change
                        type="number"
                        min="0"
                        max="50"
                        step="0.1"
                        class="form-control"
                    />
                </div>
                <div class="col-md-6">
                    <label class="form-label">Pressure (PSI)</label>
                    <input
                        v-model.number="actionForm.pressure_psi"
                        v-no-scroll-change
                        type="number"
                        min="0"
                        max="300"
                        step="0.1"
                        class="form-control"
                    />
                </div>
            </div>
            <div class="mb-2">
                <label class="form-label">Result</label>
                <AppSelect
                    v-model="actionForm.inspection_result"
                    :options="inspectionResultOptions"
                    placeholder="Select result"
                />
            </div>
            <div class="mb-2">
                <label class="form-label">Next due date</label>
                <AppDateInput v-model="actionForm.next_due_at" />
            </div>
            <div class="mb-2">
                <label class="form-label">Notes</label>
                <textarea v-model="actionForm.inspection_notes" class="form-control" rows="2" />
            </div>
        </TyreActionModal>

        <TyreActionModal
            v-if="showDamage"
            :title="damageModalMode === 'recapped' ? 'Record Regrip' : 'Record Repair'"
            :form-error="formError"
            :saving="actionSaving"
            @close="closeDamage"
            @submit="requestActionConfirm('damage')"
        >
            <TyreActionContextStrip
                v-if="tyre?.active_installation"
                class="mb-3"
                :tyre-serial="tyre.serial_number"
                :truck-number="tyre.active_installation.vehicle_registration"
                :vehicle-id="activeVehicleId"
                :position="tyre.active_installation.position_label"
            />
            <div v-if="damageModalMode === 'repair'" class="mb-2">
                <label class="form-label">Type</label>
                <AppSelect
                    v-model="actionForm.damage_type"
                    :options="repairTypeSelectOptions"
                    placeholder="Select type"
                    search-placeholder="Search type…"
                />
            </div>
            <div v-if="damageModalMode === 'recapped'" class="mb-2">
                <label class="form-label">New expected life (km) *</label>
                <input
                    v-model.number="actionForm.expected_life_km"
                    type="number"
                    min="1000"
                    step="1"
                    class="form-control"
                    :class="{ 'is-invalid': hasFieldError(fieldErrors, 'expected_life_km') }"
                    placeholder="e.g. 55000"
                    required
                    @wheel.prevent
                />
                <div class="form-text">
                    Current expected life: {{ tyre?.expected_life_km ?? 40000 }} km.
                    Enter the new expected life after regrip — you can increase or decrease this value.
                </div>
                <div v-if="hasFieldError(fieldErrors, 'expected_life_km')" class="invalid-feedback d-block">
                    {{ fieldErrorMessage(fieldErrors, 'expected_life_km') }}
                </div>
            </div>
            <div class="mb-2">
                <label class="form-label">Date</label>
                <AppDateInput
                    v-model="actionForm.occurred_at"
                    required
                    :invalid="hasFieldError(fieldErrors, 'occurred_at')"
                />
                <div v-if="hasFieldError(fieldErrors, 'occurred_at')" class="invalid-feedback d-block">
                    {{ fieldErrorMessage(fieldErrors, 'occurred_at') }}
                </div>
            </div>
            <div class="mb-2">
                <label class="form-label">Current Meter Reading (km) *</label>
                <input
                    v-model.number="actionForm.odometer_km"
                    v-no-scroll-change
                    type="number"
                    min="0"
                    step="0.01"
                    class="form-control"
                    :class="{ 'is-invalid': hasFieldError(fieldErrors, 'odometer_km') }"
                    required
                />
                <div v-if="hasFieldError(fieldErrors, 'odometer_km')" class="invalid-feedback d-block">
                    {{ fieldErrorMessage(fieldErrors, 'odometer_km') }}
                </div>
            </div>
            <div class="mb-2">
                <label class="form-label">Description</label>
                <input
                    v-model="actionForm.damage_description"
                    type="text"
                    class="form-control"
                    :placeholder="damageModalMode === 'recapped' ? 'Regrip notes…' : 'Puncture, burst…'"
                />
            </div>
            <div class="mb-2">
                <label class="form-label">{{ damageModalMode === 'recapped' ? 'Regrip Cost *' : 'Repair Cost *' }}</label>
                <input
                    v-model.number="actionForm.repair_cost"
                    v-no-scroll-change
                    type="number"
                    min="0"
                    step="0.01"
                    class="form-control"
                    :class="{ 'is-invalid': hasFieldError(fieldErrors, 'repair_cost') }"
                    required
                />
                <div v-if="hasFieldError(fieldErrors, 'repair_cost')" class="invalid-feedback d-block">
                    {{ fieldErrorMessage(fieldErrors, 'repair_cost') }}
                </div>
            </div>
        </TyreActionModal>

        <TyreActionConfirmModal
            :show="showActionConfirm"
            :title="actionConfirmSummary?.title ?? 'Confirm Action'"
            :message="actionConfirmSummary?.message ?? ''"
            :loading="actionSaving"
            :confirm-label="actionConfirmSummary?.confirmLabel ?? 'Yes, Confirm'"
            :confirm-variant="actionConfirmVariant"
            :icon-tone="actionConfirmSummary?.iconTone ?? 'primary'"
            :tyre-serial="actionConfirmSummary?.context?.tyreSerial ?? ''"
            :truck-number="actionConfirmSummary?.context?.truckNumber ?? ''"
            :vehicle-id="confirmContextVehicleId"
            :position="actionConfirmSummary?.context?.position ?? ''"
            :show-truck="actionConfirmSummary?.context?.truckNumber !== '—'"
            :items="actionConfirmSummary?.items ?? []"
            @cancel="cancelActionConfirm"
            @confirm="confirmTyreAction"
        />
    </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue';
import { useRoute } from 'vue-router';
import { listAllVehicles } from '../../api/vehicles';
import {
    getTyre,
    getVehicleTyrePositions,
    installTyre,
    listTyres,
    previewTyreRemoval,
    recordTyreDamage,
    recordTyreInspection,
    removeTyreInstallation,
    updateTyreInstallationStintKm,
    replaceTyre,
    rotateTyre,
    scrapTyre,
} from '../../api/tyres';
import { useApiFormErrors } from '../../composables/useApiFormErrors';
import { usePermissions } from '../../composables/usePermissions';
import TyreActionModal from '../../components/tyres/TyreActionModal.vue';
import TyreActionConfirmModal from '../../components/tyres/TyreActionConfirmModal.vue';
import TyreActionContextStrip from '../../components/tyres/TyreActionContextStrip.vue';
import AppSelect from '../../components/ui/AppSelect.vue';
import VehicleSelect from '../../components/ui/VehicleSelect.vue';
import { fieldErrorMessage, hasFieldError } from '../../utils/formErrors';
import { calculateTyreStintKm, resolveVehicleOdometer } from '../../utils/tyreStint';
import { formatDate, formatEventParts, toDateInputValue } from '../../utils/datetime';
import {
    formatDamageType,
    formatTyreStatus,
    formatTyreType,
    formatWarrantyLabel,
    tyreStatusBadgeClass,
    warrantyBadgeClass,
} from '../../utils/tyreDisplay';
import { buildTyreActionConfirmSummary } from '../../utils/tyre-action-confirm-info';

function formatWarrantyDate(value) {
    return formatDate(value);
}

function formatWarrantyKm(value) {
    return `${Number(value).toLocaleString('en-IN')} km`;
}

const route = useRoute();
const { canInCompany } = usePermissions();
const canManage = computed(() => canInCompany('tyres.manage'));
const { fieldErrors, formError, clearErrors, applyApiError } = useApiFormErrors();
const actionSaving = ref(false);

const tyre = ref(null);
const lifeSummary = ref(null);
const timeline = ref([]);
const historyFromDate = ref('');
const historyToDate = ref('');
const historyTypeFilter = ref('');
const loading = ref(true);
const activeTab = ref('timeline');
const vehicles = ref([]);
const stockTyres = ref([]);
const showInstall = ref(false);
const showRotate = ref(false);
const showDamage = ref(false);
const damageModalMode = ref('repair');
const showReplace = ref(false);
const showRemove = ref(false);
const showUpdateKm = ref(false);
const showScrap = ref(false);
const showInspection = ref(false);
const showActionConfirm = ref(false);
const pendingAction = ref(null);
const replacePreview = ref(null);
const removePreview = ref(null);

const vehiclePositionSlots = ref([]);
const loadingVehiclePositions = ref(false);

const installPositionOptions = computed(() =>
    vehiclePositionSlots.value.map((slot) => ({
        value: slot.position,
        label: slot.position_label,
        occupied: !slot.is_empty,
    })),
);

const installPositionSelectOptions = computed(() =>
    installPositionOptions.value.map((option) => ({
        value: option.value,
        label: `${option.occupied ? '○' : '●'} ${option.label}${option.occupied ? ' (occupied)' : ' (free)'}`,
        disabled: option.occupied,
    })),
);

const stockTyreSelectOptions = computed(() => [
    { value: '', label: 'Remove only (no new tyre)' },
    ...stockTyres.value.map((tyreOption) => ({
        value: tyreOption.id,
        label: `${tyreOption.serial_number} — ${tyreOption.brand}`,
    })),
]);

const rotatePositionOptions = computed(() =>
    vehiclePositionSlots.value
        .filter((slot) => slot.position !== tyre.value?.active_installation?.position)
        .map((slot) => ({
            value: slot.position,
            label: slot.position_label,
            occupied: !slot.is_empty,
            tyre_serial: slot.tyre?.serial_number ?? null,
        })),
);

const rotatePositionSelectOptions = computed(() =>
    rotatePositionOptions.value.map((option) => ({
        value: option.value,
        label: rotateOptionLabel(option),
    })),
);

const repairTypeSelectOptions = [
    { value: 'PUNCTURE', label: 'Puncture' },
    { value: 'BURST', label: 'Burst' },
    { value: 'CUT', label: 'Cut' },
    { value: 'SIDEWALL', label: 'Sidewall' },
    { value: 'OTHER', label: 'Other' },
];

const inspectionResultOptions = [
    { value: 'PASS', label: 'Pass' },
    { value: 'ATTENTION', label: 'Needs attention' },
    { value: 'FAIL', label: 'Fail' },
];

const selectedRotateTarget = computed(() =>
    rotatePositionOptions.value.find((option) => option.value === actionForm.new_position) ?? null,
);

const installLifeLabel = computed(() => {
    const stockKm = lifeSummary.value?.stock_km ?? 0;

    return stockKm > 0 ? 'Used tyre' : 'New tyre';
});

const installContextTruckNumber = computed(() => {
    if (!actionForm.vehicle_id) {
        return '—';
    }

    const vehicle = vehicles.value.find((row) => row.id === actionForm.vehicle_id);

    return vehicle?.registration_number ?? '—';
});

const installContextPosition = computed(() => {
    if (!actionForm.position_code) {
        return '';
    }

    return installPositionSelectOptions.value.find((option) => option.value === actionForm.position_code)?.label ?? '';
});

const actionConfirmSummary = computed(() => {
    if (!pendingAction.value) {
        return null;
    }

    return buildTyreActionConfirmSummary({
        action: pendingAction.value,
        tyre: tyre.value,
        actionForm,
        damageModalMode: damageModalMode.value,
        vehicles: vehicles.value,
        stockTyres: stockTyres.value,
        rotatePositionOptions: rotatePositionSelectOptions.value,
        selectedRotateTarget: selectedRotateTarget.value,
        calculatedStintKm: calculatedStintKm.value,
        installPositionSelectOptions: installPositionSelectOptions.value,
    });
});

const actionConfirmVariant = computed(() => {
    const tone = actionConfirmSummary.value?.iconTone;

    if (tone === 'danger') {
        return 'danger';
    }

    if (tone === 'warning') {
        return 'warning';
    }

    return 'success';
});

const activeVehicleId = computed(() => tyre.value?.active_installation?.vehicle_id ?? '');

const confirmContextVehicleId = computed(() => {
    if (pendingAction.value === 'install') {
        return actionForm.vehicle_id;
    }

    return activeVehicleId.value;
});

const activeKmAtInstallation = computed(() => {
    const active = tyre.value?.active_installation;

    return active?.km_at_installation ?? 0;
});

const calculatedStintKm = computed(() => {
    const odometer = actionForm.odometer_km;

    if (odometer === '' || odometer == null || Number.isNaN(Number(odometer))) {
        return null;
    }

    return calculateTyreStintKm(activeKmAtInstallation.value, odometer);
});

function prefillActiveVehicleOdometer() {
    const active = tyre.value?.active_installation;
    if (!active) {
        return;
    }

    actionForm.odometer_km =
        active.vehicle_odometer_km ??
        resolveVehicleOdometer(vehicles.value, active.vehicle_id);
}

function prefillInstallOdometer(vehicleId) {
    actionForm.km_at_installation = resolveVehicleOdometer(vehicles.value, vehicleId);
}

function todayIsoDate() {
    return toDateInputValue();
}

function createActionFormDefaults() {
    return {
        vehicle_id: '',
        position_code: '',
        installed_at: todayIsoDate(),
        new_position: 'FRONT_RIGHT',
        rotation_date: todayIsoDate(),
        rotation_cost: 0,
        reason: '',
        damage_type: 'PUNCTURE',
        occurred_at: todayIsoDate(),
        repair_cost: null,
        damage_description: '',
        expected_life_km: null,
        km_at_installation: 0,
        odometer_km: 0,
        stint_km: 0,
        removed_at: todayIsoDate(),
        new_tyre_id: '',
        replaced_at: todayIsoDate(),
        replace_reason: '',
        scrapped_at: todayIsoDate(),
        scrap_sale_amount: null,
        scrap_notes: '',
        inspected_at: todayIsoDate(),
        tread_depth_mm: null,
        pressure_psi: null,
        inspection_result: 'PASS',
        next_due_at: '',
        inspection_notes: '',
    };
}

const actionForm = reactive(createActionFormDefaults());

function resetActionForm() {
    Object.assign(actionForm, createActionFormDefaults());
}

const tabs = [
    { key: 'installations', label: 'Installation History' },
    { key: 'rotations', label: 'Rotations' },
    { key: 'damages', label: 'Repair' },
    { key: 'expenses', label: 'Expenses' },
];

const historyTypeOptions = [
    { value: 'installed', label: 'Install' },
    { value: 'removed', label: 'Remove' },
    { value: 'rotation', label: 'Rotation' },
    { value: 'damage', label: 'Repair' },
    { value: 'replacement', label: 'Replace' },
    { value: 'scrapped', label: 'Scrap' },
    { value: 'expense', label: 'Expense' },
];

const historyTypeSelectOptions = computed(() => [
    { value: '', label: 'All types' },
    ...historyTypeOptions,
]);

const filteredTimeline = computed(() => {
    return timeline.value.filter((event) => {
        if (historyFromDate.value && (event.date ?? '') < historyFromDate.value) {
            return false;
        }
        if (historyToDate.value && (event.date ?? '') > historyToDate.value) {
            return false;
        }
        if (historyTypeFilter.value && event.type !== historyTypeFilter.value) {
            return false;
        }

        return true;
    });
});

function clearHistoryFilters() {
    historyFromDate.value = '';
    historyToDate.value = '';
    historyTypeFilter.value = '';
}

function formatHistoryType(type) {
    if (!type) {
        return '—';
    }

    return type.replace(/_/g, ' ').replace(/\b\w/g, (char) => char.toUpperCase());
}

function historyTypeBadgeClass(type) {
    const map = {
        installed: 'bg-success',
        removed: 'bg-secondary',
        rotation: 'bg-warning text-dark',
        damage: 'bg-danger',
        replacement: 'bg-primary',
        scrapped: 'bg-dark',
        expense: 'bg-light text-dark border',
    };

    return map[type] ?? 'bg-secondary';
}

function sortByDateField(items, field) {
    return [...items].sort((a, b) => String(b[field] ?? '').localeCompare(String(a[field] ?? '')));
}

const activeRows = computed(() => {
    if (!tyre.value) {
        return [];
    }
    if (activeTab.value === 'installations') {
        return sortByDateField(tyre.value.installations ?? [], 'installed_at').map((r) => [
            r.vehicle_registration,
            r.position_label,
            formatDate(r.installed_at),
            formatDate(r.removed_at),
            r.running_km ?? '—',
            r.removal_recommendation ?? '—',
        ]);
    }
    if (activeTab.value === 'rotations') {
        return sortByDateField(tyre.value.rotations ?? [], 'rotation_date').map((r) => [
            r.vehicle_registration,
            formatDate(r.rotation_date),
            r.previous_position_label ?? r.previous_position,
            r.new_position_label ?? r.new_position,
            r.cost != null ? `₹${r.cost}` : '—',
            r.reason ?? '—',
        ]);
    }
    if (activeTab.value === 'damages') {
        return sortByDateField(tyre.value.damages ?? [], 'occurred_at').map((r) => [
            formatDate(r.occurred_at),
            formatDamageType(r.damage_type),
            r.vehicle_registration ?? '—',
            r.position_label ?? '—',
            r.km_at_occurrence ?? '—',
            r.repair_cost ?? '—',
            r.is_repaired ? 'Yes' : 'No',
        ]);
    }
    return sortByDateField(tyre.value.expenses ?? [], 'expense_date').map((r) => [
        r.expense_type,
        formatDate(r.expense_date),
        r.amount,
        r.notes ?? '—',
    ]);
});

const activeColumns = computed(() => {
    const map = {
        installations: ['Truck', 'Position', 'Installed', 'Removed', 'Ran KM', 'Advice'],
        rotations: ['Truck', 'Date', 'From', 'To', 'Cost', 'Reason'],
        damages: ['Date', 'Type', 'Truck', 'Position', 'KM', 'Repair', 'Fixed'],
        expenses: ['Type', 'Date', 'Amount', 'Notes'],
    };
    return map[activeTab.value] ?? [];
});

function rotateOptionLabel(option) {
    if (!option.occupied) {
        return option.label;
    }

    return `${option.label} (swap with ${option.tyre_serial ?? 'other tyre'})`;
}

function closeActionModal(modalRef) {
    modalRef.value = false;
    clearErrors();
    resetActionForm();
}

function openInstall() {
    clearErrors();
    resetActionForm();
    showInstall.value = true;
}

function openRotate() {
    clearErrors();
    resetActionForm();
    prefillActiveVehicleOdometer();
    showRotate.value = true;
}

function openDamage() {
    clearErrors();
    resetActionForm();
    damageModalMode.value = 'repair';
    prefillActiveVehicleOdometer();
    showDamage.value = true;
}

function openRegrip() {
    clearErrors();
    resetActionForm();
    damageModalMode.value = 'recapped';
    actionForm.damage_type = 'RECAPPED';
    prefillActiveVehicleOdometer();
    prefillRegripExpectedLife();
    showDamage.value = true;
}

function prefillRegripExpectedLife() {
    actionForm.expected_life_km = tyre.value?.expected_life_km ?? lifeSummary.value?.expected_life_km ?? 40000;
}

function openReplace() {
    clearErrors();
    resetActionForm();
    prefillActiveVehicleOdometer();
    showReplace.value = true;
}

function openRemove() {
    clearErrors();
    resetActionForm();
    prefillActiveVehicleOdometer();
    removePreview.value = null;
    showRemove.value = true;
    loadRemovePreview();
}

function openUpdateKm() {
    clearErrors();
    resetActionForm();
    prefillActiveVehicleOdometer();
    showUpdateKm.value = true;
}

function openScrap() {
    clearErrors();
    resetActionForm();
    showScrap.value = true;
}

function openInspection() {
    clearErrors();
    resetActionForm();
    actionForm.tread_depth_mm = tyre.value?.last_tread_depth_mm ?? null;
    showInspection.value = true;
}

function closeInstall() {
    closeActionModal(showInstall);
}

function closeRotate() {
    closeActionModal(showRotate);
}

function closeDamage() {
    closeActionModal(showDamage);
}

function closeReplace() {
    closeActionModal(showReplace);
}

function closeRemove() {
    closeActionModal(showRemove);
    removePreview.value = null;
}

function closeUpdateKm() {
    closeActionModal(showUpdateKm);
}

function closeScrap() {
    closeActionModal(showScrap);
}

function closeInspection() {
    closeActionModal(showInspection);
}

function finishTyreAction(modalRef) {
    modalRef.value = false;
    clearErrors();
    resetActionForm();
}

function mapTyreActionApiError(error) {
    const data = error?.response?.data;

    if (!data?.errors) {
        return error;
    }

    const errors = { ...data.errors };

    if (errors.position_code && !errors.new_position) {
        errors.new_position = errors.position_code;
        delete errors.position_code;
    }

    return {
        ...error,
        response: {
            ...error.response,
            data: {
                ...data,
                errors,
            },
        },
    };
}

function lifeBarClass(percent) {
    if (percent >= 85) {
        return 'bg-danger';
    }
    if (percent >= 60) {
        return 'bg-warning';
    }
    return 'bg-success';
}

function previewLifePercent(stintKm) {
    const expected = lifeSummary.value?.expected_life_km ?? tyre.value?.expected_life_km ?? 40000;
    const stockKm = lifeSummary.value?.stock_km ?? 0;
    const total = stockKm + (Number(stintKm) || 0);

    if (expected <= 0) {
        return 0;
    }

    return Math.min(100, Math.round((total / expected) * 1000) / 10);
}

async function loadTyre() {
    loading.value = true;
    try {
        const payload = await getTyre(route.params.id);
        tyre.value = payload.tyre;
        lifeSummary.value = payload.life_summary;
        timeline.value = payload.timeline;
        if (tyre.value?.active_installation?.vehicle_id) {
            await loadVehiclePositions(tyre.value.active_installation.vehicle_id);
            const firstTarget = rotatePositionOptions.value[0];
            actionForm.new_position = firstTarget?.value ?? '';
        }
    } finally {
        loading.value = false;
    }
}

async function loadReplacePreview() {
    const instId = tyre.value?.active_installation?.installation_id ?? tyre.value?.active_installation?.id;
    if (!instId || calculatedStintKm.value == null) {
        replacePreview.value = null;
        return;
    }
    const res = await previewTyreRemoval(instId, { odometerKm: actionForm.odometer_km });
    replacePreview.value = res.data ?? null;
}

async function loadRemovePreview() {
    const instId = tyre.value?.active_installation?.installation_id ?? tyre.value?.active_installation?.id;
    if (!instId || calculatedStintKm.value == null) {
        removePreview.value = null;
        return;
    }
    const res = await previewTyreRemoval(instId, { odometerKm: actionForm.odometer_km });
    removePreview.value = res.data ?? null;
}

async function loadVehiclePositions(vehicleId) {
    if (!vehicleId) {
        vehiclePositionSlots.value = [];
        actionForm.position_code = '';
        return;
    }

    loadingVehiclePositions.value = true;
    try {
        vehiclePositionSlots.value = await getVehicleTyrePositions(vehicleId);
        const firstEmpty = vehiclePositionSlots.value.find((slot) => slot.is_empty);
        if (firstEmpty && !actionForm.position_code) {
            actionForm.position_code = firstEmpty.position;
        }
    } finally {
        loadingVehiclePositions.value = false;
    }
}

watch(
    () => actionForm.damage_type,
    (damageType) => {
        if (damageType === 'RECAPPED') {
            prefillRegripExpectedLife();
        }
    },
);

watch(
    () => actionForm.vehicle_id,
    (vehicleId) => {
        actionForm.position_code = '';
        prefillInstallOdometer(vehicleId);
        loadVehiclePositions(vehicleId);
    },
);

watch(showRotate, async (open) => {
    if (!open) {
        return;
    }

    clearErrors();
    prefillActiveVehicleOdometer();

    if (tyre.value?.active_installation?.vehicle_id) {
        await loadVehiclePositions(tyre.value.active_installation.vehicle_id);
        const firstTarget = rotatePositionOptions.value[0];
        actionForm.new_position = firstTarget?.value ?? '';
    }
});

async function loadStockTyres() {
    const res = await listTyres({ perPage: 100, status: 'IN_STOCK' });
    stockTyres.value = res.data.filter((t) => t.id !== tyre.value?.id);
}

function requestActionConfirm(action) {
    if (!validateActionBeforeConfirm(action)) {
        return;
    }

    pendingAction.value = action;
    showActionConfirm.value = true;
}

function cancelActionConfirm() {
    if (actionSaving.value) {
        return;
    }

    showActionConfirm.value = false;
    pendingAction.value = null;
}

async function confirmTyreAction() {
    if (!pendingAction.value) {
        return;
    }

    actionSaving.value = true;

    try {
        await executeTyreAction(pendingAction.value);
        showActionConfirm.value = false;
        pendingAction.value = null;
    } catch (error) {
        showActionConfirm.value = false;
        applyApiError(mapTyreActionApiError(error), 'Unable to save. Please check the form and try again.');
    } finally {
        actionSaving.value = false;
    }
}

function validateActionBeforeConfirm(action) {
    if (action === 'damage') {
        return validateDamageForm();
    }

    clearErrors();
    const errors = {};

    if (action === 'install') {
        if (!actionForm.vehicle_id) {
            errors.vehicle_id = ['The truck field is required.'];
        }

        if (!actionForm.position_code) {
            errors.position_code = ['The position field is required.'];
        }
    }

    if (action === 'rotate') {
        if (!actionForm.new_position) {
            errors.new_position = ['Select a new position.'];
        }

        if (actionForm.odometer_km === '' || actionForm.odometer_km == null || !Number.isFinite(Number(actionForm.odometer_km))) {
            errors.odometer_km = ['Current meter reading is required.'];
        }

        if (actionForm.rotation_cost === '' || actionForm.rotation_cost == null || Number(actionForm.rotation_cost) < 0) {
            errors.cost = ['Rotation cost is required.'];
        }
    }

    if (['replace', 'remove', 'updateKm'].includes(action)) {
        const odometer = Number(actionForm.odometer_km);

        if (actionForm.odometer_km === '' || actionForm.odometer_km == null || !Number.isFinite(odometer) || odometer < 0) {
            errors.odometer_km = ['Current meter reading is required.'];
        } else {
            const kmAtInstall = tyre.value?.active_installation?.km_at_installation;

            if (kmAtInstall != null && odometer < kmAtInstall) {
                errors.odometer_km = [`Meter reading cannot be less than install reading (${kmAtInstall} km).`];
            }
        }
    }

    if (Object.keys(errors).length > 0) {
        fieldErrors.value = errors;
        formError.value = 'Please fix the highlighted fields and try again.';

        return false;
    }

    return true;
}

async function executeTyreAction(action) {
    switch (action) {
        case 'install':
            await installTyre(tyre.value.id, {
                vehicle_id: actionForm.vehicle_id,
                position_code: actionForm.position_code,
                installed_at: actionForm.installed_at,
                km_at_installation: actionForm.km_at_installation,
            });
            finishTyreAction(showInstall);
            await loadTyre();
            break;
        case 'rotate':
            await rotateTyre(tyre.value.id, {
                rotation_date: actionForm.rotation_date,
                previous_position: tyre.value.active_installation.position,
                new_position: actionForm.new_position,
                cost: actionForm.rotation_cost,
                odometer_km: actionForm.odometer_km,
                reason: actionForm.reason || null,
            });
            finishTyreAction(showRotate);
            activeTab.value = 'rotations';
            await loadTyre();
            break;
        case 'damage': {
            const payload = {
                damage_type: actionForm.damage_type,
                occurred_at: actionForm.occurred_at,
                repair_cost: actionForm.repair_cost || null,
                description: actionForm.damage_description || null,
                odometer_km: actionForm.odometer_km,
            };

            if (actionForm.damage_type === 'RECAPPED') {
                payload.expected_life_km = actionForm.expected_life_km;
            }

            if (tyre.value?.active_installation?.vehicle_id) {
                payload.vehicle_id = tyre.value.active_installation.vehicle_id;
                payload.position_code = tyre.value.active_installation.position;
            }

            await recordTyreDamage(tyre.value.id, payload);
            finishTyreAction(showDamage);
            activeTab.value = 'damages';
            await loadTyre();
            break;
        }
        case 'replace':
            await replaceTyre({
                vehicle_id: tyre.value.active_installation.vehicle_id,
                position_code: tyre.value.active_installation.position,
                old_tyre_id: tyre.value.id,
                new_tyre_id: actionForm.new_tyre_id || null,
                replaced_at: actionForm.replaced_at,
                odometer_km: actionForm.odometer_km,
                reason: actionForm.replace_reason || null,
            });
            finishTyreAction(showReplace);
            await loadTyre();
            break;
        case 'remove': {
            const instId = tyre.value?.active_installation?.installation_id ?? tyre.value?.active_installation?.id;
            if (!instId) {
                return;
            }

            await removeTyreInstallation(instId, {
                removed_at: actionForm.removed_at,
                odometer_km: actionForm.odometer_km,
            });
            finishTyreAction(showRemove);
            removePreview.value = null;
            await loadTyre();
            break;
        }
        case 'updateKm': {
            const instId = tyre.value?.active_installation?.installation_id ?? tyre.value?.active_installation?.id;
            if (!instId) {
                return;
            }

            await updateTyreInstallationStintKm(instId, { odometer_km: actionForm.odometer_km });
            finishTyreAction(showUpdateKm);
            await loadTyre();
            break;
        }
        case 'scrap':
            await scrapTyre(tyre.value.id, {
                scrapped_at: actionForm.scrapped_at,
                scrap_sale_amount:
                    actionForm.scrap_sale_amount === '' || actionForm.scrap_sale_amount == null
                        ? null
                        : actionForm.scrap_sale_amount,
                scrap_notes: actionForm.scrap_notes || null,
            });
            finishTyreAction(showScrap);
            activeTab.value = 'timeline';
            await loadTyre();
            break;
        case 'inspection':
            await recordTyreInspection(tyre.value.id, {
                vehicle_id: tyre.value?.active_installation?.vehicle_id ?? null,
                inspected_at: actionForm.inspected_at,
                tread_depth_mm: actionForm.tread_depth_mm === '' ? null : actionForm.tread_depth_mm,
                pressure_psi: actionForm.pressure_psi === '' ? null : actionForm.pressure_psi,
                result: actionForm.inspection_result || 'PASS',
                next_due_at: actionForm.next_due_at || null,
                notes: actionForm.inspection_notes || null,
            });
            finishTyreAction(showInspection);
            activeTab.value = 'timeline';
            await loadTyre();
            break;
        default:
            break;
    }
}

function validateDamageForm() {
    const errors = {};

    if (!actionForm.occurred_at) {
        errors.occurred_at = ['Date is required.'];
    }

    const odometer = Number(actionForm.odometer_km);

    if (actionForm.odometer_km === '' || actionForm.odometer_km == null || !Number.isFinite(odometer) || odometer < 0) {
        errors.odometer_km = ['Current meter reading is required.'];
    } else {
        const kmAtInstall = tyre.value?.active_installation?.km_at_installation;

        if (kmAtInstall != null && odometer < kmAtInstall) {
            errors.odometer_km = [`Meter reading cannot be less than install reading (${kmAtInstall} km).`];
        }
    }

    if (actionForm.damage_type === 'RECAPPED') {
        const expectedLife = Number(actionForm.expected_life_km);

        if (
            actionForm.expected_life_km === '' ||
            actionForm.expected_life_km == null ||
            !Number.isFinite(expectedLife) ||
            expectedLife < 1000
        ) {
            errors.expected_life_km = ['New expected life must be at least 1,000 km.'];
        }
    }

    const repairCost = actionForm.repair_cost;
    const isRegrip = actionForm.damage_type === 'RECAPPED';

    if (
        repairCost === '' ||
        repairCost == null ||
        !Number.isFinite(Number(repairCost)) ||
        Number(repairCost) < 0
    ) {
        errors.repair_cost = [isRegrip ? 'Regrip cost is required.' : 'Repair cost is required.'];
    }

    if (Object.keys(errors).length > 0) {
        fieldErrors.value = errors;
        formError.value = 'Please fix the highlighted fields and try again.';

        return false;
    }

    return true;
}

watch(showReplace, async (open) => {
    if (open) {
        await loadStockTyres();
        await loadReplacePreview();
    }
});

onMounted(async () => {
    vehicles.value = await listAllVehicles();
    await loadTyre();
});
</script>

<style scoped>
.tyre-current-truck-link {
    display: inline-block;
    font-size: 1.125rem;
    font-weight: 700;
    color: var(--brand-accent-blue, #2d8cff);
    text-decoration: none;
    margin-bottom: 0.15rem;
}

.tyre-current-truck-link:hover {
    text-decoration: underline;
}

.tyre-current-truck-link--static {
    cursor: default;
}
</style>
