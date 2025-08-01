<?php

namespace App\Http\Controllers\Backoffice;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Users\User as UsersUser;
use App\Models\Backoffice\Companies\CompaniesCommissions;
use App\Models\Backoffice\Companies\CompaniesCommissionsSpei;
use App\Models\Backoffice\Companies\CompaniesServices;
use App\Models\Backoffice\Companies\CompaniesServicesSpei;
use App\Models\Backoffice\Companies\CompaniesServicesCardCloud;
use App\Models\Backoffice\Company as CompanyModel;
use App\Models\Backoffice\Companies\CompanyProjection;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Ramsey\Uuid\Uuid;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use App\Http\Services\CardCloudApi;
use App\Models\Backoffice\Users\CompaniesAndUsers;

class Company extends Controller
{

    /**
     * @OA\Post(
     *      path="/backoffice/company/new",
     *      summary="Create a new company",
     *      tags={"Backoffice"},
     *      @OA\RequestBody(
     *         required=true,
     *        @OA\JsonContent(
     *           type="object",
     *          @OA\Property(property="fiscalName", type="string", example="Empresa S.A. de C.V."),
     *          @OA\Property(property="rfc", type="string", example="ABC123456789"),
     *          @OA\Property(property="commercialName", type="string", example="Empresa S.A."),
     *          @OA\Property(property="stpAccount", type="string", example="550e8400-e29b-41d4-a716-446655440000"),
     *          @OA\Property(property="isNewUser", type="boolean", example=true),
     *          @OA\Property(property="enableCardCloud", type="boolean", example=true),
     *          @OA\Property(property="enableSpeiCloud", type="boolean", example=true),
     *          @OA\Property(property="userName", type="string", example="Juan"),
     *          @OA\Property(property="userLastName", type="string", example="Pérez"),
     *          @OA\Property(property="userEmail", type="string", example="juan.perez@example.com"),
     *          @OA\Property(property="userPhone", type="string", example="555-1234"),
     *          @OA\Property(property="assignedUsers", type="array", @OA\Items(type="string"), example={"550e8400-e29b-41d4-a716-446655440001", "550e8400-e29b-41d4-a716-446655440002"}),
     *          @OA\Property(property="commissions", type="object",
     *              @OA\Property(property="speiOut", type="number", format="float", example=1.5),
     *              @OA\Property(property="speiIn", type="number", format="float", example=1.0),
     *              @OA\Property(property="internal", type="number", format="float", example=0.5),
     *              @OA\Property(property="feeStp", type="number", format="float", example=0.2)
     *          )
     *       )
     *    ),
     *
     *      @OA\Response(
     *          response=200,
     *          description="Company created successfully",
     *          @OA\JsonContent(
     *              type="object",
     *              @OA\Property(property="message", type="string", example="Company created successfully")
     *          )
     *      ),
     *
     *      @OA\Response(
     *          response=400,
     *          description="Bad Request",
     *          @OA\JsonContent(
     *              type="object",
     *              @OA\Property(property="message", type="string", example="El nombre fiscal es obligatorio (fiscalName).")
     *          )
     *     ),
     *
     *      @OA\Response(
     *          response=401,
     *          description="Unauthorized",
     *          @OA\JsonContent(
     *              type="object",
     *              @OA\Property(property="message", type="string", example="Unauthorized access.")
     *          )
     *      ),
     *
     *      @OA\Response(
     *          response=500,
     *          description="Internal Server Error",
     *          @OA\JsonContent(
     *              type="object",
     *              @OA\Property(property="message", type="string", example="Error interno del servidor.")
     *          )
     *      )
     * )
     */
    public function create(Request $request)
    {
        try {
            $request->validate([
                'fiscalName' => 'required|string|max:255',
                'rfc' => 'required|string|max:13',
                'commercialName' => 'nullable|string|max:255',
                'stpAccount' => 'uuid',
                'isNewUser' => 'required|boolean',
                'enableCardCloud' => 'required|boolean',
                'enableSpeiCloud' => 'required|boolean'
            ], [
                'fiscalName.required' => 'El nombre fiscal es obligatorio (fiscalName).',
                'fiscalName.string' => 'El nombre fiscal debe ser una cadena de texto (fiscalName).',
                'fiscalName.max' => 'El nombre fiscal no puede exceder los 255 caracteres (fiscalName).',
                'rfc.string' => 'El RFC debe ser una cadena de texto (rfc).',
                'rfc.max' => 'El RFC no puede exceder los 13 caracteres (rfc).',
                'commercialName.string' => 'El nombre comercial debe ser una cadena de texto (commercialName).',
                'commercialName.max' => 'El nombre comercial no puede exceder los 255 caracteres (commercialName).',
                'stpAccount.uuid' => 'La cuenta STP debe ser un UUID válido (stpAccount).',
                'isNewUser.required' => 'Debe indicar si el administrador será un nuevo usuario (isNewUser).',
                'isNewUser.boolean' => 'Debe indicar si es el administrador será un nuevo usuario (isNewUser).',
                'enableCardCloud.required' => 'Debe indicar si el servicio de Card Cloud se habilitará para la empresa (enableCardCloud).',
                'enableSpeiCloud.required' => 'Debe indicar si el servicio de SPEI Cloud se habilitará para la empresa (enableSpeiCloud).',
                'enableCardCloud.boolean' => 'Debe indicar si el servicio de Card Cloud se habilitará para la empresa (enableCardCloud).',
                'enableSpeiCloud.boolean' => 'Debe indicar si el servicio de SPEI Cloud se habilitará para la empresa (enableSpeiCloud).'
            ]);

            if (!$request->enableSpeiCloud && !$request->enableCardCloud) {
                throw new \Exception('Debe habilitar al menos uno de los servicios disponibles');
            }

            if ($request->isNewUser) {
                $request->validate([
                    'userName' => 'required|string|max:255',
                    'userLastName' => 'required|string|max:255',
                    'userEmail' => 'required|email|max:255'
                ], [
                    'userName.required' => 'El nombre del usuario es obligatorio (userName).',
                    'userName.string' => 'El nombre del usuario debe ser una cadena de texto (userName).',
                    'userName.max' => 'El nombre del usuario no puede exceder los 255 caracteres (userName).',
                    'userLastName.required' => 'El apellido del usuario es obligatorio (userLastName).',
                    'userLastName.max' => 'El apellido del usuario no puede exceder los 255 caracteres (userLastName).',
                    'userLastName.string' => 'El apellido del usuario debe ser una cadena de texto (userLastName).',
                    'userEmail.required' => 'El correo electrónico del usuario es obligatorio (userEmail).',
                    'userEmail.email' => 'El correo electrónico del usuario debe ser una dirección de correo electrónico válida (userEmail).',
                    'userEmail.max' => 'El correo electrónico del usuario no puede exceder los 255 caracteres (userEmail).'
                ]);

                $user = User::where('Email', $request->userEmail)->first();
                if ($user) {
                    throw new \Exception('El correo electrónico del usuario ya está en uso.', 400);
                }
            }

            if (!$request->isNewUser) {
                $request->validate([
                    'assignedUsers' => 'required|array',
                ], [
                    'assignedUsers.required' => 'Los usuarios asignados son obligatorios (assignedUsers).',
                    'assignedUsers.array' => 'Los usuarios asignados deben ser un arreglo (assignedUsers).',
                ]);
            }

            if ($request->enableSpeiCloud) {
                $request->validate([
                    'commissions' => 'required|array',
                    'commissions.speiOut' => 'required|numeric|min:0|max:100',
                    'commissions.speiIn' => 'required|numeric|min:0|max:100',
                    'commissions.internal' => 'required|numeric|min:0|max:100',
                    'commissions.feeStp' => 'required|numeric|min:0|max:100',
                ], [
                    'commissions.speiOut.required' => 'La comisión SPEI Out es obligatoria (commissions.speiOut).',
                    'commissions.speiIn.required' => 'La comisión SPEI In es obligatoria (commissions.speiIn).',
                    'commissions.internal.required' => 'La comisión interna es obligatoria (commissions.internal).',
                    'commissions.feeStp.required' => 'La comisión STP es obligatoria (commissions.feeStp).',
                    'commissions.speiOut.numeric' => 'La comisión SPEI Out debe ser un número del 0 al 100 (commissions.speiOut).',
                    'commissions.speiIn.numeric' => 'La comisión SPEI In debe ser un número del 0 al 100 (commissions.speiIn).',
                    'commissions.internal.numeric' => 'La comisión interna debe ser un número del 0 al 100 (commissions.internal).',
                    'commissions.feeStp.numeric' => 'La comisión STP debe ser un número del 0 al 100 (commissions.feeStp).',
                    'commissions.speiOut.min' => 'La comisión SPEI Out debe ser al menos 0 (commissions.speiOut).',
                    'commissions.speiIn.min' => 'La comisión SPEI In debe ser al menos 0 (commissions.speiIn).',
                    'commissions.internal.min' => 'La comisión interna debe ser al menos 0 (commissions.internal).',
                    'commissions.feeStp.min' => 'La comisión STP debe ser al menos 0 (commissions.feeStp).',
                    'commissions.speiOut.max' => 'La comisión SPEI Out no puede exceder 100 (commissions.speiOut).',
                    'commissions.speiIn.max' => 'La comisión SPEI In no puede exceder 100 (commissions.speiIn).',
                    'commissions.internal.max' => 'La comisión interna no puede exceder 100 (commissions.internal).',
                    'commissions.feeStp.max' => 'La comisión STP no puede exceder 100 (commissions.feeStp).',
                ]);

                $availableAccount = DB::table('t_backoffice_bank_accounts')
                    ->where('BusinessId', $request->attributes->get('jwt')->businessId)
                    ->where('Available', 1)
                    ->first();

                if (!$availableAccount) {
                    throw new \Exception('No hay cuentas CLABE disponibles para asignar a la empresa (Spei Cloud).', 400);
                }
            }

            DB::beginTransaction();
            $company = self::createCompany($request);
            if ($request->enableSpeiCloud) {
                $commissions = self::createOrUpdateCommissions($request, $company, 2);
            }
            $speiCloudService = self::createOrUpdateCompanyServices($request, $company, 4, $request->enableSpeiCloud ? 1 : 0, $availableAccount ?? null);
            $cardCloudService = self::createOrUpdateCompanyServices($request, $company, 5, $request->enableCardCloud ? 1 : 0);

            $services = [];

            if ($speiCloudService && !empty($speiCloudService)) {
                array_push($services, $speiCloudService);
            }

            if ($cardCloudService && !empty($cardCloudService)) {
                array_push($services, $cardCloudService);
            }

            if ($request->isNewUser) {
                $users = UsersUser::create([
                    'name' => $request->userName,
                    'lastName' => $request->userLastName,
                    'email' => $request->userEmail,
                    'phone' => $request->userPhone ?? null,
                    'businessId' => $request->attributes->get('jwt')->businessId
                ], $company->Id);
                $users = [$users['object']];
            } else {
                $users = self::assignUsersToCompany($company, $request->assignedUsers);
            }

            $projection = self::createProjection($company, $commissions ?? [], $services, $users);


            DB::commit();

            return response()->json([], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response($e->getMessage() . (env('APP_DEBUG') ? ' en la línea ' . $e->getLine() : ''), 400);
        }
    }

    /**
     * @OA\Post(
     *      path="/backoffice/company/update",
     *      summary="Update an existing company",
     *      tags={"Backoffice"},
     *      @OA\RequestBody(
     *         required=true,
     *        @OA\JsonContent(
     *           type="object",
     *          @OA\Property(property="id", type="string", example="550e8400-e29b-41d4-a716-446655440000"),
     *          @OA\Property(property="fiscalName", type="string", example="Empresa S.A. de C.V."),
     *          @OA\Property(property="rfc", type="string", example="ABC123456789"),
     *          @OA\Property(property="commercialName", type="string", example="Empresa S.A."),
     *          @OA\Property(property="stpAccount", type="string", example="550e8400-e29b-41d4-a716-446655440000"),
     *          @OA\Property(property="isNewUser", type="boolean", example=true),
     *          @OA\Property(property="enableCardCloud", type="boolean", example=true),
     *          @OA\Property(property="enableSpeiCloud", type="boolean", example=true),
     *          @OA\Property(property="userName", type="string", example="Juan"),
     *          @OA\Property(property="userLastName", type="string", example="Pérez"),
     *          @OA\Property(property="userEmail", type="string", example="juan.perez@example.com"),
     *          @OA\Property(property="userPhone", type="string", example="555-1234"),
     *          @OA\Property(property="assignedUsers", type="array", @OA\Items(type="string"), example={"550e8400-e29b-41d4-a716-446655440001", "550e8400-e29b-41d4-a716-446655440002"}),
     *          @OA\Property(property="commissions", type="object",
     *              @OA\Property(property="speiOut", type="number", format="float", example=1.5),
     *              @OA\Property(property="speiIn", type="number", format="float", example=1.0),
     *              @OA\Property(property="internal", type="number", format="float", example=0.5),
     *              @OA\Property(property="feeStp", type="number", format="float", example=0.2)
     *          )
     *       )
     *    ),
     *
     *      @OA\Response(
     *          response=200,
     *          description="Company updated successfully",
     *          @OA\JsonContent(
     *              type="object",
     *              @OA\Property(property="message", type="string", example="Company updated successfully")
     *          )
     *      ),
     *
     *      @OA\Response(
     *          response=400,
     *          description="Bad Request",
     *          @OA\JsonContent(
     *              type="object",
     *              @OA\Property(property="message", type="string", example="El nombre fiscal es obligatorio (fiscalName).")
     *          )
     *     ),
     *
     *      @OA\Response(
     *          response=401,
     *          description="Unauthorized",
     *          @OA\JsonContent(
     *              type="object",
     *              @OA\Property(property="message", type="string", example="Unauthorized access.")
     *          )
     *      ),
     *
     *      @OA\Response(
     *          response=500,
     *          description="Internal Server Error",
     *          @OA\JsonContent(
     *              type="object",
     *              @OA\Property(property="message", type="string", example="Error interno del servidor.")
     *          )
     *      )
     * )
     */
    public function update(Request $request)
    {
        try {
            $request->validate([
                'id' => 'required|uuid',
                'fiscalName' => 'required|string|max:255',
                'commercialName' => 'nullable|string|max:255',
                'stpAccount' => 'uuid',
                'isNewUser' => 'required|boolean',
                'enableCardCloud' => 'required|boolean',
                'enableSpeiCloud' => 'required|boolean'
            ], [
                'id.required' => 'El ID de la empresa es obligatorio (id).',
                'fiscalName.required' => 'El nombre fiscal es obligatorio (fiscalName).',
                'fiscalName.string' => 'El nombre fiscal debe ser una cadena de texto (fiscalName).',
                'fiscalName.max' => 'El nombre fiscal no puede exceder los 255 caracteres (fiscalName).',
                'commercialName.string' => 'El nombre comercial debe ser una cadena de texto (commercialName).',
                'commercialName.max' => 'El nombre comercial no puede exceder los 255 caracteres (commercialName).',
                'stpAccount.uuid' => 'La cuenta STP debe ser un UUID válido (stpAccount).',
                'isNewUser.required' => 'Debe indicar si el administrador será un nuevo usuario (isNewUser).',
                'isNewUser.boolean' => 'Debe indicar si es el administrador será un nuevo usuario (isNewUser).',
                'enableCardCloud.required' => 'Debe indicar si el servicio de Card Cloud se habilitará para la empresa (enableCardCloud).',
                'enableSpeiCloud.required' => 'Debe indicar si el servicio de SPEI Cloud se habilitará para la empresa (enableSpeiCloud).',
                'enableCardCloud.boolean' => 'Debe indicar si el servicio de Card Cloud se habilitará para la empresa (enableCardCloud).',
                'enableSpeiCloud.boolean' => 'Debe indicar si el servicio de SPEI Cloud se habilitará para la empresa (enableSpeiCloud).'
            ]);

            if (!$request->enableSpeiCloud && !$request->enableCardCloud) {
                throw new \Exception('Debe habilitar al menos uno de los servicios disponibles');
            }

            if ($request->isNewUser) {
                $request->validate([
                    'userName' => 'required|string|max:255',
                    'userLastName' => 'required|string|max:255',
                    'userEmail' => 'required|email|max:255'
                ], [
                    'userName.required' => 'El nombre del usuario es obligatorio (userName).',
                    'userName.string' => 'El nombre del usuario debe ser una cadena de texto (userName).',
                    'userName.max' => 'El nombre del usuario no puede exceder los 255 caracteres (userName).',
                    'userLastName.required' => 'El apellido del usuario es obligatorio (userLastName).',
                    'userLastName.max' => 'El apellido del usuario no puede exceder los 255 caracteres (userLastName).',
                    'userLastName.string' => 'El apellido del usuario debe ser una cadena de texto (userLastName).',
                    'userEmail.required' => 'El correo electrónico del usuario es obligatorio (userEmail).',
                    'userEmail.email' => 'El correo electrónico del usuario debe ser una dirección de correo electrónico válida (userEmail).',
                    'userEmail.max' => 'El correo electrónico del usuario no puede exceder los 255 caracteres (userEmail).'
                ]);

                $user = User::where('Email', $request->userEmail)->first();
                if ($user) {
                    throw new \Exception('El correo electrónico del usuario ya está en uso.', 400);
                }
            }

            if (!$request->isNewUser) {
                $request->validate([
                    'assignedUsers' => 'required|array',
                ], [
                    'assignedUsers.required' => 'Los usuarios asignados son obligatorios (assignedUsers).',
                    'assignedUsers.array' => 'Los usuarios asignados deben ser un arreglo (assignedUsers).',
                ]);
            }

            if ($request->enableSpeiCloud) {
                $request->validate([
                    'commissions' => 'required|array',
                    'commissions.speiOut' => 'required|numeric|min:0|max:100',
                    'commissions.speiIn' => 'required|numeric|min:0|max:100',
                    'commissions.internal' => 'required|numeric|min:0|max:100',
                    'commissions.feeStp' => 'required|numeric|min:0|max:100',
                ], [
                    'commissions.speiOut.required' => 'La comisión SPEI Out es obligatoria (commissions.speiOut).',
                    'commissions.speiIn.required' => 'La comisión SPEI In es obligatoria (commissions.speiIn).',
                    'commissions.internal.required' => 'La comisión interna es obligatoria (commissions.internal).',
                    'commissions.feeStp.required' => 'La comisión STP es obligatoria (commissions.feeStp).',
                    'commissions.speiOut.numeric' => 'La comisión SPEI Out debe ser un número del 0 al 100 (commissions.speiOut).',
                    'commissions.speiIn.numeric' => 'La comisión SPEI In debe ser un número del 0 al 100 (commissions.speiIn).',
                    'commissions.internal.numeric' => 'La comisión interna debe ser un número del 0 al 100 (commissions.internal).',
                    'commissions.feeStp.numeric' => 'La comisión STP debe ser un número del 0 al 100 (commissions.feeStp).',
                    'commissions.speiOut.min' => 'La comisión SPEI Out debe ser al menos 0 (commissions.speiOut).',
                    'commissions.speiIn.min' => 'La comisión SPEI In debe ser al menos 0 (commissions.speiIn).',
                    'commissions.internal.min' => 'La comisión interna debe ser al menos 0 (commissions.internal).',
                    'commissions.feeStp.min' => 'La comisión STP debe ser al menos 0 (commissions.feeStp).',
                    'commissions.speiOut.max' => 'La comisión SPEI Out no puede exceder 100 (commissions.speiOut).',
                    'commissions.speiIn.max' => 'La comisión SPEI In no puede exceder 100 (commissions.speiIn).',
                    'commissions.internal.max' => 'La comisión interna no puede exceder 100 (commissions.internal).',
                    'commissions.feeStp.max' => 'La comisión STP no puede exceder 100 (commissions.feeStp).',
                ]);

                $hasSpeiCloud = CompaniesServices::where('CompanyId', $request->id)
                    ->where('Type', 4)
                    ->first();

                if (!$hasSpeiCloud) {
                    $availableAccount = DB::table('t_backoffice_bank_accounts')
                        ->where('BusinessId', $request->attributes->get('jwt')->businessId)
                        ->where('Available', 1)
                        ->first();

                    if (!$availableAccount) {
                        throw new \Exception('No hay cuentas CLABE disponibles para asignar a la empresa (Spei Cloud).', 400);
                    }
                } else {
                    $stpService = CompaniesServicesSpei::where('Id', $hasSpeiCloud->Id)->first();
                    $availableAccount = DB::table('t_backoffice_bank_accounts')->where('Id', $stpService->BankAccountId)->first();
                }
            }

            DB::beginTransaction();
            $company = self::updateCompany($request);
            if ($request->enableSpeiCloud) {
                $commissions = self::createOrUpdateCommissions($request, $company, 2);
            }
            $speiCloudService = self::createOrUpdateCompanyServices($request, $company, 4, $request->enableSpeiCloud ? 1 : 0, $availableAccount ?? null);
            $cardCloudService = self::createOrUpdateCompanyServices($request, $company, 5, $request->enableCardCloud ? 1 : 0);

            $services = [];

            if ($speiCloudService && !empty($speiCloudService)) {
                array_push($services, $speiCloudService);
            }

            if ($cardCloudService && !empty($cardCloudService)) {
                array_push($services, $cardCloudService);
            }

            if ($request->isNewUser) {
                $users = UsersUser::create([
                    'name' => $request->userName,
                    'lastName' => $request->userLastName,
                    'email' => $request->userEmail,
                    'phone' => $request->userPhone ?? null,
                    'businessId' => $request->attributes->get('jwt')->businessId
                ], $request->id);
                $users = self::assignUsersToCompany($company, [$users['object']['id']]);
            } else {
                $users = self::assignUsersToCompany($company, $request->assignedUsers);
            }

            $projection = self::updateProjection($company, $commissions ?? [], $services, $users);


            DB::commit();

            return response()->json($projection, 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response($e->getMessage() . (env('APP_DEBUG') ? ' en la línea ' . $e->getLine() : ''), 400);
        }
    }



    public function toggle(Request $request)
    {
        try {
            $request->validate([
                'company' => 'required|uuid',
                'active' => 'required'
            ], [
                'company.required' => 'El ID de la empresa es obligatorio (company).',
                'active.required' => 'El estado activo es obligatorio (active).'
            ]);

            DB::beginTransaction();
            CompanyModel::where('Id', $request->company)
                ->update(['Active' => $request->boolean('active') ? 1 : 0, 'UpdatedByUser' => $request->attributes->get('jwt')->id, 'UpdateDate' => now()]);

            CompanyProjection::where('Id', $request->company)
                ->update(['Active' => $request->boolean('active') ? 1 : 0, 'UpdatedByUser' => $request->attributes->get('jwt')->id, 'UpdateDate' => now()]);

            DB::commit();

            return response()->json(['message' => 'Empresa actualizada correctamente.'], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response($e->getMessage() . (env('APP_DEBUG') ? ' en la línea ' . $e->getLine() : ''), 400);
        }
    }


    public static function createCompany($request)
    {
        $existingCompany = CompanyModel::where('RFC', $request->rfc)->first();
        if ($existingCompany) {
            throw new \Exception('Ya existe una empresa con el RFC proporcionado.', 400);
        }

        return CompanyModel::create([
            'Id' => Uuid::uuid7(),
            'Folio' => CompanyModel::max('Folio') + 1,
            'Type' => 1,
            'BusinessId' => $request->attributes->get('jwt')->businessId,
            'companyId' => "",
            'FiscalPersonType' => $request->fiscalPersonType ?? 1,
            'FiscalName' => $request->fiscalName,
            'TradeName' => $request->commercialName ?? $request->fiscalName,
            'RFC' => $request->rfc,
            'PostalAddress' => $request->postalAddress ?? "",
            'PhoneNumbers' => $request->phoneNumbers ?? "",
            'Logo' => $request->logo ?? "",
            'Slug' => $request->slug ?? "",
            'Balance' => 0,
            'StatusId' => 3,
            'RegisterStep' => 4,
            'UpdatedByUser' => $request->attributes->get('jwt')->id,
            'UpdateDate' => now(),
            'CreatedByUser' => $request->attributes->get('jwt')->id,
            'CreateDate' => now(),
            'Active' => 1
        ]);
    }

    public static function updateCompany($request)
    {
        CompanyModel::where('Id', $request->id)->update(
            [
                'FiscalName' => $request->fiscalName,
                'TradeName' => $request->commercialName ?? $request->fiscalName,
                'PostalAddress' => $request->postalAddress ?? "",
                'PhoneNumbers' => $request->phoneNumbers ?? "",
                'Logo' => $request->logo ?? "",
                'Slug' => $request->slug ?? "",
                'UpdatedByUser' => $request->attributes->get('jwt')->id,
                'UpdateDate' => now()
            ]
        );
        return CompanyModel::where('Id', $request->id)->first();
    }

    public static function createOrUpdateCommissions($request, $company, $type)
    {
        try {
            $mainCommission = CompaniesCommissions::where('CompanyId', $company->Id)
                ->where('Type', $type)
                ->first();
            if ($mainCommission) {
                $mainCommission->UpdatedByUser = $request->attributes->get('jwt')->id;
                $mainCommission->UpdateDate = now();
            } else {
                $mainCommission = new CompaniesCommissions();
                $mainCommission->Id = Uuid::uuid7();
                $mainCommission->Type = $type;
                $mainCommission->CompanyId = $company->Id;
                $mainCommission->CreatedByUser = $request->attributes->get('jwt')->id;
                $mainCommission->CreateDate = now();
            }
            $mainCommission->save();

            if ($type == 2) {
                $speiCommissions = CompaniesCommissionsSpei::where('Id', $mainCommission->Id)->first();
                if (!$speiCommissions) {
                    $speiCommissions = new CompaniesCommissionsSpei();
                    $speiCommissions->Id = $mainCommission->Id;
                }
                $speiCommissions->SpeiOut = $request->commissions['speiOut'];
                $speiCommissions->SpeiIn = $request->commissions['speiIn'];
                $speiCommissions->Internal = $request->commissions['internal'];
                $speiCommissions->FeeStp = $request->commissions['feeStp'];
                $speiCommissions->StpAccount = 0;
                $speiCommissions->save();
            }

            return [
                [
                    'id' => $mainCommission->Id,
                    'type' => $mainCommission->Type,
                    'companyId' => $mainCommission->CompanyId,
                    'speiOut' => $speiCommissions->SpeiOut ?? 0,
                    'speiIn' => $speiCommissions->SpeiIn ?? 0,
                    'internal' => $speiCommissions->Internal ?? 0,
                    'feeStp' => $speiCommissions->FeeStp ?? 0,
                    'stpAccount' => $speiCommissions->StpAccount ?? 0,
                    'updatedByUser' => $mainCommission->UpdatedByUser,
                    'updateDate' => $mainCommission->UpdateDate,
                    'createdByUser' => $mainCommission->CreatedByUser,
                    'createDate' => $mainCommission->CreateDate
                ]
            ];
        } catch (\Exception $e) {
            throw new \Exception('Error al crear o actualizar las comisiones: ' . $e->getMessage(), 500);
        }
    }

    public static function createOrUpdateCompanyServices($request, $company, $type, $enabled, $availableAccount = null)
    {
        try {
            $mainService = CompaniesServices::where('CompanyId', $company->Id)
                ->where('Type', $type)
                ->first();
            if ($mainService) {
                $mainService->UpdateByUser = $request->attributes->get('jwt')->id;
                $mainService->UpdateDate = now();
            } else {
                $mainService = new CompaniesServices();
                $mainService->Id = Uuid::uuid7();
                $mainService->Type = $type;
                $mainService->CompanyId = $company->Id;
                $mainService->CreatedByUser = $request->attributes->get('jwt')->id;
                $mainService->CreateDate = now();
            }
            $mainService->Active = $enabled;
            $mainService->save();

            $service = [
                'id' => "$mainService->Id",
                'type' => "$mainService->Type",
                'companyId' => "$mainService->CompanyId",
                'updateByUser' => "$mainService->UpdateByUser",
                'updateDate' => "$mainService->UpdateDate",
                'createdByUser' => "$mainService->CreatedByUser",
                'createDate' => "$mainService->CreateDate",
                'active' => "$mainService->Active"
            ];

            switch ($type) {
                case 4:
                    if ($enabled) {
                        $serviceSpei = CompaniesServicesSpei::where('Id', $mainService->Id)->first();
                        if (!$serviceSpei) {
                            $serviceSpei = new CompaniesServicesSpei();
                            $serviceSpei->Id = $mainService->Id;
                            $serviceSpei->StpAccountId = $request->stpAccount;
                            $serviceSpei->BankAccountId = $availableAccount->Id;
                            $serviceSpei->BankAccountNumber = $availableAccount->Number;
                            $serviceSpei->save();

                            DB::table('t_backoffice_bank_accounts')
                                ->where('Id', $availableAccount->Id)
                                ->update(['Available' => 0]);

                            $service['stpAccountId'] = "$serviceSpei->StpAccountId";
                            $service['bankAccountId'] = "$serviceSpei->BankAccountId";
                            $service['bankAccountNumber'] = "$serviceSpei->BankAccountNumber";
                        }
                    } else {
                        $service = [];
                    }
                    break;

                case 5:
                    if ($enabled) {
                        $serviceCardCloud = CompaniesServicesCardCloud::where('Id', $mainService->Id)->first();
                        if (!$serviceCardCloud) {
                            $subaccount = self::createCardCloudSubaccount($request, $company);
                            $serviceCardCloud = new CompaniesServicesCardCloud();
                            $serviceCardCloud->Id = $mainService->Id;
                            $serviceCardCloud->SubAccountId = $subaccount['subaccount_id'];
                            $serviceCardCloud->SubAccount = json_encode($subaccount);
                            $serviceCardCloud->save();

                            $service['subAccountId'] = "$serviceCardCloud->SubAccountId";
                            $service['subAccount'] = json_encode($subaccount);
                        }else{
                            $service['subAccountId'] = "$serviceCardCloud->SubAccountId";
                            $service['subAccount'] = $serviceCardCloud->SubAccount;
                        }
                    } else {
                        $service = [];
                    }
                    break;
            }
            return $service;
        } catch (\Exception $e) {
            throw new \Exception('Error al crear o actualizar los servicios de la empresa: ' . $e->getMessage() . ' en ' . $e->getFile() . ' en la línea ' . $e->getLine(), 500);
        }
    }

    public static function createCardCloudSubaccount($request, $company)
    {
        try {
            $client = new Client();
            $response = $client->request('POST', env('CARD_CLOUD_BASE_URL') . '/api/v1/subaccounts', [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Bearer ' . CardCloudApi::getToken($request->attributes->get('jwt')->id),
                ],
                'json' => [
                    'ExternalId' => $company->Id,
                    'Description' => strtoupper($request->rfc)
                ]
            ]);

            $decodedJson = json_decode($response->getBody(), true);

            return $decodedJson;
        } catch (RequestException $e) {
            throw new \Exception('No se pudo crear la subcuenta de Card Cloud: ' . $e->getMessage(), 500);
        } catch (\Exception $e) {
            throw new \Exception("Error al crear la subcuenta de Card Cloud: " . $e->getMessage(), 500);
        }
    }

    public static function createProjection($company, $commissions, $services, $users)
    {
        $projection = new CompanyProjection();
        $projection->Id = $company->Id;
        $projection->Folio = $company->Folio;
        $projection->Type = $company->Type;
        $projection->TypeName = 'Formal';
        $projection->BusinessId = $company->BusinessId;
        $projection->CompanyId = "";
        $projection->FiscalPersonType = $company->FiscalPersonType;
        $projection->FiscalName = $company->FiscalName;
        $projection->TradeName = $company->TradeName;
        $projection->Rfc = $company->RFC;
        $projection->PostalAddress = $company->PostalAddress ?? "";
        $projection->PhoneNumbers = $company->PhoneNumbers ?? "";
        $projection->Logo = $company->Logo ?? "";
        $projection->Slug = $company->Slug ?? "";
        $projection->Balance = $company->Balance;
        $projection->StatusId = $company->StatusId;
        $projection->StatusName = 'Afiliado';
        $projection->RegisterStep = $company->RegisterStep;
        $projection->Users = json_encode($users);
        $projection->Services = json_encode($services);
        $projection->Documents = json_encode([]);
        $projection->Commissions = json_encode($commissions);
        $projection->CostCenters = json_encode([]);
        $projection->UpdatedByUser = $company->UpdatedByUser;
        $projection->UpdateDate = $company->UpdateDate;
        $projection->CreatedByUser = $company->CreatedByUser;
        $projection->CreateDate = $company->CreateDate;
        $projection->Active = $company->Active;
        $projection->save();

        return $projection;
    }

    public static function updateProjection($company, $commissions, $services, $users)
    {
        $projection = CompanyProjection::where('Id', $company->Id)->update(
            [
                'FiscalName' => $company->FiscalName,
                'TradeName' => $company->TradeName,
                'PostalAddress' => $company->PostalAddress ?? "",
                'PhoneNumbers' => $company->PhoneNumbers ?? "",
                'Logo' => $company->Logo ?? "",
                'Slug' => $company->Slug ?? "",
                'Users' => json_encode($users),
                'Services' => json_encode($services),
                'Documents' => json_encode([]),
                'Commissions' => json_encode($commissions),
                'CostCenters' => json_encode([]),
                'UpdatedByUser' => $company->UpdatedByUser,
                'UpdateDate' => $company->UpdateDate,
                'Active' => $company->Active
            ]
        );

        return CompanyProjection::where('Id', $company->Id)->first();
    }

    public static function assignUsersToCompany($company, $users, $deleteOthers = true)
    {

        $newUsers = [];
        $projection = self::getProjection($company->Id);
        if ($projection) {
            $projectionUsers = json_decode($projection->Users, true);

            foreach ($projectionUsers as $user) {
                if ($user['profile'] != 7 || !$deleteOthers) {
                    $newUsers[] = $user;
                }
            }
        }

        if ($deleteOthers) {
            CompaniesAndUsers::where('CompanyId', $company->Id)->where('ProfileId', '!=', "7")->delete();
        }

        foreach ($users as $user) {
            $u = User::where('Id', $user)->first();
            if ($u) {

                if ($deleteOthers) {
                    $companyUser = new CompaniesAndUsers();
                    $companyUser->CompanyId = $company->Id;
                    $companyUser->UserId = $u->Id;
                    $companyUser->ProfileId = $u->ProfileId;
                    $companyUser->Name = $u->Name;
                    $companyUser->Lastname = $u->Lastname;
                    $companyUser->Email = $u->Email;
                    $companyUser->CreateDate = now();
                    $companyUser->save();
                }

                $newUsers[] = [
                    'id' => $u->Id,
                    'companyId' => $company->Id,
                    'profile' => $u->ProfileId,
                    'name' => $u->Name,
                    'lastname' => $u->Lastname,
                    'email' => $u->Email,
                    'createDate' => $companyUser->CreateDate
                ];
            }
        }

        return $newUsers;
    }

    public static function getProjection($companyId)
    {
        return CompanyProjection::where('Id', $companyId)->first();
    }

    public static function getCompany($companyId)
    {
        return CompanyModel::where('Id', $companyId)->first();
    }

    public static function getCompanyIdByAccount($account)
    {
        return DB::table('t_backoffice_companies_services')
            ->join('t_backoffice_companies_service_stp', 't_backoffice_companies_services.Id', '=', 't_backoffice_companies_service_stp.Id')
            ->select('CompanyId')
            ->where('t_backoffice_companies_service_stp.BankAccountNumber', $account)
            ->first();
    }
}
