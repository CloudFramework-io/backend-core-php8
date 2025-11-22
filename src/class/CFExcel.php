<?php
/**
 * [$excel = $this->core->loadClass('CFExcel');] Class CFExcel to handle MS Spreadsheet xls
 * It requires phpoffice/phpspreadsheet ^1.29 (composer install phpoffice/phpspreadsheet)
 * @package Utils
 */
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Style\Font;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;


/**
 * CFExcel class for handling and manipulating Excel spreadsheets.
 *
 * Changelog:
 * - 20250417: Initial version
 * - 20251122: Bug fixes and enhancements
 *   * Fixed setOrientation() inverted logic bug
 *   * Fixed duplicate code in setFontStyle()
 *   * Fixed namespace prefix in export()
 *   * Changed var to public/private for properties
 *   * Added merge cells, freeze panes, auto-size columns
 *   * Added bold/italic to font styles
 *   * Added horizontal alignment
 *   * Added get/set cell value methods
 *   * Added wrap text functionality
 *   * Standardized exception handling
 *   * Improved PHPDoc comments
 */
class CFExcel
{
    private $version = '20251122';

    /** @var Core7 Core7 instance */
    public $core;

    /** @var Spreadsheet PhpSpreadsheet instance */
    public $spreadsheet = null;

    /** @var Worksheet Active worksheet */
    public $sheet = null;

    // ---- Error Variables ----
    public $error = false;
    public $errorCode = null;
    public $errorMsg = [];

    /**
     * CFExcel constructor.
     * @param Core7 $core Core7 instance for framework integration
     */
    function __construct(Core7 &$core)
    {
        $this->core = $core;
        $this->spreadsheet = new Spreadsheet();
        $this->sheet = $this->spreadsheet->getActiveSheet();
    }

    /**
     * Set the orientation for the Excel file.
     *
     * @param string $orientation The orientation to set. Can be default, landscape, or portrait.
     * @return CFExcel The CFExcel object for method chaining.
     */
    public function setOrientation(string $orientation): CFExcel
    {
        if(in_array($orientation,[PageSetup::ORIENTATION_DEFAULT,PageSetup::ORIENTATION_LANDSCAPE,PageSetup::ORIENTATION_PORTRAIT]))
            $this->sheet->getPageSetup()->setOrientation($orientation);

        return $this;
    }

    /**
     * Set the title of the sheet in the CFExcel instance
     *
     * @param string $title The title to be set for the sheet
     * @return CFExcel The CFExcel instance for method chaining
     */
    public function setSheetTitle(string $title): CFExcel
    {
        $this->sheet->setTitle($title);
        return $this;
    }

    /**
     * Set background color for a specific range in the spreadsheet
     *
     * @param string $range The range for which to set the background color
     * @param string $color The color code to set as the background color
     * @return CFExcel The CFExcel instance for method chaining
     */
    public function setBackGroundColor(string $range, string $color): CFExcel
    {
        try {
            $this->sheet->getStyle($range)
                ->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setARGB($color);

        } catch (\Exception $e) {
            $this->addError('system-error',$e->getMessage());
        }
        return $this;
    }

    /**
     * Set auto filter for a specific range in the spreadsheet
     *
     * @param string $range The range for which to set the auto filter
     * @return CFExcel The CFExcel instance for method chaining
     */
    public function setAutoFilter(string $range): CFExcel
    {
        try {
            $this->sheet->setAutoFilter($range);
        } catch (\Exception $e) {
            $this->addError('system-error',$e->getMessage());
        }
        return $this;
    }

    /**
     * Set font style for a specific range in the spreadsheet.
     *
     * @param string $range The cell range to apply the font style to
     * @param array $options An array containing font style options:
     *                      - 'color' (string) : (Optional) The color of the font. Can be sent rgb color (FFFFFF) or the following supported colors: black, white, red, green, blue, yellow, magenta, cyan.
     *                      - 'size' (int) : (Optional) The size of the font.
     *                      - 'bold' (bool) : (Optional) if true it will bold.
     *                      - 'italic' (bool) : (Optional) if true it will italicize.
     *                      - 'underline' (bool) : (Optional) if true it will underline.
     * @return CFExcel The CFExcel instance for method chaining
     */
    public function setFontStyle(string $range, array $options): CFExcel
    {
        try {
            //region ASSIGN $font for $range
            $font = $this->sheet->getStyle($range)->getFont();
            //endregion

            //region EVALUATE $options['color']
            if($color = $options['color']??null) {
                $colors = [
                    'black' => 'FF000000',
                    'white' => 'FFFFFFFF',
                    'red' => 'FFFF0000',
                    'green' => 'FF00FF00',
                    'blue' => 'FF0000FF',
                    'yellow' => 'FFFFFF00',
                    'magenta' => 'FFFF00FF',
                    'cyan' => 'FF00FFFF',
                ];
                $font->setColor(new Color($colors[strtolower($color)] ?? $color));
            }
            //endregion

            //region EVALUATE $options['size']
            if(($size = intval($options['size']??0)) > 0) {
                $font->setSize($size);
            }
            //endregion

            //region EVALUATE $options['bold']
            if($options['bold']??null) {
                $font->setBold(true);
            }
            //endregion

            //region EVALUATE $options['italic']
            if($options['italic']??null) {
                $font->setItalic(true);
            }
            //endregion

            //region EVALUATE $options['underline']
            if($options['underline']??null) {
                $font->setUnderline(true);
            }
            //endregion

        } catch (\Exception $e) {
            $this->addError('system-error', $e->getMessage());
        }

        return $this;
    }

    /**
     * Set border style for the specified range in the spreadsheet.
     *
     * @param string $range The range to set the border style for.
     * @param string $borderType The type of border to set (right, left, top, bottom, all).
     * @return CFExcel The CFExcel object for method chaining. If error it adds an error in the object
     */
    public function setBorder(string $range, string $borderType): CFExcel
    {
        try {
            $borders = $this->sheet->getStyle($range)->getBorders();
            switch ($borderType) {
                case "right":
                    $borders->getRight()->setBorderStyle(Border::BORDER_THIN);
                    break;
                case "left":
                    $borders->getLeft()->setBorderStyle(Border::BORDER_THIN);
                    break;
                case "top":
                    $borders->getTop()->setBorderStyle(Border::BORDER_THIN);
                    break;
                case "bottom":
                    $borders->getBottom()->setBorderStyle(Border::BORDER_THIN);
                    break;
                case "all":
                    $borders->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
                    break;
                default:
                    $this->addError('params-error',__FUNCTION__.': has received a wrong $borderType');
                    break;
            }
        } catch (\Exception $e) {
            $this->addError('system-error',$e->getMessage());
        }
        return $this;
    }

    /**
     * Add data to the spreadsheet starting at a specific cell
     *
     * @param string $cell where to start the insertion. Ex: A1
     * @param array $data Array of data to populate (can be 1D or 2D array)
     * @param array $styles Styles to apply (reserved for future use)
     * @return CFExcel The CFExcel instance for method chaining
     */
    public function addDataStartingInCell(string $cell, array $data, array $styles=[]): CFExcel
    {
        if(!isset($data[0])) {
            $this->addError('params-error',__FUNCTION__.': has received $data missing $data[0] element');
            return $this;
        }
        if(!isset($data[0][0])) $data = [$data];

        try {
            $this->sheet->fromArray($data, null, $cell);
        } catch (\Exception $e) {
            $this->addError('system-error',$e->getMessage());
        }

        return $this;
    }

    /**
     * Set custom width sizes for columns in an Excel sheet starting from a specific column.
     *
     * @param string $startColumn The starting column letter.
     * @param array|float $size_data Array containing width sizes for each column, or single float value.
     * @return CFExcel CFExcel object with column sizes set. If error it add an error in the object
     */
    public function setColumnsSize(string $startColumn, array|float $size_data): CFExcel
    {
        if(!is_array($size_data)) $size_data = [$size_data];
        try {
            foreach ($size_data as $datum) {
                $this->sheet->getColumnDimension($startColumn)->setWidth(floatval($datum), 'pt');
                $startColumn = $this->incrementColumn($startColumn);
            }
        } catch (\Exception $e) {
            $this->addError('system-error',$e->getMessage());
        }
        return $this;
    }

    /**
     * Set custom height sizes for rows in an Excel sheet starting from a specific row.
     *
     * @param int $startRow The starting row number
     * @param array|float $size_data Array containing height sizes for each row, or single float value.
     * @return CFExcel CFExcel object with row sizes set. If error it add an error in the object
     */
    public function setRowsSize(int $startRow, array|float $size_data): CFExcel
    {
        if(!is_array($size_data)) $size_data = [$size_data];
        try {
            foreach ($size_data as $datum) {
                $this->sheet->getRowDimension($startRow)->setRowHeight(floatval($datum), 'pt');
                $startRow++;
            }
        } catch (\Exception $e) {
            $this->addError('system-error',$e->getMessage());
        }
        return $this;
    }

    /**
     * Set vertical alignment for rows starting from a specific row.
     *
     * @param int $startRow The starting row number
     * @param array|string $align_data Array containing alignment for each row, or single string value.
     *      - defined alignments are: bottom, top, center, justify, distributed
     * @return CFExcel CFExcel object with vertical alignment set. If error it add an error in the object
     */
    public function setRowsVerticalAlign(int $startRow, array|string $align_data): CFExcel
    {
        $alignments['bottom'] = Alignment::VERTICAL_BOTTOM;
        $alignments['top'] = Alignment::VERTICAL_TOP;
        $alignments['center'] = Alignment::VERTICAL_CENTER;
        $alignments['justify'] = Alignment::VERTICAL_JUSTIFY;
        $alignments['distributed'] = Alignment::VERTICAL_DISTRIBUTED;

        if(!is_array($align_data)) $align_data = [$align_data];
        try {
            foreach ($align_data as $datum) {
                $this->sheet->getStyle($startRow)->getAlignment()->setVertical($alignments[$datum]??$datum);
                $startRow++;
            }
        } catch (\Exception $e) {
            $this->addError('system-error',$e->getMessage());
        }
        return $this;
    }

    /**
     * Set specified column in the spreadsheet as clickable hyperlinks.
     *
     * @param string $column The column letter to set as clickable hyperlinks.
     * @return CFExcel The CFExcel object for chaining method calls. If error it add an error in the object
     */
    public function setColumnClickable(string $column): CFExcel
    {
        try {
            $maxRowInColumn = $this->sheet->getHighestRow($column);
            for($i=1;$i<=$maxRowInColumn;$i++) {
                $value = $this->sheet->getCell("{$column}{$i}")->getValue();
                if(str_starts_with($value, 'http')) {
                    $this->sheet->getCell("{$column}{$i}")->getHyperlink()->setUrl($value);
                    $this->sheet->getStyle("{$column}{$i}")->getFont()->setColor(new Color(Color::COLOR_BLUE));
                    $this->sheet->getStyle("{$column}{$i}")->getFont()->setUnderline(true);
                }
            }
        } catch (\Exception $e) {
            $this->addError('system-error',$e->getMessage());
        }
        return $this;
    }

    /**
     * Set the formatting for columns starting from a specified column.
     *
     * @param string $startColumn The starting column letter for formatting.
     * @param array|string $format_data The format data to apply to the columns. If string, it will be converted to an array.
     *     - defined formats are: general, text, number, decimal1, decimal2, number_comma_decimal1, number_comma_decimal2, percent, percent_decimal1, percent_decimal2
     * @return CFExcel The CFExcel object for method chaining.
     */
    public function setColumnsFormat(string $startColumn, array|string $format_data): CFExcel
    {
        $formats = [];
        $formats['general'] = \PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_GENERAL;
        $formats['text'] = \PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_TEXT;
        $formats['number'] = \PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_NUMBER;
        $formats['decimal1'] = \PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_NUMBER_0;
        $formats['decimal2'] = \PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_NUMBER_00;
        $formats['number_comma_decimal1'] = \PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1;
        $formats['number_comma_decimal2'] = \PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED2;
        $formats['percent'] = \PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_PERCENTAGE;
        $formats['percent_decimal1'] = \PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_PERCENTAGE_0;
        $formats['percent_decimal2'] = \PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_PERCENTAGE_00;

        if(is_string($format_data)) $format_data = [$format_data];
        try {
            foreach ($format_data as $format) {
                $format = $formats[$format]??$format;
                $this->sheet->getStyle($startColumn)->getNumberFormat()->setFormatCode($format);
                $startColumn = $this->incrementColumn($startColumn);
            }
        } catch (\Exception $e) {
            $this->addError('system-error',$e->getMessage());
        }
        return $this;
    }

    /**
     * Export data to specified format and save to file.
     *
     * @param string $format The format to export the data to (xlsx, xls, ods, csv, html).
     * @param string $filePath The path where the exported file will be saved.
     * @return bool True if the export and save process was successful, otherwise false reporting an error.
     */
    public function export(string $format, string $filePath): bool
    {
        $formats = [];
        $formats['xlsx'] = 'Xlsx';
        $formats['xls'] = 'Xls';
        $formats['ods'] = 'Ods';
        $formats['csv'] = 'Csv';
        $formats['html'] = 'Html';
        if(!isset($formats[strtolower($format)])) return $this->addError('params-error',__FUNCTION__.': has received a wrong $format value');

        try {
            $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($this->spreadsheet, $formats[strtolower($format)]);
            $writer->save($filePath);
        } catch (\Exception $e) {
            return $this->addError('system-error',$e->getMessage());
        }
        return true;
    }




    /**
     * Merge cells in the spreadsheet
     *
     * @param string $range The cell range to merge (e.g., 'A1:C1')
     * @return CFExcel The CFExcel instance for method chaining
     */
    public function mergeCells(string $range): CFExcel
    {
        try {
            $this->sheet->mergeCells($range);
        } catch (\Exception $e) {
            $this->addError('system-error', $e->getMessage());
        }
        return $this;
    }

    /**
     * Freeze panes at a specific cell
     *
     * @param string $cell The cell to freeze at (e.g., 'B2' to freeze first row and column)
     * @return CFExcel The CFExcel instance for method chaining
     */
    public function freezePane(string $cell): CFExcel
    {
        try {
            $this->sheet->freezePane($cell);
        } catch (\Exception $e) {
            $this->addError('system-error', $e->getMessage());
        }
        return $this;
    }

    /**
     * Set column to auto-size based on content
     *
     * @param string $column The column letter to auto-size
     * @return CFExcel The CFExcel instance for method chaining
     */
    public function setColumnAutoSize(string $column): CFExcel
    {
        try {
            $this->sheet->getColumnDimension($column)->setAutoSize(true);
        } catch (\Exception $e) {
            $this->addError('system-error', $e->getMessage());
        }
        return $this;
    }

    /**
     * Set horizontal alignment for a specific range
     *
     * @param string $range The cell range to apply alignment to
     * @param string $align The alignment type: left, center, right, justify, distributed
     * @return CFExcel The CFExcel instance for method chaining
     */
    public function setHorizontalAlign(string $range, string $align): CFExcel
    {
        $alignments = [
            'left' => Alignment::HORIZONTAL_LEFT,
            'center' => Alignment::HORIZONTAL_CENTER,
            'right' => Alignment::HORIZONTAL_RIGHT,
            'justify' => Alignment::HORIZONTAL_JUSTIFY,
            'distributed' => Alignment::HORIZONTAL_DISTRIBUTED,
        ];

        try {
            $this->sheet->getStyle($range)->getAlignment()->setHorizontal($alignments[$align] ?? $align);
        } catch (\Exception $e) {
            $this->addError('system-error', $e->getMessage());
        }
        return $this;
    }

    /**
     * Set wrap text for a specific range
     *
     * @param string $range The cell range to apply wrap text to
     * @param bool $wrap Whether to wrap text (default: true)
     * @return CFExcel The CFExcel instance for method chaining
     */
    public function setWrapText(string $range, bool $wrap = true): CFExcel
    {
        try {
            $this->sheet->getStyle($range)->getAlignment()->setWrapText($wrap);
        } catch (\Exception $e) {
            $this->addError('system-error', $e->getMessage());
        }
        return $this;
    }

    /**
     * Get value from a specific cell
     *
     * @param string $cell The cell coordinate (e.g., 'A1')
     * @return mixed The cell value, or null on error
     */
    public function getCellValue(string $cell): mixed
    {
        try {
            return $this->sheet->getCell($cell)->getValue();
        } catch (\Exception $e) {
            $this->addError('system-error', $e->getMessage());
            return null;
        }
    }

    /**
     * Set value for a specific cell
     *
     * @param string $cell The cell coordinate (e.g., 'A1')
     * @param mixed $value The value to set
     * @return CFExcel The CFExcel instance for method chaining
     */
    public function setCellValue(string $cell, mixed $value): CFExcel
    {
        try {
            $this->sheet->setCellValue($cell, $value);
        } catch (\Exception $e) {
            $this->addError('system-error', $e->getMessage());
        }
        return $this;
    }

    /**
     * Function to increment an Excel column letter (e.g., A, B, ..., Z, AA, AB, etc.).
     *
     * @param string $column The column letter to increment.
     * @return string The next column letter.
     */
    private function incrementColumn(string $column): string {
        $length = strlen($column); // Get the length of the column string
        $lastLetter = substr($column, $length - 1); // Get the last letter
        $remainingPart = substr($column, 0, $length - 1); // Get the rest of the column (if any)

        // If the last letter is less than 'Z', simply increment it
        if ($lastLetter < 'Z') {
            $lastLetter++; // Increment the last letter
            return $remainingPart . $lastLetter; // Return the combined string
        } else {
            // If the last letter is 'Z', reset it to 'A' and handle the carry-over
            $lastLetter = 'A'; // Reset to 'A'
            if ($remainingPart == '') {
                // If there are no more letters before 'Z', start with 'AA'
                return 'AA';
            } else {
                // Increment the preceding part recursively
                $remainingPart = $this->incrementColumn($remainingPart);
                return $remainingPart . $lastLetter; // Return the combined string
            }
        }
    }

    /**
     * Read an Excel file from disk
     *
     * @param string $filePath The path to the Excel file to read
     * @return bool True if the file was successfully read, false otherwise
     */
    public function readFile(string $filePath): bool
    {
        if(!file_exists($filePath)) {
            return $this->addError('params-error', 'File does not exist: ' . $filePath);
        }

        try {
            $this->spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($filePath);
            $this->sheet = $this->spreadsheet->getActiveSheet();
            return true;
        } catch (\Exception $e) {
            return $this->addError('system-error', 'Error reading file: ' . $e->getMessage());
        }
    }

    /**
     * Get all data from a specific sheet as an array
     *
     * @param int $sheetIndex The index of the sheet to read (0-based)
     * @return array|false Returns array of rows, or false on error. Each row is an array of cell values.
     */
    public function getSheetData(int $sheetIndex = 0): array|false
    {
        try {
            // Get the specified sheet
            if($this->spreadsheet->getSheetCount() <= $sheetIndex) {
                $this->addError('params-error', 'Sheet index out of range: ' . $sheetIndex);
                return false;
            }

            $sheet = $this->spreadsheet->getSheet($sheetIndex);

            // Get all data as array
            $data = $sheet->toArray(null, true, true, true);

            // Re-index to 0-based array (remove letter keys)
            $result = [];
            foreach($data as $row) {
                $result[] = array_values($row);
            }

            return $result;

        } catch (\Exception $e) {
            $this->addError('system-error', 'Error reading sheet data: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get the number of sheets in the current spreadsheet
     *
     * @return int The number of sheets
     */
    public function getSheetCount(): int
    {
        return $this->spreadsheet->getSheetCount();
    }

    /**
     * Get the name of a specific sheet
     *
     * @param int $sheetIndex The index of the sheet (0-based)
     * @return string|false The sheet name, or false on error
     */
    public function getSheetName(int $sheetIndex = 0): string|false
    {
        try {
            if($this->spreadsheet->getSheetCount() <= $sheetIndex) {
                $this->addError('params-error', 'Sheet index out of range: ' . $sheetIndex);
                return false;
            }
            return $this->spreadsheet->getSheet($sheetIndex)->getTitle();
        } catch (\Exception $e) {
            $this->addError('system-error', 'Error getting sheet name: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Add an error in the class
     * @param string $code
     * @param $message
     * @return false to facilitate the return of other functions
     */
    private function addError(string $code,$message): bool
    {
        $this->error=true;
        $this->errorCode=$code;
        $this->errorMsg[]=$message;
        return false;
    }

}