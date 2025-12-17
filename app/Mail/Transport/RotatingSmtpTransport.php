<?php

namespace App\Mail\Transport;

use App\Services\SmtpRotationService;
use App\Services\SmtpTransportFactory;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\RawMessage;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Address;
use Throwable;

class RotatingSmtpTransport implements TransportInterface
{
    public function __construct(
        private SmtpRotationService $rotation,
        private SmtpTransportFactory $factory
    ) {}

    public function send(RawMessage $message, Envelope $envelope = null): ?SentMessage
    {
        $started = microtime(true);
        $smtp = null;

        try {
            // 1️⃣ Obtener SMTP según rotación
            $smtp = $this->rotation->acquire();

            // 2️⃣ Crear transport SMTP dinámico
            $transport = $this->factory->build($smtp);

            // 3️⃣ FORZAR FROM DINÁMICO (FORMA CORRECTA SYMFONY)
            if ($message instanceof Email) {
                $message->from(
                    new Address(
                        $smtp->from_address,
                        $smtp->from_name
                    )
                );
            }

            // 4️⃣ Enviar correo
            $sent = $transport->send($message, $envelope);

            $duration = (int) round((microtime(true) - $started) * 1000);

            // 5️⃣ Health reset del SMTP (envío exitoso)
            DB::table('smtp_accounts')->where('id', $smtp->id)->update([
                'fail_count' => 0,
                'last_error' => null,
                'disabled_until' => null,
                'active' => 1,
                'updated_at' => now(),
            ]);

            // 6️⃣ Log de envío exitoso
            DB::table('mail_delivery_logs')->insert([
                'smtp_account_id' => $smtp->id,
                'to_email' => $this->extractTo($envelope),
                'subject' => $this->extractSubject($message),
                'message_id' => method_exists($sent, 'getMessageId') ? $sent->getMessageId() : null,
                'status' => 'sent',
                'attempt' => 1,
                'duration_ms' => $duration,
                'error' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return $sent;

        } catch (Throwable $e) {
            $duration = (int) round((microtime(true) - $started) * 1000);

            // 7️⃣ Circuit breaker si falla
            if ($smtp) {
                DB::table('smtp_accounts')->where('id', $smtp->id)->update([
                    'fail_count' => DB::raw('fail_count + 1'),
                    'last_error' => substr($e->getMessage(), 0, 1000),
                    'disabled_until' => DB::raw(
                        "IF(fail_count + 1 >= 3, DATE_ADD(NOW(), INTERVAL 15 MINUTE), disabled_until)"
                    ),
                    'active' => DB::raw("IF(fail_count + 1 >= 3, 0, active)"),
                    'updated_at' => now(),
                ]);
            }

            // 8️⃣ Log de fallo
            DB::table('mail_delivery_logs')->insert([
                'smtp_account_id' => $smtp->id ?? null,
                'to_email' => $this->extractTo($envelope),
                'subject' => $this->extractSubject($message),
                'message_id' => null,
                'status' => 'failed',
                'attempt' => 1,
                'duration_ms' => $duration,
                'error' => substr($e->getMessage(), 0, 4000),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            throw $e;
        }
    }

    public function __toString(): string
    {
        return 'rotating_smtp';
    }

    private function extractTo(?Envelope $envelope): ?string
    {
        if (!$envelope) {
            return null;
        }

        $recipients = $envelope->getRecipients();
        return $recipients[0]->getAddress() ?? null;
    }

    private function extractSubject(RawMessage $message): ?string
    {
        if (!$message instanceof Email) {
            return null;
        }

        return $message->getSubject();
    }
}
