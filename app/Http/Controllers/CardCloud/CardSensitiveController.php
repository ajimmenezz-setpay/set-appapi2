<?php

namespace App\Http\Controllers\CardCloud;

use App\Http\Controllers\Controller;
use Exception;
use Illuminate\Http\Request;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use App\Models\CardCloud\CardAssigned;
use App\Http\Services\CardCloudApi;

class CardSensitiveController extends Controller
{

    /**
     *  @OA\Get(
     *      path="/api/v1/card/{cardId}/sensitive",
     *      tags={"Card Cloud"},
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
            if (CardAssigned::where('CardCloudId', $cardId)->where('UserId', $request->attributes->get('jwt')->id)->count() == 0) {
                throw new Exception('El usuario no tiene acceso a la tarjeta.');
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
                }
            }
        } catch (Exception $e) {
            return self::basicError($e->getMessage());
        }
    }

    /**
     *  @OA\Get(
     *      path="/api/v1/card/{cardId}/cvv",
     *      tags={"Card Cloud"},
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

                    var_dump($decodedJson);
                } catch (RequestException $e) {
                    if ($e->hasResponse()) {
                        $statusCode = $e->getResponse()->getStatusCode();
                        $responseBody = $e->getResponse()->getBody()->getContents();
                        $decodedJson = json_decode($responseBody, true);
                        $message = 'No hemos posido obtener el cvv de la tarjeta.';

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
