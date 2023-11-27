<?php
/**
 * Class to facilitate the Google Drive and Google Documents creation
 * Last update: 2023-09-13
 */
if (!defined("_Google_CLASS_GoogleDocuments")) {
    define("_Google_CLASS_GoogleDocuments", TRUE);

    /**
     * Google Drive Class to give support to GoogleDocuments
     * Beginning Sept. 30, 2020, you will no longer be able to place a file in multiple parent
     * https://developers.google.com/drive/api/guides/about-files
     * @package LabClasses
     */
    class GoogleDrive
    {
        protected $version = '20230913';

        /** @var Core7 $core */
        private $core;

        /** @var Google\Client $googleClient will manage the drive access properties */
        protected $googleClient = null;

        /** @var Google\Service\Drive $drive will manage the drive access properties */
        protected $drive = null;

        // Google_Service_Drive_DriveFile
        /** @var Google\Service\Drive\DriveFile $file will manage  files in general */
        protected $driveFile = null;

        // https://hotexamples.com/examples/-/Google_Service_Drive_Permission/-/php-google_service_drive_permission-class-examples.html
        /** @var Google\Service\Drive\Permission $drivePermission will manage  files in general */
        protected $drivePermission = null;

        /** @var string $pageToken last pageToken used */
        var $pageToken = '';

        /** @var int $maxRecursiveLevels Max recursive levels for a tree */
        var $maxRecursiveLevels = 10;

        /** @var string $lasQuery query of last search */
        var $lastQuery = '';

        // Error Variables
        var $errorCode = 0;
        var $error = false;
        var $errorMsg = [];

        /**
         * CloudFramework GoogleDocument class
         * @param Core7 $core
         * @param array $config
         */
        function __construct(Core7 &$core, $config = [])
        {
            $this->core = $core;
            $this->googleClient = $this->getGoogleClient($config);
            $this->drive = new Google_Service_Drive($this->googleClient);
            $this->driveFile = new Google_Service_Drive_DriveFile($this->googleClient);
            $this->drivePermission = new Google_Service_Drive_Permission($this->googleClient);
        }


        /**
         * Assign to a $user_email priveleges over a $document_id with a specific rol
         * https://stackoverflow.com/questions/37846076/create-a-spreadsheet-api-v4
         * https://developers.google.com/drive/api/v3/ref-roles
         * $permissions_examples: permissions = [
         * {
         * 'type': 'user',
         * 'role': 'writer',
         * 'emailAddress': 'user@example.com'
         * }, {
         * 'type': 'domain',
         * 'role': 'writer',
         * 'domain': 'example.com'
         * }]
         * @param $document_id
         * @param $user_email
         * @param string $role values can be: reader, writer, commenter, fileOrganizer(only shared drives), organizer(only shared drives), owner
         * @return bool
         */
        public function assignDrivePermissions(string $document_id, string $user_email, string $role = 'writer')
        {

            if (!in_array($role, ['reader', 'writer', 'commenter', 'owner'])) return $this->addError(400, "the role in assignDocumentPermissions only can be ['reader','writer','commenter','owner']. The value sent is: " . $role);

            $permissions = $this->getDrivePermissions($document_id);
            if ($this->error) return;
            try {

                //region EVALUATE if the user has already a permission and UPDATED it
                foreach ($permissions as $permission) {
                    if ($permission['email'] == $user_email) {
                        if ($permission['role'] == $role) return true;
                        else {
                            //region SET $optParams based on $role
                            if ($role == 'owner')
                                $optParams = array('transferOwnership' => true, 'supportsAllDrives' => true);
                            else
                                $optParams = array('supportsAllDrives' => true);
                            //endregion

                            //region UPDATE $role
                            $this->drivePermission->setRole($role);
                            $this->drive->permissions->update($document_id, $permission['id'], $this->drivePermission, $optParams);
                            //endregion
                            return true;
                        }
                    }
                }
                //endregion

                //region ELSE create the permission

                //region SET $optParams based on $role
                if ($role == 'owner')
                    $optParams = array('sendNotificationEmail' => true, 'transferOwnership' => true, 'supportsAllDrives' => true);
                else
                    $optParams = array('sendNotificationEmail' => false, 'supportsAllDrives' => true);
                //endregion

                $this->drivePermission->setEmailAddress($user_email);
                $this->drivePermission->setType('user'); // anyone, user, owner
                $this->drivePermission->setRole($role); // reader, writer

                // The user $user_mail has to be in the same organization than the owner

                $this->drive->permissions->create($document_id, $this->drivePermission, $optParams);
                return true;
                //endregion

            } catch (Exception $e) {
                $this->addError($e->getCode(), $e->getMessage());
                return false;
            }
        }

        /**
         * List permissions of a $document_id
         * @param $document_id
         * @return bool
         */
        public function getDrivePermissions($document_id)
        {

            $optParams = array('supportsAllDrives' => true, 'fields' => '*');
            try {
                /** @var Google\Service\Drive\PermissionList $permissions */
                $permissions = $this->drive->permissions->listPermissions($document_id, $optParams);
                $ret = [];
                /** @var Google\Service\Drive\Permission $permission */
                foreach ($permissions as $permission) {
                    //_print($permission);
                    $details = $permission->getPermissionDetails();
                    $ret[] = [
                        'id' => $permission->getId(),
                        'kind' => $permission->getKind(),
                        'role' => $permission->getRole(),
                        'pendingOwner' => $permission->getPendingOwner(),
                        'name' => $permission->getDisplayName(),
                        'email' => $permission->getEmailAddress(),
                        'expirationTime' => $permission->getExpirationTime()];
                }
                return $ret;
            } catch (Exception $e) {
                $this->addError($e->getCode(), $e->getMessage());
                return false;
            }
        }


        /**
         * Create a folder under a parent
         * @param string $folder_name
         * @param string $parent_id value of the parent folder id
         * @return array|void
         */
        public function createDriveFolder(string $folder_name, string $parent_id = "")
        {
            try {
                //$file = new Google_Service_Drive_DriveFile();
                $this->driveFile->setName($folder_name);
                $this->driveFile->setMimeType('application/vnd.google-apps.folder');
                if ($parent_id)
                    $this->driveFile->setParents([$parent_id]);
                $file = $this->drive->files->create($this->driveFile, ['supportsAllDrives' => true]);
                return $this->getArrayFromDriveFile($file);
            } catch (Exception $e) {
                return $this->addError($e->getCode(), $e->getMessage());
            }

        }

        /**
         * Create a folder under a parent
         * @param string $id
         * @return array|void
         */
        public function getDriveFile(string $id)
        {
            try {
                $optParams = array('fields' => "*", 'supportsAllDrives' => true);
                /** @var Google\Service\Drive\DriveFile $file */
                $file = $this->drive->files->get($id, $optParams);
                return $this->getArrayFromDriveFile($file);
            } catch (Exception $e) {
                return $this->addError($e->getCode(), $e->getMessage());
            }

        }

        /**
         * Upload $content to $file_name drive
         * @param $content
         * @param $file_name
         * @param string $parent_id optionally assign the document to a specific parent
         * @param string $mime_type optionally you can assign a mimeType
         * @return array|void
         */
        public function uploadDriveFile(&$content,$file_name,$parent_id="",$mime_type='')
        {
            try {
                //$file = new Google_Service_Drive_DriveFile();
                $this->driveFile->setName($file_name);
                $this->driveFile->setMimeType($mime_type);
                if ($parent_id)
                    $this->driveFile->setParents([$parent_id]);
                $file = $this->drive->files->create($this->driveFile, ['data' => $content,'uploadType'=>'multipart','fields' => "*"]);
                return $this->getArrayFromDriveFile($file);
            } catch (Exception $e) {
                return $this->addError($e->getCode(), $e->getMessage());
            }

        }
        /**
         * Call to uploadDriveFile passing $mime_type = application/vnd.google-apps.spreadsheet
         * @see uploadDriveFile
         */
        public function uploadDriveFileAsSpreadSheet(&$content,$file_name,$parent_id=""){
            return $this->uploadDriveFile($content,$file_name,$parent_id,'application/vnd.google-apps.spreadsheet');
        }
        /**
         * Call to uploadDriveFile passing $mime_type = application/vnd.google-apps.document
         * @see uploadDriveFile
         */
        public function uploadDriveFileAsDoc(&$content,$file_name,$parent_id=""){
            return $this->uploadDriveFile($content,$file_name,$parent_id,'application/vnd.google-apps.document');
        }
        /**
         * Call to uploadDriveFile passing $mime_type = application/vnd.google-apps.presentation
         * @see uploadDriveFile
         */
        public function uploadDriveFileAsPresentation(&$content,$file_name,$parent_id=""){
            return $this->uploadDriveFile($content,$file_name,$parent_id,'application/vnd.google-apps.presentation');
        }



        /**
         * Create a folder under a parent
         * @param string $id
         * @return array|void
         */
        public function downloadDriveFile(string $id,string $file_path)
        {
            try {
                $optParams = array('alt' => "media", 'supportsAllDrives' => true);
                /** @var GuzzleHttp\Psr7\Response $content */
                $content = $this->drive->files->get($id, $optParams);
                $handle = fopen($file_path, "w+");
                while (!$content->getBody()->eof()) {
                    fwrite($handle, $content->getBody()->read(1024));
                }
                fclose($handle);
                return($file_path);
            } catch (Exception $e) {
                return $this->addError($e->getCode(), $e->getMessage());
            }

        }


        /**
         * Export a file
         * https://developers.google.com/drive/api/guides/manage-downloads
         * https://developers.google.com/drive/api/guides/ref-export-formats
         * Documents
        Microsoft Word	application/vnd.openxmlformats-officedocument.wordprocessingml.document	.docx
        OpenDocument	application/vnd.oasis.opendocument.text	.odt
        Rich Text	application/rtf	.rtf
        PDF	application/pdf	.pdf
        Plain Text	text/plain	.txt
        Web Page (HTML)	application/zip	.zip
        EPUB	application/epub+zip	.epub
         * Spreadsheets
        Microsoft Excel	application/vnd.openxmlformats-officedocument.spreadsheetml.sheet	.xlsx
        OpenDocument	application/x-vnd.oasis.opendocument.spreadsheet	.ods
        PDF	application/pdf	.pdf
        Web Page (HTML)	application/zip	.zip
        Comma Separated Values (first-sheet only)	text/csv	.csv
        Tab Separated Values (first-sheet only)	text/tab-separated-values	.tsv
         * Presentations
        Microsoft PowerPoint	application/vnd.openxmlformats-officedocument.presentationml.presentation	.pptx
        ODP	application/vnd.oasis.opendocument.presentation	.odp
        PDF	application/pdf	.pdf
        Plain Text	text/plain	.txt
        JPEG (first-slide only)	image/jpeg	.jpg
        PNG (first-slide only)	image/png	.png
        Scalable Vector Graphics (first-slide only)	image/svg+xml	.svg
         * Drawings
        PDF	application/pdf	.pdf
        JPEG	image/jpeg	.jpg
        PNG	image/png	.png
        Scalable Vector Graphics	image/svg+xml	.svg
         * Apps
        Script	JSON	application/vnd.google-apps.script+json	.json
         * @param string $id of the document in drive
         * @param string $file_path where to store the exported file
         * @param string $mimeType for the file to produce. By default 'application/pdf'
         * @return array|void
         */
        public function export(string $id,string $file_path,string $mimeType = 'application/pdf')
        {

            if(!$file = $this->getDriveFile($id)) return;
            if(strpos($file['mimeType'],'google')===false)
                return $this->addError('conflict',"The document is not a Google Workspace document. Current type: ".$file['mimeType']);
            try {
                $optParams = array('alt' => "media");
                /** @var GuzzleHttp\Psr7\Response $content */
                $content = $this->drive->files->export($id,$mimeType,$optParams);
                $handle = fopen($file_path, "w+");
                while (!$content->getBody()->eof()) {
                    fwrite($handle, $content->getBody()->read(1024));
                }
                fclose($handle);
                return($file_path);

            } catch (Exception $e) {
                $this->addError($e->getCode(), $e->getMessage());
                return false;
            }
        }

        /**
         * Return an array structured taking a DriveFile
         * @param \Google\Service\Drive\DriveFile $file
         * @return array
         */
        protected function getArrayFromDriveFile(\Google\Service\Drive\DriveFile &$file, bool $avoid_empty_fields = false, array $extra_data = []): array
        {

            //region FEED $ret
            $permissions_to_array = [];
            $permissions = $file->getPermissions();
            /** @var Google\Service\Drive\Permission $permission */
            foreach ($permissions as $permission) {
                //_print($permission);
                $details = $permission->getPermissionDetails();
                $permissions_to_array[] = [
                    'id' => $permission->getId(),
                    'kind' => $permission->getKind(),
                    'role' => $permission->getRole(),
                    'pendingOwner' => $permission->getPendingOwner(),
                    'name' => $permission->getDisplayName(),
                    'email' => $permission->getEmailAddress(),
                    'expirationTime' => $permission->getExpirationTime()];
            }

            $ret = [
                'id' => $file->getId(),
                'name' => $file->getName(),
                'kind' => $file->getKind(),
                'description' => $file->getDescription(),
                'mimeType' => $file->getMimeType(),
                'size' => $file->getSize(),
                'url' => $this->getUrlFromMimeType($file),
                'created' => $file->getCreatedTime(),
                'parents' => $file->getParents(),
                'permissions' => $permissions_to_array,
                'shared_drive_id' => $file->getDriveId(),
                //'shared_drive_name'=>$this->getSharedDriveInfo($file->getDriveId())['name']??null,
            ];
            //endregion

            //region DELETE fields from $ret with empty data if $avoid_empty_fields
            if ($avoid_empty_fields) {
                if ($ret['name'] === null) unset($ret['name']);
                if ($ret['kind'] === null) unset($ret['kind']);
                if ($ret['description'] === null) unset($ret['description']);
                if ($ret['mimeType'] === null) unset($ret['mimeType']);
                if ($ret['created'] === null) unset($ret['created']);
                if (!$ret['parents']) unset($ret['parents']);
                if (!$ret['permissions']) unset($ret['permissions']);
                if ($ret['shared_drive_id'] === null) unset($ret['shared_drive_id']);
            }
            //endregion

            //region ADD $extra_data to $ret
            if ($extra_data) $ret += $extra_data;
            //endregion

            return $ret;
        }

        /**
         * Creat and Drive structure based on $structure
         * @param string $parent
         * @param array $structure
         * @return array|void
         */
        public function createDriveFolderStructure(string $parent, array $structure)
        {
            if ($parent != 'root' && !$folder = $this->getDriveFile($parent)) return;
            if ($parent != 'root' && $folder['kind'] != 'drive#file') return $this->addError(400, '$parent is not a folder');
            return $this->getDriveFolderRecursiveStructure($parent, $structure);
        }

        /**
         * Create under $parent drive folder the structure defined in $structure
         * @param string $parent
         * @param array $structure
         * @return array
         */
        private function getDriveFolderRecursiveStructure(string $parent, array $structure)
        {

            $result = [];
            foreach ($structure as $folder_name => $properties) {

                //region INIT $result[$folder_name]
                $result[$folder_name] = ['_parent' => $parent];
                //endregion

                //region SEARCH $folders with name=$folder_name. If not found create it
                $folders = $this->searchDriveFiles(['parent' => $parent, 'name' => $folder_name, 'onlyFolders' => true]);
                if ($this->error) {
                    $result[$folder_name]['error'] = $this->errorMsg;
                    return $result;
                }
                if (!$folders) {
                    if (!$folder = $this->createDriveFolder($folder_name, $parent)) {
                        $result[$folder_name]['error'] = $this->errorMsg;
                        return $result;
                    }
                    $folders = [$folder];
                }
                //endregion

                //region UPDATE $result[$folder_name]['_id'] and ['_permissions']
                $folder = $folders[0];
                $result[$folder_name]['_id'] = $folder['id'];
                if (is_array($properties['_permissions'] ?? null)) {
                    $result[$folder_name]['_permissions'] = [];
                    foreach (($folder['permissions'] ?? []) as $permission) {
                        $result[$folder_name]['_permissions'][$permission['email']] = $permission['role'];
                    }
                    foreach ($properties['_permissions'] as $permission_email => $permission_role) {
                        if (!isset($result['_permissions'][$permission_email]) || $result['_permissions'][$permission_email] != $permission_role) {
                            if ($this->assignDrivePermissions($folder['id'], $permission_email, $permission_role))
                                $result[$folder_name]['_permissions'][$permission_email] = $permission_role;
                            else {
                                $result[$folder_name]['_permissions'][$permission_email] = $this->errorMsg;
                                return $result;
                            }
                        }
                    }
                }
                //endregion

                //region DELETE repeated folders if they exist
                for ($i = 1, $tr = count($folders); $i < $tr; $i++) {
                    $this->deleteDriveFile($folder['id']);
                    if ($this->error) {
                        $result[$folder_name]['_delete_repeated_folder']['_id'] = $folder['id'];
                        $result[$folder_name]['_delete_repeated_folder']['error'] = $this->errorMsg;
                        return $result;
                    }
                    if (!isset($result[$folder_name]['_delete_repeated_folders'])) $result[$folder_name]['_delete_repeated_folders'] = [];
                    $result[$folder_name]['_delete_repeated_folders'][] = $folder['id'];
                }
                //endregion

                //region SEARCH subfolders and call recursively to recursiveStructure
                //every $property_name string starting with '_' is ignored
                foreach ($properties as $property_name => $property_data) if ($property_name[0] != '_') {
                    $result[$folder_name] += $this->getDriveFolderRecursiveStructure($result[$folder_name]['_id'], [$property_name => $property_data]);
                }
                //endregion

            }
            return $result;
        }

        /**
         * Search files in the user Drive
         * Based on: https://developers.google.com/drive/api/guides/ref-search-terms#operators
         * @param array $options options of the search
         *
         * @options string q Query string. Some examples of queries: "'me' in owners and trashed = false" or "sharedWithMe"
         * @options string name object name in drive. If q is sent the the string " and name='{$name}'" is added
         * @options string parent id of the parent of the object. 'root' as default . If q is sent the the string " and '{$parent}' in parents" is added
         * @options bool onlyFolders If true then it returns only folders
         * @options bool onlyFiles If true then it returns all files but folders
         * @options bool trashed Search in files where trashed is true or false. 'false' as default
         * @options bool sharedWithMe Search shared with me
         * @options string fields to show in the response. '*' as default
         * @options int maxFiles max number of results to return
         * @options string pageToken token to use based on last result taken from $this->pageToken
         * @options string orderBy A comma-separated list of sort keys. Valid keys are
         * 'createdTime', 'folder', 'modifiedByMeTime', 'modifiedTime', 'name',
         * 'name_natural', 'quotaBytesUsed', 'recency', 'sharedWithMeTime', 'starred',
         * and 'viewedByMeTime'. Each key sorts ascending by default, but may be
         * reversed with the 'desc' modifier. Example usage:
         * ?orderBy=folder,modifiedTime desc,name. Please note that there is a current
         * limitation for users with approximately one million files in which the
         * requested sort order is ignored. 'createdTime desc' as default
         * @return array|void
         */
        public function searchDriveFiles(array $options = ['q' => '', 'trashed' => false, 'name' => '', 'parent' => 'root', 'fields' => '*', 'maxFiles' => 100, 'pageToken' => '', 'orderBy' => 'createdTime desc'])
        {

            //TODO: show only elements "trashed=false": https://developers.google.com/drive/api/v3/reference/files/list
            //$options = ['corpora'=>'drive','driveId'=>'0AOLA5z-c1M42Uk9PVA','supportsAllDrives'=>true,'includeItemsFromAllDrives'=>true,'q'=>"'1UbGIWCwageqB-cO7AYAiVPywSrFKp49f' in parents"];

            $q = $options['q'] ?? '';
            $name = $options['name'] ?? null;
            $trashed = ($options['trashed'] ?? null) ? true : false;
            $parent = $options['parent'] ?? '';
            $fields = $options['fields'] ?? '*';
            $maxFiles = intval($options['maxFiles'] ?? 100);
            $pageToken = $options['pageToken'] ?? null;
            $orderBy = $options['orderBy'] ?? 'createdTime desc';
            $onlyFolders = $options['onlyFolders'] ?? null;
            $onlyFiles = $options['onlyFiles'] ?? null;
            $sharedWithMe = $options['sharedWithMe'] ?? null;

            //region SET $parameters to search files
            $parameters = ['orderBy' => $orderBy, 'includeItemsFromAllDrives' => true, 'supportsAllDrives' => true];
            if ($fields) $parameters['fields'] = $fields;

            if (intval($maxFiles) <= 0) $maxFiles = 100;
            if ($maxFiles < 100) $parameters['pageSize'] = $maxFiles;

            $parameters['q'] = $q;
            if ($parameters['q']) $parameters['q'] .= " and ";
            $parameters['q'] .= "trashed=" . (($trashed) ? 'true' : 'false');

            if ($parent) {
                if ($parameters['q']) $parameters['q'] .= " and ";
                $parameters['q'] .= "'{$parent}' in parents";
            }
            if ($sharedWithMe) {
                if ($parameters['q']) $parameters['q'] .= " and ";
                $parameters['q'] .= "sharedWithMe";
            }
            if ($name) {
                if ($parameters['q']) $parameters['q'] .= " and ";
                $parameters['q'] .= "name='{$name}'";
            }
            if ($onlyFolders) {
                if ($parameters['q']) $parameters['q'] .= " and ";
                $parameters['q'] .= "mimeType='application/vnd.google-apps.folder'";
            }
            if ($onlyFiles && !$onlyFolders) {
                if ($parameters['q']) $parameters['q'] .= " and ";
                $parameters['q'] .= "mimeType!='application/vnd.google-apps.folder'";
            }

            if ($pageToken) $parameters['pageToken'] = $pageToken;
            //endregion

            //region RESET $this->pageToken
            $this->pageToken = $pageToken;
            //endregion

            //region RESET $this->pageToken
            $this->lastQuery = $parameters['q'] . ' [orderBy ' . $orderBy . ']';
            //endregion

            $ret = [];
            $i = 0;
            try {
                do {
                    /** @var Google\Service\Drive\FileList $files_query */
                    $files_query = $this->drive->files->listFiles($parameters);
                    $files = $files_query->getFiles();
                    foreach ($files as $file) {
                        if ($i < $maxFiles) {
                            $ret[] = $this->getArrayFromDriveFile($file);
                        }
                        $i++;
                    }
                } while (($parameters['pageToken'] = $this->pageToken = $files_query->getNextPageToken()) && ($i < $maxFiles));
                return $ret;
            } catch (Exception $e) {
                return $this->addError($e->getCode(), $e->getMessage());
            }
        }


        /**
         * Return all folder files under $parent_id
         * @param string $parent_id
         * @param int $maxFiles
         * @return array|void
         * @see getDriveFiles
         */
        public function getDriveFolders(string $parent_id = 'root', int $maxFiles = 1000)
        {
            if (!$parent_id) $parent_id = 'root';
            return $this->searchDriveFiles(['q' => "mimeType='application/vnd.google-apps.folder'", 'parent' => $parent_id, 'maxFiles' => $maxFiles]);
        }

        /**
         * Return all files under $parent_id except folders
         * @param string $parent_id
         * @param int $maxFiles
         * @return array|void
         * @see getDriveFolders
         */
        public function getDriveFiles(string $parent_id = 'root', int $maxFiles = 1000)
        {
            if (!$parent_id) $parent_id = 'root';
            return $this->searchDriveFiles(['q' => "mimeType!='application/vnd.google-apps.folder'", 'parent' => $parent_id, 'maxFiles' => $maxFiles]);
        }


        /**
         * @param $id_drive
         * @return array|mixed|void
         */
        public function getSharedDriveInfo($id_drive)
        {
            if (isset($this->drives_data[$id_drive])) return $this->drives_data[$id_drive];
            try {
                $drive_info = $this->drive->drives->get('0AOLA5z-c1M42Uk9PVA');
                $this->drives_data[$id_drive] = [
                    'name' => $drive_info->getName(),
                    'kind' => $drive_info->getKind(),
                    'created' => $drive_info->getCreatedTime(),
                    'capabilities' => $drive_info->getCapabilities(),
                ];
                return $this->drives_data[$id_drive];
            } catch (Exception $e) {
                return $this->addError($e->getCode(), $e->getMessage());
            }

        }

        /**
         * Return the URL to drive object based on $file->getMimeType();
         * @param \Google\Service\Drive\DriveFile $file
         * @return string
         */
        private function getUrlFromMimeType(Google\Service\Drive\DriveFile $file)
        {
            $mimeType = $file->getMimeType();
            $url = '';
            switch ($mimeType) {
                case "application/vnd.google-apps.folder":
                    $url = "https://drive.google.com/drive/folders/" . $file->getId();
                    break;
                case "application/vnd.google-apps.spreadsheet":
                    $url = "https://docs.google.com/spreadsheets/d/" . $file->getId();
                    break;
                case "application/vnd.google-apps.presentation":
                    $url = "https://docs.google.com/presentation/d/" . $file->getId();
                    break;
                case "application/vnd.google-apps.document":
                    $url = "https://docs.google.com/document/d/" . $file->getId();
                    break;
                case "text/html":
                case "application/pdf":
                default:
                    $url = "https://docs.google.com/file/d/" . $file->getId();
                    break;
            }
            return $url;
        }

        /**
         * Delete a folder in drive
         * @param string $fileId
         * @return bool
         */
        public function deleteDriveFolder($folderId)
        {
            try {
                $file = $this->drive->files->get($folderId, ['supportsAllDrives' => true]);
                if ($file->getKind() != 'drive#file') return $this->addError(400, 'folderId is not a folder');
                return $this->deleteDriveFile($folderId);
            } catch (Exception $e) {
                $this->addError($e->getCode(), $e->getMessage());
                return false;
            }
        }

        /**
         * Get folder structure
         * @param string $fileId
         * @return bool
         */
        public function getDriveFolderTree($folderId)
        {
            $this->core->__p->add('getDriveFolderTree ', "{$folderId}", 'note');

            try {
                $tree = ['_parent' => null, '_id' => $folderId, '_name' => $folderId, '_url' => null, '_folders' => []];
                if ($folderId != 'root') {
                    $file = $this->drive->files->get($folderId, ['supportsAllDrives' => true, 'fields' => '*']);
                    if ($file->getKind() != 'drive#file') return $this->addError(400, 'folderId is not a folder');
                    $tree['_parent'] = $file->parents[0] ?? null;
                    $tree['_name'] = $file->getName();
                    $tree['_url'] = $this->getUrlFromMimeType($file);
                }
                $this->recursiveFolderTree($folderId, $tree['_folders']);
                $this->core->__p->add('getDriveFolderTree ', '', 'endnote');

                return $tree;
            } catch (Exception $e) {
                $error = json_decode($e->getMessage(), true);
                $error['error']['folderId'] = $folderId;
                $this->addError($e->getCode(), $error);
                $this->core->__p->add('getDriveFolderTree ', '', 'endnote');
                return false;
            }
        }

        /**
         * Recursive function to create a tree
         * @param $parent_id
         * @param $folder
         * @param int $level current level of recursivity. 0 default value
         * @return bool|void
         */
        private function recursiveFolderTree($parent_id, &$folder, $level = 0)
        {
            if ($level > $this->maxRecursiveLevels) return false;
            $q = "'{$parent_id}' in parents and mimeType='application/vnd.google-apps.folder'";
            try {
                $files_query = $this->drive->files->listFiles(['q' => $q, 'orderBy' => 'name', 'supportsAllDrives' => true]);
                $files = $files_query->getFiles();
                $index = count($folder);
                $folder[$index] = [];
                foreach ($files as $file) {
                    $folder[$index][] = [
                        '_parent' => $parent_id,
                        '_id' => $file->getId(),
                        '_name' => $file->getName(),
                        '_url' => $this->getUrlFromMimeType($file),
                    ];
                }
                $level++;
                foreach ($folder[$index] as $i => $info) {
                    $folder[$index][$i]['_folders'] = [];
                    if (!$this->recursiveFolderTree($info['_id'], $folder[$index][$i]['_folders'], $level)) return;
                }
                return true;
            } catch (Exception $e) {
                $error = json_decode($e->getMessage(), true);
                $error['error']['folderId'] = $parent_id;
                $this->addError($e->getCode(), $error);
                return false;
            }
        }

        /**
         * Update a file in drive of any kind
         * @param string $fileId
         * @param array $data Data to update. Variables allowd: 'name'
         * @return array|void
         */
        public function updateDriveFile(string $fileId, array $data)
        {

            $start_time = microtime(true);
            $this->core->__p->add('updateDriveFile ', "{$fileId}->" . json_encode($data), 'note');
            /** @var Google\Service\Drive\DriveFile $file */
            $file = null;
            try {
                $fileData = new \Google\Service\Drive\DriveFile();
                if ($data['name'] ?? null) $fileData->setName($data['name']);
                $optParams = array('supportsAllDrives' => true);
                $file = $this->drive->files->update($fileId, $fileData, $optParams);
                $this->core->__p->add('updateDriveFile ', '', 'endnote');
                return $this->getArrayFromDriveFile($file, true, ['time-to-update-file' => round(microtime(true) - $start_time, 4)]);
            } catch (Exception $e) {
                $error = json_decode($e->getMessage(), true);
                $error['error']['fileId'] = $fileId;
                $this->addError($e->getCode(), $error);
                $this->core->__p->add('updateDriveFile ', '', 'endnote');
                return;
            }
        }

        /**
         * Move a file in drive of any kind
         * The error codes can be: 404 = not found, 403 = insufficient permissions
         * @param string $fileId
         * @param string $destId id of the folder dest
         * @return array|void
         */
        public function moveDriveFile(string $fileId, string $destId)
        {
            $start_time = microtime(true);
            $this->core->__p->add('moveDriveFile ', "{$fileId}->{$destId}", 'note');
            /** @var Google\Service\Drive\DriveFile $file */
            $file = null;
            try {
                $optParams = array('fields' => "id,parents", 'supportsAllDrives' => true);
                $file = $this->drive->files->get($fileId, $optParams);
                $optParams = array('supportsAllDrives' => true, 'addParents' => $destId, 'removeParents' => $file->parents[0], 'fields' => 'id,parents');
                $file = $this->drive->files->update($fileId, new \Google\Service\Drive\DriveFile(), $optParams);
                if(!$file->parents) return $this->addError('409','You do not have right permissions to see parents in the file');
                $this->core->__p->add('moveDriveFile ', '', 'endnote');
                return $this->getArrayFromDriveFile($file, true, ['$destId'=>$destId,'time-to-move-file' => round(microtime(true) - $start_time, 4)]);
            } catch (Exception $e) {
                $error = json_decode($e->getMessage(), true);
                $error['error']['fileId'] = $fileId;
                $error['error']['folderDest'] = $destId;
                if ($file) $error['error']['file'] = $this->getArrayFromDriveFile($file, true);
                $this->addError($e->getCode(), $error);
                $this->core->__p->add('moveDriveFile ', '', 'endnote');
                return;
            }
        }


        /**
         * Move all the files where parent is $folderId to $destId
         * @param string $folderId
         * @param string $destId id of the folder dest
         * @return bool
         */
        public function moveDriveFiles(string $folderId, string $destId)
        {

            //region CHECK parameters
            if (!$folderId) return $this->addError(400, '$folderId can not be empty');
            if (!$destId) return $this->addError(400, '$destId can not be empty');
            if ($destId == $folderId) return $this->addError(400, '$folderId and $destId are the same');
            //endregion

            $this->core->__p->add('moveDriveFiles ', "{$folderId}->{$destId}", 'note');
            // https://developers.google.com/drive/api/guides/fields-parameter
            $parameters = ['fields' => 'files(id,name,parents)', 'includeItemsFromAllDrives' => true, 'supportsAllDrives' => true];
            $parameters['q'] = "'{$folderId}' in parents";
            $ret = [];
            $i = 0;
            /** @var Google\Service\Drive\DriveFile $file */
            $file = null;
            try {
                do {
                    /** @var Google\Service\Drive\FileList $files_query */
                    $files_query = $this->drive->files->listFiles($parameters);
                    $files = $files_query->getFiles();
                    foreach ($files as $file) {
                        $start_time = microtime(true);

                        $optParams = array('supportsAllDrives' => true, 'addParents' => $destId, 'removeParents' => $file->parents[0], 'fields' => 'id,parents');
                        $file = $this->drive->files->update($file->getId(), new \Google\Service\Drive\DriveFile(), $optParams);
                        $ret[] = $this->getArrayFromDriveFile($file, true, ['time-to-move-file' => round(microtime(true) - $start_time, 4)]);
                        $i++;
                    }
                } while (($parameters['pageToken'] = $this->pageToken = $files_query->getNextPageToken()) && $i < 100);
                $this->core->__p->add('moveDriveFiles ', '', 'endnote');
                return $ret;
            } catch (Exception $e) {
                $error = json_decode($e->getMessage(), true);
                $error['error']['folderSource'] = $folderId;
                $error['error']['folderDest'] = $destId;
                if ($file) $error['error']['file'] = $this->getArrayFromDriveFile($file, true);
                $this->core->__p->add('moveDriveFiles ', '', 'endnote');
                return $this->addError($e->getCode(), $error);
            }
        }

        /**
         * Copy all the files where parent is $folderId to $destId with a max of 10.000 docs
         * @param string $folderId
         * @param string $destId id of the folder dest
         * @return bool
         */
        public function copyDriveFiles(string $folderId, string $destId)
        {

            //region CHECK parameters
            if (!$folderId) return $this->addError(400, '$folderId can not be empty');
            if (!$destId) return $this->addError(400, '$destId can not be empty');
            if ($destId == $folderId) return $this->addError(400, '$folderId and $destId are the same');
            //endregion

            $this->core->__p->add('copyDriveFiles ', "{$folderId}->{$destId}", 'note');
            // https://developers.google.com/drive/api/guides/fields-parameter
            $parameters = ['fields' => 'files(id)', 'includeItemsFromAllDrives' => true, 'supportsAllDrives' => true];
            $parameters['q'] = "'{$folderId}' in parents";
            $ret = [];
            $i = 0;
            /** @var Google\Service\Drive\DriveFile $file */
            $file = null;
            try {
                do {
                    /** @var Google\Service\Drive\FileList $files_query */
                    $files_query = $this->drive->files->listFiles($parameters);
                    $files = $files_query->getFiles();
                    foreach ($files as $file) {
                        $start_time = microtime(true);
                        $options = array('supportsAllDrives' => true);
                        $this->driveFile->setParents([$destId]);
                        $file = $this->drive->files->copy($file->getId(), $this->driveFile, $options);
                        $ret[] = $this->getArrayFromDriveFile($file, true, ['time-to-copy-file' => round(microtime(true) - $start_time, 4)]);
                        $i++;
                    }
                } while (($parameters['pageToken'] = $this->pageToken = $files_query->getNextPageToken()) && $i < 100);
                $this->core->__p->add('copyDriveFiles ', '', 'endnote');
                return $ret;
            } catch (Exception $e) {
                $error = json_decode($e->getMessage(), true);
                $error['error']['folderSource'] = $folderId;
                $error['error']['folderDest'] = $destId;
                if ($file) $error['error']['file'] = $this->getArrayFromDriveFile($file, true);
                $this->core->__p->add('copyDriveFiles ', '', 'endnote');
                return $this->addError($e->getCode(), $error);
            }
        }

        /**
         * Delete all the files where parent is $folderId to $destId with a max of 10.000 docs
         * @param string $folderId
         * @param string $destId id of the folder dest
         * @return bool
         */
        public function deleteDriveFiles(string $folderId)
        {
            //region CHECK parameters
            if (!$folderId) return $this->addError(400, '$folderId can not be empty');
            //endregion

            $this->core->__p->add('deleteDriveFiles ', "{$folderId}", 'note');
            // https://developers.google.com/drive/api/guides/fields-parameter
            $parameters = ['fields' => 'files(id)', 'includeItemsFromAllDrives' => true, 'supportsAllDrives' => true];
            $parameters['q'] = "'{$folderId}' in parents";
            $ret = [];
            $i = 0;
            /** @var Google\Service\Drive\DriveFile $file */
            $file = null;
            try {
                do {
                    /** @var Google\Service\Drive\FileList $files_query */
                    $files_query = $this->drive->files->listFiles($parameters);
                    $files = $files_query->getFiles();
                    foreach ($files as $file) {
                        $start_time = microtime(true);
                        $options = array('supportsAllDrives' => true);
                        $this->drive->files->delete($file->getId(), $options);
                        $ret[] = $this->getArrayFromDriveFile($file, true, ['time-to-delete-file' => round(microtime(true) - $start_time, 4)]);
                        $i++;
                    }
                } while (($parameters['pageToken'] = $this->pageToken = $files_query->getNextPageToken()) && $i < 100);
                $this->core->__p->add('deleteDriveFiles ', '', 'endnote');
                return $ret;
            } catch (Exception $e) {
                $error = json_decode($e->getMessage(), true);
                $error['error']['folder'] = $folderId;
                if ($file) $error['error']['file'] = $this->getArrayFromDriveFile($file, true);
                $this->core->__p->add('deleteDriveFiles ', '', 'endnote');
                return $this->addError($e->getCode(), $error);
            }
        }

        /**
         * Delete a file in drive of any kind
         * The error codes can be: 404 = not found, 403 = insufficient permissions
         * @param string $fileId
         * @return bool
         */
        public function deleteDriveFile($fileId)
        {
            $this->core->__p->add('deleteDriveFile ', "{$fileId}", 'note');
            try {
                $options = array('supportsAllDrives' => true);
                $this->drive->files->delete($fileId, $options);
                $this->core->__p->add('deleteDriveFile ', '', 'endnote');
                return true;
            } catch (Exception $e) {
                $this->addError($e->getCode(), $e->getMessage());
                $this->core->__p->add('deleteDriveFile ', '', 'endnote');
                return false;
            }
        }

        /**
         * Copy a file with the option to change the name
         * @param string $fileId
         * @return bool
         */
        public function copyDriveFile($fileId, string $newName = null, string $newFolder = null)
        {
            $start_time = microtime(true);
            $this->core->__p->add('copyDriveFile ', "{$fileId}->{$newFolder}", 'note');
            try {
                $options = ['supportsAllDrives' => true];
                if ($newName) $this->driveFile->setName($newName);
                if ($newFolder) $this->driveFile->setParents([$newFolder]);

                $file = $this->drive->files->copy($fileId, $this->driveFile, $options);
                $this->core->__p->add('copyDriveFile ', '', 'endnote');
                return $this->getArrayFromDriveFile($file, true, ['new_folder' => $newFolder, 'time-to-copy-file' => round(microtime(true) - $start_time, 4)]);
            } catch (Exception $e) {
                $this->addError($e->getCode(), $e->getMessage());
                $this->core->__p->add('copyDriveFile ', '', 'endnote');
                return false;
            }
        }


        /**
         * Return a Google Client with the scopes necessary to manage Google Documents
         *
         */
        private function getGoogleClient(&$config)
        {
            $client = new Google_Client();

            if ($config)
                $client->setAuthConfig($config);
            $client->useApplicationDefaultCredentials();
            // Google_Service_Sheets alias of Google\Service\Sheets
            $client->setScopes([Google\Service\Sheets::SPREADSHEETS, Google\Service\Sheets::DRIVE, Google\Service\Sheets::DRIVE_FILE]);
            return ($client);
        }


        /**
         * Add an error into the classs
         * @param $msg
         */
        protected function addError($code, $msg)
        {
            $this->error = true;
            $this->errorCode = $code;
            $this->errorMsg[] = is_string($msg) ? (json_decode($msg, true) ?: $msg) : $msg;
        }
    }

    /**
     * [$gdocs = $this->core->loadClass('GoogleDocuments');] Class to facilitate GoogleDocuments integration
     * @package LabClasses
     */
    class GoogleDocuments extends GoogleDrive
    {
        /** @var Google\Service\Sheets $drive will manage the drive access properties */
        var $spreedsheet = null;

        /**
         * CloudFramework GoogleDocument class
         * @param Core7 $core
         * @param array $config
         */
        function __construct(Core7 &$core, $config = [])
        {
            parent::__construct($core, $config);
            $this->spreedsheet = new Google_Service_Sheets($this->googleClient);

        }

        /**
         * Update SpreadSheet
         * The error codes can be: 404 = not found, 403 = insufficient permissions
         * @param string $fileId
         * @param array $values Array of [ rows [cols]]
         * @param string $range Where to start the update
         * @return array|void
         */
        public function updateSpreadSheet($fileId, $values, $range = 'A1')
        {
            try {
                $update_body = new Google_Service_Sheets_ValueRange();
                $update_body->setRange($range);
                $update_body->setValues($values);
                $update = $this->spreedsheet->spreadsheets_values->update($fileId, $range, $update_body, [
                    'valueInputOption' => 'USER_ENTERED'
                ]);
                $ret = [
                    'updatedRows' => $update->getUpdatedRows()
                    , 'updatedCells' => $update->getUpdatedCells()
                    , 'updatedRange' => $update->getUpdatedRange()
                    , 'spreadsheetId' => $update->getSpreadsheetId()
                    , 'url' => 'https://docs.google.com/spreadsheets/d/' . $update->getSpreadsheetId()
                ];
                return $ret;
            } catch (Exception $e) {
                $this->addError($e->getCode(), $e->getMessage());
                return false;
            }
        }

        /**
         * Insert data in  SpreadSheet
         * The error codes can be: 404 = not found, 403 = insufficient permissions
         * @param string $fileId
         * @param array $values Array of [ rows [cols]]
         * @param string $range Where to start the update
         * @return array|void
         */
        public function insertSpreadSheet($fileId, $values, $range = 'A1')
        {
            try {
                $insert_body = new Google_Service_Sheets_ValueRange();
                $insert_body->setRange($range);
                $insert_body->setValues($values);
                /** @var Google\Service\Sheets\AppendValuesResponse $insert */
                $insert = $this->spreedsheet->spreadsheets_values->append($fileId, $range, $insert_body, [
                    'valueInputOption' => 'USER_ENTERED',
                    'insertDataOption' => 'INSERT_ROWS'
                ]);
                $update = $insert->getUpdates();

                // "valueInputOption" => "RAW"
                $ret = [
                    'updatedRows' => $update->getUpdatedRows()
                    , 'updatedCells' => $update->getUpdatedCells()
                    , 'updatedRange' => $update->getUpdatedRange()
                    , 'spreadsheetId' => $update->getSpreadsheetId()
                    , 'url' => 'https://docs.google.com/spreadsheets/d/' . $update->getSpreadsheetId()
                ];
                return $ret;
            } catch (Exception $e) {
                $this->addError($e->getCode(), $e->getMessage());
                return false;
            }
        }

        /**
         * Read data from  SpreadSheet
         * The error codes can be: 404 = not found, 403 = insufficient permissions
         * @param string $fileId
         * @param string $range Where to start the update
         * @return array|void
         */
        public function readSpreadSheet($fileId, $range = 'A1')
        {

            try {
                $result = $this->spreedsheet->spreadsheets_values->get($fileId, $range);
                $ret = [];
                foreach ($result as $item) {
                    $ret[] = $item;
                }
                return $ret;
            } catch (Exception $e) {
                $this->addError($e->getCode(), $e->getMessage());
                return false;
            }
        }

        /**
         * Create a Spreadsheet file
         * @param string $spreadsheet_name
         * @param string $parent_id value of the parent folder id
         * @return void
         */
        public function createSpreadSheet(string $spreadsheet_name, string $parent_id = "")
        {
            try {
                $file = new Google_Service_Drive_DriveFile();
                $file->setName($spreadsheet_name);
                $file->setMimeType('application/vnd.google-apps.spreadsheet');
                if ($parent_id)
                    $file->setParents([$parent_id]);

                $retFile = $this->drive->files->create($file, ['supportsAllDrives' => true]);
                return $retFile->getId();
            } catch (Exception $e) {
                return $this->addError(['createSpreadSheet', $e->getMessage()]);
            }

        }

        /**
         * Create a Spreadsheet file
         * @param string $spreadsheet_name
         * @param string $parent_id value of the parent folder id
         * @return void
         */
        public function convertToWorkSpaceSpreadSheet($fileId)
        {

            $start_time = microtime(true);
            if(!$file = $this->getDriveFile($fileId)) return;
            if(strpos($file['mimeType'],'google')!==false)
                return $this->addError('conflict',"The document is already a workspace document. Current type: ".$file['mimeType']);

            try {
                $optParams = array('alt' => "media", 'supportsAllDrives' => true);
                /** @var GuzzleHttp\Psr7\Response $content */
                $content = $this->drive->files->get($fileId, $optParams);

                $this->driveFile->setName($file['name'].'_converted');
                $this->driveFile->setMimeType('application/pdf');
                $this->driveFile->setParents($file['parents']);
                $file_uploaded = $this->drive->files->create($this->driveFile, ['data' => $content->getBody(),'uploadType'=>'multipart','fields' => "*"]);
                return $this->getArrayFromDriveFile($file_uploaded,true, ['time-to-move-file' => round(microtime(true) - $start_time, 4)]);


            } catch (Exception $e) {
                return $this->addError(['convertToWorkSpace', $e->getMessage()]);
            }

        }
    }
}