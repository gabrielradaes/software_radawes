<?php

namespace App\Http\Middleware;

use App\Models\Branch;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Si el visitante entra por el dominio de una marca (radawes.localhost, radawes.com.bo…)
 * lo lleva directo a esa tienda, sin pasar por la portada de "elige tu tienda".
 */
class ResolveBranchDomain
{
    public function handle(Request $request, Closure $next): Response
    {
        // Tienda por defecto para los enlaces fuera de /{tienda} (panel, correos…). Se guarda 1 hora en caché.
        if (empty(\Illuminate\Support\Facades\URL::getDefaultParameters()['branch'])) {
            $slug = \Illuminate\Support\Facades\Cache::remember('branch:default-slug', 3600,
                fn () => Branch::active()->where('is_main', true)->value('slug') ?? Branch::active()->value('slug'));
            if ($slug) {
                \Illuminate\Support\Facades\URL::defaults(['branch' => $slug]);
            }
        }

        if ($request->is('/') || $request->is('sucursales')) {
            $host = strtolower($request->getHost());
            $branch = Branch::active()->where('domain', $host)->first()
                ?? Branch::active()->where('domain', preg_replace('/^www\./', '', $host))->first();

            if ($branch) {
                return redirect()->route('home', $branch);
            }
        }

        return $next($request);
    }
}
