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
     * @param  'utf-8'|'cp1251'|null  $csvEncoding  null = авто (UTF-8/UTF-16 + fallback cp1251); cp1251 = принудительно Windows-1251; utf-8 = без перекодирования из cp1251
     * @return list<string>
     */
    public static function readHeaderRow(string $absolutePath, ?string $csvEncoding = null): array
    {
        [$handle, , , , $header] = self::openNormalizedCsvStream($absolutePath, $csvEncoding);
        fclose($handle);

        return $header;
    }

    /**
     * Строки данных после заголовка (включая строки-секции: пустой артикул).
     *
     * @param  'utf-8'|'cp1251'|null  $csvEncoding  см. {@see readHeaderRow()}
     * @return \Generator<int, array<int, string>>
     */
    public static function iterateDataRows(string $absolutePath, ?string $csvEncoding = null): \Generator
    {
        if (! is_readable($absolutePath)) {
            throw new \InvalidArgumentException("Файл недоступен: {$absolutePath}");
        }

        [$handle, $delimiter, , $afterHeaderBytePos, ] = self::openNormalizedCsvStream($absolutePath, $csvEncoding);

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
     * @param  'utf-8'|'cp1251'|null  $csvEncoding
     * @return array{0: resource, 1: string, 2: string, 3: int, 4: list<string>}
     */
    public static function openNormalizedCsvStream(string $absolutePath, ?string $csvEncoding = null): array
    {
        $raw = file_get_contents($absolutePath);
        if ($raw === false) {
            throw new \RuntimeException('Не удалось прочитать файл.');
        }

        $result = self::resolveHeaderMetaWithEncodingStrategies($raw, $csvEncoding);

        if ($result === null) {
            throw new \RuntimeException(
                'После разбора CSV не найдена строка заголовка с колонками «Код» и «Артикул» '.
                '(или эквивалентами: Code, Article, Part number и т.п.). '.
                'Сохраните как «CSV UTF-8» в Excel / LibreOffice. '.
                'Поддерживаются разделители: запятая, точка с запятой, табуляция, вертикальная черта. '.
                'Попробуйте без --encoding, с --encoding=cp1251 или с --encoding=utf-8. '.
                'Если сверху таблицы много служебных строк — убедитесь, что в одной строке есть оба заголовка.'
            );
        }

        ['content' => $content, 'meta' => $meta] = $result;

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
     * Несколько стратегий: UTF-8/UTF-16 из байтов, затем cp1251; при явном --encoding сначала выбранная, затем запасная.
     *
     * @return array{content: string, meta: array{delimiter: string, after_byte_pos: int, header: list<string>, _header_physical_line?: string}}|null
     */
    private static function resolveHeaderMetaWithEncodingStrategies(string $raw, ?string $csvEncoding): ?array
    {
        $attemptUtf = static fn (): string => self::convertFileBytesToUtf8Text($raw);

        $attemptCp1251 = static function () use ($raw): string {
            $t = self::tryDecodeRawBytesToUtf8Cp1251($raw);
            if ($t === null) {
                throw new \RuntimeException('cp1251 decode skipped');
            }

            return $t;
        };

        $chains = match ($csvEncoding) {
            'cp1251' => [
                ['cp1251', $attemptCp1251],
                ['utf8', $attemptUtf],
            ],
            'utf-8' => [
                ['utf8', $attemptUtf],
                ['cp1251', $attemptCp1251],
            ],
            default => [
                ['utf8', $attemptUtf],
                ['cp1251', $attemptCp1251],
            ],
        };

        foreach ($chains as [, $getUtf]) {
            try {
                $asUtf8 = $getUtf();
            } catch (\Throwable) {
                continue;
            }
            $found = self::findHeaderMetaAfterNormalize($asUtf8);
            if ($found !== null) {
                return $found;
            }
        }

        if ($csvEncoding === null) {
            $cp1251Norm = self::tryDecodeAsCp1251ThenNormalize($raw);
            if ($cp1251Norm !== null) {
                $meta = self::locateHeaderInNormalizedContent($cp1251Norm);
                if ($meta !== null) {
                    return ['content' => $cp1251Norm, 'meta' => $meta];
                }
            }
        }

        return null;
    }

    /**
     * @return array{content: string, meta: array{delimiter: string, after_byte_pos: int, header: list<string>, _header_physical_line?: string}}|null
     */
    private static function findHeaderMetaAfterNormalize(string $asUtf8): ?array
    {
        $preCollapse = self::preCollapseNormalize($asUtf8);
        $content = self::normalizeFileContentString($asUtf8);

        $meta = self::locateHeaderByPhysicalLineScan($content)
            ?? self::locateHeaderByUtf8MarkerLine($content)
            ?? self::locateHeaderByScanningFgetcsv($content, 50_000);

        if ($meta === null) {
            $meta = self::locateHeaderByPhysicalLineScan($preCollapse)
                ?? self::locateHeaderByUtf8MarkerLine($preCollapse)
                ?? self::locateHeaderByScanningFgetcsv($preCollapse, 50_000);
            if ($meta !== null) {
                if (isset($meta['_header_physical_line'])) {
                    $meta = self::relocateHeaderMetaUsingPhysicalLine($content, $meta)
                        ?? self::locateHeaderByPhysicalLineScan($content)
                        ?? self::locateHeaderByUtf8MarkerLine($content)
                        ?? self::locateHeaderByScanningFgetcsv($content, 50_000);
                } else {
                    $pos = $meta['after_byte_pos'];
                    $prefixOk = $pos <= strlen($preCollapse) && $pos <= strlen($content)
                        && substr($preCollapse, 0, $pos) === substr($content, 0, $pos);
                    if (! $prefixOk) {
                        $meta = self::locateHeaderByPhysicalLineScan($content)
                            ?? self::locateHeaderByScanningFgetcsv($content, 50_000)
                            ?? $meta;
                    }
                }
            }
        }

        if ($meta === null) {
            return null;
        }

        return ['content' => $content, 'meta' => $meta];
    }

    /**
     * Без UTF-16 (его обрабатывает {@see convertFileBytesToUtf8Text}).
     */
    private static function tryDecodeRawBytesToUtf8Cp1251(string $raw): ?string
    {
        if (str_starts_with($raw, "\xFF\xFE") || str_starts_with($raw, "\xFE\xFF")) {
            return null;
        }
        $body = $raw;
        if (str_starts_with($body, "\xEF\xBB\xBF")) {
            $body = substr($body, 3);
        }
        $converted = @mb_convert_encoding($body, 'UTF-8', 'Windows-1251');
        if (! is_string($converted) || $converted === '') {
            return null;
        }

        return $converted;
    }

    /**
     * @return array{delimiter: string, after_byte_pos: int, header: list<string>}|null
     */
    private static function locateHeaderInNormalizedContent(string $content): ?array
    {
        return self::locateHeaderByPhysicalLineScan($content)
            ?? self::locateHeaderByScanningFgetcsv($content)
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
        foreach ([',', ';', "\t", '|'] as $delimiter) {
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
     * Построчный поиск (по символу \\n): не зависит от fgetcsv и не гоняет regex по всему 14+ МБ файла.
     *
     * @return array{delimiter: string, after_byte_pos: int, header: list<string>, _header_physical_line?: string}|null
     */
    private static function locateHeaderByPhysicalLineScan(string $content, int $maxPhysicalLines = 30_000): ?array
    {
        $len = strlen($content);
        $pos = 0;
        for ($lineNum = 0; $lineNum < $maxPhysicalLines && $pos < $len; $lineNum++) {
            $lineEnd = strpos($content, "\n", $pos);
            $line = $lineEnd === false
                ? substr($content, $pos)
                : substr($content, $pos, $lineEnd - $pos);
            $afterPos = $lineEnd === false ? $len : $lineEnd + 1;

            // Не полагаемся на preg /u по строке: при «битом» UTF-8 внутри той же строки PCRE может не совпасть,
            // хотя колонки «Код»/«Артикул» в UTF-8 есть. Достаточно подстрок + str_getcsv по разделителям.
            $utf8Kod = "\xD0\x9A\xD0\xBE\xD0\xB4";
            $utf8Artikul = "\xD0\x90\xD1\x80\xD1\x82\xD0\xB8\xD0\xBA\xD1\x83\xD0\xBB";
            $hasKod = str_contains($line, 'Код') || str_contains($line, $utf8Kod);
            $hasArtikul = str_contains($line, 'Артикул') || str_contains($line, $utf8Artikul);
            if (! $hasKod || ! $hasArtikul) {
                $pos = $afterPos;

                continue;
            }

            $tryDelims = [',', ';', "\t", '|'];
            foreach ($tryDelims as $d) {
                $row = self::strGetCsvRow($line, $d);
                if ($row === []) {
                    continue;
                }
                $normalized = array_map(fn ($c) => self::normalizeCsvHeaderCell($c), $row);
                if (self::parsedRowIsRemainsTableHeader($normalized)) {
                    return [
                        'delimiter' => $d,
                        'after_byte_pos' => $afterPos,
                        'header' => $normalized,
                        '_header_physical_line' => $line,
                    ];
                }
            }

            $pos = $afterPos;
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

        $headLen = min(16 * 1048576, strlen($content));

        foreach ($regexAttempts as [$delimiter, $pattern]) {
            $haystacks = strlen($content) <= $headLen
                ? [$content]
                : [substr($content, 0, $headLen), $content];

            foreach ($haystacks as $hay) {
                if (@preg_match($pattern, $hay, $m, PREG_OFFSET_CAPTURE) !== 1) {
                    continue;
                }

                $meta = self::extractHeaderLineMetaFromMatch($content, (int) $m[0][1], $delimiter);
                if ($meta !== null) {
                    return $meta;
                }
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
            if (self::headerCellIsKod($cell)) {
                $hasKod = true;
            }
            if (self::headerCellIsArtikul($cell)) {
                $hasArtikul = true;
            }
        }

        return $hasKod && $hasArtikul;
    }

    /**
     * Колонка «Код» / «Код номенклатуры» и т.п. (не путать со «Штрихкод» — не начинается с «код» как отдельное слово в начале для коротких форм).
     */
    private static function headerCellIsKod(string $cell): bool
    {
        $n = self::normalizeHeaderLabelForMatch($cell);
        if ($n === '') {
            return false;
        }
        $lc = mb_strtolower($n, 'UTF-8');

        if ($lc === 'код' || (preg_match('/^код(\s|$)/u', $lc) === 1 && mb_strlen($n) <= 64)) {
            return true;
        }

        $ascii = strtolower(preg_replace('/\s+/u', ' ', $n) ?? $n);

        return $ascii === 'code' || (preg_match('/^code(\s|$)/i', $ascii) === 1 && strlen($ascii) <= 64);
    }

    private static function headerCellIsArtikul(string $cell): bool
    {
        $n = self::normalizeHeaderLabelForMatch($cell);
        if ($n === '') {
            return false;
        }
        $lc = mb_strtolower($n, 'UTF-8');

        if ($lc === 'артикул' || (preg_match('/^артикул(\s|$)/u', $lc) === 1 && mb_strlen($n) <= 64)) {
            return true;
        }

        $ascii = strtolower(preg_replace('/\s+/u', ' ', $n) ?? $n);

        if ($ascii === 'article' || (preg_match('/^article(\s|$)/i', $ascii) === 1 && strlen($ascii) <= 64)) {
            return true;
        }

        if ($ascii === 'sku' || $ascii === 'part number' || $ascii === 'part no' || $ascii === 'part no.') {
            return true;
        }

        return preg_match('/^part\s+number(\s|$)/i', $ascii) === 1 && strlen($ascii) <= 64;
    }

    private static function normalizeHeaderLabelForMatch(string $cell): string
    {
        $s = trim((string) $cell);
        $s = str_replace("\xC2\xA0", ' ', $s);
        $s = preg_replace('/[\x{200B}\x{FEFF}]/u', '', $s) ?? $s;
        $s = trim($s);
        $s = preg_replace('/[:：]$/u', '', $s) ?? $s;

        return trim($s);
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
