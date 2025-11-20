# CoreCache Class

## Overview

The `CoreCache` class provides a flexible caching system for CloudFramework with support for multiple backends including Redis (memory), local files (directory), and Google Cloud Datastore. It handles caching with expiration, hashing validation, and optional encryption.

## Inheritance

Access CoreCache through the Core7 instance:

```php
// Set cache value
$this->core->cache->set('user_123', $userData);

// Get cache value
$userData = $this->core->cache->get('user_123');

// Delete cache
$this->core->cache->delete('user_123');
```

## Properties

### Public Properties

| Property | Type | Description |
|----------|------|-------------|
| `$cache` | object\|null | Cache backend instance (Redis, CoreCacheFile, or CoreCacheDataStore) |
| `$spacename` | string | Namespace for cache keys (default: 'CloudFrameWork') |
| `$type` | string | Cache type: 'memory' (Redis), 'directory' (files), or 'datastore' |
| `$dir` | string | Directory path for file-based cache |
| `$error` | bool | Error flag |
| `$errorMsg` | array | Error messages |
| `$debug` | bool | Debug mode flag |
| `$lastHash` | string\|null | Last retrieved cache entry hash |
| `$lastExpireTime` | float\|null | Time since last cache entry was set |
| `$errorSecurity` | bool | Security error flag (wrong encryption keys) |

---

## Constructor

```php
function __construct(Core7 &$core, $spacename = '', $path = null, $debug = null)
```

Initializes the cache system.

**Parameters:**
- `$core` (Core7): Reference to Core7 instance
- `$spacename` (string): Namespace for cache keys (optional)
- `$path` (string\|null): Path for file-based cache (activates directory cache if set)
- `$debug` (bool\|null): Enable debug mode (optional, auto-enabled in development)

**Example:**
```php
// Initialized automatically in Core7
// You access it via $this->core->cache

// The cache is initialized based on configuration:
// - In development: uses file cache from core.cache.cache_path
// - In production with Redis: uses memory cache (Redis)
```

---

## Activation Methods

### activateMemory()

```php
function activateMemory(): bool
```

Activates Redis-based memory cache.

**Returns:** `true` on success

**Requirements:**
- Redis server running
- Environment variables: `REDIS_HOST` and `REDIS_PORT`

**Example:**
```php
$this->core->cache->activateMemory();
```

---

### activateCacheFile()

```php
function activateCacheFile($path, $spacename = ''): bool
```

Activates file-based cache in a directory.

**Parameters:**
- `$path` (string): Directory path for cache files (must be writable)
- `$spacename` (string): Optional namespace suffix

**Returns:** `true` if directory exists or was created, `false` on error

**Example:**
```php
// Activate file cache
$cachePath = $this->core->system->root_path . '/local_data/cache';
if($this->core->cache->activateCacheFile($cachePath)) {
    // File cache is active
}
```

---

### activateDataStore()

```php
function activateDataStore($spacename = ''): bool
```

Activates Google Cloud Datastore-based cache.

**Parameters:**
- `$spacename` (string): Optional namespace for cache entities

**Returns:** `true` on success, `false` on error

**Example:**
```php
// Activate Datastore cache
if($this->core->cache->activateDataStore('my_app')) {
    // Datastore cache is active
}
```

---

## Core Cache Methods

### get()

```php
function get($key, $expireTime = -1, $hash = '', $cache_secret_key = '', $cache_secret_iv = ''): mixed
```

Retrieves a value from cache.

**Parameters:**
- `$key` (string): Cache key
- `$expireTime` (int): Expiration time in seconds (-1 = never expires)
- `$hash` (string): Optional hash to validate cache integrity
- `$cache_secret_key` (string): Optional encryption key
- `$cache_secret_iv` (string): Optional encryption IV

**Returns:** Cached value, or `null` if not found, expired, or hash mismatch

**Example:**
```php
// Simple get
$userData = $this->core->cache->get('user_123');

// Get with expiration (cache for 1 hour)
$userData = $this->core->cache->get('user_123', 3600);

// Get with hash validation
$hash = md5($queryString);
$results = $this->core->cache->get('query_results', 3600, $hash);
if(!$results) {
    // Cache miss or hash changed - regenerate
    $results = $this->runQuery($queryString);
    $this->core->cache->set('query_results', $results, $hash);
}

// Get with encryption
$secretKey = $this->core->config->get('core.gcp.secrets.cache_encrypt_key');
$secretIv = $this->core->config->get('core.gcp.secrets.cache_encrypt_iv');
$sensitiveData = $this->core->cache->get('sensitive_data', -1, '', $secretKey, $secretIv);
```

**Special Features:**
- Automatically deletes expired entries
- Validates hash if provided (useful for query caching)
- Decrypts data if encryption keys provided
- Sets `$this->errorSecurity = true` if wrong encryption keys used

---

### set()

```php
function set($key, $object, $hash = null, $cache_secret_key = '', $cache_secret_iv = ''): bool
```

Stores a value in cache.

**Parameters:**
- `$key` (string): Cache key
- `$object` (mixed): Value to cache (any serializable type)
- `$hash` (string): Optional hash for integrity validation
- `$cache_secret_key` (string): Optional encryption key
- `$cache_secret_iv` (string): Optional encryption IV

**Returns:** `true` on success, `false` on error

**Example:**
```php
// Simple set
$this->core->cache->set('user_123', $userData);

// Set with hash
$hash = md5($queryString);
$this->core->cache->set('query_results', $results, $hash);

// Set with encryption
$secretKey = $this->core->config->get('core.gcp.secrets.cache_encrypt_key');
$secretIv = $this->core->config->get('core.gcp.secrets.cache_encrypt_iv');
$this->core->cache->set('sensitive_data', $data, null, $secretKey, $secretIv);

// Cache complex objects
$this->core->cache->set('products_list', [
    'items' => $products,
    'total' => count($products),
    'timestamp' => time()
]);
```

**Data Handling:**
- Automatically serializes and compresses data (gzip)
- Encrypts if encryption keys provided
- Stores microtime for expiration checks
- Stores hash for validation

---

### delete()

```php
function delete($key): bool
```

Deletes a cached entry.

**Parameters:**
- `$key` (string): Cache key to delete

**Returns:** `true` on success

**Example:**
```php
// Delete specific cache entry
$this->core->cache->delete('user_123');

// Delete after update
$this->updateUser($userId, $data);
$this->core->cache->delete('user_' . $userId);
```

---

### keys()

```php
public function keys($search = '*'): array|null
```

Returns cache keys matching a pattern (Redis only).

**Parameters:**
- `$search` (string): Search pattern (default: '*' = all keys)

**Returns:** Array of matching keys, or `null` on error

**Example:**
```php
// Get all cache keys
$allKeys = $this->core->cache->keys();

// Search for specific pattern
$userKeys = $this->core->cache->keys('user_*');

// Delete all user caches
$userKeys = $this->core->cache->keys('user_*');
foreach($userKeys as $key) {
    $this->core->cache->delete($key);
}
```

**Note:** Only works with Redis (memory) cache type.

---

## Convenience Methods

### getByHash()

```php
public function getByHash($str, $hash): mixed
```

Retrieves cache value validated by hash.

**Parameters:**
- `$str` (string): Cache key
- `$hash` (string): Hash to validate against

**Returns:** Cached value if hash matches, `null` otherwise

**Example:**
```php
$hash = md5(json_encode($filters));
$results = $this->core->cache->getByHash('search_results', $hash);
if(!$results) {
    // Cache miss or filters changed
    $results = $this->search($filters);
    $this->core->cache->set('search_results', $results, $hash);
}
```

---

### getByExpireTime()

```php
public function getByExpireTime($str, $seconds): mixed
```

Retrieves cache value with expiration time.

**Parameters:**
- `$str` (string): Cache key
- `$seconds` (int): Expiration time in seconds

**Returns:** Cached value if not expired, `null` otherwise

**Example:**
```php
// Cache for 1 hour (3600 seconds)
$data = $this->core->cache->getByExpireTime('hourly_stats', 3600);
if(!$data) {
    $data = $this->calculateStats();
    $this->core->cache->set('hourly_stats', $data);
}

// Cache for 5 minutes
$data = $this->core->cache->getByExpireTime('live_data', 300);
```

---

## Namespace Methods

### setNameSpace()

```php
function setNameSpace(string $name): void
```

Sets the cache namespace (prefix for all keys).

**Parameters:**
- `$name` (string): Namespace name

**Example:**
```php
// Use different namespace for different tenants
$tenantId = $this->getCurrentTenantId();
$this->core->cache->setNameSpace('tenant_' . $tenantId);

// Now all cache operations use this namespace
$this->core->cache->set('config', $tenantConfig);
// Stored as: CloudFrameWork_directory_tenant_123-config
```

**Note:** `setSpaceName()` is deprecated, use `setNameSpace()` instead.

---

## Initialization

### init()

```php
function init(): bool
```

Initializes the cache backend. Called automatically.

**Returns:** `true` if cache object is ready, `false` otherwise

**Backends:**
- **memory**: Connects to Redis using `REDIS_HOST` and `REDIS_PORT` environment variables
- **directory**: Creates CoreCacheFile instance for file-based caching
- **datastore**: Creates CoreCacheDataStore instance for Datastore caching

---

## Common Usage Patterns

### Basic Caching

```php
// Check cache first
$data = $this->core->cache->get('expensive_operation');
if(!$data) {
    // Cache miss - compute data
    $data = $this->performExpensiveOperation();

    // Store in cache
    $this->core->cache->set('expensive_operation', $data);
}

// Use data
return $data;
```

### Caching with Expiration

```php
// Cache for 1 hour
$cacheKey = 'dashboard_stats';
$stats = $this->core->cache->get($cacheKey, 3600);

if(!$stats) {
    // Cache expired or doesn't exist
    $stats = $this->calculateDashboardStats();
    $this->core->cache->set($cacheKey, $stats);
}

return $stats;
```

### Caching with Hash Validation

```php
// Generate hash from query parameters
$hash = md5(json_encode([
    'filters' => $filters,
    'sort' => $sortBy,
    'page' => $page
]));

// Try to get cached results
$results = $this->core->cache->get('search_results', 3600, $hash);

if(!$results) {
    // Hash changed or cache expired
    $results = $this->search($filters, $sortBy, $page);
    $this->core->cache->set('search_results', $results, $hash);
}

return $results;
```

### Encrypted Cache

```php
// Get encryption keys from config
$key = $this->core->config->get('core.gcp.secrets.cache_encrypt_key');
$iv = $this->core->config->get('core.gcp.secrets.cache_encrypt_iv');

// Store sensitive data encrypted
$this->core->cache->set('user_tokens', $tokens, null, $key, $iv);

// Retrieve encrypted data
$tokens = $this->core->cache->get('user_tokens', -1, '', $key, $iv);

// Check for security errors (wrong keys)
if($this->core->cache->errorSecurity) {
    // Wrong encryption keys used
    $this->core->logs->add('Cache security error - wrong keys');
}
```

### Multi-tenant Caching

```php
class API extends RESTful
{
    function main()
    {
        // Set namespace per tenant
        $tenantId = $this->getTenantId();
        $this->core->cache->setNameSpace('tenant_' . $tenantId);

        // Now cache is isolated per tenant
        $config = $this->core->cache->get('config');
        if(!$config) {
            $config = $this->loadTenantConfig($tenantId);
            $this->core->cache->set('config', $config);
        }
    }
}
```

### Cache Invalidation

```php
// Invalidate specific cache
function updateUser($userId, $data) {
    // Update database
    $this->updateDatabase($userId, $data);

    // Invalidate cache
    $this->core->cache->delete('user_' . $userId);
    $this->core->cache->delete('users_list');
}

// Invalidate pattern (Redis only)
function clearUserCaches() {
    $userKeys = $this->core->cache->keys('user_*');
    foreach($userKeys as $key) {
        $this->core->cache->delete($key);
    }
}
```

### Conditional Caching

```php
// Only cache in production
if($this->core->is->production()) {
    $data = $this->core->cache->get('heavy_computation', 3600);
    if(!$data) {
        $data = $this->heavyComputation();
        $this->core->cache->set('heavy_computation', $data);
    }
} else {
    // Always fresh in development
    $data = $this->heavyComputation();
}
```

---

## Cache Backends

### Memory (Redis)

**Pros:**
- Very fast (in-memory)
- Supports distributed caching
- Automatic eviction policies

**Cons:**
- Requires Redis server
- Limited by memory

**Setup:**
```bash
# Set environment variables
export REDIS_HOST=localhost
export REDIS_PORT=6379
```

```php
$this->core->cache->activateMemory();
```

### Directory (File-based)

**Pros:**
- No external dependencies
- Simple setup
- Good for development

**Cons:**
- Slower than memory cache
- Not suitable for distributed systems

**Setup:**
```php
$cachePath = $this->core->system->root_path . '/local_data/cache';
$this->core->cache->activateCacheFile($cachePath);
```

### Datastore

**Pros:**
- Scalable
- Persistent
- Good for distributed systems

**Cons:**
- Slower than memory
- Costs money (GCP)
- Requires Datastore API

**Setup:**
```php
$this->core->cache->activateDataStore('my_app_cache');
```

---

## Performance Considerations

### Cache Key Design

```php
// Good: Specific keys
$this->core->cache->get('user_123');
$this->core->cache->get('product_456');
$this->core->cache->get('search_electronics_page_2');

// Bad: Generic keys (causes conflicts)
$this->core->cache->get('data');
$this->core->cache->get('results');
```

### Expiration Strategy

```php
// Short expiration for frequently changing data
$this->core->cache->get('live_prices', 60); // 1 minute

// Medium expiration for moderate data
$this->core->cache->get('product_catalog', 3600); // 1 hour

// Long expiration for rarely changing data
$this->core->cache->get('country_list', 86400); // 24 hours

// No expiration for static data
$this->core->cache->get('config', -1); // Never expires
```

### Cache Size

```php
// Avoid caching huge objects
$hugeData = $this->getAllDataFromDatabase(); // DON'T cache this

// Cache smaller, more specific data
$pageData = $this->getPageData($page); // Good
$this->core->cache->set('page_' . $page, $pageData);
```

---

## Debug Mode

When debug mode is enabled, cache operations are logged:

```php
// Enable debug
$this->core->cache->debug = true;

// Operations will be logged
$this->core->cache->set('test', 'value');
// Log: "set($key=test,..) in namespace..."

$value = $this->core->cache->get('test');
// Log: "get($key=test) successful returned in namespace..."

$this->core->cache->delete('test');
// Log: "delete(). token: CloudFrameWork_directory-test"
```

---

## Error Handling

```php
// Check for errors
$this->core->cache->set('key', $data);
if($this->core->cache->error) {
    $errors = $this->core->cache->errorMsg;
    // Handle errors
}

// Check for security errors (wrong encryption keys)
$data = $this->core->cache->get('key', -1, '', $wrongKey, $wrongIv);
if($this->core->cache->errorSecurity) {
    // Wrong encryption keys - cache was deleted for security
}
```

---

## Configuration

Set cache configuration in `config.json`:

```json
{
  "core.cache.cache_path": "{{rootPath}}/local_data/cache",
  "core.gcp.secrets.cache_encrypt_key": "your-base64-key",
  "core.gcp.secrets.cache_encrypt_iv": "your-base64-iv",

  "development:": {
    "core.cache.cache_path": "{{rootPath}}/local_data/cache"
  },

  "production:": {
    "core.cache.type": "memory"
  }
}
```

Generate encryption keys:
```bash
# Generate key (32 bytes)
openssl rand -base64 32

# Generate IV (24 bytes)
openssl rand -base64 24
```

---

## See Also

- [Core7 Class Reference](Core7.md)
- [CoreConfig Class Reference](CoreConfig.md)
- [Performance Guide](../guides/performance.md)
- [Configuration Guide](../guides/configuration.md)
