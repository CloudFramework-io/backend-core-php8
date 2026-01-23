<?php
/**
 * API Documentation Backup Script
 *
 * This script provides functionality to manage API documentation by:
 * - Backing up APIs from the remote platform to local storage
 * - Inserting new APIs from local backup to the remote platform
 * - Updating existing APIs from local backup to the remote platform
 * - Listing APIs in remote and local storage
 *
 * The script operates on API documentation stored in the `buckets/backups/APIs/{platform}/` directory
 * and synchronizes them with the CloudFramework platform via REST API.
 *
 * Each API backup file contains:
 * - CloudFrameWorkDevDocumentationForAPIs: Main API documentation
 * - CloudFrameWorkDevDocumentationForAPIEndPoints: Individual endpoint documentation
 *
 * Usage:
 *   _cloudia/apis/backup-from-remote           - Backup all APIs from remote
 *   _cloudia/apis/backup-from-remote?id=/path  - Backup specific API
 *   _cloudia/apis/insert-from-backup?id=/path  - Insert new API to remote
 *   _cloudia/apis/update-from-backup?id=/path  - Update existing API in remote
 *   _cloudia/apis/list-remote                  - List all APIs in remote
 *   _cloudia/apis/list-local                   - List all APIs in local backup
 *
 * @author CloudFramework Development Team
 * @version 2.1
 */
class Script extends CoreScripts
{
    /** @var string Platform ID for API operations */
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
            'X-WEB-KEY' => '/scripts/_cloudia/apis',
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
        $this->sendTerminal("  /backup-from-remote        - Backup all APIs from remote platform");
        $this->sendTerminal("  /backup-from-remote?id=/x  - Backup specific API from remote platform");
        $this->sendTerminal("  /insert-from-backup?id=/x  - Insert new API in remote platform from local backup");
        $this->sendTerminal("  /update-from-backup?id=/x  - Update existing API in remote platform from local backup");
        $this->sendTerminal("  /list-remote               - List all APIs in remote platform");
        $this->sendTerminal("  /list-local                - List all APIs in local backup");
        $this->sendTerminal("");
        $this->sendTerminal("Examples:");
        $this->sendTerminal("  composer run-script script \"_cloudia/apis/backup-from-remote?id=/erp/projects\"");
        $this->sendTerminal("  composer run-script script _cloudia/apis/list-remote");
    }

    /**
     * Convert API KeyName to backup filename
     *
     * @param string $api_id API KeyName (e.g., /erp/projects)
     * @return string Filename (e.g., _erp_projects.json)
     */
    private function apiIdToFilename($api_id)
    {
        // Remove leading slash and replace special characters with underscores
        $name = ltrim($api_id, '/');
        $name = str_replace(['/', '\\', ':', '*', '?', '"', '<', '>', '|'], '_', $name);
        return '_' . $name . '.json';
    }

    /**
     * Convert backup filename to API KeyName
     *
     * @param string $filename Filename (e.g., _erp_projects.json)
     * @return string API KeyName (e.g., /erp/projects)
     */
    private function filenameToApiId($filename)
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
        if (($backup_dir .= '/APIs') && !is_dir($backup_dir) && !mkdir($backup_dir)) {
            $this->addError("Backup directory [/buckets/backups/APIs] can not be created");
            return false;
        }
        if (($backup_dir .= '/' . $this->platform_id) && !is_dir($backup_dir) && !mkdir($backup_dir)) {
            $this->addError("Backup directory [/buckets/backups/APIs/{$this->platform_id}] can not be created");
            return false;
        }
        return $backup_dir;
    }

    /**
     * List all APIs in remote platform
     */
    public function METHOD_list_remote()
    {
        //region FETCH all APIs from remote platform
        $this->sendTerminal("Listing APIs in remote platform [{$this->platform_id}]:");
        $this->sendTerminal(str_repeat('-', 60));

        $params = ['_fields' => 'KeyName,Title,Status', '_order' => 'KeyName', '_limit' => 1000];
        $response = $this->core->request->get_json_decode(
            "{$this->api_base_url}/core/cfo/cfi/CloudFrameWorkDevDocumentationForAPIs?_raw=1&_timezone=UTC",
            $params,
            $this->headers
        );

        if ($this->core->request->error) {
            return $this->addError($this->core->request->errorMsg);
        }

        $apis = $response['data'] ?? [];
        if (!$apis) {
            $this->sendTerminal("No APIs found in remote platform");
            return true;
        }

        foreach ($apis as $api) {
            $status = $api['Status'] ?? 'N/A';
            $title = $api['Title'] ?? 'N/A';
            $this->sendTerminal(" {$api['KeyName']} - {$title} [{$status}]");
        }
        $this->sendTerminal(str_repeat('-', 60));
        $this->sendTerminal("Total: " . count($apis) . " APIs");
        //endregion

        return true;
    }

    /**
     * List all APIs in local backup
     */
    public function METHOD_list_local()
    {
        //region LIST all APIs in local backup
        $this->sendTerminal("Listing APIs in local backup [{$this->platform_id}]:");
        $this->sendTerminal(str_repeat('-', 60));

        $directory_path = $this->core->system->root_path . '/buckets/backups/APIs/' . $this->platform_id;

        if (!is_dir($directory_path)) {
            $this->sendTerminal("Backup directory not found: /buckets/backups/APIs/{$this->platform_id}");
            return true;
        }

        $files = glob($directory_path . '/*.json');
        if (!$files) {
            $this->sendTerminal("No API backup files found");
            return true;
        }

        foreach ($files as $file) {
            $data = $this->core->jsonDecode(file_get_contents($file));
            $apiData = $data['CloudFrameWorkDevDocumentationForAPIs'] ?? [];
            $keyName = $apiData['KeyName'] ?? basename($file, '.json');
            $title = $apiData['Title'] ?? 'N/A';
            $status = $apiData['Status'] ?? 'N/A';
            $this->sendTerminal(" {$keyName} - {$title} [{$status}]");
        }
        $this->sendTerminal(str_repeat('-', 60));
        $this->sendTerminal("Total: " . count($files) . " APIs");
        //endregion

        return true;
    }

    /**
     * Backup APIs from remote platform to local storage
     */
    public function METHOD_backup_from_remote()
    {
        //region VERIFY backup directory
        if (!$backup_dir = $this->getBackupDir()) {
            return false;
        }
        $this->sendTerminal(" - Backup directory: /buckets/backups/APIs/{$this->platform_id}");
        //endregion

        //region SET $api_id (specific API to backup, or null for all)
        $api_id = $this->formParams['id'] ?? null;
        if ($api_id && strpos($api_id, '/') !== 0) {
            $api_id = '/' . $api_id;
        }
        //endregion

        //region READ APIs from remote API
        $apis = [];
        $all_endpoints = [];
        if ($api_id) {
            //region FETCH single API by KeyName
            $this->sendTerminal(" - Fetching API: {$api_id}");
            $response = $this->core->request->get_json_decode(
                "{$this->api_base_url}/core/cfo/cfi/CloudFrameWorkDevDocumentationForAPIs/display/" . urlencode($api_id) . '?_raw&_timezone=UTC',
                ['_raw' => 1, '_timezone' => 'UTC'],
                $this->headers
            );
            if ($this->core->request->error) {
                return $this->addError($this->core->request->errorMsg);
            }
            if (!($response['data'] ?? null)) {
                return $this->addError("API [{$api_id}] not found in remote platform");
            }
            $apis = [$response['data']];
            //endregion

            //region READ endpoints associated
            $this->sendTerminal(" - Fetching endpoints for API... [max 2000]");
            $all_endpoints = $this->core->request->get_json_decode(
                "{$this->api_base_url}/core/cfo/cfi/CloudFrameWorkDevDocumentationForAPIEndPoints?_raw&_timezone=UTC",
                ['filter_API' => $api_id, 'cfo_limit' => 2000, '_raw' => 1, '_timezone' => 'UTC'],
                $this->headers
            );
            if ($this->core->request->error) {
                return $this->addError($this->core->request->errorMsg);
            }
            //endregion

        } else {
            //region FETCH all APIs
            $this->sendTerminal(" - Fetching all APIs... [max 2000]");
            $response = $this->core->request->get_json_decode(
                "{$this->api_base_url}/core/cfo/cfi/CloudFrameWorkDevDocumentationForAPIs?_raw&_timezone=UTC",
                ['cfo_limit' => 2000, '_raw' => 1, '_timezone' => 'UTC'],
                $this->headers
            );
            if ($this->core->request->error) {
                return $this->addError($this->core->request->errorMsg);
            }
            $apis = $response['data'] ?? [];
            //endregion

            //region READ all endpoints
            $this->sendTerminal(" - Fetching all Endpoints... [max 2000]");
            $all_endpoints = $this->core->request->get_json_decode(
                "{$this->api_base_url}/core/cfo/cfi/CloudFrameWorkDevDocumentationForAPIEndPoints?_raw&_timezone=UTC",
                ['cfo_limit' => 2000, '_raw' => 1, '_timezone' => 'UTC'],
                $this->headers
            );
            if ($this->core->request->error) {
                return $this->addError($this->core->request->errorMsg);
            }
            //endregion
        }
        $tot_apis = count($apis);
        $endpoints_data = $all_endpoints['data'] ?? [];
        $tot_endpoints = count($endpoints_data);

        $this->sendTerminal(" - APIs/Endpoints to backup: {$tot_apis}/{$tot_endpoints}");
        $all_endpoints = $this->core->utils->convertArrayIndexedByColumn($endpoints_data, 'API', true);
        //endregion

        //region PROCESS and SAVE each API to backup directory
        $saved_count = 0;
        $unchanged_count = 0;

        foreach ($apis as $api) {
            //region VALIDATE API has KeyName
            if (!($api['KeyName'] ?? null)) {
                $this->sendTerminal("   # Skipping API without KeyName");
                continue;
            }
            $key_name = $api['KeyName'];
            //endregion

            //region FETCH endpoints for this API using KeyName
            $endpoints_response = [];
            if (isset($all_endpoints[$key_name])) {
                $endpoints_response = ['data' => &$all_endpoints[$key_name]];
            }

            $endpoints = [];
            if (!$this->core->request->error && ($endpoints_response['data'] ?? null)) {
                $endpoints = $endpoints_response['data'];
                // Sort endpoints by KeyName
                foreach ($endpoints as &$endpoint) {
                    ksort($endpoint);
                }
                usort($endpoints, function ($a, $b) {
                    return strcmp($a['KeyName'] ?? '', $b['KeyName'] ?? '');
                });
            }
            //endregion

            //region SORT $api keys alphabetically
            ksort($api);
            //endregion

            //region BUILD $api_data structure
            $api_data = [
                'CloudFrameWorkDevDocumentationForAPIs' => $api,
                'CloudFrameWorkDevDocumentationForAPIEndPoints' => $endpoints
            ];
            //endregion

            //region SAVE $api_data to JSON file (skip if unchanged)
            $filename = $this->apiIdToFilename($key_name);
            $filepath = "{$backup_dir}/{$filename}";
            $json_content = $this->core->jsonEncode($api_data, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);

            // Compare with existing file before saving
            if (is_file($filepath)) {
                $existing_content = file_get_contents($filepath);
                if ($existing_content === $json_content) {
                    $unchanged_count++;
                    $this->sendTerminal("   = Unchanged: {$filename}");
                    continue;
                }
            }

            if (file_put_contents($filepath, $json_content) === false) {
                return $this->addError("Failed to write API [{$key_name}] to file");
            }
            $saved_count++;
            $endpoint_count = count($endpoints);
            $this->sendTerminal("   + Saved: {$filename} ({$endpoint_count} endpoints)");
            //endregion
        }
        //endregion

        //region SEND summary to terminal
        $this->sendTerminal(str_repeat('-', 50));
        $this->sendTerminal(" - Total APIs/Endpoints: {$tot_apis}/{$tot_endpoints} (saved: {$saved_count}, unchanged: {$unchanged_count})");
        //endregion

        return true;
    }

    /**
     * Update existing API in remote platform from local backup
     */
    public function METHOD_update_from_backup()
    {
        //region VALIDATE $api_id (required parameter)
        $api_id = $this->formParams['id'] ?? null;
        if (!$api_id) {
            return $this->addError("Missing required parameter: id. Usage: _cloudia/apis/update-from-backup?id=/path/to/api");
        }
        if (strpos($api_id, '/') !== 0) {
            $api_id = '/' . $api_id;
        }
        $this->sendTerminal(" - API to update: {$api_id}");
        //endregion

        //region SET $backup_file (path to local backup JSON file)
        $filename = $this->apiIdToFilename($api_id);
        $backup_file = $this->core->system->root_path . "/buckets/backups/APIs/{$this->platform_id}/{$filename}";
        $this->sendTerminal(" - Backup file: /buckets/backups/APIs/{$this->platform_id}/{$filename}");
        //endregion

        //region VALIDATE $backup_file exists
        if (!is_file($backup_file)) {
            return $this->addError("Backup file not found: {$backup_file}");
        }
        //endregion

        //region READ $api_data from backup file
        $json_content = file_get_contents($backup_file);
        if ($json_content === false) {
            return $this->addError("Failed to read backup file: {$backup_file}");
        }
        $api_data = $this->core->jsonDecode($json_content);
        if ($api_data === null) {
            return $this->addError("Invalid JSON in backup file: {$backup_file}");
        }
        $this->sendTerminal(" - API data loaded successfully");
        //endregion

        //region VALIDATE $api_data has correct structure
        $api = $api_data['CloudFrameWorkDevDocumentationForAPIs'] ?? null;
        if (!$api || ($api['KeyName'] ?? null) !== $api_id) {
            return $this->addError("KeyName mismatch: file contains '{$api['KeyName']}' but expected '{$api_id}'");
        }
        //endregion

        //region CHECK if remote data is unchanged (compare with local backup)
        $this->sendTerminal(" - Fetching remote API for comparison...");
        $remote_response = $this->core->request->get_json_decode(
            "{$this->api_base_url}/core/cfo/cfi/CloudFrameWorkDevDocumentationForAPIs/display/" . urlencode($api_id) . '?_raw&_timezone=UTC',
            ['_raw' => 1, '_timezone' => 'UTC'],
            $this->headers
        );
        if (!$this->core->request->error && ($remote_response['data'] ?? null)) {
            $remote_api = $remote_response['data'];
            ksort($remote_api);
            ksort($api);
            if ($this->core->jsonEncode($remote_api) === $this->core->jsonEncode($api)) {
                // Also check endpoints
                $remote_endpoints_response = $this->core->request->get_json_decode(
                    "{$this->api_base_url}/core/cfo/cfi/CloudFrameWorkDevDocumentationForAPIEndPoints?_raw&_timezone=UTC",
                    ['filter_API' => $api_id, 'cfo_limit' => 2000, '_raw' => 1, '_timezone' => 'UTC'],
                    $this->headers
                );
                $remote_endpoints = $remote_endpoints_response['data'] ?? [];
                $local_endpoints = $api_data['CloudFrameWorkDevDocumentationForAPIEndPoints'] ?? [];

                // Sort both for comparison
                foreach ($remote_endpoints as &$ep) { ksort($ep); }
                foreach ($local_endpoints as &$ep) { ksort($ep); }
                usort($remote_endpoints, function ($a, $b) { return strcmp($a['KeyName'] ?? '', $b['KeyName'] ?? ''); });
                usort($local_endpoints, function ($a, $b) { return strcmp($a['KeyName'] ?? '', $b['KeyName'] ?? ''); });

                if ($this->core->jsonEncode($remote_endpoints) === $this->core->jsonEncode($local_endpoints)) {
                    $this->sendTerminal(" = API [{$api_id}] is unchanged (local backup equals remote)");
                    return true;
                }
            }
        }
        //endregion

        //region UPDATE API in remote platform via API
        $this->sendTerminal(" - Updating API in remote platform...");
        $response = $this->core->request->put_json_decode(
            "{$this->api_base_url}/core/cfo/cfi/CloudFrameWorkDevDocumentationForAPIs/" . urlencode($api_id) . "?_raw&_timezone=UTC",
            $api,
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
        $this->sendTerminal(" + API record updated");
        //endregion

        //region UPDATE endpoints in remote platform
        $endpoints = $api_data['CloudFrameWorkDevDocumentationForAPIEndPoints'] ?? [];
        if ($endpoints) {
            $this->sendTerminal(" - Updating {" . count($endpoints) . "} endpoints...");
            foreach ($endpoints as $endpoint) {
                $endpoint_key = $endpoint['KeyId'] ?? $endpoint['KeyName'] ?? null;
                if (!$endpoint_key) {
                    $response = $this->core->request->post_json_decode(
                        "{$this->api_base_url}/core/cfo/cfi/CloudFrameWorkDevDocumentationForAPIEndPoints?_raw&_timezone=UTC",
                        $endpoint,
                        $this->headers
                    );
                } else {
                    $response = $this->core->request->put_json_decode(
                        "{$this->api_base_url}/core/cfo/cfi/CloudFrameWorkDevDocumentationForAPIEndPoints/{$endpoint_key}?_raw&_timezone=UTC",
                        $endpoint,
                        $this->headers
                    );
                }

                if ($this->core->request->error || !($response['success'] ?? false)) {
                    $endpoint_title = $endpoint['EndPoint'] ?? $endpoint_key ?? 'unknown';
                    $this->sendTerminal("   # Warning: Failed to update endpoint [{$endpoint_title}]");
                    $this->sendTerminal($this->core->request->errorMsg);
                    return false;
                }
            }
            $this->sendTerminal(" + Endpoints updated");
        }
        //endregion

        //region SEND success message to terminal
        $this->sendTerminal(str_repeat('-', 50));
        $this->sendTerminal(" + API [{$api_id}] updated successfully in remote platform");
        //endregion

        //region GET Last version of the API
        $this->formParams['id'] = $api_id;
        $this->METHOD_backup_from_remote();
        //endregion

        return true;
    }

    /**
     * Insert new API in remote platform from local backup
     */
    public function METHOD_insert_from_backup()
    {
        //region VALIDATE $api_id (required parameter)
        $api_id = $this->formParams['id'] ?? null;
        if (!$api_id) {
            return $this->addError("Missing required parameter: id. Usage: _cloudia/apis/insert-from-backup?id=/path/to/api");
        }
        if (strpos($api_id, '/') !== 0) {
            $api_id = '/' . $api_id;
        }
        $this->sendTerminal(" - API to insert: {$api_id}");
        //endregion

        //region SET $backup_file (path to local backup JSON file)
        $filename = $this->apiIdToFilename($api_id);
        $backup_file = $this->core->system->root_path . "/buckets/backups/APIs/{$this->platform_id}/{$filename}";
        $this->sendTerminal(" - Backup file: /buckets/backups/APIs/{$this->platform_id}/{$filename}");
        //endregion

        //region VALIDATE $backup_file exists
        if (!is_file($backup_file)) {
            return $this->addError("Backup file not found: {$backup_file}");
        }
        //endregion

        //region READ $api_data from backup file
        $json_content = file_get_contents($backup_file);
        if ($json_content === false) {
            return $this->addError("Failed to read backup file: {$backup_file}");
        }
        $api_data = $this->core->jsonDecode($json_content);
        if ($api_data === null) {
            return $this->addError("Invalid JSON in backup file: {$backup_file}");
        }
        $this->sendTerminal(" - API data loaded successfully");
        //endregion

        //region VALIDATE $api_data has correct structure
        $api = $api_data['CloudFrameWorkDevDocumentationForAPIs'] ?? null;
        if (!$api || ($api['KeyName'] ?? null) !== $api_id) {
            return $this->addError("KeyName mismatch: file contains '{$api['KeyName']}' but expected '{$api_id}'");
        }
        //endregion

        //region CHECK if API already exists in remote platform
        $check_response = $this->core->request->get_json_decode(
            "{$this->api_base_url}/core/cfo/cfi/CloudFrameWorkDevDocumentationForAPIs/" . urlencode($api_id).'?_raw&_timezone=UTC',
            [],
            $this->headers
        );
        if (!$this->core->request->error && ($check_response['data'] ?? null)) {
            return $this->addError("API [{$api_id}] already exists in remote platform. Use update-from-backup instead.");
        }
        //endregion

        //region INSERT API in remote platform via API
        $this->sendTerminal(" - Inserting API in remote platform...");
        $response = $this->core->request->post_json_decode(
            "{$this->api_base_url}/core/cfo/cfi/CloudFrameWorkDevDocumentationForAPIs?_raw&_timezone=UTC",
            $api,
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
        $this->sendTerminal(" + API record inserted");
        //endregion

        //region INSERT endpoints in remote platform
        $endpoints = $api_data['CloudFrameWorkDevDocumentationForAPIEndPoints'] ?? [];
        if ($endpoints) {
            $this->sendTerminal(" - Inserting {" . count($endpoints) . "} endpoints...");
            foreach ($endpoints as $endpoint) {
                $endpoint_key = $endpoint['KeyId'] ?? $endpoint['KeyName'] ?? null;
                if (!$endpoint_key) {
                    $response = $this->core->request->post_json_decode(
                        "{$this->api_base_url}/core/cfo/cfi/CloudFrameWorkDevDocumentationForAPIEndPoints?_raw&_timezone=UTC",
                        $endpoint,
                        $this->headers
                    );
                } else {
                    $response = $this->core->request->put_json_decode(
                        "{$this->api_base_url}/core/cfo/cfi/CloudFrameWorkDevDocumentationForAPIEndPoints/{$endpoint_key}?_raw&_timezone=UTC",
                        $endpoint,
                        $this->headers
                    );
                }

                if ($this->core->request->error || !($response['success'] ?? false)) {
                    $endpoint_title = $endpoint['EndPoint'] ?? $endpoint_key ?? 'unknown';
                    $this->sendTerminal("   # Warning: Failed to insert endpoint [{$endpoint_title}]");
                    $this->sendTerminal($this->core->request->errorMsg);
                    return false;
                }
            }
            $this->sendTerminal(" + Endpoints inserted");
        }
        //endregion

        //region SEND success message to terminal
        $this->sendTerminal(str_repeat('-', 50));
        $this->sendTerminal(" + API [{$api_id}] inserted successfully in remote platform");
        //endregion

        //region GET Last version of the API
        $this->formParams['id'] = $api_id;
        $this->METHOD_backup_from_remote();
        //endregion

        return true;
    }
}
