<?php

namespace App\Mail\Transport;

use App\Services\SmtpRotationService;
use App\Services\SmtpTransportFactory;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\RawMessage;
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
            $smtp = $this->rotation->acquire();
            $transport = $this->factory->build($smtp);

            $sent = $transport->send($message, $envelope);

            $duration = (int) round((microtime(true) - $started) * 1000);

            // ✅ health reset (si salió bien)
            DB::table('smtp_accounts')->where('id', $smtp->id)->update([
                'fail_count' => 0,
                'last_error' => null,
                'disabled_until' => null,
                'active' => 1,
                'updated_at' => now(),
            ]);

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

            if ($smtp) {
                // Circuit breaker: 3 fallos => disable 15 min
                DB::table('smtp_accounts')->where('id', $smtp->id)->update([
                    'fail_count' => DB::raw('fail_count + 1'),
                    'last_error' => substr($e->getMessage(), 0, 1000),
                    'disabled_until' => DB::raw("IF(fail_count + 1 >= 3, DATE_ADD(NOW(), INTERVAL 15 MINUTE), disabled_until)"),
                    'active' => DB::raw("IF(fail_count + 1 >= 3, 0, active)"),
                    'updated_at' => now(),
                ]);
            }

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
        if (!$envelope) return null;
        $recipients = $envelope->getRecipients();
        return $recipients[0]->getAddress() ?? null;
    }

    private function extractSubject(RawMessage $message): ?string
    {
        if (!method_exists($message, 'getHeaders')) return null;
        $headers = $message->getHeaders();
        if (!$headers->has('Subject')) return null;

        return (string) $headers->get('Subject')->getBodyAsString();
    }
}
