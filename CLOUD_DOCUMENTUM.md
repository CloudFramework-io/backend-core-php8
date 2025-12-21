# CLOUD Documentum

**CLOUD Documentum** is CloudFramework's solution for documenting processes, technology, and organizational knowledge. It provides a structured way to describe, version, and maintain documentation across different domains.

## Overview

CLOUD Documentum consists of several documentation modules:

| Module | Description | CFOs |
|--------|-------------|------|
| **APIs** | REST API documentation with endpoints | `CloudFrameWorkDevDocumentationForAPIs`, `CloudFrameWorkDevDocumentationForAPIEndPoints` |
| **Libraries** | Code libraries, classes, and functions documentation | `CloudFrameWorkDevDocumentationForLibraries`, `CloudFrameWorkDevDocumentationForLibrariesModules` |
| **Processes** | Business and technical processes | `CloudFrameWorkDevDocumentationForProcesses`, `CloudFrameWorkDevDocumentationForSubProcesses` |
| **Checks** | Tests, objectives, and specifications linked to other documentation | `CloudFrameWorkDevDocumentationForProcessTests` |
| **WebPages** | Web content pages for internal or public publishing | `CloudFrameWorkECMPages` |

All modules share a common lifecycle state system and backup infrastructure.

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
composer run-script script backup_platforms_apis

# CRUD operations for individual APIs
composer run-script script "apis/crud/:platform/backup-from-remote?id=/erp/projects"
composer run-script script "apis/crud/:platform/insert-from-backup?id=/core/alerts"
composer run-script script "apis/crud/:platform/update-from-backup?id=/erp/projects"
composer run-script script apis/crud/:platform/list-remote
composer run-script script apis/crud/:platform/list-local
```

**Script location**: `buckets/scripts/apis/crud.php`

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
composer run-script script "libraries/crud/:platform/backup-from-remote?id=/backend-core-php8/src/RESTful"
composer run-script script "libraries/crud/:platform/insert-from-backup?id=/api-prod/class/CloudAcademy"
composer run-script script "libraries/crud/:platform/update-from-backup?id=/backend-core-php8/src/RESTful"
composer run-script script libraries/crud/:platform/list-remote
composer run-script script libraries/crud/:platform/list-local
```

**Script locations**:
- Backup: `buckets/scripts/backup_platforms_libraries.php`
- CRUD: `buckets/scripts/libraries/crud.php`

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

| Field | Type | Description |
|-------|------|-------------|
| `KeyName` | string | Process identifier (e.g., `PROC-001`, `onboarding-employee`) |
| `DocumentationId` | string | Parent documentation group |
| `Cat` | string | Category |
| `Subcat` | string | Subcategory |
| `Type` | string | Process type |
| `Status` | enum | Lifecycle status |
| `Title` | string | Descriptive title |
| `Owner` | string | Process owner |
| `AssignedTo` | list | Assigned users |
| `Introduction` | html | Brief introduction |
| `Description` | html | Detailed description |
| `Tags` | list | Search tags |
| `JSON` | json | Structured data with route references to Checks |
| `DateUpdated` | datetime | Last update timestamp |

#### JSON Structure (with Route References)

The `JSON` field in Processes defines documentation objectives that link to Check records via the `route` property. Each route value corresponds to a Check's `Route` field.

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

| Field | Type | Description |
|-------|------|-------------|
| `KeyId` | keyid | Auto-generated unique identifier |
| `Process` | string | Parent Process KeyName |
| `Folder` | string | Grouping folder |
| `Cat` | string | Category |
| `Status` | enum | Lifecycle status |
| `EndPoint` | string | Related URL |
| `Deadline` | date | Target completion date |
| `TeamOwner` | string | SubProcess owner |
| `TeamAsignation` | string | Assigned user |
| `Title` | string | Descriptive title |
| `Description` | html | Detailed description |
| `Documents` | string | Associated documents (server_documents) |
| `Tags` | list | Search tags |
| `JSON` | json | Structured data with route references to Checks |
| `WebApps` | list | Related WebApps |
| `Libraries` | list | Related Libraries |
| `APIs` | list | Related APIs |
| `ENDPOINTs` | list | Related Endpoints |
| `DateUpdated` | datetime | Last update timestamp |

#### JSON Structure (with Route References)

The `JSON` field in SubProcesses also supports route references that link to Check records, similar to Processes.

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

### Process Management Scripts

```bash
# Backup all Processes from all platforms
composer run-script script backup_platforms_processes

# CRUD operations for individual Processes
composer run-script script "processes/crud/:platform/backup-from-remote?id=PROC-001"
composer run-script script "processes/crud/:platform/insert-from-backup?id=PROC-001"
composer run-script script "processes/crud/:platform/update-from-backup?id=PROC-001"
composer run-script script processes/crud/:platform/list-remote
composer run-script script processes/crud/:platform/list-local
```

**Script locations**:
- Backup: `buckets/scripts/backup_platforms_processes.php`
- CRUD: `buckets/scripts/processes/crud.php`

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
composer run-script script backup_platforms_checks

# CRUD operations for individual Checks (grouped by CFOEntity and CFOId)
composer run-script script "checks/crud/:platform/backup-from-remote?entity=CFOEntity&id=CFOId"
composer run-script script "checks/crud/:platform/insert-from-backup?entity=CFOEntity&id=CFOId"
composer run-script script "checks/crud/:platform/update-from-backup?entity=CFOEntity&id=CFOId"
composer run-script script checks/crud/:platform/list-remote
composer run-script script checks/crud/:platform/list-local
```

**Examples:**

```bash
# Backup checks linked to process PROC-001
composer run-script script "checks/crud/cloudframework/backup-from-remote?entity=CloudFrameWorkDevDocumentationForProcesses&id=PROC-001"

# Backup checks linked to API /erp/projects
composer run-script script "checks/crud/cloudframework/backup-from-remote?entity=CloudFrameWorkDevDocumentationForAPIs&id=/erp/projects"
```

**Script locations**:
- Backup: `buckets/scripts/backup_platforms_checks.php`
- CRUD: `buckets/scripts/checks/crud.php`

### Check Web Interface

- **Check Management**: `https://core20.web.app/ajax/cfo.html?api=/cfi/CloudFrameWorkDevDocumentationForProcessTests`

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

## Related Documentation

| Resource | Location |
|----------|----------|
| **Core Classes** | |
| CloudECM Library | `buckets/cloudframework.io/api-dev/class/CloudECM.php` |
| CloudECM Testing Script | `buckets/scripts/libraries/cloudECM.php` |
| Dev-Docs API | `buckets/cloudframework.io/api-dev/erp/dev-docs.php` |
| **Backup Locations** | |
| API Backups | `buckets/backups/APIs/` |
| Library Backups | `buckets/backups/Libraries/` |
| Process Backups | `buckets/backups/Processes/` |
| Check Backups | `buckets/backups/Checks/` |
| WebPages Backups | `buckets/backups/WebPages/` |
| **Documentation** | |
| CFOs Documentation | `CLAUDE.md` (CFOs section) |
| CFI Class | `buckets/backups/CFOs/CFI.md` |
| **Backup Scripts** | |
| API Backup Script | `buckets/scripts/backup_platforms_apis.php` |
| Library Backup Script | `buckets/scripts/backup_platforms_libraries.php` |
| Process Backup Script | `buckets/scripts/backup_platforms_processes.php` |
| Check Backup Script | `buckets/scripts/backup_platforms_checks.php` |
| WebPages Backup Script | `buckets/scripts/backup_platforms_webpages.php` |
| **CRUD Scripts** | |
| API CRUD Script | `buckets/scripts/apis/crud.php` |
| Library CRUD Script | `buckets/scripts/libraries/crud.php` |
| Process CRUD Script | `buckets/scripts/processes/crud.php` |
| Check CRUD Script | `buckets/scripts/checks/crud.php` |
