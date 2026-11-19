<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Cliente que pidió aviso cuando llegue su talla/color. */
class RestockRequest extends Model
{
    protected $fillable = ['customer_id', 'product_id', 'branch_id', 'size', 'color', 'notified_at'];

    protected $casts = ['notified_at' => 'datetime'];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function label(): string
    {
        return trim($this->product->name.($this->size ? " talla {$this->size}" : '').($this->color ? " {$this->color}" : ''));
    }

    /** Llegó stock de una variante: crea el seguimiento para avisar a quienes la esperaban. */
    public static function stockArrived(Stock $stock): void
    {
        if ($stock->available() <= 0) {
            return;
        }
        $variant = $stock->variant()->with('product', 'attributeValues.attribute')->first();
        if (! $variant) {
            return;
        }
        $size = $variant->valueOf('talla');
        $color = $variant->valueOf('color');

        $pending = static::with('customer')->where('product_id', $variant->product_id)->where('branch_id', $stock->branch_id)->whereNull('notified_at')
            ->where(fn ($q) => $q->whereNull('size')->orWhere('size', $size))
            ->where(fn ($q) => $q->whereNull('color')->orWhere('color', $color))
            ->get();
        if ($pending->isEmpty()) {
            return;
        }

        // Responsable: el supervisor de la tienda (o un administrador)
        $owner = User::where('is_active', true)->where('branch_id', $stock->branch_id)->whereHas('role', fn ($r) => $r->where('name', Role::SUPERVISOR))->first()
            ?? User::where('is_active', true)->whereHas('role', fn ($r) => $r->where('name', Role::ADMIN))->first();
        if (! $owner) {
            return;
        }
        foreach ($pending as $r) {
            CustomerTask::create([
                'customer_id' => $r->customer_id, 'user_id' => $owner->id, 'branch_id' => $stock->branch_id,
                'title' => "Avisar que llegó {$r->label()} (lo pidió en la página)", 'due_at' => now(),
            ]);
            $r->update(['notified_at' => now()]);
        }
    }
}
