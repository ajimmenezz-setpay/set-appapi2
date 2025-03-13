<?php

namespace App\Http\Controllers\CardCloud;

use App\Http\Controllers\Controller;
use App\Models\CardCloud\Contact;
use App\Models\Speicloud\StpInstitutions;
use Exception;
use Illuminate\Http\Request;
use Ramsey\Uuid\Uuid;

class ContactController extends Controller
{
    /**
     * @OA\Get(
     *      path="/api/cardCloud/contact",
     *      tags={"Card Cloud - Contactos"},
     *      summary="Lista de contactos del usuario",
     *      description="Lista de contactos del usuario",
     *      security={{"bearerAuth":{}}},
     *
     *      @OA\Response(
     *          response=200,
     *          description="Lista de contactos",
     *          @OA\JsonContent(
     *              @OA\Property(property="contact_id", type="string", example="01935c81-abe0-7238-8635-46a8486259be"),
     *              @OA\Property(property="name", type="string", example="Contacto 1"),
     *              @OA\Property(property="institution_id", type="integer", example="0"),
     *              @OA\Property(property="institution_name", type="string", example="Card Cloud"),
     *              @OA\Property(property="account", type="string", example="123456789012345678"),
     *              @OA\Property(property="client_id", type="string", example="SP0001275"),
     *              @OA\Property(property="alias", type="string", example="Contacto 1")
     *          )
     *      ),
     *
     *      @OA\Response(
     *          response=400,
     *          description="Error al obtener los contactos",
     *          @OA\MediaType(mediaType="text/plain", @OA\Schema(type="string", example="Error al obtener los contactos"))
     *      ),
     *
     *      @OA\Response(
     *          response=401,
     *          description="Unauthorized",
     *          @OA\MediaType(mediaType="text/plain", @OA\Schema(type="string", example="Unauthorized"))
     *      )
     * )
     */

    public function index(Request $request)
    {
        try {
            $contactsArray = [];
            $contacts = Contact::where('UserID', $request->attributes->get('jwt')->id)->orderBy('Alias', 'asc')->get();

            foreach ($contacts as $contact) {
                $contactsArray[] = self::contactObject($contact);
            }

            return response()->json($contactsArray);
        } catch (Exception $e) {
            return self::basicError($e->getMessage());
        }
    }

    /**
     * @OA\Post(
     *      path="/api/cardCloud/contact",
     *      tags={"Card Cloud - Contactos"},
     *      summary="Crear contacto",
     *      description="Crear contacto",
     *      security={{"bearerAuth":{}}},
     *
     *      @OA\RequestBody(
     *          required=true,
     *          @OA\JsonContent(
     *              required={"name", "alias", "institution"},
     *              @OA\Property(property="name", type="string", example="Contacto 1"),
     *              @OA\Property(property="alias", type="string", example="Contacto 1"),
     *              @OA\Property(property="institution", type="integer", example="0"),
     *              @OA\Property(property="account", type="string", example="123456789012345678"),
     *              @OA\Property(property="client_id", type="string", example="SP0001275"),
     *          )
     *      ),
     *
     *      @OA\Response(
     *          response=200,
     *          description="Contacto creado correctamente",
     *          @OA\JsonContent(
     *              @OA\Property(property="contact_id", type="string", example="01935c81-abe0-7238-8635-46a8486259be"),
     *              @OA\Property(property="name", type="string", example="Contacto 1"),
     *              @OA\Property(property="institution_id", type="integer", example="0"),
     *              @OA\Property(property="institution_name", type="string", example="Card Cloud"),
     *              @OA\Property(property="account", type="string", example="123456789012345678"),
     *              @OA\Property(property="client_id", type="string", example="SP0001275"),
     *              @OA\Property(property="alias", type="string", example="Contacto 1")
     *          )
     *      ),
     *
     *      @OA\Response(
     *          response=400,
     *          description="Error al crear el contacto",
     *          @OA\MediaType(mediaType="text/plain", @OA\Schema(type="string", example="Error al crear el contacto"))
     *      ),
     *
     *      @OA\Response(
     *          response=401,
     *          description="Unauthorized",
     *          @OA\MediaType(mediaType="text/plain", @OA\Schema(type="string", example="Unauthorized"))
     *      )
     * )
     */

    public function store(Request $request)
    {
        try {
            $this->validate($request, [
                'institution' => 'required',
                'alias' => 'required',
                'name' => 'required',
            ], [
                'institution.required' => 'El campo institution es obligatorio',
                'alias.required' => 'El campo alias es obligatorio',
                'name.required' => 'El campo name es obligatorio',
                'client_id.required' => 'El campo client_id es obligatorio'
            ]);

            if ($request->input('institution') == 0) {
                $this->validate($request, [
                    'client_id' => 'required',
                ], [
                    'client_id.required' => 'El campo client_id es obligatorio'
                ]);

                $exist = Contact::where('UserId', $request->attributes->get('jwt')->id)->where('ClientId', $request->input('client_id'))->first();
                if ($exist) throw new Exception('Ya existe un contacto con el mismo ClientId');
            } else {
                $institution = StpInstitutions::where('Code', $request->input('institution'))->first();
                if (!$institution) throw new Exception('La institución no existe');

                if (strlen($request->input('account')) < 18) throw new Exception('La cuenta CLABE debe tener 18 dígitos');
                if (strlen($request->input('account')) > 18) throw new Exception('La cuenta CLABE debe tener 18 dígitos');

                $exist = Contact::where('UserId', $request->attributes->get('jwt')->id)->where('Account', $request->input('account'))->first();
                if ($exist) throw new Exception('Ya existe un contacto con la misma cuenta CLABE');
            }


            $contact = Contact::create([
                'UUID' => Uuid::uuid7(),
                'UserId' => $request->attributes->get('jwt')->id,
                'Name' => $request->input('name'),
                'Institution' => $request->input('institution'),
                'Account' => $request->input('account') ?? "",
                'Alias' => $request->input('alias'),
                'ClientId' => $request->input('client_id') ?? "",
            ]);

            return response()->json(self::contactObject($contact));
        } catch (Exception $e) {
            return self::basicError($e->getMessage());
        }
    }

    /**
     * @OA\Get(
     *      path="/api/cardCloud/contact/{uuid}",
     *      tags={"Card Cloud - Contactos"},
     *      summary="Mostrar contacto",
     *      description="Mostrar contacto",
     *      security={{"bearerAuth":{}}},
     *
     *      @OA\Parameter(
     *          name="uuid",
     *          in="path",
     *          description="Contact UUID",
     *          required=true,
     *          @OA\Schema(type="string")
     *      ),
     *
     *      @OA\Response(
     *          response=200,
     *          description="Contacto",
     *          @OA\JsonContent(
     *              @OA\Property(property="contact_id", type="string", example="01935c81-abe0-7238-8635-46a8486259be"),
     *              @OA\Property(property="name", type="string", example="Contacto 1"),
     *              @OA\Property(property="institution_id", type="integer", example="0"),
     *              @OA\Property(property="institution_name", type="string", example="Card Cloud"),
     *              @OA\Property(property="account", type="string", example="123456789012345678"),
     *              @OA\Property(property="client_id", type="string", example="SP0001275"),
     *              @OA\Property(property="alias", type="string", example="Contacto 1")
     *
     *          )
     *      ),
     *
     *      @OA\Response(
     *          response=400,
     *          description="Error al obtener el contacto",
     *          @OA\MediaType(mediaType="text/plain", @OA\Schema(type="string", example="Error al obtener el contacto"))
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
    public function show(Request $request, $uuid)
    {
        try {
            $contact = Contact::where('UserId', $request->attributes->get('jwt')->id)->where('UUID', $uuid)->first();
            if (!$contact) throw new Exception('Contacto no encontrado o no pertenece al usuario');

            return response()->json(self::contactObject($contact));
        } catch (Exception $e) {
            return self::basicError($e->getMessage());
        }
    }


    /**
     * @OA\Delete(
     *      path="/api/cardCloud/contact/{uuid}",
     *      tags={"Card Cloud - Contactos"},
     *      summary="Eliminar contacto",
     *      description="Eliminar contacto",
     *      security={{"bearerAuth":{}}},
     *
     *      @OA\Parameter(
     *          name="uuid",
     *          in="path",
     *          description="Contact UUID",
     *          required=true,
     *          @OA\Schema(type="string")
     *       ),
     *
     *      @OA\Response(
     *          response=200,
     *          description="Contacto eliminado correctamente",
     *          @OA\JsonContent(
     *              @OA\Property(property="message", type="string", example="Contacto eliminado correctamente")
     *          )
     *      ),
     *
     *      @OA\Response(
     *          response=400,
     *          description="Error al eliminar el contacto",
     *          @OA\MediaType(mediaType="text/plain", @OA\Schema(type="string", example="Error al eliminar el contacto"))
     *      ),
     *
     *      @OA\Response(
     *          response=401,
     *          description="Unauthorized",
     *          @OA\MediaType(mediaType="text/plain", @OA\Schema(type="string", example="Unauthorized"))
     *      )
     * )
     */
    public function delete(Request $request, $uuid)
    {
        try {
            $contact = Contact::where('UserId', $request->attributes->get('jwt')->id)->where('UUID', $uuid)->first();
            if (!$contact) throw new Exception('Contacto no encontrado o no pertenece al usuario');

            $contact->delete();

            return self::success([
                'message' => 'Contacto eliminado correctamente'
            ]);
        } catch (Exception $e) {
            return self::basicError($e->getMessage());
        }
    }

    /**
     * @OA\Patch(
     *      path="/api/cardCloud/contact/{uuid}",
     *      tags={"Card Cloud - Contactos"},
     *      summary="Actualizar contacto",
     *      description="Actualizar contacto",
     *      security={{"bearerAuth":{}}},
     *
     *      @OA\Parameter(
     *          name="uuid",
     *          in="path",
     *          description="Contact UUID",
     *          required=true,
     *          @OA\Schema(type="string")
     *      ),
     *
     *      @OA\RequestBody(
     *          required=true,
     *          @OA\JsonContent(
     *              required={"name", "alias", "institution"},
     *              @OA\Property(property="name", type="string", example="Contacto 1"),
     *              @OA\Property(property="alias", type="string", example="Contacto 1"),
     *              @OA\Property(property="institution", type="integer", example="0"),
     *              @OA\Property(property="account", type="string", example="123456789012345678"),
     *              @OA\Property(property="client_id", type="string", example="SP0001275"),
     *          )
     *     ),
     *
     *      @OA\Response(
     *          response=200,
     *          description="Contacto actualizado correctamente",
     *          @OA\JsonContent(
     *              @OA\Property(property="contact_id", type="string", example="01935c81-abe0-7238-8635-46a8486259be"),
     *              @OA\Property(property="name", type="string", example="Contacto 1"),
     *              @OA\Property(property="institution_id", type="integer", example="0"),
     *              @OA\Property(property="institution_name", type="string", example="Card Cloud"),
     *              @OA\Property(property="account", type="string", example="123456789012345678"),
     *              @OA\Property(property="client_id", type="string", example="SP0001275"),
     *              @OA\Property(property="alias", type="string", example="Contacto 1")
     *          )
     *      ),
     *
     *     @OA\Response(
     *          response=400,
     *          description="Error al actualizar el contacto",
     *          @OA\MediaType(mediaType="text/plain", @OA\Schema(type="string", example="Error al actualizar el contacto"))
     *      ),
     *
     *      @OA\Response(
     *          response=401,
     *          description="Unauthorized",
     *          @OA\MediaType(mediaType="text/plain", @OA\Schema(type="string", example="Unauthorized"))
     *      )
     * )
     */

    public function update(Request $request, $uuid)
    {
        try {
            $contact = Contact::where('UserId', $request->attributes->get('jwt')->id)->where('UUID', $uuid)->first();
            if (!$contact) throw new Exception('Contacto no encontrado o no pertenece al usuario');

            $this->validate($request, [
                'institution' => 'required',
                'alias' => 'required',
                'name' => 'required',
            ], [
                'institution.required' => 'El campo institution es obligatorio',
                'alias.required' => 'El campo alias es obligatorio',
                'name.required' => 'El campo name es obligatorio',
                'client_id.required' => 'El campo client_id es obligatorio'
            ]);

            if ($request->input('institution') == 0) {
                $this->validate($request, [
                    'client_id' => 'required',
                ], [
                    'client_id.required' => 'El campo client_id es obligatorio'
                ]);

                $exist = Contact::where('UserId', $request->attributes->get('jwt')->id)->where('ClientId', $request->input('client_id'))->where('UUID', '!=', $uuid)->first();
                if ($exist) throw new Exception('Ya existe un contacto con el mismo ClientId');
            } else {
                $institution = StpInstitutions::where('Code', $request->input('institution'))->first();
                if (!$institution) throw new Exception('La institución no existe');

                if (strlen($request->input('account')) < 18) throw new Exception('La cuenta CLABE debe tener 18 dígitos');
                if (strlen($request->input('account')) > 18) throw new Exception('La cuenta CLABE debe tener 18 dígitos');

                $exist = Contact::where('UserId', $request->attributes->get('jwt')->id)->where('Account', $request->input('account'))->where('UUID', '!=', $uuid)->first();
                if ($exist) throw new Exception('Ya existe un contacto con la misma cuenta CLABE');
            }

            $contact->update([
                'Name' => $request->input('name'),
                'Institution' => $request->input('institution'),
                'Account' => $request->input('account') ?? "",
                'Alias' => $request->input('alias'),
                'ClientId' => $request->input('client_id') ?? "",
            ]);

            return response()->json(self::contactObject($contact));
        } catch (Exception $e) {
            return self::basicError($e->getMessage());
        }
    }

    public static function contactObject($contact)
    {
        $institution = $contact->Institution == 0 ? 'Card Cloud' : StpInstitutions::where('Code', $contact->Institution)->first()->ShortName;

        return [
            'contact_id' => $contact->UUID,
            'name' => $contact->Name,
            'institution_id' => $contact->Institution,
            'institution_name' => $institution,
            'account' => $contact->Account,
            'client_id' => $contact->ClientId,
            'alias' => $contact->Alias
        ];
    }
}
