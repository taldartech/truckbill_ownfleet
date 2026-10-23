# TruckBill Own Fleet — Comprehensive Test Strategy

## 1. Executive Summary & Purpose
This document defines the automated testing strategy, architectural standards, quality gates, and risk management guidelines for the **TruckBill Own Fleet** management application (`truckbill_ownfleet`).

The objective is to establish exhaustive verification across all operational, financial, compliance, and asset management domains, enforcing multi-tenant data isolation, business rule consistency, and high-confidence regression testing.

---

## 2. Functional Scope & Domain Audit Baseline
TruckBill Own Fleet is tailored for transport companies and asset-owning fleet operators. An audit of the codebase across the 17 standard functional domains yielded the following operational status:

| # | Functional Domain | Implementation Status | Primary Architecture Components |
|---|---|---|---|
| 1 | **Authentication & Authorization** | **Implemented** | Sanctum API, Session Guard, `UserRole`, `RoleMiddleware`, `CheckPermission` |
| 2 | **Tenant & Branch Management** | **Implemented** | `Tenant`, `Branch`, `TenantScoped`, `BranchScoped`, `EnsureValidTenant` |
| 3 | **Vehicle Registration & Lifecycle** | **Implemented** | `Vehicle`, `VehicleStatus`, `TruckTypeDefaults`, `TyreLayoutDefaults` |
| 4 | **Driver Management & Wages** | **Implemented** | `Driver`, `DriverAssignment`, `DriverWage`, `DriverAdvance`, `DriverSettlement` |
| 5 | **Trip Management & Multi-Leg Operations** | **Implemented** | `Trip`, `TripLeg`, `TripRoute`, `TripStatus`, `CompleteTripAction` |
| 6 | **Fuel Entries & Mileage Tracking** | **Implemented** | `FuelEntry`, `FuelCalculationService`, Odometer Tracking, Mileage Validation |
| 7 | **Trip Expenses & Settlements** | **Implemented** | `TripExpense`, `DriverAdvance`, Settlement Reconciliation, Direct vs Fleet Costs |
| 8 | **Customer & Broker Operations** | **Implemented** | `Customer`, `Broker`, Operational Billing & Freight Charges |
| 9 | **Tyre Management & Axle Lifecycle** | **Implemented** | `Tyre`, `TyrePosition`, `TyreFittingHistory`, `TyreRetreadingHistory`, Inspection |
| 10 | **Battery Management & Warranty** | **Implemented** | `Battery`, `BatteryInstallation`, `BatteryReplacementHistory`, Warranty Expiry |
| 11 | **Vehicle Maintenance & Work Orders** | **Implemented** | `WorkOrder`, `MaintenanceService`, `MaintenanceRecord`, Scheduled Intervals |
| 12 | **Spare Parts & Store Inventory** | **Planned / Not Implemented** | *Not present in current codebase; documented as external/future phase* |
| 13 | **Vehicle Documents & Compliance** | **Implemented** | `VehicleDocument`, `Challan`, `DocumentAlertNotifier`, Expiry Reminders |
| 14 | **Vehicle Loan & EMI Tracking** | **Implemented** | `Emi`, `CheckEmiDueCommand`, `EmiPolicy`, Bank/Lender Repayment Tracking |
| 15 | **Fleet Utilization & Profitability** | **Implemented** | `FleetReportService`, Profitability Analysis, Utilization KPIs, Downtime Reports |
| 16 | **Async Queues, Jobs & Scheduling** | **Implemented** | `SendComplianceReminderJob`, `CheckDocumentExpiryCommand`, Scheduled Tasks |
| 17 | **Multi-Tenant Isolation & Security** | **Implemented** | Global Scopes (`TenantScoped`, `BranchScoped`), IDOR guards, Policy verification |

---

## 3. Testing Principles & Methodologies

### 3.1 Strict Isolation & Data Safety
- All automated tests execute in an isolated environment using an in-memory SQLite database (`:memory:`) or dedicated test database.
- Tests must never touch production databases or credentials.
- Every test creates and isolates its own fixtures (Tenants, Branches, Users, Vehicles, Drivers, Trips) using factory helpers and database migrations.

### 3.2 Tenant Isolation Verification
Multi-tenancy is enforced via Eloquent global scopes (`TenantScoped`, `BranchScoped`) and middleware:
1. Every query generated within an authenticated context automatically scopes records to the active `tenant_id`.
2. Negative test cases explicitly test IDOR/BOLA attacks: Tenant B attempting to read, update, or delete vehicles, trips, fuel entries, tyres, batteries, or EMIs belonging to Tenant A must receive a `404 Not Found` or `403 Forbidden`.

### 3.3 Domain Logic & Financial Integrity
- Direct trip expenses vs. vehicle-level maintenance/asset costs must be strictly separated.
- Calculations (odometer differences, fuel mileage, cost-per-km, driver net settlements) are validated using independently calculated expected values.
- Atomic operations and database transactions (`DB::transaction`) are tested against simulated failures to ensure no partial state is persisted.

### 3.4 Accounting Add-on Boundary
- Operational fleet invoice creation, expense logging, and payment tracking are standalone features of Own Fleet.
- Advanced double-entry general ledger, balance sheets, and trial balances are part of the separate Accounting add-on and are not coupled to core fleet workflows.

---

## 4. Test Pyramid & Distribution

```
           / \
          /   \     E2E / Browser Flows (Livewire / Web SPA)
         /-----\
        /       \    Feature & Integration Tests (HTTP, Controllers, API)
       /---------\
      /           \   Unit Tests (Services, Calculations, Actions, Policies)
     /-------------\
```

1. **Unit Tests (`tests/Unit/`)**:
   - Focus: Pure domain algorithms, policy logic, value objects, math calculations.
   - Examples: `FuelCalculationServiceTest`, `TyreAxleConfigTest`, `EmiPolicyTest`.
2. **Feature & API Tests (`tests/Feature/`)**:
   - Focus: Controller actions, middleware execution, validation rules, HTTP status codes, database state mutations.
   - Examples: `TripLifecycleTest`, `FuelEntryApiTest`, `ComplianceAndEmiTest`, `VehicleManagementTest`.
3. **Command & Scheduled Job Tests**:
   - Focus: CLI tasks executed by cron (e.g., `compliance:check-emi-due`, `compliance:check-document-expiry`), verifying queue dispatching without UI triggers.

---

## 5. Coverage Goals & Quality Gates

| Metric | Target | Verified Result | Status |
|---|---|---|---|
| **Test Suite Pass Rate** | 100% | **100.00% (662 / 662 Passed)** | **Met (0 Failures)** |
| **Failures / Errors** | 0 | **0** | **Met** |
| **Critical Core Logic Line Coverage** | 100% | **100.00% (175/175 lines)** | **VERIFIED 100% GATE** |
| **Critical Core Method Coverage** | 100% | **100.00% (41/41 methods)** | **VERIFIED 100% GATE** |
| **Critical Core Class Coverage** | 100% | **100.00% (10/10 classes)** | **VERIFIED 100% GATE** |
| **Overall Application Line Coverage** | >75% | **78.19% (18,748/23,977 lines)** | **Met** |
| **Cross-Tenant IDOR Vulnerability** | 0% tolerance | **0 leaks detected** | **Met** |

---

## 6. Execution Protocols

### Fast Development Run
```bash
php -d memory_limit=512M ./vendor/bin/phpunit --testdox
```

### Critical Core Logic Quality Gate (100% Required)
```bash
php -d memory_limit=512M -d pcov.enabled=1 -d pcov.directory=app ./vendor/bin/phpunit -c phpunit.critical.xml --coverage-text
```

### Full Application Suite
```bash
php -d memory_limit=512M ./vendor/bin/phpunit
```

### Full Coverage Generation
```bash
php -d memory_limit=512M -d pcov.enabled=1 -d pcov.directory=app ./vendor/bin/phpunit --coverage-text --coverage-html coverage-html
```

