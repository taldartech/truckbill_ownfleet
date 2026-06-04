import { ref, unref, watch } from 'vue';
import { fetchVehiclePrimaryDriver } from '../api/trips';

/**
 * Auto-fill primary driver when vehicle changes (transport ERP standard).
 */
export function useVehicleDriverSync(form, { enabled = true, onWarning } = {}) {
    const driverAutoFilled = ref(false);
    const driverWarning = ref('');
    const loadingDriver = ref(false);
    let manualDriverOverride = false;

    watch(
        () => form.driver_id,
        (newValue, oldValue) => {
            if (oldValue !== '' && newValue !== oldValue && !driverAutoFilled.value) {
                manualDriverOverride = true;
            }
        },
    );

    watch(
        () => form.vehicle_id,
        async (vehicleId) => {
            if (!unref(enabled)) {
                return;
            }

            driverWarning.value = '';
            driverAutoFilled.value = false;

            if (!vehicleId) {
                return;
            }

            loadingDriver.value = true;

            try {
                const result = await fetchVehiclePrimaryDriver(vehicleId);

                if (result.primary_driver_id) {
                    if (!manualDriverOverride || !form.driver_id) {
                        form.driver_id = result.primary_driver_id;
                        driverAutoFilled.value = true;
                        manualDriverOverride = false;
                    }
                } else {
                    driverWarning.value =
                        'No primary driver is assigned to this vehicle. Assign a driver in Truck Management or select manually.';
                    if (typeof onWarning === 'function') {
                        onWarning(driverWarning.value);
                    }
                }
            } catch {
                driverWarning.value = 'Could not load the primary driver for this vehicle.';
            } finally {
                loadingDriver.value = false;
            }
        },
    );

    return {
        driverAutoFilled,
        driverWarning,
        loadingDriver,
    };
}
