<?php

namespace App\Http\Middleware;

use App\Models\Branch;
use App\Support\CurrentBranch;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cada tienda vive bajo su propio prefijo (/comercio, /calacoto, /el-alto).
 * Fija la sucursal actual, hace que route() complete {branch} solo y retira el
 * parámetro de la ruta para que los controladores reciban el resto por posición.
 */
class SetBranchFromRoute
{
    public function handle(Request $request, Closure $next): Response
    {
        $slug = $request->route('branch');
        $branch = $slug instanceof Branch ? $slug : Branch::where('slug', $slug)->first();
        abort_if(! $branch || ! $branch->is_active, 404);

        app(CurrentBranch::class)->set($branch);
        URL::defaults(['branch' => $branch->slug]);
        $request->route()->forgetParameter('branch');

        return $next($request);
    }
}
