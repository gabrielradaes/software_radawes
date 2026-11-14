<?php

use App\Http\Middleware\EnsureVisitorToken;
use App\Models\Branch;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [EnsureVisitorToken::class, \App\Http\Middleware\ResolveBranchDomain::class]);
        $middleware->encryptCookies(except: [EnsureVisitorToken::COOKIE, 'rye_name']);
        // La puerta de actualización se protege con su propia clave (no con sesión/CSRF)
        $middleware->validateCsrfTokens(except: ['_deploy', '_deploy/*']);

        // Tienda de la URL actual (o la principal) para armar rutas públicas
        $storeSlug = function (Request $r): string {
            $b = $r->route('branch') ?? app(\App\Support\CurrentBranch::class)->get();

            return $b instanceof Branch ? $b->slug : (string) $b;
        };

        // Equipo => /admin/login · Clientes => /{tienda}/ingresar
        $middleware->redirectGuestsTo(fn (Request $r) => $r->is('admin*') ? route('admin.login') : route('customer.login', ['branch' => $storeSlug($r)]));
        $middleware->redirectUsersTo(fn (Request $r) => $r->is('admin*') ? route('admin.dashboard') : route('customer.account', ['branch' => $storeSlug($r)]));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
