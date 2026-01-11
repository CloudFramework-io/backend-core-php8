<?php
/**
 * Check Documentation Backup Script
 *
 * This script provides functionality to manage Check documentation (ProcessTests) by:
 * - Backing up Checks from the remote platform to local storage
 * - Inserting new Checks from local backup to the remote platform
 * - Updating existing Checks from local backup to the remote platform
 * - Listing Checks in remote and local storage
 *
 * Checks are linked to other documentation objects through:
 * - CFOEntity: The CFO KeyName to which the check is linked
 * - CFOId: The KeyName or KeyId of the specific record
 *
 * The script operates on Check documentation stored in the `buckets/backups/Checks/{platform}/` directory
 * and synchronizes them with the CloudFramework platform via REST API.
 *
 * Usage:
 *   _cloudia/checks/backup-from-remote                      - Backup all Checks from remote
 *   _cloudia/checks/backup-from-remote?entity=X&id=Y        - Backup specific Checks
 *   _cloudia/checks/insert-from-backup?entity=X&id=Y        - Insert new Checks to remote
 *   _cloudia/checks/update-from-backup?entity=X&id=Y        - Update existing Checks in remote
 *   _cloudia/checks/list-remote                             - List all Checks in remote
 *   _cloudia/checks/list-local                              - List all Checks in local backup
 *
 * @author CloudFramework Development Team
 * @version 1.0
 */
class Script extends CoreScripts
{
    /** @var string Platform ID for Check operations */
    var $platform_id = '';

    /** @var array HTTP headers for API authentication */
    var $headers = [];

    /** @var string Base API URL for remote platform */
    var $api_base_url = 'https://api.cloudframework.dev';

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
            'X-WEB-KEY' => '/scripts/_cloudia/checks',
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

        return true;
    }

    /**
     * Display available commands
     */
    public function METHOD_default()
    {
        $this->sendTerminal("Available commands:");
        $this->sendTerminal("  /backup-from-remote                      - Backup all Checks from remote platform");
        $this->sendTerminal("  /backup-from-remote?entity=X&id=Y        - Backup specific Checks from remote platform");
        $this->sendTerminal("  /insert-from-backup?entity=X&id=Y        - Insert new Checks in remote platform from local backup");
        $this->sendTerminal("  /update-from-backup?entity=X&id=Y        - Update existing Checks in remote platform from local backup");
        $this->sendTerminal("  /list-remote                             - List all Checks in remote platform");
        $this->sendTerminal("  /list-local                              - List all Checks in local backup");
        $this->sendTerminal("");
        $this->sendTerminal("Examples:");
        $this->sendTerminal("  composer run-script script \"_cloudia/checks/backup-from-remote?entity=CloudFrameWorkDevDocumentationForProcesses&id=PROC-001\"");
        $this->sendTerminal("  composer run-script script _cloudia/checks/list-remote");
        $this->sendTerminal("");
        $this->sendTerminal("Parameters:");
        $this->sendTerminal("  entity: The CFO KeyName to which the checks are linked (e.g., CloudFrameWorkDevDocumentationForProcesses)");
        $this->sendTerminal("  id: The KeyName or KeyId of the specific record in the CFO");
    }

    /**
     * Convert CFOEntity and CFOId to backup filename
     *
     * @param string $cfo_entity CFO Entity name
     * @param string $cfo_id CFO Id value
     * @return string Filename
     */
    private function groupKeyToFilename($cfo_entity, $cfo_id)
    {
        $entityPart = preg_replace('/[^A-Za-z0-9_-]/', '_', $cfo_entity);
        $idPart = preg_replace('/[^A-Za-z0-9_-]/', '_', $cfo_id);
        return $entityPart . '__' . $idPart . '.json';
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
        if (($backup_dir .= '/Checks') && !is_dir($backup_dir) && !mkdir($backup_dir)) {
            $this->addError("Backup directory [/buckets/backups/Checks] can not be created");
            return false;
        }
        if (($backup_dir .= '/' . $this->platform_id) && !is_dir($backup_dir) && !mkdir($backup_dir)) {
            $this->addError("Backup directory [/buckets/backups/Checks/{$this->platform_id}] can not be created");
            return false;
        }
        return $backup_dir;
    }

    /**
     * List all Checks in remote platform (grouped by CFOEntity and CFOId)
     */
    public function METHOD_list_remote()
    {
        //region FETCH all Checks from remote platform
        $this->sendTerminal("Listing Checks in remote platform [{$this->platform_id}]:");
        $this->sendTerminal(str_repeat('-', 80));

        $params = ['_fields' => 'KeyId,CFOEntity,CFOId,Route,Title,Status', '_order' => 'CFOEntity,CFOId', '_limit' => 1000];
        $response = $this->core->request->get_json_decode(
            "{$this->api_base_url}/core/cfo/cfi/CloudFrameWorkDevDocumentationForProcessTests?_raw=1&_timezone=UTC",
            $params,
            $this->headers
        );

        if ($this->core->request->error) {
            return $this->addError($this->core->request->errorMsg);
        }

        $checks = $response['data'] ?? [];
        if (!$checks) {
            $this->sendTerminal("No Checks found in remote platform");
            return true;
        }

        //region GROUP checks by CFOEntity and CFOId
        $grouped = [];
        foreach ($checks as $check) {
            $entity = $check['CFOEntity'] ?? '_unlinked';
            $id = $check['CFOId'] ?? '_unlinked';
            $key = "{$entity}/{$id}";
            if (!isset($grouped[$key])) {
                $grouped[$key] = ['entity' => $entity, 'id' => $id, 'count' => 0, 'checks' => []];
            }
            $grouped[$key]['count']++;
            $grouped[$key]['checks'][] = $check;
        }
        ksort($grouped);
        //endregion

        //region DISPLAY grouped checks
        foreach ($grouped as $key => $group) {
            $this->sendTerminal(" [{$group['entity']}] {$group['id']} ({$group['count']} checks)");
            foreach ($group['checks'] as $check) {
                $status = $check['Status'] ?? 'N/A';
                $title = $check['Title'] ?? 'N/A';
                $route = $check['Route'] ?? '';
                $this->sendTerminal("   - {$check['KeyId']}: [{$route}] {$title} [{$status}]");
            }
        }
        //endregion

        $this->sendTerminal(str_repeat('-', 80));
        $this->sendTerminal("Total: " . count($checks) . " Checks in " . count($grouped) . " groups");
        //endregion

        return true;
    }

    /**
     * List all Checks in local backup
     */
    public function METHOD_list_local()
    {
        //region LIST all Checks in local backup
        $this->sendTerminal("Listing Checks in local backup [{$this->platform_id}]:");
        $this->sendTerminal(str_repeat('-', 80));

        $directory_path = $this->core->system->root_path . '/buckets/backups/Checks/' . $this->platform_id;

        if (!is_dir($directory_path)) {
            $this->sendTerminal("Backup directory not found: /buckets/backups/Checks/{$this->platform_id}");
            return true;
        }

        $files = glob($directory_path . '/*.json');
        if (!$files) {
            $this->sendTerminal("No Check backup files found");
            return true;
        }

        $totalChecks = 0;
        foreach ($files as $file) {
            $data = $this->core->jsonDecode(file_get_contents($file));
            $entity = $data['CFOEntity'] ?? '_unlinked';
            $id = $data['CFOId'] ?? '_unlinked';
            $checks = $data['CloudFrameWorkDevDocumentationForProcessTests'] ?? [];
            $count = count($checks);
            $totalChecks += $count;
            $this->sendTerminal(" [{$entity}] {$id} ({$count} checks)");
            foreach ($checks as $check) {
                $status = $check['Status'] ?? 'N/A';
                $title = $check['Title'] ?? 'N/A';
                $route = $check['Route'] ?? '';
                $this->sendTerminal("   - {$check['KeyId']}: [{$route}] {$title} [{$status}]");
            }
        }
        $this->sendTerminal(str_repeat('-', 80));
        $this->sendTerminal("Total: {$totalChecks} Checks in " . count($files) . " files");
        //endregion

        return true;
    }

    /**
     * Backup Checks from remote platform to local storage
     */
    public function METHOD_backup_from_remote()
    {
        //region VERIFY backup directory
        if (!$backup_dir = $this->getBackupDir()) {
            return false;
        }
        $this->sendTerminal(" - Backup directory: /buckets/backups/Checks/{$this->platform_id}");
        //endregion

        //region SET $cfo_entity and $cfo_id (specific Check group to backup, or null for all)
        $cfo_entity = $this->formParams['entity'] ?? null;
        $cfo_id = $this->formParams['id'] ?? null;
        //endregion

        //region READ Checks from remote API
        if ($cfo_entity && $cfo_id) {
            //region FETCH Checks for specific CFOEntity and CFOId
            $this->sendTerminal(" - Fetching Checks for: {$cfo_entity}/{$cfo_id}");
            $response = $this->core->request->get_json_decode(
                "{$this->api_base_url}/core/cfo/cfi/CloudFrameWorkDevDocumentationForProcessTests?_raw&_timezone=UTC",
                ['filter_CFOEntity' => $cfo_entity, 'filter_CFOId' => $cfo_id, '_limit' => 500, '_raw' => 1, '_timezone' => 'UTC'],
                $this->headers
            );
            if ($this->core->request->error) {
                return $this->addError($this->core->request->errorMsg);
            }

            $checks = $response['data'] ?? [];
            if (!$checks) {
                $this->sendTerminal(" # No Checks found for [{$cfo_entity}/{$cfo_id}]");
                return true;
            }

            // Sort checks by KeyId
            foreach ($checks as &$check) {
                ksort($check);
            }
            usort($checks, function ($a, $b) {
                return strcmp($a['KeyId'] ?? '', $b['KeyId'] ?? '');
            });

            // Build data structure
            $checksData = [
                'CFOEntity' => $cfo_entity,
                'CFOId' => $cfo_id,
                'CloudFrameWorkDevDocumentationForProcessTests' => $checks
            ];

            // Save to file
            $filename = $this->groupKeyToFilename($cfo_entity, $cfo_id);
            $filepath = "{$backup_dir}/{$filename}";
            $json_content = $this->core->jsonEncode($checksData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            if (file_put_contents($filepath, $json_content) === false) {
                return $this->addError("Failed to write Checks to file");
            }
            $this->sendTerminal("   + Saved: {$filename} (" . count($checks) . " checks)");
            //endregion
        } else {
            //region FETCH all Checks and group them
            $this->sendTerminal(" - Fetching all Checks...");
            $response = $this->core->request->get_json_decode(
                "{$this->api_base_url}/core/cfo/cfi/CloudFrameWorkDevDocumentationForProcessTests?_raw&_timezone=UTC",
                ['_limit' => 1000, '_raw' => 1, '_timezone' => 'UTC'],
                $this->headers
            );
            if ($this->core->request->error) {
                return $this->addError($this->core->request->errorMsg);
            }
            $checks = $response['data'] ?? [];

            if (!$checks) {
                $this->sendTerminal(" # No Checks found in remote platform");
                return true;
            }

            $this->sendTerminal(" - Checks found: " . count($checks));

            // Group checks by CFOEntity and CFOId
            $grouped = [];
            foreach ($checks as $check) {
                $entity = $check['CFOEntity'] ?? '_unlinked';
                $id = $check['CFOId'] ?? '_unlinked';
                $key = "{$entity}__{$id}";
                if (!isset($grouped[$key])) {
                    $grouped[$key] = [
                        'CFOEntity' => $entity,
                        'CFOId' => $id,
                        'CloudFrameWorkDevDocumentationForProcessTests' => []
                    ];
                }
                ksort($check);
                $grouped[$key]['CloudFrameWorkDevDocumentationForProcessTests'][] = $check;
            }

            // Save each group to a file
            $saved_count = 0;
            foreach ($grouped as $key => $checksData) {
                // Sort checks within group
                usort($checksData['CloudFrameWorkDevDocumentationForProcessTests'], function ($a, $b) {
                    return strcmp($a['KeyId'] ?? '', $b['KeyId'] ?? '');
                });

                $filename = $this->groupKeyToFilename($checksData['CFOEntity'], $checksData['CFOId']);
                $filepath = "{$backup_dir}/{$filename}";
                $json_content = $this->core->jsonEncode($checksData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
                if (file_put_contents($filepath, $json_content) === false) {
                    $this->sendTerminal("   # Failed to write: {$filename}");
                    continue;
                }
                $saved_count++;
                $check_count = count($checksData['CloudFrameWorkDevDocumentationForProcessTests']);
                $this->sendTerminal("   + Saved: {$filename} ({$check_count} checks)");
            }
            //endregion
        }
        //endregion

        //region SEND summary to terminal
        $this->sendTerminal(str_repeat('-', 50));
        $this->sendTerminal(" - Backup complete");
        //endregion

        return true;
    }

    /**
     * Update existing Checks in remote platform from local backup
     */
    public function METHOD_update_from_backup()
    {

        //region VALIDATE $cfo_entity and $cfo_id (required parameters)
        $cfo_entity = $this->formParams['entity'] ?? null;
        $cfo_id = $this->formParams['id'] ?? null;
        if (!$cfo_entity || !$cfo_id) {
            return $this->addError("Missing required parameters: entity and id. Usage: _cloudia/checks/update-from-backup?entity=X&id=Y");
        }
        $this->sendTerminal(" - Checks to update: {$cfo_entity}/{$cfo_id}");
        //endregion

        //region SET $backup_file (path to local backup JSON file)
        $filename = $this->groupKeyToFilename($cfo_entity, $cfo_id);
        $backup_file = $this->core->system->root_path . "/buckets/backups/Checks/{$this->platform_id}/{$filename}";
        $this->sendTerminal(" - Backup file: /buckets/backups/Checks/{$this->platform_id}/{$filename}");
        //endregion

        //region VALIDATE $backup_file exists
        if (!is_file($backup_file)) {
            return $this->addError("Backup file not found: {$backup_file}");
        }
        //endregion

        //region READ $checks_data from backup file
        $json_content = file_get_contents($backup_file);
        if ($json_content === false) {
            return $this->addError("Failed to read backup file: {$backup_file}");
        }
        $checks_data = $this->core->jsonDecode($json_content);
        if ($checks_data === null) {
            return $this->addError("Invalid JSON in backup file: {$backup_file}");
        }
        $this->sendTerminal(" - Checks data loaded successfully");
        //endregion

        //region VALIDATE $checks_data has correct structure
        if (($checks_data['CFOEntity'] ?? null) !== $cfo_entity || ($checks_data['CFOId'] ?? null) !== $cfo_id) {
            return $this->addError("CFOEntity/CFOId mismatch in backup file");
        }
        $local_checks = $checks_data['CloudFrameWorkDevDocumentationForProcessTests'] ?? [];
        $this->sendTerminal(" - Local checks: " . count($local_checks));
        //endregion

        //region FETCH remote Checks for comparison
        $response = $this->core->request->get_json_decode(
            "{$this->api_base_url}/core/cfo/cfi/CloudFrameWorkDevDocumentationForProcessTests?_raw&_timezone=UTC",
            ['filter_CFOEntity' => $cfo_entity, 'filter_CFOId' => $cfo_id, 'cfo_limit' => 500, '_raw' => 1, '_timezone' => 'UTC'],
            $this->headers
        );

        $remote_checks = [];
        if (!$this->core->request->error && ($response['data'] ?? null)) {
            $remote_checks = $response['data'];
        }
        $this->sendTerminal(" - Remote checks: " . count($remote_checks));
        //endregion

        //region BUILD indexed arrays for comparison
        $local_indexed = [];
        foreach ($local_checks as $check) {
            if(!$index = $check['KeyId']??($check['Route']??null)) {
                _printe('ERROR in local checks: missing KeyId or Route in check: '.$this->core->jsonEncode($check));
            }
            $local_indexed[$index] = $check;
        }


        $remote_indexed = [];
        foreach ($remote_checks as $check) {
            $remote_indexed[$check['KeyId']] = $check;
        }
        //endregion

        //region UPDATE/INSERT/DELETE Checks
        $this->sendTerminal(" - Syncing checks...");

        // Update or delete remote checks
        foreach ($remote_indexed as $keyId => $remote_check) {
            if (!isset($local_indexed[$keyId])) {
                // Remote check not in local - delete it
                $this->sendTerminal("   - Deleting [{$remote_check['CFOEntity']}/{$remote_check['CFOId']}][{$keyId}]: {$remote_check['Title']}");
                $response = $this->core->request->delete_json_decode(
                    "{$this->api_base_url}/core/cfo/cfi/CloudFrameWorkDevDocumentationForProcessTests/{$keyId}?_raw&_timezone=UTC",
                    $this->headers
                );
                if ($this->core->request->error || !($response['success'] ?? false)) {
                    _printe($this->core->request->errorMsg);
                    $this->sendTerminal("     # Warning: Failed to delete");
                }
            } else {
                // Check exists in both - compare and update if different
                ksort($remote_check);
                ksort($local_indexed[$keyId]);
                if ($this->core->jsonEncode($local_indexed[$keyId]) !== $this->core->jsonEncode($remote_check)) {
                    $this->sendTerminal("   - Updating  [{$local_indexed[$keyId]['CFOEntity']}/{$local_indexed[$keyId]['CFOId']}][{$keyId}: {$local_indexed[$keyId]['Route']}]: {$local_indexed[$keyId]['Title']}");
                    $response = $this->core->request->put_json_decode(
                        "{$this->api_base_url}/core/cfo/cfi/CloudFrameWorkDevDocumentationForProcessTests/{$keyId}?_raw&_timezone=UTC",
                        $local_indexed[$keyId],
                        $this->headers
                    );
                    if ($this->core->request->error || !($response['success'] ?? false)) {
                        $this->sendTerminal("     # Warning: Failed to update");
                    }
                } else {
                    $this->sendTerminal("   - Are the same [{$remote_check['CFOEntity']}/{$remote_check['CFOId']}][{$keyId}: {$remote_check['Route']}]");
                }
                unset($local_indexed[$keyId]);
            }
        }

        // Insert checks that exist only in local
        foreach ($local_indexed as $index => $local_check) {
            if($local_check['KeyId']??null) {
                $this->sendTerminal("   - Updating [{$local_check['CFOEntity']}/{$local_check['CFOId']}][{$index}: {$local_check['Route']}]: {$local_check['Title']}");
                $response = $this->core->request->post_json_decode(
                    "{$this->api_base_url}/core/cfo/cfi/CloudFrameWorkDevDocumentationForProcessTests?_raw&_timezone=UTC",
                    $local_check,
                    $this->headers
                );
            } else {
                $this->sendTerminal("   - Inserting [{$local_check['CFOEntity']}/{$local_check['CFOId']}][{$index}: {$local_check['Route']}]: {$local_check['Title']}");
                $response = $this->core->request->post_json_decode(
                    "{$this->api_base_url}/core/cfo/cfi/CloudFrameWorkDevDocumentationForProcessTests?_raw&_timezone=UTC",
                    $local_check,
                    $this->headers
                );
            }

            if ($this->core->request->error || !($response['success'] ?? false)) {
                $this->sendTerminal("     # Warning: Failed to insert");
            }
        }
        //endregion

        //region SEND success message to terminal
        $this->sendTerminal(str_repeat('-', 50));
        $this->sendTerminal(" + Checks [{$cfo_entity}/{$cfo_id}] updated successfully");
        //endregion

        //region GET Last version of the Checks
        $this->formParams['entity'] = $cfo_entity;
        $this->formParams['id'] = $cfo_id;
        $this->METHOD_backup_from_remote();
        //endregion

        return true;
    }

    /**
     * Insert new Checks in remote platform from local backup
     */
    public function METHOD_insert_from_backup()
    {
        //region VALIDATE $cfo_entity and $cfo_id (required parameters)
        $cfo_entity = $this->formParams['entity'] ?? null;
        $cfo_id = $this->formParams['id'] ?? null;
        if (!$cfo_entity || !$cfo_id) {
            return $this->addError("Missing required parameters: entity and id. Usage: _cloudia/checks/insert-from-backup?entity=X&id=Y");
        }
        $this->sendTerminal(" - Checks to insert: {$cfo_entity}/{$cfo_id}");
        //endregion

        //region SET $backup_file (path to local backup JSON file)
        $filename = $this->groupKeyToFilename($cfo_entity, $cfo_id);
        $backup_file = $this->core->system->root_path . "/buckets/backups/Checks/{$this->platform_id}/{$filename}";
        $this->sendTerminal(" - Backup file: /buckets/backups/Checks/{$this->platform_id}/{$filename}");
        //endregion

        //region VALIDATE $backup_file exists
        if (!is_file($backup_file)) {
            return $this->addError("Backup file not found: {$backup_file}");
        }
        //endregion

        //region READ $checks_data from backup file
        $json_content = file_get_contents($backup_file);
        if ($json_content === false) {
            return $this->addError("Failed to read backup file: {$backup_file}");
        }
        $checks_data = $this->core->jsonDecode($json_content);
        if ($checks_data === null) {
            return $this->addError("Invalid JSON in backup file: {$backup_file}");
        }
        $this->sendTerminal(" - Checks data loaded successfully");
        //endregion

        //region VALIDATE $checks_data has correct structure
        if (($checks_data['CFOEntity'] ?? null) !== $cfo_entity || ($checks_data['CFOId'] ?? null) !== $cfo_id) {
            return $this->addError("CFOEntity/CFOId mismatch in backup file");
        }
        $local_checks = $checks_data['CloudFrameWorkDevDocumentationForProcessTests'] ?? [];
        $this->sendTerminal(" - Checks to insert: " . count($local_checks));
        //endregion

        //region CHECK if Checks already exist in remote platform
        $response = $this->core->request->get_json_decode(
            "{$this->api_base_url}/core/cfo/cfi/CloudFrameWorkDevDocumentationForProcessTests?_raw&_timezone=UTC",
            ['filter_CFOEntity' => $cfo_entity, 'filter_CFOId' => $cfo_id, '_limit' => 1, '_raw' => 1, '_timezone' => 'UTC'],
            $this->headers
        );
        if (!$this->core->request->error && ($response['data'] ?? null) && count($response['data']) > 0) {
            return $this->addError("Checks for [{$cfo_entity}/{$cfo_id}] already exist in remote platform. Use update-from-backup instead.");
        }
        //endregion

        //region INSERT Checks in remote platform via API
        $this->sendTerminal(" - Inserting Checks in remote platform...");
        $inserted = 0;
        foreach ($local_checks as $check) {
            $response = $this->core->request->post_json_decode(
                "{$this->api_base_url}/core/cfo/cfi/CloudFrameWorkDevDocumentationForProcessTests?_raw&_timezone=UTC",
                $check,
                $this->headers
            );

            if ($this->core->request->error || !($response['success'] ?? false)) {
                $check_title = $check['Title'] ?? $check['KeyId'] ?? 'unknown';
                $this->sendTerminal("   # Warning: Failed to insert check [{$check_title}]");
            } else {
                $inserted++;
            }
        }
        $this->sendTerminal(" + Inserted {$inserted} checks");
        //endregion

        //region SEND success message to terminal
        $this->sendTerminal(str_repeat('-', 50));
        $this->sendTerminal(" + Checks [{$cfo_entity}/{$cfo_id}] inserted successfully in remote platform");
        //endregion

        //region GET Last version of the Checks
        $this->formParams['entity'] = $cfo_entity;
        $this->formParams['id'] = $cfo_id;
        $this->METHOD_backup_from_remote();
        //endregion

        return true;
    }
}