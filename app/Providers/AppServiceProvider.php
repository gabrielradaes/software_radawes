<?php

namespace App\Providers;

use App\Models\Branch;
use App\Models\Category;
use App\Models\User;
use App\Support\CurrentBranch;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(CurrentBranch::class);
    }

    public function boot(): void
    {
        Carbon::setLocale(config('app.locale'));

        // En el hosting todo va por https (AutoSSL de cPanel)
        if (config('app.force_https')) {
            \Illuminate\Support\Facades\URL::forceScheme('https');
        }

        // Tienda por defecto para rutas públicas fuera de /{branch} (panel admin, correos…)
        if (! $this->app->runningInConsole()) {
            try {
                $slug = Branch::active()->where('is_main', true)->value('slug') ?? Branch::active()->value('slug');
                if ($slug) {
                    \Illuminate\Support\Facades\URL::defaults(['branch' => $slug]);
                }
            } catch (\Throwable) {
                // sin BD todavía (instalación)
            }
        }

        // Autorización por permisos de rol: @can('stock.view'), middleware can:..., $user->can(...)
        // Copia de seguridad diaria sin cron: la primera visita del día al panel la crea después de responder
        if (! $this->app->runningInConsole()) {
            $this->app->terminating(function () {
                if (request()->is('admin*') && auth('web')->check()) {
                    \App\Services\DatabaseBackup::dailyIfDue();
                }
            });
        }

        Gate::before(function (User $user, string $ability) {
            if (! $user->is_active) {
                return false;
            }
            if ($user->isAdmin()) {
                return true;
            }

            return $user->hasPermission($ability) ? true : null;
        });

        // Datos disponibles en todas las vistas públicas
        View::composer('*', function ($view) {
            static $shared = null;
            if ($shared === null) {
                $shared = [
                    'currentBranch' => app(CurrentBranch::class)->get(),
                    'allBranches' => Branch::active()->orderByDesc('is_main')->orderBy('name')->get(),
                    'rootCategories' => Category::active()->roots()->with('children')->get(),
                ];
            }
            $view->with($shared);
        });
    }
}
