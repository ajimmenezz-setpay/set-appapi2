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
use App\Http\Controllers\CardCloud\CardManagementController;
use App\Http\Controllers\Security\GoogleAuth;
use Illuminate\Support\Facades\Mail;
use App\Mail\SendCredentialsToCreditUser;
use App\Models\Backoffice\Companies\Company as CompaniesCompany;
use App\Models\CardCloud\CardAssigned;
use Illuminate\Support\Facades\Log;

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
     *                  @OA\Property(property="available", type="number", example=20000),
     *                  @OA\Property(property="minimum_payment", type="number", example=0),
     *                  @OA\Property(property="interest_rate", type="number", example=78.05),
     *                  @OA\Property(property="yearly_fee", type="number", example=0),
     *                  @OA\Property(property="late_interest_rate", type="number", example=0),
     *                  @OA\Property(property="credit_start_date", type="string", format="date-time", example=null),
     *                  @OA\Property(property="next_fee_date", type="string", format="date-time", example=null)
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
                return self::creditObject($credit, $creditDetails);
            });

            return response()->json($data);
        } catch (\Exception $e) {
            return response("Error: " . $e->getMessage(), 400);
        }
    }

    /**
     *  @OA\Get(
     *      path="/api/cardCloud/credits",
     *      summary="Obtener créditos del usuario",
     *      tags={"Card Cloud - Créditos"},
     *      operationId="getUserCredits",
     *      security={{"bearerAuth":{}}},
     *
     *      @OA\Response(
     *          response=200,
     *          description="Lista de créditos del usuario",
     *          @OA\JsonContent(
     *              type="array",
     *              @OA\Items(
     *                  @OA\Property(property="id", type="string", example="0198efc0-e135-73ce-a8ad-539d64d53bf9"),
     *                  @OA\Property(property="name", type="string", example="Alonso Jiménez"),
     *                  @OA\Property(property="email", type="string", example="ajimmenezz+963@gmail.com"),
     *                  @OA\Property(property="limit", type="number", example=20000),
     *                  @OA\Property(property="used", type="number", example=0),
     *                  @OA\Property(property="available", type="number", example=20000),
     *                  @OA\Property(property="minimum_payment", type="number", example=0),
     *                  @OA\Property(property="interest_rate", type="number", example=78.05),
     *                  @OA\Property(property="yearly_fee", type="number", example=0),
     *                  @OA\Property(property="late_interest_rate", type="number", example=0),
     *                  @OA\Property(property="credit_start_date", type="string", format="date-time", example=null),
     *                  @OA\Property(property="next_fee_date", type="string", format="date-time", example=null)
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

    public function userCredits(Request $request)
    {
        try {
            if ($request->attributes->get('jwt')->profileId != 8) {
                throw new \Exception("No tiene permisos para consultar estos datos.", 403);
            }

            $credits = Credit::join('t_users', 't_card_cloud_credits.UserId', '=', 't_users.Id')
                ->where('t_card_cloud_credits.UserId', $request->attributes->get('jwt')->id)
                ->select('t_card_cloud_credits.*', 't_users.Name', 't_users.Lastname', 't_users.Email')
                ->get();

            $data = [];

            foreach ($credits as $credit) {
                try {
                    $client = new Client();
                    $response = $client->request('GET', env('CARD_CLOUD_BASE_URL') . '/api/v1/credit/' . $credit->ExternalId, [
                        'headers' => [
                            'Content-Type' => 'application/json',
                            'Authorization' => 'Bearer ' . CardCloudApi::getToken($request->attributes->get('jwt')->id),
                        ]
                    ]);

                    $decodedJson = json_decode($response->getBody(), true);
                } catch (RequestException $re) {
                    throw new \Exception("Error al obtener los detalles del crédito." . $re->getMessage(), 500);
                }

                $data[] = $this->creditObject($credit, $decodedJson);
            }

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
     *      path="/api/cardCloud/sub-account/{uuid}/credits",
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

            switch ($request->attributes->get('jwt')->profileId) {
                case 5:
                    $company = CompaniesCompany::where('Id', $uuid)->first();
                    if ($company->BusinessId != $request->attributes->get('jwt')->businessId) {
                        throw new \Exception("No tiene permisos para crear un crédito en esta empresa.", 403);
                    }
                    break;
                default:
                    throw new \Exception("No tiene permisos para crear un crédito en esta empresa.", 403);
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
            Log::error("Error en SubaccountCreditController: " . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'request' => $request->all(),
                'user_data' => $request->attributes->get('jwt')
            ]);
            return response("Error: " . $e->getMessage(), 400);
        }
    }

    /**
     * @OA\Get(
     *      path="/cardCloud/credits/{uuid}",
     *      tags={"Card Cloud - Créditos"},
     *      summary="Obtener detalles de un crédito",
     *      description="Devuelve los detalles de un crédito específico",
     *      operationId="getCreditDetails",
     *      security={{"bearerAuth":{}}},
     *      @OA\Parameter(
     *          name="uuid",
     *          in="path",
     *          required=true,
     *          description="UUID del crédito",
     *          @OA\Schema(type="string")
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Detalles del crédito",
     *          @OA\JsonContent(
     *              @OA\Property(property="id", type="string", example="uuid-1234"),
     *              @OA\Property(property="name", type="string", example="John Doe"),
     *              @OA\Property(property="email", type="string", example="john.doe@example.com"),
     *              @OA\Property(property="limit", type="number", format="float", example=1000.00),
     *              @OA\Property(property="used", type="number", format="float", example=200.00),
     *              @OA\Property(property="available", type="number", format="float", example=800.00),
     *              @OA\Property(property="minimum_payment", type="number", format="float", example=50.00),
     *              @OA\Property(property="interest_rate", type="number", format="float", example=5.0),
     *              @OA\Property(property="yearly_fee", type="number", format="float", example=100.00),
     *              @OA\Property(property="late_interest_rate", type="number", format="float", example=10.0),
     *              @OA\Property(property="credit_start_date", type="string", format="date-time", example="2023-01-01T00:00:00Z"),
     *              @OA\Property(property="next_fee_date", type="string", format="date-time", example="2023-02-01T00:00:00Z"),
     *              @OA\Property(property="movements", type="array",
     *                  @OA\Items(
     *                      @OA\Property(property="movement_id", type="string", example="uuid-5678"),
     *                      @OA\Property(property="date", type="string", format="date-time", example="2023-01-15T00:00:00Z"),
     *                      @OA\Property(property="type", type="string", example="payment"),
     *                      @OA\Property(property="amount", type="number", format="float", example=100.00),
     *                      @OA\Property(property="balance", type="number", format="float", example=900.00),
     *                      @OA\Property(property="authorization_code", type="string", example="123456"),
     *                      @OA\Property(property="description", type="string", example="Payment for invoice #1234"),
     *                      @OA\Property(property="status", type="string", example="Approved")
     *                  )
     *              ),
     *              @OA\Property(property="cards", type="array",
     *                  @OA\Items(
     *                      @OA\Property(property="card_id", type="string", example="uuid-91011"),
     *                      @OA\Property(property="card_external_id", type="string", example="uuid-91011"),
     *                      @OA\Property(property="card_type", type="string", example="physical"),
     *                      @OA\Property(property="brand", type="string", example="MASTER"),
     *                      @OA\Property(property="client_id", type="string", example="PR0000003"),
     *                      @OA\Property(property="masked_pan", type="string", example="XXXX XXXX XXXX 5487"),
     *                      @OA\Property(property="status", type="string", example="BLOCKED"),
     *                  )
     *              )
     *          )
     *      )
     * )
     */
    public function show(Request $request, $uuid)
    {
        try {

            $credit = Credit::where('UUID', $uuid)
                ->join('t_users', 't_card_cloud_credits.UserId', '=', 't_users.Id')
                ->select('t_card_cloud_credits.*', 't_users.Email', 't_users.Name', 't_users.Lastname', 't_users.Phone')
                ->first();
            if (!$credit) {
                throw new \Exception("Crédito no encontrado.", 404);
            }

            switch ($request->attributes->get('jwt')->profileId) {
                case 5:
                    $company = CompaniesCompany::where('Id', $credit->CompanyId)->first();
                    if ($company->BusinessId != $request->attributes->get('jwt')->businessId) {
                        throw new \Exception("No tiene permisos para consultar este crédito 1.", 403);
                    }
                    break;
                case 8:
                    if ($credit->UserId != $request->attributes->get('jwt')->id) {
                        throw new \Exception("No tiene permisos para consultar este crédito 2.", 403);
                    }
                    break;
                default:
                    throw new \Exception("No tiene permisos para consultar este crédito 3.", 403);
            }

            try {
                $client = new Client();
                $response = $client->request('GET', env('CARD_CLOUD_BASE_URL') . '/api/v1/credit/' . $credit->ExternalId, [
                    'headers' => [
                        'Content-Type' => 'application/json',
                        'Authorization' => 'Bearer ' . CardCloudApi::getToken($request->attributes->get('jwt')->id),
                    ]
                ]);

                $decodedJson = json_decode($response->getBody(), true);
            } catch (RequestException $re) {
                throw new \Exception("Error al obtener los detalles del crédito." . $re->getMessage(), 500);
            }

            $data = $this->creditObject($credit, $decodedJson, true);

            return response()->json($data);
        } catch (\Exception $e) {
            Log::error("Error en SubaccountCreditController: " . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'request' => $request->all(),
                'user_data' => $request->attributes->get('jwt')
            ]);
            return response("Error: " . $e->getMessage(), 400);
        }
    }



    public function creditObject($credit, $details, $full = false)
    {
        $data = [
            'id' => $credit->UUID,
            'name' => $credit->Name . " " . $credit->Lastname,
            'email' => $credit->Email,
            'limit' => $details['credit_limit'],
            'used' => $details['used_credit'],
            'available' => $details['available_credit'],
            'minimum_payment' => $details['minimum_payment'],
            'interest_rate' => $details['interest_rate'],
            'yearly_fee' => $details['yearly_fee'],
            'late_interest_rate' => $details['late_interest_rate'],
            'credit_start_date' => $details['credit_start_date'],
            'next_fee_date' => $details['next_fee_date']
        ];

        if ($full) {
            $data['cards'] = [];
            $data['movements'] = [];
            if (isset($details['cards']) && !empty($details['cards'])) {
                foreach ($details['cards'] as $card) {
                    $data['cards'][] = [
                        'card_id' => $card['card_id'],
                        'card_external_id' => $card['card_external_id'],
                        'card_type' => $card['card_type'],
                        'brand' => $card['brand'],
                        'client_id' => $card['client_id'],
                        'masked_pan' => $card['masked_pan'],
                        'status' => $card['status'],
                    ];
                }
            }

            if (isset($details['movements']) && !empty($details['movements'])) {
                foreach ($details['movements'] as $movement) {
                    $data['movements'][] = [
                        'movement_id' => $movement['movement_id'],
                        'date' => $movement['date'],
                        'type' => $movement['type'],
                        'amount' => $movement['amount'],
                        'balance' => $movement['balance'],
                        'authorization_code' => $movement['authorization_code'],
                        'description' => $movement['description'],
                        'status' => $movement['status'],
                    ];
                }
            }
        }

        return $data;
    }

    /**
     * @OA\Post(
     *      path="/cardCloud/credits/{uuid}/activate",
     *      summary="Asociar una tarjeta con un crédito",
     *      tags={"Card Cloud - Créditos"},
     *      description="Asocia una tarjeta con el UUID de crédito dado",
     *      operationId="associateCardWithCredit",
     *      security={{"bearerAuth":{}}},
     *
     *      @OA\Parameter(
     *          name="uuid",
     *          in="path",
     *          required=true,
     *          description="UUID of the credit"
     *      ),
     *      @OA\RequestBody(
     *          required=true,
     *          @OA\JsonContent(
     *              @OA\Property(property="card", type="string", example="12345678"),
     *              @OA\Property(property="expiration_date", type="string", format="date", example="0830"),
     *              @OA\Property(property="pin", type="string", example="1234")
     *          ),
     *      ),
     *
     *      @OA\Response(
     *          response=200,
     *          description="Tarjeta activada correctamente"
     *      ),
     *      @OA\Response(
     *          response=404,
     *          description="El crédito no fue encontrado o no tiene permisos para acceder a él."
     *      )
     * )
     */
    public function activateCard(Request $request, $uuid)
    {
        try {
            $this->validate($request, [
                'card' => 'required|min:8|max:8',
                'expiration_date' => 'required',
                'pin' => 'required|min:4|max:4'
            ], [
                'card.required' => 'Los últimos 8 dígitos de la tarjeta son requeridos',
                'card.min' => 'Los últimos 8 dígitos de la tarjeta deben tener 8 caracteres',
                'card.max' => 'Los últimos 8 dígitos de la tarjeta deben tener 8 caracteres',
                'expiration_date.required' => 'La fecha de expiración es requerida',
                'pin.required' => 'El PIN es requerido',
                'pin.min' => 'El PIN debe tener 4 caracteres',
                'pin.max' => 'El PIN debe tener 4 caracteres'
            ]);

            $credit = Credit::where('UUID', $uuid)
                ->join('t_users', 't_card_cloud_credits.UserId', '=', 't_users.Id')
                ->select('t_card_cloud_credits.*')
                ->first();
            if (!$credit) {
                return response("El crédito no fue encontrado o no tiene permisos para acceder a él.", 404);
            }

            $client = new Client();
            $response = $client->request('POST', env('CARD_CLOUD_BASE_URL') . '/api/v1/card/validate_revolving_card', [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Bearer ' . CardCloudApi::getToken($request->attributes->get('jwt')->id),
                ],
                'json' => [
                    'card' => $request->card,
                    'pin' => $request->pin,
                    'moye' => $request->expiration_date,
                    'credit_id' => $credit->ExternalId
                ]
            ]);

            $decodedJson = json_decode($response->getBody(), true);

            return response()->json(['message' => 'Tarjeta activada correctamente']);
        } catch (RequestException $e) {
            if ($e->hasResponse()) {
                $statusCode = $e->getResponse()->getStatusCode();
                $responseBody = $e->getResponse()->getBody()->getContents();
                $decodedJson = json_decode($responseBody, true);
                $message = 'Error al asociar la tarjeta.';

                if (json_last_error() === JSON_ERROR_NONE) {
                    $message .= " " . $decodedJson['message'];
                }
                return response($message, $statusCode);
            } else {
                return response("Error al asociar la tarjeta.", 400);
            }
        } catch (\Exception $e) {
            return self::basicError($e->getMessage());
        }
    }


    /**
     * @OA\Get(
     *      path="/cardCloud/credits/{uuid}/virtual_card_price",
     *      summary="Obtener el precio de la tarjeta virtual",
     *      description="Obtiene el precio de la tarjeta virtual asociada al crédito especificado.",
     *      operationId="getCreditVirtualCardPrice",
     *      tags={"Card Cloud - Créditos"},
     *      security={{"bearerAuth": {}}},
     *
     *      @OA\Parameter(
     *          name="uuid",
     *          in="path",
     *          required=true,
     *          description="UUID del crédito",
     *          @OA\Schema(type="string")
     *      ),
     *
     *      @OA\Response(
     *          response=200,
     *          description="Precio de la tarjeta virtual obtenido exitosamente.",
     *          @OA\JsonContent(
     *              type="object",
     *              @OA\Property(property="price", type="number", format="float", example=10.99)
     *          )
     *      ),
     *      @OA\Response(
     *          response=404,
     *          description="El crédito no fue encontrado o no tiene permisos para acceder a él."
     *      )
     * )
     */

    public function virtualCardPrice(Request $request, $uuid)
    {
        try {
            $credit = Credit::where('UUID', $uuid)
                ->join('t_users', 't_card_cloud_credits.UserId', '=', 't_users.Id')
                ->select('t_card_cloud_credits.*')
                ->first();
            if (!$credit) {
                return response("El crédito no fue encontrado o no tiene permisos para acceder a él.", 404);
            }

            $subaccount = DB::connection('card_cloud')->table('subaccounts')->where('ExternalId', $credit->CompanyId)->first();
            if (!$subaccount) {
                return response("No se ha podido encontrar el costo de la tarjeta virtual. Intente de nuevo más tarde.", 404);
            }

            return response()->json(['price' => $subaccount->VirtualCardPrice]);
        } catch (\Exception $e) {
            return self::basicError($e->getMessage());
        }
    }


    public function buyVirtualCard(Request $request, $uuid)
    {
        try {

            $this->validate($request, [
                'months' => 'required|numeric|min:1'
            ], [
                'months.required' => 'Los meses de la tarjeta virtual son requeridos',
                'months.numeric' => 'Los meses de la tarjeta virtual deben ser un número',
                'months.min' => 'Los meses de la tarjeta virtual deben ser mayor a 0'
            ]);

            if ($request->has('auth_code')) {
                $this->validate($request, [
                    'auth_code' => 'required|min:6|max:6'
                ], [
                    'auth_code.required' => 'El código de autenticación es requerido',
                    'auth_code.min' => 'El código de autenticación debe tener 6 caracteres',
                    'auth_code.max' => 'El código de autenticación debe tener 6 caracteres'
                ]);
                GoogleAuth::authorized($request->attributes->get('jwt')->id, $request->auth_code);
            }

            $credit = Credit::where('UUID', $uuid)
                ->join('t_users', 't_card_cloud_credits.UserId', '=', 't_users.Id')
                ->select('t_card_cloud_credits.*')
                ->first();
            if (!$credit) {
                return response("El crédito no fue encontrado o no tiene permisos para acceder a él.", 404);
            }

            if ($credit->UserId != $request->attributes->get('jwt')->id) {
                return response("No tiene permisos para comprar una tarjeta virtual en este crédito.", 403);
            }


            $client = new Client();
            $response = $client->request('POST', env('CARD_CLOUD_BASE_URL') . '/api/v1/credit/' . $uuid . '/buy_virtual_card', [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Bearer ' . CardCloudApi::getToken($request->attributes->get('jwt')->id),
                ],
                'json' => [
                    'months' => $request->months,
                    'company' => $credit->CompanyId
                ]
            ]);

            $decodedJson = json_decode($response->getBody(), true);

            return response()->json(['message' => 'Tarjeta virtual activada correctamente.', 'card' => $decodedJson]);
        } catch (RequestException $e) {
            Log::error("Error al activar la tarjeta virtual: " . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'request' => $request->all(),
                'user_data' => $request->attributes->get('jwt')
            ]);
            return response()->json([
                'message' => 'Error al activar la tarjeta virtual.',
                'error' => $e->getMessage(),
                'response' => json_decode($e->getResponse()->getBody(), true)
            ], 500);
        } catch (\Exception $e) {
            return self::basicError($e->getMessage());
        }
    }
}
