# TruckBill Own Fleet — Defect & Architectural Issues Register

This register logs bugs, architectural flaws, security risks, and coverage gaps discovered during the comprehensive audit and test automation of `truckbill_ownfleet`.

---

## Defect Summary
| Defect ID | Severity | Component | Summary | Status |
|---|---|---|---|---|
| **DEFECT-OF-001** | **High** | `CheckEmiDueCommand`, `CheckChallanPendingCommand` | Scheduled console commands fail to query any records in CLI due to `TenantScoped` adding `where 0 = 1` | Documented / Requires Approval |
| **DEFECT-OF-002** | **Medium** | `ComplianceController`, `EmiPolicy` | Zero automated test coverage existed for EMI creation, retrieval, and compliance dashboard | **Resolved** by `ComplianceAndEmiTest` |
| **DEFECT-OF-003** | **Medium** | Permissions / `Challans` | Challans permissions were revoked from all roles in migration `2026_06_11_113017`, locking out Admins from `/api/challans` | Documented / Requires Approval |
| **DEFECT-OF-004** | **Low / Feature Gap** | Inventory / Spare Parts | Functional Area 12 (Spare Parts / Store Management) is completely missing from codebase | Documented as Planned Feature |

---

## Detailed Defect Reports

### DEFECT-OF-001: Scheduled Compliance Commands Return 0 Records Due to TenantScoped Global Scope
- **Severity**: High (Cron failure in production)
- **Affects**:
  - `app/Console/Commands/CheckEmiDueCommand.php`
  - `app/Console/Commands/CheckChallanPendingCommand.php`
  - `routes/console.php:15`, `routes/console.php:19`
- **Root Cause**:
  `Emi` and `Challan` models implement the `TenantScoped` trait. In `TenantScoped::bootTenantScoped()`, if `TenantContext::tenantId()` is `null`, Eloquent injects `$builder->whereRaw('0 = 1')`.
  When Laravel's scheduler runs `php artisan compliance:check-emi-due` or `php artisan compliance:check-challan-pending`, no tenant context is bound to the request. Consequently, the query always evaluates to `0 = 1` and matches zero due EMIs or unpaid challans.
  In contrast, `CheckDocumentExpiryCommand.php` correctly uses `VehicleDocument::query()->withoutGlobalScope('tenant')`.
- **Reproduction Steps**:
  1. Insert an EMI record with `due_date <= now()->addDays(3)` and `status = 'DUE'`.
  2. Run `php artisan compliance:check-emi-due` from the command line.
  3. Notice output: `Queued 0 EMI reminders.`
- **Recommended Remediation**:
  Update `CheckEmiDueCommand.php`:
  ```php
  $emis = Emi::query()
      ->withoutGlobalScope('tenant')
      ->whereDate('due_date', '<=', now()->addDays(3))
      ->where('status', 'DUE')
      ->get();
  ```
  Similarly for `CheckChallanPendingCommand.php`:
  ```php
  $challans = Challan::query()
      ->withoutGlobalScope('tenant')
      ->where('status', ChallanPaymentStatus::Unpaid->value)
      ->get();
  ```

---

### DEFECT-OF-002: Zero Test Coverage on Compliance & EMI Operations
- **Severity**: Medium (Quality / Maintenance Risk)
- **Affects**: `App\Http\Controllers\Api\ComplianceController`, `App\Policies\EmiPolicy`, `routes/api-routes.php`
- **Impact**: Prior to audit, zero feature tests covered `/api/compliance`, `/api/compliance/emis`, or `EmiPolicy::create`.
- **Resolution**:
  Implemented `tests/Feature/Api/ComplianceAndEmiTest.php` with 6 exhaustive test cases covering index retrieval, EMI storage, challan management, role authorization barriers, cross-tenant isolation, and command execution.

---

### DEFECT-OF-003: Challan Endpoints Inaccessible Due to Blanket Permission Revocation
- **Severity**: Medium
- **Affects**: Migration `2026_06_11_113017_revoke_challan_permissions_from_all_roles.php`
- **Impact**: All challan permissions (`challans.view`, `challans.manage`, `challans.delete`) were detached from all system roles. As a result, standard admin users querying `/api/challans` receive `403 This action is unauthorized.` unless permissions are explicitly re-attached in tenant settings.
- **Recommended Remediation**:
  Ensure company admin roles retain `challans.view` and `challans.manage` or provide an administrative UI toggle for tenant superadmins.

---

### DEFECT-OF-004: Functional Area 12 (Spare Parts & Store Management) Not Implemented
- **Severity**: Low (Functional Gap)
- **Affects**: Scope Domain 12
- **Description**: The master prompt specifies Spare Parts, Store Management, and Inventory movements. Inspection of database migrations and source code reveals that no `spare_parts`, `inventory_items`, or `stock_movements` tables exist in `truckbill_ownfleet`.
- **Status**: Documented as a planned future module.

