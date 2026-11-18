<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Throwable;

/**
 * "Puerta de actualización" que usa GitHub Actions (el hosting no tiene terminal/SSH).
 *
 *   POST /_deploy/estado  → huella de las librerías instaladas (vendor)
 *   POST /_deploy/vendor  → instala storage/app/deploy/vendor.zip (solo si composer.lock cambió)
 *   POST /_deploy         → migraciones pendientes + limpiar cachés
 *
 * Protección: solo POST, cabecera X-Deploy-Token = DEPLOY_TOKEN del .env (≥ 40 caracteres),
 * comparación en tiempo constante y límite de intentos fallidos.
 */
class DeployController extends Controller
{
    private const HASH_FILE = 'vendor/.lock-hash';

    public function status(Request $request)
    {
        if ($denied = $this->guard($request)) {
            return $denied;
        }

        $file = base_path(self::HASH_FILE);

        return response()->json([
            'ok' => true,
            'vendor_hash' => is_file($file) ? trim(file_get_contents($file)) : null,
            'php' => PHP_VERSION,
            'zip' => class_exists(\ZipArchive::class),
        ]);
    }

    /** Reemplaza vendor/ por el contenido de storage/app/deploy/vendor.zip (subido por FTP). */
    public function vendor(Request $request)
    {
        if ($denied = $this->guard($request)) {
            return $denied;
        }

        @set_time_limit(300);
        $zip = storage_path('app/deploy/vendor.zip');
        $hash = preg_replace('/[^a-f0-9]/', '', (string) $request->header('X-Vendor-Hash'));

        if (! is_file($zip)) {
            return response()->json(['ok' => false, 'error' => 'No llegó vendor.zip'], 422);
        }

        $tmp = storage_path('app/deploy/vendor_new');
        $old = base_path('vendor_old_'.date('YmdHis'));

        try {
            File::deleteDirectory($tmp);
            File::ensureDirectoryExists($tmp);
            $this->extract($zip, $tmp);

            if (! is_file($tmp.'/vendor/autoload.php')) {
                throw new \RuntimeException('El zip no contiene vendor/autoload.php');
            }

            // Cambio casi instantáneo: renombrar carpetas (Linux). Si el sistema no lo permite
            // (p. ej. Windows con archivos en uso), se copian los archivos nuevos encima.
            if (@rename(base_path('vendor'), $old)) {
                if (! @rename($tmp.'/vendor', base_path('vendor'))) {
                    rename($old, base_path('vendor'));
                    throw new \RuntimeException('No se pudo colocar la carpeta vendor nueva');
                }
                File::deleteDirectory($old);
            } elseif (! File::copyDirectory($tmp.'/vendor', base_path('vendor'))) {
                throw new \RuntimeException('No se pudieron copiar las librerías nuevas');
            }
            file_put_contents(base_path(self::HASH_FILE), $hash);

            File::deleteDirectory($tmp);
            @unlink($zip);
        } catch (Throwable $e) {
            // Si algo falló a mitad del cambio, se restaura lo anterior
            if (! is_dir(base_path('vendor')) && is_dir($old)) {
                rename($old, base_path('vendor'));
            }
            Log::error('Fallo al instalar vendor: '.$e->getMessage());

            return response()->json(['ok' => false, 'error' => $e->getMessage()], 500);
        }

        Log::info('Librerías (vendor) actualizadas', ['hash' => $hash]);

        return response()->json(['ok' => true, 'vendor_hash' => $hash]);
    }

    public function __invoke(Request $request)
    {
        if ($denied = $this->guard($request)) {
            return $denied;
        }

        $steps = [];

        try {
            // Copia de seguridad antes de tocar la base de datos
            if (! app()->runningUnitTests()) {
                try {
                    $steps['backup'] = basename(\App\Services\DatabaseBackup::run('antes de desplegar'));
                } catch (Throwable $e) {
                    $steps['backup'] = 'falló: '.$e->getMessage();
                }
            }

            Artisan::call('migrate', ['--force' => true]);
            $steps['migrate'] = trim(Artisan::output()) ?: 'sin cambios';

            Artisan::call('optimize:clear');
            $steps['optimize:clear'] = 'ok';
        } catch (Throwable $e) {
            Log::error('Fallo en despliegue: '.$e->getMessage());

            return response()->json(['ok' => false, 'error' => $e->getMessage(), 'steps' => $steps], 500);
        }

        // Velocidad: dejar armadas las cachés de configuración, rutas, vistas y eventos.
        // Si algo falla, se limpian y la página sigue funcionando (solo un poco más lenta).
        if (! app()->runningUnitTests()) {
            try {
                Artisan::call('optimize');
                $steps['optimize'] = 'ok';
            } catch (Throwable $e) {
                Artisan::call('optimize:clear');
                $steps['optimize'] = 'omitido: '.$e->getMessage();
                Log::warning('No se pudieron armar las cachés: '.$e->getMessage());
            }
        }

        // opcache guarda el código PHP en memoria: se vacía para que el código recién subido se use al instante
        if (function_exists('opcache_get_status') && @opcache_get_status(false)) {
            $steps['opcache'] = function_exists('opcache_reset') && @opcache_reset() ? 'activo (memoria renovada)' : 'activo';
        } else {
            $steps['opcache'] = 'no disponible';
        }

        Log::info('Despliegue aplicado', $steps);

        return response()->json(['ok' => true, 'steps' => $steps, 'at' => now()->toIso8601String()]);
    }

    private function guard(Request $request)
    {
        $key = 'deploy:'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            return response()->json(['ok' => false, 'error' => 'Demasiados intentos'], 429);
        }

        $expected = (string) config('app.deploy_token');
        $given = (string) $request->header('X-Deploy-Token', '');

        if (strlen($expected) < 40 || ! hash_equals($expected, $given)) {
            RateLimiter::hit($key, 600);
            Log::warning('Intento de despliegue rechazado', ['ip' => $request->ip()]);

            return response()->json(['ok' => false, 'error' => 'No autorizado'], 403);
        }

        return null;
    }

    private function extract(string $zip, string $to): void
    {
        if (class_exists(\ZipArchive::class)) {
            $z = new \ZipArchive();
            if ($z->open($zip) !== true) {
                throw new \RuntimeException('No se pudo abrir vendor.zip');
            }
            $z->extractTo($to);
            $z->close();

            return;
        }

        // Sin la extensión zip: PharData también sabe abrir .zip
        (new \PharData($zip))->extractTo($to, null, true);
    }
}
