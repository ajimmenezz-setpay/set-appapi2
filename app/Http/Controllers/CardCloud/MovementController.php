<?php

namespace App\Http\Controllers\CardCloud;

use App\Http\Controllers\Controller;
use App\Models\CardCloud\CardAssigned;
use App\Http\Services\CardCloudApi;
use Illuminate\Http\Request;
use GuzzleHttp\Client;

class MovementController extends Controller
{
    /**
     * @OA\Get(
     *      path="/api/cardCloud/movement/{uuid}",
     *      tags={"Card Cloud V2"},
     *      summary="Show movement",
     *      description="Show movement",
     *      security={{"bearerAuth":{}}},
     *
     *      @OA\Parameter(
     *          name="uuid",
     *          in="path",
     *          description="Movement UUID",
     *          required=true,
     *          @OA\Schema(type="string")
     *     ),
     *
     *      @OA\Response(
     *          response=200,
     *          description="Movement",
     *          @OA\JsonContent(
     *              @OA\Property(property="movement_id", type="string", example="01935c81-abe0-7238-8635-46a8486259be"),
     *              @OA\Property(property="date", type="integer", example=1732423822),
     *              @OA\Property(property="type", type="string", example="PURCHASE"),
     *              @OA\Property(property="amount", type="string", example="-429.00"),
     *              @OA\Property(property="balance", type="string", example="325.73"),
     *              @OA\Property(property="authorization_code", type="string", example="324411"),
     *              @OA\Property(property="description", type="string", example="COMPRA EN TIENDA"),
     *              @OA\Property(property="status", type="string", example="Approved"),
     *              @OA\Property(property="client_id", type="string", example="SP0001275")
     *          )
     *      ),
     *
     *      @OA\Response(
     *         response=400,
     *          description="Error al obtener el movimiento",
     *          @OA\MediaType(mediaType="text/plain", @OA\Schema(type="string", example="Error al obtener el movimiento"))
     *      ),
     *
     *      @OA\Response(
     *          response=401,
     *          description="Unauthorized",
     *          @OA\MediaType(mediaType="text/plain", @OA\Schema(type="string", example="Unauthorized"))
     *      )
     * )
     */

    public function show(Request $request, $uuid)
    {
        try {
            $client = new Client();
            $response = $client->request('GET', env('CARD_CLOUD_BASE_URL') . '/api/v1/card/movement/' . $uuid, [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Bearer ' . CardCloudApi::getToken($request->attributes->get('jwt')->id),
                ]
            ]);

            $decodedJson = json_decode($response->getBody(), true);

            return response()->json($decodedJson);
        } catch (\Exception $e) {
            return response("Error al obtener el movimiento. " . $e->getMessage(), 400);
        }
    }
}
