<?php

namespace App\Http\Controllers\Report\CardCloud;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Users\Validate;
use App\Http\Services\CardCloudApi;
use Illuminate\Http\Request;

class Fundings extends Controller
{
    /**
     * @OA\Get(
     *      path="/api/reports/card-cloud/fundings",
     *      tags={"Reports"},
     *     summary="Get fundings from card cloud",
     *      description="Get fundings from card cloud",
     *      operationId="fundings",
     *      security={{"bearerAuth":{}}},
     *
     *      @OA\Parameter(
     *          name="from",
     *          in="query",
     *          description="Start date to get the fundings",
     *          required=true,
     *          @OA\Schema(
     *              type="number",
     *              format="UTC"
     *           )
     *          ),
     *
     *      @OA\Parameter(
     *          name="to",
     *          in="query",
     *          description="End date to get the fundings",
     *          required=true,
     *          @OA\Schema(
     *              type="number",
     *              format="UTC"
     *          )
     *     ),
     *
     *     @OA\Response(
     *          response=200,
     *          description="Fundings from card cloud",
     *          @OA\JsonContent(
     *              @OA\Property(property="enviroment", type="string", example="SET"),
     *              @OA\Property(property="company", type="string", example="Company Name"),
     *             @OA\Property(property="description", type="string", example="Purchase description"),
     *              @OA\Property(property="amount", type="number", example="100.00"),
     *              @OA\Property(property="approved_by", type="string", example="John Doe"),
     *              @OA\Property(property="date", type="number", example="1738027313"),
     *          ),
     *     ),
     *
     *    @OA\Response(
     *          response=400,
     *          description="Error getting fundings from card cloud",
     *          @OA\MediaType(mediaType="text/plain", @OA\Schema(type="string", example="Error getting fundings from card cloud"))
     *     )
     *
     *  )
     *
     */

    public function index(Request $request)
    {
        try {
            $this->validate($request, [
                'from' => 'required|numeric',
                'to' => 'required|numeric',
            ], [
                'from.required' => 'La fecha de inicio es requerida',
                'from.numeric' => 'La fecha de inicio debe ser un número en formato UTC',
                'to.required' => 'La fecha de fin es requerida',
                'to.numeric' => 'La fecha de fin debe ser un número en formato UTC',
            ]);

            Validate::userProfile([7, 9], $request->attributes->get('jwt')->profileId);

            $companies = CompaniesByUser::get($request->attributes->get('jwt'));

            if ($companies->count() == 0) throw new \Exception('User does not have any companies associated');

            $companiesArrayIds = $companies->pluck('CompanyId')->toArray();

            $client = new \GuzzleHttp\Client();

            $response = $client->request('GET', env('CARD_CLOUD_BASE_URL') . '/api/v1/reports/funding', [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Bearer ' . CardCloudApi::getToken($request->attributes->get('jwt')->id),
                ],
                'body' => json_encode([
                    'companies' => $companiesArrayIds,
                    'from' => $request->input('from'),
                    'to' => $request->input('to'),
                ]),
            ]);

            return response()->json(json_decode($response->getBody()->getContents()), $response->getStatusCode());
        } catch (\Exception $e) {
            return self::basicError($e->getMessage());
        }
    }
}
