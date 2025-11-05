# API Development Guide

This guide covers advanced techniques for building robust RESTful APIs with CloudFramework Backend Core.

## Table of Contents

- [API Basics](#api-basics)
- [Routing and Endpoints](#routing-and-endpoints)
- [Request Handling](#request-handling)
- [Response Formatting](#response-formatting)
- [Validation](#validation)
- [Error Handling](#error-handling)
- [Authentication](#authentication)
- [CORS Configuration](#cors-configuration)
- [Best Practices](#best-practices)

## API Basics

### Simple API Structure

Every API extends the `RESTful` base class:

```php
<?php
class API extends RESTful
{
    function main()
    {
        // Your main logic here
        $this->addReturnData('Hello World');
    }
}
```

### API Lifecycle

1. Request received by `runapi.php`
2. `RESTful` class instantiated
3. Request parameters parsed
4. Your `main()` method called
5. Response formatted and sent

## Routing and Endpoints

### URL Structure

URLs are structured as:
```
http://your-domain.com/service/param1/param2/param3
```

- `service`: Maps to the API file or directory
- `param1`, `param2`, etc.: Available in `$this->params[]`

### Single Endpoint API

Create `api/users.php`:

```php
<?php
class API extends RESTful
{
    function main()
    {
        // Handle different HTTP methods
        switch($this->method) {
            case 'GET':
                $this->getUsers();
                break;
            case 'POST':
                $this->createUser();
                break;
            case 'PUT':
                $this->updateUser();
                break;
            case 'DELETE':
                $this->deleteUser();
                break;
            default:
                $this->setErrorFromCodelib('method-error');
        }
    }

    private function getUsers()
    {
        $userId = $this->params[0] ?? null;

        if($userId) {
            // Get specific user
            $user = $this->getUserById($userId);
            if(!$user) {
                return $this->setErrorFromCodelib('not-found');
            }
            $this->addReturnData($user);
        } else {
            // Get all users
            $users = $this->getAllUsers();
            $this->addReturnData(['users' => $users]);
        }
    }

    private function getUserById($id) {
        // Your database logic
        return ['id' => $id, 'name' => 'John Doe'];
    }

    private function getAllUsers() {
        // Your database logic
        return [];
    }
}
```

### Multiple Endpoints API

Create `api/myservice/index.php`:

```php
<?php
class API extends RESTful
{
    function main()
    {
        // Get endpoint from first parameter
        $endpoint = str_replace('-', '_', $this->params[0] ?? 'default');

        // Route to ENDPOINT_{name} method
        if(!$this->useFunction('ENDPOINT_' . $endpoint)) {
            return $this->setErrorFromCodelib('not-found',
                ": Endpoint /{$this->service}/{$endpoint} not found");
        }
    }

    // GET /myservice or /myservice/default
    public function ENDPOINT_default()
    {
        $this->addReturnData([
            'service' => 'My Service',
            'endpoints' => [
                '/myservice/users',
                '/myservice/products',
                '/myservice/orders'
            ]
        ]);
    }

    // /myservice/users
    public function ENDPOINT_users()
    {
        if(!$this->checkMethod('GET,POST')) return;
        // Your logic here
    }

    // /myservice/products
    public function ENDPOINT_products()
    {
        if(!$this->checkMethod('GET')) return;
        // Your logic here
    }
}
```

### Nested Endpoints

For deeply nested routes, use multiple parameters:

```php
// URL: /api/projects/123/tasks/456
public function ENDPOINT_projects()
{
    $projectId = $this->checkMandatoryParam(1, 'Project ID required');
    if(!$projectId) return;

    $action = $this->params[2] ?? null; // 'tasks'
    $taskId = $this->params[3] ?? null;  // '456'

    if($action === 'tasks') {
        if($taskId) {
            $this->getProjectTask($projectId, $taskId);
        } else {
            $this->getProjectTasks($projectId);
        }
    }
}
```

## Request Handling

### HTTP Methods

Restrict allowed methods:

```php
// Only allow GET and POST
if(!$this->checkMethod('GET,POST')) return;

// Only GET
if(!$this->checkMethod('GET')) return;
```

### URL Parameters

Access URL parameters from `$this->params[]`:

```php
// URL: /users/123/profile
$userId = $this->params[0];     // '123'
$section = $this->params[1];    // 'profile'

// With validation
$userId = $this->checkMandatoryParam(0, 'User ID is required');
if(!$userId) return;
```

### Form Parameters (POST/GET Data)

Access form data from `$this->formParams[]`:

```php
// Single parameter
$email = $this->formParams['email'] ?? null;

// Multiple parameters
$name = $this->formParams['name'] ?? null;
$age = $this->formParams['age'] ?? null;

// With validation
$email = $this->checkMandatoryFormParam('email', 'Email is required');
if(!$email) return;
```

### Raw Input (JSON)

Raw POST body is automatically parsed:

```php
// POST body: {"name":"John","age":30}
// Automatically available in:
$name = $this->formParams['name'];  // 'John'
$age = $this->formParams['age'];     // 30

// Access raw input
$rawInput = $this->formParams['_raw_input_'];
```

### Request Headers

```php
// Get specific header
$auth = $this->getHeaderAuthorization();
$contentType = $this->getRequestHeader('Content-Type');
$customHeader = $this->getRequestHeader('X-Custom-Header');

// Check if header exists
if(!$auth) {
    return $this->setErrorFromCodelib('security-error');
}
```

## Response Formatting

### Adding Data

```php
// Simple data
$this->addReturnData('Hello World');

// Array data
$this->addReturnData(['message' => 'Success', 'id' => 123]);

// Merging data
$this->addReturnData(['users' => $users]);
$this->addReturnData(['total' => count($users)]);
// Result: {'users': [...], 'total': 10}
```

### Setting Complete Response

```php
$this->setReturnData([
    'users' => $users,
    'pagination' => [
        'page' => 1,
        'per_page' => 10,
        'total' => 100
    ]
]);
```

### HTTP Status Codes

```php
// Success - 200 (default)
$this->setReturnStatus(200);

// Created - 201
$this->setReturnStatus(201);
$this->setReturnCode('inserted');

// No Content - 204
$this->setReturnStatus(204);

// Bad Request - 400
$this->setReturnStatus(400);

// Unauthorized - 401
$this->setReturnStatus(401);

// Not Found - 404
$this->setReturnStatus(404);

// Server Error - 500
$this->setReturnStatus(500);
```

### Custom Headers

```php
// Add custom headers
$this->addHeader('X-Total-Count', '100');
$this->addHeader('X-Rate-Limit-Remaining', '99');
$this->addHeader('Cache-Control', 'no-cache');
```

### Response Messages

```php
// Success message
$this->setMessage('User created successfully', 'success');

// Info message
$this->setMessage('Processing...', 'info', 'Your request is being processed', 5);

// Warning message
$this->setMessage('Disk space low', 'warning');

// Multiple messages
$this->addMessage('Step 1 completed', 'success');
$this->addMessage('Step 2 completed', 'success');
```

## Validation

### Mandatory Parameters

```php
// Single parameter
$userId = $this->checkMandatoryParam(0, 'User ID is required');
if(!$userId) return;

// Multiple parameters
if(!$this->checkMandatoryFormParams(['name', 'email', 'password'])) {
    return;
}

// With allowed values
$status = $this->checkMandatoryFormParam('status', 'Invalid status',
    ['active', 'inactive', 'pending']);
if(!$status) return;

// With minimum length
$password = $this->checkMandatoryFormParam('password',
    'Password must be at least 8 characters', [], 8);
if(!$password) return;
```

### Value Type Validation

```php
// Email validation
if(!$this->validateValue('email', $email, 'email')) return;

// Integer validation
if(!$this->validateValue('age', $age, 'integer')) return;

// URL validation
if(!$this->validateValue('website', $url, 'url')) return;

// Custom error message
if(!$this->validateValue('email', $email, 'email', '',
    'Please provide a valid email address')) return;
```

### Model-Based Validation

```php
// Define model
$userModel = [
    'name' => ['type' => 'string', 'required' => true, 'min_length' => 2],
    'email' => ['type' => 'email', 'required' => true],
    'age' => ['type' => 'integer', 'min' => 18, 'max' => 120],
    'status' => ['type' => 'string', 'values' => ['active', 'inactive']]
];

// Validate POST data
$data = [];
if(!$this->validatePostData($userModel, 'user-validation', $data)) {
    return;
}

// Use validated data
$this->createUser($data);
```

### Custom Validation

```php
// Custom validation logic
private function validateUsername($username)
{
    if(strlen($username) < 3) {
        $this->setError('Username must be at least 3 characters', 400);
        return false;
    }

    if(!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
        $this->setError('Username can only contain letters, numbers and underscore', 400);
        return false;
    }

    return true;
}

// Use in endpoint
public function ENDPOINT_register()
{
    $username = $this->checkMandatoryFormParam('username', 'Username required');
    if(!$username) return;

    if(!$this->validateUsername($username)) return;

    // Continue with registration
}
```

## Error Handling

### Predefined Error Codes

```php
// Use built-in error codes
$this->setErrorFromCodelib('not-found');
$this->setErrorFromCodelib('security-error');
$this->setErrorFromCodelib('params-error');
$this->setErrorFromCodelib('method-error');

// With extra message
$this->setErrorFromCodelib('not-found', ': User with ID 123 not found');
```

### Custom Error Codes

```php
// Define in constructor or main
function main()
{
    // Add custom error codes
    $this->addCodeLib('insufficient-credits', 'Insufficient credits', 402);
    $this->addCodeLib('quota-exceeded', 'API quota exceeded', 429);
    $this->addCodeLib('invalid-token', 'Invalid authentication token', 401);

    // Use them
    if($credits < $cost) {
        return $this->setErrorFromCodelib('insufficient-credits');
    }
}
```

### Direct Error Setting

```php
// Simple error
$this->setError('Something went wrong', 500);

// Error with code
$this->setError('User not found', 404, 'user-not-found');

// Error array
$this->setError([
    'field' => 'email',
    'message' => 'Email already exists',
    'code' => 'duplicate-email'
], 409);
```

### Try-Catch Error Handling

```php
public function ENDPOINT_process()
{
    try {
        $result = $this->processData();
        $this->addReturnData($result);
    } catch (DatabaseException $e) {
        $this->core->logs->add($e->getMessage(), 'database-error', 'error');
        $this->setErrorFromCodelib('database-error');
    } catch (Exception $e) {
        $this->core->logs->add($e->getMessage(), 'general-error', 'error');
        $this->setError('An unexpected error occurred', 500);
    }
}
```

## Authentication

### Basic Authentication

```php
function main()
{
    // Check basic auth
    if(!$this->core->security->existBasicAuth()) {
        return $this->setErrorFromCodelib('security-error',
            ': Basic authentication required');
    }

    // Get credentials
    $username = $_SERVER['PHP_AUTH_USER'] ?? '';
    $password = $_SERVER['PHP_AUTH_PW'] ?? '';

    // Validate credentials
    if(!$this->validateCredentials($username, $password)) {
        return $this->setErrorFromCodelib('security-error',
            ': Invalid credentials');
    }

    // Continue with endpoint routing
}
```

### Token Authentication

```php
function main()
{
    // Get token from Authorization header
    $authHeader = $this->getHeaderAuthorization();

    if(!$authHeader) {
        return $this->setErrorFromCodelib('security-error',
            ': Authorization header required');
    }

    // Extract token (Bearer token format)
    if(strpos($authHeader, 'Bearer ') === 0) {
        $token = substr($authHeader, 7);
    } else {
        return $this->setErrorFromCodelib('security-error',
            ': Invalid authorization format');
    }

    // Validate token
    if(!$this->validateToken($token)) {
        return $this->setErrorFromCodelib('security-error',
            ': Invalid or expired token');
    }

    // Continue with endpoint routing
}

private function validateToken($token)
{
    // Your token validation logic
    // e.g., check database, verify JWT, etc.
    return true;
}
```

### API Key Authentication

```php
function main()
{
    // Get API key from header
    $apiKey = $this->getRequestHeader('X-API-Key');

    if(!$apiKey) {
        return $this->setErrorFromCodelib('security-error',
            ': API key required');
    }

    // Validate API key
    if(!$this->isValidApiKey($apiKey)) {
        return $this->setErrorFromCodelib('security-error',
            ': Invalid API key');
    }

    // Continue with endpoint routing
}
```

## CORS Configuration

### Enable CORS for All Origins

```php
function main()
{
    // Allow all origins
    $this->sendCorsHeaders('GET,POST,PUT,DELETE');

    // Your endpoint logic
}
```

### Restrict CORS Origins

```php
function main()
{
    // Allow specific origins
    $allowed_origins = 'https://app.example.com,https://admin.example.com';
    $this->sendCorsHeaders('GET,POST,PUT,DELETE', $allowed_origins);

    // Your endpoint logic
}
```

### CORS with Custom Headers

```php
function main()
{
    // Allow custom headers
    $this->sendCorsHeaders('GET,POST,PUT,DELETE', '*', 'X-Custom-Header,X-API-Key');

    // Your endpoint logic
}
```

## Best Practices

### 1. Organize Your APIs

```
api/
├── v1/
│   ├── users/
│   │   └── index.php
│   ├── products/
│   │   └── index.php
│   └── orders/
│       └── index.php
└── v2/
    ├── users/
    │   └── index.php
    └── products/
        └── index.php
```

### 2. Use Proper HTTP Methods

- `GET`: Retrieve data (idempotent, safe)
- `POST`: Create new resources
- `PUT`: Update existing resources (full update)
- `PATCH`: Partial update
- `DELETE`: Remove resources

### 3. Return Appropriate Status Codes

```php
// Success
200 - OK (default)
201 - Created (after POST)
204 - No Content (after DELETE)

// Client Errors
400 - Bad Request (validation errors)
401 - Unauthorized (authentication required)
403 - Forbidden (insufficient permissions)
404 - Not Found
409 - Conflict (duplicate resource)
422 - Unprocessable Entity (semantic errors)

// Server Errors
500 - Internal Server Error
503 - Service Unavailable
```

### 4. Validate All Input

```php
// Always validate
$email = $this->checkMandatoryFormParam('email', 'Email required');
if(!$email) return;

if(!$this->validateValue('email', $email, 'email')) return;

// Sanitize if needed
$name = htmlspecialchars($this->formParams['name']);
```

### 5. Log Important Events

```php
// Log API calls
$this->core->logs->add("User {$userId} accessed data", 'api-access');

// Log errors
$this->core->logs->add("Failed to process payment: {$error}", 'payment', 'error');

// Log debug info (only in development)
if($this->core->is->development()) {
    $this->core->logs->add("Debug: " . json_encode($data), 'debug');
}
```

### 6. Use Consistent Response Format

```php
// Success response
{
    "success": true,
    "status": 200,
    "code": "ok",
    "data": { ... }
}

// Error response
{
    "success": false,
    "status": 400,
    "code": "validation-error",
    "errors": [ ... ]
}
```

### 7. Implement Rate Limiting

```php
function main()
{
    // Check rate limit
    $clientId = $this->getClientIdentifier();
    if($this->isRateLimited($clientId)) {
        return $this->setError('Rate limit exceeded', 429, 'rate-limit');
    }

    // Continue with API logic
}

private function getClientIdentifier()
{
    return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
}

private function isRateLimited($clientId)
{
    $key = "rate_limit:{$clientId}";
    $count = (int)$this->core->cache->get($key);

    if($count >= 100) { // 100 requests per hour
        return true;
    }

    $this->core->cache->set($key, $count + 1, 3600);
    return false;
}
```

### 8. Version Your APIs

```php
// Option 1: URL versioning
// /v1/users
// /v2/users

// Option 2: Header versioning
function main()
{
    $version = $this->getRequestHeader('X-API-Version') ?? '1';

    if($version === '2') {
        $this->handleV2();
    } else {
        $this->handleV1();
    }
}
```

## Complete Example

Here's a complete, production-ready API example:

```php
<?php
/**
 * Products API - v1
 */
class API extends RESTful
{
    function main()
    {
        // Enable CORS
        $this->sendCorsHeaders('GET,POST,PUT,DELETE',
            'https://app.example.com');

        // Authenticate
        if(!$this->authenticate()) return;

        // Rate limiting
        if($this->isRateLimited()) {
            return $this->setError('Rate limit exceeded', 429);
        }

        // Route to endpoints
        $endpoint = str_replace('-', '_', $this->params[0] ?? 'list');
        if(!$this->useFunction('ENDPOINT_' . $endpoint)) {
            return $this->setErrorFromCodelib('not-found');
        }
    }

    private function authenticate()
    {
        $token = $this->getHeaderAuthorization();
        if(!$token || !$this->validateToken($token)) {
            $this->setErrorFromCodelib('security-error');
            return false;
        }
        return true;
    }

    private function validateToken($token) {
        // Implement your token validation
        return true;
    }

    private function isRateLimited() {
        // Implement rate limiting
        return false;
    }

    // GET /products/list
    public function ENDPOINT_list()
    {
        if(!$this->checkMethod('GET')) return;

        $page = (int)($this->formParams['page'] ?? 1);
        $limit = min((int)($this->formParams['limit'] ?? 10), 100);

        $products = $this->getProducts($page, $limit);
        $total = $this->getTotalProducts();

        $this->addReturnData([
            'products' => $products,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'pages' => ceil($total / $limit)
            ]
        ]);
    }

    // POST /products/create
    public function ENDPOINT_create()
    {
        if(!$this->checkMethod('POST')) return;

        // Validate required fields
        if(!$this->checkMandatoryFormParams(['name', 'price', 'category'])) {
            return;
        }

        // Validate types
        if(!$this->validateValue('price', $this->formParams['price'], 'float')) {
            return;
        }

        // Create product
        try {
            $product = $this->createProduct($this->formParams);
            $this->setReturnStatus(201);
            $this->setReturnCode('inserted');
            $this->addReturnData($product);
            $this->core->logs->add("Product created: {$product['id']}", 'products');
        } catch (Exception $e) {
            $this->core->logs->add($e->getMessage(), 'products', 'error');
            $this->setError('Failed to create product', 500);
        }
    }

    private function getProducts($page, $limit) {
        // Database logic
        return [];
    }

    private function getTotalProducts() {
        // Database logic
        return 0;
    }

    private function createProduct($data) {
        // Database logic
        return ['id' => 123, 'name' => $data['name']];
    }
}
```

## Next Steps

- [Script Development Guide](script-development.md)
- [GCP Integration Guide](gcp-integration.md)
- [Security Best Practices](security.md)
- [RESTful Class Reference](../api-reference/RESTful.md)
