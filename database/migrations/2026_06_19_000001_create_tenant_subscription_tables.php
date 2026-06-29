<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->uuid('tenant_id')->unique();
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->string('status');
            $table->date('starts_on');
            $table->date('ends_on');
            $table->date('trial_ends_on')->nullable();
            $table->unsignedSmallInteger('included_branches')->default(2);
            $table->unsignedSmallInteger('users_per_branch')->default(3);
            $table->unsignedInteger('included_lr_credits')->default(0);
            $table->unsignedInteger('included_trucks')->default(50);
            $table->unsignedSmallInteger('addon_branches')->default(0);
            $table->unsignedSmallInteger('addon_users')->default(0);
            $table->unsignedSmallInteger('addon_trucks')->default(0);
            $table->unsignedInteger('addon_lr_credits')->default(0);
            $table->unsignedInteger('lr_credits_remaining')->default(0);
            $table->timestamp('last_renewed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('subscription_payments', function (Blueprint $table) {
            $table->id();
            $table->uuid('tenant_id');
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->string('type');
            $table->string('status');
            $table->string('razorpay_order_id')->nullable()->unique();
            $table->string('razorpay_payment_id')->nullable();
            $table->string('razorpay_signature')->nullable();
            $table->string('promo_code')->nullable();
            $table->json('quantities')->nullable();
            $table->decimal('subtotal', 12, 2);
            $table->decimal('promo_discount', 12, 2)->default(0);
            $table->decimal('gst_amount', 12, 2)->default(0);
            $table->decimal('total_amount', 12, 2);
            $table->json('catalog_snapshot')->nullable();
            $table->json('usage_snapshot_at_purchase')->nullable();
            $table->json('razorpay_response')->nullable();
            $table->timestamps();
        });

        Schema::create('upcoming_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->uuid('tenant_id');
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreignId('payment_id')->constrained('subscription_payments')->cascadeOnDelete();
            $table->unsignedSmallInteger('included_branches');
            $table->unsignedSmallInteger('users_per_branch');
            $table->unsignedInteger('included_lr_credits')->default(0);
            $table->unsignedInteger('included_trucks')->default(50);
            $table->unsignedSmallInteger('addon_branches')->default(0);
            $table->unsignedSmallInteger('addon_users')->default(0);
            $table->unsignedSmallInteger('addon_trucks')->default(0);
            $table->unsignedInteger('addon_lr_credits')->default(0);
            $table->string('status')->default('pending');
            $table->json('catalog_snapshot')->nullable();
            $table->json('promo_snapshot')->nullable();
            $table->timestamps();
        });

        Schema::create('subscription_events', function (Blueprint $table) {
            $table->id();
            $table->uuid('tenant_id');
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->string('event_type');
            $table->json('metadata')->nullable();
            $table->foreignId('payment_id')->nullable()->constrained('subscription_payments')->nullOnDelete();
            $table->uuid('actor_user_id')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_events');
        Schema::dropIfExists('upcoming_subscriptions');
        Schema::dropIfExists('subscription_payments');
        Schema::dropIfExists('tenant_subscriptions');
    }
};
