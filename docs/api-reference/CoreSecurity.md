# CoreSecurity Class

## Overview

The `CoreSecurity` class provides comprehensive security features for CloudFramework including authentication, encryption, token management, Google Cloud identity verification, and API key validation.

## Inheritance

Access CoreSecurity through the Core7 instance:

```php
// Basic auth
$this->core->security->checkBasicAuth($user, $password);

// Encryption
$encrypted = $this->core->security->encrypt($data);
$decrypted = $this->core->security->decrypt($encrypted);

// Web key validation
if($this->core->security->checkWebKey()) {
    // Valid web key
}
```

## Properties

### Public Properties

| Property | Type | Description |
|----------|------|-------------|
| `$error` | bool | Error flag |
| `$errorMsg` | array | Error messages |
| `$cache_key` | string | Encryption key for cache (from config) |
| `$cache_iv` | string | Encryption IV for cache (from config) |
| `$platform` | string\|null | Platform ID for ERP integration |
| `$secret_vars` | array\|null | Cached secret variables |

---

## Basic Authentication

### existBasicAuth()

```php
function existBasicAuth(): bool
```

Checks if Basic Authentication headers are present.

**Returns:** `true` if Basic Auth headers exist

**Example:**
```php
if($this->core->security->existBasicAuth()) {
    // Basic Auth headers present
}
```

---

### getBasicAuth()

```php
function getBasicAuth(): array
```

Retrieves Basic Authentication credentials.

**Returns:** Array `[$username, $password]`

**Example:**
```php
list($username, $password) = $this->core->security->getBasicAuth();
if($username && $password) {
    // Process credentials
}
```

---

### checkBasicAuth()

```php
function checkBasicAuth($user, $passw): bool
```

Validates Basic Authentication credentials.

**Parameters:**
- `$user` (string): Expected username
- `$passw` (string): Expected password

**Returns:** `true` if credentials match

**Example:**
```php
if($this->core->security->checkBasicAuth('admin', 'secret123')) {
    // Valid credentials
} else {
    // Invalid credentials
}
```

---

### checkBasicAuthWithConfig()

```php
function checkBasicAuthWithConfig(): array|false
```

Validates Basic Auth against configuration with IP restrictions.

**Returns:** User configuration array if valid, `false` otherwise

**Configuration:**
```json
{
  "core.system.authorizations": {
    "api_user": {
      "password": "encrypted_password_hash",
      "ips": "192.168.1.100,10.0.0.*",
      "roles": ["admin", "api"]
    }
  }
}
```

**Example:**
```php
$auth = $this->core->security->checkBasicAuthWithConfig();
if($auth) {
    $username = $auth['_BasicAuthUser_'];
    $roles = $auth['roles'] ?? [];
    // User authenticated
} else {
    return $this->setErrorFromCodelib('auth-error', 'Unauthorized');
}
```

**Features:**
- Validates username/password against config
- Checks IP restrictions
- Uses encrypted passwords
- Returns user configuration on success

---

## API Keys

### existWebKey()

```php
function existWebKey(): bool
```

Checks if a web key is present in GET, POST, or headers.

**Returns:** `true` if web key exists

**Example:**
```php
if($this->core->security->existWebKey()) {
    // Web key present
}
```

---

### getWebKey()

```php
function getWebKey(): string
```

Retrieves web key from `$_GET['web_key']`, `$_POST['web_key']`, or `X-WEB-KEY` header.

**Returns:** Web key string, or empty string if not found

**Example:**
```php
$webKey = $this->core->security->getWebKey();
if($webKey) {
    // Process web key
}
```

---

### checkWebKey()

```php
function checkWebKey($keys = null): array|false
```

Validates web key against allowed keys with origin restrictions.

**Parameters:**
- `$keys` (array\|null): Array of allowed keys, or `null` to use `CLOUDFRAMEWORK-WEB-KEYS` config

**Returns:** Key configuration array if valid, `false` otherwise

**Key Format:**
```php
$keys = [
    ['key123', '*'],  // Allow from any origin
    ['key456', 'example.com,api.example.com'],  // Specific origins only
];
```

**Configuration:**
```json
{
  "CLOUDFRAMEWORK-WEB-KEYS": [
    ["pub_key_1234567890", "*"],
    ["pub_key_0987654321", "myapp.com"]
  ]
}
```

**Example:**
```php
if($this->core->security->checkWebKey()) {
    // Valid web key
} else {
    return $this->setErrorFromCodelib('auth-error', 'Invalid web key');
}

// With custom keys
$allowedKeys = [['my_secret_key', '*']];
if($this->core->security->checkWebKey($allowedKeys)) {
    // Valid
}
```

---

### existServerKey()

```php
function existServerKey(): bool
```

Checks if X-SERVER-KEY header exists.

**Returns:** `true` if server key header present

---

### getServerKey()

```php
function getServerKey(): string
```

Retrieves server key from X-SERVER-KEY header.

**Returns:** Server key string

---

### checkServerKey()

```php
function checkServerKey($keys = null): array|false
```

Validates server key with IP restrictions.

**Parameters:**
- `$keys` (array\|null): Array of allowed keys with IP restrictions

**Returns:** Key configuration if valid, `false` otherwise

**Configuration:**
```json
{
  "CLOUDFRAMEWORK-SERVER-KEYS": [
    ["server_key_123", "192.168.1.0/24"],
    ["server_key_456", "*"]
  ]
}
```

**Example:**
```php
if($this->core->security->checkServerKey()) {
    // Valid server key from allowed IP
} else {
    return $this->setErrorFromCodelib('auth-error', 'Invalid server key or IP');
}
```

---

## Encryption & Hashing

### encrypt()

```php
function encrypt($text, $secret_key = '', $secret_iv = ''): string
```

Encrypts text using AES-256-CBC.

**Parameters:**
- `$text` (string): Text to encrypt
- `$secret_key` (string): Encryption key (optional, uses `core.security.encrypt_key` from config)
- `$secret_iv` (string): Encryption IV (optional, uses `core.security.encrypt_secret` from config)

**Returns:** Encrypted base64 string

**Example:**
```php
// Using default keys from config
$encrypted = $this->core->security->encrypt('sensitive data');

// Using custom keys
$key = 'my-secret-key-32-chars-long!!!';
$iv = 'my-secret-iv-24-chars!!';
$encrypted = $this->core->security->encrypt('sensitive data', $key, $iv);

// Store encrypted data
$this->core->cache->set('sensitive_key', $encrypted);
```

**Configuration:**
```json
{
  "core.security.encrypt_key": "your-secret-key-minimum-32-chars",
  "core.security.encrypt_secret": "your-secret-iv-minimum-24-chars"
}
```

---

### decrypt()

```php
function decrypt($encrypted_text, $secret_key = '', $secret_iv = ''): string
```

Decrypts AES-256-CBC encrypted text.

**Parameters:**
- `$encrypted_text` (string): Encrypted base64 string
- `$secret_key` (string): Decryption key (same as encryption)
- `$secret_iv` (string): Decryption IV (same as encryption)

**Returns:** Decrypted plaintext

**Example:**
```php
// Using default keys from config
$decrypted = $this->core->security->decrypt($encrypted);

// Using custom keys
$decrypted = $this->core->security->decrypt($encrypted, $key, $iv);

// Decrypt from cache
$encrypted = $this->core->cache->get('sensitive_key');
$data = $this->core->security->decrypt($encrypted);
```

**Important:** Must use the same key and IV used for encryption.

---

### crypt()

```php
function crypt($input, $rounds = 7): string
```

Creates a one-way password hash using bcrypt.

**Parameters:**
- `$input` (string): Password to hash
- `$rounds` (int): Cost parameter (4-31, default: 7)

**Returns:** Hashed password

**Example:**
```php
// Hash password for storage
$password = 'user_password123';
$hashedPassword = $this->core->security->crypt($password);

// Store in database
$userData['password'] = $hashedPassword;

// With higher security (more rounds = slower but more secure)
$hashedPassword = $this->core->security->crypt($password, 10);
```

**Security Notes:**
- Higher rounds = more secure but slower
- Recommended: 7-10 for normal use, 10-12 for high security
- Never store plaintext passwords

---

### checkCrypt()

```php
function checkCrypt($input, $input_encrypted = null): bool
```

Verifies a password against a bcrypt hash.

**Parameters:**
- `$input` (string): Password to check
- `$input_encrypted` (string): Hashed password to compare against

**Returns:** `true` if password matches

**Example:**
```php
// Validate user login
$userInputPassword = $_POST['password'];
$storedHash = $userData['password'];

if($this->core->security->checkCrypt($userInputPassword, $storedHash)) {
    // Password correct - login successful
} else {
    // Password incorrect
}
```

---

## Google Cloud Authentication

### getGoogleEmailAccount()

```php
function getGoogleEmailAccount(): string
```

Gets the Google service account email of the current environment.

**Returns:** Service account email

**Behavior:**
- **Development**: Uses `gcloud auth list` to get active user
- **Production**: Uses GCP metadata service to get instance service account

**Example:**
```php
$serviceAccount = $this->core->security->getGoogleEmailAccount();
// Development: user@example.com
// Production: my-project@appspot.gserviceaccount.com
```

---

### getGoogleAccessToken()

```php
function getGoogleAccessToken(string $user = ''): array
```

Retrieves a Google Cloud access token.

**Parameters:**
- `$user` (string): User account (for development)

**Returns:** Token array with `access_token`, `token_type`, `expires_in`

**Example:**
```php
$token = $this->core->security->getGoogleAccessToken();
if($token) {
    $accessToken = $token['access_token'];
    // Use to call Google APIs
}
```

---

### getGoogleIdentityToken()

```php
function getGoogleIdentityToken($user = '', $audience = 'https://api.cloudframework.io'): string
```

Retrieves a Google Cloud identity token (JWT).

**Parameters:**
- `$user` (string): User account (for development)
- `$audience` (string): Token audience

**Returns:** JWT token string

**Example:**
```php
$token = $this->core->security->getGoogleIdentityToken();
// Use for service-to-service authentication
```

---

### getGoogleTokenInfo()

```php
function getGoogleTokenInfo($token): array
```

Validates and retrieves information from a Google token.

**Parameters:**
- `$token` (string): Access token or identity token

**Returns:** Token info array

**Example:**
```php
$tokenInfo = $this->core->security->getGoogleTokenInfo($token);
if(isset($tokenInfo['email'])) {
    $userEmail = $tokenInfo['email'];
    $emailVerified = $tokenInfo['email_verified'] ?? false;
}
```

---

### getGoogleAccessTokenInfo()

```php
function getGoogleAccessTokenInfo($token): array
```

Retrieves information about a Google access token.

**Parameters:**
- `$token` (string): Access token (starts with `ya29.`)

**Returns:** Token information array

**Response Example:**
```php
[
    'email' => 'user@example.com',
    'verified_email' => true,
    'expires_in' => 3599,
    'scope' => 'https://www.googleapis.com/auth/userinfo.email...'
]
```

---

### getGoogleIdentityTokenInfo()

```php
function getGoogleIdentityTokenInfo($token): array|false
```

Validates and decodes a Google identity token (JWT).

**Parameters:**
- `$token` (string): Identity token (JWT format)

**Returns:** Token payload array if valid, `false` otherwise

**Response Example:**
```php
[
    'iss' => 'https://accounts.google.com',
    'email' => 'user@example.com',
    'email_verified' => true,
    'sub' => '1234567890',
    'exp' => 1638615498,
    'iat' => 1638611898
]
```

---

## Platform Secrets (CloudFramework ERP)

### getPlatformSecretVar()

```php
public function getPlatformSecretVar($var, string $erp_secret_id = '', string $erp_platform_id = '', string $erp_user = ''): mixed
```

Retrieves a specific secret variable from CloudFramework Platform.

**Parameters:**
- `$var` (string): Secret variable name
- `$erp_secret_id` (string): Secret ID (optional, uses config)
- `$erp_platform_id` (string): Platform ID (optional, uses config)
- `$erp_user` (string): User email (optional, auto-detected)

**Returns:** Secret value, or `null` if not found

**Configuration:**
```json
{
  "core.erp.platform_id": "my-platform-id",
  "core.erp.secrets.secret_id": "my-secrets"
}
```

**Example:**
```php
// Get API key from platform secrets
$apiKey = $this->core->security->getPlatformSecretVar('STRIPE_API_KEY');

// Get database password
$dbPassword = $this->core->security->getPlatformSecretVar('DB_PASSWORD');

// Use in configuration
if($apiKey) {
    // Initialize API with secret key
}
```

---

### readPlatformSecretVars()

```php
public function readPlatformSecretVars($erp_secret_id = '', $erp_platform_id = '', $erp_user = ''): bool
```

Loads all secret variables from CloudFramework Platform.

**Parameters:**
- `$erp_secret_id` (string): Secret ID
- `$erp_platform_id` (string): Platform ID
- `$erp_user` (string): User email

**Returns:** `true` on success, `false` on error

**Example:**
```php
// Load all secrets
if($this->core->security->readPlatformSecretVars()) {
    $apiKey = $this->core->security->getPlatformSecretVar('API_KEY');
    $dbPass = $this->core->security->getPlatformSecretVar('DB_PASSWORD');
}
```

**Features:**
- Caches secrets for performance
- Auto-detects service account in production
- Validates tokens for security
- Encrypts cached secrets

---

### setSecretVar()

```php
public function setSecretVar($secret_key, $secret_value): void
```

Sets a secret variable manually.

**Parameters:**
- `$secret_key` (string): Secret key
- `$secret_value` (mixed): Secret value

**Example:**
```php
$this->core->security->setSecretVar('API_KEY', 'sk_test_1234567890');
```

---

### getSecretVar()

```php
public function getSecretVar($secret_key): mixed
```

Gets a manually set secret variable.

**Parameters:**
- `$secret_key` (string): Secret key

**Returns:** Secret value, or `null` if not found

**Example:**
```php
$apiKey = $this->core->security->getSecretVar('API_KEY');
```

---

## Datastore Tokens

### getDSToken()

```php
function getDSToken($token, $prefixStarts = '', $time = 0, $fingerprint_hash = '', $use_fingerprint_security = true): array|null
```

Retrieves and validates a token from Google Cloud Datastore.

**Parameters:**
- `$token` (string): Token ID
- `$prefixStarts` (string): Expected token prefix
- `$time` (int): Max age in seconds (0 = no expiration)
- `$fingerprint_hash` (string): Request fingerprint for validation
- `$use_fingerprint_security` (bool): Enable fingerprint validation

**Returns:** Token data array, or `null` on error

**Example:**
```php
// Retrieve token
$token = $_GET['reset_token'] ?? '';
$data = $this->core->security->getDSToken($token, 'reset_', 3600);

if($data) {
    $userId = $data['user_id'];
    // Process password reset
} else {
    // Token invalid or expired
}
```

---

### getDSTokenInfo()

```php
function getDSTokenInfo($token): array|null
```

Retrieves token information without validation.

**Parameters:**
- `$token` (string): Token ID

**Returns:** Token entity array

---

### deleteDSToken()

```php
function deleteDSToken($token): array|bool
```

Deletes a token from Datastore.

**Parameters:**
- `$token` (string): Token ID

**Returns:** Deleted entity array on success

**Example:**
```php
// Delete used token
$this->core->security->deleteDSToken($resetToken);
```

---

### updateDSToken()

```php
function updateDSToken($token, $data): array
```

Updates token data.

**Parameters:**
- `$token` (string): Token ID
- `$data` (mixed): New data

**Returns:** Updated data array

---

## JWT Tokens

### jwt_encode()

```php
public function jwt_encode($payload, $key, $keyId = null, $head = null, $algorithm = 'RS256'): string|false
```

Encodes a JWT token.

**Parameters:**
- `$payload` (mixed): Data to encode
- `$key` (string): Private key (minimum 10 characters)
- `$keyId` (string\|null): Key ID for header
- `$head` (array\|null): Additional headers
- `$algorithm` (string): Algorithm ('RS256', 'HS256', etc.)

**Returns:** JWT string, or `false` on error

**Supported Algorithms:**
- RS256, RS384, RS512 (RSA with SHA)
- HS256, HS384, HS512 (HMAC with SHA)

**Example:**
```php
$payload = [
    'user_id' => 123,
    'email' => 'user@example.com',
    'exp' => time() + 3600  // Expires in 1 hour
];

$privateKey = file_get_contents('/path/to/private.pem');
$jwt = $this->core->security->jwt_encode($payload, $privateKey);

// Send to client
header('Authorization: Bearer ' . $jwt);
```

---

## Utility Methods

### getHeader()

```php
function getHeader($str): string
```

Gets an HTTP header value.

**Parameters:**
- `$str` (string): Header name (case-insensitive, with or without 'HTTP_' prefix)

**Returns:** Header value

**Example:**
```php
$contentType = $this->core->security->getHeader('Content-Type');
$authHeader = $this->core->security->getHeader('Authorization');
$customHeader = $this->core->security->getHeader('X-Custom-Header');
```

---

### isCron()

```php
function isCron(): bool
```

Checks if the request is from App Engine Cron.

**Returns:** `true` if request is from cron

**Example:**
```php
if($this->core->security->isCron()) {
    // Allow cron-only operations
} else {
    return $this->setErrorFromCodelib('auth-error', 'Cron only');
}
```

---

### generateRandomString()

```php
public function generateRandomString($pref = ''): string
```

Generates a random unique string.

**Parameters:**
- `$pref` (string): Optional prefix for uniqueness

**Returns:** Random base64 string (32 characters)

**Example:**
```php
$token = $this->core->security->generateRandomString();
// Store as password reset token

$sessionId = $this->core->security->generateRandomString('session_');
```

---

### prompt()

```php
function prompt($title, $default = null): string
```

Prompts user for terminal input (scripts only).

**Parameters:**
- `$title` (string): Prompt message
- `$default` (mixed): Default value

**Returns:** User input or default

**Example:**
```php
// In a script
$email = $this->core->security->prompt('Enter email address: ', 'user@example.com');
$confirm = $this->core->security->prompt('Are you sure? (yes/no): ', 'no');
```

---

## Common Usage Patterns

### API Key Authentication

```php
class API extends RESTful
{
    function main()
    {
        // Validate web key
        if(!$this->core->security->checkWebKey()) {
            return $this->setErrorFromCodelib('auth-error', 'Invalid API key');
        }

        // Continue with API logic
        if(!$this->useFunction('ENDPOINT_' . ($this->params[0] ?? 'default'))) {
            return $this->setErrorFromCodelib('params-error', 'Endpoint not found');
        }
    }
}
```

### Basic Auth Protection

```php
class API extends RESTful
{
    function main()
    {
        // Require Basic Auth
        $auth = $this->core->security->checkBasicAuthWithConfig();
        if(!$auth) {
            header('WWW-Authenticate: Basic realm="API Access"');
            return $this->setErrorFromCodelib('auth-error', 'Authentication required');
        }

        // Check user roles
        $roles = $auth['roles'] ?? [];
        if(!in_array('admin', $roles)) {
            return $this->setErrorFromCodelib('auth-error', 'Admin access required');
        }

        // Continue
    }
}
```

### Password Storage and Verification

```php
class API extends RESTful
{
    public function ENDPOINT_register()
    {
        $password = $this->formParams['password'] ?? '';

        // Hash password before storing
        $hashedPassword = $this->core->security->crypt($password, 10);

        // Store in database
        $ds = $this->core->loadClass('DataStore', ['Users']);
        $ds->createEntity([
            'email' => $email,
            'password' => $hashedPassword
        ]);
    }

    public function ENDPOINT_login()
    {
        $email = $this->formParams['email'] ?? '';
        $password = $this->formParams['password'] ?? '';

        // Get user from database
        $ds = $this->core->loadClass('DataStore', ['Users']);
        $users = $ds->fetchAll([['email', '=', $email]], null, 1);

        if(empty($users)) {
            return $this->setErrorFromCodelib('auth-error', 'Invalid credentials');
        }

        $user = $users[0];

        // Verify password
        if($this->core->security->checkCrypt($password, $user['password'])) {
            // Login successful
            $this->core->session->set('user_id', $user['KeyId']);
            $this->addReturnData(['message' => 'Login successful']);
        } else {
            return $this->setErrorFromCodelib('auth-error', 'Invalid credentials');
        }
    }
}
```

### Encrypting Sensitive Data

```php
// Store encrypted data in database
$creditCard = '4111-1111-1111-1111';
$encrypted = $this->core->security->encrypt($creditCard);

$ds->createEntity([
    'user_id' => $userId,
    'credit_card' => $encrypted
]);

// Retrieve and decrypt
$userData = $ds->fetchOne($userId);
$creditCard = $this->core->security->decrypt($userData['credit_card']);
```

### Using Platform Secrets

```php
class API extends RESTful
{
    function main()
    {
        // Load secrets from platform
        $this->core->security->readPlatformSecretVars();

        // Get API keys from secrets
        $stripeKey = $this->core->security->getPlatformSecretVar('STRIPE_API_KEY');
        $dbPassword = $this->core->security->getPlatformSecretVar('DB_PASSWORD');

        // Use secrets
        if($stripeKey) {
            // Initialize Stripe
        }
    }
}
```

### Password Reset Tokens

```php
// Generate reset token
$token = $this->core->security->generateRandomString('reset_');
$this->core->security->setDSToken($token, 'reset_', [
    'user_id' => $userId,
    'email' => $userEmail
], 3600); // 1 hour expiration

// Send email with reset link
$resetLink = 'https://myapp.com/reset?token=' . $token;

// Later, validate token
$data = $this->core->security->getDSToken($token, 'reset_', 3600);
if($data) {
    $userId = $data['user_id'];
    // Allow password reset
    $this->core->security->deleteDSToken($token);
}
```

---

## Security Best Practices

### 1. Always Use HTTPS

```php
if(!$this->core->is->https() && !$this->core->is->development()) {
    return $this->setErrorFromCodelib('security-error', 'HTTPS required');
}
```

### 2. Validate API Keys from Config

```php
// In config.json
{
  "CLOUDFRAMEWORK-WEB-KEYS": [
    ["pub_key_production", "myapp.com"],
    ["pub_key_staging", "staging.myapp.com"]
  ]
}
```

### 3. Use Strong Password Hashing

```php
// Use higher rounds for better security
$hash = $this->core->security->crypt($password, 12);
```

### 4. Encrypt Sensitive Data

```php
// Always encrypt PII, credit cards, etc.
$encrypted = $this->core->security->encrypt($sensitiveData);
```

### 5. Implement IP Restrictions

```json
{
  "core.system.authorizations": {
    "admin_user": {
      "password": "hash",
      "ips": "192.168.1.0/24,10.0.0.1"
    }
  }
}
```

---

## See Also

- [Core7 Class Reference](Core7.md)
- [CoreSession Class Reference](CoreSession.md)
- [Security Guide](../guides/security.md)
- [Authentication Examples](../examples/api-examples.md#authentication)
