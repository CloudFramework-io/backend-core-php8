<?php
/**
 * WebPage (ECM Pages) Backup Script
 *
 * This script provides functionality to manage CloudFrameWorkECMPages (WebPages) by:
 * - Backing up WebPages from the remote platform to local storage
 * - Inserting new WebPages from local backup to the remote platform
 * - Updating existing WebPages from local backup to the remote platform
 * - Listing WebPages in remote and local storage
 *
 * The script operates on WebPage documentation stored in the `buckets/backups/WebPages/{platform}/` directory
 * and synchronizes them with the CloudFramework platform via REST API.
 *
 * Each WebPage backup file contains:
 * - CloudFrameWorkECMPages: Main WebPage content and configuration
 *
 * Usage:
 *   _cloudia/webpages/backup-from-remote           - Backup all WebPages from remote
 *   _cloudia/webpages/backup-from-remote?id=/path  - Backup specific WebPage
 *   _cloudia/webpages/insert-from-backup?id=/path  - Insert new WebPage to remote
 *   _cloudia/webpages/update-from-backup?id=/path  - Update existing WebPage in remote
 *   _cloudia/webpages/list-remote                  - List all WebPages in remote
 *   _cloudia/webpages/list-local                   - List all WebPages in local backup
 *
 * @author CloudFramework Development Team
 * @version 2.1
 */
class Script extends CoreScripts
{
    /** @var string Platform ID for WebPage operations */
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
        if (!$this->core->user->hasAnyPrivilege('development-admin,development-user,ecm-admin,ecm-user')) {
            return $this->addError('You do not have permission [development-admin,ecm-admin] to execute this script');
        }
        //endregion

        //region SET AUTHENTICATE header for API call
        $this->headers = [
            'X-WEB-KEY' => '/scripts/_cloudia/webpages',
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
        $this->sendTerminal("  /backup-from-remote        - Backup all WebPages from remote platform");
        $this->sendTerminal("  /backup-from-remote?id=/x  - Backup specific WebPage from remote platform");
        $this->sendTerminal("  /insert-from-backup?id=/x  - Insert new WebPage in remote platform from local backup");
        $this->sendTerminal("  /update-from-backup?id=/x  - Update existing WebPage in remote platform from local backup");
        $this->sendTerminal("  /list-remote               - List all WebPages in remote platform");
        $this->sendTerminal("  /list-local                - List all WebPages in local backup");
        $this->sendTerminal("");
        $this->sendTerminal("Examples:");
        $this->sendTerminal("  composer run-script script \"_cloudia/webpages/backup-from-remote?id=/training/cfos/cfi/views/conditional_rows_background_color\"");
        $this->sendTerminal("  composer run-script script _cloudia/webpages/list-remote");
    }

    /**
     * Convert PageRoute to backup filename
     *
     * @param string $page_route PageRoute (e.g., /training/cfos/cfi/views/example)
     * @return string Filename (e.g., _training_cfos_cfi_views_example.json)
     */
    private function pageRouteToFilename($page_route)
    {
        // Replace slashes and special characters with underscores
        $name = preg_replace('/[^A-Za-z0-9_-]/', '_', $page_route);
        return $name . '.json';
    }

    /**
     * Convert backup filename to PageRoute
     *
     * @param string $filename Filename (e.g., _training_cfos_cfi_views_example.json)
     * @return string PageRoute (e.g., /training/cfos/cfi/views/example)
     */
    private function filenameToPageRoute($filename)
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
        if (($backup_dir .= '/WebPages') && !is_dir($backup_dir) && !mkdir($backup_dir)) {
            $this->addError("Backup directory [/buckets/backups/WebPages] can not be created");
            return false;
        }
        if (($backup_dir .= '/' . $this->platform_id) && !is_dir($backup_dir) && !mkdir($backup_dir)) {
            $this->addError("Backup directory [/buckets/backups/WebPages/{$this->platform_id}] can not be created");
            return false;
        }
        return $backup_dir;
    }

    /**
     * List all WebPages in remote platform
     */
    public function METHOD_list_remote()
    {
        //region FETCH all WebPages from remote platform
        $this->sendTerminal("Listing WebPages in remote platform [{$this->platform_id}]:");
        $this->sendTerminal(str_repeat('-', 60));

        $params = ['_fields' => 'KeyId,PageRoute,PageTitle,Status', '_order' => 'PageRoute', '_limit' => 1000];
        $response = $this->core->request->get_json_decode(
            "{$this->api_base_url}/core/cfo/cfi/CloudFrameWorkECMPages?_raw=1&_timezone=UTC",
            $params,
            $this->headers
        );

        if ($this->core->request->error) {
            return $this->addError($this->core->request->errorMsg);
        }

        $webpages = $response['data'] ?? [];
        if (!$webpages) {
            $this->sendTerminal("No WebPages found in remote platform");
            return true;
        }

        foreach ($webpages as $webpage) {
            $pageRoute = $webpage['PageRoute'] ?? 'N/A';
            $title = $webpage['PageTitle'] ?? 'N/A';
            $status = $webpage['Status'] ?? 'N/A';
            $this->sendTerminal(" {$pageRoute} - {$title} [{$status}]");
        }
        $this->sendTerminal(str_repeat('-', 60));
        $this->sendTerminal("Total: " . count($webpages) . " WebPages");
        //endregion

        return true;
    }

    /**
     * List all WebPages in local backup
     */
    public function METHOD_list_local()
    {
        //region LIST all WebPages in local backup
        $this->sendTerminal("Listing WebPages in local backup [{$this->platform_id}]:");
        $this->sendTerminal(str_repeat('-', 60));

        $directory_path = $this->core->system->root_path . '/buckets/backups/WebPages/' . $this->platform_id;

        if (!is_dir($directory_path)) {
            $this->sendTerminal("Backup directory not found: /buckets/backups/WebPages/{$this->platform_id}");
            return true;
        }

        $files = glob($directory_path . '/*.json');
        if (!$files) {
            $this->sendTerminal("No WebPage backup files found");
            return true;
        }

        foreach ($files as $file) {
            $data = $this->core->jsonDecode(file_get_contents($file));
            $webpageData = $data['CloudFrameWorkECMPages'] ?? [];
            $pageRoute = $webpageData['PageRoute'] ?? basename($file, '.json');
            $title = $webpageData['PageTitle'] ?? 'N/A';
            $status = $webpageData['Status'] ?? 'N/A';
            $this->sendTerminal(" {$pageRoute} - {$title} [{$status}]");
        }
        $this->sendTerminal(str_repeat('-', 60));
        $this->sendTerminal("Total: " . count($files) . " WebPages");
        //endregion

        return true;
    }

    /**
     * Backup WebPages from remote platform to local storage
     */
    public function METHOD_backup_from_remote()
    {
        //region VERIFY backup directory
        if (!$backup_dir = $this->getBackupDir()) {
            return false;
        }
        $this->sendTerminal(" - Backup directory: /buckets/backups/WebPages/{$this->platform_id}");
        //endregion

        //region SET $page_id (specific WebPage to backup, or null for all)
        $page_id = $this->formParams['id'] ?? null;
        if ($page_id && strpos($page_id, '/') !== 0) {
            $page_id = '/' . $page_id;
        }
        //endregion

        //region READ WebPages from remote API
        $webpages = [];
        if ($page_id) {
            //region FETCH single WebPage by PageRoute
            $this->sendTerminal(" - Fetching WebPage: {$page_id}");
            $response = $this->core->request->get_json_decode(
                "{$this->api_base_url}/core/cfo/cfi/CloudFrameWorkECMPages?_raw&_timezone=UTC",
                ['filter_PageRoute' => $page_id, '_raw' => 1, '_timezone' => 'UTC'],
                $this->headers
            );
            if ($this->core->request->error) {
                return $this->addError($this->core->request->errorMsg);
            }
            if (!($response['data'] ?? null) || count($response['data']) == 0) {
                return $this->addError("WebPage [{$page_id}] not found in remote platform");
            }
            $webpages = $response['data'];
            //endregion

        } else {
            //region FETCH all WebPages
            $this->sendTerminal(" - Fetching all WebPages... [max 2000]");
            $response = $this->core->request->get_json_decode(
                "{$this->api_base_url}/core/cfo/cfi/CloudFrameWorkECMPages?_raw&_timezone=UTC",
                ['cfo_limit' => 2000, '_raw' => 1, '_timezone' => 'UTC'],
                $this->headers
            );
            if ($this->core->request->error) {
                return $this->addError($this->core->request->errorMsg);
            }
            $webpages = $response['data'] ?? [];
            //endregion
        }
        $tot_webpages = count($webpages);

        $this->sendTerminal(" - WebPages to backup: {$tot_webpages}");
        //endregion

        //region PROCESS and SAVE each WebPage to backup directory
        $saved_count = 0;
        $unchanged_count = 0;

        foreach ($webpages as $webpage) {
            //region VALIDATE WebPage has PageRoute
            if (!($webpage['PageRoute'] ?? null)) {
                $this->sendTerminal("   # Skipping WebPage without PageRoute");
                continue;
            }
            $page_route = $webpage['PageRoute'];
            //endregion

            //region SORT $webpage keys alphabetically
            ksort($webpage);
            //endregion

            //region BUILD $webpage_data structure
            $webpage_data = [
                'CloudFrameWorkECMPages' => $webpage
            ];
            //endregion

            //region SAVE $webpage_data to JSON file (if changed)
            $filename = $this->pageRouteToFilename($page_route);
            $filepath = "{$backup_dir}/{$filename}";
            $json_content = $this->core->jsonEncode($webpage_data, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);

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
                return $this->addError("Failed to write WebPage [{$page_route}] to file");
            }
            $saved_count++;
            $this->sendTerminal("   + Saved: {$filename}");
            //endregion
        }
        //endregion

        //region SEND summary to terminal
        $this->sendTerminal(str_repeat('-', 50));
        $this->sendTerminal(" - Total WebPages processed: {$tot_webpages} (saved: {$saved_count}, unchanged: {$unchanged_count})");
        //endregion

        return true;
    }

    /**
     * Update existing WebPage in remote platform from local backup
     */
    public function METHOD_update_from_backup()
    {
        //region VALIDATE $page_id (required parameter)
        $page_id = $this->formParams['id'] ?? null;
        if (!$page_id) {
            return $this->addError("Missing required parameter: id. Usage: _cloudia/webpages/update-from-backup?id=/page/route");
        }
        if (strpos($page_id, '/') !== 0) {
            $page_id = '/' . $page_id;
        }
        $this->sendTerminal(" - WebPage to update: {$page_id}");
        //endregion

        //region SET $backup_file (path to local backup JSON file)
        $filename = $this->pageRouteToFilename($page_id);
        $backup_file = $this->core->system->root_path . "/buckets/backups/WebPages/{$this->platform_id}/{$filename}";
        $this->sendTerminal(" - Backup file: /buckets/backups/WebPages/{$this->platform_id}/{$filename}");
        //endregion

        //region VALIDATE $backup_file exists
        if (!is_file($backup_file)) {
            return $this->addError("Backup file not found: {$backup_file}");
        }
        //endregion

        //region READ $webpage_data from backup file
        $json_content = file_get_contents($backup_file);
        if ($json_content === false) {
            return $this->addError("Failed to read backup file: {$backup_file}");
        }
        $webpage_data = $this->core->jsonDecode($json_content);
        if ($webpage_data === null) {
            return $this->addError("Invalid JSON in backup file: {$backup_file}");
        }
        $this->sendTerminal(" - WebPage data loaded successfully");
        //endregion

        //region VALIDATE $webpage_data has correct structure
        $webpage = $webpage_data['CloudFrameWorkECMPages'] ?? null;
        if (!$webpage || ($webpage['PageRoute'] ?? null) !== $page_id) {
            return $this->addError("PageRoute mismatch: file contains '{$webpage['PageRoute']}' but expected '{$page_id}'");
        }
        //endregion

        //region GET $key_id from WebPage data for PUT request
        $key_id = $webpage['KeyId'] ?? null;
        if (!$key_id) {
            return $this->addError("KeyId not found in WebPage data. Cannot update.");
        }
        //endregion

        //region FETCH remote data and COMPARE with local backup
        $this->sendTerminal(" - Fetching remote WebPage to compare...");
        $remote_response = $this->core->request->get_json_decode(
            "{$this->api_base_url}/core/cfo/cfi/CloudFrameWorkECMPages/{$key_id}?_raw&_timezone=UTC",
            [],
            $this->headers
        );

        if (!$this->core->request->error && ($remote_response['data'] ?? null)) {
            $remote_webpage = $remote_response['data'];
            ksort($remote_webpage);
            ksort($webpage);
            $remote_json = $this->core->jsonEncode($remote_webpage, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
            $local_json = $this->core->jsonEncode($webpage, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
            if ($remote_json === $local_json) {
                $this->sendTerminal(" = [CloudFrameWorkECMPages] is unchanged (local backup equals remote)");
                return true;
            }
        }
        //endregion

        //region UPDATE WebPage in remote platform via API
        $this->sendTerminal(" - Updating WebPage in remote platform...");
        $response = $this->core->request->put_json_decode(
            "{$this->api_base_url}/core/cfo/cfi/CloudFrameWorkECMPages/{$key_id}?_raw&_timezone=UTC",
            $webpage,
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
        $this->sendTerminal(" + WebPage record updated");
        //endregion

        //region SEND success message to terminal
        $this->sendTerminal(str_repeat('-', 50));
        $this->sendTerminal(" + WebPage [{$page_id}] updated successfully in remote platform");
        //endregion

        //region GET Last version of the WebPage
        $this->formParams['id'] = $page_id;
        $this->METHOD_backup_from_remote();
        //endregion

        return true;
    }

    /**
     * Insert new WebPage in remote platform from local backup
     */
    public function METHOD_insert_from_backup()
    {
        //region VALIDATE $page_id (required parameter)
        $page_id = $this->formParams['id'] ?? null;
        if (!$page_id) {
            return $this->addError("Missing required parameter: id. Usage: _cloudia/webpages/insert-from-backup?id=/page/route");
        }
        if (strpos($page_id, '/') !== 0) {
            $page_id = '/' . $page_id;
        }
        $this->sendTerminal(" - WebPage to insert: {$page_id}");
        //endregion

        //region SET $backup_file (path to local backup JSON file)
        $filename = $this->pageRouteToFilename($page_id);
        $backup_file = $this->core->system->root_path . "/buckets/backups/WebPages/{$this->platform_id}/{$filename}";
        $this->sendTerminal(" - Backup file: /buckets/backups/WebPages/{$this->platform_id}/{$filename}");
        //endregion

        //region VALIDATE $backup_file exists
        if (!is_file($backup_file)) {
            return $this->addError("Backup file not found: {$backup_file}");
        }
        //endregion

        //region READ $webpage_data from backup file
        $json_content = file_get_contents($backup_file);
        if ($json_content === false) {
            return $this->addError("Failed to read backup file: {$backup_file}");
        }
        $webpage_data = $this->core->jsonDecode($json_content);
        if ($webpage_data === null) {
            return $this->addError("Invalid JSON in backup file: {$backup_file}");
        }
        $this->sendTerminal(" - WebPage data loaded successfully");
        //endregion

        //region VALIDATE $webpage_data has correct structure
        $webpage = $webpage_data['CloudFrameWorkECMPages'] ?? null;
        if (!$webpage || ($webpage['PageRoute'] ?? null) !== $page_id) {
            return $this->addError("PageRoute mismatch: file contains '{$webpage['PageRoute']}' but expected '{$page_id}'");
        }
        //endregion

        //region CHECK if WebPage already exists in remote platform
        $check_response = $this->core->request->get_json_decode(
            "{$this->api_base_url}/core/cfo/cfi/CloudFrameWorkECMPages?_raw&_timezone=UTC",
            ['filter_PageRoute' => $page_id],
            $this->headers
        );
        if (!$this->core->request->error && ($check_response['data'] ?? null) && count($check_response['data']) > 0) {
            return $this->addError("WebPage [{$page_id}] already exists in remote platform. Use update-from-backup instead.");
        }
        //endregion

        //region REMOVE KeyId for insert (will be auto-generated)
        unset($webpage['KeyId']);
        //endregion

        //region INSERT WebPage in remote platform via API
        $this->sendTerminal(" - Inserting WebPage in remote platform...");
        $response = $this->core->request->post_json_decode(
            "{$this->api_base_url}/core/cfo/cfi/CloudFrameWorkECMPages?_raw&_timezone=UTC",
            $webpage,
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
        $this->sendTerminal(" + WebPage record inserted");
        //endregion

        //region SEND success message to terminal
        $this->sendTerminal(str_repeat('-', 50));
        $this->sendTerminal(" + WebPage [{$page_id}] inserted successfully in remote platform");
        //endregion

        //region GET Last version of the WebPage
        $this->formParams['id'] = $page_id;
        $this->METHOD_backup_from_remote();
        //endregion

        return true;
    }
}
