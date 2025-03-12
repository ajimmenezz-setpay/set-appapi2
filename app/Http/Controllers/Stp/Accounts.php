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

    public static function getParentAccount($account_number){
        $accounts = StpAccountsModel::get();
        $better_coincidence = null;
        $max_length_coincidence = 0;

        foreach ($accounts as $acc) {
            $common_prefix = self::getCommonPrefix($account_number, Crypt::decrypt($acc->Number));

            if ($common_prefix > $max_length_coincidence) {
                $max_length_coincidence = $common_prefix;
                $better_coincidence = $acc;
            }
        }
    }

    public static function getCommonPrefix($subaccount, $account){
        $length = min(strlen($subaccount), strlen($account));
        $count = 0;

        for ($i = 0; $i < $length; $i++) {
            if ($subaccount[$i] == $account[$i]) {
                $count++;
            } else {
                break;
            }
        }

        return $count;
    }
}
