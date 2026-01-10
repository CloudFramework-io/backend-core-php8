<?php
/**
 * Infrastructure Resources Backup Script
 *
 * This script provides functionality to manage CloudFrameWorkInfrastructureResources by:
 * - Backing up Resources from the remote platform to local storage
 * - Inserting new Resources from local backup to the remote platform
 * - Updating existing Resources from local backup to the remote platform
 * - Listing Resources in remote and local storage
 *
 * Resources document organizational assets both tangible (computers, devices, hardware)
 * and intangible (domains, databases, web servers, cloud services) under the
 * organization's control.
 *
 * The script operates on Resource data stored in the `buckets/backups/Resources/{platform}/` directory
 * and synchronizes them with the CloudFramework platform via REST API.
 *
 * Each Resource backup file contains:
 * - CloudFrameWorkInfrastructureResources: Resource data (KeyName, Category, Type, etc.)
 *
 * Usage:
 *   _cloudia/resources/backup-from-remote           - Backup all Resources from remote
 *   _cloudia/resources/backup-from-remote?id=key    - Backup specific Resource
 *   _cloudia/resources/insert-from-backup?id=key    - Insert new Resource to remote
 *   _cloudia/resources/update-from-backup?id=key    - Update existing Resource in remote
 *   _cloudia/resources/list-remote                  - List all Resources in remote
 *   _cloudia/resources/list-local                   - List all Resources in local backup
 *
 * @author CloudFramework Development Team
 * @version 2.1
 */
class Script extends CoreScripts
{
    /** @var string Platform ID for Resource operations */
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
            'X-WEB-KEY' => '/scripts/_cloudia/resources',
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
        $this->sendTerminal("  /backup-from-remote         - Backup all Resources from remote platform");
        $this->sendTerminal("  /backup-from-remote?id=key  - Backup specific Resource from remote platform");
        $this->sendTerminal("  /insert-from-backup?id=key  - Insert new Resource in remote platform from local backup");
        $this->sendTerminal("  /update-from-backup?id=key  - Update existing Resource in remote platform from local backup");
        $this->sendTerminal("  /list-remote                - List all Resources in remote platform");
        $this->sendTerminal("  /list-local                 - List all Resources in local backup");
        $this->sendTerminal("");
        $this->sendTerminal("Examples:");
        $this->sendTerminal("  composer run-script script \"_cloudia/resources/backup-from-remote?id=my-server-01\"");
        $this->sendTerminal("  composer run-script script _cloudia/resources/list-remote");
    }

    /**
     * Convert Resource KeyName to backup filename
     *
     * @param string $resource_id Resource KeyName (e.g., my-server-01)
     * @return string Filename (e.g., my-server-01.json)
     */
    private function resourceIdToFilename($resource_id)
    {
        // Sanitize special characters with underscores
        $name = preg_replace('/[^A-Za-z0-9_-]/', '_', $resource_id);
        return $name . '.json';
    }

    /**
     * Convert backup filename to Resource KeyName
     *
     * @param string $filename Filename (e.g., my-server-01.json)
     * @return string Resource KeyName (e.g., my-server-01)
     */
    private function filenameToResourceId($filename)
    {
        // Remove .json extension
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
        if (($backup_dir .= '/Resources') && !is_dir($backup_dir) && !mkdir($backup_dir)) {
            $this->addError("Backup directory [/buckets/backups/Resources] can not be created");
            return false;
        }
        if (($backup_dir .= '/' . $this->platform_id) && !is_dir($backup_dir) && !mkdir($backup_dir)) {
            $this->addError("Backup directory [/buckets/backups/Resources/{$this->platform_id}] can not be created");
            return false;
        }
        return $backup_dir;
    }

    /**
     * List all Resources in remote platform
     */
    public function METHOD_list_remote()
    {
        //region FETCH all Resources from remote platform
        $this->sendTerminal("Listing Resources in remote platform [{$this->platform_id}]:");
        $this->sendTerminal(str_repeat('-', 80));

        $params = ['_fields' => 'KeyName,Category,Type,Active', '_order' => 'KeyName', '_limit' => 1000];
        $response = $this->core->request->get_json_decode(
            "{$this->api_base_url}/core/cfo/cfi/CloudFrameWorkInfrastructureResources?_raw=1&_timezone=UTC",
            $params,
            $this->headers
        );

        if ($this->core->request->error) {
            return $this->addError($this->core->request->errorMsg);
        }

        $resources = $response['data'] ?? [];
        if (!$resources) {
            $this->sendTerminal("No Resources found in remote platform");
            return true;
        }

        $this->sendTerminal(sprintf("%-40s %-15s %-15s %s", "KeyName", "Category", "Type", "Active"));
        $this->sendTerminal(str_repeat('-', 80));

        foreach ($resources as $resource) {
            $active = ($resource['Active'] ?? false) ? 'Yes' : 'No';
            $this->sendTerminal(sprintf(
                "%-40s %-15s %-15s %s",
                substr($resource['KeyName'] ?? '', 0, 40),
                substr($resource['Category'] ?? '', 0, 15),
                substr($resource['Type'] ?? '', 0, 15),
                $active
            ));
        }
        $this->sendTerminal(str_repeat('-', 80));
        $this->sendTerminal("Total: " . count($resources) . " Resources");
        //endregion

        return true;
    }

    /**
     * List all Resources in local backup
     */
    public function METHOD_list_local()
    {
        //region LIST all Resources in local backup
        $this->sendTerminal("Listing Resources in local backup [{$this->platform_id}]:");
        $this->sendTerminal(str_repeat('-', 80));

        $directory_path = $this->core->system->root_path . '/buckets/backups/Resources/' . $this->platform_id;

        if (!is_dir($directory_path)) {
            $this->sendTerminal("Backup directory not found: /buckets/backups/Resources/{$this->platform_id}");
            return true;
        }

        $files = glob($directory_path . '/*.json');
        if (!$files) {
            $this->sendTerminal("No Resource backup files found");
            return true;
        }

        $this->sendTerminal(sprintf("%-40s %-15s %-15s %s", "KeyName", "Category", "Type", "Active"));
        $this->sendTerminal(str_repeat('-', 80));

        foreach ($files as $file) {
            $filename = basename($file);
            if ($filename === '_all_resources.json') continue;

            $data = $this->core->jsonDecode(file_get_contents($file));
            $resourceData = $data['CloudFrameWorkInfrastructureResources'] ?? $data;
            $active = ($resourceData['Active'] ?? false) ? 'Yes' : 'No';
            $this->sendTerminal(sprintf(
                "%-40s %-15s %-15s %s",
                substr($resourceData['KeyName'] ?? $filename, 0, 40),
                substr($resourceData['Category'] ?? '', 0, 15),
                substr($resourceData['Type'] ?? '', 0, 15),
                $active
            ));
        }
        $this->sendTerminal(str_repeat('-', 80));
        $this->sendTerminal("Total: " . count($files) . " Resources");
        //endregion

        return true;
    }

    /**
     * Backup Resources from remote platform to local storage
     */
    public function METHOD_backup_from_remote()
    {
        //region VERIFY backup directory
        if (!$backup_dir = $this->getBackupDir()) {
            return false;
        }
        $this->sendTerminal(" - Backup directory: /buckets/backups/Resources/{$this->platform_id}");
        //endregion

        //region SET $resource_id (specific Resource to backup, or null for all)
        $resource_id = $this->formParams['id'] ?? null;
        //endregion

        //region READ Resources from remote API
        $resources = [];
        if ($resource_id) {
            //region FETCH single Resource by KeyName
            $this->sendTerminal(" - Fetching Resource: {$resource_id}");
            $response = $this->core->request->get_json_decode(
                "{$this->api_base_url}/core/cfo/cfi/CloudFrameWorkInfrastructureResources/display/" . urlencode($resource_id) . '?_raw&_timezone=UTC',
                ['_raw' => 1, '_timezone' => 'UTC'],
                $this->headers
            );
            if ($this->core->request->error) {
                return $this->addError($this->core->request->errorMsg);
            }
            if (!($response['data'] ?? null)) {
                return $this->addError("Resource [{$resource_id}] not found in remote platform");
            }
            $resources = [$response['data']];
            //endregion

        } else {
            //region FETCH all Resources
            $this->sendTerminal(" - Fetching all Resources... [max 2000]");
            $response = $this->core->request->get_json_decode(
                "{$this->api_base_url}/core/cfo/cfi/CloudFrameWorkInfrastructureResources?_raw&_timezone=UTC",
                ['cfo_limit' => 2000, '_raw' => 1, '_timezone' => 'UTC'],
                $this->headers
            );
            if ($this->core->request->error) {
                return $this->addError($this->core->request->errorMsg);
            }
            $resources = $response['data'] ?? [];
            //endregion
        }
        $tot_resources = count($resources);

        $this->sendTerminal(" - Resources to backup: {$tot_resources}");
        //endregion

        //region PROCESS and SAVE each Resource to backup directory
        $saved_count = 0;
        $activeCount = 0;
        $inactiveCount = 0;

        foreach ($resources as $resource) {
            //region VALIDATE Resource has KeyName
            if (!($resource['KeyName'] ?? null)) {
                $this->sendTerminal("   # Skipping Resource without KeyName");
                continue;
            }
            $key_name = $resource['KeyName'];
            //endregion

            //region SORT $resource keys alphabetically
            ksort($resource);
            //endregion

            //region BUILD $resource_data structure
            $resource_data = [
                'CloudFrameWorkInfrastructureResources' => $resource
            ];
            //endregion

            //region SAVE $resource_data to JSON file
            $filename = $this->resourceIdToFilename($key_name);
            $filepath = "{$backup_dir}/{$filename}";
            $json_content = $this->core->jsonEncode($resource_data, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
            if (file_put_contents($filepath, $json_content) === false) {
                return $this->addError("Failed to write Resource [{$key_name}] to file");
            }
            $saved_count++;
            if ($resource['Active'] ?? false) {
                $activeCount++;
            } else {
                $inactiveCount++;
            }
            $this->sendTerminal("   + Saved: {$filename}");
            //endregion
        }
        //endregion

        //region SEND summary to terminal
        $this->sendTerminal(str_repeat('-', 50));
        $this->sendTerminal(" - Total Resources saved: {$saved_count} ({$activeCount} active, {$inactiveCount} inactive)");
        //endregion

        return true;
    }

    /**
     * Update existing Resource in remote platform from local backup
     */
    public function METHOD_update_from_backup()
    {
        //region VALIDATE $resource_id (required parameter)
        $resource_id = $this->formParams['id'] ?? null;
        if (!$resource_id) {
            return $this->addError("Missing required parameter: id. Usage: _cloudia/resources/update-from-backup?id=resource-key");
        }
        $this->sendTerminal(" - Resource to update: {$resource_id}");
        //endregion

        //region SET $backup_file (path to local backup JSON file)
        $filename = $this->resourceIdToFilename($resource_id);
        $backup_file = $this->core->system->root_path . "/buckets/backups/Resources/{$this->platform_id}/{$filename}";
        $this->sendTerminal(" - Backup file: /buckets/backups/Resources/{$this->platform_id}/{$filename}");
        //endregion

        //region VALIDATE $backup_file exists
        if (!is_file($backup_file)) {
            return $this->addError("Backup file not found: {$backup_file}");
        }
        //endregion

        //region READ $resource_data from backup file
        $json_content = file_get_contents($backup_file);
        if ($json_content === false) {
            return $this->addError("Failed to read backup file: {$backup_file}");
        }
        $resource_data = $this->core->jsonDecode($json_content);
        if ($resource_data === null) {
            return $this->addError("Invalid JSON in backup file: {$backup_file}");
        }
        $this->sendTerminal(" - Resource data loaded successfully");
        //endregion

        //region VALIDATE $resource_data has correct structure
        $resource = $resource_data['CloudFrameWorkInfrastructureResources'] ?? $resource_data;
        if (!$resource || ($resource['KeyName'] ?? null) !== $resource_id) {
            return $this->addError("KeyName mismatch: file contains '{$resource['KeyName']}' but expected '{$resource_id}'");
        }
        //endregion

        //region UPDATE Resource in remote platform via API
        $this->sendTerminal(" - Updating Resource in remote platform...");
        $response = $this->core->request->put_json_decode(
            "{$this->api_base_url}/core/cfo/cfi/CloudFrameWorkInfrastructureResources/" . urlencode($resource_id) . "?_raw&_timezone=UTC",
            $resource,
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
        $this->sendTerminal(" + Resource record updated");
        //endregion

        //region SEND success message to terminal
        $this->sendTerminal(str_repeat('-', 50));
        $this->sendTerminal(" + Resource [{$resource_id}] updated successfully in remote platform");
        //endregion

        //region GET Last version of the Resource
        $this->formParams['id'] = $resource_id;
        $this->METHOD_backup_from_remote();
        //endregion

        return true;
    }

    /**
     * Insert new Resource in remote platform from local backup
     */
    public function METHOD_insert_from_backup()
    {
        //region VALIDATE $resource_id (required parameter)
        $resource_id = $this->formParams['id'] ?? null;
        if (!$resource_id) {
            return $this->addError("Missing required parameter: id. Usage: _cloudia/resources/insert-from-backup?id=resource-key");
        }
        $this->sendTerminal(" - Resource to insert: {$resource_id}");
        //endregion

        //region SET $backup_file (path to local backup JSON file)
        $filename = $this->resourceIdToFilename($resource_id);
        $backup_file = $this->core->system->root_path . "/buckets/backups/Resources/{$this->platform_id}/{$filename}";
        $this->sendTerminal(" - Backup file: /buckets/backups/Resources/{$this->platform_id}/{$filename}");
        //endregion

        //region VALIDATE $backup_file exists
        if (!is_file($backup_file)) {
            return $this->addError("Backup file not found: {$backup_file}");
        }
        //endregion

        //region READ $resource_data from backup file
        $json_content = file_get_contents($backup_file);
        if ($json_content === false) {
            return $this->addError("Failed to read backup file: {$backup_file}");
        }
        $resource_data = $this->core->jsonDecode($json_content);
        if ($resource_data === null) {
            return $this->addError("Invalid JSON in backup file: {$backup_file}");
        }
        $this->sendTerminal(" - Resource data loaded successfully");
        //endregion

        //region VALIDATE $resource_data has correct structure
        $resource = $resource_data['CloudFrameWorkInfrastructureResources'] ?? $resource_data;
        if (!$resource || ($resource['KeyName'] ?? null) !== $resource_id) {
            return $this->addError("KeyName mismatch: file contains '{$resource['KeyName']}' but expected '{$resource_id}'");
        }
        //endregion

        //region CHECK if Resource already exists in remote platform
        $check_response = $this->core->request->get_json_decode(
            "{$this->api_base_url}/core/cfo/cfi/CloudFrameWorkInfrastructureResources/" . urlencode($resource_id) . '?_raw&_timezone=UTC',
            [],
            $this->headers
        );
        if (!$this->core->request->error && ($check_response['data'] ?? null)) {
            return $this->addError("Resource [{$resource_id}] already exists in remote platform. Use update-from-backup instead.");
        }
        //endregion

        //region INSERT Resource in remote platform via API
        $this->sendTerminal(" - Inserting Resource in remote platform...");
        $response = $this->core->request->post_json_decode(
            "{$this->api_base_url}/core/cfo/cfi/CloudFrameWorkInfrastructureResources?_raw&_timezone=UTC",
            $resource,
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
        $this->sendTerminal(" + Resource record inserted");
        //endregion

        //region SEND success message to terminal
        $this->sendTerminal(str_repeat('-', 50));
        $this->sendTerminal(" + Resource [{$resource_id}] inserted successfully in remote platform");
        //endregion

        //region GET Last version of the Resource
        $this->formParams['id'] = $resource_id;
        $this->METHOD_backup_from_remote();
        //endregion

        return true;
    }
}
