<?php

namespace App\Http\Controllers\Report\CardCloud;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Users\Validate;
use App\Http\Services\CardCloudApi;
use App\Models\Backoffice\Companies\CompanyProjection;
use App\Models\Backoffice\Users\CompaniesAndUsers;
use App\Models\User;
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
     *      required=true,
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
     *          @OA\Property(property="total_amount", type="number", example="1000.00"),
     *          @OA\Property(property="request_date", type="string", format="Y-m-d", example="2025-01-01"),
     *          @OA\Property(property="movements", type="array", @OA\Items(
     *              @OA\Property(property="enviroment", type="string", example="SET"),
     *              @OA\Property(property="company", type="string", example="Company Name"),
     *              @OA\Property(property="client_id", type="string", example="SP0000001"),
     *              @OA\Property(property="masked_pan", type="string", example="516152XXXXXX2992"),
     *              @OA\Property(property="type", type="string", example="Purchase"),
     *              @OA\Property(property="description", type="string", example="Purchase description"),
     *              @OA\Property(property="amount", type="number", example="100.00"),
     *              @OA\Property(property="authorization_code", type="string", example="123456"),
     *              @OA\Property(property="date", type="datetime", example="2025-01-01 00:00:00"),
     *          )),
     *      ),
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
        $this->validate($request, [
            'date' => 'required|date_format:Y-m-d',
        ], [
            'date.required' => 'The date field is required.',
            'date.date_format' => 'The date field must be in the format Y-m-d.',
        ]);

        try {
            Validate::userProfile([7, 9], $request->attributes->get('jwt')->profileId);
        } catch (\Exception $e) {
            return response($e->getMessage(), 400);
        }

        if ($request->attributes->get('jwt')->profileId == 7) {
            $companies = CompaniesAndUsers::where('UserId', $request->attributes->get('jwt')->id)->get();
        } else {
            $user = User::where('Id', $request->attributes->get('jwt')->id)->first();
            $companies = CompanyProjection::where('BusinessId', $user->BusinessId)
            ->where('Active', 1)
            ->select('Id as CompanyId')
            ->get();
        }

        if ($companies->count() == 0) {
            return response('User does not have any companies associated', 400);
        }

        $companiesArrayIds = $companies->pluck('CompanyId')->toArray();

        $client = new \GuzzleHttp\Client();

        $response = $client->request('GET', env('CARD_CLOUD_BASE_URL') . '/api/v1/reports/daily_spend', [
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . CardCloudApi::getToken($request->attributes->get('jwt')->id),
            ],
            'body' => json_encode([
                'companies' => $companiesArrayIds,
                'date' => $request->date,
            ]),
        ]);

        return response($response->getBody()->getContents());
    }
}
