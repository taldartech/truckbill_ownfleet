# TruckBill Business Workflow Matrix & Transporter Operational Architecture

## 1. Executive Summary & Purpose
This document provides a comprehensive mapping of **real-world transporter and fleet owner operations** to the technical implementation inside the TruckBill platform. 

Every workflow represents a critical operational path, documenting:
- Transporter business objective and trigger
- Domain models and database tables involved
- Validation constraints and financial business rules
- Security, authorization, and multi-tenant scoping
- Required testing categories, edge cases, and verification assertions

---

## 2. Business Workflow Inventory & Technical Mapping

### Workflow 1: Fleet Onboarding & Vehicle Lifecycle Management
- **Transporter Need**: Register trucks (owned or attached), configure carrying capacity, fuel tank volume, axle layout, and assign to specific operational branches.
- **Trigger**: New truck purchased or leased into fleet.
- **Technical Components**:
  - Models: `App\Vehicle`, `App\TruckType`, `App\TyreLayout`, `App\Branch`
  - Controller: `App\Http\Controllers\Api\VehicleController`
  - Requests: `StoreVehicleRequest`, `UpdateVehicleRequest`, `DeleteVehicleRequest`
  - Policies: `VehiclePolicy`
  - Scoping: `TenantScoped`, `BranchScoped`
- **Business Rules & Calculations**:
  - Vehicle registration number normalized and validated (Indian format e.g. `DL01AB1234`).
  - Active status defaults to `active`.
  - Soft-deletions require a mandatory audit reason (`deleted_reason`).
  - Vehicle quotas enforced against `TenantSubscription.max_vehicles`.
- **Edge Cases & Test Assertions**:
  - Attempting to add a truck exceeding subscription limit returns HTTP 422 with upgrade guidance.
  - Deleting an active truck with ongoing trips is prevented.
  - Cross-tenant vehicle duplicate prevention while allowing unique restoration.

---

### Workflow 2: Driver Onboarding, Daily Attendance & Monthly Settlements
- **Transporter Need**: Manage drivers, track daily assignments to trucks, mark daily attendance (Present, Absent, Leave, Substitute), compute monthly wages with overtime/substitute adjustments, record advances, and settle salaries.
- **Trigger**: Daily morning dispatch; end-of-month payroll closing.
- **Technical Components**:
  - Models: `App\Driver`, `App\DriverAssignment`, `App\Attendance`, `App\MonthlyDriverWage`, `App\DriverAdvanceEntry`
  - Services: `AttendanceService`, `MonthlySettlementService`, `DriverAvailabilityService`
  - Controller: `App\Http\Controllers\Api\AttendanceController`, `DriverController`
  - Scoping: `TenantScoped`, `BranchScoped`
- **Business Rules & Calculations**:
  - Driver cannot be primary on two trucks concurrently during overlapping date ranges.
  - Monthly Settlement Formula:
    $$\text{Gross Salary} = (\text{Present Days} + \text{Paid Leaves}) \times \text{Daily Rate} + \text{Substitute Wages} + \text{Trip Bonus}$$
    $$\text{Net Salary} = \text{Gross Salary} - (\text{Advance Deducted} + \text{Diesel Penalty} + \text{Challan Deductions})$$
  - Once a month is locked (`AttendanceMonthLock`), attendances and assignments become read-only.
- **Edge Cases & Test Assertions**:
  - Backdated driver assignment overlapping with an existing bounded assignment is rejected.
  - Substitute driver working on Truck B receives credit while Primary driver on Truck B has payroll adjusted.
  - Advance entries ledger balance accurately updates across debit and credit transactions.

---

### Workflow 3: Trip Creation, Route Legs & Logistics Execution
- **Transporter Need**: Create trips with single or multi-leg routes, assign trucks and drivers, capture start odometer, specify cargo/goods type, track delivery milestones, and close trips upon completion.
- **Trigger**: Customer freight booking confirmed; truck dispatched.
- **Technical Components**:
  - Models: `App\Trip`, `App\TripRouteLeg`, `App\TripPickup`, `App\Station`, `App\Customer`, `App\Broker`
  - Services: `TripService`, `TripValidationService`, `VehicleTripEligibilityService`
  - Controller: `App\Http\Controllers\Api\TripController`
  - Scoping: `TenantScoped`, `BranchScoped`
- **Business Rules & Calculations**:
  - Trip statuses: `pending` -> `in_transit` -> `completed` -> `invoiced` / `cancelled`.
  - Closing odometer must be $\ge$ starting odometer.
  - Total distance $= \text{Closing Odometer} - \text{Starting Odometer}$.
  - A truck already on an open active trip cannot be assigned to another trip without closing the prior trip.
- **Edge Cases & Test Assertions**:
  - Multi-drop trips validate that all intermediate drop points have valid goods weight and delivery status.
  - Cancelling a trip with invoiced route legs is blocked until invoices are cancelled.
  - Vehicle compliance check warns if fitness/insurance is expired before dispatch.

---

### Workflow 4: Fuel Entries, Mileage & Variance Detection
- **Transporter Need**: Track diesel fills (litres, total cost, fuel station, payment mode), calculate truck mileage (km/l), and flag abnormal consumption or fuel theft.
- **Trigger**: Driver refuels at highway pump or depot.
- **Technical Components**:
  - Models: `App\TripExpense`, `App\Trip`
  - Support: `App\Modules\TripManagement\Support\TripFuelCalculator`
  - Scoping: `TenantScoped`, `BranchScoped`
- **Business Rules & Calculations**:
  - Fuel Mileage Formula:
    $$\text{Mileage (km/l)} = \frac{\text{Total Trip Kilometers}}{\text{Total Litres Filled}}$$
  - Variance Alert: Triggered when actual km/l deviates by $\ge 15\%$ from vehicle standard expected mileage.
  - Total fuel cost is automatically rolled into trip total direct expenses.
- **Edge Cases & Test Assertions**:
  - Fuel quantity exceeding fuel tank capacity triggers validation warning.
  - Fuel entry without valid odometer logs zero mileage variance cleanly without division-by-zero exception.

---

### Workflow 5: Trip Expenses & Automated Fastag Statement Reconciliation
- **Transporter Need**: Capture en-route expenses (tolls, loading/unloading, police/en-route petty cash), and import Fastag toll statements via CSV/Excel to automatically map tolls to active trips.
- **Trigger**: Driver submits expense vouchers; monthly Fastag statement downloaded from bank portal.
- **Technical Components**:
  - Models: `App\TripExpense`, `App\TripExpenseTypeMaster`
  - Services: `FastagStatementImporter`, `TripExpenseImportService`
  - Controller: `App\Http\Controllers\Api\TripExpenseImportController`
  - Scoping: `TenantScoped`, `BranchScoped`
- **Business Rules & Calculations**:
  - Toll transactions matched to trips based on vehicle registration number and transaction timestamp falling within the trip start and end datetime window.
  - Unmatched transactions flagged for manual transporter reconciliation.
  - Prevents duplicate toll expense creation for the same transaction ID.
- **Edge Cases & Test Assertions**:
  - Importing corrupt or malformed CSV rows fails gracefully with row-level error reports.
  - Toll transaction outside any active trip window is recorded as standalone fleet expense.

---

### Workflow 6: Customer Freight Billing, GST, Invoicing & Receivables
- **Transporter Need**: Generate GST-compliant freight tax invoices for completed trips/legs, manage bulk multi-trip billing, track customer advances and TDS deductions, and record payment receipts.
- **Trigger**: Trip proof of delivery (POD) acknowledged; customer billing cycle.
- **Technical Components**:
  - Models: `App\Invoice`, `App\InvoiceItem`, `App\InvoicePayment`, `App\Customer`
  - Services: `InvoiceService`, `GstSettingsResolver`
  - Controller: `App\Http\Controllers\Api\InvoiceController`, `ChallanController`
  - Scoping: `TenantScoped`, `BranchScoped`
- **Business Rules & Calculations**:
  - Tax Calculation:
    - Intra-state transaction: CGST ($2.5\%$) + SGST ($2.5\%$) under Reverse Charge (RCM) or Forward Charge.
    - Inter-state transaction: IGST ($5.0\%$ or $12.0\%$).
  - Net Receivable:
    $$\text{Balance Due} = \text{Total Invoice Amount} - (\text{Advance Paid} + \text{TDS Deducted} + \text{Shortage Deductions})$$
  - Sequential invoice numbering per financial year per branch (`INV-YYYY-XXXX`).
- **Edge Cases & Test Assertions**:
  - Attempting to bill already invoiced route legs returns validation error.
  - Cancelling an invoice unbills the associated trip legs and resets trip status to completed.
  - Recording full payment transitions status to `paid`; partial payment transitions status to `partially_paid`.

---

### Workflow 7: Tyre Lifecycle & Axle Layout Management
- **Transporter Need**: Track every tyre from purchase to scrap, configure vehicle axle layouts (e.g. 10-wheeler, 14-wheeler dual axle), track mounting/dismounting, monitor tread depth, calculate cost-per-km (CPKM), and manage retreading.
- **Trigger**: Tyre purchase, fitment to truck, periodic inspection, or blow-out replacement.
- **Technical Components**:
  - Models: `App\Tyre`, `App\TyreInstallation`, `App\TyreReplacement`, `App\TyreInspection`, `App\TyreLayout`
  - Services: `TyreService`, `TyreLifeCalculator`, `TyreAlertService`
  - Controller: `App\Http\Controllers\Api\TyreController`
  - Scoping: `TenantScoped`, `BranchScoped`
- **Business Rules & Calculations**:
  - Tyre status: `in_stock` -> `fitted` -> `in_retread` -> `scrapped`.
  - Cost Per Kilometer:
    $$\text{CPKM} = \frac{\text{Purchase Price} + \sum \text{Retreading Costs}}{\text{Total Cumulative Kilometers Run}}$$
  - Minimum tread depth alert triggered when tread depth $\le 3\text{ mm}$.
- **Edge Cases & Test Assertions**:
  - Cannot install a tyre into a position that already has an active mounted tyre.
  - Cannot install a tyre with fitment date earlier than tyre purchase date.
  - Scrapped tyres are permanently locked from modifications.

---

### Workflow 8: Battery Asset Management & Multi-slot Fitments
- **Transporter Need**: Track heavy vehicle batteries, manage dual-battery setups (24V systems), track warranty expiry dates, record maintenance/acid inspections, and record battery replacements.
- **Trigger**: Battery purchase, slot installation, scheduled voltage inspection, or replacement.
- **Technical Components**:
  - Models: `App\Battery`, `App\BatteryInstallation`, `App\BatteryReplacement`, `App\BatteryInspection`
  - Services: `BatteryService`
  - Controller: `App\Http\Controllers\Api\BatteryController`
  - Scoping: `TenantScoped`, `BranchScoped`
- **Business Rules & Calculations**:
  - Vehicle supports multiple distinct battery slots (Slot 1, Slot 2).
  - Battery replacement atomically removes current battery, marks it `in_stock` or `scrapped`, and fits replacement battery in a single database transaction.
- **Edge Cases & Test Assertions**:
  - Attempting to install an already fitted battery onto another vehicle throws 422 Unprocessable Entity.
  - Cannot scrap a battery while it is currently installed on an active truck.

---

### Workflow 9: Vehicle Preventive Maintenance & Work Orders
- **Transporter Need**: Schedule oil changes, brake overhaul, engine servicing, and grease work based on kilometer intervals or calendar days, track garage work orders, and record parts and labour costs.
- **Trigger**: Odometer milestone reached (e.g. every 15,000 km for engine oil) or scheduled service due.
- **Technical Components**:
  - Models: `App\VehicleMaintenance`, `App\MaintenanceServiceType`
  - Services: `VehicleMaintenanceService`, `MaintenanceDueStatus`
  - Controller: `App\Http\Controllers\Api\VehicleMaintenanceController`
  - Scoping: `TenantScoped`, `BranchScoped`
- **Business Rules & Calculations**:
  - Maintenance due status computed dynamically:
    - `OVERDUE` if $\text{Current KM} \ge \text{Last Service KM} + \text{Interval KM}$.
    - `DUE_SOON` if within $1,000\text{ km}$ or 7 days of threshold.
    - `OK` otherwise.
- **Edge Cases & Test Assertions**:
  - Completing a maintenance record updates the vehicle's `last_service_odometer`.
  - Maintenance costs roll into vehicle monthly expenditure and utilization analytics.

---

### Workflow 10: RTO Compliance, Expiring Document Alerts & EMI Reminders
- **Transporter Need**: Never let a truck get seized on the highway due to expired Fitness, National Permit, Insurance, Tax, or Pollution (PUC); track monthly truck loan EMIs and avoid bank bounce penalties.
- **Trigger**: Daily automated scheduler; document upload.
- **Technical Components**:
  - Models: `App\VehicleDocument`, `App\DocumentType`, `App\Emi`, `App\Challan`
  - Commands: `CheckDocumentExpiryCommand`, `CheckEmiDueCommand`, `CheckChallanPendingCommand`
  - Controller: `App\Http\Controllers\Api\VehicleDocumentController`, `ComplianceController`
  - Scoping: `TenantScoped`, `BranchScoped`
- **Business Rules & Calculations**:
  - Alerts generated at 30 days, 15 days, 7 days, and 1 day prior to expiration.
  - Traffic challan tracking: records violation date, penalty amount, driver involved, and payment status (`pending`, `paid`).
  - EMI schedule checks monthly due date and lender payment details.
- **Edge Cases & Test Assertions**:
  - Soft-deleting a driver preserves the driver name reference on historical challans.
  - Expired document triggers warning in vehicle dispatch eligibility check.

---

### Workflow 11: Multi-Tenant Subscriptions, Vehicle Caps & Branch Quotas
- **Transporter Need**: Flexible subscription plans (Starter, Growth, Fleet Pro) with quotas on active trucks and branches; seamless billing renewals and superadmin multi-company portfolio monitoring.
- **Trigger**: Company registration; fleet expansion; subscription renewal.
- **Technical Components**:
  - Models: `App\Tenant`, `App\Models\TenantSubscription`, `App\Models\SubscriptionPayment`, `App\Branch`
  - Services: `SubscriptionAccessService`, `SubscriptionGuard`, `SubscriptionPricingService`
  - Middleware: `EnsureActiveSubscription`, `EnsureSuperAdmin`
  - Scoping: `SuperAdminPortfolioScope`
- **Business Rules & Calculations**:
  - Tier limits strictly enforced on truck creation and branch addition.
  - Expired subscriptions lock data mutation endpoints (POST/PUT/DELETE return HTTP 402/403) while permitting read-only access for reporting and tax compliance.
- **Edge Cases & Test Assertions**:
  - Prorated billing calculates exact remaining days when upgrading subscription mid-cycle.
  - Superadmin dashboard aggregates cash flow and fleet count across all managed companies without cross-tenant bleed.
