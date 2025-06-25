<?php

namespace App\Http\Controllers\Users;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Security\Password;
use Ramsey\Uuid\Uuid;
use App\Models\User as UserModel;
use App\Models\Backoffice\Users\CompaniesAndUsers;
use Illuminate\Support\Facades\Mail;
use App\Mail\SendUserCredentials;

class User extends Controller
{
    public static function create($dataUser, $companyId = null)
    {
        try {
            $randomPassword = Password::createRandomPassword(12);

            $user = new UserModel();
            $user->Id = Uuid::uuid7();
            $user->ProfileId = 7;
            $user->Name = $dataUser['name'];
            $user->Lastname = $dataUser['lastName'];
            $user->Phone = $dataUser['phone'] ?? "";
            $user->Email = $dataUser['email'];
            $user->Password = Password::hashPassword($randomPassword);
            $user->StpAccountId = "";
            $user->BusinessId = $dataUser['businessId'];
            $user->Register = now();
            $user->Active = 1;
            $user->save();

            if (!is_null($companyId)) {
                $companyUser = new CompaniesAndUsers();
                $companyUser->CompanyId = $companyId;
                $companyUser->UserId = $user->Id;
                $companyUser->ProfileId = $user->ProfileId;
                $companyUser->Name = $user->Name;
                $companyUser->Lastname = $user->Lastname;
                $companyUser->Email = $user->Email;
                $companyUser->CreateDate = now();
                $companyUser->save();
            }

            Mail::to($dataUser['email'])->send(new SendUserCredentials($dataUser['email'], $randomPassword));
        } catch (\Exception $e) {
            throw new \Exception('Error al crear el usuario: ' . $e->getMessage());
        }

        return [
            'user' => $user,
            'object' => [
                'id' => $user->Id,
                'companyId' => $companyId,
                'profile' => $user->ProfileId,
                'name' => $user->Name,
                'lastname' => $user->Lastname,
                'email' => $user->Email,
                'createDate' => $user->CreateDate
            ]
        ];
    }
}
