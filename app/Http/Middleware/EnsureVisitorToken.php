<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Identifica a los visitantes anónimos con una cookie (UUID) para poder
 * registrar sus "me gusta", comentarios y clics de WhatsApp sin pedirles cuenta.
 */
class EnsureVisitorToken
{
    public const COOKIE = 'rye_visitor';

    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->cookie(self::COOKIE);
        $isNew = false;

        if (! $token || ! Str::isUuid($token)) {
            $token = (string) Str::uuid();
            $isNew = true;
        }

        $request->attributes->set('visitor_token', $token);

        $response = $next($request);

        if ($isNew) {
            $response->headers->setCookie(cookie(self::COOKIE, $token, 60 * 24 * 365, '/', null, false, true, false, 'Lax'));
        }

        return $response;
    }
}
