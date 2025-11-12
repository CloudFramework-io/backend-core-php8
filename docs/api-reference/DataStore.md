# DataStore Class

## Overview

The `DataStore` class provides a comprehensive interface to Google Cloud Datastore, a NoSQL document database. It handles entity creation, querying, updating, deletion, and includes support for CloudFramework models, caching, and transactions.

## Requirements

- Google Cloud Datastore API enabled
- Service account with Datastore permissions
- Configuration in `config.json`:
  ```json
  {
    "core.datastore.on": true,
    "core.gcp.datastore.project_id": "your-project-id",
    "core.gcp.datastore.transport": "rest"
  }
  ```

## Basic Usage

```php
// Load DataStore class
$ds = $this->core->loadClass('DataStore', [
    'Users',                    // Entity name
    'default',                  // Namespace (optional)
    $schema                     // CloudFramework model schema (optional)
]);

// Create entity
$user = $ds->createEntity([
    'name' => 'John Doe',
    'email' => 'john@example.com',
    'age' => 30
]);

// Fetch entities
$users = $ds->fetchAll('*', ['age' => 30]);

// Update entity
$user['name'] = 'Jane Doe';
$updated = $ds->createEntity($user); // Updates if KeyId/KeyName exists

// Delete entity
$ds->delete(['KeyId' => $user['KeyId']]);
```

## Constructor

```php
function __construct(Core7 &$core, array $params)
```

**Parameters:**
- `$params[0]` or `$params['entity_name']` (string, required): Entity name
- `$params[1]` or `$params['namespace']` (string, optional): Namespace (default: 'default')
- `$params[2]` or `$params['schema']` (array, optional): CloudFramework model schema
- `$params[3]` or `$params['options']` (array, optional): Additional options

**Options:**
- `projectId` (string): GCP project ID
- `namespaceId` (string): Datastore namespace
- `transport` (string): 'grpc' or 'rest' (default: 'rest')
- `keyFile` (array): Service account credentials

**Example:**
```php
$ds = $this->core->loadClass('DataStore', [
    'entity_name' => 'Users',
    'namespace' => 'production',
    'schema' => [
        'props' => [
            'name' => ['Name', 'string'],
            'email' => ['Email', 'string', 'unique'],
            'age' => ['Age', 'integer']
        ]
    ]
]);
```

---

## Service Account Management

### updateServiceAccount()

```php
public function updateServiceAccount(array $serviceAccount): bool
```

Updates the Datastore connection with different service account credentials.

**Parameters:**
- `$serviceAccount` (array): Service account configuration
  - `private_key` (string, required): Service account private key
  - `project_id` (string, required): GCP project ID
  - `namespace` (string, optional): Datastore namespace

**Returns:** `true` on success, `false` on error

**Example:**
```php
$ds->updateServiceAccount([
    'private_key' => '-----BEGIN PRIVATE KEY-----...',
    'project_id' => 'my-other-project',
    'client_email' => 'service@my-project.iam.gserviceaccount.com',
    'namespace' => 'production'
]);
```

---

## Create Operations

### createEntity()

```php
function createEntity($data, $transaction = false)
```

Creates or updates a single entity in Datastore.

**Parameters:**
- `$data` (array): Entity data
- `$transaction` (bool): Use transaction (default: false)

**Returns:** Created/updated entity array or `false` on error

**Example:**
```php
// Create new entity
$user = $ds->createEntity([
    'name' => 'John Doe',
    'email' => 'john@example.com',
    'age' => 30,
    'active' => true
]);

// Create with specific KeyName
$user = $ds->createEntity([
    'KeyName' => 'user-john-doe',
    'name' => 'John Doe',
    'email' => 'john@example.com'
]);

// Update existing entity
$user['age'] = 31;
$updated = $ds->createEntity($user);
```

---

### createEntities()

```php
function createEntities($data, $transaction = false)
```

Creates or updates multiple entities in a single operation.

**Parameters:**
- `$data` (array): Array of entity data arrays
- `$transaction` (bool): Use transaction (default: false)

**Returns:** Array of created/updated entities or `false` on error

**Example:**
```php
$users = $ds->createEntities([
    ['name' => 'John Doe', 'email' => 'john@example.com'],
    ['name' => 'Jane Smith', 'email' => 'jane@example.com'],
    ['name' => 'Bob Wilson', 'email' => 'bob@example.com']
]);

// With transactions
$users = $ds->createEntities($data, true);
```

---

## Read Operations

### fetchOne()

```php
function fetchOne($fields = '*', $where = null, $order = null)
```

Fetches a single entity matching the criteria.

**Parameters:**
- `$fields` (string|array): Fields to retrieve ('*' for all)
- `$where` (array): Filter conditions
- `$order` (array): Sort order

**Returns:** Entity array or `null` if not found

**Example:**
```php
// Fetch one user
$user = $ds->fetchOne('*', ['email' => 'john@example.com']);

// Fetch specific fields
$user = $ds->fetchOne(['name', 'email'], ['age' => 30]);

// With ordering
$user = $ds->fetchOne('*', null, ['created_at' => 'DESC']);
```

---

### fetchAll()

```php
function fetchAll($fields = '*', $where = null, $order = null)
```

Fetches all entities matching the criteria.

**Parameters:**
- `$fields` (string|array): Fields to retrieve ('*' for all, '__key__' for keys only)
- `$where` (array): Filter conditions
- `$order` (array): Sort order

**Returns:** Array of entities or empty array

**Example:**
```php
// Fetch all users
$users = $ds->fetchAll();

// Filter by age
$users = $ds->fetchAll('*', ['age' => 30]);

// Multiple conditions
$users = $ds->fetchAll('*', [
    'age' => 30,
    'active' => true
]);

// With ordering
$users = $ds->fetchAll('*', null, ['name' => 'ASC']);

// Fetch only keys
$userKeys = $ds->fetchAll('__key__', ['age' => 30]);
```

---

### fetchLimit()

```php
function fetchLimit($fields = '*', $where = null, $order = null, $limit = null)
```

Fetches entities with pagination support.

**Parameters:**
- `$fields` (string|array): Fields to retrieve
- `$where` (array): Filter conditions
- `$order` (array): Sort order
- `$limit` (int): Maximum number of results

**Returns:** Array of entities

**Example:**
```php
// Fetch first 10 users
$users = $ds->fetchLimit('*', null, ['name' => 'ASC'], 10);

// Pagination with cursor
$users = $ds->fetchLimit('*', null, ['created_at' => 'DESC'], 20);
$nextCursor = $ds->cursor; // Save cursor for next page

// Next page
$ds->cursor = $nextCursor;
$moreUsers = $ds->fetchLimit('*', null, ['created_at' => 'DESC'], 20);
```

---

### fetchByKeys()

```php
function fetchByKeys($keys)
```

Fetches entities by their Datastore keys.

**Parameters:**
- `$keys` (array): Array of key identifiers

**Returns:** Array of entities

**Example:**
```php
// Fetch by KeyIds
$users = $ds->fetchByKeys([123, 456, 789]);

// Fetch by KeyNames
$users = $ds->fetchByKeys(['user-john', 'user-jane']);
```

---

### fetchOneByKey()

```php
function fetchOneByKey($key)
```

Fetches a single entity by its key.

**Parameters:**
- `$key` (int|string): KeyId or KeyName

**Returns:** Entity array or `null`

**Example:**
```php
// Fetch by KeyId
$user = $ds->fetchOneByKey(123456);

// Fetch by KeyName
$user = $ds->fetchOneByKey('user-john-doe');
```

---

## Aggregation Operations

### count()

```php
function count(array $where = []): int|false
```

Counts entities matching the criteria.

**Parameters:**
- `$where` (array): Filter conditions

**Returns:** Count or `false` on error

**Example:**
```php
// Count all users
$total = $ds->count();

// Count active users
$activeCount = $ds->count(['active' => true]);

// Count by age
$count30 = $ds->count(['age' => 30]);
```

---

### sum()

```php
function sum(string $field, array $where = []): float|int|false
```

Calculates the sum of a numeric field.

**Parameters:**
- `$field` (string): Field name to sum
- `$where` (array): Filter conditions

**Returns:** Sum value or `false` on error

**Example:**
```php
// Sum of all ages
$totalAge = $ds->sum('age');

// Sum with filter
$totalSalary = $ds->sum('salary', ['department' => 'Engineering']);
```

---

### avg()

```php
function avg(string $field, array $where = []): float|false
```

Calculates the average of a numeric field.

**Parameters:**
- `$field` (string): Field name to average
- `$where` (array): Filter conditions

**Returns:** Average value or `false` on error

**Example:**
```php
// Average age
$avgAge = $ds->avg('age');

// Average with filter
$avgSalary = $ds->avg('salary', ['department' => 'Sales']);
```

---

## Delete Operations

### delete()

```php
function delete($where): bool
```

Deletes entities matching the criteria.

**Parameters:**
- `$where` (array): Filter conditions (must include KeyId or KeyName)

**Returns:** `true` on success, `false` on error

**Example:**
```php
// Delete by KeyId
$ds->delete(['KeyId' => 123456]);

// Delete by KeyName
$ds->delete(['KeyName' => 'user-john-doe']);

// Delete by field (fetches and deletes matching entities)
$ds->delete(['email' => 'john@example.com']);
```

---

### deleteByKeys()

```php
function deleteByKeys($keys): bool
```

Deletes entities by their keys.

**Parameters:**
- `$keys` (array): Array of KeyIds or KeyNames

**Returns:** `true` on success, `false` on error

**Example:**
```php
// Delete multiple entities
$ds->deleteByKeys([123, 456, 789]);

// Delete by KeyNames
$ds->deleteByKeys(['user-john', 'user-jane', 'user-bob']);
```

---

## Cache Operations

### activateCache()

```php
function activateCache($activate = true, $secretKey = '', $secretIV = '')
```

Activates caching for query results.

**Parameters:**
- `$activate` (bool): Enable/disable cache
- `$secretKey` (string): Encryption key (optional)
- `$secretIV` (string): Encryption IV (optional)

**Example:**
```php
// Enable cache
$ds->activateCache(true);

// Enable with encryption
$ds->activateCache(true, 'my-secret-key', 'my-secret-iv');

// Disable cache
$ds->deactivateCache();
```

---

### resetCache()

```php
function resetCache()
```

Clears all cached query results for this entity.

**Example:**
```php
$ds->resetCache();
```

---

## Schema and Validation

### getEntityTemplate()

```php
function getEntityTemplate($transform_keys = true): array
```

Gets an empty template based on the schema.

**Parameters:**
- `$transform_keys` (bool): Transform keys to schema names

**Returns:** Template array

**Example:**
```php
$template = $ds->getEntityTemplate();
// Returns: ['name' => '', 'email' => '', 'age' => 0, ...]
```

---

### getCheckedRecordWithMapData()

```php
function getCheckedRecordWithMapData($data, $all = true, &$dictionaries = []): array|false
```

Validates data against the schema.

**Parameters:**
- `$data` (array): Data to validate
- `$all` (bool): Validate all fields or only present ones
- `$dictionaries` (array): Reference to store dictionary data

**Returns:** Validated data or `false` on error

**Example:**
```php
$validatedData = $ds->getCheckedRecordWithMapData([
    'name' => 'John Doe',
    'email' => 'john@example.com',
    'age' => 30
], true);
```

---

## Where Clause Operators

The `$where` parameter in fetch methods supports various operators:

### Basic Equality
```php
$users = $ds->fetchAll('*', ['age' => 30]);
```

### Comparison Operators
```php
// Greater than
$users = $ds->fetchAll('*', ['age' => ['>' => 30]]);

// Greater than or equal
$users = $ds->fetchAll('*', ['age' => ['>=' => 18]]);

// Less than
$users = $ds->fetchAll('*', ['age' => ['<' => 65]]);

// Less than or equal
$users = $ds->fetchAll('*', ['age' => ['<=' => 100]]);

// Not equal
$users = $ds->fetchAll('*', ['status' => ['!=' => 'deleted']]);
```

### Array Operators
```php
// IN operator
$users = $ds->fetchAll('*', ['age' => ['in' => [25, 30, 35]]]);

// NOT IN operator
$users = $ds->fetchAll('*', ['status' => ['!in' => ['deleted', 'banned']]]);
```

### Multiple Conditions
```php
$users = $ds->fetchAll('*', [
    'age' => ['>=' => 18],
    'active' => true,
    'role' => ['in' => ['admin', 'editor']]
]);
```

---

## Order Clause

```php
// Ascending order
$users = $ds->fetchAll('*', null, ['name' => 'ASC']);

// Descending order
$users = $ds->fetchAll('*', null, ['created_at' => 'DESC']);

// Multiple fields
$users = $ds->fetchAll('*', null, [
    'department' => 'ASC',
    'name' => 'ASC'
]);
```

---

## CloudFramework Model Schema

Define a schema to add validation and type checking:

```php
$schema = [
    'props' => [
        // Basic field: [DisplayName, Type]
        'name' => ['Name', 'string'],
        'email' => ['Email', 'string', 'unique'],
        'age' => ['Age', 'integer'],
        'active' => ['Active', 'boolean'],
        'created_at' => ['Created At', 'datetime'],
        'tags' => ['Tags', 'list'],

        // Field with validation
        'status' => ['Status', 'string', 'values:active,inactive,pending'],

        // Required field
        'email' => ['Email', 'string', 'required|unique'],

        // Key fields
        'user_id' => ['User ID', 'key'],
        'username' => ['Username', 'keyname']
    ]
];

$ds = $this->core->loadClass('DataStore', [
    'Users',
    'default',
    $schema
]);
```

### Supported Field Types

- `string`: Text data
- `integer`: Whole numbers
- `float`: Decimal numbers
- `boolean`: True/false
- `datetime`: Date and time
- `date`: Date only
- `list`: Array of values
- `json`: JSON data
- `key`: Entity KeyId
- `keyname`: Entity KeyName

### Validation Rules

- `required`: Field must have a value
- `unique`: Value must be unique across all entities
- `values:a,b,c`: Value must be one of the specified options
- `min:n`: Minimum value/length
- `max:n`: Maximum value/length

---

## Error Handling

```php
// Check for errors
if($ds->error) {
    echo "Error: " . implode(', ', $ds->errorMsg);
    echo "Status: " . $ds->errorCode;
}

// Create with error handling
$user = $ds->createEntity($data);
if(!$user) {
    // Handle error
    $this->core->logs->add($ds->errorMsg, 'datastore', 'error');
    $this->setError('Failed to create user', $ds->errorCode);
    return;
}
```

---

## Complete Example

```php
<?php
class API extends RESTful
{
    function main()
    {
        // Define schema
        $schema = [
            'props' => [
                'name' => ['Name', 'string', 'required'],
                'email' => ['Email', 'string', 'required|unique'],
                'age' => ['Age', 'integer'],
                'active' => ['Active', 'boolean'],
                'created_at' => ['Created At', 'datetime']
            ]
        ];

        // Load DataStore
        $ds = $this->core->loadClass('DataStore', [
            'Users',
            'production',
            $schema
        ]);

        // Enable caching
        $ds->activateCache(true);

        // Route to endpoints
        $endpoint = $this->params[0] ?? 'list';
        if(!$this->useFunction('ENDPOINT_' . $endpoint)) {
            return $this->setErrorFromCodelib('not-found');
        }
    }

    // GET /users/list
    public function ENDPOINT_list()
    {
        $ds = $this->core->loadClass('DataStore', 'Users');

        // Pagination
        $page = (int)($this->formParams['page'] ?? 1);
        $limit = (int)($this->formParams['limit'] ?? 10);

        // Filters
        $where = [];
        if(isset($this->formParams['active'])) {
            $where['active'] = ($this->formParams['active'] === 'true');
        }

        // Fetch users
        $users = $ds->fetchLimit('*', $where, ['name' => 'ASC'], $limit);

        // Get total
        $total = $ds->count($where);

        $this->addReturnData([
            'users' => $users,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total
            ]
        ]);
    }

    // POST /users/create
    public function ENDPOINT_create()
    {
        if(!$this->checkMethod('POST')) return;

        // Validate
        if(!$this->checkMandatoryFormParams(['name', 'email'])) return;

        $ds = $this->core->loadClass('DataStore', 'Users');

        // Create user
        $user = $ds->createEntity([
            'name' => $this->formParams['name'],
            'email' => $this->formParams['email'],
            'age' => (int)($this->formParams['age'] ?? 0),
            'active' => true,
            'created_at' => new DateTime()
        ]);

        if(!$user) {
            $this->core->logs->add($ds->errorMsg, 'users', 'error');
            return $this->setError('Failed to create user', $ds->errorCode);
        }

        $this->setReturnStatus(201);
        $this->addReturnData($user);
    }

    // DELETE /users/delete/{id}
    public function ENDPOINT_delete()
    {
        if(!$this->checkMethod('DELETE')) return;

        $userId = $this->checkMandatoryParam(1, 'User ID required');
        if(!$userId) return;

        $ds = $this->core->loadClass('DataStore', 'Users');

        // Delete user
        if(!$ds->delete(['KeyId' => $userId])) {
            return $this->setError('Failed to delete user', 500);
        }

        $this->setReturnStatus(204);
    }
}
```

---

## See Also

- [Getting Started Guide](../guides/getting-started.md)
- [GCP Integration Guide](../guides/gcp-integration.md)
- [Buckets Class](Buckets.md)
- [DataBQ Class](DataBQ.md)
