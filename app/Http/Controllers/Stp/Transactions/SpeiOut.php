<?php

namespace App\Http\Controllers\Stp\Transactions;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Security\Crypt;
use Illuminate\Http\Request;
use App\Http\Controllers\Security\GoogleAuth;
use App\Http\Controllers\Stp\ErrorRegisterOrder;
use App\Http\Controllers\Stp\Transactions\Transactions;
use App\Models\Backoffice\Companies\CompanyProjection;
use App\Models\Speicloud\StpAccounts;
use App\Models\Speicloud\StpTransaction;
use App\Services\StpService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Ramsey\Uuid\Uuid;

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

            $total = $this->totalTransactions($actions->destinationsAccounts);
            $origin = $this->getOrigin($actions->originBankAccount);

            if ($origin['type'] == 'business-account') {
                Transactions::updateAccountBalance($origin['id']);
            }

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

                    $handler = self::$handlers[$origin['type']][$d->type]($origin, $destination, $d->amount, $actions->concept, $request);

                    $destinos[] = $destination;
                } catch (\Exception $e) {
                    $errors[] = 'No se ha podido procesar la cuenta ' . $d->bankAccount . ' destino. ' . $e->getMessage();
                }
            }

            if ($total > $origin['balance']) {
                throw new \Exception('Fondos insuficientes, por favor verifique su saldo o espere a que se acrediten los fondos pendientes');
            }


            $actions->total = $total;
            $actions->origin = $origin;

            return response()->json([
                'destinations' => $destinos,
                'errors' => $errors,
                'actions' => $actions
            ]);
        } catch (\Exception $e) {
            return self::basicError($e->getMessage());
        }
    }

    private function totalTransactions($transactions)
    {
        $total = 0;
        foreach ($transactions as $transaction) {
            $total += $transaction->amount;
        }
        return $total;
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

            $stpAccount = StpAccounts::where('Id', $origin['id'])->first();
            $original_balance = $stpAccount->Balance;
            $traceKey = $stpAccount->Acronym . date('YmdHis') . rand(1000, 9999);
            $reference = random_int(1000000, 9999999);

            if (env('APP_ENV') == 'production') {
                $response = StpService::speiOut(
                    Crypt::decrypt($stpAccount->Url),
                    Crypt::decrypt($stpAccount->Key),
                    $stpAccount->Company,
                    $amount,
                    $traceKey,
                    $concept,
                    Crypt::decrypt($stpAccount->Number),
                    $stpAccount->Company,
                    "",
                    Crypt::decrypt($destination['account']),
                    $destination['name'],
                    $reference,
                    90646,
                    "",
                    40,
                    90646,
                    40
                );

                if (isset($response->respuesta->id) && count($response->respuesta->id) > 3) {
                    $stpId = $response->respuesta->id;
                    StpAccounts::where('Id', $origin['id'])->update([
                        'Balance' => $stpAccount->Balance - $amount,
                        'PendingCharges' => $stpAccount->PendingCharges + $amount,
                        'BalanceDate' => Carbon::now(new \DateTimeZone('America/Mexico_City'))->format('Y-m-d H:i:s')
                    ]);
                } else {
                    DB::commit();
                    throw new \Exception("Error al registrar la orden en STP. Error:" . ErrorRegisterOrder::error($response->respuesta->id));
                }
            } else {
                $response = new \stdClass();
                $response->resultado = new \stdClass();
                $response->resultado->id = "1111111111";
                $stpId = $response->resultado->id;
            }

            StpTransaction::create([
                'Id' => Uuid::uuid7(),
                'BusinessId' => $stpAccount->BusinessId,
                'TypeId' => 1,
                'StatusId' => 1,
                'Reference' => $reference,
                'TrackingKey' => $traceKey,
                'Concept' => $concept,
                'SourceAccount' => Crypt::decrypt($stpAccount->Number),
                'SourceName' => $stpAccount->Company,
                'SourceBalance' => number_format((float)$original_balance, 2, '.', ''),
                'SourceEmail' => "",
                'DestinationAccount' => Crypt::decrypt($destination['account']),
                'DestinationName' => $destination['name'],
                'DestinationBalance' => number_format((float)$original_balance, 2, '.', ''),
                'DestinationEmail' => "",
                'DestinationBankCode' => 90646,
                'Amount' => number_format((float)$original_balance, 2, '.', ''),
                'Commissions' => json_encode($origin['commissions']),
                'LiquidationDate' => "0000-00-00 00:00:00",
                'UrlCEP' => "",
                'StpId' => $stpId,
                'ApiData' => '[]',
                'CreatedByUser' => $request->attributes->get('jwt')->id,
                'CreateDate' => Carbon::now(new \DateTimeZone('America/Mexico_City'))->format('Y-m-d H:i:s'),
                'Active' => 1
            ]);

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
    protected static function processBusinessToCompany($origin, $destination, $amount) {}
    protected static function processBusinessToCardCloud($origin, $destination, $amount) {}
    protected static function processBusinessToCompanyCardCloud($origin, $destination, $amount)
    { /* ... */
    }
    protected static function processBusinessToExternal($origin, $destination, $amount)
    { /* ... */
    }

    protected static function processCompanyToBusiness($origin, $destination, $amount)
    {
        try{
            DB::beginTransaction();

            $company = CompanyProjection::where('Id', $origin['id'])->first();

            if($origin['business'] != $destination['business']){

            }



            $stpAccount = StpAccounts::where('Id', $destination['id'])->first();




        }catch(\Exception $e){
            throw $e;
        }
    }
    protected static function processCompanyToCompany($origin, $destination, $amount)
    { /* ... */
    }
    protected static function processCompanyToCardCloud($origin, $destination, $amount)
    { /* ... */
    }
    protected static function processCompanyToCompanyCardCloud($origin, $destination, $amount)
    { /* ... */
    }
    protected static function processCompanyToExternal($origin, $destination, $amount)
    { /* ... */
    }

    protected static function processCardCloudToBusiness($origin, $destination, $amount)
    { /* ... */
    }
    protected static function processCardCloudToCompany($origin, $destination, $amount)
    { /* ... */
    }
    protected static function processCardCloudToCardCloud($origin, $destination, $amount)
    { /* ... */
    }
    protected static function processCardCloudToCompanyCardCloud($origin, $destination, $amount)
    { /* ... */
    }
    protected static function processCardCloudToExternal($origin, $destination, $amount)
    { /* ... */
    }
}
