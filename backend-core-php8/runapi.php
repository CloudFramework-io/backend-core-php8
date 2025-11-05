<?php
/**
 * CloudFrameworkTests 2.1.8
 * last-update: 2021-08
 * https://www.notion.so/cloudframework/Designing-API-Tests-from-CloudFramework-afc8d166610f4b8e98742b98c504053f
 */

if (!defined("_CLOUDFRAMEWORK_CORE_CLASSES_")) {
    define("_CLOUDFRAMEWORK_CORE_CLASSES_", TRUE);

    //region debug function
    /**
     * Echo in output a group of vars passed as args
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

            if($core)
                $core->errors->add(["ErrorCode"=>$errno, "ErrorMessage"=>$errstr, "File"=>$errfile, "Line"=>$errline],'fatal_error','error');

            if(!$core || ($core->is->development() && !$core->is->terminal()))
                _print( ["ErrorCode"=>$errno, "ErrorMessage"=>$errstr, "File"=>$errfile, "Line"=>$errline]);
        }
    }

    register_shutdown_function( "__fatal_handler" );

    /**
     * Print a group of mixed vars passed as arguments
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
     * Core Class to build cloudframework applications
     * @package Core
     */
    final class Core7
    {

        var $_version = 'v73.20251';

        /**
         * @var array $loadedClasses control the classes loaded
         * @link Core::loadClass()
         */
        private $loadedClasses = [];

        var $gc_project_id = null;  // Google Cloud Project ID
        /** @var StorageClient $gc_datastorage_client  */
        var $gc_datastorage_client = null;          // Google Cloud DataStorage Client

        /**
         * Core constructor.
         * @param string $root_path
         */
        function __construct($root_path = '')
        {
            $this->__p = new CorePerformance();
            $this->system = new CoreSystem($root_path);
            $this->logs = new CoreLog();
            $this->errors = new CoreLog();
            $this->is = new CoreIs();
            $this->config = new CoreConfig($this, __DIR__ . '/config.json');
            $this->security = new CoreSecurity($this);
            $this->cache = new CoreCache($this);
            $this->request = new CoreRequest($this);
            $this->cfiLog = new CFILog($this);

            // If the $this->system->app_path ends in / delete the char.
            $this->system->app_path = preg_replace('/\/$/','',$this->system->app_path);

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

                if (!strlen($this->system->url['parts'][$this->system->url['parts_base_index']])) $this->errors->add('missing api end point');
                else {

                    // $apifile will be by default the first element of the end-point url
                    $apifile = $this->system->url['parts'][$this->system->url['parts_base_index']];

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


                    // IF NOT EXIST
                    include_once __DIR__ . '/class/RESTful.php';

                    try {
                        // Include the external file $pathfile
                        if (strlen($pathfile)) {
                            // init data storage client wrapper if filepath starts with gs://
                            $this->__p->add('Loaded $pathfile', __METHOD__,'note');
                            @include_once $pathfile;
                            $this->__p->add('Loaded $pathfile', __METHOD__,'endnote');

                        }

                        // By default the ClassName will be called API.. if the include set $api_class var, we will use that class name
                        if(!isset($api_class)) $api_class = 'API';

                        if (class_exists($api_class)) {
                            /** @var RESTful $api */
                            $api = new $api_class($this,$this->system->url['parts_base_url']);
                            if (array_key_exists(0,$api->params) && $api->params[0] == '__codes') {
                                $__codes = $api->codeLib;
                                foreach ($__codes as $key => $value) {
                                    $__codes[$key] = $api->codeLibError[$key] . ', ' . $value;
                                }
                                $api->addReturnData($__codes);
                            } else {
                                $api->main();
                            }
                            $api->send();

                        } else {
                            $api = new RESTful($this);
                            if(is_file($pathfile)) {
                                $api->setError("the code in '{$apifile}' does not include a {$api_class} class extended from RESTFul. Use: <?php class API extends RESTful { ... your code ... } ", 404);
                            } else {
                                $api->setError("the file for '{$apifile}' does not exist in api directory: ".$pathfile, 404);

                            }
                            $api->send();
                        }
                    } catch (Exception $e) {
                        // ERROR CONTROL WHERE $api is not an object
                        if(!is_object($api)) {
                            $api = new RESTful($this);
                            if(is_file($pathfile)) {
                                $api->setError("the code in '{$apifile}' does not include a {$api_class} class extended from RESTFul. Use: <?php class API extends RESTful { ... your code ... } ", 404);
                            } else {
                                $api->setError("the file for '{$apifile}' does not exist in api directory: ".$pathfile, 404);
                            }
                        }
                        // If $api is an object then an exception has been captured
                        else {
                            $api->setError("the code in '{$apifile}' has produced an exception ", 503);

                        }

                        $this->errors->add(error_get_last());
                        $this->errors->add($e->getMessage());
                        $api->send();
                    }
                    $this->__p->add("API including RESTfull.php and {$apifile}.php: ", 'There are ERRORS');
                }
                return false;
            } // Take a LOOK in the menu
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
            // URL not found in the menu.
            else {
                $this->errors->add('URL has not exist in config-menu');
                _printe($this->errors->data);
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
            if(!$this->config->get('core.api.urls')) return false;
            $paths = $this->config->get('core.api.urls');
            if(!is_array($paths)) $paths = explode(',',$this->config->get('core.api.urls'));

            foreach ($paths as $path) {
                if(strpos($this->system->url['url'], $path) === 0) {
                    $path = preg_replace('/\/$/','',$path);
                    $this->system->url['parts_base_index'] = count(explode('/',$path))-1;
                    $this->system->url['parts_base_url'] = $path;
                    return true;
                }
            }
            return false;
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
    }

    /**
     * Class to track performance
     * @package Core
     */
    class CorePerformance
    {
        var $data = [];
        var $deep = 0;
        var $spaces = "";
        var $lastnote = "";

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
            // Hidding full path (security)
            $file = str_replace($_SERVER['DOCUMENT_ROOT'], '', $file);


            if ($type == 'note') {
                for($this->spaces  = "",$i=0;$i<($this->deep);$i++) $this->spaces.="  ";
                $this->deep++;
                $line = "{$this->spaces}[$type";
                $this->lastnote = 'note';
            }
            else $line = $this->data['lastIndex'] . ' [';

            if (strlen($file)) $file = " ($file)";

            $_time = microtime(TRUE) - $this->data['lastMicrotime'];
            if ($type == 'all' || $type == 'endnote' || $type == 'time' || (isset($_GET['data']) && $_GET['data'] == $this->data['lastIndex'])) {
                $line .=  (round($_time, 3)) . ' secs';
                $this->data['lastMicrotime'] = microtime(TRUE);
            }

            $_mem = memory_get_usage() / (1024 * 1024) - $this->data['lastMemory'];
            if ($type == 'all' || $type == 'endnote' || $type == 'memory' || (isset($_GET['data']) && $_GET['data'] == $this->data['lastIndex'])) {
                $line .= (($line == '[') ? '' : ', ') . number_format(round($_mem, 3), 3) . ' Mb';
                $this->data['lastMemory'] = memory_get_usage() / (1024 * 1024);
            }
            $line .= '] ' . $title;

            $line = (($type != 'note') ? '['
                    . (round(microtime(TRUE) - $this->data['initMicrotime'], 3)) . ' secs, '
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
            $this->data['init'][$spacename][$key]['mem'] = memory_get_usage();
            $this->data['init'][$spacename][$key]['time'] = microtime(TRUE);
            $this->data['init'][$spacename][$key]['ok'] = TRUE;
        }

        function end($spacename, $key, $ok = TRUE, $msg = FALSE)
        {
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
     * Class to interacto with with the System variables
     * @package Core
     */
    class CoreSystem
    {
        var $url, $app,$root_path, $app_path, $app_url;
        var $config = [];
        var $ip, $user_agent, $os, $lang, $format, $time_zone;
        var $geo;

        function __construct($root_path = '')
        {

            // region  $server_var from $_SERVER
            $server_var['HTTPS'] = (array_key_exists('HTTPS',$_SERVER))?$_SERVER['HTTPS']:null;
            $server_var['DOCUMENT_ROOT'] = (array_key_exists('DOCUMENT_ROOT',$_SERVER))?$_SERVER['DOCUMENT_ROOT']:null;
            $server_var['HTTP_HOST'] = (array_key_exists('HTTP_HOST',$_SERVER))?$_SERVER['HTTP_HOST']:null;
            $server_var['REQUEST_URI'] = (array_key_exists('REQUEST_URI',$_SERVER))?$_SERVER['REQUEST_URI']:null;
            $server_var['SCRIPT_NAME'] = (array_key_exists('SCRIPT_NAME',$_SERVER))?$_SERVER['SCRIPT_NAME']:null;
            $server_var['HTTP_USER_AGENT'] = (array_key_exists('HTTP_USER_AGENT',$_SERVER))?$_SERVER['HTTP_USER_AGENT']:null;
            $server_var['HTTP_ACCEPT_LANGUAGE'] = (array_key_exists('HTTP_ACCEPT_LANGUAGE',$_SERVER))?$_SERVER['HTTP_ACCEPT_LANGUAGE']:null;
            $server_var['HTTP_X_APPENGINE_COUNTRY'] = (array_key_exists('HTTP_X_APPENGINE_COUNTRY',$_SERVER))?$_SERVER['HTTP_X_APPENGINE_COUNTRY']:null;
            $server_var['HTTP_X_APPENGINE_CITY'] = (array_key_exists('HTTP_X_APPENGINE_CITY',$_SERVER))?$_SERVER['HTTP_X_APPENGINE_CITY']:null;
            $server_var['HTTP_X_APPENGINE_REGION'] = (array_key_exists('HTTP_X_APPENGINE_REGION',$_SERVER))?$_SERVER['HTTP_X_APPENGINE_REGION']:null;
            $server_var['HTTP_X_APPENGINE_CITYLATLONG'] = (array_key_exists('HTTP_X_APPENGINE_CITYLATLONG',$_SERVER))?$_SERVER['HTTP_X_APPENGINE_CITYLATLONG']:null;
            // endregion

            if (!strlen($root_path)) $root_path = (strlen($_SERVER['DOCUMENT_ROOT'])) ? $_SERVER['DOCUMENT_ROOT'] : $_SERVER['PWD'];

            $this->url['https'] = $server_var['HTTPS'];
            $this->url['protocol'] = ($server_var['HTTPS'] == 'on') ? 'https' : 'http';
            $this->url['host'] = $server_var['HTTP_HOST'];
            $this->url['url_uri'] = $server_var['REQUEST_URI'];

            $this->url['url'] = $server_var['REQUEST_URI'];
            $this->url['params'] = '';
            if (strpos($server_var['REQUEST_URI'], '?') !== false)
                list($this->url['url'], $this->url['params']) = explode('?', $server_var['REQUEST_URI'], 2);

            $this->url['host_base_url'] = (($server_var['HTTPS'] == 'on') ? 'https' : 'http') . '://' . $server_var['HTTP_HOST'];
            $this->url['host_url'] = (($server_var['HTTPS'] == 'on') ? 'https' : 'http') . '://' . $server_var['HTTP_HOST'] . $this->url['url'];
            $this->url['host_url_uri'] = (($server_var['HTTPS'] == 'on') ? 'https' : 'http') . '://' . $server_var['HTTP_HOST'] . $server_var['REQUEST_URI'];
            $this->url['script_name'] = $server_var['SCRIPT_NAME'];
            $this->url['parts'] = explode('/', substr($this->url['url'], 1));
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
            $this->time_zone = array(date_default_timezone_get(), date('Y-m-d h:i:s'), date("P"), time());
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

        }

        function setTimeZone($timezone) {
            date_default_timezone_set($timezone);
            $this->time_zone = array(date_default_timezone_get(), date('Y-m-d h:i:s'), date("P"), time());
        }


        /**
         * Return the IP of the client:
         * https://cloud.google.com/appengine/docs/standard/php-gen2/runtime
         * @return mixed|string
         */
        function getClientIP() {

            $remote_address = (array_key_exists('HTTP_X_FORWARDED_FOR',$_SERVER))?$_SERVER['HTTP_X_FORWARDED_FOR']:'localhost';
            return  ($remote_address == '::1') ? 'localhost' : $remote_address;

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
         * @param string $extra if it is =='geodata' it includes the Geodata as part of the hash
         * @return array
         */
        function getRequestFingerPrint($extra = '')
        {
            // Return the fingerprint coming from a queue
            if (isset($_REQUEST['cloudframework_queued_fingerprint'])) {
                return (json_decode($_REQUEST['cloudframework_queued_fingerprint'], true));
            }

            $ret['user_agent'] = (isset($_SERVER['HTTP_USER_AGENT']))?$_SERVER['HTTP_USER_AGENT']:'unknown';
            $ret['host'] = (isset($_SERVER['HTTP_HOST']))?$_SERVER['HTTP_HOST']:null;
            $ret['software'] = (isset($_SERVER['GAE_RUNTIME']))?('GAE_RUN_TIME:'.$_SERVER['GAE_RUNTIME'].'/'.$_SERVER['GAE_VERSION']):((isset($_SERVER['SERVER_SOFTWARE']))?$_SERVER['SERVER_SOFTWARE']:'Unknown');

            if ($extra == 'geodata') {
                $ret['geoData'] = $this->core->getGeoData();
                unset($ret['geoData']['source_ip']);
                unset($ret['geoData']['credit']);
            }
            $ret['hash'] = sha1(implode(",", $ret));

            $ret['ip'] = $this->ip;
            $ret['http_referer'] = (array_key_exists('HTTP_REFERER',$_SERVER))?$_SERVER['HTTP_REFERER']:'unknown';
            $ret['time'] = date('Ymdhis');
            $ret['uri'] = (isset($_SERVER['REQUEST_URI']))?$_SERVER['REQUEST_URI']:null;
            return ($ret);
        }

        function crypt($input, $rounds = 7)
        {
            $salt = "";
            $salt_chars = array_merge(range('A', 'Z'), range('a', 'z'), range(0, 9));
            for ($i = 0; $i < 22; $i++) {
                $salt .= $salt_chars[array_rand($salt_chars)];
            }
            return crypt($input, sprintf('$2a$%02d$', $rounds) . $salt);
        }

        // Compare Password
        function checkPassword($passw, $compare)
        {
            return (crypt($passw, $compare) == $compare);
        }

    }

    /**
     * Class to manage Logs & Errors
     * https://cloud.google.com/logging/docs/setup/php
     * $logger->info('This will show up as log level INFO');
     * $logger->warning('This will show up as log level WARNING');
     * $logger->error('This will show up as log level ERROR');
     * @package Core
     */
    class CoreLog
    {
        var $lines = 0;
        var $data = [];
        var $syslog_type = 'info';  //error, info, warning, notice, debug, critical, alert, emergency
        /** @var \Google\Cloud\Logging\Logger|\Google\Cloud\Logging\PsrLogger  */
        var $logger = null;
        var $is_development;

        function __construct()
        {
            global $logger;
            $this->logger= &$logger;
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
                    $data = 'SYSLOG ['.$syslog_type.'] '.$syslog_title.': '.$data;
                else
                    $data = ['SYSLOG ['.$syslog_type.'] '.$syslog_title=>$data];
            }
            // Store in local var.
            $this->data[] = $data;
            $this->lines++;

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
                file_put_contents("php://stderr", $syslog_type.': '.$data."\n");
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
     * Class to answer is? questions
     * @package Core
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
     * Class to manage Cache
     * https://cloud.google.com/appengine/docs/standard/php7/using-memorystore#setup_redis_db
     * @package Core
     */
    class CoreCache
    {
        var $cache = null;
        var $spacename = 'CloudFrameWork';
        var $type = 'memory'; // memory, redis, datastore, directory,
        var $dir = '';
        var $error = false;
        var $errorMsg = [];
        var $log = null;
        var $debug = false;
        var $lastHash = null;
        var $lastExpireTime = null;
        var $atom = null;
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

            // Asign a CoreLog Class to log
            $this->log = new CoreLog();

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
                $this->log->add("init(). type: {$this->type}",'CoreCache');

            if ($this->type == 'memory') {
                if (!getenv('REDIS_HOST') || !getenv('REDIS_PORT')) {
                    if($this->debug)
                        $this->log->add("init(). Failed because REDIS_HOST and REDIS_PORT env_vars does not exist.",'CoreCache','warning');
                    $this->cache=-1;
                    return;
                } else {
                    $host = getenv('REDIS_HOST');
                    $port = getenv('REDIS_PORT');
                    try {
                        $this->cache = new Redis();
                        $this->cache->connect($host, $port);
                    } catch (Exception $e) {
                        $this->log->add("init(). REDIS connection failed because: ". $e->getMessage(),'CoreCache','warning');
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
         * @param $name
         */
        function setSpaceName($name)
        {
            if (strlen($name)) {
                $name = '_' . trim($name);
                $this->spacename = preg_replace('/[^A-z_-]/', '_', 'CloudFrameWork_' . $this->type . $name);
            }
        }

        /**
         * Return an object from Cache.
         * @param $key
         * @param int $expireTime The default value es -1. If you want to expire, you can use a value in seconds.
         * @param string $hash if != '' evaluate if the $hash match with hash stored in cache.. If not, delete the cache and return false;
         * @param string $cache_secret_key  optional secret key. If not empty it will encrypt the data en cache
         * @param string $cache_secret_iv  optional secret key. If not empty it will encrypt the data en cache
         * @return bool|mixed|null
         */
        function get($key, $expireTime = -1, $hash = '',$cache_secret_key='',$cache_secret_iv='')
        {
            if(!$this->init() || !strlen(trim($key))) return null;
            $this->core->__p->add("CoreCache.get [{$this->type}]", $key, 'note');

            if (!strlen($expireTime)) $expireTime = -1;

            $info = $this->cache->get($this->spacename . '-' . $key);
            if (strlen($info) && $info !== null) {

                $info = unserialize($info);
                $this->lastExpireTime = microtime(true) - $info['_microtime_'];
                $this->lastHash = $info['_hash_'];

                // Expire Caché
                if ($expireTime >= 0 && microtime(true) - $info['_microtime_'] >= $expireTime) {
                    $this->delete( $key);
                    if($this->debug)
                        $this->log->add("get('$key',$expireTime,'$hash') failed (because expiration) token: ".$this->spacename . '-' . $key.' [hash='.$this->lastHash.',since='.round($this->lastExpireTime,2).' ms.]','CoreCache');

                    $this->core->__p->add("CoreCache.get [{$this->type}]", '', 'endnote');
                    return null;
                }
                // Hash Cache
                if ('' != $hash && $hash != $info['_hash_']) {
                    $this->delete( $key);
                    if($this->debug)
                        $this->log->add("get('$key',$expireTime,'$hash') failed (because hash does not match) token: ".$this->spacename . '-' . $key.' [hash='.$this->lastHash.',since='.round($this->lastExpireTime,2).' ms.]','CoreCache');

                    $this->core->__p->add("CoreCache.get [{$this->type}]", '', 'endnote');
                    return null;
                }
                // Normal return

                if($this->debug)
                    $this->log->add("get('$key',$expireTime,'$hash'). successful returned token: ".$this->spacename . '-' . $key.' [hash='.$this->lastHash.',since='.round($this->lastExpireTime,2).' ms.]','CoreCache');


                $this->core->__p->add("CoreCache.get [{$this->type}]", '', 'endnote');

                // decrypt data if $cache_secret_key and $cache_secret_iv are not empty
                if($cache_secret_key && $cache_secret_iv) $info['_data_'] = $this->core->security->decrypt($info['_data_'],$cache_secret_key,$cache_secret_iv);

                // unserialize vars
                $ret = null;
                try {
                    if(isset($info['_data_']) && $info['_data_'])
                        $ret = ($info['_data_'])?@unserialize(@gzuncompress($info['_data_'])):null;
                } catch (Exception $e) {
                    $ret = null;
                }
                return $ret;


            } else {
                if($this->debug) $this->log->add("get($key,$expireTime,$hash) failed (beacause it does not exist) token: ".$this->spacename . '-' . $key,'CoreCache');

                $this->core->__p->add("CoreCache.get [{$this->type}]", '', 'endnote');
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
            if(!$this->init() || !strlen(trim($key))) return null;
            $this->core->__p->add("CoreCache.set [{$this->type}]", $key, 'note');

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
                $this->log->add("set({$key}). token: ".$this->spacename . '-' . $key.(($hash)?' with hash: '.$hash:''),'CoreCache');

            unset($info);
            $this->core->__p->add("CoreCache.set [{$this->type}]", $key, 'endnote');
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
                $this->log->add("delete(). token: ".$this->spacename . '-' . $key,'CoreCache');

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
     * Class to manate Cache in Files
     * @package Core
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
     * Class to manage CloudFramework configuration.
     * @package Core
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



        }




        /**
         * Get a config var value. $var is empty return the array with all values.
         * @param string $var  Config variable
         * @return mixed|null
         */
        public function get($var='')
        {
            if(strlen($var))
                return (key_exists($var, $this->data)) ? $this->data[$var] : null;
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
         * Reset Cache of the module
         */
        public function readCache() {

            if($this->cache === null) {
                $this->cache = $this->core->cache->get('Core7.CoreConfig', -1,'',$this->cache_secret_key,$this->cache_secret_iv);
                if(!$this->cache) $this->cache=[];
            }
        }

        /**
         * Reset Cache of the module
         */
        public function resetCache() {
            $this->cache = [];
            $this->core->cache->set('Core7.CoreConfig',$this->cache,null,$this->cache_secret_key,$this->cache_secret_iv);
        }

        /**
         * Update Cache of the module
         */
        public function updateCache($var,$data) {
            $this->readCache();
            $this->cache[$var] = $data;
            $this->core->cache->set('Core7.CoreConfig',$this->cache,null,$this->cache_secret_key,$this->cache_secret_iv);
        }

        /**
         * Get var Cache of the module
         */
        public function getCache($var) {
            $this->readCache();
            if(isset($this->cache[$var])) return $this->cache[$var];
            else return null;
        }


    }

    /**
     * Class to manage the security access and dynamic getenv variables
     * @package Core
     */
    class CoreSecurity
    {
        private $core;
        /* @var $dsToken DataStore */
        private $dsToken = null;

        var $error = false;
        var $errorMsg = [];
        var $cache = null;

        function __construct(Core7 &$core)
        {
            $this->core = $core;

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
            } elseif (!$this->core->system->checkPassword($passw, ((isset($auth[$user]['password']) ? $auth[$user]['password'] : '')))) {
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

        function getWebKey()
        {
            if (isset($_GET['web_key'])) return $_GET['web_key'];
            else if (isset($_POST['web_key'])) return $_POST['web_key'];
            else if (strlen($this->getHeader('X-WEB-KEY'))) return $this->getHeader('X-WEB-KEY');
            else return '';
        }

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
                $date = new \DateTime(null, new \DateTimeZone('UTC'));
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
            $spacename = $this->core->config->get('DataStoreSpaceName');
            if (!strlen($spacename)) $spacename = "cloudframework";
            $this->dsToken = $this->core->loadClass('DataStore', ['CloudFrameWorkAuthTokens', $spacename, $dschema]);
            if ($this->dsToken->error) $this->core->errors->add(['setDSToken' => $this->dsToken->errorMsg]);
            return(!$this->dsToken->error);

        }

        /**
         * @param $token Id generated with setDSToken
         * @param string $prefix Prefix to separate tokens Between apps
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
            if(count($retToken)) {
                $retToken[0]['status'] = 0;
                $ret = $this->dsToken->createEntities($retToken[0]);
                if(count($ret)) {
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
                if (!strlen($fingerprint_hash)) $fingerprint_hash = $this->core->system->getRequestFingerPrint()['hash'];
                $record['dateInsert'] = "now";
                $record['fingerprint'] = $fingerprint_hash;
                $record['JSONZIP'] = $this->compress(json_encode($data));
                $record['prefix'] = $prefix;
                $record['secondsToExpire'] = $time_expiration;
                $record['status'] = 1;
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
         * It generates a JSON WEB TOKEN based on a private key
         * based on https://github.com/firebase/php-jwt
         * to generate privateKey ../scripts/jwtRS256.sh
         * @return string|null with a length of 32 chars or null. if null check $this->error, $this->>errorMsg
         */
        public function jwt_encode($payload,$privateKey, $keyId = null, $head = null, $algorithm='SHA256')
        {
            if(!is_string($privateKey) || strlen($privateKey)< 10) return($this->addError('Wrong private key'));

            $header = array('typ' => 'JWT', 'alg' => $algorithm);
            if ($keyId !== null) {
                $header['kid'] = $keyId;
            }
            if ( isset($head) && is_array($head) ) {
                $header = array_merge($head, $header);
            }

            //region create $signing_input
            $segments = array();
            $segments[] = $this->urlsafeB64Encode($this->core->jsonEncode($header));
            $segments[] = $this->urlsafeB64Encode($this->core->jsonEncode($payload));
            $signing_input = implode('.', $segments);
            if($this->error) return;
            //endregion

            //region create $signature signing with the privateKey
            $signature = '';
            $success = openssl_sign($signing_input, $signature, $privateKey, $algorithm);
            if(!$success) {
                return($this->addError(['error'=>true,'errorMsg'=>'OpenSSL unable to sign data']));
            }
            //endregion

            //region retur the signature
            $segments[] = $this->urlsafeB64Encode($signature);
            if($this->error) return;
            return implode('.', $segments);
            //endregion
        }

        /**
         * It decode a JSON WEB TOKEN based on a public key
         * based on https://github.com/firebase/php-jwt
         * to generate publicKey ../scripts/jwtRS256.sh
         * @return string with a length of 32 chars
         */
        public function jwt_decode($jwt,$publicKey,$keyId=null,$algorithm='SHA256')
        {
            if(!is_string($publicKey) || strlen($publicKey)< 10) return($this->addError('Wrong public key'));

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
            if (!array_key_exists('alg',$header) || $header['alg']!='SHA256') {
                return($this->addError('Empty algorithm in header or value != SHA256'));
            }
            if (array_key_exists('kid',$header) && $header['kid']!=$keyId) {
                return($this->addError('KeyId present in header and does not match with $keyId'));
            }

            //region create $signature signing with the privateKey
            $success = openssl_verify("$headb64.$bodyb64",$sig, $publicKey, $algorithm);
            if($success!==1) {
                return($this->addError(['error'=>true,'errorMsg'=>'OpenSSL verification failed. '.openssl_error_string()]));
            }
            //endregion
            return($payload);
        }

        /**
         * Call https://api.clouframework.io/core/api-keys to verify an APIKey
         * More info in: https://www.notion.so/cloudframework/CloudFrameworkSecurity-APIKeys-CFS-APIKeys-13b47034a6f14f23b836c1f4238da548
         *
         * @param string $token  token of the entity of CloudFrameWorkAPIKeys
         * @param string $key   key of the APIKey to evaluate if it exists
         * @param string $spacename spacename of the data. Default cloudframework.
         * @param string $org organization of the entity inside of the spacename. Default common
         * @return bool[]|false[]|mixed|string[]|void
         */
        public function checkAPIKey($token,$key,$spacename='cloudframework',$org='common') {

            //Generate hash and evaluate return cached data
            $hash = md5($token.$key.$spacename.$org);
            if($data = $this->getCache($hash)) return $data;

            // Call CloudFrameWorkAPIKeys Service
            $url = 'https://api.cloudframework.io/core/api-keys/'.$spacename.'/'.$org;
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
        public function readCache() {
            if($this->cache === null)
                $this->cache = ($this->core->cache->get('Core7.CoreSecurity'))?:[];
        }

        /**
         * Reset Cache of the module
         */
        public function resetCache() {
            $this->cache = [];
            $this->core->cache->set('Core7.CoreSecurity',$this->cache);
        }

        /**
         * Update Cache of the module
         */
        public function updateCache($var,$data) {
            $this->readCache();
            $this->cache[$var] = $data;
            $this->core->cache->set('Core7.CoreSecurity',$this->cache);

        }

        /**
         * Get var Cache of the module
         */
        public function getCache($var) {
            $this->readCache();
            if(isset($this->cache[$var])) return $this->cache[$var];
            else return null;
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
         * Add an error Message
         * @param $value
         */
        function addError($value)
        {
            $this->error = true;
            $this->errorMsg[] = $value;
        }



    }


    /**
     * Class to manage HTTP requests
     * @package Core
     */
    class CoreRequest
    {
        protected $core;
        protected $http;
        public $responseHeaders;
        public $error = false;
        public $errorMsg = [];
        public $options = null;
        var $rawResult = '';
        var $automaticHeaders = true; // Add automatically the following headers if exist on config: X-CLOUDFRAMEWORK-SECURITY, X-SERVER-KEY, X-SERVER-KEY, X-DS-TOKEN,X-EXTRA-INFO
        var $sendSysLogs = true;

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
        function getServiceUrl($path = '')
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
                $this->core->logs->sendToSysLog("curl request {$verb} {$route} ".(($data === null) ? '{no params}' : '{with params}'));

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
                            $route .= $key . '=' . rawurlencode($value) . '&';
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
                $this->core->logs->sendToSysLog("end curl request {$verb} {$route} ".(($data === null) ? '{no params}' : '{with params}')." {$_time} secs",(($this->error)?'debug':'info'));
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
            $route = $this->getServiceUrl($route);
            $this->responseHeaders = null;

            if($this->sendSysLogs)
                $this->core->logs->sendToSysLog("request {$verb} {$route} ".(($data === null) ? '{no params}' : '{with params}'));
            //syslog(LOG_INFO,"request {$verb} {$route} ".(($data === null) ? '{no params}' : '{with params}'));

            $this->core->__p->add("Request->{$verb}: ", "$route " . (($data === null) ? '{no params}' : '{with params}'), 'note');
            // Performance for connections
            $options = array('ssl' => array('verify_peer' => false));
            $options['http']['protocol_version'] = '1.1';
            $options['http']['ignore_errors'] = '1';
            $options['http']['header'] = 'Connection: close' . "\r\n";
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
                            $route .= $key . '=' . rawurlencode($value) . '&';
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
                $this->core->logs->sendToSysLog("end request {$verb} {$route} ".(($data === null) ? '{no params}' : '{with params}')." {$_time} secs",(($this->error)?'debug':'info'));
            }

            //syslog(($this->error)?LOG_DEBUG:LOG_INFO,"end request {$verb} {$route} ".(($data === null) ? '{no params}' : '{with params}'));

            $this->core->__p->add("Request->{$verb}: ", '', 'endnote');
            return ($ret);
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
                $ret = $this->core->jsonDecode($this->get('queue/cf_logs/' . urlencode($app) . '/' . urlencode($type), $params, 'POST'), true);
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
     * Class to Manage Datastore Logs
     * @package Core
     */
    class CFILog
    {
        /** @var Core7 $core */
        var $core;

        /** @var DataStore $dsLogs */
        var $dsLogs;

        var $error = false;
        var $errorMsg = null;

        function __construct(Core7 &$core)
        {
            $this->core = $core;
        }

        /**
         * Add a LOG entry in CloudFrameWorkLogs
         * @param $app
         * @param $action string 'ok', 'error', 'check'..
         * @param $title
         * @param $method
         * @param $user
         * @param null|array $data
         * @param null|string $slack_url
         * @param null|array $rewrite_fingerprint if you want to rewrite the default fingerprint send it here
         */
        public function add($app, $action, $title, $method, $user, $data=null, $slack_url=null, $rewrite_fingerprint=null) {
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
                    "JSON": ["json","allownull"]
                  }',true);
            $this->core->model->processModels(['DataStoreEntities'=>['CloudFrameWorkLogs'=>['model'=>$model]]]);
            if($this->core->model->error) return($this->addError($this->core->model->errorMsg));

            $this->dsLogs = $this->core->model->getModelObject('CloudFrameWorkLogs');
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
         * @param true|false $rewrite_fingerprint  // send the fingerprint of the current call
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
     * The sintax is: `Class Logic extends CoreLogic() {..}`
     *
     *
     * Normally your file has to be stored in the `logic/` directory and extend this class.
     * @package Core
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
         *  $options['cache_var'] = 'name to cache the result. If result is cached it rewrites defaul'
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


        function setErrorFromCodelib($code,$msg) {
            $this->sendTerminal([$code=>$msg]);
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

    }
}

//region SET $rootPath and Autload libraries
$rootPath = exec('pwd');
//endregion

//region CHECK local_data directory or create it
if(!is_dir($rootPath.'/local_data')) {
    @mkdir($rootPath.'/local_data');
    if(!is_dir($rootPath.'/local_data')) die('I cannot create ./local_data'."\n\n");
}
if(!is_dir($rootPath.'/local_data/cache')) {
    @mkdir($rootPath.'/local_data/cache');
    if(!is_dir($rootPath.'/local_data/cache')) die('I cannot create ./local_data/cache'."\n\n");
}
//endregion

echo "CloudFramworkTest v202010\nroot_path: {$rootPath}\n";
//region SET $core Core7 object
$core = new Core7($rootPath);
// Set the cache to be written in local_data
$core->cache->activateCacheFile($rootPath.'/local_data/cache');
//endregion

//region SET $script & $formParams

$script = [];
$path='';
$formParams = '';
if(count($argv)>1) {
    if(strpos($argv[1],'?'))
        list($script, $formParams) = explode('?', str_replace('..', '', $argv[1]), 2);
    else {
        $script = $argv[1];
        $formParams = '';
    }
    $script = explode('/', $script);
}
//endregion

$version =$core->request->get_json_decode('https://api.cloudframework.io/erp/development-api-testing/version');
if($core->request->error) {
    echo "ERROR IN CLOUDFRAMEWORK Service";
    _printe($core->request->errorMsg);
}
$version_file = $version['data'].'.php';
echo "------------------------------\n";


// Evaluate if you have access to source script
if((isset($argv[1]) && $argv[1]=='update') || (!is_file($rootPath.'/local_data/'.$version_file))) {
    echo "Downloading CloudFrameworkTest last version\n";
    $file =$core->request->get('https://api.cloudframework.io/erp/development-api-testing/_download');
    if($core->request->error) {
        die("\nERROR downloading file\n".$file."\n\n");
    }
    file_put_contents($rootPath.'/local_data/'.$version_file,$file);
    if(!is_file($rootPath.'/local_data/'.$version_file)) die("\nERROR writting file local_data/{$version_file}\n\n");
    echo('Last version downloaded: '.$version['data']."\n\n");
}


$script_file = '/local_data/'.$version_file;
echo "Using {$script_file}\n";
include_once $rootPath.$script_file;
if(!class_exists('Script')) die('The script does not include a "Class Script'."\nUse:\n-------\n<?php\nclass Script extends Scripts {\n\tfunction main() { }\n}\n-------\n\n");
/** @var Script $script */

$run = new Script($core,$argv);
$run->params = $script;

if(strlen($formParams))
    parse_str($formParams,$run->formParams);

if(!method_exists($run,'main')) die('The class Script does not include the method "main()'."\n\n");

try {
    $run->main();
} catch (Exception $e) {
    $run->sendTerminal(error_get_last());
    $run->sendTerminal($e->getMessage());
}
echo "------------------------------\n";
if($core->errors->data) {
    $run->sendTerminal('Test: ERROR');
}
else $run->sendTerminal('Test: OK');
