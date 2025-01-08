<?php

use App\Http\Controllers\Security\Password;
use App\Http\Middleware\VerifyJwt;
use Illuminate\Support\Facades\Route;

Route::middleware([VerifyJwt::class])->group(function () {
    Route::group(['prefix' => 'security'], function () {
        Route::group(['prefix' => 'password'], function () {
            Route::post('change', [Password::class, 'change']);
        });

        Route::group(['prefix' => '2fa'], function () {
            Route::post('authorize', [App\Http\Controllers\Security\GoogleAuth::class, 'authorize']);
        });
    });


    Route::group(['prefix' => 'commission'], function () {
        Route::post('pay', [App\Http\Controllers\Stp\Commissions::class, 'payCommission']);
    });

    Route::group(['prefix' => 'reports'], function () {
        Route::group(['prefix' => 'card-cloud'], function () {
            Route::get('daily-consume', [App\Http\Controllers\Report\CardCloud\DailyConsume::class, 'index']);
            Route::get('card-status', [App\Http\Controllers\Report\CardCloud\CardStatus::class, 'index']);
        });
    });

    Route::group(['prefix' => 'cardCloud'], function () {
        Route::group(['prefix' => 'card'], function () {
            Route::post('/{cardId}/nip', [App\Http\Controllers\CardCloud\CardManagementController::class, 'updateNip']);
        });

        Route::group(['prefix' => 'movement'], function () {
            Route::get('/{uuid}', [App\Http\Controllers\CardCloud\MovementController::class, 'show']);
        });
    });

    Route::group(['prefix' => 'ticket'], function () {
        Route::post('/', [App\Http\Controllers\Ticket\ClickupTicket::class, 'create']);
        Route::get('/', [App\Http\Controllers\Ticket\ClickupTicket::class, 'index']);
        Route::get('/{id}', [App\Http\Controllers\Ticket\ClickupTicket::class, 'show']);
    });
});

Route::group(['prefix' => 'clickup'], function () {
    Route::group(['prefix' => 'webhook'], function () {
        Route::post('task', [App\Http\Controllers\Clickup\Webhook::class, 'updateTask']);
    });
});

Route::group(['prefix' => 'stp'], function () {
    Route::group(['prefix' => 'spei-transactions'], function () {
        Route::get('register-in', [App\Http\Controllers\Stp\Transactions\SpeiIn::class, 'register']);
    });
});
