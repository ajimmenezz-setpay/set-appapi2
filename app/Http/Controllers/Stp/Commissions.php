<?php

namespace App\Http\Controllers\Stp;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Security\Crypt;
use App\Http\Controllers\Users\Validate as UsersValidate;
use App\Services\StpService;
use Illuminate\Http\Request;
use App\Models\Speicloud\StpAccounts;
use App\Models\Speicloud\StpTransaction;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Ramsey\Uuid\Uuid;

class Commissions extends Controller
{

    /**
     *  @OA\Post(
     *      path="/api/commission/pay",
     *      tags={"Commissions"},
     *      summary="Paga las comisiones de una cuenta",
     *      description="Paga las comisiones de una cuenta",
     *      operationId="payCommission",
     *      security={{"bearerAuth":{}}},
     * 
     *      @OA\RequestBody(
     *          required=true,
     *          description="Datos de la cuenta",
     *          @OA\JsonContent(
     *              required={"account"},
     *              @OA\Property(property="account", type="string", format="string", example="123456789012345678"),
     *          ),
     *      ),
     * 
     *      @OA\Response(
     *          response=200,
     *          description="Pago de comisión enviado",
     *          @OA\JsonContent(
     *              @OA\Property(property="message", type="string", example="Se ha enviado la orden de pago de comisión correctamente. Por favor espere la confirmación de STP."),
     *          ),
     *      ),
     * 
     *      @OA\Response(
     *          response=400,
     *          description="Error al pagar la comisión",
     *          @OA\MediaType(
     *              mediaType="text/plain",
     *              @OA\Schema(
     *                 type="string",
     *                example="Error al pagar la comisión. Por favor intente más tarde."
     *             )
     *         )
     *      )
     * 
     *  )
     */


    public function payCommission(Request $request)
    {
        self::validate($request, [
            'account' => 'required|size:18',
        ], [
            'account.required' => 'La cuenta es requerida',
            'account.size' => 'La cuenta debe tener 18 dígitos',
        ]);

        $stpAccount = Accounts::searchByNumber($request->account);

        if (!$stpAccount) {
            return self::basicError("La cuenta no pertenece a ningún centro de costos");
        }

        if (UsersValidate::business($request->attributes->get('jwt'), $stpAccount->BusinessId)) {
            return self::basicError("El usuario no tiene permisos para realizar esta acción en este centro de costos");
        }

        if ($stpAccount->StpAccountId == "" || is_null($stpAccount->StpAccountId)) {
            return self::basicError("La cuenta no es compatible con el servicio de pago de comisiones.");
        }

        if ((float)$stpAccount->Commissions <= 0) {
            return self::basicError("La cuenta no tiene comisiones pendientes.");
        }

        try {

            DB::beginTransaction();

            $parentAccount = Accounts::searchById($stpAccount->Id);

            $balance = StpService::getBalance(
                Crypt::decrypt($stpAccount->Url),
                Crypt::decrypt($stpAccount->Key),
                $stpAccount->Company,
                $request->account
            );

            if (isset($balance->respuesta->saldo)) {
                if (env('APP_ENV') == 'production') {
                    StpAccounts::where('Id', $stpAccount->Id)->update([
                        'Balance' => $balance->respuesta->saldo,
                        'PendingCharges' => $balance->respuesta->cargosPendientes,
                        'BalanceDate' => date('Y-m-d H:i:s')
                    ]);
                } else {
                    $balance->respuesta->saldo = 1000000;
                }

                if ((float)$balance->respuesta->saldo < (float)$stpAccount->Commissions) {
                    return self::basicError("El saldo de $" . number_format($balance->respuesta->saldo, 2) . " es insuficiente para pagar la comisión de $" . number_format($stpAccount->Commissions, 2));
                }
            } else {
                return self::basicError("Error de comunicación con el servicio de STP. Por favor intente más tarde.");
            }


            $comission = number_format((float)$stpAccount->Commissions, 2, '.', '');
            $traceKey = $stpAccount->Acronym . date('YmdHis');
            $concept = "PAGO DE COMISIONES AL CORTE " . date('Ymd His');
            $reference = random_int(1000000, 9999999);
            $date = Carbon::now(new \DateTimeZone('America/Mexico_City'))->format('Y-m-d H:i:s');
            $comissionObject = json_encode([
                'speiOut' => 0,
                'speiIn' => 0,
                'internal' => 0,
                'feeStp' => 0,
                'stpAccount' => 0,
                'total' => $comission
            ]);

            if (env('APP_ENV') == 'production') {
                $response = StpService::speiOut(
                    Crypt::decrypt($stpAccount->Url),
                    Crypt::decrypt($stpAccount->Key),
                    $stpAccount->Company,
                    $comission,
                    $traceKey,
                    $concept,
                    Crypt::decrypt($stpAccount->Number),
                    $stpAccount->Company,
                    "",
                    Crypt::decrypt($parentAccount->Number),
                    $parentAccount->Company,
                    $reference,
                    90646,
                    "",
                    40,
                    90646,
                    40
                );

                if (isset($response->respuesta->id) && count($response->respuesta->id) > 3) {
                    $stpId = $response->respuesta->id;
                    StpAccounts::where('Id', $stpAccount->Id)->update([
                        'Commissions' => 0
                    ]);
                } else {
                    DB::commit();
                    return self::basicError("Error al registrar la orden en STP. Error:" . ErrorRegisterOrder::error($response->respuesta->id));
                }
            } else {
                $response = new \stdClass();
                $response->resultado = new \stdClass();
                $response->resultado->id = "1111111111";
                $stpId = $response->resultado->id;
            }

            StpTransaction::create([
                'Id' => Uuid::uuid7(),
                'BusinessId' => $stpAccount->BusinessId,
                'TypeId' => 1,
                'StatusId' => 1,
                'Reference' => $reference,
                'TrackingKey' => $traceKey,
                'Concept' => $concept,
                'SourceAccount' => Crypt::decrypt($stpAccount->Number),
                'SourceName' => $stpAccount->Company,
                'SourceBalance' => number_format((float)$balance->respuesta->saldo - $comission, 2, '.', ''),
                'SourceEmail' => "",
                'DestinationAccount' => Crypt::decrypt($parentAccount->Number),
                'DestinationName' => $parentAccount->Company,
                'DestinationBalance' => number_format((float)$comission, 2, '.', ''),
                'DestinationEmail' => "",
                'DestinationBankCode' => 90646,
                'Amount' => number_format($comission, 2, '.', ''),
                'Commissions' => $comissionObject,
                'LiquidationDate' => $date,
                'UrlCEP' => "",
                'StpId' => $stpId,
                'ApiData' => '[]',
                'CreatedByUser' => $request->attributes->get('jwt')->id,
                'CreateDate' => $date,
                'Active' => 1
            ]);

            DB::commit();
            return self::success([
                "message" => "Se ha enviado la orden de pago de comisión correctamente. Por favor espere la confirmación de STP.",
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return self::basicError("Error al pagar la comisión. Por favor intente más tarde." . $e->getMessage());
        }
    }
}
