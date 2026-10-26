# TruckBill Own Fleet — Master Test Case Inventory

This inventory catalogs all high-priority test cases covering core functional areas, vehicle lifecycles, trips, fuel, maintenance, compliance, multi-tenant security, and financial integrity.

---

## Master Inventory Table

### 1. Authentication, Roles & Tenant Isolation
| Test ID | Module | Req ID | Objective & Scenario | Type & Priority | Preconditions | Steps | Expected Result | Actual / Status | Automation Ref |
|---|---|---|---|---|---|---|---|---|---|
| **TC-SEC-001** | Multi-Tenancy | REQ-TENANT-01 | Prevent cross-tenant vehicle data access | Security / P0 | 2 distinct tenants (A & B) with vehicles | 1. Authenticate as Tenant B<br>2. Request `GET /api/vehicles/{tenant_A_vehicle_id}` | HTTP 404 / 403 Forbidden; no data leak | PASS | `tests/Feature/TenantIsolationTest.php` |
| **TC-SEC-002** | Multi-Tenancy | REQ-SEC-01 | Prevent cross-tenant EMI manipulation | Security / P0 | Tenant A has an EMI created | 1. Authenticate as Tenant B<br>2. Send `GET /api/compliance` | Response does not contain Tenant A's EMI | PASS | `ComplianceAndEmiTest::test_cross_tenant_isolation_on_compliance_and_emis` |
| **TC-AUTH-001**| RBAC | REQ-AUTH-01 | Moderator without `emis.manage` cannot store EMI | Security / P1 | Moderator user without permissions | 1. Authenticate as Moderator<br>2. POST `/api/compliance/emis` | HTTP 403 Forbidden | PASS | `ComplianceAndEmiTest::test_moderator_cannot_view_or_create_compliance_or_emis` |
| **TC-AUTH-002**| RBAC | REQ-AUTH-01 | Moderator without `compliance.view` cannot view dashboard | Security / P1 | Moderator user | 1. Authenticate as Moderator<br>2. GET `/api/compliance` | HTTP 403 Forbidden | PASS | `ComplianceAndEmiTest::test_moderator_cannot_view_or_create_compliance_or_emis` |

---

### 2. Vehicle Registration & Lifecycle
| Test ID | Module | Req ID | Objective & Scenario | Type & Priority | Preconditions | Steps | Expected Result | Actual / Status | Automation Ref |
|---|---|---|---|---|---|---|---|---|---|
| **TC-VEH-001** | Vehicles | REQ-VEH-01 | Create vehicle with operational attributes & tyre layout | Functional / P0 | Active tenant with seeded defaults | 1. Seed `TyreLayoutDefaults`<br>2. Create vehicle with registration number | Vehicle persisted with status `ACTIVE`, correct layout attached | PASS | `tests/Feature/VehicleTest.php` |
| **TC-VEH-002** | Vehicles | REQ-VEH-02 | Transition vehicle status from ACTIVE to IN_SHOP | Functional / P1 | Active vehicle | 1. Dispatch update vehicle status action<br>2. Change status to `IN_SHOP` | Status updated; audit history recorded | PASS | `tests/Feature/VehicleStatusTransitionTest.php` |
| **TC-VEH-003** | Vehicles | REQ-VEH-01 | Validate Indian registration number format | Unit / P2 | Form request validation | 1. Submit invalid format 'XYZ123'<br>2. Submit valid format 'MH12AB1234' | Invalid rejected; valid accepted | PASS | `IndianVehicleRegistrationNumberTest.php` |

---

### 3. Trip Management & Odometer Tracking
| Test ID | Module | Req ID | Objective & Scenario | Type & Priority | Preconditions | Steps | Expected Result | Actual / Status | Automation Ref |
|---|---|---|---|---|---|---|---|---|---|
| **TC-TRIP-001**| Trips | REQ-TRIP-01 | Create multi-leg trip with pickups and routes | Functional / P0 | Active vehicle and assigned driver | 1. POST `/api/trips`<br>2. Provide route legs, freight, driver | Trip created with unique trip number and `PLANNED` status | PASS | `tests/Feature/TripLifecycleTest.php` |
| **TC-TRIP-002**| Trips | REQ-TRIP-02 | Complete trip and validate final odometer >= start odometer | Functional / P0 | In-transit trip with start odo = 10,000 | 1. Post completion with end odo = 9,500<br>2. Post completion with end odo = 10,800 | 1. Validation error (end < start)<br>2. Trip status marked `COMPLETED` | PASS | `tests/Feature/TripCompletionTest.php` |

---

### 4. Fuel & Mileage Tracking
| Test ID | Module | Req ID | Objective & Scenario | Type & Priority | Preconditions | Steps | Expected Result | Actual / Status | Automation Ref |
|---|---|---|---|---|---|---|---|---|---|
| **TC-FUEL-001**| Fuel | REQ-FUEL-01 | Calculate fuel expense from litres and rate | Unit / P0 | Valid numerical inputs | 1. Provide litres = 120.50, rate = 95.00 | Total cost = 11,447.50 | PASS | `tests/Unit/FuelCalculationServiceTest.php` |
| **TC-FUEL-002**| Fuel | REQ-FUEL-02 | Calculate vehicle mileage across consecutive fuel entries | Functional / P0 | Completed trips with consecutive fills | 1. Calculate distance (km) / total litres | Mileage matches expected km/l; flag variance if outside threshold | PASS | `tests/Feature/FuelEntryTest.php` |

---

### 5. Tyre & Battery Asset Management
| Test ID | Module | Req ID | Objective & Scenario | Type & Priority | Preconditions | Steps | Expected Result | Actual / Status | Automation Ref |
|---|---|---|---|---|---|---|---|---|---|
| **TC-TYRE-001**| Tyres | REQ-TYRE-01 | Assign tyre to valid axle position | Functional / P1 | Unmounted tyre and active vehicle | 1. Mount tyre to Front-Left (FL)<br>2. Attempt to mount to already occupied FL | 1. Mount succeeds; status `MOUNTED`<br>2. Fails with conflict error | PASS | `tests/Feature/TyreManagementTest.php` |
| **TC-TYRE-002**| Tyres | REQ-TYRE-02 | Log retreading event and calculate cost-per-km | Functional / P2 | Tyre with 40,000 km | 1. Post retreading record with cost<br>2. Increment retread count | Total cost updated; lifecycle status refreshed | PASS | `tests/Feature/TyreRetreadingTest.php` |
| **TC-BATT-001**| Battery | REQ-BATT-01 | Battery warranty expiration tracking | Functional / P1 | Battery with warranty date | 1. Query battery dashboard stats | Correctly flags battery as active or warranty expired | PASS | `tests/Feature/BatteryLifecycleTest.php` |

---

### 6. Vehicle Maintenance & Work Orders
| Test ID | Module | Req ID | Objective & Scenario | Type & Priority | Preconditions | Steps | Expected Result | Actual / Status | Automation Ref |
|---|---|---|---|---|---|---|---|---|---|
| **TC-MAINT-001**| Maintenance | REQ-MAINT-01 | Create vehicle work order and record service cost | Functional / P1 | Vehicle in need of repair | 1. Open work order<br>2. Add labour cost and parts<br>3. Close work order | Vehicle maintenance record persisted; status restored | PASS | `tests/Feature/MaintenanceTest.php` |

---

### 7. Compliance, Challans & Loan/EMI Tracking
| Test ID | Module | Req ID | Objective & Scenario | Type & Priority | Preconditions | Steps | Expected Result | Actual / Status | Automation Ref |
|---|---|---|---|---|---|---|---|---|---|
| **TC-EMI-001** | EMI | REQ-EMI-01 | Store vehicle EMI with bank and due date | Functional / P0 | Vehicle registered | 1. POST `/api/compliance/emis`<br>2. Provide lender, amount, due date | EMI record created with status `DUE` and UUID | PASS | `ComplianceAndEmiTest::test_admin_can_store_and_retrieve_emi` |
| **TC-EMI-002** | EMI | REQ-EMI-02 | Scheduled CLI command queues reminders for due EMIs | Integration / P0 | EMIs due in 2 days, 10 days, and PAID | 1. Run `compliance:check-emi-due` | `SendComplianceReminderJob` pushed exactly for due EMI | PASS | `ComplianceAndEmiTest::test_check_emi_due_command_dispatches_reminders_only_for_due_emis` |
| **TC-CHAL-001**| Challans | REQ-COMP-02 | Create, update and soft-delete traffic challan | Functional / P1 | Vehicle and driver registered | 1. POST `/api/challans`<br>2. PUT update status<br>3. DELETE with reason | Challan lifecycle tracked; soft delete records `deleted_reason` | PASS | `ComplianceAndEmiTest::test_admin_can_store_and_manage_challan` |
| **TC-COMP-001**| Compliance | REQ-COMP-01 | Retrieve compliance index aggregation | Functional / P0 | Active vehicle with challans and EMIs | 1. GET `/api/compliance` | Returns JSON with challans, emis, documents, alert counters | PASS | `ComplianceAndEmiTest::test_admin_can_view_compliance_index_when_permissions_exist` |

