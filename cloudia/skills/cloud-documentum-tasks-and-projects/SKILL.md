# SKILL: Cloud Documentum - Projects, Tasks, and Time Tracking

## When to Use This Skill

Use this skill when the user needs to:
- Work with **projects**, **milestones**, or **tasks**
- **Create**, **update**, or **delete** tasks
- **Report hours** or track time spent
- Query **activity** history

**Technical Reference:** For detailed command parameters, CFO structures, and field references, see `cloudia/agents/cloud-documentum.md`.

---

## Core Concepts

| Concept | Description | Script |
|---------|-------------|--------|
| **Project** | High-level initiative with dates and ownership | `_cloudia/projects` |
| **Milestone** | Deliverable within a project with deadline | `_cloudia/projects` |
| **Task** | Work item assigned to team members | `_cloudia/tasks` |
| **Check** | Verification point or subtask within a task | `_cloudia/tasks` |
| **Activity** | Time entries and events | `_cloudia/activity` |

**Key Rule:** Tasks are managed **ONLY** via `_cloudia/tasks`. The `_cloudia/projects` script handles projects and milestones only.

---

## Workflow 1: Create a New Task for a Project

When you want to create a task associated with a project, follow this logical flow:

### Step 1: Get Project Information

First, understand what's in the project - its milestones and existing tasks.

```bash
# Backup project to see its structure and milestones
composer script -- "_cloudia/projects/backup-from-remote?id=my-project"

# List existing tasks for the project
composer script -- "_cloudia/tasks/project?id=my-project"
```

This gives you:
- Project details (Title, Status, Dates)
- Available milestones with their KeyIds
- Existing tasks with hours report

### Step 2: Create the Task

Create the task with the required parameters: title, project, and milestone.

```bash
composer script -- "_cloudia/tasks/insert?title=Implement user authentication&project=my-project&milestone=5734953457745920"
```

The system automatically sets:
- `Status`: pending
- `Priority`: medium
- `PlayerId`: Your email (assigned to you)
- `DateInitTask`: Today's date

**Output:** Task created with KeyId and saved to `./local_data/_cloudia/tasks/{TASK_ID}.json`

### Step 3: Complete the Task Details

The newly created task needs more details: description, estimated time, checks, etc.

```bash
# Export the task to edit it
composer script -- "_cloudia/tasks/get?id={TASK_ID}"
```

Edit the JSON file at `./local_data/_cloudia/tasks/{TASK_ID}.json`:

```json
{
    "CloudFrameWorkProjectsTasks": {
        "KeyId": "6789012345678901",
        "Title": "Implement user authentication",
        "Description": "<p>Implement OAuth2 authentication flow with Google and GitHub providers.</p>",
        "ProjectId": "my-project",
        "MilestoneId": "5734953457745920",
        "Status": "pending",
        "Priority": "high",
        "TimeEstimated": 16,
        "DateDeadLine": "2026-03-01"
    },
    "CloudFrameWorkDevDocumentationForProcessTests": [
        {
            "Title": "Setup OAuth credentials",
            "Description": "Configure Google and GitHub OAuth apps",
            "Route": "01",
            "Status": "pending",
            "CFOEntity": "CloudFrameWorkProjectsTasks",
            "CFOId": "6789012345678901"
        },
        {
            "Title": "Implement login endpoint",
            "Description": "Create /auth/login endpoint with provider selection",
            "Route": "02",
            "Status": "pending",
            "CFOEntity": "CloudFrameWorkProjectsTasks",
            "CFOId": "6789012345678901"
        },
        {
            "Title": "Test authentication flow",
            "Description": "Verify login/logout works correctly",
            "Route": "03",
            "Status": "pending",
            "CFOEntity": "CloudFrameWorkProjectsTasks",
            "CFOId": "6789012345678901"
        }
    ]
}
```

**Important for new checks:** Do NOT include `KeyId` - it's auto-generated.

### Step 4: Update the Task

Push the changes to the remote:

```bash
composer script -- "_cloudia/tasks/update?id=6789012345678901"
```

---

## Workflow 2: Work on an Existing Task

When you need to update a task's status, add checks, or modify details:

### Step 1: Find Your Tasks

```bash
# List your open tasks
composer script -- "_cloudia/tasks/list"

# Or view tasks for a specific project
composer script -- "_cloudia/tasks/project?id=my-project"
```

### Step 2: View Task Details

```bash
# Show task with checks and relations
composer script -- "_cloudia/tasks/show?id=6789012345678901"
```

### Step 3: Export for Editing

```bash
composer script -- "_cloudia/tasks/get?id=6789012345678901"
```

### Step 4: Make Changes

Edit `./local_data/_cloudia/tasks/6789012345678901.json`:
- Change `Status` to `in-progress`
- Update check statuses as you complete them
- Add new checks if needed (without KeyId)

### Step 5: Update

```bash
composer script -- "_cloudia/tasks/update?id=6789012345678901"
```

---

## Workflow 3: Report Hours on a Task

After working on a task, report the time spent:

### Step 1: Identify the Task

```bash
# List your tasks to find the KeyId
composer script -- "_cloudia/tasks/list"
```

### Step 2: Report Time

```bash
echo '{"TimeSpent":2,"Title":"Implemented OAuth flow","TaskId":"6789012345678901","Description":"Completed Google OAuth integration"}' | \
  php vendor/cloudframework-io/backend-core-php8/runscript.php "_cloudia/activity/report-input"
```

**Required fields:**
- `TimeSpent`: Hours worked (number > 0)
- `Title`: Short description
- `TaskId`: The task KeyId (or `ProjectId`/`MilestoneId`)

### Step 3: Verify

```bash
# View your activity summary
composer script -- "_cloudia/activity/summary"

# Or inputs for the specific task
composer script -- "_cloudia/activity/inputs?task=6789012345678901"
```

---

## Workflow 4: Close a Task

When a task is complete:

### Step 1: Update All Checks to OK

```bash
composer script -- "_cloudia/tasks/get?id=6789012345678901"
```

Edit the JSON: Change all check statuses to `"ok"`.

### Step 2: Close the Task

In the same JSON file:
```json
{
    "CloudFrameWorkProjectsTasks": {
        "Status": "closed",
        "Open": false,
        "Solution": "<p>Implemented OAuth2 with Google and GitHub. All tests passing.</p>"
    }
}
```

### Step 3: Update

```bash
composer script -- "_cloudia/tasks/update?id=6789012345678901"
```

---

## Workflow 5: Review Project Status

To understand the current state of a project:

### Step 1: Get Project Overview

```bash
# Backup to see milestones
composer script -- "_cloudia/projects/backup-from-remote?id=my-project"
```

### Step 2: View Tasks with Hours

```bash
# List all tasks with time tracking
composer script -- "_cloudia/tasks/project?id=my-project"
```

This shows:
- All tasks by status
- Time spent vs estimated per task
- Summary by assignee

### Step 3: View Activity Summary

```bash
# This week's activity
composer script -- "_cloudia/activity/summary"

# Specific date range
composer script -- "_cloudia/activity/summary?from=2026-02-01&to=2026-02-28"
```

---

## Workflow 6: Delete a Task

When a task is no longer needed:

### Step 1: View Task Info

```bash
composer script -- "_cloudia/tasks/delete?id=6789012345678901"
```

This shows task details and warns if it has checks.

### Step 2: Confirm Deletion

**If task has NO checks:**
```bash
composer script -- "_cloudia/tasks/delete?id=6789012345678901&confirm=yes"
```

**If task HAS checks:**
```bash
composer script -- "_cloudia/tasks/delete?id=6789012345678901&delete_checks=yes&confirm=yes"
```

---

## Important Rules

### TimeSpent is a CALCULATED Field

**🚫 NEVER include `TimeSpent` in task JSON files.**

The `TimeSpent` field is **automatically calculated** from activity inputs and events:

| Rule | Description |
|------|-------------|
| **Excluded from export** | `_cloudia/tasks/get` removes TimeSpent from the JSON file |
| **ERROR if present** | `_cloudia/tasks/update` returns ERROR if TimeSpent is in the JSON |
| **Auto-calculated** | TimeSpent = sum of hours from Inputs + Events (with user multiplication) |

**Calculation:**
```
TimeSpent = Σ(Inputs.TimeSpent) + Σ(Events.TimeSpent × userCount)

where userCount = count of distinct users from (SourcePlayerId + PlayersLinked)
```

**Event User Calculation:**
- Events with multiple participants multiply TimeSpent by user count
- `SourcePlayerId` + `PlayersLinked` are counted as distinct users
- Example: Event with 2h and 3 users = 6h total contribution

**To report time on a task, use Workflow 3: Report Hours on a Task (above)**

Or directly:
```bash
echo '{"TimeSpent":2,"Title":"Development work","TaskId":"TASK_ID"}' | \
  php vendor/cloudframework-io/backend-core-php8/runscript.php "_cloudia/activity/report-input"
```

---

## Quick Reference

### Most Common Commands

| Action | Command |
|--------|---------|
| List my tasks | `composer script -- "_cloudia/tasks/list"` |
| View project tasks | `composer script -- "_cloudia/tasks/project?id=PROJECT"` |
| Create task | `composer script -- "_cloudia/tasks/insert?title=X&project=X&milestone=X"` |
| Show task details (with inputs, events, TimeSpent) | `composer script -- "_cloudia/tasks/show?id=TASK_ID"` |
| Export task for editing | `composer script -- "_cloudia/tasks/get?id=TASK_ID"` |
| Update task | `composer script -- "_cloudia/tasks/update?id=TASK_ID"` |
| View activity | `composer script -- "_cloudia/activity/summary"` |

### Task Lifecycle

```
pending → in-progress → in-qa → closed
                    ↘ blocked ↗
```

### Check Lifecycle

```
pending → in-progress → ok
                    ↘ blocked
```

---

## File Locations

| Content | Location |
|---------|----------|
| Task exports | `./local_data/_cloudia/tasks/{TASK_ID}.json` |
| Project backups | `./buckets/backups/Projects/{platform}/{project-keyname}.json` |

---

## Technical Reference

For detailed information on:
- All command parameters
- CFO field structures
- Task and check field definitions
- Activity input fields
- Error handling

See: **`cloudia/agents/cloud-documentum.md`** (sections: Projects/Milestones/Tasks, Tasks CRUD Script, Activity Script)
