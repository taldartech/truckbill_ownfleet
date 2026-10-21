<?php

$website = rtrim((string) env('MAIL_BRAND_WEBSITE', env('WEBSITE_URL', 'https://www.truckbill.in')), '/');

return [
    'name' => 'TruckBill',
    'tagline' => 'Transport. Billing. Simplified.',
    'website' => $website,
    'support_email' => env('MAIL_BRAND_SUPPORT_EMAIL', env('SUPPORT_EMAIL', 'support@truckbill.in')),

    // Email images are hosted with the marketing website (website/images/email).
    'asset_url' => rtrim((string) env('MAIL_BRAND_ASSET_URL', $website.'/images/email'), '/'),
    'logo_url' => env('MAIL_BRAND_LOGO_URL'),

    'links' => [
        'terms' => $website.'/policies/terms-and-conditions.html',
        'privacy' => $website.'/policies/privacy-policy.html',
    ],

    // Icons without a URL are hidden from the email footer.
    'social' => [
        'linkedin' => env('MAIL_BRAND_LINKEDIN_URL', 'https://www.linkedin.com/showcase/truckbill/'),
        'instagram' => env('MAIL_BRAND_INSTAGRAM_URL', 'https://www.instagram.com/truckbill.in'),
        'youtube' => env('MAIL_BRAND_YOUTUBE_URL'),
        'x' => env('MAIL_BRAND_X_URL'),
    ],

    'colors' => [
        'primary' => '#17A762',
        'primary_dark' => '#12894F',
        'heading' => '#0B1324',
        'text' => '#374151',
        'muted' => '#6B7280',
        'border' => '#E5E7EB',
        'background' => '#F2F3F8',
        'surface' => '#FFFFFF',
        'panel' => '#F1F9F5',
        'help' => '#F5F6FB',
        'footer' => '#F5F8FD',
        'gradient_start' => '#27A075',
        'gradient_middle' => '#279CB0',
        'gradient_end' => '#1D97E2',
        'danger' => '#DC2626',
        'warning' => '#D97706',
    ],
];
