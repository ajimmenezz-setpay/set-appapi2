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

            if ($this->validateAlreadyAssigned($decodedJson['card_id'])) {
                return response("La tarjeta ya está asignada a un usuario", 400);
            }

            if (!$this->validateUserCompany($decodedJson['subaccount_id'], $request->attributes->get('jwt')->id)) {
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

    private function validateUserCompany($subaccountId, $userId)
    {
        $company = CompanyProjection::where('Services', 'like', '%' . $subaccountId . '%')->first();
        if (!$company) return false;

        $userCompany = CompaniesUsers::where('CompanyId', $company->Id)->where('UserId', $userId)->first();
        if (!$userCompany) return false;

        return true;
    }

    private function validateAlreadyAssigned($cardId)
    {
        return CardAssigned::where('CardCloudId', $cardId)->count() > 0;
    }
}
