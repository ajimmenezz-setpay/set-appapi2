<?php

namespace App\Http\Controllers\Address;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class States extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/address/states",
     *     tags={"Address"},
     *     summary="Get states",
     *     description="Get states",
     *     operationId="getStates",
     *
     *      @OA\RequestBody(
     *          required=true,
     *          @OA\JsonContent(
     *              required={"countryId"},
     *              @OA\Property(property="countryId", type="integer", example=1)
     *          )
     *      ),
     *
     *      @OA\Response(
     *         response=200,
     *         description="States",
     *         @OA\JsonContent(
     *             type="array",
     *             @OA\Items(
     *                 type="object",
     *                 @OA\Property(property="Id", type="integer", example=1),
     *                 @OA\Property(property="name", type="string", example="Aguascalientes")
     *            )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=400,
     *         description="Error getting states",
     *         @OA\MediaType(
     *             mediaType="text/plain",
     *             @OA\Schema(
     *                 type="string",
     *                 example="Error al obtener los estados"
     *             )
     *         )
     *     )
     * )
     */


    public function index(Request $request)
    {
        try {
            $this->validate($request, [
                'countryId' => 'required|integer',
            ],[
                'countryId.required' => 'El campo countryId es obligatorio.',
                'countryId.integer' => 'El campo countryId debe ser un identificador válido.'
            ]);

            $states = DB::table('cat_country_states')
                ->where('CountryId', $request->input('countryId'))
                ->orderBy('name', 'asc')
                ->select('Id', 'name')
                ->get();

            return response()->json($states);
        } catch (\Exception $e) {
            return self::error('Error al obtener los estados: ' . $e->getMessage());
        }
    }
}
