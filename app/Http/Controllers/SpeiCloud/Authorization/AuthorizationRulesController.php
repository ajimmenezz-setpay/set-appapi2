<?php

namespace App\Http\Controllers\SpeiCloud\Authorization;

use App\Http\Controllers\Controller;
use App\Models\Backoffice\Business;
use App\Http\Middleware\ValidateEnvironmentAdminProfile;
use App\Models\Speicloud\Authorization\AuthorizationRules;
use App\Models\Speicloud\Authorization\RuleSourceAccount;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AuthorizationRulesController extends Controller
{
    public function __construct()
    {
        $this->middleware(ValidateEnvironmentAdminProfile::class);
    }

    /**
     * @OA\Get(
     *  path="/api/speiCloud/authorization/rules",
     *  tags={"SpeiCloud Authorization Rules"},
     *  summary="Get authorization rules",
     *  description="Get authorization rules",
     *  operationId="getAuthorizationRules",
     *  security={{"bearerAuth":{}}},
     *
     *  @OA\Response(
     *      response=200,
     *      description="Authorization rules",
     *      @OA\JsonContent(
     *          @OA\Property(property="enabled", type="boolean", example=true),
     *          @OA\Property(property="rules", type="array", @OA\Items(type="object"))
     *      )
     *  ),
     *
     *  @OA\Response(
     *      response=400,
     *      description="Error getting authorization rules",
     *      @OA\MediaType(mediaType="text/plain", @OA\Schema(type="string", example="Error getting authorization rules"))
     *  ),
     *
     *  @OA\Response(
     *     response=404,
     *     description="Business not found",
     *     @OA\MediaType(mediaType="text/plain", @OA\Schema(type="string", example="El ambiente no existe o no tienes acceso a él"))
     *  )
     *)
     */

    public function rules(Request $request)
    {
        try {
            $business = Business::where('Id', $request->attributes->get('jwt')->businessId)->first();
            if (!$business) {
                return $this->basicError('El ambiente no existe o no tienes acceso a él', 404);
            }


            return $this->success([
                'enabled' => $business->AuthorizationRules == 1 ? true : false,
                'rules' => $business->AuthorizationRules == 1 ? self::getRules($business->Id) : []
            ]);
        } catch (\Exception $e) {
            return $this->basicError('No se pudo obtener la información de las reglas de autorización', 500);
        }
    }


    /**
     * @OA\Post(
     *      path="/api/speiCloud/authorization/rules/enable",
     *      tags={"SpeiCloud Authorization Rules"},
     *      summary="Enable authorization rules",
     *      description="Enable authorization rules",
     *      operationId="enableAuthorizationRules",
     *      security={{"bearerAuth":{}}},
     *
     *      @OA\Response(
     *          response=200,
     *          description="Authorization rules enabled",
     *          @OA\JsonContent(
     *             @OA\Property(property="enabled", type="boolean", example=true),
     *              @OA\Property(property="rules", type="array", @OA\Items(type="object"))
     *          )
     *      ),
     *
     *      @OA\Response(
     *          response=401,
     *          description="Unauthorized",
     *          @OA\MediaType(mediaType="text/plain", @OA\Schema(type="string", example="No tienes permisos para acceder a este recurso"))
     *      ),
     *
     *      @OA\Response(
     *          response=404,
     *          description="Business not found",
     *          @OA\MediaType(mediaType="text/plain", @OA\Schema(type="string", example="El ambiente no existe o no tienes acceso a él"))
     *      ),
     *
     *      @OA\Response(
     *          response=500,
     *          description="Error enabling authorization rules",
     *          @OA\MediaType(mediaType="text/plain", @OA\Schema(type="string", example="No se pudo habilitar las reglas de autorización"))
     *      )
     *  )
     *
     */
    public function enableRules(Request $request)
    {
        try {
            $business = Business::where('Id', $request->attributes->get('jwt')->businessId)->first();
            if (!$business) {
                return $this->basicError('El ambiente no existe o no tienes acceso a él', 404);
            }

            Business::where('Id', $business->Id)->update(['AuthorizationRules' => 1]);

            return $this->success([
                'enabled' => true,
                'rules' => self::getRules($business->Id)
            ]);
        } catch (\Exception $e) {
            return $this->basicError('No se pudo habilitar las reglas de autorización', 500);
        }
    }


    /**
     * @OA\Post(
     *      path="/api/speiCloud/authorization/rules/disable",
     *      tags={"SpeiCloud Authorization Rules"},
     *      summary="Disable authorization rules",
     *      description="Disable authorization rules",
     *      operationId="disableAuthorizationRules",
     *      security={{"bearerAuth":{}}},
     *
     *      @OA\Response(
     *          response=200,
     *          description="Authorization rules disabled",
     *          @OA\JsonContent(
     *              @OA\Property(property="enabled", type="boolean", example=false),
     *              @OA\Property(property="rules", type="array", @OA\Items(type="object"))
     *          )
     *      ),
     *
     *      @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *          @OA\MediaType(mediaType="text/plain", @OA\Schema(type="string", example="No tienes permisos para acceder a este recurso"))
     *     ),
     *
     *      @OA\Response(
     *          response=404,
     *          description="Business not found",
     *          @OA\MediaType(mediaType="text/plain", @OA\Schema(type="string", example="El ambiente no existe o no tienes acceso a él"))
     *      ),
     *
     *      @OA\Response(
     *          response=500,
     *          description="Error disabling authorization rules",
     *          @OA\MediaType(mediaType="text/plain", @OA\Schema(type="string", example="No se pudo deshabilitar las reglas de autorización"))
     *      )
     * )
     */
    public function disableRules(Request $request)
    {
        try {
            $business = Business::where('Id', $request->attributes->get('jwt')->businessId)->first();
            if (!$business) {
                return $this->basicError('El ambiente no existe o no tienes acceso a él', 404);
            }

            Business::where('Id', $business->Id)->update(['AuthorizationRules' => 0]);

            return $this->success([
                'enabled' => true,
                'rules' => []
            ]);
        } catch (\Exception $e) {
            return $this->basicError('No se pudo deshabilitar las reglas de autorización', 500);
        }
    }

    public static function getRules($businessId)
    {


        return [];
    }


    /**
     *  @OA\Post(
     *      path="/api/speiCloud/authorization/rules",
     *      tags={"SpeiCloud Authorization Rules"},
     *      summary="Create authorization rule",
     *      description="Create authorization rule",
     *      operationId="createAuthorizationRule",
     *      security={{"bearerAuth":{}}},
     *
     *      @OA\RequestBody(
     *          required=true,
     *          @OA\JsonContent(
     *              required={"type", "amount", "authorizers", "priority"},
     *              @OA\Property(property="type", type="integer", example=1, description="1: SpeiOut, 2: SpeiIn"),
     *              @OA\Property(property="amount", type="number", example=1000, description="Monto a partir del cual se aplica la regla"),
     *              @OA\Property(property="dailyMovements", type="integer", example=0, description="Número de movimientos diarios permitidos"),
     *              @OA\Property(property="monthlyMovements", type="integer", example=0, description="Número de movimientos mensuales permitidos"),
     *
     *              @OA\Property(
     *                  property="originAccounts",
     *                  type="array",
     *                  description="Cuentas de origen a las que se aplica la regla",
     *                  @OA\Items(
     *                      type="object",
     *                      @OA\Property(property="accountNumber", type="string", example="uuid1"),
     *                      @OA\Property(property="company", type="string", example="Cuenta de origen 1")
     *                  )
     *              ),
     *
     *              @OA\Property(
     *                  property="destinationAccounts",
     *                  type="array",
     *                  description="Cuentas de destino a las que se aplica la regla",
     *                  @OA\Items(
     *                      type="object",
     *                      @OA\Property(property="accountNumber", type="string", example="uuid1"),
     *                      @OA\Property(property="beneficiary", type="string", example="Cuenta de destino 1")
     *                  )
     *              ),
     *
     *              @OA\Property(
     *                  property="authorizers",
     *                  type="array",
     *                  @OA\Items(type="string", format="uuid"),
     *                  example={"uuid1", "uuid2"},
     *                  description="UUIDs de los usuarios autorizadores"
     *              ),
     *
     *              @OA\Property(
     *                  property="processors",
     *                  type="array",
     *                  @OA\Items(type="string", format="uuid"),
     *                  example={"uuid1", "uuid2"},
     *                  description="UUIDs de los usuarios procesadores"
     *              ),
     *
     *              @OA\Property(
     *                  property="priority",
     *                  type="integer",
     *                  example=1,
     *                  description="Prioridad de la regla"
     *              )
     *          )
     *      ),
     *
     *      @OA\Response(
     *          response=200,
     *          description="Authorization rule created",
     *          @OA\JsonContent(
     *              @OA\Property(property="enabled", type="boolean", example=true),
     *              @OA\Property(property="rules", type="array", @OA\Items(type="object"))
     *          )
     *      )
     *  )
     */

    public function store(Request $request)
    {
        $business = Business::where('Id', $request->attributes->get('jwt')->businessId)->first();
        if (!$business) {
            return $this->basicError('El ambiente no existe o no tienes acceso a él', 404);
        }

        if ($business->AuthorizationRules == 0) {
            return $this->basicError('Las reglas de autorización están inhabilitadas', 400);
        }


        try {
            $this->validate($request, [
                'type' => 'required|in:1,2',
                'amount' => 'required|numeric|min:0',
                'authorizers' => 'required|array',
                'authorizers.*' => 'required|string|min:36|max:36',
                'priority' => 'required|integer|min:0|max:10000',
            ], [
                'type.required' => 'El tipo de regla es requerido (type)',
                'type.in' => 'El tipo de regla no es válido. Elija entre SpeiOut o SpeiIn',
                'amount.required' => 'El monto es requerido (amount)',
                'amount.min' => 'El monto mínimo es 0',
                'authorizers.*.string' => 'El autoriza debe ser un UUID',
                'authorizers.*.min' => 'El autoriza debe ser un UUID',
                'authorizers.*.max' => 'El autoriza debe ser un UUID',
                'priority.integer' => 'La prioridad debe ser un número entero',
                'priority.min' => 'La prioridad debe ser un número mayor o igual a 0',
                'priority.max' => 'La prioridad debe ser un número menor o igual a 10000'
            ]);

            $this->validateAuthorizers($business->Id, $request->input('authorizers'));

            if ($request->input('processors') && count($request->input('processors')) > 0) {
                $this->validateProcessorUsers($business->Id, $this->input('processors'));
            }

            DB::beginTransaction();

            $rule = AuthorizationRules::create([
                'RuleType' => $request->input('type'),
                'Amount' => $request->input('amount'),
                'DailyMovementsLimit' => $request->input('dailyMovements') ? $request->input('dailyMovements') : 0,
                'MonthlyMovementsLimit' => $request->input('monthlyMovements') ? $request->input('monthlyMovements') : 0,
                'Priority' => $request->input('priority'),
                'CreatedBy' => $request->attributes->get('jwt')->id
            ]);

            if ($request->input('originAccounts') && count($request->input('originAccounts')) > 0) {
                foreach ($request->input('originAccounts') as $account) {
                    RuleSourceAccount::create([
                        'RuleId' => $rule->Id,
                        'SourceAccount' => $account
                    ]);
                }
            }

            $rule = [
                'type' => $request->input('type'),
                'amount' => $request->input('amount'),
                'dailyMovements' => $request->input('dailyMovements') ? $request->input('dailyMovements') : 0,
                'monthlyMovements' => $request->input('monthlyMovements') ? $request->input('monthlyMovements') : 0,
                'originAccounts' => $request->input('originAccounts') ? $request->input('originAccounts') : [],
                'destinationAccounts' => $request->input('destinationAccounts') ? $request->input('destinationAccounts') : [],
                'authorizers' => $request->input('authorizers'),
                'processors' => $request->input('processors') ? $request->input('processors') : [],
            ];

            return $this->success([
                'enabled' => true,
                'rules' => self::getRules($business->Id)
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->basicError('No se pudo guardar las reglas de autorización. ' . $e->getMessage(), 400);
        }
    }

    public function validateProcessorUsers($businessId, $processors)
    {
        $users = AuthorizingUsers::users($businessId);
        $processorUsers = $users->pluck('Id')->toArray();

        foreach ($processors as $processor) {
            if (!in_array($processor, $processorUsers)) {
                throw new \Exception('El usuario no es un procesador válido. Revisa los usuarios seleccionados');
            }
        }
    }

    public function validateAuthorizers($businessId, $authorizers)
    {
        $users = AuthorizingUsers::authorizers($businessId);
        $authorizerUsers = $users->pluck('Id')->toArray();

        foreach ($authorizers as $authorizer) {
            if (!in_array($authorizer, $authorizerUsers)) {
                throw new \Exception('El usuario no es un autorizador válido. Revisa los autorizadores seleccionados');
            }
        }
    }
}
