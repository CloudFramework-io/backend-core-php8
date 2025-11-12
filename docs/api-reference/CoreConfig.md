# CoreConfig Class

## Overview

The `CoreConfig` class manages application configuration in CloudFramework. It provides a powerful configuration system with support for JSON config files, environment-specific settings, conditional configuration, tag conversion, GCP Secrets integration, and caching.

## Inheritance

Access CoreConfig through the Core7 instance:

```php
$configValue = $this->core->config->get('my.config.var');
$this->core->config->set('my.config.var', 'value');
```

## Properties

### Public Properties

| Property | Type | Description |
|----------|------|-------------|
| `$data` | array | Configuration data loaded from config files |
| `$menu` | array | Menu configuration items |
| `$cache` | array\|null | Cache storage for configuration data |
| `$cache_secret_key` | string | Encryption key for cached data |
| `$cache_secret_iv` | string | Encryption IV for cached data |

### Protected Properties

| Property | Type | Description |
|----------|------|-------------|
| `$lang` | string | Current language code (default: 'en') |

---

## Configuration Methods

### get()

```php
public function get($var = ''): mixed
```

Retrieves a configuration variable value.

**Parameters:**
- `$var` (string): Configuration variable name using dot notation. If empty, returns all configuration data.

**Returns:** The configuration value, or empty string if not found, or all data if `$var` is empty

**Example:**
```php
// Get specific config value
$projectId = $this->core->config->get('core.gcp.project_id');

// Get all config data
$allConfig = $this->core->config->get();

// Check if a value exists
if($this->core->config->get('core.datastore.on')) {
    // Datastore is enabled
}
```

---

### set()

```php
public function set($var, $data): void
```

Sets a configuration variable.

**Parameters:**
- `$var` (string): Configuration variable name
- `$data` (mixed): Value to assign

**Example:**
```php
$this->core->config->set('my.custom.var', 'my value');
$this->core->config->set('feature.enabled', true);
```

---

### bulkSet()

```php
public function bulkSet(Array $data): void
```

Sets multiple configuration variables at once.

**Parameters:**
- `$data` (array): Associative array of configuration keys and values

**Example:**
```php
$this->core->config->bulkSet([
    'api.rate_limit' => 100,
    'api.timeout' => 30,
    'features.beta' => true
]);
```

---

## File Management Methods

### readConfigJSONFile()

```php
public function readConfigJSONFile($path): bool
```

Reads and processes a JSON configuration file.

**Parameters:**
- `$path` (string): Full path to the JSON config file

**Returns:** `true` on success, `false` on error

**Example:**
```php
$loaded = $this->core->config->readConfigJSONFile('/path/to/config.json');
if(!$loaded) {
    // Handle error
}
```

**Config File Structure:**
```json
{
  "core.gcp.project_id": "my-project",
  "core.datastore.on": true,
  "development: {
    "core.cache.cache_path": "{{rootPath}}/local_data/cache",
    "debug.enabled": true
  },
  "production: {
    "debug.enabled": false
  }
}
```

---

### processConfigData()

```php
public function processConfigData(array $data): void
```

Processes configuration data array with support for conditional tags and includes.

**Parameters:**
- `$data` (array): Configuration data to process

---

## Localization Methods

### getLang()

```php
public function getLang(): string
```

Gets the current language code.

**Returns:** Current language code (e.g., 'en', 'es', 'fr')

**Example:**
```php
$currentLang = $this->core->config->getLang(); // 'en'
```

---

### setLang()

```php
public function setLang($lang): bool
```

Sets the current language.

**Parameters:**
- `$lang` (string): Language code (2+ characters, alphabetic)

**Returns:** `true` if language was set successfully, `false` otherwise

**Example:**
```php
if($this->core->config->setLang('es')) {
    // Language changed to Spanish
}

// Only allowed languages can be set (if configured)
// core.localization.allowed_langs: "en,es,fr"
```

---

## Tag Conversion Methods

### convertTags()

```php
public function convertTags($data): mixed
```

Converts configuration tags in strings or arrays to their actual values.

**Parameters:**
- `$data` (mixed): String or array containing tags to convert

**Returns:** Data with tags converted

**Supported Tags:**
- `{{rootPath}}` - Application root path
- `{{appPath}}` - Application path
- `{{lang}}` - Current language
- `{{confVar:varname}}` - Configuration variable value

**Example:**
```php
// In config.json
{
  "cache.path": "{{rootPath}}/local_data/cache",
  "uploads.path": "{{rootPath}}/uploads/{{lang}}"
}

// After conversion
$cachePath = $this->core->config->get('cache.path');
// Result: "/var/www/myapp/local_data/cache"
```

---

## Environment Variables & Secrets

### getEnvVar()

```php
public function getEnvVar($var, $gcp_project_id = '', $gcp_secret_id = ''): mixed
```

Gets an environment variable from `getenv()` or from GCP Secrets Manager.

**Parameters:**
- `$var` (string): Environment variable name
- `$gcp_project_id` (string): Optional GCP project ID for secrets
- `$gcp_secret_id` (string): Optional GCP secret ID

**Returns:** Environment variable value, or `null` if not found

**Priority:**
1. System environment variables (`getenv()`)
2. GCP Secrets Manager (if configured)

**Example:**
```php
// Get database password from environment or GCP Secrets
$dbPassword = $this->core->config->getEnvVar('DB_PASSWORD');

// Get from specific GCP secret
$apiKey = $this->core->config->getEnvVar('API_KEY', 'my-project', 'api-secrets');
```

---

### readEnvVarsFromGCPSecrets()

```php
public function readEnvVarsFromGCPSecrets($gpc_project_id = '', $gpc_secret_id = '', $reload = false): bool
```

Loads environment variables from Google Cloud Secret Manager.

**Parameters:**
- `$gpc_project_id` (string): GCP project ID (optional, uses `core.gcp.secrets.project_id` if empty)
- `$gpc_secret_id` (string): Secret ID (optional, uses `core.gcp.secrets.env_vars` if empty)
- `$reload` (bool): Force reload from GCP (default: false, uses cache)

**Returns:** `true` on success, `false` on error

**Required Config:**
```json
{
  "core.gcp.secrets.project_id": "my-project",
  "core.gcp.secrets.env_vars": "my-env-vars-secret",
  "core.gcp.secrets.cache_encrypt_key": "base64-encoded-key",
  "core.gcp.secrets.cache_encrypt_iv": "base64-encoded-iv"
}
```

**Secret JSON Structure:**
```json
{
  "env_vars:": {
    "DB_HOST": "localhost",
    "DB_PASSWORD": "secret123",
    "API_KEY": "sk_test_..."
  }
}
```

**Example:**
```php
// Load environment variables from GCP Secrets
$loaded = $this->core->config->readEnvVarsFromGCPSecrets();
if($loaded) {
    $dbHost = $this->core->config->getEnvVar('DB_HOST');
}
```

**Development Mode:**
- In development, creates `local_env_vars.json` with secret keys (values masked)
- Skips GCP if `local_config.json` has `env_vars` defined
- Auto-reloads if `local_env_vars.json` doesn't exist

---

## Cache Methods

### readCache()

```php
public function readCache(string $cache_secret_key = '', string $cache_secret_iv = ''): void
```

Reads the configuration cache.

**Parameters:**
- `$cache_secret_key` (string): Optional encryption key
- `$cache_secret_iv` (string): Optional encryption IV

---

### resetCache()

```php
public function resetCache(string $cache_secret_key = '', string $cache_secret_iv = ''): void
```

Resets the configuration cache.

**Parameters:**
- `$cache_secret_key` (string): Optional encryption key
- `$cache_secret_iv` (string): Optional encryption IV

**Example:**
```php
// Clear configuration cache
$this->core->config->resetCache();
```

---

### updateCache()

```php
public function updateCache($var, $data, string $cache_secret_key = '', string $cache_secret_iv = ''): void
```

Updates a specific cache entry.

**Parameters:**
- `$var` (string): Cache key
- `$data` (mixed): Data to cache
- `$cache_secret_key` (string): Optional encryption key
- `$cache_secret_iv` (string): Optional encryption IV

---

### getCache()

```php
public function getCache(string $key, string $cache_secret_key = '', string $cache_secret_iv = ''): mixed
```

Retrieves a value from cache.

**Parameters:**
- `$key` (string): Cache key
- `$cache_secret_key` (string): Optional encryption key
- `$cache_secret_iv` (string): Optional encryption IV

**Returns:** Cached value, or `null` if not found

---

## Menu Methods

### pushMenu()

```php
public function pushMenu($var): void
```

Adds a menu configuration item.

**Parameters:**
- `$var` (array): Menu item configuration with 'path' key required

**Example in config.json:**
```json
{
  "menu:": [
    {
      "path": "/dashboard",
      "title": "Dashboard",
      "icon": "dashboard"
    },
    {
      "path": "/users/{*}",
      "title": "Users",
      "icon": "users"
    }
  ]
}
```

---

### inMenuPath()

```php
public function inMenuPath(): bool
```

Checks if the current URL matches a menu path.

**Returns:** `true` if current URL is in a menu path, `false` otherwise

---

## Information Methods

### getConfigLoaded()

```php
public function getConfigLoaded(): array
```

Returns a list of configuration files that have been loaded.

**Returns:** Array of config file paths (relative to root path)

**Example:**
```php
$loadedFiles = $this->core->config->getConfigLoaded();
// Returns: ['/config.json', '/local_config.json']
```

---

## Conditional Tags

Configuration files support conditional tags to load different settings based on environment:

### Environment Conditionals

```json
{
  "development:": {
    "core.cache.cache_path": "{{rootPath}}/local_data/cache",
    "debug.enabled": true
  },
  "production:": {
    "debug.enabled": false,
    "core.cache.cache_path": "/tmp/cache"
  },
  "local:": {
    "api.base_url": "http://localhost:8080"
  }
}
```

### Domain/Host Conditionals

```json
{
  "domain: example.com": {
    "site.name": "Example Site"
  },
  "indomain: staging": {
    "debug.enabled": true
  },
  "host: api.example.com": {
    "api.mode": "production"
  }
}
```

### URL Conditionals

```json
{
  "url: /admin": {
    "theme": "admin"
  },
  "inurl: /api/": {
    "cors.enabled": true
  },
  "beginurl: /dashboard": {
    "auth.required": true
  },
  "noturl: /public": {
    "auth.required": true
  }
}
```

### Variable Conditionals

```json
{
  "confvar: feature.beta=true": {
    "ui.new_design": true
  },
  "sessionvar: user_type=admin": {
    "admin.enabled": true
  },
  "servervar: HTTP_HOST=localhost": {
    "debug.enabled": true
  }
}
```

### Authentication Conditionals

```json
{
  "auth:": {
    "show.user_menu": true
  },
  "noauth:": {
    "show.login_form": true
  }
}
```

### Terminal Conditional

```json
{
  "interminal:": {
    "output.format": "text"
  }
}
```

### Combined Conditionals

You can combine multiple conditions with `|`:

```json
{
  "development: | domain: localhost": {
    "debug.enabled": true
  }
}
```

---

## Assignation Tags

### Set Variables

```json
{
  "set: my.custom.var": "value",
  "set: another.var": 123
}
```

### Include Files

```json
{
  "include:": "/path/to/another-config.json"
}
```

### Redirects

```json
{
  "redirect:": [
    { "/old-path": "/new-path" },
    { "/legacy/*": "/new-section" },
    { "*": "/default" }
  ]
}
```

### Environment Variables

```json
{
  "env_vars:": {
    "DB_HOST": "localhost",
    "DB_NAME": "myapp",
    "API_KEY": "secret"
  }
}
```

---

## Common Usage Patterns

### Basic Configuration

```php
// Get configuration value
$projectId = $this->core->config->get('core.gcp.project_id');

// Set configuration value
$this->core->config->set('api.version', 'v2');

// Check if feature is enabled
if($this->core->config->get('feature.enabled')) {
    // Feature is enabled
}
```

### Multi-Environment Setup

**config.json:**
```json
{
  "app.name": "My Application",
  "core.gcp.project_id": "production-project",

  "development:": {
    "core.gcp.project_id": "dev-project",
    "core.cache.cache_path": "{{rootPath}}/local_data/cache",
    "debug.enabled": true
  },

  "production:": {
    "debug.enabled": false,
    "core.cache.cache_path": "/tmp/cache"
  }
}
```

### Working with Secrets

```php
// Load secrets from GCP
$this->core->config->readEnvVarsFromGCPSecrets();

// Access secret values
$dbPassword = $this->core->config->getEnvVar('DB_PASSWORD');
$apiKey = $this->core->config->getEnvVar('API_KEY');

// Use in database connection
$host = $this->core->config->getEnvVar('DB_HOST');
$db = new PDO("mysql:host=$host", $user, $dbPassword);
```

### Language Support

```php
// Get current language
$lang = $this->core->config->getLang(); // 'en'

// Change language
if($this->core->config->setLang('es')) {
    // Now using Spanish
}

// Use in config with tag
// "welcome.message.{{lang}}": "Welcome" (en) or "Bienvenido" (es)
```

---

## Configuration File Best Practices

### 1. Organize by Namespace

```json
{
  "core.gcp.project_id": "my-project",
  "core.gcp.datastore.project_id": "my-project",
  "core.cache.cache_path": "/tmp/cache",

  "app.name": "My App",
  "app.version": "1.0.0",

  "features.beta": false,
  "features.maintenance": false
}
```

### 2. Use Environment-Specific Overrides

```json
{
  "api.base_url": "https://api.example.com",

  "local:": {
    "api.base_url": "http://localhost:8080"
  },

  "development:": {
    "api.base_url": "https://dev-api.example.com"
  }
}
```

### 3. Use Tags for Path Resolution

```json
{
  "core.cache.cache_path": "{{rootPath}}/local_data/cache",
  "uploads.path": "{{rootPath}}/uploads",
  "templates.path": "{{appPath}}/templates"
}
```

### 4. Keep Secrets Out of Config Files

```json
{
  "core.gcp.secrets.env_vars": "my-app-secrets",
  "core.gcp.secrets.project_id": "my-project",

  "env_vars:": {}
}
```

Then access via:
```php
$apiKey = $this->core->config->getEnvVar('API_KEY');
```

---

## See Also

- [Core7 Class Reference](Core7.md)
- [Configuration Guide](../guides/configuration.md)
- [GCP Integration Guide](../guides/gcp-integration.md)
- [Getting Started](../guides/getting-started.md)
