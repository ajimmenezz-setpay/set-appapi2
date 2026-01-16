<?php

namespace App\Http\Controllers\CardCloud;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\CardCloud\CardAssigned;
use GuzzleHttp\Client;
use App\Http\Services\CardCloudApi;
use GuzzleHttp\Exception\RequestException;
use App\Models\CardCloud\Credit;
use App\Models\CardCloud\CreditWallet;
use App\Models\Backoffice\Companies\CompaniesUsers;
use App\Models\CardCloud\Subaccount;
use App\Models\CardCloud\Card;
use Exception;

class CardBarcodeController extends Controller
{
    /**
     * @OA\Post(
     *      path="/api/cardCloud/card/{cardId}/generate-code",
     *      tags={"Card Cloud V2"},
     *      summary="Generar código de barras para transacción",
     *      description="Genera un código de barras para realizar una transacción con la tarjeta especificada.",
     *      security={{"bearerAuth":{}}},
     *
     *      @OA\Parameter(
     *          name="cardId",
     *          in="path",
     *          required=true,
     *          description="ID de la tarjeta",
     *          @OA\Schema(type="string")
     *      ),
     *
     *      @OA\RequestBody(
     *          required=true,
     *          @OA\JsonContent(
     *              required={"amount","type"},
     *              @OA\Property(property="amount", type="number", format="float", example=100.00),
     *              @OA\Property(property="type", type="string", enum={"deposit","withdrawal"}, example="deposit")
     *          )
     *      ),
     *
     *      @OA\Response(
     *          response=200,
     *          description="Código de barras generado exitosamente",
     *          @OA\JsonContent(
     *              @OA\Property(property="barcode", type="string", example="2C3427PDE2992000500001752738231")
     *          )
     *      ),
     *
     *      @OA\Response(
     *         response=400,
     *         description="Error al generar el código de barras",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Error al generar el código de barras.")
     *         )
     *      ),
     *
     *      @OA\Response(
     *         response=401,
     *         description="No autorizado",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="No autorizado")
     *         )
     *      ),
     *
     *      @OA\Response(
     *         response=500,
     *         description="Error interno del servidor",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Error interno del servidor")
     *         )
     *      )
     * )
     */


    public function generateCode(Request $request, $cardId)
    {
        try {

            $this->validate($request, [
                'amount' => 'required|numeric|min:1',
                'type' => 'required|string|in:deposit,withdrawal',
            ], [
                'amount.required' => 'El monto es obligatorio (amount)',
                'amount.numeric' => 'El monto debe ser un número (amount)',
                'amount.min' => 'El monto debe ser mayor a 0 (amount)',
                'type.required' => 'El tipo de transacción es obligatorio (type)',
                'type.string' => 'El tipo de transacción debe ser una cadena (type)',
                'type.in' => 'El tipo de transacción debe ser "deposit" o "withdrawal" (type)',
            ]);

            switch ($request->attributes->get('jwt')->profileId) {
                case 5:
                    $allowed = true;
                    break;
                case 7:
                    $subaccount = CompaniesUsers::where('UserId', $request->attributes->get('jwt')->id)
                        ->pluck('CompanyId')
                        ->toArray();
                    $cardCloudSubaccounts = Subaccount::whereIn('ExternalId', $subaccount)
                        ->pluck('Id')
                        ->toArray();

                    $cards = Card::whereIn('SubAccountId', $cardCloudSubaccounts)
                        ->where('UUID', $cardId)
                        ->first();

                    $allowed = $cards ? true : false;
                    break;
                case 8:
                    $cardAssigned = CardAssigned::where('CardCloudId', $cardId)
                        ->where('UserId', $request->attributes->get('jwt')->id)
                        ->first();
                    $allowed = $cardAssigned ? true : false;

                    if (!$allowed) {
                        $cardCloud = Card::where('UUID', $cardId)->first();
                        if ($cardCloud && $cardCloud->ProductType == "revolving") {
                            $creditWallet = CreditWallet::where('Id', $cardCloud->CreditWalletId)->first();
                            if ($creditWallet) {
                                $creditUserAssociation = Credit::where('ExternalId', $creditWallet->UUID)
                                    ->where('UserId', $request->attributes->get('jwt')->id)
                                    ->first();
                                if ($creditUserAssociation) {
                                    $allowed = true;
                                }
                            }
                        }
                    }

                    break;
                default:
                    $allowed = false;
            }

            if (!$allowed) {
                throw new Exception("No tienes permisos para generar códigos de barras para esta tarjeta.");
            } else {


                try {
                    $client = new Client();
                    $response = $client->request('POST', env('CARD_CLOUD_BASE_URL') . '/api/v1/card/' . $cardId . '/barcode', [
                        'headers' => [
                            'Content-Type' => 'application/json',
                            'Authorization' => 'Bearer ' . CardCloudApi::getToken($request->attributes->get('jwt')->id),
                        ],
                        'json' => [
                            'amount' => $request->input('amount'),
                            'type' => $request->input('type')
                        ]
                    ]);

                    $decodedJson = json_decode($response->getBody(), true);

                    return response()->json(['barcode' => $decodedJson['barcode']]);
                } catch (RequestException $e) {
                    if ($e->hasResponse()) {
                        $statusCode = $e->getResponse()->getStatusCode();
                        $responseBody = $e->getResponse()->getBody()->getContents();
                        $decodedJson = json_decode($responseBody, true);
                        $message = 'Error al generar el código de barras.';

                        if (json_last_error() === JSON_ERROR_NONE) {
                            $message .= " " . $decodedJson['message'];
                        }
                        return response($message, $statusCode);
                    } else {
                        return response("Error al generar el código de barras.", 400);
                    }
                }
            }
        } catch (\Exception $e) {
            return self::basicError($e->getMessage());
        }
    }

    /**
     * @OA\Get(
     *      path="/api/cardCloud/card/{cardId}/barcodes",
     *      tags={"Card Cloud V2"},
     *      summary="Obtener códigos de barras de una tarjeta",
     *      description="Obtiene los códigos de barras asociados a una tarjeta específica.",
     *      security={{"bearerAuth":{}}},
     *
     *      @OA\Parameter(
     *          name="cardId",
     *          in="path",
     *          required=true,
     *          description="ID de la tarjeta",
     *          @OA\Schema(type="string")
     *      ),
     *
     *      @OA\Parameter(
     *          name="filter",
     *          in="query",
     *          required=true,
     *          description="Filtro para los códigos de barras (all, active, expired, canceled, used)",
     *          @OA\Schema(type="string", enum={"all", "active", "expired", "canceled", "used"})
     *      ),
     *
     *      @OA\Response(
     *          response=200,
     *          description="Códigos de barras obtenidos exitosamente",
     *          @OA\JsonContent(
     *              type="array",
     *              @OA\Items(
     *                  type="object",
     *                  @OA\Property(property="barcode", type="string", example="2C3427PDE2992000600001752739472"),
     *                  @OA\Property(property="type", type="string", example="DEPOSIT"),
     *                  @OA\Property(property="amount", type="string", example="600.00"),
     *                  @OA\Property(property="state", type="string", example="active"),
     *                  @OA\Property(property="used", type="boolean", example=false),
     *                  @OA\Property(property="canceled", type="boolean", example=false),
     *                  @OA\Property(property="expired", type="boolean", example=false),
     *                  @OA\Property(property="used_at", type="string", format="date-time", nullable=true, example=null),
     *                  @OA\Property(property="expires_at", type="string", format="date-time", example="2025-07-24 08:04:32"),
     *                  @OA\Property(property="canceled_at", type="string", format="date-time", nullable=true, example=null)
     *              )
     *          )
     *      ),
     *
     *      @OA\Response(
     *          response=404,
     *          description="No se encontraron códigos de barras",
     *          @OA\MediaType(
     *              mediaType="text/plain",
     *              @OA\Schema(type="string", example="No se encontraron códigos de barras para la tarjeta especificada.")
     *          )
     *      ),
     *
     *      @OA\Response(
     *          response=400,
     *          description="Error al obtener los códigos de barras",
     *          @OA\MediaType(
     *              mediaType="text/plain",
     *              @OA\Schema(type="string", example="Error al obtener los códigos de barras.")
     *          )
     *      ),
     *
     *      @OA\Response(
     *          response=401,
     *          description="No autorizado",
     *          @OA\MediaType(
     *              mediaType="text/plain",
     *              @OA\Schema(type="string", example="No autorizado")
     *          )
     *      ),
     *
     *      @OA\Response(
     *          response=500,
     *          description="Error interno del servidor",
     *          @OA\MediaType(
     *              mediaType="text/plain",
     *              @OA\Schema(type="string", example="Error interno del servidor.")
     *          )
     *      )
     * )
     */


    public function getBarcodes(Request $request, $cardId)
    {
        try {
            switch ($request->attributes->get('jwt')->profileId) {
                case 5:
                    $allowed = true;
                    break;
                case 7:
                    $subaccount = CompaniesUsers::where('UserId', $request->attributes->get('jwt')->id)
                        ->pluck('CompanyId')
                        ->toArray();
                    $cardCloudSubaccounts = Subaccount::whereIn('ExternalId', $subaccount)
                        ->pluck('Id')
                        ->toArray();

                    $cards = Card::whereIn('SubAccountId', $cardCloudSubaccounts)
                        ->where('UUID', $cardId)
                        ->first();

                    $allowed = $cards ? true : false;
                    break;
                case 8:
                    $cardAssigned = CardAssigned::where('CardCloudId', $cardId)
                        ->where('UserId', $request->attributes->get('jwt')->id)
                        ->first();
                    $allowed = $cardAssigned ? true : false;

                    if (!$allowed) {
                        $cardCloud = Card::where('UUID', $cardId)->first();
                        if ($cardCloud && $cardCloud->ProductType == "revolving") {
                            $creditWallet = CreditWallet::where('Id', $cardCloud->CreditWalletId)->first();
                            if ($creditWallet) {
                                $creditUserAssociation = Credit::where('ExternalId', $creditWallet->UUID)
                                    ->where('UserId', $request->attributes->get('jwt')->id)
                                    ->first();
                                if ($creditUserAssociation) {
                                    $allowed = true;
                                }
                            }
                        }
                    }

                    break;
                default:
                    $allowed = false;
            }

            if (!$allowed) {
                throw new Exception("No tienes permisos para ver los códigos de barras de esta tarjeta.");
            } else {

                $this->validate($request, [
                    'filter' => 'required|string|in:all,active,expired,canceled,used',
                ], [
                    'filter.required' => 'El filtro es obligatorio (filter)',
                    'filter.string' => 'El filtro debe ser una cadena (filter)',
                    'filter.in' => 'El filtro debe ser "all", "active", "expired", "canceled" o "used" (filter)',
                ]);

                $client = new Client();
                $response = $client->request('GET', env('CARD_CLOUD_BASE_URL') . '/api/v1/card/' . $cardId . '/barcodes', [
                    'headers' => [
                        'Content-Type' => 'application/json',
                        'Authorization' => 'Bearer ' . CardCloudApi::getToken($request->attributes->get('jwt')->id),
                    ],
                    'json' => [
                        'filter' => $request->input('filter')
                    ]
                ]);

                $decodedJson = json_decode($response->getBody(), true);

                return response()->json($decodedJson);
            }
        } catch (\Exception $e) {
            return self::basicError($e->getMessage());
        }
    }

    /**
     * @OA\Delete(
     *     path="/api/cardCloud/card/{cardId}/barcode",
     *    tags={"Card Cloud V2"},
     *    summary="Eliminar código de barras de una tarjeta",
     *    @OA\Parameter(
     *        name="cardId",
     *        in="path",
     *        required=true,
     *        @OA\Schema(type="string")
     *    ),
     *
     *   @OA\RequestBody(
     *       required=true,
     *      @OA\JsonContent(
     *          required={"barcode"},
     *         @OA\Property(property="barcode", type="string", example="2C3427PDE2992000500001752738231")
     *     )
     *   ),
     *
     *
     *    @OA\Response(
     *        response=200,
     *        description="Código de barras eliminado exitosamente",
     *        @OA\MediaType(
     *            mediaType="text/plain",
     *            @OA\Schema(type="string", example="Código de barras cancelado exitosamente")
     *        )
     *    ),
     *    @OA\Response(
     *        response=404,
     *        description="Código de barras no encontrado",
     *        @OA\MediaType(
     *            mediaType="text/plain",
     *            @OA\Schema(type="string", example="Código de barras no encontrado.")
     *        )
     *    ),
     *    @OA\Response(
     *        response=400,
     *        description="Error al eliminar el código de barras",
     *        @OA\MediaType(
     *            mediaType="text/plain",
     *            @OA\Schema(type="string", example="Error al eliminar el código de barras.")
     *        )
     *    ),
     *    @OA\Response(
     *        response=401,
     *        description="No autorizado",
     *        @OA\MediaType(
     *            mediaType="text/plain",
     *            @OA\Schema(type="string", example="No autorizado")
     *        )
     *    ),
     *    @OA\Response(
     *        response=500,
     *        description="Error interno del servidor",
     *        @OA\MediaType(
     *            mediaType="text/plain",
     *            @OA\Schema(type="string", example="Error interno del servidor.")
     *        )
     *    )
     * )
     */


    public function deleteBarcode(Request $request, $cardId)
    {
        try {
            switch ($request->attributes->get('jwt')->profileId) {
                case 5:
                    $allowed = true;
                    break;
                case 7:
                    $subaccount = CompaniesUsers::where('UserId', $request->attributes->get('jwt')->id)
                        ->pluck('CompanyId')
                        ->toArray();
                    $cardCloudSubaccounts = Subaccount::whereIn('ExternalId', $subaccount)
                        ->pluck('Id')
                        ->toArray();

                    $cards = Card::whereIn('SubAccountId', $cardCloudSubaccounts)
                        ->where('UUID', $cardId)
                        ->first();

                    $allowed = $cards ? true : false;
                    break;
                case 8:
                    $cardAssigned = CardAssigned::where('CardCloudId', $cardId)
                        ->where('UserId', $request->attributes->get('jwt')->id)
                        ->first();
                    $allowed = $cardAssigned ? true : false;

                    if (!$allowed) {
                        $cardCloud = Card::where('UUID', $cardId)->first();
                        if ($cardCloud && $cardCloud->ProductType == "revolving") {
                            $creditWallet = CreditWallet::where('Id', $cardCloud->CreditWalletId)->first();
                            if ($creditWallet) {
                                $creditUserAssociation = Credit::where('ExternalId', $creditWallet->UUID)
                                    ->where('UserId', $request->attributes->get('jwt')->id)
                                    ->first();
                                if ($creditUserAssociation) {
                                    $allowed = true;
                                }
                            }
                        }
                    }

                    break;
                default:
                    $allowed = false;
            }

            if (!$allowed) {
                throw new Exception("No tienes permisos para eliminar códigos de barras de esta tarjeta.");
            } else {
                $this->validate($request, [
                    'barcode' => 'required|string',
                ], [
                    'barcode.required' => 'El código de barras es obligatorio (barcode)',
                    'barcode.string' => 'El código de barras debe ser una cadena (barcode)',
                ]);

                $client = new Client();
                $response = $client->request('DELETE', env('CARD_CLOUD_BASE_URL') . '/api/v1/card/' . $cardId . '/barcode', [
                    'headers' => [
                        'Content-Type' => 'application/json',
                        'Authorization' => 'Bearer ' . CardCloudApi::getToken($request->attributes->get('jwt')->id),
                    ],
                    'json' => [
                        'barcode' => $request->input('barcode')
                    ]
                ]);

                return response('Código de barras cancelado exitosamente', 200);
            }
        } catch (RequestException $e) {
            if ($e->hasResponse()) {
                $statusCode = $e->getResponse()->getStatusCode();
                $responseBody = $e->getResponse()->getBody()->getContents();
                $decodedJson = json_decode($responseBody, true);
                $message = 'Error al eliminar el código de barras.';

                if (json_last_error() === JSON_ERROR_NONE) {
                    $message .= " " . $decodedJson['message'];
                }
                return response($message, $statusCode);
            } else {
                return response("Error al eliminar el código de barras.", 400);
            }
        } catch (\Exception $e) {
            return self::basicError($e->getMessage());
        }
    }
}
