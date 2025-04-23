<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Validator;
use App\Exceptions\ValidationException;
use Illuminate\Routing\Controller as BaseController;

abstract class Controller extends BaseController
{
    /**
     * @OA\Info(
     *     title="APP SET V2",
     *     version="2.0.0",
     *     description="APP SET V2 Documentation",
     *     @OA\Contact(
     *         email="alonso@setpay.mx"
     *     )
     * )
     */

    public static function error($error)
    {
        return response()->json([
            'error' => $error
        ]);
    }

    public static function basicError($error)
    {
        return response($error, 400);
    }

    public static function success($data)
    {
        return response()->json($data);
    }

    public static function validate($request, $rules, $messages = [])
    {
        $validator = Validator::make($request->all(), $rules, $messages);

        if ($validator->fails()) {
            throw new ValidationException($validator->errors()->first());
        }
    }

    public static function printQuery($query)
    {
        $sql = $query->toSql();
        $bindings = $query->getBindings();

        foreach ($bindings as $binding) {
            $value = is_numeric($binding) ? $binding : "'$binding'";
            $sql = preg_replace('/\?/', $value, $sql, 1);
        }

        echo "SQL: $sql\n";
    }
}
