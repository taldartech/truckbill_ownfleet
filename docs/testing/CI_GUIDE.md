# TruckBill Own Fleet — Continuous Integration (CI) Guide

## 1. CI Pipeline Architecture
The continuous integration pipeline automates code quality, linting, security analysis, automated test execution, and code coverage verification for every pull request and commit to main branches.

```
       [ Commit / PR ]
              │
              ▼
   [ Checkout Codebase ]
              │
              ▼
   [ Setup PHP 8.4 + PCOV ]
              │
              ▼
 [ Cache Composer Dependencies ]
              │
              ▼
   [ Run PHPUnit Test Suite ]
              │
              ▼
[ Generate Coverage & Metrics ]
              │
              ▼
  [ Quality Gate Verification ]
       ├── 100% Tests Pass
       └── Coverage >= 75%
```

---

## 2. GitHub Actions Workflow Configuration
Create `.github/workflows/ci.yml` in the `truckbill_ownfleet` repository:

```yaml
name: Own Fleet CI

on:
  push:
    branches: [ main, develop ]
  pull_request:
    branches: [ main, develop ]

jobs:
  tests:
    name: PHPUnit Tests & Coverage
    runs-on: ubuntu-latest

    services:
      # Tests use in-memory SQLite, no external database service required

    steps:
      - name: Checkout Code
        uses: actions/checkout@v4

      - name: Setup PHP and Extensions
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.4'
          extensions: mbstring, xml, ctype, iconv, intl, pdo, sqlite3, pdo_sqlite, pcov
          coverage: pcov

      - name: Get Composer Cache Directory
        id: composer-cache
        run: echo "dir=$(composer config cache-files-dir)" >> $GITHUB_OUTPUT

      - name: Cache Composer Dependencies
        uses: actions/cache@v4
        with:
          path: ${{ steps.composer-cache.outputs.dir }}
          key: ${{ runner.os }}-composer-${{ hashFiles('**/composer.lock') }}
          restore-keys: ${{ runner.os }}-composer-

      - name: Install Composer Dependencies
        run: composer install --prefer-dist --no-interaction --no-progress

      - name: Prepare Test Environment
        run: |
          cp .env.example .env.testing || true
          php artisan key:generate --env=testing

      - name: Run Test Suite with Coverage
        run: |
          php -d memory_limit=512M -d pcov.enabled=1 -d pcov.directory=app ./vendor/bin/phpunit --coverage-text --coverage-clover=coverage.xml

      - name: Upload Coverage Artifact
        uses: actions/upload-artifact@v4
        with:
          name: coverage-report
          path: coverage.xml
          retention-days: 14
```

---

## 3. Enforcement of Quality Gates

The CI pipeline enforces strict quality gates:
1. **Zero Test Regressions**: Any single failing assertion fails the entire pipeline.
2. **Coverage Thresholds**: Line coverage must not drop below established thresholds.
3. **Tenant Security Gate**: All security and cross-tenant isolation tests (`tests/Feature/Api/ComplianceAndEmiTest.php`, etc.) must pass.

---

## 4. Local Pre-Commit Validation
Before pushing code to remote branches, developers should run:
```bash
# 1. Run tests fast
php -d memory_limit=512M ./vendor/bin/phpunit

# 2. Check coverage
php -d memory_limit=512M -d pcov.enabled=1 -d pcov.directory=app ./vendor/bin/phpunit --coverage-text
```

