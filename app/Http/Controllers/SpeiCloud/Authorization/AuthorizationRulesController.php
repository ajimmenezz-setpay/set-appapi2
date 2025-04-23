<?php

namespace App\Http\Controllers\SpeiCloud\Authorization;

use App\Http\Controllers\Controller;
use App\Models\Backoffice\Business;
use App\Http\Middleware\ValidateEnvironmentAdminProfile;
use Illuminate\Http\Request;

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

    public function enableRules(Request $request)
    {
        try {
            $business = Business::where('Id', $request->attributes->get('jwt')->businessId)->first();
            if (!$business) {
                return $this->basicError('El ambiente no existe o no tienes acceso a él', 404);
            }

            $business->AuthorizationRules = 1;
            $business->save();

            return $this->success([
                'enabled' => $business->AuthorizationRules == 1 ? true : false,
                'rules' => $business->AuthorizationRules == 1 ? self::getRules($business->Id) : []
            ]);
        } catch (\Exception $e) {
            return $this->basicError('No se pudo habilitar las reglas de autorización', 500);
        }
    }

    public function disableRules(Request $request)
    {
        try {
            $business = Business::where('Id', $request->attributes->get('jwt')->businessId)->first();
            if (!$business) {
                return $this->basicError('El ambiente no existe o no tienes acceso a él', 404);
            }

            $business->AuthorizationRules = 0;
            $business->save();

            return $this->success([
                'enabled' => $business->AuthorizationRules == 1 ? true : false,
                'rules' => $business->AuthorizationRules == 1 ? self::getRules($business->Id) : []
            ]);
        } catch (\Exception $e) {
            return $this->basicError('No se pudo deshabilitar las reglas de autorización', 500);
        }
    }

    public static function getRules($businessId)
    {
        return [];
    }

    public function store(Request $request)
    {
        try {

            $this->validate($request, [
                'rules' => 'required|array',
                'rules.*.rule' => 'required|string',
                'rules.*.description' => 'required|string',
                'rules.*.enabled' => 'required|boolean',
            ]);



            $business = Business::where('Id', $request->attributes->get('jwt')->businessId)->first();
            if (!$business) {
                return $this->basicError('El ambiente no existe o no tienes acceso a él', 404);
            }

            if ($business->AuthorizationRules == 0) {
                return $this->basicError('Las reglas de autorización están habilitadas', 400);
            }



            // Store the rules in the database
            // ...

            return $this->success([
                'enabled' => true,
                'rules' => self::getRules($business->Id)
            ]);
        } catch (\Exception $e) {
            return $this->basicError('No se pudo guardar las reglas de autorización', 500);
        }
    }
}
