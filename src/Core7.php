<?php
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
        var $_version = '8.3.24';  // 2024-09-02
        /** @var CorePerformance $__p */
        var  $__p;
        /** @var CoreIs $is */
        var  $is;
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

        /**
         * Core constructor.
         * @param string $root_path
         */
        function __construct($root_path = '')
        {
            //region SET $this->__p,$this->is,$this->system,$this->logs,$this->errors
            $this->__p = new CorePerformance();
            $this->is = new CoreIs();
            $this->system = new CoreSystem($root_path);
            $this->logs = new CoreLog($this->is->development() && $this->is->terminal());
            $this->errors = new CoreLog($this->is->development() && $this->is->terminal());
            //endregion

            //region SET $this->config and evaluate to read local_config.json and local_script.json
            $this->config = new CoreConfig($this, __DIR__ . '/config.json');
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
            $this->cache->setSpaceName($this->config->get('cacheSpacename'));

            // If the $this->system->app_path ends in / delete the char.
            $this->system->app_path = preg_replace('/\/$/','',$this->system->app_path);

            // region EVALUATE env variables:
            if($this->config->get("core.gcp.project_id")) putenv('PROJECT_ID='.$this->config->get("core.gcp.project_id"));
            if($this->config->get("core.gcp.credentials")) putenv('GOOGLE_APPLICATION_CREDENTIALS='.$this->config->get("core.gcp.credentials"));

            // Support of DataStorage to work with Buckets
            $this->initDataStorage();

        }

        /**
         * Router
         */
        function dispatch()
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
        function setAppPath($dir)
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
         * Return an object of the Class $class. If this object has been previously called class
         * @param $class
         * @param null $params
         * @return mixed|null
         */
        function loadClass($class, $params = null)
        {

            $hash = hash('md5', $class . json_encode($params));
            if (key_exists($hash, $this->loadedClasses)) return $this->loadedClasses[$hash];
            $bucket_path = $this->config->get('core.api.extra_path');

            if (is_file(__DIR__ . "/class/{$class}.php"))
                include_once(__DIR__ . "/class/{$class}.php");
            elseif (is_file($this->system->app_path . "/class/" . $class . ".php"))
                include_once($this->system->app_path . "/class/" . $class . ".php");
            elseif($bucket_path)
                @include_once($bucket_path . "/class/" . $class . ".php");

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
            if($options) $json = json_encode($input, JSON_UNESCAPED_UNICODE | $options);
            else $json = json_encode($input, JSON_UNESCAPED_UNICODE );

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
                                default:
                                    $value = $this->platform[$found[2]] ?? null;
                                    break;
                            }
                            break;
                        case "User":
                            $value = (isset($this->user->data['User'][$found[2]])) ? $this->user->data['User'][$found[2]] : '';
                            if ($rawUrlEncode) $value = urlencode($value);
                            break;
                        case "UserVariables":
                        case "UserVariable":
                            $value = (isset($this->user->data['User']['UserVariables'][$found[2]])) ? $this->user->data['User']['UserVariables'][$found[2]] : '';
                            if ($rawUrlEncode) $value = urlencode($value);
                            break;
                        case "GETVariables":
                        case "GETVariable":
                            $value = (isset($_GET[$found[2]])) ? $_GET[$found[2]] : '';
                            if ($rawUrlEncode) $value = urlencode($value);
                            break;
                        case "POSTVariables":
                        case "POSTVariable":
                            $value = (isset($_POST[$found[2]])) ? $_POST[$found[2]] : '';
                            if ($rawUrlEncode) $value = urlencode($value);
                            break;
                        case "FORMVariables":
                        case "FORMVariable":
                            $value = (isset($_REQUEST[$found[2]])) ? $_REQUEST[$found[2]] : '';
                            if ($rawUrlEncode) $value = urlencode($value);
                            break;
                    }
                    //endregion

                    //region REPLACE str_replace($found[0], $value, $text) EVALUATING $default_value
                    if (!$value && !strlen($value??'') && $default_value) $value = $default_value;
                    $data = str_replace($found[0], $value??'', $data);
                    //endregion

                }
                //endregion
            }  while($found);
            //endregion

            //region REPLACE {{xx}} variables
            if($array_of_variables) $this->replaceVariables($array_of_variables,$data,$rawUrlEncode);
            //endregion
            //region APPLY $this->localization->getTag($data) to find final translations
            if(is_string($data) && $data && substr_count($data,';')>1) {
                $data = $this->localization->getTag($data);
                if($array_of_variables && strpos($data,'{{')!==false) {
                    $this->replaceVariables($array_of_variables, $data, $rawUrlEncode);
                }

            }
            //endregion

            return $data;

        }

        private function replaceVariables(array &$array_of_variables,string &$data, bool $rawUrlEncode=false)
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
                    if (!$value && $default_value) $value = $default_value;
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
         * Return the value of $array[$var]. If $var has '.' separator it assumes that it is a subarray
         * @param $array
         * @param $var
         * @return mixed|string
         */
        private function findValueWithDotsInArray(&$array, $var) {
            if(!strpos($var,'.')) return $array[$var]??'';
            else {
                $parts = explode('.',$var,2);
                if(isset($array[$parts[0]])) return $this->findValueWithDotsInArray($array[$parts[0]],$parts[1]);
                else return '';
            }
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

        function add($title, $file = '', $type = 'all')
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

            if (isset($_GET['__p']) && $_GET['__p'] == $this->data['lastIndex']) {
                _printe($this->data);
                exit;
            }

            $this->data['lastIndex']++;

        }

        function getTotalTime($prec = 3)
        {
            return (round(microtime(TRUE) - $this->data['initMicrotime'], $prec));
        }

        function getTotalMemory($prec = 3)
        {
            return number_format(round(memory_get_usage() / (1024 * 1024), $prec), $prec);
        }

        function init($spacename, $key)
        {
            if(!$this->active) return;
            $this->data['init'][$spacename][$key]['mem'] = memory_get_usage();
            $this->data['init'][$spacename][$key]['time'] = microtime(TRUE);
            $this->data['init'][$spacename][$key]['ok'] = TRUE;
        }

        function end($spacename, $key, $ok = TRUE, $msg = FALSE)
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
     * $this->core->is Class to answer is? questions.
     * @package Core.is
     */
    class CoreIs
    {
        function development()
        {
            return (!(array_key_exists('GAE_SERVICE',$_SERVER)));
        }

        function production()
        {
            return (array_key_exists('GAE_SERVICE',$_SERVER) );
        }

        function localEnvironment()
        {
            return (!(array_key_exists('GAE_SERVICE',$_SERVER)));
        }

        function GCPEnvironment()
        {
            return (array_key_exists('GAE_SERVICE',$_SERVER) );
        }

        function script()
        {
            return (isset($_SERVER['PWD']) && !isset($_SERVER['GAE_SERVICE']));
        }

        function dirReadable($dir)
        {
            if (strlen($dir)) return (is_dir($dir));
        }

        function terminal()
        {
            return !isset($_SERVER['SERVER_PORT']);
        }

        function dirWritable($dir)
        {
            if (strlen($dir)) {
                if (!$this->dirReadble($dir)) return false;
                try {
                    if (@mkdir($dir . '/__tmp__')) {
                        rmdir($dir . '/__tmp__');
                        return (true);
                    }
                } catch (Exception $e) {
                    return false;
                }
            }
        }

        function validEmail($email)
        {
            return (filter_var($email, FILTER_VALIDATE_EMAIL));
        }

        function validURL($url)
        {
            return (filter_var($url, FILTER_VALIDATE_URL));
        }
    }

    /**
     * $this->core->system Class to interacto with with the System variables
     * @package Core.system
     */
    class CoreSystem
    {
        var $url, $app,$root_path, $app_path, $app_url,$script_path,$api_path;
        var $config = [];
        var $ip, $user_agent, $os, $lang, $format, $time_zone;
        var $geo;

        function __construct($root_path = '')
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
         * Set default timezone and feed with $this->time_zone = array(date_default_timezone_get(), date('Y-m-d H:i:s'), date("P"), time());
         * @param $timezone
         */
        function setTimeZone($timezone) {
            date_default_timezone_set($timezone);
            $this->time_zone = array(date_default_timezone_get(), date('Y-m-d H:i:s'), date("P"), time());
        }


        /**
         * Return the IP of the client:
         * https://cloud.google.com/appengine/docs/standard/php7/runtime#https_and_forwarding_proxies
         * @return mixed|string
         */
        function getClientIP() {

            $remote_address = (array_key_exists('HTTP_X_FORWARDED_FOR',$_SERVER))?$_SERVER['HTTP_X_FORWARDED_FOR']:'localhost';
            return  ($remote_address == '::1') ? 'localhost' : $remote_address;

            // Popular approaches we don't trust.
            // http://stackoverflow.com/questions/3003145/how-to-get-the-client-ip-address-in-php#comment50230065_3003233
            // http://stackoverflow.com/questions/15699101/get-the-client-ip-address-using-php
            /*
            if (getenv('HTTP_CLIENT_IP'))
                $ipaddress = getenv('HTTP_CLIENT_IP');
            else if (getenv('HTTP_X_FORWARDED_FOR'))
                $ipaddress = getenv('HTTP_X_FORWARDED_FOR');
            else if (getenv('HTTP_X_FORWARDED'))
                $ipaddress = getenv('HTTP_X_FORWARDED');
            else if (getenv('HTTP_FORWARDED_FOR'))
                $ipaddress = getenv('HTTP_FORWARDED_FOR');
            else if (getenv('HTTP_FORWARDED'))
                $ipaddress = getenv('HTTP_FORWARDED');
            else if (getenv('REMOTE_ADDR'))
                $ipaddress = getenv('REMOTE_ADDR');
            else
                $ipaddress = 'UNKNOWN';
            return $ipaddress;
            */

        }

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
         * @param $url path for destination ($dest is empty) or for source ($dest if not empty)
         * @param string $dest Optional destination. If empty, destination will be $url
         */
        function urlRedirect($url, $dest = '')
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
         * Generate a fingerprint from the Request
         * @return array
         */
        public function getRequestFingerPrint(): array
        {
            // Return the fingerprint coming from a queue
            if (isset($_REQUEST['cloudframework_queued_fingerprint'])) {
                return (json_decode($_REQUEST['cloudframework_queued_fingerprint'], true));
            }

            $ret['user_agent'] = (isset($_SERVER['HTTP_USER_AGENT']))?$_SERVER['HTTP_USER_AGENT']:'unknown';
            $ret['host'] = (isset($_SERVER['HTTP_HOST']))?$_SERVER['HTTP_HOST']:null;
            $ret['software'] = (isset($_SERVER['GAE_RUNTIME']))?('GAE_RUN_TIME:'.$_SERVER['GAE_RUNTIME'].'/'.$_SERVER['GAE_VERSION']):((isset($_SERVER['SERVER_SOFTWARE']))?$_SERVER['SERVER_SOFTWARE']:'Unknown');


            $ret['hash'] = sha1(implode(",", $ret));
            $ret['ip'] = $this->ip;

            if(isset($_SERVER['HTTP_X_APPENGINE_CITY'])) $ret['getData']['city'] = $_SERVER['HTTP_X_APPENGINE_CITY'];
            if(isset($_SERVER['HTTP_X_APPENGINE_COUNTRY'])) $ret['getData']['country'] = $_SERVER['HTTP_X_APPENGINE_COUNTRY'];
            if(isset($_SERVER['HTTP_X_APPENGINE_REGION'])) $ret['getData']['region'] = $_SERVER['HTTP_X_APPENGINE_REGION'];
            if(isset($_SERVER['HTTP_X_APPENGINE_CITYLATLONG'])) $ret['getData']['latlong'] = $_SERVER['HTTP_X_APPENGINE_CITYLATLONG'];

            $ret['http_referer'] = (array_key_exists('HTTP_REFERER',$_SERVER))?$_SERVER['HTTP_REFERER']:'unknown';
            $ret['time'] = date('Ymdhise');
            $ret['uri'] = (isset($_SERVER['REQUEST_URI']))?$_SERVER['REQUEST_URI']:null;

            return ($ret);
        }

        /**
         * Get the value of the specified header key from the $_SERVER superglobal
         * @param string $headerKey The key of the header to retrieve
         * @return string The value of the header key, or an empty string if it does not exist
         */
        public function getHeader(string $headerKey): string
        {
            $headerKey = strtoupper($headerKey);
            $headerKey = str_replace('-', '_', $headerKey);
            return ((isset($_SERVER['HTTP_' . $headerKey])) ? $_SERVER['HTTP_' . $headerKey] : '');
        }

        /**
         * Return all the header keys sent by the user. In PHP those are $_SERVER['HTTP_{varname}']
         * @return array
         */
        function getHeaders(): array
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
         * @param string $syslog_title
         */
        function set($data,$syslog_title=null, $syslog_type=null)
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
         */
        function add($data, $syslog_title=null, $syslog_type=null)
        {
            // Evaluate to write in syslog
            if(null !==  $syslog_title) {
                if(null==$syslog_type) $syslog_type = $this->syslog_type;
                $this->sendToSysLog($syslog_title.': '. json_encode($data,JSON_FORCE_OBJECT),$syslog_type);
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
        function get() { return $this->data; }

        /**
         * store all the data inside a syslog
         *
         * @param $data
         * @param $syslog_type string|null values: error, info, warning, notice, debug, critical, alert, emergency
         */
        function sendToSysLog($data, $syslog_type=null) {
            if(!is_string($data)) $data = json_encode($data,JSON_FORCE_OBJECT);
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
         * Reset the log
         */
        function reset()
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

            // Cache secrets to encrypt data in Cache
            $this->cache_secret_key=$this->get('core.gcp.secrets.cache_encrypt_key');
            $this->cache_secret_iv=$this->get('core.gcp.secrets.cache_encrypt_iv');

            // Update $this->get('core.scripts.path') and $this->get('core.api.path')
            if($this->get('core.scripts.path')) $this->core->system->script_path = $this->get('core.scripts.path');
            if($this->get('core.api.path')) $this->core->system->api_path = $this->get('core.api.path');

        }

        /**
         * Return an array of files readed for config.
         * @return array
         */
        function getConfigLoaded()
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
        function getLang()
        {
            return ($this->lang);
        }

        /**
         * Assign the language
         * @param $lang
         * @return bool
         */
        function setLang($lang)
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
            if ($_array) $data = json_encode($data);
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
        function readEnvVarsFromGCPSecrets($gpc_project_id = '',$gpc_secret_id = '', $reload=false) {

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
                $this->cache = $this->core->cache->get('Core7.CoreConfig', -1,'',$cache_secret_key,$cache_secret_iv);
                if(!$this->cache) $this->cache=[];
            }
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

        var $error = false;
        var $errorMsg = [];
        var $cache = null;
        var $cache_key = null;
        var $cache_iv = null;
        var $secret_vars = null;
        var $last_key_cache = null;     // To control double read of: readERPDeveloperEncryptedSubKeys


        function __construct(Core7 &$core)
        {
            $this->core = $core;
            $this->cache_key = ($this->core->config->get('core.gcp.secrets.cache_encrypt_key'))?:'T8K1Ogtl5E9R9CDbWIdV6Vs4yBY4';
            $this->cache_iv = ($this->core->config->get('core.gcp.secrets.cache_encrypt_iv'))?:'iveTFs7++f9niowHcuafMzTeKLG4X';
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
                $erp_platform_id = $this->core->config->get('core.erp.platform_id');
                if(!$erp_platform_id) return($this->addError('readERPDeveloperEncryptedSubKeys(..) missing function-var($erp_platform_id) or config-var(core.erp.platform_id)'));
            }
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
            $key_cache = '/my-daily-encryption-subkeys/'.$erp_user;
            $keys = [];
            //endregion

            //region GET from cache $keys if we are in production servers
            $keys = $this->core->cache->get($key_cache,3600*24,date('Y-m-d'));
            //endregion

            //region CALL $url and SET $keys if empty $keys
            if(!$keys) {
                //region SET $headers and call $url
                $headers = ['X-WEB-KEY'=>$erp_user];
                $keys = $this->core->request->get_json_decode($url,null,$headers);
                if($this->core->request->error) return($this->addError(['Error in developer license for user: '.$erp_user,($keys['message']??'no-message')]));
                //endregion

                //region SAVE from cache $keys if we are in production servers
                $this->core->cache->set($key_cache,$keys,date('Y-m-d'));
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
                $erp_platform_id = $this->core->config->get('core.erp.platform_id');
                if(!$erp_platform_id) return($this->addError('readERPSecretVars(..) missing function-var($erp_platform_id) or config-var(core.erp.platform_id)'));
            }
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
            if($this->secret_vars ===null || ($erp_secret_id && $this->secret_vars['secret-id']!=$erp_secret_id))
                if(!$this->readPlatformSecretVars($erp_secret_id,$erp_platform_id,$erp_user)) return;
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
                if(!$erp_secret_id) return($this->addError('readERPSecretVars(..) missing function-var($erp_secret_id) or config-var(core.erp.secrets.secret_id)'));
            }
            //endregion

            //region CHECK $erp_platform_id value
            if(!$erp_platform_id || !is_string($erp_platform_id)) {
                $erp_platform_id = $this->core->config->get('core.erp.platform_id');
                if(!$erp_platform_id) return($this->addError('readERPSecretVars(..) missing function-var($erp_platform_id) or config-var(core.erp.platform_id)'));
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
                if(!$erp_user) return($this->addError('readERPSecretVars(..) missing function readERPSecretVars(..,$erp_user) or config-var(core.erp.user_id.{platform_id})'));
            }
            //endregion


            if(!$this->readERPDeveloperEncryptedSubKeys($erp_platform_id,$erp_user))
                return($this->addError('Called from readERPSecretVars(..)'));

            //region READ $user_secrets from cache and RETURN it if it exist
            $key = 'getMyERPSecrets_'.$this->core->gc_project_id.'_'.$erp_platform_id.'_'.$erp_secret_id;
            $user_secrets = $this->getCache($key,'ERP.secrets');
            //verify $user_secrets['id'] match with $erp_user
            //fix bug when in production the $erp_user is returning default
            if($erp_user=='default' && isset($user_secrets['id'])) $erp_user = $user_secrets['id'];

            //check the
            if($erp_user && isset($user_secrets['id']) && $user_secrets['id']!=$erp_user) $user_secrets=[];
            if($user_secrets){
                $this->secret_vars = ['id'=>$user_secrets['id'],'secret-id'=>$user_secrets['secret-id'],'secrets'=>$user_secrets['secrets']];
                return true;
            }
            //endregion

            //region IF $user_secrets is empty feed it with basic structure
            $user_secrets = ['access_token'=>null,'id'=>'','platform'=>$erp_platform_id,'secrets'=>''];
            //endregion

            //region SET $token from User or GCP Engine Instance Token
            $token = $this->getGoogleIdentityToken($erp_user);
            if(!$token) return($this->addError('CoreSecurity.readERPSecretVars() has returned an error calling $this->getGoogleIdentityToken($erp_user)'));
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
                $url = 'https://api.cloudframework.io/core/secrets/'.$erp_platform_id.'/system-secrets/'.$user_secrets['id'].'/'.$erp_secret_id;
            }
            else{
                $url = 'https://api.cloudframework.io/core/secrets/'.$erp_platform_id.'/my-secrets/'.$user_secrets['id'];
            }

            $headers = ['X-WEB-KEY'=>$user_secrets['id'],'X-DS-TOKEN'=>$token];
            $secrets = $this->core->request->get_json_decode($url,null,$headers);

            if($this->core->request->error) {
                return($this->addError(($secrets['message']??null)?:$this->core->request->errorMsg));
                $this->core->request->reset();
                $this->core->errors->reset();
            }

            $user_secrets['secret-id'] = ($erp_secret_id)?:$user_secrets['id'];
            $user_secrets['secrets'] = $secrets['data']['secrets'];
            //endregion

            //region UPDATE cache
            $this->updateCache($key,$user_secrets,'ERP.secrets');
            //endregion

            //region RETURN $user_secrets
            $this->secret_vars = ['id'=>$user_secrets['id'],'secret-id'=>$user_secrets['secret-id'],'secrets'=>$user_secrets['secrets']];
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
                if(!isset($lines[0]) || strpos($lines[0],'ACTIVE')===false) return($this->addError("'{$gcloud_auth_list}' has no active account. Execute gcloud auth login"));
                array_shift($lines);
                $user='';
                do {
                    if(strpos($lines[0],'*')===0) {
                        $user = preg_replace('/[\* ]/','',trim($lines[0]));
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
                $this->addError($e->getMessage());
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
         * @param array|string $allows string to compare with the current IP
         * @return bool
         */
        private function checkIPs($allows)
        {
            if (is_string($allows)) $allows = explode(',', $allows);
            foreach ($allows as $host) {
                $host = trim($host);
                if ($host == '*' || preg_match('/^.*' . $host . '.*$/', $this->core->system->ip) > 0) return true;
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
         * @param $token
         * @return array|bool|void If the deletion is right it return the array with the recored deleted
         */
        function deactivateDSToken($token)
        {
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
         * initialization vector(IV) has to be the same when encrypting and decrypting
         * based on: https://gist.github.com/joashp/a1ae9cb30fa533f4ad94
         *
         * @param string $text: string to encrypt
         * @param string $secret_key  optional secret key. If empty it will take it from config-vars: core.security.encrypt_key
         * @param string $secret_iv  optional secret key. If empty it will take it from config-vars: core.security.encrypt_secret
         * @return string in base64
         */
        function encrypt($text,$secret_key='',$secret_iv='') {

            if(!$text) return $text;

            $encrypt_method = "AES-256-CBC";

            if(!$secret_key)
                $secret_key = ($this->core->config->get('core.security.encrypt_key'))?:'ybdqfG3MTPfzct3jR28qbix/yvbodtT0';

            if(!$secret_iv)
                $secret_iv = ($this->core->config->get('core.security.encrypt_secret'))?:'sadf&$sad_dkuYWER$T__6ttre';

            // hash
            $key = hash('sha256', $secret_key);

            // iv - encrypt method AES-256-CBC expects 16 bytes - else you will get a warning
            $iv = substr(hash('sha256', $secret_iv), 0, 16);

            return openssl_encrypt($text, $encrypt_method, $key, 0, $iv);

        }

        /*
         * Decrypt Encrypted Text
         *
         * @param string $encrypted_text base64 string
         * @param string $secret_key  optional secret key. If empty it will take it from config-vars: core.security.encrypt_key
         * @param string $text  optional secret key. If empty it will take it from config-vars: core.security.encrypt_secret
         *
         */
        function decrypt($encrypted_text,$secret_key='',$secret_iv='') {

            if(!$encrypted_text) return $encrypted_text;

            $encrypt_method = "AES-256-CBC";

            if(!$secret_key)
                $secret_key = ($this->core->config->get('core.security.encrypt_key'))?:'ybdqfG3MTPfzct3jR28qbix/yvbodtT0';

            if(!$secret_iv)
                $secret_iv = ($this->core->config->get('core.security.encrypt_secret'))?:'sadf&$sad_dkuYWER$T__6ttre';

            // hash
            $key = hash('sha256', $secret_key);

            // iv - encrypt method AES-256-CBC expects 16 bytes - else you will get a warning
            $iv = substr(hash('sha256', $secret_iv), 0, 16);

            return openssl_decrypt($encrypted_text, $encrypt_method, $key, 0, $iv);
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
         * Reset Cache of the module
         */
        public function readCache($security_group = 'default') {
            if(!isset($this->cache[$security_group]))
                $this->cache[$security_group] = ($this->core->cache->get('Core7.CoreSecurity.'.$security_group,3600*24,null,$this->cache_key,$this->cache_iv))?:[];
        }

        /**
         * Reset Cache of the module
         */
        public function resetCacheForERPSecretVars() {
            $this->resetCache('ERP.secrets');
        }

        /**
         * Reset Cache of the module
         */
        public function resetCache($security_group = 'default') {
            $this->cache[$security_group] = [];
            $this->core->cache->set('Core7.CoreSecurity.'.$security_group,$this->cache[$security_group],null,$this->cache_key,$this->cache_iv);
            $this->core->logs->add('CoreSecurity.resetCache(\''.$security_group.'\') from '.$this->core->system->url['host_url_uri'],'CoreSecurity');
        }

        /**
         * Update Cache of the module
         */
        public function updateCache($var,$data,$security_group = 'default') {
            if(!$security_group) $security_group = 'default';
            $this->readCache($security_group);
            $this->cache[$security_group][$var] = $data;
            $this->core->cache->set('Core7.CoreSecurity.'.$security_group,$this->cache[$security_group],null,$this->cache_key,$this->cache_iv);

        }

        /**
         * Get var Cache of the module
         * @param string $var y empty it returns all the variable of the security group
         * @param string $security_group
         * @return mixed|null
         */
        public function getCache(string $var='',$security_group = 'default') {
            if(!$security_group) $security_group = 'default';
            $this->readCache($security_group);
            if($var) return $this->cache[$security_group][$var] ?? null;
            else return $this->cache[$security_group] ?? null;
        }


        public function urlsafeB64Encode($input) {
            return str_replace('=', '', strtr(base64_encode($input), '+/', '-_'));
        }

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
         */
        public function checkAllowedIPs($allowed_ips,$myip=null) {
            if(!$myip) $myip=$this->core->system->ip;
            if($allowed_ips && $allowed_ips!='*' && $allowed_ips!=$myip) {
                if(strpos($allowed_ips,',')!==false) {
                    $allows = explode(',',$allowed_ips);
                    foreach ($allows as $allow) if(trim($allow)) {
                        if($allow=='*' || $allow==$myip) return true;
                        if(strpos($allow,'/')!==false) {
                            $myip_local = ($myip=='localhost')?'127.0.0.1':$myip;
                            list ($net, $mask) = explode("/", trim($allow),2);
                            $ip_net = ip2long ($net);
                            $ip_mask = ~((1 << (32 - $mask)) - 1);
                            $ip_ip = ip2long ($myip_local);
                            $ip_ip_net = $ip_ip & $ip_mask;

                            if(strval($ip_ip_net) == strval($ip_net)) return true;
                        }
                    }
                }
                return false;
            }
            return true;
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
         * One-way string encryption (hashing)
         * @param string|array $input The string or array to encrypt. If array is provided it will be converted to string with json_encoded. If length of $input > 72 then it returns null
         * @param int $rounds
         * @return string|null
         */
        function crypt($input, $rounds = 7)
        {
            if($input && !is_string($input)) $input = json_encode($input);
            if(!$input) return null;
            if(strlen($input)>72) return null; // This method only admin string with max 72 chars

            $salt = "";
            $salt_chars = array_merge(range('A', 'Z'), range('a', 'z'), range(0, 9));
            for ($i = 0; $i < 22; $i++) {
                $salt .= $salt_chars[array_rand($salt_chars)];
            }
            return crypt($input, sprintf('$2a$%02d$', $rounds) . $salt);
        }

        /**
         * Verify a string with its encrypted value to verify the match
         * @param string|array $input  original input encrypted
         * @param string|null $input_encrypted Encrypted string to compare. If null or empty is provided it will try to get the value from $this->core->config->get("core.security.password")
         * @return bool
         */
        function checkCrypt($input, $input_encrypted=null): bool
        {
            if($input && !is_string($input)) $input = json_encode($input);
            if(!$input) return false;
            if(strlen($input)>72) return false; // This method only admin string with max 72 chars

            // IF $input_encrypted empty try to get it from core.security.password config var
            if(!$input_encrypted) $input_encrypted = $this->core->config->get("core.security.password");

            if(!$input_encrypted || !is_string($input_encrypted)) return false;
            return (crypt($input, $input_encrypted) == $input_encrypted);
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
            $this->setSpaceName($spacename);

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

            if (isset($_SESSION['Core_CacheFile_' . $path]) || is_dir($path) || @mkdir($path)) {
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
         * Activate cache in Datastore.. It requires Core Class
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
                if($cache_secret_key && $cache_secret_iv) {
                    $this->errorSecurity = false;
                    $info['_data_'] = $this->core->security->decrypt($info['_data_'],$cache_secret_key,$cache_secret_iv);
                }

                // unserialize vars
                $ret = null;
                try {
                    if(isset($info['_data_']) && $info['_data_']) {
                        $ret = @unserialize(@gzuncompress($info['_data_']));
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
            else return unserialize(gzuncompress(utf8_decode($data['Serialize'])));
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
        /** @ignore */
        var $default_options = array('ssl' => array('verify_peer' => false),'http'=>['protocol_version'=>'1.1','ignore_errors'=>'1','follow_location'=>true]);
        /** @ignore */
        var $cookies = [];

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
         * @param string $path Path to complete URL. if it does no start with http.. $path will be aggregated to: $this->core->config->get("CloudServiceUrl")
         * @return string
         */
        function defaultServiceUrl($path = '')
        {
            if (strpos($path, 'http') === 0) return $path;
            else {
                if (!$this->core->config->get("CloudServiceUrl"))
                    $this->core->config->set("CloudServiceUrl", 'https://api7.cloudframework.io');

                $this->http = $this->core->config->get("CloudServiceUrl");

                if (strlen($path) && $path[0] != '/')
                    $path = '/' . $path;
                return ($this->http . $path);
            }
        }
        function getServiceUrl($path = '') {return $this->defaultServiceUrl($path);}


        /**
         * Call External Cloud Service Caching the result
         */
        function getCache($route, $data = null, $verb = 'GET', $extraheaders = null, $raw = false)
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
         * CURL METHOD
         * @param $route
         * @param null $data
         * @param string $verb
         * @param null $extra_headers
         * @param false $raw
         * @return false|string
         * @throws Exception
         */
        function getCurl($route, $data = null, $verb = 'GET', $extra_headers = null, $raw = false)
        {

            $_time = microtime(TRUE);
            if($this->sendSysLogs)
                $this->core->logs->add("curl request {$verb} {$route} ".(($data === null) ? '{no params}' : '{with params}'),'CoreRequest');

            $this->core->__p->add('Request->getCurl: ', "$route " . (($data === null) ? '{no params}' : '{with params}'), 'note');
            $route = $this->getServiceUrl($route);
            $this->responseHeaders = null;
            $options['http']['header'] = ['Connection: close', 'Expect:', 'ACCEPT:']; // improve perfomance and avoid 100 HTTP Header


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
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => false,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HEADER => true,            // return headers
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_HTTPHEADER => $options['http']['header'],
                CURLOPT_CUSTOMREQUEST => $verb

            ];
            // Appengine  workaround
            // $curl_options[CURLOPT_SSL_VERIFYPEER] = false;
            // $curl_options[CURLOPT_SSL_VERIFYHOST] = false;
            // Download https://pki.google.com/GIAG2.crt
            // openssl x509 -in GIAG2.crt -inform DER -out google.pem -outform PEM
            // $curl_options[CURLOPT_CAINFO] =__DIR__.'/google.pem';

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
                $this->addError(error_get_last());
                $this->addError([('Curl error ' . curl_errno($ch)) => curl_error($ch)]);
                $this->addError(['Curl url' => $route]);
                $ret = false;
            }
            curl_close($ch);

            if($this->sendSysLogs) {
                $_time = round(microtime(TRUE) -$_time,4);
                $this->core->logs->add("end curl request {$verb} {$route} ".(($data === null) ? '{no params}' : '{with params}')." {$_time} secs",(($this->error)?'debug':'info'),'CoreRequest');
            }

            $this->core->__p->add('Request->getCurl: ', '', 'endnote');
            return $ret;


        }


        /**
         * GET CALL expecting a json response
         * @param $route
         * @param null $data
         * @param null $extra_headers
         * @param false $send_in_json
         * @return bool[]|mixed|string[]
         */
        function get_json_decode($route, $data = null, $extra_headers = null, $send_in_json = false)
        {
            $this->rawResult = $this->get($route, $data, $extra_headers, $send_in_json);
            $ret = json_decode($this->rawResult, true);
            if (JSON_ERROR_NONE === json_last_error()) $this->rawResult = '';
            else {
                $ret = ['error'=>$this->rawResult];
            }
            return $ret;
        }

        /**
         * POST CALL expecting a json response
         * @param $route
         * @param null $data
         * @param null $extra_headers
         * @param false $send_in_json
         * @return bool[]|mixed|string[]
         */
        function post_json_decode($route, $data = null, $extra_headers = null, $send_in_json = false)
        {
            $this->rawResult = $this->post($route, $data, $extra_headers, $send_in_json);
            $ret = json_decode($this->rawResult, true);
            if (JSON_ERROR_NONE === json_last_error()) $this->rawResult = '';
            else {
                $ret = ['error'=>$this->rawResult];
            }
            return $ret;
        }

        /**
         * PUT CALL expecting a json response
         * @param $route
         * @param null $data
         * @param null $extra_headers
         * @param false $send_in_json
         * @return bool[]|mixed|string[]
         */
        function put_json_decode($route, $data = null, $extra_headers = null, $send_in_json = false)
        {
            $this->rawResult = $this->put($route, $data, $extra_headers, $send_in_json);
            $ret = json_decode($this->rawResult, true);
            if (JSON_ERROR_NONE === json_last_error()) $this->rawResult = '';
            else {
                $ret = ['error'=>$this->rawResult];
            }
            return $ret;
        }


        /**
         * PATCH
         * @param $route
         * @param null $data
         * @param null $extra_headers
         * @param false $send_in_json
         * @return bool[]|mixed|string[]
         */
        function patch_json_decode($route, $data = null, $extra_headers = null, $send_in_json = false)
        {
            $this->rawResult = $this->patch($route, $data, $extra_headers, $send_in_json);
            $ret = json_decode($this->rawResult, true);
            if (JSON_ERROR_NONE === json_last_error()) $this->rawResult = '';
            else {
                $ret = ['error'=>$this->rawResult];
            }
            return $ret;
        }

        /**
         * DELETE
         * @param $route
         * @param null $extra_headers
         * @param null $data
         * @param false $send_in_json
         * @return bool[]|mixed|string[]
         */
        function delete_json_decode($route, $extra_headers = null, $data = null, $send_in_json = false)
        {
            $this->rawResult = $this->delete($route, $extra_headers, $data, $send_in_json);
            $ret = json_decode($this->rawResult, true);
            if (JSON_ERROR_NONE === json_last_error()) $this->rawResult = '';
            else {
                $ret = ['error'=>$this->rawResult];
            }
            return $ret;
        }

        /**
         * GET
         * @param $route
         * @param null $data
         * @param null $extra_headers
         * @param false $send_in_json
         * @return bool|string
         */
        function get($route, $data = null, $extra_headers = null, $send_in_json = false)
        {
            return $this->call($route, $data, 'GET', $extra_headers, $send_in_json);
        }

        /**
         * POST
         * @param $route
         * @param null $data
         * @param null $extra_headers
         * @param false $send_in_json
         * @return bool|string
         */
        function post($route, $data = null, $extra_headers = null, $send_in_json = false)
        {
            return $this->call($route, $data, 'POST', $extra_headers, $send_in_json);
        }

        /**
         * PUT
         * @param $route
         * @param null $data
         * @param null $extra_headers
         * @param false $send_in_json
         * @return bool|string
         */
        function put($route, $data = null, $extra_headers = null, $send_in_json = false)
        {
            return $this->call($route, $data, 'PUT', $extra_headers, $send_in_json);
        }

        /**
         * @param $route
         * @param null $data
         * @param null $extra_headers
         * @param false $send_in_json
         * @return bool|string
         */
        function patch($route, $data = null, $extra_headers = null, $send_in_json = false)
        {
            return $this->call($route, $data, 'PATCH', $extra_headers, $send_in_json);
        }

        /**
         * DELETE
         * @param $route
         * @param null $extra_headers
         * @param null $data
         * @param false $send_in_json
         * @return bool|string
         */
        function delete($route, $extra_headers = null, $data = null, $send_in_json = false)
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
         * Sending JSON data:
         *     Put $raw = true;
         *
         *
         * Extra info: https://stackoverflow.com/questions/4003989/upload-a-file-using-file-get-contents
         * For extra headers you can get further information in: https://www.php.net/manual/de/context.http.php
         * @param $route
         * @param null $data
         * @param string $verb
         * @param null $extra_headers
         * @param bool $raw
         * @return bool|false|string
         */
        function call($route, $data = null, $verb = 'GET', $extra_headers = null, $raw = false)
        {
            $_time = microtime(TRUE);
            $this->core->__p->add("Request->{$verb}: ", "$route " . (($data === null) ? '{no params}' : '{with params}'), 'note');
            $route = $this->getServiceUrl($route);
            $this->responseHeaders = null;

            if($this->sendSysLogs)
                $this->core->logs->add("request {$verb} {$route} ".(($data === null) ? '{no params}' : '{with params}'),'CoreRequest');
            //syslog(LOG_INFO,"request {$verb} {$route} ".(($data === null) ? '{no params}' : '{with params}'));

            // Performance for connections
            $options = $this->default_options;
            $options['http']['header'] = 'Connection: close' . "\r\n";
            if(!is_array($extra_headers) || !isset($extra_headers['Accept']))
                $options['http']['header'] = 'Accept: */*' . "\r\n";


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
            // Extra Headers
            if ($extra_headers !== null && is_array($extra_headers)) {
                foreach ($extra_headers as $key => $value) {
                    $options['http']['header'] .= $key . ': ' . $value . "\r\n";
                }
            }


            // Method
            $options['http']['method'] = $verb;

            // Content-type
            $MULTIPART_BOUNDARY= '--------------------------'.microtime(true);
            if ($verb != 'GET') {
                if (stripos($options['http']['header'], 'Content-type') === false) {
                    if ($raw) {
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
                    if ($raw) {
                        if (stripos($options['http']['header'], 'application/json') !== false) {
                            $build_data = json_encode($data);
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
                if(is_array(json_decode($data,true))) $options['http']['content'] = $data;
            }

            // Save in the class the last options sent
            $this->options = ['route'=>$route,'options'=>$options];
            // Context creation
            $context = stream_context_create($options);

            try {
                $ret = @file_get_contents($route, false, $context);

                // Return response headers
                if(isset($http_response_header)) $this->responseHeaders = $http_response_header;
                else $this->responseHeaders = ['$http_response_header'=>'undefined'];

                // Process response Headers
                $this->processResponseHeaders();

                // If we have an error
                if ($ret === false) {
                    $this->addError(['route_error'=>$route,'reponse_headers'=>$this->responseHeaders,'system_error'=>error_get_last()]);
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
                $this->addError(error_get_last());
                $this->addError($e->getMessage());
            }
            if($this->sendSysLogs) {
                $_time = round(microtime(TRUE) -$_time,4);
                $this->core->logs->add("end request {$verb} {$route} ".(($data === null) ? '{no params}' : '{with params}')." - ".($this->getLastResponseCode())." [{$_time} secs]",'CoreRequest');
            }
            $this->core->__p->add("Request->{$verb}: ", '', 'endnote');


            //syslog(($this->error)?LOG_DEBUG:LOG_INFO,"end request {$verb} {$route} ".(($data === null) ? '{no params}' : '{with params}'));

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


        /*
         * Returns the status number of the last call
         */
        function getLastResponseCode()
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
        function generateCloudFrameWorkSecurityString($id, $time = '', $secret = '')
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
         * @param string $url
         * @param int $format
         * @desc Fetches all the headers
         * @return array
         */
        function getUrlHeaders($url)
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
        function getUrlHeader($url, $header=null) {
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

        function addError($value)
        {
            $this->error = true;
            $this->core->errors->add($value);
            $this->errorMsg[] = $value;
        }
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

        function sendLog($type, $cat, $subcat, $title, $text = '', $email = '', $app = '', $interactive = false)
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
            if (count($this->core->logs->lines)) $params['text'] .= "Logs: " . json_encode($this->core->logs->data, JSON_PRETTY_PRINT);

            // IP gathered from queue
            if (isset($_REQUEST['cloudframework_queued_ip']))
                $params['ip'] = $_REQUEST['cloudframework_queued_ip'];
            else
                $params['ip'] = $this->core->system->ip;

            // IP gathered from queue
            if (isset($_REQUEST['cloudframework_queued_fingerprint']))
                $params['fingerprint'] = $_REQUEST['cloudframework_queued_fingerprint'];
            else
                $params['fingerprint'] = json_encode($this->core->system->getRequestFingerPrint(), JSON_PRETTY_PRINT);

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
         * Send Cors headers to allow AJAX calls
         * @param string $methods
         * @param string $origin
         * @param string $extra_headers
         */
        function sendCorsHeaders($methods = 'GET,POST,PUT', $origin = '',$extra_headers='')
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
         * Check if the URL passed exists
         * @param string $url
         * @return boolean
         */
        function urlExists(string $url)
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
         * Returns a specific Header received in a API call
         * @param $str
         * @return mixed|string
         */
        function getHeader($str)
        {
            $str = strtoupper($str);
            $str = str_replace('-', '_', $str);
            return ((isset($_SERVER['HTTP_' . $str])) ? $_SERVER['HTTP_' . $str] : '');
        }

        /**
         * Return all headers received in a API call
         * @return array
         */
        function getHeaders()
        {
            $ret = array();
            foreach ($_SERVER as $key => $value) if (strpos($key, 'HTTP_') === 0) {
                $ret[str_replace('HTTP_', '', $key)] = $value;
            }
            return ($ret);
        }

        /**
         * Reset $this->errorMsg and $this->error vars
         */
        function reset() {
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
            ];
            $header = [
                'X-WEB-KEY' => 'CoreUser',
                'X-EXTRA-INFO' => $integration_key
            ];

            $cfUserInfo = $this->core->request->post_json_decode($this::APIServices . '/' . $platform_id . '/in?_refresh_integration_keys&from_loadPlatformUserWithUserPassword', $payload, $header);
            if ($this->core->request->error) {
                $this->addError($cfUserInfo['code']??$this->core->request->getLastResponseCode(), $cfUserInfo['message']??$this->core->request->errorMsg);
                $this->core->request->reset();
                return;
            }

            //region SET $user_token,$namespace,$key from token structure or return errorCode: WRONG_TOKEN_FORMAT
            $tokenParts = explode('__', $cfUserInfo['data']['dstoken']);
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

            $token = $cfUserInfo['data']['dstoken'];
            $userData['tokens'][$token] = ['error' => null, 'time' => $now];
            if($cfUserInfo['data']['User']['Expires']??null)
                $this->tokenExpiresIn = $cfUserInfo['data']['User']['Expires']-time();
            $userData['data'] = $cfUserInfo['data'];
            $userData['id'] = $cfUserInfo['data']['User']['KeyName'];

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
         * @return false|void
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
            if(!$userData || $refresh) $userData = ['id'=>null,'tokens'=>[],'data'=>[]];
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
            //$this->expirationTime = $userData['data']['User']['Expires']??time();
            $this->tokenExpiresIn = ($userData['data']['User']['Expires']??time())-time();
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
            if(!isset($this->data['User']['UserPrivileges'])) $this->data['User']['UserPrivileges'] = [];
            if(!in_array($privilege,$this->data['User']['UserPrivileges'])) $this->data['User']['UserPrivileges'][] = $privilege;
        }

        /**
         * Unset a privilege for a user
         * @param $privilege
         * @param bool $active
         */
        function unsetPrivilege($privilege)
        {
            if(!isset($this->data['User']['UserPrivileges'])) $this->data['User']['UserPrivileges'] = [];
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
            if(!($this->data['User']['UserPrivileges']??null)) return false;
            return (isset($this->data['User']['UserPrivileges']) && in_array($privilege,$this->data['User']['UserPrivileges']??[]));
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
         * Set a license for a user
         * @param $license
         * @param bool $active
         */
        function setLicense($license, $active=true)
        {
            if(!isset($this->data['User']['UserLicenses'])) $this->data['User']['UserLicenses'] = [];
            if(!in_array($license,$this->data['User']['UserLicenses'])) $this->data['User']['UserLicenses'][] = $license;
        }

        /**
         * Tell if the user has specific license
         * @param string $license
         * @return bool
         */
        function hasLicense(string $license)
        {
            return (isset($this->data['User']['UserLicenses']) && in_array($license,$this->data['User']['UserLicenses']));
        }

        /**
         * Unset a license for a user
         * @param $license
         * @param bool $active
         */
        function unsetLicense($license)
        {
            if(!isset($this->data['User']['UserLicenses'])) $this->data['User']['UserLicenses'] = [];
            if(($index = array_search($license,$this->data['User']['UserLicenses']))!==false)
                array_splice($this->data['User']['UserLicenses'],$index,1);
        }

        /**
         * Set a privilege for a user
         * @param string $privilege
         * @return bool
         */
        function getLicenses()
        {
            return $this->data['User']['UserLicenses']??[];
        }

        /**
         * Get a user lang. 'en' default
         * @return string
         */
        function getLang()
        {
            return $this->data['User']['UserLang']??'en';
        }

        /**
         * Tell if the user is a PORTAL-USER
         * @return bool
         */
        function isPortalUser()
        {
            return ($this->data['User']['PortalUser']??null)
                && ($this->data['User']['KeyId']??false)
                && $this->isAuth()
                && $this->token;
        }

        /**
         * Tell if the user is a PORTAL-USER
         * @return bool
         */
        function isPlatformUser()
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
        function getTimeZone()
        {
            return $this->data['User']['UserTimeZone']??'UTC';
        }

        /**
         * Get datetime Y-m-d H:i:s converted to User TimeZone
         * @return string
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
         */
        function addError($code,$message)
        {
            $this->error = true;
            $this->errorCode = $code;
            $this->errorMsg[] = $message;
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
            if(strpos($tag,'{')!==false && strpos($tag,'}')!==false) {
                preg_match("/{([^{}]*)}/", $tag, $found);
                while ($found) {
                    $tag = str_replace($found[0], $this->getTag($found[1], $lang, $namespace), $tag);
                    preg_match("/{([^{}]*)}/", $tag, $found);
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

            //if there are <2 $parts retrn $tag
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
         * @return DataStore|DataSQL|void
         */
        public function getModelObject(string $object,$options=[]) {

            //region EVALUATE TO $this->readModelsFromCloudFramework if !$this->models[$object]
            if(isset($options['cf_models_api_key']) && $options['cf_models_api_key'] &&  !isset($this->models[$object])) {
                if(!$this->readModelsFromCloudFramework(preg_replace('/.*:/','',$object),$options['cf_models_api_key'])) return;
            }
            //endregion

            //region SET $object = 'ds|db|bq:'.$object AND VERIFY $this->models[$object] exist
            $source_object = $object;
            if(!strpos($object,':')) {
                if(isset($this->models['db:'.$object])) $object = 'db:'.$object;
                elseif(isset($this->models['ds:'.$object])) $object = 'ds:'.$object;
                elseif(isset($this->models['bq:'.$object])) $object = 'bq:'.$object;
                else $object = 'api:'.$object;
            }

            // Let's find it and return
            if(!isset($this->models[$object])) {
                return($this->addError("Model $source_object does not exist",404));
            }
            if(!isset($this->models[$object]['data']))
                return($this->addError($object. 'Does not have data',503));
            //endregion

            switch ($this->models[$object]['type']) {
                //region PROCESS db OBJECTS
                case "db":
                    list($type,$table) = explode(':',$object,2);

                    if(isset($this->models[$object]['data']['extends']) && $this->models[$object]['data']['extends']) {
                        $model_extended = 'db:'.$this->models[$object]['data']['extends'];
                        if(!isset($this->models[$model_extended])) {
                            if(!$this->readModelsFromCloudFramework(preg_replace('/.*:/','',$model_extended),$options['cf_models_api_key'])) return;
                            if(!isset($this->models[$model_extended])) {
                                return ($this->addError("Model extended $model_extended from model: $object does not exist", 404));
                            }
                        }

                        // Rewrite hasExternalWorkFlows,workFlows and model if it is defined
                        $this->models[$model_extended]['data']['hasExternalWorkFlows'] = (bool)(($this->models[$object]['data']['hasExternalWorkFlows'] ?? false));
                        $this->models[$model_extended]['data']['workFlows'] =  $this->models[$object]['data']['workFlows']??null;
                        if(isset($this->models[$object]['data']['model']) && $this->models[$object]['data']['model']) {
                            $this->models[$model_extended]['data']['model'] =  $this->models[$object]['data']['model'];
                        }

                        //Merge variables with the extended object.
                        if(isset($this->models[$object]['data']['interface']) && $this->models[$object]['data']['interface']) foreach ($this->models[$object]['data']['interface'] as $object_property=>$data) {
                            $this->models[$model_extended]['data']['interface'][$object_property] = $data;
                        }
                        $this->models[$object]['data'] = array_merge(['extended_from'=>$this->models[$object]['data']['extends']],array_merge($this->models[$model_extended]['data'],array_merge($this->models[$object]['data'],$this->models[$model_extended]['data'])));

                    }
                    // rewrite name of the table
                    $table = $this->models[$object]['data']['entity'];
                    if(isset($this->models[$object]['data']['interface']['object'])) $table = $this->models[$object]['data']['interface']['object'];

                    // Object creation
                    if(!is_object($object_db = $this->core->loadClass('DataSQL',[$table,$this->models[$object]['data']]))) return;
                    return($object_db);
                    break;
                //endregion

                //region PROCESS ds OBJECTS
                case "ds":

                    list($type,$entity) = explode(':',$object,2);
                    $namespace = (isset($options['namespace']))?$options['namespace']:$this->core->config->get('DataStoreSpaceName');
                    if(isset($this->models[$object]['data']['interface']['namespace']) && $this->models[$object]['data']['interface']['namespace']) $namespace=$this->models[$object]['data']['interface']['namespace'];
                    if(empty($namespace)) return($this->addError('Missing DataStoreSpaceName config var or $options["namespace"] parameter'));

                    //region EVALUATE extends the object from others
                    if(isset($this->models[$object]['data']['extends']) && $this->models[$object]['data']['extends']) {
                        // look for the model
                        $model_extended = 'ds:'.$this->models[$object]['data']['extends'];
                        if(!isset($this->models[$model_extended])) return($this->addError("Model [$object] extends [$model_extended] and it does not previously read",404));

                        // Rewrite hasExternalWorkFlows,workFlows and model if it is defined
                        $this->models[$model_extended]['data']['hasExternalWorkFlows'] = (bool)(($this->models[$object]['data']['hasExternalWorkFlows'] ?? false));
                        $this->models[$model_extended]['data']['workFlows'] =  $this->models[$object]['data']['workFlows']??null;
                        if(isset($this->models[$object]['data']['model'])) {
                            $this->models[$model_extended]['data']['model'] =  $this->models[$object]['data']['model'];
                        }

                        //Merge variables with the extended object.
                        if(isset($this->models[$object]['data']['interface']))
                            foreach ($this->models[$object]['data']['interface'] as $object_property=>$data) {
                                //merge objects
                                if(in_array($object_property,['fields'])) {
                                    $this->models[$model_extended]['data']['interface'][$object_property] = array_merge($this->models[$model_extended]['data']['interface'][$object_property],$data);
                                }
                                //replace objects
                                else {
                                    $this->models[$model_extended]['data']['interface'][$object_property] = $data;
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

                        $entity = $this->models[$object]['data']['extends'];
                    }
                    //endregion

                    //region REWRITE entity if $this->models[$object]['data']['entity']
                    if(isset($this->models[$object]['data']['entity'])) $entity = $this->models[$object]['data']['entity'];
                    //endregion

                    if(!isset($options['projectId'])) {
                        $project_id = $this->core->config->get('core.gcp.datastore.project_id') ?? $this->core->gc_project_id;
                        $project_id = $this->models[$object]['data']['interface']['project_id'] ?? $project_id;
                        $options['projectId']=$project_id;
                    }

                    if(!isset($options['keyFile']) && isset($this->models[$object]['data']['interface']['secret']) && $this->models[$object]['data']['interface']['secret']) {
                        $options['keyFile'] = $this->models[$object]['data']['interface']['secret'];
                        if($options['keyFile']['project_id']??null) $options['projectId'] = $options['keyFile']['project_id'];
                    }
                    if(!isset($options['namespace']) && isset($this->models[$object]['data']['interface']['namespace']) && $this->models[$object]['data']['interface']['namespace']) $options['namespace'] = $this->models[$object]['data']['interface']['namespace'];
                    if(!is_object($object_ds = $this->core->loadClass('DataStore',[$entity,$namespace,$this->models[$object]['data'],$options]))) return;

                    return($object_ds);
                    break;
                //endregion
                //region PROCESS bq OBJECTS
                case "bq":
                    list($type,$dataset) = explode(':',$object,2);

                    // rewrite name of the table
                    if(isset($this->models[$object]['data']['interface']['object'])) $dataset = $this->models[$object]['data']['interface']['object'];

                    // $options
                    if(!isset($options['projectId'])) {
                        $project_id = $this->core->config->get('core.gcp.bigquery.project_id') ?? $this->core->gc_project_id;
                        $project_id = $this->models[$object]['data']['interface']['project_id'] ?? $project_id;
                        $options['projectId']=$project_id;
                    }

                    if(!isset($options['keyFile']) && isset($this->models[$object]['data']['interface']['secret']) && $this->models[$object]['data']['interface']['secret']) {
                        $options['keyFile'] = $this->models[$object]['data']['interface']['secret'];
                        if($options['keyFile']['project_id']??null) $options['projectId'] = $options['keyFile']['project_id'];
                    }
                    // Object creation
                    if(!is_object($object_bq = $this->core->loadClass('DataBQ',[$dataset,$this->models[$object]['data'],$options]))) return;
                    return($object_bq);
                    break;
                //endregion
            }
            return null;
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
         * Reset Cache of the module
         */
        public function readCache() {
            if($this->cache === null)
                $this->cache = ($this->core->cache->get('Core7.CoreModel'))?:[];
        }

        /**
         * Reset Cache of the module
         */
        public function resetCache() {
            $this->cache = [];
            $this->core->cache->set('Core7.CoreModel',$this->cache);
        }

        /**
         * Update Cache of the module
         */
        public function updateCache($var,$data) {
            $this->readCache();
            $this->cache[$var] = $data;
            $this->core->cache->set('Core7.CoreModel',$this->cache);

        }

        /**
         * Get var Cache of the module
         */
        public function getCache($var) {
            $this->readCache();
            if(isset($this->cache[$var])) return $this->cache[$var];
            else return null;
        }

        /**
         * Add an error in the class
         * @param $msg
         * @param $code
         * @return false to facilitate the return of other methods
         */
        private function addError($msg,$code=0) {
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
         * Add a LOG entry in CloudFrameWorkLogs
         * @param string $app
         * @param string $action string 'ok', 'error', 'check'..
         * @param string $title
         * @param string $method
         * @param null|string $user
         * @param null|array $data
         * @param null|string $slack_url
         * @param null|array $rewrite_fingerprint if you want to rewrite the default fingerprint send it here
         * @param null|string $id
         */
        public function add(string $app, string $action, string $title, string $method, null|string $user, null|string|array $data=null, null|string $slack_url=null, null|array $rewrite_fingerprint=null,null|string $id=null) {
            if(!$this->initDSLogs()) return;

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
                'url'=>(array_key_exists('REQUEST_URI',$_SERVER))?$_SERVER['REQUEST_URI']:''
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
            $this->dsLogs->createEntities($entity);
            if ($this->dsLogs->error) {
                $this->addError($this->dsLogs->errorMsg);
                return($this->core->logs->add(['data'=>$entity,'error'=>$this->dsLogs->errorMsg],'CFILog_add_error'));
            } else {
                $this->core->logs->add('CFILog_add_ok');
                return true;
            }
            //endregion
        }

        /**
         * Init $this->dsLog
         */
        private function initDSLogs() {

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
            $this->dsBitacora = $this->core->model->getModelObject('CloudFrameWorkBitacora');
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
        public function sendToCFBitacora(string $user, string $action,string $solution, string $app, string $app_id=null, string $title=null, $data=null) {
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
        public function bitacora(string $user, string $action, string $solution, string $app, string $app_id=null, string $title=null, $data=null)
        {
            if(!$this->initDSLogs()) return;

            //region SET $rewrite_fingerprint
            $fingerprint = $this->core->system->getRequestFingerPrint();
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
                'url'=>(array_key_exists('REQUEST_URI',$_SERVER))?$_SERVER['REQUEST_URI']:''
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

        /*
         *  Add an error in the class
         */
        private function addError($msg) {
            $this->error = true;
            $this->errorMsg[] = $msg;
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
         * Try to render a template
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

        /*
         * Return the Value from $this->formParams
         * @params $var string var for $this->formParams
         * @return mixed|null returns $this->formParams[$var]
         */
        public function getFormParams($var) {
            return (isset($this->formParams[$var]))?$this->formParams[$var]:null;
        }

        /*
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
         */
        function addError($value)
        {
            $this->error = true;
            $this->core->errors->add($value);
            $this->errorMsg[] = $value;
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

        function hasOption($option) {
            return(in_array('--'.$option, $this->argv));
        }

        function getOptionVar($option) {
            return((isset($this->vars['--'.$option]))?$this->vars['--'.$option]:null);
        }

        function sendTerminal($info='') {
            if(is_string($info)) echo $info."\n";
            else print_r($info);
            if($info)
                $this->sendTerminal[] = "[ ".(round(microtime(true)-$this->time,4))." ms] ".((is_string($info))?$info:json_encode($info));
        }

        function readCache() {
            // Read cache into $this->cache_data if not cache default value [];
            $this->cache_data = ($this->cache->get('Core7_Scripts2020_cache',-1,'',$this->cache_secret_key,$this->cache_secret_iv))?:[];
        }

        function cleanCache() {
            // Read cache into $this->cache_data if not cache default value [];
            $this->cache_data = [];
            $this->cache->set('Core7_Scripts2020_cache',$this->cache_data,'',$this->cache_secret_key,$this->cache_secret_iv);
        }

        function getCacheVar($var) {
            if($this->cache_data === null) $this->readCache();
            return((isset($this->cache_data[$var]))?$this->cache_data[$var]:null);
        }

        function setCacheVar($var,$value) {
            if($this->cache_data === null) $this->readCache();
            $this->cache_data[$var] = $value;
            $this->cache->set('Core7_Scripts2020_cache',$this->cache_data,'',$this->cache_secret_key,$this->cache_secret_iv);
        }

        /**
         * Execute a user Prompt
         * @param $title
         * @param null $default
         * @param null $cache_var
         * @return false|string|null
         */
        function prompt($title,$default=null,$cache_var=null) {

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
         * Execute a user Prompt user for a specific var
         *  $options['title'] = titlte to be shown
         *  $options['default'] = default value
         *  $options['values'] = [array of valid values]
         *  $options['cache_var'] = 'name to cache the result. If result is cached it rewrites default'
         *  $options['type'] = password | number | float
         *  $options['allowed_values'] = allowed values
         *
         * @param $options array array of options
         * @return false|string|null
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
         * Add an error in the script. This method exist to be compatible with RESTFull class
         * @param $code
         * @param $msg
         * @return false to facilitate the return of other functions
         */
        function setErrorFromCodelib($code,$msg) {
            $this->sendTerminal('ERROR: '.$code);
            $this->sendTerminal([$code=>$msg]);
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
         *
         * @param string $user optional is the Google user email. If empty it will prompt it
         * @return array|mixed|void
         */
        function getUserGoogleAccessToken(string $user='')
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
         * @return mixed|void
         */
        function getERPTokenWithGoogleAccessToken($namespace='',$user='') {

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
                if(!($access_token = $this->getUserGoogleAccessToken($user))) return;
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
                $ret = $this->core->request->post_json_decode('https://api7.cloudframework.io/core/signin/'.$namespace.'/in',$payload, ['X-WEB-KEY'=>'getERPTokenWithGoogleAccessToken']);
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
        function __construct(Core7 $core, $argv=null)
        {

            $this->core = $core;
            if(!$this->core->platform) $this->core->platform = $this->core->config->get('core.erp.platform_id');
            $this->initParameters($argv);
            $this->cache = &$this->core->cache;
            $this->time = microtime(true);

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
         * @return void
         */
        function sendTerminal() {

            $args = func_get_args();
            if($args) foreach ($args as $info) {
                if(is_string($info)) echo $info."\n";
                else print_r($info);
            }

            //            if($info)
            //                $this->sendTerminal[] = "[ ".(round(microtime(true)-$this->time,4))." ms] ".((is_string($info))?$info:json_encode($info));
        }

        function readCache() {
            // Read cache into $this->cache_data if not cache default value [];
            $this->cache_data = ($this->cache->get('Core7_Scripts2020_cache',-1,'',$this->cache_secret_key,$this->cache_secret_iv))?:[];
        }

        function cleanCache() {
            // Read cache into $this->cache_data if not cache default value [];
            $this->cache_data = [];
            $this->cache->set('Core7_Scripts2020_cache',$this->cache_data,'',$this->cache_secret_key,$this->cache_secret_iv);
        }

        function getCacheVar($var) {
            if($this->cache_data === null) $this->readCache();
            return((isset($this->cache_data[$var]))?$this->cache_data[$var]:null);
        }

        function setCacheVar($var,$value) {
            if($this->cache_data === null) $this->readCache();
            $this->cache_data[$var] = $value;
            $this->cache->set('Core7_Scripts2020_cache',$this->cache_data,'',$this->cache_secret_key,$this->cache_secret_iv);
        }

        /**
         * Execute a user Prompt
         * @param $title
         * @param null $default
         * @param null $cache_var
         * @return false|string|null
         */
        function prompt($title,$default=null,$cache_var=null) {

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
         * Execute a user Prompt user for a specific var
         *  $options['title'] = titlte to be shown
         *  $options['default'] = default value
         *  $options['values'] = [array of valid values]
         *  $options['cache_var'] = 'name to cache the result. If result is cached it rewrites default'
         *  $options['type'] = password | number | float
         *  $options['allowed_values'] = allowed values
         *
         * @param $options array array of options
         * @return false|string|null
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
         * Add an error in the script. This method exist to be compatible with RESTFull class
         * @param $code
         * @param $msg
         * @return false to facilitate the return of other functions
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
         *
         * @param string $user optional is the Google user email. If empty it will prompt it
         * @return array|mixed|void
         */
        function getUserGoogleAccessToken(string $user='')
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
         * @return mixed|void
         */
        function getERPTokenWithGoogleAccessToken($namespace='',$user='') {

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
                if(!($access_token = $this->getUserGoogleAccessToken($user))) return;
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
                $ret = $this->core->request->post_json_decode('https://api7.cloudframework.io/core/signin/'.$namespace.'/in',$payload, ['X-WEB-KEY'=>'getERPTokenWithGoogleAccessToken']);
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


        /**
         * Add an error in the class
         * @param $value
         * @return false to facilitate the return of other functions.
         */
        function addError($value)
        {
            $this->error = true;
            $this->core->errors->add($value);
            $this->errorMsg[] = $value;
            return false;
        }
    }
}