<?php

namespace App\Http\Controllers\Stp\Transactions;

use App\Http\Controllers\Controller;
use App\Models\Backoffice\Companies\Company;
use App\Models\Backoffice\Companies\CompanyProjection;
use App\Models\Speicloud\StpAccounts;
use App\Models\Speicloud\StpTransaction;
use App\Http\Controllers\Security\Crypt;
use App\Http\Controllers\Stp\Accounts;
use App\Models\Backoffice\Companies\CompanySpeiAccount;
use App\Models\CardCloud\CardSpeiAccount;
use App\Models\Speicloud\ExternalAccount;
use App\Services\StpService;

class Transactions extends Controller
{
    public function fixStpBalances()
    {
        return response()->json(self::fixBalances());
    }

    public static function fixBalances()
    {
        $companies = [];
        foreach (CompanyProjection::all() as $company) {
            $balance = 0;
            $services = json_decode($company->Services);
            foreach ($services as $service) {
                if ($service->type != 4) {
                    continue;
                }
                $companies[$company->Id] = [
                    'id' => $company->Id,
                    'name' => $company->TradeName,
                    'balance' => $company->Balance,
                    'clabe' => $service->bankAccountNumber,
                    'movements' => self::getAccountMovements($service->bankAccountNumber)
                ];
            }

            if (!isset($companies[$company->Id])) {
                continue;
            }

            if ($companies[$company->Id]['movements']->count() == 0) {
                unset($companies[$company->Id]);
            } else {
                foreach ($companies[$company->Id]['movements'] as $movement) {
                    $commissions = null;
                    if ($movement->Commissions && $movement->Commissions != "") {
                        $commissions = json_decode($movement->Commissions);
                    }

                    $total_amount = is_null($commissions) ? $movement->Amount : $commissions->total;
                    if ($movement->TypeId == 1) $total_amount = $total_amount * -1;

                    $balance += $total_amount;

                    StpTransaction::where('Id', $movement->Id)->update([
                        ($movement->TypeId == 1 ? 'SourceBalance' : 'DestinationBalance') => $balance
                    ]);
                }
                $companies[$company->Id]['new_balance'] = number_format($balance, 2, '.', '');
                unset($companies[$company->Id]['movements']);
            }

            CompanyProjection::where('Id', $company->Id)
                ->update([
                    'Balance' => $balance
                ]);

            Company::where('Id', $company->Id)
                ->update([
                    'Balance' => $balance
                ]);
        }

        return [
            'total' => count($companies),
            'companies' => $companies
        ];
    }

    public static function getAccountMovements($bankAccountNumber)
    {
        return StpTransaction::whereIn('StatusId', [3, 1])
            ->where(function ($query) use ($bankAccountNumber) {
                $query->where(function ($query) use ($bankAccountNumber) {
                    $query->where('SourceAccount', $bankAccountNumber)
                        ->where('TypeId', 1);
                })->orWhere(function ($query) use ($bankAccountNumber) {
                    $query->where('DestinationAccount', $bankAccountNumber)
                        ->where('TypeId', 2);
                });
            })->orderBy('CreateDate', 'asc')->get();
    }

    public static function searchByBusinessAccount($account, $origin = false)
    {
        $accounts = StpAccounts::all();
        foreach ($accounts as $stpAccount) {
            if ($account == Crypt::decrypt($stpAccount->Number)) {

                if ($origin) {
                    $stpAccount = self::updateAccountBalance($stpAccount);
                }

                return [
                    'type' => 'business-account',
                    'business' => $stpAccount->BusinessId,
                    'balance' => $stpAccount->Balance,
                    'account' => $account,
                    'name' => $stpAccount->Company,
                    'institution' => 90646,
                    'id' => $stpAccount->Id,
                    'commissions' => [
                        'speiOut' => 0,
                        'speiIn' => 0,
                        'internal' => 0,
                        'feeStp' => 0,
                        'stpAccount' => 0
                    ],
                    'stpAccount' => [
                        'id' => $stpAccount->Id,
                        'acronym' => $stpAccount->Acronym,
                        'number' => Crypt::decrypt($stpAccount->Number),
                        'url' => Crypt::decrypt($stpAccount->Url),
                        'key' => Crypt::decrypt($stpAccount->Key),
                        'company' => $stpAccount->Company,
                        'balance' => $stpAccount->Balance
                    ]
                ];
            }
        }
        return null;
    }

    public static function searchByCompanyAccount($account)
    {
        $company = CompanyProjection::where('Services', 'like', '%' . $account . '%')->first();
        if ($company) {
            $services = json_decode($company->Services);
            $isSpeiCloudCompany = false;
            $stpAccount = null;
            foreach ($services as $service) {
                if ($service->type = 4 && $service->bankAccountNumber == $account) {
                    $isSpeiCloudCompany = true;
                    $stpAccount = StpAccounts::where('Id', $service->stpAccountId)->first();
                    break;
                }
            }

            $companyCommissions = [
                'speiOut' => 0,
                'speiIn' => 0,
                'internal' => 0,
                'feeStp' => 0,
                'stpAccount' => 0
            ];

            $commissions = json_decode($company->Commissions);
            foreach ($commissions as $commission) {
                if ($commission->type == 2) {
                    $companyCommissions['speiOut'] = $commission->speiOut;
                    $companyCommissions['speiIn'] = $commission->speiIn;
                    $companyCommissions['internal'] = $commission->internal;
                    $companyCommissions['feeStp'] = $commission->feeStp;
                    $companyCommissions['stpAccount'] = $commission->stpAccount;
                }
            }

            if ($isSpeiCloudCompany) {
                return [
                    'type' => 'company-account',
                    'business' => $company->BusinessId,
                    'balance' => $company->Balance,
                    'account' => $account,
                    'name' => $company->TradeName,
                    'institution' => 90646,
                    'id' => $company->Id,
                    'commissions' => $companyCommissions,
                    'stpAccount' => [
                        'id' => $stpAccount->Id,
                        'acronym' => $stpAccount->Acronym,
                        'number' => Crypt::decrypt($stpAccount->Number),
                    ]
                ];
            }
        }
        return null;
    }

    public static function searchByCardCloudCompanyAccount($account)
    {
        $company = CompanySpeiAccount::join('t_backoffice_companies_projection', 't_backoffice_companies_projection.Id', '=', 't_backoffice_companies_spei_accounts.CompanyId')
            ->where('t_backoffice_companies_spei_accounts.Clabe', $account)
            ->select(
                't_backoffice_companies_projection.BusinessId',
                't_backoffice_companies_projection.TradeName',
                't_backoffice_companies_projection.Id',
                't_backoffice_companies_spei_accounts.CompanyId'
            )
            ->first();
        if ($company) {
            return [
                'type' => 'company-card-cloud-account',
                'business' => $company->BusinessId,
                'balance' => 0,
                'account' => $account,
                'name' => $company->TradeName,
                'institution' => 90646,
                'id' => $company->Id,
                'companyId' => $company->CompanyId

            ];
        }
        return null;
    }

    public static function searchByCardCloudAccount($account)
    {
        $card = CardSpeiAccount::leftJoin('t_stp_card_cloud_users', 't_stp_card_cloud_users.CardCloudId', '=', 't_card_cloud_spei_accounts.CardId')
            ->where('t_card_cloud_spei_accounts.Clabe', $account)
            ->select(
                't_stp_card_cloud_users.BusinessId',
                't_card_cloud_spei_accounts.CardId',
                't_stp_card_cloud_users.Name',
                't_stp_card_cloud_users.Lastname'
            )
            ->first();
        if ($card) {
            $stpAccount = StpAccounts::where('Id', env('CARD_CLOUD_MAIN_STP_ACCOUNT_ID', '7882dcd5-2ce8-4dd7-a1a8-42d5e8a2434a'))->first();


            return [
                'type' => 'card-cloud-account',
                'business' => $card->BusinessId ?? null,
                'balance' => 0,
                'account' => $account,
                'name' => $card->Name . ' ' . $card->Lastname,
                'institution' => 90646,
                'id' => $card->CardId,
                'stpAccount' => [
                    'id' => $stpAccount->Id,
                    'acronym' => $stpAccount->Acronym,
                    'number' => Crypt::decrypt($stpAccount->Number),
                ]
            ];
        }
    }

    public static function searchByExternalAccount($account)
    {
        $destination = ExternalAccount::join('cat_spei_banks', 'cat_spei_banks.Id', '=', 't_spei_external_accounts.BankId')
            ->where('t_spei_external_accounts.InterbankCLABE', $account)
            ->select(
                'InterbankCLABE',
                'Beneficiary',
                'Email',
                'cat_spei_banks.Code'
            )
            ->first();
        if (!is_null($destination)) {
            return [
                'type' => 'external-account',
                'business' => 0,
                'balance' => 0,
                'account' => $account,
                'name' => $destination->Beneficiary,
                'institution' => $destination->Code
            ];
        }

        return null;
    }

    public static function updateAccountBalance($account_id)
    {
        $stpAccount = StpAccounts::where('Id', $account_id)->first();

        $balance = StpService::getBalance(
            Crypt::decrypt($stpAccount->Url),
            Crypt::decrypt($stpAccount->Key),
            $stpAccount->Company,
            Crypt::decrypt($stpAccount->Number)
        );

        if (isset($balance->respuesta->saldo)) {
            $stpAccount->Balance = $balance->respuesta->saldo;
            $stpAccount->PendingCharges = $balance->respuesta->cargosPendientes;
            $stpAccount->BalanceDate = date('Y-m-d H:i:s');
            $stpAccount->save();

            return $stpAccount;
        } else {
            throw new \Exception('No se ha podido obtener el saldo de la cuenta concentradora. Por favor intente más tarde.');
        }
    }
}
