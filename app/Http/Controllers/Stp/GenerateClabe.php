<?php

namespace App\Http\Controllers\Stp;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Security\Crypt;
use App\Models\Speicloud\StpAccounts;
use App\Models\Speicloud\StpClabe;
use Illuminate\Http\Request;

class GenerateClabe extends Controller
{
    public function generate(Request $request)
    {
        $requestedClabes = $request->total ?? 100;

        $accounts = StpAccounts::where('Active', 1)->get();
        foreach ($accounts as $account) {
            $lastAccount = StpClabe::where('BusinessId', $account->BusinessId)->orderBy('Id', 'desc')->first();
            $account = substr($lastAccount->Number, 0, 17);
            echo 'Account: ' . $account . PHP_EOL;
            for ($i = 0; $i < $requestedClabes; $i++) {
                $account++;
                $clabe = $account . $this->verificatorDigit((string) $account);
                echo $clabe . PHP_EOL;
                StpClabe::create([
                    'BusinessId' => $lastAccount->BusinessId,
                    'Number' => $clabe,
                    'Available' => 1
                ]);
            }
        }
    }

    public function verificatorDigit(string $clabe)
    {
        $weights = [3, 7, 1];
        $sum = 0;

        for ($i = 0; $i < strlen($clabe); $i++) {
            $digit = (int) $clabe[$i];
            $sum += $digit * $weights[$i % 3];
        }

        return (10 - ($sum % 10)) % 10;
    }
}
