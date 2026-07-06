export function calculateTyreStintKm(kmAtInstallation, currentOdometer) {
    const atInstall = Math.max(0, Number(kmAtInstallation) || 0);
    const current = Math.max(0, Number(currentOdometer) || 0);

    return Math.max(0, Math.round((current - atInstall) * 100) / 100);
}

export function resolveVehicleOdometer(vehicles, vehicleId) {
    const vehicle = vehicles.find((row) => String(row.id) === String(vehicleId));

    return vehicle?.odometer_km != null ? Number(vehicle.odometer_km) : 0;
}
