<?php

use App\Enums\TripExpenseType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Add super_admin_id and drop tenant_id if present
        if (! Schema::hasColumn('trip_expense_types', 'super_admin_id')) {
            Schema::table('trip_expense_types', function (Blueprint $table) {
                $table->foreignUuid('super_admin_id')->nullable()->after('id')->constrained('users')->nullOnDelete();
            });
        }

        if (Schema::hasColumn('trip_expense_types', 'tenant_id')) {
            foreach (DB::table('trip_expense_types')->whereNotNull('tenant_id')->get() as $row) {
                $ownerId = DB::table('tenants')->where('id', $row->tenant_id)->value('owner_user_id');
                if ($ownerId) {
                    DB::table('trip_expense_types')->where('id', $row->id)->update(['super_admin_id' => $ownerId]);
                }
            }

            Schema::table('trip_expense_types', function (Blueprint $table) {
                try { $table->dropForeign(['tenant_id']); } catch (\Throwable) {}
                $table->dropColumn('tenant_id');
            });
        }

        // 2. Adjust indexes for super admin scoping
        if (Schema::hasIndex('trip_expense_types', 'trip_expense_types_name_unique')) {
            Schema::table('trip_expense_types', fn (Blueprint $t) => $t->dropUnique('trip_expense_types_name_unique'));
        }
        if (Schema::hasIndex('trip_expense_types', 'trip_expense_types_code_unique')) {
            Schema::table('trip_expense_types', fn (Blueprint $t) => $t->dropUnique('trip_expense_types_code_unique'));
        }
        if (! Schema::hasIndex('trip_expense_types', ['super_admin_id', 'is_active'])) {
            Schema::table('trip_expense_types', fn (Blueprint $t) => $t->index(['super_admin_id', 'is_active']));
        }

        // 3. Ensure global DIESEL and OTHER exist; remove other global types
        foreach (['DIESEL' => 'Diesel', 'OTHER' => 'Other'] as $code => $name) {
            DB::table('trip_expense_types')->updateOrInsert(
                ['code' => $code],
                ['super_admin_id' => null, 'name' => $name, 'is_active' => true, 'deleted_at' => null, 'updated_at' => now()]
            );
        }
        DB::table('trip_expense_types')->whereNull('super_admin_id')->whereNotIn('code', ['DIESEL', 'OTHER'])->delete();

        // 4. Backfill distinct expense types from super admin companies' trip_expenses
        $superAdminIds = DB::table('users')->where('user_role_id', 2)->pluck('id')
            ->merge(DB::table('tenants')->whereNotNull('owner_user_id')->pluck('owner_user_id'))
            ->unique()->filter();

        foreach ($superAdminIds as $superAdminId) {
            $tenantIds = DB::table('tenants')->where('owner_user_id', $superAdminId)->pluck('id');
            if ($tenantIds->isEmpty()) {
                continue;
            }

            $types = DB::table('trip_expenses')
                ->whereIn('tenant_id', $tenantIds)
                ->whereNotNull('expense_type')->where('expense_type', '!=', '')
                ->distinct()->pluck('expense_type')
                ->reject(fn ($c) => in_array(strtoupper(trim((string) $c)), ['DIESEL', 'OTHER'], true))
                ->map(fn ($c) => [
                    'code' => strtoupper(trim((string) $c)),
                    'name' => TripExpenseType::tryFrom(strtoupper(trim((string) $c)))?->label()
                        ?? ucwords(str_replace('_', ' ', strtolower(trim((string) $c)))),
                ]);

            $others = DB::table('trip_expenses')
                ->whereIn('tenant_id', $tenantIds)
                ->where('expense_type', 'OTHER')
                ->whereNotNull('other_type')->where('other_type', '!=', '')
                ->distinct()->pluck('other_type')
                ->map(fn ($o) => trim((string) $o))
                ->reject(fn ($name) => $name === '' || in_array(strtoupper(Str::slug($name, '_')), ['DIESEL', 'OTHER'], true))
                ->map(fn ($name) => ['code' => strtoupper(Str::slug($name, '_')), 'name' => $name]);

            foreach ($types->concat($others) as $item) {
                $exists = DB::table('trip_expense_types')
                    ->where(fn ($q) => $q->where('super_admin_id', $superAdminId)->orWhereNull('super_admin_id'))
                    ->where(fn ($q) => $q->where('code', $item['code'])->orWhere('name', $item['name']))
                    ->whereNull('deleted_at')
                    ->exists();

                if (! $exists) {
                    DB::table('trip_expense_types')->insert([
                        'super_admin_id' => $superAdminId,
                        'code' => $item['code'],
                        'name' => $item['name'],
                        'is_active' => true,
                        'created_by' => $superAdminId,
                        'updated_by' => $superAdminId,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('trip_expense_types', 'super_admin_id')) {
            Schema::table('trip_expense_types', function (Blueprint $table) {
                try { $table->dropForeign(['super_admin_id']); } catch (\Throwable) {}
                $table->dropColumn('super_admin_id');
            });
        }

        if (! Schema::hasColumn('trip_expense_types', 'tenant_id')) {
            Schema::table('trip_expense_types', function (Blueprint $table) {
                $table->foreignUuid('tenant_id')->nullable()->constrained('tenants')->nullOnDelete();
                $table->unique('name');
                $table->unique('code');
            });
        }
    }
};
