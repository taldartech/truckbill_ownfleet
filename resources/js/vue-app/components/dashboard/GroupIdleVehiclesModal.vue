<template>
    <AppModal :show="show" title="Trucks not used recently" size="xl" dismissible close-on-escape @close="$emit('close')">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
            <div class="group-tabs">
                <button
                    v-for="option in thresholdOptions"
                    :key="option"
                    type="button"
                    class="group-tabs__tab"
                    :class="{ 'group-tabs__tab--active': activeThreshold === option }"
                    @click="activeThreshold = option"
                >
                    Idle {{ option }}+ days
                </button>
            </div>
            <select v-if="companyOptions.length > 1" v-model="companyFilter" class="form-select form-select-sm w-auto">
                <option value="">All companies</option>
                <option v-for="company in companyOptions" :key="company.id" :value="company.id">{{ company.name }}</option>
            </select>
        </div>

        <div v-if="!filteredVehicles.length" class="group-panel__empty">
            <i class="fas fa-circle-check"></i>
            No trucks idle for {{ activeThreshold }}+ days.
        </div>

        <div v-else class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead>
                    <tr>
                        <th>Truck</th>
                        <th>Company / Branch</th>
                        <th class="text-end">Idle days</th>
                        <th>Last trip ended</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="vehicle in filteredVehicles" :key="vehicle.id">
                        <td class="fw-semibold">
                            {{ vehicle.registration_number }}
                            <span v-if="vehicle.is_due_for_service" class="badge text-bg-danger ms-1">Service due</span>
                        </td>
                        <td>
                            {{ vehicle.company_name }}
                            <span v-if="vehicle.branch_name" class="text-muted small">· {{ vehicle.branch_name }}</span>
                        </td>
                        <td class="text-end">
                            <span class="badge" :class="vehicle.idle_days >= criticalDays ? 'text-bg-danger' : 'text-bg-warning'">
                                {{ vehicle.idle_days }}
                            </span>
                        </td>
                        <td>{{ vehicle.last_trip_end_date ? formatDate(vehicle.last_trip_end_date) : 'No trips yet' }}</td>
                        <td class="text-end">
                            <button
                                type="button"
                                class="group-chip-btn"
                                :disabled="!vehicle.branch_id || enteringBranchId !== null"
                                @click="openVehicle(vehicle)"
                            >
                                <i
                                    class="fas"
                                    :class="enteringBranchId === vehicle.branch_id ? 'fa-spinner fa-spin' : 'fa-arrow-right-to-bracket'"
                                ></i>
                                Open
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>
            <p v-if="totalCount > vehicles.length" class="small text-muted mt-2 mb-0">
                Showing the {{ vehicles.length }} longest-idle trucks of {{ totalCount }}.
            </p>
        </div>
    </AppModal>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import { useEnterBranch } from '../../composables/useEnterBranch';
import { formatDate } from '../../utils/datetime';
import AppModal from '../ui/AppModal.vue';
import './groupDashboard.css';

const props = defineProps({
    show: {
        type: Boolean,
        default: false,
    },
    vehicles: {
        type: Array,
        default: () => [],
    },
    warningDays: {
        type: Number,
        default: 7,
    },
    criticalDays: {
        type: Number,
        default: 15,
    },
    initialThreshold: {
        type: Number,
        default: 7,
    },
    totalCount: {
        type: Number,
        default: 0,
    },
});

const emit = defineEmits(['close']);

const { enteringBranchId, enterBranchAndOpen } = useEnterBranch();

const activeThreshold = ref(props.initialThreshold);
const companyFilter = ref('');

const thresholdOptions = computed(() => [props.warningDays, props.criticalDays]);

const companyOptions = computed(() => {
    const companies = new Map();

    props.vehicles.forEach((vehicle) => companies.set(vehicle.company_id, vehicle.company_name));

    return [...companies.entries()].map(([id, name]) => ({ id, name }));
});

const filteredVehicles = computed(() =>
    props.vehicles.filter(
        (vehicle) =>
            vehicle.idle_days >= activeThreshold.value
            && (!companyFilter.value || vehicle.company_id === companyFilter.value),
    ),
);

watch(
    () => [props.show, props.initialThreshold],
    ([visible]) => {
        if (visible) {
            activeThreshold.value = props.initialThreshold;
            companyFilter.value = '';
        }
    },
);

async function openVehicle(vehicle) {
    await enterBranchAndOpen(vehicle.branch_id, {
        label: vehicle.branch_name ?? vehicle.company_name,
        to: { name: 'trucks.index', query: { search: vehicle.registration_number } },
    });

    emit('close');
}
</script>
