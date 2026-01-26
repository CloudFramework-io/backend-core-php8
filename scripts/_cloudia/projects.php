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
 * Optimization: The update-from-backup command only updates/inserts milestones and tasks that have
 * differences with the remote version. Records that are identical are skipped to minimize API calls.
 * Comparison ignores auto-updated fields (DateUpdating, DateInserting).
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
        $this->sendTerminal("  /my-tasks                      - List my open tasks across all projects");
        $this->sendTerminal("  /backup-from-remote            - Backup all Projects from remote platform");
        $this->sendTerminal("  /backup-from-remote?id=KEY     - Backup specific Project from remote platform");
        $this->sendTerminal("  /insert-from-backup?id=KEY     - Insert new Project in remote platform from local backup");
        $this->sendTerminal("  /update-from-backup?id=KEY     - Update existing Project (only changed records)");
        $this->sendTerminal("  /list-remote                   - List all Projects in remote platform");
        $this->sendTerminal("  /list-local                    - List all Projects in local backup");
        $this->sendTerminal("");
        $this->sendTerminal("Notes:");
        $this->sendTerminal("  - The ?id= parameter is the Project KeyName (e.g., cloud-platform)");
        $this->sendTerminal("  - update-from-backup only updates milestones/tasks that differ from remote");
        $this->sendTerminal("  - Identical records are skipped to minimize API calls");
        $this->sendTerminal("");
        $this->sendTerminal("Examples:");
        $this->sendTerminal("  composer run-script script _cloudia/projects/my-tasks");
        $this->sendTerminal("  composer run-script script \"_cloudia/projects/backup-from-remote?id=cloud-platform\"");
        $this->sendTerminal("  composer run-script script \"_cloudia/projects/update-from-backup?id=cloud-platform\"");
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
     * Compare two records and determine if they are different
     * Ignores fields that are auto-updated by the system (DateUpdating, DateInserting)
     *
     * @param array $local Local record from backup
     * @param array $remote Remote record from API
     * @param array $ignore_fields Fields to ignore in comparison (default: DateUpdating, DateInserting)
     * @return bool True if records are different, false if identical
     */
    private function recordsAreDifferent(array $local, array $remote, array $ignore_fields = ['DateUpdating', 'DateInserting']): bool
    {
        // Create copies to avoid modifying originals
        $local_copy = $local;
        $remote_copy = $remote;

        // Remove ignored fields from both
        foreach ($ignore_fields as $field) {
            unset($local_copy[$field], $remote_copy[$field]);
        }

        // Sort keys for consistent comparison
        ksort($local_copy);
        ksort($remote_copy);

        // Compare JSON representations
        return json_encode($local_copy) !== json_encode($remote_copy);
    }

    /**
     * Display milestones report separated by open/closed status
     *
     * @param array $milestones Array of milestone data from backup
     * @param int $inserted Number of inserted milestones (0 for unchanged report)
     * @param int $updated Number of updated milestones (0 for unchanged report)
     * @param int $unchanged Number of unchanged milestones
     */
    private function displayMilestonesReport(array $milestones, int $inserted = 0, int $updated = 0, int $unchanged = 0): void
    {
        // Build report items with open/closed classification
        $report_open = [];
        $report_closed = [];

        foreach ($milestones as $m) {
            $item = [
                'id' => $m['id'] ?? $m['KeyId'] ?? 'N/A',
                'title' => $m['title'] ?? $m['Title'] ?? 'Untitled',
                'player' => $m['player'] ?? $m['PlayerId'] ?? '-',
                'status' => $m['status'] ?? $m['Status'] ?? '-',
                'deadline' => $m['deadline'] ?? $m['DateDeadline'] ?? '-',
                'sync' => $m['sync'] ?? '-'
            ];

            // Determine if closed
            $status = $m['status'] ?? $m['Status'] ?? '';
            $open = $m['Open'] ?? $m['is_closed'] ?? null;
            $is_closed = isset($m['is_closed']) ? $m['is_closed'] : (in_array($status, ['closed', 'canceled']) || $open === false);

            if ($is_closed) {
                $report_closed[] = $item;
            } else {
                $report_open[] = $item;
            }
        }

        // Display OPEN milestones
        $this->sendTerminal("");
        $this->sendTerminal("   Milestones OPEN (" . count($report_open) . "):");
        $this->sendTerminal("   " . str_repeat('-', 130));
        $this->sendTerminal(sprintf("   %-12s %-30s %-25s %-12s %-12s %s", "KeyId", "Title", "Assignee", "Status", "Deadline", "Sync"));
        $this->sendTerminal("   " . str_repeat('-', 130));
        foreach ($report_open as $m) {
            $title_display = strlen($m['title']) > 27 ? substr($m['title'], 0, 24) . '...' : $m['title'];
            $player_display = strlen($m['player']) > 22 ? substr($m['player'], 0, 19) . '...' : $m['player'];
            $deadline_display = $m['deadline'] !== '-' ? substr($m['deadline'], 0, 10) : '-';
            $this->sendTerminal(sprintf("   %-12s %-30s %-25s %-12s %-12s %s", $m['id'], $title_display, $player_display, $m['status'], $deadline_display, $m['sync']));
        }

        // Display CLOSED milestones
        $this->sendTerminal("");
        $this->sendTerminal("   Milestones CLOSED (" . count($report_closed) . "):");
        $this->sendTerminal("   " . str_repeat('-', 130));
        foreach ($report_closed as $m) {
            $title_display = strlen($m['title']) > 27 ? substr($m['title'], 0, 24) . '...' : $m['title'];
            $player_display = strlen($m['player']) > 22 ? substr($m['player'], 0, 19) . '...' : $m['player'];
            $deadline_display = $m['deadline'] !== '-' ? substr($m['deadline'], 0, 10) : '-';
            $this->sendTerminal(sprintf("   %-12s %-30s %-25s %-12s %-12s %s", $m['id'], $title_display, $player_display, $m['status'], $deadline_display, $m['sync']));
        }
        $this->sendTerminal("   " . str_repeat('-', 130));
        $this->sendTerminal(" + Milestones: {$inserted} created, {$updated} updated, {$unchanged} unchanged");
    }

    /**
     * Display tasks report separated by open/closed status
     *
     * @param array $tasks Array of task data from backup
     * @param int $inserted Number of inserted tasks (0 for unchanged report)
     * @param int $updated Number of updated tasks (0 for unchanged report)
     * @param int $unchanged Number of unchanged tasks
     */
    private function displayTasksReport(array $tasks, int $inserted = 0, int $updated = 0, int $unchanged = 0): void
    {
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
            $players = $t['player'] ?? $t['PlayerId'] ?? [];
            $player = is_array($players) ? implode(',', $players) : $players;
            $player = $player ?: '-';

            // Get hours values
            $time_spent = floatval($t['TimeSpent'] ?? 0);
            $time_estimated = floatval($t['TimeEstimated'] ?? 0);

            // If hours already formatted as string, parse it
            if (isset($t['hours']) && is_string($t['hours']) && strpos($t['hours'], '/') !== false) {
                list($spent_str, $estimated_str) = explode('/', $t['hours']);
                $time_spent = floatval($spent_str);
                $time_estimated = floatval($estimated_str);
            }

            $item = [
                'id' => $t['id'] ?? $t['KeyId'] ?? 'N/A',
                'title' => $t['title'] ?? $t['Title'] ?? 'Untitled',
                'player' => $player,
                'status' => $t['status'] ?? $t['Status'] ?? '-',
                'deadline' => $t['deadline'] ?? $t['DateDeadline'] ?? '-',
                'hours' => sprintf("%.1f/%.1f", $time_spent, $time_estimated),
                'sync' => $t['sync'] ?? '-'
            ];

            // Determine if closed
            $status = $t['status'] ?? $t['Status'] ?? '';
            $open = $t['Open'] ?? $t['is_closed'] ?? null;
            $is_closed = isset($t['is_closed']) ? $t['is_closed'] : (in_array($status, ['closed', 'canceled']) || $open === false);

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
        $this->sendTerminal("   " . str_repeat('-', 145));
        $this->sendTerminal(sprintf("   %-12s %-25s %-22s %-12s %-12s %-10s %s", "KeyId", "Title", "Assignee", "Status", "Deadline", "Hours", "Sync"));
        $this->sendTerminal("   " . str_repeat('-', 145));
        foreach ($report_open as $t) {
            $title_display = strlen($t['title']) > 22 ? substr($t['title'], 0, 19) . '...' : $t['title'];
            $player_display = strlen($t['player']) > 19 ? substr($t['player'], 0, 16) . '...' : $t['player'];
            $deadline_display = $t['deadline'] !== '-' ? substr($t['deadline'], 0, 10) : '-';
            $this->sendTerminal(sprintf("   %-12s %-25s %-22s %-12s %-12s %-10s %s", $t['id'], $title_display, $player_display, $t['status'], $deadline_display, $t['hours'], $t['sync']));
        }
        $this->sendTerminal(sprintf("   %90s %-10s", "Subtotal:", sprintf("%.1f/%.1f", $total_spent_open, $total_estimated_open)));

        // Display CLOSED tasks
        $this->sendTerminal("");
        $this->sendTerminal("   Tasks CLOSED (" . count($report_closed) . "):");
        $this->sendTerminal("   " . str_repeat('-', 145));
        foreach ($report_closed as $t) {
            $title_display = strlen($t['title']) > 22 ? substr($t['title'], 0, 19) . '...' : $t['title'];
            $player_display = strlen($t['player']) > 19 ? substr($t['player'], 0, 16) . '...' : $t['player'];
            $deadline_display = $t['deadline'] !== '-' ? substr($t['deadline'], 0, 10) : '-';
            $this->sendTerminal(sprintf("   %-12s %-25s %-22s %-12s %-12s %-10s %s", $t['id'], $title_display, $player_display, $t['status'], $deadline_display, $t['hours'], $t['sync']));
        }
        $this->sendTerminal(sprintf("   %90s %-10s", "Subtotal:", sprintf("%.1f/%.1f", $total_spent_closed, $total_estimated_closed)));

        // Display totals
        $total_spent = $total_spent_open + $total_spent_closed;
        $total_estimated = $total_estimated_open + $total_estimated_closed;
        $this->sendTerminal("   " . str_repeat('-', 145));
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
        $this->sendTerminal(" + Tasks: {$inserted} created, {$updated} updated, {$unchanged} unchanged");
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
            //region VERIFY all milestones have the correct ProjectId
            $milestones_data = $all_milestones['data'] ?? [];
            if (count($milestones_data) > 1) {
                $invalid_milestones = array_filter($milestones_data, function($m) use ($project_id) {
                    return ($m['ProjectId'] ?? '') !== $project_id;
                });
                if ($invalid_milestones) {
                    $invalid_ids = array_column($invalid_milestones, 'KeyId');
                    return $this->addError("Found " . count($invalid_milestones) . " milestones with incorrect ProjectId (expected '{$project_id}'): " . implode(', ', $invalid_ids));
                }
            }
            //endregion
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
            //region VERIFY all tasks have the correct ProjectId
            $tasks_data = $all_tasks['data'] ?? [];
            if (count($tasks_data) > 1) {
                $invalid_tasks = array_filter($tasks_data, function($t) use ($project_id) {
                    return ($t['ProjectId'] ?? '') !== $project_id;
                });
                if ($invalid_tasks) {
                    $invalid_ids = array_column($invalid_tasks, 'KeyId');
                    return $this->addError("Found " . count($invalid_tasks) . " tasks with incorrect ProjectId (expected '{$project_id}'): " . implode(', ', $invalid_ids));
                }
            }
            //endregion
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
        $unchanged_count = 0;

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

            //region DISPLAY milestones and tasks reports
            $this->displayMilestonesReport($milestones, 0, 0, count($milestones));
            $this->displayTasksReport($tasks, 0, 0, count($tasks));
            //endregion

            //region SAVE $project_data to JSON file (only if changed)
            $filename = $this->projectIdToFilename($key_name);
            $filepath = "{$backup_dir}/{$filename}";
            $json_content = $this->core->jsonEncode($project_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

            // Compare with existing file to detect changes
            if (is_file($filepath)) {
                $existing_content = file_get_contents($filepath);
                if ($existing_content === $json_content) {
                    $unchanged_count++;
                    $this->sendTerminal("   = Unchanged: {$filename}");
                    continue;
                }
            }

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
        $this->sendTerminal(" - Total Projects/Milestones/Tasks: {$tot_projects}/{$tot_milestones}/{$tot_tasks} (saved: {$saved_count}, unchanged: {$unchanged_count})");
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

        //region FETCH remote data and COMPARE with local backup
        $this->sendTerminal(" - Fetching remote data to compare...");

        // Fetch remote project
        $remote_project_response = $this->core->request->get_json_decode(
            "{$this->api_base_url}/core/cfo/cfi/CloudFrameWorkProjectsEntries/display/" . urlencode($project_id) . '?_raw&_timezone=UTC',
            ['_raw' => 1, '_timezone' => 'UTC'],
            $this->headers
        );
        if ($this->core->request->error || !($remote_project_response['data'] ?? null)) {
            $this->sendTerminal(" - Remote project not found, proceeding with update...");
        } else {
            // Fetch remote milestones
            $remote_milestones_response = $this->core->request->get_json_decode(
                "{$this->api_base_url}/core/cfo/cfi/CloudFrameWorkProjectsMilestones?_raw&_timezone=UTC",
                ['filter_ProjectId' => $project_id, 'cfo_limit' => 2000, '_raw' => 1, '_timezone' => 'UTC'],
                $this->headers
            );
            $remote_milestones = $remote_milestones_response['data'] ?? [];

            // Fetch remote tasks
            $remote_tasks_response = $this->core->request->get_json_decode(
                "{$this->api_base_url}/core/cfo/cfi/CloudFrameWorkProjectsTasks?_raw&_timezone=UTC",
                ['filter_ProjectId' => $project_id, 'cfo_limit' => 5000, '_raw' => 1, '_timezone' => 'UTC'],
                $this->headers
            );
            $remote_tasks = $remote_tasks_response['data'] ?? [];

            // Sort remote project keys
            $remote_project = $remote_project_response['data'];
            ksort($remote_project);

            // Sort remote milestones
            foreach ($remote_milestones as &$milestone) {
                ksort($milestone);
            }
            usort($remote_milestones, function ($a, $b) {
                return strcmp($a['KeyId'] ?? '', $b['KeyId'] ?? '');
            });

            // Sort remote tasks
            foreach ($remote_tasks as &$task) {
                ksort($task);
            }
            usort($remote_tasks, function ($a, $b) {
                return strcmp($a['KeyId'] ?? '', $b['KeyId'] ?? '');
            });

            // Build remote data structure for comparison
            $remote_data = [
                'CloudFrameWorkProjectsEntries' => $remote_project,
                'CloudFrameWorkProjectsMilestones' => $remote_milestones,
                'CloudFrameWorkProjectsTasks' => $remote_tasks
            ];

            // Compare JSON representations
            $local_json = $this->core->jsonEncode($project_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            $remote_json = $this->core->jsonEncode($remote_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

            if ($local_json === $remote_json) {
                $this->sendTerminal(" = Project [{$project_id}] is unchanged (local backup equals remote)");

                //region DISPLAY report for unchanged milestones
                $milestones = $project_data['CloudFrameWorkProjectsMilestones'] ?? [];
                if ($milestones) {
                    $this->displayMilestonesReport($milestones, 0, 0, count($milestones));
                }
                //endregion

                //region DISPLAY report for unchanged tasks
                $tasks = $project_data['CloudFrameWorkProjectsTasks'] ?? [];
                if ($tasks) {
                    $this->displayTasksReport($tasks, 0, 0, count($tasks));
                }
                //endregion

                $this->sendTerminal(str_repeat('-', 50));
                $this->sendTerminal(" = No updates needed for project [{$project_id}]");
                return true;
            }
            $this->sendTerminal(" - Changes detected, proceeding with update...");
        }
        //endregion

        //region UPDATE Project in remote platform via API
        $this->sendTerminal(" - Updating Project in remote platform...");
        $response = $this->core->request->put_json_decode(
            "{$this->api_base_url}/core/cfo/cfi/CloudFrameWorkProjectsEntries/" . urlencode($project_id) . "?_raw&_timezone=UTC",
            $project,
            $this->headers,
            true
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

        //region CHECK $confirm parameter for destructive operations
        $confirm_delete = ($this->formParams['confirm'] ?? '') === '1';
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
            //endregion

            //region VALIDATE milestones belong to the correct project
            $invalid_milestones = [];
            foreach ($existing_milestones as $milestone) {
                if (($milestone['ProjectId'] ?? '') !== $project_id) {
                    $invalid_milestones[] = "[{$milestone['KeyId']}] {$milestone['Title']} (ProjectId: {$milestone['ProjectId']})";
                }
            }
            if ($invalid_milestones) {
                $this->sendTerminal("");
                $this->sendTerminal("   ❌ CRITICAL ERROR: API filter_ProjectId is not working correctly!");
                $this->sendTerminal("   The API returned {" . count($invalid_milestones) . "} milestones from OTHER projects:");
                foreach (array_slice($invalid_milestones, 0, 5) as $inv) {
                    $this->sendTerminal("      - {$inv}");
                }
                if (count($invalid_milestones) > 5) {
                    $this->sendTerminal("      ... and " . (count($invalid_milestones) - 5) . " more");
                }
                $this->sendTerminal("");
                return $this->addError("API BUG: filter_ProjectId={$project_id} returned milestones from other projects. Aborting to prevent data loss.");
            }
            //endregion

            $existing_by_keyid = $this->core->utils->convertArrayIndexedByColumn($existing_milestones, 'KeyId');
            $local_by_keyid = $this->core->utils->convertArrayIndexedByColumn($milestones, 'KeyId');
            //endregion

            //region IDENTIFY milestones to delete (exist remotely but not locally)
            $milestones_to_delete = [];
            foreach ($existing_by_keyid as $key_id => $existing) {
                if (!isset($local_by_keyid[$key_id])) {
                    $milestones_to_delete[$key_id] = $existing;
                }
            }
            //endregion

            //region DELETE milestones (only if confirmed or none to delete)
            if ($milestones_to_delete) {
                $this->sendTerminal("   ⚠️  {" . count($milestones_to_delete) . "} milestones will be DELETED from remote:");
                foreach ($milestones_to_delete as $key_id => $existing) {
                    $this->sendTerminal("      - [{$key_id}] {$existing['Title']}");
                }

                if (!$confirm_delete) {
                    $this->sendTerminal("");
                    $this->sendTerminal("   ❌ DELETION SKIPPED: Add 'confirm=1' parameter to confirm deletion");
                    $this->sendTerminal("      Example: _cloudia/projects/update-from-backup?id={$project_id}&confirm=1");
                } else {
                    $this->sendTerminal("   ✓ Deletion confirmed, proceeding...");
                    foreach ($milestones_to_delete as $key_id => $existing) {
                        $this->sendTerminal("   - Deleting remote milestone: {$existing['Title']}");
                        $this->core->request->delete(
                            "{$this->api_base_url}/core/cfo/cfi/CloudFrameWorkProjectsMilestones/{$key_id}?_raw",
                            [],
                            $this->headers
                        );
                    }
                }
            }
            //endregion

            //region UPDATE or INSERT each milestone (only if different from remote)
            $milestones_inserted = 0;
            $milestones_updated = 0;
            $milestones_unchanged = 0;
            $milestones_report = [];

            foreach ($milestones as $milestone) {
                $milestone_key = $milestone['KeyId'] ?? null;
                $milestone_title = $milestone['Title'] ?? 'Untitled';
                $milestone_player = $milestone['PlayerId'] ?? '-';
                $milestone_status = $milestone['Status'] ?? '-';
                $milestone_deadline = $milestone['DateDeadline'] ?? '-';
                $milestone_open = $milestone['Open'] ?? true;
                $is_closed = in_array($milestone_status, ['closed', 'canceled']) || $milestone_open === false;

                if (!$milestone_key) {
                    // New milestone (no KeyId) - insert
                    $response = $this->core->request->post_json_decode(
                        "{$this->api_base_url}/core/cfo/cfi/CloudFrameWorkProjectsMilestones?_raw&_timezone=UTC",
                        $milestone,
                        $this->headers
                    );
                    if ($this->core->request->error || !($response['success'] ?? false)) {
                        $milestones_report[] = ['id' => 'NEW', 'title' => $milestone_title, 'player' => $milestone_player, 'status' => $milestone_status, 'deadline' => $milestone_deadline, 'sync' => 'ERROR', 'is_closed' => $is_closed];
                    } else {
                        $milestones_inserted++;
                        $new_key = $response['data']['KeyId'] ?? 'NEW';
                        $milestones_report[] = ['id' => $new_key, 'title' => $milestone_title, 'player' => $milestone_player, 'status' => $milestone_status, 'deadline' => $milestone_deadline, 'sync' => 'CREATED', 'is_closed' => $is_closed];
                    }
                } else {
                    // Check if milestone exists remotely and compare
                    $remote_milestone = $existing_by_keyid[$milestone_key] ?? null;

                    if (!$remote_milestone) {
                        // Milestone doesn't exist remotely (has KeyId but not found) - insert
                        $response = $this->core->request->post_json_decode(
                            "{$this->api_base_url}/core/cfo/cfi/CloudFrameWorkProjectsMilestones?_raw&_timezone=UTC",
                            $milestone,
                            $this->headers
                        );
                        if ($this->core->request->error || !($response['success'] ?? false)) {
                            $milestones_report[] = ['id' => $milestone_key, 'title' => $milestone_title, 'player' => $milestone_player, 'status' => $milestone_status, 'deadline' => $milestone_deadline, 'sync' => 'ERROR', 'is_closed' => $is_closed];
                        } else {
                            $milestones_inserted++;
                            $milestones_report[] = ['id' => $milestone_key, 'title' => $milestone_title, 'player' => $milestone_player, 'status' => $milestone_status, 'deadline' => $milestone_deadline, 'sync' => 'CREATED', 'is_closed' => $is_closed];
                        }
                    } elseif ($this->recordsAreDifferent($milestone, $remote_milestone)) {
                        // Milestone exists and is different - update
                        $response = $this->core->request->put_json_decode(
                            "{$this->api_base_url}/core/cfo/cfi/CloudFrameWorkProjectsMilestones/{$milestone_key}?_raw&_timezone=UTC",
                            $milestone,
                            $this->headers,
                            true
                        );
                        if ($this->core->request->error || !($response['success'] ?? false)) {
                            $milestones_report[] = ['id' => $milestone_key, 'title' => $milestone_title, 'player' => $milestone_player, 'status' => $milestone_status, 'deadline' => $milestone_deadline, 'sync' => 'ERROR', 'is_closed' => $is_closed];
                        } else {
                            $milestones_updated++;
                            $milestones_report[] = ['id' => $milestone_key, 'title' => $milestone_title, 'player' => $milestone_player, 'status' => $milestone_status, 'deadline' => $milestone_deadline, 'sync' => 'UPDATED', 'is_closed' => $is_closed];
                        }
                    } else {
                        // Milestone exists and is identical - skip
                        $milestones_unchanged++;
                        $milestones_report[] = ['id' => $milestone_key, 'title' => $milestone_title, 'player' => $milestone_player, 'status' => $milestone_status, 'deadline' => $milestone_deadline, 'sync' => '-', 'is_closed' => $is_closed];
                    }
                }
            }
            //endregion

            //region DISPLAY milestones report
            $this->displayMilestonesReport($milestones_report, $milestones_inserted, $milestones_updated, $milestones_unchanged);
            //endregion
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
            //endregion

            //region VALIDATE tasks belong to the correct project
            $invalid_tasks = [];
            foreach ($existing_tasks as $task) {
                if (($task['ProjectId'] ?? '') !== $project_id) {
                    $invalid_tasks[] = "[{$task['KeyId']}] {$task['Title']} (ProjectId: {$task['ProjectId']})";
                }
            }
            if ($invalid_tasks) {
                $this->sendTerminal("");
                $this->sendTerminal("   ❌ CRITICAL ERROR: API filter_ProjectId is not working correctly!");
                $this->sendTerminal("   The API returned {" . count($invalid_tasks) . "} tasks from OTHER projects:");
                foreach (array_slice($invalid_tasks, 0, 5) as $inv) {
                    $this->sendTerminal("      - {$inv}");
                }
                if (count($invalid_tasks) > 5) {
                    $this->sendTerminal("      ... and " . (count($invalid_tasks) - 5) . " more");
                }
                $this->sendTerminal("");
                return $this->addError("API BUG: filter_ProjectId={$project_id} returned tasks from other projects. Aborting to prevent data loss.");
            }
            //endregion

            $existing_by_keyid = $this->core->utils->convertArrayIndexedByColumn($existing_tasks, 'KeyId');
            $local_by_keyid = $this->core->utils->convertArrayIndexedByColumn($tasks, 'KeyId');
            //endregion

            //region IDENTIFY tasks to delete (exist remotely but not locally)
            $tasks_to_delete = [];
            foreach ($existing_by_keyid as $key_id => $existing) {
                if (!isset($local_by_keyid[$key_id])) {
                    $tasks_to_delete[$key_id] = $existing;
                }
            }
            //endregion

            //region DELETE tasks (only if confirmed or none to delete)
            if ($tasks_to_delete) {
                $this->sendTerminal("   ⚠️  {" . count($tasks_to_delete) . "} tasks will be DELETED from remote:");
                foreach ($tasks_to_delete as $key_id => $existing) {
                    $this->sendTerminal("      - [{$key_id}] {$existing['Title']}");
                }

                if (!$confirm_delete) {
                    $this->sendTerminal("");
                    $this->sendTerminal("   ❌ DELETION SKIPPED: Add 'confirm=1' parameter to confirm deletion");
                    $this->sendTerminal("      Example: _cloudia/projects/update-from-backup?id={$project_id}&confirm=1");
                } else {
                    $this->sendTerminal("   ✓ Deletion confirmed, proceeding...");
                    foreach ($tasks_to_delete as $key_id => $existing) {
                        $this->sendTerminal("   - Deleting remote task: {$existing['Title']}");
                        $this->core->request->delete(
                            "{$this->api_base_url}/core/cfo/cfi/CloudFrameWorkProjectsTasks/{$key_id}?_raw",
                            [],
                            $this->headers
                        );
                    }
                }
            }
            //endregion

            //region UPDATE or INSERT each task (only if different from remote)
            $tasks_inserted = 0;
            $tasks_updated = 0;
            $tasks_unchanged = 0;
            $tasks_report = [];

            foreach ($tasks as $task) {
                $task_key = $task['KeyId'] ?? null;
                $task_title = $task['Title'] ?? 'Untitled';
                $task_players = $task['PlayerId'] ?? [];
                $task_player = is_array($task_players) ? implode(',', $task_players) : $task_players;
                $task_status = $task['Status'] ?? '-';
                $task_deadline = $task['DateDeadline'] ?? '-';
                $task_hours = sprintf("%.1f/%.1f", floatval($task['TimeSpent'] ?? 0), floatval($task['TimeEstimated'] ?? 0));
                $task_open = $task['Open'] ?? true;
                $is_closed = in_array($task_status, ['closed', 'canceled']) || $task_open === false;

                if (!$task_key) {
                    // New task (no KeyId) - insert
                    $response = $this->core->request->post_json_decode(
                        "{$this->api_base_url}/core/cfo/cfi/CloudFrameWorkProjectsTasks?_raw&_timezone=UTC",
                        $task,
                        $this->headers
                    );
                    if ($this->core->request->error || !($response['success'] ?? false)) {
                        $tasks_report[] = ['id' => 'NEW', 'title' => $task_title, 'player' => $task_player, 'status' => $task_status, 'deadline' => $task_deadline, 'hours' => $task_hours, 'sync' => 'ERROR', 'is_closed' => $is_closed];
                    } else {
                        $tasks_inserted++;
                        $new_key = $response['data']['KeyId'] ?? 'NEW';
                        $tasks_report[] = ['id' => $new_key, 'title' => $task_title, 'player' => $task_player, 'status' => $task_status, 'deadline' => $task_deadline, 'hours' => $task_hours, 'sync' => 'CREATED', 'is_closed' => $is_closed];
                    }
                } else {
                    // Check if task exists remotely and compare
                    $remote_task = $existing_by_keyid[$task_key] ?? null;

                    if (!$remote_task) {
                        // Task doesn't exist remotely (has KeyId but not found) - insert
                        $response = $this->core->request->post_json_decode(
                            "{$this->api_base_url}/core/cfo/cfi/CloudFrameWorkProjectsTasks?_raw&_timezone=UTC",
                            $task,
                            $this->headers
                        );
                        if ($this->core->request->error || !($response['success'] ?? false)) {
                            $tasks_report[] = ['id' => $task_key, 'title' => $task_title, 'player' => $task_player, 'status' => $task_status, 'deadline' => $task_deadline, 'hours' => $task_hours, 'sync' => 'ERROR', 'is_closed' => $is_closed];
                        } else {
                            $tasks_inserted++;
                            $tasks_report[] = ['id' => $task_key, 'title' => $task_title, 'player' => $task_player, 'status' => $task_status, 'deadline' => $task_deadline, 'hours' => $task_hours, 'sync' => 'CREATED', 'is_closed' => $is_closed];
                        }
                    } elseif ($this->recordsAreDifferent($task, $remote_task)) {
                        // Task exists and is different - update
                        $response = $this->core->request->put_json_decode(
                            "{$this->api_base_url}/core/cfo/cfi/CloudFrameWorkProjectsTasks/{$task_key}?_raw&_timezone=UTC",
                            $task,
                            $this->headers,
                            true
                        );
                        if ($this->core->request->error || !($response['success'] ?? false)) {
                            $tasks_report[] = ['id' => $task_key, 'title' => $task_title, 'player' => $task_player, 'status' => $task_status, 'deadline' => $task_deadline, 'hours' => $task_hours, 'sync' => 'ERROR', 'is_closed' => $is_closed];
                        } else {
                            $tasks_updated++;
                            $tasks_report[] = ['id' => $task_key, 'title' => $task_title, 'player' => $task_player, 'status' => $task_status, 'deadline' => $task_deadline, 'hours' => $task_hours, 'sync' => 'UPDATED', 'is_closed' => $is_closed];
                        }
                    } else {
                        // Task exists and is identical - skip
                        $tasks_unchanged++;
                        $tasks_report[] = ['id' => $task_key, 'title' => $task_title, 'player' => $task_player, 'status' => $task_status, 'deadline' => $task_deadline, 'hours' => $task_hours, 'sync' => '-', 'is_closed' => $is_closed];
                    }
                }
            }
            //endregion

            //region DISPLAY tasks report
            $this->displayTasksReport($tasks_report, $tasks_inserted, $tasks_updated, $tasks_unchanged);
            //endregion
        }
        //endregion

        //region SEND success message to terminal
        $this->sendTerminal(str_repeat('-', 50));
        $this->sendTerminal(" + Project [{$project_id}] sync completed");
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

    /**
     * List my open tasks across all projects
     */
    public function METHOD_my_tasks()
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

            // Format dates info
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
}
