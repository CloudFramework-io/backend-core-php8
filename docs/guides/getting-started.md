# Getting Started with CloudFramework Backend Core PHP8

This guide will walk you through setting up CloudFramework Backend Core and creating your first API and script.

## Table of Contents

- [Prerequisites](#prerequisites)
- [Installation](#installation)
- [Project Setup](#project-setup)
- [Your First API](#your-first-api)
- [Your First Script](#your-first-script)
- [Configuration](#configuration)
- [Next Steps](#next-steps)

## Prerequisites

Before you begin, ensure you have the following installed:

- **PHP 8.3 or higher**
  ```shell
  php --version
  ```

- **Composer** (PHP dependency manager)
  ```shell
  composer --version
  ```

- **Python 3.x** (for development tools)
  ```shell
  python3 --version
  ```

- **Google Cloud SDK** (optional, for GCP deployment)
  ```shell
  gcloud --version
  ```

## Installation

### Step 1: Install via Composer

Create a new directory for your project and install the framework:

```shell
# Create project directory
mkdir my-api-project
cd my-api-project

# Install CloudFramework
composer require cloudframework-io/backend-core-php8
```

If you encounter GRPC extension issues:

```shell
composer require cloudframework-io/backend-core-php8 --ignore-platform-req=ext-grpc
```

### Step 2: Initialize Project Structure

Run the installation script to set up your project:

```shell
php vendor/cloudframework-io/backend-core-php8/install.php
```

This creates the following structure:

```
my-api-project/
├── api/                    # API endpoints
│   ├── index.php          # Main API endpoint
│   └── training/          # Example APIs
│       ├── hello.php
│       └── hello-advanced.php
├── scripts/               # Background scripts
│   ├── template.php
│   └── training/
│       └── hello.php
├── local_data/            # Local development data
│   └── cache/            # Cache directory
├── config.json           # Framework configuration
├── composer.json         # Composer configuration
├── app.yaml             # Google App Engine config
├── .gcloudignore        # GCP deployment ignore rules
├── .gitignore           # Git ignore rules
├── php.ini              # PHP configuration
└── README.md            # Project README
```

## Project Setup

### Configure Composer Scripts

Your `composer.json` should include these helpful scripts:

```json
{
  "scripts": {
    "server": "php -S localhost:8080 -t . vendor/cloudframework-io/backend-core-php8/runapi.php",
    "serve": "@server",
    "script": "php vendor/cloudframework-io/backend-core-php8/runscript.php",
    "setup": "@script _setup",
    "credentials": "@script _install-development-credentials",
    "deploy": "gcloud app deploy"
  }
}
```

### Configure Development Environment

Run the setup script to configure your development environment:

```shell
composer setup
```

This interactive setup will configure:
- Shell environment (macOS zsh support)
- CloudFramework Platform connection (optional)
- GCP project settings
- Data repository access (Datastore, Cloud Storage, BigQuery)
- Local cache configuration

## Your First API

### Start the Development Server

Launch the built-in PHP development server:

```shell
composer server
```

The server will start at `http://localhost:8080/`

### Test the Default APIs

Open your browser and test these endpoints:

1. **Main endpoint**: http://localhost:8080/
   - Returns API information and available endpoints

2. **Simple Hello World**: http://localhost:8080/training/hello
   - Returns: `{"success": true, "status": 200, "code": "ok", "data": "hello World"}`

3. **Advanced Hello World**: http://localhost:8080/training/hello-advanced
   - Returns structured endpoint information

### Create Your First API

Create a new file `api/myapi.php`:

```php
<?php
/**
 * My First API
 */
class API extends RESTful
{
    function main()
    {
        // This method is called automatically
        $this->addReturnData([
            'message' => 'Welcome to my API!',
            'timestamp' => date('Y-m-d H:i:s'),
            'version' => '1.0'
        ]);
    }
}
```

Test it at: http://localhost:8080/myapi

### Create an API with Endpoints

Create `api/users/index.php`:

```php
<?php
/**
 * Users API
 */
class API extends RESTful
{
    function main()
    {
        // Only allow specific methods
        if(!$this->checkMethod('GET,POST,PUT,DELETE')) return;

        // Route to endpoint based on first parameter
        $endpoint = $this->params[0] ?? 'list';

        // Call ENDPOINT_{name} method
        if(!$this->useFunction('ENDPOINT_' . $endpoint)) {
            return $this->setErrorFromCodelib('not-found',
                ": Endpoint /{$this->service}/{$endpoint} not found");
        }
    }

    /**
     * GET /users
     * List all users
     */
    public function ENDPOINT_list()
    {
        if(!$this->checkMethod('GET')) return;

        $users = [
            ['id' => 1, 'name' => 'John Doe', 'email' => 'john@example.com'],
            ['id' => 2, 'name' => 'Jane Smith', 'email' => 'jane@example.com']
        ];

        $this->addReturnData([
            'users' => $users,
            'total' => count($users)
        ]);
    }

    /**
     * POST /users/create
     * Create a new user
     */
    public function ENDPOINT_create()
    {
        if(!$this->checkMethod('POST')) return;

        // Validate required fields
        if(!$this->checkMandatoryFormParams(['name', 'email'])) {
            return;
        }

        // Validate email format
        if(!$this->validateValue('email', $this->formParams['email'], 'email')) {
            return;
        }

        // Create user (example)
        $newUser = [
            'id' => rand(1000, 9999),
            'name' => $this->formParams['name'],
            'email' => $this->formParams['email'],
            'created_at' => date('Y-m-d H:i:s')
        ];

        $this->setReturnStatus(201);
        $this->setReturnCode('inserted');
        $this->addReturnData($newUser);
    }

    /**
     * GET /users/get/{id}
     * Get a specific user
     */
    public function ENDPOINT_get()
    {
        if(!$this->checkMethod('GET')) return;

        // Get user ID from URL parameter
        $userId = $this->checkMandatoryParam(1, 'User ID is required');
        if(!$userId) return;

        // Example: Get user from database
        $user = [
            'id' => $userId,
            'name' => 'John Doe',
            'email' => 'john@example.com'
        ];

        $this->addReturnData($user);
    }
}
```

Test the endpoints:
- List users: http://localhost:8080/users or http://localhost:8080/users/list
- Get user: http://localhost:8080/users/get/123
- Create user (POST): http://localhost:8080/users/create with JSON body

### Test with cURL

```shell
# GET request
curl http://localhost:8080/users

# POST request
curl -X POST http://localhost:8080/users/create \
  -H "Content-Type: application/json" \
  -d '{"name":"John Doe","email":"john@example.com"}'

# GET specific user
curl http://localhost:8080/users/get/123
```

## Your First Script

Scripts are background processes that can be executed via CLI.

### Run Example Scripts

```shell
# Run the hello training script
composer script training/hello

# Run with a method
composer script training/hello/world
```

### Create Your First Script

Create `scripts/myprocess.php`:

```php
<?php
/**
 * My First Script
 */
class Script extends Scripts2020
{
    function main()
    {
        // Get method from parameters (default: 'default')
        $method = $this->params[0] ?? 'default';

        // Call METHOD_{name} function
        if (!$this->useFunction('METHOD_' . $method)) {
            return $this->setErrorFromCodelib('params-error',
                ": Method {$method} not found");
        }
    }

    /**
     * Default method: composer script myprocess
     */
    function METHOD_default()
    {
        $this->sendTerminal("=== My Process Script ===");
        $this->sendTerminal("Available methods:");
        $this->sendTerminal("  - composer script myprocess/hello");
        $this->sendTerminal("  - composer script myprocess/process");
    }

    /**
     * Hello method: composer script myprocess/hello
     */
    function METHOD_hello()
    {
        $this->sendTerminal("Hello from my script!");

        // Access core framework
        $this->sendTerminal("Server time: " . date('Y-m-d H:i:s'));

        // Log information
        $this->core->logs->add("Script executed successfully", 'myprocess');
    }

    /**
     * Process method: composer script myprocess/process
     */
    function METHOD_process()
    {
        $this->sendTerminal("Starting process...");

        // Your processing logic here
        for ($i = 1; $i <= 5; $i++) {
            $this->sendTerminal("Processing item {$i}/5");
            sleep(1); // Simulate work
        }

        $this->sendTerminal("Process completed!");
    }
}
```

Run your scripts:

```shell
# Show available methods
composer script myprocess

# Run hello method
composer script myprocess/hello

# Run process method
composer script myprocess/process
```

### Access Framework Features in Scripts

```php
<?php
class Script extends Scripts2020
{
    function METHOD_example()
    {
        // Access configuration
        $projectId = $this->core->config->get('core.gcp.project_id');

        // Use cache
        $this->core->cache->set('my_key', 'my_value');
        $value = $this->core->cache->get('my_key');

        // Load classes
        $ds = $this->core->loadClass('DataStore');
        $buckets = $this->core->loadClass('Buckets');

        // Send output
        $this->sendTerminal("Project ID: {$projectId}");
    }
}
```

## Configuration

### Basic Configuration

Edit `config.json` to configure your project:

```json
{
  "core.gcp.project_id": "your-project-id",
  "core.datastore.on": false,
  "core.datastorage.on": false,
  "core.bigquery.on": false,
  "development": {
    "core.cache.cache_path": "{{rootPath}}/local_data/cache"
  }
}
```

### Enable GCP Services

To enable Google Cloud services:

```json
{
  "core.gcp.project_id": "your-gcp-project",
  "core.datastore.on": true,
  "core.gcp.datastore.project_id": "",
  "core.datastorage.on": true,
  "core.gcp.datastorage.project_id": "",
  "core.bigquery.on": true,
  "core.gcp.bigquery.project_id": ""
}
```

### Set Up Service Account Credentials

For local development with GCP services:

1. Create a service account in GCP Console
2. Download the JSON key file
3. Save it as `local_data/service-account.json`
4. Set the environment variable:

```shell
export GOOGLE_APPLICATION_CREDENTIALS=$(pwd)/local_data/service-account.json
```

Or use the composer command:

```shell
composer credentials
```

## Next Steps

Now that you have CloudFramework set up, explore these topics:

### Learn More

- **[API Development Guide](api-development.md)** - Advanced API techniques
- **[Script Development Guide](script-development.md)** - Background script patterns
- **[Configuration Guide](configuration.md)** - All configuration options
- **[GCP Integration](gcp-integration.md)** - Working with Google Cloud services

### API Reference

- **[RESTful Class](../api-reference/RESTful.md)** - Complete API reference
- **[Core7 Class](../api-reference/Core7.md)** - Core framework reference
- **[DataStore Class](../api-reference/DataStore.md)** - Datastore integration
- **[Buckets Class](../api-reference/Buckets.md)** - Cloud Storage integration

### Examples

- **[API Examples](../examples/api-examples.md)** - Common API patterns
- **[Script Examples](../examples/script-examples.md)** - Script use cases
- **[GCP Examples](../examples/gcp-examples.md)** - Using GCP services

### Deploy Your Application

- **[Deployment Guide](deployment.md)** - Deploy to App Engine, Cloud Run, etc.

## Getting Help

If you need assistance:

- **Documentation**: Check the [docs/](../) directory
- **GitHub Issues**: [Report issues](https://github.com/CloudFramework-io/backend-core-php8/issues)
- **Examples**: Review training examples in `api/training/` and `scripts/training/`

## Common Issues

### GRPC Extension Error

```shell
composer require cloudframework-io/backend-core-php8 --ignore-platform-req=ext-grpc
```

### Port Already in Use

Change the port in composer.json:

```json
"server": "php -S localhost:8081 -t . vendor/cloudframework-io/backend-core-php8/runapi.php"
```

### Cache Permission Issues

Ensure the cache directory is writable:

```shell
chmod -R 777 local_data/cache
```

---

**Next**: [API Development Guide](api-development.md)
