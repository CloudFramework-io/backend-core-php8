<?php
// CLOUD Documentum:
//   DevGroup: /cf/products/cloud-development
//   DevUnit: /php/classes/Core7/CoreSecurity, /php/classes/Core7/CoreCache, /cloud-development/frameworks/php
//   Activities: 5630949161435136, 4822239115739136, 6651158731161600
/**
 * Core classes developed in ^PHP8.1 to be used to develop APIs, Scripts, Web Pages
 *
 * Core7 class is included in your APIs as $this->core and it includes objects
 * of other classes to facilitate your developments.
 * * $this->core = new Core7();
 * * $this->core->__p = new CorePerformance();
 * * $this->core->is = new CoreIs();
 * * $this->core->system = new CoreSystem($root_path);
 * * $this->core->logs = new CoreLog();
 * * $this->core->errors = new CoreLog();
 * * $this->core->config = new CoreConfig($this, __DIR__ . '/config.json');
 * * $this->core->session = new CoreSession($this);
 * * $this->core->security = new CoreSecurity($this);
 * * $this->core->cache = new CoreCache($this);
 * * $this->core->request = new CoreRequest($this);
 * * $this->core->user = new CoreUser($this);
 * * $this->core->localization = new CoreLocalization($this);
 * * $this->core->model = new CoreModel($this);
 * * $this->core->cfiLog = new CFILog($this);
 * @author Héctor López <hlopez@cloudframework.io>
 * @package Core
 */

use Google\Cloud\Logging\Logger;
use Google\Cloud\Logging\PsrLogger;
use Google\Cloud\Storage\StorageClient;

if (!defined("_CLOUDFRAMEWORK_CORE_CLASSES_")) {
    /**
     * @ignore
     */
    define("_CLOUDFRAMEWORK_CORE_CLASSES_", TRUE);

    /**
     * Echo in output a group of vars passed as args
     * @ignore
     * @param mixed $args Element to print.
     */
    function __print($args)
    {
        if (key_exists('PWD', $_SERVER)) echo "\n";
        else echo "<pre>";
        for ($i = 0, $tr = count($args); $i < $tr; $i++) {
            if ($args[$i] === "exit")
                exit;
            if (key_exists('PWD', $_SERVER)) echo "\n[$i]: ";
            else echo "\n<li>[$i]: ";

            if (is_array($args[$i]))
                echo print_r($args[$i], TRUE);
            else if (is_object($args[$i]))
                echo var_dump($args[$i]);
            else if (is_bool($args[$i]))
                echo ($args[$i]) ? 'true' : 'false';
            else if (is_null($args[$i]))
                echo 'NULL';
            else
                echo $args[$i];
            if (key_exists('PWD', $_SERVER)) echo "\n";
            else echo "</li>";
        }
        if (key_exists('PWD', $_SERVER)) echo "\n";
        else echo "</pre>";
    }

    /**
     * @ignore
     * @throws Exception
     */
    function __fatal_handler() {
        global $core;
        $errfile = "unknown file";
        $errstr  = "shutdown";
        $errno   = E_CORE_ERROR;
        $errline = 0;

        $error = error_get_last();

        // avoid E_USER_DEPRECATED errors
        if( $error !== NULL && $error["type"]!=E_USER_DEPRECATED) {
            $errno   = $error["type"];
            $errfile = $error["file"];
            $errline = $error["line"];
            $errstr  = $error["message"];

            //Catch gzuncompress() in Core7 file for cache
            if ($errno == E_WARNING && $errstr == 'gzuncompress(): data error' && strpos($errfile,'Core7.php')) {
                $core->logs->add('gzuncompress() failed. Potential wrong credentials in CoreCache->get() or wrong content in CoreSession->get()','gzumcompress','warning');
                return;
            }
            //Catch StreamWrapper::stream_set_option
            if ($errno == E_WARNING && $errstr == 'gzuncompress(): data error' && strpos($errfile,'Core7.php')) {
                $core->logs->add('gzuncompress() failed. Potential wrong credentials in CoreCache->get() or wrong content in CoreSession->get()','gzumcompress','warning');
                return;
            }

            if($core) {
                $core->errors->add($error, 'fatal_error', 'error');
//                if(is_object($core->api)) {
//                    $core->api->setError('__fatal_handler',503,'system-error',$error["message"]);
//                    $core->api->send();
//
//                }
            }
//
//            if(!$core || ($core->is->development() && !$core->is->terminal()))
//                _print( ["ErrorCode"=>$errno, "ErrorMessage"=>$errstr, "File"=>$errfile, "Line"=>$errline]);
        }
    }
    register_shutdown_function( "__fatal_handler" );
    function __warning_handler_datastore() {
        //DO NOTHING. Google\Cloud\Datastore\Operations function runQuery(..) return Undefined index: entityResults line 527 for php 7.4
        //Github issue: https://github.com/googleapis/google-cloud-datastore/issues/317
    }

    /**
     * Print a group of mixed vars passed as arguments. Example _print($object,$array,$class,...)
     */
    function _print()
    {
        __print(func_get_args());
    }

    /**
     * _print() with an exit
     */
    function _printe()
    {
        __print(array_merge(func_get_args(), array('exit')));
    }
    //endregion

    /**
     * _print() with an exit
     */
    function _printLog()
    {
        global $core;
        if(is_object($core)) {
            $core->logs->add(json_encode(func_get_args(), JSON_PRETTY_PRINT),'printLog','debug');
        }
    }
    //endregion

    /**
     * $this->core Core7 class is included in your APIs as $this->core and it includes objects
     * of other classes to facilitate your development:
     *
     * * $this->core->__p = new CorePerformance();
     * * $this->core->is = new CoreIs();
     * * $this->core->system = new CoreSystem($root_path);
     * * $this->core->logs = new CoreLog();
     * * $this->core->errors = new CoreLog();
     * * $this->core->config = new CoreConfig($this, __DIR__ . '/config.json');
     * * $this->core->session = new CoreSession($this);
     * * $this->core->security = new CoreSecurity($this);
     * * $this->core->cache = new CoreCache($this);
     * * $this->core->request = new CoreRequest($this);
     * * $this->core->user = new CoreUser($this);
     * * $this->core->localization = new CoreLocalization($this);
     * * $this->core->model = new CoreModel($this);
     * * $this->core->cfiLog = new CFILog($this);
     *
     * @package Core.core
     */
    final class Core7
    {
        // Version of the Core7 CloudFrameWork
        var $_version = '8.5.28';  // 2026-11-12
        /** @var CorePerformance $__p */
        var  $__p;
        /** @var CoreIs $is */
        var  $is;
        /** @var CoreUtils $utils */
        var  $utils;
        /** @var CoreSession $session */
        var  $session;
        /** @var CoreSystem $system */
        var  $system;
        /** @var CoreLog $logs */
        var  $logs;
        /** @var CoreLog $errors */
        var  $errors;
        /** @var CoreConfig $config */
        var $config;
        /** @var string $namespace Default namespace for the Platform */
        var $namespace = 'default';
        /** @var array $platform Platform Data Configuration */
        var $platform = [];
        /** @var CoreSecurity $security */
        var $security;
        /** @var CoreCache $cache */
        var $cache;
        /** @var CoreAuth $auth */
        var $auth;
        /** @var CoreRequest $request */
        var $request;
        /** @var CoreLocalization $localization */
        var $localization;
        /** @var CoreUser $user */
        var $user;
        /** @var CoreModel $model */
        var $model;
        /** @var CFILog $cfiLog */
        var $cfiLog;
        /** @var string $gc_project_id GCP Google Project associated */
        var $gc_project_id;
        /** @var RESTful $api if we are executing an API code it will be the RESTful class */
        var $api;
        /**
         * @var array $loadedClasses control the classes loaded
         * @link Core::loadClass()
         */
        private $loadedClasses = [];
        /** @var StorageClient $gc_datastorage_client  */
        var $gc_datastorage_client = null;          // Google Cloud DataStorage Client

        /** @var string $gc_project_service called */
        var $gc_project_service = 'default';

        /** @var string $cf_api_base URL base for CLOUDFRAMEWORK API Services */
        protected $cf_api_base = 'https://api.cloudframework.io';

        /**
         * Core constructor.
         * @param string $root_path
         */
        function __construct(string $root_path = '')
        {
            //region SET $this->__p,$this->is,$this->system,$this->logs,$this->errors
            $this->__p = new CorePerformance();
            $this->utils = new CoreUtils();
            $this->is = new CoreIs();
            $this->system = new CoreSystem($root_path);
            $this->logs = new CoreLog($this->is->development() && $this->is->terminal());
            $this->errors = new CoreLog($this->is->development() && $this->is->terminal());
            //endregion

            //region SET $this->config and evaluate to read local_config.json and local_script.json
            $this->config = new CoreConfig($this, __DIR__ . '/config.json');
            // CoreSystem is built before CoreConfig: give it the config to read core.log.sensitive_params (since 8.5.16)
            $this->system->core_config = $this->config;
            if ($this->is->development() && is_file($this->system->root_path . '/local_config.json')) {
                $this->config->readConfigJSONFile($this->system->root_path . '/local_config.json');
                $this->logs->add('[development] loaded local_config.json');
            }
            if ($this->is->development() && $this->is->terminal() && is_file($this->system->root_path . '/local_script.json')) {
                $this->config->readConfigJSONFile($this->system->root_path . '/local_script.json');
                $this->logs->add('[script] loaded local_script.json');
            }
            //endregion
            //region SET $this->session and evaluate if we are runnning from a termina
            $this->session = new CoreSession($this);
            // To run scripts you can use Session data to store temporal Data. The session expire every 180 minutes by default
            if($this->is->development() && $this->is->terminal()) {
                $this->session->debug = true;
                $this->session->init('CloudFrameworkScripts');
            }
            //endregion

            //region SET $this->security, $this->cache, $this->request, $this->localization, $this->model, $this->cfiLog
            $this->security = new CoreSecurity($this);
            $this->cache = new CoreCache($this);
            // since 8.5.22/8.5.23: without cache keys in config or env, a fixed Secret Manager version in managed runtimes
            // (core.gcp.secrets.cache_keys_secret) or per-machine keys only in local (see CoreSecurity::resolveMissingCacheKeys())
            if($this->security->cache_keys_missing && $this->security->resolveMissingCacheKeys()) {
                $this->config->cache_secret_key = $this->security->cache_key;
                $this->config->cache_secret_iv = $this->security->cache_iv;
            }
            $this->request = new CoreRequest($this);
            $this->user = new CoreUser($this);
            $this->localization = new CoreLocalization($this);
            $this->model = new CoreModel($this);
            $this->cfiLog = new CFILog($this);
            //endregion

            //region SET GCP basic vars: $this->gc_project_id, $this->gc_project_service
            if($this->config->get('core.gcp.project_id')) putenv('PROJECT_ID='.$this->config->get('core.gcp.project_id'));
            $this->gc_project_id = getenv('PROJECT_ID');
            $this->gc_project_service = ($this->config->get('core.gcp.project_service'))?$this->config->get('core.gcp.project_service'):'default';
            //endregion

            //region FIX env vars: DATASTORE_DATASET and DATASTORE_EMULATOR_HOST to handle Datastore development
            if($this->gc_project_id && !getenv('DATASTORE_DATASET'))  putenv('DATASTORE_DATASET='.$this->gc_project_id);

            if($DATASTORE_EMULATOR_HOST = getenv('DATASTORE_EMULATOR_HOST')) {
                if(strpos($DATASTORE_EMULATOR_HOST,'::1')===0) {
                    $DATASTORE_EMULATOR_HOST = str_replace('::1','localhost',$DATASTORE_EMULATOR_HOST);
                    putenv("DATASTORE_EMULATOR_HOST={$DATASTORE_EMULATOR_HOST}");
                }
            }
            //endregion

            // Local configuration
            $this->__p->add('Loaded $this->__p,$this->system,$this->logs,$this->errors,$this->is,$this->config,$this->session,$this->security,$this->cache,$this->request,$this->localization,$this->model,$this->cfiLog with __session[started=' . (($this->session->start) ? 'true' : 'false') . ']: ,', __METHOD__);

            // Config objects based in config
            $this->cache->setNameSpace($this->config->get('cacheSpacename')?:'cloudframework');

            // If the $this->system->app_path ends in / delete the char.
            $this->system->app_path = preg_replace('/\/$/','',$this->system->app_path);

            // region EVALUATE env variables:
            if($this->config->get("core.gcp.project_id")) putenv('PROJECT_ID='.$this->config->get("core.gcp.project_id"));
            if($this->config->get("core.gcp.credentials")) putenv('GOOGLE_APPLICATION_CREDENTIALS='.$this->config->get("core.gcp.credentials"));

            // Support of DataStorage to work with Buckets
            $this->initDataStorage();

        }

        /**
         * Returns the configuration error that must stop the application before it serves anything, or null.
         * Since 8.5.13 the cache keys are mandatory: config-vars core.gcp.secrets.cache_encrypt_key / cache_encrypt_iv
         * or the environment variables CF_CACHE_ENCRYPT_KEY / CF_CACHE_ENCRYPT_IV (see docs/guides/security.md).
         *
         * @return array|null ['code'=>string,'message'=>string]
         */
        public function getStartupError(): ?array {
            if($this->security->cache_keys_missing)
                return ['code'=>'security-cache-key-missing'
                    ,'message'=>'security-cache-key-missing: set core.gcp.secrets.cache_encrypt_key/cache_encrypt_iv or the env vars CF_CACHE_ENCRYPT_KEY/CF_CACHE_ENCRYPT_IV. See docs/guides/security.md'];
            return null;
        }

        /**
         * Stops the execution when getStartupError() returns an error. Used by the entry points of the framework
         * (dispatcher.php, mcp-server.php, runscript.php, runtest.php).
         * - HTTP: 503 with a generic message (no configuration detail is exposed); the detail goes to the log.
         * - Terminal: the detail to STDERR and exit code 1.
         *
         * @return void
         */
        public function exitOnStartupError(): void {
            if(!$error = $this->getStartupError()) return;
            error_log($error['message']);
            if($this->is->terminal()) {
                fwrite(STDERR, $error['message']."\n");
                exit(1);
            }
            if(!headers_sent()) {
                http_response_code(503);
                header('Content-Type: application/json');
            }
            echo json_encode(['success'=>false,'status'=>503,'code'=>'system-error','message'=>'The service is not correctly configured']);
            exit;
        }

        /**
         * Retrieves the base URL for the Cloud Framework API.
         *
         * @return string The base URL of the Cloud Framework API.
         */
        public function getCloudFrameworkAPIBaseURL() {
            return $this->cf_api_base;
        }

        /**
         * Sets the base URL for the Cloud Framework API if a valid URL is provided.
         * Returns the currently set base URL.
         *
         * @param string $url The base URL to be set. Must start with 'http'.
         * @return string The current Cloud Framework API base URL.
         */
        public function setCloudFrameworkAPIBaseURL(string $url) {
            if(!$this->is->validURL($url)) return $this->cf_api_base;
            else $this->cf_api_base = $url;
            return $this->cf_api_base;
        }
        /**
         * Dispatch method to handle the request flow, including headers, API endpoints, and logic execution.
         * Adds headers specified in configuration and loads API endpoints or common logic based on the request path.
         * It is used from /src/dispatcher.php to process PHP server calls
         * @return false|string
         */
        public function dispatch()
        {

            // core.dispatch.headers: Evaluate to add headers in the response.
            if($headers = $this->config->get('core.dispatch.headers')) {
                if(is_array($headers)) foreach ($headers as $key=>$value) {
                    header("$key: $value");
                }
            }

            // API end points. By default $this->config->get('core_api_url') is '/'
            if ($this->isApiPath()) {

                //region SET $apifile, $pathfile
                // Extract $apifile route
                $apifile = $this->system->url['parts'][$this->system->url['parts_base_index']];
                // If empty by default it will be index
                if(!$apifile) {
                    $apifile='index';
                }

                // Detect security issue
                if(preg_match('/\.(php|json|txt|xml)($|\/|\?)/',strtolower($apifile))) {
                    header("Content-Type: text/plain");
                    header("HTTP/1.0 403 Forbidden");
                    die("Your call with IP [{$this->system->ip}] has been intercepted");
                }

                // if $apifile starts with '_' character or start with queue the look into the framework
                if ($apifile[0] == '_' || $apifile == 'queue') {
                    $pathfile = __DIR__ . "/api/{$apifile}.php";
                    if (!file_exists($pathfile)) $pathfile = '';
                }
                // else look into user api structure.
                else {

                    // $apifile is a directory and there are more than one parameter
                    // then $apifile will be the firts_parameter/sencond_parameter
                    if(isset($this->system->url['parts'][$this->system->url['parts_base_index']+1])
                        && is_dir($this->system->app_path . "/api/{$apifile}")
                        && !file_exists($this->system->app_path . "/api/{$apifile}.php")
                    ) {
                        $apifile = $this->system->url['parts'][$this->system->url['parts_base_index']].'/'.$this->system->url['parts'][$this->system->url['parts_base_index']+1];
                    }

                    // $pathfile is the path where the php file has to be created
                    $pathfile = $this->system->app_path . "/api/{$apifile}.php";

                    if (!file_exists($pathfile)) {
                        $pathfile = '';
                        if (strlen($this->config->get('core.api.extra_path')))
                            $pathfile = $this->config->get('core.api.extra_path') . "/{$apifile}.php";
                    }
                }
                //endregion

                //region INCLUDE $pathfile AND SET $this-api AND EXECUTE $this->api->main(); $this->api->send();
                try {
                    // Load BASE CLASS for APIs
                    include_once __DIR__ . '/class/RESTful.php';

                    // Include the external file $pathfile
                    if (strlen($pathfile)) {
                        $this->__p->add('Loaded $pathfile', __METHOD__,'note');
                        @include_once $pathfile;
                        $this->__p->add('Loaded $pathfile', __METHOD__,'endnote');
                    }

                    // By default the ClassName will be called API.. if the include set $api_class var, we will use that class name
                    if(!isset($api_class)) $api_class = 'API';

                    if (class_exists($api_class)) {
                        /** @var RESTful $this->api */
                        $this->api = new $api_class($this,$this->system->url['parts_base_url']);
                        if (array_key_exists(0,$this->api->params) && $this->api->params[0] == '__codes') {
                            $__codes = $this->api->codeLib;
                            foreach ($__codes as $key => $value) {
                                $__codes[$key] = $this->api->codeLibError[$key] . ', ' . $value;
                            }
                            $this->api->addReturnData($__codes);
                        } else {
                            $this->api->main();
                        }

                        return $this->api->send();

                    }
                    else {
                        $this->api = new RESTful($this);
                        if(is_file($pathfile)) {
                            $this->api->setError("the code in '{$apifile}' does not include a {$api_class} class extended from RESTFul. Use: <?php class API extends RESTful { ... your code ... } ", 404);
                        } else {
                            $this->api->setError("the file for '{$apifile}' does not exist in api directory: ".$pathfile, 404);

                        }
                        return $this->api->send();
                    }
                }
                catch (Exception $e) {
                    // ERROR CONTROL WHERE $this->api is not an object
                    if(!is_object($this->api)) {
                        $this->api = new RESTful($this);
                        if(is_file($pathfile)) {
                            $this->api->setError("the code in '{$apifile}' does not include a {$api_class} class extended from RESTFul. Use: <?php class API extends RESTful { ... your code ... } ", 404);
                        } else {
                            $this->api->setError("the file for '{$apifile}' does not exist in api directory: ".$pathfile, 404);
                        }
                    }
                    // If $this->api is an object then an exception has been captured
                    else {
                        $this->api->setError("the code in '{$apifile}' has produced an exception ", 503);

                    }

                    $this->errors->add(error_get_last());
                    $this->errors->add($e->getMessage());
                    return $this->api->send();
                }
                //endregion

                //region IF we reach this code then there are ERRORS
                $this->__p->add("API including RESTfull.php and {$apifile}.php: ", 'There are ERRORS');
                return false;
                //endregion
            }
            // Take a LOOK in the menu
            elseif ($this->config->inMenuPath()) {

                // Common logic
                if (!empty($this->config->get('commonLogic'))) {
                    try {
                        include_once $this->system->app_path . '/logic/' . $this->config->get('commonLogic');
                        if (class_exists('CommonLogic')) {
                            $commonLogic = new CommonLogic($this);
                            $commonLogic->main();
                            $this->__p->add("Executed CommonLogic->main()", "/logic/{$this->config->get('commonLogic')}");

                        } else {
                            die($this->config->get('commonLogic').' does not include CommonLogic class');
                        }
                    } catch (Exception $e) {
                        $this->errors->add(error_get_last());
                        $this->errors->add($e->getMessage());
                        _print($this->errors->data);
                    }
                }

                // Specific logic
                if (!empty($this->config->get('logic'))) {
                    try {
                        include_once $this->system->app_path . '/logic/' . $this->config->get('logic');
                        if (class_exists('Logic')) {
                            $logic = new Logic($this);
                            $logic->main();
                            $this->__p->add("Executed Logic->main()", "/logic/{$this->config->get('logic')}");

                        } else {
                            $logic = new CoreLogic($this);
                            $logic->addError("api {$this->config->get('logic')} does not include a Logic class extended from CoreLogic with method ->main()", 404);
                        }

                    } catch (Exception $e) {
                        $this->errors->add(error_get_last());
                        $this->errors->add($e->getMessage());
                    }
                } else {
                    $logic = new CoreLogic($this);
                }
                // Templates
                if (!empty($this->config->get('template'))) {
                    $logic->render($this->config->get('template'));
                }
                // No template assigned.
                else {
                    // If there is no logic and no template, then ERROR
                    if(empty($this->config->get('logic'))) {
                        $this->errors->add('No logic neither template assigned');
                        _print($this->errors->data);
                    }
                }
            }
        }

        /**
         * Assign the path to Root of the app
         * @param $dir
         */
        public function setAppPath($dir)
        {
            if (is_dir($this->system->root_path . $dir)) {
                $this->system->app_path = $this->system->root_path . $dir;
                $this->system->app_url = $dir;
            } else {
                $this->errors->add($this->system->root_path . $dir . " doesn't exist. ".$this->system->root_path . $dir);
            }
        }

        /**
         * Is the current route part of the API?
         * @return bool
         */
        private function isApiPath() {
            //backward compatibility with core.api.urls
            if(!($paths = $this->config->get('core.api.routes')??$this->config->get('core.api.urls'))) return false;
            if(!is_array($paths)) $paths = explode(',',$paths);

            foreach ($paths as $path) {
                if(strpos($this->system->url['url']??'', $path) === 0) {
                    $path = preg_replace('/\/$/','',$path);
                    $this->system->url['parts_base_index'] = count(explode('/',$path))-1;
                    $this->system->url['parts_base_url'] = $path;
                    return true;
                }
            }
            return false;
        }

        /**
         * Load a class {$class}.php and instantiate it with given parameters if available.
         * It will try to load the class from different paths in the following order: the {FrameworkSrcPath}/class, {$this->system->app_path}/class, $this->config->get('core.api.extra_path')/class
         *
         * @param string $class The name of the class to load.
         * @param mixed $params Optional parameters to pass to the class constructor.
         *
         * @return object|null An instance of the loaded class if found, or null if the class is not found.
         */
        public function loadClass($class, $params = null)
        {

            $hash = hash('md5', $class . json_encode($params));
            if (key_exists($hash, $this->loadedClasses)) return $this->loadedClasses[$hash];

            if(!class_exists($class)) {
                $bucket_path = $this->config->get('core.api.extra_path');

                if (is_file(__DIR__ . "/class/{$class}.php"))
                    include_once(__DIR__ . "/class/{$class}.php");
                elseif (is_file($this->system->app_path . "/class/" . $class . ".php"))
                    include_once($this->system->app_path . "/class/" . $class . ".php");
                elseif ($bucket_path)
                    @include_once($bucket_path . "/class/" . $class . ".php");
            }

            if(!class_exists($class))
            {
                $error = "Class $class not found in the following paths: [";
                $error.= str_replace($this->system->root_path,'',__DIR__) . "/class/{$class}.php";
                $error.= ', '.str_replace($this->system->root_path,'',$this->system->app_path) . "/class/" . $class . ".php";
                if($bucket_path)
                    $error.= ', '.str_replace($this->system->root_path,'',$bucket_path) . "/class/" . $class . ".php";
                $error.= ']';
                $this->errors->add($error);
                return null;
            }

            $this->loadedClasses[$hash] = new $class($this, $params);
            return $this->loadedClasses[$hash];

        }

        /**
         * Init gc_datastorage_client and registerStreamWrapper
         */
        protected function initDataStorage() {

            //avoid to create several times
            if(is_object($this->gc_datastorage_client)) return;

            // if $this->config->get('core.datastorage.on') but !$this->gc_project_id then error
            if(!$this->gc_project_id && $this->config->get('core.datastorage.on')) {
                echo('Missing PROJECT_ID ENVIRONMENT VARIABLE TO REGISTER STREAM WRAPPER'."\n");
                if($this->is->terminal()) {
                    echo('export PROJECT_ID={YOUR-PROJECT-ID}'."\n");
                    exit;
                } else if($this->is->development()) {
                    echo('export PROJECT_ID={YOUR-PROJECT-ID}'."\n");
                    exit;
                }else  {
                    echo('add in app.yaml'."\nenv_variables:\n   PROJECT_ID: \"{YOUR-PROJECT-ID}\"");
                    exit;
                }
                $this->logs->add('Missing PROJECT_ID ENVIRONMENT VARIABLE TO REGISTER STREAM WRAPPER');
                $this->logs->add('export PROJECT_ID={YOUR-PROJECT-ID}');
                return;
            }

            // only setup datastorage if gc_project_id
            if($this->gc_project_id) {
                if(!isset($_GET['_no_register_stream_wrapper'])) {
                    try {
                        $this->gc_datastorage_client = new StorageClient(['projectId' => $this->gc_project_id]);
                        $this->gc_datastorage_client->registerStreamWrapper();
                    } catch (Exception $e) {
                        echo $e->getMessage();
                        if(strpos($e->getMessage(),'GOOGLE_APPLICATION_CREDENTIALS')) {
                            echo "\n\nYou can set in config.json the variable [core.gcp.credentials] with the path to the google credentials file";
                        }
                        exit;
                    }
                }
            }
        }

        /**
         * json_encode an $input with JSON_UNESCAPED_UNICODE options by default. if any error iadd it
         * @param $input
         * @param null $options
         * @return false|string
         */
        public function jsonEncode($input, $options=null)
        {
            if($options) $json = json_encode($input, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | $options);
            else $json = json_encode($input, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            if (function_exists('json_last_error') && $errno = json_last_error()) {
                $this->errors->add(['json_encode error',$errno],'jsonEncode');
            } elseif ($json === 'null' && $input !== null) {
                $this->errors->add('Null result with non-null input','jsonEncode');
            }
            return $json;
        }

        /**
         * json_decode a
         * @param $input
         * @return mixed
         */
        public function jsonDecode($input)
        {
            if (version_compare(PHP_VERSION, '5.4.0', '>=') && !(defined('JSON_C_VERSION') && PHP_INT_SIZE > 4)) {
                /** In PHP >=5.4.0, json_decode() accepts an options parameter, that allows you
                 * to specify that large ints (like Steam Transaction IDs) should be treated as
                 * strings, rather than the PHP default behaviour of converting them to floats.
                 */
                $arr = json_decode($input, true, 512, JSON_BIGINT_AS_STRING);
            } else {
                /** Not all servers will support that, however, so for older versions we must
                 * manually detect large ints in the JSON string and quote them (thus converting
                 *them to strings) before decoding, hence the preg_replace() call.
                 */
                $max_int_length = strlen((string) PHP_INT_MAX) - 1;
                $json_without_bigints = preg_replace('/:\s*(-?\d{'.$max_int_length.',})/', ': "$1"', $input);
                $arr = json_decode($json_without_bigints,true);
            }

            if (function_exists('json_last_error') && $errno = json_last_error()) {
                $this->errors->add(['json_encode error',$errno],'jsonDecode');
            } elseif ($arr === null && $input !== 'null') {
                $this->errors->add('Null result with non-null input','jsonDecode');
            }
            return $arr;
        }

        /**
         * Give support for deprecated utf8_encode
         * @param $value
         * @return string|null
         */
        public function utf8Encode(string  $value) {
            return (mb_convert_encoding($value, 'UTF-8','ISO-8859-1'));
        }

        /**
         * Give support for deprecated utf8_decode
         * @param $value
         * @return string|null
         */
        public function utf8Decode($value) {
            return ( mb_convert_encoding($value, 'ISO-8859-1', 'UTF-8'));
        }

        /**
         * Return an uuid string
         * @return string
         */
        public function uuid() {
            return sprintf( '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
                // 32 bits for "time_low"
                mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ),

                // 16 bits for "time_mid"
                mt_rand( 0, 0xffff ),

                // 16 bits for "time_hi_and_version",
                // four most significant bits holds version number 4
                mt_rand( 0, 0x0fff ) | 0x4000,

                // 16 bits, 8 bits for "clk_seq_hi_res",
                // 8 bits for "clk_seq_low",
                // two most significant bits holds zero and one for variant DCE1.1
                mt_rand( 0, 0x3fff ) | 0x8000,

                // 48 bits for "node"
                mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff )
            );
        }


        /**
         * Replace CloudFramework Tags {{Tag:{value of tag}}} or variable {{Variablename,defaultvalu} if $array_of_variables is sent. The Tags available are:
         * @param string|array|null $data Potential string to replace. The allowed values will be:
         *   * {{<SystemTag>:<TagVariable>[,defaultvalue]}} Allowed values:
         *   * Platform:(namespace)
         *   * User:(UserAuthUser.<variable>)
         *   * UserVariable:(UserAuthUser.JSON.UserVariables.<variable>)
         *   * GETVariable:<$_GET variable)
         *   * POSTVariable:<$_POST variable)
         *   * FORMVariable:<$_REQUEST variable)
         *   if $array_of_variables is sent {{<Variable>>[,defaultvalue]}}
         * @param array|null $array_of_variables
         * @param bool $rawUrlEncode Apply a URL encode to the substitutions
         * @return string|array|null
         */
        public function replaceCloudFrameworkTagsAndVariables($data, &$array_of_variables=[], bool $rawUrlEncode=false) {

            //region EVALUATE if $data is array to do a recursive call
            if(is_array($data)) {
                foreach ($data as $i => $datum) {
                    $data[$i] = $this->replaceCloudFrameworkTagsAndVariables($datum, $array_of_variables, $rawUrlEncode);
                }
                return $data;
            }
            //endregion


            //region EVALUATE apply multilang
            if(is_string($data) && substr_count($data,';')>1) {
                $data = $this->localization->getTag($data);
            }
            //endregion

            //region IF $text does not contains {{ xxxx }} the return localized $text
            if(!is_string($data) || !$data || strpos($data,'{{')===false || strpos($data,'}}')===false) {
                return ($data);
            }
            //endregion

            //region REPLACE System {{Platform,User,..:tag}} variables
            if(strpos($data,':')!==false) do {
                //region SET $found = false, $value = '',  $default_value=''
                $value = '';
                $default_value='';
                $found = null;
                preg_match("/{{([A-z0-9_]*):([A-z0-9_,\- ]*)}}/", $data, $found);
                //endregion

                //region SEARCH $found[1] in Platform,User,UserVariables,GETVariables and SET $value
                if($found) {

                    //region IF $found[2] contains a ',' the right string will be set to $default_value
                    if (strpos($found[2], ',')) {
                        list($found[2], $default_value) = explode(',', $found[2], 2);
                    }
                    //endregion

                    //region SWITH $found[1]
                    if(isset($this->platform[0])) $platform = &$this->platform[0];
                    else $platform = &$this->platform;
                    switch ($found[1]) {
                        case "Platform":
                            switch (trim($found[2])) {
                                case "Fingerprint":
                                case "fingerprint":
                                    $value = json_encode($this->system->getRequestFingerPrint());
                                    break;
                                case "namespace":
                                    $value = $this->namespace;
                                    break;
                                case "project_id":
                                    $value = $this->gc_project_id;
                                    break;
                                case "ip":
                                    $value = $this->system->ip;
                                    break;
                                case "host":
                                    $value = $this->system->url['host'];
                                    break;
                                case "url":
                                    $value = $this->system->url['url'];
                                    break;
                                case "year":
                                    $value = date('Y');
                                    break;
                                case "date":
                                    $value = date('Y-m-d');
                                    break;
                                case "datetime":
                                    $value = date('Y-m-d H:i:s');
                                    break;
                                case "uuid":
                                    $value = $this->uuid();
                                    break;
                                case "random_key":
                                    $value = password_hash(uniqid(), PASSWORD_DEFAULT);
                                    break;
                                default:
                                    $value = (is_array($platform['JSON']['PlatformVariables']??null) && key_exists($found[2],$platform['JSON']['PlatformVariables']))?$platform['JSON']['PlatformVariables'][$found[2]]:$default_value;
                                    break;
                            }
                            break;
                        case "User":
                            $value = (isset($this->user->data['User'][$found[2]])) ? $this->user->data['User'][$found[2]] : '';
                            if ($rawUrlEncode) $value = urlencode($value);
                            break;
                        case "UserHasPrivilege":
                            $value = (trim($found[2]) && $this->user->hasPrivilege(trim($found[2])))?'true':'false';
                            break;
                        case "UserHasOrganization":
                            $value = (trim($found[2]) && $this->user->hasOrganization(trim($found[2])))?'true':'false';
                            break;
                        case "UserVariables":
                        case "UserVariable":
                            $value = (isset($this->user->data['User']['UserVariables'][$found[2]])) ? $this->user->data['User']['UserVariables'][$found[2]] : $default_value;
                            if ($rawUrlEncode) $value = urlencode($value);
                            break;
                        case "GETVariables":
                        case "GETVariable":
                            $value = (isset($_GET[$found[2]])) ? $_GET[$found[2]] : $default_value;
                            if ($rawUrlEncode) $value = urlencode($value);
                            break;
                        case "POSTVariables":
                        case "POSTVariable":
                            $value = (isset($_POST[$found[2]])) ? $_POST[$found[2]] : $default_value;
                            if ($rawUrlEncode) $value = urlencode($value);
                            break;
                        case "FORMVariables":
                        case "FORMVariable":
                            $value = (isset($_REQUEST[$found[2]])) ? $_REQUEST[$found[2]] : $default_value;
                            if ($rawUrlEncode) $value = urlencode($value);
                            break;
                    }
                    //endregion

                    //region REPLACE str_replace($found[0], $value, $text) EVALUATING $default_value
                    if (!$value && !strlen($value??'') && strlen(trim($default_value))) $value = trim($default_value);
                    $data = str_replace($found[0], $value??'', $data);
                    //endregion

                }
                //endregion
            }  while($found);
            //endregion

            //region REPLACE {{xx}} variables
            if($array_of_variables) $this->utils->replaceVariables($array_of_variables,$data,$rawUrlEncode);
            //endregion
            //region APPLY $this->localization->getTag($data) to find final translations
            if(is_string($data) && $data && substr_count($data,';')>1) {
                $data = $this->localization->getTag($data);
                if($array_of_variables && strpos($data,'{{')!==false) {
                    $this->utils->replaceVariables($array_of_variables, $data, $rawUrlEncode);
                }

            }
            //endregion

            //region EVALUATE if the info is a valid json to decode
            if(is_string($data) && $data && json_validate($data)) {
                $data = $this->jsonDecode($data);
            }
            //endregion

            return $data;

        }
    }

    /**
     * $this->core->__p Class to track performance
     * @package Core.perfomance
     */
    class CorePerformance
    {
        var $data = [];
        var $deep = 0;
        var $spaces = "";
        var $lastnote = "";
        var $active = true; // turn to false to deactivate Performance lines to save Memory

        function __construct()
        {
            // Performance Vars
            $this->data['initMicrotime'] = microtime(true);
            $this->data['lastMicrotime'] = $this->data['initMicrotime'];
            $this->data['initMemory'] = memory_get_usage() / (1024 * 1024);
            $this->data['lastMemory'] = $this->data['initMemory'];
            $this->data['lastIndex'] = 1;
            $this->data['info'][] = 'File: ' . str_replace($_SERVER['DOCUMENT_ROOT'], '', __FILE__);
            $this->data['info'][] = 'Init Memory Usage: ' . number_format(round($this->data['initMemory'], 4), 4) . 'Mb';

        }

        /**
         * Add a performance entry with the given title.
         *
         * @param string $title The title of the log entry.
         * @param string $file Optional. File associated with the log entry.
         * @param string $type Optional. Type of log entry ('all' by default).
         *
         * @return void
         */
        public function add($title, $file = '', $type = 'all')
        {
            if(!$this->active) return;
            // Hidding full path (security)
            $file = str_replace($_SERVER['DOCUMENT_ROOT'], '', $file??'');


            if ($type == 'note') {
                for($this->spaces  = "",$i=0;$i<($this->deep);$i++) $this->spaces.="  ";
                $this->deep++;
                $line = "{$this->spaces}[$type";
                $this->lastnote = 'note';
                $this->data['lastMicrotime_'.$this->deep] = microtime(true);
            }
            else $line = $this->data['lastIndex'] . ' [';

            if (strlen($file)) $file = " ($file)";

            $_time = microtime(TRUE) - $this->data['lastMicrotime'];
            if ($type == 'all' || $type == 'endnote' || $type == 'time' || (isset($_GET['data']) && $_GET['data'] == $this->data['lastIndex'])) {
                if(isset($this->data['lastMicrotime_'.$this->deep]))
                    $_time = microtime(TRUE) - $this->data['lastMicrotime_'.$this->deep];

                $line .=  (round($_time, 4)) . ' secs';
                $this->data['lastMicrotime'] = microtime(TRUE);
            }

            $_mem = memory_get_usage() / (1024 * 1024) - $this->data['lastMemory'];
            if ($type == 'all' || $type == 'endnote' || $type == 'memory' || (isset($_GET['data']) && $_GET['data'] == $this->data['lastIndex'])) {
                $line .= (($line == '[') ? '' : ', ') . number_format(round($_mem, 3), 3) . ' Mb';
                $this->data['lastMemory'] = memory_get_usage() / (1024 * 1024);
            }
            $line .= '] ' . $title;

            $line = (($type != 'note') ? '['
                    . (round(microtime(TRUE) - $this->data['initMicrotime'], 4)) . ' secs, '
                    . number_format(round(memory_get_usage() / (1024 * 1024), 3), 3) . ' Mb] / '
                    : '') . $line . $file;

            if ($type == 'endnote') {
                if($this->lastnote=='note') $line = "{$this->spaces}[$type] " . $line;
                $this->deep--;
                for($this->spaces = "",$i=0;$i<($this->deep);$i++) $this->spaces.="  ";
                if($this->lastnote=='endnote') $line = "{$this->spaces}[$type] " . $line;
                $this->lastnote = 'endnote';
            }

            $this->data['info'][] = $line;

            if ($title) {
                if (!isset($this->data['titles'][$title])) $this->data['titles'][$title] = ['mem' => '', 'time' => 0, 'lastIndex' => ''];
                $this->data['titles'][$title]['mem'] = $_mem;
                $this->data['titles'][$title]['time'] += $_time;
                $this->data['titles'][$title]['lastIndex'] = $this->data['lastIndex'];

            }

            // since 8.5.26: the dump of the profiler at a given line (?__p=N) only outside a managed runtime. There is no
            // configuration yet at this point, so the IPs of CoreSecurity::debugOutputAllowed() can not open it
            if (isset($_GET['__p']) && $_GET['__p'] == $this->data['lastIndex'] && !CoreSecurity::managedRuntime()) {
                _printe($this->data);
                exit;
            }

            $this->data['lastIndex']++;

        }

        /**
         * Calculates and returns the total elapsed time in seconds since the initialization of the class instance.
         *
         * @param int $prec (Optional) The precision of the time calculation (default is 3).
         * @return float The total elapsed time in seconds with the specified precision.
         */
        public function getTotalTime($prec = 3)
        {
            return (round(microtime(TRUE) - $this->data['initMicrotime'], $prec));
        }

        /**
         * Calculates and retrieves the total memory usage in megabytes.
         *
         * @param int $prec (Optional) The precision of the returned memory value (default is 3).
         * @return float The total memory usage in megabytes.
         */
        public function getTotalMemory($prec = 3)
        {
            return number_format(round(memory_get_usage() / (1024 * 1024), $prec), $prec);
        }

        /**
         * Initializes a new data entry with memory usage, start time, and default status for a given space and key.
         *
         * @param string $spacename The name of the space.
         * @param string $key The key within the space.
         * @return void
         */
        public function init($spacename, $key)
        {
            if(!$this->active) return;
            $this->data['init'][$spacename][$key]['mem'] = memory_get_usage();
            $this->data['init'][$spacename][$key]['time'] = microtime(TRUE);
            $this->data['init'][$spacename][$key]['ok'] = TRUE;
        }

        /**
         * Updates the data entry with final metrics and status information for a given space and key.
         *
         * @param string $spacename The name of the space.
         * @param string $key The key within the space.
         * @param bool $ok (Optional) The status flag indicating if the operation was successful (default is TRUE).
         * @param mixed|bool $msg (Optional) Additional notes or messages related to the entry (default is FALSE).
         * @return void
         */
        public function end($spacename, $key, $ok = TRUE, $msg = FALSE)
        {
            if(!$this->active) return;
            // Verify indexes
            if(!isset($this->data['init'][$spacename][$key])) {
                $this->data['init'][$spacename][$key] = [];
            }

            $this->data['init'][$spacename][$key]['mem'] = round((memory_get_usage() - $this->data['init'][$spacename][$key]['mem']) / (1024 * 1024), 3) . ' Mb';
            $this->data['init'][$spacename][$key]['time'] = round(microtime(TRUE) - $this->data['init'][$spacename][$key]['time'], 3) . ' secs';
            $this->data['init'][$spacename][$key]['ok'] = $ok;
            if ($msg !== FALSE) $this->data['init'][$spacename][$key]['notes'] = $msg;
        }
    }

    /**
     * $this->core->utils Class to facilitate operations. Utility methods for handling arrays, strings, and various other data processing tasks.
     * This class provides a collection of static methods designed to simplify common data manipulation operations.
     * All methods in this class work directly on the provided references or return transformed results.
     *
     * @package Core.utils
     */
    class CoreUtils
    {

        /**
         * Replaces variable placeholders in a given string with their corresponding values from an array.
         * Placeholders should be enclosed in double curly braces, e.g., {{key}}. If a default value is provided
         * after a comma within the placeholder, it will be used if the specified key is not found in the array.
         * Optionally, the replacement values can be URL-encoded.
         *
         * @param array $array_of_variables The array containing key-value pairs to be used for replacements.
         *        Keys are matched to placeholders within the string.
         * @param string $data The string containing variable placeholders to be replaced. Passed by reference
         *        and modified directly.
         * @param bool $rawUrlEncode Whether to apply raw URL encoding to the replacement values. Defaults to false.
         * @return void This method does not return any value. The input string is modified in place.
         */
        public function replaceVariables(array &$array_of_variables, string &$data, bool $rawUrlEncode=false)
        {
            //region REPLACE $array_of_variables in $data
            if($array_of_variables) do {
                $found = null;
                $value = '';
                $default_value='';
                preg_match('/{{([^}]*)}}/',$data,$found);
                if($found ) {
                    //region IF $found[2] contains a ',' the right string will be set to $default_value
                    if (strpos($found[1], ',')) list($found[1], $default_value) = explode(',', $found[1], 2);
                    //endregion

                    //region SET $value to replace
                    $value = $this->findValueWithDotsInArray($array_of_variables,$found[1]);
                    // isset($array_of_variables[$found[1]]))?$array_of_variables[$found[1]]:'';
                    if (!$value && strlen(trim($default_value))>0) $value = trim($default_value);
                    if(is_array($value)) $value = json_encode($value);
                    if($rawUrlEncode) $value=urlencode($value);
                    //endregion

                    //region REPLACE $found[0] by $value in $text
                    $data = str_replace($found[0],$value,$data);
                    //endregion
                }
            } while ($found);
            //endregion
        }


        /**
         * Recursively retrieves a value from a multidimensional array using a dot-separated string to represent nested keys.
         * If the dot-separated key does not exist in the array, an empty string is returned.
         *
         * @param array $array The input array to search, passed by reference.
         * @param string $var A dot-separated string representing the key path within the array.
         * @return mixed The value found at the given key path, or an empty string if the key path does not exist.
         */
        public function findValueWithDotsInArray(&$array, $var) {
            if(!strpos($var,'.')) return $array[$var]??'';
            else {
                $parts = explode('.',$var,2);
                if(isset($array[$parts[0]])) return $this->findValueWithDotsInArray($array[$parts[0]],$parts[1]);
                else return '';
            }
        }

        /**
         * Deletes a specified column from a multidimensional array.
         * The operation modifies the input array directly.
         *
         * @param array $array The input array from which the column will be removed, passed by reference.
         * @param string $columns The keys of the column to be removed from each element of the array separated by ','
         * @return void
         */
        public function deleteColumnsFromArray(&$array, string $columns) {
            $columns = explode(',',$columns);
            foreach ($columns as $column)
                array_walk($array, function (&$v) use ($column) {
                    if(isset($v[$column])) unset($v[$column]);
                });
        }

        /**
         * Reorganizes an array to be indexed by a specific column value from each item in the array.
         * If a duplicate index is encountered, a unique suffix is appended to ensure key uniqueness.
         * If $addAsArray is true the array elements are aggregated in each index in an array
         *
         * @param array $array The input array to be reorganized, passed by reference.
         * @param string $column The column name used as the index key for the resulting array.
         * @param bool $addAsArray adds the elements as an array of elements
         * @return array The resulting array indexed by the specified column values.
         */
        public function convertArrayIndexedByColumn(&$array, string $column, bool $addAsArray=false): array
        {
            $result = [];
            $keyCounters = []; // specific tracker for collisions

            foreach ($array as &$item) {
                // 1. Safe retrieval of the key
                // We verify if the column exists, otherwise default to 'unknown'
                $rawValue = $item[$column] ?? 'unknown';

                // 2. Ensure the key is a valid array key (Int or String)
                // PHP 8.x requires strict key types.
                $index = (is_string($rawValue) || is_int($rawValue))
                    ? (string)$rawValue
                    : 'invalid_type';

                // 3. Assign by Reference
                // We assign the reference to prevent memory duplication of the item data.
                if($addAsArray) {
                    if(!isset($result[$index])) $result[$index] = [];
                    $result[$index][] = &$item;
                } else {

                    // 3.1. Collision Handling
                    // Instead of expensive uniqid(), we check if the key exists
                    // and use a deterministic counter.
                    if (array_key_exists($index, $result)) {
                        // Initialize counter for this specific key if not present
                        if (!isset($keyCounters[$index])) {
                            $keyCounters[$index] = 0;
                        }

                        $keyCounters[$index]++;
                        $index = sprintf('%s_%d', $index, $keyCounters[$index]);
                    }

                    $result[$index] = &$item;
                }
            }

            // Break the reference to the last element to prevent future accidental modification
            unset($item);

            return $result;
        }


        /**
         * Adds a prefix to all keys in the given array.
         *
         * @param array $array The input array whose keys will be prefixed, passed by reference.
         * @param string $prefix The string to prepend to each key in the array.
         * @return array A new array with prefixed keys.
         */
        public function addPrefixToArrayKeys(array $array, string $prefix): array
        {
            $result = [];
            foreach ($array as $key => $value) {
                $result[$prefix . $key] = $value;
            }
            return $result;
        }

    }

    /**
     * $this->core->is Class to answer is? questions.
     * @package Core.is
     */
    class CoreIs
    {
        /**
         * Check if the 'GAE_SERVICE' key exists in the $_SERVER superglobal array. It assumes your are on development env if not
         *
         * @return bool Returns true if 'GAE_SERVICE' key does not exist in $_SERVER, otherwise returns false.
         */
        public function development(): bool
        {
            return (!(array_key_exists('GAE_SERVICE',$_SERVER)));
        }

        /**
         * Check if the 'GAE_SERVICE' key exists in the $_SERVER superglobal array. It assumes you are on production environment if key exists
         *
         * @return bool Returns true if 'GAE_SERVICE' key exists in $_SERVER, indicating production environment. Otherwise, returns false.
         */
        public function production(): bool
        {
            return (array_key_exists('GAE_SERVICE',$_SERVER) );
        }

        /**
         * Check if the application is running in a local environment. It is local if GAE_SERVICE does not exists in superglobal $_SERVER array
         *
         * @return bool true if running in a local environment, false otherwise.
         */
        public function localEnvironment(): bool
        {
            return (!(array_key_exists('GAE_SERVICE',$_SERVER)));
        }

        /**
         * Checks if the application is running in a Google Cloud Platform environment.
         *
         * @return bool Returns true if the application is running in a Google Cloud Platform environment, false otherwise.
         */
        public function GCPEnvironment(): bool
        {
            return (array_key_exists('GAE_SERVICE',$_SERVER) );
        }

        /**
         * Check if the 'PWD' server variable is set and the 'GAE_SERVICE' server variable is not set.
         *
         * @return bool Returns true if 'PWD' is set and 'GAE_SERVICE' is not set, otherwise returns false.
         */
        public function script(): bool
        {
            return (isset($_SERVER['PWD']) && !isset($_SERVER['GAE_SERVICE']));
        }

        /**
         * Checks if the specified directory is readable.
         *
         * @param string $dir The path of the directory to check for readability.
         *
         * @return bool Returns true if the specified directory is readable, false otherwise.
         */
        public function dirReadable(string $dir): bool
        {
            if (strlen($dir)) return (is_dir($dir));
            return false;
        }

        /**
         * Checks if the application is running on a terminal environment.
         *
         * @return bool Returns true if the application is running on a terminal environment, false otherwise.
         */
        public function terminal(): bool
        {
            return !isset($_SERVER['SERVER_PORT']);
        }

        /**
         * Checks if the given directory is writable.
         *
         * @param string $dir The directory path to be checked for writability.
         * @return bool Returns true if the directory is writable, false otherwise.
         */
        public function dirWritable(string $dir): bool
        {
            if (strlen($dir)) {
                if (!$this->dirReadable($dir)) return false;
                try {
                    if (@mkdir($dir . '/__tmp__')) {
                        rmdir($dir . '/__tmp__');
                        return (true);
                    }
                } catch (Exception $e) {
                    return false;
                }
            }
            return false;
        }

        /**
         * Validates if the given email address is a valid format.
         *
         * @param string $email The email address to be validated.
         * @return bool Returns true if the email address is in a valid format, false otherwise.
         */
        public function validEmail(string $email): bool
        {
            return (filter_var($email, FILTER_VALIDATE_EMAIL));
        }

        /**
         * Checks if the given URL is a valid URL.
         *
         * @param string $url The URL to be validated.
         *
         * @return bool Returns true if the URL is valid, false otherwise.
         */
        public function validURL(string $url): bool
        {
            return (filter_var($url, FILTER_VALIDATE_URL));
        }
    }

    /**
     * $this->core->system Class to interact with with the System variables
     * @package Core.system
     */
    class CoreSystem
    {
        /**
         * Query parameters whose VALUE is never written to the logs (CFILog, getRequestFingerPrint()), since 8.5.16.
         * The config-var core.log.sensitive_params can only add names to this list: see getSensitiveParams().
         */
        const LOG_SENSITIVE_PARAMS = ['code','state','token','access_token','id_token','password','key','secret'];

        var $url, $app,$root_path, $app_path, $app_url,$script_path,$api_path;
        var $config = [];
        /** @var CoreConfig|null $core_config Assigned by Core7::__construct() right after creating $core->config (since 8.5.16) */
        var $core_config = null;
        var $ip, $user_agent, $os, $lang, $format, $time_zone;
        var $geo;

        /**
         * Constructs a new instance setting up server variables and URL components based on $_SERVER array.
         *
         * @param string $root_path The root path of the application (default is empty string)
         *
         * @return void
         */
        function __construct(string $root_path = '')
        {

            // region  $server_var from $_SERVER
            $server_var['HTTPS'] = (array_key_exists('HTTPS',$_SERVER))?$_SERVER['HTTPS']:'';
            $server_var['DOCUMENT_ROOT'] = (array_key_exists('DOCUMENT_ROOT',$_SERVER))?$_SERVER['DOCUMENT_ROOT']:'';
            $server_var['HTTP_HOST'] = (array_key_exists('HTTP_HOST',$_SERVER))?$_SERVER['HTTP_HOST']:'';
            $server_var['REQUEST_URI'] = (array_key_exists('REQUEST_URI',$_SERVER))?$_SERVER['REQUEST_URI']:'';
            $server_var['SCRIPT_NAME'] = (array_key_exists('SCRIPT_NAME',$_SERVER))?$_SERVER['SCRIPT_NAME']:'';
            $server_var['HTTP_USER_AGENT'] = (array_key_exists('HTTP_USER_AGENT',$_SERVER))?$_SERVER['HTTP_USER_AGENT']:'';
            $server_var['HTTP_ACCEPT_LANGUAGE'] = (array_key_exists('HTTP_ACCEPT_LANGUAGE',$_SERVER))?$_SERVER['HTTP_ACCEPT_LANGUAGE']:'';
            $server_var['HTTP_X_APPENGINE_COUNTRY'] = (array_key_exists('HTTP_X_APPENGINE_COUNTRY',$_SERVER))?$_SERVER['HTTP_X_APPENGINE_COUNTRY']:'';
            $server_var['HTTP_X_APPENGINE_CITY'] = (array_key_exists('HTTP_X_APPENGINE_CITY',$_SERVER))?$_SERVER['HTTP_X_APPENGINE_CITY']:'';
            $server_var['HTTP_X_APPENGINE_REGION'] = (array_key_exists('HTTP_X_APPENGINE_REGION',$_SERVER))?$_SERVER['HTTP_X_APPENGINE_REGION']:'';
            $server_var['HTTP_X_APPENGINE_CITYLATLONG'] = (array_key_exists('HTTP_X_APPENGINE_CITYLATLONG',$_SERVER))?$_SERVER['HTTP_X_APPENGINE_CITYLATLONG']:'';
            // endregion

            if (!strlen($root_path)) $root_path = (strlen($_SERVER['DOCUMENT_ROOT'])) ? $_SERVER['DOCUMENT_ROOT'] : $_SERVER['PWD'];

            $this->url['https'] = $server_var['HTTPS'];
            $this->url['protocol'] = ($server_var['HTTPS'] == 'on') ? 'https' : 'http';
            $this->url['host'] = $server_var['HTTP_HOST'];
            $this->url['url_uri'] = $server_var['REQUEST_URI'];

            $this->url['url'] = $server_var['REQUEST_URI'];
            $this->url['params'] = '';
            if (strpos($server_var['REQUEST_URI']??'', '?') !== false)
                list($this->url['url'], $this->url['params']) = explode('?', $server_var['REQUEST_URI'], 2);

            $this->url['host_base_url'] = (($server_var['HTTPS'] == 'on') ? 'https' : 'http') . '://' . $server_var['HTTP_HOST'];
            $this->url['host_url'] = (($server_var['HTTPS'] == 'on') ? 'https' : 'http') . '://' . $server_var['HTTP_HOST'] . $this->url['url'];
            $this->url['host_url_uri'] = (($server_var['HTTPS'] == 'on') ? 'https' : 'http') . '://' . $server_var['HTTP_HOST'] . $server_var['REQUEST_URI'];
            $this->url['script_name'] = $server_var['SCRIPT_NAME'];
            $this->url['parts'] = (isset($this->url['url']))?explode('/', substr($this->url['url'], 1)):[];
            $this->url['parts_base_index'] = 0;
            $this->url['parts_base_url'] = '/';

            // paths
            $this->root_path = $root_path;
            $this->app_path = $this->root_path;

            // Remote user:
            $this->ip = $this->getClientIP();
            $this->user_agent = $server_var['HTTP_USER_AGENT'];
            $this->os = $this->getOS();
            $this->lang = $server_var['HTTP_ACCEPT_LANGUAGE'];

            // About timeZone, Date & Number format
            if (isset($_SERVER['PWD']) && strlen($_SERVER['PWD'])) date_default_timezone_set('UTC'); // necessary for shell run
            $this->time_zone = array(date_default_timezone_get(), date('Y-m-d H:i:s'), date("P"), time());
            //date_default_timezone_set(($this->core->config->get('timeZone')) ? $this->core->config->get('timeZone') : 'Europe/Madrid');
            //$this->_timeZone = array(date_default_timezone_get(), date('Y-m-d h:i:s'), date("P"), time());
            $this->format['formatDate'] = "Y-m-d";
            $this->format['formatDateTime'] = "Y-m-d h:i:s";
            $this->format['formatDBDate'] = "Y-m-d";
            $this->format['formatDBDateTime'] = "Y-m-d h:i:s";
            $this->format['formatDecimalPoint'] = ",";
            $this->format['formatThousandSep'] = ".";

            // General conf
            // TODO default formats, currencies, timezones, etc..
            $this->config['setLanguageByPath'] = false;

            // GEO BASED ON GOOGLE APPENGINE VARS
            $this->geo['COUNTRY'] = $server_var['HTTP_X_APPENGINE_COUNTRY'];
            $this->geo['CITY'] = $server_var['HTTP_X_APPENGINE_CITY'];
            $this->geo['REGION'] = $server_var['HTTP_X_APPENGINE_REGION'];
            $this->geo['COORDINATES'] = $server_var['HTTP_X_APPENGINE_CITYLATLONG'];

            // Script path for terminal
            $this->script_path = $this->app_path.'/scripts';
            $this->api_path = $this->app_path.'/api';

        }

        /**
         * Sets the time zone to be used for date and time functions within the class.
         *
         * @param string $timezone The time zone identifier to set
         *
         * @return void
         */
        public function setTimeZone(string $timezone) {
            date_default_timezone_set($timezone);
            $this->time_zone = array(date_default_timezone_get(), date('Y-m-d H:i:s'), date("P"), time());
        }


        /**
         * Return the IP of the client as the platform gives it, never as the caller writes it. Since 8.5.28.
         *
         * Until 8.5.27 it returned X-Forwarded-For as it arrived. App Engine keeps what the caller writes in that header
         * and appends the real address at the end, so a caller could put any text (an IP of its choice, or free text)
         * in front of its IP in audit records, in the Bitacora, in the IP of a login and in the IP allow lists.
         * See platformClientIP() for the source in each runtime. X-Forwarded-For as declared by the caller is kept
         * apart, only with valid IPs, in the fingerprint (forwarded_for), and decides nothing.
         * https://cloud.google.com/appengine/docs/standard/php-gen2/runtime#https_and_forwarding_proxies
         * @return string a valid IP, 'localhost' outside a managed runtime when the peer is the local machine, or 'unknown'
         */
        public function getClientIP() {
            return self::platformClientIP();
        }

        /**
         * Since 8.5.28: the IP of the client from the source each runtime gives:
         *  - App Engine: the header X-Appengine-User-Ip, which App Engine sets and removes from the incoming request.
         *    Without it (it should not happen in a request from the internet), the last element of X-Forwarded-For.
         *  - Cloud Run and Cloud Functions: the last element of X-Forwarded-For, the one the Google front end appends.
         *  - Outside a managed runtime: REMOTE_ADDR; 'localhost' for the local machine or when there is none (a terminal).
         * The value is always a valid IP (or 'localhost' / 'unknown'): never the text the caller wrote.
         * CoreSecurity::trustedClientIP() is stricter on purpose (it gives '' in Cloud Run and Cloud Functions), because
         * it decides the debug output; this one is the IP that is recorded and compared with the allow lists.
         * @param array|null $server the $_SERVER array to read (null = $_SERVER); a parameter for the tests
         * @return string
         */
        public static function platformClientIP(?array $server = null): string
        {
            $server = $server ?? $_SERVER;
            $env = function (string $var) use ($server): bool { return array_key_exists($var, $server) || getenv($var) !== false; };
            $managed = $env('GAE_SERVICE') || $env('K_SERVICE') || $env('FUNCTION_TARGET');

            if(!$managed) {
                $ip = self::validIP($server['REMOTE_ADDR'] ?? '');
                return ($ip === '' || $ip === '127.0.0.1' || $ip === '::1') ? 'localhost' : $ip;
            }
            if($env('GAE_SERVICE') && ($ip = self::validIP($server['HTTP_X_APPENGINE_USER_IP'] ?? '')) !== '') return $ip;

            $forwarded = is_string($server['HTTP_X_FORWARDED_FOR'] ?? null) ? explode(',', $server['HTTP_X_FORWARDED_FOR']) : [];
            $ip = self::validIP((string)end($forwarded));
            return ($ip !== '') ? $ip : 'unknown';
        }

        /**
         * Since 8.5.28: the IPs of X-Forwarded-For as the caller (or a proxy of the caller) declared them, in order,
         * keeping only valid IPs and at most $max of them. For investigation only: it never decides anything.
         * @param array|null $server the $_SERVER array to read (null = $_SERVER)
         * @param int $max maximum number of IPs returned
         * @return string[]
         */
        public static function forwardedForIPs(?array $server = null, int $max = 10): array
        {
            $server = $server ?? $_SERVER;
            $header = $server['HTTP_X_FORWARDED_FOR'] ?? null;
            if(!is_string($header) || $header === '') return [];
            $ips = [];
            foreach (explode(',', $header) as $part) {
                if(($ip = self::validIP($part)) !== '') $ips[] = $ip;
                if(count($ips) >= $max) break;
            }
            return $ips;
        }

        /**
         * Since 8.5.28: whether this request is a task of a queue, the only case where the IP and the fingerprint of the
         * original call (cloudframework_queued_ip / cloudframework_queued_fingerprint) are read from the request.
         * In App Engine, the header X-AppEngine-QueueName, which App Engine removes from requests that come from the
         * internet. Outside a managed runtime always true (local development). In Cloud Run and Cloud Functions false:
         * there is no header there that the caller can not write.
         * @param array|null $server the $_SERVER array to read (null = $_SERVER)
         * @return bool
         */
        public static function isQueuedTask(?array $server = null): bool
        {
            $server = $server ?? $_SERVER;
            $env = function (string $var) use ($server): bool { return array_key_exists($var, $server) || getenv($var) !== false; };
            if(!($env('GAE_SERVICE') || $env('K_SERVICE') || $env('FUNCTION_TARGET'))) return true;
            return $env('GAE_SERVICE') && is_string($server['HTTP_X_APPENGINE_QUEUENAME'] ?? null) && $server['HTTP_X_APPENGINE_QUEUENAME'] !== '';
        }

        /**
         * Since 8.5.28: $value trimmed if it is a valid IPv4 or IPv6, '' otherwise.
         * @param mixed $value
         * @return string
         */
        public static function validIP($value): string
        {
            $value = is_string($value) ? trim($value) : '';
            return (filter_var($value, FILTER_VALIDATE_IP) !== false) ? $value : '';
        }

        /**
         * Get the OS platform based on the user agent in the $_SERVER array.
         * Returns the detected OS platform or the HTTP_USER_AGENT if not found.
         *
         * @return string The OS platform detected based on the user agent or HTTP_USER_AGENT if not found
         */
        public function getOS()
        {
            $os_platform = "Unknown OS Platform";
            $os_array = array(
                '/windows nt 6.2/i'     => 'Windows 8',
                '/windows nt 6.1/i'     => 'Windows 7',
                '/windows nt 6.0/i'     => 'Windows Vista',
                '/windows nt 5.2/i'     => 'Windows Server 2003/XP x64',
                '/windows nt 5.1/i'     => 'Windows XP',
                '/windows xp/i'         => 'Windows XP',
                '/windows nt 5.0/i'     => 'Windows 2000',
                '/windows me/i'         => 'Windows ME',
                '/win98/i'              => 'Windows 98',
                '/win95/i'              => 'Windows 95',
                '/win16/i'              => 'Windows 3.11',
                '/macintosh|mac os x/i' => 'Mac OS X',
                '/mac_powerpc/i'        => 'Mac OS 9',
                '/linux/i'              => 'Linux',
                '/ubuntu/i'             => 'Ubuntu',
                '/iphone/i'             => 'iPhone',
                '/ipod/i'               => 'iPod',
                '/ipad/i'               => 'iPad',
                '/android/i'            => 'Android',
                '/blackberry/i'         => 'BlackBerry',
                '/webos/i'              => 'Mobile'
            );
            foreach ($os_array as $regex => $value) {
                if (array_key_exists('HTTP_USER_AGENT',$_SERVER) && preg_match($regex, $_SERVER['HTTP_USER_AGENT'])) {
                    $os_platform = $value;
                }
            }
            return ($os_platform)?:$_SERVER['HTTP_USER_AGENT'];
        }

        /**
         * Redirects to a new URL if specified conditions are met.
         *
         * @param string $url The URL to redirect to
         * @param string $dest The destination URL (default is empty string)
         *
         * @return void
         */
        public function urlRedirect(string $url, string $dest = ''): void
        {
            if (!strlen($dest)) {
                if ($url != $this->url['url']) {
                    Header("Location: $url");
                    exit;
                }
            } else if ($url == $this->url['url'] && $url != $dest) {
                if (strlen($this->url['params'])) {
                    if (strpos($dest, '?') === false)
                        $dest .= "?" . $this->url['params'];
                    else
                        $dest .= "&" . $this->url['params'];
                }
                Header("Location: $dest");
                exit;
            }
        }

        /**
         * Retrieves the fingerprint data based on various server variables.
         *
         * Since 8.5.16 'uri' and 'http_referer' are returned with the values of the sensitive query parameters masked
         * (see maskUrl()), also when the fingerprint comes from a queue. The 'hash' does not use them and does not change.
         * Since 8.5.17 a queued fingerprint ($_REQUEST['cloudframework_queued_fingerprint']) that is not a JSON object is
         * ignored and the fingerprint of the current request is returned: before, it was returned as decoded (null, a
         * string or a number) and the return type made it a TypeError that any client could trigger.
         *
         * @return array The fingerprint data consisting of user agent, host, software, hash, IP address, city, country, region, latitude and longitude, HTTP referer, timestamp, and URI.
         */
        public function getRequestFingerPrint(): array
        {
            // Return the fingerprint coming from a queue. Since 8.5.28 only in a real task of a queue (isQueuedTask()):
            // before, any request could send it and choose the IP and the user agent written in the logs.
            if (isset($_REQUEST['cloudframework_queued_fingerprint']) && self::isQueuedTask()) {
                $queued = $_REQUEST['cloudframework_queued_fingerprint'];
                $ret = is_string($queued) ? json_decode($queued, true) : null;
                if (is_array($ret)) return $this->maskFingerPrint($ret);
                // Not a JSON object (or not a string): ignored, the fingerprint of the current request is computed below (since 8.5.17)
                $ret = [];
            }

            $ret['user_agent'] = (isset($_SERVER['HTTP_USER_AGENT']))?$_SERVER['HTTP_USER_AGENT']:'unknown';
            $ret['host'] = (isset($_SERVER['HTTP_HOST']))?$_SERVER['HTTP_HOST']:null;
            $ret['software'] = (isset($_SERVER['GAE_RUNTIME']))?('GAE_RUN_TIME:'.$_SERVER['GAE_RUNTIME'].'/'.$_SERVER['GAE_VERSION']):((isset($_SERVER['SERVER_SOFTWARE']))?$_SERVER['SERVER_SOFTWARE']:'Unknown');


            $ret['hash'] = sha1(implode(",", $ret));
            $ret['ip'] = $this->ip;
            // Since 8.5.28: X-Forwarded-For as the caller declared it (valid IPs only), when it says something else than ip.
            // For investigation (proxies, VPNs): it decides nothing.
            $forwarded = self::forwardedForIPs();
            if($forwarded && $forwarded !== [$this->ip]) $ret['forwarded_for'] = implode(', ', $forwarded);

            if(isset($_SERVER['HTTP_X_APPENGINE_CITY'])) $ret['getData']['city'] = $_SERVER['HTTP_X_APPENGINE_CITY'];
            if(isset($_SERVER['HTTP_X_APPENGINE_COUNTRY'])) $ret['getData']['country'] = $_SERVER['HTTP_X_APPENGINE_COUNTRY'];
            if(isset($_SERVER['HTTP_X_APPENGINE_REGION'])) $ret['getData']['region'] = $_SERVER['HTTP_X_APPENGINE_REGION'];
            if(isset($_SERVER['HTTP_X_APPENGINE_CITYLATLONG'])) $ret['getData']['latlong'] = $_SERVER['HTTP_X_APPENGINE_CITYLATLONG'];

            $ret['http_referer'] = (array_key_exists('HTTP_REFERER',$_SERVER))?$this->maskUrl((string)$_SERVER['HTTP_REFERER']):'unknown';
            $ret['time'] = date('Ymdhise');
            $ret['uri'] = (isset($_SERVER['REQUEST_URI']))?$this->maskUrl((string)$_SERVER['REQUEST_URI']):null;

            return ($ret);
        }

        //region LOG MASKING of sensitive query parameters (since 8.5.16)
        /**
         * Names of the query parameters whose value must not reach the logs: the LOG_SENSITIVE_PARAMS defaults plus the
         * names of the config-var core.log.sensitive_params (an array or a comma-separated string). The config-var only
         * ADDS names: the defaults can not be removed. It is read on every call because the config can change at runtime.
         *
         * @return array Normalized names (see normalizeParamName()), without duplicates
         */
        public function getSensitiveParams(): array
        {
            $names = self::LOG_SENSITIVE_PARAMS;
            $extra = (is_object($this->core_config)) ? $this->core_config->get('core.log.sensitive_params') : null;
            if(is_string($extra)) $extra = explode(',', $extra);
            if(is_array($extra)) foreach ($extra as $name) if(is_string($name)) $names[] = $name;

            $ret = [];
            foreach ($names as $name) {
                $name = self::normalizeParamName($name);
                if(strlen($name) && !in_array($name, $ret, true)) $ret[] = $name;
            }
            return $ret;
        }

        /**
         * Returns $url with the value of every sensitive query parameter (see getSensitiveParams()) replaced by '***'.
         * Example: '/sso?code=abc&state=xyz&scope=email' => '/sso?code=***&state=***&scope=email'
         *
         * @param string|null $url A REQUEST_URI, a referer or any absolute URL
         * @return string|null
         */
        public function maskUrl(?string $url): ?string
        {
            return self::maskSensitiveParamsInUrl($url, $this->getSensitiveParams());
        }

        /**
         * Masks the URLs of a fingerprint array: 'uri' and 'http_referer', and recursively the same keys of
         * 'local_fingerprint'. Any other key is returned untouched. Values that are not strings are not modified.
         *
         * @param array $fingerprint A fingerprint as returned by getRequestFingerPrint() or received from a caller
         * @return array
         */
        public function maskFingerPrint(array $fingerprint): array
        {
            foreach (['uri','http_referer'] as $key)
                if(isset($fingerprint[$key]) && is_string($fingerprint[$key])) $fingerprint[$key] = $this->maskUrl($fingerprint[$key]);
            if(isset($fingerprint['local_fingerprint']) && is_array($fingerprint['local_fingerprint']))
                $fingerprint['local_fingerprint'] = $this->maskFingerPrint($fingerprint['local_fingerprint']);
            return $fingerprint;
        }

        /**
         * Pure function: returns the same string with the VALUE of each parameter listed in $names replaced by $mask.
         *
         * - It applies to the query (after the first '?') and to the fragment ('#...') when the fragment has a query
         *   form: the part after a '?' inside the fragment ('#/route?a=1') or, without '?', the whole fragment when it
         *   contains '=' ('#access_token=...&token_type=bearer').
         * - The path, the parameter names as they arrived (not re-encoded), their order, the '&' separators and the
         *   values of the other parameters are kept byte by byte.
         * - Names are compared normalized like PHP does with $_GET: urldecode, trim, lower case, '.' and ' ' as '_' and
         *   without the array suffix ('code[]', 'code[0]' => 'code'). So 'postcode' or 'keyword' are not 'code' / 'key'.
         * - A parameter without '=' and a parameter with an empty value ('code=') are left as they are: there is no value to hide.
         * - null, '' or a URL without query nor fragment are returned unchanged. Masking twice gives the same result.
         *
         * @param string|null $url A REQUEST_URI ('/path?x=1') or an absolute URL ('https://host/path?x=1')
         * @param array $names Parameter names to mask
         * @param string $mask Replacement of the value
         * @return string|null
         */
        public static function maskSensitiveParamsInUrl(?string $url, array $names, string $mask = '***'): ?string
        {
            if($url === null || $url === '') return $url;

            $normalized = [];
            foreach ($names as $name) if(is_string($name) && strlen($name = self::normalizeParamName($name))) $normalized[$name] = true;
            if(!$normalized) return $url;

            //region SPLIT $main and $fragment
            $fragment = null;
            if(($pos = strpos($url, '#')) !== false) {
                $fragment = substr($url, $pos + 1);
                $url = substr($url, 0, $pos);
            }
            //endregion

            //region MASK the query of $main
            if(($pos = strpos($url, '?')) !== false)
                $url = substr($url, 0, $pos + 1) . self::maskQueryString(substr($url, $pos + 1), $normalized, $mask);
            //endregion

            //region MASK $fragment when it has a query form
            if($fragment !== null) {
                if(($pos = strpos($fragment, '?')) !== false)
                    $fragment = substr($fragment, 0, $pos + 1) . self::maskQueryString(substr($fragment, $pos + 1), $normalized, $mask);
                elseif(strpos($fragment, '=') !== false)
                    $fragment = self::maskQueryString($fragment, $normalized, $mask);
                $url .= '#' . $fragment;
            }
            //endregion

            return $url;
        }

        /**
         * Masks the values of the parameters of a raw query string ('a=1&b=2') whose normalized name is a key of $normalized
         * @ignore
         */
        private static function maskQueryString(string $query, array $normalized, string $mask): string
        {
            if($query === '') return $query;
            $parts = explode('&', $query);
            foreach ($parts as $i => $part) {
                if(($pos = strpos($part, '=')) === false) continue;     // parameter without '=': nothing to hide
                if($pos + 1 === strlen($part)) continue;                // empty value: nothing to hide
                $raw_name = substr($part, 0, $pos);
                if(isset($normalized[self::normalizeParamName($raw_name)])) $parts[$i] = $raw_name . '=' . $mask;
            }
            return implode('&', $parts);
        }

        /**
         * Normalizes a parameter name the way PHP builds $_GET keys: urldecode, trim, lower case, '.' and ' ' as '_',
         * and without the array suffix ('code[]', 'code[0]' => 'code').
         * @ignore
         */
        private static function normalizeParamName(string $name): string
        {
            $name = strtolower(trim(urldecode($name)));
            if(($pos = strpos($name, '[')) !== false && $pos > 0) $name = substr($name, 0, $pos);
            return str_replace(['.',' '], '_', trim($name));
        }
        //endregion

        /**
         * Gets the value of a specific header from the $_SERVER array.
         *
         * @param string $headerKey The key of the header to retrieve
         *
         * @return string The value of the specified header 'HTTP_'+$headerKey if found, otherwise an empty string
         */
        public function getHeader(string $headerKey): string
        {
            $headerKey = strtoupper($headerKey);
            $headerKey = str_replace('-', '_', $headerKey);
            return ((isset($_SERVER['HTTP_' . $headerKey])) ? $_SERVER['HTTP_' . $headerKey] : '');
        }

        /**
         * Retrieves and returns HTTP headers from the $_SERVER global variable.
         *
         * @return array An associative array containing HTTP headers without the 'HTTP_' prefix in the keys.
         */
        public function getHeaders(): array
        {
            $ret = array();
            foreach ($_SERVER as $key => $value) if (strpos($key, 'HTTP_') === 0) {
                $ret[str_replace('HTTP_', '', $key)] = $value;
            }
            return ($ret);
        }

    }

    /**
     * $this->core->logs, $this->core->errors Class to manage Logs & Errors
     * https://cloud.google.com/logging/docs/setup/php
     * $logger->info('This will show up as log level INFO');
     * $logger->warning('This will show up as log level WARNING');
     * $logger->error('This will show up as log level ERROR');
     * @package Core.log
     */
    class CoreLog
    {
        var $lines = 0;
        var $data = [];
        var $syslog_type = 'info';  //error, info, warning, notice, debug, critical, alert, emergency
        /** @var Logger|PsrLogger  */
        var $logger = null;
        var $is_terminal;
        var $active_lines = true;

        function __construct($is_terminal=false)
        {
            global $logger;
            $this->logger= &$logger;
            $this->is_terminal = $is_terminal;
        }

        /**
         * Reset the log and add an entry in the log.. if syslog_title is passed, also insert a LOG_DEBUG
         * @param $data
         * @param null|string $syslog_title
         * @param null|string $syslog_type
         * @return void
         */
        public function set($data,$syslog_title=null, $syslog_type=null): void
        {
            $this->lines = 0;
            $this->data = [];
            $this->add($data,$syslog_title, $syslog_type);
        }

        /**
         * Add an entry in the log.. if syslog_title is passed, also insert a LOG_DEBUG
         * @param $data
         * @param string $syslog_title
         * @param $syslog_type string|null values: error, info, warning, notice, debug, critical, alert, emergency
         * @return void
         */
        public function add($data, $syslog_title=null, $syslog_type=null): void
        {
            // Evaluate to write in syslog
            if(null !==  $syslog_title) {
                if(null==$syslog_type) $syslog_type = $this->syslog_type;
                $this->sendToSysLog($syslog_title.': '. json_encode($data,JSON_FORCE_OBJECT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),$syslog_type);
                //syslog($syslog_type, $syslog_title.': '. json_encode($data,JSON_FORCE_OBJECT));
                // Change the data sent to say that the info has been sent to syslog
                if(is_string($data))
                    $data = '[syslog:'.$syslog_type.'] '.$syslog_title.': '.$data;
                else
                    $data = ['[syslog:'.$syslog_type.'] '.$syslog_title=>$data];
            }
            // Store in local var.
            if($this->active_lines) {
                $this->data[] = $data;
                $this->lines++;
            }
        }

        /**
         * return the current data stored in the log
         * @return array
         */
        public function get(): array
        { return $this->data; }

        /**
         * Store all the data inside a syslog
         *
         * @param $data
         * @param $syslog_type string|null values: error, info, warning, notice, debug, critical, alert, emergency
         */
        public function sendToSysLog($data, $syslog_type=null) {
            if(!is_string($data)) $data = json_encode($data,JSON_FORCE_OBJECT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if(null==$syslog_type) $syslog_type = $this->syslog_type;
            // In development write the logs in a different way
            if(is_object($this->logger)) {
                switch ($syslog_type) {
                    case "error":
                        $this->logger->error($data);
                        break;
                    case "warning":
                        $this->logger->warning($data);
                        break;
                    case "notice":
                        $this->logger->notice($data);
                        break;
                    case "debug":
                        $this->logger->debug($data);
                        break;
                    case "critical":
                        $this->logger->critical($data);
                        break;
                    case "alert":
                        $this->logger->alert($data);
                        break;
                    case "emergency":
                        $this->logger->emergency($data);
                        break;
                    default:
                        $this->logger->info($data);
                        break;
                }
            } else {
                switch ($syslog_type) {
                    case "error":
                        syslog(LOG_ERR,$data);
                        break;
                    case "warning":
                        syslog(LOG_WARNING,$data);
                        break;
                    case "notice":
                        syslog(LOG_NOTICE,$data);
                        break;
                    case "debug":
                        syslog(LOG_DEBUG,$data);
                        break;
                    case "critical":
                        syslog(LOG_CRIT,$data);
                        break;
                    case "alert":
                        syslog(LOG_ALERT,$data);
                        break;
                    case "emergency":
                        syslog(LOG_EMERG,$data);
                        break;
                    default:
                        syslog(LOG_INFO,$data);
                        break;
                }
                if(!$this->is_terminal || !$this->active_lines)
                    file_put_contents("php://stderr", ' - [log:'.$syslog_type.']: '.$data."\n");
            }
        }

        /**
         * Reset the lines count and empty the data stored in the log
         *
         * @return void
         */
        public function reset()
        {
            $this->lines = 0;
            $this->data = [];
        }

    }

    /**
     * $this->core->config Class to manage CloudFramework configuration.
     * @package Core.config
     */
    class CoreConfig
    {
        private $core;
        private $_configPaths = [];
        var $data = [];
        var $menu = [];
        var $cache = null;
        // takes the secret_id  from: $this->core->config->get('core.gcp.secrets.env_vars') if it is not set from local_config
        var $cache_secret_key = ''; // openssl rand -base64 32
        var $cache_secret_iv = ''; // openssl rand -base64 24
        private $cache_keys_missing_logged = false; // security-cache-key-missing is logged once per request
        protected $lang = 'en';


        function __construct(Core7 &$core, $path)
        {
            $this->core = $core;

            // Read first json file
            $this->readConfigJSONFile($path);

            // Set lang for the system
            if (strlen($this->get('core.localization.default_lang'))) $this->setLang($this->get('core.localization.default_lang'));

            // core.localization.param_name allow to change the lang by URL
            if (strlen($this->get('core.localization.param_name'))) {
                $field = $this->get('core.localization.param_name');
                if (!empty($_GET[$field])) $this->core->session->set('_CloudFrameWorkLang_', $_GET[$field]);
                $lang = $this->core->session->get('_CloudFrameWorkLang_');
                if (strlen($lang))
                    if (!$this->setLang($lang)) {
                        $this->core->session->delete('_CloudFrameWorkLang_');
                    }
            }

            // Cache secrets to encrypt data in Cache.
            // Order: config-vars core.gcp.secrets.cache_encrypt_key / cache_encrypt_iv -> environment
            // variables CF_CACHE_ENCRYPT_KEY / CF_CACHE_ENCRYPT_IV (getenv() only: these keys protect the
            // cache of the env_vars secret, so they can not come from it). There is no default: if both
            // are empty (since 8.5.13) the cache of CoreConfig, env_vars included, is neither read nor written: see cacheKeysAvailable().
            $this->cache_secret_key=($this->get('core.gcp.secrets.cache_encrypt_key'))?:(getenv('CF_CACHE_ENCRYPT_KEY')?:'');
            $this->cache_secret_iv=($this->get('core.gcp.secrets.cache_encrypt_iv'))?:(getenv('CF_CACHE_ENCRYPT_IV')?:'');

            // Update $this->get('core.scripts.path') and $this->get('core.api.path')
            if($this->get('core.scripts.path')) $this->core->system->script_path = $this->get('core.scripts.path');
            if($this->get('core.api.path')) $this->core->system->api_path = $this->get('core.api.path');

        }

        /**
         * Return an array of files readed for config.
         * @return array
         */
        public function getConfigLoaded()
        {
            $ret = [];
            foreach ($this->_configPaths as $path => $foo) {
                $ret[] = str_replace($this->core->system->root_path, '', $path);
            }
            return $ret;
        }

        /**
         * Get the current lang
         * @return string
         */
        public function getLang()
        {
            return ($this->lang);
        }

        /**
         * Assign the language
         * @param $lang
         * @return bool
         */
        public function setLang($lang)
        {
            $lang = preg_replace('/[^a-z]/', '', strtolower($lang));
            // Control Lang
            if (strlen($lang = trim($lang)) < 2) {
                $this->core->logs->add('Warning config->setLang. Trying to pass an incorrect Lang: ' . $lang);
                return false;
            }
            if (strlen($this->get('core.localization.allowed_langs'))
                && !preg_match('/(^|,)' . $lang . '(,|$)/', preg_replace('/[^A-z,]/', '', $this->get('core.localization.allowed_langs')))
            ) {
                $this->core->logs->add('Warning in config->setLang. ' . $lang . ' is not included in {{core.localization.allowed_langs}}');
                return false;
            }

            $this->lang = $lang;
            return true;
        }

        /**
         * Get a config var value. $var is empty return the array with all values.
         * @param string $var  Config variable
         * @return mixed
         */
        public function get($var='')
        {
            if(strlen($var))
                return (key_exists($var, $this->data)) ? $this->data[$var] : '';
            else return $this->data;
        }

        /**
         * Set a config var
         * @param $var string
         * @param $data mixed
         */
        public function set($var, $data)
        {
            $this->data[$var] = $data;
        }

        /**
         * Set a config vars bases in an Array {"key":"value"}
         * @param $data Array
         */
        public function bulkSet(Array $data)
        {
            foreach ($data as $key=>$item) {
                $this->data[$key] = $item;
            }
        }
        /**
         * Add a menu line
         * @param $var
         */
        public function pushMenu($var)
        {
            if (!key_exists('menupath', $this->data)) {
                $this->menu[] = $var;
                if (!isset($var['path'])) {
                    $this->core->logs->add('Missing path in menu line');
                    $this->core->logs->add($var);
                } else {
                    // Trying to match the URLs
                    if (strpos($var['path'], "{*}"))
                        $_found = strpos($this->core->system->url['url'], str_replace("{*}", '', $var['path'])) === 0;
                    else
                        $_found = $this->core->system->url['url'] == $var['path'];

                    if ($_found) {
                        $this->set('menupath', $var['path']);
                        foreach ($var as $key => $value) {
                            $value = $this->convertTags($value);
                            $this->set($key, $value);
                        }
                    }
                }
            }
        }

        /**
         * Determine if the current URL is part of the menupath
         * @return bool
         */
        public function inMenuPath()
        {
            return key_exists('menupath', $this->data);
        }

        /**
         * Try to read a JSON file to process it as a corfig file
         * @param $path string
         * @return bool
         */
        public function readConfigJSONFile($path)
        {
            // Avoid recursive load JSON files
            if (isset($this->_configPaths[$path])) {
                $this->core->errors->add("Recursive config file: " . $path);
                return false;
            }
            $this->_configPaths[$path] = 1; // Control witch config paths are beeing loaded.
            try {
                $data = json_decode(@file_get_contents($path), true);
                if (!is_array($data)) {
                    $this->core->errors->add('error reading ' . $path);
                    if (json_last_error())
                        $this->core->errors->add("Wrong format of json: " . $path);
                    elseif (!empty(error_get_last()))
                        $this->core->errors->add(error_get_last());
                    return false;
                } else {
                    $this->processConfigData($data);
                    return true;
                }
            } catch (Exception $e) {
                $this->core->errors->add(error_get_last());
                $this->core->errors->add($e->getMessage());
                return false;
            }
        }

        /**
         * Process a config array
         * @param $data array
         */
        public function processConfigData(array $data)
        {
            // going through $data
            foreach ($data as $cond => $vars) {

                // Just a comment
                if ($cond == '--') continue;

                // Convert potentials Tags
                if (is_string($vars)) $vars = $this->convertTags($vars);
                $include = false;

                $tagcode = '';
                if (strpos($cond, ':') !== false) {
                    // Substitute tags for strings
                    $cond = $this->convertTags(trim($cond));
                    list($tagcode, $tagvalue) = explode(":", $cond, 2);
                    $tagcode = trim($tagcode);
                    $tagvalue = trim($tagvalue);
                    if($tagcode=='--') continue;

                    if ($this->isConditionalTag($tagcode))
                        $include = $this->getConditionalTagResult($tagcode, $tagvalue);
                    elseif ($this->isAssignationTag($tagcode)) {
                        $this->setAssignationTag($tagcode, $tagvalue, $vars);
                        continue;
                    } else {
                        $this->core->errors->add('unknown tag: |' . $tagcode . '|');
                        continue;
                    }

                } else {
                    $include = true;
                    $vars = [$cond => $vars];

                }

                // Include config vars.
                if ($include) {
                    if (is_array($vars)) {
                        foreach ($vars as $key => $value) {
                            if ($key == '--') continue; // comment
                            // Recursive call to analyze subelements
                            if (strpos($key, ':')) {
                                $this->processConfigData([$key => $value]);
                            } else {
                                // Assign conf var values converting {} tags
                                $this->set($key, $this->convertTags($value));
                            }
                        }
                    }
                }
            }

        }

        /**
         * Evalue if the tag is a condition
         * @param $tag
         * @return bool
         */
        private function isConditionalTag($tag)
        {
            $tags = ["uservar", "authvar", "confvar", "sessionvar", "servervar", "auth", "noauth", "development", "production","local"
                , "indomain", "domain", "host","interminal", "url", "noturl", "inurl", "notinurl", "beginurl", "notbeginurl"
                , "inmenupath", "notinmenupath", "isversion", "false", "true"];
            return in_array(strtolower($tag), $tags);
        }

        /**
         * Evalue conditional tags on config file
         * @param $tagcode string
         * @param $tagvalue string
         * @return bool
         */
        private function getConditionalTagResult($tagcode, $tagvalue)
        {
            $evaluateTags = [];
            while(strpos($tagvalue,'|')) {
                list($tagvalue,$tags) = explode('|',$tagvalue,2);
                $evaluateTags[] = [trim($tagcode),trim($tagvalue)];
                list($tagcode,$tagvalue) = explode(':',$tags,2);
            }
            $evaluateTags[] = [trim($tagcode),trim($tagvalue)];
            $ret = false;
            // Conditionals tags
            // -----------------
            foreach ($evaluateTags as $evaluateTag) {
                $tagcode = $evaluateTag[0];
                $tagvalue = $evaluateTag[1];
                switch (trim(strtolower($tagcode))) {
                    case "uservar":
                    case "authvar":
                        if (strpos($tagvalue, '=') !== false) {
                            list($authvar, $authvalue) = explode("=", $tagvalue);
                            if ($this->core->user->isAuth() && $this->core->user->getVar($authvar) == $authvalue)
                                $ret = true;
                        }
                        break;
                    case "confvar":
                        if (strpos($tagvalue, '=') !== false) {
                            list($confvar, $confvalue) = explode('=', $tagvalue,2);
                            if (strlen($confvar) && $this->get($confvar) == $confvalue)
                                $ret = true;
                        } elseif (strpos($tagvalue, '!=') !== false) {
                            list($confvar, $confvalue) = explode('!=', $tagvalue,2);
                            if (strlen($confvar) && $this->get($confvar) != $confvalue)
                                $ret = true;
                        }
                        break;
                    case "sessionvar":
                        if (strpos($tagvalue, '=') !== false) {
                            list($sessionvar, $sessionvalue) = explode("=", $tagvalue);
                            if (strlen($sessionvar) && $this->core->session->get($sessionvar) == $sessionvalue)
                                $ret = true;
                        }elseif (strpos($tagvalue, '!=') !== false) {
                            list($sessionvar, $sessionvalue) = explode("!=", $tagvalue);
                            if (strlen($sessionvar) && $this->core->session->get($sessionvar) != $sessionvalue)
                                $ret = true;
                        }
                        break;
                    case "servervar":
                        if (strpos($tagvalue, '=') !== false) {
                            list($servervar, $servervalue) = explode("=", $tagvalue);
                            if (strlen($servervar) && $_SERVER[$servervar] == $servervalue)
                                $ret = true;
                        }elseif (strpos($tagvalue, '!=') !== false) {
                            list($servervar, $servervalue) = explode("!=", $tagvalue);
                            if (strlen($servervar) && $_SERVER[$servervar] != $servervalue)
                                $ret = true;
                        }
                        break;
                    case "auth":
                    case "noauth":
                        if (trim(strtolower($tagcode)) == 'auth')
                            $ret = $this->core->user->isAuth();
                        else
                            $ret = !$this->core->user->isAuth();
                        break;
                    case "local":
                    case "development":
                        $ret = $this->core->is->localEnvironment();
                        break;
                    case "production":
                        $ret = $this->core->is->production();
                        break;
                    case "indomain":
                    case "domain":
                    case "host":
                        $domains = explode(",", $tagvalue);
                        foreach ($domains as $ind => $inddomain) if (strlen(trim($inddomain))) {
                            if (trim(strtolower($tagcode)) == "domain") {
                                if (strtolower($_SERVER['HTTP_HOST']??'') == strtolower(trim($inddomain)))
                                    $ret = true;
                            } else {
                                if (isset($_SERVER['HTTP_HOST']) && stripos($_SERVER['HTTP_HOST'], trim($inddomain)) !== false)
                                    $ret = true;
                            }
                        }
                        break;
                    case "interminal":
                        $ret = $this->core->is->terminal();
                        break;
                    case "url":
                    case "noturl":
                        $urls = explode(",", $tagvalue);
                        // If noturl the condition is upsidedown
                        if (trim(strtolower($tagcode)) == "noturl") $ret = true;
                        foreach ($urls as $ind => $url) if (strlen(trim($url))) {
                            if (trim(strtolower($tagcode)) == "url") {
                                if (($this->core->system->url['url'] == trim($url)))
                                    $ret = true;
                            } else {
                                if (($this->core->system->url['url'] == trim($url)))
                                    $ret = false;
                            }
                        }
                        break;
                    case "inurl":
                    case "notinurl":
                        $urls = explode(",", $tagvalue);

                        // If notinurl the condition is upsidedown
                        if (trim(strtolower($tagcode)) == "notinurl") $ret = true;
                        foreach ($urls as $ind => $inurl) if (strlen(trim($inurl))) {
                            if (trim(strtolower($tagcode)) == "inurl") {
                                if ((strpos($this->core->system->url['url'], trim($inurl)) !== false))
                                    $ret = true;
                            } else {
                                if ((strpos($this->core->system->url['url'], trim($inurl)) !== false))
                                    $ret = false;
                            }
                        }
                        break;
                    case "beginurl":
                    case "notbeginurl":
                        $urls = explode(",", $tagvalue);
                        // If notinurl the condition is upsidedown
                        if (trim(strtolower($tagcode)) == "notbeginurl") $ret = true;
                        foreach ($urls as $ind => $inurl) if (strlen(trim($inurl))) {
                            if (trim(strtolower($tagcode)) == "beginurl") {
                                if ((strpos($this->core->system->url['url'], trim($inurl)) === 0))
                                    $ret = true;
                            } else {
                                if ((strpos($this->core->system->url['url'], trim($inurl)) === 0))
                                    $ret = false;
                            }
                        }
                        break;
                    case "inmenupath":
                        $ret = $this->inMenuPath();
                        break;
                    case "notinmenupath":
                        $ret = !$this->inMenuPath();
                        break;
                    case "isversion":
                        if (trim(strtolower($tagvalue)) == 'core')
                            $ret = true;
                        break;
                    case "false":
                    case "true":
                        $ret = trim(strtolower($tagcode))=='true';
                        break;

                    default:
                        $this->core->errors->add('unknown tag: |' . $tagcode . '|');
                        break;
                }
                // If I have found a true, break foreach
                if($ret) break;
            }
            return $ret;
        }

        /**
         * Evalue if the tag is a condition
         * @param $tag
         * @return bool
         */
        private function isAssignationTag($tag)
        {
            $tags = ["webapp", "set", "include", "redirect", "menu","coreversion","env_vars"];
            return in_array(strtolower($tag), $tags);
        }

        /**
         * Execute an assignation based on the tagcode
         * @param $tagcode string
         * @param $tagvalue string
         * @return bool
         */
        private function setAssignationTag($tagcode, $tagvalue, $vars)
        {
            // Asignation tags
            // -----------------
            switch (trim(strtolower($tagcode))) {
                case "webapp":
                    $this->set("webapp", $vars);
                    $this->core->setAppPath($vars);
                    break;
                case "set":
                    $this->set($tagvalue, $vars);
                    break;
                case "include":
                    // Recursive Call
                    $this->readConfigJSONFile($vars);
                    break;
                case "redirect":
                    // Array of redirections
                    if (!$this->core->is->terminal()) {
                        if (is_array($vars)) {
                            foreach ($vars as $ind => $urls)
                                if (!is_array($urls)) {
                                    $this->core->errors->add('Wrong redirect format. It has to be an array of redirect elements: [{orig:dest},{..}..]');
                                } else {
                                    foreach ($urls as $urlOrig => $urlDest) {
                                        if ($urlOrig == '*' || !strlen($urlOrig))
                                            $this->core->system->urlRedirect($urlDest);
                                        else
                                            $this->core->system->urlRedirect($urlOrig, $urlDest);
                                    }
                                }

                        } else {
                            $this->core->system->urlRedirect($vars);
                        }
                    }
                    break;

                case "menu":
                    if (is_array($vars)) {
                        $vars = $this->convertTags($vars);
                        foreach ($vars as $key => $value) {
                            if (!empty($value['path']))
                                $this->pushMenu($value);
                            else {
                                $this->core->logs->add('wrong menu format. Missing path element');
                                $this->core->logs->add($value);
                            }

                        }
                    } else {
                        $this->core->errors->add("menu: tag does not contain an array");
                    }
                    break;
                case "coreversion":
                    if($this->core->_version!= $vars) {
                        die("config var 'CoreVersion' is '{$vars}' and the current cloudframework version is {$this->core->_version}. Please update the framework. composer.phar update");
                    }
                    break;
                case "env_vars":
                    if(!is_array($vars))  die("config var 'env_vars:' has to be an array");
                    if(!isset( $this->data['env_vars']) || !is_array( $this->data['env_vars']))  $this->data['env_vars'] = [];
                    $this->data['env_vars'] = array_merge($this->data['env_vars'],$vars);
                    break;
                default:
                    $this->core->errors->add('unknown tag: |' . $tagcode . '|');
                    break;
            }
        }

        /**
         * Convert tags inside a string or object
         * @param $data mixed
         * @return mixed|string
         */
        public function convertTags($data)
        {
            $_array = is_array($data);

            // Convert into string if we received an array
            if ($_array) $data = json_encode($data,JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            // Tags Conversions
            $data = str_replace('{{rootPath}}', $this->core->system->root_path, $data??'');
            $data = str_replace('{{appPath}}', $this->core->system->app_path, $data??'');
            $data = str_replace('{{lang}}', $this->lang, $data);
            while (strpos($data, '{{confVar:') !== false) {
                list($foo, $var) = explode("{{confVar:", $data??'', 2);
                list($var, $foo) = explode("}}", $var, 2);
                $data = str_replace('{{confVar:' . $var . '}}', $this->get(trim($var)), $data??'');
            }
            // Convert into array if we received an array
            if ($_array) $data = json_decode($data, true);
            return $data;
        }

        /**
         * Allow to read config env_vars taking it from GCP secrets.
         * The secret has to have a JSON structure like { "env_vars:" {"key1":"value1","key2":"value2"}}
         * the result is stored in $this->data['env_vars'].
         *
         * The result is cached.
         * The config variables needed are:
         *   - core.gcp.secrets.env_vars: name of the secret in GCP
         *   - core.gcp.secrets.cache_path: If the cache is not in memory specify the directory to store the secrets
         *   - core.gcp.secrets.cache_encrypt_key: To encryt the data in cache you can define a config-var key
         *   - core.gcp.secrets.cache_encrypt_iv: To encryt the data in cache you can define a config-var iv
         *   - @param string $cache_secret_key  optional secret key. If not empty it will encrypt the data en cache
         *   - @param string $cache_secret_iv  optional secret key. If not empty it will encrypt the data en cache         *
         * @param $gpc_secret_id string project id in gcp.secrets. if empty it will take $this->get('core.gcp.secrets.env_vars')
         * @param $gpc_secret_id string secret id in gcp.secrets. if empty it will take $this->get('core.gcp.secrets.env_vars')
         * @param $reload boolean false by default. if true, force to read it from gcp.secrets
         * @return boolean
         */
        public function readEnvVarsFromGCPSecrets($gpc_project_id = '',$gpc_secret_id = '', $reload=false) {

            //region CHECK $gpc_secret_id
            if(!$gpc_project_id) $gpc_project_id = ($this->get('core.gcp.secrets.project_id'))?:$this->core->gc_project_id;
            if(!$gpc_secret_id) $gpc_secret_id = $this->get('core.gcp.secrets.env_vars');
            if(!$gpc_project_id) return $this->core->logs->add('Missing $gpc_project_id and core.gcp.secrets.project_id config var','error_readEnvVarsFromGCPSecrets');
            if(!$gpc_secret_id) return $this->core->logs->add('Missing $secret_id and core.gcp.secrets.env_vars config var','error_readEnvVarsFromGCPSecrets');
            //endregion

            //region CHECK local_config.json if this.core.is.development to avoid read from secret_id
            if($this->core->is->development()
                && is_file($this->core->system->root_path.'/local_config.json')
                && isset($this->data['env_vars'] )) {
                $this->core->logs->add('readEnvVarsFromGCPSecrets avoided because local_config.json->["env_vars"] exists');
                return true;
            }
            //endregion


            //region INIT $this->data['env_vars'] and CHECK $this->core->gc_project_id, $this->core->gc_project_service
            if(!isset($this->data['env_vars']) || !is_array($this->data['env_vars']))
                $this->data['env_vars'] = [];

            if(!$this->core->gc_project_id) {
                $this->core->logs->add('Missing $this->core->gc_project_id','error_readEnvVarsFromGCPSecrets');
                return false;
            }

            if(!$this->core->gc_project_service) {
                $this->core->logs->add('Missing $this->core->gc_project_service','error_readEnvVarsFromGCPSecrets');
                return false;
            }
            //endregion

            // read data from cache and encrypt data
            $key ="{$gpc_project_id}_{$gpc_secret_id}_core.gcp.secrets.env_vars";
            $env_vars = $this->getCache($key);

            // for local development force reload if local_env_vars.json does not exist
            if($this->core->is->development() && !is_file($this->core->system->root_path.'/local_env_vars.json')) {
                $reload=true;
            }

            //region READ env_vars data from cache or from GPC Secret service
            if($reload || !$env_vars || !isset($env_vars['env_vars:']) || !$env_vars['env_vars:']) {

                // performance init
                $this->core->__p->add('GoogleSecrets', $gpc_secret_id, 'note');

                // INSTANCE OF GoogleSecrets init
                /** @var GoogleSecrets $gs */
                $gs = $this->core->loadClass('GoogleSecrets');
                if($gs->error) {
                    $this->core->logs->add($gs->errorMsg,'error_readEnvVarsFromGCPSecrets');
                    return false;
                }

                // READ secret FROM gcp.secrets
                $secrets = $gs->getSecret($this->get('core.gcp.secrets.env_vars'));
                if($gs->error) {
                    $this->core->logs->add($gs->errorMsg,'error_readEnvVarsFromGCPSecrets');
                    return false;
                }
                // performane end
                $this->core->__p->add('GoogleSecrets', '', 'endnote');
                $this->core->logs->add('GCP Secrets service used: core.gcp.secrets.env_vars='.$this->get('core.gcp.secrets.env_vars'));


                if(!$secrets) {
                    $this->core->logs->add('the secret is empty','error_readEnvVarsFromGCPSecrets');
                    return false;
                }

                $env_vars = json_decode($secrets,true);
                if(!$env_vars || !isset($env_vars['env_vars:']) || !$env_vars['env_vars:']) {
                    $this->core->logs->add('the secret does not have a right json structure','error_readEnvVarsFromGCPSecrets');
                    return false;
                }


                // For development in localhost
                if($this->core->is->development() && isset($env_vars['env_vars:'])) {
                    $local_envars = [];

                    // If file env_vars.json exists, read it to update with secrets
                    if(is_file($this->core->system->root_path.'/local_env_vars.json')) {
                        $f_env_vars = file_get_contents($this->core->system->root_path.'/local_env_vars.json');
                        $f_env_vars = json_decode($f_env_vars,true);
                        if(is_array($f_env_vars)) $local_envars+=$f_env_vars;
                    }

                    $local_envars['gpc.secrets.loaded.id:'] = $this->get('core.gcp.secrets.env_vars');
                    $local_envars['gpc.secrets.loaded.date:'] = date('Y-m-d H:i:s');
                    $local_envars['gpc.secrets.loaded.env_vars:'] = $env_vars['env_vars:'];
                    foreach ($env_vars['env_vars:'] as $key_local=>$val) {
                        $local_envars['gpc.secrets.loaded.env_vars:'][$key_local]='******';
                    }
                    file_put_contents($this->core->system->root_path.'/local_env_vars.json',json_encode($local_envars,JSON_PRETTY_PRINT));
                    $this->core->logs->add('local_env_vars.json created');
                }

                $this->updateCache($key,$env_vars);
                $this->core->logs->add('Core7.CoreConfig data cached');

            }
            //endregion


            //region Merge this->data['env_vars'] with env_vars['env_vars:']
            if($env_vars && isset($env_vars['env_vars:']) && $env_vars['env_vars:']) {
                $this->data['env_vars'] = array_merge($this->data['env_vars'],$env_vars['env_vars:']);
            }
            //endregion

            return true;

        }

        /**
         * Return an environment var from getenv($var) or from a GCP Secret
         * $this->get('core.gcp.secrets.env_vars') in the $this->get('core.gcp.secrets.project_id')
         * @param $var
         * @param string $gcp_project_id optional GCP projectId where the secrets are stored. By default this value is got from $this->get('core.gcp.secrets.project_id')
         * @param string $gcp_secret_id  optional GCP sercretId where env_vars are stored in JSON format. By default this value is got from $this->get('core.gcp.secrets.env_vars')
         * @return mixed|null if the env var $var exist it returns the contenct and it can be any type
         */
        public function getEnvVar($var,$gcp_project_id='',$gcp_secret_id='') {

            //region RETURN getenv($var) if it exist
            if(getenv($var)) return(getenv($var));
            //endregion

            //region ELSE RETURN $this->data['env_vars'][$var] if exists reading it from $this->readEnvVarsFromGCPSecrets
            if($gcp_project_id) $this->set('core.gcp.secrets.project_id',$gcp_project_id);
            if($gcp_secret_id) $this->set('core.gcp.secrets.env_vars',$gcp_secret_id);
            if(!isset($this->data['env_vars']) && $this->get('core.gcp.secrets.env_vars')) $this->readEnvVarsFromGCPSecrets($gcp_project_id,$gcp_secret_id);

            // Return $this->data['env_vars'][$var] if it exists
            if(isset($this->data['env_vars'][$var])) return $this->data['env_vars'][$var];
            //endregion

            //region ELSE RETURN null
            return null;
            //endregion

        }

        /**
         * Reset Cache of the module
         * @param string $cache_secret_key optional param to use a specific secret_key. Default value is $this->cache_secret_key
         * @param string $cache_secret_iv optional param to use a specific secret_iv. Default value is $this->cache_secret_iv
         */
        public function readCache(string $cache_secret_key='', string $cache_secret_iv='') {

            if(!$cache_secret_key) $cache_secret_key = $this->cache_secret_key;
            if(!$cache_secret_iv) $cache_secret_iv = $this->cache_secret_iv;
            if($this->cache === null) {
                $this->cache = ($this->cacheKeysAvailable($cache_secret_key,$cache_secret_iv))
                    ? $this->core->cache->get('Core7.CoreConfig', -1,'',$cache_secret_key,$cache_secret_iv)
                    : [];
                if(!$this->cache) $this->cache=[];
            }
        }

        /**
         * Tells whether the cache of CoreConfig can be used. It holds the env_vars secret, so without both cache keys it is
         * neither read nor written (never stored in plain text) and one error per request is left (security-cache-key-missing).
         * @param string $cache_secret_key
         * @param string $cache_secret_iv
         * @return bool
         */
        private function cacheKeysAvailable(string $cache_secret_key, string $cache_secret_iv): bool {
            if($cache_secret_key && $cache_secret_iv) return true;
            if(!$this->cache_keys_missing_logged) {
                $this->cache_keys_missing_logged = true;
                $this->core->logs->add('security-cache-key-missing: set core.gcp.secrets.cache_encrypt_key/cache_encrypt_iv or the env vars CF_CACHE_ENCRYPT_KEY/CF_CACHE_ENCRYPT_IV. The cache of CoreConfig (env_vars included) is neither read nor written','CoreConfig','error');
            }
            return false;
        }

        /**
         * Reset Cache of the module
         * @param string $cache_secret_key optional param to use a specific secret_key. Default value is $this->cache_secret_key
         * @param string $cache_secret_iv optional param to use a specific secret_iv. Default value is $this->cache_secret_iv
         */
        public function resetCache(string $cache_secret_key='',string $cache_secret_iv='') {
            $this->cache = [];
            if(!$cache_secret_key) $cache_secret_key = $this->cache_secret_key;
            if(!$cache_secret_iv) $cache_secret_iv = $this->cache_secret_iv;
            if($this->cacheKeysAvailable($cache_secret_key,$cache_secret_iv))
                $this->core->cache->set('Core7.CoreConfig',$this->cache,null,$cache_secret_key,$cache_secret_iv);
        }

        /**
         * Update Cache of the module
         * @param $var
         * @param $data
         * @param string $cache_secret_key optional param to use a specific secret_key. Default value is $this->cache_secret_key
         * @param string $cache_secret_iv optional param to use a specific secret_iv. Default value is $this->cache_secret_iv
         */
        public function updateCache($var,$data,string $cache_secret_key='',string $cache_secret_iv='') {

            if(!$cache_secret_key) $cache_secret_key = $this->cache_secret_key;
            if(!$cache_secret_iv) $cache_secret_iv = $this->cache_secret_iv;
            $this->readCache($cache_secret_key,$cache_secret_iv);
            $this->cache[$var] = $data;
            if($this->cacheKeysAvailable($cache_secret_key,$cache_secret_iv))
                $this->core->cache->set('Core7.CoreConfig',$this->cache,null,$cache_secret_key,$cache_secret_iv);
        }

        /**
         * Get var Cache of the module
         * @param string $key key to get from cache
         * @param string $cache_secret_key optional param to use a specific secret_key. Default value is $this->cache_secret_key
         * @param string $cache_secret_iv optional param to use a specific secret_iv. Default value is $this->cache_secret_iv
         */
        public function getCache(string $key,string $cache_secret_key='',string $cache_secret_iv='') {
            $this->readCache($cache_secret_key,$cache_secret_iv);
            if(isset($this->cache[$key])) return $this->cache[$key];
            else return null;
        }

    }

    /**
     * $this->core->session Class to manage session
     * @package Core.session
     */
    class CoreSession
    {
        /** @var bool $start says if the session has been started */
        var $start = false;
        /** @var string $id Id of the session */
        var $id = '';
        /** @var bool $debug if true the class will send to $core->logs the use of the methods*/
        var $debug = false;
        /** @var Core7 $core */
        var $core;

        /**
         * CoreSession constructor
         * @param Core7 $core Core7 class passed by reference
         * @param null $debug if true it will send to $core->logs the use of the methods
         */
        function __construct(Core7 &$core,$debug=null)
        {
            $this->core = $core;
            //region SET $this->>debug. Activate debug based on $debug or if I am in development (local environgmnet)
            if(null !== $debug) $this->debug = true === $debug;
            else if($this->core->is->development()) $this->debug = true;
            //endregion

            //region VERIFY the session is currently active
            if(session_status() == PHP_SESSION_ACTIVE) {
                $this->id = session_id();
                $this->start = true;
                if($this->core->is->development() && $this->core->is->terminal()) {
                    $this->core->logs->add("__construct. id [{$this->id}]",'CoreSession');
                }
            }
            //endregion

        }

        /**
         * init the session
         * @param string $id optional paramater to assign a session_id
         */
        function init($id = '')
        {
            // If they pass a session id I will use it.
            if (!empty($id)) {
                $this->id = '';
                $this->start = false;

                if(session_status() == PHP_SESSION_ACTIVE) {
                    if(session_id() != $id )  {
                        session_abort();
                        session_id($id);
                    }
                } else {
                    session_id($id);
                }
            }

            // Session start
            if(session_status() != PHP_SESSION_ACTIVE) {
                session_start();
            }

            // Let's keep the session id
            $this->id = session_id();

            // Initiated.
            $this->start = true;

            if($this->debug)
                $this->core->logs->add("init(). id [{$this->id}]",'CoreSession');

        }

        /**
         * get a variable from session
         * @param $var
         * @return mixed|null
         */
        function get($var)
        {
            if (!$this->start) $this->init();
            if (key_exists('CloudSessionVar_' . $var, $_SESSION)) {
                try {
                    $ret = unserialize(gzuncompress($_SESSION['CloudSessionVar_' . $var]));
                } catch (Exception $e) {
                    if($this->debug)
                        $this->core->logs->add("get('\$var=$var') Exception: ".$e->getMessage(),'CoreSession');
                    return null;
                }
                if($this->debug)
                    $this->core->logs->add("get('\$var=$var') found",'CoreSession');
                return $ret;
            }
            if($this->debug)
                $this->core->logs->add("get('\$var=$var') not-found",'CoreSession');

            return null;
        }

        /**
         * set a variable from session
         * @param $var
         * @param $value
         */
        function set($var, $value)
        {
            if (!$this->start) $this->init();
            $_SESSION['CloudSessionVar_' . $var] = gzcompress(serialize($value));
            if($this->debug)
                $this->core->logs->add("set('\$var=$var') ok",'CoreSession');
        }

        /**
         * delete a variable from session
         * @param $var
         */
        function delete($var)
        {
            if (!$this->start) $this->init();
            unset($_SESSION['CloudSessionVar_' . $var]);
            if($this->debug)
                $this->core->logs->add("delete('\$var=$var') ok",'CoreSession');
        }
    }

    /**
     * $this->core->security Class to manage the security access and dynamic getenv variables
     * @package Core.security
     */
    class CoreSecurity
    {
        private $core;
        /* @var $dsToken DataStore */
        var $dsToken = null;
        var $platform = null;
        var $error = false;
        var $errorMsg = [];
        var $cache = null;
        var $cache_key = null;
        var $cache_iv = null;
        var $secret_vars = null;
        var $last_key_cache = null;     // To control double read of: readERPDeveloperEncryptedSubKeys
        var $isDev=false;
        /** @var bool $cache_keys_missing true when $cache_key or $cache_iv is not configured (no config-var, no env var). The encrypted cache of CoreSecurity is then neither read nor written */
        var $cache_keys_missing = false;


        /** @var string $cache_key_base $cache_key before the daily subkey is appended (see readERPDeveloperEncryptedSubKeys) */
        private $cache_key_base = '';
        /** @var string $cache_iv_base $cache_iv before the daily subkey is appended (see readERPDeveloperEncryptedSubKeys) */
        private $cache_iv_base = '';
        /** @var array $encrypt_env_vars lazy memo of CF_ENCRYPT_KEY / CF_ENCRYPT_SECRET (and, since 8.5.25, CF_LEGACY_ENCRYPT_KEY / CF_LEGACY_ENCRYPT_SECRET) read with getEnvVar() */
        private $encrypt_env_vars = [];
        /** @var bool $resolving_encrypt_env_var re-entrance guard while getEnvVar() is running */
        private $resolving_encrypt_env_var = false;
        /** @var bool $encrypt_env_vars_reloaded the env_vars secret was already read again without the cache in this request */
        private $encrypt_env_vars_reloaded = false;
        /** @var array $notes_sent one-note-per-request control for the encryption notes/warnings */
        private $notes_sent = [];
        /** @var GoogleKMS|null $kms since 8.5.27: GoogleKMS of the v3 format, created the first time it is needed */
        private $kms = null;
        /** @var bool $cache_previous_resolved since 8.5.27: the previous (read-only) cache keys were already resolved in this request */
        private $cache_previous_resolved = false;
        /** @var array|null $cache_previous since 8.5.27: previous (read-only) cache keys ['key'=>string,'iv'=>string] or null */
        private $cache_previous = null;

        /** Since 8.5.27: prefix of the additional authenticated data (AAD) of the v3 values: 'cloudframework:<usage>' */
        public const KMS_AAD_PREFIX = 'cloudframework:';
        /** Since 8.5.27: minimum bcrypt cost of crypt() */
        public const PASSWORD_BCRYPT_MIN_COST = 12;

        /**
         * Since 8.5.23, injection points of useCacheKeysSecret(). They are static because CoreSecurity is built inside
         * Core7::__construct(): a test sets them BEFORE new Core7() and restores them afterwards. Production code never
         * sets them.
         *   - $cache_keys_secret_reader: callable(string $version_name): string|false that replaces the Secret Manager
         *     read (GoogleSecrets::getSecretByName()). null = the real reader. A thrown exception counts as a failed read.
         *   - $cache_keys_instance_dir: folder of the instance copy and of the failure mark when APCu is not used.
         *   - $cache_keys_use_apcu: null = APCu when extension_loaded('apcu') && apcu_enabled(); false = never APCu.
         */
        public static $cache_keys_secret_reader = null;
        /** @var string folder of the instance copy of the cache keys read from Secret Manager (see $cache_keys_secret_reader) */
        public static $cache_keys_instance_dir = '/tmp';
        /** @var bool|null use APCu for the instance copy (see $cache_keys_secret_reader) */
        public static $cache_keys_use_apcu = null;
        /** Seconds during which a failed Secret Manager read of the cache keys is not retried in the instance */
        public const CACHE_KEYS_SECRET_RETRY_SECONDS = 60;


        /**
         * CoreSecurity constructor.
         *
         * Keys used to encrypt the framework cache ($this->cache_key / $this->cache_iv). Order:
         *   1. config-vars core.gcp.secrets.cache_encrypt_key / core.gcp.secrets.cache_encrypt_iv
         *   2. environment variables CF_CACHE_ENCRYPT_KEY / CF_CACHE_ENCRYPT_IV, read ONLY with getenv():
         *      never with getEnvVar(), because these keys protect the cache of the env_vars secret itself.
         *   There is no built-in default since 8.5.13. Without both keys $cache_keys_missing is true: the encrypted cache of
         *   CoreSecurity is neither read nor written and one error per request is left (security-cache-key-missing).
         *
         * Keys used by encrypt()/decrypt() when they are called without explicit keys: see resolveEncryptKeys().
         *
         * @param Core7 $core
         */
        function __construct(Core7 &$core)
        {
            $this->core = $core;

            $this->cache_key = ($this->core->config->get('core.gcp.secrets.cache_encrypt_key'))?:(getenv('CF_CACHE_ENCRYPT_KEY')?:'');
            $this->cache_iv = ($this->core->config->get('core.gcp.secrets.cache_encrypt_iv'))?:(getenv('CF_CACHE_ENCRYPT_IV')?:'');
            $this->cache_keys_missing = (!$this->cache_key || !$this->cache_iv);

            $this->cache_key_base = $this->cache_key;
            $this->cache_iv_base = $this->cache_iv;

            $this->platform = $this->core->config->get('core.erp.platform_id')?:null;
        }

        /**
         * Tells whether the encrypted cache of CoreSecurity can be used. Without the cache keys it leaves one error per
         * request (security-cache-key-missing) and returns false: the cache is then neither read nor written, never
         * stored in plain text.
         * @return bool
         */
        private function cacheKeysAvailable(): bool
        {
            if(!$this->cache_keys_missing) return true;
            $this->sendNoteOnce('security-cache-key-missing'
                ,'security-cache-key-missing: set core.gcp.secrets.cache_encrypt_key/cache_encrypt_iv or the env vars CF_CACHE_ENCRYPT_KEY/CF_CACHE_ENCRYPT_IV. The encrypted cache of CoreSecurity is neither read nor written','error');
            return false;
        }

        /**
         * Sends a note to the logs only once per request (one CoreSecurity instance per request).
         * @param string $id identifier of the note
         * @param string $message message without sensitive data
         * @param string|null $syslog_type null: only in the request logs ($this->core->logs). 'warning': also in syslog
         * @return void
         */
        private function sendNoteOnce(string $id, string $message, ?string $syslog_type=null): void
        {
            if(isset($this->notes_sent[$id])) return;
            $this->notes_sent[$id] = true;
            if($syslog_type) $this->core->logs->add($message,'CoreSecurity',$syslog_type);
            else $this->core->logs->add('CoreSecurity: '.$message);
        }

        /**
         * Since 8.5.23: resolves the cache keys when neither the configuration nor the environment brings them. Called by
         * Core7::__construct() right after CoreCache is created; when it returns true Core7 copies the keys to CoreConfig.
         * Order:
         *   a. core.gcp.secrets.cache_keys_secret in a managed runtime: useCacheKeysSecret() (instance copy, then Secret
         *      Manager). When it applies, its result is final: a failure never falls back to the local file;
         *   b. otherwise useLocalCacheKeys() (8.5.22, local development only);
         *   c. otherwise the keys stay missing (security-cache-key-missing).
         * @return bool true when the cache keys have been set
         */
        public function resolveMissingCacheKeys(): bool
        {
            if(!$this->cache_keys_missing) return false;
            $from_secret = $this->useCacheKeysSecret();
            if($from_secret!==null) return $from_secret;
            return $this->useLocalCacheKeys();
        }

        /**
         * Since 8.5.23: cache keys read from a FIXED version of a Secret Manager secret, so that the applications can
         * drop core.gcp.secrets.cache_encrypt_key / cache_encrypt_iv from config.json.
         *
         * Config-var core.gcp.secrets.cache_keys_secret: full name of a concrete version,
         * projects/{project}/secrets/{secret}/versions/{number}. A name that ends in latest or in anything that is not a
         * number is never read (security-cache-keys-secret-version-required): the services that share the same Redis
         * (e.g. api7 and api8-dev) must use exactly the same keys, and with latest a new version would split them.
         * Content of the version: {"key": "<base64 of 32 bytes>", "iv": "<base64 of 32 bytes>"}, the same format and the
         * same validation as local_data/cache-encrypt.json (validateCacheKeysJson()).
         *
         * Only in a managed runtime (GAE_SERVICE, K_SERVICE or FUNCTION_TARGET, see isManagedRuntime()):
         *   1. the copy of the instance is used when it is valid, without calling Secret Manager: APCu when
         *      extension_loaded('apcu') && apcu_enabled(), otherwise {$cache_keys_instance_dir}/cf-cache-keys-{hash}.json
         *      (permissions 0600, hash = 16 hex chars of sha256 of the version name). These keys can not live in Redis
         *      like the env_vars secret, because they are the keys that encrypt Redis;
         *   2. without a valid copy (none, or corrupt) the version is read once with GoogleSecrets::getSecretByName() and
         *      the copy is written (APCu without expiration, or the file with the atomic write of placeCacheKeysFile();
         *      a corrupt copy is replaced);
         *   3. when the read fails or the content is not valid no copy is written, the failure is marked for
         *      CACHE_KEYS_SECRET_RETRY_SECONDS (APCu or {$cache_keys_instance_dir}/cf-cache-keys-{hash}.failed) so that the
         *      secret is not read on every request, and the keys stay missing (security-cache-keys-secret-unavailable).
         * One log line per request, never with any value: source=secret-manager-read (syslog info), source=instance-copy
         * (request log) or the error.
         *
         * @return bool|null true: keys set. false: it applies but the keys stay missing (no fallback). null: it does not
         *                   apply (no cache_keys_secret, or not a managed runtime): the caller goes on with the local file
         */
        private function useCacheKeysSecret(): ?bool
        {
            $configured = $this->core->config->get('core.gcp.secrets.cache_keys_secret');
            if(empty($configured)) return null;
            $version_name = (is_string($configured))?trim($configured):''; // anything that is not a string is not a version name
            // configuration and environment always win; a half pair is a configuration error and is not completed
            if($this->cache_key || $this->cache_iv) return false;

            $managed = $this->isManagedRuntime();
            if(!preg_match('#^projects/[^/]+/secrets/[^/]+/versions/[1-9][0-9]*$#',$version_name)) {
                // the configured value is never logged: it could be a key pasted in the wrong config-var
                $this->sendNoteOnce('security-cache-keys-secret'
                    ,'security-cache-keys-secret-version-required: core.gcp.secrets.cache_keys_secret must be the full name of a fixed version, projects/{project}/secrets/{secret}/versions/{number}, never latest: every service sharing the cache must use the same keys. The secret is not read'
                    ,'error');
                return ($managed)?false:null;
            }
            if(!$managed) return null;

            $hash = substr(hash('sha256',$version_name),0,16);
            $use_apcu = $this->cacheKeysUseApcu();
            $dir = rtrim((string)self::$cache_keys_instance_dir,'/')?:'/tmp';
            $copy_file = $dir.'/cf-cache-keys-'.$hash.'.json';
            $apcu_key = 'cf-cache-keys-'.$hash;

            //region 1. copy of the instance
            if($use_apcu) {
                $found = false;
                $stored = apcu_fetch($apcu_key,$found);
                $copy_exists = (bool)$found;
                $keys = ($found && is_string($stored))?$this->validateCacheKeysJson($stored):null;
            } else {
                $copy_exists = (file_exists($copy_file) || is_link($copy_file));
                $keys = ($copy_exists && !is_link($copy_file))?$this->readLocalCacheKeysFile($copy_file):null;
            }
            if($keys) {
                $this->setResolvedCacheKeys($keys);
                $this->sendNoteOnce('security-cache-keys-secret'
                    ,'security-cache-keys-secret-used: source=instance-copy; cache keys of '.$version_name.' read from the copy of the instance');
                return true;
            }
            //endregion

            //region 2. a recent failure is not retried
            if($this->cacheKeysSecretFailedRecently($use_apcu,$apcu_key.'-failed',$dir.'/cf-cache-keys-'.$hash.'.failed')) {
                $this->sendNoteOnce('security-cache-keys-secret'
                    ,'security-cache-keys-secret-unavailable: the read of '.$version_name.' failed less than '.self::CACHE_KEYS_SECRET_RETRY_SECONDS.' seconds ago in this instance and it is not retried yet. The cache keys stay missing'
                    ,'warning');
                return false;
            }
            //endregion

            //region 3. read the version from Secret Manager
            $reason = '';
            $content = $this->readCacheKeysSecret($version_name,$reason);
            $keys = (is_string($content))?$this->validateCacheKeysJson($content):null;
            $content = null;
            if(!$keys) {
                $this->markCacheKeysSecretFailed($use_apcu,$apcu_key.'-failed',$dir,$dir.'/cf-cache-keys-'.$hash.'.failed');
                $this->sendNoteOnce('security-cache-keys-secret'
                    ,'security-cache-keys-secret-unavailable: '.$version_name.' '.(($reason)?'could not be read ('.$reason.')':'does not hold a valid {"key","iv"} pair (base64 of 32 bytes each)')
                        .'. No copy is written, the read is not retried for '.self::CACHE_KEYS_SECRET_RETRY_SECONDS.' seconds and the cache keys stay missing'
                    ,'warning');
                return false;
            }
            //endregion

            //region 4. copy of the instance and keys
            $copy = json_encode(['key'=>$keys['key'],'iv'=>$keys['iv'],'version'=>$version_name,'read'=>gmdate('Y-m-d H:i:s').' UTC'],JSON_UNESCAPED_SLASHES);
            if($use_apcu) {
                $copied = (bool)apcu_store($apcu_key,$copy);
                apcu_delete($apcu_key.'-failed');
            } else {
                if($copy_exists) @unlink($copy_file); // a corrupt copy is replaced
                $copied = $this->placeCacheKeysFile($dir,$copy_file,$copy) || ($this->readLocalCacheKeysFile($copy_file)===$keys);
                if(file_exists($dir.'/cf-cache-keys-'.$hash.'.failed')) @unlink($dir.'/cf-cache-keys-'.$hash.'.failed');
            }
            $copy = null;
            $this->setResolvedCacheKeys($keys);
            $this->sendNoteOnce('security-cache-keys-secret'
                ,'security-cache-keys-secret-used: source=secret-manager-read; cache keys read from '.$version_name
                    .(($copy_exists)?'; the previous copy of the instance was not valid and it has been replaced':'')
                    .(($copied)?'':'; the copy of the instance could not be written, the secret will be read again on the next request')
                ,($copied)?'info':'warning');
            return true;
            //endregion
        }

        /**
         * Reads the content of a Secret Manager version for useCacheKeysSecret(): the injected reader
         * ($cache_keys_secret_reader) in tests, otherwise GoogleSecrets::getSecretByName() with its own instance (never the
         * one shared through Core7::loadClass(), so that a failure here does not leave the env_vars reader in error).
         * @param string $version_name projects/{project}/secrets/{secret}/versions/{number}
         * @param string $reason filled with the cause of a failed read, without any value
         * @return string|false
         */
        private function readCacheKeysSecret(string $version_name, string &$reason): string|false
        {
            $reason = '';
            try {
                if(is_callable(self::$cache_keys_secret_reader)) {
                    $content = call_user_func(self::$cache_keys_secret_reader,$version_name);
                } else {
                    if(!class_exists('GoogleSecrets')) include_once __DIR__.'/class/GoogleSecrets.php';
                    $gs = new GoogleSecrets($this->core);
                    $content = $gs->getSecretByName($version_name);
                    if($content===false) {
                        $last = end($gs->errorMsg);
                        $reason = $gs->errorCode.': '.((is_string($last))?$last:json_encode($last,JSON_UNESCAPED_SLASHES));
                    }
                }
            } catch (\Throwable $e) {
                $content = false;
                $reason = get_class($e).': '.$e->getMessage();
            }
            if(!is_string($content)) {
                $reason = substr(trim(preg_replace('/\s+/',' ',$reason)),0,300)?:'read-failed';
                return false;
            }
            return $content;
        }

        /**
         * True when a failed read of the cache keys secret was marked less than CACHE_KEYS_SECRET_RETRY_SECONDS ago.
         * @param bool $use_apcu
         * @param string $apcu_key
         * @param string $file
         * @return bool
         */
        private function cacheKeysSecretFailedRecently(bool $use_apcu, string $apcu_key, string $file): bool
        {
            if($use_apcu) {
                $found = false;
                $failed_at = apcu_fetch($apcu_key,$found);
            } else {
                if(!is_file($file)) return false;
                $failed_at = trim((string)@file_get_contents($file));
                $found = ctype_digit($failed_at);
            }
            if(!$found) return false;
            $age = time() - (int)$failed_at;
            return ($age>=0 && $age<self::CACHE_KEYS_SECRET_RETRY_SECONDS);
        }

        /**
         * Marks a failed read of the cache keys secret: APCu with a TTL of CACHE_KEYS_SECRET_RETRY_SECONDS, or a file
         * holding the time of the failure (permissions 0600, written through a temporary file and rename()).
         * @param bool $use_apcu
         * @param string $apcu_key
         * @param string $dir
         * @param string $file
         * @return void
         */
        private function markCacheKeysSecretFailed(bool $use_apcu, string $apcu_key, string $dir, string $file): void
        {
            if($use_apcu) {
                apcu_store($apcu_key,time(),self::CACHE_KEYS_SECRET_RETRY_SECONDS);
                return;
            }
            $tmp = $dir.'/.'.basename($file).'.'.bin2hex(random_bytes(8)).'.tmp';
            if(@file_put_contents($tmp,(string)time())===false) return;
            @chmod($tmp,0600);
            if(!@rename($tmp,$file)) @unlink($tmp);
        }

        /**
         * APCu is used for the copy of the instance when the extension is loaded and enabled, unless
         * $cache_keys_use_apcu is false.
         * @return bool
         */
        private function cacheKeysUseApcu(): bool
        {
            if(self::$cache_keys_use_apcu===false) return false;
            return extension_loaded('apcu') && function_exists('apcu_enabled') && apcu_enabled();
        }

        /**
         * Sets resolved cache keys exactly as useLocalCacheKeys() does.
         * @param array $keys ['key'=>string,'iv'=>string]
         * @return void
         */
        private function setResolvedCacheKeys(array $keys): void
        {
            $this->cache_key = $this->cache_key_base = $keys['key'];
            $this->cache_iv = $this->cache_iv_base = $keys['iv'];
            $this->cache_keys_missing = false;
        }

        /**
         * Since 8.5.27: the PREVIOUS cache keys, only to read, for a rotation without cuts. CoreCache::get() calls it when
         * an entry can not be read with the keys it was given; when those keys are the current cache keys of this class
         * (the base pair, or the base pair with the daily subkeys appended by readERPDeveloperEncryptedSubKeys()) it
         * returns the same pair built on the previous keys, otherwise null. Keys that an application passes on its own
         * are never mapped. set() always writes with the current keys.
         * @param string $key the key given to CoreCache::get()
         * @param string $iv the iv given to CoreCache::get()
         * @return array|null [previous key, previous iv]
         */
        public function previousCacheKeysFor(string $key, string $iv): ?array
        {
            if($this->cache_keys_missing || $this->cache_key_base==='' || $this->cache_iv_base==='') return null;
            if($key===$this->cache_key_base && $iv===$this->cache_iv_base) {
                $key_suffix = ''; $iv_suffix = '';
            } elseif(strncmp($key,$this->cache_key_base.'__',strlen($this->cache_key_base)+2)===0
                && strncmp($iv,$this->cache_iv_base.'__',strlen($this->cache_iv_base)+2)===0) {
                $key_suffix = substr($key,strlen($this->cache_key_base));
                $iv_suffix = substr($iv,strlen($this->cache_iv_base));
            } else return null;

            $previous = $this->previousCacheKeys();
            if(!$previous) return null;
            return [$previous['key'].$key_suffix,$previous['iv'].$iv_suffix];
        }

        /**
         * Since 8.5.27: resolves the previous cache keys once per request, only when an entry could not be read. Order:
         *   1. config-vars core.gcp.secrets.cache_encrypt_key_previous / cache_encrypt_iv_previous, then the env vars
         *      CF_CACHE_ENCRYPT_KEY_PREVIOUS / CF_CACHE_ENCRYPT_IV_PREVIOUS (getenv() only, like the current ones).
         *      A half pair is never completed (security-cache-previous-key-incomplete);
         *   2. config-var core.gcp.secrets.cache_keys_secret_previous: a FIXED version of Secret Manager, with the same
         *      rules as core.gcp.secrets.cache_keys_secret (managed runtime only, copy of the instance, failure not
         *      retried for CACHE_KEYS_SECRET_RETRY_SECONDS), see readPreviousCacheKeysSecret().
         * A previous pair equal to the current one is ignored.
         * @return array|null ['key'=>string,'iv'=>string]
         */
        private function previousCacheKeys(): ?array
        {
            if($this->cache_previous_resolved) return $this->cache_previous;
            $this->cache_previous_resolved = true;

            $key = ($this->core->config->get('core.gcp.secrets.cache_encrypt_key_previous'))?:(getenv('CF_CACHE_ENCRYPT_KEY_PREVIOUS')?:'');
            $iv = ($this->core->config->get('core.gcp.secrets.cache_encrypt_iv_previous'))?:(getenv('CF_CACHE_ENCRYPT_IV_PREVIOUS')?:'');
            if($key || $iv) {
                if(!$key || !$iv || !is_string($key) || !is_string($iv)) {
                    $this->sendNoteOnce('security-cache-previous-key'
                        ,'security-cache-previous-key-incomplete: set both core.gcp.secrets.cache_encrypt_key_previous/cache_encrypt_iv_previous (or CF_CACHE_ENCRYPT_KEY_PREVIOUS/CF_CACHE_ENCRYPT_IV_PREVIOUS). The previous cache keys are not used'
                        ,'warning');
                    return null;
                }
                $previous = ['key'=>$key,'iv'=>$iv];
            } else {
                $previous = $this->readPreviousCacheKeysSecret();
                if(!$previous) return null;
            }
            if($previous['key']===$this->cache_key_base && $previous['iv']===$this->cache_iv_base) return null;
            return $this->cache_previous = $previous;
        }

        /**
         * Since 8.5.27: previous cache keys from the fixed Secret Manager version of
         * core.gcp.secrets.cache_keys_secret_previous. Same rules as useCacheKeysSecret(): only a version number
         * (security-cache-keys-secret-version-required), only in a managed runtime, the copy of the instance first
         * (APCu or {$cache_keys_instance_dir}/cf-cache-keys-{hash}.json, the hash of this version name), a read of
         * Secret Manager without a valid copy, and a failure not retried for CACHE_KEYS_SECRET_RETRY_SECONDS. One note per
         * request (security-cache-keys-secret-previous), never with a value.
         * @return array|null ['key'=>string,'iv'=>string]
         */
        private function readPreviousCacheKeysSecret(): ?array
        {
            $configured = $this->core->config->get('core.gcp.secrets.cache_keys_secret_previous');
            if(empty($configured)) return null;
            $version_name = (is_string($configured))?trim($configured):'';
            if(!preg_match('#^projects/[^/]+/secrets/[^/]+/versions/[1-9][0-9]*$#',$version_name)) {
                $this->sendNoteOnce('security-cache-keys-secret-previous'
                    ,'security-cache-keys-secret-version-required: core.gcp.secrets.cache_keys_secret_previous must be the full name of a fixed version, projects/{project}/secrets/{secret}/versions/{number}, never latest. The previous cache keys are not used'
                    ,'error');
                return null;
            }
            if(!$this->isManagedRuntime()) return null;

            $hash = substr(hash('sha256',$version_name),0,16);
            $use_apcu = $this->cacheKeysUseApcu();
            $dir = rtrim((string)self::$cache_keys_instance_dir,'/')?:'/tmp';
            $copy_file = $dir.'/cf-cache-keys-'.$hash.'.json';
            $apcu_key = 'cf-cache-keys-'.$hash;

            // 1. copy of the instance
            if($use_apcu) {
                $found = false;
                $stored = apcu_fetch($apcu_key,$found);
                $copy_exists = (bool)$found;
                $keys = ($found && is_string($stored))?$this->validateCacheKeysJson($stored):null;
            } else {
                $copy_exists = (file_exists($copy_file) || is_link($copy_file));
                $keys = ($copy_exists && !is_link($copy_file))?$this->readLocalCacheKeysFile($copy_file):null;
            }
            if($keys) {
                $this->sendNoteOnce('security-cache-keys-secret-previous'
                    ,'security-cache-keys-secret-previous-used: source=instance-copy; previous cache keys of '.$version_name.' read from the copy of the instance');
                return $keys;
            }

            // 2. a recent failure is not retried
            if($this->cacheKeysSecretFailedRecently($use_apcu,$apcu_key.'-failed',$dir.'/cf-cache-keys-'.$hash.'.failed')) {
                $this->sendNoteOnce('security-cache-keys-secret-previous'
                    ,'security-cache-keys-secret-previous-unavailable: the read of '.$version_name.' failed less than '.self::CACHE_KEYS_SECRET_RETRY_SECONDS.' seconds ago in this instance and it is not retried yet. The previous cache keys are not used'
                    ,'warning');
                return null;
            }

            // 3. read the version from Secret Manager
            $reason = '';
            $content = $this->readCacheKeysSecret($version_name,$reason);
            $keys = (is_string($content))?$this->validateCacheKeysJson($content):null;
            $content = null;
            if(!$keys) {
                $this->markCacheKeysSecretFailed($use_apcu,$apcu_key.'-failed',$dir,$dir.'/cf-cache-keys-'.$hash.'.failed');
                $this->sendNoteOnce('security-cache-keys-secret-previous'
                    ,'security-cache-keys-secret-previous-unavailable: '.$version_name.' '.(($reason)?'could not be read ('.$reason.')':'does not hold a valid {"key","iv"} pair (base64 of 32 bytes each)')
                        .'. No copy is written, the read is not retried for '.self::CACHE_KEYS_SECRET_RETRY_SECONDS.' seconds and the previous cache keys are not used'
                    ,'warning');
                return null;
            }

            // 4. copy of the instance
            $copy = json_encode(['key'=>$keys['key'],'iv'=>$keys['iv'],'version'=>$version_name,'read'=>gmdate('Y-m-d H:i:s').' UTC'],JSON_UNESCAPED_SLASHES);
            if($use_apcu) {
                $copied = (bool)apcu_store($apcu_key,$copy);
                apcu_delete($apcu_key.'-failed');
            } else {
                if($copy_exists) @unlink($copy_file); // a corrupt copy is replaced
                $copied = $this->placeCacheKeysFile($dir,$copy_file,$copy) || ($this->readLocalCacheKeysFile($copy_file)===$keys);
                if(file_exists($dir.'/cf-cache-keys-'.$hash.'.failed')) @unlink($dir.'/cf-cache-keys-'.$hash.'.failed');
            }
            $copy = null;
            $this->sendNoteOnce('security-cache-keys-secret-previous'
                ,'security-cache-keys-secret-previous-used: source=secret-manager-read; previous cache keys read from '.$version_name
                    .(($copied)?'':'; the copy of the instance could not be written, the secret will be read again on the next request')
                ,($copied)?'info':'warning');
            return $keys;
        }

        /**
         * A managed runtime: GAE_SERVICE (App Engine), K_SERVICE (Cloud Run) or FUNCTION_TARGET (Cloud Functions), in
         * $_SERVER or in getenv(). CoreIs::development() is not enough: it is true in Cloud Run.
         * @return bool
         */
        private function isManagedRuntime(): bool
        {
            return self::managedRuntime();
        }

        /**
         * Static form of isManagedRuntime(), for code that runs before CoreSecurity exists (CorePerformance). Since 8.5.26.
         * @return bool
         */
        public static function managedRuntime(): bool
        {
            foreach (['GAE_SERVICE','K_SERVICE','FUNCTION_TARGET'] as $runtime_var)
                if(array_key_exists($runtime_var,$_SERVER) || getenv($runtime_var)!==false) return true;
            return false;
        }

        /**
         * Since 8.5.26: whether the response of this request may carry debug output: the log lines (logs), the trace of
         * the profiler (?__p) and the echo of the request (?__debug: headers, session, parameters). The single rule used by
         * RESTful::send() and dispatcher.php:
         *   - outside a managed runtime (local server or local script, see managedRuntime()): yes;
         *   - in a managed runtime: only when trustedClientIP() is EXACTLY one of debugAllowedIPs(). No wildcard, no
         *     partial match, and neither cron calls nor /queue/ URLs are exceptions.
         * Up to 8.5.25 the logs were returned to anyone unless restful.logs.allowed_ips was set, the IP was compared with
         * strpos() against X-Forwarded-For (which the caller writes), and URLs with /queue/ after the first character
         * skipped the check (ASVS 4.0.3 4.1.4).
         * @return bool
         */
        public function debugOutputAllowed(): bool
        {
            if(!self::managedRuntime()) return true;
            $ip = $this->trustedClientIP();
            if($ip === '') return false;
            $ip_bin = @inet_pton($ip);
            foreach ($this->debugAllowedIPs() as $allowed)
                if($ip_bin !== false && @inet_pton($allowed) === $ip_bin) return true;
            return false;
        }

        /**
         * Since 8.5.26: the IP of the client as the platform gives it, never as the caller writes it. In App Engine the
         * header X-Appengine-User-Ip, which App Engine sets and removes from the incoming request. Outside a managed
         * runtime REMOTE_ADDR. In other managed runtimes (Cloud Run, Cloud Functions) '' because there is no header the
         * caller can not write: debugOutputAllowed() is then false. X-Forwarded-For is never read here.
         * Since 8.5.28 CoreSystem::getClientIP() also reads the IP from the platform (CoreSystem::platformClientIP()),
         * but in Cloud Run and Cloud Functions it takes the last element of X-Forwarded-For; this one stays '' there.
         * @return string a valid IP or ''
         */
        public function trustedClientIP(): string
        {
            if(!self::managedRuntime()) $ip = $_SERVER['REMOTE_ADDR'] ?? '';
            elseif(array_key_exists('GAE_SERVICE',$_SERVER) || getenv('GAE_SERVICE')!==false) $ip = $_SERVER['HTTP_X_APPENGINE_USER_IP'] ?? '';
            else $ip = '';
            $ip = is_string($ip) ? trim($ip) : '';
            return (filter_var($ip, FILTER_VALIDATE_IP) !== false) ? $ip : '';
        }

        /**
         * Since 8.5.26: the IPs that receive debug output in a managed runtime. Read from the config-vars
         * core.api.debug.allowed_ips (canonical) and, for compatibility, restful.logs.allowed_ips and
         * core_api_logs_allowed_ips. Each one is an array or a comma separated string. Only valid IPs are kept: '*',
         * 'localhost', ranges and anything else are ignored.
         * @return string[]
         */
        public function debugAllowedIPs(): array
        {
            $ips = [];
            foreach (['core.api.debug.allowed_ips','restful.logs.allowed_ips','core_api_logs_allowed_ips'] as $var) {
                $value = $this->core->config->get($var);
                if(is_string($value)) $value = explode(',', $value);
                if(!is_array($value)) continue;
                foreach ($value as $ip) {
                    $ip = is_string($ip) ? trim($ip) : '';
                    if(filter_var($ip, FILTER_VALIDATE_IP) !== false) $ips[$ip] = $ip;
                }
            }
            return array_values($ips);
        }

        /**
         * Since 8.5.22: per-machine cache keys for LOCAL development, so that an application can drop the cache keys from
         * its config.json (they go to Secret Manager) without leaving the developers without cache. Called by
         * Core7::__construct() right after CoreCache is created. It only acts when ALL these conditions are true:
         *   1. neither cache key is configured (config-vars core.gcp.secrets.cache_encrypt_key / cache_encrypt_iv and
         *      env vars CF_CACHE_ENCRYPT_KEY / CF_CACHE_ENCRYPT_IV are all empty). A half-configured pair is a
         *      configuration error and keeps security-cache-key-missing;
         *   2. it is not a managed runtime: no GAE_SERVICE (App Engine), K_SERVICE (Cloud Run) nor FUNCTION_TARGET
         *      (Cloud Functions), neither in $_SERVER nor in getenv(). CoreIs::development() is not enough: it is true
         *      in Cloud Run;
         *   3. the cache is a file cache ($this->core->cache->type === 'directory'), never Redis or Datastore;
         *   4. {root_path}/local_data already exists and is writable. It is never created here.
         * The keys are read from {root_path}/local_data/cache-encrypt.json when it exists and is valid; when it does not
         * exist a new pair is generated with random_bytes() (32 bytes each, base64, the format of openssl rand -base64 32)
         * and written atomically with permissions 0600 (temporary file in the same folder, chmod, then link/rename: the
         * first process wins and the others read its file). An invalid file is never overwritten: the error
         * security-cache-key-local-file-invalid is logged and the keys stay missing. One log line per request, never
         * with the value or any part of it. In any other case nothing changes (security-cache-key-missing).
         *
         * @return bool true when the cache keys have been set from the local file
         */
        public function useLocalCacheKeys(): bool
        {
            // 1. configuration and environment always win; a half pair is not completed with a generated value
            if($this->cache_key || $this->cache_iv) return false;
            // 2. never in a managed runtime
            if($this->isManagedRuntime()) return false;
            // 3. only with the file cache
            if(!is_object($this->core->cache) || $this->core->cache->type!=='directory') return false;
            // 4. local_data must already exist and be writable (same folder the entry points use: {root_path}/local_data)
            $local_data = $this->core->system->root_path.'/local_data';
            if(!is_dir($local_data) || !is_writable($local_data)) return false;

            $file = $local_data.'/cache-encrypt.json';
            $generated = false;
            if(!file_exists($file)) {
                if(!$this->writeLocalCacheKeysFile($local_data,$file)) {
                    if(!file_exists($file)) {
                        $this->sendNoteOnce('security-cache-key-local'
                            ,'security-cache-key-local-write-failed: local_data/cache-encrypt.json could not be written. The cache keys stay missing','error');
                        return false;
                    }
                    // another process wrote it first: read its keys
                } else $generated = true;
            }

            $keys = $this->readLocalCacheKeysFile($file);
            if(!$keys) {
                $this->sendNoteOnce('security-cache-key-local'
                    ,'security-cache-key-local-file-invalid: local_data/cache-encrypt.json is not valid and it is not overwritten. The cache keys stay missing: fix or delete the file','error');
                return false;
            }

            $this->cache_key = $this->cache_key_base = $keys['key'];
            $this->cache_iv = $this->cache_iv_base = $keys['iv'];
            $this->cache_keys_missing = false;
            $this->sendNoteOnce('security-cache-key-local'
                ,($generated)
                    ?'security-cache-key-local-generated: source=local-file; no cache keys configured; per-machine local keys generated in local_data/cache-encrypt.json (local only)'
                    :'security-cache-key-local-used: source=local-file; no cache keys configured; per-machine local keys read from local_data/cache-encrypt.json (local only)'
                ,'info');
            return true;
        }

        /**
         * Reads and validates local_data/cache-encrypt.json: a JSON object with 'key' and 'iv', each one the base64 of
         * 32 bytes. Any other content returns null.
         * @param string $file
         * @return array|null ['key'=>string,'iv'=>string]
         */
        private function readLocalCacheKeysFile(string $file): ?array
        {
            if(!is_file($file) || !is_readable($file)) return null;
            return $this->validateCacheKeysJson((string)@file_get_contents($file));
        }

        /**
         * Validator shared by local_data/cache-encrypt.json (8.5.22), the content of the Secret Manager version of
         * core.gcp.secrets.cache_keys_secret and its copy in the instance (8.5.23): a JSON object with 'key' and 'iv',
         * each one the base64 of 32 bytes. Other properties are ignored. Any other content returns null.
         * @param string $json
         * @return array|null ['key'=>string,'iv'=>string]
         */
        private function validateCacheKeysJson(string $json): ?array
        {
            $data = json_decode($json,true);
            if(!is_array($data)) return null;
            foreach (['key','iv'] as $field) {
                if(!isset($data[$field]) || !is_string($data[$field])) return null;
                $raw = base64_decode($data[$field],true);
                if($raw===false || strlen($raw)!==32) return null;
            }
            return ['key'=>$data['key'],'iv'=>$data['iv']];
        }

        /**
         * Generates a new pair of cache keys and writes it atomically in $file with permissions 0600: a temporary file in
         * the same folder (created exclusively, chmod 0600 before writing) is hard-linked to $file, which never replaces
         * an existing file, so the first process wins. Without hard links (some file systems) it falls back to rename()
         * only when $file still does not exist. The temporary file is always removed.
         * @param string $dir local_data folder
         * @param string $file local_data/cache-encrypt.json
         * @return bool true when this call placed the file
         */
        private function writeLocalCacheKeysFile(string $dir, string $file): bool
        {
            $content = json_encode([
                'key'=>base64_encode(random_bytes(32)),
                'iv'=>base64_encode(random_bytes(32)),
                'created'=>gmdate('Y-m-d H:i:s').' UTC',
                'note'=>'Per-machine cache keys generated by backend-core-php8 for local development only. Never commit, copy or deploy this file.',
            ],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
            return $this->placeCacheKeysFile($dir,$file,$content);
        }

        /**
         * Writes $content in $file atomically with permissions 0600 and never replaces an existing file: a temporary file
         * in $dir named .{basename($file)}.{random}.tmp is created exclusively, chmod 0600 before writing, and hard-linked
         * to $file (first process wins); without hard links it falls back to rename() only when $file still does not
         * exist. The temporary file is always removed. Shared by writeLocalCacheKeysFile() (8.5.22) and the instance copy
         * of the Secret Manager keys (8.5.23).
         * @param string $dir folder of $file
         * @param string $file
         * @param string $content
         * @return bool true when this call placed the file
         */
        private function placeCacheKeysFile(string $dir, string $file, string $content): bool
        {
            $tmp = $dir.'/.'.basename($file).'.'.bin2hex(random_bytes(8)).'.tmp';
            if(!$fh = @fopen($tmp,'x')) return false;
            $placed = false;
            if(@chmod($tmp,0600) && @fwrite($fh,$content)===strlen($content) && @fflush($fh)) {
                fclose($fh); $fh = null;
                $placed = !file_exists($file) && @link($tmp,$file);
                if(!$placed && !file_exists($file)) $placed = @rename($tmp,$file);
            }
            if($fh) fclose($fh);
            if(file_exists($tmp)) @unlink($tmp);
            return $placed;
        }


        /**
         * Sets the development environment status for external calls
         *
         * @param bool $activate Determines whether to activate or deactivate the development environment. Defaults to true.
         * @return void
         */
        public function setDevEnvironment($activate=true) {
            $this->isDev = $activate;
        }


        /**
         * Assgin a $secret_value to the secret $secret_key
         * @param $secret_key
         * @param $secret_value
         */
        public function setSecretVar($secret_key,$secret_value) {
            $this->secret_vars['secrets'][$secret_key] =$secret_value;
        }

        /**
         * Return the secret value of $secret_key
         * @param $secret_key
         * @return mixed|null
         */
        public function getSecretVar($secret_key) {
            return $this->secret_vars['secrets'][$secret_key] ?? null;
        }

        /**
         * Return $this->secret_vars
         * @param $secret_key
         * @return mixed|null
         */
        public function getSecretVars() {
            return $this->secret_vars;
        }

        /**
         * Check if exists a Basic Authorizatino header
         * @return boolean tellong
         */
        function existBasicAuth()
        {
            return (isset($_SERVER['PHP_AUTH_USER']) && strlen($_SERVER['PHP_AUTH_USER'])
                || (isset($_SERVER['HTTP_AUTHORIZATION']) && strpos(strtolower($_SERVER['HTTP_AUTHORIZATION']), 'basic') === 0)
            );
        }

        /**
         * Return an array with [user,password] from PHP_AUTH_USER, PHP_AUTH_PW or if they don't exist HTTP_AUTHORIZATION header
         * @return
         */
        function getBasicAuth()
        {
            $username = null;
            $password = null;
            // mod_php
            if (isset($_SERVER['PHP_AUTH_USER'])) {
                $username = $_SERVER['PHP_AUTH_USER'];
                $password = $_SERVER['PHP_AUTH_PW'];
                // most other servers
            } elseif (isset($_SERVER['HTTP_AUTHORIZATION'])) {
                if (strpos(strtolower($_SERVER['HTTP_AUTHORIZATION']), 'basic') === 0)
                    list($username, $password) = explode(':', base64_decode(substr($_SERVER['HTTP_AUTHORIZATION'], 6)));
            }
            return ([$username, $password]);
        }

        /**
         * Verify user,password with Basic Authorization Header from  $this->getBasicAuth()
         */
        function checkBasicAuth($user, $passw)
        {
            list($username, $password) = $this->getBasicAuth();
            return (!is_null($username) && $user == $username && $passw == $password);
        }

        function existBasicAuthConfig()
        {
            return is_array($this->core->config->get('authorizations'));
        }

        function checkBasicAuthWithConfig()
        {
            $ret = false;
            list($user, $passw) = $this->getBasicAuth();

            // Kee backwards compatibility:
            if($this->core->config->get('authorizations') && !$this->core->config->get('core.system.authorizations'))
                $this->core->config->set('core.system.authorizations',$this->core->config->get('authorizations'));


            if ($user === null) {
                $this->core->logs->add('checkBasicAuthWithConfig: No Authorization in headers ');
            } elseif (!is_array($auth = $this->core->config->get('core.system.authorizations'))) {
                $this->core->logs->add('checkBasicAuthWithConfig: no "core.system.authorizations" array in config. ');
            } elseif (!isset($auth[$user])) {
                $this->core->logs->add('checkBasicAuthWithConfig: key  does not match in "core.system.authorizations"');
            } elseif (!$this->core->security->checkCrypt($passw, ((isset($auth[$user]['password']) ? $auth[$user]['password'] : '')))) {
                $this->core->logs->add('checkBasicAuthWithConfig: password does not match in "core.system.authorizations"');

                // User and password match!!!
            } else {
                $ret = true;
                // IPs Security
                if (isset($auth[$user]['ips']) && strlen($auth[$user]['ips'])) {
                    if (!($ret = $this->checkIPs($auth[$user]['ips']))) {
                        $this->core->logs->add('checkBasicAuthWithConfig: IP "' . $this->core->system->ip . '" not allowed');
                    }
                }
            }

            // Return the array of elements it passed
            if ($ret) {
                $auth[$user]['_BasicAuthUser_'] = $user;
                $ret = $auth[$user];
            }
            return $ret;
        }

        /*
         * API KEY
         */
        function existWebKey()
        {
            return (isset($_GET['web_key']) || isset($_POST['web_key']) || strlen($this->getHeader('X-WEB-KEY')));
        }

        /**
         * Read a Development SubKeys to encrypt locally the cache where the secrets will be stores
         * In production these subkeys are cached to increase performance and because the risk who someone have access to Cache server is low. It rotates every day.
         * @param string $erp_platform_id if empty it will try to get it from $this->core->config->get('core.erp.platform_id')
         * @param string $erp_user if empty it will try to get it from $this->core->config->get('core.erp.user_id.'.$erp_platform_id)
         * @return bool|void
         */
        public function readERPDeveloperEncryptedSubKeys($erp_platform_id='',$erp_user='')
        {

            //avoid to call the method more than once
            if($this->last_key_cache) return true;

            //region CHECK $erp_platform_id value
            if(!$erp_platform_id || !is_string($erp_platform_id)) {
                $erp_platform_id = $this->platform;
                if(!$erp_platform_id) return($this->addError('readERPDeveloperEncryptedSubKeys(..) missing function-var($erp_platform_id) or config-var(core.erp.platform_id)'));
            }
            $this->platform = $erp_platform_id;
            //endregion

            //region CHECK $erp_user value
            if(!$erp_user || !is_string($erp_user)) {
                $erp_user = $this->core->config->get('core.erp.user_id.'.$erp_platform_id);
                if(!$this->core->config->get('core.erp.platform_id') || $this->core->config->get('core.erp.platform_id')!=$erp_platform_id) {
                    $erp_user = $this->core->security->getGoogleEmailAccount();
                    if($this->core->is->development() && $this->core->is->script()) echo '  - Read $this->core->security->getGoogleEmailAccount() in readERPDeveloperEncryptedSubKeys()';
                }
                if(!$erp_user) return($this->addError('readERPDeveloperEncryptedSubKeys(..) missing function-var($erp_user) or config-var(core.erp.user_id.'.$erp_platform_id.'.)'));
            }
            //endregion

            //region SET $url, $key_cache, $keys=[];
            $url = 'https://api.cloudframework.io/core/secrets/'.$erp_platform_id.'/my-daily-encryption-subkeys/'.$erp_user;
            // Since 8.5.12 the subkeys are cached ENCRYPTED with the base cache keys under a new cache key, so
            // versions < 8.5.12 (plain entry) and >= 8.5.12 (encrypted entry) do not overwrite each other.
            $key_cache = '/my-daily-encryption-subkeys-v2/'.$erp_user;
            $legacy_key_cache = '/my-daily-encryption-subkeys/'.$erp_user;
            $keys = [];
            $use_cache = $this->cacheKeysAvailable();
            //endregion

            //region GET from cache $keys if we are in production servers (a decryption failure is a cache miss)
            if($use_cache) $keys = $this->core->cache->get($key_cache,3600*24,date('Y-m-d'),$this->cache_key_base,$this->cache_iv_base);
            //endregion

            //region CALL $url and SET $keys if empty $keys
            if(!$keys) {
                //region SET $headers and call $url
                $headers = ['X-WEB-KEY'=>$erp_user];
                $keys = $this->core->request->get_json_decode($url,null,$headers);
                if($this->core->request->error) return($this->addError(['Error in developer license for user: '.$erp_user,($keys['message']??'no-message')]));
                //endregion

                //region SAVE encrypted in cache $keys and DELETE the legacy plain entry (best-effort)
                if($use_cache) $this->core->cache->set($key_cache,$keys,date('Y-m-d'),$this->cache_key_base,$this->cache_iv_base);
                $this->core->cache->delete($legacy_key_cache);
                //endregion
            }
            //endregion

            //region ADD $keys to  $this->cache_key and $this->cache_iv avoiding to be added more that one time
            if(!isset($keys['data']['key']) || !isset($keys['data']['iv'])) return($this->addError('Error in CloudFramework Service to retrieve subkeys. key or iv is missing'));
            if(strpos($this->cache_key,'__'.$keys['data']['key'])===false)
                $this->cache_key.='__'.$keys['data']['key'];
            if(strpos($this->cache_iv,'__'.$keys['data']['iv'])===false)
                $this->cache_iv.='__'.$keys['data']['iv'];
            //endregion

            //avoid to call the method more than once
            $this->last_key_cache = $this->cache_key;
            return true;
        }

        /**
         * GET from CloudFramework ERP Secrets of user is running the script or the GCP appengine,cloudfuntion,computeengine
         * If the user is running in localhost it will prompted
         * If the GCP engine is running it will use the Token of the Instance
         * @param string $erp_platform_id
         * @param string $erp_user User to use in the autentication. If empty it will prompt in the terminal if you are an script
         */
        function readMyERPSecretsVars($erp_platform_id='',$erp_user='') {

            //region CHECK $erp_platform_id value
            if(!$erp_platform_id || !is_string($erp_platform_id)) {
                $erp_platform_id = $this->platform;
                if(!$erp_platform_id) return($this->addError('readERPSecretVars(..) missing function-var($erp_platform_id) or config-var(core.erp.platform_id)'));
            }
            $this->platform = $erp_platform_id;
            //endregion

            //region CHECK $erp_user value
            if(!$erp_user || !is_string($erp_user)) {
                $erp_user = $this->core->config->get('core.erp.user_id.'.$erp_platform_id);
                if(!$this->core->config->get('core.erp.platform_id') || $this->core->config->get('core.erp.platform_id')!=$erp_platform_id) {
                    $erp_user = $this->core->security->getGoogleUserEmailAccount();
                }
                if(!$erp_user) return($this->addError('readERPSecretVars(..) missing function-var($erp_platform_id) or config-var(core.erp.platform_id)'));
            }
            //endregion

            return($this->readPlatformSecretVars($erp_user,$erp_platform_id,$erp_user));
        }

        /**
         * Return a specific secret var form ERP
         * @deprecated Use getPlatformSecretVar
         * @param $var
         * @param string $erp_secret_id
         * @param string $erp_platform_id
         * @param string $erp_user
         */
        public function getERPSecretVar($var, string $erp_secret_id='', string $erp_platform_id='', string $erp_user='') {
            return $this->getPlatformSecretVar($var,$erp_secret_id,$erp_platform_id,$erp_user);
        }

        /**
         * Return a specific secret var from CLOUD PLATFORM
         * @param $var
         * @param string $erp_secret_id
         * @param string $erp_platform_id
         * @param string $erp_user
         */
        public function getPlatformSecretVar($var, string $erp_secret_id='', string $erp_platform_id='', string $erp_user='')
        {

            // Read only when it is necessary
            if(!$erp_platform_id) {
                $erp_platform_id = $this->platform;
                if(!$erp_platform_id) return($this->addError('getPlatformSecretVar(..) missing function-var($erp_platform_id) or config-var(core.erp.platform_id)'));
            }

            // isDev is part of the memo identity: dev and prod share the same base platform id,
            // so without this check a dev↔prod toggle would serve secrets of the wrong environment
            if($this->secret_vars ===null
                || ($erp_secret_id && $this->secret_vars['secret-id']!=$erp_secret_id)
                || ($this->secret_vars['platform']??null)!=$erp_platform_id
                || ($this->secret_vars['isDev']??false)!==$this->isDev
            ) {
                if (!$this->readPlatformSecretVars($erp_secret_id, $erp_platform_id, $erp_user)) return false;
            }
            return $this->secret_vars['secrets'][$var]??null;
        }

            /**
         * Deprecated. User readPlatformSecretVars
         * $this->get('core.gcp.secrets.env_vars') in the $this->get('core.gcp.secrets.project_id')
         *
         * @deprecated Use readPlatformSecretVars
         * @param string $erp_secret_id ID of the secret in the ERP. If this ID match with with
         * @param string $erp_platform_id ID of the platform to look for the secret
         * @param string $erp_user ID of the user who reads the secret
         * @return mixed|null if the env var $var exist it returns the content and it can be any type
         */
        public function readERPSecretVars($erp_secret_id='',$erp_platform_id='',$erp_user='')
        {
            return $this->readPlatformSecretVars($erp_secret_id,$erp_platform_id,$erp_user);
        }

        /**
         * Read secrets stored in CLOUD-PLATFORM
         * $this->get('core.gcp.secrets.env_vars') in the $this->get('core.gcp.secrets.project_id')
         * @param string $erp_secret_id ID of the secret in the ERP. If this ID match with with
         * @param string $erp_platform_id ID of the platform to look for the secret
         * @param string $erp_user ID of the user who reads the secret
         * @return mixed|null if the env var $var exist it returns the content and it can be any type
         */
        public function readPlatformSecretVars($erp_secret_id='',$erp_platform_id='',$erp_user='') {

            //region CHECK $erp_platform_id value
            if(!$erp_secret_id || !is_string($erp_secret_id)) {
                $erp_secret_id = $this->core->config->get('core.erp.secrets.secret_id');
                if(!$erp_secret_id) return($this->addError('readPlatformSecretVars(..) missing function-var($erp_secret_id) or config-var(core.erp.secrets.secret_id)'));
            }
            //endregion

            //region CHECK $erp_platform_id value
            if(!$erp_platform_id || !is_string($erp_platform_id)) {
                $erp_platform_id = $this->core->config->get('core.erp.platform_id');
                if(!$erp_platform_id) return($this->addError('readPlatformSecretVars(..) missing function-var($erp_platform_id) or config-var(core.erp.platform_id)'));
            }
            //endregion
            //region CHECK $erp_user value
            if(!$erp_user || !is_string($erp_user)) {
                $erp_user = $this->core->config->get('core.erp.user_id.'.$erp_platform_id);
                if(!$erp_user) {
                    $key_erp_user = 'getGoogleEmailAccount_'.$this->core->gc_project_id.'_'.$erp_platform_id;
                    $erp_user = $this->getCache($key_erp_user,'ERP.users');
                    if(!$erp_user) {
                        $erp_user = $this->core->security->getGoogleEmailAccount();
                        $this->updateCache($key_erp_user,$erp_user,'ERP.users');
                    }
                }
                if(!$erp_user) return($this->addError('readPlatformSecretVars(..) missing function readERPSecretVars(..,$erp_user) or config-var(core.erp.user_id.{platform_id})'));
            }
            //endregion

            if(!$this->readERPDeveloperEncryptedSubKeys($erp_platform_id,$erp_user))
                return($this->addError('Called from readPlatformSecretVars(..)'));

            //region READ $user_secrets from cache and RETURN it if it exist
            $key = 'getMyERPSecrets_'.$this->core->gc_project_id.'_'.$erp_platform_id.'_'.$erp_secret_id.($this->isDev?'_dev':'');
            $user_secrets = $this->getCache($key,'ERP.secrets');

            //verify $user_secrets['id'] match with $erp_user
            //fix bug when in production the $erp_user is returning default
            if($erp_user=='default' && isset($user_secrets['id'])) $erp_user = $user_secrets['id'];

            //check the
            if($erp_user && isset($user_secrets['id']) && $user_secrets['id']!=$erp_user) $user_secrets=[];
            if($user_secrets){
                $this->secret_vars = [
                    'id'=>$user_secrets['id'],
                    'secret-id'=>$user_secrets['secret-id'],
                    'platform'=>$user_secrets['platform'],
                    'isDev'=>$this->isDev,
                    'secrets'=>$user_secrets['secrets']
                ];
                return true;
            }
            //endregion

            //region IF $user_secrets is empty feed it with basic structure
            $user_secrets = ['access_token'=>null,'id'=>'','platform'=>$erp_platform_id,'secrets'=>''];
            //endregion

            //region SET $token from User or GCP Engine Instance Token
            $token = $this->getGoogleIdentityToken($erp_user);
            if(!$token) return($this->addError('CoreSecurity.readPlatformSecretVars() has returned an error calling $this->getGoogleIdentityToken($erp_user)'));
            //endregion

            //region VERIFY $token and SET $user_secrets['id'] and $user_secrets['token']
            $token_info = $this->getGoogleTokenInfo($token);
            if(isset($token_info['error'])) return($this->addError($token_info));
            // If the token_info does not contains email attribute the we trust the email sent in the header.
            if(!isset($token_info['email']) && !isset($token_info['sub'])) return($this->addError('CoreSecurity.readERPSecretVars() has not got a token with email or $token attributes'));

            $user_secrets['id'] = (isset($token_info['email']))?$token_info['email']:$token_info['sub'];
            $user_secrets['token'] = $token_info;
            //endregion

            //region CALL secret CF API and set $user_secrets['secrets']
            if($erp_secret_id!=$user_secrets['id']){
                $url = 'https://api.cloudframework.io/core/secrets/'.$erp_platform_id.'/system-secrets/'.$user_secrets['id'].'/'.$erp_secret_id.($this->isDev?'?_dev=1':'');
            }
            else{
                $url = 'https://api.cloudframework.io/core/secrets/'.$erp_platform_id.'/my-secrets/'.$user_secrets['id'].($this->isDev?'?_dev=1':'');
            }

            $headers = ['X-WEB-KEY'=>$user_secrets['id'],'X-DS-TOKEN'=>$token];
            $secrets = $this->core->request->get_json_decode($url,null,$headers);

            if($this->core->request->error) {
                $this->addError(($secrets['message']??null)?:$this->core->request->errorMsg);
                $this->core->request->reset();
                return false;
            }

            $user_secrets['secret-id'] = ($erp_secret_id)?:$user_secrets['id'];
            $user_secrets['platform'] = $erp_platform_id;
            $user_secrets['secrets'] = $secrets['data']['secrets'];
            //endregion

            //region UPDATE cache
            $this->updateCache($key,$user_secrets,'ERP.secrets');
            //endregion

            //region RETURN $user_secrets
            $this->secret_vars = [
                'id'=>$user_secrets['id'],
                'secret-id'=>$user_secrets['secret-id'],
                'platform'=>$user_secrets['platform'],
                'isDev'=>$this->isDev,
                'secrets'=>$user_secrets['secrets']];
            return true;
            //endregion

        }

        /**
         * Reset the cache for the ERP Secrets
         * @deprecated use resetPlatformCache
         */
        public function resetERPCache() {
            return $this->resetPlatformCache();
        }

        /**
         * Reset the cache for the ERP Secrets
         */
        public function resetPlatformCache() {
            return $this->resetCache('ERP.secrets');
        }

        /**
         * Execute a user Prompt
         * @param $title
         * @param null $default
         * @return false|string|null
         */
        function prompt($title,$default=null) {
            // Check default value
            if($default) $title.="[{$default}] ";
            $ret = readline($title);
            if(!$ret) $ret=$default;
            return $ret;
        }

        /**
         * Get the Google user email that the terminal or the instance (appengine, computeengine, or user localhost..) is using.
         */
        function getGoogleEmailAccount() {
            if($this->core->is->development()) {
                $gcloud_auth_list = 'gcloud auth list 2>/dev/null';
                $auth_list = shell_exec($gcloud_auth_list);
                if(!$auth_list) return($this->addError("'{$gcloud_auth_list}' has produced an error. Install gcloud or check gcloud auth login"));
                $lines = explode("\n",$auth_list);
                array_shift($lines);
                if(!isset($lines[0]) || strpos($auth_list,'ACTIVE')===false) return($this->addError("'{$gcloud_auth_list}' has no active account. Execute gcloud auth login"));
                array_shift($lines);
                $user='';
                do {
                    if(strpos($lines[0],'*')===0) {
                        $user = preg_replace('/[\* ]/','',trim($lines[0]));
                    } elseif(strpos($lines[0],'ACCOUNT: ')===0) {
                        $user = str_replace('ACCOUNT: ','',trim($lines[0]));
                    }
                    array_shift($lines);
                } while($lines && !$user);
                if(!$user) return($this->addError("getGoogleEmailAccount() has not found an active user with '{$gcloud_auth_list}'. Verify the programming of this method"));
                return $user;
            } else {
                try {

                    //get user using service account of the the server
                    $metadata = new Google\Cloud\Core\Compute\Metadata();
                    $metaparts = explode('/',$metadata->get('instance/service-accounts'));

                    //some times the service returns "default/\n<Service Account>" instead the name of the service directly
                    $user = ($metaparts[0]=='default')?trim($metaparts[1]):$metaparts[0];

                    // return the user calculated
                    return $user;
                } catch (Exception $e) {
                    return($this->addError("getGoogleUserEmailAccount() has produced an error in metadata call: ".$e->getMessage()));
                }
            }
        }

        /**
         * Prompt the user to generate a token
         * @param string $user this should be passed when you are working in a script or development environment
         * @return array|voud
         * {
        "access_token": "ya29.****",
        "expires_in": 1799,
        "token_type": "Bearer"
        }
         */
        function getGoogleAccessToken(string $user='') {
            if($this->core->is->development()) {
                $gcloud_token_command = 'gcloud auth print-access-token';
                if($user) $gcloud_token_command.=' --account='.$user;
                $token = shell_exec($gcloud_token_command);
                if(!$token) return($this->addError('CoreSecurity.getGoogleUserAccessToken(..) The following command does not work: '.$gcloud_token_command));
                $token = ['access_token'=>$token,'token_type'=>'Bearer','expires_in'=>0];
            } else {
                try {
                    $url = 'instance/service-accounts/default/token';
                    $metadata = new Google\Cloud\Core\Compute\Metadata();
                    $token =  json_decode($metadata->get($url),true);
                } catch (Exception $e) {
                    return($this->addError("CoreSecurity.getGoogleUserAccessToken(..) has produced an error calling {$url}: ".$e->getMessage()));
                }
            }
            return($token);
        }

        /**
         * Get an Identity token for the $user
         * https://cloud.google.com/compute/docs/instances/verifying-instance-identity#curl
         * https://cloud.google.com/compute/docs/instances/verifying-instance-identity#token_format
         * @param string $user this should be passed when you are working in a script or development environment
         * @param string $audience this is the audience added to the token for more security. Only valid for GAE service account
         * @return string|void example: eyJhbGciOiJSUzI1NiIsImtpZCI6I....
         */
        function getGoogleIdentityToken($user='',$audience='https//api.cloudframework.io') {
            if($this->core->is->development()) {
                $gcloud_token_command = 'gcloud auth print-identity-token';
                if($audience) {
                    if(strpos($user,'compute@developer.gserviceaccount.com'))
                        $gcloud_token_command.=' --audiences='.$audience;
                }
                if($user) {
                    $gcloud_token_command .= ' --account=' . $user;
                    if(strpos($user,'compute@developer.gserviceaccount.com')) {
                        $gcloud_token_command.=' --token-format=full';
                    }
                }
                $token = shell_exec($gcloud_token_command);
                if(!$token) return($this->addError('CoreSecurity.getGoogleIdentityToken(...) The following command does not work ['.$gcloud_token_command.']. Execute manually [gcloud auth login] and try again.'));
            } else {
                try {
                    $url='instance/service-accounts/default/identity?format=full&licenses=true';
                    if($audience) $url.='&audience='.$audience;
                    $metadata = new Google\Cloud\Core\Compute\Metadata();
                    $token = $metadata->get($url);
                    if(!$token) return($this->addError('CoreSecurity.getGoogleIdentityToken(...)  Error calling The following url does not work: '.$url));
                } catch (Exception $e) {
                    return($this->addError('CoreSecurity.getGoogleIdentityToken(...)  Error calling The following url does not work: '.$url));
                }
            }
            return($token);
        }

        /**
         * Return info about an Access Token or Identity Tokens
         * This function call getGoogleAccessTokenInfo($token) or getGoogleIdentityTokenInfo($token) depending on $token format
         * @param $token
         * @return array|void
         */
        function getGoogleTokenInfo($token) {

            // IF IT IS AN ACCESS TOKEN
            if(strpos($token,'ya29.')===0) {
                return($this->getGoogleAccessTokenInfo($token));
            }
            // ELSE THEN IT HAS TO BE AN IDENTITY TOKEN
            // https://developers.google.com/identity/gsi/web/guides/verify-google-id-token
            else {
                return($this->getGoogleIdentityTokenInfo($token));
            }
        }

        /**
         * Retrieve info about a Google Access Token
         * For example you can generate a localhost token using: gcloud auth print-access-token --account={{personal_email_user}}
         * For example you can instance token with:
         *         $metadata = new Google\Cloud\Core\Compute\Metadata();
         *         $ret = json_decode($metadata->get('instance/service-accounts/default/token'),true)['access_token'];
         * @param $token
         * @return mixed|string
         * appengine output {
        "issued_to": "anonymous",
        "audience": "anonymous",
        "scope": "https://www.googleapis.com/auth/trace.append https://www.googleapis.com/auth/monitoring.write https://www.googleapis.com/auth/userinfo.email https://www.googleapis.com/auth/logging.write https://www.googleapis.com/auth/cloud_debugger https://www.googleapis.com/auth/cloud-platform https://www.googleapis.com/auth/devstorage.full_control https://www.googleapis.com/auth/appengine.apis",
        "expires_in": 1408,
        "email": "cloudframework-io@appspot.gserviceaccount.com",
        "verified_email": true,
        "access_type": "online"
        },
         * compute engine script {
        "aud": "32555940559.apps.googleusercontent.com",
        "azp": "107073846750350635781",
        "email": "173191905033-compute@developer.gserviceaccount.com",
        "email_verified": true,
        "exp": 1638615700,
        "google": {
        "compute_engine": {
        "instance_creation_timestamp": 1608593543,
        "instance_id": "5265569244953019153",
        "instance_name": "bnext-spain-etl-machine",
        "license_id": [
        "5926592092274602096"
        ],
        "project_id": "bnext-cloud",
        "project_number": 173191905033,
        "zone": "europe-west1-b"
        }
        },
        "iat": 1638612100,
        "iss": "https://accounts.google.com",
        "sub": "107073846750350635781"
        }
         */
        function getGoogleAccessTokenInfo($token)
        {
            $token_info = $this->core->request->post_json_decode('https://www.googleapis.com/oauth2/v1/tokeninfo',['access_token'=>$token],['Content-Type'=>'application/x-www-form-urlencoded']);
            if($this->core->request->error) {
                $this->addError((isset($token_info['error']))?$token_info:$this->core->request->errorMsg);
                if(!isset($token_info['error_description'])) {
                    $token_info['error_description'] = 'unknown';
                } else {
                    $this->core->errors->reset();
                }
            }
            if(isset($token_info['error'])) $token_info['code'] = $this->core->request->getLastResponseCode();
            $this->core->request->reset();
            return $token_info;
        }

        /**
         * Retrieve info about a Google Identity Token
         * https://developers.google.com/identity/gsi/web/guides/verify-google-id-token
         * https://cloud.google.com/compute/docs/instances/verifying-instance-identity#token_format
         * @param $token
         * @return array|boolean|null if the token is valid returns an array otherwise false. If there is a service error it returns null and addError in the class
         * appengine output example{
        "aud": "https://api7.cloudframework.io",
        "azp": "105263613482625024225",
        "email": "cloudframework-io@appspot.gserviceaccount.com",
        "email_verified": true,
        "exp": 1638601791,
        "iat": 1638598191,
        "iss": "https://accounts.google.com",
        "sub": "105263613482625024225"
        },
         * computeengine script example{
        "aud": "32555940559.apps.googleusercontent.com",
        "azp": "107073846750350635781",
        "exp": 1638609030,
        "iat": 1638605430,
        "iss": "https://accounts.google.com",
        "sub": "107073846750350635781"
        },
         * user localhost example{
        "iss": "https://accounts.google.com",
        "azp": "32555940559.apps.googleusercontent.com",
        "aud": "32555940559.apps.googleusercontent.com",
        "sub": "116008531197812823243",
        "hd": "adrianmm.com",
        "email": "info@adrianmm.com",
        "email_verified": true,
        "at_hash": "LgKsYP8U6NcmstQR1LgO7w",
        "iat": 1638611898,
        "exp": 1638615498
        }
         */
        function getGoogleIdentityTokenInfo($token)
        {
            try {
                $client = new Google_Client();
                $token_info = $client->verifyIdToken($token);
                return $token_info;
            } catch (Exception $e) {
                $this->addError(['getGoogleIdentityTokenInfo',$e->getMessage()]);
                return null;
            }

        }

        /**
         * Read $_GET['web-key'] or HEADER X-WEB-KEY
         * @return mixed|string
         */
        function getWebKey()
        {
            if (isset($_GET['web_key'])) return $_GET['web_key'];
            else if (isset($_POST['web_key'])) return $_POST['web_key'];
            else if (strlen($this->getHeader('X-WEB-KEY'))) return $this->getHeader('X-WEB-KEY');
            else return '';
        }

        /**
         * @param null $keys
         * @return array|false|mixed
         */
        function checkWebKey($keys = null)
        {

            // If I don't have the credentials in keys I try to check if CLOUDFRAMEWORK-WEB-KEYS is defined.
            if (null === $keys) {
                $keys = $this->core->config->get('CLOUDFRAMEWORK-WEB-KEYS');
                if (!is_array($keys)) return false;
            }

            // Analyzing $keys
            if (!is_array($keys)) $keys = [[$keys, '*']];
            else if (!is_array($keys[0])) $keys = [$keys];
            $web_key = $this->getWebKey();

            if (strlen($web_key))
                foreach ($keys as $key) {
                    if ($key[0] == $web_key) {
                        if (!isset($key[1])) $key[1] = "*";
                        if ($key[1] == '*') return $key;
                        elseif (!strlen($_SERVER['HTTP_ORIGIN'])) return false;
                        else {
                            $allows = explode(',', $key[1]);
                            foreach ($allows as $host) {
                                if (preg_match('/^.*' . trim($host) . '.*$/', $_SERVER['HTTP_ORIGIN']) > 0) return $key;
                            }
                            return false;
                        }
                    }
                }
            return false;
        }

        function existServerKey()
        {
            return (strlen($this->getHeader('X-SERVER-KEY')) > 0);
        }

        function getServerKey()
        {
            return $this->getHeader('X-SERVER-KEY');
        }

        function checkServerKey($keys=null)
        {
            // If I don't have the credentials in keys I try to check if CLOUDFRAMEWORK-SERVER-KEYS is defined.
            if (null === $keys) {
                $keys = $this->core->config->get('CLOUDFRAMEWORK-SERVER-KEYS');
                if (!is_array($keys)) return false;
            }


            if (!is_array($keys)) $keys = [[$keys, '*']];
            else if (!is_array($keys[0])) $keys = [$keys];
            $web_key = $this->getServerKey();

            if (strlen($web_key))
                foreach ($keys as $key) {
                    if ($key[0] == $web_key) {

                        if (!isset($key[1])) $key[1] = "*";
                        if ($key[1] == '*') return $key;
                        else return $this->checkIPs($key[1]);
                    }
                }
            return false;
        }

        /**
         * Whether the IP of the client is in $allows (see ipInList()).
         * Since 8.5.28 the comparison is exact or by CIDR. Until 8.5.27 it was preg_match('/^.*ENTRY.*$/'): an entry
         * matched anywhere in the IP (1.2.3.4 matched 11.2.3.45) and, with the IP read from X-Forwarded-For, a caller
         * that wrote an allowed IP in that header passed.
         * @param array|string $allows IPs, CIDR ranges or legacy prefixes ending in '.' or ':', separated by ','
         * @return bool
         */
        private function checkIPs($allows)
        {
            return self::ipInList((string)$this->core->system->ip, $allows);
        }

        /**
         * Since 8.5.28: the single comparator of IP allow lists of the framework (checkIPs(), checkAllowedIPs()), public
         * so that applications compare the same way. $ip must be one IP (as CoreSystem::getClientIP() returns it).
         * Each entry of $allows (an array, or a string separated by ',') is one of:
         *  - '*': any IP;
         *  - an IPv4 or IPv6: equal to $ip (IPv6 compared in binary form, so any notation of the same address matches);
         *  - a CIDR range, IPv4 or IPv6 (10.0.0.0/8, 2001:db8::/32);
         *  - 'localhost': $ip is localhost, 127.0.0.1 or ::1;
         *  - a legacy prefix ending in '.' or ':' (192.168.1.): $ip starts with it. Kept for allow lists written for the
         *    old checkIPs(), now on a validated IP only.
         * Anything else in an entry is ignored. An $ip that is not a valid IP (nor 'localhost') only matches '*'.
         * @param string $ip
         * @param array|string|null $allows
         * @return bool
         */
        public static function ipInList(string $ip, $allows): bool
        {
            if(is_string($allows)) $allows = explode(',', $allows);
            if(!is_array($allows)) return false;
            $ip = trim($ip);
            $is_local = in_array($ip, ['localhost', '127.0.0.1', '::1'], true);
            $ip_bin = (filter_var($ip, FILTER_VALIDATE_IP) !== false) ? inet_pton($ip) : false;
            if($ip === 'localhost') $ip_bin = inet_pton('127.0.0.1');

            foreach ($allows as $allow) {
                $allow = is_string($allow) ? trim($allow) : '';
                if($allow === '') continue;
                if($allow === '*') return true;
                if($ip_bin === false) continue;
                if($allow === 'localhost') { if($is_local) return true; continue; }

                if(strpos($allow, '/') !== false) {
                    [$net, $bits] = explode('/', $allow, 2);
                    if(filter_var($net, FILTER_VALIDATE_IP) === false || !ctype_digit($bits)) continue;
                    $net_bin = inet_pton($net);
                    $bits = (int)$bits;
                    if(strlen($net_bin) !== strlen($ip_bin) || $bits > strlen($net_bin) * 8) continue;
                    $full = intdiv($bits, 8);
                    $rest = $bits % 8;
                    if(substr($net_bin, 0, $full) !== substr($ip_bin, 0, $full)) continue;
                    if($rest === 0) return true;
                    $mask = chr((0xFF << (8 - $rest)) & 0xFF);
                    if((($net_bin[$full] & $mask) === ($ip_bin[$full] & $mask))) return true;
                    continue;
                }

                if(filter_var($allow, FILTER_VALIDATE_IP) !== false) {
                    if(inet_pton($allow) === $ip_bin) return true;
                    continue;
                }

                $last = substr($allow, -1);
                if(($last === '.' || $last === ':') && preg_match('/^[0-9a-fA-F.:]+$/', $allow)
                    && $ip !== 'localhost' && stripos($ip, $allow) === 0) return true;
            }
            return false;
        }

        function getHeader($str)
        {
            $str = strtoupper($str);
            $str = str_replace('-', '_', $str);
            return ((isset($_SERVER['HTTP_' . $str])) ? $_SERVER['HTTP_' . $str] : '');
        }

        // Check checkCloudFrameWorkSecurity
        function checkCloudFrameWorkSecurity($maxSeconds = 0, $id = '', $secret = '')
        {
            if (!strlen($this->getHeader('X-CLOUDFRAMEWORK-SECURITY')))
                $this->core->logs->add('X-CLOUDFRAMEWORK-SECURITY missing.');
            else {
                list($_id, $_zone, $_time, $_token) = explode('__', $this->getHeader('X-CLOUDFRAMEWORK-SECURITY'), 4);
                if (!strlen($_id)
                    || !strlen($_zone)
                    || !strlen($_time)
                    || !strlen($_token)
                ) {
                    $this->core->logs->add('_wrong format in X-CLOUDFRAMEWORK-SECURITY.');
                } else {
                    $date = new DateTime(null, new DateTimeZone($_zone));
                    $secs = microtime(true) + $date->getOffset() - $_time;
                    if (!strlen($secret)) {
                        $secArr = $this->core->config->get('CLOUDFRAMEWORK-ID-' . $_id);
                        if (isset($secArr['secret'])) $secret = $secArr['secret'];
                    }


                    if (!strlen($secret)) {
                        $this->core->logs->add('conf-var CLOUDFRAMEWORK-ID-' . $_id . ' missing or it is not a righ CLOUDFRAMEWORK array.');
                    } elseif (!strlen($_time) || !strlen($_token)) {
                        $this->core->logs->add('wrong X-CLOUDFRAMEWORK-SECURITY format.');
                        // We allow an error of 2 min
                    } elseif (false && $secs < -120) {
                        $this->core->logs->add('Bad microtime format. Negative value got: ' . $secs . '. Check the clock of the client side.');
                    } elseif (strlen($id) && $id != $_id) {
                        $this->core->logs->add($_id . ' ID is not allowed');
                    } elseif ($this->getHeader('X-CLOUDFRAMEWORK-SECURITY') != $this->generateCloudFrameWorkSecurityString($_id, $_time, $secret)) {
                        $this->core->logs->add('X-CLOUDFRAMEWORK-SECURITY does not match.');
                    } elseif ($maxSeconds > 0 && $maxSeconds <= $secs) {
                        $this->core->logs->add('Security String has reached maxtime: ' . $maxSeconds . ' seconds');
                    } else {
                        $secArr['SECURITY-ID'] = $_id;
                        $secArr['SECURITY-EXPIRATION'] = ($maxSeconds) ? $maxSeconds - $secs : $maxSeconds;
                        return ($secArr);
                    }
                }
            }
            return false;
        }

        function getCloudFrameWorkSecurityInfo($maxSeconds = 0, $id = '', $secret = '')
        {
            $info = $this->checkCloudFrameWorkSecurity($maxSeconds, $id, $secret);
            if (false === $info) return [];
            else {
                return $this->core->config->get('CLOUDFRAMEWORK-ID-' . $info['SECURITY-ID']);
            }

        }

        // time, has to to be microtime().
        function generateCloudFrameWorkSecurityString($id = '', $time = '', $secret = '')
        {
            if (!strlen($id)) {
                $id = $this->core->config->get('CloudServiceId');
                if (!strlen($id)) {
                    $this->core->errors->add('generateCloudFrameWorkSecurityString has not received $id and CloudServiceId config var does not exist');
                    return false;
                }
            }

            if (!strlen($secret)) {
                $secret = $this->core->config->get('CloudServiceSecret');
                if (!strlen($secret)) {
                    $secArr = $this->core->config->get('CLOUDFRAMEWORK-ID-' . $id);
                    if (isset($secArr['secret'])) $secret = $secArr['secret'];
                    if (!strlen($secret)) {
                        $this->core->errors->add('generateCloudFrameWorkSecurityString has not received $secret and CloudServiceSecret and CLOUDFRAMEWORK-ID-XXX   config vars don\'t not exist');
                        return false;
                    }
                }
            }

            $ret = null;
            if (!strlen($secret)) {
                $secArr = $this->core->config->get('CLOUDFRAMEWORK-ID-' . $id);
                if (isset($secArr['secret'])) $secret = $secArr['secret'];
            }
            if (!strlen($secret)) {
                $this->core->logs->add('conf-var CLOUDFRAMEWORK-ID-' . $id . ' missing.');
            } else {
                if (!strlen($time)) $time = microtime(true);
                $date = new DateTime(null, new DateTimeZone('UTC'));
                $time += $date->getOffset();
                $ret = $id . '__UTC__' . $time;
                $ret .= '__' . hash_hmac('sha1', $ret, $secret);
            }
            return $ret;
        }

        private function createDSToken()
        {
            $dschema = ['token' => ['keyname', 'index']];
            $dschema['dateInsert'] = ['datetime', 'index'];
            $dschema['JSONZIP'] = ['string'];
            $dschema['fingerprint'] = ['string'];
            $dschema['prefix'] = ['string', 'index'];
            $dschema['secondsToExpire'] = ['integer'];
            $dschema['status'] = ['integer','index'];
            $dschema['ip'] = ['string','index'];
            $dschema['User'] = ['string','index|allownull'];
            $spacename = $this->core->config->get('DataStoreSpaceName');
            if (!strlen($spacename)) $spacename = "cloudframework";
            $this->dsToken = $this->core->loadClass('DataStore', ['CloudFrameWorkAuthTokens', $spacename, $dschema]);
            if ($this->dsToken->error) $this->core->errors->add(['setDSToken' => $this->dsToken->errorMsg]);
            return(!$this->dsToken->error);

        }



        /**
         * Just Read a Token from Database
         * @param $token Id generated with setDSToken
         * @return array|mixed    The content contained in DS.JSONZIP
         */
        function getDSTokenInfo($token)
        {

            // Check if object has been created
            if (null === $this->dsToken) if(!$this->createDSToken()) return;

            $retToken = $this->dsToken->fetchByKeys($token);
            if ($this->dsToken->error) {
                $this->core->errors->add(['getDSTokenInfo' => $this->dsToken->errorMsg]);
            }
            return $retToken[0]??null;
        }


        /**
         * Verify a Token with different rules and return the content from field JSONZIP unziping it.
         * @param $token Id generated with setDSToken
         * @param string $prefixStarts
         * @param int $time MAX TIME to expire the token
         * @param string $fingerprint_hash fingerprint_has to use.. if '' we will generate it using: $this->core->system->getRequestFingerPrint()['hash']
         * @param boolean $use_fingerprint_security Says it we are going to apply fingerprint security
         * @return array|mixed    The content contained in DS.JSONZIP
         */
        function getDSToken($token, $prefixStarts = '', $time = 0, $fingerprint_hash='',$use_fingerprint_security=true)
        {
            $this->core->__p->add('getDSToken', $prefixStarts, 'note');
            $ret = null;

            // Check if token starts with $prefix
            if (strlen($prefixStarts) && strpos($token, $prefixStarts) !== 0) {
                $this->core->errors->add(['getDSToken' => 'incorrect prefix token']);
                return $ret;
            }
            // Check if object has been created
            if (null === $this->dsToken) if(!$this->createDSToken()) return;

            $retToken = $this->dsToken->fetchByKeys($token);
            // Allow to rewrite the fingerprint it it is passed
            if (!$this->dsToken->error && !strlen($fingerprint_hash)) $fingerprint_hash = $this->core->system->getRequestFingerPrint()['hash'];

            if ($this->dsToken->error) {
                $this->core->errors->add(['getDSToken' => $this->dsToken->errorMsg]);
            } elseif (!count($retToken)) {
                $this->core->errors->add(['getDSToken' => 'Token not found.']);
            } elseif (!$retToken[0]['status']) {
                $this->core->errors->add(['getDSToken' => 'Token is no longer active.']);
            } elseif ($use_fingerprint_security && $fingerprint_hash != $retToken[0]['fingerprint']) {
                $this->core->errors->add(['getDSToken' => 'Token fingerprint does not match. Security violation.']);
            } elseif ($time > 0 && ((new DateTime())->getTimestamp()) - (new DateTime($retToken[0]['dateInsert']))->getTimestamp() >= $time) {
                $this->core->errors->add(['getDSToken' => 'Token expired']);
            } elseif (isset($retToken[0]['JSONZIP'])) {
                $ret = json_decode($this->uncompress($retToken[0]['JSONZIP']), true);
            }
            $this->core->__p->add('getDSToken', '', 'endnote');
            return $ret;
        }

        /**
         * Delete the entity with KeyName=$token
         * @param $token
         * @return array|bool|void If the deletion is right it return the array with the recored deleted
         */
        function deleteDSToken($token)
        {
            $ret = null;

            // Check if object has been created
            if (null === $this->dsToken) if(!$this->createDSToken()) return;

            // Return deleted records
            return($this->dsToken->deleteByKeys($token));
        }

        /**
         * Update the token data.
         * @param $token
         * @return array|bool|void If the deletion is right it return the array with the recored deleted
         */
        function updateDSToken($token,$data)
        {
            // Check if object has been created
            if (null === $this->dsToken) if(!$this->createDSToken()) return;

            $retToken = $this->dsToken->fetchByKeys($token);

            if(count($retToken)) {
                $retToken[0]['JSONZIP'] = $this->compress(json_encode($data));
                $ret = $this->dsToken->createEntities($retToken[0]);
                if(count($ret)) {
                    return(json_decode($this->uncompress($ret[0]['JSONZIP']), true));
                }
            }
            return [];

        }

        /**
         * Change the status = 0
         * It also removes the token from the validation cache of CoreUser::loadPlatformUserWithToken(), so the
         * APIs of this app reject it in the next request instead of accepting it until the cache expires.
         * @param $token
         * @return array|bool|void If the deletion is right it return the array with the recored deleted
         */
        function deactivateDSToken($token)
        {
            //region DELETE $token from the validation cache even if the token entity does not exist
            if(is_string($token) && $token) $this->core->user->deleteTokenFromCache($token);
            //endregion

            // Check if object has been created
            if (null === $this->dsToken) if(!$this->createDSToken()) return;
            $retToken = $this->dsToken->fetchByKeys($token);
            if(($retToken ?? false) && is_array($retToken) && count($retToken )) {
                $retToken[0]['status'] = 0;
                $ret = $this->dsToken->createEntities($retToken[0]);
                if(($ret ?? false) && is_array($ret) && count($ret)) {
                    return(true);
                }
            }
            return false;
        }

        function setDSToken($data, $prefix = '', $fingerprint_hash = '',$time_expiration=0)
        {
            $ret = null;
            if (!strlen(trim($prefix))) $prefix = 'default';

            // Check if object has been created
            if (null === $this->dsToken) if(!$this->createDSToken()) return;

            // If not error continue
            if (!$this->core->errors->lines) {
                if (!strlen($fingerprint_hash))
                    $fingerprint_hash = $this->core->system->getRequestFingerPrint()['hash'];
                $record['dateInsert'] = "now";
                $record['fingerprint'] = $fingerprint_hash;
                $record['JSONZIP'] = $this->compress(json_encode($data));
                $record['prefix'] = $prefix;
                $record['secondsToExpire'] = $time_expiration;
                $record['status'] = 1;
                $record['User'] = $data['KeyName']??null;
                $record['ip'] = $this->core->system->ip;
                $record['token'] = $this->core->config->get('DataStoreSpaceName') . '__' . $prefix . '__' . sha1(json_encode($record) . date('Ymdhis'));

                $retEntity = $this->dsToken->createEntities($record);
                if ($this->dsToken->error) {
                    $this->core->errors->add(['setDSToken' => $this->dsToken->errorMsg]);
                } else {
                    $ret = $retEntity[0]['KeyName'];
                }
            }
            return $ret;

        }

        function compress($data) {
            if(!is_string($data)) return $data;
            return base64_encode(gzcompress($data));
        }

        function uncompress($data) {
            return gzuncompress(base64_decode($data));
        }

        /**
         * method to encrypt a plain text string
         *
         * Formats:
         *   - v1 (default in 8.5.x): AES-256-CBC with a static IV derived from $secret_iv, base64 output without prefix.
         *     based on: https://gist.github.com/joashp/a1ae9cb30fa533f4ad94
         *   - v2: AES-256-GCM, see encryptV2(). Written when the config-var core.security.encrypt_format = 'v2' or when
         *     core.security.encrypt_require_key is active (see isEncryptStrictMode()).
         *   - v3 (since 8.5.27): Cloud KMS, 'v3:<alias>:<version>:<base64 of the KMS ciphertext>', see encryptV3(). Written
         *     ONLY when the call passes no key at all ($secret_key and $secret_iv empty) and a primary data key is
         *     configured (core.gcp.kms.data_key_primary or the env var CF_KMS_DATA_KEY_PRIMARY). It wins over
         *     encrypt_format. It fails closed: when Cloud KMS fails it returns false, never a v1/v2 value.
         * decrypt() reads every format, so v2 or v3 can be activated without breaking stored v1/v2 values.
         *
         * When $secret_key or $secret_iv are empty they are resolved with resolveEncryptKeys():
         * config-vars core.security.encrypt_key / core.security.encrypt_secret -> env vars CF_ENCRYPT_KEY /
         * CF_ENCRYPT_SECRET (getEnvVar). Since 8.5.25 there is no built-in default: without both keys it returns false
         * and logs the error 'security-encrypt-key-missing' once per request. The legacy keys
         * (core.security.legacy_encrypt_key / legacy_encrypt_secret) are never used to encrypt.
         *
         * @param string $text: string to encrypt
         * @param string $secret_key  optional secret key. If empty it will be resolved with resolveEncryptKeys()
         * @param string $secret_iv  optional secret key. If empty it will be resolved with resolveEncryptKeys()
         * @param string $usage since 8.5.27, optional: purpose of the value, [a-z0-9-]{1,40}. In v3 it is bound to the
         *                      ciphertext (AAD 'cloudframework:<usage>', 'cloudframework:default' when empty) and the same
         *                      usage is required to decrypt it. Ignored by v1/v2. An invalid usage returns false.
         * @return string|false in base64 ('v2:' / 'v3:' prefixed when that format is active); false when the keys are
         *                      missing, the usage is invalid or Cloud KMS fails (v3)
         */
        function encrypt($text,$secret_key='',$secret_iv='',string $usage='') {

            if(!$text) return $text;

            if(!$this->validEncryptUsage($usage)) return false;

            // since 8.5.27: v3 (Cloud KMS) only when no key is passed and a primary data key is configured
            if(!$secret_key && !$secret_iv && $this->kmsPrimaryAlias()!=='')
                return $this->encryptV3((string)$text,$usage);

            if(!$secret_key || !$secret_iv)
                if(!$this->resolveEncryptKeys($secret_key,$secret_iv)) return false;

            // v2 is opt-in (core.security.encrypt_format), and always written with core.security.encrypt_require_key
            if($this->isEncryptStrictMode() || $this->core->config->get('core.security.encrypt_format')==='v2')
                return $this->encryptV2($text,$secret_key,$secret_iv);

            $encrypt_method = "AES-256-CBC";

            // hash
            $key = hash('sha256', $secret_key);

            // iv - encrypt method AES-256-CBC expects 16 bytes - else you will get a warning
            $iv = substr(hash('sha256', $secret_iv), 0, 16);

            return openssl_encrypt($text, $encrypt_method, $key, 0, $iv);

        }

        /**
         * Decrypt Encrypted Text produced by encrypt() in any of its formats:
         *   - 'v3:' prefix (since 8.5.27): Cloud KMS (see decryptV3()). The alias must be in core.gcp.kms.data_keys (or
         *     CF_KMS_DATA_KEYS) and $usage must be the one used to encrypt it. It never uses keys: a call that passes
         *     keys explicitly (CoreCache) gets false without calling Cloud KMS.
         *   - 'v2:' prefix: AES-256-GCM (see decryptV2()). Returns false if it can not be authenticated. Without explicit
         *     keys it needs the configured keys (resolveEncryptKeys()); never the legacy keys.
         *   - no prefix: legacy v1 AES-256-CBC, unchanged. A note 'legacy-cbc-format-read' is added once per request.
         *
         * v1 without explicit keys (since 8.5.25, there is no built-in default):
         *   1. the configured keys (resolveEncryptKeys() order), when both are available: the v1 values that encrypt()
         *      wrote with them;
         *   2. when they are missing or can not read it, the legacy keys, only when they are configured explicitly:
         *      config-vars core.security.legacy_encrypt_key / core.security.legacy_encrypt_secret -> env vars
         *      CF_LEGACY_ENCRYPT_KEY / CF_LEGACY_ENCRYPT_SECRET (getEnvVar, same order and same reload of the env_vars
         *      secret as the configured keys). A value read with them leaves the warning 'security-legacy-key-read'
         *      once per request: encrypt it again to store it with the configured keys.
         *   3. without the legacy keys it returns false and logs the error 'security-legacy-key-missing' once per request.
         * With both keys passed explicitly (CoreCache) only those keys are used, as before.
         *
         * @param string $encrypted_text base64 string
         * @param string $secret_key  optional secret key. If empty it will be resolved with resolveEncryptKeys()
         * @param string $secret_iv  optional secret key. If empty it will be resolved with resolveEncryptKeys()
         * @param string $usage since 8.5.27, optional: the usage given to encrypt() ([a-z0-9-]{1,40}); only v3 checks it
         * @return string|false
         */
        function decrypt($encrypted_text,$secret_key='',$secret_iv='',string $usage='') {

            if(!$encrypted_text) return $encrypted_text;

            if(!$this->validEncryptUsage($usage)) return false;

            if(is_string($encrypted_text) && strncmp($encrypted_text,'v3:',3)===0) {
                if($secret_key || $secret_iv) {
                    $this->sendNoteOnce('security-kms-explicit-keys'
                        ,'security-kms-explicit-keys: a v3 value was given to decrypt() with explicit keys. v3 values are decrypted only with Cloud KMS and without keys. Nothing was decrypted'
                        ,'warning');
                    return false;
                }
                return $this->decryptV3($encrypted_text,$usage);
            }

            $resolved_keys = (!$secret_key || !$secret_iv);

            if(is_string($encrypted_text) && strncmp($encrypted_text,'v2:',3)===0) {
                if($resolved_keys)
                    if(!$this->resolveEncryptKeys($secret_key,$secret_iv)) return false;
                return $this->decryptV2($encrypted_text,$secret_key,$secret_iv);
            }

            $this->sendNoteOnce('legacy-cbc-format-read','legacy-cbc-format-read');

            if(!$resolved_keys) return $this->decryptV1($encrypted_text,$secret_key,$secret_iv);

            // 1. configured keys (each value independently, never mixed with a legacy one)
            $plain = false;
            if(!$this->fillEncryptKeyPair($secret_key,$secret_iv))
                $plain = $this->decryptV1($encrypted_text,$secret_key,$secret_iv);
            if($plain!==false) return $plain;

            // 2. legacy keys, only when they are configured explicitly. Only to read, never to write.
            $legacy_key = ''; $legacy_secret = '';
            $missing = $this->fillEncryptKeyPair($legacy_key,$legacy_secret,true);
            if($missing) {
                $this->sendNoteOnce('security-legacy-key-missing'
                    ,'security-legacy-key-missing ['.implode(',',$missing).']: a v1 value could not be read with the configured keys and core.security.legacy_encrypt_key/legacy_encrypt_secret or the env vars CF_LEGACY_ENCRYPT_KEY/CF_LEGACY_ENCRYPT_SECRET are not available. Nothing was decrypted'
                    ,'error');
                return false;
            }
            $plain = $this->decryptV1($encrypted_text,$legacy_key,$legacy_secret);
            if($plain!==false)
                $this->sendNoteOnce('security-legacy-key-read'
                    ,'security-legacy-key-read: a v1 value was read with the legacy keys. Encrypt it again to store it with the configured keys'
                    ,'warning');

            return $plain;
        }

        /**
         * Legacy v1 decryption: AES-256-CBC with a static IV derived from $secret_iv.
         * @param string $encrypted_text base64 string
         * @param string $secret_key
         * @param string $secret_iv
         * @return string|false
         */
        private function decryptV1($encrypted_text,$secret_key,$secret_iv) {

            $encrypt_method = "AES-256-CBC";

            // hash
            $key = hash('sha256', $secret_key);

            // iv - encrypt method AES-256-CBC expects 16 bytes - else you will get a warning
            $iv = substr(hash('sha256', $secret_iv), 0, 16);

            return openssl_decrypt($encrypted_text, $encrypt_method, $key, 0, $iv);
        }

        /**
         * Encrypt $text with the v2 format: AES-256-GCM, random 12-byte IV and 16-byte tag.
         * Output: 'v2:' . base64(iv . tag . ciphertext).
         * The 32-byte key is derived with HKDF-SHA256 from $secret_key . "\0" . $secret_iv (info
         * 'cloudframework-encrypt-v2'), so $secret_iv keeps adding entropy to the key although it is not the IV.
         *
         * @param string $text string to encrypt
         * @param string $secret_key optional. If empty it will be resolved with resolveEncryptKeys()
         * @param string $secret_iv optional. If empty it will be resolved with resolveEncryptKeys()
         * @return string|false
         */
        function encryptV2($text,$secret_key='',$secret_iv='') {

            if(!$text) return $text;

            if(!$secret_key || !$secret_iv)
                if(!$this->resolveEncryptKeys($secret_key,$secret_iv)) return false;

            $iv = random_bytes(12);
            $tag = '';
            $cipher_text = openssl_encrypt((string)$text,'aes-256-gcm',$this->deriveEncryptV2Key($secret_key,$secret_iv),OPENSSL_RAW_DATA,$iv,$tag,'',16);
            if($cipher_text===false || strlen($tag)!==16) return false;

            return 'v2:'.base64_encode($iv.$tag.$cipher_text);
        }

        /**
         * Decrypt a text produced by encryptV2(). Any failure (missing prefix, corrupted base64, length < 28 bytes,
         * wrong key or tampered data/tag) returns false.
         *
         * @param string $encrypted_text 'v2:' prefixed text
         * @param string $secret_key optional. If empty it will be resolved with resolveEncryptKeys()
         * @param string $secret_iv optional. If empty it will be resolved with resolveEncryptKeys()
         * @return string|false
         */
        function decryptV2($encrypted_text,$secret_key='',$secret_iv='') {

            if(!is_string($encrypted_text) || strncmp($encrypted_text,'v2:',3)!==0) return false;

            if(!$secret_key || !$secret_iv)
                if(!$this->resolveEncryptKeys($secret_key,$secret_iv)) return false;

            $raw = base64_decode(substr($encrypted_text,3),true);
            if($raw===false || strlen($raw)<28) return false;

            $plain = openssl_decrypt(substr($raw,28),'aes-256-gcm',$this->deriveEncryptV2Key($secret_key,$secret_iv),OPENSSL_RAW_DATA,substr($raw,0,12),substr($raw,12,16));
            return ($plain===false)?false:$plain;
        }

        /**
         * Since 8.5.27: encrypts $text with the primary Cloud KMS data key. Output: 'v3:<alias>:<N>:<base64>' where
         * <alias> is core.gcp.kms.data_key_primary, <N> the number of the key version that encrypted it (from the
         * response of Cloud KMS, never read from the key metadata) and <base64> the ciphertext of Cloud KMS. The AAD is
         * 'cloudframework:<usage>' ('cloudframework:default' when $usage is empty).
         *
         * Fails closed: a configuration error (security-kms-config-invalid), a plaintext larger than 64 KiB
         * (security-kms-plaintext-too-large) or a failure of Cloud KMS after its retry (security-kms-unavailable) return
         * false with an error note once per request. It never falls back to v1/v2.
         *
         * @param string $text
         * @param string $usage already validated
         * @return string|false
         */
        private function encryptV3(string $text, string $usage): string|false
        {
            $alias = $this->kmsPrimaryAlias();
            $resource = $this->kmsKeyResource($alias);
            if(!$resource) {
                $this->sendNoteOnce('security-kms-config-invalid'
                    ,'security-kms-config-invalid: core.gcp.kms.data_key_primary (or CF_KMS_DATA_KEY_PRIMARY) must be an alias [a-z0-9]{1,8} present in core.gcp.kms.data_keys (or CF_KMS_DATA_KEYS) with a key resource projects/{project}/locations/{location}/keyRings/{ring}/cryptoKeys/{key}. Nothing was encrypted'
                    ,'error');
                return false;
            }
            if(!$kms = $this->getKMS()) return false;
            if(strlen($text)>$kms::MAX_PLAINTEXT_BYTES) {
                $this->sendNoteOnce('security-kms-plaintext-too-large'
                    ,'security-kms-plaintext-too-large: v3 values are limited to '.$kms::MAX_PLAINTEXT_BYTES.' bytes (Cloud KMS). Nothing was encrypted'
                    ,'error');
                return false;
            }
            $res = $kms->encrypt($text,self::KMS_AAD_PREFIX.(($usage!=='')?$usage:'default'),$resource);
            if($res===false) {
                $this->kmsFailureNote($kms,'encrypt');
                return false;
            }
            return 'v3:'.$alias.':'.$res['version'].':'.base64_encode($res['ciphertext']);
        }

        /**
         * Since 8.5.27: decrypts a 'v3:<alias>:<N>:<base64>' value with the Cloud KMS key of <alias> and the AAD of $usage.
         * An unknown alias returns false without calling Cloud KMS (security-kms-unknown-alias), a malformed value too
         * (security-kms-format-invalid). A wrong usage or a corrupted value is rejected by Cloud KMS
         * (security-kms-decrypt-failed); any other failure after its retry is security-kms-unavailable.
         * @param string $value
         * @param string $usage already validated
         * @return string|false
         */
        private function decryptV3(string $value, string $usage): string|false
        {
            $parsed = $this->parseV3($value);
            if(!$parsed) {
                $this->sendNoteOnce('security-kms-format-invalid'
                    ,'security-kms-format-invalid: a value with the v3: prefix is not v3:<alias>:<version>:<base64>. Nothing was decrypted'
                    ,'warning');
                return false;
            }
            $resource = $this->kmsKeyResource($parsed['alias']);
            if(!$resource) {
                $this->sendNoteOnce('security-kms-unknown-alias'
                    ,'security-kms-unknown-alias: the alias '.$parsed['alias'].' of a v3 value is not in core.gcp.kms.data_keys (or CF_KMS_DATA_KEYS). Nothing was decrypted'
                    ,'error');
                return false;
            }
            if(!$kms = $this->getKMS()) return false;
            $plain = $kms->decrypt($parsed['ciphertext'],self::KMS_AAD_PREFIX.(($usage!=='')?$usage:'default'),$resource);
            if($plain===false) {
                $this->kmsFailureNote($kms,'decrypt');
                return false;
            }
            return $plain;
        }

        /**
         * Since 8.5.27: the encryption format of a value, without decrypting it and without any network call.
         *   - 'v3:<alias>:<N>' for a well formed v3 value (the alias does not need to be configured);
         *   - 'v2' for 'v2:' + base64 of at least 28 bytes;
         *   - 'v1' for base64 without prefix whose length is a multiple of 16 bytes (AES-256-CBC);
         *   - null for anything else (empty, not a string, malformed v2/v3, plain text).
         * @param mixed $value
         * @return string|null
         */
        public function encryptionVersionOf($value): ?string
        {
            if(!is_string($value) || $value==='') return null;
            if(strncmp($value,'v3:',3)===0) {
                $parsed = $this->parseV3($value);
                return ($parsed)?'v3:'.$parsed['alias'].':'.$parsed['version']:null;
            }
            if(strncmp($value,'v2:',3)===0) {
                $raw = base64_decode(substr($value,3),true);
                return ($raw!==false && strlen($raw)>=28)?'v2':null;
            }
            if(!preg_match('#^[A-Za-z0-9+/]+={0,2}$#',$value) || strlen($value)%4!==0) return null;
            $raw = base64_decode($value,true);
            return ($raw!==false && $raw!=='' && strlen($raw)%16===0)?'v1':null;
        }

        /**
         * Since 8.5.27: brings a stored value to the primary Cloud KMS data key.
         *   - a v3 value of the primary alias is returned unchanged (the primary version of the key in Cloud KMS is never
         *     read: Cloud KMS rotates the versions behind the same key and decrypts every enabled version);
         *   - any other value (v1, v2 or v3 of another alias) is decrypted with $usage and encrypted again with the
         *     primary key (v3).
         * It returns false, and never invents a value, when there is no primary key configured
         * (security-kms-not-configured), the value is not recognised, it can not be decrypted or the new encryption fails.
         * @param mixed $value
         * @param string $usage the usage of the value ([a-z0-9-]{1,40}); the same is used to encrypt it again
         * @return string|false
         */
        public function reEncrypt($value, string $usage=''): string|false
        {
            if(!$this->validEncryptUsage($usage)) return false;
            $primary = $this->kmsPrimaryAlias();
            if($primary==='') {
                $this->sendNoteOnce('security-kms-not-configured'
                    ,'security-kms-not-configured: reEncrypt() needs core.gcp.kms.data_key_primary (or CF_KMS_DATA_KEY_PRIMARY). Nothing was re-encrypted'
                    ,'error');
                return false;
            }
            $version = $this->encryptionVersionOf($value);
            if($version===null) return false;
            if(strncmp($version,'v3:'.$primary.':',strlen('v3:'.$primary.':'))===0) return $value;

            $plain = $this->decrypt($value,'','',$usage);
            if($plain===false || $plain==='' || $plain===null) return false;
            $encrypted = $this->encrypt($plain,'','',$usage);
            $plain = null;
            if(!is_string($encrypted) || strncmp((string)$this->encryptionVersionOf($encrypted),'v3:'.$primary.':',strlen('v3:'.$primary.':'))!==0) return false;
            return $encrypted;
        }

        /**
         * Since 8.5.27: alias of the primary Cloud KMS data key: config-var core.gcp.kms.data_key_primary, then the env var
         * CF_KMS_DATA_KEY_PRIMARY (getenv() only: it is not a secret and it is read on every encrypt()). '' when none.
         * @return string
         */
        private function kmsPrimaryAlias(): string
        {
            $alias = $this->core->config->get('core.gcp.kms.data_key_primary');
            if(!is_string($alias) || trim($alias)==='') $alias = getenv('CF_KMS_DATA_KEY_PRIMARY');
            return (is_string($alias))?trim($alias):'';
        }

        /**
         * Since 8.5.27: Cloud KMS data keys {alias: key resource}: config-var core.gcp.kms.data_keys (an object, or its
         * JSON as a string), then the env var CF_KMS_DATA_KEYS (JSON, getenv() only). Only the entries with a valid alias
         * ([a-z0-9]{1,8}) and a valid resource (projects/{p}/locations/{l}/keyRings/{r}/cryptoKeys/{k}) are returned.
         * @return array
         */
        private function kmsDataKeys(): array
        {
            $keys = $this->core->config->get('core.gcp.kms.data_keys');
            if(is_string($keys) && trim($keys)!=='') $keys = json_decode($keys,true);
            if(!is_array($keys) || !$keys) {
                $env = getenv('CF_KMS_DATA_KEYS');
                $keys = (is_string($env) && trim($env)!=='')?json_decode($env,true):[];
            }
            if(!is_array($keys)) return [];
            $valid = [];
            foreach ($keys as $alias=>$resource) {
                if(is_string($alias) && preg_match('/^[a-z0-9]{1,8}$/',$alias) && is_string($resource)
                    && preg_match('#^projects/[^/\s]+/locations/[^/\s]+/keyRings/[^/\s]+/cryptoKeys/[^/\s]+$#',$resource))
                    $valid[$alias] = $resource;
            }
            return $valid;
        }

        /**
         * Since 8.5.27: key resource of a Cloud KMS data key alias, or null when the alias is not valid or not configured.
         * @param string $alias
         * @return string|null
         */
        private function kmsKeyResource(string $alias): ?string
        {
            if(!preg_match('/^[a-z0-9]{1,8}$/',$alias)) return null;
            return $this->kmsDataKeys()[$alias] ?? null;
        }

        /**
         * Since 8.5.27: parses 'v3:<alias>:<N>:<base64>'.
         * @param string $value
         * @return array|null ['alias'=>string,'version'=>string,'ciphertext'=>binary string]
         */
        private function parseV3(string $value): ?array
        {
            if(!preg_match('#^v3:([a-z0-9]{1,8}):([1-9][0-9]{0,9}):([A-Za-z0-9+/]+={0,2})$#',$value,$m)) return null;
            $ciphertext = base64_decode($m[3],true);
            if($ciphertext===false || $ciphertext==='') return null;
            return ['alias'=>$m[1],'version'=>$m[2],'ciphertext'=>$ciphertext];
        }

        /**
         * Since 8.5.27: the usage of encrypt()/decrypt()/reEncrypt(): '' or [a-z0-9-]{1,40}. An invalid one leaves the
         * error security-encrypt-usage-invalid once per request (never with the value) and returns false.
         * @param string $usage
         * @return bool
         */
        private function validEncryptUsage(string $usage): bool
        {
            if($usage==='' || preg_match('/^[a-z0-9-]{1,40}$/',$usage)) return true;
            $this->sendNoteOnce('security-encrypt-usage-invalid'
                ,'security-encrypt-usage-invalid: the usage of encrypt()/decrypt() must be [a-z0-9-]{1,40}. Nothing was encrypted or decrypted'
                ,'error');
            return false;
        }

        /**
         * Since 8.5.27: the GoogleKMS of this request, created the first time it is needed (Application Default
         * Credentials of the runtime). Tests inject the client with GoogleKMS::$client_factory.
         * @return GoogleKMS|null
         */
        private function getKMS(): ?GoogleKMS
        {
            if($this->kms) return $this->kms;
            if(!class_exists('GoogleKMS')) include_once __DIR__.'/class/GoogleKMS.php';
            if(!class_exists('GoogleKMS')) {
                $this->sendNoteOnce('security-kms-unavailable','security-kms-unavailable: the class GoogleKMS can not be loaded. Nothing was encrypted or decrypted','error');
                return null;
            }
            return $this->kms = new GoogleKMS($this->core);
        }

        /**
         * Since 8.5.27: the note of a failed Cloud KMS call, once per request and never with any value. A decrypt
         * rejected by Cloud KMS as INVALID_ARGUMENT (wrong usage, corrupted value) is security-kms-decrypt-failed
         * (warning); everything else is security-kms-unavailable (error).
         * @param GoogleKMS $kms
         * @param string $operation encrypt|decrypt
         * @return void
         */
        private function kmsFailureNote(GoogleKMS $kms, string $operation): void
        {
            $detail = $kms->errorCode.(($kms->lastStatus)?' '.$kms->lastStatus:'').' after '.$kms->lastAttempts.' attempt(s)';
            if($operation==='decrypt' && $kms->errorCode==='kms-invalid-argument') {
                $this->sendNoteOnce('security-kms-decrypt-failed'
                    ,'security-kms-decrypt-failed: Cloud KMS rejected a v3 value ('.$detail.'): wrong usage or corrupted value. Nothing was decrypted'
                    ,'warning');
                return;
            }
            $this->sendNoteOnce('security-kms-unavailable'
                ,'security-kms-unavailable: Cloud KMS '.$operation.' failed ('.$detail.'). It fails closed: nothing was '.$operation.'ed and no v1/v2 value was written'
                ,'error');
        }

        /**
         * Binary 32-byte key for the v2 format.
         * @param string $secret_key
         * @param string $secret_iv
         * @return string
         */
        private function deriveEncryptV2Key($secret_key,$secret_iv): string
        {
            return hash_hkdf('sha256',(string)$secret_key."\0".(string)$secret_iv,32,'cloudframework-encrypt-v2');
        }

        /**
         * Fills the empty $secret_key / $secret_iv used by encrypt()/decrypt() when they are called without explicit keys.
         * Order (each value independently), see fillEncryptKeyPair():
         *   1. config-vars core.security.encrypt_key / core.security.encrypt_secret (read on every call, as before)
         *   2. environment variables CF_ENCRYPT_KEY / CF_ENCRYPT_SECRET via $this->core->config->getEnvVar(), that
         *      reads getenv() and then the env_vars secret. Read lazily, only when a key is missing, and memorized
         *      in this instance for the rest of the request.
         * Before failing, the env_vars secret is read again once per request without the cache (see
         * reloadEncryptEnvVars(), since 8.5.14): the cached copy lives per cache namespace and may predate the keys.
         * Since 8.5.25 there is no built-in default, with or without core.security.encrypt_require_key: if a key is still
         * missing the error 'security-encrypt-key-missing' is left once per request and the method returns false, also
         * when the env_vars secret can not be read. Only decrypt() of a v1 value can use the legacy keys (see decrypt()).
         * Calls with explicit keys (CoreCache) never reach this method.
         *
         * @param string $secret_key
         * @param string $secret_iv
         * @return bool false when a key is missing
         */
        private function resolveEncryptKeys(&$secret_key,&$secret_iv): bool
        {
            $missing = $this->fillEncryptKeyPair($secret_key,$secret_iv);
            if(!$missing) return true;

            $this->sendNoteOnce('security-encrypt-key-missing'
                ,'security-encrypt-key-missing ['.implode(',',$missing).']: core.security.encrypt_key/encrypt_secret or the env vars CF_ENCRYPT_KEY/CF_ENCRYPT_SECRET are not available. Nothing was encrypted or decrypted'
                ,'error');
            return false;
        }

        /**
         * Fills the empty values of a pair of encryption keys without logging anything:
         *   - $legacy false: config-vars core.security.encrypt_key / encrypt_secret -> env vars CF_ENCRYPT_KEY / CF_ENCRYPT_SECRET
         *   - $legacy true (since 8.5.25): config-vars core.security.legacy_encrypt_key / legacy_encrypt_secret -> env vars
         *     CF_LEGACY_ENCRYPT_KEY / CF_LEGACY_ENCRYPT_SECRET
         * Each value independently. When a value is still missing the env_vars secret is read again without the cache
         * (reloadEncryptEnvVars(), at most once per request for all the pairs) and the pair is filled again.
         * @param string $secret_key
         * @param string $secret_iv
         * @param bool $legacy
         * @return array the missing values ('key', 'secret'); empty when the pair is complete
         */
        private function fillEncryptKeyPair(&$secret_key,&$secret_iv,bool $legacy=false): array
        {
            [$config_key,$config_secret,$env_key,$env_secret] = $legacy
                ? ['core.security.legacy_encrypt_key','core.security.legacy_encrypt_secret','CF_LEGACY_ENCRYPT_KEY','CF_LEGACY_ENCRYPT_SECRET']
                : ['core.security.encrypt_key','core.security.encrypt_secret','CF_ENCRYPT_KEY','CF_ENCRYPT_SECRET'];

            for($attempt=0;;$attempt++) {
                if(!$secret_key)
                    $secret_key = ($this->core->config->get($config_key))?:$this->getEncryptEnvVar($env_key);
                if(!$secret_iv)
                    $secret_iv = ($this->core->config->get($config_secret))?:$this->getEncryptEnvVar($env_secret);

                $missing = [];
                if(!$secret_key) $missing[] = 'key';
                if(!$secret_iv) $missing[] = 'secret';
                if(!$missing || $attempt>0 || !$this->reloadEncryptEnvVars()) return $missing;
            }
        }

        /**
         * Config-var core.security.encrypt_require_key (true, 1, '1' or 'true').
         * Since 8.5.25 its rule on the keys applies to every application: encrypting or decrypting without configured keys
         * always fails explicitly and there are no built-in default keys. The config-var is still accepted and keeps
         * only its effect on the format: encrypt() writes v2, the same as core.security.encrypt_format = 'v2'.
         * @return bool
         */
        function isEncryptStrictMode(): bool
        {
            return in_array($this->core->config->get('core.security.encrypt_require_key'),[true,1,'1','true'],true);
        }

        /**
         * Since 8.5.14 (strict mode only until 8.5.24, always since 8.5.25): reads the env_vars secret again without the
         * cache when CF_ENCRYPT_KEY / CF_ENCRYPT_SECRET (or, when decrypt() needs them, CF_LEGACY_ENCRYPT_KEY /
         * CF_LEGACY_ENCRYPT_SECRET) are missing. The secret is cached per cache namespace (each platform keeps its own copy, see
         * CoreConfig::readEnvVarsFromGCPSecrets), so a copy cached before the keys were added to the secret lacks them and
         * resetting the cache of one namespace does not refresh the others. The reload also rewrites the copy of the
         * current namespace, so the next requests of that platform read the keys from the cache again.
         * At most once per request; leaves the warning 'security-encrypt-env-vars-reloaded' when it reads the secret.
         * @return bool true when the secret was read again and the memo of CF_ENCRYPT_* / CF_LEGACY_ENCRYPT_* was cleared
         */
        private function reloadEncryptEnvVars(): bool
        {
            if($this->encrypt_env_vars_reloaded || $this->resolving_encrypt_env_var) return false;
            $this->encrypt_env_vars_reloaded = true;

            $secret_id = $this->core->config->get('core.gcp.secrets.env_vars');
            if(!$secret_id) return false;

            $this->resolving_encrypt_env_var = true;
            try {
                $reloaded = $this->core->config->readEnvVarsFromGCPSecrets('','',true);
            } catch (\Throwable $e) {
                $reloaded = false;
            } finally {
                $this->resolving_encrypt_env_var = false;
            }
            if(!$reloaded) return false;

            unset($this->encrypt_env_vars['CF_ENCRYPT_KEY'],$this->encrypt_env_vars['CF_ENCRYPT_SECRET']
                ,$this->encrypt_env_vars['CF_LEGACY_ENCRYPT_KEY'],$this->encrypt_env_vars['CF_LEGACY_ENCRYPT_SECRET']);
            $this->sendNoteOnce('security-encrypt-env-vars-reloaded'
                ,'security-encrypt-env-vars-reloaded: encryption keys were not in the cached env_vars of this cache namespace. The secret '.$secret_id.' was read again without the cache and its cached copy rewritten'
                ,'warning');
            return true;
        }

        /**
         * Lazy and memorized read of an encryption env var with getEnvVar().
         * A re-entrant call (getEnvVar() running) returns '' without memorizing it, so it can never recurse.
         * @param string $var
         * @return string
         */
        private function getEncryptEnvVar(string $var): string
        {
            if(array_key_exists($var,$this->encrypt_env_vars)) return $this->encrypt_env_vars[$var];
            if($this->resolving_encrypt_env_var) return '';

            $this->resolving_encrypt_env_var = true;
            try {
                $value = $this->core->config->getEnvVar($var);
            } finally {
                $this->resolving_encrypt_env_var = false;
            }

            return $this->encrypt_env_vars[$var] = (is_string($value) || is_numeric($value))?(string)$value:'';
        }

        /**
         * It says if the call is being doing by Cron Appengine Service.
         * @return boolean
         */
        function isCron() {
            return(!empty($this->getHeader('X-Appengine-Cron')));
        }

        /**
         * It generates a random unique string.
         * @return string with a length of 32 chars
         */
        public function generateRandomString($pref='')
        {
            if(!abs(intval(32)) ) $length=32;
            if(!$pref) $pref = rand();
            return(base64_encode(md5(uniqid($pref))));
        }

        /**
         * Encode a JSON Web Token (JWT) using the given payload, key, and algorithm.
         *
         * @param mixed $payload The data to be encoded in the JWT. This can be any valid JSON-serializable value.
         * @param string $key The private key used to sign the JWT. It must be a string of at least 10 characters.
         * @param string|null $keyId (optional) The key ID to be included in the JWT header. If not provided, it will be omitted.
         * @param array|null $head (optional) Additional headers to be included in the JWT. If not provided, the default header will be used.
         * @param string $algorithm (optional) The cryptographic algorithm to be used for signing the JWT. The default is 'RS256'.
         *                          Supported algorithms are 'RS256', 'RS384', 'RS512', 'HS256', 'HS384', and 'HS512'.
         *                          If an unsupported algorithm is provided, an error will be returned.
         *
         * @return string|false The encoded JWT as a string. If an error occurs during encoding, false will be returned.
         *
         */
        public function jwt_encode($payload, $key, $keyId = null, $head = null, $algorithm='RS256')
        {
            if(!is_string($key) || strlen($key)< 10) return($this->addError('Wrong private key'));

            $header = array('typ' => 'JWT', 'alg' => $algorithm);
            if ($keyId !== null) {
                $header['kid'] = $keyId;
            }
            if ( isset($head) && is_array($head) ) {
                $header = array_merge($head, $header);
            }

            //region create $signing_input
            $b64SafeHeader = $this->urlsafeB64Encode($this->core->jsonEncode($header));
            $b64SafePayload = $this->urlsafeB64Encode($this->core->jsonEncode($payload));
            $b64SafeSignature='';
            $token='';
            if($this->error) return false;
            //endregion

            //region SET $token SIGNING with different algorithms
            switch (strtoupper($algorithm)) {
                case "RS256":
                case "RS384":
                case "RS512":
                    $signature = '';
                    if(strtoupper($algorithm)=='RS256')
                        $success = openssl_sign($b64SafeHeader.'.'.$b64SafePayload, $signature, $key, 'SHA256');
                    if(strtoupper($algorithm)=='RS384')
                        $success = openssl_sign($b64SafeHeader.'.'.$b64SafePayload, $signature, $key, 'SHA384');
                    if(strtoupper($algorithm)=='RS512')
                        $success = openssl_sign($b64SafeHeader.'.'.$b64SafePayload, $signature, $key, 'SHA512');

                    if(!$success) {
                        return($this->addError(['error'=>true,'errorMsg'=>'OpenSSL unable to sign data']));
                    }
                    $b64SafeSignature=$this->urlsafeB64Encode($signature);
                    if($this->error) return false;
                    $token = $b64SafeHeader.'.'.$b64SafePayload.'.'.$b64SafeSignature;
                    break;
                case "PS256":
                case "PS384":
                case "PS512":
                    $ps_key = openssl_pkey_get_private($key);
                    if (!$ps_key) {
                        return($this->addError(['error'=>true,'errorMsg'=>'openssl_pkey_get_private($key) has returned an error']));
                    }
                    $signature = '';
                    if(strtoupper($algorithm)=='PS256')
                        $success = openssl_sign($b64SafeHeader.'.'.$b64SafePayload, $signature, $ps_key, 'SHA256');
                    if(strtoupper($algorithm)=='PS384')
                        $success = openssl_sign($b64SafeHeader.'.'.$b64SafePayload, $signature, $ps_key, 'SHA384');
                    if(strtoupper($algorithm)=='PS512')
                        $success = openssl_sign($b64SafeHeader.'.'.$b64SafePayload, $signature, $ps_key, 'SHA512');
                    if(!$success) {
                        return($this->addError(['error'=>true,'errorMsg'=>'OpenSSL unable to sign data']));
                    }
                    $b64SafeSignature=$this->urlsafeB64Encode($signature);
                    if($this->error) return false;
                    $token = $b64SafeHeader.'.'.$b64SafePayload.'.'.$b64SafeSignature;
                    break;
                case "HS256":
                case "HS384":
                case "HS512":
                    if(strtoupper($algorithm)=='HS256')
                        $signature = hash_hmac('sha256', $b64SafeHeader . "." . $b64SafePayload, $key, true);
                    elseif(strtoupper($algorithm)=='HS384')
                        $signature = hash_hmac('sha384', $b64SafeHeader . "." . $b64SafePayload, $key, true);
                    elseif(strtoupper($algorithm)=='HS512')
                        $signature = hash_hmac('sha512', $b64SafeHeader . "." . $b64SafePayload, $key, true);
                    $b64SafeSignature=$this->urlsafeB64Encode($signature);
                    if($this->error) return false;
                    $token = $b64SafeHeader.'.'.$b64SafePayload.'.'.$b64SafeSignature;
                    break;
                    $signature = hash_hmac('sha512', $b64SafeHeader . "." . $b64SafePayload, $key, true);
                    $b64SafeSignature=$this->urlsafeB64Encode($signature);
                    if($this->error) return false;
                    $token = $b64SafeHeader.'.'.$b64SafePayload.'.'.$b64SafeSignature;
                    break;
                default:
                    return($this->addError(['error'=>true,'errorMsg'=>"Algorithm [{$algorithm}] is not supported"]));
                    break;
            }
            //endregion

            //region return $token
            return $token;
            //endregion
        }

        /**
         * Decode a JSON Web Token (JWT)
         *
         * @param string $jwt The JWT string to decode
         * @param string|null $key (optional) The public key/secret used to verify the token signature
         * @param string|null (optional) $keyId The Key ID (KID) present in the token header
         * @param string $algorithm (optional) The cryptographic algorithm to be used for signing the JWT. The default is 'RS256'.
         * *                          Supported algorithms are 'RS256', 'RS384', 'RS512', 'HS256', 'HS384', and 'HS512'.
         * *                          If an unsupported algorithm is provided, an error will be returned.
         * *
         *
         * @return array|false The decoded token as an associative array if successful, false otherwise
         */
        public function jwt_decode($jwt, $key=null, $keyId=null, $algorithm=null)
        {

            $tks = explode('.', $jwt);
            if (count($tks) != 3) {
                return($this->addError('Wrong number of segments in $token'));
            }

            list($headb64, $bodyb64, $cryptob64) = $tks;

            if (null === ($header = $this->core->jsonDecode($this->urlsafeB64Decode($headb64)))) {
                return($this->addError('Invalid header encoding'));
            }
            if (null === $payload = $this->core->jsonDecode($this->urlsafeB64Decode($bodyb64))) {
                return($this->addError('Invalid claims encoding'));
            }
            if (false === ($sig = $this->urlsafeB64Decode($cryptob64))) {
                return($this->addError('Invalid signature encoding'));
            }
            if (array_key_exists('kid',$header) && $keyId && $header['kid']!=$keyId) {
                return($this->addError('KeyId present in header and does not match with $keyId'));
            }

            //region create $signature signing with the privateKey
            if($key) {

                if(!$alg = ($header['alg']??null))
                    return($this->addError('Missing alg from token header'));
                if($algorithm && $algorithm!=$alg)
                    return($this->addError('Token header algorithm does not match with $algorithm value: '.$algorithm));

                switch (strtoupper($alg)) {

                    case "RS256":
                    case "RS384":
                    case "RS512":
                        if(!is_string($key) || strlen($key)< 10) return($this->addError('Wrong public key'));
                        if(strtoupper($alg)=='RS256')
                            $success = openssl_verify("$headb64.$bodyb64",$sig, $key, 'SHA256');
                        elseif(strtoupper($alg)=='RS384')
                            $success = openssl_verify("$headb64.$bodyb64",$sig, $key, 'SHA384');
                        elseif(strtoupper($alg)=='RS512')
                            $success = openssl_verify("$headb64.$bodyb64",$sig, $key, 'SHA512');
                        if($success!==1) {
                            $error_msg ='';
                            while($error=openssl_error_string())
                                $error_msg.=$error;
                            $this->addError(['error'=>true,'errorMsg'=>'OpenSSL verification failed. '.$error_msg]);
                        }
                        break;

                    case "PS256":
                    case "PS384":
                    case "PS512":
                        if(!is_string($key) || strlen($key)< 10) return($this->addError('Wrong public key'));
                        $ps_key = openssl_pkey_get_public($key);
                        if (!$ps_key) {
                            return($this->addError(['error'=>true,'errorMsg'=>'openssl_pkey_get_private($key) has returned an error']));
                        }
                        if(strtoupper($alg)=='PS256')
                            $success = openssl_verify("$headb64.$bodyb64",$sig, $ps_key, 'SHA256');
                        elseif(strtoupper($alg)=='PS384')
                            $success = openssl_verify("$headb64.$bodyb64",$sig, $ps_key, 'SHA384');
                        elseif(strtoupper($alg)=='PS512')
                            $success = openssl_verify("$headb64.$bodyb64",$sig, $ps_key, 'SHA512');
                        if($success!==1) {
                            $error_msg ='';
                            while($error=openssl_error_string())
                                $error_msg.=$error;
                            $this->addError(['error'=>true,'errorMsg'=>'OpenSSL verification failed. '.$error_msg]);
                        }
                        break;

                    case "HS256":
                    case "HS384":
                    case "HS512":
                        $sig = $this->urlsafeB64Decode($cryptob64);
                        if(strtoupper($alg)=='HS256')
                            $expectedSignature = hash_hmac('sha256', $headb64 . '.' . $bodyb64, $key, true);
                        elseif(strtoupper($alg)=='HS384')
                            $expectedSignature = hash_hmac('sha384', $headb64 . '.' . $bodyb64, $key, true);
                        elseif(strtoupper($alg)=='HS512')
                            $expectedSignature = hash_hmac('sha512', $headb64 . '.' . $bodyb64, $key, true);
                        if(!hash_equals($sig, $expectedSignature)) {
                            return $this->addError("token signature [{$alg}] does not match with token \$secret");
                        }
                        break;


                    case "PS256":
                    case "PS384":
                    case "PS512":
                        if(!is_string($key) || strlen($key)< 10) return($this->addError('Wrong public key'));
                        if(strtoupper($alg)=='RS256')
                            $success = openssl_verify("$headb64.$bodyb64",$sig, $key, 'SHA256');
                        elseif(strtoupper($alg)=='RS384')
                            $success = openssl_verify("$headb64.$bodyb64",$sig, $key, 'SHA384');
                        elseif(strtoupper($alg)=='RS512')
                            $success = openssl_verify("$headb64.$bodyb64",$sig, $key, 'SHA512');
                        if($success!==1) {
                            $error_msg ='';
                            while($error=openssl_error_string())
                                $error_msg.=$error;
                            $this->addError(['error'=>true,'errorMsg'=>'OpenSSL verification failed. '.$error_msg]);
                        }
                        break;

                    default:
                        return($this->addError(['error'=>true,'errorMsg'=>"Algorithm [{$alg}] is not supported"]));
                        break;

                }
            }
            //endregion
            return(['header'=>$header,'body'=>$payload,'signature'=>$cryptob64]);
        }

        /**
         * Call https://api.clouframework.io/core/api-keys to verify an APIKey
         * More info in: https://www.notion.so/cloudframework/CloudFrameworkSecurity-APIKeys-CFS-APIKeys-13b47034a6f14f23b836c1f4238da548
         *
         * @param string $token  token of the entity of CloudFrameWorkAPIKeys
         * @param string $key   key of the APIKey to evaluate if it exists
         * @param string $namespace spacename of the data. Default cloudframework.
         * @param string $org organization of the entity inside of the spacename. Default common
         * @return bool[]|false[]|mixed|string[]|void
         */
        public function checkAPIKey($token, $key, $namespace='cloudframework', $org='common') {

            //Generate hash and evaluate return cached data
            $hash = md5($token.$key.$namespace.$org);
            if($data = $this->getCache($hash)) return $data;

            // Call CloudFrameWorkAPIKeys Service
            $url = 'https://api.cloudframework.io/core/api-keys/'.$namespace.'/'.$org;
            $ret = $this->core->request->get_json_decode($url,null,['X-WEB-KEY'=>$key,'X-DS-TOKEN'=>$token]);
            if($this->core->request->error) {
                $this->addError(['checkAPIKey'=>$this->core->request->errorMsg]);
                $this->core->request->reset();
                return;
            }
            $this->core->logs->add('CloudFrameworkSecurity APIKeys service used');

            //Update Cache
            $this->updateCache($hash,$ret['data']);

            // Return data
            return $ret['data'];
        }

        /**
         * Reads and retrieves cached data for the specified security group.
         * If no cache exists for the given security group and space name, it initializes the cache.
         *
         * @param string $security_group The security group to retrieve cached data for. Defaults to 'default'.
         * @return array The cached data for the specified security group and space name.
         */
        public function readCache($security_group = 'default') {
            if(!isset($this->cache[$security_group][$this->core->cache->spacename])) {
                $this->cache[$security_group][$this->core->cache->spacename] = ($this->cacheKeysAvailable())
                    ? (($this->core->cache->get('Core7.CoreSecurity.'.$security_group,3600*24,null,$this->cache_key,$this->cache_iv))?:[])
                    : [];
            }
        }

        /**
         * Resets the cache specifically for ERP secret variables by invoking
         * the platform-level secret variable cache reset mechanism.
         *
         * @deprecated
         * @return void
         */
        public function resetCacheForERPSecretVars() {
            $this->resetCacheForPlatformSecretVars();
        }

        /**
         * Resets the cache for platform secret variables.
         * This method clears the cached data related to platform secret variables
         * by invoking a cache reset for a specific cache identifier.
         *
         * @return void
         */
        public function resetCacheForPlatformSecretVars() {
            $this->resetCache('ERP.secrets');
        }

        /**
         * Resets the cache for a given security group.
         * This method clears the cache for the specified security group and updates
         * the corresponding cache entry in the system cache.
         *
         * @param string $security_group The security group for which the cache is being reset. Defaults to 'default'.
         * @return void
         */
        public function resetCache($security_group = 'default') {
            $this->cache[$security_group][$this->core->cache->spacename] = [];
            if($this->cacheKeysAvailable())
                $this->core->cache->set('Core7.CoreSecurity.'.$security_group,$this->cache[$security_group][$this->core->cache->spacename],null,$this->cache_key,$this->cache_iv);
            $this->core->logs->add('CoreSecurity.resetCache(\''.$security_group.'\') from '.$this->core->system->url['host_url_uri'],'CoreSecurity');
        }

        /**
         * Updates the cache with the given data under the specified variable name and security group.
         * If no security group is provided, 'default' will be used.
         *
         * @param string $var The variable name under which the data will be stored.
         * @param mixed $data The data to be stored in the cache.
         * @param string $security_group Optional. The security group within which the cache will be updated. Defaults to 'default'.
         * @return void
         */
        public function updateCache($var,$data,$security_group = 'default') {
            if(!$security_group) $security_group = 'default';
            $this->readCache($security_group);
            $this->cache[$security_group][$this->core->cache->spacename][$var] = $data;
            if($this->cacheKeysAvailable())
                $this->core->cache->set('Core7.CoreSecurity.'.$security_group,$this->cache[$security_group][$this->core->cache->spacename],null,$this->cache_key,$this->cache_iv);

        }

        /**
         * Retrieves the cached value or the entire cache structure for a specific variable and security group.
         *
         * @param string $var The identifier of the cache variable to retrieve. If not provided, the entire cache structure is returned.
         * @param string $security_group The security group under which the cache is stored. Defaults to 'default' if not specified.
         * @return mixed|null The cached value for the specified variable or the entire cache structure within the security group. Returns null if no value is found.
         */
        public function getCache(string $var='',$security_group = 'default') {
            if(!$security_group) $security_group = 'default';
            $this->readCache($security_group);
            if($var) return $this->cache[$security_group][$this->core->cache->spacename][$var] ?? null;
            else return $this->cache[$security_group][$this->core->cache->spacename] ?? null;
        }


        /**
         * Encodes the given input into a URL-safe Base64 format.
         * This method modifies the standard Base64 encoding by replacing certain characters to make the string URL-safe.
         *
         * @param string $input The input string to be encoded.
         * @return string The URL-safe Base64 encoded version of the input.
         */
        public function urlsafeB64Encode($input) {
            return str_replace('=', '', strtr(base64_encode($input), '+/', '-_'));
        }

        /**
         * Decodes a URL-safe Base64 encoded string.
         * This method ensures that the input string is padded correctly and converts
         * URL-safe Base64 characters back to standard Base64 characters before decoding.
         *
         * @param string $input The URL-safe Base64 encoded string to decode.
         * @return string|false The decoded string, or false on failure.
         */
        public function urlsafeB64Decode($input)
        {
            $remainder = strlen($input) % 4;
            if ($remainder) {
                $padlen = 4 - $remainder;
                $input .= str_repeat('=', $padlen);
            }
            return base64_decode(strtr($input, '-_', '+/'));
        }

        /**
         * Check if myip is allowed in the pattern described in $allowed_ips
         * Example: $allowed_ips="127.0.0.1,234.45.23.123,10.0.0.0/24"
         * @param string $allowed_ips  Here You describe the IPs or subnets separated by ','. '*' value means any IP
         * @param null $myip  Optional. By default it is $this->core->system->ip
         * @return boolean
         *
         * An empty $allowed_ips allows any IP, as before. Since 8.5.28 the comparison is ipInList(): IPv6 and a single
         * CIDR range (without ',') are supported; until 8.5.27 a single range never matched and IPv6 ranges failed.
         */
        public function checkAllowedIPs($allowed_ips,$myip=null) {
            if(!$myip) $myip=$this->core->system->ip;
            if(!$allowed_ips) return true;
            return self::ipInList((string)$myip, $allowed_ips);
        }

        /**
         * Add an error Message
         * @param $value
         * @return false to facilitate the return of other functions
         */
        function addError($value)
        {
            $this->error = true;
            $this->errorMsg[] = $value;
            return false;
        }

        /**
         * Resets the error state of the object.
         * Clears any stored error messages and sets the error flag to false.
         *
         * @return bool Returns true after resetting the error state.
         */
        function resetError()
        {
            $this->error = false;
            $this->errorMsg = [];
            return true;
        }

        /**
         * One-way string hashing (passwords, recovery codes, token secrets).
         *
         * Since 8.5.27: password_hash() with bcrypt and a cost of at least PASSWORD_BCRYPT_MIN_COST (12). Up to 8.5.26
         * it was crypt() with '$2a$' and a cost of 7. The output is still a 60-character bcrypt string ('$2y$12$...'),
         * verified with checkCrypt() (also by the versions before 8.5.27), and the hashes written before keep verifying:
         * use needsRehash() after a successful checkCrypt() to store them again with the current policy.
         *
         * $rounds is kept for compatibility: it is the bcrypt cost, with a minimum of 12 (a lower value, including the
         * old default 7, is raised to 12) and a maximum of 31.
         *
         * Input longer than 72 bytes: bcrypt only uses the first 72 bytes, so two inputs that share them would get the
         * same hash. It is rejected explicitly, as before 8.5.27: it returns null and leaves the error
         * security-password-too-long once per request (never with the value). A NUL byte (bcrypt can not hash it) also
         * returns null (security-password-invalid).
         *
         * @param string|array $input The string or array to hash. An array is converted to string with json_encode
         * @param int $rounds bcrypt cost, minimum 12
         * @return string|null null when the input is empty, longer than 72 bytes or not valid for bcrypt
         */
        function crypt($input, $rounds = self::PASSWORD_BCRYPT_MIN_COST)
        {
            if($input && !is_string($input)) $input = json_encode($input);
            if(!$input) return null;
            if(strlen($input)>72) {
                // This method only admits strings up to 72 bytes (bcrypt)
                $this->sendNoteOnce('security-password-too-long'
                    ,'security-password-too-long: crypt()/checkCrypt() admit up to 72 bytes (bcrypt). The value was rejected','error');
                return null;
            }
            $cost = min(31,max(self::PASSWORD_BCRYPT_MIN_COST,(int)$rounds));
            try {
                $hash = password_hash($input,PASSWORD_BCRYPT,['cost'=>$cost]);
            } catch (\ValueError $e) {
                $this->sendNoteOnce('security-password-invalid'
                    ,'security-password-invalid: crypt() can not hash a value with a NUL byte (bcrypt). The value was rejected','error');
                return null;
            }
            return (is_string($hash))?$hash:null;
        }

        /**
         * Verify a string with its hash, in constant time (password_verify()).
         *
         * Since 8.5.27 it uses password_verify(), which accepts every hash of crypt(): the bcrypt '$2y$' of crypt() since
         * 8.5.27 and the '$2a$' (cost 7) that crypt() wrote up to 8.5.26. Input longer than 72 bytes returns false and
         * leaves the error security-password-too-long once per request (see crypt()).
         *
         * @param string|array $input  original input
         * @param string|null $input_encrypted Hash to compare. If null or empty is provided it will try to get the value from $this->core->config->get("core.security.password")
         * @return bool
         */
        function checkCrypt($input, $input_encrypted=null): bool
        {
            if($input && !is_string($input)) $input = json_encode($input);
            if(!$input) return false;
            if(strlen($input)>72) {
                // This method only admits strings up to 72 bytes (bcrypt)
                $this->sendNoteOnce('security-password-too-long'
                    ,'security-password-too-long: crypt()/checkCrypt() admit up to 72 bytes (bcrypt). The value was rejected','error');
                return false;
            }

            // IF $input_encrypted empty try to get it from core.security.password config var
            if(!$input_encrypted) $input_encrypted = $this->core->config->get("core.security.password");

            if(!$input_encrypted || !is_string($input_encrypted)) return false;
            return password_verify($input,$input_encrypted);
        }

        /**
         * Since 8.5.27: whether a hash of crypt() should be computed again with the current policy (bcrypt '$2y$' with a
         * cost of at least PASSWORD_BCRYPT_MIN_COST). True for the '$2a$' cost 7 hashes written up to 8.5.26 and for any
         * other algorithm; false for a '$2y$' bcrypt with cost >= 12 (a higher cost is not lowered). Call it after a
         * successful checkCrypt(), when the plain value is at hand, and store crypt($input) when it says true.
         * @param string $hash
         * @return bool
         */
        public function needsRehash(string $hash): bool
        {
            $info = password_get_info($hash);
            if(($info['algo']??null)!==PASSWORD_BCRYPT) return true;
            return ((int)($info['options']['cost']??0))<self::PASSWORD_BCRYPT_MIN_COST;
        }

        /**
         * Replace Accented characters to avoid Key troubles
         * source: https://stackoverflow.com/questions/3371697/replacing-accented-characters-php
         * @param string|array $input  original input encrypted
         * @return string|array
         */
        function replaceAccentedCharacters($input)
        {
            $unwanted_array = array('Š'=>'S', 'š'=>'s', 'Ž'=>'Z', 'ž'=>'z', 'À'=>'A', 'Á'=>'A', 'Â'=>'A', 'Ã'=>'A', 'Ä'=>'A', 'Å'=>'A', 'Æ'=>'A', 'Ç'=>'C', 'È'=>'E', 'É'=>'E',
                'Ê'=>'E', 'Ë'=>'E', 'Ì'=>'I', 'Í'=>'I', 'Î'=>'I', 'Ï'=>'I', 'Ñ'=>'N', 'Ò'=>'O', 'Ó'=>'O', 'Ô'=>'O', 'Õ'=>'O', 'Ö'=>'O', 'Ø'=>'O', 'Ù'=>'U',
                'Ú'=>'U', 'Û'=>'U', 'Ü'=>'U', 'Ý'=>'Y', 'Þ'=>'B', 'ß'=>'Ss', 'à'=>'a', 'á'=>'a', 'â'=>'a', 'ã'=>'a', 'ä'=>'a', 'å'=>'a', 'æ'=>'a', 'ç'=>'c',
                'è'=>'e', 'é'=>'e', 'ê'=>'e', 'ë'=>'e', 'ì'=>'i', 'í'=>'i', 'î'=>'i', 'ï'=>'i', 'ð'=>'o', 'ñ'=>'n', 'ò'=>'o', 'ó'=>'o', 'ô'=>'o', 'õ'=>'o',
                'ö'=>'o', 'ø'=>'o', 'ù'=>'u', 'ú'=>'u', 'û'=>'u', 'ý'=>'y', 'þ'=>'b', 'ÿ'=>'y',
                'Ğ'=>'G', 'İ'=>'I', 'Ş'=>'S', 'ğ'=>'g', 'ı'=>'i', 'ş'=>'s', 'ü'=>'u',
                'ă'=>'a', 'Ă'=>'A', 'ș'=>'s', 'Ș'=>'S', 'ț'=>'t', 'Ț'=>'T');
            if(is_array($input)) {
                foreach ($input as $i=>$item) if(is_string($item)){
                    $input[$i] =  strtr($item,$unwanted_array);
                }
                return $input;
            } else {
                return strtr($input,$unwanted_array);
            }
        }
    }

    /**
     * $this->core->cache Class to manage Cache in your solutions.
     * https://cloud.google.com/appengine/docs/standard/php7/using-memorystore#setup_redis_db
     * @package Core.cache
     */
    class CoreCache
    {
        var $cache = null;
        var $spacename = 'CloudFrameWork';
        var $type = 'memory'; // memory, redis, datastore, directory,
        var $dir = '';
        var $error = false;
        var $errorMsg = [];
        var $debug = false;
        var $lastHash = null;
        var $lastExpireTime = null;
        var $atom = null;
        var $errorSecurity = false; // It will change to true when you try to get a value using a wrong $cache_secret_key,$cache_secret_iv
        /** @var bool $previous_keys_noted since 8.5.27: the note security-cache-previous-key-read was already left */
        private $previous_keys_noted = false;
        /** @var Core  */
        var $core=null;

        /**
         * CoreCache constructor. If $type==CacheInDirectory a writable $path is required
         * @param string $spacename
         * @param string $path if != null the it assumes the cache will be store in files
         */
        function __construct(Core7 &$core, $spacename = '',  $path=null, $debug = null)
        {
            $this->core = $core;

            // Initialize $this->spacename
            if (!strlen(trim($spacename))) $spacename = (isset($_SERVER['HTTP_HOST'])) ? $_SERVER['HTTP_HOST'] : $_SERVER['PWD'];
            $this->setNameSpace($spacename);

            // Activate debug based on $debug or if I am in development (local environgmnet)
            if(null !== $debug)
                $this->debug = true === $debug;
            // If we are in localhost then activate
            else
                if($this->core->is->development()) {
                    if(!$path && $this->core->config->get('core.cache.cache_path')) $path = $this->core->config->get('core.cache.cache_path');
                    $this->debug = true;
                }

            // Activate CacheInDirectory
            if (null !== $path) {
                $this->activateCacheFile($path);
            }
        }

        /**
         * Activate cache in memory
         * @return bool
         */
        function activateMemory()
        {
            $this->cache = null;
            $this->type = 'memory';
            $this->init();
            return true;
        }

        /**
         * Activate cache in files.. It requires that the path will be writtable
         * @param string $path dir path to keep files.
         * @param string $spacename
         * @return bool
         */
        function activateCacheFile($path, $spacename = '')
        {
            // Avoid to activate the same Cache Path
            if($this->dir == $path && is_object($this->cache) ) return true;

            // since 8.5.23: when mkdir() fails because another process created the folder in between, the folder is valid
            // (before, that process was left with the in-memory cache)
            if (isset($_SESSION['Core_CacheFile_' . $path]) || is_dir($path) || @mkdir($path) || $this->cacheDirCreatedMeanwhile($path)) {
                $this->type = 'directory';
                $this->dir = $path;
                if (strlen($spacename)) $spacename = '_' . $spacename;
                $this->setSpaceName(basename($path) . $spacename);
                $this->cache = null;
                $this->init();

                // Save in session to improve the performance for buckets because is_dir has a high cost.
                if(isset($_SESSION))
                    $_SESSION['Core_CacheFile_' . $path] = true;
                return true;
            } else {
                $this->addError($path . ' does not exist and can not be created');
                return false;
            }
        }

        /**
         * Since 8.5.23: called only when mkdir() of the cache folder has failed. Another process may have created it
         * between is_dir() and mkdir(): the stat cache of $path is cleared and the folder is checked again.
         * @param string $path
         * @return bool true when the folder exists now
         */
        private function cacheDirCreatedMeanwhile($path): bool
        {
            clearstatcache(true,$path);
            return is_dir($path);

        }

        /**
         * Activate cache in Datastore. The cache will be stored in ds:CloudFrameworkCache
         * @param Core7 $core Core class to facilitate errors
         * @param string $spacename spacename where to store objecs
         * @return bool
         */
        function activateDataStore( $spacename = '')
        {
            $this->type = 'datastore';
            if($spacename) $this->spacename = $spacename;
            $this->cache = null;
            $this->init();
            if($this->error) return false;
            else return true;
        }

        /**
         * Initialiated Cache Memory object.. If previously it has been called it just returns true.. if there is an error it returns false..
         * https://cloud.google.com/appengine/docs/standard/php7/php-differences
         * App Engine Memcache support is not provided. Instead, use Memorystore for Redis.
         * https://cloud.google.com/appengine/docs/standard/php7/using-memorystore
         * @return bool
         */
        function init()
        {
            if(null !== $this->cache) return(is_object($this->cache));

            if($this->debug)
                $this->core->logs->add("init(). type: {$this->type}",'CoreCache');

            if ($this->type == 'memory') {
                if (!getenv('REDIS_HOST') || !getenv('REDIS_PORT')) {
                    if($this->debug)
                        $this->core->logs->add("init(). Failed because REDIS_HOST and REDIS_PORT env_vars does not exist.",'CoreCache','warning');
                    $this->cache=-1;
                    return;
                } else {
                    $host = getenv('REDIS_HOST');
                    $port = getenv('REDIS_PORT');
                    try {
                        $this->cache = new Redis();
                        $this->cache->connect($host, $port);
                    } catch (Exception $e) {
                        $this->core->logs->add("init(). REDIS connection failed because: ". $e->getMessage(),'CoreCache','warning');
                        unset($this->cache);
                        $this->cache=-1;
                        return;
                    }

                }
            } elseif($this->type=='datastore') {
                $this->cache = new CoreCacheDataStore($this->core,$this->spacename);
                if($this->cache->error) $this->addError(['CoreCacheDataStore'=>$this->cache->errorMsg]);
            } elseif($this->type=='directory' && $this->dir) {
                $this->cache = new CoreCacheFile($this->dir);
            }

            return(is_object($this->cache));
        }


        /**
         * Set a $spacename to set/get $objects
         * @deprecated use setNameSpace. It will no longer exist in 8.2.xx
         * @param string $name
         */
        function setSpaceName(string $name) {
            $this->setNameSpace($name);
        }

        /**
         * Set a $spacename to set/get $objects
         * @param string $name
         */
        function setNameSpace(string $name)
        {
            if (strlen($name??'')) {
                $name = '_' . trim($name);
                $this->spacename = preg_replace('/[^A-z_-]/', '_', 'CloudFrameWork_' . $this->type . $name);
            }
        }
        /**
         * Return the keys stored in the cache under $this->spacename
         * @param $search [optional] default '*' allow to searcg for a pattern
         * @return array|null if there is some error it will return null else the array of keys found
         */
        public function keys($search='*')
        {
            $keys = [];
            try {
                if($keys = $this->cache->keys($this->spacename . '-' .$search)) {
                    foreach ($keys as $i=>$key) {
                        $keys[$i] = str_replace($this->spacename . '-' ,'',$key);
                    }
                }
            }  catch (Exception $e) {
                $keys = null;
                $this->addError($e->getMessage());
            }
            return $keys;
        }

        /**
         * Return an object from Cache.
         * @param $key
         * @param int $expireTime The default value es -1. If you want to expire, you can use a value in seconds.
         * @param string $hash if != '' evaluate if the $hash match with hash stored in cache.. If not, delete the cache and return false;
         * @param string $cache_secret_key  optional secret key. If not empty it will encrypt the data in cache
         * @param string $cache_secret_iv  optional secret key. If not empty it will encrypt the data in cache
         * @return bool|mixed|null
         */
        function get($key, $expireTime = -1, $hash = '',$cache_secret_key='',$cache_secret_iv='')
        {
            if(!$this->init() || !strlen(trim($key))) return null;

            // Performance microtime
            $time = microtime(true);

            //region SET __p performance paramater
            if (!strlen($expireTime)) $expireTime = -1;
            $encrypted = ($cache_secret_key && $cache_secret_iv)?'/encrypted':'/no-encrypted';
            $encrypted.='/exp:'.$expireTime;
            $encrypted.='/hash:'.(($hash)?'/with-hash':'/no-hash');
            $this->core->__p->add("CoreCache.get [{$this->type}{$encrypted}]", $key, 'note');
            //endregion


            $info = $this->cache->get($this->spacename . '-' . $key)?:'';
            if (strlen($info) && $info !== null) {

                $info = unserialize($info);
                $this->lastExpireTime = microtime(true) - $info['_microtime_'];
                $this->lastHash = $info['_hash_'];

                // Expire Caché
                if ($expireTime >= 0 && microtime(true) - $info['_microtime_'] >= $expireTime) {
                    $this->delete( $key);
                    if($this->debug)
                        $this->core->logs->add("get('\$key=$key',$expireTime=\$expireTime) failed (because expiration)",'CoreCache');
                    $this->core->__p->add("CoreCache.get [{$this->type}{$encrypted}]", '', 'endnote');
                    return null;
                }
                // Hash Cache
                if ('' != $hash && $hash != $info['_hash_']) {
                    $this->delete( $key);
                    if($this->debug)
                        $this->core->logs->add("get('$key',$expireTime,'\$hash') failed (because hash does not match) token: ".$this->spacename . '-' . $key.' [hash='.$this->lastHash.',since='.round($this->lastExpireTime,2).' ms.]','CoreCache');

                    $this->core->__p->add("CoreCache.get [{$this->type}{$encrypted}]", '', 'endnote');
                    return null;
                }
                // Normal return

                // decrypt data if $cache_secret_key and $cache_secret_iv are not empty
                $encrypted_data = null;
                if($cache_secret_key && $cache_secret_iv) {
                    $this->errorSecurity = false;
                    $encrypted_data = $info['_data_'] ?? null;
                    $info['_data_'] = $this->core->security->decrypt($info['_data_'],$cache_secret_key,$cache_secret_iv);
                }

                // unserialize vars
                $ret = null;
                try {
                    if(isset($info['_data_']) && $info['_data_'])
                        $ret = @unserialize(@gzuncompress($info['_data_']));

                    // since 8.5.27: an entry that the current cache keys can not read is tried with the previous cache keys
                    // (rotation without cuts, see CoreSecurity::previousCacheKeysFor()). It is returned and never deleted
                    // nor rewritten: its age (expiration) stays the one it had.
                    if($encrypted_data && ($ret===false || !$info['_data_'])) {
                        $previous_value = $this->getWithPreviousCacheKeys($encrypted_data,$cache_secret_key,$cache_secret_iv);
                        if($previous_value!==false) {
                            if($this->debug)
                                $this->core->logs->add("get(\$key=$key) read with the previous cache keys in namespace ".$this->spacename,'CoreCache');
                            $this->core->__p->add("CoreCache.get [{$this->type}{$encrypted}]", '', 'endnote');
                            return $previous_value;
                        }
                    }

                    if(isset($info['_data_']) && $info['_data_']) {
                        if($ret===false && $cache_secret_key && $cache_secret_iv) {
                            $this->delete( $key);
                            $this->errorSecurity = true;
                            if($this->debug)
                                $this->core->logs->add("get('$key',$expireTime,'$hash',\$cache_secret_key or \$cache_secret_iv). Wrong \$cache_secret_key or \$cache_secret_iv. Cache key has been deleted because security",'CoreCache');
                            $this->core->__p->add("CoreCache.get [{$this->type}{$encrypted}]", '', 'endnote');
                            return null;
                        }
                    }
                } catch (Exception $e) {
                    $ret = null;
                }

                if($this->debug)
                    $this->core->logs->add("get(\$key=$key,\$expireTime=$expireTime,\$hash,\$cache_secret_key, \$cache_secret_iv). successful returned in namespace ".$this->spacename . ' [time='.(round(microtime(true)-$time,4)).' secs]','CoreCache');


                $this->core->__p->add("CoreCache.get [{$this->type}{$encrypted}]", '', 'endnote');
                return $ret;

            } else {
                if($this->debug) $this->core->logs->add("get(\$key=$key) failed (because it does not exist)",'CoreCache');
                $this->core->__p->add("CoreCache.get [{$this->type}{$encrypted}]", 'error', 'endnote');
                return null;
            }
        }

        /**
         * Since 8.5.27: reads an encrypted entry with the previous cache keys mapped by
         * CoreSecurity::previousCacheKeysFor(). Only for the current cache keys of CoreSecurity; false when there are no
         * previous keys or they can not read it either. One note per instance when an entry is read this way.
         * @param string $encrypted_data
         * @param string $cache_secret_key
         * @param string $cache_secret_iv
         * @return mixed|false
         */
        private function getWithPreviousCacheKeys($encrypted_data, string $cache_secret_key, string $cache_secret_iv)
        {
            if(!is_object($this->core->security) || !method_exists($this->core->security,'previousCacheKeysFor')) return false;
            $previous = $this->core->security->previousCacheKeysFor($cache_secret_key,$cache_secret_iv);
            if(!$previous) return false;
            $plain = $this->core->security->decrypt($encrypted_data,$previous[0],$previous[1]);
            $value = ($plain)?@unserialize(@gzuncompress($plain)):false;
            $plain = null;
            if($value===false) return false;
            $this->errorSecurity = false;
            if(!$this->previous_keys_noted) {
                $this->previous_keys_noted = true;
                $this->core->logs->add('CoreCache: security-cache-previous-key-read: an entry was read with the previous cache keys. It is kept until it expires or is written again with the current keys');
            }
            return $value;
        }

        /**
         * Set an object on cache based on $key
         * @param $key
         * @param mixed $object
         * @param string $hash Allow to set the info based in a hash to determine if it is valid when read it.
         * @param string $cache_secret_key  optional secret key. If not empty it will encrypt the data en cache
         * @param string $cache_secret_iv  optional secret key. If not empty it will encrypt the data en cache
         * @return bool
         */
        function set($key, $object, $hash=null, $cache_secret_key='',$cache_secret_iv='')
        {
            $encrypt = ($cache_secret_key && $cache_secret_iv)?'/encrypt':'/no-encrypt';
            if(!$this->init() || !strlen(trim($key))) return null;
            $this->core->__p->add("CoreCache.set [{$this->type}{$encrypt}]", $key, 'note');

            $info['_microtime_'] = microtime(true);
            $info['_hash_'] = $hash;
            $info['_data_'] = gzcompress(serialize($object));

            // encrypt data if $cache_secret_key and $cache_secret_iv are not empty
            if($cache_secret_key && $cache_secret_iv) $info['_data_'] = $this->core->security->encrypt($info['_data_'],$cache_secret_key,$cache_secret_iv);

            $this->cache->set($this->spacename . '-' . $key, serialize($info));
            // If exists a property error in the class checkit
            if(isset($this->cache->error) && $this->cache->error) {
                $this->error = true;
                $this->errorMsg = $this->cache->errorMsg;
            }

            if($this->debug)
                $this->core->logs->add("set(\$key={$key},..)".(($hash)?' with $hash,':'').(($cache_secret_key && $cache_secret_iv)?' with $cache_secret_key and $cache_secret_iv':''),'CoreCache');

            unset($info);
            $this->core->__p->add("CoreCache.set [{$this->type}{$encrypt}]", '', 'endnote');
            return ($this->error)?false:true;
        }

        /**
         * delete a $key from cache
         * @param $key
         * @return true|null
         */
        function delete($key)
        {
            if(!$this->init() || !strlen(trim($key))) return null;

            if (!strlen(trim($key))) return false;
            if($this->type=='memory')
                $this->cache->del($this->spacename . '-' . $key);
            else
                $this->cache->delete($this->spacename . '-' . $key);
            if($this->debug)
                $this->core->logs->add("delete(). token: ".$this->spacename . '-' . $key,'CoreCache');

            return true;
        }


        /**
         * Return a cache based in a hash previously assigned in set
         * @param $str
         * @param $hash
         * @return bool|mixed|null
         */
        public function getByHash($str, $hash) { return $this->get($str,-1, $hash); }


        /**
         * Return a cache based in the Expiration time = TimeToSave + $seconds
         * @param string $str
         * @param int $seconds
         * @return bool|mixed|null
         */
        public function getByExpireTime($str, $seconds) { return $this->get($str,$seconds); }

        /**
         * Set error in the class
         * @param $value
         */
        function addError($value)
        {
            $this->error = true;
            $this->errorMsg[] = $value;
        }

    }

    /**
     * Class to implement cache over local files
     * @package Core.cache
     */
    class CoreCacheFile
    {

        var $dir = '';

        function __construct($dir = '')
        {
            if (strlen($dir)) $this->dir = $dir . '/';
        }

        function set($path, $data)
        {
            $path = preg_replace('/[\/\.;]/','_',$path);
            return @file_put_contents($this->dir . $path, gzcompress(serialize($data)));
        }

        function delete($path)
        {
            $path = preg_replace('/[\/\.;]/','_',$path);
            if(is_file($this->dir . $path))
                return @unlink($this->dir . $path);
        }
        function keys($pattern)
        {
            try {
                if($files = scandir($this->dir)) {
                    array_shift($files);
                    array_shift($files);
                    foreach ($files as $i=>$file) {
                        $files[$i] = preg_replace('/^[^-]*\-/','',$file);
                    }
                }

            } catch (Exception $e) {
                $files = $e->getMessage();
            }
            return $files;
        }


        function get($path)
        {
            $ret = false;
            $path = preg_replace('/[\/\.;]/','_',$path);
            if (is_file($this->dir . $path))
                $ret = file_get_contents($this->dir . $path);
            if (false === $ret) return null;
            else return unserialize(gzuncompress($ret));
        }
    }

    /**
     * Class to manage Cache in DataStore
     * @package Core.cache
     */
    class CoreCacheDataStore
    {

        /** @var DataStore  */
        var $ds = null;
        /** @var Core7 */
        var $core;
        var $error = false;
        var $errorMsg = [];
        var $spacename = 'CloudFramework';

        function __construct(Core7 &$core, $spacename)
        {
            $this->spacename = $spacename;
            $this->core = $core;
            $entity = 'CloudFrameworkCache';
            $model = json_decode('{
                                    "KeyName": ["keyname","index|minlength:4"],
                                    "Fingerprint": ["json","internal"],
                                    "DateUpdating": ["datetime","index|forcevalue:now"],
                                    "Serialize": ["string"]
                                  }',true);
            $this->ds = $core->loadClass('DataStore',[$entity,$this->spacename,$model]);
            if ($this->ds->error) return($this->addError($this->ds->errorMsg));

        }

        function set($path, $data)
        {
            $entity = ['KeyName'=>$path
                ,'Fingerprint'=>$this->core->system->getRequestFingerPrint()
                ,'DateUpdating'=>"now"
                ,'Serialize'=>mb_convert_encoding(gzcompress(serialize($data)), 'UTF-8','ISO-8859-1')];

            $ret = $this->ds->createEntities([$entity]);
            if($this->ds->error) {
                $this->errorMsg = ['DataStore'=>$this->ds->errorMsg];
                $this->error = true;
                return false;
            }
            return true;
        }


        function delete($path)
        {
            $this->ds->deleteByKeys([$path]);
            if($this->ds->error) $this->addError($this->ds->errorMsg);
            if($this->error) return false;
            else return true;
        }

        function get($path)
        {
            $data = $this->ds->fetchOneByKey($path);
            if($this->ds->error) return($this->addError($this->ds->errorMsg));
            if(!$data) return null;
            else return unserialize(gzuncompress($this->core->utf8Decode($data['Serialize'])));
        }

        function addError($value)
        {
            $this->error = true;
            $this->errorMsg[] = $value;
        }
    }

    /**
     * $this->core->request Object is used to handle http requests
     * @package Core.request
     */
    class CoreRequest
    {
        /** @ignore */
        protected $version = '20250410';
        /** @ignore */
        protected $core;
        /** @ignore */
        protected $http;
        /** @ignore */
        public $responseHeaders;
        /** @var bool $error it allow to know if the last call has returned an error. $this->core->request->error */
        public $error = false;
        /** @var array $errorMsg It contains the last errors. $this->core->request->errorMsg */
        public $errorMsg = [];
        /** @ignore */
        public $options = null;
        /** @ignore */
        var $rawResult = '';
        /** @ignore */
        var $automaticHeaders = true; // Add automatically the following headers if exist on config: X-CLOUDFRAMEWORK-SECURITY, X-SERVER-KEY, X-SERVER-KEY, X-DS-TOKEN,X-EXTRA-INFO
        /** @ignore */
        var $sendSysLogs = true;
        /** @ignore TLS certificates are verified by default since 8.5.15 (see getSSLOptions()) */
        var $default_options = array('ssl' => array('verify_peer' => true,'verify_peer_name' => true),'http'=>['protocol_version'=>'1.1','ignore_errors'=>'1','follow_location'=>true]);
        /** @ignore */
        var $cookies = [];
        /** @ignore SSL context parameters accepted by addSSLParameter() and $extra_headers['ssl']: https://www.php.net/manual/en/context.ssl.php */
        const SSL_PARAMETERS = ['peer_name','verify_peer','verify_peer_name','allow_self_signed','cafile','capath','local_cert','local_pk','passphrase'
            ,'verify_depth','ciphers','capture_peer_cert','capture_peer_cert_chain','SNI_enabled','disable_compression','peer_fingerprint','security_level'];
        /** @ignore hosts already reported with security-tls-verify-disabled in this request */
        private $tls_notes_sent = [];

        /**
         * Class constructor
         * @ignore
         * @param Core7 $core
         */
        function __construct(Core7 &$core)
        {
            $this->core = $core;
            if (!$this->core->config->get("CloudServiceUrl"))
                $this->core->config->set("CloudServiceUrl", 'https://api7.cloudframework.io');
        }


        /**
         * Add an SSL parameter to the default options array for SSL configuration in de request calls
         * It only will allow to add in $this->>default_options the parameters defined in
         * https://www.php.net/manual/en/context.ssl.php
         *
         * @param string $parameter The name of the SSL parameter to add
         * @param mixed $value The value of the SSL parameter
         * @return boolean True if the SSL parameter was successfully added, otherwise false
         */
        public function addSSLParameter(string $parameter, $value): ?bool
        {
            if (!in_array($parameter, self::SSL_PARAMETERS)) return $this->addError("parameter [{$parameter}] is not supported in ssl");

            $this->default_options['ssl'][$parameter] = $value;
            return true;

        }

        /**
         * Returns the SSL options of one call, without modifying $this->default_options.
         *
         * Order, each one overriding the previous: $this->default_options['ssl'] (certificates verified, plus what
         * addSSLParameter() set for the request) → configuration → $call_ssl ($extra_headers['ssl'] of the call).
         * Configuration: 'core.request.ssl.cafile' / 'core.request.ssl.capath' set the CA bundle when addSSLParameter()
         * did not, and 'core.request.ssl_verify' === false (strictly the boolean) turns the verification off, for
         * example in a local environment with its own certificate.
         * When the verification ends up off, a warning 'security-tls-verify-disabled' with the host (never the full URL)
         * is logged once per host and request.
         *
         * @param string $route URL of the call (only its host is logged)
         * @param array|null $call_ssl SSL parameters of this call only
         * @return array|false SSL options, or false when $call_ssl has a parameter not supported
         */
        public function getSSLOptions(string $route, ?array $call_ssl = null): array|false
        {
            $ssl = $this->default_options['ssl'] ?? [];
            foreach (['cafile','capath'] as $parameter) {
                if(!isset($ssl[$parameter]) && ($value = $this->core->config->get('core.request.ssl.'.$parameter)))
                    $ssl[$parameter] = $value;
            }
            if($this->core->config->get('core.request.ssl_verify') === false) {
                $ssl['verify_peer'] = false;
                $ssl['verify_peer_name'] = false;
            }
            foreach ($call_ssl ?? [] as $parameter => $value) {
                if (!in_array($parameter, self::SSL_PARAMETERS)) return $this->addError("parameter [{$parameter}] is not supported in ssl");
                $ssl[$parameter] = $value;
            }

            // PHP casts these context values to bool: 0, '0' or '' turn the verification off as false does
            $disabled = [];
            if(!($ssl['verify_peer'] ?? true)) $disabled[] = 'verify_peer';
            if(!($ssl['verify_peer_name'] ?? true)) $disabled[] = 'verify_peer_name';
            if(!empty($ssl['allow_self_signed'])) $disabled[] = 'allow_self_signed';
            if($disabled) {
                $host = parse_url($route, PHP_URL_HOST) ?: 'unknown-host';
                if(!isset($this->tls_notes_sent[$host])) {
                    $this->tls_notes_sent[$host] = true;
                    $this->core->logs->add('security-tls-verify-disabled ['.implode(',',$disabled).'] for host '.$host.': the TLS certificate of this host is not fully verified','CoreRequest','warning');
                }
            }
            return $ssl;
        }

        /**
         * Translates SSL context options into cURL options for getCurl().
         * allow_self_signed, peer_name, peer_fingerprint and the rest of the parameters have no cURL equivalent here and are
         * not applied: the verification stays as verify_peer / verify_peer_name say.
         * @param array $ssl SSL options returned by getSSLOptions()
         * @return array cURL options
         */
        private function getCurlSSLOptions(array $ssl): array
        {
            $curl_options = [
                CURLOPT_SSL_VERIFYPEER => (bool)($ssl['verify_peer'] ?? true),
                CURLOPT_SSL_VERIFYHOST => ($ssl['verify_peer_name'] ?? true) ? 2 : 0,
            ];
            if(!empty($ssl['cafile'])) $curl_options[CURLOPT_CAINFO] = $ssl['cafile'];
            if(!empty($ssl['capath'])) $curl_options[CURLOPT_CAPATH] = $ssl['capath'];
            if(!empty($ssl['local_cert'])) $curl_options[CURLOPT_SSLCERT] = $ssl['local_cert'];
            if(!empty($ssl['local_pk'])) $curl_options[CURLOPT_SSLKEY] = $ssl['local_pk'];
            if(isset($ssl['passphrase']) && strlen($ssl['passphrase'])) $curl_options[CURLOPT_KEYPASSWD] = $ssl['passphrase'];
            if(!empty($ssl['ciphers'])) $curl_options[CURLOPT_SSL_CIPHER_LIST] = $ssl['ciphers'];
            return $curl_options;
        }

        /**
         * Adds an explicit error when a call failed because the TLS certificate of the host could not be verified
         * @param string $route URL of the call (only its host is reported)
         * @param string $detail system or cURL error message
         */
        private function addTLSVerifyError(string $route, string $detail): void
        {
            $host = parse_url($route, PHP_URL_HOST) ?: 'unknown-host';
            $this->addError('tls-verify-failed: the TLS certificate of host '.$host.' could not be verified ('.$this->logUrl($detail).'). Fix the certificate or the CA bundle (core.request.ssl.cafile); disable the verification only explicitly with $extra_headers[\'ssl\']');
        }

        /**
         * Returns the URL to write in logs, performance notes and errors: the value of every sensitive query parameter
         * (CoreSystem::getSensitiveParams(): code, state, token, access_token, id_token, password, key, secret and the
         * names added with core.log.sensitive_params) replaced by '***'. The call itself always uses the real URL.
         * Since 8.5.19: before, an outgoing call like userinfo?access_token=... reached the application log in clear.
         *
         * @param string|null $url URL of the call, or a message that may contain it (system or exception messages)
         * @return string|null
         */
        private function logUrl(?string $url): ?string
        {
            if(is_object($this->core->system ?? null)) return $this->core->system->maskUrl($url);
            return CoreSystem::maskSensitiveParamsInUrl($url, CoreSystem::LOG_SENSITIVE_PARAMS);
        }

        /**
         * error_get_last() with its message masked by logUrl(): a failed file_get_contents() reports the URL of the
         * call, query included, in that message (since 8.5.19).
         *
         * @return array|null
         */
        private function logLastError(): ?array
        {
            $error = error_get_last();
            if(is_array($error) && isset($error['message']) && is_string($error['message'])) $error['message'] = $this->logUrl($error['message']);
            return $error;
        }

        /**
         * Get the default service URL by either returning the provided path directly or appending it to the base URL.
         * @param string $path The path to be appended to the base URL. If path is empty or already contains 'http', it will be returned as is.
         * @return string The complete URL composed of the base URL and the provided path.
         */
        public function defaultServiceUrl($path = '')
        {
            if ($path && strpos($path, 'http') === 0) return $path;
            else {
                if (!$this->core->config->get("CloudServiceUrl"))
                    $this->core->config->set("CloudServiceUrl", 'https://api.cloudframework.io');

                $this->http = $this->core->config->get("CloudServiceUrl");

                if (strlen($path ?? '') && ($path[0] ?? '') != '/')
                    $path = '/' . $path;
                return ($this->http . $path);
            }
        }

        /**
         * Get the service URL using the default service URL
         *
         * @param string $path The path to append to the service URL
         * @return string The complete service URL
         */
        function getServiceUrl($path = '') {return $this->defaultServiceUrl($path);}


        /**
         * Get cache data for a specific route with optional parameters.
         *
         * @param string $route The route for which cache data is requested.
         * @param mixed $data Additional data to be used in generating cache key.
         * @param string $verb The HTTP verb used to request the route (default: 'GET').
         * @param array|null $extraheaders Additional headers to be included in the request.
         * @param bool $raw Flag to indicate if raw response should be returned (default: false).
         * @return mixed|null The cached data for the given route, or null if cache miss or refresh is forced.
         */
        public function getCache(string $route, $data = null, $verb = 'GET', $extraheaders = null, $raw = false)
        {
            $_qHash = hash('md5', $route . json_encode($data) . $verb);
            $ret = $this->core->cache->get($_qHash);
            if (isset($_GET['refreshCache']) || $ret === false || $ret === null) {
                $ret = $this->get($route, $data, $extraheaders, $raw);
                // Only cache successful responses.
                if (is_array($this->responseHeaders) && isset($this->responseHeaders[0]) && strpos($this->responseHeaders[0], 'OK')) {
                    $this->core->cache->set($_qHash, $ret);
                }
            }
            return ($ret);
        }

        /**
         * Generate a cURL request to a specified route with optional data using specified HTTP verb and additional headers
         *
         * @param string $route The URL to send the cURL request to
         * @param mixed|null $data The data to be sent with the request (default: null)
         * @param string $verb The HTTP verb to use for the request (default: 'GET')
         * @param array|null $extra_headers Additional headers to include in the request (default: null)
         * @param bool $raw Flag indicating if data should be sent as raw input (default: false)
         * @return string|false The response from the cURL request or false on failure
         */
        public function getCurl(string $route, $data = null, $verb = 'GET', $extra_headers = null, $raw = false): bool|string
        {

            $_time = microtime(TRUE);
            if($this->sendSysLogs)
                $this->core->logs->add("curl request {$verb} {$this->logUrl($route)} ".(($data === null) ? '{no params}' : '{with params}'),'CoreRequest');

            $this->core->__p->add('Request->getCurl: ', $this->logUrl($route) . " " . (($data === null) ? '{no params}' : '{with params}'), 'note');
            $route = $this->getServiceUrl($route);
            $this->responseHeaders = null;
            $options['http']['header'] = ['Connection: close', 'Expect:', 'ACCEPT:']; // improve perfomance and avoid 100 HTTP Header

            // SSL options of this call: $extra_headers['ssl'] applies to this call only
            $call_ssl = null;
            if(is_array($extra_headers) && array_key_exists('ssl',$extra_headers)) {
                $call_ssl = is_array($extra_headers['ssl']) ? $extra_headers['ssl'] : null;
                unset($extra_headers['ssl']);
            }
            if(false === ($ssl = $this->getSSLOptions($route, $call_ssl))) return false;


            // Automatic send header for X-CLOUDFRAMEWORK-SECURITY if it is defined in config
            if (strlen($this->core->config->get("CloudServiceId")) && strlen($this->core->config->get("CloudServiceSecret")))
                $options['http']['header'][] = 'X-CLOUDFRAMEWORK-SECURITY: ' . $this->generateCloudFrameWorkSecurityString($this->core->config->get("CloudServiceId"), microtime(true), $this->core->config->get("CloudServiceSecret"));

            // Extra Headers
            if ($extra_headers !== null && is_array($extra_headers)) {
                foreach ($extra_headers as $key => $value) {
                    $options['http']['header'][] .= $key . ': ' . $value;
                }
            }

            # Content-type for something different than get.
            if ($verb != 'GET') {
                if (stripos(json_encode($options['http']['header']), 'Content-type') === false) {
                    if ($raw) {
                        $options['http']['header'][] = 'Content-type: application/json';
                    } else {
                        $options['http']['header'][] = 'Content-type: application/x-www-form-urlencoded';
                    }
                }
            }
            // Build contents received in $data as an array
            if (is_array($data) ) {
                if ($verb == 'GET') {
                    // Add the parameter ? or & to add new variable
                    $route .= (strpos($route,'?'))?'&':'?';
                    //explore variable by variable
                    foreach ($data as $key => $value) {
                        if (is_array($value)) {
                            // This could be improved becuase the coding will produce 1738 format and 3986 format
                            $route .= http_build_query([$key => $value]) . '&';
                        } else {
                            $route .= $key . '=' . rawurlencode($value??'') . '&';
                        }
                    }
                } else {
                    if ($raw) {
                        if (stripos(json_encode($options['http']['header']), '/json') !== false) {
                            $build_data = json_encode($data);
                        } else
                            $build_data = $data;
                    } else {
                        $build_data = http_build_query($data);
                    }
                    $options['http']['content'] = $build_data;

                    // You have to calculate the Content-Length to run as script
                    // $options['http']['header'][] = sprintf('Content-Length: %d', strlen($build_data));
                }
            }

            $curl_options = [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HEADER => true,            // return headers
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_HTTPHEADER => $options['http']['header'],
                CURLOPT_CUSTOMREQUEST => $verb

            ] + $this->getCurlSSLOptions($ssl);

            if (isset($options['http']['content'])) {
                $curl_options[CURLOPT_POSTFIELDS] = $options['http']['content'];
            }

            // Cache
            $ch = curl_init($route);
            curl_setopt_array($ch, $curl_options);
            $ret = curl_exec($ch);

            if (!curl_errno($ch)) {
                $header_len = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
                $this->responseHeaders = explode("\n",substr($ret, 0, $header_len));
                $ret = substr($ret, $header_len);
                if($this->getLastResponseCode()>=400) {
                    $this->addError('Error code returned: ' . $this->getLastResponseCode());
                    $this->addError($this->responseHeaders);
                    $this->addError($ret);
                }
            } else {
                $this->addError($this->logLastError());
                $this->addError([('Curl error ' . curl_errno($ch)) => curl_error($ch)]);
                $this->addError(['Curl url' => $this->logUrl($route)]);
                // 60: CURLE_PEER_FAILED_VERIFICATION (certificate or host name), 77: CURLE_SSL_CACERT_BADFILE
                if(in_array(curl_errno($ch),[60,77])) $this->addTLSVerifyError($route, curl_error($ch));
                $ret = false;
            }
            unset($ch); // CurlHandle objects are freed automatically; curl_close() is deprecated in PHP 8.5

            if($this->sendSysLogs) {
                $_time = round(microtime(TRUE) -$_time,4);
                $this->core->logs->add("end curl request {$verb} {$this->logUrl($route)} ".(($data === null) ? '{no params}' : '{with params}')." {$_time} secs",(($this->error)?'debug':'info'),'CoreRequest');
            }

            $this->core->__p->add('Request->getCurl: ', '', 'endnote');
            return $ret;
        }

        /**
         * Decodes a JSON response from a specified route
         *
         * @param string $route The route to fetch JSON data from
         * @param mixed $data Optional data to send along with the request
         * @param array|null $extra_headers Additional headers to include in the request
         * @param bool $send_in_json Flag to specify if data should be sent as JSON
         *
         * @return mixed|null The decoded JSON response or an error array on failure
         */
        public function get_json_decode(string $route, $data = null, $extra_headers = null, $send_in_json = false)
        {
            $this->rawResult = $this->get($route, $data, $extra_headers, $send_in_json);
            $ret = $this->core->jsonDecode($this->rawResult);
            if (JSON_ERROR_NONE === json_last_error()) $this->rawResult = '';
            else {
                $ret = ['error'=>$this->rawResult];
            }
            return $ret;
        }

        /**
         * Decode JSON response from a POST request and return as an array
         *
         * @param string $route The route for the POST request
         * @param mixed $data Optional. The data to be sent in the POST request
         * @param array|null $extra_headers Optional. Extra headers to be included in the POST request
         * @param bool $send_in_json Optional. Whether to send the data in JSON format
         * @return array|null The decoded JSON response or an array with an error key if decoding fails
         */
        public function post_json_decode(string $route, $data = null, $extra_headers = null, $send_in_json = false)
        {
            $this->rawResult = $this->post($route, $data, $extra_headers, $send_in_json);
            $ret = $this->core->jsonDecode($this->rawResult);
            if (JSON_ERROR_NONE === json_last_error()) $this->rawResult = '';
            else {
                $ret = ['error'=>$this->rawResult];
            }
            return $ret;
        }

        /**
         * Decode JSON response after sending data in PUT request
         * @param string $route The route for the PUT request
         * @param mixed $data The data to be sent in the PUT request (optional, default is null)
         * @param array $extra_headers Extra headers to be included in the PUT request (optional, default is null)
         * @param bool $send_in_json Flag to specify if data should be sent in JSON format (optional, default is false)
         * @return mixed Returns the decoded JSON response as an associative array
         */
        public function put_json_decode(string $route, $data = null, $extra_headers = null, $send_in_json = false)
        {
            $this->rawResult = $this->put($route, $data, $extra_headers, $send_in_json);
            $ret = $this->core->jsonDecode($this->rawResult);
            if (JSON_ERROR_NONE === json_last_error()) $this->rawResult = '';
            else {
                $ret = ['error'=>$this->rawResult];
            }
            return $ret;
        }


        /**
         * Parse JSON response data from a PATCH request.
         *
         * @param string $route The API route to send the PATCH request to.
         * @param mixed $data The data to be sent in the PATCH request.
         * @param array|null $extra_headers Extra headers to be included in the PATCH request.
         * @param bool $send_in_json Whether to send the data in JSON format.
         *
         * @return array|null The decoded JSON response as an associative array if successful, null if JSON is empty, or an error array if decoding fails.
         */
        public function patch_json_decode(string $route, $data = null, $extra_headers = null, $send_in_json = false)
        {
            $this->rawResult = $this->patch($route, $data, $extra_headers, $send_in_json);
            $ret = $this->core->jsonDecode($this->rawResult);
            if (JSON_ERROR_NONE === json_last_error()) $this->rawResult = '';
            else {
                $ret = ['error'=>$this->rawResult];
            }
            return $ret;
        }

        /**
         * Delete data at a specific route and decode the JSON response.
         *
         * @param string $route The route where the data should be deleted
         * @param array|null $extra_headers Additional headers to be included in the request
         * @param mixed $data Data to be sent along with the request
         * @param bool $send_in_json Flag indicating if the data should be sent as JSON
         *
         * @return array|null An associative array representing the JSON response if decoding is successful, null otherwise
         */
        public function delete_json_decode(string $route, $extra_headers = null, $data = null, $send_in_json = false)
        {
            $this->rawResult = $this->delete($route, $extra_headers, $data, $send_in_json);
            $ret = $this->core->jsonDecode($this->rawResult);
            if (JSON_ERROR_NONE === json_last_error()) $this->rawResult = '';
            else {
                $ret = ['error'=>$this->rawResult];
            }
            return $ret;
        }

        /**
         * Retrieve data from a specified route using a GET request.
         *
         * @param string $route The route to retrieve data from
         * @param mixed $data Additional data to send with the request (default is null)
         * @param array|null $extra_headers Extra headers to include in the request (default is null)
         * @param bool $send_in_json Whether to send data in JSON format (default is false)
         * @return mixed The response from the specified route
         */
        public function get(string $route, $data = null, $extra_headers = null, $send_in_json = false)
        {
            return $this->call($route, $data, 'GET', $extra_headers, $send_in_json);
        }

        /**
         * Perform a POST request using the specified route and data
         *
         * @param string $route The route for the POST request
         * @param mixed $data (Optional) The data to be sent in the request body
         * @param array $extra_headers (Optional) Extra headers to be included in the request
         * @param bool $send_in_json (Optional) Indicates whether the data should be sent in JSON format
         *
         * @return mixed The response from the POST request
         */
        public function post(string $route, $data = null, $extra_headers = null, $send_in_json = false)
        {
            return $this->call($route, $data, 'POST', $extra_headers, $send_in_json);
        }

        /**
         * Executes a PUT request to the specified route.
         *
         * @param string $route The endpoint route where the PUT request will be sent.
         * @param mixed $data The data to be sent along with the PUT request. Defaults to null.
         * @param array|null $extra_headers Additional headers to be included in the request. Defaults to null.
         * @param bool $send_in_json Indicates whether the data should be sent in JSON format. Defaults to false.
         *
         * @return mixed The response from the PUT request.
         */
        public function put(string $route, $data = null, $extra_headers = null, $send_in_json = false)
        {
            return $this->call($route, $data, 'PUT', $extra_headers, $send_in_json);
        }

        /**
         * Patch method to make a PATCH HTTP request to a specified route with optional data and headers.
         *
         * @param string $route The route to which the PATCH request will be made
         * @param mixed $data (Optional) The data to be sent in the PATCH request (default is null)
         * @param array $extra_headers (Optional) Additional headers to include in the PATCH request (default is null)
         * @param bool $send_in_json (Optional) Indicates whether data should be sent in JSON format (default is false)
         */
        public function patch(string $route, $data = null, $extra_headers = null, $send_in_json = false)
        {
            return $this->call($route, $data, 'PATCH', $extra_headers, $send_in_json);
        }

        /**
         * Deletes a resource on the server using the DELETE HTTP method.
         *
         * @param string $route The endpoint route to send the DELETE request to.
         * @param mixed $extra_headers Additional headers to include in the request. Default is null.
         * @param mixed $data The data to send in the request body. Default is null.
         * @param bool $send_in_json Whether to send the data in JSON format. Default is false.
         * @return string|null The response from the DELETE request or null if no response.
         * @throws Exception If an error occurs during the request.
         */
        public function delete(string $route, $extra_headers = null, $data = null, $send_in_json = false)
        {
            return $this->call($route, $data, 'DELETE', $extra_headers, $send_in_json);
        }

        /**
         * Allow to execute a GET,POST,PUT,PATCH,DELETE call with variables and files
         * All the calls will try to add the following headers if they exit in $this->core->config:
         *   - X-CLOUDFRAMEWORK-SECURITY
         *   - X-SERVER-KEY
         *   - X-DS-TOKEN
         *   - X-EXTRA-INFO
         *
         * Sending files:
         *     If you need to send files it can only be made with POST/PUT and $raw=false
         *     $data has to include $data['__files'] = array($file,$file,..) where
         *     $file = [
         *           'file_content'=>'{content to send}'  // if file_path is not sent this is mandatory. Content of file
         *          ,'file_path'=>'{path_to_content}'     // if file_content is not sent this is mandatory. Path of the file content
         *          ,'file_name'=>'{name_of_file}'        // Name of file
         *          ,'file_type'=>'{file type}'           // Content-Type of the file. Example: application/pdf
         *          ,'file_var'=>'{name_of_var}'          // Optional var name to be used sending the file. You can use {name_of_var}[] for multiple files in the same var. default = file
         *
         * Sending SSL data:
         *     $data has to include $data['ssl'][ssl-parameter] = value
         *     to send a client certificate:
         *     $data['ssl']['local_cert'] = path-to-pem-file
         *     $data['ssl']['passphrase'] = password of the pem file
         *
         * Sending JSON data:
         *     send $send_json = true;
         *
         *
         * Extra info: https://stackoverflow.com/questions/4003989/upload-a-file-using-file-get-contents
         * For extra headers you can get further information in: https://www.php.net/manual/de/context.http.php
         * @param string $route
         * @param null $data
         * @param string $verb
         * @param null $extra_headers
         * @param bool $send_json
         * @return bool|false|string
         */
        public function call(string $route, $data = null, $verb = 'GET', $extra_headers = null, $send_json = false)
        {
            if(!$route = trim($route)) {
                $this->addError('Calling function call($route,..) with empty route');
                return false;
            }

            $_time = microtime(TRUE);
            $this->core->__p->add("Request->{$verb}: ", $this->logUrl($route) . " " . (($data === null) ? '{no params}' : '{with params}'), 'note');
            $route = $this->getServiceUrl($route);
            $this->responseHeaders = null;

            if($this->sendSysLogs)
                $this->core->logs->add("request {$verb} {$this->logUrl($route)} ".(($data === null) ? '{no params}' : '{with params}'),'CoreRequest');
            //syslog(LOG_INFO,"request {$verb} {$this->logUrl($route)} ".(($data === null) ? '{no params}' : '{with params}'));

            //region EVALUATE $extra_headers['ssl'] special headers: they apply to this call only (before 8.5.15 they stayed in $this->default_options)
            $call_ssl = null;
            if(is_array($extra_headers) && array_key_exists('ssl',$extra_headers)) {
                $call_ssl = is_array($extra_headers['ssl']) ? $extra_headers['ssl'] : null;
                unset($extra_headers['ssl']);
            }
            $options = $this->default_options;
            if(false === ($options['ssl'] = $this->getSSLOptions($route, $call_ssl))) return false;
            //endregion

            //region EVALUATE to add in $options['http']['header'] Connection and Accept
            // Connection parameter increase the performance of the connection
            $options['http']['header'] = 'Connection: close' . "\r\n";
            if(!is_array($extra_headers) || !isset($extra_headers['Accept']))
                $options['http']['header'] = 'Accept: */*' . "\r\n";
            //endregion


            //region EVALUATE to add X-SERVER-KEY, X-DS-TOKEN, X-EXTRA-INFO, X-CLOUDFRAMEWORK-SECURITY if $this->automaticHeaders
            if($this->automaticHeaders) {
                // Automatic send header for X-CLOUDFRAMEWORK-SECURITY if it is defined in config
                if (strlen($this->core->config->get("CloudServiceId")) && strlen($this->core->config->get("CloudServiceSecret")))
                    $options['http']['header'] .= 'X-CLOUDFRAMEWORK-SECURITY: ' . $this->generateCloudFrameWorkSecurityString($this->core->config->get("CloudServiceId"), microtime(true), $this->core->config->get("CloudServiceSecret")) . "\r\n";

                // Add Server Key if we have it.
                if (strlen($this->core->config->get("CloudServerKey")))
                    $options['http']['header'] .= 'X-SERVER-KEY: ' . $this->core->config->get("CloudServerKey") . "\r\n";

                // Add Server Key if we have it.
                if (strlen($this->core->config->get("X-DS-TOKEN")))
                    $options['http']['header'] .= 'X-DS-TOKEN: ' . $this->core->config->get("X-DS-TOKEN") . "\r\n";

                if (strlen($this->core->config->get("X-EXTRA-INFO")))
                    $options['http']['header'] .= 'X-EXTRA-INFO: ' . $this->core->config->get("X-EXTRA-INFO") . "\r\n";
            }
            //endregion

            //region EVALUATE to add https extra headers from $headers
            // Extra Headers
            if ($extra_headers !== null && is_array($extra_headers)) {
                foreach ($extra_headers as $key => $value) {
                    if(!is_array($value) && $value!='ssl')
                        $options['http']['header'] .= $key . ': ' . $value . "\r\n";
                }
            }
            //endregion

            // Method
            $options['http']['method'] = $verb;

            // Content-type
            $MULTIPART_BOUNDARY= '--------------------------'.microtime(true);
            if ($verb != 'GET') {
                if (stripos($options['http']['header'], 'Content-type') === false) {
                    if ($send_json) {
                        $options['http']['header'] .= 'Content-type: application/json' . "\r\n";
                    }elseif(($verb == 'POST' || $verb == 'PUT' || $verb == 'PATCH') && is_array($data) && key_exists('__files', $data) && $data['__files']){
                        $options['http']['header'] .= 'Content-Type: multipart/form-data; boundary='.$MULTIPART_BOUNDARY;
                    } else {
                        $options['http']['header'] .= 'Content-type: application/x-www-form-urlencoded' . "\r\n";
                    }
                }
            }


            // Build contents received in $data as an array
            if (is_array($data)) {
                if ($verb == 'GET') {
                    if (strpos($route, '?') === false) $route .= '?';
                    else $route .= '&';
                    foreach ($data as $key => $value) {
                        if (is_array($value)) {
                            // This could be improved becuase the coding will produce 1738 format and 3986 format
                            $route .= http_build_query([$key => $value]) . '&';
                        } else {
                            $route .= $key . '=' . rawurlencode($value??'') . '&';
                        }
                    }
                } else {
                    if ($send_json) {
                        if (stripos($options['http']['header'], 'application/json') !== false) {
                            $build_data = $this->core->jsonEncode($data);
                        } else
                            $build_data = $data;
                    } else {
                        if(($verb == 'POST' || $verb == 'PUT' || $verb == 'PATCH') && is_array($data) && key_exists('__files',$data) && $data['__files']) {
                            $build_data = '';
                            foreach ($data['__files'] as $file) {

                                //region SET $file_var
                                $file_var = (isset($file['file_var']) && $file['file_var'])?$file['file_var']:'file';
                                //endregion
                                //region SET $file_content
                                $file_content=null;
                                if(key_exists('file_content',$file) && !empty($file['file_content'])) {
                                    $file_content = $file['file_content'];
                                    if(!$file_content) return($this->addError('Error trying to send a file. file_content is empty.'));
                                } elseif(key_exists('file_path',$file) && !empty($file['file_path'])) {
                                    $file_content = @file_get_contents($file['file_path']);
                                    if(false === $file_content) return($this->addError('Error trying to send a file. The file_path {'.$file['file_path'].'} does not return content'));
                                } else {
                                    return($this->addError('Error trying to send a file. Missing file_content or file_path variables'));
                                }
                                //endregion
                                //region SET $file_name
                                $file_name = (isset($file['file_name']) && $file['file_name'])?$file['file_name']:null;
                                if(!$file_name) return($this->addError('Error trying to send a file. Missing file_name variable'));
                                //endregion
                                //region SET $file_type
                                $file_type = (isset($file['file_type']) && $file['file_type'])?$file['file_type']:null;
                                if(!$file_type) return($this->addError('Error trying to send a file. Missing file_name variable'));
                                //endregion

                                $build_data.=  '--'.$MULTIPART_BOUNDARY."\r\n".
                                    'Content-Disposition: form-data; name="'.$file_var.'"; filename="'.$file_name."\"\r\n".
                                    'Content-Type: '.$file_type."\r\n\r\n".
                                    $file_content."\r\n";
                            }
                            unset($data['__files']);
                            foreach ($data as $key=>$datum) {
                                $build_data.=  '--'.$MULTIPART_BOUNDARY."\r\n".
                                    'Content-Disposition: form-data; name="'.$key.'"'."\r\n\r\n".
                                    $datum."\r\n";
                            }
                            $build_data.=  '--'.$MULTIPART_BOUNDARY."--\r\n";

                        } else {
                            $build_data = http_build_query($data);
                        }
                    }
                    $options['http']['content'] = $build_data;

                    // You have to calculate the Content-Length to run as script
                    if($this->core->is->script())
                        $options['http']['header'] .= sprintf('Content-Length: %d', strlen($build_data)) . "\r\n";
                }
            }
            // Take data as a valid JSON
            elseif(is_string($data)) {
                if(strpos($options['http']['header'],'json')) {
                    if (is_array($this->core->jsonDecode($data))) $options['http']['content'] = $data;
                }
                else {
                    $options['http']['content'] = $data;
                }
            }


            // Save in the class the last options sent
            $this->options = ['route'=>$route,'options'=>$options];
            // Context creation
            $context = stream_context_create($options);

            // The TLS reason is in an earlier warning of file_get_contents(), not in error_get_last(): collect them, keeping the
            // error handler of the application in the chain
            $call_warnings = [];
            $previous_error_handler = set_error_handler(function ($errno, $errstr, $errfile = '', $errline = 0) use (&$call_warnings, &$previous_error_handler) {
                $call_warnings[] = $errstr;
                return is_callable($previous_error_handler) ? $previous_error_handler($errno, $errstr, $errfile, $errline) : false;
            });
            try {
                $ret = file_get_contents($route, false, $context);
                // Return response headers: $http_response_header is deprecated in PHP 8.5 (compile-time notice
                // on any literal reference, hence the dynamic access in the fallback for PHP < 8.5)
                if(function_exists('http_get_last_response_headers')) $this->responseHeaders = http_get_last_response_headers() ?? ['$http_response_header'=>'undefined'];
                else $this->responseHeaders = ${'http_response_header'} ?? ['$http_response_header'=>'undefined'];

                // Process response Headers
                $this->processResponseHeaders();

                // If we have an error
                if ($ret === false) {
                    $this->addError(['route_error'=>$this->logUrl($route),'reponse_headers'=>$this->responseHeaders,'system_error'=>$this->logLastError()]);
                } else {
                    $code = $this->getLastResponseCode();
                    if ($code === null) {
                        $this->addError('Return header not found');
                        $this->addError($this->responseHeaders);
                        $this->addError($ret);
                    } else {
                        if ($code >= 400) {
                            $this->addError('Error code returned: ' . $code);
                            $this->addError($this->responseHeaders);
                            $this->addError($ret);
                        }
                    }
                }
            } catch (Exception $e) {
                $ret = false;
                $this->addError($this->logLastError());
                $this->addError($this->logUrl($e->getMessage()));
            } finally {
                restore_error_handler();
            }
            if($ret === false) {
                foreach ($call_warnings as $warning) {
                    if(preg_match('/certificate verify failed|did not match expected CN|Peer certificate CN/i', $warning)) {
                        $this->addTLSVerifyError($route, trim(preg_replace('/\s+/', ' ', $warning)));
                        break;
                    }
                }
            }
            if($this->sendSysLogs) {
                $_time = round(microtime(TRUE) -$_time,4);
                $this->core->logs->add("end request {$verb} {$this->logUrl($route)} ".(($data === null) ? '{no params}' : '{with params}')." - ".($this->getLastResponseCode())." [{$_time} secs]",'CoreRequest');
            }
            $this->core->__p->add("Request->{$verb}: ", '', 'endnote');


            //syslog(($this->error)?LOG_DEBUG:LOG_INFO,"end request {$verb} {$this->logUrl($route)} ".(($data === null) ? '{no params}' : '{with params}'));

            return ($ret);
        }

        private function processResponseHeaders() {
            if(is_array($this->responseHeaders)) foreach ($this->responseHeaders as $responseHeader) {
                if(strpos($responseHeader,'Set-Cookie: ')===0) {
                    list($var,$attributes) = explode(';',$responseHeader,2);
                    list($var,$value) = explode('=',$var);
                    $var  = str_replace('Set-Cookie: ','',$var);
                    $this->cookies[$var][] = ['value'=>$value,'path'=>$attributes];
                }
            }
        }

        /**
         * Gets the last response code from the response headers.
         *
         * Parses the first response header to extract the response code,
         * which is typically found after the HTTP status line.
         *
         * @return int|null The last response code if available, null otherwise.
         */
        public function getLastResponseCode()
        {
            $code = null;
            if (isset($this->responseHeaders[0])) {
                $parts = explode(' ', $this->responseHeaders[0]);
                if(isset($parts[1])) $code=$parts[1];
            }
            return $code;

        }

        /**
         * Generate a CloudFrameWork SecurityS tring to be used with a API with CloudFramework Technology
         * @param $id
         * @param string $time time, has to to be microtime()
         * @param string $secret
         * @return string|null
         * @throws Exception
         */
        public function generateCloudFrameWorkSecurityString($id, $time = '', $secret = '')
        {
            $ret = null;
            if (!strlen($secret)) {
                $secArr = $this->core->config->get('CLOUDFRAMEWORK-ID-' . $id);
                if (isset($secArr['secret'])) $secret = $secArr['secret'];
            }
            if (!strlen($secret)) {
                $this->core->logs->add('conf-var CLOUDFRAMEWORK-ID-' . $id . ' missing.');
            } else {
                if (!strlen($time)) $time = microtime(true);
                $date = new DateTime(null, new DateTimeZone('UTC'));
                $time += $date->getOffset();
                $ret = $id . '__UTC__' . $time;
                $ret .= '__' . hash_hmac('sha1', $ret, $secret);
            }
            return $ret;
        }

        /**
         * Fetches the headers of a given URL and returns them as an array.
         *
         * @param string $url The URL for which headers need to be retrieved.
         *
         * @return array|bool Returns an array containing the headers of the URL if successful, or false if an error occurs while fetching the headers.
         */
        public function getUrlHeaders(string $url): bool|array|null
        {
            if(!$this->core->is->validURL($url)) return($this->core->errors->add('invalid url: '.$url));
            if(!($headers = @get_headers($url))) {
                $this->core->errors->add(error_get_last()['message']);
            }
            return $headers;
        }

        /**
         * @param $url
         * @param $header key name of the header to get.. If not passed return all the array
         * @return string|array
         */
        public function getUrlHeader($url, $header=null) {
            $ret = 'error';
            $response = $this->getUrlHeaders($url);
            $headers = [];
            foreach ($response as $i=>$item) {
                if($i==0) $headers['response'] = $item;
                else {
                    list($key,$value) = explode(':',strtolower($item),2);
                    $headers[$key] = $value;
                }
            }
            if($header) return($headers[strtolower($header)]);
            else return $headers;
        }

        /**
         * Adds an error message in the class and in the parent $this->>core object
         * and sets error flag to true.
         *
         * @param mixed $value The error message to be added.
         *
         * @return bool Returns false after adding the error message.
         */
        private function addError($value): bool
        {
            $this->error = true;
            $this->core->errors->add($value);
            $this->errorMsg[] = $value;
            return false;
        }

        /**
         * Retrieves the content of a specific response header identified by the given key from the responseHeaders array.
         *
         * @param string $key The key of the response header to retrieve.
         *
         * @return string|null The content of the response header corresponding to the provided key if found, otherwise returns null.
         */
        public function getResponseHeader($key) {

            if(is_array($this->responseHeaders))
                foreach ($this->responseHeaders as $responseHeader)
                    if(strpos($responseHeader,$key)!==false) {
                        list($header_key,$content) = explode(':',$responseHeader,2);
                        $content = trim($content);
                        return $content;
                    }
            return null;
        }

        /**
         * Fingerprint that sendLog() forwards to the log service: getRequestFingerPrint() as pretty JSON, so the values of
         * the sensitive query parameters of 'uri' and 'http_referer' are masked also for a fingerprint gathered from
         * queue (since 8.5.16; before, the queued one was forwarded as it arrived).
         * Since 8.5.17 a queued fingerprint that is not a JSON object is no longer forwarded as it arrived (it could carry
         * unmasked values): it is replaced by the fingerprint of the current request, as getRequestFingerPrint() does.
         *
         * @return string|false The JSON string (false only if json_encode() fails).
         */
        public function getLogFingerPrint()
        {
            return json_encode($this->core->system->getRequestFingerPrint(), JSON_PRETTY_PRINT);
        }

        /**
         * Sends log data to a cloud service or local log path.
         *
         * @param string $type The type of log being sent.
         * @param string $cat The category of the log.
         * @param string $subcat The subcategory of the log.
         * @param string $title The title of the log.
         * @param string $text The main text content of the log.
         * @param string $email The email to which the log report should be sent.
         * @param string $app The application associated with the log (defaults to current host).
         * @param bool $interactive Whether the log is interactive. default is false
         *
         * @return mixed Returns the result of sending the log data.
         */
        public function sendLog($type, $cat, $subcat, $title, $text = '', $email = '', $app = '', $interactive = false)
        {

            if (!strlen($app)) $app = $this->core->system->url['host'];

            $this->core->logs->add(['sending cloud service logs:' => [$this->getServiceUrl('queue/cf_logs/' . $app), $type, $cat, $subcat, $title]]);
            if (!$this->core->config->get('CloudServiceLog') && !$this->core->config->get('LogPath')) return false;
            $app = str_replace(' ', '_', $app);
            $params['id'] = $this->core->config->get('CloudServiceId');
            $params['cat'] = $cat;
            $params['subcat'] = $subcat;
            $params['title'] = $title;
            if (!is_string($text)) $text = json_encode($text);
            $params['text'] = $text . ((strlen($text)) ? "\n\n" : '');
            if ($this->core->errors->lines) $params['text'] .= "Errors: " . json_encode($this->core->errors->data, JSON_PRETTY_PRINT) . "\n\n";
            if ($this->core->logs->lines) $params['text'] .= "Logs: " . json_encode($this->core->logs->data, JSON_PRETTY_PRINT);

            // IP gathered from queue. Since 8.5.28 only in a real task of a queue and only a valid IP: before, any
            // request could send cloudframework_queued_ip and choose the IP written in the log.
            $queued_ip = CoreSystem::isQueuedTask() ? CoreSystem::validIP($_REQUEST['cloudframework_queued_ip'] ?? '') : '';
            $params['ip'] = ($queued_ip !== '') ? $queued_ip : $this->core->system->ip;

            // Fingerprint of the request, or the one gathered from queue, with its URLs masked (since 8.5.16)
            $params['fingerprint'] = $this->getLogFingerPrint();

            // Tell the service to send email of the report.
            if (strlen($email) && filter_var($email, FILTER_VALIDATE_EMAIL))
                $params['email'] = $email;
            if ($this->core->config->get('CloudServiceLog')) {
                $ret = $this->core->jsonDecode($this->get('queue/cf_logs/' . urlencode($app??'') . '/' . urlencode($type??''), $params, 'POST'), true);
                if (is_array($ret) && !$ret['success']) $this->addError($ret);
            } else {
                $ret = 'Sending to LogPath not yet implemented';
            }
            return $ret;
        }

        /**
         * Sends Cross-Origin Resource Sharing (CORS) headers to allow cross-domain AJAX requests.
         * if the method is OPTIONS it performs an exit to avoid ENDPOINT execution
         *
         * @param string $methods (optional) Allowed HTTP methods. Default is 'GET,POST,PUT'.
         * @param string $origin (optional) Allowed origin. If not provided, it will be obtained from the HTTP_ORIGIN header.
         * @param string $extra_headers (optional) Additional headers to allow in the CORS request.
         *
         * @return void
         */
        public function sendCorsHeaders(string $methods = 'GET,POST,PUT', string $origin = '', string $extra_headers='')
        {

            if($extra_headers) $extra_headers = ','.$extra_headers;
            // Rules for Cross-Domain AJAX
            // https://developer.mozilla.org/en-US/docs/Web/HTTP/Access_control_CORS
            // $origin =((strlen($_SERVER['HTTP_ORIGIN']))?preg_replace('/\/$/', '', $_SERVER['HTTP_ORIGIN']):'*')
            if (!strlen($origin)) $origin = ((strlen($_SERVER['HTTP_ORIGIN'])) ? preg_replace('/\/$/', '', $_SERVER['HTTP_ORIGIN']) : '*');
            header("Access-Control-Allow-Origin: $origin");
            header("Access-Control-Allow-Methods: $methods");
            header("Access-Control-Allow-Headers: Content-Type,Authorization,X-CloudFrameWork-AuthToken,X-CLOUDFRAMEWORK-SECURITY,X-DS-TOKEN,X-REST-TOKEN,X-EXTRA-INFO,X-WEB-KEY,X-SERVER-KEY,X-REST-USERNAME,X-REST-PASSWORD,X-APP-KEY,cache-control,x-requested-with".$extra_headers);
            header("Access-Control-Allow-Credentials: true");
            header('Access-Control-Max-Age: 1000');

            // To avoid angular Cross-Reference
            if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
                header("HTTP/1.1 200 OK");
                exit();
            }


        }


        /**
         * Checks if a URL exists by sending a HEAD request and analyzing the response headers.
         *
         * @param string $url The URL to check for existence.
         *
         * @return bool Returns true if the URL exists (returns a non-404 response code), false otherwise.
         */
        public function urlExists(string $url): bool
        {
            $exists = true;
            if(strpos($url,'http')!==0) $exists = false;
            else {
                $file_headers = @get_headers($url);
                if(!$file_headers || strpos($file_headers[0]??'', '404') || strpos($file_headers[0]??'', '403')) {
                    $exists = false;
                }
            }
            return $exists;
        }


        /**
         * Converts the given string to uppercase, replaces hyphens with underscores,
         * and retrieves the value from the HTTP header provided within the $_SERVER superglobal.
         *
         * @param string $str The HTTP header string to be retrieved.
         *
         * @return string Returns the value of the HTTP header if it exists in $_SERVER, empty string otherwise.
         */
        public function getHeader(string $str)
        {
            $str = strtoupper($str);
            $str = str_replace('-', '_', $str);
            return ((isset($_SERVER['HTTP_' . $str])) ? $_SERVER['HTTP_' . $str] : '');
        }

        /**
         * Retrieves and returns the HTTP headers from the $_SERVER superglobal array.
         *
         * This method loops through the $_SERVER array and extracts HTTP headers by checking for keys starting
         * with 'HTTP_'. It then removes the 'HTTP_' prefix from the key and adds the header to the result array.
         *
         * @return array An associative array containing HTTP headers where the keys are the header names without the 'HTTP_' prefix
         * and the values are the corresponding header values.
         */
        public function getHeaders(): array
        {
            $ret = array();
            foreach ($_SERVER as $key => $value) if (strpos($key, 'HTTP_') === 0) {
                $ret[str_replace('HTTP_', '', $key)] = $value;
            }
            return ($ret);
        }

        /**
         * Resets the error message array ($this->errorMsg) and sets error flag ($this->error) to false.
         *
         * @return void
         */
        public function reset() {
            $this->errorMsg = [];
            $this->error = false;
        }


    }

    /**
     * $this->core->user Class to manage User information
     * @package Core.user
     */
    class CoreUser
    {
        private $core;
        var $isAuth = false;
        var $id;
        var $namespace = 'Default';
        var $token;
        /** @var int $cacheExpiresIn Time to expire a token */
        var $cacheExpiresIn = 3600;
        var $tokenExpiration;
        var $tokenExpiresIn;
        var $cachedTokenExpiresIn;
        var $data = [];
        /** @var bool $cached says if the user data has been retrieved from cache */
        var $cached = false;

        /** @var int $maxTokens Max number of tokens in $expirationTime to handle */
        var $maxTokens = 10;
        /** @var int Number of tokens active for the user */
        var $activeTokens = 0;

        var $error = false;
        var $errorCode = null;
        var $errorMsg = [];

        // URL of the API service to verify token
        const APIServices = 'https://api.cloudframework.io/core/signin';
        const APIPortalServices = 'https://api.cloudframework.io/erp/portal-users/2.0';

        function __construct(Core7 &$core)
        {
            $this->core = $core;
            $this->reset();
        }

        /**
         * Resent user variables
         */
        public function reset()
        {
            $this->isAuth = false;
            $this->id = null;
            $this->data = null;
            $this->token = null;
            $this->tokenExpiration = null;
        }

        /**
         * Set Auth for a user in a namespace and store the result in cache for new queries
         * @param bool $auth
         * @param string $user
         * @param string $namespace
         * @param bool $auth
         * @param array $data
         * @param string $token
         */
        function setAuth(bool $auth, string $user = '', string $namespace = '', string $token = '', $data = null)
        {

            $this->reset();
            $this->isAuth = $auth;

            if ($user && $namespace) {
                $userData = $this->core->cache->get($namespace . '_' . $user);
                if (!$userData) $userData = ['id' => $user, 'tokens' => [], 'data' => []];
                if ($this->isAuth) {
                    $this->id = $userData['id'];
                    $this->data = $userData['data'];
                    if ($token) {
                        if (count($userData['tokens']) >= $this->maxTokens) do {
                            array_shift($userData['tokens']);
                        } while (count($userData['tokens']) >= $this->maxTokens);

                        $this->token = $token;
                        $userData['tokens'][$token] = ['error' => null, 'time' => microtime(true)];
                    }
                    if ($data !== null) $userData['data'] = $data;


                    $this->core->cache->set($namespace . '_' . $user, $userData);
                    $this->data = $userData['data'];


                } else {
                    if ($token) {
                        if (isset($userData['tokens'][$token])) unset($userData['tokens'][$token]);
                        $this->core->cache->set($namespace . '_' . $user, $userData);
                    } else {
                        $this->core->cache->delete($namespace . '_' . $user);
                    }
                }

            }
        }

        /**
         * Check a token previously generated
         * @deprecated use loadPlatformUserWithToken
         * @param string $token
         * @return bool|void
         */
        function checkUserToken(string $token)
        {

            $this->reset();

            $parts = explode('__', $token, 4);
            if (count($parts) != 4 || $parts[0] != 'token' || !$parts[1] || !$parts[2]) return ($this->addError('The token does not have a right format'));

            $user = $parts[1];
            $namespace = $parts[2];

            $userData = $this->core->cache->get($namespace . '_' . $user);
            if (!$userData || !isset($userData['tokens'][$token]) || !isset($userData['tokens'][$token]['time'])) return ($this->addError('Token is not found'));

            $now = microtime(true);
            if (($now - $userData['tokens'][$token]['time']) > $this->cacheExpiresIn) {
                unset($userData['tokens'][$token]);
                $this->core->cache->set($namespace . '_' . $user, $userData);

                return ($this->addError('Token is expired'));

            }

            //region SET $this->{isAuth, namespace, token, id, data}
            $this->isAuth = true;
            $this->token = $token;
            $this->activeTokens = count($userData['tokens']);
            $this->cachedTokenExpiresIn =  intval($this->cacheExpiresIn - (microtime(true)-$userData['tokens'][$token]['time']));
            $this->tokenExpiresIn = ($userData['data']['User']['Expires']??time())-time();
            $this->namespace = $namespace;
            $this->id = $userData['id'];
            $this->data = $userData['data'];
            //endregion

            return true;
        }

        /**
         * Return a Token with a specific format token__{user}__{namespace}__hash()
         * This method is to be used together with setAuth(..)
         * @param $user
         * @param $namespace
         * @return string
         */
        function createUserToken($user, $namespace = 'default', $data = null)
        {

            $this->reset();

            //region CHECK $user, $namespace
            if (!$user) return ($this->addError('The user is empty'));
            if (!$namespace) return ($this->addError('The namespace is empty'));
            if (strpos($user, '__') !== false) return ($this->addError('The user has "__" chars in the content'));
            if (strpos($namespace, '__') !== false) return ($this->addError('The namespace has "__" chars in the content'));
            //endregion

            //region SET $token and $userData reading from cache
            $token = 'token__' . $user . '__' . $namespace . '__' . hash('md5', microtime(true));

            $userData = $this->core->cache->get($namespace . '_' . $user);
            if (!$userData) $userData = ['id' => $user, 'tokens' => [], 'data' => []];
            //endregion

            //region SET $now and REDUCE $userData['tokens'] to $this->maxTokens
            $now = microtime(true);
            if (count($userData['tokens']) >= $this->maxTokens) do {
                array_shift($userData['tokens']);
            } while (count($userData['tokens']) >= $this->maxTokens);
            $userData['tokens'][$token] = ['error' => null, 'time' => $now];
            //endregion

            //region ASSIGN $data to $userData['data'] if not null
            if ($data !== null) $userData['data'] = $data;
            //endregion

            //region SET $this->isAuth, namespace, token, id, data}
            $this->isAuth = true;
            $this->token = $token;
            $this->tokenExpiration = $this->cacheExpiresIn;
            $this->namespace = $namespace;
            $this->id = $userData['id'];
            $this->data = $userData['data'];
            //endregion

            //region SAVE cache of $userData and return the token
            $this->core->cache->set($namespace . '_' . $user, $userData);
            return ($token);
            //endregion

        }

        /**
         * Logout the user
         * @param string $token
         * @param string $delete_all_tokens
         * @return bool
         */
        function logoutUserToken(string $token = '', $delete_all_tokens = false)
        {
            if(!$token && $this->token) $token = $this->token;
            $this->reset();
            if ($token) {
                //region SET $user,$namespace
                $parts = explode('__', $token, 4);
                if (count($parts) != 4 || $parts[0] != 'token' || !$parts[1] || !$parts[2]) return ($this->addError('WRONG_USER_TOKEN_FORMAT','The token does not have a right format'));
                $user_token = $parts[1];
                $namespace = $parts[2];
                //endregion

                //region SET $userData
                $userData = $this->core->cache->get($namespace . '_' . $user_token);
                //endregion

                if ($delete_all_tokens) {
                    if ($userData) {
                        $this->core->cache->delete($namespace . '_' . $user_token);
                    }
                } else {
                    if ($userData) {
                        if (isset($userData['tokens'][$token])) {
                            unset($userData['tokens'][$token]);
                            $this->core->cache->set($namespace . '_' . $user_token, $userData);
                        }
                    }
                }
            }

            return true;
        }

        /**
         * Execute a sign-in over the CLOUD-PLATFORM using $user/$password in $platform_id
         * and store the token and user info in $this->token, $this->data
         * @param string $user
         * @param string $password
         * @param string $platform_id
         * @param string $ClientId
         * @param string $web_key
         * @return bool|void
         */
        function loadPortalUserWithUserPassword(string $user, string $password, string $platform_id, string $ClientId, string $web_key)
        {

            $payload = [
                'username' => $user,
                'userpassword' => $password,
                'ClientId' => $ClientId,
                'Fingerprint'=>$this->core->system->getRequestFingerPrint()
            ];
            $header = [
                'X-WEB-KEY' => 'CoreUser',
                'X-WEB-KEY' => $web_key
            ];

            $cfUserInfo = $this->core->request->post_json_decode($this::APIPortalServices . '/' . $platform_id . '/web-oauth/signin', $payload, $header);
            if ($this->core->request->error) {
                $this->addError($cfUserInfo['code']??$this->core->request->getLastResponseCode(), $cfUserInfo['message']??$this->core->request->errorMsg);
                $this->core->request->reset();
                return;
            }

            //region SET $user_token,$namespace,$key from token structure or return errorCode: WRONG_TOKEN_FORMAT
            $tokenParts = explode('__', $cfUserInfo['data']['web_token']);
            if (count($tokenParts) != 3
                || !($namespace = $tokenParts[0])
                || !($user_token = $tokenParts[1])
                || !($key = $tokenParts[2])) {

                $this->addError('WRONG_TOKEN_FORMAT', 'The structure of the token is not right');
                return false;
            }
            //endregion

            //region READ $userData from $this->core->cache->get($namespace.'_'.$user_token)
            $userData = $this->core->cache->get($namespace . '_' . $user_token);
            if (!$userData) $userData = ['id' => null, 'tokens' => [], 'data' => []];
            //endregion

            $now = microtime(true);
            if (count($userData['tokens']) >= $this->maxTokens) do {
                array_shift($userData['tokens']);
            } while (count($userData['tokens']) >= $this->maxTokens);

            $token = $cfUserInfo['data']['web_token'];
            $userData['tokens'][$token] = ['error' => null, 'time' => $now,'expires'=>$cfUserInfo['data']['expires']??null];
            if($cfUserInfo['data']['Expires']??null)
                $this->tokenExpiresIn = $cfUserInfo['data']['Expires']-time();
            $userData['data'] = $cfUserInfo['data'];
            $userData['id'] = $cfUserInfo['data']['user_data']['KeyId'];

            $this->core->cache->set($namespace . '_' . $user_token, $userData);

            //region SET $this->{isAuth, namespace, token, id, data}
            $this->isAuth = true;
            $this->token = $token;
            $this->namespace = $namespace;
            $this->id = $userData['id'];
            $this->data = $userData['data'];
            unset($userData);
            //endregion

            return true;
        }

        /**
         * Load platform user data using Google access token, authenticate the user,
         * and fetch ERP token from the platform API. Processes and updates user data on success.
         *
         * @param string $user The user's identifier (usually an email or unique username).
         * @param string $token The Google access token associated with the user's account.
         * @param string $platform_id Optional. Platform identifier; if not provided, a default configuration value will be used.
         * @return mixed Returns the result of the processPlatformUserData method or null on error.
         */
        function loadPlatformUserWithGoogleAccessToken(string $user, string $access_token, string $platform_id='',?string $auth_code='')
        {
            //region VERIFY $namespace
            if(!$platform_id && !( $platform_id = $this->core->config->get('core.erp.platform_id')))
                return($this->addError('configuration-error','Missing $platform_id var or core.erp.platform_id config var'));
            //endregion

            //region SET $payload and POST: https://api.cloudframework.io/core/signin/cloudframework/in
            $payload = [
                'user'=>$user,
                'token'=> $access_token,
                'type'=>'google-token'
            ];
            if($auth_code)
                $payload['google_authenticator_code'] = $auth_code;
            //endregion

            //region SET $user_erp_token CALLING https://api.cloudframework.io/core/signin/cloudframework/in to get ERP Token
            $url = 'https://api.cloudframework.dev/core/signin/'.$platform_id.'/in';
            $userLoginData = $this->core->request->post_json_decode($url,$payload, ['X-WEB-KEY'=>'loadPlatformUserWithGoogleAccessToken']);
            if($this->core->request->error) {
                $this->core->request->reset();
                return($this->addError(($userLoginData['code']??$this->core->request->getLastResponseCode()),$userLoginData['message']??$this->core->request->errorMsg));
            }
            if($this->core->request->getLastResponseCode() == 200) {
                return $this->addError($userLoginData['data']['code']??'params-error',$userLoginData['data']);
            }
            //endregion

            //region CALL processPlatformUserData to process userdata and update user variables
            return $this->processPlatformUserData($userLoginData);
            //endregion

        }

        /**
         * Execute a sign-in over the CLOUD-PLATFORM using $user/$password in $platform_id
         * and store the token and user info in $this->token, $this->data
         * @param string $user
         * @param string $password
         * @param string $platform_id
         * @param string $integration_key
         * @return bool|void
         */
        function loadPlatformUserWithUserPassword(string $user, string $password, string $platform_id, string $integration_key)
        {

            $payload = [
                'type' => 'userpassword',
                'user' => $user,
                'password' => $password,
                'Fingerprint'=>$this->core->system->getRequestFingerPrint()
            ];
            $header = [
                'X-WEB-KEY' => 'CoreUser',
                'X-EXTRA-INFO' => $integration_key
            ];

            $userLoginData = $this->core->request->post_json_decode($this::APIServices . '/' . $platform_id . '/in?_refresh_integration_keys&from_loadPlatformUserWithUserPassword', $payload, $header);
            if ($this->core->request->error) {
                $this->addError($userLoginData['code']??$this->core->request->getLastResponseCode(), $userLoginData['message']??$this->core->request->errorMsg);
                $this->core->request->reset();
                return;
            }
            //region CALL processPlatformUserData to process userdata and update user variables
            return $this->processPlatformUserData($userLoginData);
            //endregion

        }

        /**
         * Processes platform user data by validating and updating the user's token information within the cache.
         *
         * @param array $userLoginData Reference to an array containing the user login data, including the `dstoken` and user details.
         * @return bool Returns true if the user data is successfully processed and cached; false if the token structure is invalid.
         */
        private function processPlatformUserData(array &$userLoginData): bool
        {

            //region SET $user_token,$namespace,$key from token structure or return errorCode: WRONG_TOKEN_FORMAT
            $tokenParts = explode('__', $userLoginData['data']['dstoken']);
            if (count($tokenParts) != 3
                || !($namespace = $tokenParts[0])
                || !($user_token = $tokenParts[1])
                || !($key = $tokenParts[2])) {

                $this->addError('WRONG_TOKEN_FORMAT', 'The structure of the token is not right');
                return false;
            }
            //endregion

            //region READ $userData from $this->core->cache->get($namespace.'_'.$user_token)
            $userData = $this->core->cache->get($namespace . '_' . $user_token);
            if (!$userData) $userData = ['id' => null, 'tokens' => [], 'data' => []];
            //endregion

            $now = microtime(true);
            if (count($userData['tokens']) >= $this->maxTokens) do {
                array_shift($userData['tokens']);
            } while (count($userData['tokens']) >= $this->maxTokens);

            $token = $userLoginData['data']['dstoken'];
            $userData['tokens'][$token] = ['error' => null, 'time' => $now];
            if($userLoginData['data']['User']['Expires']??null)
                $this->tokenExpiresIn = $userLoginData['data']['User']['Expires']-time();
            $userData['data'] = $userLoginData['data'];
            $userData['id'] = $userLoginData['data']['User']['KeyName'];

            $this->core->cache->set($namespace . '_' . $user_token, $userData);

            //region SET $this->{isAuth, namespace, token, id, data}
            $this->isAuth = true;
            $this->token = $token;
            $this->namespace = $namespace;
            $this->id = $userData['id'];
            $this->data = $userData['data'];
            unset($userData);
            //endregion

            return true;
        }

        /**
         * Deprecated. Use loginCloudPlatform
         * @param $user
         * @param $password
         * @param $namespace
         * @param $integration_key
         * @return bool|void
         * @deprecated
         */
        function loginERP($user, $password, $namespace, $integration_key)
        {
            return $this->loadPlatformUserWithUserPassword($user, $password, $namespace, $integration_key);
        }

        /**
         * Logout the user
         * @deprecated use logoutPlatformToken
         * @param string $token
         * @param string $delete_all_tokens
         * @return bool
         */
        function logoutERPToken(string $token = '', $delete_all_tokens = false)
        {
            return $this->logoutPlatformToken($token, $delete_all_tokens);
        }

        /**
         * Logout the user
         * @param string $token
         * @param string $delete_all_tokens
         * @return bool
         */
        function deletePlatformToken(string $token = '', $delete_all_tokens = false)
        {
            if (!$token) $token = $this->token;
            $this->reset();
            if ($token) {
                //region SET $user,$namespace,$key from token structure or return errorCode: WRONG_TOKEN_FORMAT
                $tokenParts = explode('__', $token);
                if (count($tokenParts) != 3
                    || !($namespace = $tokenParts[0])
                    || !($user_token = $tokenParts[1])
                    || !($key = $tokenParts[2]))
                    return ($this->addError('WRONG_PLATFORM_TOKEN_FORMAT', 'The structure of the token is not right'));
                //endregion

                //region GET $userData from Cache
                $userData = $this->core->cache->get($namespace . '_' . $user_token);
                //endregion

                if ($delete_all_tokens) {
                    $this->core->cache->delete($namespace . '_' . $user_token);
                } else {
                    if ($userData) {
                        if (isset($userData['tokens'][$token])) {
                            unset($userData['tokens'][$token]);
                            $this->core->cache->set($namespace . '_' . $user_token, $userData);
                        }
                    }
                }
            }

            return true;
        }

        /**
         * Remove $token from the validation cache used by loadPlatformUserWithToken() and
         * loadPlatformUserWithMCPToken() without resetting the current user of the request (deletePlatformToken() does reset it).
         * Called by CoreSecurity::deactivateDSToken() so a revoked token is rejected in the next request.
         *
         * The cache key is prefixed with the cache namespace active when the token was validated, and each API sets its own:
         * the default cacheSpacename ('cloudframework'), the platform or the platform with '_dev' (CFAPI2026). So the token is
         * removed in all of them and not only in the namespace of the request that revokes it.
         * @param string $token Platform or MCP token with the structure {namespace}__{prefix}__{hash}
         * @param bool $whole_key true removes every token cached under {namespace}_{prefix} (all the tokens of that user and prefix)
         * @return bool false if the token does not have the right structure
         */
        function deleteTokenFromCache(string $token, bool $whole_key = false): bool
        {
            $tokenParts = explode('__', $token);
            if (count($tokenParts) != 3 || !$tokenParts[0] || !$tokenParts[1] || !$tokenParts[2]) return false;
            $key_cache = $tokenParts[0] . '_' . $tokenParts[1];

            //region SET $spacenames where the token can have been validated
            $current_spacename = $this->core->cache->spacename;
            $platform = preg_replace('/_dev$/', '', $tokenParts[0]);
            $spacenames = array_unique([$this->core->config->get('cacheSpacename') ?: 'cloudframework', $platform, $platform . '_dev']);
            //endregion

            //region DELETE $token in the current cache namespace and in $spacenames
            foreach (array_merge([null], $spacenames) as $spacename) {
                if ($spacename !== null) $this->core->cache->setNameSpace($spacename);
                if ($whole_key) {
                    $this->core->cache->delete($key_cache);
                    continue;
                }
                $userData = $this->core->cache->get($key_cache);
                if (is_array($userData) && (isset($userData['tokens'][$token]) || isset($userData['mcp_tokens'][$token]))) {
                    unset($userData['tokens'][$token], $userData['mcp_tokens'][$token]);
                    $this->core->cache->set($key_cache, $userData);
                }
            }
            $this->core->cache->spacename = $current_spacename;
            //endregion

            return true;
        }

        /**
         * Logout the user
         * @param string $token
         * @param string $delete_all_tokens
         * @return bool
         */
        function logoutPlatformToken(string $token = '', $delete_all_tokens = false)
        {
            if(!$token) $token = $this->token;
            $this->reset();
            if ($token) {
                //region SET $user,$namespace,$key from token structure or return errorCode: WRONG_TOKEN_FORMAT
                $tokenParts = explode('__', $token);
                if (count($tokenParts) != 3
                    || !($namespace = $tokenParts[0])
                    || !($user_token = $tokenParts[1])
                    || !($key = $tokenParts[2]))
                    return ($this->addError('WRONG_PLATFORM_TOKEN_FORMAT', 'The structure of the token is not right'));
                //endregion

                //region GET $userData from Cache
                $userData = $this->core->cache->get($namespace . '_' . $user_token);
                //endregion

                if ($delete_all_tokens) {
                    $this->core->cache->delete($namespace . '_' . $user_token);
                } else {
                    if ($userData) {
                        if (isset($userData['tokens'][$token])) {
                            unset($userData['tokens'][$token]);
                            $this->core->cache->set($namespace . '_' . $user_token, $userData);
                        }
                    }
                }
            }

            return true;

            $this->reset();
            if ($token) {
                $parts = explode('__', $token, 4);
                if (count($parts) != 4 || $parts[0] != 'token' || !$parts[1] || !$parts[2]) return ($this->addError('The token does not have a right format'));


                //region SET $user,$namespace,$key from token structure or return errorCode: WRONG_TOKEN_FORMAT
                $tokenParts = explode('__', $token);
                if (count($tokenParts) != 3
                    || !($namespace = $tokenParts[0])
                    || !($user_token = $tokenParts[1])
                    || !($key = $tokenParts[2]))
                    return ($this->addError('WRONG_TOKEN_FORMAT', 'The structure of the token is not right'));
                //endregion

                //region SET $userData
                $userData = $this->core->cache->get($namespace . '_' . $user_token);
                //endregion


                if ($delete_all_tokens) {
                    if ($userData) {
                        foreach ($userData['tokens'] as $token_key => $foo) if (strpos($token_key, '__signin_in_')) {
                            $header = [
                                'X-WEB-KEY' => 'CoreUser',
                                'X-DS-TOKEN' => $token_key
                            ];
                            $this->core->request->get_json_decode($this::APIServices . '/' . $namespace . '/logout', null, $header);
                            if ($this->core->request->error) {
                                $this->core->logs->add($this->core->request->errorMsg, 'logout_erp_logout');
                                $this->core->request->reset();
                            }
                        }
                        $this->core->cache->delete($namespace . '_' . $user_token);
                    }
                } else {
                    if ($userData) {
                        if (isset($userData['tokens'][$token])) {
                            if (strpos($token, '__signin_in_')) {
                                $header = [
                                    'X-WEB-KEY' => 'CoreUser',
                                    'X-DS-TOKEN' => $token
                                ];
                                $this->core->request->get_json_decode($this::APIServices . '/' . $namespace . '/logout', null, $header);
                                if ($this->core->request->error) {
                                    $this->core->logs->add($this->core->request->errorMsg, 'logout_erp_logout');
                                    $this->core->request->reset();
                                }
                            }
                            unset($userData['tokens'][$token]);
                            $this->core->cache->set($namespace . '_' . $user_token);
                        }
                    }
                }
            }
        }

        /**
         * Use checkPlatformToken
         * @deprecated
         * @param string $token
         * @param string $integration_key
         * @param bool $refresh
         * @return void
         */
        function checkERPToken(string $token, string $integration_key, bool $refresh = false) {
            return $this->loadPlatformUserWithToken($token,$integration_key,$refresh);
        }

        /**
         * Verify that a Portal Web Token is valid and update the following properties: namespace, userId, userData
         * It allows to work with several active tokens at the same time
         * @param string $token Token to verify with CloudFramework ERP
         * @param string $integration_key Integration Key to call CloudFramework API for signing
         * @param bool $refresh if true it ignores cached data and call CLOUD-DIRECTORY API
         * @return false|void
         */
        function loadPortalUserWithToken(string $token, string $ClientId, string $web_key, bool $refresh=false)
        {
            // Reset $user variables
            $this->reset();

            //region SET $user,$namespace,$key from token structure or return errorCode: WRONG_TOKEN_FORMAT
            $tokenParts = explode('__',$token);
            if(count($tokenParts) != 3
                || !($namespace=$tokenParts[0])
                || !($user_token=$tokenParts[1])
                || !($key=$tokenParts[2])
                || strpos($tokenParts[1],'web_oauth_')!==0)
                return($this->addError('WRONG_TOKEN_FORMAT','The structure of the token is not right'));
            //endregion

            //region SET $userData trying to get the info from cache deleting expired tokens and checking $this->maxTokens
            $updateCache = false;
            $userData = $this->core->cache->get($namespace.'_'.$user_token);
            if(!$userData) $userData = ['id'=>null,'tokens'=>[],'data'=>[]];
            else {
                $now = microtime(true);
                $num_tokens = 0;
                foreach ($userData['tokens'] as $tokenId=>$tokenInfo) {
                    if(($now - $tokenInfo['time']) > $this->cacheExpiresIn) {
                        unset($userData['tokens'][$tokenId]);
                        $updateCache = true;
                    } else {
                        $num_tokens++;
                        if(!isset($userData['tokens'][$token]) && $num_tokens>=$this->maxTokens) {
                            unset($userData['tokens'][$tokenId]);
                            $updateCache = true;
                        }
                    }
                }

                //update Cache with the deleted tokens
                if($updateCache) $this->core->cache->set($namespace.'_'.$user_token,$userData);

                // Verify $token is not a token with error
                if(isset($userData['tokens'][$token]) && $userData['tokens'][$token]['error']) {
                    return($this->addError('TOKEN_NOT_VALID',['The token already has been used but it is not valid',$userData['tokens'][$token]['error']]));
                }

                // Verify number of active tokens
                if(!isset($userData['tokens'][$token]) && count($userData['tokens']) >=$this->maxTokens) {
                    return($this->addError('MAX_TOKENS_REACHED','The max number of tokens has been reached: '.$this->maxTokens));
                }

                $this->cached = true;

            }
            //endregion

            //region CALL CloudFramework API Service IF $token DOEST not exist in $userData OR $refresh
            if($expires = ($userData['data']['expires']??null)) {
                $this->tokenExpiresIn = $expires-time();
                if($expires >= time()) $expired=false;
                else return($this->addError('TOKEN_EXPIRED','The expiration time of the token has been reached: '.date('Y-m-d H:i:s e',$expires)));
            }
            if(!isset($userData['tokens'][$token]) || $refresh) {

                $this->cached = false;
                $userData['tokens'][$token] = ['error'=>null,'time'=>microtime(true)];
                $params = ['ClientId'=>$ClientId];
                $headers = ['X-WEB-KEY'=>$web_key,'X-DS-TOKEN'=>$token];
                $url = $this::APIPortalServices.'/'.$namespace.'/web-oauth/user-info/'.(str_replace('web_oauth_','',$user_token)).'?checkPortalToken';
                $cfUserInfo = $this->core->request->get_json_decode(
                    $url
                    ,$params
                    ,$headers);

                if($this->core->request->error) {
                    $userData['tokens'][$token]['error'] = $this->core->request->errorMsg;
                    $this->addError($cfUserInfo['code']??$this->core->request->getLastResponseCode(), $cfUserInfo['message']??$this->core->request->errorMsg);
                } else {
                    $userData['data']['user_data'] =  $cfUserInfo['data'];
                    $userData['id'] = $userData['data']['user_data']['KeyId'];
                }
                $this->core->request->reset();
                $this->core->cache->set($namespace.'_'.$user_token,$userData);
                if($this->error) return;
            }
            //endregion

            //region SET $this->{isAuth, namespace, token, id, data}
            $this->isAuth = true;
            $this->token = $token;
            $this->activeTokens = count($userData['tokens']);
            $this->namespace = $namespace;
            $this->id = $userData['id'];
            $this->data = $userData['data'];
            unset($userData);
            $this->core->namespace = $namespace;
            //endregion

            return true;

        }

        /**
         * Verify that a token is valid and update the following properties: namespace, userId, userData
         * It allows to work with several active tokens at the same time
         * @param string $token Token to verify with CloudFramework ERP
         * @param string $integration_key Integration Key to call CloudFramework API for signing
         * @param bool $refresh if true it ignores cached data and call CLOUD-DIRECTORY API to refresh information
         * @return bool
         */
        function loadPlatformUserWithToken(string $token, string $integration_key, bool $refresh=false)
        {
            // Reset $user variables
            $this->reset();

            //region SET $user,$namespace,$key from token structure or return errorCode: WRONG_TOKEN_FORMAT
            $tokenParts = explode('__',$token);
            if(count($tokenParts) != 3
                || !($namespace=$tokenParts[0])
                || !($user_token=$tokenParts[1])
                || !($key=$tokenParts[2]) )
                return($this->addError('WRONG_TOKEN_FORMAT','The structure of the token is not right'));
            //endregion

            //region SET $userData trying to get the info from cache deleting expired tokens and checking $this->maxTokens
            $updateCache = false;
            $userData = $this->core->cache->get($namespace.'_'.$user_token);
            if(!$userData || !is_array($userData) || !isset($userData['tokens']) || $refresh) $userData = ['id'=>null,'tokens'=>[],'data'=>[]];
            else {
                $now = microtime(true);
                $num_tokens = 0;
                $token_expired = false;
                foreach ($userData['tokens'] as $tokenId=>$tokenInfo) {
                    // a token whose own expiration has passed leaves the cache even if the cache time has not expired
                    $tokenExpired = !empty($tokenInfo['expires']) && $tokenInfo['expires'] <= time();
                    if($tokenExpired && $tokenId === $token) $token_expired = true;
                    if($tokenExpired || ($now - $tokenInfo['time']) > $this->cacheExpiresIn) {
                        unset($userData['tokens'][$tokenId]);
                        $updateCache = true;
                    } else {
                        $num_tokens++;
                        if(!isset($userData['tokens'][$token]) && $num_tokens>=$this->maxTokens) {
                            unset($userData['tokens'][$tokenId]);
                            $updateCache = true;
                        }
                    }
                }

                // a valid token cached before 'expires' was stored is validated again once
                if(isset($userData['tokens'][$token]) && !$userData['tokens'][$token]['error'] && !array_key_exists('expires',$userData['tokens'][$token])) {
                    unset($userData['tokens'][$token]);
                    $updateCache = true;
                }

                //update Cache with the deleted tokens
                if($updateCache) $this->core->cache->set($namespace.'_'.$user_token,$userData);

                // Verify $token has not expired
                if($token_expired) {
                    return($this->addError('TOKEN_EXPIRED','The token has expired'));
                }

                // Verify $token is not a token with error
                if(isset($userData['tokens'][$token]) && $userData['tokens'][$token]['error']) {
                    return($this->addError('TOKEN_NOT_VALID',['The token already has been used but it is not valid',$userData['tokens'][$token]['error']]));
                }

                // Verify number of active tokens
                if(!isset($userData['tokens'][$token]) && count($userData['tokens']) >=$this->maxTokens) {
                    return($this->addError('MAX_TOKENS_REACHED','The max number of tokens has been reached: '.$this->maxTokens));
                }

                $this->cached = true;

            }
            //endregion

            //region CALL CloudFramework API Service IF $token DOEST not exist in $userData OR $refresh
            if(!isset($userData['tokens'][$token]) || $refresh) {

                $this->cached = false;
                $userData['tokens'][$token] = ['error'=>null,'time'=>microtime(true)];
                $cfUserInfo = $this->core->request->post_json_decode(
                    $this::APIServices.'/'.$namespace.'/check?_update&from_core7_user_object'
                    ,['Fingerprint'=>$this->core->system->getRequestFingerPrint()]
                    ,['X-WEB-KEY'=>'Core7UserObject'
                    ,'X-DS-TOKEN'=>$token
                    ,'X-EXTRA-INFO'=>$integration_key
                ]);

                if($this->core->request->error) {
                    $userData['tokens'][$token]['error'] = $this->core->request->errorMsg;
                    $this->addError($cfUserInfo['code']??$this->core->request->getLastResponseCode(), $cfUserInfo['message']??$this->core->request->errorMsg);
                } else {
                    $userData['data'] =  $cfUserInfo['data'];
                    $userData['id'] = $cfUserInfo['data']['User']['KeyName'];
                    // expiration of THIS token: $userData['data'] is shared by the tokens of the same prefix.
                    // Only a future value is kept: the issuer answers time() when the token has no Expires
                    $expires = intval($cfUserInfo['data']['User']['Expires']??0);
                    $userData['tokens'][$token]['expires'] = ($expires > time())?$expires:null;
                }
                $this->core->request->reset();
                $this->core->cache->set($namespace.'_'.$user_token,$userData);
                if($this->error) return false;
            }
            //endregion

            //region SET $this->{isAuth, namespace, token, id, data}
            $this->isAuth = true;
            $this->token = $token;
            $this->activeTokens = count($userData['tokens']);
            $this->namespace = $namespace;
            $this->id = $userData['id'];
            $this->data = $userData['data'];
            //$this->expirationTime = $userData['data']['User']['Expires']??time();
            $this->tokenExpiresIn = (($userData['tokens'][$token]['expires']??null)?:($userData['data']['User']['Expires']??time()))-time();
            $this->cachedTokenExpiresIn =  intval($this->cacheExpiresIn - (microtime(true)-$userData['tokens'][$token]['time']));
            unset($userData);
            $this->core->namespace = $namespace;
            //endregion

            return true;

        }


        /**
         * Verify that a token is valid and update the following properties: namespace, userId, userData
         * It allows to work with several active tokens at the same time
         * @param string $token Token to verify with CloudFramework ERP
         * @param string $integration_key Integration Key to call CloudFramework API for signing
         * @param string $authenticator_code optional to send an authenticator code
         * @param bool $refresh if true it ignores cached data and call CLOUD-DIRECTORY API to refresh information
         * @return false|void
         */
        function loadPlatformUserWithMCPToken(string $mcp_token, string $integration_key, string $authenticator_code='', bool $refresh=false)
        {
            // Reset $user variables
            $this->reset();

            //region SET $user,$namespace,$key from token structure or return errorCode: WRONG_TOKEN_FORMAT
            $tokenParts = explode('__',$mcp_token);
            if(count($tokenParts) != 3
                || !($namespace=$tokenParts[0])
                || !($user_token=$tokenParts[1])
                || !($key=$tokenParts[2]) )
                return($this->addError('WRONG_TOKEN_FORMAT','The structure of the token is not right'));
            //endregion

            //region SET $userData trying to get the info from cache deleting expired tokens and checking $this->maxTokens
            $updateCache = false;
            $userData = $this->core->cache->get($namespace.'_'.$user_token);
            if(!is_array($userData['mcp_tokens']??null) || $refresh){
                $userData = ['id'=>null,'mcp_tokens'=>[],'data'=>[]];
            }
            else {
                $now = microtime(true);
                $num_tokens = 0;
                foreach ($userData['mcp_tokens'] as $tokenId=>$tokenInfo) {
                    if(($now - $tokenInfo['time']) > $this->cacheExpiresIn) {
                        unset($userData['mcp_tokens'][$tokenId]);
                        $updateCache = true;
                    } else {
                        $num_tokens++;
                        if(!isset($userData['mcp_tokens'][$mcp_token]) && $num_tokens>=$this->maxTokens) {
                            unset($userData['mcp_tokens'][$tokenId]);
                            $updateCache = true;
                        }
                    }
                }

                //update Cache with the deleted tokens
                if($updateCache) $this->core->cache->set($namespace.'_'.$user_token,$userData);

                $this->cached = true;
            }
            if($userData['mcp_tokens'][$mcp_token]['error']??null)
                unset($userData['mcp_tokens'][$mcp_token]);
            // the derived platform token has expired: validate again to get a new one instead of returning an expired one
            if(!empty($userData['mcp_tokens'][$mcp_token]['expires']) && $userData['mcp_tokens'][$mcp_token]['expires'] <= time())
                unset($userData['mcp_tokens'][$mcp_token]);
            //endregion

            //region CALL CloudFramework API Service IF $token DOEST not exist in $userData OR $refresh
            if(!isset($userData['mcp_tokens'][$mcp_token])  || $authenticator_code || $refresh) {

                $this->cached = false;
                $userData['mcp_tokens'][$mcp_token] =
                    ['error'=>null,'time'=>microtime(true)
                    ];

                $params = ['Fingerprint'=>$this->core->system->getRequestFingerPrint()];
                if($authenticator_code) $params['google_authenticator_code'] = $authenticator_code;
                $cfUserInfo = $this->core->request->post_json_decode(
                    $this::APIServices.'/'.$namespace.'/mcp?_user_data&loadPlatformUserWithMCPToken'
                    ,$params
                    ,['X-WEB-KEY'=>'Core7.loadPlatformUserWithMCPToken'
                    ,'Authorization'=>'Bearer '.$mcp_token
                    ,'X-EXTRA-INFO'=>$integration_key
                ]);

                if($this->core->request->error) {
                    $userData['mcp_tokens'][$mcp_token]['error'] = $this->core->request->errorMsg;
                    $this->addError($cfUserInfo['code']??$this->core->request->getLastResponseCode(), $cfUserInfo['message']??$this->core->request->errorMsg);
                }elseif(($cfUserInfo['data']['google_authenticator_code'])??null) {
                    $this->id = $cfUserInfo['data']['user_id']??null;
                    $this->data['google_authenticator_code'] = 'required';
                    return true;
                }elseif(!($cfUserInfo['data']['token'])) {
                    $userData['mcp_tokens'][$mcp_token]['error'] = ['Missing data.token in the response',$cfUserInfo];
                    $this->addError('conflict', $userData['mcp_tokens'][$mcp_token]['error']);
                }else {
                    $userData['mcp_tokens'][$mcp_token]['token'] = $cfUserInfo['data']['token'];
                    // expiration of the derived platform token. Only a future value is kept
                    $expires = intval($cfUserInfo['data']['User']['Expires']??0);
                    $userData['mcp_tokens'][$mcp_token]['expires'] = ($expires > time())?$expires:null;
                    $userData['data'] =  $cfUserInfo['data'];
                    $userData['id'] = $cfUserInfo['data']['User']['KeyName'];
                }
                $this->core->request->reset();
                $this->core->cache->set($namespace.'_'.$user_token,$userData);
                if($this->error) return false;
            }
            //endregion

            //region SET $this->{isAuth, namespace, token, id, data}
            $this->isAuth = true;
            $this->token = $userData['mcp_tokens'][$mcp_token]['token'];
            $this->activeTokens = count($userData['mcp_tokens']);
            $this->namespace = $namespace;
            $this->id = $userData['id'];
            $this->data = $userData['data'];
            //$this->expirationTime = $userData['data']['User']['Expires']??time();
            $this->tokenExpiresIn = ($userData['data']['User']['Expires']??time())-time();
            $this->cachedTokenExpiresIn =  intval($this->cacheExpiresIn - (microtime(true)-$userData['mcp_tokens'][$mcp_token]['time']));
            unset($userData);
            $this->core->namespace = $namespace;
            //endregion

            return true;

        }

        /**
         * Verify that a token is valid and update the following properties: namespace, userId, userData
         * It allows to work with several active tokens at the same time
         * @param string $token Token to verify with CloudFramework ERP
         * @param string $integration_key Integration Key to call CloudFramework API for signing
         * @param bool $refresh indicates if we have to ignore cached data with $refresh=true. Default is false
         * @return false|void
         */
        function checkWebToken(string $token, string $integration_key,bool $refresh=false)
        {
            // Reset $user variables
            $this->reset();


            //region SET $user,$namespace,$key from token structure or return errorCode: WRONG_TOKEN_FORMAT
            $tokenParts = explode('__',$token);
            if(count($tokenParts) != 3
                || !($namespace=$tokenParts[0])
                || !($user_token=$tokenParts[1])
                || !($key=$tokenParts[2]) )
                return($this->addError('WRONG_WEB_TOKEN_FORMAT','The structure of the token is not right'));
            //endregion

            //region SET $userData trying to get the info from cache deleting expired tokens and checking $this->maxTokens
            $updateCache = false;
            $userData = $this->core->cache->get($namespace.'_'.$user_token);
            if(!$userData) $userData = ['id'=>null,'tokens'=>[],'data'=>[]];
            else {
                $now = microtime(true);
                $num_tokens = 0;
                foreach ($userData['tokens'] as $tokenId=>$tokenInfo) {
                    if(($now - $tokenInfo['time']) > $this->cacheExpiresIn) {
                        unset($userData['tokens'][$tokenId]);
                        $updateCache = true;
                    } else {
                        $num_tokens++;
                        if(!isset($userData['tokens'][$token]) && $num_tokens>=$this->maxTokens) {
                            unset($userData['tokens'][$tokenId]);
                            $updateCache = true;
                        }
                    }
                }

                //update Cache with the deleted tokens
                if($updateCache) $this->core->cache->set($namespace.'_'.$user_token,$userData);

                // Verify $token is not a token with error
                if(isset($userData['tokens'][$token]) && $userData['tokens'][$token]['error']) {
                    return($this->addError('TOKEN_NOT_VALID',['The token already has been used but it is not valid',$userData['tokens'][$token]['error']]));
                }

                // Verify number of active tokens
                if(!isset($userData['tokens'][$token]) && count($userData['tokens']) >=$this->maxTokens) {
                    return($this->addError('MAX_TOKENS_REACHED','The max number of tokens has been reached: '.$this->maxTokens));
                }

                $this->cached = true;

            }
            //endregion

            //region CALL CloudFramework API Service IF $token DOEST not exist in $userData OR $refresh
            if(!isset($userData['tokens'][$token]) || $refresh) {

                $this->cached = false;
                $userData['tokens'][$token] = ['error'=>null,'time'=>microtime(true)];
                $cfUserInfo = $this->core->request->post_json_decode(
                    $this::APIPortalServices.'/'.$namespace.'/web-oauth/'.$this->core->id.'?_update&from_core7_user_object'
                    ,['Fingerprint'=>$this->core->system->getRequestFingerPrint()]
                    ,['X-WEB-KEY'=>'Core7UserObject'
                    ,'X-DS-TOKEN'=>$token
                    ,'X-EXTRA-INFO'=>$integration_key
                ]);

                if($this->core->request->error) {
                    $userData['tokens'][$token]['error'] = $this->core->request->errorMsg;
                    if($this->core->request->getLastResponseCode()==401) {
                        $this->addError('SECURITY_ERROR','Token is not authorized');
                    } else {
                        $this->addError('TOKEN_ERROR_'.$this->core->request->getLastResponseCode(),$this->core->request->errorMsg);
                    }
                } else {
                    $userData['data'] =  $cfUserInfo['data'];
                    $userData['id'] = $cfUserInfo['data']['User']['KeyName'];
                }
                $this->core->request->reset();
                $this->core->cache->set($namespace.'_'.$user_token,$userData);
                if($this->error) return;
            }
            //endregion

            //region SET $this->{isAuth, namespace, token, id, data}
            $this->isAuth = true;
            $this->token = $token;
            $this->activeTokens = count($userData['tokens']);
            $this->namespace = $namespace;
            $this->id = $userData['id'];
            $this->data = $userData['data'];
            unset($userData);
            $this->core->namespace = $namespace;
            //endregion

            return true;

        }

        /**
         * Update UserData in cache and in the Token of the user
         * @return true|null
         */
        function updateERPUserDataInCache()
        {
            if(!$this->core->user->token) return($this->addError('WRONG_TOKEN_FORMAT','The user does not have any token'));
            $tokenParts = explode('__',$this->core->user->token);
            if(count($tokenParts) != 3
                || !($namespace=$tokenParts[0])
                || !($user_token=$tokenParts[1])
                || !($key=$tokenParts[2]) )
                return($this->addError('WRONG_TOKEN_FORMAT','The structure of the token is not right'));
            //endregion

            if($cache_data = $this->core->cache->get($namespace.'_'.$user_token)) {
                $cache_data['data']['User'] = $this->data['User'];
                $this->core->cache->set($namespace.'_'.$user_token,$cache_data);
                $this->core->security->updateDSToken($this->token, $this->core->user->data['User'] );
            }
            return true;
        }


        /**
         * Return if the user is Authenticated
         * @return bool
         */
        function isAuth()
        {
            return $this->isAuth;
        }

        /**
         * Set a privilege for a user
         * @param $privilege
         * @param bool $active
         */
        function setPrivilege($privilege,$active=true)
        {
            if(!is_array($this->data['User']['UserPrivileges']??null)) $this->data['User']['UserPrivileges'] = [];
            if(!in_array($privilege,$this->data['User']['UserPrivileges'])) $this->data['User']['UserPrivileges'][] = $privilege;
        }

        /**
         * Unset a privilege for a user
         * @param $privilege
         * @param bool $active
         */
        function unsetPrivilege($privilege)
        {
            if(!is_array($this->data['User']['UserPrivileges']??null)) $this->data['User']['UserPrivileges'] = [];
            if(($index = array_search($privilege,$this->data['User']['UserPrivileges']))!==false)
                array_splice($this->data['User']['UserPrivileges'],$index,1);
        }

        /**
         * Tell if the user has specific privilege
         * @param string $privilege
         * @return bool
         */
        function hasPrivilege(string $privilege)
        {
            if(!is_array($this->data['User']['UserPrivileges']??null)) $this->data['User']['UserPrivileges'] = [];
            return (in_array($privilege,$this->data['User']['UserPrivileges']));
        }

        /**
         * Tell if the user has any $privileges.
         * @param string|array $privileges It can be an array of string or a string with privileges separated by ','
         * @return bool
         */
        function hasAnyPrivilege($privileges)
        {
            if(!is_array($privileges)) $privileges = explode(',',$privileges);
            $found = false;
            foreach ($privileges as $privilege) if(is_string($privilege)){
                if($this->hasPrivilege($privilege)) return true;
            }
            return false;
        }

        /**
         * Set a privilege for a user
         * @param string $privilege
         * @return bool
         */
        function getPrivileges()
        {
            return $this->data['User']['UserPrivileges']??[];
        }

        /**
         * Get the value of a specific variable from the UserVariables array
         *
         * @version ^8.3.44
         * @param string $variable The name of the variable to retrieve
         *
         * @return mixed|null The value of the specified variable if found, otherwise null
         */
        function getVariable(string $variable)
        {
            if(!is_array($this->data['User']['UserVariables']??null))
                $this->data['User']['UserVariables'] = [];
            return $this->data['User']['UserVariables'][$variable]??null;
        }

        /**
         * Get the specified variable from the UserVariables array of the User data.
         * If the UserVariables array is not an array, initialize it as an empty array.
         *
         * @version ^8.3.44
         * @return array The UserVariables array that contains the specified variable
         */
        function getVariables()
        {
            if(!is_array($this->data['User']['UserVariables']??null))
                $this->data['User']['UserVariables'] = [];
            return $this->data['User']['UserVariables'];
        }

        /**
         * Set a variable in the UserVariables array of the User data
         *
         * @version ^8.3.44
         * @param string $variable The name of the variable to set
         * @param mixed $value The value to assign to the variable
         *
         * @return void
         */
        function setVariable(string $variable,$value)
        {
            if(!is_array($this->data['User']['UserVariables']??null))
                $this->data['User']['UserVariables'] = [];
            $this->data['User']['UserVariables'][$variable] = $value;
        }

        /**
         * Unset a specific variable from the UserVariables array in the data.
         *
         * @version ^8.3.44
         * @param string $variable The variable to unset from the UserVariables array.
         *
         * @return void|null Returns null if the UserVariables array is not an array, else unsets the specified variable if it exists.
         */
        function unsetVariable(string $variable)
        {
            if(!is_array($this->data['User']['UserVariables']??null))
                $this->data['User']['UserVariables'] = [];
            if(isset($this->data['User']['UserVariables'][$variable]))
                unset($this->data['User']['UserVariables'][$variable]);
        }

        /**
         * Set the organization for the user and add it to the list of UserOrganizations if not already present.
         *
         * @version ^8.3.44
         * @param string $organization The organization to be set for the user.
         * @param bool $active Whether the organization should be active or not. Default is true.
         *
         * @return void
         */
        function setOrganization(string $organization)
        {
            if(!is_array($this->data['User']['UserOrganizations']??null)) $this->data['User']['UserOrganizations'] = [];
            if(!in_array($organization,$this->data['User']['UserOrganizations'])) $this->data['User']['UserOrganizations'][] = $organization;
        }

        /**
         * Removes the specified organization from the User's list of organizations.
         *
         * @version ^8.3.44
         * @param string $organization The organization to be removed.
         *
         * @return void
         */
        function unsetOrganization(string $organization)
        {
            if(!is_array($this->data['User']['UserOrganizations']??null)) $this->data['User']['UserOrganizations'] = [];
            if(($index = array_search($organization,$this->data['User']['UserOrganizations']))!==false)
                array_splice($this->data['User']['UserOrganizations'],$index,1);
        }

        /**
         * Check if the user belongs to a specific organization.
         *
         * @version ^8.3.44
         * @param string $organization The organization to check for.
         *
         * @return bool Returns true if the user belongs to the specified organization, false otherwise.
         */
        function hasOrganization(string $organization)
        {
            if(!is_array($this->data['User']['UserOrganizations']??null)) $this->data['User']['UserOrganizations'] = [];
            return (in_array($organization,$this->data['User']['UserOrganizations']??[]));
        }

        /**
         * Check if the user has any of the given organizations.
         *
         * @version ^8.3.44
         * @@param string|array $organizations The organization(s) to check. If a string is provided, it will be split by comma (,).
         *
         * @return bool Whether the user has any of the specified organizations.
         */
        function hasAnyOrganization(string|array $organizations): bool
        {
            if(!is_array($organizations)) $organizations = explode(',',$organizations);
            $found = false;
            foreach ($organizations as $organization) if(is_string($organization)){
                if($this->hasOrganization($organization)) return true;
            }
            return false;
        }

        /**
         * Retrieves the organizations associated with the user.
         *
         * @version ^8.3.44
         * @return array    The user's organizations, or an empty array if no organizations are found.
         */
        function getOrganizations()
        {
            if(!is_array($this->data['User']['UserOrganizations']??null)) $this->data['User']['UserOrganizations'] = [];
            return $this->data['User']['UserOrganizations']??[];
        }


        /**
         * Set a license for the user and mark it as active if specified
         *
         * @param string $license The license to set for the user
         * @param bool $active (Optional) Whether the license should be marked as active. Defaults to true.
         *
         * @return void
         */
        function setLicense(string $license, $active=true)
        {
            if(!is_array($this->data['User']['UserLicenses']??null)) $this->data['User']['UserLicenses'] = [];
            if(!in_array($license,$this->data['User']['UserLicenses'])) $this->data['User']['UserLicenses'][] = $license;
        }

        /**
         * Check if the user has a specific license.
         *
         * @param string $license The license to check for.
         *
         * @return bool Returns true if the user has the specified license, false otherwise.
         */
        function hasLicense(string $license)
        {
            if(!is_array($this->data['User']['UserLicenses']??null)) $this->data['User']['UserLicenses'] = [];
            return (in_array($license,$this->data['User']['UserLicenses']));
        }

        /**
         * Unset a license from the User's UserLicenses array.
         *
         * @param string $license The license to be removed from the User's UserLicenses array.
         *
         * @return void
         */
        function unsetLicense(string $license)
        {
            if(!is_array($this->data['User']['UserLicenses']??null)) $this->data['User']['UserLicenses'] = [];
            if(($index = array_search($license,$this->data['User']['UserLicenses']))!==false)
                array_splice($this->data['User']['UserLicenses'],$index,1);
        }

        /**
         * Get the licenses associated with the user
         *
         * This method retrieves the licenses associated with the user. If the user does not have any licenses, an empty array will be returned.
         *
         * @return array An array of user licenses, or an empty array if no licenses are found
         */
        function getLicenses()
        {
            if(!is_array($this->data['User']['UserLicenses']??null)) $this->data['User']['UserLicenses'] = [];
            return $this->data['User']['UserLicenses']??[];
        }

        /**
         * Get the language of the current user from the data.
         * If the user's language is not found in the data, 'en' (English) is returned as a default.
         *
         * @return string The language code of the current user, or 'en' if not found
         */
        function getLang()
        {
            return $this->data['User']['UserLang']??'en';
        }

        /**
         * Check if the current user is a portal user based on certain conditions
         *
         * @return bool Returns true if the user is a portal user, otherwise returns false
         */
        function isPortalUser(): bool
        {
            return ($this->data['User']['PortalUser']??null)
                && ($this->data['User']['KeyId']??false)
                && $this->isAuth()
                && $this->token;
        }

        /**
         * Retrieves the email address of the user from the data array from PLATFORM User or PORTAL User
         * @return string|null The email address of the user or null if not found.
         */
        function getEmail(): ?string
        {
            return ($this->data['User']['UserEmail']??($this->data['User']['Email']??null));
        }

        /**
         * Check if the user is a platform user by verifying certain conditions
         *
         * @return bool Returns true if the user is a platform user based on specific data conditions, otherwise returns false.
         */
        function isPlatformUser(): bool
        {
            return !($this->data['User']['PortalUser']??false)
                && ($this->data['User']['KeyName']??false)
                && $this->isAuth()
                && $this->token;
        }

        /**
         * Get de TimeZone of the user. UTC by default
         * @return string
         */
        function getTimeZone(): string
        {
            return ($this->data['User']['UserTimeZone']??'UTC')?:'';
        }

        /**
         * Get the formatted date and time based on the specified time zone and date time
         *
         * @param string $time_zone The time zone to use for formatting the date and time
         * @param string $date_time The date and time string to convert (optional)
         *
         * @return string Returns the formatted date and time string ('Y-m-d H:i:s') in the user's time zone,
         * or an error return an error message if there was an issue converting the date and time
         */
        function getDateTime(string $time_zone='',string $date_time='')
        {
            if(!$time_zone) $time_zone = $this->getTimeZone();
            $user_time_zone = $this->getTimeZone();
            try {
                if(!$date_time)
                    $date = (new DateTime('now', new DateTimeZone($time_zone)));
                else
                    $date = (new DateTime($date_time, new DateTimeZone($time_zone)));
                if($user_time_zone != $time_zone) {
                    $date->setTimezone(new DateTimeZone($user_time_zone));
                }
                return $date->format('Y-m-d H:i:s');
            }catch (Exception $e) {
                return "Error to convert datetime from zone [{$time_zone}] to UserTimeZone [{$user_time_zone}] ".$date_time;
            }
        }

        /**
         * Set a lang for a user
         * @param string $lang
         */
        function setLang(string $lang)
        {
            $this->data['User']['UserLang'] = $lang;
        }


        /**
         * Retrieve user data based on the given key or return all user data if no key is provided.
         *
         * @param string $key The specific key of the user data to retrieve. Defaults to an empty string.
         * @return mixed The value associated with the specified key, all user data if no key is provided, or null if the key does not exist.
         */
        public function getUserData(string $key='')
        {
            return $key?($this->data[$key]??null):$this->data;
        }

        /**
         * Add Info to User data and try to store it in cache
         * @param string $key Key name for the array of user data
         * @param mix $data data to store
         * @return void
         */
        public function addUserData(string $key,$data) {
            $this->data[$key] = $data;

            //region EVALUATE to store in cache the new data added
            if($this->token) {
                //region SET $user,$namespace,$key from token structure or return errorCode: WRONG_TOKEN_FORMAT
                $tokenParts = explode('__',$this->token);
                if(count($tokenParts) != 3
                    || !($namespace=$tokenParts[0])
                    || !($user_token=$tokenParts[1])
                    || !($key=$tokenParts[2]) )
                    return($this->addError('WRONG_TOKEN_FORMAT','The structure of the token is not right'));
                //endregion

                if($userData = $this->core->cache->get($namespace.'_'.$user_token)) {
                    $userData['data'] = $this->data;
                    $this->core->cache->set($namespace.'_'.$user_token,$userData);
                }
            }
            //endregion
        }

        /**
         * Add an error Message
         * @param $code
         * @param null $message
         * @return false to facilitate caller return
         */
        function addError($code,$message)
        {
            $this->error = true;
            $this->errorCode = $code;
            $this->errorMsg[] = $message;
            return false;
        }


    }

    /**
     * $this->core->localization Class to manage localizations
     * @package Core.localization
     */
    class CoreLocalization
    {
        protected $core;
        var $data = [];
        var $auto_reset = false;
        var $reset = false;
        var $reset_files = [];
        var $cacheExpiration = -1;
        private $init = false;
        var $error = false;
        var $errorMsg = [];

        var $api_service = null;
        var $api_namespace = 'cloudframework';
        var $api_user = 'user-unknown';
        var $api_lang = 'en';
        var $api_creation_credentials = [];
        var $localize_files = null;

        function __construct(Core7 &$core)
        {
            $this->core = $core;

        }

        /**
         * Se external API to feed localization tags
         * @param string $namespace
         * @param string $user
         * @param string $api
         */
        public function initLocalizationService($namespace='cloudframework', $lang='en',$user = null, $api='https://api.cloudframework.io/erp/localizations') {
            $this->api_service = $api;
            $this->api_lang = $lang;
            $this->api_namespace = $namespace;
            $this->api_user = ($user)?:($this->core->user->id?:'user-unknown');
        }

        /**
         * Get the current default lang for localizations
         * @return string
         */
        public function getDefaultLang() {
            return $this->api_lang;
        }

        /**
         * Get the current default lang for tags creation
         * @return string
         */
        public function getDefaultCreationLang() {
            return $this->api_lang;
        }


        /**
         * Set default lang for localizations
         * @param string $lang
         */
        public function setDefaultLang(string $lang) {
            $this->api_lang = $lang;
        }

        /**
         * Set credentials to create tags in the ERP
         * @param array $lang
         */
        public function setCreationCredentials(array $credentials) {
            $this->api_creation_credentials = $credentials;
        }

        /**
         * Set default langs to convert on tags creation
         * @param array $langs
         */
        public function setDefaultCreationToConvertLangs(array $langs) {
            $this->api_creation_to_convert_langs = $langs;
        }


        /**
         * Set default namespace for localizations
         * @param string $namespace
         */
        public function setDefaultNamespace(string $namespace) {
            $this->api_namespace = $namespace;
        }

        /**
         * Get a Localization code from a localization file
         * @param string $app_tag
         * @param string $lang
         * @param string $namespace [optional]
         * @return mixed|string
         */
        function getAppCats(string $lang='',$namespace='')
        {
            if(!$namespace) $namespace=$this->api_namespace?:'cloudframework';
            if(!$lang) $lang=$this->api_lang;
            $app_tags = $this->core->request->get_json_decode($this->api_service."/{$namespace}/{$this->api_user}/apps",['langs'=>$lang]);
            if($this->core->request->error) {
                $this->addError($this->core->request->errorMsg);
                $this->core->request->reset();
                return false;
            }
            return $app_tags['data'];
        }

        /**
         * Return localizations taking $text with tags included
         * @param string $text
         * @param string $lang
         * @param string $namespace [optional]
         * @return mixed|string
         */
        function changeTextWithTags(string &$text, string $lang='',$namespace='')
        {
            $pattern = '/({[^;}]+;[^;}]+;[^}]+})/';
            preg_match_all($pattern, $text, $matches, PREG_SET_ORDER);
            foreach ($matches as $match) {
                $text = str_replace($match[0], $this->getTag($match[1],$lang,$namespace), $text);
            }
        }

        /**
         * Apply localizations over $array with tags included
         * @param array $array
         * @param string $lang
         * @param string $namespace [optional]
         */
        function changeArrayWithTags(array & $array, string $lang='',$namespace='')
        {
            foreach ($array as $i=>$item) {
                if(is_string($item)) $array[$i] = $this->getTag($item,$lang,$namespace);
                elseif(is_array($item)) $this->changeArrayWithTags($array[$i],$lang,$namespace);
            }
        }


        /**
         * Get a Localization code from a localization file
         * @param string $tag the tag to translate with the pattern [{][$namepace:<namespace>,]<app_id>;<cat_id>;<tag_id>[;<subtag_id>][}]
         * @param array $values
         * @param bool $with_subtags
         * @param string $namespace [optional]
         * @return array|void
         */
        function createTags(string $tag, array $values, $with_subtags=false,$namespace='')
        {

            if(!$this->api_service) return $this->addError("Mising api_service. Use initLocalizationService()");
            $parts = explode(';',trim($tag));
            if(count($parts)!=3) return $this->addError("Wrong \$tag paramater. Use it with format [App;Cat;Tag]");
            if(!$namespace) $namespace=$this->api_namespace?:'cloudframework';
            if($with_subtags) {
                $values = ['subtags'=>$values];
            } else {
                $values = ['langs'=>$values];
            }
            $url = $this->api_service."/{$namespace}/{$this->api_user}/apps/{$tag}?with_subtags=".(($with_subtags)?1:0);
            $this->core->logs->add('Creating Localizations from API: '.$this->api_service."/{$namespace}/{$this->api_user}/apps/{$tag}",'localization_create_tag');
            $localizations = $this->core->request->post_json_decode($url,$values,$this->api_creation_credentials,true);
            if($this->core->request->error) {
                $this->addError($this->core->request->errorMsg);
                $this->core->request->reset();
                return $localizations;
            }
            return $localizations['data'];
        }


        /**
         * Get a Localization code from a localization file
         * If the tag is not found and $this->auto_reset is true it will try to load the dictionary agaain.
         * @param string $tag the tag to translate with the pattern [{][$namepace:<namespace>,]<app_id>;<cat_id>;<tag_id>[;<subtag_id>][}]
         * @param string $lang
         * @param string $namespace [optional]
         * @return mixed|string
         */
        function getTag(string $tag, string $lang='', $namespace='')
        {


            //region IT it has spaces return the different parts
            if(strpos($tag,' ')) {
                $_spaces = explode(' ',$tag);
                $ret ='';
                foreach ($_spaces as $i=>$item) {
                    if($i) $ret.=' ';
                    if($item) $ret.=$this->getTag($item,$lang,$namespace);
                    else $ret.=$item;
                }
                return $ret;
            }
            //endregion

            $source_tag = $tag;
            if( !preg_match('/^[^;]+;[^;]+;[^;]+/',$tag))  return $tag;

            // PROCESS $tag with {xxx} and return the result with recursive data
            $pattern = '/\{([^;\{\}]+;[^;]+;[^\}]+)\}/';
            if(strpos($tag,'{')!==false && strpos($tag,'}')!==false) {
                preg_match($pattern, $tag, $found);
                while ($found) {

                    $tag = str_replace($found[0], $this->getTag($found[1], $lang, $namespace), $tag);
                    preg_match($pattern, $tag, $found);
                }
                return $tag;
            }

            if(!$namespace) $namespace=$this->api_namespace?:'cloudframework';
            if(!$lang) $lang=$this->api_lang;

            //detect namespace value in tag.
            if(strpos($tag,'$namespace:')===0 && strpos($tag,',')) {
                list($namespace,$tag) = explode(',',$tag,2);
                $namespace = str_replace('$namespace:','',$namespace);
            }
            // return tags with chars not valid
            if(preg_match('/[^A-Z0-9a-z;_-]/',$tag) || !preg_match('/^[^;]+;[^;]+;[^;]+/',$tag)) {
                return $tag;
            }

            //set $locFile
            //split the tag in $parts
            $parts = explode(';',$tag);

            //if there are <2 $parts return $tag
            if(count($parts)<2) return $tag;

            //if the tag has the structure [{app};{cat};{code};]  (with no subcode), then it is an error
            if(isset($parts[3]) && !$parts[3]) return $tag;

            //read dictionary for "{$parts[0]};{$parts[1]}". If there is no dictionary return $tag
            $locFile = "{$parts[0]};{$parts[1]}";

            //read $locFile dictionary
            if(!$this->readLocalizationData($locFile,$lang,$namespace)) {
                $this->core->logs->add("{$locFile} does no exist",'localization_warning');
                return $tag;
            }

            //search
            $ret =  (count($parts)<3)?($this->data[$locFile][$lang]??$tag):($this->data[$locFile][$lang][$tag]??$tag);
            if($ret==$tag) {
                if($this->auto_reset && !$this->reset) {
                    $this->resetLocalizationsCache();
                    return $this->getTag($source_tag,$lang,$namespace);
                }
                $this->core->logs->add("[{$locFile}/{$lang}] - {$tag} does no exist",'localization_warning');
            }

            return $ret;
        }

        /**
         * @deprecated  Use getTag
         */
        function getCode(string $code, string $lang='',$namespace='') {return $this->getTag($code,$lang,$namespace);}


        /**
         * Get a Localization code from a localization file
         * @param string $tag
         * @param string $lang
         * @param string $namespace [optional]
         * @return mixed|string
         */
        function setTag(string $tag, string $value, string $lang='', $namespace='')
        {
            if(!$namespace) $namespace=$this->api_namespace?:'cloudframework';
            if(!$lang) $lang=$this->api_lang;

            // make compatible tags with $namespace
            if(strpos($tag,'$namespace:')===0 && strpos($tag,',')) {
                list($namespace,$tag) = explode(',',$tag,2);
            }
            $parts = explode(';',$tag);
            if(count($parts)<3) return false;
            $locFile = "{$parts[0]};{$parts[1]}";
            $this->data[$locFile][$lang][$tag] = $value;
            return true;
        }
        /**
         * @deprecated  Use getTag
         */
        function setCode(string $code, string $value, string $lang='',$namespace='') {return $this->setTag($code,$value,$lang,$namespace);}


        /**
         * Reset Cache for localizations. Every call to localizations will call API at least once
         */
        public function resetLocalizationsCache() {
            $this->reset = true;
            $this->reset_files = [];
            $this->localize_files = null;
            $this->data = [];
        }

        /**
         * Delete cache Data in $namespace
         * @param string $loc_file  optional. Specifical the loc_file (ex: '<app_id>;<cat_id>')
         * @param string $namespace optional. Default $this->api_namespace
         */
        public function deleteLocalizationsCache(string $loc_file='',$namespace='') {
            $this->resetLocalizationsCache();
            if(!$namespace) $namespace=$this->api_namespace?:'cloudframework';
            $this->localize_files = $this->core->cache->get('LOCALIZE_FILES_'.$namespace)?:[];
            foreach ($this->localize_files as $key=>$foo) {
                if($loc_file) {
                    if(strpos($key,"{$namespace}{$loc_file}")===0) {
                        $this->core->cache->delete('LOCALIZE_FILES_LANGS_' . $key);
                        unset($this->localize_files[$key]);
                    }
                } else {
                    $this->core->cache->delete('LOCALIZE_FILES_LANGS_' . $key);
                }
            }
            if(!$loc_file) {
                $this->core->cache->delete('LOCALIZE_FILES_'.$namespace);
                $this->localize_files = null;
            }
            else $this->core->cache->set('LOCALIZE_FILES_'.$namespace,$this->localize_files);
        }

        /**
         * @param string $locFile
         * @param string $lang
         * @param string $namespace
         */
        public function readLocalizationData(string $locFile,string $lang='',$namespace='') {
            if(!$namespace) $namespace=$this->api_namespace?:'cloudframework';
            if(!$lang) $lang=$this->api_lang;
            if(isset($this->data[$locFile][$lang])) return true;

            //read from cache files cached
            if($this->localize_files===null) $this->localize_files = $this->core->cache->get('LOCALIZE_FILES_'.$namespace,$this->cacheExpiration)?:[];

            //reset $this->localize_files[$namespace.$locFile.$lang] IF $this->reset force api read at least once
            if($this->reset && !isset($this->reset_files[$namespace.$locFile.$lang])) {
                $this->localize_files[$namespace.$locFile.$lang] = null;
                $this->reset_files[$namespace.$locFile.$lang] = true;
            }

            //read from api if $this->localize_files[$namespace.$locFile.$lang] is null
            if(($this->localize_files[$namespace.$locFile.$lang]??null)===null) {
                if($this->api_service) {
                    $this->core->logs->add('Reading Localizations from API: '.$this->api_service."/{$namespace}/{$this->api_user}/apps/{$locFile}?langs={$lang}",'localization_call');
                    $data = $this->core->request->get_json_decode($this->api_service."/{$namespace}/{$this->api_user}/apps/{$locFile}",['langs'=>$lang]);
                    if($this->core->request->error) {
                        $this->core->logs->add(['api'=>$this->api_service."/{$namespace}/{$this->api_user}/apps/{$locFile}",'error'=>$this->core->request->errorMsg],'error_readLocalizeData');
                        $this->core->request->reset();
                        return false;
                    }
                    if($data['data'][$lang]??null) {
                        $this->localize_files[$namespace.$locFile.$lang] = true;
                        $this->core->cache->set('LOCALIZE_FILES_'.$namespace,$this->localize_files);

                        $this->data[$locFile][$lang] = $data['data'][$lang];
                        $this->core->cache->set('LOCALIZE_FILES_LANGS_'.$namespace.$locFile.$lang,$this->data[$locFile][$lang]);
                    }
                }
            }
            else {
                $this->data[$locFile][$lang] = $this->core->cache->get('LOCALIZE_FILES_LANGS_'.$namespace.$locFile.$lang)?:[];
            }
            return ($this->data[$locFile][$lang]??null)?true:false;
        }

        function addError($value)
        {
            $this->error = true;
            $this->errorMsg[] = $value;
        }
    }

    /**
     * $this->core->model Class to manage Data Models
     * @package Core.model
     */
    Class CoreModel
    {
        var $error = false;
        var $errorMsg = null;
        var $errorCode = null;
        var $dbConnections = [];
        var $dbConnection = 'default';
        /** @var CloudSQL $db  */
        var $db = null;
        /** @var DataMongoDB $mongoDB  */
        var $mongoDB = null;
        var $cache = null;

        protected $core;
        /** @var array|null $models already read */
        var $models = null;

        /**
         * Class constructor
         * @param Core7 $core
         */
        function __construct(Core7 &$core)
        {
            $this->core = $core;
        }

        /**
         * Read models from specific Path
         * @param $path
         * @return bool
         */
        function readModels($path) {

            try {
                $data = json_decode(@file_get_contents($path), true);

                if (!is_array($data)) {
                    $this->addError('error reading ' . $path);
                    if (json_last_error())
                        $this->addError("Wrong format of json: " . $path);
                    elseif (!empty(error_get_last()))
                        $this->addError(error_get_last());
                    return false;
                } else {
                    $this->processModels($data);
                    return true;
                }
            } catch (Exception $e) {
                $this->addError(error_get_last());
                $this->addError($e->getMessage());
                return false;
            }

        }

        /**
         * Read models from CloudFramework
         * @param $models string Models separated by ,
         * @param $api_key string API Key of the licence
         * @param string $source optional parameter to send the platform id
         * @return boolean|void
         */
        function readModelsFromCloudFramework($models,$api_key,string $source='') {

            // To reset cache they have to call $this->resetCache();
            $ret_models = $this->getCache($models);
            //_printe('aaaa',$ret_models);
            if(!$ret_models || isset($ret_models['Unknown'])) {
                $ret_models =  $this->core->request->get_json_decode('https://api.cloudframework.io/erp/models/export',['models'=>$models,'source'=>($source?:$this->core->user->id.'_'.$this->core->user->namespace.'_'.($this->core->system->url['host']??'nohost'))],['X-WEB-KEY'=>$api_key]);
                if($this->core->request->error)  {
                    return($this->addError(['https://api.cloudframework.io/erp/models/export',$this->core->request->errorMsg]));
                }
                $ret_models = $ret_models['data'];
                if(!isset($ret_models['Unknown']))
                    $this->updateCache($models,$ret_models);
            }

            // SET $processed CFOs
            $processed=[];
            if( !$ret_models
                || isset($ret_models['Unknown'])
                || !($processed = $this->processModels($ret_models))) {

                if(isset($ret_models['Unknown']))
                    $this->addError('The following models are unknown: '.implode(',',array_keys($ret_models['Unknown'])));
                else
                    return ($this->addError($ret_models));
            }

            // EVALUATE $processed['extends'] and read pending CFOs
            if(is_array($processed['extends']??null))
                foreach ($processed['extends'] as $cfo=>$type) {
                    if(!($this->models["{$type}:{$cfo}"]??null)) {
                        $this->readModelsFromCloudFramework($cfo, $api_key, $source);
                        if($this->error) return false;
                    }
            }

            return $ret_models;
        }

        /**
         * Processes CFO objects and maps them to the corresponding models based on platform and type.
         *
         * @param array $CFOs List of CFO objects passed by reference. Each CFO is expected to contain keys like 'KeyName', 'interface',
         *                    'entity', 'type', etc., to construct the model associations.
         * @param array $models The associative array of models grouped by type (e.g., DataStoreEntities, DataBaseTables, Unknown, etc.).
         *                      This is modified to include information from each CFO based on its characteristics.
         * @param string $platform Describes the platform being used (e.g., 'cloudframework'). This modifies behavior, such as the 'cfo_locked' handling.
         *
         * @return bool Returns true after processing all CFOs correctly. Returns false if any error occurs during processing, such as missing
         *              mandatory keys (e.g., 'KeyName') in CFO data, which triggers `addError`.
         */
        public function processReadCFOsIntoModels(array $CFOs, array &$models, string $platform): bool
        {

            if(!isset($CFOs[0])) return $this->addError( 'processReadCFOs(..), [$CFOs] has to be an array of CFOs');
            foreach ($CFOs as $i=> $item) {

                if (!isset($item['KeyName'])) return $this->addError( 'processReadCFOs(..), [KeyName] is missing in cfo: ' . $i);

                $extends = $item['extends'] ?? null;
                $entity = $item['interface']['object'] ?? $item['entity'];
                $project_id = $item['interface']['project_id'] ?? null;
                $secret = $item['interface']['secret'] ?? ($item['lowCode']['secret'] ?? null);
                $namespace = $item['interface']['namespace'] ?? null;
                $dbName = $item['interface']['dbName'] ?? null;
                $bq = $item['model']['bq'] ?? null;
                $workFlows = $item['events'] ?? null;
                $hasExternalWorkFlows = ($item['hasExternalWorkflows'] ?? false) ? true : false;

                //set $cfo_locked only for cloudframework cfos
                $cfo_locked = ($platform=='cloudframework' && isset($item['securityAndFields']['security']['cfo_locked']))?($item['securityAndFields']['security']['cfo_locked']?1:0):null;

                if(isset($models['Unknown']))
                    if (key_exists($item['KeyName'], $models['Unknown'])) unset($models['Unknown'][$item['KeyName']]);

                if ($item['type'] == 'ds') {
                    $_type = 'DataStoreEntities';
                } elseif ($item['type'] == 'db') {
                    $_type = 'DataBaseTables';
                } elseif ($item['type'] == 'bq') {
                    $_type = 'BigqueryDataSets';
                } elseif ($item['type'] == 'mongodb') {
                    $_type = 'MongoDBCollections';
                } elseif ($item['type'] == 'api') {
                    $_type = 'APIUrls';
                } else {
                    $_type = 'Unknown';
                }

                //avoid to rewrite previous locked cfos in cloudframework
                if(isset($models[$_type][$item['KeyName']]['cfo'])) {
                    if($models[$_type][$item['KeyName']]['cfo_locked']??null) continue;
                    elseif(!isset($models[$_type][$item['KeyName']]['cfo_locked']) && ($extend_cfo = $models[$_type][$item['KeyName']]['extends']??null)) {
                        if($models[$_type][$extend_cfo]['cfo_locked']??null) continue;
                    }
                }

                // if of cfo
                $models[$_type][$item['KeyName']]['cfo'] = $item['KeyName'];
                // when the cfo has been read
                $models[$_type][$item['KeyName']]['read'] = date('Y-m-d H:i:s e');
                // from what platform
                $models[$_type][$item['KeyName']]['platform'] = $platform;
                // is the cfo locked in cloudframework
                if($cfo_locked!==null) $models[$_type][$item['KeyName']]['cfo_locked'] = $cfo_locked;
                // the cfo extends other
                if ($extends) $models[$_type][$item['KeyName']]['extends'] = $extends;
                //has specific project_id set?
                if ($project_id) $models[$_type][$item['KeyName']]['project_id'] = $project_id;
                //has a secret key defined
                if ($secret) $models[$_type][$item['KeyName']]['secret'] = $secret;
                //has a specific namespace
                if ($namespace) $models[$_type][$item['KeyName']]['namespace'] = $namespace;
                //what entity in databases will read
                $models[$_type][$item['KeyName']]['entity'] = $entity;
                //has a specific dbName?
                if ($_type == 'DataBaseTables' && $dbName) $models[$_type][$item['KeyName']]['dbName'] = $dbName;
                //describe de model
                $models[$_type][$item['KeyName']]['model'] = $item['model']['model'] ?? null;
                //has linked any bq entity
                if ($bq) $models[$_type][$item['KeyName']]['bq'] = $bq;
                //has any workflow?
                $models[$_type][$item['KeyName']]['workFlows'] = $workFlows;
                //has allowed external workflows?
                $models[$_type][$item['KeyName']]['hasExternalWorkFlows'] = $hasExternalWorkFlows;
            }

            return true;
        }

        /**
         * Process the model received in models
         * @param $models array
         * @return array with the structure
         *    ['cfos'=>[{cfo-id=>type}],'extends'=>[cfo-id=>type]]
         */
        public function processModels($models) {
            // $models has to be an array
            if(!is_array($models) || !$models) return [];

            // init $processed to report
            $processed = [];
            if(array_key_exists('DataBaseTables',$models) && is_array($models['DataBaseTables']))
                foreach ($models['DataBaseTables'] as $model=>$dataBaseTable) {
                    $this->models['db:'.$model] = ['type'=>'db','data'=>$dataBaseTable];
                    $processed['cfos'][$model] = 'db';
                    if($dataBaseTable['extends']??null) $processed['extends'][$dataBaseTable['extends']] = 'db';
                }
            if(array_key_exists('DataStoreEntities',$models) && is_array($models['DataStoreEntities']))
                foreach ($models['DataStoreEntities'] as $model=>$dsEntity) {
                    $this->models['ds:'.$model] = ['type'=>'ds','data'=>$dsEntity];
                    $processed['cfos'][$model] = 'ds';
                    if($dsEntity['extends']??null) $processed['extends'][$dsEntity['extends']] = 'ds';
                }
            if(array_key_exists('BigqueryDataSets',$models) && is_array($models['BigqueryDataSets']))
                foreach ($models['BigqueryDataSets'] as $model=>$bqDataset) {
                    $this->models['bq:'.$model] = ['type'=>'bq','data'=>$bqDataset];
                    $processed['cfos'][$model] = 'bq';
                    if($bqDataset['extends']??null) $processed['extends'][$bqDataset['extends']] = 'bq';

                }
            if(array_key_exists('MongoDBCollections',$models) && is_array($models['MongoDBCollections']))
                foreach ($models['MongoDBCollections'] as $model=>$mongoDBColletion) {
                    $this->models['mongodb:'.$model] = ['type'=>'mongodb','data'=>$mongoDBColletion];
                    $processed['cfos'][$model] = 'mongodb';
                    if($mongoDBColletion['extends']??null) $processed['extends'][$mongoDBColletion['extends']] = 'mongodb';

                }
            if(array_key_exists('JSONTables',$models) && is_array($models['JSONTables']))
                foreach ($models['JSONTables'] as $model=>$jsonEntity) {
                    $this->models['json:'.$model] = ['type'=>'json','data'=>$jsonEntity];
                    $processed['cfos'][$model] = 'json';
                    if($jsonEntity['extends']??null) $processed['extends'][$jsonEntity['extends']] = 'json';
                }

            if(array_key_exists('APIUrls',$models) && is_array($models['APIUrls']))
                foreach ($models['APIUrls'] as $model=>$apiUrl) {
                    $this->models['api:'.$model] = ['type'=>'api','data'=>$apiUrl];
                    $processed['cfos'][$model] = 'api';
                    if($apiUrl['extends']??null) $processed['extends'][$apiUrl['extends']] = 'api';
                }

            return $processed;
        }

        /**
         * @param string $object  We expect a '(db|ds):model_name' or just 'model_name'
         * @param array $options  optional options
         *    string $namespace says what namespace to use for datastore objects
         *    string $projectId says what project_id to use for datastore/bq objects
         *    string $cf_models_api_key is the API-KEY to use for CloudFrameworkDataModels and read the structure remotelly
         * @return DataStore|DataSQL|false
         */
        public function getModelObject(string $object,$options=[]) {

            //region Verify $object exists and it has a right structure
            if(!$object = $this->verifyModelStructureForObject($object,$options)) return false;
            //endregion

            //region SWITCH ($this->models[$object]['type']
            switch ($this->models[$object]['type']) {
                //region case db
                case "db":
                    //region SET $table
                    list($_foo,$table) = explode(':',$object,2);
                    //endregion

                    //region UPDATE $table IF $this->models[$object]['data']['interface']['object'] || $this->models[$object]['data']['entity']
                    if(isset($this->models[$object]['data']['entity'])) $table = $this->models[$object]['data']['entity'];
                    if(isset($this->models[$object]['data']['interface']['object'])) $table = $this->models[$object]['data']['interface']['object'];
                    //endregion

                    //region INIT AND RETURN $object_db as an object of Class DataSQL using [$table,$this->models[$object]['data']]
                    if(!is_object($object_db = $this->core->loadClass('DataSQL',[$table,$this->models[$object]['data']]))) return false;

                    return($object_db);
                    //endregion
                    break;
                //endregion

                //region case ds
                case "ds":

                    //region SET $table,$namespace
                    list($_foo,$entity) = explode(':',$object,2);
                    $namespace = (isset($options['namespace']))?$options['namespace']:$this->core->config->get('DataStoreSpaceName');
                    if(isset($this->models[$object]['data']['interface']['namespace']) && $this->models[$object]['data']['interface']['namespace'])
                        $namespace=$this->models[$object]['data']['interface']['namespace'];
                    if(empty($namespace)) return($this->addError('Missing DataStoreSpaceName config var or $options["namespace"] parameter'));
                    //endregion

                    //region UPDATE $entity IF $this->models[$object]['data']['interface']['object'] || $this->models[$object]['data']['entity']
                    if(isset($this->models[$object]['data']['entity'])) $entity = $this->models[$object]['data']['entity'];
                    if(isset($this->models[$object]['data']['interface']['object'])) $table = $this->models[$object]['data']['interface']['object'];
                    //endregion

                    //region SET $options['projectId'] IF it does not exists
                    if(!isset($options['projectId'])) {
                        $project_id = $this->core->config->get('core.gcp.datastore.project_id') ?? $this->core->gc_project_id;
                        $project_id = $this->models[$object]['data']['interface']['project_id'] ?? $project_id;
                        $options['projectId']=$project_id;
                    }
                    //endregion

                    //region VERIFY $options['keyFile'], $options['namespace']
                    if(!isset($options['keyFile']) && isset($this->models[$object]['data']['interface']['secret']) && $this->models[$object]['data']['interface']['secret']) {
                        $options['keyFile'] = $this->models[$object]['data']['interface']['secret'];
                        if($options['keyFile']['project_id']??null) $options['projectId'] = $options['keyFile']['project_id'];
                    }
                    if(!isset($options['namespace']) && isset($this->models[$object]['data']['interface']['namespace']) && $this->models[$object]['data']['interface']['namespace']) $options['namespace'] = $this->models[$object]['data']['interface']['namespace'];
                    //endregion


                    //region INIT AND RETURN $object_ds as an object of Class DataStore using [$entity,$namespace,$this->models[$object]['data'],$options]
                    if(!is_object($object_ds = $this->core->loadClass('DataStore',[$entity,$namespace,$this->models[$object]['data'],$options]))) return;

                    return($object_ds);
                    //endregion
                    break;
                //endregion

                //region case bq
                case "bq":

                    //region SET $dataset
                    list($_foo,$dataset) = explode(':',$object,2);
                    // rewrite name of the table
                    if(isset($this->models[$object]['data']['interface']['object'])) $dataset = $this->models[$object]['data']['interface']['object'];
                    //endregion

                    //region SET $options['projectId'] IF it does not exists
                    if(!isset($options['projectId'])) {
                        $project_id = $this->core->config->get('core.gcp.bigquery.project_id') ?? $this->core->gc_project_id;
                        $project_id = $this->models[$object]['data']['interface']['project_id'] ?? $project_id;
                        $options['projectId']=$project_id;
                    }
                    //endregion

                    //region VERIFY $options['keyFile']
                    if(!isset($options['keyFile']) && isset($this->models[$object]['data']['interface']['secret']) && $this->models[$object]['data']['interface']['secret']) {
                        $options['keyFile'] = $this->models[$object]['data']['interface']['secret'];
                        if($options['keyFile']['project_id']??null) $options['projectId'] = $options['keyFile']['project_id'];
                    }
                    //endregion

                    //region INIT AND RETURN $object_bq as an object of Class DataBQ using [$dataset,$this->models[$object]['data'],$options]
                    if(!is_object($object_bq = $this->core->loadClass('DataBQ',[$dataset,$this->models[$object]['data'],$options]))) return;
                    return($object_bq);
                    //endregion

                    break;
                //endregion
            }
            //endregion
            return null;
        }

        /**
         * Verifies the model structure of the given object identifier and ensures it adheres to an expected structure.
         * If necessary, it fetches missing model definitions using the provided API key
         * and resolves any external extensions or inherited properties within the model structure.
         *
         * @param string $object A model identifier in the format '[type:name]' or 'name' without a type. Valid types are 'db', 'ds', 'bq', 'api'.
         * @param array $options Optional parameters for extended functionality:
         *    string $cf_models_api_key Specifies the API key to use for fetching model definitions from a remote source.
         * @return string|false Returns the name of the object in the model with format 'type:name' or false ir any error
         **/
        public function verifyModelStructureForObject(string $object, ?array $options=[]): false|string
        {

            $source_object = $object;

            //region VERIFY $object has [type:name] string structure
            if(!strpos($object,':')) {
                if(isset($this->models['db:'.$object])) $object = 'db:'.$object;
                elseif(isset($this->models['ds:'.$object])) $object = 'ds:'.$object;
                elseif(isset($this->models['bq:'.$object])) $object = 'bq:'.$object;
                elseif(isset($this->models['api:'.$object])) $object = 'api:'.$object;
            }
            //endregion

            //region EVALUATE TO $this->readModelsFromCloudFramework if !$this->models[$object]
            if((!strpos($object,':') || !isset($this->models[$object])) && ($options['cf_models_api_key']??null) ) {
                if(!$this->readModelsFromCloudFramework(preg_replace('/.*:/','',$object),$options['cf_models_api_key']))
                    return false;
            }
            //endregion

            //region VERIFY again $object has [type:name] string structure
            if(!strpos($object,':')) {
                if(isset($this->models['db:'.$object])) $object = 'db:'.$object;
                elseif(isset($this->models['ds:'.$object])) $object = 'ds:'.$object;
                elseif(isset($this->models['bq:'.$object])) $object = 'bq:'.$object;
                elseif(isset($this->models['api:'.$object])) $object = 'api:'.$object;
            }
            //endregion

            //region VERIFY $object exists in $this->models and it has 'data' node
            // Let's find it and return
            if(!isset($this->models[$object])) {
                return($this->addError("Model [$source_object] has not been previously read or it does not exist",404));
            }

            if(!isset($this->models[$object]['data']))
                return($this->addError($object. 'Does not have data',503));
            //endregion

            list($type,$entity) = explode(':',$object,2);
            $this->core->model->models[$object]['data']['type'] = $type;
            $this->core->model->models[$object]['data']['id'] = $entity;

            //region EVALUATE $this->models[$object]['data']['extends'] and it has not been previously extended
            if(($model_extended = ($this->models[$object]['data']['extends']??null))
            && !isset($this->models[$object]['data']['extended_from'])) {

                $model_extended = $type.':'.$model_extended;
                if(!isset($this->models[$model_extended]) && isset($options['cf_models_api_key']) && $options['cf_models_api_key'] ) {
                    if(!$this->readModelsFromCloudFramework(preg_replace('/.*:/','',$model_extended),$options['cf_models_api_key'])) return false;
                }
                if(!isset($this->models[$model_extended])) {
                    return ($this->addError("Model extended [$model_extended] from model [$object] has not been previously read or it does not exist", 404));
                }

                // Rewrite hasExternalWorkFlows,workFlows and model if it is defined
                $this->models[$model_extended]['data']['hasExternalWorkFlows'] = (bool)(($this->models[$object]['data']['hasExternalWorkFlows'] ?? false));
                $this->models[$model_extended]['data']['workFlows'] =  $this->models[$object]['data']['workFlows']??null;
                if(isset($this->models[$object]['data']['model']) && $this->models[$object]['data']['model']) {
                    $this->models[$model_extended]['data']['model'] =  $this->models[$object]['data']['model'];
                }

                //Merge variables with the extended object.
                if(is_array($this->models[$object]['data']['interface']??null))
                    foreach ($this->models[$object]['data']['interface'] as $object_property=>$object_content) {
                        //merge objects
                        if(in_array($object_property,['fields'])) {
                            $this->models[$model_extended]['data']['interface'][$object_property] = array_merge($this->models[$model_extended]['data']['interface'][$object_property],$object_content);
                        }
                        //replace objects
                        else {
                            $this->models[$model_extended]['data']['interface'][$object_property] = $object_content;
                        }
                    }

                $this->models[$object]['data'] = array_merge(
                    ['extended_from'=>$this->models[$object]['data']['extends']],
                    array_merge(
                        $this->models[$model_extended]['data'],
                        array_merge(
                            $this->models[$object]['data'],
                            $this->models[$model_extended]['data']
                        )
                    )
                );
            }
            //endregion

            //region SET $this->models[$object]['data']['_verified'] to true
            $this->models[$object]['data']['_verified'] = true;
            //endregion

            return $object;
        }

        /**
         * Returns the array keys of the models
         * @return array
         */
        public function listmodels() {
            if(is_array($this->models)) return array_keys($this->models);
            else return [];
        }

        /**
         * Init a Mongo connection
         * @return bool
         */
        public function mongoInit($uri='') {

            if(null === $this->mongoDB) {
                $this->mongoDB = $this->core->loadClass('DataMongoDB',$uri);
                if(!$this->mongoDB->connect()) $this->addError($this->mongoDB->errorMsg);

            }
            return !$this->mongoDB->error;
        }

        /**
         * Init a DB connection
         * @param string $connection optional connection to use. By default $this->dbConnection
         * @param array $db_credentials optional connection to use. By default $this->dbConnection
         * @return bool
         */
        public function dbInit($connection ='',$db_credentials=[]): bool
        {

            //region EVALUATE $connection
            if($connection) $this->dbConnection = $connection;
            //endregion

            //region VERIFY $this->dbConnections[$this->dbConnection] exist
            if(!isset($this->dbConnections[$this->dbConnection])) {
                if(class_exists('CloudSQL'))
                    $this->dbConnections[$this->dbConnection] = new CloudSQL($this->core);
                else
                    $this->dbConnections[$this->dbConnection] = $this->core->loadClass('CloudSQL');

                if($db_credentials) {
                    $this->dbConnections[$this->dbConnection]->setConf('dbServer', $db_credentials['dbServer'] ?? null);
                    $this->dbConnections[$this->dbConnection]->setConf('dbSocket', $db_credentials['dbSocket'] ?? null);
                    $this->dbConnections[$this->dbConnection]->setConf('dbUser', $db_credentials['dbUser'] ?? null);
                    $this->dbConnections[$this->dbConnection]->setConf('dbPassword', $db_credentials['dbPassword'] ?? null);
                    $this->dbConnections[$this->dbConnection]->setConf('dbName', $db_credentials['dbName'] ?? null);
                    $this->dbConnections[$this->dbConnection]->setConf('dbPort', $db_credentials['dbPort'] ?? '3306');
                    $this->dbConnections[$this->dbConnection]->setConf('dbCharset', $db_credentials['dbCharset'] ?? null);
                    $this->dbConnections[$this->dbConnection]->setConf('dbProxy', $db_credentials['dbProxy'] ?? null);
                    $this->dbConnections[$this->dbConnection]->setConf('dbProxyHeaders', $db_credentials['dbProxyHeaders'] ?? null);
                    if(isset($db_credentials['dbSSL']))
                        $this->dbConnections[$this->dbConnection]->setConf('dbSSL', $db_credentials['dbSSL']);
                }
                if(!$this->dbConnections[$this->dbConnection]->connect()) $this->addError($this->dbConnections[$this->dbConnection]->getError());
            }
            //endregion

            //region SET $this->db = &$this->dbConnections[$this->dbConnection];
            $this->db = &$this->dbConnections[$this->dbConnection];
            //endregion

            //region RETURN !$this->db->error();
            return !$this->db->error();
            //endregion
        }

        /**
         * Excute the query and return the result if there is no errors
         * @param $SQL
         * @param $params
         * @param $types type format of the fields. Example: ['id':'int(10)']
         * @return array|void
         */
        public function dbQuery($title, $SQL, $params=[],$types=null) {

            if(!is_string($SQL)) return($this->addError('Wrong $SQL method parameter in: dbQuery($title, $SQL, $params=[]) '));
            // Verify we have the object created
            if(!$this->dbInit()) return($this->errorMsg);


            // Execute the query
            $ret = $this->db->getDataFromQuery($SQL,$params);
            if(!$this->db->_onlyCreateQuery)
                $this->core->logs->add($title.' -> '.$this->db->getQuery()." [{$this->db->_lastExecutionMicrotime} secs]",'dbQuery');
            if($this->db->error()) return($this->addError($this->db->getError()));
            else {

                // If there is no type defined return as is
                if(!$types || !is_array($types)) return $ret;
                // else cast it
                else {
                    $number_types = [];
                    foreach ($types as $field=>$type) {
                        if(is_array($type)) $type=$type[0];
                        if(strpos($type,'int')===0 || strpos($type,'bit')===0 ) $number_types[$field] = 'int';
                        else if(strpos($type,'number')===0 || strpos($type,'decimal')===0 || strpos($type,'float')===0) $number_types[$field] = 'float';
                    }

                    if($number_types)
                        foreach ($ret as $i=>$row) {
                            foreach ($row as $field=>$value) if(isset($number_types[$field]) && strlen($value??'')) {
                                if($number_types[$field]=='int') $value=intval($value);
                                elseif($number_types[$field]=='float') $value=floatval($value);
                                $ret[$i][$field] = $value;
                            }
                        }
                    return $ret;
                }
            }

        }

        /**
         * Update a record into the database
         * @param $title
         * @param $table
         * @param $data
         * @return bool|null|void
         */
        public function dbUpdate($title, $table, &$data) {

            $time = microtime(true);
            // Verify we have the object created
            if(!$this->dbInit()) return($this->errorMsg);

            // Execute the query
            $this->db->cfmode=false; // Deactivate Cloudframework mode.
            $this->db->cloudFrameWork('update',$data,$table);
            $time = round(microtime(true)-$time,4);
            $this->core->logs->add($title." [{$time} secs]",'dbUpdate');

            if($this->db->error()) return($this->addError($this->db->getError()));
            else return true;

        }

        /**
         * Execute a SQL command
         * @param $title
         * @param $q
         * @param $params
         * @return bool|null|void
         */
        public function dbCommand($title, $q,$params=[]) {

            $time = microtime(true);
            // Verify we have the object created
            if(!$this->dbInit()) return($this->errorMsg);

            // Execute the query
            $this->db->cfmode=false; // Deactivate Cloudframework mode.
            $this->db->command($q,$params);
            $time = round(microtime(true)-$time,4);
            $this->core->logs->add($title." [{$time} secs]",'dbCommand');
            if($this->db->error()) return($this->addError($this->db->getError()));
            else return true;

        }

        /**
         * Upsert a record into the database. If it exist rewrite it
         * @param $title
         * @param $table
         * @param $data
         * @return bool|null|void
         */
        public function dbUpsert($title, $table, &$data) {

            $time = microtime(true);
            // Verify we have the object created
            if(!$this->dbInit()) return($this->errorMsg);

            // Execute the query

            $this->db->cfmode=false; // Deactivate Cloudframework mode.
            if(!isset($data[0])) $data = [$data];
            foreach ($data as $record) {
                $this->db->cloudFrameWork('replace',$record,$table);
                $time = round(microtime(true)-$time,4);
                $this->core->logs->add($title." [{$time} secs]",'dbUpsert');
                if($this->db->error()) return($this->addError($this->db->getError()));
                $time = microtime(true);
            }

            return true;
        }

        /**
         * Insert
         * @param $title
         * @param $table
         * @param $data
         * @return bool|null|void
         */
        public function dbInsert($title, $table, &$data) {

            $time = microtime(true);
            // Verify we have the object created
            if(!$this->dbInit()) return($this->errorMsg);

            // Execute the query

            $this->db->cfmode=false; // Deactivate Cloudframework mode.
            if(!isset($data[0])) $data = [$data];
            foreach ($data as $record) {
                $this->db->cloudFrameWork('insert',$record,$table);
                $time = round(microtime(true)-$time,4);
                $this->core->logs->add($title." [{$time} secs]",'dbInsert');
                if($this->db->error()) return($this->addError($this->db->getError()));
                $time = microtime(true);
            }

            return $this->db->getInsertId();

        }

        /**
         * Delete a record into the database. If it exist rewrite it
         * @param $title
         * @param $table
         * @param $data
         * @return bool|null|void
         */
        public function dbDelete($title, $table, &$data) {

            $time = microtime(true);

            // Verify we have the object created
            if(!$this->dbInit()) return($this->errorMsg);

            // Execute the query
            $this->db->cfmode=false; // Deactivate Cloudframework mode.
            if(!isset($data[0])) $data = [$data];
            foreach ($data as $record) {
                $this->db->cloudFrameWork('delete',$record,$table);
                $time = round(microtime(true)-$time,4);
                $this->core->logs->add($title." [{$time} secs]",'dbDelete');
                if($this->db->error()) return($this->addError($this->db->getError()));
            }

            return true;
        }

        /**
         * Close Database connections.
         * @param string $connection Optional it specify to close a specific connection instead of all
         */
        public function dbClose(string $connection='') {
            foreach (array_keys($this->dbConnections) as $key) {
                if(!$connection || $connection==$key) $this->dbConnections[$key]->close();
            }
        }

        /**
         * Loads the cached data into the $cache property. If no cached data is found, it initializes $cache to an empty array.
         *
         * @return void
         */
        public function readCache(): void
        {
            if($this->cache === null)
                $this->cache = ($this->core->cache->get('Core7.CoreModel'))?:[];
        }

        /**
         * Reset the cache for all cfo models or for specific $cfo
         * @param string $cfo The key of the cache item to reset
         *                      If provided, will reset the specific cache item identified by this key
         * @return void  Returns nothing
         */
        public function resetCache(string $cfo=''): void
        {
            if($cfo) {
                $this->readCache();
                if(isset($this->cache[$cfo]))
                    unset($this->cache[$cfo]);
            }
            else {
                $this->cache = [];
            }
            $this->core->cache->set('Core7.CoreModel',$this->cache);
        }

        /**
         * Updates the cache with the specified variable and data.
         *
         * @param mixed $var The key of the cache item to update.
         * @param mixed $data The data to store in the cache.
         * @return void
         */
        public function updateCache($var,$data): void
        {
            $this->readCache();
            $this->cache[$var] = $data;
            $this->core->cache->set('Core7.CoreModel',$this->cache);
        }

        /**
         * Saves the current cache data into the Core7 caching system for persistent storage.
         * Updates the cache with the provided data if it exists as an array.
         *
         * @return void
         */
        public function saveCache(): void
        {
            if(is_array($this->cache))
                $this->core->cache->set('Core7.CoreModel',$this->cache);
        }

        /**
         * Retrieve a specific cache value based on the provided key.
         *
         * @param mixed $var The key used to identify the cached data.
         * @return mixed|null The cached value associated with the key if found, otherwise null.
         */
        public function getCache($var) {
            $this->readCache();
            if(isset($this->cache[$var])) return $this->cache[$var];
            else return null;
        }

        /**
         * Adds an error message, sets an error state, and returns false.
         *
         * @param string $msg The error message to register.
         * @param int $code Optional error code. Default is 0.
         * @return false Always returns false.
         */
        private function addError($msg,$code=0): false
        {
            $this->error = true;
            $this->errorCode = $code;
            $this->errorMsg[] = $msg;
            return false;
        }



    }

    /**
     * $this->core->cfiLog Class to Manage Datastore Logs and Bitacora Entries
     * @package Core.log
     */
    class CFILog
    {
        /** @var Core7 $core */
        var $core;

        /** @var DataStore $dsLogs */
        var $dsLogs;

        /** @var DataStore $dsBitacora */
        var $dsBitacora;

        var $error = false;
        var $errorMsg = null;

        function __construct(Core7 &$core)
        {
            $this->core = $core;
        }

        /**
         * Logs an action to the system's logging mechanism with optional Slack notification.
         *
         * @param string $app The application name or identifier where the action occurred.
         * @param string $action A description of the action performed (e.g., 'created', 'updated', 'deleted').
         * @param string $title The title or description of the action being logged.
         * @param string $method The HTTP method associated with the action (e.g., 'GET', 'POST').
         * @param null|string $user The identifier of the user who performed the action (if available).
         * @param null|string|array $data Additional data or context about the action to log.
         * @param null|string $slack_url Optional Slack webhook URL to send a notification about the action.
         * @param null|array $rewrite_fingerprint Optional fingerprint data to override the default request fingerprint.
         * @param null|string $id Optional identifier for the logged action.
         * @return array|false Returns the array entity if the log entry was successfully created; otherwise, false.
         */
        public function add(string $app, string $action, string $title, string $method, null|string $user, null|string|array $data=null, null|string $slack_url=null, null|array $rewrite_fingerprint=null,null|string $id=null) {
            if(!$this->initDSLogs()) return false;
            //region SET $rewrite_fingerprint
            $fingerprint = $this->core->system->getRequestFingerPrint();
            if(!isset($fingerprint['ip'])) $fingerprint['ip'] = $this->core->system->ip;
            if(!isset($fingerprint['http_referer'])) $fingerprint['http_referer'] = 'unknown';
            if($rewrite_fingerprint && is_array($rewrite_fingerprint)){
                if(!isset($rewrite_fingerprint['ip'])) $rewrite_fingerprint['ip'] = 'X.X.X.X';
                if(!isset($rewrite_fingerprint['http_referer'])) $rewrite_fingerprint['http_referer'] = 'unknown';
                $rewrite_fingerprint['local_fingerprint'] = $fingerprint;
            } else {
                $rewrite_fingerprint = $fingerprint;
            }
            // The fingerprint of the caller can come from outside (e.g. /core/logs): mask its URLs too (since 8.5.16)
            $rewrite_fingerprint = $this->maskFingerprintUrls($rewrite_fingerprint);
            //endregion

            //region SET $entity
            $entity = ["Host"=>(isset($_SERVER['HTTP_HOST']))?$_SERVER['HTTP_HOST']:'NO HTTP_HOST'];
            $entity["App"]=$app;
            $entity["Method"]=$method;
            $entity["DateInsertion"]="now";
            $entity["User"]=$user;
            $entity["Ip"]=$rewrite_fingerprint['ip'];
            $entity["Action"]=$action;
            $entity["Title"]=$title;
            $entity["Id"]=$id;
            $entity["JSON"]=[
                'url'=>(array_key_exists('REQUEST_URI',$_SERVER))?$this->core->system->maskUrl((string)$_SERVER['REQUEST_URI']):''
                ,'http_referer'=> $rewrite_fingerprint['http_referer']
                ,'data'=>$data
                ,'fingerprint'=>$rewrite_fingerprint
            ];
            //endregion

            // If slack_url. Send the post to slack_url
            if($slack_url) {
                $data_slack = ['text'=>"[$user]{$method}:{$app}: {$action}. {$title}"];
                $ret = $this->core->request->post_json_decode($slack_url,$data_slack,['Content-type'=> 'application/json'],true);
                if($this->core->request->error) {
                    $entity["JSON"]['slack_url_error'] = $this->core->request->errorMsg;
                    $this->core->logs->add($this->core->request->errorMsg,'slack_error');
                } else {
                    $this->core->logs->add('slack_url');
                    $entity["JSON"]['slack_url_ok'] = $ret;
                }
            }

            //region $this->dsLogs->createEntities($entity)
            $entity = $this->dsLogs->createEntities($entity)[0]??null;
            if ($this->dsLogs->error) {
                $this->addError($this->dsLogs->errorMsg);
                $this->core->logs->add(['data'=>$entity,'error'=>$this->dsLogs->errorMsg],'CFILog_add_error');
                return false;
            } else {
                $this->core->logs->add('CFILog_add_ok');
                return $entity;
            }
            //endregion
        }

        /**
         * Initializes data store logging models for CloudFrameWork logs and bitacora.
         * This method resets potential error states within the core model,
         * defines the necessary JSON schemas for logs and bitacora models,
         * processes those models, and assigns them to class properties.
         *
         * @return bool Returns true on successful initialization of the data store logging models.
         */
        private function initDSLogs(): bool
        {

            //reset Potential previous errors in core model
            $this->core->model->error = false;
            $this->core->model->errorMsg = null;
            $this->core->model->errorCode = null;


            if(is_object($this->dsLogs)) return true;
            $model = json_decode('{
                    "Host": ["string","index"],
                    "App": ["string","index"],
                    "Method": ["string","index"],
                    "DateInsertion": ["datetime","index|forcevalue:now"],
                    "Title": ["string","index"],
                    "User": ["string","index"],
                    "Ip": ["string","index"],
                    "Action": ["string","index"],
                    "Id": ["string","index|allownull"],
                    "JSON": ["json","allownull"]
                  }',true);
            $model_bitacora = json_decode('{
                    "Solution": ["string","index"],
                    "App": ["string","index"],
                    "AppId": ["string","index|allowNull"],
                    "DateInsertion": ["datetime","index|forcevalue:now"],
                    "User": ["string","index"],
                    "Action": ["string","index"],
                    "Title": ["string","index"],
                    "Ip": ["string","index"],
                    "JSON": ["json","allownull"]
                  }',true);

            $this->core->model->processModels(['DataStoreEntities'=>['CloudFrameWorkLogs'=>['model'=>$model],'CloudFrameWorkBitacora'=>['model'=>$model_bitacora]]]);
            if($this->core->model->error) return($this->addError($this->core->model->errorMsg));

            $this->dsLogs = $this->core->model->getModelObject('CloudFrameWorkLogs');
            if($this->core->model->error) return($this->addError($this->core->model->errorMsg));

            $this->dsBitacora = $this->core->model->getModelObject('CloudFrameWorkBitacora');
            if($this->core->model->error) return($this->addError($this->core->model->errorMsg));

            return true;
        }

        /**
         * Add a LOG entry in CloudFrameWorkLogs
         * @param $app
         * @param $action string 'ok', 'error', 'check'..
         * @param $title
         * @param $method
         * @param $user
         * @param $data
         * @param $platform
         * @param $api_key
         * @param true|false $rewrite_fingerprint // send the fingerprint of the current call
         * @return bool|void
         */
        public function sendToCFService($app, $action, $title, $method, $user, $data,$platform,$api_key,$rewrite_fingerprint=false) {
            //region SET $entity
            $entity = ["App"=>$app];
            $entity["Method"]=$method;
            $entity["User"]=$user;
            $entity["Action"]=$action;
            $entity["Title"]=$title;
            $entity["Data"]=$data;

            // Send the fingerprint from the call to rewrite in the recepcion  of the service
            if($rewrite_fingerprint)
                $entity["Fingerprint"]=$this->core->system->getRequestFingerPrint();
            //endregion

            $this->core->request->reset();
            $this->core->request->post_json_decode('https://api.cloudframework.io/core/logs/'.$platform,$entity,['X-WEB-KEY'=>$api_key]);
            if($this->core->request->error) return($this->addError($this->core->request->errorMsg));

            return true;
        }

        /**
         * Send a LOG to CF ERP Bitacora
         * @param string $user
         * @param $action string 'inserted'',updated', 'deleted', 'accessed'.. Try to add a past verb over a Solution/App
         * @param string $solution
         * @param string $app
         * @param null|string $app_id  Id of the app to which receive and action
         * @param null|string $title
         * @param null|mixed $data values to add in bitacora if it is necessary
         * @return bool|void
         */
        public function sendToCFBitacora(string $user, string $action,string $solution, string $app, ?string $app_id=null, ?string $title=null, $data=null) {
            $platform_id = $this->core->config->get('core.erp.platform_id');
            $token = $this->core->config->get('core.erp.integrations.token');
            $key = $this->core->config->get('core.erp.integrations.key');
            if(!$platform_id) return($this->addError('Missing core.erp.platform_id config-var from CFILog.sendToCFBitacora()'));
            if(!$token) return($this->addError('Missing core.erp.integrations.token config-var from CFILog.sendToCFBitacora()'));
            if(!$key) return($this->addError('Missing core.erp.integrations.key config-var from CFILog.sendToCFBitacora()'));

            //region SET $rewrite_fingerprint
            $fingerprint = $this->core->system->getRequestFingerPrint();
            if(!isset($fingerprint['ip'])) $fingerprint['ip'] = $this->core->system->ip;
            //endregion
            //region SET $title
            if(!$title) {
                $title = "The user {$user} has {$action}";
                if($app_id) $title.=" [Id: {$app_id}]";
                $title .= " in {$solution}/{$app}";
            }
            //endregion

            //region SET $entity
            $entity = [];
            $entity["User"]=$user;
            $entity["Action"]=$action;
            $entity["Solution"]=$solution;
            $entity["App"]=$app;
            $entity["AppId"]=$app_id;
            $entity["Fingerprint"]=$fingerprint;
            $entity["Title"]=$title;
            $entity["Data"]=[];
            if($data) {
                $entity['Data'] = $data;
            }
            //endregion

            $service = "https://api.cloudframework.io/erp/bitacora/{$platform_id}/".basename($this->core->system->app_url);
            $headers = ['X-DS-TOKEN'=>$token,'X-WEB-KEY'=>$key];
            $this->core->request->post_json_decode($service,$entity,$headers,true);
            if($this->core->request->error) {
                return($this->addError($this->core->request->errorMsg));
            }
            //endregion
            return true;
        }

        /**
         * Add a LOG entry in CloudFrameWorkBitacora
         * @param string $user
         * @param string $action 'inserted'',updated', 'deleted', 'accessed'.. Try to add a past verb over a Solution/App
         * @param string $solution
         * @param string $app
         * @param null|string $app_id string Id of the app to which receive and action
         * @param null|string $title
         * @param null|mixed $data  values to add in bitacora if it is necessary
         * @return integer|void It returns the KeyId of the Datastore
         */
        public function bitacora(string $user, string $action, string $solution, string $app, ?string $app_id=null, ?string $title=null, $data=null)
        {
            if(!$this->initDSLogs()) return;

            //region SET $rewrite_fingerprint
            $fingerprint = $this->maskFingerprintUrls($this->core->system->getRequestFingerPrint());
            if(!isset($fingerprint['ip'])) $fingerprint['ip'] = $this->core->system->ip;
            //endregion
            if(!$title) {
                $title = "The user {$user} has {$action}";
                if($app_id) $title.=" [Id: {$app_id}]";
                $title .= " in {$solution}/{$app}";
            }

            //region SET $entity
            $entity = [];
            $entity["User"]=$user;
            $entity["Action"]=$action;
            $entity["Solution"]=$solution;
            $entity["App"]=$app;
            $entity["AppId"]=$app_id;
            $entity["DateInsertion"]="now";
            $entity["Ip"]=$fingerprint['ip'];
            $entity["Title"]=$title;
            $entity["JSON"]=[
                'url'=>(array_key_exists('REQUEST_URI',$_SERVER))?$this->core->system->maskUrl((string)$_SERVER['REQUEST_URI']):''
                ,'http_referer'=> $fingerprint['http_referer']

            ];
            if($data) {
                $entity['JSON']['data'] = $data;
            }
            //endregion

            //region $this->dsBitacora->createEntities($entity)
            $entity = $this->dsBitacora->createEntities($entity)[0]??null;
            if ($this->dsBitacora->error) {
                $this->addError($this->dsBitacora->errorMsg);
                return($this->core->logs->add(['data'=>$entity,'error'=>$this->dsLogs->errorMsg],'CFILog_bitacora_error'));
            } else {
                $this->core->logs->add('CFILog_bitacora_ok');
                return $entity['KeyId'];
            }
            //endregion
        }

        /**
         * Masks the values of the sensitive query parameters in the URLs of a fingerprint ('uri', 'http_referer' and,
         * recursively, 'local_fingerprint') before it is stored. See CoreSystem::maskFingerPrint(). Since 8.5.16.
         *
         * @param mixed $fingerprint
         * @return mixed The masked array, or $fingerprint unchanged when it is not an array
         */
        private function maskFingerprintUrls($fingerprint)
        {
            return (is_array($fingerprint)) ? $this->core->system->maskFingerPrint($fingerprint) : $fingerprint;
        }

        /**
         * Logs an error message and updates the error state.
         *
         * @param string $msg The error message to be added.
         * @return bool Always returns false.
         */
        private function addError($msg) {
            $this->error = true;
            $this->errorMsg[] = $msg;
            return false;
        }

    }

    /**
     * Class to be extended for the creation of a logic application.
     *
     * Normally your file has to be stored in the `logic/` directory and extend this class.
     * @package Logic
     */
    Class CoreLogic2020
    {
        /** @var Core7 $core pointer to the Core class. `$this->core->...` */
        protected $core;

        /** @var string $method indicates the HTTP method used to access the script: GET, POST etc.. Default value is GET */
        var $method = 'GET';

        /** @var array $formParams Contains the variables passed in a GET,POST,PUT call intro an URL  */
        var $formParams = array();

        /** @var array $params contains the substrings paths of an URL script/param0/param1/..  */
        var $params = array();

        /**
         * @var boolean $error Indicates if an error has been produced
         */
        public $error = false;

        /** @var array $errorMsg Keep the error messages  */
        public $errorMsg = [];


        /**
         * CoreLogic constructor.
         * @param Core7 $core
         */
        function __construct(Core7 &$core)
        {
            // Singleton of core
            $this->core = $core;

            // Params
            $this->method = (array_key_exists('REQUEST_METHOD',$_SERVER)) ? $_SERVER['REQUEST_METHOD'] : 'GET';
            if ($this->method == 'GET') {
                $this->formParams = &$_GET;
                if (isset($_GET['_raw_input_']) && strlen($_GET['_raw_input_'])) $this->formParams = (count($this->formParams)) ? array_merge($this->formParams, json_decode($_GET['_raw_input_'], true)) : json_decode($_GET['_raw_input_'], true);
            } else {
                if (count($_GET)) $this->formParams = (count($this->formParams)) ? array_merge($this->formParam, $_GET) : $_GET;
                if (count($_POST)) $this->formParams = (count($this->formParams)) ? array_merge($this->formParams, $_POST) : $_POST;
                // POST
                $raw = null;
                if(isset($_POST['_raw_input_']) && strlen($_POST['_raw_input_'])) $raw = json_decode($_POST['_raw_input_'],true);
                if (is_array($raw)) $this->formParams = (count($this->formParams)) ? array_merge($this->formParams, $raw) : $raw;
                // GET
                $raw = null;
                if(isset($_GET['_raw_input_']) && strlen($_GET['_raw_input_'])) $raw = json_decode($_GET['_raw_input_'],true);
                if (is_array($raw)) $this->formParams = (count($this->formParams)) ? array_merge($this->formParams, $raw) : $raw;
                // RAW DATA
                $input = file_get_contents("php://input");
                if (strlen($input)) {
                    $this->formParams['_raw_input_'] = $input;

                    if (is_object(json_decode($input))) {
                        $input_array = json_decode($input, true);
                    } elseif(strpos($input,"\n") === false && strpos($input,"=")) {
                        parse_str($input, $input_array);
                    }

                    if (is_array($input_array)) {
                        $this->formParams = array_merge($this->formParams, $input_array);
                        unset($input_array);

                    }
                }
                // Trimming fields
                foreach ($this->formParams as $i=>$data) if(is_string($data)) $this->formParams[$i] = trim ($data);
            }

            $this->params = &$this->core->system->url['parts'];

        }

        /**
         * Try to render a twig template
         * @param string $template Path to the template
         */
        function render($template)
        {
            if(strpos($template,'.htm.twig')) {
                $template = str_replace('.htm.twig','',$template);
                /* @var $rtwig RenderTwig */
                $rtwig = $this->core->loadClass('RenderTwig');
                if(!$rtwig->error) {
                    $path = $this->core->system->app_path;
                    if($path[strlen($path)-1] != '/') $path.='/';
                    $template_path = $path . 'templates/';
                    if($this->core->config->get('core.path.templates')) $template_path = $this->core->config->get('core.path.templates').'/';
                    $rtwig->addFileTemplate($template,$template_path . $template);
                    $rtwig->setTwig($template);
                    echo $rtwig->render();
                } else {
                    $this->addError($rtwig->errorMsg);
                }
            } else {
                try {
                    $template_path = $this->core->system->app_path . '/templates/';
                    if($this->core->config->get('core.path.templates')) $template_path = $this->core->config->get('core.path.templates').'/';
                    include  $template_path. $template;
                } catch (Exception $e) {
                    $this->addError(error_get_last());
                    $this->addError($e->getMessage());
                }
            }

        }

        /**
         * Return the Value from $this->formParams
         * @params $var string var for $this->formParams
         * @return mixed|null returns $this->formParams[$var]
         */
        public function getFormParams($var) {
            return (isset($this->formParams[$var]))?$this->formParams[$var]:null;
        }

        /**
         * Return the Value from $this->params
         * @params $index string var for $this->params
         * @return mixed|null returns $this->params[$index]
         */
        public function getParams($index) {
            return (isset($this->params[$index]))?$this->params[$index]:null;
        }

        /**
         * Add an error in the class
         * @param $value
         * @return false always false to facilitate other calls
         */
        function addError($value): bool
        {
            $this->error = true;
            $this->core->errors->add($value);
            $this->errorMsg[] = $value;
            return false;
        }
    }

    /**
     * Class to be extended for the creation of a scripts
     *
     * The syntax is: `Class Logic extends CoreLogic2020() {..}`
     *
     *
     * Normally your file has to be stored in the `logic/` directory and extend this class.
     * @package Scripts
     */
    class Scripts2020 extends CoreLogic2020
    {
        /** @var array $argv Keep the arguments passed to the logic if it runs as a script  */
        public $argv = null;
        var $tests;
        /** @var CoreCache */
        var $cache = null;
        var $cache_secret_key = '';
        var $cache_secret_iv = '';
        var $cache_data = null;
        var $vars = [];
        var $sendTerminal=[];
        var $time = null;

        /**
         * Scripts constructor.
         * @param Core7 $core
         * @param null $argv
         */
        function __construct(Core7 $core, $argv=null)
        {

            parent::__construct($core);
            $this->argv = $argv;

            // Adding $vars
            foreach ($this->argv as $item) {
                if(strpos($item,'--')===0 && strpos($item,'=')) {
                    list($var,$value) = explode('=',$item,2);
                    $this->vars[$var] = $value;
                }
            }
            //endregion

            $this->cache = &$this->core->cache;
            $this->time = microtime(true);

        }

        /**
         * Check if a specific option is present in the argument list.
         *
         * @param string $option The option to check for.
         *
         * @return bool Returns true if the option is found in the argument list, false otherwise.
         */
        function hasOption(string $option): bool
        {
            return in_array('--'.$option, $this->argv);
        }

        /**
         * Retrieves the value of a specified option variable.
         *
         * @param string $option The name of the option variable to retrieve.
         *
         * @return mixed|null The value of the specified option variable, or null if the option variable does not exist.
         */
        public function getOptionVar($option) {
            return((isset($this->vars['--'.$option]))?$this->vars['--'.$option]:null);
        }

        /**
         * Send output to the terminal.
         * @param string|array $info Optional. The information to be displayed in the terminal. Default is an empty string.
         * @return bool Always returns true.
         */
        public function sendTerminal($info=''): bool
        {
            if(is_string($info)) echo $info."\n";
            else print_r($info);
            if($info)
                $this->sendTerminal[] = "[ ".(round(microtime(true)-$this->time,4))." ms] ".((is_string($info))?$info:json_encode($info));
            return true;
        }

        /**
         * Reads cache data from cache storage.
         *
         * Retrieves the cache data from the cache storage using specified parameters.
         * If cache data is not found or an error occurs, an empty array is returned.
         *
         * @return void
         */
        private function readCache() {
            $this->cache_data = ($this->cache->get('Core7_Scripts2020_cache',-1,'',$this->cache_secret_key,$this->cache_secret_iv))?:[];
        }

        /**
         * Clears the cache data by resetting it to an empty array.
         *
         * @return void
         */
        private function cleanCache(): void
        {
            $this->cache_data = [];
            $this->cache->set('Core7_Scripts2020_cache',$this->cache_data,'',$this->cache_secret_key,$this->cache_secret_iv);
        }

        /**
         * Get the value of a cache variable by name.
         *
         * @param string $var The name of the cache variable to retrieve.
         * @return mixed|null The value of the cache variable if found, or null if the variable does not exist.
         */
        public function getCacheVar($var) {
            if($this->cache_data === null) $this->readCache();
            return((isset($this->cache_data[$var]))?$this->cache_data[$var]:null);
        }

        /**
         * Sets a cache variable with the specified key and value.
         *
         * @param string $var The key of the cache variable to set.
         * @param mixed $value The value to set for the cache variable.
         *
         * @return void
         */
        public function setCacheVar($var, $value): void
        {
            if($this->cache_data === null) $this->readCache();
            $this->cache_data[$var] = $value;
            $this->cache->set('Core7_Scripts2020_cache',$this->cache_data,'',$this->cache_secret_key,$this->cache_secret_iv);
        }

        /**
         * Prompts the user with a title and optional default value, and allows caching of the user input.
         *
         * @param string $title The title of the prompt message.
         * @param mixed $default Optional. The default value to be used if user input is not provided.
         * @param string|null $cache_var Optional. The key for caching the user input.
         *
         * @return string The user input obtained from the prompt.
         */
        public function prompt($title,$default=null,$cache_var=null) {

            // Check Cache var
            if($cache_var) {
                $cache_content = $this->cache->get('Core7_Scripts2020_'.$cache_var,-1,'',$this->cache_secret_key,$this->cache_secret_iv);
                if($cache_content) $default = $cache_content;
            }

            // Check default value
            if($default) $title.="[{$default}] ";
            $ret = readline($title);
            if(!$ret) $ret=$default;

            // Set Cache var
            if($cache_var) {
                $this->cache->set('Core7_Scripts2020_'.$cache_var,$ret,'',$this->cache_secret_key,$this->cache_secret_iv);
            }
            return $ret;
        }

        /**
         * Prompt the user for a variable based on the specified options.
         *
         * @param array $options An array of options for prompting the user:
         *     - 'title': The title or prompt message for the variable.
         *     - 'default': The default value for the variable (optional).
         *     - 'cache_var': The key to store the prompted value in the cache (optional).
         *     - 'type': The type of input (e.g., 'password') (optional).
         *     - 'allowed_values': An array of allowed values for the input (optional).
         *
         * @return mixed The user input based on the specified options.
         */
        public function promptVar($options) {

            $title = (isset($options['title']) && $options['title'])?$options['title']:'missing title in prompt';
            $default = (isset($options['default']) && $options['default'])?$options['default']:null;
            $cache_var = (isset($options['cache_var']) && $options['cache_var'])?$options['cache_var']:null;
            $type = (isset($options['type']) && $options['type'])?$options['type']:null;
            $allowed_values = (isset($options['allowed_values']) && is_array($options['allowed_values']))?$options['allowed_values']:null;
            // Check rewrite $default
            if($cache_var && ($cache_content = $this->getCacheVar($options['cache_var']))) $default = $cache_content;

            // Check default value
            if($allowed_values) $title.= ' ['.implode(', ',$allowed_values).']';
            if($default) {
                if($type=='password') $title.=" (*******) :";
                else $title.=" ({$default}) :";
            } else {
                $title.=' :';
            }
            do {
                if($type=='password') {
                    system('stty -echo');
                    echo $title;
                    $ret = trim(fgets(STDIN));
                    echo "\n";
                    system('stty echo');
                    if(!$ret) $ret=$default;
                } else {
                    $ret = readline($title);
                    if(!$ret) $ret=$default;
                }
                $error = ($allowed_values && !in_array($ret,$allowed_values))?true:false;
            } while($error);


            // Set Cache var
            if($cache_var) $this->setCacheVar($cache_var,$ret);

            return $ret;
        }


        /**
         * Sets an error with the specified code and message to be handled by the code library.
         * This method has been created to facilitate the migration from APIs code
         *
         * @param int $code The error code to be set.
         * @param string $msg The error message associated with the code.
         *
         * @return bool Returns false to indicate that an error was set from the code library.
         */
        public function setErrorFromCodelib($code,$msg): bool
        {
            $this->sendTerminal('ERROR: '.$code);
            $this->sendTerminal([$code=>$msg]);
            $this->addError([$code=>$msg]);
            return false;
        }

        /**
         * Calls the specified method if it exists.
         *
         * @param string $method The name of the method to call.
         *
         * @return bool Returns true if the method exists and is called successfully, otherwise returns false.
         */
        public function useFunction(string $method): bool
        {
            if(method_exists($this,$method)) {
                $this->$method();
                return true;
            } else {
                return false;
            }
        }

        /**
         * Retrieves the Google access token for a specific user.
         *
         * @param string $user The user for whom to retrieve the access token. If not provided, it will be fetched from the cache or prompted.
         *
         * @return array|false The access token details, including token, email, information, and time it was obtained. False if error
         */
        public function getUserGoogleAccessToken(string $user=''): bool|array
        {
            //region VERIFY $user or prompt it
            if(!$user) {
                $user = $this->getCacheVar('user_readUserGoogleCredentials');
                do {
                    $user_new = $this->prompt('Give me your Google User Email: ', $user);
                } while (!$this->core->is->validEmail($user_new));
                $this->setCacheVar('user_readUserGoogleCredentials',$user_new);
                $user = $user_new;
            }
            $this->sendTerminal('Gathering Access Token for user: '.$user);
            //endregion

            //region SET $token from cache or gather a new one if expired
            $token = $this->getCacheVar($user.'_token_readUserGoogleCredentials');
            if(!$token || (microtime(true)-$token['time'] > 3500)) {
                $token=[];
                $gcloud_token_command = 'gcloud auth print-access-token --account='.$user;
                $this->sendTerminal('gcloud auth print-access-token --account='.$user);
                $auth['token'] = shell_exec($gcloud_token_command);
                if(!$auth['token']) return($this->addError('The following command does not work: '.$gcloud_token_command));
                $token_info = $this->core->request->post_json_decode('https://www.googleapis.com/oauth2/v1/tokeninfo',['access_token'=>$auth['token']],['Content-Type'=>'application/x-www-form-urlencoded']);
                if($this->core->request->error)
                    return($this->addError($this->core->request->errorMsg));

                $token['time'] = microtime(true);
                $token['token'] = $auth['token'];
                $token['email'] = $user;
                $token['info'] = $token_info;
                $this->setCacheVar($user.'_token_readUserGoogleCredentials',$token);
            }
            //endregion

            //region RETURN $token
            return($token);
            //endregion
        }

        /**
         * Return the ERP for the user using a Google Access token for specific namespace
         * @param string $namespace
         * @param string $user
         * @uses getPlatformTokenWithGoogleAccessToken
         * @deprecated
         * @return string|false The platform token retrieved or false on error
         */
        public function getERPTokenWithGoogleAccessToken($namespace='',$user=''): bool|string
        {
            return $this->getPlatformTokenWithGoogleAccessToken($namespace,$user);
        }

        /**
         * Retrieves the platform token (used in X-DS-TOKEN header) using a Google access token.
         *
         * @param string $namespace The namespace for the platform token.
         * @param string $user The user associated with the platform token.
         *
         * @return string|false The platform token retrieved or false on error
         */
        public function getPlatformTokenWithGoogleAccessToken($namespace='',$user=''): bool|string
        {

            //region VERIFY $namespace
            if(!$namespace && !( $namespace = $this->core->config->get('core.erp.platform_id')))
                return($this->addError('Missing $namespace var or core.erp.platform_id config var'));
            //endregion

            //region VERIFY $user or GET it from CACHE or GET it from getGoogleEmailAccount()
            if(!$user && !($user = $this->getCacheVar($namespace.'_erp_user'))) {
                $user = $this->core->security->getGoogleEmailAccount();
                if($this->core->security->error) return($this->addError($this->core->security->errorMsg));
                $this->setCacheVar($namespace.'_erp_user',$user);
            }
            //endregion

            //region SET $user_erp_token from cache expiring in 23h or get it from https://api.cloudframework.io/core/signin
            $user_erp_token = $this->getCacheVar($user.'_'.$namespace.'_erp_token');
            if(!$user_erp_token || (microtime(true)-$user_erp_token['time']> 60*60*23)) {

                //region GET $access_token from Google
                // https://app.cloudframework.app/app.html#https://core20.web.app/ajax/ecm.html?page=/scripts/auth/erp-login-with-google-credentials
                if(!($access_token = $this->getUserGoogleAccessToken($user))) return false;
                $token = $access_token['token'];
                //endregion

                //region SET $payload and POST: https://api.cloudframework.io/core/signin/cloudframework/in
                $payload = [
                    'user'=>$user,
                    'token'=> $token,
                    'type'=>'google_token'
                ];
                //endregion


                $this->sendTerminal('Calling [https://api.cloudframework.io/core/signin/'.$namespace.'/in] with Google access token');
                //region SET $user_erp_token CALLING https://api.cloudframework.io/core/signin/cloudframework/in to get ERP Token
                $ret = $this->core->request->post_json_decode('https://api.cloudframework.io/core/signin/'.$namespace.'/in',$payload, ['X-WEB-KEY'=>'getERPTokenWithGoogleAccessToken']);
                if($this->core->request->error) return($this->addError($this->core->request->errorMsg));
                $user_erp_token = [
                    'time'=>microtime(true),
                    'token'=>$ret['data']['dstoken']];
                $this->setCacheVar($user.'_'.$namespace.'_erp_token',$user_erp_token);
                $this->core->namespace = $namespace;
                //endregion
            }
            //endregion

            //region RETURN $user_erp_token['token']
            return $user_erp_token['token'];
            //endregion

        }
    }


    /**
     * Class to handle script
     *
     * Normally your file has to be stored in the {document_root}/`scripts` ofr definedn in 'core.scripts.path' config variable.
     * @package Scripts
     */
    class CoreScripts
    {

        /** @var Core7 $core pointer to the Core class. `$this->core->...` */
        protected $core;

        /** @var string $method indicates the HTTP method used to access the script: GET, POST etc.. Default value is GET */
        var $method = 'GET';

        /** @var array $formParams Contains the variables passed in a GET,POST,PUT call intro an URL  */
        var $formParams = array();

        /** @var array $params contains the substrings paths of an URL script/param0/param1/..  */
        var $params = array();

        /**
         * @var boolean $error Indicates if an error has been produced
         */
        public $error = false;

        /** @var CoreScriptPrompt $prompt helps with users prompts*/
        public CoreScriptPrompt $prompt;

        /** @var string|integer Code of the error  */
        public $errorCode = '';

        /** @var array $errorMsg Keep the error messages  */
        public $errorMsg = [];

        /** @var array $argv Keep the arguments passed to the logic if it runs as a script  */
        public $argv = null;
        var $tests;
        /** @var CoreCache */
        var $cache = null;
        var $cache_secret_key = '';
        var $cache_secret_iv = '';
        var $cache_data = null;
        var $vars = [];
        var $sendTerminal=[];
        var $time = null;

        /**
         * Scripts constructor.
         * @param Core7 $core
         * @param null $argv
         */
        function __construct(Core7 &$core, $argv=null)
        {

            $this->core = $core;
            if(!$this->core->platform) $this->core->platform = $this->core->config->get('core.erp.platform_id');
            $this->initParameters($argv);
            $this->cache = &$this->core->cache;
            $this->time = microtime(true);
            $this->cache_secret_key = substr($this->core->system->getRequestFingerPrint()['hash'],0,32);
            $this->cache_secret_iv = substr($this->core->system->getRequestFingerPrint()['hash'],0,24);
            $this->prompt = new CoreScriptPrompt($this);

        }

        private function initParameters(&$argv) {

            // take the script lines parts
            $this->params = &$this->core->system->url['parts'];

            // Process ARGV
            $this->argv = $argv;

            // Adding $vars
            foreach ($this->argv as $item) {
                if(strpos($item,'--')===0 && strpos($item,'=')) {
                    list($var,$value) = explode('=',$item,2);
                    $this->vars[$var] = $value;
                }
            }
            //endregion

        }

        function hasOption($option) {
            return(in_array('--'.$option, $this->argv));
        }

        function getOptionVar($option) {
            return((isset($this->vars['--'.$option]))?$this->vars['--'.$option]:null);
        }

        /**
         * Sent to Terminan
         * @param func_get_args
         * @return true to facilitate caller return
         */
        function sendTerminal() {

            $args = func_get_args();
            if($args) foreach ($args as $info) {
                if(is_string($info)) echo $info."\n";
                else print_r($info);
            }
            return true;

            //            if($info)
            //                $this->sendTerminal[] = "[ ".(round(microtime(true)-$this->time,4))." ms] ".((is_string($info))?$info:json_encode($info));
        }


        /**
         * Deprecated Prompt function (use $this->prompt object) to get from user an input with specified options and validates the response.
         * Optionally supports setting and retrieving cached values.
         *
         * @param array $options An array of options to configure the prompt behavior:
         * - 'title': A string specifying the title or question to display.
         * - 'default': The default value to return if no input is provided.
         * - 'cache_var': The name of the cache variable to fetch or store input.
         * - 'type': The type of input expected (e.g., 'password').
         * - 'allowed_values': An array of acceptable input values for validation.
         * @return string|null The user's input, the default value if specified, or null if no input is provided.
         * @deprecated
         */
        function promptVar($options) {

            $title = (isset($options['title']) && $options['title'])?$options['title']:'missing title in prompt';
            $default = (isset($options['default']) && $options['default'])?$options['default']:null;
            $cache_var = (isset($options['cache_var']) && $options['cache_var'])?$options['cache_var']:null;
            $type = (isset($options['type']) && $options['type'])?$options['type']:null;
            $allowed_values = (isset($options['allowed_values']) && is_array($options['allowed_values']))?$options['allowed_values']:null;
            // Check rewrite $default
            if($cache_var && ($cache_content = $this->getCacheVar($options['cache_var']))) $default = $cache_content;

            // Check default value
            if($allowed_values) $title.= ' ['.implode(', ',$allowed_values).']';
            if($default) {
                if($type=='password') $title.=" (*******) :";
                else $title.=" ({$default}) :";
            } else {
                $title.=' :';
            }
            do {
                if($type=='password') {
                    system('stty -echo');
                    echo $title;
                    $ret = trim(fgets(STDIN));
                    echo "\n";
                    system('stty echo');
                    if(!$ret) $ret=$default;
                } else {
                    $ret = readline($title);
                    if(!$ret) $ret=$default;
                }
                $error = ($allowed_values && !in_array($ret,$allowed_values))?true:false;
            } while($error);


            // Set Cache var
            if($cache_var) $this->setCacheVar($cache_var,$ret);

            return $ret;
        }


        /**
         * Sets an error using a code and message sourced from a codelib and logs it.
         *
         * @param string|int $code The error code to identify the nature of the error.
         * @param string $msg The error message providing details about the error.
         * @return false Always returns false to signify error status.
         */
        function setErrorFromCodelib($code,$msg) {
            $this->sendTerminal('ERROR: '.$code);
            $this->sendTerminal([$code=>$msg]);
            $this->errorCode = $code;
            $this->addError([$code=>$msg]);
            return false;
        }

        /**
         * Execute a method if $method is defined.
         * @param string $method name of the method
         * @return bool
         */
        function useFunction($method) {
            if(method_exists($this,$method)) {
                $this->$method();
                return true;
            } else {
                return false;
            }
        }

        /**
         * Retrieves the Google access token for a specified user.
         *
         * This method checks the cache for an existing token, otherwise it requests a new one
         * using the Google Cloud SDK command-line tool. If no user is provided, it prompts
         * for the user's email and validates it. The token information is then returned and
         * optionally cached for further use.
         *
         * @param string $user The Google user email. If not provided, it will be prompted
         *                     and validated.
         * @param bool $use_cache Whether to use cached credentials if available. Defaults to true.
         * @return array|false Returns an array containing the token and related information, or
         *                     false if an error occurs during the process.
         */
        function getUserGoogleAccessToken(string $user='',$use_cache=true)
        {
            //region VERIFY $user or prompt it
            if(!$user && $use_cache) $user = $this->getCacheVar('user_readUserGoogleCredentials');
            if(!$user) {
                do {
                    $user_new = $this->prompt->title('Give me your Google User Email')->mandatory()->query();
                } while (!$this->core->is->validEmail($user_new));
                $this->setCacheVar('user_readUserGoogleCredentials',$user_new);
                $user = $user_new;
            }
            $this->sendTerminal('Gathering Access Token for user: '.$user);
            //endregion

            //region SET $token from cache or gather a new one if expired
            $token = $use_cache?$this->getCacheVar($user.'_token_readUserGoogleCredentials'):null;
            if(!$token || (microtime(true)-$token['time'] > 3500)) {
                $token=[];
                $gcloud_token_command = 'gcloud auth print-access-token --account='.$user;
                $this->sendTerminal('gcloud auth print-access-token --account='.$user);
                $auth['token'] = shell_exec($gcloud_token_command);
                if(!$auth['token']) return($this->addError('The following command does not work: '.$gcloud_token_command));
                $token_info = $this->core->request->post_json_decode('https://www.googleapis.com/oauth2/v1/tokeninfo',['access_token'=>$auth['token']],['Content-Type'=>'application/x-www-form-urlencoded']);
                if($this->core->request->error)
                    return($this->addError($this->core->request->errorMsg));

                $token['time'] = microtime(true);
                $token['token'] = $auth['token'];
                $token['email'] = $user;
                $token['info'] = $token_info;
                $this->setCacheVar($user.'_token_readUserGoogleCredentials',$token);
            }
            //endregion

            //region RETURN $token
            return($token);
            //endregion
        }

        /**
         * Resets the platform token for a given namespace and user.
         * Deletes the cached token associated with the specified user and namespace.
         *
         * @param string $namespace The namespace identifier for the token.
         * @param string $user The user identifier for the token.
         * @return void
         */
        public function resetPlatformToken($namespace='', $user='') {
            $hash = md5($namespace.$user);
            $this->cache->delete('user-token-'.$hash);
        }

        /**
         * Authenticates a platform user using a local access token or Google access token.
         * If a platform user is not authenticated, it attempts to acquire the necessary tokens and validates them.
         *
         * @param string $namespace The namespace of the platform (e.g., system identifier or application domain). Optional.
         * @param string $user The user email or identifier. Optional.
         * @param string $integration_key The integration key for the platform. Optional.
         * @param string|null $auth_code An optional authorization code for specific use cases. Optional.
         * @return bool Returns true if the user is successfully authenticated, otherwise false.
         */
        function authPlatformUserWithLocalAccessToken($namespace='',$user='',$integration_key='',?string $auth_code='') {

            $hash = md5($namespace.$user);
            $token = $this->cache->get('user-token-'.$hash);
            if(!$integration_key) $integration_key = $this->cache->get('integration-key-'.$hash);
            if(!$integration_key) {
                $integration_key = $this->prompt->mandatory()->title("Integration Key for platform [{$namespace}]")->query();
                $this->cache->set('integration-key-'.$hash,$integration_key);
            }

            if(!$user) {
                if(!$user = $this->cache->get('user-email-'.$hash)) {
                    if($user = $this->core->security->getGoogleEmailAccount()) {
                        $this->cache->set('user-email-'.$hash,$user);
                    } else {
                        return $this->addError('Missing $user');
                    }
                }
            }

            if(!$token || !$this->core->user->loadPlatformUserWithToken($token,$integration_key)) {
                $this->cache->delete('user-token-'.$hash);
                $this->cache->delete('user-email-'.$hash);
                if(!$access_token = $this->getUserGoogleAccessToken(($user??''),false)) return false;
                $this->cache->set('user-email-'.$hash,$access_token['email']);
                if(!$this->core->user->loadPlatformUserWithGoogleAccessToken($access_token['email'],$access_token['token'],$namespace,$auth_code)) {
                    if($this->core->user->errorCode=='google-authenticator') {
                        return $this->addError('IT REQUIRE GOOGLE AUTHENTICATOR CODE');
                    } else {
                        return $this->addError($this->core->user->errorMsg ?? 'Unknown error');
                    }
                }

                $this->cache->set('user-token-'.$hash,$this->core->user->token,3600*24);
            }

            return true;


            //            //region VERIFY $namespace
            //            if(!$namespace && !( $namespace = $this->core->config->get('core.erp.platform_id')))
            //                return($this->addError('Missing $namespace var or core.erp.platform_id config var'));
            //            //endregion
            //
            //            //region VERIFY $user or GET it from CACHE or GET it from getGoogleEmailAccount()
            //            if(!$user && !($user = $this->getCacheVar($namespace.'_erp_user'))) {
            //                $user = $this->core->security->getGoogleEmailAccount();
            //                if($this->core->security->error) return($this->addError($this->core->security->errorMsg));
            //                $this->setCacheVar($namespace.'_erp_user',$user);
            //            }
            //            //endregion
            //
            //            //region SET $user_erp_token from cache expiring in 23h or get it from https://api.cloudframework.io/core/signin
            //            $user_erp_token = $this->getCacheVar($user.'_'.$namespace.'_erp_token');
            //            if(!$user_erp_token || (microtime(true)-$user_erp_token['time']> 60*60*23)) {
            //
            //                //region GET $access_token from Google
            //                // https://app.cloudframework.app/app.html#https://core20.web.app/ajax/ecm.html?page=/scripts/auth/erp-login-with-google-credentials
            //                if(!($access_token = $this->getUserGoogleAccessToken($user))) return;
            //                $token = $access_token['token'];
            //                //endregion
            //
            //                //region SET $payload and POST: https://api.cloudframework.io/core/signin/cloudframework/in
            //                $payload = [
            //                    'user'=>$user,
            //                    'token'=> $token,
            //                    'type'=>'google_token'
            //                ];
            //                //endregion
            //
            //
            //                $this->sendTerminal('Calling [https://api.cloudframework.io/core/signin/'.$namespace.'/in] with Google access token');
            //                //region SET $user_erp_token CALLING https://api.cloudframework.io/core/signin/cloudframework/in to get ERP Token
            //                $ret = $this->core->request->post_json_decode('https://api7.cloudframework.io/core/signin/'.$namespace.'/in',$payload, ['X-WEB-KEY'=>'getERPTokenWithGoogleAccessToken']);
            //                if($this->core->request->error) return($this->addError($this->core->request->errorMsg));
            //                $user_erp_token = [
            //                    'time'=>microtime(true),
            //                    'token'=>$ret['data']['dstoken']];
            //                $this->setCacheVar($user.'_'.$namespace.'_erp_token',$user_erp_token);
            //                $this->core->namespace = $namespace;
            //                //endregion
            //            }
            //            //endregion
            //
            //            //region RETURN $user_erp_token['token']
            //            return $user_erp_token['token'];
            //            //endregion

        }

        function readCache() {
            // Read cache into $this->cache_data if not cache default value [];
            $this->cache_data = ($this->cache->get('Core7_CoreScript_cache',-1,'',$this->cache_secret_key,$this->cache_secret_iv))?:[];
        }

        function cleanCache() {
            // Read cache into $this->cache_data if not cache default value [];
            $this->cache_data = [];
            $this->cache->set('Core7_CoreScript_cache',$this->cache_data,'',$this->cache_secret_key,$this->cache_secret_iv);
        }

        function getCacheVar($var) {
            if($this->cache_data === null) $this->readCache();
            return((isset($this->cache_data[$var]))?$this->cache_data[$var]:null);
        }

        function setCacheVar($var,$value) {
            if($this->cache_data === null) $this->readCache();
            $this->cache_data[$var] = $value;
            $this->cache->set('Core7_CoreScript_cache',$this->cache_data,'',$this->cache_secret_key,$this->cache_secret_iv);
        }


        /**
         * Reset errors and messages
         * @return true to facilitate the return of other functions.
         */
        function reset(): true
        {
            $this->error = false;
            $this->core->errors->reset();
            $this->errorMsg = [];
            return true;
        }


        /**
         * Add an error in the class
         * @param $value
         * @return false to facilitate the return of other functions.
         */
        function addError($value): false
        {
            $this->error = true;
            $this->core->errors->add($value);
            $this->errorMsg[] = $value;
            return false;
        }
    }

    /**
     * Handles user prompts through CLI with additional configurations like
     * default values, mandatory input, allowed values, and caching functionalities.
     */
    Class CoreScriptPrompt {

        /** @var CoreScripts $parent pointer to the Core class. `$this->core->...` */
        protected CoreScripts $parent;
        protected string $type='text';
        protected string $title='';
        protected string $cache_var='';
        protected string $default_value='';
        protected bool $mandatory=false;
        protected array $allowed_values=[];

        /**
         * Scripts constructor.
         * @param Core7 $core
         * @param null $argv
         */
        function __construct(CoreScripts &$parent)
        {
            $this->parent = $parent;
        }

        public function init(string $secret_key='',string $secret_iv=''): static
        {
            $this->title = '';
            $this->cache_var = '';
            $this->type = 'text';
            $this->default_value = '';
            $this->allowed_values = [];
            $this->mandatory = false;
            return $this;
        }

        /**
         * Prompts the user for input, validates it based on defined constraints,
         * and optionally returns a cached value if available.
         *
         * The method constructs a title using the `title` property and provides
         * a default value if available or retrieved from a cache. It supports
         * validation against allowed values and handles mandatory fields. Input
         * for password fields is masked for security. In case of invalid input,
         * the user is prompted again until valid input is provided.
         *
         * @return string Returns the user-provided or default value after validation.
         */
        function query() {

            $title = $this->title;
            $default = $this->default_value;
            if($this->cache_var && ($cache_content = $this->parent->getCacheVar($this->cache_var))) $default = $cache_content;

            // Check default value
            if($this->allowed_values) $title.= ' ['.implode(', ',$this->allowed_values).']';
            if($default) {
                if($this->type=='password') $title.=" (*******) :";
                else $title.=" ({$default}) :";
            } else {
                $title.=' :';
            }
            do {
                if($this->type=='password') {
                    system('stty -echo');
                    echo $title;
                    $ret = trim(fgets(STDIN));
                    echo "\n";
                    system('stty echo');
                    if(!$ret) $ret=$default;
                } else {
                    $ret = readline($title);
                    if(!$ret) $ret=$default;
                }

                $error = false;
                if($this->mandatory && !$ret) $error = true;
                elseif($this->allowed_values && !in_array($ret,$this->allowed_values)) $error = true;

            } while($error);


            // Set Cache var
            if($this->cache_var) $this->parent->setCacheVar($this->cache_var,$ret);

            return $ret;
        }

        public function title($title): static
        {
            $this->title = $title;
            return $this;
        }

        public function defaultValue(string $value): static
        {
            $this->default_value = $value;
            return $this;
        }

        public function cacheVar(string $variable): static
        {
            $this->cache_var = $variable;
            return $this;
        }

        public function allowedValues(array $values): static
        {
            $this->allowed_values = $values;
            return $this;
        }

        public function isPassword(bool $option=true): static
        {
            $this->type = $option?'password':'text';
            return $this;
        }

        public function mandatory(bool $option=true): static
        {
            $this->mandatory = $option;
            return $this;
        }


    }
}