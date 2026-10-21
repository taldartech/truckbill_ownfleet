@php
    $c = config('mail-brand.colors');
    $assets = config('mail-brand.asset_url');
@endphp
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="margin:0 0 24px 0;">
    <tr>
        <td style="padding:24px 24px 14px 24px;background-color:{{ $c['panel'] }};border-radius:14px;">
            @if (filled($title ?? null))
                <p style="margin:0 0 16px 0;font-size:19px;line-height:1.4;font-weight:700;color:{{ $c['heading'] }};">{{ $title }}</p>
            @endif
            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">
                @foreach ($items as $item)
                    <tr>
                        <td width="40" valign="middle" style="width:40px;padding:0 0 12px 0;">
                            <img src="{{ $assets }}/icon-{{ $item['icon'] ?? 'check' }}.png" width="40" height="40" alt="" style="display:block;width:40px;height:40px;border:0;">
                        </td>
                        <td valign="middle" style="padding:0 0 12px 18px;font-size:16px;line-height:1.5;color:{{ $c['heading'] }};">{{ $item['text'] }}</td>
                    </tr>
                @endforeach
            </table>
        </td>
    </tr>
</table>
