<?php

namespace App\Http\Controllers\Users;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use App\Models\Users\Address as UserAddress;
use Illuminate\Support\Facades\DB;

class Address extends Controller
{
    /**
     * @OA\Post(
     *      path="/api/users/address",
     *      tags={"Usuarios - Dirección"},
     *      summary="Set user address",
     *      description="Set user address",
     *      operationId="setUserAddress",
     *      security={{"bearerAuth":{}}},
     *      @OA\RequestBody(
     *          required=true,
     *          @OA\JsonContent(
     *              required={"user_id", "country_id", "state_id", "city", "postal_code", "street", "external_number"},
     *              @OA\Property(property="user_id", type="string", example="12345678-1234-1234-1234-123456789012"),
     *              @OA\Property(property="country_id", type="integer", example=1),
     *              @OA\Property(property="state_id", type="integer", example=1),
     *              @OA\Property(property="city", type="string", example="Mexico City"),
     *              @OA\Property(property="postal_code", type="string", example="12345"),
     *              @OA\Property(property="street", type="string", example="Main St"),
     *              @OA\Property(property="external_number", type="string", example="123"),
     *              @OA\Property(property="internal_number", type="string", example="A"),
     *              @OA\Property(property="reference", type="string", example="Near the park"),
     *          )
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="User address set successfully",
     *          @OA\JsonContent(
     *              @OA\Property(property="message", type="string", example="La dirección del usuario se ha establecido correctamente")
     *          )
     *      ),
     *      @OA\Response(
     *          response=400,
     *          description="Error setting user address",
     *          @OA\JsonContent(
     *              @OA\Property(property="error", type="string", example="Error setting address: El usuario no existe")
     *          )
     *      ),
     *
     *      @OA\Response(
     *          response=401,
     *          description="Unauthorized",
     *          @OA\JsonContent(
     *              @OA\Property(property="error", type="string", example="Unauthorized")
     *          )
     *      )
     * )
     */


    public function setAddress(Request $request)
    {
        try {
            $this->validate($request, [
                'user_id' => 'required|string|max:36',
                'country_id' => 'required|integer',
                'state_id' => 'required|integer',
                'city' => 'required|string|max:255',
                'postal_code' => 'required|string|max:10',
                'street' => 'required|string|max:255',
                'external_number' => 'required|string|max:20',
                'internal_number' => 'nullable|string|max:20',
                'reference' => 'nullable|string|max:255',
            ], [
                'user_id.required' => 'El campo usuario (user_id) es requerido',
                'user_id.string' => 'El campo usuario (user_id) debe ser una cadena de texto',
                'user_id.max' => 'El campo usuario (user_id) no puede tener más de 36 caracteres',
                'country_id.required' => 'El campo pais (country_id) es requerido',
                'country_id.integer' => 'El campo pais (country_id) debe ser válido',
                'state_id.required' => 'El campo estado (state_id) es requerido',
                'state_id.integer' => 'El campo estado (state_id) debe ser válido',
                'city.required' => 'El campo ciudad (city) es requerido',
                'city.string' => 'El campo ciudad (city) debe ser una cadena de texto',
                'city.max' => 'El campo ciudad (city) no puede tener más de 255 caracteres',
                'postal_code.required' => 'El campo código postal (postal_code) es requerido',
                'postal_code.string' => 'El campo código postal (postal_code) debe ser una cadena de texto',
                'postal_code.max' => 'El campo código postal (postal_code) no puede tener más de 10 caracteres',
                'street.required' => 'El campo calle (street) es requerido',
                'street.string' => 'El campo calle (street) debe ser una cadena de texto',
                'street.max' => 'El campo calle (street) no puede tener más de 255 caracteres',
                'external_number.required' => 'El campo número exterior (external_number) es requerido',
                'external_number.string' => 'El campo número exterior (external_number) debe ser una cadena de texto',
                'external_number.max' => 'El campo número exterior (external_number) no puede tener más de 20 caracteres',
                'internal_number.string' => 'El campo número interior (internal_number) debe ser una cadena de texto',
                'internal_number.max' => 'El campo número interior (internal_number) no puede tener más de 20 caracteres',
                'reference.string' => 'El campo referencia (reference) debe ser una cadena de texto',
                'reference.max' => 'El campo referencia (reference) no puede tener más de 255 caracteres',
            ]);


            $user = User::find($request->user_id);
            if (!$user) {
                return self::error('El usuario no existe');
            }

            $userAddress = UserAddress::where('UserId', $request->user_id)->first();
            if (!$userAddress) {
                $userAddress = new UserAddress();
                $userAddress->UserId = $request->user_id;
            }
            $userAddress->CountryId = $request->country_id;
            $userAddress->StateId = $request->state_id;
            $userAddress->City = $request->city;
            $userAddress->PostalCode = $request->postal_code;
            $userAddress->Street = $request->street;
            $userAddress->ExternalNumber = $request->external_number;
            $userAddress->InternalNumber = $request->internal_number ?? null;
            $userAddress->Reference = $request->reference ?? null;
            $userAddress->save();

            return response()->json([
                'message' => 'La dirección del usuario se ha establecido correctamente'
            ], 200);
        } catch (\Exception $e) {
            return self::error('Error setting address: ' . $e->getMessage());
        }
    }

    /**
     * @OA\Get(
     *      path="/api/users/address",
     *      tags={"Usuarios - Dirección"},
     *      summary="Get user address",
     *      description="Get user address",
     *      operationId="getUserAddress",
     *      security={{"bearerAuth":{}}},
     *
     *      @OA\RequestBody(
     *          required=true,
     *          @OA\JsonContent(
     *              required={"user_id"},
     *              @OA\Property(property="user_id", type="string", example="12345678-1234-1234-1234-123456789012"),
     *          )
     *      ),
     *
     *      @OA\Response(
     *          response=200,
     *          description="User address retrieved successfully",
     *          @OA\JsonContent(
     *              @OA\Property(property="country_id", type="integer", example=1),
     *              @OA\Property(property="country", type="string", example="Mexico"),
     *              @OA\Property(property="state_id", type="integer", example=1),
     *              @OA\Property(property="state", type="string", example="Mexico"),
     *              @OA\Property(property="city", type="string", example="Mexico City"),
     *              @OA\Property(property="postal_code", type="string", example="12345"),
     *              @OA\Property(property="street", type="string", example="Main St"),
     *              @OA\Property(property="external_number", type="string", example="123"),
     *              @OA\Property(property="internal_number", type="string", example="A"),
     *              @OA\Property(property="reference", type="string", example="Near the park")
     *          )
     *      ),
     *
     *      @OA\Response(
     *          response=400,
     *          description="Error getting user address",
     *          @OA\JsonContent(
     *              @OA\Property(property="error", type="string", example="Error al obtener la dirección: Error")
     *          )
     *      ),
     *
     *      @OA\Response(
     *          response=401,
     *          description="Unauthorized",
     *          @OA\JsonContent(
     *              @OA\Property(property="error", type="string", example="Unauthorized")
     *          )
     *      ),
     *
     *      @OA\Response(
     *          response=404,
     *          description="User not found",
     *          @OA\JsonContent(
     *              @OA\Property(property="error", type="string", example="El usuario no existe o no tiene una dirección establecida")
     *         )
     *      )
     * )
     */

    public function getAddress(Request $request)
    {
        try {
            $this->validate($request, [
                'user_id' => 'required|string|max:36',
            ], [
                'user_id.required' => 'El campo usuario (user_id) es requerido',
                'user_id.string' => 'El campo usuario (user_id) debe ser una cadena de texto',
                'user_id.max' => 'El campo usuario (user_id) no puede tener más de 36 caracteres',
            ]);

            $user = User::find($request->user_id);
            if (!$user) {
                return response('El usuario no existe', 404);
            }

            $userAddress = UserAddress::where('UserId', $request->user_id)->first();
            if (!$userAddress) {
                return response('El usuario no tiene una dirección establecida', 404);
            }

            return response()->json(self::addressObject($userAddress), 200);
        } catch (\Exception $e) {
            return self::error('Error al obtener la dirección: ' . $e->getMessage());
        }
    }

    public static function addressObject($userAddress)
    {
        $country = DB::table('cat_countries')->where('Id', $userAddress->CountryId)->first();
        $state = DB::table('cat_country_states')->where('Id', $userAddress->StateId)->first();

        return [
            'country_id' => $userAddress->CountryId,
            'country' => $state->name,
            'state_id' => $userAddress->StateId,
            'state' => $country->name,
            'city' => $userAddress->City,
            'postal_code' => $userAddress->PostalCode,
            'street' => $userAddress->Street,
            'external_number' => $userAddress->ExternalNumber,
            'internal_number' => $userAddress->InternalNumber,
            'reference' => $userAddress->Reference,
        ];
    }
}
