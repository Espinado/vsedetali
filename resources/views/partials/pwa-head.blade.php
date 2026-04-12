@php
    $pwaResolver = app(\App\Pwa\PwaProfileResolver::class);
    $pwaKey = $pwaResolver->resolveKey(request());
    $pwaApp = config('pwa.apps.'.$pwaKey);
@endphp
<link rel="manifest" href="{{ route('pwa.manifest') }}">
<meta name="theme-color" content="{{ e($pwaApp['theme_color'] ?? '#1c1917') }}">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<meta name="apple-mobile-web-app-title" content="{{ e($pwaApp['short_name'] ?? config('app.name')) }}">
<link rel="apple-touch-icon" href="{{ url((string) config('pwa.icons.192')) }}">
