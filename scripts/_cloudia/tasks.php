<?php
/**
 * Tasks CRUD Script
 *
 * This script provides full CRUD functionality for tasks:
 * - List my open tasks
 * - List tasks for today
 * - List tasks in the current sprint
 * - List tasks for a specific person
 * - Get detailed information about a specific task
 * - Create a new task from JSON input
 * - Update a task from JSON input
 * - Filter tasks by project, status, or priority
 *
 * Usage:
 *   _cloudia/tasks/list                               - List my open tasks
 *   _cloudia/tasks/today                              - List tasks for today
 *   _cloudia/tasks/sprint                             - List tasks in current sprint
 *   _cloudia/tasks/project?id=project-keyname         - List tasks for a specific project
 *   _cloudia/tasks/person?email=user@example.com      - List tasks for a specific person
 *   _cloudia/tasks/get?id=TASK_KEYID                  - Get task details (includes raw JSON)
 *   _cloudia/tasks/insert?json={...}                  - Create new task from JSON
 *   _cloudia/tasks/update?id=TASK_KEYID&json={...}       - Update task from JSON
 *   _cloudia/tasks/search?status=in-progress          - Search tasks by filters
 *
 * @author CloudFramework Development Team
 * @version 1.2
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
        $this->sendTerminal("  /project?id=KEY                - List tasks for a specific project");
        $this->sendTerminal("  /person?email=EMAIL            - List tasks for a specific person");
        $this->sendTerminal("  /get?id=TASK_KEYID             - Get detailed task information");
        $this->sendTerminal("  /insert?json={...}             - Create a new task from JSON");
        $this->sendTerminal("  /put?id=TASK_KEYID&json={...}  - Update a task from JSON");
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
        $this->sendTerminal("  composer script -- \"_cloudia/tasks/person?email=user@example.com\"");
        $this->sendTerminal("  composer script -- \"_cloudia/tasks/get?id=5734953457745920\"");
        $this->sendTerminal("  composer script -- \"_cloudia/tasks/insert?json={\\\"ProjectId\\\":\\\"my-project\\\",\\\"Title\\\":\\\"New Task\\\"}\"");
        $this->sendTerminal("  composer script -- \"_cloudia/tasks/update?id=5734953457745920&json={\\\"Status\\\":\\\"closed\\\"}\"");
        $this->sendTerminal("  composer script -- \"_cloudia/tasks/search?status=in-progress&priority=high\"");
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
     * List tasks for a specific project
     */
    public function METHOD_project(): bool
    {
        //region VALIDATE project ID
        $project_id = $this->formParams['id'] ?? null;
        if (!$project_id) {
            return $this->addError("Missing required parameter: id. Usage: _cloudia/tasks/project?id=project-keyname");
        }
        //endregion

        //region FETCH tasks for the project
        $this->sendTerminal("");
        $this->sendTerminal("Tasks for project [{$project_id}]:");
        $this->sendTerminal(str_repeat('-', 100));

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
     * Get detailed task information
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

        //region SHOW raw JSON
        $this->sendTerminal("");
        $this->sendTerminal("Raw JSON:");
        $this->sendTerminal(str_repeat('-', 100));
        $this->sendTerminal(json_encode($task, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $this->sendTerminal(str_repeat('=', 100));
        //endregion

        return true;
    }

    /**
     * Update a task from JSON input
     *
     * Receives task data via:
     * - Form parameter 'json' containing the JSON string
     * - Or reads from stdin if 'json' param not provided
     *
     * Required: 'id' parameter with the task KeyId
     *
     * Usage:
     *   _cloudia/tasks/update?id=TASK_KEYID&json={"Status":"in-progress","Title":"Updated"}
     *   echo '{"Status":"closed"}' | composer script -- "_cloudia/tasks/update?id=TASK_KEYID"
     */
    public function METHOD_update(): bool
    {
        //region VALIDATE task ID
        $task_id = $this->formParams['id'] ?? null;
        if (!$task_id) {
            return $this->addError("Missing required parameter: id. Usage: _cloudia/tasks/update?id=TASK_KEYID&json={...}");
        }
        //endregion

        //region GET JSON data from parameter or stdin
        $json_string = $this->formParams['json'] ?? null;

        if (!$json_string) {
            // Try to read from stdin
            $stdin = file_get_contents('php://stdin');

            if ($stdin && trim($stdin)) {
                $json_string = trim($stdin);
            }
        }


        if (!$json_string) {
            return $this->addError("Missing JSON data. Provide via 'json' parameter or stdin");
        }

        $task_data = json_decode($json_string, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return $this->addError("Invalid JSON: " . json_last_error_msg());
        }

        if (!is_array($task_data) || empty($task_data)) {
            return $this->addError("JSON must be a non-empty object with fields to update");
        }
        //endregion

        //region FETCH current task to verify it exists
        $this->sendTerminal("");
        $this->sendTerminal("Updating task [{$task_id}]...");
        $this->sendTerminal(str_repeat('-', 100));

        $response = $this->core->request->get_json_decode(
            "{$this->api_base_url}/core/cfo/cfi/CloudFrameWorkProjectsTasks/display/{$task_id}",
            ['_raw' => 1, '_timezone' => 'UTC'],
            $this->headers
        );

        if ($this->core->request->error) {
            return $this->addError($this->core->request->errorMsg);
        }

        $current_task = $response['data'] ?? null;
        if (!$current_task) {
            return $this->addError("Task [{$task_id}] not found");
        }

        $this->sendTerminal(" - Current task: {$current_task['Title']}");
        $this->sendTerminal(" - Status: {$current_task['Status']} | Open: " . ($current_task['Open'] ? 'Yes' : 'No'));
        //endregion

        //region SHOW fields being updated
        $this->sendTerminal("");
        $this->sendTerminal(" - Fields to update:");
        foreach ($task_data as $field => $value) {
            $displayValue = is_array($value) ? json_encode($value) : (string)$value;
            if (strlen($displayValue) > 80) {
                $displayValue = substr($displayValue, 0, 77) . '...';
            }
            $this->sendTerminal("   * {$field}: {$displayValue}");
        }
        //endregion

        //region UPDATE task via API
        $this->sendTerminal("");
        $this->sendTerminal(" - Sending update to remote platform...");

        $response = $this->core->request->put_json_decode(
            "{$this->api_base_url}/core/cfo/cfi/CloudFrameWorkProjectsTasks/{$task_id}?_raw&_timezone=UTC",
            $task_data,
            $this->headers,
            true // JSON body flag
        );

        if ($this->core->request->error) {
            return $this->addError("API Error: " . json_encode($this->core->request->errorMsg));
        }

        if (!($response['success'] ?? false)) {
            $errorMsg = $response['errorMsg'] ?? $response['error'] ?? 'Unknown error';
            if (is_array($errorMsg)) {
                $errorMsg = implode(', ', $errorMsg);
            }
            return $this->addError("Update failed: {$errorMsg}");
        }
        //endregion

        //region SHOW updated task
        $updated_task = $response['data'] ?? null;
        if ($updated_task) {
            $this->sendTerminal("");
            $this->sendTerminal("Task updated successfully!");
            $this->sendTerminal(str_repeat('-', 100));
            $this->sendTerminal(" - Title: {$updated_task['Title']}");
            $this->sendTerminal(" - Status: {$updated_task['Status']}");
            $this->sendTerminal(" - Open: " . (($updated_task['Open'] ?? false) ? 'Yes' : 'No'));
            $this->sendTerminal(" - Updated: {$updated_task['DateUpdating']}");

            $this->sendTerminal("");
            $this->sendTerminal("Updated JSON:");
            $this->sendTerminal(str_repeat('-', 100));
            $this->sendTerminal(json_encode($updated_task, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        } else {
            $this->sendTerminal("");
            $this->sendTerminal("Task updated (no data returned)");
        }
        $this->sendTerminal(str_repeat('=', 100));
        //endregion

        return true;
    }

    /**
     * Create a new task from JSON input
     *
     * Receives task data via:
     * - Form parameter 'json' containing the JSON string
     * - Or reads from stdin if 'json' param not provided
     *
     * Required fields: ProjectId, Title
     * KeyId must NOT be included (will be auto-generated)
     *
     * Usage:
     *   _cloudia/tasks/insert?json={"ProjectId":"my-project","Title":"New Task","Status":"pending"}
     *   echo '{"ProjectId":"my-project","Title":"New Task"}' | composer script -- "_cloudia/tasks/insert"
     */
    public function METHOD_insert(): bool
    {
        //region GET JSON data from parameter or stdin
        $json_string = $this->formParams['json'] ?? null;

        if (!$json_string) {
            // Try to read from stdin
            $stdin = file_get_contents('php://stdin');
            if ($stdin && trim($stdin)) {
                $json_string = trim($stdin);
            }
        }

        if (!$json_string) {
            return $this->addError("Missing JSON data. Provide via 'json' parameter or stdin");
        }

        $task_data = json_decode($json_string, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return $this->addError("Invalid JSON: " . json_last_error_msg());
        }

        if (!is_array($task_data) || empty($task_data)) {
            return $this->addError("JSON must be a non-empty object with task fields");
        }
        //endregion

        //region VALIDATE required fields and remove KeyId if present
        if (isset($task_data['KeyId'])) {
            $this->sendTerminal("");
            $this->sendTerminal("Warning: KeyId will be ignored (auto-generated on insert)");
            unset($task_data['KeyId']);
        }

        if (empty($task_data['ProjectId'])) {
            return $this->addError("Missing required field: ProjectId");
        }

        if (empty($task_data['Title'])) {
            return $this->addError("Missing required field: Title");
        }

        // Set defaults if not provided
        if (!isset($task_data['Status'])) {
            $task_data['Status'] = 'pending';
        }
        if (!isset($task_data['Priority'])) {
            $task_data['Priority'] = 'medium';
        }
        if (!isset($task_data['Open'])) {
            $task_data['Open'] = true;
        }
        //endregion

        //region SHOW task data being created
        $this->sendTerminal("");
        $this->sendTerminal("Creating new task...");
        $this->sendTerminal(str_repeat('-', 100));
        $this->sendTerminal(" - Project: {$task_data['ProjectId']}");
        $this->sendTerminal(" - Title: {$task_data['Title']}");
        $this->sendTerminal(" - Status: {$task_data['Status']}");
        $this->sendTerminal(" - Priority: {$task_data['Priority']}");

        if (!empty($task_data['MilestoneId'])) {
            $this->sendTerminal(" - Milestone: {$task_data['MilestoneId']}");
        }
        if (!empty($task_data['PlayerId'])) {
            $assigned = is_array($task_data['PlayerId']) ? implode(', ', $task_data['PlayerId']) : $task_data['PlayerId'];
            $this->sendTerminal(" - Assigned: {$assigned}");
        }

        $this->sendTerminal("");
        $this->sendTerminal(" - All fields:");
        foreach ($task_data as $field => $value) {
            $displayValue = is_array($value) ? json_encode($value) : (string)$value;
            if (strlen($displayValue) > 80) {
                $displayValue = substr($displayValue, 0, 77) . '...';
            }
            $this->sendTerminal("   * {$field}: {$displayValue}");
        }
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
        //endregion

        //region SHOW created task
        $created_task = $response['data'] ?? null;
        if ($created_task) {
            $this->sendTerminal("");
            $this->sendTerminal("Task created successfully!");
            $this->sendTerminal(str_repeat('-', 100));
            $this->sendTerminal(" - KeyId: {$created_task['KeyId']}");
            $this->sendTerminal(" - Title: {$created_task['Title']}");
            $this->sendTerminal(" - Project: {$created_task['ProjectId']}");
            $this->sendTerminal(" - Status: {$created_task['Status']}");
            $this->sendTerminal(" - Created: {$created_task['DateInserting']}");

            $this->sendTerminal("");
            $this->sendTerminal("Created task JSON:");
            $this->sendTerminal(str_repeat('-', 100));
            $this->sendTerminal(json_encode($created_task, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        } else {
            $this->sendTerminal("");
            $this->sendTerminal("Task created (no data returned)");
        }
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
