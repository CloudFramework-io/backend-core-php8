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
        $this->sendTerminal("  /backup-from-remote        - Backup all Processes + checks from remote platform");
        $this->sendTerminal("  /backup-from-remote?id=xx  - Backup specific Process + checks from remote platform");
        $this->sendTerminal("  /insert-from-backup?id=xx  - Insert new Process + checks in remote platform from local backup");
        $this->sendTerminal("  /update-from-backup?id=xx  - Update existing Process + checks in remote platform from local backup");
        $this->sendTerminal("  /update-from-backup?id=xx&delete=yes|no - Confirm or skip deletion of remote checks");
        $this->sendTerminal("  /list-remote               - List all Processes in remote platform");
        $this->sendTerminal("  /list-local                - List all Processes in local backup");
        $this->sendTerminal("");
        $this->sendTerminal("Backup file structure:");
        $this->sendTerminal("  CloudFrameWorkDevDocumentationForProcesses    - Process record");
        $this->sendTerminal("  CloudFrameWorkDevDocumentationForSubProcesses - SubProcess records");
        $this->sendTerminal("  CloudFrameWorkDevDocumentationForProcessTests - Checks (Process + SubProcesses)");
        $this->sendTerminal("");
        $this->sendTerminal("Examples:");
        $this->sendTerminal("  composer script -- \"_cloudia/processes/backup-from-remote?id=HIPOTECH-001\"");
        $this->sendTerminal("  composer script -- \"_cloudia/processes/update-from-backup?id=HIPOTECH-001\"");
        $this->sendTerminal("  composer script -- \"_cloudia/processes/update-from-backup?id=HIPOTECH-001&delete=yes\"");
        $this->sendTerminal("  composer script -- _cloudia/processes/list-remote");
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
     * Fetch checks (CloudFrameWorkDevDocumentationForProcessTests) for a given entity
     *
     * @param string $cfo_entity CFO entity name (e.g., CloudFrameWorkDevDocumentationForProcesses)
     * @param string $cfo_id Entity KeyId
     * @return array Array of checks or empty array on error
     */
    private function fetchChecksForEntity($cfo_entity, $cfo_id)
    {
        $response = $this->core->request->get_json_decode(
            "{$this->api_base_url}/core/cfo/cfi/CloudFrameWorkDevDocumentationForProcessTests?_raw&_timezone=UTC",
            [
                'filter_CFOEntity' => $cfo_entity,
                'filter_CFOId' => $cfo_id,
                '_order' => 'Route',
                'cfo_limit' => 500,
                '_raw' => 1,
                '_timezone' => 'UTC'
            ],
            $this->headers
        );

        if ($this->core->request->error || !($response['data'] ?? null)) {
            return [];
        }

        $checks = $response['data'];
        // Sort checks by KeyId and keys
        foreach ($checks as &$check) {
            ksort($check);
        }
        usort($checks, function ($a, $b) {
            return strcmp($a['KeyId'] ?? '', $b['KeyId'] ?? '');
        });

        return $checks;
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

            //region VERIFY all subprocesses have the correct Process
            $subprocesses_data = $all_subprocesses['data'] ?? [];
            if (count($subprocesses_data) > 0) {
                $invalid_subprocesses = array_filter($subprocesses_data, function($s) use ($process_id) {
                    return ($s['Process'] ?? '') !== $process_id;
                });
                if ($invalid_subprocesses) {
                    $invalid_keys = array_map(function($s) { return $s['KeyId'] ?? $s['KeyName'] ?? 'unknown'; }, $invalid_subprocesses);
                    return $this->addError("Found " . count($invalid_subprocesses) . " subprocesses with incorrect Process (expected '{$process_id}'): " . implode(', ', $invalid_keys));
                }
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

            //region FETCH checks for Process and SubProcesses
            $all_checks = [];

            // Fetch checks for the Process itself
            $process_key_id = $process['KeyId'] ?? null;
            if ($process_key_id) {
                $process_checks = $this->fetchChecksForEntity('CloudFrameWorkDevDocumentationForProcesses', $process_key_id);
                $all_checks = array_merge($all_checks, $process_checks);
            }

            // Fetch checks for each SubProcess
            foreach ($subprocesses as $subprocess) {
                $subprocess_key_id = $subprocess['KeyId'] ?? null;
                if ($subprocess_key_id) {
                    $subprocess_checks = $this->fetchChecksForEntity('CloudFrameWorkDevDocumentationForSubProcesses', $subprocess_key_id);
                    $all_checks = array_merge($all_checks, $subprocess_checks);
                }
            }

            // Sort all checks by KeyId
            usort($all_checks, function ($a, $b) {
                return strcmp($a['KeyId'] ?? '', $b['KeyId'] ?? '');
            });
            //endregion

            //region BUILD $process_data structure
            $process_data = [
                'CloudFrameWorkDevDocumentationForProcesses' => $process,
                'CloudFrameWorkDevDocumentationForSubProcesses' => $subprocesses,
                'CloudFrameWorkDevDocumentationForProcessTests' => $all_checks
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
            $checks_count = count($all_checks);
            $this->sendTerminal("   + Saved: {$filename} ({$subprocess_count} subprocesses, {$checks_count} checks)");
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
        $process_unchanged = false;
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
                $process_unchanged = true;
            }
        }
        //endregion

        //region UPDATE Process in remote platform via API (only if changed)
        if (!$process_unchanged) {
            $this->sendTerminal(" - Updating Process in remote platform...");
            $response = $this->core->request->put_json_decode(
                "{$this->api_base_url}/core/cfo/cfi/CloudFrameWorkDevDocumentationForProcesses/" . urlencode($process_id) . "?_raw&_timezone=UTC",
                $process,
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
            $this->sendTerminal(" + Process record updated");
        }
        //endregion

        //region FETCH remote subprocesses for comparison
        $this->sendTerminal(" - Fetching remote SubProcesses for comparison...");
        $remote_subprocesses_response = $this->core->request->get_json_decode(
            "{$this->api_base_url}/core/cfo/cfi/CloudFrameWorkDevDocumentationForSubProcesses?_raw&_timezone=UTC",
            ['filter_Process' => $process_id, 'cfo_limit' => 2000, '_raw' => 1, '_timezone' => 'UTC'],
            $this->headers
        );
        $remote_subprocesses = [];
        if (!$this->core->request->error && ($remote_subprocesses_response['data'] ?? null)) {
            // Index remote subprocesses by KeyId for easy comparison
            foreach ($remote_subprocesses_response['data'] as $remote_sub) {
                if ($key = ($remote_sub['KeyId'] ?? null)) {
                    ksort($remote_sub);
                    $remote_subprocesses[$key] = $remote_sub;
                }
            }
        }
        //endregion

        //region UPDATE/INSERT subprocesses in remote platform
        $subprocesses = $process_data['CloudFrameWorkDevDocumentationForSubProcesses'] ?? [];
        $subprocess_updated = 0;
        $subprocess_inserted = 0;
        $subprocess_unchanged = 0;

        if ($subprocesses) {
            $this->sendTerminal(" - Processing {" . count($subprocesses) . "} subprocesses...");
            foreach ($subprocesses as $subprocess) {
                $subprocess_key = $subprocess['KeyId'] ?? null;
                $subprocess_title = $subprocess['Title'] ?? $subprocess_key ?? 'unknown';

                if (!$subprocess_key) {
                    // INSERT new subprocess (no KeyId)
                    $response = $this->core->request->post_json_decode(
                        "{$this->api_base_url}/core/cfo/cfi/CloudFrameWorkDevDocumentationForSubProcesses?_raw&_timezone=UTC",
                        $subprocess,
                        $this->headers
                    );

                    if ($this->core->request->error || !($response['success'] ?? false)) {
                        $this->sendTerminal("   # Warning: Failed to insert subprocess [{$subprocess_title}]");
                        $this->sendTerminal("     " . ($this->core->request->errorMsg ?: json_encode($response['errorMsg'] ?? 'Unknown error')));
                        return false;
                    }
                    $subprocess_inserted++;
                    $this->sendTerminal("   + Inserted: {$subprocess_title}");
                } else {
                    // Check if subprocess exists and compare
                    $local_sub = $subprocess;
                    ksort($local_sub);

                    if (isset($remote_subprocesses[$subprocess_key])) {
                        // Compare with remote
                        if ($this->core->jsonEncode($local_sub) === $this->core->jsonEncode($remote_subprocesses[$subprocess_key])) {
                            $subprocess_unchanged++;
                            continue; // Skip unchanged subprocess
                        }
                    }

                    // UPDATE subprocess
                    $response = $this->core->request->put_json_decode(
                        "{$this->api_base_url}/core/cfo/cfi/CloudFrameWorkDevDocumentationForSubProcesses/{$subprocess_key}?_raw&_timezone=UTC",
                        $subprocess,
                        $this->headers,
                        true
                    );

                    if ($this->core->request->error || !($response['success'] ?? false)) {
                        $this->sendTerminal("   # Warning: Failed to update subprocess [{$subprocess_title}]");
                        $this->sendTerminal("     " . ($this->core->request->errorMsg ?: json_encode($response['errorMsg'] ?? 'Unknown error')));
                        return false;
                    }
                    $subprocess_updated++;
                    $this->sendTerminal("   + Updated: {$subprocess_title}");
                }
            }
            $this->sendTerminal(" - SubProcesses: {$subprocess_updated} updated, {$subprocess_inserted} inserted, {$subprocess_unchanged} unchanged");
        }
        //endregion

        //region SYNC checks (CloudFrameWorkDevDocumentationForProcessTests)
        $local_checks = $process_data['CloudFrameWorkDevDocumentationForProcessTests'] ?? [];
        $this->sendTerminal(" - Local checks in backup: " . count($local_checks));

        // Build list of valid CFOEntity/CFOId pairs from current process and subprocesses
        $valid_cfo_pairs = [];

        // Process itself
        $process_key_id = $process['KeyId'] ?? null;
        if ($process_key_id) {
            $valid_cfo_pairs[] = ['entity' => 'CloudFrameWorkDevDocumentationForProcesses', 'id' => $process_key_id];
        }

        // SubProcesses (use remote subprocesses since they have the correct KeyIds after insert)
        foreach ($subprocesses as $subprocess) {
            $subprocess_key_id = $subprocess['KeyId'] ?? null;
            if ($subprocess_key_id) {
                $valid_cfo_pairs[] = ['entity' => 'CloudFrameWorkDevDocumentationForSubProcesses', 'id' => $subprocess_key_id];
            }
        }

        // Fetch remote checks for all valid CFO pairs
        $this->sendTerminal(" - Fetching remote checks for comparison...");
        $remote_checks = [];
        foreach ($valid_cfo_pairs as $pair) {
            $entity_checks = $this->fetchChecksForEntity($pair['entity'], $pair['id']);
            foreach ($entity_checks as $check) {
                $remote_checks[$check['KeyId']] = $check;
            }
        }
        $this->sendTerminal(" - Remote checks: " . count($remote_checks));

        // Index local checks by KeyId (only those that have KeyId)
        $local_indexed = [];
        $local_new_checks = []; // Checks without KeyId (to be inserted)
        foreach ($local_checks as $check) {
            if ($keyId = $check['KeyId'] ?? null) {
                ksort($check);
                $local_indexed[$keyId] = $check;
            } else {
                $local_new_checks[] = $check;
            }
        }

        // Identify checks to delete (exist in remote but not in local)
        $checks_to_delete = [];
        foreach ($remote_checks as $keyId => $remote_check) {
            if (!isset($local_indexed[$keyId])) {
                $checks_to_delete[$keyId] = $remote_check;
            }
        }

        // Check if delete confirmation is needed
        $allow_delete = false;
        if (!empty($checks_to_delete)) {
            $delete_param = $this->formParams['delete'] ?? null;

            if ($delete_param === null) {
                // Show checks that will be deleted and require confirmation
                $this->sendTerminal("");
                $this->sendTerminal(" !! WARNING: The following checks exist in REMOTE but NOT in LOCAL:");
                $this->sendTerminal(str_repeat('-', 100));
                foreach ($checks_to_delete as $keyId => $check) {
                    $cfo_entity = $check['CFOEntity'] ?? 'unknown';
                    $this->sendTerminal("    - [{$keyId}] {$check['Title']} ({$cfo_entity})");
                }
                $this->sendTerminal(str_repeat('-', 100));
                $this->sendTerminal("");
                $this->sendTerminal(" These checks will be DELETED from remote if you proceed.");
                $this->sendTerminal(" To confirm deletion, re-run the command with: delete=yes");
                $this->sendTerminal(" To skip deletion (only update/insert), re-run with: delete=no");
                $this->sendTerminal("");
                $this->sendTerminal(" Example:");
                $this->sendTerminal("   composer script -- \"_cloudia/processes/update-from-backup?id={$process_id}&delete=yes\"");
                $this->sendTerminal("   composer script -- \"_cloudia/processes/update-from-backup?id={$process_id}&delete=no\"");
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
        }

        // Sync checks
        $this->sendTerminal(" - Syncing checks...");
        $checks_updated = 0;
        $checks_inserted = 0;
        $checks_deleted = 0;
        $checks_unchanged = 0;
        $checks_skipped = 0;

        // Process remote checks: update, delete, or skip
        foreach ($remote_checks as $keyId => $remote_check) {
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

        $this->sendTerminal(" - Checks: " . implode(', ', $summary_parts));
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
                        $this->headers,
                        true
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

        //region INSERT checks in remote platform
        $checks = $process_data['CloudFrameWorkDevDocumentationForProcessTests'] ?? [];
        if ($checks) {
            $this->sendTerminal(" - Inserting " . count($checks) . " checks...");
            $checks_inserted = 0;
            $checks_failed = 0;

            foreach ($checks as $check) {
                // Remove KeyId to ensure a new record is created
                unset($check['KeyId']);

                $response = $this->core->request->post_json_decode(
                    "{$this->api_base_url}/core/cfo/cfi/CloudFrameWorkDevDocumentationForProcessTests?_raw&_timezone=UTC",
                    $check,
                    $this->headers
                );

                if ($this->core->request->error || !($response['success'] ?? false)) {
                    $check_name = $check['Title'] ?? $check['Route'] ?? 'unknown';
                    $this->sendTerminal("   # Warning: Failed to insert check [{$check_name}]");
                    $checks_failed++;
                } else {
                    $checks_inserted++;
                }
            }

            if ($checks_inserted > 0) {
                $this->sendTerminal(" + Checks inserted: {$checks_inserted}");
            }
            if ($checks_failed > 0) {
                $this->sendTerminal(" # Checks failed: {$checks_failed}");
            }
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