<?php

use Illuminate\Support\Facades\Route;

Route::redirect('/', '/login');
Route::view('/login', 'auth.login')->name('login');
Route::view('/forgot-password', 'auth.forgot-password')->name('password.request');

Route::get('/robots.txt', function () {
    return response("User-agent: *\nDisallow:\n", 200)
        ->header('Content-Type', 'text/plain; charset=UTF-8');
});

Route::get('/ui/{view?}', function (?string $view = null) {
    $candidate = trim((string) $view, '/');
    $normalized = str_replace('/', '.', $candidate);
    $viewsToTry = array_filter([
        $normalized,
        $normalized !== '' ? $normalized.'.index' : null,
        $normalized !== '' ? $normalized.'.create' : null,
    ]);

    foreach ($viewsToTry as $viewName) {
        if (view()->exists($viewName)) {
            return view($viewName);
        }
    }

    abort(404);
})->where('view', '.*')->name('ui.preview');

Route::get('/{path?}', function (?string $path = null) {
    $candidate = trim((string) $path, '/');

    if ($candidate === '') {
        return redirect('/login');
    }

    $viewName = str_replace('/', '.', $candidate);
    $viewsToTry = [$viewName, $viewName.'.index', $viewName.'.create'];

    foreach ($viewsToTry as $name) {
        if (view()->exists($name)) {
            return view($name);
        }
    }

    abort(404);
})->where('path', '.*');
