@extends('emails.layouts.master')

@section('title', 'Customer portal access')

@section('content')
    @include('emails.partials.intro', [
        'greeting' => "Hi {$userName},",
        'title' => 'Your customer portal access is ready',
        'highlight' => 'ready',
        'text' => "Your customer portal access is ready for {$consigneeName}.",
        'buttonUrl' => $loginUrl,
        'buttonLabel' => 'Sign In',
    ])

    @include('emails.partials.details', [
        'rows' => array_filter([
            ['label' => 'Login URL', 'value' => $loginUrl, 'href' => $loginUrl],
            ['label' => 'Email', 'value' => $email],
            $isNewUser && $plainPassword ? ['label' => 'Temporary Password', 'value' => $plainPassword] : null,
        ]),
    ])

    @include('emails.partials.note', [
        'icon' => null,
        'text' => $isNewUser
            ? 'Please sign in and change your password immediately.'
            : 'Your existing customer login now has access to this account as well.',
    ])
@endsection
