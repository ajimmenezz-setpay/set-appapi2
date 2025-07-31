<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json(['version' => '2.0.1']);
});

Route::get('/docs/conector', function () {
    return view('docs.conector');
});
