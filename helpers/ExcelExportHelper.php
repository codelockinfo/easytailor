<?php

class ExcelExportHelper
{
    public static function escapeXml($value): string
    {
        return htmlspecialchars((string)$value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    /**
     * Build a styled SpreadsheetML 2003 (.xls) workbook compatible with Excel, Sheets, and mobile viewers.
     *
     * @param string $sheetName Worksheet tab name (max 31 chars)
     * @param string $title Document title
     * @param array<int, array{label: string, width: int, type?: string, style?: string}> $columns
     * @param array<int, array<int, mixed>> $rows
     * @param array<int, array{label?: string, cells: array<int, array{value: mixed, type?: string, style?: string}>}> $summaryRows
     */
    public static function buildWorkbook(
        string $sheetName,
        string $title,
        array $columns,
        array $rows,
        array $summaryRows = []
    ): string {
        $safeSheet = preg_replace('/[\\\\\/\?\*\[\]:]/', '', $sheetName);
        $safeSheet = substr(trim($safeSheet) ?: 'Export', 0, 31);

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<?mso-application progid="Excel.Sheet"?>' . "\n";
        $xml .= '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet"' . "\n";
        $xml .= ' xmlns:o="urn:schemas-microsoft-com:office:office"' . "\n";
        $xml .= ' xmlns:x="urn:schemas-microsoft-com:office:excel"' . "\n";
        $xml .= ' xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet"' . "\n";
        $xml .= ' xmlns:html="http://www.w3.org/TR/REC-html40">' . "\n";
        $xml .= '<DocumentProperties xmlns="urn:schemas-microsoft-com:office:office">' . "\n";
        $xml .= '  <Title>' . self::escapeXml($title) . '</Title>' . "\n";
        $xml .= '  <Author>' . self::escapeXml(defined('APP_NAME') ? APP_NAME : 'TailorPro') . '</Author>' . "\n";
        $xml .= '  <Created>' . date('c') . '</Created>' . "\n";
        $xml .= '</DocumentProperties>' . "\n";

        $xml .= '<Styles>' . "\n";
        $xml .= '  <Style ss:ID="Default" ss:Name="Normal"><Alignment ss:Vertical="Center"/><Font ss:FontName="Calibri" ss:Size="10" ss:Color="#1E293B"/></Style>' . "\n";
        $xml .= '  <Style ss:ID="Title"><Alignment ss:Horizontal="Left" ss:Vertical="Center"/><Font ss:FontName="Calibri" ss:Size="13" ss:Bold="1" ss:Color="#312E81"/></Style>' . "\n";
        $xml .= '  <Style ss:ID="SubTitle"><Alignment ss:Horizontal="Left" ss:Vertical="Center"/><Font ss:FontName="Calibri" ss:Size="9" ss:Color="#64748B"/></Style>' . "\n";
        $xml .= '  <Style ss:ID="Header"><Alignment ss:Horizontal="Center" ss:Vertical="Center" ss:WrapText="1"/><Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#4338CA"/><Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#4338CA"/><Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#6366F1"/><Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#6366F1"/></Borders><Font ss:FontName="Calibri" ss:Size="10" ss:Bold="1" ss:Color="#FFFFFF"/><Interior ss:Color="#4F46E5" ss:Pattern="Solid"/></Style>' . "\n";
        $xml .= '  <Style ss:ID="Text"><Alignment ss:Horizontal="Left" ss:Vertical="Center" ss:WrapText="1"/><Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E2E8F0"/><Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E2E8F0"/><Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E2E8F0"/></Borders></Style>' . "\n";
        $xml .= '  <Style ss:ID="CenterText"><Alignment ss:Horizontal="Center" ss:Vertical="Center"/><Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E2E8F0"/><Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E2E8F0"/><Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E2E8F0"/></Borders></Style>' . "\n";
        $xml .= '  <Style ss:ID="Integer"><Alignment ss:Horizontal="Center" ss:Vertical="Center"/><Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E2E8F0"/><Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E2E8F0"/><Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E2E8F0"/></Borders><NumberFormat ss:Format="0"/></Style>' . "\n";
        $xml .= '  <Style ss:ID="Currency"><Alignment ss:Horizontal="Right" ss:Vertical="Center"/><Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E2E8F0"/><Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E2E8F0"/><Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E2E8F0"/></Borders><NumberFormat ss:Format="#,##0.00"/></Style>' . "\n";
        $xml .= '  <Style ss:ID="SummaryText"><Alignment ss:Horizontal="Left" ss:Vertical="Center"/><Borders><Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="2" ss:Color="#4F46E5"/><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#CBD5E1"/></Borders><Font ss:FontName="Calibri" ss:Size="10" ss:Bold="1" ss:Color="#1E293B"/><Interior ss:Color="#EEF2FF" ss:Pattern="Solid"/></Style>' . "\n";
        $xml .= '  <Style ss:ID="SummaryInteger"><Alignment ss:Horizontal="Center" ss:Vertical="Center"/><Borders><Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="2" ss:Color="#4F46E5"/><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#CBD5E1"/></Borders><Font ss:FontName="Calibri" ss:Size="10" ss:Bold="1" ss:Color="#1E293B"/><Interior ss:Color="#EEF2FF" ss:Pattern="Solid"/><NumberFormat ss:Format="0"/></Style>' . "\n";
        $xml .= '  <Style ss:ID="SummaryCurrency"><Alignment ss:Horizontal="Right" ss:Vertical="Center"/><Borders><Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="2" ss:Color="#4F46E5"/><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#CBD5E1"/></Borders><Font ss:FontName="Calibri" ss:Size="10" ss:Bold="1" ss:Color="#1E293B"/><Interior ss:Color="#EEF2FF" ss:Pattern="Solid"/><NumberFormat ss:Format="#,##0.00"/></Style>' . "\n";
        $xml .= '</Styles>' . "\n";

        $xml .= '<Worksheet ss:Name="' . self::escapeXml($safeSheet) . '">' . "\n";
        $xml .= '<Table>' . "\n";

        foreach ($columns as $col) {
            $width = (int)($col['width'] ?? 110);
            $xml .= '  <Column ss:AutoFitWidth="0" ss:Width="' . $width . '"/>' . "\n";
        }

        // Report Title & Timestamp Banner
        $colCount = max(1, count($columns));
        $xml .= '  <Row ss:Height="24"><Cell ss:MergeAcross="' . ($colCount - 1) . '" ss:StyleID="Title"><Data ss:Type="String">' . self::escapeXml($title) . '</Data></Cell></Row>' . "\n";
        $xml .= '  <Row ss:Height="18"><Cell ss:MergeAcross="' . ($colCount - 1) . '" ss:StyleID="SubTitle"><Data ss:Type="String">Generated on ' . self::escapeXml(date('d M Y, h:i A')) . ' | Total Records: ' . count($rows) . '</Data></Cell></Row>' . "\n";

        // Column Headers
        $xml .= '  <Row ss:Height="24">' . "\n";
        foreach ($columns as $col) {
            $xml .= '    <Cell ss:StyleID="Header"><Data ss:Type="String">' . self::escapeXml($col['label']) . '</Data></Cell>' . "\n";
        }
        $xml .= '  </Row>' . "\n";

        // Data Rows
        foreach ($rows as $row) {
            $xml .= '  <Row ss:Height="20">' . "\n";
            foreach ($columns as $idx => $col) {
                $val = $row[$idx] ?? '';
                $type = $col['type'] ?? 'String';
                $style = $col['style'] ?? ($type === 'Number' ? 'Currency' : 'Text');

                if ($type === 'Number') {
                    $numVal = is_numeric($val) ? (string)(0 + $val) : '0';
                    $xml .= '    <Cell ss:StyleID="' . $style . '"><Data ss:Type="Number">' . $numVal . '</Data></Cell>' . "\n";
                } else {
                    $xml .= '    <Cell ss:StyleID="' . $style . '"><Data ss:Type="String">' . self::escapeXml((string)$val) . '</Data></Cell>' . "\n";
                }
            }
            $xml .= '  </Row>' . "\n";
        }

        // Summary / Totals Rows
        foreach ($summaryRows as $sRow) {
            $xml .= '  <Row ss:Height="22">' . "\n";
            for ($i = 0; $i < $colCount; $i++) {
                $cellDef = $sRow[$i] ?? ['value' => '', 'type' => 'String', 'style' => 'SummaryText'];
                $type = $cellDef['type'] ?? 'String';
                $style = $cellDef['style'] ?? ($type === 'Number' ? 'SummaryCurrency' : 'SummaryText');
                $val = $cellDef['value'] ?? '';
                if ($type === 'Number') {
                    $numVal = is_numeric($val) ? (string)(0 + $val) : '0';
                    $xml .= '    <Cell ss:StyleID="' . $style . '"><Data ss:Type="Number">' . $numVal . '</Data></Cell>' . "\n";
                } else {
                    $xml .= '    <Cell ss:StyleID="' . $style . '"><Data ss:Type="String">' . self::escapeXml((string)$val) . '</Data></Cell>' . "\n";
                }
            }
            $xml .= '  </Row>' . "\n";
        }

        $xml .= '</Table>' . "\n";
        $xml .= '</Worksheet>' . "\n";
        $xml .= '</Workbook>';

        return $xml;
    }

    public static function sendDownload(string $filename, string $xmlContent): void
    {
        if (ob_get_length()) {
            ob_clean();
        }
        header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: max-age=0');
        header('Pragma: public');
        header('Content-Length: ' . strlen($xmlContent));
        echo $xmlContent;
        exit;
    }
}
