<?php

namespace Tests\Unit;

use App\Services\AutoPartsCatalogService;
use Tests\TestCase;

class OemBundleEnrichmentConverterTest extends TestCase
{
    public function test_enrichment_payload_from_bundle_empty_without_oem_rows(): void
    {
        $catalog = $this->app->make(AutoPartsCatalogService::class);
        $out = $catalog->enrichmentPayloadFromFullOemBundle(['source_payload' => ['oem_search_rows' => []]]);

        $this->assertSame('none', $out['source']);
        $this->assertSame([], $out['vehicles_normalized']);
    }

    public function test_enrichment_payload_from_minimal_bundle(): void
    {
        $catalog = $this->app->make(AutoPartsCatalogService::class);
        $bundle = [
            'category' => ['main' => 'Тормоза', 'sub' => 'Колодки', 'full' => ''],
            'part' => [
                'article_id' => 99,
                'image_url_primary' => 'https://example.test/img.jpg',
                'image_urls' => ['https://example.test/img.jpg'],
            ],
            'compatibility' => [
                'vehicles' => [
                    [
                        'make' => 'BMW',
                        'model' => 'X5',
                        'body_type' => 'SUV',
                        'year_from' => 2010,
                        'year_to' => 2015,
                        'engine' => '3.0',
                    ],
                ],
            ],
            'analogs_replacement_same_applicability' => [
                [
                    'supplier_name' => 'ATE',
                    'article_no' => '12345',
                ],
            ],
            'source_payload' => [
                'oem_search_rows' => [
                    [
                        'supplierId' => 5,
                        'supplierName' => 'Bosch',
                        'articleNo' => 'ABC',
                        'articleId' => 99,
                        'manufacturerId' => 7,
                    ],
                ],
                'category_raw' => null,
            ],
        ];

        $out = $catalog->enrichmentPayloadFromFullOemBundle($bundle);

        $this->assertSame('oem', $out['source']);
        $this->assertSame('Тормоза', $out['category_main']);
        $this->assertSame('Колодки', $out['category_sub']);
        $this->assertSame('https://example.test/img.jpg', $out['catalog_image_url']);
        $this->assertCount(1, $out['vehicles_normalized']);
        $this->assertSame('BMW', $out['vehicles_normalized'][0]['make']);
        $this->assertCount(1, $out['oem_suppliers']);
        $this->assertCount(1, $out['cross_analogs']);
        $this->assertSame(99, $out['first_article_id']);
        $this->assertSame(7, $out['manufacturer_id']);
    }
}
