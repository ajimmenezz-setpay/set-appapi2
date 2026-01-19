<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Validator;
use App\Exceptions\ValidationException;
use App\Models\Backoffice\Business;
use App\Models\Backoffice\BusinessSmtp;
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
        ], 400);
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
            throw new ValidationException($validator->errors()->first(), 400);
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

    static public function encrypt($data)
    {
        $encrypter = new \Illuminate\Encryption\Encrypter(env('APP_SECURITY_KEY'), 'AES-256-CBC');
        return $encrypter->encrypt($data);
    }

    static public function decrypt($data)
    {
        $encrypter = new \Illuminate\Encryption\Encrypter(env('APP_SECURITY_KEY'), 'AES-256-CBC');
        return $encrypter->decrypt($data);
    }

    static public function needsNormalization($value): int
    {
        if (!is_numeric($value)) {
            return -1;
        }

        $value = (string) $value;

        // Notación científica
        if (stripos($value, 'e') !== false) {
            return 1;
        }

        // Más de 2 decimales
        if (preg_match('/\.\d{3,}$/', $value)) {
            return 1;
        }

        return 0;
    }


    static public function getClientId($prefix, $id)
    {
        return $prefix . str_pad($id, 7, '0', STR_PAD_LEFT);
    }

    public static function splitClientId($input)
    {
        if (preg_match('/^([A-Za-z]+)(\d+)$/', $input, $matches)) {
            $prefix = $matches[1]; // Primer grupo de captura: letras
            $number = (int) $matches[2]; // Segundo grupo de captura: números convertido a entero
            return [
                'prefix' => $prefix,
                'number' => $number,
            ];
        }

        // Retornar null si no coincide el patrón
        return null;
    }

    public static function setSMTP($businessId)
    {
        $smtp = Backoffice\SMTPController::smtpByBusinessId($businessId);
        if ($smtp) {
            if ($smtp['last_used_main'] == 1 && !is_null($smtp['host2'])) {
                config([
                    'mail.mailers.smtp.host' => $smtp['host2'],
                    'mail.mailers.smtp.port' => $smtp['port2'],
                    'mail.mailers.smtp.encryption' => $smtp['encryption2'],
                    'mail.mailers.smtp.username' => $smtp['username2'],
                    'mail.mailers.smtp.password' => $smtp['password2'],
                ]);

                BusinessSmtp::where('BusinessId', $businessId)->update(['LastUsedMain' => 0]);
            } else {
                config([
                    'mail.mailers.smtp.host' => $smtp['host'],
                    'mail.mailers.smtp.port' => $smtp['port'],
                    'mail.mailers.smtp.encryption' => $smtp['encryption'],
                    'mail.mailers.smtp.username' => $smtp['username'],
                    'mail.mailers.smtp.password' => $smtp['password'],
                ]);
                BusinessSmtp::where('BusinessId', $businessId)->update(['LastUsedMain' => 1]);
            }
        }
    }

    public static function baseUrlByBusinessId($businessId)
    {
        $business = Business::where('Id', $businessId)->first();
        return $business ? "https://" . $business->Domain : null;
    }
}
