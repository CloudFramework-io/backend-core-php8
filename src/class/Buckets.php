<?php
use Google\Cloud\Storage\StorageObject;

if (!defined ("_Buckets_CLASS_") ) {
    define ("_Buckets_CLASS_", TRUE);

    /**
     * Class to Handle GCS Buckets based on Google Cloud Storage for PHP
     *
     * php example to create a class object:
     * ```php
     * $bucket = $this->core->loadClass('Buckets','gs://{BucketName}');
     * if($bucket->error) return ['errorCode'=>$bucket->errorCode,'errorMsg'=>$bucket->errorMsg];
     * ```
     * Url references
     * * [Buckets Source code](https://github.com/CloudFramework-io/appengine-php-core-7.4/blob/master/src/class/Buckets.php)
     * * [Try it in replit](https://replit.com/@cloudframework/php#api/06-buckets/00-explore.php)
     * * [Official Google Documentation about Storage](https://github.com/googleapis/google-cloud-php/tree/main/Storage)
     * * [CloudFramework Academy Storage and Buckets: Backend](https://cloudframework.io/contacto-academy/?utm_source=replit_php74_documentor)
     * @package CoreClasses
     */
    class Buckets {

        /** @ignore */
        private $core;
        /**
         * version of the class.
         * ```php
         * $bucket->version;
         * ```
         * @var string $version version of the class
         */
        var $version = '202305311';

        /** @ignore */
        var $bucket = '';
        /** @ignore */
        var $bucketInfo = [];
        /**
         * If there is an error in the last execution
         *
         * ```php
         * if($bucket->error) return ['errorCode'=>$bucket->errorCode,'errorMsg'=>$bucket->errorMsg];
         * ```
         * @var bool $error
         */
        var $error = false;

        /**
         * If there is an error it shows the error code
         *
         * ```php
         * if($bucket->error) echo $bucket->errorCode;
         * ```
         * @var string|integer $errorCode
         */
        var $errorCode = '';
        /**
         * If there is an error it shows an array of error messages
         *
         * ```php
         * if($bucket->error) var_dump($bucket->$errorMsg);
         * ```
         * @var array $errorMsg
         */
        var $errorMsg = array();
        /** @ignore */
        var $max = array();
        /** @ignore */
        var $uploadedFiles = array();
        /** @ignore */
        var $isUploaded = false;
        /** @ignore */
        var $vars = [];
        /** @ignore */
        var $gs_bucket = null;
        /** @ignore */
        var $gs_bucket_url = null;
        /** @ignore */
        var $debug = false;

        /**
         * Move files uploaded in $_FILES to temporal space to Bucket $path taking $options
         *
         * example:
         * ```php
         * if(!$bucket->uploadedFiles) die('there are not files uploaded');
         * $options = [];
         * $options['public'] = true; // It tries to make the document public returning "publicUrl" and "mediaLink" attributes in the returned array
         * $options['apply_hash_to_filenames'] = true; // It rewrites filename with a format: {datetime}_{hash}.{extension} and add "hash_from_name" attribute in the returned array with the original name
         * $options['allowed_extensions'] = ['jpg'];
         * $options['allowed_content_types'] = ['image/jpeg'];
         * $uploaded_files = $this->bucket->manageUploadFiles('/uploads',$options);
         * if($bucket->error) return $bucket->errorMsg;
         * else return $uploaded_files;
         * ```
         * @param string $path optional path to store files uploaded. If it is passed it has to start with '/'
         * @param array $options [optional] {
         *
         *     @type string $public Makes the files to be public
         *     @type string $field_name it says only to process documents with the variable $field_name otherwise it processes the document of all variables
         *     @type bool $apply_hash_to_filenames change the original name by a hash to avoid duplicated files
         *     @type array $allowed_extensions extensions to be allowed in file names: ['jpg','pdf',..]
         *     @type array $allowed_content_types content_types allowed to be allowed in file names: ['image','image/jpg','plain/txt',..]
         *
         * }
         * @return array
         * the output is an array with the following structure where 'files' if the name of formParms to send the files in this example:
         * ```json
         * {
         *   "field_name1": [
         *     {
         *     "name": "20221221042421_upload63a9cab5988acf3ccdd27d2000e3f9255a7e3e2c48800.jpg",
         *     "type": "image/jpeg",
         *      "tmp_name": "/private/var/folders/hb/9499jh9s3jd9gx0q68sm5bj80000gn/T/phpG9pBMz",
         *     "error": 0,
         *     "size": 1412041,
         *     "hash_from_name": "1.jpg",
         *     "movedTo": "gs://academy-bucket-mix/uploads/20221221042421_upload63a9cab5988acf3ccdd27d2000e3f9255a7e3e2c48800.jpg",
         *     "publicUrl": "https://storage.googleapis.com/academy-bucket-mix/uploads/20221221042421_upload63a9cab5988acf3ccdd27d2000e3f9255a7e3e2c48800.jpg",
         *     "mediaLink": "https://storage.googleapis.com/download/storage/v1/b/academy-bucket-mix/o/uploads%2F20221221042421_upload63a9cab5988acf3ccdd27d2000e3f9255a7e3e2c48800.jpg?generation=1672071863226633&alt=media"
         *    }],
         *  "field_name2":[
         *    {
         *     "name": "IMAGE1.jpg",
         *     "type": "image/jpeg",
         *     "tmp_name": "/private/var/folders/hb/9499jh9s3jd9gx0q68sm5bj80000gn/T/php3uVF7y",
         *     "error": 0,
         *     "size": 1111558,
         *     "movedTo": "gs://academy-bucket-mix/uploads/20221223042423_upload63a9cab752d3dfa7ea700b267bf1ef4c9f556f17314bd.jpg",
         *    }
         *  ]
         * }
         * ```
         */
        function manageUploadFiles($path='', $options =[]) {

            //region SET $base_dir to upload the files
            $base_dir = $this->getBucketPath($path);
            //endregion

            //region IF there is no files to upload return error
            if(!$this->uploadedFiles)  return $this->addError('manageUploadFiles($path,$options) There is not $this->uploadedFiles ');
            //endregion

            //region INIT $time to analyze perfomrance
            $time = microtime(true);
            $this->core->__p->add('Buckets.manageUploadFiles', $path, 'note');
            //endregion

            //region CHECK if $base_dir is a directory
            if(!$this->mkdir($base_dir)) {
                $this->core->__p->add('Buckets.manageUploadFiles',null , 'endnote');
                return($this->addError('the path to write the files does not exist: '.$base_dir));
            }
            //endregion

            //region SET $public,$apply_hash_to_filenames,$allowed_extensions,$allowed_content_types from $options
            $public=($options['public']??false)?true:false;
            $apply_hash_to_filenames = ($options['apply_hash_to_filenames']??false)?true:false;
            $allowed_extensions = ($options['allowed_extensions']??'')?explode(',',strtolower($options['allowed_extensions'])):[];
            $allowed_content_types = ($options['allowed_content_types']??'')?explode(',',strtolower($options['allowed_content_types'])):[];
            //endregion

            //region IF $public verify the bucket can allow public files
            if($public && is_object($this->gs_bucket) && ($this->gs_bucket->info()['iamConfiguration']['publicAccessPrevention']??null) == 'enforced') {
                $this->core->__p->add('Buckets.manageUploadFiles',null , 'endnote');
                return($this->addError('The bucket does not allow public objects, publicAccessPrevention=enforced in '.$this->bucket));
            }
            //endregion

            //region RETURN error IF $public and $this->bucketInfo['iamConfiguration']['publicAccessPrevention']??null) == 'enforced'
            if(($this->bucketInfo['iamConfiguration']['publicAccessPrevention']??null) == 'enforced' && $public)
                return($this->addError('The bucket does not allow public objects, publicAccessPrevention=enforced in '.$this->bucket));
            //endregion

            //region LOOP $this->uploadedFiles and move them to $path
            foreach ($this->uploadedFiles as $key => $files) {

                // only handle specific field_name documents if this var name is included in options
                if(($options['field_name']??null) && $options['field_name']!=$key) continue;

                //region VERIFY $allowed_extensions and $allowed_content_types
                if($allowed_extensions || $allowed_content_types) for($i=0,$tr=count($files);$i<$tr;$i++) {

                    $value = $files[$i];
                    if($value['error']) continue;

                    // Do I have allowed extensions
                    if($allowed_extensions) {
                        $extension = '';
                        if(strpos($value['name'],'.')) {
                            $parts = explode('.',$value['name']);
                            $extension = '.'.strtolower($parts[count($parts)-1]);
                        }

                        $allow = false;
                        if($extension)
                            foreach ($allowed_extensions as $allowed_extension) {
                                if('.'.trim($allowed_extension) == $extension ) {
                                    $allow=true;
                                    break;
                                }
                            }

                        if(!$allow) {
                            $this->core->__p->add('Buckets.manageUploadFiles',null , 'endnote');
                            return($this->addError($value['name'].' does not have any of the following extensions: '.$options['allowed_extensions'],'extensions'));
                        }
                    }

                    // Do I have allowed content types
                    if($allowed_content_types) {

                        $allow = false;
                        foreach ($allowed_content_types as $allowed_content_type) {
                            if(strpos(strtolower($value['type']),trim($allowed_content_type)) !== false ) {
                                $allow=true;
                                break;
                            }
                        }
                        if(!$allow) {
                            $this->core->__p->add('Buckets.manageUploadFiles',null , 'endnote');
                            return($this->addError($value['type'].' does not match with any of the following content-types: '.$options['allowed_content_types'],'content-type'));
                        }
                    }
                }
                //endregion

                for($i=0,$tr=count($files);$i<$tr;$i++) {
                    $value = $files[$i];

                    if(!$value['error']) {

                        // If the name of the file uploaded has special chars, the system convert it into mime-encode-utf8
                        if(strpos($value['name'],'=?UTF-8') !== false) $value['name'] = iconv_mime_decode($value['name'],0,'UTF-8');

                        // Extension calculation
                        $extension = '';
                        if(strpos($value['name'],'.')) {
                            $parts = explode('.',$value['name']);
                            $extension = '.'.strtolower($parts[count($parts)-1]);
                        }

                        // do not use the original name.. and transform to has+extension
                        if($apply_hash_to_filenames) {
                            $this->uploadedFiles[$key][$i]['hash_from_name'] = $value['name'];
                            $value['name'] = date('Ymshis').uniqid('_upload'). md5($value['name']).$extension;
                            $this->uploadedFiles[$key][$i]['name'] = $value['name'];
                        }

                        $dest = $base_dir.'/'.$value['name'];

                        // Let's try to move the temporal files to their destinations.
                        try {
                            if(move_uploaded_file($value['tmp_name'],$dest)) {
                                //region SET $this->uploadedFiles[$key][$i]['movedTo']
                                $this->uploadedFiles[$key][$i]['movedTo'] = $dest;
                                //endregion
                                //region IF $this->bucketInfo['iamConfiguration']['uniformBucketLevelAccess']['enabled'] SET $this->uploadedFiles[$key][$i]['uniformBucketLevelAccess']
                                if (($this->bucketInfo['iamConfiguration']['uniformBucketLevelAccess']['enabled'] ?? null))
                                    $this->uploadedFiles[$key][$i]['uniformBucketLevelAccess'] = "active. You can not assign specific permissions to the object";
                                //endregion

                                //region IF $public SET $this->uploadedFiles[$key][$i]['publicUrl'] and make the file accesible by AllUsers
                                if($public) {
                                    $file = preg_replace('/gs:\/\/[^\/]*\//','',$dest);
                                    $this->uploadedFiles[$key][$i]['publicUrl'] = 'https://storage.googleapis.com/'.$this->gs_bucket->name().'/'.$file;
                                    $object = $this->gs_bucket->object($file);
                                    if (!($this->bucketInfo['iamConfiguration']['uniformBucketLevelAccess']['enabled'] ?? null))
                                        $object->update(['acl' => []], ['predefinedAcl' => 'PUBLICREAD']);
                                    $this->uploadedFiles[$key][$i]['mediaLink'] = ($object->info()['mediaLink']??null);
                                }
                                //endregion
                            } else {
                                $this->addError(error_get_last());
                                $this->uploadedFiles[$key][$i]['error'] = $this->errorMsg;
                            }

                        }catch(Exception $e) {
                            $this->addError($e->getMessage());
                            $this->addError(error_get_last());
                            $this->uploadedFiles[$key][$i]['error'] = $this->errorMsg;
                        }
                    }
                }


            }
            //endregion

            //region IF $this->debug add a log about the upload
            if($this->debug)
                $this->core->logs->add("manageUploadFiles('{$path}') [processing uploaded files:".count($this->uploadedFiles)."]". ' [time='.(round(microtime(true)-$time,4)).' secs]','Buckets');
            //endregion

            $this->core->__p->add('Buckets.manageUploadFiles',null , 'endnote');

            //region RETURN $this->uploadedFiles
            return($this->uploadedFiles);
            //endregion
        }

        /**
         * Object constructor
         * example:
         *
         * ```php
         * $buckets = $this->core->loadClass('Buckets','gs://{BucketName}');
         * if($buckets->error) return $buckets->errorMsg
         * ```
         * @ignore
         * @param Core7 $core
         * @param string $bucket
         */
        Function __construct (Core7 &$core,$bucket='') {

            //Performance
            $time = microtime(true);
            $this->core = $core;
            $this->core->__p->add('Buckets', $bucket??'', 'note');

            if($this->core->is->development()) $this->debug = true;

            if(strlen($bucket??'')) $this->bucket = $bucket;
            else $this->bucket = $this->core->config->get('bucketUploadPath');
            if(!$this->bucket) return($this->addError('Missing bucketUploadPath config var or $bucket in the constructor'));

            if(strpos($this->bucket,'gs://')===0) {
                // take the bucket name: ex: gs://cloudframework/adnbp/.. -> cloudframework
                $bucket_root = preg_replace('/\/.*/','',str_replace('gs://','',$this->bucket));
                try {
                    $this->gs_bucket = $this->core->gc_datastorage_client->bucket($bucket_root);
                    if(!$this->gs_bucket->exists()) $this->addError('I can not find bucket: '.$this->bucket,'bucket-not-found');
                    $this->gs_bucket_url = 'https://console.cloud.google.com/storage/browser/'.$this->bucket;
                    $this->bucketInfo = $this->gs_bucket->info(['projection'=>'full']);
                } catch (Exception $e) {
                    $this->addError($e->getMessage(),'bucket-can-not-be-assigned');
                }

                // Add logs for performance
                if($this->debug)
                    $this->core->logs->add("Buckets('{$bucket_root}')". ' [time='.(round(microtime(true)-$time,4)).' secs]','Buckets');

                if($this->error) {
                    $this->core->__p->add('Buckets', null, 'endnote');
                    return;
                }
            } else {
                $this->core->__p->add('Bucket', null, 'endnote');
                return($this->addError('The bucket has to begin with gs:// ['.$this->bucket.']','bucket-wrong-name-format'));
            }

            $time = microtime(true);
            $this->vars['upload_max_filesize'] = ini_get('upload_max_filesize');
            $this->vars['max_file_uploads'] = ini_get('max_file_uploads');
            $this->vars['file_uploads'] = ini_get('file_uploads');
            $this->vars['default_bucket'] = $this->bucket;
            $this->vars['retUploadUrl'] = $this->core->system->url['host_url_uri'];

            if(count($_FILES)) {
                foreach ($_FILES as $key => $value) {
                    if(is_array($value['name'])) {
                        for($j=0,$tr2=count($value['name']);$j<$tr2;$j++) {
                            foreach ($value as $key2 => $value2) {
                                $this->uploadedFiles[$key][$j][$key2] = $value[$key2][$j];
                            }
                        }
                    } else {
                        $this->uploadedFiles[$key][0] = $value;
                    }
                    $this->isUploaded = true;
                }

                if($this->debug)
                    $this->core->logs->add("__construct('{$bucket_root}') [storing temporally uploaded files:".count($_FILES)."]". ' [time='.(round(microtime(true)-$time,4)).' secs]','Buckets');

            }
            $this->core->__p->add('Buckets', null, 'endnote');
        }


        /**
         * Execute a scandir over the path and evluate if they are files or directories. If the path is incorrect it will return void. If $path is empty it assumes '/'
         *
         * example:
         * ```php
         * $files = $bucket->scan('/');
         * if($bucket->error) return ['errorCode'=>$bucket->errorCode,'errorMsg'=>$bucket->errorMsg];
         * die(json_encode($bucket->fastScan('/'),JSON_PRETTY_PRINT));
         * ```
         * output example:
         * ```json
         * { "documents": { "type": "dir" }, "images": { "type": "dir" },"image.jpg": {"type":"file"} }
         * ```
         * @param string $path
         * @return array|null|void
         */
        function scan(string $path='') {
            try{
                $bucket_path = $this->getBucketPath($path);
                if(!is_dir($bucket_path)) return null;
                $ret = array();
                $tmp = @scandir($this->bucket.$path);
                foreach ($tmp as $key => $value) {
                    $ret[$value] = array('type'=>(is_file($bucket_path.'/'.$value))?'file':'dir');
                    if(isset($_REQUEST['__p'])) __p('is_dir: '.$bucket_path.'/'.$value);
                }
                return($ret);
            } catch (Exception $e) {
                return $this->addError($e->getMessage(),'bucket-path-scandir-error');
            }
        }


        /**
         * Execute a scandir over the path. if $path is empty it assumes '/'
         *
         * example:
         * ```php
         * $files = $bucket->fastScan('/');
         * if($bucket->error) return ['errorCode'=>$bucket->errorCode,'errorMsg'=>$bucket->errorMsg];
         * die(json_encode($bucket->fastScan('/'),JSON_PRETTY_PRINT));
         * ```
         * output example:
         * ```json
         * [ "documents", "images" ]
         * ```
         * @param string $path path starting with '/' inside the bucket
         * @return array|void Array of files in the bucket. if there is any error it will return void
         */
        function fastScan(string $path='') {
            try{
                $bucket_path = $this->getBucketPath($path);
                if(!is_dir($bucket_path)) return null;
                return(@scandir($bucket_path));
            } catch (Exception $e) {
                return $this->addError($e->getMessage(),'bucket-path-scandir-error');
            }
        }


        /**
         * Delete a folder inside the bucket
         *
         * example:
         * ```php
         * $deleted = $bucket->rmdir('/tmp');
         * if($bucket->error) return ['errorCode'=>$bucket->errorCode,'errorMsg'=>$bucket->errorMsg];
         * if($deleted) echo "dir /tmp deleted";
         * ```
         * @param string $path path starting with '/' inside the bucket
         * @return bool|void true of the path is dir and has been deleted. if there is any error it will return void
         */
        public function rmdir(string $path)  {
            $bucket_path = $this->getBucketPath($path);
            $ret = false;
            try {
                if(!is_dir($bucket_path)) return false;
                $ret = @rmdir($bucket_path);
                if(is_dir($bucket_path)) return false;
                else return $ret;
            } catch(Exception $e) {
                die($e->getMessage());
                $this->addError($e->getMessage());
                $this->addError(error_get_last());
            }
        }

        /**
         * Create a folder inside the bucket. If the folder exist then return true;
         *
         * example:
         * ```php
         * $created = $bucket->mkdir('/tmp');
         * if($bucket->error) return ['errorCode'=>$bucket->errorCode,'errorMsg'=>$bucket->errorMsg];
         * if($deleted) echo "dir /tmp created";
         * ```
         * @param string $path path starting with '/' inside the bucket
         * @return bool|void true if the path is dir and has been created. if there is any error it will return void
         */
        public function mkdir(string $path)  {
            $bucket_path = $this->getBucketPath($path);
            $ret = false;
            try {
                if(is_dir($bucket_path)) return true;
                $ret = @mkdir($bucket_path);
            } catch(Exception $e) {
                $this->addError($e->getMessage());
                $this->addError(error_get_last());
            }
            return $ret;
        }

        /**
         * Deprecated method
         * @param string $returnUrl is the url the system has to call once the file has been uploaded
         * @return mixed
         * @deprecated
         */
        function getUploadUrl($returnUrl=null) {
            return($returnUrl);
            /*
            if(!$returnUrl) $returnUrl = $this->vars['retUploadUrl'];
            else $this->vars['retUploadUrl'] = $returnUrl;
            $options = array( 'gs_bucket_name' => str_replace('gs://','',$this->bucket) );
            $upload_url = CloudStorageTools::createUploadUrl($returnUrl, $options);
            return($upload_url);
            */
        }


        /**
         * tell if path is a dir
         *
         * example:
         * ```php
         * if(!$bucket->isDir('/tmp')){
         *    if($bucket->error) return ['errorCode'=>$bucket->errorCode,'errorMsg'=>$bucket->errorMsg];
         *    else echo "/tmp does not exist or it is not a dir";
         * } else echo "/tmp is dir";
         * ```
         * @param string $path path inside the bucket. If $path starts with gs:// it will try to find the file outside of $this->bucket
         * @return bool true if the path is dir.
         */
        public function isDir($path='')  {
            return(is_dir($this->getBucketPath($path)));
        }

        /**
         * Tell if path is a file
         *
         * example:
         * ```php
         * if($info = $this->bucket->uploadFile('/uploads/image_to_upload.jpg',__DIR__.'/image_to_upload.jpg',['public'=>true])){
         *   var_dump ($info);
         * } else var_dump ['errorCode'=>$bucket->errorCode,'errorMsg'=>$bucket->errorMsg];
         * ```
         * @param string $path path inside the bucket. If $path starts with gs:// it will try to find the file outside of $this->bucket
         * @return bool true if the path is file.
         */
        function isFile(string $path)  {
            return(is_file($this->getBucketPath($path)));
        }


        /**
         * Deprecated.. call mkdir
         * @ignore
         * @deprecated
         * @param string $path
         * @return bool|void
         */
        function isMkdir(string $path='')  {
            return $this->mkdir($this->getBucketPath($path));
        }


        /**
         * Upload $source_file_path to the bucket with $filename_path
         *
         * example:
         * ```php
         * if($info = $this->bucket->uploadContents('/uploads/hello.txt','Hello World',['public'=>true])){
         *   var_dump ($info);
         * } else var_dump ['errorCode'=>$bucket->errorCode,'errorMsg'=>$bucket->errorMsg];
         * ```
         * @param string $filename_path file name path of the file to create
         * @param string $contents The contents to upload to the file
         * @param string $options [optional] {
         *
         * Options to create the object file.
         *
         *     @type bool $public It says the object has to be public and return publicUrl attribute. It equals to ['predefinedAcl'=>'publicRead']
         *     @type string $predefinedAcl defines the access of the file. It can be: "authenticatedRead", "bucketOwnerFullControl", "bucketOwnerRead", "private", "projectPrivate", and "publicRead"
         *     @type array $metadata for the object to create: contentType, cacheControl.. More info in: : https://cloud.google.com/storage/docs/json_api/v1/objects/insert#request-body
         * }
         * @return array with the object created in the bucket
         */
        function uploadContents(string $filename_path, string $contents,array $options = [] )
        {
            try{
                $stream = fopen('data://text/plain,' . $contents, 'r');
                return $this->upload($stream,$filename_path,$options);

            } catch(Exception $e) {
                return $this->addError($e->getMessage());
            }
        }

        /**
         * Upload $source_file_path to the bucket with $filename_path
         *
         * example:
         * ```php
         * if(!$bucket->putContents('file.txt','mi content','/tmp',['gs' =>['Content-Type' => 'text/plain'])){
         *    if($bucket->error) return ['errorCode'=>$bucket->errorCode,'errorMsg'=>$bucket->errorMsg];
         *    else echo "/tmp/file.txt' can no be created";
         * } else echo "/tmp/file.txt' has been created";
         * ```
         * @param string $filename_path file name path of the file to create
         * @param string $source_file_path local source file to upload
         * @param string $options [optional] {
         *
         * Options to create the object file.
         *
         *     @type bool $public It says the object has to be public and return publicUrl attribute. It equals to ['predefinedAcl'=>'publicRead']
         *     @type string $predefinedAcl defines the access of the file. It can be: "authenticatedRead", "bucketOwnerFullControl", "bucketOwnerRead", "private", "projectPrivate", and "publicRead"
         *     @type array $metadata for the object to create: contentType, cacheControl.. More info in: : https://cloud.google.com/storage/docs/json_api/v1/objects/insert#request-body
         * }
         * @return array with the object created in the bucket
         */
        function uploadFile(string $filename_path, string $source_file_path,array $options = [] ) {
            try{
                $file = fopen($source_file_path, 'r');
                return $this->upload($file,$filename_path,$options);
            } catch(Exception $e) {
                return $this->addError($e->getMessage());
            }
        }

        /**
         * Upload $source_file_path to the bucket with $filename_path
         *
         * example:
         * ```php
         * if(!$bucket->putContents('file.txt','mi content','/tmp',['gs' =>['Content-Type' => 'text/plain'])){
         *    if($bucket->error) return ['errorCode'=>$bucket->errorCode,'errorMsg'=>$bucket->errorMsg];
         *    else echo "/tmp/file.txt' can no be created";
         * } else echo "/tmp/file.txt' has been created";
         * ```
         * @ignore
         * @param mixed $file_descriptor fopen file descriptor
         * @param string $filename_path file name path of the object to create
         * @param string $options [optional] {
         *
         * Options to create the object file.
         *     @type bool $public It says the object has to be public and return publicUrl attribute. It equals to ['predefinedAcl'=>'publicRead']
         *     @type string $predefinedAcl defines the access of the file. It can be: "authenticatedRead", "bucketOwnerFullControl", "bucketOwnerRead", "private", "projectPrivate", and "publicRead"
         *     @type array $metadata for the object to create: contentType, cacheControl.. More info in: : https://cloud.google.com/storage/docs/json_api/v1/objects/insert#request-body
         * }
         * @return array with the object created in the bucket
         */
        private function upload(&$file_descriptor, string $filename_path, array $options = [] ) {
            if(!$filename_path) return $this->addError('uploadFile($filename_path,$source_file_path,...) $filename_path can not be empty');
            if($filename_path[0]=='/') $filename_path = substr($filename_path,1);

            try{

                //region SET $file = fopen($source_file_path) and INIT $optons_to_upload
                $options_to_upload = [
                    'name'=>$filename_path
                ];
                //endregion

                //region EVALUATE $options to add parameters in $options_to_upload
                // Public: "authenticatedRead", "bucketOwnerFullControl", "bucketOwnerRead", "private", "projectPrivate", and "publicRead"
                $extra_info = [];
                if(($options['public']??null)) $options['predefinedAcl'] = 'publicRead';
                if(in_array(($options['predefinedAcl']??''),["authenticatedRead", "bucketOwnerFullControl", "bucketOwnerRead", "private", "projectPrivate", "publicRead"])) {
                    if($options['predefinedAcl']=='publicRead') $extra_info['publicUrl'] = '';
                    //region SET $extra_info['publicAccessPrevention'] and RETURN error if $public and $this->bucketInfo['iamConfiguration']['publicAccessPrevention']??null) == 'enforced'
                    if(($this->bucketInfo['iamConfiguration']['publicAccessPrevention']??null) == 'enforced' && $options['predefinedAcl']=='publicRead')
                        $extra_info['publicAccessPrevention'] = "true. Object can not be public";
                    elseif(($this->bucketInfo['iamConfiguration']['uniformBucketLevelAccess']['enabled'] ?? null)){
                        $extra_info['uniformBucketLevelAccess'] = "enabled. Objects can not be forced to be public. Check the bucket properties";
                        if($options['predefinedAcl']=='publicRead')
                            $extra_info['publicUrl'] = 'https://storage.googleapis.com/'.$this->gs_bucket->name().'/'.$filename_path;
                    } else {
                        $options_to_upload['predefinedAcl'] = $options['predefinedAcl'];
                        if($options['predefinedAcl']=='publicRead')
                            $extra_info['publicUrl'] = 'https://storage.googleapis.com/'.$this->gs_bucket->name().'/'.$filename_path;
                    }
                }
                if(is_array($options['metadata']??null)) {
                    $options_to_upload['metadata'] = $options['metadata'];
                }
                //endregion

                //region UPLOAD $file to the bucket
                $object = $this->gs_bucket->upload($file_descriptor, $options_to_upload);
                return array_merge($object->info(),$extra_info);
                //endregion

            } catch(Exception $e) {
                $this->addError($e->getMessage());
                return $this->addError(error_get_last());
            }
        }

        /**
         * Create $filename with $data content in $path
         *
         * example:
         * ```php
         * if(!$bucket->putContents('file.txt','mi content','/tmp',['gs' =>['Content-Type' => 'text/plain'])){
         *    if($bucket->error) return ['errorCode'=>$bucket->errorCode,'errorMsg'=>$bucket->errorMsg];
         *    else echo "/tmp/file.txt' can no be created";
         * } else echo "/tmp/file.txt' has been created";
         * ```
         * @param string $filename name of the file to create
         * @param string $data Info to put in $file
         * @param string $path optinal path starting with '/' inside the bucket
         * @param array $options optional to assign file properties taking https://cloud.google.com/storage/docs/json_api/v1/objects/insert#request-body
         *  - supported values: ['contentType' =>'text/html','metadata'=>['var1'=>,'value1',..]]
         * @return bool true if the path is file.
         */
        function putContents(string $filename, $data, string $path='',array $options = [] ) {
            if(!$filename) return $this->addError('putContents($filename, $data, $path) $filename can not be empty');
            if(($filename[0]??null)=='/') $filename = substr($filename,1);
            if($path) {
                if($path[0]=='/') $path = substr($path,1);
                $filename = "{$path}/{$filename}";
            }

            $ret = false;
            try{
                if(strpos($filename,'gs://')===false && strpos($this->bucket,'gs:')===0 && is_object($this->gs_bucket)) {
                    $upload_options =['name'=>$filename];
                    if($options['medatada']??null) $upload_options['metadata']=$options['medatada'];
                    $upload_options['contentType'] = $options['contentType']??$this->getMimeTypeFromExtension(pathinfo($filename, PATHINFO_EXTENSION));
                    if($object = $this->gs_bucket->upload($data,$upload_options))
                        $ret=true;
                } else {
                    $upload_options = [];
                    $upload_options['gs']['Content-Type'] = $options['contentType']??$this->getMimeTypeFromExtension(pathinfo($filename, PATHINFO_EXTENSION));
                    $ctx = stream_context_create($upload_options);
                    if(@file_put_contents($this->getBucketPath($filename), $data,0,$ctx) === false) {
                        $this->addError(error_get_last());
                    } else {
                        $ret = true;
                    }
                }
            } catch(Exception $e) {
                $this->addError($e->getMessage());
                $this->addError(error_get_last());
            }
            return($ret);
        }

        /**
         * Return $filename content from $path
         *
         * example:
         * ```php
         * if(!$content = $bucket->getContents('file.txt','mi content','/tmp')){
         *    if($bucket->error) return ['errorCode'=>$bucket->errorCode,'errorMsg'=>$bucket->errorMsg];
         *    else echo "/tmp/file.txt' does not exist or does not have any content";
         * } else echo $content;
         * ```
         * @param string $filename name of the file to create
         * @param string $path optinal path starting with '/' inside the bucket
         * @return string|void if $filename exist return string content
         */
        function getContents($file,$path='') {
            if(!$file || ($file[0]??null)=='/') return $this->addError('getContents($file, $path) $file can not be empty and can not start with /');
            if($path && ($path[0]??null)!='/') return $this->addError('getContents($file, $path) $path has to start with /');

            $ret = '';
            try{
                $ret = @file_get_contents($this->bucket.$path.'/'.$file);
                if($ret=== false) {
                    $this->addError(error_get_last());
                }
            } catch(Exception $e) {
                $this->addError($e->getMessage());
                $this->addError(error_get_last());
            }
            return($ret);
        }

        /**
         * Delete a filename from $path. if $path is empty it assumes '/'
         *
         * example:
         * ```php
         * if(!$bucket->deleteFile('/tmp/file.txt')) {
         *     if($bucket->error) return ['errorCode'=>$bucket->errorCode,'errorMsg'=>$bucket->errorMsg];
         *     else echo "/tmp/file.txt does not exist or can not be deleted";
         * } else echo "/tmp/file.txt has been deleted";
         * @param string $filename_path path to the file starting with '/' inside the bucket
         * @return bool|void true if the file has been deleted. false if out exist and void if error
         */
        function deleteFile(string $filename_path) {
            if(($filename_path[0]??null)!='/') return $this->addError('deleteFile($filename_path) $filename_path has to start with /');
            try{
                if(!$this->isFile($filename_path)) return false;
                $ret = unlink($this->bucket.$filename_path);
                if($ret === false) return $this->addError(error_get_last());
                else return true;
            } catch(Exception $e) {
                die($e->getMessage());
                $this->addError($e->getMessage());
                $this->addError(error_get_last());
                return;
            }
        }

        /**
         * Provides a PUT-signed URL that is used to upload an object.
         * More information in https://cloud.google.com/storage/docs/access-control/signing-urls-with-helpers#client-libraries_1
         *
         * notes:
         *  * if you are using postman send file content as binary.
         *  * you need to use a service account with the right credentials instead a personal credential to avoid the error: Credentials fetcher does not implement Google\Auth\SignBlobInterface
         *
         * example
         * ```php
         * $url = $this->bucket->getSignedUploadUrl('/uploads/video_file')
         * if($bucket->error) return ['errorCode'=>$bucket->errorCode,'errorMsg'=>$bucket->errorMsg];
         * ```
         * @param string $upload_file_path file name path to upload the file
         * @param init $expiration_in_minutes minutes to expire the url
         * @return string|void $returnUrl is the url the system has to call once the file has been uploaded
         */
        function getSignedUploadUrl($upload_file_path,int $expiration_in_minutes=15) {
            if(($upload_file_path[0]??null)!='/') return $this->addError('getSignedUploadUrl($upload_file_path) $filename_path has to start with /');

            try{
                $url=null;
                $object = $this->gs_bucket->object(substr($upload_file_path,1));

                //region DEPRECATED $object->signedUploadUrl
//                if(false) {
//                    $signed_upload_url = $object->signedUploadUrl(new \DateTime($expiration_in_minutes.' min'), [
//                        'version' => 'v4'
//                    ]);
//
//                    $headers = [
//                        'Content-Length' => 0,
//                        'x-goog-resumable' => 'start',
//                        'Origin' => '*'
//                    ];
//
//                    // step 2 - beginSignedUploadSession (POST)
//                    $response = $this->core->request->post($signed_upload_url, null, $headers);
//
//                    if (in_array($this->core->request->getLastResponseCode(), [200, 201])) {
//                        $url = $this->core->request->getResponseHeader('Location');
//                    } else {
//                        die('error');
//                    }
//                }
                //endregion

                // This is to upload  but it requires a Google Signature
                if(true) {
                    $url = $object->signedUrl(
                    # This URL is valid for 15 minutes
                        new \DateTime($expiration_in_minutes.' min'),
                        [
                            'method' => 'PUT',
                            'version' => 'v4',
                        ]
                    );
                }

                return($url);
            } catch(Exception $e) {
                $this->addError($e->getMessage());
                $this->addError(error_get_last());
                return;
            }
        }

        /**
         * Provides a temporal signed URL to download an object.
         * More information in https://cloud.google.com/storage/docs/access-control/signing-urls-with-helpers#client-libraries_1
         *
         * notes:
         *  * if you are using postman send file content as binary.
         *  * you need to use a service account with the right credentials instead a personal credential to avoid the error: Credentials fetcher does not implement Google\Auth\SignBlobInterface
         *
         * example
         * ```php
         * $url = $this->bucket->getSignedDownloadUrl('/uploads/video_file',2,['responseDisposition'=>'attachment; filename="myvideo.mp4"'])
         * if($bucket->error) return ['errorCode'=>$bucket->errorCode,'errorMsg'=>$bucket->errorMsg];
         * ```
         * @param string $filename_path file name path to upload the file
         * @param init $expiration_in_minutes minutes to expire the url. 1 minute by default

         * @param array $download_options  [optional] {
         *     Available options to update the object.
         *
         *     @type string $responseDisposition Disposition of the file. Check https://developer.mozilla.org/en-US/docs/Web/HTTP/Headers/Content-Disposition for further information. Exmples: "inline", 'attachment; filename="{filename}"'
         * }
         * @return string|void the url to redirect in order to download or access to the object
         */
        function getSignedDownloadUrl($filename_path,int $expiration_in_minutes=1,$download_options=[]) {

            try{
                $object = $this->gs_bucket->object($filename_path);
                $options=[
                    'version' => 'v4',
                ];
                if($download_options['responseDisposition']??null) $options['responseDisposition'] = $download_options['responseDisposition'];
                $url = $object->signedUrl(
                # This URL is valid for 15 minutes
                    new \DateTime($expiration_in_minutes.' min'),$options
                );
                return $url;
            } catch(Exception $e) {
                $this->addError($e->getMessage());
                $this->addError(error_get_last());
                return;
            }

        }

        /**
         * Set the file as private
         *
         * example
         * ```php
         * $bucket->setFilePrivate('/file.txt');
         * if($bucket->error) return ['errorCode'=>$bucket->errorCode,'errorMsg'=>$bucket->errorMsg];
         * ```
         * @param string $file_path file path without gs://<bucket-name>/
         * @return string|void return string public url or void if error
         */
        function setFilePrivate(string $file_path)
        {
            $this->core->__p->add('Buckets.setPrivate', $file_path, 'note');


            //region CHECK $this->bucketInfo['iamConfiguration']['uniformBucketLevelAccess']['enabled']
            if(($this->bucketInfo['iamConfiguration']['uniformBucketLevelAccess']['enabled'] ?? null))
                return $this->addError('uniformBucketLevelAccess is enabled. The object can not have specific permissions');
            //endregion

            //region REMOVE from $file $this->bucket as part of the string
            if($this->bucket && strpos($this->bucket,'gs://')===0) {
                $bucket = $this->bucket.((substr($this->bucket,-1)!='/')?'/':'');
                $file_path = str_replace($bucket,'',$file_path);
            }
            //endregion

            //region REMOVE in $file first character '/' it exist
            $file_path = ltrim($file_path,'/');
            //endregion

            //region SET (StorageObject)$object, $infoObject,$updateObject=[] taking $file_path
            try {
                /** @var StorageObject $object */
                $object = $this->gs_bucket->object($file_path);
                $object->update([], ['predefinedAcl' => 'private']);
                $ret = ['info'=>$object->info(),'acl'=>$object->acl()->get()];
                $this->core->__p->add('Buckets.setPrivate', null, 'note');
                return $ret;

            } catch (Exception $e){
                return $this->addError($e->getMessage());
            }
            //endregion
        }

        /**
         * Set the file as publicRead
         *
         * example
         * ```php
         * $bucket->setFilePublic('/file.txt');
         * if($bucket->error) return ['errorCode'=>$bucket->errorCode,'errorMsg'=>$bucket->errorMsg];
         * ```
         * @param string $file_path file path without gs://<bucket-name>/
         * @return string|void return string public url or void if error
         */
        function setFilePublic(string $file_path)
        {
            $this->core->__p->add('Buckets.setPrivate', $file_path, 'note');

            //region CHECK $this->bucketInfo['iamConfiguration']['publicAccessPrevention']
            if(($this->bucketInfo['iamConfiguration']['publicAccessPrevention']??null) == 'enforced')
                return $this->addError('publicAccessPrevention is enforced. The object can not be public');
            //endregion

            //region CHECK $this->bucketInfo['iamConfiguration']['uniformBucketLevelAccess']['enabled']
            if(($this->bucketInfo['iamConfiguration']['uniformBucketLevelAccess']['enabled'] ?? null))
                return $this->addError('uniformBucketLevelAccess is enabled. The object can not have specific permissions');
            //endregion

            //region REMOVE from $file $this->bucket as part of the string
            if($this->bucket && strpos($this->bucket,'gs://')===0) {
                $bucket = $this->bucket.((substr($this->bucket,-1)!='/')?'/':'');
                $file_path = str_replace($bucket,'',$file_path);
            }
            //endregion

            //region REMOVE in $file first character '/' it exist
            $file_path = ltrim($file_path,'/');
            //endregion

            //region SET (StorageObject)$object, $infoObject,$updateObject=[] taking $file_path
            try {
                /** @var StorageObject $object */
                $object = $this->gs_bucket->object($file_path);
                $object->update([], ['predefinedAcl' => 'publicRead']);
                $ret = $object->info(['projection'=>'full']);
                $ret['publicUrl'] = 'https://storage.googleapis.com/'.$this->gs_bucket->name().'/'.$file_path;
                $this->core->__p->add('Buckets.setPrivate', null, 'note');
                return $ret;

            } catch (Exception $e){
                return $this->addError($e->getMessage());
            }
            //endregion
        }


        /**
         * Update File object properties
         *
         * example:
         * ```php
         * $filename = 'video.mp4';
         * $mime_type = $this->bucket->getMimeTypeFromExtension(pathinfo($filename, PATHINFO_EXTENSION));
         * $options = ['filename'=>$filename,'contentType'=>$mime_type,'public'=>true,'private'=>false];
         * $object_info =$this->bucket->updateFileObject('/uploads/upload_tmp_file',$options);
         * if($this->bucket->error) return $this->setErrorFromCodelib($this->bucket->errorCode,$this->bucket->errorMsg);
         * ```
         * @param string $filename_path file path starting with '/'
         * @param array $file_options  [optional] {
         *     Available options to update the object.
         *
         *     @type string $filename We can change the name of the file with this parameter. It can not have '/' char
         *     @type string $contentType contentType of the file
         *     @type string $contentDisposition Disposition of the file. Check https://developer.mozilla.org/en-US/docs/Web/HTTP/Headers/Content-Disposition for further information
         *     @type string $predefinedAcl defines the access of the file. It can be: "authenticatedRead", "bucketOwnerFullControl", "bucketOwnerRead", "private", "projectPrivate", and "publicRead"
         *     @type bool $public It says the object has to be public and return publicUrl attribute. It equals to ['predefinedAcl'=>'publicRead']. If it is sent with true value then $predefinedAcl is ignored
         *     @type bool $private It says the object has to be public and return publicUrl attribute. It equals to ['predefinedAcl'=>'private']. If it is sent with true value then $public and $predefinedAcl are ignored
         * }
         * @return array|void return the array with the file properties
         *
         * example:
         * ```json
         * {
        +     "kind": "storage#object",
         *     id": "{bucket}/uploads/video.mp4/xxxxx",
         *     selfLink": "https://www.googleapis.com/storage/v1/b/{bucket}/o/uploads%2Fvideo.mp4",
         *     mediaLink": "https://storage.googleapis.com/download/storage/v1/b/{bucket}/o/uploads%2Fvideo.mp4?generation=1672201560118273&alt=media",
         *     name": "uploads/video.mp4",
         *     bucket": "{bucket}",
         *     generation": "1672201560118273",
         *     metageneration": "5",
         *     contentType": "video/mpeg",
         *     storageClass": "STANDARD",
         *     size": "1298315450",
         *     md5Hash": "h0KTFOhrEFbwdWqhRBQRkQ==",
         *     cacheControl": "no-cache",
         *     crc32c": "dML25A==",
         *     etag": "CIGo75+8m/wCEAU=",
         *     timeCreated": "2022-12-28T04:26:00.124Z",
         *     updated": "2022-12-28T04:31:15.669Z",
         *     timeStorageClassUpdated": "2022-12-28T04:26:00.124Z",
         *     acl": [
         *      {
         *         kind": "storage#objectAccessControl",
         *         object": "uploads/video.mp4",
         *         generation": "1672201560118273",
         *         id": "{bucket}/uploads/video.mp4/1672201560118273/user-datastore-terminal@cloudframework-io.iam.gserviceaccount.com",
         *         selfLink": "https://www.googleapis.com/storage/v1/b/{bucket}/o/uploads%2Fvideo.mp4/acl/user-datastore-terminal@cloudframework-io.iam.gserviceaccount.com",
         *         bucket": "{bucket}",
         *         entity": "user-datastore-terminal@cloudframework-io.iam.gserviceaccount.com",
         *         role": "OWNER",
         *         email": "datastore-terminal@cloudframework-io.iam.gserviceaccount.com",
         *         etag": "CIGo75+8m/wCEAU="
         *      },
         *      {
         *         kind": "storage#objectAccessControl",
         *         object": "uploads/video.mp4",
         *         generation": "1672201560118273",
         *         id": "{bucket}/uploads/video.mp4/1672201560118273/allUsers",
         *         selfLink": "https://www.googleapis.com/storage/v1/b/{bucket}/o/uploads%2Fvideo.mp4/acl/allUsers",
         *         bucket": "{bucket}",
         *         entity": "allUsers",
         *         role": "READER",
         *         etag": "CIGo75+8m/wCEAU="
         *      }
         *    ],
         *     owner": {
         *         entity": "xxxxl@xxxxx.iam.gserviceaccount.com"
         *     },
         *     publicUrl": "https://storage.googleapis.com/{bucket}/video.mp4"
         * }
         * ```
         */
        function updateFileObject(string $filename_path,array $file_options)
        {
            //region VERIFY $this->gs_bucket is an object
            if(!is_object($this->gs_bucket)) return $this->addError('updateFileObject($filename_path,$file_options) has been called but with no bucket initiated');
            //endregion

            //region VERIFY if $file_path status with '/' to delete it
            if($filename_path[0] == '/') $filename_path = substr($filename_path,1);
            //endregion

            //region VERIFY if $file_options['filename'] does not have '/' to delete it
            if(strpos(($file_options['filename']??''),'/')!==false) return $this->addError('updateFileObject($filename_path,$file_options) has received $file_options["filename"] with / char');
            //endregion

            //region CREATE (StorageObject)$object and Return object information
            try {
                /** @var StorageObject $object */
                $object = $this->gs_bucket->object($filename_path);
                //region EVALUATE $file_options['name'] to rename file
                $object_info = $object->info(['projection'=>'full']);
                if(($file_options['filename']??null) && $file_options['filename']!=basename($object_info['name'])) {
                    $new_file_path = dirname($object_info['name']).'/'.$file_options['filename'];
                    $new_object = $object->copy($this->bucketInfo['name'],['name'=>$new_file_path]);
                    $object->delete();
                    $object = &$new_object;
                }
                //endregion

                //region EVALUATE in $metadada $file_options['contentType']
                $metadata = [];
                if($file_options['contentType']??null) $metadata['contentType'] = $file_options['contentType'];
                if($file_options['contentDisposition']??null) $metadata['contentDisposition'] = $file_options['contentDisposition'];
                //endregion

                //region EVALUATE in $options: $file_options['public'], $file_options['predefinedAcl']
                $options = ['projection'=>'full'];
                if(($file_options['public']??null)) $file_options['predefinedAcl'] = 'publicRead';
                if(($file_options['private']??null)) $file_options['predefinedAcl'] = 'private';
                $extra_info=[];
                if(in_array(($file_options['predefinedAcl']??''),["authenticatedRead", "bucketOwnerFullControl", "bucketOwnerRead", "private", "projectPrivate", "publicRead"])) {
                    if($file_options['predefinedAcl']=='publicRead') $extra_info['publicUrl'] = '';
                    //region SET $extra_info['publicAccessPrevention'] and RETURN error if $public and $this->bucketInfo['iamConfiguration']['publicAccessPrevention']??null) == 'enforced'
                    if(($this->bucketInfo['iamConfiguration']['publicAccessPrevention']??null) == 'enforced' && $file_options['predefinedAcl']=='publicRead')
                        $extra_info['publicAccessPrevention'] = "true. Object can not be public";
                    elseif(($this->bucketInfo['iamConfiguration']['uniformBucketLevelAccess']['enabled'] ?? null)){
                        $extra_info['uniformBucketLevelAccess'] = "enabled. Objects can not be forced to be public. Check the bucket properties";
                        if($file_options['predefinedAcl']=='publicRead')
                            $extra_info['publicUrl'] = 'https://storage.googleapis.com/'.$this->gs_bucket->name().$filename_path;
                    } else {
                        $options['predefinedAcl'] = $file_options['predefinedAcl'];
                        if($options['predefinedAcl']=='publicRead')
                            $extra_info['publicUrl'] = 'https://storage.googleapis.com/'.$this->gs_bucket->name().$filename_path;
                    }
                }
                //endregion

                //region UPDATE current object with $metadata,$options
                $object_info = $object->update($metadata,$options);
                //endregion



                return array_merge($object_info,$extra_info);
            } catch (Exception $e){
                return $this->addError($e->getMessage());
            }
            //endregion
        }

        /**
         * It returns a public URL for the object making it Public you have the rights and the Bucket is Granular
         *
         * Example of php code
         * ```php
         * $bucket = $this->core->loadClass('Buckets',"{$bucket_name}");
         * if(!$bucket->error) return $bucket->errorMsg;
         * $url= $bucket->getPublicUrl('/path/to_file');
         * if(!$bucket->error) return $bucket->errorMsg;
         * else echo($url);
         * ```
         * @param string $file_path filename path without starting with '/'
         * @param string $content_type optionally you can set content_type
         * @return string|void return string public url or void if error
         */
        function getPublicUrl(string $file_path, string $content_type='')
        {

            $this->core->__p->add('Buckets.getPublicUrl', $file_path, 'note');


            //region CHECK $this->bucketInfo['iamConfiguration']['publicAccessPrevention']
            if (($this->bucketInfo['iamConfiguration']['publicAccessPrevention'] ?? null) == 'enforced')
                return $this->addError('publicAccessPrevention is enforced. The object can not be public');
            //endregion

            //region REMOVE from $file $this->bucket as part of the string
            if ($this->bucket && strpos($this->bucket, 'gs://') === 0) {
                $bucket = $this->bucket . ((substr($this->bucket, -1) != '/') ? '/' : '');
                $file_path = str_replace($bucket, '', $file_path);
            }
            //endregion

            //region REMOVE in $file first character '/' it exist
            $file_path = ltrim($file_path, '/');
            //endregion

            //region SET (StorageObject)$object, $infoObject,$updateObject=[] taking $file_path
            try {
                /** @var StorageObject $object */
                $object = $this->gs_bucket->object($file_path);
                $info = $object->info();
            } catch (Exception $e) {
                return $this->addError($e->getMessage());
            }
            $updateObject = [];
            //endregion

            // allow to updated permissions if uniformBucketLevelAccess is not enabled
            if (!($this->bucketInfo['iamConfiguration']['uniformBucketLevelAccess']['enabled'] ?? null)) {

                //region EVALUATING $content_type to modify $updateObject
                if ($content_type && $content_type != ($info['contentType'] ?? null))
                    $updateObject['contentType'] = $content_type;
                //endregion

                //region EVALUATING if the object is Public to modify $updateObject
                try {
                    $isPublic = $object->acl()->get(['entity' => 'AllUsers']);
                } catch (Exception $e) {
                    $updateObject['acl'] = [];
                }
                //endregion

                //region IF $updateObject EXECUTE $object->update($updateObject, (isset($updateObject['acl']))?['predefinedAcl' => 'PUBLICREAD']:[]);
                if ($updateObject) {
                    try {
                        $object->update($updateObject, (isset($updateObject['acl'])) ? ['predefinedAcl' => 'PUBLICREAD'] : []);
                    } catch (Exception $e) {
                        return $this->addError($e->getMessage());
                    }
                }
                //endregion
            }

            $this->core->__p->add('Buckets.getPublicUrl', null, 'endnote');
            return 'https://storage.googleapis.com/'.$this->gs_bucket->name().'/'.$file_path;

        }

        /**
         * Returns the GCS bucket info of the gs://bucketname you passed in the Object construction
         *
         * Example of php code
         * ```php
         * $bucket = $this->core->loadClass('Buckets',"{$bucket_name}");
         * $bucket->getInfo();
         * ```
         *
         * example to array returned
         * ```json
         * {
         *    "kind": "storage#bucket",
         *    "selfLink": "https://www.googleapis.com/storage/v1/b/{$bucket_name}",
         *    "id": "{id-backet-name}",
         *    "name": "academy-bucket-public",
         *    "projectNumber": "{number of bucket}}",
         *    "metageneration": "2",
         *    "location": "EUROPE-WEST1",
         *    "storageClass": "STANDARD",
         *    "etag": "CAI=",
         *    "timeCreated": "2022-12-16T08:47:07.180Z",
         *    "updated": "2022-12-16T08:47:24.727Z",
         *    "iamConfiguration": {
         *       "bucketPolicyOnly": {
         *           "enabled": true,
         *           "lockedTime": "2023-03-16T08:47:07.180Z"
         *       },
         *       "uniformBucketLevelAccess": {
         *           "enabled": true,
         *           "lockedTime": "2023-03-16T08:47:07.180Z"
         *       },
         *       "publicAccessPrevention": "inherited"
         *    },
         *   "locationType": "region"
         * }
         * ```
         * @return array|void
         */
        function getInfo() {
            try {
                return (is_object($this->gs_bucket))?$this->gs_bucket->info():null;
            } catch (Exception $e){
                return $this->addError($e->getMessage());
            }
        }

        /**
         * Returns the GCS Admin URL for the bucket
         *
         * Example of php code
         * ```php
         * $bucket = $this->core->loadClass('Buckets',"{$bucket_name}");
         * if(!$bucket->error) echo $bucket->getAdminUrl();
         * ```
         * @return string|null
         */
        function getAdminUrl() {
            return $this->gs_bucket_url;
        }

        /**
         * Returns the GCS $file_path object info
         *
         * Example of php code
         * ```php
         * $bucket = $this->core->loadClass('Buckets',"{$bucket_name}");
         * if(!$bucket->error) return $bucket->errorMsg;
         * $info= $bucket->getFileInfo('/path/to_file');
         * if(!$bucket->error) return $bucket->errorMsg;
         * else var_dump($info);
         * ```
         * @param string $file_path route to the file taking gs://{bucket_name}/ as the root path
         * @return array|void
         */
        function getFileInfo(string $file_path) {

            //region VERIFY $this->gs_bucket is an object
            if(!is_object($this->gs_bucket)) return $this->addError('getFileInfo($file) has been called but with no bucket initiated');
            //endregion

            //region VERIFY if $file_path status with '/' to delete it
            if($file_path[0] == '/') $file_path = substr($file_path,1);
            //endregion

            //region CREATE (StorageObject)$object and Return object information
            try {
                /** @var StorageObject $object */
                $object = $this->gs_bucket->object($file_path);
                return $object->info(['projection'=>'full']);
            } catch (Exception $e){
                return $this->addError($e->getMessage());
            }
            //endregion


        }

        /**
         * Returns an url to download a gs_file using CFBlobDownload Technique
         *
         * @ignore
         * @deprecated
         * @param $url
         * @param $params array ['downloads'=>'number of downloads allowed, by default 1', 'spacename'=>'to store downloads files in Datastore', 'content-type' =>'content type of the file']
         * @return string URL to download the file $params['donwloads'] times
         */
        function getCFBlobDownloadUrl($gs_file, $params,$blob_service = 'https://api.cloudframework.io/blobs') {

            // check the file exists
            if(!is_file($gs_file)) return($this->addError("{$gs_file} does not exist"));

            // Get Hash from file_name and params
            $hash = md5($gs_file.json_encode($params));
            $url = $blob_service.'/'.$hash;

            $cache = $params;

            // downloads allowed
            $cache['downloads'] = (isset($params['downloads']) && intval($params['downloads'])>0)?intval($params['downloads']):1;

            //if we are going to use DataStore temporal files retrive spacename
            $spacename = (isset($params['spacename']))?$params['spacename']:null;
            if($spacename) {
                $this->core->cache->activateDataStore($this->core,$spacename);
                $url.='/ds/'.$spacename;
            }

            // adding $url
            $cache['url'] = $gs_file;

            // downloads allowed
            $cache['content-type'] = (isset($params['content-type']))?$params['content-type']:'application/octet-stream';

            $this->core->cache->set($hash,$cache);

            return $url;

        }

        /**
         * Return the mime type for $extension. If the extension is not found it returns 'application/octet-stream'
         *
         * Example of php code
         * ```php
         * $bucket = $this->core->loadClass('Buckets',"{$bucket_name}");
         * if(!$bucket->error) return $bucket->errorMsg;
         * $mime_type= $bucket->getMimeTypeFromExtension('jpg');
         * else echo($mime_type);
         * ```
         * The list has been got from https://developer.mozilla.org/en-US/docs/Web/HTTP/Basics_of_HTTP/MIME_types/Common_types
         * @param string $extension is the extension: jpeg,txt,...
         * @return string
         */
        public function getMimeTypeFromExtension(string $extension) {
            $extensions_mime_types = ['aac'=>'audio/aac',
                'abw'=>'application/x-abiword',
                'arc'=>'application/x-freearc',
                'avif'=>'image/avif',
                'avi'=>'video/x-msvideo',
                'azw'=>'application/vnd.amazon.ebook',
                'bin'=>'application/octet-stream',
                'bmp'=>'image/bmp',
                'bz'=>'application/x-bzip',
                'bz2'=>'application/x-bzip2',
                'cda'=>'application/x-cdf',
                'csh'=>'application/x-csh',
                'css'=>'text/css',
                'csv'=>'text/csv',
                'doc'=>'application/msword',
                'docx'=>'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'eot'=>'application/vnd.ms-fontobject',
                'epub'=>'application/epub+zip',
                'gz'=>'application/gzip',
                'gif'=>'image/gif',
                'htm'=>'text/html',
                'html'=>'text/html',
                'ico'=>'image/vnd.microsoft.icon',
                'ics'=>'text/calendar',
                'jar'=>'application/java-archive',
                'jpg'=>'image/jpeg',
                'jpeg'=>'image/jpeg',
                'js'=>'text/javascript (Specifications: HTML and RFC 9239)',
                'json'=>'application/json',
                'jsonld'=>'application/ld+json',
                'mid, midi'=>'audio/midi, audio/x-midi',
                'mjs'=>'text/javascript',
                'mp3'=>'audio/mpeg',
                'mp4'=>'video/mp4',
                'mpeg'=>'video/mpeg',
                'mpkg'=>'application/vnd.apple.installer+xml',
                'odp'=>'application/vnd.oasis.opendocument.presentation',
                'ods'=>'application/vnd.oasis.opendocument.spreadsheet',
                'odt'=>'application/vnd.oasis.opendocument.text',
                'oga'=>'audio/ogg',
                'ogv'=>'video/ogg',
                'ogx'=>'application/ogg',
                'opus'=>'audio/opus',
                'otf'=>'font/otf',
                'png'=>'image/png',
                'pdf'=>'application/pdf',
                'php'=>'application/x-httpd-php',
                'ppt'=>'application/vnd.ms-powerpoint',
                'pptx'=>'application/vnd.openxmlformats-officedocument.presentationml.presentation',
                'rar'=>'application/vnd.rar',
                'rtf'=>'application/rtf',
                'sh'=>'application/x-sh',
                'svg'=>'image/svg+xml',
                'tar'=>'application/x-tar',
                'tiff'=>'image/tiff',
                'tif'=>'image/tiff',
                'ts'=>'video/mp2t',
                'ttf'=>'font/ttf',
                'txt'=>'text/plain',
                'vsd'=>'application/vnd.visio',
                'wav'=>'audio/wav',
                'weba'=>'audio/webm',
                'webm'=>'video/webm',
                'webp'=>'image/webp',
                'woff'=>'font/woff',
                'woff2'=>'font/woff2',
                'xhtml'=>'application/xhtml+xml',
                'xls'=>'application/vnd.ms-excel',
                'xlsx'=>'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'xml'=>'application/xml',
                'xul'=>'application/vnd.mozilla.xul+xml',
                'zip'=>'application/zip',
                '3gp'=>'video/3gpp',
                '3g2'=>'video/3gpp2',
                '7z'=>'application/x-7z-compressed'];
            return $extensions_mime_types[strtolower($extension)]??'application/octet-stream';
        }


        /**
         * Return the path including the bucket name
         * @param string $path
         * @return string
         */
        public function getBucketPath(string $path) {
            $bucket_path = '';
            if(strpos($path,'gs://')!==0) {
                $bucket_path = $this->bucket;
                if($path && ($path[0]??null)!='/') $bucket_path.= '/';
            }

            $bucket_path.= $path;
            if(substr($bucket_path,-1)=='/') $bucket_path = substr($bucket_path,0,-1);
            return $bucket_path;
        }

        /**
         * Set an error in the class initiating $this->errorMsg
         * @ignore
         * @param $msg
         * @param string $code
         */
        private function setError($msg,$code='') {
            $this->errorMsg = array();
            $this->addError($msg,$code);
        }

        /**
         * Add an error in the class in the array $this->errorMsg
         * @ignore
         * @param $msg
         * @param string $code
         */
        private function addError($msg,$code='') {
            $this->error = true;
            $this->errorMsg[] = $msg;
            $this->errorCode = $code;
        }
    }
}