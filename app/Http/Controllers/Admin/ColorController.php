<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Support\Palettes;
use App\Support\Theme;
use Illuminate\Http\Request;

/**
 * Paleta de colores de cada tienda, desde el panel.
 * Admin: todas las tiendas. Supervisor: solo la suya. El cliente nunca llega aquí.
 */
class ColorController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $branches = Branch::active()->when($user->scopedBranchId(), fn ($q) => $q->whereKey($user->scopedBranchId()))->orderByDesc('is_main')->get();
        abort_if($branches->isEmpty() || ! $user->can('products.manage'), 403);

        $branch = $branches->firstWhere('id', (int) $request->query('tienda')) ?? $branches->first();
        $current = Palettes::match($branch->color_primary, $branch->color_accent, $branch->color_surface);

        return view('admin.colors.index', [
            'branches' => $branches,
            'branch' => $branch,
            'current' => $current,
            'palettes' => Palettes::LIST,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'branch_id' => ['required', 'exists:branches,id'],
            'paleta' => ['required', 'integer', 'min:1', 'max:'.Palettes::count()],
        ]);
        $branch = Branch::findOrFail($data['branch_id']);
        abort_unless(Theme::canEdit($branch), 403);

        $pal = Palettes::get((int) $data['paleta']);
        $branch->update(['color_primary' => $pal['primary'], 'color_accent' => $pal['accent'], 'color_surface' => $pal['surface']]);

        return redirect()->route('admin.colores.index', ['tienda' => $branch->id])
            ->with('status', "Paleta {$data['paleta']} · {$pal['name']} aplicada a {$branch->name}. Los clientes ya la ven.")
            ->with('status_link', ['url' => route('home', $branch), 'label' => 'Ver la tienda ↗']);
    }
}
