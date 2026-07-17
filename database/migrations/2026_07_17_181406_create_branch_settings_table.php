<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('branch_settings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('branch_id')->unique()->constrained('branches')->cascadeOnDelete();
            $table->string('trip_initial', 50);
            $table->string('trip_separator_1', 5)->default('-');
            $table->string('trip_year_type', 20)->default('year');
            $table->unsignedTinyInteger('trip_year_digits')->default(4);
            $table->string('trip_separator_2', 5)->default('-');
            $table->unsignedTinyInteger('trip_sequence_digits')->default(4);
            $table->string('invoice_initial', 50);
            $table->string('invoice_separator_1', 5)->default('-');
            $table->string('invoice_year_type', 20)->default('year');
            $table->unsignedTinyInteger('invoice_year_digits')->default(4);
            $table->string('invoice_separator_2', 5)->default('-');
            $table->unsignedTinyInteger('invoice_sequence_digits')->default(4);
            $table->timestamps();
        });

        $branches = DB::table('branches')->select('*')->get();
        $now = now();

        foreach ($branches as $branch) {
            $branchCode = sprintf('BR%03d', (int) $branch->id);
            $hasLegacyFormats = Schema::hasColumn('branches', 'trip_number_initial');

            $tripYearType = $hasLegacyFormats
                ? $this->mapYearMode((string) ($branch->trip_number_year_mode ?? 'calendar'))
                : 'year';
            $invoiceYearType = $hasLegacyFormats
                ? $this->mapYearMode((string) ($branch->invoice_number_year_mode ?? 'calendar'))
                : 'year';

            $tripInitial = $hasLegacyFormats && filled($branch->trip_number_initial ?? null)
                ? (string) $branch->trip_number_initial
                : 'TRIP-'.$branchCode;
            $invoiceInitial = $hasLegacyFormats && filled($branch->invoice_number_initial ?? null)
                ? (string) $branch->invoice_number_initial
                : 'INV-'.$branchCode;

            // Preserve historical TRIP-BR###-YYYY-#### when legacy initial was only "TRIP".
            if ($hasLegacyFormats && $tripInitial === 'TRIP') {
                $tripInitial = 'TRIP-'.$branchCode;
            }

            if ($hasLegacyFormats && $invoiceInitial === 'INV') {
                $invoiceInitial = 'INV-'.$branchCode;
            }

            DB::table('branch_settings')->insert([
                'branch_id' => $branch->id,
                'trip_initial' => $tripInitial,
                'trip_separator_1' => $hasLegacyFormats
                    ? (string) ($branch->trip_number_separator_1 ?? '-')
                    : '-',
                'trip_year_type' => $tripYearType,
                'trip_year_digits' => 4,
                'trip_separator_2' => $hasLegacyFormats
                    ? (string) ($branch->trip_number_separator_2 ?? '-')
                    : '-',
                'trip_sequence_digits' => 4,
                'invoice_initial' => $invoiceInitial,
                'invoice_separator_1' => $hasLegacyFormats
                    ? (string) ($branch->invoice_number_separator_1 ?? '-')
                    : '-',
                'invoice_year_type' => $invoiceYearType,
                'invoice_year_digits' => 4,
                'invoice_separator_2' => $hasLegacyFormats
                    ? (string) ($branch->invoice_number_separator_2 ?? '-')
                    : '-',
                'invoice_sequence_digits' => 4,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        Schema::table('branches', function (Blueprint $table): void {
            $drop = [];

            foreach ([
                'trip_sequence_start',
                'trip_number_initial',
                'trip_number_separator_1',
                'trip_number_year_mode',
                'trip_number_separator_2',
                'invoice_number_initial',
                'invoice_number_separator_1',
                'invoice_number_year_mode',
                'invoice_number_separator_2',
                'invoice_sequence_start',
            ] as $column) {
                if (Schema::hasColumn('branches', $column)) {
                    $drop[] = $column;
                }
            }

            if ($drop !== []) {
                $table->dropColumn($drop);
            }
        });

        $duplicates = DB::table('trips')
            ->select('tenant_id', 'branch_id', 'trip_number', DB::raw('COUNT(*) as aggregate'))
            ->whereNotNull('trip_number')
            ->whereNotNull('branch_id')
            ->groupBy('tenant_id', 'branch_id', 'trip_number')
            ->having('aggregate', '>', 1)
            ->exists();

        if ($duplicates) {
            throw new RuntimeException('Cannot add unique trip numbers: duplicate (tenant_id, branch_id, trip_number) rows exist.');
        }

        Schema::table('trips', function (Blueprint $table): void {
            $table->unique(['tenant_id', 'branch_id', 'trip_number'], 'trips_tenant_branch_trip_number_unique');
        });
    }

    public function down(): void
    {
        Schema::table('trips', function (Blueprint $table): void {
            $table->dropUnique('trips_tenant_branch_trip_number_unique');
        });

        Schema::table('branches', function (Blueprint $table): void {
            if (! Schema::hasColumn('branches', 'trip_sequence_start')) {
                $table->unsignedBigInteger('trip_sequence_start')->default(1)->after('head_office');
            }
        });

        Schema::dropIfExists('branch_settings');
    }

    private function mapYearMode(string $mode): string
    {
        return match (strtolower(trim($mode))) {
            'blank', 'none', 'off' => 'blank',
            'financial', 'financial_year', 'fy' => 'financial_year',
            default => 'year',
        };
    }
};
