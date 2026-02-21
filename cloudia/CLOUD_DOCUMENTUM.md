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
| **Project** | Execution tracking container |
| **Milestone** | Delivery phase matching feature categories |
| **Task** | Executable work item with verification |

### CHECKs: Planning and Execution Phases

Every CHECK has two fundamental fields that support the **planning** and **execution** workflow:

| Field | Phase | Purpose |
|-------|-------|---------|
| **Objetivo** | Planning | Defines WHAT needs to be achieved - acceptance criteria, expected outcome |
| **Resultado** | Execution | Documents WHAT was done - implementation details, actual results |

**Workflow:**

```
┌─────────────────────────────────────────────────────────────────┐
│  PLANNING PHASE (Status: pending)                               │
│  ─────────────────────────────────────────────────────────────  │
│  • Define Objetivo: What must be accomplished                   │
│  • Set DateDueDate: Estimated completion date                   │
│  • Resultado: Empty or initial notes                            │
└─────────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│  EXECUTION PHASE (Status: in-progress → in-qa → ok)            │
│  ─────────────────────────────────────────────────────────────  │
│  • Fill Resultado: What was implemented and the outcome         │
│  • Update Status: Reflect current progress                      │
│  • DateDueDate: Set to today when completing (ok)               │
└─────────────────────────────────────────────────────────────────┘
```

**Example:**
- **Objetivo**: "Create OAuth2 login with Google and GitHub. Users should login/logout seamlessly."
- **Resultado**: "Implemented OAuth2 using passport.js. Added Google/GitHub strategies. Session with Redis."

This separation ensures:
1. **Clear requirements** during planning (Objetivo)
2. **Documented outcomes** during execution (Resultado)
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
| **Activity** | Time tracking and event logging (TimeSpent on tasks is calculated from activity) |

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

## Related Documentation

| Document | Description |
|----------|-------------|
| [`agents/cloud-documentum.md`](agents/cloud-documentum.md) | Complete technical agent |
| [`skills/cloud-documentum-processes-unitdevs-projects/SKILL.md`](skills/cloud-documentum-processes-unitdevs-projects/SKILL.md) | Documentation structure workflows |
| [`skills/cloud-documentum-tasks-and-projects/SKILL.md`](skills/cloud-documentum-tasks-and-projects/SKILL.md) | Task execution workflows |
| [`CLOUDIA.md`](CLOUDIA.md) | CloudIA platform overview |
| [`CFOs.md`](CFOs.md) | CFO structure reference |
