<?php

namespace App\Support;

use App\Models\Branch;

/**
 * Resuelve la sucursal "actual" del visitante: la elegida en sesión o la principal.
 * Preparado para cuando haya varias sucursales con su propia página.
 */
class CurrentBranch
{
    protected ?Branch $branch = null;

    public function get(): Branch
    {
        if ($this->branch) {
            return $this->branch;
        }

        $id = session('branch_id');
        $branch = $id ? Branch::active()->find($id) : null;

        return $this->branch = $branch
            ?? Branch::active()->where('is_main', true)->first()
            ?? Branch::active()->firstOrFail();
    }

    public function set(Branch $branch): void
    {
        session(['branch_id' => $branch->id]);
        $this->branch = $branch;
    }
}
