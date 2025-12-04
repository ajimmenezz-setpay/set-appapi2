<?php

namespace App\Http\Controllers\Report\CardCloud;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Users\Validate;
use App\Http\Services\CardCloudApi;
use Illuminate\Http\Request;

class DailyConsume extends Controller
{

    /**
     * @OA\Get(
     *  path="/api/reports/card-cloud/daily-consume",
     *  tags={"Reports"},
     *  summary="Get daily consume from card cloud",
     *  description="Get daily consume from card cloud",
     *  operationId="dailyConsume",
     *  security={{"bearerAuth":{}}},
     *
     *  @OA\Parameter(
     *      name="date",
     *      in="query",
     *      description="Date to get the daily consume",
     *      required=false,
     *      @OA\Schema(
     *          type="string",
     *          format="Y-m-d"
     *      )
     *  ),
     *
     * @OA\Parameter(
     *      name="from",
     *      in="query",
     *      description="Initial date to get the daily consume",
     *      required=false,
     *      @OA\Schema(
     *         type="string",
     *          format="Y-m-d"
     *     )
     * ),
     *
     *  @OA\Parameter(
     *      name="to",
     *      in="query",
     *      description="Final date to get the daily consume",
     *      required=false,
     *      @OA\Schema(
     *          type="string",
     *          format="Y-m-d"
     *      )
     *  ),
     *
     *  @OA\Response(
     *      response=200,
     *      description="Daily consume from card cloud",
     *      @OA\JsonContent(
     *          @OA\Property(property="total_amount", type="string", example="-8527.35"),
     *          @OA\Property(property="movements", type="array", @OA\Items(
     *              @OA\Property(property="enviroment", type="string", example="SET"),
     *              @OA\Property(property="company", type="string", example="SET Nominas"),
     *              @OA\Property(property="client_id", type="string", example="0000320"),
     *              @OA\Property(property="masked_pan", type="string", example="516152XXXXXX5678"),
     *              @OA\Property(property="type", type="string", example="PURCHASE"),
     *              @OA\Property(property="description", type="string", example="BAE VILLALUZ           JUAREZ NL     MEX"),
     *              @OA\Property(property="amount", type="string", example="-28.00"),
     *              @OA\Property(property="authorization_code", type="string", example="725997"),
     *              @OA\Property(property="date", type="string", example="2025-01-13 12:31:29")
     *         )
     *    ),
     *   @OA\Property(property="last_update", type="integer", example=1738393271)
     *  )
     *  ),
     *
     *  @OA\Response(
     *      response=400,
     *          description="Error getting daily consume from card cloud",
     *          @OA\MediaType(mediaType="text/plain", @OA\Schema(type="string", example="Error getting daily consume from card cloud"))
     *      )
     *  )
     */

    public function index(Request $request)
    {
        try {

            if ($request->has("from")) {
                $this->validate($request, [
                    'from' => 'required|date_format:Y-m-d',
                    'to' => 'required|date_format:Y-m-d',
                ], [
                    'from.required' => "La fecha inicial es requerida.",
                    'from.date_format' => "La fecha inicial debe tener el formato Y-m-d.",
                    'to.required' => "La fecha final es requerida.",
                    'to.date_format' => "La fecha final debe tener el formato Y-m-d.",
                ]);

                $dates = [
                    'from' => $request->from,
                    'to' => $request->to,
                ];
            } else {
                $this->validate($request, [
                    'date' => 'required|date_format:Y-m-d',
                ], [
                    'date.required' => 'La fecha es requerida.',
                    'date.date_format' => 'La fecha debe tener el formato Y-m-d.',
                ]);

                $dates = [
                    'date' => $request->date,
                ];
            }

            Validate::userProfile([5, 7, 9, 11,13], $request->attributes->get('jwt')->profileId);

            $companies = CompaniesByUser::get($request->attributes->get('jwt'));

            if ($companies->count() == 0) {
                throw new \Exception("El usuario no tiene empresas asociadas");
            }

            $companiesArrayIds = $companies->pluck('CompanyId')->toArray();

            $client = new \GuzzleHttp\Client();

            $response = $client->request('GET', env('CARD_CLOUD_BASE_URL') . '/api/v1/reports/daily_spend', [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Bearer ' . CardCloudApi::getToken($request->attributes->get('jwt')->id),
                ],
                'body' => json_encode(array_merge([
                    'companies' => $companiesArrayIds
                ], $dates)),
            ]);

            return response()->json(json_decode($response->getBody()->getContents()));
        } catch (\Exception $e) {
            return self::basicError($e->getMessage());
        }
    }
}
