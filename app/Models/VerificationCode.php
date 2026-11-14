<?php

namespace App\Models;

use App\Mail\VerificationCodeMail;
use App\Support\CurrentBranch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

/**
 * Código de 6 dígitos enviado por correo. Sirve para un cliente (Customer) o para
 * alguien del equipo del panel (User). Solo se guarda el hash.
 */
class VerificationCode extends Model
{
    public const VERIFY = 'verify_email';
    public const RESET = 'reset_password';

    public const TTL_MINUTES = 15;
    public const MAX_ATTEMPTS = 5;

    protected $fillable = ['customer_id', 'user_id', 'purpose', 'code_hash', 'attempts', 'expires_at', 'used_at'];

    protected $casts = [
        'expires_at' => 'datetime',
        'used_at' => 'datetime',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Columna dueña del código según el tipo de persona. */
    private static function owner(Customer|User $who): array
    {
        return $who instanceof User ? ['user_id' => $who->id] : ['customer_id' => $who->id];
    }

    /** Genera un código nuevo (anula los anteriores del mismo tipo) y lo envía a su correo. */
    public static function issue(Customer|User $who, string $purpose): void
    {
        $owner = self::owner($who);

        static::where($owner)->where('purpose', $purpose)->whereNull('used_at')->update(['used_at' => now()]);

        $code = (string) random_int(100000, 999999);

        static::create([
            ...$owner,
            'purpose' => $purpose,
            'code_hash' => Hash::make($code),
            'expires_at' => now()->addMinutes(self::TTL_MINUTES),
        ]);

        $branch = $who instanceof User && $who->branch ? $who->branch : app(CurrentBranch::class)->get();

        Mail::to($who->email)->send(new VerificationCodeMail($who, $code, $purpose, $branch));
    }

    /**
     * Comprueba el código. Devuelve null si es correcto (y lo marca como usado) o el mensaje de error.
     */
    public static function check(Customer|User $who, string $purpose, string $code): ?string
    {
        $record = static::where(self::owner($who))->where('purpose', $purpose)
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
