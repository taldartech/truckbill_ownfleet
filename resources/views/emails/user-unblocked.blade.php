@extends('emails.layouts.master')

@section('title', 'Account Unlocked')

@section('content')
    @include('emails.partials.intro', [
        'greeting' => "Hello {$userName},",
        'title' => 'Your account has been unlocked',
        'highlight' => 'unlocked',
        'text' => 'Your '.config('mail-brand.name').' user account is now unlocked. You can sign in again at any time.',
    ])

    @include('emails.partials.details', [
        'rows' => [
            ['label' => 'Unlocked by', 'value' => $unblockedByName],
            ['label' => 'Note', 'value' => $reason],
            ['label' => 'Unlocked at', 'value' => $unblockedAt],
        ],
    ])
@endsection
