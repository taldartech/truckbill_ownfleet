import { Page, Locator, expect } from '@playwright/test';

export class InvoicePage {
  readonly page: Page;
  readonly generateInvoiceButton: Locator;
  readonly customerSelect: Locator;
  readonly tripCheckboxList: Locator;
  readonly gstModeSelect: Locator;
  readonly totalAmountDisplay: Locator;
  readonly saveInvoiceButton: Locator;
  readonly invoiceTableRows: Locator;

  constructor(page: Page) {
    this.page = page;
    this.generateInvoiceButton = page.locator('button:has-text("Generate Invoice"), a:has-text("Create Invoice")');
    this.customerSelect = page.locator('select[name="customer_id"]');
    this.tripCheckboxList = page.locator('input[type="checkbox"][name="trip_ids[]"]');
    this.gstModeSelect = page.locator('select[name="gst_mode"]');
    this.totalAmountDisplay = page.locator('[data-testid="invoice-total"]');
    this.saveInvoiceButton = page.locator('button[type="submit"]:has-text("Save Invoice")');
    this.invoiceTableRows = page.locator('table tbody tr');
  }

  async goto() {
    await this.page.goto('/invoices');
  }
}
