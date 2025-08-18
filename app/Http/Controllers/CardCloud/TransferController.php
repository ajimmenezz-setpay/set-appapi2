<?php

namespace App\Http\Controllers\CardCloud;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Security\GoogleAuth;
use Illuminate\Http\Request;
use GuzzleHttp\Client;
use App\Http\Services\CardCloudApi;
use GuzzleHttp\Exception\RequestException;
use App\Models\CardCloud\CardAssigned;

class TransferController extends Controller
{
    /**
     * @OA\Post(
     *      path="/api/cardCloud/card/transfer",
     *      tags={"Card Cloud V2"},
     *      summary="Transfer money between cards",
     *      description="Transfer money between cards",
     *      security={{"bearerAuth":{}}},
     *
     *
     *      @OA\RequestBody(
     *          required=true,
     *          @OA\JsonContent(
     *              required={"source_card", "destination_card", "amount", "concept"},
     *              @OA\Property(property="source_card", type="string", example="0190760f-acf6-7800-bce8-1b2a451c3427"),
     *              @OA\Property(property="destination_card", type="string", example="0190760f-acf6-7800-bce8-1b2a451c3427"),
     *              @OA\Property(property="amount", type="number", example=100),
     *              @OA\Property(property="concept", type="string", example="Transferencia de dinero"),
     *              @OA\Property(property="auth_code", type="string", example="123456")
     *          )
     *      ),
     *
     *      @OA\Response(
     *          response=200,
     *          description="Transfer completed",
     *          @OA\JsonContent(
     *              @OA\Property(property="new_balance", type="number", example=10.12),
     *              @OA\Property(property="movement", type="object",
     *                  @OA\Property(property="movement_order", type="number", example=107631),
     *                  @OA\Property(property="movement_id", type="string", example="01944de9-4c8a-700c-b31f-a7895fb50244"),
     *                  @OA\Property(property="type", type="string", example="TRANSFER"),
     *                  @OA\Property(property="description", type="string", example="Transferencia de dinero"),
     *                  @OA\Property(property="reference", type="string", example=""),
     *                  @OA\Property(property="amount", type="number", example=-100),
     *                  @OA\Property(property="balance", type="number", example=10.12),
     *                  @OA\Property(property="date", type="number", example=1736473922),
     *                  @OA\Property(property="card", type="object",
     *                      @OA\Property(property="card_id", type="string", example="0190760f-acf6-71e0-bce8-1b2a762c3427"),
     *                      @OA\Property(property="masked_pan", type="string", example="516152XXXXXX1111"),
     *                      @OA\Property(property="bin", type="string", example="22221111")
     *                  )
     *             )
     *         )
     *     ),
     *
     *      @OA\Response(
     *          response=400,
     *          description="Error transferring money",
     *          @OA\MediaType(mediaType="text/plain", @OA\Schema(type="string", example="Error transferring money"))
     *      ),
     *
     *      @OA\Response(
     *          response=401,
     *          description="Unauthorized",
     *          @OA\MediaType(mediaType="text/plain", @OA\Schema(type="string", example="Unauthorized"))
     *      )
     * )
     *
     */

    public function cardTransfer(Request $request)
    {
        try {
            $this->validate($request, [
                'source_card' => 'required',
                'destination_card' => 'required',
                'amount' => 'required|numeric',
                'concept' => 'required|max:120'
            ], [
                'source_card.required' => 'La tarjeta de origen es requerida',
                'destination_card.required' => 'La tarjeta de destino es requerida',
                'amount.required' => 'El monto a transferir es requerido',
                'amount.numeric' => 'El monto a transferir debe ser un número',
                'concept.required' => 'El concepto de la transferencia es requerido',
                'concept.max' => 'El concepto de la transferencia no debe exceder los 120 caracteres'
            ]);

            if ($request->has('auth_code')) {
                $this->validate($request, [
                    'auth_code' => 'required|min:6|max:6'
                ], [
                    'auth_code.required' => 'El código de autenticación es requerido.',
                    'auth_code.min' => 'El código de autenticación debe tener 6 caracteres.',
                    'auth_code.max' => 'El código de autenticación debe tener 6 caracteres.'
                ]);

                GoogleAuth::authorized($request->attributes->get('jwt')->id, $request->auth_code);
            }

            switch ($request->attributes->get('jwt')->profileId) {
                case 5:
                    $allowed = true;
                    break;
                case 8:
                    $cardAssigned = CardAssigned::where('CardCloudId', $request->source_card)
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

                $client = new Client();
                $response = $client->request('POST', env('CARD_CLOUD_BASE_URL') . '/api/v1/card_transfer', [
                    'headers' => [
                        'Content-Type' => 'application/json',
                        'Authorization' => 'Bearer ' . CardCloudApi::getToken($request->attributes->get('jwt')->id),
                    ],
                    'json' => [
                        'source_card' => $request->source_card,
                        'destination_card' => $request->destination_card,
                        'amount' => $request->amount,
                        'concept' => $request->concept
                    ]
                ]);

                $decodedJson = json_decode($response->getBody(), true);

                return response()->json($decodedJson);
            }
        } catch (RequestException $e) {
            if ($e->hasResponse()) {
                $statusCode = $e->getResponse()->getStatusCode();
                $responseBody = $e->getResponse()->getBody()->getContents();
                $decodedJson = json_decode($responseBody, true);
                $message = 'Error al realizar la transferencia.';

                if (json_last_error() === JSON_ERROR_NONE) {
                    $message .= " " . $decodedJson['message'];
                }
                return response($message, $statusCode);
            } else {
                return response("Error al realizar la transferencia.", 400);
            }
        } catch (\Exception $e) {
            return self::basicError($e->getMessage());
        }
    }

    /**
     * @OA\Post(
     *     path="/api/cardCloud/card/{cardId}/deposit",
     *     tags={"Card Cloud V2"},
     *     summary="Depositar fondos",
     *     description="Permite depositar fondos en una tarjeta específica desde la subcuenta padre.",
     *     operationId="depositFunds",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *        name="cardId",
     *        in="path",
     *        required=true,
     *        description="ID de la tarjeta",
     *        @OA\Schema(type="string")
     *     ),
     *
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"amount","concept"},
     *             @OA\Property(property="amount", type="number", format="float", example=100.00),
     *             @OA\Property(property="concept", type="string", example="Depósito de prueba")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Depósito realizado con éxito",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Depósito realizado con éxito")
     *         )
     *     ),
     *
     *      @OA\Response(
     *         response=400,
     *         description="Error al realizar el depósito",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="Error al realizar el depósito.")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=500,
     *         description="Error interno del servidor",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="Error interno del servidor.")
     *         )
     *     )
     * )
     */

    public function deposit(Request $request, $cardId)
    {
        try {
            $this->validate($request, [
                'amount' => 'required|numeric',
                'concept' => 'required|max:120'
            ], [
                'amount.required' => 'El monto a depositar es requerido',
                'amount.numeric' => 'El monto a depositar debe ser un número',
                'concept.required' => 'El concepto del depósito es requerido',
                'concept.max' => 'El concepto del depósito no debe exceder los 120 caracteres'
            ]);

            $client = new Client();
            $response = $client->request('POST', env('CARD_CLOUD_BASE_URL') . '/api/v1/card/' . $cardId . '/deposit', [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Bearer ' . CardCloudApi::getToken($request->attributes->get('jwt')->id),
                ],
                'json' => [
                    'amount' => $request->amount,
                    'description' => $request->concept
                ]
            ]);

            $decodedJson = json_decode($response->getBody(), true);

            return response()->json($decodedJson);
        } catch (RequestException $e) {
            if ($e->hasResponse()) {
                $statusCode = $e->getResponse()->getStatusCode();
                $responseBody = $e->getResponse()->getBody()->getContents();
                $decodedJson = json_decode($responseBody, true);
                $message = 'Error al realizar el depósito.';

                if (json_last_error() === JSON_ERROR_NONE) {
                    $message .= " " . $decodedJson['message'];
                }

                return response()->json([
                    'error' => $message
                ], $statusCode);
            } else {
                return response()->json([
                    'error' => "Error al realizar el depósito."
                ], 400);
            }
        } catch (\Exception $e) {
            return self::error($e->getMessage());
        }
    }

    /**
     * @OA\Post(
     *     path="/api/cardCloud/card/{cardId}/reverse",
     *     tags={"Card Cloud V2"},
     *     summary="Reversar fondos",
     *     description="Permite retornar recursos de una tarjeta hacia la subcuenta padre.",
     *     operationId="reverseFunds",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *        name="cardId",
     *        in="path",
     *        required=true,
     *        description="ID de la tarjeta",
     *        @OA\Schema(type="string")
     *     ),
     *
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"amount","concept"},
     *             @OA\Property(property="amount", type="number", format="float", example=50.00),
     *             @OA\Property(property="concept", type="string", example="Reversa de prueba")
     *         )
     *     ),
     *
     *      @OA\Response(
     *         response=200,
     *         description="Reversa realizada con éxito",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Reversa realizada con éxito")
     *         )
     *     ),
     *
     *      @OA\Response(
     *         response=400,
     *         description="Error al realizar la reversa",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="Error al realizar la reversa.")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=500,
     *         description="Error interno del servidor",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="Error interno del servidor.")
     *         )
     *     )
     * )
     */

    public function reverse(Request $request, $cardId)
    {
        try {
            $this->validate($request, [
                'amount' => 'required|numeric',
                'concept' => 'required|max:120'
            ], [
                'amount.required' => 'El monto a revertir es requerido',
                'amount.numeric' => 'El monto a revertir debe ser un número',
                'concept.required' => 'El concepto de la reversa es requerido',
                'concept.max' => 'El concepto de la reversa no debe exceder los 120 caracteres'
            ]);

            $client = new Client();
            $response = $client->request('POST', env('CARD_CLOUD_BASE_URL') . '/api/v1/card/' . $cardId . '/reverse', [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Bearer ' . CardCloudApi::getToken($request->attributes->get('jwt')->id),
                ],
                'json' => [
                    'amount' => $request->amount,
                    'description' => $request->concept
                ]
            ]);

            $decodedJson = json_decode($response->getBody(), true);

            return response()->json($decodedJson);
        } catch (RequestException $e) {
            if ($e->hasResponse()) {
                $statusCode = $e->getResponse()->getStatusCode();
                $responseBody = $e->getResponse()->getBody()->getContents();
                $decodedJson = json_decode($responseBody, true);
                $message = 'Error al realizar el reverso.';

                if (json_last_error() === JSON_ERROR_NONE) {
                    $message .= " " . $decodedJson['message'];
                }

                return response()->json([
                    'error' => $message
                ], $statusCode);
            } else {
                return response()->json([
                    'error' => "Error al realizar el reverso."
                ], 400);
            }
        } catch (\Exception $e) {
            return self::error($e->getMessage());
        }
    }
}
