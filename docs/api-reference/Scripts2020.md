# Scripts2020 Class

## Overview

The `Scripts2020` class is the base class for all CLI scripts in CloudFramework. It provides utilities for terminal output, user prompts, parameter handling, caching, and error management.

## Basic Usage

```php
<?php
/**
 * My Script
 */
class Script extends Scripts2020
{
    function main()
    {
        // Your script logic here
        $this->sendTerminal("Hello from script!");

        // Access core framework
        $config = $this->core->config->get('my_setting');

        // Use script utilities
        $answer = $this->prompt("Enter your name: ");
        $this->sendTerminal("Hello, {$answer}!");
    }
}
```

## Running Scripts

Scripts are executed via composer command:

```shell
# Run script
composer script myscript

# Run script with method
composer script myscript/methodname

# Run with parameters
composer script myscript/process/param1/param2

# Run with options
composer script myscript --option1 --option2=value
```

## Properties

### Public Properties

| Property | Type | Description |
|----------|------|-------------|
| `$core` | Core7 | Reference to Core7 framework instance |
| `$params` | array | URL path parameters (script/param0/param1/...) |
| `$formParams` | array | Form parameters from GET/POST |
| `$method` | string | HTTP method (GET, POST, etc.) |
| `$error` | bool | Indicates if an error occurred |
| `$errorCode` | string\|int | Error code |
| `$errorMsg` | array | Error messages |
| `$argv` | array | Command-line arguments |
| `$vars` | array | Command-line variables (--var=value) |
| `$cache` | CoreCache | Reference to cache system |

---

## Main Structure

### main()

The `main()` method is the entry point of your script.

```php
function main()
{
    // Your script logic
}
```

### METHOD_xxx()

Methods prefixed with `METHOD_` can be called from the command line:

```php
<?php
class Script extends Scripts2020
{
    function main()
    {
        // Get method from parameters
        $method = $this->params[0] ?? 'default';

        // Call METHOD_{name} function
        if (!$this->useFunction('METHOD_' . $method)) {
            return $this->setErrorFromCodelib('params-error',
                ": Method {$method} not found");
        }
    }

    // composer script myscript
    function METHOD_default()
    {
        $this->sendTerminal("Default method");
    }

    // composer script myscript/hello
    function METHOD_hello()
    {
        $this->sendTerminal("Hello World!");
    }

    // composer script myscript/process
    function METHOD_process()
    {
        $this->sendTerminal("Processing...");
        // Your processing logic
    }
}
```

---

## Terminal Output Methods

### sendTerminal()

```php
function sendTerminal(...$args): true
```

Sends output to the terminal.

**Parameters:**
- `...$args` (mixed): Multiple arguments to output

**Example:**
```php
// Simple message
$this->sendTerminal("Processing complete");

// Multiple lines
$this->sendTerminal("Line 1", "Line 2", "Line 3");

// Arrays and objects
$this->sendTerminal("Users:", $usersArray);

// Formatted output
$this->sendTerminal("=== Report ===");
$this->sendTerminal("Total users: " . count($users));
$this->sendTerminal("Active: {$active}");
$this->sendTerminal("Inactive: {$inactive}");
```

---

## User Input Methods

### prompt()

```php
function prompt($title, $default = null, $cache_var = null): string|null
```

Prompts the user for input.

**Parameters:**
- `$title` (string): Prompt message
- `$default` (string): Default value
- `$cache_var` (string): Cache variable name (remembers last input)

**Returns:** User input or default value

**Example:**
```php
// Simple prompt
$name = $this->prompt("Enter your name: ");

// With default value
$email = $this->prompt("Enter email: ", "user@example.com");

// With cache (remembers last input)
$apiKey = $this->prompt("Enter API key: ", null, 'api_key');
// Next time it runs, the last API key will be the default

// Configuration prompts
$dbHost = $this->prompt("Database host: ", "localhost", 'db_host');
$dbName = $this->prompt("Database name: ", "mydb", 'db_name');
$dbUser = $this->prompt("Database user: ", "root", 'db_user');
```

### promptOptions()

```php
function promptOptions($title, $options, $default = null, $cache_var = null): string
```

Prompts the user to select from a list of options.

**Parameters:**
- `$title` (string): Prompt message
- `$options` (array): Available options
- `$default` (mixed): Default option
- `$cache_var` (string): Cache variable name

**Returns:** Selected option

**Example:**
```php
// Select environment
$env = $this->promptOptions(
    "Select environment:",
    ['development', 'staging', 'production'],
    'development',
    'environment'
);

// Select action
$action = $this->promptOptions(
    "What do you want to do?",
    ['create', 'update', 'delete', 'list'],
    'list'
);

$this->sendTerminal("You selected: {$action}");
```

---

## Parameter Methods

### hasOption()

```php
function hasOption($option): bool
```

Checks if a command-line option is present.

**Example:**
```shell
# Run with options
composer script myscript --verbose --force
```

```php
// Check options
if($this->hasOption('verbose')) {
    $this->sendTerminal("Verbose mode enabled");
}

if($this->hasOption('force')) {
    $this->sendTerminal("Force mode enabled");
}
```

### getOptionVar()

```php
function getOptionVar($option): string|null
```

Gets the value of a command-line option variable.

**Example:**
```shell
# Run with option variables
composer script myscript --limit=100 --output=csv
```

```php
// Get option values
$limit = $this->getOptionVar('limit') ?? 10;
$output = $this->getOptionVar('output') ?? 'json';

$this->sendTerminal("Limit: {$limit}");
$this->sendTerminal("Output format: {$output}");
```

---

## Cache Methods

### getCacheVar()

```php
function getCacheVar($var): mixed
```

Gets a value from script cache.

**Example:**
```php
$lastRun = $this->getCacheVar('last_run_timestamp');

if($lastRun) {
    $this->sendTerminal("Last run: " . date('Y-m-d H:i:s', $lastRun));
}
```

### setCacheVar()

```php
function setCacheVar($var, $value): void
```

Sets a value in script cache.

**Example:**
```php
// Save last run timestamp
$this->setCacheVar('last_run_timestamp', time());

// Save processed count
$this->setCacheVar('processed_count', $count);

// Save configuration
$this->setCacheVar('config', $config);
```

### readCache()

```php
function readCache(): void
```

Loads the script cache into `$this->cache_data`.

### cleanCache()

```php
function cleanCache(): void
```

Clears all script cache data.

**Example:**
```php
// Clean cache
$this->cleanCache();
$this->sendTerminal("Cache cleared");
```

---

## Error Handling

### setError()

```php
function setError($error, int $returnStatus = 400, $returnCode = null): void
```

Sets an error.

**Example:**
```php
if(!file_exists($file)) {
    $this->setError("File not found: {$file}", 404);
    return;
}
```

### addError()

```php
function addError($error): void
```

Adds an error message.

**Example:**
```php
$this->addError("Failed to connect to database");
$this->addError("Invalid configuration");

if($this->error) {
    $this->sendTerminal("Errors:");
    $this->sendTerminal($this->errorMsg);
}
```

---

## Complete Examples

### Data Processing Script

```php
<?php
/**
 * Data Processing Script
 */
class Script extends Scripts2020
{
    function main()
    {
        $method = $this->params[0] ?? 'default';

        if (!$this->useFunction('METHOD_' . $method)) {
            return $this->setError("Method {$method} not found");
        }
    }

    // composer script process
    function METHOD_default()
    {
        $this->sendTerminal("=== Data Processor ===");
        $this->sendTerminal("Available methods:");
        $this->sendTerminal("  - composer script process/import");
        $this->sendTerminal("  - composer script process/export");
        $this->sendTerminal("  - composer script process/clean");
    }

    // composer script process/import --source=file.csv
    function METHOD_import()
    {
        $this->sendTerminal("=== Import Data ===");

        // Get options
        $source = $this->getOptionVar('source');
        $verbose = $this->hasOption('verbose');

        if(!$source) {
            $source = $this->prompt("Source file: ", "data.csv", 'import_source');
        }

        if(!file_exists($source)) {
            $this->setError("Source file not found: {$source}");
            return;
        }

        $this->sendTerminal("Importing from: {$source}");

        // Process file
        $data = array_map('str_getcsv', file($source));
        $headers = array_shift($data);
        $count = 0;

        foreach($data as $row) {
            $record = array_combine($headers, $row);

            // Insert into database
            $ds = $this->core->loadClass('DataStore', ['ImportedData']);
            $ds->createEntity($record);

            $count++;

            if($verbose) {
                $this->sendTerminal("Imported: {$record['id']}");
            }
        }

        $this->sendTerminal("Import complete: {$count} records");
        $this->setCacheVar('last_import_count', $count);
        $this->setCacheVar('last_import_time', time());
    }

    // composer script process/export --format=csv --limit=100
    function METHOD_export()
    {
        $this->sendTerminal("=== Export Data ===");

        // Get options
        $format = $this->getOptionVar('format') ?? 'csv';
        $limit = (int)($this->getOptionVar('limit') ?? 1000);

        $this->sendTerminal("Format: {$format}");
        $this->sendTerminal("Limit: {$limit}");

        // Fetch data
        $ds = $this->core->loadClass('DataStore', ['Users']);
        $ds->setLimit($limit);
        $users = $ds->fetchAll();

        if($format === 'csv') {
            $filename = 'export_' . date('Y-m-d_His') . '.csv';
            $fp = fopen($filename, 'w');

            // Headers
            fputcsv($fp, ['ID', 'Name', 'Email', 'Created']);

            // Data
            foreach($users as $user) {
                fputcsv($fp, [
                    $user['KeyId'],
                    $user['name'],
                    $user['email'],
                    $user['created_at']
                ]);
            }

            fclose($fp);
            $this->sendTerminal("Exported to: {$filename}");
        }
    }

    // composer script process/clean --confirm
    function METHOD_clean()
    {
        $this->sendTerminal("=== Clean Old Data ===");

        if(!$this->hasOption('confirm')) {
            $confirm = $this->prompt("This will delete old data. Continue? (yes/no): ", "no");

            if($confirm !== 'yes') {
                $this->sendTerminal("Cancelled");
                return;
            }
        }

        // Delete old records
        $ds = $this->core->loadClass('DataStore', ['Logs']);
        $cutoff = date('Y-m-d', strtotime('-30 days'));

        $deleted = $ds->delete(['created_at' => ['<' => $cutoff]]);

        $this->sendTerminal("Cleaned records older than: {$cutoff}");
        $this->sendTerminal("Deleted: {$deleted} records");
    }
}
```

### Database Migration Script

```php
<?php
/**
 * Database Migration Script
 */
class Script extends Scripts2020
{
    function main()
    {
        $method = $this->params[0] ?? 'status';

        if (!$this->useFunction('METHOD_' . $method)) {
            return $this->setError("Method {$method} not found");
        }
    }

    // composer script migrate/status
    function METHOD_status()
    {
        $this->sendTerminal("=== Migration Status ===");

        $lastMigration = $this->getCacheVar('last_migration');

        if($lastMigration) {
            $this->sendTerminal("Last migration: {$lastMigration}");
            $this->sendTerminal("Date: " . date('Y-m-d H:i:s',
                $this->getCacheVar('last_migration_time')));
        } else {
            $this->sendTerminal("No migrations run yet");
        }
    }

    // composer script migrate/up
    function METHOD_up()
    {
        $this->sendTerminal("=== Running Migrations ===");

        $migrations = ['001_create_users', '002_create_orders', '003_add_indexes'];

        $lastMigration = $this->getCacheVar('last_migration') ?? '';

        foreach($migrations as $migration) {
            if($migration <= $lastMigration) {
                $this->sendTerminal("[SKIP] {$migration}");
                continue;
            }

            $this->sendTerminal("[RUN]  {$migration}");

            // Run migration
            $method = 'migrate_' . $migration;
            if(method_exists($this, $method)) {
                $this->$method();
                $this->sendTerminal("[OK]   {$migration}");

                $this->setCacheVar('last_migration', $migration);
                $this->setCacheVar('last_migration_time', time());
            } else {
                $this->sendTerminal("[ERR]  Method {$method} not found");
            }
        }

        $this->sendTerminal("Migrations complete");
    }

    private function migrate_001_create_users()
    {
        $sql = $this->core->loadClass('CloudSQL');
        $sql->command("
            CREATE TABLE IF NOT EXISTS users (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(255),
                email VARCHAR(255) UNIQUE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");
    }

    private function migrate_002_create_orders()
    {
        $sql = $this->core->loadClass('CloudSQL');
        $sql->command("
            CREATE TABLE IF NOT EXISTS orders (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT,
                total DECIMAL(10, 2),
                status VARCHAR(50),
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id)
            )
        ");
    }

    private function migrate_003_add_indexes()
    {
        $sql = $this->core->loadClass('CloudSQL');
        $sql->command("CREATE INDEX idx_users_email ON users(email)");
        $sql->command("CREATE INDEX idx_orders_user ON orders(user_id)");
        $sql->command("CREATE INDEX idx_orders_status ON orders(status)");
    }
}
```

### Scheduled Task Script

```php
<?php
/**
 * Scheduled Tasks Script
 */
class Script extends Scripts2020
{
    function main()
    {
        $this->sendTerminal("=== Scheduled Tasks ===");
        $this->sendTerminal("Started: " . date('Y-m-d H:i:s'));

        // Daily cleanup
        $this->cleanOldLogs();

        // Send daily report
        $this->sendDailyReport();

        // Sync external data
        $this->syncExternalData();

        $this->sendTerminal("Completed: " . date('Y-m-d H:i:s'));
    }

    private function cleanOldLogs()
    {
        $this->sendTerminal("Cleaning old logs...");

        $ds = $this->core->loadClass('DataStore', ['Logs']);
        $cutoff = date('Y-m-d', strtotime('-7 days'));

        $count = $ds->delete(['created_at' => ['<' => $cutoff]]);

        $this->sendTerminal("Deleted {$count} old log entries");
    }

    private function sendDailyReport()
    {
        $this->sendTerminal("Generating daily report...");

        // Get statistics
        $bq = $this->core->loadClass('DataBQ');
        $stats = $bq->query('Daily Stats', "
            SELECT
                COUNT(*) as total_orders,
                SUM(total) as revenue
            FROM `project.dataset.orders`
            WHERE DATE(created_at) = CURRENT_DATE()
        ");

        // Send email
        $email = $this->core->loadClass('Email');
        $email->send([
            'to' => 'admin@example.com',
            'subject' => 'Daily Report - ' . date('Y-m-d'),
            'body' => "Orders: {$stats[0]['total_orders']}\nRevenue: $" . number_format($stats[0]['revenue'], 2)
        ]);

        $this->sendTerminal("Daily report sent");
    }

    private function syncExternalData()
    {
        $this->sendTerminal("Syncing external data...");

        // Fetch from external API
        $response = $this->core->request->get('https://api.example.com/data');
        $data = json_decode($response, true);

        // Update local database
        $sql = $this->core->loadClass('CloudSQL');

        foreach($data as $item) {
            $sql->command("
                INSERT INTO external_data (id, data, updated_at)
                VALUES (?, ?, NOW())
                ON DUPLICATE KEY UPDATE data = ?, updated_at = NOW()
            ", [$item['id'], json_encode($item), json_encode($item)]);
        }

        $this->sendTerminal("Synced " . count($data) . " items");
    }
}
```

---

## Best Practices

### 1. Use Clear Output

```php
$this->sendTerminal("=== Section Title ===");
$this->sendTerminal("Processing...");
$this->sendTerminal("Complete");
```

### 2. Save State in Cache

```php
// Save progress
$this->setCacheVar('processed_items', $count);

// Resume from last state
$lastProcessed = $this->getCacheVar('processed_items') ?? 0;
```

### 3. Use Options for Flexibility

```php
$dryRun = $this->hasOption('dry-run');
$verbose = $this->hasOption('verbose');
$limit = $this->getOptionVar('limit') ?? 100;
```

### 4. Provide Interactive Prompts

```php
$apiKey = $this->prompt("Enter API key: ", null, 'api_key');
$env = $this->promptOptions("Environment:", ['dev', 'prod'], 'dev');
```

### 5. Handle Errors Gracefully

```php
if(!$data) {
    $this->setError("Failed to fetch data");
    return;
}
```

---

## See Also

- [Getting Started Guide](../guides/getting-started.md)
- [Script Development Guide](../guides/script-development.md)
- [Core7 Class](Core7.md)
- [RESTful Class](RESTful.md)
