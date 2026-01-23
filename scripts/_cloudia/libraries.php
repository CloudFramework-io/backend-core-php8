<?php
/**
 * Library Documentation Backup Script
 *
 * This script provides functionality to manage Library documentation by:
 * - Backing up Libraries from the remote platform to local storage
 * - Inserting new Libraries from local backup to the remote platform
 * - Updating existing Libraries from local backup to the remote platform
 * - Listing Libraries in remote and local storage
 *
 * The script operates on Library documentation stored in the `buckets/backups/Libraries/{platform}/` directory
 * and synchronizes them with the CloudFramework platform via REST API.
 *
 * Each Library backup file contains:
 * - CloudFrameWorkDevDocumentationForLibraries: Main Library documentation (class, module info)
 * - CloudFrameWorkDevDocumentationForLibrariesModules: Individual function/method documentation
 *
 * Usage:
 *   _cloudia/libraries/backup-from-remote           - Backup all Libraries from remote
 *   _cloudia/libraries/backup-from-remote?id=/path  - Backup specific Library
 *   _cloudia/libraries/insert-from-backup?id=/path  - Insert new Library to remote
 *   _cloudia/libraries/update-from-backup?id=/path  - Update existing Library in remote
 *   _cloudia/libraries/list-remote                  - List all Libraries in remote
 *   _cloudia/libraries/list-local                   - List all Libraries in local backup
 *
 * @author CloudFramework Development Team
 * @version 2.1
 */
class Script extends CoreScripts
{
    /** @var string Platform ID for Library operations */
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
            'X-WEB-KEY' => '/scripts/_cloudia/libraries',
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
        $this->sendTerminal("  /backup-from-remote        - Backup all Libraries from remote platform");
        $this->sendTerminal("  /backup-from-remote?id=/x  - Backup specific Library from remote platform");
        $this->sendTerminal("  /insert-from-backup?id=/x  - Insert new Library in remote platform from local backup");
        $this->sendTerminal("  /update-from-backup?id=/x  - Update existing Library in remote platform from local backup");
        $this->sendTerminal("  /list-remote               - List all Libraries in remote platform");
        $this->sendTerminal("  /list-local                - List all Libraries in local backup");
        $this->sendTerminal("");
        $this->sendTerminal("Examples:");
        $this->sendTerminal("  composer run-script script \"_cloudia/libraries/backup-from-remote?id=/api-dev/class/CloudAI\"");
        $this->sendTerminal("  composer run-script script _cloudia/libraries/list-remote");
    }

    /**
     * Convert Library KeyName to backup filename
     *
     * @param string $library_id Library KeyName (e.g., /api-dev/class/CloudAI)
     * @return string Filename (e.g., _api-dev_class_CloudAI.json)
     */
    private function libraryIdToFilename($library_id)
    {
        // Remove leading slash and replace special characters with underscores
        $name = ltrim($library_id, '/');
        $name = str_replace(['/', '\\', ':', '*', '?', '"', '<', '>', '|'], '_', $name);
        return '_' . $name . '.json';
    }

    /**
     * Convert backup filename to Library KeyName
     *
     * @param string $filename Filename (e.g., _api-dev_class_CloudAI.json)
     * @return string Library KeyName (e.g., /api-dev/class/CloudAI)
     */
    private function filenameToLibraryId($filename)
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
        if (($backup_dir .= '/Libraries') && !is_dir($backup_dir) && !mkdir($backup_dir)) {
            $this->addError("Backup directory [/buckets/backups/Libraries] can not be created");
            return false;
        }
        if (($backup_dir .= '/' . $this->platform_id) && !is_dir($backup_dir) && !mkdir($backup_dir)) {
            $this->addError("Backup directory [/buckets/backups/Libraries/{$this->platform_id}] can not be created");
            return false;
        }
        return $backup_dir;
    }

    /**
     * List all Libraries in remote platform
     */
    public function METHOD_list_remote()
    {
        //region FETCH all Libraries from remote platform
        $this->sendTerminal("Listing Libraries in remote platform [{$this->platform_id}]:");
        $this->sendTerminal(str_repeat('-', 60));

        $params = ['_fields' => 'KeyName,Title,Status,Type', '_order' => 'KeyName', '_limit' => 1000];
        $response = $this->core->request->get_json_decode(
            "{$this->api_base_url}/core/cfo/cfi/CloudFrameWorkDevDocumentationForLibraries?_raw=1&_timezone=UTC",
            $params,
            $this->headers
        );

        if ($this->core->request->error) {
            return $this->addError($this->core->request->errorMsg);
        }

        $libraries = $response['data'] ?? [];
        if (!$libraries) {
            $this->sendTerminal("No Libraries found in remote platform");
            return true;
        }

        foreach ($libraries as $library) {
            $status = $library['Status'] ?? 'N/A';
            $title = $library['Title'] ?? 'N/A';
            $type = $library['Type'] ?? 'N/A';
            $this->sendTerminal(" {$library['KeyName']} - {$title} [{$type}] [{$status}]");
        }
        $this->sendTerminal(str_repeat('-', 60));
        $this->sendTerminal("Total: " . count($libraries) . " Libraries");
        //endregion

        return true;
    }

    /**
     * List all Libraries in local backup
     */
    public function METHOD_list_local()
    {
        //region LIST all Libraries in local backup
        $this->sendTerminal("Listing Libraries in local backup [{$this->platform_id}]:");
        $this->sendTerminal(str_repeat('-', 60));

        $directory_path = $this->core->system->root_path . '/buckets/backups/Libraries/' . $this->platform_id;

        if (!is_dir($directory_path)) {
            $this->sendTerminal("Backup directory not found: /buckets/backups/Libraries/{$this->platform_id}");
            return true;
        }

        $files = glob($directory_path . '/*.json');
        if (!$files) {
            $this->sendTerminal("No Library backup files found");
            return true;
        }

        foreach ($files as $file) {
            $data = $this->core->jsonDecode(file_get_contents($file));
            $libraryData = $data['CloudFrameWorkDevDocumentationForLibraries'] ?? [];
            $keyName = $libraryData['KeyName'] ?? basename($file, '.json');
            $title = $libraryData['Title'] ?? 'N/A';
            $status = $libraryData['Status'] ?? 'N/A';
            $type = $libraryData['Type'] ?? 'N/A';
            $this->sendTerminal(" {$keyName} - {$title} [{$type}] [{$status}]");
        }
        $this->sendTerminal(str_repeat('-', 60));
        $this->sendTerminal("Total: " . count($files) . " Libraries");
        //endregion

        return true;
    }

    /**
     * Backup Libraries from remote platform to local storage
     */
    public function METHOD_backup_from_remote()
    {
        //region VERIFY backup directory
        if (!$backup_dir = $this->getBackupDir()) {
            return false;
        }
        $this->sendTerminal(" - Backup directory: /buckets/backups/Libraries/{$this->platform_id}");
        //endregion

        //region SET $library_id (specific Library to backup, or null for all)
        $library_id = $this->formParams['id'] ?? null;
        if ($library_id && strpos($library_id, '/') !== 0) {
            $library_id = '/' . $library_id;
        }
        //endregion

        //region READ Libraries from remote API
        $libraries = [];
        $all_modules = [];
        if ($library_id) {
            //region FETCH single Library by KeyName
            $this->sendTerminal(" - Fetching Library: {$library_id}");
            $response = $this->core->request->get_json_decode(
                "{$this->api_base_url}/core/cfo/cfi/CloudFrameWorkDevDocumentationForLibraries/display/" . urlencode($library_id) . '?_raw&_timezone=UTC',
                ['_raw' => 1, '_timezone' => 'UTC'],
                $this->headers
            );
            if ($this->core->request->error) {
                return $this->addError($this->core->request->errorMsg);
            }
            if (!($response['data'] ?? null)) {
                return $this->addError("Library [{$library_id}] not found in remote platform");
            }
            $libraries = [$response['data']];
            //endregion

            //region READ modules associated
            $this->sendTerminal(" - Fetching modules for Library... [max 2000]");
            $all_modules = $this->core->request->get_json_decode(
                "{$this->api_base_url}/core/cfo/cfi/CloudFrameWorkDevDocumentationForLibrariesModules?_raw&_timezone=UTC",
                ['filter_Library' => $library_id, 'cfo_limit' => 2000, '_raw' => 1, '_timezone' => 'UTC'],
                $this->headers
            );
            if ($this->core->request->error) {
                return $this->addError($this->core->request->errorMsg);
            }
            //endregion

        } else {
            //region FETCH all Libraries
            $this->sendTerminal(" - Fetching all Libraries... [max 2000]");
            $response = $this->core->request->get_json_decode(
                "{$this->api_base_url}/core/cfo/cfi/CloudFrameWorkDevDocumentationForLibraries?_raw&_timezone=UTC",
                ['cfo_limit' => 2000, '_raw' => 1, '_timezone' => 'UTC'],
                $this->headers
            );
            if ($this->core->request->error) {
                return $this->addError($this->core->request->errorMsg);
            }
            $libraries = $response['data'] ?? [];
            //endregion

            //region READ all modules
            $this->sendTerminal(" - Fetching all Modules... [max 2000]");
            $all_modules = $this->core->request->get_json_decode(
                "{$this->api_base_url}/core/cfo/cfi/CloudFrameWorkDevDocumentationForLibrariesModules?_raw&_timezone=UTC",
                ['cfo_limit' => 2000, '_raw' => 1, '_timezone' => 'UTC'],
                $this->headers
            );
            if ($this->core->request->error) {
                return $this->addError($this->core->request->errorMsg);
            }
            //endregion
        }
        $tot_libraries = count($libraries);
        $modules_data = $all_modules['data'] ?? [];
        $tot_modules = count($modules_data);

        $this->sendTerminal(" - Libraries/Modules to backup: {$tot_libraries}/{$tot_modules}");
        $all_modules = $this->core->utils->convertArrayIndexedByColumn($modules_data, 'Library', true);
        //endregion

        //region PROCESS and SAVE each Library to backup directory
        $saved_count = 0;
        $unchanged_count = 0;

        foreach ($libraries as $library) {
            //region VALIDATE Library has KeyName
            if (!($library['KeyName'] ?? null)) {
                $this->sendTerminal("   # Skipping Library without KeyName");
                continue;
            }
            $key_name = $library['KeyName'];
            //endregion

            //region FETCH modules for this Library using KeyName
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

            //region SORT $library keys alphabetically
            ksort($library);
            //endregion

            //region BUILD $library_data structure
            $library_data = [
                'CloudFrameWorkDevDocumentationForLibraries' => $library,
                'CloudFrameWorkDevDocumentationForLibrariesModules' => $modules
            ];
            //endregion

            //region SAVE $library_data to JSON file (only if changed)
            $filename = $this->libraryIdToFilename($key_name);
            $filepath = "{$backup_dir}/{$filename}";
            $json_content = $this->core->jsonEncode($library_data, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);

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
                return $this->addError("Failed to write Library [{$key_name}] to file");
            }
            $saved_count++;
            $module_count = count($modules);
            $this->sendTerminal("   + Saved: {$filename} ({$module_count} modules)");
            //endregion
        }
        //endregion

        //region SEND summary to terminal
        $this->sendTerminal(str_repeat('-', 50));
        $this->sendTerminal(" - Total Libraries/Modules processed: {$tot_libraries}/{$tot_modules} (saved: {$saved_count}, unchanged: {$unchanged_count})");
        //endregion

        return true;
    }

    /**
     * Update existing Library in remote platform from local backup
     */
    public function METHOD_update_from_backup()
    {
        //region VALIDATE $library_id (required parameter)
        $library_id = $this->formParams['id'] ?? null;
        if (!$library_id) {
            return $this->addError("Missing required parameter: id. Usage: _cloudia/libraries/update-from-backup?id=/path/to/library");
        }
        if (strpos($library_id, '/') !== 0) {
            $library_id = '/' . $library_id;
        }
        $this->sendTerminal(" - Library to update: {$library_id}");
        //endregion

        //region SET $backup_file (path to local backup JSON file)
        $filename = $this->libraryIdToFilename($library_id);
        $backup_file = $this->core->system->root_path . "/buckets/backups/Libraries/{$this->platform_id}/{$filename}";
        $this->sendTerminal(" - Backup file: /buckets/backups/Libraries/{$this->platform_id}/{$filename}");
        //endregion

        //region VALIDATE $backup_file exists
        if (!is_file($backup_file)) {
            return $this->addError("Backup file not found: {$backup_file}");
        }
        //endregion

        //region READ $library_data from backup file
        $json_content = file_get_contents($backup_file);
        if ($json_content === false) {
            return $this->addError("Failed to read backup file: {$backup_file}");
        }
        $library_data = $this->core->jsonDecode($json_content);
        if ($library_data === null) {
            return $this->addError("Invalid JSON in backup file: {$backup_file}");
        }
        $this->sendTerminal(" - Library data loaded successfully");
        //endregion

        //region VALIDATE $library_data has correct structure
        $library = $library_data['CloudFrameWorkDevDocumentationForLibraries'] ?? null;
        if (!$library || ($library['KeyName'] ?? null) !== $library_id) {
            return $this->addError("KeyName mismatch: file contains '{$library['KeyName']}' but expected '{$library_id}'");
        }
        //endregion

        //region FETCH remote Library data and compare with local backup
        $this->sendTerminal(" - Fetching remote Library data for comparison...");
        $remote_response = $this->core->request->get_json_decode(
            "{$this->api_base_url}/core/cfo/cfi/CloudFrameWorkDevDocumentationForLibraries/display/" . urlencode($library_id) . "?_raw&_timezone=UTC",
            ['_raw' => 1, '_timezone' => 'UTC'],
            $this->headers
        );

        if (!$this->core->request->error && ($remote_response['data'] ?? null)) {
            $remote_library = $remote_response['data'];
            ksort($remote_library);
            ksort($library);

            $remote_json = $this->core->jsonEncode($remote_library, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
            $local_json = $this->core->jsonEncode($library, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);

            if ($remote_json === $local_json) {
                $this->sendTerminal(" = [CloudFrameWorkDevDocumentationForLibraries] is unchanged (local backup equals remote)");
                return true;
            }
        }
        //endregion

        //region UPDATE Library in remote platform via API
        $this->sendTerminal(" - Updating Library in remote platform...");
        $response = $this->core->request->put_json_decode(
            "{$this->api_base_url}/core/cfo/cfi/CloudFrameWorkDevDocumentationForLibraries/" . urlencode($library_id) . "?_raw&_timezone=UTC",
            $library,
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
        $this->sendTerminal(" + Library record updated");
        //endregion

        //region UPDATE modules in remote platform
        $modules = $library_data['CloudFrameWorkDevDocumentationForLibrariesModules'] ?? [];
        if ($modules) {
            $this->sendTerminal(" - Updating {" . count($modules) . "} modules...");
            foreach ($modules as $module) {
                $module_key = $module['KeyId'] ?? $module['KeyName'] ?? null;
                if (!$module_key) {
                    $response = $this->core->request->post_json_decode(
                        "{$this->api_base_url}/core/cfo/cfi/CloudFrameWorkDevDocumentationForLibrariesModules?_raw&_timezone=UTC",
                        $module,
                        $this->headers
                    );
                } else {
                    $response = $this->core->request->put_json_decode(
                        "{$this->api_base_url}/core/cfo/cfi/CloudFrameWorkDevDocumentationForLibrariesModules/{$module_key}?_raw&_timezone=UTC",
                        $module,
                        $this->headers
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
        $this->sendTerminal(" + Library [{$library_id}] updated successfully in remote platform");
        //endregion

        //region GET Last version of the Library
        $this->formParams['id'] = $library_id;
        $this->METHOD_backup_from_remote();
        //endregion

        return true;
    }

    /**
     * Insert new Library in remote platform from local backup
     */
    public function METHOD_insert_from_backup()
    {
        //region VALIDATE $library_id (required parameter)
        $library_id = $this->formParams['id'] ?? null;
        if (!$library_id) {
            return $this->addError("Missing required parameter: id. Usage: _cloudia/libraries/insert-from-backup?id=/path/to/library");
        }
        if (strpos($library_id, '/') !== 0) {
            $library_id = '/' . $library_id;
        }
        $this->sendTerminal(" - Library to insert: {$library_id}");
        //endregion

        //region SET $backup_file (path to local backup JSON file)
        $filename = $this->libraryIdToFilename($library_id);
        $backup_file = $this->core->system->root_path . "/buckets/backups/Libraries/{$this->platform_id}/{$filename}";
        $this->sendTerminal(" - Backup file: /buckets/backups/Libraries/{$this->platform_id}/{$filename}");
        //endregion

        //region VALIDATE $backup_file exists
        if (!is_file($backup_file)) {
            return $this->addError("Backup file not found: {$backup_file}");
        }
        //endregion

        //region READ $library_data from backup file
        $json_content = file_get_contents($backup_file);
        if ($json_content === false) {
            return $this->addError("Failed to read backup file: {$backup_file}");
        }
        $library_data = $this->core->jsonDecode($json_content);
        if ($library_data === null) {
            return $this->addError("Invalid JSON in backup file: {$backup_file}");
        }
        $this->sendTerminal(" - Library data loaded successfully");
        //endregion

        //region VALIDATE $library_data has correct structure
        $library = $library_data['CloudFrameWorkDevDocumentationForLibraries'] ?? null;
        if (!$library || ($library['KeyName'] ?? null) !== $library_id) {
            return $this->addError("KeyName mismatch: file contains '{$library['KeyName']}' but expected '{$library_id}'");
        }
        //endregion

        //region CHECK if Library already exists in remote platform
        $check_response = $this->core->request->get_json_decode(
            "{$this->api_base_url}/core/cfo/cfi/CloudFrameWorkDevDocumentationForLibraries/" . urlencode($library_id).'?_raw&_timezone=UTC',
            [],
            $this->headers
        );
        if (!$this->core->request->error && ($check_response['data'] ?? null)) {
            return $this->addError("Library [{$library_id}] already exists in remote platform. Use update-from-backup instead.");
        }
        //endregion

        //region INSERT Library in remote platform via API
        $this->sendTerminal(" - Inserting Library in remote platform...");
        $response = $this->core->request->post_json_decode(
            "{$this->api_base_url}/core/cfo/cfi/CloudFrameWorkDevDocumentationForLibraries?_raw&_timezone=UTC",
            $library,
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
        $this->sendTerminal(" + Library record inserted");
        //endregion

        //region INSERT modules in remote platform
        $modules = $library_data['CloudFrameWorkDevDocumentationForLibrariesModules'] ?? [];
        if ($modules) {
            $this->sendTerminal(" - Inserting {" . count($modules) . "} modules...");
            foreach ($modules as $module) {
                $module_key = $module['KeyId'] ?? $module['KeyName'] ?? null;
                if (!$module_key) {
                    $response = $this->core->request->post_json_decode(
                        "{$this->api_base_url}/core/cfo/cfi/CloudFrameWorkDevDocumentationForLibrariesModules?_raw&_timezone=UTC",
                        $module,
                        $this->headers
                    );
                } else {
                    $response = $this->core->request->put_json_decode(
                        "{$this->api_base_url}/core/cfo/cfi/CloudFrameWorkDevDocumentationForLibrariesModules/{$module_key}?_raw&_timezone=UTC",
                        $module,
                        $this->headers
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
        $this->sendTerminal(" + Library [{$library_id}] inserted successfully in remote platform");
        //endregion

        //region GET Last version of the Library
        $this->formParams['id'] = $library_id;
        $this->METHOD_backup_from_remote();
        //endregion

        return true;
    }
}
