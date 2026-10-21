@extends('emails.layouts.master')

@section('title', 'Database Backup Failed')

@section('content')
    @include('emails.partials.intro', [
        'title' => "{$appName} Backup Failed",
        'highlight' => 'Failed',
        'highlightColor' => config('mail-brand.colors.danger'),
        'text' => 'The scheduled database backup could not be completed successfully. Please check the error details below and review the application logs if necessary.',
    ])

    @include('emails.partials.details', [
        'tone' => '#FEF2F2',
        'rows' => [
            ['label' => 'File', 'value' => $attachmentName, 'break' => true],
            ['label' => 'Failed At', 'value' => $generatedAt],
            ['label' => 'Error', 'value' => $errorMessage, 'color' => '#B42318', 'multiline' => true],
        ],
    ])

    @include('emails.partials.note', [
        'icon' => null,
        'text' => 'This is an automated system email. The database backup was not successfully stored. Please investigate the issue before relying on the backup.',
    ])
@endsection
