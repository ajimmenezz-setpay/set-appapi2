<?php

namespace App\Http\Controllers\Address;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;

class Countries extends Controller
{

    /**
     * @OA\Get(
     *     path="/api/address/countries",
     *     tags={"Address"},
     *     summary="Get countries",
     *     description="Get countries",
     *     operationId="getCountries",
     *
     *     @OA\Response(
     *         response=200,
     *         description="Countries",
     *         @OA\JsonContent(
     *             type="array",
     *             @OA\Items(
     *                 type="object",
     *                 @OA\Property(property="Id", type="integer", example=1),
     *                 @OA\Property(property="name", type="string", example="Mexico"),
     *                 @OA\Property(property="official_name", type="string", example="Estados Unidos Mexicanos"),
     *                 @OA\Property(property="iso2", type="string", example="MX"),
     *                 @OA\Property(property="iso3", type="string", example="MEX"),
     *                 @OA\Property(property="phone_code", type="string", example="+52"),
     *                 @OA\Property(property="currency_code_alpha", type="string", example="MXN"),
     *                 @OA\Property(property="currency_code_numeric", type="string", example="484"),
     *                 @OA\Property(property="currency_name", type="string", example="Peso Mexicano")
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=400,
     *         description="Error getting countries",
     *         @OA\MediaType(
     *             mediaType="text/plain",
     *             @OA\Schema(
     *                 type="string",
     *                 example="Error al obtener los países"
     *             )
     *         )
     *     )
     * )
     */

    public function index()
    {
        try {
            $countries = DB::table('cat_countries')->orderBy('name', 'asc')->get();
            return response()->json($countries);
        } catch (\Exception $e) {
            return self::error('Error al obtener los paises: ' . $e->getMessage());
        }
    }
}
