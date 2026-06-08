<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('brokers')) {
            Schema::create('brokers', function (Blueprint $table) {
                $table->id();
                $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
                $table->unsignedBigInteger('country_id')->nullable();
                $table->unsignedBigInteger('state_id')->nullable();
                $table->unsignedBigInteger('city_id')->nullable();
                $table->unsignedBigInteger('agency_id')->nullable();
                $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->string('name', 255);
                $table->string('gst_number', 255)->nullable();
                $table->string('pan_number', 255)->nullable();
                $table->text('address')->nullable();
                $table->string('contact_number', 255)->nullable();
                $table->softDeletes();
                $table->timestamps();

                $table->index('pan_number');
                $table->unique(['tenant_id', 'name']);
            });
        }

        if (! Schema::hasTable('customers')) {
            Schema::create('customers', function (Blueprint $table) {
                $table->id();
                $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
                $table->unsignedBigInteger('country_id')->nullable();
                $table->unsignedBigInteger('state_id')->nullable();
                $table->unsignedBigInteger('city_id')->nullable();
                $table->unsignedBigInteger('agency_id')->nullable();
                $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->string('name', 255);
                $table->string('gst_number', 255)->nullable();
                $table->string('pan_number', 255)->nullable();
                $table->text('address')->nullable();
                $table->string('contact_number', 255)->nullable();
                $table->softDeletes();
                $table->timestamps();

                $table->index('pan_number');
                $table->unique(['tenant_id', 'name']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
        Schema::dropIfExists('brokers');
    }
};
