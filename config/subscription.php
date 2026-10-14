<?php

return [
    'product' => 'ownfleet',
    'admin_api_url' => env('ADMIN_API_URL'),
    'signing_secret' => env('ONBOARDING_SIGNING_SECRET'),
    'razorpay_key' => env('RAZORPAY_KEY'),
    'razorpay_secret' => env('RAZORPAY_SECRET'),
    'trial_days' => (int) env('SUBSCRIPTION_TRIAL_DAYS', 15),
    'default_catalog' => [
        'included_branches' => (int) env('SUBSCRIPTION_DEFAULT_INCLUDED_BRANCHES', 2),
        'users_per_branch' => (int) env('SUBSCRIPTION_DEFAULT_USERS_PER_BRANCH', 3),
        'included_trucks' => (int) env('SUBSCRIPTION_DEFAULT_INCLUDED_TRUCKS', 50),
        'included_lr_credits' => (int) env('SUBSCRIPTION_DEFAULT_INCLUDED_LR_CREDITS', 0),
    ],
];
