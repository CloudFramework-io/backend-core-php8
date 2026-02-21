<?php
/**
 * Localizations Management Script
 *
 * This script provides functionality to manage CloudFrameWorkLocalizations by:
 * - Backing up Localizations from the remote platform to local storage
 * - Inserting new Localizations from local backup to the remote platform
 * - Updating existing Localizations from local backup to the remote platform
 * - Listing Localizations in remote and local storage
 *
 * Localizations provide multi-language tag dictionaries organized by App and Category.
 * Each localization entry contains translations for different languages.
 *
 * The script operates on Localization data stored in the `buckets/backups/Localize/{platform}/` directory
 * and synchronizes them with the CloudFramework platform via REST API.
 *
 * Each Localization backup file contains:
 * - CloudFrameWorkLocalizations: Localization data (KeyName, App, Cat, Code, Translations, JSON, etc.)
 *
 * Usage:
 *   _cloudia/localize/backup-from-remote                    - Backup all Localizations from remote
 *   _cloudia/localize/backup-from-remote?id=key             - Backup specific Localization
 *   _cloudia/localize/backup-from-remote?app=myapp          - Backup all Localizations for an App
 *   _cloudia/localize/backup-from-remote?app=myapp&cat=cat  - Backup Localizations for App;Cat
 *   _cloudia/localize/insert-from-backup?id=key             - Insert new Localization to remote
 *   _cloudia/localize/update-from-backup?id=key             - Update existing Localization in remote
 *   _cloudia/localize/list-remote                           - List all Localizations in remote
 *   _cloudia/localize/list-remote?app=myapp                 - List Localizations for an App
 *   _cloudia/localize/list-local                            - List all Localizations in local backup
 *
 * @author CloudFramework Development Team
 * @version 1.0
 */
class Script extends CoreScripts
{
    /** @var string Platform ID for Localization operations */
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
        if (!$this->core->user->hasAnyPrivilege('development-admin,development-user,localization-admin')) {
            return $this->addError('You do not have permission [development-admin,localization-admin] to execute this script');
        }
        //endregion

        //region SET AUTHENTICATE header for API call
        $this->headers = [
            'X-WEB-KEY' => '/scripts/_cloudia/localize',
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
        $this->sendTerminal("  /backup-from-remote                    - Backup all Localizations from remote platform");
        $this->sendTerminal("  /backup-from-remote?id=key             - Backup specific Localization from remote platform");
        $this->sendTerminal("  /backup-from-remote?app=myapp          - Backup all Localizations for an App");
        $this->sendTerminal("  /backup-from-remote?app=myapp&cat=cat  - Backup Localizations for App and Category");
        $this->sendTerminal("  /insert-from-backup?id=key             - Insert new Localization in remote platform from local backup");
        $this->sendTerminal("  /update-from-backup?id=key             - Update existing Localization in remote platform from local backup");
        $this->sendTerminal("  /list-remote                           - List all Localizations in remote platform");
        $this->sendTerminal("  /list-remote?app=myapp                 - List Localizations for an App");
        $this->sendTerminal("  /list-local                            - List all Localizations in local backup");
        $this->sendTerminal("");
        $this->sendTerminal("Examples:");
        $this->sendTerminal("  composer script -- \"_cloudia/localize/backup-from-remote?id=myapp;mycat;mycode\"");
        $this->sendTerminal("  composer script -- \"_cloudia/localize/backup-from-remote?app=cloudframework&cat=common\"");
        $this->sendTerminal("  composer script -- _cloudia/localize/list-remote");
    }

    /**
     * Convert Localization KeyName to backup filename
     *
     * @param string $localization_id Localization KeyName (e.g., myapp;mycat;mycode)
     * @return string Filename (e.g., myapp__mycat__mycode.json)
     */
    private function localizationIdToFilename($localization_id)
    {
        // Replace semicolons and special characters with double underscores
        $name = str_replace(';', '__', $localization_id);
        $name = preg_replace('/[^A-Za-z0-9_-]/', '_', $name);
        return $name . '.json';
    }

    /**
     * Convert backup filename to Localization KeyName
     *
     * @param string $filename Filename (e.g., myapp__mycat__mycode.json)
     * @return string Localization KeyName (e.g., myapp;mycat;mycode)
     */
    private function filenameToLocalizationId($filename)
    {
        // Remove .json extension and convert double underscores back to semicolons
        $name = basename($filename, '.json');
        return str_replace('__', ';', $name);
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
        if (($backup_dir .= '/Localize') && !is_dir($backup_dir) && !mkdir($backup_dir)) {
            $this->addError("Backup directory [/buckets/backups/Localize] can not be created");
            return false;
        }
        if (($backup_dir .= '/' . $this->platform_id) && !is_dir($backup_dir) && !mkdir($backup_dir)) {
            $this->addError("Backup directory [/buckets/backups/Localize/{$this->platform_id}] can not be created");
            return false;
        }
        return $backup_dir;
    }

    /**
     * List all Localizations in remote platform
     */
    public function METHOD_list_remote()
    {
        //region FETCH all Localizations from remote platform
        $this->sendTerminal("Listing Localizations in remote platform [{$this->platform_id}]:");
        $this->sendTerminal(str_repeat('-', 100));

        $params = [
            '_fields' => 'KeyName,App,Cat,Code,Lang,Default,Translations',
            '_order' => 'App,Cat,Code',
            '_limit' => 1000
        ];

        // Filter by App if provided
        $app = $this->formParams['app'] ?? null;
        if ($app) {
            $params['filter_App'] = $app;
            $this->sendTerminal(" - Filtering by App: {$app}");
        }

        // Filter by Cat if provided
        $cat = $this->formParams['cat'] ?? null;
        if ($cat) {
            $params['filter_Cat'] = $cat;
            $this->sendTerminal(" - Filtering by Cat: {$cat}");
        }

        $response = $this->core->request->get_json_decode(
            "{$this->api_base_url}/core/cfo/cfi/CloudFrameWorkLocalizations?_raw=1&_timezone=UTC",
            $params,
            $this->headers
        );

        if ($this->core->request->error) {
            return $this->addError($this->core->request->errorMsg);
        }

        $localizations = $response['data'] ?? [];
        if (!$localizations) {
            $this->sendTerminal("No Localizations found in remote platform");
            return true;
        }

        $this->sendTerminal(sprintf("%-20s %-15s %-20s %-5s %-30s %s", "App", "Cat", "Code", "Lang", "Default", "Translations"));
        $this->sendTerminal(str_repeat('-', 100));

        foreach ($localizations as $loc) {
            $translations = $loc['Translations'] ?? [];
            $translationsStr = is_array($translations) ? implode(',', $translations) : $translations;
            $this->sendTerminal(sprintf(
                "%-20s %-15s %-20s %-5s %-30s %s",
                substr($loc['App'] ?? '', 0, 20),
                substr($loc['Cat'] ?? '', 0, 15),
                substr($loc['Code'] ?? '', 0, 20),
                substr($loc['Lang'] ?? '', 0, 5),
                substr($loc['Default'] ?? '', 0, 30),
                substr($translationsStr, 0, 15)
            ));
        }
        $this->sendTerminal(str_repeat('-', 100));
        $this->sendTerminal("Total: " . count($localizations) . " Localizations");
        //endregion

        return true;
    }

    /**
     * List all Localizations in local backup
     */
    public function METHOD_list_local()
    {
        //region LIST all Localizations in local backup
        $this->sendTerminal("Listing Localizations in local backup [{$this->platform_id}]:");
        $this->sendTerminal(str_repeat('-', 100));

        $directory_path = $this->core->system->root_path . '/buckets/backups/Localize/' . $this->platform_id;

        if (!is_dir($directory_path)) {
            $this->sendTerminal("Backup directory not found: /buckets/backups/Localize/{$this->platform_id}");
            return true;
        }

        $files = glob($directory_path . '/*.json');
        if (!$files) {
            $this->sendTerminal("No Localization backup files found");
            return true;
        }

        $this->sendTerminal(sprintf("%-20s %-15s %-20s %-5s %-30s %s", "App", "Cat", "Code", "Lang", "Default", "Translations"));
        $this->sendTerminal(str_repeat('-', 100));

        foreach ($files as $file) {
            $filename = basename($file);

            $data = $this->core->jsonDecode(file_get_contents($file));
            $locData = $data['CloudFrameWorkLocalizations'] ?? $data;
            $translations = $locData['Translations'] ?? [];
            $translationsStr = is_array($translations) ? implode(',', $translations) : $translations;
            $this->sendTerminal(sprintf(
                "%-20s %-15s %-20s %-5s %-30s %s",
                substr($locData['App'] ?? '', 0, 20),
                substr($locData['Cat'] ?? '', 0, 15),
                substr($locData['Code'] ?? '', 0, 20),
                substr($locData['Lang'] ?? '', 0, 5),
                substr($locData['Default'] ?? '', 0, 30),
                substr($translationsStr, 0, 15)
            ));
        }
        $this->sendTerminal(str_repeat('-', 100));
        $this->sendTerminal("Total: " . count($files) . " Localizations");
        //endregion

        return true;
    }

    /**
     * Backup Localizations from remote platform to local storage
     */
    public function METHOD_backup_from_remote()
    {
        //region VERIFY backup directory
        if (!$backup_dir = $this->getBackupDir()) {
            return false;
        }
        $this->sendTerminal(" - Backup directory: /buckets/backups/Localize/{$this->platform_id}");
        //endregion

        //region SET filters (specific Localization, App, or Cat)
        $localization_id = $this->formParams['id'] ?? null;
        $app = $this->formParams['app'] ?? null;
        $cat = $this->formParams['cat'] ?? null;
        //endregion

        //region READ Localizations from remote API
        $localizations = [];
        if ($localization_id) {
            //region FETCH single Localization by KeyName
            $this->sendTerminal(" - Fetching Localization: {$localization_id}");
            $response = $this->core->request->get_json_decode(
                "{$this->api_base_url}/core/cfo/cfi/CloudFrameWorkLocalizations/display/" . urlencode($localization_id) . '?_raw&_timezone=UTC',
                ['_raw' => 1, '_timezone' => 'UTC'],
                $this->headers
            );
            if ($this->core->request->error) {
                return $this->addError($this->core->request->errorMsg);
            }
            if (!($response['data'] ?? null)) {
                return $this->addError("Localization [{$localization_id}] not found in remote platform");
            }
            $localizations = [$response['data']];
            //endregion

        } else {
            //region FETCH Localizations with optional filters
            $params = ['cfo_limit' => 2000, '_raw' => 1, '_timezone' => 'UTC'];

            if ($app) {
                $params['filter_App'] = $app;
                $this->sendTerminal(" - Filtering by App: {$app}");
            }
            if ($cat) {
                $params['filter_Cat'] = $cat;
                $this->sendTerminal(" - Filtering by Cat: {$cat}");
            }

            if (!$app && !$cat) {
                $this->sendTerminal(" - Fetching all Localizations... [max 2000]");
            }

            $response = $this->core->request->get_json_decode(
                "{$this->api_base_url}/core/cfo/cfi/CloudFrameWorkLocalizations?_raw&_timezone=UTC",
                $params,
                $this->headers
            );
            if ($this->core->request->error) {
                return $this->addError($this->core->request->errorMsg);
            }
            $localizations = $response['data'] ?? [];
            //endregion
        }
        $tot_localizations = count($localizations);

        $this->sendTerminal(" - Localizations to backup: {$tot_localizations}");
        //endregion

        //region PROCESS and SAVE each Localization to backup directory
        $saved_count = 0;
        $unchanged_count = 0;

        foreach ($localizations as $localization) {
            //region VALIDATE Localization has KeyName
            if (!($localization['KeyName'] ?? null)) {
                $this->sendTerminal("   # Skipping Localization without KeyName");
                continue;
            }
            $key_name = $localization['KeyName'];
            //endregion

            //region SORT $localization keys alphabetically
            ksort($localization);
            //endregion

            //region BUILD $localization_data structure
            $localization_data = [
                'CloudFrameWorkLocalizations' => $localization
            ];
            //endregion

            //region COMPARE with local file and SAVE if different
            $filename = $this->localizationIdToFilename($key_name);
            $filepath = "{$backup_dir}/{$filename}";
            $json_content = $this->core->jsonEncode($localization_data, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);

            // Check if local file exists and compare content
            if (is_file($filepath)) {
                $local_content = file_get_contents($filepath);
                if ($local_content === $json_content) {
                    $unchanged_count++;
                    $this->sendTerminal("   = Unchanged: {$filename}");
                    continue;
                }
            }

            if (file_put_contents($filepath, $json_content) === false) {
                return $this->addError("Failed to write Localization [{$key_name}] to file");
            }
            $saved_count++;
            $this->sendTerminal("   + Saved: {$filename}");
            //endregion
        }
        //endregion

        //region SEND summary to terminal
        $this->sendTerminal(str_repeat('-', 50));
        $this->sendTerminal(" - Total Localizations: {$tot_localizations} (saved: {$saved_count}, unchanged: {$unchanged_count})");
        //endregion

        return true;
    }

    /**
     * Update existing Localization in remote platform from local backup
     */
    public function METHOD_update_from_backup()
    {
        //region VALIDATE $localization_id (required parameter)
        $localization_id = $this->formParams['id'] ?? null;
        if (!$localization_id) {
            return $this->addError("Missing required parameter: id. Usage: _cloudia/localize/update-from-backup?id=app;cat;code");
        }
        $this->sendTerminal(" - Localization to update: {$localization_id}");
        //endregion

        //region SET $backup_file (path to local backup JSON file)
        $filename = $this->localizationIdToFilename($localization_id);
        $backup_file = $this->core->system->root_path . "/buckets/backups/Localize/{$this->platform_id}/{$filename}";
        $this->sendTerminal(" - Backup file: /buckets/backups/Localize/{$this->platform_id}/{$filename}");
        //endregion

        //region VALIDATE $backup_file exists
        if (!is_file($backup_file)) {
            return $this->addError("Backup file not found: {$backup_file}");
        }
        //endregion

        //region READ $localization_data from backup file
        $json_content = file_get_contents($backup_file);
        if ($json_content === false) {
            return $this->addError("Failed to read backup file: {$backup_file}");
        }
        $localization_data = $this->core->jsonDecode($json_content);
        if ($localization_data === null) {
            return $this->addError("Invalid JSON in backup file: {$backup_file}");
        }
        $this->sendTerminal(" - Localization data loaded successfully");
        //endregion

        //region VALIDATE $localization_data has correct structure
        $localization = $localization_data['CloudFrameWorkLocalizations'] ?? $localization_data;
        if (!$localization || ($localization['KeyName'] ?? null) !== $localization_id) {
            return $this->addError("KeyName mismatch: file contains '{$localization['KeyName']}' but expected '{$localization_id}'");
        }
        //endregion

        //region FETCH remote Localization and COMPARE with local backup
        $this->sendTerminal(" - Fetching remote Localization for comparison...");
        $remote_response = $this->core->request->get_json_decode(
            "{$this->api_base_url}/core/cfo/cfi/CloudFrameWorkLocalizations/display/" . urlencode($localization_id) . "?_raw&_timezone=UTC",
            ['_timezone' => 'UTC', '_raw' => 1],
            $this->headers
        );

        if (!$this->core->request->error && ($remote_response['data'] ?? null)) {
            $remote_localization = $remote_response['data'];
            ksort($remote_localization);
            ksort($localization);

            $remote_json = $this->core->jsonEncode($remote_localization, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            $local_json = $this->core->jsonEncode($localization, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

            if ($remote_json === $local_json) {
                $this->sendTerminal(str_repeat('-', 50));
                $this->sendTerminal(" = Localization [{$localization_id}] is unchanged (local backup equals remote)");
                return true;
            }
        }
        //endregion

        //region UPDATE Localization in remote platform via API
        $this->sendTerminal(" - Updating Localization in remote platform...");
        $response = $this->core->request->put_json_decode(
            "{$this->api_base_url}/core/cfo/cfi/CloudFrameWorkLocalizations/" . urlencode($localization_id) . "?_raw&_timezone=UTC",
            $localization,
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
        $this->sendTerminal(" + Localization record updated");
        //endregion

        //region SEND success message to terminal
        $this->sendTerminal(str_repeat('-', 50));
        $this->sendTerminal(" + Localization [{$localization_id}] updated successfully in remote platform");
        //endregion

        //region GET Last version of the Localization
        $this->formParams['id'] = $localization_id;
        $this->METHOD_backup_from_remote();
        //endregion

        return true;
    }

    /**
     * Insert new Localization in remote platform from local backup
     */
    public function METHOD_insert_from_backup()
    {
        //region VALIDATE $localization_id (required parameter)
        $localization_id = $this->formParams['id'] ?? null;
        if (!$localization_id) {
            return $this->addError("Missing required parameter: id. Usage: _cloudia/localize/insert-from-backup?id=app;cat;code");
        }
        $this->sendTerminal(" - Localization to insert: {$localization_id}");
        //endregion

        //region SET $backup_file (path to local backup JSON file)
        $filename = $this->localizationIdToFilename($localization_id);
        $backup_file = $this->core->system->root_path . "/buckets/backups/Localize/{$this->platform_id}/{$filename}";
        $this->sendTerminal(" - Backup file: /buckets/backups/Localize/{$this->platform_id}/{$filename}");
        //endregion

        //region VALIDATE $backup_file exists
        if (!is_file($backup_file)) {
            return $this->addError("Backup file not found: {$backup_file}");
        }
        //endregion

        //region READ $localization_data from backup file
        $json_content = file_get_contents($backup_file);
        if ($json_content === false) {
            return $this->addError("Failed to read backup file: {$backup_file}");
        }
        $localization_data = $this->core->jsonDecode($json_content);
        if ($localization_data === null) {
            return $this->addError("Invalid JSON in backup file: {$backup_file}");
        }
        $this->sendTerminal(" - Localization data loaded successfully");
        //endregion

        //region VALIDATE $localization_data has correct structure
        $localization = $localization_data['CloudFrameWorkLocalizations'] ?? $localization_data;
        if (!$localization || ($localization['KeyName'] ?? null) !== $localization_id) {
            return $this->addError("KeyName mismatch: file contains '{$localization['KeyName']}' but expected '{$localization_id}'");
        }
        //endregion

        //region CHECK if Localization already exists in remote platform
        $check_response = $this->core->request->get_json_decode(
            "{$this->api_base_url}/core/cfo/cfi/CloudFrameWorkLocalizations/" . urlencode($localization_id) . '?_raw&_timezone=UTC',
            [],
            $this->headers
        );
        if (!$this->core->request->error && ($check_response['data'] ?? null)) {
            return $this->addError("Localization [{$localization_id}] already exists in remote platform. Use update-from-backup instead.");
        }
        //endregion

        //region INSERT Localization in remote platform via API
        $this->sendTerminal(" - Inserting Localization in remote platform...");
        $response = $this->core->request->post_json_decode(
            "{$this->api_base_url}/core/cfo/cfi/CloudFrameWorkLocalizations?_raw&_timezone=UTC",
            $localization,
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
        $this->sendTerminal(" + Localization record inserted");
        //endregion

        //region SEND success message to terminal
        $this->sendTerminal(str_repeat('-', 50));
        $this->sendTerminal(" + Localization [{$localization_id}] inserted successfully in remote platform");
        //endregion

        //region GET Last version of the Localization
        $this->formParams['id'] = $localization_id;
        $this->METHOD_backup_from_remote();
        //endregion

        return true;
    }
}
