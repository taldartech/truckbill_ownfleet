# TruckBill Testing Architecture & QA Automation Framework

## 1. Executive Summary & Testing Philosophy
TruckBill's quality assurance framework is designed to provide complete, deterministic verification of the transport management platform without compromising data safety or production integrity.

The framework enforces:
1. **Zero Production Mutation Safety**: All automated tests run on ephemeral in-memory SQLite instances (`:memory:`). Live databases, external client records, and production credentials are never contacted.
2. **Deterministic Reproducibility**: Tests use strictly seeded synthetic factories, isolated timestamps (`Carbon::setTestNow()`), and isolated storage disks (`Storage::fake()`).
3. **Multi-Layer Verification**:
   - **Unit Tests**: Pure arithmetic, policies, enum transitions, formatters, and domain rules.
   - **Feature & API Tests**: Request validation, controller execution, database assertions, multi-tenant scoping, and role authorization.
   - **Integration Tests**: Complex cross-module flows (e.g. Trip Completion -> Invoice Generation -> Driver Settlement).
   - **End-to-End (E2E) Browser Tests**: Playwright TypeScript tests simulating transporter real-browser interactions.

---

## 2. Test Execution Environments & Drivers

### 2.1 PHP Test Runner (PHPUnit 11)
- **Framework**: PHPUnit 11.5 on PHP 8.4.21 runtime.
- **Configuration Files**:
  - `phpunit.xml`: Master test configuration targeting complete test suite.
  - `phpunit.critical.xml`: Focused quality gate targeting 100% coverage on core settlement calculations, policies, and financial commands.
- **Database Connection**:
  ```xml
  <env name="DB_CONNECTION" value="sqlite"/>
  <env name="DB_DATABASE" value=":memory:"/>
  ```
- **Context Isolation Trait**:
  Tests inherit `Tests\TestCase`, utilizing helper methods to automatically establish authenticated sessions with valid tenant and branch contexts:
  ```php
  $this->applyUserOperationalContext($user);
  // Internally sets TenantContext::setTenantId($user->tenant_id)
  // and BranchContext::setBranchId($user->branch_id)
  ```

### 2.2 Coverage Measurement Engine (Xdebug 3.5 / PCOV)
- **Engine**: Xdebug v3.5.3 / PCOV 1.0.12 extension loaded into PHP 8.4 CLI.
- **Coverage Command**:
  ```bash
  php -d xdebug.mode=coverage vendor/bin/phpunit --coverage-text --coverage-html coverage-html
  ```
- **Code Coverage Scope**: Analyzes all executable lines in `app/`, filtering out third-party vendors and auto-generated boilerplate.

---

## 3. Playwright E2E Browser Testing Architecture

### 3.1 Technology Stack
- **Framework**: `@playwright/test`
- **Language**: TypeScript (`.ts`)
- **Configuration**: `playwright.config.ts`
- **Browsers**: Chromium (Desktop Chrome), WebKit (Safari), Firefox

### 3.2 Page Object Model (POM) Design
Tests are structured into maintainable Page Object classes located in `tests/e2e/pages/`:
1. `LoginPage.ts`:
   - Handles email/password authentication.
   - Validates multi-company selection dropdown.
   - Verifies active session token storage.
2. `DashboardPage.ts`:
   - Validates executive KPI cards (Active Trucks, In-transit Trips, Pending Invoices).
   - Verifies compliance document expiry alert badges.
3. `VehiclePage.ts`:
   - Fills vehicle registration, truck type, capacity, and tyre layout.
   - Asserts successful creation toast and table row insertion.
4. `TripPage.ts`:
   - Defines route legs (origin station, destination station, freight rate, goods type).
   - Assigns available truck and driver.
   - Submits trip and verifies status transitions (`pending` -> `in_transit` -> `completed`).
5. `InvoicePage.ts`:
   - Selects unbilled completed trips/legs for customer.
   - Generates GST freight invoice.
   - Verifies computed CGST, SGST, IGST, and net balance due.

---

## 4. Multi-Tenant & Security Testing Protocols

### 4.1 Tenant & Branch Isolation (IDOR Defense)
For every API endpoint, tests must verify:
- **Same Tenant, Same Branch**: Authorized user succeeds with 200/201.
- **Same Tenant, Different Branch**: For branch-scoped models (e.g. `Trip`, `DriverAssignment`), user from Branch A cannot view or mutate Branch B data.
- **Different Tenant**: Complete isolation; queries return HTTP 404 Not Found or HTTP 403 Forbidden. Eloquent global scope prevents data leakage.

### 4.2 Role-Based Access Control (RBAC) Matrix
Tests execute the identical payload under multiple roles:
- `super_admin`: Full system access across all owned tenants and billing.
- `admin`: Full operational and financial control within tenant.
- `moderator`: Operational view/create rights; destructive mutations (DELETE/UPDATE) restricted.
- `accountant`: Financial access (Invoices, Expenses, Payments, Settlements); fleet modifications restricted.
- `driver`: Read-only access to assigned trips.

---

## 5. Mocking Strategies & External Services
To ensure fast, deterministic, offline execution, third-party boundaries are mocked:
- **Payment Gateway (Razorpay)**: Mocked via service container bindings in `SubscriptionCheckoutServiceTest`.
- **SMS / WhatsApp Alerts**: Mocked using Laravel's `Notification::fake()`.
- **Background Jobs & Commands**: Verified using `Queue::fake()` and `Event::fake()`.
- **Document & PDF Generation**: `Storage::fake('public')` for bill-of-lading (Bilty) and invoice PDFs.

---

## 6. Continuous Integration (CI/CD) Workflow
The CI pipeline is automated via GitHub Actions in `.github/workflows/ci.yml`:
1. **PHP Quality Gate**:
   - Checkout repository.
   - Set up PHP 8.4 with required extensions (SQLite, Xdebug/PCOV, BCMath).
   - Install Composer dependencies.
   - Execute PHPUnit test suite.
   - Verify 100% Critical Core coverage gate.
2. **Browser E2E Gate**:
   - Set up Node.js runtime.
   - Install npm dependencies and Playwright browsers (`npx playwright install --with-deps`).
   - Run Laravel web server in background.
   - Execute Playwright test suite and upload trace artifacts.
