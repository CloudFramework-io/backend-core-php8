# DataMongoDB Class

## Overview

The `DataMongoDB` class provides integration with MongoDB for NoSQL document storage. It offers methods for connecting to MongoDB, performing CRUD operations, and querying collections.

## Loading the Class

```php
$mongo = $this->core->loadClass('DataMongoDB');
```

## Properties

| Property | Type | Description |
|----------|------|-------------|
| `$core` | Core7 | Reference to Core7 instance |
| `$error` | bool | Error flag |
| `$errorMsg` | array | Error messages |
| `$connection` | MongoDB\Client | MongoDB client connection |
| `$db` | MongoDB\Database | Current database |
| `$collection` | MongoDB\Collection | Current collection |

---

## Connection Methods

### connect()

```php
public function connect($uri, $dbName): bool
```

Connects to MongoDB.

**Parameters:**
- `$uri` (string): MongoDB connection URI
- `$dbName` (string): Database name

**Returns:** `true` on success

**Example:**
```php
$mongo = $this->core->loadClass('DataMongoDB');
$connected = $mongo->connect('mongodb://localhost:27017', 'myapp');

if(!$connected) {
    // Handle connection error
}
```

---

### selectCollection()

```php
public function selectCollection($collectionName): void
```

Selects a collection to work with.

**Example:**
```php
$mongo->selectCollection('users');
```

---

## CRUD Operations

### insert()

```php
public function insert($document): string|false
```

Inserts a document into the collection.

**Parameters:**
- `$document` (array): Document to insert

**Returns:** Inserted document ID, or `false` on error

**Example:**
```php
$mongo->selectCollection('users');
$userId = $mongo->insert([
    'name' => 'John Doe',
    'email' => 'john@example.com',
    'created_at' => new MongoDB\BSON\UTCDateTime()
]);
```

---

### find()

```php
public function find($filter = [], $options = []): array
```

Finds documents matching filter.

**Parameters:**
- `$filter` (array): Query filter
- `$options` (array): Query options (limit, sort, projection)

**Returns:** Array of documents

**Example:**
```php
// Find all active users
$users = $mongo->find(['status' => 'active']);

// Find with options
$users = $mongo->find(
    ['age' => ['$gte' => 18]],
    ['limit' => 10, 'sort' => ['name' => 1]]
);

// Find with projection
$users = $mongo->find(
    ['status' => 'active'],
    ['projection' => ['name' => 1, 'email' => 1]]
);
```

---

### findOne()

```php
public function findOne($filter = [], $options = []): array|null
```

Finds a single document.

**Example:**
```php
$user = $mongo->findOne(['email' => 'john@example.com']);
```

---

### update()

```php
public function update($filter, $update, $options = []): int
```

Updates documents matching filter.

**Parameters:**
- `$filter` (array): Query filter
- `$update` (array): Update operations
- `$options` (array): Update options

**Returns:** Number of modified documents

**Example:**
```php
// Update single document
$mongo->update(
    ['email' => 'john@example.com'],
    ['$set' => ['status' => 'verified']]
);

// Update multiple
$mongo->update(
    ['status' => 'pending'],
    ['$set' => ['status' => 'active']],
    ['multi' => true]
);
```

---

### delete()

```php
public function delete($filter): int
```

Deletes documents matching filter.

**Returns:** Number of deleted documents

**Example:**
```php
// Delete single document
$deleted = $mongo->delete(['email' => 'john@example.com']);

// Delete multiple
$deleted = $mongo->delete(['status' => 'inactive']);
```

---

### count()

```php
public function count($filter = []): int
```

Counts documents matching filter.

**Example:**
```php
$activeUsers = $mongo->count(['status' => 'active']);
```

---

## Common Usage Patterns

### User Management

```php
class API extends RESTful
{
    private $mongo;

    function main()
    {
        $this->mongo = $this->core->loadClass('DataMongoDB');
        $this->mongo->connect('mongodb://localhost:27017', 'myapp');
        $this->mongo->selectCollection('users');

        if(!$this->useFunction('ENDPOINT_' . ($this->params[0] ?? 'list'))) {
            return $this->setErrorFromCodelib('params-error', 'Endpoint not found');
        }
    }

    public function ENDPOINT_create()
    {
        if(!$this->checkMethod('POST')) return;

        $userId = $this->mongo->insert([
            'name' => $this->formParams['name'],
            'email' => $this->formParams['email'],
            'status' => 'active',
            'created_at' => new MongoDB\BSON\UTCDateTime()
        ]);

        $this->addReturnData(['user_id' => $userId]);
    }

    public function ENDPOINT_list()
    {
        $users = $this->mongo->find(
            ['status' => 'active'],
            ['limit' => 50, 'sort' => ['created_at' => -1]]
        );

        $this->addReturnData($users);
    }

    public function ENDPOINT_get()
    {
        $userId = $this->params[1] ?? null;

        $user = $this->mongo->findOne(['_id' => new MongoDB\BSON\ObjectId($userId)]);

        if($user) {
            $this->addReturnData($user);
        } else {
            return $this->setErrorFromCodelib('not-found', 'User not found');
        }
    }

    public function ENDPOINT_update()
    {
        if(!$this->checkMethod('PUT')) return;

        $userId = $this->params[1] ?? null;

        $this->mongo->update(
            ['_id' => new MongoDB\BSON\ObjectId($userId)],
            ['$set' => [
                'name' => $this->formParams['name'],
                'email' => $this->formParams['email'],
                'updated_at' => new MongoDB\BSON\UTCDateTime()
            ]]
        );

        $this->addReturnData(['status' => 'updated']);
    }

    public function ENDPOINT_delete()
    {
        if(!$this->checkMethod('DELETE')) return;

        $userId = $this->params[1] ?? null;

        $this->mongo->delete(['_id' => new MongoDB\BSON\ObjectId($userId)]);

        $this->addReturnData(['status' => 'deleted']);
    }
}
```

### Search and Filter

```php
// Complex queries
$users = $mongo->find([
    'age' => ['$gte' => 18, '$lte' => 65],
    'status' => 'active',
    '$or' => [
        ['city' => 'New York'],
        ['city' => 'Los Angeles']
    ]
]);

// Text search
$results = $mongo->find([
    '$text' => ['$search' => 'developer']
]);

// Array contains
$users = $mongo->find([
    'tags' => ['$in' => ['premium', 'vip']]
]);
```

### Aggregation

```php
$pipeline = [
    ['$match' => ['status' => 'active']],
    ['$group' => [
        '_id' => '$city',
        'count' => ['$sum' => 1],
        'avg_age' => ['$avg' => '$age']
    ]],
    ['$sort' => ['count' => -1]]
];

$results = $mongo->aggregate($pipeline);
```

---

## MongoDB Operators

### Comparison

- `$eq` - Equal
- `$ne` - Not equal
- `$gt` - Greater than
- `$gte` - Greater than or equal
- `$lt` - Less than
- `$lte` - Less than or equal
- `$in` - In array
- `$nin` - Not in array

### Logical

- `$and` - Logical AND
- `$or` - Logical OR
- `$not` - Logical NOT
- `$nor` - Logical NOR

### Update

- `$set` - Set field value
- `$unset` - Remove field
- `$inc` - Increment value
- `$push` - Add to array
- `$pull` - Remove from array

---

## See Also

- [DataStore Class Reference](DataStore.md)
- [Core7 Class Reference](Core7.md)
- [API Development Guide](../guides/api-development.md)
