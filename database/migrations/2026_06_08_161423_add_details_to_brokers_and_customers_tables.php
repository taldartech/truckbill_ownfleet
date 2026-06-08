<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['brokers', 'customers'] as $tableName) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                if (! Schema::hasColumn($tableName, 'gst_number')) {
                    $table->string('gst_number', 255)->nullable()->after('name');
                }

                if (! Schema::hasColumn($tableName, 'pan_number')) {
                    $table->string('pan_number', 255)->nullable()->after('gst_number');
                    $table->index('pan_number');
                }

                if (! Schema::hasColumn($tableName, 'address')) {
                    $table->text('address')->nullable()->after('pan_number');
                }

                if (! Schema::hasColumn($tableName, 'contact_number')) {
                    $table->string('contact_number', 255)->nullable()->after('address');
                }

                if (! Schema::hasColumn($tableName, 'created_by')) {
                    $table->foreignUuid('created_by')->nullable()->after('agency_id')->constrained('users')->nullOnDelete();
                }

                if (! Schema::hasColumn($tableName, 'updated_by')) {
                    $table->foreignUuid('updated_by')->nullable()->after('created_by')->constrained('users')->nullOnDelete();
                }
            });
        }
    }

    public function down(): void
    {
        foreach (['brokers', 'customers'] as $tableName) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                if (Schema::hasColumn($tableName, 'updated_by')) {
                    $table->dropForeign(['updated_by']);
                    $table->dropColumn('updated_by');
                }

                if (Schema::hasColumn($tableName, 'created_by')) {
                    $table->dropForeign(['created_by']);
                    $table->dropColumn('created_by');
                }

                if (Schema::hasColumn($tableName, 'contact_number')) {
                    $table->dropColumn('contact_number');
                }

                if (Schema::hasColumn($tableName, 'address')) {
                    $table->dropColumn('address');
                }

                if (Schema::hasColumn($tableName, 'pan_number')) {
                    $table->dropIndex(['pan_number']);
                    $table->dropColumn('pan_number');
                }

                if (Schema::hasColumn($tableName, 'gst_number')) {
                    $table->dropColumn('gst_number');
                }
            });
        }
    }
};
