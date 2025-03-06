<?php

namespace App\Http\Controllers\Administration;

use App\Http\Controllers\Controller;
use App\Models\Backoffice\Companies\CompanyProjection;
use Illuminate\Http\Request;

class Company extends Controller
{
    public function index(Request $request)
    {
        try {
            switch ($request->attributes->get('jwt')->profileId) {
                case 5:
                    $companies = CompanyProjection::where('BusinessId', $request->attributes->get('jwt')->businessId)->get();
                    break;
                case 7:
                    $companies = CompanyProjection::join('t_backoffice_companies_and_users', 't_backoffice_companies_and_users.CompanyId', '=', 't_backoffice_companies_projection.Id')
                        ->where('t_backoffice_companies_and_users.UserId', $request->attributes->get('jwt')->id)
                        ->get();
                    break;
                default:
                    return self::basicError("No tienes permisos para ver las empresas");
            }

            $companies = $companies->map(function ($company) {
                return self::companyObject($company);
            });
            return response()->json($companies);
        } catch (\Exception $e) {
            var_dump($e->getMessage());
            return self::basicError("Error al obtener las empresas");
        }
    }

    public function show(Request $request, $id)
    {
        try {
            switch ($request->attributes->get('jwt')->profileId) {
                case 5:
                    $company = CompanyProjection::where('BusinessId', $request->attributes->get('jwt')->businessId)->where('Id', $id)->first();
                    break;
                case 7:
                    $company = CompanyProjection::join('t_backoffice_companies_and_users', 't_backoffice_companies_and_users.CompanyId', '=', 't_backoffice_companies_projection.Id')
                        ->where('t_backoffice_companies_and_users.UserId', $request->attributes->get('jwt')->id)
                        ->where('t_backoffice_companies_projection.Id', $id)
                        ->first();
                    break;
                default:
                    return self::basicError("No tienes permisos para ver la empresa solicitada");
            }

            if (!$company) {
                return self::basicError("No tienes permisos para ver la empresa solicitada");
            }
            return response()->json(self::companyObject($company));
        } catch (\Exception $e) {
            return self::basicError("Error al obtener la empresa");
        }
    }

    public static function companyObject($company)
    {
        $stpService = self::stpService($company);
        $stpCommission = self::stpCommission($company);
        $cardCloudService = self::cardCloudService($company);

        return [
            "id" => $company->Id,
            "businessId" => $company->BusinessId,
            "folio" => $company->Folio,
            "fatherId" => $company->CompanyId,
            "legalRepresentative" => "",
            "legalRepresentativeName" => "",
            "legalRepresentativeEmail" => "",
            "legalRepresentativePhone" => "",
            "legalRepresentativeRegister" => "",
            "legalRepresentativeLastSession" => "",
            "fiscalPersonType" => $company->FiscalPersonType,
            "fiscalName" => $company->FiscalName,
            "tradeName" => $company->TradeName,
            "rfc" => $company->Rfc,
            "postalAddress" => "",
            "phoneNumbers" => "",
            "logo" => "",
            "slug" => "",
            "balance" => $company->Balance,
            "bankAccount" => $stpService->bankAccountNumber ?? "",
            "bankAccounts" => [$stpService->bankAccountNumber ?? ""],
            "publicTerminal" => "",
            "employees" => 0,
            "branchOffices" => 0,
            "pointSaleTerminal" => 0,
            "paymentApi" => 0,
            "type" => $company->Type,
            "typeName" => $company->TypeName,
            "allowTransactions" => "",
            "statusId" => $company->StatusId,
            "statusName" => $company->StatusName,
            "stpAccountId" => $stpService->stpAccountId ?? "",
            "registerStep" => $company->RegisterStep,
            "users" => self::users($company),
            "services" => self::companyServices($company),
            "servicesIds" => [],
            "costCenters" => [],
            "documents" => [],
            "commissions" => [
                "speiOut" => $stpCommission->speiOut ?? 0,
                "speiIn" => $stpCommission->speiIn ?? 0,
                "internal" => $stpCommission->internal ?? 0,
                "feeStp" => $stpCommission->feeStp ?? 0,
                "stpAccount" => $stpCommission->stpAccount ?? 0
            ],
            "speiCommissions" => [
                "speiOut" => $stpCommission->speiOut ?? 0,
                "speiIn" => $stpCommission->speiIn ?? 0,
                "internal" => $stpCommission->internal ?? 0,
                "feeStp" => $stpCommission->feeStp ?? 0,
                "stpAccount" => $stpCommission->stpAccount ?? 0
            ],
            "createdByUser" => $company->CreatedByUser,
            "register" => $company->CreateDate,
            "active" => (string)$company->Active
        ];
    }

    public static function stpCommission($company)
    {
        $commissions = json_decode($company->Commissions);
        $stpCommission = collect($commissions)->filter(function ($commission) {
            return $commission->type == 2;
        })->first();
        return $stpCommission;
    }

    public static function stpService($company)
    {
        $services = json_decode($company->Services);
        $stpService = collect($services)->filter(function ($service) {
            return $service->type == 4;
        })->first();
        return $stpService;
    }

    public static function cardCloudService($company)
    {
        $services = json_decode($company->Services);
        $cardCloudService = collect($services)->filter(function ($service) {
            return $service->type == 5;
        })->first();
        return $cardCloudService;
    }

    public static function users($company)
    {
        $users = json_decode($company->Users);
        $users = array_map(function ($user) {
            $user->profileId = $user->profile;
            return $user;
        }, $users);
        return $users;
    }

    public static function companyServices($company)
    {
        $services = json_decode($company->Services);
        return $services;
    }
}

// {
//     "id":"017bd513-008c-470f-bc0f-07a4460ff354",
//     "businessId":"1015093b-5d33-404a-a4a4-2c0cff648dda",
//     "folio":"1238",
//     "fatherId":"",
//     "legalRepresentative":"be4cea5b-563b-4c0f-968f-cee3e23fb585",
//     "legalRepresentativeName":"Constructora y Remodelación",
//     "legalRepresentativeEmail":"lalaguna.admon@yahoo.com",
//     "legalRepresentativePhone":"",
//     "legalRepresentativeRegister":"",
//     "legalRepresentativeLastSession":"",
//     "fiscalPersonType":"1",
//     "fiscalName":"CONSTRUCCION Y REMODELACION LA LAGUNA Suc 2",
//     "tradeName":"CONSTRUCCION Y REMODELACION LA LAGUNA Suc 2",
//     "rfc":"CYRLL1234562",
//     "postalAddress":"",
//     "phoneNumbers":"",
//     "logo":"",
//     "slug":"",
//     "balance":0,
//     "bankAccount":"646180300800000404",
//     "bankAccounts":[
//        "646180300800000404"
//     ],
//     "publicTerminal":"",
//     "employees":"0",
//     "branchOffices":"0",
//     "pointSaleTerminal":"0",
//     "paymentApi":"0",
//     "type":"1",
//     "typeName":"Formal",
//     "allowTransactions":"",
//     "statusId":"3",
//     "statusName":"Afiliado",
//     "stpAccountId":"7882dcd5-2ce8-4dd7-a1a8-42d5e8a2434a",
//     "registerStep":"4",
//     "users":[
//        {
//           "id":"be4cea5b-563b-4c0f-968f-cee3e23fb585",
//           "companyId":"017bd513-008c-470f-bc0f-07a4460ff354",
//           "profile":"7",
//           "name":"Constructora y Remodelación",
//           "lastname":"La Laguna",
//           "email":"lalaguna.admon@yahoo.com",
//           "createDate":"2024-10-10 20:15:37",
//           "profileId":"7"
//        }
//     ],
//     "services":[
//        {
//           "id":"b1bf3929-13b5-4726-9511-212268f69b93",
//           "type":"4",
//           "companyId":"017bd513-008c-470f-bc0f-07a4460ff354",
//           "stpAccountId":"7882dcd5-2ce8-4dd7-a1a8-42d5e8a2434a",
//           "bankAccountId":"10040",
//           "bankAccountNumber":"646180300800000404",
//           "active":"1",
//           "cardNumbers":"0",
//           "cardUse":"0"
//        },
//        {
//           "id":"9cf1d934-813e-4fce-a4b9-066373e75cfd",
//           "type":"5",
//           "companyId":"017bd513-008c-470f-bc0f-07a4460ff354",
//           "subAccountId":"0192911b-3208-72f6-ab3e-47973fc0ada0",
//           "subAccount":"{\"subaccount_id\":\"0192911b-3208-72f6-ab3e-47973fc0ada0\",\"external_id\":\"017bd513-008c-470f-bc0f-07a4460ff354\",\"description\":\"CYRLL1234562\"}",
//           "active":"1",
//           "cardNumbers":"0",
//           "cardUse":"0",
//           "stpAccountId":""
//        }
//     ],
//     "servicesIds":[

//     ],
//     "costCenters":[

//     ],
//     "documents":[

//     ],
//     "commissions":{
//        "speiOut":7,
//        "speiIn":7,
//        "internal":0,
//        "feeStp":0,
//        "stpAccount":0
//     },
//     "speiCommissions":{
//        "speiOut":7,
//        "speiIn":7,
//        "internal":0,
//        "feeStp":0,
//        "stpAccount":0
//     },
//     "createdByUser":"23162514-5cd7-4ed2-8cde-8cc5ac881033",
//     "register":"2024-10-15 10:55:30",
//     "active":"1"
//  }
