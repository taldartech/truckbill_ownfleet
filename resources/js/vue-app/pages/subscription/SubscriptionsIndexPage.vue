<template>
    <div class="container mt-4 app-index-page">
        <AppIndexPageHeader title="Subscriptions">
            <template #actions>
                <RouterLink to="/subscription/history" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-receipt me-1"></i> Payment history
                </RouterLink>
            </template>
        </AppIndexPageHeader>

        <div v-if="error" class="alert alert-danger">{{ error }}</div>
        <div v-if="loading" class="text-center py-5 text-muted">Loading subscriptions…</div>

        <template v-else>
            <div class="row g-3 mb-4">
                <div class="col-md-4">
                    <div class="card h-100">
                        <div class="card-body">
                            <div class="text-muted small">Active plans</div>
                            <div class="fs-3 fw-bold text-success">{{ summary.active }}</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card h-100">
                        <div class="card-body">
                            <div class="text-muted small">Needs attention</div>
                            <div class="fs-3 fw-bold text-danger">{{ summary.expired }}</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card h-100">
                        <div class="card-body">
                            <div class="text-muted small">Total companies</div>
                            <div class="fs-3 fw-bold">{{ summary.total }}</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card app-index-list-card">
                <div class="card-header">
                    <h3 class="card-title mb-0">Company subscriptions</h3>
                </div>
                <div class="card-body p-0 table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Company</th>
                                <th>Status</th>
                                <th>Valid until</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="company in companies" :key="company.id">
                                <td>{{ company.name }}</td>
                                <td>
                                    <StatusBadge :status="company.subscription?.display_status ?? 'none'" />
                                </td>
                                <td>{{ formatDate(company.subscription?.ends_on) }}</td>
                                <td class="text-end">
                                    <RouterLink
                                        :to="{ path: '/subscription/manage', query: { company_id: company.id } }"
                                        class="btn btn-outline-primary btn-sm"
                                    >
                                        Manage
                                    </RouterLink>
                                </td>
                            </tr>
                            <tr v-if="companies.length === 0">
                                <td colspan="4" class="text-center py-4 text-muted">No companies found.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </template>
    </div>
</template>

<script setup>
import { onMounted, ref } from 'vue';
import { RouterLink } from 'vue-router';
import AppIndexPageHeader from '../../components/ui/AppIndexPageHeader.vue';
import StatusBadge from '../../components/subscription/StatusBadge.vue';
import { fetchSubscriptionIndex } from '../../api/subscriptions';

const loading = ref(true);
const error = ref('');
const companies = ref([]);
const summary = ref({ active: 0, expired: 0, total: 0 });

onMounted(async () => {
    try {
        const data = await fetchSubscriptionIndex();
        companies.value = data.companies ?? [];
        summary.value = data.summary ?? summary.value;
    } catch (err) {
        error.value = err.response?.data?.message || 'Unable to load subscriptions.';
    } finally {
        loading.value = false;
    }
});

function formatDate(value) {
    if (!value) return '—';

    return new Date(value).toLocaleDateString('en-GB', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    });
}
</script>
