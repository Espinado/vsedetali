<?php

namespace Tests\Unit;

use App\Services\AutoPartsCatalogService;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class AutoPartsCatalogVinCategoryPathTest extends TestCase
{
    public function test_list_vin_product_group_children_respects_path_and_has_children(): void
    {
        Cache::flush();
        $token = 'unit_test_pg_token_'.bin2hex(random_bytes(8));
        $rows = [
            [
                'level' => 2,
                'categoryName1' => 'A',
                'categoryId1' => 1,
                'categoryName2' => 'B',
                'categoryId2' => 2,
                'categoryName3' => '',
                'categoryId3' => null,
            ],
            [
                'level' => 2,
                'categoryName1' => 'A',
                'categoryId1' => 1,
                'categoryName2' => 'C',
                'categoryId2' => 3,
                'categoryName3' => '',
                'categoryId3' => null,
            ],
            [
                'level' => 3,
                'categoryName1' => 'A',
                'categoryId1' => 1,
                'categoryName2' => 'B',
                'categoryId2' => 2,
                'categoryName3' => 'D',
                'categoryId3' => 4,
            ],
        ];
        Cache::put('auto_parts_catalog.pg_rows.'.$token, [
            'vehicle_id' => 99,
            'manufacturer_id' => 5,
            'rows' => $rows,
        ], 60);

        /** @var AutoPartsCatalogService $svc */
        $svc = $this->app->make(AutoPartsCatalogService::class);

        $roots = $svc->listVinProductGroupChildren($token, 99, 5, []);
        $this->assertSame('', $roots['error']);
        $this->assertCount(1, $roots['nodes']);
        $this->assertSame('A', $roots['nodes'][0]['name']);
        $this->assertTrue($roots['nodes'][0]['has_children']);

        $lvl2 = $svc->listVinProductGroupChildren($token, 99, 5, [1]);
        $this->assertCount(2, $lvl2['nodes']);
        $this->assertTrue(collect($lvl2['nodes'])->contains(fn (array $n): bool => $n['id'] === 2 && $n['has_children']));
        $this->assertTrue(collect($lvl2['nodes'])->contains(fn (array $n): bool => $n['id'] === 3 && ! $n['has_children']));

        $lvl3 = $svc->listVinProductGroupChildren($token, 99, 5, [1, 2]);
        $this->assertCount(1, $lvl3['nodes']);
        $this->assertSame('D', $lvl3['nodes'][0]['name']);
        $this->assertFalse($lvl3['nodes'][0]['has_children']);

        $aid = $svc->resolveVinProductGroupLeafArticleId($token, 99, 5, [1, 2, 4]);
        $this->assertSame(4, $aid);

        $bad = $svc->listVinProductGroupChildren($token, 100, 5, []);
        $this->assertNotSame('', $bad['error']);
    }
}
