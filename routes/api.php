<?php

use App\Http\Controllers\Security\Password;
use App\Http\Middleware\VerifyJwt;
use App\Http\Middleware\ApiLogger;
use Illuminate\Support\Facades\Route;

Route::post('login', [App\Http\Controllers\Auth\Login::class, 'login']);

Route::middleware([VerifyJwt::class, ApiLogger::class])->group(function () {

    Route::group(['prefix' => 'modules'], function () {
        Route::get('/user', [App\Http\Controllers\Users\Modules::class, 'index']);
    });


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

    Route::group(['prefix' => 'card'], function () {
        Route::get('/{pan_suffix}', [App\Http\Controllers\Card\CardManagementController::class, 'show']);
        Route::get('/{pan_suffix}/balance', [App\Http\Controllers\Card\CardManagementController::class, 'getBalance']);
        Route::get('/{pan_suffix}/movements', [App\Http\Controllers\Card\CardManagementController::class, 'movements']);
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
            Route::get('/{cardId}/pin', [App\Http\Controllers\CardCloud\CardSensitiveController::class, 'pin']);
            Route::post('/{cardId}/nip_view', [App\Http\Controllers\CardCloud\CardSensitiveController::class, 'nipView']);

            Route::get('/search/{search}', [App\Http\Controllers\CardCloud\CardManagementController::class, 'search']);
            Route::post('/{cardId}/setup/{setupName}/{action}', [App\Http\Controllers\CardCloud\CardManagementController::class, 'setup']);
            Route::get('/{cardId}/webhooks', [App\Http\Controllers\CardCloud\CardManagementController::class, 'webhooks']);
            Route::get('/{cardId}/failed-authorizations', [App\Http\Controllers\CardCloud\CardManagementController::class, 'failedAuthorizations']);
            Route::get('/{cardId}/movements', [App\Http\Controllers\CardCloud\CardManagementController::class, 'movements']);
            Route::delete('/{cardId}/unassign-user', [App\Http\Controllers\CardCloud\CardManagementController::class, 'unassignUser']);

            Route::post('/{cardId}/generate-code', [App\Http\Controllers\CardCloud\CardBarcodeController::class, 'generateCode']);
            Route::get('/{cardId}/barcodes', [App\Http\Controllers\CardCloud\CardBarcodeController::class, 'getBarcodes']);
            Route::delete('/{cardId}/barcode', [App\Http\Controllers\CardCloud\CardBarcodeController::class, 'deleteBarcode']);

            Route::post('/{cardId}/deposit', [App\Http\Controllers\CardCloud\TransferController::class, 'deposit']);
            Route::post('/{cardId}/reverse', [App\Http\Controllers\CardCloud\TransferController::class, 'reverse']);

            Route::post('/{cardId}/block', [App\Http\Controllers\CardCloud\CardManagementController::class, 'blockCard']);
            Route::post('/{cardId}/unblock', [App\Http\Controllers\CardCloud\CardManagementController::class, 'unblockCard']);
        });

        Route::group(['prefix' => 'detailed-movements'], function () {
            Route::get('/{movementId}', [App\Http\Controllers\CardCloud\MovementController::class, 'detailedMovements']);
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

        Route::group(['prefix' => 'sub-account'], function () {
            Route::get('/{uuid}/cards', [App\Http\Controllers\CardCloud\SubaccountCardController::class, 'index']);
            Route::get('/{uuid}/credits', [App\Http\Controllers\CardCloud\Credits\SubaccountCreditController::class, 'index']);
            Route::post('/{uuid}/credits', [App\Http\Controllers\CardCloud\Credits\SubaccountCreditController::class, 'store']);
        });

        Route::group(['prefix' => 'cards'], function () {
            Route::post('/assign-user-from-file', [App\Http\Controllers\CardCloud\SubaccountCardController::class, 'assignUserFromFile']);
        });

        Route::group(['prefix' => 'credits'], function () {
            Route::get('/', [App\Http\Controllers\CardCloud\Credits\SubaccountCreditController::class, 'userCredits']);
            Route::get('/users', [App\Http\Controllers\CardCloud\Credits\SubaccountCreditController::class, 'getUsers']);
            Route::get('/{uuid}', [App\Http\Controllers\CardCloud\Credits\SubaccountCreditController::class, 'show']);
            Route::post('/{uuid}/activate', [App\Http\Controllers\CardCloud\Credits\SubaccountCreditController::class, 'activateCard']);
            Route::get('/{uuid}/virtual_card_price', [App\Http\Controllers\CardCloud\Credits\SubaccountCreditController::class, 'virtualCardPrice']);
            Route::post('/{uuid}/buy_virtual_card', [App\Http\Controllers\CardCloud\Credits\SubaccountCreditController::class, 'buyVirtualCard']);
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

        Route::group(['prefix' => 'additional-info'], function () {
            Route::get('/', [App\Http\Controllers\Users\AdditionalInfo::class, 'getAdditionalInfo']);
            Route::post('/', [App\Http\Controllers\Users\AdditionalInfo::class, 'setAdditionalInfo']);
        });

        Route::group(['prefix' => 'firebase-token'], function () {
            Route::post('/asociate', [App\Http\Controllers\Notifications\FirebasePushController::class, 'asociateDeviceToken']);
        });
    });

    Route::group(['prefix' => 'administration'], function () {
        Route::group(['prefix' => 'company'], function () {
            Route::get('/', [App\Http\Controllers\Administration\Company::class, 'index']);
            Route::get('/{id}', [App\Http\Controllers\Administration\Company::class, 'show']);
        });
    });

    Route::group(['prefix' => 'backoffice'], function () {
        Route::group(['prefix' => 'company'], function () {
            Route::post('/new', [App\Http\Controllers\Backoffice\Company::class, 'create']);
            Route::put('/update', [App\Http\Controllers\Backoffice\Company::class, 'update']);
            Route::put('/toggle', [App\Http\Controllers\Backoffice\Company::class, 'toggle']);
            Route::get('/{id}', [App\Http\Controllers\Backoffice\Company::class, 'show']);
        });

        Route::group(['prefix' => 'users'], function () {
            Route::get('/administrators-of-companies', [App\Http\Controllers\Backoffice\Users\AdministratorsOfCompanies::class, 'index']);
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

Route::post('voice-ramos-arizpe/control', [App\Http\Controllers\Netelip\VoiceRamosArizpeController::class, 'control']);

Route::post('balance/kommo', [App\Http\Controllers\Kommo\BalanceController::class, 'getBalance']);

Route::get('card-cloud/balance/{phone}', [App\Http\Controllers\CardCloud\CardManagementController::class, 'getBalanceByPhone']);

Route::get('card-cloud/info/{clientId}', [App\Http\Controllers\CardCloud\CardManagementController::class, 'getInfoByClientId']);

Route::get('card-cloud/balanceByCard/{card}', [App\Http\Controllers\CardCloud\CardManagementController::class, 'getBalanceByCardId']);

Route::get('card-cloud/subaccountByTerm/{search_term}', [App\Http\Controllers\CardCloud\CardManagementController::class, 'getSubaccountBySearchTerm']);

Route::group(['prefix' => 'dev'], function () {
    Route::group(['prefix' => 'permissions'], function () {
        Route::get('/categories', [App\Http\Controllers\Users\Permissions::class, 'categories']);
        Route::get('/permissions', [App\Http\Controllers\Users\Permissions::class, 'index']);
        Route::post('/permissions', [App\Http\Controllers\Users\Permissions::class, 'store']);
    });

    Route::group(['prefix' => 'users'], function () {
        Route::post('/fixMissingCompany', [App\Http\Controllers\Users\FixMissingCompany::class, 'fix']);
    });

    Route::group(['prefix' => 'backoffice'], function () {
        Route::prefix('business')->group(function () {
            Route::group(['prefix' => 'smtp'], function () {
                Route::get('/', [App\Http\Controllers\Backoffice\SMTPController::class, 'index']);
                Route::post('/', [App\Http\Controllers\Backoffice\SMTPController::class, 'store']);
                Route::put('/{id}', [App\Http\Controllers\Backoffice\SMTPController::class, 'update']);
            });
        });
    });

    Route::group(['prefix' => 'fixes'], function () {
        Route::post('/fixMissingCompany', [App\Http\Controllers\Users\FixMissingCompany::class, 'fixMissingCompany']);
    });

    Route::get('/push-notifications/pending', [App\Http\Controllers\Notifications\PushController::class, 'sendPendingPushNotifications']);
});

Route::post('npush', [App\Http\Controllers\Notifications\PushController::class, 'sendPushNotification']);
