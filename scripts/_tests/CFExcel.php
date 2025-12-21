<?php

/**
 * https://cloudframework.io
 * CFExcel Test Script - Comprehensive test suite for CFExcel class
 *
 * Usage:
 *   composer script -- tests/CFExcel            # Run all tests
 *   composer script -- tests/CFExcel/all        # Run all tests
 *   composer script -- tests/CFExcel/basic      # Run basic tests only
 *   composer script -- tests/CFExcel/styling    # Run styling tests only
 *   composer script -- tests/CFExcel/features   # Run new features tests only
 *   composer script -- tests/CFExcel/fileops    # Run file operations tests only
 */
class Script extends CoreScripts
{
    private $testResults = [];
    private $testCount = 0;
    private $passedCount = 0;
    private $failedCount = 0;
    private $outputPath = '';

    /**
     * This function is executed as the main method of the class
     */
    function main()
    {
        $this->sendTerminal("===========================================");
        $this->sendTerminal("CFExcel Class Test Suite");
        $this->sendTerminal("===========================================\n");

        // Check if phpoffice/phpspreadsheet library is installed
        if (!class_exists('PhpOffice\PhpSpreadsheet\Spreadsheet')) {
            $this->sendTerminal("ERROR: phpoffice/phpspreadsheet library is not installed!\n");
            $this->sendTerminal("The CFExcel class requires phpoffice/phpspreadsheet ^1.29 or higher.\n");
            $this->sendTerminal("\nTo install the library, run:");
            $this->sendTerminal("  composer require phpoffice/phpspreadsheet\n");
            $this->sendTerminal("Or add to your composer.json:");
            $this->sendTerminal('  "phpoffice/phpspreadsheet": "^1.29"');
            $this->sendTerminal("\nThen run: composer install\n");
            return $this->setErrorFromCodelib('system-error', 'Required library phpoffice/phpspreadsheet is not installed');
        }

        // Setup output path for test files
        $this->outputPath = $this->core->system->root_path . '/local_data/cfexcel_tests/';
        if (!is_dir($this->outputPath)) {
            mkdir($this->outputPath, 0755, true);
        }

        // Get test method from params[2], default to 'all'
        $method = (isset($this->params[2])) ? $this->params[2] : 'all';
        $method = str_replace('-', '_', $method);

        // Call internal METHOD_{$method}
        if (!$this->useFunction('METHOD_' . $method)) {
            return ($this->setErrorFromCodelib('params-error', "/{$method} is not implemented. Available: all, basic, styling, features, fileops"));
        }

        // Print summary
        $this->printSummary();
    }

    /**
     * Run all tests
     */
    function METHOD_all()
    {
        $this->sendTerminal("Running ALL tests...\n");
        $this->METHOD_basic();
        $this->METHOD_styling();
        $this->METHOD_features();
        $this->METHOD_fileops();
    }

    /**
     * Test basic methods (orientation, title, colors, autofilter)
     */
    function METHOD_basic()
    {
        $this->sendTerminal("--- BASIC METHODS TESTS ---\n");

        // Test 1: Constructor
        $this->test('Constructor', function() {
            $excel = $this->createFreshExcel();
            return ($excel !== null && $excel->spreadsheet !== null && $excel->sheet !== null);
        });

        // Test 2: setSheetTitle
        $this->test('setSheetTitle', function() {
            $excel = $this->createFreshExcel();
            $excel->setSheetTitle('Test Sheet');
            return ($excel->sheet->getTitle() === 'Test Sheet' && !$excel->error);
        });

        // Test 3: setOrientation (testing the bug fix)
        $this->test('setOrientation (portrait)', function() {
            $excel = $this->createFreshExcel();
            $excel->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_PORTRAIT);
            return (!$excel->error);
        });

        // Test 4: setOrientation (landscape)
        $this->test('setOrientation (landscape)', function() {
            $excel = $this->createFreshExcel();
            $excel->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_LANDSCAPE);
            return (!$excel->error);
        });

        // Test 5: setBackGroundColor
        $this->test('setBackGroundColor', function() {
            $excel = $this->createFreshExcel();
            $excel->setCellValue('A1', 'Test')
                  ->setBackGroundColor('A1', 'FFFF0000');
            return (!$excel->error);
        });

        // Test 6: setAutoFilter
        $this->test('setAutoFilter', function() {
            $excel = $this->createFreshExcel();
            $excel->addDataStartingInCell('A1', [['Name', 'Age', 'City']])
                  ->setAutoFilter('A1:C1');
            return (!$excel->error);
        });

        $this->sendTerminal("");
    }

    /**
     * Test styling methods (font, borders)
     */
    function METHOD_styling()
    {
        $this->sendTerminal("--- STYLING METHODS TESTS ---\n");

        // Test 7: setFontStyle - color
        $this->test('setFontStyle (color)', function() {
            $excel = $this->createFreshExcel();
            $excel->setCellValue('A1', 'Test')
                  ->setFontStyle('A1', ['color' => 'red']);
            return (!$excel->error);
        });

        // Test 8: setFontStyle - size
        $this->test('setFontStyle (size)', function() {
            $excel = $this->createFreshExcel();
            $excel->setCellValue('A1', 'Test')
                  ->setFontStyle('A1', ['size' => 16]);
            return (!$excel->error);
        });

        // Test 9: setFontStyle - bold (new feature)
        $this->test('setFontStyle (bold)', function() {
            $excel = $this->createFreshExcel();
            $excel->setCellValue('A1', 'Test')
                  ->setFontStyle('A1', ['bold' => true]);
            return (!$excel->error);
        });

        // Test 10: setFontStyle - italic (new feature)
        $this->test('setFontStyle (italic)', function() {
            $excel = $this->createFreshExcel();
            $excel->setCellValue('A1', 'Test')
                  ->setFontStyle('A1', ['italic' => true]);
            return (!$excel->error);
        });

        // Test 11: setFontStyle - underline
        $this->test('setFontStyle (underline)', function() {
            $excel = $this->createFreshExcel();
            $excel->setCellValue('A1', 'Test')
                  ->setFontStyle('A1', ['underline' => true]);
            return (!$excel->error);
        });

        // Test 12: setFontStyle - combined
        $this->test('setFontStyle (combined)', function() {
            $excel = $this->createFreshExcel();
            $excel->setCellValue('A1', 'Test')
                  ->setFontStyle('A1', [
                      'color' => 'blue',
                      'size' => 14,
                      'bold' => true,
                      'italic' => true,
                      'underline' => true
                  ]);
            return (!$excel->error);
        });

        // Test 13: setBorder - all
        $this->test('setBorder (all)', function() {
            $excel = $this->createFreshExcel();
            $excel->setCellValue('A1', 'Test')
                  ->setBorder('A1', 'all');
            return (!$excel->error);
        });

        // Test 14: setBorder - top
        $this->test('setBorder (top)', function() {
            $excel = $this->createFreshExcel();
            $excel->setCellValue('A1', 'Test')
                  ->setBorder('A1', 'top');
            return (!$excel->error);
        });

        // Test 15: setBorder - invalid type
        $this->test('setBorder (invalid type)', function() {
            $excel = $this->createFreshExcel();
            $excel->setCellValue('A1', 'Test')
                  ->setBorder('A1', 'invalid');
            return ($excel->error && $excel->errorCode === 'params-error');
        });

        $this->sendTerminal("");
    }

    /**
     * Test data operations and formatting
     */
    function METHOD_data()
    {
        $this->sendTerminal("--- DATA OPERATIONS TESTS ---\n");

        // Test 16: addDataStartingInCell - 1D array
        $this->test('addDataStartingInCell (1D array)', function() {
            $excel = $this->createFreshExcel();
            $excel->addDataStartingInCell('A1', ['Name', 'Age', 'City']);
            return (!$excel->error);
        });

        // Test 17: addDataStartingInCell - 2D array
        $this->test('addDataStartingInCell (2D array)', function() {
            $excel = $this->createFreshExcel();
            $data = [
                ['Name', 'Age', 'City'],
                ['John', 30, 'New York'],
                ['Jane', 25, 'Los Angeles']
            ];
            $excel->addDataStartingInCell('A1', $data);
            return (!$excel->error);
        });

        // Test 18: setColumnsSize
        $this->test('setColumnsSize', function() {
            $excel = $this->createFreshExcel();
            $excel->setColumnsSize('A', [100, 150, 200]);
            return (!$excel->error);
        });

        // Test 19: setRowsSize
        $this->test('setRowsSize', function() {
            $excel = $this->createFreshExcel();
            $excel->setRowsSize(1, [25, 30, 35]);
            return (!$excel->error);
        });

        // Test 20: setRowsVerticalAlign
        $this->test('setRowsVerticalAlign', function() {
            $excel = $this->createFreshExcel();
            $excel->setRowsVerticalAlign(1, ['center', 'top', 'bottom']);
            return (!$excel->error);
        });

        // Test 21: setColumnsFormat - text
        $this->test('setColumnsFormat (text)', function() {
            $excel = $this->createFreshExcel();
            $excel->setColumnsFormat('A', 'text');
            return (!$excel->error);
        });

        // Test 22: setColumnsFormat - number
        $this->test('setColumnsFormat (number)', function() {
            $excel = $this->createFreshExcel();
            $excel->setColumnsFormat('B', 'number');
            return (!$excel->error);
        });

        // Test 23: setColumnsFormat - percent
        $this->test('setColumnsFormat (percent)', function() {
            $excel = $this->createFreshExcel();
            $excel->setColumnsFormat('C', ['percent', 'percent_decimal1', 'percent_decimal2']);
            return (!$excel->error);
        });

        // Test 24: setColumnClickable
        $this->test('setColumnClickable', function() {
            $excel = $this->createFreshExcel();
            $excel->setCellValue('A1', 'https://cloudframework.io')
                  ->setCellValue('A2', 'https://google.com')
                  ->setColumnClickable('A');
            return (!$excel->error);
        });

        $this->sendTerminal("");
    }

    /**
     * Test new features (merge, freeze, alignment, wrap)
     */
    function METHOD_features()
    {
        $this->sendTerminal("--- NEW FEATURES TESTS ---\n");

        // Test 25: mergeCells
        $this->test('mergeCells', function() {
            $excel = $this->createFreshExcel();
            $excel->setCellValue('A1', 'Merged Header')
                  ->mergeCells('A1:C1');
            return (!$excel->error);
        });

        // Test 26: freezePane
        $this->test('freezePane', function() {
            $excel = $this->createFreshExcel();
            $excel->freezePane('B2');
            return (!$excel->error);
        });

        // Test 27: setColumnAutoSize
        $this->test('setColumnAutoSize', function() {
            $excel = $this->createFreshExcel();
            $excel->setCellValue('A1', 'Very Long Text That Should Auto Size')
                  ->setColumnAutoSize('A');
            return (!$excel->error);
        });

        // Test 28: setHorizontalAlign - left
        $this->test('setHorizontalAlign (left)', function() {
            $excel = $this->createFreshExcel();
            $excel->setCellValue('A1', 'Test')
                  ->setHorizontalAlign('A1', 'left');
            return (!$excel->error);
        });

        // Test 29: setHorizontalAlign - center
        $this->test('setHorizontalAlign (center)', function() {
            $excel = $this->createFreshExcel();
            $excel->setCellValue('A1', 'Test')
                  ->setHorizontalAlign('A1', 'center');
            return (!$excel->error);
        });

        // Test 30: setHorizontalAlign - right
        $this->test('setHorizontalAlign (right)', function() {
            $excel = $this->createFreshExcel();
            $excel->setCellValue('A1', 'Test')
                  ->setHorizontalAlign('A1', 'right');
            return (!$excel->error);
        });

        // Test 31: setWrapText - true
        $this->test('setWrapText (true)', function() {
            $excel = $this->createFreshExcel();
            $excel->setCellValue('A1', 'This is a very long text that should wrap in the cell')
                  ->setWrapText('A1', true);
            return (!$excel->error);
        });

        // Test 32: setWrapText - false
        $this->test('setWrapText (false)', function() {
            $excel = $this->createFreshExcel();
            $excel->setCellValue('A1', 'This text should not wrap')
                  ->setWrapText('A1', false);
            return (!$excel->error);
        });

        // Test 33: getCellValue
        $this->test('getCellValue', function() {
            $excel = $this->createFreshExcel();
            $excel->setCellValue('A1', 'Test Value');
            $value = $excel->getCellValue('A1');
            return ($value === 'Test Value' && !$excel->error);
        });

        // Test 34: setCellValue
        $this->test('setCellValue', function() {
            $excel = $this->createFreshExcel();
            $excel->setCellValue('A1', 'New Value');
            $value = $excel->getCellValue('A1');
            return ($value === 'New Value' && !$excel->error);
        });

        $this->sendTerminal("");
    }

    /**
     * Test file operations (export, read, getSheetData)
     */
    function METHOD_fileops()
    {
        $this->sendTerminal("--- FILE OPERATIONS TESTS ---\n");

        // Test 35: export - xlsx
        $this->test('export (xlsx)', function() {
            $excel = $this->createFreshExcel();
            $excel->setCellValue('A1', 'Test Export');
            $result = $excel->export('xlsx', $this->outputPath . 'test_export.xlsx');
            $fileExists = file_exists($this->outputPath . 'test_export.xlsx');
            return ($result && $fileExists && !$excel->error);
        });

        // Test 36: export - csv
        $this->test('export (csv)', function() {
            $excel = $this->createFreshExcel();
            $excel->setCellValue('A1', 'Test Export');
            $result = $excel->export('csv', $this->outputPath . 'test_export.csv');
            $fileExists = file_exists($this->outputPath . 'test_export.csv');
            return ($result && $fileExists && !$excel->error);
        });

        // Test 37: export - html
        $this->test('export (html)', function() {
            $excel = $this->createFreshExcel();
            $excel->setCellValue('A1', 'Test Export');
            $result = $excel->export('html', $this->outputPath . 'test_export.html');
            $fileExists = file_exists($this->outputPath . 'test_export.html');
            return ($result && $fileExists && !$excel->error);
        });

        // Test 38: export - invalid format
        $this->test('export (invalid format)', function() {
            $excel = $this->createFreshExcel();
            $excel->setCellValue('A1', 'Test');
            $result = $excel->export('invalid', $this->outputPath . 'test.invalid');
            return (!$result && $excel->error && $excel->errorCode === 'params-error');
        });

        // Test 39: readFile
        $this->test('readFile', function() {
            // First create a file
            $excel = $this->createFreshExcel();
            $excel->setCellValue('A1', 'Test Read');
            $excel->export('xlsx', $this->outputPath . 'test_read.xlsx');

            // Now read it
            $excel2 = $this->createFreshExcel();
            $result = $excel2->readFile($this->outputPath . 'test_read.xlsx');
            return ($result && !$excel2->error);
        });

        // Test 40: readFile - nonexistent file
        $this->test('readFile (nonexistent)', function() {
            $excel = $this->createFreshExcel();
            $result = $excel->readFile($this->outputPath . 'nonexistent.xlsx');
            return (!$result && $excel->error && $excel->errorCode === 'params-error');
        });

        // Test 41: getSheetData
        $this->test('getSheetData', function() {
            // Create a file with data
            $excel = $this->createFreshExcel();
            $data = [
                ['Name', 'Age', 'City'],
                ['John', 30, 'New York'],
                ['Jane', 25, 'Los Angeles']
            ];
            $excel->addDataStartingInCell('A1', $data);
            $excel->export('xlsx', $this->outputPath . 'test_getdata.xlsx');

            // Read and get data
            $excel2 = $this->createFreshExcel();
            $excel2->readFile($this->outputPath . 'test_getdata.xlsx');
            $retrievedData = $excel2->getSheetData(0);

            return (is_array($retrievedData) && count($retrievedData) === 3 && !$excel2->error);
        });

        // Test 42: getSheetCount
        $this->test('getSheetCount', function() {
            $excel = $this->createFreshExcel();
            $count = $excel->getSheetCount();
            return ($count === 1); // Default spreadsheet has 1 sheet
        });

        // Test 43: getSheetName
        $this->test('getSheetName', function() {
            $excel = $this->createFreshExcel();
            $excel->setSheetTitle('Custom Sheet');
            $name = $excel->getSheetName(0);
            return ($name === 'Custom Sheet' && !$excel->error);
        });

        // Test 44: getSheetName - invalid index
        $this->test('getSheetName (invalid index)', function() {
            $excel = $this->createFreshExcel();
            $name = $excel->getSheetName(99);
            return ($name === false && $excel->error && $excel->errorCode === 'params-error');
        });

        $this->sendTerminal("");
    }

    /**
     * Comprehensive integration test
     */
    function METHOD_integration()
    {
        $this->sendTerminal("--- INTEGRATION TEST ---\n");

        $this->test('Complete workflow integration', function() {
            $excel = $this->createFreshExcel();

            // Build a complete spreadsheet
            $excel->setSheetTitle('Sales Report')
                  ->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_LANDSCAPE);

            // Add header
            $excel->addDataStartingInCell('A1', [['Product', 'Q1', 'Q2', 'Q3', 'Q4', 'Total']])
                  ->mergeCells('A1:A1')
                  ->setFontStyle('A1:F1', ['bold' => true, 'size' => 12, 'color' => 'white'])
                  ->setBackGroundColor('A1:F1', 'FF0070C0')
                  ->setHorizontalAlign('A1:F1', 'center')
                  ->setBorder('A1:F1', 'all');

            // Add data
            $data = [
                ['Widget A', 1000, 1200, 1100, 1300, 4600],
                ['Widget B', 800, 900, 950, 1000, 3650],
                ['Widget C', 1500, 1600, 1700, 1800, 6600]
            ];
            $excel->addDataStartingInCell('A2', $data);

            // Format columns
            $excel->setColumnsFormat('B', ['number_comma_decimal2', 'number_comma_decimal2', 'number_comma_decimal2', 'number_comma_decimal2', 'number_comma_decimal2'])
                  ->setColumnAutoSize('A')
                  ->setHorizontalAlign('B2:F4', 'right')
                  ->setBorder('A1:F4', 'all');

            // Add summary
            $excel->setCellValue('A6', 'Summary')
                  ->mergeCells('A6:F6')
                  ->setFontStyle('A6', ['bold' => true, 'italic' => true])
                  ->setHorizontalAlign('A6', 'center')
                  ->setWrapText('A6', false);

            // Freeze header
            $excel->freezePane('A2');

            // Export
            $result = $excel->export('xlsx', $this->outputPath . 'integration_test.xlsx');

            return ($result && !$excel->error && file_exists($this->outputPath . 'integration_test.xlsx'));
        });

        $this->sendTerminal("");
    }

    /**
     * Helper method to create a fresh CFExcel instance (avoiding cache)
     * Core7's loadClass caches instances by params, so we use unique random params
     */
    private function createFreshExcel()
    {
        // Use random parameter to bypass Core7's instance caching
        return $this->core->loadClass('CFExcel', ['_test_id' => uniqid('test_', true)]);
    }

    /**
     * Helper method to run a test
     */
    private function test($name, $callback)
    {
        $this->testCount++;
        try {
            $result = $callback();
            if ($result) {
                $this->passedCount++;
                $this->testResults[] = ['name' => $name, 'status' => 'PASS'];
                $this->sendTerminal("✓ Test #{$this->testCount}: {$name} - PASSED");
            } else {
                $this->failedCount++;
                $this->testResults[] = ['name' => $name, 'status' => 'FAIL'];
                $this->sendTerminal("✗ Test #{$this->testCount}: {$name} - FAILED");
            }
        } catch (Exception $e) {
            $this->failedCount++;
            $this->testResults[] = ['name' => $name, 'status' => 'ERROR', 'message' => $e->getMessage()];
            $this->sendTerminal("✗ Test #{$this->testCount}: {$name} - ERROR: " . $e->getMessage());
        } catch (Throwable $e) {
            $this->failedCount++;
            $this->testResults[] = ['name' => $name, 'status' => 'ERROR', 'message' => $e->getMessage()];
            $this->sendTerminal("✗ Test #{$this->testCount}: {$name} - ERROR: " . $e->getMessage());
        }
    }

    /**
     * Print test summary
     */
    private function printSummary()
    {
        $this->sendTerminal("===========================================");
        $this->sendTerminal("TEST SUMMARY");
        $this->sendTerminal("===========================================");
        $this->sendTerminal("Total Tests:  {$this->testCount}");
        $this->sendTerminal("Passed:       {$this->passedCount}");
        $this->sendTerminal("Failed:       {$this->failedCount}");
        $passRate = $this->testCount > 0 ? round(($this->passedCount / $this->testCount) * 100, 2) : 0;
        $this->sendTerminal("Pass Rate:    {$passRate}%");
        $this->sendTerminal("===========================================");

        if ($this->failedCount > 0) {
            $this->sendTerminal("\nFailed Tests:");
            foreach ($this->testResults as $result) {
                if ($result['status'] !== 'PASS') {
                    $message = isset($result['message']) ? " - " . $result['message'] : '';
                    $this->sendTerminal("  - {$result['name']}{$message}");
                }
            }
        }

        $this->sendTerminal("\nTest files saved to: {$this->outputPath}");
        $this->sendTerminal("");

        // Return appropriate exit code
        return ($this->failedCount === 0);
    }
}
