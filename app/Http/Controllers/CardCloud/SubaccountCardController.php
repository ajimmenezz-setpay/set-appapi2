<?php

namespace App\Http\Controllers\CardCloud;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\CardCloud\Card;
use App\Models\CardCloud\CardAssigned;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Security\GoogleAuth;
use App\Http\Controllers\Security\Password;
use App\Models\Backoffice\Companies\CompanyProjection;
use App\Models\Backoffice\Users\CompaniesAndUsers;
use App\Models\User;
use Carbon\Carbon;
use Maatwebsite\Excel\Facades\Excel;
use Ramsey\Uuid\Uuid;
use Illuminate\Support\Str;

class SubaccountCardController extends Controller
{
    public function index(Request $request, $uuid)
    {
        $dates = [
            '1' => Carbon::now(),
            '2' => "",
            '3' => "",
            '4' => ""
        ];
        $subaccount = DB::connection('card_cloud')
            ->table('subaccounts')
            ->where('UUID', $uuid)
            ->first();

        if (!$subaccount) {
            return response("No se encontraron tarjetas para la subcuenta especificada.", 404);
        }
        $dates['2'] = Carbon::now();

        $businessUsers = self::businessCardUsers($request->attributes->get('jwt')->businessId);

        $cards = DB::connection('card_cloud')
            ->table('cards')
            ->join('card_setup', 'cards.Id', '=', 'card_setup.CardId')
            ->leftJoin('card_pan', 'card_pan.CardId', '=', 'cards.Id')
            ->where('card_setup.Status', '<>', 'CANCELED')
            ->whereNotNull('cards.Pan')
            ->where('cards.SubAccountId', $subaccount->Id);
        var_dump(self::printQuery($cards));
        die();

        $cards = $cards->get();
        $dates['3'] = Carbon::now();


        $cards = $cards->map(function ($card) use ($businessUsers) {
            return [
                'card_id' => $card->UUID,
                'card_external_id' => $card->ExternalId,
                'card_type' => $card->Type,
                'brand' => $card->Brand,
                'bin' => (strlen($card->Pan) >= 16) ? substr($card->Pan, -8) : "",
                'pan' => $card->Pan,
                'client_id' => self::getClientId($card->CustomerPrefix, $card->CustomerId),
                'masked_pan' => (strlen($card->Pan) >= 16) ? "XXXX XXXX XXXX " . substr($card->Pan, -4) : "",
                'balance' => self::decrypt($card->Balance),
                'clabe' => $card->ShowSTPAccount == 1 ? $card->STPAccount : null,
                'status' => $card->Status,
                'name' => $businessUsers[$card->UUID]['name'] ?? "",
                'lastname' => $businessUsers[$card->UUID]['lastname'] ?? "",
                'email' => $businessUsers[$card->UUID]['email'] ?? "",
                'ownerId' => $businessUsers[$card->UUID]['ownerId'] ?? "",
                'setups' => [
                    'Ecommerce' => $card->Ecommerce,
                    'International' => $card->International,
                    'Stripe' => $card->Stripe,
                    'Wallet' => $card->Wallet,
                    'Withdrawal' => $card->Withdrawal,
                    'Contactless' => $card->Contactless,
                    'PinOffline' => $card->PinOffline,
                    'PinOnUs' => $card->PinOnUs
                ]
            ];
        });

        $dates['4'] = Carbon::now();

        return response()->json([
            'cards' => $cards,
            'dates' => $dates
        ]);
    }

    public static function businessCardUsers($businessId)
    {
        $users = CardAssigned::where('BusinessId', $businessId)
            ->select(
                'CardCloudId',
                'UserId',
                'Name',
                'Lastname',
                'Email'
            )
            ->get();

        $array = [];
        foreach ($users as $user) {
            $array[$user->CardCloudId] = [
                'ownerId' => $user->UserId,
                'name' => $user->Name,
                'lastname' => $user->Lastname,
                'email' => $user->Email
            ];
        }

        return $array;
    }

    public function assignUserFromFile(Request $request)
    {
        try {
            $request->validate([
                'file' => 'required|file|mimes:xlsx,xls,csv',
                'companyId' => 'required|uuid',
                'subAccountId' => 'required|uuid',
                'googleAuthenticatorCode' => 'required|digits:6',
            ], [
                'file.required' => 'El archivo es obligatorio.',
                'file.file' => 'El archivo debe ser un archivo válido.',
                'file.mimes' => 'El archivo debe ser de tipo xlsx, xls o csv.',
                'companyId.required' => 'El ID de la empresa es obligatorio.',
                'companyId.uuid' => 'El ID de la empresa debe ser un UUID válido.',
                'subAccountId.required' => 'El ID de la subcuenta es obligatorio.',
                'subAccountId.uuid' => 'El ID de la subcuenta debe ser un UUID válido.',
                'googleAuthenticatorCode.required' => 'El código de Google Authenticator es obligatorio.',
                'googleAuthenticatorCode.digits' => 'El código de Google Authenticator debe tener 6 dígitos.'
            ]);

            GoogleAuth::authorized($request->attributes->get('jwt')->id, $request->googleAuthenticatorCode);

            $data = Excel::toArray([], $request->file('file'));

            $errors = [];
            $c = 0;

            foreach ($data[0] as $rows) {
                if ($c == 0) {
                    $c++;
                    continue; // Saltamos la primera fila (cabecera)
                }

                $clientId = trim($rows[0]);
                $name = trim($rows[2]);
                $lastname = trim($rows[3]);
                $phone = trim($rows[4]);
                $email = trim($rows[5]);

                if (empty($clientId)) {
                    continue;
                }

                if (empty($name) || empty($lastname)) {
                    $errors[] = "El nombre y apellido son obligatorios para el cliente con ID: $clientId.";
                    continue;
                }

                $splitClientId = self::splitClientId($clientId);

                $card = DB::connection('card_cloud')->table('cards')->where(
                    'CustomerPrefix',
                    $splitClientId['prefix']
                )->where(
                    'CustomerId',
                    $splitClientId['number']
                )->first();
                if (!$card) {
                    $errors[] = "No se encontró una tarjeta para el cliente con ID: $clientId.";
                    continue;
                }

                $assigned = CardAssigned::where('CardCloudId', $card->UUID)->first();
                if ($assigned) {
                    $errors[] = "La tarjeta {$clientId} ya está asignada al usuario {$assigned->Name} {$assigned->Lastname}.";
                    continue;
                }

                if ($email && $email != "" && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $errors[] = "El correo electrónico proporcionado no es válido para el cliente con ID: $clientId.";
                    continue;
                }

                if ($email != "") {
                    $user = User::where("Email", $email)->first();
                    if ($user) {
                        $errors[] = "El correo electrónico ya ha sido registrado, utilice otra cuenta de correo";
                        continue;
                    }
                }

                $password = Str::random(12);

                DB::beginTransaction();

                try {
                    $user = User::create([
                        'Id' => Uuid::uuid7(),
                        'ProfileId' => 8,
                        'Name' => $name,
                        'Lastname' => $lastname,
                        'Phone' => $phone,
                        'Email' => $email,
                        'Password' => Password::hashPassword($password),
                        'BusinessId' => $request->attributes->get('jwt')->businessId,
                        'Register' => now(),
                        'Active' => 1
                    ]);

                    $assigned = CardAssigned::create([
                        'Id' => Uuid::uuid7(),
                        'BusinessId' => $request->attributes->get('jwt')->businessId,
                        'CardCloudId' => $card->UUID,
                        'CardCloudNumber' => "",
                        'UserId' => $request->attributes->get('jwt')->id,
                        'Name' => $name,
                        'Lastname' => $lastname,
                        'Phone' => $phone,
                        'Email' => $email,
                        'IsPending' => $email != "" ? 0 : 1,
                        'CreatedByUser' => $request->attributes->get('jwt')->id,
                        'CreateDate' => now()
                    ]);

                    CompaniesAndUsers::create([
                        'CompanyId' => $request->companyId,
                        'UserId' => $user->Id,
                        'ProfileId' => $user->ProfileId,
                        'Name' => $user->Name,
                        'Lastname' => $user->Lastname,
                        'Email' => $user->Email,
                        'CreateDate' => $user->Register
                    ]);

                    $projection = CompanyProjection::where('Id', $request->companyId)->first();
                    $usersDecode = json_decode($projection->Users, true);
                    $usersDecode[] = [
                        'id' => $user->Id,
                        'companyId' => $request->companyId,
                        'profile' => $user->ProfileId,
                        'name' => $user->Name,
                        'lastName' => $user->Lastname,
                        'email' => $user->Email,
                        'createDate' => $user->Register
                    ];

                    CompanyProjection::where('Id', $request->companyId)->update([
                        'Users' => json_encode($usersDecode)
                    ]);

                    DB::commit();
                } catch (\Exception $e) {
                    DB::rollBack();
                    $errors[] = "Error al asignar la tarjeta al usuario con ID: $clientId. Error: " . $e->getMessage();
                    continue;
                }
            }

            if (count($errors) > 0) {
                return self::basicError($errors[0]);
            }

            return response()->json();
        } catch (\Exception $e) {
            return self::basicError($e->getMessage());
        }
    }
}
