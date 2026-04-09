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
     * Первые N физических строк файла в UTF-8 (для {@see \App\Console\Commands\RemainsCsvInspectCommand}).
     *
     * @return list<string>
     */
    public static function diagnosticUtf8Lines(string $absolutePath, ?string $csvEncoding, int $maxLines = 15): array
    {
        $raw = file_get_contents($absolutePath);
        if ($raw === false || $raw === '') {
            return ['(файл пуст или не прочитан)'];
        }

        $tryUtf = static fn (): string => self::convertFileBytesToUtf8Text($raw);
        $tryCp = static function () use ($raw): string {
            $t = self::tryDecodeRawBytesToUtf8Cp1251($raw);
            if ($t === null) {
                throw new \RuntimeException('cp1251');
            }

            return $t;
        };

        $chains = match ($csvEncoding) {
            'cp1251' => [$tryCp, $tryUtf],
            'utf-8' => [$tryUtf, $tryCp],
            default => [$tryUtf, $tryCp],
        };

        $text = '';
        foreach ($chains as $getUtf) {
            try {
                $text = $getUtf();
                break;
            } catch (\Throwable) {
                continue;
            }
        }
        if ($text === '') {
            $text = $tryUtf();
        }

        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $lines = explode("\n", $text);

        return array_slice(array_map(static fn ($l) => (string) $l, $lines), 0, max(1, $maxLines));
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
                'После разбора CSV не найдена строка заголовка: нужны «Код»+«Артикул» или «Артикул»+«Наименование»/«Номенклатура» '.
                '(либо Code/Article/Name и т.п.). '.
                'Сохраните как «CSV UTF-8» в Excel / LibreOffice. Разделители: запятая, точка с запятой, табуляция, |. '.
                'Попробуйте php artisan remains-csv:inspect "ваш.csv". '
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
        $pre = self::preCollapseNormalize($asUtf8);
        $pre = self::repairRemainsHeaderAfterUtf8KodArtikulMarker($pre);
        $pre = self::stitchRemainsBrokenHeaderLinePair($pre);
        $preCollapsed = self::mergeRemainsTypicalMultilineQuotedFields($pre);
        $strict = self::normalizeFileContentString($asUtf8);

        // Важно: шапка часто разбита на две физические строки из‑за переноса внутри кавычек
        // («Сумма» + newline + «себестоимости»). Якорь ,Код,Артикул, + fgetcsv с позиции строки — надёжнее полного скана.
        $variants = [
            ['preCollapsed', $preCollapsed],
            ['strict', $strict],
        ];

        foreach ($variants as [, $blob]) {
            $meta = self::locateHeaderByAnchorNeedleAndFgetcsv($blob)
                ?? self::locateHeaderByScanningFgetcsv($blob, 50_000)
                ?? self::locateHeaderByPhysicalLineScan($blob)
                ?? self::locateHeaderByUtf8MarkerLine($blob);
            if ($meta !== null) {
                return ['content' => $blob, 'meta' => $meta];
            }
        }

        return null;
    }

    /**
     * Ищет пару соседних физических строк (шапка «Остатки»), склеивает поле «Сумма/себестоимости» в одну строку.
     * Не зависит от успеха глобального merge по всему файлу — замена по точному совпадению фрагмента.
     */
    private static function stitchRemainsBrokenHeaderLinePair(string $content): string
    {
        $normalized = str_replace("\r", '', $content);
        $lines = explode("\n", $normalized);
        $max = min(80, count($lines));
        $offsets = self::byteLineStartOffsets($lines);
        for ($i = 0; $i + 1 < $max; $i++) {
            $a = $lines[$i];
            $b = $lines[$i + 1];
            if (strpos($a, 'Код') === false || strpos($a, 'Артикул') === false) {
                continue;
            }
            if (preg_match('/^\s*себестоимости/u', $b) !== 1) {
                continue;
            }
            if (preg_match('/Сумма\s*$/u', $a) !== 1) {
                continue;
            }
            $pair = $a."\n".$b;
            $fixed = self::collapseRemainsSumCostTwoLineFragment($pair);
            if ($fixed === $pair) {
                continue;
            }
            $start = $offsets[$i];
            if (isset($offsets[$i + 2])) {
                $end = $offsets[$i + 2];

                return substr($normalized, 0, $start).$fixed."\n".substr($normalized, $end);
            }

            return substr($normalized, 0, $start).$fixed;
        }

        return $normalized;
    }

    /**
     * Находит в файле UTF-8 последовательность «,Код,Артикул,» и чинит типичный разрыв «'Сумма» + перевод строки + «себестоимости'» в первых двух строках шапки.
     * Не использует литералы «Код» из исходников — только байты UTF-8 (на случай странностей окружения).
     */
    private static function repairRemainsHeaderAfterUtf8KodArtikulMarker(string $content): string
    {
        $marker = ",\xD0\x9A\xD0\xBE\xD0\xB4\x2C\xD0\x90\xD1\x80\xD1\x82\xD0\xB8\xD0\xBA\xD1\x83\xD0\xBB\x2C";
        $p = strpos($content, $marker);
        if ($p === false) {
            return $content;
        }

        $lineStart = strrpos(substr($content, 0, $p), "\n");
        $lineStart = $lineStart === false ? 0 : $lineStart + 1;
        $e1 = strpos($content, "\n", $lineStart);
        if ($e1 === false) {
            return $content;
        }
        $e2 = strpos($content, "\n", $e1 + 1);
        $blockEnd = $e2 === false ? strlen($content) : $e2;
        $block = substr($content, $lineStart, $blockEnd - $lineStart);

        $fixed = self::collapseRemainsSumCostTwoLineFragment($block);
        if ($fixed === $block) {
            $fixed = preg_replace(
                '/[\'\x{2018}\x{2019}]\s*Сумма\s*\R+\s*себестоимости\s*[\'\x{2018}\x{2019}]/u',
                '"Сумма себестоимости"',
                $block
            ) ?? $block;
        }
        if ($fixed === $block) {
            return $content;
        }

        return substr($content, 0, $lineStart).$fixed.substr($content, $lineStart + strlen($block));
    }

    private static function collapseRemainsSumCostTwoLineFragment(string $pair): string
    {
        // Якорь по байтам UTF-8: ' + «Сумма» + перевод строки + «себестоимости» + '.
        // Важно: шаблон в двойных кавычках PHP — иначе в одинарных \xDD не станет байтом и совпадений не будет.
        $s = preg_replace(
            "/'\xD0\xA1\xD1\x83\xD0\xBC\xD0\xBC\xD0\xB0(?:\r\n|\n|\r)\xD1\x81\xD0\xB5\xD0\xB1\xD0\xB5\xD1\x81\xD1\x82\xD0\xBE\xD0\xB8\xD0\xBC\xD0\xBE\xD1\x81\xD1\x82\xD0\xB8'/u",
            '"Сумма себестоимости"',
            $pair
        ) ?? $pair;
        if ($s !== $pair) {
            return $s;
        }

        foreach (["'Сумма\nсебестоимости'", "'Сумма\r\nсебестоимости'", "'Сумма\rсебестоимости'"] as $lit) {
            if (str_contains($pair, $lit)) {
                return str_replace($lit, '"Сумма себестоимости"', $pair);
            }
        }

        $s = preg_replace(
            '/[\'\x{2018}\x{2019}]\s*Сумма\s*\R+\s*себестоимости\s*[\'\x{2018}\x{2019}]/u',
            '"Сумма себестоимости"',
            $pair
        ) ?? $pair;
        if ($s !== $pair) {
            return $s;
        }

        // Запятая ASCII или fullwidth U+FF0C (Excel) после закрывающей кавычки.
        $s = preg_replace(
            '/Себестоимость\s*,\s*.\s*Сумма\s*\R+\s*себестоимости.\s*(?=\s*[,\x{FF0C}])/u',
            'Себестоимость,"Сумма себестоимости"',
            $pair
        ) ?? $pair;
        if ($s !== $pair) {
            return $s;
        }
        $s = preg_replace(
            '/Себестоимость\s*,\s*Сумма\s*\R+\s*себестоимости\s*(?=\s*[,\x{FF0C}])/u',
            'Себестоимость,"Сумма себестоимости"',
            $pair
        ) ?? $pair;
        if ($s !== $pair) {
            return $s;
        }
        $s = preg_replace(
            '/[\'\x{2018}\x{2019}]Сумма\s*\R+\s*себестоимости[\'\x{2018}\x{2019}]/u',
            '"Сумма себестоимости"',
            $pair
        ) ?? $pair;

        return $s;
    }

    /**
     * @param  list<string>  $lines
     * @return list<int>
     */
    private static function byteLineStartOffsets(array $lines): array
    {
        $offsets = [];
        $p = 0;
        $n = count($lines);
        for ($k = 0; $k < $n; $k++) {
            $offsets[$k] = $p;
            $p += strlen($lines[$k]);
            if ($k + 1 < $n) {
                $p += 1;
            }
        }

        return $offsets;
    }

    /**
     * Типичный баг выгрузки «Остатки»: перенос строки внутри "Сумма … себестоимости" в шапке.
     * Должно выполняться до {@see collapseNewlinesInsideDoubleQuotedFields}.
     */
    private static function mergeRemainsTypicalMultilineQuotedFields(string $content): string
    {
        // Типичный баг 1С: строка шапки обрывается на «…Себестоимость,"Сумма», продолжение на следующей строке.
        // LibreOffice часто сохраняет поле в одинарных кавычках '…' — для fgetcsv важны двойные "…".
        // Без склейки fgetcsv тянет «строку» до EOF — заголовок не находится.
        $content = self::joinBrokenRemainsSumCostSemantic($content);

        // Любые кавычки (ASCII/U+2019 и т.д.) — по одному символу до/после «Сумма»/«себестоимости».
        $content = preg_replace(
            '/Себестоимость\s*,\s*.\s*Сумма\s*\R+\s*себестоимости.\s*(?=\s*[,\x{FF0C}])/u',
            'Себестоимость,"Сумма себестоимости"',
            $content
        ) ?? $content;
        $content = preg_replace(
            '/Себестоимость\s*,\s*Сумма\s*\R+\s*себестоимости\s*(?=\s*[,\x{FF0C}])/u',
            'Себестоимость,"Сумма себестоимости"',
            $content
        ) ?? $content;

        $content = self::joinBrokenSumCostQuotedLines($content);

        $content = preg_replace('/Себестоимость\s*,\s*"Сумма\s*\R\s*себестоимости"/ui', 'Себестоимость,"Сумма себестоимости"', $content) ?? $content;
        $content = preg_replace('/Себестоимость\s*,\s*\'Сумма\s*\R\s*себестоимости\'/ui', 'Себестоимость,"Сумма себестоимости"', $content) ?? $content;
        $content = preg_replace('/"Сумма\h*\R\h*себестоимости"/u', '"Сумма себестоимости"', $content) ?? $content;
        $content = preg_replace('/\'Сумма\h*\R\h*себестоимости\'/u', '"Сумма себестоимости"', $content) ?? $content;
        $content = preg_replace('/"Сумма\s*\R+\s*себестоимости"/u', '"Сумма себестоимости"', $content) ?? $content;
        $content = preg_replace('/\'Сумма\s*\R+\s*себестоимости\'/u', '"Сумма себестоимости"', $content) ?? $content;
        $content = preg_replace('/"Сумма\s*\/\s*себестоимости"/u', '"Сумма себестоимости"', $content) ?? $content;
        $content = preg_replace('/"Сумма\s+себестоимости"/u', '"Сумма себестоимости"', $content) ?? $content;
        $content = preg_replace('/Себестоимость\s*,\s*\'Сумма\s+себестоимости\'/u', 'Себестоимость,"Сумма себестоимости"', $content) ?? $content;
        foreach (["\r\n", "\r", "\n"] as $nl) {
            $content = str_replace('"Сумма'.$nl.'себестоимости"', '"Сумма себестоимости"', $content);
            $content = str_replace("'Сумма".$nl."себестоимости'", '"Сумма себестоимости"', $content);
        }

        $content = self::bruteMergeFirstSumCostQuotedFieldAcrossNewlines($content);

        return $content;
    }

    /**
     * Последняя линия защиты: между "Сумма и себестоимости" только пробелы/переносы — склеиваем без preg (на случай невидимых байтов).
     */
    private static function bruteMergeFirstSumCostQuotedFieldAcrossNewlines(string $content): string
    {
        foreach ([['"Сумма', 'себестоимости"'], ["'Сумма", "себестоимости'"]] as [$open, $close]) {
            $p = strpos($content, $open);
            if ($p === false) {
                continue;
            }

            $afterOpen = $p + strlen($open);
            $q = strpos($content, $close, $afterOpen);
            if ($q === false) {
                continue;
            }

            $between = substr($content, $afterOpen, $q - $afterOpen);
            if ($between !== '' && ! preg_match('/^\s+$/u', $between)) {
                continue;
            }

            $merged = '"Сумма себестоимости"';

            return substr($content, 0, $p).$merged.substr($content, $q + strlen($close));
        }

        return $content;
    }

    /**
     * Разрыв шапки без надёжных ASCII-кавычек: строка с «Себестоимость» заканчивается на «Сумма», следующая начинается с «себестоимости».
     */
    private static function joinBrokenRemainsSumCostSemantic(string $content): string
    {
        $lines = explode("\n", str_replace("\r", '', $content));
        $out = [];
        for ($i = 0, $n = count($lines); $i < $n; $i++) {
            $line = $lines[$i];
            $next = $lines[$i + 1] ?? '';
            if (
                $i + 1 < $n
                && str_contains($line, 'Себестоимость')
                && preg_match('/Сумма\s*$/u', $line)
                && preg_match('/^\s*себестоимости/u', $next)
            ) {
                $line .= "\n".$lines[++$i];
            }
            $out[] = $line;
        }

        return implode("\n", $out);
    }

    /**
     * Склеивает две физические строки, если первая заканчивается на открытую кавычку после «Сумма» (поле «Сумма себестоимости» разорвано).
     */
    private static function joinBrokenSumCostQuotedLines(string $content): string
    {
        $lines = explode("\n", str_replace("\r", '', $content));
        $out = [];
        for ($i = 0, $n = count($lines); $i < $n; $i++) {
            $line = $lines[$i];
            if ($i + 1 < $n && (preg_match('/"Сумма\s*$/u', $line) || preg_match('/\'Сумма\s*$/u', $line))) {
                $line .= "\n".$lines[$i + 1];
                $i++;
            }
            $out[] = $line;
        }

        return implode("\n", $out);
    }

    /**
     * Находит шапку по шаблону «,Код,Артикул,» (пробелы вокруг запятых допускаются), читает одну логическую запись fgetcsv — с переносами в кавычках.
     *
     * @return array{delimiter: string, after_byte_pos: int, header: list<string>}|null
     */
    private static function locateHeaderByAnchorNeedleAndFgetcsv(string $content): ?array
    {
        $patterns = [
            [',', '/,\s*Код\s*,\s*Артикул\s*,/u'],
            [';', '/;\s*Код\s*;\s*Артикул\s*;/u'],
            ["\t", '/\t\s*Код\s*\t\s*Артикул\s*\t/u'],
        ];

        foreach ($patterns as [$delimiter, $regex]) {
            if (preg_match($regex, $content, $m, PREG_OFFSET_CAPTURE) !== 1) {
                continue;
            }

            $at = (int) ($m[0][1] ?? -1);
            if ($at < 0) {
                continue;
            }

            $lineStart = strrpos(substr($content, 0, $at), "\n");
            $lineStart = $lineStart === false ? 0 : $lineStart + 1;
            $tail = substr($content, $lineStart);
            if ($tail === '') {
                continue;
            }

            $tail = self::stitchRemainsBrokenHeaderLinePair($tail);
            $tail = self::mergeRemainsTypicalMultilineQuotedFields($tail);

            $h = fopen('php://temp', 'r+b');
            if ($h === false) {
                continue;
            }
            fwrite($h, $tail);
            rewind($h);
            $row = self::readCsvRow($h, $delimiter);
            fclose($h);
            if ($row === false) {
                continue;
            }

            $normalized = array_map(fn ($c) => self::normalizeCsvHeaderCell($c), $row);
            if (! self::parsedRowIsRemainsTableHeader($normalized)) {
                continue;
            }

            $h = fopen('php://temp', 'r+b');
            if ($h === false) {
                continue;
            }
            fwrite($h, $tail);
            rewind($h);
            self::readCsvRow($h, $delimiter);
            $consumed = ftell($h);
            fclose($h);

            return [
                'delimiter' => $delimiter,
                'after_byte_pos' => $lineStart + (int) $consumed,
                'header' => $normalized,
            ];
        }

        return null;
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
        return self::locateHeaderByScanningFgetcsv($content, 50_000)
            ?? self::locateHeaderByPhysicalLineScan($content)
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
            $hasName = str_contains($line, 'Наименование')
                || str_contains($line, 'Номенклатура')
                || str_contains($line, 'Название');
            $looksLikeHeader = ($hasKod && $hasArtikul) || ($hasArtikul && $hasName);
            if (! $looksLikeHeader) {
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
        $afterBytePos = $lineEnd === false ? strlen($content) : $lineEnd + 1;
        $physicalLine = $line;

        if (! self::parsedRowIsRemainsTableHeader($normalized) && $lineEnd !== false) {
            $lineEnd2 = strpos($content, "\n", $lineEnd + 1);
            $nextLine = $lineEnd2 === false
                ? substr($content, $lineEnd + 1)
                : substr($content, $lineEnd + 1, $lineEnd2 - $lineEnd - 1);
            $extended = $line."\n".$nextLine;
            $fixed = self::collapseRemainsSumCostTwoLineFragment($extended);
            if ($fixed !== $extended) {
                $row = self::strGetCsvRow($fixed, $delimiter);
                if ($row !== []) {
                    $normalized = array_map(fn ($c) => self::normalizeCsvHeaderCell($c), $row);
                    $physicalLine = $fixed;
                    $afterBytePos = $lineEnd2 === false ? strlen($content) : $lineEnd2 + 1;
                }
            }
        }

        if (! self::parsedRowIsRemainsTableHeader($normalized)) {
            return null;
        }

        return [
            'delimiter' => $delimiter,
            'after_byte_pos' => $afterBytePos,
            'header' => $normalized,
            '_header_physical_line' => $physicalLine,
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
        $hasName = false;
        foreach ($cells as $cell) {
            if (self::headerCellIsKod($cell)) {
                $hasKod = true;
            }
            if (self::headerCellIsArtikul($cell)) {
                $hasArtikul = true;
            }
            if (self::headerCellIsNameColumn($cell)) {
                $hasName = true;
            }
        }

        // Классический отчёт: «Код» + «Артикул». Часто в 1С/Excel: «Артикул» + «Наименование» без колонки «Код».
        $ok = ($hasKod && $hasArtikul) || ($hasArtikul && $hasName);

        return $ok || self::rowLooksLikeRemainsTableHeaderLoose($cells);
    }

    /**
     * Колонка наименования номенклатуры (типичная шапка «Остатки» без поля «Код»).
     */
    private static function headerCellIsNameColumn(string $cell): bool
    {
        $n = self::normalizeHeaderLabelForMatch($cell);
        if ($n === '') {
            return false;
        }
        $lc = mb_strtolower($n, 'UTF-8');

        if ($lc === 'наименование' || (preg_match('/^наименование(\s|$)/u', $lc) === 1 && mb_strlen($n) <= 80)) {
            return true;
        }
        if ($lc === 'номенклатура' || (preg_match('/^номенклатура(\s|$)/u', $lc) === 1 && mb_strlen($n) <= 80)) {
            return true;
        }
        if ($lc === 'название' || (preg_match('/^название(\s|$)/u', $lc) === 1 && mb_strlen($n) <= 64)) {
            return true;
        }

        $ascii = strtolower(preg_replace('/\s+/u', ' ', $n) ?? $n);

        return $ascii === 'name' || $ascii === 'description'
            || preg_match('/^(name|description|item)(\s|$)/i', $ascii) === 1;
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

        // «Артикул поставщика», «Артикул / код», одна ячейка
        if (preg_match('/^артикул\b/u', $lc) === 1 && mb_strlen($n) <= 96) {
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
        if (str_starts_with($s, "\xEF\xBB\xBF")) {
            $s = substr($s, 3);
        }
        $s = str_replace("\xC2\xA0", ' ', $s);
        $s = preg_replace('/[\x{200B}\x{FEFF}]/u', '', $s) ?? $s;
        $s = trim($s);
        $s = preg_replace('/[:：]$/u', '', $s) ?? $s;

        return trim($s);
    }

    /**
     * Запасная проверка шапки, если в ячейке остались невидимые символы (Excel / BOM).
     *
     * @param  list<string>  $cells
     */
    private static function rowLooksLikeRemainsTableHeaderLoose(array $cells): bool
    {
        $hasKod = false;
        $hasArtikul = false;
        $hasName = false;
        foreach ($cells as $cell) {
            $s = self::normalizeHeaderLabelForMatch((string) $cell);
            $lc = mb_strtolower($s, 'UTF-8');
            if ($lc === 'код' || preg_match('/^код(\s|$|[,;])/u', $lc) === 1) {
                $hasKod = true;
            }
            if ($lc === 'артикул' || preg_match('/^артикул(\s|$|[,;])/u', $lc) === 1) {
                $hasArtikul = true;
            }
            if (str_starts_with($lc, 'наименование') || $lc === 'номенклатура' || str_starts_with($lc, 'название')) {
                $hasName = true;
            }
        }

        return ($hasKod && $hasArtikul) || ($hasArtikul && $hasName);
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
        $content = str_replace("\u{200B}", '', $content);

        // LibreOffice/Excel: «кавычки» поля часто в U+2018/U+2019, а не в ASCII 0x27 — иначе склейка «Сумма/себестоимости» не срабатывает.
        $content = str_replace(
            ["\u{2018}", "\u{2019}", "\u{201A}", "\u{201B}", "\u{FF07}", "\u{02BC}", "\u{02B9}"],
            "'",
            $content
        );

        return str_replace(
            ["\xE2\x80\x9C", "\xE2\x80\x9D", "\xE2\x80\x9E", "\xC2\xAB", "\xC2\xBB"],
            '"',
            $content
        );
    }

    private static function normalizeFileContentString(string $bytes): string
    {
        $content = self::preCollapseNormalize($bytes);
        $content = self::repairRemainsHeaderAfterUtf8KodArtikulMarker($content);
        $content = self::stitchRemainsBrokenHeaderLinePair($content);
        $content = self::mergeRemainsTypicalMultilineQuotedFields($content);

        $content = self::collapseNewlinesInsideDoubleQuotedFields($content);

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
