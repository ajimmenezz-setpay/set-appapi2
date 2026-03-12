<?php

namespace App\Http\Controllers\SpeiCloud\Lists;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class Institutions extends Controller
{
    public function index()
    {
        /**
         * @OA\Get(
         *      path="/speiCloud/institutions",
         *      tags={"Spei Cloud"},
         *      summary="List of institutions",
         *      description="List of institutions",
         *      operationId="getSTPInstitutions",
         *      security={{"bearerAuth":{}}},
         *
         *      @OA\Response(
         *          response=200,
         *          description="List of institutions",
         *          @OA\JsonContent(
         *              type="array",
         *              @OA\Items(
         *                  @OA\Property(property="code", type="string", example="002"),
         *                  @OA\Property(property="name", type="string", example="BANAMEX")
         *              )
         *          )
         *      ),
         *
         *      @OA\Response(
         *          response=400,
         *          description="Error al obtener las instituciones",
         *          @OA\MediaType(mediaType="text/plain", @OA\Schema(type="string", example="Error al obtener las instituciones"))
         *      ),
         *
         *      @OA\Response(
         *          response=401,
         *          description="Unauthorized",
         *          @OA\MediaType(mediaType="text/plain", @OA\Schema(type="string", example="Unauthorized"))
         *      )
         * )
         */
        try {
            $institutions = \App\Models\Speicloud\StpInstitutions::where('Active', 1)->orderBy('ShortName')->get(['Code as code', 'ShortName as name']);
            return response()->json($institutions);
        } catch (\Exception $e) {
            return response($e->getMessage(), $e->getCode() ?: 400);
        }
    }
}
