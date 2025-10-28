<?php

namespace App\Http\Controllers\CardCloud;

use Exception;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Notifications\FirebasePushController as FirebaseService;
use App\Http\Controllers\Card\CardManagementController as CardCardManagementController;

use App\Http\Services\CardCloudApi;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

use App\Models\CardCloud\CardAssigned;
use App\Models\CardCloud\NipView;
use App\Models\CardCloud\Card;
use App\Models\CardCloud\Credit;
use App\Models\CardCloud\CreditWallet;
use App\Models\Users\FirebaseToken;

class CardSensitiveController extends Controller
{

    /**
     *  @OA\Get(
     *      path="/api/cardCloud/card/{cardId}/sensitive",
     *      tags={"Card Cloud V2"},
     *      summary="Obtener datos sensibles de la tarjeta",
     *      description="Obtener datos sensibles de la tarjeta",
     *      operationId="sensitive",
     *      @OA\Parameter(
     *          name="cardId",
     *          in="path",
     *          description="ID de la tarjeta",
     *          required=true,
     *          @OA\Schema(
     *              type="string"
     *          )
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Datos sensibles de la tarjeta",
     *          @OA\JsonContent(
     *              type="object",
     *              @OA\Property(property="pan", type="string", example="5161520880102992"),
     *              @OA\Property(property="expiration_date", type="string", example="2029-08-01"),
     *              @OA\Property(property="pin", type="string", example="1911")
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
     *         response=400,
     *          description="Bad Request",
     *          @OA\MediaType(mediaType="text/plain", @OA\Schema(type="string", example="Bad Request"))
     *      ),
     *
     *      @OA\Response(
     *        response=404,
     *         description="Not Found",
     *         @OA\MediaType(mediaType="text/plain", @OA\Schema(type="string", example="Not Found"))
     *     ),
     *
     *      @OA\Response(
     *        response=500,
     *        description="Internal Server Error",
     *        @OA\MediaType(mediaType="text/plain", @OA\Schema(type="string", example="Internal Server Error"))
     *     )
     * )
     *
     *
     */

    public function sensitive(Request $request, $cardId)
    {
        try {
            switch ($request->attributes->get('jwt')->profileId) {
                case 5:
                    $allowed = true;
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
                throw new Exception("No tienes permisos para ver los datos sensibles de la tarjeta");
            } else {

                try {

                    $client = new Client();
                    $response = $client->request('GET', env('CARD_CLOUD_BASE_URL') . '/api/v1/card/' . $cardId . '/sensitive', [
                        'headers' => [
                            'Content-Type' => 'application/json',
                            'Authorization' => 'Bearer ' . CardCloudApi::getToken($request->attributes->get('jwt')->id),
                        ]
                    ]);

                    $decodedJson = json_decode($response->getBody(), true);

                    if (isset($decodedJson['sensitive_data_raw'])) {
                        return response()->json($decodedJson['sensitive_data_raw']);
                    } else {
                        return self::basicError('No hemos podido obtener los datos sensibles de la tarjeta.');
                    }
                } catch (RequestException $e) {
                    if ($e->hasResponse()) {
                        $statusCode = $e->getResponse()->getStatusCode();
                        $responseBody = $e->getResponse()->getBody()->getContents();
                        $decodedJson = json_decode($responseBody, true);
                        $message = 'No hemos podido obtener los datos sensibles de la tarjeta.';

                        if (json_last_error() === JSON_ERROR_NONE) {
                            $message .= " " . $decodedJson['message'];
                        }
                        throw new Exception($message);
                    } else {
                        throw new Exception('No hemos podido obtener los datos sensibles de la tarjeta.');
                    }
                } catch (Exception $e) {
                    throw new Exception('No hemos podido obtener los datos sensibles de la tarjeta.');
                } finally {
                    $cardAssigned = CardAssigned::where('CardCloudId', $cardId)->first();
                    Log::info('Card Assigned for sensitive data notification', ['cardAssigned' => $cardAssigned]);
                    if ($cardAssigned) {
                        Log::info('Sending push notification for sensitive data access', ['userId' => $cardAssigned->UserId]);
                        $firebaseToken = FirebaseToken::where('UserId', $cardAssigned->UserId)->first();
                        if ($firebaseToken) {
                            $pan = CardCardManagementController::cardPan($cardId);
                            $title = "Datos sensibles";
                            $body = "Se han solicitado los datos sensibles de la tarjeta con terminación " . substr($pan, -4) . ".";
                            $data = ['movementType' => 'PIN_CHANGE', 'description' => 'Se han solicitado los datos sensibles de la tarjeta con terminación ' . substr($pan, -4) . '. Si usted no realizó esta acción, contacte a soporte.'];
                            FirebaseService::sendPushNotification($firebaseToken->FirebaseToken, $title, $body, $data);
                        }
                    }
                }
            }
        } catch (Exception $e) {
            return self::basicError($e->getMessage());
        }
    }


    public function pin(Request $request, $cardId)
    {
        try {

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
                throw new Exception("No tienes permisos para ver los datos sensibles de la tarjeta");
            } else {

                try {

                    $client = new Client();
                    $response = $client->request('GET', env('CARD_CLOUD_BASE_URL') . '/api/v1/cards_management/' . $cardId . '/pin', [
                        'headers' => [
                            'Content-Type' => 'application/json',
                            'Authorization' => 'Bearer ' . CardCloudApi::getToken($request->attributes->get('jwt')->id),
                        ]
                    ]);

                    $decodedJson = json_decode($response->getBody(), true);

                    if (isset($decodedJson['pin'])) {
                        return response()->json([
                            'pin' => $decodedJson['pin']
                        ]);
                    } else {
                        return self::basicError('No hemos podido obtener el nip de la tarjeta.');
                    }
                } catch (RequestException $e) {
                    if ($e->hasResponse()) {
                        $statusCode = $e->getResponse()->getStatusCode();
                        $responseBody = $e->getResponse()->getBody()->getContents();
                        $decodedJson = json_decode($responseBody, true);
                        $message = 'No hemos podido obtener el nip de la tarjeta.';

                        if (json_last_error() === JSON_ERROR_NONE) {
                            $message .= " " . $decodedJson['message'];
                        }
                        throw new Exception($message);
                    } else {
                        throw new Exception('No hemos podido obtener el nip de la tarjeta.');
                    }
                } catch (Exception $e) {
                    throw new Exception('No hemos podido obtener el nip de la tarjeta.');
                }
            }
        } catch (Exception $e) {
            return self::basicError($e->getMessage());
        }
    }


    /**
     *  @OA\Post(
     *      path="/api/cardCloud/card/{cardId}/nip_view",
     *      tags={"Card Cloud V2"},
     *      summary="Registrar vista de NIP",
     *      description="Registrar vista de NIP",
     *      operationId="nipView",
     *
     *      @OA\Parameter(
     *          name="cardId",
     *          in="path",
     *          description="ID de la tarjeta",
     *          required=true,
     *          @OA\Schema(
     *             type="string"
     *          )
     *      ),
     *
     *      @OA\Response(
     *         response=201,
     *          description="Vista de NIP registrada exitosamente",
     *          @OA\JsonContent(
     *              type="object",
     *              @OA\Property(property="message", type="string", example="NIP view logged successfully.")
     *          )
     *      ),
     *
     *     @OA\Response(
     *         response=400,
     *         description="Bad Request",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="message", type="string", example="Bad Request")
     *         )
     *     ),
     *
     *    @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="message", type="string", example="Unauthorized")
     *         )
     *     )
     *  )
     */
    public function nipView(Request $request, $cardId)
    {
        try {
            NipView::create([
                'UserId' => $request->attributes->get('jwt')->id,
                'CardId' => $cardId
            ]);
            return response()->json(['message' => 'NIP view logged successfully.'], 201);
        } catch (Exception $e) {
            Log::error("Error en nipView: " . $e->getMessage(), [
                'userId' => $request->attributes->get('jwt')->id,
                'cardId' => $cardId,
                'profileId' => $request->attributes->get('jwt')->profileId,
                'timestamp' => now()
            ]);
            return self::basicError($e->getMessage());
        }
    }

    /**
     *  @OA\Get(
     *      path="/api/cardCloud/card/{cardId}/cvv",
     *      tags={"Card Cloud V2"},
     *      summary="Obtener CVV dinámico de la tarjeta",
     *      description="Obtener CVV dinámico de la tarjeta",
     *      operationId="dynamicCvv",
     *
     *      @OA\Parameter(
     *          name="cardId",
     *          in="path",
     *          description="ID de la tarjeta",
     *          required=true,
     *          @OA\Schema(
     *              type="string"
     *          )
     *      ),
     *
     *     @OA\Response(
     *         response=200,
     *          description="CVV dinámico de la tarjeta",
     *          @OA\JsonContent(
     *             type="object",
     *            @OA\Property(property="cvv", type="string", example="275"),
     *            @OA\Property(property="expiration", type="integer", example=1880236800)
     *           )
     *        ),
     *
     *     @OA\Response(
     *        response=401,
     *        description="Unauthorized",
     *       @OA\MediaType(mediaType="text/plain", @OA\Schema(type="string", example="Unauthorized"))
     *     ),
     *
     *      @OA\Response(
     *          response=400,
     *          description="Bad Request",
     *          @OA\MediaType(mediaType="text/plain", @OA\Schema(type="string", example="Bad Request"))
     *      ),
     *
     * )
     *
     */

    public function dynamicCvv(Request $request, $cardId)
    {
        try {
            if (CardAssigned::where('CardCloudId', $cardId)->where('UserId', $request->attributes->get('jwt')->id)->count() == 0) {
                throw new Exception('El usuario no tiene acceso a la tarjeta.');
            } else {

                try {

                    $client = new Client();
                    $response = $client->request('GET', env('CARD_CLOUD_BASE_URL') . '/api/v1/card/' . $cardId . '/cvv', [
                        'headers' => [
                            'Content-Type' => 'application/json',
                            'Authorization' => 'Bearer ' . CardCloudApi::getToken($request->attributes->get('jwt')->id),
                        ]
                    ]);

                    $decodedJson = json_decode($response->getBody(), true);

                    if (isset($decodedJson['cvv'])) {
                        return response()->json($decodedJson);
                    } else {
                        return self::basicError('No hemos podido obtener el cvv de la tarjeta.');
                    }
                } catch (RequestException $e) {
                    if ($e->hasResponse()) {
                        $statusCode = $e->getResponse()->getStatusCode();
                        $responseBody = $e->getResponse()->getBody()->getContents();
                        $decodedJson = json_decode($responseBody, true);
                        $message = 'No hemos podido obtener el cvv de la tarjeta.';

                        if (json_last_error() === JSON_ERROR_NONE) {
                            $message .= " " . $decodedJson['message'];
                        }
                        throw new Exception($message);
                    } else {
                        throw new Exception('No hemos podido obtener el cvv de la tarjeta.');
                    }
                } catch (Exception $e) {
                    throw new Exception('No hemos podido obtener el cvv de la tarjeta.');
                }
            }
        } catch (Exception $e) {
            return self::basicError($e->getMessage());
        }
    }
}
