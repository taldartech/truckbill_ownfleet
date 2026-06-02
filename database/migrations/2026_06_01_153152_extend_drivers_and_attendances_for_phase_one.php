<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('drivers', function (Blueprint $table) {
            $table->text('address')->nullable()->after('license_expiry_date');
            $table->string('aadhaar_number', 12)->nullable()->after('address');
            $table->string('pan_number', 10)->nullable()->after('aadhaar_number');
            $table->date('joining_date')->nullable()->after('pan_number');
            $table->string('emergency_contact_name')->nullable()->after('joining_date');
            $table->string('emergency_contact_phone', 20)->nullable()->after('emergency_contact_name');
        });

        Schema::table('attendances', function (Blueprint $table) {
            $table->string('marked_via', 30)->default('admin_panel')->after('status');
            $table->text('temporary_assignment_remarks')->nullable()->after('temporary_driver_id');
            $table->string('wage_payment_status', 20)->default('unpaid')->after('wage_amount');
            $table->timestamp('wage_paid_at')->nullable()->after('wage_payment_status');
            $table->text('wage_transfer_notes')->nullable()->after('wage_paid_at');

            $table->index(['tenant_id', 'wage_payment_status']);
        });
    }

    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'wage_payment_status']);
            $table->dropColumn([
                'marked_via',
                'temporary_assignment_remarks',
                'wage_payment_status',
                'wage_paid_at',
                'wage_transfer_notes',
            ]);
        });

        Schema::table('drivers', function (Blueprint $table) {
            $table->dropColumn([
                'address',
                'aadhaar_number',
                'pan_number',
                'joining_date',
                'emergency_contact_name',
                'emergency_contact_phone',
            ]);
        });
    }
};
