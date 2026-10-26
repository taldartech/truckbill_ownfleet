# TruckBill Test Data Setup & Fixture Engineering Guide

## 1. Executive Summary & Zero Production Data Policy
TruckBill enforces a strict **Zero Production Data Policy** across all automated tests, developer environments, and CI/CD pipelines.

Under no circumstances is real transporter, customer, financial, or vehicle data used in testing. All test scenarios operate exclusively on deterministic, synthetically generated fixtures engineered via Laravel Model Factories and Database Seeders.

---

## 2. Test Execution Context & Isolation

### 2.1 Ephemeral In-Memory Database
All automated PHPUnit and Feature tests execute against an ephemeral, in-memory SQLite database:
```ini
DB_CONNECTION=sqlite
DB_DATABASE=:memory:
```
Every test case uses Laravel's `Illuminate\Foundation\Testing\RefreshDatabase` trait, ensuring a pristine schema migration and zero residual data bleed between tests.

### 2.2 Operational Context Binding
TruckBill uses custom Eloquent global scopes (`TenantScoped`, `BranchScoped`). To test operational logic seamlessly, `Tests\TestCase` exposes operational helpers:
```php
$user = User::factory()->create([
    'tenant_id' => $tenant->id,
    'branch_id' => $branch->id,
    'user_role_id' => $adminRole->id,
]);

// Automatically binds TenantContext and BranchContext to test process:
$this->applyUserOperationalContext($user);
```

---

## 3. Core Model Factories Inventory

### 3.1 Tenant & Branch Fixtures
- **`Tenant::factory()`**:
  - `id`: Valid UUID v4.
  - `name`: Fake company name (e.g. `Balaji Freight Carriers Pvt Ltd`).
  - `company_pan`: Valid Indian PAN format (`[A-Z]{5}[0-9]{4}[A-Z]`).
  - `gstin`: Valid Indian GSTIN format (`27AAAAA0000A1Z5`).
  - `is_active`: `true`.
  - `document_color`: Hex color string (`#1E40AF`).
- **`Branch::factory()`**:
  - `tenant_id`: Associated tenant UUID.
  - `name`: Branch name (e.g. `Head Office - Mumbai`, `Depot - Pune`).
  - `contact_number`: Unique 10-digit mobile number.
  - `is_head_office`: Boolean flag.

### 3.2 Vehicle & Fleet Fixtures
- **`Vehicle::factory()`**:
  - `registration_number`: Regex-generated Indian registration plate (e.g. `MH12AB1234`, `GJ06CD5678`).
  - `truck_type_id`: Linked `TruckType` ID.
  - `tyre_layout_id`: Linked `TyreLayout` ID.
  - `carrying_capacity`: Decimal metric tonnes (e.g. `16.50`).
  - `fuel_tank_capacity_ltr`: Integer litres (e.g. `350`).
  - `is_active`: `true`.
  - `status`: `available` (or `on_trip`, `maintenance`).

### 3.3 Driver & Attendance Fixtures
- **`Driver::factory()`**:
  - `name`: Full driver name.
  - `mobile_number`: Unique 10-digit phone number.
  - `license_number`: Commercial driving license string (`MH12 20180012345`).
  - `salary_monthly`: Base wage integer (e.g. `22000`).
  - `status`: `active`.
- **`Attendance::factory()`**:
  - `date`: YYYY-MM-DD.
  - `status`: Enum (`present`, `absent`, `leave`, `substitute`).
  - `attendance_marked_via`: Enum (`manual`, `system`, `trip`).

### 3.4 Logistics & Trip Fixtures
- **`Trip::factory()`**:
  - `trip_number`: Auto-generated sequential string (`TRIP-2026-0001`).
  - `vehicle_id`, `driver_id`: Assigned fleet resources.
  - `start_odometer`: Base integer (e.g. `100000`).
  - `close_odometer`: Null or greater than start odometer.
  - `status`: `pending`, `in_transit`, `completed`, or `invoiced`.
- **`TripRouteLeg::factory()`**:
  - `trip_id`: Associated trip.
  - `origin_station_id`, `destination_station_id`: Linked stations.
  - `goods_type_id`: Linked goods classification.
  - `freight_amount`: Decimal INR (e.g. `35000.00`).
  - `delivery_status`: `pending`, `in_transit`, `delivered`.

### 3.5 Asset Management Fixtures (Tyres & Batteries)
- **`Tyre::factory()`**:
  - `serial_number`: Unique manufacturer barcode (`TYR-998877`).
  - `brand`: Apollo, MRF, CEAT, Michelin.
  - `initial_tread_depth_mm`: Decimal (e.g. `15.0`).
  - `current_tread_depth_mm`: Decimal (e.g. `12.5`).
  - `status`: `in_stock`, `fitted`, `scrapped`.
- **`Battery::factory()`**:
  - `serial_number`: Unique battery barcode (`BAT-112233`).
  - `brand`: Exide, Amaron, Tata Green.
  - `voltage`: `12V` or `24V`.
  - `capacity_ah`: `150Ah` or `180Ah`.
  - `status`: `in_stock`, `installed`, `scrapped`.

---

## 4. Master Data Seeders & Baselines
TruckBill includes comprehensive seeders that populate static industry master data:
1. **`TruckTypeSeeder`**:
   - `Mini Truck` (Capacity: 1.5 MT)
   - `LCV / Pick-up` (Capacity: 3.5 MT)
   - `MCV 6-Wheeler` (Capacity: 9.0 MT)
   - `ICV 10-Wheeler` (Capacity: 16.0 MT)
   - `Multi-Axle 14-Wheeler` (Capacity: 25.0 MT)
   - `Trailer 22-Wheeler` (Capacity: 40.0 MT)
2. **`GoodsTypeSeeder`**:
   - `FMCG & Consumer Goods`
   - `Steel, Metals & Heavy Industrial`
   - `Agricultural Produce & Grains`
   - `Automotive Components & Machinery`
   - `Chemicals & Petroleum Products`
   - `E-Commerce & Parcel Cargo`
3. **`DocumentTypeSeeder`**:
   - `Registration Certificate (RC)` (Renewal: 15 Years)
   - `National Permit (Form 47/48)` (Renewal: 1 Year)
   - `Commercial Vehicle Insurance` (Renewal: 1 Year)
   - `Fitness Certificate` (Renewal: 1 Year)
   - `Pollution Under Control (PUC)` (Renewal: 6 Months)
   - `Road Tax Token` (Renewal: Quarterly / Annual)

---

## 5. Test State Preparation Helpers

### 5.1 Deterministic Date Travel
Tests involving document expirations, monthly payroll closing, and subscription proration use Carbon date travel:
```php
// Freeze time at specific date:
Carbon::setTestNow('2026-10-15 10:00:00');

// Run workflow...

// Reset time after test:
Carbon::setTestNow();
```

### 5.2 Storage Disk & File Upload Mocking
Tests verifying vehicle RC upload, driver license scans, and company logos use fake storage:
```php
Storage::fake('public');

$uploadedFile = UploadedFile::fake()->image('vehicle_rc.jpg', 800, 600);
```

### 5.3 Automated Fastag Statement Generation
For testing automated statement imports:
```php
$csvContent = "VRN,Transaction Date,Toll Plaza,Amount\n";
$csvContent .= "MH12AB1234,2026-10-15 14:30:00,Khed-Shivapur Toll,180.00\n";
$csvContent .= "MH12AB1234,2026-10-15 18:45:00,Anewadi Toll,140.00\n";

$file = UploadedFile::fake()->createWithContent('fastag.csv', $csvContent);
```
