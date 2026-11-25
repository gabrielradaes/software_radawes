<?php

namespace App\Http\Middleware;

use App\Models\AuditLog;
use App\Models\Branch;
use App\Support\ActivityLabels;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Anota en el historial cada acción que alguien hace (todo lo que no sea solo mirar):
 * vender, publicar, editar, apartar, comentar, iniciar sesión… del equipo y de los clientes.
 * Solo anota lo que salió bien; si faltaban datos (error de validación) no se anota.
 */
class RecordActivity
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethodSafe()) {
            return $next($request);
        }

        // Quién era antes (para "cerró sesión", que ya no está logueado después)
        $userBefore = auth('web')->user();
        $customerBefore = auth('customer')->user();

        $response = $next($request);

        try {
            $this->record($request, $response, $userBefore, $customerBefore);
        } catch (\Throwable $e) {
            report($e);   // el historial nunca rompe la acción
        }

        return $response;
    }

    protected function record(Request $request, Response $response, $userBefore, $customerBefore): void
    {
        $route = $request->route();
        if (! $route) {
            return;
        }
        $name = $route->getName();
        $name = $name && ! str_ends_with($name, '.') ? $name : null;
        $uri = $route->uri();
        if ($name && in_array($name, ActivityLabels::SKIP, true)) {
            return;
        }

        $failed = $response->getStatusCode() >= 400
            || ($request->hasSession() && in_array('errors', $request->session()->get('_flash.new', []), true));

        $user = auth('web')->user() ?? $userBefore;
        $customer = $user ? null : (auth('customer')->user() ?? $customerBefore);

        // Intento de ingreso fallido al panel: se anota (seguridad), sin la contraseña
        if ($failed) {
            if ($uri === 'admin/login' && $response->getStatusCode() < 500) {
                AuditLog::create([
                    'action' => 'ingreso_fallido',
                    'description' => mb_substr('Intento fallido de ingreso al panel con «'.(string) $request->input('email').'»', 0, 255),
                    'ip' => $request->ip(),
                    'batch' => AuditLog::batchFor($request),
                ]);
            }

            return;
        }

        $label = ActivityLabels::for($name, $uri) ?? 'Hizo una acción ('.($name ?? $uri).')';

        // Sobre qué: el primer registro de la dirección que no sea la tienda (venta, publicación…)
        $subject = null;
        $branch = null;
        foreach ($route->parameters() as $param) {
            if ($param instanceof Branch && ! $branch) {
                $branch = $param;
            } elseif ($param instanceof Model && ! $subject) {
                $subject = $param;
            }
        }

        // Páginas públicas: la tienda la resuelve el middleware de la tienda (no queda como parámetro)
        if (! $branch && ! str_starts_with($uri, 'admin')) {
            $current = app(\App\Support\CurrentBranch::class)->get();
            $branch = $current instanceof Branch ? $current : null;
        }

        // Detalle: el mensaje de éxito que vio la persona ("Venta V-000123 registrada…")
        $detail = $request->hasSession() && in_array('status', $request->session()->get('_flash.new', []), true)
            ? (string) $request->session()->get('status', '') : '';
        if ($detail === '' && $subject && method_exists($subject, 'auditLabel')) {
            $detail = $subject->auditLabel();
        } elseif ($detail === '' && $subject) {
            $detail = (string) ($subject->getAttribute('name') ?? $subject->getAttribute('title') ?? $subject->getAttribute('code') ?? '');
        }
        if ($name === 'like.toggle' || $name === 'comment.store') {
            $detail = $request->route('type') === 'product' ? 'en un producto' : 'en una publicación';
            $detail .= $name === 'comment.store' && $request->filled('body') ? ': «'.mb_substr((string) $request->input('body'), 0, 80).'»' : '';
        }

        $description = trim($label.($detail !== '' && $detail !== $label ? ' · '.trim($detail, " ✓.") : ''));

        AuditLog::create([
            'user_id' => $user?->id,
            'customer_id' => $customer?->id,
            'branch_id' => $user?->branch_id ?? $branch?->id ?? $subject?->getAttribute('branch_id'),
            'action' => 'accion',
            'subject_type' => $subject ? $subject::class : null,
            'subject_id' => $subject?->getKey(),
            'description' => mb_substr($description, 0, 255),
            'ip' => $request->ip(),
            'batch' => AuditLog::batchFor($request),
        ]);
    }
}
