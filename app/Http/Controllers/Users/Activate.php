<?php

namespace App\Http\Controllers\Users;

use App\Http\Controllers\CardCloud\CardManagementController;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Security\Crypt;
use App\Http\Controllers\Security\GoogleAuth as SecurityGoogleAuth;
use App\Http\Controllers\Security\Password;
use App\Models\Backoffice\Companies\CompanyProjection;
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

class Activate extends Controller
{
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
            if ($user && $user->Active == 0 && $user->ProfileId == 8 && $user->Name == 'Usuario' && $user->Lastname == 'Nuevo' && $user->Phone == '0000000000') {
                $continueProcess = true;
                User::where('Id', $user->Id)->delete();
                GoogleAuth::where('UserId', $user->Id)->delete();
            }
            if (!$user || $continueProcess) {
                $user = $this->createTemporalUser($request->email);

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
                $returnArray['temporal_code'] = $user->StpAccountId;
                return response()->json($returnArray);
            }
            return response()->json(['message' => 'Para continuar con la activación de tu cuenta, por favor inicia sesión'], 202);
        } catch (\Exception $e) {
            return self::basicError($e->getMessage());
        }
    }

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
                throw new \Exception('No se ha completado el registro de la cuenta. Es necesario volver a empezar el proceso de activación', 400);
            }

            if (!$user) {
                throw new \Exception('El email no está registrado', 400);
            }

            if (!Password::verifyUserPassword($user->Id, $request->password)) {
                throw new \Exception('La contraseña no coincide con la registrada', 400);
            }

            $user->StpAccountId = substr(Uuid::uuid7(), -7);
            $user->save();

            $returnArray = [
                'user' => [
                    'id' => $user->Id,
                    'email' => $user->Email,
                    'name' => $user->Name,
                    'last_name' => $user->Lastname,
                    'phone' => $user->Phone
                ],
                'temporal_code' => $user->StpAccountId,
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

    public function activate(Request $request)
    {
        try {
            $operations = [];

            $this->validate($request, [
                'user_id' => 'required',
                'temporal_code' => 'required',
                'google_code' => 'required|string|min:6|max:6'
            ], [
                'user_id.required' => 'El id del usuario es requerido',
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

            // SecurityGoogleAuth::authorized($user->Id, $request->google_code);

            DB::beginTransaction();

            User::where('Id', $user->Id)->update([
                'Active' => 1,
                'Name' => $request->name,
                'Lastname' => $request->last_name,
                'Phone' => $request->phone,
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

                companyProjection::where('Id', $company->Id)->update([
                    'Users' => json_encode($newCompanyUsers)
                ]);

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

            return response()->json(['message' => $operations], 200);
        } catch (\Exception $e) {
            DB::rollBack();
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
            'qrCodeUrl' => $qrCodeUrl
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
}
