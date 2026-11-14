<?php

namespace App\Models;

use App\Mail\VerificationCodeMail;
use App\Support\CurrentBranch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

class VerificationCode extends Model
{
    public const VERIFY = 'verify_email';
    public const RESET = 'reset_password';

    public const TTL_MINUTES = 15;
    public const MAX_ATTEMPTS = 5;

    protected $fillable = ['customer_id', 'purpose', 'code_hash', 'attempts', 'expires_at', 'used_at'];

    protected $casts = [
        'expires_at' => 'datetime',
        'used_at' => 'datetime',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** Genera un código nuevo (anula los anteriores del mismo tipo) y lo envía al correo del cliente. */
    public static function issue(Customer $customer, string $purpose): void
    {
        static::where('customer_id', $customer->id)->where('purpose', $purpose)->whereNull('used_at')
            ->update(['used_at' => now()]);

        $code = (string) random_int(100000, 999999);

        static::create([
            'customer_id' => $customer->id,
            'purpose' => $purpose,
            'code_hash' => Hash::make($code),
            'expires_at' => now()->addMinutes(self::TTL_MINUTES),
        ]);

        Mail::to($customer->email)->send(new VerificationCodeMail($customer, $code, $purpose, app(CurrentBranch::class)->get()));
    }

    /**
     * Comprueba el código. Devuelve null si es correcto (y lo marca como usado) o el mensaje de error.
     */
    public static function check(Customer $customer, string $purpose, string $code): ?string
    {
        $record = static::where('customer_id', $customer->id)->where('purpose', $purpose)
            ->whereNull('used_at')->latest('id')->first();

        if (! $record) {
            return 'No hay un código activo. Pide uno nuevo.';
        }
        if ($record->expires_at->isPast()) {
            return 'El código venció. Pide uno nuevo.';
        }
        if ($record->attempts >= self::MAX_ATTEMPTS) {
            return 'Demasiados intentos con este código. Pide uno nuevo.';
        }

        $record->increment('attempts');

        if (! Hash::check(preg_replace('/\D/', '', $code), $record->code_hash)) {
            $left = self::MAX_ATTEMPTS - $record->attempts;

            return $left > 0 ? "Código incorrecto. Te quedan {$left} intentos." : 'Código incorrecto. Pide uno nuevo.';
        }

        $record->update(['used_at' => now()]);

        return null;
    }
}
