<?php

namespace App\Models\Concerns;

use App\Models\AuditLog;

/**
 * Deja registro (auditoría) cuando se crean, cambian o eliminan registros sensibles.
 * El modelo define: protected array $audited = ['campo' => 'Etiqueta', …]; y opcionalmente auditLabel().
 */
trait Audited
{
    public static function bootAudited(): void
    {
        static::created(function ($m) {
            if (($m->auditCreated ?? true) && auth('web')->check()) {
                AuditLog::record('creado', $m, $m->auditName().' creado: '.$m->auditLabel());
            }
        });

        static::updated(function ($m) {
            $changes = [];
            foreach ($m->audited ?? [] as $field => $label) {
                if ($m->wasChanged($field)) {
                    $changes[$label] = [self::auditValue($m->getOriginal($field)), self::auditValue($m->getAttribute($field))];
                }
            }
            if ($changes) {
                AuditLog::record('actualizado', $m, $m->auditName().' modificado: '.$m->auditLabel(), $changes);
            }
        });

        static::deleted(function ($m) {
            AuditLog::record('eliminado', $m, $m->auditName().' eliminado: '.$m->auditLabel());
        });
    }

    public function auditName(): string
    {
        return [
            'Product' => 'Producto', 'Sale' => 'Venta', 'User' => 'Usuario', 'Branch' => 'Sucursal', 'Category' => 'Clase',
            'Supplier' => 'Proveedor', 'Post' => 'Publicación', 'Purchase' => 'Compra', 'Customer' => 'Cliente',
        ][class_basename($this)] ?? class_basename($this);
    }

    public function auditLabel(): string
    {
        return (string) ($this->name ?? $this->code ?? $this->title ?? '#'.$this->getKey());
    }

    private static function auditValue(mixed $v): mixed
    {
        if ($v instanceof \DateTimeInterface) {
            return $v->format('Y-m-d H:i');
        }
        if (is_bool($v)) {
            return $v ? 'sí' : 'no';
        }

        return $v;
    }
}
