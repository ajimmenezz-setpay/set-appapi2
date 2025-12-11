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
            $admins = User::whereIn('ProfileId', [7, 13])
            ->where('BusinessId', $request->attributes->get('jwt')->businessId)
            ->where('Active', 1)
            ->select('Id', 'Name', 'Lastname', 'Email')
            ->get();

            $adminsArray = [];
            foreach ($admins as $admin) {
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
