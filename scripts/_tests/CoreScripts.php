<?php

/**
 * https://cloudframework.io
 * CoreScripts Test Script - Comprehensive test suite for CoreScripts class
 *
 * The CoreScripts class provides the foundation for all script execution in CloudFramework,
 * including parameter handling, caching, user prompts, and ERP integration.
 *
 * Usage:
 *   composer script -- tests/CoreScripts                # Run all tests
 *   composer script -- tests/CoreScripts/all            # Run all tests
 *   composer script -- tests/CoreScripts/params         # Run parameter handling tests
 *   composer script -- tests/CoreScripts/cache          # Run cache tests
 *   composer script -- tests/CoreScripts/errors         # Run error handling tests
 *   composer script -- tests/CoreScripts/methods        # Run method tests
 */
class Script extends CoreScripts
{
    private $testResults = [];
    private $testCount = 0;
    private $passedCount = 0;
    private $failedCount = 0;

    /**
     * This function is executed as the main method of the class
     */
    function main()
    {
        $this->sendTerminal("===========================================");
        $this->sendTerminal("CoreScripts Class Test Suite");
        $this->sendTerminal("===========================================\n");

        // Get test method from params[2], default to 'all'
        $method = (isset($this->params[2])) ? $this->params[2] : 'all';
        $method = str_replace('-', '_', $method);

        // Call internal METHOD_{$method}
        if (!$this->useFunction('METHOD_' . $method)) {
            return ($this->setErrorFromCodelib('params-error', "/{$method} is not implemented. Available: all, params, cache, errors, methods"));
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
        $this->METHOD_params();
        $this->METHOD_cache();
        $this->METHOD_errors();
        $this->METHOD_methods();

        $this->core->logs->reset();
        $this->core->errors->reset();

    }

    /**
     * Test parameter handling methods
     */
    function METHOD_params()
    {
        $this->sendTerminal("--- PARAMETER HANDLING TESTS ---\n");

        // Test 1: Constructor initialization
        $this->test('Constructor initialization', function() {
            // $this is already a CoreScripts instance from main()
            return (
                $this->core !== null &&
                is_array($this->params) &&
                is_array($this->formParams) &&
                is_array($this->argv) &&
                $this->cache !== null
            );
        });

        // Test 2: params array contains URL parts
        $this->test('params array contains URL parts', function() {
            // params[0] should be 'tests', params[1] should be 'CoreScripts'
            return (
                is_array($this->params) &&
                isset($this->params[0]) &&
                $this->params[0] === '_tests'
            );
        });

        // Test 3: hasOption() - test with existing option
        $this->test('hasOption() with --p flag', function() {
            // Check if --p option exists in argv
            $hasP = $this->hasOption('p');
            return is_bool($hasP);
        });

        // Test 4: hasOption() - test with non-existing option
        $this->test('hasOption() with non-existing flag', function() {
            $hasNonExisting = $this->hasOption('nonexistingoption12345');
            return ($hasNonExisting === false);
        });

        // Test 5: getOptionVar() - test retrieving option value
        $this->test('getOptionVar() returns null for non-existing', function() {
            $value = $this->getOptionVar('nonexistingoption12345');
            return ($value === null);
        });

        // Test 6: vars array from --key=value arguments
        $this->test('vars array initialization', function() {
            return is_array($this->vars);
        });

        // Test 7: method property
        $this->test('method property defaults to GET', function() {
            return ($this->method === 'GET');
        });

        $this->sendTerminal("");
    }

    /**
     * Test cache methods
     */
    function METHOD_cache()
    {
        $this->sendTerminal("--- CACHE METHODS TESTS ---\n");

        // Test 8: cache property is CoreCache instance
        $this->test('cache property is CoreCache instance', function() {
            return ($this->cache !== null && get_class($this->cache) === 'CoreCache');
        });

        // Test 9: setCacheVar() and getCacheVar()
        $this->test('setCacheVar() and getCacheVar()', function() {
            $testKey = 'test_cache_var_' . uniqid();
            $testValue = 'test_value_' . time();

            $this->setCacheVar($testKey, $testValue);
            $retrieved = $this->getCacheVar($testKey);

            return ($retrieved === $testValue);
        });

        // Test 10: getCacheVar() returns null for non-existing
        $this->test('getCacheVar() returns null for non-existing', function() {
            $value = $this->getCacheVar('nonexisting_cache_key_' . uniqid());
            return ($value === null);
        });

        // Test 11: readCache() initializes cache_data
        $this->test('readCache() initializes cache_data', function() {
            $this->readCache();
            return is_array($this->cache_data);
        });

        // Test 12: cleanCache() clears cache_data
        $this->test('cleanCache() clears cache_data', function() {
            // Set a cache var first
            $this->setCacheVar('temp_test_var', 'value');

            // Clean cache
            $this->cleanCache();

            // Cache data should be empty array
            return (is_array($this->cache_data) && empty($this->cache_data));
        });

        // Test 13: cache_data is array after operations
        $this->test('cache_data is array after operations', function() {
            $testKey = 'persistence_test_' . uniqid();
            $testValue = 'persistent_value_' . time();

            $this->setCacheVar($testKey, $testValue);

            // cache_data should be an array and contain our key
            return (is_array($this->cache_data) && isset($this->cache_data[$testKey]));
        });

        $this->sendTerminal("");
    }

    /**
     * Test error handling methods
     */
    function METHOD_errors()
    {
        $this->sendTerminal("--- ERROR HANDLING TESTS ---\n");

        // Test 14: error properties initialization
        $this->test('error properties initialized', function() {
            return (
                $this->error === false &&
                $this->errorCode === '' &&
                is_array($this->errorMsg)
            );
        });

        // Test 15: addError() sets error state
        $this->test('addError() sets error state', function() {
            // Create a fresh instance to test
            $testScript = new class($this->core, $this->argv) extends CoreScripts {};

            $testScript->addError('Test error message');

            return (
                $testScript->error === true &&
                count($testScript->errorMsg) === 1 &&
                $testScript->errorMsg[0] === 'Test error message'
            );
        });

        // Test 16: addError() returns false
        $this->test('addError() returns false', function() {
            $testScript = new class($this->core, $this->argv) extends CoreScripts {};
            $result = $testScript->addError('Test error');
            return ($result === false);
        });

        // Test 17: setErrorFromCodelib() sets error code and message
        $this->test('setErrorFromCodelib() sets error', function() {
            $testScript = new class($this->core, $this->argv) extends CoreScripts {};

            $result = $testScript->setErrorFromCodelib('test-code', 'Test error message');

            return (
                $result === false &&
                $testScript->error === true &&
                $testScript->errorCode === 'test-code' &&
                count($testScript->errorMsg) > 0
            );
        });

        // Test 18: Multiple errors accumulate in errorMsg
        $this->test('Multiple errors accumulate', function() {
            $testScript = new class($this->core, $this->argv) extends CoreScripts {};

            $testScript->addError('Error 1');
            $testScript->addError('Error 2');
            $testScript->addError('Error 3');

            return (count($testScript->errorMsg) === 3);
        });

        $this->sendTerminal("");
    }

    /**
     * Test utility methods
     */
    function METHOD_methods()
    {
        $this->sendTerminal("--- UTILITY METHODS TESTS ---\n");

        // Test 19: useFunction() with existing method
        $this->test('useFunction() with existing method', function() {
            $testScript = new class($this->core, $this->argv) extends CoreScripts {
                public $testMethodCalled = false;

                public function testMethod() {
                    $this->testMethodCalled = true;
                }
            };

            $result = $testScript->useFunction('testMethod');

            return ($result === true && $testScript->testMethodCalled === true);
        });

        // Test 20: useFunction() with non-existing method
        $this->test('useFunction() with non-existing method', function() {
            $testScript = new class($this->core, $this->argv) extends CoreScripts {};

            $result = $testScript->useFunction('nonExistingMethod');

            return ($result === false);
        });

        // Test 21: sendTerminal() executes without error
        $this->test('sendTerminal() executes', function() {
            ob_start();
            $result = $this->sendTerminal('Test output');
            $output = ob_get_clean();

            return ($result === true && strpos($output, 'Test output') !== false);
        });

        // Test 22: sendTerminal() with array
        $this->test('sendTerminal() with array', function() {
            ob_start();
            $result = $this->sendTerminal(['key' => 'value']);
            $output = ob_get_clean();

            return ($result === true && !empty($output));
        });

        // Test 23: sendTerminal() with multiple arguments
        $this->test('sendTerminal() with multiple args', function() {
            ob_start();
            $result = $this->sendTerminal('Line 1', 'Line 2', 'Line 3');
            $output = ob_get_clean();

            return (
                $result === true &&
                strpos($output, 'Line 1') !== false &&
                strpos($output, 'Line 2') !== false &&
                strpos($output, 'Line 3') !== false
            );
        });

        // Test 24: time property initialization
        $this->test('time property initialized', function() {
            return ($this->time !== null && is_float($this->time));
        });

        // Test 25: core property is Core7 instance
        $this->test('core property is Core7 instance', function() {
            return ($this->core !== null && get_class($this->core) === 'Core7');
        });

        // Test 26: formParams is array
        $this->test('formParams is array', function() {
            return is_array($this->formParams);
        });

        // Test 27: argv is array
        $this->test('argv is array', function() {
            return is_array($this->argv);
        });

        $this->sendTerminal("");
    }

    /**
     * Test integration scenarios
     */
    function METHOD_integration()
    {
        $this->sendTerminal("--- INTEGRATION TESTS ---\n");

        // Test 28: Complete workflow - cache, error, method
        $this->test('Complete workflow integration', function() {
            $testScript = new class($this->core, $this->argv) extends CoreScripts {
                public $processCount = 0;

                public function processData() {
                    $this->processCount++;

                    // Try to get cached data
                    $cached = $this->getCacheVar('workflow_test_' . $this->processCount);

                    if (!$cached) {
                        // Set cache
                        $this->setCacheVar('workflow_test_' . $this->processCount, 'processed_data');
                        return 'new';
                    }

                    return 'cached';
                }
            };

            // First call should return 'new' (no cache)
            $result1 = $testScript->processData();

            // Second call with same key should return 'cached'
            $testScript->processCount--; // Reuse same key
            $result2 = $testScript->processData();

            return ($result1 === 'new' && $result2 === 'cached');
        });

        // Test 29: Error handling with useFunction
        $this->test('Error handling with useFunction', function() {
            $testScript = new class($this->core, $this->argv) extends CoreScripts {
                public function failingMethod() {
                    $this->addError('Method failed');
                    return false;
                }
            };

            $result = $testScript->useFunction('failingMethod');

            return (
                $result === true && // useFunction returns true if method exists
                $testScript->error === true
            );
        });

        // Test 30: Method chaining with error state
        $this->test('Method chaining with error state', function() {
            $testScript = new class($this->core, $this->argv) extends CoreScripts {
                public function step1() {
                    if ($this->error) return false;
                    $this->setCacheVar('step1', 'done');
                    return true;
                }

                public function step2() {
                    if ($this->error) return false;
                    $this->addError('Step 2 failed');
                    return false;
                }

                public function step3() {
                    if ($this->error) return false;
                    $this->setCacheVar('step3', 'done');
                    return true;
                }
            };

            $testScript->step1();
            $testScript->step2();
            $testScript->step3();

            return (
                $testScript->getCacheVar('step1') === 'done' &&
                $testScript->getCacheVar('step3') === null && // Should not execute
                $testScript->error === true
            );
        });

        $this->sendTerminal("");
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

        $this->sendTerminal("");

        // Return appropriate exit code
        return ($this->failedCount === 0);
    }
}
