<?php
/**
 * ============================================================================
 * SPREADSHEET HANDLER (XLSX) — АВТОНОМНЫЙ ОБРАБОТЧИК ТАБЛИЦ ЯНДЕКС / EXCEL
 * ============================================================================
 * Позволяет создавать, читать и дописывать строки в таблицы формата .xlsx
 * без необходимости установки сторонних тяжелых библиотек через Composer.
 * Использует встроенный PHP ZipArchive и XML.
 */

if (!defined('APP_INIT')) {
    define('APP_INIT', true);
}

class SpreadsheetHandler
{
    /**
     * Создание новой таблицы брифов по умолчанию
     */
    public static function createDefaultBriefsFile(string $filePath): bool
    {
        $headers = [
            'Дата и время',
            'Статус',
            'Название бизнеса',
            'Имя клиента',
            'MAX / Telegram / Контакты',
            'Город',
            'Ниша / Услуги',
            'Задачи сайта',
            'Текущие сложности',
            'Яндекс Карты / 2ГИС',
            'VK / Соцсети',
            'Материалы (Фото)',
            'Сроки запуска',
            'Комментарий',
            'Источник'
        ];

        return self::createSimpleXlsx($filePath, 'Брифы', $headers);
    }

    /**
     * Создание новой таблицы расписания и записей (по образцу студии)
     */
    public static function createDefaultScheduleFile(string $filePath): bool
    {
        $scheduleHeaders = ['Направление', 'Дата', 'Время', 'Преподаватель', 'Всего мест', 'Занято мест', 'Осталось мест', 'Тип'];
        $bookingsHeaders = ['Дата записи', 'Имя', 'Телефон', 'Направление', 'Дата занятия', 'Время занятия', 'Запрос (Цель)', 'Источник'];

        return self::createTwoSheetXlsx(
            $filePath,
            'Расписание', $scheduleHeaders,
            'Записи', $bookingsHeaders
        );
    }

    /**
     * Добавление новой строки в лист .xlsx файла
     */
    public static function appendRow(string $xlsxPath, array $rowData, string $sheetName = 'Sheet1'): bool
    {
        if (!class_exists('ZipArchive')) {
            error_log('SpreadsheetHandler: ZipArchive extension is missing.');
            return false;
        }

        $zip = new ZipArchive();
        if ($zip->open($xlsxPath) !== true) {
            return false;
        }

        // Находим нужный лист в xl/worksheets/
        $sheetXmlFile = 'xl/worksheets/sheet1.xml';
        if ($sheetName === 'Записи') {
            // Если в книге 2 листа и нужен лист Записи (обычно sheet2.xml)
            if ($zip->locateName('xl/worksheets/sheet2.xml') !== false) {
                $sheetXmlFile = 'xl/worksheets/sheet2.xml';
            }
        }

        $sheetXml = $zip->getFromName($sheetXmlFile);
        if (!$sheetXml) {
            $zip->close();
            return false;
        }

        // Читаем sharedStrings если есть
        $sharedStringsXml = $zip->getFromName('xl/sharedStrings.xml');
        
        // Парсим XML листа
        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = true;
        @$dom->loadXML($sheetXml);

        $sheetDataList = $dom->getElementsByTagName('sheetData');
        if ($sheetDataList->length === 0) {
            $zip->close();
            return false;
        }
        $sheetData = $sheetDataList->item(0);

        // Находим максимальный номер строки
        $rows = $sheetData->getElementsByTagName('row');
        $maxRowIndex = 0;
        foreach ($rows as $r) {
            $rNum = (int)$r->getAttribute('r');
            if ($rNum > $maxRowIndex) {
                $maxRowIndex = $rNum;
            }
        }

        $newRowIndex = $maxRowIndex + 1;
        $newRow = $dom->createElement('row');
        $newRow->setAttribute('r', (string)$newRowIndex);

        $colLetters = ['A','B','C','D','E','F','G','H','I','J','K','L','M','N','O','P','Q','R','S','T','U','V','W','X','Y','Z'];

        $cIdx = 0;
        foreach ($rowData as $val) {
            $colLetter = $colLetters[$cIdx] ?? 'A';
            $cellRef = $colLetter . $newRowIndex;

            $cell = $dom->createElement('c');
            $cell->setAttribute('r', $cellRef);
            $cell->setAttribute('t', 'inlineStr');

            $is = $dom->createElement('is');
            $t = $dom->createElement('t');
            $t->nodeValue = htmlspecialchars((string)$val, ENT_XML1, 'UTF-8');

            $is->appendChild($t);
            $cell->appendChild($is);
            $newRow->appendChild($cell);
            $cIdx++;
        }

        $sheetData->appendChild($newRow);

        // Обновляем dimensions
        $dimList = $dom->getElementsByTagName('dimension');
        if ($dimList->length > 0) {
            $dimList->item(0)->setAttribute('ref', 'A1:' . ($colLetters[max(0, count($rowData)-1)]) . $newRowIndex);
        }

        $zip->addFromString($sheetXmlFile, $dom->saveXML());
        $zip->close();

        return true;
    }

    /**
     * Чтение строк листа расписания
     */
    public static function readSheetRows(string $xlsxPath, string $sheetXmlFile = 'xl/worksheets/sheet1.xml'): array
    {
        if (!class_exists('ZipArchive') || !file_exists($xlsxPath)) {
            return [];
        }

        $zip = new ZipArchive();
        if ($zip->open($xlsxPath) !== true) {
            return [];
        }

        // Загружаем общие строки (sharedStrings)
        $shared = [];
        $sharedXml = $zip->getFromName('xl/sharedStrings.xml');
        if ($sharedXml) {
            $sDom = new DOMDocument();
            if (@$sDom->loadXML($sharedXml)) {
                $siList = $sDom->getElementsByTagName('si');
                foreach ($siList as $si) {
                    $tTags = $si->getElementsByTagName('t');
                    $val = '';
                    foreach ($tTags as $t) {
                        $val .= $t->nodeValue;
                    }
                    $shared[] = $val;
                }
            }
        }

        $sheetXml = $zip->getFromName($sheetXmlFile);
        $zip->close();

        if (!$sheetXml) return [];

        $dom = new DOMDocument();
        if (!@$dom->loadXML($sheetXml)) return [];

        $rows = [];
        $rowNodes = $dom->getElementsByTagName('row');

        foreach ($rowNodes as $rNode) {
            $rowNum = (int)$rNode->getAttribute('r');
            $cNodes = $rNode->getElementsByTagName('c');
            $rowArr = [];

            foreach ($cNodes as $cNode) {
                $type = $cNode->getAttribute('t');
                $ref = $cNode->getAttribute('r'); // e.g. A1, B2
                
                $val = '';
                $vNode = $cNode->getElementsByTagName('v')->item(0);
                $isNode = $cNode->getElementsByTagName('is')->item(0);

                if ($type === 's' && $vNode) {
                    $idx = (int)$vNode->nodeValue;
                    $val = $shared[$idx] ?? '';
                } elseif ($type === 'inlineStr' && $isNode) {
                    $tNode = $isNode->getElementsByTagName('t')->item(0);
                    $val = $tNode ? $tNode->nodeValue : '';
                } elseif ($vNode) {
                    $val = $vNode->nodeValue;
                }

                // Извлекаем букву колонки
                preg_match('/^([A-Z]+)/', $ref, $m);
                $col = $m[1] ?? 'A';
                $rowArr[$col] = $val;
            }

            $rows[$rowNum] = $rowArr;
        }

        return $rows;
    }

    /**
     * Создание базового XLSX с 1 листом
     */
    private static function createSimpleXlsx(string $filePath, string $sheetTitle, array $headers): bool
    {
        $dir = dirname($filePath);
        if (!is_dir($dir)) @mkdir($dir, 0755, true);

        if (!class_exists('ZipArchive')) return false;

        $zip = new ZipArchive();
        if ($zip->open($filePath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            return false;
        }

        // [Content_Types].xml
        $contentTypes = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
<Default Extension="xml" ContentType="application/xml"/>
<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>
<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>
</Types>';

        // _rels/.rels
        $rels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>
</Relationships>';

        // xl/_rels/workbook.xml.rels
        $wbRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>
<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>
</Relationships>';

        // xl/workbook.xml
        $workbook = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
<sheets>
<sheet name="' . htmlspecialchars($sheetTitle, ENT_XML1, 'UTF-8') . '" sheetId="1" r:id="rId1"/>
</sheets>
</workbook>';

        // xl/styles.xml
        $styles = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
<fonts count="2">
<font><name val="Calibri"/><sz val="11"/></font>
<font><b/><name val="Calibri"/><sz val="11"/></font>
</fonts>
<fills count="2">
<fill><patternFill patternType="none"/></fill>
<fill><patternFill patternType="gray125"/></fill>
</fills>
<borders count="1">
<border><left/><right/><top/><bottom/></border>
</borders>
<cellStyleXfs count="1">
<xf numFmtId="0" fontId="0" fillId="0" borderId="0"/>
</cellStyleXfs>
<cellXfs count="2">
<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>
<xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/>
</cellXfs>
</styleSheet>';

        // xl/worksheets/sheet1.xml
        $colLetters = ['A','B','C','D','E','F','G','H','I','J','K','L','M','N','O','P','Q','R','S','T','U','V','W','X','Y','Z'];
        $headerCells = '';
        foreach ($headers as $i => $h) {
            $col = $colLetters[$i] ?? 'A';
            $headerCells .= '<c r="' . $col . '1" t="inlineStr" s="1"><is><t>' . htmlspecialchars($h, ENT_XML1, 'UTF-8') . '</t></is></c>';
        }

        $lastCol = $colLetters[max(0, count($headers)-1)];
        $sheet = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
<dimension ref="A1:' . $lastCol . '1"/>
<sheetViews>
<sheetView tabSelected="1" workbookViewId="0">
<pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/>
</sheetView>
</sheetViews>
<sheetData>
<row r="1">' . $headerCells . '</row>
</sheetData>
</worksheet>';

        $zip->addFromString('[Content_Types].xml', $contentTypes);
        $zip->addFromString('_rels/.rels', $rels);
        $zip->addFromString('xl/_rels/workbook.xml.rels', $wbRels);
        $zip->addFromString('xl/workbook.xml', $workbook);
        $zip->addFromString('xl/styles.xml', $styles);
        $zip->addFromString('xl/worksheets/sheet1.xml', $sheet);

        $zip->close();
        return true;
    }

    /**
     * Создание XLSX с 2 листами (Расписание и Записи)
     */
    private static function createTwoSheetXlsx(string $filePath, string $name1, array $h1, string $name2, array $h2): bool
    {
        $dir = dirname($filePath);
        if (!is_dir($dir)) @mkdir($dir, 0755, true);

        if (!class_exists('ZipArchive')) return false;

        $zip = new ZipArchive();
        if ($zip->open($filePath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            return false;
        }

        $contentTypes = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
<Default Extension="xml" ContentType="application/xml"/>
<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>
<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
<Override PartName="/xl/worksheets/sheet2.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>
</Types>';

        $rels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>
</Relationships>';

        $wbRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>
<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet2.xml"/>
<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>
</Relationships>';

        $workbook = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
<sheets>
<sheet name="' . htmlspecialchars($name1, ENT_XML1, 'UTF-8') . '" sheetId="1" r:id="rId1"/>
<sheet name="' . htmlspecialchars($name2, ENT_XML1, 'UTF-8') . '" sheetId="2" r:id="rId2"/>
</sheets>
</workbook>';

        $styles = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
<fonts count="2">
<font><name val="Calibri"/><sz val="11"/></font>
<font><b/><name val="Calibri"/><sz val="11"/></font>
</fonts>
<fills count="2">
<fill><patternFill patternType="none"/></fill>
<fill><patternFill patternType="gray125"/></fill>
</fills>
<borders count="1">
<border><left/><right/><top/><bottom/></border>
</borders>
<cellStyleXfs count="1">
<xf numFmtId="0" fontId="0" fillId="0" borderId="0"/>
</cellStyleXfs>
<cellXfs count="2">
<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>
<xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/>
</cellXfs>
</styleSheet>';

        $colLetters = ['A','B','C','D','E','F','G','H','I','J','K','L','M','N','O','P','Q','R','S','T','U','V','W','X','Y','Z'];
        
        $h1Cells = '';
        foreach ($h1 as $i => $h) {
            $col = $colLetters[$i] ?? 'A';
            $h1Cells .= '<c r="' . $col . '1" t="inlineStr" s="1"><is><t>' . htmlspecialchars($h, ENT_XML1, 'UTF-8') . '</t></is></c>';
        }

        $h2Cells = '';
        foreach ($h2 as $i => $h) {
            $col = $colLetters[$i] ?? 'A';
            $h2Cells .= '<c r="' . $col . '1" t="inlineStr" s="1"><is><t>' . htmlspecialchars($h, ENT_XML1, 'UTF-8') . '</t></is></c>';
        }

        $sheet1 = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
<sheetViews><sheetView tabSelected="1" workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>
<sheetData><row r="1">' . $h1Cells . '</row></sheetData>
</worksheet>';

        $sheet2 = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
<sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>
<sheetData><row r="1">' . $h2Cells . '</row></sheetData>
</worksheet>';

        $zip->addFromString('[Content_Types].xml', $contentTypes);
        $zip->addFromString('_rels/.rels', $rels);
        $zip->addFromString('xl/_rels/workbook.xml.rels', $wbRels);
        $zip->addFromString('xl/workbook.xml', $workbook);
        $zip->addFromString('xl/styles.xml', $styles);
        $zip->addFromString('xl/worksheets/sheet1.xml', $sheet1);
        $zip->addFromString('xl/worksheets/sheet2.xml', $sheet2);

        $zip->close();
        return true;
    }
}
