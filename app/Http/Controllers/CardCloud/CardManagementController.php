<?php

namespace App\Http\Controllers\CardCloud;

use App\Http\Controllers\Card\CardManagementController as CardCardManagementController;
use App\Http\Controllers\Controller;
use App\Models\CardCloud\CardAssigned;
use Illuminate\Http\Request;
use GuzzleHttp\Client;
use App\Http\Services\CardCloudApi;
use GuzzleHttp\Exception\RequestException;
use App\Models\Backoffice\Companies\CompanyProjection;
use App\Models\Backoffice\Companies\CompaniesUsers;
use App\Http\Controllers\Security\GoogleAuth;
use App\Models\CardCloud\Card;
use App\Models\CardCloud\CardPan;
use App\Models\Users\FirebaseToken;
use Ramsey\Uuid\Uuid;
use Carbon\Carbon;
use App\Http\Controllers\Notifications\FirebasePushController as FirebaseService;
use Illuminate\Support\Facades\Log;

class CardManagementController extends Controller
{
    /**
     *  @OA\Post(
     *      path="/api/cardCloud/card/{cardId}/nip",
     *      tags={"Card Cloud V2"},
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


        switch ($request->attributes->get('jwt')->profileId) {
            case 5:
                $allowed = true;
                break;
            case 8:
                $cardAssigned = CardAssigned::where('CardCloudId', $cardId)
                    ->where('UserId', $request->attributes->get('jwt')->id)
                    ->first();
                $allowed = $cardAssigned ? true : false;
                break;
            default:
                $allowed = false;
        }

        if (!$allowed) {
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
     *      tags={"Card Cloud V2"},
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
     *      tags={"Card Cloud V2"},
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
        return CardAssigned::where('CardCloudId', $cardId)->where('Email', '!=', "")->whereNotNull('Email')->count() > 0;
    }

    /**
     * @OA\Post(
     *      path="/api/cardCloud/card/buy_virtual_card",
     *      tags={"Card Cloud V2"},
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
                'months' => 'required|numeric|min:1'
            ], [
                'source_card.required' => 'La tarjeta de origen es requerida',
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
     *      tags={"Card Cloud V2"},
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


    public function getBalanceByPhone(Request $request, $phone)
    {
        try {
            if (!$phone) {
                return self::error("El número de teléfono es requerido");
            }
            if (!preg_match('/^[0-9]{10}$/', $phone)) {
                return self::error("El número de teléfono debe tener 10 dígitos y no puede contener caracteres especiales");
            }

            $cardsResponse = [];

            $cards = CardAssigned::join('t_users', 't_stp_card_cloud_users.UserId', '=', 't_users.Id')
                ->where('ProfileId', 8)
                ->where('t_users.Active', 1)
                ->where('t_users.Phone', $phone)
                ->where('t_users.Phone', '!=', '0000000000')
                ->select('t_stp_card_cloud_users.CardCloudId')
                ->get();
            if ($cards->isEmpty()) {
                return self::error("No se encontraron tarjetas asociadas al número de teléfono proporcionado.");
            }

            foreach ($cards as $card) {
                try {
                    $client = new Client();
                    $response = $client->request('GET', env('CARD_CLOUD_BASE_URL') . '/card/' . $card->CardCloudId . '/balance', [
                        'headers' => [
                            'Content-Type' => 'application/json'
                        ]
                    ]);

                    $decodedJson = json_decode($response->getBody(), true);

                    $cardsResponse[] = [
                        'client_id' => $decodedJson['client_id'],
                        'balance' => $decodedJson['balance']
                    ];

                    // $message .= "La tarjeta con terminación {$decodedJson['card_end']} tiene un balance de $" . number_format($decodedJson['balance'], 2, '.', ',') . ".\n";
                } catch (\Exception $e) {
                    // $message .= $e->getMessage() . "\n";
                }
            }

            if (empty($cardsResponse)) {
                return self::error("No se pudo obtener el balance de las tarjetas asociadas al número de teléfono proporcionado.");
            }

            return response()->json($cardsResponse);
        } catch (\Exception $e) {
            return self::basicError($e->getMessage());
        }
    }

    /**
     * @OA\Get(
     *      path="/api/cardCloud/card/search/{search}",
     *      summary="Search card",
     *      description="Search card by PAN, Client ID or last 8 digits of PAN",
     *      tags={"Card Cloud V2"},
     *      security={{"bearerAuth": {}}},
     *      @OA\Parameter(
     *          name="search",
     *          in="path",
     *          description="Search term",
     *          required=true,
     *          @OA\Schema(type="string")
     *      ),
     *
     *       @OA\Response(
     *           response=200,
     *           description="Card found",
     *           @OA\JsonContent(
     *               @OA\Property(property="card_id", type="string", example="f4b3b3b3-4b3b-4b3b-4b3b-4b3b4b3b4b3b", description="Card UUID"),
     *               @OA\Property(property="card_external_id", type="string", example="f4b3b3b3-4b3b-4b3b-4b3b-4b3b4b3b4b3b", description="Card External Id"),
     *               @OA\Property(property="card_type", type="string", example="virtual", description="Card type"),
     *               @OA\Property(property="brand", type="string", example="MASTER", description="Card active function"),
     *               @OA\Property(property="client_id", type="string", example="SP0000000", description="Client ID"),
     *               @OA\Property(property="masked_pan", type="string", example="555544******2222", description="Masked PAN"),
     *               @OA\Property(property="balance", type="string", example="0.00", description="Card balance"),
     *               @OA\Property(property="clabe", type="string", example="0123456789010203", description="CLABE account number"),
     *               @OA\Property(property="status", type="string", example="NORMAL", description="Card status"),
     *               @OA\Property(
     *                  property="substatus",
     *                  type="object",
     *                  description="Card substatus",
     *                  @OA\Property(property="id", type="integer", example=1, description="Substatus ID"),
     *                  @OA\Property(property="name", type="string", example="NORMAL", description="Substatus name"),
     *                  @OA\Property(property="description", type="string", example="Card is normal", description="Substatus description")
     *               ),
     *               @OA\Property(
     *                   property="setups",
     *                   type="object",
     *                   description="Card configurations",
     *                   @OA\Property(property="Status", type="string", example="NORMAL", description="Card status"),
     *                   @OA\Property(property="StatusReason", type="string", example="", description="Reason for status change"),
     *                   @OA\Property(property="Ecommerce", type="integer", example=1, description="Ecommerce setup status"),
     *                   @OA\Property(property="International", type="integer", example=0, description="International transactions setup status"),
     *                   @OA\Property(property="Stripe", type="integer", example=1, description="Stripe integration status"),
     *                   @OA\Property(property="Wallet", type="integer", example=1, description="Wallet integration status"),
     *                   @OA\Property(property="Withdrawal", type="integer", example=1, description="Withdrawal setup status"),
     *                   @OA\Property(property="Contactless", type="integer", example=1, description="Contactless transactions setup status")
     *               ),
     *               @OA\Property(property="enviroment", type="string", example="SET", description="Customer Name / Enviroment"),
     *               @OA\Property(property="company", type="string", example="SET", description="Company Name"),
     *               @OA\Property(
     *                  property="profile",
     *                  type="object",
     *                  description="Profile information",
     *                  @OA\Property(property="Id", type="integer", example=7, description="Profile ID"),
     *                  @OA\Property(property="ProfileName", type="string", example="Perfil Dir SET", description="Profile name"),
     *                  @OA\Property(property="MaxDailyAmountTPV", type="string", example="$104.64 / $150,000.00", description="Max daily amount for TPV transactions"),
     *                  @OA\Property(property="MaxDailyAmountATM", type="string", example="$0.00 / $24,100.00", description="Max daily amount for ATM transactions"),
     *                  @OA\Property(property="MaxDailyOperationsTPV", type="string", example="1 / 30", description="Max daily operations for TPV transactions"),
     *                  @OA\Property(property="MaxAmountTPV", type="string", example="250000.00", description="Max amount for TPV transactions"),
     *                  @OA\Property(property="MaxAmountATM", type="string", example="24100.00", description="Max amount for ATM transactions"),
     *                  @OA\Property(property="MaxAmountMonthlyTPV", type="string", example="$1,018.14 / $500,000.00", description="Max amount for monthly TPV transactions"),
     *                  @OA\Property(property="MaxAmountMonthlyATM", type="string", example="$24,069.60 / $250,000.00", description="Max amount for monthly ATM transactions"),
     *                  @OA\Property(property="MaxOperationsMonthlyTPV", type="string", example="7 / 50000", description="Max operations for monthly TPV transactions")
     *               ),
     *               @OA\Property(
     *                  property="assigned_user",
     *                  type="object",
     *                  description="User assigned to the card",
     *                  @OA\Property(property="name", type="string", example="John Doe", description="User name"),
     *                  @OA\Property(property="email", type="string", example="john@doe.email", description="User email"),
     *               ),
     *               @OA\Property(
     *                   property="movements",
     *                   type="array",
     *                   @OA\Items(
     *                       type="object",
     *                       @OA\Property(property="movement_id", type="string", example="019244d6-120b-71f1-baef-5420fe167046", description="Unique identifier for the movement"),
     *                       @OA\Property(property="date", type="integer", example=1727731733, description="Timestamp of the movement"),
     *                       @OA\Property(property="type", type="string", example="TRANSFER", description="Type of the movement"),
     *                       @OA\Property(property="amount", type="string", example="21496.27", description="Amount of the transaction"),
     *                       @OA\Property(property="balance", type="string", example="12.30", description="Balance after the transaction"),
     *                       @OA\Property(property="authorization_code", type="string", example="287069", description="Authorization code"),
     *                       @OA\Property(property="description", type="string", example="Transfer from subaccount. Deposito N", description="Description of the movement")
     *                   ),
     *                   description="List of movements associated with the card"
     *               )
     *           )
     *       ),
     *
     *      @OA\Response(
     *          response=404,
     *          description="Card not found",
     *          @OA\JsonContent(
     *              @OA\Property(property="message", type="string", example="Card not found", description="Error message")
     *          )
     *      ),
     *
     *      @OA\Response(
     *          response=400,
     *          description="Search term must be at least 8 characters",
     *          @OA\JsonContent(
     *              @OA\Property(property="message", type="string", example="Search term must be at least 8 characters", description="Error message")
     *          )
     *      ),
     *
     *      @OA\Response(
     *          response=401,
     *          description="Unauthorized",
     *          @OA\JsonContent(
     *              @OA\Property(property="message", type="string", example="Unauthorized")
     *          )
     *      )
     *
     * )
     *
     */

    public function search(Request $request, $search)
    {
        try {
            if (strlen($search) < 8)
                throw new \Exception('El término de búsqueda debe tener al menos 8 caracteres (Client ID o Últimos 8 dígitos de la tarjeta)', 400);

            $client = new Client();
            $response = $client->request('GET', env('CARD_CLOUD_BASE_URL') . '/api/v1/card/search/' . $search, [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Bearer ' . CardCloudApi::getToken($request->attributes->get('jwt')->id)
                ]
            ]);

            $decodedJson = json_decode($response->getBody(), true);

            unset($decodedJson['bin']);
            unset($decodedJson['pan']);


            return response()->json($decodedJson);
        } catch (\Exception $e) {
            return self::basicError($e->getMessage(), $e->getCode() ?? 400);
        }
    }

    /**
     * @OA\Post(
     *      path="/api/cardCloud/card/{cardId}/setup/{setup_name}/{action}",
     *      summary="Set card setup",
     *      description="Set card setup.",
     *      tags={"Card Cloud V2"},
     *      security={{"bearerAuth": {}}},
     *      @OA\Parameter(
     *          name="cardId",
     *          in="path",
     *          description="Card ID",
     *          required=true,
     *          @OA\Schema(type="string")
     *      ),
     *
     *      @OA\Parameter(
     *          name="setup_name",
     *          in="path",
     *          description="Setup name (ecommerce, international, stripe, wallet, withdrawal, contactless)",
     *          required=true,
     *          @OA\Schema(type="string")
     *      ),
     *
     *      @OA\Parameter(
     *          name="action",
     *          in="path",
     *          description="Action (enable, disable)",
     *          required=true,
     *          @OA\Schema(type="string")
     *      ),
     *
     *       @OA\Response(
     *           response=200,
     *           description="Card setup updated successfully",
     *           @OA\JsonContent(
     *               @OA\Property(property="message", type="string", example="Card setup updated successfully", description="Message indicating the result of the operation"),
     *               @OA\Property(
     *                   property="card",
     *                   type="object",
     *                   @OA\Property(property="card_id", type="string", example="019076da-2212-7245-b8ff-efa0c9e927ba", description="Card UUID"),
     *                   @OA\Property(property="card_type", type="string", example="physical", description="Type of the card"),
     *                   @OA\Property(property="active_function", type="string", example="CREDIT", description="Active function of the card"),
     *                   @OA\Property(property="brand", type="string", example="MASTER", description="Card brand"),
     *                   @OA\Property(property="masked_pan", type="string", example="516152XXXXXX9752", description="Masked PAN"),
     *                   @OA\Property(property="balance", type="number", format="float", example=62.82999999999765, description="Card balance"),
     *                   @OA\Property(
     *                       property="setup",
     *                       type="object",
     *                       description="Card setup configuration",
     *                       @OA\Property(property="status", type="string", example="NORMAL", description="Status of the card"),
     *                       @OA\Property(property="enabled_ecommerce", type="boolean", example=true, description="Ecommerce setup enabled status"),
     *                       @OA\Property(property="enabled_international", type="boolean", example=true, description="International transactions setup enabled status"),
     *                       @OA\Property(property="enabled_stripe", type="boolean", example=true, description="Stripe setup enabled status"),
     *                       @OA\Property(property="enabled_wallet", type="boolean", example=true, description="Wallet setup enabled status"),
     *                       @OA\Property(property="enabled_withdrawal", type="boolean", example=true, description="Withdrawal setup enabled status"),
     *                       @OA\Property(property="enabled_contactless", type="boolean", example=true, description="Contactless setup enabled status"),
     *                       @OA\Property(property="pin_offline", type="boolean", example=true, description="Offline PIN enabled status"),
     *                       @OA\Property(property="pin_on_us", type="boolean", example=false, description="On-us PIN enabled status")
     *                   ),
     *                   @OA\Property(
     *                       property="person",
     *                       type="object",
     *                       description="Person associated with the card",
     *                       @OA\Property(property="person_id", type="integer", example=2, description="Person ID"),
     *                       @OA\Property(property="person_external_id", type="string", example="018fa638-6749-4f46-b773-433faad3af8a", description="External ID of the person"),
     *                       @OA\Property(property="person_type", type="string", example="legal", description="Type of person (e.g., legal or individual)"),
     *                       @OA\Property(property="status", type="string", example="active", description="Status of the person"),
     *                       @OA\Property(
     *                           property="person_account",
     *                           type="object",
     *                           description="Account information of the person",
     *                           @OA\Property(property="account_id", type="integer", example=1, description="Account ID"),
     *                           @OA\Property(property="external_id", type="string", example="018fa638-6e9a-0c1a-9542-2f1a8b4a4c13", description="External ID of the account"),
     *                           @OA\Property(property="client_id", type="string", example="018e7c16-6510-f94a-1ae2-37b6ba26c264", description="Client ID associated with the account"),
     *                           @OA\Property(property="book_id", type="string", example="018f5ed6-279e-700d-9cdb-99778820245a", description="Book ID associated with the account")
     *                       ),
     *                       @OA\Property(
     *                           property="legal_person_data",
     *                           type="object",
     *                           description="Legal information of the person",
     *                           @OA\Property(property="legal_name", type="string", example="SE TRANSACCIONALES", description="Legal name of the person"),
     *                           @OA\Property(property="trade_name", type="string", example="SET", description="Trade name of the legal person"),
     *                           @OA\Property(property="rfc", type="string", example="STR170601FT1", description="RFC of the legal person")
     *                       )
     *                   ),
     *                   @OA\Property(
     *                       property="alias_account",
     *                       type="object",
     *                       description="Alias account information",
     *                       @OA\Property(property="Id", type="integer", example=15676, description="Alias account ID"),
     *                       @OA\Property(property="PersonAccountId", type="integer", example=1, description="Person account ID linked to alias account"),
     *                       @OA\Property(property="CardId", type="integer", example=30382, description="Card ID linked to alias account"),
     *                       @OA\Property(property="ExternalId", type="string", example=" ", description="External ID of alias account"),
     *                       @OA\Property(property="ClientId", type="string", example=" ", description="Client ID of alias account"),
     *                       @OA\Property(property="BookId", type="string", example=" ", description="Book ID of alias account")
     *                   )
     *               )
     *           )
     *       ),
     *
     *      @OA\Response(
     *          response=404,
     *          description="Card not found or you do not have permission to access it",
     *          @OA\JsonContent(
     *              @OA\Property(property="message", type="string", example="Card not found or you do not have permission to access it", description="Error message")
     *          )
     *      ),
     *
     *      @OA\Response(
     *          response=400,
     *          description="Error setting card setup",
     *          @OA\JsonContent(
     *              @OA\Property(property="message", type="string", example="Error setting card setup", description="Error message")
     *          )
     *      ),
     *
     *      @OA\Response(
     *          response=401,
     *          description="Unauthorized",
     *          @OA\JsonContent(
     *              @OA\Property(property="message", type="string", example="Unauthorized")
     *          )
     *      )
     * )
     *
     */

    public function setup(Request $request, $cardId, $setupName, $action)
    {
        try {
            $validSetups = ['ecommerce', 'international', 'stripe', 'wallet', 'withdrawal', 'contactless', 'pin_offline', 'pin_on_us'];
            if (!in_array($setupName, $validSetups)) {
                return self::basicError("El setup no es válido");
            }

            if (!in_array($action, ['enable', 'disable'])) {
                return self::basicError("La acción no es válida");
            }

            $client = new Client();
            $response = $client->request('POST', env('CARD_CLOUD_BASE_URL') . '/api/v1/card/' . $cardId . '/setup/' . $setupName . '/' . $action, [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Bearer ' . CardCloudApi::getToken($request->attributes->get('jwt')->id),
                ]
            ]);

            $decodedJson = json_decode($response->getBody(), true);

            return response()->json(['message' => $decodedJson['message']]);
        } catch (RequestException $e) {
            if ($e->hasResponse()) {
                $statusCode = $e->getResponse()->getStatusCode();
                $responseBody = $e->getResponse()->getBody()->getContents();
                $decodedJson = json_decode($responseBody, true);
                $message = 'Error al actualizar el setup.';

                if (json_last_error() === JSON_ERROR_NONE) {
                    $message .= " " . $decodedJson['message'];
                }
                return response($message, $statusCode);
            } else {
                return response("Error al actualizar el setup.", 400);
            }
        }
    }

    /**
     * @OA\Get(
     *      path="/api/cardCloud/card/{cardId}/webhooks",
     *      tags={"Card Cloud V2"},
     *      summary="Get registered card webhooks",
     *      description="Get registered card webhooks",
     *      @OA\Parameter(
     *          name="uuid",
     *          in="path",
     *          description="Card UUID",
     *          required=true,
     *          @OA\Schema(type="string")
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Successful operation",
     *          @OA\JsonContent(
     *              type="array",
     *              @OA\Items(
     *                  @OA\Property(property="id", type="integer", example=1456),
     *                  @OA\Property(property="event_type", type="string", example="authorization_created"),
     *                  @OA\Property(property="event_name", type="string", example="global_authorization"),
     *                  @OA\Property(property="authorizer_response", type="string", example="APPROVED"),
     *                  @OA\Property(property="endpoint", type="string", example="CONSULT"),
     *                  @OA\Property(property="establishment", type="string", example="BANCOMER S.A.          CIUDAD DE MEX MEX"),
     *                  @OA\Property(property="amount", type="string", example="13.92"),
     *                  @OA\Property(
     *                      property="request",
     *                      type="string",
     *                      example=""
     *                  ),
     *                  @OA\Property(property="date", type="integer", example=1722156442)
     *              )
     *          )
     *      ),
     *      @OA\Response(
     *          response=404,
     *          description="Card not found or no permission",
     *          @OA\JsonContent(
     *              @OA\Property(property="message", type="string", example="Card not found or you do not have permission to access it")
     *          )
     *      ),
     *      @OA\Response(
     *          response=500,
     *          description="Error getting webhooks",
     *          @OA\JsonContent(
     *              @OA\Property(property="message", type="string", example="Error getting webhooks")
     *          )
     *      )
     * )
     */

    public function webhooks(Request $request, $cardId)
    {
        try {
            $client = new Client();
            $response = $client->request('GET', env('CARD_CLOUD_BASE_URL') . '/api/v1/card/' . $cardId . '/webhooks', [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Bearer ' . CardCloudApi::getToken($request->attributes->get('jwt')->id)
                ]
            ]);

            $decodedJson = json_decode($response->getBody(), true);

            return response()->json($decodedJson);
        } catch (RequestException $e) {
            echo $e->getMessage() . " - " . $e->getCode() . " - " . $e->getFile() . " - " . $e->getLine();
            if ($e->hasResponse()) {
                $statusCode = $e->getResponse()->getStatusCode();
                $responseBody = $e->getResponse()->getBody()->getContents();
                $decodedJson = json_decode($responseBody, true);
                $message = 'Error al actualizar el setup.';

                if (json_last_error() === JSON_ERROR_NONE) {
                    $message .= " " . $decodedJson['message'];
                }
                return response($message, $statusCode);
            }
        } catch (\Exception $e) {
            return self::basicError($e->getMessage() . " - " . $e->getCode() . " - " . $e->getFile() . " - " . $e->getLine());
        }
    }

    /**
     * @OA\Get(
     *      path="/api/cardCloud/card/{cardId}/failed-authorizations",
     *      tags={"Card Cloud V2"},
     *      summary="Get failed authorizations for a card",
     *      description="Get failed authorizations for a card",
     *
     *      @OA\Parameter(
     *          name="cardId",
     *          in="path",
     *          description="Card ID",
     *          required=true,
     *          @OA\Schema(type="string")
     *     ),
     *
     *
     *      @OA\Response(
     *          response=200,
     *          description="Successful operation",
     *              @OA\JsonContent(
     *              type="array",
     *              @OA\Items(
     *                  @OA\Property(property="uuid", type="string", example="123e4567-e89b-12d3-a456-426614174000"),
     *                   @OA\Property(property="authorization_code", type="string", example="123456"),
     *                   @OA\Property(property="endpoint", type="string", example="CONSULT"),
     *                   @OA\Property(property="event_type", type="string", example="authorization_created"),
     *                   @OA\Property(property="description", type="string", example="BANCOMER S.A.          CIUDAD DE MEX MEX"),
     *                   @OA\Property(property="amount", type="number", format="float", example=13.92),
     *                   @OA\Property(property="error", type="string", example="Error message"),
     *                   @OA\Property(property="timestamp", type="integer", example=1722156442),
     *                   @OA\Property(property="request", type="string", example="Request body"),
     *                   @OA\Property(property="response", type="string", example="Response body")
     *               )
     *          )
     *     ),
     *
     *      @OA\Response(
     *          response=404,
     *          description="Card not found or no permission",
     *          @OA\JsonContent(
     *              @OA\Property(property="message", type="string", example="Card not found or you do not have permission to access it")
     *          )
     *      ),
     *
     *      @OA\Response(
     *          response=500,
     *          description="Error getting failed authorizations",
     *          @OA\JsonContent(
     *              @OA\Property(property="message", type="string", example="Error getting failed authorizations")
     *          )
     *      )
     *
     * )
     */

    public function failedAuthorizations(Request $request, $cardId)
    {
        try {
            $client = new Client();
            $response = $client->request('GET', env('CARD_CLOUD_BASE_URL') . '/api/v1/card/' . $cardId . '/failed_authorization', [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Bearer ' . CardCloudApi::getToken($request->attributes->get('jwt')->id)
                ]
            ]);

            $decodedJson = json_decode($response->getBody(), true);

            return response()->json($decodedJson);
        } catch (RequestException $e) {
            if ($e->hasResponse()) {
                $statusCode = $e->getResponse()->getStatusCode();
                $responseBody = $e->getResponse()->getBody()->getContents();
                $decodedJson = json_decode($responseBody, true);
                $message = 'Error al obtener las autorizaciones fallidas.';

                if (json_last_error() === JSON_ERROR_NONE) {
                    $message .= " " . $decodedJson['message'];
                }
                return response($message, $statusCode);
            } else {
                return response("Error al obtener las autorizaciones fallidas.", 400);
            }
        } catch (\Exception $e) {
            return self::basicError($e->getMessage());
        }
    }

    /**
     *  @OA\Get(
     *      path="/api/cardCloud/card/{cardId}/movements",
     *      tags={"Card Cloud V2"},
     *      summary="Get card movements",
     *      description="Returns card movements",
     *      security={{"bearerAuth":{}}},
     *
     *      @OA\Parameter(
     *          name="cardId",
     *          in="path",
     *          description="Card ID",
     *          required=true,
     *          @OA\Schema(
     *              type="string"
     *          )
     *      ),
     *
     *      @OA\RequestBody(
     *          required=false,
     *          @OA\JsonContent(
     *              @OA\Property(property="from", type="string", example="1234567890", description="From date (Unix timestamp)"),
     *              @OA\Property(property="to", type="string", example="1234567890", description="To date (Unix timestamp)")
     *          )
     *      ),
     *
     *      @OA\Response(
     *          response="200",
     *          description="Movements retrieved successfully",
     *          @OA\JsonContent(
     *              @OA\Property(property="movements", type="array",
     *                  @OA\Items(
     *                      @OA\Property(property="movement_id", type="string", example="123456", description="Movement UUID"),
     *                      @OA\Property(property="date", type="string", example="1234567890", description="Movement Date (Unix timestamp)"),
     *                      @OA\Property(property="type", type="string", example="deposit", description="Movement Type"),
     *                      @OA\Property(property="amount", type="string", example="100.00", description="Movement Amount"),
     *                      @OA\Property(property="authorization_code", type="string", example="123456", description="Authorization Code"),
     *                      @OA\Property(property="description", type="string", example="Deposit", description="Movement Description")
     *                  ),
     *              ),
     *              @OA\Property(property="total_records", type="integer", example=1, description="Total records"),
     *              @OA\Property(property="from", type="string", example="1234567890", description="From date (Unix timestamp)"),
     *              @OA\Property(property="to", type="string", example="1234567890", description="To date (Unix timestamp)")
     *          )
     *     ),
     *
     *      @OA\Response(
     *          response=401,
     *          description="Unauthorized",
     *          @OA\JsonContent(
     *              @OA\Property(property="message", type="string", example="Unauthorized | Error while decoding the token", description="Message")
     *          )
     *      ),
     *
     * )
     *
     */

    public function movements(Request $request, $cardId)
    {
        try {
            $from = isset($request['from']) ? Carbon::createFromTimestamp($request->from) : Carbon::now()->subMonth();
            $to = isset($request['to']) ? Carbon::createFromTimestamp($request->to) : Carbon::now();

            if ($from > $to) return response()->json(['message' => 'Invalid date range'], 400);

            $client = new Client();
            $response = $client->request('GET', env('CARD_CLOUD_BASE_URL') . '/api/v1/card/' . $cardId . '/movements', [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Bearer ' . CardCloudApi::getToken($request->attributes->get('jwt')->id)
                ],
                'json' => [
                    "from" => $from->timestamp,
                    "to" => $to->timestamp
                ]
            ]);

            $decodedJson = json_decode($response->getBody(), true);

            return response()->json($decodedJson);
        } catch (RequestException $e) {
            if ($e->hasResponse()) {
                $statusCode = $e->getResponse()->getStatusCode();
                $responseBody = $e->getResponse()->getBody()->getContents();
                $decodedJson = json_decode($responseBody, true);
                $message = 'Error al obtener los movimientos de la tarjeta.';

                if (json_last_error() === JSON_ERROR_NONE) {
                    $message .= " " . $decodedJson['message'];
                }
                return response($message, $statusCode);
            } else {
                return response("Error al obtener los movimientos de la tarjeta.", 400);
            }
        } catch (\Exception $e) {
            return self::basicError($e->getMessage());
        }
    }

    /**
     * @OA\Delete(
     *      path="/api/cardCloud/card/{cardId}/unassign-user",
     *      summary="Unassign card from user",
     *      description="Unassign card from user",
     *      tags={"Card Cloud V2"},
     *     security={{"bearerAuth": {}}},
     *
     *      @OA\Parameter(
     *          name="cardId",
     *          in="path",
     *          description="Card ID",
     *          required=true,
     *          @OA\Schema(type="string")
     *      ),
     *
     *      @OA\Response(
     *          response=200,
     *          description="Card unassigned successfully",
     *          @OA\JsonContent(
     *              @OA\Property(property="message", type="string", example="Card unassigned successfully")
     *          )
     *      ),
     *      @OA\Response(
     *          response=404,
     *          description="Card not found or you dont have permission to access it",
     *          @OA\JsonContent(
     *              @OA\Property(property="message", type="string", example="Card not found or you dont have permission to access it")
     *         )
     *      ),
     *      @OA\Response(
     *          response=400,
     *          description="Error unassigning subaccount",
     *          @OA\JsonContent(
     *              @OA\Property(property="message", type="string", example="Error unassigning subaccount")
     *          )
     *      ),
     *
     *      @OA\Response(
     *          response=401,
     *          description="Unauthorized",
     *          @OA\JsonContent(
     *              @OA\Property(property="message", type="string", example="Unauthorized")
     *          )
     *      )
     * )
     */

    public function unassignUser(Request $request, $cardId)
    {
        try {
            $client = new Client();
            $response = $client->request('DELETE', env('CARD_CLOUD_BASE_URL') . '/api/v1/card/' . $cardId . '/user_unassign', [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Bearer ' . CardCloudApi::getToken($request->attributes->get('jwt')->id)
                ]
            ]);

            $decodedJson = json_decode($response->getBody(), true);

            return response()->json(['message' => $decodedJson['message']]);
        } catch (RequestException $e) {
            if ($e->hasResponse()) {
                $statusCode = $e->getResponse()->getStatusCode();
                $responseBody = $e->getResponse()->getBody()->getContents();
                $decodedJson = json_decode($responseBody, true);
                $message = 'Error al desasignar el usuario de la tarjeta.';

                if (json_last_error() === JSON_ERROR_NONE) {
                    $message .= " " . $decodedJson['message'];
                }
                return response($message, $statusCode);
            } else {
                return response("Error al desasignar el usuario de la tarjeta.", 400);
            }
        } catch (\Exception $e) {
            return self::basicError($e->getMessage());
        }
    }


    /**
     * @OA\Post(
     *      path="/api/cardCloud/card/{cardId}/block",
     *      summary="Bloquear tarjeta",
     *      description="Bloquear una tarjeta",
     *      tags={"Card Cloud V2"},
     *      operationId="blockCard",
     *      security={{"bearerAuth": {}}},
     *
     *      @OA\Parameter(
     *          name="cardId",
     *          in="path",
     *          required=true,
     *          description="ID de la tarjeta",
     *          @OA\Schema(type="string")
     *      ),
     *
     *      @OA\Response(
     *          response=200,
     *          description="Tarjeta bloqueada exitosamente",
     *          @OA\JsonContent(
     *              @OA\Property(property="message", type="string", example="La tarjeta ha sido bloqueada.", description="Mensaje de éxito")
     *          )
     *      ),
     *
     *      @OA\Response(
     *         response=400,
     *          description="Error al bloquear la tarjeta",
     *          @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Error al bloquear la tarjeta.", description="Mensaje de error")
     *          )
     *      ),
     *
     *      @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Unauthorized", description="Mensaje de error")
     *         )
     *     )
     *
     * )
     */

    public function blockCard(Request $request, $cardId)
    {
        switch ($request->attributes->get('jwt')->profileId) {
            case 5:
            case 10:
            case 11:
            case 12:
                $allowed = true;
                break;
            case 8:
                $cardAssigned = CardAssigned::where('CardCloudId', $cardId)
                    ->where('UserId', $request->attributes->get('jwt')->id)
                    ->first();
                $allowed = $cardAssigned ? true : false;
                break;
            default:
                $allowed = false;
        }

        if (!$allowed) {
            return response("La tarjeta no está asignada al usuario", 400);
        } else {

            try {
                $client = new Client();
                $response = $client->request('POST', env('CARD_CLOUD_BASE_URL') . '/api/v1/card/' . $cardId . '/block', [
                    'headers' => [
                        'Content-Type' => 'application/json',
                        'Authorization' => 'Bearer ' . CardCloudApi::getToken($request->attributes->get('jwt')->id)
                    ]
                ]);

                $decodedJson = json_decode($response->getBody(), true);

                return response()->json(['message' => "La tarjeta ha sido bloqueada."]);
            } catch (RequestException $e) {
                if ($e->hasResponse()) {
                    $statusCode = $e->getResponse()->getStatusCode();
                    $responseBody = $e->getResponse()->getBody()->getContents();
                    $decodedJson = json_decode($responseBody, true);
                    $message = 'Error al bloquear la tarjeta.';

                    if (json_last_error() === JSON_ERROR_NONE) {
                        $message .= " " . $decodedJson['message'];
                    }
                    return response($message, $statusCode);
                } else {
                    return response("Error al bloquear la tarjeta.", 400);
                }
            } catch (\Exception $e) {
                return self::basicError($e->getMessage());
            } finally {
                $cardAssigned = CardAssigned::where('CardCloudId', $cardId)->first();
                if ($cardAssigned) {
                    $firebaseToken = FirebaseToken::where('UserId', $cardAssigned->UserId)->first();
                    if ($firebaseToken) {
                        $pan = CardCardManagementController::cardPan($cardId);
                        $title = "Tarjeta bloqueada";
                        $body = "Su tarjeta con terminación " . substr($cardAssigned->MaskedPan, -4) . " se ha bloqueado.";
                        $data = ['movementType' => 'CARD_LOCK', 'description' => 'Su tarjeta con terminación ' . substr($cardAssigned->MaskedPan, -4) . ' ha sido bloqueada por usted o por un administrador. Si cree que esto es un error, contacte a soporte.'];
                        FirebaseService::sendPushNotification($firebaseToken->FirebaseToken, $title, $body, $data);
                    }
                }
            }
        }
    }


    /**
     * @OA\Post(
     *      path="/api/cardCloud/card/{cardId}/unblock",
     *      summary="Desbloquear tarjeta",
     *      description="Desbloquear una tarjeta",
     *      tags={"Card Cloud V2"},
     *      operationId="unblockCard",
     *      security={{"bearerAuth": {}}},
     *
     *     @OA\Parameter(
     *          name="cardId",
     *          in="path",
     *          required=true,
     *          description="ID de la tarjeta",
     *          @OA\Schema(type="string")
     *      ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Tarjeta desbloqueada exitosamente",
     *        @OA\JsonContent(
     *            @OA\Property(property="message", type="string", example="La tarjeta ha sido desbloqueada.", description="Mensaje de éxito")
     *        )
     *     ),
     *
     *      @OA\Response(
     *        response=400,
     *        description="Error al desbloquear la tarjeta",
     *       @OA\JsonContent(
     *           @OA\Property(property="message", type="string", example="Error al desbloquear la tarjeta.", description="Mensaje de error")
     *       )
     *     ),
     *
     *      @OA\Response(
     *          response=401,
     *          description="Unauthorized",
     *          @OA\JsonContent(
     *              @OA\Property(property="message", type="string", example="Unauthorized", description="Mensaje de error")
     *          )
     *      )
     *
     *  )
     */
    public function unblockCard(Request $request, $cardId)
    {
        switch ($request->attributes->get('jwt')->profileId) {
            case 5:
            case 10:
            case 11:
            case 12:
                $allowed = true;
                break;
            case 8:
                $cardAssigned = CardAssigned::where('CardCloudId', $cardId)
                    ->where('UserId', $request->attributes->get('jwt')->id)
                    ->first();
                $allowed = $cardAssigned ? true : false;
                break;
            default:
                $allowed = false;
        }

        if (!$allowed) {
            return response("La tarjeta no está asignada al usuario", 400);
        } else {
            try {
                $client = new Client();
                $response = $client->request('POST', env('CARD_CLOUD_BASE_URL') . '/api/v1/card/' . $cardId . '/unblock', [
                    'headers' => [
                        'Content-Type' => 'application/json',
                        'Authorization' => 'Bearer ' . CardCloudApi::getToken($request->attributes->get('jwt')->id)
                    ]
                ]);

                $decodedJson = json_decode($response->getBody(), true);

                return response()->json(['message' => "La tarjeta ha sido desbloqueada."]);
            } catch (RequestException $e) {
                if ($e->hasResponse()) {
                    $statusCode = $e->getResponse()->getStatusCode();
                    $responseBody = $e->getResponse()->getBody()->getContents();
                    $decodedJson = json_decode($responseBody, true);
                    $message = 'Error al desbloquear la tarjeta.';

                    if (json_last_error() === JSON_ERROR_NONE) {
                        $message .= " " . $decodedJson['message'];
                    }
                    return response($message, $statusCode);
                } else {
                    return response("Error al desbloquear la tarjeta.", 400);
                }
            } catch (\Exception $e) {
                return self::basicError($e->getMessage());
            } finally {
                $cardAssigned = CardAssigned::where('CardCloudId', $cardId)->first();
                if ($cardAssigned) {
                    $firebaseToken = FirebaseToken::where('UserId', $cardAssigned->UserId)->first();
                    if ($firebaseToken) {
                        $pan = CardCardManagementController::cardPan($cardId);
                        $title = "Tarjeta desbloqueada";
                        $body = "Su tarjeta con terminación " . substr($pan, -4) . " se ha desbloqueado.";
                        $data = ['movementType' => 'CARD_LOCK', 'description' => 'Su tarjeta con terminación ' . substr($pan, -4) . ' ha sido desbloqueada por usted o por un administrador. Si cree que esto es un error, contacte a soporte.'];
                        FirebaseService::sendPushNotification($firebaseToken->FirebaseToken, $title, $body, $data);
                    }
                }
            }
        }
    }

    public function getInfoByClientId(Request $request, $clientId)
    {
        try {
            $clientId = self::splitClientId($clientId);
            $card = Card::where('CustomerPrefix', $clientId['prefix'])
                ->join('card_pan', 'card_pan.CardId', '=', 'cards.Id')
                ->where('CustomerId', $clientId['number'])
                ->select('cards.Id', 'card_pan.Pan')
                ->first();
            if (!$card) {
                return self::basicError("No se encontró información para el clientId proporcionado");
            }

            $pan = CardPan::where('CardId', $card->Id)->first();
            if (!$pan) {
                return self::basicError("No se encontró información para el clientId proporcionado");
            }

            return response($pan->Pan);
        } catch (\Exception $e) {
            return self::basicError($e->getMessage());
        }
    }

    public function getBalanceByCardId(Request $request, $card)
    {
        try {
            if (!$card) {
                return self::error("El número de tarjeta es requerido");
            }
            if (!preg_match('/^[0-9]{8}$/', $card)) {
                return self::error("El número de tarjeta debe tener 8 dígitos y no puede contener caracteres especiales");
            }

            $cardsResponse = [];

            $card = CardPan::where('card_pan.Pan', 'like', '%' . $card)
                ->join('cards', 'cards.Id', '=', 'card_pan.CardId')
                ->select('cards.UUID as CardCloudId')
                ->first();
            if (!$card) {
                return self::error("No se encontraron tarjetas con la terminación proporcionada.");
            }

            $client = new Client();
            $response = $client->request('GET', env('CARD_CLOUD_BASE_URL') . '/card/' . $card->CardCloudId . '/balance', [
                'headers' => [
                    'Content-Type' => 'application/json'
                ]
            ]);

            $decodedJson = json_decode($response->getBody(), true);

            $cardsResponse[] = [
                'client_id' => $decodedJson['client_id'],
                'balance' => $decodedJson['balance']
            ];


            if (empty($cardsResponse)) {
                return self::error("No se pudo obtener el balance de la tarjeta.");
            }

            return response()->json($cardsResponse);
        } catch (\Exception $e) {
            return self::basicError($e->getMessage());
        }
    }

    public static function splitClientId($input)
    {
        if (preg_match('/^([A-Za-z]+)(\d+)$/', $input, $matches)) {
            $prefix = $matches[1]; // Primer grupo de captura: letras
            $number = (int) $matches[2]; // Segundo grupo de captura: números convertido a entero
            return [
                'prefix' => $prefix,
                'number' => $number,
            ];
        }

        // Retornar null si no coincide el patrón
        return null;
    }
}
