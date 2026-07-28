<?php
declare(strict_types=1);

/**
 * Basit OOXML (.xlsx) — tek çalışma sayfası, tüm hücreler metin (inlineStr).
 */

function cargo_generic_xlsx_col_letter(int $zeroBasedIndex): string
{
    $n = $zeroBasedIndex + 1;
    $s = '';
    while ($n > 0) {
        $m = ($n - 1) % 26;
        $s = chr(65 + $m) . $s;
        $n = intdiv($n - 1, 26);
    }

    return $s;
}

/** XML 1.0 yasak karakterleri kaldır */
function cargo_generic_xlsx_strip_illegal(?string $str): string
{
    if ($str === null || $str === '') {
        return '';
    }

    return preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', (string)$str) ?? '';
}

/** @param list<string|int|float|null> $cells */
function cargo_generic_cell_xml(string $ref, mixed $value): string
{
    $s = cargo_generic_xlsx_strip_illegal(is_scalar($value) || $value === null ? (string)$value : '');

    return '<c r="' . htmlspecialchars($ref, ENT_XML1 | ENT_QUOTES, 'UTF-8') . '" t="inlineStr"><is><t>' . htmlspecialchars($s, ENT_XML1 | ENT_QUOTES, 'UTF-8') . '</t></is></c>';
}

/**
 * @param list<string> $headers
 * @param list<list<mixed>> $rows
 */
function cargo_emit_table_xlsx(
    array $headers,
    array $rows,
    string $sheetTitle = 'Veri',
    string $downloadBaseFilename = 'export'
): void {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    $titled = preg_replace('/[\[\]\*\/\\\?:]/u', '', $sheetTitle);
    if ($titled === '' || $titled === null) {
        $titled = 'Sayfa';
    }
    $sheetTitleSafe = function_exists('mb_substr')
        ? mb_substr((string)$titled, 0, 31, 'UTF-8')
        : substr((string)$titled, 0, 31);
    $fnBase = preg_replace('/[^a-zA-Z0-9_-]/', '_', $downloadBaseFilename) ?: 'export';
    $colCount = count($headers);

    $sheetData = '<row r="1">';
    foreach ($headers as $i => $h) {
        $ref = cargo_generic_xlsx_col_letter($i) . '1';
        $sheetData .= cargo_generic_cell_xml($ref, $h);
    }
    $sheetData .= '</row>';

    $rnum = 2;
    foreach ($rows as $row) {
        if (!is_array($row)) {
            continue;
        }
        $sheetData .= '<row r="' . $rnum . '">';
        for ($ci = 0; $ci < $colCount; $ci++) {
            $ref = cargo_generic_xlsx_col_letter($ci) . (string)$rnum;
            $sheetData .= cargo_generic_cell_xml($ref, $row[$ci] ?? '');
        }
        $sheetData .= '</row>';
        $rnum++;
    }

    $tmpFile = tempnam(sys_get_temp_dir(), 'tblxlsx');
    if ($tmpFile === false) {
        header('HTTP/1.1 500 Internal Server Error');
        exit('Geçici dosya oluşturulamadı.');
    }
    unlink($tmpFile);

    $zip = new ZipArchive();
    $flags = ZipArchive::CREATE;
    if (defined('ZipArchive::OVERWRITE')) {
        $flags |= ZipArchive::OVERWRITE;
    }
    if ($zip->open($tmpFile, $flags) !== true) {
        header('HTTP/1.1 500 Internal Server Error');
        exit('Excel arşivi yazılamadı.');
    }

    $contentTypes = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
<Default Extension="xml" ContentType="application/xml"/>
<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>
<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>
</Types>';
    $zip->addFromString('[Content_Types].xml', $contentTypes);

    $rels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>
</Relationships>';
    $zip->addFromString('_rels/.rels', $rels);

    $escapedTitle = htmlspecialchars($sheetTitleSafe, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    $workbook = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
<sheets><sheet name="' . $escapedTitle . '" sheetId="1" r:id="rId1"/></sheets>
</workbook>';
    $zip->addFromString('xl/workbook.xml', $workbook);

    $workbookRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>
<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>
</Relationships>';
    $zip->addFromString('xl/_rels/workbook.xml.rels', $workbookRels);

    $styles = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
<fonts count="1"><font><sz val="11"/><name val="Calibri"/></font></fonts>
<fills count="2"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill></fills>
<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>
<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>
<cellXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/></cellXfs>
</styleSheet>';
    $zip->addFromString('xl/styles.xml', $styles);

    $sheetHead = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
<sheetData>';

    $sheetFoot = '</sheetData></worksheet>';
    $zip->addFromString('xl/worksheets/sheet1.xml', $sheetHead . $sheetData . $sheetFoot);

    if ($zip->close() !== true) {
        unlink($tmpFile);
        header('HTTP/1.1 500 Internal Server Error');
        exit('Excel arşivi kapatılamadı.');
    }

    $filename = $fnBase . '_' . date('Y-m-d_H-i-s') . '.xlsx';
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-store, must-revalidate');
    $flen = filesize($tmpFile);
    if ($flen !== false) {
        header('Content-Length: ' . $flen);
    }
    readfile($tmpFile);
    unlink($tmpFile);
    exit;
}
