@php
    $c = config('mail-brand.colors');
@endphp
<table role="presentation" cellspacing="0" cellpadding="0" border="0">
    <tr>
        <td align="center" bgcolor="{{ $c['primary'] }}" style="border-radius:10px;background-color:{{ $c['primary'] }};">
            <a href="{{ $url }}" target="_blank" style="display:inline-block;padding:15px 30px;font-size:17px;line-height:1.2;font-weight:700;color:#FFFFFF;text-decoration:none;border-radius:10px;">
                {{ $label }}&nbsp;&nbsp;&rarr;
            </a>
        </td>
    </tr>
</table>
