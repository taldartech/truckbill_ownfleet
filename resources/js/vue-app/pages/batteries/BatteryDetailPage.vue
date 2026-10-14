<template>
    <div class="container mt-4 app-detail-page">
        <div v-if="!battery" class="mb-3">
            <RouterLink
                :to="{ name: 'batteries.index' }"
                class="btn btn-outline-secondary btn-sm"
                title="Back to Batteries"
            >
                <i class="fas fa-arrow-left me-1"></i> Batteries
            </RouterLink>
        </div>

        <div v-if="loading && !battery" class="card">
            <div class="card-body text-center py-5 text-muted">
                <span class="spinner-border spinner-border-sm me-2" role="status"></span>
                Loading battery details…
            </div>
        </div>

        <div v-else-if="loadError && !battery" class="card">
            <div class="card-body text-center py-5">
                <i class="fas fa-triangle-exclamation text-danger fs-3 mb-2"></i>
                <div class="mb-3">{{ loadError }}</div>
                <button type="button" class="btn btn-outline-primary btn-sm" @click="fetchBattery">Retry</button>
            </div>
        </div>

        <template v-else-if="battery">
            <div class="card mb-3">
                <div class="card-body battery-hero">
                    <RouterLink
                        :to="{ name: 'batteries.index' }"
                        class="btn btn-outline-secondary btn-sm battery-hero__back"
                        title="Back to Batteries"
                        aria-label="Back to Batteries"
                    >
                        <i class="fas fa-arrow-left"></i>
                    </RouterLink>
                    <!-- <span class="battery-hero__icon" aria-hidden="true">
                        <i class="fas fa-car-battery"></i>
                    </span> -->
                    <div style="min-width: 0;">
                        <div class="d-flex flex-wrap align-items-center gap-2">
                            <h1 class="battery-hero__title">{{ battery.serial_number }}</h1>
                            <span class="badge" :class="batteryStatusBadgeClass(battery.status)">
                                {{ batteryStatusLabel(battery.status) }}
                            </span>
                            <span class="badge" :class="batteryWarrantyMeta(battery.warranty_status).badgeClass">
                                {{ batteryWarrantyMeta(battery.warranty_status).label }}
                            </span>
                        </div>
                        <div class="battery-hero__meta">
                            <span><i class="fas fa-tag me-1"></i>{{ battery.brand }}<template v-if="battery.model"> · {{ battery.model }}</template></span>
                            <span><i class="fas fa-bolt me-1"></i>{{ battery.voltage }} · {{ Number(battery.capacity_ah) }} Ah</span>
                            <span><i class="fas fa-flask me-1"></i>{{ battery.battery_type_label || batteryTypeLabel(battery.battery_type) }}</span>
                            <span v-if="battery.active_installation">
                                <i class="fas fa-truck me-1"></i>{{ battery.active_installation.vehicle_registration }}
                            </span>
                        </div>
                    </div>
                    <div class="battery-hero__actions">
                        <button
                            v-if="canEdit && !isScrapped"
                            type="button"
                            class="btn btn-outline-info btn-sm"
                            @click="showInspectionModal = true"
                        >
                            <i class="fas fa-stethoscope me-1"></i> Health Check
                        </button>
                        <button
                            v-if="canEdit && !isScrapped"
                            type="button"
                            class="btn btn-outline-primary btn-sm"
                            @click="showEditModal = true"
                        >
                            <i class="fas fa-pencil-alt me-1"></i> Edit
                        </button>
                        <button
                            v-if="canCreate && isInstallable"
                            type="button"
                            class="btn btn-success btn-sm"
                            @click="showInstallModal = true"
                        >
                            <i class="fas fa-plus-circle me-1"></i> Install on Truck
                        </button>
                        <button
                            v-if="canEdit && !battery.active_installation && !isScrapped"
                            type="button"
                            class="btn btn-outline-danger btn-sm"
                            @click="showScrapModal = true"
                        >
                            <i class="fas fa-recycle me-1"></i> Scrap
                        </button>
                    </div>
                </div>
            </div>

            <div class="card mb-3">
                <div class="row g-0 battery-kpi-strip">
                    <div class="col-6 col-xl-3 battery-kpi-strip__item">
                        <span class="battery-kpi-card__icon bg-primary-subtle text-primary"><i class="fas fa-road"></i></span>
                        <div class="battery-kpi-card__body">
                            <div class="battery-stat__label">Distance Served</div>
                            <div class="battery-kpi-strip__value text-primary">{{ formatKm(lifeSummary?.total_running_km) }}</div>
                            <div class="battery-kpi-strip__sub">
                                {{ lifeSummary?.installations_count ?? 0 }}
                                {{ (lifeSummary?.installations_count ?? 0) === 1 ? 'installation' : 'installations' }}
                            </div>
                        </div>
                    </div>

                    <div class="col-6 col-xl-3 battery-kpi-strip__item">
                        <span class="battery-kpi-card__icon bg-success-subtle text-success"><i class="fas fa-hourglass-half"></i></span>
                        <div class="battery-kpi-card__body">
                            <div class="d-flex justify-content-between">
                                <span class="battery-stat__label">Service Life Used</span>
                                <span class="small fw-semibold">{{ lifeUsedPercent }}%</span>
                            </div>
                            <div class="battery-kpi-strip__value">
                                {{ lifeSummary?.age_months ?? 0 }}
                                <span class="battery-kpi-strip__unit">/ {{ battery.expected_life_months }} months</span>
                            </div>
                            <div class="battery-meter mt-1" role="progressbar" :aria-valuenow="lifeUsedPercent" aria-valuemin="0" aria-valuemax="100">
                                <div class="battery-meter__fill" :class="lifeMeterClass" :style="{ width: `${Math.max(lifeUsedPercent, 2)}%` }"></div>
                            </div>
                        </div>
                    </div>

                    <div class="col-6 col-xl-3 battery-kpi-strip__item">
                        <span class="battery-kpi-card__icon bg-warning-subtle text-warning-emphasis"><i class="fas fa-shield-halved"></i></span>
                        <div class="battery-kpi-card__body">
                            <div class="battery-stat__label">Warranty</div>
                            <template v-if="battery.warranty_valid_until">
                                <div
                                    v-if="warrantyDaysLeft >= 0"
                                    class="battery-kpi-strip__value"
                                    :class="battery.warranty_status === 'expiring' ? 'text-warning-emphasis' : 'text-success'"
                                >
                                    {{ warrantyDaysLeft }}
                                    <span class="battery-kpi-strip__unit">{{ warrantyDaysLeft === 1 ? 'day' : 'days' }} left</span>
                                </div>
                                <div v-else class="battery-kpi-strip__value text-secondary">
                                    Expired
                                    <span class="battery-kpi-strip__unit">{{ Math.abs(warrantyDaysLeft) }} days ago</span>
                                </div>
                                <div class="battery-kpi-strip__sub">Till {{ formatDate(battery.warranty_valid_until) }}</div>
                            </template>
                            <div v-else class="battery-kpi-strip__sub mt-1">No warranty recorded</div>
                        </div>
                    </div>

                    <div class="col-6 col-xl-3 battery-kpi-strip__item">
                        <span class="battery-kpi-card__icon bg-info-subtle text-info-emphasis"><i class="fas fa-heart-pulse"></i></span>
                        <div class="battery-kpi-card__body">
                            <div class="battery-stat__label">Last Health Check</div>
                            <template v-if="lastInspection">
                                <div class="d-flex flex-wrap align-items-center gap-2 mt-1">
                                    <span class="badge" :class="conditionBadgeClass(lastInspection.condition)">
                                        {{ conditionLabel(lastInspection.condition) }}
                                    </span>
                                    <span v-if="lastInspection.voltage_reading != null" class="small fw-semibold">
                                        {{ Number(lastInspection.voltage_reading).toFixed(2) }} V
                                    </span>
                                </div>
                                <div class="battery-kpi-strip__sub">{{ formatDate(lastInspection.inspected_at) }}</div>
                            </template>
                            <template v-else>
                                <div class="battery-kpi-strip__sub mt-1">Not checked yet</div>
                                <button
                                    v-if="canEdit && !isScrapped"
                                    type="button"
                                    class="btn btn-link btn-sm p-0 small"
                                    @click="showInspectionModal = true"
                                >
                                    Log first check
                                </button>
                            </template>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-3">
                <div class="col-lg-8">
                    <div class="card mb-3">
                        <div class="card-header battery-section-header">
                            <h2 class="battery-section-header__title"><i class="fas fa-link"></i><strong>Current Assignment</strong></h2>
                            <div class="battery-section-header__end">
                                <span class="battery-header-pill">
                                    <i :class="battery.active_installation ? 'fas fa-truck' : 'fas fa-box'"></i>
                                    {{ battery.active_installation ? 'On Truck' : 'Not Fitted' }}
                                </span>
                            </div>
                        </div>
                        <div class="card-body py-2">
                            <div v-if="battery.active_installation" class="battery-assignment">
                                <div class="battery-truck-tile battery-assignment__truck">
                                    <span class="battery-truck-tile__icon"><i class="fas fa-truck"></i></span>
                                    <div style="min-width: 0;">
                                        <RouterLink
                                            :to="{ name: 'trucks.show', params: { vehicleId: battery.active_installation.vehicle_id } }"
                                            class="fw-bold text-decoration-none"
                                        >
                                            {{ battery.active_installation.vehicle_registration }}
                                        </RouterLink>
                                        <div class="text-muted small text-nowrap">
                                            Slot <strong>{{ battery.active_installation.slot_name || '—' }}</strong>
                                            · {{ formatDate(battery.active_installation.installed_at) }}
                                        </div>
                                    </div>
                                </div>
                                <div class="battery-assignment__metric">
                                    <div class="battery-stat__label">At Fitting</div>
                                    <div class="fw-semibold">{{ formatKm(battery.active_installation.km_at_installation) }}</div>
                                </div>
                                <div class="battery-assignment__metric">
                                    <div class="battery-stat__label">Odometer Now</div>
                                    <div class="fw-semibold">{{ formatKm(battery.active_installation.vehicle_odometer_km) }}</div>
                                </div>
                                <div class="battery-assignment__metric">
                                    <div class="battery-stat__label">Current Stint</div>
                                    <div class="fw-bold text-primary">{{ formatKm(battery.active_installation.stint_km) }}</div>
                                </div>
                                <div v-if="canCreate" class="battery-assignment__actions">
                                    <button type="button" class="btn btn-primary btn-sm" @click="showReplaceModal = true">
                                        <i class="fas fa-arrows-rotate me-1"></i> Replace
                                    </button>
                                    <button type="button" class="btn btn-outline-warning btn-sm" @click="showRemoveModal = true">
                                        <i class="fas fa-plug-circle-minus me-1"></i> Remove
                                    </button>
                                </div>
                            </div>

                            <div v-else-if="isScrapped" class="battery-truck-tile align-items-start">
                                <span class="battery-truck-tile__icon bg-secondary-subtle text-secondary"><i class="fas fa-recycle"></i></span>
                                <div class="flex-grow-1">
                                    <div class="fw-semibold">Retired on {{ formatDate(battery.scrapped_at) }}</div>
                                    <div class="row g-2 mt-1 small">
                                        <div class="col-sm-4">
                                            <span class="text-muted">Scrap recovery</span>
                                            <div class="fw-semibold text-success">{{ formatMoney(battery.scrap_sale_amount ?? 0) }}</div>
                                        </div>
                                        <div class="col-sm-8">
                                            <span class="text-muted">Scrap notes</span>
                                            <div>{{ battery.scrap_notes || '—' }}</div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div v-else class="d-flex flex-wrap gap-3 align-items-center justify-content-between">
                                <div class="battery-truck-tile">
                                    <span class="battery-truck-tile__icon bg-light"><i :class="[unassignedState.icon, unassignedState.iconClass]"></i></span>
                                    <div>
                                        <div class="fw-semibold">{{ unassignedState.title }}</div>
                                        <div class="text-muted small">{{ unassignedState.subtitle }}</div>
                                    </div>
                                </div>
                                <button
                                    v-if="canCreate && isInstallable"
                                    type="button"
                                    class="btn btn-outline-success btn-sm"
                                    @click="showInstallModal = true"
                                >
                                    <i class="fas fa-plus-circle me-1"></i> Install on Truck
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="card mb-3">
                        <div class="card-header battery-section-header">
                            <h2 class="battery-section-header__title"><i class="fas fa-clock-rotate-left"></i><strong>Installation History</strong></h2>
                            <div class="battery-section-header__end">
                                <span class="battery-header-pill">{{ battery.installations?.length || 0 }}</span>
                            </div>
                        </div>
                        <div class="card-body p-0">
                            <div v-if="!battery.installations?.length" class="battery-empty">
                                <i class="fas fa-truck"></i>
                                This battery has not been fitted to any truck yet.
                            </div>
                            <div v-else class="table-responsive">
                                <table class="table table-hover align-middle mb-0 small">
                                    <thead class="table-light">
                                        <tr>
                                            <th class="ps-3">Truck / Slot</th>
                                            <th>Fitted</th>
                                            <th>Removed</th>
                                            <th class="text-end">Odometer In → Out</th>
                                            <th class="text-end">Stint</th>
                                            <th class="pe-3">Reason</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-for="inst in battery.installations" :key="inst.id">
                                            <td class="ps-3">
                                                <RouterLink
                                                    v-if="inst.vehicle_id"
                                                    :to="{ name: 'trucks.show', params: { vehicleId: inst.vehicle_id } }"
                                                    class="fw-semibold text-decoration-none"
                                                >
                                                    {{ inst.vehicle_registration || 'Truck' }}
                                                </RouterLink>
                                                <div class="text-muted">{{ inst.slot_name || '—' }}</div>
                                            </td>
                                            <td class="text-nowrap">{{ formatDate(inst.installed_at) }}</td>
                                            <td class="text-nowrap">
                                                <span v-if="inst.removed_at">{{ formatDate(inst.removed_at) }}</span>
                                                <span v-else class="badge bg-success-subtle text-success border border-success-subtle">
                                                    <i class="fas fa-circle me-1" style="font-size: 0.45rem; vertical-align: middle;"></i>Current
                                                </span>
                                            </td>
                                            <td class="text-end text-nowrap">
                                                {{ Math.round(inst.km_at_installation || 0).toLocaleString('en-IN') }}
                                                <i class="fas fa-arrow-right text-muted mx-1" style="font-size: 0.65rem;"></i>
                                                {{ inst.km_at_removal != null ? Math.round(inst.km_at_removal).toLocaleString('en-IN') : 'now' }}
                                            </td>
                                            <td class="text-end fw-semibold text-primary text-nowrap">
                                                {{ inst.stint_km != null ? formatKm(inst.stint_km) : '—' }}
                                            </td>
                                            <td class="pe-3">
                                                <span v-if="inst.removal_reason" class="badge bg-light text-secondary border">
                                                    {{ inst.removal_reason_label || batteryRemovalReasonLabel(inst.removal_reason) }}
                                                </span>
                                                <span v-else class="text-muted">—</span>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <div class="card mb-3">
                        <div class="card-header battery-section-header">
                            <h2 class="battery-section-header__title"><i class="fas fa-arrows-rotate"></i><strong>Replacement &amp; Removal Log</strong></h2>
                            <div class="battery-section-header__end">
                                <span class="battery-header-pill">{{ replacements.length }}</span>
                            </div>
                        </div>
                        <div v-if="!replacements.length" class="card-body p-0">
                            <div class="battery-empty">
                                <i class="fas fa-arrows-rotate"></i>
                                No replacement or removal events yet.
                            </div>
                        </div>
                        <div v-else class="card-body">
                            <ul class="battery-timeline">
                                <li v-for="event in replacements" :key="event.id" class="battery-timeline__item">
                                    <span class="battery-timeline__dot" :class="replacementDotClass(event)"></span>
                                    <div class="d-flex flex-wrap justify-content-between gap-2">
                                        <div class="fw-semibold">{{ replacementTitle(event) }}</div>
                                        <div class="text-muted small text-nowrap">{{ formatDate(event.replaced_at) }}</div>
                                    </div>
                                    <div class="small text-muted">
                                        <RouterLink
                                            v-if="event.vehicle_id"
                                            :to="{ name: 'trucks.show', params: { vehicleId: event.vehicle_id } }"
                                            class="text-decoration-none"
                                        >{{ event.vehicle_registration || 'Truck' }}</RouterLink>
                                        <span v-if="event.slot_name"> · Slot {{ event.slot_name }}</span>
                                        · {{ formatKm(event.odometer_km) }}
                                        · {{ event.reason_label || batteryRemovalReasonLabel(event.reason) }}
                                    </div>
                                    <div v-if="replacementCounterpart(event)" class="small mt-1">
                                        {{ replacementCounterpart(event).label }}
                                        <RouterLink
                                            :to="{ name: 'batteries.show', params: { id: replacementCounterpart(event).battery.id } }"
                                            class="fw-semibold text-decoration-none"
                                        >{{ replacementCounterpart(event).battery.serial_number }}</RouterLink>
                                    </div>
                                    <div v-if="event.notes" class="small text-muted fst-italic mt-1">{{ event.notes }}</div>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="card mb-3">
                        <div class="card-header battery-section-header">
                            <h2 class="battery-section-header__title"><i class="fas fa-list-ul"></i><strong>Specifications</strong></h2>
                        </div>
                        <div class="card-body small py-2">
                            <dl class="battery-spec-list">
                                <div class="battery-spec-list__row">
                                    <dt>Brand</dt>
                                    <dd>{{ battery.brand }}</dd>
                                </div>
                                <div class="battery-spec-list__row">
                                    <dt>Model</dt>
                                    <dd>{{ battery.model || '—' }}</dd>
                                </div>
                                <div class="battery-spec-list__row">
                                    <dt>Type</dt>
                                    <dd>{{ battery.battery_type_label || batteryTypeLabel(battery.battery_type) }}</dd>
                                </div>
                                <div class="battery-spec-list__row">
                                    <dt>Rating</dt>
                                    <dd>{{ battery.voltage }} · {{ Number(battery.capacity_ah) }} Ah</dd>
                                </div>
                                <div class="battery-spec-list__row">
                                    <dt>Purchase cost</dt>
                                    <dd>{{ battery.purchase_cost ? formatMoney(battery.purchase_cost) : '—' }}</dd>
                                </div>
                                <div class="battery-spec-list__row">
                                    <dt>Purchase date</dt>
                                    <dd>{{ formatDate(battery.purchase_date) }}</dd>
                                </div>
                                <div class="battery-spec-list__row">
                                    <dt>Supplier</dt>
                                    <dd>{{ battery.supplier_name || '—' }}</dd>
                                </div>
                                <div class="battery-spec-list__row">
                                    <dt>Warranty</dt>
                                    <dd>{{ battery.warranty_months ? `${battery.warranty_months} months` : '—' }}</dd>
                                </div>
                                <div class="battery-spec-list__row">
                                    <dt>Expected life</dt>
                                    <dd>{{ battery.expected_life_months }} months</dd>
                                </div>
                                <div v-if="costPerThousandKm !== null" class="battery-spec-list__row">
                                    <dt>Cost / 1,000 KM</dt>
                                    <dd>{{ formatMoney(costPerThousandKm) }}</dd>
                                </div>
                            </dl>
                            <div v-if="battery.notes" class="mt-2 mb-1 p-2 bg-light rounded border text-muted">
                                <i class="fas fa-note-sticky me-1"></i>{{ battery.notes }}
                            </div>
                        </div>
                    </div>

                    <div class="card mb-3">
                        <div class="card-header battery-section-header">
                            <h2 class="battery-section-header__title"><i class="fas fa-heart-pulse"></i><strong>Health Checks</strong></h2>
                            <div class="battery-section-header__end">
                                <span v-if="battery.inspections?.length" class="battery-header-pill">{{ battery.inspections.length }}</span>
                                <button
                                    v-if="canEdit && !isScrapped"
                                    type="button"
                                    class="battery-header-btn"
                                    @click="showInspectionModal = true"
                                >
                                    <i class="fas fa-plus"></i> Log
                                </button>
                            </div>
                        </div>
                        <div class="card-body p-0">
                            <div v-if="!battery.inspections?.length" class="battery-empty">
                                <i class="fas fa-heart-pulse"></i>
                                Log voltage and condition checks to spot a weak battery before it fails on the road.
                            </div>
                            <ul v-else class="list-group list-group-flush small">
                                <li
                                    v-for="check in battery.inspections"
                                    :key="check.id"
                                    class="list-group-item battery-check"
                                    :class="`battery-check--${check.condition}`"
                                >
                                    <div class="d-flex justify-content-between align-items-center gap-2">
                                        <span class="badge" :class="conditionBadgeClass(check.condition)">
                                            {{ check.condition_label || conditionLabel(check.condition) }}
                                        </span>
                                        <span class="text-muted text-nowrap">{{ formatDate(check.inspected_at) }}</span>
                                    </div>
                                    <div
                                        v-if="check.voltage_reading != null || check.specific_gravity != null || check.vehicle_registration"
                                        class="d-flex flex-wrap gap-3 mt-2"
                                    >
                                        <span v-if="check.voltage_reading != null">
                                            <i class="fas fa-bolt text-warning me-1"></i><strong>{{ Number(check.voltage_reading).toFixed(2) }}</strong> V
                                        </span>
                                        <span v-if="check.specific_gravity != null">
                                            <span class="text-muted">SG</span> <strong>{{ Number(check.specific_gravity).toFixed(3) }}</strong>
                                        </span>
                                        <span v-if="check.vehicle_registration" class="text-muted">
                                            <i class="fas fa-truck me-1"></i>{{ check.vehicle_registration }}
                                        </span>
                                    </div>
                                    <div v-if="check.action_taken" class="text-muted mt-1">
                                        <i class="fas fa-wrench me-1"></i>{{ humanize(check.action_taken) }}
                                    </div>
                                    <div v-if="check.notes" class="text-muted fst-italic mt-1">{{ check.notes }}</div>
                                </li>
                            </ul>
                        </div>
                    </div>

                    <div class="card mb-3">
                        <div class="card-header battery-section-header">
                            <h2 class="battery-section-header__title"><i class="fas fa-circle-info"></i><strong>Record Info</strong></h2>
                        </div>
                        <div class="card-body small py-2">
                            <dl class="battery-spec-list">
                                <div class="battery-spec-list__row">
                                    <dt>Created</dt>
                                    <dd>{{ formatDateTime(battery.created_at) }}</dd>
                                </div>
                                <div class="battery-spec-list__row">
                                    <dt>Last updated</dt>
                                    <dd>{{ formatDateTime(battery.updated_at) }}</dd>
                                </div>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>
        </template>

        <BatteryFormModal
            :show="showEditModal"
            :battery="battery"
            @close="showEditModal = false"
            @saved="onActionDone('Battery updated.')"
        />

        <BatteryInstallModal
            :show="showInstallModal"
            :battery="battery"
            @close="showInstallModal = false"
            @installed="onActionDone('Battery installed on truck.')"
        />

        <BatteryReplaceModal
            :show="showReplaceModal"
            :battery="battery"
            @close="showReplaceModal = false"
            @replaced="onActionDone('Battery replaced successfully.')"
        />

        <BatteryRemoveModal
            :show="showRemoveModal"
            :installation="battery?.active_installation"
            :battery-serial="battery?.serial_number"
            @close="showRemoveModal = false"
            @removed="onActionDone('Battery removed from truck and moved to standby.')"
        />

        <BatteryInspectionModal
            :show="showInspectionModal"
            :battery="battery"
            @close="showInspectionModal = false"
            @saved="onActionDone('Health check saved.')"
        />

        <BatteryScrapModal
            :show="showScrapModal"
            :battery="battery"
            @close="showScrapModal = false"
            @scrapped="onActionDone('Battery marked as scrapped.')"
        />
    </div>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import { useRoute } from 'vue-router';
import { usePermissions } from '../../composables/usePermissions';
import { useAppToast } from '../../composables/useAppToast';
import { getBattery } from '../../api/batteries';
import { formatDate, formatDateTime } from '../../utils/datetime';
import { formatMoney } from '../../utils/formatMoney';
import {
    batteryConditionOption,
    batteryRemovalReasonLabel,
    batteryStatusBadgeClass,
    batteryStatusLabel,
    batteryTypeLabel,
    batteryWarrantyMeta,
    formatKm,
} from '../../constants/battery';

import BatteryFormModal from '../../components/batteries/BatteryFormModal.vue';
import BatteryInstallModal from '../../components/batteries/BatteryInstallModal.vue';
import BatteryReplaceModal from '../../components/batteries/BatteryReplaceModal.vue';
import BatteryRemoveModal from '../../components/batteries/BatteryRemoveModal.vue';
import BatteryInspectionModal from '../../components/batteries/BatteryInspectionModal.vue';
import BatteryScrapModal from '../../components/batteries/BatteryScrapModal.vue';
import '../../components/batteries/battery.css';

const route = useRoute();
const toast = useAppToast();
const { canCreateInCompany, canEditInCompany } = usePermissions();

const canCreate = computed(() => canCreateInCompany('batteries'));
const canEdit = computed(() => canEditInCompany('batteries'));

const loading = ref(false);
const loadError = ref('');
const battery = ref(null);
const lifeSummary = ref(null);
const replacements = ref([]);

const showEditModal = ref(false);
const showInstallModal = ref(false);
const showReplaceModal = ref(false);
const showRemoveModal = ref(false);
const showInspectionModal = ref(false);
const showScrapModal = ref(false);

const isScrapped = computed(() => battery.value?.status === 'SCRAPPED');
const isInstallable = computed(() =>
    ['IN_STOCK', 'REMOVED'].includes(battery.value?.status) && !battery.value?.active_installation,
);

const lastInspection = computed(() => lifeSummary.value?.last_inspection ?? null);
const warrantyDaysLeft = computed(() => Number(lifeSummary.value?.warranty_remaining_days ?? 0));

const lifeUsedPercent = computed(() => Number(lifeSummary.value?.life_used_percent ?? 0));
const lifeMeterClass = computed(() => {
    if (lifeUsedPercent.value >= 90) {
        return 'bg-danger';
    }
    if (lifeUsedPercent.value >= 70) {
        return 'bg-warning';
    }
    return 'bg-success';
});

const costPerThousandKm = computed(() => {
    const km = Number(lifeSummary.value?.total_running_km ?? 0);
    const cost = Number(battery.value?.purchase_cost ?? 0) - Number(battery.value?.scrap_sale_amount ?? 0);
    if (km < 1000 || cost <= 0) {
        return null;
    }
    return (cost / km) * 1000;
});

const unassignedState = computed(() => {
    switch (battery.value?.status) {
        case 'REMOVED':
            return {
                icon: 'fas fa-pause-circle',
                iconClass: 'text-warning',
                title: 'On standby',
                subtitle: 'Previously used and taken off a truck. It can be fitted again or scrapped.',
            };
        case 'UNDER_INSPECTION':
            return {
                icon: 'fas fa-stethoscope',
                iconClass: 'text-primary',
                title: 'Under inspection',
                subtitle: 'Awaiting test results before it can be reused.',
            };
        case 'RETURNED':
            return {
                icon: 'fas fa-undo',
                iconClass: 'text-dark',
                title: 'Returned to dealer',
                subtitle: 'Sent back to the supplier, for example on a warranty claim.',
            };
        default:
            return {
                icon: 'fas fa-boxes',
                iconClass: 'text-info',
                title: 'In stock',
                subtitle: 'New and ready to be fitted to a truck.',
            };
    }
});

function conditionBadgeClass(condition) {
    return batteryConditionOption(condition)?.badgeClass ?? 'bg-secondary';
}

function conditionLabel(condition) {
    return batteryConditionOption(condition)?.label ?? humanize(condition);
}

function humanize(value) {
    const text = String(value ?? '');
    if (!/^[A-Z0-9_]+$/.test(text)) {
        return text;
    }
    const words = text.toLowerCase().split('_').filter(Boolean).join(' ');
    return words.charAt(0).toUpperCase() + words.slice(1);
}

function replacementDotClass(event) {
    return event.old_battery?.id === battery.value?.id ? 'bg-warning' : 'bg-success';
}

function replacementTitle(event) {
    if (event.old_battery?.id === battery.value?.id) {
        return event.new_battery ? 'Replaced on truck' : 'Removed from truck';
    }
    return 'Fitted as a replacement';
}

function replacementCounterpart(event) {
    if (event.old_battery?.id === battery.value?.id && event.new_battery) {
        return { label: 'Replaced by', battery: event.new_battery };
    }
    if (event.new_battery?.id === battery.value?.id && event.old_battery) {
        return { label: 'Replaced', battery: event.old_battery };
    }
    return null;
}

async function fetchBattery() {
    if (route.name !== 'batteries.show' || !route.params.id) {
        return;
    }
    loading.value = true;
    loadError.value = '';
    try {
        const res = await getBattery(route.params.id);
        battery.value = res.battery;
        lifeSummary.value = res.life_summary;
        replacements.value = res.replacements;
    } catch (err) {
        battery.value = null;
        loadError.value = err.response?.status === 404
            ? 'Battery not found. It may have been deleted or belongs to another company.'
            : err.response?.data?.message || 'Failed to load battery details.';
    } finally {
        loading.value = false;
    }
}

function onActionDone(message) {
    toast.success(message);
    fetchBattery();
}

watch(() => route.params.id, fetchBattery, { immediate: true });
</script>
