<?php

use App\Support\TyreLayoutDefaults;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tyre_layouts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('code', 64);
            $table->string('name');
            $table->string('layout_type', 32)->default('standard');
            $table->unsignedTinyInteger('steer_axles')->default(1);
            $table->json('dual_axles')->nullable();
            $table->json('single_axles')->nullable();
            $table->unsignedTinyInteger('spare_count')->default(0);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'code']);
        });

        if (Schema::hasTable('tenants')) {
            $tenantIds = DB::table('tenants')->pluck('id');
            foreach ($tenantIds as $tenantId) {
                TyreLayoutDefaults::seedForTenant((string) $tenantId);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('tyre_layouts');
    }
};
