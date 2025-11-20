# Script Development Guide

This guide covers everything you need to know about developing CLI scripts with CloudFramework Backend Core.

## Table of Contents

- [Introduction](#introduction)
- [Script Basics](#script-basics)
- [Script Structure](#script-structure)
- [Terminal Output](#terminal-output)
- [User Input](#user-input)
- [Command-Line Arguments](#command-line-arguments)
- [Working with Data](#working-with-data)
- [Caching](#caching)
- [Error Handling](#error-handling)
- [Scheduled Tasks](#scheduled-tasks)
- [Best Practices](#best-practices)

## Introduction

Scripts in CloudFramework are CLI programs that extend the `Scripts2020` base class. They're perfect for:

- Background processing
- Data migrations
- Database maintenance
- Scheduled tasks (cron jobs)
- Administrative tools
- Batch operations
- System utilities

## Script Basics

### Creating Your First Script

1. Create a file in the `scripts/` directory:

```php
<?php
/**
 * My First Script
 * scripts/hello.php
 */
class Script extends Scripts2020
{
    function main()
    {
        $this->sendTerminal("Hello from my script!");
    }
}
```

2. Run it:

```shell
composer script hello
```

### Script Naming Convention

- Script files go in `scripts/` directory
- Can be organized in subdirectories: `scripts/admin/users.php`
- Class name must be `Script`
- Extends `Scripts2020`

## Script Structure

### Basic Structure

```php
<?php
class Script extends Scripts2020
{
    function main()
    {
        // Entry point - always called first
        $this->sendTerminal("Script started");

        // Your logic here

        $this->sendTerminal("Script completed");
    }
}
```

### Multi-Method Structure

```php
<?php
class Script extends Scripts2020
{
    function main()
    {
        // Get method from parameters
        $method = $this->params[0] ?? 'default';

        // Route to METHOD_{name}
        if (!$this->useFunction('METHOD_' . $method)) {
            return $this->setError("Method {$method} not found");
        }
    }

    // composer script myscript
    function METHOD_default()
    {
        $this->sendTerminal("Available methods:");
        $this->sendTerminal("  - composer script myscript/process");
        $this->sendTerminal("  - composer script myscript/cleanup");
    }

    // composer script myscript/process
    function METHOD_process()
    {
        $this->sendTerminal("Processing...");
        // Your processing logic
    }

    // composer script myscript/cleanup
    function METHOD_cleanup()
    {
        $this->sendTerminal("Cleaning up...");
        // Your cleanup logic
    }
}
```

## Terminal Output

### Basic Output

```php
// Simple message
$this->sendTerminal("Processing complete");

// Multiple arguments
$this->sendTerminal("User:", $userName, "Email:", $email);

// Arrays and objects
$this->sendTerminal($dataArray);

// Formatted output
$this->sendTerminal("===================");
$this->sendTerminal("=== My Report ===");
$this->sendTerminal("===================");
```

### Progress Indicators

```php
function METHOD_process()
{
    $items = range(1, 100);
    $total = count($items);

    foreach($items as $index => $item) {
        // Process item
        sleep(0.1);

        // Show progress
        $progress = round(($index + 1) / $total * 100);
        $this->sendTerminal("Progress: {$progress}% ({$index + 1}/{$total})");
    }
}
```

### Formatted Tables

```php
function METHOD_report()
{
    $this->sendTerminal(str_repeat("-", 60));
    $this->sendTerminal(sprintf("%-20s %-20s %-15s", "Name", "Email", "Status"));
    $this->sendTerminal(str_repeat("-", 60));

    foreach($users as $user) {
        $this->sendTerminal(sprintf(
            "%-20s %-20s %-15s",
            $user['name'],
            $user['email'],
            $user['status']
        ));
    }

    $this->sendTerminal(str_repeat("-", 60));
    $this->sendTerminal("Total: " . count($users) . " users");
}
```

### Color Output (ANSI)

```php
function METHOD_colorful()
{
    // Colors
    $red = "\033[31m";
    $green = "\033[32m";
    $yellow = "\033[33m";
    $blue = "\033[34m";
    $reset = "\033[0m";

    $this->sendTerminal("{$green}✓{$reset} Success message");
    $this->sendTerminal("{$red}✗{$reset} Error message");
    $this->sendTerminal("{$yellow}⚠{$reset} Warning message");
    $this->sendTerminal("{$blue}ℹ{$reset} Info message");
}
```

## User Input

### Simple Prompts

```php
// Basic prompt
$name = $this->prompt("Enter your name: ");

// With default value
$port = $this->prompt("Port number: ", "3306");

// With cache (remembers last input)
$apiKey = $this->prompt("API Key: ", null, 'api_key');
```

### Option Selection

```php
// Select from options
$environment = $this->promptOptions(
    "Select environment:",
    ['development', 'staging', 'production'],
    'development',
    'environment'
);

// Yes/No confirmation
$confirm = $this->prompt("Are you sure? (yes/no): ", "no");
if($confirm !== 'yes') {
    $this->sendTerminal("Operation cancelled");
    return;
}
```

### Interactive Configuration

```php
function METHOD_configure()
{
    $this->sendTerminal("=== Database Configuration ===");

    $config = [];
    $config['host'] = $this->prompt("Database host: ", "localhost", 'db_host');
    $config['port'] = $this->prompt("Port: ", "3306", 'db_port');
    $config['database'] = $this->prompt("Database name: ", null, 'db_name');
    $config['username'] = $this->prompt("Username: ", "root", 'db_user');
    $config['password'] = $this->prompt("Password: ", null, 'db_password');

    // Save configuration
    $this->core->config->set('database', $config);

    $this->sendTerminal("Configuration saved");
}
```

## Command-Line Arguments

### URL Parameters

```shell
# composer script myscript/action/param1/param2
```

```php
function main()
{
    $action = $this->params[0] ?? 'default';  // 'action'
    $param1 = $this->params[1] ?? null;        // 'param1'
    $param2 = $this->params[2] ?? null;        // 'param2'
}
```

### Option Flags

```shell
# composer script myscript --verbose --force --dry-run
```

```php
function METHOD_process()
{
    $verbose = $this->hasOption('verbose');
    $force = $this->hasOption('force');
    $dryRun = $this->hasOption('dry-run');

    if($verbose) {
        $this->sendTerminal("Verbose mode enabled");
    }

    if($dryRun) {
        $this->sendTerminal("DRY RUN - No changes will be made");
    }
}
```

### Option Variables

```shell
# composer script myscript --limit=100 --format=csv --output=/tmp/export.csv
```

```php
function METHOD_export()
{
    $limit = (int)($this->getOptionVar('limit') ?? 1000);
    $format = $this->getOptionVar('format') ?? 'json';
    $output = $this->getOptionVar('output') ?? 'export.csv';

    $this->sendTerminal("Limit: {$limit}");
    $this->sendTerminal("Format: {$format}");
    $this->sendTerminal("Output: {$output}");
}
```

## Working with Data

### Loading Data from Datastore

```php
function METHOD_export_users()
{
    $this->sendTerminal("Exporting users...");

    // Load DataStore
    $ds = $this->core->loadClass('DataStore', ['Users']);

    // Fetch users
    $users = $ds->fetchAll('*', ['active' => true]);

    $this->sendTerminal("Found " . count($users) . " users");

    // Export to CSV
    $fp = fopen('users.csv', 'w');
    fputcsv($fp, ['ID', 'Name', 'Email', 'Created']);

    foreach($users as $user) {
        fputcsv($fp, [
            $user['KeyId'],
            $user['name'],
            $user['email'],
            $user['created_at']
        ]);
    }

    fclose($fp);
    $this->sendTerminal("Exported to users.csv");
}
```

### Batch Processing

```php
function METHOD_process_orders()
{
    $this->sendTerminal("Processing orders...");

    $ds = $this->core->loadClass('DataStore', ['Orders']);

    // Process in batches
    $batchSize = 100;
    $offset = 0;
    $processed = 0;

    do {
        // Fetch batch
        $ds->setLimit($batchSize);
        $ds->setOffset($offset);
        $orders = $ds->fetchAll('*', ['status' => 'pending']);

        foreach($orders as $order) {
            // Process order
            $this->processOrder($order);
            $processed++;

            if($processed % 10 == 0) {
                $this->sendTerminal("Processed: {$processed}");
            }
        }

        $offset += $batchSize;

    } while(count($orders) == $batchSize);

    $this->sendTerminal("Total processed: {$processed}");
}
```

### Database Operations

```php
function METHOD_migrate_data()
{
    $sql = $this->core->loadClass('CloudSQL');

    $this->sendTerminal("Starting migration...");

    // Start transaction
    $sql->command("START TRANSACTION");

    try {
        // Read from old table
        $oldData = $sql->command("SELECT * FROM old_table");

        // Insert into new table
        foreach($oldData as $row) {
            $sql->command("
                INSERT INTO new_table (id, name, email)
                VALUES (?, ?, ?)
            ", [$row['id'], $row['name'], $row['email']]);
        }

        // Commit
        $sql->command("COMMIT");
        $this->sendTerminal("Migration completed: " . count($oldData) . " records");

    } catch(Exception $e) {
        $sql->command("ROLLBACK");
        $this->setError("Migration failed: " . $e->getMessage());
    }
}
```

## Caching

### Saving Script State

```php
function METHOD_long_process()
{
    // Check if we have a saved state
    $lastProcessed = $this->getCacheVar('last_processed_id');

    if($lastProcessed) {
        $this->sendTerminal("Resuming from ID: {$lastProcessed}");
    } else {
        $this->sendTerminal("Starting fresh");
        $lastProcessed = 0;
    }

    // Process items
    $ds = $this->core->loadClass('DataStore', ['Items']);
    $items = $ds->fetchAll('*', ['id' => ['>' => $lastProcessed]]);

    foreach($items as $item) {
        // Process item
        $this->processItem($item);

        // Save progress
        $this->setCacheVar('last_processed_id', $item['KeyId']);
    }

    $this->sendTerminal("Process completed");
    $this->setCacheVar('last_run_time', time());
}
```

### Caching Configuration

```php
function METHOD_setup()
{
    // Get cached values
    $apiKey = $this->getCacheVar('api_key');
    $apiUrl = $this->getCacheVar('api_url');

    if(!$apiKey) {
        $apiKey = $this->prompt("API Key: ", null, 'api_key');
        $this->setCacheVar('api_key', $apiKey);
    }

    if(!$apiUrl) {
        $apiUrl = $this->prompt("API URL: ", "https://api.example.com", 'api_url');
        $this->setCacheVar('api_url', $apiUrl);
    }

    $this->sendTerminal("Configuration ready");
}
```

### Clearing Cache

```php
function METHOD_reset()
{
    $this->cleanCache();
    $this->sendTerminal("Cache cleared");
}
```

## Error Handling

### Basic Error Handling

```php
function METHOD_risky_operation()
{
    if(!file_exists($file)) {
        $this->setError("File not found: {$file}");
        return;
    }

    if(!is_writable($directory)) {
        $this->setError("Directory not writable: {$directory}");
        return;
    }

    // Continue with operation
}
```

### Try-Catch Error Handling

```php
function METHOD_safe_process()
{
    try {
        $this->sendTerminal("Processing...");

        // Risky operation
        $result = $this->riskyOperation();

        $this->sendTerminal("Success: " . $result);

    } catch(Exception $e) {
        $this->setError("Error: " . $e->getMessage());
        $this->core->logs->add($e->getMessage(), 'script-error', 'error');
    }
}
```

### Multiple Error Collection

```php
function METHOD_validate()
{
    $errors = [];

    if(!$condition1) {
        $errors[] = "Condition 1 failed";
    }

    if(!$condition2) {
        $errors[] = "Condition 2 failed";
    }

    if($errors) {
        $this->sendTerminal("Validation errors:");
        foreach($errors as $error) {
            $this->sendTerminal("  - {$error}");
        }
        return;
    }

    $this->sendTerminal("Validation passed");
}
```

## Scheduled Tasks

### Cron Job Script

```php
<?php
/**
 * Daily Maintenance Script
 * Run via cron: 0 2 * * * cd /path/to/project && composer script maintenance
 */
class Script extends Scripts2020
{
    function main()
    {
        $this->sendTerminal("=== Daily Maintenance ===");
        $this->sendTerminal("Started: " . date('Y-m-d H:i:s'));

        // Clean old logs
        $this->cleanOldLogs();

        // Backup database
        $this->backupDatabase();

        // Send report
        $this->sendDailyReport();

        $this->sendTerminal("Completed: " . date('Y-m-d H:i:s'));
    }

    private function cleanOldLogs()
    {
        $this->sendTerminal("Cleaning old logs...");

        $ds = $this->core->loadClass('DataStore', ['Logs']);
        $cutoff = date('Y-m-d', strtotime('-30 days'));

        $count = $ds->delete(['created_at' => ['<' => $cutoff]]);

        $this->sendTerminal("Deleted {$count} old logs");
    }

    private function backupDatabase()
    {
        $this->sendTerminal("Backing up database...");

        $sql = $this->core->loadClass('CloudSQL');
        $tables = ['users', 'orders', 'products'];

        foreach($tables as $table) {
            $data = $sql->command("SELECT * FROM {$table}");
            file_put_contents(
                "backup_{$table}_" . date('Y-m-d') . ".json",
                json_encode($data)
            );
        }

        $this->sendTerminal("Backup completed");
    }

    private function sendDailyReport()
    {
        $this->sendTerminal("Sending daily report...");

        // Get statistics
        $sql = $this->core->loadClass('CloudSQL');
        $stats = $sql->command("
            SELECT
                COUNT(*) as total_orders,
                SUM(total) as revenue
            FROM orders
            WHERE DATE(created_at) = CURDATE()
        ");

        // Send email
        $email = $this->core->loadClass('Email');
        $email->send([
            'to' => 'admin@example.com',
            'subject' => 'Daily Report - ' . date('Y-m-d'),
            'body' => "Orders: {$stats[0]['total_orders']}\nRevenue: $" .
                      number_format($stats[0]['revenue'], 2)
        ]);

        $this->sendTerminal("Report sent");
    }
}
```

### Rate-Limited Script

```php
function METHOD_sync()
{
    // Check last run time
    $lastRun = $this->getCacheVar('last_sync_time');
    $now = time();

    // Minimum 1 hour between runs
    if($lastRun && ($now - $lastRun) < 3600) {
        $nextRun = date('H:i:s', $lastRun + 3600);
        $this->sendTerminal("Too soon. Next run available at: {$nextRun}");
        return;
    }

    // Run sync
    $this->sendTerminal("Starting sync...");
    // ... sync logic ...

    // Save last run time
    $this->setCacheVar('last_sync_time', $now);
}
```

## Best Practices

### 1. Clear Output Messages

```php
// Good
$this->sendTerminal("=== Starting Data Import ===");
$this->sendTerminal("Source: {$file}");
$this->sendTerminal("Processed: {$count} records");
$this->sendTerminal("Completed successfully");

// Bad
$this->sendTerminal("Starting");
$this->sendTerminal($count);
```

### 2. Save Progress for Long Operations

```php
// Save state periodically
foreach($items as $index => $item) {
    $this->processItem($item);

    if($index % 100 == 0) {
        $this->setCacheVar('last_index', $index);
        $this->sendTerminal("Progress saved at: {$index}");
    }
}
```

### 3. Use Options for Flexibility

```php
// Support different modes
$dryRun = $this->hasOption('dry-run');
$verbose = $this->hasOption('verbose');
$force = $this->hasOption('force');

if($dryRun) {
    $this->sendTerminal("DRY RUN - No changes will be made");
}
```

### 4. Handle Interruptions Gracefully

```php
// Register shutdown handler
register_shutdown_function(function() {
    $this->sendTerminal("Script interrupted - saving state");
    $this->setCacheVar('interrupted', true);
});

// Check if previous run was interrupted
if($this->getCacheVar('interrupted')) {
    $this->sendTerminal("Previous run was interrupted - resuming");
    $this->setCacheVar('interrupted', false);
}
```

### 5. Log Important Operations

```php
// Log to file
$this->core->logs->add("Started import from {$file}", 'import');
$this->core->logs->add("Processed {$count} records", 'import');

// Log errors
if($error) {
    $this->core->errors->add($error, 'import', 'error');
}
```

### 6. Provide Help Messages

```php
function METHOD_default()
{
    $this->sendTerminal("=== Data Manager ===");
    $this->sendTerminal("");
    $this->sendTerminal("Usage:");
    $this->sendTerminal("  composer script manager/import --source=file.csv");
    $this->sendTerminal("  composer script manager/export --format=json");
    $this->sendTerminal("  composer script manager/clean --confirm");
    $this->sendTerminal("");
    $this->sendTerminal("Options:");
    $this->sendTerminal("  --dry-run     Simulate without making changes");
    $this->sendTerminal("  --verbose     Show detailed output");
    $this->sendTerminal("  --force       Skip confirmations");
}
```

### 7. Use Transactions for Database Operations

```php
$sql = $this->core->loadClass('CloudSQL');
$sql->command("START TRANSACTION");

try {
    // Multiple operations
    $sql->command("UPDATE ...");
    $sql->command("INSERT ...");
    $sql->command("DELETE ...");

    $sql->command("COMMIT");
    $this->sendTerminal("All changes committed");

} catch(Exception $e) {
    $sql->command("ROLLBACK");
    $this->setError("Transaction failed: " . $e->getMessage());
}
```

### 8. Time Execution

```php
function METHOD_benchmark()
{
    $start = microtime(true);

    // Your operation
    $this->heavyOperation();

    $duration = round(microtime(true) - $start, 2);
    $this->sendTerminal("Completed in {$duration} seconds");
}
```

## Complete Example

Here's a comprehensive script example:

```php
<?php
/**
 * User Management Script
 */
class Script extends Scripts2020
{
    function main()
    {
        $method = $this->params[0] ?? 'help';

        if (!$this->useFunction('METHOD_' . $method)) {
            return $this->METHOD_help();
        }
    }

    function METHOD_help()
    {
        $this->sendTerminal("=== User Management Script ===");
        $this->sendTerminal("");
        $this->sendTerminal("Commands:");
        $this->sendTerminal("  list                  List all users");
        $this->sendTerminal("  import --file=x.csv   Import users from CSV");
        $this->sendTerminal("  export --format=csv   Export users");
        $this->sendTerminal("  cleanup --days=30     Remove inactive users");
        $this->sendTerminal("");
        $this->sendTerminal("Options:");
        $this->sendTerminal("  --verbose             Show detailed output");
        $this->sendTerminal("  --dry-run             Simulate without changes");
    }

    function METHOD_list()
    {
        $verbose = $this->hasOption('verbose');

        $ds = $this->core->loadClass('DataStore', ['Users']);
        $users = $ds->fetchAll();

        $this->sendTerminal("Total users: " . count($users));

        if($verbose) {
            foreach($users as $user) {
                $this->sendTerminal("  {$user['KeyId']}: {$user['name']} ({$user['email']})");
            }
        }
    }

    function METHOD_import()
    {
        $file = $this->getOptionVar('file');
        $dryRun = $this->hasOption('dry-run');

        if(!$file) {
            $file = $this->prompt("CSV file path: ", "users.csv");
        }

        if(!file_exists($file)) {
            return $this->setError("File not found: {$file}");
        }

        $this->sendTerminal("Importing from: {$file}");

        if($dryRun) {
            $this->sendTerminal("DRY RUN - No changes will be made");
        }

        $data = array_map('str_getcsv', file($file));
        $headers = array_shift($data);

        $ds = $this->core->loadClass('DataStore', ['Users']);
        $imported = 0;

        foreach($data as $row) {
            $user = array_combine($headers, $row);

            if(!$dryRun) {
                $ds->createEntity($user);
            }

            $imported++;

            if($imported % 100 == 0) {
                $this->sendTerminal("Imported: {$imported}");
            }
        }

        $this->sendTerminal("Total imported: {$imported}");
    }

    function METHOD_export()
    {
        $format = $this->getOptionVar('format') ?? 'csv';

        $ds = $this->core->loadClass('DataStore', ['Users']);
        $users = $ds->fetchAll();

        $filename = "users_export_" . date('Y-m-d_His') . ".{$format}";

        if($format === 'csv') {
            $fp = fopen($filename, 'w');
            fputcsv($fp, ['ID', 'Name', 'Email', 'Created']);

            foreach($users as $user) {
                fputcsv($fp, [
                    $user['KeyId'],
                    $user['name'],
                    $user['email'],
                    $user['created_at']
                ]);
            }

            fclose($fp);
        } else {
            file_put_contents($filename, json_encode($users, JSON_PRETTY_PRINT));
        }

        $this->sendTerminal("Exported to: {$filename}");
    }

    function METHOD_cleanup()
    {
        $days = (int)($this->getOptionVar('days') ?? 90);
        $dryRun = $this->hasOption('dry-run');

        $this->sendTerminal("Cleaning up inactive users (>{$days} days)");

        $ds = $this->core->loadClass('DataStore', ['Users']);
        $cutoff = date('Y-m-d', strtotime("-{$days} days"));

        $inactive = $ds->fetchAll('*', [
            'last_login' => ['<' => $cutoff],
            'active' => false
        ]);

        $this->sendTerminal("Found " . count($inactive) . " inactive users");

        if(!$dryRun) {
            $confirm = $this->prompt("Delete these users? (yes/no): ", "no");

            if($confirm === 'yes') {
                foreach($inactive as $user) {
                    $ds->delete(['KeyId' => $user['KeyId']]);
                }
                $this->sendTerminal("Deleted " . count($inactive) . " users");
            } else {
                $this->sendTerminal("Cancelled");
            }
        } else {
            $this->sendTerminal("DRY RUN - No users deleted");
        }
    }
}
```

## See Also

- [Getting Started Guide](getting-started.md)
- [Scripts2020 Class Reference](../api-reference/Scripts2020.md)
- [Core7 Class Reference](../api-reference/Core7.md)
- [API Development Guide](api-development.md)
