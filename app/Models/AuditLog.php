<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Str;

/**
 * Historial de actividad: qué hizo cada persona (equipo o cliente), cuándo y en qué tienda.
 * - action = 'accion' → lo que la persona hizo (lo anota el middleware RecordActivity)
 * - action = creado/actualizado/eliminado → detalle de los datos que cambiaron (trait Audited)
 * Todo lo de un mismo pedido comparte "batch" para mostrarse junto.
 */
class AuditLog extends Model
{
    public const UPDATED_AT = null;

    public const KEEP_MONTHS = 18;

    protected $fillable = ['user_id', 'customer_id', 'branch_id', 'action', 'subject_type', 'subject_id', 'description', 'changes', 'ip', 'batch'];

    protected $casts = ['changes' => 'array', 'created_at' => 'datetime'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public static function record(string $action, ?Model $subject, string $description, ?array $changes = null, array $extra = []): ?self
    {
        try {
            $request = app()->runningInConsole() && ! app()->runningUnitTests() ? null : request();
            $user = auth('web')->user();

            return static::create([
                'user_id' => $user?->id,
                'customer_id' => $user ? null : auth('customer')->id(),
                'branch_id' => $user?->branch_id ?? ($subject?->getAttribute('branch_id')),
                'action' => $action,
                'subject_type' => $subject ? $subject::class : null,
                'subject_id' => $subject?->getKey(),
                'description' => mb_substr($description, 0, 255),
                'changes' => $changes ?: null,
                'ip' => $request?->ip(),
                'batch' => $request ? static::batchFor($request) : null,
                ...$extra,
            ]);
        } catch (\Throwable $e) {
            // El historial nunca debe impedir que se guarde una venta o una publicación
            report($e);

            return null;
        }
    }

    /** Identificador del pedido actual: agrupa la acción con los cambios que produjo. */
    public static function batchFor(\Illuminate\Http\Request $request): string
    {
        if (! $request->attributes->has('activity_batch')) {
            $request->attributes->set('activity_batch', (string) Str::uuid());
        }

        return $request->attributes->get('activity_batch');
    }

    /** Lo que un usuario del equipo puede ver: admin todo; supervisor su tienda (sin lo del admin); el resto, lo suyo. */
    public function scopeVisibleTo(Builder $q, User $viewer): Builder
    {
        if ($viewer->isAdmin()) {
            return $q;
        }
        if ($viewer->can('activity.view') && $viewer->branch_id) {
            $adminIds = User::whereHas('role', fn ($r) => $r->where('name', Role::ADMIN))->pluck('id');

            return $q->where('branch_id', $viewer->branch_id)
                ->where(fn ($w) => $w->whereNull('user_id')->orWhereNotIn('user_id', $adminIds));
        }

        return $q->where('user_id', $viewer->id);
    }

    /** Borra lo muy antiguo una vez al día (sin cron). */
    public static function pruneIfDue(): void
    {
        $key = 'activity:pruned:'.now()->toDateString();
        if (cache()->add($key, 1, now()->addDay())) {
            static::where('created_at', '<', now()->subMonths(self::KEEP_MONTHS))->limit(5000)->delete();
        }
    }

    public function who(): string
    {
        if ($this->user) {
            return $this->user->name;
        }
        if ($this->customer) {
            return $this->customer->name.' (cliente)';
        }

        return $this->user_id || $this->customer_id ? '(borrado)' : 'Visitante';
    }

    public function icon(): string
    {
        $d = mb_strtolower($this->description);

        return match (true) {
            $this->action === 'ingreso_fallido' => '⛔',
            $this->action === 'eliminado' || str_contains($d, 'elimin') => '🗑️',
            str_contains($d, 'venta') || str_contains($d, 'cobr') => '🧾',
            str_contains($d, 'publica') => '📣',
            str_contains($d, 'sesión') => '🔑',
            str_contains($d, 'stock') || str_contains($d, 'traspaso') || str_contains($d, 'inventario') || str_contains($d, 'compra') => '📦',
            str_contains($d, 'caja') => '💵',
            str_contains($d, 'apart') || str_contains($d, 'separ') => '📌',
            str_contains($d, 'coment') => '💬',
            str_contains($d, 'me gusta') => '❤️',
            str_contains($d, 'client') => '🧑',
            str_contains($d, 'producto') || str_contains($d, 'variante') => '👞',
            default => '•',
        };
    }
}
