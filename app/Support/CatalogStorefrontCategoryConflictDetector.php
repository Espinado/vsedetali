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

        if (self::nameLooksCardanRelated($name) && self::categoryLooksCvJointRelated($cat)) {
            return 'cardan_name_cvjoint_category';
        }

        if (self::nameLooksHubRelated($name) && self::categoryLooksAlienToHub($cat)) {
            return 'hub_name_alien_category';
        }

        if (self::nameLooksBumperRelated($name) && self::categoryLooksAlienToBodyExterior($cat)) {
            return 'bumper_name_alien_category';
        }

        if (self::nameLooksBodyGlassRelated($name) && self::categoryLooksAlienToBodyExterior($cat)) {
            return 'body_glass_name_alien_category';
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

        if (self::nameLooksCardanRelated($name) && self::categoryLooksCvJointRelated($cat)) {
            return 'cardan_name_cvjoint_category';
        }

        if (self::nameLooksHubRelated($name) && self::categoryLooksAlienToHub($cat)) {
            return 'hub_name_alien_category';
        }

        if (self::nameLooksBumperRelated($name) && self::categoryLooksAlienToBodyExterior($cat)) {
            return 'bumper_name_alien_category';
        }

        if (self::nameLooksBodyGlassRelated($name) && self::categoryLooksAlienToBodyExterior($cat)) {
            return 'body_glass_name_alien_category';
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

    private static function nameLooksCardanRelated(string $nameLower): bool
    {
        return mb_stripos($nameLower, 'кардан') !== false
            || mb_stripos($nameLower, 'карданн') !== false
            || (mb_stripos($nameLower, 'муфт') !== false && mb_stripos($nameLower, 'вала') !== false);
    }

    private static function categoryLooksCvJointRelated(string $catLower): bool
    {
        return mb_stripos($catLower, 'шарнир') !== false
            || mb_stripos($catLower, 'шрус') !== false;
    }

    private static function nameLooksHubRelated(string $nameLower): bool
    {
        return mb_stripos($nameLower, 'ступиц') !== false
            || mb_stripos($nameLower, 'подшипник ступи') !== false;
    }

    /**
     * Категории, в которые ступица/подшипник ступицы заведомо не относится
     * (краски/лаки, оптика, гидравлика тормозов, топливная, электроника пуска и т.п.).
     */
    private static function categoryLooksAlienToHub(string $catLower): bool
    {
        // Если категория сама про ступицу/подшипник — это НЕ конфликт.
        if (mb_stripos($catLower, 'ступиц') !== false || mb_stripos($catLower, 'подшипник') !== false) {
            return false;
        }

        $alien = [
            'краск', 'лак', 'эмаль', 'грунт',
            'фонар', 'оптик', 'фар',
            'цилиндр', 'карбюратор', 'стартер', 'свеч',
            'фильтр', 'топлив', 'форсунк',
            'датчик', 'модул', 'температур',
            'шарнир', 'диск шарнира',
            'клапан',
        ];
        foreach ($alien as $token) {
            if (mb_stripos($catLower, $token) !== false) {
                return true;
            }
        }

        return false;
    }

    private static function nameLooksBumperRelated(string $nameLower): bool
    {
        if (mb_stripos($nameLower, 'бампер') === false) {
            return false;
        }
        if (mb_stripos($nameLower, 'усилител') !== false) {
            return false;
        }
        if (mb_stripos($nameLower, 'кронштейн') !== false) {
            return false;
        }

        return true;
    }

    private static function nameLooksBodyGlassRelated(string $nameLower): bool
    {
        if (mb_stripos($nameLower, 'фар') !== false) {
            return false;
        }
        if (mb_stripos($nameLower, 'противотуман') !== false || mb_stripos($nameLower, 'птф') !== false) {
            return false;
        }

        $patterns = [
            'стекло лобов',
            'стекло переда',
            'стекло переднее',
            'стекло задн',
            'стекло двер',
            'стекло бок',
            'стекло крыши',
        ];
        foreach ($patterns as $p) {
            if (mb_stripos($nameLower, $p) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Категории, чуждые внешнему кузову/стеклу (бамперу, лобовому стеклу и т.п.):
     * гидравлика тормозов, моторная электрика, топливо, фары и т.д.
     */
    private static function categoryLooksAlienToBodyExterior(string $catLower): bool
    {
        $alien = [
            'цилиндр', 'карбюратор', 'стартер', 'свеч',
            'тормоз', 'колодк', 'суппорт',
            'топлив', 'форсунк', 'фильтр',
            'фонар', 'стояночный',
            'датчик', 'модул', 'температур',
            'шарнир', 'шрус',
            'генератор', 'зажиган',
        ];
        foreach ($alien as $token) {
            if (mb_stripos($catLower, $token) !== false) {
                return true;
            }
        }

        return false;
    }
}
