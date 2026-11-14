<?php

namespace App\Mail;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\VerificationCode;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class VerificationCodeMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Customer $customer,
        public string $code,
        public string $purpose,
        public Branch $branch,
    ) {}

    public function envelope(): Envelope
    {
        $subject = $this->purpose === VerificationCode::RESET
            ? "{$this->code} es tu código para cambiar la contraseña"
            : "{$this->code} es tu código de verificación";

        return new Envelope(
            from: new Address(config('mail.from.address'), $this->branch->brandName()),
            subject: $subject.' · '.$this->branch->brandName(),
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.verification-code', with: [
            'minutes' => VerificationCode::TTL_MINUTES,
            'isReset' => $this->purpose === VerificationCode::RESET,
        ]);
    }
}
