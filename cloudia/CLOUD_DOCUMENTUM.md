# CLOUD Documentum

CLOUD Documentum is CloudFramework's comprehensive system for organizing all information within an organization. For technology and product development, it provides a structured methodology that separates **business knowledge** from **technical implementation**, ensuring clarity for both customers and development teams.

---

## Methodology: Business Knowledge First

When developing products or technological solutions, CLOUD Documentum enforces a methodology where **business knowledge comes before technical implementation**. This ensures that:

1. **Customers** understand what the product does (functional perspective)
2. **Development teams** understand what to implement (clear requirements)
3. **Execution** is traceable from business goals to completed tasks

### The Development Flow

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                         1. BUSINESS KNOWLEDGE                                │
│  ┌─────────────────────────────────────────────────────────────────────┐    │
│  │  PROCESS: "Email Sending Product"                                    │    │
│  │  - Strategic and commercial objectives                               │    │
│  │  - What we want to achieve (NOT how to implement it)                │    │
│  │                                                                      │    │
│  │  SUBPROCESSES (Features - Functional Definition):                    │    │
│  │  ├── Template Management (what the user can do)                     │    │
│  │  ├── Contact Lists (functional capabilities)                         │    │
│  │  ├── Campaign Scheduling (business rules)                           │    │
│  │  └── Analytics Dashboard (what information is shown)                │    │
│  └─────────────────────────────────────────────────────────────────────┘    │
└─────────────────────────────────────────────────────────────────────────────┘
                                      │
                                      ▼
┌─────────────────────────────────────────────────────────────────────────────┐
│                      2. DEVELOPMENT GROUP                                    │
│  ┌─────────────────────────────────────────────────────────────────────┐    │
│  │  Groups all related information under one organizational unit:       │    │
│  │  - Processes (business knowledge)                                    │    │
│  │  - WebApps (technical implementation)                                │    │
│  │  - Projects (execution tracking)                                     │    │
│  │  - APIs, Libraries, Courses...                                       │    │
│  └─────────────────────────────────────────────────────────────────────┘    │
└─────────────────────────────────────────────────────────────────────────────┘
                                      │
                                      ▼
┌─────────────────────────────────────────────────────────────────────────────┐
│                 3. WEBAPPS (Unidades de Desarrollo)                          │
│  ┌─────────────────────────────────────────────────────────────────────┐    │
│  │  Technical definition of each feature/functionality:                 │    │
│  │                                                                      │    │
│  │  WebApp: "/email-product/template-editor"                           │    │
│  │  └── Module: "/drag-drop-builder" → Checks (acceptance criteria)    │    │
│  │  └── Module: "/html-editor" → Checks                                │    │
│  │                                                                      │    │
│  │  WebApp: "/email-product/campaign-manager"                          │    │
│  │  └── Module: "/scheduler" → Checks                                  │    │
│  │  └── Module: "/recipient-selector" → Checks                         │    │
│  └─────────────────────────────────────────────────────────────────────┘    │
└─────────────────────────────────────────────────────────────────────────────┘
                                      │
                                      ▼
┌─────────────────────────────────────────────────────────────────────────────┐
│                    4. PROJECT WITH MILESTONES                                │
│  ┌─────────────────────────────────────────────────────────────────────┐    │
│  │  Project linked to Development Group                                 │    │
│  │  Milestones matching WebApp categories:                              │    │
│  │                                                                      │    │
│  │  Milestone: "Template Editor"                                        │    │
│  │  Milestone: "Campaign Manager"                                       │    │
│  │  Milestone: "Analytics"                                              │    │
│  └─────────────────────────────────────────────────────────────────────┘    │
└─────────────────────────────────────────────────────────────────────────────┘
                                      │
                                      ▼
┌─────────────────────────────────────────────────────────────────────────────┐
│                           5. TASKS                                           │
│  ┌─────────────────────────────────────────────────────────────────────┐    │
│  │  Tasks execute the functional definitions:                           │    │
│  │                                                                      │    │
│  │  Task: "Implement drag-drop builder"                                 │    │
│  │  ├── Project: email-product-2026                                    │    │
│  │  ├── Milestone: Template Editor                                      │    │
│  │  ├── Related WebApp Module: /drag-drop-builder                      │    │
│  │  └── Checks: Implementation verification points                      │    │
│  └─────────────────────────────────────────────────────────────────────┘    │
└─────────────────────────────────────────────────────────────────────────────┘
```

### What an AI Must Understand

When working with CLOUD Documentum to develop a product or solution, an AI must:

1. **Identify or create the Process and SubProcesses**
   - Define the business objectives (strategic/commercial)
   - Document features from a FUNCTIONAL perspective, not technical
   - This serves both customers (understanding) and developers (requirements)

2. **Create or identify the Development Group**
   - The organizational umbrella for all related documentation
   - Links processes, WebApps, projects, and other elements

3. **Create WebApps (Unidades de Desarrollo) categorized by feature area**
   - Technical definition of each functionality
   - Must fulfill the requirements defined in Processes/SubProcesses
   - Include Modules with their acceptance Checks

4. **Create a Project linked to the Development Group**
   - Milestones should match WebApp categories
   - This enables organized task execution

5. **Document WebApps at functional level**
   - Define Modules with their Checks
   - Checks become the acceptance criteria for tasks

6. **Create Tasks for execution**
   - Each task linked to Project, Milestone, and related WebApp/Module
   - Tasks execute the functional definitions

---

## Key Concepts

### Processes vs WebApps

| Aspect | Process/SubProcess | WebApp/Module |
|--------|-------------------|---------------|
| **Purpose** | Business knowledge | Technical implementation |
| **Audience** | Customers + Developers | Developers |
| **Content** | Functional definition | Technical specification |
| **Focus** | WHAT the product does | HOW it's implemented |
| **Example** | "User can schedule campaigns" | "Scheduler API integration" |

### The Role of Each Element

| Element | Role in Methodology |
|---------|---------------------|
| **Process** | Strategic and commercial definition of the product |
| **SubProcess** | Functional features from user perspective |
| **Development Group** | Organizational container linking all elements |
| **WebApp** | Technical application implementing features |
| **Module** | Specific functionality within a WebApp |
| **Check** | Acceptance criteria and verification points |
| **Requirement** | Functional requirements linked to any Documentum object |
| **Project** | Execution tracking container |
| **Milestone** | Delivery phase matching feature categories |
| **Task** | Executable work item with verification |

### CHECKs: Planning and Execution Phases

Every CHECK has two fundamental fields that support the **planning** and **execution** workflow:

| Field | Phase | Purpose |
|-------|-------|---------|
| **Description** | Planning | Defines WHAT needs to be achieved - acceptance criteria, expected outcome |
| **Results** | Execution | Documents WHAT was done - implementation details, actual results |

**Valid CHECK Status values:** `pending`, `in-progress`, `blocked`, `in-qa`, `ok`

**Results is REQUIRED** when status is: `blocked`, `in-qa`, `ok`

**Workflow:**

```
┌─────────────────────────────────────────────────────────────────┐
│  PLANNING PHASE (Status: pending)                               │
│  ─────────────────────────────────────────────────────────────  │
│  • Define Description: What must be accomplished                │
│  • Set DateDueDate: Estimated completion date                   │
│  • Results: Empty or initial notes                              │
└─────────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│  EXECUTION PHASE (Status: in-progress → blocked/in-qa → ok)    │
│  ─────────────────────────────────────────────────────────────  │
│  • Fill Results: What was implemented and the outcome           │
│  • Update Status: Reflect current progress                      │
│  • DateDueDate: Set to today when completing (ok)               │
└─────────────────────────────────────────────────────────────────┘
```

**Example:**
- **Description**: "Create OAuth2 login with Google and GitHub. Users should login/logout seamlessly."
- **Results**: "Implemented OAuth2 using passport.js. Added Google/GitHub strategies. Session with Redis."

This separation ensures:
1. **Clear requirements** during planning (Description)
2. **Documented outcomes** during execution (Results)
3. **Traceability** between what was planned and what was delivered

---

## Module Overview

| Module | Purpose |
|--------|---------|
| **Development Groups** | Organizational backbone grouping all documentation |
| **Processes** | Business knowledge - strategic and functional definitions |
| **WebApps** | Technical implementation - development units |
| **Projects** | Execution tracking with milestones |
| **Tasks** | Work items with checks for verification |
| **APIs** | REST API documentation with endpoints |
| **Libraries** | Code libraries and function documentation |
| **Courses** | CLOUD Academy learning content |
| **Requirements** | Functional requirements associated to Documentum objects (Processes, SubProcesses, WebApps, etc.) |
| **Activity** | Time tracking and event logging (TimeSpent on tasks is calculated from activity) |
| **Localizations** | Multi-language dictionary tags for i18n support |

---

## CLI Scripts

All operations are performed via `_cloudia` scripts:

```bash
composer script -- "_cloudia/{module}/{action}?{params}"
```

### Documentation Scripts

| Script | Purpose |
|--------|---------|
| `_cloudia/devgroups` | Development Groups management |
| `_cloudia/processes` | Process and SubProcess documentation |
| `_cloudia/webapps` | WebApp and Module documentation |
| `_cloudia/checks` | Checks/tests management |
| `_cloudia/apis` | API documentation |
| `_cloudia/libraries` | Library documentation |
| `_cloudia/localize` | Localization dictionaries (i18n tags) |

### Execution Scripts

| Script | Purpose |
|--------|---------|
| `_cloudia/projects` | Projects and milestones |
| `_cloudia/tasks` | Task CRUD with checks |
| `_cloudia/activity` | Time tracking and events (TimeSpent is calculated from Inputs + Events) |

---

## Detailed Documentation

### Agent: cloud-documentum

**Location:** `cloudia/agents/cloud-documentum.md`

The complete technical reference for all CLOUD Documentum operations:
- CFO structures and field definitions
- All script commands and parameters
- Checks linking and configuration
- File formats and naming conventions

```
Use the cloud-documentum agent for all CLOUD Documentum documentation operations.
```

### Skill: cloud-documentum-processes-unitdevs-projects

**Location:** `cloudia/skills/cloud-documentum-processes-unitdevs-projects/SKILL.md`

Workflow-oriented guide for the methodology:
- Creating Development Groups
- Creating Processes and SubProcesses (business knowledge)
- Creating WebApps and Modules (technical implementation)
- Linking Projects to Development Groups
- Creating Checks for verification

```
Use this skill when setting up documentation structure for a new product or solution.
```

### Skill: cloud-documentum-tasks-and-projects

**Location:** `cloudia/skills/cloud-documentum-tasks-and-projects/SKILL.md`

Workflow-oriented guide for execution:
- Creating and managing Tasks
- Working with Projects and Milestones
- Reporting hours and activity
- Task lifecycle management

```
Use this skill for task execution and time tracking operations.
```

---

## Requirements (Functional Requirements)

CLOUD Documentum includes a **Requirements Management** module (`CloudFrameWorkDevRequirements`) that allows defining functional requirements associated to any Documentum object. The **meaning and impact** of a requirement changes depending on the entity it is associated with.

### Use Cases by Associated Entity

The `CFOEntity`/`CFOId` association determines the nature and downstream effect of each requirement:

#### Associated to Process / SubProcess → New functional element

When a requirement is linked to a **Process** or **SubProcess**, it indicates that a **new functional element** needs to be added to the business knowledge. This triggers a downstream workflow:

```
Requirement (approved) → New SubProcess/feature documented in the Process
                       → WebApps (DevUnits) created to implement the functionality
                       → Tasks generated from the WebApp Checks
```

**Example**: A requirement on Process `/cloud-hrms` stating "The system must allow employees to request schedule changes" means a new SubProcess will be documented, then DevUnits will be created to implement it.

#### Associated to WebApp / Module → Implementation requirement

When a requirement is linked to a **WebApp** (DevUnit) or **Module**, it defines a **functional requirement to implement** within that development unit. Tasks associated to the WebApp/Module must take this requirement into account.

```
Requirement (approved) → Acceptance criteria for the WebApp/Module
                       → Tasks must satisfy the requirement
                       → Checks verify compliance
```

**Example**: A requirement on WebApp `/cloud-hrms/schedule-management` stating "The calendar must show pending approvals with visual differentiation" is a functional spec that developers must implement and tasks must verify.

#### Associated to Library / Library Module → Implementation specification

When a requirement is linked to a **Library** or **Library function**, it defines a **concrete implementation requirement** — a technical specification for how the library/function must behave.

```
Requirement (approved) → Technical spec for the library/function
                       → Direct implementation constraint
```

**Example**: A requirement on Library `/cloud-solutions/cloud-ecm/class/CloudECM` stating "The method `generatePDF` must support watermarks with configurable opacity" is a direct technical specification.

#### Associated to API / API EndPoint → API contract requirement

When a requirement is linked to an **API** or **EndPoint**, it defines a **contract requirement** for the API behavior, input/output, or integration constraints.

### Summary Table

| Associated Entity | Requirement Means | Downstream Effect |
|-------------------|-------------------|-------------------|
| **Process / SubProcess** | New functional element needed | Creates SubProcesses → DevUnits → Tasks |
| **WebApp / Module** | Functional requirement to implement | Tasks must satisfy it; Checks verify it |
| **Library / Library Module** | Concrete implementation specification | Direct technical constraint |
| **API / API EndPoint** | API contract/behavior requirement | Endpoint implementation constraint |

### CloudFrameWorkDevRequirements Structure

| Field | Type | Mandatory | Description |
|-------|------|-----------|-------------|
| `CFOEntity` | select | No | Associated Documentum CFO (APIs, Processes, SubProcesses, WebApps, Modules, Libraries) |
| `CFOId` | string | No | KeyName/KeyId of the associated object |
| `Title` | string | Yes | Requirement title |
| `Priority` | select | No | MoSCoW priority: `must`, `should`, `could`, `wont` |
| `Status` | select | No | Lifecycle: `draft`, `pending-review`, `approved`, `rejected`, `implemented`, `deprecated` |
| `Open` | boolean | Yes | Auto-calculated: `false` when Status is `implemented` or `deprecated` |
| `Owner` | string | Yes | Requirement owner email |
| `AssignedTo` | multiselect | No | Assigned users (filtered by development/ecm privileges) |
| `DateDueDate` | date | No | Target completion date |
| `Description` | html (zip) | Yes | Detailed functional requirement description |
| `CloudIAAnalysis` | html (zip) | No | AI-generated analysis of the requirement |
| `AcceptanceCriteria` | html (zip) | No | Formal acceptance criteria |
| `Results` | html (zip) | No | Implementation results and notes |
| `Documents` | server_documents | No | Attached documentation files |
| `Tags` | list | No | Search tags |
| `JSON` | json | No | Extra structured data |

### Associable CFOEntities

| CFOEntity Value | Object | Requirement Type |
|-----------------|--------|------------------|
| `CloudFrameWorkDevDocumentationForProcesses` | Processes | New functional element → generates DevUnits |
| `CloudFrameWorkDevDocumentationForSubProcesses` | SubProcesses | New functional element → generates DevUnits |
| `CloudFrameWorkDevDocumentationForWebApps` | WebApps (DevUnits) | Functional requirement → tasks must satisfy |
| `CloudFrameWorkDevDocumentationForWebAppsModules` | WebApp Modules | Functional requirement → tasks must satisfy |
| `CloudFrameWorkDevDocumentationForLibraries` | Libraries | Implementation specification |
| `CloudFrameWorkDevDocumentationForLibrariesModules` | Library Modules | Implementation specification |
| `CloudFrameWorkDevDocumentationForAPIs` | APIs | API contract requirement |
| `CloudFrameWorkDevDocumentationForAPIEndPoints` | API EndPoints | API contract requirement |

### Status Lifecycle

```
draft → pending-review → approved → implemented
                       ↘ rejected
                                     deprecated
```

| Status | Description | Deletable | Open |
|--------|-------------|-----------|------|
| `draft` | Initial draft, still being written | **Yes** | Yes |
| `pending-review` | Submitted for stakeholder review | No | Yes |
| `approved` | Approved for implementation | No | Yes |
| `rejected` | Rejected, not to be implemented | No | Yes |
| `implemented` | Successfully implemented | No | **No** |
| `deprecated` | No longer relevant | No | **No** |

> **Delete rule**: Only requirements with `Status == 'draft'` can be deleted.

### Priority (MoSCoW Method)

| Priority | Label | Description |
|----------|-------|-------------|
| `must` | Must Have | Critical requirement, non-negotiable |
| `should` | Should Have | Important but not critical |
| `could` | Could Have | Desirable if time/resources allow |
| `wont` | Won't Have (this time) | Explicitly excluded from current scope |

### Security

| Setting | Value |
|---------|-------|
| CFO Locked | Yes |
| Required Privileges | `development-admin`, `development-user`, `ecm-admin`, `ecm-user` |
| Backups | Update and delete tracked |
| Delete restriction | Only when `Status == 'draft'` |

### Data Type

- **Type**: `ds` (Google Datastore)
- **GroupName**: CLOUD Documentum
- **Interface icon**: `clipboard-list`

### Relationship with Other Documentum Elements

```
Process/SubProcess (Business Knowledge)
    │
    ├── Requirements (on Process) → "Add new functional element"
    │       └── Generates → new SubProcesses → new DevUnits → new Tasks
    │
    ├── WebApps/Modules (Technical Implementation)
    │   ├── Requirements (on WebApp) → "Implement this functionality"
    │   │       └── Tasks must satisfy → Checks verify compliance
    │   └── Checks (Verification Points)
    │
    ├── Libraries (Code Implementation)
    │   └── Requirements (on Library) → "Concrete implementation spec"
    │
    ├── APIs (Contracts)
    │   └── Requirements (on API) → "API behavior/contract spec"
    │
    └── Tasks (Execution)
            └── Checks (Task Verification)
```

Requirements complement the existing Documentum methodology at **different levels** depending on association:
1. **On Processes**: Signal that new functionality must be designed and built end-to-end
2. **On WebApps/Modules**: Define functional constraints that tasks must implement
3. **On Libraries**: Specify concrete technical behavior
4. **On APIs**: Define contract and integration requirements

---

## Related Documentation

| Document | Description |
|----------|-------------|
| [`agents/cloud-documentum.md`](agents/cloud-documentum.md) | Complete technical agent |
| [`skills/cloud-documentum-processes-unitdevs-projects/SKILL.md`](skills/cloud-documentum-processes-unitdevs-projects/SKILL.md) | Documentation structure workflows |
| [`skills/cloud-documentum-tasks-and-projects/SKILL.md`](skills/cloud-documentum-tasks-and-projects/SKILL.md) | Task execution workflows |
| [`CLOUDIA.md`](CLOUDIA.md) | CloudIA platform overview |
| [`CFOs.md`](CFOs.md) | CFO structure reference |
