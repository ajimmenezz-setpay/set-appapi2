<?php

use App\Http\Controllers\Security\Password;
use App\Http\Middleware\ValidateEnvironmentAdminProfile;
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
            Route::get('fundings', [App\Http\Controllers\Report\CardCloud\Fundings::class, 'index']);
        });
    });

    Route::group(['prefix' => 'cardCloud'], function () {
        Route::group(['prefix' => 'card'], function () {
            Route::post('/{cardId}/nip', [App\Http\Controllers\CardCloud\CardManagementController::class, 'updateNip']);
            Route::get('/client-id/{clientId}', [App\Http\Controllers\CardCloud\CardManagementController::class, 'searchByClientId']);
            Route::post('/transfer', [App\Http\Controllers\CardCloud\TransferController::class, 'cardTransfer']);
            Route::post('/activate', [App\Http\Controllers\CardCloud\CardManagementController::class, 'activateCard']);
            Route::post('/buy_virtual_card', [App\Http\Controllers\CardCloud\CardManagementController::class, 'buyVirtualCard']);
            Route::get('/virtual_card_price', [App\Http\Controllers\CardCloud\CardManagementController::class, 'getVirtualCardPrice']);
            Route::get('/{cardId}/sensitive', [App\Http\Controllers\CardCloud\CardSensitiveController::class, 'sensitive']);
            Route::get('/{cardId}/cvv', [App\Http\Controllers\CardCloud\CardSensitiveController::class, 'dynamicCvv']);
        });

        Route::group(['prefix' => 'movement'], function () {
            Route::get('/{uuid}', [App\Http\Controllers\CardCloud\MovementController::class, 'show']);
        });

        Route::group(['prefix' => 'contact'], function () {
            Route::get('/', [App\Http\Controllers\CardCloud\ContactController::class, 'index']);
            Route::post('/', [App\Http\Controllers\CardCloud\ContactController::class, 'store']);
            Route::get('/{uuid}', [App\Http\Controllers\CardCloud\ContactController::class, 'show']);
            Route::delete('/{uuid}', [App\Http\Controllers\CardCloud\ContactController::class, 'delete']);
            Route::patch('/{uuid}', [App\Http\Controllers\CardCloud\ContactController::class, 'update']);
        });

        Route::group(['prefix' => 'institution'], function () {
            Route::get('/', [App\Http\Controllers\CardCloud\InstitutionController::class, 'index']);
        });
    });

    Route::group(['prefix' => 'speiCloud'], function () {
        Route::group(['prefix' => 'transaction'], function () {
            Route::post('/process-payments', [App\Http\Controllers\Stp\Transactions\SpeiOut::class, 'processPayments']);
        });

        Route::group(['prefix' => 'authorization'], function () {
            Route::group(['prefix' => 'rules'], function () {
                Route::get('/', [App\Http\Controllers\SpeiCloud\Authorization\AuthorizationRulesController::class, 'rules']);
                Route::post('/', [App\Http\Controllers\SpeiCloud\Authorization\AuthorizationRulesController::class, 'store']);
                Route::post('/enable', [App\Http\Controllers\SpeiCloud\Authorization\AuthorizationRulesController::class, 'enableRules']);
                Route::post('/disable', [App\Http\Controllers\SpeiCloud\Authorization\AuthorizationRulesController::class, 'disableRules']);
                Route::post('/{ruleId}', [App\Http\Controllers\SpeiCloud\Authorization\AuthorizationRulesController::class, 'update']);
                Route::post('/{ruleId}/enable', [App\Http\Controllers\SpeiCloud\Authorization\AuthorizationRulesController::class, 'enable']);
                Route::post('/{ruleId}/disable', [App\Http\Controllers\SpeiCloud\Authorization\AuthorizationRulesController::class, 'disable']);
            });

            Route::group(['prefix' => 'authorizing-users'], function () {
                Route::get('/', [App\Http\Controllers\SpeiCloud\Authorization\AuthorizingUsers::class, 'index']);
                Route::post('/', [App\Http\Controllers\SpeiCloud\Authorization\AuthorizingUsers::class, 'store']);
                Route::delete('/', [App\Http\Controllers\SpeiCloud\Authorization\AuthorizingUsers::class, 'delete']);
            });

            Route::group(['prefix' => 'users'], function () {
                Route::get('/processors', [App\Http\Controllers\SpeiCloud\Authorization\AuthorizingUsers::class, 'processorUsers']);
                Route::get('/authorizers', [App\Http\Controllers\SpeiCloud\Authorization\AuthorizingUsers::class, 'authorizerUsers']);
            });

            Route::group(['prefix' => 'accounts'], function () {
                Route::get('/origin', [App\Http\Controllers\SpeiCloud\Authorization\AuthorizationAccountsController::class, 'origins']);
                Route::get('/destination', [App\Http\Controllers\SpeiCloud\Authorization\AuthorizationAccountsController::class, 'destinations']);
            });


        });
    });

    Route::group(['prefix' => 'ticket'], function () {
        Route::post('/', [App\Http\Controllers\Ticket\ClickupTicket::class, 'create']);
        Route::get('/', [App\Http\Controllers\Ticket\ClickupTicket::class, 'index']);
        Route::get('/{id}', [App\Http\Controllers\Ticket\ClickupTicket::class, 'show']);
    });

    Route::group(['prefix' => 'users'], function () {

        Route::group(['prefix' => 'secret-phrase'], function () {
            Route::post('/', [App\Http\Controllers\Users\SecretPhrase::class, 'create']);
            Route::patch('/', [App\Http\Controllers\Users\SecretPhrase::class, 'update']);
            Route::delete('/', [App\Http\Controllers\Users\SecretPhrase::class, 'delete']);
            Route::get('/', [App\Http\Controllers\Users\SecretPhrase::class, 'index']);
        });

        Route::group(['prefix' => 'address'], function () {
            Route::post('/', [App\Http\Controllers\Users\Address::class, 'setAddress']);
            Route::get('/', [App\Http\Controllers\Users\Address::class, 'getAddress']);
        });
    });

    Route::group(['prefix' => 'administration'], function () {
        Route::group(['prefix' => 'company'], function () {
            Route::get('/', [App\Http\Controllers\Administration\Company::class, 'index']);
            Route::get('/{id}', [App\Http\Controllers\Administration\Company::class, 'show']);
        });
    });
});

Route::group(['prefix' => 'address'], function () {
    Route::get('/countries', [App\Http\Controllers\Address\Countries::class, 'index']);
    Route::get('/states', [App\Http\Controllers\Address\States::class, 'index']);
});

Route::group(['prefix' => 'clickup'], function () {
    Route::group(['prefix' => 'webhook'], function () {
        Route::post('task', [App\Http\Controllers\Clickup\Webhook::class, 'updateTask']);
    });
});

Route::group(['prefix' => 'stp'], function () {
    Route::group(['prefix' => 'spei-transactions'], function () {
        Route::get('register-in', [App\Http\Controllers\Stp\Transactions\SpeiIn::class, 'register']);
        Route::get('fix-balances', [App\Http\Controllers\Stp\Transactions\Transactions::class, 'fixStpBalances']);
    });

    Route::get('generate_clabe', [App\Http\Controllers\Stp\GenerateClabe::class, 'generate']);

    Route::get('fix_clabes_assignation', [App\Http\Controllers\Stp\AssignClabes::class, 'fixClabesAssignation']);
});

Route::group(['prefix' => 'users'], function () {
    Route::post('validate', [App\Http\Controllers\Users\Activate::class, 'validateEmail']);
    Route::post('validate-code', [App\Http\Controllers\Users\Activate::class, 'validateCode']);
    Route::post('login', [App\Http\Controllers\Users\Activate::class, 'validateCredentials']);
    Route::post('activate', [App\Http\Controllers\Users\Activate::class, 'activate']);
    Route::delete('clean', [App\Http\Controllers\Users\Activate::class, 'cleanActivation']);
    Route::post('forgot-password', [App\Http\Controllers\Users\ForgotPassword::class, 'forgotPassword']);
    Route::post('reset-password', [App\Http\Controllers\Users\ForgotPassword::class, 'resetPassword']);
});

Route::group(['prefix' => 'app-scrapper'], function () {
    Route::get('google-play/{id}', [App\Http\Controllers\Scrapper\AppScrapperController::class, 'google']);
    Route::get('play-store/{id}', [App\Http\Controllers\Scrapper\AppScrapperController::class, 'apple']);
});
