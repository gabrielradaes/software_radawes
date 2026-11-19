<?php

namespace App\Support;

use Closure;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

/**
 * Subida de imágenes SIN depender de la extensión "fileinfo" de PHP (el hosting no la tiene).
 * El tipo se detecta leyendo el contenido real del archivo con getimagesize(), que viene
 * incluido en PHP, así que solo se aceptan imágenes de verdad (JPG, PNG, WebP, GIF).
 */
class ImageUpload
{
    private const TYPES = [
        IMAGETYPE_JPEG => 'jpg',
        IMAGETYPE_PNG => 'png',
        IMAGETYPE_WEBP => 'webp',
        IMAGETYPE_GIF => 'gif',
    ];

    /** Regla de validación: usar en lugar de 'image'. $maxKb = tamaño máximo en KB. */
    public static function rule(int $maxKb = 6144): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($maxKb) {
            if (! $value instanceof UploadedFile || ! $value->isValid()) {
                $fail('No se pudo subir la foto. Intenta de nuevo (quizás es muy pesada).');

                return;
            }
            if ($value->getSize() > $maxKb * 1024) {
                $fail('Cada foto puede pesar como máximo '.round($maxKb / 1024, 1).' MB.');

                return;
            }
            if (! self::extension($value)) {
                $fail('«'.Str::limit($value->getClientOriginalName(), 40).'» no es una foto válida. Usa JPG, PNG o WebP.');
            }
        };
    }

    /** Extensión según el contenido real (null si no es una imagen admitida). */
    public static function extension(UploadedFile $file): ?string
    {
        $info = @getimagesize($file->getRealPath());

        return $info ? (self::TYPES[$info[2]] ?? null) : null;
    }

    /**
     * Guarda en public_html/uploads/<carpeta> y devuelve la ruta pública relativa.
     * Se mueve el archivo directamente (sin el disco de Laravel/Flysystem, que también exige fileinfo).
     */
    public static function store(UploadedFile $file, string $folder): string
    {
        $name = Str::random(40).'.'.(self::extension($file) ?? 'jpg');
        $dir = public_path('uploads/'.$folder);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $file->move($dir, $name);
        self::optimize($dir.'/'.$name);

        return 'uploads/'.$folder.'/'.$name;
    }

    public const MAX_SIDE = 1600;

    /**
     * Achica fotos grandes (las de celular pesan 3–8 MB) a 1600 px y las recomprime: la página carga
     * mucho más rápido con datos móviles. Respeta la orientación de la foto (EXIF) sin necesitar la
     * extensión exif. Si algo falla, deja la foto original.
     */
    public static function optimize(string $path): void
    {
        try {
            $info = @getimagesize($path);
            if (! $info || ! function_exists('imagecreatetruecolor')) {
                return;
            }
            [$w, $h, $type] = $info;
            $orientation = $type === IMAGETYPE_JPEG ? self::jpegOrientation($path) : 1;
            if (max($w, $h) <= self::MAX_SIDE && filesize($path) <= 700 * 1024 && $orientation === 1) {
                return;
            }
            $src = match ($type) {
                IMAGETYPE_JPEG => @imagecreatefromjpeg($path),
                IMAGETYPE_PNG => @imagecreatefrompng($path),
                IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : false,
                default => false,
            };
            if (! $src) {
                return;
            }
            $src = match ($orientation) {
                3 => imagerotate($src, 180, 0),
                6 => imagerotate($src, -90, 0),
                8 => imagerotate($src, 90, 0),
                default => $src,
            };
            [$w, $h] = [imagesx($src), imagesy($src)];
            $scale = min(1, self::MAX_SIDE / max($w, $h));
            [$nw, $nh] = [max(1, (int) round($w * $scale)), max(1, (int) round($h * $scale))];
            $dst = imagecreatetruecolor($nw, $nh);
            if ($type !== IMAGETYPE_JPEG) {
                imagealphablending($dst, false);
                imagesavealpha($dst, true);
            }
            imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);

            $tmp = $path.'.tmp';
            $ok = match ($type) {
                IMAGETYPE_JPEG => imagejpeg($dst, $tmp, 82),
                IMAGETYPE_PNG => imagepng($dst, $tmp, 8),
                IMAGETYPE_WEBP => imagewebp($dst, $tmp, 80),
            };
            imagedestroy($src);
            imagedestroy($dst);
            // Reemplazar si se achicó, si había que corregir la orientación o si quedó más liviana
            if ($ok && is_file($tmp) && ($scale < 1 || $orientation !== 1 || filesize($tmp) < filesize($path))) {
                @chmod($path, 0644);
                // En Windows rename() no siempre puede reemplazar un archivo existente: copiar encima
                if (! @rename($tmp, $path)) {
                    copy($tmp, $path);
                    @unlink($tmp);
                }
            } else {
                @unlink($tmp);
            }
        } catch (\Throwable) {
            @unlink($path.'.tmp');
        }
    }

    /** Lee la etiqueta Orientation (0x0112) del EXIF de un JPEG. 1 = normal. */
    private static function jpegOrientation(string $path): int
    {
        if (function_exists('exif_read_data')) {
            $exif = @exif_read_data($path);

            return (int) ($exif['Orientation'] ?? 1) ?: 1;
        }
        $data = @file_get_contents($path, false, null, 0, 65536);
        $pos = $data ? strpos($data, "Exif\0\0") : false;
        if ($pos === false) {
            return 1;
        }
        $tiff = substr($data, $pos + 6);
        $le = substr($tiff, 0, 2) === 'II';
        $u16 = fn ($o) => unpack($le ? 'v' : 'n', substr($tiff, $o, 2))[1] ?? 0;
        $u32 = fn ($o) => unpack($le ? 'V' : 'N', substr($tiff, $o, 4))[1] ?? 0;
        $ifd = $u32(4);
        $count = $u16($ifd);
        for ($i = 0; $i < $count && $i < 64; $i++) {
            $entry = $ifd + 2 + $i * 12;
            if ($u16($entry) === 0x0112) {
                $v = $u16($entry + 8);

                return in_array($v, [1, 3, 6, 8], true) ? $v : 1;
            }
        }

        return 1;
    }

    /** Borra una foto subida (solo dentro de public_html/uploads). */
    public static function delete(?string $path): void
    {
        if ($path && str_starts_with($path, 'uploads/') && ! str_contains($path, '..')) {
            @unlink(public_path($path));
        }
    }
}
