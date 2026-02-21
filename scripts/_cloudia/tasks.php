<?php
/**
 * Tasks CRUD Script
 *
 * This script provides full CRUD functionality for tasks:
 * - List my open tasks
 * - List tasks for today
 * - List tasks in the current sprint
 * - List tasks for a specific project (with detailed report)
 * - List tasks for a specific person
 * - Show detailed task information with associated checks
 * - Export task JSON to local file
 * - Create a new task from JSON input
 * - Update a task from JSON input
 * - Filter tasks by project, status, or priority
 *
 * Usage:
 *   _cloudia/tasks/list                               - List my open tasks
 *   _cloudia/tasks/today                              - List tasks for today
 *   _cloudia/tasks/sprint                             - List tasks in current sprint
 *   _cloudia/tasks/project?id=project-keyname         - List tasks for a project (detailed report)
 *   _cloudia/tasks/milestone?id=milestone-keyid       - List tasks for a specific milestone
 *   _cloudia/tasks/person?email=user@example.com      - List tasks for a specific person
 *   _cloudia/tasks/show?id=TASK_KEYID                 - Show task details with checks
 *   _cloudia/tasks/get?id=TASK_KEYID                  - Export task JSON to ./local_data/_cloudia/tasks/
 *   _cloudia/tasks/insert?json={...}                  - Create new task from JSON
 *   _cloudia/tasks/update?id=TASK_KEYID&json={...}    - Update task from JSON
 *   _cloudia/tasks/search?status=in-progress          - Search tasks by filters
 *
 * @author CloudFramework Development Team
 * @version 1.3
 */
class Script extends CoreScripts
{
    /** @var string Platform ID for task operations */
    var $platform_id = '';

    /** @var array HTTP headers for API authentication */
    var $headers = [];

    /** @var string Base API URL for remote platform */
    var $api_base_url = 'https://api.cloudframework.io';

    /** @var string Current user email */
    var $user_email = '';

    /**
     * Main execution method
     */
    function main()
    {

        //region SET $this->platform_id from configuration
        $this->platform_id = $this->core->config->get('core.erp.platform_id');
        if (!$this->platform_id) {
            return $this->addError('config-error', 'core.erp.platform_id is not defined');
        }
        //endregion

        //region AUTHENTICATE user and SET $this->headers
        if (!$this->authPlatformUserWithLocalAccessToken($this->platform_id)) {
            return false;
        }
        $this->user_email = $this->core->user->id;
        //endregion

        //region VERIFY privileges
        $this->sendTerminal("Executing {$this->params[0]}/{$this->params[1]} from platform [{$this->platform_id}] user [{$this->user_email}]");
        if (!$this->core->user->hasAnyPrivilege('development-admin,development-user,projects-admin')) {
            return $this->addError('You do not have permission [development-admin,projects-admin] to execute this script');
        }
        //endregion

        //region SET AUTHENTICATE header for API call
        $this->headers = [
            'X-WEB-KEY' => '/scripts/_cloudia/tasks',
            'X-DS-TOKEN' => $this->core->user->token
        ];
        //endregion

        //region EXECUTE METHOD_{$method}
        $method = ($this->params[2] ?? 'default');
        $this->sendTerminal(" - method: {$method}");

        if (!$this->useFunction('METHOD_' . str_replace('-', '_', $method))) {
            return $this->addError("/{$method} is not implemented");
        }
        //endregion
    }

    /**
     * Display available commands
     */
    public function METHOD_default()
    {
        $this->sendTerminal("");
        $this->sendTerminal("Available commands:");
        $this->sendTerminal("  /list                          - List my open tasks");
        $this->sendTerminal("  /today                         - List tasks active for today");
        $this->sendTerminal("  /sprint                        - List tasks in current sprint");
        $this->sendTerminal("  /project?id=KEY                - List tasks for a specific project (detailed report)");
        $this->sendTerminal("  /milestone?id=MILESTONE_KEYID  - List tasks for a specific milestone");
        $this->sendTerminal("  /person?email=EMAIL            - List tasks for a specific person");
        $this->sendTerminal("  /show?id=TASK_KEYID            - Show detailed task info with checks");
        $this->sendTerminal("  /get?id=TASK_KEYID             - Export task + checks JSON to local file");
        $this->sendTerminal("  /update?id=TASK_KEYID          - Update task + checks from local file");
        $this->sendTerminal("  /update?id=TASK_KEYID&delete=yes|no - Confirm or skip deletion of remote checks");
        $this->sendTerminal("  /insert?title=TITLE&project=ID&milestone=ID - Create a new task");
        $this->sendTerminal("  /delete?id=TASK_KEYID          - Delete a task (requires confirm=yes)");
        $this->sendTerminal("  /delete?...&delete_checks=yes  - Confirm deletion of associated checks");
        $this->sendTerminal("  /search?status=STATE           - Search tasks by filters");
        $this->sendTerminal("");
        $this->sendTerminal("Filter parameters for /search:");
        $this->sendTerminal("  status    - Task status (pending, in-progress, in-qa, closed, blocked, etc.)");
        $this->sendTerminal("  priority  - Task priority (very_high, high, medium, low, very_low)");
        $this->sendTerminal("  project   - Project KeyName");
        $this->sendTerminal("  assigned  - Assigned user email");
        $this->sendTerminal("");
        $this->sendTerminal("Examples:");
        $this->sendTerminal("  composer script -- _cloudia/tasks/list");
        $this->sendTerminal("  composer script -- _cloudia/tasks/today");
        $this->sendTerminal("  composer script -- \"_cloudia/tasks/project?id=cloud-platform\"");
        $this->sendTerminal("  composer script -- \"_cloudia/tasks/milestone?id=5734953457745920\"");
        $this->sendTerminal("  composer script -- \"_cloudia/tasks/person?email=user@example.com\"");
        $this->sendTerminal("  composer script -- \"_cloudia/tasks/show?id=5734953457745920\"");
        $this->sendTerminal("  composer script -- \"_cloudia/tasks/get?id=5734953457745920\"");
        $this->sendTerminal("  composer script -- \"_cloudia/tasks/update?id=5734953457745920\"");
        $this->sendTerminal("  composer script -- \"_cloudia/tasks/insert?title=New Task&project=my-project&milestone=123456\"");
        $this->sendTerminal("  composer script -- \"_cloudia/tasks/delete?id=5734953457745920\"");
        $this->sendTerminal("  composer script -- \"_cloudia/tasks/delete?id=5734953457745920&confirm=yes\"");
        $this->sendTerminal("  composer script -- \"_cloudia/tasks/delete?id=5734953457745920&delete_checks=yes&confirm=yes\"");
        $this->sendTerminal("  composer script -- \"_cloudia/tasks/search?status=in-progress&priority=high\"");
        $this->sendTerminal("");
        $this->sendTerminal("Workflow: get -> edit file -> update");
        $this->sendTerminal("  1. Export task: composer script -- \"_cloudia/tasks/get?id=TASK_ID\"");
        $this->sendTerminal("  2. Edit file:   ./local_data/_cloudia/tasks/TASK_ID.json");
        $this->sendTerminal("  3. Update:      composer script -- \"_cloudia/tasks/update?id=TASK_ID\"");
    }

    /**
     * List my open tasks
     */
    public function METHOD_list()
    {
        //region FETCH tasks assigned to current user
        $this->sendTerminal("");
        $this->sendTerminal("My open tasks [{$this->user_email}]:");
        $this->sendTerminal(str_repeat('-', 100));

        $params = [
            'filter_Open' => 'true',
            'filter_PlayerId' => $this->user_email,
            '_order' => '-Priority,DateDeadLine',
            'cfo_limit' => 100,
            '_raw' => 1,
            '_timezone' => 'UTC'
        ];

        $response = $this->core->request->get_json_decode(
            "{$this->api_base_url}/core/cfo/cfi/CloudFrameWorkProjectsTasks?_raw&_timezone=UTC",
            $params,
            $this->headers
        );

        if ($this->core->request->error) {
            return $this->addError($this->core->request->errorMsg);
        }

        $tasks = $response['data'] ?? [];
        $this->displayTaskList($tasks);
        //endregion

        return true;
    }

    /**
     * List tasks for today
     */
    public function METHOD_today()
    {
        //region FETCH tasks for today
        $this->sendTerminal("");
        $this->sendTerminal("Tasks for today [{$this->user_email}]:");
        $this->sendTerminal(str_repeat('-', 100));

        $today = date('Y-m-d');
        $params = [
            'filter_Open' => 'true',
            'filter_PlayerId' => $this->user_email,
            'filter_DateInit' => ['<=', $today],
            '_order' => '-Priority,DateDeadLine',
            'cfo_limit' => 100,
            '_raw' => 1,
            '_timezone' => 'UTC'
        ];

        $response = $this->core->request->get_json_decode(
            "{$this->api_base_url}/core/cfo/cfi/CloudFrameWorkProjectsTasks?_raw&_timezone=UTC",
            $params,
            $this->headers
        );

        if ($this->core->request->error) {
            return $this->addError($this->core->request->errorMsg);
        }

        $tasks = $response['data'] ?? [];
        $this->displayTaskList($tasks);
        //endregion

        return true;
    }

    /**
     * List tasks in current sprint
     */
    public function METHOD_sprint()
    {
        //region FETCH current sprint
        $this->sendTerminal("");
        $this->sendTerminal("Fetching current sprint...");

        $today = date('Y-m-d');
        $params = [
            'filter_Active' => 'true',
            'filter_DateInit' => ['<=', $today],
            'filter_DateEnd' => ['>=', $today],
            '_order' => '-DateInit',
            'cfo_limit' => 1,
            '_raw' => 1,
            '_timezone' => 'UTC'
        ];

        $response = $this->core->request->get_json_decode(
            "{$this->api_base_url}/core/cfo/cfi/CloudFrameWorkProjectsSprints?_raw&_timezone=UTC",
            $params,
            $this->headers
        );

        if ($this->core->request->error) {
            return $this->addError($this->core->request->errorMsg);
        }

        $sprints = $response['data'] ?? [];
        if (!$sprints) {
            $this->sendTerminal("No active sprint found for today");
            return true;
        }

        $sprint = $sprints[0];
        $sprintId = $sprint['KeyId'] ?? '';
        $sprintTitle = $sprint['Title'] ?? 'Untitled Sprint';
        $this->sendTerminal("");
        $this->sendTerminal("Current Sprint: {$sprintTitle} (ID: {$sprintId})");
        $this->sendTerminal("Period: {$sprint['DateInit']} to {$sprint['DateEnd']}");
        $this->sendTerminal(str_repeat('-', 100));
        //endregion

        //region FETCH tasks for current sprint
        $params = [
            'filter_SprintIds' => $sprintId,
            'filter_PlayerId' => $this->user_email,
            '_order' => '-Priority,Status',
            'cfo_limit' => 200,
            '_raw' => 1,
            '_timezone' => 'UTC'
        ];

        $response = $this->core->request->get_json_decode(
            "{$this->api_base_url}/core/cfo/cfi/CloudFrameWorkProjectsTasks?_raw&_timezone=UTC",
            $params,
            $this->headers
        );

        if ($this->core->request->error) {
            return $this->addError($this->core->request->errorMsg);
        }

        $tasks = $response['data'] ?? [];
        $this->displayTaskList($tasks);
        //endregion

        return true;
    }

    /**
     * List tasks for a specific project with detailed report
     *
     * Shows tasks separated by OPEN/CLOSED status with hours summary
     * and breakdown by assignee.
     */
    public function METHOD_project(): bool
    {
        //region VALIDATE project ID
        $project_id = $this->formParams['id'] ?? null;
        if (!$project_id) {
            return $this->addError("Missing required parameter: id. Usage: _cloudia/tasks/project?id=project-keyname");
        }
        //endregion

        //region FETCH project info
        $this->sendTerminal("");
        $this->sendTerminal("Fetching project [{$project_id}]...");

        $project_response = $this->core->request->get_json_decode(
            "{$this->api_base_url}/core/cfo/cfi/CloudFrameWorkProjectsEntries/display/" . urlencode($project_id) . "?_raw&_timezone=UTC",
            ['_raw' => 1, '_timezone' => 'UTC'],
            $this->headers
        );

        if (!$this->core->request->error && ($project_response['data'] ?? null)) {
            $project = $project_response['data'];
            $projectTitle = $project['Title'] ?? 'Untitled Project';
            $projectStatus = $project['Status'] ?? '';
            $projectType = $project['Type'] ?? '';
            $projectOpen = ($project['Open'] ?? false) ? 'Open' : 'Closed';
            $this->sendTerminal("");
            $this->sendTerminal("Project: {$projectTitle}");
            if ($projectType) $this->sendTerminal("Type: {$projectType}");
            if ($projectStatus) $this->sendTerminal("Status: {$projectStatus} ({$projectOpen})");
        }
        //endregion

        //region FETCH tasks for the project
        $this->sendTerminal("");
        $this->sendTerminal("Fetching tasks for project [{$project_id}]...");

        $params = [
            'filter_ProjectId' => $project_id,
            '_order' => '-Priority,Status,DateDeadLine',
            'cfo_limit' => 500,
            '_raw' => 1,
            '_timezone' => 'UTC'
        ];

        $response = $this->core->request->get_json_decode(
            "{$this->api_base_url}/core/cfo/cfi/CloudFrameWorkProjectsTasks?_raw&_timezone=UTC",
            $params,
            $this->headers
        );

        if ($this->core->request->error) {
            return $this->addError($this->core->request->errorMsg);
        }

        $tasks = $response['data'] ?? [];
        $this->sendTerminal(" - Found " . count($tasks) . " tasks");
        //endregion

        //region DISPLAY tasks report
        $this->displayTasksReport($tasks, $project_id);
        //endregion

        return true;
    }

    /**
     * List tasks for a specific milestone
     */
    public function METHOD_milestone(): bool
    {
        //region VALIDATE milestone ID
        $milestone_id = $this->formParams['id'] ?? null;
        if (!$milestone_id) {
            return $this->addError("Missing required parameter: id. Usage: _cloudia/tasks/milestone?id=MILESTONE_KEYID");
        }
        //endregion

        //region FETCH milestone info
        $this->sendTerminal("");
        $this->sendTerminal("Fetching milestone [{$milestone_id}]...");

        $milestone_response = $this->core->request->get_json_decode(
            "{$this->api_base_url}/core/cfo/cfi/CloudFrameWorkProjectsMilestones/display/{$milestone_id}?_raw&_timezone=UTC",
            ['_raw' => 1, '_timezone' => 'UTC'],
            $this->headers
        );

        if (!$this->core->request->error && ($milestone_response['data'] ?? null)) {
            $milestone = $milestone_response['data'];
            $milestoneTitle = $milestone['Title'] ?? 'Untitled Milestone';
            $milestoneStatus = $milestone['Status'] ?? '';
            $milestoneDeadline = $milestone['DateDeadLine'] ?? '';
            $this->sendTerminal("");
            $this->sendTerminal("Milestone: {$milestoneTitle}");
            if ($milestoneStatus) $this->sendTerminal("Status: {$milestoneStatus}");
            if ($milestoneDeadline) $this->sendTerminal("Deadline: {$milestoneDeadline}");
        }
        //endregion

        //region FETCH tasks for the milestone
        $this->sendTerminal(str_repeat('-', 100));

        $params = [
            'filter_MilestoneId' => $milestone_id,
            '_order' => '-Priority,Status,DateDeadLine',
            'cfo_limit' => 500,
            '_raw' => 1,
            '_timezone' => 'UTC'
        ];

        $response = $this->core->request->get_json_decode(
            "{$this->api_base_url}/core/cfo/cfi/CloudFrameWorkProjectsTasks?_raw&_timezone=UTC",
            $params,
            $this->headers
        );

        if ($this->core->request->error) {
            return $this->addError($this->core->request->errorMsg);
        }

        $tasks = $response['data'] ?? [];
        $this->displayTaskList($tasks);
        //endregion

        return true;
    }

    /**
     * List tasks for a specific person
     */
    public function METHOD_person()
    {
        //region VALIDATE email parameter
        $email = $this->formParams['email'] ?? null;
        if (!$email) {
            return $this->addError("Missing required parameter: email. Usage: _cloudia/tasks/person?email=user@example.com");
        }
        //endregion

        //region SET filter options from parameters
        $only_open = ($this->formParams['open'] ?? 'true') === 'true';
        $status = $this->formParams['status'] ?? null;
        $project = $this->formParams['project'] ?? null;
        //endregion

        //region FETCH tasks for the person
        $this->sendTerminal("");
        $this->sendTerminal("Tasks for person [{$email}]" . ($only_open ? " (open only)" : "") . ":");
        $this->sendTerminal(str_repeat('-', 100));

        $params = [
            'filter_PlayerId' => $email,
            '_order' => '-Priority,Status,DateDeadLine',
            'cfo_limit' => 500,
            '_raw' => 1,
            '_timezone' => 'UTC'
        ];

        // Add optional filters
        if ($only_open) {
            $params['filter_Open'] = 'true';
        }
        if ($status) {
            $params['filter_Status'] = $status;
        }
        if ($project) {
            $params['filter_ProjectId'] = $project;
        }

        $response = $this->core->request->get_json_decode(
            "{$this->api_base_url}/core/cfo/cfi/CloudFrameWorkProjectsTasks?_raw&_timezone=UTC",
            $params,
            $this->headers
        );

        if ($this->core->request->error) {
            return $this->addError($this->core->request->errorMsg);
        }

        $tasks = $response['data'] ?? [];
        $this->displayTaskList($tasks, $email);
        //endregion

        return true;
    }

    /**
     * Export task and checks JSON to local file
     *
     * Saves the task data and associated checks to ./local_data/_cloudia/tasks/{task_id}.json
     * Structure:
     *   - CloudFrameWorkProjectsTasks: task data
     *   - CloudFrameWorkDevDocumentationForProcessTests: associated checks
     */
    public function METHOD_get()
    {
        //region VALIDATE task ID
        $task_id = $this->formParams['id'] ?? null;
        if (!$task_id) {
            return $this->addError("Missing required parameter: id. Usage: _cloudia/tasks/get?id=TASK_KEYID");
        }
        //endregion

        //region FETCH task details using display endpoint
        $this->sendTerminal("");
        $this->sendTerminal("Fetching task [{$task_id}]...");

        $response = $this->core->request->get_json_decode(
            "{$this->api_base_url}/core/cfo/cfi/CloudFrameWorkProjectsTasks/display/{$task_id}?_raw&_timezone=UTC",
            ['_raw' => 1, '_timezone' => 'UTC'],
            $this->headers
        );

        if ($this->core->request->error) {
            return $this->addError($this->core->request->errorMsg);
        }

        $task = $response['data'] ?? null;
        if (!$task) {
            return $this->addError("Task [{$task_id}] not found");
        }
        $this->sendTerminal(" - Task found: {$task['Title']}");
        //endregion

        //region FETCH associated checks
        $this->sendTerminal(" - Fetching associated checks...");

        $checks_response = $this->core->request->get_json_decode(
            "{$this->api_base_url}/core/cfo/cfi/CloudFrameWorkDevDocumentationForProcessTests?_raw&_timezone=UTC",
            [
                'filter_CFOEntity' => 'CloudFrameWorkProjectsTasks',
                'filter_CFOId' => $task_id,
                '_order' => 'Route',
                'cfo_limit' => 200,
                '_raw' => 1,
                '_timezone' => 'UTC'
            ],
            $this->headers
        );

        $checks = [];
        if (!$this->core->request->error && ($checks_response['data'] ?? null)) {
            $checks = $checks_response['data'];
            // Sort checks by KeyId
            foreach ($checks as &$check) {
                ksort($check);
            }
            usort($checks, function ($a, $b) {
                return strcmp($a['KeyId'] ?? '', $b['KeyId'] ?? '');
            });
        }
        $this->sendTerminal(" - Checks found: " . count($checks));
        //endregion

        //region CREATE output directory if not exists
        $output_dir = $this->core->system->root_path . '/local_data/_cloudia/tasks';

        // Create directories recursively if they don't exist
        if (!is_dir($output_dir)) {
            if (!mkdir($output_dir, 0755, true)) {
                return $this->addError("Failed to create directory: {$output_dir}");
            }
            $this->sendTerminal(" - Created directory: ./local_data/_cloudia/tasks");
        }
        //endregion

        //region BUILD data structure and WRITE to file
        // Remove TimeSpent from exported JSON (it's a calculated field from activity inputs/events)
        // TimeSpent must be managed via _cloudia/activity script, not directly in task JSON
        unset($task['TimeSpent']);

        // Sort task keys
        ksort($task);

        $data = [
            'CloudFrameWorkProjectsTasks' => $task,
            'CloudFrameWorkDevDocumentationForProcessTests' => $checks
        ];

        $filepath = "{$output_dir}/{$task_id}.json";
        $json_content = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if (file_put_contents($filepath, $json_content) === false) {
            return $this->addError("Failed to write file: {$filepath}");
        }

        $this->sendTerminal(" + Saved: ./local_data/_cloudia/tasks/{$task_id}.json");
        $this->sendTerminal("");
        $this->sendTerminal("Task: {$task['Title']}");
        $this->sendTerminal("Project: {$task['ProjectId']}");
        $milestone = $task['MilestoneId'] ?? null;
        if ($milestone) {
            $this->sendTerminal("Milestone: {$milestone}");
        }
        $this->sendTerminal("Status: {$task['Status']}");
        $this->sendTerminal("Checks: " . count($checks));
        //endregion

        return true;
    }

    /**
     * Show detailed task information including associated checks
     */
    public function METHOD_show()
    {
        //region VALIDATE task ID
        $task_id = $this->formParams['id'] ?? null;
        if (!$task_id) {
            return $this->addError("Missing required parameter: id. Usage: _cloudia/tasks/show?id=TASK_KEYID");
        }
        //endregion

        //region FETCH task details using display endpoint
        $this->sendTerminal("");
        $this->sendTerminal("Task Details [{$task_id}]:");
        $this->sendTerminal(str_repeat('=', 100));

        $response = $this->core->request->get_json_decode(
            "{$this->api_base_url}/core/cfo/cfi/CloudFrameWorkProjectsTasks/display/{$task_id}?_raw&_timezone=UTC",
            ['_raw' => 1, '_timezone' => 'UTC'],
            $this->headers
        );

        if ($this->core->request->error) {
            return $this->addError($this->core->request->errorMsg);
        }

        $task = $response['data'] ?? null;
        if (!$task) {
            return $this->addError("Task [{$task_id}] not found");
        }

        $this->displayTaskDetail($task);
        //endregion

        //region FETCH and DISPLAY project info (if linked)
        $project_id = $task['ProjectId'] ?? null;
        if ($project_id) {
            $project_response = $this->core->request->get_json_decode(
                "{$this->api_base_url}/core/cfo/cfi/CloudFrameWorkProjectsEntries/display/" . urlencode($project_id) . "?_raw&_timezone=UTC",
                ['_raw' => 1, '_timezone' => 'UTC'],
                $this->headers
            );

            if (!$this->core->request->error && ($project_response['data'] ?? null)) {
                $project = $project_response['data'];
                $this->sendTerminal("");
                $this->sendTerminal(" Project Info:");
                $this->sendTerminal(str_repeat('-', 100));
                $this->sendTerminal(" Title: {$project['Title']}");
                if ($projectType = $project['Type'] ?? null) {
                    $this->sendTerminal(" Type: {$projectType}");
                }
                if ($projectStatus = $project['Status'] ?? null) {
                    $projectOpen = ($project['Open'] ?? false) ? 'Open' : 'Closed';
                    $this->sendTerminal(" Status: {$projectStatus} ({$projectOpen})");
                }
                if ($projectDescription = $project['Description'] ?? null) {
                    $cleanDesc = strip_tags($projectDescription);
                    $cleanDesc = html_entity_decode($cleanDesc);
                    $cleanDesc = preg_replace('/\s+/', ' ', trim($cleanDesc));
                    if (strlen($cleanDesc) > 300) {
                        $cleanDesc = substr($cleanDesc, 0, 297) . '...';
                    }
                    $this->sendTerminal(" Description: " . $cleanDesc);
                }
            }
        }
        //endregion

        //region FETCH and DISPLAY milestone info (if linked)
        $milestone_id = $task['MilestoneId'] ?? null;
        if ($milestone_id) {
            $milestone_response = $this->core->request->get_json_decode(
                "{$this->api_base_url}/core/cfo/cfi/CloudFrameWorkProjectsMilestones/display/{$milestone_id}?_raw&_timezone=UTC",
                ['_raw' => 1, '_timezone' => 'UTC'],
                $this->headers
            );

            if (!$this->core->request->error && ($milestone_response['data'] ?? null)) {
                $milestone = $milestone_response['data'];
                $this->sendTerminal("");
                $this->sendTerminal(" Milestone Info:");
                $this->sendTerminal(str_repeat('-', 100));
                $this->sendTerminal(" Title: {$milestone['Title']}");
                if ($milestoneStatus = $milestone['Status'] ?? null) {
                    $this->sendTerminal(" Status: {$milestoneStatus}");
                }
                if ($milestoneDeadline = $milestone['DateDeadLine'] ?? null) {
                    $this->sendTerminal(" Deadline: {$milestoneDeadline}");
                }
                if ($milestoneDescription = $milestone['Description'] ?? null) {
                    $cleanDesc = strip_tags($milestoneDescription);
                    $cleanDesc = html_entity_decode($cleanDesc);
                    $cleanDesc = preg_replace('/\s+/', ' ', trim($cleanDesc));
                    if (strlen($cleanDesc) > 300) {
                        $cleanDesc = substr($cleanDesc, 0, 297) . '...';
                    }
                    $this->sendTerminal(" Description: " . $cleanDesc);
                }
            }
        }
        //endregion

        //region FETCH and DISPLAY WebApps info (if linked)
        $webapps = $task['WebApps'] ?? [];
        if (!is_array($webapps)) {
            $webapps = $webapps ? [$webapps] : [];
        }

        if (!empty($webapps)) {
            $this->sendTerminal("");
            $this->sendTerminal(" WebApps Info (" . count($webapps) . "):");
            $this->sendTerminal(str_repeat('-', 100));

            foreach ($webapps as $webapp_id) {
                if (!$webapp_id) continue;

                $webapp_response = $this->core->request->get_json_decode(
                    "{$this->api_base_url}/core/cfo/cfi/CloudFrameWorkDevDocumentationForWebApps/display/" . urlencode($webapp_id) . "?_raw&_timezone=UTC",
                    ['_raw' => 1, '_timezone' => 'UTC'],
                    $this->headers
                );

                if (!$this->core->request->error && ($webapp_response['data'] ?? null)) {
                    $webapp = $webapp_response['data'];
                    $this->sendTerminal(" [{$webapp_id}]");
                    $this->sendTerminal("   Title: {$webapp['Title']}");
                    if ($webappStatus = $webapp['Status'] ?? null) {
                        $this->sendTerminal("   Status: {$webappStatus}");
                    }
                    if ($webappType = $webapp['Type'] ?? null) {
                        $this->sendTerminal("   Type: {$webappType}");
                    }
                    if ($webappDescription = $webapp['Description'] ?? null) {
                        $cleanDesc = strip_tags($webappDescription);
                        $cleanDesc = html_entity_decode($cleanDesc);
                        $cleanDesc = preg_replace('/\s+/', ' ', trim($cleanDesc));
                        if (strlen($cleanDesc) > 300) {
                            $cleanDesc = substr($cleanDesc, 0, 297) . '...';
                        }
                        $this->sendTerminal("   Description: " . $cleanDesc);
                    }
                    $this->sendTerminal("");
                } else {
                    $this->sendTerminal(" [{$webapp_id}] - Not found or error fetching");
                    $this->sendTerminal("");
                }
            }
        }
        //endregion

        //region FETCH and DISPLAY associated checks
        $this->sendTerminal("");
        $this->sendTerminal(" Checks:");
        $this->sendTerminal(str_repeat('-', 100));

        $checks_response = $this->core->request->get_json_decode(
            "{$this->api_base_url}/core/cfo/cfi/CloudFrameWorkDevDocumentationForProcessTests?_raw&_timezone=UTC",
            [
                'filter_CFOEntity' => 'CloudFrameWorkProjectsTasks',
                'filter_CFOId' => $task_id,
                '_order' => 'Route',
                'cfo_limit' => 100,
                '_raw' => 1,
                '_timezone' => 'UTC'
            ],
            $this->headers
        );

        if ($this->core->request->error) {
            $this->sendTerminal(" # Error fetching checks: " . json_encode($this->core->request->errorMsg));
        } else {
            $checks = $checks_response['data'] ?? [];
            if (!$checks) {
                $this->sendTerminal(" No checks found for this task");
            } else {
                $this->sendTerminal(" Found " . count($checks) . " check(s):");
                $this->sendTerminal("");

                foreach ($checks as $index => $check) {
                    $checkKeyId = $check['KeyId'] ?? 'N/A';
                    $checkRoute = $check['Route'] ?? '';
                    $checkTitle = $check['Title'] ?? 'Untitled';
                    $checkStatus = $check['Status'] ?? 'N/A';
                    $checkDescription = $check['Description'] ?? '';

                    $this->sendTerminal(sprintf(" [%d] %s", $index + 1, $checkTitle));
                    $this->sendTerminal(sprintf("     KeyId: %s | Route: %s | Status: %s", $checkKeyId, $checkRoute, $checkStatus));

                    // Show check description if available
                    if ($checkDescription) {
                        // Clean and format description
                        $cleanDesc = strip_tags($checkDescription);
                        $cleanDesc = html_entity_decode($cleanDesc);
                        $cleanDesc = preg_replace('/\s+/', ' ', trim($cleanDesc));
                        if (strlen($cleanDesc) > 200) {
                            $cleanDesc = substr($cleanDesc, 0, 197) . '...';
                        }
                        $this->sendTerminal("     Description: " . $cleanDesc);
                    }

                    // Show additional check fields if present
                    if ($checkExpected = $check['Expected'] ?? null) {
                        $cleanExpected = strip_tags($checkExpected);
                        $cleanExpected = html_entity_decode($cleanExpected);
                        $cleanExpected = preg_replace('/\s+/', ' ', trim($cleanExpected));
                        if (strlen($cleanExpected) > 200) {
                            $cleanExpected = substr($cleanExpected, 0, 197) . '...';
                        }
                        $this->sendTerminal("     Expected: " . $cleanExpected);
                    }

                    if ($checkSteps = $check['Steps'] ?? null) {
                        $cleanSteps = strip_tags($checkSteps);
                        $cleanSteps = html_entity_decode($cleanSteps);
                        $cleanSteps = preg_replace('/\s+/', ' ', trim($cleanSteps));
                        if (strlen($cleanSteps) > 200) {
                            $cleanSteps = substr($cleanSteps, 0, 197) . '...';
                        }
                        $this->sendTerminal("     Steps: " . $cleanSteps);
                    }

                    $this->sendTerminal("");
                }
            }
        }
        $this->sendTerminal(str_repeat('=', 100));
        //endregion

        return true;
    }

    /**
     * Update a task and checks from local JSON file
     *
     * Reads the task data from ./local_data/_cloudia/tasks/{task_id}.json
     * and updates both the task and associated checks in the remote platform.
     *
     * Validates:
     * - KeyId in file matches the id parameter
     * - Compares with remote to avoid unnecessary updates
     *
     * Check handling:
     * - Checks with KeyId that exist in remote: compared and updated if different
     * - Checks without KeyId: inserted as new checks
     * - Checks in remote but not in local: requires delete=yes|no parameter
     *   - If delete=yes: deletes remote checks not present in local
     *   - If delete=no: skips deletion, only updates/inserts
     *   - If not specified: shows warning and requires re-run with parameter
     *
     * Auto-refresh:
     * - After any changes (task/checks updated/inserted/deleted), automatically
     *   refreshes the local file with the latest data from remote
     * - This ensures new checks get their assigned KeyIds in the local file
     *
     * Required: 'id' parameter with the task KeyId
     * Optional: 'delete' parameter (yes|no) for remote check deletion
     *
     * Usage:
     *   _cloudia/tasks/update?id=TASK_KEYID
     *   _cloudia/tasks/update?id=TASK_KEYID&delete=yes
     *   _cloudia/tasks/update?id=TASK_KEYID&delete=no
     */
    public function METHOD_update(): bool
    {
        //region VALIDATE task ID
        $task_id = $this->formParams['id'] ?? null;
        if (!$task_id) {
            return $this->addError("Missing required parameter: id. Usage: _cloudia/tasks/update?id=TASK_KEYID");
        }
        //endregion

        //region READ JSON file from local_data
        $filepath = $this->core->system->root_path . "/local_data/_cloudia/tasks/{$task_id}.json";

        $this->sendTerminal("");
        $this->sendTerminal("Updating task [{$task_id}] from local file...");
        $this->sendTerminal(str_repeat('-', 100));

        if (!is_file($filepath)) {
            return $this->addError("Local file not found: ./local_data/_cloudia/tasks/{$task_id}.json. Use 'get' command first to export the task.");
        }

        $json_content = file_get_contents($filepath);
        if ($json_content === false) {
            return $this->addError("Failed to read file: {$filepath}");
        }

        $data = json_decode($json_content, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return $this->addError("Invalid JSON in file: " . json_last_error_msg());
        }

        $local_task = $data['CloudFrameWorkProjectsTasks'] ?? null;
        $local_checks = $data['CloudFrameWorkDevDocumentationForProcessTests'] ?? [];

        if (!$local_task) {
            return $this->addError("Invalid file structure: missing 'CloudFrameWorkProjectsTasks' node");
        }

        // Validate KeyId matches
        $file_key_id = $local_task['KeyId'] ?? null;
        if (!$file_key_id) {
            return $this->addError("Invalid task data: missing 'KeyId' in CloudFrameWorkProjectsTasks");
        }
        if ($file_key_id !== $task_id) {
            return $this->addError("KeyId mismatch: file contains KeyId '{$file_key_id}' but parameter id is '{$task_id}'");
        }

        $this->sendTerminal(" - File loaded: ./local_data/_cloudia/tasks/{$task_id}.json");
        $this->sendTerminal(" - Task: {$local_task['Title']}");
        $this->sendTerminal(" - Checks in file: " . count($local_checks));
        //endregion

        //region VALIDATE TimeSpent is NOT in local file (it's a calculated field)
        if (array_key_exists('TimeSpent', $local_task)) {
            $this->sendTerminal("");
            $this->sendTerminal(" !! ERROR: TimeSpent field found in local JSON file");
            $this->sendTerminal("");
            $this->sendTerminal("    TimeSpent is a CALCULATED field that cannot be set manually.");
            $this->sendTerminal("    It is automatically calculated from activity inputs and events.");
            $this->sendTerminal("");
            $this->sendTerminal("    To report time spent on this task, use:");
            $this->sendTerminal("      composer script -- \"_cloudia/activity/report-input?json={\\\"TimeSpent\\\":{$local_task['TimeSpent']},\\\"Title\\\":\\\"Description\\\",\\\"TaskId\\\":\\\"{$task_id}\\\"}\"");
            $this->sendTerminal("");
            $this->sendTerminal("    Remove the 'TimeSpent' field from the JSON file and try again.");
            $this->sendTerminal("");
            return $this->addError("TimeSpent field must not be in the JSON file. Use _cloudia/activity/report-input to report time.");
        }
        //endregion

        //region VALIDATE task required fields
        $this->sendTerminal("");
        $this->sendTerminal(" - Validating task required fields...");

        $taskValidation = $this->validateTaskFields($local_task, $task_id);

        // Show task warnings
        foreach ($taskValidation['warnings'] as $warning) {
            $this->sendTerminal("   ! WARNING: {$warning}");
        }

        // Show task errors
        foreach ($taskValidation['errors'] as $error) {
            $this->sendTerminal("   # ERROR: {$error}");
        }

        if (!$taskValidation['valid']) {
            $this->sendTerminal("");
            $this->sendTerminal(" !! TASK VALIDATION FAILED");
            $this->sendTerminal("    Required task fields: KeyId, Title, ProjectId, Status");
            $this->sendTerminal("");
            return $this->addError("Task field validation failed. Ensure all required fields are present.");
        }

        if (empty($taskValidation['warnings']) && empty($taskValidation['errors'])) {
            $this->sendTerminal("   + Task fields validated successfully");
        }
        //endregion

        //region VALIDATE check required fields
        if (!empty($local_checks)) {
            $this->sendTerminal("");
            $this->sendTerminal(" - Validating check required fields...");

            $checksValidation = $this->validateAllChecks($local_checks, $task_id);

            // Show check warnings
            foreach ($checksValidation['warnings'] as $warning) {
                $this->sendTerminal("   ! WARNING: {$warning}");
            }

            // Show check errors
            foreach ($checksValidation['errors'] as $error) {
                $this->sendTerminal("   # ERROR: {$error}");
            }

            if (!$checksValidation['valid']) {
                $this->sendTerminal("");
                $this->sendTerminal(" !! CHECK VALIDATION FAILED");
                $this->sendTerminal("    Required check fields: Title, Status, Route");
                $this->sendTerminal("    For existing checks (with KeyId): CFOEntity=CloudFrameWorkProjectsTasks, CFOId=TaskKeyId");
                $this->sendTerminal("");
                return $this->addError("Check field validation failed. Ensure all required fields are present.");
            }

            if (empty($checksValidation['warnings']) && empty($checksValidation['errors'])) {
                $this->sendTerminal("   + Check fields validated successfully");
            }
        }
        //endregion

        //region VALIDATE JSON routes vs CHECK routes
        $json_field = $local_task['JSON'] ?? [];
        if (!empty($json_field) || !empty($local_checks)) {
            $this->sendTerminal("");
            $this->sendTerminal(" - Validating JSON routes with CHECK routes...");

            $validation = $this->validateJSONRoutesWithChecks($json_field, $local_checks, $task_id);

            // Show warnings (JSON routes without matching checks)
            foreach ($validation['warnings'] as $warning) {
                $this->sendTerminal("   ! WARNING: {$warning}");
            }

            // Show errors (CHECK routes without matching JSON routes)
            foreach ($validation['errors'] as $error) {
                $this->sendTerminal("   # ERROR: {$error}");
            }

            if (!$validation['valid']) {
                $this->sendTerminal("");
                $this->sendTerminal(" !! VALIDATION FAILED: Each CHECK's Route must have a corresponding entry in the JSON field.");
                $this->sendTerminal("    The JSON field should have leaf nodes with 'route' attributes matching CHECK Routes.");
                $this->sendTerminal("");
                $this->sendTerminal(" Expected JSON structure:");
                $this->sendTerminal('    {');
                $this->sendTerminal('        "Category Name": {');
                $this->sendTerminal('            "Check Title": {"route": "/check-route-value"}');
                $this->sendTerminal('        }');
                $this->sendTerminal('    }');
                $this->sendTerminal("");
                return $this->addError("JSON/CHECK route validation failed. Fix the JSON field to include routes for all CHECKs.");
            }

            if (empty($validation['warnings']) && empty($validation['errors'])) {
                $this->sendTerminal("   + Routes validated successfully");
            }
        }
        //endregion

        //region FETCH remote task for comparison
        $this->sendTerminal("");
        $this->sendTerminal(" - Fetching remote data for comparison...");

        $remote_response = $this->core->request->get_json_decode(
            "{$this->api_base_url}/core/cfo/cfi/CloudFrameWorkProjectsTasks/display/{$task_id}?_raw&_timezone=UTC",
            ['_raw' => 1, '_timezone' => 'UTC'],
            $this->headers
        );

        if ($this->core->request->error) {
            return $this->addError("API Error fetching remote task: " . json_encode($this->core->request->errorMsg));
        }

        $remote_task = $remote_response['data'] ?? null;
        if (!$remote_task) {
            return $this->addError("Task [{$task_id}] not found in remote platform");
        }
        //endregion

        //region FETCH remote checks and ANALYZE differences
        $checks_response = $this->core->request->get_json_decode(
            "{$this->api_base_url}/core/cfo/cfi/CloudFrameWorkDevDocumentationForProcessTests?_raw&_timezone=UTC",
            [
                'filter_CFOEntity' => 'CloudFrameWorkProjectsTasks',
                'filter_CFOId' => $task_id,
                'cfo_limit' => 200,
                '_raw' => 1,
                '_timezone' => 'UTC'
            ],
            $this->headers
        );

        $remote_checks = $checks_response['data'] ?? [];
        $this->sendTerminal(" - Remote checks: " . count($remote_checks));

        // Index remote checks by KeyId
        $remote_indexed = [];
        foreach ($remote_checks as $check) {
            $remote_indexed[$check['KeyId']] = $check;
        }

        // Index local checks by KeyId (only those that have KeyId)
        $local_indexed = [];
        $local_new_checks = []; // Checks without KeyId (to be inserted)
        foreach ($local_checks as $check) {
            if ($keyId = $check['KeyId'] ?? null) {
                $local_indexed[$keyId] = $check;
            } else {
                $local_new_checks[] = $check;
            }
        }

        // Identify checks to delete (exist in remote but not in local)
        $checks_to_delete = [];
        foreach ($remote_indexed as $keyId => $remote_check) {
            if (!isset($local_indexed[$keyId])) {
                $checks_to_delete[$keyId] = $remote_check;
            }
        }
        //endregion

        //region CHECK if delete confirmation is needed
        if (!empty($checks_to_delete)) {
            $delete_param = $this->formParams['delete'] ?? null;

            if ($delete_param === null) {
                // Show checks that will be deleted and require confirmation
                $this->sendTerminal("");
                $this->sendTerminal(" !! WARNING: The following checks exist in REMOTE but NOT in LOCAL:");
                $this->sendTerminal(str_repeat('-', 100));
                foreach ($checks_to_delete as $keyId => $check) {
                    $this->sendTerminal("    - [{$keyId}] {$check['Title']}");
                }
                $this->sendTerminal(str_repeat('-', 100));
                $this->sendTerminal("");
                $this->sendTerminal(" These checks will be DELETED from remote if you proceed.");
                $this->sendTerminal(" To confirm deletion, re-run the command with: delete=yes");
                $this->sendTerminal(" To skip deletion (only update/insert), re-run with: delete=no");
                $this->sendTerminal("");
                $this->sendTerminal(" Example:");
                $this->sendTerminal("   composer script -- \"_cloudia/tasks/update?id={$task_id}&delete=yes\"");
                $this->sendTerminal("   composer script -- \"_cloudia/tasks/update?id={$task_id}&delete=no\"");
                $this->sendTerminal("");
                return $this->addError("Delete confirmation required. Use delete=yes or delete=no parameter.");
            }

            $allow_delete = ($delete_param === 'yes');
            if (!$allow_delete && $delete_param !== 'no') {
                return $this->addError("Invalid delete parameter value. Use delete=yes or delete=no");
            }

            if ($allow_delete) {
                $this->sendTerminal(" - Delete parameter: yes (will delete " . count($checks_to_delete) . " remote checks)");
            } else {
                $this->sendTerminal(" - Delete parameter: no (skipping deletion of " . count($checks_to_delete) . " remote checks)");
            }
        } else {
            $allow_delete = false; // No checks to delete
        }
        //endregion

        //region CALCULATE TimeSpent from activity inputs and events
        $this->sendTerminal("");
        $this->sendTerminal(" - Calculating TimeSpent from activity inputs and events...");

        $timeSpentResult = $this->calculateTimeSpentForTask($task_id);

        if (!$timeSpentResult['success']) {
            $this->sendTerminal("   ! WARNING: Could not calculate TimeSpent: {$timeSpentResult['error']}");
            $this->sendTerminal("   ! TimeSpent will not be updated");
            $calculatedTimeSpent = null;
        } else {
            $calculatedTimeSpent = $timeSpentResult['timeSpent'];
            $this->sendTerminal("   + Inputs: {$timeSpentResult['inputsCount']} records, {$timeSpentResult['inputsHours']} hours");
            $this->sendTerminal("   + Events: {$timeSpentResult['eventsCount']} records, {$timeSpentResult['eventsHours']} hours");
            $this->sendTerminal("   + Total TimeSpent: {$calculatedTimeSpent} hours");
        }
        //endregion

        //region COMPARE and UPDATE task if different
        // Remove TimeSpent from remote for comparison (since local doesn't have it)
        $remote_task_for_compare = $remote_task;
        unset($remote_task_for_compare['TimeSpent']);

        // Sort both arrays by key for proper comparison
        ksort($local_task);
        ksort($remote_task_for_compare);

        $task_updated = false;
        $updated_task = $remote_task; // Default to remote if no update needed

        // Check if task fields changed (excluding TimeSpent) or if TimeSpent needs update
        $fieldsChanged = json_encode($local_task) !== json_encode($remote_task_for_compare);
        $timeSpentChanged = $calculatedTimeSpent !== null &&
                            floatval($remote_task['TimeSpent'] ?? 0) !== $calculatedTimeSpent;

        if ($fieldsChanged || $timeSpentChanged) {
            if ($fieldsChanged) {
                $this->sendTerminal(" - Changes detected in task fields, updating...");
            }
            if ($timeSpentChanged) {
                $oldTimeSpent = floatval($remote_task['TimeSpent'] ?? 0);
                $this->sendTerminal(" - TimeSpent changed: {$oldTimeSpent} -> {$calculatedTimeSpent}");
            }

            // Build update data: local_task + calculated TimeSpent
            $update_data = $local_task;
            if ($calculatedTimeSpent !== null) {
                $update_data['TimeSpent'] = $calculatedTimeSpent;
            }

            $response = $this->core->request->put_json_decode(
                "{$this->api_base_url}/core/cfo/cfi/CloudFrameWorkProjectsTasks/{$task_id}?_raw&_timezone=UTC",
                $update_data,
                $this->headers,
                true
            );

            if ($this->core->request->error) {
                return $this->addError("API Error: " . json_encode($this->core->request->errorMsg));
            }

            if (!($response['success'] ?? false)) {
                $errorMsg = $response['errorMsg'] ?? $response['error'] ?? 'Unknown error';
                if (is_array($errorMsg)) {
                    $errorMsg = implode(', ', $errorMsg);
                }
                return $this->addError("Task update failed: {$errorMsg}");
            }

            $updated_task = $response['data'] ?? $update_data;
            $task_updated = true;
            $this->sendTerminal(" + Task updated successfully");
        } else {
            $this->sendTerminal(" - No changes in task, skipping update");
        }
        //endregion

        //region SYNC checks with remote platform
        $this->sendTerminal("");
        $this->sendTerminal(" - Syncing checks...");

        $checks_updated = 0;
        $checks_inserted = 0;
        $checks_deleted = 0;
        $checks_unchanged = 0;
        $checks_skipped = 0;

        // Process remote checks: update, delete, or skip
        foreach ($remote_indexed as $keyId => $remote_check) {
            if (!isset($local_indexed[$keyId])) {
                // Remote check not in local - delete or skip based on parameter
                if ($allow_delete) {
                    $this->sendTerminal("   - Deleting check: {$remote_check['Title']}");
                    $this->core->request->delete(
                        "{$this->api_base_url}/core/cfo/cfi/CloudFrameWorkDevDocumentationForProcessTests/{$keyId}?_raw",
                        $this->headers
                    );
                    $checks_deleted++;
                } else {
                    $checks_skipped++;
                }
            } else {
                // Check exists in both - compare and update if different
                $local_check_sorted = $local_indexed[$keyId];
                $remote_check_sorted = $remote_check;
                ksort($local_check_sorted);
                ksort($remote_check_sorted);

                if (json_encode($local_check_sorted) !== json_encode($remote_check_sorted)) {
                    $this->sendTerminal("   - Updating check: {$local_indexed[$keyId]['Title']}");
                    $this->core->request->put_json_decode(
                        "{$this->api_base_url}/core/cfo/cfi/CloudFrameWorkDevDocumentationForProcessTests/{$keyId}?_raw&_timezone=UTC",
                        $local_indexed[$keyId],
                        $this->headers,
                        true
                    );
                    $checks_updated++;
                } else {
                    $checks_unchanged++;
                }
                unset($local_indexed[$keyId]);
            }
        }

        // Insert checks that have KeyId but don't exist in remote
        foreach ($local_indexed as $keyId => $local_check) {
            $this->sendTerminal("   - Inserting check: {$local_check['Title']}");
            unset($local_check['KeyId']); // Remove KeyId for insert
            $this->core->request->post_json_decode(
                "{$this->api_base_url}/core/cfo/cfi/CloudFrameWorkDevDocumentationForProcessTests?_raw&_timezone=UTC",
                $local_check,
                $this->headers
            );
            $checks_inserted++;
        }

        // Insert new checks (those without KeyId)
        foreach ($local_new_checks as $local_check) {
            $this->sendTerminal("   - Inserting new check: {$local_check['Title']}");
            // Ensure CFOEntity and CFOId are set for new checks
            $local_check['CFOEntity'] = 'CloudFrameWorkProjectsTasks';
            $local_check['CFOId'] = $task_id;
            $this->core->request->post_json_decode(
                "{$this->api_base_url}/core/cfo/cfi/CloudFrameWorkDevDocumentationForProcessTests?_raw&_timezone=UTC",
                $local_check,
                $this->headers
            );
            $checks_inserted++;
        }

        // Build summary message
        $summary_parts = [];
        if ($checks_updated > 0) $summary_parts[] = "{$checks_updated} updated";
        if ($checks_inserted > 0) $summary_parts[] = "{$checks_inserted} inserted";
        if ($checks_deleted > 0) $summary_parts[] = "{$checks_deleted} deleted";
        if ($checks_unchanged > 0) $summary_parts[] = "{$checks_unchanged} unchanged";
        if ($checks_skipped > 0) $summary_parts[] = "{$checks_skipped} skipped (not deleted)";

        $this->sendTerminal(" + Checks: " . implode(', ', $summary_parts));
        //endregion

        //region SHOW summary
        $this->sendTerminal("");
        $this->sendTerminal(str_repeat('-', 100));

        $has_changes = $task_updated || $checks_updated > 0 || $checks_inserted > 0 || $checks_deleted > 0;

        if ($has_changes) {
            $this->sendTerminal("Update completed!");
        } else {
            $this->sendTerminal("No changes detected - nothing to update");
        }

        $this->sendTerminal(" - Title: {$updated_task['Title']}");
        $this->sendTerminal(" - Project: {$updated_task['ProjectId']}");
        $milestone = $updated_task['MilestoneId'] ?? null;
        if ($milestone) {
            $this->sendTerminal(" - Milestone: {$milestone}");
        }
        $this->sendTerminal(" - Status: {$updated_task['Status']}");
        if ($task_updated) {
            $this->sendTerminal(" - Updated: {$updated_task['DateUpdating']}");
        }
        //endregion

        //region REFRESH local file if changes were made
        if ($has_changes) {
            $this->sendTerminal("");
            $this->sendTerminal(" - Refreshing local file with remote data...");

            // Fetch updated task from remote
            $refresh_task_response = $this->core->request->get_json_decode(
                "{$this->api_base_url}/core/cfo/cfi/CloudFrameWorkProjectsTasks/display/{$task_id}?_raw&_timezone=UTC",
                ['_raw' => 1, '_timezone' => 'UTC'],
                $this->headers
            );

            if (!$this->core->request->error && ($refresh_task_response['data'] ?? null)) {
                $refreshed_task = $refresh_task_response['data'];
                ksort($refreshed_task);

                // Fetch updated checks from remote
                $refresh_checks_response = $this->core->request->get_json_decode(
                    "{$this->api_base_url}/core/cfo/cfi/CloudFrameWorkDevDocumentationForProcessTests?_raw&_timezone=UTC",
                    [
                        'filter_CFOEntity' => 'CloudFrameWorkProjectsTasks',
                        'filter_CFOId' => $task_id,
                        '_order' => 'Route',
                        'cfo_limit' => 200,
                        '_raw' => 1,
                        '_timezone' => 'UTC'
                    ],
                    $this->headers
                );

                $refreshed_checks = [];
                if (!$this->core->request->error && ($refresh_checks_response['data'] ?? null)) {
                    $refreshed_checks = $refresh_checks_response['data'];
                    // Sort checks by KeyId
                    foreach ($refreshed_checks as &$check) {
                        ksort($check);
                    }
                    usort($refreshed_checks, function ($a, $b) {
                        return strcmp($a['KeyId'] ?? '', $b['KeyId'] ?? '');
                    });
                }

                // Build and save refreshed data
                $refreshed_data = [
                    'CloudFrameWorkProjectsTasks' => $refreshed_task,
                    'CloudFrameWorkDevDocumentationForProcessTests' => $refreshed_checks
                ];

                $json_content = json_encode($refreshed_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                if (file_put_contents($filepath, $json_content) !== false) {
                    $this->sendTerminal(" + Local file updated: ./local_data/_cloudia/tasks/{$task_id}.json");
                } else {
                    $this->sendTerminal(" # Warning: Failed to update local file");
                }
            } else {
                $this->sendTerminal(" # Warning: Failed to refresh data from remote");
            }
        }
        //endregion

        $this->sendTerminal(str_repeat('=', 100));

        return true;
    }

    /**
     * Create a new task with basic parameters
     *
     * Creates a new task with the specified title, project, and milestone.
     * Validates that both project and milestone exist in the remote platform.
     * After successful creation, generates a local JSON file that can be edited
     * and updated using the 'update' command.
     *
     * Required: 'title', 'project', and 'milestone' parameters
     *
     * Default values:
     * - Status: pending
     * - Priority: medium
     * - Open: true
     *
     * Usage:
     *   _cloudia/tasks/insert?title=My Task&project=my-project&milestone=MILESTONE_KEYID
     */
    public function METHOD_insert(): bool
    {
        //region VALIDATE required parameters
        $title = $this->formParams['title'] ?? null;
        $project = $this->formParams['project'] ?? null;
        $milestone = $this->formParams['milestone'] ?? null;

        if (!$title) {
            return $this->addError("Missing required parameter: title. Usage: _cloudia/tasks/insert?title=TITLE&project=PROJECT_ID&milestone=MILESTONE_ID");
        }

        if (!$project) {
            return $this->addError("Missing required parameter: project. Usage: _cloudia/tasks/insert?title=TITLE&project=PROJECT_ID&milestone=MILESTONE_ID");
        }

        if (!$milestone) {
            return $this->addError("Missing required parameter: milestone. Usage: _cloudia/tasks/insert?title=TITLE&project=PROJECT_ID&milestone=MILESTONE_ID");
        }
        //endregion

        //region VERIFY project exists
        $this->sendTerminal("");
        $this->sendTerminal("Validating parameters...");
        $this->sendTerminal(str_repeat('-', 100));
        $this->sendTerminal(" - Checking project [{$project}]...");

        $project_response = $this->core->request->get_json_decode(
            "{$this->api_base_url}/core/cfo/cfi/CloudFrameWorkProjectsEntries/display/" . urlencode($project) . "?_raw&_timezone=UTC",
            ['_raw' => 1, '_timezone' => 'UTC'],
            $this->headers
        );

        if ($this->core->request->error || !($project_response['data'] ?? null)) {
            return $this->addError("Project [{$project}] not found in remote platform");
        }

        $project_data = $project_response['data'];
        $this->sendTerminal("   + Found: {$project_data['Title']}");
        //endregion

        //region VERIFY milestone exists
        $this->sendTerminal(" - Checking milestone [{$milestone}]...");

        $milestone_response = $this->core->request->get_json_decode(
            "{$this->api_base_url}/core/cfo/cfi/CloudFrameWorkProjectsMilestones/display/{$milestone}?_raw&_timezone=UTC",
            ['_raw' => 1, '_timezone' => 'UTC'],
            $this->headers
        );

        if ($this->core->request->error || !($milestone_response['data'] ?? null)) {
            return $this->addError("Milestone [{$milestone}] not found in remote platform");
        }

        $milestone_data = $milestone_response['data'];
        $this->sendTerminal("   + Found: {$milestone_data['Title']}");
        //endregion

        //region BUILD task data with defaults
        $task_data = [
            'Title' => $title,
            'ProjectId' => $project,
            'MilestoneId' => $milestone,
            'Status' => 'pending',
            'Priority' => 'medium',
            'Open' => true,
            'PlayerId' => $this->user_email,
            'PlayerIdSource' => $this->user_email,
            'DateInitTask' => date('Y-m-d')
        ];
        //endregion

        //region SHOW task data being created
        $this->sendTerminal("");
        $this->sendTerminal("Creating new task...");
        $this->sendTerminal(str_repeat('-', 100));
        $this->sendTerminal(" - Title: {$task_data['Title']}");
        $this->sendTerminal(" - Project: {$project_data['Title']} ({$project})");
        $this->sendTerminal(" - Milestone: {$milestone_data['Title']} ({$milestone})");
        $this->sendTerminal(" - Status: {$task_data['Status']}");
        $this->sendTerminal(" - Priority: {$task_data['Priority']}");
        $this->sendTerminal(" - Assigned: {$task_data['PlayerId']}");
        $this->sendTerminal(" - Source: {$task_data['PlayerIdSource']}");
        $this->sendTerminal(" - Init Date: {$task_data['DateInitTask']}");
        //endregion

        //region INSERT task via API
        $this->sendTerminal("");
        $this->sendTerminal(" - Sending to remote platform...");

        $response = $this->core->request->post_json_decode(
            "{$this->api_base_url}/core/cfo/cfi/CloudFrameWorkProjectsTasks?_raw&_timezone=UTC",
            $task_data,
            $this->headers
        );

        if ($this->core->request->error) {
            return $this->addError("API Error: " . json_encode($this->core->request->errorMsg));
        }

        if (!($response['success'] ?? false)) {
            $errorMsg = $response['errorMsg'] ?? $response['error'] ?? 'Unknown error';
            if (is_array($errorMsg)) {
                $errorMsg = implode(', ', $errorMsg);
            }
            return $this->addError("Insert failed: {$errorMsg}");
        }

        $created_task = $response['data'] ?? null;
        if (!$created_task || !($created_task['KeyId'] ?? null)) {
            return $this->addError("Task created but no KeyId returned");
        }

        $task_id = $created_task['KeyId'];
        $this->sendTerminal(" + Task created with KeyId: {$task_id}");
        //endregion

        //region CREATE output directory if not exists
        $output_dir = $this->core->system->root_path . '/local_data/_cloudia/tasks';

        if (!is_dir($output_dir)) {
            if (!mkdir($output_dir, 0755, true)) {
                return $this->addError("Failed to create directory: {$output_dir}");
            }
            $this->sendTerminal(" - Created directory: ./local_data/_cloudia/tasks");
        }
        //endregion

        //region SAVE task to local file
        $this->sendTerminal("");
        $this->sendTerminal(" - Saving to local file...");

        // Sort task keys
        ksort($created_task);

        $data = [
            'CloudFrameWorkProjectsTasks' => $created_task,
            'CloudFrameWorkDevDocumentationForProcessTests' => []
        ];

        $filepath = "{$output_dir}/{$task_id}.json";
        $json_content = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if (file_put_contents($filepath, $json_content) === false) {
            return $this->addError("Failed to write file: {$filepath}");
        }

        $this->sendTerminal(" + Saved: ./local_data/_cloudia/tasks/{$task_id}.json");
        //endregion

        //region SHOW summary
        $this->sendTerminal("");
        $this->sendTerminal(str_repeat('-', 100));
        $this->sendTerminal("Task created successfully!");
        $this->sendTerminal(" - KeyId: {$created_task['KeyId']}");
        $this->sendTerminal(" - Title: {$created_task['Title']}");
        $this->sendTerminal(" - Project: {$created_task['ProjectId']}");
        if ($milestone) {
            $this->sendTerminal(" - Milestone: {$milestone}");
        }
        $this->sendTerminal(" - Status: {$created_task['Status']}");
        $this->sendTerminal(" - Created: {$created_task['DateInserting']}");
        $this->sendTerminal("");
        $this->sendTerminal("Next steps:");
        $this->sendTerminal("  1. Edit the file: ./local_data/_cloudia/tasks/{$task_id}.json");
        $this->sendTerminal("  2. Update task:   composer script -- \"_cloudia/tasks/update?id={$task_id}\"");
        $this->sendTerminal(str_repeat('=', 100));
        //endregion

        return true;
    }

    /**
     * Delete a task from the remote platform
     *
     * Deletes the task and optionally removes the local JSON file if it exists.
     * Requires confirmation via the 'confirm' parameter.
     *
     * Required: 'id' parameter with the task KeyId
     * Required: 'confirm=yes' to confirm deletion
     *
     * Usage:
     *   _cloudia/tasks/delete?id=TASK_KEYID              - Shows task info and requires confirmation
     *   _cloudia/tasks/delete?id=TASK_KEYID&confirm=yes  - Deletes the task
     */
    public function METHOD_delete(): bool
    {
        //region VALIDATE task ID
        $task_id = $this->formParams['id'] ?? null;
        if (!$task_id) {
            return $this->addError("Missing required parameter: id. Usage: _cloudia/tasks/delete?id=TASK_KEYID");
        }
        //endregion

        //region FETCH task details to confirm it exists
        $this->sendTerminal("");
        $this->sendTerminal("Fetching task [{$task_id}]...");

        $response = $this->core->request->get_json_decode(
            "{$this->api_base_url}/core/cfo/cfi/CloudFrameWorkProjectsTasks/display/{$task_id}?_raw&_timezone=UTC",
            ['_raw' => 1, '_timezone' => 'UTC'],
            $this->headers
        );

        if ($this->core->request->error) {
            return $this->addError("API Error: " . json_encode($this->core->request->errorMsg));
        }

        $task = $response['data'] ?? null;
        if (!$task) {
            return $this->addError("Task [{$task_id}] not found in remote platform");
        }
        //endregion

        //region FETCH associated checks count
        $checks_response = $this->core->request->get_json_decode(
            "{$this->api_base_url}/core/cfo/cfi/CloudFrameWorkDevDocumentationForProcessTests?_raw&_timezone=UTC",
            [
                'filter_CFOEntity' => 'CloudFrameWorkProjectsTasks',
                'filter_CFOId' => $task_id,
                'cfo_limit' => 200,
                '_raw' => 1,
                '_timezone' => 'UTC'
            ],
            $this->headers
        );

        $checks = $checks_response['data'] ?? [];
        $checks_count = count($checks);
        //endregion

        //region SHOW task info
        $this->sendTerminal("");
        $this->sendTerminal("Task to delete:");
        $this->sendTerminal(str_repeat('-', 100));
        $this->sendTerminal(" - KeyId: {$task['KeyId']}");
        $this->sendTerminal(" - Title: {$task['Title']}");
        $this->sendTerminal(" - Project: {$task['ProjectId']}");
        if ($milestone = $task['MilestoneId'] ?? null) {
            $this->sendTerminal(" - Milestone: {$milestone}");
        }
        $this->sendTerminal(" - Status: {$task['Status']}");
        $this->sendTerminal(" - Checks: {$checks_count}");
        $this->sendTerminal(str_repeat('-', 100));
        //endregion

        //region CHECK delete_checks parameter if task has checks
        if ($checks_count > 0) {
            $delete_checks = $this->formParams['delete_checks'] ?? null;

            if ($delete_checks !== 'yes') {
                $this->sendTerminal("");
                $this->sendTerminal(" !! WARNING: This task has {$checks_count} associated check(s) that will also be deleted.");
                $this->sendTerminal("");
                $this->sendTerminal(" To confirm deletion of checks, re-run the command with: delete_checks=yes");
                $this->sendTerminal("");
                $this->sendTerminal(" Example:");
                $this->sendTerminal("   composer script -- \"_cloudia/tasks/delete?id={$task_id}&delete_checks=yes&confirm=yes\"");
                $this->sendTerminal("");
                return $this->addError("Delete checks confirmation required. Use delete_checks=yes parameter.");
            }
        }
        //endregion

        //region CHECK confirmation parameter
        $confirm = $this->formParams['confirm'] ?? null;

        if ($confirm !== 'yes') {
            $this->sendTerminal("");
            $this->sendTerminal(" !! WARNING: This action will permanently delete the task" . ($checks_count > 0 ? " and {$checks_count} associated check(s)" : "") . ".");
            $this->sendTerminal("");
            $this->sendTerminal(" To confirm deletion, re-run the command with: confirm=yes");
            $this->sendTerminal("");
            $this->sendTerminal(" Example:");
            if ($checks_count > 0) {
                $this->sendTerminal("   composer script -- \"_cloudia/tasks/delete?id={$task_id}&delete_checks=yes&confirm=yes\"");
            } else {
                $this->sendTerminal("   composer script -- \"_cloudia/tasks/delete?id={$task_id}&confirm=yes\"");
            }
            $this->sendTerminal("");
            return $this->addError("Delete confirmation required. Use confirm=yes parameter.");
        }
        //endregion

        //region DELETE associated checks first
        if ($checks_count > 0) {
            $this->sendTerminal("");
            $this->sendTerminal("Deleting {$checks_count} associated checks...");

            foreach ($checks as $check) {
                $check_id = $check['KeyId'] ?? null;
                if ($check_id) {
                    $this->sendTerminal(" - Deleting check: {$check['Title']}");
                    $this->core->request->delete(
                        "{$this->api_base_url}/core/cfo/cfi/CloudFrameWorkDevDocumentationForProcessTests/{$check_id}?_raw",
                        $this->headers
                    );
                }
            }
            $this->sendTerminal(" + {$checks_count} checks deleted");
        }
        //endregion

        //region DELETE task from remote
        $this->sendTerminal("");
        $this->sendTerminal("Deleting task...");

        $this->core->request->delete(
            "{$this->api_base_url}/core/cfo/cfi/CloudFrameWorkProjectsTasks/{$task_id}?_raw",
            $this->headers
        );

        if ($this->core->request->error) {
            return $this->addError("API Error deleting task: " . json_encode($this->core->request->errorMsg));
        }

        $this->sendTerminal(" + Task deleted from remote platform");
        //endregion

        //region DELETE local file if exists
        $filepath = $this->core->system->root_path . "/local_data/_cloudia/tasks/{$task_id}.json";

        if (is_file($filepath)) {
            if (unlink($filepath)) {
                $this->sendTerminal(" + Local file deleted: ./local_data/_cloudia/tasks/{$task_id}.json");
            } else {
                $this->sendTerminal(" # Warning: Failed to delete local file");
            }
        }
        //endregion

        //region SHOW summary
        $this->sendTerminal("");
        $this->sendTerminal(str_repeat('-', 100));
        $this->sendTerminal("Task deleted successfully!");
        $this->sendTerminal(" - KeyId: {$task_id}");
        $this->sendTerminal(" - Title: {$task['Title']}");
        $this->sendTerminal(" - Checks deleted: {$checks_count}");
        $this->sendTerminal(str_repeat('=', 100));
        //endregion

        return true;
    }

    /**
     * Search tasks by filters
     */
    public function METHOD_search()
    {
        //region BUILD filter parameters
        $params = [
            '_order' => '-Priority,Status,DateDeadLine',
            'cfo_limit' => 200,
            '_raw' => 1,
            '_timezone' => 'UTC'
        ];

        // Add filters from form params
        if ($status = $this->formParams['status'] ?? null) {
            $params['filter_Status'] = $status;
        }
        if ($priority = $this->formParams['priority'] ?? null) {
            $params['filter_Priority'] = $priority;
        }
        if ($project = $this->formParams['project'] ?? null) {
            $params['filter_ProjectId'] = $project;
        }
        if ($assigned = $this->formParams['assigned'] ?? null) {
            $params['filter_PlayerId'] = $assigned;
        }
        if ($open = $this->formParams['open'] ?? null) {
            $params['filter_Open'] = $open;
        }

        $filterDesc = [];
        if ($status) $filterDesc[] = "status={$status}";
        if ($priority) $filterDesc[] = "priority={$priority}";
        if ($project) $filterDesc[] = "project={$project}";
        if ($assigned) $filterDesc[] = "assigned={$assigned}";
        if ($open) $filterDesc[] = "open={$open}";
        //endregion

        //region FETCH tasks with filters
        $this->sendTerminal("");
        $this->sendTerminal("Search tasks" . ($filterDesc ? " [" . implode(', ', $filterDesc) . "]" : "") . ":");
        $this->sendTerminal(str_repeat('-', 100));

        $response = $this->core->request->get_json_decode(
            "{$this->api_base_url}/core/cfo/cfi/CloudFrameWorkProjectsTasks?_raw&_timezone=UTC",
            $params,
            $this->headers
        );

        if ($this->core->request->error) {
            return $this->addError($this->core->request->errorMsg);
        }

        $tasks = $response['data'] ?? [];
        $this->displayTaskList($tasks);
        //endregion

        return true;
    }

    /**
     * Display tasks report separated by open/closed status with hours summary
     *
     * Shows a detailed report including:
     * - Tasks separated by OPEN/CLOSED status
     * - Hours spent vs estimated per task
     * - Subtotals by section
     * - Summary by assignee (PlayerId)
     *
     * @param array $tasks Array of task data
     * @param string|null $project_id Optional project ID for context
     */
    private function displayTasksReport(array $tasks, ?string $project_id = null): void
    {
        if (!$tasks) {
            $this->sendTerminal("");
            $this->sendTerminal("   No tasks found" . ($project_id ? " for project [{$project_id}]" : ""));
            $this->sendTerminal("");
            return;
        }

        // Build report items with open/closed classification
        $report_open = [];
        $report_closed = [];

        // Totals for hours (open vs closed)
        $total_spent_open = 0;
        $total_estimated_open = 0;
        $total_spent_closed = 0;
        $total_estimated_closed = 0;

        // Stats by PlayerId
        $stats_by_player = [];

        foreach ($tasks as $t) {
            // Handle PlayerId as array or string
            $players = $t['PlayerId'] ?? [];
            $player = is_array($players) ? implode(',', $players) : $players;
            $player = $player ?: '-';

            // Get hours values
            $time_spent = floatval($t['TimeSpent'] ?? 0);
            $time_estimated = floatval($t['TimeEstimated'] ?? 0);

            $item = [
                'id' => $t['KeyId'] ?? 'N/A',
                'title' => $t['Title'] ?? 'Untitled',
                'player' => $player,
                'status' => $t['Status'] ?? '-',
                'deadline' => $t['DateDeadLine'] ?? '-',
                'hours' => sprintf("%.1f/%.1f", $time_spent, $time_estimated),
            ];

            // Determine if closed
            $status = $t['Status'] ?? '';
            $open = $t['Open'] ?? true;
            $is_closed = in_array($status, ['closed', 'canceled']) || $open === false;

            // Initialize player stats if not exists
            if (!isset($stats_by_player[$player])) {
                $stats_by_player[$player] = [
                    'tasks_open' => 0,
                    'tasks_closed' => 0,
                    'spent_open' => 0,
                    'estimated_open' => 0,
                    'spent_closed' => 0,
                    'estimated_closed' => 0
                ];
            }

            if ($is_closed) {
                $report_closed[] = $item;
                $total_spent_closed += $time_spent;
                $total_estimated_closed += $time_estimated;
                $stats_by_player[$player]['tasks_closed']++;
                $stats_by_player[$player]['spent_closed'] += $time_spent;
                $stats_by_player[$player]['estimated_closed'] += $time_estimated;
            } else {
                $report_open[] = $item;
                $total_spent_open += $time_spent;
                $total_estimated_open += $time_estimated;
                $stats_by_player[$player]['tasks_open']++;
                $stats_by_player[$player]['spent_open'] += $time_spent;
                $stats_by_player[$player]['estimated_open'] += $time_estimated;
            }
        }

        // Display OPEN tasks
        $this->sendTerminal("");
        $this->sendTerminal("   Tasks OPEN (" . count($report_open) . "):");
        $this->sendTerminal("   " . str_repeat('-', 130));
        $this->sendTerminal(sprintf("   %-12s %-25s %-22s %-12s %-12s %-10s", "KeyId", "Title", "Assignee", "Status", "Deadline", "Hours"));
        $this->sendTerminal("   " . str_repeat('-', 130));
        foreach ($report_open as $t) {
            $title_display = strlen($t['title']) > 22 ? substr($t['title'], 0, 19) . '...' : $t['title'];
            $player_display = strlen($t['player']) > 19 ? substr($t['player'], 0, 16) . '...' : $t['player'];
            $deadline_display = $t['deadline'] !== '-' ? substr($t['deadline'], 0, 10) : '-';
            $this->sendTerminal(sprintf("   %-12s %-25s %-22s %-12s %-12s %-10s", $t['id'], $title_display, $player_display, $t['status'], $deadline_display, $t['hours']));
        }
        $this->sendTerminal(sprintf("   %90s %-10s", "Subtotal:", sprintf("%.1f/%.1f", $total_spent_open, $total_estimated_open)));

        // Display CLOSED tasks
        $this->sendTerminal("");
        $this->sendTerminal("   Tasks CLOSED (" . count($report_closed) . "):");
        $this->sendTerminal("   " . str_repeat('-', 130));
        foreach ($report_closed as $t) {
            $title_display = strlen($t['title']) > 22 ? substr($t['title'], 0, 19) . '...' : $t['title'];
            $player_display = strlen($t['player']) > 19 ? substr($t['player'], 0, 16) . '...' : $t['player'];
            $deadline_display = $t['deadline'] !== '-' ? substr($t['deadline'], 0, 10) : '-';
            $this->sendTerminal(sprintf("   %-12s %-25s %-22s %-12s %-12s %-10s", $t['id'], $title_display, $player_display, $t['status'], $deadline_display, $t['hours']));
        }
        $this->sendTerminal(sprintf("   %90s %-10s", "Subtotal:", sprintf("%.1f/%.1f", $total_spent_closed, $total_estimated_closed)));

        // Display totals
        $total_spent = $total_spent_open + $total_spent_closed;
        $total_estimated = $total_estimated_open + $total_estimated_closed;
        $this->sendTerminal("   " . str_repeat('-', 130));
        $this->sendTerminal(sprintf("   %90s %-10s", "TOTAL Hours (Spent/Estimated):", sprintf("%.1f/%.1f", $total_spent, $total_estimated)));

        // Display summary by PlayerId
        $this->sendTerminal("");
        $this->sendTerminal("   Summary by Assignee:");
        $this->sendTerminal("   " . str_repeat('-', 100));
        $this->sendTerminal(sprintf("   %-30s %12s %12s %15s %15s", "Assignee", "Tasks Open", "Tasks Closed", "Hours Open", "Hours Closed"));
        $this->sendTerminal("   " . str_repeat('-', 100));
        ksort($stats_by_player);
        foreach ($stats_by_player as $player_id => $stats) {
            $player_display = strlen($player_id) > 27 ? substr($player_id, 0, 24) . '...' : $player_id;
            $hours_open = sprintf("%.1f/%.1f", $stats['spent_open'], $stats['estimated_open']);
            $hours_closed = sprintf("%.1f/%.1f", $stats['spent_closed'], $stats['estimated_closed']);
            $this->sendTerminal(sprintf("   %-30s %12d %12d %15s %15s", $player_display, $stats['tasks_open'], $stats['tasks_closed'], $hours_open, $hours_closed));
        }
        $this->sendTerminal("   " . str_repeat('-', 100));
        $this->sendTerminal(sprintf("   %-30s %12d %12d %15s %15s", "TOTAL", count($report_open), count($report_closed), sprintf("%.1f/%.1f", $total_spent_open, $total_estimated_open), sprintf("%.1f/%.1f", $total_spent_closed, $total_estimated_closed)));
        $this->sendTerminal("");
    }

    /**
     * Display a list of tasks in table format
     *
     * @param array $tasks Array of task records
     * @param string|null $person_email Optional email to display instead of current user
     */
    private function displayTaskList(array $tasks, ?string $person_email = null)
    {
        if (!$tasks) {
            $this->sendTerminal("No tasks found");
            $this->sendTerminal(str_repeat('-', 100));
            $this->sendTerminal("Total: 0 tasks");
            return;
        }

        // Group tasks by status for summary
        $byStatus = [];
        foreach ($tasks as $task) {
            $status = $task['Status'] ?? 'unknown';
            $byStatus[$status] = ($byStatus[$status] ?? 0) + 1;
        }

        // Display tasks
        foreach ($tasks as $task) {
            $keyId = $task['KeyId'] ?? 'N/A';
            $title = $task['Title'] ?? 'Untitled';
            $project = $task['ProjectId'] ?? '';
            $status = $task['Status'] ?? 'N/A';
            $priority = $task['Priority'] ?? 'medium';
            $deadline = $task['DateDeadLine'] ?? '';
            $dueDate = $task['DateDueDate'] ?? '';
            $estimated = $task['TimeEstimated'] ?? 0;
            $spent = $task['TimeSpent'] ?? 0;
            $open = ($task['Open'] ?? false) ? 'Open' : 'Closed';

            // Priority indicator
            $priorityIcon = match($priority) {
                'very_high' => '!!!',
                'high' => '!! ',
                'medium' => '!  ',
                'low' => '.  ',
                'very_low' => '   ',
                default => '   '
            };

            // Truncate title if too long
            $maxTitleLen = 50;
            if (strlen($title) > $maxTitleLen) {
                $title = substr($title, 0, $maxTitleLen - 3) . '...';
            }

            // Format time
            $timeInfo = "{$spent}h/{$estimated}h";

            // Milestone info
            $milestone = $task['MilestoneId'] ?? '';
            $milestoneInfo = $milestone ? " | Milestone: {$milestone}" : "";

            // Format dates info - always show both fields
            $deadlineDisplay = $deadline ?: '-';
            $dueDateDisplay = $dueDate ?: '-';
            $datesInfo = " | Deadline: {$deadlineDisplay} | DueDate: {$dueDateDisplay}";

            // Format output line
            $statusPad = str_pad($status, 12);
            $this->sendTerminal(" {$priorityIcon} [{$keyId}] [{$statusPad}] {$title}");
            $this->sendTerminal("     Project: {$project}{$milestoneInfo}{$datesInfo} | Time: {$timeInfo}");
        }

        // Summary
        $this->sendTerminal(str_repeat('-', 100));
        $this->sendTerminal("Total: " . count($tasks) . " tasks");

        // Status breakdown
        if ($byStatus) {
            $statusSummary = [];
            foreach ($byStatus as $status => $count) {
                $statusSummary[] = "{$status}: {$count}";
            }
            $this->sendTerminal("By status: " . implode(' | ', $statusSummary));
        }

        // User info
        $display_email = $person_email ?? $this->user_email;
        $this->sendTerminal("User: {$display_email}");
    }

    /**
     * Validate task required fields
     *
     * Ensures all mandatory fields are present and correctly defined.
     *
     * Required fields:
     * - KeyId: Must be present and match expected value
     * - Title: Non-empty string
     * - ProjectId: Non-empty string
     * - Status: Non-empty string
     *
     * Recommended fields (warnings if missing):
     * - MilestoneId: Should be present for proper organization
     * - PlayerId: Should be assigned
     *
     * @param array $task The task data
     * @param string $expectedKeyId The expected KeyId value
     * @return array ['valid' => bool, 'errors' => array, 'warnings' => array]
     */
    private function validateTaskFields(array $task, string $expectedKeyId): array
    {
        $result = [
            'valid' => true,
            'errors' => [],
            'warnings' => []
        ];

        // Required fields
        $requiredFields = [
            'KeyId' => 'Task KeyId',
            'Title' => 'Task Title',
            'ProjectId' => 'Project ID',
            'Status' => 'Task Status'
        ];

        foreach ($requiredFields as $field => $label) {
            if (!isset($task[$field]) || (is_string($task[$field]) && trim($task[$field]) === '')) {
                $result['errors'][] = "Missing required field: {$label} ({$field})";
                $result['valid'] = false;
            }
        }

        // Validate KeyId matches expected value
        if (isset($task['KeyId']) && $task['KeyId'] !== $expectedKeyId) {
            $result['errors'][] = "KeyId mismatch: task has '{$task['KeyId']}' but expected '{$expectedKeyId}'";
            $result['valid'] = false;
        }

        // Valid Status values
        $validStatuses = ['pending', 'in-progress', 'in-qa', 'closed', 'blocked', 'canceled', 'on-hold'];
        if (isset($task['Status']) && !in_array($task['Status'], $validStatuses)) {
            $result['warnings'][] = "Status '{$task['Status']}' is not a standard value. Valid: " . implode(', ', $validStatuses);
        }

        // Valid Priority values
        $validPriorities = ['very_high', 'high', 'medium', 'low', 'very_low'];
        if (isset($task['Priority']) && !in_array($task['Priority'], $validPriorities)) {
            $result['warnings'][] = "Priority '{$task['Priority']}' is not a standard value. Valid: " . implode(', ', $validPriorities);
        }

        // Recommended fields (warnings only)
        if (!isset($task['MilestoneId']) || trim($task['MilestoneId']) === '') {
            $result['warnings'][] = "MilestoneId is empty - task should be linked to a milestone";
        }

        if (!isset($task['PlayerId']) || (is_array($task['PlayerId']) && empty($task['PlayerId'])) || (is_string($task['PlayerId']) && trim($task['PlayerId']) === '')) {
            $result['warnings'][] = "PlayerId is empty - task should be assigned to someone";
        }

        return $result;
    }

    /**
     * Validate check required fields
     *
     * Ensures all mandatory fields are present and correctly defined for a check.
     *
     * Required fields:
     * - CFOEntity: Must be 'CloudFrameWorkProjectsTasks'
     * - CFOId: Must match the task KeyId
     * - Route: Non-empty string
     * - Title: Non-empty string
     * - Status: Non-empty string
     *
     * Recommended fields:
     * - CFOField: Should be 'JSON' for proper linking
     *
     * @param array $check The check data
     * @param string $taskKeyId The parent task KeyId
     * @param int $index The check index in the array (for error messages)
     * @return array ['valid' => bool, 'errors' => array, 'warnings' => array]
     */
    private function validateCheckFields(array $check, string $taskKeyId, int $index): array
    {
        $result = [
            'valid' => true,
            'errors' => [],
            'warnings' => []
        ];

        $checkLabel = "Check #" . ($index + 1) . (isset($check['Title']) ? " ({$check['Title']})" : "");

        // Required fields
        $requiredFields = [
            'Title' => 'Check Title',
            'Status' => 'Check Status',
            'Route' => 'Check Route'
        ];

        foreach ($requiredFields as $field => $label) {
            if (!isset($check[$field]) || (is_string($check[$field]) && trim($check[$field]) === '')) {
                $result['errors'][] = "{$checkLabel}: Missing required field '{$field}'";
                $result['valid'] = false;
            }
        }

        // CFOEntity validation - ALWAYS required for task checks
        if (isset($check['KeyId'])) {
            // Existing check - must have correct CFOEntity
            if (!isset($check['CFOEntity']) || $check['CFOEntity'] !== 'CloudFrameWorkProjectsTasks') {
                $result['errors'][] = "{$checkLabel}: CFOEntity must be 'CloudFrameWorkProjectsTasks', found: '" . ($check['CFOEntity'] ?? 'missing') . "'";
                $result['valid'] = false;
            }

            // CFOId must match task KeyId
            if (!isset($check['CFOId']) || $check['CFOId'] !== $taskKeyId) {
                $result['errors'][] = "{$checkLabel}: CFOId must be '{$taskKeyId}', found: '" . ($check['CFOId'] ?? 'missing') . "'";
                $result['valid'] = false;
            }

            // CFOField MUST be 'JSON' for task checks
            if (!isset($check['CFOField']) || $check['CFOField'] !== 'JSON') {
                $result['errors'][] = "{$checkLabel}: CFOField must be 'JSON', found: '" . ($check['CFOField'] ?? 'missing') . "'";
                $result['valid'] = false;
            }
        } else {
            // New check (no KeyId) - validate CFOEntity and CFOField if present
            if (isset($check['CFOEntity']) && $check['CFOEntity'] !== 'CloudFrameWorkProjectsTasks') {
                $result['errors'][] = "{$checkLabel}: CFOEntity must be 'CloudFrameWorkProjectsTasks', found: '{$check['CFOEntity']}'";
                $result['valid'] = false;
            }
            if (isset($check['CFOField']) && $check['CFOField'] !== 'JSON') {
                $result['errors'][] = "{$checkLabel}: CFOField must be 'JSON', found: '{$check['CFOField']}'";
                $result['valid'] = false;
            }
        }

        // Valid Status values for checks (complete list)
        $validStatuses = ['backlog', 'recurrent', 'new', 'pending', 'in-progress', 'in-qa', 'closing', 'closed', 'canceled', 'blocked'];
        if (isset($check['Status']) && !in_array($check['Status'], $validStatuses)) {
            $result['errors'][] = "{$checkLabel}: Status '{$check['Status']}' is not valid. Allowed: " . implode(', ', $validStatuses);
            $result['valid'] = false;
        }

        // Objetivo field validation (PLANNING phase - should be present for all checks)
        if (!isset($check['Objetivo']) || (is_string($check['Objetivo']) && trim(strip_tags($check['Objetivo'])) === '')) {
            $result['warnings'][] = "{$checkLabel}: 'Objetivo' field is empty - should define what needs to be achieved (planning phase)";
        }

        // Resultado field validation (EXECUTION phase)
        // Planning statuses: backlog, recurrent, new, pending - Resultado is optional
        // Execution statuses: in-progress, in-qa, closing, closed, canceled, blocked - Resultado is REQUIRED
        $planningStatuses = ['backlog', 'recurrent', 'new', 'pending'];
        if (isset($check['Status']) && !in_array($check['Status'], $planningStatuses)) {
            // Status is NOT a planning status, so Resultado is REQUIRED
            if (!isset($check['Resultado']) || (is_string($check['Resultado']) && trim(strip_tags($check['Resultado'])) === '')) {
                $result['errors'][] = "{$checkLabel}: 'Resultado' field is REQUIRED when status is '{$check['Status']}' (execution phase)";
                $result['valid'] = false;
            }
        }

        return $result;
    }

    /**
     * Validate all checks in an array
     *
     * @param array $checks Array of check records
     * @param string $taskKeyId The parent task KeyId
     * @return array ['valid' => bool, 'errors' => array, 'warnings' => array]
     */
    private function validateAllChecks(array $checks, string $taskKeyId): array
    {
        $result = [
            'valid' => true,
            'errors' => [],
            'warnings' => []
        ];

        foreach ($checks as $index => $check) {
            $checkValidation = $this->validateCheckFields($check, $taskKeyId, $index);

            $result['errors'] = array_merge($result['errors'], $checkValidation['errors']);
            $result['warnings'] = array_merge($result['warnings'], $checkValidation['warnings']);

            if (!$checkValidation['valid']) {
                $result['valid'] = false;
            }
        }

        return $result;
    }

    /**
     * Extract all route values from a JSON structure
     *
     * Recursively traverses the JSON structure and extracts the "route" values
     * from leaf nodes. A leaf node is an object that contains a "route" key.
     *
     * Expected structure:
     * {
     *     "Category": {
     *         "Check Title": {"route": "/check-route"}
     *     }
     * }
     *
     * @param mixed $json The JSON data (array or object)
     * @param array &$routes Array to collect found routes
     * @return array Array of route strings found
     */
    private function extractRoutesFromJSON($json, array &$routes = []): array
    {
        if (!is_array($json)) {
            return $routes;
        }

        // Check if this node has a "route" key (leaf node)
        if (isset($json['route']) && is_string($json['route'])) {
            $routes[] = $json['route'];
            return $routes;
        }

        // Recursively process child nodes
        foreach ($json as $key => $value) {
            if (is_array($value)) {
                $this->extractRoutesFromJSON($value, $routes);
            }
        }

        return $routes;
    }

    /**
     * Validate that JSON routes match CHECK routes
     *
     * Verifies that:
     * 1. All routes in JSON field exist in the checks (warning if not)
     * 2. All routes in checks exist in JSON field (warning if not)
     *
     * @param array $jsonField The JSON field from the task
     * @param array $checks The checks array
     * @param string $taskId The task KeyId for logging
     * @return array ['valid' => bool, 'errors' => array, 'warnings' => array]
     */
    private function validateJSONRoutesWithChecks(array $jsonField, array $checks, string $taskId): array
    {
        $result = [
            'valid' => true,
            'errors' => [],
            'warnings' => []
        ];

        // Extract routes from JSON
        $jsonRoutes = [];
        $this->extractRoutesFromJSON($jsonField, $jsonRoutes);
        $jsonRoutes = array_unique($jsonRoutes);

        // Extract routes from checks
        $checkRoutes = [];
        foreach ($checks as $check) {
            if (isset($check['Route']) && is_string($check['Route'])) {
                $checkRoutes[] = $check['Route'];
            }
        }
        $checkRoutes = array_unique($checkRoutes);

        // If no JSON routes and no checks, that's valid
        if (empty($jsonRoutes) && empty($checkRoutes)) {
            return $result;
        }

        // Find routes in JSON but not in checks
        $jsonOnlyRoutes = array_diff($jsonRoutes, $checkRoutes);
        if (!empty($jsonOnlyRoutes)) {
            foreach ($jsonOnlyRoutes as $route) {
                $result['warnings'][] = "Route '{$route}' in JSON but no CHECK with this Route";
            }
        }

        // Find routes in checks but not in JSON
        $checkOnlyRoutes = array_diff($checkRoutes, $jsonRoutes);
        if (!empty($checkOnlyRoutes)) {
            foreach ($checkOnlyRoutes as $route) {
                $result['errors'][] = "CHECK with Route '{$route}' has no matching route in JSON field";
            }
            $result['valid'] = false;
        }

        return $result;
    }

    /**
     * Calculate TimeSpent for a task from activity inputs and events
     *
     * TimeSpent is a calculated field that aggregates time from:
     * - CloudFrameWorkProjectsTasksInputs (activity inputs/time entries)
     * - CloudFrameWorkCRMEvents (events with TimeSpent)
     *
     * @param string $taskId The task KeyId
     * @return array ['success' => bool, 'timeSpent' => float, 'inputsCount' => int, 'eventsCount' => int, 'error' => string|null]
     */
    private function calculateTimeSpentForTask(string $taskId): array
    {
        $result = [
            'success' => true,
            'timeSpent' => 0.0,
            'inputsCount' => 0,
            'eventsCount' => 0,
            'inputsHours' => 0.0,
            'eventsHours' => 0.0,
            'error' => null
        ];

        //region FETCH activity inputs for the task (CloudFrameWorkProjectsTasksInputs)
        $inputParams = [
            'filter_TaskId' => $taskId,
            'cfo_limit' => 1000,
            '_raw' => 1,
            '_timezone' => 'UTC'
        ];

        $inputsResponse = $this->core->request->get_json_decode(
            "{$this->api_base_url}/core/cfo/cfi/CloudFrameWorkProjectsTasksInputs",
            $inputParams,
            $this->headers
        );

        if ($this->core->request->error) {
            $result['success'] = false;
            $result['error'] = "Error fetching activity inputs: " . json_encode($this->core->request->errorMsg);
            return $result;
        }

        $inputs = $inputsResponse['data'] ?? [];
        $result['inputsCount'] = count($inputs);

        // Sum TimeSpent from inputs
        foreach ($inputs as $input) {
            $timeSpent = floatval($input['TimeSpent'] ?? 0);
            $result['inputsHours'] += $timeSpent;
        }
        //endregion

        //region FETCH events for the task (CloudFrameWorkCRMEvents)
        $eventParams = [
            'filter_TaskId' => $taskId,
            'cfo_limit' => 1000,
            '_raw' => 1,
            '_timezone' => 'UTC'
        ];

        $eventsResponse = $this->core->request->get_json_decode(
            "{$this->api_base_url}/core/cfo/cfi/CloudFrameWorkCRMEvents",
            $eventParams,
            $this->headers
        );

        if ($this->core->request->error) {
            $result['success'] = false;
            $result['error'] = "Error fetching events: " . json_encode($this->core->request->errorMsg);
            return $result;
        }

        $events = $eventsResponse['data'] ?? [];
        $result['eventsCount'] = count($events);

        // Sum TimeSpent from events
        foreach ($events as $event) {
            $timeSpent = floatval($event['TimeSpent'] ?? 0);
            $result['eventsHours'] += $timeSpent;
        }
        //endregion

        // Total TimeSpent
        $result['timeSpent'] = $result['inputsHours'] + $result['eventsHours'];

        return $result;
    }

    /**
     * Display detailed task information
     *
     * @param array $task Task record
     */
    private function displayTaskDetail(array $task)
    {
        $fields = [
            'KeyId' => 'ID',
            'Title' => 'Title',
            'ProjectId' => 'Project',
            'MilestoneId' => 'Milestone',
            'Status' => 'Status',
            'Priority' => 'Priority',
            'Open' => 'Open',
            'PlayerId' => 'Assigned To',
            'ReporterId' => 'Reporter',
            'DateInit' => 'Start Date',
            'DateDeadLine' => 'Deadline',
            'TimeEstimated' => 'Estimated Hours',
            'TimeSpent' => 'Spent Hours',
            'SprintIds' => 'Sprint IDs',
            'Tags' => 'Tags',
            'DateInserting' => 'Created',
            'DateUpdating' => 'Updated'
        ];

        foreach ($fields as $key => $label) {
            if (isset($task[$key])) {
                $value = $task[$key];
                if (is_bool($value)) {
                    $value = $value ? 'Yes' : 'No';
                } elseif (is_array($value)) {
                    $value = implode(', ', $value);
                }
                $this->sendTerminal(sprintf(" %-18s: %s", $label, $value));
            }
        }

        // Description (may be long)
        if ($description = $task['Description'] ?? null) {
            $this->sendTerminal("");
            $this->sendTerminal(" Description:");
            $this->sendTerminal(str_repeat('-', 50));
            // Strip HTML and limit length
            $cleanDesc = strip_tags($description);
            $cleanDesc = html_entity_decode($cleanDesc);
            $cleanDesc = preg_replace('/\s+/', ' ', $cleanDesc);
            if (strlen($cleanDesc) > 500) {
                $cleanDesc = substr($cleanDesc, 0, 497) . '...';
            }
            $this->sendTerminal(" " . wordwrap($cleanDesc, 90, "\n "));
        }

        $this->sendTerminal(str_repeat('=', 100));
    }
}
