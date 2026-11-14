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
