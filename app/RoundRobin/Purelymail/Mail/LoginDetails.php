<?php

namespace App\RoundRobin\Purelymail\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;

/** First-login email for a new person: address, password (or "your current one"), links. Sent via the system mailer. */
class LoginDetails extends Mailable
{
    use Queueable;

    public $details;

    /**
     * @param array $details first_name, address, password (null = keeps their current email password),
     *                       mailboxes (names they can see), dashboard, webmail, lost_password, imap, smtp
     */
    public function __construct(array $details)
    {
        $this->details = $details;
    }

    public function build()
    {
        $mail = $this->subject(__('Your Round Robin email and inbox login'))
            ->view('purelymail::emails.login_details')
            ->text('purelymail::emails.login_details_text')
            ->with(['d' => $this->details]);
        if ($reply_to = config('purelymail.login_details_reply_to')) {
            $mail->replyTo($reply_to);
        }

        return $mail;
    }
}
