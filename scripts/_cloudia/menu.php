<?php
/**
 * Menu Modules Backup Script
 *
 * This script provides functionality to manage CloudFrameWorkModules (Menu Modules) by:
 * - Backing up Menu Modules from the remote platform to local storage
 * - Inserting new Menu Modules from local backup to the remote platform
 * - Updating existing Menu Modules from local backup to the remote platform
 * - Listing Menu Modules in remote and local storage
 *
 * Menu modules define the navigation structure shown to users for accessing different
 * solutions in the CLOUD Platform. The JSON field contains a hierarchical menu structure
 * rendered by the web application (app.html).
 *
 * The script operates on Menu Module data stored in the `buckets/backups/Menus/{platform}/` directory
 * and synchronizes them with the CloudFramework platform via REST API.
 *
 * Each Menu Module backup file contains:
 * - CloudFrameWorkModules: Menu module data (KeyName, ModuleName, JSON, Active, etc.)
 *
 * Usage:
 *   _cloudia/menu/backup-from-remote           - Backup all Menu Modules from remote
 *   _cloudia/menu/backup-from-remote?id=key    - Backup specific Menu Module
 *   _cloudia/menu/insert-from-backup?id=key    - Insert new Menu Module to remote
 *   _cloudia/menu/update-from-backup?id=key    - Update existing Menu Module in remote
 *   _cloudia/menu/list-remote                  - List all Menu Modules in remote
 *   _cloudia/menu/list-local                   - List all Menu Modules in local backup
 *
 * @author CloudFramework Development Team
 * @version 2.1
 * @see CloudFrameWorkModules CFO
 * @see local_data/core20.web.app/public/app.html (Web application that renders menus)
 */
class Script extends CoreScripts
{
    /** @var string Platform ID for Menu Module operations */
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
            'X-WEB-KEY' => '/scripts/_cloudia/menu',
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
        $this->sendTerminal("  /backup-from-remote           - Backup all Menu Modules from remote platform");
        $this->sendTerminal("  /backup-from-remote?id=key    - Backup specific Menu Module from remote platform");
        $this->sendTerminal("  /insert-from-backup?id=key    - Insert new Menu Module in remote platform from local backup");
        $this->sendTerminal("  /update-from-backup?id=key    - Update existing Menu Module in remote platform from local backup");
        $this->sendTerminal("  /list-remote                  - List all Menu Modules in remote platform");
        $this->sendTerminal("  /list-local                   - List all Menu Modules in local backup");
        $this->sendTerminal("");
        $this->sendTerminal("Examples:");
        $this->sendTerminal("  composer run-script script \"_cloudia/menu/backup-from-remote?id=development\"");
        $this->sendTerminal("  composer run-script script _cloudia/menu/list-remote");
    }

    /**
     * Convert Menu Module KeyName to backup filename
     *
     * @param string $module_id Module KeyName (e.g., development, crm, hipotech)
     * @return string Filename (e.g., development.json)
     */
    private function moduleIdToFilename($module_id)
    {
        // Sanitize special characters with underscores
        $name = preg_replace('/[^A-Za-z0-9_-]/', '_', $module_id);
        return $name . '.json';
    }

    /**
     * Convert backup filename to Menu Module KeyName
     *
     * @param string $filename Filename (e.g., development.json)
     * @return string Module KeyName (e.g., development)
     */
    private function filenameToModuleId($filename)
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
        if (($backup_dir .= '/Menus') && !is_dir($backup_dir) && !mkdir($backup_dir)) {
            $this->addError("Backup directory [/buckets/backups/Menus] can not be created");
            return false;
        }
        if (($backup_dir .= '/' . $this->platform_id) && !is_dir($backup_dir) && !mkdir($backup_dir)) {
            $this->addError("Backup directory [/buckets/backups/Menus/{$this->platform_id}] can not be created");
            return false;
        }
        return $backup_dir;
    }

    /**
     * List all Menu Modules in remote platform
     */
    public function METHOD_list_remote()
    {
        //region FETCH all Menu Modules from remote platform
        $this->sendTerminal("Listing Menu Modules in remote platform [{$this->platform_id}]:");
        $this->sendTerminal(str_repeat('-', 80));

        $params = ['_fields' => 'KeyName,ModuleName,Active', '_order' => 'ModuleName', '_limit' => 1000];
        $response = $this->core->request->get_json_decode(
            "{$this->api_base_url}/core/cfo/cfi/CloudFrameWorkModules?_raw=1&_timezone=UTC",
            $params,
            $this->headers
        );

        if ($this->core->request->error) {
            return $this->addError($this->core->request->errorMsg);
        }

        $modules = $response['data'] ?? [];
        if (!$modules) {
            $this->sendTerminal("No Menu Modules found in remote platform");
            return true;
        }

        $this->sendTerminal(sprintf("%-30s %-40s %s", "KeyName", "ModuleName", "Active"));
        $this->sendTerminal(str_repeat('-', 80));

        foreach ($modules as $module) {
            $active = ($module['Active'] ?? false) ? 'Yes' : 'No';
            $this->sendTerminal(sprintf(
                "%-30s %-40s %s",
                substr($module['KeyName'] ?? '', 0, 30),
                substr($module['ModuleName'] ?? '', 0, 40),
                $active
            ));
        }
        $this->sendTerminal(str_repeat('-', 80));
        $this->sendTerminal("Total: " . count($modules) . " Menu Modules");
        //endregion

        return true;
    }

    /**
     * List all Menu Modules in local backup
     */
    public function METHOD_list_local()
    {
        //region LIST all Menu Modules in local backup
        $this->sendTerminal("Listing Menu Modules in local backup [{$this->platform_id}]:");
        $this->sendTerminal(str_repeat('-', 80));

        $directory_path = $this->core->system->root_path . '/buckets/backups/Menus/' . $this->platform_id;

        if (!is_dir($directory_path)) {
            $this->sendTerminal("Backup directory not found: /buckets/backups/Menus/{$this->platform_id}");
            return true;
        }

        $files = glob($directory_path . '/*.json');
        if (!$files) {
            $this->sendTerminal("No Menu Module backup files found");
            return true;
        }

        $this->sendTerminal(sprintf("%-30s %-40s %s", "KeyName", "ModuleName", "Active"));
        $this->sendTerminal(str_repeat('-', 80));

        $count = 0;
        foreach ($files as $file) {
            $filename = basename($file);
            if ($filename === '_all_modules.json') continue;

            $data = $this->core->jsonDecode(file_get_contents($file));
            $moduleData = $data['CloudFrameWorkModules'] ?? $data;
            $active = ($moduleData['Active'] ?? false) ? 'Yes' : 'No';
            $this->sendTerminal(sprintf(
                "%-30s %-40s %s",
                substr($moduleData['KeyName'] ?? $filename, 0, 30),
                substr($moduleData['ModuleName'] ?? '', 0, 40),
                $active
            ));
            $count++;
        }
        $this->sendTerminal(str_repeat('-', 80));
        $this->sendTerminal("Total: " . $count . " Menu Modules");
        //endregion

        return true;
    }

    /**
     * Backup Menu Modules from remote platform to local storage
     */
    public function METHOD_backup_from_remote()
    {
        //region VERIFY backup directory
        if (!$backup_dir = $this->getBackupDir()) {
            return false;
        }
        $this->sendTerminal(" - Backup directory: /buckets/backups/Menus/{$this->platform_id}");
        //endregion

        //region SET $module_id (specific Menu Module to backup, or null for all)
        $module_id = $this->formParams['id'] ?? null;
        //endregion

        //region READ Menu Modules from remote API
        $modules = [];
        if ($module_id) {
            //region FETCH single Menu Module by KeyName
            $this->sendTerminal(" - Fetching Menu Module: {$module_id}");
            $response = $this->core->request->get_json_decode(
                "{$this->api_base_url}/core/cfo/cfi/CloudFrameWorkModules/display/" . urlencode($module_id) . '?_raw&_timezone=UTC',
                ['_raw' => 1, '_timezone' => 'UTC'],
                $this->headers
            );
            if ($this->core->request->error) {
                return $this->addError($this->core->request->errorMsg);
            }
            if (!($response['data'] ?? null)) {
                return $this->addError("Menu Module [{$module_id}] not found in remote platform");
            }
            $modules = [$response['data']];
            //endregion

        } else {
            //region FETCH all Menu Modules
            $this->sendTerminal(" - Fetching all Menu Modules... [max 2000]");
            $response = $this->core->request->get_json_decode(
                "{$this->api_base_url}/core/cfo/cfi/CloudFrameWorkModules?_raw&_timezone=UTC",
                ['cfo_limit' => 2000, '_raw' => 1, '_timezone' => 'UTC'],
                $this->headers
            );
            if ($this->core->request->error) {
                return $this->addError($this->core->request->errorMsg);
            }
            $modules = $response['data'] ?? [];
            //endregion
        }
        $tot_modules = count($modules);

        $this->sendTerminal(" - Menu Modules to backup: {$tot_modules}");
        //endregion

        //region PROCESS and SAVE each Menu Module to backup directory
        $saved_count = 0;
        $unchanged_count = 0;
        $activeCount = 0;
        $inactiveCount = 0;

        foreach ($modules as $module) {
            //region VALIDATE Menu Module has KeyName
            if (!($module['KeyName'] ?? null)) {
                $this->sendTerminal("   # Skipping Menu Module without KeyName");
                continue;
            }
            $key_name = $module['KeyName'];
            //endregion

            //region SORT $module keys alphabetically
            ksort($module);
            //endregion

            //region BUILD $module_data structure
            $module_data = [
                'CloudFrameWorkModules' => $module
            ];
            //endregion

            //region COMPARE with existing local file and SAVE if changed
            $filename = $this->moduleIdToFilename($key_name);
            $filepath = "{$backup_dir}/{$filename}";
            $json_content = $this->core->jsonEncode($module_data, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);

            // Check if file exists and content is unchanged
            if (is_file($filepath)) {
                $existing_content = file_get_contents($filepath);
                if ($existing_content === $json_content) {
                    $unchanged_count++;
                    $this->sendTerminal("   = Unchanged: {$filename}");
                    continue;
                }
            }

            // Save the file (new or changed)
            if (file_put_contents($filepath, $json_content) === false) {
                return $this->addError("Failed to write Menu Module [{$key_name}] to file");
            }
            $saved_count++;
            if ($module['Active'] ?? false) {
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
        $this->sendTerminal(" - Total Menu Modules processed: " . ($saved_count + $unchanged_count) . " (saved: {$saved_count}, unchanged: {$unchanged_count})");
        //endregion

        return true;
    }

    /**
     * Update existing Menu Module in remote platform from local backup
     */
    public function METHOD_update_from_backup()
    {
        //region VALIDATE $module_id (required parameter)
        $module_id = $this->formParams['id'] ?? null;
        if (!$module_id) {
            return $this->addError("Missing required parameter: id. Usage: _cloudia/menu/update-from-backup?id=module-key");
        }
        $this->sendTerminal(" - Menu Module to update: {$module_id}");
        //endregion

        //region SET $backup_file (path to local backup JSON file)
        $filename = $this->moduleIdToFilename($module_id);
        $backup_file = $this->core->system->root_path . "/buckets/backups/Menus/{$this->platform_id}/{$filename}";
        $this->sendTerminal(" - Backup file: /buckets/backups/Menus/{$this->platform_id}/{$filename}");
        //endregion

        //region VALIDATE $backup_file exists
        if (!is_file($backup_file)) {
            return $this->addError("Backup file not found: {$backup_file}");
        }
        //endregion

        //region READ $module_data from backup file
        $json_content = file_get_contents($backup_file);
        if ($json_content === false) {
            return $this->addError("Failed to read backup file: {$backup_file}");
        }
        $module_data = $this->core->jsonDecode($json_content);
        if ($module_data === null) {
            return $this->addError("Invalid JSON in backup file: {$backup_file}");
        }
        $this->sendTerminal(" - Menu Module data loaded successfully");
        //endregion

        //region VALIDATE $module_data has correct structure
        $module = $module_data['CloudFrameWorkModules'] ?? $module_data;
        if (!$module || ($module['KeyName'] ?? null) !== $module_id) {
            return $this->addError("KeyName mismatch: file contains '{$module['KeyName']}' but expected '{$module_id}'");
        }
        //endregion

        //region FETCH remote Menu Module and COMPARE with local backup
        $this->sendTerminal(" - Fetching remote Menu Module for comparison...");
        $remote_response = $this->core->request->get_json_decode(
            "{$this->api_base_url}/core/cfo/cfi/CloudFrameWorkModules/display/" . urlencode($module_id) . '?_raw&_timezone=UTC',
            ['_raw' => 1, '_timezone' => 'UTC'],
            $this->headers
        );

        if (!$this->core->request->error && ($remote_response['data'] ?? null)) {
            $remote_module = $remote_response['data'];
            ksort($remote_module);
            ksort($module);

            $remote_json = $this->core->jsonEncode($remote_module, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
            $local_json = $this->core->jsonEncode($module, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);

            if ($remote_json === $local_json) {
                $this->sendTerminal(" = Menu Module [{$module_id}] is unchanged (local backup equals remote)");
                return true;
            }
        }
        //endregion

        //region UPDATE Menu Module in remote platform via API
        $this->sendTerminal(" - Updating Menu Module in remote platform...");
        $response = $this->core->request->put_json_decode(
            "{$this->api_base_url}/core/cfo/cfi/CloudFrameWorkModules/" . urlencode($module_id) . "?_raw&_timezone=UTC",
            $module,
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
        $this->sendTerminal(" + Menu Module record updated");
        //endregion

        //region SEND success message to terminal
        $this->sendTerminal(str_repeat('-', 50));
        $this->sendTerminal(" + Menu Module [{$module_id}] updated successfully in remote platform");
        //endregion

        //region GET Last version of the Menu Module
        $this->formParams['id'] = $module_id;
        $this->METHOD_backup_from_remote();
        //endregion

        return true;
    }

    /**
     * Insert new Menu Module in remote platform from local backup
     */
    public function METHOD_insert_from_backup()
    {
        //region VALIDATE $module_id (required parameter)
        $module_id = $this->formParams['id'] ?? null;
        if (!$module_id) {
            return $this->addError("Missing required parameter: id. Usage: _cloudia/menu/insert-from-backup?id=module-key");
        }
        $this->sendTerminal(" - Menu Module to insert: {$module_id}");
        //endregion

        //region SET $backup_file (path to local backup JSON file)
        $filename = $this->moduleIdToFilename($module_id);
        $backup_file = $this->core->system->root_path . "/buckets/backups/Menus/{$this->platform_id}/{$filename}";
        $this->sendTerminal(" - Backup file: /buckets/backups/Menus/{$this->platform_id}/{$filename}");
        //endregion

        //region VALIDATE $backup_file exists
        if (!is_file($backup_file)) {
            return $this->addError("Backup file not found: {$backup_file}");
        }
        //endregion

        //region READ $module_data from backup file
        $json_content = file_get_contents($backup_file);
        if ($json_content === false) {
            return $this->addError("Failed to read backup file: {$backup_file}");
        }
        $module_data = $this->core->jsonDecode($json_content);
        if ($module_data === null) {
            return $this->addError("Invalid JSON in backup file: {$backup_file}");
        }
        $this->sendTerminal(" - Menu Module data loaded successfully");
        //endregion

        //region VALIDATE $module_data has correct structure
        $module = $module_data['CloudFrameWorkModules'] ?? $module_data;
        if (!$module || ($module['KeyName'] ?? null) !== $module_id) {
            return $this->addError("KeyName mismatch: file contains '{$module['KeyName']}' but expected '{$module_id}'");
        }
        //endregion

        //region CHECK if Menu Module already exists in remote platform
        $check_response = $this->core->request->get_json_decode(
            "{$this->api_base_url}/core/cfo/cfi/CloudFrameWorkModules/" . urlencode($module_id) . '?_raw&_timezone=UTC',
            [],
            $this->headers
        );
        if (!$this->core->request->error && ($check_response['data'] ?? null)) {
            return $this->addError("Menu Module [{$module_id}] already exists in remote platform. Use update-from-backup instead.");
        }
        //endregion

        //region INSERT Menu Module in remote platform via API
        $this->sendTerminal(" - Inserting Menu Module in remote platform...");
        $response = $this->core->request->post_json_decode(
            "{$this->api_base_url}/core/cfo/cfi/CloudFrameWorkModules?_raw&_timezone=UTC",
            $module,
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
        $this->sendTerminal(" + Menu Module record inserted");
        //endregion

        //region SEND success message to terminal
        $this->sendTerminal(str_repeat('-', 50));
        $this->sendTerminal(" + Menu Module [{$module_id}] inserted successfully in remote platform");
        //endregion

        //region GET Last version of the Menu Module
        $this->formParams['id'] = $module_id;
        $this->METHOD_backup_from_remote();
        //endregion

        return true;
    }
}
