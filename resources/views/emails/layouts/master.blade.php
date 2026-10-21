@php
    $brand = config('mail-brand');
    $c = $brand['colors'];
    $assets = $brand['asset_url'];
    $logoUrl = $brand['logo_url'] ?: $assets.'/logo.png';
    $supportEmail = $supportEmail ?? $brand['support_email'];
    $font = "'Segoe UI',Roboto,'Helvetica Neue',Helvetica,Arial,sans-serif";
    $socials = array_filter([
        'linkedin' => ['url' => $brand['social']['linkedin'] ?? null, 'label' => 'LinkedIn'],
        'instagram' => ['url' => $brand['social']['instagram'] ?? null, 'label' => 'Instagram'],
        'youtube' => ['url' => $brand['social']['youtube'] ?? null, 'label' => 'YouTube'],
        'x' => ['url' => $brand['social']['x'] ?? null, 'label' => 'X'],
    ], fn (array $social) => filled($social['url']));
@endphp
<!DOCTYPE html>
<html lang="en" xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="color-scheme" content="light">
    <meta name="supported-color-schemes" content="light">
    <title>@yield('title', $subject ?? $brand['name'])</title>
    <style>
        body { margin: 0; padding: 0; -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%; }
        img { border: 0; outline: none; text-decoration: none; -ms-interpolation-mode: bicubic; }
        table { border-collapse: collapse; }
        @media only screen and (max-width: 620px) {
            .tb-container { width: 100% !important; }
            .tb-pad { padding-left: 20px !important; padding-right: 20px !important; }
            .tb-stack { display: block !important; width: 100% !important; }
            .tb-hide-sm { display: none !important; }
            .tb-logo { width: 220px !important; }
            .tb-title { font-size: 26px !important; }
            .tb-hero-img { padding: 24px 0 0 0 !important; text-align: center !important; }
            .tb-footer-divider { border-left: 0 !important; padding: 16px 0 0 0 !important; }
        }
    </style>
</head>
<body style="margin:0;padding:0;background-color:{{ $c['background'] }};font-family:{{ $font }};color:{{ $c['text'] }};">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background-color:{{ $c['background'] }};">
        <tr>
            <td align="center" style="padding:24px 12px;">
                <table role="presentation" class="tb-container" width="640" cellspacing="0" cellpadding="0" border="0" style="width:640px;max-width:640px;background-color:{{ $c['surface'] }};border:1px solid #E6E9F0;border-radius:20px;overflow:hidden;">
                    {{-- Header --}}
                    <tr>
                        <td style="padding:0;background-color:#FFFFFF;">
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">
                                <tr>
                                    <td class="tb-pad" valign="middle" style="padding:28px 0 28px 32px;">
                                        <a href="{{ $brand['website'] }}" target="_blank" style="text-decoration:none;">
                                            <img class="tb-logo" src="{{ $logoUrl }}" width="250" alt="{{ $brand['name'] }} — {{ $brand['tagline'] }}" style="display:block;width:250px;max-width:100%;height:auto;border:0;">
                                        </a>
                                    </td>
                                    <td class="tb-hide-sm" align="right" valign="bottom" width="284" style="padding:0;width:284px;">
                                        <img src="{{ $assets }}/header.png" width="284" alt="" style="display:block;width:284px;height:auto;border:0;">
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td style="height:4px;line-height:4px;font-size:0;background-color:{{ $c['gradient_middle'] }};background-image:linear-gradient(90deg,{{ $c['gradient_start'] }} 0%,{{ $c['gradient_middle'] }} 50%,{{ $c['gradient_end'] }} 100%);">&nbsp;</td>
                    </tr>

                    {{-- Body --}}
                    <tr>
                        <td class="tb-pad" style="padding:36px 32px 8px 32px;font-family:{{ $font }};font-size:16px;line-height:1.6;color:{{ $c['text'] }};">
                            @yield('content')
                        </td>
                    </tr>

                    {{-- Help --}}
                    <tr>
                        <td class="tb-pad" style="padding:0 32px 32px 32px;">
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">
                                <tr><td style="border-top:1px solid {{ $c['border'] }};height:24px;line-height:24px;font-size:0;">&nbsp;</td></tr>
                            </table>
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background-color:{{ $c['help'] }};border-radius:12px;">
                                <tr>
                                    <td width="76" align="center" valign="middle" style="width:76px;padding:18px 0 18px 8px;">
                                        <img src="{{ $assets }}/headset.png" width="34" height="34" alt="" style="display:block;width:34px;height:34px;border:0;">
                                    </td>
                                    <td valign="middle" style="padding:18px 20px 18px 24px;border-left:1px solid #D9DDE6;font-family:{{ $font }};">
                                        <p style="margin:0 0 4px 0;font-size:16px;line-height:1.4;font-weight:700;color:{{ $c['heading'] }};">Need help?</p>
                                        <p style="margin:0;font-size:15px;line-height:1.5;color:{{ $c['text'] }};">
                                            Our team is here to help. Contact us at
                                            <a href="mailto:{{ $supportEmail }}" style="color:{{ $c['primary'] }};font-weight:700;text-decoration:none;">{{ $supportEmail }}</a>
                                        </p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    {{-- Footer --}}
                    <tr>
                        <td class="tb-pad" style="padding:28px 32px 22px 32px;background-color:{{ $c['footer'] }};font-family:{{ $font }};">
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">
                                <tr>
                                    <td class="tb-stack" valign="middle" width="196" style="width:196px;padding:0 24px 0 0;">
                                        <a href="{{ $brand['website'] }}" target="_blank" style="text-decoration:none;">
                                            <img src="{{ $logoUrl }}" width="172" alt="{{ $brand['name'] }}" style="display:block;width:172px;max-width:100%;height:auto;border:0;">
                                        </a>
                                    </td>
                                    <td class="tb-stack tb-footer-divider" valign="middle" style="padding:0 0 0 28px;border-left:1px solid #D9DDE6;">
                                        <p style="margin:0 0 14px 0;font-size:13px;line-height:1.5;color:{{ $c['heading'] }};">
                                            <a href="{{ $brand['website'] }}" target="_blank" style="color:{{ $c['heading'] }};text-decoration:none;white-space:nowrap;">Website</a>
                                            <span style="color:#9CA3AF;">&nbsp;&nbsp;|&nbsp;&nbsp;</span>
                                            <a href="{{ $brand['links']['terms'] }}" target="_blank" style="color:{{ $c['heading'] }};text-decoration:none;white-space:nowrap;">Terms &amp; Conditions</a>
                                            <span style="color:#9CA3AF;">&nbsp;&nbsp;|&nbsp;&nbsp;</span>
                                            <a href="{{ $brand['links']['privacy'] }}" target="_blank" style="color:{{ $c['heading'] }};text-decoration:none;white-space:nowrap;">Privacy Policy</a>
                                        </p>
                                        @if ($socials !== [])
                                            <table role="presentation" cellspacing="0" cellpadding="0" border="0">
                                                <tr>
                                                    <td valign="middle" style="padding:0 12px 0 0;font-size:13px;line-height:1.5;color:{{ $c['heading'] }};">Follow us on</td>
                                                    @foreach ($socials as $network => $social)
                                                        <td valign="middle" style="padding:0 8px 0 0;">
                                                            <a href="{{ $social['url'] }}" target="_blank" style="text-decoration:none;">
                                                                <img src="{{ $assets }}/social-{{ $network }}.png" width="28" height="28" alt="{{ $social['label'] }}" style="display:block;width:28px;height:28px;border:0;">
                                                            </a>
                                                        </td>
                                                    @endforeach
                                                </tr>
                                            </table>
                                        @endif
                                    </td>
                                </tr>
                            </table>
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">
                                <tr><td style="border-bottom:1px solid #DDE2EA;height:22px;line-height:22px;font-size:0;">&nbsp;</td></tr>
                                <tr>
                                    <td align="center" style="padding:16px 0 0 0;font-size:13px;line-height:1.5;color:{{ $c['muted'] }};">
                                        &copy; {{ date('Y') }} {{ $brand['name'] }}. All rights reserved.
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
