<?php

namespace Tests\Unit;

use App\Support\CatalogStorefrontCategoryConflictDetector;
use PHPUnit\Framework\TestCase;

class CatalogStorefrontCategoryConflictDetectorTest extends TestCase
{
    public function test_detects_headlight_bracket_name_with_brake_category(): void
    {
        $reason = CatalogStorefrontCategoryConflictDetector::detect(
            'Кронштейн фары передний левый 501001574AADYJ Exeed Lx',
            'Тормозная система',
            'Тормозная колодка / накладка',
        );

        $this->assertSame('lighting_name_brake_category', $reason);
    }

    public function test_detect_for_assigned_category_path(): void
    {
        $reason = CatalogStorefrontCategoryConflictDetector::detectForAssignedCategory(
            'Колодки тормозные передние',
            'Освещение / Фара',
        );

        $this->assertSame('brake_name_lighting_category', $reason);
    }

    public function test_no_conflict_for_unrelated_strings(): void
    {
        $this->assertNull(CatalogStorefrontCategoryConflictDetector::detect(
            'Масляный фильтр',
            'Система смазки',
            'Фильтр масляный',
        ));
    }
}
