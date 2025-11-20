# Testing Guide

## Overview

Testing is essential for building reliable applications. This guide covers testing strategies and best practices for CloudFramework applications, including:

- Unit testing
- Integration testing
- API testing
- Database testing
- GCP service testing
- Load testing
- Continuous integration

---

## Table of Contents

1. [Testing Setup](#testing-setup)
2. [Unit Testing](#unit-testing)
3. [Integration Testing](#integration-testing)
4. [API Testing](#api-testing)
5. [Database Testing](#database-testing)
6. [GCP Services Testing](#gcp-services-testing)
7. [Mocking and Test Doubles](#mocking-and-test-doubles)
8. [Test Automation](#test-automation)
9. [Load Testing](#load-testing)
10. [Best Practices](#best-practices)

---

## Testing Setup

### Install PHPUnit

**composer.json:**

```json
{
  "require-dev": {
    "phpunit/phpunit": "^10.0",
    "mockery/mockery": "^1.5"
  }
}
```

**Install:**
```bash
composer require --dev phpunit/phpunit mockery/mockery
```

### Project Structure

```
project/
├── app/
│   ├── api/
│   └── scripts/
├── tests/
│   ├── Unit/
│   │   ├── UserTest.php
│   │   └── HelperTest.php
│   ├── Integration/
│   │   ├── DataStoreTest.php
│   │   └── CloudSQLTest.php
│   └── Feature/
│       ├── UserAPITest.php
│       └── AuthTest.php
├── phpunit.xml
└── composer.json
```

### phpunit.xml Configuration

```xml
<?xml version="1.0" encoding="UTF-8"?>
<phpunit xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
         xsi:noNamespaceSchemaLocation="vendor/phpunit/phpunit/phpunit.xsd"
         bootstrap="vendor/autoload.php"
         colors="true"
         stopOnFailure="false">
    <testsuites>
        <testsuite name="Unit">
            <directory>tests/Unit</directory>
        </testsuite>
        <testsuite name="Integration">
            <directory>tests/Integration</directory>
        </testsuite>
        <testsuite name="Feature">
            <directory>tests/Feature</directory>
        </testsuite>
    </testsuites>
    <php>
        <env name="APP_ENV" value="testing"/>
        <env name="GCP_PROJECT_ID" value="test-project"/>
        <env name="DB_DATABASE" value="test_database"/>
    </php>
    <coverage includeUncoveredFiles="true">
        <include>
            <directory suffix=".php">app</directory>
        </include>
        <exclude>
            <directory>vendor</directory>
            <directory>tests</directory>
        </exclude>
    </coverage>
</phpunit>
```

### Run Tests

```bash
# Run all tests
./vendor/bin/phpunit

# Run specific test suite
./vendor/bin/phpunit --testsuite Unit

# Run single test file
./vendor/bin/phpunit tests/Unit/UserTest.php

# Run with coverage
./vendor/bin/phpunit --coverage-html coverage/

# Run specific test method
./vendor/bin/phpunit --filter testUserCreation
```

---

## Unit Testing

### Basic Unit Test

**tests/Unit/HelperTest.php:**

```php
<?php
namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class HelperTest extends TestCase
{
    public function testValidateEmail()
    {
        $this->assertTrue($this->isValidEmail('user@example.com'));
        $this->assertFalse($this->isValidEmail('invalid-email'));
        $this->assertFalse($this->isValidEmail(''));
    }

    public function testSanitizeString()
    {
        $input = '<script>alert("xss")</script>Hello';
        $expected = 'Hello';

        $result = $this->sanitizeString($input);

        $this->assertEquals($expected, $result);
    }

    public function testCalculateAge()
    {
        $birthdate = '1990-01-01';
        $age = $this->calculateAge($birthdate);

        $this->assertGreaterThanOrEqual(34, $age);
        $this->assertIsInt($age);
    }

    // Helper methods (these would be in your actual application)
    private function isValidEmail(string $email): bool
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    private function sanitizeString(string $input): string
    {
        return strip_tags($input);
    }

    private function calculateAge(string $birthdate): int
    {
        $birth = new \DateTime($birthdate);
        $now = new \DateTime();
        return $birth->diff($now)->y;
    }
}
```

### Testing Custom Classes

**app/class/UserValidator.php:**

```php
<?php
class UserValidator
{
    public function validate(array $userData): array
    {
        $errors = [];

        if(empty($userData['name'])) {
            $errors[] = 'Name is required';
        } elseif(strlen($userData['name']) < 2) {
            $errors[] = 'Name must be at least 2 characters';
        }

        if(empty($userData['email'])) {
            $errors[] = 'Email is required';
        } elseif(!filter_var($userData['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Invalid email format';
        }

        if(isset($userData['age']) && ($userData['age'] < 18 || $userData['age'] > 120)) {
            $errors[] = 'Age must be between 18 and 120';
        }

        return $errors;
    }
}
```

**tests/Unit/UserValidatorTest.php:**

```php
<?php
namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../app/class/UserValidator.php';

class UserValidatorTest extends TestCase
{
    private $validator;

    protected function setUp(): void
    {
        $this->validator = new \UserValidator();
    }

    public function testValidUserData()
    {
        $userData = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'age' => 30
        ];

        $errors = $this->validator->validate($userData);

        $this->assertEmpty($errors);
    }

    public function testMissingName()
    {
        $userData = [
            'email' => 'john@example.com'
        ];

        $errors = $this->validator->validate($userData);

        $this->assertContains('Name is required', $errors);
    }

    public function testInvalidEmail()
    {
        $userData = [
            'name' => 'John Doe',
            'email' => 'invalid-email'
        ];

        $errors = $this->validator->validate($userData);

        $this->assertContains('Invalid email format', $errors);
    }

    public function testInvalidAge()
    {
        $userData = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'age' => 15
        ];

        $errors = $this->validator->validate($userData);

        $this->assertContains('Age must be between 18 and 120', $errors);
    }

    public function testMultipleErrors()
    {
        $userData = [
            'name' => 'J',
            'email' => 'invalid',
            'age' => 200
        ];

        $errors = $this->validator->validate($userData);

        $this->assertCount(3, $errors);
    }
}
```

---

## Integration Testing

### Testing with Database

**tests/Integration/UserRepositoryTest.php:**

```php
<?php
namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use CloudFramework\Core;

class UserRepositoryTest extends TestCase
{
    private $core;
    private $sql;

    protected function setUp(): void
    {
        $this->core = new Core();
        $this->sql = $this->core->loadClass('CloudSQL', [
            '127.0.0.1',
            'root',
            'password',
            'test_database'
        ]);

        // Create test table
        $this->sql->command("
            CREATE TABLE IF NOT EXISTS users (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(100),
                email VARCHAR(100) UNIQUE,
                age INT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");

        // Clean table
        $this->sql->command("TRUNCATE TABLE users");
    }

    protected function tearDown(): void
    {
        // Clean up after each test
        $this->sql->command("TRUNCATE TABLE users");
        $this->sql->close();
    }

    public function testCreateUser()
    {
        $result = $this->sql->command(
            "INSERT INTO users (name, email, age) VALUES (?, ?, ?)",
            ['John Doe', 'john@example.com', 30]
        );

        $this->assertNotFalse($result);

        $userId = $this->sql->getInsertId();
        $this->assertGreaterThan(0, $userId);

        // Verify user was created
        $users = $this->sql->command("SELECT * FROM users WHERE id = ?", [$userId]);
        $this->assertCount(1, $users);
        $this->assertEquals('John Doe', $users[0]['name']);
    }

    public function testDuplicateEmail()
    {
        // Insert first user
        $this->sql->command(
            "INSERT INTO users (name, email, age) VALUES (?, ?, ?)",
            ['John Doe', 'john@example.com', 30]
        );

        // Try to insert duplicate email
        $this->sql->command(
            "INSERT INTO users (name, email, age) VALUES (?, ?, ?)",
            ['Jane Doe', 'john@example.com', 25]
        );

        // Should have error
        $this->assertTrue($this->sql->error());
    }

    public function testUpdateUser()
    {
        // Create user
        $this->sql->command(
            "INSERT INTO users (name, email, age) VALUES (?, ?, ?)",
            ['John Doe', 'john@example.com', 30]
        );
        $userId = $this->sql->getInsertId();

        // Update user
        $this->sql->command(
            "UPDATE users SET name = ?, age = ? WHERE id = ?",
            ['John Smith', 31, $userId]
        );

        // Verify update
        $users = $this->sql->command("SELECT * FROM users WHERE id = ?", [$userId]);
        $this->assertEquals('John Smith', $users[0]['name']);
        $this->assertEquals(31, $users[0]['age']);
    }

    public function testDeleteUser()
    {
        // Create user
        $this->sql->command(
            "INSERT INTO users (name, email, age) VALUES (?, ?, ?)",
            ['John Doe', 'john@example.com', 30]
        );
        $userId = $this->sql->getInsertId();

        // Delete user
        $this->sql->command("DELETE FROM users WHERE id = ?", [$userId]);

        // Verify deletion
        $users = $this->sql->command("SELECT * FROM users WHERE id = ?", [$userId]);
        $this->assertEmpty($users);
    }
}
```

---

## API Testing

### Testing API Endpoints

**tests/Feature/UserAPITest.php:**

```php
<?php
namespace Tests\Feature;

use PHPUnit\Framework\TestCase;

class UserAPITest extends TestCase
{
    private $baseUrl = 'http://localhost:8080';
    private $apiKey = 'test-api-key';

    public function testListUsers()
    {
        $response = $this->get('/api/users');

        $this->assertEquals(200, $response['status']);
        $this->assertArrayHasKey('data', $response);
        $this->assertIsArray($response['data']);
    }

    public function testCreateUser()
    {
        $userData = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'age' => 30
        ];

        $response = $this->post('/api/users/create', $userData);

        $this->assertEquals(201, $response['status']);
        $this->assertArrayHasKey('data', $response);
        $this->assertArrayHasKey('KeyId', $response['data']);
    }

    public function testCreateUserValidation()
    {
        $userData = [
            'name' => 'J', // Too short
            'email' => 'invalid-email',
            'age' => 15 // Too young
        ];

        $response = $this->post('/api/users/create', $userData);

        $this->assertEquals(400, $response['status']);
        $this->assertArrayHasKey('error', $response);
    }

    public function testGetUser()
    {
        // First create a user
        $userData = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'age' => 30
        ];

        $createResponse = $this->post('/api/users/create', $userData);
        $userId = $createResponse['data']['KeyId'];

        // Then fetch it
        $response = $this->get("/api/users/{$userId}");

        $this->assertEquals(200, $response['status']);
        $this->assertEquals('John Doe', $response['data']['name']);
    }

    public function testUpdateUser()
    {
        // Create user
        $userData = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'age' => 30
        ];

        $createResponse = $this->post('/api/users/create', $userData);
        $userId = $createResponse['data']['KeyId'];

        // Update user
        $updateData = [
            'name' => 'John Smith',
            'age' => 31
        ];

        $response = $this->put("/api/users/update/{$userId}", $updateData);

        $this->assertEquals(200, $response['status']);
        $this->assertEquals('John Smith', $response['data']['name']);
    }

    public function testDeleteUser()
    {
        // Create user
        $userData = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'age' => 30
        ];

        $createResponse = $this->post('/api/users/create', $userData);
        $userId = $createResponse['data']['KeyId'];

        // Delete user
        $response = $this->delete("/api/users/delete/{$userId}");

        $this->assertEquals(204, $response['status']);

        // Verify user is deleted
        $getResponse = $this->get("/api/users/{$userId}");
        $this->assertEquals(404, $getResponse['status']);
    }

    public function testUnauthorizedAccess()
    {
        $response = $this->getWithoutAuth('/api/users');

        $this->assertEquals(401, $response['status']);
    }

    // Helper methods
    private function get(string $path): array
    {
        return $this->request('GET', $path);
    }

    private function post(string $path, array $data): array
    {
        return $this->request('POST', $path, $data);
    }

    private function put(string $path, array $data): array
    {
        return $this->request('PUT', $path, $data);
    }

    private function delete(string $path): array
    {
        return $this->request('DELETE', $path);
    }

    private function request(string $method, string $path, array $data = []): array
    {
        $url = $this->baseUrl . $path;

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'X-API-Key: ' . $this->apiKey
        ]);

        if(!empty($data)) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }

        $response = curl_exec($ch);
        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return [
            'status' => $statusCode,
            ...(json_decode($response, true) ?? [])
        ];
    }

    private function getWithoutAuth(string $path): array
    {
        $url = $this->baseUrl . $path;

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $response = curl_exec($ch);
        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return [
            'status' => $statusCode,
            ...(json_decode($response, true) ?? [])
        ];
    }
}
```

### Testing with cURL

```bash
# Test GET endpoint
curl -X GET http://localhost:8080/api/users \
  -H "X-API-Key: your-api-key"

# Test POST endpoint
curl -X POST http://localhost:8080/api/users/create \
  -H "Content-Type: application/json" \
  -H "X-API-Key: your-api-key" \
  -d '{
    "name": "John Doe",
    "email": "john@example.com",
    "age": 30
  }'

# Test PUT endpoint
curl -X PUT http://localhost:8080/api/users/update/12345 \
  -H "Content-Type: application/json" \
  -H "X-API-Key: your-api-key" \
  -d '{
    "name": "John Smith",
    "age": 31
  }'

# Test DELETE endpoint
curl -X DELETE http://localhost:8080/api/users/delete/12345 \
  -H "X-API-Key: your-api-key"

# Test with JWT token
curl -X GET http://localhost:8080/api/users \
  -H "Authorization: Bearer eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9..."

# Save response to file
curl -X GET http://localhost:8080/api/users \
  -H "X-API-Key: your-api-key" \
  -o response.json

# Show response headers
curl -X GET http://localhost:8080/api/users \
  -H "X-API-Key: your-api-key" \
  -i
```

---

## Database Testing

### DataStore Testing

**tests/Integration/DataStoreTest.php:**

```php
<?php
namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use CloudFramework\Core;

class DataStoreTest extends TestCase
{
    private $core;
    private $ds;
    private $testEntityIds = [];

    protected function setUp(): void
    {
        $this->core = new Core();
        $this->ds = $this->core->loadClass('DataStore', ['TestUsers']);
    }

    protected function tearDown(): void
    {
        // Clean up test entities
        foreach($this->testEntityIds as $id) {
            $this->ds->delete($id);
        }
    }

    public function testCreateEntity()
    {
        $entityData = [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'age' => 30
        ];

        $entityId = $this->ds->createEntity($entityData);
        $this->testEntityIds[] = $entityId;

        $this->assertNotEmpty($entityId);
        $this->assertIsString($entityId);
    }

    public function testFetchOne()
    {
        // Create entity
        $entityData = [
            'name' => 'Test User',
            'email' => 'test@example.com'
        ];

        $entityId = $this->ds->createEntity($entityData);
        $this->testEntityIds[] = $entityId;

        // Fetch entity
        $entity = $this->ds->fetchOne($entityId);

        $this->assertNotNull($entity);
        $this->assertEquals('Test User', $entity['name']);
        $this->assertEquals($entityId, $entity['KeyId']);
    }

    public function testFetchAll()
    {
        // Create multiple entities
        for($i = 0; $i < 5; $i++) {
            $id = $this->ds->createEntity([
                'name' => "User {$i}",
                'age' => 20 + $i
            ]);
            $this->testEntityIds[] = $id;
        }

        // Fetch all
        $entities = $this->ds->fetchAll();

        $this->assertGreaterThanOrEqual(5, count($entities));
    }

    public function testFetchAllWithFilter()
    {
        // Create entities with different ages
        $id1 = $this->ds->createEntity(['name' => 'Young User', 'age' => 20]);
        $id2 = $this->ds->createEntity(['name' => 'Old User', 'age' => 50]);

        $this->testEntityIds[] = $id1;
        $this->testEntityIds[] = $id2;

        // Fetch with filter
        $entities = $this->ds->fetchAll([['age', '>', 30]]);

        $filtered = array_filter($entities, fn($e) => $e['KeyId'] === $id2);
        $this->assertNotEmpty($filtered);
    }

    public function testUpdateEntity()
    {
        // Create entity
        $entityId = $this->ds->createEntity([
            'name' => 'Original Name',
            'age' => 30
        ]);
        $this->testEntityIds[] = $entityId;

        // Update entity
        $this->ds->updateEntity($entityId, [
            'name' => 'Updated Name',
            'age' => 31
        ]);

        // Fetch and verify
        $entity = $this->ds->fetchOne($entityId);

        $this->assertEquals('Updated Name', $entity['name']);
        $this->assertEquals(31, $entity['age']);
    }

    public function testDeleteEntity()
    {
        // Create entity
        $entityId = $this->ds->createEntity([
            'name' => 'To Delete',
            'age' => 30
        ]);

        // Delete entity
        $result = $this->ds->delete($entityId);

        $this->assertTrue($result);

        // Verify deletion
        $entity = $this->ds->fetchOne($entityId);
        $this->assertNull($entity);
    }

    public function testCount()
    {
        // Create entities
        for($i = 0; $i < 3; $i++) {
            $id = $this->ds->createEntity(['name' => "User {$i}"]);
            $this->testEntityIds[] = $id;
        }

        // Count all
        $count = $this->ds->count();

        $this->assertGreaterThanOrEqual(3, $count);
    }
}
```

---

## GCP Services Testing

### Cloud Storage Testing

**tests/Integration/BucketsTest.php:**

```php
<?php
namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use CloudFramework\Core;

class BucketsTest extends TestCase
{
    private $core;
    private $buckets;
    private $testFiles = [];

    protected function setUp(): void
    {
        $this->core = new Core();
        $this->buckets = $this->core->loadClass('Buckets', ['gs://test-bucket']);
    }

    protected function tearDown(): void
    {
        // Clean up test files
        foreach($this->testFiles as $file) {
            $this->buckets->delete($file);
        }
    }

    public function testUploadFile()
    {
        $testContent = 'Test file content';
        $testFile = 'test/test-file.txt';

        $result = $this->buckets->uploadContents($testContent, $testFile);
        $this->testFiles[] = $testFile;

        $this->assertTrue($result);
    }

    public function testGetContents()
    {
        $testContent = 'Test file content';
        $testFile = 'test/test-file.txt';

        $this->buckets->uploadContents($testContent, $testFile);
        $this->testFiles[] = $testFile;

        $contents = $this->buckets->getContents($testFile);

        $this->assertEquals($testContent, $contents);
    }

    public function testFileExists()
    {
        $testFile = 'test/test-file.txt';

        $this->buckets->uploadContents('content', $testFile);
        $this->testFiles[] = $testFile;

        $exists = $this->buckets->fileExists($testFile);

        $this->assertTrue($exists);
    }

    public function testDeleteFile()
    {
        $testFile = 'test/test-file.txt';

        $this->buckets->uploadContents('content', $testFile);

        $result = $this->buckets->delete($testFile);

        $this->assertTrue($result);
        $this->assertFalse($this->buckets->fileExists($testFile));
    }
}
```

---

## Mocking and Test Doubles

### Mocking DataStore

```php
<?php
namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Mockery;

class UserServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
    }

    public function testGetUserById()
    {
        // Mock DataStore
        $mockDataStore = Mockery::mock('DataStore');
        $mockDataStore->shouldReceive('fetchOne')
            ->once()
            ->with('user123')
            ->andReturn([
                'KeyId' => 'user123',
                'name' => 'John Doe',
                'email' => 'john@example.com'
            ]);

        // Test service with mock
        $user = $mockDataStore->fetchOne('user123');

        $this->assertEquals('user123', $user['KeyId']);
        $this->assertEquals('John Doe', $user['name']);
    }

    public function testGetUserNotFound()
    {
        // Mock DataStore returning null
        $mockDataStore = Mockery::mock('DataStore');
        $mockDataStore->shouldReceive('fetchOne')
            ->once()
            ->with('nonexistent')
            ->andReturn(null);

        $user = $mockDataStore->fetchOne('nonexistent');

        $this->assertNull($user);
    }

    public function testCreateUser()
    {
        // Mock DataStore
        $mockDataStore = Mockery::mock('DataStore');
        $mockDataStore->shouldReceive('createEntity')
            ->once()
            ->with(['name' => 'John Doe', 'email' => 'john@example.com'])
            ->andReturn('new-user-id');

        $userId = $mockDataStore->createEntity([
            'name' => 'John Doe',
            'email' => 'john@example.com'
        ]);

        $this->assertEquals('new-user-id', $userId);
    }
}
```

---

## Test Automation

### GitHub Actions CI

**.github/workflows/tests.yml:**

```yaml
name: Tests

on:
  push:
    branches: [ main, develop ]
  pull_request:
    branches: [ main, develop ]

jobs:
  test:
    runs-on: ubuntu-latest

    services:
      mysql:
        image: mysql:8.0
        env:
          MYSQL_ROOT_PASSWORD: password
          MYSQL_DATABASE: test_database
        ports:
          - 3306:3306
        options: --health-cmd="mysqladmin ping" --health-interval=10s --health-timeout=5s --health-retries=3

    steps:
      - uses: actions/checkout@v3

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.3'
          extensions: pdo, pdo_mysql, mbstring

      - name: Install dependencies
        run: composer install --prefer-dist --no-progress

      - name: Run Unit Tests
        run: ./vendor/bin/phpunit --testsuite Unit

      - name: Run Integration Tests
        run: ./vendor/bin/phpunit --testsuite Integration
        env:
          DB_HOST: 127.0.0.1
          DB_PASSWORD: password
          DB_DATABASE: test_database

      - name: Generate Coverage Report
        run: ./vendor/bin/phpunit --coverage-clover coverage.xml

      - name: Upload Coverage
        uses: codecov/codecov-action@v3
        with:
          files: ./coverage.xml
```

### Pre-commit Hook

**.git/hooks/pre-commit:**

```bash
#!/bin/bash

echo "Running tests before commit..."

# Run PHPUnit tests
./vendor/bin/phpunit --testsuite Unit

if [ $? -ne 0 ]; then
    echo "Tests failed. Commit aborted."
    exit 1
fi

echo "All tests passed!"
exit 0
```

Make executable:
```bash
chmod +x .git/hooks/pre-commit
```

---

## Load Testing

### Apache Bench

```bash
# Simple load test
ab -n 1000 -c 10 http://localhost:8080/api/users

# With authentication
ab -n 1000 -c 10 -H "X-API-Key: test-key" http://localhost:8080/api/users

# POST request
ab -n 100 -c 10 -p data.json -T application/json \
  -H "X-API-Key: test-key" \
  http://localhost:8080/api/users/create
```

**data.json:**
```json
{
  "name": "Test User",
  "email": "test@example.com",
  "age": 30
}
```

### Using Apache JMeter

1. Download JMeter: https://jmeter.apache.org/
2. Create test plan with HTTP Request samplers
3. Configure thread groups for concurrent users
4. Add listeners for results

### Load Testing with K6

**load-test.js:**

```javascript
import http from 'k6/http';
import { check, sleep } from 'k6';

export let options = {
  stages: [
    { duration: '30s', target: 20 },  // Ramp up to 20 users
    { duration: '1m', target: 20 },   // Stay at 20 users
    { duration: '30s', target: 0 },   // Ramp down to 0 users
  ],
};

export default function () {
  let response = http.get('http://localhost:8080/api/users', {
    headers: { 'X-API-Key': 'test-key' },
  });

  check(response, {
    'status is 200': (r) => r.status === 200,
    'response time < 500ms': (r) => r.timings.duration < 500,
  });

  sleep(1);
}
```

**Run:**
```bash
k6 run load-test.js
```

---

## Best Practices

### 1. Write Testable Code

**Bad:**
```php
public function ENDPOINT_users()
{
    $sql = new CloudSQL('host', 'user', 'pass', 'db');
    $users = $sql->command("SELECT * FROM users");
    $this->addReturnData($users);
}
```

**Good:**
```php
public function ENDPOINT_users()
{
    $users = $this->getUsersFromDatabase();
    $this->addReturnData($users);
}

protected function getUsersFromDatabase(): array
{
    $sql = $this->core->loadClass('CloudSQL');
    return $sql->command("SELECT * FROM users");
}
```

### 2. Use Test Data Builders

```php
class UserBuilder
{
    private $data = [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'age' => 30
    ];

    public function withName(string $name): self
    {
        $this->data['name'] = $name;
        return $this;
    }

    public function withEmail(string $email): self
    {
        $this->data['email'] = $email;
        return $this;
    }

    public function withAge(int $age): self
    {
        $this->data['age'] = $age;
        return $this;
    }

    public function build(): array
    {
        return $this->data;
    }
}

// Usage in tests
$user = (new UserBuilder())
    ->withName('John Doe')
    ->withAge(25)
    ->build();
```

### 3. Separate Test Data

Create a fixtures directory:

**tests/fixtures/users.json:**
```json
[
  {
    "name": "John Doe",
    "email": "john@example.com",
    "age": 30
  },
  {
    "name": "Jane Smith",
    "email": "jane@example.com",
    "age": 25
  }
]
```

**Load in tests:**
```php
protected function loadFixture(string $name): array
{
    $file = __DIR__ . "/../fixtures/{$name}.json";
    return json_decode(file_get_contents($file), true);
}
```

### 4. Test Edge Cases

```php
public function testEmptyInput()
{
    // Test with empty data
}

public function testNullInput()
{
    // Test with null values
}

public function testVeryLargeInput()
{
    // Test with large data sets
}

public function testSpecialCharacters()
{
    // Test with special characters
}

public function testBoundaryValues()
{
    // Test min/max values
}
```

### 5. Use Descriptive Test Names

```php
// Good
public function testUserCannotRegisterWithInvalidEmail() {}
public function testAdminCanDeleteAnyUser() {}
public function testRegularUserCanOnlyDeleteOwnAccount() {}

// Bad
public function testUser1() {}
public function testDelete() {}
```

### 6. One Assert Per Test (when possible)

```php
// Good - focused test
public function testUserNameIsRequired()
{
    $errors = $this->validator->validate(['email' => 'test@example.com']);
    $this->assertContains('Name is required', $errors);
}

// Also good - related asserts
public function testUserCreation()
{
    $user = $this->createUser(['name' => 'John']);
    $this->assertNotNull($user['id']);
    $this->assertEquals('John', $user['name']);
}
```

### 7. Clean Up After Tests

```php
protected function tearDown(): void
{
    // Clean database
    // Remove test files
    // Clear cache
    // Close connections

    parent::tearDown();
}
```

---

## See Also

- [API Development Guide](api-development.md)
- [Security Guide](security.md)
- [Configuration Guide](configuration.md)
- [Deployment Guide](deployment.md)
