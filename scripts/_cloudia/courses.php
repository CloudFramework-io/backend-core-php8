<?php
/**
 * Course Documentation Backup Script
 *
 * This script provides functionality to manage CLOUD Academy Course documentation by:
 * - Backing up Courses from the remote platform to local storage
 * - Inserting new Courses from local backup to the remote platform
 * - Updating existing Courses from local backup to the remote platform
 * - Listing Courses in remote and local storage
 * - Managing Course Groups
 *
 * The script operates on Course documentation stored in the `buckets/backups/Courses/{platform}/` directory
 * and synchronizes them with the CloudFramework platform via REST API.
 *
 * Each Course backup file contains:
 * - CloudFrameWorkAcademyCourses: Main Course documentation
 * - CloudFrameWorkAcademyContents: Individual content/chapter documentation
 *
 * Additionally, groups.json contains:
 * - CloudFrameWorkAcademyGroups: Course groups/categories
 *
 * Usage:
 *   _cloudia/courses/backup-from-remote                    - Backup all Courses from remote
 *   _cloudia/courses/backup-from-remote?id=1234567890      - Backup specific Course by KeyId
 *   _cloudia/courses/insert-from-backup?id=1234567890      - Insert new Course to remote
 *   _cloudia/courses/update-from-backup?id=1234567890      - Update existing Course in remote
 *   _cloudia/courses/list-remote                           - List all Courses in remote
 *   _cloudia/courses/list-local                            - List all Courses in local backup
 *   _cloudia/courses/backup-groups                         - Backup Course Groups from remote
 *
 * @author CloudFramework Development Team
 * @version 1.1
 */
class Script extends CoreScripts
{
    /** @var string Platform ID for Course operations */
    var $platform_id = '';

    /** @var array HTTP headers for API authentication */
    var $headers = [];

    /** @var string Base API URL for remote platform */
    var $api_base_url = 'https://api.cloudframework.io';

    /** @var array Course groups indexed by KeyId */
    var $groups = [];

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
            'X-WEB-KEY' => '/scripts/_cloudia/courses',
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
        $this->sendTerminal("  /backup-from-remote            - Backup all Courses from remote platform");
        $this->sendTerminal("  /backup-from-remote?id=KEYID   - Backup specific Course from remote platform");
        $this->sendTerminal("  /insert-from-backup?id=KEYID   - Insert new Course in remote platform from local backup");
        $this->sendTerminal("  /update-from-backup?id=KEYID   - Update existing Course in remote platform from local backup");
        $this->sendTerminal("  /list-remote                   - List all Courses in remote platform");
        $this->sendTerminal("  /list-local                    - List all Courses in local backup");
        $this->sendTerminal("  /backup-groups                 - Backup Course Groups from remote platform");
        $this->sendTerminal("");
        $this->sendTerminal("Examples:");
        $this->sendTerminal("  composer run-script script \"_cloudia/courses/backup-from-remote?id=5077124951572480\"");
        $this->sendTerminal("  composer run-script script _cloudia/courses/list-remote");
        $this->sendTerminal("  composer run-script script _cloudia/courses/backup-groups");
    }

    /**
     * Convert Course KeyId to backup filename
     *
     * @param string $course_id Course KeyId (numeric)
     * @return string Filename (e.g., 5077124951572480.json)
     */
    private function courseIdToFilename($course_id)
    {
        // KeyId is numeric, just add .json extension
        $name = preg_replace('/[^0-9]/', '', $course_id);
        return $name . '.json';
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
        if (($backup_dir .= '/Courses') && !is_dir($backup_dir) && !mkdir($backup_dir)) {
            $this->addError("Backup directory [/buckets/backups/Courses] can not be created");
            return false;
        }
        if (($backup_dir .= '/' . $this->platform_id) && !is_dir($backup_dir) && !mkdir($backup_dir)) {
            $this->addError("Backup directory [/buckets/backups/Courses/{$this->platform_id}] can not be created");
            return false;
        }
        return $backup_dir;
    }

    /**
     * Load groups from remote platform
     */
    private function loadGroupsFromRemote()
    {
        $response = $this->core->request->get_json_decode(
            "{$this->api_base_url}/core/cfo/cfi/CloudFrameWorkAcademyGroups?_raw&_timezone=UTC",
            ['cfo_limit' => 500, '_raw' => 1, '_timezone' => 'UTC'],
            $this->headers
        );
        if (!$this->core->request->error && ($response['data'] ?? null)) {
            $this->groups = $this->core->utils->convertArrayIndexedByColumn($response['data'], 'KeyId');
        }
    }

    /**
     * Load groups from local backup
     */
    private function loadGroupsFromLocal()
    {
        $groups_file = $this->core->system->root_path . "/buckets/backups/Courses/{$this->platform_id}/groups.json";
        if (is_file($groups_file)) {
            $data = $this->core->jsonDecode(file_get_contents($groups_file));
            if (isset($data['CloudFrameWorkAcademyGroups'])) {
                $this->groups = $this->core->utils->convertArrayIndexedByColumn($data['CloudFrameWorkAcademyGroups'], 'KeyId');
            }
        }
    }

    /**
     * Backup Course Groups from remote platform
     */
    public function METHOD_backup_groups()
    {
        //region VERIFY backup directory
        if (!$backup_dir = $this->getBackupDir()) {
            return false;
        }
        $this->sendTerminal(" - Backup directory: /buckets/backups/Courses/{$this->platform_id}");
        //endregion

        //region FETCH Groups from remote platform
        $this->sendTerminal(" - Fetching Course Groups...");
        $response = $this->core->request->get_json_decode(
            "{$this->api_base_url}/core/cfo/cfi/CloudFrameWorkAcademyGroups?_raw&_timezone=UTC",
            ['cfo_limit' => 500, '_raw' => 1, '_timezone' => 'UTC'],
            $this->headers
        );

        if ($this->core->request->error) {
            return $this->addError($this->core->request->errorMsg);
        }

        $groups = $response['data'] ?? [];
        if (!$groups) {
            $this->sendTerminal(" # No Course Groups found in remote platform");
            return true;
        }

        // Sort groups
        foreach ($groups as &$group) {
            ksort($group);
        }
        usort($groups, function ($a, $b) {
            return strcmp($a['KeyId'] ?? '', $b['KeyId'] ?? '');
        });
        //endregion

        //region SAVE Groups to JSON file
        $groups_data = ['CloudFrameWorkAcademyGroups' => $groups];
        $filepath = "{$backup_dir}/groups.json";
        $json_content = $this->core->jsonEncode($groups_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if (file_put_contents($filepath, $json_content) === false) {
            return $this->addError("Failed to write groups.json");
        }
        $this->sendTerminal(" + Saved: groups.json (" . count($groups) . " groups)");
        //endregion

        return true;
    }

    /**
     * List all Courses in remote platform
     */
    public function METHOD_list_remote()
    {
        //region FETCH Groups for display
        $this->loadGroupsFromRemote();
        //endregion

        //region FETCH all Courses from remote platform
        $this->sendTerminal("Listing Courses in remote platform [{$this->platform_id}]:");
        $this->sendTerminal(str_repeat('-', 60));

        $params = ['_fields' => 'KeyId,CourseTitle,GroupId,Active', '_order' => 'CourseTitle', '_limit' => 1000];
        $response = $this->core->request->get_json_decode(
            "{$this->api_base_url}/core/cfo/cfi/CloudFrameWorkAcademyCourses?_raw=1&_timezone=UTC",
            $params,
            $this->headers
        );

        if ($this->core->request->error) {
            return $this->addError($this->core->request->errorMsg);
        }

        $courses = $response['data'] ?? [];
        if (!$courses) {
            $this->sendTerminal("No Courses found in remote platform");
            return true;
        }

        foreach ($courses as $course) {
            $keyId = $course['KeyId'] ?? 'N/A';
            $title = $course['CourseTitle'] ?? 'N/A';
            $active = ($course['Active'] ?? null) ? 'Active' : 'Inactive';
            $groupId = $course['GroupId'] ?? null;
            $group = $groupId ? ($this->groups[$groupId]['GroupName'] ?? 'not-found') : 'N/A';
            $this->sendTerminal(" [{$group}] {$keyId} - {$title} [{$active}]");
        }
        $this->sendTerminal(str_repeat('-', 60));
        $this->sendTerminal("Total: " . count($courses) . " Courses");
        //endregion

        return true;
    }

    /**
     * List all Courses in local backup
     */
    public function METHOD_list_local()
    {
        //region LIST all Courses in local backup
        $this->sendTerminal("Listing Courses in local backup [{$this->platform_id}]:");
        $this->sendTerminal(str_repeat('-', 60));

        $directory_path = $this->core->system->root_path . '/buckets/backups/Courses/' . $this->platform_id;

        if (!is_dir($directory_path)) {
            $this->sendTerminal("Backup directory not found: /buckets/backups/Courses/{$this->platform_id}");
            return true;
        }

        // Load groups for display
        $this->loadGroupsFromLocal();

        $files = glob($directory_path . '/*.json');
        if (!$files) {
            $this->sendTerminal("No Course backup files found");
            return true;
        }

        $count = 0;
        foreach ($files as $file) {
            $filename = basename($file, '.json');
            if ($filename == 'groups') continue; // Skip groups.json

            $count++;
            $data = $this->core->jsonDecode(file_get_contents($file));
            $courseData = $data['CloudFrameWorkAcademyCourses'] ?? [];
            $keyId = $courseData['KeyId'] ?? $filename;
            $title = $courseData['CourseTitle'] ?? 'N/A';
            $active = ($courseData['Active'] ?? null) ? 'Active' : 'Inactive';
            $groupId = $courseData['GroupId'] ?? null;
            $group = $groupId ? ($this->groups[$groupId]['GroupName'] ?? 'not-found') : 'N/A';
            $contentCount = count($data['CloudFrameWorkAcademyContents'] ?? []);
            $this->sendTerminal(" [{$group}] {$keyId} - {$title} [{$active}]: {$contentCount} contents");
        }
        $this->sendTerminal(str_repeat('-', 60));
        $this->sendTerminal("Total: " . $count . " Courses");
        //endregion

        return true;
    }

    /**
     * Backup Courses from remote platform to local storage
     */
    public function METHOD_backup_from_remote()
    {
        //region VERIFY backup directory
        if (!$backup_dir = $this->getBackupDir()) {
            return false;
        }
        $this->sendTerminal(" - Backup directory: /buckets/backups/Courses/{$this->platform_id}");
        //endregion

        //region SET $course_id (specific Course to backup, or null for all)
        $course_id = $this->formParams['id'] ?? null;
        //endregion

        //region BACKUP Groups first (always)
        $this->METHOD_backup_groups();
        //endregion

        //region READ Courses from remote API
        $courses = [];
        $all_contents = [];
        if ($course_id) {
            //region FETCH single Course by KeyId
            $this->sendTerminal(" - Fetching Course: {$course_id}");
            $response = $this->core->request->get_json_decode(
                "{$this->api_base_url}/core/cfo/cfi/CloudFrameWorkAcademyCourses/display/" . urlencode($course_id) . '?_raw&_timezone=UTC',
                ['_raw' => 1, '_timezone' => 'UTC'],
                $this->headers
            );
            if ($this->core->request->error) {
                return $this->addError($this->core->request->errorMsg);
            }
            if (!($response['data'] ?? null)) {
                return $this->addError("Course [{$course_id}] not found in remote platform");
            }
            $courses = [$response['data']];
            //endregion

            //region READ contents associated
            $this->sendTerminal(" - Fetching contents for Course... [max 2000]");
            $all_contents = $this->core->request->get_json_decode(
                "{$this->api_base_url}/core/cfo/cfi/CloudFrameWorkAcademyContents?_raw&_timezone=UTC",
                ['filter_CourseId' => $course_id, 'cfo_limit' => 2000, '_raw' => 1, '_timezone' => 'UTC'],
                $this->headers
            );
            if ($this->core->request->error) {
                return $this->addError($this->core->request->errorMsg);
            }
            //endregion

        } else {
            //region FETCH all Courses
            $this->sendTerminal(" - Fetching all Courses... [max 2000]");
            $response = $this->core->request->get_json_decode(
                "{$this->api_base_url}/core/cfo/cfi/CloudFrameWorkAcademyCourses?_raw&_timezone=UTC",
                ['cfo_limit' => 2000, '_raw' => 1, '_timezone' => 'UTC'],
                $this->headers
            );
            if ($this->core->request->error) {
                return $this->addError($this->core->request->errorMsg);
            }
            $courses = $response['data'] ?? [];
            //endregion

            //region READ all contents
            $this->sendTerminal(" - Fetching all Contents... [max 2000]");
            $all_contents = $this->core->request->get_json_decode(
                "{$this->api_base_url}/core/cfo/cfi/CloudFrameWorkAcademyContents?_raw&_timezone=UTC",
                ['cfo_limit' => 2000, '_raw' => 1, '_timezone' => 'UTC'],
                $this->headers
            );
            if ($this->core->request->error) {
                return $this->addError($this->core->request->errorMsg);
            }
            //endregion
        }
        $tot_courses = count($courses);
        $contents_data = $all_contents['data'] ?? [];
        $tot_contents = count($contents_data);

        $this->sendTerminal(" - Courses/Contents to backup: {$tot_courses}/{$tot_contents}");
        $all_contents = $this->core->utils->convertArrayIndexedByColumn($contents_data, 'CourseId', true);
        //endregion

        //region PROCESS and SAVE each Course to backup directory
        $saved_count = 0;
        $unchanged_count = 0;

        foreach ($courses as $course) {
            //region VALIDATE Course has KeyId
            if (!($course['KeyId'] ?? null)) {
                $this->sendTerminal("   # Skipping Course without KeyId");
                continue;
            }
            $key_id = $course['KeyId'];
            //endregion

            //region FETCH contents for this Course using KeyId
            $contents_response = [];
            if (isset($all_contents[$key_id])) {
                $contents_response = ['data' => &$all_contents[$key_id]];
            }

            $contents = [];
            if (!$this->core->request->error && ($contents_response['data'] ?? null)) {
                $contents = $contents_response['data'];
                // Sort contents by KeyId
                foreach ($contents as &$content) {
                    ksort($content);
                }
                usort($contents, function ($a, $b) {
                    return strcmp($a['KeyId'] ?? '', $b['KeyId'] ?? '');
                });
            }
            //endregion

            //region SORT $course keys alphabetically
            ksort($course);
            //endregion

            //region BUILD $course_data structure
            $course_data = [
                'CloudFrameWorkAcademyCourses' => $course,
                'CloudFrameWorkAcademyContents' => $contents
            ];
            //endregion

            //region SAVE $course_data to JSON file (skip if unchanged)
            $filename = $this->courseIdToFilename($key_id);
            $filepath = "{$backup_dir}/{$filename}";
            $json_content = $this->core->jsonEncode($course_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

            // Compare with existing local file
            if (is_file($filepath)) {
                $existing_content = file_get_contents($filepath);
                if ($existing_content === $json_content) {
                    $unchanged_count++;
                    $title = $course['CourseTitle'] ?? 'N/A';
                    $this->sendTerminal("   = Unchanged: {$filename} - {$title}");
                    continue;
                }
            }

            if (file_put_contents($filepath, $json_content) === false) {
                return $this->addError("Failed to write Course [{$key_id}] to file");
            }
            $saved_count++;
            $content_count = count($contents);
            $title = $course['CourseTitle'] ?? 'N/A';
            $this->sendTerminal("   + Saved: {$filename} - {$title} ({$content_count} contents)");
            //endregion
        }
        //endregion

        //region SEND summary to terminal
        $this->sendTerminal(str_repeat('-', 50));
        $this->sendTerminal(" - Total Courses/Contents: {$tot_courses}/{$tot_contents} (saved: {$saved_count}, unchanged: {$unchanged_count})");
        //endregion

        return true;
    }

    /**
     * Update existing Course in remote platform from local backup
     *
     * This method compares local backup with remote data and:
     * - Skips if data is identical
     * - Updates if data is different
     * - Inserts new contents that only exist in local
     * - Deletes contents that only exist in remote
     */
    public function METHOD_update_from_backup()
    {
        //region VALIDATE $course_id (required parameter)
        $course_id = $this->formParams['id'] ?? null;
        if (!$course_id) {
            return $this->addError("Missing required parameter: id. Usage: _cloudia/courses/update-from-backup?id=COURSE_KEYID");
        }
        $this->sendTerminal(" - Course to update: {$course_id}");
        //endregion

        //region SET $backup_file (path to local backup JSON file)
        $filename = $this->courseIdToFilename($course_id);
        $backup_file = $this->core->system->root_path . "/buckets/backups/Courses/{$this->platform_id}/{$filename}";
        $this->sendTerminal(" - Backup file: /buckets/backups/Courses/{$this->platform_id}/{$filename}");
        //endregion

        //region VALIDATE $backup_file exists
        if (!is_file($backup_file)) {
            return $this->addError("Backup file not found: {$backup_file}");
        }
        //endregion

        //region READ $course_data from backup file
        $json_content = file_get_contents($backup_file);
        if ($json_content === false) {
            return $this->addError("Failed to read backup file: {$backup_file}");
        }
        $course_data = $this->core->jsonDecode($json_content);
        if ($course_data === null) {
            return $this->addError("Invalid JSON in backup file: {$backup_file}");
        }
        $this->sendTerminal(" - Course data loaded successfully");
        //endregion

        //region VALIDATE $course_data has correct structure
        $local_course = $course_data['CloudFrameWorkAcademyCourses'] ?? null;
        if (!$local_course || ($local_course['KeyId'] ?? null) != $course_id) {
            return $this->addError("KeyId mismatch: file contains '{$local_course['KeyId']}' but expected '{$course_id}'");
        }
        $local_contents = $course_data['CloudFrameWorkAcademyContents'] ?? [];
        $this->sendTerminal(" - Local course loaded with " . count($local_contents) . " contents");
        //endregion

        //region FETCH remote Course for comparison
        $this->sendTerminal(" - Fetching remote Course for comparison...");
        $response = $this->core->request->get_json_decode(
            "{$this->api_base_url}/core/cfo/cfi/CloudFrameWorkAcademyCourses/display/{$course_id}?_raw&_timezone=UTC",
            ['_raw' => 1, '_timezone' => 'UTC'],
            $this->headers
        );

        $remote_course = null;
        if (!$this->core->request->error && ($response['data'] ?? null)) {
            $remote_course = $response['data'];
        }
        //endregion

        //region FETCH remote Contents for comparison
        $response = $this->core->request->get_json_decode(
            "{$this->api_base_url}/core/cfo/cfi/CloudFrameWorkAcademyContents?_raw&_timezone=UTC",
            ['filter_CourseId' => $course_id, 'cfo_limit' => 2000, '_raw' => 1, '_timezone' => 'UTC'],
            $this->headers
        );

        $remote_contents = [];
        if (!$this->core->request->error && ($response['data'] ?? null)) {
            $remote_contents = $response['data'];
        }
        $this->sendTerminal(" - Remote course found with " . count($remote_contents) . " contents");
        //endregion

        //region CHECK if local backup equals remote (skip update if unchanged)
        if ($remote_course) {
            // Build remote data structure for comparison
            $remote_course_sorted = $remote_course;
            ksort($remote_course_sorted);

            $remote_contents_sorted = $remote_contents;
            foreach ($remote_contents_sorted as &$content) {
                ksort($content);
            }
            usort($remote_contents_sorted, function ($a, $b) {
                return strcmp($a['KeyId'] ?? '', $b['KeyId'] ?? '');
            });

            $remote_data = [
                'CloudFrameWorkAcademyCourses' => $remote_course_sorted,
                'CloudFrameWorkAcademyContents' => $remote_contents_sorted
            ];

            // Build local data structure for comparison
            $local_course_sorted = $local_course;
            ksort($local_course_sorted);

            $local_contents_sorted = $local_contents;
            foreach ($local_contents_sorted as &$content) {
                ksort($content);
            }
            usort($local_contents_sorted, function ($a, $b) {
                return strcmp($a['KeyId'] ?? '', $b['KeyId'] ?? '');
            });

            $local_data = [
                'CloudFrameWorkAcademyCourses' => $local_course_sorted,
                'CloudFrameWorkAcademyContents' => $local_contents_sorted
            ];

            // Compare JSON representations
            if ($this->core->jsonEncode($local_data) === $this->core->jsonEncode($remote_data)) {
                $this->sendTerminal(" = Course [{$course_id}] is unchanged (local backup equals remote)");
                return true;
            }
        }
        //endregion

        //region COMPARE and UPDATE Course
        $course_updated = false;
        if ($remote_course) {
            ksort($local_course);
            ksort($remote_course);
            if ($this->core->jsonEncode($local_course) === $this->core->jsonEncode($remote_course)) {
                $this->sendTerminal(" - Course data is identical, skipping update");
            } else {
                $this->sendTerminal(" - Course data differs, updating...");
                $response = $this->core->request->put_json_decode(
                    "{$this->api_base_url}/core/cfo/cfi/CloudFrameWorkAcademyCourses/{$course_id}?_raw&_timezone=UTC",
                    $local_course,
                    $this->headers
                    ,true
                );
                if ($this->core->request->error || !($response['success'] ?? false)) {
                    $error_msg = $response['errorMsg'] ?? $this->core->request->errorMsg ?? 'Unknown error';
                    if (is_array($error_msg)) $error_msg = implode(', ', $error_msg);
                    return $this->addError("Failed to update Course: {$error_msg}");
                }
                $this->sendTerminal(" + Course record updated");
                $course_updated = true;
            }
        } else {
            // Remote course doesn't exist - insert it
            $this->sendTerminal(" - Remote course not found, inserting...");
            $response = $this->core->request->post_json_decode(
                "{$this->api_base_url}/core/cfo/cfi/CloudFrameWorkAcademyCourses?_raw&_timezone=UTC",
                $local_course,
                $this->headers
            );
            if ($this->core->request->error || !($response['success'] ?? false)) {
                $error_msg = $response['errorMsg'] ?? $this->core->request->errorMsg ?? 'Unknown error';
                if (is_array($error_msg)) $error_msg = implode(', ', $error_msg);
                return $this->addError("Failed to insert Course: {$error_msg}");
            }
            $this->sendTerminal(" + Course record inserted");
            $course_updated = true;
        }
        //endregion

        //region BUILD indexed arrays for contents comparison
        $local_indexed = [];
        foreach ($local_contents as $content) {
            $index = $content['KeyId'] ?? ($content['ContentTitle'] ?? null);
            if (!$index) {
                $this->sendTerminal("   # Warning: Content without KeyId or ContentTitle, skipping");
                continue;
            }
            $local_indexed[$index] = $content;
        }

        $remote_indexed = [];
        foreach ($remote_contents as $content) {
            if ($content['KeyId'] ?? null) {
                $remote_indexed[$content['KeyId']] = $content;
            }
        }
        //endregion

        //region SYNC Contents (compare, update, insert, delete)
        $this->sendTerminal(" - Syncing contents...");
        $stats = ['same' => 0, 'updated' => 0, 'inserted' => 0, 'deleted' => 0];

        // Process remote contents: update or delete
        foreach ($remote_indexed as $keyId => $remote_content) {
            if (!isset($local_indexed[$keyId])) {
                // Remote content not in local - delete it
                $content_title = $remote_content['ContentTitle'] ?? $keyId;
                $this->sendTerminal("   - Deleting: [{$keyId}] {$content_title}");
                $response = $this->core->request->delete_json_decode(
                    "{$this->api_base_url}/core/cfo/cfi/CloudFrameWorkAcademyContents/{$keyId}?_raw&_timezone=UTC",
                    $this->headers
                );
                if ($this->core->request->error || !($response['success'] ?? false)) {
                    $this->sendTerminal("     # Warning: Failed to delete");
                    $this->sendTerminal($this->core->request->errorMsg);
                    return false;
                } else {
                    $stats['deleted']++;
                }
            } else {
                // Content exists in both - compare and update if different
                ksort($remote_content);
                ksort($local_indexed[$keyId]);
                if ($this->core->jsonEncode($local_indexed[$keyId]) !== $this->core->jsonEncode($remote_content)) {
                    $content_title = $local_indexed[$keyId]['ContentTitle'] ?? $keyId;
                    $this->sendTerminal("   - Updating: [{$keyId}] {$content_title}");
                    $response = $this->core->request->put_json_decode(
                        "{$this->api_base_url}/core/cfo/cfi/CloudFrameWorkAcademyContents/{$keyId}?_raw&_timezone=UTC",
                        $local_indexed[$keyId],
                        $this->headers,
                        true
                    );
                    if ($this->core->request->error || !($response['success'] ?? false)) {
                        $this->sendTerminal("     # Warning: Failed to update");
                        $this->sendTerminal($this->core->request->errorMsg);
                        return false;
                    } else {
                        $stats['updated']++;
                    }
                } else {
                    $content_title = $remote_content['ContentTitle'] ?? $keyId;
                    $this->sendTerminal("   - Same: [{$keyId}] {$content_title}");
                    $stats['same']++;
                }
                // Remove from local to track what's left to insert
                unset($local_indexed[$keyId]);
            }
        }

        // Insert contents that exist only in local
        foreach ($local_indexed as $index => $local_content) {
            $content_title = $local_content['ContentTitle'] ?? $index;
            $has_key_id = isset($local_content['KeyId']) && !empty($local_content['KeyId']);

            if ($has_key_id) {
                // Has KeyId but wasn't in remote - try to update (might exist with different CourseId)
                $this->sendTerminal("   - Updating (new): [{$local_content['KeyId']}] {$content_title}");
                $response = $this->core->request->put_json_decode(
                    "{$this->api_base_url}/core/cfo/cfi/CloudFrameWorkAcademyContents/{$local_content['KeyId']}?_raw&_timezone=UTC",
                    $local_content,
                    $this->headers,
                    true
                );
            } else {
                // No KeyId - insert as new
                $this->sendTerminal("   - Inserting: {$content_title}");
                $response = $this->core->request->post_json_decode(
                    "{$this->api_base_url}/core/cfo/cfi/CloudFrameWorkAcademyContents?_raw&_timezone=UTC",
                    $local_content,
                    $this->headers
                );
            }

            if ($this->core->request->error || !($response['success'] ?? false)) {
                $this->sendTerminal("     # Warning: Failed to " . ($has_key_id ? 'update' : 'insert'));
                $this->sendTerminal($this->core->request->errorMsg);
                return false;
            } else {
                $stats['inserted']++;
            }
        }
        //endregion

        //region SEND summary to terminal
        $this->sendTerminal(str_repeat('-', 50));
        $this->sendTerminal(" + Course [{$course_id}] sync complete:");
        $this->sendTerminal("   - Course: " . ($course_updated ? "updated" : "unchanged"));
        $this->sendTerminal("   - Contents same: {$stats['same']}");
        $this->sendTerminal("   - Contents updated: {$stats['updated']}");
        $this->sendTerminal("   - Contents inserted: {$stats['inserted']}");
        $this->sendTerminal("   - Contents deleted: {$stats['deleted']}");
        //endregion

        //region GET Last version of the Course (only if changes were made)
        if ($course_updated || $stats['updated'] > 0 || $stats['inserted'] > 0 || $stats['deleted'] > 0) {
            $this->sendTerminal(" - Backing up latest version from remote...");
            $this->formParams['id'] = $course_id;
            $this->METHOD_backup_from_remote();
        }
        //endregion

        return true;
    }

    /**
     * Insert new Course in remote platform from local backup
     */
    public function METHOD_insert_from_backup()
    {
        //region VALIDATE $course_id (required parameter)
        $course_id = $this->formParams['id'] ?? null;
        if (!$course_id) {
            return $this->addError("Missing required parameter: id. Usage: _cloudia/courses/insert-from-backup?id=COURSE_KEYID");
        }
        $this->sendTerminal(" - Course to insert: {$course_id}");
        //endregion

        //region SET $backup_file (path to local backup JSON file)
        $filename = $this->courseIdToFilename($course_id);
        $backup_file = $this->core->system->root_path . "/buckets/backups/Courses/{$this->platform_id}/{$filename}";
        $this->sendTerminal(" - Backup file: /buckets/backups/Courses/{$this->platform_id}/{$filename}");
        //endregion

        //region VALIDATE $backup_file exists
        if (!is_file($backup_file)) {
            return $this->addError("Backup file not found: {$backup_file}");
        }
        //endregion

        //region READ $course_data from backup file
        $json_content = file_get_contents($backup_file);
        if ($json_content === false) {
            return $this->addError("Failed to read backup file: {$backup_file}");
        }
        $course_data = $this->core->jsonDecode($json_content);
        if ($course_data === null) {
            return $this->addError("Invalid JSON in backup file: {$backup_file}");
        }
        $this->sendTerminal(" - Course data loaded successfully");
        //endregion

        //region VALIDATE $course_data has correct structure
        $course = $course_data['CloudFrameWorkAcademyCourses'] ?? null;
        if (!$course || ($course['KeyId'] ?? null) != $course_id) {
            return $this->addError("KeyId mismatch: file contains '{$course['KeyId']}' but expected '{$course_id}'");
        }
        //endregion

        //region CHECK if Course already exists in remote platform
        $check_response = $this->core->request->get_json_decode(
            "{$this->api_base_url}/core/cfo/cfi/CloudFrameWorkAcademyCourses/{$course_id}?_raw&_timezone=UTC",
            [],
            $this->headers
        );
        if (!$this->core->request->error && ($check_response['data'] ?? null)) {
            return $this->addError("Course [{$course_id}] already exists in remote platform. Use update-from-backup instead.");
        }
        //endregion

        //region INSERT Course in remote platform via API
        $this->sendTerminal(" - Inserting Course in remote platform...");
        $response = $this->core->request->post_json_decode(
            "{$this->api_base_url}/core/cfo/cfi/CloudFrameWorkAcademyCourses?_raw&_timezone=UTC",
            $course,
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

        // Get the new KeyId from the response
        $new_key_id = $response['data']['KeyId'] ?? $course['KeyId'] ?? null;
        $this->sendTerminal(" + Course record inserted (KeyId: {$new_key_id})");
        //endregion

        //region INSERT contents in remote platform
        $contents = $course_data['CloudFrameWorkAcademyContents'] ?? [];
        if ($contents) {
            $this->sendTerminal(" - Inserting {" . count($contents) . "} contents...");
            foreach ($contents as $content) {
                $content_key = $content['KeyId'] ?? null;
                if (!$content_key) {
                    $response = $this->core->request->post_json_decode(
                        "{$this->api_base_url}/core/cfo/cfi/CloudFrameWorkAcademyContents?_raw&_timezone=UTC",
                        $content,
                        $this->headers
                    );
                } else {
                    $response = $this->core->request->put_json_decode(
                        "{$this->api_base_url}/core/cfo/cfi/CloudFrameWorkAcademyContents/{$content_key}?_raw&_timezone=UTC",
                        $content,
                        $this->headers,
                        true
                    );
                }

                if ($this->core->request->error || !($response['success'] ?? false)) {
                    $content_title = $content['ContentTitle'] ?? $content_key ?? 'unknown';
                    $this->sendTerminal("   # Warning: Failed to insert content [{$content_title}]");
                    $this->sendTerminal($this->core->request->errorMsg);
                    return false;
                }
            }
            $this->sendTerminal(" + Contents inserted");
        }
        //endregion

        //region SEND success message to terminal
        $this->sendTerminal(str_repeat('-', 50));
        $this->sendTerminal(" + Course [{$course_id}] inserted successfully in remote platform");
        //endregion

        //region GET Last version of the Course
        $this->formParams['id'] = $course_id;
        $this->METHOD_backup_from_remote();
        //endregion

        return true;
    }
}
