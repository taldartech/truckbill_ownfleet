<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->foreignUuid('owner_user_id')->nullable()->after('id')->constrained('users')->nullOnDelete();
            $table->string('short_name')->nullable()->unique()->after('slug');
            $table->string('gst_number')->nullable()->after('timezone');
            $table->string('pan_number')->nullable()->after('gst_number');
            $table->string('gst_scheme')->nullable()->after('pan_number');
            $table->string('gst_type')->nullable()->after('gst_scheme');
            $table->text('address')->nullable()->after('gst_type');
            $table->string('contact_person')->nullable()->after('address');
            $table->string('contact_number')->nullable()->after('contact_person');
            $table->date('plan_end_date')->nullable()->after('contact_number');
            $table->unsignedInteger('lr_credits')->default(150)->after('plan_end_date');
            $table->unsignedInteger('truck_credits')->default(0)->after('lr_credits');
            $table->boolean('is_blocked')->default(false)->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropForeign(['owner_user_id']);
            $table->dropColumn([
                'owner_user_id',
                'short_name',
                'gst_number',
                'pan_number',
                'gst_scheme',
                'gst_type',
                'address',
                'contact_person',
                'contact_number',
                'plan_end_date',
                'lr_credits',
                'truck_credits',
                'is_blocked',
            ]);
        });
    }
};
