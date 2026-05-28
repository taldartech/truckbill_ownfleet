<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('vehicle_documents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('vehicle_id')->constrained('vehicles')->cascadeOnDelete();
            $table->string('document_type');
            $table->date('issue_date')->nullable();
            $table->date('expiry_date')->nullable();
            $table->string('file_url')->nullable();
            $table->unsignedInteger('reminder_days_before')->default(7);
            $table->foreignUuid('created_by')->nullable();
            $table->foreignUuid('updated_by')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'expiry_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vehicle_documents');
    }
};
