<?php

namespace App\Support;

use App\DocumentType;
use App\Enums\VehicleDocumentType;
use Illuminate\Support\Str;

class DocumentTypeDefaults
{
    /**
     * Request-scoped label map keyed by tenant id then document type code.
     *
     * @var array<string, array<string, string>>
     */
    private static array $labelsByTenant = [];

    /**
     * @return list<array{name: string, code: string}>
     */
    public static function presets(): array
    {
        return array_map(
            fn (VehicleDocumentType $type) => [
                'name' => $type->label(),
                'code' => $type->value,
            ],
            VehicleDocumentType::cases(),
        );
    }

    public static function seedForTenant(string $tenantId): void
    {
        if (DocumentType::query()->withoutGlobalScopes()->where('tenant_id', $tenantId)->exists()) {
            return;
        }

        foreach (self::presets() as $preset) {
            DocumentType::query()->create([
                'tenant_id' => $tenantId,
                'name' => $preset['name'],
                'code' => $preset['code'],
                'is_active' => true,
            ]);
        }

        unset(self::$labelsByTenant[$tenantId]);
    }

    public static function generateCode(string $name, string $tenantId, ?int $ignoreId = null): string
    {
        $base = Str::snake(Str::lower(trim($name)));
        $base = preg_replace('/[^a-z0-9_]+/', '_', $base) ?? 'type';
        $base = trim($base, '_');

        if ($base === '') {
            $base = 'type';
        }

        $code = $base;
        $suffix = 1;

        while (self::codeExists($tenantId, $code, $ignoreId)) {
            $code = $base.'_'.$suffix;
            $suffix++;
        }

        return $code;
    }

    public static function labelFor(string $tenantId, string $code): ?string
    {
        return self::labelsForTenant($tenantId)[$code] ?? null;
    }

    /**
     * @return array<string, string>
     */
    public static function labelsForTenant(string $tenantId): array
    {
        if (! isset(self::$labelsByTenant[$tenantId])) {
            self::$labelsByTenant[$tenantId] = DocumentType::query()
                ->withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->pluck('name', 'code')
                ->all();
        }

        return self::$labelsByTenant[$tenantId];
    }

    public static function forgetLabelCache(?string $tenantId = null): void
    {
        if ($tenantId === null) {
            self::$labelsByTenant = [];

            return;
        }

        unset(self::$labelsByTenant[$tenantId]);
    }

    private static function codeExists(string $tenantId, string $code, ?int $ignoreId): bool
    {
        $query = DocumentType::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('code', $code);

        if ($ignoreId !== null) {
            $query->where('id', '!=', $ignoreId);
        }

        return $query->exists();
    }
}
