# CLOUDIA.md - CloudFramework PHP8 Guide and Generative AI 

This file provides instructions to Claude Code (claude.ai/code) for projects using `cloudframework-io/backend-core-php8`.

**Usage:** Add to your project's CLAUDE.md: `Follow instructions in vendor/cloudframework-io/backend-core-php8/cloudia/CLOUDIA.md`

---

## Framework Overview
CloudFramework PHP8 is a backend framework for REST APIs and scripts optimized for Google Cloud Platform and CLOUDFRAMEWORK EaaS (Enterprise as a Service)

- **Pattern:** Service Locator with `Core7` as central hub
- **Entry Point:** `vendor/cloudframework-io/backend-core-php8/src/dispatcher.php`
- **Base Classes:** `RESTful` for APIs, `CoreScripts` for scripts

## Project Structure

```
your-project/
├── api/                    # REST API endpoints (*.php)
├── scripts/                # CLI scripts (*.php)
├── config.json             # Main configuration
├── local_config.json       # Local overrides (gitignored)
├── local_data/
│   ├── cache/              # File cache (dev)
│   └── application_default_credentials.json
└── vendor/cloudframework-io/backend-core-php8/
```

## Essential Commands

```bash
# Start local server at http://localhost:8080
composer serve

# Run a script
composer script -- {script_name}[/{param1}] [?formParam=value]

# Create new API/script from template
composer create-api
composer create-script

# Clear cache
composer clean

# Setup GCP credentials
composer credentials
```

## CloudIA
CloudIA is the solution for Generative IA connected with CLOUDFRAMEWORK EaaS

```bash
# Verify you have credentials with CloudIA
composer script _cloudia/auth

# GET X-DS-TOKEN to connect with EaaS CLOUD Platform
composer script _cloudia/auth/x-ds-token
```

## API Development

### Basic API Structure

```php
<?php
// api/example.php
class API extends RESTful {

    function main() {
        // Route to endpoint method
        $this->end_point = $this->params[0] ?? 'default';
        $this->useFunction('ENDPOINT_' . str_replace('-', '_', $this->end_point));
    }

    public function ENDPOINT_default() {
        // Return data
        $this->addReturnData(['message' => 'Hello World']);
    }

    public function ENDPOINT_users() {
        // Validate HTTP method
        if (!$this->checkMethod('GET,POST')) return;

        // Validate required params
        if (!$this->checkMandatoryFormParams(['email'])) return;

        // Access form data
        $email = $this->formParams['email'];

        // Use data layer
        $ds = $this->core->loadClass('DataStore', ['entity_name' => 'User']);
        $users = $ds->fetchByQuery(['email' => $email], 10);

        if ($ds->error) {
            return $this->setErrorFromCodelib('datastore-error', $ds->errorMsg);
        }

        $this->addReturnData($users);
    }
}
```

### Available Variables in APIs

| Variable | Description |
|----------|-------------|
| `$this->core` | Core7 service locator (access all framework features) |
| `$this->params[]` | URL path segments after API name |
| `$this->formParams[]` | Merged GET/POST/JSON input |
| `$this->requestMethod` | HTTP method (GET, POST, PUT, DELETE) |

### RESTful Methods Reference

```php
// HTTP method validation
$this->checkMethod('GET,POST,PUT,DELETE');

// CORS headers
$this->sendCorsHeaders('GET,POST', '*');

// Required field validation
$this->checkMandatoryFormParams(['field1', 'field2']);
$this->checkMandatoryParam(0, 'Missing ID parameter');  // URL param

// Return data (success)
$this->addReturnData($data);

// Return error
$this->setError('Error message', 400);
$this->setErrorFromCodelib('params-error', 'Details');
$this->setErrorFromCodelib('not-found', 'User not found');
$this->setErrorFromCodelib('security-error', 'Unauthorized');

// Call method dynamically
$this->useFunction('ENDPOINT_name');  // Returns false if not exists
```

### Response Format

**Success:**
```json
{
  "success": true,
  "status": 200,
  "code": "ok",
  "data": { ... }
}
```

**Error:**
```json
{
  "success": false,
  "status": 400,
  "code": "params-error",
  "errorMsg": ["Error description"]
}
```

---

## Script Development

### Basic Script Structure

```php
<?php
// scripts/myprocess.php
class Script extends Scripts2020 {

    function main() {
        // Log output
        $this->core->logs->add('Starting process');

        // Access parameters
        $action = $this->params[0] ?? 'default';
        $id = $this->formParams['id'] ?? null;

        // Use data layer
        $ds = $this->core->loadClass('DataStore', ['entity_name' => 'Task']);
        $tasks = $ds->fetchAll();

        // Terminal output
        $this->sendTerminal('Found ' . count($tasks) . ' tasks');

        // Structured output
        $this->sendOutput('Process completed', $tasks);
    }
}
```

### Run Scripts

```bash
# Basic execution
composer script -- myprocess

# With path params
composer script -- myprocess/action1/param2

# With form params
composer script -- myprocess?id=123&mode=test

# With debug output
composer script -- myprocess?__p
```

---

## Core7 Services

Access via `$this->core` in APIs and scripts:

```php
$this->core->config      // Configuration (CoreConfig)
$this->core->cache       // Cache system (CoreCache)
$this->core->logs        // Debug logging (CoreLog)
$this->core->errors      // Error collection (CoreLog)
$this->core->session     // Session management (CoreSession)
$this->core->security    // Auth & tokens (CoreSecurity)
$this->core->request     // HTTP client (CoreRequest)
$this->core->is          // Environment detection (CoreIs)
$this->core->system      // System info (CoreSystem)
```

### Common Operations

```php
// Configuration
$value = $this->core->config->get('my.config.key');
$this->core->config->set('key', 'value');

// Logging (visible with ?__p)
$this->core->logs->add('Debug message');
$this->core->logs->add('With context', 'category');

// Cache
$this->core->cache->set('key', $data, 3600);  // TTL in seconds
$cached = $this->core->cache->get('key');
$this->core->cache->delete('key');

// Environment detection
if ($this->core->is->development()) { ... }
if ($this->core->is->production()) { ... }
if ($this->core->is->terminal()) { ... }

// HTTP headers
$token = $this->core->request->getHeader('X-Auth-Token');
```

---

## Data Layer Classes

Load classes via: `$this->core->loadClass('ClassName', $params)`

Classes are cached by configuration - same params return same instance.

### DataStore (Google Cloud Datastore)

```php
$ds = $this->core->loadClass('DataStore', [
    'entity_name' => 'User',
    'namespace' => 'default',  // Multi-tenancy
    'model' => [
        'KeyId' => 'key',                              // Auto ID
        'email' => ['string', 'required|email|unique'],
        'name' => ['string', 'required'],
        'age' => ['integer', 'min:18'],
        'status' => ['string', 'values:active,inactive'],
        'profile' => ['json', ''],                     // Auto JSON
        'created' => ['datetime', 'index']
    ]
]);

// Create
$id = $ds->create(['email' => 'user@example.com', 'name' => 'John']);

// Read by ID
$entity = $ds->read(['id' => $id]);

// Update
$ds->update(['KeyId' => $id, 'status' => 'inactive']);

// Delete
$ds->delete(['id' => $id]);

// Query
$results = $ds->fetchByQuery(['status' => 'active'], 10);  // limit 10
$results = $ds->fetchByQuery(['email' => 'user%'], 10);    // LIKE (starts with)
$results = $ds->fetchByQuery(['age' => ['>', 18]], 10);    // Comparison
$results = $ds->fetchByQuery(['deleted' => '__null__'], 10);  // NULL check

// Error handling
if ($ds->error) {
    return $this->setErrorFromCodelib('datastore-error', $ds->errorMsg);
}
```

**Model Field Types:** `key`, `keyname`, `string`, `integer`, `float`, `boolean`, `datetime`, `date`, `json`, `geo`, `list`, `zip`

**Validation Rules:** `required`, `email`, `unique`, `index`, `min:N`, `max:N`, `minlength:N`, `maxlength:N`, `values:a,b,c`, `internal`

### DataSQL (MySQL/PostgreSQL)

```php
$sql = $this->core->loadClass('DataSQL', [
    'users',  // Table name
    [
        'model' => [
            'id' => ['int(11)', 'isKey'],
            'email' => ['varchar(255)', 'required|email'],
            'name' => ['varchar(100)', 'required'],
            'status' => ['enum(active,inactive)', 'required'],
            'created' => ['datetime', 'internal']
        ],
        'mapping' => [
            'id' => 'user_id',       // Internal => DB column
            'name' => 'full_name'
        ]
    ]
]);

// Query builder (chainable)
$results = $sql->select(['id', 'email', 'name'])
    ->where('status', '=', 'active')
    ->where('created', '>', '2024-01-01')
    ->order('created', 'DESC')
    ->limit(10)
    ->fetch();

// JOIN
$addressSql = $this->core->loadClass('DataSQL', ['addresses', [...]]);
$sql->join('left', $addressSql, 'id', 'user_id');

// Debug SQL
list($query, $params) = $sql->getQuerySQLWhereAndParams();
```

### DataBQ (BigQuery)

```php
$bq = $this->core->loadClass('DataBQ', [
    'dataset.table',
    [
        'model' => [
            'id' => 'INTEGER',
            'event' => 'STRING',
            'timestamp' => 'TIMESTAMP'
        ]
    ]
]);

// Insert (streaming)
$bq->insert(['id' => 1, 'event' => 'login', 'timestamp' => time()]);

// Query
$results = $bq->query('SELECT * FROM dataset.table WHERE id > ?', [100]);
```

### CloudSQL (Low-Level MySQL)

```php
$db = $this->core->loadClass('CloudSQL');
$db->connect([
    'host' => 'localhost',
    'user' => 'root',
    'password' => '',
    'db' => 'mydb'
]);

$results = $db->getDataFromQuery('SELECT * FROM users WHERE status = ?', ['active']);
$db->execute('UPDATE users SET status = ? WHERE id = ?', ['inactive', 123]);
```

### Buckets (Cloud Storage)

```php
$bucket = $this->core->loadClass('Buckets', 'gs://my-bucket');

// Read file
$content = $bucket->getObject('path/to/file.txt');

// Write file
$bucket->uploadObject('path/to/file.txt', $content, ['contentType' => 'text/plain']);

// List files
$files = $bucket->listObjects('folder/');

// Delete
$bucket->deleteObject('path/to/file.txt');

// Error handling
if ($bucket->error) {
    return $this->setErrorFromCodelib('storage-error', $bucket->errorMsg);
}
```

### GoogleSecrets (Secret Manager)

```php
$secrets = $this->core->loadClass('GoogleSecrets');

// Get secret value
$apiKey = $secrets->getSecret('my-api-key');
$dbPassword = $secrets->getSecret('db-password', 'latest');

// Create and store secret
$secrets->createSecret('new-secret-id');
$secrets->addSecretVersion('new-secret-id', 'secret-value');

// List secrets
$allSecrets = $secrets->listSecrets();

// Error handling
if ($secrets->error) {
    $this->core->logs->add('Secret error: ' . json_encode($secrets->errorMsg));
}
```

---

## Configuration

### config.json Structure

```json
{
  "core.gcp.project_id": "your-gcp-project",
  "core.gcp.credentials": "{{documentRoot}}/local_data/application_default_credentials.json",

  "core.gcp.datastore.on": true,
  "core.gcp.datastore.namespace": "default",

  "core.gcp.datastorage.on": true,
  "core.gcp.bigquery.on": true,

  "core.cache.cache_path": "{{documentRoot}}/local_data/cache",

  "core.db.connections": {
    "default": {
      "host": "localhost",
      "user": "root",
      "password": "",
      "db": "mydb"
    }
  },

  "development": {
    "my.custom.setting": "dev-value"
  },

  "production": {
    "my.custom.setting": "prod-value"
  }
}
```

### Variable Substitution

- `{{documentRoot}}` - Project root directory
- `{{rootPath}}` - Framework root
- `{{dev}}` / `{{production}}` - Environment flags

### Local Overrides

Create `local_config.json` (gitignored) for local development overrides:

```json
{
  "core.gcp.project_id": "my-dev-project",
  "my.debug.setting": true
}
```

---

## Error Handling Pattern

```php
// Standard pattern for data operations
$ds = $this->core->loadClass('DataStore', ['entity_name' => 'User']);
$result = $ds->create($data);

if ($ds->error) {
    return $this->setErrorFromCodelib('datastore-error', $ds->errorMsg);
}

// Common error codes
$this->setErrorFromCodelib('params-error', 'Missing required field');
$this->setErrorFromCodelib('not-found', 'Resource not found');
$this->setErrorFromCodelib('security-error', 'Unauthorized access');
$this->setErrorFromCodelib('system-error', 'Internal error');

// Manual error with HTTP status
$this->setError('Custom error message', 400);

// Multiple error messages
$this->errorMsg[] = 'First error';
$this->errorMsg[] = 'Second error';
```

---

## Authentication Patterns

```php
// Get custom header
$token = $this->core->request->getHeader('X-Auth-Token');
if (!$token) {
    return $this->setErrorFromCodelib('security-error', 'Token required');
}

// HTTP Basic Auth
if ($this->core->security->existBasicAuth()) {
    $user = $this->core->security->getBasicAuthUser();
    $pass = $this->core->security->getBasicAuthPassword();
}

// GCP Service Account
$email = $this->core->security->getGoogleEmailAccount();
```

---

## Debugging

### Performance Logs

Add `?__p` to any API call or script to see execution trace:

```bash
# API
curl "http://localhost:8080/example/users?__p"

# Script
composer script -- myprocess?__p
```

### Manual Logging

```php
// Add to logs (visible in response with ?__p)
$this->core->logs->add('Debug message');
$this->core->logs->add('With category', 'database');

// Performance tracking
$this->core->__p->add('Operation started', 'context', 'note');
// ... code ...
$this->core->__p->add('Operation ended', 'context', 'endnote');
```

---

## Deployment (Google App Engine)

### app.yaml Example

```yaml
runtime: php83
service: default

entrypoint: vendor/cloudframework-io/backend-core-php8/src/dispatcher.php

automatic_scaling:
  max_instances: 10

env_variables:
  PROJECT_ID: "your-project"

handlers:
  - url: /.*
    script: auto
```

### Deploy Commands

```bash
# Deploy to App Engine
gcloud app deploy app.yaml --project=your-project

# Deploy with version
gcloud app deploy app.yaml --version=$(date +"%Y%m%d%H%M")
```

---

## Quick Reference

### URL Routing

```
GET /api-name              → params[0] = null
GET /api-name/action       → params[0] = 'action'
GET /api-name/action/123   → params[0] = 'action', params[1] = '123'
```

### Form Data Access

```php
// All merged (GET + POST + JSON body)
$all = $this->formParams;

// Specific field
$email = $this->formParams['email'] ?? null;
```

### Class Loading

```php
// Classes are cached by config hash
$ds1 = $this->core->loadClass('DataStore', ['entity_name' => 'User']);
$ds2 = $this->core->loadClass('DataStore', ['entity_name' => 'User']);
// $ds1 === $ds2 (same instance)

$ds3 = $this->core->loadClass('DataStore', ['entity_name' => 'Order']);
// $ds3 !== $ds1 (different instance)
```

### Environment Check

```php
if ($this->core->is->development()) {
    // Local development
}

if ($this->core->is->production()) {
    // Production environment
}

if ($this->core->is->terminal()) {
    // Running as CLI script
}
```

---

## Common Patterns

### CRUD API Endpoint

```php
public function ENDPOINT_users() {
    $this->sendCorsHeaders('GET,POST,PUT,DELETE');

    switch ($this->requestMethod) {
        case 'GET':
            return $this->getUsers();
        case 'POST':
            return $this->createUser();
        case 'PUT':
            return $this->updateUser();
        case 'DELETE':
            return $this->deleteUser();
    }
}

private function getUsers() {
    $id = $this->params[1] ?? null;
    $ds = $this->core->loadClass('DataStore', ['entity_name' => 'User']);

    if ($id) {
        $user = $ds->read(['id' => $id]);
        if ($ds->error) return $this->setErrorFromCodelib('not-found', 'User not found');
        return $this->addReturnData($user);
    }

    $users = $ds->fetchByQuery([], 100);
    $this->addReturnData($users);
}

private function createUser() {
    if (!$this->checkMandatoryFormParams(['email', 'name'])) return;

    $ds = $this->core->loadClass('DataStore', ['entity_name' => 'User']);
    $id = $ds->create([
        'email' => $this->formParams['email'],
        'name' => $this->formParams['name'],
        'status' => 'active'
    ]);

    if ($ds->error) return $this->setErrorFromCodelib('datastore-error', $ds->errorMsg);

    $this->addReturnData(['id' => $id, 'message' => 'User created']);
}
```

### Script with Multiple Actions

```php
class Script extends Scripts2020 {
    function main() {
        $action = $this->params[0] ?? 'help';

        switch ($action) {
            case 'sync':
                return $this->syncData();
            case 'cleanup':
                return $this->cleanupOldRecords();
            case 'report':
                return $this->generateReport();
            default:
                $this->sendTerminal("Available actions: sync, cleanup, report");
        }
    }

    private function syncData() {
        $this->sendTerminal('Starting sync...');
        // ... logic
        $this->sendTerminal('Sync completed');
    }
}
```

---

## Additional Documentation

For more detailed information, refer to these documents in the framework:

- **CLAUDE.md** - Complete framework internals and architecture
- **CFOs.md** - CloudFramework Objects (business logic layer)
- **CLOUD_DOCUMENTUM.md** - API documentation standards

---

## Conventions Summary

1. **APIs** extend `RESTful`, **Scripts** extend `Scripts2020`
2. Endpoint methods: `ENDPOINT_{name}` (hyphens → underscores)
3. Always check `$class->error` after data operations
4. Use `$this->core->loadClass()` for all framework classes
5. Use namespaces in DataStore for multi-tenancy
6. Configuration hierarchy: framework → app → local_config
7. Use `?__p` for debugging performance issues
