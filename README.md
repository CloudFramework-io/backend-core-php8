# CloudFramework Backend Core PHP8

A powerful and flexible PHP8+ framework for building scalable backend APIs and scripts, optimized for Google Cloud Platform (GCP) services including App Engine, Cloud Functions, Compute Engine, and Kubernetes.

[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)
[![PHP Version](https://img.shields.io/badge/PHP-%5E8.3-blue.svg)](https://www.php.net/)

## Overview

CloudFramework Backend Core is a comprehensive framework designed to accelerate backend development with built-in support for:

- **RESTful API Development**: Robust API creation with built-in routing, validation, and response handling
- **Google Cloud Integration**: Native support for Datastore, Cloud Storage, BigQuery, Cloud SQL, and more
- **Script Execution**: CLI script framework for backend tasks and automation
- **Security**: Built-in authentication, authorization, and security features
- **Data Validation**: Comprehensive validation system for API requests
- **Caching**: Flexible caching strategies for performance optimization
- **Logging**: Advanced logging with GCP Cloud Logging integration

## Requirements

- PHP ^8.3
- Composer
- Python 3.x (for development tools)
- Google Cloud SDK (optional, for GCP deployment)

## Quick Links

- **GitHub Repository**: [https://github.com/CloudFramework-io/backend-core-php8](https://github.com/CloudFramework-io/backend-core-php8)
- **Packagist**: [https://packagist.org/packages/cloudframework-io/backend-core-php8](https://packagist.org/packages/cloudframework-io/backend-core-php8)
- **Documentation**: See `/docs` directory for comprehensive guides

## Table of Contents

- [Installation](#installation)
- [Quick Start](#quick-start)
- [Your First API](#your-first-api)
- [Running Scripts](#running-scripts)
- [Configuration](#configuration)
- [GCP Setup](#gcp-setup)
- [Deployment](#deployment)
- [Documentation](#documentation)
- [Contributing](#contributing)
- [License](#license)

## Installation

### Basic Installation

Install the framework via Composer:
```shell
composer require cloudframework-io/backend-core-php8
# if you have problem with GRPC extensions you can use:
# composer require cloudframework-io/backend-core-php8 --ignore-platform-req=ext-grpc
```

### Initialize Project Structure

Run the installation script to create the basic project structure:

```shell
php vendor/cloudframework-io/backend-core-php8/install.php
```

This command will create:
- `./local_data/cache` - Local cache directory
- `./api/` - API endpoints examples
- `./scripts/` - Script examples
- `composer.json` - Project composer configuration
- `.gitignore` - Git ignore rules
- `app.yaml` - Google App Engine configuration
- `.gcloudignore` - GCP deployment ignore rules
- `README.md` - Project README
- `php.ini` - PHP configuration
- `config.json` - Framework configuration

## Quick Start

### Launch Local Development Server

Start the built-in development server:
```shell
composer server
```

The server will start at `http://localhost:8080/`

### Test Your First API

Open your browser and navigate to:
- `http://localhost:8080/` - Main endpoint with API information
- `http://localhost:8080/training/hello` - Simple Hello World API
- `http://localhost:8080/training/hello-advanced` - Advanced Hello World with endpoints

### Example API Response

The response from `http://localhost:8080/` will be a JSON:
```json
{
    "success": true,
    "status": 200,
    "code": "ok",
    "time_zone": "UTC",
    "data": {
        "end-point /index [current]": "This end-point defined in <document-root>/api/index.php",
        "end-point /training/hello": "Advanced API Structure of Hello World in  <document-root>/api/training/hello-advanced.php",
        "Current Url Parameters: $this->params": [],
        "Current formParameters: $this->formParams": []
    },
    "logs": [
    "[syslog:info] CoreCache: init(). type: directory",
    "[syslog:info] RESTful: Url: [GET] http://localhost:8080/"
    ]
}
```

## Your First API

### Simple API Structure

Create a simple API in `api/your-endpoint.php`:

```php
<?php
class API extends RESTful
{
    function main()
    {
        // Add data to the response
        $this->addReturnData('Hello World');
    }
}
```

### Advanced API with Endpoints

Create an advanced API with multiple endpoints in `api/your-service/index.php`:

```php
<?php
class API extends RESTful
{
    function main()
    {
        // Restrict HTTP methods
        if(!$this->checkMethod('GET,POST,PUT,DELETE')) return;

        // Route to specific endpoint
        $endpoint = $this->params[0] ?? 'default';
        if(!$this->useFunction('ENDPOINT_'.$endpoint)) {
            return $this->setErrorFromCodelib('params-error', "Endpoint not found");
        }
    }

    public function ENDPOINT_default()
    {
        $this->addReturnData([
            "message" => "Welcome to the API",
            "endpoints" => ["/hello", "/users", "/data"]
        ]);
    }

    public function ENDPOINT_hello()
    {
        if(!$this->checkMethod('GET')) return;
        $this->addReturnData('Hello from endpoint');
    }
}
```

See the training examples in:
- `api/training/hello.php` - Basic API structure
- `api/training/hello-advanced.php` - Advanced API with endpoints

## Running Scripts

### Execute Scripts

Run background scripts using the CLI:

```shell
# Execute a script
composer script training/hello

# Pass parameters to the script
composer script training/hello/world
```

### Script Structure

Create a script in `scripts/your-script.php`:

```php
<?php
class Script extends Scripts2020
{
    function main()
    {
        $method = $this->params[0] ?? 'default';
        if (!$this->useFunction('METHOD_' . $method)) {
            return $this->setErrorFromCodelib('params-error', "Method not found");
        }
    }

    function METHOD_default()
    {
        $this->sendTerminal("Hello from script!");
    }
}
```

## Configuration

### Environment Setup

Configure your development environment (requires [GCP SDK](https://cloud.google.com/sdk/docs/install-sdk) for GCP integration):

```shell
composer setup
```

This interactive setup will configure:
- Shell environment (for macOS with zsh)
- CloudFramework Platform connection (optional)
- GCP project settings
- Datastore, Cloud Storage, BigQuery access
- Local cache configuration

### Configuration File

Edit `config.json` to configure your project:

```json
{
  "core.gcp.project_id": "your-project-id",
  "core.datastore.on": true,
  "core.gcp.datastore.project_id": "",
  "core.datastorage.on": true,
  "core.gcp.datastorage.project_id": "",
  "core.bigquery.on": true,
  "core.gcp.bigquery.project_id": "",
  "development": {
    "core.cache.cache_path": "{{rootPath}}/local_data/cache"
  }
}
```

### Service Account Credentials

For local development with GCP services, set up credentials:

```shell
export GOOGLE_APPLICATION_CREDENTIALS=$(pwd)/local_data/service-account.json
# Or use composer command
composer credentials
```
### macOS Development Environment

For macOS users with zsh, the setup script can configure helpful aliases:

```shell
cfserve              # Start local development server
cfdeploy             # Deploy to GCP
cfcredentials        # Install development credentials
cfscript             # Run scripts
cfgen_password       # Generate secure passwords
```

## GCP Setup

### Prerequisites

1. Install [Google Cloud SDK](https://cloud.google.com/sdk/docs/install-sdk)
2. Create a GCP project or use an existing one
3. Enable required APIs:
   - Cloud Datastore API (optional)
   - Cloud Storage API (optional)
   - BigQuery API (optional)
   - Cloud SQL Admin API (optional)

### Configure GCP Project

Run the interactive setup to configure GCP services:

```shell
composer setup
```

The setup will prompt you for:
- GCP Project ID
- Enable/disable Datastore, Cloud Storage, BigQuery
- Cache configuration
- Service account credentials

## Deployment

### Deploy to Google App Engine

1. Ensure you have the correct GCP project selected:

```shell
gcloud config set project YOUR-PROJECT-ID
```

2. Deploy your application:

```shell
gcloud app deploy app.yaml --project=YOUR-PROJECT-ID
```

3. Your application will be available at:
```
https://YOUR-PROJECT-ID.ew.r.appspot.com
```

### Deploy to Cloud Functions

Create a Cloud Function entry point and deploy:

```shell
gcloud functions deploy myFunction \
  --runtime php83 \
  --trigger-http \
  --allow-unauthenticated
```

### Deploy to Cloud Run

Build and deploy as a container:

```shell
gcloud run deploy my-api \
  --source . \
  --platform managed \
  --region europe-west1 \
  --allow-unauthenticated
```

## Core Features

### RESTful API Framework
- Automatic request/response handling
- Built-in routing with URL parameters
- HTTP method validation
- CORS support
- Request validation and sanitization
- Automatic JSON responses
- Error handling with customizable codes

### Data Integration
- **Google Cloud Datastore**: NoSQL database access
- **Google Cloud Storage**: File storage and management
- **Google BigQuery**: Data warehouse queries
- **Cloud SQL**: MySQL/PostgreSQL integration
- **MongoDB**: NoSQL database support
- **PostgreSQL**: Direct PostgreSQL access

### Security
- Authentication mechanisms
- Authorization and access control
- CORS configuration
- Input validation and sanitization
- XSS and SQL injection protection
- Secure session management

### Developer Tools
- Script execution framework
- Logging and debugging
- Performance monitoring
- Caching strategies
- Email sending capabilities
- PDF generation (via tcpdi_cf)
- Template rendering (Twig support)

## Documentation

Comprehensive documentation is available in the `/docs` directory:

- **[Getting Started Guide](docs/guides/getting-started.md)** - Complete setup and first steps
- **[API Development](docs/guides/api-development.md)** - Building RESTful APIs
- **[Script Development](docs/guides/script-development.md)** - Creating background scripts
- **[GCP Integration](docs/guides/gcp-integration.md)** - Working with Google Cloud services
- **[API Reference](docs/api-reference/)** - Detailed class and method documentation
- **[Configuration Guide](docs/guides/configuration.md)** - Configuration options
- **[Deployment Guide](docs/guides/deployment.md)** - Deploy to various platforms
- **[Security Best Practices](docs/guides/security.md)** - Security guidelines
- **[Examples](docs/examples/)** - Code examples and use cases

## Framework Architecture

The framework follows a modular architecture:

```
your-project/
├── api/                    # API endpoints
│   ├── index.php          # Main API
│   └── training/          # Training examples
├── scripts/               # Background scripts
├── local_data/            # Local development data
│   └── cache/            # Cache directory
├── config.json           # Configuration
├── composer.json         # Dependencies
└── vendor/
    └── cloudframework-io/
        └── backend-core-php8/
            ├── src/
            │   ├── Core7.php        # Core framework
            │   ├── class/           # Framework classes
            │   └── dispatcher.php   # Request dispatcher
            ├── runapi.php          # API entry point
            ├── runscript.php       # Script entry point
            └── install.php         # Installation script
```

## Available Classes

Core classes accessible via `$this->core`:

- `Core7` - Main framework class
- `CoreConfig` - Configuration management
- `CoreCache` - Caching system
- `CoreSecurity` - Security features
- `CoreRequest` - HTTP request handling
- `CoreSession` - Session management
- `CoreLog` - Logging system
- `CorePerformance` - Performance monitoring

Extended functionality classes:

- `RESTful` - API base class
- `Scripts2020` - Script base class
- `DataStore` - Google Cloud Datastore
- `Buckets` - Google Cloud Storage
- `DataBQ` - BigQuery integration
- `CloudSQL` - Cloud SQL access
- `Email` - Email sending
- `DataValidation` - Input validation
- `WorkFlows` - Workflow management

See the [API Reference](docs/api-reference/) for detailed documentation.

## Contributing

We welcome contributions! Please follow these guidelines:

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

### Coding Standards

- Follow PSR-12 coding standards
- Write clear, documented code
- Include unit tests for new features
- Update documentation as needed

## Support

- **Issues**: [GitHub Issues](https://github.com/CloudFramework-io/backend-core-php8/issues)
- **Documentation**: [Documentation Directory](docs/)
- **Website**: [https://cloudframework.io](https://cloudframework.io)

## Authors

- Héctor López - [hl@cloudframework.io](mailto:hl@cloudframework.io)
- Fran Herrera - [fran@cloudframework.io](mailto:fran@cloudframework.io)
- Adrian Martínez - [am@cloudframework.io](mailto:am@cloudframework.io)

## License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## Acknowledgments

- Built with support for Google Cloud Platform
- Integrated with various Google Cloud services
- Optimized for App Engine, Cloud Functions, Cloud Run, and Kubernetes

---

**CloudFramework** - Accelerating backend development since 2013