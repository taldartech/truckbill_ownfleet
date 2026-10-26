import { Page, Locator, expect } from '@playwright/test';

export class VehiclePage {
  readonly page: Page;
  readonly addVehicleButton: Locator;
  readonly registrationInput: Locator;
  readonly truckTypeSelect: Locator;
  readonly capacityInput: Locator;
  readonly fuelTankCapacityInput: Locator;
  readonly tyreLayoutSelect: Locator;
  readonly submitButton: Locator;
  readonly searchInput: Locator;
  readonly vehicleTableRows: Locator;
  readonly toastSuccess: Locator;

  constructor(page: Page) {
    this.page = page;
    this.addVehicleButton = page.locator('button:has-text("Add Truck"), a:has-text("Add Vehicle")');
    this.registrationInput = page.locator('input[name="vehicle_number"], input[name="registration_number"]');
    this.truckTypeSelect = page.locator('select[name="truck_type_id"]');
    this.capacityInput = page.locator('input[name="carrying_capacity"]');
    this.fuelTankCapacityInput = page.locator('input[name="fuel_tank_capacity_ltr"]');
    this.tyreLayoutSelect = page.locator('select[name="tyre_layout_id"]');
    this.submitButton = page.locator('button[type="submit"]:has-text("Save"), button:has-text("Create")');
    this.searchInput = page.locator('input[placeholder*="Filter"], input[name="q"]');
    this.vehicleTableRows = page.locator('table tbody tr');
    this.toastSuccess = page.locator('.toast-success, .alert-success');
  }

  async goto() {
    await this.page.goto('/trucks');
  }

  async createVehicle(data: {
    registrationNumber: string;
    capacity?: string;
    fuelTankCapacity?: string;
  }) {
    await this.addVehicleButton.click();
    await this.registrationInput.fill(data.registrationNumber);
    if (data.capacity) {
      await this.capacityInput.fill(data.capacity);
    }
    if (data.fuelTankCapacity) {
      await this.fuelTankCapacityInput.fill(data.fuelTankCapacity);
    }
    await this.submitButton.click();
  }
}
