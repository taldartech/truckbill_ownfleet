<?php

use Illuminate\Support\Facades\Route;

Route::post('/logout', function () {
    return redirect('/login');
})->name('logout');
