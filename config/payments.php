<?php

return [
    'tds_submission_day' => (int) env('TDS_SUBMISSION_DAY', 3),

    'tds_percentages' => [
        'default' => (float) env('TDS_PERCENTAGE_DEFAULT', 20),
        'agency' => (float) env('TDS_PERCENTAGE_AGENCY', 2),
        'owner' => (float) env('TDS_PERCENTAGE_OWNER', 1),
    ],

    'gst_slabs' => explode(',', env('GST_SLABS', '5,12,18')),

    'truck_count_1' => env('TRUCK_COUNT_1', '1-10'),
    'truck_count_2' => env('TRUCK_COUNT_2', '11-50'),
    'truck_count_3' => env('TRUCK_COUNT_3', '50+'),

    'truck_rate_1' => (int) env('TRUCK_RATE_1', 1000),
    'truck_rate_2' => (int) env('TRUCK_RATE_2', 850),
    'truck_rate_3' => (int) env('TRUCK_RATE_3', 750),

    'lr_credits_1' => (int) env('LR_CREDITS_1', 100),
    'lr_credits_2' => (int) env('LR_CREDITS_2', 225),
    'lr_credits_3' => (int) env('LR_CREDITS_3', 350),

    'lr_credit_rate_1' => (int) env('LR_CREDIT_RATE_1', 1000),
    'lr_credit_rate_2' => (int) env('LR_CREDIT_RATE_2', 2000),
    'lr_credit_rate_3' => (int) env('LR_CREDIT_RATE_3', 3000),

    'annual_maintenance_agency' => (int) env('ANNUAL_MAINTENANCE_AGENCY', 10000),
    'annual_maintenance_broker' => (int) env('ANNUAL_MAINTENANCE_BROKER', 15000),
    'annual_maintenance_both' => (int) env('ANNUAL_MAINTENANCE_BOTH', 20000),

    'discount' => (int) env('DISCOUNT', 10),

    'service_gst_percentage' => (int) env('SERVICE_GST_PERCENTAGE', 18),

    // Razorpay Configuration
    'razorpay_key' => env('RAZORPAY_KEY', ''),
    'razorpay_secret' => env('RAZORPAY_SECRET', ''),
];
