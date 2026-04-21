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

    public function test_detects_hub_name_with_paints_category(): void
    {
        $this->assertSame(
            'hub_name_alien_category',
            CatalogStorefrontCategoryConflictDetector::detect(
                'Ступица передняя в сборе 3103100XGW02A',
                'Краски, лаки',
                '',
            ),
        );
    }

    public function test_detects_hub_name_with_taillight_category_for_assigned(): void
    {
        $this->assertSame(
            'hub_name_alien_category',
            CatalogStorefrontCategoryConflictDetector::detectForAssignedCategory(
                'Подшипник ступицы заднего колеса',
                'Освещение / Задний фонарь',
            ),
        );
    }

    public function test_no_conflict_when_hub_in_hub_category(): void
    {
        $this->assertNull(CatalogStorefrontCategoryConflictDetector::detect(
            'Ступица переднего колеса',
            'Подвеска',
            'Подшипник ступицы колеса',
        ));
    }

    public function test_detects_bumper_name_with_cylinder_category(): void
    {
        $this->assertSame(
            'bumper_name_alien_category',
            CatalogStorefrontCategoryConflictDetector::detect(
                'Бампер передний 5N0807221',
                'Тормозная система',
                'Рабочий цилиндр',
            ),
        );
    }

    public function test_no_conflict_for_bumper_reinforcement_in_brackets(): void
    {
        // Усилитель бампера — отдельная история, его попадание в "Кронштейн" не считаем мусором.
        $this->assertNull(CatalogStorefrontCategoryConflictDetector::detect(
            'Усилитель бампера переднего',
            'Кузов',
            'Кронштейн бампера',
        ));
    }

    public function test_detects_body_glass_with_brake_cylinder_category(): void
    {
        $this->assertSame(
            'body_glass_name_alien_category',
            CatalogStorefrontCategoryConflictDetector::detect(
                'Стекло переднее лобовое 6102100XS01XB',
                'Тормозная система',
                'Колесный тормозной цилиндр',
            ),
        );
    }

    public function test_no_conflict_for_headlight_glass(): void
    {
        // Стекло фары — это оптика, не кузовное стекло; новое правило не должно срабатывать.
        $this->assertNull(CatalogStorefrontCategoryConflictDetector::detectForAssignedCategory(
            'Стекло фары правой',
            'Освещение / Фара',
        ));
    }
}
