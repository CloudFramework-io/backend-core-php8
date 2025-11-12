# CloudFramework Backend Core PHP8 - Documentation

Welcome to the CloudFramework Backend Core PHP8 documentation. This comprehensive guide will help you build scalable backend APIs and scripts with Google Cloud Platform integration.

## Table of Contents

- [Getting Started](#getting-started)
- [Guides](#guides)
- [API Reference](#api-reference)
- [Examples](#examples)
- [Additional Resources](#additional-resources)

## Getting Started

Start here if you're new to CloudFramework:

### Quick Start

1. **[Installation & Setup](guides/getting-started.md)** - Install the framework and create your first project
2. **[Your First API](guides/getting-started.md#your-first-api)** - Build a simple RESTful API
3. **[Your First Script](guides/getting-started.md#your-first-script)** - Create a background script
4. **[Configuration](guides/getting-started.md#configuration)** - Configure your project

### Prerequisites

- PHP 8.3+
- Composer
- Basic understanding of REST APIs
- Google Cloud account (optional, for GCP features)

## Guides

Comprehensive guides for different aspects of the framework:

### Development Guides

| Guide | Description |
|-------|-------------|
| **[Getting Started](guides/getting-started.md)** | Complete setup and first steps |
| **[API Development](guides/api-development.md)** | Building robust RESTful APIs |
| **[Script Development](guides/script-development.md)** | Creating background scripts and CLI tools |
| **[Configuration](guides/configuration.md)** | Framework configuration options |
| **[Deployment](guides/deployment.md)** | Deploy to App Engine, Cloud Run, etc. |

### Integration Guides

| Guide | Description |
|-------|-------------|
| **[GCP Integration](guides/gcp-integration.md)** | Working with Google Cloud services |
| **[Datastore Guide](guides/datastore-guide.md)** | Using Google Cloud Datastore |
| **[Cloud Storage Guide](guides/cloud-storage-guide.md)** | Managing files with Cloud Storage |
| **[BigQuery Guide](guides/bigquery-guide.md)** | Analytics with BigQuery |
| **[Cloud SQL Guide](guides/cloud-sql-guide.md)** | Database access with Cloud SQL |

### Best Practices

| Guide | Description |
|-------|-------------|
| **[Security Best Practices](guides/security.md)** | Secure your APIs and data |
| **[Performance Optimization](guides/performance.md)** | Optimize for speed and efficiency |
| **[Error Handling](guides/error-handling.md)** | Robust error handling patterns |
| **[Testing](guides/testing.md)** | Test your APIs and scripts |

## API Reference

Detailed documentation for all classes and methods:

### Core Classes

| Class | Description |
|-------|-------------|
| **[Core7](api-reference/Core7.md)** | Main framework class |
| **[RESTful](api-reference/RESTful.md)** | Base class for all APIs |
| **[Scripts2020](api-reference/Scripts2020.md)** | Base class for scripts |

### Configuration Classes

| Class | Description |
|-------|-------------|
| **[CoreConfig](api-reference/CoreConfig.md)** | Configuration management |
| **[CoreCache](api-reference/CoreCache.md)** | Caching system |
| **[CoreSession](api-reference/CoreSession.md)** | Session management |
| **[CoreSecurity](api-reference/CoreSecurity.md)** | Security features |

### Data Access Classes

| Class | Description |
|-------|-------------|
| **[DataStore](api-reference/DataStore.md)** | Google Cloud Datastore access |
| **[Buckets](api-reference/Buckets.md)** | Google Cloud Storage access |
| **[DataBQ](api-reference/DataBQ.md)** | BigQuery integration |
| **[CloudSQL](api-reference/CloudSQL.md)** | Cloud SQL access |
| **[DataSQL](api-reference/DataSQL.md)** | Generic SQL database access |
| **[DataMongoDB](api-reference/DataMongoDB.md)** | MongoDB integration |

### Utility Classes

| Class | Description |
|-------|-------------|
| **[Email](api-reference/Email.md)** | Email sending functionality |
| **[DataValidation](api-reference/DataValidation.md)** | Data validation utilities |
| **[WorkFlows](api-reference/WorkFlows.md)** | Workflow management |
| **[GoogleSecrets](api-reference/GoogleSecrets.md)** | Secret Manager access |
| **[PubSub](api-reference/PubSub.md)** | Pub/Sub messaging |

## Examples

Practical examples for common use cases:

### API Examples

- **[Basic CRUD API](examples/api-examples.md#basic-crud)** - Create, Read, Update, Delete operations
- **[Authentication API](examples/api-examples.md#authentication)** - User authentication and authorization
- **[File Upload API](examples/api-examples.md#file-upload)** - Handle file uploads to Cloud Storage
- **[Search API](examples/api-examples.md#search)** - Search and filter data
- **[Pagination API](examples/api-examples.md#pagination)** - Paginated results

### Script Examples

- **[Data Migration](examples/script-examples.md#data-migration)** - Migrate data between databases
- **[Batch Processing](examples/script-examples.md#batch-processing)** - Process large datasets
- **[Scheduled Tasks](examples/script-examples.md#scheduled-tasks)** - Cron-like scheduled tasks
- **[Data Export](examples/script-examples.md#data-export)** - Export data to various formats

### GCP Integration Examples

- **[Datastore CRUD](examples/gcp-examples.md#datastore-crud)** - Basic Datastore operations
- **[File Storage](examples/gcp-examples.md#file-storage)** - Upload/download from Cloud Storage
- **[BigQuery Analytics](examples/gcp-examples.md#bigquery)** - Run analytics queries
- **[Secret Management](examples/gcp-examples.md#secrets)** - Manage secrets securely
- **[Pub/Sub Messaging](examples/gcp-examples.md#pubsub)** - Send and receive messages

## Quick Reference

### Common Tasks

#### Create a Simple API

```php
<?php
class API extends RESTful {
    function main() {
        $this->addReturnData('Hello World');
    }
}
```

#### Create an API with Validation

```php
<?php
class API extends RESTful {
    function main() {
        if(!$this->checkMethod('POST')) return;

        $email = $this->checkMandatoryFormParam('email', 'Email required');
        if(!$email) return;

        if(!$this->validateValue('email', $email, 'email')) return;

        $this->addReturnData(['email' => $email]);
    }
}
```

#### Create a Script

```php
<?php
class Script extends Scripts2020 {
    function main() {
        $this->sendTerminal("Hello from script!");
    }
}
```

#### Use Datastore

```php
$ds = $this->core->loadClass('DataStore');
$entity = $ds->createEntity('Users', [
    'name' => 'John Doe',
    'email' => 'john@example.com'
]);
```

#### Use Cloud Storage

```php
$buckets = $this->core->loadClass('Buckets');
$buckets->upload('/path/to/file.pdf', 'gs://my-bucket/file.pdf');
```

## Architecture Overview

```
CloudFramework Backend Core
├── Core7                    # Main framework
│   ├── Configuration        # Config management
│   ├── Cache               # Caching layer
│   ├── Security            # Security features
│   ├── Session             # Session management
│   └── Logging             # Logging system
├── RESTful API Layer       # API development
│   ├── Request Handling    # HTTP request processing
│   ├── Validation          # Input validation
│   ├── Response Formatting # JSON/XML responses
│   └── Error Handling      # Error management
├── Script Execution        # CLI scripts
│   ├── Background Tasks    # Long-running tasks
│   ├── Cron Jobs          # Scheduled tasks
│   └── Data Processing     # Batch processing
└── GCP Integration         # Google Cloud Platform
    ├── Datastore          # NoSQL database
    ├── Cloud Storage      # File storage
    ├── BigQuery           # Analytics
    ├── Cloud SQL          # Relational database
    ├── Secret Manager     # Secrets management
    └── Pub/Sub            # Messaging
```

## Framework Features

### RESTful API Framework
- Automatic request/response handling
- Built-in routing and parameter extraction
- HTTP method validation
- CORS support
- Request validation and sanitization
- Automatic JSON responses
- Error handling with customizable codes

### Data Integration
- Google Cloud Datastore (NoSQL)
- Google Cloud Storage (file storage)
- Google BigQuery (analytics)
- Cloud SQL (MySQL/PostgreSQL)
- MongoDB support
- Generic SQL database support

### Security
- Authentication mechanisms
- Authorization and access control
- CORS configuration
- Input validation and sanitization
- XSS and SQL injection protection
- Secure session management
- Secret Manager integration

### Developer Tools
- Script execution framework
- Advanced logging and debugging
- Performance monitoring
- Flexible caching strategies
- Email sending capabilities
- PDF generation support
- Template rendering (Twig)

## Learning Path

### Beginner

1. Read [Getting Started Guide](guides/getting-started.md)
2. Create your [First API](guides/getting-started.md#your-first-api)
3. Learn about [Request Handling](guides/api-development.md#request-handling)
4. Understand [Response Formatting](guides/api-development.md#response-formatting)

### Intermediate

1. Master [Validation](guides/api-development.md#validation)
2. Implement [Authentication](guides/api-development.md#authentication)
3. Learn [Error Handling](guides/api-development.md#error-handling)
4. Create [Background Scripts](guides/script-development.md)

### Advanced

1. Integrate [Google Cloud Services](guides/gcp-integration.md)
2. Implement [Datastore Access](guides/datastore-guide.md)
3. Use [Cloud Storage](guides/cloud-storage-guide.md)
4. Deploy to [App Engine/Cloud Run](guides/deployment.md)

## Support

### Getting Help

- **Documentation**: You're reading it!
- **GitHub Issues**: [Report bugs or request features](https://github.com/CloudFramework-io/backend-core-php8/issues)
- **Examples**: Check `api/training/` and `scripts/training/` directories
- **Website**: [cloudframework.io](https://cloudframework.io)

### Community

- Share your projects and examples
- Contribute to the framework
- Report issues and suggest improvements

## Contributing

We welcome contributions! See the main [README.md](../README.md#contributing) for guidelines.

## License

This project is licensed under the MIT License - see the [LICENSE](../LICENSE) file for details.

---

## Quick Links

- **[Main README](../README.md)** - Project overview
- **[Getting Started](guides/getting-started.md)** - Quick start guide
- **[API Development](guides/api-development.md)** - API development guide
- **[RESTful Class Reference](api-reference/RESTful.md)** - Complete API reference
- **[GCP Integration](guides/gcp-integration.md)** - Google Cloud integration

---

**CloudFramework** - Accelerating backend development since 2013
