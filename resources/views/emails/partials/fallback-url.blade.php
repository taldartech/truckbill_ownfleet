@php
    $c = config('mail-brand.colors');
@endphp
<p style="margin:0 0 20px 0;font-size:13px;line-height:1.6;color:{{ $c['muted'] }};">
    Having trouble with the button? Copy and paste this URL into your browser:<br>
    <a href="{{ $url }}" target="_blank" style="color:{{ $c['primary'] }};text-decoration:none;word-break:break-all;">{{ $url }}</a>
</p>
