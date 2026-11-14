<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Throwable;

/**
 * "Puerta de actualización": GitHub Actions la llama después de subir los archivos por FTP.
 * Aplica las migraciones pendientes y limpia cachés (el hosting no tiene terminal/SSH).
 *
 * Protección: solo POST, clave larga en la cabecera X-Deploy-Token que debe coincidir con
 * DEPLOY_TOKEN del .env (mínimo 40 caracteres), comparación en tiempo constante y límite de intentos.
 */
class DeployController extends Controller
{
    public function __invoke(Request $request)
    {
        $key = 'deploy:'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            return response()->json(['ok' => false, 'error' => 'Demasiados intentos'], 429);
        }
        RateLimiter::hit($key, 600);

        $expected = (string) config('app.deploy_token');
        $given = (string) $request->header('X-Deploy-Token', '');

        if (strlen($expected) < 40 || ! hash_equals($expected, $given)) {
            Log::warning('Intento de despliegue rechazado', ['ip' => $request->ip()]);

            return response()->json(['ok' => false, 'error' => 'No autorizado'], 403);
        }

        RateLimiter::clear($key);
        $steps = [];

        try {
            Artisan::call('migrate', ['--force' => true]);
            $steps['migrate'] = trim(Artisan::output()) ?: 'sin cambios';

            Artisan::call('optimize:clear');
            $steps['optimize:clear'] = 'ok';
        } catch (Throwable $e) {
            Log::error('Fallo en despliegue: '.$e->getMessage());

            return response()->json(['ok' => false, 'error' => $e->getMessage(), 'steps' => $steps], 500);
        }

        Log::info('Despliegue aplicado', $steps);

        return response()->json(['ok' => true, 'steps' => $steps, 'at' => now()->toIso8601String()]);
    }
}
