<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PostMedia extends Model
{
    protected $table = 'post_media';

    protected $fillable = ['post_id', 'type', 'path', 'thumbnail_path', 'sort_order'];

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    public function url(): string
    {
        return str_starts_with($this->path, 'http') ? $this->path : asset($this->path);
    }

    /** Video subido a la página (no un enlace de Facebook/TikTok/YouTube). */
    public function isLocalVideo(): bool
    {
        return $this->type === 'video' && str_starts_with($this->path, 'uploads/');
    }

    public function posterUrl(): ?string
    {
        return $this->thumbnail_path ? asset($this->thumbnail_path) : null;
    }

    /** Imagen para vistas previas (redes, buscador): la foto o la portada del video. */
    public function previewUrl(): ?string
    {
        if ($this->type === 'video') {
            return $this->posterUrl();
        }

        return $this->url();
    }
}
