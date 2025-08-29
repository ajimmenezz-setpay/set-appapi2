<?php

namespace App\Http\Controllers\Users;

use App\Http\Controllers\Controller;
use App\Models\Backoffice\Companies\CompanyProjection;
use App\Models\Backoffice\Users\CompaniesAndUsers;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FixMissingCompany extends Controller
{
    public function fix(Request $request)
    {
        try {
            $request->validate(
                [
                    'email' => 'required|email|exists:t_users,Email',
                    'company' => 'required|string|exists:t_backoffice_companies,TradeName',
                ],
                [
                    'email.required' => 'Email is required.',
                    'email.email' => 'Invalid email format.',
                    'email.exists' => 'Email does not exist in the users records.',
                    'company.required' => 'Company is required.',
                    'company.string' => 'Company must be a string.',
                    'company.exists' => 'Company does not exist in the companies records.',
                ]
            );

            $user = \App\Models\User::where('Email', $request->email)->first();
            $company = \App\Models\Backoffice\Companies\Company::where('TradeName', $request->company)->first();

            if (!$user || !$company) {
                throw new Exception('User or company not found.');
            }

            DB::beginTransaction();

            $companyUser = CompaniesAndUsers::where('UserId', $user->Id)
                ->where('CompanyId', $company->Id)
                ->first();
            if ($companyUser) {
                CompaniesAndUsers::where('UserId', $user->Id)
                    ->where('CompanyId', $company->Id)
                    ->update([
                        'ProfileId' => $user->ProfileId,
                        'Name' => $user->Name,
                        'Lastname' => $user->Lastname,
                        'Email' => $user->Email,
                        'CreateDate' => $user->Register
                    ]);
            } else {
                CompaniesAndUsers::create([
                    'CompanyId' => $company->Id,
                    'UserId' => $user->Id,
                    'ProfileId' => $user->ProfileId,
                    'Name' => $user->Name,
                    'Lastname' => $user->Lastname,
                    'Email' => $user->Email,
                    'CreateDate' => $user->Register
                ]);
            }

            $dataArray = json_encode([
                'id' => $user->Id,
                'companyId' => $company->Id,
                'profile' => $user->ProfileId,
                'name' => $user->Name,
                'lastname' => $user->Lastname,
                'email' => $user->Email,
                'createDate' => $user->Register
            ]);

            $projection = CompanyProjection::where('Id', $company->Id)->first();
            if ($projection) {
                $users = json_decode($projection->Users, true);
                $users[] = json_decode($dataArray, true);
                CompanyProjection::where('Id', $company->Id)
                    ->update(['Users' => json_encode($users)]);
            }

            DB::commit();

            return response()->json(['message' => 'User company updated successfully.'], 200);
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 400);
        }




        return response()->json(['message' => 'User company updated successfully.'], 200);
    }


    public function fixMissingCompany(Request $request)
    {
        try {
            $users = DB::table('t_stp_card_cloud_users')
                ->join('t_users', 't_stp_card_cloud_users.UserId', '=', 't_users.Id')
                ->leftJoin('t_backoffice_companies_and_users', 't_stp_card_cloud_users.UserId', '=', 't_backoffice_companies_and_users.UserId')
                ->where('t_backoffice_companies_and_users.CompanyId', '=', null)
                ->select('t_users.*', 't_stp_card_cloud_users.CardCloudId')
                ->get();

            $companiesConector = self::conectorCompanies();

            $companiesToFix = [];

            foreach ($users as $user) {
                $subaccountId = DB::connection('card_cloud')->table('cards')->where('UUID', $user->CardCloudId)->select('SubAccountId')->first();
                if ($subaccountId) {
                    $externalId = $companiesConector[$subaccountId->SubAccountId] ?? null;
                    if ($externalId) {
                        DB::table('t_backoffice_companies_and_users')->insert([
                            'CompanyId' => $externalId,
                            'UserId' => $user->Id,
                            'ProfileId' => $user->ProfileId,
                            'Name' => $user->Name,
                            'Lastname' => $user->Lastname,
                            'Email' => $user->Email,
                            'CreateDate' => $user->Register
                        ]);

                        if (!in_array($externalId, $companiesToFix)) {
                            $companiesToFix[] = $externalId;
                        }
                    }
                }
            }

            $companiesToFix = DB::table('t_backoffice_companies')->where('Active', 1)->get();

            foreach ($companiesToFix as $companyId) {
                $projection = CompanyProjection::where('Id', $companyId->Id)->first();
                if ($projection) {
                    $users = json_decode($projection->Users, true);

                    $relatedUsers = DB::table('t_backoffice_companies_and_users')
                        ->where('CompanyId', $companyId->Id)
                        ->get();

                    foreach ($relatedUsers as $relatedUser) {
                        $users[] = [
                            'id' => $relatedUser->UserId,
                            'companyId' => $relatedUser->CompanyId,
                            'profile' => $relatedUser->ProfileId,
                            'name' => $relatedUser->Name,
                            'lastname' => $relatedUser->Lastname,
                            'email' => $relatedUser->Email,
                            'createDate' => $relatedUser->CreateDate
                        ];
                    }


                    CompanyProjection::where('Id', $companyId->Id)
                        ->update(['Users' => json_encode($users)]);
                }
            }

            return response()->json(['users' => $users, 'companiesToFix' => $companiesToFix], 200);
        } catch (Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }


    public static function conectorCompanies()
    {
        $companies = DB::connection('card_cloud')->table('subaccounts')->select('Id', 'ExternalId')->get();
        $arrayReturn = [];
        foreach ($companies as $company) {
            $arrayReturn[$company->Id] = $company->ExternalId;
        }
        return $arrayReturn;
    }
}
