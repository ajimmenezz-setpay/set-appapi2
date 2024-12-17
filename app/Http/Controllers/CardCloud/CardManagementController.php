<?php

namespace App\Http\Controllers\CardCloud;

use App\Http\Controllers\Controller;
use App\Models\CardCloud\CardAssigned;
use Illuminate\Http\Request;
use GuzzleHttp\Client;
use App\Http\Services\CardCloudApi;
use GuzzleHttp\Exception\RequestException;

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
                    return response("Error updating NIP.", 400);
                }
            }
        }
    }
}
