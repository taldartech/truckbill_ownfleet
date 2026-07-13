<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->foreignUuid('trip_id')->constrained('trips')->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained('customers')->restrictOnDelete();
            $table->string('invoice_number');
            $table->string('registration_type')->nullable();
            $table->string('tax_liability')->nullable();
            $table->string('document_type');
            $table->decimal('gst_rate', 8, 3)->default(0);
            $table->unsignedBigInteger('supplier_state_id')->nullable();
            $table->unsignedBigInteger('customer_state_id')->nullable();
            $table->decimal('freight_amount', 12, 2)->default(0);
            $table->decimal('cgst_rate', 8, 3)->default(0);
            $table->decimal('cgst_amount', 12, 2)->default(0);
            $table->decimal('sgst_rate', 8, 3)->default(0);
            $table->decimal('sgst_amount', 12, 2)->default(0);
            $table->decimal('igst_rate', 8, 3)->default(0);
            $table->decimal('igst_amount', 12, 2)->default(0);
            $table->decimal('gst_amount', 12, 2)->default(0);
            $table->decimal('total_amount', 12, 2)->default(0);
            $table->date('invoice_date');
            $table->date('due_date')->nullable();
            $table->string('payment_status')->default('pending');
            $table->decimal('paid_amount', 12, 2)->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('supplier_state_id')->references('id')->on('states')->nullOnDelete();
            $table->foreign('customer_state_id')->references('id')->on('states')->nullOnDelete();

            $table->unique(['tenant_id', 'branch_id', 'invoice_number']);
            $table->index(['tenant_id', 'branch_id', 'payment_status']);
            $table->index(['tenant_id', 'due_date']);
            $table->index(['trip_id', 'customer_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
