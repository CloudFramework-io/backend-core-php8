# MCP Server Development Guide

This guide explains how to set up and develop MCP (Model Context Protocol) servers using CloudFramework backend-core-php8.

## Prerequisites

- PHP 8.4 or higher
- Composer
- Node.js (optional, for MCP Inspector)

## Quick Start

### Option 1: Automatic Installation

If your project only has `cloudframework-io/backend-core-php8` as dependency, run:

```bash
composer require cloudframework-io/backend-core-php8
php vendor/cloudframework-io/backend-core-php8/install.php mcp
```

This will automatically:
- Create `./local_data/cache` directory
- Create `./mcp` directory for your tools
- Copy `composer-mcp-dist.json` to `composer.json`
- Copy `config-dist.json` to `config.json` (if not exists)
- Copy `.gitignore` (if not exists)
- Copy `.gcloudignore` (if not exists)
- Copy `app-dev.yaml` for GCP App Engine deployment (if not exists)

Then run:
```bash
composer update
composer serve
```
Now you can use for example Postman to connect with your 

### Option 2: Manual Installation

1. **Copy the MCP composer file:**
   ```bash
   cp vendor/cloudframework-io/backend-core-php8/install/composer-mcp-dist.json ./composer.json
   ```

2. **Create required directories:**
   ```bash
   mkdir -p ./mcp ./local_data/cache
   ```

3. **Copy config file (if not exists):**
   ```bash
   cp vendor/cloudframework-io/backend-core-php8/install/config-dist.json ./config.json
   ```

4. **Install dependencies:**
   ```bash
   composer install
   ```

5. **Start the server:**
   ```bash
   composer serve
   ```

## Project Structure

After setup, your project should look like this:

```
your-project/
├── composer.json          # MCP-specific composer config
├── config.json            # CloudFramework configuration
├── local_data/
│   └── cache/             # Local cache directory
├── mcp/                   # Your custom MCP tools (create your classes here)
│   └── MyTools.php        # Example: Your custom tool class
└── vendor/
    └── cloudframework-io/
        └── backend-core-php8/
            └── src/
                ├── mcp-server.php       # MCP server entry point
                └── mcp/
                    ├── MCPCore7.php     # Base class for MCP tools
                    └── Auth.php         # Authentication tools
```

## Available Commands

| Command | Description |
|---------|-------------|
| `composer serve` | Start MCP server at http://localhost:8000 |
| `composer inspector-local` | Launch MCP Inspector for testing |
| `composer credentials` | Setup GCP credentials for local development |
| `composer autoload` | Regenerate autoload files |
| `composer script -- {name}` | Run a CloudFramework script |

## Creating Custom MCP Tools

### Basic Tool Class

Create a file in the `./mcp` directory:

```php
<?php
// mcp/MyTools.php

namespace App\Mcp;

use Mcp\Capability\Attribute\McpTool;
use Mcp\Capability\Attribute\McpResource;
use Mcp\Capability\Attribute\McpPrompt;

class MyTools extends \MCPCore7
{
    /**
     * A simple hello world tool
     *
     * @param string $name The name to greet
     * @return string Greeting message
     */
    #[McpTool(name: 'hello_world')]
    public function helloWorld(string $name = 'World'): string
    {
        return "Hello, {$name}!";
    }
}
```

### Using MCPCore7 Features

The `MCPCore7` base class provides access to:

```php
class MyTools extends \MCPCore7
{
    #[McpTool(name: 'my_tool')]
    public function myTool(): array
    {
        // Access Core7 framework
        $this->core->logs->add('Tool executed', 'my-tool');

        // Check if user is authenticated
        if (!$this->core->user->isAuth()) {
            return ['error' => true, 'message' => 'Authentication required'];
        }

        // Get current user
        $userId = $this->core->user->id;

        // Initialize CFOs (CloudFramework Objects)
        if ($this->initCFOs()) {
            // Use CFOs for data operations
            $data = $this->cfos->getCFOCodeObject('MyEntity');
        }

        // Access request parameters
        $headers = $this->api->getHeaders();

        return ['success' => true, 'user' => $userId];
    }
}
```

### Tool Types

#### 1. Tools (Actions)

Tools perform actions and return results:

```php
#[McpTool(name: 'create_user')]
public function createUser(string $email, string $name): array
{
    // Perform action
    return ['id' => 123, 'email' => $email, 'name' => $name];
}
```

#### 2. Resources (Read-only Data)

Resources provide read-only access to data:

```php
#[McpResource(
    uri: 'data://users/current',
    name: 'current_user',
    description: 'Current authenticated user information'
)]
public function getCurrentUser(): array
{
    return [
        'id' => $this->core->user->id,
        'data' => $this->core->user->data
    ];
}
```

#### 3. Prompts (Guided Conversations)

Prompts provide conversation templates:

```php
#[McpPrompt(
    name: 'setup_guide',
    description: 'Guide user through initial setup'
)]
public function setupGuide(string $projectName): array
{
    return [
        [
            'role' => 'assistant',
            'content' => "I'll help you set up {$projectName}."
        ],
        [
            'role' => 'user',
            'content' => 'Please guide me through the configuration steps.'
        ]
    ];
}
```

## Authentication

The framework supports multiple authentication methods:

### 1. OAuth 2.1 (Recommended)

```php
// In your MCP client, start OAuth flow:
// 1. Call oauth_start tool
// 2. User opens URL in browser
// 3. User provides authorization code
// 4. Call oauth_complete with the code
```

### 2. DS-Token

```php
// Set token directly:
// Call set_dstoken tool with your platform token
```

### 3. Authorization Header

Send OAuth token in request header:
```
Authorization: Bearer your_oauth_token
```

## Configuration

### config.json

Key configuration options:

```json
{
    "core.gcp.project_id": "your-gcp-project",
    "core.gcp.datastore.on": true,
    "core.gcp.datastore.namespace": "default",
    "core.erp.platform_id": "your-platform-id",
    "core.cache.cache_path": "{{documentRoot}}/local_data/cache"
}
```

### GCP Credentials

For local development with GCP services:

```bash
composer credentials
```

Then add to config.json:
```json
{
    "core.gcp.credentials": "{{documentRoot}}/local_data/application_default_credentials.json"
}
```

### app-dev.yaml (GCP App Engine Deployment)

Before deploying to GCP App Engine, you must configure `app-dev.yaml` with your project settings:

```yaml
service: mcp-php                    # Change to your service name
runtime: php84
instance_class: F2                  # Adjust based on your needs (F1, F2, F4, F4_1G)
automatic_scaling:
  max_concurrent_requests: 10
  max_instances: 3                  # Adjust based on expected load

# MCP server entry point (do not change)
entrypoint: serve vendor/cloudframework-io/backend-core-php8/src/mcp-server.php

# ... handlers section ...

env_variables:
  PROJECT_ID: "your-project"        # Your GCP project ID
  LOCATION_ID: "europe-west1"       # Your preferred region
  QUEUE_ID: "default"               # Cloud Tasks queue ID
  DATASTORE_DATASET: "your-project" # Usually same as PROJECT_ID
  REDIS_HOST: "10.0.0.1"            # Your Redis instance IP
  REDIS_PORT: "6379"                # Redis port

# VPC connector for Redis access (required if using Redis)
vpc_access_connector:
  name: "projects/{your-project}/locations/{your-region}/connectors/{connector-name}"
```

**Required modifications:**

| Setting | Description |
|---------|-------------|
| `service` | Unique service name for your MCP server |
| `PROJECT_ID` | Your GCP project ID |
| `LOCATION_ID` | GCP region (e.g., `europe-west1`, `us-central1`) |
| `DATASTORE_DATASET` | Your Datastore project (usually same as PROJECT_ID) |
| `REDIS_HOST` / `REDIS_PORT` | Your Memorystore Redis instance details |
| `vpc_access_connector.name` | VPC connector path for Redis access |

**Optional modifications:**

| Setting | Description |
|---------|-------------|
| `instance_class` | Instance size: `F1` (256MB), `F2` (512MB), `F4` (1GB), `F4_1G` (2GB) |
| `max_instances` | Maximum number of instances for auto-scaling |
| `max_concurrent_requests` | Requests per instance before scaling |

**Deploy to App Engine:**

```bash
gcloud app deploy app-dev.yaml --project=your-project --version=dev
```

## PSR-4 Namespaces

The composer.json configures these namespaces:

| Namespace | Directory | Description |
|-----------|-----------|-------------|
| `App\` | `.` | Your project root |
| `App\Mcp\` | `mcp/` | Your custom MCP tools |
| `App\CFMcp\` | `vendor/.../src/mcp/` | Framework MCP classes |

## Testing with MCP Inspector

1. Start your server:
   ```bash
   composer serve
   ```

2. In another terminal, launch inspector:
   ```bash
   composer inspector-local
   ```

3. Or use npx directly:
   ```bash
   npx @modelcontextprotocol/inspector http://localhost:8000
   ```

## Connecting to Claude Desktop

Add to your Claude Desktop configuration (`claude_desktop_config.json`):

```json
{
  "mcpServers": {
    "my-mcp-server": {
      "command": "npx",
      "args": [
        "mcp-remote",
        "http://localhost:8000"
      ]
    }
  }
}
```

For production with authentication:
```json
{
  "mcpServers": {
    "my-mcp-server": {
      "command": "npx",
      "args": [
        "mcp-remote",
        "https://your-server.com",
        "--header",
        "Authorization: Bearer YOUR_TOKEN"
      ]
    }
  }
}
```

## Important Considerations

### Security

1. **Authentication**: Always validate user authentication in tools that access sensitive data
2. **Input Validation**: Validate and sanitize all input parameters
3. **Secrets**: Use `readSecrets()` method to access platform secrets securely
4. **CORS**: The server handles CORS automatically for development

### Sessions

- MCP sessions are stored using PHP native sessions
- Default TTL is 24 hours (86400 seconds)
- Use `session_ping` tool to keep sessions alive
- Use `session_restore` tool to recover from session loss

### Error Handling

```php
#[McpTool(name: 'safe_operation')]
public function safeOperation(): array
{
    try {
        // Your logic
        return ['success' => true, 'data' => $result];
    } catch (\Exception $e) {
        return [
            'error' => true,
            'message' => $e->getMessage()
        ];
    }
}
```

### Performance

1. **Caching**: Use `$this->core->cache` for expensive operations
2. **Lazy Loading**: Initialize resources only when needed
3. **CFOs**: Call `initCFOs()` only when database access is required

## Dependencies

The MCP server requires these packages:

```json
{
    "require": {
        "mcp/sdk": "^0.3.0",
        "nyholm/psr7": "^1.8",
        "nyholm/psr7-server": "^1.1",
        "laminas/laminas-httphandlerrunner": "^2.10",
        "cloudframework-io/backend-core-php8": "^8.4.27"
    }
}
```

## Built-in Tools Reference

The framework includes these authentication tools in `Auth.php`:

| Tool | Description |
|------|-------------|
| `set_dstoken` | Authenticate with a platform dstoken |
| `clean_dstoken` | Clear current dstoken |
| `clean_session` | Clear all session data |
| `test_dstoken` | Verify current dstoken validity |
| `refresh_dstoken` | Refresh dstoken from OAuth token |
| `get_platform_user` | Get authenticated user info |
| `oauth_start` | Start OAuth 2.1 flow |
| `oauth_complete` | Complete OAuth with auth code |
| `oauth_status` | Check authentication status |
| `oauth_refresh` | Refresh OAuth access token |
| `get_oauth_config` | Get OAuth server configuration |
| `session_ping` | Keep session alive |
| `session_restore` | Restore authentication after session loss |

## Troubleshooting

### Server won't start
- Check PHP version: `php -v` (requires 8.1+)
- Verify composer dependencies: `composer install`
- Check for port conflicts on 8000

### Authentication fails
- Verify token format includes platform prefix: `platform__token`
- Check `cfo-secrets` configuration in CloudFramework platform
- Ensure `api_login_integration_key` is set in secrets

### Tools not discovered
- Verify class is in `mcp/` directory
- Check namespace is `App\Mcp`
- Ensure class extends `\MCPCore7`
- Run `composer dump-autoload`

### Session expired
- Use `session_ping` periodically
- Call `session_restore` to recover
- Check session TTL configuration

## Documentation

- CloudFramework Docs: https://cloudframework.io/docs/es/developers/php-framework/backend-core-php8
- MCP Protocol: https://modelcontextprotocol.io/
- MCP SDK: https://github.com/modelcontextprotocol/php-sdk
