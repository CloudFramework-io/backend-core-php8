# SKILL: Cloud Documentum - Product Development Methodology

## When to Use This Skill

Use this skill when the user needs to:
- **Start a new product or solution development**
- Create the documentation structure for a project
- Define business knowledge (Processes/SubProcesses)
- Create technical implementation documentation (WebApps/Modules)
- Link Projects to Development Groups
- Set up the complete development workflow

**Technical Reference:** For CFO structures, field definitions, and command parameters, see `cloudia/agents/cloud-documentum.md`.

**Philosophy Reference:** See `cloudia/CLOUD_DOCUMENTUM.md` for the complete methodology explanation.

---

## The Methodology: Business Knowledge First

CLOUD Documentum enforces a methodology where **business knowledge comes before technical implementation**:

```
1. PROCESS/SUBPROCESSES  →  Business knowledge (WHAT the product does)
          ↓
2. DEVELOPMENT GROUP     →  Organizational umbrella
          ↓
3. WEBAPPS/MODULES       →  Technical implementation (HOW it's built)
          ↓
4. PROJECT/MILESTONES    →  Execution tracking (matching WebApp categories)
          ↓
5. TASKS                 →  Executable work items
```

### Key Distinction

| Element | Purpose | Audience | Content |
|---------|---------|----------|---------|
| **Process** | Business knowledge | Customers + Developers | WHAT the product does |
| **SubProcess** | Feature definition | Customers + Developers | Functional capabilities |
| **WebApp** | Technical implementation | Developers | HOW it's implemented |
| **Module** | Specific functionality | Developers | Technical details |

---

## Complete Workflow: New Product Development

### Phase 1: Define Business Knowledge (Process)

When creating a product, first document the business objectives and functional features.

**Example:** Creating an "Email Marketing Product"

#### Step 1.1: Create the Process

The Process defines strategic and commercial objectives - NOT technical details.

Create `buckets/backups/Processes/{platform}/email-marketing-product.json`:

```json
{
    "CloudFrameWorkDevDocumentationForProcesses": {
        "KeyName": "email-marketing-product",
        "Title": "Email Marketing Product",
        "Cat": "Products",
        "Subcat": "Marketing Tools",
        "Type": "Product Definition",
        "Owner": "product@company.com",
        "Introduction": "<p>Complete email marketing solution for businesses.</p>",
        "Description": "<p><strong>Strategic Objectives:</strong></p><ul><li>Enable businesses to create and send marketing emails</li><li>Provide analytics on campaign performance</li><li>Integrate with major CRM platforms</li></ul><p><strong>Target Market:</strong> SMBs and enterprises</p>",
        "Status": "2.IN DEVELOPMENT",
        "JSON": {
            "Features": {
                "Template Management": {"route": "/feature-templates"},
                "Campaign Scheduling": {"route": "/feature-campaigns"},
                "Contact Management": {"route": "/feature-contacts"},
                "Analytics Dashboard": {"route": "/feature-analytics"}
            }
        }
    },
    "CloudFrameWorkDevDocumentationForSubProcesses": []
}
```

#### Step 1.2: Add SubProcesses (Functional Features)

SubProcesses define features from the USER's perspective - what they can DO, not how it's implemented.

```json
{
    "CloudFrameWorkDevDocumentationForProcesses": { ... },
    "CloudFrameWorkDevDocumentationForSubProcesses": [
        {
            "Process": "email-marketing-product",
            "Title": "Template Management",
            "Folder": "Core Features",
            "Status": "2.IN DEVELOPMENT",
            "TeamOwner": "product@company.com",
            "Description": "<p><strong>Functional Definition:</strong></p><ul><li>User can create email templates using drag-and-drop</li><li>User can import HTML templates</li><li>User can save and organize templates in folders</li><li>User can preview templates on different devices</li></ul>",
            "JSON": {
                "Capabilities": {
                    "Drag-Drop Creation": {"route": "/cap-dragdrop"},
                    "HTML Import": {"route": "/cap-htmlimport"},
                    "Template Library": {"route": "/cap-library"}
                }
            }
        },
        {
            "Process": "email-marketing-product",
            "Title": "Campaign Scheduling",
            "Folder": "Core Features",
            "Status": "2.IN DEVELOPMENT",
            "TeamOwner": "product@company.com",
            "Description": "<p><strong>Functional Definition:</strong></p><ul><li>User can schedule campaigns for specific dates/times</li><li>User can set up recurring campaigns</li><li>User can segment recipients by criteria</li><li>User receives confirmation before sending</li></ul>",
            "JSON": {
                "Capabilities": {
                    "Date Scheduling": {"route": "/cap-scheduling"},
                    "Recipient Segmentation": {"route": "/cap-segmentation"}
                }
            }
        }
    ]
}
```

#### Step 1.3: Sync to Remote

```bash
composer script -- "_cloudia/processes/insert-from-backup?id=email-marketing-product"
```

---

### Phase 2: Create Development Group

The Development Group is the organizational umbrella that links all related elements.

#### Step 2.1: Create the Group

Create `buckets/backups/DevelopmentGroups/{platform}/EMAIL-MARKETING.json`:

```json
{
    "KeyName": "EMAIL-MARKETING",
    "Title": "Email Marketing Product Development",
    "Cat": "Products",
    "Status": "2.IN DEVELOPMENT",
    "Owner": "lead@company.com",
    "Introduction": "<p>All documentation for the Email Marketing product.</p>",
    "Description": "<p>Includes business processes, technical WebApps, project tracking, and related APIs.</p>",
    "Tags": ["email", "marketing", "product"]
}
```

#### Step 2.2: Sync to Remote

```bash
composer script -- "_cloudia/devgroups/insert-from-backup?id=EMAIL-MARKETING"
```

#### Step 2.3: Link Process to Development Group

Update the Process to add `DocumentationId`:

```bash
composer script -- "_cloudia/processes/backup-from-remote?id=email-marketing-product"
```

Edit the backup to add `"DocumentationId": "EMAIL-MARKETING"`, then:

```bash
composer script -- "_cloudia/processes/update-from-backup?id=email-marketing-product"
```

---

### Phase 3: Create WebApps (Technical Implementation)

WebApps define HOW each feature is implemented technically. Create WebApps that map to the SubProcesses.

#### Step 3.1: Plan WebApp Structure

Based on SubProcesses, plan your WebApps:

| SubProcess (Business) | WebApp (Technical) |
|----------------------|-------------------|
| Template Management | `/email-marketing/template-editor` |
| Campaign Scheduling | `/email-marketing/campaign-manager` |
| Contact Management | `/email-marketing/contact-manager` |
| Analytics Dashboard | `/email-marketing/analytics` |

#### Step 3.2: Create WebApp with Modules

Create `buckets/backups/WebApps/{platform}/_email-marketing_template-editor.json`:

```json
{
    "CloudFrameWorkDevDocumentationForWebApps": {
        "KeyName": "/email-marketing/template-editor",
        "Title": "Template Editor",
        "Description": "<p>Technical implementation of template management features.</p><p><strong>Implements:</strong> SubProcess 'Template Management'</p>",
        "DocumentationId": "EMAIL-MARKETING",
        "Status": "2.IN DEVELOPMENT",
        "TeamOwner": "dev@company.com",
        "JSON": {
            "Architecture": {
                "Component Design": {"route": "/arch-components"},
                "State Management": {"route": "/arch-state"}
            },
            "Acceptance": {
                "Template Creation": {"route": "/accept-creation"},
                "Template Preview": {"route": "/accept-preview"}
            }
        }
    },
    "CloudFrameWorkDevDocumentationForWebAppsModules": [
        {
            "WebApp": "/email-marketing/template-editor",
            "EndPoint": "/drag-drop-builder",
            "Title": "Drag & Drop Builder",
            "Folder": "Editor Components",
            "Status": "2.IN DEVELOPMENT",
            "TeamOwner": "frontend@company.com",
            "Description": "<p>Visual editor for building email templates.</p><p><strong>Implements:</strong> 'Drag-Drop Creation' capability</p>",
            "JSON": {
                "Features": {
                    "Block Library": {"route": "/feat-blocks"},
                    "Responsive Grid": {"route": "/feat-grid"},
                    "Undo/Redo": {"route": "/feat-history"}
                },
                "Tests": {
                    "Block Drag Test": {"route": "/test-drag"},
                    "Save Template Test": {"route": "/test-save"}
                }
            }
        },
        {
            "WebApp": "/email-marketing/template-editor",
            "EndPoint": "/html-editor",
            "Title": "HTML Code Editor",
            "Folder": "Editor Components",
            "Status": "2.IN DEVELOPMENT",
            "TeamOwner": "frontend@company.com",
            "Description": "<p>Code editor for advanced HTML template editing.</p><p><strong>Implements:</strong> 'HTML Import' capability</p>",
            "JSON": {
                "Features": {
                    "Syntax Highlighting": {"route": "/feat-syntax"},
                    "Code Validation": {"route": "/feat-validation"}
                }
            }
        }
    ]
}
```

#### Step 3.3: Sync WebApps

```bash
composer script -- "_cloudia/webapps/insert-from-backup?id=/email-marketing/template-editor"
```

#### Step 3.4: Backup to Get Module KeyIds

```bash
composer script -- "_cloudia/webapps/backup-from-remote?id=/email-marketing/template-editor"
```

---

### Phase 4: Create Project with Milestones

Create a Project linked to the Development Group, with Milestones matching WebApp categories.

#### Step 4.1: Create Project

Create `buckets/backups/Projects/{platform}/email-marketing-2026.json`:

```json
{
    "CloudFrameWorkProjectsEntries": {
        "KeyName": "email-marketing-2026",
        "Title": "Email Marketing Product 2026",
        "DocumentationId": "EMAIL-MARKETING",
        "Status": "active",
        "Open": true,
        "DateStart": "2026-01-01",
        "DateEnd": "2026-12-31"
    },
    "CloudFrameWorkProjectsMilestones": [
        {
            "ProjectId": "email-marketing-2026",
            "Title": "Template Editor",
            "DocumentationId": "EMAIL-MARKETING",
            "Status": "in-progress",
            "DateEnd": "2026-03-31"
        },
        {
            "ProjectId": "email-marketing-2026",
            "Title": "Campaign Manager",
            "DocumentationId": "EMAIL-MARKETING",
            "Status": "pending",
            "DateEnd": "2026-06-30"
        },
        {
            "ProjectId": "email-marketing-2026",
            "Title": "Analytics Dashboard",
            "DocumentationId": "EMAIL-MARKETING",
            "Status": "pending",
            "DateEnd": "2026-09-30"
        }
    ],
    "CloudFrameWorkProjectsTasks": "Use _cloudia/tasks to manage tasks"
}
```

#### Step 4.2: Sync Project

```bash
composer script -- "_cloudia/projects/insert-from-backup?id=email-marketing-2026"
```

#### Step 4.3: Backup to Get Milestone KeyIds

```bash
composer script -- "_cloudia/projects/backup-from-remote?id=email-marketing-2026"
```

---

### Phase 5: Create Checks for Verification

Create Checks linked to WebApps and Modules for acceptance criteria.

#### Step 5.1: Create WebApp-Level Checks

For general acceptance criteria at the WebApp level.

Create `buckets/backups/Checks/{platform}/CloudFrameWorkDevDocumentationForWebApps___email-marketing_template-editor.json`:

```json
{
    "CFOEntity": "CloudFrameWorkDevDocumentationForWebApps",
    "CFOId": "/email-marketing/template-editor",
    "CloudFrameWorkDevDocumentationForProcessTests": [
        {
            "CFOEntity": "CloudFrameWorkDevDocumentationForWebApps",
            "CFOField": "JSON",
            "CFOId": "/email-marketing/template-editor",
            "Route": "/accept-creation",
            "Title": "Template Creation Acceptance",
            "Description": "<p>Verify users can create templates successfully.</p>",
            "Status": "pending",
            "Owner": "qa@company.com"
        }
    ]
}
```

#### Step 5.2: Create Module-Level Checks

For specific tests at the Module level (use Module KeyId from backup).

```bash
composer script -- "_cloudia/checks/update-from-backup?entity=CloudFrameWorkDevDocumentationForWebApps&id=/email-marketing/template-editor"
```

---

### Phase 6: Create Tasks for Execution

Tasks execute the functional definitions. See skill `cloud-documentum-tasks-and-projects` for detailed task workflows.

#### Step 6.1: Create Task

```bash
composer script -- "_cloudia/tasks/insert?title=Implement drag-drop builder&project=email-marketing-2026&milestone={MILESTONE_KEYID}"
```

#### Step 6.2: Complete Task Details

```bash
composer script -- "_cloudia/tasks/get?id={TASK_ID}"
```

Edit to add description linking to the WebApp Module:

```json
{
    "CloudFrameWorkProjectsTasks": {
        "Title": "Implement drag-drop builder",
        "Description": "<p><strong>Implements:</strong> WebApp Module /drag-drop-builder</p><p><strong>Reference:</strong> Template Editor → Drag & Drop Builder</p><p><strong>Acceptance Criteria:</strong></p><ul><li>Block library with 10+ components</li><li>Responsive grid system</li><li>Undo/redo functionality</li></ul>",
        "TimeEstimated": 40
    },
    "CloudFrameWorkDevDocumentationForProcessTests": [
        {
            "Title": "Block library implemented",
            "Route": "01",
            "Status": "pending",
            "CFOEntity": "CloudFrameWorkProjectsTasks",
            "CFOId": "{TASK_ID}"
        },
        {
            "Title": "Grid system works",
            "Route": "02",
            "Status": "pending",
            "CFOEntity": "CloudFrameWorkProjectsTasks",
            "CFOId": "{TASK_ID}"
        }
    ]
}
```

#### Step 6.3: Update Task

```bash
composer script -- "_cloudia/tasks/update?id={TASK_ID}"
```

---

## Quick Reference: Complete Flow

| Phase | What to Create | Script |
|-------|---------------|--------|
| 1 | Process + SubProcesses | `_cloudia/processes/insert-from-backup` |
| 2 | Development Group | `_cloudia/devgroups/insert-from-backup` |
| 3 | WebApps + Modules | `_cloudia/webapps/insert-from-backup` |
| 4 | Project + Milestones | `_cloudia/projects/insert-from-backup` |
| 5 | Checks | `_cloudia/checks/update-from-backup` |
| 6 | Tasks | `_cloudia/tasks/insert` + `update` |

---

## Checklist: Before Starting Development

Before creating tasks, verify you have:

- [ ] **Process** with business objectives defined
- [ ] **SubProcesses** with functional features (user perspective)
- [ ] **Development Group** linking all elements
- [ ] **WebApps** for each feature area
- [ ] **Modules** with technical specifications
- [ ] **Checks** for acceptance criteria
- [ ] **Project** linked to Development Group
- [ ] **Milestones** matching WebApp categories

---

## Technical Reference

For detailed CFO structures, field definitions, and all command parameters:
- **Agent:** `cloudia/agents/cloud-documentum.md`

For task execution and time tracking:
- **Skill:** `cloudia/skills/cloud-documentum-tasks-and-projects/SKILL.md`
