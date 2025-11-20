# API Examples

## Overview

This document provides complete, production-ready examples of CloudFramework APIs for common use cases. Each example includes:
- Complete source code
- Authentication and authorization
- Input validation
- Error handling
- Best practices

---

## Table of Contents

1. [User Management API](#user-management-api)
2. [E-commerce Products API](#e-commerce-products-api)
3. [Blog Posts API](#blog-posts-api)
4. [File Upload API](#file-upload-api)
5. [Authentication API](#authentication-api)
6. [Webhook Handler API](#webhook-handler-api)
7. [Analytics API](#analytics-api)
8. [Multi-tenant API](#multi-tenant-api)

---

## User Management API

Complete CRUD API for user management with authentication and authorization.

**File:** `app/api/users.php`

```php
<?php
/**
 * User Management API
 *
 * Endpoints:
 * GET    /users/list          - List all users (admin only)
 * GET    /users/profile/{id}  - Get user profile
 * POST   /users/create        - Create new user
 * PUT    /users/update/{id}   - Update user
 * DELETE /users/delete/{id}   - Delete user
 * POST   /users/search        - Search users
 */

use CloudFramework\Patterns\RESTful;

class API extends RESTful
{
    private $currentUser;
    private $ds;

    function main()
    {
        // Initialize DataStore
        $this->ds = $this->core->loadClass('DataStore', ['Users']);

        // Authenticate user
        if(!$this->authenticate()) {
            return $this->setError('Authentication required', 401);
        }

        // Route to endpoint
        $endpoint = $this->params[0] ?? 'list';

        if(!$this->useFunction('ENDPOINT_' . $endpoint)) {
            return $this->setErrorFromCodelib('not-found');
        }
    }

    /**
     * GET /users/list
     * List all users with pagination
     */
    public function ENDPOINT_list()
    {
        if(!$this->checkMethod('GET')) return;

        // Only admins can list all users
        if($this->currentUser['role'] !== 'admin') {
            return $this->setError('Admin access required', 403);
        }

        // Get pagination parameters
        $page = (int)($this->formParams['page'] ?? 1);
        $limit = (int)($this->formParams['limit'] ?? 20);
        $limit = min($limit, 100); // Max 100 per page

        // Cache key
        $cacheKey = "users_list_page_{$page}_limit_{$limit}";
        $cached = $this->core->cache->get($cacheKey);

        if($cached !== null) {
            return $this->addReturnData($cached);
        }

        // Fetch users
        $users = $this->ds->fetchAll([], ['created_at' => 'DESC'], $limit, null, $page);

        // Get total count
        $total = $this->ds->count();

        // Remove sensitive data
        foreach($users as &$user) {
            unset($user['password']);
            unset($user['api_key']);
        }

        $result = [
            'users' => $users,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'pages' => ceil($total / $limit)
            ]
        ];

        // Cache for 5 minutes
        $this->core->cache->set($cacheKey, $result, 300);

        $this->addReturnData($result);
    }

    /**
     * GET /users/profile/{id}
     * Get user profile by ID
     */
    public function ENDPOINT_profile()
    {
        if(!$this->checkMethod('GET')) return;

        $userId = $this->params[1] ?? null;
        if(!$userId) {
            return $this->setError('User ID required', 400);
        }

        // Users can only view their own profile unless admin
        if($userId !== $this->currentUser['id'] && $this->currentUser['role'] !== 'admin') {
            return $this->setError('Access denied', 403);
        }

        // Fetch user
        $user = $this->ds->fetchOne($userId);

        if(!$user) {
            return $this->setError('User not found', 404);
        }

        // Remove sensitive data
        unset($user['password']);
        unset($user['api_key']);

        $this->addReturnData($user);
    }

    /**
     * POST /users/create
     * Create new user
     */
    public function ENDPOINT_create()
    {
        if(!$this->checkMethod('POST')) return;

        // Validate input
        if(!$this->checkMandatoryFormParams(['name', 'email', 'password'])) return;

        // Validate email
        if(!filter_var($this->formParams['email'], FILTER_VALIDATE_EMAIL)) {
            return $this->setError('Invalid email format', 400);
        }

        // Validate password strength
        if(strlen($this->formParams['password']) < 8) {
            return $this->setError('Password must be at least 8 characters', 400);
        }

        // Check if email already exists
        $existing = $this->ds->fetchAll([['email', '=', $this->formParams['email']]], null, 1);
        if(!empty($existing)) {
            return $this->setError('Email already registered', 409);
        }

        // Hash password
        $hashedPassword = password_hash($this->formParams['password'], PASSWORD_ARGON2ID);

        // Create user
        $userData = [
            'name' => trim($this->formParams['name']),
            'email' => strtolower(trim($this->formParams['email'])),
            'password' => $hashedPassword,
            'role' => 'user', // Default role
            'status' => 'active',
            'created_at' => date('c'),
            'updated_at' => date('c')
        ];

        $userId = $this->ds->createEntity($userData);

        if($this->ds->error()) {
            $this->core->errors->add($this->ds->getError(), 'user_creation', 'error');
            return $this->setError('Failed to create user', 500);
        }

        // Invalidate cache
        $this->core->cache->delete('users_list_page_1_limit_20');

        // Log event
        $this->core->logs->add([
            'event' => 'user_created',
            'user_id' => $userId,
            'created_by' => $this->currentUser['id']
        ], 'audit');

        // Return created user (without password)
        $user = $this->ds->fetchOne($userId);
        unset($user['password']);

        $this->setReturnStatus(201);
        $this->addReturnData($user);
    }

    /**
     * PUT /users/update/{id}
     * Update user
     */
    public function ENDPOINT_update()
    {
        if(!$this->checkMethod('PUT')) return;

        $userId = $this->params[1] ?? null;
        if(!$userId) {
            return $this->setError('User ID required', 400);
        }

        // Users can only update their own profile unless admin
        if($userId !== $this->currentUser['id'] && $this->currentUser['role'] !== 'admin') {
            return $this->setError('Access denied', 403);
        }

        // Check if user exists
        $user = $this->ds->fetchOne($userId);
        if(!$user) {
            return $this->setError('User not found', 404);
        }

        // Prepare update data
        $updateData = ['updated_at' => date('c')];

        // Allowed fields for regular users
        $allowedFields = ['name', 'bio', 'phone'];

        // Admins can update role and status
        if($this->currentUser['role'] === 'admin') {
            $allowedFields[] = 'role';
            $allowedFields[] = 'status';
        }

        foreach($allowedFields as $field) {
            if(isset($this->formParams[$field])) {
                $updateData[$field] = $this->formParams[$field];
            }
        }

        // Special handling for email
        if(isset($this->formParams['email'])) {
            $email = strtolower(trim($this->formParams['email']));

            if(!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return $this->setError('Invalid email format', 400);
            }

            // Check if email is already taken
            $existing = $this->ds->fetchAll([['email', '=', $email]], null, 1);
            if(!empty($existing) && $existing[0]['KeyId'] !== $userId) {
                return $this->setError('Email already in use', 409);
            }

            $updateData['email'] = $email;
        }

        // Special handling for password
        if(isset($this->formParams['password'])) {
            if(strlen($this->formParams['password']) < 8) {
                return $this->setError('Password must be at least 8 characters', 400);
            }
            $updateData['password'] = password_hash($this->formParams['password'], PASSWORD_ARGON2ID);
        }

        // Update user
        $this->ds->updateEntity($userId, $updateData);

        if($this->ds->error()) {
            return $this->setError('Failed to update user', 500);
        }

        // Invalidate cache
        $this->core->cache->delete('users_list_page_1_limit_20');

        // Log event
        $this->core->logs->add([
            'event' => 'user_updated',
            'user_id' => $userId,
            'updated_by' => $this->currentUser['id']
        ], 'audit');

        // Return updated user
        $updatedUser = $this->ds->fetchOne($userId);
        unset($updatedUser['password']);

        $this->addReturnData($updatedUser);
    }

    /**
     * DELETE /users/delete/{id}
     * Delete user (soft delete)
     */
    public function ENDPOINT_delete()
    {
        if(!$this->checkMethod('DELETE')) return;

        // Only admins can delete users
        if($this->currentUser['role'] !== 'admin') {
            return $this->setError('Admin access required', 403);
        }

        $userId = $this->params[1] ?? null;
        if(!$userId) {
            return $this->setError('User ID required', 400);
        }

        // Check if user exists
        $user = $this->ds->fetchOne($userId);
        if(!$user) {
            return $this->setError('User not found', 404);
        }

        // Prevent self-deletion
        if($userId === $this->currentUser['id']) {
            return $this->setError('Cannot delete your own account', 400);
        }

        // Soft delete (set status to deleted)
        $this->ds->updateEntity($userId, [
            'status' => 'deleted',
            'deleted_at' => date('c'),
            'deleted_by' => $this->currentUser['id']
        ]);

        // Or hard delete:
        // $this->ds->delete($userId);

        if($this->ds->error()) {
            return $this->setError('Failed to delete user', 500);
        }

        // Invalidate cache
        $this->core->cache->delete('users_list_page_1_limit_20');

        // Log event
        $this->core->logs->add([
            'event' => 'user_deleted',
            'user_id' => $userId,
            'deleted_by' => $this->currentUser['id']
        ], 'audit');

        $this->setReturnStatus(204);
    }

    /**
     * POST /users/search
     * Search users
     */
    public function ENDPOINT_search()
    {
        if(!$this->checkMethod('POST')) return;

        // Only admins can search users
        if($this->currentUser['role'] !== 'admin') {
            return $this->setError('Admin access required', 403);
        }

        $query = $this->formParams['query'] ?? '';

        if(strlen($query) < 2) {
            return $this->setError('Search query must be at least 2 characters', 400);
        }

        // Search in name and email
        // Note: Datastore doesn't support LIKE queries, so we fetch all and filter
        $allUsers = $this->ds->fetchAll();

        $results = array_filter($allUsers, function($user) use ($query) {
            $searchIn = strtolower($user['name'] . ' ' . $user['email']);
            return strpos($searchIn, strtolower($query)) !== false;
        });

        // Remove sensitive data
        foreach($results as &$user) {
            unset($user['password']);
            unset($user['api_key']);
        }

        $this->addReturnData([
            'query' => $query,
            'count' => count($results),
            'results' => array_values($results)
        ]);
    }

    /**
     * Authenticate user using JWT token
     */
    private function authenticate(): bool
    {
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';

        if(!preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            return false;
        }

        $token = $matches[1];
        $payload = $this->validateJWT($token);

        if(!$payload) {
            return false;
        }

        $this->currentUser = $payload;
        return true;
    }

    /**
     * Validate JWT token
     */
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
}
```

---

## E-commerce Products API

Complete product catalog API with categories, search, and inventory management.

**File:** `app/api/products.php`

```php
<?php
/**
 * E-commerce Products API
 *
 * Endpoints:
 * GET    /products/list            - List products
 * GET    /products/view/{id}       - Get product details
 * POST   /products/create          - Create product (admin)
 * PUT    /products/update/{id}     - Update product (admin)
 * DELETE /products/delete/{id}     - Delete product (admin)
 * GET    /products/category/{slug} - Get products by category
 * POST   /products/search          - Search products
 * GET    /products/featured        - Get featured products
 */

use CloudFramework\Patterns\RESTful;

class API extends RESTful
{
    private $ds;
    private $buckets;
    private $currentUser;

    function main()
    {
        // Initialize services
        $this->ds = $this->core->loadClass('DataStore', ['Products']);
        $this->buckets = $this->core->loadClass('Buckets', ['gs://my-products-bucket']);

        // CORS
        $this->sendCorsHeaders();

        // Handle preflight
        if($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(204);
            exit;
        }

        // Authenticate for protected endpoints
        $protectedEndpoints = ['create', 'update', 'delete'];
        $endpoint = $this->params[0] ?? 'list';

        if(in_array($endpoint, $protectedEndpoints)) {
            if(!$this->authenticate()) {
                return $this->setError('Authentication required', 401);
            }

            if($this->currentUser['role'] !== 'admin') {
                return $this->setError('Admin access required', 403);
            }
        }

        // Route to endpoint
        if(!$this->useFunction('ENDPOINT_' . $endpoint)) {
            return $this->setErrorFromCodelib('not-found');
        }
    }

    /**
     * GET /products/list
     */
    public function ENDPOINT_list()
    {
        if(!$this->checkMethod('GET')) return;

        $page = (int)($this->formParams['page'] ?? 1);
        $limit = (int)($this->formParams['limit'] ?? 20);
        $category = $this->formParams['category'] ?? null;

        // Build WHERE clause
        $where = [['status', '=', 'active']];

        if($category) {
            $where[] = ['category', '=', $category];
        }

        // Cache key
        $cacheKey = "products_list_{$page}_{$limit}_{$category}";
        $cached = $this->core->cache->get($cacheKey);

        if($cached !== null) {
            return $this->addReturnData($cached);
        }

        // Fetch products
        $products = $this->ds->fetchAll($where, ['created_at' => 'DESC'], $limit, null, $page);

        // Get total count
        $total = $this->ds->count($where);

        $result = [
            'products' => $products,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'pages' => ceil($total / $limit)
            ]
        ];

        // Cache for 10 minutes
        $this->core->cache->set($cacheKey, $result, 600);

        $this->addReturnData($result);
    }

    /**
     * GET /products/view/{id}
     */
    public function ENDPOINT_view()
    {
        if(!$this->checkMethod('GET')) return;

        $productId = $this->params[1] ?? null;
        if(!$productId) {
            return $this->setError('Product ID required', 400);
        }

        // Try cache first
        $cacheKey = "product_{$productId}";
        $cached = $this->core->cache->get($cacheKey);

        if($cached !== null) {
            return $this->addReturnData($cached);
        }

        // Fetch product
        $product = $this->ds->fetchOne($productId);

        if(!$product || $product['status'] !== 'active') {
            return $this->setError('Product not found', 404);
        }

        // Increment view count
        $this->ds->updateEntity($productId, [
            'views' => ($product['views'] ?? 0) + 1
        ]);

        // Cache for 5 minutes
        $this->core->cache->set($cacheKey, $product, 300);

        $this->addReturnData($product);
    }

    /**
     * POST /products/create
     */
    public function ENDPOINT_create()
    {
        if(!$this->checkMethod('POST')) return;

        // Validate input
        if(!$this->checkMandatoryFormParams(['name', 'price', 'category'])) return;

        // Validate price
        $price = (float)$this->formParams['price'];
        if($price <= 0) {
            return $this->setError('Price must be greater than 0', 400);
        }

        // Validate stock
        $stock = (int)($this->formParams['stock'] ?? 0);
        if($stock < 0) {
            return $this->setError('Stock cannot be negative', 400);
        }

        // Handle image upload
        $imageUrl = null;
        if(!empty($_FILES['image'])) {
            $result = $this->buckets->manageUploadFiles('image', 'products/');

            if($this->buckets->error()) {
                return $this->setError('Failed to upload image', 400);
            }

            $imageUrl = $result['data'][0]['url'] ?? null;
        }

        // Generate slug
        $slug = $this->generateSlug($this->formParams['name']);

        // Create product
        $productData = [
            'name' => trim($this->formParams['name']),
            'slug' => $slug,
            'description' => $this->formParams['description'] ?? '',
            'price' => $price,
            'category' => $this->formParams['category'],
            'stock' => $stock,
            'image' => $imageUrl,
            'status' => 'active',
            'featured' => (bool)($this->formParams['featured'] ?? false),
            'views' => 0,
            'created_at' => date('c'),
            'updated_at' => date('c'),
            'created_by' => $this->currentUser['id']
        ];

        $productId = $this->ds->createEntity($productData);

        if($this->ds->error()) {
            return $this->setError('Failed to create product', 500);
        }

        // Invalidate cache
        $this->invalidateProductCache();

        $product = $this->ds->fetchOne($productId);

        $this->setReturnStatus(201);
        $this->addReturnData($product);
    }

    /**
     * PUT /products/update/{id}
     */
    public function ENDPOINT_update()
    {
        if(!$this->checkMethod('PUT')) return;

        $productId = $this->params[1] ?? null;
        if(!$productId) {
            return $this->setError('Product ID required', 400);
        }

        // Check if product exists
        $product = $this->ds->fetchOne($productId);
        if(!$product) {
            return $this->setError('Product not found', 404);
        }

        // Prepare update data
        $updateData = ['updated_at' => date('c')];

        $allowedFields = ['name', 'description', 'price', 'category', 'stock', 'status', 'featured'];

        foreach($allowedFields as $field) {
            if(isset($this->formParams[$field])) {
                $updateData[$field] = $this->formParams[$field];
            }
        }

        // Update slug if name changed
        if(isset($updateData['name'])) {
            $updateData['slug'] = $this->generateSlug($updateData['name']);
        }

        // Handle image upload
        if(!empty($_FILES['image'])) {
            // Delete old image
            if(!empty($product['image'])) {
                $oldImagePath = str_replace('https://storage.googleapis.com/my-products-bucket/', '', $product['image']);
                $this->buckets->delete($oldImagePath);
            }

            // Upload new image
            $result = $this->buckets->manageUploadFiles('image', 'products/');

            if(!$this->buckets->error()) {
                $updateData['image'] = $result['data'][0]['url'] ?? null;
            }
        }

        // Update product
        $this->ds->updateEntity($productId, $updateData);

        if($this->ds->error()) {
            return $this->setError('Failed to update product', 500);
        }

        // Invalidate cache
        $this->invalidateProductCache();
        $this->core->cache->delete("product_{$productId}");

        $updatedProduct = $this->ds->fetchOne($productId);
        $this->addReturnData($updatedProduct);
    }

    /**
     * DELETE /products/delete/{id}
     */
    public function ENDPOINT_delete()
    {
        if(!$this->checkMethod('DELETE')) return;

        $productId = $this->params[1] ?? null;
        if(!$productId) {
            return $this->setError('Product ID required', 400);
        }

        $product = $this->ds->fetchOne($productId);
        if(!$product) {
            return $this->setError('Product not found', 404);
        }

        // Soft delete
        $this->ds->updateEntity($productId, [
            'status' => 'deleted',
            'deleted_at' => date('c')
        ]);

        // Delete product image
        if(!empty($product['image'])) {
            $imagePath = str_replace('https://storage.googleapis.com/my-products-bucket/', '', $product['image']);
            $this->buckets->delete($imagePath);
        }

        // Invalidate cache
        $this->invalidateProductCache();
        $this->core->cache->delete("product_{$productId}");

        $this->setReturnStatus(204);
    }

    /**
     * POST /products/search
     */
    public function ENDPOINT_search()
    {
        if(!$this->checkMethod('POST')) return;

        $query = $this->formParams['query'] ?? '';

        if(strlen($query) < 2) {
            return $this->setError('Search query must be at least 2 characters', 400);
        }

        // Fetch all active products
        $allProducts = $this->ds->fetchAll([['status', '=', 'active']]);

        // Filter by search query
        $results = array_filter($allProducts, function($product) use ($query) {
            $searchIn = strtolower($product['name'] . ' ' . $product['description'] . ' ' . $product['category']);
            return strpos($searchIn, strtolower($query)) !== false;
        });

        $this->addReturnData([
            'query' => $query,
            'count' => count($results),
            'results' => array_values($results)
        ]);
    }

    /**
     * GET /products/featured
     */
    public function ENDPOINT_featured()
    {
        if(!$this->checkMethod('GET')) return;

        $cacheKey = 'products_featured';
        $cached = $this->core->cache->get($cacheKey);

        if($cached !== null) {
            return $this->addReturnData($cached);
        }

        $products = $this->ds->fetchAll([
            ['status', '=', 'active'],
            ['featured', '=', true]
        ], ['created_at' => 'DESC'], 10);

        // Cache for 1 hour
        $this->core->cache->set($cacheKey, $products, 3600);

        $this->addReturnData($products);
    }

    /**
     * GET /products/category/{slug}
     */
    public function ENDPOINT_category()
    {
        if(!$this->checkMethod('GET')) return;

        $categorySlug = $this->params[1] ?? null;
        if(!$categorySlug) {
            return $this->setError('Category slug required', 400);
        }

        $page = (int)($this->formParams['page'] ?? 1);
        $limit = (int)($this->formParams['limit'] ?? 20);

        $products = $this->ds->fetchAll([
            ['status', '=', 'active'],
            ['category', '=', $categorySlug]
        ], ['created_at' => 'DESC'], $limit, null, $page);

        $total = $this->ds->count([
            ['status', '=', 'active'],
            ['category', '=', $categorySlug]
        ]);

        $this->addReturnData([
            'category' => $categorySlug,
            'products' => $products,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'pages' => ceil($total / $limit)
            ]
        ]);
    }

    // Helper methods

    private function authenticate(): bool
    {
        $apiKey = $_SERVER['HTTP_X_API_KEY'] ?? '';
        $validKeys = $this->core->config->get('security.api_keys.valid_keys', []);

        if(in_array($apiKey, $validKeys)) {
            $this->currentUser = ['id' => 'admin', 'role' => 'admin'];
            return true;
        }

        return false;
    }

    private function generateSlug(string $text): string
    {
        $text = strtolower($text);
        $text = preg_replace('/[^a-z0-9-]/', '-', $text);
        $text = preg_replace('/-+/', '-', $text);
        return trim($text, '-');
    }

    private function invalidateProductCache()
    {
        $this->core->cache->delete('products_list_1_20_');
        $this->core->cache->delete('products_featured');
    }
}
```

---

## Blog Posts API

Content management API with posts, categories, tags, and comments.

**File:** `app/api/blog.php`

```php
<?php
/**
 * Blog Posts API
 *
 * Endpoints:
 * GET    /blog/posts              - List posts
 * GET    /blog/post/{slug}        - Get post by slug
 * POST   /blog/create             - Create post (admin)
 * PUT    /blog/update/{id}        - Update post (admin)
 * DELETE /blog/delete/{id}        - Delete post (admin)
 * POST   /blog/comment/{id}       - Add comment to post
 * GET    /blog/comments/{id}      - Get post comments
 * GET    /blog/tag/{tag}          - Get posts by tag
 */

use CloudFramework\Patterns\RESTful;

class API extends RESTful
{
    private $postsDS;
    private $commentsDS;
    private $currentUser;

    function main()
    {
        $this->postsDS = $this->core->loadClass('DataStore', ['BlogPosts']);
        $this->commentsDS = $this->core->loadClass('DataStore', ['BlogComments']);

        $this->sendCorsHeaders();

        if($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(204);
            exit;
        }

        $endpoint = $this->params[0] ?? 'posts';

        // Authenticate for admin endpoints
        $adminEndpoints = ['create', 'update', 'delete'];
        if(in_array($endpoint, $adminEndpoints)) {
            if(!$this->authenticate()) {
                return $this->setError('Authentication required', 401);
            }
        }

        if(!$this->useFunction('ENDPOINT_' . $endpoint)) {
            return $this->setErrorFromCodelib('not-found');
        }
    }

    /**
     * GET /blog/posts
     */
    public function ENDPOINT_posts()
    {
        if(!$this->checkMethod('GET')) return;

        $page = (int)($this->formParams['page'] ?? 1);
        $limit = (int)($this->formParams['limit'] ?? 10);

        $posts = $this->postsDS->fetchAll(
            [['status', '=', 'published']],
            ['published_at' => 'DESC'],
            $limit,
            null,
            $page
        );

        // Add comment count to each post
        foreach($posts as &$post) {
            $post['comment_count'] = $this->commentsDS->count([
                ['post_id', '=', $post['KeyId']],
                ['status', '=', 'approved']
            ]);
        }

        $total = $this->postsDS->count([['status', '=', 'published']]);

        $this->addReturnData([
            'posts' => $posts,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'pages' => ceil($total / $limit)
            ]
        ]);
    }

    /**
     * GET /blog/post/{slug}
     */
    public function ENDPOINT_post()
    {
        if(!$this->checkMethod('GET')) return;

        $slug = $this->params[1] ?? null;
        if(!$slug) {
            return $this->setError('Post slug required', 400);
        }

        // Try cache
        $cacheKey = "blog_post_{$slug}";
        $cached = $this->core->cache->get($cacheKey);

        if($cached !== null) {
            return $this->addReturnData($cached);
        }

        // Fetch post
        $posts = $this->postsDS->fetchAll([
            ['slug', '=', $slug],
            ['status', '=', 'published']
        ], null, 1);

        if(empty($posts)) {
            return $this->setError('Post not found', 404);
        }

        $post = $posts[0];

        // Increment views
        $this->postsDS->updateEntity($post['KeyId'], [
            'views' => ($post['views'] ?? 0) + 1
        ]);

        // Get approved comments
        $comments = $this->commentsDS->fetchAll([
            ['post_id', '=', $post['KeyId']],
            ['status', '=', 'approved']
        ], ['created_at' => 'DESC']);

        $post['comments'] = $comments;
        $post['comment_count'] = count($comments);

        // Cache for 10 minutes
        $this->core->cache->set($cacheKey, $post, 600);

        $this->addReturnData($post);
    }

    /**
     * POST /blog/create
     */
    public function ENDPOINT_create()
    {
        if(!$this->checkMethod('POST')) return;

        if(!$this->checkMandatoryFormParams(['title', 'content'])) return;

        $slug = $this->generateSlug($this->formParams['title']);

        // Check if slug exists
        $existing = $this->postsDS->fetchAll([['slug', '=', $slug]], null, 1);
        if(!empty($existing)) {
            $slug = $slug . '-' . time();
        }

        $postData = [
            'title' => trim($this->formParams['title']),
            'slug' => $slug,
            'content' => $this->formParams['content'],
            'excerpt' => $this->formParams['excerpt'] ?? substr(strip_tags($this->formParams['content']), 0, 200),
            'author_id' => $this->currentUser['id'],
            'author_name' => $this->currentUser['name'],
            'category' => $this->formParams['category'] ?? 'general',
            'tags' => $this->formParams['tags'] ?? [],
            'featured_image' => $this->formParams['featured_image'] ?? null,
            'status' => $this->formParams['status'] ?? 'draft',
            'views' => 0,
            'created_at' => date('c'),
            'updated_at' => date('c')
        ];

        if($postData['status'] === 'published') {
            $postData['published_at'] = date('c');
        }

        $postId = $this->postsDS->createEntity($postData);

        $post = $this->postsDS->fetchOne($postId);

        $this->setReturnStatus(201);
        $this->addReturnData($post);
    }

    /**
     * POST /blog/comment/{id}
     */
    public function ENDPOINT_comment()
    {
        if(!$this->checkMethod('POST')) return;

        $postId = $this->params[1] ?? null;
        if(!$postId) {
            return $this->setError('Post ID required', 400);
        }

        if(!$this->checkMandatoryFormParams(['name', 'email', 'comment'])) return;

        // Validate email
        if(!filter_var($this->formParams['email'], FILTER_VALIDATE_EMAIL)) {
            return $this->setError('Invalid email format', 400);
        }

        // Check if post exists
        $post = $this->postsDS->fetchOne($postId);
        if(!$post) {
            return $this->setError('Post not found', 404);
        }

        $commentData = [
            'post_id' => $postId,
            'name' => htmlspecialchars(trim($this->formParams['name']), ENT_QUOTES, 'UTF-8'),
            'email' => $this->formParams['email'],
            'comment' => htmlspecialchars(trim($this->formParams['comment']), ENT_QUOTES, 'UTF-8'),
            'status' => 'pending', // Require approval
            'created_at' => date('c'),
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ];

        $commentId = $this->commentsDS->createEntity($commentData);

        // Invalidate cache
        $this->core->cache->delete("blog_post_{$post['slug']}");

        $this->setReturnStatus(201);
        $this->addReturnData([
            'message' => 'Comment submitted and awaiting approval',
            'comment_id' => $commentId
        ]);
    }

    /**
     * GET /blog/tag/{tag}
     */
    public function ENDPOINT_tag()
    {
        if(!$this->checkMethod('GET')) return;

        $tag = $this->params[1] ?? null;
        if(!$tag) {
            return $this->setError('Tag required', 400);
        }

        // Fetch all published posts
        $allPosts = $this->postsDS->fetchAll([['status', '=', 'published']]);

        // Filter by tag
        $posts = array_filter($allPosts, function($post) use ($tag) {
            return in_array($tag, $post['tags'] ?? []);
        });

        $this->addReturnData([
            'tag' => $tag,
            'count' => count($posts),
            'posts' => array_values($posts)
        ]);
    }

    private function authenticate(): bool
    {
        $apiKey = $_SERVER['HTTP_X_API_KEY'] ?? '';
        $validKeys = $this->core->config->get('security.api_keys.valid_keys', []);

        if(in_array($apiKey, $validKeys)) {
            $this->currentUser = [
                'id' => 'admin',
                'name' => 'Administrator',
                'role' => 'admin'
            ];
            return true;
        }

        return false;
    }

    private function generateSlug(string $text): string
    {
        $text = strtolower($text);
        $text = preg_replace('/[^a-z0-9-]/', '-', $text);
        $text = preg_replace('/-+/', '-', $text);
        return trim($text, '-');
    }
}
```

---

## File Upload API

Secure file upload with validation and Cloud Storage integration.

**File:** `app/api/upload.php`

```php
<?php
/**
 * File Upload API
 *
 * Endpoints:
 * POST /upload/file      - Upload single file
 * POST /upload/multiple  - Upload multiple files
 * GET  /upload/list      - List uploaded files
 * DELETE /upload/delete/{filename} - Delete file
 */

use CloudFramework\Patterns\RESTful;

class API extends RESTful
{
    private $buckets;
    private $maxFileSize = 10485760; // 10MB
    private $allowedTypes = [
        'image/jpeg',
        'image/png',
        'image/gif',
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
    ];

    function main()
    {
        // Authenticate
        if(!$this->authenticate()) {
            return $this->setError('Authentication required', 401);
        }

        $this->buckets = $this->core->loadClass('Buckets', ['gs://my-uploads-bucket']);

        $endpoint = $this->params[0] ?? 'file';

        if(!$this->useFunction('ENDPOINT_' . $endpoint)) {
            return $this->setErrorFromCodelib('not-found');
        }
    }

    /**
     * POST /upload/file
     */
    public function ENDPOINT_file()
    {
        if(!$this->checkMethod('POST')) return;

        if(empty($_FILES['file'])) {
            return $this->setError('No file uploaded', 400);
        }

        $file = $_FILES['file'];

        // Validate file
        $validation = $this->validateFile($file);
        if($validation !== true) {
            return $this->setError($validation, 400);
        }

        // Generate safe filename
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $filename = bin2hex(random_bytes(16)) . '.' . $extension;
        $uploadPath = 'uploads/' . date('Y/m/') . $filename;

        // Upload to Cloud Storage
        $result = $this->buckets->uploadFile($file['tmp_name'], $uploadPath);

        if($this->buckets->error()) {
            return $this->setError('Upload failed', 500);
        }

        // Make file public (optional)
        $this->buckets->setFilePublic($uploadPath);

        // Get public URL
        $publicUrl = "https://storage.googleapis.com/my-uploads-bucket/{$uploadPath}";

        // Log upload
        $this->core->logs->add([
            'event' => 'file_uploaded',
            'filename' => $filename,
            'size' => $file['size'],
            'type' => $file['type'],
            'user_id' => $this->currentUser['id']
        ], 'uploads');

        $this->setReturnStatus(201);
        $this->addReturnData([
            'filename' => $filename,
            'url' => $publicUrl,
            'size' => $file['size'],
            'type' => $file['type']
        ]);
    }

    /**
     * POST /upload/multiple
     */
    public function ENDPOINT_multiple()
    {
        if(!$this->checkMethod('POST')) return;

        if(empty($_FILES['files'])) {
            return $this->setError('No files uploaded', 400);
        }

        $files = $_FILES['files'];
        $uploadedFiles = [];
        $errors = [];

        // Handle multiple files
        $fileCount = count($files['name']);

        for($i = 0; $i < $fileCount; $i++) {
            $file = [
                'name' => $files['name'][$i],
                'type' => $files['type'][$i],
                'tmp_name' => $files['tmp_name'][$i],
                'error' => $files['error'][$i],
                'size' => $files['size'][$i]
            ];

            // Validate
            $validation = $this->validateFile($file);
            if($validation !== true) {
                $errors[] = [
                    'filename' => $file['name'],
                    'error' => $validation
                ];
                continue;
            }

            // Generate filename and upload
            $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $filename = bin2hex(random_bytes(16)) . '.' . $extension;
            $uploadPath = 'uploads/' . date('Y/m/') . $filename;

            $result = $this->buckets->uploadFile($file['tmp_name'], $uploadPath);

            if(!$this->buckets->error()) {
                $this->buckets->setFilePublic($uploadPath);

                $uploadedFiles[] = [
                    'filename' => $filename,
                    'url' => "https://storage.googleapis.com/my-uploads-bucket/{$uploadPath}",
                    'size' => $file['size'],
                    'type' => $file['type']
                ];
            } else {
                $errors[] = [
                    'filename' => $file['name'],
                    'error' => 'Upload failed'
                ];
            }
        }

        $this->addReturnData([
            'uploaded' => $uploadedFiles,
            'errors' => $errors,
            'total' => $fileCount,
            'successful' => count($uploadedFiles),
            'failed' => count($errors)
        ]);
    }

    /**
     * GET /upload/list
     */
    public function ENDPOINT_list()
    {
        if(!$this->checkMethod('GET')) return;

        $prefix = 'uploads/';
        $files = $this->buckets->scan($prefix);

        if($this->buckets->error()) {
            return $this->setError('Failed to list files', 500);
        }

        $fileList = [];
        foreach($files as $file) {
            $fileList[] = [
                'filename' => basename($file),
                'path' => $file,
                'url' => "https://storage.googleapis.com/my-uploads-bucket/{$file}"
            ];
        }

        $this->addReturnData([
            'count' => count($fileList),
            'files' => $fileList
        ]);
    }

    /**
     * DELETE /upload/delete/{filename}
     */
    public function ENDPOINT_delete()
    {
        if(!$this->checkMethod('DELETE')) return;

        $filename = $this->params[1] ?? null;
        if(!$filename) {
            return $this->setError('Filename required', 400);
        }

        // Find file (search in uploads directory)
        $files = $this->buckets->scan('uploads/');
        $filePath = null;

        foreach($files as $file) {
            if(basename($file) === $filename) {
                $filePath = $file;
                break;
            }
        }

        if(!$filePath) {
            return $this->setError('File not found', 404);
        }

        // Delete file
        $result = $this->buckets->delete($filePath);

        if($this->buckets->error()) {
            return $this->setError('Failed to delete file', 500);
        }

        // Log deletion
        $this->core->logs->add([
            'event' => 'file_deleted',
            'filename' => $filename,
            'user_id' => $this->currentUser['id']
        ], 'uploads');

        $this->setReturnStatus(204);
    }

    private function validateFile(array $file): string|true
    {
        // Check for upload errors
        if($file['error'] !== UPLOAD_ERR_OK) {
            return 'Upload error occurred';
        }

        // Check file size
        if($file['size'] > $this->maxFileSize) {
            return 'File too large (max 10MB)';
        }

        // Check file type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if(!in_array($mimeType, $this->allowedTypes)) {
            return 'File type not allowed';
        }

        // Check extension
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx'];
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        if(!in_array($extension, $allowedExtensions)) {
            return 'File extension not allowed';
        }

        return true;
    }

    private function authenticate(): bool
    {
        $apiKey = $_SERVER['HTTP_X_API_KEY'] ?? '';
        $validKeys = $this->core->config->get('security.api_keys.valid_keys', []);

        if(in_array($apiKey, $validKeys)) {
            $this->currentUser = ['id' => 'user123', 'role' => 'user'];
            return true;
        }

        return false;
    }
}
```

---

## Authentication API

Complete authentication system with JWT tokens, refresh tokens, and session management.

**File:** `app/api/auth.php`

```php
<?php
/**
 * Authentication API
 *
 * Endpoints:
 * POST /auth/register        - Register new user
 * POST /auth/login           - Login user
 * POST /auth/logout          - Logout user
 * POST /auth/refresh         - Refresh access token
 * POST /auth/forgot-password - Request password reset
 * POST /auth/reset-password  - Reset password
 * GET  /auth/verify-email    - Verify email address
 * GET  /auth/me              - Get current user info
 */

use CloudFramework\Patterns\RESTful;

class API extends RESTful
{
    private $ds;
    private $jwtSecret;
    private $accessTokenExpiry = 3600; // 1 hour
    private $refreshTokenExpiry = 2592000; // 30 days

    function main()
    {
        $this->ds = $this->core->loadClass('DataStore', ['Users']);
        $this->jwtSecret = $this->core->config->get('security.jwt.secret');

        $this->sendCorsHeaders();

        if($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(204);
            exit;
        }

        $endpoint = $this->params[0] ?? 'login';

        if(!$this->useFunction('ENDPOINT_' . $endpoint)) {
            return $this->setErrorFromCodelib('not-found');
        }
    }

    /**
     * POST /auth/register
     */
    public function ENDPOINT_register()
    {
        if(!$this->checkMethod('POST')) return;

        // Validate input
        if(!$this->checkMandatoryFormParams(['name', 'email', 'password'])) return;

        // Validate email
        $email = strtolower(trim($this->formParams['email']));
        if(!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->setError('Invalid email format', 400);
        }

        // Validate password strength
        $password = $this->formParams['password'];
        if(strlen($password) < 8) {
            return $this->setError('Password must be at least 8 characters', 400);
        }

        if(!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)/', $password)) {
            return $this->setError('Password must contain uppercase, lowercase, and numbers', 400);
        }

        // Check if user exists
        $existing = $this->ds->fetchAll([['email', '=', $email]], null, 1);
        if(!empty($existing)) {
            return $this->setError('Email already registered', 409);
        }

        // Hash password
        $hashedPassword = password_hash($password, PASSWORD_ARGON2ID);

        // Generate email verification token
        $verificationToken = bin2hex(random_bytes(32));

        // Create user
        $userData = [
            'name' => trim($this->formParams['name']),
            'email' => $email,
            'password' => $hashedPassword,
            'role' => 'user',
            'status' => 'pending', // Pending email verification
            'email_verified' => false,
            'verification_token' => $verificationToken,
            'created_at' => date('c'),
            'updated_at' => date('c')
        ];

        $userId = $this->ds->createEntity($userData);

        if($this->ds->error()) {
            return $this->setError('Failed to create user', 500);
        }

        // Send verification email
        $this->sendVerificationEmail($email, $verificationToken);

        // Log event
        $this->core->logs->add([
            'event' => 'user_registered',
            'user_id' => $userId,
            'email' => $email
        ], 'auth');

        $this->setReturnStatus(201);
        $this->addReturnData([
            'message' => 'Registration successful. Please check your email to verify your account.',
            'user_id' => $userId
        ]);
    }

    /**
     * POST /auth/login
     */
    public function ENDPOINT_login()
    {
        if(!$this->checkMethod('POST')) return;

        if(!$this->checkMandatoryFormParams(['email', 'password'])) return;

        $email = strtolower(trim($this->formParams['email']));

        // Find user
        $users = $this->ds->fetchAll([['email', '=', $email]], null, 1);

        if(empty($users)) {
            // Prevent user enumeration
            sleep(1);
            return $this->setError('Invalid credentials', 401);
        }

        $user = $users[0];

        // Verify password
        if(!password_verify($this->formParams['password'], $user['password'])) {
            // Log failed attempt
            $this->core->logs->add([
                'event' => 'login_failed',
                'email' => $email,
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
            ], 'auth');

            sleep(1);
            return $this->setError('Invalid credentials', 401);
        }

        // Check if email is verified
        if(!$user['email_verified']) {
            return $this->setError('Please verify your email address', 403);
        }

        // Check account status
        if($user['status'] !== 'active') {
            return $this->setError('Account is not active', 403);
        }

        // Generate tokens
        $accessToken = $this->generateAccessToken($user);
        $refreshToken = $this->generateRefreshToken($user);

        // Save refresh token
        $this->ds->updateEntity($user['KeyId'], [
            'refresh_token' => password_hash($refreshToken, PASSWORD_BCRYPT),
            'last_login' => date('c'),
            'last_login_ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ]);

        // Log successful login
        $this->core->logs->add([
            'event' => 'login_success',
            'user_id' => $user['KeyId'],
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ], 'auth');

        $this->addReturnData([
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
            'token_type' => 'Bearer',
            'expires_in' => $this->accessTokenExpiry,
            'user' => [
                'id' => $user['KeyId'],
                'name' => $user['name'],
                'email' => $user['email'],
                'role' => $user['role']
            ]
        ]);
    }

    /**
     * POST /auth/logout
     */
    public function ENDPOINT_logout()
    {
        if(!$this->checkMethod('POST')) return;

        $user = $this->authenticateRequest();
        if(!$user) {
            return $this->setError('Authentication required', 401);
        }

        // Invalidate refresh token
        $this->ds->updateEntity($user['KeyId'], [
            'refresh_token' => null,
            'updated_at' => date('c')
        ]);

        // Log logout
        $this->core->logs->add([
            'event' => 'logout',
            'user_id' => $user['KeyId']
        ], 'auth');

        $this->addReturnData(['message' => 'Logged out successfully']);
    }

    /**
     * POST /auth/refresh
     */
    public function ENDPOINT_refresh()
    {
        if(!$this->checkMethod('POST')) return;

        if(!$this->checkMandatoryFormParams(['refresh_token'])) return;

        $refreshToken = $this->formParams['refresh_token'];

        // Decode refresh token
        $payload = $this->verifyToken($refreshToken);
        if(!$payload) {
            return $this->setError('Invalid refresh token', 401);
        }

        // Get user
        $user = $this->ds->fetchOne($payload['user_id']);
        if(!$user) {
            return $this->setError('User not found', 404);
        }

        // Verify stored refresh token
        if(!isset($user['refresh_token']) || !password_verify($refreshToken, $user['refresh_token'])) {
            return $this->setError('Invalid refresh token', 401);
        }

        // Generate new tokens
        $newAccessToken = $this->generateAccessToken($user);
        $newRefreshToken = $this->generateRefreshToken($user);

        // Update refresh token
        $this->ds->updateEntity($user['KeyId'], [
            'refresh_token' => password_hash($newRefreshToken, PASSWORD_BCRYPT),
            'updated_at' => date('c')
        ]);

        $this->addReturnData([
            'access_token' => $newAccessToken,
            'refresh_token' => $newRefreshToken,
            'token_type' => 'Bearer',
            'expires_in' => $this->accessTokenExpiry
        ]);
    }

    /**
     * POST /auth/forgot-password
     */
    public function ENDPOINT_forgot_password()
    {
        if(!$this->checkMethod('POST')) return;

        if(!$this->checkMandatoryFormParams(['email'])) return;

        $email = strtolower(trim($this->formParams['email']));

        // Find user (don't reveal if user exists)
        $users = $this->ds->fetchAll([['email', '=', $email]], null, 1);

        if(!empty($users)) {
            $user = $users[0];

            // Generate reset token
            $resetToken = bin2hex(random_bytes(32));
            $resetExpiry = date('c', strtotime('+1 hour'));

            // Save token
            $this->ds->updateEntity($user['KeyId'], [
                'password_reset_token' => $resetToken,
                'password_reset_expiry' => $resetExpiry
            ]);

            // Send reset email
            $this->sendPasswordResetEmail($email, $resetToken);

            // Log event
            $this->core->logs->add([
                'event' => 'password_reset_requested',
                'user_id' => $user['KeyId']
            ], 'auth');
        }

        // Always return success (prevent user enumeration)
        $this->addReturnData([
            'message' => 'If the email exists, a password reset link has been sent'
        ]);
    }

    /**
     * POST /auth/reset-password
     */
    public function ENDPOINT_reset_password()
    {
        if(!$this->checkMethod('POST')) return;

        if(!$this->checkMandatoryFormParams(['token', 'password'])) return;

        $token = $this->formParams['token'];
        $newPassword = $this->formParams['password'];

        // Validate password
        if(strlen($newPassword) < 8) {
            return $this->setError('Password must be at least 8 characters', 400);
        }

        // Find user with token
        $users = $this->ds->fetchAll([['password_reset_token', '=', $token]], null, 1);

        if(empty($users)) {
            return $this->setError('Invalid or expired reset token', 400);
        }

        $user = $users[0];

        // Check expiry
        if(strtotime($user['password_reset_expiry']) < time()) {
            return $this->setError('Reset token has expired', 400);
        }

        // Update password
        $this->ds->updateEntity($user['KeyId'], [
            'password' => password_hash($newPassword, PASSWORD_ARGON2ID),
            'password_reset_token' => null,
            'password_reset_expiry' => null,
            'refresh_token' => null, // Invalidate all sessions
            'updated_at' => date('c')
        ]);

        // Log event
        $this->core->logs->add([
            'event' => 'password_reset_completed',
            'user_id' => $user['KeyId']
        ], 'auth');

        $this->addReturnData(['message' => 'Password reset successfully']);
    }

    /**
     * GET /auth/verify-email
     */
    public function ENDPOINT_verify_email()
    {
        if(!$this->checkMethod('GET')) return;

        $token = $this->formParams['token'] ?? null;

        if(!$token) {
            return $this->setError('Verification token required', 400);
        }

        // Find user with token
        $users = $this->ds->fetchAll([['verification_token', '=', $token]], null, 1);

        if(empty($users)) {
            return $this->setError('Invalid verification token', 400);
        }

        $user = $users[0];

        // Update user
        $this->ds->updateEntity($user['KeyId'], [
            'email_verified' => true,
            'status' => 'active',
            'verification_token' => null,
            'updated_at' => date('c')
        ]);

        // Log event
        $this->core->logs->add([
            'event' => 'email_verified',
            'user_id' => $user['KeyId']
        ], 'auth');

        $this->addReturnData(['message' => 'Email verified successfully']);
    }

    /**
     * GET /auth/me
     */
    public function ENDPOINT_me()
    {
        if(!$this->checkMethod('GET')) return;

        $user = $this->authenticateRequest();
        if(!$user) {
            return $this->setError('Authentication required', 401);
        }

        // Return user info (without sensitive data)
        $this->addReturnData([
            'id' => $user['KeyId'],
            'name' => $user['name'],
            'email' => $user['email'],
            'role' => $user['role'],
            'status' => $user['status'],
            'email_verified' => $user['email_verified'],
            'created_at' => $user['created_at']
        ]);
    }

    // Helper methods

    private function generateAccessToken(array $user): string
    {
        $payload = [
            'user_id' => $user['KeyId'],
            'email' => $user['email'],
            'role' => $user['role'],
            'type' => 'access',
            'iat' => time(),
            'exp' => time() + $this->accessTokenExpiry
        ];

        return $this->createToken($payload);
    }

    private function generateRefreshToken(array $user): string
    {
        $payload = [
            'user_id' => $user['KeyId'],
            'type' => 'refresh',
            'iat' => time(),
            'exp' => time() + $this->refreshTokenExpiry
        ];

        return $this->createToken($payload);
    }

    private function createToken(array $payload): string
    {
        $header = json_encode(['typ' => 'JWT', 'alg' => 'HS256']);
        $payload = json_encode($payload);

        $base64UrlHeader = $this->base64UrlEncode($header);
        $base64UrlPayload = $this->base64UrlEncode($payload);

        $signature = hash_hmac('sha256', $base64UrlHeader . "." . $base64UrlPayload, $this->jwtSecret, true);
        $base64UrlSignature = $this->base64UrlEncode($signature);

        return $base64UrlHeader . "." . $base64UrlPayload . "." . $base64UrlSignature;
    }

    private function verifyToken(string $token): array|false
    {
        $parts = explode('.', $token);
        if(count($parts) !== 3) {
            return false;
        }

        [$header, $payload, $signature] = $parts;

        // Verify signature
        $expectedSignature = $this->base64UrlEncode(
            hash_hmac('sha256', $header . "." . $payload, $this->jwtSecret, true)
        );

        if($signature !== $expectedSignature) {
            return false;
        }

        // Decode payload
        $payloadData = json_decode($this->base64UrlDecode($payload), true);

        // Check expiration
        if(isset($payloadData['exp']) && $payloadData['exp'] < time()) {
            return false;
        }

        return $payloadData;
    }

    private function authenticateRequest(): array|false
    {
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';

        if(!preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            return false;
        }

        $token = $matches[1];
        $payload = $this->verifyToken($token);

        if(!$payload || $payload['type'] !== 'access') {
            return false;
        }

        // Get user
        $user = $this->ds->fetchOne($payload['user_id']);

        return $user ?: false;
    }

    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $data): string
    {
        return base64_decode(strtr($data, '-_', '+/'));
    }

    private function sendVerificationEmail(string $email, string $token)
    {
        $verificationUrl = $this->core->config->get('app.url') . "/auth/verify-email?token={$token}";

        $emailService = $this->core->loadClass('Email');
        $emailService->setTo($email);
        $emailService->setSubject('Verify Your Email Address');
        $emailService->setBodyHTML("
            <h1>Welcome!</h1>
            <p>Please click the link below to verify your email address:</p>
            <p><a href='{$verificationUrl}'>{$verificationUrl}</a></p>
        ");
        $emailService->send();
    }

    private function sendPasswordResetEmail(string $email, string $token)
    {
        $resetUrl = $this->core->config->get('app.url') . "/auth/reset-password?token={$token}";

        $emailService = $this->core->loadClass('Email');
        $emailService->setTo($email);
        $emailService->setSubject('Password Reset Request');
        $emailService->setBodyHTML("
            <h1>Password Reset</h1>
            <p>Click the link below to reset your password:</p>
            <p><a href='{$resetUrl}'>{$resetUrl}</a></p>
            <p>This link will expire in 1 hour.</p>
        ");
        $emailService->send();
    }
}
```

---

## Webhook Handler API

Process webhooks from external services like Stripe, GitHub, or custom integrations.

**File:** `app/api/webhooks.php`

```php
<?php
/**
 * Webhook Handler API
 *
 * Endpoints:
 * POST /webhooks/stripe       - Handle Stripe webhooks
 * POST /webhooks/github       - Handle GitHub webhooks
 * POST /webhooks/sendgrid     - Handle SendGrid webhooks
 * POST /webhooks/custom       - Handle custom webhooks
 * GET  /webhooks/logs         - View webhook logs (admin)
 */

use CloudFramework\Patterns\RESTful;

class API extends RESTful
{
    private $ds;
    private $webhookSecret;

    function main()
    {
        $this->ds = $this->core->loadClass('DataStore', ['WebhookLogs']);

        $endpoint = $this->params[0] ?? 'stripe';

        if(!$this->useFunction('ENDPOINT_' . $endpoint)) {
            return $this->setErrorFromCodelib('not-found');
        }
    }

    /**
     * POST /webhooks/stripe
     */
    public function ENDPOINT_stripe()
    {
        if(!$this->checkMethod('POST')) return;

        // Get raw body
        $payload = file_get_contents('php://input');
        $signature = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';

        // Verify signature
        $secret = $this->core->config->get('webhooks.stripe.secret');
        if(!$this->verifyStripeSignature($payload, $signature, $secret)) {
            $this->logWebhook('stripe', 'failed', 'Invalid signature');
            return $this->setError('Invalid signature', 401);
        }

        // Parse event
        $event = json_decode($payload, true);

        if(!$event || !isset($event['type'])) {
            $this->logWebhook('stripe', 'failed', 'Invalid payload');
            return $this->setError('Invalid payload', 400);
        }

        // Log webhook
        $this->logWebhook('stripe', 'received', $event['type'], $event);

        // Handle event types
        $handled = false;

        switch($event['type']) {
            case 'payment_intent.succeeded':
                $handled = $this->handlePaymentSuccess($event['data']['object']);
                break;

            case 'payment_intent.payment_failed':
                $handled = $this->handlePaymentFailed($event['data']['object']);
                break;

            case 'customer.subscription.created':
                $handled = $this->handleSubscriptionCreated($event['data']['object']);
                break;

            case 'customer.subscription.updated':
                $handled = $this->handleSubscriptionUpdated($event['data']['object']);
                break;

            case 'customer.subscription.deleted':
                $handled = $this->handleSubscriptionCancelled($event['data']['object']);
                break;

            case 'invoice.payment_succeeded':
                $handled = $this->handleInvoicePaid($event['data']['object']);
                break;

            default:
                $this->core->logs->add([
                    'event' => 'webhook_unhandled',
                    'service' => 'stripe',
                    'type' => $event['type']
                ], 'webhooks');
        }

        if($handled) {
            $this->logWebhook('stripe', 'processed', $event['type']);
        }

        // Always return 200 to acknowledge receipt
        $this->addReturnData(['received' => true]);
    }

    /**
     * POST /webhooks/github
     */
    public function ENDPOINT_github()
    {
        if(!$this->checkMethod('POST')) return;

        $payload = file_get_contents('php://input');
        $signature = $_SERVER['HTTP_X_HUB_SIGNATURE_256'] ?? '';

        // Verify signature
        $secret = $this->core->config->get('webhooks.github.secret');
        if(!$this->verifyGitHubSignature($payload, $signature, $secret)) {
            $this->logWebhook('github', 'failed', 'Invalid signature');
            return $this->setError('Invalid signature', 401);
        }

        $event = json_decode($payload, true);
        $eventType = $_SERVER['HTTP_X_GITHUB_EVENT'] ?? 'unknown';

        $this->logWebhook('github', 'received', $eventType, $event);

        // Handle different event types
        switch($eventType) {
            case 'push':
                $this->handleGitHubPush($event);
                break;

            case 'pull_request':
                $this->handleGitHubPullRequest($event);
                break;

            case 'issues':
                $this->handleGitHubIssue($event);
                break;

            case 'release':
                $this->handleGitHubRelease($event);
                break;

            default:
                $this->core->logs->add([
                    'event' => 'webhook_unhandled',
                    'service' => 'github',
                    'type' => $eventType
                ], 'webhooks');
        }

        $this->logWebhook('github', 'processed', $eventType);
        $this->addReturnData(['received' => true]);
    }

    /**
     * POST /webhooks/sendgrid
     */
    public function ENDPOINT_sendgrid()
    {
        if(!$this->checkMethod('POST')) return;

        $payload = file_get_contents('php://input');
        $events = json_decode($payload, true);

        if(!is_array($events)) {
            return $this->setError('Invalid payload', 400);
        }

        $this->logWebhook('sendgrid', 'received', 'email_events', ['count' => count($events)]);

        // Process each event
        foreach($events as $event) {
            $eventType = $event['event'] ?? 'unknown';
            $email = $event['email'] ?? 'unknown';

            switch($eventType) {
                case 'delivered':
                    $this->handleEmailDelivered($event);
                    break;

                case 'open':
                    $this->handleEmailOpened($event);
                    break;

                case 'click':
                    $this->handleEmailClicked($event);
                    break;

                case 'bounce':
                    $this->handleEmailBounced($event);
                    break;

                case 'dropped':
                    $this->handleEmailDropped($event);
                    break;

                case 'spam':
                    $this->handleEmailSpam($event);
                    break;

                case 'unsubscribe':
                    $this->handleEmailUnsubscribe($event);
                    break;
            }

            $this->core->logs->add([
                'event' => 'email_event',
                'type' => $eventType,
                'email' => $email,
                'timestamp' => $event['timestamp'] ?? time()
            ], 'email_tracking');
        }

        $this->logWebhook('sendgrid', 'processed', 'email_events');
        $this->addReturnData(['received' => true]);
    }

    /**
     * POST /webhooks/custom
     */
    public function ENDPOINT_custom()
    {
        if(!$this->checkMethod('POST')) return;

        // Verify API key
        $apiKey = $_SERVER['HTTP_X_API_KEY'] ?? '';
        $validKeys = $this->core->config->get('webhooks.custom.api_keys', []);

        if(!in_array($apiKey, $validKeys)) {
            $this->logWebhook('custom', 'failed', 'Invalid API key');
            return $this->setError('Invalid API key', 401);
        }

        $payload = file_get_contents('php://input');
        $data = json_decode($payload, true);

        if(!$data) {
            return $this->setError('Invalid JSON', 400);
        }

        $eventType = $data['event'] ?? 'unknown';

        $this->logWebhook('custom', 'received', $eventType, $data);

        // Store webhook data
        $webhookDS = $this->core->loadClass('DataStore', ['CustomWebhooks']);
        $webhookDS->createEntity([
            'event_type' => $eventType,
            'data' => $data,
            'source_ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'received_at' => date('c')
        ]);

        // Trigger custom processing
        if(isset($data['action'])) {
            $this->processCustomAction($data['action'], $data);
        }

        $this->logWebhook('custom', 'processed', $eventType);
        $this->addReturnData(['received' => true, 'event' => $eventType]);
    }

    /**
     * GET /webhooks/logs
     */
    public function ENDPOINT_logs()
    {
        if(!$this->checkMethod('GET')) return;

        // Admin authentication required
        if(!$this->authenticateAdmin()) {
            return $this->setError('Admin access required', 403);
        }

        $service = $this->formParams['service'] ?? null;
        $limit = (int)($this->formParams['limit'] ?? 50);

        $where = [];
        if($service) {
            $where[] = ['service', '=', $service];
        }

        $logs = $this->ds->fetchAll($where, ['created_at' => 'DESC'], $limit);

        $this->addReturnData([
            'count' => count($logs),
            'logs' => $logs
        ]);
    }

    // Stripe handlers

    private function handlePaymentSuccess(array $paymentIntent): bool
    {
        $ordersDS = $this->core->loadClass('DataStore', ['Orders']);

        // Find order by payment intent ID
        $orders = $ordersDS->fetchAll([['payment_intent_id', '=', $paymentIntent['id']]], null, 1);

        if(!empty($orders)) {
            $order = $orders[0];

            // Update order status
            $ordersDS->updateEntity($order['KeyId'], [
                'status' => 'paid',
                'paid_at' => date('c'),
                'payment_method' => $paymentIntent['payment_method'] ?? null
            ]);

            // Send confirmation email
            $this->sendOrderConfirmation($order);

            return true;
        }

        return false;
    }

    private function handlePaymentFailed(array $paymentIntent): bool
    {
        $ordersDS = $this->core->loadClass('DataStore', ['Orders']);

        $orders = $ordersDS->fetchAll([['payment_intent_id', '=', $paymentIntent['id']]], null, 1);

        if(!empty($orders)) {
            $order = $orders[0];

            $ordersDS->updateEntity($order['KeyId'], [
                'status' => 'payment_failed',
                'failure_reason' => $paymentIntent['last_payment_error']['message'] ?? 'Unknown'
            ]);

            // Notify customer
            $this->sendPaymentFailedEmail($order);

            return true;
        }

        return false;
    }

    private function handleSubscriptionCreated(array $subscription): bool
    {
        $subsDS = $this->core->loadClass('DataStore', ['Subscriptions']);

        $subsDS->createEntity([
            'stripe_subscription_id' => $subscription['id'],
            'customer_id' => $subscription['customer'],
            'status' => $subscription['status'],
            'plan_id' => $subscription['items']['data'][0]['price']['id'] ?? null,
            'current_period_start' => date('c', $subscription['current_period_start']),
            'current_period_end' => date('c', $subscription['current_period_end']),
            'created_at' => date('c')
        ]);

        return true;
    }

    private function handleSubscriptionUpdated(array $subscription): bool
    {
        $subsDS = $this->core->loadClass('DataStore', ['Subscriptions']);

        $subs = $subsDS->fetchAll([['stripe_subscription_id', '=', $subscription['id']]], null, 1);

        if(!empty($subs)) {
            $subsDS->updateEntity($subs[0]['KeyId'], [
                'status' => $subscription['status'],
                'current_period_start' => date('c', $subscription['current_period_start']),
                'current_period_end' => date('c', $subscription['current_period_end']),
                'updated_at' => date('c')
            ]);

            return true;
        }

        return false;
    }

    private function handleSubscriptionCancelled(array $subscription): bool
    {
        $subsDS = $this->core->loadClass('DataStore', ['Subscriptions']);

        $subs = $subsDS->fetchAll([['stripe_subscription_id', '=', $subscription['id']]], null, 1);

        if(!empty($subs)) {
            $subsDS->updateEntity($subs[0]['KeyId'], [
                'status' => 'cancelled',
                'cancelled_at' => date('c')
            ]);

            return true;
        }

        return false;
    }

    private function handleInvoicePaid(array $invoice): bool
    {
        // Log invoice payment
        $this->core->logs->add([
            'event' => 'invoice_paid',
            'invoice_id' => $invoice['id'],
            'amount' => $invoice['amount_paid'],
            'customer' => $invoice['customer']
        ], 'payments');

        return true;
    }

    // GitHub handlers

    private function handleGitHubPush(array $event)
    {
        $branch = str_replace('refs/heads/', '', $event['ref']);
        $commits = count($event['commits']);

        $this->core->logs->add([
            'event' => 'github_push',
            'repository' => $event['repository']['full_name'],
            'branch' => $branch,
            'commits' => $commits,
            'pusher' => $event['pusher']['name']
        ], 'github');

        // Trigger deployment if push to main
        if($branch === 'main') {
            $this->triggerDeployment($event['repository']['name']);
        }
    }

    private function handleGitHubPullRequest(array $event)
    {
        $action = $event['action'];
        $pr = $event['pull_request'];

        $this->core->logs->add([
            'event' => 'github_pull_request',
            'action' => $action,
            'pr_number' => $pr['number'],
            'title' => $pr['title'],
            'author' => $pr['user']['login']
        ], 'github');
    }

    private function handleGitHubIssue(array $event)
    {
        $action = $event['action'];
        $issue = $event['issue'];

        $this->core->logs->add([
            'event' => 'github_issue',
            'action' => $action,
            'issue_number' => $issue['number'],
            'title' => $issue['title']
        ], 'github');
    }

    private function handleGitHubRelease(array $event)
    {
        $release = $event['release'];

        $this->core->logs->add([
            'event' => 'github_release',
            'tag' => $release['tag_name'],
            'name' => $release['name'],
            'published' => $release['published_at']
        ], 'github');
    }

    // SendGrid handlers

    private function handleEmailDelivered(array $event)
    {
        $this->updateEmailStatus($event['email'], 'delivered', $event);
    }

    private function handleEmailOpened(array $event)
    {
        $this->updateEmailStatus($event['email'], 'opened', $event);
    }

    private function handleEmailClicked(array $event)
    {
        $this->updateEmailStatus($event['email'], 'clicked', $event);
    }

    private function handleEmailBounced(array $event)
    {
        $this->updateEmailStatus($event['email'], 'bounced', $event);
        // Mark email as invalid
        $this->markEmailInvalid($event['email'], 'bounced');
    }

    private function handleEmailDropped(array $event)
    {
        $this->updateEmailStatus($event['email'], 'dropped', $event);
    }

    private function handleEmailSpam(array $event)
    {
        $this->updateEmailStatus($event['email'], 'spam', $event);
        $this->markEmailInvalid($event['email'], 'spam');
    }

    private function handleEmailUnsubscribe(array $event)
    {
        $this->updateEmailStatus($event['email'], 'unsubscribed', $event);
        // Add to unsubscribe list
        $this->addToUnsubscribeList($event['email']);
    }

    // Helper methods

    private function verifyStripeSignature(string $payload, string $signature, string $secret): bool
    {
        $elements = explode(',', $signature);
        $timestamp = null;
        $sig = null;

        foreach($elements as $element) {
            [$key, $value] = explode('=', $element, 2);
            if($key === 't') $timestamp = $value;
            if($key === 'v1') $sig = $value;
        }

        if(!$timestamp || !$sig) {
            return false;
        }

        // Check timestamp tolerance (5 minutes)
        if(abs(time() - $timestamp) > 300) {
            return false;
        }

        $signedPayload = $timestamp . '.' . $payload;
        $expectedSignature = hash_hmac('sha256', $signedPayload, $secret);

        return hash_equals($expectedSignature, $sig);
    }

    private function verifyGitHubSignature(string $payload, string $signature, string $secret): bool
    {
        if(!preg_match('/^sha256=(.+)$/', $signature, $matches)) {
            return false;
        }

        $expected = hash_hmac('sha256', $payload, $secret);
        return hash_equals($expected, $matches[1]);
    }

    private function logWebhook(string $service, string $status, string $eventType, ?array $data = null)
    {
        $this->ds->createEntity([
            'service' => $service,
            'status' => $status,
            'event_type' => $eventType,
            'data' => $data,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'created_at' => date('c')
        ]);
    }

    private function authenticateAdmin(): bool
    {
        $apiKey = $_SERVER['HTTP_X_API_KEY'] ?? '';
        $adminKeys = $this->core->config->get('security.admin_keys', []);

        return in_array($apiKey, $adminKeys);
    }

    private function sendOrderConfirmation(array $order)
    {
        // Implementation
    }

    private function sendPaymentFailedEmail(array $order)
    {
        // Implementation
    }

    private function triggerDeployment(string $repository)
    {
        // Implementation
    }

    private function updateEmailStatus(string $email, string $status, array $event)
    {
        // Implementation
    }

    private function markEmailInvalid(string $email, string $reason)
    {
        // Implementation
    }

    private function addToUnsubscribeList(string $email)
    {
        // Implementation
    }

    private function processCustomAction(string $action, array $data)
    {
        // Implementation
    }
}
```

---

## Analytics API

Real-time analytics data retrieval from BigQuery with aggregations and metrics.

**File:** `app/api/analytics.php`

```php
<?php
/**
 * Analytics API
 *
 * Endpoints:
 * GET /analytics/dashboard      - Get dashboard metrics
 * GET /analytics/users          - User analytics
 * GET /analytics/revenue        - Revenue analytics
 * GET /analytics/products       - Product analytics
 * GET /analytics/traffic        - Traffic analytics
 * POST /analytics/custom-query  - Run custom query (admin)
 * POST /analytics/track-event   - Track custom event
 */

use CloudFramework\Patterns\RESTful;

class API extends RESTful
{
    private $bq;
    private $dataset = 'analytics';
    private $currentUser;

    function main()
    {
        // Authenticate
        if(!$this->authenticate()) {
            return $this->setError('Authentication required', 401);
        }

        $this->bq = $this->core->loadClass('DataBQ', [$this->dataset]);

        $this->sendCorsHeaders();

        if($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(204);
            exit;
        }

        $endpoint = $this->params[0] ?? 'dashboard';

        if(!$this->useFunction('ENDPOINT_' . $endpoint)) {
            return $this->setErrorFromCodelib('not-found');
        }
    }

    /**
     * GET /analytics/dashboard
     */
    public function ENDPOINT_dashboard()
    {
        if(!$this->checkMethod('GET')) return;

        $period = $this->formParams['period'] ?? '7d'; // 7d, 30d, 90d, 1y
        $dateRange = $this->getDateRange($period);

        // Cache key
        $cacheKey = "analytics_dashboard_{$period}";
        $cached = $this->core->cache->get($cacheKey);

        if($cached !== null) {
            return $this->addReturnData($cached);
        }

        $dashboard = [
            'period' => $period,
            'date_range' => $dateRange,
            'metrics' => []
        ];

        // Total users
        $dashboard['metrics']['total_users'] = $this->getTotalUsers($dateRange);

        // Active users
        $dashboard['metrics']['active_users'] = $this->getActiveUsers($dateRange);

        // New users
        $dashboard['metrics']['new_users'] = $this->getNewUsers($dateRange);

        // Total revenue
        $dashboard['metrics']['revenue'] = $this->getRevenue($dateRange);

        // Total orders
        $dashboard['metrics']['orders'] = $this->getOrders($dateRange);

        // Average order value
        $dashboard['metrics']['avg_order_value'] = $this->getAverageOrderValue($dateRange);

        // Page views
        $dashboard['metrics']['page_views'] = $this->getPageViews($dateRange);

        // Conversion rate
        $dashboard['metrics']['conversion_rate'] = $this->getConversionRate($dateRange);

        // Top products
        $dashboard['top_products'] = $this->getTopProducts($dateRange, 5);

        // Traffic sources
        $dashboard['traffic_sources'] = $this->getTrafficSources($dateRange);

        // User growth (daily)
        $dashboard['user_growth'] = $this->getUserGrowth($dateRange);

        // Revenue by day
        $dashboard['revenue_by_day'] = $this->getRevenueByDay($dateRange);

        // Cache for 10 minutes
        $this->core->cache->set($cacheKey, $dashboard, 600);

        $this->addReturnData($dashboard);
    }

    /**
     * GET /analytics/users
     */
    public function ENDPOINT_users()
    {
        if(!$this->checkMethod('GET')) return;

        $period = $this->formParams['period'] ?? '30d';
        $dateRange = $this->getDateRange($period);

        $analytics = [
            'period' => $period,
            'metrics' => [
                'total' => $this->getTotalUsers($dateRange),
                'active' => $this->getActiveUsers($dateRange),
                'new' => $this->getNewUsers($dateRange),
                'returning' => $this->getReturningUsers($dateRange)
            ],
            'by_country' => $this->getUsersByCountry($dateRange),
            'by_device' => $this->getUsersByDevice($dateRange),
            'by_browser' => $this->getUsersByBrowser($dateRange),
            'retention' => $this->getUserRetention($dateRange),
            'cohort_analysis' => $this->getCohortAnalysis($dateRange)
        ];

        $this->addReturnData($analytics);
    }

    /**
     * GET /analytics/revenue
     */
    public function ENDPOINT_revenue()
    {
        if(!$this->checkMethod('GET')) return;

        $period = $this->formParams['period'] ?? '30d';
        $dateRange = $this->getDateRange($period);

        $analytics = [
            'period' => $period,
            'metrics' => [
                'total_revenue' => $this->getRevenue($dateRange),
                'total_orders' => $this->getOrders($dateRange),
                'avg_order_value' => $this->getAverageOrderValue($dateRange),
                'revenue_per_user' => $this->getRevenuePerUser($dateRange)
            ],
            'by_day' => $this->getRevenueByDay($dateRange),
            'by_category' => $this->getRevenueByCategory($dateRange),
            'by_product' => $this->getRevenueByProduct($dateRange),
            'top_customers' => $this->getTopCustomers($dateRange, 10)
        ];

        $this->addReturnData($analytics);
    }

    /**
     * GET /analytics/products
     */
    public function ENDPOINT_products()
    {
        if(!$this->checkMethod('GET')) return;

        $period = $this->formParams['period'] ?? '30d';
        $dateRange = $this->getDateRange($period);

        $analytics = [
            'period' => $period,
            'top_selling' => $this->getTopProducts($dateRange, 20),
            'top_revenue' => $this->getTopRevenueProducts($dateRange, 20),
            'most_viewed' => $this->getMostViewedProducts($dateRange, 20),
            'conversion_rates' => $this->getProductConversionRates($dateRange),
            'inventory_status' => $this->getInventoryStatus()
        ];

        $this->addReturnData($analytics);
    }

    /**
     * GET /analytics/traffic
     */
    public function ENDPOINT_traffic()
    {
        if(!$this->checkMethod('GET')) return;

        $period = $this->formParams['period'] ?? '30d';
        $dateRange = $this->getDateRange($period);

        $analytics = [
            'period' => $period,
            'metrics' => [
                'total_sessions' => $this->getTotalSessions($dateRange),
                'page_views' => $this->getPageViews($dateRange),
                'unique_visitors' => $this->getUniqueVisitors($dateRange),
                'avg_session_duration' => $this->getAvgSessionDuration($dateRange),
                'bounce_rate' => $this->getBounceRate($dateRange)
            ],
            'by_source' => $this->getTrafficSources($dateRange),
            'by_page' => $this->getTopPages($dateRange, 20),
            'by_hour' => $this->getTrafficByHour($dateRange),
            'by_day_of_week' => $this->getTrafficByDayOfWeek($dateRange)
        ];

        $this->addReturnData($analytics);
    }

    /**
     * POST /analytics/custom-query
     */
    public function ENDPOINT_custom_query()
    {
        if(!$this->checkMethod('POST')) return;

        // Admin only
        if($this->currentUser['role'] !== 'admin') {
            return $this->setError('Admin access required', 403);
        }

        if(!$this->checkMandatoryFormParams(['query'])) return;

        $query = $this->formParams['query'];

        // Validate query (prevent destructive operations)
        if(preg_match('/(DROP|DELETE|UPDATE|INSERT|TRUNCATE|ALTER)/i', $query)) {
            return $this->setError('Only SELECT queries are allowed', 400);
        }

        try {
            $result = $this->bq->query($query);

            if($this->bq->error()) {
                return $this->setError('Query failed: ' . implode(', ', $this->bq->getError()), 400);
            }

            $this->addReturnData([
                'count' => count($result),
                'results' => $result
            ]);

        } catch(Exception $e) {
            return $this->setError('Query error: ' . $e->getMessage(), 500);
        }
    }

    /**
     * POST /analytics/track-event
     */
    public function ENDPOINT_track_event()
    {
        if(!$this->checkMethod('POST')) return;

        if(!$this->checkMandatoryFormParams(['event_name'])) return;

        $eventData = [
            'event_name' => $this->formParams['event_name'],
            'user_id' => $this->formParams['user_id'] ?? null,
            'session_id' => $this->formParams['session_id'] ?? null,
            'properties' => $this->formParams['properties'] ?? [],
            'timestamp' => date('c'),
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
        ];

        // Insert into BigQuery
        $tableName = 'events';
        $result = $this->bq->insert($tableName, [$eventData]);

        if($this->bq->error()) {
            return $this->setError('Failed to track event', 500);
        }

        $this->setReturnStatus(201);
        $this->addReturnData(['tracked' => true, 'event' => $eventData['event_name']]);
    }

    // Metric calculation methods

    private function getTotalUsers(array $dateRange): int
    {
        $query = "
            SELECT COUNT(DISTINCT user_id) as total
            FROM users
            WHERE created_at <= '{$dateRange['end']}'
        ";

        $result = $this->bq->query($query);
        return $result[0]['total'] ?? 0;
    }

    private function getActiveUsers(array $dateRange): int
    {
        $query = "
            SELECT COUNT(DISTINCT user_id) as active
            FROM user_sessions
            WHERE DATE(session_start) BETWEEN '{$dateRange['start']}' AND '{$dateRange['end']}'
        ";

        $result = $this->bq->query($query);
        return $result[0]['active'] ?? 0;
    }

    private function getNewUsers(array $dateRange): int
    {
        $query = "
            SELECT COUNT(DISTINCT user_id) as new_users
            FROM users
            WHERE DATE(created_at) BETWEEN '{$dateRange['start']}' AND '{$dateRange['end']}'
        ";

        $result = $this->bq->query($query);
        return $result[0]['new_users'] ?? 0;
    }

    private function getReturningUsers(array $dateRange): int
    {
        $query = "
            SELECT COUNT(DISTINCT user_id) as returning
            FROM user_sessions
            WHERE DATE(session_start) BETWEEN '{$dateRange['start']}' AND '{$dateRange['end']}'
            AND user_id IN (
                SELECT user_id FROM user_sessions
                WHERE DATE(session_start) < '{$dateRange['start']}'
            )
        ";

        $result = $this->bq->query($query);
        return $result[0]['returning'] ?? 0;
    }

    private function getRevenue(array $dateRange): float
    {
        $query = "
            SELECT COALESCE(SUM(amount), 0) as revenue
            FROM orders
            WHERE DATE(created_at) BETWEEN '{$dateRange['start']}' AND '{$dateRange['end']}'
            AND status = 'completed'
        ";

        $result = $this->bq->query($query);
        return (float)($result[0]['revenue'] ?? 0);
    }

    private function getOrders(array $dateRange): int
    {
        $query = "
            SELECT COUNT(*) as orders
            FROM orders
            WHERE DATE(created_at) BETWEEN '{$dateRange['start']}' AND '{$dateRange['end']}'
            AND status = 'completed'
        ";

        $result = $this->bq->query($query);
        return $result[0]['orders'] ?? 0;
    }

    private function getAverageOrderValue(array $dateRange): float
    {
        $query = "
            SELECT COALESCE(AVG(amount), 0) as avg_value
            FROM orders
            WHERE DATE(created_at) BETWEEN '{$dateRange['start']}' AND '{$dateRange['end']}'
            AND status = 'completed'
        ";

        $result = $this->bq->query($query);
        return round((float)($result[0]['avg_value'] ?? 0), 2);
    }

    private function getPageViews(array $dateRange): int
    {
        $query = "
            SELECT COUNT(*) as views
            FROM page_views
            WHERE DATE(timestamp) BETWEEN '{$dateRange['start']}' AND '{$dateRange['end']}'
        ";

        $result = $this->bq->query($query);
        return $result[0]['views'] ?? 0;
    }

    private function getConversionRate(array $dateRange): float
    {
        $query = "
            SELECT
                COUNT(DISTINCT CASE WHEN purchased = true THEN user_id END) * 100.0 /
                NULLIF(COUNT(DISTINCT user_id), 0) as conversion_rate
            FROM user_sessions
            WHERE DATE(session_start) BETWEEN '{$dateRange['start']}' AND '{$dateRange['end']}'
        ";

        $result = $this->bq->query($query);
        return round((float)($result[0]['conversion_rate'] ?? 0), 2);
    }

    private function getTopProducts(array $dateRange, int $limit): array
    {
        $query = "
            SELECT
                product_id,
                product_name,
                COUNT(*) as orders,
                SUM(quantity) as units_sold,
                SUM(amount) as revenue
            FROM order_items
            WHERE DATE(created_at) BETWEEN '{$dateRange['start']}' AND '{$dateRange['end']}'
            GROUP BY product_id, product_name
            ORDER BY units_sold DESC
            LIMIT {$limit}
        ";

        return $this->bq->query($query);
    }

    private function getTrafficSources(array $dateRange): array
    {
        $query = "
            SELECT
                source,
                COUNT(*) as sessions,
                COUNT(DISTINCT user_id) as users
            FROM user_sessions
            WHERE DATE(session_start) BETWEEN '{$dateRange['start']}' AND '{$dateRange['end']}'
            GROUP BY source
            ORDER BY sessions DESC
        ";

        return $this->bq->query($query);
    }

    private function getUserGrowth(array $dateRange): array
    {
        $query = "
            SELECT
                DATE(created_at) as date,
                COUNT(*) as new_users
            FROM users
            WHERE DATE(created_at) BETWEEN '{$dateRange['start']}' AND '{$dateRange['end']}'
            GROUP BY DATE(created_at)
            ORDER BY date ASC
        ";

        return $this->bq->query($query);
    }

    private function getRevenueByDay(array $dateRange): array
    {
        $query = "
            SELECT
                DATE(created_at) as date,
                COUNT(*) as orders,
                SUM(amount) as revenue
            FROM orders
            WHERE DATE(created_at) BETWEEN '{$dateRange['start']}' AND '{$dateRange['end']}'
            AND status = 'completed'
            GROUP BY DATE(created_at)
            ORDER BY date ASC
        ";

        return $this->bq->query($query);
    }

    private function getUsersByCountry(array $dateRange): array
    {
        $query = "
            SELECT
                country,
                COUNT(DISTINCT user_id) as users
            FROM user_sessions
            WHERE DATE(session_start) BETWEEN '{$dateRange['start']}' AND '{$dateRange['end']}'
            GROUP BY country
            ORDER BY users DESC
            LIMIT 10
        ";

        return $this->bq->query($query);
    }

    private function getUsersByDevice(array $dateRange): array
    {
        $query = "
            SELECT
                device_type,
                COUNT(DISTINCT user_id) as users
            FROM user_sessions
            WHERE DATE(session_start) BETWEEN '{$dateRange['start']}' AND '{$dateRange['end']}'
            GROUP BY device_type
        ";

        return $this->bq->query($query);
    }

    private function getUsersByBrowser(array $dateRange): array
    {
        $query = "
            SELECT
                browser,
                COUNT(DISTINCT user_id) as users
            FROM user_sessions
            WHERE DATE(session_start) BETWEEN '{$dateRange['start']}' AND '{$dateRange['end']}'
            GROUP BY browser
            ORDER BY users DESC
            LIMIT 10
        ";

        return $this->bq->query($query);
    }

    private function getUserRetention(array $dateRange): array
    {
        // Calculate week-over-week retention
        $query = "
            WITH cohorts AS (
                SELECT
                    user_id,
                    DATE_TRUNC(DATE(created_at), WEEK) as cohort_week
                FROM users
                WHERE DATE(created_at) BETWEEN '{$dateRange['start']}' AND '{$dateRange['end']}'
            )
            SELECT
                cohort_week,
                COUNT(DISTINCT c.user_id) as cohort_size,
                COUNT(DISTINCT CASE WHEN DATE_DIFF(DATE(s.session_start), c.cohort_week, WEEK) = 1
                    THEN c.user_id END) as week_1,
                COUNT(DISTINCT CASE WHEN DATE_DIFF(DATE(s.session_start), c.cohort_week, WEEK) = 2
                    THEN c.user_id END) as week_2,
                COUNT(DISTINCT CASE WHEN DATE_DIFF(DATE(s.session_start), c.cohort_week, WEEK) = 3
                    THEN c.user_id END) as week_3
            FROM cohorts c
            LEFT JOIN user_sessions s ON c.user_id = s.user_id
            GROUP BY cohort_week
            ORDER BY cohort_week DESC
        ";

        return $this->bq->query($query);
    }

    private function getCohortAnalysis(array $dateRange): array
    {
        // Implementation similar to retention
        return [];
    }

    private function getRevenuePerUser(array $dateRange): float
    {
        $totalRevenue = $this->getRevenue($dateRange);
        $activeUsers = $this->getActiveUsers($dateRange);

        return $activeUsers > 0 ? round($totalRevenue / $activeUsers, 2) : 0;
    }

    private function getRevenueByCategory(array $dateRange): array
    {
        $query = "
            SELECT
                category,
                COUNT(*) as orders,
                SUM(amount) as revenue
            FROM order_items
            WHERE DATE(created_at) BETWEEN '{$dateRange['start']}' AND '{$dateRange['end']}'
            GROUP BY category
            ORDER BY revenue DESC
        ";

        return $this->bq->query($query);
    }

    private function getRevenueByProduct(array $dateRange): array
    {
        return $this->getTopProducts($dateRange, 20);
    }

    private function getTopCustomers(array $dateRange, int $limit): array
    {
        $query = "
            SELECT
                user_id,
                user_name,
                COUNT(*) as total_orders,
                SUM(amount) as total_spent
            FROM orders
            WHERE DATE(created_at) BETWEEN '{$dateRange['start']}' AND '{$dateRange['end']}'
            AND status = 'completed'
            GROUP BY user_id, user_name
            ORDER BY total_spent DESC
            LIMIT {$limit}
        ";

        return $this->bq->query($query);
    }

    private function getTopRevenueProducts(array $dateRange, int $limit): array
    {
        $query = "
            SELECT
                product_id,
                product_name,
                SUM(amount) as revenue,
                SUM(quantity) as units_sold
            FROM order_items
            WHERE DATE(created_at) BETWEEN '{$dateRange['start']}' AND '{$dateRange['end']}'
            GROUP BY product_id, product_name
            ORDER BY revenue DESC
            LIMIT {$limit}
        ";

        return $this->bq->query($query);
    }

    private function getMostViewedProducts(array $dateRange, int $limit): array
    {
        $query = "
            SELECT
                product_id,
                product_name,
                COUNT(*) as views
            FROM product_views
            WHERE DATE(timestamp) BETWEEN '{$dateRange['start']}' AND '{$dateRange['end']}'
            GROUP BY product_id, product_name
            ORDER BY views DESC
            LIMIT {$limit}
        ";

        return $this->bq->query($query);
    }

    private function getProductConversionRates(array $dateRange): array
    {
        $query = "
            SELECT
                p.product_id,
                p.product_name,
                COUNT(DISTINCT pv.user_id) as viewers,
                COUNT(DISTINCT oi.user_id) as buyers,
                (COUNT(DISTINCT oi.user_id) * 100.0 / NULLIF(COUNT(DISTINCT pv.user_id), 0)) as conversion_rate
            FROM product_views pv
            LEFT JOIN order_items oi ON pv.product_id = oi.product_id AND pv.user_id = oi.user_id
            LEFT JOIN products p ON pv.product_id = p.product_id
            WHERE DATE(pv.timestamp) BETWEEN '{$dateRange['start']}' AND '{$dateRange['end']}'
            GROUP BY p.product_id, p.product_name
            ORDER BY conversion_rate DESC
            LIMIT 20
        ";

        return $this->bq->query($query);
    }

    private function getInventoryStatus(): array
    {
        $query = "
            SELECT
                product_id,
                product_name,
                stock,
                CASE
                    WHEN stock = 0 THEN 'out_of_stock'
                    WHEN stock < 10 THEN 'low_stock'
                    ELSE 'in_stock'
                END as status
            FROM products
            ORDER BY stock ASC
            LIMIT 50
        ";

        return $this->bq->query($query);
    }

    private function getTotalSessions(array $dateRange): int
    {
        $query = "
            SELECT COUNT(*) as sessions
            FROM user_sessions
            WHERE DATE(session_start) BETWEEN '{$dateRange['start']}' AND '{$dateRange['end']}'
        ";

        $result = $this->bq->query($query);
        return $result[0]['sessions'] ?? 0;
    }

    private function getUniqueVisitors(array $dateRange): int
    {
        $query = "
            SELECT COUNT(DISTINCT user_id) as visitors
            FROM user_sessions
            WHERE DATE(session_start) BETWEEN '{$dateRange['start']}' AND '{$dateRange['end']}'
        ";

        $result = $this->bq->query($query);
        return $result[0]['visitors'] ?? 0;
    }

    private function getAvgSessionDuration(array $dateRange): float
    {
        $query = "
            SELECT AVG(session_duration) as avg_duration
            FROM user_sessions
            WHERE DATE(session_start) BETWEEN '{$dateRange['start']}' AND '{$dateRange['end']}'
            AND session_duration IS NOT NULL
        ";

        $result = $this->bq->query($query);
        return round((float)($result[0]['avg_duration'] ?? 0), 2);
    }

    private function getBounceRate(array $dateRange): float
    {
        $query = "
            SELECT
                COUNT(CASE WHEN bounced = true THEN 1 END) * 100.0 /
                NULLIF(COUNT(*), 0) as bounce_rate
            FROM user_sessions
            WHERE DATE(session_start) BETWEEN '{$dateRange['start']}' AND '{$dateRange['end']}'
        ";

        $result = $this->bq->query($query);
        return round((float)($result[0]['bounce_rate'] ?? 0), 2);
    }

    private function getTopPages(array $dateRange, int $limit): array
    {
        $query = "
            SELECT
                page_url,
                COUNT(*) as views,
                COUNT(DISTINCT user_id) as unique_visitors
            FROM page_views
            WHERE DATE(timestamp) BETWEEN '{$dateRange['start']}' AND '{$dateRange['end']}'
            GROUP BY page_url
            ORDER BY views DESC
            LIMIT {$limit}
        ";

        return $this->bq->query($query);
    }

    private function getTrafficByHour(array $dateRange): array
    {
        $query = "
            SELECT
                EXTRACT(HOUR FROM timestamp) as hour,
                COUNT(*) as sessions
            FROM user_sessions
            WHERE DATE(session_start) BETWEEN '{$dateRange['start']}' AND '{$dateRange['end']}'
            GROUP BY hour
            ORDER BY hour ASC
        ";

        return $this->bq->query($query);
    }

    private function getTrafficByDayOfWeek(array $dateRange): array
    {
        $query = "
            SELECT
                EXTRACT(DAYOFWEEK FROM session_start) as day_of_week,
                COUNT(*) as sessions
            FROM user_sessions
            WHERE DATE(session_start) BETWEEN '{$dateRange['start']}' AND '{$dateRange['end']}'
            GROUP BY day_of_week
            ORDER BY day_of_week ASC
        ";

        return $this->bq->query($query);
    }

    // Helper methods

    private function getDateRange(string $period): array
    {
        $end = date('Y-m-d');

        switch($period) {
            case '7d':
                $start = date('Y-m-d', strtotime('-7 days'));
                break;
            case '30d':
                $start = date('Y-m-d', strtotime('-30 days'));
                break;
            case '90d':
                $start = date('Y-m-d', strtotime('-90 days'));
                break;
            case '1y':
                $start = date('Y-m-d', strtotime('-1 year'));
                break;
            default:
                $start = date('Y-m-d', strtotime('-30 days'));
        }

        return ['start' => $start, 'end' => $end];
    }

    private function authenticate(): bool
    {
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';

        if(!preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            return false;
        }

        $token = $matches[1];
        $payload = $this->validateJWT($token);

        if(!$payload) {
            return false;
        }

        $this->currentUser = $payload;
        return true;
    }

    private function validateJWT(string $token): array|false
    {
        // JWT validation implementation
        // Similar to Authentication API
        return ['id' => 'user123', 'role' => 'admin']; // Placeholder
    }
}
```

---

## Multi-tenant API

Multi-tenant SaaS platform with tenant isolation and management.

**File:** `app/api/tenants.php`

```php
<?php
/**
 * Multi-tenant API
 *
 * Endpoints:
 * GET    /tenants/list           - List all tenants (superadmin)
 * GET    /tenants/info           - Get current tenant info
 * POST   /tenants/create         - Create new tenant (superadmin)
 * PUT    /tenants/update/{id}    - Update tenant
 * DELETE /tenants/delete/{id}    - Delete tenant (superadmin)
 * GET    /tenants/users          - List tenant users
 * POST   /tenants/users/add      - Add user to tenant
 * DELETE /tenants/users/remove   - Remove user from tenant
 * GET    /tenants/settings       - Get tenant settings
 * PUT    /tenants/settings       - Update tenant settings
 * GET    /tenants/stats          - Get tenant statistics
 */

use CloudFramework\Patterns\RESTful;

class API extends RESTful
{
    private $ds;
    private $currentUser;
    private $currentTenant;

    function main()
    {
        $this->ds = $this->core->loadClass('DataStore', ['Tenants']);

        // Authenticate
        if(!$this->authenticate()) {
            return $this->setError('Authentication required', 401);
        }

        // Identify tenant from request
        if(!$this->identifyTenant()) {
            return $this->setError('Tenant not found', 404);
        }

        $this->sendCorsHeaders();

        if($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(204);
            exit;
        }

        $endpoint = $this->params[0] ?? 'info';

        if(!$this->useFunction('ENDPOINT_' . $endpoint)) {
            return $this->setErrorFromCodelib('not-found');
        }
    }

    /**
     * GET /tenants/list
     */
    public function ENDPOINT_list()
    {
        if(!$this->checkMethod('GET')) return;

        // Superadmin only
        if($this->currentUser['role'] !== 'superadmin') {
            return $this->setError('Superadmin access required', 403);
        }

        $page = (int)($this->formParams['page'] ?? 1);
        $limit = (int)($this->formParams['limit'] ?? 20);
        $status = $this->formParams['status'] ?? null;

        $where = [];
        if($status) {
            $where[] = ['status', '=', $status];
        }

        $tenants = $this->ds->fetchAll($where, ['created_at' => 'DESC'], $limit, null, $page);
        $total = $this->ds->count($where);

        // Add statistics to each tenant
        foreach($tenants as &$tenant) {
            $tenant['stats'] = $this->getTenantStats($tenant['KeyId']);
        }

        $this->addReturnData([
            'tenants' => $tenants,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'pages' => ceil($total / $limit)
            ]
        ]);
    }

    /**
     * GET /tenants/info
     */
    public function ENDPOINT_info()
    {
        if(!$this->checkMethod('GET')) return;

        $tenant = $this->currentTenant;

        // Remove sensitive data
        unset($tenant['api_secret']);
        unset($tenant['database_password']);

        // Add statistics
        $tenant['stats'] = $this->getTenantStats($tenant['KeyId']);

        $this->addReturnData($tenant);
    }

    /**
     * POST /tenants/create
     */
    public function ENDPOINT_create()
    {
        if(!$this->checkMethod('POST')) return;

        // Superadmin only
        if($this->currentUser['role'] !== 'superadmin') {
            return $this->setError('Superadmin access required', 403);
        }

        if(!$this->checkMandatoryFormParams(['name', 'slug', 'admin_email'])) return;

        $slug = $this->generateSlug($this->formParams['slug']);

        // Check if slug exists
        $existing = $this->ds->fetchAll([['slug', '=', $slug]], null, 1);
        if(!empty($existing)) {
            return $this->setError('Tenant slug already exists', 409);
        }

        // Validate plan
        $plan = $this->formParams['plan'] ?? 'basic';
        $validPlans = ['basic', 'professional', 'enterprise'];
        if(!in_array($plan, $validPlans)) {
            return $this->setError('Invalid plan', 400);
        }

        // Create tenant
        $tenantData = [
            'name' => trim($this->formParams['name']),
            'slug' => $slug,
            'domain' => $this->formParams['domain'] ?? null,
            'admin_email' => $this->formParams['admin_email'],
            'plan' => $plan,
            'status' => 'active',
            'settings' => [
                'max_users' => $this->getPlanLimit($plan, 'users'),
                'max_storage' => $this->getPlanLimit($plan, 'storage'),
                'features' => $this->getPlanFeatures($plan)
            ],
            'api_key' => bin2hex(random_bytes(32)),
            'api_secret' => bin2hex(random_bytes(32)),
            'created_at' => date('c'),
            'updated_at' => date('c'),
            'created_by' => $this->currentUser['id']
        ];

        $tenantId = $this->ds->createEntity($tenantData);

        if($this->ds->error()) {
            return $this->setError('Failed to create tenant', 500);
        }

        // Create tenant database/namespace (if using separate databases)
        $this->provisionTenantResources($tenantId, $slug);

        // Create admin user for tenant
        $this->createTenantAdmin($tenantId, $this->formParams['admin_email']);

        // Log event
        $this->core->logs->add([
            'event' => 'tenant_created',
            'tenant_id' => $tenantId,
            'created_by' => $this->currentUser['id']
        ], 'tenants');

        $tenant = $this->ds->fetchOne($tenantId);

        $this->setReturnStatus(201);
        $this->addReturnData($tenant);
    }

    /**
     * PUT /tenants/update/{id}
     */
    public function ENDPOINT_update()
    {
        if(!$this->checkMethod('PUT')) return;

        $tenantId = $this->params[1] ?? null;

        if(!$tenantId) {
            return $this->setError('Tenant ID required', 400);
        }

        // Check permissions
        if($this->currentUser['role'] !== 'superadmin' && $this->currentTenant['KeyId'] !== $tenantId) {
            return $this->setError('Access denied', 403);
        }

        $tenant = $this->ds->fetchOne($tenantId);
        if(!$tenant) {
            return $this->setError('Tenant not found', 404);
        }

        $updateData = ['updated_at' => date('c')];

        // Allowed fields
        $allowedFields = ['name', 'domain', 'admin_email'];

        // Superadmin can update plan and status
        if($this->currentUser['role'] === 'superadmin') {
            $allowedFields[] = 'plan';
            $allowedFields[] = 'status';
        }

        foreach($allowedFields as $field) {
            if(isset($this->formParams[$field])) {
                $updateData[$field] = $this->formParams[$field];
            }
        }

        // Update plan limits if plan changed
        if(isset($updateData['plan'])) {
            $updateData['settings'] = [
                'max_users' => $this->getPlanLimit($updateData['plan'], 'users'),
                'max_storage' => $this->getPlanLimit($updateData['plan'], 'storage'),
                'features' => $this->getPlanFeatures($updateData['plan'])
            ];
        }

        $this->ds->updateEntity($tenantId, $updateData);

        if($this->ds->error()) {
            return $this->setError('Failed to update tenant', 500);
        }

        // Log event
        $this->core->logs->add([
            'event' => 'tenant_updated',
            'tenant_id' => $tenantId,
            'updated_by' => $this->currentUser['id']
        ], 'tenants');

        $updatedTenant = $this->ds->fetchOne($tenantId);
        $this->addReturnData($updatedTenant);
    }

    /**
     * DELETE /tenants/delete/{id}
     */
    public function ENDPOINT_delete()
    {
        if(!$this->checkMethod('DELETE')) return;

        // Superadmin only
        if($this->currentUser['role'] !== 'superadmin') {
            return $this->setError('Superadmin access required', 403);
        }

        $tenantId = $this->params[1] ?? null;
        if(!$tenantId) {
            return $this->setError('Tenant ID required', 400);
        }

        $tenant = $this->ds->fetchOne($tenantId);
        if(!$tenant) {
            return $this->setError('Tenant not found', 404);
        }

        // Soft delete
        $this->ds->updateEntity($tenantId, [
            'status' => 'deleted',
            'deleted_at' => date('c'),
            'deleted_by' => $this->currentUser['id']
        ]);

        // Deprovision resources (optional)
        // $this->deprovisionTenantResources($tenantId);

        // Log event
        $this->core->logs->add([
            'event' => 'tenant_deleted',
            'tenant_id' => $tenantId,
            'deleted_by' => $this->currentUser['id']
        ], 'tenants');

        $this->setReturnStatus(204);
    }

    /**
     * GET /tenants/users
     */
    public function ENDPOINT_users()
    {
        if(!$this->checkMethod('GET')) return;

        // Check if user has access to tenant
        if(!$this->canAccessTenant($this->currentTenant['KeyId'])) {
            return $this->setError('Access denied', 403);
        }

        $usersDS = $this->core->loadClass('DataStore', ['TenantUsers']);

        $users = $usersDS->fetchAll([
            ['tenant_id', '=', $this->currentTenant['KeyId']]
        ]);

        // Get user details
        $userDS = $this->core->loadClass('DataStore', ['Users']);

        foreach($users as &$tenantUser) {
            $user = $userDS->fetchOne($tenantUser['user_id']);
            if($user) {
                $tenantUser['user'] = [
                    'id' => $user['KeyId'],
                    'name' => $user['name'],
                    'email' => $user['email'],
                    'status' => $user['status']
                ];
            }
        }

        $this->addReturnData([
            'count' => count($users),
            'users' => $users
        ]);
    }

    /**
     * POST /tenants/users/add
     */
    public function ENDPOINT_users_add()
    {
        if(!$this->checkMethod('POST')) return;

        // Admin only
        if(!$this->isT enantAdmin()) {
            return $this->setError('Tenant admin access required', 403);
        }

        if(!$this->checkMandatoryFormParams(['user_email', 'role'])) return;

        // Check user limit
        $stats = $this->getTenantStats($this->currentTenant['KeyId']);
        $maxUsers = $this->currentTenant['settings']['max_users'];

        if($stats['users'] >= $maxUsers) {
            return $this->setError('User limit reached. Upgrade your plan.', 403);
        }

        // Find user
        $userDS = $this->core->loadClass('DataStore', ['Users']);
        $users = $userDS->fetchAll([['email', '=', $this->formParams['user_email']]], null, 1);

        if(empty($users)) {
            return $this->setError('User not found', 404);
        }

        $user = $users[0];

        // Check if already member
        $usersDS = $this->core->loadClass('DataStore', ['TenantUsers']);
        $existing = $usersDS->fetchAll([
            ['tenant_id', '=', $this->currentTenant['KeyId']],
            ['user_id', '=', $user['KeyId']]
        ], null, 1);

        if(!empty($existing)) {
            return $this->setError('User already member of this tenant', 409);
        }

        // Add user to tenant
        $membershipId = $usersDS->createEntity([
            'tenant_id' => $this->currentTenant['KeyId'],
            'user_id' => $user['KeyId'],
            'role' => $this->formParams['role'],
            'status' => 'active',
            'added_at' => date('c'),
            'added_by' => $this->currentUser['id']
        ]);

        // Log event
        $this->core->logs->add([
            'event' => 'user_added_to_tenant',
            'tenant_id' => $this->currentTenant['KeyId'],
            'user_id' => $user['KeyId'],
            'added_by' => $this->currentUser['id']
        ], 'tenants');

        $this->setReturnStatus(201);
        $this->addReturnData([
            'message' => 'User added successfully',
            'membership_id' => $membershipId
        ]);
    }

    /**
     * DELETE /tenants/users/remove
     */
    public function ENDPOINT_users_remove()
    {
        if(!$this->checkMethod('DELETE')) return;

        if(!$this->isTenantAdmin()) {
            return $this->setError('Tenant admin access required', 403);
        }

        if(!$this->checkMandatoryFormParams(['user_id'])) return;

        $usersDS = $this->core->loadClass('DataStore', ['TenantUsers']);

        $memberships = $usersDS->fetchAll([
            ['tenant_id', '=', $this->currentTenant['KeyId']],
            ['user_id', '=', $this->formParams['user_id']]
        ], null, 1);

        if(empty($memberships)) {
            return $this->setError('User not found in tenant', 404);
        }

        $usersDS->delete($memberships[0]['KeyId']);

        // Log event
        $this->core->logs->add([
            'event' => 'user_removed_from_tenant',
            'tenant_id' => $this->currentTenant['KeyId'],
            'user_id' => $this->formParams['user_id'],
            'removed_by' => $this->currentUser['id']
        ], 'tenants');

        $this->setReturnStatus(204);
    }

    /**
     * GET /tenants/settings
     */
    public function ENDPOINT_settings()
    {
        if(!$this->checkMethod('GET')) return;

        if(!$this->canAccessTenant($this->currentTenant['KeyId'])) {
            return $this->setError('Access denied', 403);
        }

        $settings = $this->currentTenant['settings'] ?? [];

        $this->addReturnData($settings);
    }

    /**
     * PUT /tenants/settings
     */
    public function ENDPOINT_settings_update()
    {
        if(!$this->checkMethod('PUT')) return;

        if(!$this->isTenantAdmin()) {
            return $this->setError('Tenant admin access required', 403);
        }

        $currentSettings = $this->currentTenant['settings'] ?? [];

        // Merge with new settings (preserving plan limits)
        $newSettings = array_merge($currentSettings, $this->formParams);

        // Prevent modification of plan limits
        $newSettings['max_users'] = $currentSettings['max_users'];
        $newSettings['max_storage'] = $currentSettings['max_storage'];
        $newSettings['features'] = $currentSettings['features'];

        $this->ds->updateEntity($this->currentTenant['KeyId'], [
            'settings' => $newSettings,
            'updated_at' => date('c')
        ]);

        $this->addReturnData($newSettings);
    }

    /**
     * GET /tenants/stats
     */
    public function ENDPOINT_stats()
    {
        if(!$this->checkMethod('GET')) return;

        if(!$this->canAccessTenant($this->currentTenant['KeyId'])) {
            return $this->setError('Access denied', 403);
        }

        $stats = $this->getTenantStats($this->currentTenant['KeyId']);

        $this->addReturnData($stats);
    }

    // Helper methods

    private function identifyTenant(): bool
    {
        // Try to identify tenant from:
        // 1. Subdomain
        // 2. Custom domain
        // 3. Header
        // 4. User's tenant

        $tenantId = null;

        // From header
        $tenantSlug = $_SERVER['HTTP_X_TENANT'] ?? null;

        if($tenantSlug) {
            $tenants = $this->ds->fetchAll([['slug', '=', $tenantSlug]], null, 1);
            if(!empty($tenants)) {
                $this->currentTenant = $tenants[0];
                return true;
            }
        }

        // From subdomain
        $host = $_SERVER['HTTP_HOST'] ?? '';
        if(preg_match('/^([a-z0-9-]+)\./i', $host, $matches)) {
            $subdomain = $matches[1];
            $tenants = $this->ds->fetchAll([['slug', '=', $subdomain]], null, 1);
            if(!empty($tenants)) {
                $this->currentTenant = $tenants[0];
                return true;
            }
        }

        // From user's default tenant
        if(isset($this->currentUser['tenant_id'])) {
            $tenant = $this->ds->fetchOne($this->currentUser['tenant_id']);
            if($tenant) {
                $this->currentTenant = $tenant;
                return true;
            }
        }

        return false;
    }

    private function authenticate(): bool
    {
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';

        if(!preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            return false;
        }

        $token = $matches[1];
        // Validate JWT (simplified)
        $this->currentUser = [
            'id' => 'user123',
            'email' => 'user@example.com',
            'role' => 'admin',
            'tenant_id' => 'tenant123'
        ];

        return true;
    }

    private function canAccessTenant(string $tenantId): bool
    {
        if($this->currentUser['role'] === 'superadmin') {
            return true;
        }

        // Check if user belongs to tenant
        $usersDS = $this->core->loadClass('DataStore', ['TenantUsers']);
        $memberships = $usersDS->fetchAll([
            ['tenant_id', '=', $tenantId],
            ['user_id', '=', $this->currentUser['id']],
            ['status', '=', 'active']
        ], null, 1);

        return !empty($memberships);
    }

    private function isTenantAdmin(): bool
    {
        if($this->currentUser['role'] === 'superadmin') {
            return true;
        }

        $usersDS = $this->core->loadClass('DataStore', ['TenantUsers']);
        $memberships = $usersDS->fetchAll([
            ['tenant_id', '=', $this->currentTenant['KeyId']],
            ['user_id', '=', $this->currentUser['id']],
            ['role', 'IN', ['admin', 'owner']]
        ], null, 1);

        return !empty($memberships);
    }

    private function getTenantStats(string $tenantId): array
    {
        $usersDS = $this->core->loadClass('DataStore', ['TenantUsers']);
        $users = $usersDS->count([['tenant_id', '=', $tenantId]]);

        // Calculate storage usage (simplified)
        $storage = 0; // Would calculate actual storage

        return [
            'users' => $users,
            'storage_used' => $storage,
            'storage_limit' => $this->currentTenant['settings']['max_storage'] ?? 0,
            'created_at' => $this->currentTenant['created_at'] ?? null
        ];
    }

    private function getPlanLimit(string $plan, string $type): int
    {
        $limits = [
            'basic' => ['users' => 5, 'storage' => 1073741824], // 1GB
            'professional' => ['users' => 25, 'storage' => 10737418240], // 10GB
            'enterprise' => ['users' => 100, 'storage' => 107374182400] // 100GB
        ];

        return $limits[$plan][$type] ?? 0;
    }

    private function getPlanFeatures(string $plan): array
    {
        $features = [
            'basic' => ['basic_support', 'api_access'],
            'professional' => ['basic_support', 'api_access', 'advanced_analytics', 'custom_branding'],
            'enterprise' => ['basic_support', 'api_access', 'advanced_analytics', 'custom_branding', 'priority_support', 'sso', 'custom_integrations']
        ];

        return $features[$plan] ?? [];
    }

    private function provisionTenantResources(string $tenantId, string $slug)
    {
        // Create tenant-specific resources
        // - Database namespace
        // - Storage bucket
        // - etc.
    }

    private function createTenantAdmin(string $tenantId, string $email)
    {
        // Create or link admin user to tenant
        $userDS = $this->core->loadClass('DataStore', ['Users']);
        $users = $userDS->fetchAll([['email', '=', $email]], null, 1);

        if(empty($users)) {
            // Create new user
            $userId = $userDS->createEntity([
                'email' => $email,
                'name' => 'Admin',
                'role' => 'admin',
                'status' => 'pending',
                'created_at' => date('c')
            ]);
        } else {
            $userId = $users[0]['KeyId'];
        }

        // Link to tenant
        $usersDS = $this->core->loadClass('DataStore', ['TenantUsers']);
        $usersDS->createEntity([
            'tenant_id' => $tenantId,
            'user_id' => $userId,
            'role' => 'owner',
            'status' => 'active',
            'added_at' => date('c')
        ]);
    }

    private function generateSlug(string $text): string
    {
        $text = strtolower($text);
        $text = preg_replace('/[^a-z0-9-]/', '-', $text);
        $text = preg_replace('/-+/', '-', $text);
        return trim($text, '-');
    }
}
```

---

## See Also

- [API Development Guide](../guides/api-development.md)
- [RESTful Class Reference](../api-reference/RESTful.md)
- [Security Guide](../guides/security.md)
- [Script Examples](script-examples.md)
- [GCP Examples](gcp-examples.md)
