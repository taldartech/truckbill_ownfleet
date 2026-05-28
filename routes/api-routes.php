<?php

use Illuminate\Support\Facades\Route;

Route::any('/api/{any}', function () {
    return response()->json([
        'message' => 'API logic removed for fresh start.',
    ], 410);
})->where('any', '.*');
