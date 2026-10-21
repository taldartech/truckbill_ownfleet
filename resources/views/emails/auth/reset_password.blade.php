@extends('emails.layouts.master')

@section('title', 'Reset Password')

@section('content')
    @php
        $resetUrl = url(route('password.reset', ['token' => $token, 'email' => $email], false));
    @endphp

    @include('emails.partials.intro', [
        'greeting' => 'Hello,',
        'title' => 'Reset your password',
        'highlight' => 'password',
        'text' => 'We received a request to reset the password for your '.config('mail-brand.name').' account. Click the button below to choose a new password.',
        'buttonUrl' => $resetUrl,
        'buttonLabel' => 'Reset Password',
    ])

    @include('emails.partials.note', [
        'text' => "This link will expire in {$count} minutes.",
    ])

    @include('emails.partials.note', [
        'icon' => null,
        'text' => 'If you did not request this, please ignore this email.',
    ])

    @include('emails.partials.fallback-url', ['url' => $resetUrl])
@endsection
