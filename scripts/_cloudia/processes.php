<?php
/**
 * Process Documentation Backup Script
 *
 * This script provides functionality to manage Process documentation by:
 * - Backing up Processes from the remote platform to local storage
 * - Inserting new Processes from local backup to the remote platform
 * - Updating existing Processes from local backup to the remote platform
 * - Listing Processes in remote and local storage
 *
 * The script operates on Process documentation stored in the `buckets/backups/Processes/{platform}/` directory
 * and synchronizes them with the CloudFramework platform via REST API.
 *
 * Each Process backup file contains:
 * - CloudFrameWorkDevDocumentationForProcesses: Main Process documentation
 * - CloudFrameWorkDevDocumentationForSubProcesses: Individual subprocess documentation
 *
 * Usage:
 *   _cloudia/processes/backup-from-remote           - Backup all Processes from remote
 *   _cloudia/processes/backup-from-remote?id=PROC1  - Backup specific Process
 *   _cloudia/processes/insert-from-backup?id=PROC1  - Insert new Process to remote
 *   _cloudia/processes/update-from-backup?id=PROC1  - Update existing Process in remote
 *   _cloudia/processes/list-remote                  - List all Processes in remote
 *   _cloudia/processes/list-local                   - List all Processes in local backup
 *
 * @author CloudFramework Development Team
 * @version 1.0
 */
class Script extends CoreScripts
{
    /** @var string Platform ID for Process operations */
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
        if (!$this->core->user->hasAnyPrivilege('development-admin,development-user')) {
            return $this->addError('You do not have permission [development-admin] to execute this script');
        }
        //endregion

        //region SET AUTHENTICATE header for API call
        $this->headers = [
            'X-WEB-KEY' => '/scripts/_cloudia/processes',
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
        $this->sendTerminal("  /backup-from-remote        - Backup all Processes from remote platform");
        $this->sendTerminal("  /backup-from-remote?id=xx  - Backup specific Process from remote platform");
        $this->sendTerminal("  /insert-from-backup?id=xx  - Insert new Process in remote platform from local backup");
        $this->sendTerminal("  /update-from-backup?id=xx  - Update existing Process in remote platform from local backup");
        $this->sendTerminal("  /list-remote               - List all Processes in remote platform");
        $this->sendTerminal("  /list-local                - List all Processes in local backup");
        $this->sendTerminal("");
        $this->sendTerminal("Examples:");
        $this->sendTerminal("  composer run-script script \"_cloudia/processes/backup-from-remote?id=HIPOTECH-001\"");
        $this->sendTerminal("  composer run-script script _cloudia/processes/list-remote");
    }

    /**
     * Convert Process KeyName to backup filename
     *
     * @param string $process_id Process KeyName (e.g., HIPOTECH-001)
     * @return string Filename (e.g., HIPOTECH-001.json)
     */
    private function processIdToFilename($process_id)
    {
        // Replace any problematic characters for filesystem
        $name = str_replace(['/', '\\', ':', '*', '?', '"', '<', '>', '|'], '_', $process_id);
        return $name . '.json';
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
        if (($backup_dir .= '/Processes') && !is_dir($backup_dir) && !mkdir($backup_dir)) {
            $this->addError("Backup directory [/buckets/backups/Processes] can not be created");
            return false;
        }
        if (($backup_dir .= '/' . $this->platform_id) && !is_dir($backup_dir) && !mkdir($backup_dir)) {
            $this->addError("Backup directory [/buckets/backups/Processes/{$this->platform_id}] can not be created");
            return false;
        }
        return $backup_dir;
    }

    /**
     * List all Processes in remote platform
     */
    public function METHOD_list_remote()
    {
        //region FETCH all Processes from remote platform
        $this->sendTerminal("Listing Processes in remote platform [{$this->platform_id}]:");
        $this->sendTerminal(str_repeat('-', 60));

        $params = ['_fields' => 'KeyName,Title,Status', '_order' => 'KeyName', '_limit' => 1000];
        $response = $this->core->request->get_json_decode(
            "{$this->api_base_url}/core/cfo/cfi/CloudFrameWorkDevDocumentationForProcesses?_raw=1&_timezone=UTC",
            $params,
            $this->headers
        );

        if ($this->core->request->error) {
            return $this->addError($this->core->request->errorMsg);
        }

        $processes = $response['data'] ?? [];
        if (!$processes) {
            $this->sendTerminal("No Processes found in remote platform");
            return true;
        }

        foreach ($processes as $process) {
            $status = $process['Status'] ?? 'N/A';
            $title = $process['Title'] ?? 'N/A';
            $this->sendTerminal(" {$process['KeyName']} - {$title} [{$status}]");
        }
        $this->sendTerminal(str_repeat('-', 60));
        $this->sendTerminal("Total: " . count($processes) . " Processes");
        //endregion

        return true;
    }

    /**
     * List all Processes in local backup
     */
    public function METHOD_list_local()
    {
        //region LIST all Processes in local backup
        $this->sendTerminal("Listing Processes in local backup [{$this->platform_id}]:");
        $this->sendTerminal(str_repeat('-', 60));

        $directory_path = $this->core->system->root_path . '/buckets/backups/Processes/' . $this->platform_id;

        if (!is_dir($directory_path)) {
            $this->sendTerminal("Backup directory not found: /buckets/backups/Processes/{$this->platform_id}");
            return true;
        }

        $files = glob($directory_path . '/*.json');
        if (!$files) {
            $this->sendTerminal("No Process backup files found");
            return true;
        }

        foreach ($files as $file) {
            $data = $this->core->jsonDecode(file_get_contents($file));
            $processData = $data['CloudFrameWorkDevDocumentationForProcesses'] ?? [];
            $keyName = $processData['KeyName'] ?? basename($file, '.json');
            $title = $processData['Title'] ?? 'N/A';
            $status = $processData['Status'] ?? 'N/A';
            $this->sendTerminal(" {$keyName} - {$title} [{$status}]");
        }
        $this->sendTerminal(str_repeat('-', 60));
        $this->sendTerminal("Total: " . count($files) . " Processes");
        //endregion

        return true;
    }

    /**
     * Backup Processes from remote platform to local storage
     */
    public function METHOD_backup_from_remote()
    {
        //region VERIFY backup directory
        if (!$backup_dir = $this->getBackupDir()) {
            return false;
        }
        $this->sendTerminal(" - Backup directory: /buckets/backups/Processes/{$this->platform_id}");
        //endregion

        //region SET $process_id (specific Process to backup, or null for all)
        $process_id = $this->formParams['id'] ?? null;
        //endregion

        //region READ Processes from remote API
        $processes=[];
        $all_subprocesses=[];
        if ($process_id) {
            //region FETCH single Process by KeyName
            $this->sendTerminal(" - Fetching Process: {$process_id}");
            $response = $this->core->request->get_json_decode(
                "{$this->api_base_url}/core/cfo/cfi/CloudFrameWorkDevDocumentationForProcesses/display/" . urlencode($process_id) . '?_raw&_timezone=UTC',
                ['_raw' => 1, '_timezone' => 'UTC'],
                $this->headers
            );
            if ($this->core->request->error) {
                return $this->addError($this->core->request->errorMsg);
            }
            if (!($response['data'] ?? null)) {
                return $this->addError("Process [{$process_id}] not found in remote platform");
            }
            $processes = [$response['data']];
            //endregion

            //region READ subprocesses associated
            $this->sendTerminal(" - Fetching all SubProcesses... [max 2000]");
            $all_subprocesses = $this->core->request->get_json_decode(
                "{$this->api_base_url}/core/cfo/cfi/CloudFrameWorkDevDocumentationForSubProcesses?_raw&_timezone=UTC",
                ['filter_Process' => $process_id,'cfo_limit' => 2000, '_raw' => 1, '_timezone' => 'UTC'],
                $this->headers
            );
            if ($this->core->request->error) {
                return $this->addError($this->core->request->errorMsg);
            }
            //endregion

        } else {
            //region FETCH all Processes
            $this->sendTerminal(" - Fetching all Processes... [max 2000]");
            $response = $this->core->request->get_json_decode(
                "{$this->api_base_url}/core/cfo/cfi/CloudFrameWorkDevDocumentationForProcesses?_raw&_timezone=UTC",
                ['cfo_limit' => 2000, '_raw' => 1, '_timezone' => 'UTC'],
                $this->headers
            );
            if ($this->core->request->error) {
                return $this->addError($this->core->request->errorMsg);
            }
            $processes = $response['data'] ?? [];
            //endregion
            //region READ subprocesses associated
            $this->sendTerminal(" - Fetching all SubProcesses... [max 2000]");
            $all_subprocesses = $this->core->request->get_json_decode(
                "{$this->api_base_url}/core/cfo/cfi/CloudFrameWorkDevDocumentationForSubProcesses?_raw&_timezone=UTC",
                [ 'cfo_limit' => 2000, '_raw' => 1, '_timezone' => 'UTC'],
                $this->headers
            );
            if ($this->core->request->error) {
                return $this->addError($this->core->request->errorMsg);
            }
            //endregion
        }
        $tot_processes = count($processes);
        $tot_subprocesses = count($all_subprocesses['data']??[]);

        $this->sendTerminal(" - Processes/Subprocesses to backup: {$tot_processes}/{$tot_subprocesses}");
        $all_subprocesses = $this->core->utils->convertArrayIndexedByColumn($all_subprocesses['data'],'Process',true);
        //endregion

        //region PROCESS and SAVE each Process to backup directory
        $saved_count = 0;
        $unchanged_count = 0;

        foreach ($processes as $process) {
            //region VALIDATE Process has KeyName
            if (!($process['KeyName'] ?? null)) {
                $this->sendTerminal("   # Skipping Process without KeyName");
                continue;
            }
            $key_name = $process['KeyName'];
            //endregion

            //region FETCH subprocesses for this Process using KeyId
            $subprocesses_response = [];
            if(isset($all_subprocesses[$key_name])) {
                $subprocesses_response = ['data'=>&$all_subprocesses[$key_name]];
            }

            $subprocesses = [];
            if (!$this->core->request->error && ($subprocesses_response['data'] ?? null)) {
                $subprocesses = $subprocesses_response['data'];
                // Sort subprocesses by Position then KeyName
                foreach ($subprocesses as &$subprocess) {
                    ksort($subprocess);
                }
                usort($subprocesses, function ($a, $b) {
                    return strcmp($a['KeyId'] ?? '', $b['KeyId'] ?? '');
                });
            }
            //endregion

            //region SORT $process keys alphabetically
            ksort($process);
            //endregion

            //region BUILD $process_data structure
            $process_data = [
                'CloudFrameWorkDevDocumentationForProcesses' => $process,
                'CloudFrameWorkDevDocumentationForSubProcesses' => $subprocesses
            ];
            //endregion

            //region SAVE $process_data to JSON file (skip if unchanged)
            $filename = $this->processIdToFilename($key_name);
            $filepath = "{$backup_dir}/{$filename}";
            $json_content = $this->core->jsonEncode($process_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

            // Compare with existing local file
            if (is_file($filepath)) {
                $existing_content = file_get_contents($filepath);
                if ($existing_content === $json_content) {
                    $unchanged_count++;
                    $this->sendTerminal("   = Unchanged: {$filename}");
                    continue;
                }
            }

            if (file_put_contents($filepath, $json_content) === false) {
                return $this->addError("Failed to write Process [{$key_name}] to file");
            }
            $saved_count++;
            $subprocess_count = count($subprocesses);
            $this->sendTerminal("   + Saved: {$filename} ({$subprocess_count} subprocesses)");
            //endregion
        }
        //endregion

        //region SEND summary to terminal
        $this->sendTerminal(str_repeat('-', 50));
        $this->sendTerminal(" - Total Processes/Subprocesses: {$tot_processes}/{$tot_subprocesses} (saved: {$saved_count}, unchanged: {$unchanged_count})");
        //endregion

        return true;
    }

    /**
     * Update existing Process in remote platform from local backup
     */
    public function METHOD_update_from_backup()
    {
        //region VALIDATE $process_id (required parameter)
        $process_id = $this->formParams['id'] ?? null;
        if (!$process_id) {
            return $this->addError("Missing required parameter: id. Usage: _cloudia/processes/update-from-backup?id=PROCESS_ID");
        }
        $this->sendTerminal(" - Process to update: {$process_id}");
        //endregion

        //region SET $backup_file (path to local backup JSON file)
        $filename = $this->processIdToFilename($process_id);
        $backup_file = $this->core->system->root_path . "/buckets/backups/Processes/{$this->platform_id}/{$filename}";
        $this->sendTerminal(" - Backup file: /buckets/backups/Processes/{$this->platform_id}/{$filename}");
        //endregion

        //region VALIDATE $backup_file exists
        if (!is_file($backup_file)) {
            return $this->addError("Backup file not found: {$backup_file}");
        }
        //endregion

        //region READ $process_data from backup file
        $json_content = file_get_contents($backup_file);
        if ($json_content === false) {
            return $this->addError("Failed to read backup file: {$backup_file}");
        }
        $process_data = $this->core->jsonDecode($json_content);
        if ($process_data === null) {
            return $this->addError("Invalid JSON in backup file: {$backup_file}");
        }
        $this->sendTerminal(" - Process data loaded successfully");
        //endregion

        //region VALIDATE $process_data has correct structure
        $process = $process_data['CloudFrameWorkDevDocumentationForProcesses'] ?? null;
        if (!$process || ($process['KeyName'] ?? null) !== $process_id) {
            return $this->addError("KeyName mismatch: file contains '{$process['KeyName']}' but expected '{$process_id}'");
        }
        //endregion

        //region FETCH remote Process and COMPARE with local backup
        $this->sendTerminal(" - Fetching remote Process for comparison...");
        $remote_response = $this->core->request->get_json_decode(
            "{$this->api_base_url}/core/cfo/cfi/CloudFrameWorkDevDocumentationForProcesses/display/" . urlencode($process_id) . '?_raw&_timezone=UTC',
            ['_raw' => 1, '_timezone' => 'UTC'],
            $this->headers
        );

        if (!$this->core->request->error && ($remote_response['data'] ?? null)) {
            $remote_process = $remote_response['data'];
            ksort($remote_process);
            $local_process = $process;
            ksort($local_process);

            if ($this->core->jsonEncode($local_process) === $this->core->jsonEncode($remote_process)) {
                $this->sendTerminal(" = [CloudFrameWorkDevDocumentationForProcesses] is unchanged (local backup equals remote)");
                return true;
            }
        }
        //endregion

        //region UPDATE Process in remote platform via API
        $this->sendTerminal(" - Updating Process in remote platform...");
        $response = $this->core->request->put_json_decode(
            "{$this->api_base_url}/core/cfo/cfi/CloudFrameWorkDevDocumentationForProcesses/" . urlencode($process_id) . "?_raw&_timezone=UTC",
            $process,
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
        $this->sendTerminal(" + Process record updated");
        //endregion

        //region UPDATE subprocesses in remote platform
        $subprocesses = $process_data['CloudFrameWorkDevDocumentationForSubProcesses'] ?? [];
        if ($subprocesses) {
            $this->sendTerminal(" - Updating {" . count($subprocesses) . "} subprocesses...");
            foreach ($subprocesses as $subprocess) {
                $subprocess_key = $subprocess['KeyId'] ?? $subprocess['KeyName'] ?? null;
                if (!$subprocess_key)  {
                    $response = $this->core->request->post_json_decode(
                        "{$this->api_base_url}/core/cfo/cfi/CloudFrameWorkDevDocumentationForSubProcesses?_raw&_timezone=UTC",
                        $subprocess,
                        $this->headers
                    );
                } else {
                    $response = $this->core->request->put_json_decode(
                        "{$this->api_base_url}/core/cfo/cfi/CloudFrameWorkDevDocumentationForSubProcesses/{$subprocess_key}?_raw&_timezone=UTC",
                        $subprocess,
                        $this->headers
                    );
                }

                if ($this->core->request->error || !($response['success'] ?? false)) {
                    $this->sendTerminal("   # Warning: Failed to update subprocess [{$subprocess_key}]");
                    $this->sendTerminal($this->core->request->errorMsg);
                    return false;
                }
            }
            $this->sendTerminal(" + Subprocesses updated");
        }
        //endregion

        //region SEND success message to terminal
        $this->sendTerminal(str_repeat('-', 50));
        $this->sendTerminal(" + Process [{$process_id}] updated successfully in remote platform");
        //endregion

        //region GET Last version of the Process
        $this->formParams['id'] = $process_id;
        $this->METHOD_backup_from_remote();
        //endregion

        return true;
    }

    /**
     * Insert new Process in remote platform from local backup
     */
    public function METHOD_insert_from_backup()
    {
        //region VALIDATE $process_id (required parameter)
        $process_id = $this->formParams['id'] ?? null;
        if (!$process_id) {
            return $this->addError("Missing required parameter: id. Usage: _cloudia/processes/insert-from-backup?id=PROCESS_ID");
        }
        $this->sendTerminal(" - Process to insert: {$process_id}");
        //endregion

        //region SET $backup_file (path to local backup JSON file)
        $filename = $this->processIdToFilename($process_id);
        $backup_file = $this->core->system->root_path . "/buckets/backups/Processes/{$this->platform_id}/{$filename}";
        $this->sendTerminal(" - Backup file: /buckets/backups/Processes/{$this->platform_id}/{$filename}");
        //endregion

        //region VALIDATE $backup_file exists
        if (!is_file($backup_file)) {
            return $this->addError("Backup file not found: {$backup_file}");
        }
        //endregion

        //region READ $process_data from backup file
        $json_content = file_get_contents($backup_file);
        if ($json_content === false) {
            return $this->addError("Failed to read backup file: {$backup_file}");
        }
        $process_data = $this->core->jsonDecode($json_content);
        if ($process_data === null) {
            return $this->addError("Invalid JSON in backup file: {$backup_file}");
        }
        $this->sendTerminal(" - Process data loaded successfully");
        //endregion

        //region VALIDATE $process_data has correct structure
        $process = $process_data['CloudFrameWorkDevDocumentationForProcesses'] ?? null;
        if (!$process || ($process['KeyName'] ?? null) !== $process_id) {
            return $this->addError("KeyName mismatch: file contains '{$process['KeyName']}' but expected '{$process_id}'");
        }
        //endregion

        //region CHECK if Process already exists in remote platform
        $check_response = $this->core->request->get_json_decode(
            "{$this->api_base_url}/core/cfo/cfi/CloudFrameWorkDevDocumentationForProcesses/" . urlencode($process_id) . '?_raw&_timezone=UTC',
            [],
            $this->headers
        );
        if (!$this->core->request->error && ($check_response['data'] ?? null)) {
            return $this->addError("Process [{$process_id}] already exists in remote platform. Use update-from-backup instead.");
        }
        //endregion

        //region INSERT Process in remote platform via API
        $this->sendTerminal(" - Inserting Process in remote platform...");
        $response = $this->core->request->post_json_decode(
            "{$this->api_base_url}/core/cfo/cfi/CloudFrameWorkDevDocumentationForProcesses?_raw&_timezone=UTC",
            $process,
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

        // Get the new KeyId from the response for subprocess insertion
        $new_key_id = $response['data']['KeyName'] ?? $process['KeyName'] ?? null;
        $this->sendTerminal(" + Process record inserted (KeyName: {$new_key_id})");
        //endregion

        //region INSERT subprocesses in remote platform
        $subprocesses = $process_data['CloudFrameWorkDevDocumentationForSubProcesses'] ?? [];
        if ($subprocesses) {
            $this->sendTerminal(" - Inserting {" . count($subprocesses) . "} subprocesses...");
            foreach ($subprocesses as $subprocess) {
                $subprocess_key = $subprocess['KeyId'] ?? $subprocess['KeyName'] ?? null;
                if (!$subprocess_key)  {
                    $response = $this->core->request->post_json_decode(
                        "{$this->api_base_url}/core/cfo/cfi/CloudFrameWorkDevDocumentationForSubProcesses?_raw&_timezone=UTC",
                        $subprocess,
                        $this->headers
                    );
                } else {
                    $response = $this->core->request->put_json_decode(
                        "{$this->api_base_url}/core/cfo/cfi/CloudFrameWorkDevDocumentationForSubProcesses/{$subprocess_key}?_raw&_timezone=UTC",
                        $subprocess,
                        $this->headers
                    );
                }

                if ($this->core->request->error || !($response['success'] ?? false)) {
                    $subprocess_name = $subprocess['Title'] ?? $subprocess['KeyName'] ?? 'unknown';
                    $this->sendTerminal("   # Warning: Failed to insert subprocess [{$subprocess_name}]");
                    $this->sendTerminal($this->core->request->errorMsg);
                    return false;
                }
            }
            $this->sendTerminal(" + Subprocesses inserted");
        }
        //endregion

        //region SEND success message to terminal
        $this->sendTerminal(str_repeat('-', 50));
        $this->sendTerminal(" + Process [{$process_id}] inserted successfully in remote platform");
        //endregion

        //region GET Last version of the Process
        $this->formParams['id'] = $process_id;
        $this->METHOD_backup_from_remote();
        //endregion

        return true;
    }
}