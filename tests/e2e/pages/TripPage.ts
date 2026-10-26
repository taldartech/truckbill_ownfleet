import { Page, Locator, expect } from '@playwright/test';

export class TripPage {
  readonly page: Page;
  readonly createTripButton: Locator;
  readonly vehicleSelect: Locator;
  readonly driverSelect: Locator;
  readonly originStationSelect: Locator;
  readonly destinationStationSelect: Locator;
  readonly freightRateInput: Locator;
  readonly startOdometerInput: Locator;
  readonly submitTripButton: Locator;
  readonly tripStatusBadge: Locator;

  constructor(page: Page) {
    this.page = page;
    this.createTripButton = page.locator('a:has-text("New Trip"), button:has-text("Create Trip")');
    this.vehicleSelect = page.locator('select[name="vehicle_id"]');
    this.driverSelect = page.locator('select[name="driver_id"]');
    this.originStationSelect = page.locator('select[name="origin_station_id"]');
    this.destinationStationSelect = page.locator('select[name="destination_station_id"]');
    this.freightRateInput = page.locator('input[name="freight_amount"], input[name="rate"]');
    this.startOdometerInput = page.locator('input[name="start_odometer"]');
    this.submitTripButton = page.locator('button[type="submit"]:has-text("Dispatch"), button:has-text("Save Trip")');
    this.tripStatusBadge = page.locator('.badge-status, [data-testid="trip-status"]');
  }

  async goto() {
    await this.page.goto('/trips');
  }
}
