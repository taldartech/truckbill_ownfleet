@php
    $c = config('mail-brand.colors');
    $icon = array_key_exists('icon', get_defined_vars()) ? $icon : 'clock';
@endphp
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="margin:0 0 20px 0;">
    <tr>
        @if ($icon)
            <td width="22" valign="middle" style="width:22px;padding:0 12px 0 0;">
                <img src="{{ config('mail-brand.asset_url') }}/{{ $icon }}.png" width="20" height="20" alt="" style="display:block;width:20px;height:20px;border:0;">
            </td>
        @endif
        <td valign="middle" style="font-size:15px;line-height:1.5;color:{{ $c['muted'] }};">{{ $text }}</td>
    </tr>
</table>
