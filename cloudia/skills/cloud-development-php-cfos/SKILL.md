# SKILL: CFOs - DataStore (`ds`) and SQL (`db`) Data Access

## When to Use This Skill

Use this skill when the developer needs to:
- **Read, create, update, or delete** entities in Google Cloud Datastore via `$this->cfos->ds()`
- **Read, create, update, or delete** records in SQL/CloudSQL databases via `$this->cfos->db()`
- Execute **raw queries** with `$this->cfos->dbQuery()` or `$this->cfos->ds()->query()`
- Configure **database connections**, namespaces, or service accounts
- Understand **query building**, pagination, caching, and error handling patterns

---

## Core Concepts

| Concept | Description |
|---------|-------------|
| **CFOs** | CloudFramework Objects - orchestration layer for data backends |
| **`$this->cfos->ds('Entity')`** | Access Google Cloud Datastore entities |
| **`$this->cfos->db('Table')`** | Access SQL database tables |
| **`$this->cfos->dbQuery()`** | Execute raw SQL queries |
| **Namespace** | Multi-tenancy isolation in Datastore |
| **CFO Model** | Schema definition loaded from the CloudFramework platform |

**Key Rule:** Always check `->error` after every operation. All data methods can fail silently without exceptions.

---

## Part 1: DataStore Access - `$this->cfos->ds()`

### 1.1 How It Works

`$this->cfos->ds('EntityName')` returns a `DataStore` object for the specified entity. The object is **lazily initialized** on first access and **cached** for subsequent calls.

```php
// First call: initializes DataStore for 'Users' entity
$users = $this->cfos->ds('Users')->fetchAll();

// Second call: returns the cached DataStore object
$user = $this->cfos->ds('Users')->fetchOneByKey('user_123');
```

The entity model (fields, types, validation) is automatically loaded from the CloudFramework platform using the integration key configured during `initCFOs()`.

---

### 1.2 Read Operations

#### fetchOneByKey() - Fetch by Primary Key

The most common operation. Retrieves a single entity by its `KeyName` or `KeyId`.

```php
$user = $this->cfos->ds('CloudFrameWorkUsers')->fetchOneByKey($userId);
if ($this->cfos->ds('CloudFrameWorkUsers')->error)
    return $this->setErrorFromCodelib('system-error', $this->cfos->ds('CloudFrameWorkUsers')->errorMsg);

if (!$user)
    return $this->setErrorFromCodelib('not-found', 'User not found');
```

#### fetchOne() - Fetch Single with Filters

Returns the first entity matching the conditions.

**IMPORTANT:** Despite its name, `fetchOne()` returns an **array of results** (like `fetchAll` with limit 1), NOT a single entity. You must access the first element with `[0]`.

```php
// fetchOne($fields, $where, $order)
// $fields: '*' for all fields, or 'field1,field2' (never include KeyId/KeyName)
// $where:  associative array with filters (see Where Conditions section 1.3)
// $order:  'FieldName ASC' (ascending) or 'FieldName DESC' (descending)

// Get the latest version (descending order)
$version = $this->cfos->ds('CloudFrameWorkLocalizationsVersions')->fetchOne(
    '*',                        // fields: all
    ['Cat' => $cat],            // where: filter by category
    'Version DESC'              // order: descending (latest first)
);
if ($this->cfos->ds('CloudFrameWorkLocalizationsVersions')->error)
    return $this->setErrorFromCodelib('system-error', $this->cfos->ds('CloudFrameWorkLocalizationsVersions')->errorMsg);

// IMPORTANT: access with [0] - fetchOne returns an array
$data = $version[0] ?? null;
if (!$data)
    return $this->setErrorFromCodelib('not-found', 'Version not found');
```

```php
// Get the oldest task (ascending order)
$oldest = $this->cfos->ds('CloudFrameWorkProjectsTasks')->fetchOne(
    'Name,Status,DateCreation',     // specific fields
    ['Status' => 'pending'],        // filter
    'DateCreation ASC'              // order: ascending (oldest first)
);
if ($this->cfos->ds('CloudFrameWorkProjectsTasks')->error)
    return $this->setErrorFromCodelib('system-error', $this->cfos->ds('CloudFrameWorkProjectsTasks')->errorMsg);

$task = $oldest[0] ?? null;
```

```php
// Without order (returns first match found, no guaranteed order)
$user = $this->cfos->ds('CloudFrameWorkUsers')->fetchOne(
    '*',
    ['email' => 'john@example.com']
);
$data = $user[0] ?? null;
```

#### fetchAll() - Fetch Multiple Entities

Returns all entities matching the conditions (respects `limit` if set).

**IMPORTANT:** Never include `KeyId` or `KeyName` in the `$fields` parameter. These key fields are always returned automatically by Datastore and must NOT be listed as fields to fetch. Including them will cause errors.

```php
// fetchAll($fields, $where, $order)
// $order accepts: 'FieldName ASC' (ascending) or 'FieldName DESC' (descending)
// Multiple order fields: 'Field1 ASC,Field2 DESC'

// Descending order (most recent first)
$leads = $this->cfos->ds('CloudFrameWorkCRMLeads')->fetchAll(
    '*',                                // all fields (KeyId/KeyName auto-included)
    ['Open' => true],                   // filter: only open leads
    'DateUpdating DESC'                 // order: descending (newest first)
);
if ($this->cfos->ds('CloudFrameWorkCRMLeads')->error)
    return $this->setErrorFromCodelib('system-error', $this->cfos->ds('CloudFrameWorkCRMLeads')->errorMsg);

// Ascending order (oldest first)
$tasks = $this->cfos->ds('CloudFrameWorkProjectsTasks')->fetchAll(
    '*',
    ['Status' => 'pending'],
    'DateCreation ASC'                  // order: ascending (oldest first)
);

// CORRECT: list only data fields, NOT KeyId/KeyName
$users = $this->cfos->ds('CloudFrameWorkUsers')->fetchAll('name,email,status', ['UserActive' => true]);

// WRONG: never include KeyId or KeyName in fields
// $users = $this->cfos->ds('CloudFrameWorkUsers')->fetchAll('KeyId,name,email', $where);  // ERROR!
```

**With distinct fields:**
```php
$categories = $this->cfos->ds('CloudFrameWorkLocalizations')->fetchAll(
    'distinct Cat',                     // distinct values of 'Cat' field
    ['App' => $app]
);
```

#### fetchByKeys() - Fetch Multiple by Keys

Retrieve multiple entities by an array of keys.

```php
$companies = $this->cfos->ds('CloudFrameWorkCompanies')->fetchByKeys(['comp_1', 'comp_2', 'comp_3']);
// Or with comma-separated string
$companies = $this->cfos->ds('CloudFrameWorkCompanies')->fetchByKeys('comp_1,comp_2,comp_3');

// Access a specific result
$company = $this->cfos->ds('CloudFrameWorkCompanies')->fetchByKeys($companyId)[0] ?? null;
if (!$company)
    return $this->setErrorFromCodelib('not-found', 'Company not found');
```

#### fetchCount() - Count Entities

```php
$count = $this->cfos->ds('CloudFrameWorkUsers')->fetchCount(['UserActive' => true]);
```

---

### 1.3 Where Conditions (Filters)

The `$where` array supports multiple filter types:

```php
// Equality
['status' => 'active']

// Comparators (prefix the key)
['>age' => 18]            // age > 18
['<=balance' => 1000]     // balance <= 1000
['!=type' => 'admin']     // type != 'admin'

// Null checks
['deleted' => '__null__']       // deleted IS NULL
['active' => '__notnull__']     // active IS NOT NULL

// LIKE search (suffix with %)
['name%' => 'john%']           // name LIKE 'john%'

// Multiple conditions (AND)
['status' => 'active', '>age' => 18, 'deleted' => '__null__']
```

---

### 1.4 Create Operations

#### createEntities() - Create One or Multiple Entities

```php
// Single entity
$entityData = [
    'KeyName' => 'cx-admin',
    'PrivilegeName' => 'CX Admin Profile'
];
$this->cfos->ds('CloudFrameWorkPrivileges')->createEntities($entityData);
if ($this->cfos->ds('CloudFrameWorkPrivileges')->error)
    return $this->setErrorFromCodelib('system-error', $this->cfos->ds('CloudFrameWorkPrivileges')->errorMsg);
```

```php
// Multiple entities (array of arrays)
$entities = [
    ['KeyName' => 'role_1', 'Name' => 'Admin'],
    ['KeyName' => 'role_2', 'Name' => 'Editor']
];
$this->cfos->ds('CloudFrameWorkRoles')->createEntities($entities);
if ($this->cfos->ds('CloudFrameWorkRoles')->error)
    return $this->setErrorFromCodelib('datastore-error', $this->cfos->ds('CloudFrameWorkRoles')->errorMsg);
```

#### createEntity() - Create a Single Entity

```php
$entity = $this->cfos->ds('CloudFrameWorkUsers')->createEntity([
    'KeyName' => 'user_' . time(),
    'name' => 'John Doe',
    'email' => 'john@example.com',
    'status' => 'active'
]);
```

**Key Types:**
- `KeyName` - Custom string key (you define it)
- `KeyId` - Auto-generated numeric key (Datastore assigns it)

---

### 1.5 Update Operations (Upsert Pattern)

In Datastore, `createEntities()` is also used for updates. If the entity key already exists, it overwrites the entity.

```php
// Read entity
$task = $this->cfos->ds('CloudFrameWorkProjectsTasks')->fetchOneByKey($taskId);

// Modify it
$task['TimeSpent'] = floatval($newTime);

// Save back (upsert)
$this->cfos->ds('CloudFrameWorkProjectsTasks')->createEntities([$task]);
if ($this->cfos->ds('CloudFrameWorkProjectsTasks')->error)
    return $this->setErrorFromCodelib('system-error', $this->cfos->ds('CloudFrameWorkProjectsTasks')->errorMsg);
```

#### update() - Update by Key

```php
$this->cfos->ds('CloudFrameWorkUsers')->update(
    ['name' => 'Jane Doe', 'email' => 'jane@example.com'],  // data to update
    'user_123'                                                // key
);
if ($this->cfos->ds('CloudFrameWorkUsers')->error)
    return $this->setErrorFromCodelib('system-error', $this->cfos->ds('CloudFrameWorkUsers')->errorMsg);
```

---

### 1.6 Delete Operations

#### deleteByKeys() - Delete by Key Array

```php
$this->cfos->ds('CloudFrameWorkUserAuths')->deleteByKeys([$credentials['KeyName']]);
if ($this->cfos->ds('CloudFrameWorkUserAuths')->error) {
    // Handle error
    $this->core->logs->add($this->cfos->ds('CloudFrameWorkUserAuths')->errorMsg, 'delete-error');
}
```

#### delete() - Delete by Where Condition

```php
$this->cfos->ds('CloudFrameWorkUsers')->delete(['status' => 'inactive']);
```

---

### 1.7 Pagination

#### Using `limit` Property

```php
$this->cfos->ds('CloudFrameWorkCompanies')->limit = 50;
$companies = $this->cfos->ds('CloudFrameWorkCompanies')->fetchAll('*', $where, $order);
```

#### Cursor-Based Pagination

For large datasets, use cursor pagination to iterate through results:

```php
// First page
$this->cfos->ds('CloudFrameWorkCompanies')->limit = 100;

// Set cursor from request (for subsequent pages)
if ($next_cursor = $this->getUrlPathParamater('next_cursor'))
    $this->cfos->ds('CloudFrameWorkCompanies')->cursor = $next_cursor;

// Fetch results
$companies = $this->cfos->ds('CloudFrameWorkCompanies')->fetchAll($fields, $where, $order);
if ($this->cfos->ds('CloudFrameWorkCompanies')->error)
    return $this->setErrorFromCodelib('system-error', $this->cfos->ds('CloudFrameWorkCompanies')->errorMsg);

// Return cursor for next page
$total_rows = count($companies);
$response['next_cursor'] = ($total_rows == $limit)
    ? $this->cfos->ds('CloudFrameWorkCompanies')->last_cursor
    : null;
```

**Iterating through all results:**

```php
$all_items = [];
$this->cfos->ds('CloudFrameWorkLocalizations')->limit = 500;

while ($items = $this->cfos->ds('CloudFrameWorkLocalizations')->fetchAll('DISTINCT App,Cat', null, 'App,Cat')) {
    $all_items = array_merge($all_items, $items);
    $this->cfos->ds('CloudFrameWorkLocalizations')->cursor = $this->cfos->ds('CloudFrameWorkLocalizations')->last_cursor;
}
```

---

### 1.8 Caching

Enable Datastore caching for frequently accessed entities:

```php
// Activate cache for entity
$this->cfos->ds('CloudFrameWorkPlatformsSecrets')->activateCache();

// Try cache first, fetch from Datastore on miss
$data = $this->cfos->ds('CloudFrameWorkPlatformsSecrets')->getCache('cache_key_' . $this->namespace);
if (!$data) {
    // Fetch from Datastore
    $data = $this->cfos->ds('CloudFrameWorkPlatformsSecrets')->fetchOneByKey('my-key');
    if ($this->cfos->ds('CloudFrameWorkPlatformsSecrets')->error)
        return $this->setErrorFromCodelib('system-error', $this->cfos->ds('CloudFrameWorkPlatformsSecrets')->errorMsg);

    // Store in cache for next time
    if ($data)
        $this->cfos->ds('CloudFrameWorkPlatformsSecrets')->setCache('cache_key_' . $this->namespace, $data);
}
```

**Cache with encryption:**
```php
$this->cfos->ds('EntityName')->activateCache(true, 'encryption_key', 'encryption_iv');
```

**Reset cache:**
```php
$this->cfos->ds('EntityName')->resetCache();
```

---

### 1.9 Namespace Handling

Change the namespace for multi-tenancy or cross-namespace queries:

```php
// Read from a different namespace
$this->cfos->ds('CloudFrameWorkPlatforms')->namespace = 'cloudframework_platforms';
$data = $this->cfos->ds('CloudFrameWorkPlatforms')->fetchOneByKey($this->namespace);

// Restore to original namespace
$this->cfos->ds('CloudFrameWorkPlatforms')->namespace = $this->namespace;
```

---

### 1.10 Aggregation Functions

```php
// Count
$count = $this->cfos->ds('CloudFrameWorkUsers')->count(['status' => 'active']);

// Sum
$total = $this->cfos->ds('CloudFrameWorkOrders')->sum('amount', ['status' => 'completed']);

// Average
$avg = $this->cfos->ds('CloudFrameWorkUsers')->avg('age', ['country' => 'Spain']);
```

---

### 1.11 Raw GQL Queries

```php
$results = $this->cfos->ds('CloudFrameWorkUsers')->query(
    'SELECT * FROM CloudFrameWorkUsers WHERE status = @status AND age >= @age LIMIT @limit',
    ['status' => 'active', 'age' => 18, 'limit' => 100]
);
```

---

### 1.12 Utility Methods

```php
// Get an empty entity template based on the schema
$template = $this->cfos->ds('CloudFrameWorkUsers')->getEntityTemplate();

// Validate data against schema
$validated = $this->cfos->ds('CloudFrameWorkUsers')->getCheckedRecordWithMapData($data);
```

---

## Part 2: SQL Database Access - `$this->cfos->db()`

### 2.1 How It Works

`$this->cfos->db('TableName')` returns a `DataSQL` object for the specified table/CFO. Like `ds()`, it is lazily initialized and cached.

**Prerequisites:** Before using `db()`, you must establish a database connection. This is typically done during API initialization:

```php
$this->initAPI
    ->initCFOs()
    ->setDBConnectionFromSecret('FINANCE_DB_ACCESS.connection')  // from platform secret
    ->endPointParams(3);
```

Or manually:
```php
$this->cfos->setDBCredentials([
    'dbServer' => 'localhost',
    'dbUser' => 'root',
    'dbPassword' => 'secret',
    'dbName' => 'mydb',
    'dbSocket' => '/cloudsql/project:region:instance'  // for Cloud SQL
]);
```

---

### 2.2 Read Operations

#### fetch() - Fetch Multiple Records

The primary read method. Accepts array filters or string WHERE clause.

**With array filters:**
```php
$orgs = $this->cfos->db('CF_DirectoryOrganizations')->fetch(
    ['DirectoryOrganization_Active' => 1],                    // where conditions
    'DirectoryOrganization_Id id, DirectoryOrganization_Name' // fields (with aliases)
);
if ($this->cfos->db('CF_DirectoryOrganizations')->error)
    return $this->setErrorFromCodelib('system-error', $this->cfos->db('CF_DirectoryOrganizations')->errorMsg);
```

**With complex filters and special values:**
```php
$records = $this->cfos->db('CF_DirectoryOrganizations')->fetch(
    [
        'DirectoryOrganization_Active' => 1,
        'DirectoryOrganization_AfterBanks' => 1,
        'DirectoryOrganization_Namespace' => '__notnull__'    // IS NOT NULL
    ],
    'DirectoryOrganization_Id, DirectoryOrganization_Name, DirectoryOrganization_Namespace'
);
if ($this->cfos->db('CF_DirectoryOrganizations')->error)
    return $this->setErrorFromCodelib('db-error', $this->cfos->db('CF_DirectoryOrganizations')->errorMsg);
```

**With string WHERE clause (for complex SQL):**
```php
$where = "DirectoryOrganization_Active=1 AND DirectoryOrganization_Id IN ({$orgIds})";
$orgs = $this->cfos->db('CF_DirectoryOrganizations')->fetch(
    $where,
    'DirectoryOrganization_Id id, CONCAT(DirectoryOrganization_Id,":",DirectoryOrganization_Name) value'
);
```

#### fetchOneByKey() - Fetch by Primary Key

```php
$deposit = $this->cfos->db('FINANCE_deposits')->fetchOneByKey($depositId);
if ($this->cfos->db('FINANCE_deposits')->error)
    return $this->setErrorFromCodelib('system-error', $this->cfos->db('FINANCE_deposits')->errorMsg);
if (!$deposit)
    return $this->setErrorFromCodelib('not-found', 'Deposit not found: ' . $depositId);
```

#### fetchOne() - Fetch Single with Filters

Returns the first record matching the conditions. Unlike DataStore's `fetchOne()`, SQL's `fetchOne()` returns **a single record directly** (associative array), NOT an array of results.

```php
// fetchOne($where, $fields)
// $where:  associative array or string WHERE clause
// $fields: comma-separated field names (with optional aliases)
// Note: parameter order is ($where, $fields), different from DataStore's ($fields, $where, $order)

$last_invoice = $this->cfos->db('FINANCE_documents')->fetchOne(
    [
        'FinancialInvoice_DirectoryOrganization_Id' => intval($org),
        'FinancialInvoice_FinancialInvoicesType_Id' => 2,
        'FinancialInvoice_FinancialInvoicesState_Id' => 2,
        'FinancialInvoice_CRMOrganization_Id' => intval($company),
    ],
    'FinancialInvoice_Id, FinancialInvoice_PerspectiveBusiness, FinancialInvoice_ContractId'
);
if ($this->cfos->db('FINANCE_documents')->error)
    return $this->setErrorFromCodelib('system-error', $this->cfos->db('FINANCE_documents')->errorMsg);

// Direct access - no need for [0] (unlike DataStore)
if (!$last_invoice)
    return $this->setErrorFromCodelib('not-found', 'Invoice not found');
$invoiceId = $last_invoice['FinancialInvoice_Id'];
```

**With ordering** (use `setOrder()` before calling `fetchOne()`):
```php
// Get the most recent invoice (descending)
$this->cfos->db('FINANCE_documents')->setOrder('FinancialInvoice_DateOfInvoice', 'DESC');
$latest = $this->cfos->db('FINANCE_documents')->fetchOne(
    ['FinancialInvoice_DirectoryOrganization_Id' => intval($org)],
    'FinancialInvoice_Id, FinancialInvoice_DateOfInvoice'
);

// Get the oldest invoice (ascending)
$this->cfos->db('FINANCE_documents')->setOrder('FinancialInvoice_DateOfInvoice', 'ASC');
$oldest = $this->cfos->db('FINANCE_documents')->fetchOne(
    ['FinancialInvoice_DirectoryOrganization_Id' => intval($org)],
    'FinancialInvoice_Id, FinancialInvoice_DateOfInvoice'
);
```

#### fetchByKeys() - Fetch Multiple by Primary Keys

```php
$users = $this->cfos->db('CF_Users')->fetchByKeys([1, 2, 3]);
// Or comma-separated
$users = $this->cfos->db('CF_Users')->fetchByKeys('1,2,3');
```

---

### 2.3 Where Conditions (Filters)

Array filters support the same operators as Datastore, plus SQL-specific features:

```php
// Equality
['status' => 'active']

// Comparators (prefix the key)
['>age' => 18]                           // age > 18
['<=balance' => 1000]                    // balance <= 1000
['!=type' => 'admin']                    // type != 'admin'

// Null/Empty checks
['deleted' => '__null__']                // IS NULL
['active' => '__notnull__']              // IS NOT NULL
['name' => '__empty__']                  // = ''
['name' => '__noempty__']                // != ''

// LIKE search (suffix with %)
['Prefix' => "{$prefix}%"]              // LIKE 'INV-%'

// SQL expressions (raw SQL in key with %s placeholder)
['(year(DateField)=%s)' => '2026']       // year(DateField) = '2026'

// Multiple conditions (AND)
[
    'DirectoryOrganization_Active' => 1,
    'DirectoryOrganization_Namespace' => '__notnull__',
    '>FinancialInvoice_Amount' => 0
]
```

---

### 2.4 Create Operations

#### insert() - Insert a New Record

Returns the auto-increment ID on success, or `false`/`null` on error.

```php
$invoice_id = $this->cfos->db('CF_FinancialInvoices')->insert($document_data);
if ($this->cfos->db('CF_FinancialInvoices')->error)
    return $this->setErrorFromCodelib('system-error', $this->cfos->db('CF_FinancialInvoices')->errorMsg);

// Use the new ID
$document_data['FinancialInvoice_Id'] = $invoice_id;
```

**Check insert result:**
```php
if (!$invoice_id = $this->cfos->db('FINANCE_documents')->insert($invoice)) {
    // Insert failed
    $this->core->logs->add($this->cfos->db('FINANCE_documents')->errorMsg, 'insert-error');
    return $this->setErrorFromCodelib('system-error', 'Error creating document');
}
// Success - $invoice_id contains the new ID
```

---

### 2.5 Update Operations

#### update() - Update Existing Record

The data array must include the primary key field.

```php
$update = [
    'FinancialDeposit_Id' => $deposit_id,     // primary key
    '_trigger' => 2                            // field to update
];
$this->cfos->db('FINANCE_deposits')->update($update);
if ($this->cfos->db('FINANCE_deposits')->error)
    return $this->setErrorFromCodelib('system-error', $this->cfos->db('FINANCE_deposits')->errorMsg);
```

**Update and re-fetch:**
```php
$update = ['FinancialDeposit_Id' => $deposit_id, '_trigger' => 2];
$this->cfos->db('FINANCE_deposits')->update($update);
if ($this->cfos->db('FINANCE_deposits')->error)
    return $this->setErrorFromCodelib('system-error', $this->cfos->db('FINANCE_deposits')->errorMsg);

// Re-read to get updated data
$deposit = $this->cfos->db('FINANCE_deposits')->fetchOneByKey($deposit_id);
```

#### upsert() - Insert or Update

```php
$this->cfos->db('CF_Users')->upsert([
    'id' => 123,
    'name' => 'Jane Doe',
    'email' => 'jane@example.com'
]);
```

---

### 2.6 Delete Operations

#### delete() - Delete by Primary Key

```php
$this->cfos->db('CF_Users')->delete(['id' => 123]);
if ($this->cfos->db('CF_Users')->error)
    return $this->setErrorFromCodelib('system-error', $this->cfos->db('CF_Users')->errorMsg);
```

---

### 2.7 Ordering Results

#### setOrder() - Set ORDER BY

The second parameter accepts `'ASC'` (ascending) or `'DESC'` (descending).

```php
// Descending order (most recent first)
$this->cfos->db('CF_FinancialInvoices')->setOrder('FinancialInvoice_DateOfInvoice', 'DESC');
$invoices = $this->cfos->db('CF_FinancialInvoices')->fetch($where);

// Ascending order (oldest first)
$this->cfos->db('CF_FinancialInvoices')->setOrder('FinancialInvoice_DateOfInvoice', 'ASC');
$invoices = $this->cfos->db('CF_FinancialInvoices')->fetch($where);
```

#### addOrder() - Add Additional ORDER BY

```php
$this->cfos->db('CF_FinancialInvoices')->setOrder('FinancialInvoice_DateOfInvoice', 'DESC');
$this->cfos->db('CF_FinancialInvoices')->addOrder('FinancialInvoice_DocumentId', 'ASC');

$last_invoice = $this->cfos->db('CF_FinancialInvoices')->fetchOne($where, $fields);
```

#### unsetOrder() - Clear ORDER BY

```php
$this->cfos->db('CF_FinancialInvoices')->unsetOrder();
```

---

### 2.8 Pagination

#### limit Property

```php
$this->cfos->db('CF_DirectoryOrganizations')->limit = 5;
$orgs = $this->cfos->db('CF_DirectoryOrganizations')->fetch(['DirectoryOrganization_Active' => 1]);
```

#### setLimit() and setPage()

```php
$this->cfos->db('CF_Users')->setLimit(50);
$this->cfos->db('CF_Users')->setPage(2);  // Skip 50, return next 50
$users = $this->cfos->db('CF_Users')->fetch($where);
```

#### setOffset()

```php
$this->cfos->db('CF_Users')->setLimit(50);
$this->cfos->db('CF_Users')->setOffset(100);
$users = $this->cfos->db('CF_Users')->fetch($where);
```

---

### 2.9 Aggregation Functions

```php
// Count
$count = $this->cfos->db('CF_Users')->count('*', ['status' => 'active']);

// Sum
$total = $this->cfos->db('FINANCE_documents')->sum('FinancialInvoice_Amount', ['state' => 2]);

// Average
$avg = $this->cfos->db('CF_Users')->avg('age', ['country' => 'Spain']);

// Any aggregate function
$max = $this->cfos->db('FINANCE_deposits')->aggregateFunction('MAX', 'balance', ['active' => 1]);
$min = $this->cfos->db('FINANCE_deposits')->aggregateFunction('MIN', 'balance', ['active' => 1]);
```

---

### 2.10 Raw SQL Queries - `$this->cfos->dbQuery()`

For complex queries that cannot be expressed with array filters.

```php
// Simple query
$invoices = $this->cfos->dbQuery(
    'SELECT FinancialInvoice_Id FROM CF_FinancialInvoices
     WHERE FinancialInvoice_Automatic = 1
     AND FinancialInvoice_FinancialInvoicesState_Id = 1
     ORDER BY FinancialInvoice_DateOfInvoice ASC'
);
```

**Aggregation with GROUP BY:**
```php
$this->cfos->dbQuery("SET lc_time_names = 'es_ES'");

$monthly = $this->cfos->dbQuery(
    "SELECT
        CONCAT(UPPER(MONTHNAME(FinancialInvoice_DateOfInvoice)), ' ',
               RIGHT(CAST(YEAR(FinancialInvoice_DateOfInvoice) AS CHAR(4)), 2)) date,
        SUM(FinancialInvoice_Amount) total
     FROM CF_FinancialInvoices
     WHERE FinancialInvoice_DirectoryOrganization_Id = 116
       AND FinancialInvoice_FinancialInvoicesState_Id = 2
       AND FinancialInvoice_Amount > 0
     GROUP BY FinancialInvoice_DateOfInvoice
     ORDER BY FinancialInvoice_DateOfInvoice DESC
     LIMIT 12"
);
```

---

### 2.11 Joins

```php
$userDB = $this->cfos->db('CF_Users');
$roleDB = $this->cfos->db('CF_Roles');

// LEFT JOIN: CF_Users LEFT JOIN CF_Roles ON CF_Users.role_id = CF_Roles.id
$userDB->join('left', $roleDB, 'role_id', 'id');

// Then fetch normally
$results = $userDB->fetch(['CF_Users.status' => 'active']);
```

**Join types:** `'left'`, `'inner'`, `'right'`, `'full'`

---

### 2.12 Group By and Virtual Fields

```php
// Group by
$this->cfos->db('CF_FinancialInvoices')->setGroupBy('status, country');

// Virtual fields (computed columns)
$this->cfos->db('CF_Users')->addVirtualField('full_name', 'CONCAT(first_name, " ", last_name)');
```

---

### 2.13 Extra WHERE (Manual SQL)

For additional SQL conditions applied to all queries:

```php
$this->cfos->db('CF_Users')->setExtraWhere("created >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
$recent_users = $this->cfos->db('CF_Users')->fetch(['status' => 'active']);
// WHERE status = 'active' AND created >= DATE_SUB(NOW(), INTERVAL 30 DAY)
```

---

### 2.14 Connection Management

#### dbClose() - Close Connections

Always close database connections when done, especially in endpoints with multiple operations:

```php
if (!$this->useFunction('ENDPOINT_' . $method)) {
    $this->cfos->dbClose();
    return $this->setErrorFromCodelib('params-error', 'Endpoint not implemented');
} else {
    $this->cfos->dbClose();  // close after endpoint finishes
}
```

**Close all connections:**
```php
$this->cfos->dbClose();        // closes all connections
```

**Close specific connection:**
```php
$this->cfos->dbClose('production');  // close only 'production' connection
```

#### dbConnection() - Get Raw CloudSQL Object

```php
$db = $this->cfos->dbConnection();
// Direct CloudSQL methods available
```

---

### 2.15 Reset Query State

After each query, the DataSQL object retains its state (limit, order, where). Use `reset()` to clear:

```php
$this->cfos->db('CF_Users')->reset();
// Clears limit, page, offset, joins, where conditions, order, etc.
```

---

### 2.16 Timezone Handling

```php
// Set timezone for reading dates (output)
$this->cfos->db('CF_Users')->default_time_zone_to_read = 'Europe/Madrid';

// Set timezone for writing dates (input)
$this->cfos->db('CF_Users')->default_time_zone_to_write = 'UTC';
```

---

## Part 3: CFOs Configuration

### 3.1 Initialization (Typical API Pattern)

```php
// Modern fluent chain (CFAPI2024+)
$this->initAPI
    ->sendCorsHeaders('GET,POST,PUT,DELETE')
    ->platformParams(1)
    ->userIdParams(2)
    ->checkPlatformUserToken()
    ->initCFOs()                                              // initialize CFOs
    ->setDBConnectionFromSecret('FINANCE_DB_ACCESS.connection') // SQL credentials
    ->endPointParams(3);
```

### 3.2 Namespace Configuration

```php
// Set namespace (updates all cached DataStore objects)
$this->cfos->setNameSpace('my_tenant');

// Development environment (appends _dev to namespace)
$this->cfos->setDevEnvironment(true);   // namespace becomes 'my_tenant_dev'
$this->cfos->setDevEnvironment(false);  // namespace back to 'my_tenant'
```

### 3.3 Database Credentials

```php
// From platform secret
$this->cfos->setDBCredentialsFromPlatformSecret('FINANCE_DB_ACCESS.connection');

// Manual credentials
$this->cfos->setDBCredentials([
    'dbServer' => 'localhost',
    'dbUser' => 'root',
    'dbPassword' => 'secret',
    'dbName' => 'mydb',
    'dbPort' => '3306'
]);
```

### 3.4 Service Account (for Datastore/BigQuery)

```php
$this->cfos->setServiceAccount([
    'type' => 'service_account',
    'project_id' => 'my-project',
    'private_key' => '...',
    'client_email' => 'app@project.iam.gserviceaccount.com'
]);
```

### 3.5 CFO Secrets

```php
// Enable reading secrets from CFO definitions
$this->cfos->useCFOSecret(true);

// Or equivalently
$this->cfos->avoidSecrets(false);
```

---

## Part 4: Error Handling

### Standard Error Check Pattern

**Every** `ds()` or `db()` operation must be followed by an error check:

```php
$result = $this->cfos->ds('EntityName')->fetchOneByKey($id);
if ($this->cfos->ds('EntityName')->error)
    return $this->setErrorFromCodelib('system-error', $this->cfos->ds('EntityName')->errorMsg);
```

```php
$result = $this->cfos->db('TableName')->insert($data);
if ($this->cfos->db('TableName')->error)
    return $this->setErrorFromCodelib('system-error', $this->cfos->db('TableName')->errorMsg);
```

### Error Properties

| Property | Type | Description |
|----------|------|-------------|
| `->error` | `bool` | `true` if an error occurred |
| `->errorMsg` | `array\|string` | Error message(s) |
| `->errorCode` | `int` | HTTP status code (400, 401, 403, 404, 503) |

### Common Error Codes

```php
$this->setErrorFromCodelib('system-error', $msg);      // 503 - Internal error
$this->setErrorFromCodelib('datastore-error', $msg);    // 503 - Datastore error
$this->setErrorFromCodelib('db-error', $msg);           // 503 - Database error
$this->setErrorFromCodelib('not-found', $msg);          // 404 - Entity not found
$this->setErrorFromCodelib('params-error', $msg);       // 400 - Invalid parameters
$this->setErrorFromCodelib('security-error', $msg);     // 401 - Authentication error
```

---

## Quick Reference

### DataStore (`ds`) Methods

| Method | Description | Returns |
|--------|-------------|---------|
| `fetchOneByKey($key)` | Get entity by key | `array` |
| `fetchOne($fields, $where, $order)` | Get first matching entity | `array` |
| `fetchAll($fields, $where, $order)` | Get all matching entities | `array` |
| `fetchByKeys($keys)` | Get entities by key array | `array` |
| `fetchCount($where)` | Count matching entities | `int` |
| `createEntity($data)` | Create one entity | `mixed` |
| `createEntities($data)` | Create/upsert entities | `array` |
| `update($data, $key)` | Update entity by key | `bool` |
| `deleteByKeys($keys)` | Delete entities by keys | `mixed` |
| `delete($where)` | Delete by condition | `array\|bool` |
| `count($where)` | Aggregate count | `int` |
| `sum($field, $where)` | Aggregate sum | `int` |
| `avg($field, $where)` | Aggregate average | `int` |
| `query($gql, $bindings)` | Raw GQL query | `array` |
| `activateCache()` | Enable caching | `void` |
| `getCache($key)` | Get cached value | `mixed` |
| `setCache($key, $value)` | Set cached value | `void` |
| `resetCache()` | Clear all cache | `void` |
| `getEntityTemplate()` | Get empty entity | `array` |

### SQL (`db`) Methods

| Method | Description | Returns |
|--------|-------------|---------|
| `fetch($where, $fields)` | Get matching records | `array` |
| `fetchOne($where, $fields)` | Get first matching record | `array` |
| `fetchOneByKey($key)` | Get record by primary key | `array` |
| `fetchByKeys($keys)` | Get records by key array | `array` |
| `insert($data)` | Insert new record | `int\|false` |
| `update($data)` | Update record (must include PK) | `bool` |
| `upsert($data)` | Insert or update | `bool` |
| `delete($data)` | Delete record (must include PK) | `bool` |
| `count($fields, $where)` | Count records | `int` |
| `sum($field, $where)` | Sum a field | `int` |
| `avg($field, $where)` | Average a field | `int` |
| `aggregateFunction($fn, $field, $where)` | Any aggregate | `mixed` |
| `setOrder($field, $dir)` | Set ORDER BY | `void` |
| `addOrder($field, $dir)` | Add ORDER BY | `void` |
| `unsetOrder()` | Clear ORDER BY | `void` |
| `setLimit($n)` | Set LIMIT | `void` |
| `setPage($n)` | Set page number | `void` |
| `setOffset($n)` | Set OFFSET | `void` |
| `setGroupBy($fields)` | Set GROUP BY | `void` |
| `setExtraWhere($sql)` | Add raw WHERE clause | `void` |
| `join($type, $obj, $field, $joinField)` | SQL JOIN | `void` |
| `addVirtualField($name, $expr)` | Add computed column | `void` |
| `reset()` | Clear all query state | `void` |
| `getFields()` | Get field names | `array` |

### CFOs Configuration Methods

| Method | Description |
|--------|-------------|
| `setNameSpace($ns)` | Set Datastore namespace |
| `setProjectId($id)` | Set GCP project ID |
| `setServiceAccount($sa)` | Set service account credentials |
| `setDBCredentials($creds)` | Set database connection |
| `setDBCredentialsFromPlatformSecret($var)` | Set DB from platform secret |
| `setDevEnvironment($bool)` | Toggle development mode |
| `useCFOSecret($bool)` | Enable/disable CFO secrets |
| `dbQuery($sql, $params)` | Execute raw SQL |
| `dbClose($conn)` | Close DB connection(s) |
| `dbConnection($conn)` | Get raw CloudSQL object |
| `getCFOCodeObject($id)` | Load CFO code class |
