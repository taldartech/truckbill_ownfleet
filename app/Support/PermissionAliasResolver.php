<?php

namespace App\Support;

final class PermissionAliasResolver
{
    /**
     * @var array<string, list<string>>
     */
    private const SYNONYMS = [
        'attendance.bulk_mark' => ['attendances.bulk_manage'],
        'attendances.bulk_manage' => ['attendance.bulk_mark'],
        'attendance_reports.view' => ['reports.view'],
        'reports.view' => ['attendance_reports.view'],
        'attendance_reports.export' => ['reports.export'],
        'reports.export' => ['attendance_reports.export'],
        'companies.switch' => ['companies.enter'],
        'companies.enter' => ['companies.switch'],
        'companies.edit' => ['companies.update'],
        'companies.update' => ['companies.edit'],
        'vehicle_documents.create' => ['vehicle_documents.manage'],
        'vehicle_documents.manage' => ['vehicle_documents.create', 'vehicle_documents.edit'],
        'driver_advances.manage' => ['drivers.manage'],
        'drivers.archive' => ['drivers.manage'],
        'trips.expenses' => ['trips.edit', 'trips.manage'],
        'trips.change_status' => ['trips.edit', 'trips.manage'],
        'trip_expense_types.view' => ['trips.expenses', 'trips.view'],
    ];

    /**
     * @param  list<string>  $ownedSlugs
     */
    public static function matches(string $requestedSlug, array $ownedSlugs): bool
    {
        foreach (self::SYNONYMS[$requestedSlug] ?? [] as $synonym) {
            if (in_array($synonym, $ownedSlugs, true)) {
                return true;
            }
        }

        if (str_ends_with($requestedSlug, '.manage')) {
            return self::matchesLegacyManage($requestedSlug, $ownedSlugs);
        }

        if (str_ends_with($requestedSlug, '.create') || str_ends_with($requestedSlug, '.edit')) {
            return self::matchesGranularAction($requestedSlug, $ownedSlugs);
        }

        return false;
    }

    /**
     * @param  list<string>  $ownedSlugs
     */
    private static function matchesLegacyManage(string $requestedSlug, array $ownedSlugs): bool
    {
        if (in_array($requestedSlug, $ownedSlugs, true)) {
            return true;
        }

        $base = substr($requestedSlug, 0, -strlen('.manage'));

        if (in_array("{$base}.create", $ownedSlugs, true)) {
            return true;
        }

        if (
            in_array("{$base}.create", $ownedSlugs, true)
            && in_array("{$base}.edit", $ownedSlugs, true)
        ) {
            return true;
        }

        return false;
    }

    /**
     * @param  list<string>  $ownedSlugs
     */
    private static function matchesGranularAction(string $requestedSlug, array $ownedSlugs): bool
    {
        if (in_array($requestedSlug, $ownedSlugs, true)) {
            return true;
        }

        $base = str_contains($requestedSlug, '.create')
            ? substr($requestedSlug, 0, -strlen('.create'))
            : substr($requestedSlug, 0, -strlen('.edit'));

        return in_array("{$base}.manage", $ownedSlugs, true);
    }
}
