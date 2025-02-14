<?php

namespace App\Http\Controllers\CardCloud;

use App\Http\Controllers\Controller;
use App\Models\Speicloud\StpInstitutions;
use Illuminate\Http\Request;

class InstitutionController extends Controller
{
    /**
     * @OA\Get(
     *      path="/api/cardCloud/institution",
     *      tags={"Institutions"},
     *      summary="Get the list of institutions",
     *      description="Get the list of institutions",
     *      operationId="institution", 
     *      security={{"bearerAuth":{}}},
     * 
     *      @OA\Response(
     *          response=200,
     *          description="List of institutions",
     *          @OA\JsonContent(     
     *              @OA\Property(property="id", type="integer", example="1"),
     *              @OA\Property(property="name", type="string", example="Card Cloud"),
     *         )        
     *     ),  
     * 
     *      @OA\Response(
     *          response=400,
     *          description="No se posible obtener la lista de instituciones",
     *          @OA\MediaType(mediaType="text/plain", @OA\Schema(type="string", example="No se posible obtener la lista de instituciones"))
     *     ),
     * 
     *      @OA\Response(
     *          response=401,
     *          description="Unauthorized",
     *          @OA\MediaType(mediaType="text/plain", @OA\Schema(type="string", example="Unauthorized"))
     *      )
          
     * )
     */

    public function index()
    {
        try {


            $institutions = StpInstitutions::where('Active', 1)->orderBy('ShortName', 'asc')->get();

            $institutionsObject = [];
            $institutionsObject[] = [
                'id' => 0,
                'name' => 'Card Cloud'
            ];

            foreach ($institutions as $institution) {
                $institutionsObject[] = [
                    'id' => $institution->Code,
                    'name' => $institution->ShortName
                ];
            }

            return response()->json($institutionsObject);
        } catch (\Exception $e) {
            return response('No se posible obtener la lista de instituciones', 400);
        }
    }
}
