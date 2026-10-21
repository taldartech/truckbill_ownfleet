@php
    $c = config('mail-brand.colors');
@endphp
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="margin:0 0 24px 0;">
    <tr>
        <td align="center" style="padding:26px 24px;background-color:{{ $c['panel'] }};border-radius:14px;">
            <p style="margin:0 0 10px 0;font-size:13px;line-height:1.4;font-weight:700;letter-spacing:0.08em;text-transform:uppercase;color:{{ $c['muted'] }};">{{ $label }}</p>
            <p style="margin:0;font-size:38px;line-height:1.2;font-weight:800;letter-spacing:0.22em;color:{{ $c['primary_dark'] }};">{{ $code }}</p>
        </td>
    </tr>
</table>
