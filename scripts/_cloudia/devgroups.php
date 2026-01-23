<?php
/**
 * Development Groups Documentation Backup Script
 *
 * This script provides functionality to manage Development Groups documentation by:
 * - Backing up Development Groups from the remote platform to local storage
 * - Inserting new Development Groups from local backup to the remote platform
 * - Updating existing Development Groups from local backup to the remote platform
 * - Listing Development Groups in remote and local storage
 *
 * The script operates on Development Group documentation stored in the `buckets/backups/DevelopmentGroups/{platform}/` directory
 * and synchronizes them with the CloudFramework platform via REST API.
 *
 * Development Groups (CloudFrameWorkDevDocumentation) are the organizational backbone of CLOUD Documentum.
 * Other documentation CFOs reference Development Groups via the DocumentationId field, which links to the Development Group's KeyName:
 * - CloudFrameWorkDevDocumentationForWebApps
 * - CloudFrameWorkDevDocumentationForAPIs
 * - CloudFrameWorkDevDocumentationForLibraries
 * - CloudFrameWorkDevDocumentationForProcesses
 * - CloudFrameWorkAcademyCourses
 * - CloudFrameWorkProjectsEntries
 * - CloudFrameWorkProjectsMilestones
 * - CloudFrameWorkECMPages
 *
 * Usage:
 *   _cloudia/devgroups/backup-from-remote                              - Backup all Development Groups from remote
 *   _cloudia/devgroups/backup-from-remote?id=/cf/products/my-product   - Backup specific Development Group
 *   _cloudia/devgroups/insert-from-backup?id=/cf/products/my-product   - Insert new Development Group to remote
 *   _cloudia/devgroups/update-from-backup?id=/cf/products/my-product   - Update existing Development Group in remote
 *   _cloudia/devgroups/list-remote                                     - List all Development Groups in remote
 *   _cloudia/devgroups/list-local                                      - List all Development Groups in local backup
 *
 * @author CloudFramework Development Team
 * @version 1.0
 */
class Script extends CoreScripts
{
    /** @var string Platform ID for Development Group operations */
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
            return $this->addError('You do not have permission [development-admin,development-user] to execute this script');
        }
        //endregion

        //region SET AUTHENTICATE header for API call
        $this->headers = [
            'X-WEB-KEY' => '/scripts/_cloudia/devgroups',
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
        $this->sendTerminal("  /backup-from-remote            - Backup all Development Groups from remote platform");
        $this->sendTerminal("  /backup-from-remote?id=KEY     - Backup specific Development Group from remote platform");
        $this->sendTerminal("  /insert-from-backup?id=KEY     - Insert new Development Group in remote platform from local backup");
        $this->sendTerminal("  /update-from-backup?id=KEY     - Update existing Development Group in remote platform from local backup");
        $this->sendTerminal("  /list-remote                   - List all Development Groups in remote platform");
        $this->sendTerminal("  /list-local                    - List all Development Groups in local backup");
        $this->sendTerminal("");
        $this->sendTerminal("Examples:");
        $this->sendTerminal("  composer run-script script \"_cloudia/devgroups/backup-from-remote?id=/cf/products/cloud-documentum\"");
        $this->sendTerminal("  composer run-script script _cloudia/devgroups/list-remote");
        $this->sendTerminal("");
        $this->sendTerminal("Note: The ?id= parameter is the Development Group KeyName (e.g., /cf/products/cloud-documentum)");
    }

    /**
     * Convert Development Group KeyName to backup filename
     *
     * @param string $devgroup_id Development Group KeyName (e.g., /cf/products/cloud-documentum)
     * @return string Filename (e.g., _cf_products_cloud-documentum.json)
     */
    private function devgroupIdToFilename($devgroup_id)
    {
        // Replace special characters with underscores for safe filenames
        $name = preg_replace('/[^A-Za-z0-9_-]/', '_', $devgroup_id);
        return $name . '.json';
    }

    /**
     * Convert backup filename to Development Group KeyName
     *
     * @param string $filename Filename (e.g., _cf_products_cloud-documentum.json)
     * @return string Development Group KeyName (e.g., /cf/products/cloud-documentum)
     */
    private function filenameToDevgroupId($filename)
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
        if (($backup_dir .= '/DevelopmentGroups') && !is_dir($backup_dir) && !mkdir($backup_dir)) {
            $this->addError("Backup directory [/buckets/backups/DevelopmentGroups] can not be created");
            return false;
        }
        if (($backup_dir .= '/' . $this->platform_id) && !is_dir($backup_dir) && !mkdir($backup_dir)) {
            $this->addError("Backup directory [/buckets/backups/DevelopmentGroups/{$this->platform_id}] can not be created");
            return false;
        }
        return $backup_dir;
    }

    /**
     * List all Development Groups in remote platform
     */
    public function METHOD_list_remote()
    {
        //region FETCH all Development Groups from remote platform
        $this->sendTerminal("Listing Development Groups in remote platform [{$this->platform_id}]:");
        $this->sendTerminal(str_repeat('-', 80));

        $params = ['_fields' => 'KeyName,Title,Cat,Status,Owner', '_order' => 'KeyName', '_limit' => 1000];
        $response = $this->core->request->get_json_decode(
            "{$this->api_base_url}/core/cfo/cfi/CloudFrameWorkDevDocumentation?_raw=1&_timezone=UTC",
            $params,
            $this->headers
        );

        if ($this->core->request->error) {
            return $this->addError($this->core->request->errorMsg);
        }

        $devgroups = $response['data'] ?? [];
        if (!$devgroups) {
            $this->sendTerminal("No Development Groups found in remote platform");
            return true;
        }

        foreach ($devgroups as $devgroup) {
            $status = $devgroup['Status'] ?? 'N/A';
            $title = $devgroup['Title'] ?? 'N/A';
            $cat = $devgroup['Cat'] ?? '';
            $owner = $devgroup['Owner'] ?? '';
            $catInfo = $cat ? " [{$cat}]" : "";
            $ownerInfo = $owner ? " by {$owner}" : "";
            $this->sendTerminal(" {$devgroup['KeyName']}{$catInfo} - {$title} [{$status}]{$ownerInfo}");
        }
        $this->sendTerminal(str_repeat('-', 80));
        $this->sendTerminal("Total: " . count($devgroups) . " Development Groups");
        //endregion

        return true;
    }

    /**
     * List all Development Groups in local backup
     */
    public function METHOD_list_local()
    {
        //region LIST all Development Groups in local backup
        $this->sendTerminal("Listing Development Groups in local backup [{$this->platform_id}]:");
        $this->sendTerminal(str_repeat('-', 80));

        $directory_path = $this->core->system->root_path . '/buckets/backups/DevelopmentGroups/' . $this->platform_id;

        if (!is_dir($directory_path)) {
            $this->sendTerminal("Backup directory not found: /buckets/backups/DevelopmentGroups/{$this->platform_id}");
            return true;
        }

        $files = glob($directory_path . '/*.json');
        if (!$files) {
            $this->sendTerminal("No Development Group backup files found");
            return true;
        }

        foreach ($files as $file) {
            $data = $this->core->jsonDecode(file_get_contents($file));
            $keyName = $data['KeyName'] ?? basename($file, '.json');
            $title = $data['Title'] ?? 'N/A';
            $status = $data['Status'] ?? 'N/A';
            $cat = $data['Cat'] ?? '';
            $owner = $data['Owner'] ?? '';
            $catInfo = $cat ? " [{$cat}]" : "";
            $ownerInfo = $owner ? " by {$owner}" : "";
            $this->sendTerminal(" {$keyName}{$catInfo} - {$title} [{$status}]{$ownerInfo}");
        }
        $this->sendTerminal(str_repeat('-', 80));
        $this->sendTerminal("Total: " . count($files) . " Development Groups");
        //endregion

        return true;
    }

    /**
     * Backup Development Groups from remote platform to local storage
     */
    public function METHOD_backup_from_remote()
    {
        //region VERIFY backup directory
        if (!$backup_dir = $this->getBackupDir()) {
            return false;
        }
        $this->sendTerminal(" - Backup directory: /buckets/backups/DevelopmentGroups/{$this->platform_id}");
        //endregion

        //region SET $devgroup_id (specific Development Group to backup, or null for all)
        $devgroup_id = $this->formParams['id'] ?? null;
        //endregion

        //region READ Development Groups from remote API
        $devgroups = [];

        if ($devgroup_id) {
            //region FETCH single Development Group by KeyName
            $this->sendTerminal(" - Fetching Development Group: {$devgroup_id}");
            $response = $this->core->request->get_json_decode(
                "{$this->api_base_url}/core/cfo/cfi/CloudFrameWorkDevDocumentation/display/" . urlencode($devgroup_id) . '?_raw&_timezone=UTC',
                ['_raw' => 1, '_timezone' => 'UTC'],
                $this->headers
            );
            if ($this->core->request->error) {
                return $this->addError($this->core->request->errorMsg);
            }
            if (!($response['data'] ?? null)) {
                return $this->addError("Development Group [{$devgroup_id}] not found in remote platform");
            }
            $devgroups = [$response['data']];
            //endregion

        } else {
            //region FETCH all Development Groups
            $this->sendTerminal(" - Fetching all Development Groups... [max 2000]");
            $response = $this->core->request->get_json_decode(
                "{$this->api_base_url}/core/cfo/cfi/CloudFrameWorkDevDocumentation?_raw&_timezone=UTC",
                ['cfo_limit' => 2000, '_raw' => 1, '_timezone' => 'UTC'],
                $this->headers
            );
            if ($this->core->request->error) {
                return $this->addError($this->core->request->errorMsg);
            }
            $devgroups = $response['data'] ?? [];
            //endregion
        }
        $tot_devgroups = count($devgroups);
        $this->sendTerminal(" - Development Groups to backup: {$tot_devgroups}");
        //endregion

        //region PROCESS and SAVE each Development Group to backup directory
        $saved_count = 0;

        foreach ($devgroups as $devgroup) {
            //region VALIDATE Development Group has KeyName
            if (!($devgroup['KeyName'] ?? null)) {
                $this->sendTerminal("   # Skipping Development Group without KeyName");
                continue;
            }
            $key_name = $devgroup['KeyName'];
            //endregion

            //region SORT $devgroup keys alphabetically
            ksort($devgroup);
            //endregion

            //region SAVE $devgroup to JSON file
            $filename = $this->devgroupIdToFilename($key_name);
            $filepath = "{$backup_dir}/{$filename}";
            $json_content = $this->core->jsonEncode($devgroup, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            if (file_put_contents($filepath, $json_content) === false) {
                return $this->addError("Failed to write Development Group [{$key_name}] to file");
            }
            $saved_count++;
            $this->sendTerminal("   + Saved: {$filename}");
            //endregion
        }
        //endregion

        //region SEND summary to terminal
        $this->sendTerminal(str_repeat('-', 50));
        $this->sendTerminal(" - Total Development Groups saved: {$saved_count}");
        //endregion

        return true;
    }

    /**
     * Update existing Development Group in remote platform from local backup
     */
    public function METHOD_update_from_backup()
    {
        //region VALIDATE $devgroup_id (required parameter)
        $devgroup_id = $this->formParams['id'] ?? null;
        if (!$devgroup_id) {
            return $this->addError("Missing required parameter: id. Usage: _cloudia/devgroups/update-from-backup?id=/cf/products/my-product");
        }
        $this->sendTerminal(" - Development Group to update: {$devgroup_id}");
        //endregion

        //region SET $backup_file (path to local backup JSON file)
        $filename = $this->devgroupIdToFilename($devgroup_id);
        $backup_file = $this->core->system->root_path . "/buckets/backups/DevelopmentGroups/{$this->platform_id}/{$filename}";
        $this->sendTerminal(" - Backup file: /buckets/backups/DevelopmentGroups/{$this->platform_id}/{$filename}");
        //endregion

        //region VALIDATE $backup_file exists
        if (!is_file($backup_file)) {
            return $this->addError("Backup file not found: {$backup_file}");
        }
        //endregion

        //region READ $devgroup_data from backup file
        $json_content = file_get_contents($backup_file);
        if ($json_content === false) {
            return $this->addError("Failed to read backup file: {$backup_file}");
        }
        $devgroup = $this->core->jsonDecode($json_content);
        if ($devgroup === null) {
            return $this->addError("Invalid JSON in backup file: {$backup_file}");
        }
        $this->sendTerminal(" - Development Group data loaded successfully");
        //endregion

        //region VALIDATE $devgroup has correct structure
        if (($devgroup['KeyName'] ?? null) !== $devgroup_id) {
            return $this->addError("KeyName mismatch: file contains '{$devgroup['KeyName']}' but expected '{$devgroup_id}'");
        }
        //endregion

        //region UPDATE Development Group in remote platform via API
        $this->sendTerminal(" - Updating Development Group in remote platform...");
        $response = $this->core->request->put_json_decode(
            "{$this->api_base_url}/core/cfo/cfi/CloudFrameWorkDevDocumentation/" . urlencode($devgroup_id) . "?_raw&_timezone=UTC",
            $devgroup,
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
        $this->sendTerminal(" + Development Group record updated");
        //endregion

        //region SEND success message to terminal
        $this->sendTerminal(str_repeat('-', 50));
        $this->sendTerminal(" + Development Group [{$devgroup_id}] updated successfully in remote platform");
        //endregion

        //region GET Last version of the Development Group
        $this->formParams['id'] = $devgroup_id;
        $this->METHOD_backup_from_remote();
        //endregion

        return true;
    }

    /**
     * Insert new Development Group in remote platform from local backup
     */
    public function METHOD_insert_from_backup()
    {
        //region VALIDATE $devgroup_id (required parameter)
        $devgroup_id = $this->formParams['id'] ?? null;
        if (!$devgroup_id) {
            return $this->addError("Missing required parameter: id. Usage: _cloudia/devgroups/insert-from-backup?id=/cf/products/my-product");
        }
        $this->sendTerminal(" - Development Group to insert: {$devgroup_id}");
        //endregion

        //region SET $backup_file (path to local backup JSON file)
        $filename = $this->devgroupIdToFilename($devgroup_id);
        $backup_file = $this->core->system->root_path . "/buckets/backups/DevelopmentGroups/{$this->platform_id}/{$filename}";
        $this->sendTerminal(" - Backup file: /buckets/backups/DevelopmentGroups/{$this->platform_id}/{$filename}");
        //endregion

        //region VALIDATE $backup_file exists
        if (!is_file($backup_file)) {
            return $this->addError("Backup file not found: {$backup_file}");
        }
        //endregion

        //region READ $devgroup_data from backup file
        $json_content = file_get_contents($backup_file);
        if ($json_content === false) {
            return $this->addError("Failed to read backup file: {$backup_file}");
        }
        $devgroup = $this->core->jsonDecode($json_content);
        if ($devgroup === null) {
            return $this->addError("Invalid JSON in backup file: {$backup_file}");
        }
        $this->sendTerminal(" - Development Group data loaded successfully");
        //endregion

        //region VALIDATE $devgroup has correct structure
        if (($devgroup['KeyName'] ?? null) !== $devgroup_id) {
            return $this->addError("KeyName mismatch: file contains '{$devgroup['KeyName']}' but expected '{$devgroup_id}'");
        }
        //endregion

        //region CHECK if Development Group already exists in remote platform
        $check_response = $this->core->request->get_json_decode(
            "{$this->api_base_url}/core/cfo/cfi/CloudFrameWorkDevDocumentation/" . urlencode($devgroup_id) . '?_raw&_timezone=UTC',
            [],
            $this->headers
        );
        if (!$this->core->request->error && ($check_response['data'] ?? null)) {
            return $this->addError("Development Group [{$devgroup_id}] already exists in remote platform. Use update-from-backup instead.");
        }
        //endregion

        //region INSERT Development Group in remote platform via API
        $this->sendTerminal(" - Inserting Development Group in remote platform...");
        $response = $this->core->request->post_json_decode(
            "{$this->api_base_url}/core/cfo/cfi/CloudFrameWorkDevDocumentation?_raw&_timezone=UTC",
            $devgroup,
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

        $new_key_name = $response['data']['KeyName'] ?? $devgroup['KeyName'] ?? null;
        $this->sendTerminal(" + Development Group record inserted (KeyName: {$new_key_name})");
        //endregion

        //region SEND success message to terminal
        $this->sendTerminal(str_repeat('-', 50));
        $this->sendTerminal(" + Development Group [{$devgroup_id}] inserted successfully in remote platform");
        //endregion

        //region GET Last version of the Development Group
        $this->formParams['id'] = $devgroup_id;
        $this->METHOD_backup_from_remote();
        //endregion

        return true;
    }
}
