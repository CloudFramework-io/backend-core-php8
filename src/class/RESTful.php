<?php

// CloudSQL Class v10
if (!defined("_RESTfull_CLASS_")) {
    define("_RESTfull_CLASS_", TRUE);

    /**
     * [$api = $this->core->loadClass('RESTful');] Class CFI to handle APIs. It is been used in Core APIs
     * last_update: 20200502
     * @package CoreClasses
     */
    class RESTful
    {
        var $apiName ='';
        var $formParams = array();
        var $rawData = array();
        var $params = array();
        var $error = 0;
        var $code = null;
        var $codeLib = [];
        var $codeLibError = [];
        var $ok = 200;
        var $errorMsg = [];
        var $message = "";
        var $extra_headers = [];
        var $requestHeaders = array();
        var $method = '';
        var $contentTypeReturn = 'JSON';
        var $url = '';
        var $urlParams = '';
        var $returnData = null;
        var $auth = true;
        var $referer = null;

        var $service = '';
        var $serviceParam = '';
        var $org_id = '';
        var $rewrite = [];
        /** @var DataValidation $dv */
        var $dv;
        /** @var Core7|null  */
        var $core = null;

        function __construct(Core7 &$core, $apiUrl = '/h/api')
        {
            $this->core = $core;
            // FORCE Ask to the browser the basic Authetication
            if (isset($_REQUEST['_forceBasicAuth'])) {
                if (($_REQUEST['_forceBasicAuth'] !== '0' && !$this->core->security->existBasicAuth())
                    || ($_REQUEST['_forceBasicAuth'] === '0' && $this->core->security->existBasicAuth())

                ) {
                    header('WWW-Authenticate: Basic realm="Test Authentication System"');
                    header('HTTP/1.0 401 Unauthorized');
                    echo "You must enter a valid login ID and password to access this resource\n";
                    exit;

                }
            }
            $this->core->__p->add("RESTFull: ", __FILE__, 'note');

            // $this->requestHeaders = apache_request_headers();
            $this->method = (isset($_SERVER['REQUEST_METHOD']) && strlen($_SERVER['REQUEST_METHOD'])) ? $_SERVER['REQUEST_METHOD'] : 'GET';
            if ($this->method == 'GET') {
                $this->formParams = &$_GET;
                if (isset($_GET['_raw_input_']) && strlen($_GET['_raw_input_'])) $this->formParams = (count($this->formParams)) ? array_merge($this->formParams, json_decode($_GET['_raw_input_'], true)) : json_decode($_GET['_raw_input_'], true);
            } else {
                if (count($_GET)) $this->formParams = (count($this->formParams)) ? array_merge($this->formParam, $_GET) : $_GET;
                if (count($_POST)) $this->formParams = (count($this->formParams)) ? array_merge($this->formParams, $_POST) : $_POST;

                // Reading raw format is _raw_input is passed
                //POST
                $raw = null;
                if(isset($_POST['_raw_input_']) && strlen($_POST['_raw_input_'])) $raw = json_decode($_POST['_raw_input_'],true);
                if (is_array($raw)) $this->formParams = (count($this->formParams)) ? array_merge($this->formParams, $raw) : $raw;
                // GET
                $raw = null;
                if(isset($_GET['_raw_input_']) && strlen($_GET['_raw_input_'])) $raw = json_decode($_GET['_raw_input_'],true);
                if (is_array($raw)) $this->formParams = (count($this->formParams)) ? array_merge($this->formParams, $raw) : $raw;


                // raw data.
                $input = file_get_contents("php://input");

                if (strlen($input)) {
                    $this->formParams['_raw_input_'] = $input;

                    // Try to parse as a JSON
                    $input_array = json_decode($input, true);

                    if(!is_array($input_array) && strpos($input,"\n") === false && strpos($input,"=")) {
                        parse_str($input, $input_array);
                    }

                    if (is_array($input_array)) {
                        $this->formParams = array_merge($this->formParams, $input_array);
                        unset($input_array);

                    }
                    /*
                   if(strpos($this->requestHeaders['Content-Type'], 'json')) {
                   }
                     *
                     */
                }

                // Trimming fields
                foreach ($this->formParams as $i=>$data) if(is_string($data)) $this->formParams[$i] = trim ($data);
            }



            // URL splits
            $this->url = (isset($_SERVER['REQUEST_URI']))?str_replace('/_eval/','/',$_SERVER['REQUEST_URI']):'';
            $this->urlParams = '';
            if (isset($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], '?') !== false)
                list($this->url, $this->urlParams) = explode('?', str_replace('/_eval/','/',$_SERVER['REQUEST_URI']), 2);

            // API URL Split. If $this->core->system->url['parts_base_url'] take it out
            $url = $this->url;
            if($this->url) list($foo, $url) = explode($this->core->system->url['parts_base_url'] . '/', $this->url, 2);
            $this->service = $url;
            $this->serviceParam = '';
            $this->params = [];

            if($this->core->is->development()) {
                $this->core->logs->add("Url: [{$this->method}] ". $this->core->system->url['host_url_uri'],'RESTful');
            }

            if (strpos($url, '/') !== false) {
                list($this->service, $this->serviceParam) = explode('/', $url, 2);
                $this->service = strtolower($this->service);
                $this->params = explode('/', $this->serviceParam);
            }

            // Based on: http://www.restapitutorial.com/httpstatuscodes.html
            $this->addCodeLib('ok','OK',200);
            $this->addCodeLib('inserted','Inserted succesfully',201);
            $this->addCodeLib('no-content','No content',204);
            $this->addCodeLib('form-params-error','Wrong form paramaters.',400);
            $this->addCodeLib('params-error','Wrong parameters.',400);
            $this->addCodeLib('security-error','You don\'t have right credentials.',401);
            $this->addCodeLib('not-allowed','You are not allowed.',403);
            $this->addCodeLib('not-found','Not Found',404);
            $this->addCodeLib('method-error','Wrong method.',405);
            $this->addCodeLib('conflict','There are conflicts.',409);
            $this->addCodeLib('gone','The resource is not longer available.',410);
            $this->addCodeLib('unsupported-media','Unsupported Media Type.',415);
            $this->addCodeLib('server-error', 'Generic server error',500);
            $this->addCodeLib('not-implemented', 'There is something in the server not implemented yet',501);
            $this->addCodeLib('service-unavailable','The service is unavailable.',503);
            $this->addCodeLib('system-error','There is a problem in the platform.',503);
            $this->addCodeLib('datastore-error','There is a problem with the Datastore.',503);
            $this->addCodeLib('database-error','There is a problem with the Database.',503);
            $this->addCodeLib('bigquery-error','There is a problem with the BigQuery.',503);
            $this->addCodeLib('ddos-error','DDoS rules is activated for the User or IP.',503);
            $this->addCodeLib('cache-error','There is a problem with the Cache.',503);
            $this->addCodeLib('programming-error','There is a problem with the DataStore.',503);
            $this->addCodeLib('db-error','There is a problem in the Database.',503);
            $this->addCodeLib('ds-error','There is a problem in the Datastore.',503);
            $this->addCodeLib('bq-error','There is a problem in the BigQuery.',503);
            if(method_exists($this,'__codes')) {
                $this->__codes();
            }

        }

        /**
         * Send CORS Headers to allow Ajax Web Calls
         * https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
         *
         * @param string $methods
         * @param string $allow_origins '*' by default. Set domains separated by ',' to allow specific origins. It can start with http for specific urls
         * @param string $allow_extra_headers Access-Control-Allow-Headers extra headers. The framework allows: Content-Type,Authorization,X-Requested-With,cache-control,X-CloudFrameWork-AuthToken,X-CLOUDFRAMEWORK-SECURITY,X-DS-TOKEN,X-REST-TOKEN,X-EXTRA-INFO,X-WEB-KEY,X-SERVER-KEY,X-REST-USERNAME,X-REST-PASSWORD,X-APP-KEY,X-DATA-ENV
         */
        function sendCorsHeaders($methods = 'GET,POST,PUT', $allow_origins = '',$allow_extra_headers='')
        {

            //region CALCULATE $origin taking $allow_origins
            // Rules for Cross-Domain AJAX
            // https://developer.mozilla.org/en-US/docs/Web/HTTP/Access_control_CORS
            $origin = ((array_key_exists('HTTP_ORIGIN',$_SERVER) && strlen($_SERVER['HTTP_ORIGIN'])) ? preg_replace('/\/$/', '', $_SERVER['HTTP_ORIGIN']) : '*');
            if ($allow_origins && $allow_origins!='*') {
                if(is_string($allow_origins)) $allow_origins = explode(',',$allow_origins);
                $_found=false;
                foreach ($allow_origins as $allow_origin) {
                    if(strpos($origin,trim($allow_origin))!==false) {
                        $_found=true;
                    }
                }
                if(!$_found) $origin = $this->core->system->url['host_base_url'];
            }
            //endregion

            //region Access-Control-Allow-Origin
            // https://developer.mozilla.org/en-US/docs/Web/HTTP/Headers/Access-Control-Allow-Origin
            $this->core->logs->add($origin,'trace');
            header("Access-Control-Allow-Origin: {$origin}");
            if($origin!='*')
                header("Vary: Origin");
            $this->core->logs->add($origin.' '.json_encode($allow_origins),'trace');
            //endregion

            //region Access-Control-Allow-Methods
            // https://developer.mozilla.org/en-US/docs/Web/HTTP/Headers/Access-Control-Allow-Methods
            header("Access-Control-Allow-Methods: $methods");
            //endregion

            //region Access-Control-Allow-Methods
            // https://developer.mozilla.org/en-US/docs/Web/HTTP/Headers/Access-Control-Allow-Headers
            $allow_headers='Content-Type,Authorization,X-Requested-With,cache-control,X-CloudFrameWork-AuthToken,X-CLOUDFRAMEWORK-SECURITY,X-DS-TOKEN,X-REST-TOKEN,X-EXTRA-INFO,X-WEB-KEY,X-SERVER-KEY,X-REST-USERNAME,X-REST-PASSWORD,X-APP-KEY,X-DATA-ENV';
            if($allow_extra_headers && is_string($allow_extra_headers)) $allow_headers.=','.strtoupper($allow_extra_headers);
            header("Access-Control-Allow-Headers: {$allow_headers}");
            //endregion

            //region Access-Control-Allow-Credentials
            // https://developer.mozilla.org/en-US/docs/Web/HTTP/Headers/Access-Control-Allow-Credentials
            header("Access-Control-Allow-Credentials: true");
            //endregion

            //region Access-Control-Allow-Max-Age
            // https://developer.mozilla.org/en-US/docs/Web/HTTP/Headers/Access-Control-Max-Age
            header('Access-Control-Max-Age: 1000');
            //endregion

            // To avoid angular Cross-Reference
            if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
                header("HTTP/1.1 200 OK");
                exit();
            }
        }

        /**
         * CHECK if this->method is allowed
         * @param string $methods allowed methods separated by ',': GET,POST
         * @param string|arrat $error_msg error message in case it is not allowed
         * @return bool
         */
        function checkMethod(string $methods, $error_msg = '')
        {
            if (strpos(strtoupper($methods), $this->method) === false) {
                if (!strlen($error_msg)) $error_msg = 'Method ' . $this->method . ' is not supported';
                $this->setErrorFromCodelib('method-error',$error_msg);
            }
            return ($this->error === 0);
        }

        /**
         * Check if $this->formParams[$key] value has been sent.
         * @param string $key variable to be received in $this->formParams.
         * @param string|array $error_msg in case of error is the message to be set in $this->setError(..)
         * @param array $values if it is sent the check if the value match with any of $values or return error
         * @param int $min_length min length of the content
         * @param null|string $code error code to be returned. 'form-params-error' will be the default value
         * @return false|mixed|string
         */
        function checkMandatoryFormParam(string $key, $error_msg = '', $values=[], $min_length = 1, $code=null)
        {
            if (isset($this->formParams[$key]) && is_string($this->formParams[$key]))
                $this->formParams[$key] = trim($this->formParams[$key]);

            if (!isset($this->formParams[$key])
                || (is_string($this->formParams[$key]) && strlen($this->formParams[$key]) < $min_length)
                || (is_array($this->formParams[$key]) && count($this->formParams[$key]) < $min_length)
                || (is_array($values) && count($values) && !in_array($this->formParams[$key], $values))
            ) {
                if (!strlen($error_msg))
                    $error_msg = "{{$key}}" . ((!isset($this->formParams[$key])) ? ' form-param missing ' : ' form-params\' length is less than: ' . $min_length);
                if(!$code) $code='form-params-error';
                $this->setError($error_msg,400,$code,$error_msg);
            }
            // Return
            return (($this->error !== 0)?false:$this->formParams[$key]);
        }

        /**
         * @param string $field
         * @param mix $value
         * @param string $type
         * @param string $validation
         * @param string $msg_error
         */
        function validateValue($field, $value, $type, $validation = '', $msg_error='')
        {
            if(!is_object($this->dv)) $this->dv = $this->core->loadClass('DataValidation');
            $data = [$field=>$value];
            $model = [$field=>['type'=>$type,'validation'=>$validation]];
            $dictionaries =  [];


            if (!$this->dv->validateModel($model, $data, $dictionaries, true)) {
                if ($this->dv->typeError == 'field') {
                        $this->setError($this->dv->field . ': ' . $msg_error.' ['.$this->dv->errorMsg.']', 400);
                } else {
                        $this->setError($this->dv->field . ': ' . $msg_error.' ['.$this->dv->errorMsg.']', 503);
                }
                if (count($this->dv->errorFields))
                    $this->core->errors->add($this->dv->errorFields);
                return false;
            }
            return true;
        }

        /**
         * Check if it is received mandatory params
         * @param array $keys with format: [key1,kye2,..] or [[key1,msg,[allowed,values],min_length],[]]
         * @return bool|void
         */
        function checkMandatoryFormParams($keys)
        {
            if(!$keys) return;

            if (!is_array($keys) && strlen($keys)) $keys = array($keys);
            foreach ($keys as $i=>$item) if(!is_array($item)) $keys[$i] = array($item);

            foreach ($keys as $key)if(is_string($key[0])) {
                $fkey = $key[0];
                $fmsg = (isset($key[1]))?$key[1]:'';
                $fvalues = (array_key_exists(2,$key) && is_array($key[2]))?$key[2]:[];
                $fmin = (isset($key[3]))?$key[3]:1;
                $fcode = (isset($key[4]))?$key[4]:null;
                $this->checkMandatoryFormParam($fkey,$fmsg,$fvalues,$fmin,$fcode);
            }
            return ($this->error === 0);
        }

        /**
         * Check the form paramters received based in a json model
         * @param array $model
         * @param string $codelibbase
         * @param null $data
         * @return bool
         */
        function validatePostData($model,$codelibbase='error-form-params',&$data=null,&$dictionaries=[]) {

            if(null===$data) $data = &$this->formParams;
            if(!($ret = $this->checkFormParamsFromModel($model,true,$codelibbase,$data,$dictionaries))) return;

            if(is_array($model)) foreach ($model as $field=>$props) {
                if(array_key_exists('validation',$props) && preg_match('/(^internal|\|internal)/',$props['validation']) && array_key_exists($field,$data)) {
                    $this->setErrorFromCodelib($codelibbase.'-'.$field,$field.' is internal and can not be rewritten');
                    return false;
                }
            }
            return $ret;
        }

        /**
         * Validate data received in $this->formParams to update a record
         * @param $model
         * @param string $codelibbase
         * @param null $data
         * @param array $dictionaries
         * @return bool|void
         */
        function validatePutData($model,$codelibbase='error-form-params',&$data=null,&$dictionaries=[]) {
            if(null===$data) $data = &$this->formParams;
            if(!($ret = $this->checkFormParamsFromModel($model,false,$codelibbase,$data,$dictionaries))) return;

            if(is_array($model)) foreach ($model as $field=>$props) {
                if(array_key_exists('validation',$props) && preg_match('/(^internal|\|internal)/',$props['validation']) && array_key_exists($field,$data)) {
                    $this->setErrorFromCodelib($codelibbase.'-'.$field,$field.' is internal and can not be rewritten');
                    return false;
                }
            }
            return $ret;
        }

        /**
         * Evaluate $data and if it is correct using $model. It uses the class DataValidation
         * @param $model
         * @param bool $all
         * @param string $codelibbase
         * @param null $data
         * @param array $dictionaries
         * @return bool|void
         */
        function checkFormParamsFromModel(&$model, $all=true, $codelibbase='', &$data=null, &$dictionaries=[])
        {
            if(!is_array($model)) {
                $this->core->logs->add('Passed a non array model in checkFormParamsFromModel(array $model,...)');
                return false;
            }
            if($this->error) return false;
            if(null === $data) $data = &$this->formParams;

            /* Control the params of the URL */
            $params=[];
            if(isset($model['_params'])) {
                $params = $model['_params'];
                unset($model['_params']);
            }

            //region validate there are not internal fields
            foreach ($data as $key=>$datum) {
                if(in_array($key,$model) && isset($model[$key]['validation']) && preg_match('/(^|\|)internal($|\|)/',trim($model[$key]['validation'])) )
                    return($this->setErrorFromCodelib('params-error',$key . ': not allowed in form validation' ));
            }
            //endregion

            //region validate there are not internal fields
            if(!$this->error) {
                /* @var $dv DataValidation */
                $dv = $this->core->loadClass('DataValidation');
                if (!$dv->validateModel($model, $data, $dictionaries, $all)) {

                    if ($dv->typeError == 'field') {
                        if (strlen($codelibbase))
                            $this->setErrorFromCodelib($codelibbase . '-' . $dv->field, $dv->errorMsg, 400, $codelibbase . '-' . $dv->field);
                        else
                            $this->setError($dv->field . ': ' . $dv->errorMsg, 400);
                    } else {
                        if (strlen($codelibbase))
                            $this->setError($this->getCodeLib($codelibbase) . '-' . $dv->field . ': ' . $dv->errorMsg, 503);
                        else
                            $this->setError($dv->field . ': ' . $dv->errorMsg, 503);
                    }
                    if (count($dv->errorFields))
                        $this->core->errors->add($dv->errorFields);
                }

            }
            //endregion

            if(!$this->error && count($params)) {
                if(!$dv->validateModel($params,$this->params,$dictionaries,$all)) {
                    if (strlen($codelibbase)) {
                        $this->setErrorFromCodelib($codelibbase . '-' . $dv->field, $dv->errorMsg);
                    } else
                        $this->setError($dv->field . ': ' . $dv->errorMsg);
                }
            }
            return !$this->error;
        }


        /**
         * Validate that a specific parameter exist: /{end-point}/parameter[0]/parameter[1]/parameter[2]/..
         * @param $pos
         * @param string $msg
         * @param array $validation
         * @param null $code
         * @return bool
         */
        function checkMandatoryParam($pos, $msg = '', $validation = [], $code=null)
        {
            if(!isset($this->params[$pos])) {
                $error=true;
            } else {
                $this->params[$pos] = trim($this->params[$pos]); // TRIM
                $error = strlen($this->params[$pos])==0;         // If empty error
            }

            // Validation by array values
            if (!$error &&  (is_array($validation) && count($validation) && !in_array($this->params[$pos], $validation)) )  $error = true;

            // Validation by string of validation
            if(!$error &&  is_string($validation) && strlen($validation)) {
                /* @var $dv DataValidation */
                $dv = $this->core->loadClass('DataValidation');
                $model = ["params[$pos]"=>['type'=>'string','validation'=>$validation]];
                $data = ["params[$pos]"=>$this->params[$pos]];
                if(!$dv->validateModel($model,$data)) {
                    $msg .= '. Validation error: '.$dv->errorMsg.' ['.$validation.']';
                    $error = true;
                }
            }
            // Generate Error
            if($error) {
                if(empty($code)) $code='params-error';
                $this->setErrorFromCodelib($code,($msg == '') ? 'param ' . $pos . ' is mandatory' : $msg);
            }

            // Return
            return ($this->error?false:$this->params[$pos]);
        }

        /**
         * Set and error to be return in JSON format with status $returnStatus
         * @param mixed $error Error to show. It can be a string or and array
         * @param int $returnStatus integer >=200. 400 by default
         * @param mixed $returnCode optional returnCode
         * @param string $message
         */
        function setError($error, int $returnStatus = 400, $returnCode=null, $message='')
        {
            $this->error = (intval($returnStatus)<=100)?400:intval($returnStatus);
            $this->errorMsg = $error;
            $this->core->errors->add($error);
            $this->code = (null !== $returnCode)? $returnCode:$returnStatus;
            $this->message = ($message)?:(is_string($error)?$error:$this->code);
        }

        function addHeader($key, $value)
        {
            $this->extra_headers[] = "$key: $value";
        }

        function setReturnFormat($method)
        {
            switch (strtoupper($method)) {
                case 'JSON':
                case 'TEXT':
                case 'HTML':
                    $this->contentTypeReturn = strtoupper($method);
                    break;
                default:
                    $this->contentTypeReturn = 'JSON';
                    break;
            }
        }

        /**
         * Returns the info of the header Authorization if it exists, otherwise null
         * @return null|string
         */
        function getHeaderAuthorization()
        {
            $str = 'AUTHORIZATION';
            return ((isset($_SERVER['HTTP_' . $str])) ? $_SERVER['HTTP_' . $str] : null);
        }

        function getRequestHeader($str)
        {
            $str = strtoupper($str);
            $str = str_replace('-', '_', $str);
            return ((isset($_SERVER['HTTP_' . $str])) ? $_SERVER['HTTP_' . $str] : '');
        }

        function getResponseHeaders()
        {
            $ret = array();
            foreach ($_SERVER as $key => $value) if (strpos($key, 'HTTP_') === 0) {
                $ret[str_replace('HTTP_', '', $key)] = $value;
            }
            return ($ret);
        }


        function sendHeaders()
        {
            if($this->core->is->terminal()) return;

            $header = $this->getResponseHeader();
            if (strlen($header)) header($header);
            foreach ($this->extra_headers as $header) {
                header($header);
            }
            switch ($this->contentTypeReturn) {
                case 'JSON':
                    header("Content-Type: application/json");

                    break;
                case 'TEXT':
                    header("Content-Type: text/plain");

                    break;
                case 'HTML':
                    header("Content-Type: text/html");

                    break;
                default:
                    header("Content-Type: " . $this->contentTypeReturn);
                    break;
            }


        }

        function setReturnResponse($response)
        {
            $this->returnData = $response;
        }

        function updateReturnResponse($response)
        {
            if (is_array($response))
                foreach ($response as $key => $value) {
                    $this->returnData[$key] = $value;
                }
        }

        function rewriteReturnResponse($response)
        {
            $this->rewrite = $response;
        }

        /**
         * Init the data to be returned
         * @param $data
         * @return true
         */
        function setReturnData($data)
        {
            $this->returnData['data'] = $data;
            return true;
        }

        /**
         * Add a new record to be returned
         * @param $value
         * @return true
         */
        function addReturnData($value)
        {

            if (!isset($this->returnData['data'])) $this->setReturnData($value);
            else {
                if (!is_array($value)) $value = array($value);
                if (!is_array($this->returnData['data'])) $this->returnData['data'] = array($this->returnData['data']);
                $this->returnData['data'] = array_merge($this->returnData['data'], $value);
            }
            return true;

        }

        /**
         * Add a code for JSON output
         * @param $code
         * @param $msg
         * @param int $error
         * @param null $model
         */
        public function addCodeLib($code, $msg, $error=400, array $model=null) {
            $this->codeLib[$code] = $msg;
            $this->codeLibError[$code] = $error;
            if(is_array($model))
                foreach ($model as $key=>$value) {

                    $this->codeLib[$code.'-'.$key] = $msg.' {'.$key.'}';
                    $this->codeLibError[$code.'-'.$key] = $error;

                    // If instead to pass [type=>,validation=>] pass [type,validaton]
                    if(count($value) && isset($value[0])) {
                        $value['type'] = $value[0];
                        if(isset($value[1])) $value['validation'] = $value[1];
                    }
                    $this->codeLib[$code.'-'.$key].= ' ['.$value['type'].']';
                    // Show the validation associated to the field
                    if(isset($value['validation']))
                        $this->codeLib[$code.'-'.$key].= '('.$value['validation'].')';

                    if($value['type']=='model') {
                        $this->addCodeLib($code.'-'.$key,$msg.' '.$key.'.',$error,$value['fields']);
                    }
                }
        }


        /**
         * Return the structure of $code in $this->codeLib if it exists
         * @param $code
         * @return mixed
         */
        function getCodeLib($code) {
            return (isset($this->codeLib[$code]))?$this->codeLib[$code]:$code;
        }

        /**
         * Return the structure of $code in $this->codeLibError if it exists
         * @param $code
         * @return int|mixed
         */
        function getCodeLibError($code) {
            return (isset($this->codeLibError[$code]))?$this->codeLibError[$code]:400;
        }

        /**
         * Set an error using $code
         * @param string $code
         * @param string $extramsg
         * @return false
         */
        function setErrorFromCodelib(string $code,$extramsg='') {
            $formatted_message =$extramsg;
            if(is_array($formatted_message)) $formatted_message = json_encode($formatted_message,JSON_PRETTY_PRINT);
            if(strlen($formatted_message??'')) $formatted_message = " [{$formatted_message}]";

            // Delete from code any character :.* to delete potential comments
            $this->setError(
                $this->getCodeLib($code).$formatted_message
                ,$this->getCodeLibError($code)
                ,preg_replace('/:.*/','' ,$code??'')
                ,($extramsg)?:$this->getCodeLib($code??'')
            );
            return false;
        }

        /**
         * Return the code applied with setError and defined in __codes
         * @return int|string
         */
        function getReturnCode()
        {
            // if there is no code and status == 200 then 'ok'
            if(null === $this->code &&  $this->getReturnStatus()==200) $this->code='ok';

            // Return the code or the status number if code is null
            return (($this->code!==null) ? $this->code : $this->getReturnStatus());
        }

        /**
         * Assign a code
         * @param $code
         */
        function setReturnCode($code)
        {
            $this->code = $code;
        }

        function getReturnStatus()
        {
            return (($this->error) ? $this->error : $this->ok);
        }

        function setReturnStatus($status)
        {
            $this->ok = $status;
        }

        /**
         * Translate Error codes into headers.
         * Useful info: https://restfulapi.net/http-status-codes/
         */
        function getResponseHeader()
        {
            switch ($this->getReturnStatus()) {
                case 201:
                    $ret = ("HTTP/1.0 201 Created");
                    break;
                case 204:
                    $ret = ("HTTP/1.0 204 No Content");
                    break;
                case 400:
                    $ret = ("HTTP/1.0 400 Bad Request");
                    break;
                case 401:
                    $ret = ("HTTP/1.0 401 Unauthorized");
                    break;
                case 403:
                    $ret = ("HTTP/1.0 403 Forbidden");
                    break;
                case 404:
                    $ret = ("HTTP/1.0 404 Not Found");
                    break;
                case 405:
                    $ret = ("HTTP/1.0 405 Method Not Allowed");
                    break;
                case 409:
                    $ret = ("HTTP/1.0 409 Conflict");
                    break;
                case 500:
                    $ret = ("HTTP/1.0 500 Internal Server Error");
                    break;
                case 503:
                    $ret = ("HTTP/1.0 503 Service Unavailable");
                    break;
                default:
                    if ($this->error) $ret = ("HTTP/1.0 " . $this->error);
                    else $ret = ("HTTP/1.0 200 OK");
                    break;
            }
            return ($ret);
        }

        function checkCloudFrameWorkSecurity($time = 0, $id = '')
        {
            $ret = false;
            $info = $this->core->security->checkCloudFrameWorkSecurity($time,$id); // Max. 10 min for the Security Token and return $this->getConf('CLOUDFRAMEWORK-ID-'.$id);
            if (false === $info) $this->setError($this->core->logs->get(), 401);
            else {
                $ret = true;
                $response['SECURITY-MODE'] = 'CloudFrameWork Security';
                $response['SECURITY-ID'] = $info['SECURITY-ID'];
                $response['SECURITY-EXPIRATION'] = ($info['SECURITY-EXPIRATION']) ? round($info['SECURITY-EXPIRATION']) . ' secs' : 'none';
                $this->setReturnResponse($response);
            }
            return $ret;
        }

        function existBasicAuth()
        {
            return ($this->core->security->existBasicAuth() && $this->core->security->existBasicAuthConfig());
        }

        /**
         * Check if an Authorization-Header Basic has been sent and match with any core.system.authorizations config var.
         * @param string $id
         * @return bool
         */
        function checkBasicAuthSecurity($id='')
        {
            $ret = false;
            if (false === ($basic_info = $this->core->security->checkBasicAuthWithConfig())) {
                $this->setError($this->core->logs->get(), 401);
            } elseif (!isset($basic_info['id'])) {
                $this->setError('Missing "id" parameter in authorizations config file', 401);
            } elseif (strlen($id)>0 && $id != $basic_info['id']) {
                $this->setError('This "id" parameter in authorizations is not allowed', 401);
            } else {
                $ret = true;
                $response['SECURITY-MODE'] = 'Basic Authorization: ' . $basic_info['_BasicAuthUser_'];
                $response['SECURITY-ID'] = $basic_info['id'];
                $this->setReturnResponse($response);
            }
            return $ret;
        }

        function getCloudFrameWorkSecurityInfo()
        {
            if (isset($this->returnData['SECURITY-ID'])) {
                return $this->core->config->get('CLOUDFRAMEWORK-ID-' . $this->returnData['SECURITY-ID']);
            } else return [];
        }


        /**
         * Return the user header key value. In PHP those are $_SERVER["HTTP_$headerKey}"].
         * @param string $headerKey The name of the header. Vars like X-VAR or X_VAR are treated like the same
         * @return string If the key does not exist return empty string
         */
        function getHeader(string $headerKey)
        {
            $headerKey = strtoupper($headerKey);
            $headerKey = str_replace('-', '_', $headerKey);
            return ((isset($_SERVER['HTTP_' . $headerKey])) ? $_SERVER['HTTP_' . $headerKey] : '');
        }

        /**
         * Return all the header keys sent by the user. In PHP those are $_SERVER['HTTP_{varname}']
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

        function getHeadersToResend($extra_headers=null)
        {
            $ret = array();
            foreach ($_SERVER as $key => $value) if (strpos($key, 'HTTP_X_') === 0) {
                $ret[str_replace('_','-',str_replace('HTTP_', '', $key))] = $value;
            }

            if($extra_headers) {
                $extra_headers = explode(',',$extra_headers);
                foreach ($extra_headers as $extra_header) {
                    $header = 'HTTP_'.str_replace('-','_',strtoupper($extra_header));
                    $ret[$extra_header] = (isset($_SERVER[$header]))?$_SERVER[$header]:'';
                }
            }
            return ($ret);
        }

        /**
         * Return the value of  $this->formParams[$var]. Return null if $var does not exist
         * @param string $var
         * @retun null|mixed
         */
        public function getFormParamater($var) {
            if(!isset($this->formParams[$var])) return null;
            return $this->formParams[$var];
        }

        /**
         * Return the value of  $this->params[$var]. Return null if $var does not exist
         * @param integer $var
         * @retun null|mixed
         */
        public function getUrlPathParamater($var) {
            if(!isset($this->params[$var])) return null;
            return $this->params[$var];
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
         * Echo the result
         * @param bool $pretty if true, returns the JSON string with JSON_PRETTY_PRINT
         * @param bool $return if true, instead to echo then return the output.
         * @param array $argv if we are running from a terminal it will receive the command line args.
         * @return mixed
         */
        function send($pretty=false, $return=false, $argv=[])
        {
            // Close potential open connections
            $this->core->model->dbClose();

            //region Prepare success,status,code and optionally message return vards
            $ret = array();
            $ret['success'] = ($this->error) ? false : true;
            $ret['status'] = $this->getReturnStatus();
            $ret['code'] = $this->getReturnCode();
            $ret['time_zone'] = $this->core->system->time_zone[0];
            if($this->message) $ret['message'] = $this->message;
            //endregion

            if($this->core->is->terminal())
                $ret['exec'] = '['.$_SERVER['PWD']. '] php '.implode(' ',$argv);

            if (is_array($this->returnData)) $ret = array_merge($ret, $this->returnData);

            if ($this->core->logs->lines) {
                $ret['logs'] = $this->core->logs->data;

                //Restrict output
                if($this->core->config->get('core_api_logs_allowed_ips') || $this->core->config->get('restful.logs.allowed_ips')) {
                    $_allowed_ips = ($this->core->config->get('restful.logs.allowed_ips'))?:$this->core->config->get('core_api_logs_allowed_ips');
                    if($_allowed_ips!='*' && !$this->core->security->isCron() && !strpos($this->core->system->url['url'],'/queue/')) {
                        if(strpos($_allowed_ips,$this->core->system->ip)===false) {
                            $ret['logs'] = 'only restful.logs.allowed_ips. Current ip: '.$this->core->system->ip;
                        }
                    }
                }
            }

            // If I have been called from a queue or from a Cron the response has to be 200 to avoid recalls
            if ($this->core->security->isCron() || isset($this->formParams['cloudframework_queued'])) {
                if ($this->core->errors->lines) {
                    $ret['queued_return_error'] = $this->core->errors->data;
                    $this->error = 0;
                    $this->ok = 200;
                }
            } elseif ($this->core->errors->lines) {
                $error_var = ($this->core->config->get('core.api.errors.var_name'))?$this->core->config->get('core.api.errors.var_name'):'errors';
                $ret[$error_var] = $this->core->errors->data;
                $this->core->errors->sendToSysLog('CloudFramework RESTFul: '. json_encode($this->core->errors->data,JSON_FORCE_OBJECT),'error');
            }

            // Send response headers
            $this->sendHeaders();

            // If response is 204 (no content) just return
            if($ret['status']==204) return;

            // else continue adding extra info.
            $this->core->__p->add("RESTFull: ", '', 'endnote');
            switch ($this->contentTypeReturn) {
                case 'JSON':
                    if (isset($this->formParams['__p'])) {
                        $this->core->__p->data['config loaded'] = $this->core->config->getConfigLoaded();
                        $ret['__p'] = $this->core->__p->data;
                    }

                    // Debug params
                    if (isset($this->formParams['__debug'])) {
                        if(!$this->core->is->terminal())
                            $ret['__debug']['url'] = ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on') ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
                        $ret['__debug']['method'] = $this->method;
                        $ret['__debug']['ip'] = $this->core->system->ip;
                        $ret['__debug']['header'] = $this->getResponseHeader();
                        $ret['__debug']['session'] = session_id();
                        $ret['__debug']['ip'] = $this->core->system->ip;
                        $ret['__debug']['user_agent'] = ($this->core->system->user_agent != null) ? $this->core->system->user_agent : $this->requestHeaders['User-Agent'];
                        $ret['__debug']['urlParams'] = $this->params;
                        $ret['__debug']['form-raw Params'] = $this->formParams;
                        $ret['__debug']['_totTime'] = $this->core->__p->getTotalTime(5) . ' secs';
                        $ret['__debug']['_totMemory'] = $this->core->__p->getTotalMemory(5) . ' Mb';
                    }

                    // If the API->main does not want to send $ret standard it can send its own data
                    if (count($this->rewrite)) $ret = $this->rewrite;

                    // JSON conversion
                    if($pretty) $ret = json_encode($ret,JSON_PRETTY_PRINT);
                    else $ret = json_encode($ret);

                    break;
                default:
                    if ($this->core->errors->lines) $ret=&$this->core->errors->data;
                    else $ret = &$this->returnData['data'];
                    break;
            }


            // Task control inputs:
            if(isset($_SERVER['HTTP_X_APPENGINE_TASKNAME'])) {
                $this->formParams['HTTP_X_APPENGINE_TASKNAME'] = $_SERVER['HTTP_X_APPENGINE_TASKNAME'];
                $this->formParams['HTTP_X_APPENGINE_QUEUENAME'] = $_SERVER['HTTP_X_APPENGINE_QUEUENAME'];
            }

            // IF THE CALL comes from a queue then LOG the result to facilitate the debug
            if(($this->core->security->isCron() || (isset($this->formParams) && array_key_exists('cloudframework_queued',$this->formParams))) && !strpos($this->core->system->url['url'],'/queue/')) {
                $title = (isset($this->formParams['cloudframework_queued']) && $this->formParams['cloudframework_queued'])?'RESULT FROM QUEUE ':'';
                if($this->core->security->isCron())
                    $title .= 'USING CRON';
                $this->core->logs->add($title, $ret, 'debug');
            }

            // ending script
            if($return) return $ret;
            else {
                if(is_string($ret)) die($ret);
                else die(json_encode($ret,JSON_PRETTY_PRINT));
            }
        }


    } // Class
}
?>
