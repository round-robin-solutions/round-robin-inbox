<?php

namespace App\RoundRobin\Purelymail\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;

/** New password set by an admin, sent to the person's personal email. Sent via the system mailer. */
class NewPassword extends Mailable
{
    use Queueable;

    public $details;

    /** @param array $details first_name, address, password, backed (also the email password), by, dashboard, webmail */
    public function __construct(array $details)
    {
        $this->details = $details;
    }

    public function build()
    {
        $mail = $this->subject(__('Your new Round Robin password'))
            ->view('purelymail::emails.new_password')
            ->text('purelymail::emails.new_password_text')
            ->with(['d' => $this->details]);
        if ($reply_to = config('purelymail.login_details_reply_to')) {
            $mail->replyTo($reply_to);
        }

        return $mail;
    }
}
