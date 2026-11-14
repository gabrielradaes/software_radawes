<?php

namespace App\Support;

/**
 * Combinaciones de colores probadas para una tienda de calzado:
 * primario (marca, botones), acento (detalles, ofertas) y superficie (fondo claro).
 */
class Palettes
{
    public const LIST = [
        1 => ['name' => 'Cuero clásico', 'primary' => '#6b3e1e', 'accent' => '#c8a24a', 'surface' => '#faf6f1'],
        2 => ['name' => 'Marrón, rojo y blanco', 'primary' => '#6b3e1e', 'accent' => '#b3121b', 'surface' => '#ffffff'],
        3 => ['name' => 'Verde Brasil', 'primary' => '#0f5132', 'accent' => '#f2c94c', 'surface' => '#f6f9f4'],
        4 => ['name' => 'Azul marino y camel', 'primary' => '#1b2a4a', 'accent' => '#c19a6b', 'surface' => '#f7f8fb'],
        5 => ['name' => 'Carbón y oro', 'primary' => '#2b2b2b', 'accent' => '#d4af37', 'surface' => '#fafafa'],
        6 => ['name' => 'Burdeos y crema', 'primary' => '#5c1a24', 'accent' => '#e3c598', 'surface' => '#fbf7f2'],
        7 => ['name' => 'Oliva y arena', 'primary' => '#4b5320', 'accent' => '#d9b382', 'surface' => '#f8f7f2'],
        8 => ['name' => 'Negro y blanco', 'primary' => '#111111', 'accent' => '#8a8a8a', 'surface' => '#ffffff'],
        9 => ['name' => 'Azul petróleo y cobre', 'primary' => '#0f4c5c', 'accent' => '#b87333', 'surface' => '#f4f8f9'],
        10 => ['name' => 'Pizarra y coral', 'primary' => '#3d4a5c', 'accent' => '#e07a5f', 'surface' => '#f8f9fa'],
        11 => ['name' => 'Chocolate y menta', 'primary' => '#4a2c2a', 'accent' => '#7fb3a3', 'surface' => '#f9f6f3'],
        12 => ['name' => 'Marino y rojo', 'primary' => '#14213d', 'accent' => '#c1121f', 'surface' => '#ffffff'],
        13 => ['name' => 'Café y celeste', 'primary' => '#7a4b2a', 'accent' => '#4f9fd1', 'surface' => '#f7fafc'],
        14 => ['name' => 'Verde bosque y marfil', 'primary' => '#1e4d2b', 'accent' => '#c9a86a', 'surface' => '#fbfaf5'],
        15 => ['name' => 'Terracota y crema', 'primary' => '#a0522d', 'accent' => '#e9a86b', 'surface' => '#fff8f0'],
        16 => ['name' => 'Vino y dorado', 'primary' => '#722f37', 'accent' => '#d4a373', 'surface' => '#fcf8f5'],
        17 => ['name' => 'Índigo y mostaza', 'primary' => '#2c3e7a', 'accent' => '#e1ad01', 'surface' => '#f6f7fb'],
        18 => ['name' => 'Taupe y negro', 'primary' => '#5c5247', 'accent' => '#1f1f1f', 'surface' => '#faf8f5'],
    ];

    public static function get(int $n): ?array
    {
        return self::LIST[$n] ?? null;
    }

    public static function count(): int
    {
        return count(self::LIST);
    }

    /** Número de la paleta que coincide con los colores de la tienda (o null si es personalizada). */
    public static function match(string $primary, string $accent, string $surface): ?int
    {
        foreach (self::LIST as $n => $p) {
            if (strcasecmp($p['primary'], $primary) === 0 && strcasecmp($p['accent'], $accent) === 0 && strcasecmp($p['surface'], $surface) === 0) {
                return $n;
            }
        }

        return null;
    }
}
