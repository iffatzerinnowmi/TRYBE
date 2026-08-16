<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class PaidAccessUnlockedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public int $completed,
        public int $target,
    ) {}

    public function build(): self
    {
        return $this->subject('Paid study access unlocked 🎉')
            ->view('emails.paid-access-unlocked');
    }
}