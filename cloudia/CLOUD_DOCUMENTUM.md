# CLOUD Documentum

**CLOUD Documentum** is CloudFramework's solution for documenting processes, technology, and organizational knowledge. It provides a structured way to describe, version, and maintain documentation across different domains.

## Overview

CLOUD Documentum consists of several documentation modules:

| Module | Description | CFOs |
|--------|-------------|------|
| **Development Groups** | Organizational groups for documentation elements | `CloudFrameWorkDevDocumentation` |
| **APIs** | REST API documentation with endpoints | `CloudFrameWorkDevDocumentationForAPIs`, `CloudFrameWorkDevDocumentationForAPIEndPoints` |
| **Libraries** | Code libraries, classes, and functions documentation | `CloudFrameWorkDevDocumentationForLibraries`, `CloudFrameWorkDevDocumentationForLibrariesModules` |
| **Processes** | Business and technical processes | `CloudFrameWorkDevDocumentationForProcesses`, `CloudFrameWorkDevDocumentationForSubProcesses` |
| **Checks** | Tests, objectives, and specifications linked to other documentation | `CloudFrameWorkDevDocumentationForProcessTests` |
| **Resources** | Infrastructure resources (tangible and intangible assets) | `CloudFrameWorkInfrastructureResources` |
| **Modules** | Menu configuration for platform solutions | `CloudFrameWorkModules` |
| **WebPages** | Web content pages for internal or public publishing | `CloudFrameWorkECMPages` |

All modules share a common lifecycle state system and backup infrastructure.

---

## Development Groups

**Development Groups** are the organizational backbone of CLOUD Documentum. They allow grouping related documentation elements (APIs, Libraries, Processes, WebApps, Courses, AI components, etc.) under a common umbrella for better organization and navigation.

### CloudFrameWorkDevDocumentation CFO

The **CloudFrameWorkDevDocumentation** CFO stores Development Group records. Each record represents a logical grouping of documentation elements.

**Key fields:**

| Field | Type | Description |
|-------|------|-------------|
| `KeyName` | keyname | Unique identifier for the group (min 4 chars) |
| `Title` | string | Display title of the development group |
| `Cat` | string | Category for grouping (autoselect) |
| `Status` | enum | Lifecycle status (0.DEFINED → 6.DEPRECATED) |
| `Owner` | string | Group owner (FK to CloudFrameWorkUsers) |
| `Introduction` | html | Brief HTML introduction |
| `Description` | html | Detailed HTML description |
| `Tags` | list | Search tags |
| `CloudFrameworkUser` | string | User who last modified |
| `DateUpdated` | datetime | Last update timestamp |
| `DateInsertion` | datetime | Creation timestamp |

### DocumentationId Relationship

Other documentation CFOs reference Development Groups through the **DocumentationId** field. This creates a hierarchical organization:

```
CloudFrameWorkDevDocumentation (Development Group)
       │
       │ KeyName
       │
       ├──> CloudFrameWorkDevDocumentationForAPIs.DocumentationId
       ├──> CloudFrameWorkDevDocumentationForLibraries.DocumentationId
       ├──> CloudFrameWorkDevDocumentationForProcesses.DocumentationId
       ├──> CloudFrameWorkDevDocumentationForWebApps.DocumentationId
       ├──> CloudFrameWorkECMPages.DocumentationId
       ├──> CloudFrameWorkAcademyCourses.DocumentationId
       ├──> CloudFrameWorkAIChatBots.DocumentationId
       ├──> CloudFrameWorkAIMCP.DocumentationId
       ├──> CloudFrameWorkAIPrompts.DocumentationId
       ├──> CloudFrameWorkAIRags.DocumentationId
       └──> CloudFrameWorkProjectsEntries.DocumentationId
```

### CFOs with DocumentationId

The following CFOs can be grouped under Development Groups:

| CFO | Module | Description |
|-----|--------|-------------|
| `CloudFrameWorkDevDocumentationForAPIs` | APIs | REST API documentation |
| `CloudFrameWorkDevDocumentationForLibraries` | Libraries | Code libraries and classes |
| `CloudFrameWorkDevDocumentationForProcesses` | Processes | Business/technical processes |
| `CloudFrameWorkDevDocumentationForWebApps` | WebApps | Web application documentation |
| `CloudFrameWorkECMPages` | WebPages | ECM content pages |
| `CloudFrameWorkAcademyCourses` | Academy | Training courses |
| `CloudFrameWorkAIChatBots` | AI | AI chatbot configurations |
| `CloudFrameWorkAIMCP` | AI | Model Context Protocol configs |
| `CloudFrameWorkAIPrompts` | AI | AI prompt templates |
| `CloudFrameWorkAIRags` | AI | RAG (Retrieval-Augmented Generation) configs |
| `CloudFrameWorkProjectsEntries` | Projects | Project entries |

### DocumentationId Field Configuration

In CFOs that support Development Groups, the DocumentationId field is configured as:

```json
"DocumentationId": {
    "name": "Development Group",
    "type": "autocomplete",
    "external_values": "datastore",
    "entity": "CloudFrameWorkDevDocumentation",
    "fields": "KeyName,Title",
    "linked_field": "KeyName",
    "allow_empty": true,
    "display_cfo": true,
    "cfo": "CloudFrameWorkDevDocumentation"
}
```

### Using Development Groups in Code

```php
// Fetch all Development Groups
$groups = $this->cfos->ds('CloudFrameWorkDevDocumentation')->fetchAll('*');

// Fetch APIs belonging to a Development Group
$apis = $this->cfos->ds('CloudFrameWorkDevDocumentationForAPIs')->fetch([
    'DocumentationId' => 'my-dev-group'
], '*');

// Fetch all documentation elements for a group
$documentationId = 'cloud-hrms';

$apis = $this->cfos->ds('CloudFrameWorkDevDocumentationForAPIs')->fetch(['DocumentationId' => $documentationId], '*');
$libraries = $this->cfos->ds('CloudFrameWorkDevDocumentationForLibraries')->fetch(['DocumentationId' => $documentationId], '*');
$processes = $this->cfos->ds('CloudFrameWorkDevDocumentationForProcesses')->fetch(['DocumentationId' => $documentationId], '*');
$webapps = $this->cfos->ds('CloudFrameWorkDevDocumentationForWebApps')->fetch(['DocumentationId' => $documentationId], '*');
$courses = $this->cfos->ds('CloudFrameWorkAcademyCourses')->fetch(['DocumentationId' => $documentationId], '*');
```

### Development Group Web Interface

- **Development Groups CFO**: `https://core20.web.app/ajax/cfo.html?api=/cfi/CloudFrameWorkDevDocumentation`

### Security Privileges

| Privilege | Description |
|-----------|-------------|
| `directory-admin` | Full access to Development Groups |
| `monitors-admin` | Administrative access to Development Groups |

---

## Visual Representation System

The visual representation of CLOUD Documentum content is rendered within the **CLOUD Platform** web application (`app.html`). When users access documentation through the platform, the `CloudECM` class generates HTML that is dynamically injected into the main content area (`#js-page-content`).

**Key components:**

| Component | Description |
|-----------|-------------|
| `app.html` | Main CLOUD Platform webapp that hosts all documentation views |
| `CloudECM` class | PHP class that generates HTML for each documentation type |
| `#js-page-content` | DOM node where generated HTML is injected |
| `CloudFrameWorkCFA` | JavaScript library that handles navigation and content loading |

**HTML Generation Functions:**

| Function | Renders |
|----------|---------|
| `htmlForAPIs()` | API listing dashboard |
| `htmlForAPI()` | Single API documentation |
| `htmlForProcesses()` | Process listing |
| `htmlForProcess()` | Single process documentation |
| `htmlForLibrary()` | Library/class documentation |
| `htmlForCourses()` | CLOUD Academy course dashboard |
| `htmlForCourse()` | Single course view |
| `htmlForECM()` | ECM page content |

For detailed information about the frontend architecture, CSS system, component patterns, and best practices for HTML generation, see [`CLOUD_PLATFORM.md`](buckets/backups/HTMLs/CLOUD_PLATFORM.md).

## Lifecycle States

All documentation entities follow a common lifecycle:

| State | Description |
|-------|-------------|
| `0.DEFINED` | Defined, pending development |
| `1.MOCKED` | With mocks for testing |
| `2.IN DEVELOPMENT` | Active development |
| `3.IN QA` | Testing phase |
| `4.WITH INCIDENCES` | Has reported issues |
| `5.RUNNING` | In production |
| `6.DEPRECATED` | Deprecated, pending removal |

---

## API Documentation

APIs are documented through two Datastore CFOs that capture both the API definition and its individual endpoints.

### CloudFrameWorkDevDocumentationForAPIs

Represents the description of a complete API.

**Key fields:**

| Field | Type | Description |
|-------|------|-------------|
| `KeyName` | string | API route (e.g., `/erp/projects`, `/core/alerts`) |
| `Title` | string | Descriptive title |
| `Description` | html | Detailed HTML description |
| `ExtraPath` | string | Additional path variables (e.g., `/:platform/:user`) |
| `Folder` | string | Grouping folder |
| `Cat` | string | Category |
| `Status` | enum | Lifecycle status |
| `TeamOwner` | string | API owner |
| `SourceCode` | string | URL to source code in GIT |
| `CFOs` | list | Related CFOs |
| `Libraries` | list | Used libraries |
| `JSONDATA` | json | Common config (testurl, headers, params) |

#### URL Composition

The full API URL is formed by concatenating `KeyName` + `ExtraPath`:
- `KeyName`: `/integrations/rubricae`, `ExtraPath`: `/:platform/:user`
- **Full URL**: `/integrations/rubricae/:platform/:user`

#### JSONDATA Structure

```json
{
    "path_variables": {
        "platform": {
            "_description": "Platform identifier",
            "_type": "string",
            "_example": "cloudframework"
        }
    },
    "headers": {
        "X-WEB-KEY": {
            "_description": "Application API Key",
            "_type": "string",
            "_mandatory": true,
            "_example": "abc123xyz"
        }
    }
}
```

**Special value `@cf:auth-headers`:** For APIs requiring standard CloudFramework authentication.

### CloudFrameWorkDevDocumentationForAPIEndPoints

Represents individual endpoints within an API.

> **Important:** When creating new ENDPOINTs in local backup files, do NOT include the `KeyId` field. When the API is updated/synced to the remote server, new ENDPOINTs will automatically generate their `KeyId` value. Only existing ENDPOINTs that were previously synced should retain their `KeyId` for proper identification and updates.

**Key fields:**

| Field | Type | Description |
|-------|------|-------------|
| `API` | string | Parent API KeyName |
| `EndPoint` | string | Endpoint path (e.g., `/check`, `/token`) |
| `Method` | enum | HTTP method (GET, POST, PUT, DELETE) |
| `Title` | string | Descriptive title |
| `Description` | html | Detailed description |
| `Status` | enum | Development status |
| `Private` | boolean | Excludes from public documentation |
| `PAYLOADJSON` | json | Expected payload definition |
| `RETURNEDJSON` | json | Response documentation with examples |

#### PAYLOADJSON Structure

```json
{
    "path_variables": {
        "idEmployee": {"_description": "Employee ID", "_type": "string", "_example": "123"}
    },
    "headers": {
        "X-Custom": {"_description": "Custom header", "_mandatory": true}
    },
    "params": {
        "page": {"_description": "Page number", "_type": "integer", "_example": "1"}
    },
    "body": {
        "name": {"_description": "Name", "_type": "string", "_mandatory": true},
        "status": {"_type": "enum", "_values": ["active", "inactive"]}
    }
}
```

#### RETURNEDJSON Structure

The `RETURNEDJSON` field documents API responses for different HTTP status codes. It uses the `documentations` object to organize responses by status.

```json
{
    "documentations": {
        "200 ok": {
            "id": {
                "_description": "Unique identifier",
                "_type": "integer",
                "_example": 12345
            },
            "name": {
                "_description": "User full name",
                "_type": "string",
                "_example": "John Doe"
            },
            "created_at": {
                "_description": "Creation timestamp",
                "_type": "datetime",
                "_example": "2025-01-15T10:30:00Z"
            },
            "metadata": {
                "tags": {
                    "_description": "Associated tags",
                    "_type": "array",
                    "_example": ["important", "reviewed"]
                },
                "status": {
                    "_description": "Current status",
                    "_type": "enum",
                    "_values": ["active", "pending", "archived"],
                    "_example": "active"
                }
            }
        },
        "400 bad-request": {
            "error": {
                "_description": "Error code",
                "_type": "string",
                "_example": "invalid-parameter"
            },
            "message": {
                "_description": "Human-readable error message",
                "_type": "string",
                "_example": "The 'id' parameter is required"
            }
        },
        "404 not-found": {
            "error": {
                "_description": "Error code",
                "_type": "string",
                "_example": "not-found"
            },
            "message": {
                "_description": "Resource not found message",
                "_type": "string",
                "_example": "User with id 12345 not found"
            }
        }
    },
    "success": {
        "id": 12345,
        "name": "John Doe"
    },
    "error": {
        "code": "invalid-parameter",
        "message": "Error description"
    }
}
```

**Field Properties:**

| Property | Type | Description | Example |
|----------|------|-------------|---------|
| `_description` | string | Human-readable field description | `"Invoice number"` |
| `_type` | string | Technical data type | `"integer"`, `"string"`, `"datetime"`, `"boolean"`, `"array"`, `"object"`, `"enum"` |
| `_example` | any | Sample value (mask sensitive data with ***) | `"INV-2023-***"` |
| `_values` | array | (Optional) List of allowed values for enums | `["EUR", "USD", "GBP"]` |

**Notes:**
- The `documentations` object contains response schemas organized by HTTP status (e.g., `"200 ok"`, `"400 bad-request"`)
- Nested objects are supported for documenting complex response structures
- Sensitive data in examples should be masked with `***`
- The `success` and `error` keys (outside `documentations`) provide quick JSON examples for testing

### API Backup System

**Location**: `buckets/backups/APIs/`

```
buckets/backups/APIs/
├── APIS.md                    # Documentation guide
├── cloudframework/            # CloudFramework APIs
│   ├── _core_alerts.json
│   ├── _erp_projects.json
│   └── ...
├── hipotecalia/               # Client APIs
└── ...
```

**Naming convention**: `/core/alerts` → `_core_alerts.json`

### API Management Scripts

```bash
# Backup all APIs from all platforms
composer run-script script _backup/apis/backup-from-remote

# CRUD operations for individual APIs
composer run-script script "_backup/apis/backup-from-remote\?id=:KeyName"
composer run-script script "_backup/apis/insert-from-backup?id=:KeyName"
composer run-script script "_backup/apis/update-from-backup?id=:KeyName"
composer run-script script _backup/apis/list-remote
composer run-script script _backup/apis/list-local
```

**Script location**: `scripts/_backup/api.php`

### API Web Interface

- **API Management**: `https://core20.web.app/ajax/cfo.html?api=/cfi/CloudFrameWorkDevDocumentationForAPIs`
- **Endpoint Management**: `https://core20.web.app/ajax/cfo.html?api=/cfi/CloudFrameWorkDevDocumentationForAPIEndPoints`

---

## Library Documentation

Libraries document code modules such as PHP classes, JavaScript modules, Python libraries, and their individual functions/methods. Each Library can have multiple Modules (functions/methods).

### CloudFrameWorkDevDocumentationForLibraries

Represents a code library, class, or module.

**Key fields:**

| Field | Type | Description |
|-------|------|-------------|
| `KeyName` | string | Library path (e.g., `/backend-core-php8/src/RESTful`, `/api-prod/class/CloudAcademy`) |
| `Title` | string | Library display name |
| `Description` | html | Detailed HTML description with code examples |
| `Type` | string | Library type: `class`, `function`, `module`, etc. |
| `DocumentationId` | string | Parent documentation group |
| `Folder` | string | Grouping folder (e.g., `FRAMEWORK-PHP8`, `CLOUD Academy`) |
| `Cat` | string | Category (e.g., `APIs`, `BACKEND-LIBRARIES`) |
| `Menu` | string | Menu display name |
| `Status` | enum | Lifecycle status |
| `SourceCode` | string | URL to source code in Git repository |
| `SourceCodeContent` | text | Full source code content |
| `Tags` | list | Search tags |
| `TeamOwner` | string | Library owner |
| `TeamAsignation` | string | Assigned developer |
| `TeamSupport` | string | Support contact |
| `JSON` | json | Additional structured data |

**Mandatory fields for creating a Process:**

```json
{
  "KeyName": "/classes/CloudECM",
  "Title": "Process Title",
  "Menu": "Menu Title",
  "Type": "class",
  "Folder": "CATEGORY",
  "Description": "<p>Description of the Library</p>",
  "Status": "SUBCATEGORY",
  "Tags": [],"/classes/CloudECM","CloudECM"],
  "TeamOwner": "owner@example.com",
  "DateInsertion": "now",
  "DateUpdated": "now",
  "CloudFrameworkUser": "creator@example.com"
}
```

### CloudFrameWorkDevDocumentationForLibrariesModules

Represents individual functions or methods within a library.

**Key fields:**

| Field | Type | Description |
|-------|------|-------------|
| `KeyId` | keyid | Auto-generated unique identifier |
| `Library` | string | Parent library KeyName |
| `EndPoint` | string | Function/method path (e.g., `/addMessage`, `/setReturnData`) |
| `Title` | string | Function/method title (e.g., `addMessage(..)`, `setReturnData(..)`) |
| `Description` | html | Detailed description with signature and parameters |
| `Status` | enum | Lifecycle status |
| `Tags` | list | Search tags |
| `TeamOwner` | string | Module owner |

### Library-Module Relationship

```
CloudFrameWorkDevDocumentationForLibraries (1) ──> (N) CloudFrameWorkDevDocumentationForLibrariesModules
       │                                                    │
       │ KeyName ←─────────────────────────── Library      │
```

### Library Backup System

**Location**: `buckets/backups/Libraries/`

```
buckets/backups/Libraries/
├── cloudframework/            # CloudFramework Libraries
│   ├── _backend-core-php8_src_RESTful.json
│   ├── _api-prod_class_CloudAcademy.json
│   ├── _js_classes_CloudFrameWorkCFO.json
│   └── ...
├── freeme/                    # Freeme client Libraries
├── laworatory/                # Laworatory client Libraries
└── ...
```

**Naming convention**: `/backend-core-php8/src/RESTful` → `_backend-core-php8_src_RESTful.json`

**JSON file structure:**

```json
{
    "CloudFrameWorkDevDocumentationForLibraries": {
        "KeyName": "/backend-core-php8/src/RESTful",
        "Title": "RESTful",
        "Type": "class",
        "Status": "5.RUNNING",
        "Folder": "FRAMEWORK-PHP8",
        "Cat": "APIs",
        "Description": "<pre>...</pre>",
        "SourceCode": "https://github.com/...",
        "SourceCodeContent": "<?php\n...",
        ...
    },
    "CloudFrameWorkDevDocumentationForLibrariesModules": [
        {
            "KeyId": "4561276677652480",
            "Library": "/backend-core-php8/src/RESTful",
            "EndPoint": "/addMessage",
            "Title": "addMessage(..)",
            "Description": "<pre>/**\n* Add a message...\n*/</pre>",
            "Status": "5.RUNNING",
            ...
        }
    ]
}
```

### Library Management Scripts

```bash
# Backup all Libraries from all platforms
composer run-script script backup_platforms_libraries

# CRUD operations for individual Libraries
composer run-script script "_cloudia/libraries/:platform/backup-from-remote?id=/backend-core-php8/src/RESTful"
composer run-script script "_cloudia/libraries/:platform/insert-from-backup?id=/api-prod/class/CloudAcademy"
composer run-script script "_cloudia/libraries/:platform/update-from-backup?id=/backend-core-php8/src/RESTful"
composer run-script script _cloudia/libraries/:platform/list-remote
composer run-script script _cloudia/libraries/:platform/list-local
```

**Script locations**:
- CRUD: `vendor/cloudframework-io/backend-core-php8/scripts/_cloudia/libraries.php`

### Library Web Interface

- **Library Management**: `https://core20.web.app/ajax/cfo.html?api=/cfi/CloudFrameWorkDevDocumentationForLibraries`
- **Module Management**: `https://core20.web.app/ajax/cfo.html?api=/cfi/CloudFrameWorkDevDocumentationForLibrariesModules`
- **Library Documentation View**: `https://app.cloudframework.app/app.html#__cfa?api=/erp/dev-docs?tag=library:{KeyName}`

---

## Process Documentation

Processes document business workflows, technical procedures, and operational processes. Each Process can have multiple SubProcesses.

### CloudFrameWorkDevDocumentationForProcesses

Represents the description of a complete Process.

**Key fields:**

| Field | Type | Mandatory | Description |
|-------|------|-----------|-------------|
| `KeyName` | string | **Yes** | Process identifier (e.g., `PROC-001`, `onboarding-employee`). Min 4 chars. |
| `Title` | string | **Yes** | Descriptive title |
| `Cat` | string | **Yes** | Category |
| `Subcat` | string | **Yes** | Subcategory |
| `Type` | string | **Yes** | Process type |
| `Owner` | string | **Yes** | Process owner (email) |
| `CloudFrameworkUser` | string | **Yes** | User who created/modified (auto-set) |
| `DocumentationId` | string | No | Parent documentation group |
| `Status` | enum | No | Lifecycle status (defaults to empty) |
| `AssignedTo` | list | No | Assigned users |
| `Introduction` | html | No | Brief introduction |
| `Description` | html | No | Detailed description |
| `Tags` | list | No | Search tags |
| `JSON` | json | No | Structured data with route references to Checks |
| `DateUpdated` | datetime | Auto | Last update timestamp (auto-set to now) |
| `DateInsertion` | datetime | Auto | Creation timestamp (auto-set to now) |

**Mandatory fields for creating a Process:**

```json
{
  "KeyName": "PROC-001",
  "Title": "Process Title",
  "Cat": "CATEGORY",
  "Subcat": "SUBCATEGORY",
  "Type": "TYPE",
  "Owner": "owner@example.com",
  "CloudFrameworkUser": "creator@example.com"
}
```

#### JSON Structure (with Route References)

The `JSON` field in Processes defines documentation objectives that link to Check records via the `route` property. Each route value corresponds to a Check's `Route` field.

**Required Format**: Each leaf node **must** be an object with a `route` property:
- **Key**: The title/label displayed in the UI
- **Value**: An object with `{"route": "<ruta>"}` where `<ruta>` matches the Check's `Route` field

**Flat structure:**
```json
{
    "Permisos de creación": {"route": "permissions"},
    "Filosofía de navegación": {"route": "/navigation"}
}
```

**Nested structure (with categories):**
```json
{
    "Análisis": {
        "Estudio de competencia": {"route": "/competitive-analisys"},
        "Elementos y módulos de HRMS": {"route": "/functions-modules"}
    },
    "Desarrollo": {
        "Entorno de desarrollo para HRMS": {"route": "/hrms-development"}
    },
    "Modelos de Datos": {
        "Tablas de configuración": {"route": "/data-models-hrms-config"}
    },
    "Esquema de aplicaciones": {
        "Figma": {"url": "https://www.figma.com/..."}
    }
}
```

**Additional properties** (non-route entries):
```json
{
    "notion": "https://notion.so/...",
    "source-code": "https://github.com/..."
}
```

### CloudFrameWorkDevDocumentationForSubProcesses

Represents individual SubProcesses within a Process.

**Key fields:**

| Field | Type | Mandatory | Description |
|-------|------|-----------|-------------|
| `KeyId` | keyid | Auto | Auto-generated unique identifier (do not set manually) |
| `Process` | string | **Yes** | Parent Process KeyName (FK to CloudFrameWorkDevDocumentationForProcesses) |
| `Title` | string | **Yes** | Descriptive title |
| `Folder` | string | **Yes** | Grouping folder |
| `Status` | enum | **Yes** | Lifecycle status |
| `TeamOwner` | string | **Yes** | SubProcess owner (email) |
| `CloudFrameworkUser` | string | **Yes** | User who created/modified (auto-set) |
| `Cat` | string | No | Category |
| `EndPoint` | string | No | Related URL |
| `Deadline` | date | No | Target completion date |
| `TeamAsignation` | string | No | Assigned user |
| `Description` | html | No | Detailed description |
| `Documents` | string | No | Associated documents (server_documents) |
| `Tags` | list | No | Search tags |
| `JSON` | json | No | Structured data with route references to Checks |
| `WebApps` | list | No | Related WebApps |
| `Libraries` | list | No | Related Libraries |
| `APIs` | list | No | Related APIs |
| `ENDPOINTs` | list | No | Related Endpoints |
| `DateUpdated` | datetime | Auto | Last update timestamp (auto-set to now) |
| `DateInsertion` | datetime | Auto | Creation timestamp (auto-set to now) |

**Mandatory fields for creating a SubProcess:**

```json
{
  "Process": "PROC-001",
  "Title": "SubProcess Title",
  "Folder": "FOLDER_NAME",
  "Status": "0.DEFINED",
  "TeamOwner": "owner@example.com",
  "CloudFrameworkUser": "creator@example.com"
}
```

**Important:** Never include `KeyId` when creating a new SubProcess manually - the system will auto-generate it.

#### JSON Structure (with Route References)

The `JSON` field in SubProcesses also supports route references that link to Check records, similar to Processes.

**Required Format**: Each leaf node **must** be an object with a `route` property:
- **Key**: The title/label displayed in the UI
- **Value**: An object with `{"route": "<ruta>"}` where `<ruta>` matches the Check's `Route` field

**Simple structure:**
```json
{
    "Accesos y privilegios": {"route": "privileges"},
    "Ejemplos de Uso": {"route": "examples"},
    "Logs de funcionalidad": {"route": "/logs"}
}
```

**Nested structure:**
```json
{
    "Dashboard General": {"route": "/dashboard"},
    "Apps y Modulos": {
        "Relacionando Milestones con Apps": {"route": "/milestones-apps"}
    }
}
```

**Legacy scenarios format** (still supported):
```json
{
    "Category of Scenarios": {
        "Scenario Title": {
            "status": "ok|pending",
            "description": "Description of the scenario",
            "deadline": "2025-01-01"
        }
    }
}
```

### Process-SubProcess Relationship

```
CloudFrameWorkDevDocumentationForProcesses (1) ──> (N) CloudFrameWorkDevDocumentationForSubProcesses
       │                                                    │
       │ KeyName ←─────────────────────────── Process      │
```

### Process Backup System

**Location**: `buckets/backups/Processes/`

```
buckets/backups/Processes/
├── PROCESSES.md               # Documentation guide
├── cloudframework/            # CloudFramework Processes
│   ├── PROC-001.json
│   ├── onboarding-employee.json
│   └── ...
├── hipotecalia/               # Client Processes
└── ...
```

**JSON file structure:**

```json
{
    "CloudFrameWorkDevDocumentationForProcesses": {
        "KeyName": "PROC-001",
        "Title": "Onboarding Process",
        "Status": "5.RUNNING",
        "Cat": "OPERATIONS",
        ...
    },
    "CloudFrameWorkDevDocumentationForSubProcesses": [
        {
            "KeyId": "5123456789012345",
            "Process": "PROC-001",
            "Title": "Initial employee registration",
            "Status": "5.RUNNING",
            ...
        }
    ]
}
```
When a subprocess is created manually, never insert 'KeyId' because the system will generate the field automatically
in the remote platform

### Process Management Scripts

```bash
# Backup all Processes from all platforms
composer run-script script _backup/processes/backup-from-remote

# CRUD operations for individual Processes
composer run-script script "_backup/processes/backup-from-remote?id=:KeyName"
composer run-script script "_backup/processes/insert-from-backup?id=:KeyName"
composer run-script script "_backup/processes/update-from-backup?id=:KeyName"
composer run-script script _backup/processes/list-remote
composer run-script script _backup/processes/list-local
```

**Script locations**:
- Backup: `scripts/_backup/processes.php`

### Process Web Interface

- **Process Management**: `https://core20.web.app/ajax/cfo.html?api=/cfi/CloudFrameWorkDevDocumentationForProcesses`
- **SubProcess Management**: `https://core20.web.app/ajax/cfo.html?api=/cfi/CloudFrameWorkDevDocumentationForSubProcesses`

---

## Check Documentation (ProcessTests)

Checks allow creating information objectives, tests, or specifications linked to other documentation objects. They are useful for:
- Defining functional or technical objectives
- Specifying features to implement
- Creating user acceptance tests
- Documenting acceptance criteria

### Linking System

Checks are linked to other documentation CFOs through three fields:

| Field | Description |
|-------|-------------|
| `CFOEntity` | KeyName of the CFO to which the check is linked |
| `CFOField` | Name of the JSON field within the CFO |
| `CFOId` | KeyName or KeyId of the specific record |

**Example:**
```
CFOEntity: CloudFrameWorkDevDocumentationForProcesses
CFOField: JSON
CFOId: /cloud-hrms
```

### Route Mechanism

The **Route** field in Checks corresponds to `"route"` values in the **JSON** field of Processes/SubProcesses. This creates a bidirectional link:

1. **Process/SubProcess JSON** defines documentation objectives with route references
2. **Check Route** field matches those route values to provide detailed documentation

**Example - Process `/cloud-hrms`:**

Process JSON field:
```json
{
    "Análisis": {
        "Estudio de competencia": {"route": "/competitive-analisys"},
        "Elementos y módulos de HRMS": {"route": "/functions-modules"}
    },
    "Desarrollo": {
        "Entorno de desarrollo para HRMS": {"route": "/hrms-development"}
    },
    "Modelos de Datos": {
        "Tablas de configuración": {"route": "/data-models-hrms-config"}
    }
}
```

Corresponding Checks:

| Check Route | Check Title | Status |
|-------------|-------------|--------|
| `/competitive-analisys` | Estudio de competencia | ok |
| `/functions-modules` | Elementos y módulos de HRMS | ok |
| `/hrms-development` | Entorno de desarrollo de HRMS | ok |
| `/data-models-hrms-config` | Tablas de configuración HRMS | ok |

**Example - SubProcess `5974784620363776`:**

SubProcess JSON field:
```json
{
    "Accesos y privilegios": {"route": "privileges"},
    "Ejemplos de Uso": {"route": "examples"},
    "Logs de funcionalidad": {"route": "/logs"}
}
```

Corresponding Checks:

| Check Route | Check Title | Status |
|-------------|-------------|--------|
| `privileges` | Accesos y privilegios | blocked |
| `examples` | Ejemplos de Uso | ok |
| `/logs` | Logs de funcionalidad | ok |

This mechanism provides a **documentation completion tracking system** where:
- Processes/SubProcesses define what needs to be documented
- Checks track progress with statuses (`pending`, `in-progress`, `blocked`, `in-qa`, `ok`)
- Assignees and due dates enable task management

### CloudFrameWorkDevDocumentationForProcessTests

**Key fields:**

| Field | Type | Description |
|-------|------|-------------|
| `KeyId` | keyid | Auto-generated unique identifier |
| `Route` | string | Route identifier that matches `"route"` values in Process/SubProcess JSON |
| `CFOEntity` | string | Linked CFO KeyName |
| `CFOField` | string | JSON field in the CFO |
| `CFOId` | string | KeyName/KeyId of linked record |
| `Title` | string | Descriptive title |
| `Status` | enum | Check status (see below) |
| `Owner` | string | Owner |
| `AssignedTo` | list | Assigned users |
| `Description` | html | Objective or description |
| `Results` | html | Obtained results |
| `DateDueDate` | date | Due date |
| `Open` | boolean | Whether the check is open |
| `Documents` | string | Attached documents |
| `Tags` | list | Search tags |
| `JSON` | json | Extra structured data |

### Check States

| State | Description |
|-------|-------------|
| `pending` | Pending definition |
| `in-progress` | In progress |
| `blocked` | Blocked |
| `in-qa` | In QA |
| `ok` | Finished (OK) |

**Delete rule:** Only checks with `Status == 'pending'` can be deleted.

### JSON Field Structure for Displaying Checks

**IMPORTANT**: For Checks to be visible in the interface, the parent entity (Process, SubProcess, WebApp, etc.) must define the Check structure in its `JSON` field. The `JSON` field acts as an index/table of contents that links to the actual Check records stored in `CloudFrameWorkDevDocumentationForProcessTests`.

**Structure:**

```json
{
    "Category Name": {
        "Check Title": {
            "route": "/route-identifier"
        },
        "Another Check Title": {
            "route": "/another-route"
        }
    },
    "Another Category": {
        "Third Check": {
            "route": "/third-route"
        }
    }
}
```

**How it works:**

1. **JSON Field in Parent Entity**: Define categories and check titles with their `route` values
2. **CloudFrameWorkDevDocumentationForProcessTests Records**: Create Check records with matching `Route` field values
3. **Linking**: The `CFOEntity` and `CFOId` fields in Checks link them to the parent entity
4. **Display**: The interface uses the JSON structure to organize and display linked Checks

**Example for a SubProcess:**

```json
// SubProcess JSON field
{
    "Conceptos Básicos": {
        "1. Introducción": {
            "route": "/introduction"
        },
        "2. Estructura de datos": {
            "route": "/data-structure"
        }
    },
    "Operaciones": {
        "3. Gestión desde interfaz": {
            "route": "/management"
        }
    }
}
```

The corresponding Checks in `CloudFrameWorkDevDocumentationForProcessTests` must have:
- `CFOEntity`: `CloudFrameWorkDevDocumentationForSubProcesses`
- `CFOId`: The SubProcess KeyId (e.g., `5761597392814080`)
- `Route`: Matching route value (e.g., `/introduction`, `/data-structure`, `/management`)

**Key Points:**
- The `route` value in JSON must match the `Route` field in the Check record
- Categories in JSON are used for grouping in the interface
- Check titles in JSON are display names (the Check record has its own `Title` field)
- Without the JSON structure, Checks exist in the database but won't appear in the interface view

### Check Backup System

**Location**: `buckets/backups/Checks/`

```
buckets/backups/Checks/
├── CHECKS.md                  # Documentation guide
├── cloudframework/            # CloudFramework Checks
│   ├── CloudFrameWorkDevDocumentationForProcesses__PROC-001.json
│   ├── CloudFrameWorkDevDocumentationForAPIs___erp_projects.json
│   └── ...
├── hipotecalia/               # Client Checks
└── ...
```

**Naming convention**: `{CFOEntity}__{CFOId}.json`

**JSON file structure:**

```json
{
    "CFOEntity": "CloudFrameWorkDevDocumentationForProcesses",
    "CFOId": "PROC-001",
    "CloudFrameWorkDevDocumentationForProcessTests": [
        {
            "KeyId": "5123456789012345",
            "CFOEntity": "CloudFrameWorkDevDocumentationForProcesses",
            "CFOId": "PROC-001",
            "Title": "Verify onboarding flow",
            "Status": "in-progress",
            ...
        }
    ]
}
```

### Check Management Scripts

```bash
# Backup all Checks from all platforms
composer run-script script _backup/check/backup-from-remote

# CRUD operations for individual Checks (grouped by CFOEntity and CFOId)
composer run-script script "_backup/check/backup-from-remote?entity=CFOEntity&id=CFOId"
composer run-script script "_backup/check/insert-from-backup?entity=CFOEntity&id=CFOId"
composer run-script script "_backup/check/update-from-backup?entity=CFOEntity&id=CFOId"
composer run-script script _backup/check/list-remote
composer run-script script _backup/check/list-local
```

**Examples:**

```bash
# Backup checks linked to process PROC-001
composer run-script script "_cloudia/check/cloudframework/backup-from-remote?entity=CloudFrameWorkDevDocumentationForProcesses&id=PROC-001"

# Backup checks linked to API /erp/projects
composer run-script script "_cloudia/check/cloudframework/backup-from-remote?entity=CloudFrameWorkDevDocumentationForAPIs&id=/erp/projects"
```

**Script locations**:
- Backup: `buckets/scripts/backup_platforms_checks.php`
- CRUD: `vendor/cloudframework-io/backend-core-php8/scripts/_cloudia/check.php`

### Check Web Interface

- **Check Management**: `https://core20.web.app/ajax/cfo.html?api=/cfi/CloudFrameWorkDevDocumentationForProcessTests`

---

## Infrastructure Resources

Infrastructure Resources document organizational assets that are under the organization's control. This includes both:

- **Tangible assets**: Computers, servers, devices, hardware equipment
- **Intangible assets**: Domains, databases, web servers, cloud services, certificates, subscriptions

Resources are part of CLOUD Documentum and accessible through the CLOUD Development menu under "Resources" section.

### CloudFrameWorkInfrastructureResources

**Key fields:**

| Field | Type | Description |
|-------|------|-------------|
| `KeyName` | keyname | Unique identifier for the resource (min 1 char, lowercase) |
| `ResourceParent` | string | Parent resource KeyName (for hierarchical organization) |
| `Category` | string | Resource category (e.g., `Hardware`, `Cloud Services`, `Domains`) |
| `Type` | string | Resource type (min 3 chars, e.g., `Server`, `Database`, `Domain`) |
| `Description` | string | Brief description of the resource |
| `HTMLDescription` | string | Detailed HTML description |
| `Tags` | list | Search tags for categorization |
| `Structure` | json | Structured data about the resource configuration |
| `MonthlyPrice` | float | Monthly cost/price of the resource |
| `ResourceContent` | json | Additional content and metadata |
| `ExpirationDate` | date | Expiration or renewal date (for domains, certificates, subscriptions) |
| `Active` | boolean | Whether the resource is currently active |

### Resource Categories

Common categories for organizing resources:

| Category | Examples |
|----------|----------|
| `Hardware` | Servers, laptops, network equipment |
| `Cloud Services` | GCP projects, AWS accounts, Azure subscriptions |
| `Domains` | Domain names, DNS records |
| `Databases` | Cloud SQL, Datastore, BigQuery datasets |
| `Certificates` | SSL/TLS certificates, code signing certificates |
| `Software` | Licenses, subscriptions, SaaS tools |
| `Storage` | Cloud Storage buckets, backup systems |

### Resource Hierarchy

Resources can be organized hierarchically using the `ResourceParent` field:

```
Organization (KeyName: "org-cloudframework")
├── Infrastructure (ResourceParent: "org-cloudframework")
│   ├── GCP Project (ResourceParent: "infrastructure")
│   │   ├── App Engine (ResourceParent: "gcp-project")
│   │   ├── Cloud SQL (ResourceParent: "gcp-project")
│   │   └── Cloud Storage (ResourceParent: "gcp-project")
│   └── Domain (ResourceParent: "infrastructure")
│       └── SSL Certificate (ResourceParent: "domain")
```

### Resource Backup System

**Location**: `buckets/backups/Resources/`

```
buckets/backups/Resources/
├── cloudframework/            # CloudFramework Resources
│   ├── server-production.json
│   ├── domain-cloudframework-io.json
│   ├── gcp-project-cloudframework.json
│   ├── _all_resources.json    # Consolidated backup with metadata
│   └── ...
├── hipotecalia/               # Client Resources
└── ...
```

**JSON file structure:**

```json
{
    "KeyName": "server-production",
    "ResourceParent": "infrastructure",
    "Category": "Hardware",
    "Type": "Server",
    "Description": "Production application server",
    "HTMLDescription": "<p>Main production server...</p>",
    "Tags": ["production", "critical"],
    "Structure": {
        "cpu": "8 cores",
        "ram": "32GB",
        "storage": "500GB SSD"
    },
    "MonthlyPrice": 150.00,
    "ResourceContent": {
        "ip": "10.0.0.1",
        "hostname": "prod-server-01"
    },
    "ExpirationDate": null,
    "Active": true
}
```

### Resource Management Scripts

```bash
# Backup all Resources from all platforms
composer run-script script backup_platforms_resources

# CRUD operations for individual Resources
composer run-script script "_cloudia/resources/:platform/list-remote"
composer run-script script "_cloudia/resources/:platform/list-local"
composer run-script script "_cloudia/resources/:platform/backup-from-remote?id=resource-key"
composer run-script script "_cloudia/resources/:platform/backup-from-remote"  # all resources
composer run-script script "_cloudia/resources/:platform/update-from-backup?id=resource-key"
composer run-script script "_cloudia/resources/:platform/insert-from-backup?id=resource-key"
```

**Script locations**:
- CRUD: `vendor/cloudframework-io/backend-core-php8/scripts/_cloudia/resources.php`

### Using Resources in Code

```php
// Fetch all resources
$resources = $this->cfos->ds('CloudFrameWorkInfrastructureResources')->fetchAll('*');

// Fetch active resources by category
$servers = $this->cfos->ds('CloudFrameWorkInfrastructureResources')->fetch([
    'Category' => 'Hardware',
    'Active' => true
], '*');

// Fetch resources expiring soon
$expiringResources = $this->cfos->ds('CloudFrameWorkInfrastructureResources')->fetch([
    'ExpirationDate' => ['<=', date('Y-m-d', strtotime('+30 days'))]
], '*');

// Fetch child resources
$childResources = $this->cfos->ds('CloudFrameWorkInfrastructureResources')->fetch([
    'ResourceParent' => 'parent-resource-key'
], '*');
```

### Resource Web Interface

- **Resource Management**: `https://core20.web.app/ajax/cfo.html?api=/cfi/CloudFrameWorkInfrastructureResources`
- **Resource Accesses**: `https://core20.web.app/ajax/cfo.html?api=/cfi/CloudFrameWorkInfrastructureResourcesAccesses`

### Related CFOs

| CFO | Description |
|-----|-------------|
| `CloudFrameWorkInfrastructureResources` | Main resource records |
| `CloudFrameWorkInfrastructureResourcesAccesses` | Access permissions and credentials for resources |

### Security Privileges

| Privilege | Description |
|-----------|-------------|
| `directory-admin` | Full access to Infrastructure Resources |
| `documents-admin` | Administrative access to Resources |

---

## Modules (Menu Configuration)

Modules define the navigation menu structure for different platform solutions. Each module contains a JSON configuration that specifies menu items, templates, icons, and security privileges for different user roles.

### CloudFrameWorkModules

**Key fields:**

| Field | Type | Description |
|-------|------|-------------|
| `KeyName` | keyname | Module identifier (unique key) |
| `ModuleName` | string | Display name for the module |
| `Active` | boolean | Whether the module is enabled |
| `JSON` | json | Menu structure configuration |
| `Fingerprint` | json | Internal tracking data |
| `DateInsertion` | datetime | Creation timestamp |
| `DateUpdating` | datetime | Last modification timestamp |

### Menu JSON Structure

The `JSON` field contains the complete menu configuration:

```json
{
    "menu": {
        "_template": "https://core20.web.app/ajax/dashboard.html",
        "_icon": "fa-chess",
        "_security": {
            "user_privileges": ["module-admin", "module-user"]
        },
        "Dashboard": {
            "_code": "module-dashboard",
            "_template": "https://core20.web.app/ajax/module_dashboard.html",
            "_icon": "fa-tachometer-alt",
            "_security": {
                "user_privileges": ["module-admin", "module-user"]
            }
        },
        "Settings": {
            "_code": "module-settings",
            "_template": "https://core20.web.app/ajax/module_settings.html",
            "_icon": "fa-cog",
            "_security": {
                "user_privileges": ["module-admin"]
            }
        }
    }
}
```

### Menu Configuration Properties

| Property | Description |
|----------|-------------|
| `_template` | URL to the HTML template for the menu item |
| `_icon` | FontAwesome icon class (e.g., `fa-chess`, `fa-cog`) |
| `_code` | Internal code identifier for the menu item |
| `_security.user_privileges` | Array of privileges required to see/access the menu item |

### Web Interface

- **Module Management**: `https://core20.web.app/ajax/cfo.html?api=/cfi/CloudFrameWorkModules`

### Security Privileges

| Privilege | Description |
|-----------|-------------|
| `_superadmin_` | Full system access |
| `platform-admin` | Platform administration access |
| `development-admin` | Development administration access |
| `CFDE` | CloudFramework Development Environment access |

### Example: Creating a New Module

```php
// Create a new module for a CRM solution
$moduleData = [
    'KeyName' => 'crm-module',
    'ModuleName' => 'CRM Solution',
    'Active' => true,
    'JSON' => [
        'menu' => [
            '_template' => 'https://core20.web.app/ajax/crm_dashboard.html',
            '_icon' => 'fa-users',
            '_security' => [
                'user_privileges' => ['crm-admin', 'crm-user']
            ],
            'Contacts' => [
                '_code' => 'crm-contacts',
                '_template' => 'https://core20.web.app/ajax/crm_contacts.html',
                '_icon' => 'fa-address-book'
            ],
            'Deals' => [
                '_code' => 'crm-deals',
                '_template' => 'https://core20.web.app/ajax/crm_deals.html',
                '_icon' => 'fa-handshake'
            ]
        ]
    ]
];

$this->cfos->ds('CloudFrameWorkModules')->insert($moduleData);
```

---

## WebPages (ECM Pages)

WebPages provide a web content management system for creating, managing, and publishing web pages. Pages can be used internally within the platform or exposed publicly for external access. They support rich HTML content, structured JSON data, and integration with documentation systems.

### CloudFrameWorkECMPages

**Key fields:**

| Field | Type | Description |
|-------|------|-------------|
| `KeyId` | keyid | Page unique identifier (auto-generated) |
| `PageRoute` | string | Internal route path (unique, e.g., `/cfos/CloudFrameWorkCFOs`) |
| `PageTitle` | string | Internal page title |
| `PageIco` | string | FontAwesome icon name |
| `PageHTML` | zip | HTML content (compressed for storage efficiency) |
| `JSON` | json | Structured content data |
| `MultiPlatform` | boolean | Whether page is available across platforms |
| `Public` | boolean | Whether page is publicly accessible |
| `Status` | string | Development status (see below) |
| `Tags` | list | Tags for categorization and search |
| `PublicPageRoutes` | list | Public URL routes (unique) |
| `PublicRouteTree` | string | Public navigation tree path |
| `PublicTitle` | string | Public-facing page title |
| `PublicMeta` | json | SEO meta tags (title, description, keywords) |
| `DocumentationId` | string | FK to CloudFrameWorkDevDocumentation |
| `CloudFrameworkUser` | string | User who last modified |
| `DateInsertion` | datetime | Creation timestamp |
| `DateUpdating` | datetime | Last update timestamp |

### Page Status Values

| Status | Description |
|--------|-------------|
| `new` | New page, not yet assigned |
| `pending` | Pending development |
| `in-development` | Currently being developed |
| `with-issues` | Has reported issues |
| `ok` | Finished and ready |

### PublicMeta Structure

```json
{
  "title": "Page Title for SEO",
  "description": "Page description for search engines",
  "keywords": ["keyword1", "keyword2"],
  "og:image": "https://example.com/image.png"
}
```

### WebPage Backup System

**Location**: `buckets/backups/WebPages/`

```
buckets/backups/WebPages/
├── cloudframework/           # CloudFramework platform pages
│   ├── _cfos_CloudFrameWorkCFOs.json
│   ├── _erp_dashboard.json
│   └── ...
├── hipotecalia/             # Client pages
└── ...
```

**Naming convention**: `/cfos/CloudFrameWorkCFOs` → `_cfos_CloudFrameWorkCFOs.json`

**JSON file structure:**

```json
{
  "CloudFrameWorkECMPages": {
    "KeyId": "5123456789012345",
    "PageRoute": "/cfos/CloudFrameWorkCFOs",
    "PageTitle": "CFO Documentation",
    "PageHTML": "<compressed HTML content>",
    "JSON": {...},
    "Public": true,
    "PublicPageRoutes": ["/documentation/cfos"],
    "Status": "ok"
  }
}
```

### WebPage Management Scripts

```bash
# Backup all WebPages from all platforms
composer run-script script backup_platforms_webpages
```

**Script location**: `buckets/scripts/backup_platforms_webpages.php`

### Using WebPages in Code

```php
// Fetch all pages
$pages = $this->cfos->ds('CloudFrameWorkECMPages')->fetchAll('*');

// Fetch public pages only
$publicPages = $this->cfos->ds('CloudFrameWorkECMPages')->fetch([
    'Public' => true
], '*');

// Fetch page by route
$page = $this->cfos->ds('CloudFrameWorkECMPages')->fetchOne('*', [
    'PageRoute' => '/cfos/CloudFrameWorkCFOs'
]);

// Fetch pages by documentation group
$docPages = $this->cfos->ds('CloudFrameWorkECMPages')->fetch([
    'DocumentationId' => $documentationId
], '*');
```

### WebPage Web Interface

- **ECM Page viewer**: `https://core20.web.app/ajax/ecm.html?page={PageRoute}`
- **ECM Pages CFO**: `https://core20.web.app/ajax/cfo.html?api=/cfi/CloudFrameWorkECMPages`
- **Public pages example**: `https://cloudframework.io/developers/training{PublicPageRoute}`

### Key Features

- Rich HTML content with WYSIWYG editing
- Structured JSON data for dynamic content
- Multi-platform page sharing
- Public/private visibility control
- SEO meta tags for public pages
- Integration with development documentation
- Version tracking via DateUpdating
- Tag-based organization and search

---

## CloudECM Library

The **CloudECM** class is the core rendering engine for CLOUD Documentum. It transforms documentation data from CFOs into HTML representations for display in the CLOUD Platform web interface.

**Location**: `buckets/cloudframework.io/api-dev/class/CloudECM.php`

### Purpose

CloudECM provides:
1. **HTML Rendering**: Converts CFO documentation records into formatted HTML
2. **CF Tag Transformation**: Processes special CloudFramework tags (`[CF:tag:id]`) embedded in content
3. **Navigation Generation**: Creates links, breadcrumbs, and table of contents
4. **Related Objects**: Discovers and displays relationships between documentation entities

### Key Methods

| Method | Description | CFO Source |
|--------|-------------|------------|
| `htmlForAPIs()` | Renders list of APIs by folder | `CloudFrameWorkDevDocumentationForAPIs` |
| `htmlForAPI()` | Renders single API with endpoints | `CloudFrameWorkDevDocumentationForAPIs`, `CloudFrameWorkDevDocumentationForAPIEndPoints` |
| `htmlForEndPoint()` | Renders single endpoint details | `CloudFrameWorkDevDocumentationForAPIEndPoints` |
| `htmlForProcesses()` | Renders list of processes | `CloudFrameWorkDevDocumentationForProcesses` |
| `htmlForProcess()` | Renders single process with subprocesses | `CloudFrameWorkDevDocumentationForProcesses`, `CloudFrameWorkDevDocumentationForSubProcesses` |
| `htmlForSubprocess()` | Renders single subprocess | `CloudFrameWorkDevDocumentationForSubProcesses` |
| `htmlForChecks()` | Renders checks linked to an entity | `CloudFrameWorkDevDocumentationForProcessTests` |
| `htmlForECM()` | Renders ECM page content | `CloudFrameWorkECMPages` |
| `htmlForCourse()` | Renders Academy course | `CloudFrameWorkAcademyCourses` |
| `htmlForWebApp()` | Renders WebApp documentation | `CloudFrameWorkDevDocumentationForWebApps` |
| `htmlForLibrary()` | Renders Library documentation | `CloudFrameWorkDevDocumentationForLibraries` |
| `htmlForCFO()` | Renders CFO documentation | `CloudFrameWorkCFOsLocal` |
| `htmlForLocalizations()` | Renders localization strings | `CloudFrameWorkLocalizations` |

### CF Tags

CloudECM processes special tags embedded in HTML content. Tags follow the format `[CF:type:id]` or `[CF:type:id:props]`.

**Common Tags:**

| Tag | Example | Description |
|-----|---------|-------------|
| `[CF:api-link:id]` | `[CF:api-link:/erp/projects]` | Link to API documentation |
| `[CF:endpoint-link:id]` | `[CF:endpoint-link:5123456789]` | Link to endpoint documentation |
| `[CF:process-link:id]` | `[CF:process-link:PROC-001]` | Link to process documentation |
| `[CF:subprocess-link:id]` | `[CF:subprocess-link:5123456789]` | Link to subprocess |
| `[CF:cfo-link:id]` | `[CF:cfo-link:MP_HIPOTECH_Clients]` | Link to CFO documentation |
| `[CF:library-link:id]` | `[CF:library-link:CloudHIPOTECHV2]` | Link to library documentation |
| `[CF:course-link:id]` | `[CF:course-link:5123456789]` | Link to Academy course |
| `[CF:ecm-links:route]` | `[CF:ecm-links:/docs/intro]` | Embedded ECM page content |
| `[CF:icon:name]` | `[CF:icon:check]` | FontAwesome icon |
| `[CF:youtube:id]` | `[CF:youtube:dQw4w9WgXcQ]` | Embedded YouTube video |
| `[CF:iframe:url]` | `[CF:iframe:https://example.com]` | Embedded iframe |
| `[CF:external-link:url]` | `[CF:external-link:https://docs.example.com]` | External link with icon |

### Usage in Code

```php
// Load CloudECM class
$cloudECM = $this->core->loadClass('CloudECM', $this->cfos);

// Render API documentation
$html = $cloudECM->htmlForAPI('/cloud-solutions/hipotech/documents', 'content_label');

// Render process documentation
$html = $cloudECM->htmlForProcess('PROC-001', 'content_label');

// Transform CF tags in content
$content = "<p>See [CF:api-link:/erp/projects] for details</p>";
$cloudECM->transformCFTags($content);
```

### CloudECM Testing Script

A local testing script allows verification of CloudECM methods before deploying to the remote development environment.

**Location**: `buckets/scripts/libraries/cloudECM.php`

**Available Methods by Category:**

| Category | Methods |
|----------|---------|
| **CLOUD Academy** | `htmlForCourses`, `htmlForCourse` |
| **APIs** | `htmlForAPIs`, `htmlForAPI`, `htmlForEndPoint` |
| **Processes** | `htmlForProcesses`, `htmlForProcess`, `htmlForProcessIntro`, `htmlForSubprocess` |
| **Libraries** | `htmlForLibrary`, `htmlForLibraryModule` |
| **WebApps** | `htmlForWebApp`, `htmlForWebAppModule` |
| **Checks** | `htmlForCheck`, `htmlForChecks` |
| **ECM Pages** | `htmlForECM`, `htmlForCompanyECM`, `htmlForPublicECM` |
| **Dev Documents** | `htmlForDevDocuments`, `htmlForDevDocument` |
| **CFOs** | `htmlForCFO`, `htmlForCFOWorkFlow` |
| **Localizations** | `htmlForLocalizations`, `htmlForLocalization` |
| **Projects** | `htmlForProject`, `htmlForMilestone` |
| **Other** | `htmlForResource`, `htmlForTicket`, `htmlForEmailTemplate`, `transformCFTags` |

**Usage Examples:**

```bash
# Basic testing - list available methods
composer run-script script libraries/cloudECM/default

# Test CLOUD Academy courses dashboard
composer run-script script libraries/cloudECM/htmlForCourses

# Test API documentation with ID parameter
composer run-script script "libraries/cloudECM/htmlForAPI?id=/erp/projects"

# Test process documentation
composer run-script script "libraries/cloudECM/htmlForProcess?id=/cloud-hrms"

# Test with admin privileges
composer run-script script "libraries/cloudECM/htmlForCourses?admin=1"

# Save HTML output to file
composer run-script script "libraries/cloudECM/htmlForCourses?output=html"

# Simulate specific user
composer run-script script "libraries/cloudECM/htmlForCourses?user=test@example.com"

# Embedded mode for applicable methods
composer run-script script "libraries/cloudECM/htmlForECM?id=/docs/intro&embedded=1"
```

**Parameters:**

| Parameter | Description | Default |
|-----------|-------------|---------|
| `id` | Identifier for methods that require it (API route, process ID, etc.) | - |
| `user` | Email to simulate as authenticated user | Authenticated user or test@cloudframework.io |
| `admin` | Add admin privileges (1 = yes) | 0 |
| `output` | Output mode: `terminal` (truncated) or `html` (saved to file) | terminal |
| `embedded` | Embedded mode for applicable methods (1 = yes) | 0 |

**Output:**
- **Terminal mode**: Displays first 5000 characters of HTML with execution stats
- **HTML mode**: Saves full HTML to `local_data/cloudECM_{method}.html`

---

## Dev-Docs API

The **dev-docs.php** API exposes CloudECM functionality as REST endpoints for the CLOUD Platform web interface.

**Location**: `buckets/cloudframework.io/api-dev/erp/dev-docs.php`

**API Class**: `CFDevDoc` (extends `CFAPI2022`)

### Base URL

```
/erp/dev-docs/:platform/:userId/cfa
```

### Tag Parameter

The `tag` parameter specifies what documentation to render. Format: `type:id[:props]`

**Examples:**

| Tag Value | Description |
|-----------|-------------|
| `api:/erp/projects` | Render API documentation |
| `endpoint:5123456789` | Render endpoint documentation |
| `apis` | List all APIs |
| `apis?folder=CLOUD HIPOTECH V2` | List APIs in folder |
| `process:PROC-001` | Render process documentation |
| `subprocess:5123456789` | Render subprocess documentation |
| `processes` | List all processes |
| `checks:5123456789` | Render checks for entity |
| `ecm-pages:/docs/intro` | Render ECM page |
| `ecm-links:/docs/intro` | Render ECM page with table of contents |
| `course:5123456789` | Render Academy course |
| `webapp:MyApp` | Render WebApp documentation |
| `library:CloudHIPOTECHV2` | Render Library documentation |
| `cfo:MP_HIPOTECH_Clients` | Render CFO documentation |
| `localizations:MyApp` | Render localizations |

### Example Requests

```bash
# Get API documentation
GET /erp/dev-docs/cloudframework/user@example.com/cfa?tag=api:/cloud-solutions/hipotech/documents

# Get process documentation
GET /erp/dev-docs/cloudframework/user@example.com/cfa?tag=process:PROC-001

# List APIs in folder
GET /erp/dev-docs/cloudframework/user@example.com/cfa?tag=apis&folder=CLOUD%20HIPOTECH%20V2

# Get ECM page with table of contents
GET /erp/dev-docs/cloudframework/user@example.com/cfa?tag=ecm-links:/docs/getting-started
```

### Web Interface URLs

The CLOUD Platform uses these URLs to display documentation:

```
# API Documentation
https://app.cloudframework.app/app.html#__cfa?api=/erp/dev-docs?tag=api:/cloud-solutions/hipotech/documents

# Process Documentation
https://app.cloudframework.app/app.html#__cfa?api=/erp/dev-docs?tag=process:PROC-001

# Library Documentation
https://app.cloudframework.app/app.html#__cfa?api=/erp/dev-docs?tag=library:CloudHIPOTECHV2

# ECM Page
https://app.cloudframework.app/app.html#__cfa?api=/erp/dev-docs?tag=ecm-pages:/docs/intro
```

### Required Privileges

- `ecm-admin` or `ecm-user`: Required for editing documentation
- `development-admin`: Required for development documentation access
- `projects-admin`: Admin access to all documentation

---

## Security Privileges

Access to CLOUD Documentum CFOs is controlled by these privileges:

| Privilege | Description |
|-----------|-------------|
| `directory-admin` | Full access to all ECM and documentation |
| `ecm-admin` | Full ECM administrator access |
| `ecm-user` | ECM user read/write access |
| `documents-admin` | Administrative access to ECM Pages (WebPages) |
| `development-admin` | Development administrator access |
| `development-user` | Development user access (Checks only) |

---

## CFO Structure Reference

CFOs (Cloud Framework Objects) are JSON files that define data models, security rules, and user interface configurations for CLOUD Documentum entities. This section documents the structure and components of CFO definitions.

### Local CFO Files

The CFO definitions for CLOUD Documentum are stored in `vendor/cloudframework-io/backend-core-php8/cloudia/cfos/`:

| CFO File | Entity | Description |
|----------|--------|-------------|
| `CloudFrameWorkCFOs.json` | CloudFrameWorkCFOs | Master CFO definitions management |
| `CloudFrameWorkCFOWorkFlows.json` | CloudFrameWorkCFOWorkFlows | CFO workflow configurations |
| `CloudFrameWorkDevDocumentation.json` | CloudFrameWorkDevDocumentation | Development groups |
| `CloudFrameWorkDevDocumentationForAPIs.json` | CloudFrameWorkDevDocumentationForAPIs | API documentation |
| `CloudFrameWorkDevDocumentationForAPIEndPoints.json` | CloudFrameWorkDevDocumentationForAPIEndPoints | API endpoints |
| `CloudFrameWorkDevDocumentationForLibraries.json` | CloudFrameWorkDevDocumentationForLibraries | Code libraries |
| `CloudFrameWorkDevDocumentationForLibrariesModules.json` | CloudFrameWorkDevDocumentationForLibrariesModules | Library modules/functions |
| `CloudFrameWorkDevDocumentationForProcesses.json` | CloudFrameWorkDevDocumentationForProcesses | Business processes |
| `CloudFrameWorkDevDocumentationForSubProcesses.json` | CloudFrameWorkDevDocumentationForSubProcesses | Sub-processes |
| `CloudFrameWorkDevDocumentationForProcessTests.json` | CloudFrameWorkDevDocumentationForProcessTests | Checks/tests |
| `CloudFrameWorkDevDocumentationForWebApps.json` | CloudFrameWorkDevDocumentationForWebApps | Web applications |
| `CloudFrameWorkDevDocumentationForWebAppsModules.json` | CloudFrameWorkDevDocumentationForWebAppsModules | WebApp modules |
| `CloudFrameWorkECMPages.json` | CloudFrameWorkECMPages | ECM content pages |
| `CloudFrameWorkECMPagesCompany.json` | CloudFrameWorkECMPagesCompany | Company-specific ECM pages |
| `CloudFrameWorkInfrastructureResources.json` | CloudFrameWorkInfrastructureResources | Infrastructure resources (servers, computers, domains, etc.) |
| `CloudFrameWorkInfrastructureResourcesAccesses.json` | CloudFrameWorkInfrastructureResourcesAccesses | Access permissions and credentials for resources |
| `CloudFrameWorkModules.json` | CloudFrameWorkModules | Menu modules for different platform solutions |

### CFO JSON Structure

Every CFO file follows a consistent JSON structure with these main sections:

```json
{
    "KeyName": "CFOEntityName",
    "entity": "CFOEntityName",
    "type": "ds",
    "GroupName": "CLOUD Documentum",
    "Active": true,
    "status": "IN PRODUCTION",
    "Owner": "owner@example.com",
    "CloudFrameworkUser": "creator@example.com",
    "DateUpdating": "2025-01-10 12:00:00",
    "Title": "CFO Title",
    "Description": "<p>HTML description</p>",
    "Tags": ["tag1", "tag2"],
    "_search": ["SEARCHTERM1", "SEARCHTERM2"],
    "environment": "",
    "extends": "",
    "Connections": "",
    "hasExternalWorkflows": false,
    "events": {
        "hooks": null,
        "workflows": null
    },
    "model": { ... },
    "securityAndFields": { ... },
    "interface": { ... },
    "lowCode": { ... }
}
```

### Root-Level Properties

| Property | Type | Description |
|----------|------|-------------|
| `KeyName` | string | Unique identifier for the CFO |
| `entity` | string | Datastore entity name (table name) |
| `type` | string | Storage backend: `ds` (Datastore), `db` (SQL), `bq` (BigQuery), `api` |
| `GroupName` | string | Category group (e.g., "CLOUD Documentum") |
| `Active` | boolean | Whether the CFO is currently active |
| `status` | string | Production status (e.g., "IN PRODUCTION", "IN DEVELOPMENT") |
| `Owner` | string | CFO owner email |
| `CloudFrameworkUser` | string | User who last modified the CFO |
| `Title` | string | Display title |
| `Description` | string | HTML description of the CFO purpose |
| `Tags` | array | Categorization tags |
| `_search` | array | Auto-generated search terms (uppercase) |
| `extends` | string | Parent CFO to extend from |
| `events` | object | Event hooks and workflows configuration |

### Model Section

The `model` section defines the data schema and field validation:

```json
{
    "model": {
        "model": {
            "KeyName": ["keyname", "index"],
            "Title": ["string", "index|description=Title of the entity"],
            "Status": ["string", "index"],
            "Email": ["string", "index|required|email"],
            "Age": ["integer", "index|min:18|max:100"],
            "Tags": ["list", "index|allownull"],
            "Description": ["string", "allownull"],
            "JSON": ["json", "allownull|description:Extra data"],
            "Content": ["zip", "allownull"],
            "DateInsertion": ["datetime", "index|allownull|defaultvalue:now"],
            "DateUpdated": ["datetime", "index|allownull|forcevalue:now"]
        },
        "dependencies": [
            "CloudFrameWorkUsers",
            "CloudFrameWorkDevDocumentation"
        ]
    }
}
```

#### Field Types

| Type | Description | Storage |
|------|-------------|---------|
| `keyname` | Primary key (auto-generated if not provided) | String |
| `keyid` | Auto-generated numeric ID | Integer |
| `string` | Text field | String |
| `integer` | Numeric field | Integer |
| `float` | Decimal number | Float |
| `boolean` | True/false | Boolean |
| `list` | Array of values | Array |
| `json` | JSON object | JSON |
| `zip` | Compressed text (for large content) | Compressed String |
| `datetime` | Date and time with timezone | Timestamp |
| `date` | Date only | Date |
| `geo` | Geographic coordinates | Geopoint |
| `email` | Email address | String |

#### Validation Rules

| Rule | Description | Example |
|------|-------------|---------|
| `index` | Create index for queries | `"index"` |
| `allownull` | Field can be null | `"index\|allownull"` |
| `allowempty` | Field can be empty string | `"index\|allowempty"` |
| `notnull` | Field is required | `"index\|notnull"` |
| `required` | Alias for notnull | `"required"` |
| `unique` | Value must be unique | `"unique"` |
| `minlength:N` | Minimum string length | `"minlength:4"` |
| `maxlength:N` | Maximum string length | `"maxlength:100"` |
| `min:N` | Minimum numeric value | `"min:0"` |
| `max:N` | Maximum numeric value | `"max:100"` |
| `defaultvalue:X` | Default value on insert | `"defaultvalue:now"` |
| `forcevalue:X` | Force value on every update | `"forcevalue:now"` |
| `description:X` | Field description | `"description:User email"` |
| `internal` | Cannot be set via API | `"internal"` |

### SecurityAndFields Section

The `securityAndFields` section defines access control and field UI configurations:

```json
{
    "securityAndFields": {
        "security": {
            "cfo_locked": true,
            "user_privileges": ["development-admin", "development-user"],
            "user_spacenames": null,
            "user_organizations": null,
            "backups": {
                "delete": true,
                "update": true
            },
            "allow_delete": {
                "field_values": {
                    "Status": ["equals", "pending"]
                }
            }
        },
        "fields": {
            "KeyName": {
                "name": "Display Name",
                "display_cfo": true
            },
            "Status": {
                "type": "select",
                "values": ["0.DEFINED", "1.MOCKED", "5.RUNNING"]
            },
            "TeamOwner": {
                "name": "Owner",
                "type": "autocomplete",
                "external_values": "datastore",
                "entity": "CloudFrameWorkUsers",
                "fields": "UserName,UserEmail",
                "linked_field": "KeyName",
                "defaultvalue": "{{User:KeyName}}"
            },
            "Description": {
                "type": "html"
            },
            "JSON": {
                "type": "json",
                "tab": "config"
            }
        }
    }
}
```

#### Security Properties

| Property | Description |
|----------|-------------|
| `cfo_locked` | Prevents CFO structure modification |
| `user_privileges` | Required user privileges to access |
| `user_spacenames` | Namespace restrictions |
| `user_organizations` | Organization restrictions |
| `backups.delete` | Enable backup on delete |
| `backups.update` | Enable backup on update |
| `allow_delete.field_values` | Conditional delete rules |

#### Field UI Types

| Type | Description |
|------|-------------|
| `string` | Text input (default) |
| `textarea` | Multi-line text |
| `html` | Rich text editor |
| `json` | JSON editor |
| `select` | Dropdown with static values |
| `multiselect` | Multiple selection |
| `autocomplete` | Search-based dropdown |
| `autoselect` | Auto-populated from existing values |
| `date` | Date picker |
| `datetime` | Date and time picker |
| `boolean` | Checkbox |
| `server_documents` | File upload |
| `virtual` | Computed/display-only field |

#### External Values Configuration

For fields that reference other entities:

```json
{
    "TeamOwner": {
        "type": "autocomplete",
        "external_values": "datastore",
        "entity": "CloudFrameWorkUsers",
        "fields": "UserName,UserEmail",
        "linked_field": "KeyName",
        "external_where": {
            "UserActive": true,
            "UserPrivileges": ["development-admin"]
        },
        "allow_empty": true,
        "empty_value": "Select a user"
    }
}
```

### Interface Section

The `interface` section defines the web UI configuration:

```json
{
    "interface": {
        "name": "Entity Name",
        "plural": "Entity Names",
        "ico": "bullseye",
        "ecm": "/cfos/EntityName",
        "tabs": {
            "default": {"title": "Main", "ico": "home"},
            "config": {"title": "Configuration", "ico": "cog"}
        },
        "filters": [
            {
                "type": "autocomplete",
                "field": "Folder",
                "empty_value": "Select a Folder",
                "allow_empty": true,
                "external_auto_values": true
            }
        ],
        "buttons": [
            {"title": "New Entry", "type": "api-insert"}
        ],
        "views": {
            "default": {
                "name": "General View",
                "server_limit": 200,
                "fields": { ... }
            }
        },
        "insert_fields": { ... },
        "update_fields": { ... },
        "display_fields": { ... },
        "copy_fields": { ... },
        "delete_fields": { ... }
    }
}
```

#### Interface Properties

| Property | Description |
|----------|-------------|
| `name` | Singular display name |
| `plural` | Plural display name |
| `ico` | FontAwesome icon name |
| `ecm` | ECM documentation path |
| `tabs` | Tab definitions for forms |
| `filters` | List filters for views |
| `buttons` | Action buttons |
| `views` | Grid/list view configurations |
| `insert_fields` | Fields shown on insert form |
| `update_fields` | Fields shown on update form |
| `display_fields` | Fields shown on display/read form |
| `copy_fields` | Fields shown on copy form |
| `delete_fields` | Fields shown on delete confirmation |

#### View Configuration

```json
{
    "views": {
        "default": {
            "name": "General View",
            "all_fields": true,
            "server_order": "DateUpdated DESC",
            "server_limit": 200,
            "conditional_rows_background_color": {
                "default": "",
                "fields": [
                    {
                        "field": "Status",
                        "condition": "equals",
                        "color": "#43db81a0",
                        "values": ["5.RUNNING"]
                    }
                ]
            },
            "fields": {
                "Folder": {
                    "field": "Folder",
                    "order": "ASC",
                    "row_group": true
                },
                "KeyName": {
                    "field": "KeyName",
                    "display_cfo": true,
                    "id_field": "KeyName"
                }
            }
        }
    }
}
```

### LowCode Section

The `lowCode` section provides a human-readable model description for code generation:

```json
{
    "lowCode": {
        "name": "Check de información",
        "plural": "Checks de información",
        "description": "",
        "ico": "ballot-check",
        "secret": "",
        "model": {
            "Title": {
                "name": "Title",
                "description": "Title of the check",
                "type": "string",
                "allow_null": false,
                "index": true,
                "view": true,
                "insert": true,
                "display": true,
                "update": true,
                "copy": true,
                "interface": {
                    "type": "string"
                }
            }
        },
        "dependencies": []
    }
}
```

### Events Section

The `events` section defines automation hooks and workflows:

```json
{
    "events": {
        "hooks": {
            "beforeInsert": "validate_data",
            "afterInsert": "send_notification",
            "beforeUpdate": null,
            "afterUpdate": "log_changes",
            "beforeDelete": "check_dependencies",
            "afterDelete": null
        },
        "workflows": {
            "approval": {
                "trigger": "Status == pending",
                "actions": ["notify_approvers", "set_deadline"]
            }
        }
    }
}
```

### CFO Dependencies

The `dependencies` array in the model section lists related CFOs:

```
CloudFrameWorkDevDocumentation
├── CloudFrameWorkDevDocumentationForAPIs
│   └── CloudFrameWorkDevDocumentationForAPIEndPoints
├── CloudFrameWorkDevDocumentationForLibraries
│   └── CloudFrameWorkDevDocumentationForLibrariesModules
├── CloudFrameWorkDevDocumentationForProcesses
│   ├── CloudFrameWorkDevDocumentationForSubProcesses
│   └── CloudFrameWorkDevDocumentationForProcessTests
├── CloudFrameWorkDevDocumentationForWebApps
│   └── CloudFrameWorkDevDocumentationForWebAppsModules
└── CloudFrameWorkECMPages
```

### Using CFOs in Code

```php
// Load a CFO definition
$cfo = $this->cfos->getCFODefinition('CloudFrameWorkDevDocumentationForAPIs');

// Access model fields
$model = $cfo['model']['model'];

// Access field configurations
$fields = $cfo['securityAndFields']['fields'];

// Access interface configuration
$interface = $cfo['interface'];

// Fetch data using CFO entity
$apis = $this->cfos->ds('CloudFrameWorkDevDocumentationForAPIs')->fetchAll('*');
```

---

## Related Documentation

| Resource | Location |
|----------|----------|
| **Backup Locations** | |
| API Backups | `buckets/backups/APIs/` |
| Library Backups | `buckets/backups/Libraries/` |
| Process Backups | `buckets/backups/Processes/` |
| Check Backups | `buckets/backups/Checks/` |
| Resource Backups | `buckets/backups/Resources/` |
| WebPages Backups | `buckets/backups/WebPages/` |
| **Documentation** | |
| CFOs Documentation | `CLAUDE.md` (CFOs section) |
| CFI Class | `buckets/backups/CFOs/CFI.md` |
| CFO Definitions | `vendor/cloudframework-io/backend-core-php8/cloudia/cfos/` |
| **Backup Scripts** | |
| API Backup Script | `buckets/scripts/backup_platforms_apis.php` |
| Library Backup Script | `buckets/scripts/backup_platforms_libraries.php` |
| Process Backup Script | `buckets/scripts/backup_platforms_processes.php` |
| Check Backup Script | `buckets/scripts/backup_platforms_checks.php` |
| Resource Backup Script | `buckets/scripts/backup_platforms_resources.php` |
| WebPages Backup Script | `buckets/scripts/backup_platforms_webpages.php` |
| **CRUD Scripts** | |
| API CRUD Script | `vendor/cloudframework-io/backend-core-php8/scripts/_cloudia/apis.php` |
| Library CRUD Script | `vendor/cloudframework-io/backend-core-php8/scripts/_cloudia/libraries.php` |
| Process CRUD Script | `vendor/cloudframework-io/backend-core-php8/scripts/_cloudia/processes.php` |
| Check CRUD Script | `vendor/cloudframework-io/backend-core-php8/scripts/_cloudia/checks.php` |
| Resource CRUD Script | `vendor/cloudframework-io/backend-core-php8/scripts/_cloudia/resources.php` |
| WebApp CRUD Script | `vendor/cloudframework-io/backend-core-php8/scripts/_cloudia/webapps.php` |
| WebPages CRUD Script | `vendor/cloudframework-io/backend-core-php8/scripts/_cloudia/webpages.php` |
| Courses CRUD Script | `vendor/cloudframework-io/backend-core-php8/scripts/_cloudia/courses.php` |
| CFO CRUD Script | `vendor/cloudframework-io/backend-core-php8/scripts/_cloudia/cfos.php` |
| Menu CRUD Script | `vendor/cloudframework-io/backend-core-php8/scripts/_cloudia/menu.php` |
| Auth Script | `vendor/cloudframework-io/backend-core-php8/scripts/_cloudia/auth.php` |
