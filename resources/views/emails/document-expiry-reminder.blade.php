@extends('emails.layouts.master')

@section('title', 'Document Expiry Alert')

@section('content')
    @php
        $isExpired = ($payload['status'] ?? 'expiring') === 'expired';
        $statusColor = $isExpired ? '#dc3545' : '#f59e0b';
        $statusLabel = $isExpired ? 'Expired' : 'Expiring Soon';
        $expiryDate = $payload['expiry_date'] ?? null;
        $formattedExpiry = $expiryDate
            ? \Illuminate\Support\Carbon::parse($expiryDate)->format('d M Y')
            : 'N/A';
    @endphp
    <tr>
        <td style="padding:32px 30px 10px 30px;">
            <h1 style="margin:0 0 12px 0;font-size:24px;line-height:1.3;color:#1f2937;">
                Document Expiry Alert
            </h1>
            <p style="margin:0;font-size:16px;line-height:1.6;color:#4b5563;">
                Hi {{ $recipient->name }},
            </p>
        </td>
    </tr>
    <tr>
        <td style="padding:10px 30px 24px 30px;">
            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background-color:#f8fafc;border:1px solid #e2e8f0;border-radius:12px;">
                <tr>
                    <td style="padding:20px;">
                        <p style="margin:0 0 8px 0;font-size:14px;color:#64748b;text-transform:uppercase;letter-spacing:0.04em;">Truck</p>
                        <p style="margin:0 0 16px 0;font-size:18px;font-weight:700;color:#0f172a;">{{ $payload['vehicle_registration'] ?? '—' }}</p>

                        <p style="margin:0 0 8px 0;font-size:14px;color:#64748b;text-transform:uppercase;letter-spacing:0.04em;">Document</p>
                        <p style="margin:0 0 16px 0;font-size:18px;font-weight:700;color:#0f172a;">{{ $payload['document_type_label'] ?? '—' }}</p>

                        <p style="margin:0 0 8px 0;font-size:14px;color:#64748b;text-transform:uppercase;letter-spacing:0.04em;">Expiry Date</p>
                        <p style="margin:0 0 16px 0;font-size:18px;font-weight:700;color:#0f172a;">{{ $formattedExpiry }}</p>

                        <span style="display:inline-block;padding:6px 12px;border-radius:999px;background-color:{{ $statusColor }};color:#ffffff;font-size:13px;font-weight:700;">
                            {{ $statusLabel }}
                        </span>
                    </td>
                </tr>
            </table>
            <p style="margin:20px 0 0 0;font-size:15px;line-height:1.6;color:#4b5563;">
                Please review this document in your fleet dashboard and update the expiry date if renewed.
            </p>
        </td>
    </tr>
@endsection
