<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class SmtpRotationService
{
    public function acquire(): object
    {
        return DB::transaction(function () {

            // Garantiza que solo haya un "next" (si no hay, lo repara)
            $current = DB::table('smtp_accounts')
                ->where('is_next', true)
                ->lockForUpdate()
                ->first();

            if (!$current) {
                $this->ensureHasNext();
                $current = DB::table('smtp_accounts')
                    ->where('is_next', true)
                    ->lockForUpdate()
                    ->first();
            }

            if (!$current) {
                throw new \RuntimeException('No hay SMTP marcado como siguiente.');
            }

            // Si no es usable, lo desmarca y avanza
            if (!$this->isUsable($current)) {
                DB::table('smtp_accounts')->where('id', $current->id)->update(['is_next' => false]);
                $this->advance($current->id);
                return $this->acquire();
            }

            // Reservar 1 envío (contabiliza)
            DB::table('smtp_accounts')->where('id', $current->id)->increment('sent_today');

            // Marcar siguiente en rotación
            $this->advance($current->id);

            return $current;
        });
    }

    private function isUsable(object $smtp): bool
    {
        if (!$smtp->active) return false;

        if (!empty($smtp->disabled_until) && now()->lt($smtp->disabled_until)) {
            return false;
        }

        if ($smtp->sent_today >= $smtp->daily_limit) {
            return false;
        }

        return true;
    }

    private function advance(int $currentId): void
    {
        // Apaga el actual como next (por si no lo apagaron antes)
        DB::table('smtp_accounts')->where('id', $currentId)->update(['is_next' => false]);

        // Buscar el siguiente ID mayor
        $next = DB::table('smtp_accounts')
            ->where('active', true)
            ->where(function ($q) {
                $q->whereNull('disabled_until')->orWhere('disabled_until', '<=', now());
            })
            ->whereColumn('sent_today', '<', 'daily_limit')
            ->where('id', '>', $currentId)
            ->orderBy('id')
            ->first();

        // Si no hay siguiente, regresar al primero disponible
        if (!$next) {
            $next = DB::table('smtp_accounts')
                ->where('active', true)
                ->where(function ($q) {
                    $q->whereNull('disabled_until')->orWhere('disabled_until', '<=', now());
                })
                ->whereColumn('sent_today', '<', 'daily_limit')
                ->orderBy('id')
                ->first();
        }

        if ($next) {
            DB::table('smtp_accounts')->where('id', $next->id)->update(['is_next' => true]);
        }
    }

    private function ensureHasNext(): void
    {
        // Intentar elegir el primero disponible
        $first = DB::table('smtp_accounts')
            ->where('active', true)
            ->where(function ($q) {
                $q->whereNull('disabled_until')->orWhere('disabled_until', '<=', now());
            })
            ->whereColumn('sent_today', '<', 'daily_limit')
            ->orderBy('id')
            ->first();

        if ($first) {
            DB::table('smtp_accounts')->update(['is_next' => false]);
            DB::table('smtp_accounts')->where('id', $first->id)->update(['is_next' => true]);
        }
    }
}
