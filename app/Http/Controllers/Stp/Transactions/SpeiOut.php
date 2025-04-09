<?php

namespace App\Http\Controllers\Stp\Transactions;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Security\Crypt;
use Illuminate\Http\Request;
use App\Http\Controllers\Security\GoogleAuth;
use App\Http\Controllers\Stp\ErrorRegisterOrder;
use App\Http\Controllers\Stp\Transactions\Transactions;
use App\Models\Backoffice\Companies\Company;
use App\Models\Backoffice\Companies\CompanyProjection;
use App\Models\Speicloud\StpAccounts;
use App\Models\Speicloud\StpTransaction;
use App\Services\StpService;
use Carbon\Carbon;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\DB;
use Ramsey\Uuid\Uuid;
use Illuminate\Support\Facades\Log;
use GuzzleHttp\Exception\RequestException;

class SpeiOut extends Controller
{
    protected static $handlers = [
        'business-account' => [
            'business-account' => 'processBusinessToBusiness',
            'company-account' => 'processBusinessToCompany',
            'card-cloud-account' => 'processBusinessToCardCloud',
            'company-card-cloud-account' => 'processBusinessToCompanyCardCloud',
            'external-account' => 'processBusinessToExternal',
        ],
        'company-account' => [
            'business-account' => 'processCompanyToBusiness',
            'company-account' => 'processCompanyToCompany',
            'card-cloud-account' => 'processCompanyToCardCloud',
            'company-card-cloud-account' => 'processCompanyToCompanyCardCloud',
            'external-account' => 'processCompanyToExternal',
        ],
        'card-cloud-account' => [
            'business-account' => 'processCardCloudToBusiness',
            'company-account' => 'processCardCloudToCompany',
            'card-cloud-account' => 'processCardCloudToCardCloud',
            'company-card-cloud-account' => 'processCardCloudToCompanyCardCloud',
            'external-account' => 'processCardCloudToExternal',
        ],
    ];

    public function processPayments(Request $request)
    {
        try {
            $actions = Crypt::opensslDecrypt($request->all());
            $actions = json_decode($actions);
            // GoogleAuth::authorized($request->attributes->get('jwt')->id, $actions->googleAuthenticatorCode);

            $origin = $this->getOrigin($actions->originBankAccount);

            $destinos = [];

            $errors = [];

            foreach ($actions->destinationsAccounts as $d) {
                try {
                    $destination = $this->getDestination($d->bankAccount);
                    if (is_null($destination)) {
                        throw new \Exception('Cuenta ' . $d->bankAccount . ' destino no encontrada');
                    }

                    if ($origin['id'] == $destination['id'] && $origin['type'] == $destination['type']) {
                        throw new \Exception('No es posible realizar transferencias a la misma cuenta');
                    }

                    if ($origin['balance'] < $d->amount) {
                        throw new \Exception('No hay suficiente saldo en la cuenta ' . $origin['account'] . ' para realizar la transferencia a la cuenta ' . $d->bankAccount);
                    }

                    $method = self::$handlers[$origin['type']][$destination['type']];
                    $handler = self::$method($origin, $destination, $d->amount, $actions->concept, $request);


                    if (isset($handler['error'])) {
                        $errors[] = $handler['error'];
                    } else {
                        $destinos[] = [
                            'destinationsAccount' => $handler['destinationsAccount'],
                            'url'  => $handler['url']
                        ];

                        $origin['balance'] -= $d->amount;
                    }
                } catch (\Exception $e) {
                    Log::channel('spei_out')->error(
                        "Error al tranferir los fondos a la cuenta " . $d->bankAccount . ". Error:",
                        [
                            'message' => $e->getMessage(),
                            'file' => $e->getFile(),
                            'line' => $e->getLine()
                        ]
                    );
                    $errors[] = "Error al tranferir los fondos a la cuenta " . $d->bankAccount . ", intentelo más tarde o contacte a soporte";
                }
            }

            if (count($errors) > 0) {
                return response()->json([
                    'error' => $errors,
                    'destinos' => $destinos
                ]);
            }

            return response()->json($destinos);
        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            return self::basicError($e->getMessage());
        }
    }

    private function getOrigin($account)
    {
        $stpAccount = Transactions::searchByBusinessAccount($account, true);
        if (!is_null($stpAccount)) return $stpAccount;

        $company = Transactions::searchByCompanyAccount($account);
        if (!is_null($company)) return $company;

        $cardCloud = Transactions::searchByCardCloudAccount($account);
        if (!is_null($cardCloud)) return $cardCloud;

        return null;
    }

    private function getDestination($account)
    {
        $stpAccount = Transactions::searchByBusinessAccount($account);
        if (!is_null($stpAccount)) return $stpAccount;

        $company = Transactions::searchByCompanyAccount($account);
        if (!is_null($company)) return $company;

        $companyCardCloud = Transactions::searchByCardCloudCompanyAccount($account);
        if (!is_null($companyCardCloud)) return $companyCardCloud;

        $cardCloud = Transactions::searchByCardCloudAccount($account);
        if (!is_null($cardCloud)) return $cardCloud;

        $externalAccount = Transactions::searchByExternalAccount($account);
        if (!is_null($externalAccount)) return $externalAccount;

        return null;
    }

    protected static function processBusinessToBusiness($origin, $destination, $amount, $concept, $request)
    {
        try {
            DB::beginTransaction();

            $out = self::setOutMovement($origin, $destination, $concept, $amount, $request, 'external');
            self::setNewBalance($origin, $out->SourceBalance);
            $commissions = self::calculateOutCommissions('external', $amount, $origin['commissions']);
            $amount = $commissions['total'];

            if (env('APP_ENV') == 'production') {
                $response = StpService::speiOut(
                    $origin['stpAccount']['url'],
                    $origin['stpAccount']['key'],
                    $origin['stpAccount']['company'],
                    $amount,
                    $out->TrackingKey,
                    substr(preg_replace('/[^a-zA-Z0-9\s]/', '', $concept), 0, 38),
                    $origin['stpAccount']['number'],
                    $origin['name'],
                    "",
                    $destination['account'],
                    $destination['name'],
                    $out->Reference,
                    $origin['institution'],
                    "",
                    40,
                    $destination['institution'],
                    40
                );

                if (isset($response->respuesta->id) && count($response->respuesta->id) > 3) {
                    $stpId = $response->respuesta->id;
                } else {
                    DB::rollBack();
                    throw new \Exception("Error:" . ErrorRegisterOrder::error($response->respuesta->id));
                }
            } else {
                $stpId = "1111111111";
            }

            StpTransaction::where('Id', $out->Id)->update([
                'StpId' => $stpId
            ]);

            DB::commit();
            return [
                'destinationsAccount' => $destination['name'],
                'url'  => env('APP_API_URL') . "/spei/transaccion/" . $out['Id'],
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            Log::channel('spei_out')->error(
                "Error al tranferir los fondos a la cuenta " . $destination['account'] . ". Error:",
                [
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine()
                ]
            );

            return [
                'error' => "Error al tranferir los fondos a la cuenta " . $destination['account'] . ", intentelo más tarde o contacte a soporte"
            ];
        }
    }
    protected static function processBusinessToCompany($origin, $destination, $amount, $concept, $request)
    {
        try {
            DB::beginTransaction();

            if ($origin['business'] == $destination['business']) {
                $out = self::setOutMovement($origin, $destination, $concept, $amount, $request, '');

                $in = self::setInMovement($origin, $destination, $concept, $amount, $request, $out);
                self::setNewBalance($destination, $in->DestinationBalance);

                StpTransaction::where('Id', $out->Id)->update([
                    'StatusId' => 3,
                    'LiquidationDate' => Carbon::now(new \DateTimeZone('America/Mexico_City'))->format('Y-m-d H:i:s'),
                    'Active' => 0
                ]);
            } else {
                $out = self::setOutMovement($origin, $destination, $concept, $amount, $request, 'external');
                self::setNewBalance($origin, $out->SourceBalance);

                $commissions = self::calculateOutCommissions('external', $amount, $origin['commissions']);
                $amount = $commissions['total'];

                if (env('APP_ENV') == 'production') {
                    $response = StpService::speiOut(
                        $origin['stpAccount']['url'],
                        $origin['stpAccount']['key'],
                        $origin['stpAccount']['company'],
                        $amount,
                        $out->TrackingKey,
                        substr(preg_replace('/[^a-zA-Z0-9\s]/', '', $concept), 0, 38),
                        $origin['stpAccount']['number'],
                        $origin['name'],
                        "",
                        $destination['account'],
                        $destination['name'],
                        $out->Reference,
                        90646,
                        "",
                        40,
                        90646,
                        40
                    );

                    if (isset($response->respuesta->id) && count($response->respuesta->id) > 3) {
                        $stpId = $response->respuesta->id;
                    } else {
                        DB::rollBack();
                        throw new \Exception("Error:" . ErrorRegisterOrder::error($response->respuesta->id));
                    }
                } else {
                    $stpId = "1111111111";
                }

                StpTransaction::where('Id', $out->Id)->update([
                    'StpId' => $stpId
                ]);
            }

            DB::commit();
            return [
                'destinationsAccount' => $destination['name'],
                'url'  => env('APP_API_URL') . "/spei/transaccion/" . $out['Id'],
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            Log::channel('spei_out')->error(
                "Error al tranferir los fondos a la cuenta " . $destination['account'] . ". Error:" . $e->getMessage(),
                [
                    'file' => $e->getFile(),
                    'line' => $e->getLine()
                ]
            );

            return [
                'error' => "Error al tranferir los fondos a la cuenta " . $destination['account']
            ];
        }
    }

    protected static function processBusinessToCompanyCardCloud($origin, $destination, $amount, $concept, $request)
    {
        try {
            DB::beginTransaction();

            if ($origin['business'] == env('CARD_CLOUD_MAIN_BUSINESS_ID')) {
                $out = self::setOutMovement($origin, $destination, $concept, $amount, $request, '');

                $in = self::setInMovementCardCloud($origin, $destination, $concept, $amount, $request, $out->Reference, $out->TrackingKey);
                self::setNewBalance($destination, $in->DestinationBalance);

                StpTransaction::where('Id', $out->Id)->update([
                    'StatusId' => 3,
                    'LiquidationDate' => Carbon::now(new \DateTimeZone('America/Mexico_City'))->format('Y-m-d H:i:s'),
                    'Active' => 0
                ]);

                try {
                    $client = new Client();
                    $client->request('POST', env('CARD_CLOUD_BASE_URL') . '/subaccount/' . $destination['companyId'] . '/deposit', [
                        'headers' => [
                            'Content-Type' => 'application/json'
                        ],
                        'json' => [
                            'movement_id' => Carbon::now(new \DateTimeZone('America/Mexico_City'))->format('ymdhis'),
                            'amount' => $amount,
                            'reference' => $out->TrackingKey
                        ]
                    ]);
                } catch (RequestException $e) {
                    throw new \Exception('Error al registrar el movimiento en CardCloud: ' . $e->getMessage());
                }
            } else {
                $out = self::setOutMovement($origin, $destination, $concept, $amount, $request, 'external');
                self::setNewBalance($origin, $out->SourceBalance);

                $commissions = self::calculateOutCommissions('external', $amount, $origin['commissions']);
                $amount = $commissions['total'];

                if (env('APP_ENV') == 'production') {
                    $response = StpService::speiOut(
                        $origin['stpAccount']['url'],
                        $origin['stpAccount']['key'],
                        $origin['stpAccount']['company'],
                        $amount,
                        $out->TrackingKey,
                        substr(preg_replace('/[^a-zA-Z0-9\s]/', '', $concept), 0, 38),
                        $origin['stpAccount']['number'],
                        $origin['name'],
                        "",
                        $destination['account'],
                        $destination['name'],
                        $out->Reference,
                        90646,
                        "",
                        40,
                        90646,
                        40
                    );

                    if (isset($response->respuesta->id) && count($response->respuesta->id) > 3) {
                        $stpId = $response->respuesta->id;
                    } else {
                        DB::rollBack();
                        throw new \Exception("Error:" . ErrorRegisterOrder::error($response->respuesta->id));
                    }
                } else {
                    $stpId = "1111111111";
                }

                StpTransaction::where('Id', $out->Id)->update([
                    'StpId' => $stpId
                ]);
            }

            DB::commit();
            return [
                'destinationsAccount' => $destination['name'],
                'url'  => env('APP_API_URL') . "/spei/transaccion/" . $out['Id'],
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            Log::channel('spei_out')->error(
                "Error al tranferir los fondos a la cuenta " . $destination['account'] . ".",
                [
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine()
                ]
            );
            return [
                'error' => "Error al tranferir los fondos a la cuenta " . $destination['account'] . " de " . $destination['name']
            ];
        }
    }

    protected static function processBusinessToCardCloud($origin, $destination, $amount, $concept, $request)
    {
        try {
            DB::beginTransaction();

            if ($origin['business'] == env('CARD_CLOUD_MAIN_BUSINESS_ID')) {
                $out = self::setOutMovement($origin, $destination, $concept, $amount, $request, '');

                $in = self::setInMovementCardCloud($origin, $destination, $concept, $amount, $request, $out->Reference, $out->TrackingKey);
                self::setNewBalance($destination, $in->DestinationBalance);

                StpTransaction::where('Id', $out->Id)->update([
                    'StatusId' => 3,
                    'LiquidationDate' => Carbon::now(new \DateTimeZone('America/Mexico_City'))->format('Y-m-d H:i:s'),
                    'Active' => 0
                ]);

                try {
                    $client = new Client();
                    $client->request('POST', env('CARD_CLOUD_BASE_URL') . '/card/' . $destination['id'] . '/deposit', [
                        'headers' => [
                            'Content-Type' => 'application/json'
                        ],
                        'json' => [
                            'movement_id' => rand(1000000, 9999999),
                            'amount' => $amount,
                            'reference' => $out->TrackingKey
                        ]
                    ]);
                } catch (RequestException $e) {
                    throw new \Exception('Error al registrar el movimiento en CardCloud: ' . $e->getMessage());
                }
            } else {
                $out = self::setOutMovement($origin, $destination, $concept, $amount, $request, 'external');
                self::setNewBalance($origin, $out->SourceBalance);

                $commissions = self::calculateOutCommissions('external', $amount, $origin['commissions']);
                $amount = $commissions['total'];

                if (env('APP_ENV') == 'production') {
                    $response = StpService::speiOut(
                        $origin['stpAccount']['url'],
                        $origin['stpAccount']['key'],
                        $origin['stpAccount']['company'],
                        $amount,
                        $out->TrackingKey,
                        substr(preg_replace('/[^a-zA-Z0-9\s]/', '', $concept), 0, 38),
                        $origin['stpAccount']['number'],
                        $origin['name'],
                        "",
                        $destination['account'],
                        $destination['name'],
                        $out->Reference,
                        90646,
                        "",
                        40,
                        90646,
                        40
                    );

                    if (isset($response->respuesta->id) && count($response->respuesta->id) > 3) {
                        $stpId = $response->respuesta->id;
                    } else {
                        DB::rollBack();
                        throw new \Exception("Error:" . ErrorRegisterOrder::error($response->respuesta->id));
                    }
                } else {
                    $stpId = "1111111111";
                }

                StpTransaction::where('Id', $out->Id)->update([
                    'StpId' => $stpId
                ]);
            }

            DB::commit();

            return [
                'destinationsAccount' => $destination['name'],
                'url'  => env('APP_API_URL') . "/spei/transaccion/" . $out['Id'],
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            Log::channel('spei_out')->error(
                "Error al tranferir los fondos a la cuenta " . $destination['account'] . ".",
                [
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine()
                ]
            );
            return [
                'error' => "Error al tranferir los fondos a la cuenta " . $destination['account'] . " de " . $destination['name']
            ];
        }
    }

    protected static function processBusinessToExternal($origin, $destination, $amount, $concept, $request)
    {
        try {
            $out = self::setOutMovement($origin, $destination, $concept, $amount, $request, 'external');
            self::setNewBalance($origin, $out->SourceBalance);

            $commissions = self::calculateOutCommissions('external', $amount, $origin['commissions']);
            $amount = $commissions['total'];

            if (env('APP_ENV') == 'production') {
                $response = StpService::speiOut(
                    $origin['stpAccount']['url'],
                    $origin['stpAccount']['key'],
                    $origin['stpAccount']['company'],
                    $amount,
                    $out->TrackingKey,
                    substr(preg_replace('/[^a-zA-Z0-9\s]/', '', $concept), 0, 38),
                    $origin['stpAccount']['number'],
                    $origin['name'],
                    "",
                    $destination['account'],
                    $destination['name'],
                    $out->Reference,
                    90646,
                    "",
                    40,
                    90646,
                    40
                );

                if (isset($response->respuesta->id) && count($response->respuesta->id) > 3) {
                    $stpId = $response->respuesta->id;
                } else {
                    DB::rollBack();
                    throw new \Exception("Error:" . ErrorRegisterOrder::error($response->respuesta->id));
                }
            } else {
                $stpId = "1111111111";
            }

            StpTransaction::where('Id', $out->Id)->update([
                'StpId' => $stpId
            ]);

            DB::commit();

            return [
                'destinationsAccount' => $destination['name'],
                'url'  => env('APP_API_URL') . "/spei/transaccion/" . $out['Id'],
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            Log::channel('spei_out')->error(
                "Error al tranferir los fondos a la cuenta " . $destination['account'] . ".",
                [
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine()
                ]
            );
            return [
                'error' => "Error al tranferir los fondos a la cuenta " . $destination['account'] . " de " . $destination['name']
            ];
        }
    }

    protected static function processCompanyToBusiness($origin, $destination, $amount, $concept, $request)
    {
        try {
            DB::beginTransaction();

            if ($origin['business'] == $destination['business']) {
                $out = self::setOutMovement($origin, $destination, $concept, $amount, $request, '');
                self::setNewBalance($origin, $out->SourceBalance);

                self::setInMovement($origin, $destination, $concept, $amount, $request, $out);

                StpTransaction::where('Id', $out->Id)->update([
                    'StatusId' => 3,
                    'LiquidationDate' => Carbon::now(new \DateTimeZone('America/Mexico_City'))->format('Y-m-d H:i:s'),
                    'Active' => 0
                ]);
            } else {
                $out = self::setOutMovement($origin, $destination, $concept, $amount, $request, 'external');
                self::setNewBalance($origin, $out->SourceBalance);

                $commissions = self::calculateOutCommissions('external', $amount, $origin['commissions']);
                $amount = $commissions['total'];

                if (env('APP_ENV') == 'production') {
                    $response = StpService::speiOut(
                        $origin['stpAccount']['url'],
                        $origin['stpAccount']['key'],
                        $origin['stpAccount']['company'],
                        $amount,
                        $out->TrackingKey,
                        substr(preg_replace('/[^a-zA-Z0-9\s]/', '', $concept), 0, 38),
                        $origin['stpAccount']['number'],
                        $origin['name'],
                        "",
                        $destination['account'],
                        $destination['name'],
                        $out->Reference,
                        $origin['institution'],
                        "",
                        40,
                        $destination['institution'],
                        40
                    );

                    if (isset($response->respuesta->id) && count($response->respuesta->id) > 3) {
                        $stpId = $response->respuesta->id;
                    } else {
                        DB::rollBack();
                        throw new \Exception("Error:" . ErrorRegisterOrder::error($response->respuesta->id));
                    }
                } else {
                    $stpId = "1111111111";
                }

                StpTransaction::where('Id', $out->Id)->update([
                    'StpId' => $stpId
                ]);
            }

            DB::commit();
            return [
                'destinationsAccount' => $destination['name'],
                'url'  => env('APP_API_URL') . "/spei/transaccion/" . $out['Id'],
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            Log::channel('spei_out')->error(
                "Error al tranferir los fondos a la cuenta " . $destination['account'] . ". Error:",
                [
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine()
                ]
            );

            return [
                'error' => "Error al tranferir los fondos a la cuenta " . $destination['account'] . ", intentelo más tarde o contacte a soporte"
            ];
        }
    }

    protected static function processCompanyToCompany($origin, $destination, $amount, $concept, $request)
    {
        try {
            DB::beginTransaction();

            if ($origin['business'] == $destination['business']) {
                $out = self::setOutMovement($origin, $destination, $concept, $amount, $request, '');
                self::setNewBalance($origin, $out->SourceBalance);

                $in = self::setInMovement($origin, $destination, $concept, $amount, $request, $out);
                self::setNewBalance($destination, $in->DestinationBalance);

                StpTransaction::where('Id', $out->Id)->update([
                    'StatusId' => 3,
                    'LiquidationDate' => Carbon::now(new \DateTimeZone('America/Mexico_City'))->format('Y-m-d H:i:s'),
                    'Active' => 0
                ]);
            } else {
                $out = self::setOutMovement($origin, $destination, $concept, $amount, $request, 'external');
                self::setNewBalance($origin, $out->SourceBalance);

                $commissions = self::calculateOutCommissions('external', $amount, $origin['commissions']);
                $amount = $commissions['total'];

                if (env('APP_ENV') == 'production') {
                    $response = StpService::speiOut(
                        $origin['stpAccount']['url'],
                        $origin['stpAccount']['key'],
                        $origin['stpAccount']['company'],
                        $amount,
                        $out->TrackingKey,
                        substr(preg_replace('/[^a-zA-Z0-9\s]/', '', $concept), 0, 38),
                        $origin['stpAccount']['number'],
                        $origin['name'],
                        "",
                        $destination['account'],
                        $destination['name'],
                        $out->Reference,
                        90646,
                        "",
                        40,
                        90646,
                        40
                    );

                    if (isset($response->respuesta->id) && count($response->respuesta->id) > 3) {
                        $stpId = $response->respuesta->id;
                    } else {
                        DB::rollBack();
                        throw new \Exception("Error:" . ErrorRegisterOrder::error($response->respuesta->id));
                    }
                } else {
                    $stpId = "1111111111";
                }

                StpTransaction::where('Id', $out->Id)->update([
                    'StpId' => $stpId
                ]);
            }

            DB::commit();
            return [
                'destinationsAccount' => $destination['name'],
                'url'  => env('APP_API_URL') . "/spei/transaccion/" . $out['Id'],
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            Log::channel('spei_out')->error(
                "Error al tranferir los fondos a la cuenta " . $destination['account'] . ". Error:" . $e->getMessage(),
                [
                    'file' => $e->getFile(),
                    'line' => $e->getLine()
                ]
            );

            return [
                'error' => "Error al tranferir los fondos a la cuenta " . $destination['account']
            ];
        }
    }

    protected static function processCompanyToCompanyCardCloud($origin, $destination, $amount, $concept, $request)
    {
        try {
            DB::beginTransaction();

            if ($origin['business'] == env('CARD_CLOUD_MAIN_BUSINESS_ID')) {
                $out = self::setOutMovement($origin, $destination, $concept, $amount, $request, '');
                self::setNewBalance($origin, $out->SourceBalance);

                $in = self::setInMovementCardCloud($origin, $destination, $concept, $amount, $request, $out->Reference, $out->TrackingKey);
                self::setNewBalance($destination, $in->DestinationBalance);

                StpTransaction::where('Id', $out->Id)->update([
                    'StatusId' => 3,
                    'LiquidationDate' => Carbon::now(new \DateTimeZone('America/Mexico_City'))->format('Y-m-d H:i:s'),
                    'Active' => 0
                ]);

                try {
                    $client = new Client();
                    $client->request('POST', env('CARD_CLOUD_BASE_URL') . '/subaccount/' . $destination['companyId'] . '/deposit', [
                        'headers' => [
                            'Content-Type' => 'application/json'
                        ],
                        'json' => [
                            'movement_id' => Carbon::now(new \DateTimeZone('America/Mexico_City'))->format('ymdhis'),
                            'amount' => $amount,
                            'reference' => $out->TrackingKey
                        ]
                    ]);
                } catch (RequestException $e) {
                    throw new \Exception('Error al registrar el movimiento en CardCloud: ' . $e->getMessage());
                }
            } else {
                $out = self::setOutMovement($origin, $destination, $concept, $amount, $request, 'external');
                self::setNewBalance($origin, $out->SourceBalance);

                $commissions = self::calculateOutCommissions('external', $amount, $origin['commissions']);
                $amount = $commissions['total'];

                if (env('APP_ENV') == 'production') {
                    $response = StpService::speiOut(
                        $origin['stpAccount']['url'],
                        $origin['stpAccount']['key'],
                        $origin['stpAccount']['company'],
                        $amount,
                        $out->TrackingKey,
                        substr(preg_replace('/[^a-zA-Z0-9\s]/', '', $concept), 0, 38),
                        $origin['stpAccount']['number'],
                        $origin['name'],
                        "",
                        $destination['account'],
                        $destination['name'],
                        $out->Reference,
                        90646,
                        "",
                        40,
                        90646,
                        40
                    );

                    if (isset($response->respuesta->id) && count($response->respuesta->id) > 3) {
                        $stpId = $response->respuesta->id;
                    } else {
                        throw new \Exception("Error:" . ErrorRegisterOrder::error($response->respuesta->id));
                    }
                } else {
                    $stpId = "1111111111";
                }

                StpTransaction::where('Id', $out->Id)->update([
                    'StpId' => $stpId
                ]);
            }

            DB::commit();
            return [
                'destinationsAccount' => $destination['name'],
                'url'  => env('APP_API_URL') . "/spei/transaccion/" . $out['Id'],
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            Log::channel('spei_out')->error(
                "Error al tranferir los fondos a la cuenta " . $destination['account'] . ".",
                [
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine()
                ]
            );
            return [
                'error' => "Error al tranferir los fondos a la cuenta " . $destination['account'] . " de " . $destination['name']
            ];
        }
    }

    protected static function processCompanyToCardCloud($origin, $destination, $amount, $concept, $request)
    {
        try {
            DB::beginTransaction();

            if ($origin['business'] == env('CARD_CLOUD_MAIN_BUSINESS_ID')) {
                $out = self::setOutMovement($origin, $destination, $concept, $amount, $request, '');
                self::setNewBalance($origin, $out->SourceBalance);

                $in = self::setInMovementCardCloud($origin, $destination, $concept, $amount, $request, $out->Reference, $out->TrackingKey);
                self::setNewBalance($destination, $in->DestinationBalance);

                StpTransaction::where('Id', $out->Id)->update([
                    'StatusId' => 3,
                    'LiquidationDate' => Carbon::now(new \DateTimeZone('America/Mexico_City'))->format('Y-m-d H:i:s'),
                    'Active' => 0
                ]);

                try {
                    $client = new Client();
                    $client->request('POST', env('CARD_CLOUD_BASE_URL') . '/card/' . $destination['id'] . '/deposit', [
                        'headers' => [
                            'Content-Type' => 'application/json'
                        ],
                        'json' => [
                            'movement_id' => rand(1000000, 9999999),
                            'amount' => $amount,
                            'reference' => $out->TrackingKey
                        ]
                    ]);
                } catch (RequestException $e) {
                    throw new \Exception('Error al registrar el movimiento en CardCloud: ' . $e->getMessage());
                }
            } else {
                $out = self::setOutMovement($origin, $destination, $concept, $amount, $request, 'external');
                self::setNewBalance($origin, $out->SourceBalance);

                $commissions = self::calculateOutCommissions('external', $amount, $origin['commissions']);
                $amount = $commissions['total'];

                if (env('APP_ENV') == 'production') {
                    $response = StpService::speiOut(
                        $origin['stpAccount']['url'],
                        $origin['stpAccount']['key'],
                        $origin['stpAccount']['company'],
                        $amount,
                        $out->TrackingKey,
                        substr(preg_replace('/[^a-zA-Z0-9\s]/', '', $concept), 0, 38),
                        $origin['stpAccount']['number'],
                        $origin['name'],
                        "",
                        $destination['account'],
                        $destination['name'],
                        $out->Reference,
                        90646,
                        "",
                        40,
                        90646,
                        40
                    );

                    if (isset($response->respuesta->id) && count($response->respuesta->id) > 3) {
                        $stpId = $response->respuesta->id;
                    } else {
                        throw new \Exception("Error:" . ErrorRegisterOrder::error($response->respuesta->id));
                    }
                } else {
                    $stpId = "1111111111";
                }

                StpTransaction::where('Id', $out->Id)->update([
                    'StpId' => $stpId
                ]);
            }

            DB::commit();

            return [
                'destinationsAccount' => $destination['name'],
                'url'  => env('APP_API_URL') . "/spei/transaccion/" . $out['Id'],
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            Log::channel('spei_out')->error(
                "Error al tranferir los fondos a la cuenta " . $destination['account'] . ".",
                [
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine()
                ]
            );
            return [
                'error' => "Error al tranferir los fondos a la cuenta " . $destination['account'] . " de " . $destination['name']
            ];
        }
    }


    protected static function processCompanyToExternal($origin, $destination, $amount, $concept, $request)
    {
        try {
            $out = self::setOutMovement($origin, $destination, $concept, $amount, $request, 'external');
            self::setNewBalance($origin, $out->SourceBalance);

            $commissions = self::calculateOutCommissions('external', $amount, $origin['commissions']);
            $amount = $commissions['total'];

            if (env('APP_ENV') == 'production') {
                $response = StpService::speiOut(
                    $origin['stpAccount']['url'],
                    $origin['stpAccount']['key'],
                    $origin['stpAccount']['company'],
                    $amount,
                    $out->TrackingKey,
                    substr(preg_replace('/[^a-zA-Z0-9\s]/', '', $concept), 0, 38),
                    $origin['stpAccount']['number'],
                    $origin['name'],
                    "",
                    $destination['account'],
                    $destination['name'],
                    $out->Reference,
                    90646,
                    "",
                    40,
                    90646,
                    40
                );

                if (isset($response->respuesta->id) && count($response->respuesta->id) > 3) {
                    $stpId = $response->respuesta->id;
                } else {
                    DB::rollBack();
                    throw new \Exception("Error:" . ErrorRegisterOrder::error($response->respuesta->id));
                }
            } else {
                $stpId = "1111111111";
            }

            StpTransaction::where('Id', $out->Id)->update([
                'StpId' => $stpId
            ]);

            DB::commit();

            return [
                'destinationsAccount' => $destination['name'],
                'url'  => env('APP_API_URL') . "/spei/transaccion/" . $out['Id'],
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            Log::channel('spei_out')->error(
                "Error al tranferir los fondos a la cuenta " . $destination['account'] . ".",
                [
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine()
                ]
            );
            return [
                'error' => "Error al tranferir los fondos a la cuenta " . $destination['account'] . " de " . $destination['name']
            ];
        }
    }

    protected static function processCardCloudToBusiness($origin, $destination, $amount) {}
    protected static function processCardCloudToCompany($origin, $destination, $amount) {}
    protected static function processCardCloudToCardCloud($origin, $destination, $amount) {}
    protected static function processCardCloudToCompanyCardCloud($origin, $destination, $amount) {}
    protected static function processCardCloudToExternal($origin, $destination, $amount) {}


    protected static function setOutMovement($origin, $destination, $concept, $amount, $request, $type)
    {
        $commissions = self::calculateOutCommissions($type, $amount, $origin['commissions']);

        $transaction = new StpTransaction();
        $transaction->Id = Uuid::uuid7()->toString();
        $transaction->BusinessId = $origin['business'];
        $transaction->TypeId = 1;
        $transaction->StatusId = 1;
        $transaction->Reference =  random_int(1000000, 9999999);
        $transaction->TrackingKey = $origin['stpAccount']['acronym'] . date('YmdHis') . rand(1000, 9999);
        $transaction->Concept = $concept;
        $transaction->SourceAccount = $origin['account'];
        $transaction->SourceName = $origin['name'];
        $transaction->SourceBalance = number_format((float)$origin['balance'] - (float)$commissions['total'], 2, '.', '');
        $transaction->SourceEmail = "";
        $transaction->DestinationAccount = $destination['account'];
        $transaction->DestinationName = $destination['name'];
        $transaction->DestinationBalance = number_format((float)$destination['balance'] + (float)$amount, 2, '.', '');
        $transaction->DestinationEmail = "";
        $transaction->DestinationBankCode = $destination['institution'];
        $transaction->Amount = $amount;
        $transaction->Commissions = json_encode($commissions);
        $transaction->LiquidationDate = null;
        $transaction->UrlCEP = "";
        $transaction->StpId = 0;
        $transaction->ApiData = '[]';
        $transaction->CreatedByUser = $request->attributes->get('jwt')->id;
        $transaction->CreateDate = Carbon::now(new \DateTimeZone('America/Mexico_City'))->format('Y-m-d H:i:s');
        $transaction->Active = 1;
        $transaction->save();

        return $transaction;
    }

    protected static function setInMovement($origin, $destination, $concept, $amount, $request, $outMovement)
    {
        $commissions = [
            'speiOut' => 0,
            'speiIn' => 0,
            'internal' => 0,
            'feeStp' => 0,
            'stpAccount' => 0,
            'total' => $amount
        ];

        $transaction = new StpTransaction();
        $transaction->Id = Uuid::uuid7()->toString();
        $transaction->BusinessId = $origin['business'];
        $transaction->TypeId = 2;
        $transaction->StatusId = 3;
        $transaction->Reference =  $outMovement->Reference;
        $transaction->TrackingKey = $outMovement->TrackingKey;
        $transaction->Concept = $concept;
        $transaction->SourceAccount = $origin['account'];
        $transaction->SourceName = $origin['name'];
        $transaction->SourceBalance = number_format((float)$origin['balance'] - (float)$amount, 2, '.', '');
        $transaction->SourceEmail = "";
        $transaction->DestinationAccount = $destination['account'];
        $transaction->DestinationName = $destination['name'];
        $transaction->DestinationBalance = number_format($destination['balance'] + $amount, 2, '.', '');
        $transaction->DestinationEmail = "";
        $transaction->DestinationBankCode = $destination['institution'];
        $transaction->Amount = $amount;
        $transaction->Commissions = json_encode($commissions);
        $transaction->LiquidationDate = Carbon::now(new \DateTimeZone('America/Mexico_City'))->format('Y-m-d H:i:s');
        $transaction->UrlCEP = "";
        $transaction->StpId = 0;
        $transaction->ApiData = '[]';
        $transaction->CreatedByUser = $request->attributes->get('jwt')->id;
        $transaction->CreateDate = Carbon::now(new \DateTimeZone('America/Mexico_City'))->format('Y-m-d H:i:s');
        $transaction->Active = 0;
        $transaction->save();

        return $transaction;
    }

    public static function setInMovementCardCloud($origin, $destination, $concept, $amount, $request, $reference, $trackingKey)
    {
        $commissions = [
            'speiOut' => 0,
            'speiIn' => 0,
            'internal' => 0,
            'feeStp' => 0,
            'stpAccount' => 0,
            'total' => $amount
        ];

        $transaction = new StpTransaction();
        $transaction->Id = Uuid::uuid7()->toString();
        $transaction->BusinessId = $origin['business'];
        $transaction->TypeId = 2;
        $transaction->StatusId = 3;
        $transaction->Reference =  $reference;
        $transaction->TrackingKey = $trackingKey;
        $transaction->Concept = $concept;
        $transaction->SourceAccount = $origin['account'];
        $transaction->SourceName = $origin['name'];
        $transaction->SourceBalance = number_format((float)$origin['balance'] - (float)$amount, 2, '.', '');
        $transaction->SourceEmail = "";
        $transaction->DestinationAccount = env('CARD_CLOUD_MAIN_STP_ACCOUNT');
        $transaction->DestinationName = $destination['name'];
        $transaction->DestinationBalance = number_format($destination['balance'] + $amount, 2, '.', '');
        $transaction->DestinationEmail = "";
        $transaction->DestinationBankCode = 90646;
        $transaction->Amount = $amount;
        $transaction->Commissions = json_encode($commissions);
        $transaction->LiquidationDate = Carbon::now(new \DateTimeZone('America/Mexico_City'))->format('Y-m-d H:i:s');
        $transaction->UrlCEP = "";
        $transaction->StpId = 0;
        $transaction->ApiData = '[]';
        $transaction->CreatedByUser = $request->attributes->get('jwt')->id;
        $transaction->CreateDate = Carbon::now(new \DateTimeZone('America/Mexico_City'))->format('Y-m-d H:i:s');
        $transaction->Active = 0;
        $transaction->save();

        return $transaction;
    }

    public static function setNewBalance($account, $newBalance)
    {
        switch ($account['type']) {
            case 'business-account':
                StpAccounts::where('Id', $account['id'])->update([
                    'Balance' => $newBalance,
                    'BalanceDate' => Carbon::now(new \DateTimeZone('America/Mexico_City'))->format('Y-m-d H:i:s')
                ]);
                break;

            case 'company-account':
                CompanyProjection::where('Id', $account['id'])->update([
                    'Balance' => $newBalance
                ]);

                Company::where('Id', $account['id'])->update([
                    'Balance' => $newBalance
                ]);
                break;
            case 'company-card-cloud-account':
                $company = Company::where('Id', env('CARD_CLOUD_MAIN_COMPANY_ID'))->first();
                $balance = $company->Balance + $newBalance;

                Company::where('Id', env('CARD_CLOUD_MAIN_COMPANY_ID'))->update([
                    'Balance' => $balance
                ]);

                CompanyProjection::where('Id', env('CARD_CLOUD_MAIN_COMPANY_ID'))->update([
                    'Balance' => $balance
                ]);

                break;
        }
    }

    public static function calculateOutCommissions($type, $amount, $commissions)
    {
        $commissionsReturn = [
            'speiOut' => 0,
            'speiIn' => 0,
            'internal' => 0,
            'feeStp' => 0,
            'stpAccount' => 0,
            'total' => $amount
        ];
        switch ($type) {
            case 'internal':
                $commissionsReturn['internal'] = $amount * ($commissions['internal'] / 100);
                $commissionsReturn['total'] = $amount + $commissionsReturn['internal'];
                break;
            case 'external':
                $commissionsReturn['speiOut'] = $amount * ($commissions['speiOut'] / 100);
                $commissionsReturn['total'] = $amount + $commissionsReturn['speiOut'];
                $commissionsReturn['feeStp'] = $commissions['feeStp'];
                $commissionsReturn['total'] += $commissionsReturn['feeStp'];
                break;
            default:
                break;
        }

        return $commissionsReturn;
    }
}
