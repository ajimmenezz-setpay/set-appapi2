<?php

namespace App\Http\Controllers\Backoffice;

use App\Http\Controllers\Controller;
use App\Models\Backoffice\Business;
use App\Models\Business\SMTP;
use Illuminate\Http\Request;

class SMTPController extends Controller
{
    public function index(Request $request)
    {
        $smtp = \App\Models\Business\SMTP::get();
        if ($smtp->isEmpty()) {
            return response()->json("No SMTP credentials found", 404);
        }

        $response = [];
        foreach ($smtp as $item) {
            $response[] = self::smtpByBusinessId($item->BusinessId);
        }

        return response()->json($response);
    }

    public static function smtpByBusinessId($businessId){
        $smtp = SMTP::where('BusinessId', $businessId)->first();
        if (!$smtp) {
            return false;
        }

        return [
            'business_id' => $smtp->BusinessId,
            'business_name' => Business::where('Id', $smtp->BusinessId)->value('Name'),
            'host' => self::decrypt($smtp->SmtpHost),
            'port' => self::decrypt($smtp->SmtpPort),
            'username' => self::decrypt($smtp->SmtpUser),
            'password' => self::decrypt($smtp->SmtpPassword),
            'encryption' => self::decrypt($smtp->SmtpEncryption),
            'host2' => !is_null($smtp->SmtpHost2) ? self::decrypt($smtp->SmtpHost2) : null,
            'port2' => !is_null($smtp->SmtpPort2) ? self::decrypt($smtp->SmtpPort2) : null,
            'username2' => !is_null($smtp->SmtpUser2) ? self::decrypt($smtp->SmtpUser2) : null,
            'password2' => !is_null($smtp->SmtpPassword2) ? self::decrypt($smtp->SmtpPassword2) : null,
            'encryption2' => !is_null($smtp->SmtpEncryption2) ? self::decrypt($smtp->SmtpEncryption2) : null,
            'last_used_main' => $smtp->LastUsedMain ? $smtp->LastUsedMain : 0
        ];
    }

    public function store(Request $request)
    {
        try {
            $this->validate($request, [
                'business_id' => 'required|string|max:36|exists:t_backoffice_business,Id',
                'host' => 'required|string|max:255',
                'port' => 'required|integer',
                'username' => 'required|string|max:255',
                'password' => 'required|string|max:255',
                'encryption' => 'required|string|max:10',
                'host2' => 'string|max:255',
                'port2' => 'integer',
                'username2' => 'string|max:255',
                'password2' => 'string|max:255',
                'encryption2' => 'string|max:10'
            ]);

            $smtp = SMTP::where('BusinessId', $request->input('business_id'))->first();
            if (!$smtp) {
                SMTP::create([
                    "BusinessId" => $request->input('business_id'),
                    "SmtpHost" => self::encrypt($request->input('host')),
                    "SmtpPort" => self::encrypt($request->input('port')),
                    "SmtpUser" => self::encrypt($request->input('username')),
                    "SmtpPassword" => self::encrypt($request->input('password')),
                    "SmtpEncryption" => self::encrypt($request->input('encryption')),
                    "SmtpHost2" => $request->has('host2') ? self::encrypt($request->input('host2')) : null,
                    "SmtpPort2" => $request->has('port2') ? self::encrypt($request->input('port2')) : null,
                    "SmtpUser2" => $request->has('username2') ? self::encrypt($request->input('username2')) : null,
                    "SmtpPassword2" => $request->has('password2') ? self::encrypt($request->input('password2')) : null,
                    "SmtpEncryption2" => $request->has('encryption2') ? self::encrypt($request->input('encryption2')) : null,
                ]);
            } else {
                SMTP::where('BusinessId', $request->input('business_id'))->update([
                    "SmtpHost" => self::encrypt($request->input('host')),
                    "SmtpPort" => self::encrypt($request->input('port')),
                    "SmtpUser" => self::encrypt($request->input('username')),
                    "SmtpPassword" => self::encrypt($request->input('password')),
                    "SmtpEncryption" => self::encrypt($request->input('encryption')),
                    "SmtpHost2" => $request->has('host2') ? self::encrypt($request->input('host2')) : null,
                    "SmtpPort2" => $request->has('port2') ? self::encrypt($request->input('port2')) : null,
                    "SmtpUser2" => $request->has('username2') ? self::encrypt($request->input('username2')) : null,
                    "SmtpPassword2" => $request->has('password2') ? self::encrypt($request->input('password2')) : null,
                    "SmtpEncryption2" => $request->has('encryption2') ? self::encrypt($request->input('encryption2')) : null,
                ]);
            }

            return response()->json("Saved Successfully", 201);
        } catch (\Exception $e) {
            return response()->json("Error: " . $e->getMessage(), 500);
        }
    }
}
