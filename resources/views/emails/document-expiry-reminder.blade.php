@extends('emails.layouts.master')

@section('title', 'Document Expiry Alert')

@section('content')
    @php
        $c = config('mail-brand.colors');
        $isExpired = ($payload['status'] ?? 'expiring') === 'expired';
        $statusColor = $isExpired ? $c['danger'] : $c['warning'];
        $statusLabel = $isExpired ? 'Expired' : 'Expiring Soon';
        $expiryDate = $payload['expiry_date'] ?? null;
        $formattedExpiry = $expiryDate
            ? \Illuminate\Support\Carbon::parse($expiryDate)->format('d M Y')
            : 'N/A';
    @endphp

    @include('emails.partials.intro', [
        'greeting' => "Hi {$recipient->name},",
        'title' => 'Document Expiry Alert',
        'highlight' => 'Alert',
        'highlightColor' => $statusColor,
        'text' => 'A vehicle document in your fleet '.($isExpired ? 'has expired' : 'is expiring soon').'. Please review the details below.',
    ])

    @include('emails.partials.details', [
        'rows' => [
            ['label' => 'Truck', 'value' => $payload['vehicle_registration'] ?? null],
            ['label' => 'Document', 'value' => $payload['document_type_label'] ?? null],
            ['label' => 'Expiry Date', 'value' => $formattedExpiry],
            ['label' => 'Status', 'value' => $statusLabel, 'badge' => $statusColor],
        ],
    ])

    @include('emails.partials.note', [
        'icon' => 'clock',
        'text' => 'Please review this document in your fleet dashboard and update the expiry date if renewed.',
    ])
@endsection
