<?php

namespace App\Models;

use App\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class SubscriptionPayment extends Model
{
    protected $fillable = [
        'tenant_id',
        'type',
        'status',
        'razorpay_order_id',
        'razorpay_payment_id',
        'razorpay_signature',
        'promo_code',
        'quantities',
        'subtotal',
        'promo_discount',
        'gst_amount',
        'total_amount',
        'catalog_snapshot',
        'usage_snapshot_at_purchase',
        'razorpay_response',
    ];

    protected function casts(): array
    {
        return [
            'quantities' => 'array',
            'subtotal' => 'decimal:2',
            'promo_discount' => 'decimal:2',
            'gst_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'catalog_snapshot' => 'array',
            'usage_snapshot_at_purchase' => 'array',
            'razorpay_response' => 'array',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function upcomingSubscription(): HasOne
    {
        return $this->hasOne(UpcomingSubscription::class, 'payment_id');
    }
}
