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

### Encrypt Sensitive Data

```php
// Configuration
$encryptionKey = $this->core->config->get('security.encryption.key');

// Encrypt data before storing
$sensitiveData = 'credit card number';
$encrypted = $this->core->security->encrypt($sensitiveData, $encryptionKey);

// Store encrypted data
$ds = $this->core->loadClass('DataStore', ['Users']);
$ds->createEntity([
    'name' => 'John Doe',
    'payment_info' => $encrypted // Encrypted field
]);

// Decrypt when needed
$user = $ds->fetchOne($userId);
$decrypted = $this->core->security->decrypt($user['payment_info'], $encryptionKey);
```

### Hash Passwords

```php
public function ENDPOINT_register()
{
    if(!$this->checkMethod('POST')) return;
    if(!$this->checkMandatoryFormParams(['email', 'password'])) return;

    $password = $this->formParams['password'];

    // Validate password strength
    if(strlen($password) < 8) {
        return $this->setError('Password must be at least 8 characters', 400);
    }

    // Hash password
    $hashedPassword = password_hash($password, PASSWORD_ARGON2ID);

    // Create user
    $ds = $this->core->loadClass('DataStore', ['Users']);
    $userId = $ds->createEntity([
        'email' => $this->formParams['email'],
        'password' => $hashedPassword,
        'created_at' => date('c')
    ]);

    $this->addReturnData(['user_id' => $userId]);
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
