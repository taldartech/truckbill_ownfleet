<template>
    <AppModal
        :show="show"
        :loading="generating"
        :form-error="formError"
        size="lg"
        :header-gradient="false"
        :body-class="'tgi-modal__body'"
        :footer-class="'tgi-modal__footer'"
        :content-class="'tgi-modal__content'"
        @close="$emit('close')"
    >
        <template #title>
            <span class="tgi-modal__title">
                <i class="bi bi-receipt-cutoff" aria-hidden="true"></i>
                Generate Invoice(s)
                <span v-if="groups.length > 0" class="tgi-modal__count">
                    {{ groups.length }}
                </span>
            </span>
        </template>

        <div v-if="trip" class="tgi-trip">
            <div class="tgi-trip__row">
                <strong class="tgi-trip__number">{{ trip.trip_number }}</strong>
                <span class="tgi-trip__chip">{{ tripTypeLabel(trip.trip_type) }}</span>
                <span class="tgi-trip__chip">{{ trip.vehicle?.registration_number || '—' }}</span>
                <span class="tgi-trip__chip">{{ trip.driver?.name || '—' }}</span>
                <span class="tgi-trip__chip">
                    {{ formatDate(trip.start_at) }}<template v-if="trip.end_at"> – {{ formatDate(trip.end_at) }}</template>
                </span>
                <span class="tgi-trip__chip tgi-trip__chip--money">₹{{ formatMoney(trip.total_freight_amount) }}</span>
            </div>
            <div v-if="routePreview" class="tgi-trip__route" :title="routePreview">
                <i class="bi bi-signpost-split" aria-hidden="true"></i>
                {{ routePreview }}
            </div>
        </div>

        <div v-if="previewLoading && groups.length === 0" class="tgi-empty">
            <div class="spinner-border spinner-border-sm text-primary" role="status"></div>
            <span>Loading preview…</span>
        </div>

        <div v-else-if="groups.length === 0" class="tgi-alert">
            <i class="bi bi-exclamation-triangle" aria-hidden="true"></i>
            No billable routes are available to invoice on this trip.
        </div>

        <div v-else class="tgi-content">
            <div v-if="previewRefreshing" class="tgi-refresh">
                <span class="spinner-border spinner-border-sm" role="status"></span>
                Recalculating…
            </div>

            <div class="tgi-groups">
                <section
                    v-for="group in groups"
                    :key="group.customer_id"
                    class="tgi-group"
                >
                    <div class="tgi-group__top">
                        <div class="tgi-group__identity">
                            <div class="tgi-group__name-row">
                                <strong class="tgi-group__name">{{ group.customer_name }}</strong>
                                <span class="tgi-group__doc">{{ group.document_type_label }}</span>
                            </div>
                            <div class="tgi-group__sub">
                                <span v-if="group.customer_gst_number">{{ group.customer_gst_number }}</span>
                                <span>{{ routeCount(group) }} route{{ routeCount(group) === 1 ? '' : 's' }}</span>
                            </div>
                        </div>
                        <div class="tgi-group__amount">
                            <span class="tgi-group__amount-label">Total</span>
                            <strong>₹{{ formatMoney(group.total_amount) }}</strong>
                            <span v-if="group.displays_gst" class="tgi-group__amount-sub">
                                incl. GST ₹{{ formatMoney(group.gst_amount) }}
                            </span>
                        </div>
                    </div>

                    <div class="tgi-group__fields">
                        <div class="tgi-field">
                            <label class="tgi-field__label">Invoice date</label>
                            <AppDateInput
                                v-model="group.invoice_date"
                                size="sm"
                                :invalid="Boolean(fieldErrors[`groups.${group.customer_id}.invoice_date`])"
                                @update:model-value="$emit('invoice-date-change')"
                            />
                            <p
                                v-if="fieldErrors[`groups.${group.customer_id}.invoice_date`]"
                                class="tgi-field__error"
                            >
                                {{ fieldErrors[`groups.${group.customer_id}.invoice_date`][0] }}
                            </p>
                        </div>
                        <div class="tgi-field">
                            <label class="tgi-field__label">Due date</label>
                            <AppDateInput
                                v-model="group.due_date"
                                size="sm"
                                :min="group.invoice_date || undefined"
                                :invalid="Boolean(fieldErrors[`groups.${group.customer_id}.due_date`])"
                            />
                            <p
                                v-if="fieldErrors[`groups.${group.customer_id}.due_date`]"
                                class="tgi-field__error"
                            >
                                {{ fieldErrors[`groups.${group.customer_id}.due_date`][0] }}
                            </p>
                        </div>
                        <div class="tgi-field tgi-field--number">
                            <label class="tgi-field__label">Invoice no.</label>
                            <div class="tgi-number">
                                <input
                                    :value="group.invoice_number"
                                    type="text"
                                    class="form-control form-control-sm bg-light tgi-number__prefix"
                                    readonly
                                />
                                <input
                                    :value="group.invoice_sequence"
                                    type="text"
                                    inputmode="numeric"
                                    class="form-control form-control-sm tgi-number__seq"
                                    :class="{
                                        'is-invalid': Boolean(
                                            fieldErrors[`groups.${group.customer_id}.invoice_sequence`],
                                        ),
                                    }"
                                    placeholder="#"
                                    @input="$emit('sequence-input', group, $event)"
                                />
                            </div>
                            <p
                                v-if="fieldErrors[`groups.${group.customer_id}.invoice_sequence`]"
                                class="tgi-field__error"
                            >
                                {{ fieldErrors[`groups.${group.customer_id}.invoice_sequence`][0] }}
                            </p>
                        </div>
                    </div>

                    <button
                        type="button"
                        class="tgi-routes-toggle"
                        :aria-expanded="isRoutesExpanded(group.customer_id)"
                        @click="toggleRoutes(group.customer_id)"
                    >
                        <i
                            class="bi"
                            :class="isRoutesExpanded(group.customer_id) ? 'bi-chevron-up' : 'bi-chevron-down'"
                            aria-hidden="true"
                        ></i>
                        <span>
                            Routes · Freight ₹{{ formatMoney(group.route_freight_amount ?? group.freight_amount) }}
                        </span>
                    </button>

                    <div v-if="isRoutesExpanded(group.customer_id)" class="tgi-routes">
                        <div
                            v-for="route in group.routes"
                            :key="route.id"
                            class="tgi-route"
                        >
                            <div class="tgi-route__path">
                                {{ route.from_location }} → {{ route.to_location }}
                            </div>
                            <div class="tgi-route__meta">
                                <span>{{ route.goods_name || '—' }}</span>
                                <strong>₹{{ formatMoney(route.freight_amount) }}</strong>
                            </div>
                        </div>
                    </div>

                    <div class="tgi-custom">
                        <div class="tgi-custom__header">
                            <span class="tgi-custom__title">Additional charges</span>
                            <button
                                type="button"
                                class="btn btn-link btn-sm tgi-custom__add"
                                @click="addCustomItem(group)"
                            >
                                <i class="bi bi-plus-lg" aria-hidden="true"></i>
                                Add
                            </button>
                        </div>
                        <div
                            v-for="(item, index) in ensureCustomItems(group)"
                            :key="`${group.customer_id}-custom-${index}`"
                            class="tgi-custom__row"
                        >
                            <input
                                v-model="item.description"
                                type="text"
                                class="form-control form-control-sm"
                                :class="{
                                    'is-invalid': Boolean(
                                        fieldErrors[`groups.${group.customer_id}.custom_items.${index}.description`],
                                    ),
                                }"
                                placeholder="Description"
                                @input="$emit('custom-items-change')"
                            />
                            <input
                                v-model="item.amount"
                                type="number"
                                min="0.01"
                                step="0.01"
                                class="form-control form-control-sm tgi-custom__amount"
                                :class="{
                                    'is-invalid': Boolean(
                                        fieldErrors[`groups.${group.customer_id}.custom_items.${index}.amount`],
                                    ),
                                }"
                                placeholder="Amount"
                                @input="$emit('custom-items-change')"
                            />
                            <button
                                type="button"
                                class="btn btn-outline-secondary btn-sm tgi-custom__remove"
                                title="Remove"
                                @click="removeCustomItem(group, index); $emit('custom-items-change')"
                            >
                                <i class="bi bi-x-lg" aria-hidden="true"></i>
                            </button>
                        </div>
                        <p
                            v-if="customItemsError(group)"
                            class="tgi-field__error"
                        >
                            {{ customItemsError(group) }}
                        </p>
                    </div>
                </section>
            </div>
        </div>

        <template #footer>
            <div v-if="groups.length > 0" class="tgi-footer-summary">
                <span class="tgi-footer-summary__count">
                    {{ groups.length }} invoice{{ groups.length === 1 ? '' : 's' }}
                </span>
                <span class="tgi-footer-summary__divider" aria-hidden="true"></span>
                <span>Freight <strong>₹{{ formatMoney(totals.freight) }}</strong></span>
                <span v-if="totals.gst > 0">GST <strong>₹{{ formatMoney(totals.gst) }}</strong></span>
                <span class="tgi-footer-summary__total">
                    ₹{{ formatMoney(totals.total) }}
                </span>
            </div>
            <div class="tgi-footer-actions">
                <button type="button" class="btn btn-outline-secondary btn-sm" @click="$emit('close')">
                    Cancel
                </button>
                <AppButton
                    variant="info"
                    label="Continue"
                    icon="bi bi-arrow-right"
                    size="sm"
                    :disabled="previewLoading || generating || groups.length === 0"
                    @click="$emit('continue')"
                />
            </div>
        </template>
    </AppModal>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import { tripTypeLabel } from '../../api/trips';
import AppButton from '../ui/AppButton.vue';
import AppDateInput from '../ui/AppDateInput.vue';
import AppModal from '../ui/AppModal.vue';
import { formatDate } from '../../utils/datetime';

const props = defineProps({
    show: { type: Boolean, default: false },
    trip: { type: Object, default: null },
    groups: { type: Array, default: () => [] },
    previewLoading: { type: Boolean, default: false },
    previewRefreshing: { type: Boolean, default: false },
    generating: { type: Boolean, default: false },
    formError: { type: String, default: '' },
    fieldErrors: { type: Object, default: () => ({}) },
    statusMeta: { type: Object, default: () => ({ badge: 'bg-secondary', label: '—' }) },
    routePreview: { type: String, default: '' },
});

defineEmits(['close', 'continue', 'invoice-date-change', 'sequence-input', 'custom-items-change']);

const expandedRouteIds = ref(new Set());

watch(
    () => props.show,
    (visible) => {
        if (!visible) {
            expandedRouteIds.value = new Set();
        }
    },
);

watch(
    () => props.groups.map((group) => group.customer_id).join(','),
    (ids) => {
        if (!ids) {
            expandedRouteIds.value = new Set();

            return;
        }

        if (props.groups.length === 1) {
            expandedRouteIds.value = new Set([props.groups[0].customer_id]);
        }
    },
    { immediate: true },
);

const totals = computed(() =>
    props.groups.reduce(
        (acc, group) => {
            acc.freight += Number(group.freight_amount) || 0;
            acc.gst += group.displays_gst ? Number(group.gst_amount) || 0 : 0;
            acc.total += Number(group.total_amount) || 0;

            return acc;
        },
        { freight: 0, gst: 0, total: 0 },
    ),
);

function routeCount(group) {
    return Array.isArray(group.routes) ? group.routes.length : 0;
}

function ensureCustomItems(group) {
    if (!Array.isArray(group.custom_items)) {
        group.custom_items = [];
    }

    return group.custom_items;
}

function addCustomItem(group) {
    ensureCustomItems(group).push({ description: '', amount: '' });
}

function removeCustomItem(group, index) {
    ensureCustomItems(group).splice(index, 1);
}

function customItemsError(group) {
    const prefix = `groups.${group.customer_id}.custom_items`;
    const errors = props.fieldErrors || {};

    for (const [key, messages] of Object.entries(errors)) {
        if (key.startsWith(prefix) && Array.isArray(messages) && messages[0]) {
            return messages[0];
        }
    }

    return '';
}

function isRoutesExpanded(customerId) {
    return expandedRouteIds.value.has(customerId);
}

function toggleRoutes(customerId) {
    const next = new Set(expandedRouteIds.value);

    if (next.has(customerId)) {
        next.delete(customerId);
    } else {
        next.add(customerId);
    }

    expandedRouteIds.value = next;
}

function formatMoney(value) {
    return Number(value ?? 0).toLocaleString('en-IN', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });
}
</script>

<style scoped>
:deep(.tgi-modal__content) {
    border: 0;
    box-shadow: 0 18px 48px rgba(15, 23, 42, 0.14);
}

:deep(.tgi-modal__body) {
    padding: 0.65rem 0.85rem 0.55rem;
    max-height: min(68vh, 620px);
    overflow-y: auto;
}

:deep(.tgi-modal__footer) {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 0.55rem 0.75rem;
    padding: 0.55rem 0.85rem;
    border-top: 1px solid #e2e8f0;
    background: #f8fafc;
}

.tgi-modal__title {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    font-size: 0.95rem;
    font-weight: 700;
}

.tgi-modal__count {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 1.35rem;
    height: 1.35rem;
    padding: 0 0.35rem;
    border-radius: 99px;
    background: #dbeafe;
    color: #1d4ed8;
    font-size: 0.68rem;
    font-weight: 800;
}

.tgi-trip {
    margin-bottom: 0.55rem;
    padding: 0.45rem 0.55rem;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    background: #f8fafc;
}

.tgi-trip__row {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 0.28rem 0.4rem;
}

.tgi-trip__number {
    font-size: 0.82rem;
    color: #0f172a;
}

.tgi-trip__badge {
    font-size: 0.62rem;
    padding: 0.2em 0.45em;
}

.tgi-trip__chip {
    display: inline-flex;
    align-items: center;
    padding: 0.12rem 0.4rem;
    border-radius: 99px;
    background: #fff;
    border: 1px solid #e2e8f0;
    color: #334155;
    font-size: 0.68rem;
    font-weight: 600;
    line-height: 1.3;
}

.tgi-trip__chip--money {
    color: #166534;
    border-color: #bbf7d0;
    background: #f0fdf4;
}

.tgi-trip__route {
    display: flex;
    align-items: flex-start;
    gap: 0.35rem;
    margin-top: 0.35rem;
    padding-top: 0.35rem;
    border-top: 1px dashed #e2e8f0;
    color: #475569;
    font-size: 0.68rem;
    font-weight: 600;
    line-height: 1.35;
}

.tgi-trip__route i {
    margin-top: 0.1rem;
    color: #94a3b8;
}

.tgi-empty,
.tgi-alert {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.45rem;
    padding: 1.5rem 0.5rem;
    font-size: 0.8rem;
    color: #64748b;
}

.tgi-alert {
    border-radius: 8px;
    background: #fffbeb;
    border: 1px solid #fde68a;
    color: #92400e;
}

.tgi-content {
    display: flex;
    flex-direction: column;
    gap: 0.4rem;
}

.tgi-refresh {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    align-self: flex-end;
    font-size: 0.68rem;
    color: #64748b;
}

.tgi-groups {
    display: flex;
    flex-direction: column;
    gap: 0.4rem;
}

.tgi-group {
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    overflow: hidden;
    background: #fff;
    box-shadow: 0 1px 0 rgba(15, 23, 42, 0.03);
}

.tgi-group__top {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 0.55rem;
    padding: 0.45rem 0.55rem;
    background: linear-gradient(180deg, #f8fafc 0%, #fff 100%);
    border-bottom: 1px solid #eef2f7;
}

.tgi-group__identity {
    min-width: 0;
}

.tgi-group__name-row {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 0.3rem 0.45rem;
}

.tgi-group__name {
    font-size: 0.8rem;
    color: #0f172a;
}

.tgi-group__doc {
    display: inline-flex;
    padding: 0.1rem 0.4rem;
    border-radius: 99px;
    background: #eff6ff;
    border: 1px solid #bfdbfe;
    color: #1d4ed8;
    font-size: 0.62rem;
    font-weight: 700;
}

.tgi-group__sub {
    display: flex;
    flex-wrap: wrap;
    gap: 0.35rem 0.55rem;
    margin-top: 0.15rem;
    font-size: 0.64rem;
    font-weight: 600;
    color: #64748b;
}

.tgi-group__amount {
    text-align: right;
    flex-shrink: 0;
}

.tgi-group__amount-label {
    display: block;
    font-size: 0.6rem;
    font-weight: 700;
    letter-spacing: 0.03em;
    text-transform: uppercase;
    color: #94a3b8;
}

.tgi-group__amount strong {
    display: block;
    font-size: 0.88rem;
    color: #1d4ed8;
    line-height: 1.15;
}

.tgi-group__amount-sub {
    display: block;
    margin-top: 0.05rem;
    font-size: 0.62rem;
    color: #64748b;
}

.tgi-group__fields {
    display: grid;
    grid-template-columns: minmax(0, 1fr) minmax(0, 1fr) minmax(0, 1.35fr);
    gap: 0.35rem 0.45rem;
    padding: 0.45rem 0.55rem;
}

.tgi-field {
    min-width: 0;
}

.tgi-field__label {
    display: block;
    margin-bottom: 0.12rem;
    font-size: 0.6rem;
    font-weight: 700;
    letter-spacing: 0.03em;
    text-transform: uppercase;
    color: #94a3b8;
}

.tgi-field__error {
    margin: 0.12rem 0 0;
    font-size: 0.64rem;
    color: #dc2626;
}

.tgi-number {
    display: flex;
    gap: 0.25rem;
}

.tgi-number__prefix {
    flex: 1 1 auto;
    min-width: 0;
    font-size: 0.74rem;
}

.tgi-number__seq {
    flex: 0 0 3.25rem;
    width: 3.25rem;
    text-align: center;
    font-size: 0.74rem;
}

.tgi-routes-toggle {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    width: 100%;
    padding: 0.32rem 0.55rem;
    border: 0;
    border-top: 1px solid #eef2f7;
    background: #fcfdff;
    color: #475569;
    font-size: 0.68rem;
    font-weight: 600;
    text-align: left;
}

.tgi-routes-toggle:hover {
    background: #f1f5f9;
    color: #0f172a;
}

.tgi-routes {
    border-top: 1px solid #eef2f7;
    background: #fafbfc;
}

.tgi-route {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.55rem;
    padding: 0.28rem 0.55rem;
    font-size: 0.7rem;
    border-bottom: 1px solid #f1f5f9;
}

.tgi-route:last-child {
    border-bottom: 0;
}

.tgi-route__path {
    min-width: 0;
    color: #0f172a;
    font-weight: 600;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.tgi-route__meta {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    flex-shrink: 0;
    color: #64748b;
}

.tgi-route__meta strong {
    color: #0f172a;
    font-size: 0.7rem;
}

.tgi-custom {
    padding: 0.4rem 0.55rem 0.55rem;
    border-top: 1px dashed #e2e8f0;
}

.tgi-custom__header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
    margin-bottom: 0.35rem;
}

.tgi-custom__title {
    font-size: 0.6rem;
    font-weight: 700;
    letter-spacing: 0.03em;
    text-transform: uppercase;
    color: #94a3b8;
}

.tgi-custom__add {
    padding: 0;
    text-decoration: none;
    font-size: 0.72rem;
    font-weight: 600;
}

.tgi-custom__row {
    display: grid;
    grid-template-columns: minmax(0, 1fr) 6.5rem 2rem;
    gap: 0.3rem;
    margin-bottom: 0.3rem;
}

.tgi-custom__amount {
    text-align: right;
}

.tgi-custom__remove {
    padding: 0.15rem;
    line-height: 1;
}

.tgi-footer-summary {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 0.35rem 0.55rem;
    font-size: 0.72rem;
    color: #64748b;
}

.tgi-footer-summary strong {
    color: #0f172a;
}

.tgi-footer-summary__count {
    font-weight: 700;
    color: #334155;
}

.tgi-footer-summary__divider {
    width: 1px;
    height: 0.9rem;
    background: #cbd5e1;
}

.tgi-footer-summary__total {
    padding: 0.15rem 0.45rem;
    border-radius: 6px;
    background: #dbeafe;
    color: #1d4ed8;
    font-size: 0.82rem;
    font-weight: 800;
}

.tgi-footer-actions {
    display: flex;
    align-items: center;
    gap: 0.45rem;
    margin-left: auto;
}

@media (max-width: 767.98px) {
    :deep(.tgi-modal__footer) {
        flex-direction: column;
        align-items: stretch;
    }

    .tgi-group__fields {
        grid-template-columns: 1fr;
    }

    .tgi-footer-actions {
        width: 100%;
        justify-content: flex-end;
    }
}
</style>
