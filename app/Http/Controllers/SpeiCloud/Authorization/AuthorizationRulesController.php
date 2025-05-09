<?php

namespace App\Http\Controllers\SpeiCloud\Authorization;

use App\Http\Controllers\Controller;
use App\Models\Backoffice\Business;
use App\Http\Middleware\ValidateEnvironmentAdminProfile;
use App\Models\Speicloud\Authorization\AuthorizationRules;
use App\Models\Speicloud\Authorization\RuleAuthorizers;
use App\Models\Speicloud\Authorization\RuleDestinationAccount;
use App\Models\Speicloud\Authorization\RuleProcessors;
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
     *     path="/api/speiCloud/authorization/rules",
     *     tags={"SpeiCloud Authorization Rules"},
     *     summary="Get authorization rules",
     *     description="Get authorization rules",
     *     operationId="getAuthorizationRules",
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Response(
     *         response=200,
     *         description="Authorization rules disabled",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="enabled", type="boolean", example=false),
     *             @OA\Property(
     *                 property="rules",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="ruleId", type="integer", example=1),
     *                     @OA\Property(property="ruleType", type="integer", example=1),
     *                     @OA\Property(property="ruleTypeName", type="string", example="SpeiOut"),
     *                     @OA\Property(property="amount", type="number", example=100000.00),
     *                     @OA\Property(property="dailyMovements", type="integer", example=0),
     *                     @OA\Property(property="monthlyMovements", type="integer", example=0),
     *                     @OA\Property(property="priority", type="integer", example=10),
     *                     @OA\Property(property="createdBy", type="string", example="00000000-1111-2222-3333-444444444444"),
     *                     @OA\Property(property="active", type="boolean", example=true),
     *                     @OA\Property(
     *                         property="processors",
     *                         type="array",
     *                         @OA\Items(
     *                             type="object",
     *                             @OA\Property(property="userId", type="string", example="00000000-1111-2222-3333-444444444444"),
     *                             @OA\Property(property="userName", type="string", example="User"),
     *                             @OA\Property(property="userEmail", type="string", example="admin@email.com")
     *                         )
     *                     ),
     *                     @OA\Property(
     *                         property="authorizers",
     *                         type="array",
     *                         @OA\Items(
     *                             type="object",
     *                             @OA\Property(property="userId", type="string", example="00000000-1111-2222-3333-444444444444"),
     *                             @OA\Property(property="userName", type="string", example="User"),
     *                             @OA\Property(property="userEmail", type="string", example="admin@email.com")
     *                         )
     *                     ),
     *                     @OA\Property(
     *                         property="sourceAccounts",
     *                         type="array",
     *                         @OA\Items(
     *                             type="object",
     *                             @OA\Property(property="accountNumber", type="string", example="001122334455667788"),
     *                             @OA\Property(property="company", type="string", example="Company Name")
     *                         )
     *                     ),
     *                     @OA\Property(
     *                         property="destinationAccounts",
     *                         type="array",
     *                         @OA\Items(
     *                             type="object",
     *                             @OA\Property(property="accountNumber", type="string", example="001122334455667788"),
     *                             @OA\Property(property="beneficiary", type="string", example="Beneficiary Name")
     *                         )
     *                     )
     *                 )
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=400,
     *         description="Error getting authorization rules",
     *         @OA\MediaType(mediaType="text/plain", @OA\Schema(type="string", example="Error getting authorization rules"))
     *     ),
     *
     *     @OA\Response(
     *         response=404,
     *         description="Business not found",
     *         @OA\MediaType(mediaType="text/plain", @OA\Schema(type="string", example="El ambiente no existe o no tienes acceso a él"))
     *     )
     * )
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
     *     path="/api/speiCloud/authorization/rules/enable",
     *     tags={"SpeiCloud Authorization Rules"},
     *     summary="Enable authorization rules",
     *     description="Enable authorization rules",
     *     operationId="enableAuthorizationRules",
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Response(
     *         response=200,
     *         description="Authorization rules enabled",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="enabled", type="boolean", example=true),
     *             @OA\Property(
     *                 property="rules",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="ruleId", type="integer", example=1),
     *                     @OA\Property(property="ruleType", type="integer", example=1),
     *                     @OA\Property(property="ruleTypeName", type="string", example="SpeiOut"),
     *                     @OA\Property(property="amount", type="number", example=100000.00),
     *                     @OA\Property(property="dailyMovements", type="integer", example=0),
     *                     @OA\Property(property="monthlyMovements", type="integer", example=0),
     *                     @OA\Property(property="priority", type="integer", example=10),
     *                     @OA\Property(property="createdBy", type="string", example="00000000-1111-2222-3333-444444444444"),
     *                     @OA\Property(property="active", type="boolean", example=true),
     *                     @OA\Property(
     *                         property="processors",
     *                         type="array",
     *                         @OA\Items(
     *                             type="object",
     *                             @OA\Property(property="userId", type="string", example="00000000-1111-2222-3333-444444444444"),
     *                             @OA\Property(property="userName", type="string", example="User"),
     *                             @OA\Property(property="userEmail", type="string", example="admin@email.com")
     *                         )
     *                     ),
     *                     @OA\Property(
     *                         property="authorizers",
     *                         type="array",
     *                         @OA\Items(
     *                             type="object",
     *                             @OA\Property(property="userId", type="string", example="00000000-1111-2222-3333-444444444444"),
     *                             @OA\Property(property="userName", type="string", example="User"),
     *                             @OA\Property(property="userEmail", type="string", example="admin@email.com")
     *                         )
     *                     ),
     *                     @OA\Property(
     *                         property="sourceAccounts",
     *                         type="array",
     *                         @OA\Items(
     *                             type="object",
     *                             @OA\Property(property="accountNumber", type="string", example="001122334455667788"),
     *                             @OA\Property(property="company", type="string", example="Company Name")
     *                         )
     *                     ),
     *                     @OA\Property(
     *                         property="destinationAccounts",
     *                         type="array",
     *                         @OA\Items(
     *                             type="object",
     *                             @OA\Property(property="accountNumber", type="string", example="001122334455667788"),
     *                             @OA\Property(property="beneficiary", type="string", example="Beneficiary Name")
     *                         )
     *                     )
     *                 )
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=404,
     *         description="Business not found",
     *         @OA\MediaType(mediaType="text/plain", @OA\Schema(type="string", example="El ambiente no existe o no tienes acceso a él"))
     *     ),
     *
     *     @OA\Response(
     *         response=500,
     *         description="Error enabling authorization rules",
     *         @OA\MediaType(mediaType="text/plain", @OA\Schema(type="string", example="No se pudo habilitar las reglas de autorización"))
     *     )
     * )
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
     *     path="/api/speiCloud/authorization/rules/disable",
     *     tags={"SpeiCloud Authorization Rules"},
     *     summary="Disable authorization rules",
     *     description="Disable authorization rules",
     *     operationId="disableAuthorizationRules",
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Response(
     *         response=200,
     *         description="Authorization rules disabled",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="enabled", type="boolean", example=false),
     *             @OA\Property(
     *                 property="rules",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="ruleId", type="integer", example=1),
     *                     @OA\Property(property="ruleType", type="integer", example=1),
     *                     @OA\Property(property="ruleTypeName", type="string", example="SpeiOut"),
     *                     @OA\Property(property="amount", type="number", example=100000.00),
     *                     @OA\Property(property="dailyMovements", type="integer", example=0),
     *                     @OA\Property(property="monthlyMovements", type="integer", example=0),
     *                     @OA\Property(property="priority", type="integer", example=10),
     *                     @OA\Property(property="createdBy", type="string", example="00000000-1111-2222-3333-444444444444"),
     *                     @OA\Property(property="active", type="boolean", example=true),
     *                     @OA\Property(
     *                         property="processors",
     *                         type="array",
     *                         @OA\Items(
     *                             type="object",
     *                             @OA\Property(property="userId", type="string", example="00000000-1111-2222-3333-444444444444"),
     *                             @OA\Property(property="userName", type="string", example="User"),
     *                             @OA\Property(property="userEmail", type="string", example="admin@email.com")
     *                         )
     *                     ),
     *                     @OA\Property(
     *                         property="authorizers",
     *                         type="array",
     *                         @OA\Items(
     *                             type="object",
     *                             @OA\Property(property="userId", type="string", example="00000000-1111-2222-3333-444444444444"),
     *                             @OA\Property(property="userName", type="string", example="User"),
     *                             @OA\Property(property="userEmail", type="string", example="admin@email.com")
     *                         )
     *                     ),
     *                     @OA\Property(
     *                         property="sourceAccounts",
     *                         type="array",
     *                         @OA\Items(
     *                             type="object",
     *                             @OA\Property(property="accountNumber", type="string", example="001122334455667788"),
     *                             @OA\Property(property="company", type="string", example="Company Name")
     *                         )
     *                     ),
     *                     @OA\Property(
     *                         property="destinationAccounts",
     *                         type="array",
     *                         @OA\Items(
     *                             type="object",
     *                             @OA\Property(property="accountNumber", type="string", example="001122334455667788"),
     *                             @OA\Property(property="beneficiary", type="string", example="Beneficiary Name")
     *                         )
     *                     )
     *                 )
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=404,
     *         description="Business not found",
     *         @OA\MediaType(mediaType="text/plain", @OA\Schema(type="string", example="El ambiente no existe o no tienes acceso a él"))
     *     ),
     *
     *     @OA\Response(
     *         response=500,
     *         description="Error disabling authorization rules",
     *         @OA\MediaType(mediaType="text/plain", @OA\Schema(type="string", example="No se pudo deshabilitar las reglas de autorización"))
     *     )
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

    /**
     *  @OA\Post(
     *      path="/api/speiCloud/authorization/rules/{ruleId}",
     *      tags={"SpeiCloud Authorization Rules"},
     *      summary="Update authorization rule",
     *      description="Update authorization rule",
     *      operationId="updateAuthorizationRule",
     *      security={{"bearerAuth":{}}},
     *
     *      @OA\RequestBody(
     *          required=true,
     *          @OA\JsonContent(
     *              required={"amount", "authorizers", "priority"},
     *              @OA\Property(property="amount", type="number", example=1000, description="Monto a partir del cual se aplica la regla"),
     *              @OA\Property(property="dailyMovements", type="integer", example=0, description="Número de movimientos diarios permitidos"),
     *              @OA\Property(property="monthlyMovements", type="integer", example=0, description="Número de movimientos mensuales permitidos"),
     *
     *              @OA\Property(
     *                  property="sourceAccounts",
     *                  type="array",
     *                  description="Cuentas de origen a las que se aplica la regla",
     *                  @OA\Items(
     *                      type="object",
     *                      @OA\Property(property="accountNumber", type="string", example="00000000-1111-2222-3333-444444444444"),
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
     *                      @OA\Property(property="accountNumber", type="string", example="00000000-1111-2222-3333-444444444444"),
     *                      @OA\Property(property="beneficiary", type="string", example="Cuenta de destino 1")
     *                  )
     *              ),
     *
     *              @OA\Property(
     *                  property="authorizers",
     *                  type="array",
     *                  @OA\Items(type="string", format="uuid"),
     *                  example={"00000000-1111-2222-3333-444444444444", "00000000-1111-2222-3333-444444444455"},
     *                  description="UUIDs de los usuarios autorizadores"
     *              ),
     *
     *              @OA\Property(
     *                  property="processors",
     *                  type="array",
     *                  @OA\Items(type="string", format="uuid"),
     *                  example={"00000000-1111-2222-3333-444444444466", "00000000-1111-2222-3333-444444444477"},
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
     *          description="Authorization rules disabled",
     *          @OA\JsonContent(
     *              type="object",
     *              @OA\Property(property="enabled", type="boolean", example=false),
     *              @OA\Property(
     *                  property="rules",
     *                  type="array",
     *                  @OA\Items(
     *                      type="object",
     *                      @OA\Property(property="ruleId", type="integer", example=1),
     *                      @OA\Property(property="ruleType", type="integer", example=1),
     *                      @OA\Property(property="ruleTypeName", type="string", example="SpeiOut"),
     *                      @OA\Property(property="amount", type="number", example=100000.00),
     *                      @OA\Property(property="dailyMovements", type="integer", example=0),
     *                      @OA\Property(property="monthlyMovements", type="integer", example=0),
     *                      @OA\Property(property="priority", type="integer", example=10),
     *                      @OA\Property(property="createdBy", type="string", example="00000000-1111-2222-3333-444444444444"),
     *                      @OA\Property(property="active", type="boolean", example=true),
     *
     *                      @OA\Property(
     *                          property="processors",
     *                          type="array",
     *                          @OA\Items(
     *                              type="object",
     *                              @OA\Property(property="userId", type="string", example="00000000-1111-2222-3333-444444444444"),
     *                              @OA\Property(property="userName", type="string", example="User"),
     *                              @OA\Property(property="userEmail", type="string", example="admin@email.com")
     *                          )
     *                      ),
     *
     *                      @OA\Property(
     *                          property="authorizers",
     *                          type="array",
     *                          @OA\Items(
     *                              type="object",
     *                              @OA\Property(property="userId", type="string", example="00000000-1111-2222-3333-444444444444"),
     *                              @OA\Property(property="userName", type="string", example="User"),
     *                              @OA\Property(property="userEmail", type="string", example="admin@email.com")
     *                          )
     *                      ),
     *
     *                      @OA\Property(
     *                          property="sourceAccounts",
     *                          type="array",
     *                          @OA\Items(
     *                              type="object",
     *                              @OA\Property(property="accountNumber", type="string", example="001122334455667788"),
     *                              @OA\Property(property="company", type="string", example="Company Name")
     *                          )
     *                      ),
     *
     *                      @OA\Property(
     *                          property="destinationAccounts",
     *                          type="array",
     *                          @OA\Items(
     *                              type="object",
     *                              @OA\Property(property="accountNumber", type="string", example="001122334455667788"),
     *                              @OA\Property(property="beneficiary", type="string", example="Beneficiary Name")
     *                          )
     *                      )
     *                  )
     *              )
     *          )
     *      )
     *  ),
     *
     *      @OA\Response(
     *          response=400,
     *          description="Error updating authorization rule",
     *          @OA\MediaType(mediaType="text/plain", @OA\Schema(type="string", example="Las reglas de autorización están inhabilitadas por lo que no se puede actualizar"))
     *     ),
     *
     *      @OA\Response(
     *          response=404,
     *          description="Business not found",
     *          @OA\MediaType(mediaType="text/plain", @OA\Schema(type="string", example="El ambiente no existe o no tienes acceso a él"))
     *      ),
     *
     *      @OA\Response(
     *         response=500,
     *         description="Error updating authorization rule",
     *         @OA\MediaType(mediaType="text/plain", @OA\Schema(type="string", example="No se pudo guardar el cambio de la regla de autorización"))
     *      )
     *  )
     */

    public function update(Request $request, $ruleId)
    {
        $business = Business::where('Id', $request->attributes->get('jwt')->businessId)->first();
        if (!$business) {
            return $this->basicError('El ambiente no existe o no tienes acceso a él', 404);
        }

        if ($business->AuthorizationRules == 0) {
            return $this->basicError('Las reglas de autorización están inhabilitadas', 400);
        }

        $rule = AuthorizationRules::where('Id', $ruleId)->where('BusinessId', $business->Id)->first();
        if (!$rule) {
            return $this->basicError('La regla de autorización no existe o no tienes acceso a ella', 404);
        }


        try {
            $this->validate($request, [
                'amount' => 'required|numeric|min:0',
                'authorizers' => 'required|array',
                'authorizers.*' => 'required|string|min:36|max:36',
                'priority' => 'required|integer|min:0|max:10000',
            ], [
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
                $this->validateProcessorUsers($business->Id, $request->input('processors'));
            }

            DB::beginTransaction();

            AuthorizationRules::where('Id', $rule->Id)->update([
                'Amount' => $request->input('amount'),
                'DailyMovementsLimit' => $request->input('dailyMovements') ? $request->input('dailyMovements') : 0,
                'MonthlyMovementsLimit' => $request->input('monthlyMovements') ? $request->input('monthlyMovements') : 0,
                'Priority' => $request->input('priority')
            ]);

            if ($request->input('processors') && count($request->input('processors')) > 0) {
                RuleProcessors::where('RuleId', $rule->Id)->delete();

                foreach ($request->input('processors') as $processor) {
                    RuleProcessors::create([
                        'RuleId' => $rule->Id,
                        'UserId' => $processor
                    ]);
                }
            }

            if ($request->input('sourceAccounts') && count($request->input('sourceAccounts')) > 0) {
                RuleSourceAccount::where('RuleId', $rule->Id)->delete();

                foreach ($request->input('sourceAccounts') as $account) {
                    RuleSourceAccount::create([
                        'RuleId' => $rule->Id,
                        'SourceAccount' => $account['accountNumber'],
                        'SourceAccountName' => $account['company']
                    ]);
                }
            }


            if ($request->input('destinationAccounts') && count($request->input('destinationAccounts')) > 0) {
                RuleDestinationAccount::where('RuleId', $rule->Id)->delete();

                foreach ($request->input('destinationAccounts') as $account) {
                    RuleDestinationAccount::create([
                        'RuleId' => $rule->Id,
                        'DestinationAccount' => $account['accountNumber'],
                        'DestinationAccountName' => $account['beneficiary']
                    ]);
                }
            }

            if ($request->input('authorizers') && count($request->input('authorizers')) > 0) {
                RuleAuthorizers::where('RuleId', $rule->Id)->delete();

                foreach ($request->input('authorizers') as $authorizer) {
                    RuleAuthorizers::create([
                        'RuleId' => $rule->Id,
                        'UserId' => $authorizer
                    ]);
                }
            }

            DB::commit();
            return $this->success([
                'enabled' => true,
                'rules' => self::getRules($business->Id)
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->basicError('No se pudo guardar la regla de autorización', 500);
        }
    }

    public static function getRules($businessId)
    {
        $rules = AuthorizationRules::where('BusinessId', $businessId)->orderBy('Priority', 'desc')->get();
        $rulesReturn = [];

        foreach ($rules as $rule) {
            $rulesReturn[] = self::ruleObject($rule);
        }

        return $rulesReturn;
    }

    public static function ruleObject($rule)
    {
        $processors = RuleProcessors::join('t_users', 't_speicloud_authorization_rules_processors.UserId', '=', 't_users.Id')
            ->where('t_speicloud_authorization_rules_processors.RuleId', $rule->Id)
            ->select(
                't_users.Id as userId',
                't_users.Name as userName',
                't_users.Email as userEmail'
            )
            ->get();
        $authorizers = RuleAuthorizers::join('t_users', 't_speicloud_authorization_rules_authorizers.UserId', '=', 't_users.Id')
            ->where('t_speicloud_authorization_rules_authorizers.RuleId', $rule->Id)
            ->select(
                't_users.Id as userId',
                't_users.Name as userName',
                't_users.Email as userEmail'
            )
            ->get();

        $sourceAccounts = RuleSourceAccount::where('RuleId', $rule->Id)
            ->select(
                'SourceAccount as accountNumber',
                'SourceAccountName as company'
            )->get();

        $destinationAccounts = RuleDestinationAccount::where('RuleId', $rule->Id)
            ->select(
                'DestinationAccount as accountNumber',
                'DestinationAccountName as beneficiary'
            )->get();

        return [
            'ruleId' => $rule->Id,
            'ruleType' => $rule->RuleType,
            'ruleTypeName' => $rule->RuleType == 1 ? 'SpeiOut' : 'SpeiIn',
            'amount' => $rule->Amount,
            'dailyMovements' => $rule->DailyMovementsLimit,
            'monthlyMovements' => $rule->MonthlyMovementsLimit,
            'priority' => $rule->Priority,
            'createdBy' => $rule->CreatedBy,
            'active' => $rule->Active == 1 ? true : false,
            'processors' => $processors,
            'authorizers' => $authorizers,
            'sourceAccounts' => $sourceAccounts,
            'destinationAccounts' => $destinationAccounts
        ];
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
     *                  property="sourceAccounts",
     *                  type="array",
     *                  description="Cuentas de origen a las que se aplica la regla",
     *                  @OA\Items(
     *                      type="object",
     *                      @OA\Property(property="accountNumber", type="string", example="00000000-1111-2222-3333-444444444444"),
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
     *                      @OA\Property(property="accountNumber", type="string", example="00000000-1111-2222-3333-444444444444"),
     *                      @OA\Property(property="beneficiary", type="string", example="Cuenta de destino 1")
     *                  )
     *              ),
     *
     *              @OA\Property(
     *                  property="authorizers",
     *                  type="array",
     *                  @OA\Items(type="string", format="uuid"),
     *                  example={"00000000-1111-2222-3333-444444444444", "00000000-1111-2222-3333-444444444455"},
     *                  description="UUIDs de los usuarios autorizadores"
     *              ),
     *
     *              @OA\Property(
     *                  property="processors",
     *                  type="array",
     *                  @OA\Items(type="string", format="uuid"),
     *                  example={"00000000-1111-2222-3333-444444444466", "00000000-1111-2222-3333-444444444477"},
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
     *          description="Authorization rules disabled",
     *          @OA\JsonContent(
     *              type="object",
     *              @OA\Property(property="enabled", type="boolean", example=false),
     *              @OA\Property(
     *                  property="rules",
     *                  type="array",
     *                  @OA\Items(
     *                      type="object",
     *                      @OA\Property(property="ruleId", type="integer", example=1),
     *                      @OA\Property(property="ruleType", type="integer", example=1),
     *                      @OA\Property(property="ruleTypeName", type="string", example="SpeiOut"),
     *                      @OA\Property(property="amount", type="number", example=100000.00),
     *                      @OA\Property(property="dailyMovements", type="integer", example=0),
     *                      @OA\Property(property="monthlyMovements", type="integer", example=0),
     *                      @OA\Property(property="priority", type="integer", example=10),
     *                      @OA\Property(property="createdBy", type="string", example="00000000-1111-2222-3333-444444444444"),
     *                      @OA\Property(property="active", type="boolean", example=true),
     *
     *                      @OA\Property(
     *                          property="processors",
     *                          type="array",
     *                          @OA\Items(
     *                              type="object",
     *                              @OA\Property(property="userId", type="string", example="00000000-1111-2222-3333-444444444444"),
     *                              @OA\Property(property="userName", type="string", example="User"),
     *                              @OA\Property(property="userEmail", type="string", example="admin@email.com")
     *                          )
     *                      ),
     *
     *                      @OA\Property(
     *                          property="authorizers",
     *                          type="array",
     *                          @OA\Items(
     *                              type="object",
     *                              @OA\Property(property="userId", type="string", example="00000000-1111-2222-3333-444444444444"),
     *                              @OA\Property(property="userName", type="string", example="User"),
     *                              @OA\Property(property="userEmail", type="string", example="admin@email.com")
     *                          )
     *                      ),
     *
     *                      @OA\Property(
     *                          property="sourceAccounts",
     *                          type="array",
     *                          @OA\Items(
     *                              type="object",
     *                              @OA\Property(property="accountNumber", type="string", example="001122334455667788"),
     *                              @OA\Property(property="company", type="string", example="Company Name")
     *                          )
     *                      ),
     *
     *                      @OA\Property(
     *                          property="destinationAccounts",
     *                          type="array",
     *                          @OA\Items(
     *                              type="object",
     *                              @OA\Property(property="accountNumber", type="string", example="001122334455667788"),
     *                              @OA\Property(property="beneficiary", type="string", example="Beneficiary Name")
     *                          )
     *                      )
     *                  )
     *              )
     *          )
     *      )
     *  )
     *
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
                $this->validateProcessorUsers($business->Id, $request->input('processors'));
            }

            DB::beginTransaction();

            $rule = AuthorizationRules::create([
                'BusinessId' => $business->Id,
                'RuleType' => $request->input('type'),
                'Amount' => $request->input('amount'),
                'DailyMovementsLimit' => $request->input('dailyMovements') ? $request->input('dailyMovements') : 0,
                'MonthlyMovementsLimit' => $request->input('monthlyMovements') ? $request->input('monthlyMovements') : 0,
                'Priority' => $request->input('priority'),
                'CreatedBy' => $request->attributes->get('jwt')->id,
                'Active' => 1
            ]);

            if ($request->input('processors') && count($request->input('processors')) > 0) {
                foreach ($request->input('processors') as $processor) {
                    RuleProcessors::create([
                        'RuleId' => $rule->Id,
                        'UserId' => $processor
                    ]);
                }
            }

            if ($request->input('sourceAccounts') && count($request->input('sourceAccounts')) > 0) {
                foreach ($request->input('sourceAccounts') as $account) {
                    RuleSourceAccount::create([
                        'RuleId' => $rule->Id,
                        'SourceAccount' => $account['accountNumber'],
                        'SourceAccountName' => $account['company']
                    ]);
                }
            }


            if ($request->input('destinationAccounts') && count($request->input('destinationAccounts')) > 0) {
                foreach ($request->input('destinationAccounts') as $account) {
                    RuleDestinationAccount::create([
                        'RuleId' => $rule->Id,
                        'DestinationAccount' => $account['accountNumber'],
                        'DestinationAccountName' => $account['beneficiary']
                    ]);
                }
            }

            if ($request->input('authorizers') && count($request->input('authorizers')) > 0) {
                foreach ($request->input('authorizers') as $authorizer) {
                    RuleAuthorizers::create([
                        'RuleId' => $rule->Id,
                        'UserId' => $authorizer
                    ]);
                }
            }

            DB::commit();
            return $this->success([
                'enabled' => true,
                'rules' => self::getRules($business->Id)
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->basicError('No se pudo guardar la regla de autorización.'. ((env('APP_ENV') == 'production') ? '' : $e->getMessage()), 500);
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

    /**
     *  @OA\Post(
     *      path="/api/speiCloud/authorization/rules/{ruleId}/enable",
     *      tags={"SpeiCloud Authorization Rules"},
     *      summary="Disable authorization rule",
     *      description="Disable authorization rule",
     *      operationId="enableAuthorizationRule",
     *      security={{"bearerAuth":{}}},
     *
     *      @OA\Response(
     *          response=200,
     *          description="Authorization rule disabled",
     *          @OA\JsonContent(
     *              type="object",
     *              @OA\Property(property="enabled", type="boolean", example=false),
     *              @OA\Property(property="rules", type="array", @OA\Items(type="object"))
     *          )
     *      ),
     *
     *      @OA\Response(
     *         response=400,
     *         description="Error disabling authorization rules",
     *         @OA\MediaType(mediaType="text/plain", @OA\Schema(type="string", example="Las reglas de autorización están inhabilitadas"))
     *      ),
     *
     *      @OA\Response(
     *         response=404,
     *         description="Business not found",
     *         @OA\MediaType(mediaType="text/plain", @OA\Schema(type="string", example="El ambiente no existe o no tienes acceso a él"))
     *      ),
     *
     *      @OA\Response(
     *         response=500,
     *         description="Error disabling authorization rules",
     *         @OA\MediaType(mediaType="text/plain", @OA\Schema(type="string", example="No se pudo deshabilitar las reglas de autorización"))
     *      )
     *  )
     */

    public function enable(Request $request, $ruleId)
    {
        try {
            $business = Business::where('Id', $request->attributes->get('jwt')->businessId)->first();
            if (!$business) {
                return $this->basicError('El ambiente no existe o no tienes acceso a él', 404);
            }

            if ($business->AuthorizationRules == 0) {
                return $this->basicError('Las reglas de autorización están inhabilitadas', 400);
            }

            $rule = AuthorizationRules::where('Id', $ruleId)->where('BusinessId', $business->Id)->first();
            if (!$rule) {
                return $this->basicError('La regla de autorización no existe o no tienes acceso a ella', 404);
            }

            DB::beginTransaction();
            AuthorizationRules::where('Id', $rule->Id)->update([
                'Active' => 1
            ]);
            DB::commit();
            return $this->success([
                'enabled' => true,
                'rules' => self::getRules($business->Id)
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->basicError('No se pudo habilitar la regla de autorización', 500);
        }
    }


    /**
     * @OA\Post(
     *      path="/api/speiCloud/authorization/rules/{ruleId}/disable",
     *      tags={"SpeiCloud Authorization Rules"},
     *      summary="Disable authorization rule",
     *      description="Disable authorization rule",
     *      operationId="disableAuthorizationRule",
     *      security={{"bearerAuth":{}}},
     *
     *      @OA\Response(
     *          response=200,
     *          description="Authorization rule disabled",
     *          @OA\JsonContent(
     *              type="object",
     *              @OA\Property(property="enabled", type="boolean", example=false),
     *              @OA\Property(property="rules", type="array", @OA\Items(type="object"))
     *          )
     *      ),
     *
     *      @OA\Response(
     *         response=400,
     *         description="Error disabling authorization rules",
     *         @OA\MediaType(mediaType="text/plain", @OA\Schema(type="string", example="Las reglas de autorización están inhabilitadas"))
     *      ),
     *
     *      @OA\Response(
     *         response=404,
     *         description="Business not found",
     *         @OA\MediaType(mediaType="text/plain", @OA\Schema(type="string", example="El ambiente no existe o no tienes acceso a él"))
     *      ),
     *
     *      @OA\Response(
     *         response=500,
     *         description="Error disabling authorization rules",
     *         @OA\MediaType(mediaType="text/plain", @OA\Schema(type="string", example="No se pudo deshabilitar las reglas de autorización"))
     *      )
     *  )
     */
    public function disable(Request $request, $ruleId)
    {
        try {
            $business = Business::where('Id', $request->attributes->get('jwt')->businessId)->first();
            if (!$business) {
                return $this->basicError('El ambiente no existe o no tienes acceso a él', 404);
            }

            if ($business->AuthorizationRules == 0) {
                return $this->basicError('Las reglas de autorización están inhabilitadas', 400);
            }

            $rule = AuthorizationRules::where('Id', $ruleId)->where('BusinessId', $business->Id)->first();
            if (!$rule) {
                return $this->basicError('La regla de autorización no existe o no tienes acceso a ella', 404);
            }
            DB::beginTransaction();
            AuthorizationRules::where('Id', $rule->Id)->update([
                'Active' => 0
            ]);
            DB::commit();
            return $this->success([
                'enabled' => true,
                'rules' => self::getRules($business->Id)
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->basicError('No se pudo deshabilitar la regla de autorización', 500);
        }
    }
}
