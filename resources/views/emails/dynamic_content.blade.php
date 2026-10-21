@extends('emails.layouts.master')

@section('title', $emailSubject ?? 'Notification')

@section('content')
@php
    $c = config('mail-brand.colors');
    $formattedHtml = $bodyHtml;

    // Lift a leading "Hello {{name}}," paragraph into the greeting slot above the headline.
    $greeting = null;
    if (preg_match('/^\s*<p>\s*((?:Hello|Hi|Dear)\b[^<]{0,150})<\/p>/i', $formattedHtml, $greetingMatch)) {
        $greeting = html_entity_decode(trim($greetingMatch[1]), ENT_QUOTES | ENT_HTML5);
        $formattedHtml = substr($formattedHtml, strlen($greetingMatch[0]));
    }

    $headline = filled($emailSubject ?? null)
        ? trim((string) preg_replace('/\s*\|\|\s*'.preg_quote(config('mail-brand.name'), '/').'\s*$/i', '', $emailSubject))
        : null;

    // Convert "<strong>Key:</strong> value<br>" blocks into a details panel.
    $formattedHtml = preg_replace_callback('/<p>\s*(<strong>.*?(?:<br\s*\/?>).*?)<\/p>/is', function ($matches) use ($c) {
        $lines = array_values(array_filter(array_map('trim', preg_split('/<br\s*\/?>/i', $matches[1])), fn ($line) => $line !== ''));
        $last = count($lines) - 1;
        $tableHtml = '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:8px 0 24px 0;"><tr><td style="padding:12px 24px;background-color:'.$c['panel'].';border-radius:14px;">'
            .'<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="font-size:15px;line-height:1.5;">';

        foreach ($lines as $index => $line) {
            $border = $index < $last ? 'border-bottom:1px solid #DDEBE3;' : '';

            if (preg_match('/<strong>(.*?)<\/strong>(.*)/is', $line, $parts)) {
                $key = rtrim(trim(strip_tags($parts[1])), ':');
                $val = trim($parts[2]);
                $tableHtml .= '<tr><td width="40%" valign="top" style="width:40%;padding:10px 12px 10px 0;'.$border.'color:'.$c['muted'].';">'.$key.'</td>'
                    .'<td valign="top" style="padding:10px 0;'.$border.'color:'.$c['heading'].';font-weight:600;">'.$val.'</td></tr>';
            } else {
                $tableHtml .= '<tr><td colspan="2" style="padding:10px 0;'.$border.'color:'.$c['text'].';">'.$line.'</td></tr>';
            }
        }

        return $tableHtml.'</table></td></tr></table>';
    }, $formattedHtml);

    // Timestamp footnotes ("Generated at", "Welcome at", "Last Updated").
    $formattedHtml = preg_replace(
        '/<p>\s*<strong>(Generated at|Welcome at|Last Updated):\s*<\/strong>\s*(.*?)<\/p>/i',
        '<p style="margin:24px 0 16px 0;font-size:13px;line-height:1.5;color:'.$c['muted'].';">$1: $2</p>',
        $formattedHtml
    );

    // Buttons stored with inline background colours adopt the brand button style.
    $formattedHtml = preg_replace(
        '/<a\b([^>]*?)\sstyle="[^"]*background(?:-color)?\s*:[^"]*"([^>]*)>/i',
        '<a$1 style="display:inline-block;padding:15px 30px;background-color:'.$c['primary'].';color:#FFFFFF;text-decoration:none;border-radius:10px;font-size:17px;line-height:1.2;font-weight:700;"$2>',
        $formattedHtml
    );

    $formattedHtml = str_replace('<p>', '<p style="margin:0 0 16px 0;font-size:16px;line-height:1.6;color:'.$c['text'].';">', $formattedHtml);
@endphp

    @if (filled($headline))
        @include('emails.partials.intro', [
            'greeting' => $greeting,
            'title' => $headline,
            'highlight' => config('mail-brand.name'),
        ])
    @elseif (filled($greeting))
        <p style="margin:0 0 18px 0;font-size:17px;line-height:1.5;color:{{ $c['heading'] }};">{{ $greeting }}</p>
    @endif

    <div style="margin:0 0 8px 0;font-size:16px;line-height:1.6;color:{{ $c['text'] }};">
        {!! $formattedHtml !!}
    </div>
@endsection
