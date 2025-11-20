# Google Cloud Platform (GCP) Integration Guide

CloudFramework Backend Core provides native integration with Google Cloud Platform services, making it easy to build scalable applications using GCP infrastructure.

## Table of Contents

- [Prerequisites](#prerequisites)
- [Configuration](#configuration)
- [Service Account Setup](#service-account-setup)
- [Available Services](#available-services)
- [Cloud Datastore](#cloud-datastore)
- [Cloud Storage](#cloud-storage)
- [BigQuery](#bigquery)
- [Cloud SQL](#cloud-sql)
- [Secret Manager](#secret-manager)
- [Pub/Sub](#pubsub)
- [Best Practices](#best-practices)

## Prerequisites

### 1. Google Cloud Account

Create a Google Cloud account at [cloud.google.com](https://cloud.google.com)

### 2. GCP Project

Create a project in [GCP Console](https://console.cloud.google.com/projectcreate)

### 3. Enable Required APIs

Enable the APIs you plan to use:
- [Cloud Datastore API](https://console.cloud.google.com/apis/library/datastore.googleapis.com)
- [Cloud Storage API](https://console.cloud.google.com/apis/library/storage-api.googleapis.com)
- [BigQuery API](https://console.cloud.google.com/apis/library/bigquery.googleapis.com)
- [Cloud SQL Admin API](https://console.cloud.google.com/apis/library/sqladmin.googleapis.com)
- [Secret Manager API](https://console.cloud.google.com/apis/library/secretmanager.googleapis.com)
- [Pub/Sub API](https://console.cloud.google.com/apis/library/pubsub.googleapis.com)

### 4. Install Google Cloud SDK (for local development)

```shell
# macOS
brew install google-cloud-sdk

# Or download from
https://cloud.google.com/sdk/docs/install
```

## Configuration

### 1. Update config.json

Edit your project's `config.json`:

```json
{
  "core.gcp.project_id": "your-project-id",

  "core.datastore.on": true,
  "core.gcp.datastore.project_id": "",
  "core.gcp.datastore.transport": "rest",

  "core.datastorage.on": true,
  "core.gcp.datastorage.project_id": "",

  "core.bigquery.on": true,
  "core.gcp.bigquery.project_id": "",

  "development": {
    "core.cache.cache_path": "{{rootPath}}/local_data/cache"
  }
}
```

### 2. Run Interactive Setup

```shell
composer setup
```

This will guide you through:
- Setting GCP project ID
- Enabling/disabling services
- Configuring service accounts
- Setting up local credentials

## Service Account Setup

### 1. Create Service Account

In [GCP Console](https://console.cloud.google.com/iam-admin/serviceaccounts):

1. Go to "IAM & Admin" > "Service Accounts"
2. Click "Create Service Account"
3. Name it (e.g., "backend-core-dev")
4. Grant roles:
   - Cloud Datastore User
   - Storage Object Admin
   - BigQuery Data Editor
   - Cloud SQL Client
   - Secret Manager Secret Accessor
   - Pub/Sub Publisher/Subscriber

### 2. Create and Download Key

1. Click on your service account
2. Go to "Keys" tab
3. Click "Add Key" > "Create new key"
4. Choose "JSON" format
5. Download the key file

### 3. Configure Locally

```shell
# Save the key file
mkdir -p local_data
mv ~/Downloads/your-key-file.json local_data/service-account.json

# Set environment variable
export GOOGLE_APPLICATION_CREDENTIALS=$(pwd)/local_data/service-account.json

# Add to your shell profile (~/.zshrc or ~/.bashrc)
echo 'export GOOGLE_APPLICATION_CREDENTIALS="'$(pwd)'/local_data/service-account.json"' >> ~/.zshrc

# Or use composer command
composer credentials
```

### 4. Add to .gitignore

```shell
echo "local_data/service-account.json" >> .gitignore
```

## Available Services

CloudFramework provides classes for the following GCP services:

| Service | Class | Purpose |
|---------|-------|---------|
| Cloud Datastore | `DataStore` | NoSQL document database |
| Cloud Storage | `Buckets` | Object/file storage |
| BigQuery | `DataBQ` | Data warehouse and analytics |
| Cloud SQL | `CloudSQL` | Managed MySQL/PostgreSQL |
| Secret Manager | `GoogleSecrets` | Secure secret storage |
| Pub/Sub | `PubSub` | Messaging and event streaming |

## Cloud Datastore

NoSQL document database for web and mobile apps.

### Basic Usage

```php
// Load DataStore class
$ds = $this->core->loadClass('DataStore', ['Users']);

// Create entity
$user = $ds->createEntity([
    'name' => 'John Doe',
    'email' => 'john@example.com',
    'age' => 30
]);

// Fetch entities
$users = $ds->fetchAll('*', ['age' => ['>' => 25]]);

// Update entity
$user['age'] = 31;
$ds->createEntity($user);

// Delete entity
$ds->delete(['KeyId' => $user['KeyId']]);

// Count entities
$total = $ds->count(['active' => true]);
```

### Advanced Features

```php
// With schema validation
$schema = [
    'props' => [
        'name' => ['Name', 'string', 'required'],
        'email' => ['Email', 'string', 'required|unique'],
        'age' => ['Age', 'integer']
    ]
];

$ds = $this->core->loadClass('DataStore', [
    'Users',
    'production',
    $schema
]);

// With caching
$ds->activateCache(true);

// Pagination
$ds->cursor = $savedCursor;
$users = $ds->fetchLimit('*', null, ['created_at' => 'DESC'], 20);
$nextCursor = $ds->cursor;

// Aggregations
$avgAge = $ds->avg('age');
$totalAge = $ds->sum('age');
```

**See:** [DataStore Class Reference](../api-reference/DataStore.md)

---

## Cloud Storage

Scalable object storage for files and media.

### Basic Usage

```php
// Load Buckets class
$buckets = $this->core->loadClass('Buckets', ['my-bucket']);

// Upload file
$result = $buckets->upload(
    '/local/path/file.pdf',
    'gs://my-bucket/documents/file.pdf'
);

// Download file
$buckets->download(
    'gs://my-bucket/documents/file.pdf',
    '/local/path/file.pdf'
);

// List files
$files = $buckets->ls('gs://my-bucket/documents/');

// Delete file
$buckets->delete('gs://my-bucket/documents/file.pdf');

// Check if file exists
if($buckets->fileExist('gs://my-bucket/file.pdf')) {
    // File exists
}
```

### Working with Directories

```php
// Create directory
$buckets->mkdir('gs://my-bucket/new-folder/');

// List directory
$files = $buckets->ls('gs://my-bucket/new-folder/');

// Check if directory exists
if($buckets->isDir('gs://my-bucket/new-folder/')) {
    // Directory exists
}

// Remove directory
$buckets->rmdir('gs://my-bucket/new-folder/');
```

### Upload from API

```php
// In your API
public function ENDPOINT_upload()
{
    if(!$this->checkMethod('POST')) return;

    // Check if file was uploaded
    if(!isset($_FILES['file'])) {
        return $this->setError('No file uploaded', 400);
    }

    $file = $_FILES['file'];
    $buckets = $this->core->loadClass('Buckets', ['my-bucket']);

    // Upload to Cloud Storage
    $destination = 'gs://my-bucket/uploads/' . $file['name'];
    $result = $buckets->upload($file['tmp_name'], $destination);

    if(!$result) {
        return $this->setError('Upload failed', 500);
    }

    $this->addReturnData([
        'url' => $destination,
        'size' => $file['size'],
        'type' => $file['type']
    ]);
}
```

**See:** [Buckets Class Reference](../api-reference/Buckets.md)

---

## BigQuery

Data warehouse for analytics and large-scale queries.

### Basic Usage

```php
// Load DataBQ class
$bq = $this->core->loadClass('DataBQ', ['my_dataset']);

// Run query
$results = $bq->query("
    SELECT
        user_id,
        COUNT(*) as order_count,
        SUM(total) as total_revenue
    FROM `{$this->core->gc_project_id}.my_dataset.orders`
    WHERE created_at >= TIMESTAMP_SUB(CURRENT_TIMESTAMP(), INTERVAL 30 DAY)
    GROUP BY user_id
    ORDER BY total_revenue DESC
    LIMIT 10
");

if($bq->error) {
    // Handle error
    $this->core->logs->add($bq->errorMsg, 'bigquery', 'error');
} else {
    $this->addReturnData($results);
}
```

### Insert Data

```php
$bq = $this->core->loadClass('DataBQ', ['my_dataset', 'events']);

// Insert single row
$bq->insert([
    'user_id' => 123,
    'event' => 'page_view',
    'timestamp' => new DateTime(),
    'data' => json_encode(['page' => '/home'])
]);

// Insert multiple rows
$bq->insertAll([
    ['user_id' => 123, 'event' => 'click', 'timestamp' => new DateTime()],
    ['user_id' => 456, 'event' => 'purchase', 'timestamp' => new DateTime()]
]);
```

### Create Table

```php
$schema = [
    ['name' => 'user_id', 'type' => 'INTEGER', 'mode' => 'REQUIRED'],
    ['name' => 'event', 'type' => 'STRING', 'mode' => 'REQUIRED'],
    ['name' => 'timestamp', 'type' => 'TIMESTAMP', 'mode' => 'REQUIRED'],
    ['name' => 'data', 'type' => 'JSON', 'mode' => 'NULLABLE']
];

$bq->createTable('events', $schema);
```

**See:** [DataBQ Class Reference](../api-reference/DataBQ.md)

---

## Cloud SQL

Managed MySQL and PostgreSQL databases.

### Basic Usage

```php
// Load CloudSQL class
$sql = $this->core->loadClass('CloudSQL', [
    'host' => '/cloudsql/project:region:instance',
    'database' => 'mydb',
    'username' => 'root',
    'password' => 'secret'
]);

// Execute query
$users = $sql->query("
    SELECT * FROM users
    WHERE age > :age AND active = :active
", [
    ':age' => 25,
    ':active' => 1
]);

// Insert
$sql->command("
    INSERT INTO users (name, email, age)
    VALUES (:name, :email, :age)
", [
    ':name' => 'John Doe',
    ':email' => 'john@example.com',
    ':age' => 30
]);

// Get last insert ID
$userId = $sql->getInsertId();
```

### With Connection Pooling

```php
// In your API
function main()
{
    // Initialize once
    if(!isset($this->sql)) {
        $this->sql = $this->core->loadClass('CloudSQL', [
            'host' => $_ENV['DB_HOST'],
            'database' => $_ENV['DB_NAME'],
            'username' => $_ENV['DB_USER'],
            'password' => $_ENV['DB_PASS']
        ]);
    }

    // Use in endpoints
    $endpoint = $this->params[0] ?? 'list';
    $this->useFunction('ENDPOINT_' . $endpoint);
}

public function ENDPOINT_list()
{
    $users = $this->sql->query("SELECT * FROM users");
    $this->addReturnData($users);
}
```

**See:** [CloudSQL Class Reference](../api-reference/CloudSQL.md)

---

## Secret Manager

Securely store and access secrets like API keys and passwords.

### Basic Usage

```php
// Load GoogleSecrets class
$secrets = $this->core->loadClass('GoogleSecrets');

// Get secret
$apiKey = $secrets->getSecret('api-key');
$dbPassword = $secrets->getSecret('database-password');

// Create secret
$secrets->createSecret('new-secret', 'secret-value');

// Update secret
$secrets->updateSecret('api-key', 'new-value');

// Delete secret
$secrets->deleteSecret('old-secret');
```

### Use in Configuration

```php
// In your API
function main()
{
    $secrets = $this->core->loadClass('GoogleSecrets');

    // Get database credentials from Secret Manager
    $config = [
        'host' => $secrets->getSecret('db-host'),
        'database' => $secrets->getSecret('db-name'),
        'username' => $secrets->getSecret('db-user'),
        'password' => $secrets->getSecret('db-password')
    ];

    $this->sql = $this->core->loadClass('CloudSQL', $config);
}
```

**See:** [GoogleSecrets Class Reference](../api-reference/GoogleSecrets.md)

---

## Pub/Sub

Messaging service for event-driven architectures.

### Basic Usage

```php
// Load PubSub class
$pubsub = $this->core->loadClass('PubSub');

// Publish message
$pubsub->publish('my-topic', [
    'event' => 'user.created',
    'user_id' => 123,
    'timestamp' => time()
]);

// Publish multiple messages
$pubsub->publishBatch('my-topic', [
    ['event' => 'order.created', 'order_id' => 1],
    ['event' => 'order.created', 'order_id' => 2],
    ['event' => 'order.created', 'order_id' => 3]
]);

// Subscribe and pull messages
$messages = $pubsub->pull('my-subscription', 10);

foreach($messages as $message) {
    $data = $message['data'];

    // Process message
    processEvent($data);

    // Acknowledge message
    $pubsub->acknowledge('my-subscription', $message['ackId']);
}
```

### Create Topic and Subscription

```php
$pubsub = $this->core->loadClass('PubSub');

// Create topic
$pubsub->createTopic('events');

// Create subscription
$pubsub->createSubscription('events', 'events-processor');
```

**See:** [PubSub Class Reference](../api-reference/PubSub.md)

---

## Best Practices

### 1. Use Environment Variables

Store sensitive configuration in environment variables or Secret Manager:

```php
// Instead of hardcoding
$projectId = $_ENV['GCP_PROJECT_ID'];
$bucket = $_ENV['GCP_BUCKET'];

// Or use Secret Manager
$secrets = $this->core->loadClass('GoogleSecrets');
$apiKey = $secrets->getSecret('third-party-api-key');
```

### 2. Implement Error Handling

Always check for errors:

```php
$ds = $this->core->loadClass('DataStore', ['Users']);
$user = $ds->createEntity($data);

if($ds->error) {
    $this->core->logs->add($ds->errorMsg, 'datastore', 'error');
    return $this->setError('Failed to create user', $ds->errorCode);
}
```

### 3. Use Caching

Enable caching for frequently accessed data:

```php
$ds = $this->core->loadClass('DataStore', ['Users']);
$ds->activateCache(true);

// First call: fetches from Datastore
$users = $ds->fetchAll('*', ['active' => true]);

// Subsequent calls: returns from cache
$users = $ds->fetchAll('*', ['active' => true]);
```

### 4. Optimize Queries

Use indexes and filters effectively:

```php
// Good: Filter at database level
$activeUsers = $ds->fetchAll('*', ['active' => true]);

// Bad: Fetch all and filter in code
$allUsers = $ds->fetchAll();
$activeUsers = array_filter($allUsers, fn($u) => $u['active']);
```

### 5. Use Batch Operations

Batch operations are more efficient:

```php
// Good: Batch insert
$ds->createEntities($multipleUsers);

// Less efficient: Individual inserts
foreach($users as $user) {
    $ds->createEntity($user);
}
```

### 6. Implement Retries

Add retry logic for transient failures:

```php
function uploadWithRetry($buckets, $source, $dest, $maxRetries = 3)
{
    $retries = 0;
    while($retries < $maxRetries) {
        $result = $buckets->upload($source, $dest);
        if($result) {
            return $result;
        }
        $retries++;
        sleep(pow(2, $retries)); // Exponential backoff
    }
    return false;
}
```

### 7. Monitor Costs

Be aware of GCP costs:
- Datastore: Charged per read/write operation
- Cloud Storage: Charged per storage and operations
- BigQuery: Charged per query data processed
- Use GCP cost monitoring tools

### 8. Secure Service Accounts

- Use least privilege principle
- Rotate keys regularly
- Never commit service account keys to version control
- Use Secret Manager for production

---

## Complete Example: User Management with GCP

```php
<?php
class API extends RESTful
{
    private $ds;
    private $buckets;
    private $pubsub;

    function main()
    {
        // Initialize GCP services
        $this->ds = $this->core->loadClass('DataStore', ['Users']);
        $this->ds->activateCache(true);

        $this->buckets = $this->core->loadClass('Buckets', [
            $this->core->config->get('core.gcp.storage.bucket')
        ]);

        $this->pubsub = $this->core->loadClass('PubSub');

        // Route to endpoints
        $endpoint = $this->params[0] ?? 'list';
        if(!$this->useFunction('ENDPOINT_' . $endpoint)) {
            return $this->setErrorFromCodelib('not-found');
        }
    }

    // POST /users/create
    public function ENDPOINT_create()
    {
        if(!$this->checkMethod('POST')) return;

        // Validate input
        if(!$this->checkMandatoryFormParams(['name', 'email'])) return;

        // Create user in Datastore
        $user = $this->ds->createEntity([
            'name' => $this->formParams['name'],
            'email' => $this->formParams['email'],
            'created_at' => new DateTime(),
            'active' => true
        ]);

        if($this->ds->error) {
            $this->core->logs->add($this->ds->errorMsg, 'users', 'error');
            return $this->setError('Failed to create user', 500);
        }

        // Handle profile picture upload
        if(isset($_FILES['photo'])) {
            $photoPath = "gs://my-bucket/users/{$user['KeyId']}/photo.jpg";
            $this->buckets->upload($_FILES['photo']['tmp_name'], $photoPath);
            $user['photo_url'] = $photoPath;
            $this->ds->createEntity($user);
        }

        // Publish event to Pub/Sub
        $this->pubsub->publish('user-events', [
            'event' => 'user.created',
            'user_id' => $user['KeyId'],
            'timestamp' => time()
        ]);

        // Log to BigQuery for analytics
        $bq = $this->core->loadClass('DataBQ', ['analytics', 'user_events']);
        $bq->insert([
            'event_type' => 'user_created',
            'user_id' => $user['KeyId'],
            'timestamp' => new DateTime()
        ]);

        $this->setReturnStatus(201);
        $this->addReturnData($user);
    }
}
```

---

## Next Steps

- [DataStore Class Reference](../api-reference/DataStore.md)
- [Buckets Class Reference](../api-reference/Buckets.md)
- [Deployment Guide](deployment.md)
- [Security Best Practices](security.md)
