<?php
/**
 * Project Documentation Backup Script
 *
 * This script provides functionality to manage Project documentation by:
 * - Backing up Projects from the remote platform to local storage
 * - Inserting new Projects from local backup to the remote platform
 * - Updating existing Projects from local backup to the remote platform
 * - Listing Projects in remote and local storage
 *
 * The script operates on Project documentation stored in the `buckets/backups/Projects/{platform}/` directory
 * and synchronizes them with the CloudFramework platform via REST API.
 *
 * Each Project backup file contains:
 * - CloudFrameWorkProjectsEntries: Main Project documentation
 * - CloudFrameWorkProjectsMilestones: Individual Milestone documentation
 * - CloudFrameWorkProjectsTasks: Tasks linked to projects via ProjectId and to milestones via MilestoneId
 *
 * Usage:
 *   _cloudia/projects/backup-from-remote                    - Backup all Projects from remote
 *   _cloudia/projects/backup-from-remote?id=cloud-platform  - Backup specific Project
 *   _cloudia/projects/insert-from-backup?id=cloud-platform  - Insert new Project to remote
 *   _cloudia/projects/update-from-backup?id=cloud-platform  - Update existing Project in remote
 *   _cloudia/projects/list-remote                           - List all Projects in remote
 *   _cloudia/projects/list-local                            - List all Projects in local backup
 *
 * @author CloudFramework Development Team
 * @version 1.0
 */
class Script extends CoreScripts
{
    /** @var string Platform ID for Project operations */
    var $platform_id = '';

    /** @var array HTTP headers for API authentication */
    var $headers = [];

    /** @var string Base API URL for remote platform */
    var $api_base_url = 'https://api.cloudframework.io';

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
        //endregion

        //region VERIFY privileges
        $this->sendTerminal("Executing {$this->params[0]}/{$this->params[1]} from platform [{$this->platform_id}] user [{$this->core->user->id}]");
        if (!$this->core->user->hasAnyPrivilege('development-admin,development-user,projects-admin')) {
            return $this->addError('You do not have permission [development-admin,projects-admin] to execute this script');
        }
        //endregion

        //region SET AUTHENTICATE header for API call
        $this->headers = [
            'X-WEB-KEY' => '/scripts/_cloudia/projects',
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
        $this->sendTerminal("Available commands:");
        $this->sendTerminal("  /backup-from-remote            - Backup all Projects from remote platform");
        $this->sendTerminal("  /backup-from-remote?id=KEY     - Backup specific Project from remote platform");
        $this->sendTerminal("  /insert-from-backup?id=KEY     - Insert new Project in remote platform from local backup");
        $this->sendTerminal("  /update-from-backup?id=KEY     - Update existing Project in remote platform from local backup");
        $this->sendTerminal("  /list-remote                   - List all Projects in remote platform");
        $this->sendTerminal("  /list-local                    - List all Projects in local backup");
        $this->sendTerminal("");
        $this->sendTerminal("Examples:");
        $this->sendTerminal("  composer run-script script \"_cloudia/projects/backup-from-remote?id=cloud-platform\"");
        $this->sendTerminal("  composer run-script script _cloudia/projects/list-remote");
        $this->sendTerminal("");
        $this->sendTerminal("Note: The ?id= parameter is the Project KeyName (e.g., cloud-platform)");
    }

    /**
     * Convert Project KeyName to backup filename
     *
     * @param string $project_id Project KeyName (e.g., cloud-platform)
     * @return string Filename (e.g., cloud-platform.json)
     */
    private function projectIdToFilename($project_id)
    {
        // Replace special characters with underscores for safe filenames
        $name = preg_replace('/[^A-Za-z0-9_-]/', '_', $project_id);
        return $name . '.json';
    }

    /**
     * Convert backup filename to Project KeyName
     *
     * @param string $filename Filename (e.g., cloud-platform.json)
     * @return string Project KeyName (e.g., cloud-platform)
     */
    private function filenameToProjectId($filename)
    {
        return basename($filename, '.json');
    }

    /**
     * Get backup directory path
     *
     * @return string|false Directory path on success, false on error
     */
    private function getBackupDir()
    {
        $backup_dir = $this->core->system->root_path;
        if (($backup_dir .= '/buckets') && !is_dir($backup_dir) && !mkdir($backup_dir)) {
            $this->addError("Backup directory [/buckets] can not be created");
            return false;
        }
        if (($backup_dir .= '/backups') && !is_dir($backup_dir) && !mkdir($backup_dir)) {
            $this->addError("Backup directory [/buckets/backups] can not be created");
            return false;
        }
        if (($backup_dir .= '/Projects') && !is_dir($backup_dir) && !mkdir($backup_dir)) {
            $this->addError("Backup directory [/buckets/backups/Projects] can not be created");
            return false;
        }
        if (($backup_dir .= '/' . $this->platform_id) && !is_dir($backup_dir) && !mkdir($backup_dir)) {
            $this->addError("Backup directory [/buckets/backups/Projects/{$this->platform_id}] can not be created");
            return false;
        }
        return $backup_dir;
    }

    /**
     * List all Projects in remote platform
     */
    public function METHOD_list_remote()
    {
        //region FETCH all Projects from remote platform
        $this->sendTerminal("Listing Projects in remote platform [{$this->platform_id}]:");
        $this->sendTerminal(str_repeat('-', 80));

        $params = ['_fields' => 'KeyName,Title,Type,Status,Open', '_order' => 'KeyName', '_limit' => 1000];
        $response = $this->core->request->get_json_decode(
            "{$this->api_base_url}/core/cfo/cfi/CloudFrameWorkProjectsEntries?_raw=1&_timezone=UTC",
            $params,
            $this->headers
        );

        if ($this->core->request->error) {
            return $this->addError($this->core->request->errorMsg);
        }

        $projects = $response['data'] ?? [];
        if (!$projects) {
            $this->sendTerminal("No Projects found in remote platform");
            return true;
        }

        foreach ($projects as $project) {
            $status = $project['Status'] ?? 'N/A';
            $title = $project['Title'] ?? 'N/A';
            $type = $project['Type'] ?? '';
            $open = ($project['Open'] ?? false) ? 'Open' : 'Closed';
            $typeInfo = $type ? " [{$type}]" : "";
            $this->sendTerminal(" {$project['KeyName']}{$typeInfo} - {$title} [{$status}] ({$open})");
        }
        $this->sendTerminal(str_repeat('-', 80));
        $this->sendTerminal("Total: " . count($projects) . " Projects");
        //endregion

        return true;
    }

    /**
     * List all Projects in local backup
     */
    public function METHOD_list_local()
    {
        //region LIST all Projects in local backup
        $this->sendTerminal("Listing Projects in local backup [{$this->platform_id}]:");
        $this->sendTerminal(str_repeat('-', 80));

        $directory_path = $this->core->system->root_path . '/buckets/backups/Projects/' . $this->platform_id;

        if (!is_dir($directory_path)) {
            $this->sendTerminal("Backup directory not found: /buckets/backups/Projects/{$this->platform_id}");
            return true;
        }

        $files = glob($directory_path . '/*.json');
        if (!$files) {
            $this->sendTerminal("No Project backup files found");
            return true;
        }

        foreach ($files as $file) {
            $data = $this->core->jsonDecode(file_get_contents($file));
            $projectData = $data['CloudFrameWorkProjectsEntries'] ?? [];
            $keyName = $projectData['KeyName'] ?? basename($file, '.json');
            $title = $projectData['Title'] ?? 'N/A';
            $status = $projectData['Status'] ?? 'N/A';
            $type = $projectData['Type'] ?? '';
            $open = ($projectData['Open'] ?? false) ? 'Open' : 'Closed';
            $milestoneCount = count($data['CloudFrameWorkProjectsMilestones'] ?? []);
            $taskCount = count($data['CloudFrameWorkProjectsTasks'] ?? []);
            $typeInfo = $type ? " [{$type}]" : "";
            $this->sendTerminal(" {$keyName}{$typeInfo} - {$title} [{$status}] ({$open}) - {$milestoneCount} milestones, {$taskCount} tasks");
        }
        $this->sendTerminal(str_repeat('-', 80));
        $this->sendTerminal("Total: " . count($files) . " Projects");
        //endregion

        return true;
    }

    /**
     * Backup Projects from remote platform to local storage
     */
    public function METHOD_backup_from_remote()
    {
        //region VERIFY backup directory
        if (!$backup_dir = $this->getBackupDir()) {
            return false;
        }
        $this->sendTerminal(" - Backup directory: /buckets/backups/Projects/{$this->platform_id}");
        //endregion

        //region SET $project_id (specific Project to backup, or null for all)
        $project_id = $this->formParams['id'] ?? null;
        //endregion

        //region READ Projects from remote API
        $projects = [];
        $all_milestones = [];
        $all_tasks = [];

        if ($project_id) {
            //region FETCH single Project by KeyName
            $this->sendTerminal(" - Fetching Project: {$project_id}");
            $response = $this->core->request->get_json_decode(
                "{$this->api_base_url}/core/cfo/cfi/CloudFrameWorkProjectsEntries/display/" . urlencode($project_id) . '?_raw&_timezone=UTC',
                ['_raw' => 1, '_timezone' => 'UTC'],
                $this->headers
            );
            if ($this->core->request->error) {
                return $this->addError($this->core->request->errorMsg);
            }
            if (!($response['data'] ?? null)) {
                return $this->addError("Project [{$project_id}] not found in remote platform");
            }
            $projects = [$response['data']];
            //endregion

            //region READ milestones associated
            $this->sendTerminal(" - Fetching milestones for Project... [max 2000]");
            $all_milestones = $this->core->request->get_json_decode(
                "{$this->api_base_url}/core/cfo/cfi/CloudFrameWorkProjectsMilestones?_raw&_timezone=UTC",
                ['filter_ProjectId' => $project_id, 'cfo_limit' => 2000, '_raw' => 1, '_timezone' => 'UTC'],
                $this->headers
            );
            if ($this->core->request->error) {
                return $this->addError($this->core->request->errorMsg);
            }
            //endregion

            //region READ tasks associated
            $this->sendTerminal(" - Fetching tasks for Project... [max 5000]");
            $all_tasks = $this->core->request->get_json_decode(
                "{$this->api_base_url}/core/cfo/cfi/CloudFrameWorkProjectsTasks?_raw&_timezone=UTC",
                ['filter_ProjectId' => $project_id, 'cfo_limit' => 5000, '_raw' => 1, '_timezone' => 'UTC'],
                $this->headers
            );
            if ($this->core->request->error) {
                return $this->addError($this->core->request->errorMsg);
            }
            //endregion

        } else {
            //region FETCH all Projects
            $this->sendTerminal(" - Fetching all Projects... [max 2000]");
            $response = $this->core->request->get_json_decode(
                "{$this->api_base_url}/core/cfo/cfi/CloudFrameWorkProjectsEntries?_raw&_timezone=UTC",
                ['cfo_limit' => 2000, '_raw' => 1, '_timezone' => 'UTC'],
                $this->headers
            );
            if ($this->core->request->error) {
                return $this->addError($this->core->request->errorMsg);
            }
            $projects = $response['data'] ?? [];
            //endregion

            //region READ all milestones
            $this->sendTerminal(" - Fetching all Milestones... [max 5000]");
            $all_milestones = $this->core->request->get_json_decode(
                "{$this->api_base_url}/core/cfo/cfi/CloudFrameWorkProjectsMilestones?_raw&_timezone=UTC",
                ['cfo_limit' => 5000, '_raw' => 1, '_timezone' => 'UTC'],
                $this->headers
            );
            if ($this->core->request->error) {
                return $this->addError($this->core->request->errorMsg);
            }
            //endregion

            //region READ all tasks
            $this->sendTerminal(" - Fetching all Tasks... [max 10000]");
            $all_tasks = $this->core->request->get_json_decode(
                "{$this->api_base_url}/core/cfo/cfi/CloudFrameWorkProjectsTasks?_raw&_timezone=UTC",
                ['cfo_limit' => 10000, '_raw' => 1, '_timezone' => 'UTC'],
                $this->headers
            );
            if ($this->core->request->error) {
                return $this->addError($this->core->request->errorMsg);
            }
            //endregion
        }
        $tot_projects = count($projects);
        $milestones_data = $all_milestones['data'] ?? [];
        $tot_milestones = count($milestones_data);
        $tasks_data = $all_tasks['data'] ?? [];
        $tot_tasks = count($tasks_data);

        $this->sendTerminal(" - Projects/Milestones/Tasks to backup: {$tot_projects}/{$tot_milestones}/{$tot_tasks}");
        $all_milestones = $this->core->utils->convertArrayIndexedByColumn($milestones_data, 'ProjectId', true);
        $all_tasks = $this->core->utils->convertArrayIndexedByColumn($tasks_data, 'ProjectId', true);
        //endregion

        //region PROCESS and SAVE each Project to backup directory
        $saved_count = 0;

        foreach ($projects as $project) {
            //region VALIDATE Project has KeyName
            if (!($project['KeyName'] ?? null)) {
                $this->sendTerminal("   # Skipping Project without KeyName");
                continue;
            }
            $key_name = $project['KeyName'];
            //endregion

            //region FETCH milestones for this Project using KeyName
            $milestones_response = [];
            if (isset($all_milestones[$key_name])) {
                $milestones_response = ['data' => &$all_milestones[$key_name]];
            }

            $milestones = [];
            if (!$this->core->request->error && ($milestones_response['data'] ?? null)) {
                $milestones = $milestones_response['data'];
                // Sort milestones by KeyId
                foreach ($milestones as &$milestone) {
                    ksort($milestone);
                }
                usort($milestones, function ($a, $b) {
                    return strcmp($a['KeyId'] ?? '', $b['KeyId'] ?? '');
                });
            }
            //endregion

            //region FETCH tasks for this Project using KeyName
            $tasks_response = [];
            if (isset($all_tasks[$key_name])) {
                $tasks_response = ['data' => &$all_tasks[$key_name]];
            }

            $tasks = [];
            if (!$this->core->request->error && ($tasks_response['data'] ?? null)) {
                $tasks = $tasks_response['data'];
                // Sort tasks by KeyId
                foreach ($tasks as &$task) {
                    ksort($task);
                }
                usort($tasks, function ($a, $b) {
                    return strcmp($a['KeyId'] ?? '', $b['KeyId'] ?? '');
                });
            }
            //endregion

            //region SORT $project keys alphabetically
            ksort($project);
            //endregion

            //region BUILD $project_data structure
            $project_data = [
                'CloudFrameWorkProjectsEntries' => $project,
                'CloudFrameWorkProjectsMilestones' => $milestones,
                'CloudFrameWorkProjectsTasks' => $tasks
            ];
            //endregion

            //region SAVE $project_data to JSON file
            $filename = $this->projectIdToFilename($key_name);
            $filepath = "{$backup_dir}/{$filename}";
            $json_content = $this->core->jsonEncode($project_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            if (file_put_contents($filepath, $json_content) === false) {
                return $this->addError("Failed to write Project [{$key_name}] to file");
            }
            $saved_count++;
            $milestone_count = count($milestones);
            $task_count = count($tasks);
            $this->sendTerminal("   + Saved: {$filename} ({$milestone_count} milestones, {$task_count} tasks)");
            //endregion
        }
        //endregion

        //region SEND summary to terminal
        $this->sendTerminal(str_repeat('-', 50));
        $this->sendTerminal(" - Total Projects/Milestones/Tasks saved: {$tot_projects}/{$tot_milestones}/{$tot_tasks}");
        //endregion

        return true;
    }

    /**
     * Update existing Project in remote platform from local backup
     */
    public function METHOD_update_from_backup()
    {
        //region VALIDATE $project_id (required parameter)
        $project_id = $this->formParams['id'] ?? null;
        if (!$project_id) {
            return $this->addError("Missing required parameter: id. Usage: _cloudia/projects/update-from-backup?id=project-keyname");
        }
        $this->sendTerminal(" - Project to update: {$project_id}");
        //endregion

        //region SET $backup_file (path to local backup JSON file)
        $filename = $this->projectIdToFilename($project_id);
        $backup_file = $this->core->system->root_path . "/buckets/backups/Projects/{$this->platform_id}/{$filename}";
        $this->sendTerminal(" - Backup file: /buckets/backups/Projects/{$this->platform_id}/{$filename}");
        //endregion

        //region VALIDATE $backup_file exists
        if (!is_file($backup_file)) {
            return $this->addError("Backup file not found: {$backup_file}");
        }
        //endregion

        //region READ $project_data from backup file
        $json_content = file_get_contents($backup_file);
        if ($json_content === false) {
            return $this->addError("Failed to read backup file: {$backup_file}");
        }
        $project_data = $this->core->jsonDecode($json_content);
        if ($project_data === null) {
            return $this->addError("Invalid JSON in backup file: {$backup_file}");
        }
        $this->sendTerminal(" - Project data loaded successfully");
        //endregion

        //region VALIDATE $project_data has correct structure
        $project = $project_data['CloudFrameWorkProjectsEntries'] ?? null;
        if (!$project || ($project['KeyName'] ?? null) !== $project_id) {
            return $this->addError("KeyName mismatch: file contains '{$project['KeyName']}' but expected '{$project_id}'");
        }
        //endregion

        //region UPDATE Project in remote platform via API
        $this->sendTerminal(" - Updating Project in remote platform...");
        $response = $this->core->request->put_json_decode(
            "{$this->api_base_url}/core/cfo/cfi/CloudFrameWorkProjectsEntries/" . urlencode($project_id) . "?_raw&_timezone=UTC",
            $project,
            $this->headers
        );

        if ($this->core->request->error) {
            return $this->addError("API request failed: " . $this->core->request->errorMsg);
        }

        if (!($response['success'] ?? false)) {
            $error_msg = $response['errorMsg'] ?? 'Unknown error';
            if (is_array($error_msg)) $error_msg = implode(', ', $error_msg);
            return $this->addError("API returned error: {$error_msg}");
        }
        $this->sendTerminal(" + Project record updated");
        //endregion

        //region SYNC milestones in remote platform
        $milestones = $project_data['CloudFrameWorkProjectsMilestones'] ?? [];
        if ($milestones) {
            $this->sendTerminal(" - Syncing {" . count($milestones) . "} milestones...");

            //region FETCH existing milestones from remote
            $existing_response = $this->core->request->get_json_decode(
                "{$this->api_base_url}/core/cfo/cfi/CloudFrameWorkProjectsMilestones?_raw&_timezone=UTC",
                ['filter_ProjectId' => $project_id, 'cfo_limit' => 2000],
                $this->headers
            );
            $existing_milestones = $existing_response['data'] ?? [];
            $existing_by_keyid = $this->core->utils->convertArrayIndexedByColumn($existing_milestones, 'KeyId');
            $local_by_keyid = $this->core->utils->convertArrayIndexedByColumn($milestones, 'KeyId');
            //endregion

            //region DELETE milestones that exist remotely but not locally
            foreach ($existing_by_keyid as $key_id => $existing) {
                if (!isset($local_by_keyid[$key_id])) {
                    $this->sendTerminal("   - Deleting remote milestone: {$existing['Title']}");
                    $this->core->request->delete(
                        "{$this->api_base_url}/core/cfo/cfi/CloudFrameWorkProjectsMilestones/{$key_id}?_raw",
                        [],
                        $this->headers
                    );
                }
            }
            //endregion

            //region UPDATE or INSERT each milestone
            foreach ($milestones as $milestone) {
                $milestone_key = $milestone['KeyId'] ?? null;
                if (!$milestone_key) {
                    // New milestone - insert
                    $response = $this->core->request->post_json_decode(
                        "{$this->api_base_url}/core/cfo/cfi/CloudFrameWorkProjectsMilestones?_raw&_timezone=UTC",
                        $milestone,
                        $this->headers
                    );
                    if ($this->core->request->error || !($response['success'] ?? false)) {
                        $this->sendTerminal("   # Warning: Failed to insert milestone [{$milestone['Title']}]");
                    } else {
                        $this->sendTerminal("   + Inserted milestone: {$milestone['Title']}");
                    }
                } else {
                    // Existing milestone - update
                    $response = $this->core->request->put_json_decode(
                        "{$this->api_base_url}/core/cfo/cfi/CloudFrameWorkProjectsMilestones/{$milestone_key}?_raw&_timezone=UTC",
                        $milestone,
                        $this->headers
                    );
                    if ($this->core->request->error || !($response['success'] ?? false)) {
                        $this->sendTerminal("   # Warning: Failed to update milestone [{$milestone['Title']}]");
                    }
                }
            }
            //endregion

            $this->sendTerminal(" + Milestones synced");
        }
        //endregion

        //region SYNC tasks in remote platform
        $tasks = $project_data['CloudFrameWorkProjectsTasks'] ?? [];
        if ($tasks) {
            $this->sendTerminal(" - Syncing {" . count($tasks) . "} tasks...");

            //region FETCH existing tasks from remote
            $existing_response = $this->core->request->get_json_decode(
                "{$this->api_base_url}/core/cfo/cfi/CloudFrameWorkProjectsTasks?_raw&_timezone=UTC",
                ['filter_ProjectId' => $project_id, 'cfo_limit' => 5000],
                $this->headers
            );
            $existing_tasks = $existing_response['data'] ?? [];
            $existing_by_keyid = $this->core->utils->convertArrayIndexedByColumn($existing_tasks, 'KeyId');
            $local_by_keyid = $this->core->utils->convertArrayIndexedByColumn($tasks, 'KeyId');
            //endregion

            //region DELETE tasks that exist remotely but not locally
            foreach ($existing_by_keyid as $key_id => $existing) {
                if (!isset($local_by_keyid[$key_id])) {
                    $this->sendTerminal("   - Deleting remote task: {$existing['Title']}");
                    $this->core->request->delete(
                        "{$this->api_base_url}/core/cfo/cfi/CloudFrameWorkProjectsTasks/{$key_id}?_raw",
                        [],
                        $this->headers
                    );
                }
            }
            //endregion

            //region UPDATE or INSERT each task
            foreach ($tasks as $task) {
                $task_key = $task['KeyId'] ?? null;
                if (!$task_key) {
                    // New task - insert
                    $response = $this->core->request->post_json_decode(
                        "{$this->api_base_url}/core/cfo/cfi/CloudFrameWorkProjectsTasks?_raw&_timezone=UTC",
                        $task,
                        $this->headers
                    );
                    if ($this->core->request->error || !($response['success'] ?? false)) {
                        $this->sendTerminal("   # Warning: Failed to insert task [{$task['Title']}]");
                    } else {
                        $this->sendTerminal("   + Inserted task: {$task['Title']}");
                    }
                } else {
                    // Existing task - update
                    $response = $this->core->request->put_json_decode(
                        "{$this->api_base_url}/core/cfo/cfi/CloudFrameWorkProjectsTasks/{$task_key}?_raw&_timezone=UTC",
                        $task,
                        $this->headers
                    );
                    if ($this->core->request->error || !($response['success'] ?? false)) {
                        $this->sendTerminal("   # Warning: Failed to update task [{$task['Title']}]");
                    }
                }
            }
            //endregion

            $this->sendTerminal(" + Tasks synced");
        }
        //endregion

        //region SEND success message to terminal
        $this->sendTerminal(str_repeat('-', 50));
        $this->sendTerminal(" + Project [{$project_id}] updated successfully in remote platform");
        //endregion

        //region GET Last version of the Project
        $this->formParams['id'] = $project_id;
        $this->METHOD_backup_from_remote();
        //endregion

        return true;
    }

    /**
     * Insert new Project in remote platform from local backup
     */
    public function METHOD_insert_from_backup()
    {
        //region VALIDATE $project_id (required parameter)
        $project_id = $this->formParams['id'] ?? null;
        if (!$project_id) {
            return $this->addError("Missing required parameter: id. Usage: _cloudia/projects/insert-from-backup?id=project-keyname");
        }
        $this->sendTerminal(" - Project to insert: {$project_id}");
        //endregion

        //region SET $backup_file (path to local backup JSON file)
        $filename = $this->projectIdToFilename($project_id);
        $backup_file = $this->core->system->root_path . "/buckets/backups/Projects/{$this->platform_id}/{$filename}";
        $this->sendTerminal(" - Backup file: /buckets/backups/Projects/{$this->platform_id}/{$filename}");
        //endregion

        //region VALIDATE $backup_file exists
        if (!is_file($backup_file)) {
            return $this->addError("Backup file not found: {$backup_file}");
        }
        //endregion

        //region READ $project_data from backup file
        $json_content = file_get_contents($backup_file);
        if ($json_content === false) {
            return $this->addError("Failed to read backup file: {$backup_file}");
        }
        $project_data = $this->core->jsonDecode($json_content);
        if ($project_data === null) {
            return $this->addError("Invalid JSON in backup file: {$backup_file}");
        }
        $this->sendTerminal(" - Project data loaded successfully");
        //endregion

        //region VALIDATE $project_data has correct structure
        $project = $project_data['CloudFrameWorkProjectsEntries'] ?? null;
        if (!$project || ($project['KeyName'] ?? null) !== $project_id) {
            return $this->addError("KeyName mismatch: file contains '{$project['KeyName']}' but expected '{$project_id}'");
        }
        //endregion

        //region CHECK if Project already exists in remote platform
        $check_response = $this->core->request->get_json_decode(
            "{$this->api_base_url}/core/cfo/cfi/CloudFrameWorkProjectsEntries/" . urlencode($project_id) . '?_raw&_timezone=UTC',
            [],
            $this->headers
        );
        if (!$this->core->request->error && ($check_response['data'] ?? null)) {
            return $this->addError("Project [{$project_id}] already exists in remote platform. Use update-from-backup instead.");
        }
        //endregion

        //region INSERT Project in remote platform via API
        $this->sendTerminal(" - Inserting Project in remote platform...");
        $response = $this->core->request->post_json_decode(
            "{$this->api_base_url}/core/cfo/cfi/CloudFrameWorkProjectsEntries?_raw&_timezone=UTC",
            $project,
            $this->headers
        );

        if ($this->core->request->error) {
            return $this->addError("API request failed: " . $this->core->request->errorMsg);
        }

        if (!($response['success'] ?? false)) {
            $error_msg = $response['errorMsg'] ?? 'Unknown error';
            if (is_array($error_msg)) $error_msg = implode(', ', $error_msg);
            return $this->addError("API returned error: {$error_msg}");
        }

        $new_key_name = $response['data']['KeyName'] ?? $project['KeyName'] ?? null;
        $this->sendTerminal(" + Project record inserted (KeyName: {$new_key_name})");
        //endregion

        //region INSERT milestones in remote platform
        $milestones = $project_data['CloudFrameWorkProjectsMilestones'] ?? [];
        if ($milestones) {
            $this->sendTerminal(" - Inserting {" . count($milestones) . "} milestones...");
            foreach ($milestones as $milestone) {
                // Remove KeyId for new milestones (let the system generate them)
                unset($milestone['KeyId']);
                $milestone['ProjectId'] = $new_key_name;

                $response = $this->core->request->post_json_decode(
                    "{$this->api_base_url}/core/cfo/cfi/CloudFrameWorkProjectsMilestones?_raw&_timezone=UTC",
                    $milestone,
                    $this->headers
                );

                if ($this->core->request->error || !($response['success'] ?? false)) {
                    $milestone_title = $milestone['Title'] ?? 'unknown';
                    $this->sendTerminal("   # Warning: Failed to insert milestone [{$milestone_title}]");
                }
            }
            $this->sendTerminal(" + Milestones inserted");
        }
        //endregion

        //region INSERT tasks in remote platform
        $tasks = $project_data['CloudFrameWorkProjectsTasks'] ?? [];
        if ($tasks) {
            $this->sendTerminal(" - Inserting {" . count($tasks) . "} tasks...");
            foreach ($tasks as $task) {
                // Remove KeyId for new tasks (let the system generate them)
                unset($task['KeyId']);
                $task['ProjectId'] = $new_key_name;

                $response = $this->core->request->post_json_decode(
                    "{$this->api_base_url}/core/cfo/cfi/CloudFrameWorkProjectsTasks?_raw&_timezone=UTC",
                    $task,
                    $this->headers
                );

                if ($this->core->request->error || !($response['success'] ?? false)) {
                    $task_title = $task['Title'] ?? 'unknown';
                    $this->sendTerminal("   # Warning: Failed to insert task [{$task_title}]");
                }
            }
            $this->sendTerminal(" + Tasks inserted");
        }
        //endregion

        //region SEND success message to terminal
        $this->sendTerminal(str_repeat('-', 50));
        $this->sendTerminal(" + Project [{$project_id}] inserted successfully in remote platform");
        //endregion

        //region GET Last version of the Project
        $this->formParams['id'] = $project_id;
        $this->METHOD_backup_from_remote();
        //endregion

        return true;
    }
}
