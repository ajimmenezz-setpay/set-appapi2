<?php

namespace App\Http\Controllers\Backoffice;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Backoffice\Company as CompanyModel;
use App\Models\Backoffice\Companies\CompanyProjection;

class Company extends Controller
{

    public static function getProjection($companyId)
    {
        return CompanyProjection::where('Id', $companyId)->first();
    }

    public static function getCompany($companyId)
    {
        return CompanyModel::where('Id', $companyId)->first();
    }

    public static function getCompanyIdByAccount($account)
    {
        return DB::table('t_backoffice_companies_services')
            ->join('t_backoffice_companies_service_stp', 't_backoffice_companies_services.Id', '=', 't_backoffice_companies_service_stp.Id')
            ->select('CompanyId')
            ->where('t_backoffice_companies_service_stp.BankAccountNumber', $account)
            ->first();
    }
}
