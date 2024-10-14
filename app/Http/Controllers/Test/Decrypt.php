<?php

namespace App\Http\Controllers\Test;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Security\Crypt;
use Illuminate\Http\Request;
use App\Models\Speicloud\StpAccounts;

class Decrypt extends Controller
{
    public function decrypt(Request $request)
    {  
        $businessId = $request->attributes->get('jwt')->businessId;

        $stp_account = StpAccounts::where('BusinessId', $businessId)->first();

        echo Crypt::decrypt($stp_account->Number);


    }
}
