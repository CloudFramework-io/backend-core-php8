# Deployment Guide

## Overview

CloudFramework Backend Core PHP8 can be deployed to various environments:
- Google App Engine (recommended)
- Google Cloud Functions
- Google Cloud Run
- Traditional servers (Apache, Nginx)
- Docker containers

This guide covers deployment strategies, configuration, and best practices for each platform.

---

## Table of Contents

1. [Pre-Deployment Checklist](#pre-deployment-checklist)
2. [Google App Engine](#google-app-engine)
3. [Google Cloud Functions](#google-cloud-functions)
4. [Google Cloud Run](#google-cloud-run)
5. [Docker Deployment](#docker-deployment)
6. [Traditional Server Deployment](#traditional-server-deployment)
7. [Environment Configuration](#environment-configuration)
8. [Health Checks & Monitoring](#health-checks--monitoring)
9. [Scaling & Performance](#scaling--performance)
10. [Troubleshooting](#troubleshooting)

---

## Pre-Deployment Checklist

### 1. Code Preparation

- [ ] All dependencies installed via Composer
- [ ] Remove development dependencies: `composer install --no-dev`
- [ ] Configuration files ready (no sensitive data in git)
- [ ] Error handling implemented
- [ ] Logs configured properly

### 2. Security

- [ ] API keys and secrets in environment variables
- [ ] CORS configured for production domains
- [ ] Rate limiting enabled
- [ ] SQL injection protection verified
- [ ] XSS protection implemented

### 3. GCP Setup

- [ ] GCP project created
- [ ] Service account created with appropriate roles
- [ ] APIs enabled (Datastore, Storage, BigQuery, Cloud SQL)
- [ ] Billing enabled

### 4. Database

- [ ] Database schema created
- [ ] Migration scripts ready
- [ ] Backup strategy in place
- [ ] Connection pooling configured

### 5. Testing

- [ ] All tests passing
- [ ] Load testing completed
- [ ] Security audit performed
- [ ] API endpoints tested

---

## Google App Engine

### Overview

Google App Engine is a fully managed platform ideal for CloudFramework applications. It provides:
- Automatic scaling
- Built-in load balancing
- Zero-downtime deployments
- Direct Cloud SQL connections
- Integrated monitoring

### Prerequisites

1. Install Google Cloud SDK:
```bash
# macOS
brew install google-cloud-sdk

# Or download from:
# https://cloud.google.com/sdk/docs/install
```

2. Initialize and authenticate:
```bash
gcloud init
gcloud auth login
gcloud config set project YOUR_PROJECT_ID
```

### Project Structure

```
project/
├── app/
│   ├── api/
│   └── scripts/
├── vendor/
├── app.yaml           # App Engine configuration
├── composer.json
├── config.json
└── .gcloudignore      # Files to ignore during deployment
```

### app.yaml Configuration

**Basic Configuration:**

```yaml
runtime: php83

env_variables:
  GCP_PROJECT_ID: 'my-project-123'
  DB_PASSWORD: 'production_password'
  ENCRYPTION_KEY: 'your-encryption-key'

# Recommended for APIs
automatic_scaling:
  min_instances: 1
  max_instances: 10
  target_cpu_utilization: 0.65
  target_throughput_utilization: 0.6

# Instance resources
instance_class: F2

# Handlers
handlers:
  # Serve static files
  - url: /static
    static_dir: static
    secure: always

  # All other requests to index.php
  - url: /.*
    script: auto
    secure: always
```

**Advanced Configuration with Cloud SQL:**

```yaml
runtime: php83

env_variables:
  GCP_PROJECT_ID: 'my-project-123'
  DB_PASSWORD: 'production_password'
  CACHE_TYPE: 'datastore'

# Cloud SQL connection
vpc_access_connector:
  name: "projects/my-project/locations/us-central1/connectors/my-connector"

# Connect to Cloud SQL via Unix socket
beta_settings:
  cloud_sql_instances: "my-project:us-central1:main-instance"

automatic_scaling:
  min_instances: 2
  max_instances: 20
  target_cpu_utilization: 0.65

instance_class: F4

handlers:
  - url: /static
    static_dir: static
    secure: always
    expiration: "1d"

  - url: /.*
    script: auto
    secure: always
```

**Configuration with Multiple Services:**

```yaml
# default service (app.yaml)
runtime: php83
service: default

env_variables:
  SERVICE_NAME: 'api'

automatic_scaling:
  min_instances: 2
  max_instances: 20

handlers:
  - url: /.*
    script: auto
    secure: always
```

```yaml
# admin service (admin.yaml)
runtime: php83
service: admin

env_variables:
  SERVICE_NAME: 'admin'

basic_scaling:
  max_instances: 2
  idle_timeout: 10m

handlers:
  - url: /.*
    script: auto
    secure: always
    login: admin  # Requires Google account login
```

### .gcloudignore File

Create a `.gcloudignore` file to exclude unnecessary files:

```
# Ignore development files
.git/
.gitignore
.env
local_config.json
local_script.json

# Ignore tests
tests/
phpunit.xml

# Ignore documentation
docs/
*.md
README.md

# Ignore development dependencies
/vendor/phpunit/
/vendor/mockery/

# Ignore IDE files
.idea/
.vscode/
*.swp
*.swo

# Ignore logs
*.log
logs/

# Ignore cache
cache/
tmp/
```

### Deployment Commands

**Deploy default service:**
```bash
gcloud app deploy
```

**Deploy with specific version:**
```bash
gcloud app deploy --version=v1-0-0 --no-promote
```

**Deploy multiple services:**
```bash
gcloud app deploy app.yaml admin.yaml
```

**Deploy and view logs:**
```bash
gcloud app deploy && gcloud app logs tail -s default
```

**Set traffic splitting:**
```bash
# Split traffic 50/50 between versions
gcloud app services set-traffic default --splits v1=0.5,v2=0.5

# Migrate all traffic to new version
gcloud app services set-traffic default --splits v2=1.0 --migrate
```

### Post-Deployment

**View application:**
```bash
gcloud app browse
```

**View logs:**
```bash
gcloud app logs tail -s default
```

**Check versions:**
```bash
gcloud app versions list
```

**Delete old versions:**
```bash
gcloud app versions delete v1-0-0
```

---

## Google Cloud Functions

### Overview

Cloud Functions is ideal for:
- Single API endpoints
- Webhooks
- Event-driven tasks
- Microservices architecture

### Project Structure

```
function/
├── vendor/
├── composer.json
├── config.json
├── index.php           # Entry point
└── .gcloudignore
```

### index.php Entry Point

```php
<?php
require_once __DIR__ . '/vendor/autoload.php';

use CloudFramework\Core;
use CloudFramework\Patterns\RESTful;

class API extends RESTful
{
    function main()
    {
        // Your API logic
        $endpoint = $this->params[0] ?? 'default';
        $this->useFunction('ENDPOINT_' . $endpoint);
    }

    public function ENDPOINT_default()
    {
        $this->addReturnData(['message' => 'Hello from Cloud Function']);
    }

    public function ENDPOINT_users()
    {
        if(!$this->checkMethod('GET')) return;

        $ds = $this->core->loadClass('DataStore', ['Users']);
        $users = $ds->fetchAll();

        $this->addReturnData($users);
    }
}

// Handle the request
$core = new Core();
$api = new API($core);
$api->execute();
$api->send();
```

### Deployment

**Deploy HTTP function:**
```bash
gcloud functions deploy my-api \
  --runtime php83 \
  --trigger-http \
  --allow-unauthenticated \
  --entry-point=index.php \
  --set-env-vars GCP_PROJECT_ID=my-project,DB_PASSWORD=secret \
  --region us-central1 \
  --memory 256MB \
  --timeout 60s
```

**Deploy with service account:**
```bash
gcloud functions deploy my-api \
  --runtime php83 \
  --trigger-http \
  --entry-point=index.php \
  --service-account my-function@my-project.iam.gserviceaccount.com \
  --set-env-vars GCP_PROJECT_ID=my-project \
  --region us-central1
```

**Deploy Pub/Sub triggered function:**
```bash
gcloud functions deploy process-events \
  --runtime php83 \
  --trigger-topic my-topic \
  --entry-point=processEvent \
  --set-env-vars GCP_PROJECT_ID=my-project \
  --region us-central1
```

### Get function URL:
```bash
gcloud functions describe my-api --region us-central1 --format="value(httpsTrigger.url)"
```

### View logs:
```bash
gcloud functions logs read my-api --region us-central1 --limit 50
```

---

## Google Cloud Run

### Overview

Cloud Run combines the flexibility of containers with serverless benefits:
- Custom Docker images
- Any dependencies
- Full control over environment
- Automatic scaling
- Pay per use

### Project Structure

```
project/
├── app/
├── vendor/
├── Dockerfile
├── .dockerignore
├── composer.json
├── config.json
└── nginx.conf
```

### Dockerfile

```dockerfile
# Use official PHP 8.3 image
FROM php:8.3-fpm-alpine

# Install system dependencies
RUN apk add --no-cache \
    nginx \
    supervisor \
    git \
    zip \
    unzip

# Install PHP extensions
RUN docker-php-ext-install pdo pdo_mysql opcache

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /app

# Copy composer files
COPY composer.json composer.lock ./

# Install dependencies
RUN composer install --no-dev --optimize-autoloader --no-interaction

# Copy application
COPY . .

# Copy nginx config
COPY nginx.conf /etc/nginx/nginx.conf

# Copy supervisor config
COPY supervisord.conf /etc/supervisord.conf

# Expose port
EXPOSE 8080

# Start supervisor (manages nginx + php-fpm)
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisord.conf"]
```

### nginx.conf

```nginx
worker_processes auto;
daemon off;

events {
    worker_connections 1024;
}

http {
    include mime.types;
    default_type application/octet-stream;

    sendfile on;
    keepalive_timeout 65;

    upstream php-fpm {
        server 127.0.0.1:9000;
    }

    server {
        listen 8080;
        server_name _;
        root /app;
        index index.php;

        location / {
            try_files $uri $uri/ /index.php?$query_string;
        }

        location ~ \.php$ {
            fastcgi_pass php-fpm;
            fastcgi_index index.php;
            fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
            include fastcgi_params;
        }

        location ~ /\. {
            deny all;
        }
    }
}
```

### supervisord.conf

```ini
[supervisord]
nodaemon=true
user=root

[program:php-fpm]
command=php-fpm -F
autostart=true
autorestart=true
stdout_logfile=/dev/stdout
stdout_logfile_maxbytes=0
stderr_logfile=/dev/stderr
stderr_logfile_maxbytes=0

[program:nginx]
command=nginx
autostart=true
autorestart=true
stdout_logfile=/dev/stdout
stdout_logfile_maxbytes=0
stderr_logfile=/dev/stderr
stderr_logfile_maxbytes=0
```

### .dockerignore

```
.git
.gitignore
.env
local_config.json
local_script.json
tests/
docs/
*.md
.idea/
.vscode/
cache/
tmp/
logs/
```

### Build and Deploy

**Build locally:**
```bash
docker build -t gcr.io/my-project/my-app:v1 .
```

**Test locally:**
```bash
docker run -p 8080:8080 \
  -e GCP_PROJECT_ID=my-project \
  -e DB_PASSWORD=password \
  gcr.io/my-project/my-app:v1
```

**Push to Container Registry:**
```bash
docker push gcr.io/my-project/my-app:v1
```

**Deploy to Cloud Run:**
```bash
gcloud run deploy my-app \
  --image gcr.io/my-project/my-app:v1 \
  --platform managed \
  --region us-central1 \
  --allow-unauthenticated \
  --set-env-vars GCP_PROJECT_ID=my-project,DB_PASSWORD=secret \
  --memory 512Mi \
  --cpu 1 \
  --max-instances 10 \
  --min-instances 1 \
  --timeout 300
```

**Deploy with Cloud SQL:**
```bash
gcloud run deploy my-app \
  --image gcr.io/my-project/my-app:v1 \
  --platform managed \
  --region us-central1 \
  --add-cloudsql-instances my-project:us-central1:main-instance \
  --set-env-vars DB_SOCKET=/cloudsql/my-project:us-central1:main-instance \
  --memory 1Gi \
  --cpu 2
```

**Deploy using gcloud build:**
```bash
# Build and deploy in one command
gcloud run deploy my-app \
  --source . \
  --platform managed \
  --region us-central1 \
  --allow-unauthenticated
```

---

## Docker Deployment

### Docker Compose for Development

**docker-compose.yml:**

```yaml
version: '3.8'

services:
  app:
    build: .
    ports:
      - "8080:8080"
    environment:
      - GCP_PROJECT_ID=my-project-dev
      - DB_HOST=mysql
      - DB_USER=root
      - DB_PASSWORD=password
      - DB_NAME=myapp
      - CACHE_TYPE=file
    volumes:
      - ./:/app
      - ./cache:/tmp/cache
    depends_on:
      - mysql

  mysql:
    image: mysql:8.0
    environment:
      - MYSQL_ROOT_PASSWORD=password
      - MYSQL_DATABASE=myapp
    ports:
      - "3306:3306"
    volumes:
      - mysql_data:/var/lib/mysql

volumes:
  mysql_data:
```

**Run with Docker Compose:**
```bash
docker-compose up -d
docker-compose logs -f app
docker-compose down
```

---

## Traditional Server Deployment

### Apache Configuration

**Create VirtualHost (.htaccess):**

```apache
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteBase /

    # Redirect to HTTPS
    RewriteCond %{HTTPS} off
    RewriteRule ^(.*)$ https://%{HTTP_HOST}/$1 [R=301,L]

    # Route all requests to index.php
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteRule ^(.*)$ index.php/$1 [L,QSA]
</IfModule>

# Security headers
<IfModule mod_headers.c>
    Header set X-Content-Type-Options "nosniff"
    Header set X-Frame-Options "SAMEORIGIN"
    Header set X-XSS-Protection "1; mode=block"
</IfModule>

# Disable directory listing
Options -Indexes

# Protect sensitive files
<FilesMatch "^(config\.json|composer\.(json|lock)|\.env)$">
    Order allow,deny
    Deny from all
</FilesMatch>
```

**VirtualHost Configuration:**

```apache
<VirtualHost *:80>
    ServerName api.example.com
    DocumentRoot /var/www/html/api

    <Directory /var/www/html/api>
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog ${APACHE_LOG_DIR}/api_error.log
    CustomLog ${APACHE_LOG_DIR}/api_access.log combined
</VirtualHost>
```

### Nginx Configuration

```nginx
server {
    listen 80;
    server_name api.example.com;
    root /var/www/html/api;
    index index.php;

    # Logging
    access_log /var/log/nginx/api_access.log;
    error_log /var/log/nginx/api_error.log;

    # Security headers
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-XSS-Protection "1; mode=block" always;

    # Route all requests to index.php
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    # PHP-FPM configuration
    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }

    # Deny access to sensitive files
    location ~ /(config\.json|composer\.(json|lock)|\.env) {
        deny all;
        return 404;
    }

    # Deny access to hidden files
    location ~ /\. {
        deny all;
        return 404;
    }
}
```

### PHP Configuration (php.ini)

```ini
; Memory and execution time
memory_limit = 256M
max_execution_time = 60
upload_max_filesize = 50M
post_max_size = 50M

; Error handling (production)
display_errors = Off
display_startup_errors = Off
error_reporting = E_ALL & ~E_DEPRECATED & ~E_STRICT
log_errors = On
error_log = /var/log/php/error.log

; Session
session.save_handler = files
session.save_path = "/tmp"

; Opcache (highly recommended)
opcache.enable=1
opcache.memory_consumption=128
opcache.interned_strings_buffer=8
opcache.max_accelerated_files=10000
opcache.revalidate_freq=60
```

---

## Environment Configuration

### Production config.json

```json
{
  "core": {
    "project": {
      "environment": "production"
    },
    "gcp": {
      "project_id": "${GCP_PROJECT_ID}",
      "service_account": "/app/credentials/service-account.json"
    },
    "cache": {
      "cache_type": "datastore",
      "default_ttl": 3600
    }
  },
  "dbSocket": "/cloudsql/${CLOUD_SQL_INSTANCE}",
  "dbPassword": "${DB_PASSWORD}",
  "api": {
    "cors": {
      "enabled": true,
      "allowed_origins": ["https://app.example.com"]
    },
    "response": {
      "pretty_print": false,
      "include_performance": false
    }
  }
}
```

### Environment Variables

Set these in your deployment platform:

```bash
# GCP
GCP_PROJECT_ID=my-project-123
GCP_SERVICE_ACCOUNT=/path/to/service-account.json

# Database
DB_PASSWORD=secure_production_password
CLOUD_SQL_INSTANCE=my-project:us-central1:main-db

# Security
ENCRYPTION_KEY=your-32-character-key
API_KEY_1=production-api-key-1
API_KEY_2=production-api-key-2

# Application
CACHE_TYPE=datastore
ENVIRONMENT=production
```

---

## Health Checks & Monitoring

### Health Check Endpoint

**Create health check API:**

```php
<?php
class API extends RESTful
{
    public function ENDPOINT_health()
    {
        $health = [
            'status' => 'ok',
            'timestamp' => date('c'),
            'checks' => []
        ];

        // Check database
        try {
            $sql = $this->core->loadClass('CloudSQL');
            if(!$sql->error()) {
                $health['checks']['database'] = 'ok';
            } else {
                $health['checks']['database'] = 'error';
                $health['status'] = 'degraded';
            }
        } catch(Exception $e) {
            $health['checks']['database'] = 'error';
            $health['status'] = 'degraded';
        }

        // Check cache
        try {
            $this->core->cache->set('health_check', 'ok', 10);
            $value = $this->core->cache->get('health_check');
            $health['checks']['cache'] = ($value === 'ok') ? 'ok' : 'error';
        } catch(Exception $e) {
            $health['checks']['cache'] = 'error';
        }

        // Check Datastore
        try {
            $ds = $this->core->loadClass('DataStore', ['__health__']);
            $health['checks']['datastore'] = 'ok';
        } catch(Exception $e) {
            $health['checks']['datastore'] = 'error';
            $health['status'] = 'degraded';
        }

        $statusCode = ($health['status'] === 'ok') ? 200 : 503;
        $this->setReturnStatus($statusCode);
        $this->addReturnData($health);
    }
}
```

### App Engine Health Check

**app.yaml:**
```yaml
health_check:
  enable_health_check: True
  check_interval_sec: 30
  timeout_sec: 4
  unhealthy_threshold: 2
  healthy_threshold: 2

liveness_check:
  path: "/health"
  check_interval_sec: 30
  timeout_sec: 4
  failure_threshold: 2
  success_threshold: 2

readiness_check:
  path: "/health"
  check_interval_sec: 5
  timeout_sec: 4
  failure_threshold: 2
  success_threshold: 2
  app_start_timeout_sec: 300
```

### Cloud Run Health Check

```bash
gcloud run services update my-app \
  --region us-central1 \
  --health-check-path=/health \
  --health-check-interval=30s \
  --health-check-timeout=4s
```

### Logging

```php
// Log important events
$this->core->logs->add('User logged in', 'auth');
$this->core->errors->add('Payment failed', 'payments', 'error');

// Structured logging for Cloud Logging
error_log(json_encode([
    'severity' => 'INFO',
    'message' => 'User action completed',
    'user_id' => $userId,
    'action' => 'purchase',
    'timestamp' => time()
]));
```

---

## Scaling & Performance

### App Engine Scaling Configuration

**Automatic Scaling (recommended):**
```yaml
automatic_scaling:
  min_instances: 2              # Always have 2 instances ready
  max_instances: 20             # Scale up to 20 instances
  target_cpu_utilization: 0.65  # Scale when CPU > 65%
  target_throughput_utilization: 0.6
  max_concurrent_requests: 80
```

**Basic Scaling (for admin/cron services):**
```yaml
basic_scaling:
  max_instances: 3
  idle_timeout: 10m
```

**Manual Scaling:**
```yaml
manual_scaling:
  instances: 5
```

### Cloud Run Scaling

```bash
gcloud run services update my-app \
  --min-instances=2 \
  --max-instances=100 \
  --concurrency=80 \
  --cpu=2 \
  --memory=1Gi
```

### Performance Optimization

**1. Enable OpCache (php.ini):**
```ini
opcache.enable=1
opcache.memory_consumption=256
opcache.max_accelerated_files=20000
opcache.validate_timestamps=0  # Production only
```

**2. Use Datastore Cache:**
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

**3. Optimize Composer:**
```bash
composer install --no-dev --optimize-autoloader --classmap-authoritative
```

**4. Connection Pooling (CloudSQL):**
```php
class API extends RESTful
{
    private static $sqlConnection = null;

    function main()
    {
        if(self::$sqlConnection === null) {
            self::$sqlConnection = $this->core->loadClass('CloudSQL');
        }
        $this->sql = self::$sqlConnection;
    }
}
```

---

## Troubleshooting

### Common Deployment Issues

**1. 502 Bad Gateway**
- Check application logs: `gcloud app logs tail -s default`
- Verify all dependencies installed
- Check PHP version compatibility
- Increase instance resources

**2. Service Account Permissions**
```bash
# Grant required roles
gcloud projects add-iam-policy-binding my-project \
  --member="serviceAccount:my-service@my-project.iam.gserviceaccount.com" \
  --role="roles/datastore.user"

gcloud projects add-iam-policy-binding my-project \
  --member="serviceAccount:my-service@my-project.iam.gserviceaccount.com" \
  --role="roles/storage.objectAdmin"
```

**3. CloudSQL Connection Timeout**
- Verify socket path: `/cloudsql/project:region:instance`
- Check Cloud SQL instance is running
- Verify service account has Cloud SQL Client role
- Enable Cloud SQL Admin API

**4. Memory Exceeded**
- Increase instance class in app.yaml: `instance_class: F4`
- Optimize queries and data structures
- Implement pagination
- Use caching effectively

**5. Slow Cold Starts**
- Set min_instances > 0
- Reduce composer dependencies
- Enable OpCache
- Use Cloud Run over Cloud Functions for large apps

### Debug Mode

**Enable debug in development:**

```json
{
  "core": {
    "debug": {
      "enabled": true,
      "display_errors": true,
      "log_queries": true
    }
  }
}
```

**View detailed errors:**
```php
ini_set('display_errors', 1);
error_reporting(E_ALL);
```

### Rollback Deployment

**App Engine:**
```bash
# List versions
gcloud app versions list

# Migrate traffic back
gcloud app services set-traffic default --splits v1=1.0 --migrate

# Delete bad version
gcloud app versions delete v2
```

**Cloud Run:**
```bash
# Deploy previous revision
gcloud run services update-traffic my-app \
  --to-revisions=my-app-00001-abc=100
```

---

## See Also

- [Configuration Guide](configuration.md)
- [Security Guide](security.md)
- [GCP Integration Guide](gcp-integration.md)
- [Testing Guide](testing.md)
