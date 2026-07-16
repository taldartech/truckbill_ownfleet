<template>
    <div class="container mt-4 city-detail-page">
        <div v-if="loading" class="text-center py-5 text-muted">Loading city details…</div>

        <template v-else-if="city">
            <div class="row g-3">
                <div class="col-lg-8">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <strong>City Information ({{ city?.name }})</strong>
                            <div class="app-index-table-actions ms-auto" style="margin-left: auto !important; display: inline-flex !important; gap: 0.375rem !important;">
                                <RouterLink
                                    :to="{ name: 'locations.index' }"
                                    class="btn btn-sm btn-outline-secondary btn-icon app-index-action-btn"
                                    title="Back to Locations"
                                    style="background-color: #fff; border-color: #cbd5e1; color: #475569;"
                                >
                                    <i class="fas fa-arrow-left" style="color: #475569 !important;" aria-hidden="true"></i>
                                </RouterLink>
                            </div>
                        </div>
                        <div class="card-body row g-3 small">
                            <div class="col-md-6">
                                <span class="text-muted">Name</span>
                                <div class="fw-semibold">{{ city.name }}</div>
                            </div>
                            <div class="col-md-6">
                                <span class="text-muted">State</span>
                                <div class="fw-semibold">{{ city.state_name || '—' }}</div>
                            </div>
                        </div>

                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="card">
                        <div class="card-header"><strong>Record Info</strong></div>
                        <div class="card-body small">
                            <div class="mb-2">
                                <span class="text-muted">Created</span>
                                <div>{{ formatDateTime(city.created_at) }}</div>
                            </div>
                            <div>
                                <span class="text-muted">Last Updated</span>
                                <div>{{ formatDateTime(city.updated_at) }}</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </template>

        <div v-else class="alert alert-danger">
            City not found or you do not have access.
        </div>
    </div>
</template>

<script setup>
import { onMounted, ref } from 'vue';
import { RouterLink, useRoute } from 'vue-router';
import { fetchCity } from '../../api/cities';
import { useAppToast } from '../../composables/useAppToast';
import { formatDateTime } from '../../utils/datetime';

const route = useRoute();
const toast = useAppToast();
const city = ref(null);
const loading = ref(true);

async function loadCity() {
    const id = route.params.id;
    if (!id) {
        city.value = null;
        loading.value = false;
        return;
    }

    loading.value = true;
    try {
        city.value = await fetchCity(id);
    } catch {
        city.value = null;
        toast.error('Could not load city details.');
    } finally {
        loading.value = false;
    }
}

onMounted(async () => {
    await loadCity();
});
</script>
