<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerInteraction extends Model
{
    public const TYPES = ['nota' => '📝 Nota', 'llamada' => '📞 Llamada', 'whatsapp' => '💬 WhatsApp', 'visita' => '🏬 Visita'];

    protected $fillable = ['customer_id', 'user_id', 'type', 'body'];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? '📝 Nota';
    }
}
