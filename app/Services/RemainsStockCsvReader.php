<?php

namespace App\Services;

/**
 * Чтение CSV отчёта «Остатки» (Код, Артикул, …) — та же нормализация, что в {@see RemainsStockCsvImportService}.
 */
final class RemainsStockCsvReader
{
    /**
     * @return array<int, string>|false
     */
    private static function readCsvRow($handle, string $delimiter): array|false
    {
        if (PHP_VERSION_ID >= 80400) {
            return fgetcsv($handle, 0, $delimiter, '"', '');
        }

        return fgetcsv($handle, 0, $delimiter, '"');
    }

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

            while (($row = self::readCsvRow($handle, $delimiter)) !== false) {
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

        $asUtf8 = self::convertFileBytesToUtf8Text($raw);
        $preCollapse = self::preCollapseNormalize($asUtf8);
        $content = self::normalizeFileContentString($asUtf8);

        $meta = self::locateHeaderByUtf8MarkerLine($content)
            ?? self::locateHeaderByScanningFgetcsv($content, 12_000);

        if ($meta === null) {
            $meta = self::locateHeaderByUtf8MarkerLine($preCollapse)
                ?? self::locateHeaderByScanningFgetcsv($preCollapse, 12_000);
            if ($meta !== null) {
                if (isset($meta['_header_physical_line'])) {
                    $meta = self::relocateHeaderMetaUsingPhysicalLine($content, $meta)
                        ?? self::locateHeaderByScanningFgetcsv($content, 12_000);
                } else {
                    $pos = $meta['after_byte_pos'];
                    $prefixOk = $pos <= strlen($preCollapse) && $pos <= strlen($content)
                        && substr($preCollapse, 0, $pos) === substr($content, 0, $pos);
                    if (! $prefixOk) {
                        $meta = self::locateHeaderByScanningFgetcsv($content, 12_000) ?? $meta;
                    }
                }
            }
        }

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
                'Сохраните как «CSV UTF-8» или «CSV (разделители — запятые)» в Excel / LibreOffice. '.
                'Поддерживаются разделители запятая, точка с запятой и табуляция. '.
                'Если файл в UTF-16 — сохраните как UTF-8.'
            );
        }

        $handle = fopen('php://temp', 'r+b');
        if ($handle === false) {
            throw new \RuntimeException('Не удалось создать временный поток.');
        }
        fwrite($handle, $content);
        rewind($handle);

        unset($meta['_header_physical_line']);

        return [$handle, $meta['delimiter'], $content, $meta['after_byte_pos'], $meta['header']];
    }

    /**
     * @return array{delimiter: string, after_byte_pos: int, header: list<string>}|null
     */
    private static function locateHeaderInNormalizedContent(string $content): ?array
    {
        return self::locateHeaderByScanningFgetcsv($content)
            ?? self::locateHeaderByUtf8MarkerLine($content);
    }

    /**
     * @param  array{delimiter: string, after_byte_pos: int, header: list<string>, _header_physical_line?: string}  $meta
     * @return array{delimiter: string, after_byte_pos: int, header: list<string>}|null
     */
    private static function relocateHeaderMetaUsingPhysicalLine(string $content, array $meta): ?array
    {
        $line = $meta['_header_physical_line'] ?? null;
        unset($meta['_header_physical_line']);
        if (! is_string($line) || $line === '') {
            return null;
        }

        $p = strpos($content, $line);
        if ($p === false) {
            return null;
        }

        $lineEnd = strpos($content, "\n", $p);
        $afterBytePos = $lineEnd === false ? strlen($content) : $lineEnd + 1;
        $meta['after_byte_pos'] = $afterBytePos;

        return $meta;
    }

    /**
     * @return array{delimiter: string, after_byte_pos: int, header: list<string>, _header_physical_line?: string}|null
     */
    private static function locateHeaderByScanningFgetcsv(string $content, ?int $maxLogicalRows = null): ?array
    {
        foreach ([',', ';', "\t"] as $delimiter) {
            $h = fopen('php://temp', 'r+b');
            if ($h === false) {
                continue;
            }
            fwrite($h, $content);
            rewind($h);

            $rowsRead = 0;
            while (($row = self::readCsvRow($h, $delimiter)) !== false) {
                if ($maxLogicalRows !== null && $rowsRead >= $maxLogicalRows) {
                    break;
                }
                $rowsRead++;
                $normalized = array_map(fn ($c) => self::normalizeCsvHeaderCell($c), $row);
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
     * Если выше строки заголовка есть «битая» кавычка, fgetcsv съедает заголовок в состав предыдущей записи.
     * Ищем однозначную UTF-8 метку «,Код,Артикул,» / «;Код;Артикул;» и разбираем одну физическую строку.
     *
     * @return array{delimiter: string, after_byte_pos: int, header: list<string>}|null
     */
    private static function locateHeaderByUtf8MarkerLine(string $content): ?array
    {
        $regexAttempts = [
            [',', '/,\s*Код\s*,\s*Артикул\s*,/u'],
            [';', '/;\s*Код\s*;\s*Артикул\s*;/u'],
            ["\t", '/\t\s*Код\s*\t\s*Артикул\s*\t/u'],
        ];

        foreach ($regexAttempts as [$delimiter, $pattern]) {
            if (preg_match($pattern, $content, $m, PREG_OFFSET_CAPTURE) !== 1) {
                continue;
            }

            $meta = self::extractHeaderLineMetaFromMatch($content, (int) $m[0][1], $delimiter);
            if ($meta !== null) {
                return $meta;
            }
        }

        $byteNeedles = [
            [',', "\x2C\xD0\x9A\xD0\xBE\xD0\xB4\x2C\xD0\x90\xD1\x80\xD1\x82\xD0\xB8\xD0\xBA\xD1\x83\xD0\xBB\x2C"],
            [';', "\x3B\xD0\x9A\xD0\xBE\xD0\xB4\x3B\xD0\x90\xD1\x80\xD1\x82\xD0\xB8\xD0\xBA\xD1\x83\xD0\xBB\x3B"],
            ["\t", "\x09\xD0\x9A\xD0\xBE\xD0\xB4\x09\xD0\x90\xD1\x80\xD1\x82\xD0\xB8\xD0\xBA\xD1\x83\xD0\xBB\x09"],
        ];

        foreach ($byteNeedles as [$delimiter, $needle]) {
            $pos = strpos($content, $needle);
            if ($pos === false) {
                continue;
            }

            $meta = self::extractHeaderLineMetaFromMatch($content, $pos, $delimiter);
            if ($meta !== null) {
                return $meta;
            }
        }

        return null;
    }

    /**
     * @return array{delimiter: string, after_byte_pos: int, header: list<string>}|null
     */
    private static function extractHeaderLineMetaFromMatch(string $content, int $matchBytePos, string $delimiter): ?array
    {
        $lineStart = strrpos(substr($content, 0, $matchBytePos), "\n");
        $lineStart = $lineStart === false ? 0 : $lineStart + 1;
        $lineEnd = strpos($content, "\n", $matchBytePos);
        $line = $lineEnd === false
            ? substr($content, $lineStart)
            : substr($content, $lineStart, $lineEnd - $lineStart);

        $row = self::strGetCsvRow($line, $delimiter);
        if ($row === []) {
            return null;
        }

        $normalized = array_map(fn ($c) => self::normalizeCsvHeaderCell($c), $row);
        if (! self::parsedRowIsRemainsTableHeader($normalized)) {
            return null;
        }

        $afterBytePos = $lineEnd === false ? strlen($content) : $lineEnd + 1;

        return [
            'delimiter' => $delimiter,
            'after_byte_pos' => $afterBytePos,
            'header' => $normalized,
            '_header_physical_line' => $line,
        ];
    }

    /**
     * @return list<string>
     */
    private static function strGetCsvRow(string $line, string $delimiter): array
    {
        if (PHP_VERSION_ID >= 80400) {
            $row = str_getcsv($line, $delimiter, '"', '');
        } else {
            $row = str_getcsv($line, $delimiter, '"');
        }

        return is_array($row) ? $row : [];
    }

    /**
     * @param  list<string>  $cells
     */
    private static function parsedRowIsRemainsTableHeader(array $cells): bool
    {
        $hasKod = false;
        $hasArtikul = false;
        foreach ($cells as $cell) {
            if ($cell === 'Код' || $cell === 'код') {
                $hasKod = true;
            }
            if ($cell === 'Артикул' || $cell === 'артикул') {
                $hasArtikul = true;
            }
            if (function_exists('mb_strtolower')) {
                $lc = @mb_strtolower($cell, 'UTF-8');
                if ($lc === 'код') {
                    $hasKod = true;
                }
                if ($lc === 'артикул') {
                    $hasArtikul = true;
                }
            }
        }

        return $hasKod && $hasArtikul;
    }

    /**
     * BOM / UTF-16: Excel часто отдаёт «Unicode CSV» как UTF-16LE.
     */
    private static function convertFileBytesToUtf8Text(string $bytes): string
    {
        if ($bytes === '') {
            return $bytes;
        }

        if (str_starts_with($bytes, "\xFF\xFE")) {
            $body = substr($bytes, 2);
            $out = @mb_convert_encoding($body, 'UTF-8', 'UTF-16LE');

            return $out !== false ? $out : $bytes;
        }

        if (str_starts_with($bytes, "\xFE\xFF")) {
            $body = substr($bytes, 2);
            $out = @mb_convert_encoding($body, 'UTF-8', 'UTF-16BE');

            return $out !== false ? $out : $bytes;
        }

        if (str_starts_with($bytes, "\xEF\xBB\xBF")) {
            return substr($bytes, 3);
        }

        return $bytes;
    }

    private static function normalizeCsvHeaderCell(mixed $c): string
    {
        $s = self::normalizeUtf8String(trim((string) ($c ?? ''), " \t\n\r\0\x0B\xEF\xBB\xBF"));
        $s = str_replace("\xC2\xA0", ' ', $s);
        $s = preg_replace('/[\x{200B}\x{FEFF}]/u', '', $s) ?? $s;

        return trim($s);
    }

    /**
     * BOM, переводы строк и «умные» кавычки — без collapse (он может ломать поиск заголовка в больших файлах).
     */
    private static function preCollapseNormalize(string $bytes): string
    {
        while (str_starts_with($bytes, "\xEF\xBB\xBF")) {
            $bytes = substr($bytes, 3);
        }

        $content = str_replace(["\r\n", "\r"], "\n", $bytes);

        return str_replace(
            ["\xE2\x80\x9C", "\xE2\x80\x9D", "\xE2\x80\x9E", "\xC2\xAB", "\xC2\xBB"],
            '"',
            $content
        );
    }

    private static function normalizeFileContentString(string $bytes): string
    {
        $content = self::preCollapseNormalize($bytes);

        $content = self::collapseNewlinesInsideDoubleQuotedFields($content);

        $content = preg_replace('/"Сумма\s*\/\s*себестоимости"/u', '"Сумма себестоимости"', $content) ?? $content;
        $content = preg_replace('/"Сумма\s+себестоимости"/u', '"Сумма себестоимости"', $content) ?? $content;
        $content = str_replace('"Сумма'."\n".'себестоимости"', '"Сумма себестоимости"', $content);

        return $content;
    }

    private static function tryDecodeAsCp1251ThenNormalize(string $raw): ?string
    {
        if (str_starts_with($raw, "\xFF\xFE") || str_starts_with($raw, "\xFE\xFF")) {
            return null;
        }

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
