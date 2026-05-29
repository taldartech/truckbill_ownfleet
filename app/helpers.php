<?php

use Carbon\Carbon;

if (! function_exists('formatDate')) {
    function formatDate($date, $format = 'd M Y', $default = '—')
    {
        return formatCarbonDate($date, $format, $default);
    }
}

if (! function_exists('formatDateTime')) {
    function formatDateTime($date, $format = 'd M Y h:i A', $default = '—')
    {
        return formatCarbonDate($date, $format, $default);
    }
}

if (! function_exists('formatCarbonDate')) {
    function formatCarbonDate($date, $format, $default)
    {
        try {
            return $date ? Carbon::parse($date)->format($format) : $default;
        } catch (\Exception $e) {
            return $default;
        }
    }
}
