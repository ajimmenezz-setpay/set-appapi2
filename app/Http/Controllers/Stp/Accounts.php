<?php

namespace App\Http\Controllers\Stp;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Security\Crypt;
use Illuminate\Http\Request;
use App\Models\Speicloud\StpAccounts as StpAccountsModel;

class Accounts extends Controller
{
    public static function searchByNumber($account)
    {
        $accounts = StpAccountsModel::get();

        foreach ($accounts as $acc) {
            if (Crypt::decrypt($acc->Number) == $account) {
                return $acc;
            }
        }

        return null;
    }

    public static function searchById($id)
    {
        return StpAccountsModel::where('Id', $id)->first();
    }
}
