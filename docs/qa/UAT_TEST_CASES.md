# TruckBill User Acceptance Testing (UAT) Test Cases

## 1. Executive Summary & Transporter Testing Objectives
This document defines end-to-end User Acceptance Testing (UAT) test cases designed specifically from the point of view of a **transporter/fleet owner and operational staff** (Dispatchers, Fleet Managers, and Accountants).

The test cases validate real-world operational workflows under actual transport operating conditions.

---

## 2. UAT Test Cases

### UAT-01: Onboarding New 14-Wheeler Heavy Truck with Custom Axle Layout & Fuel Capacity
- **Test ID**: `UAT-TRUCK-01`
- **User Role**: Fleet Owner (`admin`)
- **Objective**: Transporter purchases a 14-wheeler multi-axle truck and registers it with accurate payload and fuel specs.
- **Preconditions**:
  - Transporter logged into company portal with active Fleet Pro subscription.
  - Truck Type "14 Wheeler Multi-Axle" exists in system.
- **Step-by-Step Procedure**:
  1. Navigate to **Vehicles** -> **Add New Truck**.
  2. Enter Registration Number: `MH12AB9988`.
  3. Select Truck Type: `14 Wheeler Multi-Axle`.
  4. Select Tyre Layout: `14-Wheeler (1 Front Axle, 2 Rear Dual Axles, 1 Lift Axle)`.
  5. Enter Carrying Capacity: `25.5` Metric Tonnes.
  6. Enter Fuel Tank Capacity: `400` Litres.
  7. Assign Primary Operational Branch: `Pune Central Hub`.
  8. Click **Save Vehicle**.
- **Expected Result**:
  - Vehicle created with status `active`.
  - Registration number normalized to uppercase without whitespace.
  - Tyre layout visual axle slots initialized with all positions marked `empty`.
  - Vehicle shows up immediately on Fleet Dashboard.
- **Acceptance Criteria**: **PASS** if vehicle record persists, layout matches 14 wheels, and quota decrements by 1.

---

### UAT-02: Driver Assignment & Morning Operational Dispatch
- **Test ID**: `UAT-DRIVER-02`
- **User Role**: Dispatcher / Operational Manager (`moderator`)
- **Objective**: Assign an available driver to truck `MH12AB9988` for morning dispatch.
- **Preconditions**:
  - Truck `MH12AB9988` is idle without an active driver.
  - Driver `Ramesh Kumar` (Commercial HMV License) is active and unassigned.
- **Step-by-Step Procedure**:
  1. Navigate to **Drivers** -> **Driver Assignments**.
  2. Select Truck: `MH12AB9988`.
  3. Select Driver: `Ramesh Kumar`.
  4. Select Assignment Type: `Primary Driver`.
  5. Set Effective From: Today's date. Leave Effective To empty (open-ended).
  6. Click **Confirm Assignment**.
- **Expected Result**:
  - System verifies Ramesh Kumar has no overlapping open assignments.
  - Assignment saved; Truck `MH12AB9988` shows `Primary Driver: Ramesh Kumar`.
  - Driver availability search marks Ramesh Kumar as unavailable for other trucks.
- **Acceptance Criteria**: **PASS** if assignment is active and duplicate assignment to another truck is blocked.

---

### UAT-03: Multi-Drop Trip Dispatch & Delivery Confirmation
- **Test ID**: `UAT-TRIP-03`
- **User Role**: Dispatcher (`admin` / `moderator`)
- **Objective**: Create a multi-drop freight trip from Mumbai to Pune (Drop 1) and Satara (Drop 2).
- **Preconditions**:
  - Truck `MH12AB9988` with driver `Ramesh Kumar` available.
  - Customer `Tata Logistics Ltd` active.
- **Step-by-Step Procedure**:
  1. Navigate to **Trips** -> **Create Trip**.
  2. Select Truck `MH12AB9988` (Auto-populates driver Ramesh Kumar).
  3. Enter Start Odometer: `125,400 km`.
  4. Define Leg 1:
     - Origin: `Mumbai Hub` -> Destination: `Pune Depot`.
     - Cargo: `Automotive Parts` (12 MT), Freight Rate: `₹ 28,000`.
  5. Define Leg 2:
     - Origin: `Pune Depot` -> Destination: `Satara Warehouse`.
     - Cargo: `Industrial Goods` (8 MT), Freight Rate: `₹ 16,500`.
  6. Click **Dispatch Trip**.
  7. Upon arrival at Satara, click **Complete Trip** -> Enter Closing Odometer: `125,740 km`.
- **Expected Result**:
  - Trip created with unique auto-generated number (e.g. `TRIP-2026-0042`).
  - Total distance accurately calculated: $125,740 - 125,400 = 340\text{ km}$.
  - Both legs marked `delivered` and available in unbilled freight pool.
  - Truck status transitions from `on_trip` back to `available`.
- **Acceptance Criteria**: **PASS** if trip distance equals 340 km and unbilled legs appear in billing queue.

---

### UAT-04: Highway Fuel Fill & Mileage Variance Alert
- **Test ID**: `UAT-FUEL-04`
- **User Role**: Fleet Manager (`admin`)
- **Objective**: Log a diesel fill of 120 litres and verify fuel efficiency calculation and anomaly alert.
- **Preconditions**:
  - Trip `TRIP-2026-0042` completed (340 km run).
- **Step-by-Step Procedure**:
  1. Navigate to **Trips** -> Select `TRIP-2026-0042` -> **Add Fuel Expense**.
  2. Fuel Pump: `Indian Oil Express Highway`.
  3. Quantity: `120` Litres. Rate: `₹ 94.50 / Litre`. Total: `₹ 11,340`.
  4. Payment Mode: `Fleet Card / HPCL DriveTrack`.
  5. Click **Save Fuel Entry**.
- **Expected Result**:
  - Mileage calculated: $\frac{340\text{ km}}{120\text{ L}} = 2.83\text{ km/l}$.
  - Since standard expected mileage for 14-wheeler is $3.8\text{ km/l}$, variance is $-25.5\%$.
  - System flags a warning: `Abnormal Fuel Consumption: Variance > 15% below threshold`.
  - Fuel expense ₹ 11,340 automatically included in trip total expenses.
- **Acceptance Criteria**: **PASS** if km/l is 2.83 and high-variance alert is surfaced on dashboard.

---

### UAT-05: Fastag Monthly Bank Statement Automated Reconciliation
- **Test ID**: `UAT-FASTAG-05`
- **User Role**: Accountant (`accountant`)
- **Objective**: Import bank Fastag CSV statement and automatically assign toll charges to trips.
- **Preconditions**:
  - Bank statement CSV with toll transactions for truck `MH12AB9988` during the trip window.
- **Step-by-Step Procedure**:
  1. Navigate to **Trips** -> **Expense Statement Import**.
  2. Select Importer Type: `Fastag Toll Statement`.
  3. Upload CSV file (`fastag_october_2026.csv`).
  4. Click **Analyze File**. Verify column mapping suggestions (VRN, Date, Toll Plaza, Amount).
  5. Click **Generate Preview**.
  6. Verify toll matches `TRIP-2026-0042` (Mumbai-Pune Expressway Toll: ₹ 680).
  7. Click **Confirm & Import Expenses**.
- **Expected Result**:
  - System matches transaction timestamp with trip start/end date window.
  - Trip expense of ₹ 680 created under category `Toll (Fastag)`.
  - Re-uploading the same statement detects duplicate transaction ID and skips row.
- **Acceptance Criteria**: **PASS** if toll is attributed to `TRIP-2026-0042` with zero duplicates.

---

### UAT-06: Customer GST Freight Invoicing with TDS & RCM
- **Test ID**: `UAT-INVOICE-06`
- **User Role**: Accountant (`accountant`)
- **Objective**: Generate a GST tax invoice for completed legs of customer `Tata Logistics Ltd`.
- **Preconditions**:
  - Completed trip legs available for billing (Leg 1: ₹ 28,000, Leg 2: ₹ 16,500).
- **Step-by-Step Procedure**:
  1. Navigate to **Invoices** -> **Generate Bulk / Trip Invoice**.
  2. Select Customer: `Tata Logistics Ltd` (GSTIN: `27AAACT1234F1Z5`).
  3. Select Completed Trips / Legs: Select Leg 1 and Leg 2 (Subtotal: ₹ 44,500).
  4. Select GST Option: `Reverse Charge Mechanism (RCM Applicable)`.
  5. Select TDS Deductible: `TDS u/s 194C @ 1%` (₹ 445).
  6. Click **Generate Invoice**.
  7. From Invoice Detail, click **Download PDF (Bilty / Tax Invoice)**.
- **Expected Result**:
  - Invoice created with sequential number (e.g. `INV-2026-0105`).
  - RCM flag clearly printed; GST payable by recipient.
  - TDS deduction recorded; Net Receivable = ₹ 44,055.
  - Trip legs transition status from `delivered` to `invoiced`.
- **Acceptance Criteria**: **PASS** if invoice total is ₹ 44,500, legs are locked from re-billing, and PDF renders correctly.

---

### UAT-07: Tyre Mounting, Stint Tracking & Tread Wear Alert
- **Test ID**: `UAT-TYRE-07`
- **User Role**: Workshop / Tyre Manager (`admin`)
- **Objective**: Fit a brand new Apollo radial tyre to Front Steer Left position and monitor wear.
- **Preconditions**:
  - Tyre `APO-99120` in stock (Brand New, Initial Tread Depth: 15 mm).
  - Truck `MH12AB9988` Front Steer Left (`FSL`) slot currently empty.
- **Step-by-Step Procedure**:
  1. Navigate to **Tyres** -> **Tyre Fitment**.
  2. Select Vehicle: `MH12AB9988`.
  3. Select Slot: `Front Steer Left (FSL)`.
  4. Select Tyre: `APO-99120`.
  5. Enter Fitment Odometer: `125,400 km`.
  6. Click **Mount Tyre**.
  7. After 60 days, navigate to **Tyre Inspections** -> Record Tread Depth: `2.5 mm`.
- **Expected Result**:
  - Tyre status transitions from `in_stock` to `fitted`.
  - Axle layout diagram visually reflects tyre mounted at `FSL`.
  - Inspection recording 2.5 mm triggers `CRITICAL ALERT: Tread Depth below 3.0 mm minimum limit. Retread or Replace immediately`.
- **Acceptance Criteria**: **PASS** if tyre is mounted on vehicle, slot cannot accept another tyre, and low-tread alert is fired.

---

### UAT-08: Battery Slot Installation & Atomic Replacement
- **Test ID**: `UAT-BATTERY-08`
- **User Role**: Fleet Maintenance Manager (`admin`)
- **Objective**: Replace a failing battery in Slot 1 with a new Exide battery in an atomic transaction.
- **Preconditions**:
  - Truck `MH12AB9988` has old battery `EX-OLD-01` in Slot 1.
  - New battery `EX-NEW-02` in stock.
- **Step-by-Step Procedure**:
  1. Navigate to **Batteries** -> **Replace Battery**.
  2. Select Truck: `MH12AB9988`.
  3. Select Active Battery: `EX-OLD-01` (Slot 1).
  4. Select Removal Reason: `Failed / Weak Specific Gravity`.
  5. Select Action for Old Battery: `Scrap Battery` -> Scrap Value: `₹ 1,800`.
  6. Select Replacement Battery: `EX-NEW-02`.
  7. Click **Execute Battery Replacement**.
- **Expected Result**:
  - Old battery `EX-OLD-01` atomically uninstalled and marked `scrapped` with salvage credit ₹ 1,800.
  - New battery `EX-NEW-02` installed into Slot 1 with installation date and truck odometer logged.
  - Full audit trail recorded under `BatteryInspection` and `BatteryInstallation`.
- **Acceptance Criteria**: **PASS** if old battery is scrapped, new battery is active in Slot 1, and database remains consistent.

---

### UAT-09: Scheduled Preventive Maintenance & Work Order Closure
- **Test ID**: `UAT-MAINT-09`
- **User Role**: Fleet Maintenance Manager (`admin`)
- **Objective**: Record scheduled engine oil service and grease work for truck `MH12AB9988`.
- **Preconditions**:
  - Service type `Major Service (Engine Oil + Filters + Greasing)` configured with 15,000 km interval.
- **Step-by-Step Procedure**:
  1. Navigate to **Maintenance** -> **Add Work Order**.
  2. Select Vehicle: `MH12AB9988`.
  3. Service Type: `Major Service`.
  4. Odometer: `125,740 km`.
  5. Garage / Workshop: `National Truck Care Workshop, Pune`.
  6. Cost Breakdown: Parts: `₹ 8,500`, Labour: `₹ 1,500`, Total: `₹ 10,000`.
  7. Click **Complete Service Work Order**.
- **Expected Result**:
  - Work order recorded with status `completed`.
  - Truck `MH12AB9988` updates `last_service_odometer` to `125,740 km`.
  - Next service scheduled alert calculated for $125,740 + 15,000 = 140,740\text{ km}$.
  - ₹ 10,000 cost rolls into monthly vehicle maintenance expense report.
- **Acceptance Criteria**: **PASS** if last service odometer updates and next service due threshold advances by 15,000 km.

---

### UAT-10: End-of-Month Driver Payroll Closing, Advance Deductions & Salary Settlement
- **Test ID**: `UAT-PAYROLL-10`
- **User Role**: Accountant (`accountant`)
- **Objective**: Close monthly attendance for October 2026, compute net wages, deduct driver advances, and mark paid.
- **Preconditions**:
  - Driver `Ramesh Kumar`: Monthly Base Salary ₹ 20,000.
  - Attendance: 26 Present days, 4 Paid weekly offs (Total: 30 days).
  - Advance Ledger: Driver took ₹ 4,000 cash advance mid-month.
- **Step-by-Step Procedure**:
  1. Navigate to **Drivers** -> **Attendance & Settlements**.
  2. Select Month: `October 2026`.
  3. Click **Close Month & Generate Settlement**.
  4. Open Driver Line for `Ramesh Kumar`:
     - Verify Gross Salary: `₹ 20,000`.
     - Enter Advance Deducted: `₹ 4,000`.
     - Enter Good Mileage Bonus: `₹ 1,000`.
     - Net Payable computed: $20,000 - 4,000 + 1,000 = ₹ 17,000$.
  5. Click **Lock Month**.
  6. Click **Mark Salary Paid** -> Select Payment Mode: `Bank NEFT Transfer`.
- **Expected Result**:
  - Month status transitions to `locked` and `paid`.
  - Driver advance wallet reduced by ₹ 4,000.
  - Attendance records for October 2026 are frozen from further edits.
  - Settlement sheet exported to Excel matches verified figures.
- **Acceptance Criteria**: **PASS** if net pay equals ₹ 17,000, advance is deducted, and month is permanently locked.

---

### UAT-11: RTO Document Expiry Warning & EMI Loan Reminder
- **Test ID**: `UAT-COMPLIANCE-11`
- **User Role**: Fleet Owner (`admin`)
- **Objective**: Verify compliance engine alerts when truck National Permit is due to expire in 7 days.
- **Preconditions**:
  - Truck `MH12AB9988` National Permit expiry set to 7 days from today.
  - Active bank loan EMI of ₹ 45,000 due on 5th of next month.
- **Step-by-Step Procedure**:
  1. Trigger automated schedule or execute artisan commands:
     - `php artisan check:document-expiry`
     - `php artisan check:emi-due`
  2. Log in as Fleet Owner and view **Dashboard**.
  3. Navigate to **Compliance & Document Alerts**.
- **Expected Result**:
  - High-priority badge appears: `1 Document Expiring Soon (National Permit - MH12AB9988)`.
  - Notification logged in `AppNotification` table and email queued for fleet owner.
  - Upcoming EMI card displays `₹ 45,000 due on 5th (HDFC Bank Vehicle Loan)`.
- **Acceptance Criteria**: **PASS** if alert is surfaced on dashboard and automated notification is logged.

---

### UAT-12: Multi-Company Portfolio Performance Monitoring on SuperAdmin Dashboard
- **Test ID**: `UAT-SUPERADMIN-12`
- **User Role**: Transporter Group SuperAdmin (`super_admin`)
- **Objective**: Monitor consolidated operational and cash performance across 3 distinct transport companies.
- **Preconditions**:
  - Superadmin owns Company A (10 trucks), Company B (15 trucks), and Company C (5 trucks).
- **Step-by-Step Procedure**:
  1. Log in with `super_admin` credentials.
  2. Navigate to **SuperAdmin Executive Dashboard**.
  3. View **Consolidated Portfolio Stats**:
     - Total Fleet: 30 Trucks.
     - Trucks on Trip vs Idle.
     - Total Revenue, Total Direct Expenses, and Operating Margin %.
  4. Select Filter: Company B -> Verify stats narrow down strictly to Company B (15 trucks).
  5. Select Filter: Unauthorized Company X (owned by another user) -> Verify HTTP 403 Forbidden.
- **Expected Result**:
  - Consolidated portfolio view aggregates only owned companies.
  - Individual company filter reflects isolated company metrics.
  - Cross-tenant data tampering is strictly blocked by `SuperAdminPortfolioScope`.
- **Acceptance Criteria**: **PASS** if portfolio totals accurately aggregate owned companies without data leakage.
