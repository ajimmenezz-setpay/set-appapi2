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
     *              type="object",
     *              @OA\Property(property="last_update", type="number", description="Fecha de la última actualización", example="1738300770"),
     *             @OA\Property(property="data", type="array", description="Datos de los fondeos",
     *                  @OA\Items(
     *                      type="object",
     *                      @OA\Property(property="enviroment", type="string", description="Ambiente", example="SET"),
     *                      @OA\Property(property="company", type="string", description="Nombre de la empresa", example="SET Administración"),
     *                      @OA\Property(property="description", type="string", description="Descripción del movimiento", example="Fondeo Prueba"),
     *                      @OA\Property(property="amount", type="string", description="Monto del movimiento", example="1000.00"),
     *                      @OA\Property(property="approved_by", type="string", description="Aprobado por", example="Admin"),
     *                      @OA\Property(property="date", type="string", format="date-time", nullable=true, description="Fecha del movimiento", example="1738300770")
     *                 )
     *             )
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

            Validate::userProfile([5, 7, 9], $request->attributes->get('jwt')->profileId);

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
