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

        return 'uploads/'.$folder.'/'.$name;
    }

    /** Borra una foto subida (solo dentro de public_html/uploads). */
    public static function delete(?string $path): void
    {
        if ($path && str_starts_with($path, 'uploads/') && ! str_contains($path, '..')) {
            @unlink(public_path($path));
        }
    }
}
