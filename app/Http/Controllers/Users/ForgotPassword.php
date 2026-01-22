<?php

namespace App\Http\Controllers\Users;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Security\Password;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Mail\VerificationAccountCode;
use App\Models\Users\UsersCode;
use Illuminate\Support\Facades\Log;
use App\Models\Users\FirebaseToken;
use App\Models\Notifications\Push as PushModel;


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
            if (!$user) return self::basicError('Ya se ha enviado un código de verificación recientemente, por favor intente en 15 minutos.');

            $cutoff = Carbon::now('America/Mexico_City')->subMinutes(15);

            $lastCode = UsersCode::where('UserId', $user->Id)->where('Register', '>=', $cutoff)->first();
            if ($lastCode) {
                return self::basicError('Ya se ha enviado un código de verificación recientemente, por favor intente en 15 minutos.');
            }

            $code = random_int(100000, 999999);
            DB::table('t_users_codes')->where('UserId', $user->Id)->delete();

            DB::table('t_users_codes')->insert([
                'UserId' => $user->Id,
                'Code' => $code,
                'Register' => Carbon::now('America/Mexico_City')->format('Y-m-d H:i:s')
            ]);

            self::setSMTP($user->BusinessId);

            Mail::to($request->email)->send(new VerificationAccountCode($code));

            return self::success([
                'message' => 'Se ha enviado un correo electrónico con el código de verificación'
            ]);
        } catch (\Exception $e) {
            return self::basicError($e->getMessage());
        } finally {
            if ($user && $code) {
                $bundleContext = DB::table('t_backoffice_business_bundle_context')->where('BusinessId', $user->BusinessId)->value('BundleContext');
                if ($bundleContext) {
                    $bundle = $bundleContext;
                } else {
                    $bundle = 'com.set.transaccionales';
                }

                $firebaseToken = FirebaseToken::where('UserId', $user->Id)->first();
                if ($firebaseToken) {
                    PushModel::create([
                        'UserId' => $user->Id,
                        'Token' => $firebaseToken->FirebaseToken,
                        'CardCloudId' => null,
                        'BundleContext' => $bundle,
                        'Title' => "Código de verificación",
                        'Body' => "Tu código de verificación es: $code",
                        'Type' => "PASSWORD_CHANGE",
                        'Description' => "Se ha enviado el código $code para restablecer tu contraseña. Si no solicitaste este cambio, por favor contácta a soporte.",
                        'IsSent' => false,
                        'IsFailed' => false,
                    ]);
                }
            }
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
     *              @OA\Property(property="code", type="integer", example=123456),
     *              @OA\Property(property="password", type="string", example="Password123!"),
     *              @OA\Property(property="password_confirmation", type="string", example="Password123!")
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
                'code' => 'required|numeric',
                'password' => [
                    'required',
                    'min:8',
                    'confirmed',
                    'regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).+$/'
                ]
            ], [
                'email.required' => 'El email es requerido',
                'email.email' => 'El email no es válido',
                'code.required' => 'El código de verificación es requerido',
                'code.numeric' => 'El código de verificación no es válido',
                'password.required' => 'La contraseña es requerida',
                'password.min' => 'La contraseña debe tener al menos 8 caracteres',
                'password.confirmed' => 'Las contraseñas no coinciden',
                'password.regex' => 'La contraseña debe contener al menos una letra mayúscula, una letra minúscula y un número'
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

            DB::beginTransaction();

            User::where('Id', $user->Id)->update(['Password' => Password::hashPassword($request->password)]);
            UsersCode::where('UserId', $user->Id)->delete();

            DB::commit();

            return response()->json(['message' => 'La contraseña ha sido actualizada con éxito']);
        } catch (\Exception $e) {

            DB::rollBack();
            Log::error('Error al actualizar la contraseña: ', [
                'email' => $request->email,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'code' => $e->getCode()
            ]);
            return response()->json(['message' => 'Error al actualizar la contraseña, ' . $e->getMessage()], 500);
        } finally {
            $bundleContext = DB::table('t_backoffice_business_bundle_context')->where('BusinessId', $user->BusinessId)->value('BundleContext');
            if ($bundleContext) {
                $bundle = $bundleContext;
            } else {
                $bundle = 'com.set.transaccionales';
            }

            $firebaseToken = FirebaseToken::where('UserId', $user->Id)->first();
            if ($firebaseToken) {
                PushModel::create([
                    'UserId' => $user->Id,
                    'Token' => $firebaseToken->FirebaseToken,
                    'CardCloudId' => null,
                    'BundleContext' => $bundle,
                    'Title' => "Cambio de contraseña",
                    'Body' => "Tu contraseña ha sido cambiada exitosamente.",
                    'Type' => "PASSWORD_CHANGE",
                    'Description' => "Su contraseña ha sido restablecida correctamente. Si no realizaste este cambio, por favor contácta a soporte.",
                    'IsSent' => false,
                    'IsFailed' => false,
                ]);
            }
        }
    }
}
