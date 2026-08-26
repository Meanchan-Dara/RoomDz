<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class OtpMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public $otp;

    public function __construct($otp)
    {
        $this->otp = $otp;
    }

    public function build()
    {
        return $this->subject('លេខកូដ OTP របស់អ្នក')
            ->html("<h3>លេខកូដរបស់អ្នកគឺ: <b>{$this->otp}</b></h3><p>វានឹងផុតកំណត់ក្នុងរយៈពេល ១០ នាទី។</p>");
    }
}
