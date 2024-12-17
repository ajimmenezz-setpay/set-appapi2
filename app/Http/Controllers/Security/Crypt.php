<?php

namespace App\Http\Controllers\Security;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Exception;

class Crypt extends Controller
{
    public static function decrypt($value)
    {
        try {
            if (empty($value)) {
                return '';
            }
            $ciphertext = base64_decode($value);
            $plaintext = openssl_decrypt(
                $ciphertext,
                'AES-256-CBC',
                env('OPENSSL_KEY'),
                OPENSSL_RAW_DATA,
                env('IV_KEY')
            );

            if ($plaintext === false) {
                throw new Exception('Unable to decrypt data.');
            }
            return $plaintext;
        } catch (Exception $e) {
            return $e->getMessage();
        }
    }

    public static function encrypt($value)
    {
        try {
            if (empty($value)) {
                return '';
            }
            $ciphertext = openssl_encrypt(
                $value,
                'AES-256-CBC',
                env('OPENSSL_KEY'),
                OPENSSL_RAW_DATA,
                env('IV_KEY')
            );

            return base64_encode($ciphertext);
        } catch (Exception $e) {
            return $e->getMessage();
        }
    }
}
