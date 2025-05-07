<?php

namespace App\Http\Controllers\SpeiCloud\Authorization;

use App\Http\Controllers\Controller;
use App\Models\Backoffice\Companies\CompanyProjection;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

class AuthorizationAccountsController extends Controller
{

    /**
     *  @OA\Get(
     *      path="/api/speiCloud/authorization/accounts/origin",
     *      tags={"SpeiCloud Authorization Accounts"},
     *      summary="Get origin accounts",
     *      description="Get origin accounts",
     *      operationId="getOriginAccounts",
     *      security={{"bearerAuth":{}}},
     *
     *      @OA\Response(
     *          response=200,
     *          description="Origin accounts",
     *          @OA\JsonContent(
     *              @OA\Property(property="company", type="string", example="Company Name"),
     *              @OA\Property(property="accountNumber", type="string", example="123456789012345678"),
     *          )
     *      ),
     *
     *      @OA\Response(
     *          response=400,
     *          description="Error getting origin accounts",
     *          @OA\MediaType(mediaType="text/plain", @OA\Schema(type="string", example="Error al obtener las cuentas de origen"))
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
     *          description="Business not found",
     *          @OA\MediaType(mediaType="text/plain", @OA\Schema(type="string", example="El ambiente no existe o no tienes acceso a él"))
     *      )
     *
     *  )
     */

    public function origins(){

        try {
            $businessId = request()->attributes->get('jwt')->businessId;
            $accounts = self::acountsByBusiness($businessId);
            return response()->json($accounts);
        } catch (\Exception $e) {
            return self::basicError('Error al obtener las cuentas de origen', 400);
        }
    }


    public static function acountsByBusiness($businessId)
    {
        $companies = CompanyProjection::where('BusinessId', $businessId)
            ->where('Active', 1)
            ->orderBy('TradeName')
            ->get();

        $accounts = [];

        foreach ($companies as $company) {
            $services = json_decode($company->Services);
            foreach ($services as $service) {
                if ($service->type == '4') {
                    $accounts[] = [
                        'company' => $company->TradeName,
                        'accountNumber' => $service->bankAccountNumber
                    ];
                    break;
                }
            }
        }

        return $accounts;
    }


    /**
     *  @OA\Get(
     *      path="/api/speiCloud/authorization/accounts/destination",
     *      tags={"SpeiCloud Authorization Accounts"},
     *      summary="Get destination accounts",
     *      description="Get destination accounts",
     *      operationId="getDestinationAccounts",
     *      security={{"bearerAuth":{}}},
     *
     *      @OA\Response(
     *          response=200,
     *          description="Destination accounts",
     *          @OA\JsonContent(
     *              @OA\Property(property="accountNumber", type="string", example="123456789012345678"),
     *              @OA\Property(property="beneficiary", type="string", example="Beneficiary Name"),
     *              @OA\Property(property="bank", type="string", example="Bank Name"),
     *          )
     *      ),
     *
     *      @OA\Response(
     *          response=400,
     *          description="Error getting destination accounts",
     *          @OA\MediaType(mediaType="text/plain", @OA\Schema(type="string", example="Error al obtener las cuentas de destino"))
     *      ),
     *
     *      @OA\Response(
     *          response=401,
     *          description="Unauthorized",
     *          @OA\MediaType(mediaType="text/plain", @OA\Schema(type="string", example="Unauthorized"))
     *      ),
     *
     *
     *      @OA\Response(
     *          response=404,
     *          description="Business not found",
     *          @OA\MediaType(mediaType="text/plain", @OA\Schema(type="string", example="El ambiente no existe o no tienes acceso a él"))
     *      )
     *  )
     */
    public function destinations(){

        try {
            $businessId = request()->attributes->get('jwt')->businessId;
            $accounts = self::destinationAccounts($businessId);
            return response()->json($accounts);
        } catch (\Exception $e) {
            var_dump($e->getMessage());
            return self::basicError('Error al obtener las cuentas de destino', 400);
        }
    }

    public static function destinationAccounts($businessId){
        $accounts =  DB::table('t_spei_external_accounts as tsea')
        ->join('t_users as tu', 'tsea.CreatedByUser', '=', 'tu.Id')
        ->join('cat_spei_banks as tsb', 'tsea.BankId', '=', 'tsb.Id')
        ->where('tu.BusinessId', $businessId)
        ->select([
            'tsea.InterbankCLABE as accountNumber',
            'tsea.Beneficiary as beneficiary',
            'tsb.ShortName as bank',
        ])
        ->orderBy('tsea.Beneficiary')
        ->groupBy(
            'tsea.InterbankCLABE',
            'tsea.Beneficiary',
            'tsb.ShortName'
        )
        ->get();

       return $accounts;
    }
}
