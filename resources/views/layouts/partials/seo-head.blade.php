@php
    use Illuminate\Support\Str;
    $seoTitle = trim($seoTitle ?? '');
    $headTitle = $seoTitle !== ''
        ? $seoTitle
        : trim($__env->yieldContent('title') ?: ($title ?? $storeName)).' — '.$storeName;
    $descRaw = $metaDescription ?? null;
    $description = $descRaw !== null && $descRaw !== ''
        ? Str::limit(trim(preg_replace('/\s+/u', ' ', strip_tags((string) $descRaw))), 320, '')
        : '';
    $canonical = $canonicalUrl ?? url()->current();
    $ogImage = $ogImageUrl ?? null;
@endphp
<title>{{ $headTitle }}</title>
@if($description !== '')
    <meta name="description" content="{{ e($description) }}">
@endif
<link rel="canonical" href="{{ $canonical }}">
<meta property="og:locale" content="{{ str_replace('_', '-', app()->getLocale()) }}">
<meta property="og:site_name" content="{{ e($storeName) }}">
<meta property="og:title" content="{{ e($headTitle) }}">
@if($description !== '')
    <meta property="og:description" content="{{ e($description) }}">
@endif
<meta property="og:url" content="{{ $canonical }}">
<meta property="og:type" content="{{ $ogType ?? 'website' }}">
@if($ogImage)
    <meta property="og:image" content="{{ $ogImage }}">
@endif
<meta name="twitter:card" content="{{ $ogImage ? 'summary_large_image' : 'summary' }}">
<meta name="twitter:title" content="{{ e($headTitle) }}">
@if($description !== '')
    <meta name="twitter:description" content="{{ e($description) }}">
@endif
@if(!empty($noindex))
    <meta name="robots" content="noindex, follow">
@endif
