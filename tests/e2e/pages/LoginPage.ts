import { Page, Locator, expect } from '@playwright/test';

export class LoginPage {
  readonly page: Page;
  readonly emailInput: Locator;
  readonly passwordInput: Locator;
  readonly loginButton: Locator;
  readonly companySelector: Locator;
  readonly branchSelector: Locator;
  readonly submitCompanyButton: Locator;
  readonly errorMessage: Locator;

  constructor(page: Page) {
    this.page = page;
    this.emailInput = page.locator('input[type="email"], input[name="email"]');
    this.passwordInput = page.locator('input[type="password"], input[name="password"]');
    this.loginButton = page.locator('button[type="submit"], button:has-text("Sign In"), button:has-text("Login")');
    this.companySelector = page.locator('select[name="company_id"], select#company_id');
    this.branchSelector = page.locator('select[name="branch_id"], select#branch_id');
    this.submitCompanyButton = page.locator('button:has-text("Select"), button:has-text("Continue")');
    this.errorMessage = page.locator('.alert-danger, [role="alert"]');
  }

  async goto() {
    await this.page.goto('/login');
  }

  async login(email: string, pass: string) {
    await this.emailInput.fill(email);
    await this.passwordInput.fill(pass);
    await this.loginButton.click();
  }

  async selectOperationalContext(companyId?: string, branchId?: string) {
    if (await this.companySelector.isVisible()) {
      if (companyId) {
        await this.companySelector.selectOption(companyId);
      }
      if (branchId && await this.branchSelector.isVisible()) {
        await this.branchSelector.selectOption(branchId);
      }
      await this.submitCompanyButton.click();
    }
  }

  async assertLoginErrorVisible() {
    await expect(this.errorMessage).toBeVisible();
  }
}
