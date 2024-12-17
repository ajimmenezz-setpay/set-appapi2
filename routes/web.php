<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json(['version' => '2.0.1']);
});
