<?php

namespace App\Services;

use App\Http\Controllers\Security\Crypt as SecurityCrypt;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\View;
use Illuminate\Mail\Mailer;
use Illuminate\Events\Dispatcher;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use App\Models\Backoffice\BusinessSmtp;

class DynamicMailService
{
    public static function send($business, $to, Mailable $mailable)
    {
        $smtp = BusinessSmtp::where('BusinessId', $business)->first();

        if (!$smtp) {
            $smtp = BusinessSmtp::where('Id', 1)->first();
        }

        $transport = new EsmtpTransport(
            $smtp->SmtpHost,
            $smtp->SmtpPort,
            $smtp->SmtpEncryption ?? 'tls'
        );

        $transport->setUsername(SecurityCrypt::decrypt($smtp->SmtpUser));
        $transport->setPassword(SecurityCrypt::decrypt($smtp->SmtpPassword));

        $mailer = new Mailer(
            'dynamic',
            View::getFacadeRoot(),
            $transport,
            app(Dispatcher::class)
        );

        $mailer->to($to)->send($mailable);
    }
}
