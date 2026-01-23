<?php
/**
 * Test script for Buckets class
 *
 * Run: php runscript.php tests/Buckets?bucket=gs://your-bucket-name
 *
 * Tests:
 * - Basic bucket operations (mkdir, rmdir, scan, fastScan)
 * - File operations (putContents, getContents, uploadContents, deleteFile)
 * - File manipulation (copyFile, moveFile)
 * - Path validation (isDir, isFile, getBucketPath)
 * - MIME type detection (getMimeTypeFromExtension)
 * - Bucket information (getInfo, getAdminUrl)
 * - File information (getFileInfo)
 */
class Script extends CoreScripts
{

    /** @var string Platform ID for CFO operations (default: cloudframework) */
    var $platform_id = '';

    /** @var array HTTP headers for API authentication */
    var $headers = [];

    function main() {

        //region SET $this->platform_id from configuration
        $this->platform_id = $this->core->config->get('core.erp.platform_id');
        if(!$this->platform_id) return $this->addError('config-error','core.platform_id is not defined');
        //endregion

        //region AUTHENTICATE user and SET $this->headers (authentication headers for API requests)
        if (!$this->authPlatformUserWithLocalAccessToken($this->platform_id)) {
            return false;
        }
        //endregion


        //region VERIFY privileges
        $this->sendTerminal("Executing {$this->params[0]}/{$this->params[1]} from platform [{$this->platform_id}] user [{$this->core->user->id}] ");
        if(!$this->core->user->hasAnyPrivilege('development-admin,development-user'))
            return $this->addError('You do not have permission [development-admin] to execute this script');
        //endregion

        //region SET AUTHENTICATE header for API call
        $this->headers = [
            'X-WEB-KEY' => '/scripts/_backup/cfos',
            'X-DS-TOKEN' => $this->core->user->token
        ];
        //endregion

        //region EXECUTE METHOD_{$method} (dynamic method execution based on command)
        $method = ($this->params[2] ?? 'default');
        $this->sendTerminal(" - method: {$method}");
        
        if (!$this->useFunction('METHOD_' . str_replace('-', '_', $method))) {
            return $this->addError("   #/{$method} is not implemented");
        }
        if(!$this->error) {
            $this->core->logs->reset();
        }
        //endregion

    }

    public function METHOD_default() {
        $this->sendTerminal("Available commands:");
        $this->sendTerminal("  /backup-from-remote        - Backup all CFOs from remote platform");
        $this->sendTerminal("  /backup-from-remote?id=xx  - Backup specific CFO from remote platform");
        $this->sendTerminal("  /insert-from-backup?id=xx  - Insert new CFO in remote platform from local backup");
        $this->sendTerminal("  /update-from-backup?id=xx  - Update existing CFO in remote platform from local backup");
    }

    /**
     * Retrieves CFO data from a remote API endpoint and saves to backup directory.
     *
     * This method makes a request to the CloudFramework API to fetch CFO data.
     * If ?id= parameter is provided, it fetches only that specific CFO.
     * Otherwise, it fetches all CFOs for the platform.
     * Each CFO is saved as an individual JSON file in the backup directory.
     *
     * Usage:
     *   cfos/crud/{platform}/backup-from-remote           - Backup all CFOs
     *   cfos/crud/{platform}/backup-from-remote?id=CFOName - Backup specific CFO
     *
     * @return bool false if any error
     */
    public function METHOD_backup_from_remote() {

        //region VERIFY backup directory
        $backup_dir = $this->core->system->root_path;
        if(($backup_dir .= '/buckets') && !is_dir($backup_dir) && !mkdir($backup_dir)) return $this->addError("Backup directory [/buckets] can not be created");
        if(($backup_dir .= '/backups') && !is_dir($backup_dir) && !mkdir($backup_dir)) return $this->addError("Backup directory [/buckets/backups] can not be created");
        if(($backup_dir .= '/CFOs') && !is_dir($backup_dir) && !mkdir($backup_dir)) return $this->addError("Backup directory [/buckets/backups/CFOs] can not be created");
        if(($backup_dir .= '/'.$this->platform_id) && !is_dir($backup_dir) && !mkdir($backup_dir)) return $this->addError("Backup directory [/buckets/backups/CFOs/{$this->platform_id}] can not be created");
        $this->sendTerminal(' - Backup directory: /buckets/backups/CFOs/'.$this->platform_id);
        //endregion

        //region SET $cfo_id (specific CFO to backup, or null for all)
        $cfo_id = $this->formParams['id'] ?? null;
        //endregion

        //region READ CFOs from remote API
        if($cfo_id) {
            //region FETCH single CFO by KeyName
            $params = ['_timezone'=>'UTC','_raw'=>1];
            $this->sendTerminal(" - Fetching CFO: {$cfo_id}");
            $response = $this->core->request->get_json_decode(
                "https://api.cloudframework.dev/core/cfo/cfi/CloudFrameWorkCFOsLocal/display/{$cfo_id}",
                $params,
                $this->headers
            );
            if($this->core->request->error) return $this->addError($this->core->request->errorMsg);
            if(!($response['data']??null)) return $this->addError("CFO [{$cfo_id}] not found in remote platform");
            $cfos = [$response['data']];
            //endregion
        } else {
            //region FETCH all CFOs
            $this->sendTerminal(" - Fetching all CFOs...");
            $params = ['cfo_limit' => 2000,'_timezone'=>'UTC','_raw'=>1];
            $response = $this->core->request->get_json_decode(
                'https://api.cloudframework.dev/core/cfo/cfi/CloudFrameWorkCFOsLocal?_raw',
                $params,
                $this->headers
            );
            if($this->core->request->error) return $this->addError($this->core->request->errorMsg);
            $cfos = $response['data'] ?? [];
            //endregion

        }
        $this->sendTerminal(" - CFOs to backup: ".count($cfos));
        //endregion

        //region PROCESS and SAVE each CFO to backup directory
        $no_secrets = [];
        $no_date_updating = [];
        $saved_count = 0;
        $unchanged_count = 0;

        foreach ($cfos as $cfo) {
            //region VALIDATE CFO has KeyName
            if(!($cfo['KeyName'] ?? null)) {
                $this->sendTerminal("   # Skipping CFO without KeyName");
                continue;
            }
            $key_name = $cfo['KeyName'];
            //endregion

            //region SORT $cfo keys alphabetically
            ksort($cfo);
            //endregion

            //region CHECK for missing secrets in non-ds CFOs
            if(($cfo['type'] ?? 'ds') != 'ds') {
                if(!($cfo['interface']['secret'] ?? null)) {
                    $no_secrets[] = $key_name;
                }
            }
            //endregion

            //region CHECK for missing DateUpdating
            if(!($cfo['DateUpdating'] ?? null)) {
                $no_date_updating[] = $key_name;
            }
            //endregion

            //region COMPARE with local file and SAVE if different
            $filename = "{$backup_dir}/{$key_name}.json";
            $json_content = $this->core->jsonEncode($cfo, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);

            // Check if local file exists and compare content
            if (is_file($filename)) {
                $local_content = file_get_contents($filename);
                if ($local_content === $json_content) {
                    $unchanged_count++;
                    $this->sendTerminal("   = Unchanged: {$key_name}.json");
                    continue;
                }
            }

            // Save only if content is different or file doesn't exist
            if(file_put_contents($filename, $json_content) === false) {
                return $this->addError("Failed to write CFO [{$key_name}] to file");
            }
            $saved_count++;
            $this->sendTerminal("   + Saved: {$key_name}.json");
            //endregion
        }
        //endregion

        //region SEND summary to terminal
        $this->sendTerminal(str_repeat('-', 50));
        $this->sendTerminal(" - Total CFOs: " . count($cfos) . " (saved: {$saved_count}, unchanged: {$unchanged_count})");

        if($no_secrets) {
            $this->sendTerminal("   # CFOs without secrets: ".implode(', ', $no_secrets));
        }
        if($no_date_updating) {
            $this->sendTerminal("   # CFOs without DateUpdating: ".implode(', ', $no_date_updating));
        }
        //endregion

        return true;
    }

    /**
     * Updates a CFO in the remote platform from local backup file.
     *
     * This method reads a CFO JSON file from the local backup directory
     * and updates it in the remote CloudFramework platform via API.
     *
     * Usage:
     *   _backup/cfos/update-from-backup?id=CFOName
     *
     * @return bool false if any error
     */
    public function METHOD_update_from_backup() {

        //region VALIDATE $cfo_id (required parameter)
        $cfo_id = $this->formParams['id'] ?? null;
        if(!$cfo_id) {
            return $this->addError("Missing required parameter: id. Usage: _backup/cfos/update-from-backup?id=CFOName");
        }
        $this->sendTerminal(" - CFO to update: {$cfo_id}");
        //endregion

        //region SET $backup_file (path to local backup JSON file)
        $backup_file = $this->core->system->root_path."/buckets/backups/CFOs/{$this->platform_id}/{$cfo_id}.json";
        $this->sendTerminal(" - Backup file: /buckets/backups/CFOs/{$this->platform_id}/{$cfo_id}.json");
        //endregion

        //region VALIDATE $backup_file exists
        if(!is_file($backup_file)) {
            return $this->addError("Backup file not found: {$backup_file}");
        }
        //endregion

        //region READ $cfo_data from backup file
        $json_content = file_get_contents($backup_file);
        if($json_content === false) {
            return $this->addError("Failed to read backup file: {$backup_file}");
        }
        $cfo_data = $this->core->jsonDecode($json_content);
        if($cfo_data === null) {
            return $this->addError("Invalid JSON in backup file: {$backup_file}");
        }
        $this->sendTerminal(" - CFO data loaded successfully");
        //endregion

        //region VALIDATE $cfo_data has KeyName matching $cfo_id
        if(($cfo_data['KeyName'] ?? null) !== $cfo_id) {
            return $this->addError("KeyName mismatch: file contains '{$cfo_data['KeyName']}' but expected '{$cfo_id}'");
        }
        //endregion

        //region FETCH remote CFO and COMPARE with local backup
        $this->sendTerminal(" - Fetching remote CFO for comparison...");
        $remote_response = $this->core->request->get_json_decode(
            "https://api.cloudframework.dev/core/cfo/cfi/CloudFrameWorkCFOsLocal/display/{$cfo_id}",
            ['_timezone' => 'UTC', '_raw' => 1],
            $this->headers
        );

        if (!$this->core->request->error && ($remote_response['data'] ?? null)) {
            $remote_cfo = $remote_response['data'];
            ksort($remote_cfo);
            ksort($cfo_data);

            $remote_json = $this->core->jsonEncode($remote_cfo, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            $local_json = $this->core->jsonEncode($cfo_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

            if ($remote_json === $local_json) {
                $this->sendTerminal(str_repeat('-', 50));
                $this->sendTerminal(" = CFO [{$cfo_id}] is unchanged (local backup equals remote)");
                return true;
            }
        }
        //endregion

        //region UPDATE CFO in remote platform via API
        $this->sendTerminal(" - Updating CFO in remote platform...");
        $api_url = "https://api.cloudframework.dev/core/cfo/cfi/CloudFrameWorkCFOsLocal/{$cfo_id}?_raw";

        $response = $this->core->request->put_json_decode(
            $api_url,
            $cfo_data,
            $this->headers,
            true
        );

        if($this->core->request->error) {
            return $this->addError("API request failed: ".$this->core->request->errorMsg);
        }

        if(!($response['success'] ?? false)) {
            $error_msg = $response['errorMsg'] ?? 'Unknown error';
            if(is_array($error_msg)) $error_msg = implode(', ', $error_msg);
            return $this->addError("API returned error: {$error_msg}");
        }
        //endregion

        //region SEND success message to terminal
        $this->sendTerminal(str_repeat('-', 50));
        $this->sendTerminal(" + CFO [{$cfo_id}] updated successfully in remote platform");
        //endregion

        //region GET Last version of the cfo
        $this->formParams['id'] = $cfo_id;
        $this->METHOD_backup_from_remote();
        //endregion

        return true;
    }

    /**
     * Inserts a new CFO in the remote platform from local backup file.
     *
     * This method reads a CFO JSON file from the local backup directory
     * and creates it in the remote CloudFramework platform via API.
     *
     * Usage:
     *   _backup/cfos/insert-from-backup?id=CFOName
     *
     * @return bool false if any error
     */
    public function METHOD_insert_from_backup() {

        //region VALIDATE $cfo_id (required parameter)
        $cfo_id = $this->formParams['id'] ?? null;
        if(!$cfo_id) {
            return $this->addError("Missing required parameter: id. Usage: _backup/cfos/insert-from-backup?id=CFOName");
        }
        $this->sendTerminal(" - CFO to insert: {$cfo_id}");
        //endregion

        //region SET $backup_file (path to local backup JSON file)
        $backup_file = $this->core->system->root_path."/buckets/backups/CFOs/{$this->platform_id}/{$cfo_id}.json";
        $this->sendTerminal(" - Backup file: /buckets/backups/CFOs/{$this->platform_id}/{$cfo_id}.json");
        //endregion

        //region VALIDATE $backup_file exists
        if(!is_file($backup_file)) {
            return $this->addError("Backup file not found: {$backup_file}");
        }
        //endregion

        //region READ $cfo_data from backup file
        $json_content = file_get_contents($backup_file);
        if($json_content === false) {
            return $this->addError("Failed to read backup file: {$backup_file}");
        }
        $cfo_data = $this->core->jsonDecode($json_content);
        if($cfo_data === null) {
            return $this->addError("Invalid JSON in backup file: {$backup_file}");
        }
        $this->sendTerminal(" - CFO data loaded successfully");
        //endregion

        //region VALIDATE $cfo_data has KeyName matching $cfo_id
        if(($cfo_data['KeyName'] ?? null) !== $cfo_id) {
            return $this->addError("KeyName mismatch: file contains '{$cfo_data['KeyName']}' but expected '{$cfo_id}'");
        }
        //endregion

        //region INSERT CFO in remote platform via API
        $this->sendTerminal(" - Inserting CFO in remote platform...");
        $api_url = "https://api.cloudframework.dev/core/cfo/cfi/CloudFrameWorkCFOsLocal?_raw";

        $response = $this->core->request->post_json_decode(
            $api_url,
            $cfo_data,
            $this->headers
        );

        if($this->core->request->error) {
            return $this->addError("API request failed: ".$this->core->request->errorMsg);
        }

        if(!($response['success'] ?? false)) {
            $error_msg = $response['errorMsg'] ?? 'Unknown error';
            if(is_array($error_msg)) $error_msg = implode(', ', $error_msg);
            return $this->addError("API returned error: {$error_msg}");
        }
        //endregion

        //region SEND success message to terminal
        $this->sendTerminal(str_repeat('-', 50));
        $this->sendTerminal(" + CFO [{$cfo_id}] inserted successfully in remote platform");
        //endregion

        //region GET Last version of the cfo
        $this->METHOD_backup_from_remote();
        //endregion

        return true;
    }
}