# TruckBill Repository Audit & Codebase Architecture

## 1. Executive Summary & Environment Specification
- **Application Target**: `truckbill_ownfleet` (Comprehensive Transport, Fleet & Billing Management System)
- **Framework**: Laravel 12.44.0 on PHP 8.4.21 runtime (Platform target: PHP 8.3)
- **Frontend Architecture**: Vue 3.5 SPA with Vite 6.0 and TailwindCSS 4.1
- **Database Engine**: MySQL/PostgreSQL in production; In-memory SQLite for automated testing suite
- **Multi-Tenancy Engine**: Custom Scoped Multi-Tenancy (`TenantScoped`, `BranchScoped`, `SuperAdminPortfolioScope`)
- **Key Modules**: Fleet Assets, Drivers & Payroll, Trip Logistics, Fuel & Fastag Accounting, Invoicing & GST, Tyre & Battery Asset Tracking, Vehicle Maintenance, RTO Compliance & EMI Reminders, Subscriptions

---

## 2. Architectural Overview & Scoping Rules

### 2.1 Multi-Tenancy Architecture
The system enforces strict multi-tenancy at two primary layers:
1. **Tenant (Company) Scoping**:
   - Implemented via `App\Concerns\TenantScoped` trait.
   - Enforced automatically using Eloquent global scopes checking `App\Support\TenantContext::getTenantId()`.
   - If `TenantContext` is unset or null, a safeguard `0 = 1` condition is appended to all queries to prevent unintended data exposure across tenants.
2. **Branch Scoping**:
   - Implemented via `App\Concerns\BranchScoped` trait.
   - Enforced through `App\Support\BranchContext::getBranchId()`.
   - Select models (e.g. `Customer`, `Broker`, `Station`) are shared across branches within a tenant, while operational records (e.g. `Trip`, `DriverAssignment`, `Vehicle`) are branch-scoped.
3. **SuperAdmin Portfolio Scope**:
   - Implemented via `App\Support\SuperAdminPortfolioScope` allowing platform superadmins to view consolidated portfolio metrics across multiple owned companies.

### 2.2 Role-Based Access Control (RBAC) & Authorization
- **Roles**: `super_admin`, `admin`, `moderator`, `accountant`, `operational_manager`
- **Role Hierarchy**: Governed by `App\Support\RoleHierarchy`
- **Granular Permissions**: Defined in `App\Support\PermissionDefinitions` with dynamic alias resolution via `App\Support\PermissionAliasResolver`.
- **Policy Enforcement**: Handled via 28 dedicated Eloquent policies in `app/Policies/`.

---

## 3. Eloquent Models Inventory (60 Models)

| Model Class | Database Table | Key Type | Fillable Columns | Active Traits | Scopes |
|---|---|---|---|---|---|
| `App\AppNotification` | `app_notifications` | `string` | 9 | BranchScoped, TenantScoped, UsesUuid | - |
| `App\Attendance` | `attendances` | `string` | 17 | BranchScoped, TenantScoped, UsesUuid | - |
| `App\AttendanceMonthLock` | `attendance_month_locks` | `string` | 9 | BranchScoped, TenantScoped, UsesUuid | - |
| `App\Battery` | `batteries` | `string` | 21 | BranchScoped, SoftDeletes, TenantScoped, UsesUuid | inStock, installed, underInspection, warrantyExpiring |
| `App\BatteryInspection` | `battery_inspections` | `string` | 11 | BranchScoped, TenantScoped, UsesUuid | - |
| `App\BatteryInstallation` | `battery_installations` | `string` | 14 | BranchScoped, TenantScoped, UsesUuid | - |
| `App\BatteryReplacement` | `battery_replacements` | `string` | 11 | BranchScoped, TenantScoped, UsesUuid | - |
| `App\Branch` | `branches` | `int` | 15 | SoftDeletes | - |
| `App\BranchSetting` | `branch_settings` | `int` | 13 | - | - |
| `App\Broker` | `brokers` | `int` | 13 | SoftDeletes, TenantScoped | - |
| `App\Challan` | `challans` | `string` | 11 | BranchScoped, SoftDeletes, TenantScoped, UsesUuid | - |
| `App\City` | `cities` | `int` | 2 | - | - |
| `App\Country` | `countries` | `int` | 2 | - | - |
| `App\Customer` | `customers` | `int` | 14 | SoftDeletes, TenantScoped | - |
| `App\DocumentAlertSetting` | `document_alert_settings` | `string` | 5 | BranchScoped, TenantScoped | - |
| `App\DocumentType` | `document_types` | `int` | 6 | TenantScoped | - |
| `App\Driver` | `drivers` | `string` | 20 | BranchScoped, SoftDeletes, TenantScoped, UsesUuid | - |
| `App\DriverAdvanceEntry` | `driver_advance_entries` | `string` | 8 | BranchScoped, TenantScoped, UsesUuid | - |
| `App\DriverAssignment` | `driver_assignments` | `string` | 9 | BranchScoped, TenantScoped, UsesUuid | overlappingPrimaryPeriod, activePrimaryOn |
| `App\Emi` | `emis` | `string` | 7 | BranchScoped, TenantScoped, UsesUuid | - |
| `App\GoodsType` | `goods_types` | `int` | 8 | TenantScoped, SoftDeletes | - |
| `App\Invoice` | `invoices` | `string` | 32 | BranchScoped, HasFactory, SoftDeletes, TenantScoped, UsesUuid | - |
| `App\InvoiceAuditLog` | `invoice_audit_logs` | `string` | 7 | BranchScoped, TenantScoped, UsesUuid | - |
| `App\InvoiceItem` | `invoice_items` | `string` | 15 | BranchScoped, TenantScoped, UsesUuid | - |
| `App\InvoicePayment` | `invoice_payments` | `string` | 11 | BranchScoped, HasFactory, TenantScoped, UsesUuid | - |
| `App\MaintenanceServiceType` | `maintenance_service_types` | `int` | 6 | TenantScoped | - |
| `App\MonthlyDriverWage` | `monthly_driver_wages` | `string` | 36 | BranchScoped, TenantScoped, UsesUuid | - |
| `App\MonthlyExpense` | `monthly_expenses` | `string` | 7 | BranchScoped, SoftDeletes, TenantScoped, UsesUuid | - |
| `App\NotificationLog` | `notification_logs` | `string` | 8 | BranchScoped, TenantScoped, UsesUuid | - |
| `App\OnboardingProvisioningLog` | `onboarding_provisioning_logs` | `int` | 5 | - | - |
| `App\Permission` | `permissions` | `int` | 5 | - | - |
| `App\State` | `states` | `int` | 3 | - | - |
| `App\Station` | `stations` | `int` | 7 | SoftDeletes, TenantScoped | - |
| `App\Models\SubscriptionEvent` | `subscription_events` | `int` | 5 | - | - |
| `App\Models\SubscriptionPayment` | `subscription_payments` | `int` | 15 | - | - |
| `App\Tenant` | `tenants` | `string` | 20 | UsesUuid | - |
| `App\Models\TenantSubscription` | `tenant_subscriptions` | `int` | 15 | - | - |
| `App\Trip` | `trips` | `string` | 36 | BranchScoped, SoftDeletes, TenantScoped, UsesUuid | - |
| `App\TripAuditLog` | `trip_audit_logs` | `string` | 9 | BranchScoped, TenantScoped, UsesUuid | - |
| `App\TripExpense` | `trip_expenses` | `string` | 16 | BranchScoped, SoftDeletes, TenantScoped, UsesUuid | - |
| `App\TripExpenseTypeMaster` | `trip_expense_types` | `int` | 6 | SoftDeletes | forSuperAdmin |
| `App\TripPickup` | `trip_pickups` | `string` | 15 | BranchScoped, TenantScoped, UsesUuid | - |
| `App\TripRouteLeg` | `trip_route_legs` | `string` | 35 | BranchScoped, TenantScoped, UsesUuid | - |
| `App\TripStatusHistory` | `trip_status_histories` | `string` | 8 | BranchScoped, TenantScoped, UsesUuid | - |
| `App\TruckType` | `truck_types` | `int` | 6 | TenantScoped | - |
| `App\Tyre` | `tyres` | `string` | 19 | BranchScoped, TenantScoped, UsesUuid | - |
| `App\TyreAuditLog` | `tyre_audit_logs` | `string` | 10 | BranchScoped, TenantScoped, UsesUuid | - |
| `App\TyreDamage` | `tyre_damages` | `string` | 12 | BranchScoped, TenantScoped, UsesUuid | - |
| `App\TyreExpense` | `tyre_expenses` | `string` | 8 | BranchScoped, TenantScoped, UsesUuid | - |
| `App\TyreInspection` | `tyre_inspections` | `string` | 12 | BranchScoped, TenantScoped, UsesUuid | - |
| `App\TyreInstallation` | `tyre_installations` | `string` | 14 | BranchScoped, TenantScoped, UsesUuid | - |
| `App\TyreLayout` | `tyre_layouts` | `string` | 12 | SoftDeletes, TenantScoped, UsesUuid | - |
| `App\TyreReplacement` | `tyre_replacements` | `string` | 13 | BranchScoped, TenantScoped, UsesUuid | - |
| `App\TyreRotation` | `tyre_rotations` | `string` | 11 | BranchScoped, TenantScoped, UsesUuid | - |
| `App\Models\UpcomingSubscription` | `upcoming_subscriptions` | `int` | 13 | - | - |
| `App\User` | `users` | `string` | 14 | HasApiTokens, HasPermissions, Notifiable, UsesUuid, SoftDeletes | - |
| `App\UserRole` | `user_roles` | `int` | 1 | - | systemRoles |
| `App\Vehicle` | `vehicles` | `string` | 17 | BranchScoped, SoftDeletes, TenantScoped, UsesUuid | filterByPrimaryDriver |
| `App\VehicleDocument` | `vehicle_documents` | `string` | 14 | BranchScoped, TenantScoped, UsesUuid | - |
| `App\VehicleMaintenance` | `vehicle_maintenances` | `string` | 13 | BranchScoped, SoftDeletes, TenantScoped, UsesUuid | - |

---

## 4. Controllers & HTTP Handling Layer (52 Controllers)

| Controller Class | Actions Count | Public Methods |
|---|---|---|
| `App\Http\Controllers\Api\AppNotificationController` | 4 | `index`, `unreadCount`, `markRead`, `markAllRead` |
| `App\Http\Controllers\Api\AttendanceController` | 19 | `index`, `monthOverview`, `monthlyWages`, `settlementSheet`, `exportSettlementSheet`, `updateSettlementLine`, `updateVehicleSettlementLine`, `closeMonth`, `lockMonth`, `reopenMonth`, `unlockMonth`, `markAllMonthlyWagesPaid`, `bulkMark`, `store`, `show`, `update`, `markWagePaid`, `markMonthlyWagePaid`, `destroy` |
| `App\Http\Controllers\Api\AttendanceReportController` | 2 | `index`, `export` |
| `App\Http\Controllers\Api\AuthController` | 3 | `login`, `me`, `logout` |
| `App\Http\Controllers\Api\BatteryController` | 13 | `index`, `counts`, `inStock`, `store`, `show`, `update`, `scrap`, `destroy`, `install`, `replace`, `removeInstallation`, `storeInspection`, `vehicleBatteries` |
| `App\Http\Controllers\Api\BranchController` | 9 | `index`, `store`, `show`, `update`, `destroy`, `deletionImpact`, `enter`, `switch`, `exit` |
| `App\Http\Controllers\Api\BrokerController` | 7 | `bulkUploadTemplate`, `bulkUpload`, `index`, `store`, `show`, `update`, `destroy` |
| `App\Http\Controllers\Api\ChallanController` | 5 | `index`, `store`, `show`, `update`, `destroy` |
| `App\Http\Controllers\Api\CityController` | 4 | `index`, `store`, `show`, `update` |
| `App\Http\Controllers\Api\CompanyController` | 6 | `index`, `store`, `show`, `update`, `enter`, `exit` |
| `App\Http\Controllers\Api\ComplianceController` | 3 | `index`, `storeChallan`, `storeEmi` |
| `App\Http\Controllers\Api\CustomerController` | 7 | `bulkUploadTemplate`, `bulkUpload`, `index`, `store`, `show`, `update`, `destroy` |
| `App\Http\Controllers\Api\DashboardController` | 1 | `index` |
| `App\Http\Controllers\Api\DocumentAlertSettingController` | 2 | `show`, `update` |
| `App\Http\Controllers\Api\DocumentTypeController` | 5 | `index`, `store`, `show`, `update`, `destroy` |
| `App\Http\Controllers\Api\DriverAdvanceController` | 2 | `ledger`, `store` |
| `App\Http\Controllers\Api\DriverAssignmentController` | 5 | `index`, `store`, `show`, `update`, `destroy` |
| `App\Http\Controllers\Api\DriverController` | 8 | `bulkUploadTemplate`, `bulkUpload`, `index`, `store`, `show`, `update`, `destroy`, `licenseExpiring` |
| `App\Http\Controllers\Api\FleetDashboardController` | 2 | `stats`, `complianceAlerts` |
| `App\Http\Controllers\Api\FleetMonthlyReportController` | 2 | `show`, `export` |
| `App\Http\Controllers\Api\GoodsTypeController` | 7 | `bulkUploadTemplate`, `bulkUpload`, `index`, `store`, `show`, `update`, `destroy` |
| `App\Http\Controllers\Api\InvoiceController` | 13 | `index`, `show`, `update`, `eligibleTrips`, `previewBulk`, `storeBulk`, `previewForTrip`, `generateForTrip`, `pdf`, `storePayment`, `destroyPayment`, `destroy`, `softDelete` |
| `App\Http\Controllers\Api\LocationController` | 3 | `countries`, `states`, `cities` |
| `App\Http\Controllers\Api\MaintenanceServiceTypeController` | 1 | `index` |
| `App\Http\Controllers\Api\ManagedUserController` | 6 | `index`, `store`, `show`, `update`, `destroy`, `assignableRoles` |
| `App\Http\Controllers\Api\MonthlyExpenseController` | 5 | `index`, `store`, `show`, `update`, `destroy` |
| `App\Http\Controllers\Api\OnboardingController` | 3 | `createUser`, `updateUser`, `status` |
| `App\Http\Controllers\Api\PayrollSettingController` | 2 | `show`, `update` |
| `App\Http\Controllers\Api\PermissionController` | 5 | `index`, `store`, `show`, `update`, `destroy` |
| `App\Http\Controllers\Api\QuickSearchController` | 1 | `index` |
| `App\Http\Controllers\Api\ReportController` | 2 | `attendance`, `trips` |
| `App\Http\Controllers\Api\StateController` | 4 | `index`, `store`, `show`, `update` |
| `App\Http\Controllers\Api\StationController` | 7 | `bulkUploadTemplate`, `bulkUpload`, `index`, `store`, `show`, `update`, `destroy` |
| `App\Http\Controllers\Api\SubscriptionController` | 8 | `index`, `show`, `checkoutPage`, `history`, `quote`, `validatePromo`, `checkout`, `callback` |
| `App\Http\Controllers\Api\SuperAdminDashboardController` | 1 | `stats` |
| `App\Http\Controllers\Api\TripController` | 13 | `nextTripNumber`, `checkTripWindow`, `vehiclePrimaryDriver`, `index`, `store`, `show`, `update`, `archiveImpact`, `deletionImpact`, `archive`, `restore`, `biltyPdf`, `destroy` |
| `App\Http\Controllers\Api\TripExpenseImportController` | 3 | `analyze`, `preview`, `store` |
| `App\Http\Controllers\Api\TripExpenseTypeController` | 5 | `index`, `store`, `show`, `update`, `destroy` |
| `App\Http\Controllers\Api\TripReportController` | 7 | `profitability`, `vehicleWise`, `driverWise`, `expenseSummary`, `routeProfitability`, `fuelPerformance`, `export` |
| `App\Http\Controllers\Api\TruckTypeController` | 5 | `index`, `store`, `show`, `update`, `destroy` |
| `App\Http\Controllers\Api\TyreController` | 20 | `bulkUploadTemplate`, `bulkUpload`, `index`, `store`, `show`, `removalPreview`, `update`, `destroy`, `alerts`, `install`, `removeInstallation`, `updateStintKm`, `rotate`, `damage`, `replace`, `inspection`, `scrap`, `expense`, `vehiclePositions`, `vehicleTyreHistory` |
| `App\Http\Controllers\Api\TyreLayoutController` | 6 | `index`, `seedDefaults`, `store`, `show`, `update`, `destroy` |
| `App\Http\Controllers\Api\TyreReportController` | 6 | `tyreLife`, `costPerKm`, `replacements`, `vehicleWiseUsage`, `expenseSummary`, `export` |
| `App\Http\Controllers\Api\UserRoleController` | 6 | `index`, `show`, `syncPermissions`, `store`, `update`, `destroy` |
| `App\Http\Controllers\Api\VehicleController` | 13 | `bulkUploadTemplate`, `bulkUpload`, `forTripSelection`, `index`, `store`, `show`, `primaryAssignment`, `removePrimaryDriver`, `update`, `toggleStatus`, `fuelHistory`, `destroy`, `restore` |
| `App\Http\Controllers\Api\VehicleDocumentController` | 7 | `index`, `store`, `show`, `update`, `download`, `preview`, `destroy` |
| `App\Http\Controllers\Api\VehicleMaintenanceController` | 7 | `index`, `due`, `forVehicle`, `store`, `show`, `update`, `destroy` |
| `App\Http\Controllers\Api\VehicleMaintenanceReportController` | 4 | `costSummary`, `truckWise`, `dueServices`, `export` |
| `App\Http\Controllers\Api\VehicleUtilizationReportController` | 2 | `show`, `export` |
| `App\Http\Controllers\Auth\ForgotPasswordController` | 1 | `store` |
| `App\Http\Controllers\Auth\LoginController` | 3 | `create`, `store`, `destroy` |
| `App\Http\Controllers\Auth\ResetPasswordController` | 2 | `create`, `store` |

---

## 5. Domain Services & Business Logic Engines (46 Services)

| Service Class | Methods Count | Core Capabilities |
|---|---|---|
| `App\Modules\BatteryManagement\Services\BatteryService` | 14 | `paginate`, `getCounts`, `getBrands`, `getInStockList`, `showWithLifecycle`, `create`, `update`, `scrap` *(+6 more)* |
| `App\Modules\DriverManagement\Services\AttendanceCalendarService` | 7 | `buildOverview`, `driverWageTotals`, `driverPrimaryDayStats`, `driverSubstituteEarnings`, `primarySubstituteLiabilities`, `substituteWageForCell`, `driverDayStats` |
| `App\Modules\DriverManagement\Services\AttendanceMonthLockService` | 14 | `statusForMonth`, `summaryForMonth`, `assertEditableForDate`, `isMonthOpen`, `periodHasNonOpenMonth`, `lockedMonthsInPeriod`, `formatLockedMonthsList`, `assertPeriodIsOpen` *(+6 more)* |
| `App\Modules\DriverManagement\Services\AttendanceService` | 14 | `paginate`, `monthOverview`, `listMonthlyWages`, `closeMonth`, `lockMonth`, `reopenMonth`, `unlockMonth`, `markAllMonthlyWagesPaid` *(+6 more)* |
| `App\Modules\DriverManagement\Services\DriverAssignmentService` | 8 | `paginate`, `currentPrimaryForVehicle`, `primaryAssignmentSummary`, `formatAssignmentPayload`, `create`, `update`, `delete`, `removePrimaryDriver` |
| `App\Modules\DriverManagement\Services\DriverAvailabilityService` | 2 | `attachAvailability`, `assertAssignmentWithinAvailabilityLimits` |
| `App\Modules\DriverManagement\Services\DriverService` | 5 | `paginate`, `create`, `update`, `delete`, `licenseExpiringWithinDays` |
| `App\Modules\DriverManagement\Services\MonthlySettlementService` | 6 | `sheet`, `updatePayrollLine`, `updateVehicleLine`, `formatPayrollLine`, `applySettlementDefaults`, `refreshMonthCalculations` |
| `App\Modules\DriverManagement\Services\SubstituteDriverAvailability` | 3 | `assertAvailable`, `conflictMessage`, `openTripBusyByDate` |
| `App\Modules\InvoiceManagement\Services\InvoiceService` | 14 | `paginate`, `find`, `calculateGst`, `previewForTrip`, `generateForTrip`, `eligibleTripsForCustomer`, `previewForCustomerTrips`, `generateForCustomerTrips` *(+6 more)* |
| `App\Modules\LocationManagement\Services\CityService` | 3 | `paginate`, `create`, `update` |
| `App\Modules\LocationManagement\Services\StateService` | 3 | `paginate`, `create`, `update` |
| `App\Modules\TripManagement\Services\BrokerService` | 5 | `paginate`, `listAll`, `create`, `update`, `delete` |
| `App\Modules\TripManagement\Services\CustomerService` | 5 | `paginate`, `listAll`, `create`, `update`, `delete` |
| `App\Modules\TripManagement\Services\FleetMonthlyReportService` | 2 | `build`, `exportRows` |
| `App\Modules\TripManagement\Services\StationService` | 5 | `paginate`, `listAll`, `create`, `update`, `delete` |
| `App\Modules\TripManagement\Services\TripAuditLogger` | 1 | `log` |
| `App\Modules\TripManagement\Services\TripBiltyService` | 2 | `groupsForTrip`, `pdf` |
| `App\Modules\TripManagement\Services\TripReportService` | 14 | `filteredTripCount`, `financialSummary`, `profitability`, `vehicleWise`, `driverWise`, `emptyKm`, `expenseSummary`, `routeProfitability` *(+6 more)* |
| `App\Modules\TripManagement\Services\TripService` | 7 | `paginate`, `previewNextTripNumber`, `create`, `update`, `archive`, `delete`, `restore` |
| `App\Modules\TripManagement\Services\TripSnapshotService` | 4 | `copyTruckDefaultsToTrip`, `copyMileage`, `copyMaxLoad`, `copyDiesel` |
| `App\Modules\TripManagement\Services\TripValidationService` | 23 | `resolveTripEditScope`, `assertTripIsEditable`, `findOverlappingOpenTrip`, `assertDriverAssignedToVehicle`, `assertDriverIsActive`, `assertSubstituteDriverIsValid`, `assertVehicleExists`, `assertRouteLegsAreValid` *(+15 more)* |
| `App\Modules\TripManagement\Services\VehicleTripEligibilityService` | 3 | `applySelectableFirstOrdering`, `evaluate`, `assertEligibleForTripCreation` |
| `App\Modules\TripManagement\Services\VehicleUtilizationReportService` | 2 | `build`, `exportRows` |
| `App\Modules\TyreManagement\Services\TyreAlertService` | 2 | `alerts`, `dashboardCounts` |
| `App\Modules\TyreManagement\Services\TyreAuditLogger` | 1 | `log` |
| `App\Modules\TyreManagement\Services\TyreReportService` | 5 | `tyreLife`, `costPerKm`, `replacements`, `vehicleWiseUsage`, `expenseSummary` |
| `App\Modules\TyreManagement\Services\TyreService` | 18 | `paginate`, `show`, `showWithLifecycle`, `create`, `update`, `delete`, `install`, `removeInstallation` *(+10 more)* |
| `App\Modules\TyreManagement\Services\TyreTimelineService` | 1 | `build` |
| `App\Modules\VehicleMaintenanceManagement\Services\VehicleMaintenanceReportService` | 3 | `costSummary`, `truckWiseCost`, `dueServices` |
| `App\Modules\VehicleMaintenanceManagement\Services\VehicleMaintenanceService` | 7 | `create`, `update`, `delete`, `paginate`, `listForVehicle`, `dueServices`, `dueServicesForAlerts` |
| `App\Services\AdminApiClient` | 3 | `getCatalog`, `validatePromo`, `incrementPromoUse` |
| `App\Services\BulkUploadService` | 2 | `process`, `downloadTemplate` |
| `App\Services\OnboardingProvisioningService` | 4 | `createUser`, `updateUser`, `statusFor`, `statusByEmail` |
| `App\Services\SubscriptionAccessService` | 3 | `resolveForTenant`, `accessPayloadForTenant`, `cachedSettings` |
| `App\Services\SubscriptionCheckoutService` | 6 | `createCheckoutOrder`, `handleCallback`, `recordOfflinePurchase`, `applyUpcomingForTenant`, `applyDueUpcomingSubscriptions`, `renewalTermFollowing` |
| `App\Services\SubscriptionGuard` | 6 | `assertCanMutate`, `assertPlanActive`, `assertCanCreateBranch`, `assertCanCreateUser`, `assertCanAssignUserToBranch`, `assertCanCreateVehicle` |
| `App\Services\SubscriptionPricingService` | 4 | `minimumRenewalAddons`, `quote`, `createTrialSubscription`, `resolveCatalogForTrial` |
| `App\Services\SubscriptionUsageService` | 4 | `usageForTenant`, `overflowUsersUsed`, `minimumRenewalAddons`, `vehicleCountForTenant` |
| `App\Services\TripExpenseImport\AbstractStatementImporter` | 3 | `analyze`, `preview`, `store` |
| `App\Services\TripExpenseImport\FastagStatementImporter` | 0 |  |
| `App\Services\TripExpenseImport\FuelStatementImporter` | 0 |  |
| `App\Services\TripExpenseImport\StatementColumnMapper` | 7 | `fieldsFor`, `aliasesFor`, `normalizeHeader`, `detectHeaderRow`, `analyzeHeaders`, `validateMap`, `isMapped` |
| `App\Services\TripExpenseImport\StatementDateParser` | 3 | `parse`, `parseCombined`, `parseTimePart` |
| `App\Services\TripExpenseImport\StatementSheetReader` | 1 | `read` |
| `App\Services\TripExpenseImport\TripExpenseImportTripMatcher` | 5 | `candidateTrips`, `tripOptions`, `resolveVehicle`, `matchTrip`, `existingReferences` |

---

## 6. Policies & Security Authorization (28 Policies)

| Policy Class | Enforced Policy Methods |
|---|---|
| `App\Policies\AttendancePolicy` | `viewAny`, `view`, `create`, `update`, `delete`, `bulkMark`, `markWagePaid`, `viewSettlement`, `exportSettlement`, `closeMonth`, `markMonthlyWagePaid`, `unlockMonth` |
| `App\Policies\BatteryPolicy` | `viewAny`, `view`, `create`, `update`, `delete` |
| `App\Policies\BranchPolicy` | `viewAny`, `view`, `create`, `update`, `delete`, `enter` |
| `App\Policies\BrokerPolicy` | `viewAny`, `view`, `create`, `update`, `delete` |
| `App\Policies\ChallanPolicy` | `viewAny`, `view`, `create`, `update`, `delete` |
| `App\Policies\CityPolicy` | `viewAny`, `view`, `create`, `update`, `delete` |
| `App\Policies\CustomerPolicy` | `viewAny`, `view`, `create`, `update`, `delete` |
| `App\Policies\DocumentTypePolicy` | `viewAny`, `view`, `create`, `update`, `delete` |
| `App\Policies\DriverAssignmentPolicy` | `viewAny`, `view`, `create`, `update`, `delete` |
| `App\Policies\DriverPolicy` | `viewAny`, `view`, `create`, `update`, `delete`, `viewAdvances`, `manageAdvances` |
| `App\Policies\EmiPolicy` | `viewAny`, `view`, `create`, `update`, `delete` |
| `App\Policies\GoodsTypePolicy` | `viewAny`, `view`, `create`, `update`, `delete` |
| `App\Policies\InvoicePolicy` | `viewAny`, `viewArchived`, `view`, `create`, `update`, `updateCharges`, `delete`, `softDelete`, `export` |
| `App\Policies\MaintenanceServiceTypePolicy` | `viewAny`, `view` |
| `App\Policies\ManagedUserPolicy` | `viewAny`, `view`, `create`, `update`, `delete` |
| `App\Policies\PermissionPolicy` | `viewAny`, `view`, `create`, `update`, `delete` |
| `App\Policies\StatePolicy` | `viewAny`, `view`, `create`, `update`, `delete` |
| `App\Policies\StationPolicy` | `viewAny`, `view`, `create`, `update`, `delete` |
| `App\Policies\TenantPolicy` | `viewAny`, `view`, `create`, `update`, `enter`, `block` |
| `App\Policies\TripExpenseTypePolicy` | `viewAny`, `view`, `create`, `update`, `delete` |
| `App\Policies\TripPolicy` | `viewAny`, `viewArchived`, `view`, `create`, `update`, `archive`, `restore`, `delete` |
| `App\Policies\TruckTypePolicy` | `viewAny`, `view`, `create`, `update`, `delete` |
| `App\Policies\TyreLayoutPolicy` | `viewAny`, `view`, `create`, `update`, `delete` |
| `App\Policies\TyrePolicy` | `viewAny`, `view`, `create`, `update`, `delete` |
| `App\Policies\UserRolePolicy` | `viewAny`, `view`, `update` |
| `App\Policies\VehicleDocumentPolicy` | `viewAny`, `view`, `create`, `update`, `delete` |
| `App\Policies\VehicleMaintenancePolicy` | `viewAny`, `view`, `create`, `update`, `delete` |
| `App\Policies\VehiclePolicy` | `viewAny`, `view`, `create`, `update`, `delete`, `toggleStatus` |

---

## 7. Application Enums & Value Objects (32 Enums)

| Enum Class | Cases Count | Defined Values |
|---|---|---|
| `App\Enums\AttendanceMarkedVia` | 3 | `admin_panel`, `manual`, `mobile_app` |
| `App\Enums\AttendanceMonthStatus` | 3 | `open`, `closed`, `paid` |
| `App\Enums\AttendanceStatus` | 3 | `PRESENT`, `ABSENT`, `NO_WORK_DAY` |
| `App\Enums\BatteryCondition` | 4 | `GOOD`, `FAIR`, `WEAK`, `FAILED` |
| `App\Enums\BatteryRemovalReason` | 7 | `BATTERY_FAILED`, `LOW_VOLTAGE`, `SCHEDULED_AGING`, `PHYSICAL_DAMAGE`, `WARRANTY_CLAIM`, `TRANSFERRED`, `VEHICLE_MAINTENANCE` |
| `App\Enums\BatteryStatus` | 6 | `IN_STOCK`, `INSTALLED`, `REMOVED`, `UNDER_INSPECTION`, `RETURNED`, `SCRAPPED` |
| `App\Enums\BatteryType` | 5 | `LEAD_ACID`, `MAINTENANCE_FREE_SMF`, `TUBULAR`, `LITHIUM_ION`, `GEL` |
| `App\Enums\ChallanPaymentStatus` | 2 | `PAID`, `UNPAID` |
| `App\Enums\DriverAdvanceEntryType` | 2 | `dr`, `cr` |
| `App\Enums\DropDeliveryStatus` | 2 | `pending`, `delivered` |
| `App\Enums\FreightBillingStatus` | 3 | `unbilled`, `billed`, `invoiced` |
| `App\Enums\FreightPaymentStatus` | 3 | `pending`, `partial`, `paid` |
| `App\Enums\GstRegistrationType` | 3 | `regular`, `composition`, `unregistered` |
| `App\Enums\InvoiceDocumentType` | 4 | `tax_invoice`, `tax_invoice_rcm`, `bill_of_supply`, `bill_invoice` |
| `App\Enums\InvoiceParticularsMode` | 2 | `separate`, `combined` |
| `App\Enums\InvoicePaymentStatus` | 4 | `pending`, `partial`, `paid`, `cancelled` |
| `App\Enums\LoadCategory` | 3 | `empty`, `half`, `full` |
| `App\Enums\PayrollPayDaysMode` | 2 | `calendar`, `fixed_30` |
| `App\Enums\PickupDeliveryStatus` | 3 | `pending`, `partial`, `delivered` |
| `App\Enums\RouteType` | 3 | `LOADED`, `HALF_LOADED`, `EMPTY` |
| `App\Enums\SubscriptionAccessMode` | 3 | `full`, `grace`, `locked` |
| `App\Enums\TaxLiability` | 2 | `forward_charge`, `reverse_charge` |
| `App\Enums\TripExpenseType` | 11 | `DIESEL`, `TOLL`, `DRIVER_EXPENSE`, `LOADING_CHARGES`, `UNLOADING_CHARGES`, `REPAIR`, `FOOD_EXPENSE`, `PARKING`, `RTO_EXPENSE`, `PERMIT_BORDER_TAX`, `OTHER` |
| `App\Enums\TripStatus` | 5 | `PENDING`, `IN_PROGRESS`, `COMPLETED`, `CANCELLED`, `INVOICED` |
| `App\Enums\TripType` | 3 | `ONE_WAY`, `ROUND_TRIP`, `MULTI_POINT` |
| `App\Enums\TyreDamageType` | 6 | `PUNCTURE`, `BURST`, `CUT`, `SIDEWALL`, `RECAPPED`, `OTHER` |
| `App\Enums\TyreExpenseType` | 6 | `PURCHASE`, `REPAIR`, `RETREAD`, `ROTATION`, `MAINTENANCE`, `REPLACEMENT` |
| `App\Enums\TyrePosition` | 7 | `FRONT_LEFT`, `FRONT_RIGHT`, `REAR_LEFT_OUTER`, `REAR_LEFT_INNER`, `REAR_RIGHT_OUTER`, `REAR_RIGHT_INNER`, `SPARE` |
| `App\Enums\TyreStatus` | 3 | `IN_STOCK`, `INSTALLED`, `SCRAPPED` |
| `App\Enums\TyreType` | 5 | `RADIAL`, `BIAS`, `TUBELESS`, `TUBED`, `OTHER` |
| `App\Enums\VehicleDocumentType` | 9 | `fitness_certificate`, `puc`, `insurance`, `permit`, `rc`, `road_tax`, `national_permit`, `gps_subscription`, `fastag` |
| `App\Enums\WagePaymentStatus` | 2 | `unpaid`, `paid` |

---

## 8. Middleware Pipeline
The application enforces strict request filtering through the following middleware stack:
1. `Authenticate:sanctum` / `auth:web` - Authentication token & session validation.
2. `App\Http\Middleware\ResolveTenant` - Extracts and verifies company context from session or request headers.
3. `App\Http\Middleware\ResolveBranch` - Extracts and verifies branch context from session or request headers.
4. `App\Http\Middleware\EnsureTenantContext` - Aborts with HTTP 403 if tenant context cannot be firmly established.
5. `App\Http\Middleware\EnsureActiveSubscription` - Validates that the tenant holds an active, unexpired subscription tier and vehicle quota.
6. `App\Http\Middleware\EnsurePermission` - Verifies that the authenticated user possesses the specific module permission slug.
7. `App\Http\Middleware\EnsureSuperAdmin` - Restricts administrative and multi-company actions to superadmins.
8. `App\Http\Middleware\VerifyOnboardingSignature` - Validates cryptographic signatures for admin API endpoints.

---

## 9. Form Requests & Validation Layer (99 Request Classes)
Form requests in `app/Http/Requests` encapsulate all input validation, sanitization, and business authorization rules prior to controller execution. Key request categories include:
- Fleet & Vehicle requests (`StoreVehicleRequest`, `UpdateVehicleRequest`, `DeleteVehicleRequest`)
- Logistics & Trip requests (`StoreTripRequest`, `UpdateTripRequest`, `AnalyzeTripExpenseImportRequest`)
- Invoicing & Billing requests (`BulkInvoicePreviewRequest`, `BulkInvoiceStoreRequest`, `CancelInvoiceRequest`)
- Driver & Payroll requests (`StoreDriverRequest`, `MarkAttendanceRequest`, `CloseMonthlyWagesRequest`)
- Assets & Workshop requests (`StoreTyreRequest`, `InstallBatteryRequest`, `StoreVehicleMaintenanceRequest`)
