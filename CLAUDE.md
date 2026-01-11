# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Overview

CloudFramework PHP8 Core Framework (v8.4.5) - A backend framework for developing APIs and scripts optimized for Google Cloud Platform (App Engine, Cloud Functions, Compute Engine, Kubernetes). The framework provides a service-locator pattern with Core7 as the central hub, supporting multiple data backends (Datastore, SQL, BigQuery), caching strategies, and GCP-native integrations.

## Development Commands

### Local Development
```bash
# Start local development server (uses dispatcher.php)
composer serve
# Runs: php -S 0.0.0.0:8080 vendor/cloudframework-io/backend-core-php8/src/dispatcher.php
# Access APIs at: http://localhost:8080/

# Clean local cache
composer clean
# Removes ./local_data/cache/* and recreates directory structure

# Setup development environment (configures config.json, .zshrc aliases)
composer setup
# Interactive setup for GCP project_id, datastore, bigquery, storage, and ERP platform

# Setup GCP credentials for local development
composer credentials
# Runs: gcloud auth application-default login
# Moves credentials to local_data/application_default_credentials.json
# Remember to add to config.json: "core.gcp.credentials":"{{documentRoot}}/local_data/application_default_credentials.json"
```

### Running Scripts
```bash
# Execute a script (scripts starting with _ are framework scripts)
composer script -- {script_name}[/{param1}/{param2}] [?formParam=value]
# Example: composer script -- _setup
# Example: composer script -- users/create?name=John

# Create new script template
composer create-script

# Create new API template
composer create-api
```

### Testing
```bash
# Download/update test suite from CloudFramework platform
php runtest.php update

# Run tests
php runtest.php
```

## Core Architecture

### Core7 Service Locator Pattern

The `Core7` class (src/Core7.php) is injected into all APIs and scripts as `$this->core` or `$core`. It provides access to subsystems:

```php
$core->__p              // CorePerformance - performance tracking
$core->is               // CoreIs - environment detection (dev/prod/terminal)
$core->system           // CoreSystem - file paths, URLs, IP info
$core->logs             // CoreLog - development logging
$core->errors           // CoreLog - error collection
$core->config           // CoreConfig - JSON configuration with inheritance
$core->session          // CoreSession - 180min timeout, namespace support
$core->security         // CoreSecurity - auth, tokens, Google credentials
$core->cache            // CoreCache - multi-backend (memory/file/datastore/redis)
$core->request          // CoreRequest - HTTP client for external calls
$core->user             // CoreUser - user authentication info
$core->localization     // CoreLocalization - i18n support
$core->model            // CoreModel - model schema management
$core->cfiLog           // CFILog - custom logging
$core->api              // RESTful - set when dispatching to APIs
```

### Lazy Class Loading with Caching

Classes are instantiated once per unique configuration and cached in memory:

```php
$ds = $core->loadClass('DataStore', ['entity_name' => 'User', 'namespace' => 'default']);
// Cached by MD5(class_name + json_encode(params))
// Subsequent calls with same params return cached instance
```

Available classes in `src/class/`:
- **DataStore** - Google Cloud Datastore (NoSQL)
- **DataSQL** - SQL databases (MySQL/PostgreSQL) with model-driven queries
- **DataBQ** - Google BigQuery data warehouse
- **CloudSQL** - Low-level MySQL connection wrapper
- **CFOs** - CloudFramework Objects integration layer
- **Buckets** - Google Cloud Storage operations
- **GoogleSecrets** - Google Cloud Secret Manager integration
- **DataValidation** - Input validation utilities
- **Email** - Email sending (via GCP or external services)
- **GoogleDocuments** - Google Docs/Sheets API integration
- **CFA**, **CFI** - CloudFramework Platform integrations

## API Development

### API Entry Point and Dispatch

HTTP requests → `src/dispatcher.php` → `$core->dispatch()` routes to:
- `/_*` APIs → `/src/api/{name}.php` (framework core APIs)
- `/queue` APIs → `/src/api/queue.php` (Cloud Tasks queue handler)
- User APIs → `{app_path}/api/{name}.php` (your application APIs)
- Bucket APIs → `{extra_path}/api/{name}.php` (external/shared APIs)

### API Structure Pattern

```php
<?php
// api/myapi.php
class API extends RESTful {
    function main() {
        // Access URL segments: $this->params[0], $this->params[1], ...
        // Access form data: $this->formParams['key']

        $this->end_point = $this->params[0] ?? 'default';
        $this->useFunction('ENDPOINT_'.str_replace('-','_',$this->end_point));
    }

    public function ENDPOINT_default() {
        // Return JSON data
        $this->addReturnData(['message' => 'Hello World']);
    }

    public function ENDPOINT_users() {
        // Access data layer
        $ds = $this->core->loadClass('DataStore', ['entity_name' => 'User']);
        $users = $ds->fetchAll();
        $this->addReturnData($users);
    }
}
```

**Key Conventions:**
- All APIs extend `RESTful` base class
- Implement `main()` method (entry point)
- Use `ENDPOINT_{name}()` methods for routing (hyphens converted to underscores)
- `$this->params` = URL segments after API name
- `$this->formParams` = merged GET/POST/JSON input
- Auto-parses JSON from `php://input`
- Return data via `$this->addReturnData($data)` or `$this->setError($message, $code)`

**RESTful Helper Methods:**
- `checkMethod($methods)` - Validate HTTP method (e.g., 'GET,POST')
- `sendCorsHeaders($methods)` - Send CORS headers for cross-origin requests
- `checkMandatoryFormParams($fields)` - Validate required input fields (array or single field)
- `validateValue($field, $value, $type, $validation)` - Validate data against type/rules
- `setErrorFromCodelib($code, $extra)` - Set error from framework error codes
- `useFunction($method_name)` - Call method if exists, return false if not found

### Standard Response Format

**Success Response:**
```json
{
    "success": true,
    "status": 200,
    "code": "ok",
    "time_zone": "UTC",
    "data": { /* your data */ },
    "logs": [ /* development logs */ ]
}
```

**Error Response:**
```json
{
    "success": false,
    "status": 400,
    "code": "params-error",
    "time_zone": "UTC",
    "errorMsg": ["Validation failed", "Email is required"],
    "data": [],
    "logs": []
}
```

**Error Handling Pattern:**
```php
// Set error manually
$this->setError('Invalid input', 400);

// Add multiple error messages
$this->errorMsg[] = 'Email is required';
$this->errorMsg[] = 'Password too short';

// Use framework error codes
$this->setErrorFromCodelib('not-found', 'User not found');
$this->setErrorFromCodelib('params-error', 'Missing required fields');

// Check errors from data layer
$ds = $this->core->loadClass('DataStore', ['entity_name' => 'User']);
if($ds->error) {
    return $this->setErrorFromCodelib('datastore-error', $ds->errorMsg);
}
```

## Data Layer Architecture

All data classes follow a **three-layer pattern**:
1. **Public interface** - CRUD operations (`read()`, `create()`, `update()`, `delete()`)
2. **Query builder** - Chainable methods (`where()`, `limit()`, `order()`)
3. **Model transformer** - Converts DB records to application objects

### DataStore (Google Cloud Datastore - NoSQL)

```php
$ds = $core->loadClass('DataStore', [
    'entity_name' => 'User',
    'namespace' => 'default',  // Multi-tenancy support
    'model' => [
        'KeyID' => 'key',
        'email' => 'email',
        'status' => 'string',
        'created' => 'datetime'
    ]
]);

// CRUD operations
$entity = $ds->read(['id' => 123]);
$ds->create($model_data);
$ds->update($model_data);
$ds->delete(['id' => 123]);

// Query building
$results = $ds->fetchByQuery(['status' => 'active'], 10);

// Advanced query patterns
$ds->fetchByQuery(['email' => 'user%'], 10);           // LIKE search (starts with)
$ds->fetchByQuery(['age' => '__notnull__'], 10);       // Not null check
$ds->fetchByQuery(['deleted' => '__null__'], 10);      // Null check
$ds->fetchByQuery(['created' => ['>', '2024-01-01']], 10);  // Comparison operators
```

**Important:**
- Uses REST transport by default for stability (GRPC available but may cause issues)
- Built-in caching with optional encryption
- Namespace for multi-tenancy
- Lazy-loads entities with transformation

**Model Field Types and Validation:**
```php
'model' => [
    'KeyId' => 'key',                           // Auto-generated numeric key
    'KeyName' => 'keyname',                     // Custom string key
    'email' => ['string', 'required|email|unique'],
    'age' => ['integer', 'required|min:18|max:100'],
    'status' => ['string', 'values:active,inactive,pending'],
    'profile' => ['json', ''],                   // Auto-serialized JSON
    'notes' => ['zip', ''],                      // Compressed text
    'location' => ['geo', ''],                   // Geopoint (lat,long)
    'created' => ['datetime', 'index'],          // With timezone conversion
    'tags' => ['list', ''],                      // Array values
]
```

**Validation Rules:**
- `required` - Field cannot be null/empty
- `email` - Email format validation
- `unique` - Enforce uniqueness in entity
- `index` - Create Datastore index
- `min:N` / `max:N` - Value range
- `minlength:N` / `maxlength:N` - String length
- `values:a,b,c` - Allowed enum values
- `internal` - Cannot be set via API (auto-set only)

### DataSQL (MySQL/PostgreSQL with Model-Driven Queries)

```php
$sql = $core->loadClass('DataSQL', [
    'table_name',
    [
        'model' => [
            'id' => 'int',
            'name' => 'char:50',
            'email' => 'char:100',
            'status' => 'char:20'
        ],
        'mapping' => [
            'id' => 'user_id',          // Internal name => DB column name
            'name' => 'full_name'
        ]
    ]
]);

// Query building (chainable)
$results = $sql->select(['id', 'name', 'email'])
    ->where('status', '=', 'active')
    ->where('created', '>', '2024-01-01')
    ->limit(10)
    ->order('created', 'DESC')
    ->fetch();

// JOIN support (chainable with other DataSQL instances)
$addressSql = $core->loadClass('DataSQL', ['addresses', ['model' => [...]]]);
$sql->join('left', $addressSql, 'id', 'user_id');  // LEFT JOIN
$sql->join('inner', $addressSql, 'id', 'user_id'); // INNER JOIN

// Get SQL and params (for debugging)
list($sql_query, $params) = $sql->getQuerySQLWhereAndParams();
```

**Important:**
- Model-driven with field type detection (SQL types: `int(11)`, `varchar(100)`, `text`, `datetime`)
- Mapping layer (internal field names → database column names)
- Virtual fields and GROUP BY support
- Multi-connection support via `$core->config->get('core.db.connections')`

**SQL Model Field Types:**
```php
'model' => [
    'id' => ['int(11)', 'isKey'],                    // Primary key
    'name' => ['varchar(100)', 'required'],          // String field
    'email' => ['varchar(255)', 'required|email'],   // Validated email
    'bio' => ['text', ''],                           // Long text
    'age' => ['int(3)', 'min:18'],                   // Integer with validation
    'balance' => ['decimal(10,2)', ''],              // Decimal numbers
    'created' => ['datetime', 'internal'],           // Timestamp
    'status' => ['enum(active,inactive)', 'required']// Enum type
]
```

### DataBQ (Google BigQuery)

```php
$bq = $core->loadClass('DataBQ', [
    'dataset.table',
    [
        'model' => [
            'id' => 'INTEGER',
            'name' => 'STRING',
            'timestamp' => 'TIMESTAMP'
        ]
    ]
]);

// Streaming inserts
$bq->insert(['id' => 1, 'name' => 'John', 'timestamp' => time()]);

// Query execution
$results = $bq->query('SELECT * FROM dataset.table WHERE id > ?', [100]);
```

**Important:**
- Streaming inserts with exponential backoff retry
- Query result caching for performance
- Schema evolution support

### CloudSQL (Low-Level MySQL Operations)

```php
$db = $core->loadClass('CloudSQL');
$db->connect(['host' => 'localhost', 'user' => 'root', 'password' => '', 'db' => 'mydb']);

$results = $db->getDataFromQuery('SELECT * FROM users WHERE status = ?', ['active']);
$db->execute('UPDATE users SET status = ? WHERE id = ?', ['inactive', 123]);
```

## Configuration System

### Hierarchical Configuration Loading

1. Load `/src/config.json` (framework defaults)
2. Load `{app_path}/config.json` (application config)
3. Load `local_config.json` if in development (overrides, gitignored)
4. Support includes: `"include:":"{{rootPath}}/config.json"`
5. Variable substitution: `{{rootPath}}`, `{{dev}}`, `{{production}}`

### Environment-Specific Configuration

```json
{
  "default-value": "always loaded",
  "development": {
    "override": "only when developing"
  },
  "production": {
    "override": "only in production"
  }
}
```

### Key Configuration Variables

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
  "core.erp.platform_id": "optional-platform-id"
}
```

## Caching Strategy

### Multi-Backend Cache System

```php
// Development: File-based cache
$core->cache->activateCacheFile('./local_data/cache');

// Production: Datastore-backed
$core->cache->activateDataStore();

// Usage
$core->cache->set('key', $value, 3600);  // TTL in seconds
$cached = $core->cache->get('key');
$core->cache->delete('key');
```

**Cache Backend Selection:**
- **Development** → File cache (`core.cache.cache_path`)
- **Production** → Datastore cache (entity: `CloudFrameworkCache`)
- **Memory** → In-process volatile cache (no persistence)

**Features:**
- Namespace support (multi-tenancy)
- Optional encryption via `$cache_secret_key` and `$cache_secret_iv`
- Automatic compression (gzip)

## Script Development

### Script Execution Pattern

```bash
php runscript.php {script_name}[/{param1}/{param2}] [?formParam=value] [--options]
```

Script locations:
- `/scripts/_*.php` → Framework scripts (prefix with `_`)
- `{app_path}/scripts/*.php` → Your application scripts

### Script Structure

```php
<?php
// scripts/mytest.php
class Script extends Scripts2020 {
    function main() {
        // Access Core7
        $this->core->logs->add('Starting script');

        // Access params
        $param1 = $this->params[0] ?? 'default';
        $formParam = $this->formParams['key'] ?? null;

        // Use data layers
        $ds = $this->core->loadClass('DataStore', ['entity_name' => 'User']);
        $users = $ds->fetchAll();

        // Output
        $this->sendOutput('Script completed', $users);
    }
}
```

**Available in Scripts:**
- `$this->core` - Full Core7 access
- `$this->params` - CLI path segments
- `$this->formParams` - CLI query parameters
- `$this->sendOutput($message, $data)` - Structured output
- `$this->sendTerminal($message)` - Terminal output

## Performance Tracking

Enable performance tracking in development:

```php
$core->__p->add('Description', 'context', 'note|endnote');
```

Access via:
- APIs: Add `?__p` query parameter
- Scripts: Add `?__p` or `--p` flag
- Shows nested call stacks with timing information

## Security Patterns

### Authentication

```php
// GCP service account email
$email = $core->security->getGoogleEmailAccount();

// HTTP Basic Auth
if ($core->security->existBasicAuth()) {
    $user = $core->security->getBasicAuthUser();
    $pass = $core->security->getBasicAuthPassword();
}

// Custom token headers
$token = $core->request->getHeader('X-DS-TOKEN');
```

### ERP Integration

If `core.erp.platform_id` is configured, the framework automatically:
1. Validates gcloud auth is active
2. Caches service account email
3. Uses email for audit trails

## GCP Integration Features

### Stream Wrapper for Cloud Storage

```php
// Enable gs:// protocol support
if ($core->config->get('core.datastorage.on')) {
    $core->gc_datastorage_client->registerStreamWrapper();
}

// Now use native PHP file functions
$contents = file_get_contents('gs://bucket-name/file.txt');
file_put_contents('gs://bucket-name/output.txt', $data);
```

### Datastore Transport Selection

Add `?_fix_datastore_transport` to force REST transport if GRPC fails.

### Google Secret Manager

```php
$secrets = $core->loadClass('GoogleSecrets');

// Create a new secret (creates the secret container, no data yet)
$secrets->createSecret('my-secret-id');

// Add a secret version with actual data (you can add multiple versions)
$secrets->addSecretVersion('my-secret-id', 'my-secret-value');

// Update a secret by adding a new version
$secrets->addSecretVersion('my-secret-id', 'updated-secret-value');

// Get secret value (retrieves the latest version by default)
$value = $secrets->getSecret('my-secret-id', 'latest');  // 'latest' is default
$value = $secrets->getSecret('my-secret-id', '1');       // Get specific version

// List all versions of a specific secret
$versions = $secrets->getSecretVersions('my-secret-id');
// Returns: [
//   ['version' => '1', 'state' => 1, 'create_time' => '2024-01-15 10:30:00', 'name' => '...'],
//   ['version' => '2', 'state' => 1, 'create_time' => '2024-02-20 14:45:00', 'name' => '...']
// ]

// List all secret IDs in the project
$secretIds = $secrets->listSecrets();
// Returns: ['secret-1', 'secret-2', 'my-secret-id', ...]

// Get IAM access list for a secret (who has access and their roles)
$accessList = $secrets->getSecretAccessList('my-secret-id');
// Returns: [
//   ['role' => 'roles/secretmanager.secretAccessor', 'members' => ['user:john@example.com', 'serviceAccount:app@project.iam.gserviceaccount.com']],
//   ['role' => 'roles/secretmanager.admin', 'members' => ['user:admin@example.com']]
// ]

// Grant access to a secret (single user with default secretAccessor role)
$secrets->grantSecretAccess('my-secret-id', 'user:developer@example.com');

// Grant access to multiple users/service accounts
$secrets->grantSecretAccess('my-secret-id', [
    'user:developer1@example.com',
    'user:developer2@example.com',
    'serviceAccount:backend-app@my-project.iam.gserviceaccount.com'
]);

// Grant admin access (specify custom role)
$secrets->grantSecretAccess('my-secret-id', 'user:admin@example.com', 'roles/secretmanager.admin');

// Revoke access from a single user
$secrets->revokeAccess('my-secret-id', 'user:developer@example.com');

// Revoke access from multiple users
$secrets->revokeAccess('my-secret-id', [
    'user:former-employee@example.com',
    'serviceAccount:old-app@my-project.iam.gserviceaccount.com'
]);

// Revoke admin access (specify custom role)
$secrets->revokeAccess('my-secret-id', 'user:former-admin@example.com', 'roles/secretmanager.admin');

// Delete a secret (permanently deletes the secret and all its versions)
$success = $secrets->deleteSecret('my-secret-id');
if($success) {
    echo "Secret deleted successfully";
}

// Check for errors
if($secrets->error) {
    $this->core->logs->add('Error: ' . json_encode($secrets->errorMsg));
}
```

**Typical Workflow:**
1. `createSecret($secretId)` - Create the secret container
2. `addSecretVersion($secretId, $data)` - Add the actual secret data (can be called multiple times for versioning)
3. `grantSecretAccess($secretId, $members, $role)` - Grant access to users/service accounts (optional)
4. `getSecret($secretId, $version)` - Retrieve the secret value
5. `getSecretAccessList($secretId)` - Audit who has access (optional)
6. `deleteSecret($secretId)` - Delete when no longer needed

**Configuration:**
- Uses `core.gcp.project_id` or `core.gcp.secrets.project_id` config
- Automatic service account authentication in GCP
- For local development, ensure `GOOGLE_APPLICATION_CREDENTIALS` is set

**Important:**
- `createSecret()` creates an empty secret container - you must call `addSecretVersion()` to store actual data
- `addSecretVersion()` creates a new version each time it's called (versioning for secret rotation)
- `getSecretVersions()` returns version metadata including state (1=ENABLED, 2=DISABLED, 3=DESTROYED)
- `grantSecretAccess()` adds members to IAM roles (requires `secretmanager.secrets.setIamPolicy` permission)
- `grantSecretAccess()` preserves existing members - it only adds new ones
- `revokeAccess()` removes members from IAM roles (requires `secretmanager.secrets.setIamPolicy` permission)
- `revokeAccess()` removes the entire role binding if all members are revoked
- `deleteSecret()` permanently deletes the secret and ALL of its versions
- This operation cannot be undone

**Version States:**
- `1` (ENABLED) - Version is active and can be accessed
- `2` (DISABLED) - Version is disabled and cannot be accessed
- `3` (DESTROYED) - Version is scheduled for destruction

**IAM Roles for Secret Manager:**
- `roles/secretmanager.admin` - Full access to manage secrets and IAM policies
- `roles/secretmanager.secretAccessor` - Read access to secret payloads
- `roles/secretmanager.secretVersionManager` - Create and manage secret versions
- `roles/secretmanager.viewer` - View secret metadata (not the secret values)

**IAM Member Types:**
- `user:email@example.com` - Individual user account
- `serviceAccount:name@project.iam.gserviceaccount.com` - Service account
- `group:group@example.com` - Google Group
- `domain:example.com` - All users in a domain
- `allUsers` - Anyone on the internet (public)
- `allAuthenticatedUsers` - Any authenticated Google account

## Deployment

### Google App Engine

Uses `app.yaml` (see `install/app-dist.yaml`):
- Runtime: `php81` (or newer)
- Entrypoint: `vendor/cloudframework-io/backend-core-php8/src/dispatcher.php`
- Static file handlers for images, CSS, JS
- Automatic credential detection via service account

### Cloud Functions / Compute Engine

- Same dispatch mechanism as App Engine
- Configure via `config.json` or environment variables
- Service account authentication automatic

### Environment Detection

```php
$core->is->development()   // localhost or dev environment
$core->is->production()    // !development
$core->is->terminal()      // CLI execution
```

## Important Patterns

### Service Locator with Lazy Loading

Classes instantiated once per unique configuration, cached by MD5 hash:
```php
$data = $core->loadClass('DataStore', $params);  // Cached instance
```

### Configuration-Driven Dispatch

- `core.api.urls` → API route paths
- `core.api.extra_path` → External API source
- `core.db.proxy` → Database proxy configuration

### Multi-Tenancy via Namespaces

- DataStore entities: `namespace` parameter
- Cache keys: `spacename` scoping
- Session data: namespace support

### Graceful Degradation

- Missing data layer → logs error, continues
- Missing API file → 404 with helpful error
- Missing credentials → exits with setup instructions

## Framework APIs Reference

The framework provides built-in APIs (accessed via `/_*` prefix):
- `/_ah` - App Engine health checks
- `/_dbproxy` - Database proxy with token auth
- `/_dsproxy` - Datastore proxy operations
- `/_config` - Configuration inspection
- `/_crypt` - Encryption/decryption utilities
- `/_google` - Google API integrations
- `/_pubsub` - Pub/Sub operations
- `/_eval` - Dynamic code evaluation (dev only)
- `/queue` - Cloud Tasks queue handler (special routing)

## Git Workflow

Current branch: `development`
Main branch: `main`

Recent commits follow pattern:
```
[task:{task_id}] Description of changes
[check:{check_id}][task:{task_id}] Description with check reference
```

## File Structure

```
.
├── src/
│   ├── Core7.php              # Central service locator (418KB, 10K lines)
│   ├── class/                 # Data layer and utility classes
│   │   ├── DataStore.php      # Google Cloud Datastore
│   │   ├── DataSQL.php        # SQL databases
│   │   ├── DataBQ.php         # BigQuery
│   │   ├── CFOs.php           # CloudFramework Objects
│   │   └── ...
│   ├── api/                   # Framework core APIs
│   ├── dispatcher.php         # API request dispatcher
│   └── config.json            # Framework default config
├── cloudia/                   # CLOUDIA documentation system
│   ├── CLOUDIA.md             # CLOUDIA overview and conventions
│   ├── CLOUD_DOCUMENTUM.md    # CLOUD Documentum documentation
│   ├── CFOs.md                # CloudFramework Objects documentation
│   └── cfos/                  # CFO JSON definitions for CLOUD Documentum
│       ├── CloudFrameWorkCFOs.json
│       ├── CloudFrameWorkDevDocumentation.json
│       ├── CloudFrameWorkDevDocumentationForAPIs.json
│       ├── CloudFrameWorkDevDocumentationForAPIEndPoints.json
│       ├── CloudFrameWorkDevDocumentationForLibraries.json
│       ├── CloudFrameWorkDevDocumentationForProcesses.json
│       ├── CloudFrameWorkDevDocumentationForProcessTests.json
│       ├── CloudFrameWorkDevDocumentationForWebApps.json
│       ├── CloudFrameWorkECMPages.json
│       ├── CloudFrameWorkInfrastructureResources.json
│       ├── CloudFrameWorkInfrastructureResourcesAccesses.json
│       ├── CloudFrameWorkModules.json
│       └── ...
├── scripts/                   # Framework scripts (_*.php)
│   └── _cloudia/              # CLOUDIA backup/sync scripts
│       ├── apis.php           # API documentation CRUD
│       ├── auth.php           # Authentication utilities
│       ├── cfos.php           # CFO definitions CRUD
│       ├── checks.php         # Checks documentation CRUD
│       ├── courses.php        # Courses documentation CRUD
│       ├── libraries.php      # Libraries documentation CRUD
│       ├── menu.php           # Menu modules CRUD
│       ├── processes.php      # Processes documentation CRUD
│       ├── resources.php      # Infrastructure resources CRUD
│       ├── webapps.php        # WebApps documentation CRUD
│       └── webpages.php       # WebPages documentation CRUD
├── install/                   # Installation templates
│   ├── api-dist/              # Example API templates
│   ├── scripts-dist/          # Example script templates
│   ├── composer-dist.json     # Composer template
│   └── config-dist.json       # Config template
├── runscript.php              # Script execution entry point
├── runtest.php                # Test suite runner
├── runapi.php                 # API testing/mocking tool
└── install.php                # First-time installation script
```

## Development Tips

1. **Local Development Config:** Use `local_config.json` (gitignored) to override config without modifying `config.json`

2. **Debugging APIs:** Add `?__p` to see performance logs and execution trace

3. **Datastore Namespace:** Use namespaces for multi-tenancy in development to avoid data conflicts

4. **Cache Clearing:** Run `composer clean` to clear file cache when testing cache-dependent features

5. **Script Testing:** Use `?__p` flag to see detailed execution logs: `composer script -- myscript?__p`

6. **Database Proxy:** Use `/_dbproxy` API for secure database access from external systems with token authentication

7. **ERP Integration:** If using CloudFramework platform, ensure `gcloud auth login` is active for script execution

8. **Model-Driven Development:** Always define models in data layer classes for type safety and field mapping

9. **Error Handling:** Check `$core->errors->data` after operations to detect failures

10. **Stream Wrapper:** Enable Cloud Storage stream wrapper to use `gs://` URLs with native PHP file functions

## CLOUDIA Documentation System

The `cloudia/` directory contains documentation and CFO definitions for the CLOUDIA platform:

### Documentation Files

| File | Description |
|------|-------------|
| `cloudia/CLOUDIA.md` | CLOUDIA overview, conventions, and architecture |
| `cloudia/CLOUD_DOCUMENTUM.md` | CLOUD Documentum documentation system (APIs, Processes, Libraries, etc.) |
| `cloudia/CFOs.md` | CloudFramework Objects (CFOs) business logic layer documentation |

### CFO Definitions

The `cloudia/cfos/` directory contains JSON definitions for CLOUD Documentum entities:

| CFO | Description |
|-----|-------------|
| `CloudFrameWorkCFOs` | Master CFO definitions management |
| `CloudFrameWorkDevDocumentation` | Development groups |
| `CloudFrameWorkDevDocumentationForAPIs` | API documentation |
| `CloudFrameWorkDevDocumentationForAPIEndPoints` | API endpoints |
| `CloudFrameWorkDevDocumentationForLibraries` | Code libraries |
| `CloudFrameWorkDevDocumentationForLibrariesModules` | Library functions/methods |
| `CloudFrameWorkDevDocumentationForProcesses` | Business processes |
| `CloudFrameWorkDevDocumentationForSubProcesses` | Sub-processes |
| `CloudFrameWorkDevDocumentationForProcessTests` | Checks and tests |
| `CloudFrameWorkDevDocumentationForWebApps` | Web applications |
| `CloudFrameWorkDevDocumentationForWebAppsModules` | WebApp modules |
| `CloudFrameWorkECMPages` | ECM content pages |
| `CloudFrameWorkInfrastructureResources` | Infrastructure resources (servers, computers, domains) |
| `CloudFrameWorkInfrastructureResourcesAccesses` | Access permissions and credentials for resources |
| `CloudFrameWorkModules` | Menu modules for different platform solutions |

For detailed CFO structure documentation, see `cloudia/CLOUD_DOCUMENTUM.md`.

## CLOUDIA Scripts (_cloudia)

The `scripts/_cloudia/` directory contains scripts for managing CLOUD Documentum content:

### Available Scripts

> **Note:** The platform is automatically determined from `core.erp.platform_id` in your config.json file.

```bash
# APIs - Manage API documentation
composer script -- _cloudia/apis/list-remote              # List remote APIs
composer script -- _cloudia/apis/list-local               # List local backups
composer script -- _cloudia/apis/backup-from-remote       # Backup all APIs
composer script -- "_cloudia/apis/backup-from-remote?id=/api/path" # Backup specific API
composer script -- "_cloudia/apis/insert-from-backup?id=/api/path" # Insert from backup
composer script -- "_cloudia/apis/update-from-backup?id=/api/path" # Update from backup

# Libraries - Manage library documentation
composer script -- _cloudia/libraries/list-remote
composer script -- _cloudia/libraries/backup-from-remote
composer script -- "_cloudia/libraries/backup-from-remote?id=/path/to/library"

# Processes - Manage process documentation
composer script -- _cloudia/processes/list-remote
composer script -- _cloudia/processes/backup-from-remote
composer script -- "_cloudia/processes/backup-from-remote?id=PROCESS-ID"

# Checks - Manage checks/tests
composer script -- _cloudia/checks/list-remote
composer script -- "_cloudia/checks/backup-from-remote?entity=CFOEntity&id=CFOId"

# WebApps - Manage webapp documentation
composer script -- _cloudia/webapps/list-remote
composer script -- _cloudia/webapps/backup-from-remote

# Courses - Manage academy courses
composer script -- _cloudia/courses/list-remote
composer script -- _cloudia/courses/backup-from-remote

# WebPages - Manage ECM pages
composer script -- _cloudia/webpages/list-remote
composer script -- _cloudia/webpages/backup-from-remote

# CFOs - Manage CFO definitions
composer script -- _cloudia/cfos/list-remote
composer script -- _cloudia/cfos/backup-from-remote
composer script -- "_cloudia/cfos/backup-from-remote?id=CFOKeyName"

# Resources - Manage infrastructure resources
composer script -- _cloudia/resources/list-remote
composer script -- _cloudia/resources/backup-from-remote

# Menu - Manage menu modules
composer script -- _cloudia/menu/list-remote
composer script -- _cloudia/menu/backup-from-remote

# Auth - Authentication utilities
composer script -- _cloudia/auth/info          # Show auth info
composer script -- _cloudia/auth/token         # Get/refresh token
```

### Script Parameters

| Parameter | Description |
|-----------|-------------|
| `id` | Entity identifier (KeyName for APIs/Libraries/Processes, KeyId for others) |
| `entity` | CFO entity name (for Checks) |

### Example Usage

```bash
# Backup all APIs
composer script -- _cloudia/apis/backup-from-remote

# Backup specific API with its endpoints
composer script -- "_cloudia/apis/backup-from-remote?id=/erp/projects"

# List all libraries
composer script -- _cloudia/libraries/list-remote

# Backup checks linked to a process
composer script -- "_cloudia/checks/backup-from-remote?entity=CloudFrameWorkDevDocumentationForProcesses&id=/cloud-hrms"
```
