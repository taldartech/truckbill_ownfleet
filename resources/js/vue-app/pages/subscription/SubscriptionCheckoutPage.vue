<template>
    <div class="container mt-4 app-index-page">
        <div v-if="error" class="alert alert-danger">{{ error }}</div>
        <div v-if="loading" class="text-center py-5 text-muted">Loading checkout…</div>
        <SubscriptionCheckoutForm
            v-else-if="checkoutData"
            v-bind="checkoutData"
        />
    </div>
</template>

<script setup>
import { onMounted, ref } from 'vue';
import { useRoute } from 'vue-router';
import SubscriptionCheckoutForm from '../../components/subscription/SubscriptionCheckoutForm.vue';
import { fetchSubscriptionCheckout } from '../../api/subscriptions';

const route = useRoute();
const loading = ref(true);
const error = ref('');
const checkoutData = ref(null);

onMounted(async () => {
    const companyId = route.query.company_id;
    const purchaseType = route.query.purchase_type ?? 'renewal';

    if (!companyId) {
        error.value = 'Company is required.';
        loading.value = false;
        return;
    }

    try {
        checkoutData.value = await fetchSubscriptionCheckout({
            company_id: companyId,
            purchase_type: purchaseType,
        });
    } catch (err) {
        error.value = err.response?.data?.message || 'Unable to load checkout.';
    } finally {
        loading.value = false;
    }
});
</script>
