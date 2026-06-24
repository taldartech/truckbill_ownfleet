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
        Schema::create('branches', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('company_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('branch_name', 150);
            $table->text('branch_address')->nullable();
            $table->string('branch_contact_number', 20);
            $table->boolean('head_office')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'branch_name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('branches');
    }
};
