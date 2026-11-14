<?php

if (! function_exists('whatsapp_link')) {
    /**
     * Enlace wa.me con mensaje prellenado.
     * $phone en formato internacional (59177718360); se limpian espacios, guiones y "+".
     */
    function whatsapp_link(?string $phone, ?string $message = null): string
    {
        $phone = preg_replace('/\D+/', '', (string) $phone);
        $url = 'https://wa.me/'.$phone;

        return $message ? $url.'?text='.rawurlencode($message) : $url;
    }
}

if (! function_exists('video_embed_url')) {
    /**
     * Convierte un enlace de Facebook / YouTube / TikTok en la URL embebible (iframe).
     * Devuelve null si no se reconoce.
     */
    function video_embed_url(?string $url): ?string
    {
        if (! $url) {
            return null;
        }
        $url = trim($url);

        if (preg_match('~(facebook\.com|fb\.watch)~i', $url)) {
            return 'https://www.facebook.com/plugins/video.php?show_text=false&autoplay=false&href='.rawurlencode($url);
        }
        if (preg_match('~(?:youtube\.com/(?:watch\?v=|shorts/|embed/)|youtu\.be/)([A-Za-z0-9_-]{6,})~i', $url, $m)) {
            return 'https://www.youtube.com/embed/'.$m[1];
        }
        if (preg_match('~tiktok\.com/.*/video/(\d+)~i', $url, $m)) {
            return 'https://www.tiktok.com/embed/v2/'.$m[1];
        }

        return null;
    }
}

if (! function_exists('money')) {
    /** Formato de precio boliviano: Bs 350,00 */
    function money(float|int|string|null $amount, string $currency = 'BOB'): string
    {
        $symbol = $currency === 'BOB' ? 'Bs' : $currency;

        return $symbol.' '.number_format((float) $amount, 2, ',', '.');
    }
}
