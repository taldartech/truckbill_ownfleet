<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('drivers', function (Blueprint $table) {
            $table->string('employee_code', 50)->nullable()->after('name');
            $table->string('father_name', 150)->nullable()->after('employee_code');
            $table->string('uan_number', 30)->nullable()->after('pan_number');
            $table->string('esic_number', 30)->nullable()->after('uan_number');
            $table->string('bank_account_number', 40)->nullable()->after('esic_number');
            $table->string('bank_ifsc', 20)->nullable()->after('bank_account_number');
        });

        Schema::table('monthly_driver_wages', function (Blueprint $table) {
            $table->unsignedSmallInteger('calendar_days')->default(0)->after('month');
            $table->unsignedSmallInteger('present_days')->default(0)->after('payable_days');
            $table->unsignedSmallInteger('absent_days')->default(0)->after('present_days');
            $table->unsignedSmallInteger('leave_days')->default(0)->after('absent_days');
            $table->decimal('gross_earning', 12, 2)->default(0)->after('total_wage');
            $table->decimal('absent_deduction', 12, 2)->default(0)->after('gross_earning');
            $table->decimal('base_salary', 12, 2)->default(0)->after('absent_deduction');
            $table->decimal('hra', 12, 2)->default(0)->after('base_salary');
            $table->decimal('holiday_allowance', 12, 2)->default(0)->after('hra');
            $table->decimal('pf', 12, 2)->default(0)->after('holiday_allowance');
            $table->decimal('esi', 12, 2)->default(0)->after('pf');
            $table->decimal('other_deduction', 12, 2)->default(0)->after('esi');
            $table->decimal('advance_deducted', 12, 2)->default(0)->after('other_deduction');
            $table->decimal('total_advance', 12, 2)->default(0)->after('advance_deducted');
            $table->decimal('net_salary', 12, 2)->default(0)->after('total_advance');
            $table->boolean('round_net')->default(false)->after('net_salary');
            $table->text('remarks')->nullable()->after('payment_notes');
        });

        Schema::table('attendance_month_locks', function (Blueprint $table) {
            $table->json('vehicle_settlement')->nullable()->after('locked_by');
        });
    }

    public function down(): void
    {
        Schema::table('attendance_month_locks', function (Blueprint $table) {
            $table->dropColumn('vehicle_settlement');
        });

        Schema::table('monthly_driver_wages', function (Blueprint $table) {
            $table->dropColumn([
                'calendar_days',
                'present_days',
                'absent_days',
                'leave_days',
                'gross_earning',
                'absent_deduction',
                'base_salary',
                'hra',
                'holiday_allowance',
                'pf',
                'esi',
                'other_deduction',
                'advance_deducted',
                'total_advance',
                'net_salary',
                'round_net',
                'remarks',
            ]);
        });

        Schema::table('drivers', function (Blueprint $table) {
            $table->dropColumn([
                'employee_code',
                'father_name',
                'uan_number',
                'esic_number',
                'bank_account_number',
                'bank_ifsc',
            ]);
        });
    }
};
