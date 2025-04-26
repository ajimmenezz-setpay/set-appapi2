<?php

namespace App\Http\Controllers\SpeiCloud\Authorization;

use App\Http\Controllers\Controller;
use App\Http\Middleware\ValidateEnvironmentAdminProfile;
use Illuminate\Http\Request;
use App\Models\Backoffice\Business;
use App\Models\Speicloud\Authorization\AuthorizingUsers as AuthorizationAuthorizingUsers;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class AuthorizingUsers extends Controller
{
    public function __construct()
    {
        $this->middleware(ValidateEnvironmentAdminProfile::class);
    }

    public function index(Request $request)
    {
        try {
            $business = Business::where('Id', $request->attributes->get('jwt')->businessId)->first();
            if (!$business) {
                return $this->basicError('El ambiente no existe o no tienes acceso a él', 404);
            }

            return $this->success([
                'authorizers' => self::authorizers($business->Id),
                'users' => self::users($business->Id),
            ]);
        } catch (\Exception $e) {
            var_dump($e->getMessage());
            return $this->basicError('No se pudo obtener la información de los usuarios autorizadores');
        }
    }

    public static function users($businessId)
    {
        return User::join('cat_profile', 'cat_profile.Id', '=', 't_users.ProfileId')
            ->where('t_users.BusinessId', $businessId)
            ->where('t_users.Active', 1)
            ->whereIn('t_users.ProfileId', [5, 7])
            ->get([
                't_users.Id',
                't_users.Name',
                't_users.Lastname',
                't_users.Email',
                'cat_profile.Name as ProfileName',
            ]);
    }

    public static function authorizers($businessId)
    {
        return User::join('t_backoffice_speicloud_authorizing_users', 't_backoffice_speicloud_authorizing_users.UserId', '=', 't_users.Id')
            ->join('t_users as t2', 't2.Id', '=', 't_backoffice_speicloud_authorizing_users.CreatedBy')
            ->join('cat_profile', 'cat_profile.Id', '=', 't_users.ProfileId')
            ->where('t_backoffice_speicloud_authorizing_users.BusinessId', $businessId)
            ->where('t_users.Active', 1)
            ->where('t_backoffice_speicloud_authorizing_users.Active', 1)
            ->whereIn('t_users.ProfileId', [5, 7])
            ->get([
                't_users.Id',
                't_users.Name',
                't_users.Lastname',
                't_users.Email',
                'cat_profile.Name as ProfileName',
                't2.Name as CreatedBy',
            ]);
    }


    public function store(Request $request)
    {
        try {

            $this->validate(
                $request,
                [
                    'user_id' => 'required|string',
                ],
                [
                    'user_id.required' => 'El campo user_id es obligatorio',
                    'user_id.string' => 'El campo user_id debe ser una cadena de texto',
                ]
            );

            DB::beginTransaction();

            $business = Business::where('Id', $request->attributes->get('jwt')->businessId)->first();
            if (!$business) {
                return $this->basicError('El ambiente no existe o no tienes acceso a él', 404);
            }

            $user = User::where('Id', $request->input('user_id'))->first();
            if (!$user) {
                return $this->basicError('El usuario no existe o no tienes acceso a él', 404);
            }

            $userExists = AuthorizationAuthorizingUsers::where('BusinessId', $business->Id)
                ->where('UserId', $user->Id)
                ->first();
            if ($userExists && $userExists->Active == 1) {
                return $this->basicError('El usuario ya existe como autorizador', 400);
            } else if ($userExists && $userExists->Active == 0) {
                $userExists->Active = 1;
                $userExists->save();
                return $this->success([
                    'authorizers' => self::authorizers($business->Id),
                    'users' => self::users($business->Id),
                ]);
            }

            AuthorizationAuthorizingUsers::create([
                'BusinessId' => $business->Id,
                'UserId' => $user->Id,
                'CreatedBy' => $request->attributes->get('jwt')->id,
            ]);

            DB::commit();
            return $this->success([
                'authorizers' => self::authorizers($business->Id),
                'users' => self::users($business->Id),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->basicError($e->getMessage());
        }
    }


    public function delete(Request $request)
    {
        try {
            $this->validate(
                $request,
                [
                    'user_id' => 'required|string',
                ],
                [
                    'user_id.required' => 'El campo user_id es obligatorio',
                    'user_id.string' => 'El campo user_id debe ser una cadena de texto',
                ]
            );

            DB::beginTransaction();

            $business = Business::where('Id', $request->attributes->get('jwt')->businessId)->first();
            if (!$business) {
                return $this->basicError('El ambiente no existe o no tienes acceso a él', 404);
            }

            $user = User::where('Id', $request->input('user_id'))->first();
            if (!$user) {
                return $this->basicError('El usuario no existe o no tienes acceso a él', 404);
            }

            $userExists = AuthorizationAuthorizingUsers::where('BusinessId', $business->Id)
                ->where('UserId', $user->Id)
                ->first();
            if (!$userExists) {
                return $this->basicError('El usuario no existe como autorizador', 400);
            }

            $userExists->Active = 0;
            $userExists->save();

            DB::commit();
            return $this->success([
                'authorizers' => self::authorizers($business->Id),
                'users' => self::users($business->Id),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->basicError('No se pudo eliminar el usuario autorizador');
        }
    }
}
