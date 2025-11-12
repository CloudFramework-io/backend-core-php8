# Configuration Guide

## Overview

CloudFramework uses a flexible JSON-based configuration system that supports:
- Environment-specific settings (development, production)
- Google Cloud Platform integration
- Database connections
- Cache strategies
- Security policies
- API behavior customization

Configuration is loaded from JSON files and can be accessed throughout your application using `$this->core->config`.

---

## Table of Contents

1. [Configuration Files](#configuration-files)
2. [Core Configuration](#core-configuration)
3. [GCP Configuration](#gcp-configuration)
4. [Database Configuration](#database-configuration)
5. [Cache Configuration](#cache-configuration)
6. [API Configuration](#api-configuration)
7. [Security Configuration](#security-configuration)
8. [Environment Variables](#environment-variables)
9. [Accessing Configuration](#accessing-configuration)
10. [Best Practices](#best-practices)

---

## Configuration Files

### File Loading Order

CloudFramework loads configuration files in this order:

1. **`config.json`** - Main configuration file (always loaded)
2. **`local_config.json`** - Local overrides for development (loaded in development mode)
3. **`local_script.json`** - Script-specific overrides (loaded when running CLI scripts in development)

```
project/
├── config.json              # Main configuration (committed to git)
├── local_config.json        # Local overrides (ignored by git)
└── local_script.json        # Script overrides (ignored by git)
```

### Basic config.json Structure

```json
{
  "core": {
    "gcp": {
      "project_id": "my-project-id",
      "service_account": "/path/to/service-account.json"
    },
    "cache": {
      "cache_type": "file"
    }
  },
  "dbServer": "127.0.0.1",
  "dbUser": "root",
  "dbPassword": "password",
  "dbName": "mydatabase",
  "api": {
    "cors": {
      "enabled": true,
      "allowed_origins": ["*"]
    }
  }
}
```

---

## Core Configuration

### Project Settings

```json
{
  "core": {
    "project": {
      "name": "My Application",
      "version": "1.0.0",
      "environment": "production"
    },
    "timezone": "UTC",
    "locale": "en_US"
  }
}
```

**Available Options:**

| Option | Type | Description | Default |
|--------|------|-------------|---------|
| `core.project.name` | string | Application name | - |
| `core.project.version` | string | Application version | - |
| `core.project.environment` | string | Environment (production, development) | auto-detected |
| `core.timezone` | string | Default timezone | UTC |
| `core.locale` | string | Default locale | en_US |

### Root Paths

```json
{
  "core": {
    "root_path": "/var/www/html",
    "app_path": "/var/www/html/app",
    "tmp_path": "/tmp"
  }
}
```

---

## GCP Configuration

### Basic GCP Setup

```json
{
  "core": {
    "gcp": {
      "project_id": "my-gcp-project",
      "service_account": "/path/to/service-account.json",
      "location": "us-central1"
    }
  }
}
```

### Cloud Datastore Configuration

```json
{
  "core": {
    "gcp": {
      "datastore": {
        "namespace": "production",
        "cache_queries": true
      }
    }
  }
}
```

### Cloud Storage Configuration

```json
{
  "core": {
    "gcp": {
      "storage": {
        "default_bucket": "gs://my-bucket",
        "upload_path": "uploads/",
        "public_url": "https://storage.googleapis.com/my-bucket"
      }
    }
  }
}
```

### BigQuery Configuration

```json
{
  "core": {
    "gcp": {
      "bigquery": {
        "dataset": "analytics",
        "location": "US",
        "maximum_bytes_billed": "1000000000"
      }
    }
  }
}
```

### Cloud SQL Configuration

```json
{
  "core": {
    "gcp": {
      "cloudsql": {
        "socket": "/cloudsql/project:region:instance",
        "instance": "project:region:instance"
      }
    }
  }
}
```

### Complete GCP Example

```json
{
  "core": {
    "gcp": {
      "project_id": "my-project-123",
      "service_account": "/app/credentials/service-account.json",
      "location": "us-central1",
      "datastore": {
        "namespace": "production"
      },
      "storage": {
        "default_bucket": "gs://my-app-uploads",
        "upload_path": "uploads/"
      },
      "bigquery": {
        "dataset": "analytics",
        "location": "US"
      },
      "cloudsql": {
        "socket": "/cloudsql/my-project-123:us-central1:main-db"
      }
    }
  }
}
```

---

## Database Configuration

### Cloud SQL / MySQL Configuration

```json
{
  "dbServer": "127.0.0.1",
  "dbUser": "root",
  "dbPassword": "secure_password",
  "dbName": "production_db",
  "dbPort": "3306",
  "dbSocket": "",
  "dbCharset": "utf8mb4"
}
```

**Available Options:**

| Option | Type | Description | Default |
|--------|------|-------------|---------|
| `dbServer` | string | Database host or IP | - |
| `dbUser` | string | Database username | - |
| `dbPassword` | string | Database password | - |
| `dbName` | string | Database name | - |
| `dbPort` | string | Database port | 3306 |
| `dbSocket` | string | Unix socket path (Cloud SQL) | - |
| `dbCharset` | string | Character set | utf8mb4 |

### Cloud SQL Socket Connection (App Engine)

```json
{
  "dbServer": "",
  "dbUser": "root",
  "dbPassword": "password",
  "dbName": "mydb",
  "dbPort": "3306",
  "dbSocket": "/cloudsql/project:region:instance",
  "dbCharset": "utf8mb4"
}
```

### Multiple Database Connections

```json
{
  "databases": {
    "main": {
      "host": "127.0.0.1",
      "user": "app_user",
      "password": "password",
      "database": "app_db"
    },
    "analytics": {
      "host": "127.0.0.1",
      "user": "analytics_user",
      "password": "password",
      "database": "analytics_db"
    }
  }
}
```

**Usage:**

```php
// Main database
$mainDb = $this->core->config->get('databases.main');
$sql = $this->core->loadClass('CloudSQL', [
    $mainDb['host'],
    $mainDb['user'],
    $mainDb['password'],
    $mainDb['database']
]);

// Analytics database
$analyticsDb = $this->core->config->get('databases.analytics');
$analyticsSql = $this->core->loadClass('CloudSQL', [
    $analyticsDb['host'],
    $analyticsDb['user'],
    $analyticsDb['password'],
    $analyticsDb['database']
]);
```

---

## Cache Configuration

### Cache Types

CloudFramework supports three cache types:

1. **File Cache** - Stores cache in filesystem
2. **Memory Cache** - Stores cache in PHP memory (not persistent)
3. **Datastore Cache** - Stores cache in Google Cloud Datastore

```json
{
  "core": {
    "cache": {
      "cache_type": "file",
      "cache_path": "/tmp/cache",
      "default_ttl": 3600
    }
  }
}
```

### File Cache Configuration

```json
{
  "core": {
    "cache": {
      "cache_type": "file",
      "cache_path": "/tmp/cloudframework_cache",
      "default_ttl": 3600,
      "cleanup_probability": 0.01
    }
  }
}
```

**Options:**

| Option | Description | Default |
|--------|-------------|---------|
| `cache_type` | Cache type (file, memory, datastore) | file |
| `cache_path` | Directory for cache files | /tmp/cache |
| `default_ttl` | Default time-to-live in seconds | 3600 |
| `cleanup_probability` | Probability of cleanup on write (0-1) | 0.01 |

### Memory Cache Configuration

```json
{
  "core": {
    "cache": {
      "cache_type": "memory",
      "default_ttl": 3600
    }
  }
}
```

> **Note:** Memory cache is not persistent across requests. Use for single-request caching only.

### Datastore Cache Configuration

```json
{
  "core": {
    "cache": {
      "cache_type": "datastore",
      "cache_namespace": "cache",
      "default_ttl": 3600
    }
  }
}
```

### Environment-Specific Cache

**Production (config.json):**
```json
{
  "core": {
    "cache": {
      "cache_type": "datastore",
      "default_ttl": 3600
    }
  }
}
```

**Development (local_config.json):**
```json
{
  "core": {
    "cache": {
      "cache_type": "file",
      "cache_path": "/tmp/dev_cache"
    }
  }
}
```

---

## API Configuration

### CORS Configuration

```json
{
  "api": {
    "cors": {
      "enabled": true,
      "allowed_origins": ["https://example.com", "https://app.example.com"],
      "allowed_methods": ["GET", "POST", "PUT", "DELETE", "OPTIONS"],
      "allowed_headers": ["Content-Type", "Authorization", "X-API-Key"],
      "allow_credentials": true,
      "max_age": 86400
    }
  }
}
```

**Allow All Origins (Development Only):**

```json
{
  "api": {
    "cors": {
      "enabled": true,
      "allowed_origins": ["*"]
    }
  }
}
```

### API Timeouts

```json
{
  "api": {
    "timeout": 30,
    "max_execution_time": 60
  }
}
```

### Rate Limiting

```json
{
  "api": {
    "rate_limit": {
      "enabled": true,
      "requests_per_minute": 60,
      "requests_per_hour": 1000
    }
  }
}
```

### API Response Format

```json
{
  "api": {
    "response": {
      "pretty_print": false,
      "include_performance": false,
      "date_format": "Y-m-d H:i:s"
    }
  }
}
```

### Complete API Configuration Example

```json
{
  "api": {
    "cors": {
      "enabled": true,
      "allowed_origins": ["https://app.example.com"],
      "allowed_methods": ["GET", "POST", "PUT", "DELETE"],
      "allowed_headers": ["Content-Type", "Authorization"],
      "allow_credentials": true
    },
    "timeout": 30,
    "max_execution_time": 60,
    "rate_limit": {
      "enabled": true,
      "requests_per_minute": 100
    },
    "response": {
      "pretty_print": false,
      "include_performance": true
    }
  }
}
```

---

## Security Configuration

### API Keys

```json
{
  "security": {
    "api_keys": {
      "enabled": true,
      "header_name": "X-API-Key",
      "valid_keys": [
        "key1_production_secret",
        "key2_production_secret"
      ]
    }
  }
}
```

**Usage in API:**

```php
function main()
{
    // Check API key
    $apiKey = $_SERVER['HTTP_X_API_KEY'] ?? '';
    $validKeys = $this->core->config->get('security.api_keys.valid_keys', []);

    if(!in_array($apiKey, $validKeys)) {
        return $this->setError('Invalid API key', 401);
    }

    // Continue with API logic
}
```

### IP Whitelisting

```json
{
  "security": {
    "ip_whitelist": {
      "enabled": true,
      "allowed_ips": [
        "192.168.1.1",
        "10.0.0.0/8",
        "172.16.0.0/12"
      ]
    }
  }
}
```

### Basic Authentication

```json
{
  "security": {
    "basic_auth": {
      "enabled": true,
      "users": {
        "admin": "hashed_password",
        "user": "hashed_password"
      }
    }
  }
}
```

### Encryption Keys

```json
{
  "security": {
    "encryption": {
      "key": "your-32-character-encryption-key",
      "algorithm": "AES-256-CBC"
    }
  }
}
```

**Usage:**

```php
$encrypted = $this->core->security->encrypt(
    'sensitive data',
    $this->core->config->get('security.encryption.key')
);

$decrypted = $this->core->security->decrypt(
    $encrypted,
    $this->core->config->get('security.encryption.key')
);
```

### JWT Configuration

```json
{
  "security": {
    "jwt": {
      "secret": "your-jwt-secret-key",
      "algorithm": "HS256",
      "expiration": 3600,
      "issuer": "my-app.com"
    }
  }
}
```

---

## Environment Variables

### Using Environment Variables

You can use environment variables in configuration files:

**config.json:**
```json
{
  "core": {
    "gcp": {
      "project_id": "${GCP_PROJECT_ID}",
      "service_account": "${GCP_SERVICE_ACCOUNT}"
    }
  },
  "dbPassword": "${DB_PASSWORD}",
  "security": {
    "encryption": {
      "key": "${ENCRYPTION_KEY}"
    }
  }
}
```

**Access in PHP:**
```php
$projectId = $this->core->config->get('core.gcp.project_id');
// Returns value from $_ENV['GCP_PROJECT_ID']
```

### .env File Support

Create a `.env` file in your project root:

```bash
# .env
GCP_PROJECT_ID=my-project-123
GCP_SERVICE_ACCOUNT=/path/to/service-account.json
DB_PASSWORD=secure_password
ENCRYPTION_KEY=your-32-char-key-here
```

### App Engine Environment Variables

**app.yaml:**
```yaml
runtime: php83

env_variables:
  GCP_PROJECT_ID: 'my-project-123'
  DB_PASSWORD: 'production_password'
  CACHE_TYPE: 'datastore'
```

---

## Accessing Configuration

### Get Configuration Values

```php
// Simple value
$projectId = $this->core->config->get('core.gcp.project_id');

// Nested value
$bucket = $this->core->config->get('core.gcp.storage.default_bucket');

// With default value
$timeout = $this->core->config->get('api.timeout', 30);

// Get entire section
$gcpConfig = $this->core->config->get('core.gcp');
```

### Set Configuration Values

```php
// Set single value
$this->core->config->set('api.timeout', 60);

// Set nested value
$this->core->config->set('custom.feature.enabled', true);
```

### Load Additional Config Files

```php
// Load custom configuration file
$this->core->config->readConfigJSONFile('/path/to/custom-config.json');
```

### Check if Configuration Exists

```php
$hasApiKey = $this->core->config->get('security.api_keys.enabled') !== null;

if($hasApiKey) {
    // API key validation enabled
}
```

---

## Best Practices

### 1. Separate Secrets from Code

**Don't:**
```json
{
  "dbPassword": "my_actual_password"
}
```

**Do:**
```json
{
  "dbPassword": "${DB_PASSWORD}"
}
```

### 2. Use Environment-Specific Files

**config.json (committed):**
```json
{
  "api": {
    "cors": {
      "allowed_origins": ["https://production.com"]
    }
  }
}
```

**local_config.json (not committed):**
```json
{
  "api": {
    "cors": {
      "allowed_origins": ["*"]
    }
  }
}
```

### 3. Document Your Configuration

Add a `config.example.json`:

```json
{
  "_comment": "Copy this file to config.json and update values",
  "core": {
    "gcp": {
      "project_id": "your-project-id",
      "service_account": "/path/to/service-account.json"
    }
  },
  "dbPassword": "your-database-password"
}
```

### 4. Validate Configuration on Startup

```php
class API extends RESTful
{
    function main()
    {
        // Validate required configuration
        $required = [
            'core.gcp.project_id',
            'dbServer',
            'dbName'
        ];

        foreach($required as $key) {
            if($this->core->config->get($key) === null) {
                return $this->setError("Missing required configuration: {$key}", 500);
            }
        }

        // Continue with API logic
    }
}
```

### 5. Use Consistent Naming

```json
{
  "feature_name": {
    "enabled": true,
    "option_one": "value",
    "option_two": "value"
  }
}
```

### 6. Cache Configuration Values

```php
// Cache frequently accessed config
private $timeout;

function main()
{
    // Cache in constructor or first access
    if($this->timeout === null) {
        $this->timeout = $this->core->config->get('api.timeout', 30);
    }

    // Use cached value
    // ... code using $this->timeout
}
```

---

## Complete Configuration Example

### Production config.json

```json
{
  "core": {
    "project": {
      "name": "My Application",
      "version": "1.0.0",
      "environment": "production"
    },
    "gcp": {
      "project_id": "${GCP_PROJECT_ID}",
      "service_account": "/app/credentials/service-account.json",
      "location": "us-central1",
      "datastore": {
        "namespace": "production"
      },
      "storage": {
        "default_bucket": "gs://myapp-production",
        "upload_path": "uploads/"
      },
      "bigquery": {
        "dataset": "analytics",
        "location": "US"
      }
    },
    "cache": {
      "cache_type": "datastore",
      "default_ttl": 3600
    }
  },
  "dbServer": "",
  "dbUser": "root",
  "dbPassword": "${DB_PASSWORD}",
  "dbName": "production_db",
  "dbSocket": "/cloudsql/my-project:us-central1:main-db",
  "dbCharset": "utf8mb4",
  "api": {
    "cors": {
      "enabled": true,
      "allowed_origins": ["https://app.mysite.com"],
      "allowed_methods": ["GET", "POST", "PUT", "DELETE"],
      "allow_credentials": true
    },
    "timeout": 30,
    "rate_limit": {
      "enabled": true,
      "requests_per_minute": 100
    }
  },
  "security": {
    "api_keys": {
      "enabled": true,
      "header_name": "X-API-Key",
      "valid_keys": ["${API_KEY_1}", "${API_KEY_2}"]
    },
    "encryption": {
      "key": "${ENCRYPTION_KEY}"
    }
  }
}
```

### Development local_config.json

```json
{
  "core": {
    "project": {
      "environment": "development"
    },
    "gcp": {
      "project_id": "my-project-dev",
      "service_account": "/Users/me/credentials/service-account-dev.json"
    },
    "cache": {
      "cache_type": "file",
      "cache_path": "/tmp/dev_cache"
    }
  },
  "dbServer": "127.0.0.1",
  "dbPassword": "dev_password",
  "dbSocket": "",
  "api": {
    "cors": {
      "allowed_origins": ["*"]
    },
    "response": {
      "pretty_print": true,
      "include_performance": true
    }
  }
}
```

---

## See Also

- [Getting Started Guide](getting-started.md)
- [API Development Guide](api-development.md)
- [GCP Integration Guide](gcp-integration.md)
- [Security Guide](security.md)
- [Deployment Guide](deployment.md)
