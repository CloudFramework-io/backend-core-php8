# Script Examples

## Overview

This document provides complete, production-ready examples of CloudFramework CLI scripts for common automation tasks. Each example includes:
- Complete source code
- Interactive prompts
- Data processing
- Error handling
- Progress indicators

---

## Table of Contents

1. [Database Migration Script](#database-migration-script)
2. [Data Import Script](#data-import-script)
3. [Backup Script](#backup-script)
4. [User Management Script](#user-management-script)
5. [Email Campaign Script](#email-campaign-script)
6. [Data Sync Script](#data-sync-script)
7. [Report Generation Script](#report-generation-script)
8. [Cleanup Script](#cleanup-script)

---

## Database Migration Script

Migrate data between CloudSQL databases with progress tracking and rollback support.

**File:** `app/scripts/migrate-database.php`

```php
<?php
/**
 * Database Migration Script
 *
 * Usage:
 * php index.php script=migrate-database method=run
 * php index.php script=migrate-database method=rollback
 * php index.php script=migrate-database method=status
 */

use CloudFramework\Patterns\Scripts2020;

class Script extends Scripts2020
{
    private $sourceSQL;
    private $targetSQL;
    private $migrations = [];

    function main()
    {
        $this->sendTerminal('=== Database Migration Script ===', 'blue');
        $this->sendTerminal('');

        // Initialize databases
        if(!$this->initializeDatabases()) {
            return $this->sendTerminal('Failed to initialize databases', 'red');
        }

        // Load migrations
        $this->loadMigrations();

        // Route to method
        $method = $this->getOptionVar('method') ?? $this->promptOptions(
            'Select action:',
            ['run' => 'Run migrations', 'rollback' => 'Rollback last migration', 'status' => 'Check status']
        );

        if(!$this->useFunction('METHOD_' . $method)) {
            $this->sendTerminal('Invalid method', 'red');
        }
    }

    /**
     * Run pending migrations
     */
    public function METHOD_run()
    {
        $this->sendTerminal('Checking for pending migrations...', 'yellow');

        // Get completed migrations
        $completed = $this->getCompletedMigrations();

        // Find pending migrations
        $pending = array_filter($this->migrations, function($migration) use ($completed) {
            return !in_array($migration['id'], $completed);
        });

        if(empty($pending)) {
            return $this->sendTerminal('No pending migrations', 'green');
        }

        $this->sendTerminal('Found ' . count($pending) . ' pending migration(s)', 'yellow');
        $this->sendTerminal('');

        // Confirm
        if(!$this->promptConfirm('Do you want to run these migrations?')) {
            return $this->sendTerminal('Migration cancelled', 'yellow');
        }

        // Run migrations
        foreach($pending as $migration) {
            $this->sendTerminal('Running: ' . $migration['name'], 'blue');

            $startTime = microtime(true);

            try {
                // Start transaction
                $this->targetSQL->command('START TRANSACTION');

                // Run migration
                foreach($migration['queries'] as $query) {
                    $this->targetSQL->command($query);

                    if($this->targetSQL->error()) {
                        throw new Exception('Query failed: ' . implode(', ', $this->targetSQL->getError()));
                    }
                }

                // Mark as completed
                $this->markMigrationCompleted($migration['id'], $migration['name']);

                // Commit transaction
                $this->targetSQL->command('COMMIT');

                $duration = round(microtime(true) - $startTime, 2);
                $this->sendTerminal('✓ Completed in ' . $duration . 's', 'green');

            } catch(Exception $e) {
                // Rollback transaction
                $this->targetSQL->command('ROLLBACK');

                $this->sendTerminal('✗ Failed: ' . $e->getMessage(), 'red');
                break;
            }

            $this->sendTerminal('');
        }

        $this->sendTerminal('Migration process completed', 'green');
    }

    /**
     * Rollback last migration
     */
    public function METHOD_rollback()
    {
        $this->sendTerminal('Rolling back last migration...', 'yellow');

        $completed = $this->getCompletedMigrations();

        if(empty($completed)) {
            return $this->sendTerminal('No migrations to rollback', 'yellow');
        }

        $lastMigration = end($completed);

        // Find migration definition
        $migration = null;
        foreach($this->migrations as $m) {
            if($m['id'] === $lastMigration) {
                $migration = $m;
                break;
            }
        }

        if(!$migration) {
            return $this->sendTerminal('Migration not found', 'red');
        }

        $this->sendTerminal('Rolling back: ' . $migration['name'], 'blue');

        if(!$this->promptConfirm('Are you sure?')) {
            return $this->sendTerminal('Rollback cancelled', 'yellow');
        }

        try {
            $this->targetSQL->command('START TRANSACTION');

            // Run rollback queries
            if(isset($migration['rollback'])) {
                foreach($migration['rollback'] as $query) {
                    $this->targetSQL->command($query);

                    if($this->targetSQL->error()) {
                        throw new Exception('Rollback query failed');
                    }
                }
            }

            // Remove from completed
            $this->removeCompletedMigration($lastMigration);

            $this->targetSQL->command('COMMIT');

            $this->sendTerminal('✓ Rollback completed', 'green');

        } catch(Exception $e) {
            $this->targetSQL->command('ROLLBACK');
            $this->sendTerminal('✗ Rollback failed: ' . $e->getMessage(), 'red');
        }
    }

    /**
     * Show migration status
     */
    public function METHOD_status()
    {
        $completed = $this->getCompletedMigrations();

        $this->sendTerminal('Migration Status:', 'blue');
        $this->sendTerminal('');

        foreach($this->migrations as $migration) {
            $status = in_array($migration['id'], $completed) ? '✓' : '○';
            $color = in_array($migration['id'], $completed) ? 'green' : 'yellow';

            $this->sendTerminal($status . ' ' . $migration['name'], $color);
        }

        $this->sendTerminal('');
        $this->sendTerminal('Total: ' . count($this->migrations), 'blue');
        $this->sendTerminal('Completed: ' . count($completed), 'green');
        $this->sendTerminal('Pending: ' . (count($this->migrations) - count($completed)), 'yellow');
    }

    // Helper methods

    private function initializeDatabases(): bool
    {
        try {
            // Source database (read-only)
            $this->sourceSQL = $this->core->loadClass('CloudSQL', [
                $this->core->config->get('migration.source.host'),
                $this->core->config->get('migration.source.user'),
                $this->core->config->get('migration.source.password'),
                $this->core->config->get('migration.source.database')
            ]);

            // Target database (write)
            $this->targetSQL = $this->core->loadClass('CloudSQL', [
                $this->core->config->get('migration.target.host'),
                $this->core->config->get('migration.target.user'),
                $this->core->config->get('migration.target.password'),
                $this->core->config->get('migration.target.database')
            ]);

            if($this->sourceSQL->error() || $this->targetSQL->error()) {
                return false;
            }

            // Create migrations table if not exists
            $this->targetSQL->command("
                CREATE TABLE IF NOT EXISTS migrations (
                    id VARCHAR(255) PRIMARY KEY,
                    name VARCHAR(255),
                    executed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                )
            ");

            return true;

        } catch(Exception $e) {
            $this->sendTerminal('Database connection failed: ' . $e->getMessage(), 'red');
            return false;
        }
    }

    private function loadMigrations()
    {
        $this->migrations = [
            [
                'id' => '001_create_users_table',
                'name' => 'Create users table',
                'queries' => [
                    "CREATE TABLE IF NOT EXISTS users (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        name VARCHAR(100),
                        email VARCHAR(100) UNIQUE,
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                    )"
                ],
                'rollback' => ["DROP TABLE IF EXISTS users"]
            ],
            [
                'id' => '002_add_user_status',
                'name' => 'Add status column to users',
                'queries' => [
                    "ALTER TABLE users ADD COLUMN status VARCHAR(20) DEFAULT 'active'"
                ],
                'rollback' => ["ALTER TABLE users DROP COLUMN status"]
            ],
            [
                'id' => '003_create_products_table',
                'name' => 'Create products table',
                'queries' => [
                    "CREATE TABLE IF NOT EXISTS products (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        name VARCHAR(200),
                        price DECIMAL(10,2),
                        stock INT DEFAULT 0,
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                    )"
                ],
                'rollback' => ["DROP TABLE IF EXISTS products"]
            ]
        ];
    }

    private function getCompletedMigrations(): array
    {
        $result = $this->targetSQL->command("SELECT id FROM migrations ORDER BY executed_at");

        if($this->targetSQL->error() || empty($result)) {
            return [];
        }

        return array_column($result, 'id');
    }

    private function markMigrationCompleted(string $id, string $name)
    {
        $this->targetSQL->command(
            "INSERT INTO migrations (id, name) VALUES (?, ?)",
            [$id, $name]
        );
    }

    private function removeCompletedMigration(string $id)
    {
        $this->targetSQL->command("DELETE FROM migrations WHERE id = ?", [$id]);
    }

    private function promptConfirm(string $message): bool
    {
        $response = $this->prompt($message . ' (yes/no): ');
        return strtolower(trim($response)) === 'yes';
    }
}
```

---

## Data Import Script

Import data from CSV/JSON files into Datastore with validation and error handling.

**File:** `app/scripts/import-data.php`

```php
<?php
/**
 * Data Import Script
 *
 * Usage:
 * php index.php script=import-data method=import file=/path/to/data.csv kind=Users
 * php index.php script=import-data method=validate file=/path/to/data.csv
 */

use CloudFramework\Patterns\Scripts2020;

class Script extends Scripts2020
{
    private $ds;
    private $errors = [];
    private $imported = 0;
    private $skipped = 0;

    function main()
    {
        $this->sendTerminal('=== Data Import Script ===', 'blue');
        $this->sendTerminal('');

        $method = $this->getOptionVar('method', 'import');

        if(!$this->useFunction('METHOD_' . $method)) {
            $this->sendTerminal('Invalid method', 'red');
        }
    }

    /**
     * Import data from file
     */
    public function METHOD_import()
    {
        // Get parameters
        $file = $this->getOptionVar('file');
        $kind = $this->getOptionVar('kind');

        if(!$file) {
            $file = $this->prompt('Enter file path: ');
        }

        if(!file_exists($file)) {
            return $this->sendTerminal('File not found: ' . $file, 'red');
        }

        if(!$kind) {
            $kind = $this->prompt('Enter Datastore kind: ');
        }

        // Initialize Datastore
        $this->ds = $this->core->loadClass('DataStore', [$kind]);

        $this->sendTerminal('File: ' . $file, 'blue');
        $this->sendTerminal('Kind: ' . $kind, 'blue');
        $this->sendTerminal('');

        // Detect file type
        $extension = pathinfo($file, PATHINFO_EXTENSION);

        if($extension === 'csv') {
            $data = $this->readCSV($file);
        } elseif($extension === 'json') {
            $data = $this->readJSON($file);
        } else {
            return $this->sendTerminal('Unsupported file type: ' . $extension, 'red');
        }

        if(empty($data)) {
            return $this->sendTerminal('No data found in file', 'red');
        }

        $this->sendTerminal('Found ' . count($data) . ' records', 'green');
        $this->sendTerminal('');

        // Preview first record
        $this->sendTerminal('Preview first record:', 'yellow');
        foreach($data[0] as $key => $value) {
            $this->sendTerminal("  {$key}: {$value}", 'white');
        }
        $this->sendTerminal('');

        // Confirm import
        if(!$this->promptConfirm('Start import?')) {
            return $this->sendTerminal('Import cancelled', 'yellow');
        }

        // Import data
        $total = count($data);
        $startTime = microtime(true);

        foreach($data as $index => $record) {
            $recordNum = $index + 1;

            // Validate record
            if(!$this->validateRecord($record)) {
                $this->sendTerminal("[{$recordNum}/{$total}] Skipped: Invalid data", 'yellow');
                $this->skipped++;
                continue;
            }

            // Import record
            try {
                // Check if exists (by email or unique field)
                if(isset($record['email'])) {
                    $existing = $this->ds->fetchAll([['email', '=', $record['email']]], null, 1);

                    if(!empty($existing)) {
                        $this->sendTerminal("[{$recordNum}/{$total}] Skipped: Already exists", 'yellow');
                        $this->skipped++;
                        continue;
                    }
                }

                // Create entity
                $entityId = $this->ds->createEntity($record);

                if($this->ds->error()) {
                    throw new Exception(implode(', ', $this->ds->getError()));
                }

                $this->imported++;
                $this->sendTerminal("[{$recordNum}/{$total}] Imported: {$entityId}", 'green');

            } catch(Exception $e) {
                $this->errors[] = [
                    'record' => $recordNum,
                    'data' => $record,
                    'error' => $e->getMessage()
                ];
                $this->sendTerminal("[{$recordNum}/{$total}] Error: " . $e->getMessage(), 'red');
            }

            // Progress indicator every 10 records
            if($recordNum % 10 === 0) {
                $percent = round(($recordNum / $total) * 100);
                $this->sendTerminal("Progress: {$percent}%", 'blue');
            }
        }

        $duration = round(microtime(true) - $startTime, 2);

        // Summary
        $this->sendTerminal('', 'white');
        $this->sendTerminal('=== Import Summary ===', 'blue');
        $this->sendTerminal('Total records: ' . $total, 'white');
        $this->sendTerminal('Imported: ' . $this->imported, 'green');
        $this->sendTerminal('Skipped: ' . $this->skipped, 'yellow');
        $this->sendTerminal('Errors: ' . count($this->errors), 'red');
        $this->sendTerminal('Duration: ' . $duration . 's', 'blue');

        // Save error log
        if(!empty($this->errors)) {
            $errorFile = 'import_errors_' . date('Y-m-d_H-i-s') . '.json';
            file_put_contents($errorFile, json_encode($this->errors, JSON_PRETTY_PRINT));
            $this->sendTerminal('Error log saved: ' . $errorFile, 'yellow');
        }
    }

    /**
     * Validate file without importing
     */
    public function METHOD_validate()
    {
        $file = $this->getOptionVar('file');

        if(!$file) {
            $file = $this->prompt('Enter file path: ');
        }

        if(!file_exists($file)) {
            return $this->sendTerminal('File not found: ' . $file, 'red');
        }

        $this->sendTerminal('Validating file: ' . $file, 'blue');
        $this->sendTerminal('');

        // Read data
        $extension = pathinfo($file, PATHINFO_EXTENSION);

        if($extension === 'csv') {
            $data = $this->readCSV($file);
        } elseif($extension === 'json') {
            $data = $this->readJSON($file);
        } else {
            return $this->sendTerminal('Unsupported file type', 'red');
        }

        if(empty($data)) {
            return $this->sendTerminal('No data found', 'red');
        }

        $this->sendTerminal('Found ' . count($data) . ' records', 'green');
        $this->sendTerminal('');

        // Validate each record
        $valid = 0;
        $invalid = 0;

        foreach($data as $index => $record) {
            if($this->validateRecord($record)) {
                $valid++;
            } else {
                $invalid++;
                $this->sendTerminal('Record ' . ($index + 1) . ': Invalid', 'red');
            }
        }

        $this->sendTerminal('', 'white');
        $this->sendTerminal('Valid records: ' . $valid, 'green');
        $this->sendTerminal('Invalid records: ' . $invalid, 'red');
    }

    // Helper methods

    private function readCSV(string $file): array
    {
        $data = [];
        $handle = fopen($file, 'r');

        if(!$handle) {
            return $data;
        }

        // Read header
        $header = fgetcsv($handle);

        // Read data
        while(($row = fgetcsv($handle)) !== false) {
            if(count($row) === count($header)) {
                $data[] = array_combine($header, $row);
            }
        }

        fclose($handle);

        return $data;
    }

    private function readJSON(string $file): array
    {
        $content = file_get_contents($file);
        $data = json_decode($content, true);

        return is_array($data) ? $data : [];
    }

    private function validateRecord(array $record): bool
    {
        // Check required fields
        $required = ['name', 'email'];

        foreach($required as $field) {
            if(empty($record[$field])) {
                return false;
            }
        }

        // Validate email
        if(!filter_var($record['email'], FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        return true;
    }

    private function promptConfirm(string $message): bool
    {
        $response = $this->prompt($message . ' (yes/no): ');
        return strtolower(trim($response)) === 'yes';
    }
}
```

---

## Backup Script

Automated backup of Datastore entities and Cloud SQL databases.

**File:** `app/scripts/backup.php`

```php
<?php
/**
 * Backup Script
 *
 * Usage:
 * php index.php script=backup method=datastore kind=Users
 * php index.php script=backup method=cloudsql database=mydb
 * php index.php script=backup method=all
 */

use CloudFramework\Patterns\Scripts2020;

class Script extends Scripts2020
{
    private $buckets;
    private $backupPath = 'backups/';

    function main()
    {
        $this->sendTerminal('=== Backup Script ===', 'blue');
        $this->sendTerminal('');

        // Initialize Cloud Storage
        $this->buckets = $this->core->loadClass('Buckets', ['gs://my-backups-bucket']);

        $method = $this->getOptionVar('method', 'all');

        if(!$this->useFunction('METHOD_' . $method)) {
            $this->sendTerminal('Invalid method', 'red');
        }
    }

    /**
     * Backup Datastore entities
     */
    public function METHOD_datastore()
    {
        $kind = $this->getOptionVar('kind');

        if(!$kind) {
            $kind = $this->prompt('Enter Datastore kind: ');
        }

        $this->sendTerminal('Backing up Datastore kind: ' . $kind, 'blue');

        $ds = $this->core->loadClass('DataStore', [$kind]);

        // Fetch all entities
        $entities = $ds->fetchAll();

        if($ds->error()) {
            return $this->sendTerminal('Failed to fetch entities', 'red');
        }

        $this->sendTerminal('Found ' . count($entities) . ' entities', 'green');

        // Prepare backup data
        $backup = [
            'kind' => $kind,
            'count' => count($entities),
            'timestamp' => date('c'),
            'entities' => $entities
        ];

        // Save to file
        $filename = $kind . '_' . date('Y-m-d_H-i-s') . '.json';
        $localFile = '/tmp/' . $filename;
        file_put_contents($localFile, json_encode($backup, JSON_PRETTY_PRINT));

        // Upload to Cloud Storage
        $remotePath = $this->backupPath . date('Y/m/d/') . $filename;
        $this->buckets->uploadFile($localFile, $remotePath);

        if($this->buckets->error()) {
            return $this->sendTerminal('Failed to upload backup', 'red');
        }

        $this->sendTerminal('✓ Backup saved: ' . $remotePath, 'green');

        // Cleanup local file
        unlink($localFile);

        // Cache backup info
        $this->setCacheVar('last_backup_' . $kind, [
            'file' => $remotePath,
            'timestamp' => time(),
            'count' => count($entities)
        ]);
    }

    /**
     * Backup CloudSQL database
     */
    public function METHOD_cloudsql()
    {
        $database = $this->getOptionVar('database');

        if(!$database) {
            $database = $this->prompt('Enter database name: ');
        }

        $this->sendTerminal('Backing up CloudSQL database: ' . $database, 'blue');

        $sql = $this->core->loadClass('CloudSQL');

        // Get all tables
        $tables = $sql->command("SHOW TABLES");

        if($sql->error()) {
            return $this->sendTerminal('Failed to get tables', 'red');
        }

        $this->sendTerminal('Found ' . count($tables) . ' tables', 'green');

        $backup = [
            'database' => $database,
            'timestamp' => date('c'),
            'tables' => []
        ];

        // Export each table
        foreach($tables as $table) {
            $tableName = array_values($table)[0];
            $this->sendTerminal('Exporting: ' . $tableName, 'yellow');

            // Get table data
            $data = $sql->command("SELECT * FROM `{$tableName}`");

            if(!$sql->error()) {
                $backup['tables'][$tableName] = $data;
                $this->sendTerminal('  ✓ ' . count($data) . ' rows', 'green');
            } else {
                $this->sendTerminal('  ✗ Failed', 'red');
            }
        }

        // Save backup
        $filename = $database . '_' . date('Y-m-d_H-i-s') . '.json';
        $localFile = '/tmp/' . $filename;
        file_put_contents($localFile, json_encode($backup, JSON_PRETTY_PRINT));

        // Upload to Cloud Storage
        $remotePath = $this->backupPath . 'sql/' . date('Y/m/d/') . $filename;
        $this->buckets->uploadFile($localFile, $remotePath);

        if($this->buckets->error()) {
            return $this->sendTerminal('Failed to upload backup', 'red');
        }

        $this->sendTerminal('✓ Backup saved: ' . $remotePath, 'green');

        unlink($localFile);
    }

    /**
     * Backup all data
     */
    public function METHOD_all()
    {
        $this->sendTerminal('Starting full backup...', 'blue');
        $this->sendTerminal('');

        // Backup Datastore kinds
        $datastoreKinds = ['Users', 'Products', 'Orders'];

        foreach($datastoreKinds as $kind) {
            $this->sendTerminal('Backing up ' . $kind . '...', 'yellow');
            $this->METHOD_datastore_internal($kind);
            $this->sendTerminal('');
        }

        // Backup CloudSQL
        $databases = $this->core->config->get('backup.databases', ['mydb']);

        foreach($databases as $database) {
            $this->sendTerminal('Backing up database ' . $database . '...', 'yellow');
            $this->METHOD_cloudsql_internal($database);
            $this->sendTerminal('');
        }

        $this->sendTerminal('✓ Full backup completed', 'green');
    }

    /**
     * List available backups
     */
    public function METHOD_list()
    {
        $this->sendTerminal('Available backups:', 'blue');
        $this->sendTerminal('');

        $files = $this->buckets->scan($this->backupPath);

        if($this->buckets->error() || empty($files)) {
            return $this->sendTerminal('No backups found', 'yellow');
        }

        foreach($files as $file) {
            $info = $this->buckets->getFileInfo($file);
            $size = isset($info['size']) ? round($info['size'] / 1024, 2) . ' KB' : 'unknown';

            $this->sendTerminal(basename($file) . ' (' . $size . ')', 'white');
        }

        $this->sendTerminal('', 'white');
        $this->sendTerminal('Total backups: ' . count($files), 'green');
    }

    // Internal methods (used by METHOD_all)

    private function METHOD_datastore_internal(string $kind)
    {
        $ds = $this->core->loadClass('DataStore', [$kind]);
        $entities = $ds->fetchAll();

        if(!$ds->error() && !empty($entities)) {
            $backup = [
                'kind' => $kind,
                'count' => count($entities),
                'timestamp' => date('c'),
                'entities' => $entities
            ];

            $filename = $kind . '_' . date('Y-m-d_H-i-s') . '.json';
            $localFile = '/tmp/' . $filename;
            file_put_contents($localFile, json_encode($backup, JSON_PRETTY_PRINT));

            $remotePath = $this->backupPath . date('Y/m/d/') . $filename;
            $this->buckets->uploadFile($localFile, $remotePath);

            unlink($localFile);

            $this->sendTerminal('  ✓ ' . count($entities) . ' entities backed up', 'green');
        }
    }

    private function METHOD_cloudsql_internal(string $database)
    {
        // Similar to METHOD_cloudsql but without terminal output
        // Implementation here...
    }
}
```

---

## Report Generation Script

Generate and email reports from BigQuery data.

**File:** `app/scripts/generate-reports.php`

```php
<?php
/**
 * Report Generation Script
 *
 * Usage:
 * php index.php script=generate-reports method=daily
 * php index.php script=generate-reports method=weekly
 * php index.php script=generate-reports method=custom start=2024-01-01 end=2024-01-31
 */

use CloudFramework\Patterns\Scripts2020;

class Script extends Scripts2020
{
    private $bq;
    private $buckets;
    private $email;

    function main()
    {
        $this->sendTerminal('=== Report Generation Script ===', 'blue');
        $this->sendTerminal('');

        // Initialize services
        $this->bq = $this->core->loadClass('DataBQ', ['analytics']);
        $this->buckets = $this->core->loadClass('Buckets', ['gs://my-reports-bucket']);
        $this->email = $this->core->loadClass('Email');

        $method = $this->getOptionVar('method', 'daily');

        if(!$this->useFunction('METHOD_' . $method)) {
            $this->sendTerminal('Invalid method', 'red');
        }
    }

    /**
     * Generate daily report
     */
    public function METHOD_daily()
    {
        $date = date('Y-m-d', strtotime('-1 day'));

        $this->sendTerminal('Generating daily report for: ' . $date, 'blue');

        $report = $this->generateReport($date, $date);
        $this->saveAndSendReport($report, 'daily', $date);
    }

    /**
     * Generate weekly report
     */
    public function METHOD_weekly()
    {
        $endDate = date('Y-m-d', strtotime('-1 day'));
        $startDate = date('Y-m-d', strtotime('-7 days'));

        $this->sendTerminal('Generating weekly report: ' . $startDate . ' to ' . $endDate, 'blue');

        $report = $this->generateReport($startDate, $endDate);
        $this->saveAndSendReport($report, 'weekly', $startDate);
    }

    /**
     * Generate custom range report
     */
    public function METHOD_custom()
    {
        $startDate = $this->getOptionVar('start');
        $endDate = $this->getOptionVar('end');

        if(!$startDate || !$endDate) {
            $startDate = $this->prompt('Start date (Y-m-d): ');
            $endDate = $this->prompt('End date (Y-m-d): ');
        }

        $this->sendTerminal('Generating custom report: ' . $startDate . ' to ' . $endDate, 'blue');

        $report = $this->generateReport($startDate, $endDate);
        $this->saveAndSendReport($report, 'custom', $startDate . '_to_' . $endDate);
    }

    // Helper methods

    private function generateReport(string $startDate, string $endDate): array
    {
        $report = [
            'period' => [
                'start' => $startDate,
                'end' => $endDate
            ],
            'metrics' => []
        ];

        // User metrics
        $this->sendTerminal('Fetching user metrics...', 'yellow');
        $report['metrics']['users'] = $this->getUserMetrics($startDate, $endDate);

        // Revenue metrics
        $this->sendTerminal('Fetching revenue metrics...', 'yellow');
        $report['metrics']['revenue'] = $this->getRevenueMetrics($startDate, $endDate);

        // Product metrics
        $this->sendTerminal('Fetching product metrics...', 'yellow');
        $report['metrics']['products'] = $this->getProductMetrics($startDate, $endDate);

        return $report;
    }

    private function getUserMetrics(string $startDate, string $endDate): array
    {
        $query = "
            SELECT
                COUNT(DISTINCT user_id) as total_users,
                COUNT(DISTINCT CASE WHEN DATE(created_at) BETWEEN '{$startDate}' AND '{$endDate}' THEN user_id END) as new_users,
                COUNT(DISTINCT CASE WHEN DATE(last_login) BETWEEN '{$startDate}' AND '{$endDate}' THEN user_id END) as active_users
            FROM users
        ";

        $result = $this->bq->query($query);

        return $result[0] ?? [];
    }

    private function getRevenueMetrics(string $startDate, string $endDate): array
    {
        $query = "
            SELECT
                COUNT(*) as total_orders,
                SUM(amount) as total_revenue,
                AVG(amount) as avg_order_value,
                MAX(amount) as max_order_value
            FROM orders
            WHERE DATE(created_at) BETWEEN '{$startDate}' AND '{$endDate}'
            AND status = 'completed'
        ";

        $result = $this->bq->query($query);

        return $result[0] ?? [];
    }

    private function getProductMetrics(string $startDate, string $endDate): array
    {
        $query = "
            SELECT
                product_name,
                COUNT(*) as orders,
                SUM(quantity) as units_sold,
                SUM(amount) as revenue
            FROM order_items
            WHERE DATE(created_at) BETWEEN '{$startDate}' AND '{$endDate}'
            GROUP BY product_name
            ORDER BY revenue DESC
            LIMIT 10
        ";

        return $this->bq->query($query);
    }

    private function saveAndSendReport(array $report, string $type, string $identifier)
    {
        // Save as JSON
        $filename = "report_{$type}_{$identifier}.json";
        $localFile = '/tmp/' . $filename;
        file_put_contents($localFile, json_encode($report, JSON_PRETTY_PRINT));

        // Upload to Cloud Storage
        $remotePath = 'reports/' . date('Y/m/') . $filename;
        $this->buckets->uploadFile($localFile, $remotePath);

        if($this->buckets->error()) {
            $this->sendTerminal('Failed to upload report', 'red');
        } else {
            $this->sendTerminal('✓ Report saved: ' . $remotePath, 'green');
        }

        // Generate HTML report
        $htmlReport = $this->generateHTMLReport($report, $type);

        // Send email
        $recipients = $this->core->config->get('reports.recipients', []);

        if(!empty($recipients)) {
            $this->email->setTo($recipients);
            $this->email->setSubject("Report: {$type} - {$identifier}");
            $this->email->setBodyHTML($htmlReport);
            $this->email->setAttachment($localFile);

            if($this->email->send()) {
                $this->sendTerminal('✓ Report emailed to: ' . implode(', ', $recipients), 'green');
            } else {
                $this->sendTerminal('Failed to send email', 'red');
            }
        }

        // Cleanup
        unlink($localFile);
    }

    private function generateHTMLReport(array $report, string $type): string
    {
        $html = '<html><body>';
        $html .= '<h1>' . ucfirst($type) . ' Report</h1>';
        $html .= '<p>Period: ' . $report['period']['start'] . ' to ' . $report['period']['end'] . '</p>';

        // User metrics
        $html .= '<h2>User Metrics</h2>';
        $html .= '<ul>';
        foreach($report['metrics']['users'] as $key => $value) {
            $html .= '<li>' . ucwords(str_replace('_', ' ', $key)) . ': ' . $value . '</li>';
        }
        $html .= '</ul>';

        // Revenue metrics
        $html .= '<h2>Revenue Metrics</h2>';
        $html .= '<ul>';
        foreach($report['metrics']['revenue'] as $key => $value) {
            $formatted = is_numeric($value) ? '$' . number_format($value, 2) : $value;
            $html .= '<li>' . ucwords(str_replace('_', ' ', $key)) . ': ' . $formatted . '</li>';
        }
        $html .= '</ul>';

        // Top products
        $html .= '<h2>Top Products</h2>';
        $html .= '<table border="1" cellpadding="5">';
        $html .= '<tr><th>Product</th><th>Orders</th><th>Units</th><th>Revenue</th></tr>';

        foreach($report['metrics']['products'] as $product) {
            $html .= '<tr>';
            $html .= '<td>' . $product['product_name'] . '</td>';
            $html .= '<td>' . $product['orders'] . '</td>';
            $html .= '<td>' . $product['units_sold'] . '</td>';
            $html .= '<td>$' . number_format($product['revenue'], 2) . '</td>';
            $html .= '</tr>';
        }

        $html .= '</table>';
        $html .= '</body></html>';

        return $html;
    }
}
```

---

## User Management Script

Automated user management tasks including activation, deactivation, and bulk operations.

**File:** `app/scripts/user-management.php`

```php
<?php
/**
 * User Management Script
 *
 * Usage:
 * php index.php script=user-management method=activate user_id=123
 * php index.php script=user-management method=deactivate user_id=123
 * php index.php script=user-management method=bulk-activate file=/path/to/users.csv
 * php index.php script=user-management method=clean-inactive days=90
 * php index.php script=user-management method=export format=csv
 */

use CloudFramework\Patterns\Scripts2020;

class Script extends Scripts2020
{
    private $ds;
    private $processed = 0;
    private $failed = 0;

    function main()
    {
        $this->sendTerminal('=== User Management Script ===', 'blue');
        $this->sendTerminal('');

        $this->ds = $this->core->loadClass('DataStore', ['Users']);

        $method = $this->getOptionVar('method', 'list');

        if(!$this->useFunction('METHOD_' . $method)) {
            $this->sendTerminal('Invalid method', 'red');
        }
    }

    /**
     * Activate user account
     */
    public function METHOD_activate()
    {
        $userId = $this->getOptionVar('user_id');

        if(!$userId) {
            $userId = $this->prompt('Enter User ID: ');
        }

        $this->sendTerminal('Activating user: ' . $userId, 'blue');

        $user = $this->ds->fetchOne($userId);

        if(!$user) {
            return $this->sendTerminal('User not found', 'red');
        }

        if($user['status'] === 'active') {
            return $this->sendTerminal('User is already active', 'yellow');
        }

        // Update user status
        $this->ds->updateEntity($userId, [
            'status' => 'active',
            'activated_at' => date('c'),
            'updated_at' => date('c')
        ]);

        if($this->ds->error()) {
            return $this->sendTerminal('Failed to activate user', 'red');
        }

        // Send activation email
        $this->sendActivationEmail($user['email'], $user['name']);

        // Log event
        $this->core->logs->add([
            'event' => 'user_activated',
            'user_id' => $userId,
            'activated_by' => 'script'
        ], 'user_management');

        $this->sendTerminal('✓ User activated successfully', 'green');
    }

    /**
     * Deactivate user account
     */
    public function METHOD_deactivate()
    {
        $userId = $this->getOptionVar('user_id');

        if(!$userId) {
            $userId = $this->prompt('Enter User ID: ');
        }

        $reason = $this->getOptionVar('reason', 'Administrative action');

        $this->sendTerminal('Deactivating user: ' . $userId, 'blue');

        $user = $this->ds->fetchOne($userId);

        if(!$user) {
            return $this->sendTerminal('User not found', 'red');
        }

        if($user['status'] === 'inactive') {
            return $this->sendTerminal('User is already inactive', 'yellow');
        }

        // Confirm action
        if(!$this->promptConfirm('Are you sure you want to deactivate this user?')) {
            return $this->sendTerminal('Deactivation cancelled', 'yellow');
        }

        // Update user status
        $this->ds->updateEntity($userId, [
            'status' => 'inactive',
            'deactivated_at' => date('c'),
            'deactivation_reason' => $reason,
            'updated_at' => date('c')
        ]);

        if($this->ds->error()) {
            return $this->sendTerminal('Failed to deactivate user', 'red');
        }

        // Invalidate user sessions
        $this->invalidateUserSessions($userId);

        // Log event
        $this->core->logs->add([
            'event' => 'user_deactivated',
            'user_id' => $userId,
            'reason' => $reason,
            'deactivated_by' => 'script'
        ], 'user_management');

        $this->sendTerminal('✓ User deactivated successfully', 'green');
    }

    /**
     * Bulk activate users from file
     */
    public function METHOD_bulk_activate()
    {
        $file = $this->getOptionVar('file');

        if(!$file) {
            $file = $this->prompt('Enter file path (CSV with user IDs): ');
        }

        if(!file_exists($file)) {
            return $this->sendTerminal('File not found: ' . $file, 'red');
        }

        $this->sendTerminal('Reading users from: ' . $file, 'blue');

        // Read user IDs from file
        $userIds = [];
        $handle = fopen($file, 'r');

        // Skip header
        fgetcsv($handle);

        while(($row = fgetcsv($handle)) !== false) {
            if(!empty($row[0])) {
                $userIds[] = trim($row[0]);
            }
        }

        fclose($handle);

        $this->sendTerminal('Found ' . count($userIds) . ' users to activate', 'green');
        $this->sendTerminal('');

        if(!$this->promptConfirm('Proceed with bulk activation?')) {
            return $this->sendTerminal('Bulk activation cancelled', 'yellow');
        }

        $startTime = microtime(true);

        foreach($userIds as $index => $userId) {
            $userNum = $index + 1;

            $user = $this->ds->fetchOne($userId);

            if(!$user) {
                $this->sendTerminal("[{$userNum}] User {$userId}: Not found", 'red');
                $this->failed++;
                continue;
            }

            if($user['status'] === 'active') {
                $this->sendTerminal("[{$userNum}] User {$userId}: Already active", 'yellow');
                continue;
            }

            // Activate user
            $this->ds->updateEntity($userId, [
                'status' => 'active',
                'activated_at' => date('c'),
                'updated_at' => date('c')
            ]);

            if($this->ds->error()) {
                $this->sendTerminal("[{$userNum}] User {$userId}: Failed to activate", 'red');
                $this->failed++;
            } else {
                $this->sendTerminal("[{$userNum}] User {$userId}: Activated", 'green');
                $this->processed++;

                // Send email
                $this->sendActivationEmail($user['email'], $user['name']);
            }

            // Progress every 10 users
            if($userNum % 10 === 0) {
                $percent = round(($userNum / count($userIds)) * 100);
                $this->sendTerminal("Progress: {$percent}%", 'blue');
            }
        }

        $duration = round(microtime(true) - $startTime, 2);

        // Summary
        $this->sendTerminal('', 'white');
        $this->sendTerminal('=== Bulk Activation Summary ===', 'blue');
        $this->sendTerminal('Total users: ' . count($userIds), 'white');
        $this->sendTerminal('Activated: ' . $this->processed, 'green');
        $this->sendTerminal('Failed: ' . $this->failed, 'red');
        $this->sendTerminal('Duration: ' . $duration . 's', 'blue');
    }

    /**
     * Clean inactive users
     */
    public function METHOD_clean_inactive()
    {
        $days = (int)($this->getOptionVar('days', 90));

        $this->sendTerminal("Cleaning users inactive for {$days}+ days", 'blue');
        $this->sendTerminal('');

        $cutoffDate = date('Y-m-d', strtotime("-{$days} days"));

        // Find inactive users
        $allUsers = $this->ds->fetchAll();

        $inactiveUsers = array_filter($allUsers, function($user) use ($cutoffDate) {
            if($user['status'] !== 'active') {
                return false;
            }

            $lastLogin = $user['last_login'] ?? $user['created_at'];
            return $lastLogin < $cutoffDate;
        });

        $this->sendTerminal('Found ' . count($inactiveUsers) . ' inactive users', 'yellow');

        if(empty($inactiveUsers)) {
            return $this->sendTerminal('No inactive users to clean', 'green');
        }

        // Preview first 5
        $this->sendTerminal('', 'white');
        $this->sendTerminal('Preview (first 5):', 'yellow');

        $preview = array_slice($inactiveUsers, 0, 5);
        foreach($preview as $user) {
            $lastLogin = $user['last_login'] ?? $user['created_at'];
            $this->sendTerminal("  {$user['email']} - Last login: {$lastLogin}", 'white');
        }

        $this->sendTerminal('', 'white');

        if(!$this->promptConfirm('Proceed with deactivation?')) {
            return $this->sendTerminal('Cleanup cancelled', 'yellow');
        }

        // Deactivate users
        foreach($inactiveUsers as $user) {
            $this->ds->updateEntity($user['KeyId'], [
                'status' => 'inactive',
                'deactivated_at' => date('c'),
                'deactivation_reason' => "Inactive for {$days}+ days",
                'updated_at' => date('c')
            ]);

            if(!$this->ds->error()) {
                $this->processed++;
                $this->sendTerminal("✓ Deactivated: {$user['email']}", 'green');
            } else {
                $this->failed++;
                $this->sendTerminal("✗ Failed: {$user['email']}", 'red');
            }
        }

        $this->sendTerminal('', 'white');
        $this->sendTerminal('Processed: ' . $this->processed, 'green');
        $this->sendTerminal('Failed: ' . $this->failed, 'red');
    }

    /**
     * Export users to CSV or JSON
     */
    public function METHOD_export()
    {
        $format = $this->getOptionVar('format', 'csv');
        $status = $this->getOptionVar('status', null);

        $this->sendTerminal('Exporting users...', 'blue');

        $where = [];
        if($status) {
            $where[] = ['status', '=', $status];
        }

        $users = $this->ds->fetchAll($where);

        $this->sendTerminal('Found ' . count($users) . ' users', 'green');

        // Remove sensitive data
        foreach($users as &$user) {
            unset($user['password']);
            unset($user['api_key']);
            unset($user['refresh_token']);
        }

        $filename = 'users_export_' . date('Y-m-d_H-i-s') . '.' . $format;

        if($format === 'csv') {
            $this->exportToCSV($users, $filename);
        } else {
            $this->exportToJSON($users, $filename);
        }

        $this->sendTerminal('✓ Exported to: ' . $filename, 'green');
    }

    /**
     * List user statistics
     */
    public function METHOD_stats()
    {
        $this->sendTerminal('=== User Statistics ===', 'blue');
        $this->sendTerminal('');

        $allUsers = $this->ds->fetchAll();

        // Count by status
        $byStatus = [];
        foreach($allUsers as $user) {
            $status = $user['status'] ?? 'unknown';
            $byStatus[$status] = ($byStatus[$status] ?? 0) + 1;
        }

        $this->sendTerminal('Total users: ' . count($allUsers), 'white');
        $this->sendTerminal('');

        $this->sendTerminal('By status:', 'yellow');
        foreach($byStatus as $status => $count) {
            $color = $status === 'active' ? 'green' : 'white';
            $this->sendTerminal("  {$status}: {$count}", $color);
        }

        // Recent registrations
        $this->sendTerminal('', 'white');
        $this->sendTerminal('Recent registrations (last 7 days):', 'yellow');

        $weekAgo = date('Y-m-d', strtotime('-7 days'));
        $recent = array_filter($allUsers, function($user) use ($weekAgo) {
            return isset($user['created_at']) && $user['created_at'] >= $weekAgo;
        });

        $this->sendTerminal('  ' . count($recent) . ' new users', 'green');

        // Active in last 30 days
        $monthAgo = date('Y-m-d', strtotime('-30 days'));
        $activeRecently = array_filter($allUsers, function($user) use ($monthAgo) {
            $lastLogin = $user['last_login'] ?? null;
            return $lastLogin && $lastLogin >= $monthAgo;
        });

        $this->sendTerminal('', 'white');
        $this->sendTerminal('Active in last 30 days: ' . count($activeRecently), 'green');
    }

    // Helper methods

    private function sendActivationEmail(string $email, string $name)
    {
        $emailService = $this->core->loadClass('Email');
        $emailService->setTo($email);
        $emailService->setSubject('Your Account Has Been Activated');
        $emailService->setBodyHTML("
            <h1>Welcome back, {$name}!</h1>
            <p>Your account has been activated and you can now log in.</p>
        ");
        $emailService->send();
    }

    private function invalidateUserSessions(string $userId)
    {
        // Clear user's refresh tokens and sessions
        $this->ds->updateEntity($userId, [
            'refresh_token' => null
        ]);
    }

    private function exportToCSV(array $users, string $filename)
    {
        $handle = fopen($filename, 'w');

        // Write header
        if(!empty($users)) {
            fputcsv($handle, array_keys($users[0]));
        }

        // Write data
        foreach($users as $user) {
            fputcsv($handle, $user);
        }

        fclose($handle);
    }

    private function exportToJSON(array $users, string $filename)
    {
        file_put_contents($filename, json_encode($users, JSON_PRETTY_PRINT));
    }

    private function promptConfirm(string $message): bool
    {
        $response = $this->prompt($message . ' (yes/no): ');
        return strtolower(trim($response)) === 'yes';
    }
}
```

---

## Email Campaign Script

Send email campaigns to users with tracking and scheduling.

**File:** `app/scripts/email-campaign.php`

```php
<?php
/**
 * Email Campaign Script
 *
 * Usage:
 * php index.php script=email-campaign method=send campaign_id=123
 * php index.php script=email-campaign method=schedule campaign_id=123 datetime="2024-12-25 10:00:00"
 * php index.php script=email-campaign method=test campaign_id=123 email=test@example.com
 * php index.php script=email-campaign method=stats campaign_id=123
 */

use CloudFramework\Patterns\Scripts2020;

class Script extends Scripts2020
{
    private $campaignsDS;
    private $usersDS;
    private $email;
    private $sent = 0;
    private $failed = 0;

    function main()
    {
        $this->sendTerminal('=== Email Campaign Script ===', 'blue');
        $this->sendTerminal('');

        $this->campaignsDS = $this->core->loadClass('DataStore', ['EmailCampaigns']);
        $this->usersDS = $this->core->loadClass('DataStore', ['Users']);
        $this->email = $this->core->loadClass('Email');

        $method = $this->getOptionVar('method', 'send');

        if(!$this->useFunction('METHOD_' . $method)) {
            $this->sendTerminal('Invalid method', 'red');
        }
    }

    /**
     * Send email campaign
     */
    public function METHOD_send()
    {
        $campaignId = $this->getOptionVar('campaign_id');

        if(!$campaignId) {
            $campaignId = $this->prompt('Enter Campaign ID: ');
        }

        $this->sendTerminal('Loading campaign: ' . $campaignId, 'blue');

        $campaign = $this->campaignsDS->fetchOne($campaignId);

        if(!$campaign) {
            return $this->sendTerminal('Campaign not found', 'red');
        }

        if($campaign['status'] !== 'draft' && $campaign['status'] !== 'scheduled') {
            return $this->sendTerminal('Campaign already sent or in progress', 'yellow');
        }

        // Display campaign details
        $this->sendTerminal('', 'white');
        $this->sendTerminal('Campaign: ' . $campaign['name'], 'blue');
        $this->sendTerminal('Subject: ' . $campaign['subject'], 'white');
        $this->sendTerminal('Target: ' . $campaign['target_segment'], 'white');
        $this->sendTerminal('', 'white');

        // Get recipients
        $recipients = $this->getRecipients($campaign);

        $this->sendTerminal('Recipients: ' . count($recipients), 'green');

        if(empty($recipients)) {
            return $this->sendTerminal('No recipients found', 'red');
        }

        // Preview first recipient
        $this->sendTerminal('', 'white');
        $this->sendTerminal('Preview (first recipient):', 'yellow');
        $preview = $this->renderEmail($campaign, $recipients[0]);
        $this->sendTerminal('To: ' . $recipients[0]['email'], 'white');
        $this->sendTerminal('Subject: ' . $preview['subject'], 'white');
        $this->sendTerminal('', 'white');

        if(!$this->promptConfirm('Send campaign to all recipients?')) {
            return $this->sendTerminal('Campaign send cancelled', 'yellow');
        }

        // Update campaign status
        $this->campaignsDS->updateEntity($campaignId, [
            'status' => 'sending',
            'started_at' => date('c')
        ]);

        $startTime = microtime(true);

        // Send emails
        foreach($recipients as $index => $recipient) {
            $recipientNum = $index + 1;

            try {
                $rendered = $this->renderEmail($campaign, $recipient);

                $this->email->setTo($recipient['email']);
                $this->email->setSubject($rendered['subject']);
                $this->email->setBodyHTML($rendered['html']);

                // Add tracking pixel
                if($campaign['track_opens']) {
                    $trackingPixel = $this->generateTrackingPixel($campaignId, $recipient['id']);
                    $rendered['html'] .= $trackingPixel;
                }

                if($this->email->send()) {
                    $this->sent++;
                    $this->sendTerminal("[{$recipientNum}/{count($recipients)}] Sent to: {$recipient['email']}", 'green');

                    // Log send
                    $this->logCampaignSend($campaignId, $recipient['id'], 'sent');

                } else {
                    $this->failed++;
                    $this->sendTerminal("[{$recipientNum}/{count($recipients)}] Failed: {$recipient['email']}", 'red');

                    // Log failure
                    $this->logCampaignSend($campaignId, $recipient['id'], 'failed');
                }

            } catch(Exception $e) {
                $this->failed++;
                $this->sendTerminal("[{$recipientNum}/{count($recipients)}] Error: {$recipient['email']} - {$e->getMessage()}", 'red');
            }

            // Rate limiting (avoid overwhelming email service)
            if($recipientNum % 50 === 0) {
                $this->sendTerminal('Pausing for rate limit...', 'yellow');
                sleep(1);
            }

            // Progress every 25 emails
            if($recipientNum % 25 === 0) {
                $percent = round(($recipientNum / count($recipients)) * 100);
                $this->sendTerminal("Progress: {$percent}%", 'blue');
            }
        }

        $duration = round(microtime(true) - $startTime, 2);

        // Update campaign
        $this->campaignsDS->updateEntity($campaignId, [
            'status' => 'sent',
            'completed_at' => date('c'),
            'total_sent' => $this->sent,
            'total_failed' => $this->failed
        ]);

        // Summary
        $this->sendTerminal('', 'white');
        $this->sendTerminal('=== Campaign Summary ===', 'blue');
        $this->sendTerminal('Total recipients: ' . count($recipients), 'white');
        $this->sendTerminal('Sent: ' . $this->sent, 'green');
        $this->sendTerminal('Failed: ' . $this->failed, 'red');
        $this->sendTerminal('Duration: ' . $duration . 's', 'blue');
        $this->sendTerminal('Rate: ' . round(count($recipients) / $duration, 2) . ' emails/sec', 'blue');
    }

    /**
     * Schedule campaign for later
     */
    public function METHOD_schedule()
    {
        $campaignId = $this->getOptionVar('campaign_id');
        $datetime = $this->getOptionVar('datetime');

        if(!$campaignId) {
            $campaignId = $this->prompt('Enter Campaign ID: ');
        }

        if(!$datetime) {
            $datetime = $this->prompt('Enter schedule datetime (Y-m-d H:i:s): ');
        }

        $campaign = $this->campaignsDS->fetchOne($campaignId);

        if(!$campaign) {
            return $this->sendTerminal('Campaign not found', 'red');
        }

        // Validate datetime
        $scheduleTime = strtotime($datetime);
        if($scheduleTime === false || $scheduleTime < time()) {
            return $this->sendTerminal('Invalid datetime or datetime is in the past', 'red');
        }

        // Update campaign
        $this->campaignsDS->updateEntity($campaignId, [
            'status' => 'scheduled',
            'scheduled_at' => date('c', $scheduleTime),
            'updated_at' => date('c')
        ]);

        $this->sendTerminal('✓ Campaign scheduled for: ' . date('Y-m-d H:i:s', $scheduleTime), 'green');
        $this->sendTerminal('', 'white');
        $this->sendTerminal('Note: Set up a cron job to check for scheduled campaigns:', 'yellow');
        $this->sendTerminal('* * * * * php index.php script=email-campaign method=process-scheduled', 'white');
    }

    /**
     * Send test email
     */
    public function METHOD_test()
    {
        $campaignId = $this->getOptionVar('campaign_id');
        $testEmail = $this->getOptionVar('email');

        if(!$campaignId) {
            $campaignId = $this->prompt('Enter Campaign ID: ');
        }

        if(!$testEmail) {
            $testEmail = $this->prompt('Enter test email address: ');
        }

        $campaign = $this->campaignsDS->fetchOne($campaignId);

        if(!$campaign) {
            return $this->sendTerminal('Campaign not found', 'red');
        }

        $this->sendTerminal('Sending test email to: ' . $testEmail, 'blue');

        // Create test recipient
        $testRecipient = [
            'id' => 'test',
            'email' => $testEmail,
            'name' => 'Test User',
            'first_name' => 'Test',
            'last_name' => 'User'
        ];

        $rendered = $this->renderEmail($campaign, $testRecipient);

        $this->email->setTo($testEmail);
        $this->email->setSubject('[TEST] ' . $rendered['subject']);
        $this->email->setBodyHTML($rendered['html']);

        if($this->email->send()) {
            $this->sendTerminal('✓ Test email sent successfully', 'green');
        } else {
            $this->sendTerminal('✗ Failed to send test email', 'red');
        }
    }

    /**
     * Show campaign statistics
     */
    public function METHOD_stats()
    {
        $campaignId = $this->getOptionVar('campaign_id');

        if(!$campaignId) {
            $campaignId = $this->prompt('Enter Campaign ID: ');
        }

        $campaign = $this->campaignsDS->fetchOne($campaignId);

        if(!$campaign) {
            return $this->sendTerminal('Campaign not found', 'red');
        }

        $this->sendTerminal('=== Campaign Statistics ===', 'blue');
        $this->sendTerminal('');
        $this->sendTerminal('Campaign: ' . $campaign['name'], 'white');
        $this->sendTerminal('Status: ' . $campaign['status'], 'white');
        $this->sendTerminal('');

        if($campaign['status'] !== 'sent') {
            return $this->sendTerminal('Campaign has not been sent yet', 'yellow');
        }

        // Get stats from campaign log
        $logsDS = $this->core->loadClass('DataStore', ['CampaignLogs']);
        $logs = $logsDS->fetchAll([['campaign_id', '=', $campaignId]]);

        $totalSent = count(array_filter($logs, fn($log) => $log['status'] === 'sent'));
        $totalOpened = count(array_filter($logs, fn($log) => $log['opened'] === true));
        $totalClicked = count(array_filter($logs, fn($log) => $log['clicked'] === true));
        $totalBounced = count(array_filter($logs, fn($log) => $log['status'] === 'bounced'));

        $openRate = $totalSent > 0 ? round(($totalOpened / $totalSent) * 100, 2) : 0;
        $clickRate = $totalSent > 0 ? round(($totalClicked / $totalSent) * 100, 2) : 0;
        $bounceRate = $totalSent > 0 ? round(($totalBounced / $totalSent) * 100, 2) : 0;

        $this->sendTerminal('Total sent: ' . $totalSent, 'green');
        $this->sendTerminal('Total opened: ' . $totalOpened . ' (' . $openRate . '%)', 'green');
        $this->sendTerminal('Total clicked: ' . $totalClicked . ' (' . $clickRate . '%)', 'green');
        $this->sendTerminal('Total bounced: ' . $totalBounced . ' (' . $bounceRate . '%)', 'red');
        $this->sendTerminal('');

        $sent_at = $campaign['started_at'] ?? null;
        $completed_at = $campaign['completed_at'] ?? null;

        if($sent_at) {
            $this->sendTerminal('Started: ' . $sent_at, 'white');
        }

        if($completed_at) {
            $this->sendTerminal('Completed: ' . $completed_at, 'white');
        }
    }

    /**
     * Process scheduled campaigns
     */
    public function METHOD_process_scheduled()
    {
        $this->sendTerminal('Checking for scheduled campaigns...', 'blue');

        $campaigns = $this->campaignsDS->fetchAll([['status', '=', 'scheduled']]);

        $now = time();
        $processed = 0;

        foreach($campaigns as $campaign) {
            $scheduledTime = strtotime($campaign['scheduled_at']);

            if($scheduledTime <= $now) {
                $this->sendTerminal('Processing campaign: ' . $campaign['name'], 'green');

                // Execute send method
                $this->setOptionVar('campaign_id', $campaign['KeyId']);
                $this->METHOD_send();

                $processed++;
            }
        }

        if($processed === 0) {
            $this->sendTerminal('No campaigns to process', 'yellow');
        } else {
            $this->sendTerminal('Processed ' . $processed . ' campaign(s)', 'green');
        }
    }

    // Helper methods

    private function getRecipients(array $campaign): array
    {
        $segment = $campaign['target_segment'] ?? 'all';

        $where = [['status', '=', 'active']];

        if($segment !== 'all') {
            $where[] = ['segment', '=', $segment];
        }

        return $this->usersDS->fetchAll($where);
    }

    private function renderEmail(array $campaign, array $recipient): array
    {
        $subject = $campaign['subject'];
        $html = $campaign['html_content'];

        // Replace placeholders
        $placeholders = [
            '{{first_name}}' => $recipient['first_name'] ?? $recipient['name'],
            '{{last_name}}' => $recipient['last_name'] ?? '',
            '{{name}}' => $recipient['name'],
            '{{email}}' => $recipient['email']
        ];

        foreach($placeholders as $placeholder => $value) {
            $subject = str_replace($placeholder, $value, $subject);
            $html = str_replace($placeholder, $value, $html);
        }

        return ['subject' => $subject, 'html' => $html];
    }

    private function generateTrackingPixel(string $campaignId, string $userId): string
    {
        $trackingUrl = $this->core->config->get('app.url') . "/track/open?c={$campaignId}&u={$userId}";
        return "<img src='{$trackingUrl}' width='1' height='1' />";
    }

    private function logCampaignSend(string $campaignId, string $userId, string $status)
    {
        $logsDS = $this->core->loadClass('DataStore', ['CampaignLogs']);

        $logsDS->createEntity([
            'campaign_id' => $campaignId,
            'user_id' => $userId,
            'status' => $status,
            'sent_at' => date('c')
        ]);
    }

    private function promptConfirm(string $message): bool
    {
        $response = $this->prompt($message . ' (yes/no): ');
        return strtolower(trim($response)) === 'yes';
    }
}
```

---

## Data Sync Script

Synchronize data between different systems and platforms.

**File:** `app/scripts/data-sync.php`

```php
<?php
/**
 * Data Sync Script
 *
 * Usage:
 * php index.php script=data-sync method=sync-users source=crm
 * php index.php script=data-sync method=sync-products source=shopify
 * php index.php script=data-sync method=sync-orders source=stripe
 * php index.php script=data-sync method=full-sync
 */

use CloudFramework\Patterns\Scripts2020;

class Script extends Scripts2020
{
    private $ds;
    private $synced = 0;
    private $skipped = 0;
    private $errors = 0;

    function main()
    {
        $this->sendTerminal('=== Data Sync Script ===', 'blue');
        $this->sendTerminal('');

        $method = $this->getOptionVar('method', 'full-sync');

        if(!$this->useFunction('METHOD_' . $method)) {
            $this->sendTerminal('Invalid method', 'red');
        }
    }

    /**
     * Sync users from external CRM
     */
    public function METHOD_sync_users()
    {
        $source = $this->getOptionVar('source', 'crm');

        $this->sendTerminal("Syncing users from {$source}...", 'blue');

        $this->ds = $this->core->loadClass('DataStore', ['Users']);

        // Fetch users from external source
        $externalUsers = $this->fetchExternalUsers($source);

        $this->sendTerminal('Found ' . count($externalUsers) . ' users in ' . $source, 'green');

        $startTime = microtime(true);

        foreach($externalUsers as $index => $externalUser) {
            $userNum = $index + 1;

            try {
                // Check if user exists
                $existing = $this->ds->fetchAll([
                    ['email', '=', $externalUser['email']]
                ], null, 1);

                if(!empty($existing)) {
                    // Update existing user
                    $userId = $existing[0]['KeyId'];

                    $updateData = $this->mapUserData($externalUser);
                    $updateData['updated_at'] = date('c');
                    $updateData['last_synced_at'] = date('c');

                    $this->ds->updateEntity($userId, $updateData);

                    $this->sendTerminal("[{$userNum}] Updated: {$externalUser['email']}", 'green');
                    $this->synced++;

                } else {
                    // Create new user
                    $userData = $this->mapUserData($externalUser);
                    $userData['created_at'] = date('c');
                    $userData['last_synced_at'] = date('c');
                    $userData['source'] = $source;

                    $userId = $this->ds->createEntity($userData);

                    $this->sendTerminal("[{$userNum}] Created: {$externalUser['email']}", 'blue');
                    $this->synced++;
                }

            } catch(Exception $e) {
                $this->sendTerminal("[{$userNum}] Error: {$externalUser['email']} - {$e->getMessage()}", 'red');
                $this->errors++;
            }
        }

        $duration = round(microtime(true) - $startTime, 2);

        $this->sendTerminal('', 'white');
        $this->sendTerminal('Synced: ' . $this->synced, 'green');
        $this->sendTerminal('Errors: ' . $this->errors, 'red');
        $this->sendTerminal('Duration: ' . $duration . 's', 'blue');
    }

    /**
     * Sync products from Shopify
     */
    public function METHOD_sync_products()
    {
        $source = $this->getOptionVar('source', 'shopify');

        $this->sendTerminal("Syncing products from {$source}...", 'blue');

        $this->ds = $this->core->loadClass('DataStore', ['Products']);

        // Fetch products from Shopify
        $externalProducts = $this->fetchExternalProducts($source);

        $this->sendTerminal('Found ' . count($externalProducts) . ' products', 'green');

        foreach($externalProducts as $index => $externalProduct) {
            $productNum = $index + 1;

            try {
                // Check if product exists
                $existing = $this->ds->fetchAll([
                    ['external_id', '=', $externalProduct['id']]
                ], null, 1);

                if(!empty($existing)) {
                    // Update existing product
                    $productId = $existing[0]['KeyId'];

                    $updateData = $this->mapProductData($externalProduct);
                    $updateData['updated_at'] = date('c');

                    $this->ds->updateEntity($productId, $updateData);

                    $this->sendTerminal("[{$productNum}] Updated: {$externalProduct['title']}", 'green');
                    $this->synced++;

                } else {
                    // Create new product
                    $productData = $this->mapProductData($externalProduct);
                    $productData['created_at'] = date('c');
                    $productData['source'] = $source;

                    $productId = $this->ds->createEntity($productData);

                    $this->sendTerminal("[{$productNum}] Created: {$externalProduct['title']}", 'blue');
                    $this->synced++;
                }

            } catch(Exception $e) {
                $this->sendTerminal("[{$productNum}] Error: {$externalProduct['title']} - {$e->getMessage()}", 'red');
                $this->errors++;
            }
        }

        $this->sendTerminal('', 'white');
        $this->sendTerminal('Synced: ' . $this->synced, 'green');
        $this->sendTerminal('Errors: ' . $this->errors, 'red');
    }

    /**
     * Sync orders from Stripe
     */
    public function METHOD_sync_orders()
    {
        $source = $this->getOptionVar('source', 'stripe');

        $this->sendTerminal("Syncing orders from {$source}...", 'blue');

        $this->ds = $this->core->loadClass('DataStore', ['Orders']);

        // Fetch orders from Stripe
        $externalOrders = $this->fetchExternalOrders($source);

        $this->sendTerminal('Found ' . count($externalOrders) . ' orders', 'green');

        foreach($externalOrders as $index => $externalOrder) {
            $orderNum = $index + 1;

            try {
                // Check if order exists
                $existing = $this->ds->fetchAll([
                    ['external_id', '=', $externalOrder['id']]
                ], null, 1);

                if(!empty($existing)) {
                    // Update existing order
                    $orderId = $existing[0]['KeyId'];

                    $updateData = $this->mapOrderData($externalOrder);
                    $updateData['updated_at'] = date('c');

                    $this->ds->updateEntity($orderId, $updateData);

                    $this->sendTerminal("[{$orderNum}] Updated: {$externalOrder['id']}", 'green');
                    $this->synced++;

                } else {
                    // Create new order
                    $orderData = $this->mapOrderData($externalOrder);
                    $orderData['created_at'] = date('c');
                    $orderData['source'] = $source;

                    $orderId = $this->ds->createEntity($orderData);

                    $this->sendTerminal("[{$orderNum}] Created: {$externalOrder['id']}", 'blue');
                    $this->synced++;
                }

            } catch(Exception $e) {
                $this->sendTerminal("[{$orderNum}] Error: {$externalOrder['id']} - {$e->getMessage()}", 'red');
                $this->errors++;
            }
        }

        $this->sendTerminal('', 'white');
        $this->sendTerminal('Synced: ' . $this->synced, 'green');
        $this->sendTerminal('Errors: ' . $this->errors, 'red');
    }

    /**
     * Full sync of all data
     */
    public function METHOD_full_sync()
    {
        $this->sendTerminal('=== Starting Full Data Sync ===', 'blue');
        $this->sendTerminal('');

        $startTime = microtime(true);

        // Sync users
        $this->sendTerminal('1. Syncing Users', 'yellow');
        $this->METHOD_sync_users();
        $this->resetCounters();

        $this->sendTerminal('', 'white');

        // Sync products
        $this->sendTerminal('2. Syncing Products', 'yellow');
        $this->METHOD_sync_products();
        $this->resetCounters();

        $this->sendTerminal('', 'white');

        // Sync orders
        $this->sendTerminal('3. Syncing Orders', 'yellow');
        $this->METHOD_sync_orders();

        $totalDuration = round(microtime(true) - $startTime, 2);

        $this->sendTerminal('', 'white');
        $this->sendTerminal('=== Full Sync Completed ===', 'blue');
        $this->sendTerminal('Total duration: ' . $totalDuration . 's', 'green');
    }

    /**
     * Sync to BigQuery for analytics
     */
    public function METHOD_sync_to_bigquery()
    {
        $this->sendTerminal('Syncing data to BigQuery...', 'blue');

        $bq = $this->core->loadClass('DataBQ', ['analytics']);

        // Sync users
        $usersDS = $this->core->loadClass('DataStore', ['Users']);
        $users = $usersDS->fetchAll();

        $this->sendTerminal('Syncing ' . count($users) . ' users to BigQuery...', 'yellow');

        $result = $bq->insert('users', $users);

        if($bq->error()) {
            $this->sendTerminal('Failed to sync users', 'red');
        } else {
            $this->sendTerminal('✓ Users synced', 'green');
        }

        // Sync orders
        $ordersDS = $this->core->loadClass('DataStore', ['Orders']);
        $orders = $ordersDS->fetchAll();

        $this->sendTerminal('Syncing ' . count($orders) . ' orders to BigQuery...', 'yellow');

        $result = $bq->insert('orders', $orders);

        if($bq->error()) {
            $this->sendTerminal('Failed to sync orders', 'red');
        } else {
            $this->sendTerminal('✓ Orders synced', 'green');
        }

        $this->sendTerminal('', 'white');
        $this->sendTerminal('✓ BigQuery sync completed', 'green');
    }

    // Helper methods

    private function fetchExternalUsers(string $source): array
    {
        // Mock implementation - replace with actual API call
        if($source === 'crm') {
            // Fetch from CRM API
            $apiUrl = $this->core->config->get('sync.crm.api_url');
            $apiKey = $this->core->config->get('sync.crm.api_key');

            // Make API request
            // $response = file_get_contents($apiUrl . '/users', [
            //     'http' => ['header' => "Authorization: Bearer {$apiKey}"]
            // ]);

            // return json_decode($response, true);
        }

        // Mock data
        return [
            ['id' => '1', 'email' => 'user1@example.com', 'name' => 'User 1', 'phone' => '123456789'],
            ['id' => '2', 'email' => 'user2@example.com', 'name' => 'User 2', 'phone' => '987654321']
        ];
    }

    private function fetchExternalProducts(string $source): array
    {
        // Mock implementation
        return [
            ['id' => 'prod_1', 'title' => 'Product 1', 'price' => 29.99, 'stock' => 100],
            ['id' => 'prod_2', 'title' => 'Product 2', 'price' => 49.99, 'stock' => 50]
        ];
    }

    private function fetchExternalOrders(string $source): array
    {
        // Mock implementation
        return [
            ['id' => 'order_1', 'customer_email' => 'user1@example.com', 'amount' => 100, 'status' => 'paid'],
            ['id' => 'order_2', 'customer_email' => 'user2@example.com', 'amount' => 200, 'status' => 'paid']
        ];
    }

    private function mapUserData(array $externalUser): array
    {
        return [
            'external_id' => $externalUser['id'],
            'email' => $externalUser['email'],
            'name' => $externalUser['name'],
            'phone' => $externalUser['phone'] ?? null,
            'status' => 'active'
        ];
    }

    private function mapProductData(array $externalProduct): array
    {
        return [
            'external_id' => $externalProduct['id'],
            'name' => $externalProduct['title'],
            'price' => $externalProduct['price'],
            'stock' => $externalProduct['stock'],
            'status' => 'active'
        ];
    }

    private function mapOrderData(array $externalOrder): array
    {
        return [
            'external_id' => $externalOrder['id'],
            'customer_email' => $externalOrder['customer_email'],
            'amount' => $externalOrder['amount'],
            'status' => $externalOrder['status']
        ];
    }

    private function resetCounters()
    {
        $this->synced = 0;
        $this->skipped = 0;
        $this->errors = 0;
    }
}
```

---

## Cleanup Script

Automated cleanup of old data, logs, and temporary files.

**File:** `app/scripts/cleanup.php`

```php
<?php
/**
 * Cleanup Script
 *
 * Usage:
 * php index.php script=cleanup method=logs days=30
 * php index.php script=cleanup method=cache
 * php index.php script=cleanup method=temp-files
 * php index.php script=cleanup method=old-data days=90
 * php index.php script=cleanup method=all
 */

use CloudFramework\Patterns\Scripts2020;

class Script extends Scripts2020
{
    private $deleted = 0;
    private $totalSize = 0;

    function main()
    {
        $this->sendTerminal('=== Cleanup Script ===', 'blue');
        $this->sendTerminal('');

        $method = $this->getOptionVar('method', 'all');

        if(!$this->useFunction('METHOD_' . $method)) {
            $this->sendTerminal('Invalid method', 'red');
        }
    }

    /**
     * Clean old logs
     */
    public function METHOD_logs()
    {
        $days = (int)($this->getOptionVar('days', 30));

        $this->sendTerminal("Cleaning logs older than {$days} days...", 'blue');

        $logsDS = $this->core->loadClass('DataStore', ['Logs']);

        $cutoffDate = date('Y-m-d', strtotime("-{$days} days"));

        // Get old logs
        $oldLogs = $logsDS->fetchAll([
            ['created_at', '<', $cutoffDate]
        ]);

        $this->sendTerminal('Found ' . count($oldLogs) . ' old log entries', 'yellow');

        if(empty($oldLogs)) {
            return $this->sendTerminal('No logs to clean', 'green');
        }

        if(!$this->promptConfirm('Delete these log entries?')) {
            return $this->sendTerminal('Cleanup cancelled', 'yellow');
        }

        // Delete logs
        foreach($oldLogs as $log) {
            $logsDS->delete($log['KeyId']);

            if(!$logsDS->error()) {
                $this->deleted++;
            }
        }

        $this->sendTerminal('✓ Deleted ' . $this->deleted . ' log entries', 'green');
    }

    /**
     * Clean cache
     */
    public function METHOD_cache()
    {
        $this->sendTerminal('Cleaning cache...', 'blue');

        $cachePath = $this->core->config->get('core.cache.cache_path');

        if(!$cachePath || !is_dir($cachePath)) {
            return $this->sendTerminal('Cache directory not found', 'red');
        }

        $this->sendTerminal('Cache path: ' . $cachePath, 'white');

        // Get cache files
        $files = glob($cachePath . '/*');

        $this->sendTerminal('Found ' . count($files) . ' cache files', 'yellow');

        if(empty($files)) {
            return $this->sendTerminal('No cache files to clean', 'green');
        }

        // Calculate total size
        $totalSize = 0;
        foreach($files as $file) {
            if(is_file($file)) {
                $totalSize += filesize($file);
            }
        }

        $this->sendTerminal('Total cache size: ' . $this->formatBytes($totalSize), 'white');

        if(!$this->promptConfirm('Delete all cache files?')) {
            return $this->sendTerminal('Cache cleanup cancelled', 'yellow');
        }

        // Delete cache files
        foreach($files as $file) {
            if(is_file($file)) {
                unlink($file);
                $this->deleted++;
            }
        }

        $this->sendTerminal('✓ Deleted ' . $this->deleted . ' cache files', 'green');
        $this->sendTerminal('✓ Freed ' . $this->formatBytes($totalSize) . ' of space', 'green');
    }

    /**
     * Clean temporary files
     */
    public function METHOD_temp_files()
    {
        $this->sendTerminal('Cleaning temporary files...', 'blue');

        $tempPath = '/tmp';
        $prefix = 'cf_'; // CloudFramework temp files prefix

        $pattern = $tempPath . '/' . $prefix . '*';
        $files = glob($pattern);

        $this->sendTerminal('Found ' . count($files) . ' temporary files', 'yellow');

        if(empty($files)) {
            return $this->sendTerminal('No temporary files to clean', 'green');
        }

        // Calculate size and filter old files
        $oldFiles = [];
        $totalSize = 0;
        $cutoff = time() - (24 * 3600); // 24 hours

        foreach($files as $file) {
            if(is_file($file) && filemtime($file) < $cutoff) {
                $oldFiles[] = $file;
                $totalSize += filesize($file);
            }
        }

        $this->sendTerminal('Found ' . count($oldFiles) . ' old temp files (' . $this->formatBytes($totalSize) . ')', 'yellow');

        if(empty($oldFiles)) {
            return $this->sendTerminal('No old temporary files to clean', 'green');
        }

        if(!$this->promptConfirm('Delete old temporary files?')) {
            return $this->sendTerminal('Cleanup cancelled', 'yellow');
        }

        // Delete files
        foreach($oldFiles as $file) {
            unlink($file);
            $this->deleted++;
        }

        $this->sendTerminal('✓ Deleted ' . $this->deleted . ' temporary files', 'green');
        $this->sendTerminal('✓ Freed ' . $this->formatBytes($totalSize) . ' of space', 'green');
    }

    /**
     * Clean old data
     */
    public function METHOD_old_data()
    {
        $days = (int)($this->getOptionVar('days', 90));

        $this->sendTerminal("Cleaning data older than {$days} days...", 'blue');

        $cutoffDate = date('Y-m-d', strtotime("-{$days} days"));

        // Clean old sessions
        $this->sendTerminal('Cleaning old sessions...', 'yellow');
        $sessionsDS = $this->core->loadClass('DataStore', ['Sessions']);
        $oldSessions = $sessionsDS->fetchAll([['created_at', '<', $cutoffDate]]);

        foreach($oldSessions as $session) {
            $sessionsDS->delete($session['KeyId']);
            $this->deleted++;
        }

        $this->sendTerminal('  ✓ Deleted ' . count($oldSessions) . ' old sessions', 'green');

        // Clean old notifications
        $this->sendTerminal('Cleaning old notifications...', 'yellow');
        $notificationsDS = $this->core->loadClass('DataStore', ['Notifications']);
        $oldNotifications = $notificationsDS->fetchAll([
            ['created_at', '<', $cutoffDate],
            ['read', '=', true]
        ]);

        foreach($oldNotifications as $notification) {
            $notificationsDS->delete($notification['KeyId']);
            $this->deleted++;
        }

        $this->sendTerminal('  ✓ Deleted ' . count($oldNotifications) . ' old notifications', 'green');

        // Clean old analytics events
        $this->sendTerminal('Cleaning old analytics events...', 'yellow');
        $eventsDS = $this->core->loadClass('DataStore', ['AnalyticsEvents']);
        $oldEvents = $eventsDS->fetchAll([['created_at', '<', $cutoffDate]]);

        foreach($oldEvents as $event) {
            $eventsDS->delete($event['KeyId']);
            $this->deleted++;
        }

        $this->sendTerminal('  ✓ Deleted ' . count($oldEvents) . ' old events', 'green');

        $this->sendTerminal('', 'white');
        $this->sendTerminal('Total deleted: ' . $this->deleted . ' records', 'green');
    }

    /**
     * Clean everything
     */
    public function METHOD_all()
    {
        $this->sendTerminal('=== Running Full Cleanup ===', 'blue');
        $this->sendTerminal('');

        $startTime = microtime(true);

        // Clean logs
        $this->sendTerminal('1. Cleaning Logs', 'yellow');
        $this->METHOD_logs();
        $this->resetCounters();

        $this->sendTerminal('', 'white');

        // Clean cache
        $this->sendTerminal('2. Cleaning Cache', 'yellow');
        $this->METHOD_cache();
        $this->resetCounters();

        $this->sendTerminal('', 'white');

        // Clean temp files
        $this->sendTerminal('3. Cleaning Temporary Files', 'yellow');
        $this->METHOD_temp_files();
        $this->resetCounters();

        $this->sendTerminal('', 'white');

        // Clean old data
        $this->sendTerminal('4. Cleaning Old Data', 'yellow');
        $this->METHOD_old_data();

        $duration = round(microtime(true) - $startTime, 2);

        $this->sendTerminal('', 'white');
        $this->sendTerminal('=== Cleanup Completed ===', 'blue');
        $this->sendTerminal('Total duration: ' . $duration . 's', 'green');
    }

    /**
     * Vacuum/optimize database
     */
    public function METHOD_optimize_database()
    {
        $this->sendTerminal('Optimizing database...', 'blue');

        $sql = $this->core->loadClass('CloudSQL');

        // Get all tables
        $tables = $sql->command("SHOW TABLES");

        if($sql->error()) {
            return $this->sendTerminal('Failed to get tables', 'red');
        }

        $this->sendTerminal('Found ' . count($tables) . ' tables', 'yellow');

        foreach($tables as $table) {
            $tableName = array_values($table)[0];

            $this->sendTerminal('Optimizing: ' . $tableName, 'white');

            $sql->command("OPTIMIZE TABLE `{$tableName}`");

            if(!$sql->error()) {
                $this->sendTerminal('  ✓ Optimized', 'green');
            } else {
                $this->sendTerminal('  ✗ Failed', 'red');
            }
        }

        $this->sendTerminal('', 'white');
        $this->sendTerminal('✓ Database optimization completed', 'green');
    }

    /**
     * Clean Cloud Storage old files
     */
    public function METHOD_storage()
    {
        $days = (int)($this->getOptionVar('days', 90));

        $this->sendTerminal("Cleaning Cloud Storage files older than {$days} days...", 'blue');

        $buckets = $this->core->loadClass('Buckets', ['gs://my-uploads-bucket']);

        $files = $buckets->scan('uploads/');

        if($buckets->error()) {
            return $this->sendTerminal('Failed to scan bucket', 'red');
        }

        $this->sendTerminal('Found ' . count($files) . ' files', 'yellow');

        $cutoff = time() - ($days * 24 * 3600);
        $oldFiles = [];
        $totalSize = 0;

        foreach($files as $file) {
            $info = $buckets->getFileInfo($file);

            if(isset($info['updated']) && strtotime($info['updated']) < $cutoff) {
                $oldFiles[] = $file;
                $totalSize += $info['size'] ?? 0;
            }
        }

        $this->sendTerminal('Found ' . count($oldFiles) . ' old files (' . $this->formatBytes($totalSize) . ')', 'yellow');

        if(empty($oldFiles)) {
            return $this->sendTerminal('No old files to clean', 'green');
        }

        if(!$this->promptConfirm('Delete old files from Cloud Storage?')) {
            return $this->sendTerminal('Cleanup cancelled', 'yellow');
        }

        foreach($oldFiles as $file) {
            $buckets->delete($file);

            if(!$buckets->error()) {
                $this->deleted++;
                $this->sendTerminal('  ✓ Deleted: ' . basename($file), 'green');
            } else {
                $this->sendTerminal('  ✗ Failed: ' . basename($file), 'red');
            }
        }

        $this->sendTerminal('', 'white');
        $this->sendTerminal('✓ Deleted ' . $this->deleted . ' files', 'green');
        $this->sendTerminal('✓ Freed ' . $this->formatBytes($totalSize) . ' of space', 'green');
    }

    // Helper methods

    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= (1 << (10 * $pow));

        return round($bytes, 2) . ' ' . $units[$pow];
    }

    private function promptConfirm(string $message): bool
    {
        $response = $this->prompt($message . ' (yes/no): ');
        return strtolower(trim($response)) === 'yes';
    }

    private function resetCounters()
    {
        $this->deleted = 0;
        $this->totalSize = 0;
    }
}
```

---

## See Also

- [Script Development Guide](../guides/script-development.md)
- [Scripts2020 Class Reference](../api-reference/Scripts2020.md)
- [API Examples](api-examples.md)
- [GCP Examples](gcp-examples.md)
