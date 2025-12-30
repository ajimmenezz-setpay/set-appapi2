<?php

use Illuminate\Support\Facades\Route;

Route::group(['prefix' => 'v2', 'as' => 'api.v2.'], function () {
    Route::post('/login', [App\Http\Controllers\Users\UserV2::class, 'login']);
});
