# Security Guide

## Overview

Security is critical for any web application. CloudFramework provides built-in security features and follows best practices to protect your APIs and data. This guide covers:

- Authentication and authorization
- Input validation and sanitization
- Protection against common vulnerabilities
- Secure data handling
- GCP security integration
- Security monitoring and auditing

---

## Table of Contents

1. [Security Checklist](#security-checklist)
2. [Authentication](#authentication)
3. [Authorization](#authorization)
4. [Input Validation](#input-validation)
5. [SQL Injection Prevention](#sql-injection-prevention)
6. [XSS Protection](#xss-protection)
7. [CSRF Protection](#csrf-protection)
8. [CORS Configuration](#cors-configuration)
9. [Rate Limiting](#rate-limiting)
10. [Encryption & Data Protection](#encryption--data-protection)
11. [Secure File Uploads](#secure-file-uploads)
12. [Security Headers](#security-headers)
13. [Logging & Auditing](#logging--auditing)
14. [GCP Security](#gcp-security)
15. [Common Vulnerabilities](#common-vulnerabilities)

---

## Security Checklist

### Pre-Production Checklist

- [ ] Authentication implemented for protected endpoints
- [ ] Authorization checks in place
- [ ] All user inputs validated and sanitized
- [ ] Parameterized queries used (no string concatenation)
- [ ] XSS protection implemented
- [ ] CORS properly configured
- [ ] Rate limiting enabled
- [ ] Sensitive data encrypted
- [ ] Security headers configured
- [ ] Error messages don't expose sensitive information
- [ ] Logging and monitoring enabled
- [ ] Service account permissions minimized
- [ ] Secrets stored in environment variables
- [ ] Own random cache and encryption keys configured, one pair per environment (see [Framework Encryption Keys](#framework-encryption-keys))
- [ ] Stored secrets encrypted with a Cloud KMS key where possible (see [Cloud KMS Data Keys (v3)](#cloud-kms-data-keys-v3))
- [ ] HTTPS enforced
- [ ] File upload validation implemented

---

## Authentication

### API Key Authentication

**Configuration (config.json):**

```json
{
  "security": {
    "api_keys": {
      "enabled": true,
      "header_name": "X-API-Key",
      "valid_keys": [
        "${API_KEY_1}",
        "${API_KEY_2}"
      ]
    }
  }
}
```

**Implementation:**

```php
<?php
class API extends RESTful
{
    function main()
    {
        // Validate API key
        if(!$this->validateAPIKey()) {
            return $this->setError('Invalid or missing API key', 401);
        }

        // Continue with API logic
        $endpoint = $this->params[0] ?? 'default';
        $this->useFunction('ENDPOINT_' . $endpoint);
    }

    private function validateAPIKey(): bool
    {
        $apiKey = $_SERVER['HTTP_X_API_KEY'] ?? '';

        if(empty($apiKey)) {
            return false;
        }

        $validKeys = $this->core->config->get('security.api_keys.valid_keys', []);
        return in_array($apiKey, $validKeys);
    }
}
```

**Usage:**

```bash
curl -H "X-API-Key: your-api-key-here" https://api.example.com/users
```

### Token-Based Authentication (JWT)

**Generate JWT Token:**

```php
private function generateJWT(array $payload): string
{
    $secret = $this->core->config->get('security.jwt.secret');
    $algorithm = $this->core->config->get('security.jwt.algorithm', 'HS256');

    $header = base64_encode(json_encode(['alg' => $algorithm, 'typ' => 'JWT']));
    $payload['exp'] = time() + 3600; // 1 hour expiration
    $payload['iat'] = time();

    $payloadEncoded = base64_encode(json_encode($payload));
    $signature = base64_encode(hash_hmac('sha256', "$header.$payloadEncoded", $secret, true));

    return "$header.$payloadEncoded.$signature";
}
```

**Validate JWT Token:**

```php
private function validateJWT(string $token): array|false
{
    $secret = $this->core->config->get('security.jwt.secret');

    $parts = explode('.', $token);
    if(count($parts) !== 3) {
        return false;
    }

    [$header, $payload, $signature] = $parts;

    // Verify signature
    $expectedSignature = base64_encode(hash_hmac('sha256', "$header.$payload", $secret, true));
    if($signature !== $expectedSignature) {
        return false;
    }

    // Decode payload
    $payloadData = json_decode(base64_decode($payload), true);

    // Check expiration
    if(isset($payloadData['exp']) && $payloadData['exp'] < time()) {
        return false;
    }

    return $payloadData;
}
```

**Login Endpoint:**

```php
public function ENDPOINT_login()
{
    if(!$this->checkMethod('POST')) return;
    if(!$this->checkMandatoryFormParams(['email', 'password'])) return;

    $email = $this->formParams['email'];
    $password = $this->formParams['password'];

    // Verify credentials (example with Datastore)
    $ds = $this->core->loadClass('DataStore', ['Users']);
    $users = $ds->fetchAll([['email', '=', $email]], null, 1);

    if(empty($users)) {
        return $this->setError('Invalid credentials', 401);
    }

    $user = $users[0];

    // Verify password
    if(!password_verify($password, $user['password'])) {
        return $this->setError('Invalid credentials', 401);
    }

    // Generate JWT token
    $token = $this->generateJWT([
        'user_id' => $user['KeyId'],
        'email' => $user['email'],
        'role' => $user['role'] ?? 'user'
    ]);

    $this->addReturnData([
        'token' => $token,
        'user' => [
            'id' => $user['KeyId'],
            'email' => $user['email'],
            'role' => $user['role'] ?? 'user'
        ]
    ]);
}
```

**Protected Endpoint:**

```php
function main()
{
    // Extract token from Authorization header
    $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';

    if(!preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
        return $this->setError('Missing authorization token', 401);
    }

    $token = $matches[1];
    $payload = $this->validateJWT($token);

    if(!$payload) {
        return $this->setError('Invalid or expired token', 401);
    }

    // Store user info for use in endpoints
    $this->currentUser = $payload;

    // Continue with API logic
    $endpoint = $this->params[0] ?? 'default';
    $this->useFunction('ENDPOINT_' . $endpoint);
}
```

### HTTP Basic Authentication

```php
function main()
{
    if(!$this->core->security->existBasicAuth()) {
        header('WWW-Authenticate: Basic realm="Protected Area"');
        return $this->setError('Authentication required', 401);
    }

    $username = $_SERVER['PHP_AUTH_USER'];
    $password = $_SERVER['PHP_AUTH_PW'];

    // Verify credentials
    if(!$this->verifyCredentials($username, $password)) {
        return $this->setError('Invalid credentials', 401);
    }

    // Continue with API logic
}

private function verifyCredentials(string $username, string $password): bool
{
    $users = $this->core->config->get('security.basic_auth.users', []);

    if(!isset($users[$username])) {
        return false;
    }

    return password_verify($password, $users[$username]);
}
```

### Google OAuth2 Authentication

```php
// Verify Google ID Token
private function verifyGoogleToken(string $idToken): array|false
{
    $client = new Google\Client();
    $client->setAuthConfig($this->core->config->get('core.gcp.service_account'));

    try {
        $payload = $client->verifyIdToken($idToken);

        if($payload) {
            return [
                'user_id' => $payload['sub'],
                'email' => $payload['email'],
                'name' => $payload['name'],
                'picture' => $payload['picture']
            ];
        }
    } catch(Exception $e) {
        $this->core->errors->add($e->getMessage(), 'auth', 'error');
    }

    return false;
}
```

---

## Authorization

### Role-Based Access Control (RBAC)

```php
<?php
class API extends RESTful
{
    private $currentUser;
    private $roles = [
        'admin' => ['users:read', 'users:write', 'users:delete', 'settings:write'],
        'editor' => ['users:read', 'users:write'],
        'viewer' => ['users:read']
    ];

    function main()
    {
        // Authenticate user first
        if(!$this->authenticate()) {
            return $this->setError('Authentication required', 401);
        }

        // Route to endpoints
        $endpoint = $this->params[0] ?? 'list';
        $this->useFunction('ENDPOINT_' . $endpoint);
    }

    public function ENDPOINT_list()
    {
        if(!$this->checkPermission('users:read')) {
            return $this->setError('Insufficient permissions', 403);
        }

        // Fetch users
        $ds = $this->core->loadClass('DataStore', ['Users']);
        $users = $ds->fetchAll();

        $this->addReturnData($users);
    }

    public function ENDPOINT_create()
    {
        if(!$this->checkPermission('users:write')) {
            return $this->setError('Insufficient permissions', 403);
        }

        // Create user logic
    }

    public function ENDPOINT_delete()
    {
        if(!$this->checkPermission('users:delete')) {
            return $this->setError('Insufficient permissions', 403);
        }

        // Delete user logic
    }

    private function checkPermission(string $permission): bool
    {
        $userRole = $this->currentUser['role'] ?? 'viewer';
        $permissions = $this->roles[$userRole] ?? [];

        return in_array($permission, $permissions);
    }

    private function authenticate(): bool
    {
        // Authentication logic (JWT, API key, etc.)
        // Set $this->currentUser on success
        return true;
    }
}
```

### Resource Ownership Validation

```php
public function ENDPOINT_update()
{
    if(!$this->checkMethod('PUT')) return;

    $userId = $this->params[1] ?? null;
    if(!$userId) {
        return $this->setError('User ID required', 400);
    }

    // Check if user can modify this resource
    if(!$this->canAccessResource($userId)) {
        return $this->setError('Access denied', 403);
    }

    // Update user logic
}

private function canAccessResource(string $resourceUserId): bool
{
    $currentUserId = $this->currentUser['user_id'];
    $currentUserRole = $this->currentUser['role'];

    // Admins can access any resource
    if($currentUserRole === 'admin') {
        return true;
    }

    // Users can only access their own resources
    return $currentUserId === $resourceUserId;
}
```

---

## Input Validation

### Validate Form Parameters

```php
public function ENDPOINT_create()
{
    if(!$this->checkMethod('POST')) return;

    // Check mandatory fields
    if(!$this->checkMandatoryFormParams(['name', 'email', 'age'])) return;

    // Validate email format
    if(!filter_var($this->formParams['email'], FILTER_VALIDATE_EMAIL)) {
        return $this->setError('Invalid email format', 400);
    }

    // Validate age range
    $age = (int)$this->formParams['age'];
    if($age < 18 || $age > 120) {
        return $this->setError('Age must be between 18 and 120', 400);
    }

    // Validate name length
    $name = trim($this->formParams['name']);
    if(strlen($name) < 2 || strlen($name) > 100) {
        return $this->setError('Name must be between 2 and 100 characters', 400);
    }

    // Sanitize inputs
    $sanitizedData = [
        'name' => htmlspecialchars($name, ENT_QUOTES, 'UTF-8'),
        'email' => filter_var($this->formParams['email'], FILTER_SANITIZE_EMAIL),
        'age' => $age
    ];

    // Continue with creation
}
```

### Custom Validation Function

```php
private function validateInput(array $rules): bool
{
    foreach($rules as $field => $fieldRules) {
        $value = $this->formParams[$field] ?? null;

        // Required check
        if(in_array('required', $fieldRules) && empty($value)) {
            $this->setError("Field '{$field}' is required", 400);
            return false;
        }

        // Email validation
        if(in_array('email', $fieldRules) && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
            $this->setError("Field '{$field}' must be a valid email", 400);
            return false;
        }

        // Numeric validation
        if(in_array('numeric', $fieldRules) && !is_numeric($value)) {
            $this->setError("Field '{$field}' must be numeric", 400);
            return false;
        }

        // Min length
        foreach($fieldRules as $rule) {
            if(preg_match('/^min:(\d+)$/', $rule, $matches)) {
                if(strlen($value) < $matches[1]) {
                    $this->setError("Field '{$field}' must be at least {$matches[1]} characters", 400);
                    return false;
                }
            }
        }

        // Max length
        foreach($fieldRules as $rule) {
            if(preg_match('/^max:(\d+)$/', $rule, $matches)) {
                if(strlen($value) > $matches[1]) {
                    $this->setError("Field '{$field}' must not exceed {$matches[1]} characters", 400);
                    return false;
                }
            }
        }
    }

    return true;
}

public function ENDPOINT_create()
{
    if(!$this->checkMethod('POST')) return;

    $rules = [
        'name' => ['required', 'min:2', 'max:100'],
        'email' => ['required', 'email'],
        'age' => ['required', 'numeric']
    ];

    if(!$this->validateInput($rules)) {
        return; // Error already set
    }

    // Continue with creation
}
```

---

## SQL Injection Prevention

### Always Use Parameterized Queries

**Good (Safe):**

```php
// Using parameterized queries
$users = $sql->command(
    "SELECT * FROM users WHERE email = ? AND status = ?",
    [$email, $status]
);

// Multiple parameters
$orders = $sql->command(
    "SELECT * FROM orders WHERE user_id = ? AND created_at >= ? AND status IN (?, ?)",
    [$userId, $startDate, 'completed', 'shipped']
);

// INSERT with parameters
$sql->command(
    "INSERT INTO users (name, email, age) VALUES (?, ?, ?)",
    [$name, $email, $age]
);
```

**Bad (Vulnerable):**

```php
// NEVER DO THIS - Vulnerable to SQL injection
$users = $sql->command("SELECT * FROM users WHERE email = '{$email}'");

// NEVER DO THIS - String concatenation
$users = $sql->command("SELECT * FROM users WHERE email = '" . $email . "'");
```

### DataStore Security

DataStore queries are inherently safe from SQL injection, but validate input types:

```php
$ds = $this->core->loadClass('DataStore', ['Users']);

// Safe - using proper WHERE clauses
$users = $ds->fetchAll([
    ['email', '=', $email],
    ['age', '>', $minAge]
]);

// Validate input types
$age = (int)$this->formParams['age']; // Ensure integer
$email = filter_var($this->formParams['email'], FILTER_VALIDATE_EMAIL);
```

---

## XSS Protection

### Output Encoding

```php
// Encode output in API responses
public function ENDPOINT_users()
{
    $ds = $this->core->loadClass('DataStore', ['Users']);
    $users = $ds->fetchAll();

    // Sanitize output
    foreach($users as &$user) {
        $user['name'] = htmlspecialchars($user['name'], ENT_QUOTES, 'UTF-8');
        $user['bio'] = htmlspecialchars($user['bio'], ENT_QUOTES, 'UTF-8');
    }

    $this->addReturnData($users);
}
```

### Content Security Policy (CSP)

```php
function main()
{
    // Set CSP header
    header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'");

    // Continue with API logic
}
```

### Strip Dangerous HTML Tags

```php
private function sanitizeHTML(string $html): string
{
    // Allow only safe tags
    $allowedTags = '<p><br><strong><em><u><a>';
    return strip_tags($html, $allowedTags);
}

public function ENDPOINT_update_profile()
{
    if(!$this->checkMethod('PUT')) return;

    $bio = $this->formParams['bio'] ?? '';
    $sanitizedBio = $this->sanitizeHTML($bio);

    // Save sanitized bio
}
```

---

## CSRF Protection

### Token-Based CSRF Protection

```php
class API extends RESTful
{
    function main()
    {
        // Check CSRF token for state-changing methods
        if(in_array($_SERVER['REQUEST_METHOD'], ['POST', 'PUT', 'DELETE'])) {
            if(!$this->validateCSRFToken()) {
                return $this->setError('Invalid CSRF token', 403);
            }
        }

        // Continue with API logic
    }

    private function generateCSRFToken(): string
    {
        if(!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    private function validateCSRFToken(): bool
    {
        $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $this->formParams['csrf_token'] ?? '';
        $sessionToken = $_SESSION['csrf_token'] ?? '';

        return hash_equals($sessionToken, $token);
    }

    public function ENDPOINT_get_token()
    {
        $this->addReturnData(['csrf_token' => $this->generateCSRFToken()]);
    }
}
```

---

## CORS Configuration

### Secure CORS Setup

**Production (config.json):**

```json
{
  "api": {
    "cors": {
      "enabled": true,
      "allowed_origins": [
        "https://app.example.com",
        "https://admin.example.com"
      ],
      "allowed_methods": ["GET", "POST", "PUT", "DELETE"],
      "allowed_headers": ["Content-Type", "Authorization", "X-API-Key"],
      "allow_credentials": true,
      "max_age": 86400
    }
  }
}
```

**Implementation:**

```php
function main()
{
    // Send CORS headers
    $this->sendCorsHeaders();

    // Handle preflight OPTIONS request
    if($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(204);
        exit;
    }

    // Continue with API logic
}
```

**Never use wildcard with credentials:**

```json
{
  "api": {
    "cors": {
      "allowed_origins": ["*"],
      "allow_credentials": true  // BAD - Don't do this
    }
  }
}
```

---

## Rate Limiting

### IP-Based Rate Limiting

```php
class API extends RESTful
{
    private $rateLimit = 100; // requests per minute
    private $rateLimitWindow = 60; // seconds

    function main()
    {
        // Check rate limit
        if(!$this->checkRateLimit()) {
            return $this->setError('Rate limit exceeded', 429);
        }

        // Continue with API logic
    }

    private function checkRateLimit(): bool
    {
        $ip = $_SERVER['REMOTE_ADDR'];
        $cacheKey = "rate_limit:{$ip}";

        // Get current count
        $count = $this->core->cache->get($cacheKey) ?? 0;

        if($count >= $this->rateLimit) {
            return false;
        }

        // Increment count
        $this->core->cache->set($cacheKey, $count + 1, $this->rateLimitWindow);

        // Set rate limit headers
        header("X-RateLimit-Limit: {$this->rateLimit}");
        header("X-RateLimit-Remaining: " . ($this->rateLimit - $count - 1));

        return true;
    }
}
```

### User-Based Rate Limiting

```php
private function checkRateLimit(): bool
{
    $userId = $this->currentUser['user_id'] ?? 'anonymous';
    $cacheKey = "rate_limit:user:{$userId}";

    $count = $this->core->cache->get($cacheKey) ?? 0;

    if($count >= $this->rateLimit) {
        return false;
    }

    $this->core->cache->set($cacheKey, $count + 1, $this->rateLimitWindow);
    return true;
}
```

---

## Encryption & Data Protection

### Framework Encryption Keys

The framework uses two pairs of keys, plus an optional third pair only to read old data. Generate them yourself: random values, never reused between applications or environments.

| Pair | Protects | Config-vars | Environment variables | Required |
|------|----------|-------------|-----------------------|----------|
| Cache keys | Encrypted cache entries: configuration and `env_vars` secret, security groups, daily subkeys | `core.gcp.secrets.cache_encrypt_key` / `core.gcp.secrets.cache_encrypt_iv` | `CF_CACHE_ENCRYPT_KEY` / `CF_CACHE_ENCRYPT_IV` | **Yes, since 8.5.13** |
| Data keys | `$this->core->security->encrypt()` / `decrypt()` called without explicit keys | `core.security.encrypt_key` / `core.security.encrypt_secret` | `CF_ENCRYPT_KEY` / `CF_ENCRYPT_SECRET` | **Yes, since 8.5.25** |
| Legacy keys | Only reading v1 values (without the `v2:` prefix) that the data keys can not read, with `decrypt()` called without explicit keys | `core.security.legacy_encrypt_key` / `core.security.legacy_encrypt_secret` | `CF_LEGACY_ENCRYPT_KEY` / `CF_LEGACY_ENCRYPT_SECRET` | Only while you keep data encrypted with the built-in default keys of 8.5.24 or before (since 8.5.25) |
| Previous cache keys | Only reading cache entries written with the cache keys before a rotation (since 8.5.27) | `core.gcp.secrets.cache_encrypt_key_previous` / `core.gcp.secrets.cache_encrypt_iv_previous`, or `core.gcp.secrets.cache_keys_secret_previous` | `CF_CACHE_ENCRYPT_KEY_PREVIOUS` / `CF_CACHE_ENCRYPT_IV_PREVIOUS` | Only during a rotation of the cache keys (see [Rotating the cache keys without cuts](#rotating-the-cache-keys-without-cuts)) |

Since 8.5.27 `encrypt()` / `decrypt()` called without keys can use **Cloud KMS keys** instead of the data keys: see [Cloud KMS Data Keys (v3)](#cloud-kms-data-keys-v3). They are key *names*, not secrets, and the cache never uses them.

**Since 8.5.13 the cache keys are mandatory.** There is no built-in default: without both values the entry points (`dispatcher.php`, `mcp-server.php`, `runscript.php`, `runtest.php`) stop before serving anything. HTTP calls get a `503` with a generic message and the detail (`security-cache-key-missing`) goes to the log; scripts print it and exit with code 1.

**Since 8.5.22, in local development the framework generates the cache keys itself when none is configured.** It writes a random pair (32 bytes each, base64) to `local_data/cache-encrypt.json` in the root of the application, with permissions `600`, and reuses it on the next requests, so an application can move its cache keys out of `config.json` without breaking local development. It only happens when all these conditions are true; otherwise nothing changes and `security-cache-key-missing` stops the entry points as above:

- Neither cache key is configured, neither in the config-vars nor in the environment. The configuration and the environment always win over the file, and a half-configured pair is not completed.
- It is not a managed runtime: no `GAE_SERVICE` (App Engine), `K_SERVICE` (Cloud Run) nor `FUNCTION_TARGET` (Cloud Functions), neither in `$_SERVER` nor in the environment.
- The cache is the file cache (`core.cache.cache_path`), never Redis.
- The `local_data` folder already exists and is writable. The framework does not create it.

The request log gets one line (`security-cache-key-local-generated` or `security-cache-key-local-used`), never the value. A `cache-encrypt.json` that is not valid is never overwritten: the error `security-cache-key-local-file-invalid` is logged and the keys stay missing until the file is fixed or deleted. Keep `local_data/` out of the repository and out of every deployment. Since 8.5.23 those lines also say `source=local-file`.

**Since 8.5.23, managed runtimes can read the cache keys from a fixed version of a Secret Manager secret**, so the cache keys can leave `config.json` in production too. Set `core.gcp.secrets.cache_keys_secret` to the full name of a concrete version:

```json
"core.gcp.secrets.cache_keys_secret": "projects/{project}/secrets/{secret}/versions/{number}"
```

- **Content of the version**: a JSON object `{"key": "<base64 of 32 bytes>", "iv": "<base64 of 32 bytes>"}`, the same format and the same validation as `local_data/cache-encrypt.json`. Other properties are ignored. To create it without the values going through the terminal or the shell history:

  ```bash
  printf '{"key":"%s","iv":"%s"}' "$(openssl rand -base64 32)" "$(openssl rand -base64 32)" \
    | gcloud secrets versions add {secret} --data-file=- --project={project}
  ```

- **The version must be a fixed number.** A name that ends in `latest` or in anything that is not a number is never read: the error `security-cache-keys-secret-version-required` is logged (without the configured value) and the keys stay missing, so the entry points answer `503`. The reason: the services that share the same Redis (for example a production and a development service) must use exactly the same keys, and with `latest` a new version of the secret would make the instances started after it use different keys over the same cache.
- **Order of resolution.** The configuration and the environment (`core.gcp.secrets.cache_encrypt_key` / `cache_encrypt_iv`, `CF_CACHE_ENCRYPT_KEY` / `CF_CACHE_ENCRYPT_IV`) always win, and a half-configured pair is not completed. Without them: in a managed runtime (`GAE_SERVICE`, `K_SERVICE` or `FUNCTION_TARGET`) with `cache_keys_secret`, the secret; otherwise the local file of 8.5.22 (local development only); otherwise `security-cache-key-missing`. Outside a managed runtime `cache_keys_secret` is not read; a wrong version name is reported anyway.
- **Read once per instance.** These keys encrypt Redis, so they can not be cached in Redis like the `env_vars` secret. The first request of an instance reads the version and keeps a copy in the instance: APCu when the extension is loaded and enabled, otherwise `/tmp/cf-cache-keys-{hash}.json` (`hash`: 16 hexadecimal characters of the sha256 of the version name) with permissions `600`, written atomically. The next requests use the copy without calling Secret Manager. A corrupt copy is read again from Secret Manager and replaced.
- **When the read fails** (permission, network, the version disabled or destroyed) **or the content is not valid**, no copy is written, the keys stay missing (`503`) and the warning `security-cache-keys-secret-unavailable` is logged with the cause, never with a value. The failure is remembered in the instance for 60 seconds, so the secret is not read on every request; after that the next request tries again.
- **One log line per request, never a value**, saying where the keys came from: `source=secret-manager-read` (syslog, once per instance), `source=instance-copy` (request log) or `source=local-file` (local development).
- **Permission**: the service account of each service that reads it needs `roles/secretmanager.secretAccessor` on that secret (not on the whole project). The App Engine default service account is `{project}@appspot.gserviceaccount.com` unless `service_account` is set in the `app.yaml`.

  ```bash
  gcloud secrets add-iam-policy-binding {secret} --project={project} \
    --member=serviceAccount:{service-account} --role=roles/secretmanager.secretAccessor
  ```

**Rotating the cache keys stored in Secret Manager:**

1. Add a new version to the secret with a new pair (command above). Do not disable the current one.
2. Change `core.gcp.secrets.cache_keys_secret` to the new version number in the configuration of **every** service that shares the same Redis.
3. Deploy those services **at the same time**. The copy of the instance is per version name, so an instance never mixes versions; while old and new instances overlap they use different keys over the same cache, the entries written by one side can not be read by the other and are rebuilt, so keep that overlap short.
4. When no instance of the previous deployments serves traffic, disable the previous version (destroy it later). An old instance restarted after the version is disabled can not read it and answers `503`.

Since 8.5.27 the overlap of step 3 no longer empties the cache: use the previous cache keys as described in [Rotating the cache keys without cuts](#rotating-the-cache-keys-without-cuts).

#### Rotating the cache keys without cuts

**Since 8.5.27 the cache keys can be rotated without losing the cache**, also when several services share the same Redis (for example a production and a development service). Up to 8.5.26, while one service had the new keys and another the old ones, neither could read the entries of the other: each one took them as missing and wrote them again with its own keys, so the cache was rebuilt over and over until every service had the same keys.

A service can now have a **previous pair of cache keys, only to read**:

| Source | Config-vars | Environment variables |
|--------|-------------|-----------------------|
| Values | `core.gcp.secrets.cache_encrypt_key_previous` / `core.gcp.secrets.cache_encrypt_iv_previous` | `CF_CACHE_ENCRYPT_KEY_PREVIOUS` / `CF_CACHE_ENCRYPT_IV_PREVIOUS` (`getenv()` only, like the current ones) |
| Secret Manager | `core.gcp.secrets.cache_keys_secret_previous`: a **fixed** version, `projects/{project}/secrets/{secret}/versions/{number}`, with exactly the same rules as `core.gcp.secrets.cache_keys_secret` (managed runtime only, copy of the instance, failure not retried for 60 seconds, never `latest`) | - |

- **How it is used.** `$this->core->cache->get()` first decrypts with the current keys. Only when they can not read the entry, and only when the keys it was given are the current cache keys of the framework (also with the daily subkeys appended), it tries the previous keys. An entry read that way is returned and **never deleted nor rewritten**: it keeps its age, so its expiration does not change. It is replaced the next time the application writes it.
- **`set()` always writes with the current keys.** No entry is ever written with the previous keys.
- **Keys that an application passes on its own** to `get()` / `set()` are never mapped to the previous keys.
- **Resolved only when needed**: the previous keys are read the first time an entry can not be read in a request, at most once per request. With Secret Manager, the version is read once per instance like the current one. Notes, never with a value: `security-cache-previous-key-read` (an entry was read with the previous keys, request log), `security-cache-previous-key-incomplete` (a half pair, not used), `security-cache-keys-secret-previous-used` / `security-cache-keys-secret-previous-unavailable` and `security-cache-keys-secret-version-required`.
- **Without previous keys everything is as in 8.5.26.** A previous pair equal to the current one is ignored. There is no Cloud KMS in the cache.

**Procedure for every service that shares the same cache (same Redis):**

1. **Prepare.** Generate the new pair (or the new Secret Manager version). Deploy every service with the **current** keys unchanged and the **new** keys as the **previous** ones. Nothing changes yet: nobody writes with the new keys, but every service will be able to read them.
2. **Switch.** Deploy every service with the **new** keys as current and the **old** ones as previous. During this deployment the services already switched read the old entries with their previous keys, and the services not switched yet read the new entries with theirs (step 1), so no entry is lost on either side.
3. **Retire.** When every service runs with the new keys, wait for the longest expiration of the cache entries of your application (with no expiration, until the entries have been written again), then remove the previous keys from every service. Then disable (and later destroy) the old Secret Manager version.

Without step 1 the switch still works, only in one direction: the services already switched read the old entries, while those not switched yet can not read the new entries until they are deployed.

**Since 8.5.25 the data keys are mandatory and the framework has no built-in encryption keys. This is a breaking change.** Up to 8.5.24, `encrypt()` and `decrypt()` called without explicit keys and without data keys used two keys written in the source code of the framework, which is public, with the warning `security-default-encrypt-key-used`. Those keys are gone:

- **`encrypt()` and `encryptV2()` without data keys return `false`** and log the error `security-encrypt-key-missing` once per request, with or without `core.security.encrypt_require_key`. `decrypt()` of a v2 value (`v2:` prefix) without data keys does the same.
- **`decrypt()` of a v1 value** (without the `v2:` prefix) called without explicit keys tries the data keys first, which read the v1 values that `encrypt()` wrote with them. When they are missing or can not read it, it tries the **legacy keys**, only when they are configured: the value is returned and the warning `security-legacy-key-read` is logged once per request. Without legacy keys it returns `false` and logs the error `security-legacy-key-missing` once per request.
- **The legacy keys are only for reading.** They are never used by `encrypt()`, never to read a v2 value and never when the keys are passed explicitly (as `CoreCache` does). They are resolved like the data keys, each value independently: config-vars `core.security.legacy_encrypt_key` / `core.security.legacy_encrypt_secret`, then the environment variables `CF_LEGACY_ENCRYPT_KEY` / `CF_LEGACY_ENCRYPT_SECRET` (`getenv()` and then the `env_vars` secret), with the same reload of the `env_vars` secret described below. A half pair is never completed with anything else.
- **Calls with explicit keys do not change**: `encrypt($text, $key, $secret)` and `decrypt($text, $key, $secret)` behave exactly as before.
- **`core.security.encrypt_require_key` is still accepted** and does no harm, but its rule on the keys is now the rule for every application. It only keeps its effect on the format: `encrypt()` writes v2, the same as `core.security.encrypt_format: "v2"`.

**Who is affected:** any application that calls `$this->core->security->encrypt()` / `decrypt()` (or `encryptV2()` / `decryptV2()`) without explicit keys and without `core.security.encrypt_key` / `encrypt_secret` or `CF_ENCRYPT_KEY` / `CF_ENCRYPT_SECRET`. The warning `security-default-encrypt-key-used` in the logs of 8.5.24 or before identifies them. Also any application that **stores values encrypted that way** (v1 with the built-in default keys), including those in strict mode that read them through the warning `security-default-key-legacy-read`.

**Before updating to 8.5.25:**

1. Configure your own data keys (generate them as shown below).
2. If you keep data encrypted with the old built-in default keys, configure the legacy keys with the values that were those defaults. Take them from the source code of the version you run now, the constants `DEFAULT_ENCRYPT_KEY` and `DEFAULT_ENCRYPT_SECRET` of `CoreSecurity` in `src/Core7.php` of 8.5.24 or before: this guide and the release notes do not reproduce them. Store them like any other secret (the `env_vars` secret or a value injected at deploy time), never in the repository.
3. Re-encrypt that data: read each value (it is read with the legacy keys) and encrypt it again, which writes it with your data keys. When the warning `security-legacy-key-read` no longer appears, remove the legacy keys.

Applications that require `cloudframework-io/backend-core-php8` with `^8` receive 8.5.25 in their next deployment that resolves dependencies without a `composer.lock` pinning an earlier version, so configure the keys before that deployment.

**Generate each value** with a cryptographically secure generator: 32 random bytes encoded in base64 (44 characters).

```bash
openssl rand -base64 32   # cache_encrypt_key  (or CF_CACHE_ENCRYPT_KEY)
openssl rand -base64 32   # cache_encrypt_iv   (or CF_CACHE_ENCRYPT_IV)
openssl rand -base64 32   # encrypt_key        (or CF_ENCRYPT_KEY)
openssl rand -base64 32   # encrypt_secret     (or CF_ENCRYPT_SECRET)
```

Recommendations:

- **Do not type, reuse or derive the values** from names, passwords or other keys. Short or hand-written values make the encryption guessable.
- **One pair per environment.** Development must not be able to read production data. Share a pair only between services that share the same data (same Datastore namespaces and cache).
- **Keep them out of the repository.** Prefer a value injected at deploy time or Secret Manager over a committed `config.json`. Anyone who can read the repository can read a committed key.
- **The cache keys can not live in the `env_vars` secret**: they encrypt the cached copy of that secret, so they are read with `getenv()` only. The data keys can live in the `env_vars` secret (`CF_ENCRYPT_KEY` / `CF_ENCRYPT_SECRET`), and so can the legacy keys (`CF_LEGACY_ENCRYPT_KEY` / `CF_LEGACY_ENCRYPT_SECRET`).
- **Adding the data keys to the `env_vars` secret takes effect without emptying the cache (since 8.5.14).** The cached copy of the secret is kept per cache namespace, so a namespace cached before the keys were added would keep a copy without them. When the data keys are missing (or, for `decrypt()` of a v1 value, the legacy keys), the framework reads the secret again once per request without the cache, rewrites the cached copy of the current namespace and logs the warning `security-encrypt-env-vars-reloaded`. If the keys are still missing after that read, `encrypt()` / `decrypt()` return `false` and the error `security-encrypt-key-missing` (or `security-legacy-key-missing`) is logged. Explicit keys and keys in the configuration never trigger that read. Up to 8.5.24 it only happened in strict mode; since 8.5.25 it does not depend on it.
- **Never print or log them.** When you check a configuration, compare names or hashes, not values.
- **Rotating the cache keys** only empties the encrypted cache: it is rebuilt on the next requests (since 8.5.27, without losing it, see [Rotating the cache keys without cuts](#rotating-the-cache-keys-without-cuts)). **Rotating the data keys** makes the values encrypted with the old keys unreadable, so decrypt and encrypt them again with the new keys before removing the old ones.

### Cloud KMS Data Keys (v3)

**Since 8.5.27 `$this->core->security->encrypt()` can encrypt with a Cloud KMS key**, so the key that protects the stored values is never in the application: Cloud KMS keeps it, rotates its versions and records every use. Values encrypted that way have the format **v3**:

```
v3:<alias>:<version>:<base64 of the ciphertext of Cloud KMS>
```

**Configuration** (names of keys, not secrets):

```json
{
  "core.gcp.kms.data_keys": {
    "main": "projects/{project}/locations/{location}/keyRings/{ring}/cryptoKeys/{key}",
    "old": "projects/{project}/locations/{location}/keyRings/{ring}/cryptoKeys/{previous-key}"
  },
  "core.gcp.kms.data_key_primary": "main"
}
```

or the environment variables `CF_KMS_DATA_KEYS` (the same object as JSON) and `CF_KMS_DATA_KEY_PRIMARY`, read with `getenv()` only. The configuration wins over the environment. An alias is `[a-z0-9]{1,8}`; a resource is the CryptoKey (never a version). Invalid entries are ignored.

- **When v3 is written.** `encrypt($text)` writes v3 only when the call passes **no key at all** and `data_key_primary` is set. It wins over `core.security.encrypt_format`. With explicit keys (as `CoreCache` does), or without `data_key_primary`, `encrypt()` behaves exactly as in 8.5.26 (v1 or v2). `encryptV2()` still writes v2.
- **Usage (additional authenticated data).** `encrypt()` and `decrypt()` accept a fourth parameter, `$usage` (`[a-z0-9-]{1,40}`): `encrypt($text, '', '', 'user-token')`. In v3 it is bound to the ciphertext as the AAD `cloudframework:<usage>` (`cloudframework:default` when it is empty), and the same usage is required to decrypt it, so a value copied from one field to another does not decrypt. v1 and v2 ignore it. An invalid usage returns `false` (`security-encrypt-usage-invalid`).
- **Reading.** `decrypt()` reads v1, v2 and v3. For v3 it takes the alias of the value and looks for it in `data_keys` (not only the primary), so values of a previous key keep decrypting while its alias stays configured. An alias that is not configured returns `false` without calling Cloud KMS (`security-kms-unknown-alias`). A v3 value given with explicit keys returns `false` (`security-kms-explicit-keys`).
- **It fails closed.** When Cloud KMS does not answer (after one retry) or refuses the call, `encrypt()` and `decrypt()` return `false` and log the error `security-kms-unavailable` once per request. `encrypt()` **never** falls back to v1 or v2. A v3 value rejected by Cloud KMS because of a wrong usage or a corrupted value logs `security-kms-decrypt-failed`. Plan the callers for a `false`.
- **Limits.** Cloud KMS encrypts up to 64 KiB: a longer text returns `false` (`security-kms-plaintext-too-large`). Each `encrypt()` / `decrypt()` of a v3 value is one call to Cloud KMS (3 seconds at most per attempt): do not use v3 for values read in a loop; keep them in memory once decrypted.
- **Quota.** The framework never reads the metadata of the key (its primary version, its state): the version written in the value comes from the answer of Cloud KMS. Cloud KMS has a small quota of reads of metadata per project, and `Encrypt` / `Decrypt` have their own, larger quota.
- **Permissions.** The service account of the service needs `roles/cloudkms.cryptoKeyEncrypterDecrypter` on each key (not on the project).

  ```bash
  gcloud kms keys add-iam-policy-binding {key} --keyring={ring} --location={location} --project={project} \
    --member=serviceAccount:{service-account} --role=roles/cloudkms.cryptoKeyEncrypterDecrypter
  ```

**Rotation.** There are two levels:

1. **Versions of the same key** (automatic rotation of Cloud KMS). Nothing to do in the application: new values are written with the new primary version (the number in the value changes) and the old values keep decrypting while their version is enabled. Never disable or destroy a version while values encrypted with it are stored.
2. **Another key.** Add it to `data_keys` with a new alias and make it the primary; keep the old alias in `data_keys` so its values decrypt. Re-encrypt the stored values and, when none is left with the old alias, remove it.

**Moving stored values to the primary key:**

```php
// v1, v2 or v3 of another alias -> v3 of the primary key. A v3 value of the primary alias is returned unchanged.
$version = $this->core->security->encryptionVersionOf($stored);   // 'v1', 'v2', 'v3:<alias>:<N>' or null
$value = $this->core->security->reEncrypt($stored, 'user-token'); // false when it can not decrypt or encrypt it
if($value !== false && $value !== $stored) { /* save $value */ }
```

`encryptionVersionOf()` never decrypts and never calls Cloud KMS. `reEncrypt()` returns `false` and invents nothing when there is no primary key (`security-kms-not-configured`), the value is not recognised, it can not be decrypted with that usage or Cloud KMS fails.

**`GoogleKMS` class.** `src/class/GoogleKMS.php` is the only code of the framework that calls Cloud KMS, and it can be used directly:

```php
$kms = $this->core->loadClass('GoogleKMS');            // or new GoogleKMS($core, ['credentials' => $serviceAccount])
$res = $kms->encrypt($plaintext, 'cloudframework:my-usage', $keyResource); // ['ciphertext' => bytes, 'version' => 'N'] or false
$plain = $kms->decrypt($res['ciphertext'], 'cloudframework:my-usage', $keyResource);
if($plain === false) { /* $kms->errorCode: kms-unavailable, kms-permission-denied, kms-invalid-argument, ... */ }
```

One client per object (REST transport), created when first needed; at most 3 seconds per attempt; one retry, only when Cloud KMS is unavailable or the call times out, never on a permission or AAD error; no plaintext, ciphertext or AAD in `errorMsg` or in the logs. `wrapKey()` / `unwrapKey()` wrap a data key without AAD. Since 8.5.27 `Buckets::encryptLargeFile()` / `decryptLargeFile()` wrap their data key with `GoogleKMS`: `initKMS()` takes the same options and the format of the encrypted files does not change, so files encrypted before 8.5.27 decrypt with 8.5.27 and the other way round.

### Outgoing TLS Verification

**Since 8.5.15 the framework verifies the TLS certificate and the host name of every outgoing HTTPS call**: `$this->core->request->get()`, `post()`, `put()`, `patch()`, `delete()`, `call()`, the `*_json_decode()` variants, `getCurl()` and the `Firebase` class. Before 8.5.15 they did not verify it. A call to a host with an expired, self-signed, wrong-name or untrusted certificate now fails: it returns `false` and `$this->core->request->errorMsg` contains `tls-verify-failed` with the host.

The certificates are checked against the CA store of the system. To use another bundle:

| Config-var | Effect |
|------------|--------|
| `core.request.ssl.cafile` / `core.request.ssl.capath` | CA bundle file / directory used to verify the certificates |
| `core.request.ssl_verify` | `false` (the boolean) turns the verification off for every call. `"false"`, `0` or `null` do not |

Turning the verification off is always possible, explicitly, at three levels:

```php
// 1. One call only (the next calls verify again)
$this->core->request->get($url, null, ['ssl' => ['verify_peer' => false, 'verify_peer_name' => false]]);

// 2. Every call of the current request
$this->core->request->addSSLParameter('verify_peer', false);
$this->core->request->addSSLParameter('verify_peer_name', false);

// 3. Every call of the environment, without code: in config.json
// "core.request.ssl_verify": false
```

Each host reached without full verification logs the warning `security-tls-verify-disabled` once per request, with the host and never the URL. Use it only for a host you control (a local server with its own certificate) and prefer `core.request.ssl.cafile` with that certificate.

`$extra_headers['ssl']` applies to its call only since 8.5.15. Before, it stayed in `$this->core->request->default_options` for the rest of the request. `getCurl()` honours the same SSL options (`cafile`, `capath`, `local_cert`, `local_pk`, `passphrase`, `ciphers`).

### Encrypt Sensitive Data

```php
// Configuration: a key and a secret (see Framework Encryption Keys)
$encryptionKey = $this->core->config->get('security.encryption.key');
$encryptionSecret = $this->core->config->get('security.encryption.secret');

// Encrypt data before storing
$sensitiveData = 'credit card number';
$encrypted = $this->core->security->encrypt($sensitiveData, $encryptionKey, $encryptionSecret);

// Store encrypted data
$ds = $this->core->loadClass('DataStore', ['Users']);
$ds->createEntity([
    'name' => 'John Doe',
    'payment_info' => $encrypted // Encrypted field
]);

// Decrypt when needed
$user = $ds->fetchOne($userId);
$decrypted = $this->core->security->decrypt($user['payment_info'], $encryptionKey, $encryptionSecret);
```

### Hash Passwords

Use `$this->core->security->crypt()` to hash a password (or any secret that only has to be verified, such as a recovery code) and `checkCrypt()` to verify it.

**Since 8.5.27** `crypt()` uses `password_hash()` with bcrypt and a cost of **at least 12** (`$2y$12$...`, 60 characters) and `checkCrypt()` uses `password_verify()`, which compares in constant time. Up to 8.5.26 `crypt()` wrote `$2a$07$` hashes (cost 7) and `checkCrypt()` compared with `==`.

- **Old hashes keep working.** `checkCrypt()` verifies the `$2a$07$` hashes written before, and `checkCrypt()` of 8.5.26 or before verifies the new ones, so services with different versions can share the same data.
- **Upgrade them on login.** After a successful `checkCrypt()`, `needsRehash($hash)` says whether the hash should be computed again with the current policy (true for the old `$2a$07$` hashes and for any other algorithm; a higher cost is not lowered). Store `crypt($password)` when it says true.
- **The second parameter is the cost.** `crypt($input, $rounds)` keeps it for compatibility, with a minimum of 12 (a lower value, including the old default 7, is raised to 12) and a maximum of 31. A cost of 12 takes about 0.2-0.3 seconds per hash: hash once per login, never per request.
- **Up to 72 bytes.** bcrypt only uses the first 72 bytes of the input, so two inputs that share them would get the same hash. Longer input is refused explicitly, as before 8.5.27: `crypt()` returns `null`, `checkCrypt()` returns `false` and the error `security-password-too-long` is logged once per request (never with the value). Limit the length of passwords at the input (72 bytes, not characters: a multi-byte character counts more). A NUL byte also returns `null` (`security-password-invalid`).
- **Arrays** are converted with `json_encode()` before hashing and verifying, as before. `checkCrypt($input)` without a hash verifies against the config-var `core.security.password`.

```php
public function ENDPOINT_register()
{
    if(!$this->checkMethod('POST')) return;
    if(!$this->checkMandatoryFormParams(['email', 'password'])) return;

    $password = $this->formParams['password'];

    // Validate password strength (and at most 72 bytes)
    if(strlen($password) < 8 || strlen($password) > 72) {
        return $this->setError('Password must have between 8 and 72 bytes', 400);
    }

    // Hash password
    $hashedPassword = $this->core->security->crypt($password);

    // Create user
    $ds = $this->core->loadClass('DataStore', ['Users']);
    $userId = $ds->createEntity([
        'email' => $this->formParams['email'],
        'password' => $hashedPassword,
        'created_at' => date('c')
    ]);

    $this->addReturnData(['user_id' => $userId]);
}

// Login: verify, then upgrade an old hash
if(!$this->core->security->checkCrypt($password, $user['password'])) return $this->setError('Invalid credentials', 401);
if($this->core->security->needsRehash($user['password'])) {
    $user['password'] = $this->core->security->crypt($password);
    // save $user
}
```

### Secure Session Handling

```php
// Configure secure sessions
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 1);  // HTTPS only
ini_set('session.cookie_samesite', 'Strict');
ini_set('session.use_strict_mode', 1);

// Initialize session
$this->core->session->init('MyApp');

// Store user data
$this->core->session->set('user_id', $userId);
$this->core->session->set('role', 'admin');

// Regenerate session ID after login
session_regenerate_id(true);
```

---

## Secure File Uploads

### Validate File Uploads

```php
public function ENDPOINT_upload()
{
    if(!$this->checkMethod('POST')) return;

    if(empty($_FILES['file'])) {
        return $this->setError('No file uploaded', 400);
    }

    $file = $_FILES['file'];

    // Validate file size (5MB max)
    $maxSize = 5 * 1024 * 1024;
    if($file['size'] > $maxSize) {
        return $this->setError('File too large (max 5MB)', 400);
    }

    // Validate file type
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'application/pdf'];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if(!in_array($mimeType, $allowedTypes)) {
        return $this->setError('Invalid file type', 400);
    }

    // Validate file extension
    $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'pdf'];
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    if(!in_array($extension, $allowedExtensions)) {
        return $this->setError('Invalid file extension', 400);
    }

    // Generate safe filename
    $safeFilename = bin2hex(random_bytes(16)) . '.' . $extension;

    // Upload to Cloud Storage
    $buckets = $this->core->loadClass('Buckets', ['gs://my-bucket']);
    $result = $buckets->uploadFile($file['tmp_name'], "uploads/{$safeFilename}");

    if($buckets->error()) {
        return $this->setError('Upload failed', 500);
    }

    $this->addReturnData([
        'filename' => $safeFilename,
        'url' => "https://storage.googleapis.com/my-bucket/uploads/{$safeFilename}"
    ]);
}
```

---

## Security Headers

### Implement Security Headers

```php
function main()
{
    // Prevent clickjacking
    header('X-Frame-Options: SAMEORIGIN');

    // Prevent MIME sniffing
    header('X-Content-Type-Options: nosniff');

    // Enable XSS protection
    header('X-XSS-Protection: 1; mode=block');

    // HTTPS only
    header('Strict-Transport-Security: max-age=31536000; includeSubDomains');

    // Content Security Policy
    header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'");

    // Referrer Policy
    header('Referrer-Policy: strict-origin-when-cross-origin');

    // Permissions Policy
    header('Permissions-Policy: geolocation=(), microphone=(), camera=()');

    // Continue with API logic
}
```

---

## Logging & Auditing

### Security Event Logging

```php
// Log authentication attempts
private function logAuthAttempt(string $email, bool $success)
{
    $this->core->logs->add([
        'event' => 'auth_attempt',
        'email' => $email,
        'success' => $success,
        'ip' => $_SERVER['REMOTE_ADDR'],
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
        'timestamp' => time()
    ], 'security');
}

// Log data access
private function logDataAccess(string $resource, string $action)
{
    $this->core->logs->add([
        'event' => 'data_access',
        'resource' => $resource,
        'action' => $action,
        'user_id' => $this->currentUser['user_id'] ?? 'anonymous',
        'ip' => $_SERVER['REMOTE_ADDR'],
        'timestamp' => time()
    ], 'audit');
}

// Usage
public function ENDPOINT_login()
{
    // ... authentication logic ...

    if($success) {
        $this->logAuthAttempt($email, true);
    } else {
        $this->logAuthAttempt($email, false);
    }
}

public function ENDPOINT_users()
{
    $this->logDataAccess('users', 'read');
    // ... fetch users ...
}
```

### Sensitive Query Parameters in Logs

**Since 8.5.16 the framework masks the value of the sensitive query parameters before writing a URL to its logs**, so an OAuth callback (`code`, `state`) or a token passed in the URL is not stored. It applies to:

- `$this->core->cfiLog->add()` (`CloudFrameWorkLogs`): `JSON.url`, `JSON.http_referer` and the stored `fingerprint`, including `local_fingerprint` and the fingerprint passed by the caller in `$rewrite_fingerprint`.
- `$this->core->cfiLog->bitacora()` (`CloudFrameWorkBitacora`): `JSON.url` and `JSON.http_referer`.
- `$this->core->system->getRequestFingerPrint()`: `uri` and `http_referer`, also for a fingerprint received from a queue (`cloudframework_queued_fingerprint`). The fingerprint `hash` does not use them and does not change. Since 8.5.17 a queued value that is not a JSON object is ignored and the fingerprint of the current request is used instead, both here and in what `sendLog()` forwards: before, it was a `TypeError` that any client could trigger, and `sendLog()` forwarded it as it arrived.
- `CoreRequest` (`$this->core->request`), since 8.5.19: the URL it calls, with the parameters of `$data` already added to a GET, in its log lines (`sendSysLogs`), its performance notes and its errors (`errorMsg` and `$this->core->errors`), including the `error_get_last()` message and the TLS verification error. A call such as `get_json_decode($userinfo_url, ['access_token' => $token])` that fails no longer writes the token. The request itself and `$this->core->request->options`, which keeps the route for debugging, are not modified.

The masked parameters by default are `code`, `state`, `token`, `access_token`, `id_token`, `password`, `key` and `secret`. Names are compared like PHP builds `$_GET` (case-insensitive, url-decoded, `.` and space as `_`, without the `[]` suffix), so `CODE`, `access%5Ftoken` or `code[]` are masked and `postcode` or `keyword` are not. The path, the other parameters and their order are kept as they arrived. The `$data` you pass to `add()` or `bitacora()` is not modified: do not put secrets there.

The config-var `core.log.sensitive_params` adds names (an array or a comma-separated string). It only adds: the default names can not be removed.

**Since 8.5.20 the error line of `RESTful::send()` carries only `status`, `code` and `lines`.** The `CloudFramework RESTFul:` line sent to syslog used to hold all of `$this->core->errors->data`, which may include what the caller sent or what a service answered. The client still receives that data in the response. In the same way, the line of a call that comes from a queue or a cron (`RESULT FROM QUEUE`, `USING CRON`) carries only `status` and `code`: it used to hold the whole response.

```json
{
  "core.log.sensitive_params": ["otp", "session_id"]
}
```

```php
// GET /sso?code=abc123&state=xyz&scope=email
$this->core->cfiLog->add('sso', 'callback', 'Google SSO', 'GET', $user);
// JSON.url stored: /sso?code=***&state=***&scope=email

// The same masking for your own logs
$this->core->logs->add($this->core->system->maskUrl($url), 'audit');
```

When `file_get_contents()` can not open a URL, PHP itself writes a warning with the full URL to its error log (stderr in App Engine); the framework does not intercept it. Send tokens in a header (`Authorization: Bearer …`) rather than in the URL.

The request log of App Engine (and of any load balancer or proxy in front of it) records the full URL and is **not** controlled by the framework. Treat it in Cloud Logging, for example with an exclusion filter on the callback routes or a restricted log bucket.

### Debug Output in API Responses

**Since 8.5.26 an API response carries debug output only in local development or for the IPs you configure.** The debug output is the log lines (`logs`), the trace of the profiler (`?__p`) and the echo of the request (`?__debug`: URL, method, IP, headers, session, user agent and parameters). Up to 8.5.25 the logs went to any caller unless `restful.logs.allowed_ips` was set, and even then the check could be skipped: the IP came from `X-Forwarded-For` (which the caller writes) and was compared with a partial match, calls from a cron and URLs with `/queue/` after the first character were exceptions, and `?__p` and `?__debug` had no check at all. That is a disclosure of internal details to anyone (ASVS 4.0.3, 4.1.4).

The rule lives in `$this->core->security->debugOutputAllowed()`, used by `RESTful::send()` and by `dispatcher.php`:

| Where the code runs | Debug output |
|---|---|
| Local server or local script (no `GAE_SERVICE`, `K_SERVICE` nor `FUNCTION_TARGET`) | Yes |
| App Engine | Only when the IP of the client is **exactly** one of the configured IPs |
| Cloud Run, Cloud Functions | No: they have no IP header that the caller can not write |

```json
{
  "core.api.debug.allowed_ips": ["203.0.113.10", "2001:db8::10"]
}
```

- The IP is the one the platform gives: `X-Appengine-User-Ip` in App Engine (App Engine sets it and removes the one sent by the client), `REMOTE_ADDR` in local. `X-Forwarded-For` is never used for this. `$this->core->security->trustedClientIP()` returns it.
- The value is an array or a comma-separated string. Only valid IPv4 and IPv6 addresses count: `*`, `localhost`, ranges and anything else are ignored. IPv6 addresses are compared in binary form, so `2001:db8::10` and `2001:0db8:0:0:0:0:0:10` are the same.
- `restful.logs.allowed_ips` and `core_api_logs_allowed_ips` are still read, with the same rules, so a `*` there no longer opens the logs to everybody. The default of the framework, `"restful.logs.allowed_ips": "localhost,127.0.0.1"`, opens nothing in a managed runtime: `localhost` is not an IP and no client of App Engine arrives as `127.0.0.1`. With that default, nobody receives debug output in App Engine until you configure real IPs.
- Cron calls and `/queue/` URLs follow the same rule. Cloud Tasks and App Engine cron do not read the body of the response.
- The dump of the profiler at a given line (`?__p=N`) works only outside a managed runtime: it runs before the configuration is read.
- The errors (`errors` and `queued_return_error`) are part of the answer and are not affected. Do not put internal details in them (see [Information Disclosure](#3-information-disclosure)).

`$this->core->system->ip` (from `getClientIP()`) is unchanged and still comes from `X-Forwarded-For`. Do not use it for security decisions: use `trustedClientIP()`.

---

## GCP Security

### Service Account Best Practices

**1. Principle of Least Privilege:**

```bash
# Grant only necessary roles
gcloud projects add-iam-policy-binding my-project \
  --member="serviceAccount:my-api@my-project.iam.gserviceaccount.com" \
  --role="roles/datastore.user"

# Don't grant overly permissive roles like:
# roles/owner, roles/editor
```

**2. Use separate service accounts:**

```bash
# API service account
gcloud iam service-accounts create api-service \
  --display-name="API Service Account"

# Scripts service account
gcloud iam service-accounts create scripts-service \
  --display-name="Scripts Service Account"
```

**3. Rotate service account keys:**

```bash
# Create new key
gcloud iam service-accounts keys create new-key.json \
  --iam-account=my-api@my-project.iam.gserviceaccount.com

# Delete old key
gcloud iam service-accounts keys delete OLD_KEY_ID \
  --iam-account=my-api@my-project.iam.gserviceaccount.com
```

### Secure Data in Datastore

```php
// Use namespaces to separate environments
$ds = $this->core->loadClass('DataStore', ['Users']);

// Don't expose internal IDs
public function ENDPOINT_users()
{
    $users = $ds->fetchAll();

    // Remove sensitive fields
    foreach($users as &$user) {
        unset($user['password']);
        unset($user['payment_info']);
        unset($user['internal_notes']);
    }

    $this->addReturnData($users);
}
```

### Enable Cloud Audit Logs

Enable in GCP Console:
- IAM & Admin → Audit Logs
- Enable Admin Read, Data Read, Data Write

---

## Common Vulnerabilities

### 1. Insecure Direct Object References (IDOR)

**Vulnerable:**
```php
public function ENDPOINT_user()
{
    $userId = $this->params[1];

    // Anyone can access any user
    $ds = $this->core->loadClass('DataStore', ['Users']);
    $user = $ds->fetchOne($userId);

    $this->addReturnData($user);
}
```

**Secure:**
```php
public function ENDPOINT_user()
{
    $userId = $this->params[1];
    $currentUserId = $this->currentUser['user_id'];

    // Users can only access their own data (unless admin)
    if($userId !== $currentUserId && $this->currentUser['role'] !== 'admin') {
        return $this->setError('Access denied', 403);
    }

    $ds = $this->core->loadClass('DataStore', ['Users']);
    $user = $ds->fetchOne($userId);

    $this->addReturnData($user);
}
```

### 2. Mass Assignment

**Vulnerable:**
```php
public function ENDPOINT_update()
{
    // User can modify any field, including 'role'
    $ds = $this->core->loadClass('DataStore', ['Users']);
    $ds->updateEntity($userId, $this->formParams);
}
```

**Secure:**
```php
public function ENDPOINT_update()
{
    // Whitelist allowed fields
    $allowedFields = ['name', 'email', 'bio'];
    $updateData = [];

    foreach($allowedFields as $field) {
        if(isset($this->formParams[$field])) {
            $updateData[$field] = $this->formParams[$field];
        }
    }

    $ds = $this->core->loadClass('DataStore', ['Users']);
    $ds->updateEntity($userId, $updateData);
}
```

### 3. Information Disclosure

**Vulnerable:**
```php
catch(Exception $e) {
    return $this->setError($e->getMessage(), 500); // Exposes internal details
}
```

**Secure:**
```php
catch(Exception $e) {
    // Log detailed error
    $this->core->errors->add($e->getMessage(), 'api', 'error');

    // Return generic error to user
    return $this->setError('An error occurred', 500);
}
```

---

## See Also

- [API Development Guide](api-development.md)
- [Configuration Guide](configuration.md)
- [Deployment Guide](deployment.md)
- [GCP Integration Guide](gcp-integration.md)
