<?php

namespace App\Http\Controllers\Users;

use App\Http\Controllers\CardCloud\CardManagementController;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Security\Crypt;
use App\Http\Controllers\Security\GoogleAuth as SecurityGoogleAuth;
use App\Http\Controllers\Security\Password;
use App\Mail\VerificationAccountCode;
use App\Models\Backoffice\Companies\CompanyProjection;
use App\Models\Backoffice\Users\CompaniesAndUsers;
use App\Models\CardCloud\CardAssigned;
use App\Models\Security\GoogleAuth;
use Illuminate\Http\Request;
use App\Models\User;
use Carbon\Carbon;
use Ramsey\Uuid\Uuid;
use PragmaRX\Google2FA\Google2FA;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use App\Models\Users\SecretPhrase;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class Activate extends Controller
{
    /**
     * @OA\Post(
     *      path="/api/users/validate",
     *      tags={"Activación de cuenta"},
     *      summary="Validar existencia de cuenta a través del email",
     *      description="Validar existencia de cuenta a través del email",
     *      @OA\RequestBody(
     *          required=true,
     *          @OA\JsonContent(
     *              required={"email"},
     *             @OA\Property(property="email", type="string", example="  [email protected]")
     *          )
     *      ),
     *
     *      @OA\Response(
     *          response=200,
     *          description="Nuevo usuario",
     *          @OA\JsonContent(
     *              @OA\Property(property="user", type="object", example={"id": "123456", "email": "  [email protected]", "name": "", "last_name": "", "phone": ""}),
     *              @OA\Property(property="temporal_code", type="string", example="1234567"),
     *              @OA\Property(property="google_secret", type="string", example="1234567"),
     *              @OA\Property(property="google_qrCode", type="string", example="data:image/svg+xml;base64,1234567"),
     *              @OA\Property(property="google_url", type="string", example="otpauth://totp/Card%20Cloud:[email protected]"),
     *              @OA\Property(property="newUser", type="boolean", example=true),
     *              @OA\Property(property="has_2fa", type="boolean", example=false),
     *              @OA\Property(property="has_secret_phrase", type="boolean", example=false),
     *              @OA\Property(property="has_cards", type="boolean", example=false)
     *          )
     *      ),
     *
     *      @OA\Response(
     *          response=202,
     *          description="Cuenta ya registrada",
     *          @OA\MediaType(mediaType="text/plain", @OA\Schema(type="string", example="Para continuar con la activación de tu cuenta, por favor inicia sesión"))
     *      ),
     *
     *      @OA\Response(
     *          response=400,
     *          description="Error al validar el email",
     *          @OA\MediaType(mediaType="text/plain", @OA\Schema(type="string", example="Error al validar el email"))
     *      )
     *
     *)
     */
    public function validateEmail(Request $request)
    {
        try {
            $this->validate($request, [
                'email' => 'required|email'
            ], [
                'email.required' => 'El email es requerido',
                'email.email' => 'El email no es válido'
            ]);

            $returnArray = [
                'user' => [],
                'temporal_code' => '',
                'google_secret' => '',
                'google_qrCode' => '',
                'newUser' => true,
                'has_2fa' => false,
                'has_secret_phrase' => false,
                'has_cards' => false
            ];

            $continueProcess = false;

            $user = User::where('email', $request->email)->first();
            if ($user && $user->ProfileId != 8) {
                throw new \Exception('Esta cuenta de correo no puede utilizarse para activar una tarjeta, por favor intenta con otra cuenta de correo', 400);
            }

            if ($user && $user->Active == 0 && $user->ProfileId == 8 && $user->Name == 'Usuario' && $user->Lastname == 'Nuevo' && $user->Phone == '0000000000') {
                $continueProcess = true;
                User::where('Id', $user->Id)->delete();
                GoogleAuth::where('UserId', $user->Id)->delete();
            }
            if (!$user || $continueProcess) {
                $user = $this->createTemporalUser($request->email);

                $code = rand(100000, 999999);

                DB::table('t_users_codes')->where('UserId', $user->Id)->delete();
                DB::table('t_users_codes')->insert([
                    'UserId' => $user->Id,
                    'Code' => $code,
                    'Register' => Carbon::now('America/Mexico_City')->format('Y-m-d H:i:s')
                ]);

                Mail::to($request->email)->send(new VerificationAccountCode($code));

                $secret = $this->generateSecret($user);

                $returnArray['user'] = [
                    'id' => $user->Id,
                    'email' => $user->Email,
                    'name' => "",
                    'last_name' => "",
                    'phone' => ""
                ];
                $returnArray['google_secret'] = $secret['secret'];
                $returnArray['google_qrCode'] = $this->getSvgQrCode($secret['qrCodeUrl']);
                $returnArray['google_url'] = "otpauth://totp/Card%20Cloud:" . $user->Email . "?secret=" . $secret['secret'] . "&issuer=Card%20Cloud";
                $returnArray['temporal_code'] = $user->StpAccountId;
                return response()->json($returnArray);
            }
            return response()->json(['message' => 'Para continuar con la activación de tu cuenta, por favor inicia sesión'], 202);
        } catch (\Exception $e) {
            return self::basicError($e->getMessage());
        }
    }

    /**
     * @OA\Post(
     *      path="/api/users/validate-code",
     *      tags={"Activación de cuenta"},
     *      summary="Validar código de verificación",
     *      description="Validar código de verificación",
     *      @OA\RequestBody(
     *          required=true,
     *          @OA\JsonContent(
     *              required={"email", "code"},
     *              @OA\Property(property="email", type="string", example="  [email protected]"),
     *              @OA\Property(property="code", type="string", example="123456")
     *          )
     *      ),
     *
     *      @OA\Response(
     *          response=200,
     *          description="Código de verificación correcto",
     *          @OA\JsonContent(
     *              @OA\Property(property="message", type="string", example="El código de verificación es correcto")
     *         )
     *     ),
     *
     *      @OA\Response(
     *          response=400,
     *          description="Error al validar el código de verificación",
     *          @OA\MediaType(mediaType="text/plain", @OA\Schema(type="string", example="No se ha solicitado un código de verificación | El código de verificación no es válido"))
     *      ),
     *
     *      @OA\Response(
     *          response=404,
     *          description="Email no registrado",
     *          @OA\MediaType(mediaType="text/plain", @OA\Schema(type="string", example="El email no está registrado"))
     *      )
     *
     * )
     */

    public function validateCode(Request $request)
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

            return response()->json(['message' => 'El código de verificación es correcto'], 200);
        } catch (\Exception $e) {
            return self::basicError($e->getMessage());
        }
    }

    /**
     * @OA\Post(
     *      path="/api/users/login",
     *      tags={"Activación de cuenta"},
     *      summary="Validar credenciales de usuario",
     *      description="Validar credenciales de usuario",
     *      @OA\RequestBody(
     *          required=true,
     *          @OA\JsonContent(
     *              required={"email", "password"},
     *              @OA\Property(property="email", type="string", example="  [email protected]"),
     *              @OA\Property(property="password", type="string", example="12345678")
     *          )
     *      ),
     *
     *      @OA\Response(
     *          response=200,
     *          description="Credenciales válidas",
     *          @OA\JsonContent(
     *              @OA\Property(property="user", type="object", example={"id": "123456", "email": "  [email protected]", "name": "", "last_name": "", "phone": ""}),
     *              @OA\Property(property="temporal_code", type="string", example="1234567"),
     *              @OA\Property(property="google_secret", type="string", example="1234567"),
     *              @OA\Property(property="google_qrCode", type="string", example="data:image/svg+xml;base64,1234567"),
     *              @OA\Property(property="google_url", type="string", example="otpauth://totp/Card%20Cloud:[email protected]"),
     *              @OA\Property(property="newUser", type="boolean", example=false),
     *              @OA\Property(property="has_2fa", type="boolean", example=false),
     *              @OA\Property(property="has_secret_phrase", type="boolean", example=false),
     *              @OA\Property(property="has_cards", type="boolean", example=false)
     *          )
     *      ),
     *
     *      @OA\Response(
     *          response=400,
     *          description="Error al validar las credenciales",
     *          @OA\MediaType(mediaType="text/plain", @OA\Schema(type="string", example="Error al validar las credenciales"))
     *      ),
     *
     *      @OA\Response(
     *          response=401,
     *          description="Cuenta no activada",
     *          @OA\MediaType(mediaType="text/plain", @OA\Schema(type="string", example="La contraseña no coincide con la registrada"))
     *      ),
     *
     *      @OA\Response(
     *          response=404,
     *          description="Email no registrado",
     *          @OA\MediaType(mediaType="text/plain", @OA\Schema(type="string", example="El email no está registrado"))
     *     ),
     *
     *     @OA\Response(
     *          response=409,
     *          description="Cuenta no activada",
     *          @OA\MediaType(mediaType="text/plain", @OA\Schema(type="string", example="No se ha completado el registro de la cuenta. Es necesario volver a empezar el proceso de activación"))
     *    )
     * )
     *
     *
     */
    public function validateCredentials(Request $request)
    {
        try {
            $this->validate($request, [
                'email' => 'required|email',
                'password' => 'required'
            ], [
                'email.required' => 'El email es requerido',
                'email.email' => 'El email no es válido',
                'password.required' => 'La contraseña es requerida'
            ]);

            $user = User::where('Email', $request->email)->first();
            if ($user && $user->Active == 0 && $user->ProfileId == 8 && $user->Name == 'Usuario' && $user->Lastname == 'Nuevo' && $user->Phone == '0000000000') {
                throw new \Exception('No se ha completado el registro de la cuenta. Es necesario volver a empezar el proceso de activación', 409);
            }

            if (!$user) {
                throw new \Exception('El email no está registrado', 404);
            }

            if (!Password::verifyUserPassword($user->Id, $request->password)) {
                throw new \Exception('La contraseña no coincide con la registrada', 401);
            }

            $temporalCode = substr(Uuid::uuid7(), -7);

            User::where('Id', $user->Id)->update([
                'StpAccountId' => $temporalCode
            ]);

            $returnArray = [
                'user' => [
                    'id' => $user->Id,
                    'email' => $user->Email,
                    'name' => $user->Name,
                    'last_name' => $user->Lastname,
                    'phone' => $user->Phone ?? ""
                ],
                'temporal_code' => $temporalCode,
                'google_secret' => '',
                'google_qrCode' => '',
                'newUser' => false,
                'has_2fa' => false,
                'has_secret_phrase' => $this->userHasSecretPhrase($user->Id),
                'has_cards' => $this->hasRegisteredCards($user->Id)
            ];

            $googleAuth = GoogleAuth::where('UserId', $user->Id)->first();
            if ($googleAuth) {
                $returnArray['has_2fa'] = true;
            } else {
                $secret = $this->generateSecret($user);

                $qrCodeSvg = QrCode::format('svg')->size(300)->generate($secret['qrCodeUrl']);
                $qrCodeBase64 = base64_encode($qrCodeSvg);

                $returnArray['google_secret'] = $secret['secret'];
                $returnArray['google_qrCode'] = "data:image/svg+xml;base64," . $qrCodeBase64;
            }

            return response()->json($returnArray);
        } catch (\Exception $e) {
            return self::basicError($e->getMessage());
        }
    }


    /**
     * @OA\Post(
     *      path="/api/users/activate",
     *      tags={"Activación de cuenta"},
     *      summary="Activar cuenta de usuario, asociar tarjeta, definir frase secreta y activar doble factor de autenticación",
     *      description="Activar cuenta de usuario, asociar tarjeta, definir frase secreta y activar doble factor de autenticación",
     *      @OA\RequestBody(
     *          required=true,
     *          @OA\JsonContent(
     *              required={"user_id", "temporal_code", "google_code"},
     *              @OA\Property(property="user_id", type="string", example="123456"),
     *              @OA\Property(property="temporal_code", type="string", example="1234567"),
     *              @OA\Property(property="google_code", type="string", example="1234567"),
     *              @OA\Property(property="name", type="string", example="Nombre"),
     *              @OA\Property(property="last_name", type="string", example="Apellido"),
     *              @OA\Property(property="phone", type="string", example="1234567890"),
     *              @OA\Property(property="secret_phrase", type="string", example="Frase secreta"),
     *              @OA\Property(property="card", type="string", example="12345678"),
     *              @OA\Property(property="nip", type="string", example="1234"),
     *              @OA\Property(property="moye", type="string", example="0123")
     *          )
     *     ),
     *
     *      @OA\Response(
     *          response=200,
     *          description="Cuenta activada",
     *          @OA\JsonContent(
     *              @OA\Property(
     *                  property="message",
     *                  type="array",
     *                  @OA\Items(type="string"),
     *                  example={
     *                      "Los datos del usuario han sido registrados",
     *                      "La frase secreta ha sido registrada",
     *                      "La tarjeta ha sido registrada y asignada al usuario",
     *                      "El doble factor de autenticación ha sido activado"
     *                  }
     *              )
     *          )
     *      ),
     *
     *      @OA\Response(
     *          response=400,
     *          description="Error al activar la cuenta",
     *          @OA\JsonContent(
     *              @OA\Property(property="error", type="string")
     *          ),
     *          @OA\Examples(
     *              example="user_not_found",
     *              summary="Usuario no encontrado",
     *              value={"error": "El usuario no existe"}
     *          ),
     *          @OA\Examples(
     *              example="invalid_temporal_code",
     *              summary="Código temporal incorrecto",
     *              value={"error": "El código temporal no coincide con el registrado"}
     *          ),
     *          @OA\Examples(
     *              example="invalid_secret_phrase",
     *              summary="Frase secreta no válida",
     *              value={"error": "La frase secreta debe tener al menos 10 caracteres"}
     *          ),
     *          @OA\Examples(
     *              example="invalid_nip",
     *              summary="NIP incorrecto",
     *              value={"error": "El NIP debe tener 4 dígitos"}
     *          )
     *      )
     * )
     *
     */

    public function activate(Request $request)
    {
        try {
            $operations = [];

            $this->validate($request, [
                'user_id' => 'required',
                'temporal_code' => 'required',
                'google_code' => 'required|string|min:6|max:6'
            ], [
                'user_id.required' => '¡Que pena! el proceso no pudo completarse de forma exitosa, refresque la página y vuelva a intentarlo',
                'temporal_code.required' => 'El código temporal es requerido',
                'google_code.required' => 'El código de google es requerido',
                'google_code.string' => 'El código de google no es válido',
                'google_code.min' => 'El código de google debe tener al menos 6 caracteres',
                'google_code.max' => 'El código de google debe tener como máximo 6 caracteres'
            ]);

            $user = User::where('Id', $request->user_id)->first();
            if (!$user) {
                throw new \Exception('El usuario no existe', 400);
            }

            if ($user->StpAccountId != $request->temporal_code) {
                throw new \Exception('El código temporal no coincide con el registrado', 400);
            }

            if ($user && $user->Active == 0 && $user->ProfileId == 8 && $user->Name == 'Usuario' && $user->Lastname == 'Nuevo' && $user->Phone == '0000000000') {
                $this->validate($request, [
                    'name' => 'required|string',
                    'last_name' => 'required|string',
                    'phone' => 'required|min:10|max:15|regex:/^[0-9]+$/'
                ], [
                    'name.required' => 'El nombre es requerido',
                    'name.string' => 'El nombre no es válido',
                    'last_name.required' => 'El apellido es requerido',
                    'last_name.string' => 'El apellido no es válido',
                    'phone.required' => 'El teléfono es requerido',
                    'phone.min' => 'El teléfono debe tener al menos 10 dígitos',
                    'phone.max' => 'El teléfono debe tener máximo 15 dígitos',
                    'phone.regex' => 'El teléfono no es válido'
                ]);
            }

            if (!$this->userHasSecretPhrase($user->Id)) {
                $this->validate($request, [
                    'secret_phrase' => 'required|string|min:10|max:100'
                ], [
                    'secret_phrase.required' => 'La frase secreta es requerida',
                    'secret_phrase.string' => 'La frase secreta no es válida',
                    'secret_phrase.min' => 'La frase secreta debe tener al menos 10 caracteres',
                    'secret_phrase.max' => 'La frase secreta debe tener máximo 100 caracteres'
                ]);
            }

            if (!$this->hasRegisteredCards($user->Id)) {
                $this->validate($request, [
                    'card' => 'required|string|min:8|max:8|regex:/^[0-9]+$/',
                    'nip' => 'required|string|min:4|max:4|regex:/^[0-9]+$/',
                    'moye' => 'required'
                ], [
                    'card.required' => 'El número de tarjeta es requerido',
                    'card.string' => 'El número de tarjeta no es válido',
                    'card.min' => 'El número de tarjeta debe tener 8 dígitos',
                    'card.max' => 'El número de tarjeta debe tener 8 dígitos',
                    'card.regex' => 'El número de tarjeta no es válido',
                    'nip.required' => 'El nip es requerido',
                    'nip.string' => 'El nip no es válido',
                    'nip.min' => 'El nip debe tener 4 dígitos',
                    'nip.max' => 'El nip debe tener 4 dígitos',
                    'nip.regex' => 'El nip no es válido',
                    'moye.required' => 'El mes y año de expiración es requerido',
                    // 'moye.regex' => 'El mes y año de expiración no es válido'
                ]);
            }

            SecurityGoogleAuth::authorized($user->Id, $request->google_code);

            DB::beginTransaction();

            User::where('Id', $user->Id)->update([
                'Active' => 1,
                'Name' => $request->name ?? "",
                'Lastname' => $request->last_name ?? "",
                'Phone' => $request->phone ?? "",
                'StpAccountId' => ""
            ]);

            $user = User::where('Id', $user->Id)->first();

            $operations[] = 'Los datos del usuario han sido registrados';

            if (!$this->userHasSecretPhrase($user->Id)) {
                SecretPhrase::create([
                    'Id' => Uuid::uuid7(),
                    'UserId' => $user->Id,
                    'SecretPhrase' => $request->secret_phrase
                ]);
                $operations[] = 'La frase secreta ha sido registrada';
            }

            if (!$this->hasRegisteredCards($user->Id)) {
                $cardCloudData = $this->validateCard($request->card, $request->nip, $request->moye);

                $company = CompanyProjection::where('Services', 'like', '%' . $cardCloudData['subaccount_id'] . '%')->first();
                if (!$company) throw new \Exception('La tarjeta no pertenece a ninguna empresa. Pongase en contacto con el área de soporte', 400);

                User::where('Id', $user->Id)->update([
                    'BusinessId' => $company->BusinessId
                ]);

                $companyUsers = json_decode($company->Users, true);
                $newCompanyUsers = [];
                $c = 0;
                foreach ($companyUsers as $companyUser) {
                    if ($companyUser['id'] == $user->Id) {
                        $companyUser['name'] = $user->Name;
                        $companyUser['last_name'] = $user->Lastname;
                        $c = 1;
                    }

                    $newCompanyUsers[] = $companyUser;
                }

                if ($c == 0) {
                    $newCompanyUsers[] = [
                        'id' => $user->Id,
                        'companyId' => $company->Id,
                        'profile' => 8,
                        'name' => $user->Name,
                        'lastname' => $user->Lastname,
                        'email' => $user->Email,
                        'createDate' => $user->CreateDate
                    ];
                }

                CompanyProjection::where('Id', $company->Id)->update([
                    'Users' => json_encode($newCompanyUsers)
                ]);

                CompaniesAndUsers::create([
                    'CompanyId' => $company->Id,
                    'UserId' => $user->Id,
                    'ProfileId' => 8,
                    'Name' => $user->Name,
                    'Lastname' => $user->Lastname,
                    'Email' => $user->Email,
                    'CreateDate' => Carbon::now('America/Mexico_City')->format('Y-m-d H:i:s')
                ]);

                $assignedWithoutEmail = CardAssigned::where('CardCloudId', $cardCloudData['card_id'])->where('Email', '')->first();
                if ($assignedWithoutEmail) {
                    CardAssigned::where('CardCloudId', $cardCloudData['card_id'])->delete();
                    User::where('Id', $assignedWithoutEmail->UserId)->delete();
                }

                CardAssigned::create([
                    'Id' => Uuid::uuid7(),
                    'BusinessId' => $company->BusinessId,
                    'CardCloudId' => $cardCloudData['card_id'],
                    'CardCloudNumber' => "",
                    'UserId' => $user->Id,
                    'Name' => $user->Name,
                    'Lastname' => $user->Lastname,
                    'Email' => $user->Email,
                    'IsPending' => 0,
                    'CreatedByUser' => $user->Id,
                    'CreateDate' => Carbon::now('America/Mexico_City')->format('Y-m-d H:i:s')
                ]);

                $operations[] = 'La tarjeta ha sido registrada y asignada al usuario';
            }

            $operations[] = 'El dobble factor de autenticación ha sido activado';

            DB::commit();

            self::send_access($user);

            return response()->json(['message' => $operations], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al activar cuenta de usuario: ', [
                'user_id' => $request->user_id,
                'temporal_code' => $request->temporal_code,
                'google_code' => $request->google_code,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request' => $request->all()
            ]);
            return self::basicError($e->getMessage());
        }
    }

    private function getSvgQrCode($qrCodeUrl)
    {
        $qrCodeSvg = QrCode::format('svg')->size(300)->generate($qrCodeUrl);
        return "data:image/svg+xml;base64," . base64_encode($qrCodeSvg);
    }

    private function createTemporalUser($email)
    {
        $user = new User();
        $user->Id = Uuid::uuid7();
        $user->ProfileId = 8;
        $user->Email = $email;
        $user->Name = 'Usuario';
        $user->Lastname = 'Nuevo';
        $user->Phone = '0000000000';
        $user->Password = Password::hashPassword('12345678');
        $user->StpAccountId = substr(Uuid::uuid7(), -7);
        $user->Register = Carbon::now('America/Mexico_City')->format('Y-m-d H:i:s');
        $user->Active = 0;
        $user->save();

        return $user;
    }

    private function generateSecret($user)
    {
        $google2fa = new Google2FA();
        $secret = $google2fa->generateSecretKey();

        GoogleAuth::create([
            'Id' => Uuid::uuid7(),
            'UserId' => $user->Id,
            'Provider' => 'GoogleAuthenticator',
            'SecretKey' => Crypt::encrypt($secret),
            'RecoveryKeys' => "1",
            'CreateDate' => Carbon::now('America/Mexico_City')->format('Y-m-d H:i:s')
        ]);

        $qrCodeUrl = $google2fa->getQRCodeUrl(
            'Card Cloud',
            $user->Email,
            $secret
        );

        return [
            'secret' => $secret,
            'qrCodeUrl' => $qrCodeUrl,
            'url' => "otpauth://totp/Card%20Cloud:" . $user->Email . "?secret=" . $secret . "&issuer=Card%20Cloud"
        ];
    }

    private function userHasSecretPhrase($userId)
    {
        $secretPhrase = SecretPhrase::where('UserId', $userId)->first();
        return $secretPhrase ? true : false;
    }

    private function hasRegisteredCards($userId)
    {
        $cards = CardAssigned::where('UserId', $userId)->get();
        return $cards->count() > 0 ? true : false;
    }

    private function validateCard($card, $nip, $moye)
    {
        try {
            $client = new Client();
            $response = $client->request('POST', env('CARD_CLOUD_BASE_URL') . '/card/validate', [
                'headers' => [
                    'Content-Type' => 'application/json'
                ],
                'json' => [
                    'card' => $card,
                    'pin' => $nip,
                    'moye' => $moye
                ]
            ]);

            $decodedJson = json_decode($response->getBody(), true);

            if (CardManagementController::validateAlreadyAssigned($decodedJson['card_id'])) {
                throw new \Exception('La tarjeta ya está asignada a un usuario', 400);
            }

            return $decodedJson;
        } catch (RequestException $e) {
            throw new \Exception('Los datos de la tarjeta no son válidos', 400);
        }
    }

    public function cleanActivation(Request $request)
    {
        try {
            $this->validate($request, [
                'email' => 'required|email'
            ], [
                'email.required' => 'El email es requerido',
                'email.email' => 'El email no es válido'
            ]);

            $user = User::where('email', $request->email)->where('ProfileId', 8)->first();
            if ($user) {
                DB::beginTransaction();
                CardAssigned::where('UserId', $user->Id)->delete();
                GoogleAuth::where('UserId', $user->Id)->delete();
                SecretPhrase::where('UserId', $user->Id)->delete();
                User::where('Id', $user->Id)->delete();
                CompaniesAndUsers::where('UserId', $user->Id)->delete();

                $company = CompanyProjection::where('Users', 'like', '%"id":"' . $user->Id . '"%')->first();
                if ($company) {
                    $companyUsers = json_decode($company->Users, true);
                    $newCompanyUsers = [];
                    foreach ($companyUsers as $companyUser) {
                        if ($companyUser['id'] != $user->Id) {
                            $newCompanyUsers[] = $companyUser;
                        }
                    }

                    CompanyProjection::where('Id', $company->Id)->update([
                        'Users' => json_encode($newCompanyUsers)
                    ]);
                }

                DB::commit();
                return response()->json(['message' => 'El proceso de activación del usuario ha sido eliminado'], 200);
            } else {
                DB::rollBack();
                throw new \Exception('El email no está registrado', 404);
            }
        } catch (\Exception $e) {
            return self::basicError($e->getMessage());
        }
    }

    public static function send_access($user)
    {
        try {
            $curl = curl_init();
            curl_setopt_array($curl, array(
                CURLOPT_URL =>  env('APP_API_URL') . "/api/user/password/reset/" . $user->Id,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'GET',
            ));

            curl_exec($curl);
            curl_close($curl);
        } catch (\Exception $e) {
            throw new \Exception('Error al enviar el correo de acceso', 400);
        }
    }
}
