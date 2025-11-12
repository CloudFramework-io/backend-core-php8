# DataSQL Class

## Overview

The `DataSQL` class provides a model-based interface for querying SQL databases (MySQL, PostgreSQL, etc.) in CloudFramework. It offers a fluent API for building SELECT queries with WHERE conditions, JOINs, GROUP BY, ORDER BY, and pagination.

**Note:** This class is primarily for **reading data** (SELECT queries). For write operations, use the CloudSQL class directly or DataStore for NoSQL operations.

## Loading the Class

```php
$model = [
    'model' => [
        'id' => ['int', 'isKey'],
        'name' => ['varchar(255)'],
        'email' => ['varchar(255)', 'index'],
        'status' => ['int', 'index'],
        'created_at' => ['datetime']
    ]
];

$sql = $this->core->loadClass('DataSQL', ['users', $model]);
```

## Properties

### Public Properties

| Property | Type | Description |
|----------|------|-------------|
| `$version` | string | Class version |
| `$core` | Core7 | Reference to Core7 instance |
| `$error` | bool | Error flag |
| `$errorMsg` | string | Error message |
| `$entity_schema` | array | Table schema definition |
| `$entity_name` | string | Table name |
| `$keys` | array | Primary key fields |
| `$fields` | array | All table fields with types |
| `$mapping` | array | Field mapping for aliases |
| `$limit` | int | Query limit |
| `$page` | int | Current page (for pagination) |
| `$offset` | int | Query offset |
| `$order` | string | ORDER BY clause |
| `$debug` | bool | Debug mode (auto-enabled in development) |
| `$default_time_zone_to_read` | string | Timezone for reading dates (default: 'UTC') |
| `$default_time_zone_to_write` | string | Timezone for writing dates (default: 'UTC') |

---

## Constructor

```php
function __construct(Core7 &$core, array $params)
```

Initializes DataSQL with table schema.

**Parameters:**
- `$core` (Core7): Reference to Core7
- `$params` (array): Array with `[table_name, schema]`

**Schema Structure:**
```php
[
    'model' => [
        'field_name' => ['type', 'attributes']
    ],
    'mapping' => [  // Optional
        'db_field' => 'api_field'
    ]
]
```

**Field Types:**
- `int`, `bigint`, `tinyint`, `smallint`
- `varchar(N)`, `text`, `longtext`
- `float`, `double`, `decimal(N,M)`
- `datetime`, `timestamp`, `date`, `time`
- `json`

**Field Attributes:**
- `isKey` - Primary key field
- `index` - Indexed field
- `allownull` - Allows NULL values
- `readonly` - Read-only field

**Example:**
```php
$model = [
    'model' => [
        'user_id' => ['int', 'isKey'],
        'username' => ['varchar(100)', 'index'],
        'email' => ['varchar(255)', 'index'],
        'password_hash' => ['varchar(255)'],
        'is_active' => ['tinyint', 'index'],
        'metadata' => ['json', 'allownull'],
        'created_at' => ['datetime'],
        'updated_at' => ['timestamp']
    ]
];

$userSQL = $this->core->loadClass('DataSQL', ['users', $model]);
```

---

## Read Methods

### fetchOneByKey()

```php
function fetchOneByKey($key, string $fields = ''): array
```

Fetches a single record by primary key.

**Parameters:**
- `$key` (string\|int): Primary key value
- `$fields` (string): Comma-separated fields to return (optional)

**Returns:** Record array, or empty array if not found

**Example:**
```php
// Fetch user by ID
$user = $sql->fetchOneByKey(123);
// Returns: ['id' => 123, 'name' => 'John', 'email' => 'john@example.com', ...]

// Fetch specific fields only
$user = $sql->fetchOneByKey(123, 'id,name,email');
// Returns: ['id' => 123, 'name' => 'John', 'email' => 'john@example.com']
```

---

### fetchByKeys()

```php
function fetchByKeys($keysWhere, $fields = ''): array
```

Fetches multiple records by primary keys.

**Parameters:**
- `$keysWhere` (array): Array of primary key values
- `$fields` (string): Comma-separated fields to return (optional)

**Returns:** Array of records

**Example:**
```php
// Fetch multiple users by IDs
$users = $sql->fetchByKeys([123, 456, 789]);
// Returns: [
//   ['id' => 123, 'name' => 'John', ...],
//   ['id' => 456, 'name' => 'Jane', ...],
//   ['id' => 789, 'name' => 'Bob', ...]
// ]

// Fetch specific fields
$users = $sql->fetchByKeys([123, 456], 'id,name');
```

---

### fetch()

```php
function fetch($keysWhere = [], $fields = null, $params = []): array
```

Fetches records with WHERE conditions.

**Parameters:**
- `$keysWhere` (array): WHERE conditions `['field' => 'value']`
- `$fields` (string\|array\|null): Fields to return
- `$params` (array): Additional parameters for prepared statements

**Returns:** Array of records

**WHERE Condition Formats:**
```php
// Simple equals
['status' => 1]

// Special values
['deleted_at' => '__null__']        // IS NULL
['deleted_at' => '__notnull__']     // IS NOT NULL
['name' => '__empty__']             // = ''
['name' => '__notempty__']          // != ''

// Multiple conditions (AND)
['status' => 1, 'is_active' => 1]

// Array for IN clause
['status' => [1, 2, 3]]  // status IN (1, 2, 3)
```

**Example:**
```php
// Fetch all active users
$users = $sql->fetch(['status' => 1]);

// Fetch with multiple conditions
$users = $sql->fetch([
    'status' => 1,
    'is_verified' => 1
]);

// Fetch specific fields
$users = $sql->fetch(['status' => 1], 'id,name,email');

// Fetch with IN condition
$users = $sql->fetch(['role' => ['admin', 'editor']]);

// Fetch where field is NULL
$deletedUsers = $sql->fetch(['deleted_at' => '__notnull__']);

// Fetch where field is NOT NULL
$activeUsers = $sql->fetch(['deleted_at' => '__null__']);
```

---

### fetchOne()

```php
function fetchOne($keysWhere = [], $fields = null, $params = []): array
```

Fetches a single record with WHERE conditions.

**Parameters:**
- `$keysWhere` (array): WHERE conditions
- `$fields` (string\|array\|null): Fields to return
- `$params` (array): Additional parameters

**Returns:** Single record array, or empty array if not found

**Example:**
```php
// Fetch user by email
$user = $sql->fetchOne(['email' => 'john@example.com']);

// Fetch with multiple conditions
$user = $sql->fetchOne([
    'email' => 'john@example.com',
    'status' => 1
]);

// Fetch specific fields
$user = $sql->fetchOne(['email' => 'john@example.com'], 'id,name');
```

---

### count()

```php
function count(string $fields_count = '*', array|string $keysWhere = [], $params = []): int
```

Counts records matching WHERE conditions.

**Parameters:**
- `$fields_count` (string): Field to count (default: '*')
- `$keysWhere` (array): WHERE conditions
- `$params` (array): Additional parameters

**Returns:** Count as integer

**Example:**
```php
// Count all users
$totalUsers = $sql->count();

// Count active users
$activeUsers = $sql->count('*', ['status' => 1]);

// Count distinct emails
$uniqueEmails = $sql->count('DISTINCT email');

// Count with complex condition
$verifiedUsers = $sql->count('*', [
    'status' => 1,
    'is_verified' => 1
]);
```

---

### sum()

```php
function sum(string $field, array|string $keysWhere = [], $params = []): float
```

Calculates sum of a field.

**Parameters:**
- `$field` (string): Field to sum
- `$keysWhere` (array): WHERE conditions
- `$params` (array): Additional parameters

**Returns:** Sum as float

**Example:**
```php
// Sum all order totals
$total = $sql->sum('total');

// Sum for specific status
$completedTotal = $sql->sum('total', ['status' => 'completed']);
```

---

### avg()

```php
function avg(string $field = '', array|string $keysWhere = [], $params = []): float
```

Calculates average of a field.

**Parameters:**
- `$field` (string): Field to average
- `$keysWhere` (array): WHERE conditions
- `$params` (array): Additional parameters

**Returns:** Average as float

**Example:**
```php
// Average order amount
$avgAmount = $sql->avg('amount');

// Average rating for product
$avgRating = $sql->avg('rating', ['product_id' => 123]);
```

---

## Query Builder Methods

### setLimit()

```php
function setLimit($limit): void
```

Sets the LIMIT clause.

**Example:**
```php
$sql->setLimit(10);
$users = $sql->fetch(['status' => 1]);
// Returns up to 10 records
```

---

### setPage()

```php
function setPage($page): void
```

Sets the page for pagination (auto-calculates OFFSET).

**Example:**
```php
$sql->setLimit(25);  // 25 per page
$sql->setPage(2);    // Page 2 (records 26-50)
$users = $sql->fetch();
```

---

### setOffset()

```php
function setOffset($offset): void
```

Sets the OFFSET clause directly.

**Example:**
```php
$sql->setLimit(10);
$sql->setOffset(20);  // Skip first 20 records
$users = $sql->fetch();
```

---

### setOrder()

```php
function setOrder($field, $type = 'ASC'): void
```

Sets ORDER BY clause (replaces existing order).

**Parameters:**
- `$field` (string): Field to order by
- `$type` (string): 'ASC' or 'DESC'

**Example:**
```php
// Order by name ascending
$sql->setOrder('name', 'ASC');
$users = $sql->fetch();

// Order by created_at descending
$sql->setOrder('created_at', 'DESC');
$users = $sql->fetch();
```

---

### addOrder()

```php
function addOrder($field, $type = 'ASC'): void
```

Adds to ORDER BY clause (keeps existing order).

**Example:**
```php
// Order by status, then by name
$sql->setOrder('status', 'ASC');
$sql->addOrder('name', 'ASC');
$users = $sql->fetch();
// ORDER BY status ASC, name ASC
```

---

### unsetOrder()

```php
function unsetOrder(): void
```

Clears ORDER BY clause.

---

### setQueryFields()

```php
function setQueryFields($fields): void
```

Defines specific fields to SELECT.

**Example:**
```php
$sql->setQueryFields('id,name,email');
$users = $sql->fetch(['status' => 1]);
// Returns only id, name, email
```

---

### setQueryWhere()

```php
function setQueryWhere($keysWhere): void
```

Sets WHERE conditions for the query.

**Example:**
```php
$sql->setQueryWhere(['status' => 1, 'role' => 'admin']);
$users = $sql->fetch();
```

---

### addQueryWhere()

```php
function addQueryWhere($keysWhere): void
```

Adds to WHERE conditions.

**Example:**
```php
$sql->setQueryWhere(['status' => 1]);
$sql->addQueryWhere(['is_verified' => 1]);
$users = $sql->fetch();
// WHERE status = 1 AND is_verified = 1
```

---

### setExtraWhere()

```php
function setExtraWhere($extraWhere): void
```

Adds raw WHERE clause (advanced).

**Example:**
```php
$sql->setExtraWhere("DATE(created_at) >= '2024-01-01'");
$users = $sql->fetch();
```

---

### setGroupBy()

```php
function setGroupBy($group): void
```

Sets GROUP BY clause.

**Example:**
```php
$sql->setQueryFields('status, COUNT(*) as count');
$sql->setGroupBy('status');
$stats = $sql->fetch();
// Returns: [
//   ['status' => 1, 'count' => 50],
//   ['status' => 0, 'count' => 10]
// ]
```

---

### join()

```php
function join($type, DataSQL &$object, $first_field, $join_field, $extraon = null): void
```

Adds a JOIN clause.

**Parameters:**
- `$type` (string): 'LEFT', 'RIGHT', 'INNER'
- `$object` (DataSQL): DataSQL object to join
- `$first_field` (string): Field from first table
- `$join_field` (string): Field from joined table
- `$extraon` (string\|null): Additional ON conditions

**Example:**
```php
// Join users with orders
$userSQL = $this->core->loadClass('DataSQL', ['users', $userModel]);
$orderSQL = $this->core->loadClass('DataSQL', ['orders', $orderModel]);

$userSQL->join('LEFT', $orderSQL, 'user_id', 'user_id');
$userSQL->setQueryFields('users.*, orders.total');
$results = $userSQL->fetch();
```

---

### reset()

```php
public function reset(): void
```

Resets all query builder settings.

**Example:**
```php
$sql->reset();  // Clear all limits, orders, wheres, etc.
```

---

## Utility Methods

### getFields()

```php
function getFields(): array
```

Returns all field names defined in schema.

**Returns:** Array of field names

**Example:**
```php
$fields = $sql->getFields();
// Returns: ['id', 'name', 'email', 'status', 'created_at']
```

---

## Common Usage Patterns

### Pagination

```php
class API extends RESTful
{
    public function ENDPOINT_users()
    {
        $page = (int)($this->formParams['page'] ?? 1);
        $perPage = 25;

        $sql = $this->core->loadClass('DataSQL', ['users', $model]);
        $sql->setLimit($perPage);
        $sql->setPage($page);
        $sql->setOrder('created_at', 'DESC');

        $users = $sql->fetch(['status' => 1]);
        $total = $sql->count('*', ['status' => 1]);

        $this->addReturnData([
            'users' => $users,
            'pagination' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'total_pages' => ceil($total / $perPage)
            ]
        ]);
    }
}
```

### Search and Filter

```php
public function ENDPOINT_search()
{
    $search = $this->formParams['search'] ?? '';
    $status = $this->formParams['status'] ?? null;

    $sql = $this->core->loadClass('DataSQL', ['users', $model]);

    // Build WHERE
    $where = [];
    if($status !== null) {
        $where['status'] = (int)$status;
    }

    // Add search condition
    if($search) {
        $sql->setExtraWhere("(name LIKE '%{$search}%' OR email LIKE '%{$search}%')");
    }

    $sql->setOrder('name', 'ASC');
    $users = $sql->fetch($where);

    $this->addReturnData($users);
}
```

### Aggregations

```php
public function ENDPOINT_stats()
{
    $sql = $this->core->loadClass('DataSQL', ['orders', $model]);

    // Total orders
    $totalOrders = $sql->count();

    // Total revenue
    $totalRevenue = $sql->sum('total', ['status' => 'completed']);

    // Average order value
    $avgOrderValue = $sql->avg('total', ['status' => 'completed']);

    // Orders by status
    $sql->reset();
    $sql->setQueryFields('status, COUNT(*) as count, SUM(total) as total');
    $sql->setGroupBy('status');
    $byStatus = $sql->fetch();

    $this->addReturnData([
        'total_orders' => $totalOrders,
        'total_revenue' => $totalRevenue,
        'avg_order_value' => $avgOrderValue,
        'by_status' => $byStatus
    ]);
}
```

### Complex Queries with JOINs

```php
public function ENDPOINT_user_orders()
{
    $userId = $this->params[0] ?? null;
    if(!$userId) {
        return $this->setErrorFromCodelib('params-error', 'User ID required');
    }

    // Create SQL objects
    $userSQL = $this->core->loadClass('DataSQL', ['users', $userModel]);
    $orderSQL = $this->core->loadClass('DataSQL', ['orders', $orderModel]);

    // Join users with orders
    $userSQL->join('LEFT', $orderSQL, 'id', 'user_id');
    $userSQL->setQueryFields('users.*, orders.id as order_id, orders.total, orders.created_at as order_date');
    $userSQL->setQueryWhere(['users.id' => $userId]);
    $userSQL->setOrder('orders.created_at', 'DESC');

    $results = $userSQL->fetch();

    $this->addReturnData($results);
}
```

### Date Range Queries

```php
public function ENDPOINT_recent()
{
    $days = (int)($this->formParams['days'] ?? 7);
    $date = date('Y-m-d H:i:s', strtotime("-{$days} days"));

    $sql = $this->core->loadClass('DataSQL', ['users', $model]);
    $sql->setExtraWhere("created_at >= '{$date}'");
    $sql->setOrder('created_at', 'DESC');

    $recentUsers = $sql->fetch();

    $this->addReturnData([
        'days' => $days,
        'count' => count($recentUsers),
        'users' => $recentUsers
    ]);
}
```

---

## Performance Tips

### 1. Select Only Needed Fields

```php
// Bad: Fetches all fields
$users = $sql->fetch();

// Good: Fetch only what you need
$users = $sql->fetch([], 'id,name,email');
```

### 2. Use Indexes

```php
// Define indexes in schema
$model = [
    'model' => [
        'id' => ['int', 'isKey'],
        'email' => ['varchar(255)', 'index'],  // Indexed
        'status' => ['int', 'index'],         // Indexed
    ]
];
```

### 3. Use Limits

```php
// Always use limits for lists
$sql->setLimit(100);
$users = $sql->fetch();
```

### 4. Reset Between Queries

```php
// Reset to avoid carrying over settings
$sql->reset();
$sql->setLimit(10);
$users = $sql->fetch();
```

---

## Debug Mode

Debug mode logs all SQL queries:

```php
$sql->debug = true;

$users = $sql->fetch(['status' => 1]);
// Logs: SELECT * FROM users WHERE status = 1
```

Auto-enabled in development environment.

---

## Error Handling

```php
$sql = $this->core->loadClass('DataSQL', ['users', $model]);
$users = $sql->fetch(['status' => 1]);

if($sql->error) {
    $this->core->logs->add('SQL Error: ' . $sql->errorMsg);
    return $this->setErrorFromCodelib('database-error', 'Failed to fetch users');
}
```

---

## See Also

- [CloudSQL Class Reference](CloudSQL.md) - For write operations (INSERT, UPDATE, DELETE)
- [DataStore Class Reference](DataStore.md) - NoSQL alternative with write operations
- [API Development Guide](../guides/api-development.md)
- [Database Examples](../examples/api-examples.md#database)
