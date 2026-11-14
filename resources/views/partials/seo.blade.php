{{--
  Etiquetas para Google y redes sociales.
  Variables: $seoTitle, $seoDescription, $seoImage (url), $seoType (website|product|article), $branch (opcional)
--}}
@php
    $seoUrl = url()->current();
    $seoImage = $seoImage ?? asset('images/brands/radawes.png');
    $verification = config('services.google.site_verification');
@endphp
<link rel="canonical" href="{{ $seoUrl }}">
@if($verification)<meta name="google-site-verification" content="{{ $verification }}">@endif
<meta property="og:site_name" content="RadaWes">
<meta property="og:locale" content="es_BO">
<meta property="og:type" content="{{ $seoType ?? 'website' }}">
<meta property="og:title" content="{{ $seoTitle }}">
<meta property="og:description" content="{{ $seoDescription }}">
<meta property="og:url" content="{{ $seoUrl }}">
<meta property="og:image" content="{{ $seoImage }}">
<meta name="twitter:card" content="summary_large_image">
@if(! empty($branch))
<script type="application/ld+json">
{!! json_encode([
    '@context' => 'https://schema.org',
    '@type' => 'ShoeStore',
    'name' => $branch->name,
    'alternateName' => ['RadaWes', $branch->brandName()],
    'description' => $branch->description ?: $branch->tagline,
    'url' => route('home', $branch),
    'logo' => $branch->logoUrl() ?? asset('images/brands/radawes.png'),
    'image' => $branch->logoUrl() ?? asset('images/brands/radawes.png'),
    'telephone' => $branch->whatsapp ? '+'.$branch->whatsapp : null,
    'address' => array_filter([
        '@type' => 'PostalAddress',
        'streetAddress' => $branch->address,
        'addressLocality' => $branch->city,
        'addressCountry' => 'BO',
    ]),
    'geo' => $branch->latitude ? ['@type' => 'GeoCoordinates', 'latitude' => $branch->latitude, 'longitude' => $branch->longitude] : null,
    'openingHours' => $branch->opening_hours,
    'sameAs' => $branch->socialLinks->pluck('url')->values()->all(),
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) !!}
</script>
@endif
