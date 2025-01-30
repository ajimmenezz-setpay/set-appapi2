<?php

namespace App\Http\Controllers\Stp\Transactions;

use App\Http\Controllers\Controller;
use App\Models\Backoffice\Companies\Company;
use App\Models\Backoffice\Companies\CompanyProjection;
use App\Models\Speicloud\StpTransaction;

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
        return StpTransaction::whereIn('StatusId', [3,1])
            ->where(function ($query) use ($bankAccountNumber) {
                $query->where('SourceAccount', $bankAccountNumber)
                    ->orWhere('DestinationAccount', $bankAccountNumber);
            })->orderBy('CreateDate', 'asc')->get();
    }
}
