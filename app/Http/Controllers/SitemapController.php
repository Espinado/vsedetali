<?php

namespace App\Http\Controllers;

use App\Models\Page;
use App\Models\Product;
use Illuminate\Http\Response;

class SitemapController extends Controller
{
    public function __invoke(): Response
    {
        $lines = ['<?xml version="1.0" encoding="UTF-8"?>', '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'];

        $add = function (string $loc, ?string $lastmod = null, string $changefreq = 'weekly', string $priority = '0.5') use (&$lines): void {
            $lines[] = '<url>';
            $lines[] = '<loc>'.e($loc).'</loc>';
            if ($lastmod !== null && $lastmod !== '') {
                $lines[] = '<lastmod>'.$lastmod.'</lastmod>';
            }
            $lines[] = '<changefreq>'.$changefreq.'</changefreq>';
            $lines[] = '<priority>'.$priority.'</priority>';
            $lines[] = '</url>';
        };

        $add(url('/'), now()->toAtomString(), 'daily', '1.0');

        Product::query()
            ->active()
            ->orderBy('id')
            ->chunk(1000, function ($products) use ($add): void {
                foreach ($products as $product) {
                    $add(route('product.show', $product), $product->updated_at?->toAtomString(), 'weekly', '0.7');
                }
            });

        Page::query()
            ->active()
            ->orderBy('id')
            ->each(function (Page $page) use ($add): void {
                $add(route('page.show', ['slug' => $page->slug]), $page->updated_at?->toAtomString(), 'monthly', '0.4');
            });

        $lines[] = '</urlset>';

        return response(implode("\n", $lines), 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
        ]);
    }
}
