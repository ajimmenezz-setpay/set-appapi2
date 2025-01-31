<?php

namespace App\Http\Controllers\Report\CardCloud;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Users\Validate;
use App\Http\Services\CardCloudApi;
use Illuminate\Http\Request;

class CardStatus extends Controller
{

    /**
     * @OA\Get(
     *  path="/api/reports/card-cloud/card-status",
     *  tags={"Reports"},
     *  summary="Get card status and balance from card cloud",
     *  description="Get card status and balance from card cloud",
     *  operationId="cardStatus",
     *  security={{"bearerAuth":{}}},
     *
     *  @OA\Response(
     *      response=200,
     *     description="Card status and balance from card cloud",
     *      @OA\JsonContent(
     *          @OA\Property(property="enviroment", type="string", example="GBS"),
     *          @OA\Property(property="company", type="string", example="GBS1"),
     *          @OA\Property(property="client_id", type="string", example="GB0000001"),
     *          @OA\Property(property="masked_pan", type="string", example="516152XXXXXX0546"),
     *          @OA\Property(property="type", type="string", example="virtual"),
     *          @OA\Property(property="balance", type="number", example="20.00"),
     *          @OA\Property(property="status", type="string", example="NORMAL"),
     *          @OA\Property(property="active_date", type="timestamp", example="1717806908"),
     *     ),
     *  ),
     *
     *  @OA\Response(
     *      response=400,
     *      description="Error getting card status and balance from card cloud",
     *      @OA\MediaType(mediaType="text/plain", @OA\Schema(type="string", example="Error getting card status and balance from card cloud"))
     *  )
     * )
     */

    public function index(Request $request)
    {

        try {
            Validate::userProfile([7, 9], $request->attributes->get('jwt')->profileId);


            $companies = CompaniesByUser::get($request->attributes->get('jwt'));

            if ($companies->count() == 0) {
                return response('User does not have any companies associated', 400);
            }

            $companiesArrayIds = $companies->pluck('CompanyId')->toArray();

            $client = new \GuzzleHttp\Client();

            $response = $client->request('GET', env('CARD_CLOUD_BASE_URL') . '/api/v1/reports/card_status', [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Bearer ' . CardCloudApi::getToken($request->attributes->get('jwt')->id),
                ],
                'body' => json_encode([
                    'companies' => $companiesArrayIds
                ]),
            ]);

            return response()->json(json_decode($response->getBody()->getContents()));
        } catch (\Exception $e) {
            return self::basicError($e->getMessage());
        }
    }
}
