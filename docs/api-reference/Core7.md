# Core7 Class

## Overview

The `Core7` class is the main framework class that provides access to all core functionality and services. It is automatically available in your APIs and scripts through `$this->core` and includes multiple sub-classes for different purposes.

## Accessing Core7

In your APIs and scripts, Core7 is available as `$this->core`:

```php
// In your API
class API extends RESTful
{
    function main()
    {
        // Access Core7 and its sub-classes
        $this->core->logs->add('API called');
        $config = $this->core->config->get('my_setting');
        $cached = $this->core->cache->get('my_key');

        // Load external classes
        $ds = $this->core->loadClass('DataStore', ['Users']);
    }
}
```

## Core7 Sub-Classes

Core7 initializes and provides access to multiple sub-classes:

| Property | Class | Purpose |
|----------|-------|---------|
| `$this->core->__p` | [CorePerformance](#coreperformance) | Performance monitoring |
| `$this->core->is` | [CoreIs](#coreis) | Environment detection |
| `$this->core->system` | [CoreSystem](#coresystem) | System information |
| `$this->core->logs` | [CoreLog](#corelog) | Logging system |
| `$this->core->errors` | [CoreLog](#corelog) | Error logging |
| `$this->core->config` | [CoreConfig](#coreconfig) | Configuration management |
| `$this->core->session` | [CoreSession](#coresession) | Session management |
| `$this->core->security` | [CoreSecurity](#coresecurity) | Security features |
| `$this->core->cache` | [CoreCache](#corecache) | Caching system |
| `$this->core->request` | [CoreRequest](#corerequest) | HTTP request handling |
| `$this->core->user` | [CoreUser](#coreuser) | User management |
| `$this->core->localization` | [CoreLocalization](#corelocalization) | Localization/i18n |
| `$this->core->model` | [CoreModel](#coremodel) | Data models |

---

## Main Methods

### loadClass()

```php
public function loadClass($class, $params = null): object|null
```

Loads and instantiates a CloudFramework class.

**Parameters:**
- `$class` (string): Class name
- `$params` (mixed): Parameters to pass to the class constructor

**Returns:** Instance of the class, or `null` if not found

**Example:**
```php
// Load DataStore
$ds = $this->core->loadClass('DataStore', ['Users']);

// Load Buckets
$buckets = $this->core->loadClass('Buckets', ['gs://my-bucket']);

// Load DataBQ
$bq = $this->core->loadClass('DataBQ', ['my_dataset']);

// Load CloudSQL
$sql = $this->core->loadClass('CloudSQL', [
    'host',
    'user',
    'password',
    'database'
]);

// Load Email
$email = $this->core->loadClass('Email');

// Load custom class from /class directory
$myClass = $this->core->loadClass('MyCustomClass', ['param1', 'param2']);
```

**Class Loading Paths:**
1. `vendor/cloudframework-io/backend-core-php8/src/class/{Class}.php`
2. `./class/{Class}.php` (your project)
3. Custom path from `core.api.extra_path` config

---

### jsonEncode()

```php
public function jsonEncode($input, $options = null): string|false
```

Encodes data to JSON with UTF-8 support.

**Parameters:**
- `$input` (mixed): Data to encode
- `$options` (int): JSON encode options

**Returns:** JSON string, or `false` on error

**Example:**
```php
$json = $this->core->jsonEncode(['name' => 'John', 'age' => 30]);
// Returns: {"name":"John","age":30}

// With custom options
$json = $this->core->jsonEncode($data, JSON_PRETTY_PRINT);
```

---

### jsonDecode()

```php
public function jsonDecode($input): mixed
```

Decodes JSON string to PHP array/object.

**Parameters:**
- `$input` (string): JSON string

**Returns:** Decoded data

**Example:**
```php
$data = $this->core->jsonDecode('{"name":"John","age":30}');
// Returns: ['name' => 'John', 'age' => 30]
```

---

### getCloudFrameworkAPIBaseURL()

```php
public function getCloudFrameworkAPIBaseURL(): string
```

Gets the base URL for CloudFramework API services.

**Returns:** Current CloudFramework API base URL

**Example:**
```php
$apiUrl = $this->core->getCloudFrameworkAPIBaseURL();
// Returns: 'https://api.cloudframework.io' (default)

// Use in API calls
$url = $this->core->getCloudFrameworkAPIBaseURL() . '/core/secrets/my-platform';
```

---

### setCloudFrameworkAPIBaseURL()

```php
public function setCloudFrameworkAPIBaseURL(string $url): string
```

Sets the base URL for CloudFramework API services.

**Parameters:**
- `$url` (string): New base URL (must be a valid URL starting with 'http')

**Returns:** The current CloudFramework API base URL

**Example:**
```php
// Set custom API URL (e.g., for testing or private instance)
$this->core->setCloudFrameworkAPIBaseURL('https://custom-api.mycompany.com');

// Invalid URL won't change the setting
$this->core->setCloudFrameworkAPIBaseURL('invalid-url');
// Returns original URL, doesn't change it

// Use case: Override for development
if($this->core->is->development()) {
    $this->core->setCloudFrameworkAPIBaseURL('http://localhost:8080/api');
}
```

---

### verifyModelStructureForObject()

```php
public function verifyModelStructureForObject(string $object, ?array $options = []): false|string
```

Verifies and returns the model structure for a given object, supporting extended CloudFramework Objects (CFOs).

**Parameters:**
- `$object` (string): Object name to verify
- `$options` (array): Optional configuration options

**Returns:** Model structure as string, or `false` if verification fails

**Example:**
```php
// Verify model structure exists
$modelStructure = $this->core->verifyModelStructureForObject('Users');
if($modelStructure) {
    // Model is valid, use it
    $ds = $this->core->loadClass('DataStore', [$modelStructure]);
} else {
    // Model not found or invalid
    $this->core->errors->add('Invalid model structure for Users');
}

// With options for extended CFOs
$modelStructure = $this->core->verifyModelStructureForObject('CustomObject', [
    'allow_extended' => true,
    'merge_cfo' => true
]);
```

**Use Case:**
This method is particularly useful when working with CloudFramework Objects that may have extensions or when you need to validate model structure before loading DataStore or other model-dependent classes.

---

## CorePerformance

**Access:** `$this->core->__p`

Monitors performance and execution time.

### add()

```php
$this->core->__p->add($title, $data = '', $type = '');
```

Adds a performance marker.

**Example:**
```php
$this->core->__p->add('Start processing', '', 'note');
// ... your code ...
$this->core->__p->add('End processing', '', 'endnote');
```

---

## CoreIs

**Access:** `$this->core->is`

Detects environment and execution context.

### Methods

```php
// Check environment
if($this->core->is->development()) {
    echo "Running in development mode";
}

if($this->core->is->production()) {
    echo "Running in production";
}

// Check execution context
if($this->core->is->terminal()) {
    echo "Running from terminal";
}

// Check server
if($this->core->is->localEnvironment()) {
    echo "Running on localhost";
}

// Check GCP environment
if($this->core->is->gae()) {
    echo "Running on Google App Engine";
}
```

---

## CoreSystem

**Access:** `$this->core->system`

Provides system and environment information.

### Properties

```php
// Root path
echo $this->core->system->root_path;
// /var/www/html

// App path
echo $this->core->system->app_path;
// /var/www/html/app

// URL information
echo $this->core->system->url['host'];
echo $this->core->system->url['protocol'];
echo $this->core->system->url['url'];
echo $this->core->system->url['uri'];

// Server information
echo $this->core->system->os;
echo $this->core->system->ip;

// Time zone
echo $this->core->system->time_zone[0];
// UTC
```

---

## CoreLog

**Access:** `$this->core->logs` and `$this->core->errors`

Logging system for application messages and errors.

### add()

```php
$this->core->logs->add($message, $category = '', $type = 'info');
```

Adds a log entry.

**Parameters:**
- `$message` (string|array): Log message
- `$category` (string): Log category
- `$type` (string): Log type (info, warning, error)

**Example:**
```php
// Simple log
$this->core->logs->add('User logged in');

// Log with category
$this->core->logs->add('Payment processed', 'payments');

// Log with type
$this->core->logs->add('Database connection failed', 'database', 'error');

// Error logging
$this->core->errors->add('Critical error occurred', 'system', 'error');

// Log arrays
$this->core->logs->add(['user_id' => 123, 'action' => 'login'], 'auth');
```

### get()

```php
$logs = $this->core->logs->get();
```

Gets all log entries.

**Example:**
```php
$logs = $this->core->logs->get();
foreach($logs as $log) {
    echo $log . "\n";
}
```

---

## CoreConfig

**Access:** `$this->core->config`

Configuration management system.

### get()

```php
$value = $this->core->config->get($key, $default = null);
```

Gets a configuration value.

**Example:**
```php
// Simple config
$projectId = $this->core->config->get('core.gcp.project_id');

// With default
$timeout = $this->core->config->get('api.timeout', 30);

// Nested config
$dbHost = $this->core->config->get('database.host');
$cacheEnabled = $this->core->config->get('cache.enabled');
```

### set()

```php
$this->core->config->set($key, $value);
```

Sets a configuration value.

**Example:**
```php
$this->core->config->set('api.timeout', 60);
$this->core->config->set('custom.setting', 'value');
```

### readConfigJSONFile()

```php
$this->core->config->readConfigJSONFile($file_path);
```

Loads configuration from a JSON file.

**Example:**
```php
// Load custom config
$this->core->config->readConfigJSONFile('/path/to/config.json');

// Automatically loaded files:
// - config.json (main)
// - local_config.json (development)
// - local_script.json (scripts in development)
```

---

## CoreSession

**Access:** `$this->core->session`

Session management system.

### init()

```php
$this->core->session->init($id = 'CloudFramework');
```

Initializes a session.

### set()

```php
$this->core->session->set($key, $value);
```

Sets a session value.

**Example:**
```php
$this->core->session->set('user_id', 123);
$this->core->session->set('cart', $cartData);
```

### get()

```php
$value = $this->core->session->get($key, $default = null);
```

Gets a session value.

**Example:**
```php
$userId = $this->core->session->get('user_id');
$cart = $this->core->session->get('cart', []);
```

### delete()

```php
$this->core->session->delete($key);
```

Deletes a session value.

**Example:**
```php
$this->core->session->delete('cart');
```

---

## CoreSecurity

**Access:** `$this->core->security`

Security and authentication features.

### checkUserIP()

```php
$allowed = $this->core->security->checkUserIP($ips);
```

Checks if user IP is in allowed list.

### existBasicAuth()

```php
if($this->core->security->existBasicAuth()) {
    $user = $_SERVER['PHP_AUTH_USER'];
    $pass = $_SERVER['PHP_AUTH_PW'];
}
```

Checks if HTTP Basic Auth credentials exist.

### encrypt()

```php
$encrypted = $this->core->security->encrypt($data, $key);
```

Encrypts data.

### decrypt()

```php
$decrypted = $this->core->security->decrypt($encrypted, $key);
```

Decrypts data.

**Example:**
```php
// Encrypt sensitive data
$encrypted = $this->core->security->encrypt('secret data', 'encryption-key');

// Decrypt
$decrypted = $this->core->security->decrypt($encrypted, 'encryption-key');
```

---

## CoreCache

**Access:** `$this->core->cache`

Caching system (supports file cache, memory, and Datastore).

### set()

```php
$this->core->cache->set($key, $value, $ttl = 0);
```

Sets a cache value.

**Parameters:**
- `$key` (string): Cache key
- `$value` (mixed): Value to cache
- `$ttl` (int): Time to live in seconds (0 = no expiration)

**Example:**
```php
// Cache for 1 hour
$this->core->cache->set('users_list', $users, 3600);

// Cache indefinitely
$this->core->cache->set('config', $config);

// Cache complex data
$this->core->cache->set('api_response', ['data' => $data, 'timestamp' => time()], 300);
```

### get()

```php
$value = $this->core->cache->get($key);
```

Gets a cache value.

**Example:**
```php
$users = $this->core->cache->get('users_list');

if($users === null) {
    // Cache miss - fetch from database
    $users = $this->fetchUsersFromDB();
    $this->core->cache->set('users_list', $users, 3600);
}
```

### delete()

```php
$this->core->cache->delete($key);
```

Deletes a cache entry.

**Example:**
```php
$this->core->cache->delete('users_list');
```

### flush()

```php
$this->core->cache->flush();
```

Clears all cache entries.

---

## CoreRequest

**Access:** `$this->core->request`

HTTP request utilities.

### get()

```php
$data = $this->core->request->get($url, $options = []);
```

Performs a GET request.

**Example:**
```php
$response = $this->core->request->get('https://api.example.com/data');
$json = json_decode($response, true);

// With headers
$response = $this->core->request->get('https://api.example.com/data', [
    'headers' => ['Authorization' => 'Bearer token123']
]);
```

### post()

```php
$data = $this->core->request->post($url, $data, $options = []);
```

Performs a POST request.

**Example:**
```php
$response = $this->core->request->post('https://api.example.com/users', [
    'name' => 'John Doe',
    'email' => 'john@example.com'
]);
```

---

## CoreUser

**Access:** `$this->core->user`

User management and authentication.

### setUser()

```php
$this->core->user->setUser($userData);
```

Sets current user data.

### getUser()

```php
$user = $this->core->user->getUser();
```

Gets current user data.

**Example:**
```php
// Set user after login
$this->core->user->setUser([
    'id' => 123,
    'name' => 'John Doe',
    'email' => 'john@example.com',
    'role' => 'admin'
]);

// Get user in another endpoint
$user = $this->core->user->getUser();
if($user && $user['role'] === 'admin') {
    // Admin access
}
```

---

## CoreLocalization

**Access:** `$this->core->localization`

Localization and internationalization.

### setLang()

```php
$this->core->localization->setLang($lang);
```

Sets the current language.

### getLang()

```php
$lang = $this->core->localization->getLang();
```

Gets the current language.

**Example:**
```php
// Set language
$this->core->localization->setLang('es');

// Get translated string
$message = $this->core->localization->get('welcome_message');
```

---

## Complete Example

```php
<?php
class API extends RESTful
{
    function main()
    {
        // Log API call
        $this->core->logs->add('API endpoint accessed', 'api');

        // Check environment
        if($this->core->is->development()) {
            $this->core->logs->add('Running in development mode');
        }

        // Get configuration
        $timeout = $this->core->config->get('api.timeout', 30);
        $projectId = $this->core->config->get('core.gcp.project_id');

        // Check cache
        $cacheKey = 'users_' . $this->formParams['filter'] ?? 'all';
        $users = $this->core->cache->get($cacheKey);

        if($users === null) {
            // Cache miss - fetch from database
            $ds = $this->core->loadClass('DataStore', ['Users']);
            $users = $ds->fetchAll();

            // Cache for 5 minutes
            $this->core->cache->set($cacheKey, $users, 300);
        }

        // Check user session
        $userId = $this->core->session->get('user_id');
        if(!$userId) {
            return $this->setErrorFromCodelib('security-error');
        }

        // Log performance
        $this->core->__p->add('Data fetched', count($users), 'note');

        // Return data
        $this->addReturnData([
            'users' => $users,
            'cached' => $users !== null,
            'environment' => $this->core->is->development() ? 'dev' : 'prod'
        ]);
    }
}
```

---

## See Also

- [Getting Started Guide](../guides/getting-started.md)
- [API Development Guide](../guides/api-development.md)
- [RESTful Class](RESTful.md)
- [Configuration Guide](../guides/configuration.md)
