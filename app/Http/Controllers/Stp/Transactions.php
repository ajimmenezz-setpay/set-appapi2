<?php

namespace App\Http\Controllers\Stp;

use App\Http\Controllers\Backoffice\Company;
use App\Http\Controllers\Controller;
use App\Models\Backoffice\Companies\CompanyProjection;
use Illuminate\Http\Request;
use App\Models\Speicloud\StpTransaction;
use App\Models\Backoffice\Company as CompanyModel;


class Transactions extends Controller
{
    public function fixBalanceTransaction(Request $request)
    {
        self::validate($request, [
            'account' => 'required',
            'parent_account' => 'required'
        ]);

        if (self::hasActiveTransaction($request->account)) {
            return self::error('There is an active transaction for this account.');
        }

        $query = StpTransaction::where(function ($query) use ($request) {
            $query->where(function ($query) use ($request) {
                $query->where('SourceAccount', $request->account)
                    ->where('TypeId', '1');
            })->orWhere(function ($query) use ($request) {
                $query->where('DestinationAccount', $request->account)
                    ->where('TypeId', '2');
            })->orWhere(function ($query) use ($request) {
                $query->where('DestinationAccount', $request->account)
                    ->where('TypeId', '1')
                    ->where('SourceAccount', $request->parent_account);
            });
        })->whereIn("StatusId", [1, 3])
            ->orderBy('LiquidationDate', 'asc');

        // self::printQuery($query);

        $transactions = $query->get();

        if (count($transactions) == 0) {
            return self::error('There are no transactions for this account.');
        }

        if ($transactions[0]->TypeId == 1 && $transactions[0]->SourceAccount <> $request->parent_account) {
            return self::error('The first transaction is an output transaction.');
        }

        $balance = 0;

        foreach ($transactions as $transaction) {
            $comissions = json_decode($transaction->Commissions);
            $totalMovement = $comissions->total;
            if($transaction->TypeId == 1){
                if($transaction->SourceAccount == $request->parent_account){
                    $balance += $totalMovement;
                }else{
                    $balance -= $totalMovement;
                }
            }else{
                $balance += $totalMovement;
            }

            // if ($transaction->TypeId == 1) {
            //     StpTransaction::where('Id', $transaction->Id)
            //         ->update([
            //             'SourceBalance' => $balance,
            //             'DestinationBalance' => $transaction->Amount
            //         ]);
            // } else {
            //     StpTransaction::where('Id', $transaction->Id)
            //         ->update([
            //             'DestinationBalance' => $balance
            //         ]);
            // }

            echo "Type=$transaction->TypeId Amount=$totalMovement Balance=$balance\n";
        }

        // $companyId = Company::getCompanyIdByAccount($request->account);

        // if ($companyId) {
        //     CompanyModel::where('Id', $companyId->CompanyId)
        //         ->update([
        //             'Balance' => $balance
        //         ]);
        //     CompanyProjection::where('CompanyId', $companyId->CompanyId)
        //         ->update([
        //             'Balance' => $balance
        //         ]);
        // }
    }

    private function hasActiveTransaction($account)
    {
        $activeTransaction = StpTransaction::where('SourceAccount', $account)
            ->where('Active', '1')
            ->first();

        if ($activeTransaction) {
            return true;
        }

        return false;
    }
}
