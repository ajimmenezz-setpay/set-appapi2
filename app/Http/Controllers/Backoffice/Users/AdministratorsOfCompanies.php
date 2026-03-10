<?php

namespace App\Http\Controllers\Backoffice\Users;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

class AdministratorsOfCompanies extends Controller
{
    public function index(Request $request)
    {
        try {
            $admins = User::whereIn('ProfileId', [7, 11, 13])
                ->where('BusinessId', $request->attributes->get('jwt')->businessId)
                ->where('Active', 1)
                ->select('Id', 'Name', 'Lastname', 'Email')
                ->orderBy('Name')
                ->get();

            $adminsArray = [];
            foreach ($admins as $admin) {
                $adminsArray[] = [
                    'id' => $admin->Id,
                    'name' => $admin->Name . ' ' . $admin->Lastname,
                    'email' => $admin->Email
                ];
            }

            $multiProfileAdmins = User::join('multi_profile_users', 'multi_profile_users.UserId', '=', 't_users.Id')
                ->whereIn('multi_profile_users.ProfileId', [7])
                ->where('t_users.Active', 1)
                ->where('multi_profile_users.IsActive', 1)
                ->select('t_users.Id', 't_users.Name', 't_users.Lastname', 't_users.Email')
                ->orderBy('t_users.Name')
                ->get();
            foreach ($multiProfileAdmins as $admin) {
                $adminsArray[] = [
                    'id' => $admin->Id,
                    'name' => $admin->Name . ' ' . $admin->Lastname,
                    'email' => $admin->Email
                ];
            }

            return response()->json($adminsArray, 200);
        } catch (\Exception $e) {
            return response($e->getMessage() . (env('APP_DEBUG') ? ' en la línea ' . $e->getLine() : ''), $e->getCode() ?: 400);
        }
    }
}
