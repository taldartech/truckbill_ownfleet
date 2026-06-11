import { onBeforeUnmount, unref, watch } from 'vue';
import { checkTripWindowOverlap, tripWindowConflictErrors } from '../api/trips';

export function useTripWindowValidation(form, { fieldErrors, clearFieldError, ignoreTripId = null } = {}) {
    let debounceTimer = null;

    function resolveIgnoreTripId() {
        const value = unref(ignoreTripId);

        return value ? String(value) : null;
    }

    async function applyWindowValidation() {
        if (!form.vehicle_id || !form.start_at) {
            clearFieldError?.('start_at');
            clearFieldError?.('vehicle_id');
            clearFieldError?.('driver_id');

            return true;
        }

        const result = await checkTripWindowOverlap({
            vehicleId: form.vehicle_id,
            driverId: form.driver_id || null,
            startAt: form.start_at,
            endAt: form.end_at || null,
            ignoreTripId: resolveIgnoreTripId(),
        });

        const conflictErrors = tripWindowConflictErrors(result);

        if (Object.keys(conflictErrors).length > 0) {
            if (fieldErrors) {
                fieldErrors.value = {
                    ...fieldErrors.value,
                    ...conflictErrors,
                };
            }

            return false;
        }

        clearFieldError?.('start_at');
        clearFieldError?.('vehicle_id');
        clearFieldError?.('driver_id');

        return true;
    }

    function scheduleWindowValidation() {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(() => {
            applyWindowValidation().catch(() => {});
        }, 350);
    }

    watch(
        () => [form.vehicle_id, form.driver_id, form.start_at, form.end_at, resolveIgnoreTripId()],
        scheduleWindowValidation,
    );

    onBeforeUnmount(() => {
        clearTimeout(debounceTimer);
    });

    return { applyWindowValidation };
}
