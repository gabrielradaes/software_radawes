<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * El asistente anterior guardó una frase dictada entera como nombre de color
 * ("Tofi necesito que lo ingreses a la base de datos"). Se deja solo el color.
 */
return new class extends Migration
{
    public function up(): void
    {
        $colorAttr = DB::table('attributes')->where('slug', 'color')->value('id');
        if (! $colorAttr) {
            return;
        }
        foreach (DB::table('attribute_values')->where('attribute_id', $colorAttr)->where('value', 'like', 'Tofi necesito%')->get() as $v) {
            $clash = DB::table('attribute_values')->where('attribute_id', $colorAttr)->where('value', 'Tofi')->exists();
            if (! $clash) {
                DB::table('attribute_values')->where('id', $v->id)->update(['value' => 'Tofi']);
            }
        }
    }

    public function down(): void
    {
        // No hace falta deshacerlo
    }
};
