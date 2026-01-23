<?php
/**
 * WebApp Documentation Backup Script
 *
 * This script provides functionality to manage WebApp documentation by:
 * - Backing up WebApps from the remote platform to local storage
 * - Inserting new WebApps from local backup to the remote platform
 * - Updating existing WebApps from local backup to the remote platform
 * - Listing WebApps in remote and local storage
 *
 * The script operates on WebApp documentation stored in the `buckets/backups/WebApps/{platform}/` directory
 * and synchronizes them with the CloudFramework platform via REST API.
 *
 * Each WebApp backup file contains:
 * - CloudFrameWorkDevDocumentationForWebApps: Main WebApp documentation
 * - CloudFrameWorkDevDocumentationForWebAppsModules: Individual module documentation
 *
 * Usage:
 *   _cloudia/webapps/backup-from-remote                              - Backup all WebApps from remote
 *   _cloudia/webapps/backup-from-remote?id=/verticals/hrms/init      - Backup specific WebApp
 *   _cloudia/webapps/insert-from-backup?id=/verticals/hrms/init      - Insert new WebApp to remote
 *   _cloudia/webapps/update-from-backup?id=/verticals/hrms/init      - Update existing WebApp in remote
 *   _cloudia/webapps/list-remote                                     - List all WebApps in remote
 *   _cloudia/webapps/list-local                                      - List all WebApps in local backup
 *
 * @author CloudFramework Development Team
 * @version 1.0
 */
class Script extends CoreScripts
{
    /** @var string Platform ID for WebApp operations */
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
            'X-WEB-KEY' => '/scripts/_cloudia/webapps',
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
        $this->sendTerminal("  /backup-from-remote          - Backup all WebApps from remote platform");
        $this->sendTerminal("  /backup-from-remote?id=/path - Backup specific WebApp from remote platform");
        $this->sendTerminal("  /insert-from-backup?id=/path - Insert new WebApp in remote platform from local backup");
        $this->sendTerminal("  /update-from-backup?id=/path - Update existing WebApp in remote platform from local backup");
        $this->sendTerminal("  /list-remote                 - List all WebApps in remote platform");
        $this->sendTerminal("  /list-local                  - List all WebApps in local backup");
        $this->sendTerminal("");
        $this->sendTerminal("Examples:");
        $this->sendTerminal("  composer run-script script \"_cloudia/webapps/backup-from-remote?id=/verticals/hrms/init-employee-hiring\"");
        $this->sendTerminal("  composer run-script script _cloudia/webapps/list-remote");
    }

    /**
     * Convert WebApp KeyName to backup filename
     *
     * @param string $webapp_id WebApp KeyName (e.g., /verticals/hrms/init)
     * @return string Filename (e.g., _verticals_hrms_init.json)
     */
    private function webappIdToFilename($webapp_id)
    {
        // Remove leading slash and replace remaining slashes with underscores
        $name = ltrim($webapp_id, '/');
        $name = str_replace(['/', '\\', ':', '*', '?', '"', '<', '>', '|'], '_', $name);
        return '_' . $name . '.json';
    }

    /**
     * Convert backup filename to WebApp KeyName
     *
     * @param string $filename Filename (e.g., _verticals_hrms_init.json)
     * @return string WebApp KeyName (e.g., /verticals/hrms/init)
     */
    private function filenameToWebappId($filename)
    {
        // Remove .json extension and leading underscore
        $name = basename($filename, '.json');
        $name = ltrim($name, '_');
        // Replace underscores back to slashes (this is an approximation)
        return '/' . str_replace('_', '/', $name);
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
        if (($backup_dir .= '/WebApps') && !is_dir($backup_dir) && !mkdir($backup_dir)) {
            $this->addError("Backup directory [/buckets/backups/WebApps] can not be created");
            return false;
        }
        if (($backup_dir .= '/' . $this->platform_id) && !is_dir($backup_dir) && !mkdir($backup_dir)) {
            $this->addError("Backup directory [/buckets/backups/WebApps/{$this->platform_id}] can not be created");
            return false;
        }
        return $backup_dir;
    }

    /**
     * List all WebApps in remote platform
     */
    public function METHOD_list_remote()
    {
        //region FETCH all WebApps from remote platform
        $this->sendTerminal("Listing WebApps in remote platform [{$this->platform_id}]:");
        $this->sendTerminal(str_repeat('-', 60));

        $params = ['_fields' => 'KeyName,Title,Type,Status', '_order' => 'KeyName', '_limit' => 1000];
        $response = $this->core->request->get_json_decode(
            "{$this->api_base_url}/core/cfo/cfi/CloudFrameWorkDevDocumentationForWebApps?_raw=1&_timezone=UTC",
            $params,
            $this->headers
        );

        if ($this->core->request->error) {
            return $this->addError($this->core->request->errorMsg);
        }

        $webapps = $response['data'] ?? [];
        if (!$webapps) {
            $this->sendTerminal("No WebApps found in remote platform");
            return true;
        }

        foreach ($webapps as $webapp) {
            $status = $webapp['Status'] ?? 'N/A';
            $title = $webapp['Title'] ?? 'N/A';
            $type = $webapp['Type'] ?? 'N/A';
            $this->sendTerminal(" {$webapp['KeyName']} - {$title} [{$type}] [{$status}]");
        }
        $this->sendTerminal(str_repeat('-', 60));
        $this->sendTerminal("Total: " . count($webapps) . " WebApps");
        //endregion

        return true;
    }

    /**
     * List all WebApps in local backup
     */
    public function METHOD_list_local()
    {
        //region LIST all WebApps in local backup
        $this->sendTerminal("Listing WebApps in local backup [{$this->platform_id}]:");
        $this->sendTerminal(str_repeat('-', 60));

        $directory_path = $this->core->system->root_path . '/buckets/backups/WebApps/' . $this->platform_id;

        if (!is_dir($directory_path)) {
            $this->sendTerminal("Backup directory not found: /buckets/backups/WebApps/{$this->platform_id}");
            return true;
        }

        $files = glob($directory_path . '/*.json');
        if (!$files) {
            $this->sendTerminal("No WebApp backup files found");
            return true;
        }

        foreach ($files as $file) {
            $data = $this->core->jsonDecode(file_get_contents($file));
            $webappData = $data['CloudFrameWorkDevDocumentationForWebApps'] ?? [];
            $keyName = $webappData['KeyName'] ?? basename($file, '.json');
            $title = $webappData['Title'] ?? 'N/A';
            $status = $webappData['Status'] ?? 'N/A';
            $type = $webappData['Type'] ?? 'N/A';
            $this->sendTerminal(" {$keyName} - {$title} [{$type}] [{$status}]");
        }
        $this->sendTerminal(str_repeat('-', 60));
        $this->sendTerminal("Total: " . count($files) . " WebApps");
        //endregion

        return true;
    }

    /**
     * Backup WebApps from remote platform to local storage
     */
    public function METHOD_backup_from_remote()
    {
        //region VERIFY backup directory
        if (!$backup_dir = $this->getBackupDir()) {
            return false;
        }
        $this->sendTerminal(" - Backup directory: /buckets/backups/WebApps/{$this->platform_id}");
        //endregion

        //region SET $webapp_id (specific WebApp to backup, or null for all)
        $webapp_id = $this->formParams['id'] ?? null;
        if ($webapp_id && strpos($webapp_id, '/') !== 0) {
            $webapp_id = '/' . $webapp_id;
        }
        //endregion

        //region READ WebApps from remote API
        $webapps = [];
        $all_modules = [];
        if ($webapp_id) {
            //region FETCH single WebApp by KeyName
            $this->sendTerminal(" - Fetching WebApp: {$webapp_id}");
            $response = $this->core->request->get_json_decode(
                "{$this->api_base_url}/core/cfo/cfi/CloudFrameWorkDevDocumentationForWebApps/display/" . urlencode($webapp_id) . '?_raw&_timezone=UTC',
                ['_raw' => 1, '_timezone' => 'UTC'],
                $this->headers
            );
            if ($this->core->request->error) {
                return $this->addError($this->core->request->errorMsg);
            }
            if (!($response['data'] ?? null)) {
                return $this->addError("WebApp [{$webapp_id}] not found in remote platform");
            }
            $webapps = [$response['data']];
            //endregion

            //region READ modules associated
            $this->sendTerminal(" - Fetching modules for WebApp... [max 2000]");
            $all_modules = $this->core->request->get_json_decode(
                "{$this->api_base_url}/core/cfo/cfi/CloudFrameWorkDevDocumentationForWebAppsModules?_raw&_timezone=UTC",
                ['filter_WebApp' => $webapp_id, 'cfo_limit' => 2000, '_raw' => 1, '_timezone' => 'UTC'],
                $this->headers
            );
            if ($this->core->request->error) {
                return $this->addError($this->core->request->errorMsg);
            }
            //endregion

            //region VERIFY all modules have the correct WebApp
            $modules_data = $all_modules['data'] ?? [];
            if (count($modules_data) > 0) {
                $invalid_modules = array_filter($modules_data, function($m) use ($webapp_id) {
                    return ($m['WebApp'] ?? '') !== $webapp_id;
                });
                if ($invalid_modules) {
                    $invalid_keys = array_map(function($m) { return $m['KeyId'] ?? $m['KeyName'] ?? 'unknown'; }, $invalid_modules);
                    return $this->addError("Found " . count($invalid_modules) . " modules with incorrect WebApp (expected '{$webapp_id}'): " . implode(', ', $invalid_keys));
                }
            }
            //endregion

        } else {
            //region FETCH all WebApps
            $this->sendTerminal(" - Fetching all WebApps... [max 2000]");
            $response = $this->core->request->get_json_decode(
                "{$this->api_base_url}/core/cfo/cfi/CloudFrameWorkDevDocumentationForWebApps?_raw&_timezone=UTC",
                ['cfo_limit' => 2000, '_raw' => 1, '_timezone' => 'UTC'],
                $this->headers
            );
            if ($this->core->request->error) {
                return $this->addError($this->core->request->errorMsg);
            }
            $webapps = $response['data'] ?? [];
            //endregion

            //region READ all modules
            $this->sendTerminal(" - Fetching all Modules... [max 2000]");
            $all_modules = $this->core->request->get_json_decode(
                "{$this->api_base_url}/core/cfo/cfi/CloudFrameWorkDevDocumentationForWebAppsModules?_raw&_timezone=UTC",
                ['cfo_limit' => 2000, '_raw' => 1, '_timezone' => 'UTC'],
                $this->headers
            );
            if ($this->core->request->error) {
                return $this->addError($this->core->request->errorMsg);
            }
            //endregion
        }
        $tot_webapps = count($webapps);
        $modules_data = $all_modules['data'] ?? [];
        $tot_modules = count($modules_data);

        $this->sendTerminal(" - WebApps/Modules to backup: {$tot_webapps}/{$tot_modules}");
        $all_modules = $this->core->utils->convertArrayIndexedByColumn($modules_data, 'WebApp', true);
        //endregion

        //region PROCESS and SAVE each WebApp to backup directory
        $saved_count = 0;
        $unchanged_count = 0;

        foreach ($webapps as $webapp) {
            //region VALIDATE WebApp has KeyName
            if (!($webapp['KeyName'] ?? null)) {
                $this->sendTerminal("   # Skipping WebApp without KeyName");
                continue;
            }
            $key_name = $webapp['KeyName'];
            //endregion

            //region FETCH modules for this WebApp using KeyName
            $modules_response = [];
            if (isset($all_modules[$key_name])) {
                $modules_response = ['data' => &$all_modules[$key_name]];
            }

            $modules = [];
            if (!$this->core->request->error && ($modules_response['data'] ?? null)) {
                $modules = $modules_response['data'];
                // Sort modules by KeyId
                foreach ($modules as &$module) {
                    ksort($module);
                }
                usort($modules, function ($a, $b) {
                    return strcmp($a['KeyId'] ?? '', $b['KeyId'] ?? '');
                });
            }
            //endregion

            //region SORT $webapp keys alphabetically
            ksort($webapp);
            //endregion

            //region BUILD $webapp_data structure
            $webapp_data = [
                'CloudFrameWorkDevDocumentationForWebApps' => $webapp,
                'CloudFrameWorkDevDocumentationForWebAppsModules' => $modules
            ];
            //endregion

            //region SAVE $webapp_data to JSON file (if changed)
            $filename = $this->webappIdToFilename($key_name);
            $filepath = "{$backup_dir}/{$filename}";
            $json_content = $this->core->jsonEncode($webapp_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

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
                return $this->addError("Failed to write WebApp [{$key_name}] to file");
            }
            $saved_count++;
            $module_count = count($modules);
            $this->sendTerminal("   + Saved: {$filename} ({$module_count} modules)");
            //endregion
        }
        //endregion

        //region SEND summary to terminal
        $this->sendTerminal(str_repeat('-', 50));
        $this->sendTerminal(" - Total WebApps/Modules: {$tot_webapps}/{$tot_modules} (saved: {$saved_count}, unchanged: {$unchanged_count})");
        //endregion

        return true;
    }

    /**
     * Update existing WebApp in remote platform from local backup
     */
    public function METHOD_update_from_backup()
    {
        //region VALIDATE $webapp_id (required parameter)
        $webapp_id = $this->formParams['id'] ?? null;
        if (!$webapp_id) {
            return $this->addError("Missing required parameter: id. Usage: _cloudia/webapps/update-from-backup?id=/path/to/webapp");
        }
        if (strpos($webapp_id, '/') !== 0) {
            $webapp_id = '/' . $webapp_id;
        }
        $this->sendTerminal(" - WebApp to update: {$webapp_id}");
        //endregion

        //region SET $backup_file (path to local backup JSON file)
        $filename = $this->webappIdToFilename($webapp_id);
        $backup_file = $this->core->system->root_path . "/buckets/backups/WebApps/{$this->platform_id}/{$filename}";
        $this->sendTerminal(" - Backup file: /buckets/backups/WebApps/{$this->platform_id}/{$filename}");
        //endregion

        //region VALIDATE $backup_file exists
        if (!is_file($backup_file)) {
            return $this->addError("Backup file not found: {$backup_file}");
        }
        //endregion

        //region READ $webapp_data from backup file
        $json_content = file_get_contents($backup_file);
        if ($json_content === false) {
            return $this->addError("Failed to read backup file: {$backup_file}");
        }
        $webapp_data = $this->core->jsonDecode($json_content);
        if ($webapp_data === null) {
            return $this->addError("Invalid JSON in backup file: {$backup_file}");
        }
        $this->sendTerminal(" - WebApp data loaded successfully");
        //endregion

        //region VALIDATE $webapp_data has correct structure
        $webapp = $webapp_data['CloudFrameWorkDevDocumentationForWebApps'] ?? null;
        if (!$webapp || ($webapp['KeyName'] ?? null) !== $webapp_id) {
            return $this->addError("KeyName mismatch: file contains '{$webapp['KeyName']}' but expected '{$webapp_id}'");
        }
        //endregion

        //region FETCH remote WebApp and COMPARE with local backup
        $this->sendTerminal(" - Fetching remote WebApp for comparison...");
        $remote_response = $this->core->request->get_json_decode(
            "{$this->api_base_url}/core/cfo/cfi/CloudFrameWorkDevDocumentationForWebApps/display/" . urlencode($webapp_id) . '?_raw&_timezone=UTC',
            ['_raw' => 1, '_timezone' => 'UTC'],
            $this->headers
        );

        if (!$this->core->request->error && ($remote_response['data'] ?? null)) {
            $remote_webapp = $remote_response['data'];
            ksort($remote_webapp);
            $local_webapp = $webapp;
            ksort($local_webapp);

            $remote_json = $this->core->jsonEncode($remote_webapp, JSON_UNESCAPED_SLASHES);
            $local_json = $this->core->jsonEncode($local_webapp, JSON_UNESCAPED_SLASHES);

            if ($remote_json === $local_json) {
                $this->sendTerminal(" = [CloudFrameWorkDevDocumentationForWebApps] is unchanged (local backup equals remote)");
                return true;
            }
        }
        //endregion

        //region UPDATE WebApp in remote platform via API
        $this->sendTerminal(" - Updating WebApp in remote platform...");
        $response = $this->core->request->put_json_decode(
            "{$this->api_base_url}/core/cfo/cfi/CloudFrameWorkDevDocumentationForWebApps/" . urlencode($webapp_id) . "?_raw&_timezone=UTC",
            $webapp,
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
        $this->sendTerminal(" + WebApp record updated");
        //endregion

        //region UPDATE modules in remote platform
        $modules = $webapp_data['CloudFrameWorkDevDocumentationForWebAppsModules'] ?? [];
        if ($modules) {
            $this->sendTerminal(" - Updating {" . count($modules) . "} modules...");
            foreach ($modules as $module) {
                $module_key = $module['KeyId'] ?? $module['KeyName'] ?? null;
                if (!$module_key) {
                    $response = $this->core->request->post_json_decode(
                        "{$this->api_base_url}/core/cfo/cfi/CloudFrameWorkDevDocumentationForWebAppsModules?_raw&_timezone=UTC",
                        $module,
                        $this->headers
                    );
                } else {
                    $response = $this->core->request->put_json_decode(
                        "{$this->api_base_url}/core/cfo/cfi/CloudFrameWorkDevDocumentationForWebAppsModules/{$module_key}?_raw&_timezone=UTC",
                        $module,
                        $this->headers,
                        true
                    );
                }

                if ($this->core->request->error || !($response['success'] ?? false)) {
                    $module_title = $module['Title'] ?? $module_key ?? 'unknown';
                    $this->sendTerminal("   # Warning: Failed to update module [{$module_title}]");
                    $this->sendTerminal($this->core->request->errorMsg);
                    return false;
                }
            }
            $this->sendTerminal(" + Modules updated");
        }
        //endregion

        //region SEND success message to terminal
        $this->sendTerminal(str_repeat('-', 50));
        $this->sendTerminal(" + WebApp [{$webapp_id}] updated successfully in remote platform");
        //endregion

        //region GET Last version of the WebApp
        $this->formParams['id'] = $webapp_id;
        $this->METHOD_backup_from_remote();
        //endregion

        return true;
    }

    /**
     * Insert new WebApp in remote platform from local backup
     */
    public function METHOD_insert_from_backup()
    {
        //region VALIDATE $webapp_id (required parameter)
        $webapp_id = $this->formParams['id'] ?? null;
        if (!$webapp_id) {
            return $this->addError("Missing required parameter: id. Usage: _cloudia/webapps/insert-from-backup?id=/path/to/webapp");
        }
        if (strpos($webapp_id, '/') !== 0) {
            $webapp_id = '/' . $webapp_id;
        }
        $this->sendTerminal(" - WebApp to insert: {$webapp_id}");
        //endregion

        //region SET $backup_file (path to local backup JSON file)
        $filename = $this->webappIdToFilename($webapp_id);
        $backup_file = $this->core->system->root_path . "/buckets/backups/WebApps/{$this->platform_id}/{$filename}";
        $this->sendTerminal(" - Backup file: /buckets/backups/WebApps/{$this->platform_id}/{$filename}");
        //endregion

        //region VALIDATE $backup_file exists
        if (!is_file($backup_file)) {
            return $this->addError("Backup file not found: {$backup_file}");
        }
        //endregion

        //region READ $webapp_data from backup file
        $json_content = file_get_contents($backup_file);
        if ($json_content === false) {
            return $this->addError("Failed to read backup file: {$backup_file}");
        }
        $webapp_data = $this->core->jsonDecode($json_content);
        if ($webapp_data === null) {
            return $this->addError("Invalid JSON in backup file: {$backup_file}");
        }
        $this->sendTerminal(" - WebApp data loaded successfully");
        //endregion

        //region VALIDATE $webapp_data has correct structure
        $webapp = $webapp_data['CloudFrameWorkDevDocumentationForWebApps'] ?? null;
        if (!$webapp || ($webapp['KeyName'] ?? null) !== $webapp_id) {
            return $this->addError("KeyName mismatch: file contains '{$webapp['KeyName']}' but expected '{$webapp_id}'");
        }
        //endregion

        //region CHECK if WebApp already exists in remote platform
        $check_response = $this->core->request->get_json_decode(
            "{$this->api_base_url}/core/cfo/cfi/CloudFrameWorkDevDocumentationForWebApps/" . urlencode($webapp_id) . '?_raw&_timezone=UTC',
            [],
            $this->headers
        );
        if (!$this->core->request->error && ($check_response['data'] ?? null)) {
            return $this->addError("WebApp [{$webapp_id}] already exists in remote platform. Use update-from-backup instead.");
        }
        //endregion

        //region INSERT WebApp in remote platform via API
        $this->sendTerminal(" - Inserting WebApp in remote platform...");
        $response = $this->core->request->post_json_decode(
            "{$this->api_base_url}/core/cfo/cfi/CloudFrameWorkDevDocumentationForWebApps?_raw&_timezone=UTC",
            $webapp,
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

        // Get the new KeyName from the response for module insertion
        $new_key_name = $response['data']['KeyName'] ?? $webapp['KeyName'] ?? null;
        $this->sendTerminal(" + WebApp record inserted (KeyName: {$new_key_name})");
        //endregion

        //region INSERT modules in remote platform
        $modules = $webapp_data['CloudFrameWorkDevDocumentationForWebAppsModules'] ?? [];
        if ($modules) {
            $this->sendTerminal(" - Inserting {" . count($modules) . "} modules...");
            foreach ($modules as $module) {
                $module_key = $module['KeyId'] ?? $module['KeyName'] ?? null;
                if (!$module_key) {
                    $response = $this->core->request->post_json_decode(
                        "{$this->api_base_url}/core/cfo/cfi/CloudFrameWorkDevDocumentationForWebAppsModules?_raw&_timezone=UTC",
                        $module,
                        $this->headers
                    );
                } else {
                    $response = $this->core->request->put_json_decode(
                        "{$this->api_base_url}/core/cfo/cfi/CloudFrameWorkDevDocumentationForWebAppsModules/{$module_key}?_raw&_timezone=UTC",
                        $module,
                        $this->headers,
                        true
                    );
                }

                if ($this->core->request->error || !($response['success'] ?? false)) {
                    $module_title = $module['Title'] ?? $module_key ?? 'unknown';
                    $this->sendTerminal("   # Warning: Failed to insert module [{$module_title}]");
                    $this->sendTerminal($this->core->request->errorMsg);
                    return false;
                }
            }
            $this->sendTerminal(" + Modules inserted");
        }
        //endregion

        //region SEND success message to terminal
        $this->sendTerminal(str_repeat('-', 50));
        $this->sendTerminal(" + WebApp [{$webapp_id}] inserted successfully in remote platform");
        //endregion

        //region GET Last version of the WebApp
        $this->formParams['id'] = $webapp_id;
        $this->METHOD_backup_from_remote();
        //endregion

        return true;
    }
}
