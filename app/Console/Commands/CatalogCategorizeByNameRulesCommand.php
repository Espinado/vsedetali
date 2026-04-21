<?php

namespace App\Console\Commands;

use App\Models\Category;
use App\Models\Product;
use App\Support\CatalogStorefrontCategoryConflictDetector;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Массовое исправление витринных категорий на основе whitelist-правил по названию товара.
 *
 * Правила хранятся в config/catalog_name_category_rules.php (можно править без кода).
 * По умолчанию работает в режиме preview — ничего в БД не пишет.
 */
class CatalogCategorizeByNameRulesCommand extends Command
{
    protected $signature = 'catalog:categorize-by-name-rules
        {--apply : Применить изменения (без флага только preview)}
        {--limit=0 : Максимум товаров для обработки (0 = без лимита)}
        {--only=* : Применять только указанные id правил (по умолчанию — все)}
        {--report= : Путь к TSV-отчёту (по умолчанию storage/app/moderation/catalog-name-rules-{дата}.tsv)}
        {--ignore-current-category-guard : Игнорировать apply_only_if_current_category_name_contains_any (опасно)}
        {--max-sample=30 : Сколько строк показать в консольной таблице}';

    protected $description = 'Чинит витринные категории по правилам в config/catalog_name_category_rules.php (preview/--apply)';

    /** @var array<int, array{id:string,description:string,rule:array<string,mixed>,target:Category|null,resolution:string}> */
    private array $resolvedRules = [];

    private ?string $reportPath = null;

    /** @var resource|null */
    private $reportHandle = null;

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $limit = max(0, (int) $this->option('limit'));
        $only = collect($this->option('only'))->filter(fn ($v): bool => is_string($v) && $v !== '')->values()->all();
        $maxSample = max(1, (int) $this->option('max-sample'));
        $ignoreGuard = (bool) $this->option('ignore-current-category-guard');

        $rules = (array) config('catalog_name_category_rules.rules', []);
        if ($rules === []) {
            $this->error('В config/catalog_name_category_rules.php не задано ни одного правила.');

            return self::FAILURE;
        }

        $this->resolveRules($rules, $only);
        $applicable = array_filter($this->resolvedRules, fn (array $r): bool => $r['target'] instanceof Category);

        $this->printResolutionTable();

        if ($applicable === []) {
            $this->warn('Нет правил с найденной целевой категорией — нечего делать.');

            return self::SUCCESS;
        }

        $this->reportPath = (string) ($this->option('report') ?: $this->defaultReportPath($apply));
        $this->openReport();

        $checked = 0;
        $matched = 0;
        $changed = 0;
        $skippedGuard = 0;
        $skippedSameCategory = 0;
        $sample = [];

        Product::query()
            ->where('is_active', true)
            ->whereNotNull('category_id')
            ->with(['category.parent'])
            ->orderBy('id')
            ->chunkById(300, function (Collection $chunk) use (
                &$checked, &$matched, &$changed, &$skippedGuard, &$skippedSameCategory,
                &$sample, $applicable, $limit, $apply, $maxSample, $ignoreGuard,
            ): bool {
                foreach ($chunk as $product) {
                    if ($limit > 0 && $checked >= $limit) {
                        return false;
                    }
                    $checked++;

                    $current = $product->category;
                    if ($current === null) {
                        continue;
                    }

                    $name = (string) $product->name;
                    $currentName = (string) $current->name;

                    foreach ($applicable as $resolved) {
                        $rule = $resolved['rule'];
                        if (! $this->matchesName($name, $rule)) {
                            continue;
                        }

                        $matched++;

                        $target = $resolved['target'];
                        if (! $target instanceof Category) {
                            continue 2;
                        }
                        if ((int) $target->id === (int) $current->id) {
                            $skippedSameCategory++;

                            continue 2;
                        }

                        $guardTokens = (array) ($rule['apply_only_if_current_category_name_contains_any'] ?? []);
                        if (! $ignoreGuard && $guardTokens !== [] && ! $this->stringContainsAny(mb_strtolower($currentName), $guardTokens)) {
                            $skippedGuard++;

                            $this->writeReportRow([
                                'mode' => 'skipped_guard',
                                'rule_id' => $resolved['id'],
                                'product_id' => (string) $product->id,
                                'sku' => (string) $product->sku,
                                'name' => $name,
                                'from' => $currentName,
                                'to' => (string) $target->name,
                                'reason' => 'current_category_does_not_match_guard_tokens',
                            ]);

                            continue 2;
                        }

                        $newPath = $this->categoryPath($target);
                        if (CatalogStorefrontCategoryConflictDetector::detectForAssignedCategory($name, $newPath) !== null) {
                            $skippedGuard++;

                            $this->writeReportRow([
                                'mode' => 'skipped_detector_conflict',
                                'rule_id' => $resolved['id'],
                                'product_id' => (string) $product->id,
                                'sku' => (string) $product->sku,
                                'name' => $name,
                                'from' => $currentName,
                                'to' => (string) $target->name,
                                'reason' => 'target_path_conflicts_with_name',
                            ]);

                            continue 2;
                        }

                        if (count($sample) < $maxSample) {
                            $sample[] = [
                                $product->id,
                                Str::limit((string) $product->sku, 24),
                                Str::limit($name, 60),
                                Str::limit($currentName, 30),
                                Str::limit((string) $target->name, 30),
                                $resolved['id'],
                                $apply ? 'updated' : 'preview',
                            ];
                        }

                        $this->writeReportRow([
                            'mode' => $apply ? 'updated' : 'preview',
                            'rule_id' => $resolved['id'],
                            'product_id' => (string) $product->id,
                            'sku' => (string) $product->sku,
                            'name' => $name,
                            'from' => $currentName,
                            'to' => (string) $target->name,
                            'reason' => $resolved['description'],
                        ]);

                        if ($apply) {
                            $product->category_id = $target->id;
                            $product->save();
                            $changed++;
                        }

                        continue 2;
                    }
                }

                return true;
            });

        $this->closeReport();

        $this->info(sprintf(
            'Проверено: %d; совпадений правил: %d; %s',
            $checked,
            $matched,
            $apply ? "обновлено: {$changed}" : 'режим preview, без записи',
        ));
        $this->line(sprintf(
            'Пропущено (страховка по текущей категории/детектор): %d; уже в целевой: %d',
            $skippedGuard,
            $skippedSameCategory,
        ));

        if ($sample !== []) {
            $this->table(
                ['id', 'sku', 'name', 'from', 'to', 'rule', 'mode'],
                $sample,
            );
        }

        if ($this->reportPath !== null) {
            $this->comment("Отчёт: {$this->reportPath}");
        }

        if (! $apply) {
            $this->comment('Для применения повторите с --apply');
        }

        return self::SUCCESS;
    }

    /**
     * @param  array<int, array<string,mixed>>  $rules
     * @param  list<string>  $only
     */
    private function resolveRules(array $rules, array $only): void
    {
        $byExactName = Category::query()
            ->where('is_active', true)
            ->get(['id', 'name', 'slug', 'parent_id'])
            ->keyBy(fn (Category $c): string => mb_strtolower(trim((string) $c->name)));

        foreach ($rules as $rule) {
            if (! is_array($rule)) {
                continue;
            }
            $id = (string) ($rule['id'] ?? '');
            if ($id === '') {
                continue;
            }
            if ($only !== [] && ! in_array($id, $only, true)) {
                continue;
            }

            $description = (string) ($rule['description'] ?? $id);
            $targets = (array) ($rule['target_category_names'] ?? []);
            $target = null;
            $resolution = 'no_match';

            foreach ($targets as $candidateName) {
                if (! is_string($candidateName) || $candidateName === '') {
                    continue;
                }
                $key = mb_strtolower(trim($candidateName));
                if ($byExactName->has($key)) {
                    $target = $byExactName->get($key);
                    $resolution = "by_name:{$candidateName}";

                    break;
                }
            }

            $this->resolvedRules[] = [
                'id' => $id,
                'description' => $description,
                'rule' => $rule,
                'target' => $target instanceof Category ? $target : null,
                'resolution' => $resolution,
            ];
        }
    }

    private function printResolutionTable(): void
    {
        $rows = [];
        foreach ($this->resolvedRules as $r) {
            $rows[] = [
                $r['id'],
                Str::limit($r['description'], 40),
                $r['target'] instanceof Category ? (string) $r['target']->name : '— не найдена —',
                $r['target'] instanceof Category ? (string) $r['target']->slug : '',
                $r['resolution'],
            ];
        }
        if ($rows !== []) {
            $this->info('Резолвинг целевых категорий:');
            $this->table(['rule', 'описание', 'категория (резолв)', 'slug', 'источник'], $rows);
        }
    }

    /**
     * @param  array<string,mixed>  $rule
     */
    private function matchesName(string $productName, array $rule): bool
    {
        $lower = mb_strtolower($productName);

        $any = (array) ($rule['when_name_any'] ?? []);
        if ($any !== [] && ! $this->stringContainsAny($lower, $any)) {
            return false;
        }

        $all = (array) ($rule['when_name_all'] ?? []);
        foreach ($all as $token) {
            if (! is_string($token) || $token === '') {
                continue;
            }
            if (mb_stripos($lower, mb_strtolower($token)) === false) {
                return false;
            }
        }

        $none = (array) ($rule['when_name_none'] ?? []);
        if ($none !== [] && $this->stringContainsAny($lower, $none)) {
            return false;
        }

        return true;
    }

    /**
     * @param  list<mixed>  $tokens
     */
    private function stringContainsAny(string $haystackLower, array $tokens): bool
    {
        foreach ($tokens as $token) {
            if (! is_string($token) || $token === '') {
                continue;
            }
            if (mb_stripos($haystackLower, mb_strtolower($token)) !== false) {
                return true;
            }
        }

        return false;
    }

    private function categoryPath(Category $category): string
    {
        $category->loadMissing('parent');
        $parent = $category->parent;

        return $parent !== null
            ? trim((string) $parent->name).' / '.trim((string) $category->name)
            : trim((string) $category->name);
    }

    private function defaultReportPath(bool $apply): string
    {
        $stamp = Carbon::now()->format('Ymd-His');
        $suffix = $apply ? 'apply' : 'preview';
        $relative = "moderation/catalog-name-rules-{$stamp}-{$suffix}.tsv";
        $abs = storage_path('app/'.$relative);
        $dir = dirname($abs);
        if (! is_dir($dir) && ! @mkdir($dir, 0775, true) && ! is_dir($dir)) {
            return '';
        }

        return $abs;
    }

    private function openReport(): void
    {
        if ($this->reportPath === null || $this->reportPath === '') {
            return;
        }
        $handle = @fopen($this->reportPath, 'wb');
        if ($handle === false) {
            $this->warn("Не удалось открыть отчёт для записи: {$this->reportPath}");
            $this->reportPath = null;

            return;
        }
        $this->reportHandle = $handle;

        fwrite($handle, "\xEF\xBB\xBF");
        fwrite($handle, $this->tsvRow([
            'mode', 'rule_id', 'product_id', 'sku', 'name', 'from', 'to', 'reason',
        ]));
    }

    /**
     * @param  array<string,string>  $row
     */
    private function writeReportRow(array $row): void
    {
        if ($this->reportHandle === null) {
            return;
        }
        fwrite($this->reportHandle, $this->tsvRow([
            $row['mode'] ?? '',
            $row['rule_id'] ?? '',
            $row['product_id'] ?? '',
            $row['sku'] ?? '',
            $row['name'] ?? '',
            $row['from'] ?? '',
            $row['to'] ?? '',
            $row['reason'] ?? '',
        ]));
    }

    private function closeReport(): void
    {
        if ($this->reportHandle !== null) {
            fclose($this->reportHandle);
            $this->reportHandle = null;
        }
    }

    /**
     * @param  list<string>  $cells
     */
    private function tsvRow(array $cells): string
    {
        $escaped = array_map(static function (string $c): string {
            $c = str_replace(["\r", "\n", "\t"], [' ', ' ', ' '], $c);

            return $c;
        }, $cells);

        return implode("\t", $escaped)."\n";
    }
}
