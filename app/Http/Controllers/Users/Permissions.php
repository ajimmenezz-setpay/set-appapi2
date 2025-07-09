<?php

namespace App\Http\Controllers\Users;

use App\Http\Controllers\Controller;
use App\Models\Users\Permissions as UsersPermissions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class Permissions extends Controller
{

    public function index(Request $request)
    {
        try {
            $permissions = [];
            foreach (self::getCategories() as $category) {
                if (isset($request->category) && $request->category != $category['id']) {
                    continue;
                }

                $permissions[] = [
                    'category' => $category['name'],
                    'modules' => self::getModules($category['id'])
                ];
            }

            return response()->json($permissions);
        } catch (\Exception $e) {
            return response("Error al obtener los permisos: " . $e->getMessage(), 500);
        }
    }


    public function categories()
    {
        return response()->json(self::getCategories());
    }

    public function modules(Request $request)
    {
        try {
            $categoryId = $request->input('categoryId');
            $modules = self::getModules($categoryId);
            return response()->json($modules);
        } catch (\Exception $e) {
            return response("Error al obtener los módulos: " . $e->getMessage(), 500);
        }
    }

    public static function getModules($categoryId = null)
    {
        return DB::table('cat_modules')
            ->leftJoin('cat_modules as cat_modules2', 'cat_modules.SubModuleId', '=', 'cat_modules2.Id')
            ->where('cat_modules.CategoryId', $categoryId)
            ->where('cat_modules.Active', 1)
            ->select(
                'cat_modules.Id',
                DB::raw("CONCAT(cat_modules.Name, IFNULL(cat_modules2.Name, '')) AS Name")
            )
            ->orderBy('cat_modules.Order', 'asc')
            ->orderBy('cat_modules.SubModuleId', 'asc')
            ->get();
    }


    public function store(Request $request)
    {
        try {
            $data = $request->validate([
                'CategoryId' => 'required|integer',
                'Name' => 'required|string|max:100',
                'Description' => 'nullable|string'
            ]);

            $permission = UsersPermissions::create($data);
            return response()->json($permission, 201);
        } catch (\Exception $e) {
            return response("Error al crear el permiso: " . $e->getMessage(), 500);
        }
    }

    public static function getCategories()
    {
        return DB::table('cat_modules_category')->where('Flag', 1)
            ->orderBy('Order')
            ->get(['Id', 'Name', 'Order'])
            ->map(function ($category) {
                return [
                    'id' => $category->Id,
                    'name' => $category->Name,
                    'order' => $category->Order
                ];
            });
    }
}
