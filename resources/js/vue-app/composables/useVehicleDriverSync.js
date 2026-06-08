import { ref, unref, watch } from 'vue';
import { fetchVehiclePrimaryDriver } from '../api/trips';
import { dateOnlyFromDatetimeLocal } from '../utils/datetime';

/**
 * Auto-fill primary driver when vehicle or trip start date changes.
 */
export function useVehicleDriverSync(form, { enabled = true } = {}) {
    const driverWarning = ref('');
    const loadingDriver = ref(false);
    const primaryDriverName = ref('');

    async function syncPrimaryDriver() {
        if (!unref(enabled)) {
            return;
        }

        driverWarning.value = '';
        primaryDriverName.value = '';
        form.driver_id = '';

        const vehicleId = form.vehicle_id;

        if (!vehicleId) {
            return;
        }

        loadingDriver.value = true;

        try {
            const asOfDate = dateOnlyFromDatetimeLocal(form.start_at);
            const result = await fetchVehiclePrimaryDriver(vehicleId, { asOfDate });

            if (result.primary_driver_id) {
                form.driver_id = result.primary_driver_id;
                primaryDriverName.value = result.driver?.name ?? '';
            } else {
                driverWarning.value = asOfDate
                    ? 'No primary driver is assigned to this truck on the selected trip start date. Update the start date or assign a driver in Truck Management.'
                    : 'No primary driver is assigned to this truck. Assign a driver in Truck Management first.';
            }
        } catch {
            driverWarning.value = 'Could not load the primary driver for this truck.';
        } finally {
            loadingDriver.value = false;
        }
    }

    watch(
        () => [form.vehicle_id, form.start_at],
        () => {
            syncPrimaryDriver();
        },
    );

    function setPrimaryDriverFromSelection(vehicleItem) {
        if (!vehicleItem?.vehicle_id) {
            form.driver_id = '';
            primaryDriverName.value = '';

            return;
        }

        if (vehicleItem.selectable && vehicleItem.primary_driver_id) {
            form.driver_id = vehicleItem.primary_driver_id;
            primaryDriverName.value = vehicleItem.primary_driver_name ?? '';
            driverWarning.value = '';

            return;
        }

        syncPrimaryDriver();
    }

    return {
        driverWarning,
        loadingDriver,
        primaryDriverName,
        setPrimaryDriverFromSelection,
        syncPrimaryDriver,
    };
}
