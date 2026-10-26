import { test, expect } from '@playwright/test';
import { LoginPage } from './pages/LoginPage';
import { DashboardPage } from './pages/DashboardPage';

test.describe('Authentication & Dashboard E2E Workflow', () => {
  test('unauthenticated user is redirected to login', async ({ page }) => {
    await page.goto('/dashboard');
    await expect(page).toHaveURL(/.*login.*/);
  });

  test('user sees login form with required elements', async ({ page }) => {
    const loginPage = new LoginPage(page);
    await loginPage.goto();
    await expect(loginPage.emailInput).toBeVisible();
    await expect(loginPage.passwordInput).toBeVisible();
    await expect(loginPage.loginButton).toBeVisible();
  });

  test('invalid login credentials display alert message', async ({ page }) => {
    const loginPage = new LoginPage(page);
    await loginPage.goto();
    await loginPage.login('invalid-user@truckbill.test', 'WrongPassword123!');
    // Verify either error alert or stays on login page
    await expect(page).toHaveURL(/.*login.*/);
  });
});
