# DataBQ Class

## Overview

The `DataBQ` class provides a comprehensive interface to Google BigQuery, a serverless data warehouse for analytics. It handles query execution, data insertion, table management, and includes support for CloudFramework models.

## Requirements

- Google BigQuery API enabled
- Service account with BigQuery Data Editor role
- Configuration in `config.json`:
  ```json
  {
    "core.bigquery.on": true,
    "core.gcp.bigquery.project_id": "your-project-id"
  }
  ```

## Basic Usage

```php
// Load DataBQ class
$bq = $this->core->loadClass('DataBQ', ['my_dataset']);

// Run query
$results = $bq->query('Get users', "
    SELECT * FROM `project.my_dataset.users`
    WHERE age > 25
    LIMIT 10
");

// Insert data
$bq = $this->core->loadClass('DataBQ', ['my_dataset', 'events']);
$bq->insert([
    'user_id' => 123,
    'event' => 'page_view',
    'timestamp' => new DateTime()
]);

// Check for errors
if($bq->error) {
    echo "Error: " . implode(', ', $bq->errorMsg);
}
```

## Constructor

```php
function __construct(Core7 &$core, $params)
```

**Parameters:**
- `$params[0]` (string): Dataset name or `dataset.table` format
- `$params[1]` (array, optional): CloudFramework schema
- `$params[2]` (array, optional): Options
  - `projectId` (string): GCP project ID
  - `keyFile` (array): Service account credentials

**Example:**
```php
// Dataset only
$bq = $this->core->loadClass('DataBQ', ['my_dataset']);

// Dataset and table
$bq = $this->core->loadClass('DataBQ', ['my_dataset.users']);

// With schema
$schema = [
    'model' => [
        'user_id' => ['ID', 'KEY'],
        'name' => ['Name', 'string'],
        'email' => ['Email', 'string'],
        'age' => ['Age', 'integer']
    ]
];
$bq = $this->core->loadClass('DataBQ', ['my_dataset.users', $schema]);

// With custom project
$bq = $this->core->loadClass('DataBQ', [
    'my_dataset',
    null,
    ['projectId' => 'other-project']
]);
```

---

## Dataset Methods

### getDataSets()

```php
public function getDataSets(): array|false
```

Returns all datasets in the project.

**Returns:** Array of dataset names, or `false` on error

**Example:**
```php
$bq = $this->core->loadClass('DataBQ', []);

$datasets = $bq->getDataSets();
// Returns: ['dataset1', 'dataset2', 'analytics', 'logs']

foreach($datasets as $dataset) {
    echo "Dataset: {$dataset}\n";
}
```

---

### getDataSetInfo()

```php
public function getDataSetInfo($dataset_name = null): array|false
```

Gets detailed information about a dataset.

**Parameters:**
- `$dataset_name` (string, optional): Dataset name (uses current dataset if not provided)

**Returns:** Dataset information array, or `false` on error

**Example:**
```php
$bq = $this->core->loadClass('DataBQ', ['my_dataset']);

$info = $bq->getDataSetInfo();

echo $info['id'];              // Dataset ID
echo $info['location'];        // Dataset location (e.g., 'US')
echo $info['creationTime'];    // Creation timestamp
echo $info['description'];     // Dataset description
```

---

### getDataSetTables()

```php
public function getDataSetTables($dataset_name = null): array|false
```

Lists all tables in a dataset.

**Parameters:**
- `$dataset_name` (string, optional): Dataset name

**Returns:** Array of table names, or `false` on error

**Example:**
```php
$bq = $this->core->loadClass('DataBQ', ['my_dataset']);

$tables = $bq->getDataSetTables();
// Returns: ['users', 'orders', 'products', 'events']

foreach($tables as $table) {
    echo "Table: {$table}\n";
}
```

---

### getDataSetTableInfo()

```php
public function getDataSetTableInfo($dataset_name = null, $table_name = null): array|false
```

Gets detailed information about a table including schema.

**Parameters:**
- `$dataset_name` (string, optional): Dataset name
- `$table_name` (string, optional): Table name

**Returns:** Table information array, or `false` on error

**Example:**
```php
$bq = $this->core->loadClass('DataBQ', ['my_dataset.users']);

$info = $bq->getDataSetTableInfo();

echo $info['id'];              // Table ID
echo $info['numRows'];         // Number of rows
echo $info['numBytes'];        // Size in bytes
echo $info['creationTime'];    // Creation timestamp
echo $info['schema']['fields']; // Table schema
```

---

### createDataSetTableInfo()

```php
public function createDataSetTableInfo(array $fields, $dataset_name = null, $table_name = null): bool
```

Creates a new table with the specified schema.

**Parameters:**
- `$fields` (array): Array of field definitions
- `$dataset_name` (string, optional): Dataset name
- `$table_name` (string, optional): Table name

**Returns:** `true` on success, `false` on error

**Example:**
```php
$bq = $this->core->loadClass('DataBQ', ['my_dataset']);

// Define schema
$schema = [
    ['name' => 'user_id', 'type' => 'INTEGER', 'mode' => 'REQUIRED'],
    ['name' => 'email', 'type' => 'STRING', 'mode' => 'REQUIRED'],
    ['name' => 'name', 'type' => 'STRING', 'mode' => 'NULLABLE'],
    ['name' => 'age', 'type' => 'INTEGER', 'mode' => 'NULLABLE'],
    ['name' => 'created_at', 'type' => 'TIMESTAMP', 'mode' => 'REQUIRED'],
    ['name' => 'metadata', 'type' => 'JSON', 'mode' => 'NULLABLE']
];

// Create table
$bq->createDataSetTableInfo($schema, 'my_dataset', 'users');

if($bq->error) {
    echo "Failed to create table";
}
```

**Field Types:**
- `STRING`: Text data
- `INTEGER`: Whole numbers
- `FLOAT`: Decimal numbers
- `BOOLEAN`: True/false
- `TIMESTAMP`: Date and time
- `DATE`: Date only
- `TIME`: Time only
- `DATETIME`: Date and time
- `JSON`: JSON data
- `ARRAY`: Arrays
- `STRUCT`: Nested structures

**Field Modes:**
- `REQUIRED`: Field must have a value
- `NULLABLE`: Field can be null
- `REPEATED`: Field can have multiple values (array)

---

## Query Methods

### query()

```php
public function query($title, $_q, $params = []): array|false
```

Executes a BigQuery SQL query.

**Parameters:**
- `$title` (string): Query title for logging
- `$_q` (string): SQL query
- `$params` (array, optional): Query parameters for parameterized queries

**Returns:** Array of results, or `false` on error

**Example:**
```php
$bq = $this->core->loadClass('DataBQ', []);

// Simple query
$results = $bq->query('Get active users', "
    SELECT user_id, name, email
    FROM `my-project.my_dataset.users`
    WHERE active = true
    ORDER BY created_at DESC
    LIMIT 100
");

// Parameterized query
$results = $bq->query('Get users by age', "
    SELECT * FROM `my-project.my_dataset.users`
    WHERE age > @min_age AND country = @country
", [
    'min_age' => 25,
    'country' => 'US'
]);

// Analytics query
$results = $bq->query('Monthly revenue', "
    SELECT
        FORMAT_TIMESTAMP('%Y-%m', created_at) as month,
        COUNT(*) as order_count,
        SUM(total) as revenue
    FROM `my-project.my_dataset.orders`
    WHERE created_at >= TIMESTAMP_SUB(CURRENT_TIMESTAMP(), INTERVAL 12 MONTH)
    GROUP BY month
    ORDER BY month DESC
");

if($bq->error) {
    echo "Query failed: " . implode(', ', $bq->errorMsg);
} else {
    foreach($results as $row) {
        print_r($row);
    }
}
```

---

### dbQuery()

```php
public function dbQuery($title, $_q, $params = []): array|false
```

Alias for `query()` method.

---

## Data Insertion Methods

### insert()

```php
public function insert($data, $upsert = false): bool
```

Inserts one or more rows into a table.

**Parameters:**
- `$data` (array): Data to insert (single row or array of rows)
- `$upsert` (bool): If true, update if record exists (default: false)

**Returns:** `true` on success, `false` on error

**Example:**
```php
$bq = $this->core->loadClass('DataBQ', ['my_dataset', 'events']);

// Insert single row
$bq->insert([
    'user_id' => 123,
    'event' => 'page_view',
    'page' => '/home',
    'timestamp' => new DateTime()
]);

// Insert multiple rows
$bq->insert([
    ['user_id' => 123, 'event' => 'click', 'timestamp' => new DateTime()],
    ['user_id' => 456, 'event' => 'purchase', 'timestamp' => new DateTime()],
    ['user_id' => 789, 'event' => 'signup', 'timestamp' => new DateTime()]
]);

if($bq->error) {
    echo "Insert failed: " . implode(', ', $bq->errorMsg);
}
```

---

### upsert()

```php
public function upsert($data): bool
```

Inserts or updates data (requires a key field in the schema).

**Parameters:**
- `$data` (array): Data to upsert

**Returns:** `true` on success, `false` on error

**Example:**
```php
$schema = [
    'model' => [
        'user_id' => ['ID', 'KEY'],
        'name' => ['Name', 'string'],
        'email' => ['Email', 'string']
    ]
];

$bq = $this->core->loadClass('DataBQ', ['my_dataset.users', $schema]);

// Upsert (insert if new, update if exists)
$bq->upsert([
    'user_id' => 123,
    'name' => 'John Doe',
    'email' => 'john@example.com'
]);
```

---

### insertWithStreamingBuffer()

```php
public function insertWithStreamingBuffer(array $data): bool
```

Inserts data using streaming buffer for real-time analytics.

**Parameters:**
- `$data` (array): Array of rows to insert

**Returns:** `true` on success, `false` on error

**Example:**
```php
$bq = $this->core->loadClass('DataBQ', ['analytics', 'events']);

// Stream events in real-time
$bq->insertWithStreamingBuffer([
    ['event' => 'click', 'user_id' => 123, 'timestamp' => time()],
    ['event' => 'view', 'user_id' => 456, 'timestamp' => time()]
]);
```

---

## Data Retrieval Methods

### fetch()

```php
function fetch($keysWhere = [], $fields = null, $groupBy = null, $params = []): array|false
```

Fetches multiple rows from a table.

**Parameters:**
- `$keysWhere` (array): WHERE conditions
- `$fields` (array|string, optional): Fields to select
- `$groupBy` (string, optional): GROUP BY clause
- `$params` (array, optional): Additional query parameters

**Returns:** Array of rows, or `false` on error

**Example:**
```php
$bq = $this->core->loadClass('DataBQ', ['my_dataset.users']);

// Fetch all users
$users = $bq->fetch();

// Fetch with conditions
$users = $bq->fetch(['active' => true, 'age' => ['>' => 25]]);

// Fetch specific fields
$users = $bq->fetch(['active' => true], ['name', 'email']);

// Fetch with GROUP BY
$stats = $bq->fetch([], ['country', 'COUNT(*) as count'], 'country');
```

---

### fetchOne()

```php
function fetchOne($keysWhere = [], $fields = null, $groupBy = null, $params = []): array|false
```

Fetches a single row from a table.

**Parameters:** Same as `fetch()`

**Returns:** Single row array, or `false` if not found

**Example:**
```php
$bq = $this->core->loadClass('DataBQ', ['my_dataset.users']);

// Fetch one user
$user = $bq->fetchOne(['email' => 'john@example.com']);

if($user) {
    echo $user['name'];
    echo $user['email'];
}
```

---

### fetchOneByKey()

```php
function fetchOneByKey($key, $fields = null): array|false
```

Fetches a row by its key value.

**Parameters:**
- `$key` (mixed): Key value
- `$fields` (array|string, optional): Fields to select

**Returns:** Row array, or `false` if not found

**Example:**
```php
$schema = [
    'model' => [
        'user_id' => ['ID', 'KEY'],
        'name' => ['Name', 'string'],
        'email' => ['Email', 'string']
    ]
];

$bq = $this->core->loadClass('DataBQ', ['my_dataset.users', $schema]);

// Fetch by key
$user = $bq->fetchOneByKey(123);

// Fetch specific fields
$user = $bq->fetchOneByKey(123, ['name', 'email']);
```

---

### fetchByKeys()

```php
function fetchByKeys($keysWhere, $fields = null): array|false
```

Fetches multiple rows by key values.

**Parameters:**
- `$keysWhere` (array): Array of key values
- `$fields` (array|string, optional): Fields to select

**Returns:** Array of rows, or `false` on error

**Example:**
```php
$bq = $this->core->loadClass('DataBQ', ['my_dataset.users', $schema]);

// Fetch multiple users by IDs
$users = $bq->fetchByKeys([123, 456, 789]);

foreach($users as $user) {
    echo $user['name'] . "\n";
}
```

---

## Data Update Methods

### update()

```php
public function update($data, $record_readed = []): bool
```

Updates an existing row (requires key field).

**Parameters:**
- `$data` (array): Updated data
- `$record_readed` (array, optional): Original record for comparison

**Returns:** `true` on success, `false` on error

**Example:**
```php
$bq = $this->core->loadClass('DataBQ', ['my_dataset.users', $schema]);

// Update user
$bq->update([
    'user_id' => 123,
    'name' => 'Jane Doe',
    'email' => 'jane@example.com'
]);
```

---

### delete()

```php
public function delete($data): bool
```

Deletes a row (requires key field).

**Parameters:**
- `$data` (array): Data with key field

**Returns:** `true` on success, `false` on error

**Example:**
```php
$bq = $this->core->loadClass('DataBQ', ['my_dataset.users', $schema]);

// Delete user
$bq->delete(['user_id' => 123]);
```

---

### softDelete()

```php
public function softDelete($key): bool
```

Soft deletes a row by setting a deleted flag.

**Parameters:**
- `$key` (mixed): Key value

**Returns:** `true` on success, `false` on error

**Example:**
```php
$bq = $this->core->loadClass('DataBQ', ['my_dataset.users', $schema]);

// Soft delete (sets deleted_at timestamp)
$bq->softDelete(123);
```

---

## Query Building Methods

### setLimit()

```php
function setLimit($limit): void
```

Sets the LIMIT for queries.

**Example:**
```php
$bq->setLimit(10);
$users = $bq->fetch();
```

---

### setPage()

```php
function setPage($page): void
```

Sets the page number for pagination.

**Example:**
```php
$bq->setLimit(20);
$bq->setPage(2); // Skip first 20 records
$users = $bq->fetch();
```

---

### setOffset()

```php
function setOffset($offset): void
```

Sets the OFFSET for queries.

**Example:**
```php
$bq->setOffset(50);
$bq->setLimit(10);
$users = $bq->fetch(); // Records 51-60
```

---

### setOrder()

```php
function setOrder($field, $type = 'ASC'): void
```

Sets the ORDER BY clause (replaces existing order).

**Example:**
```php
$bq->setOrder('created_at', 'DESC');
$users = $bq->fetch();
```

---

### addOrder()

```php
function addOrder($field, $type = 'ASC'): void
```

Adds an ORDER BY clause (keeps existing orders).

**Example:**
```php
$bq->setOrder('country', 'ASC');
$bq->addOrder('created_at', 'DESC');
$users = $bq->fetch();
// ORDER BY country ASC, created_at DESC
```

---

### setQueryFields()

```php
function setQueryFields($fields): void
```

Sets specific fields to select.

**Example:**
```php
$bq->setQueryFields(['name', 'email', 'created_at']);
$users = $bq->fetch();
```

---

### setQueryWhere()

```php
function setQueryWhere($keysWhere): void
```

Sets WHERE conditions (replaces existing).

**Example:**
```php
$bq->setQueryWhere(['active' => true, 'age' => ['>' => 25]]);
$users = $bq->fetch();
```

---

### addQueryWhere()

```php
function addQueryWhere($keysWhere): void
```

Adds WHERE conditions (keeps existing).

**Example:**
```php
$bq->setQueryWhere(['active' => true]);
$bq->addQueryWhere(['country' => 'US']);
$users = $bq->fetch();
// WHERE active = true AND country = 'US'
```

---

### setExtraWhere()

```php
function setExtraWhere($extraWhere): void
```

Adds raw SQL to WHERE clause.

**Example:**
```php
$bq->setExtraWhere("AND created_at >= TIMESTAMP_SUB(CURRENT_TIMESTAMP(), INTERVAL 30 DAY)");
$users = $bq->fetch();
```

---

### reset()

```php
public function reset(): void
```

Resets all query parameters.

**Example:**
```php
$bq->reset();
```

---

## Complete Examples

### Analytics Dashboard

```php
<?php
class API extends RESTful
{
    // GET /analytics/dashboard
    public function ENDPOINT_dashboard()
    {
        $bq = $this->core->loadClass('DataBQ', []);

        // Get daily active users
        $daily_users = $bq->query('Daily Active Users', "
            SELECT
                DATE(timestamp) as date,
                COUNT(DISTINCT user_id) as active_users
            FROM `{$this->core->gc_project_id}.analytics.events`
            WHERE timestamp >= TIMESTAMP_SUB(CURRENT_TIMESTAMP(), INTERVAL 30 DAY)
            GROUP BY date
            ORDER BY date DESC
        ");

        // Get top pages
        $top_pages = $bq->query('Top Pages', "
            SELECT
                page,
                COUNT(*) as views
            FROM `{$this->core->gc_project_id}.analytics.page_views`
            WHERE timestamp >= TIMESTAMP_SUB(CURRENT_TIMESTAMP(), INTERVAL 7 DAY)
            GROUP BY page
            ORDER BY views DESC
            LIMIT 10
        ");

        // Get revenue by country
        $revenue = $bq->query('Revenue by Country', "
            SELECT
                country,
                COUNT(*) as orders,
                SUM(amount) as revenue
            FROM `{$this->core->gc_project_id}.sales.orders`
            WHERE created_at >= TIMESTAMP_SUB(CURRENT_TIMESTAMP(), INTERVAL 30 DAY)
            GROUP BY country
            ORDER BY revenue DESC
        ");

        if($bq->error) {
            return $this->setError('Analytics query failed', 500);
        }

        $this->addReturnData([
            'daily_active_users' => $daily_users,
            'top_pages' => $top_pages,
            'revenue_by_country' => $revenue
        ]);
    }
}
```

### Event Tracking

```php
// POST /events/track
public function ENDPOINT_track()
{
    if(!$this->checkMethod('POST')) return;

    // Validate event data
    if(!$this->checkMandatoryFormParams(['event', 'user_id'])) return;

    $bq = $this->core->loadClass('DataBQ', ['analytics', 'events']);

    // Insert event
    $bq->insert([
        'event' => $this->formParams['event'],
        'user_id' => $this->formParams['user_id'],
        'page' => $this->formParams['page'] ?? null,
        'data' => json_encode($this->formParams['data'] ?? []),
        'timestamp' => new DateTime(),
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? ''
    ]);

    if($bq->error) {
        $this->core->logs->add($bq->errorMsg, 'bigquery', 'error');
        return $this->setError('Failed to track event', 500);
    }

    $this->setReturnStatus(201);
    $this->addReturnData(['tracked' => true]);
}
```

### Data Export

```php
// GET /export/users
public function ENDPOINT_users()
{
    $bq = $this->core->loadClass('DataBQ', ['my_dataset']);

    // Export users to CSV
    $users = $bq->query('Export Users', "
        SELECT
            user_id,
            name,
            email,
            created_at,
            last_login
        FROM `{$this->core->gc_project_id}.my_dataset.users`
        WHERE active = true
        ORDER BY created_at DESC
    ");

    if($bq->error) {
        return $this->setError('Export failed', 500);
    }

    // Generate CSV
    $csv = "User ID,Name,Email,Created At,Last Login\n";
    foreach($users as $user) {
        $csv .= implode(',', [
            $user['user_id'],
            '"' . $user['name'] . '"',
            $user['email'],
            $user['created_at'],
            $user['last_login'] ?? ''
        ]) . "\n";
    }

    // Send CSV file
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="users-export.csv"');
    echo $csv;
    exit;
}
```

---

## See Also

- [Getting Started Guide](../guides/getting-started.md)
- [GCP Integration Guide](../guides/gcp-integration.md)
- [DataStore Class](DataStore.md)
- [Buckets Class](Buckets.md)
