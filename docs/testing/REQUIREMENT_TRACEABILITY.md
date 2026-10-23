# TruckBill Own Fleet — Requirement-to-Test Traceability Matrix

## 1. Traceability Overview
This matrix maps the 17 business requirement domains defined in the master prompt to their corresponding implementation files, test case identifiers, automated test files, and verification status.

---

## 2. Requirement Traceability Matrix

| Req ID | Domain / Module | Core Functional Requirement | Implementation Class / Route | Automated Test Reference | Test Status |
|---|---|---|---|---|---|
| **REQ-AUTH-01** | Authentication & Roles | Multi-guard authentication (Sanctum/Session), role assignment, permissions | `UserRole`, `RoleMiddleware`, `CheckPermission` | `tests/Feature/Auth/`, `tests/Feature/Api/RolePermissionTest.php` | **PASS** |
| **REQ-AUTH-02** | Branch Access | Scoping data and user permissions to designated operating branches | `BranchScoped`, `BranchController` | `tests/Feature/BranchScopedTest.php` | **PASS** |
| **REQ-TENANT-01**| Multi-Tenancy | Automatic data isolation across distinct transport companies | `TenantScoped`, `EnsureValidTenant` | `tests/Feature/TenantIsolationTest.php`, `ComplianceAndEmiTest.php` | **PASS** |
| **REQ-VEH-01** | Vehicle Registration | Vehicle creation with truck types, operational attributes, tyre layouts | `Vehicle`, `TruckTypeDefaults`, `TyreLayoutDefaults` | `tests/Feature/VehicleTest.php`, `VehicleRegistrationTest.php` | **PASS** |
| **REQ-VEH-02** | Vehicle Status | Operational status transitions (Active, In-Shop, Idle, Breakdown) | `VehicleStatus`, `UpdateVehicleStatusAction` | `tests/Feature/VehicleStatusTransitionTest.php` | **PASS** |
| **REQ-DRV-01** | Driver Management | Driver profiles, license validity, assignment to vehicles/trips | `Driver`, `DriverAssignment` | `tests/Feature/DriverTest.php`, `DriverAssignmentTest.php` | **PASS** |
| **REQ-DRV-02** | Driver Wages & Advances| Driver wage calculations, trip advances, settlements | `DriverWage`, `DriverAdvance`, `DriverSettlement` | `tests/Feature/DriverWageAndSettlementTest.php` | **PASS** |
| **REQ-TRIP-01** | Trip Creation & Legs | Route definitions, multi-leg pickups, dropoffs, freight charges | `Trip`, `TripLeg`, `TripRoute` | `tests/Feature/TripLifecycleTest.php` | **PASS** |
| **REQ-TRIP-02** | Trip Completion | Odometer recording, trip completion workflow, cost rollups | `CompleteTripAction`, `TripStatus` | `tests/Feature/TripCompletionTest.php` | **PASS** |
| **REQ-FUEL-01** | Fuel Entry & Costs | Litres, rates, total cost calculation, fuel station logging | `FuelEntry`, `FuelCalculationService` | `tests/Feature/FuelEntryTest.php` | **PASS** |
| **REQ-FUEL-02** | Mileage & Odometer | Fuel efficiency calculation, distance, mileage variance detection | `FuelCalculationService` | `tests/Unit/FuelCalculationServiceTest.php` | **PASS** |
| **REQ-EXP-01** | Trip Expenses | Direct trip expenses vs. vehicle overhead, advance deductions | `TripExpense`, `DriverAdvance` | `tests/Feature/TripExpenseTest.php` | **PASS** |
| **REQ-CUST-01** | Customers & Brokers | Operational billing records, freight rate tracking | `Customer`, `Broker` | `tests/Feature/CustomerBrokerTest.php` | **PASS** |
| **REQ-TYRE-01** | Tyre Axle Positions | Fitting, removing, rotating tyres across specified axles | `Tyre`, `TyrePosition`, `TyreFittingHistory` | `tests/Feature/TyreManagementTest.php` | **PASS** |
| **REQ-TYRE-02** | Tyre Retreading & Cost| Retreading history, scrap recording, cost per kilometre | `TyreRetreadingHistory`, `ScrapTyreAction` | `tests/Feature/TyreRetreadingTest.php` | **PASS** |
| **REQ-BATT-01** | Battery Lifecycle | Battery installation, vehicle assignments, warranty expiries | `Battery`, `BatteryInstallation` | `tests/Feature/BatteryLifecycleTest.php` | **PASS** |
| **REQ-MAINT-01**| Maintenance & Repair | Work orders, scheduled service intervals, workshop job cards | `WorkOrder`, `MaintenanceRecord` | `tests/Feature/MaintenanceTest.php` | **PASS** |
| **REQ-INVT-01** | Spare Parts & Store | Stock receipts, store movements, part consumption | *Not Implemented in Codebase* | N/A (Planned feature) | **BLOCKED** |
| **REQ-COMP-01** | Vehicle Documents | Fitness, permits, insurance, road tax tracking & expiry alerts | `VehicleDocument`, `CheckDocumentExpiryCommand`| `tests/Feature/VehicleDocumentTest.php` | **PASS** |
| **REQ-COMP-02** | Challans & Fines | Traffic violation tracking, payment status, audit logging | `Challan`, `ChallanPaymentStatus` | `tests/Feature/Api/ComplianceAndEmiTest.php` | **PASS** |
| **REQ-EMI-01** | Loan & EMI Tracking | Vehicle EMI schedules, due dates, lender tracking | `Emi`, `ComplianceController`, `EmiPolicy` | `tests/Feature/Api/ComplianceAndEmiTest.php` | **PASS** |
| **REQ-EMI-02** | EMI Due Reminders | Scheduled CLI reminder dispatching to ops team | `CheckEmiDueCommand`, `SendComplianceReminderJob`| `tests/Feature/Api/ComplianceAndEmiTest.php` | **PASS** |
| **REQ-REP-01** | Fleet Reports & KPIs | Vehicle profitability, fuel efficiency, idle vehicle reports | `FleetReportService` | `tests/Feature/FleetReportTest.php` | **PASS** |
| **REQ-SEC-01** | Multi-Tenant IDOR | Preventing unauthorized cross-tenant data manipulation | `TenantScoped`, `BranchScoped` | `tests/Feature/TenantIsolationTest.php`, `ComplianceAndEmiTest.php` | **PASS** |

