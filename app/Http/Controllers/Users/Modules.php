<?php

namespace App\Http\Controllers\Users;

use App\Http\Controllers\Controller;
use App\Models\Modules\Category;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class Modules extends Controller
{

    /**
     * @OA\Get(
     *      path="/api/modules/user",
     *      tags={"Usuarios - Módulos"},
     *      summary="Obtener menú y permisos del usuario",
     *      description="Obtener el menú de módulos y permisos del usuario autenticado",
     *      security={{"bearerAuth":{}}},
     *
     *      @OA\Response(
     *          response=200,
     *          description="Successful response",
     *          @OA\JsonContent(
     *              type="object",
     *              @OA\Property(
     *                  property="menu",
     *                  type="array",
     *                  @OA\Items(
     *                      type="object",
     *                      @OA\Property(property="category", type="string", example="SPEI Cloud"),
     *                      @OA\Property(
     *                          property="modules",
     *                          type="array",
     *                          nullable=true,
     *                          @OA\Items(
     *                              type="object",
     *                              @OA\Property(property="icon", type="string", example="dashboard"),
     *                              @OA\Property(property="moduleActions", type="string", nullable=true, example=null),
     *                              @OA\Property(property="moduleName", type="string", example="Dashboard"),
     *                              @OA\Property(
     *                                  property="modules",
     *                                  type="array",
     *                                  nullable=true,
     *                                  @OA\Items(
     *                                      type="object",
     *                                      @OA\Property(property="icon", type="string", example="contacts"),
     *                                      @OA\Property(property="moduleActions", type="string", nullable=true, example=null),
     *                                      @OA\Property(property="moduleName", type="string", example="SPEI"),
     *                                      @OA\Property(property="path", type="string", example="/spei-cloud/spei-transfer")
     *                                  )
     *                              ),
     *                              @OA\Property(property="path", type="string", example="/spei-cloud")
     *                          )
     *                      )
     *                  )
     *              ),
     *              @OA\Property(
     *                  property="userActions",
     *                  type="string",
     *                  example="MP-VIABO-SPEI-ADMIN"
     *              )
     *          )
     *      ),
     *
     *      @OA\Response(
     *          response=404,
     *          description="Not Found",
     *          @OA\JsonContent(
     *              type="object",
     *              @OA\Property(property="error", type="string", example="User not found")
     *          )
     *      ),
     *
     *      @OA\Response(
     *          response=401,
     *          description="Unauthorized",
     *          @OA\JsonContent(
     *              type="object",
     *              @OA\Property(property="error", type="string", example="Unauthorized")
     *          )
     *      )
     * )
     */


    public function index(Request $request)
    {
        try {
            $user = User::find($request->attributes->get('jwt')->id);
            $menu = [
                "menu" => [],
                'userActions' => self::userActions($user->ProfileId)
            ];

            $categories = self::categories();
            foreach ($categories as $category) {
                $modules = self::modules($category->Id, $user->ProfileId);
                if (empty($modules)) {
                    continue; // Skip categories with no modules
                }

                $menu['menu'][] = [
                    'category' => $category->Name,
                    'modules' => $modules
                ];
            }

            return response()->json($menu);
        } catch (\Exception $e) {
            return response("Error al obtener los módulos: " . $e->getMessage(), 500);
        }
    }

    public static function categories()
    {
        return Category::where('Flag', 1)
            ->orderBy('Order', 'asc')
            ->get(['Id', 'Name', 'Order', 'ServiceId']);
    }

    public static function modules($categoryId, $profileId)
    {
        $modules = DB::table('cat_modules')
            ->join('t_modules_and_profiles', 'cat_modules.Id', '=', 't_modules_and_profiles.ModuleId')
            ->where('t_modules_and_profiles.ProfileId', $profileId)
            ->where('cat_modules.Active', 1)
            ->where('cat_modules.SubModuleId', 0)
            ->where('cat_modules.CategoryId', $categoryId)
            ->select('cat_modules.*')
            ->groupBy('cat_modules.Id')
            ->orderBy('cat_modules.Order', 'asc')
            ->get();


        $modules_return = [];

        foreach ($modules as $module) {
            $modules_return[] = [
                'icon' => $module->Icon,
                'moduleActions' => null,
                'moduleName' => $module->Name,
                'modules' => self::submodules($module->Id, $profileId),
                'path' => $module->Path ?? '/'
            ];
        }

        if (empty($modules_return)) {
            return null;
        }

        return $modules_return;
    }

    public static function submodules($moduleId, $profileId)
    {
        $submodules = DB::table('cat_modules')
            ->join('t_modules_and_profiles', 'cat_modules.Id', '=', 't_modules_and_profiles.ModuleId')
            ->where('t_modules_and_profiles.ProfileId', $profileId)
            ->where('cat_modules.Active', 1)
            ->where('cat_modules.SubModuleId', $moduleId)
            ->select('cat_modules.*')
            ->groupBy('cat_modules.Id')
            ->orderBy('cat_modules.Order', 'asc')
            ->get();

        $submodules_return = [];

        foreach ($submodules as $submodule) {
            $submodules_return[] = [
                'icon' => $submodule->Icon,
                'moduleActions' => null,
                'moduleName' => $submodule->Name,
                'path' => $submodule->Path ?? '/'
            ];
        }

        if (empty($submodules_return)) {
            return null;
        }

        return $submodules_return;
    }

    public static function userActions($profileId)
    {
        $actions = DB::table('cat_permissions')
            ->join('t_profile_permissions', 'cat_permissions.Id', '=', 't_profile_permissions.PermissionId')
            ->where('t_profile_permissions.ProfileId', $profileId)
            ->where('cat_permissions.Flag', 1)
            ->where('t_profile_permissions.Active', 1)
            ->select('cat_permissions.Key')
            ->get();

        $actions_return = [];
        foreach ($actions as $action) {
            $actions_return[] = $action->Key;
        }

        return $actions_return;
    }
}
