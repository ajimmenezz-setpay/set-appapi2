<?php

namespace App\Http\Controllers\SpeiCloud\Authorization;

use App\Http\Controllers\Controller;
use App\Http\Middleware\ValidateEnvironmentAdminProfile;
use Illuminate\Http\Request;
use App\Models\Backoffice\Business;
use App\Models\Speicloud\Authorization\AuthorizingUsers as AuthorizationAuthorizingUsers;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class AuthorizingUsers extends Controller
{
    public function __construct()
    {
        $this->middleware(ValidateEnvironmentAdminProfile::class);
    }

    /**
     * @OA\Get(
     *  path="/api/speiCloud/authorization/authorizing-users",
     *  tags={"SpeiCloud Authorization Users"},
     *  summary="Get authorizing users",
     *  description="Get authorizing users",
     *  operationId="getAuthorizingUsers",
     *  security={{"bearerAuth":{}}},
     *
     *  @OA\Response(
     *      response=200,
     *      description="Authorizing users",
     *      @OA\JsonContent(
     *          type="object",
     *          @OA\Property(
     *              property="authorizers",
     *              type="array",
     *              @OA\Items(
     *                  type="object",
     *                  @OA\Property(property="Id", type="string", example="82438ecc-829a-4c2d-9df8-c7babbd32374"),
     *                  @OA\Property(property="Name", type="string", example="Administrador"),
     *                  @OA\Property(property="Lastname", type="string", example="SET"),
     *                  @OA\Property(property="Email", type="string", example="admin@set.lat"),
     *                  @OA\Property(property="ProfileName", type="string", example="Administrador STP"),
     *                  @OA\Property(property="CreatedBy", type="string", example="Alonso All")
     *              )
     *          ),
     *          @OA\Property(
     *              property="users",
     *              type="array",
     *              @OA\Items(
     *                  type="object",
     *                  @OA\Property(property="Id", type="string", example="82438ecc-829a-4c2d-9df8-c7babbd32372"),
     *                  @OA\Property(property="Name", type="string", example="Alonso All"),
     *                  @OA\Property(property="Lastname", type="string", example="Jimenez H"),
     *                  @OA\Property(property="Email", type="string", example="ajimmenezzz@gmail.com"),
     *                  @OA\Property(property="ProfileName", type="string", example="Administrador STP")
     *              )
     *          )
     *      )
     *  ),
     *
     *  @OA\Response(
     *      response=400,
     *      description="Error getting authorizing users",
     *      @OA\MediaType(mediaType="text/plain", @OA\Schema(type="string", example="Error getting authorizing users"))
     *  ),
     *
     *  @OA\Response(
     *      response=404,
     *      description="El ambiente no existe o no tienes acceso a él",
     *      @OA\MediaType(mediaType="text/plain", @OA\Schema(type="string", example="El ambiente no existe o no tienes acceso a él"))
     *  )
     *)
     */
    public function index(Request $request)
    {
        try {
            $business = Business::where('Id', $request->attributes->get('jwt')->businessId)->first();
            if (!$business) {
                return $this->basicError('El ambiente no existe o no tienes acceso a él', 404);
            }

            return $this->success(self::allUsers($business->Id));
        } catch (\Exception $e) {
            return $this->basicError('No se pudo obtener la información de los usuarios autorizadores');
        }
    }

    public static function allUsers($businessId)
    {
        $authorizers = self::authorizers($businessId);
        $authorizersId = [];
        foreach ($authorizers as $authorizer) {
            $authorizersId[] = $authorizer->Id;
        }

        return [
            'authorizers' => $authorizers,
            'users' => self::users($businessId, $authorizersId),
        ];
    }

    public static function users($businessId, $authorizers = [])
    {
        return User::join('cat_profile', 'cat_profile.Id', '=', 't_users.ProfileId')
            ->where('t_users.BusinessId', $businessId)
            ->where('t_users.Active', 1)
            ->whereIn('t_users.ProfileId', [5, 7])
            ->whereNotIn('t_users.Id', $authorizers)
            ->orderBy('t_users.Name', 'asc')
            ->orderBy('t_users.Lastname', 'asc')
            ->get([
                't_users.Id',
                't_users.Name',
                't_users.Lastname',
                't_users.Email',
                'cat_profile.Name as ProfileName',
            ]);
    }

    public static function authorizers($businessId)
    {
        return User::join('t_backoffice_speicloud_authorizing_users', 't_backoffice_speicloud_authorizing_users.UserId', '=', 't_users.Id')
            ->join('t_users as t2', 't2.Id', '=', 't_backoffice_speicloud_authorizing_users.CreatedBy')
            ->join('cat_profile', 'cat_profile.Id', '=', 't_users.ProfileId')
            ->where('t_backoffice_speicloud_authorizing_users.BusinessId', $businessId)
            ->where('t_users.Active', 1)
            ->where('t_backoffice_speicloud_authorizing_users.Active', 1)
            ->whereIn('t_users.ProfileId', [5, 7])
            ->orderBy('t_users.Name', 'asc')
            ->orderBy('t_users.Lastname', 'asc')
            ->get([
                't_users.Id',
                't_users.Name',
                't_users.Lastname',
                't_users.Email',
                'cat_profile.Name as ProfileName',
                't2.Name as CreatedBy',
            ]);
    }

    /**
     * @OA\Post(
     *  path="/api/speiCloud/authorization/authorizing-users",
     *  tags={"SpeiCloud Authorization Users"},
     *  summary="Create authorizing user",
     *  description="Create authorizing user",
     *  operationId="createAuthorizingUser",
     *  security={{"bearerAuth":{}}},
     *
     *  @OA\RequestBody(
     *      required=true,
     *      @OA\JsonContent(
     *          @OA\Property(property="users_id", type="array", @OA\Items(type="string", example="82438ecc-829a-4c2d-9df8-c7babbd32372")),
     *      )
     *  ),
     *
     *  @OA\Response(
     *      response=200,
     *      description="Authorizing users",
     *      @OA\JsonContent(
     *          type="object",
     *          @OA\Property(
     *              property="authorizers",
     *              type="array",
     *              @OA\Items(
     *                  type="object",
     *                  @OA\Property(property="Id", type="string", example="82438ecc-829a-4c2d-9df8-c7babbd32374"),
     *                  @OA\Property(property="Name", type="string", example="Administrador"),
     *                  @OA\Property(property="Lastname", type="string", example="SET"),
     *                  @OA\Property(property="Email", type="string", example="admin@set.lat"),
     *                  @OA\Property(property="ProfileName", type="string", example="Administrador STP"),
     *                  @OA\Property(property="CreatedBy", type="string", example="Alonso All")
     *              )
     *          ),
     *          @OA\Property(
     *              property="users",
     *              type="array",
     *              @OA\Items(
     *                  type="object",
     *                  @OA\Property(property="Id", type="string", example="82438ecc-829a-4c2d-9df8-c7babbd32372"),
     *                  @OA\Property(property="Name", type="string", example="Alonso All"),
     *                  @OA\Property(property="Lastname", type="string", example="Jimenez H"),
     *                  @OA\Property(property="Email", type="string", example="ajimmenezzz@gmail.com"),
     *                  @OA\Property(property="ProfileName", type="string", example="Administrador STP")
     *              )
     *          )
     *      )
     *  ),
     *
     *  @OA\Response(
     *     response=400,
     *    description="Error creating authorizing user",
     *     @OA\MediaType(mediaType="text/plain", @OA\Schema(type="string", example="Error creating authorizing user"))
     *  ),
     *
     *  @OA\Response(
     *      response=404,
     *      description="El ambiente no existe o no tienes acceso a él",
     *      @OA\MediaType(mediaType="text/plain", @OA\Schema(type="string", example="El ambiente no existe o no tienes acceso a él"))
     *  ),
     *
     *  @OA\Response(
     *      response=500,
     *     description="Error creating authorizing user",
     *     @OA\MediaType(mediaType="text/plain", @OA\Schema(type="string", example="Error creating authorizing user"))
     *  ),
     *
     *  @OA\Response(
     *      response=401,
     *      description="Unauthorized",
     *      @OA\MediaType(mediaType="text/plain", @OA\Schema(type="string", example="Unauthorized"))
     *  )
     * )
     */

    public function store(Request $request)
    {
        try {

            $this->validate(
                $request,
                [
                    'users_id' => 'required|array'
                ],
                [
                    'users_id.required' => 'El campo user_id es obligatorio',
                    'users_id.array' => 'El campo user_id debe ser un arreglo'
                ]
            );

            DB::beginTransaction();

            $business = Business::where('Id', $request->attributes->get('jwt')->businessId)->first();
            if (!$business) {
                return $this->basicError('El ambiente no existe o no tienes acceso a él', 404);
            }

            foreach ($request->input('users_id') as $userId) {
                $user = User::where('Id', $userId)->first();
                if (!$user) {
                    throw new \Exception('Algun usuario no existe o no tienes acceso a él');
                }

                $userExists = AuthorizationAuthorizingUsers::where('BusinessId', $business->Id)
                    ->where('UserId', $user->Id)
                    ->first();
                if ($userExists && $userExists->Active == 1) {
                    throw new \Exception('Alguno de los ususarios seleccionados ya existe como autorizador');
                } else if ($userExists && $userExists->Active == 0) {
                    $userExists->Active = 1;
                    $userExists->save();
                } else {
                    AuthorizationAuthorizingUsers::create([
                        'BusinessId' => $business->Id,
                        'UserId' => $user->Id,
                        'CreatedBy' => $request->attributes->get('jwt')->id,
                    ]);
                }
            }

            DB::commit();

            return $this->success(self::allUsers($business->Id));
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->basicError($e->getMessage());
        }
    }

    /**
     * @OA\Delete(
     *  path="/api/speiCloud/authorization/authorizing-users",
     *  tags={"SpeiCloud Authorization Users"},
     *  summary="Delete authorizing user",
     *  description="Delete authorizing user",
     *  operationId="deleteAuthorizingUser",
     *  security={{"bearerAuth":{}}},
     *
     *  @OA\RequestBody(
     *      required=true,
     *     @OA\JsonContent(
     *         @OA\Property(property="user_id", type="string", example="12345678-1234-1234-1234-123456789012"),
     *     )
     *  ),
     *
     *  @OA\Response(
     *      response=200,
     *      description="Authorizing users",
     *      @OA\JsonContent(
     *          type="object",
     *          @OA\Property(
     *              property="authorizers",
     *              type="array",
     *              @OA\Items(
     *                  type="object",
     *                  @OA\Property(property="Id", type="string", example="82438ecc-829a-4c2d-9df8-c7babbd32374"),
     *                  @OA\Property(property="Name", type="string", example="Administrador"),
     *                  @OA\Property(property="Lastname", type="string", example="SET"),
     *                  @OA\Property(property="Email", type="string", example="admin@set.lat"),
     *                  @OA\Property(property="ProfileName", type="string", example="Administrador STP"),
     *                  @OA\Property(property="CreatedBy", type="string", example="Alonso All")
     *              )
     *          ),
     *          @OA\Property(
     *              property="users",
     *              type="array",
     *              @OA\Items(
     *                  type="object",
     *                  @OA\Property(property="Id", type="string", example="82438ecc-829a-4c2d-9df8-c7babbd32372"),
     *                  @OA\Property(property="Name", type="string", example="Alonso All"),
     *                  @OA\Property(property="Lastname", type="string", example="Jimenez H"),
     *                  @OA\Property(property="Email", type="string", example="ajimmenezzz@gmail.com"),
     *                  @OA\Property(property="ProfileName", type="string", example="Administrador STP")
     *              )
     *          )
     *      )
     *  ),
     *
     *  @OA\Response(
     *      response=400,
     *      description="Error deleting authorizing user",
     *      @OA\MediaType(mediaType="text/plain", @OA\Schema(type="string", example="No se pudo eliminar el usuario autorizador"))
     *  ),
     *
     *  @OA\Response(
     *      response=404,
     *      description="El ambiente no existe o no tienes acceso a él",
     *      @OA\MediaType(mediaType="text/plain", @OA\Schema(type="string", example="El ambiente no existe o no tienes acceso a él"))
     *  ),
     *
     *  @OA\Response(
     *      response=500,
     *      description="Error deleting authorizing user",
     *      @OA\MediaType(mediaType="text/plain", @OA\Schema(type="string", example="Error deleting authorizing user"))
     *  ),
     *
     *  @OA\Response(
     *      response=401,
     *      description="Unauthorized",
     *      @OA\MediaType(mediaType="text/plain", @OA\Schema(type="string", example="Unauthorized"))
     *  )
     * )
     */

    public function delete(Request $request)
    {
        try {
            $this->validate(
                $request,
                [
                    'user_id' => 'required|string',
                ],
                [
                    'user_id.required' => 'El campo user_id es obligatorio',
                    'user_id.string' => 'El campo user_id debe ser una cadena de texto',
                ]
            );

            DB::beginTransaction();

            $business = Business::where('Id', $request->attributes->get('jwt')->businessId)->first();
            if (!$business) {
                return $this->basicError('El ambiente no existe o no tienes acceso a él', 404);
            }

            $user = User::where('Id', $request->input('user_id'))->first();
            if (!$user) {
                return $this->basicError('El usuario no existe o no tienes acceso a él', 404);
            }

            $userExists = AuthorizationAuthorizingUsers::where('BusinessId', $business->Id)
                ->where('UserId', $user->Id)
                ->first();
            if (!$userExists) {
                return $this->basicError('El usuario no existe como autorizador', 400);
            }

            $userExists->Active = 0;
            $userExists->save();

            DB::commit();
            return $this->success([
                'authorizers' => self::authorizers($business->Id),
                'users' => self::users($business->Id),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->basicError('No se pudo eliminar el usuario autorizador');
        }
    }

    /**
     * @OA\Get(
     *      path="/api/speiCloud/authorization/users/processors",
     *      tags={"SpeiCloud Authorization Processor Users"},
     *      summary="Get processor users",
     *      description="Get processor users",
     *      operationId="getProcessorUsers",
     *      security={{"bearerAuth":{}}},
     *
     *      @OA\Response(
     *          response=200,
     *          description="Processor users",
     *          @OA\JsonContent(
     *              type="array",
     *              @OA\Items(
     *                  type="object",
     *                  @OA\Property(property="Id", type="string", example="82438ecc-829a-4c2d-9df8-c7babbd32374"),
     *                  @OA\Property(property="Name", type="string", example="Administrador"),
     *                  @OA\Property(property="Lastname", type="string", example="SET"),
     *                  @OA\Property(property="Email", type="string", example="admin@email.com"),
     *                  @OA\Property(property="ProfileName", type="string", example="Administrador Ambiente")
     *              )
     *          )
     *      ),
     *
     *      @OA\Response(
     *          response=401,
     *         description="Unauthorized",
     *         @OA\MediaType(mediaType="text/plain", @OA\Schema(type="string", example="Unauthorized"))
     *      ),
     *
     *      @OA\Response(
     *          response=404,
     *          description="El ambiente no existe o no tienes acceso a él | No se ha encontrado información de los usuarios procesadores",
     *          @OA\MediaType(mediaType="text/plain", @OA\Schema(type="string", example="El ambiente no existe o no tienes acceso a él"))
     *      )
     *  )
     */

    public function processorUsers()
    {
        $business = Business::where('Id', request()->attributes->get('jwt')->businessId)->first();
        if (!$business) {
            return $this->basicError('El ambiente no existe o no tienes acceso a él', 404);
        }

        try {
            return $this->success(self::users($business->Id));
        } catch (\Exception $e) {
            return $this->basicError('No se ha encontrado información de los usuarios procesadores', 404);
        }
    }


    /**
     *  @OA\Get(
     *      path="/api/speiCloud/authorization/users/authorizers",
     *      tags={"SpeiCloud Authorization Authorizer Users"},
     *      summary="Get authorizer users",
     *      description="Get authorizer users",
     *      operationId="getAuthorizerUsers",
     *      security={{"bearerAuth":{}}},
     *
     *      @OA\Response(
     *          response=200,
     *          description="Authorizer users",
     *          @OA\JsonContent(
     *              type="array",
     *              @OA\Items(
     *                  type="object",
     *                  @OA\Property(property="Id", type="string", example="82438ecc-829a-4c2d-9df8-c7babbd32374"),
     *                  @OA\Property(property="Name", type="string", example="Administrador"),
     *                  @OA\Property(property="Lastname", type="string", example="SET"),
     *                  @OA\Property(property="Email", type="string", example="admin@email.com"),
     *                  @OA\Property(property="ProfileName", type="string", example="Administrador Ambiente")
     *              )
     *          )
     *      ),
     *
     *      @OA\Response(
     *          response=401,
     *          description="Unauthorized",
     *          @OA\MediaType(mediaType="text/plain", @OA\Schema(type="string", example="Unauthorized"))
     *      ),
     *
     *      @OA\Response(
     *          response=404,
     *          description="El ambiente no existe o no tienes acceso a él | No se ha encontrado información de los usuarios autorizadores",
     *          @OA\MediaType(mediaType="text/plain", @OA\Schema(type="string", example="El ambiente no existe o no tienes acceso a él"))
     *      )
     *
     *  )
     */

    public function authorizerUsers()
    {
        $business = Business::where('Id', request()->attributes->get('jwt')->businessId)->first();
        if (!$business) {
            return $this->basicError('El ambiente no existe o no tienes acceso a él', 404);
        }

        try {
            return $this->success(self::authorizers($business->Id));
        } catch (\Exception $e) {
            return $this->basicError('No se ha encontrado información de los usuarios autorizadores', 404);
        }
    }
}
