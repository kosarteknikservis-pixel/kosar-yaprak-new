<?php
declare(strict_types=1);

/**
 * Panel (../panel/xnull/controller/aras_kargo_export.php) ile uyumlu Aras Kargo .xlsx üretimi.
 */

function cargo_aras_normalize_phone(?string $val): string
{
    $raw = trim((string)$val);
    if ($raw === '') {
        return '';
    }
    $digits = preg_replace('/\D+/', '', $raw);

    return $digits !== '' ? $digits : $raw;
}

/**
 * Panel export_helpers panel_export_strip_xml_illegal_chars karşılığı
 */
function cargo_aras_strip_xml_illegal(?string $str): string
{
    if ($str === null || $str === '') {
        return '';
    }
    $s = (string)$str;

    return preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', $s) ?? '';
}

/**
 * @param list<array{ad:string, adres:string, ilce:string, sehir:string, tel:string, tutar:int}> $rowsData
 */
function cargo_aras_emit_xlsx(array $rowsData, string $siteUrunCol): void
{
    /** İndirmeden önce gelen BOM/boşluk çıktılarını temizle (ZIP bozulmasını önler) */
    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    $headers = [
        'mok',
        'urun',
        'ad',
        'adres',
        'ilce',
        'sehir',
        'tel',
        'irsaliyeno',
        'ilkodu',
        'ilcekodu',
        'varis',
        'serino',
        'desi',
        'kg',
        'tahsilat tutarı',
        'ödeme tipi',
    ];

    $tmpFile = tempnam(sys_get_temp_dir(), 'arasxlsx');
    if ($tmpFile === false) {
        header('HTTP/1.1 500 Internal Server Error');
        exit('Geçici dosya oluşturulamadı.');
    }
    /**
     * tempnam'in oluşturduğu boş dosya bazı PHP/Zip kombinasyonlarında arşivi bozacak şekilde açılabilir.
     * Panel export ile aynı güvenilir yöntem: dosyayı kaldır, ZipArchive sıfırdan oluştursun.
     */
    unlink($tmpFile);

    $zip = new ZipArchive();
    $zipFlags = ZipArchive::CREATE;
    if (defined('ZipArchive::OVERWRITE')) {
        $zipFlags |= ZipArchive::OVERWRITE;
    }
    if ($zip->open($tmpFile, $zipFlags) !== true) {
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

    $workbook = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
<sheets><sheet name="Aras Kargo" sheetId="1" r:id="rId1"/></sheets>
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

    $sheetData = '<row r="1">';
    foreach ($headers as $i => $h) {
        $col = chr(65 + $i);
        $sheetData .= '<c r="' . $col . '1" t="inlineStr"><is><t>' . htmlspecialchars((string)$h, ENT_XML1 | ENT_QUOTES, 'UTF-8') . '</t></is></c>';
    }
    $sheetData .= '</row>';

    $rowCount = 2;
    foreach ($rowsData as $row) {
        $ad = cargo_aras_strip_xml_illegal($row['ad'] ?? '');
        $adres = cargo_aras_strip_xml_illegal($row['adres'] ?? '');
        $ilce = cargo_aras_strip_xml_illegal($row['ilce'] ?? '');
        $sehir = cargo_aras_strip_xml_illegal($row['sehir'] ?? '');
        $tel = cargo_aras_strip_xml_illegal($row['tel'] ?? '');
        $tel = cargo_aras_normalize_phone($tel);
        $urunDisp = cargo_aras_strip_xml_illegal($siteUrunCol);
        $tutar = max(0, (int)($row['tutar'] ?? 0));

        $sheetData .= '<row r="' . $rowCount . '">';
        $cells = [
            ['t' => 's', 'v' => '.'],
            ['t' => 's', 'v' => $urunDisp],
            ['t' => 's', 'v' => $ad],
            ['t' => 's', 'v' => $adres],
            ['t' => 's', 'v' => $ilce],
            ['t' => 's', 'v' => $sehir],
            ['t' => 's', 'v' => $tel],
            ['t' => 's', 'v' => '.'],
            ['t' => 's', 'v' => '.'],
            ['t' => 's', 'v' => '.'],
            ['t' => 's', 'v' => '.'],
            ['t' => 's', 'v' => '.'],
            ['t' => 'n', 'v' => '1'],
            ['t' => 'n', 'v' => '1'],
            ['t' => 'n', 'v' => (string)$tutar],
            ['t' => 's', 'v' => 'kredi'],
        ];
        foreach ($cells as $i => $c) {
            $col = chr(65 + $i);
            if ($c['t'] === 'n') {
                $sheetData .= '<c r="' . $col . $rowCount . '" t="n"><v>' . htmlspecialchars($c['v'], ENT_XML1 | ENT_QUOTES, 'UTF-8') . '</v></c>';
            } else {
                $sheetData .= '<c r="' . $col . $rowCount . '" t="inlineStr"><is><t>' . htmlspecialchars($c['v'], ENT_XML1 | ENT_QUOTES, 'UTF-8') . '</t></is></c>';
            }
        }
        $sheetData .= '</row>';
        $rowCount++;
    }

    $sheetFoot = '</sheetData></worksheet>';
    $zip->addFromString('xl/worksheets/sheet1.xml', $sheetHead . $sheetData . $sheetFoot);
    if ($zip->close() !== true) {
        unlink($tmpFile);
        header('HTTP/1.1 500 Internal Server Error');
        exit('Excel arşivi kapatılamadı.');
    }

    $filename = 'aras_kargo_' . date('Y-m-d_His') . '.xlsx';
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
