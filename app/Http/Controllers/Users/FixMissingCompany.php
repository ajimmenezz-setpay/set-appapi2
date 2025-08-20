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
                'profileId' => $user->ProfileId,
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
}
