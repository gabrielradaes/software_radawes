<?php

namespace App\Support;

use App\Models\Branch;

/**
 * Genera la paleta Tailwind de una tienda a partir de sus 3 colores (primario, acento, fondo).
 */
class Theme
{
    /** Aclara (amount > 0) u oscurece (amount < 0) un color hex. amount entre -1 y 1. */
    public static function shade(string $hex, float $amount): string
    {
        $hex = ltrim($hex, '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }
        [$r, $g, $b] = array_map('hexdec', str_split($hex, 2));
        $f = fn (int $c) => (int) round($amount >= 0 ? $c + (255 - $c) * $amount : $c * (1 + $amount));

        return sprintf('#%02x%02x%02x', max(0, min(255, $f($r))), max(0, min(255, $f($g))), max(0, min(255, $f($b))));
    }

    /** Paleta por defecto (Cuero clásico) para el panel, el login y la portada del grupo. */
    public static function defaultPalette(): array
    {
        return [
            'brand' => [50 => '#faf6f1', 100 => '#f3e9df', 200 => '#e6d3c1', 300 => '#c9a27e', 500 => '#8a5a3c', 700 => '#5a3a2a', 800 => '#3f2a1f', 900 => '#2b1d17', 950 => '#1a1210'],
            'gold' => [400 => '#d8b561', 500 => '#c8a24a', 600 => '#a98534'],
            'wa' => [500 => '#25D366', 600 => '#1ebe5b'],
        ];
    }

    /** Variables CSS (--brand-500: 107 62 30; …) que usa el Tailwind compilado. */
    public static function cssVars(array $palette): string
    {
        $out = [];
        foreach (['brand', 'gold'] as $group) {
            foreach ($palette[$group] ?? [] as $shade => $hex) {
                $hex = ltrim($hex, '#');
                [$r, $g, $b] = array_map('hexdec', str_split(strlen($hex) === 3 ? $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2] : $hex, 2));
                $out[] = "--{$group}-{$shade}: {$r} {$g} {$b};";
            }
        }

        return implode(' ', $out);
    }

    /** Paleta para tailwind.config: brand.*, gold.* y wa.*  Acepta colores alternativos (vista previa). */
    public static function palette(Branch $branch, ?array $override = null): array
    {
        $p = $override['primary'] ?? ($branch->color_primary ?: '#5a3a2a');
        $a = $override['accent'] ?? ($branch->color_accent ?: '#c8a24a');
        $s = $override['surface'] ?? ($branch->color_surface ?: '#faf6f1');

        return [
            'brand' => [
                50 => $s,
                100 => self::shade($p, 0.9),
                200 => self::shade($p, 0.75),
                300 => self::shade($p, 0.55),
                500 => $p,
                700 => self::shade($p, -0.2),
                800 => self::shade($p, -0.4),
                900 => self::shade($p, -0.55),
                950 => self::shade($p, -0.7),
            ],
            'gold' => [
                400 => self::shade($a, 0.2),
                500 => $a,
                600 => self::shade($a, -0.2),
            ],
            'wa' => [500 => '#25D366', 600 => '#1ebe5b'],
        ];
    }

    /**
     * Colores a usar en esta petición: si un miembro del equipo con permiso pide ?paleta=N
     * se muestra esa combinación como vista previa (sin guardar).
     */
    public static function resolve(Branch $branch): array
    {
        $preview = null;
        $n = (int) request()->query('paleta');
        if ($n && self::canEdit($branch) && ($pal = Palettes::get($n))) {
            $preview = $pal;
        }

        return [
            'palette' => self::palette($branch, $preview),
            'preview' => $n && $preview ? $n : null,
            'current' => Palettes::match($branch->color_primary, $branch->color_accent, $branch->color_surface),
        ];
    }

    /** Admin, o supervisor/empleado con permiso de productos en esta misma tienda. */
    public static function canEdit(Branch $branch): bool
    {
        $user = auth()->user();
        if (! $user) {
            return false;
        }

        return $user->isAdmin() || ($user->branch_id === $branch->id && $user->can('products.manage'));
    }
}
