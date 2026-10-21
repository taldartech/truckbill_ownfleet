@extends('emails.layouts.master')

@section('title', 'Account deactivated')

@section('content')
    @php
        $c = config('mail-brand.colors');
    @endphp

    @include('emails.partials.intro', [
        'greeting' => "Hi {$userName},",
        'title' => 'We noticed that your '.config('mail-brand.name').' account is currently deactivated',
        'highlight' => 'deactivated',
        'text' => 'Many transporters face hidden profit loss due to billing errors, TDS gaps, and manual tracking—and '.config('mail-brand.name').' is designed to help you avoid exactly that, while running your business with clarity and peace.',
    ])

    {{-- @include('emails.partials.details', [
        'rows' => [
            ['label' => 'Locked by', 'value' => $blockedByName],
            ['label' => 'Reason', 'value' => $reason],
            ['label' => 'Locked at', 'value' => $blockedAt],
        ],
    ]) --}}

    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="margin:0 0 24px 0;">
        <tr>
            <td style="padding:22px 24px;background-color:{{ $c['panel'] }};border-radius:14px;">
                <p style="margin:0 0 8px 0;font-size:19px;line-height:1.4;font-weight:700;color:{{ $c['heading'] }};">We’d love to help you get back on track</p>
                <p style="margin:0;font-size:16px;line-height:1.6;color:{{ $c['text'] }};">
                    Simply reply to this email or reach out to our team on
                    <a href="tel:+919001010007" style="color:{{ $c['primary'] }};font-weight:700;text-decoration:none;white-space:nowrap;">+91 9001010007</a>,
                    and we’ll assist you with quick reactivation and support.
                </p>
            </td>
        </tr>
    </table>

    @include('emails.partials.note', [
        'icon' => null,
        'text' => 'Let’s ensure your business runs smoothly—without stress and without losses.',
    ])
@endsection
