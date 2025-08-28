<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SendCredentialsToCreditUser extends Mailable
{
    use Queueable, SerializesModels;

    public $email;
    public $password;
    public $base_url;

    /**
     * Create a new message instance.
     */
    public function __construct($email, $password, $base_url)
    {
        $this->email = $email;
        $this->password = $password;
        $this->base_url = $base_url;
    }

    public function build()
    {
        return $this->subject('Credenciales de usuario')
            ->view('emails.new_credit_user_credentials');
    }
}
