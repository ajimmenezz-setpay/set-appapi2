<?php

namespace App\Http\Controllers\CardCloud\Credits;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Security\Password;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use GuzzleHttp\Client;
use App\Http\Services\CardCloudApi;
use App\Models\CardCloud\Credit;
use GuzzleHttp\Exception\RequestException;
use Ramsey\Uuid\Uuid;
use App\Http\Controllers\Backoffice\Company;
use Illuminate\Support\Facades\Mail;
use App\Mail\SendCredentialsToCreditUser;


class SubaccountCreditController extends Controller
{

    /**
     * @OA\Get(
     *      path="/api/cardCloud/sub-account/{companyId}/credits",
     *      summary="Obtiene los créditos de la empresa seleccionada",
     *      tags={"Card Cloud - Créditos"},
     *      operationId="getSubaccountCredits",
     *      security={{"bearerAuth":{}}},
     *
     *      @OA\Response(
     *          response=200,
     *          description="Lista de créditos de la empresa seleccionada",
     *          @OA\JsonContent(
     *              type="array",
     *              @OA\Items(
     *                  @OA\Property(property="id", type="string", example="0198efc0-e135-73ce-a8ad-539d64d53bf9"),
     *                  @OA\Property(property="name", type="string", example="Alonso Jiménez"),
     *                  @OA\Property(property="email", type="string", example="ajimmenezz+963@gmail.com"),
     *                  @OA\Property(property="limit", type="number", example=20000),
     *                  @OA\Property(property="used", type="number", example=0),
     *                  @OA\Property(property="details", type="object",
     *                      @OA\Property(property="id", type="string", example="0198efc0-e123-73de-b73b-c09d38fda0f7"),
     *                      @OA\Property(property="credit_limit", type="number", example=20000),
     *                      @OA\Property(property="used_credit", type="number", example=0),
     *                      @OA\Property(property="available_credit", type="number", example=20000),
     *                      @OA\Property(property="minimum_payment", type="number", example=0),
     *                      @OA\Property(property="interest_rate", type="number", example=78.05),
     *                      @OA\Property(property="yearly_fee", type="number", example=0),
     *                      @OA\Property(property="late_interest_rate", type="number", example=0),
     *                      @OA\Property(property="credit_start_date", type="string", format="date-time", example=null),
     *                      @OA\Property(property="next_fee_date", type="string", format="date-time", example=null)
     *                  )
     *              )
     *          )
     *      ),
     *
     *      @OA\Response(
     *          response=403,
     *          description="No tiene permisos para consultar estos datos.",
     *          @OA\JsonContent(
     *              type="object",
     *              @OA\Property(property="message", type="string", example="No tiene permisos para consultar estos datos.")
     *          )
     *      ),
     *
     *      @OA\Response(
     *          response=401,
     *          description="No autorizado.",
     *          @OA\JsonContent(
     *              type="object",
     *              @OA\Property(property="message", type="string", example="No autorizado.")
     *          )
     *      )
     * )
     */
    public function index(Request $request, $uuid)
    {
        try {
            if ($request->attributes->get('jwt')->profileId != 5) {
                throw new \Exception("No tiene permisos para consultar estos datos.", 403);
            }

            $client = new Client();
            $response = $client->request('GET', env('CARD_CLOUD_BASE_URL') . '/api/v1/credit', [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Bearer ' . CardCloudApi::getToken($request->attributes->get('jwt')->id),
                ]
            ]);

            $decodedJson = json_decode($response->getBody(), true);

            $credits = Credit::join('t_users', 't_card_cloud_credits.UserId', '=', 't_users.Id')
                ->where('t_card_cloud_credits.CompanyId', $uuid)
                ->select('t_card_cloud_credits.*', 't_users.Name', 't_users.Lastname', 't_users.Email')
                ->get();

            $data = $credits->map(function ($credit) use ($decodedJson) {
                $creditDetails = collect($decodedJson)->firstWhere('id', $credit->ExternalId);
                return [
                    'id' => $credit->UUID,
                    'name' => $credit->Name . " " . $credit->Lastname,
                    'email' => $credit->Email,
                    'limit' => $creditDetails['credit_limit'],
                    'used' => $creditDetails['used_credit'],
                    'details' => $creditDetails
                ];
            });

            return response()->json($data);
        } catch (\Exception $e) {
            return response("Error: " . $e->getMessage(), 400);
        }
    }

    /**
     *  @OA\Get(
     *      path="/api/cardCloud/credits/users",
     *      summary="Obtener usuarios existentes sin crédito activo",
     *      tags={"Card Cloud - Créditos"},
     *      operationId="getNonCreditUsers",
     *      security={{"bearerAuth":{}}},
     *
     *      @OA\Response(
     *          response=200,
     *          description="Lista de usuarios sin crédito activo",
     *          @OA\JsonContent(
     *              type="array",
     *              @OA\Items(
     *                  @OA\Property(property="Id", type="integer", example=1),
     *                  @OA\Property(property="Name", type="string", example="John"),
     *                  @OA\Property(property="Lastname", type="string", example="Doe"),
     *                  @OA\Property(property="Email", type="string", example="john.doe@example.com")
     *              )
     *          )
     *      ),
     *
     *      @OA\Response(
     *          response=401,
     *          description="Unauthorized: Token de acceso inválido o expirado.",
     *          @OA\JsonContent(
     *              @OA\Property(property="error", type="string", example="Unauthorized: Token de acceso inválido o expirado.")
     *          )
     *      ),
     *
     *      @OA\Response(
     *          response=403,
     *          description="Error: No tiene permisos para consultar estos datos.",
     *          @OA\JsonContent(
     *              @OA\Property(property="error", type="string", example="Error: No tiene permisos para consultar estos datos.")
     *          )
     *      )
     * )
     */

    public function getUsers(Request $request)
    {
        try {
            if ($request->attributes->get('jwt')->profileId != 5) {
                throw new \Exception("No tiene permisos para consultar estos datos.", 403);
            }

            $users = User::leftJoin('t_card_cloud_credits', 't_users.Id', '=', 't_card_cloud_credits.UserId')
                ->whereNull('t_card_cloud_credits.Id')
                ->where('BusinessId', $request->attributes->get('jwt')->businessId)
                ->where('ProfileId', 8)
                ->where('Active', 1)
                ->select('t_users.Id', 'Name', 'Lastname', 'Email')
                ->orderBy('Name', 'ASC')
                ->orderBy('Lastname', 'ASC')
                ->orderBy('Email', 'ASC')
                ->get();

            return response()->json($users);
        } catch (\Exception $e) {
            return response("Error: " . $e->getMessage(), $e->getCode() ?? 400);
        }
    }


    /**
     * @OA\Post(
     *      path="/api/cardCloud/ub-account/{uuid}credits",
     *      summary="Crear un nuevo crédito",
     *      tags={"Card Cloud - Créditos"},
     *      operationId="createCredit",
     *      security={{"bearerAuth":{}}},
     *
     *      @OA\RequestBody(
     *          required=true,
     *          @OA\JsonContent(
     *              type="object",
     *              @OA\Property(property="limit", type="number", format="float", example=1000.00),
     *              @OA\Property(property="interest_rate", type="number", format="float", example=5.0),
     *              @OA\Property(property="yearly_fee", type="number", format="float", example=100.0),
     *              @OA\Property(property="late_interest_rate", type="number", format="float", example=10.0),
     *              @OA\Property(property="is_new_user", type="boolean", example=true),
     *              @OA\Property(property="new_user", type="object",
     *                  @OA\Property(property="name", type="string", example="John"),
     *                  @OA\Property(property="lastname", type="string", example="Doe"),
     *                  @OA\Property(property="email", type="string", example="john.doe@example.com"),
     *                  @OA\Property(property="phone", type="string", example="1234567890")
     *              ),
     *              @OA\Property(property="user_id", type="string", example="123456")
     *          )
     *      ),
     *
     *      @OA\Response(
     *          response=201,
     *          description="Crédito creado exitosamente.",
     *          @OA\JsonContent(
     *              type="object",
     *              @OA\Property(property="message", type="string", example="Crédito creado exitosamente.")
     *          )
     *      ),
     *
     *      @OA\Response(
     *          response=400,
     *          description="Error de validación.",
     *          @OA\JsonContent(
     *              type="object",
     *              @OA\Property(property="error", type="string", example="Error de validación.")
     *          )
     *      ),
     *
     *      @OA\Response(
     *          response=401,
     *          description="No autorizado.",
     *          @OA\JsonContent(
     *              type="object",
     *              @OA\Property(property="message", type="string", example="No autorizado.")
     *          )
     *      ),
     *
     *      @OA\Response(
     *          response=403,
     *          description="Error: No tiene permisos para consultar estos datos.",
     *          @OA\JsonContent(
     *              @OA\Property(property="error", type="string", example="Error: No tiene permisos para consultar estos datos.")
     *          )
     *      )
     * )
     */
    public function store(Request $request, $uuid)
    {
        try {
            DB::beginTransaction();

            $this->validate($request, [
                'limit' => 'required|numeric|min:0.01|regex:/^\d+(\.\d{1,2})?$/',
                'interest_rate' => 'required|numeric|min:0',
                'yearly_fee' => 'nullable|numeric|min:0',
                'late_interest_rate' => 'nullable|numeric|min:0',
                'is_new_user' => 'required|boolean'
            ], [
                'limit.required' => 'El límite de crédito es obligatorio (campo limit)',
                'limit.numeric' => 'El límite de crédito debe ser un número (campo limit)',
                'limit.min' => 'El límite de crédito debe ser al menos :min (campo limit)',
                'limit.regex' => 'El límite de crédito puede tener hasta 2 decimales (campo limit).',
                'interest_rate.required' => 'La tasa de interés es obligatoria (campo interest_rate).',
                'interest_rate.numeric' => 'La tasa de interés debe ser un número (campo interest_rate).',
                'interest_rate.min' => 'La tasa de interés debe ser al menos :min (campo interest_rate).',
                'yearly_fee.numeric' => 'La comisión anual debe ser un número (campo yearly_fee).',
                'yearly_fee.min' => 'La comisión anual debe ser al menos :min (campo yearly_fee).',
                'late_interest_rate.numeric' => 'La comisión moratoria debe ser un número (campo late_interest_rate).',
                'late_interest_rate.min' => 'La comisión moratoria debe ser al menos :min (campo late_interest_rate).',
                'is_new_user.required' => 'Debe definir si el crédito es para un nuevo usuario (campo is_new_user).'
            ]);

            if ($request->is_new_user) {
                $this->validate($request, [
                    'new_user' => 'required|array',
                    'new_user.name' => 'required|string|max:255',
                    'new_user.lastname' => 'required|string|max:255',
                    'new_user.email' => 'required|email|max:255',
                    'new_user.phone' => 'nullable|string|max:10',
                ], [
                    'new_user.required' => 'Los datos del nuevo usuario son obligatorios (campo new_user).',
                    'new_user.array' => 'Los datos del nuevo usuario deben ser un objeto (campo new_user).',
                    'new_user.name.required' => 'El nombre del nuevo usuario es obligatorio (campo new_user.name).',
                    'new_user.name.string' => 'El nombre del nuevo usuario debe ser una cadena de texto (campo new_user.name).',
                    'new_user.name.max' => 'El nombre del nuevo usuario no debe exceder los :max caracteres (campo new_user.name).',
                    'new_user.lastname.required' => 'El apellido del nuevo usuario es obligatorio (campo new_user.lastname).',
                    'new_user.lastname.string' => 'El apellido del nuevo usuario debe ser una cadena de texto (campo new_user.lastname).',
                    'new_user.lastname.max' => 'El apellido del nuevo usuario no debe exceder los :max caracteres (campo new_user.lastname).',
                    'new_user.email.required' => 'El correo electrónico del nuevo usuario es obligatorio (campo new_user.email).',
                    'new_user.email.email' => 'El correo electrónico del nuevo usuario debe ser una dirección de correo válida (campo new_user.email).',
                    'new_user.email.max' => 'El correo electrónico del nuevo usuario no debe exceder los :max caracteres (campo new_user.email).',
                    'new_user.phone.string' => 'El teléfono del nuevo usuario debe ser una cadena de texto (campo new_user.phone).',
                    'new_user.phone.max' => 'El teléfono del nuevo usuario no debe exceder los :max caracteres (campo new_user.phone).',
                ]);

                if (User::where('Email', $request->new_user['email'])->first()) {
                    throw new \Exception("El correo electrónico ya está registrado.", 409);
                }

                $password = Password::createRandomPassword(8);

                $user = User::create([
                    'Id' => Uuid::uuid7(),
                    'Name' => $request->new_user['name'],
                    'Lastname' => $request->new_user['lastname'],
                    'Email' => $request->new_user['email'],
                    'Phone' => $request->new_user['phone'] ?? null,
                    'ProfileId' => 8,
                    'Password' => Password::hashPassword($password),
                    'BusinessId' => $request->attributes->get('jwt')->businessId,
                    'Register' => now(),
                    'Active' => 1
                ]);
            } else {
                $this->validate($request, [
                    'user_id' => 'required|exists:t_users,Id',
                ], [
                    'user_id.required' => 'El usuario es obligatorio (campo user_id).',
                    'user_id.exists' => 'El usuario seleccionado no es válido (campo user_id).',
                ]);

                $user = User::where('Id', $request->user_id)->first();
                if ($user->ProfileId != 8) {
                    throw new \Exception("El usuario seleccionado no tiene el perfil de tarjetahabiente.", 403);
                }
            }

            Company::addUserToCompany($uuid, $user);

            try {
                $client = new Client();
                $response = $client->request('POST', env('CARD_CLOUD_BASE_URL') . '/api/v1/credit', [
                    'headers' => [
                        'Content-Type' => 'application/json',
                        'Authorization' => 'Bearer ' . CardCloudApi::getToken($request->attributes->get('jwt')->id),
                    ],
                    'json' => [
                        "limit" => $request->limit,
                        "interest_rate" => $request->interest_rate,
                        "yearly_fee" => $request->yearly_fee ?? 0,
                        "late_interest_rate" => $request->late_interest_rate ?? 0,
                        "credit_start_date" => $request->credit_start_date ?? null
                    ]
                ]);

                $decodedJson = json_decode($response->getBody(), true);

                Credit::create([
                    'UUID' => Uuid::uuid7(),
                    'ExternalId' => $decodedJson['data']['id'],
                    'CompanyId' => $uuid,
                    'UserId' => $user->Id
                ]);

                if ($request->is_new_user) {
                    self::setSMTP($request->attributes->get('jwt')->businessId);
                    Mail::to($user->Email)->send(new SendCredentialsToCreditUser($user->Email, $password, self::baseUrlByBusinessId($request->attributes->get('jwt')->businessId)));
                }

                DB::commit();

                return response()->json(['message' => 'Crédito creado exitosamente.'], 200);
            } catch (RequestException $e) {
                if ($e->hasResponse()) {
                    $statusCode = $e->getResponse()->getStatusCode();
                    $responseBody = $e->getResponse()->getBody()->getContents();
                    $decodedJson = json_decode($responseBody, true);
                    $message = 'Error al crear el crédito.';

                    if (json_last_error() === JSON_ERROR_NONE) {
                        $message .= " " . $decodedJson['message'];
                    }
                    throw new \Exception($message, $statusCode);
                } else {
                    throw new \Exception("Error al crear el crédito.", 500);
                }
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            return response("Error: " . $e->getFile() . " " . $e->getLine() . " " . $e->getMessage(), 400);
        }
    }
}
