<?php

namespace App\Http\Controllers\Users;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Mail\VerificationAccountCode;

class ForgotPassword extends Controller
{

    /**
     * @OA\Post(
     *      path="/api/users/forgot-password",
     *      summary="Recuperar contraseña",
     *      tags={"Usuarios - Autenticación"},
     *
     *       @OA\RequestBody(
     *          required=true,
     *          @OA\JsonContent(
     *              required={"email"},
     *             @OA\Property(property="email", type="string", example="  [email protected]")
     *          )
     *      ),
     *
     *      @OA\Response(
     *          response=200,
     *          description="Correo electrónico enviado",
     *          @OA\JsonContent(
     *              @OA\Property(property="message", type="string", example="Se ha enviado un correo electrónico con el código de verificación")
     *          )
     *      ),
     *
     *      @OA\Response(
     *          response=400,
     *          description="Error al validar el email",
     *          @OA\MediaType(mediaType="text/plain", @OA\Schema(type="string", example="El correo electrónico no está registrado"))
     *      )
     * )
     */

    public function forgotPassword(Request $request)
    {
        try {
            $this->validate($request, [
                'email' => 'required|email'
            ]);

            $user = User::where('email', $request->email)->first();
            if (!$user) throw new \Exception('El correo electrónico no está registrado');

            $code = rand(100000, 999999);

            DB::table('t_users_codes')->where('UserId', $user->Id)->delete();
            DB::table('t_users_codes')->insert([
                'UserId' => $user->Id,
                'Code' => $code,
                'Register' => Carbon::now('America/Mexico_City')->format('Y-m-d H:i:s')
            ]);

            Mail::to($request->email)->send(new VerificationAccountCode($code));

            return self::success([
                'message' => 'Se ha enviado un correo electrónico con el código de verificación'
            ]);
        } catch (\Exception $e) {
            return self::basicError($e->getMessage());
        }
    }

    /**
     * @OA\Post(
     *      path="/api/users/reset-password",
     *      summary="Restablecer contraseña",
     *      tags={"Usuarios - Autenticación"},
     *
     *      @OA\RequestBody(
     *          required=true,
     *          @OA\JsonContent(
     *              required={"email", "code"},
     *              @OA\Property(property="email", type="string", example="  [email protected]"),
     *              @OA\Property(property="code", type="integer", example=123456)
     *          )
     *      ),
     *
     *      @OA\Response(
     *          response=200,
     *          description="Correo electrónico enviado",
     *          @OA\JsonContent(
     *              @OA\Property(property="message", type="string", example="Se ha enviado un correo electrónico con las nuevas credenciales")
     *          )
     *      ),
     *
     *      @OA\Response(
     *          response=400,
     *          description="Error al validar el email",
     *          @OA\MediaType(mediaType="text/plain", @OA\Schema(type="string", example="No se ha solicitado un código de verificación | El código de verificación no es válido"))
     *      ),
     *
     *      @OA\Response(
     *          response=404,
     *          description="Error al validar el email",
     *          @OA\MediaType(mediaType="text/plain", @OA\Schema(type="string", example="El email no está registrado"))
     *      )
     * )
     */

    public function resetPassword(Request $request)
    {
        try {
            $this->validate($request, [
                'email' => 'required|email',
                'code' => 'required|numeric'
            ], [
                'email.required' => 'El email es requerido',
                'email.email' => 'El email no es válido',
                'code.required' => 'El código de verificación es requerido',
                'code.numeric' => 'El código de verificación no es válido'
            ]);

            $user = User::where('Email', $request->email)->first();
            if (!$user) {
                throw new \Exception('El email no está registrado', 404);
            }

            $code = DB::table('t_users_codes')->where('UserId', $user->Id)->orderBy('Register', 'desc')->first();
            if (!$code) {
                throw new \Exception('No se ha solicitado un código de verificación', 400);
            }

            if ($code->Code != $request->code) {
                throw new \Exception('El código de verificación no es válido', 400);
            }

            Activate::send_access($user);

            return response()->json(['message' => 'Se ha enviado un correo electrónico con las nuevas credenciales']);
        } catch (\Exception $e) {
            return self::basicError($e->getMessage());
        }
    }
}
