<?php

namespace App\Pwa;

use Illuminate\Http\Request;

final class PwaProfileResolver
{
    /** @return 'storefront'|'admin'|'seller' */
    public function resolveKey(Request $request): string
    {
        $host = $request->getHost();
        $admin = (string) config('panels.admin.domain');
        $seller = (string) config('panels.seller.domain');

        if ($admin !== '' && $host === $admin) {
            return 'admin';
        }

        if ($seller !== '' && $host === $seller) {
            return 'seller';
        }

        return 'storefront';
    }

    /**
     * @return array<string, mixed>
     */
    public function manifestDocument(Request $request): array
    {
        $key = $this->resolveKey($request);
        $app = config('pwa.apps.'.$key);
        $iconsConfig = config('pwa.icons');

        $icons = [];
        foreach (['192', '512'] as $size) {
            $src = (string) ($iconsConfig[$size] ?? '');
            if ($src === '') {
                continue;
            }
            $icons[] = [
                'src' => url($src),
                'sizes' => $size.'x'.$size,
                'type' => 'image/png',
                'purpose' => 'any maskable',
            ];
        }

        return [
            'id' => (string) $app['manifest_id'],
            'name' => (string) $app['name'],
            'short_name' => (string) $app['short_name'],
            'start_url' => (string) $app['start_url'],
            'scope' => (string) $app['scope'],
            'display' => 'standalone',
            'background_color' => (string) $app['background_color'],
            'theme_color' => (string) $app['theme_color'],
            'icons' => $icons,
            'lang' => 'ru',
            'dir' => 'ltr',
        ];
    }
}
