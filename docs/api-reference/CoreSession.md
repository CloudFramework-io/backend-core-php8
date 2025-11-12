# CoreSession Class

## Overview

The `CoreSession` class provides a simple and efficient session management system in CloudFramework. It wraps PHP's native session handling with automatic compression and serialization of session data.

## Inheritance

Access CoreSession through the Core7 instance:

```php
// Set session variable
$this->core->session->set('user_id', 123);

// Get session variable
$userId = $this->core->session->get('user_id');

// Delete session variable
$this->core->session->delete('user_id');
```

## Properties

### Public Properties

| Property | Type | Description |
|----------|------|-------------|
| `$start` | bool | Whether session has been started |
| `$id` | string | Current session ID |
| `$debug` | bool | Debug mode flag (auto-enabled in development) |
| `$core` | Core7 | Reference to Core7 instance |

---

## Constructor

```php
function __construct(Core7 &$core, $debug = null)
```

Initializes the session manager.

**Parameters:**
- `$core` (Core7): Reference to Core7 instance
- `$debug` (bool\|null): Enable debug mode (optional, auto-enabled in development)

**Auto-initialization:**
- Detects if session is already active
- Automatically enables debug mode in development environment
- Stores session ID if session is active

---

## Session Methods

### init()

```php
function init($id = ''): void
```

Initializes or resumes a session.

**Parameters:**
- `$id` (string): Optional session ID to use (useful for session restoration)

**Behavior:**
- Starts a new session if none exists
- Resumes existing session if already active
- Can switch to a different session ID if provided
- Automatically called by `get()`, `set()`, and `delete()` if session not started

**Example:**
```php
// Start session with default ID
$this->core->session->init();

// Start session with specific ID (useful for session restoration)
$sessionId = $_COOKIE['custom_session_id'] ?? '';
if($sessionId) {
    $this->core->session->init($sessionId);
}

// Get session ID
$currentSessionId = $this->core->session->id;
```

---

### get()

```php
function get($var): mixed
```

Retrieves a variable from the session.

**Parameters:**
- `$var` (string): Variable name

**Returns:** Variable value, or `null` if not found

**Example:**
```php
// Get simple value
$userId = $this->core->session->get('user_id');

// Get complex object
$userData = $this->core->session->get('user_data');
// Returns: ['name' => 'John', 'email' => 'john@example.com']

// Check if variable exists
$cart = $this->core->session->get('shopping_cart');
if($cart === null) {
    // Variable doesn't exist
    $cart = [];
}

// Get with default
$language = $this->core->session->get('language') ?? 'en';
```

**Data Handling:**
- Automatically initializes session if not started
- Uncompresses and unserializes stored data
- Returns `null` if variable doesn't exist
- Handles exceptions gracefully

---

### set()

```php
function set($var, $value): void
```

Stores a variable in the session.

**Parameters:**
- `$var` (string): Variable name
- `$value` (mixed): Variable value (any serializable type)

**Example:**
```php
// Set simple value
$this->core->session->set('user_id', 123);

// Set complex object
$this->core->session->set('user_data', [
    'id' => 123,
    'name' => 'John Doe',
    'email' => 'john@example.com',
    'roles' => ['user', 'admin']
]);

// Set array
$this->core->session->set('shopping_cart', [
    ['product_id' => 1, 'quantity' => 2],
    ['product_id' => 5, 'quantity' => 1]
]);

// Update existing value
$cart = $this->core->session->get('shopping_cart') ?? [];
$cart[] = ['product_id' => 10, 'quantity' => 1];
$this->core->session->set('shopping_cart', $cart);
```

**Data Handling:**
- Automatically initializes session if not started
- Serializes and compresses data before storing
- Supports any serializable PHP type (arrays, objects, etc.)

---

### delete()

```php
function delete($var): void
```

Removes a variable from the session.

**Parameters:**
- `$var` (string): Variable name to delete

**Example:**
```php
// Delete single variable
$this->core->session->delete('user_id');

// Clear user session on logout
$this->core->session->delete('user_id');
$this->core->session->delete('user_data');
$this->core->session->delete('shopping_cart');

// Or use a loop
$sessionVars = ['user_id', 'user_data', 'shopping_cart', 'preferences'];
foreach($sessionVars as $var) {
    $this->core->session->delete($var);
}
```

---

## Common Usage Patterns

### User Authentication

```php
class API extends RESTful
{
    public function ENDPOINT_login()
    {
        $email = $this->formParams['email'] ?? '';
        $password = $this->formParams['password'] ?? '';

        // Validate credentials
        $user = $this->validateCredentials($email, $password);
        if($user) {
            // Store user in session
            $this->core->session->set('user_id', $user['id']);
            $this->core->session->set('user_data', $user);
            $this->core->session->set('authenticated', true);

            $this->addReturnData([
                'message' => 'Login successful',
                'user' => $user
            ]);
        } else {
            $this->setErrorFromCodelib('auth-error', 'Invalid credentials');
        }
    }

    public function ENDPOINT_logout()
    {
        // Clear session variables
        $this->core->session->delete('user_id');
        $this->core->session->delete('user_data');
        $this->core->session->delete('authenticated');

        $this->addReturnData('Logout successful');
    }

    protected function checkAuthentication(): bool
    {
        return $this->core->session->get('authenticated') === true;
    }
}
```

### Shopping Cart

```php
class API extends RESTful
{
    public function ENDPOINT_cart_add()
    {
        $productId = $this->formParams['product_id'] ?? '';
        $quantity = (int)($this->formParams['quantity'] ?? 1);

        if(!$productId) {
            return $this->setErrorFromCodelib('params-error', 'Product ID required');
        }

        // Get current cart
        $cart = $this->core->session->get('shopping_cart') ?? [];

        // Add or update product
        $found = false;
        foreach($cart as &$item) {
            if($item['product_id'] == $productId) {
                $item['quantity'] += $quantity;
                $found = true;
                break;
            }
        }

        if(!$found) {
            $cart[] = [
                'product_id' => $productId,
                'quantity' => $quantity,
                'added_at' => time()
            ];
        }

        // Save cart
        $this->core->session->set('shopping_cart', $cart);

        $this->addReturnData([
            'cart' => $cart,
            'total_items' => count($cart)
        ]);
    }

    public function ENDPOINT_cart_get()
    {
        $cart = $this->core->session->get('shopping_cart') ?? [];

        $this->addReturnData([
            'cart' => $cart,
            'total_items' => count($cart)
        ]);
    }

    public function ENDPOINT_cart_clear()
    {
        $this->core->session->delete('shopping_cart');
        $this->addReturnData('Cart cleared');
    }
}
```

### Multi-step Forms (Wizard)

```php
class API extends RESTful
{
    public function ENDPOINT_wizard_step1()
    {
        if(!$this->checkMethod('POST')) return;

        $data = [
            'company_name' => $this->formParams['company_name'] ?? '',
            'industry' => $this->formParams['industry'] ?? ''
        ];

        // Validate
        if(!$data['company_name']) {
            return $this->setErrorFromCodelib('validation-error', 'Company name required');
        }

        // Store in session
        $wizardData = $this->core->session->get('wizard_data') ?? [];
        $wizardData['step1'] = $data;
        $wizardData['current_step'] = 2;
        $this->core->session->set('wizard_data', $wizardData);

        $this->addReturnData([
            'message' => 'Step 1 completed',
            'next_step' => 2
        ]);
    }

    public function ENDPOINT_wizard_step2()
    {
        if(!$this->checkMethod('POST')) return;

        // Get previous data
        $wizardData = $this->core->session->get('wizard_data') ?? [];
        if(empty($wizardData['step1'])) {
            return $this->setErrorFromCodelib('state-error', 'Please complete step 1 first');
        }

        // Store step 2 data
        $wizardData['step2'] = [
            'contact_name' => $this->formParams['contact_name'] ?? '',
            'email' => $this->formParams['email'] ?? ''
        ];
        $wizardData['current_step'] = 3;
        $this->core->session->set('wizard_data', $wizardData);

        $this->addReturnData([
            'message' => 'Step 2 completed',
            'next_step' => 3
        ]);
    }

    public function ENDPOINT_wizard_complete()
    {
        if(!$this->checkMethod('POST')) return;

        // Get all wizard data
        $wizardData = $this->core->session->get('wizard_data') ?? [];

        if(empty($wizardData['step1']) || empty($wizardData['step2'])) {
            return $this->setErrorFromCodelib('state-error', 'Please complete all steps');
        }

        // Process complete registration
        $result = $this->processRegistration($wizardData);

        // Clear wizard data
        $this->core->session->delete('wizard_data');

        $this->addReturnData([
            'message' => 'Registration completed',
            'result' => $result
        ]);
    }
}
```

### User Preferences

```php
class API extends RESTful
{
    function main()
    {
        // Load user preferences from session
        $preferences = $this->core->session->get('user_preferences') ?? [
            'language' => 'en',
            'theme' => 'light',
            'timezone' => 'UTC',
            'items_per_page' => 25
        ];

        // Apply preferences
        $this->core->config->setLang($preferences['language']);
        date_default_timezone_set($preferences['timezone']);

        // Route to endpoint
        if(!$this->useFunction('ENDPOINT_' . ($this->params[0] ?? 'default'))) {
            return $this->setErrorFromCodelib('params-error', 'Endpoint not found');
        }
    }

    public function ENDPOINT_preferences_set()
    {
        if(!$this->checkMethod('POST')) return;

        // Get current preferences
        $preferences = $this->core->session->get('user_preferences') ?? [];

        // Update preferences
        if(isset($this->formParams['language'])) {
            $preferences['language'] = $this->formParams['language'];
        }
        if(isset($this->formParams['theme'])) {
            $preferences['theme'] = $this->formParams['theme'];
        }
        if(isset($this->formParams['timezone'])) {
            $preferences['timezone'] = $this->formParams['timezone'];
        }
        if(isset($this->formParams['items_per_page'])) {
            $preferences['items_per_page'] = (int)$this->formParams['items_per_page'];
        }

        // Save preferences
        $this->core->session->set('user_preferences', $preferences);

        $this->addReturnData([
            'message' => 'Preferences updated',
            'preferences' => $preferences
        ]);
    }
}
```

### Flash Messages

```php
class API extends RESTful
{
    protected function setFlashMessage($type, $message)
    {
        $this->core->session->set('flash_message', [
            'type' => $type,
            'message' => $message
        ]);
    }

    protected function getFlashMessage()
    {
        $flash = $this->core->session->get('flash_message');
        if($flash) {
            $this->core->session->delete('flash_message');
        }
        return $flash;
    }

    public function ENDPOINT_save_data()
    {
        if(!$this->checkMethod('POST')) return;

        // Process data
        $success = $this->saveData($this->formParams);

        if($success) {
            $this->setFlashMessage('success', 'Data saved successfully');
        } else {
            $this->setFlashMessage('error', 'Failed to save data');
        }

        $this->addReturnData(['redirect' => '/dashboard']);
    }

    public function ENDPOINT_dashboard()
    {
        $flash = $this->getFlashMessage();

        $this->addReturnData([
            'dashboard_data' => $this->getDashboardData(),
            'flash_message' => $flash
        ]);
    }
}
```

### Session Recovery

```php
// In a custom session handler or API endpoint
class API extends RESTful
{
    public function ENDPOINT_session_restore()
    {
        $sessionId = $this->formParams['session_id'] ?? '';

        if(!$sessionId) {
            return $this->setErrorFromCodelib('params-error', 'Session ID required');
        }

        // Validate session ID format/security
        if(!$this->isValidSessionId($sessionId)) {
            return $this->setErrorFromCodelib('security-error', 'Invalid session ID');
        }

        // Restore session
        $this->core->session->init($sessionId);

        // Check if session has user data
        $userId = $this->core->session->get('user_id');
        if($userId) {
            $userData = $this->core->session->get('user_data');
            $this->addReturnData([
                'message' => 'Session restored',
                'user' => $userData
            ]);
        } else {
            $this->setErrorFromCodelib('auth-error', 'No active session found');
        }
    }
}
```

---

## Session Security

### Session ID Management

```php
// Get current session ID (for cookies, etc.)
$sessionId = $this->core->session->id;

// Store in secure cookie
setcookie('app_session', $sessionId, [
    'expires' => time() + 3600,
    'path' => '/',
    'secure' => true,
    'httponly' => true,
    'samesite' => 'Strict'
]);
```

### Session Regeneration (Anti-Fixation)

```php
// After successful login, regenerate session ID
public function ENDPOINT_login()
{
    $user = $this->validateCredentials($email, $password);

    if($user) {
        // Regenerate session ID to prevent session fixation
        session_regenerate_id(true);

        // Update stored session ID
        $this->core->session->id = session_id();

        // Store user data
        $this->core->session->set('user_id', $user['id']);
        $this->core->session->set('authenticated', true);
    }
}
```

### Session Timeout

```php
// Track last activity
public function checkSessionTimeout()
{
    $lastActivity = $this->core->session->get('last_activity');
    $timeout = 1800; // 30 minutes

    if($lastActivity && (time() - $lastActivity) > $timeout) {
        // Session expired
        $this->core->session->delete('user_id');
        $this->core->session->delete('authenticated');
        $this->core->session->delete('last_activity');

        return false;
    }

    // Update last activity
    $this->core->session->set('last_activity', time());

    return true;
}
```

---

## Debug Mode

When debug mode is enabled, session operations are logged:

```php
// Enable debug
$this->core->session->debug = true;

// Operations will be logged
$this->core->session->set('test', 'value');
// Log: "set('$var=test') ok"

$value = $this->core->session->get('test');
// Log: "get('$var=test') found"

$this->core->session->delete('test');
// Log: "delete('$var=test') ok"
```

Debug mode is automatically enabled in development environment.

---

## Performance Considerations

### Data Compression

Session data is automatically compressed using gzip:

```php
// Data is compressed before storing
$this->core->session->set('large_data', $bigArray);
// Stored as: gzcompress(serialize($bigArray))

// And decompressed when retrieved
$data = $this->core->session->get('large_data');
// Returns: original $bigArray
```

This reduces session storage size significantly.

### Avoid Storing Large Objects

```php
// Bad: Storing huge datasets in session
$allProducts = $this->getAllProducts(); // 10,000 products
$this->core->session->set('products', $allProducts);

// Good: Store only IDs or use cache
$productIds = $this->getProductIds();
$this->core->session->set('product_ids', $productIds);

// Or use cache for large datasets
$this->core->cache->set('products', $allProducts, 3600);
```

---

## Important Notes

### Session Variable Naming

Session variables are prefixed with `CloudSessionVar_` internally:

```php
// You use
$this->core->session->set('user_id', 123);

// Actually stored as
$_SESSION['CloudSessionVar_user_id']

// This prevents conflicts with other session variables
```

### Serialization

All session data is automatically serialized and unserialized:

```php
// You can store any serializable type
$this->core->session->set('user', $userObject);
$this->core->session->set('array', ['a', 'b', 'c']);
$this->core->session->set('number', 123);
$this->core->session->set('bool', true);

// Retrieved with original type
$user = $this->core->session->get('user'); // object
$array = $this->core->session->get('array'); // array
$number = $this->core->session->get('number'); // int
$bool = $this->core->session->get('bool'); // bool
```

---

## See Also

- [Core7 Class Reference](Core7.md)
- [CoreSecurity Class Reference](CoreSecurity.md)
- [Security Guide](../guides/security.md)
- [API Development Guide](../guides/api-development.md)
