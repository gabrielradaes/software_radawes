<?php

namespace App\Http\Controllers;

use App\Support\CurrentBranch;
use App\Support\Palettes;
use App\Support\Theme;
use Illuminate\Http\Request;

/** Guarda la paleta elegida para la tienda actual (solo admin o supervisor de esa tienda). */
class ThemeController extends Controller
{
    public function save(Request $request, CurrentBranch $current)
    {
        $branch = $current->get();
        abort_unless(Theme::canEdit($branch), 403);

        $data = $request->validate(['paleta' => ['required', 'integer', 'min:1', 'max:'.Palettes::count()]]);
        $pal = Palettes::get((int) $data['paleta']);

        $branch->update([
            'color_primary' => $pal['primary'],
            'color_accent' => $pal['accent'],
            'color_surface' => $pal['surface'],
        ]);

        return redirect()->to(url()->previous() ? preg_replace('/([?&])paleta=\d+&?/', '$1', url()->previous()) : route('home'))
            ->with('status', "Paleta {$data['paleta']} · {$pal['name']} guardada para {$branch->brandName()}. Los clientes ya la ven.");
    }
}
