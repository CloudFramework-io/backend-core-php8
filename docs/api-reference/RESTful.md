# RESTful Class

## Overview

The `RESTful` class is the base class for all API endpoints in CloudFramework. It provides comprehensive functionality for handling HTTP requests, responses, validation, error handling, and CORS support.

## Inheritance

All your API classes should extend `RESTful`:

```php
<?php
class API extends RESTful
{
    function main()
    {
        // Your API logic here
    }
}
```

## Properties

### Public Properties

| Property | Type | Description |
|----------|------|-------------|
| `$formParams` | array | Form parameters from GET/POST requests |
| `$params` | array | URL path parameters |
| `$method` | string | HTTP method (GET, POST, PUT, DELETE, etc.) |
| `$service` | string | Service name from URL |
| `$error` | int | Error code (0 if no error) |
| `$code` | string\|null | Response code identifier |
| `$core` | Core7 | Reference to Core7 framework instance |

### Protected Properties

| Property | Type | Description |
|----------|------|-------------|
| `$version` | string | RESTful class version |
| `$returnData` | mixed | Data to be returned in the response |
| `$requestHeaders` | array | Request headers |
| `$extra_headers` | array | Additional response headers |

## Main Methods

### Constructor

```php
function __construct(Core7 &$core, $apiUrl = '/h/api')
```

Initializes the RESTful instance, processes request parameters, and sets up default response codes.

**Parameters:**
- `$core` (Core7): Reference to the Core7 framework instance
- `$apiUrl` (string): API base URL (default: '/h/api')

**Automatic Processing:**
- Extracts URL parameters
- Parses form parameters (GET/POST)
- Reads raw input (JSON)
- Trims string values
- Initializes default code library (ok, error, etc.)

---

## Request Handling Methods

### checkMethod()

```php
public function checkMethod(string $methods, $error_msg = ''): bool
```

Validates that the current HTTP method is allowed.

**Parameters:**
- `$methods` (string): Comma-separated list of allowed methods (e.g., 'GET,POST,PUT')
- `$error_msg` (string): Custom error message (optional)

**Returns:** `true` if method is allowed, `false` otherwise

**Example:**
```php
// Only allow GET and POST
if(!$this->checkMethod('GET,POST')) return;

// With custom error message
if(!$this->checkMethod('GET', 'Only GET requests allowed')) return;
```

---

### sendCorsHeaders()

```php
public function sendCorsHeaders($methods = 'GET,POST,PUT', $allow_origins = '', $allow_extra_headers = '')
```

Sends CORS headers to allow cross-origin requests.

**Parameters:**
- `$methods` (string): Allowed HTTP methods
- `$allow_origins` (string): Allowed origins ('*' for all, or comma-separated domains)
- `$allow_extra_headers` (string): Additional allowed headers

**Example:**
```php
// Allow all origins
$this->sendCorsHeaders('GET,POST,PUT,DELETE');

// Allow specific origins
$this->sendCorsHeaders('GET,POST', 'https://example.com,https://app.example.com');

// Allow extra headers
$this->sendCorsHeaders('GET,POST', '*', 'X-Custom-Header,X-Api-Key');
```

---

## Parameter Validation Methods

### checkMandatoryParam()

```php
public function checkMandatoryParam($pos, $msg = '', $validation = [], $code = null): mixed
```

Validates a URL parameter at a specific position.

**Parameters:**
- `$pos` (int): Parameter position (0-indexed)
- `$msg` (string): Error message if validation fails
- `$validation` (array): Validation rules (optional)
- `$code` (string): Error code (optional)

**Returns:** Parameter value if valid, sets error and returns `false` otherwise

**Example:**
```php
// Check if parameter exists at position 0
$userId = $this->checkMandatoryParam(0, 'User ID is required');
if(!$userId) return;

// With validation
$userId = $this->checkMandatoryParam(0, 'Invalid user ID', ['type' => 'integer']);
if(!$userId) return;
```

---

### checkMandatoryFormParam()

```php
public function checkMandatoryFormParam(string $key, $error_msg = '', $values = [], $min_length = 1, $code = null): mixed
```

Validates a required form parameter.

**Parameters:**
- `$key` (string): Parameter key name
- `$error_msg` (string): Error message if validation fails
- `$values` (array): Allowed values (optional, empty array = any value)
- `$min_length` (int): Minimum string length (default: 1)
- `$code` (string): Error code (optional)

**Returns:** Parameter value if valid, sets error and returns `false` otherwise

**Example:**
```php
// Check required parameter
$email = $this->checkMandatoryFormParam('email', 'Email is required');
if(!$email) return;

// Check with allowed values
$status = $this->checkMandatoryFormParam('status', 'Invalid status', ['active', 'inactive']);
if(!$status) return;

// Check with minimum length
$password = $this->checkMandatoryFormParam('password', 'Password too short', [], 8);
if(!$password) return;
```

---

### checkMandatoryFormParams()

```php
public function checkMandatoryFormParams($keys): bool|array
```

Validates multiple required form parameters.

**Parameters:**
- `$keys` (array): Array of parameter keys or key => config arrays

**Returns:** `true` if all valid, array of missing parameters otherwise

**Example:**
```php
// Simple validation
$required = ['email', 'password', 'name'];
if(!$this->checkMandatoryFormParams($required)) return;

// With configurations
$required = [
    'email' => ['error' => 'Email required'],
    'status' => ['values' => ['active', 'inactive'], 'error' => 'Invalid status']
];
if(!$this->checkMandatoryFormParams($required)) return;
```

---

### validateValue()

```php
public function validateValue($field, $value, $type, $validation = '', $msg_error = ''): bool
```

Validates a value against a specific type and rules.

**Parameters:**
- `$field` (string): Field name (for error messages)
- `$value` (mixed): Value to validate
- `$type` (string): Data type (string, integer, email, url, etc.)
- `$validation` (string): Additional validation rules
- `$msg_error` (string): Custom error message

**Returns:** `true` if valid, `false` otherwise (sets error)

**Example:**
```php
// Validate email
if(!$this->validateValue('email', $email, 'email')) return;

// Validate integer
if(!$this->validateValue('age', $age, 'integer')) return;

// Validate with custom message
if(!$this->validateValue('url', $url, 'url', '', 'Invalid URL format')) return;
```

---

## Model Validation Methods

### validatePostData()

```php
public function validatePostData($model, $codelibbase = 'error-form-params', &$data = null, &$dictionaries = []): bool
```

Validates POST data against a model definition.

**Parameters:**
- `$model` (array): Model definition with field rules
- `$codelibbase` (string): Base code for errors
- `$data` (array): Reference to store validated data
- `$dictionaries` (array): Reference to store dictionary data

**Returns:** `true` if valid, `false` otherwise

**Example:**
```php
$model = [
    'name' => ['type' => 'string', 'required' => true],
    'email' => ['type' => 'email', 'required' => true],
    'age' => ['type' => 'integer', 'min' => 18]
];

$data = [];
if(!$this->validatePostData($model, 'user-validation-error', $data)) return;
```

---

### validatePutData()

```php
public function validatePutData($model, $codelibbase = 'error-form-params', &$data = null, &$dictionaries = []): bool
```

Validates PUT data against a model definition (similar to validatePostData but for updates).

---

## Response Methods

### addReturnData()

```php
public function addReturnData($value): true
```

Adds data to the response. If data already exists, it merges or appends.

**Parameters:**
- `$value` (mixed): Data to add to the response

**Example:**
```php
// Add simple data
$this->addReturnData('Hello World');

// Add array data
$this->addReturnData(['users' => $users, 'total' => $total]);

// Add multiple times (will merge/append)
$this->addReturnData(['name' => 'John']);
$this->addReturnData(['age' => 30]);
```

---

### setReturnData()

```php
public function setReturnData($data): true
```

Sets the response data (replaces existing data).

**Parameters:**
- `$data` (mixed): Data to return

**Example:**
```php
$this->setReturnData(['status' => 'success', 'data' => $result]);
```

---

### setReturnResponse()

```php
public function setReturnResponse($response): void
```

Sets a complete response object.

**Parameters:**
- `$response` (array): Complete response structure

**Example:**
```php
$this->setReturnResponse([
    'success' => true,
    'status' => 200,
    'data' => $data,
    'message' => 'Operation successful'
]);
```

---

### updateReturnResponse()

```php
public function updateReturnResponse($response): void
```

Updates specific fields in the response.

**Parameters:**
- `$response` (array): Fields to update in the response

**Example:**
```php
$this->updateReturnResponse(['extra_info' => 'Additional data']);
```

---

## Error Handling Methods

### setError()

```php
public function setError($error, int $returnStatus = 400, $returnCode = null, $message = ''): void
```

Sets an error response.

**Parameters:**
- `$error` (string|array): Error message or error array
- `$returnStatus` (int): HTTP status code (default: 400)
- `$returnCode` (string): Error code identifier
- `$message` (string): Additional message

**Example:**
```php
// Simple error
$this->setError('Invalid user ID', 400);

// With code
$this->setError('User not found', 404, 'user-not-found');

// With array
$this->setError(['field' => 'email', 'message' => 'Email already exists'], 409);
```

---

### addCodeLib()

```php
public function addCodeLib($code, $msg, $error = 400, ?array $model = null): void
```

Adds a reusable error code to the code library.

**Parameters:**
- `$code` (string): Code identifier
- `$msg` (string): Error message
- `$error` (int): HTTP status code
- `$model` (array): Model structure (optional)

**Example:**
```php
// Define custom error codes
$this->addCodeLib('user-not-found', 'User not found', 404);
$this->addCodeLib('invalid-token', 'Invalid authentication token', 401);
$this->addCodeLib('rate-limit', 'Rate limit exceeded', 429);
```

---

### setErrorFromCodelib()

```php
public function setErrorFromCodelib(string $code, $extramsg = ''): void
```

Sets an error using a code from the code library.

**Parameters:**
- `$code` (string): Code identifier from code library
- `$extramsg` (string): Additional message to append

**Example:**
```php
// Use predefined error code
$this->setErrorFromCodelib('user-not-found');

// With extra message
$this->setErrorFromCodelib('params-error', ': user_id is required');
```

---

### getCodeLib()

```php
public function getCodeLib($code): array|null
```

Gets a code definition from the code library.

**Returns:** Code definition array or `null` if not found

---

## Status and Code Methods

### setReturnCode()

```php
public function setReturnCode($code): void
```

Sets the response code.

**Parameters:**
- `$code` (string): Code identifier

---

### getReturnCode()

```php
public function getReturnCode(): string|null
```

Gets the current response code.

**Returns:** Code identifier or `null`

---

### setReturnStatus()

```php
public function setReturnStatus(int $status): void
```

Sets the HTTP status code for the response.

**Parameters:**
- `$status` (int): HTTP status code (200, 404, 500, etc.)

---

### getReturnStatus()

```php
public function getReturnStatus(): int
```

Gets the current HTTP status code.

**Returns:** HTTP status code

---

## Header Methods

### addHeader()

```php
public function addHeader($key, $value): void
```

Adds a custom HTTP header to the response.

**Parameters:**
- `$key` (string): Header name
- `$value` (string): Header value

**Example:**
```php
$this->addHeader('X-Custom-Header', 'CustomValue');
$this->addHeader('X-Rate-Limit-Remaining', '100');
```

---

### getRequestHeader()

```php
public function getRequestHeader($str): string|null
```

Gets a request header value.

**Parameters:**
- `$str` (string): Header name

**Returns:** Header value or `null` if not found

**Example:**
```php
$auth = $this->getRequestHeader('Authorization');
$contentType = $this->getRequestHeader('Content-Type');
```

---

### getHeaderAuthorization()

```php
public function getHeaderAuthorization(): string|null
```

Gets the Authorization header value.

**Returns:** Authorization header value or `null`

**Example:**
```php
$token = $this->getHeaderAuthorization();
if($token) {
    // Validate token
}
```

---

## Message Methods

### setMessage()

```php
public function setMessage(string $message, string $type = 'success', string $description = '', int $time = 5, string $url = ''): bool
```

Sets a user-facing message in the response.

**Parameters:**
- `$message` (string): Message text
- `$type` (string): Message type (success, error, warning, info)
- `$description` (string): Detailed description
- `$time` (int): Display time in seconds
- `$url` (string): Related URL

**Example:**
```php
$this->setMessage('User created successfully', 'success');
$this->setMessage('Operation completed', 'info', 'Your data has been saved', 3);
```

---

### addMessage()

```php
public function addMessage(string $message, string $type = 'success', string $description = '', int $time = 5, string $url = ''): bool
```

Adds a message to the response (allows multiple messages).

---

## Utility Methods

### setReturnFormat()

```php
public function setReturnFormat($method): void
```

Sets the return format for the response.

**Parameters:**
- `$method` (string): Format type (JSON, XML, HTML, etc.)

**Example:**
```php
$this->setReturnFormat('JSON'); // Default
$this->setReturnFormat('XML');
```

---

### useFunction()

```php
protected function useFunction(string $functionName): bool
```

Calls a method if it exists (used for routing to endpoints).

**Parameters:**
- `$functionName` (string): Method name to call

**Returns:** `true` if method exists and was called, `false` otherwise

**Example:**
```php
// Route to ENDPOINT_xxx methods
$endpoint = $this->params[0] ?? 'default';
if(!$this->useFunction('ENDPOINT_' . $endpoint)) {
    return $this->setErrorFromCodelib('not-found');
}
```

---

## Default Code Library

The RESTful class includes these predefined error codes:

| Code | Message | Status |
|------|---------|--------|
| `ok` | OK | 200 |
| `inserted` | Inserted successfully | 201 |
| `no-content` | No content | 204 |
| `form-params-error` | Wrong form parameters | 400 |
| `params-error` | Wrong parameters | 400 |
| `security-error` | You don't have right credentials | 401 |
| `not-allowed` | You are not allowed | 403 |
| `not-found` | Not Found | 404 |
| `method-error` | Wrong method | 405 |
| `conflict` | There are conflicts | 409 |
| `gone` | The resource is not longer available | 410 |
| `unsupported-media` | Unsupported Media Type | 415 |
| `server-error` | Generic server error | 500 |
| `not-implemented` | Not implemented yet | 501 |
| `service-unavailable` | The service is unavailable | 503 |
| `system-error` | Problem in the platform | 503 |
| `datastore-error` | Problem with Datastore | 503 |
| `database-error` | Problem with Database | 503 |
| `bigquery-error` | Problem with BigQuery | 503 |

---

## Complete API Example

```php
<?php
class API extends RESTful
{
    function main()
    {
        // Enable CORS
        $this->sendCorsHeaders('GET,POST,PUT,DELETE');

        // Check authentication
        $token = $this->getHeaderAuthorization();
        if(!$token) {
            return $this->setErrorFromCodelib('security-error');
        }

        // Route to endpoints
        $endpoint = str_replace('-', '_', $this->params[0] ?? 'default');
        if(!$this->useFunction('ENDPOINT_' . $endpoint)) {
            return $this->setErrorFromCodelib('not-found');
        }
    }

    public function ENDPOINT_default()
    {
        $this->addReturnData([
            'api' => 'My API',
            'version' => '1.0',
            'endpoints' => ['/users', '/products', '/orders']
        ]);
    }

    public function ENDPOINT_users()
    {
        // Only allow GET and POST
        if(!$this->checkMethod('GET,POST')) return;

        if($this->method == 'GET') {
            // Get user ID from URL
            $userId = $this->params[1] ?? null;

            if($userId) {
                // Get specific user
                $user = $this->getUser($userId);
                if(!$user) {
                    return $this->setErrorFromCodelib('not-found', ': User not found');
                }
                $this->addReturnData($user);
            } else {
                // Get all users
                $users = $this->getAllUsers();
                $this->addReturnData(['users' => $users]);
            }
        } else {
            // POST - Create user
            // Validate required fields
            if(!$this->checkMandatoryFormParams(['email', 'name', 'password'])) {
                return;
            }

            // Create user
            $user = $this->createUser($this->formParams);
            $this->setReturnStatus(201);
            $this->setReturnCode('inserted');
            $this->addReturnData($user);
        }
    }

    private function getUser($id)
    {
        // Your logic to get user
        return ['id' => $id, 'name' => 'John Doe', 'email' => 'john@example.com'];
    }

    private function getAllUsers()
    {
        // Your logic to get all users
        return [
            ['id' => 1, 'name' => 'John Doe'],
            ['id' => 2, 'name' => 'Jane Smith']
        ];
    }

    private function createUser($data)
    {
        // Your logic to create user
        return ['id' => 123, 'name' => $data['name'], 'email' => $data['email']];
    }
}
```

---

## See Also

- [API Development Guide](../guides/api-development.md)
- [Getting Started](../guides/getting-started.md)
- [DataValidation Class](DataValidation.md)
- [Core7 Class](Core7.md)
