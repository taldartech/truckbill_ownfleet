<template>
    <div>
        <RouterLink
            :to="{ path: '/subscription/manage', query: { company_id: company.id } }"
            class="btn btn-link mb-3 text-decoration-none"
        >
            <i class="bi bi-arrow-left me-1"></i> Back to plan details
        </RouterLink>

        <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
            <div>
                <h4 class="fw-bold mb-1">{{ isRenewal ? 'Renew plan' : 'Buy add-ons' }}</h4>
                <p class="text-muted mb-0">{{ company.name }} — configure your order and pay securely.</p>
            </div>
            <StatusBadge :status="subscription?.display_status ?? 'none'" />
        </div>

        <div class="row g-4">
            <div class="col-lg-5">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Catalog pricing</h5>
                    </div>
                    <div class="card-body">
                        <div class="d-flex justify-content-between py-2 border-bottom">
                            <span>Annual base plan</span>
                            <span>₹{{ formatMoney(catalogData.base_price) }}</span>
                        </div>
                        <div class="d-flex justify-content-between py-2 border-bottom">
                            <span>Branch add-on</span>
                            <span>₹{{ formatMoney(catalogData.addon_branch_price) }}/yr</span>
                        </div>
                        <div class="d-flex justify-content-between py-2 border-bottom">
                            <span>User add-on</span>
                            <span>₹{{ formatMoney(catalogData.addon_user_price) }}/yr</span>
                        </div>
                        <div class="d-flex justify-content-between py-2">
                            <span>Vehicle add-on</span>
                            <span>₹{{ formatMoney(catalogData.addon_truck_price) }}/yr</span>
                        </div>
                        <p v-if="!isRenewal && remainingDays" class="text-success small mt-3 mb-0">
                            Add-ons are prorated for {{ remainingDays }} remaining day(s).
                        </p>
                    </div>
                </div>
            </div>

            <div class="col-lg-7">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            {{ isRenewal ? 'Renew your subscription' : 'Add capacity to your plan' }}
                        </h5>
                    </div>
                    <div class="card-body">
                        <div v-if="isRenewal && usage" class="alert alert-warning">
                            Current usage: {{ usage.branches }} branch(es), {{ usage.total_users }} user(s),
                            {{ usage.trucks }} vehicle(s).
                        </div>

                        <div class="row g-3 mb-3">
                            <div class="col-md-4">
                                <label class="form-label fw-semibold" for="addon_branches">
                                    Extra branches<span v-if="isRenewal && minBranches > 0"> (min {{ minBranches }})</span>
                                </label>
                                <input
                                    id="addon_branches"
                                    v-model.number="addonBranches"
                                    type="number"
                                    class="form-control"
                                    :min="isRenewal ? minBranches : 0"
                                    @input="onAddonChange"
                                />
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold" for="addon_users">
                                    Extra users<span v-if="isRenewal && minUsers > 0"> (min {{ minUsers }})</span>
                                </label>
                                <input
                                    id="addon_users"
                                    v-model.number="addonUsers"
                                    type="number"
                                    class="form-control"
                                    :min="isRenewal ? minUsers : 0"
                                    @input="onAddonChange"
                                />
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold" for="addon_trucks">
                                    Extra vehicles<span v-if="isRenewal && minTrucks > 0"> (min {{ minTrucks }})</span>
                                </label>
                                <input
                                    id="addon_trucks"
                                    v-model.number="addonTrucks"
                                    type="number"
                                    class="form-control"
                                    :min="isRenewal ? minTrucks : 0"
                                    @input="onAddonChange"
                                />
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold" for="promo_code">Promo code</label>
                            <div class="input-group">
                                <input
                                    id="promo_code"
                                    v-model="promoCode"
                                    type="text"
                                    class="form-control"
                                    placeholder="Enter code"
                                    @input="onPromoInput"
                                />
                                <button type="button" class="btn btn-outline-secondary" @click="validatePromo">
                                    Apply
                                </button>
                            </div>
                        </div>

                        <div v-if="quoteErrors" class="alert alert-danger py-2">{{ quoteErrors }}</div>
                        <div v-if="promoMessage" class="alert alert-success py-2">{{ promoMessage }}</div>

                        <div class="border rounded p-3 mb-3 bg-light">
                            <div class="text-muted small mb-2">Order summary</div>
                            <div v-if="loadingQuote" class="text-muted">Calculating quote…</div>
                            <template v-else>
                                <div
                                    v-for="(item, index) in lineItems"
                                    :key="index"
                                    class="d-flex justify-content-between py-1"
                                >
                                    <span>{{ item.label }}</span>
                                    <span>₹{{ formatMoney(Math.abs(item.amount)) }}</span>
                                </div>
                            </template>
                            <div v-if="!loadingQuote" class="d-flex justify-content-between py-1 border-top mt-2 pt-2">
                                <span>GST ({{ gstRatePercent }}%)</span>
                                <span>₹{{ gstAmount }}</span>
                            </div>
                            <div class="d-flex justify-content-between fw-bold border-top pt-2 mt-2">
                                <span>Total payable</span>
                                <span>₹{{ quoteTotal }}</span>
                            </div>
                        </div>

                        <button
                            type="button"
                            class="btn btn-primary"
                            :disabled="!canCheckout || paying"
                            @click="pay(company.name)"
                        >
                            {{ paying ? 'Processing…' : 'Pay securely with Razorpay' }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { RouterLink } from 'vue-router';
import StatusBadge from '../../components/subscription/StatusBadge.vue';
import { useSubscriptionCheckout } from '../../composables/useSubscriptionCheckout';

const props = defineProps({
    company: { type: Object, required: true },
    subscription: { type: Object, default: null },
    catalog: { type: Object, required: true },
    purchaseType: { type: String, required: true },
    usage: { type: Object, default: null },
    minimumAddons: { type: Object, default: () => ({}) },
    remainingDays: { type: Number, default: null },
    includeTrucks: { type: Boolean, default: true },
});

const catalogData = computed(() => props.catalog?.catalog ?? {});

const {
    addonBranches,
    addonUsers,
    addonTrucks,
    promoCode,
    promoMessage,
    quoteErrors,
    lineItems,
    gstAmount,
    gstRatePercent,
    quoteTotal,
    canCheckout,
    paying,
    loadingQuote,
    minBranches,
    minUsers,
    minTrucks,
    isRenewal,
    onAddonChange,
    onPromoInput,
    validatePromo,
    pay,
} = useSubscriptionCheckout({
    companyId: props.company.id,
    purchaseType: props.purchaseType,
    manageRoute: { path: '/subscription/manage', query: { company_id: props.company.id } },
    minimumAddons: props.minimumAddons,
    includeTrucks: props.includeTrucks,
});

function formatMoney(value) {
    return Number(value || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}
</script>
