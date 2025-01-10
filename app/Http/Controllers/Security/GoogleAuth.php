<?php

namespace App\Http\Controllers\Security;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use OTPHP\TOTP;

class GoogleAuth extends Controller
{
    /**
     * @OA\Post(
     *      path="/api/security/2fa/authorize",
     *      tags={"Security"},
     *      summary="Authorize two factor authentication",
     *      description="Authorize two factor authentication",
     *      security={{"bearerAuth":{}}},
     *
     *      @OA\RequestBody(
     *          required=true,
     *          @OA\JsonContent(
     *              required={"code"},
     *              @OA\Property(property="code", type="string", example="123456")
     *          )
     *      ),
     *
     *      @OA\Response(
     *          response=200,
     *          description="Authorization successful",
     *          @OA\JsonContent(
     *              @OA\Property(property="message", type="string", example="Autorizado")
     *          )
     *      ),
     *
     *      @OA\Response(
     *          response=400,
     *          description="Error authorizing",
     *          @OA\MediaType(mediaType="text/plain", @OA\Schema(type="string", example="Error authorizing"))
     *      )
     * )
     */

    public function authorize(Request $request)
    {
        try {
            $this->validate($request, [
                'code' => 'required|min:6|max:6'
            ], [
                'code.required' => 'El código de autenticación es requerido.',
                'code.min' => 'El código de autenticación debe tener 6 caracteres.',
                'code.max' => 'El código de autenticación debe tener 6 caracteres.'
            ]);

            self::authorized($request->attributes->get('jwt')->id, $request->code);

            return $this->success(['message' => 'Autorizado']);
        } catch (\Exception $e) {
            return $this->basicError($e->getMessage());
        }
    }

    public static function validateToken($secretKey, $code)
    {
        $secretKey = Crypt::decrypt($secretKey);

        $totp = TOTP::create($secretKey);
        return $totp->verify($code);
    }

    public static function authorized($userId, $code)
    {
        $user = User::leftJoin('t_security_authenticator_factors', 't_security_authenticator_factors.UserId', '=', 't_users.Id')
            ->where('t_users.Id', $userId)
            ->select('t_security_authenticator_factors.SecretKey')
            ->first();

        if (!$user || !$user->SecretKey) {
            throw new \Exception('No se ha configurado la autenticación de dos factores.');
        }

        if (!self::validateToken($user->SecretKey, $code)) {
            throw new \Exception('El código de autenticación es incorrecto.');
        }
    }
}
