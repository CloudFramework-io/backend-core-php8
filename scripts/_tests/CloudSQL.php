<?php
/**
 * Test script for CloudSQL class
 *
 * Run: php runscript.php tests/CloudSQL
 *
 * Tests:
 * - Connection (connect, close)
 * - Configuration (getConf, setConf)
 * - Query execution (getDataFromQuery, command)
 * - CRUD operations (INSERT, SELECT, UPDATE, DELETE)
 * - Table operations (tableExists)
 * - Model extraction (getModelFromTable, getSimpleModelFromTable, getInterfaceModelFromTable)
 * - Query builder (joinQueryValues, scapeValue, getSecuredSqlString, getQueryFromSearch)
 * - Error handling (error, getError, setError)
 */
class Script extends CoreScripts {

    /** @var CloudSQL */
    private $db;

    private $test_table = 'cf_test_cloudsql';
    private $test_results = [];

    function main() {
        $this->core->logs->add('Starting CloudSQL Test Script');

        // Check database configuration
        $dbServer = $this->core->config->get('dbServer');
        $dbSocket = $this->core->config->get('dbSocket');
        $dbName = $this->core->config->get('dbName');

        if (!$dbServer && !$dbSocket) {
            $this->sendTerminal('ERROR: No database server configured.');
            $this->sendTerminal('Please configure in config.json or local_config.json:');
            $this->sendTerminal('  "dbServer": "localhost",');
            $this->sendTerminal('  "dbUser": "root",');
            $this->sendTerminal('  "dbPassword": "",');
            $this->sendTerminal('  "dbName": "test_db"');
            return;
        }

        // Initialize CloudSQL class
        $this->db = $this->core->loadClass('CloudSQL');
        if (!$this->db) {
            $this->sendTerminal('ERROR: Failed to load CloudSQL class');
            return;
        }

        $this->sendTerminal("=== CLOUDSQL TEST SCRIPT ===");
        $this->sendTerminal("Server: " . ($dbServer ?: $dbSocket));
        $this->sendTerminal("Database: {$dbName}");
        $this->sendTerminal("Version: {$this->db->_version}");
        $this->sendTerminal("");

        // Run tests
        $this->testConnection();

        if (!$this->db->error()) {
            $this->testConfiguration();
            $this->testSimpleQuery();
            $this->testParameterizedQuery();
            $this->testQueryBuilder();
            $this->testSecurityFunctions();
            $this->testCreateTable();
            $this->testTableExists();
            $this->testInsert();
            $this->testSelect();
            $this->testUpdate();
            $this->testDelete();
            $this->testModelFromTable();
            $this->testSimpleModelFromTable();
            $this->testInterfaceModelFromTable();
            $this->testErrorHandling();
        }

        // Show summary
        $this->showSummary();

        // Cleanup
        $this->cleanup();
    }

    /**
     * Test database connection
     */
    private function testConnection() {
        $this->sendTerminal("TEST: connect()");

        $connected = $this->db->connect();
        if ($this->db->error()) {
            $this->addTestResult('connect()', false, $this->db->getError());
            return;
        }

        $this->sendTerminal("  - Connection established");
        $this->addTestResult('connect()', $connected === true);
    }

    /**
     * Test configuration methods
     */
    private function testConfiguration() {
        $this->sendTerminal("TEST: Configuration (getConf/setConf)");

        $server = $this->db->getConf('dbServer');
        $this->sendTerminal("  - getConf('dbServer'): {$server}");

        $originalServer = $this->db->getConf('dbServer');
        $this->db->setConf('dbServer', 'test-server-temp');
        $newServer = $this->db->getConf('dbServer');
        $this->db->setConf('dbServer', $originalServer);

        $passed = ($newServer === 'test-server-temp');
        $this->sendTerminal("  - setConf() works: " . ($passed ? 'YES' : 'NO'));

        $this->addTestResult('getConf() & setConf()', $passed);
    }

    /**
     * Test simple query execution
     */
    private function testSimpleQuery() {
        $this->sendTerminal("TEST: Simple Query (getDataFromQuery)");

        $result = $this->db->getDataFromQuery("SELECT 1 as test_value");
        if ($this->db->error()) {
            $this->addTestResult('getDataFromQuery() - simple', false, $this->db->getError());
            return;
        }

        $passed = is_array($result) && isset($result[0]['test_value']) && $result[0]['test_value'] == 1;
        $this->sendTerminal("  - SELECT 1: " . ($passed ? 'OK' : 'FAIL'));
        $this->sendTerminal("  - Last query: " . substr($this->db->getQuery(), 0, 50));

        $this->addTestResult('getDataFromQuery() - simple', $passed);
    }

    /**
     * Test parameterized query
     */
    private function testParameterizedQuery() {
        $this->sendTerminal("TEST: Parameterized Query");

        $result = $this->db->getDataFromQuery(
            "SELECT %s as name, %s as value, %s as number",
            ['John Doe', 'test@example.com', '42']
        );
        if ($this->db->error()) {
            $this->addTestResult('getDataFromQuery() - params', false, $this->db->getError());
            return;
        }

        $passed = is_array($result)
            && $result[0]['name'] === 'John Doe'
            && $result[0]['value'] === 'test@example.com'
            && $result[0]['number'] === '42';

        $this->sendTerminal("  - Name: " . ($result[0]['name'] ?? 'N/A'));
        $this->sendTerminal("  - Value: " . ($result[0]['value'] ?? 'N/A'));
        $this->sendTerminal("  - Number: " . ($result[0]['number'] ?? 'N/A'));

        $this->addTestResult('getDataFromQuery() - params', $passed);
    }

    /**
     * Test query builder functions
     */
    private function testQueryBuilder() {
        $this->sendTerminal("TEST: Query Builder Functions");

        // Test joinQueryValues
        $query = $this->db->joinQueryValues(
            "SELECT * FROM users WHERE name = '%s' AND id = %s",
            ['John', '123']
        );
        $joinPassed = strpos($query, 'John') !== false && strpos($query, '123') !== false;
        $this->sendTerminal("  - joinQueryValues(): " . ($joinPassed ? 'OK' : 'FAIL'));

        // Test getQueryFromSearch
        $searchQuery = $this->db->getQueryFromSearch('test', ['name', 'email'], 'LIKE', 'OR');
        $searchPassed = strpos($searchQuery, 'name') !== false && strpos($searchQuery, 'email') !== false;
        $this->sendTerminal("  - getQueryFromSearch(): " . ($searchPassed ? 'OK' : 'FAIL'));

        $this->addTestResult('Query Builder', $joinPassed && $searchPassed);
    }

    /**
     * Test security functions
     */
    private function testSecurityFunctions() {
        $this->sendTerminal("TEST: Security Functions");

        // Test scapeValue
        $escaped = $this->db->scapeValue("O'Reilly \"test\"");
        $escapePassed = strpos($escaped, "\\'") !== false || strpos($escaped, "''") !== false;
        $this->sendTerminal("  - scapeValue(): " . ($escapePassed ? 'OK' : 'FAIL'));

        // Test getSecuredSqlString
        $malicious = "DELETE FROM users; DROP TABLE users; INSERT INTO hack";
        $secured = $this->db->getSecuredSqlString($malicious);
        $securedPassed = stripos($secured, 'delete') === false
            && stripos($secured, 'drop') === false
            && stripos($secured, 'insert') === false;
        $this->sendTerminal("  - getSecuredSqlString(): " . ($securedPassed ? 'OK' : 'FAIL'));

        // Test SQL injection prevention in query
        $injectionTest = "'; DROP TABLE users; --";
        $result = $this->db->getDataFromQuery("SELECT %s as safe_value", [$injectionTest]);
        $injectionPassed = !$this->db->error() && is_array($result);
        $this->sendTerminal("  - SQL Injection Prevention: " . ($injectionPassed ? 'OK' : 'FAIL'));

        $this->addTestResult('Security Functions', $escapePassed && $securedPassed && $injectionPassed);
    }

    /**
     * Test CREATE TABLE
     */
    private function testCreateTable() {
        $this->sendTerminal("TEST: CREATE TABLE (command)");

        // Drop table if exists first
        $this->db->command("DROP TABLE IF EXISTS {$this->test_table}");

        $createSQL = "CREATE TABLE {$this->test_table} (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            email VARCHAR(255),
            status ENUM('active', 'inactive') DEFAULT 'active',
            score DECIMAL(10,2) DEFAULT 0.00,
            metadata JSON,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_status (status),
            UNIQUE KEY uk_email (email)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='CloudSQL Test Table'";

        $result = $this->db->command($createSQL);
        if ($this->db->error()) {
            $this->addTestResult('CREATE TABLE', false, $this->db->getError());
            return;
        }

        $this->sendTerminal("  - Table created: {$this->test_table}");
        $this->addTestResult('CREATE TABLE', $result === true);
    }

    /**
     * Test tableExists
     */
    private function testTableExists() {
        $this->sendTerminal("TEST: tableExists()");

        $exists = $this->db->tableExists($this->test_table);
        $notExists = !$this->db->tableExists('non_existent_table_xyz_12345');

        $this->sendTerminal("  - Existing table '{$this->test_table}': " . ($exists ? 'TRUE' : 'FALSE'));
        $this->sendTerminal("  - Non-existing table: " . ($notExists ? 'FALSE (correct)' : 'TRUE (incorrect)'));

        $this->addTestResult('tableExists()', $exists && $notExists);
    }

    /**
     * Test INSERT
     */
    private function testInsert() {
        $this->sendTerminal("TEST: INSERT (command)");

        // Insert single record
        $result1 = $this->db->command(
            "INSERT INTO {$this->test_table} (name, email, status, score) VALUES (%s, %s, %s, %s)",
            ['John Doe', 'john@example.com', 'active', '95.50']
        );
        $insertId1 = $this->db->getInsertId();

        // Insert more records
        $this->db->command(
            "INSERT INTO {$this->test_table} (name, email, status, score) VALUES (%s, %s, %s, %s)",
            ['Jane Smith', 'jane@example.com', 'active', '88.75']
        );
        $this->db->command(
            "INSERT INTO {$this->test_table} (name, email, status, score) VALUES (%s, %s, %s, %s)",
            ['Bob Wilson', 'bob@example.com', 'inactive', '72.00']
        );

        if ($this->db->error()) {
            $this->addTestResult('INSERT', false, $this->db->getError());
            return;
        }

        $this->sendTerminal("  - Inserted record ID: {$insertId1}");
        $this->sendTerminal("  - Total records inserted: 3");

        $this->addTestResult('INSERT', $result1 === true && $insertId1 > 0);
    }

    /**
     * Test SELECT
     */
    private function testSelect() {
        $this->sendTerminal("TEST: SELECT (getDataFromQuery)");

        // Select all
        $all = $this->db->getDataFromQuery("SELECT * FROM {$this->test_table}");
        $this->sendTerminal("  - Total records: " . count($all));

        // Select with WHERE
        $active = $this->db->getDataFromQuery(
            "SELECT * FROM {$this->test_table} WHERE status = %s",
            ['active']
        );
        $this->sendTerminal("  - Active records: " . count($active));

        // Select with ORDER BY and LIMIT
        $limited = $this->db->getDataFromQuery(
            "SELECT name, score FROM {$this->test_table} ORDER BY score DESC LIMIT %s",
            ['2']
        );
        $this->sendTerminal("  - Top 2 by score: " . ($limited[0]['name'] ?? 'N/A'));

        // Affected rows
        $affectedRows = $this->db->getAffectedRows();
        $this->sendTerminal("  - Affected rows: {$affectedRows}");

        $passed = count($all) >= 3 && count($active) >= 2 && count($limited) == 2;
        $this->addTestResult('SELECT', $passed);
    }

    /**
     * Test UPDATE
     */
    private function testUpdate() {
        $this->sendTerminal("TEST: UPDATE (command)");

        $result = $this->db->command(
            "UPDATE {$this->test_table} SET score = %s, status = %s WHERE email = %s",
            ['99.99', 'inactive', 'john@example.com']
        );
        $affectedRows = $this->db->getAffectedRows();

        if ($this->db->error()) {
            $this->addTestResult('UPDATE', false, $this->db->getError());
            return;
        }

        // Verify update
        $updated = $this->db->getDataFromQuery(
            "SELECT score, status FROM {$this->test_table} WHERE email = %s",
            ['john@example.com']
        );

        $this->sendTerminal("  - Affected rows: {$affectedRows}");
        $this->sendTerminal("  - New score: " . ($updated[0]['score'] ?? 'N/A'));
        $this->sendTerminal("  - New status: " . ($updated[0]['status'] ?? 'N/A'));

        $passed = $result === true
            && $affectedRows == 1
            && $updated[0]['score'] == '99.99'
            && $updated[0]['status'] == 'inactive';

        $this->addTestResult('UPDATE', $passed);
    }

    /**
     * Test DELETE
     */
    private function testDelete() {
        $this->sendTerminal("TEST: DELETE (command)");

        // Count before
        $before = $this->db->getDataFromQuery("SELECT COUNT(*) as cnt FROM {$this->test_table}");
        $countBefore = $before[0]['cnt'];

        $result = $this->db->command(
            "DELETE FROM {$this->test_table} WHERE email = %s",
            ['bob@example.com']
        );
        $affectedRows = $this->db->getAffectedRows();

        if ($this->db->error()) {
            $this->addTestResult('DELETE', false, $this->db->getError());
            return;
        }

        // Count after
        $after = $this->db->getDataFromQuery("SELECT COUNT(*) as cnt FROM {$this->test_table}");
        $countAfter = $after[0]['cnt'];

        $this->sendTerminal("  - Records before: {$countBefore}");
        $this->sendTerminal("  - Records after: {$countAfter}");
        $this->sendTerminal("  - Affected rows: {$affectedRows}");

        $passed = $result === true && $affectedRows == 1 && ($countBefore - $countAfter) == 1;
        $this->addTestResult('DELETE', $passed);
    }

    /**
     * Test getModelFromTable
     */
    private function testModelFromTable() {
        $this->sendTerminal("TEST: getModelFromTable()");

        $model = $this->db->getModelFromTable($this->test_table);

        if (!isset($model['table_exists']) || $model['table_exists'] !== true) {
            $this->addTestResult('getModelFromTable()', false, 'Table not found');
            return;
        }

        $fields = $model['model']['fields'] ?? [];
        $this->sendTerminal("  - Table exists: YES");
        $this->sendTerminal("  - Fields found: " . count($fields));
        $this->sendTerminal("  - Engine: " . ($model['model']['engine'] ?? 'N/A'));

        // Check primary key detection
        $hasKey = isset($fields['id']['key']) && $fields['id']['key'] === true;
        $this->sendTerminal("  - Primary key detected: " . ($hasKey ? 'YES' : 'NO'));

        // Check index detection
        $hasIndex = isset($fields['status']['index']);
        $this->sendTerminal("  - Index detected: " . ($hasIndex ? 'YES' : 'NO'));

        $passed = count($fields) >= 5 && $hasKey;
        $this->addTestResult('getModelFromTable()', $passed);
    }

    /**
     * Test getSimpleModelFromTable
     */
    private function testSimpleModelFromTable() {
        $this->sendTerminal("TEST: getSimpleModelFromTable()");

        $model = $this->db->getSimpleModelFromTable($this->test_table);

        $hasModel = isset($model['model']) && count($model['model']) > 0;
        $hasMapping = isset($model['mapping']) && count($model['mapping']) > 0;
        $hasEntity = isset($model['mapWithEntity']) && $model['mapWithEntity'] === $this->test_table;

        $this->sendTerminal("  - Model fields: " . count($model['model'] ?? []));
        $this->sendTerminal("  - Mapping entries: " . count($model['mapping'] ?? []));
        $this->sendTerminal("  - Entity: " . ($model['mapWithEntity'] ?? 'N/A'));

        $passed = $hasModel && $hasMapping && $hasEntity;
        $this->addTestResult('getSimpleModelFromTable()', $passed);
    }

    /**
     * Test getInterfaceModelFromTable
     */
    private function testInterfaceModelFromTable() {
        $this->sendTerminal("TEST: getInterfaceModelFromTable()");

        $interface = $this->db->getInterfaceModelFromTable($this->test_table);

        $hasKeyName = isset($interface['KeyName']) && $interface['KeyName'] === $this->test_table;
        $hasInterface = isset($interface['interface']['views']['default']);
        $hasFields = isset($interface['securityAndFields']['fields']) && count($interface['securityAndFields']['fields']) > 0;

        $this->sendTerminal("  - KeyName: " . ($interface['KeyName'] ?? 'N/A'));
        $this->sendTerminal("  - Has default view: " . ($hasInterface ? 'YES' : 'NO'));
        $this->sendTerminal("  - Fields defined: " . count($interface['securityAndFields']['fields'] ?? []));

        $passed = $hasKeyName && $hasInterface && $hasFields;
        $this->addTestResult('getInterfaceModelFromTable()', $passed);
    }

    /**
     * Test error handling
     */
    private function testErrorHandling() {
        $this->sendTerminal("TEST: Error Handling");

        // Create fresh instance
        $db = $this->core->loadClass('CloudSQL');
        $db->connect();

        // Execute invalid query
        $result = $db->getDataFromQuery("SELECT * FROM non_existent_table_xyz_99999");
        $hasError = $db->error();
        $errors = $db->getError();

        $this->sendTerminal("  - Invalid query triggers error: " . ($hasError ? 'YES' : 'NO'));
        $this->sendTerminal("  - getError() returns array: " . (is_array($errors) ? 'YES' : 'NO'));

        // Test setError
        $db2 = $this->core->loadClass('CloudSQL');
        $db2->connect();
        $db2->setError('Custom test error');
        $customError = $db2->error();
        $this->sendTerminal("  - setError() works: " . ($customError ? 'YES' : 'NO'));

        $passed = $hasError && is_array($errors) && count($errors) > 0 && $customError;
        $this->addTestResult('Error Handling', $passed);
    }

    /**
     * Add test result
     */
    private function addTestResult($test_name, $success, $error = null) {
        $this->test_results[] = [
            'test' => $test_name,
            'success' => $success,
            'error' => $error
        ];

        $status = $success ? '✓ PASS' : '✗ FAIL';
        $this->sendTerminal("  [{$status}]");
        if (!$success && $error) {
            $this->sendTerminal("  ERROR: " . json_encode($error));
        }
        $this->sendTerminal("");
    }

    /**
     * Show summary
     */
    private function showSummary() {
        $this->sendTerminal("=== TEST SUMMARY ===");

        $total = count($this->test_results);
        $passed = 0;
        $failed = 0;

        foreach ($this->test_results as $result) {
            if ($result['success']) {
                $passed++;
            } else {
                $failed++;
            }
        }

        $this->sendTerminal("Total tests: {$total}");
        $this->sendTerminal("Passed: {$passed}");
        $this->sendTerminal("Failed: {$failed}");
        $this->sendTerminal("");

        if ($failed > 0) {
            $this->sendTerminal("Failed tests:");
            foreach ($this->test_results as $result) {
                if (!$result['success']) {
                    $this->sendTerminal("  - {$result['test']}");
                    if ($result['error']) {
                        $this->sendTerminal("    Error: " . json_encode($result['error']));
                    }
                }
            }
        }

        // Return test results as JSON
        $this->sendTerminal("");
        $this->sendTerminal("=== TEST RESULTS (JSON) ===");
        $this->sendTerminal([
            'results' => $this->test_results,
            'success' => ($failed === 0),
            'message' => 'CloudSQL Test Complete',
            'total' => $total,
            'passed' => $passed,
            'failed' => $failed,
        ]);
    }

    /**
     * Cleanup - remove test table
     */
    private function cleanup() {
        $this->sendTerminal("=== CLEANUP ===");

        if ($this->db && !$this->db->error()) {
            // Drop test table
            $this->db->command("DROP TABLE IF EXISTS {$this->test_table}");
            $this->sendTerminal("  - Dropped test table: {$this->test_table}");

            // Close connection
            $this->db->close();
            $this->sendTerminal("  - Connection closed");
        }

        $this->sendTerminal("Cleanup complete");
    }
}
