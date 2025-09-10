<?php

namespace App\Http\Controllers\Report\CardCloud;

use App\Http\Controllers\Controller;
use App\Models\Backoffice\Companies\CompanyProjection;
use App\Models\Backoffice\Users\CompaniesAndUsers;
use App\Models\User;

class CompaniesByUser extends Controller
{
    public static function get($jwt)
    {
        if (in_array($jwt->profileId, [7,11])) {
            $companies = CompaniesAndUsers::where('UserId', $jwt->id)->get();
        } else {
            $user = User::where('Id', $jwt->id)->first();
            $companies = CompanyProjection::where('BusinessId', $user->BusinessId)
            ->where('Active', 1)
            ->select('Id as CompanyId')
            ->get();
        }

        return $companies;
    }
}
