<?php

namespace App\Support;

use Closure;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

/**
 * Videos subidos a la página (los que salen del editor de video o del celular).
 * Igual que ImageUpload, el tipo se reconoce leyendo el contenido del archivo (no depende de fileinfo):
 * MP4/MOV tienen "ftyp" en el byte 4 y WebM empieza con 1A 45 DF A3.
 */
class VideoUpload
{
    public const MAX_MB = 100;

    /** Tamaño máximo real: lo que permita el servidor, sin pasar de MAX_MB. */
    public static function maxMb(): int
    {
        $limits = [self::MAX_MB, self::iniMb('upload_max_filesize'), self::iniMb('post_max_size')];

        return max(1, min(array_filter($limits, fn ($v) => $v > 0)) - 1);
    }

    public static function rule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) {
            if (! $value instanceof UploadedFile || ! $value->isValid()) {
                $fail('No se pudo subir el video. Puede que sea muy pesado (máximo '.self::maxMb().' MB) o se cortó la conexión.');

                return;
            }
            if ($value->getSize() > self::maxMb() * 1024 * 1024) {
                $fail('El video pesa más de '.self::maxMb().' MB. Recórtalo en el editor o expórtalo más corto.');

                return;
            }
            if (! self::extension($value)) {
                $fail('«'.Str::limit($value->getClientOriginalName(), 40).'» no es un video válido. Usa MP4, MOV o WebM.');
            }
        };
    }

    public static function extension(UploadedFile $file): ?string
    {
        $fh = @fopen($file->getRealPath(), 'rb');
        if (! $fh) {
            return null;
        }
        $head = (string) fread($fh, 16);
        fclose($fh);

        if (strlen($head) >= 12 && substr($head, 4, 4) === 'ftyp') {
            return substr($head, 8, 2) === 'qt' ? 'mov' : 'mp4';
        }
        if (str_starts_with($head, "\x1A\x45\xDF\xA3")) {
            return 'webm';
        }

        return null;
    }

    /** Guarda en public_html/uploads/videos y devuelve la ruta pública relativa. */
    public static function store(UploadedFile $file): string
    {
        $name = Str::random(40).'.'.(self::extension($file) ?? 'mp4');
        $dir = public_path('uploads/videos');
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $file->move($dir, $name);

        return 'uploads/videos/'.$name;
    }

    private static function iniMb(string $key): int
    {
        $v = trim((string) ini_get($key));
        if ($v === '' || $v === '0' || $v === '-1') {
            return 0;
        }
        $n = (float) $v;

        return (int) match (strtolower(substr($v, -1))) {
            'g' => $n * 1024,
            'm' => $n,
            'k' => $n / 1024,
            default => $n / 1048576,
        };
    }
}
