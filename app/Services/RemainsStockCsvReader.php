<?php

namespace App\Services;

use Illuminate\Support\Str;

/**
 * Чтение CSV отчёта «Остатки» (Код, Артикул, …) — та же нормализация, что в {@see RemainsStockCsvImportService}.
 */
final class RemainsStockCsvReader
{
    /**
     * Разобранная строка заголовка таблицы (колонки «Код», «Артикул», …).
     *
     * @return list<string>
     */
    public static function readHeaderRow(string $absolutePath): array
    {
        [$handle, $delimiter, $content] = self::openNormalizedCsvStream($absolutePath);
        fclose($handle);

        $markers = [',Код,Артикул,', ';Код;Артикул;'];
        $pos = false;
        $matchedMarker = null;
        foreach ($markers as $m) {
            $pos = strpos($content, $m);
            if ($pos !== false) {
                $matchedMarker = $m;

                break;
            }
        }

        if ($pos === false || $matchedMarker === null) {
            throw new \RuntimeException(
                'В тексте файла не найдена подстрока «,Код,Артикул,» (или с «;»).'
            );
        }

        $lineStart = strrpos(substr($content, 0, $pos), "\n");
        $lineStart = $lineStart === false ? 0 : $lineStart + 1;
        $lineEnd = strpos($content, "\n", $lineStart);
        $headerLine = $lineEnd === false
            ? substr($content, $lineStart)
            : substr($content, $lineStart, $lineEnd - $lineStart);

        if (! self::headerLineLooksLikeDataTable($headerLine, $delimiter)) {
            throw new \RuntimeException(
                'Строка заголовка не распознана. Начало: '.Str::limit($headerLine, 240)
            );
        }

        $mem = fopen('php://memory', 'r+b');
        if ($mem === false) {
            throw new \RuntimeException('Не удалось разобрать строку заголовка.');
        }
        fwrite($mem, $headerLine);
        rewind($mem);
        $row = fgetcsv($mem, 0, $delimiter, '"');
        fclose($mem);
        if ($row === false) {
            return [];
        }

        return array_map(fn ($c) => self::normalizeUtf8String(trim((string) ($c ?? ''), " \t\n\r\0\x0B\xEF\xBB\xBF")), $row);
    }

    /**
     * Строки данных после заголовка (включая строки-секции: пустой артикул).
     *
     * @return \Generator<int, array<int, string>>
     */
    public static function iterateDataRows(string $absolutePath): \Generator
    {
        if (! is_readable($absolutePath)) {
            throw new \InvalidArgumentException("Файл недоступен: {$absolutePath}");
        }

        [$handle, $delimiter, $content] = self::openNormalizedCsvStream($absolutePath);
        self::seekStreamAfterHeaderRow($handle, $content, $delimiter);

        try {
            while (($row = fgetcsv($handle, 0, $delimiter, '"')) !== false) {
                $row = array_map(
                    fn ($c) => self::normalizeUtf8String(trim((string) ($c ?? ''), " \t\n\r\0\x0B\xEF\xBB\xBF")),
                    $row
                );
                $row = array_pad($row, 16, '');

                yield $row;
            }
        } finally {
            fclose($handle);
        }
    }

    /**
     * @return array{0: resource, 1: string, 2: string}
     */
    public static function openNormalizedCsvStream(string $absolutePath): array
    {
        $content = file_get_contents($absolutePath);
        if ($content === false) {
            throw new \RuntimeException('Не удалось прочитать файл.');
        }

        if (str_starts_with($content, "\xEF\xBB\xBF")) {
            $content = substr($content, 3);
        }

        $content = str_replace(["\r\n", "\r"], "\n", $content);

        $content = str_replace(
            ["\xE2\x80\x9C", "\xE2\x80\x9D", "\xE2\x80\x9E", "\xC2\xAB", "\xC2\xBB"],
            '"',
            $content
        );

        $content = self::collapseNewlinesInsideDoubleQuotedFields($content);

        $content = preg_replace('/"Сумма\s*\/\s*себестоимости"/u', '"Сумма себестоимости"', $content);
        $content = preg_replace('/"Сумма\s+себестоимости"/u', '"Сумма себестоимости"', $content);
        $content = str_replace('"Сумма'."\n".'себестоимости"', '"Сумма себестоимости"', $content);

        if (! str_contains($content, ',Код,Артикул,') && ! str_contains($content, ';Код;Артикул;')) {
            throw new \RuntimeException(
                'После разбора CSV не найдена строка заголовка с «Код» и «Артикул». '.
                'Уберите перенос строки внутри ячейки «Сумма / себестоимости» в первой строке таблицы или сохраните файл как CSV UTF-8.'
            );
        }

        $delimiter = ',';
        if (str_contains($content, ',Код,Артикул,') || preg_match('/(^|\n),Код,Артикул,/u', $content)) {
            $delimiter = ',';
        } elseif (str_contains($content, ';Код;Артикул;') || preg_match('/(^|\n);Код;Артикул;/u', $content)) {
            $delimiter = ';';
        } else {
            foreach (explode("\n", $content) as $line) {
                if (str_contains($line, 'Код') && str_contains($line, 'Артикул')) {
                    $delimiter = substr_count($line, ';') > substr_count($line, ',') ? ';' : ',';

                    break;
                }
            }
        }

        $handle = fopen('php://temp', 'r+b');
        if ($handle === false) {
            throw new \RuntimeException('Не удалось создать временный поток.');
        }
        fwrite($handle, $content);
        rewind($handle);

        return [$handle, $delimiter, $content];
    }

    public static function seekStreamAfterHeaderRow($handle, string $content, string $delimiter): void
    {
        $markers = [',Код,Артикул,', ';Код;Артикул;'];
        $pos = false;
        $matchedMarker = null;
        foreach ($markers as $m) {
            $pos = strpos($content, $m);
            if ($pos !== false) {
                $matchedMarker = $m;

                break;
            }
        }

        if ($pos === false || $matchedMarker === null) {
            throw new \RuntimeException(
                'В тексте файла не найдена подстрока «,Код,Артикул,» (или с «;»). Сохраните CSV в UTF-8 и одну строку заголовка.'
            );
        }

        $lineStart = strrpos(substr($content, 0, $pos), "\n");
        $lineStart = $lineStart === false ? 0 : $lineStart + 1;
        $lineEnd = strpos($content, "\n", $lineStart);
        $headerLine = $lineEnd === false
            ? substr($content, $lineStart)
            : substr($content, $lineStart, $lineEnd - $lineStart);

        if (! str_contains($headerLine, $matchedMarker)) {
            throw new \RuntimeException(
                'Строка заголовка обрезана неверно. Начало: '.Str::limit($headerLine, 240)
            );
        }

        if (! self::headerLineLooksLikeDataTable($headerLine, $delimiter)) {
            throw new \RuntimeException(
                'Строка с «Код»/«Артикул» не похожа на заголовок таблицы. Начало строки: '.Str::limit($headerLine, 240)
            );
        }

        $nextOffset = $lineEnd === false ? strlen($content) : $lineEnd + 1;
        rewind($handle);
        fseek($handle, $nextOffset);
    }

    public static function headerLineLooksLikeDataTable(string $headerLine, string $delimiter): bool
    {
        if ($delimiter === ',') {
            return (bool) preg_match('/(?:^|,)\s*Код\s*,\s*Артикул\s*(?:,|$)/u', $headerLine);
        }

        return (bool) preg_match('/(?:^|;)\s*Код\s*;\s*Артикул\s*(?:;|$)/u', $headerLine);
    }

    /**
     * Excel/1C иногда сохраняют перенос строки внутри кавычек («Сумма» + newline + «себестоимости»),
     * из‑за этого подстрока «,Код,Артикул,» не находится в одной текстовой строке.
     */
    private static function collapseNewlinesInsideDoubleQuotedFields(string $content): string
    {
        $len = strlen($content);
        if ($len === 0) {
            return $content;
        }

        $out = '';
        $inQuotes = false;
        for ($i = 0; $i < $len; $i++) {
            $c = $content[$i];
            if ($c === '"') {
                if ($inQuotes && $i + 1 < $len && $content[$i + 1] === '"') {
                    $out .= '""';
                    $i++;

                    continue;
                }
                $inQuotes = ! $inQuotes;
                $out .= '"';

                continue;
            }
            if ($inQuotes) {
                if ($c === "\r") {
                    if ($i + 1 < $len && $content[$i + 1] === "\n") {
                        $i++;
                    }
                    $out .= ' ';

                    continue;
                }
                if ($c === "\n") {
                    $out .= ' ';

                    continue;
                }
            }
            $out .= $c;
        }

        return $out;
    }

    public static function normalizeUtf8String(string $s): string
    {
        if ($s === '') {
            return '';
        }

        if (function_exists('iconv')) {
            $clean = @iconv('UTF-8', 'UTF-8//IGNORE', $s);
            if ($clean !== false) {
                $s = $clean;
            }
        }

        if (! mb_check_encoding($s, 'UTF-8')) {
            $converted = @mb_convert_encoding($s, 'UTF-8', 'Windows-1251');
            if (is_string($converted) && mb_check_encoding($converted, 'UTF-8')) {
                return $converted;
            }
        }

        return $s;
    }
}
