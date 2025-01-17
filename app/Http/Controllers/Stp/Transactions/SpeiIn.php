<?php

namespace App\Http\Controllers\Stp\Transactions;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Security\Crypt;
use App\Http\Services\STPApi;
use App\Models\Backoffice\Companies\CompanyProjection;
use App\Models\Backoffice\Companies\Company;
use App\Models\Backoffice\Companies\CompanySpeiAccount;
use App\Models\CardCloud\CardSpeiAccount;
use App\Models\Speicloud\StpAccounts;
use App\Models\Speicloud\StpTransaction;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Ramsey\Uuid\Uuid;
use Illuminate\Support\Facades\DB;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Log;

class SpeiIn extends Controller
{
    public function register(Request $request)
    {
        $response = [];
        $accounts = StpAccounts::where('Active', 1)->get();
        $date = isset($request->date) ? $request->date : Carbon::now()->format('Ymd');
        foreach ($accounts as $account) {
            if (!isset($response[$account->Id])) {
                $response[$account->Id] = [
                    'company' => $account->Company,
                    'movements' => [],
                    'errors' => []
                ];
            }

            try {
                $movements = STPApi::collection(Crypt::decrypt($account->Url), Crypt::decrypt($account->Key), $account->Company, $date);
                $movements = $this->processMovements($movements, $account);
                $response[$account->Id]['movements'] = $movements;
            } catch (\Exception $e) {
                $response[$account->Id]['errors'][] = $e->getMessage() . " " . $e->getLine();
                continue;
            }
        }

        return response()->json($response);
    }

    private function processMovements($movements, $account)
    {
        $response = [];
        foreach ($movements as $movement) {
            if (StpTransaction::where('StpId', $movement->id)->exists()) {
                $response[] = [
                    'id' => $movement->id,
                    'status' => 'Already registered'
                ];
                continue;
            }

            if ($this->searchByBusinessAcount($movement->cuentaBeneficiario, Crypt::decrypt($account->Number))) {
                $response[] = $this->processAsBusiness($movement, $account->BusinessId);
                continue;
            }

            $company = $this->searchByCompanyAccount($movement->cuentaBeneficiario);
            if (!is_null($company)) {
                $response[] = $this->processAsCompany($movement, $company);
                continue;
            }

            $cardCloudCompany = $this->searchByCardCloudCompanyAccount($movement->cuentaBeneficiario);
            if (!is_null($cardCloudCompany)) {
                $response[] = $this->processCardCloudMovement($movement, 'company', $cardCloudCompany->Id);
                continue;
            }

            $cardCloud = $this->searchByCardCloud($movement->cuentaBeneficiario);
            if (!is_null($cardCloud)) {
                $response[] = $this->processCardCloudMovement($movement, 'card', null, $cardCloud->CardId);
                continue;
            }
        }

        return $response;
    }

    private function searchByBusinessAcount($beneficiaryAccount, $businessAccount)
    {
        if ($beneficiaryAccount == $businessAccount) {
            return true;
        }
        return false;
    }

    private function processAsBusiness($movement, $businessId)
    {
        $comissions = json_encode([
            "speiOut" => 0,
            "speiIn" => 0,
            "internal" => 0,
            "feeStp" => 0,
            "stpAccount" => 0,
            "total" => $movement->monto
        ]);
        $transaction = self::transactionBase($movement, $businessId, $comissions);
        $transaction->save();
        return [
            'id' => $movement->id,
            'destination' => $movement->cuentaBeneficiario,
            'amount' => $movement->monto,
            'status' => 'Registered as business'
        ];
    }

    private function searchByCompanyAccount($beneficiaryAccount)
    {
        $company = CompanyProjection::where('Services', 'like', '%' . $beneficiaryAccount . '%')->first();
        if ($company) {
            $services = json_decode($company->Services);
            $isSpeiCloudCompany = false;
            foreach ($services as $service) {
                if ($service->type = 4 && $service->bankAccountNumber == $beneficiaryAccount) {
                    $isSpeiCloudCompany = true;
                    break;
                }
            }

            if ($isSpeiCloudCompany) {
                return $company;
            }
        }
        return null;
    }

    private function processAsCompany($movement, $company)
    {
        $comissions = $this->calculateCompanyCommission($company->Commissions, $movement->monto);
        $companyBalance = $company->Balance + $comissions['total'];

        DB::beginTransaction();
        try {
            CompanyProjection::where('Id', $company->Id)
                ->update([
                    'Balance' => $companyBalance
                ]);

            Company::where('Id', $company->CompanyId)
                ->update([
                    'Balance' => $companyBalance
                ]);

            $transaction = self::transactionBase($movement, $company->BusinessId, json_encode($comissions), $companyBalance);
            $transaction->save();
            DB::commit();

            return [
                'id' => $movement->id,
                'destination' => $movement->cuentaBeneficiario,
                'amount' => $movement->monto,
                'status' => 'Registered as company'
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            throw new \Exception('Error al registrar la transacción. ' . $e->getMessage());
        }

        return $comissions;
    }

    private function calculateCompanyCommission($comissions = null, $amount)
    {
        $response = [
            'speiOut' => 0,
            'speiIn' => 0,
            'internal' => 0,
            'feeStp' => 0,
            'stpAccount' => 0,
            'total' => $amount
        ];

        if (!is_null($comissions)) {
            $comissions = json_decode($comissions);

            foreach ($comissions as $commission) {
                if ($commission->type == 2) {
                    $response['speiIn'] = $amount * ($commission->speiIn / 100);
                    $response['total'] -= $response['speiIn'];
                }
            }
        }

        return $response;
    }

    public static function transactionBase($movement, $businessId, $comissions, $destinationBalance = 0, $cardCloudAccount = null)
    {
        $transaction = new StpTransaction();
        $transaction->Id = Uuid::uuid7();
        $transaction->BusinessId = $businessId;
        $transaction->TypeId = 2;
        $transaction->StatusId = 3;
        $transaction->Reference = $movement->referenciaNumerica;
        $transaction->TrackingKey = $movement->claveRastreo;
        $transaction->Concept = $movement->conceptoPago;
        $transaction->SourceAccount = $movement->cuentaOrdenante;
        $transaction->SourceName = $movement->nombreOrdenante;
        $transaction->SourceBalance = 0;
        $transaction->SourceEmail = "";
        $transaction->DestinationAccount = ($cardCloudAccount) ? $cardCloudAccount : $movement->cuentaBeneficiario;
        $transaction->DestinationName = $movement->nombreBeneficiario;
        $transaction->DestinationBalance = $destinationBalance;
        $transaction->DestinationEmail = "";
        $transaction->DestinationBankCode = 90646;
        $transaction->Amount = $movement->monto;
        $transaction->Commissions = $comissions;

        $date = Carbon::createFromTimestampMs($movement->tsLiquidacion);

        $transaction->LiquidationDate = $date->toDateTimeString();
        $transaction->UrlCEP = "";
        $transaction->StpId = $movement->id;
        $transaction->ApiData = json_encode($movement);
        $transaction->CreatedByUser = "";
        $transaction->CreateDate = Carbon::now('America/Mexico_City')->toDateTimeString();
        $transaction->Active = 0;
        return $transaction;
    }

    public function searchByCardCloudCompanyAccount($beneficiaryAccount)
    {
        $company = CompanySpeiAccount::where('Clabe', $beneficiaryAccount)->first();
        if ($company) {
            return $company;
        }
        return null;
    }

    public function searchByCardCloud($beneficiaryAccount)
    {
        $card = CardSpeiAccount::where('Clabe', $beneficiaryAccount)->first();
        if ($card) {
            return $card;
        }
        return null;
    }

    public function processCardCloudMovement($movement, $type, $cardCloudCompany = null, $cardCloudCard = null)
    {
        $company = Company::where('Id', env('CARD_CLOUD_MAIN_COMPANY_ID'))->first();
        $comissions = $this->calculateCompanyCommission(null, $movement->monto);
        $companyBalance = $company->Balance + $comissions['total'];

        try {
            DB::beginTransaction();

            if ($type == 'company') {
                $this->processCardCloudCompany($cardCloudCompany, $movement);
            } else if ($type == 'card') {
                $this->processCardCloudCard($cardCloudCard, $movement);
            }

            CompanyProjection::where('Id', $company->Id)
                ->update([
                    'Balance' => $companyBalance
                ]);

            Company::where('Id', $company->CompanyId)
                ->update([
                    'Balance' => $companyBalance
                ]);

            $transaction = self::transactionBase($movement, $company->BusinessId, json_encode($comissions), $companyBalance, env('CARD_CLOUD_MAIN_STP_ACCOUNT'));
            $transaction->save();

            DB::commit();

            return [
                'id' => $movement->id,
                'destination' => $movement->cuentaBeneficiario,
                'amount' => $movement->monto,
                'status' => 'Registered as company card cloud'
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            throw new \Exception('Error al registrar la transacción. ' . $e->getMessage());
        }
    }

    public function processCardCloudCompany($company, $movement)
    {
        try {
            $client = new Client();
            $client->request('GET', env('CARD_CLOUD_BASE_URL') . '/subaccount/' . $company . '/deposit', [
                'headers' => [
                    'Content-Type' => 'application/json'
                ],
                'json' => [
                    'movement_id' => $movement->id,
                    'amount' => $movement->monto,
                    'reference' => $movement->claveRastreo
                ]
            ]);

            return true;
        } catch (RequestException $e) {
            Log::error('Error al registrar la transacción. ' . $e->getMessage());
            throw new \Exception('Error al registrar la transacción. ' . $e->getMessage());
        }
    }

    public function processCardCloudCard($card, $movement)
    {
        try {
            $client = new Client();
            $client->request('GET', env('CARD_CLOUD_BASE_URL') . '/card/' . $card . '/deposit', [
                'headers' => [
                    'Content-Type' => 'application/json'
                ],
                'json' => [
                    'movement_id' => $movement->id,
                    'amount' => $movement->monto,
                    'reference' => $movement->claveRastreo
                ]
            ]);

            return true;
        } catch (RequestException $e) {
            Log::error('Error al registrar la transacción. ' . $e->getMessage());
            throw new \Exception('Error al registrar la transacción. ' . $e->getMessage());
        }
    }
}
