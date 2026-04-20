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
use App\Models\Speicloud\StpInstitutions;

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

    /**
     * @OA\Post(
     *      path="/api/speiCloud/transaction/processPaymentsFile",
     *      summary="Procesar pagos desde un archivo",
     *      description="Este endpoint permite procesar múltiples pagos desde un archivo Excel",
     *      tags={"SPEI Cloud"},
     *      operationId="processPaymentsFile",
     *      security={{"bearerAuth":{}}},
     *      @OA\RequestBody(
     *          @OA\MediaType(
     *              mediaType="multipart/form-data",
     *              @OA\Schema(
     *                  @OA\Property(
     *                      property="origin_account",
     *                      type="string",
     *                      description="Cuenta de origen"
     *                  ),
     *                  @OA\Property(
     *                      property="file",
     *                      type="file",
     *                      description="Archivo Excel con los datos de los pagos"
     *                  ),
     *                  @OA\Property(
     *                      property="googleAuthenticatorCode",
     *                      type="string",
     *                      description="Código de Google Authenticator"
     *                  )
     *              )
     *          )
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Operación exitosa"
     *      ),
     *      @OA\Response(
     *          response=400,
     *          description="Solicitud inválida"
     *      )
     * )
     */
    public function processPaymentsFile(Request $request)
    {
        try {
            $request->validate([
                'origin_account' => 'required|string|min:18|max:18',
                'file' => 'required|file|mimes:xlsx',
                'googleAuthenticatorCode' => 'required|string'
            ], [
                'origin_account.required' => 'La cuenta de origen es requerida',
                'origin_account.string' => 'La cuenta de origen debe ser una cadena de texto',
                'origin_account.min' => 'La cuenta de origen debe tener 18 caracteres',
                'origin_account.max' => 'La cuenta de origen debe tener 18 caracteres',
                'file.required' => 'El archivo es requerido',
                'file.file' => 'El archivo debe ser un archivo válido',
                'file.mimes' => 'El archivo debe ser un archivo de Excel (.xlsx)',
                'googleAuthenticatorCode.required' => 'El código de Google Authenticator es requerido',
                'googleAuthenticatorCode.string' => 'El código de Google Authenticator debe ser una cadena de texto'
            ]);

            $file = $request->file('file');

            // GoogleAuth::authorized($request->attributes->get('jwt')->id, $request->input('googleAuthenticatorCode'));

            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($file->getPathname());
            $sheet = $spreadsheet->getSheet(0);
            $header = $sheet->rangeToArray('A1:D1')[0];
            $expectedHeader = ['Beneficiario', 'CLABE', 'Monto', 'Concepto'];
            if ($header !== $expectedHeader) {
                throw new \Exception('El archivo no tiene la estructura correcta, las columnas deben ser: Beneficiario, CLABE, Monto, Concepto');
            }

            $actions = [];
            $row = 2;
            $origin = Transactions::searchByCompanyAccount($request->input('origin_account'));
            $totalAmount = 0;
            $totalCommissions = 0;

            while (true) {
                $data = $sheet->rangeToArray('A' . $row . ':D' . $row)[0];
                if (empty(array_filter($data))) {
                    break;
                }

                if (strlen($data[0]) > 100) {
                    throw new \Exception('El beneficiario en la fila ' . $row . ' no es válido. La longitud máxima permitida es de 100 caracteres.');
                }

                if (!preg_match('/^\d{18}$/', $data[1])) {
                    throw new \Exception('La CLABE en la fila ' . $row . ' no es válida. Asegúrate de que tenga 18 dígitos y solo contenga números.');
                }

                if (!is_numeric($data[2]) || $data[2] <= 0 || !preg_match('/^\d+(\.\d{1,2})?$/', $data[2])) {
                    throw new \Exception('El monto en la fila ' . $row . ' no es válido. Asegúrate de que sea un número mayor a 0 y con máximo dos decimales.');
                }

                if (empty(trim($data[3]))) {
                    $data[3] = "Transferencia";
                } else {
                    if (strlen($data[3]) > 38) {
                        throw new \Exception('El concepto en la fila ' . $row . ' no es válido. La longitud máxima permitida es de 38 caracteres.');
                    }

                    if (preg_match('/[^a-zA-Z0-9\s]/', $data[3])) {
                        throw new \Exception('El concepto en la fila ' . $row . ' no es válido. No se permiten caracteres especiales.');
                    }
                }

                $bank = $this->validateClabe($data[1]);

                $destination = $this->getDestination($data[1]);
                if (is_null($destination)) {
                    $destination = [
                        'type' => 'external-account',
                        'business' => 0,
                        'balance' => 0,
                        'account' => $data[1],
                        'name' => $data[0],
                        'institution' => $bank->Code
                    ];
                }

                $commissionsType = $destination['type'] == 'external-account' ? 'external' : 'internal';
                $commissions = self::calculateOutCommissions($commissionsType, $data[2], $origin['commissions']);

                $totalAmount += $data[2];
                $totalCommissions += $commissions['total'] - $data[2];

                $actions[] = [
                    'destination' => $destination,
                    'comission' => $commissions['total'],
                    'beneficiary' => $data[0],
                    'clabe' => $data[1],
                    'amount' => $data[2],
                    'concept' => $data[3],
                    'bank' => $bank,
                ];

                $row++;
            }

            if ($origin['balance'] < ($totalAmount + $totalCommissions)) {
                throw new \Exception('No hay suficiente saldo en la cuenta de origen para realizar las transferencias. Saldo disponible: $' . number_format($origin['balance'], 2, '.', ',') . ', monto total a transferir (incluyendo comisiones): $' . number_format($totalAmount + $totalCommissions, 2, '.', ','));
            }

            $processResults = $this->processPaymentsFileActions($origin, $actions, $request);

            if (count($processResults['errors']) > 0) {
                return response()->json([
                    'error' => $processResults['errors'],
                    'destinos' => $processResults['destinos']
                ]);
            }

            return response()->json($processResults['destinos']);
        } catch (\Exception $e) {
            return self::basicError($e->getMessage());
        }
    }

    public function processPaymentsFileActions($origin, $actions, $paymentRequest = null)
    {
        $destinos = [];
        $errors = [];

        foreach ($actions as $action) {
            try {
                $method = self::$handlers[$origin['type']][$action['destination']['type']];
                $handler = self::$method($origin, $action['destination'], $action['amount'], $action['concept'], $paymentRequest);
                if (isset($handler['error'])) {
                    $errors[] = $handler['error'];
                } else {
                    $destinos[] = [
                        'destinationsAccount' => $handler['destinationsAccount'],
                        'url'  => $handler['url'],
                        'balance' => $origin['balance'],
                        'amount' => $action['amount'],
                        'comission' => $action['comission'] - $action['amount']
                    ];
                    $origin['balance'] -= $action['amount'] + ($action['comission'] - $action['amount']);
                }
            } catch (\Exception $e) {
                $errors[] = $e->getMessage();
            }
        }
        return ['destinos' => $destinos, 'errors' => $errors];
    }

    public function validateClabe($clabe)
    {
        if (!preg_match('/^\d{18}$/', $clabe)) {
            throw new \Exception('La CLABE no es válida. Asegúrate de que tenga 18 dígitos y solo contenga números.');
        }

        $bank = $this->getInstitutionCodeByClabe($clabe);

        $weights = [3, 7, 1];
        $sum = 0;
        for ($i = 0; $i < 17; $i++) {
            $sum += intval($clabe[$i]) * $weights[$i % 3];
        }
        $calculatedCheckDigit = (10 - ($sum % 10)) % 10;
        $checkDigit = intval($clabe[17]);
        if ($calculatedCheckDigit !== $checkDigit) {
            throw new \Exception('La CLABE ' . $clabe . ' no es válida. El dígito verificador es incorrecto.');
        }
        return $bank;
    }

    public static function getInstitutionCodeByClabe($clabe)
    {
        $bankCode = substr($clabe, 0, 3);
        $bank = StpInstitutions::where('Code', 'like', '%' . $bankCode)->first();
        if (isset($bank->Id)) {
            return $bank;
        } else {
            throw new \Exception('No se encontró el banco para la CLABE proporcionada');
        }
    }

    public function processPayments(Request $request)
    {
        try {
            $actions = Crypt::opensslDecrypt($request->all());
            $actions = json_decode($actions);
            GoogleAuth::authorized($request->attributes->get('jwt')->id, $actions->googleAuthenticatorCode);

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
                    number_format($amount, 2, '.', ''),
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

                if (isset($response->resultado->id) && strlen((string)abs($response->resultado->id)) > 3) {
                    $stpId = $response->respuesta->id;
                } else {
                    DB::rollBack();
                    throw new \Exception("Error:" . ErrorRegisterOrder::error($response->respuesta->id));
                }
            } else {
                $stpId = "1111111111";
                sleep(1);
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
                    sleep(1);
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
                    sleep(1);
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
                    sleep(1);
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
                sleep(1);
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
                    sleep(1);
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
                    sleep(1);
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
                    sleep(1);
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
                    sleep(1);
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
                sleep(1);
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

    public static function calculateOutCommissions($type, $amount, $commissions = [])
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
