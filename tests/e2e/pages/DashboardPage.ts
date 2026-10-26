import { Page, Locator, expect } from '@playwright/test';

export class DashboardPage {
  readonly page: Page;
  readonly activeTrucksCard: Locator;
  readonly inTransitTripsCard: Locator;
  readonly complianceAlertsBanner: Locator;
  readonly quickSearchInput: Locator;
  readonly userDropdown: Locator;
  readonly logoutButton: Locator;

  constructor(page: Page) {
    this.page = page;
    this.activeTrucksCard = page.locator('[data-testid="stat-active-trucks"], .stat-active-trucks');
    this.inTransitTripsCard = page.locator('[data-testid="stat-in-transit"], .stat-in-transit');
    this.complianceAlertsBanner = page.locator('[data-testid="compliance-alerts"], .compliance-alerts');
    this.quickSearchInput = page.locator('input[placeholder*="Search"], input[name="search"]');
    this.userDropdown = page.locator('#user-menu, [data-testid="user-menu"]');
    this.logoutButton = page.locator('button:has-text("Logout"), a:has-text("Sign Out")');
  }

  async goto() {
    await this.page.goto('/dashboard');
  }

  async performQuickSearch(query: string) {
    await this.quickSearchInput.fill(query);
    await this.page.keyboard.press('Enter');
  }

  async logout() {
    if (await this.userDropdown.isVisible()) {
      await this.userDropdown.click();
    }
    await this.logoutButton.click();
  }
}
