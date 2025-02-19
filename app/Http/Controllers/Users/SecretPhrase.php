<?php

namespace App\Http\Controllers\Users;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Security\GoogleAuth;
use App\Models\Users\SecretPhrase as UsersSecretPhrase;
use Illuminate\Http\Request;

class SecretPhrase extends Controller
{

    /**
     * @OA\Get(
     *      path="/api/users/secret-phrase",
     *      tags={"Usuarios - Frase secreta"},
     *      summary="Obtener frase secreta",
     *     description="Obtener frase secreta",
     *     security={{"bearerAuth":{}}},
     * 
     *    @OA\Response(
     *          response=200,
     *              description="Frase secreta",
     *              @OA\JsonContent(
     *                  @OA\Property(property="phrase", type="string", example="Frase secreta")
     *              )
     *     ),
     * 
     *    @OA\Response(
     *          response=400,
     *          description="Error al obtener la frase secreta",
     *          @OA\MediaType(mediaType="text/plain", @OA\Schema(type="string", example="Error al obtener la frase secreta"))
     *    ),
     * 
     *   @OA\Response(
     *      response=401,
     *      description="Unauthorized",
     *      @OA\MediaType(mediaType="text/plain", @OA\Schema(type="string", example="Unauthorized"))
     *      )
     * )
     */

    public function index(Request $request)
    {
        try {
            $secretPhrase = UsersSecretPhrase::where('UserId', $request->attributes->get('jwt')->id)->first();
            if (!$secretPhrase) throw new \Exception('El usuario no tiene una frase secreta');

            return self::success(['phrase' => $secretPhrase->SecretPhrase]);
        } catch (\Exception $e) {
            return self::basicError($e->getMessage());
        }
    }


    /**
     * @OA\Post(
     *      path="/api/users/secret-phrase",
     *      tags={"Usuarios - Frase secreta"},
     *      summary="Crear frase secreta",
     *      description="Crear frase secreta",
     *      security={{"bearerAuth":{}}},
     *
     *      @OA\RequestBody(
     *          required=true,
     *          @OA\JsonContent(
     *              required={"phrase", "code"},
     *              @OA\Property(property="phrase", type="string", example="Frase secreta"),
     *              @OA\Property(property="code", type="string", example="123456")
     *          )
     *      ),
     *
     *      @OA\Response(
     *          response=200,
     *          description="Frase secreta creada",
     *          @OA\JsonContent(
     *              @OA\Property(property="message", type="string", example="Frase secreta creada")
     *          )
     *      ),
     *
     *      @OA\Response(
     *          response=400,
     *          description="Error al crear la frase secreta",
     *          @OA\MediaType(mediaType="text/plain", @OA\Schema(type="string", example="Error al crear la frase secreta"))
     *      ),
     *
     *      @OA\Response(
     *          response=401,
     *          description="Unauthorized",
     *          @OA\MediaType(mediaType="text/plain", @OA\Schema(type="string", example="Unauthorized"))
     *      )
     * )
     *
     */
    public function create(Request $request)
    {
        try {
            $this->validate($request, [
                'phrase' => 'required',
                'code' => 'required|min:6|max:6'
            ], [
                'phrase.required' => 'La frase secreta es requerida',
                'code.required' => 'El código de autenticación es requerido',
                'code.min' => 'El código de autenticación debe tener al menos 6 caracteres',
                'code.max' => 'El código de autenticación debe tener como máximo 6 caracteres'
            ]);

            $exist = UsersSecretPhrase::where('UserId', $request->attributes->get('jwt')->id)->first();
            if ($exist) throw new \Exception('El usuario ya tiene una frase secreta');

            GoogleAuth::authorized($request->attributes->get('jwt')->id, $request->code);

            $secretPhrase = new UsersSecretPhrase();
            $secretPhrase->UserId = $request->attributes->get('jwt')->id;
            $secretPhrase->SecretPhrase = $request->phrase;
            $secretPhrase->save();

            return self::success(['message' => 'Frase secreta creada']);
        } catch (\Exception $e) {
            return self::basicError($e->getMessage());
        }
    }


    /**
     * @OA\Patch(
     *      path="/api/users/secret-phrase",
     *      tags={"Usuarios - Frase secreta"},
     *      summary="Actualizar frase secreta",
     *      description="Actualizar frase secreta",
     *      security={{"bearerAuth":{}}},
     *
     *      @OA\RequestBody(
     *          required=true,
     *          @OA\JsonContent(
     *              required={"phrase", "code"},
     *              @OA\Property(property="phrase", type="string", example="Frase secreta"),
     *              @OA\Property(property="code", type="string", example="123456")
     *          )
     *      ),
     *
     *      @OA\Response(
     *          response=200,
     *          description="Frase secreta actualizada",
     *          @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Frase secreta actualizada")
     *          )
     *      ),
     *
     *      @OA\Response(
     *          response=400,
     *          description="Error al actualizar la frase secreta",
     *          @OA\MediaType(mediaType="text/plain", @OA\Schema(type="string", example="Error al actualizar la frase secreta"))
     *      ),
     *
     *      @OA\Response(
     *          response=401,
     *          description="Unauthorized",
     *          @OA\MediaType(mediaType="text/plain", @OA\Schema(type="string", example="Unauthorized"))
     *      )
     * )
     *
     */
    public function update(Request $request)
    {
        try {
            $this->validate($request, [
                'phrase' => 'required',
                'code' => 'required|min:6|max:6'
            ], [
                'phrase.required' => 'La frase secreta es requerida',
                'code.required' => 'El código de autenticación es requerido',
                'code.min' => 'El código de autenticación debe tener al menos 6 caracteres',
                'code.max' => 'El código de autenticación debe tener como máximo 6 caracteres'
            ]);

            $secretPhrase = UsersSecretPhrase::where('UserId', $request->attributes->get('jwt')->id)->first();
            if (!$secretPhrase) throw new \Exception('El usuario no tiene una frase secreta');

            GoogleAuth::authorized($request->attributes->get('jwt')->id, $request->code);

            $secretPhrase->SecretPhrase = $request->phrase;
            $secretPhrase->save();

            return self::success(['message' => 'Frase secreta actualizada']);
        } catch (\Exception $e) {
            return self::basicError($e->getMessage());
        }
    }

    /**
     * @OA\Delete(
     *      path="/api/users/secret-phrase",
     *      tags={"Usuarios - Frase secreta"},
     *      summary="Eliminar frase secreta",
     *      description="Eliminar frase secreta",
     *      security={{"bearerAuth":{}}},
     *
     *      @OA\RequestBody(
     *          required=true,
     *          @OA\JsonContent(
     *              required={"code"},
     *              @OA\Property(property="code", type="string", example="123456")
     *          )
     *      ),
     *
     *      @OA\Response(
     *          response=200,
     *          description="Frase secreta eliminada",
     *          @OA\JsonContent(
     *              @OA\Property(property="message", type="string", example="Frase secreta eliminada")
     *          )
     *      ),
     *
     *      @OA\Response(
     *          response=400,
     *          description="Error al eliminar la frase secreta",
     *          @OA\MediaType(mediaType="text/plain", @OA\Schema(type="string", example="Error al eliminar la frase secreta"))
     *      ),
     *
     *      @OA\Response(
     *          response=401,
     *          description="Unauthorized",
     *          @OA\MediaType(mediaType="text/plain", @OA\Schema(type="string", example="Unauthorized"))
     *      )
     * )
     */
    public function delete(Request $request)
    {
        try {
            $this->validate($request, [
                'code' => 'required|min:6|max:6'
            ], [
                'code.required' => 'El código de autenticación es requerido',
                'code.min' => 'El código de autenticación debe tener al menos 6 caracteres',
                'code.max' => 'El código de autenticación debe tener como máximo 6 caracteres'
            ]);

            GoogleAuth::authorized($request->attributes->get('jwt')->id, $request->code);

            $secretPhrase = UsersSecretPhrase::where('UserId', $request->attributes->get('jwt')->id)->first();
            if (!$secretPhrase) throw new \Exception('El usuario no tiene una frase secreta');


            $secretPhrase->delete();

            return self::success(['message' => 'Frase secreta eliminada']);
        } catch (\Exception $e) {
            return self::basicError($e->getMessage());
        }
    }
}
