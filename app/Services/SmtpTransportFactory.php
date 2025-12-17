<?php

namespace App\Services;

use Illuminate\Support\Facades\Crypt;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;

class SmtpTransportFactory
{
    public function build(object $smtp): EsmtpTransport
    {
        $useSslFlag = ($smtp->encryption === 'ssl');

        $transport = new EsmtpTransport(
            $smtp->host,
            (int) $smtp->port,
            $useSslFlag
        );

        if (!empty($smtp->username)) {
            $transport->setUsername(Crypt::decryptString($smtp->username));
        }

        if (!empty($smtp->password)) {
            $transport->setPassword(Crypt::decryptString($smtp->password));
        }

        return $transport;
    }
}
