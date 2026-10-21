@php
    $c = config('mail-brand.colors');
    $rows = array_values($rows);
    $last = count($rows) - 1;
@endphp
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="margin:0 0 24px 0;">
    <tr>
        <td style="padding:20px 24px;background-color:{{ $tone ?? $c['panel'] }};border-radius:14px;">
            @if (filled($title ?? null))
                <p style="margin:0 0 10px 0;font-size:19px;line-height:1.4;font-weight:700;color:{{ $c['heading'] }};">{{ $title }}</p>
            @endif
            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="font-size:15px;line-height:1.5;">
                @foreach ($rows as $index => $row)
                    @php
                        $border = $index < $last ? 'border-bottom:1px solid #DDEBE3;' : '';
                        $value = filled($row['value'] ?? null) ? $row['value'] : '—';
                    @endphp
                    <tr>
                        <td width="38%" valign="top" style="width:38%;padding:10px 12px 10px 0;{{ $border }}color:{{ $c['muted'] }};">{{ $row['label'] }}</td>
                        <td valign="top" style="padding:10px 0;{{ $border }}color:{{ $row['color'] ?? $c['heading'] }};font-weight:600;{{ ! empty($row['break']) ? 'word-break:break-all;' : '' }}{{ ! empty($row['multiline']) ? 'white-space:pre-line;' : '' }}">
                            @if (! empty($row['badge']))
                                <span style="display:inline-block;padding:4px 12px;border-radius:999px;background-color:{{ $row['badge'] }};color:#FFFFFF;font-size:13px;font-weight:700;">{{ $value }}</span>
                            @elseif (! empty($row['href']))
                                <a href="{{ $row['href'] }}" target="_blank" style="color:{{ $c['primary'] }};text-decoration:none;word-break:break-all;">{{ $value }}</a>
                            @else
                                {{ $value }}
                            @endif
                        </td>
                    </tr>
                @endforeach
            </table>
        </td>
    </tr>
</table>
