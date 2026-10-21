@extends('emails.layouts.master')

@section('title', 'Daily Database Backup')

@section('content')
    @include('emails.partials.intro', [
        'title' => "{$appName} Backup",
        'highlight' => 'Backup',
        'text' => "Please find today's database backup attached with this email.",
    ])

    @include('emails.partials.details', [
        'rows' => [
            ['label' => 'File', 'value' => $attachmentName, 'break' => true],
            ['label' => 'Generated At', 'value' => $generatedAt],
        ],
    ])

    @include('emails.partials.note', [
        'icon' => null,
        'text' => 'This is an automated system email. Please keep this backup secure and do not forward it outside authorized recipients.',
    ])
@endsection
