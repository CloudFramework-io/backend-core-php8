# GoogleSecrets Class

## Overview

The `GoogleSecrets` class provides integration with Google Cloud Secret Manager for securely storing and retrieving sensitive configuration data like API keys, passwords, and credentials.

## Loading the Class

```php
$gs = $this->core->loadClass('GoogleSecrets');
```

## Properties

| Property | Type | Description |
|----------|------|-------------|
| `$core` | Core7 | Reference to Core7 instance |
| `$error` | bool | Error flag |
| `$errorMsg` | array | Error messages |
| `$client` | SecretManagerServiceClient | Google Secret Manager client |
| `$projectPath` | string | GCP project path |
| `$project_id` | string | GCP project ID |

---

## Methods

### createSecret()

```php
public function createSecret($secretId): Secret
```

Creates a new secret in Google Cloud Secret Manager.

**Parameters:**
- `$secretId` (string): Secret identifier

**Returns:** Secret object

**Example:**
```php
$gs = $this->core->loadClass('GoogleSecrets');
$secret = $gs->createSecret('my-api-key');
if(!$gs->error) {
    // Secret created successfully
}
```

---

### getSecret()

```php
public function getSecret($secretId, $version = 'latest'): string
```

Retrieves a secret value from Google Cloud Secret Manager.

**Parameters:**
- `$secretId` (string): Secret identifier
- `$version` (string): Secret version (default: 'latest')

**Returns:** Secret value as string

**Example:**
```php
$gs = $this->core->loadClass('GoogleSecrets');
$apiKey = $gs->getSecret('stripe-api-key');

if($gs->error) {
    $this->core->logs->add('Failed to get secret: ' . json_encode($gs->errorMsg));
} else {
    // Use $apiKey
}

// Get specific version
$oldApiKey = $gs->getSecret('stripe-api-key', '1');
```

---

## Configuration

The class requires a GCP project ID from one of these sources (in order of priority):

1. `core.gcp.secrets.project_id` config variable
2. `core.gcp.project_id` config variable
3. `PROJECT_ID` environment variable

**config.json:**
```json
{
  "core.gcp.secrets.project_id": "my-project-id"
}
```

---

## Common Usage Patterns

### Loading API Keys

```php
class API extends RESTful
{
    function main()
    {
        // Load secret
        $gs = $this->core->loadClass('GoogleSecrets');
        $apiKey = $gs->getSecret('stripe-api-key');

        if($gs->error) {
            return $this->setErrorFromCodelib('config-error', 'Failed to load API key');
        }

        // Use API key
        \Stripe\Stripe::setApiKey($apiKey);
    }
}
```

### Loading Database Credentials

```php
$gs = $this->core->loadClass('GoogleSecrets');
$dbPassword = $gs->getSecret('database-password');

if(!$gs->error) {
    $pdo = new PDO("mysql:host=localhost;dbname=mydb", "user", $dbPassword);
}
```

### Caching Secrets

```php
// Cache secret for performance
$apiKey = $this->core->cache->get('stripe_api_key');
if(!$apiKey) {
    $gs = $this->core->loadClass('GoogleSecrets');
    $apiKey = $gs->getSecret('stripe-api-key');

    if(!$gs->error) {
        // Cache for 1 hour
        $this->core->cache->set('stripe_api_key', $apiKey);
    }
}
```

---

## Error Handling

```php
$gs = $this->core->loadClass('GoogleSecrets');

if($gs->error) {
    // Error during initialization (missing project ID)
    $this->core->logs->add('GoogleSecrets init error: ' . json_encode($gs->errorMsg));
    return;
}

$secret = $gs->getSecret('my-secret');

if($gs->error) {
    // Error retrieving secret
    $this->core->logs->add('Secret retrieval error: ' . json_encode($gs->errorMsg));
}
```

---

## See Also

- [CoreSecurity Class Reference](CoreSecurity.md)
- [CoreConfig Class Reference](CoreConfig.md)
- [GCP Integration Guide](../guides/gcp-integration.md)
- [Security Best Practices](../guides/security.md)
