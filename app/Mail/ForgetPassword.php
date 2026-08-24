<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ForgetPassword extends Mailable
{
    use Queueable, SerializesModels;

    public $username;

    public $token;

    /**
     * Create a new message instance.
     */
    public function __construct($username, $token)
    {
        $this->username = $username;
        $this->token = $token;

    }

    public function build()
    {
        return $this->subject('Password Reset')
            ->view('emails.forgotPassword');
    }
}
