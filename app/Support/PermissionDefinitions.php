<?php

namespace App\Support;

final class PermissionDefinitions
{
    /**
     * @return array{name: string, group: string, description: string, display_order: int}
     */
    private static function entry(string $name, string $group, string $description, int $displayOrder): array
    {
        return [
            'name' => $name,
            'group' => $group,
            'description' => $description,
            'display_order' => $displayOrder,
        ];
    }

    /**
     * @var array<string, array{name: string, group: string, description: string, display_order: int}>|null
     */
    private static ?array $cache = null;

    /**
     * @return array<string, array{name: string, group: string, description: string, display_order: int}>
     */
    public static function all(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }

        self::$cache = self::build();

        return self::$cache;
    }

    /**
     * @return array<string, array{name: string, group: string, description: string, display_order: int}>
     */
    private static function build(): array
    {
        return [
            'dashboard.view' => self::entry('View dashboard', 'dashboard', 'Access the main dashboard.', 100),
            'dashboard.widgets.trips' => self::entry('Dashboard trips widget', 'dashboard', 'Show trip summary widgets on the dashboard.', 101),
            'dashboard.widgets.fleet' => self::entry('Dashboard fleet widget', 'dashboard', 'Show fleet summary widgets on the dashboard.', 102),
            'dashboard.widgets.attendance' => self::entry('Dashboard attendance widget', 'dashboard', 'Show attendance summary widgets on the dashboard.', 103),
            'dashboard.widgets.payroll' => self::entry('Dashboard payroll widget', 'dashboard', 'Show payroll summary widgets on the dashboard.', 104),
            'dashboard.widgets.financial' => self::entry('Dashboard financial widget', 'dashboard', 'Show financial summary widgets on the dashboard.', 105),
            'dashboard.widgets.compliance' => self::entry('Dashboard compliance widget', 'dashboard', 'Show compliance summary widgets on the dashboard.', 106),

            'trips.view' => self::entry('View trips', 'trips', 'List and open trip records.', 200),
            'trips.create' => self::entry('Create trips', 'trips', 'Create new trip records.', 201),
            'trips.edit' => self::entry('Edit trips', 'trips', 'Update existing trip records.', 202),
            'trips.manage' => self::entry('Manage trips', 'trips', 'Legacy manage trips permission (create and edit).', 203),
            'trips.archive' => self::entry('Archive trips', 'trips', 'Archive completed or cancelled trips.', 204),
            'trips.view_archived' => self::entry('View archived trips', 'trips', 'View archived trip records.', 205),
            'trips.restore' => self::entry('Restore trips', 'trips', 'Restore archived trip records.', 206),
            'trips.delete' => self::entry('Delete trips (legacy)', 'trips', 'Legacy hard-delete trips permission.', 207),
            'trips.export' => self::entry('Export trips', 'trips', 'Export trip data.', 208),
            'trips.import' => self::entry('Import trips', 'trips', 'Import trip data.', 209),
            'trip_reports.view' => self::entry('View trip reports', 'trips', 'View operational trip reports.', 210),
            'trip_reports.export' => self::entry('Export trip reports', 'trips', 'Export operational trip reports.', 211),

            'stations.view' => self::entry('View stations', 'trips', 'View station master data.', 220),
            'stations.create' => self::entry('Create stations', 'trips', 'Create station master records.', 221),
            'stations.edit' => self::entry('Edit stations', 'trips', 'Edit station master records.', 222),
            'stations.manage' => self::entry('Manage stations', 'trips', 'Legacy manage stations permission.', 223),
            'brokers.view' => self::entry('View references', 'trips', 'View broker/reference master data.', 230),
            'brokers.create' => self::entry('Create references', 'trips', 'Create broker/reference records.', 231),
            'brokers.edit' => self::entry('Edit references', 'trips', 'Edit broker/reference records.', 232),
            'brokers.manage' => self::entry('Manage references', 'trips', 'Legacy manage broker/reference permission.', 233),
            'customers.view' => self::entry('View customers', 'trips', 'View customer master data.', 240),
            'customers.create' => self::entry('Create customers', 'trips', 'Create customer records.', 241),
            'customers.edit' => self::entry('Edit customers', 'trips', 'Edit customer records.', 242),
            'customers.manage' => self::entry('Manage customers', 'trips', 'Legacy manage customers permission.', 243),
            'goods_types.view' => self::entry('View goods types', 'trips', 'View goods type master data.', 250),
            'goods_types.create' => self::entry('Create goods types', 'trips', 'Create goods type records.', 251),
            'goods_types.edit' => self::entry('Edit goods types', 'trips', 'Edit goods type records.', 252),
            'goods_types.manage' => self::entry('Manage goods types', 'trips', 'Legacy manage goods types permission.', 253),

            'invoices.view' => self::entry('View invoices', 'invoices', 'List and open invoices.', 250),
            'invoices.create' => self::entry('Create invoices', 'invoices', 'Generate invoices from completed trips.', 251),
            'invoices.edit' => self::entry('Edit invoices', 'invoices', 'Record invoice payments and updates.', 252),
            'invoices.manage' => self::entry('Manage invoices', 'invoices', 'Legacy manage invoices permission (create and edit).', 253),
            'invoices.delete' => self::entry('Cancel invoices', 'invoices', 'Cancel unpaid invoices.', 254),
            'invoices.export' => self::entry('Export invoices', 'invoices', 'Download invoice PDFs.', 255),

            'drivers.view' => self::entry('View drivers', 'drivers', 'View driver records.', 300),
            'drivers.create' => self::entry('Create drivers', 'drivers', 'Create driver records.', 301),
            'drivers.edit' => self::entry('Edit drivers', 'drivers', 'Edit driver records.', 302),
            'drivers.manage' => self::entry('Manage drivers', 'drivers', 'Legacy manage drivers permission.', 303),
            'drivers.archive' => self::entry('Archive drivers', 'drivers', 'Archive driver records.', 304),
            'driver_advances.view' => self::entry('View driver advances', 'drivers', 'View driver advance wallet and ledger.', 305),
            'driver_advances.manage' => self::entry('Manage driver advances', 'drivers', 'Record driver advance and recovery payments.', 306),
            'driver_assignments.view' => self::entry('View vehicle driver assignments', 'drivers', 'View driver-to-vehicle assignments.', 310),
            'driver_assignments.create' => self::entry('Create vehicle driver assignments', 'drivers', 'Assign drivers to vehicles.', 311),
            'driver_assignments.edit' => self::entry('Edit vehicle driver assignments', 'drivers', 'Update driver-to-vehicle assignments.', 312),
            'driver_assignments.manage' => self::entry('Manage vehicle driver assignments', 'drivers', 'Legacy manage driver assignments permission.', 313),

            'attendances.view' => self::entry('View attendance', 'attendance', 'View daily attendance records.', 400),
            'attendances.create' => self::entry('Create attendance', 'attendance', 'Mark single-day attendance.', 401),
            'attendances.edit' => self::entry('Edit attendance', 'attendance', 'Edit attendance records.', 402),
            'attendances.manage' => self::entry('Manage daily attendance', 'attendance', 'Legacy manage daily attendance permission.', 403),
            'attendance.bulk_mark' => self::entry('Bulk mark attendance', 'attendance', 'Bulk mark attendance across a date range.', 404),
            'attendances.bulk_manage' => self::entry('Bulk mark and month grid attendance', 'attendance', 'Legacy bulk attendance permission.', 405),
            'wages.view' => self::entry('View monthly wage settlement', 'attendance', 'View monthly wage settlement sheets.', 410),
            'wages.manage' => self::entry('Manage driver wages', 'attendance', 'Close months and manage wage settlement.', 411),

            'vehicles.view' => self::entry('View trucks/vehicles', 'vehicles', 'View fleet vehicles.', 500),
            'vehicles.create' => self::entry('Create trucks/vehicles', 'vehicles', 'Create fleet vehicles.', 501),
            'vehicles.edit' => self::entry('Edit trucks/vehicles', 'vehicles', 'Edit fleet vehicles.', 502),
            'vehicles.manage' => self::entry('Manage trucks/vehicles', 'vehicles', 'Legacy manage vehicles permission.', 503),
            'vehicles.delete' => self::entry('Delete trucks/vehicles', 'vehicles', 'Delete fleet vehicles.', 504),
            'vehicles.toggle_status' => self::entry('Activate or deactivate trucks', 'vehicles', 'Activate or deactivate fleet vehicles.', 505),
            'vehicle_documents.view' => self::entry('View vehicle documents', 'vehicles', 'View uploaded vehicle documents.', 510),
            'vehicle_documents.create' => self::entry('Upload vehicle documents', 'vehicles', 'Upload vehicle documents.', 511),
            'vehicle_documents.edit' => self::entry('Edit vehicle documents', 'vehicles', 'Edit vehicle document metadata.', 512),
            'vehicle_documents.manage' => self::entry('Manage vehicle documents', 'vehicles', 'Legacy manage vehicle documents permission.', 513),

            'reports.view' => self::entry('View attendance reports (legacy)', 'reports', 'Legacy view attendance reports permission.', 600),
            'reports.export' => self::entry('Export attendance reports (legacy)', 'reports', 'Legacy export attendance reports permission.', 601),
            'attendance_reports.view' => self::entry('View attendance reports', 'reports', 'View attendance operational reports.', 602),
            'attendance_reports.export' => self::entry('Export attendance reports', 'reports', 'Export attendance operational reports.', 603),
            'fleet_monthly_report.view' => self::entry('View monthly fleet report', 'reports', 'View monthly fleet financial report.', 610),
            'fleet_monthly_report.export' => self::entry('Export monthly fleet report', 'reports', 'Export monthly fleet financial report.', 611),

            'locations.view' => self::entry('View states and cities', 'locations', 'View state and city master data.', 700),
            'locations.create' => self::entry('Create states and cities', 'locations', 'Create state and city master data.', 701),
            'locations.edit' => self::entry('Edit states and cities', 'locations', 'Edit state and city master data.', 702),
            'locations.manage' => self::entry('Manage states and cities', 'locations', 'Legacy manage locations permission.', 703),

            'tyres.view' => self::entry('View tyres', 'tyres', 'View tyre inventory and assignments.', 800),
            'tyres.create' => self::entry('Create tyres', 'tyres', 'Create tyre records.', 801),
            'tyres.edit' => self::entry('Edit tyres', 'tyres', 'Edit tyre records.', 802),
            'tyres.manage' => self::entry('Manage tyres', 'tyres', 'Legacy manage tyres permission.', 803),
            'tyre_layouts.view' => self::entry('View tyre layouts', 'tyres', 'View tyre layout templates.', 810),
            'tyre_layouts.create' => self::entry('Create tyre layouts', 'tyres', 'Create tyre layout templates.', 811),
            'tyre_layouts.edit' => self::entry('Edit tyre layouts', 'tyres', 'Edit tyre layout templates.', 812),
            'tyre_layouts.manage' => self::entry('Manage tyre layouts', 'tyres', 'Legacy manage tyre layouts permission.', 813),
            'tyre_reports.view' => self::entry('View tyre reports', 'tyres', 'View tyre operational reports.', 820),
            'tyre_reports.export' => self::entry('Export tyre reports', 'tyres', 'Export tyre operational reports.', 821),

            'maintenance.view' => self::entry('View truck maintenance', 'maintenance', 'View maintenance records and due services.', 900),
            'maintenance.create' => self::entry('Create maintenance records', 'maintenance', 'Create maintenance service records.', 901),
            'maintenance.edit' => self::entry('Edit maintenance records', 'maintenance', 'Edit maintenance service records.', 902),
            'maintenance.delete' => self::entry('Delete maintenance records', 'maintenance', 'Delete maintenance service records.', 903),
            'maintenance_reports.view' => self::entry('View maintenance reports', 'maintenance', 'View maintenance operational reports.', 910),
            'maintenance_reports.export' => self::entry('Export maintenance reports', 'maintenance', 'Export maintenance operational reports.', 911),

            'emis.view' => self::entry('View EMIs', 'compliance', 'View EMI compliance records.', 1000),
            'emis.create' => self::entry('Create EMIs', 'compliance', 'Create EMI compliance records.', 1001),
            'emis.edit' => self::entry('Edit EMIs', 'compliance', 'Edit EMI compliance records.', 1002),
            'emis.manage' => self::entry('Create & edit EMIs', 'compliance', 'Legacy manage EMIs permission.', 1003),
            'emis.delete' => self::entry('Delete EMIs', 'compliance', 'Delete EMI compliance records.', 1004),

            'challans.view' => self::entry('View challans', 'compliance', 'View challan records.', 1010),
            'challans.create' => self::entry('Create challans', 'compliance', 'Create challan records.', 1011),
            'challans.edit' => self::entry('Edit challans', 'compliance', 'Edit challan records.', 1012),
            'challans.manage' => self::entry('Create & edit challans', 'compliance', 'Legacy manage challans permission.', 1013),
            'challans.delete' => self::entry('Delete challans', 'compliance', 'Delete challan records.', 1014),

            'companies.view' => self::entry('View companies', 'companies', 'View company records.', 1100),
            'companies.create' => self::entry('Create company', 'companies', 'Create new companies.', 1101),
            'companies.update' => self::entry('Update company', 'companies', 'Update company profile details.', 1102),
            'companies.edit' => self::entry('Edit company', 'companies', 'Edit company profile details.', 1103),
            'companies.enter' => self::entry('Enter company', 'companies', 'Legacy enter company permission.', 1104),
            'companies.switch' => self::entry('Switch company', 'companies', 'Switch active company context.', 1105),
            'companies.settings' => self::entry('Company settings', 'companies', 'Manage company-level settings.', 1106),
            'companies.block' => self::entry('Block company', 'companies', 'Block or unblock companies.', 1107),
            'branches.view' => self::entry('View branches', 'companies', 'View branch records.', 1110),
            'branches.manage' => self::entry('Manage branches', 'companies', 'Create and edit branch records.', 1111),
            'branches.enter' => self::entry('Enter branch', 'companies', 'Enter branch operational context.', 1112),

            'roles.view' => self::entry('View roles', 'access', 'View role definitions.', 1200),
            'roles.manage' => self::entry('Manage role permissions', 'access', 'Assign permissions to roles.', 1201),
            'permissions.view' => self::entry('View permissions', 'access', 'View permission catalog.', 1202),
            'permissions.manage' => self::entry('Manage permissions', 'access', 'Manage permission catalog.', 1203),
            'users.view' => self::entry('View company users', 'access', 'View company user accounts.', 1210),
            'users.manage' => self::entry('Manage company users', 'access', 'Create and manage company user accounts.', 1211),
            'super_admins.view' => self::entry('View super admins', 'access', 'View super admin accounts.', 1220),
            'super_admins.manage' => self::entry('Manage super admins', 'access', 'Create and manage super admin accounts.', 1221),

            'notifications.view' => self::entry('View notifications', 'notifications', 'View in-app notifications.', 1300),
            'notifications.manage' => self::entry('Manage notifications', 'notifications', 'Mark notifications read and manage notification state.', 1301),
        ];
    }
}
