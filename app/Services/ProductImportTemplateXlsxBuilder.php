<?php

namespace App\Services;

use ZipArchive;

class ProductImportTemplateXlsxBuilder
{
    /**
     * @param  list<string>  $headers
     * @param  list<string>  $hints
     * @param  list<string>  $example
     */
    public function build(string $targetPath, array $headers, array $hints, array $example): void
    {
        if ($headers === []) {
            throw new \InvalidArgumentException('Список колонок шаблона пуст.');
        }

        if (count($hints) !== count($headers) || count($example) !== count($headers)) {
            throw new \InvalidArgumentException('Подсказки и пример должны совпадать по размеру с заголовками.');
        }

        $dir = dirname($targetPath);
        if (! is_dir($dir) && ! mkdir($dir, 0777, true) && ! is_dir($dir)) {
            throw new \RuntimeException('Не удалось создать директорию: '.$dir);
        }

        $rows = [$headers, $hints, $example];
        $sheetXml = $this->buildWorksheetXml($rows);
        $sharedStringsXml = $this->buildSharedStringsXml($rows);

        $zip = new ZipArchive;
        if ($zip->open($targetPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('Не удалось создать XLSX: '.$targetPath);
        }

        $zip->addFromString('[Content_Types].xml', $this->contentTypesXml());
        $zip->addFromString('_rels/.rels', $this->rootRelsXml());
        $zip->addFromString('xl/workbook.xml', $this->workbookXml());
        $zip->addFromString('xl/_rels/workbook.xml.rels', $this->workbookRelsXml());
        $zip->addFromString('xl/styles.xml', $this->stylesXml());
        $zip->addFromString('xl/sharedStrings.xml', $sharedStringsXml);
        $zip->addFromString('xl/worksheets/sheet1.xml', $sheetXml);
        $zip->close();
    }

    /**
     * @param  list<list<string>>  $rows
     */
    protected function buildWorksheetXml(array $rows): string
    {
        $cells = [];
        foreach ($rows as $rIdx => $row) {
            $rowNo = $rIdx + 1;
            $cellXml = '';
            foreach ($row as $cIdx => $unusedValue) {
                $ref = $this->columnName($cIdx).$rowNo;
                $sharedIndex = ($rIdx * count($row)) + $cIdx;
                $style = $rIdx === 0 ? ' s="1"' : '';
                $cellXml .= '<c r="'.$ref.'" t="s"'.$style.'><v>'.$sharedIndex.'</v></c>';
            }
            $cells[] = '<row r="'.$rowNo.'">'.$cellXml.'</row>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<sheetViews><sheetView workbookViewId="0"/></sheetViews>'
            .'<sheetFormatPr defaultRowHeight="15"/>'
            .'<cols><col min="1" max="6" width="18" customWidth="1"/><col min="7" max="12" width="22" customWidth="1"/><col min="13" max="24" width="28" customWidth="1"/></cols>'
            .'<sheetData>'.implode('', $cells).'</sheetData>'
            .'</worksheet>';
    }

    /**
     * @param  list<list<string>>  $rows
     */
    protected function buildSharedStringsXml(array $rows): string
    {
        $flat = [];
        foreach ($rows as $row) {
            foreach ($row as $val) {
                $flat[] = $val;
            }
        }

        $si = implode('', array_map(fn (string $v): string => '<si><t>'.$this->xmlEscape($v).'</t></si>', $flat));
        $count = count($flat);

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" count="'.$count.'" uniqueCount="'.$count.'">'
            .$si
            .'</sst>';
    }

    protected function contentTypesXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            .'<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            .'<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            .'<Override PartName="/xl/sharedStrings.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sharedStrings+xml"/>'
            .'</Types>';
    }

    protected function rootRelsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            .'</Relationships>';
    }

    protected function workbookXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" '
            .'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<sheets><sheet name="products_import_template" sheetId="1" r:id="rId1"/></sheets>'
            .'</workbook>';
    }

    protected function workbookRelsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            .'<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            .'<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/sharedStrings" Target="sharedStrings.xml"/>'
            .'</Relationships>';
    }

    protected function stylesXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/></font></fonts>'
            .'<fills count="2"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill></fills>'
            .'<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
            .'<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            .'<cellXfs count="2"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            .'<xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/></cellXfs>'
            .'</styleSheet>';
    }

    protected function columnName(int $index): string
    {
        $name = '';
        $i = $index + 1;
        while ($i > 0) {
            $mod = ($i - 1) % 26;
            $name = chr(65 + $mod).$name;
            $i = intdiv($i - 1, 26);
        }

        return $name;
    }

    protected function xmlEscape(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}

