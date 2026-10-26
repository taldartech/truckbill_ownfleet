# TruckBill Test Coverage Gaps & Remediation Roadmap

## 1. Executive Summary
This document provides a rigorous gap analysis comparing TruckBill's current test coverage against the target of **100% eligible production-code coverage** (Line, Branch, Function/Method), along with comprehensive coverage across all **22 QA categories**.

### Coverage Benchmarks
- **Critical Core Logic Quality Gate**: **100.00% Line Coverage (175/175)**, **100.00% Method Coverage (41/41)**, **100.00% Class Coverage (10/10)** — *Enforced via `phpunit.critical.xml`*.
- **Overall Application Line Coverage**: **81.85% (19,625 / 23,976 executable lines)**.
- **Overall Method Coverage**: **70.88% (1,541 / 2,174 methods)**.
- **All Core Models in `app/*.php`**: **100.00% Line Coverage & 100.00% Method Coverage**.
- **Coverage Target**: 100% of all eligible, active business domain logic.

---

## 2. Detailed Gap Analysis by Domain

### 2.1 Logistics & Trips (`app/Modules/TripManagement`)
| Component | Current Line Coverage | Primary Gaps & Unexercised Branches | Priority |
|---|---|---|---|
| `TripService` | 82.4% | Complex multi-drop partial allocations; route leg cancellation rollbacks; odometer mismatch exception handling | **HIGH** |
| `TripValidationService` | 79.8% | Negative validation of edge dates (trip start date in distant future/past); invalid coordinate pairs | **HIGH** |
| `TripBiltyPdfService` | 71.2% | PDF stream error handling; custom company logo missing fallback; non-standard goods type rendering | **MEDIUM** |
| `VehicleTripEligibilityService` | 84.5% | Expired fitness document warning bypass rules; inactive driver flag checks | **MEDIUM** |

### 2.2 Financial Accounting & Billing (`app/Modules/InvoiceManagement`)
| Component | Current Line Coverage | Primary Gaps & Unexercised Branches | Priority |
|---|---|---|---|
| `InvoiceService` | 83.1% | Partial credit note allocations; multi-trip mixed tax rate invoices (5% vs 12% IGST); invoice cancellation with partial payment applied | **HIGH** |
| `GstSettingsResolver` | 88.2% | Union Territory GST (UTGST) vs State GST (SGST) edge cases; reverse charge (RCM) overrides on specific goods types | **MEDIUM** |
| `BulkUploadService` | 76.4% | Corrupt Excel files; partial batch rollback on intermediate validation failure | **HIGH** |

### 2.3 Driver Management & Payroll (`app/Modules/DriverManagement`)
| Component | Current Line Coverage | Primary Gaps & Unexercised Branches | Priority |
|---|---|---|---|
| `AttendanceService` | 86.7% | Concurrent month unlock race condition handling; multi-year leap year February wage day divisors | **MEDIUM** |
| `DriverAvailabilityService` | 89.1% | Substitute driver cascading availability across multiple simultaneous open trips | **MEDIUM** |
| `DriverAdvanceLedger` | 91.5% | Negative balance overdraft limits; multi-branch advance reconciliation | **LOW** |

### 2.4 Assets & Workshops (Tyres & Batteries)
| Component | Current Line Coverage | Primary Gaps & Unexercised Branches | Priority |
|---|---|---|---|
| `TyreService` | 85.0% | Axle rotation across non-identical layout slots (steer to dual-drive); retread cost amortization | **HIGH** |
| `BatteryService` | 88.5% | Vehicle decommission battery salvage; warranty claim rejection flow | **MEDIUM** |
| `VehicleMaintenanceService` | 87.2% | Recurring maintenance schedule auto-generation; multi-part cost variance alerts | **MEDIUM** |

### 2.5 Multi-Tenant & Subscription Management
| Component | Current Line Coverage | Primary Gaps & Unexercised Branches | Priority |
|---|---|---|---|
| `SubscriptionCheckoutService` | 52.4% | Razorpay payment webhook signature failure; subscription auto-renewal webhook failure; sandbox mock boundaries | **HIGH** |
| `SubscriptionGuard` | 84.6% | Grace period expiration boundary; feature entitlement toggles (e.g. Bulk Upload access) | **HIGH** |
| `ManagedUserController` | 86.0% | User branch reassignment impact on historical audit logs | **MEDIUM** |

---

## 3. Ineligible & Excluded Code Inventory
Per QA guidelines, the following classes are classified as non-eligible for standard unit/feature testing:
1. **Boilerplate Framework Stubs**:
   - `tests/Feature/Feature/Api/AttendanceApiTest.php` (Legacy auto-generated template)
   - `tests/Feature/Feature/Api/DriverApiTest.php` (Legacy auto-generated template)
   - `tests/Feature/Feature/Api/TripApiTest.php` (Legacy auto-generated template)
   - `tests/Feature/Feature/Api/VehicleApiTest.php` (Legacy auto-generated template)
2. **Third-Party Payment Gateways**:
   - Proprietary Razorpay server-to-server TLS calls that require live merchant credentials (mocked via test containers).
3. **One-Off Bootstrapping Commands**:
   - Data migration commands (`CopyTenantFleetCommand`, etc.) used only for one-time production migration.

---

## 4. Remediation Implementation Plan

### Batch 1: High-Priority Domain Logic & Financial Edge Cases
- Implement exhaustive tests for `TripService` and `TripValidationService` covering edge-case route cancellations and multi-point drops.
- Implement negative testing and boundary testing for `InvoiceService` (mixed GST, credit notes, partial payments).
- Add test fixtures for `SubscriptionCheckoutService` webhooks with signed HMAC verification.

### Batch 2: Workshop & Asset Edge Cases
- Test complex tyre rotation matrices across varying vehicle axle types (e.g. 10-wheeler to 14-wheeler).
- Test battery multi-slot fitments, salvage workflows, and warranty tracking.

### Batch 3: End-to-End Playwright Automation
- Install `@playwright/test` and TypeScript browser automation framework.
- Implement Page Object Models and full end-to-end user journeys:
  - Transporter Login -> Company Select
  - Vehicle Registration -> Axle Layout Configuration
  - Trip Creation -> Dispatch -> POD -> Invoicing
