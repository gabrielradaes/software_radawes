<?php

namespace App\Models\Concerns;

use App\Models\Like;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait HasLikes
{
    public function likes(): MorphMany
    {
        return $this->morphMany(Like::class, 'likeable');
    }

    /** ¿Este cliente, usuario del equipo o visitante (cookie) ya dio "me gusta"? */
    public function isLikedBy(?string $visitorToken, ?int $userId = null, ?int $customerId = null): bool
    {
        return $this->likes()
            ->where(function ($q) use ($visitorToken, $userId, $customerId) {
                $q->whereRaw('1 = 0');
                if ($customerId) {
                    $q->orWhere('customer_id', $customerId);
                }
                if ($userId) {
                    $q->orWhere('user_id', $userId);
                }
                if ($visitorToken) {
                    $q->orWhere('visitor_token', $visitorToken);
                }
            })
            ->exists();
    }

    /** Alterna el like y devuelve true si quedó marcado. Prioridad: cliente > equipo > visitante. */
    public function toggleLike(?string $visitorToken, ?int $userId = null, ?int $customerId = null): bool
    {
        $query = $this->likes();
        if ($customerId) {
            $query->where('customer_id', $customerId);
        } elseif ($userId) {
            $query->where('user_id', $userId);
        } else {
            $query->where('visitor_token', $visitorToken);
        }

        if ($existing = $query->first()) {
            $existing->delete();
            return false;
        }

        $this->likes()->create([
            'customer_id' => $customerId,
            'user_id' => $customerId ? null : $userId,
            'visitor_token' => ($customerId || $userId) ? null : $visitorToken,
        ]);

        return true;
    }
}
