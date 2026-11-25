<?php

if (! function_exists('whatsapp_link')) {
    /**
     * Enlace wa.me con mensaje prellenado.
     * $phone en formato internacional (59177718360); se limpian espacios, guiones y "+".
     */
    function whatsapp_link(?string $phone, ?string $message = null): string
    {
        $phone = preg_replace('/\D+/', '', (string) $phone);
        // Celular boliviano guardado sin código de país (70011222) → 59170011222
        if (preg_match('/^[67]\d{7}$/', $phone)) {
            $phone = '591'.$phone;
        }
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

        // Fotos y publicaciones de Facebook: se muestran con el complemento de publicaciones
        if (preg_match('~facebook\.com/(photo|.*/photos/|.*/posts/|permalink\.php)|[?&]fbid=~i', $url) && ! preg_match('~/(reel|videos)/~i', $url)) {
            return 'https://www.facebook.com/plugins/post.php?show_text=false&width=500&href='.rawurlencode($url);
        }
        if (preg_match('~(facebook\.com|fb\.watch)~i', $url)) {
            return 'https://www.facebook.com/plugins/video.php?show_text=false&autoplay=false&href='.rawurlencode($url);
        }
        if (preg_match('~(?:youtube\.com/(?:watch\?v=|shorts/|embed/)|youtu\.be/)([A-Za-z0-9_-]{6,})~i', $url, $m)) {
            return 'https://www.youtube.com/embed/'.$m[1];
        }
        if (preg_match('~tiktok\.com/.*/(?:video|photo)/(\d+)~i', $url, $m)) {
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

if (! function_exists('ensure_can_delete')) {
    /** Eliminar es solo del administrador: el supervisor y el vendedor hacen todo lo demás. */
    function ensure_can_delete(): void
    {
        abort_unless(auth()->user()?->can('records.delete'), 403, 'Solo el administrador puede eliminar. Pídeselo o, si es una publicación, desactívala.');
    }
}

if (! function_exists('upload_aware')) {
    /**
     * Formularios que suben fotos/videos con barra de progreso (media-uploader.js): en vez de redirigir,
     * responden {redirect: url} y el navegador va allí (el mensaje de éxito queda guardado para esa página).
     */
    function upload_aware(\Illuminate\Http\RedirectResponse $redirect): \Symfony\Component\HttpFoundation\Response
    {
        return request()->header('X-RyE-Upload') ? response()->json(['redirect' => $redirect->getTargetUrl()]) : $redirect;
    }
}
