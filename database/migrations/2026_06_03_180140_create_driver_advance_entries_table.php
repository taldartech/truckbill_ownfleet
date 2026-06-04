<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('driver_advance_entries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('driver_id')->constrained('drivers')->cascadeOnDelete();
            $table->string('entry_type', 2);
            $table->decimal('amount', 12, 2);
            $table->date('entry_date');
            $table->text('remarks')->nullable();
            $table->foreignUuid('created_by')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'driver_id', 'entry_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('driver_advance_entries');
    }
};
