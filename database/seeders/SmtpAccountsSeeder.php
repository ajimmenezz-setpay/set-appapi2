<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Crypt;

class SmtpAccountsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('smtp_accounts')->insert([
            [
                'name' => 'SET SMTP 1',
                'host' => 'smtp.gmail.com',
                'port' => 587,
                'encryption' => 'tls',
                'username' => Crypt::encryptString('noreplay@setpay.mx'),
                'password' => Crypt::encryptString('brryhngmidecsfpd'),
                'from_address' => 'noreplay@setpay.mx',
                'from_name' => 'Notificaciones Card Cloud / Spei Cloud',
                'daily_limit' => 2000,
                'sent_today' => 0,
                'is_next' => true,
                'active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'SET SMTP 2',
                'host' => 'smtp.gmail.com',
                'port' => 587,
                'encryption' => 'tls',
                'username' => Crypt::encryptString('noreplay1@setpay.mx'),
                'password' => Crypt::encryptString('rxyajrurczfopzto'),
                'from_address' => 'noreplay1@setpay.mx',
                'from_name' => 'Notificaciones Card Cloud / Spei Cloud',
                'daily_limit' => 2000,
                'sent_today' => 0,
                'is_next' => false,
                'active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'SET SMTP 3',
                'host' => 'smtp.gmail.com',
                'port' => 587,
                'encryption' => 'tls',
                'username' => Crypt::encryptString('noreplay2@setpay.mx'),
                'password' => Crypt::encryptString('jrqyehikygozliox'),
                'from_address' => 'noreplay2@setpay.mx',
                'from_name' => 'Notificaciones Card Cloud / Spei Cloud',
                'daily_limit' => 2000,
                'sent_today' => 0,
                'is_next' => false,
                'active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'SET SMTP 3',
                'host' => 'smtp.gmail.com',
                'port' => 587,
                'encryption' => 'tls',
                'username' => Crypt::encryptString('noreplau3@setpay.mx'),
                'password' => Crypt::encryptString('jrjkwtaepjptllpb'),
                'from_address' => 'noreplau3@setpay.mx',
                'from_name' => 'Notificaciones Card Cloud / Spei Cloud',
                'daily_limit' => 2000,
                'sent_today' => 0,
                'is_next' => false,
                'active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'SET SMTP 4',
                'host' => 'smtp.gmail.com',
                'port' => 587,
                'encryption' => 'tls',
                'username' => Crypt::encryptString('no-reply@setpay.mx'),
                'password' => Crypt::encryptString('wjnatjcemfntlrrs'),
                'from_address' => 'no-reply@setpay.mx',
                'from_name' => 'Notificaciones Card Cloud / Spei Cloud',
                'daily_limit' => 2000,
                'sent_today' => 0,
                'is_next' => false,
                'active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        ]);
    }
}
