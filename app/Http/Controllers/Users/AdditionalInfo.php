<?php

namespace App\Http\Controllers\Users;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Users\Additional;

class AdditionalInfo extends Controller
{
    /**
     * @OA\Post(
     *     path="/api/users/additional-info",
     *     tags={"Usuarios - Información Adicional"},
     *     summary="Actualizar información adicional del usuario",
     *     operationId="setAdditionalInfo",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(
     *                 property="rfc",
     *                 type="string",
     *                 maxLength=13,
     *                 description="RFC del usuario"
     *             ),
     *             @OA\Property(
     *                 property="curp",
     *                 type="string",
     *                 maxLength=18,
     *                 description="CURP del usuario"
     *             ),
     *             @OA\Property(
     *                 property="voter_code",
     *                 type="string",
     *                 maxLength=30,
     *                 description="Clave de elector"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Información adicional actualizada correctamente",
     *         @OA\JsonContent(
     *             type="string",
     *             example="Información adicional actualizada correctamente."
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Error de validación o usuario no encontrado",
     *         @OA\JsonContent(
     *             type="string",
     *             example="El usuario no existe"
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Error interno del servidor",
     *         @OA\JsonContent(
     *             type="string",
     *             example="Error al procesar la solicitud"
     *         )
     *     )
     * )
     */


    public function setAdditionalInfo(Request $request)
    {
        try {
            $this->validate($request, [
                'rfc' => 'nullable|string|max:13',
                'curp' => 'nullable|string|max:18',
                'voter_code' => 'nullable|string|max:30'
            ], [
                'rfc.max' => 'El RFC no puede tener más de 13 caracteres.',
                'curp.max' => 'La CURP no puede tener más de 18 caracteres.',
                'voter_code.max' => 'El código de elector no puede tener más de 30 caracteres.'
            ]);

            $user = User::find($request->attributes->get('jwt')->id);
            if (!$user) {
                return self::error('El usuario no existe');
            }

            $additionalInfo = Additional::where('UserId', $request->attributes->get('jwt')->id)->first();
            if (!$additionalInfo) {
                $additionalInfo = new Additional();
                $additionalInfo->UserId = $request->attributes->get('jwt')->id;
            }

            $additionalInfo->RFC = $request->rfc ?? "";
            $additionalInfo->CURP = $request->curp ?? "";
            $additionalInfo->VoterCode = $request->voter_code ?? "";
            $additionalInfo->save();

            return response()->json("Información adicional actualizada correctamente.", 200);
        } catch (\Exception $e) {
            return self::error($e->getMessage());
        }
    }


    /**
     * @OA\Get(
     *     path="/api/users/additional-info",
     *     tags={"Usuarios - Información Adicional"},
     *     summary="Obtener información adicional del usuario",
     *     operationId="getAdditionalInfo",
     *     @OA\Response(
     *         response=200,
     *         description="Información adicional del usuario",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="rfc", type="string", example="ABC123456789"),
     *             @OA\Property(property="curp", type="string", example="ABC123456HDFGJ01"),
     *             @OA\Property(property="voter_code", type="string", example="12345678901234567890")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Error de validación o usuario no encontrado",
     *         @OA\JsonContent(
     *             type="string",
     *             example="El usuario no existe"
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Error interno del servidor",
     *         @OA\JsonContent(
     *             type="string",
     *             example="Error al procesar la solicitud"
     *         )
     *     )
     * )
     */


    public function getAdditionalInfo(Request $request)
    {
        try {
            $user = User::find($request->attributes->get('jwt')->id);
            if (!$user) {
                return self::error('El usuario no existe');
            }

            $additionalInfo = Additional::where('UserId', $request->attributes->get('jwt')->id)->first();
            if (!$additionalInfo) {
                $additionalInfo = [
                    'rfc' => null,
                    'curp' => null,
                    'voter_code' => null
                ];
            } else {
                $additionalInfo = [
                    'rfc' => $additionalInfo->RFC,
                    'curp' => $additionalInfo->CURP,
                    'voter_code' => $additionalInfo->VoterCode
                ];
            }

            return response()->json($additionalInfo, 200);
        } catch (\Exception $e) {
            return self::basicError($e->getMessage());
        }
    }
}
