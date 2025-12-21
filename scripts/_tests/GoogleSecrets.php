<?php

/**
 * https://cloudframework.io
 * GoogleSecrets Test Script - Comprehensive test suite for GoogleSecrets class
 *
 * IMPORTANT: This test requires:
 * - Google Cloud Secret Manager API enabled
 * - Proper GCP credentials configured
 * - PROJECT_ID or core.gcp.project_id configured
 * - Appropriate IAM permissions (roles/secretmanager.admin or equivalent)
 *
 * Usage:
 *   composer script -- tests/GoogleSecrets                  # Run all tests
 *   composer script -- tests/GoogleSecrets/all              # Run all tests
 *   composer script -- tests/GoogleSecrets/basic            # Run basic tests only
 *   composer script -- tests/GoogleSecrets/versions         # Run version management tests
 *   composer script -- tests/GoogleSecrets/iam              # Run IAM/access control tests
 *   composer script -- tests/GoogleSecrets/cleanup          # Cleanup test secrets
 *
 * Test Mode:
 *   By default, tests will SKIP actual GCP operations if credentials are not available.
 *   Set environment variable to enable actual GCP testing:
 *     RUN_GCP_TESTS=1 composer script -- tests/GoogleSecrets
 */
class Script extends CoreScripts
{
    private $testResults = [];
    private $testCount = 0;
    private $passedCount = 0;
    private $failedCount = 0;
    private $skippedCount = 0;
    private $testSecretPrefix = 'cftest_secret_';
    private $createdSecrets = [];
    private $runGcpTests = true;
    private $testMember = null;

    /**
     * This function is executed as the main method of the class
     */
    function main()
    {
        $this->sendTerminal("===========================================");
        $this->sendTerminal("GoogleSecrets Class Test Suite");
        $this->sendTerminal("===========================================\n");

        // Check if Google Cloud Secret Manager library is installed
        if (!class_exists('Google\Cloud\SecretManager\V1\Client\SecretManagerServiceClient')) {
            $this->sendTerminal("ERROR: Google Cloud Secret Manager library is not installed!\n");
            $this->sendTerminal("The GoogleSecrets class requires google/cloud-secret-manager.\n");
            $this->sendTerminal("\nTo install the library, run:");
            $this->sendTerminal("  composer require google/cloud-secret-manager\n");
            $this->sendTerminal("Or it should already be included in google/cloud packages.\n");
            return $this->setErrorFromCodelib('system-error', 'Required library google/cloud-secret-manager is not installed');
        }

        if(!$this->testMember = $this->core->security->getGoogleEmailAccount()) {
            return $this->setErrorFromCodelib('system-error', 'No Google email account found');
        }
        $this->sendTerminal("Using testMember: {$this->testMember}");


        // Check if we should run actual GCP tests
        $this->runGcpTests = !(getenv('RUN_GCP_TESTS') === '0' || getenv('RUN_GCP_TESTS') === 'false');

        if (!$this->runGcpTests) {
            $this->sendTerminal("INFO: Running in SIMULATION mode (no actual GCP operations)");
            $this->sendTerminal("      Set RUN_GCP_TESTS=1 to run actual GCP integration tests\n");
        } else {
            $this->sendTerminal("INFO: Running in GCP INTEGRATION mode (actual GCP operations)");
            $this->sendTerminal("      Test secrets will be created with prefix: {$this->testSecretPrefix}\n");
        }

        // Get test method from params[2], default to 'all'
        $method = (isset($this->params[2])) ? $this->params[2] : 'all';
        $method = str_replace('-', '_', $method);

        // Call internal METHOD_{$method}
        if (!$this->useFunction('METHOD_' . $method)) {
            return ($this->setErrorFromCodelib('params-error', "/{$method} is not implemented. Available: all, basic, versions, iam, cleanup"));
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
        $this->METHOD_versions();
        $this->METHOD_iam();
        $this->METHOD_errors();

        if ($this->runGcpTests && !empty($this->createdSecrets)) {
            $this->sendTerminal("\n--- CLEANUP ---\n");
            $this->METHOD_cleanup();
        }
    }

    /**
     * Test basic methods (constructor, create, get, delete, list)
     */
    function METHOD_basic()
    {
        $this->sendTerminal("--- BASIC METHODS TESTS ---\n");

        // Test 1: Constructor without project_id
        $this->test('Constructor (missing project_id)', function() {
            // Temporarily unset config
            $originalProjectId = $this->core->config->get('core.gcp.project_id');
            $originalSecretsProjectId = $this->core->config->get('core.gcp.secrets.project_id');

            // Store original env var
            $originalEnvProjectId = getenv('PROJECT_ID');

            // Clear all project_id sources
            if (isset($this->core->config->data['core.gcp.project_id'])) {
                unset($this->core->config->data['core.gcp.project_id']);
            }
            if (isset($this->core->config->data['core.gcp.secrets.project_id'])) {
                unset($this->core->config->data['core.gcp.secrets.project_id']);
            }
            if ($originalEnvProjectId !== false) {
                putenv('PROJECT_ID=');
            }

            $secrets = $this->createFreshSecrets();
            $hasError = $secrets->error;

            // Restore config and env
            if ($originalProjectId !== null) {
                $this->core->config->data['core.gcp.project_id'] = $originalProjectId;
            }
            if ($originalSecretsProjectId !== null) {
                $this->core->config->data['core.gcp.secrets.project_id'] = $originalSecretsProjectId;
            }
            if ($originalEnvProjectId !== false) {
                putenv("PROJECT_ID={$originalEnvProjectId}");
            }

            return $hasError === true;
        });

        // Test 2: Constructor with project_id
        $this->test('Constructor (with project_id)', function() {
            if (!$this->runGcpTests) {
                return 'SKIP';
            }

            $secrets = $this->createFreshSecrets();
            return ($secrets !== null && !$secrets->error && $secrets->project_id !== null);
        });

        // Test 3: createSecret
        $this->test('createSecret', function() {
            if (!$this->runGcpTests) {
                return 'SKIP';
            }

            $secrets = $this->createFreshSecrets();
            if ($secrets->error) return false;

            $secretId = $this->testSecretPrefix . uniqid();
            $this->createdSecrets[] = $secretId;

            $result = $secrets->createSecret($secretId);
            return ($result !== false && !$secrets->error);
        });

        // Test 4: createSecret (duplicate should fail)
        $this->test('createSecret (duplicate)', function() {
            if (!$this->runGcpTests) {
                return 'SKIP';
            }

            $secrets = $this->createFreshSecrets();
            if ($secrets->error) return false;

            // Use the first created secret
            if (empty($this->createdSecrets)) {
                return 'SKIP';
            }

            $secretId = $this->createdSecrets[0];
            $result = $secrets->createSecret($secretId);

            // Should return false and have error
            return ($result === false && $secrets->error);
        });

        // Test 5: addSecretVersion
        $this->test('addSecretVersion', function() {
            if (!$this->runGcpTests) {
                return 'SKIP';
            }

            $secrets = $this->createFreshSecrets();
            if ($secrets->error || empty($this->createdSecrets)) {
                return 'SKIP';
            }

            $secretId = $this->createdSecrets[0];
            $result = $secrets->addSecretVersion($secretId, 'test-secret-value-123');

            return ($result !== false && !$secrets->error);
        });

        // Test 6: getSecret
        $this->test('getSecret (latest version)', function() {
            if (!$this->runGcpTests) {
                return 'SKIP';
            }

            $secrets = $this->createFreshSecrets();
            if ($secrets->error || empty($this->createdSecrets)) {
                return 'SKIP';
            }

            $secretId = $this->createdSecrets[0];
            $value = $secrets->getSecret($secretId, 'latest');

            return ($value === 'test-secret-value-123' && !$secrets->error);
        });

        // Test 7: getSecret (specific version)
        $this->test('getSecret (version 1)', function() {
            if (!$this->runGcpTests) {
                return 'SKIP';
            }

            $secrets = $this->createFreshSecrets();
            if ($secrets->error || empty($this->createdSecrets)) {
                return 'SKIP';
            }

            $secretId = $this->createdSecrets[0];
            $value = $secrets->getSecret($secretId, '1');

            return ($value !== false && !$secrets->error);
        });

        // Test 8: getSecret (nonexistent secret)
        $this->test('getSecret (nonexistent)', function() {
            if (!$this->runGcpTests) {
                return 'SKIP';
            }

            $secrets = $this->createFreshSecrets();
            if ($secrets->error) return false;

            $value = $secrets->getSecret('nonexistent_secret_' . uniqid(), 'latest');

            // Should return false and have error
            return ($value === false && $secrets->error);
        });

        // Test 9: listSecrets
        $this->test('listSecrets', function() {
            if (!$this->runGcpTests) {
                return 'SKIP';
            }

            $secrets = $this->createFreshSecrets();
            if ($secrets->error) return false;

            $secretsList = $secrets->listSecrets();

            return (is_array($secretsList) && !$secrets->error);
        });

        // Test 10: listSecrets (verify created secret exists)
        $this->test('listSecrets (contains test secret)', function() {
            if (!$this->runGcpTests) {
                return 'SKIP';
            }

            $secrets = $this->createFreshSecrets();
            if ($secrets->error || empty($this->createdSecrets)) {
                return 'SKIP';
            }

            $secretsList = $secrets->listSecrets();

            return (is_array($secretsList) && in_array($this->createdSecrets[0], $secretsList));
        });

        $this->sendTerminal("");
    }

    /**
     * Test version management methods
     */
    function METHOD_versions()
    {
        $this->sendTerminal("--- VERSION MANAGEMENT TESTS ---\n");

        // Test 11: addSecretVersion (multiple versions)
        $this->test('addSecretVersion (version 2)', function() {
            if (!$this->runGcpTests) {
                return 'SKIP';
            }

            $secrets = $this->createFreshSecrets();
            if ($secrets->error || empty($this->createdSecrets)) {
                return 'SKIP';
            }

            $secretId = $this->createdSecrets[0];
            $result = $secrets->addSecretVersion($secretId, 'test-secret-value-v2');

            return ($result !== false && !$secrets->error);
        });

        // Test 12: getSecretVersions
        $this->test('getSecretVersions', function() {
            if (!$this->runGcpTests) {
                return 'SKIP';
            }

            $secrets = $this->createFreshSecrets();
            if ($secrets->error || empty($this->createdSecrets)) {
                return 'SKIP';
            }

            $secretId = $this->createdSecrets[0];
            $versions = $secrets->getSecretVersions($secretId);

            return (is_array($versions) && count($versions) >= 2 && !$secrets->error);
        });

        // Test 13: getSecretVersions (verify structure)
        $this->test('getSecretVersions (structure)', function() {
            if (!$this->runGcpTests) {
                return 'SKIP';
            }

            $secrets = $this->createFreshSecrets();
            if ($secrets->error || empty($this->createdSecrets)) {
                return 'SKIP';
            }

            $secretId = $this->createdSecrets[0];
            $versions = $secrets->getSecretVersions($secretId);

            if (!is_array($versions) || empty($versions)) {
                return false;
            }

            $firstVersion = $versions[0];
            return (
                isset($firstVersion['version']) &&
                isset($firstVersion['state']) &&
                isset($firstVersion['create_time']) &&
                isset($firstVersion['name'])
            );
        });

        // Test 14: getSecret (verify latest is version 2)
        $this->test('getSecret (latest returns v2)', function() {
            if (!$this->runGcpTests) {
                return 'SKIP';
            }

            $secrets = $this->createFreshSecrets();
            if ($secrets->error || empty($this->createdSecrets)) {
                return 'SKIP';
            }

            $secretId = $this->createdSecrets[0];
            $value = $secrets->getSecret($secretId, 'latest');

            return ($value === 'test-secret-value-v2' && !$secrets->error);
        });

        $this->sendTerminal("");
    }

    /**
     * Test IAM/access control methods
     */
    function METHOD_iam()
    {
        $this->sendTerminal("--- IAM & ACCESS CONTROL TESTS ---\n");

        // Test 15: getSecretAccessList
        $this->test('getSecretAccessList', function() {
            if (!$this->runGcpTests) {
                return 'SKIP';
            }

            $secrets = $this->createFreshSecrets();
            if ($secrets->error || empty($this->createdSecrets)) {
                return 'SKIP';
            }

            $secretId = $this->createdSecrets[0];
            $accessList = $secrets->getSecretAccessList($secretId);

            return (is_array($accessList) && !$secrets->error);
        });

        // Test 16: grantSecretAccess (single member)
        $this->test('grantSecretAccess (single member)', function() {
            if (!$this->runGcpTests) {
                return 'SKIP';
            }

            $secrets = $this->createFreshSecrets();
            if ($secrets->error || empty($this->createdSecrets)) {
                return 'SKIP';
            }

            $secretId = $this->createdSecrets[0];
            $result = $secrets->grantSecretAccess($secretId, 'user:'.$this->testMember);

            return ($result === true && !$secrets->error);
        });

        // Test 17: grantSecretAccess (multiple members)
        $this->test('grantSecretAccess (multiple members)', function() {
            if (!$this->runGcpTests) {
                return 'SKIP';
            }

            $secrets = $this->createFreshSecrets();
            if ($secrets->error || empty($this->createdSecrets)) {
                return 'SKIP';
            }

            $secretId = $this->createdSecrets[0];
            $testMembers = [
                'user:'.$this->testMember
            ];

            $result = $secrets->grantSecretAccess($secretId, $testMembers);

            return ($result === true && !$secrets->error);
        });

        // Test 18: grantSecretAccess (custom role)
        $this->test('grantSecretAccess (custom role)', function() {
            if (!$this->runGcpTests) {
                return 'SKIP';
            }

            $secrets = $this->createFreshSecrets();
            if ($secrets->error || empty($this->createdSecrets)) {
                return 'SKIP';
            }

            $secretId = $this->createdSecrets[0];
            $testMember = 'user:'.$this->testMember;

            $result = $secrets->grantSecretAccess(
                $secretId,
                $testMember,
                'roles/secretmanager.admin'
            );

            return ($result === true && !$secrets->error);
        });

        // Test 19: getSecretAccessList (verify granted access)
        $this->test('getSecretAccessList (after grant)', function() {
            if (!$this->runGcpTests) {
                return 'SKIP';
            }

            $secrets = $this->createFreshSecrets();
            if ($secrets->error || empty($this->createdSecrets)) {
                return 'SKIP';
            }

            $secretId = $this->createdSecrets[0];
            $accessList = $secrets->getSecretAccessList($secretId);

            if (!is_array($accessList)) {
                return false;
            }

            // Check if any binding has members
            $hasMembers = false;
            foreach ($accessList as $binding) {
                if (!empty($binding['members'])) {
                    $hasMembers = true;
                    break;
                }
            }

            return $hasMembers;
        });

        // Test 20: revokeAccess (single member)
        $this->test('revokeAccess (single member)', function() {
            if (!$this->runGcpTests) {
                return 'SKIP';
            }

            $secrets = $this->createFreshSecrets();
            if ($secrets->error || empty($this->createdSecrets)) {
                return 'SKIP';
            }

            $secretId = $this->createdSecrets[0];
            $testMember = 'user:'.$this->testMember;

            $result = $secrets->revokeAccess($secretId, $testMember);

            return ($result === true && !$secrets->error);
        });

        // Test 21: revokeAccess (multiple members)
        $this->test('revokeAccess (multiple members)', function() {
            if (!$this->runGcpTests) {
                return 'SKIP';
            }

            $secrets = $this->createFreshSecrets();
            if ($secrets->error || empty($this->createdSecrets)) {
                return 'SKIP';
            }

            $secretId = $this->createdSecrets[0];
            $testMembers = [
                'user:'.$this->testMember
            ];

            $result = $secrets->revokeAccess($secretId, $testMembers);

            return ($result === true && !$secrets->error);
        });

        // Test 22: revokeAccess (nonexistent member)
        $this->test('revokeAccess (nonexistent)', function() {
            if (!$this->runGcpTests) {
                return 'SKIP';
            }

            $secrets = $this->createFreshSecrets();
            if ($secrets->error || empty($this->createdSecrets)) {
                return 'SKIP';
            }

            $secretId = $this->createdSecrets[0];
            $testMember = 'user:nonexistent@example.com';

            $result = $secrets->revokeAccess($secretId, $testMember);

            // Should succeed (nothing to revoke is not an error)
            return ($result === true && !$secrets->error);
        });

        $this->sendTerminal("");
    }

    /**
     * Test error handling and edge cases
     */
    function METHOD_errors()
    {
        $this->sendTerminal("--- ERROR HANDLING TESTS ---\n");

        // Test 23: reset() method
        $this->test('reset() clears errors', function() {
            $secrets = $this->createFreshSecrets();

            // Trigger an error
            $secrets->addError('test-error', 'Test error message');

            // Reset
            $result = $secrets->reset();

            return ($result === true && !$secrets->error && empty($secrets->errorMsg));
        });

        // Test 24: Error propagation (operation after error)
        $this->test('Error propagation', function() {
            $secrets = $this->createFreshSecrets();

            // Set error state
            $secrets->addError('test-error', 'Test error');

            // Try to perform operation (should return false immediately)
            $result = $secrets->listSecrets();

            return ($result === false);
        });

        $this->sendTerminal("");
    }

    /**
     * Cleanup test secrets
     */
    function METHOD_cleanup()
    {
        $this->sendTerminal("Cleaning up test secrets...\n");

        if (empty($this->createdSecrets)) {
            $this->sendTerminal("No test secrets to clean up.\n");
            return;
        }

        $cleaned = 0;
        $failed = 0;

        foreach ($this->createdSecrets as $secretId) {
            $secrets = $this->createFreshSecrets();
            if ($secrets->error) {
                $failed++;
                continue;
            }

            $result = $secrets->deleteSecret($secretId);
            if ($result) {
                $this->sendTerminal("  ✓ Deleted: {$secretId}");
                $cleaned++;
            } else {
                $this->sendTerminal("  ✗ Failed to delete: {$secretId}");
                $failed++;
            }
        }

        $this->sendTerminal("\nCleanup Summary:");
        $this->sendTerminal("  Deleted: {$cleaned}");
        $this->sendTerminal("  Failed:  {$failed}");
        $this->sendTerminal("");
    }

    /**
     * Helper method to create a fresh GoogleSecrets instance
     * Uses unique test_id to bypass Core7's instance caching
     */
    private function createFreshSecrets()
    {
        return $this->core->loadClass('GoogleSecrets', ['_test_id' => uniqid('test_', true)]);
    }

    /**
     * Helper method to run a test
     */
    private function test($name, $callback)
    {
        $this->testCount++;
        try {
            $result = $callback();

            if ($result === 'SKIP') {
                $this->skippedCount++;
                $this->testResults[] = ['name' => $name, 'status' => 'SKIP'];
                $this->sendTerminal("⊘ Test #{$this->testCount}: {$name} - SKIPPED");
            } elseif ($result) {
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
        $this->sendTerminal("Skipped:      {$this->skippedCount}");

        $executedTests = $this->testCount - $this->skippedCount;
        $passRate = $executedTests > 0 ? round(($this->passedCount / $executedTests) * 100, 2) : 0;
        $this->sendTerminal("Pass Rate:    {$passRate}% (of executed tests)");
        $this->sendTerminal("===========================================");

        if ($this->failedCount > 0) {
            $this->sendTerminal("\nFailed Tests:");
            foreach ($this->testResults as $result) {
                if ($result['status'] === 'FAIL' || $result['status'] === 'ERROR') {
                    $message = isset($result['message']) ? " - " . $result['message'] : '';
                    $this->sendTerminal("  - {$result['name']}{$message}");
                }
            }
        }

        if (!$this->runGcpTests) {
            $this->sendTerminal("\nNOTE: Most tests were SKIPPED (simulation mode)");
            $this->sendTerminal("      Run with RUN_GCP_TESTS=1 to execute actual GCP operations");
        }

        $this->sendTerminal("");

        // Return appropriate exit code
        return ($this->failedCount === 0);
    }
}
