<?php

namespace App\Services;

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
        [$handle, , , , $header] = self::openNormalizedCsvStream($absolutePath);
        fclose($handle);

        return $header;
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

        [$handle, $delimiter, , $afterHeaderBytePos, ] = self::openNormalizedCsvStream($absolutePath);

        try {
            rewind($handle);
            fseek($handle, $afterHeaderBytePos);

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
     * @return array{0: resource, 1: string, 2: string, 3: int, 4: list<string>}
     */
    public static function openNormalizedCsvStream(string $absolutePath): array
    {
        $raw = file_get_contents($absolutePath);
        if ($raw === false) {
            throw new \RuntimeException('Не удалось прочитать файл.');
        }

        $content = self::normalizeFileContentString($raw);
        $meta = self::locateHeaderInNormalizedContent($content);

        if ($meta === null) {
            $cp1251 = self::tryDecodeAsCp1251ThenNormalize($raw);
            if ($cp1251 !== null) {
                $content = $cp1251;
                $meta = self::locateHeaderInNormalizedContent($content);
            }
        }

        if ($meta === null) {
            throw new \RuntimeException(
                'После разбора CSV не найдена строка заголовка с «Код» и «Артикул». '.
                'Сохраните файл как CSV UTF-8 или CSV с разделителем «;»/«,» и колонками «Код», «Артикул». '.
                'Если перенос строки внутри ячейки «Сумма / себестоимости» — пересохраните из Excel в UTF-8.'
            );
        }

        $handle = fopen('php://temp', 'r+b');
        if ($handle === false) {
            throw new \RuntimeException('Не удалось создать временный поток.');
        }
        fwrite($handle, $content);
        rewind($handle);

        return [$handle, $meta['delimiter'], $content, $meta['after_byte_pos'], $meta['header']];
    }

    /**
     * @return array{delimiter: string, after_byte_pos: int, header: list<string>}|null
     */
    private static function locateHeaderInNormalizedContent(string $content): ?array
    {
        foreach ([',', ';'] as $delimiter) {
            $h = fopen('php://temp', 'r+b');
            if ($h === false) {
                continue;
            }
            fwrite($h, $content);
            rewind($h);

            while (($row = fgetcsv($h, 0, $delimiter, '"')) !== false) {
                $normalized = array_map(
                    fn ($c) => self::normalizeUtf8String(trim((string) ($c ?? ''), " \t\n\r\0\x0B\xEF\xBB\xBF")),
                    $row
                );
                if (self::parsedRowIsRemainsTableHeader($normalized)) {
                    $after = ftell($h);
                    fclose($h);

                    return [
                        'delimiter' => $delimiter,
                        'after_byte_pos' => $after !== false ? $after : strlen($content),
                        'header' => $normalized,
                    ];
                }
            }

            fclose($h);
        }

        return null;
    }

    /**
     * @param  list<string>  $cells
     */
    private static function parsedRowIsRemainsTableHeader(array $cells): bool
    {
        $hasKod = false;
        $hasArtikul = false;
        foreach ($cells as $cell) {
            if ($cell === 'Код') {
                $hasKod = true;
            }
            if ($cell === 'Артикул') {
                $hasArtikul = true;
            }
        }

        return $hasKod && $hasArtikul;
    }

    private static function normalizeFileContentString(string $bytes): string
    {
        if (str_starts_with($bytes, "\xEF\xBB\xBF")) {
            $bytes = substr($bytes, 3);
        }

        $content = str_replace(["\r\n", "\r"], "\n", $bytes);

        $content = str_replace(
            ["\xE2\x80\x9C", "\xE2\x80\x9D", "\xE2\x80\x9E", "\xC2\xAB", "\xC2\xBB"],
            '"',
            $content
        );

        $content = self::collapseNewlinesInsideDoubleQuotedFields($content);

        $content = preg_replace('/"Сумма\s*\/\s*себестоимости"/u', '"Сумма себестоимости"', $content) ?? $content;
        $content = preg_replace('/"Сумма\s+себестоимости"/u', '"Сумма себестоимости"', $content) ?? $content;
        $content = str_replace('"Сумма'."\n".'себестоимости"', '"Сумма себестоимости"', $content);

        return $content;
    }

    private static function tryDecodeAsCp1251ThenNormalize(string $raw): ?string
    {
        if (str_starts_with($raw, "\xEF\xBB\xBF")) {
            $raw = substr($raw, 3);
        }

        $converted = @mb_convert_encoding($raw, 'UTF-8', 'Windows-1251');
        if (! is_string($converted) || $converted === '' || ! mb_check_encoding($converted, 'UTF-8')) {
            return null;
        }

        return self::normalizeFileContentString($converted);
    }

    /**
     * Excel/1C иногда сохраняют перенос строки внутри кавычек («Сумма» + newline + «себестоимости»).
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
