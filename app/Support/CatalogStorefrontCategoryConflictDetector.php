<?php

namespace App\Support;

/**
 * Эвристика: название товара (остатки/витрина) не совпадает с категорией из каталога TecDoc/RapidAPI.
 * Используется, чтобы не перезаписывать category_id и отправлять кейс в файл модерации.
 */
final class CatalogStorefrontCategoryConflictDetector
{
    /**
     * @return non-empty-string|null код правила или null, если конфликта нет
     */
    public static function detect(string $productName, string $categoryMain, string $categorySub): ?string
    {
        $name = self::norm($productName);
        if ($name === '') {
            return null;
        }

        $cat = self::norm(trim($categoryMain.' '.$categorySub));
        if ($cat === '') {
            return null;
        }

        if (self::nameLooksLightingRelated($name) && self::categoryLooksBrakeRelated($cat)) {
            return 'lighting_name_brake_category';
        }

        if (self::nameLooksBrakeRelated($name) && self::categoryLooksLightingRelated($cat)) {
            return 'brake_name_lighting_category';
        }

        return null;
    }

    /**
     * Проверка уже назначенной витринной категории (имя листа + родитель).
     */
    public static function detectForAssignedCategory(string $productName, string $categoryPath): ?string
    {
        $name = self::norm($productName);
        $cat = self::norm($categoryPath);
        if ($name === '' || $cat === '') {
            return null;
        }

        if (self::nameLooksLightingRelated($name) && self::categoryLooksBrakeRelated($cat)) {
            return 'lighting_name_brake_category';
        }

        if (self::nameLooksBrakeRelated($name) && self::categoryLooksLightingRelated($cat)) {
            return 'brake_name_lighting_category';
        }

        return null;
    }

    private static function norm(string $s): string
    {
        $s = mb_strtolower(trim($s));

        return preg_replace('/\s+/u', ' ', $s) ?? $s;
    }

    private static function nameLooksLightingRelated(string $nameLower): bool
    {
        if (mb_stripos($nameLower, 'птф') !== false) {
            return true;
        }
        if (mb_stripos($nameLower, 'противотуман') !== false) {
            return true;
        }
        if (mb_stripos($nameLower, 'кронштейн') !== false && mb_stripos($nameLower, 'фар') !== false) {
            return true;
        }
        if (mb_stripos($nameLower, 'стекло') !== false && mb_stripos($nameLower, 'фар') !== false) {
            return true;
        }
        if (mb_stripos($nameLower, 'отражател') !== false && mb_stripos($nameLower, 'фар') !== false) {
            return true;
        }
        if (preg_match('#(?:^|\s)фар[аы]?(?:\s|$|[,.;/])#u', $nameLower) === 1) {
            return true;
        }

        return false;
    }

    private static function categoryLooksBrakeRelated(string $catLower): bool
    {
        return mb_stripos($catLower, 'тормоз') !== false
            || mb_stripos($catLower, 'колодк') !== false
            || mb_stripos($catLower, 'суппорт') !== false;
    }

    private static function nameLooksBrakeRelated(string $nameLower): bool
    {
        return mb_stripos($nameLower, 'тормоз') !== false
            || mb_stripos($nameLower, 'колодк') !== false
            || mb_stripos($nameLower, 'суппорт') !== false;
    }

    private static function categoryLooksLightingRelated(string $catLower): bool
    {
        if (mb_stripos($catLower, 'птф') !== false) {
            return true;
        }
        if (mb_stripos($catLower, 'противотуман') !== false) {
            return true;
        }
        if (mb_stripos($catLower, 'фар') !== false) {
            return true;
        }
        if (mb_stripos($catLower, 'оптик') !== false) {
            return true;
        }

        return false;
    }
}
