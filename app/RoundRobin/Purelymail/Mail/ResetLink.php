<?php

namespace App\RoundRobin\Purelymail\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;

/** "Forgot password" link, sent to the person's personal email instead of the work address. */
class ResetLink extends Mailable
{
    use Queueable;

    public $details;

    /** @param array $details first_name, address, url, backed */
    public function __construct(array $details)
    {
        $this->details = $details;
    }

    public function build()
    {
        return $this->subject(__('Reset your Round Robin password'))
            ->view('purelymail::emails.reset_link')
            ->text('purelymail::emails.reset_link_text')
            ->with(['d' => $this->details]);
    }
}
