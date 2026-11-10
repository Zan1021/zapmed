{{--
    Reusable SEO meta partial.
    Pass any of: $metaTitle, $metaDescription, $canonical, $ogImage, $ogType, $noindex.
    Falls back to sensible site-wide defaults when a value is not supplied.
--}}
@php
    $seoTitle       = $metaTitle       ?? ($title ?? config('app.name', 'Zapmed'));
    $seoDescription = $metaDescription ?? 'Doctor-led online treatment for weight loss, sexual health, skincare, and chronic care. Prescribed by licensed SA doctors, delivered to your door.';
    $seoCanonical   = $canonical       ?? url()->current();
    $seoOgImage     = $ogImage         ?? asset('/images/Hero.webp');
    $seoOgType      = $ogType          ?? 'website';
    $seoNoindex     = $noindex         ?? false;
@endphp

<meta name="description" content="{{ $seoDescription }}">
<link rel="canonical" href="{{ $seoCanonical }}">
@if($seoNoindex)
    <meta name="robots" content="noindex, nofollow">
@else
    <meta name="robots" content="index, follow, max-image-preview:large">
@endif

{{-- Open Graph --}}
<meta property="og:site_name" content="Zapmed">
<meta property="og:locale" content="en_ZA">
<meta property="og:type" content="{{ $seoOgType }}">
<meta property="og:title" content="{{ $seoTitle }}">
<meta property="og:description" content="{{ $seoDescription }}">
<meta property="og:url" content="{{ $seoCanonical }}">
<meta property="og:image" content="{{ $seoOgImage }}">

{{-- Twitter --}}
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="{{ $seoTitle }}">
<meta name="twitter:description" content="{{ $seoDescription }}">
<meta name="twitter:image" content="{{ $seoOgImage }}">
