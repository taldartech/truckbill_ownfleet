# TruckBill Own Fleet — Test Setup & Developer Guide

## 1. Prerequisites
- **PHP**: 8.2 or 8.4 CLI (`php -v`)
- **Extensions**: `pdo_sqlite`, `mbstring`, `openssl`, `tokenizer`, `xml`, `ctype`, `json`, `pcov` (for fast code coverage)
- **Composer**: 2.x (`composer --version`)
- **SQLite3**: In-memory database supported by default

---

## 2. Environment Configuration
The test environment is configured via `phpunit.xml` in the repository root. It overrides standard `.env` variables to ensure total isolation:

```xml
<php>
    <env name="APP_ENV" value="testing"/>
    <env name="APP_MAINTENANCE_DRIVER" value="file"/>
    <env name="BCRYPT_ROUNDS" value="4"/>
    <env name="CACHE_STORE" value="array"/>
    <env name="DB_CONNECTION" value="sqlite"/>
    <env name="DB_DATABASE" value=":memory:"/>
    <env name="MAIL_MAILER" value="array"/>
    <env name="QUEUE_CONNECTION" value="sync"/>
    <env name="SESSION_DRIVER" value="array"/>
    <env name="PULSE_ENABLED" value="false"/>
    <env name="TELESCOPE_ENABLED" value="false"/>
</php>
```

No external database server (MySQL/PostgreSQL) or Redis cache is required to run tests.

---

## 3. Running Automated Tests

### 3.1 Execute Full Test Suite
To run all automated unit and feature tests:
```bash
php -d memory_limit=512M ./vendor/bin/phpunit
```

### 3.2 Execute an Individual Test File
```bash
# Run Compliance & EMI tests
php -d memory_limit=512M ./vendor/bin/phpunit tests/Feature/Api/ComplianceAndEmiTest.php

# Run Vehicle Management tests
php -d memory_limit=512M ./vendor/bin/phpunit tests/Feature/VehicleTest.php

# Run Trip Lifecycle tests
php -d memory_limit=512M ./vendor/bin/phpunit tests/Feature/TripLifecycleTest.php
```

### 3.3 Execute a Specific Test Method
```bash
php -d memory_limit=512M ./vendor/bin/phpunit --filter test_admin_can_store_and_retrieve_emi
```

### 3.4 Execute with Code Coverage (PCOV)
To run tests and display text-based line coverage across the `app/` directory:
```bash
php -d memory_limit=512M -d pcov.enabled=1 -d pcov.directory=app ./vendor/bin/phpunit --coverage-text
```

To generate an HTML coverage report:
```bash
php -d memory_limit=512M -d pcov.enabled=1 -d pcov.directory=app ./vendor/bin/phpunit --coverage-html coverage-report
```
The report can then be viewed by opening `coverage-report/index.html` in any browser.

---

## 4. Test Data Best Practices

### 4.1 Tenant & User Creation
Always use the provided helpers or traits to establish tenant context:
```php
[$tenant, $admin] = $this->createTenantUser('admin');
```

### 4.2 Operational Vehicle Setup
Vehicles require specific seeded attributes for operational compliance:
```php
use App\Support\TruckTypeDefaults;
use App\Support\TyreLayoutDefaults;

TyreLayoutDefaults::seedForTenant($tenant->id);
TruckTypeDefaults::seedForTenant($tenant->id);

$vehicle = Vehicle::factory()->create([
    'tenant_id' => $tenant->id,
    'branch_id' => $branch->id,
    ...$this->operationalAttributesForTenant($tenant),
]);
```

### 4.3 Scheduled Command Testing
When executing console commands that query tenant-scoped models (`TenantScoped`), remember to set the tenant context in test scope or ensure the command bypasses global scopes (`withoutGlobalScope('tenant')`):
```php
\App\Support\TenantContext::setTenantId($tenant->id);
try {
    Artisan::call('compliance:check-emi-due');
} finally {
    \App\Support\TenantContext::setTenantId(null);
}
```

