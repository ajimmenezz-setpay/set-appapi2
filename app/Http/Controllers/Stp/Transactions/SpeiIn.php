<?php

namespace App\Http\Controllers\Stp\Transactions;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Security\Crypt;
use App\Http\Services\STPApi;
use App\Models\Backoffice\Companies\CompanyProjection;
use App\Models\Backoffice\Companies\Company;
use App\Models\Speicloud\StpAccounts;
use App\Models\Speicloud\StpTransaction;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Ramsey\Uuid\Uuid;
use Illuminate\Support\Facades\DB;

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
                $movements = $this->proccessMovements($movements, $account);
                $response[$account->Id]['movements'] = $movements;
            } catch (\Exception $e) {
                $response[$account->Id]['errors'][] = $e->getMessage();
                continue;
            }
        }

        return response()->json($response);
    }

    private function proccessMovements($movements, $account)
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
                $response[] = $this->proccessAsBusiness($movement, $account->BusinessId);
                continue;
            }

            $company = $this->searchByCompanyAccount($movement->cuentaBeneficiario);
            if (!is_null($company)) {
                $response[] = $this->proccessAsCompany($movement, $company);
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

    private function proccessAsBusiness($movement, $businessId)
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

    private function proccessAsCompany($movement, $company)
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

    private function calculateCompanyCommission($comissions, $amount)
    {
        $response = [
            'speiOut' => 0,
            'speiIn' => 0,
            'internal' => 0,
            'feeStp' => 0,
            'stpAccount' => 0,
            'total' => $amount
        ];

        $comissions = json_decode($comissions);

        foreach ($comissions as $commission) {
            if ($commission->type == 2) {
                $response['speiIn'] = $amount * ($commission->speiIn / 100);
                $response['total'] -= $response['speiIn'];
            }
        }

        return $response;
    }

    public static function transactionBase($movement, $businessId, $comissions, $destinationBalance = 0)
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
        $transaction->DestinationAccount = $movement->cuentaBeneficiario;
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
}
