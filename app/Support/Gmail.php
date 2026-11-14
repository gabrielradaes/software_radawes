<?php

namespace App\Support;

use Illuminate\Http\Request;

/** Los clientes solo pueden registrarse con una cuenta @gmail.com. */
class Gmail
{
    public const MESSAGE = 'Solo se aceptan correos que terminen en @gmail.com.';

    /** Pasa el campo a minúsculas y sin espacios antes de validar. */
    public static function normalize(Request $request, string $field = 'email'): void
    {
        if ($request->filled($field)) {
            $request->merge([$field => strtolower(trim((string) $request->input($field)))]);
        }
    }

    public static function rules(array $extra = []): array
    {
        return ['required', 'string', 'max:150', 'email:rfc', 'regex:/^[a-z0-9](?:[a-z0-9.+_-]*[a-z0-9])?@gmail\.com$/', ...$extra];
    }

    /** juan.perez@gmail.com → ju*******z@gmail.com */
    public static function mask(?string $email): string
    {
        if (! $email || ! str_contains($email, '@')) {
            return (string) $email;
        }
        [$user, $domain] = explode('@', $email, 2);
        $visible = mb_strlen($user) <= 3 ? mb_substr($user, 0, 1) : mb_substr($user, 0, 2);

        return $visible.str_repeat('*', max(3, mb_strlen($user) - mb_strlen($visible) - 1)).mb_substr($user, -1).'@'.$domain;
    }
}
