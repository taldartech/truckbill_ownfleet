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

if (! function_exists('getIndianCurrency')) {
    function getIndianCurrency(float $number): string
    {
        $decimal = round($number - ($no = floor($number)), 2) * 100;
        $hundred = null;
        $digits_length = strlen((string) $no);
        $i = 0;
        $str = [];
        $words = [
            0 => '',
            1 => 'one',
            2 => 'two',
            3 => 'three',
            4 => 'four',
            5 => 'five',
            6 => 'six',
            7 => 'seven',
            8 => 'eight',
            9 => 'nine',
            10 => 'ten',
            11 => 'eleven',
            12 => 'twelve',
            13 => 'thirteen',
            14 => 'fourteen',
            15 => 'fifteen',
            16 => 'sixteen',
            17 => 'seventeen',
            18 => 'eighteen',
            19 => 'nineteen',
            20 => 'twenty',
            30 => 'thirty',
            40 => 'forty',
            50 => 'fifty',
            60 => 'sixty',
            70 => 'seventy',
            80 => 'eighty',
            90 => 'ninety',
        ];
        $digits = ['', 'hundred', 'thousand', 'lakh', 'crore'];

        while ($i < $digits_length) {
            $divider = $i == 2 ? 10 : 100;
            $chunk = floor($no % $divider);
            $no = floor($no / $divider);
            $i += $divider == 10 ? 1 : 2;

            if ($chunk) {
                $plural = ($counter = count($str)) && $chunk > 9 ? 's' : null;
                $hundred = $counter == 1 && $str[0] ? ' and ' : null;
                $str[] = $chunk < 21
                    ? $words[$chunk].' '.$digits[$counter].$plural.' '.$hundred
                    : $words[floor($chunk / 10) * 10].' '.$words[$chunk % 10].' '.$digits[$counter].$plural.' '.$hundred;
            } else {
                $str[] = null;
            }
        }

        $rupees = implode('', array_reverse($str));
        $paise = $decimal
            ? ($rupees ? 'and ' : ' ').(
                $decimal < 21
                    ? $words[(int) $decimal]
                    : $words[(int) floor($decimal / 10) * 10].' '.$words[(int) $decimal % 10]
            ).' Paise'
            : '';

        return ucwords(($rupees ? $rupees.'Rupees ' : '').$paise);
    }
}

if (! function_exists('versioned_asset')) {
    function versioned_asset(string $path): string
    {
        $path = ltrim($path, '/');

        return asset($path) . '?v=' . config('app.asset_version');
    }
}
