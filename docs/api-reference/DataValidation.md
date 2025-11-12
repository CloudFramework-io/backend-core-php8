# DataValidation Class

## Overview

The `DataValidation` class provides comprehensive data validation functionality for API requests and form data. It validates data types, formats, ranges, patterns, and custom rules based on model definitions.

## Loading the Class

```php
$validator = $this->core->loadClass('DataValidation');
```

## Main Methods

### validateModel()

```php
public function validateModel(array &$model, array &$data, array &$dictionaries = [], $all = true, $extrakey = ''): bool
```

Validates data against a model definition.

**Parameters:**
- `$model` (array): Validation model with field definitions
- `$data` (array): Data to validate (passed by reference, may be modified)
- `$dictionaries` (array): Optional lookup dictionaries
- `$all` (bool): Validate all fields (true) or only present ones (false)
- `$extrakey` (string): Extra key prefix for nested validation

**Returns:** `true` if valid, `false` otherwise

**Model Structure:**
```php
$model = [
    'field_name' => [
        'type' => 'string',       // Data type
        'required' => true,       // Is required
        'maxlength' => 100,       // Max length
        'minlength' => 3,         // Min length
        'email' => true,          // Email format
        'values' => ['a', 'b'],   // Allowed values
        'range' => [1, 100],      // Numeric range
        'regex' => '/^[A-Z]/',    // Regex pattern
        // ... more rules
    ]
];
```

**Example:**
```php
$validator = $this->core->loadClass('DataValidation');

$model = [
    'name' => ['type' => 'string', 'required' => true, 'maxlength' => 100],
    'email' => ['type' => 'string', 'required' => true, 'email' => true],
    'age' => ['type' => 'integer', 'range' => [18, 100]],
    'status' => ['type' => 'string', 'values' => ['active', 'inactive']]
];

$data = $this->formParams;

if($validator->validateModel($model, $data)) {
    // Data is valid, use $data
} else {
    // Validation failed
    $errors = $this->core->errors->getErrorMsg();
    return $this->setErrorFromCodelib('validation-error', $errors);
}
```

---

### validType()

```php
public function validType($key, $type, &$data): bool
```

Validates field type.

**Supported Types:**
- `string`
- `integer`, `int`
- `float`, `double`
- `boolean`, `bool`
- `array`
- `object`
- `email`
- `url`
- `date`, `datetime`, `timestamp`
- `json`

**Example:**
```php
if($validator->validType('age', 'integer', $data)) {
    // Age is valid integer
}
```

---

### validateEmail()

```php
public function validateEmail($key, $options, $data): bool
```

Validates email format.

**Example:**
```php
$validator->validateEmail('email', [], $data);
```

---

### validateDate()

```php
public function validateDate($data): bool
```

Validates date format (YYYY-MM-DD).

---

### validateDateTime()

```php
public function validateDateTime($data): bool
```

Validates datetime format.

---

### validateMaxLength()

```php
public function validateMaxLength($key, $options, $data): bool
```

Validates maximum string length.

**Example:**
```php
$model = ['name' => ['type' => 'string', 'maxlength' => 100]];
```

---

### validateMinLength()

```php
public function validateMinLength($key, $options, $data): bool
```

Validates minimum string length.

---

### validateRange()

```php
public function validateRange($key, $options, $data): bool
```

Validates numeric range.

**Example:**
```php
$model = ['age' => ['type' => 'integer', 'range' => [18, 100]]];
```

---

### validateValues()

```php
public function validateValues($key, $options, $data): bool
```

Validates value against allowed values list.

**Example:**
```php
$model = ['status' => ['type' => 'string', 'values' => ['active', 'inactive', 'pending']]];
```

---

### validateRegexMatch()

```php
public function validateRegexMatch($key, $options, $data): bool
```

Validates value against regex pattern.

**Example:**
```php
$model = ['code' => ['type' => 'string', 'regex' => '/^[A-Z]{2}[0-9]{4}$/']];
```

---

### validateUnsigned()

```php
public function validateUnsigned($key, $options, $data): bool
```

Validates unsigned integer (positive number).

---

### transformValue()

```php
public function transformValue($data, $options): mixed
```

Transforms value based on options (trim, uppercase, lowercase, etc.).

---

## Common Usage Patterns

### API Request Validation

```php
class API extends RESTful
{
    public function ENDPOINT_create_user()
    {
        if(!$this->checkMethod('POST')) return;

        // Define validation model
        $model = [
            'name' => [
                'type' => 'string',
                'required' => true,
                'minlength' => 2,
                'maxlength' => 100
            ],
            'email' => [
                'type' => 'string',
                'required' => true,
                'email' => true
            ],
            'age' => [
                'type' => 'integer',
                'required' => true,
                'range' => [18, 120]
            ],
            'country' => [
                'type' => 'string',
                'required' => true,
                'values' => ['US', 'UK', 'ES', 'FR', 'DE']
            ],
            'phone' => [
                'type' => 'string',
                'regex' => '/^\+?[0-9]{10,15}$/'
            ]
        ];

        // Validate
        $validator = $this->core->loadClass('DataValidation');
        $data = $this->formParams;

        if(!$validator->validateModel($model, $data)) {
            $errors = $this->core->errors->getErrorMsg();
            return $this->setErrorFromCodelib('validation-error', json_encode($errors));
        }

        // Data is valid - create user
        $userId = $this->createUser($data);

        $this->addReturnData(['user_id' => $userId]);
    }
}
```

### Complex Validation

```php
$model = [
    'product_name' => [
        'type' => 'string',
        'required' => true,
        'minlength' => 3,
        'maxlength' => 200
    ],
    'price' => [
        'type' => 'float',
        'required' => true,
        'range' => [0.01, 999999.99],
        'unsigned' => true
    ],
    'quantity' => [
        'type' => 'integer',
        'required' => true,
        'range' => [0, 10000],
        'unsigned' => true
    ],
    'category' => [
        'type' => 'string',
        'required' => true,
        'values' => ['electronics', 'clothing', 'food', 'books']
    ],
    'sku' => [
        'type' => 'string',
        'required' => true,
        'regex' => '/^[A-Z]{3}-[0-9]{6}$/',
        'fixlength' => 10
    ],
    'description' => [
        'type' => 'string',
        'maxlength' => 1000
    ],
    'tags' => [
        'type' => 'array'
    ],
    'metadata' => [
        'type' => 'json'
    ]
];

$validator = $this->core->loadClass('DataValidation');
if(!$validator->validateModel($model, $productData)) {
    // Handle validation errors
}
```

### Partial Validation (Update)

```php
// Only validate fields that are present
$model = [
    'name' => ['type' => 'string', 'maxlength' => 100],
    'email' => ['type' => 'string', 'email' => true],
    'status' => ['type' => 'string', 'values' => ['active', 'inactive']]
];

$data = $this->formParams;

// Validate only present fields (not all)
$validator = $this->core->loadClass('DataValidation');
if($validator->validateModel($model, $data, [], false)) {
    // Update user with $data
    $this->updateUser($userId, $data);
}
```

---

## Validation Rules

### Type Validation

- `type: 'string'` - String value
- `type: 'integer'` - Integer number
- `type: 'float'` - Floating point number
- `type: 'boolean'` - Boolean true/false
- `type: 'array'` - Array value
- `type: 'email'` - Valid email format
- `type: 'url'` - Valid URL format
- `type: 'date'` - Valid date (YYYY-MM-DD)
- `type: 'datetime'` - Valid datetime
- `type: 'json'` - Valid JSON string

### String Rules

- `required: true` - Field is mandatory
- `maxlength: N` - Maximum string length
- `minlength: N` - Minimum string length
- `fixlength: N` - Exact string length
- `regex: '/pattern/'` - Must match regex pattern

### Numeric Rules

- `range: [min, max]` - Number must be in range
- `unsigned: true` - Must be positive number

### Value Rules

- `values: [...]` - Must be one of allowed values

---

## See Also

- [RESTful Class Reference](RESTful.md)
- [API Development Guide](../guides/api-development.md)
- [Security Guide](../guides/security.md)
