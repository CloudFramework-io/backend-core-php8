# CloudSQL Class

## Overview

The `CloudSQL` class provides a comprehensive interface to Google Cloud SQL (MySQL/PostgreSQL), allowing you to execute queries, manage data, and work with relational databases. It supports both direct connections and Cloud SQL proxy connections.

## Requirements

- Google Cloud SQL instance (MySQL or PostgreSQL)
- Service account with Cloud SQL Client role
- Configuration in `config.json` or direct connection parameters

## Basic Usage

```php
// Load CloudSQL class
$sql = $this->core->loadClass('CloudSQL', [
    '/cloudsql/project:region:instance',  // host (Cloud SQL socket)
    'root',                                // username
    'password',                            // password
    'database_name',                       // database
    '3306',                                // port
    '',                                    // socket
    'utf8mb4'                              // charset
]);

// Execute query
$users = $sql->command("SELECT * FROM users WHERE age > ? AND status = ?", [25, 'active']);

// Check for errors
if($sql->error()) {
    print_r($sql->getError());
}
```

## Constructor

```php
function __construct(Core7 &$core, $h = '', $u = '', $p = '', $db = '', $port = '3306', $socket = '', $charset = '')
```

**Parameters:**
- `$h` (string): Host (IP, domain, or Cloud SQL socket path)
- `$u` (string): Username
- `$p` (string): Password
- `$db` (string): Database name
- `$port` (string): Port (default: '3306')
- `$socket` (string): Socket path (optional)
- `$charset` (string): Character set (optional, e.g., 'utf8mb4')

**Example:**
```php
// Direct connection
$sql = $this->core->loadClass('CloudSQL', [
    '10.0.0.1',
    'myuser',
    'mypassword',
    'mydatabase',
    '3306'
]);

// Cloud SQL Unix socket (recommended for App Engine)
$sql = $this->core->loadClass('CloudSQL', [
    '',                                      // empty host
    'root',
    'password',
    'mydb',
    '3306',
    '/cloudsql/project:region:instance'     // socket
]);

// Load from config.json
// If parameters are empty, it loads from:
// - dbServer, dbUser, dbPassword, dbName, dbSocket, dbPort, dbCharset
$sql = $this->core->loadClass('CloudSQL');
```

---

## Connection Methods

### connect()

```php
function connect($h = '', $u = '', $p = '', $db = '', $port = "3306", $socket = '', $charset = ''): bool
```

Establishes a connection to the database.

**Parameters:** Same as constructor

**Returns:** `true` on success, `false` on error

**Example:**
```php
$sql = $this->core->loadClass('CloudSQL');

// Connect manually
$sql->connect(
    '127.0.0.1',
    'user',
    'pass',
    'mydb',
    '3306'
);

if($sql->error()) {
    echo "Connection failed";
}
```

---

### close()

```php
function close(): void
```

Closes the database connection.

**Example:**
```php
$sql->close();
```

---

## Query Execution Methods

### command()

```php
function command($query, $params = []): array|false
```

Executes a SQL query with optional parameter binding.

**Parameters:**
- `$query` (string): SQL query (use `?` for parameter placeholders)
- `$params` (array): Values to bind to placeholders

**Returns:** Array of results for SELECT, `true` for other queries, `false` on error

**Example:**
```php
$sql = $this->core->loadClass('CloudSQL');

// SELECT query
$users = $sql->command("SELECT * FROM users WHERE age > ? AND status = ?", [25, 'active']);

// INSERT query
$sql->command("INSERT INTO users (name, email, age) VALUES (?, ?, ?)", [
    'John Doe',
    'john@example.com',
    30
]);

// UPDATE query
$sql->command("UPDATE users SET status = ? WHERE id = ?", ['active', 123]);

// DELETE query
$sql->command("DELETE FROM users WHERE id = ?", [123]);

// Multiple parameters
$orders = $sql->command("
    SELECT * FROM orders
    WHERE user_id = ?
    AND created_at >= ?
    AND status IN (?, ?)
    ORDER BY created_at DESC
", [123, '2024-01-01', 'completed', 'shipped']);

// Check results
if($sql->error()) {
    print_r($sql->getError());
} else {
    foreach($users as $user) {
        echo $user['name'] . "\n";
    }
}
```

---

### query()

```php
function query($query, $params = []): array|false
```

Alias for `command()` method.

---

## Configuration Methods

### setConf()

```php
function setConf($var, $value): void
```

Sets a configuration variable.

**Parameters:**
- `$var` (string): Configuration key
  - `dbServer`: Database host
  - `dbUser`: Database username
  - `dbPassword`: Database password
  - `dbName`: Database name
  - `dbSocket`: Socket path
  - `dbPort`: Port number
  - `dbCharset`: Character set
- `$value` (string): Configuration value

**Example:**
```php
$sql = $this->core->loadClass('CloudSQL');

$sql->setConf('dbServer', '127.0.0.1');
$sql->setConf('dbUser', 'myuser');
$sql->setConf('dbPassword', 'mypass');
$sql->setConf('dbName', 'mydb');
$sql->setConf('dbCharset', 'utf8mb4');

$sql->connect();
```

---

### getConf()

```php
function getConf($var): string
```

Gets a configuration variable.

**Example:**
```php
$host = $sql->getConf('dbServer');
$database = $sql->getConf('dbName');
```

---

### setDB()

```php
function setDB($db): void
```

Changes the active database.

**Example:**
```php
$sql->setDB('another_database');
```

---

## Helper Methods

### getInsertId()

```php
function getInsertId(): int
```

Returns the last inserted ID from an INSERT query.

**Example:**
```php
$sql->command("INSERT INTO users (name, email) VALUES (?, ?)", ['John', 'john@example.com']);

$userId = $sql->getInsertId();
echo "New user ID: {$userId}";
```

---

### getAffectedRows()

```php
function getAffectedRows(): int
```

Returns the number of rows affected by the last query.

**Example:**
```php
$sql->command("UPDATE users SET status = ? WHERE age > ?", ['active', 18]);

$affected = $sql->getAffectedRows();
echo "Updated {$affected} users";
```

---

### getQuery()

```php
function getQuery(): string
```

Returns the last executed query.

**Example:**
```php
$sql->command("SELECT * FROM users");

echo "Last query: " . $sql->getQuery();
```

---

### tableExists()

```php
function tableExists($table): bool
```

Checks if a table exists in the database.

**Parameters:**
- `$table` (string): Table name

**Returns:** `true` if table exists, `false` otherwise

**Example:**
```php
if($sql->tableExists('users')) {
    echo "Users table exists";
} else {
    // Create table
    $sql->command("CREATE TABLE users (...)");
}
```

---

## Error Handling Methods

### error()

```php
function error(): bool
```

Checks if there was an error in the last operation.

**Returns:** `true` if error exists, `false` otherwise

**Example:**
```php
$users = $sql->command("SELECT * FROM users");

if($sql->error()) {
    echo "Query failed";
}
```

---

### getError()

```php
function getError(): array
```

Returns error details.

**Returns:** Array of error messages

**Example:**
```php
if($sql->error()) {
    $errors = $sql->getError();
    foreach($errors as $error) {
        echo "Error: {$error}\n";
    }
}
```

---

### setError()

```php
function setError($err): void
```

Sets an error message.

---

## Pagination Methods

### setLimit()

```php
function setLimit($limit): void
```

Sets the LIMIT for queries.

**Example:**
```php
$sql->setLimit(10);
```

---

### setPage()

```php
function setPage($page): void
```

Sets the page number for pagination.

**Example:**
```php
$sql->setLimit(20);
$sql->setPage(2); // Skip first 20 records
```

---

## Complete Examples

### CRUD Operations

```php
<?php
class API extends RESTful
{
    private $sql;

    function main()
    {
        // Initialize SQL connection
        $this->sql = $this->core->loadClass('CloudSQL', [
            '/cloudsql/my-project:us-central1:myinstance',
            'root',
            'password',
            'mydatabase'
        ]);

        if($this->sql->error()) {
            return $this->setError('Database connection failed', 500);
        }

        // Route to endpoints
        $endpoint = $this->params[0] ?? 'list';
        if(!$this->useFunction('ENDPOINT_' . $endpoint)) {
            return $this->setErrorFromCodelib('not-found');
        }
    }

    // GET /users/list
    public function ENDPOINT_list()
    {
        if(!$this->checkMethod('GET')) return;

        // Pagination
        $page = (int)($this->formParams['page'] ?? 1);
        $limit = (int)($this->formParams['limit'] ?? 10);
        $offset = ($page - 1) * $limit;

        // Get users
        $users = $this->sql->command("
            SELECT id, name, email, created_at
            FROM users
            WHERE active = 1
            ORDER BY created_at DESC
            LIMIT ? OFFSET ?
        ", [$limit, $offset]);

        // Get total count
        $result = $this->sql->command("SELECT COUNT(*) as total FROM users WHERE active = 1");
        $total = $result[0]['total'];

        if($this->sql->error()) {
            return $this->setError('Failed to fetch users', 500);
        }

        $this->addReturnData([
            'users' => $users,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'pages' => ceil($total / $limit)
            ]
        ]);
    }

    // POST /users/create
    public function ENDPOINT_create()
    {
        if(!$this->checkMethod('POST')) return;

        // Validate
        if(!$this->checkMandatoryFormParams(['name', 'email'])) return;

        // Check if email exists
        $existing = $this->sql->command("
            SELECT id FROM users WHERE email = ?
        ", [$this->formParams['email']]);

        if($existing) {
            return $this->setError('Email already exists', 409);
        }

        // Insert user
        $this->sql->command("
            INSERT INTO users (name, email, created_at)
            VALUES (?, ?, NOW())
        ", [
            $this->formParams['name'],
            $this->formParams['email']
        ]);

        if($this->sql->error()) {
            return $this->setError('Failed to create user', 500);
        }

        $userId = $this->sql->getInsertId();

        // Get created user
        $user = $this->sql->command("SELECT * FROM users WHERE id = ?", [$userId]);

        $this->setReturnStatus(201);
        $this->addReturnData($user[0]);
    }

    // PUT /users/update/{id}
    public function ENDPOINT_update()
    {
        if(!$this->checkMethod('PUT')) return;

        $userId = $this->checkMandatoryParam(1, 'User ID required');
        if(!$userId) return;

        // Check if user exists
        $user = $this->sql->command("SELECT id FROM users WHERE id = ?", [$userId]);
        if(!$user) {
            return $this->setError('User not found', 404);
        }

        // Update user
        $this->sql->command("
            UPDATE users
            SET name = ?, email = ?, updated_at = NOW()
            WHERE id = ?
        ", [
            $this->formParams['name'] ?? $user[0]['name'],
            $this->formParams['email'] ?? $user[0]['email'],
            $userId
        ]);

        if($this->sql->error()) {
            return $this->setError('Failed to update user', 500);
        }

        // Get updated user
        $updatedUser = $this->sql->command("SELECT * FROM users WHERE id = ?", [$userId]);

        $this->addReturnData($updatedUser[0]);
    }

    // DELETE /users/delete/{id}
    public function ENDPOINT_delete()
    {
        if(!$this->checkMethod('DELETE')) return;

        $userId = $this->checkMandatoryParam(1, 'User ID required');
        if(!$userId) return;

        // Soft delete
        $this->sql->command("
            UPDATE users
            SET active = 0, deleted_at = NOW()
            WHERE id = ?
        ", [$userId]);

        // Or hard delete
        // $this->sql->command("DELETE FROM users WHERE id = ?", [$userId]);

        if($this->sql->error()) {
            return $this->setError('Failed to delete user', 500);
        }

        $this->setReturnStatus(204);
    }
}
```

### Transaction Example

```php
// Start transaction
$sql->command("START TRANSACTION");

try {
    // Create user
    $sql->command("
        INSERT INTO users (name, email)
        VALUES (?, ?)
    ", ['John Doe', 'john@example.com']);

    $userId = $sql->getInsertId();

    // Create user profile
    $sql->command("
        INSERT INTO user_profiles (user_id, bio, avatar)
        VALUES (?, ?, ?)
    ", [$userId, 'Software Developer', '/avatars/default.jpg']);

    // Commit transaction
    $sql->command("COMMIT");

    echo "User and profile created successfully";

} catch(Exception $e) {
    // Rollback on error
    $sql->command("ROLLBACK");
    echo "Transaction failed: " . $e->getMessage();
}
```

### Advanced Query Example

```php
// Complex JOIN query
$orders = $sql->command("
    SELECT
        o.id,
        o.order_number,
        o.total,
        u.name as customer_name,
        u.email as customer_email,
        COUNT(oi.id) as item_count
    FROM orders o
    JOIN users u ON o.user_id = u.id
    LEFT JOIN order_items oi ON o.id = oi.order_id
    WHERE o.status = ?
    AND o.created_at >= ?
    GROUP BY o.id, u.name, u.email
    HAVING COUNT(oi.id) > ?
    ORDER BY o.created_at DESC
    LIMIT ?
", ['completed', '2024-01-01', 0, 100]);
```

### Connection Pool Pattern

```php
class API extends RESTful
{
    private static $sqlConnection = null;

    function main()
    {
        // Reuse connection across multiple requests
        if(self::$sqlConnection === null) {
            self::$sqlConnection = $this->core->loadClass('CloudSQL', [
                $_ENV['DB_HOST'],
                $_ENV['DB_USER'],
                $_ENV['DB_PASS'],
                $_ENV['DB_NAME']
            ]);

            if(self::$sqlConnection->error()) {
                return $this->setError('Database connection failed', 500);
            }
        }

        $this->sql = self::$sqlConnection;

        // Route to endpoints
        $endpoint = $this->params[0] ?? 'list';
        $this->useFunction('ENDPOINT_' . $endpoint);
    }
}
```

---

## Best Practices

### 1. Use Parameterized Queries

Always use parameterized queries to prevent SQL injection:

```php
// Good
$users = $sql->command("SELECT * FROM users WHERE email = ?", [$email]);

// Bad
$users = $sql->command("SELECT * FROM users WHERE email = '{$email}'");
```

### 2. Handle Errors Properly

```php
$result = $sql->command("SELECT * FROM users");

if($sql->error()) {
    $this->core->logs->add($sql->getError(), 'database', 'error');
    return $this->setError('Database query failed', 500);
}
```

### 3. Close Connections

```php
// Close connection when done (optional, automatically closed on script end)
$sql->close();
```

### 4. Use Transactions for Multiple Operations

```php
$sql->command("START TRANSACTION");

// Multiple operations
$sql->command("INSERT INTO...");
$sql->command("UPDATE...");

if($sql->error()) {
    $sql->command("ROLLBACK");
} else {
    $sql->command("COMMIT");
}
```

### 5. Use Connection Pooling

Reuse connections across multiple API calls for better performance.

---

## Cloud SQL Specific Notes

### Cloud SQL Unix Sockets (App Engine)

```php
$sql = $this->core->loadClass('CloudSQL', [
    '',                                      // empty host
    'root',
    'password',
    'mydb',
    '3306',
    '/cloudsql/project:region:instance'     // Cloud SQL socket
]);
```

### Cloud SQL Proxy

For local development, use Cloud SQL Proxy:

```shell
# Start proxy
./cloud_sql_proxy -instances=project:region:instance=tcp:3306

# Connect normally
$sql = $this->core->loadClass('CloudSQL', [
    '127.0.0.1',
    'root',
    'password',
    'mydb',
    '3306'
]);
```

---

## See Also

- [Getting Started Guide](../guides/getting-started.md)
- [GCP Integration Guide](../guides/gcp-integration.md)
- [DataStore Class](DataStore.md)
- [DataBQ Class](DataBQ.md)
