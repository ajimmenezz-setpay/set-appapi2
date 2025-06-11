<?php

namespace App\Http\Controllers\Card;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use GuzzleHttp\Client;
use App\Http\Services\CardCloudApi;
use GuzzleHttp\Exception\RequestException;

class CardManagementController extends Controller
{
    /**
     *  @OA\Get(
     *      path="/api/card/{pan_suffix}",
     *      tags={"Card Cloud"},
     *      summary="Get card details by suffix",
     *      description="Retrieve card details using the last 8 digits of the card number",
     *      operationId="getCardDetails",
     *      security={{"bearerAuth":{}}},
     *
     *      @OA\Parameter(
     *          name="pan_suffix",
     *          in="path",
     *          description="Last 8 digits of the card number",
     *          required=true,
     *          @OA\Schema(type="string")
     *      ),
     *
     *      @OA\Response(
     *          response=200,
     *          description="Card details retrieved successfully",
     *          @OA\JsonContent(
     *              @OA\Property(property="card_id", type="string", example="01922a29-3edb-7283-895a-5aebfd93257d"),
     *              @OA\Property(property="card_external_id", type="string", example="01922a29-4405-8d62-64d2-ceb079990479"),
     *              @OA\Property(property="card_type", type="string", example="physical"),
     *              @OA\Property(property="brand", type="string", example="MASTER"),
     *              @OA\Property(property="bin", type="string", example="00000000"),
     *              @OA\Property(property="pan", type="string", example="1122334455667788"),
     *              @OA\Property(property="client_id", type="string", example="XX0000001"),
     *              @OA\Property(property="masked_pan", type="string", example="XXXX XXXX XXXX 6694"),
     *              @OA\Property(property="balance", type="string", example="0.00"),
     *              @OA\Property(property="clabe", type="string", nullable=true, example=null),
     *              @OA\Property(property="status", type="string", example="BLOCKED"),

     *              @OA\Property(
     *                  property="substatus",
     *                  type="object",
     *                  @OA\Property(property="id", type="integer", example=0),
     *                  @OA\Property(property="name", type="string", example="NORMAL"),
     *                  @OA\Property(property="description", type="string", example="This card is already to use")
     *              ),
     *
     *              @OA\Property(
     *                  property="setups",
     *                  type="object",
     *                  @OA\Property(property="Id", type="integer", example=26302),
     *                  @OA\Property(property="CardId", type="integer", example=35115),
     *                  @OA\Property(property="Status", type="string", example="BLOCKED"),
     *                  @OA\Property(property="StatusReason", type="string", example="INITIAL_BLOCKED"),
     *                  @OA\Property(property="Ecommerce", type="integer", example=1),
     *                  @OA\Property(property="International", type="integer", example=0),
     *                  @OA\Property(property="Stripe", type="integer", example=1),
     *                  @OA\Property(property="Wallet", type="integer", example=1),
     *                  @OA\Property(property="Withdrawal", type="integer", example=1),
     *                  @OA\Property(property="Contactless", type="integer", example=1),
     *                  @OA\Property(property="PinOffline", type="integer", example=1),
     *                  @OA\Property(property="PinOnUs", type="integer", example=0)
     *              )
     *          )
     *     ),
     *
     *      @OA\Response(
     *          response=400,
     *          description="Invalid card suffix format",
     *          @OA\MediaType(mediaType="text/plain", @OA\Schema(type="string", example="Invalid card suffix format. It must be 8 digits."))
     *      ),
     *
     *      @OA\Response(
     *          response=401,
     *          description="Unauthorized",
     *          @OA\MediaType(mediaType="text/plain", @OA\Schema(type="string", example="Unauthorized"))
     *      ),
     *
     *      @OA\Response(
     *         response=500,
     *         description="Internal Server Error",
     *         @OA\MediaType(mediaType="text/plain", @OA\Schema(type="string", example="Error retrieving card details: {error_message}"))
     *     )
     * )
     */

    public function show(Request $request, $pan_suffix)
    {
        if (!preg_match('/^\d{8}$/', $pan_suffix)) {
            return response('Invalid card suffix format. It must be 8 digits.', 400);
        }

        try {
            $client = new Client();
            $response = $client->request('GET', env('CARD_CLOUD_BASE_URL') . '/api/b1/card/' . $pan_suffix, [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Bearer ' . CardCloudApi::getToken($request->attributes->get('jwt')->id),
                ]
            ]);

            $decodedJson = json_decode($response->getBody(), true);

            return response()->json($decodedJson, $response->getStatusCode());
        } catch (RequestException $e) {
            if ($e->getResponse()) {
                $decodedJson = json_decode($e->getResponse()->getBody(), true);
                if (isset($decodedJson['message'])) {
                    return response($decodedJson['message'], $e->getResponse()->getStatusCode());
                } else {
                    return response('Error retrieving card details: ' . $e->getMessage(), 500);
                }
            }
            return response('Error retrieving card details: ' . $e->getMessage(), 500);
        } catch (\Exception $e) {
            return response("Error retrieving card details: " . $e->getMessage(), 500);
        }
    }


    /**
     *  @OA\Get(
     *      path="/api/card/{pan_suffix}/balance",
     *      tags={"Card Cloud"},
     *      summary="Get card balance by suffix",
     *      description="Retrieve card balance using the last 8 digits of the card number",
     *      operationId="getCardBalance",
     *      security={{"bearerAuth":{}}},
     *
     *      @OA\Parameter(
     *          name="pan_suffix",
     *          in="path",
     *          description="Last 8 digits of the card number",
     *          required=true,
     *          @OA\Schema(type="string")
     *      ),
     *
     *      @OA\Response(
     *          response=200,
     *          description="Card balance retrieved successfully",
     *          @OA\JsonContent(
     *              @OA\Property(property="balance", type="string", example="100.00")
     *          )
     *     ),
     *
     *      @OA\Response(
     *          response=400,
     *          description="Invalid card suffix format",
     *          @OA\MediaType(mediaType="text/plain", @OA\Schema(type="string", example="Invalid card suffix format. It must be 8 digits."))
     *      ),
     *
     *      @OA\Response(
     *          response=401,
     *          description="Unauthorized",
     *          @OA\MediaType(mediaType="text/plain", @OA\Schema(type="string", example="Unauthorized"))
     *      ),
     *
     *      @OA\Response(
     *         response=500,
     *         description="Internal Server Error",
     *         @OA\MediaType(mediaType="text/plain", @OA\Schema(type="string", example="Error retrieving card details: {error_message}"))
     *     )
     * )
     */


    public function getBalance(Request $request, $pan_suffix)
    {
        if (!preg_match('/^\d{8}$/', $pan_suffix)) {
            return response('Invalid card suffix format. It must be 8 digits.', 400);
        }

        try {
            $client = new Client();
            $response = $client->request('GET', env('CARD_CLOUD_BASE_URL') . '/api/b1/card/' . $pan_suffix . '/balance', [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Bearer ' . CardCloudApi::getToken($request->attributes->get('jwt')->id),
                ]
            ]);

            $decodedJson = json_decode($response->getBody(), true);

            return response()->json($decodedJson, $response->getStatusCode());
        } catch (RequestException $e) {
            if ($e->getResponse()) {
                $decodedJson = json_decode($e->getResponse()->getBody(), true);
                if (isset($decodedJson['message'])) {
                    return response($decodedJson['message'], $e->getResponse()->getStatusCode());
                } else {
                    return response('Error retrieving card balance: ' . $e->getMessage(), 500);
                }
            }
            return response('Error retrieving card balance: ' . $e->getMessage(), 500);
        } catch (\Exception $e) {
            return response("Error retrieving card balance: " . $e->getMessage(), 500);
        }
    }


    /**
     *  @OA\Get(
     *      path="/api/card/{pan_suffix}/movements",
     *      tags={"Card Cloud"},
     *      summary="Get card movements by suffix",
     *      description="Retrieve card movements using the last 8 digits of the card number",
     *      operationId="getCardMovements",
     *      security={{"bearerAuth":{}}},
     *
     *      @OA\Parameter(
     *          name="pan_suffix",
     *          in="path",
     *          description="Last 8 digits of the card number",
     *          required=true,
     *          @OA\Schema(type="string")
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
     *          response=400,
     *          description="Invalid card suffix format",
     *          @OA\MediaType(mediaType="text/plain", @OA\Schema(type="string", example="Invalid card suffix format. It must be 8 digits."))
     *      ),
     *
     *      @OA\Response(
     *          response=401,
     *          description="Unauthorized",
     *          @OA\MediaType(mediaType="text/plain", @OA\Schema(type="string", example="Unauthorized"))
     *      ),
     *
     *      @OA\Response(
     *         response=500,
     *         description="Internal Server Error",
     *         @OA\MediaType(mediaType="text/plain", @OA\Schema(type="string", example="Error retrieving card movements: {error_message}"))
     *      )
     * )
     */



    public function movements(Request $request, $pan_suffix)
    {
        if (!preg_match('/^\d{8}$/', $pan_suffix)) {
            return response('Invalid card suffix format. It must be 8 digits.', 400);
        }

        $request->validate([
            'from' => 'integer|sometimes|nullable',
            'to' => 'integer|sometimes|nullable',
        ]);

        try {
            $client = new Client();
            $response = $client->request('GET', env('CARD_CLOUD_BASE_URL') . '/api/b1/card/' . $pan_suffix . '/movements', [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Bearer ' . CardCloudApi::getToken($request->attributes->get('jwt')->id),
                ],
                'json' => [
                    "from" => $request->input('from'),
                    "to" => $request->input('to')
                ]
            ]);

            $decodedJson = json_decode($response->getBody(), true);

            return response()->json($decodedJson, $response->getStatusCode());
        } catch (RequestException $e) {
            if ($e->getResponse()) {
                $decodedJson = json_decode($e->getResponse()->getBody(), true);
                if (isset($decodedJson['message'])) {
                    return response($decodedJson['message'], $e->getResponse()->getStatusCode());
                } else {
                    return response('Error retrieving card movements: ' . $e->getMessage(), 500);
                }
            }
            return response('Error retrieving card movements: ' . $e->getMessage(), 500);
        } catch (\Exception $e) {
            return response("Error retrieving card movements: " . $e->getMessage(), 500);
        }
    }
}
