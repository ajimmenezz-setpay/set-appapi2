<?php

namespace App\Http\Controllers\CardCloud;

use App\Http\Controllers\Controller;
use App\Models\CardCloud\CardAssigned;
use Illuminate\Http\Request;
use GuzzleHttp\Client;
use App\Http\Services\CardCloudApi;
use GuzzleHttp\Exception\RequestException;
use App\Models\Backoffice\Companies\CompanyProjection;
use App\Models\Backoffice\Companies\CompaniesUsers;
use App\Http\Controllers\Security\GoogleAuth;
use Ramsey\Uuid\Uuid;

class CardManagementController extends Controller
{
    /**
     *  @OA\Post(
     *      path="/api/cardCloud/card/{cardId}/nip",
     *      tags={"Card Cloud"},
     *      summary="Update NIP from card",
     *      description="Update NIP from card",
     *      operationId="updateNip",
     *      security={{"bearerAuth":{}}},
     *
     *      @OA\Parameter(
     *          name="cardId",
     *          in="path",
     *          description="Card ID",
     *          required=true,
     *          @OA\Schema(type="string")
     *      ),
     *
     *      @OA\RequestBody(
     *          required=true,
     *          @OA\JsonContent(
     *              @OA\Property(property="old_nip", type="string", example="1234"),
     *              @OA\Property(property="new_nip", type="string", example="4321")
     *          )
     *      ),
     *
     *      @OA\Response(
     *          response=200,
     *          description="NIP updated successfully",
     *          @OA\JsonContent(
     *              @OA\Property(property="message", type="string", example="NIP updated successfully")
     *          )
     *       ),
     *
     *      @OA\Response(
     *          response=400,
     *          description="Error updating NIP",
     *          @OA\MediaType(mediaType="text/plain", @OA\Schema(type="string", example="Error updating NIP"))
     *      ),
     *
     *      @OA\Response(
     *          response=401,
     *          description="Unauthorized",
     *          @OA\MediaType(mediaType="text/plain", @OA\Schema(type="string", example="Unauthorized"))
     *      )
     * )
     */

    public function updateNip(Request $request, $cardId)
    {
        $this->validate($request, [
            'old_nip' => 'required|string|min:4|max:4',
            'new_nip' => 'required|string|min:4|max:4'
        ], [
            'old_nip.required' => 'El campo old_nip es obligatorio',
            'old_nip.string' => 'El campo old_nip debe ser una cadena de texto',
            'old_nip.min' => 'El campo old_nip debe tener al menos 4 caracteres',
            'old_nip.max' => 'El campo old_nip debe tener como máximo 4 caracteres',
            'new_nip.required' => 'El campo new_nip es obligatorio',
            'new_nip.string' => 'El campo new_nip debe ser una cadena de texto',
            'new_nip.min' => 'El campo new_nip debe tener al menos 4 caracteres',
            'new_nip.max' => 'El campo new_nip debe tener como máximo 4 caracteres'
        ]);

        if (CardAssigned::where('CardCloudId', $cardId)->where('UserId', $request->attributes->get('jwt')->id)->count() == 0) {
            return response("La tarjeta no está asignada al usuario", 400);
        } else {
            try {
                $client = new Client();
                $response = $client->request('PATCH', env('CARD_CLOUD_BASE_URL') . '/api/v1/card/' . $cardId . '/update_nip', [
                    'headers' => [
                        'Content-Type' => 'application/json',
                        'Authorization' => 'Bearer ' . CardCloudApi::getToken($request->attributes->get('jwt')->id),
                    ],
                    'json' => [
                        'old_nip' => $request->old_nip,
                        'new_nip' => $request->new_nip
                    ]
                ]);

                $decodedJson = json_decode($response->getBody(), true);

                return response()->json(['message' => $decodedJson['message']]);
            } catch (RequestException $e) {
                if ($e->hasResponse()) {
                    $statusCode = $e->getResponse()->getStatusCode();
                    $responseBody = $e->getResponse()->getBody()->getContents();
                    $decodedJson = json_decode($responseBody, true);
                    $message = 'Error al actualizar el NIP.';

                    if (json_last_error() === JSON_ERROR_NONE) {
                        $message .= " " . $decodedJson['message'];
                    }
                    return response($message, $statusCode);
                } else {
                    return response("Error al actualizar el NIP.", 400);
                }
            }
        }
    }

    /**
     * @OA\Get(
     *      path="/api/cardCloud/card/client-id/{clientId}",
     *      tags={"Card Cloud"},
     *      summary="Search card by client id",
     *      description="Search card by client id",
     *      operationId="searchByClientId",
     *      security={{"bearerAuth":{}}},
     *
     *      @OA\Parameter(
     *          name="clientId",
     *          in="path",
     *          description="Client ID",
     *          required=true,
     *          @OA\Schema(type="string")
     *      ),
     *
     *      @OA\Response(
     *          response=200,
     *          description="Card found",
     *          @OA\JsonContent(
     *              @OA\Property(property="card_id", type="string", example="0190760f-acf6-7800-bce8-1b2a451c3427"),
     *              @OA\Property(property="client_id", type="string", example="SP0000001"),
     *              @OA\Property(property="masked_pan", type="string", example="123456******1234"),
     *          )
     *      ),
     *
     *      @OA\Response(
     *          response=400,
     *          description="Error searching card by client id",
     *          @OA\MediaType(mediaType="text/plain", @OA\Schema(type="string", example="Error searching card by client id"))
     *      ),
     *
     *      @OA\Response(
     *          response=401,
     *          description="Unauthorized",
     *          @OA\MediaType(mediaType="text/plain", @OA\Schema(type="string", example="Unauthorized"))
     *      )
     * )
     */

    public function searchByClientId(Request $request, $clientId)
    {
        try {
            $client = new Client();
            $response = $client->request('GET', env('CARD_CLOUD_BASE_URL') . '/api/v1/card/client_id/' . $clientId, [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Bearer ' . CardCloudApi::getToken($request->attributes->get('jwt')->id),
                ]
            ]);

            $decodedJson = json_decode($response->getBody(), true);

            return response()->json($decodedJson);
        } catch (RequestException $e) {
            if ($e->hasResponse()) {
                $statusCode = $e->getResponse()->getStatusCode();
                $responseBody = $e->getResponse()->getBody()->getContents();
                $decodedJson = json_decode($responseBody, true);
                $message = 'Error al buscar la tarjeta por el Client Id.';

                if (json_last_error() === JSON_ERROR_NONE) {
                    $message .= " " . $decodedJson['message'];
                }
                return response($message, $statusCode);
            } else {
                return response("Error al buscar la tarjeta por el Client Id. " . $e->getMessage(), 400);
            }
        }
    }

    /**
     * @OA\Post(
     *      path="/api/cardCloud/card/activate",
     *      tags={"Card Cloud"},
     *      summary="Activate additional card for user",
     *      description="Activate additional card for user",
     *      operationId="activateCard",
     *      security={{"bearerAuth":{}}},
     *
     *      @OA\RequestBody(
     *          required=true,
     *          @OA\JsonContent(
     *              required={"card", "expiration_date", "pin"},
     *              @OA\Property(property="card", type="string", example="12345678"),
     *              @OA\Property(property="expiration_date", type="string", example="1225 (mmYY)"),
     *              @OA\Property(property="pin", type="string", example="1234")
     *          )
     *      ),
     *
     *      @OA\Response(
     *          response=200,
     *          description="Card activated successfully",
     *          @OA\JsonContent(
     *              @OA\Property(property="message", type="string", example="Tarjeta activada correctamente")
     *          )
     *       ),
     *
     *      @OA\Response(
     *          response=400,
     *          description="Error activating card",
     *          @OA\MediaType(mediaType="text/plain", @OA\Schema(type="string", example="Error activating card"))
     *      ),
     *
     *      @OA\Response(
     *          response=401,
     *          description="Unauthorized",
     *          @OA\MediaType(mediaType="text/plain", @OA\Schema(type="string", example="Unauthorized"))
     *       )
     * )
     */

    public function activateCard(Request $request)
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

            $client = new Client();
            $response = $client->request('POST', env('CARD_CLOUD_BASE_URL') . '/card/validate', [
                'headers' => [
                    'Content-Type' => 'application/json'
                ],
                'json' => [
                    'card' => $request->card,
                    'pin' => $request->pin,
                    'moye' => $request->expiration_date
                ]
            ]);

            $decodedJson = json_decode($response->getBody(), true);

            if (self::validateAlreadyAssigned($decodedJson['card_id'])) {
                return response("La tarjeta ya está asignada a un usuario", 400);
            }

            if (!self::validateUserCompany($decodedJson['subaccount_id'], $request->attributes->get('jwt')->id)) {
                return self::basicError("La tarjeta de origen no pertenece a la empresa del usuario");
            }

            CardAssigned::create([
                'Id' => Uuid::uuid7(),
                'BusinessId' => $request->attributes->get('jwt')->businessId,
                'CardCloudId' => $decodedJson['card_id'],
                'UserId' => $request->attributes->get('jwt')->id,
                'Name' => $request->attributes->get('jwt')->firstName,
                'Lastname' => $request->attributes->get('jwt')->lastname,
                'Email' => $request->attributes->get('jwt')->email,
                'IsPending' => 0,
                'CreatedByUser' => $request->attributes->get('jwt')->id,
                'CreateDate' => date('Y-m-d H:i:s')
            ]);

            return response()->json(['message' => 'Tarjeta activada correctamente']);
        } catch (RequestException $e) {
            if ($e->hasResponse()) {
                $statusCode = $e->getResponse()->getStatusCode();
                $responseBody = $e->getResponse()->getBody()->getContents();
                $decodedJson = json_decode($responseBody, true);
                $message = 'Error al activar la tarjeta.';

                if (json_last_error() === JSON_ERROR_NONE) {
                    $message .= " " . $decodedJson['message'];
                }
                return response($message, $statusCode);
            } else {
                return response("Error al activar la tarjeta.", 400);
            }
        } catch (\Exception $e) {
            return self::basicError($e->getMessage());
        }
    }

    public static function validateUserCompany($subaccountId, $userId)
    {
        $company = CompanyProjection::where('Services', 'like', '%' . $subaccountId . '%')->first();
        if (!$company) return false;

        $userCompany = CompaniesUsers::where('CompanyId', $company->Id)->where('UserId', $userId)->first();
        if (!$userCompany) return false;

        return true;
    }

    public static function validateAlreadyAssigned($cardId)
    {
        return CardAssigned::where('CardCloudId', $cardId)->count() > 0;
    }

    /**
     * @OA\Post(
     *      path="/api/cardCloud/card/buy_virtual_card",
     *      tags={"Card Cloud"},
     *      summary="Buy virtual card",
     *      description="Buy virtual card",
     *      operationId="buyVirtualCard",
     *      security={{"bearerAuth":{}}},
     *
     *      @OA\RequestBody(
     *          required=true,
     *          @OA\JsonContent(
     *              required={"source_card", "months", "auth_code"},
     *              @OA\Property(property="source_card", type="string", example="01948734-0b5d-73d2-ac58-465617bfaa5b"),
     *              @OA\Property(property="months", type="integer", example=3),
     *              @OA\Property(property="auth_code", type="string", example="123456")
     *          )
     *      ),
     *
     *      @OA\Response(
     *          response=200,
     *          description="Virtual card bought successfully",
     *          @OA\JsonContent(
     *              @OA\Property(property="card_id", type="string", example="0190760f-acf6-7800-bce8-1b2a451c3427"),
     *              @OA\Property(property="card_external_id", type="string", example="01948734-19d5-5641-e5af-e7a5433f2a81"),
     *              @OA\Property(property="card_type", type="string", example="virtual"),
     *              @OA\Property(property="brand", type="string", example="MASTER"),
     *              @OA\Property(property="bin", type="string", example="55557777"),
     *              @OA\Property(property="pan", type="string", example="1111444455557777"),
     *              @OA\Property(property="client_id", type="string", example="SP0000001"),
     *              @OA\Property(property="masked_pan", type="string", example="516152XXXXXX9874"),
     *              @OA\Property(property="balance", type="string", example="0.00"),
     *              @OA\Property(property="clabe", type="string", example="123456789012345678"),
     *              @OA\Property(property="status", type="string", example="BLOCKED"),
     *              @OA\Property(property="setups", type="object",
     *                  @OA\Property(property="CardId", type="integer", example=106124),
     *                  @OA\Property(property="Status", type="string", example="BLOCKED"),
     *                  @OA\Property(property="StatusReason", type="string", example="INITIAL_BLOCKED"),
     *                  @OA\Property(property="Ecommerce", type="boolean", example=true),
     *                  @OA\Property(property="International", type="boolean", example=false),
     *                  @OA\Property(property="Stripe", type="boolean", example=true),
     *                  @OA\Property(property="Wallet", type="boolean", example=true),
     *                  @OA\Property(property="Withdrawal", type="boolean", example=false),
     *                  @OA\Property(property="Contactless", type="boolean", example=false),
     *                  @OA\Property(property="PinOffline", type="boolean", example=true),
     *                  @OA\Property(property="PinOnUs", type="boolean", example=false),
     *                  @OA\Property(property="Id", type="integer", example=84831)
     *             )
     *         )
     *    ),
     *
     *      @OA\Response(
     *          response=400,
     *          description="Error buying virtual card",
     *          @OA\MediaType(mediaType="text/plain", @OA\Schema(type="string", example="Error buying virtual card"))
     *      ),
     *
     *      @OA\Response(
     *          response=401,
     *          description="Unauthorized",
     *          @OA\MediaType(mediaType="text/plain", @OA\Schema(type="string", example="Unauthorized"))
     *      )
     *
     * )
     *
     */

    public function buyVirtualCard(Request $request)
    {
        try {
            $this->validate($request, [
                'source_card' => 'required',
                'months' => 'required|numeric|min:1',
                'auth_code' => 'required|min:6|max:6'
            ], [
                'source_card.required' => 'La tarjeta de origen es requerida',
                'months.required' => 'Los meses de la tarjeta virtual son requeridos',
                'months.numeric' => 'Los meses de la tarjeta virtual deben ser un número',
                'months.min' => 'Los meses de la tarjeta virtual deben ser mayor a 0',
                'auth_code.required' => 'El código de autenticación es requerido.',
                'auth_code.min' => 'El código de autenticación debe tener 6 caracteres.',
                'auth_code.max' => 'El código de autenticación debe tener 6 caracteres.'
            ]);

            GoogleAuth::authorized($request->attributes->get('jwt')->id, $request->auth_code);

            if (CardAssigned::where('CardCloudId', $request->source_card)->where('UserId', $request->attributes->get('jwt')->id)->count() == 0) {
                return self::basicError("La tarjeta de origen no está asignada al usuario");
            }

            $client = new Client();
            $response = $client->request('POST', env('CARD_CLOUD_BASE_URL') . '/api/v1/card/' . $request->source_card . '/buy_virtual_card', [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Bearer ' . CardCloudApi::getToken($request->attributes->get('jwt')->id),
                ],
                'json' => [
                    'months' => $request->months
                ]
            ]);

            $decodedJson = json_decode($response->getBody(), true);

            CardAssigned::create([
                'Id' => Uuid::uuid7(),
                'BusinessId' => $request->attributes->get('jwt')->businessId,
                'CardCloudId' => $decodedJson['card_id'],
                'UserId' => $request->attributes->get('jwt')->id,
                'Name' => $request->attributes->get('jwt')->firstName,
                'Lastname' => $request->attributes->get('jwt')->lastname,
                'Email' => $request->attributes->get('jwt')->email,
                'IsPending' => 0,
                'CreatedByUser' => $request->attributes->get('jwt')->id,
                'CreateDate' => date('Y-m-d H:i:s')
            ]);

            return response()->json($decodedJson);
        } catch (RequestException $e) {
            if ($e->hasResponse()) {
                $statusCode = $e->getResponse()->getStatusCode();
                $responseBody = $e->getResponse()->getBody()->getContents();
                $decodedJson = json_decode($responseBody, true);
                $message = 'Error al comprar la tarjeta virtual.';

                if (json_last_error() === JSON_ERROR_NONE) {
                    $message .= " " . $decodedJson['message'];
                }
                return response($message, $statusCode);
            } else {
                return response("Error al comprar la tarjeta virtual.", 400);
            }
        } catch (\Exception $e) {
            return self::basicError($e->getMessage());
        }
    }

    /**
     * @OA\Get(
     *      path="/api/cardCloud/card/virtual_card_price",
     *      tags={"Card Cloud"},
     *      summary="Get virtual card price",
     *      description="Get virtual card price",
     *      operationId="getVirtualCardPrice",
     *      security={{"bearerAuth":{}}},
     *
     *      @OA\Parameter(
     *          name="card_id",
     *          in="query",
     *          description="Card ID",
     *          required=true,
     *          @OA\Schema(type="string")
     *      ),
     *
     *      @OA\Response(
     *          response=200,
     *          description="Virtual card price",
     *          @OA\JsonContent(
     *              @OA\Property(property="price", type="string", example="100.00")
     *          )
     *      ),
     *
     *      @OA\Response(
     *          response=400,
     *          description="Error getting virtual card price",
     *          @OA\MediaType(mediaType="text/plain", @OA\Schema(type="string", example="Error getting virtual card price"))
     *      ),
     *
     *     @OA\Response(
     *          response=401,
     *          description="Unauthorized",
     *          @OA\MediaType(mediaType="text/plain", @OA\Schema(type="string", example="Unauthorized"))
     *     )
     * )
     */

    public function getVirtualCardPrice(Request $request)
    {
        try {

            $this->validate($request, [
                'card_id' => 'required',
            ], [
                'card_id.required' => 'El id de la tarjeta es requerido'
            ]);

            $client = new Client();
            $response = $client->request('GET', env('CARD_CLOUD_BASE_URL') . '/api/v1/card/' . $request->card_id . '/virtual_card_price', [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Bearer ' . CardCloudApi::getToken($request->attributes->get('jwt')->id),
                ]
            ]);

            $decodedJson = json_decode($response->getBody(), true);

            return response()->json($decodedJson);
        } catch (RequestException $e) {
            if ($e->hasResponse()) {
                $statusCode = $e->getResponse()->getStatusCode();
                $responseBody = $e->getResponse()->getBody()->getContents();
                $decodedJson = json_decode($responseBody, true);
                $message = 'Error al obtener el precio de la tarjeta virtual.';

                if (json_last_error() === JSON_ERROR_NONE) {
                    $message .= " " . $decodedJson['message'];
                }
                return response($message, $statusCode);
            } else {
                return response("Error al obtener el precio de la tarjeta virtual.", 400);
            }
        } catch (\Exception $e) {
            return self::basicError($e->getMessage());
        }
    }
}
