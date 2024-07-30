<?php
/**
 * [$cfos = $this->core->loadClass('CFOs',$integrationKey);] Class CFOs to handle CFO app for CloudFrameworkInterface
 * https://www.notion.so/cloudframework/CFI-PHP-Class-c26b2a1dd2254ddd9e663f2f8febe038
 * last_update: 20240725
 * @package CoreClasses
 */
class CFOs {

    /** @var Core7  */
    var $core;
    var $version = '202301051';
    /** @var string $integrationKey To connect with the ERP */
    var $integrationKey='';

    var $error = false;                 // When error true
    var $errorCode = null;                   // Code of error
    var $errorMsg = [];                 // When error array of messages

    var $namespace = 'default';
    var $project_id = null;
    var $service_account = null;
    var $last_cfo = '';

    var $db_connection = null;
    var $keyId = null;
    var $dsObjects = [];
    var $bqObjects = [];
    var $dbObjects = [];
    /** @var CloudSQL $lastDBObject */
    var $lastDBObject = null;
    var $secrets = [];
    var $avoid_secrets = true;   // SET

    /** @var CFOWorkFlows $workFlows */
    var $workFlows = null;


    /**
     * DataSQL constructor.
     * @param Core $core
     * @param array $model where [0] is the table name and [1] is the model ['model'=>[],'mapping'=>[], etc..]
     */
    function __construct(Core7 &$core,$integrationKey='')
    {
        $this->core = $core;
        $this->integrationKey = $integrationKey;
        $this->project_id = $this->core->gc_project_id;
        $this->workFlows = new CFOWorkFlows($core,$this);
        //region Create a
    }

    /**
     * @param string $cfos
     * @return array|false if there is no error return an array with the model structure
     */
    public function readCFOs (string $cfos)
    {
        $models = $this->core->model->readModelsFromCloudFramework($cfos,$this->integrationKey,$this->core->user->id.'_'.$this->namespace.'_'.($this->core->system->url['host']??'nohost'));
        if($this->core->model->error) {
            return $this->addError('model-error',$this->core->model->errorMsg[0]??$this->core->model->errorMsg);
        }
        return $models;
    }

    /**
     * Create a (Datastore) $this->dsObjects[$object] element to be used by ds. If any error it creates a Datastore Foo Object with error message;
     * @param string $object
     * @param string $namespace
     * @param string $project_id
     * @param array $service_account Optional parameter to
     * @return bool
     */
    public function dsInit (string $object,string $namespace='',string $project_id='',array $service_account=[])
    {

        //region IF (!$service_account and $this->service_account ) SET $service_account = $this->service_account
        if(!$service_account && $this->service_account && is_array($this->service_account)) $service_account = $this->service_account;
        //endregion

        //region IF (!$service_account && !$this->avoid_secrets) READ $model to verify $model['data']['secret'] exist
        if(!$service_account && !$this->avoid_secrets) {
            $model = ($this->core->model->models['ds:' . $object] ?? null);
            if (!$model) {
                if(!$this->readCFOs($object)) {
                    $this->createFooDatastoreObject($object);
                    $this->dsObjects[$object]->error = true;
                    $this->dsObjects[$object]->errorMsg = $this->errorMsg;
                    return false;
                }
                $model = ($this->core->model->models['ds:' . $object] ?? null);
            }
            if (($service_account_secret = ($model['data']['secret'] ?? ($model['data']['interface']['secret']??null)))) {
                if (is_string($service_account_secret)) {
                    if (!$service_account = $this->getCFOSecret($service_account_secret)) {
                        $this->core->logs->add("CFO {$object} has a secret and it does not exist in CFOs->secrets[]. Set the secret value or call CFOs->avoidSecrets(true).", 'CFOs_warning');
                        $this->createFooDatastoreObject($object);
                        $this->dsObjects[$object]->error = true;
                        $this->dsObjects[$object]->errorMsg = 'CFO ['.$object.'] hash a secret ['.$service_account_secret.'] and it does not exist in CFOs->secrets. Programmer has to include [CFOs->setSecret(\''.$service_account_secret.'\', array secret] or to include [CFOs->setServiceAccount(array service_account])';
                        return false;
                    }
                } else {
                    $service_account = $service_account_secret;
                }
            }
        }
        //endregion

        //region SET $options[cf_models_api_key,namespace,projectId,projectId]
        $options = ['cf_models_api_key'=>$this->integrationKey];
        if($namespace) $options['namespace'] = $namespace;
        if($project_id) $options['projectId'] = $project_id;
        elseif($this->project_id) $options['projectId'] = $this->project_id;
        if($service_account){
            if (!($service_account['private_key']??null) || !($service_account['project_id']??null)) {
                $this->createFooDatastoreObject($object);
                $this->dsObjects[$object]->error = true;
                $this->dsObjects[$object]->errorMsg = "In CFO[{$object}] there service_account configured does not have private_key or project_id";
                return false;
            }
            $options['keyFile'] = $service_account;
            $options['projectId'] = $service_account['project_id'];
        }
        //endregion

        //region SET $this->dsObjects[$object] = $this->core->model->getModelObject('ds:'.$object,$options);
        $this->dsObjects[$object] = $this->core->model->getModelObject('ds:'.$object,$options);
        if($this->core->model->error) {
            //Return a Foo object instead to avoid exceptions in the execution
            $this->createFooDatastoreObject($object);
            if(is_object($this->dsObjects[$object]??null)) {
                $this->dsObjects[$object]->error = true;
                $this->dsObjects[$object]->errorMsg = $this->core->model->errorMsg;
            } else {
                $this->core->logs->add('Error creating Foo datastore Object when error','error_CFOs_dsInit');
            }
            return false;
        }
        //endregion


        return true;
    }

    /**
     * @param $object
     * @return DataStore
     */
    public function ds ($object): DataStore
    {
        if(!isset($this->dsObjects[$object]))
            $this->dsInit($object);

        $this->last_cfo = $object;
        return $this->dsObjects[$object];
    }

    /**
     * Initialize a bq $object
     * @param string $object
     * @param string $project_id
     * @param array $service_account
     * @return bool
     */
    public function bqInit (string $object, string $project_id='', array $service_account=[])
    {

        //region IF (!$service_account and $this->service_account ) SET $service_account = $this->service_account
        if(!$service_account && $this->service_account && is_array($this->service_account)) $service_account = $this->service_account;
        //endregion

        //region IF (!$service_account && !$this->avoid_secrets) READ $model to verify $model['data']['secret'] exist
        if(!$service_account && !$this->avoid_secrets) {
            $model = ($this->core->model->models['bq:' . $object] ?? null);
            if (!$model) {
                if(!$this->readCFOs($object)) {
                    $this->createFooBQObject($object);
                    $this->bqObjects[$object]->error = true;
                    $this->bqObjects[$object]->errorMsg = $this->errorMsg;
                    return false;
                }
                $model = ($this->core->model->models['bq:' . $object] ?? null);
            }

            if (($service_account_secret = ($model['data']['secret'] ?? null))) {
                if (is_string($service_account_secret)) {
                    if (!$service_account = $this->getCFOSecret($service_account_secret)) {
                        $this->core->logs->add("CFO {$object} has a secret and it does not exist in CFOs->secrets[]. Set the secret value or call CFOs->avoidSecrets(true).", 'CFOs_warning');
                        $this->createFooBQObject($object);
                        $this->bqObjects[$object]->error = true;
                        $this->bqObjects[$object]->errorMsg = 'CFO ['.$object.'] hash a secret ['.$service_account_secret.'] and it does not exist in CFOs->secrets. Programmer has to include [CFOs->setSecret(\''.$service_account_secret.'\', array secret] or to include [CFOs->setServiceAccount(array service_account])';
                        return false;
                    }
                } else {
                    $service_account = $service_account_secret;
                }
            }
        }
        //endregion

        //region SET $options[cf_models_api_key,namespace,projectId,projectId]
        $options = ['cf_models_api_key'=>$this->integrationKey];
        if($project_id) $options['projectId'] = $project_id;
        elseif($this->project_id) $options['projectId'] = $this->project_id;
        if($service_account){
            if (!($service_account['private_key']??null) || !($service_account['project_id']??null)) {
                $this->createFooBQObject($object);
                $this->bqObjects[$object]->error = true;
                $this->bqObjects[$object]->errorMsg = "In CFO[{$object}] there service_account configured does not have private_key or project_id";
                return false;
            }
            $options['keyFile'] = $service_account;
            $options['projectId'] = $service_account['project_id'];
        }
        //endregion

        //region SET $this->bqObjects[$object] = $this->core->model->getModelObject('bq:'.$object,$options);
        $this->bqObjects[$object] = $this->core->model->getModelObject('bq:'.$object,$options);
        if($this->core->model->error) {
            if(!is_object($this->bqObjects[$object]))
                $this->createFooBQObject($object);
            $this->bqObjects[$object]->error = true;
            $this->bqObjects[$object]->errorMsg = $this->core->model->errorMsg;
        }
        //endregion

        return true;
    }

    /**
     * Return a bq $object
     * @param $object
     * @return DataBQ
     */
    public function bq ($object): DataBQ
    {
        if(!isset($this->bqObjects[$object]))
            $this->bqInit($object);

        $this->last_cfo = $object;
        return $this->bqObjects[$object];
    }

    /**
     * Initialize a bq $object
     * @param string $object
     * @param string $connection name of the connection
     * @param array $db_credentials optional credentials for the connection
     * @return bool
     */
    public function dbInit (string $object, $connection='default', array $db_credentials = [])
    {

        //region EVALUATE IF $this->dbObjects[$object]) exist to create it
        if(!isset($this->dbObjects[$object])) {

            //region READ $model
            $model = ($this->core->model->models['db:' . $object] ?? null);
            if (!$model) {
                if(!$this->readCFOs($object)) {
                    $this->createFooDBObject($object);
                    $this->dbObjects[$object]->error = true;
                    $this->dbObjects[$object]->errorMsg = $this->errorMsg;
                    return false;
                }
                $model = ($this->core->model->models['db:' . $object] ?? null);
            }
            //endregion

            //region EVALUATE IF $model['data']['secret'] exist to update $db_connection
            if (!$db_credentials && ($service_account_secret = ($model['data']['secret'] ?? null)) && !$this->avoid_secrets) {
                if (is_string($service_account_secret)) {
                    if(!$db_credentials = $this->getCFOSecret($service_account_secret)) {
                        $this->core->logs->add("CFO {$object} has a secret and it does not exist in CFOs->secrets[]. Use the CFOs->setSecret()  or set CFOs->useCFOSecret(true).", 'CFOs_warning');
                        $this->createFooDBObject($object);
                        $this->dbObjects[$object]->error = true;
                        $this->dbObjects[$object]->errorMsg = ['CFO ['.$object.'] has a secret ['.$service_account_secret.'] and it does not exist in CFOs->secrets. Programmer has to include [CFOs->setSecret(\''.$service_account_secret.'\', array secret] or add it in CLOUD-DEVELOPMENT/Secrets'];
                        if($this->error) $this->dbObjects[$object]->errorMsg[] = $this->errorMsg;
                        return false;
                    }

                } else {
                    $db_credentials = $service_account_secret;
                }
            }
            //endregion

            //region INIT $this->dbObjects[$object]
            $this->dbObjects[$object] = $this->core->model->getModelObject('db:'.$object,['cf_models_api_key'=>$this->integrationKey]);
            if($this->core->model->error) {
                if(!is_object($this->dbObjects[$object]))
                    $this->createFooDBObject($object);
                $this->dbObjects[$object]->error = true;
                $this->dbObjects[$object]->errorMsg = $this->core->model->errorMsg;
                return false;
            }
            //endregion

            //region EVALUATE $db_connection
            if($db_credentials) {
                //region REWRITE dbSocket to null for localEnvironment
                if($this->core->is->localEnvironment() && ($db_credentials['dbServer']??null) && ($db_credentials['dbSocket']??null) )
                    $db_credentials['dbSocket']=null;
                //endregion
            }
            //endregion

        }
        //endregion

        //region ASSURE the object is created
        $this->core->model->dbInit($connection,$db_credentials);
        $this->lastDBObject = &$this->core->model->dbConnections[$connection];
        //endregion

        return true;
    }

    /**
     * @param $object
     * @return DataSQL
     */
    public function db ($object,$connection='default'): DataSQL
    {
        if(!isset($this->dbObjects[$object]))
            $this->dbInit($object,$connection);

        $this->last_cfo = $object;
        return $this->dbObjects[$object];
    }

    /**
     * Execute a Direct query inside a $connection
     * @param $q
     * @param null $params
     * @param string $connection
     * @return array|void
     */
    public function dbQuery ($q,$params=null,$connection='default')
    {
        $this->core->model->dbInit($connection);
        $this->lastDBObject = &$this->core->model->dbConnections[$connection];

        $ret= $this->core->model->dbQuery('CFO Direct Query for connection  '.$connection,$q,$params);
        if($this->core->model->error) $this->addError('database-error',$this->core->model->errorMsg);
        return($ret);
    }

    /**
     * @param string $object
     * @return CloudSQL|bool returns false if error
     */
    public function dbConnection (string $connection='default'): CloudSQL|bool
    {
        if(!$connection) $connection='default';
        if(!$this->core->model->dbInit($connection))
            return $this->addError('database-error',$this->core->model->errorMsg);
        return($this->core->model->db);

    }

    /**
     * Close Database Connections
     * @param string $connection Optional it specify to close a specific connection instead of all
     */
    public function dbClose (string $connection='')
    {
        $this->core->model->dbClose($connection);
    }

    /**
     * Assign DB Credentials to stablish connection
     * @param array $credentials Varaibles to establish a connection
     * $credentials['dbServer']
     * $credentials['dbUser']
     * $credentials['dbPassword']??null);
     * $credentials['dbName']??null);
     * $credentials['dbSocket']??null);
     * $credentials['dbProxy']??null);
     * $credentials['dbProxyHeaders']??null);
     * $credentials['dbCharset']??null);
     * $credentials['dbPort']??'3306');
     * @param string $connection Optional name of the connection. If empty it will be default
     * @return boolean
     */
    public function setDBCredentials (array $credentials,string $connection='default')
    {
        $this->core->config->set("dbServer",$credentials['dbServer']??null);
        $this->core->config->set("dbUser",$credentials['dbUser']??null);
        $this->core->config->set("dbPassword",$credentials['dbPassword']??null);
        $this->core->config->set("dbName",$credentials['dbName']??null);
        $this->core->config->set("dbSocket",$credentials['dbSocket']??null);
        $this->core->config->set("dbProxy",$credentials['dbProxy']??null);
        $this->core->config->set("dbProxyHeaders",$credentials['dbProxyHeaders']??null);
        $this->core->config->set("dbCharset",$credentials['dbCharset']??null);
        $this->core->config->set("dbPort",$credentials['dbPort']??'3306');
        if($this->core->is->localEnvironment()) $this->core->config->set("dbSocket",null);

        // SET to null dbSocket if you are in localhost
        if($this->core->is->localEnvironment() && $this->core->config->get("dbSocket") && $this->core->config->get("dbServer"))
            $this->core->config->set("dbSocket",null);

        return true;
    }

    /**
     * Try to read from CLOUD-PLATFORM $secret
     * @param string $platform_secret_variable name of the secret with se format: {secret-id}.{variable}
     * @param string $platform_id optional platform id. If is not passed it will take $this->namespace
     * @return bool
     */
    public function setDBCredentialsFromPlatformSecret(string $platform_secret_variable,string $platform_id='') {

        if(!$platform_id) $platform = $this->namespace;
        if(!strpos($platform_secret_variable,'.')) return $this->addError('function-conflict','setDBCredentialsFromPlatformSecret($platform_secret_variable) has received a value with wrong format. Use {secret_id}.{varname}')??false;
        if(!$secret = $this->getPlatformSecret($platform_secret_variable,$platform_id)) return false;
        if(!($secret['dbServer']) && !($secret['dbSocket']))  return $this->addError('secret-conflict','setDBCredentialsFromPlatformSecret($platform_secret_variable) has received secret with no [dbName or dbSocket] parameter')??false;
        if ($this->core->is->localEnvironment()) {
            if(!($secret['dbServer']))  {
                return $this->addError('secret-conflict','setDBCredentialsFromPlatformSecret($platform_secret_variable) has received secret with no [dbServer] parameter for local environment')??false;
            }
            $secret['dbSocket'] = null;
        }
        $this->setDBCredentials($secret);
        return true;
    }

    /**
     * Return the PlatformSecret value of $secret only if $this->avoid_secrets is false. Else it will return empty array []
     * It $platform_secret_id does not exist it will return an error
     * @param string $platform_secret_id Secret to read with format {secret_id}.{varname}
     * @return mixed return the secret value or null if error
     */
    public function getCFOSecret(string $platform_secret_id): mixed
    {
        if($this->avoid_secrets) return [];
        if(!strpos($platform_secret_id,'.')) return $this->addError('function-conflict',"CFOs.getCFOSecret(\$secret) has a wrong format. Use {secret_id}.{varname}");
        else return $this->getPlatformSecret($platform_secret_id);

    }

    /**
     * Read a PlatformSecret into $this->secrets[] and return the value.
     * It $platform_secret_id does not exist it will return an error
     * @param string $platform_secret_id Secret to read with format {secret_id}.{varname}
     * @return mixed return the secret value or null if error
     */
    public function getPlatformSecret(string $platform_secret_id,$platform_id=''): mixed
    {
        if(!$platform_id) $platform_id = $this->namespace;
        if(isset($this->secrets[$platform_secret_id]) && $this->secrets[$platform_secret_id]) return $this->secrets[$platform_secret_id];
        if(!strpos($platform_secret_id,'.')) return $this->addError('function-conflict',"CFOs.readPlatformSecret(\$secret) has a wrong format. Use {secret_id}.{varname}");
        list($secret_id, $var_id ) = explode('.',$platform_secret_id,2);
        $this->secrets[$platform_secret_id] = $this->core->security->getPlatformSecretVar($var_id,$secret_id,$platform_id);
        if($this->core->security->error)
            return($this->addError('platform-secret-error',['CFOs.readPlatformSecret($secret) has produced an error.',$this->core->security->errorMsg]));
        return $this->secrets[$platform_secret_id]?:[];
    }

    /**
     * Create a Foo Datastore Object to be returned in case someone tries to access a non created object
     * @ignore
     */
    public function createFooDatastoreObject($object) {
        if(!isset($this->dsObjects[$object]) || !is_object($this->dsObjects[$object])) {
            $model = json_decode('{
                                    "KeyName": ["keyname","index|minlength:4"]
                                  }',true);
            $this->dsObjects[$object] = $this->core->loadClass('Datastore',['Foo','default',$model]);
            if ($this->dsObjects[$object]->error) return($this->addError('datastore-error',$this->dsObjects[$object]->errorMsg));
        }
    }

    /**
     * Create a Foo BQ Object to be returned in case someone tries to access a non created object
     * @ignore
     */
    public function createFooBQObject($object) {
        if(!isset($this->bqObjects[$object])) {
            $model = json_decode('{
                                    "KeyName": ["string","index|minlength:4"]
                                  }',true);
            $this->bqObjects[$object] = $this->core->loadClass('DataBQ',['Foo',$model]);
            if ($this->bqObjects[$object]->error) return($this->addError('bigquery-error',$this->dsObjects[$object]->errorMsg));
        }
    }

    /**
     * Create a Foo DB Object to be returned in case someone tries to access a non created object
     * @ignore
     */
    public function createFooDBObject($object) {

        if(!isset($this->dbObjects[$object])) {
            $model = json_decode('{
                                    "KeyName": ["int","isKey"]
                                  }',true);

            $this->dbObjects[$object] = $this->core->loadClass('DataSQL',['Foo',['model'=>$model]]);
            if ($this->dbObjects[$object]->error) return($this->addError('database-error',$this->dbObjects[$object]->errorMsg));
        }
    }

    /**
     * @param $namespace
     */
    function setNameSpace($namespace) {
        $this->namespace = $namespace;
        $this->core->config->set('DataStoreSpaceName',$this->namespace);
        foreach (array_keys($this->dsObjects) as $object) {
            $this->ds($object)->namespace=$namespace;
        }
    }

    /**
     * Set a default project_id overwritting the default project_id
     * @param $project_id
     */
    function setProjectId($project_id) {
        $this->project_id = $project_id;
    }

    /**
     * If ($avoid==true and if !$this->service_account) the secrets of Datastore, Bigquery, Database CFOs will be tried to be read. False by default
     * @param bool $avoid
     */
    function avoidSecrets(bool $avoid) {
        $this->avoid_secrets = $avoid;
    }

    /**
     * If ($use==true and if !$this->service_account) the secrets of Datastore, Bigquery, Database CFOs will be tried to be read. False by default
     * @param bool $avoid
     */
    function useCFOSecret(bool $use) {
        $this->avoid_secrets = !$use;
    }

    /**
     * Set $this->integrationKey to connect with CFO models
     * @param $key
     */
    public function setIntegrationKey (string $key) {
        $this->integrationKey = $key;
    }

    /**
     * Set secrets to be used by Datastore, Bigquery, Database
     * @param $key
     * @param array $value
     */
    public function setSecret ($key,array $value) {
        $this->secrets[$key] = $value;
    }

    /**
     * Set a default service account for Datastore and BigQuery Objects. It has to be an array and it will rewrite the secrets includes in the CFOs for ds and bigquery
     * @param array $service_account
     */
    function setServiceAccount(array $service_account) {
        $this->service_account = $service_account;
    }

    /**
     * Set a default DB Connection for CloudSQL. It has to be an array and it will rewrite the secrets included in the CFOs for db
     * @param array $db_connection
     */
    function setDBConnection(array $db_connection) {
        $this->db_connection = $db_connection;
    }

    /**
     * Execute a Manual Query
     * @param string $txt
     * @return array|void
     */
    public function transformTXTInDatastoreModel(string $txt, string $cfo)
    {
        $model_lines = explode("\n", $txt);
        $ds_model = [];
        $group = '';
        if ($model_lines > 1) {
            list($entity, $group) = explode(':', trim($model_lines[0]), 2);
            if ($entity != $cfo) {
                $error = 'Model first line entity does not match with DS:Entity: ' . $this->getFormParamater('entity');
            } else {
                list($key, $type, $info) = explode(',', trim($model_lines[1]), 3);
                if (!in_array($key, ['KeyName', 'KeyId'])) {
                    $error = 'First field has to be KeyId or KeyName';
                } elseif (!$type) {
                    $error = 'First field does not have second element type';
                } else {
                    $ds_model[$key] = [($key=='KeyName')?'keyname':'key', $info];
                    for ($i = 2, $tr = count($model_lines); $i < $tr; $i++) if ($model_lines[$i]) {
                        list($key, $type, $info) = explode(',', trim($model_lines[$i]), 3);
                        if (!$type) {
                            $error = "The field $key does not have type";
                            break;
                        }
                        $ds_model[$key] = [$type, $info];
                    }
                }
            }
        }
        if($error) return $this->setError($error);
        else return ['group'=>$group,'model'=>$ds_model];
    }

    /**
     * Return a structure with bigquery squema based on CF model
     * @return array
     */
    public function getInterfaceModelFromDatastoreModel($entity,$model,$group,$secret_id='')
    {

        $fields_definition = [];
        $fields = [];
        $fields_list = [];
        $key_type = $model['KeyName']?'KeyName':'KeyId';
        $filters=[];

        $order=null;
        foreach ($model as $_key=>$item) {
            $name = $_key;
            if(stripos(($item[1]??''),'name:')!==false){
                $name = preg_replace('/\|.*/','',explode('name:',$item[1],2)[1]);
            }
            $fields_definition[$_key] =  ['name'=>$name];
            if(in_array($item[0],['date','datetime','json','html','zip','boolean'])) {
                $fields_definition[$_key]['type'] = $item[0];
            }

            if(stripos(($item[1]??''),'allownull')!==false) {
                $fields_definition[$_key]['allow_empty']=true;
            }
            if(strpos(($item[1]??''),'index')!==false || $_key==$key_type) {
                $fields_list[$_key] = ['field' => $_key];
                if ($_key == $key_type) {
                    $fields_list[$_key]['display_cfo'] = true;
                }
                if(!$order && $_key!=$key_type) {
                    $order = $fields_list[$_key]['order'] = 'asc';
                }
            }
            $fields[$_key] =  ['field'=>$_key];

            $type='text';
            if(in_array($item[0],['date','datetime'])) {
                $type=$item[0];

            }
            elseif(in_array($item[0],['list'])) $type='select';
            if(strpos(($item[1]??''),'index')!==false) {
                $filters[] = [
                    'field'=>$_key,
                    'name'=>$_key,
                    'type'=>$type,
                    'placeholder'=>'Example for '.$_key
                ];
            }
        }

        $cfo = [
            'KeyName'=>"{$entity}"
            ,'type'=>'ds'
            ,'entity'=>"{$entity}"
            ,'extends'=>null
            ,'GroupName'=>"{$group}"
            ,'model'=>[
                'model'=>$model
                ,'dependencies'=>[]
            ]
            ,'securityAndFields'=>[
                'security'=>[
                    'cfo_locked'=>false,
                    'user_privileges'=>[],
                    'user_groups'=>[],
                    'user_organizations'=>[],
                    'user_namespaces'=>[],
                    'allow_update'=>[
                        'user_privileges'=>[],
                        'user_groups'=>[],
                        'user_organizations'=>[],
                        'user_namespaces'=>[],
                        'field_values'=>[]],
                    'allow_display'=>[],
                    'allow_delete'=>[],
                    'allow_copy'=>[],
                    'logs'=>['list'=>false,'display'=>false,'update'=>false,'delete'=>false],
                    'backups'=>['update'=>false,'delete'=>false]
                ],
                'fields'=>$fields_definition]
            ,'interface'=>[
                'object'=>"$entity"
                ,'name'=>$entity.' Report'
                ,'plural'=>$entity.' Reports'
                ,'ico'=>'building'
                ,'modal_size'=>'xl'
                ,'secret'=>$secret_id
                ,'filters'=>$filters
                ,'buttons'=>[['type'=>'api-insert','title'=>'Insert'],['type'=>'api-bulk','title'=>'Bulk Insert']]
                ,'views'=>['default'=>[
                    'name'=>'Default View',
                    'all_fields'=>true,
                    'server_fields'=>null,
                    'server_order'=>null,
                    'server_where'=>null,
                    'server_limit'=>200,
                    'fields'=>$fields_list]]
                ,'display_fields'=>$fields
                ,'update_fields'=>$fields
                ,'insert_fields'=>$fields
                ,'copy_fields'=>$fields
                ,'delete_fields'=>[$key_type=>['field'=>$key_type]]
                ,'hooks'=>['on.insert'=>[],'on.update'=>[],'on.delete'=>[]]
            ]
        ];

        if($key_type=='KeyId') {
            unset($cfo['interface']['insert_fields']['KeyId']);
            unset($cfo['interface']['copy_fields']['KeyId']);
        }
        return $cfo;
    }

    /**
     * Reset the cache to load the CFOs
     * @param $namespace
     */
    function resetCache() {
        $this->core->model->resetCache();
    }

    /**
     * Add an error in the class
     * @param string $code Code of error
     * @param mixed $value
     * @return bool Always return null to facilitate other return functions
     */
    function addError(string $code,$value)
    {

        $this->error = true;
        $this->errorCode = $code;
        $this->errorMsg[] = $value;

        return false;
    }

}

/**
 * Class to support CFO workflows
 */
class CFOWorkFlows {

    /** @var Core7  */
    var $core;
    /** @var CFOs  */
    var $cfos;
    var $cfoWorkFlows =[];
    /** @var WorkFlows $workFlows **/
    var $workFlows;

    /** @var array $logs to report Workflow results */
    var $logs = [];
    /** @var array $messages included on workflows */
    var $messages = [];

    var $error = false;                 // When error true
    var $errorCode = null;                   // Code of error
    var $errorMsg = [];                 // When error array of messages

    /**
     * DataSQL constructor.
     * @param Core $core
     * @param array $model where [0] is the table name and [1] is the model ['model'=>[],'mapping'=>[], etc..]
     */
    function __construct(Core7 &$core,&$cfos)
    {
        $this->core = $core;
        $this->cfos = $cfos;
    }

    /**
     * Process the event and update the logs
     *
     * @param string $event The event to be processed
     * @param array $data The data to be processed
     * @param mixed $id value of $data array
     * @param string $cfo The CFO used with $data
     *
     * @return bool return true on success and false on error
     */
    function process(string $event, array &$data, $id, string $cfo='') {

        //region INIT $this->logs and VERIFY $cfo value. If Empty try to set $this->cfos->last_cfo
        $this->logs = [];
        $this->messages = [];
        if(!$cfo) $cfo = $this->cfos->last_cfo;
        if(!$cfo) return $this->addError('params-error','Missing a $cfo value because $this->cfos->last_cfo is empty');
        //endregion

        //region SEARCH $model for $cfo
        if(!$model=$this->searchModel($cfo)) {
            return $this->addError('not-found',"[{$cfo}]");
        }
        //endregion

        //region READ $this->cfoWorkFlows[$cfo] from $model['data']['workFlows'] and ds:CloudFrameWorkCFOWorkFlows
        if(!($this->cfoWorkFlows[$cfo]??null)) {

            //read $workFlows from the model
            $workFlows = $model['data']['workFlows']['workflows'] ?? [];
            //if hasExternalWorkFlows read them from ds:CloudFrameWorkCFOWorkFlows
            if ($model['data']['hasExternalWorkFlows'] ?? null) {

                //read $external_workflows from cache in ds:CloudFrameWorkCFOWorkFlows
                $this->cfos->ds('CloudFrameWorkCFOWorkFlows')->activateCache(true);
                $external_workflows = $this->cfos->ds('CloudFrameWorkCFOWorkFlows')->getCache('external_cfo_'.$cfo);
                if ($this->cfos->ds('CloudFrameWorkCFOWorkFlows')->error)
                    return $this->workflows_report('reading-CloudFrameWorkCFOWorkFlows', $this->cfos->ds('CloudFrameWorkCFOWorkFlows')->error);

                //if $external_workflows does not exists in cache
                if($external_workflows===null){
                        $external_workflows = $this->cfos->ds('CloudFrameWorkCFOWorkFlows')->fetchAll('*', ['CFOId' => $cfo]);
                        if ($this->cfos->ds('CloudFrameWorkCFOWorkFlows')->error)
                            return $this->workflows_report('reading-CloudFrameWorkCFOWorkFlows', $this->cfos->ds('CloudFrameWorkCFOWorkFlows')->error);
                        $this->cfos->ds('CloudFrameWorkCFOWorkFlows')->setCache('external_cfo_'.$cfo,$external_workflows);
                }

                if ($external_workflows) foreach ($external_workflows as $workflow) if ($workflow['Active']) {
                    if (is_array($workflow['JSON'] ?? null))
                        foreach ($workflow['JSON'] as $event_type => $events) {
                            if (!isset($workFlows[$event_type])) $workFlows[$event_type] = [];
                            if (is_array($events)) foreach ($events as $title => $event_data) {
                                $workFlows[$event_type][] = array_merge(['title' => $title], $event_data);
                            }
                        }
                }
            }

            //assign the $workFlows to $this->cfoWorkFlows[$cfo]
            $this->cfoWorkFlows[$cfo] = $workFlows;
            unset($workFlows);  // free memory
        }
        //endregion

        //region SEARCH $event in $workFlow and try to execute UPDATING $logs
        if(is_array($this->cfoWorkFlows[$cfo][$event]??null))
            foreach ($this->cfoWorkFlows[$cfo][$event] as $_i=>$workflow) {

                //region IGNORE workflows not active
                if(!($workflow['active'])) continue;
                //endregion

                //region VERIFY $workflow['id'] is set or create it
                if(!($workflow['id']??null)) $workflow['id'] = md5(json_encode($workflow));
                $workflow['id']="{$workflow['id']} [{$workflow['action']},{$_i}]";
                //endregion

                //region EVALUATE conditional AND conitnue y false
                if(($workflow['conditional']??''))  {
                    if(!is_string($workflow['conditional'])) {$this->workflows_report($workflow['id'],'Error [conditional] is not an string in workflow '.$event."[$_i]",'cfo_workflow_error'); }
                    $_eval = false;
                    try {
                        $_eval_expression = '$_eval = ('.str_replace(';','',$this->core->replaceCloudFrameworkTagsAndVariables($workflow['conditional'],$data)).');';
                        eval($_eval_expression);
                    } catch (Throwable $t) {
                        $this->workflows_report($workflow['id'],"Error of expression in attribute [conditional] [{$_eval_expression}] in workflow {$event}[{$_i}]: ".$t->getMessage(),'cfo_workflow_error');
                    }
                    if(!$_eval) {
                        $this->workflows_report($workflow['id'],['result'=>'[conditional] has returned [false] in workflow.'.$workflow['action'].' '.$event."[$_i]"],'cfo_workflow.'.$event);
                        $this->workflows_report($workflow['id'],['condition'=>$_eval_expression],'cfo_workflow.conditional');
                        continue;
                    }
                    else {
                        $this->workflows_report($workflow['id'],['result'=>'[conditional] has returned [true] in workflow.'.$workflow['action'].' '.$event."[$_i]"],'cfo_workflow.'.$event);
                        $this->workflows_report($workflow['id'],['condition'=>$workflow['conditional']],'cfo_workflow.conditional');
                    }
                }
                //endregion

                //region PROCESS $workflow['action'] == ['readRelations','sendEmail','updateCFOData','insertCFOData','setLocalizationLang','hook']
                if ($workflow['action'] == 'readRelations') {
                    $this->readRelations($workflow, $data, $_i);
                }
                elseif ($workflow['action'] == 'insertCFOData') {
                    $this->insertCFOData($workflow, $data, $_i);
                }
                elseif ($workflow['action'] == 'updateCFOData') {
                    $this->updateCFOData($workflow, $data,  $_i);
                }
                elseif ($workflow['action'] == 'sendEmail') {
                    $this->sendEmail($workflow, $data, $id,  $cfo,$_i, $event);
                }
                elseif ($workflow['action'] == 'setLocalizationLang') {
                    $this->setLocalizationLang($workflow, $data, $_i);
                }
                elseif ($workflow['action'] == 'hook') {
                    $this->hook($workflow, $data, $_i, $event);
                }
                else {
                    $this->workflows_report($workflow['id'],"Unknown action [{$workflow['action']}]");
                }
                //endregion

            }
        //endregion

        //region RETURN $logs
        return true;
        //endregion

    }

    /**
     * Process $workflow to insert a record in a CFO
     * @param array $workflow
     * @param array $data
     * @param $_i
     * @param $hook_type
     * @return true|void|null
     */
    private function readRelations(array &$workflow,array &$data, $_i)
    {
        //region CHECK $workflow['action'] and $workflow['active']
        if (!($workflow['active'] ?? false)) return;
        if (($workflow['action'] ?? '') != 'readRelations') return $this->workflows_report($workflow['id'],'readRelations() has receive a wrong [action]');
        //endregion

        //region READ $workflow['relations']
        if(is_array(($workflow['relations'] ?? false))) {

            //region CHECK $this->cfos is correctly configured
            if(!$this->cfos->integrationKey) {
                $this->workflows_report($workflow['id'],'missing cfo integrationKey configuration', 'cfo_workflow_error');
                return false;
            }
            //endregion

            //loop $workflow['relations'] in $relation where $relation['cfo'] exists
            foreach ($workflow['relations'] as $relation) if (($relation['cfo'] ?? null) )
            {

                //region VERIFY $relation['cfo'] model exists
                if (!$models = $this->cfos->readCFOs($relation['cfo'])) {
                    $this->cfos->errorMsg[] = "Error in workflows[{$_i}].relation";
                    $this->workflows_report($workflow['id'],$this->cfos->errorMsg, 'cfo_workflow_error');
                    $workflow['active'] = false;
                    break;
                }
                //endregion

                //region IF $relation has 'key' and 'value' lets read record by key
                if( ($relation['key'] ?? null) && ($relation['value'] ?? null)) {
                    if ($value = $this->core->replaceCloudFrameworkTagsAndVariables($relation['value'], $data))
                    {
                        //check if it is db model
                        if ($models['DataBaseTables']??null) {

                            //region READ $record from ds:$relation['cfo'] evaluating $relation['fields']
                            $record = $this->cfos->db($relation['cfo'])->fetchOne([$relation['key'] => $value],$relation['fields']??'*');
                            if ($this->cfos->db($relation['cfo'])->error) {
                                $this->cfos->db($relation['cfo'])->errorMsg[] = "Error in workflows[{$_i}].relation";
                                $this->workflows_report($workflow['id'], $this->cfos->db($relation['cfo'])->errorMsg, 'cfo_workflow_error');
                                $workflow['active'] = false;
                                if($workflow['error_message']??null) $this->workflows_report($workflow['id'],['message'=>$this->core->replaceCloudFrameworkTagsAndVariables($workflow['error_message'],$data)]);
                            } else {
                                if($workflow['message']??null) $this->workflows_report($workflow['id'],['message'=>$this->core->replaceCloudFrameworkTagsAndVariables($workflow['message'],$data)]);
                            }
                            //endregion

                        }
                        elseif ($models['DataStoreEntities']??null) {

                            //region EVALUATE to apply namespace
                            if($relation['namespace']??null)
                                $this->cfos->ds($relation['cfo'])->namespace = $this->core->replaceCloudFrameworkTagsAndVariables($relation['namespace'],$data)?:$this->cfos->ds($relation['cfo'])->namespace;
                            //endregion

                            //region READ $record from ds:$relation['cfo']. IF ERROR $workflow['active']
                            if(in_array($relation['key'],['KeyId','KeyName']))
                                $record = $this->cfos->ds($relation['cfo'])->fetchOneByKey($value);
                            else
                                $record = $this->cfos->ds($relation['cfo'])->fetchOne('*',[$relation['key'] => $value]);

                            if ($this->cfos->ds($relation['cfo'])->error) {
                                $this->cfos->ds($relation['cfo'])->errorMsg[] = "Error in workflows[{$_i}].relation";
                                $this->workflows_report($workflow['id'], $this->cfos->ds($relation['cfo'])->errorMsg, 'cfo_workflow_error');
                                $workflow['active'] = false;
                                if($workflow['error_message']??null) $this->workflows_report($workflow['id'],['message'=>$this->core->replaceCloudFrameworkTagsAndVariables($workflow['error_message'],$data)]);

                            } else {
                                if($workflow['message']??null) $this->workflows_report($workflow['id'],['message'=>$this->core->replaceCloudFrameworkTagsAndVariables($workflow['message'],$data)]);
                            }
                            //endregion

                            //region REDUCE $record if $relation['fields'] is present
                            if($record && ($relation['fields']??null) && $relation['fields']!='*') {
                                if(!is_array($relation['fields'])) $relation['fields'] = explode(',',$relation['fields']);
                                $relation['fields'][] = 'KeyId';
                                $relation['fields'][] = 'KeyName';
                                foreach ($record as $i=>$item) {
                                    if(!in_array($i,$relation['fields'])) unset($record[$i]);
                                }
                            }
                            //endregion
                        }

                        //region REPORT $record if $workflow['active']
                        if($workflow['active']) {
                            $data[$relation['cfo']] = $record;
                            $_report = [$relation['cfo'] => array_merge(['Keys' => array_keys($record)], ['WHERE' => [$relation['key'] => $value]])];
                            if ($models['DataStoreEntities'] ?? null)
                                $_report[$relation['cfo']]['namespace'] = $this->cfos->ds($relation['cfo'])->namespace;
                            $this->workflows_report($workflow['id'], $_report);
                        }
                        //endregion
                    }
                }
                //endregion
                //region ELSE if $relation has 'count' field execute a count query over $relation['cfo']
                elseif( ($relation['count'] ?? null)) {
                    if(!isset($relation['where'])) $relation['where']=[];
                    if($relation['where']) $relation['where'] = $this->core->replaceCloudFrameworkTagsAndVariables($relation['where'], $data);
                    if ($models['DataBaseTables']??null) {
                        $total = $this->cfos->db($relation['cfo'])->fetchOne($relation['where'],'count(*) total');
                        if ($this->cfos->db($relation['cfo'])->error) {
                            $this->cfos->db($relation['cfo'])->errorMsg[] = "Error in workflows[{$_i}].relation";
                            $this->workflows_report($workflow['id'], ['error'=>$this->cfos->db($relation['cfo'])->errorMsg]);
                            $workflow['active'] = false;
                            if($workflow['error_message']??null) $this->workflows_report($workflow['id'],['message'=>$this->core->replaceCloudFrameworkTagsAndVariables($workflow['error_message'],$data)]);
                        } else {
                            if($workflow['message']??null) $this->workflows_report($workflow['id'],['message'=>$this->core->replaceCloudFrameworkTagsAndVariables($workflow['message'],$data)]);
                            $total = $total['total']??null;
                        }
                    }
                    elseif($models['DataStoreEntities']??null) {

                        //region EVALUATE to apply namespace
                        if($relation['namespace']??null)
                            $this->cfos->ds($relation['cfo'])->namespace = $this->core->replaceCloudFrameworkTagsAndVariables($relation['namespace'],$data)?:$this->cfos->ds($relation['cfo'])->namespace;
                        //endregion

                        //region QUERY into $total the count. IF ERROR SET $workflow['active'] = false;
                        $total = $this->cfos->ds($relation['cfo'])->count($relation['where']);
                        if ($this->cfos->ds($relation['cfo'])->error) {
                            $this->cfos->ds($relation['cfo'])->errorMsg[] = "Error in workflows[{$_i}].relation";
                            $this->workflows_report($workflow['id'], $this->cfos->ds($relation['cfo'])->errorMsg, 'cfo_workflow_error');
                            $workflow['active'] = false;
                            if($workflow['error_message']??null) $this->workflows_report($workflow['id'],['message'=>$this->core->replaceCloudFrameworkTagsAndVariables($workflow['error_message'],$data)]);
                        } else {
                            if($workflow['message']??null) $this->workflows_report($workflow['id'],['message'=>$this->core->replaceCloudFrameworkTagsAndVariables($workflow['message'],$data)]);
                        }
                        //endregion
                    }

                    //region REPORT result if $workflow['active']
                    if($workflow['active']) {
                        $record = [$relation['count'] => $total];
                        $data[$relation['cfo']] = $total;
                        $_report = array_merge(['total' => $total], ['WHERE' => $relation['where']]);
                        if ($models['DataStoreEntities'] ?? null)
                            $_report[$relation['cfo']]['namespace'] = $this->cfos->ds($relation['cfo'])->namespace;
                        $this->workflows_report($workflow['id'], [$relation['cfo'] => $_report]);
                    }
                    //endregion
                }
                //endregion
                //region ELSE error
                else {
                    $this->workflows_report($workflow['id'],"Error in workflows[{$_i}].relation.{$relation['cfo']}. value [{$relation['value']}] is empty.", 'cfo_workflow_error');
                    $workflow['active'] = false;
                    break;
                }
                //endregion
            }
            //if !$workflow['active'] continue to the next workflow
            if (!$workflow['active']) return false;
        }
        //endregion

        return true;
    }

    /**
     * Process $workflow to insert a record in a CFO
     * @param array $workflow
     * @param array $data
     * @param $_i
     * @param $hook_type
     * @return bool|void|null
     */
    private function insertCFOData(array &$workflow,array &$data,$_i)
    {
        //region CHECK $workflow['action'] and $workflow['active']
        if (!($workflow['active'] ?? false)) return;
        if (($workflow['action'] ?? '') != 'insertCFOData') return $this->workflows_report($workflow['id'],'insertCFOData has receive a wrong [action]','workflow_error');
        if (!($workflow['cfo'] ?? false)) return $this->workflows_report($workflow['id'],'insertCFOData has receive an empty [cfo]','workflow_error');
        if (!is_array($workflow['data'] ?? null)) return $this->workflows_report($workflow['id'],'insertCFOData has receive wrong [data]','workflow_error');
        //endregion

        //region FEED $workflow['data']
        foreach ($workflow['data'] as $key=>$datum) if($datum && !is_array($datum)) {
            $workflow['data'][$key] = $this->core->replaceCloudFrameworkTagsAndVariables($datum,$data);
        }
        //endregion

        //region CHECK  $this->cfos->integrationKey
        if(!$this->cfos->integrationKey) {
            $this->workflows_report($workflow['id'],'missing cfo integrationKey configuration', 'cfo_workflow_error');
            return false;
        }
        //endregion

        //region EVALUATE model
        if (!$models = $this->cfos->readCFOs($workflow['cfo'])) {
            $this->cfos->errorMsg[] = "Error in workflows[{$_i}].relation";
            $this->workflows_report($workflow['id'],$this->cfos->errorMsg, 'cfo_workflow_error');
            return false;
        }
        //endregion

        //region INSERT $workflow['data'] in $workflow['cfo']
        if($models['DataBaseTables']??null) {
            $workflow['data']['_key_'] = $this->cfos->db($workflow['cfo'])->insert($workflow['data']);
            if($this->cfos->db($workflow['cfo'])->error) {
                $this->cfos->db($workflow['cfo'])->errorMsg[] = "Error in workflows[{$_i}].insertDataInCFO";
                $this->workflows_report($workflow['id'],['error'=>$this->cfos->db($workflow['cfo'])->errorMsg]);
                return false;
            }
            $this->workflows_report($workflow['id'],[$workflow['cfo']=>$workflow['data']]);
        }
        elseif($models['DataStoreEntities']??null) {
            $entity = $this->cfos->ds($workflow['cfo'])->createEntities($workflow['data'])[0]??null;
            if($this->cfos->ds($workflow['cfo'])->error) {
                $this->workflows_report($workflow['id'],['error'=>$this->cfos->ds($workflow['cfo'])->errorMsg,'namespace'=>$this->cfos->ds($workflow['cfo'])->namespace]);
                return false;
            } else {
                $this->workflows_report($workflow['id'],['report'=>'Created entity in cfo: '.$workflow['cfo']]);
                $this->workflows_report($workflow['id'],['data'=>$entity,'namespace'=>$this->cfos->ds($workflow['cfo'])->namespace]);
            }
        }
        else {
            $this->workflows_report($workflow['id'],$workflow['cfo'].' is not a type db:ds','cfo_workflow_error');
            return false;
        }
        //endregion

        //region EVALUATE to process $workflow['message']
        $this->processWorkFlowMessage($workflow,$data);
        //endregion

        return true;
    }

    /**
     * Process the workflow message and update the relevant variables and reports
     *
     * @param array $workflow The workflow array containing the message information
     * @param array $data The data array containing the variables for replacement
     * @return void
     */
    private function processWorkFlowMessage(array &$workflow, array &$data) {
        //region EVALUATE to process $workflow['message']
        if($workflow['message']??null) {
            if(!is_array($workflow['message'])) $workflow['message'] = ['title'=>$workflow['message']];
            $message = [
                'title' => $this->core->replaceCloudFrameworkTagsAndVariables($workflow['message']['title']??'',$data),
                'type'=>$workflow['message']['type']??'info',
                'description'=>$this->core->replaceCloudFrameworkTagsAndVariables($workflow['message']['description']??null,$data),
                'time'=>$workflow['message']['time']??-1,
                'url'=>$workflow['message']['url']??null,
            ];
            $this->workflows_report($workflow['id'],['message'=>$message]);
            $this->messages[] = $message;
        }
        //endregion
    }

    /**
     * Process $workflow to update a record in a CFO
     * @param array $workflow
     * @param array $data
     * @param $_i
     * @param $hook_type
     * @return bool|void|null
     */
    private function updateCFOData(array &$workflow,array &$data, $_i)
    {
        //region CHECK mandatory fields $workflow['action','value','cfo','key','value','data']
        if (!($workflow['active'] ?? false)) return;
        if (($workflow['action'] ?? '') != 'updateCFOData') return $this->workflows_report($workflow['id'],'processUpdateDataInCFOWorkflow has receive a wrong [action]','workflow_error');
        if (!($workflow['cfo'] ?? false)) return $this->workflows_report($workflow['id'],'processUpdateDataInCFOWorkflow has receive an empty [cfo]','workflow_error');
        if (!($workflow['key'] ?? false)) return $this->workflows_report($workflow['id'],'processUpdateDataInCFOWorkflow has receive an empty [key]','workflow_error');
        if (!($workflow['value'] ?? false)) return $this->workflows_report($workflow['id'],'processUpdateDataInCFOWorkflow has receive an empty [value]','workflow_error');
        if (!is_array($workflow['data'] ?? null)) return $this->workflows_report($workflow['id'],'processUpdateDataInCFOWorkflow misses a right attribute [data]','workflow_error');
        //endregion

        //region APPLY replaceCloudFrameworkTagsAndVariables over $workflow['data']
        foreach ($workflow['data'] as $key=>$datum) if($datum && !is_array($datum)) {
            $workflow['data'][$key] = $this->core->replaceCloudFrameworkTagsAndVariables($datum,$data);
        }
        //endregion

        //region CHECK  $this->cfos->integrationKey
        if(!$this->cfos->integrationKey) {
            $this->workflows_report($workflow['id'],'missing cfo integrationKey configuration', 'cfo_workflow_error');
            return false;
        }
        //endregion

        //region EVALUATE model
        if (!$models = $this->cfos->readCFOs($workflow['cfo'])) {
            $this->cfos->errorMsg[] = "Error in workflows[{$_i}].relation";
            $this->workflows_report($workflow['id'],$this->cfos->errorMsg, 'cfo_workflow_error');
            return false;
        }
        //endregion

        //region SET $value from replaceCloudFrameworkTagsAndVariables($workflow['value'])
        if (!$value = $this->core->replaceCloudFrameworkTagsAndVariables($workflow['value'], $data)) {
            $this->workflows_report($workflow['id'],"Error in workflows[{$_i}].relation.{$workflow['cfo']}. value [{$workflow['value']}] is empty.", 'cfo_workflow_error');
            return false;
        }
        //endregion

        //region UPDATE db if $models['DataBaseTables']
        if($models['DataBaseTables']??null) {
            $record = $this->cfos->db($workflow['cfo'])->fetchOne([$workflow['key']=>$value]);
            if($this->cfos->db($workflow['cfo'])->error) {
                $this->cfos->db($workflow['cfo'])->errorMsg[] = "Error in workflows[{$_i}].updateDataInCFO";
                $this->workflows_report($workflow['id'],['error'=>$this->cfos->db($workflow['cfo'])->errorMsg]);
                return false;
            }

            //check the database record exists
            if(!$record) {
                $this->workflows_report($workflow['id'],['error'=>"Error in workflows[{$_i}].updateDataInCFO.{$workflow['cfo']}. Key [{$workflow['key']}='{$value}'] not found."]);
                return false;
            }
            $workflow['data'][$workflow['key']] = $value;
            $this->cfos->db($workflow['cfo'])->update($workflow['data']);
            if($this->cfos->db($workflow['cfo'])->error) {
                $this->cfos->db($workflow['cfo'])->errorMsg[] = "Error in workflows[{$_i}].updateDataInCFO";
                $this->workflows_report($workflow['id'],['error'=>$this->cfos->db($workflow['cfo'])->errorMsg]);
                return false;
            }
            $this->workflows_report($workflow['id'],[$workflow['cfo']=>$workflow['data']]);

        }
        //endregion
        //region UPDATE ds if $models['DataStoreEntities']
        elseif($models['DataStoreEntities']??null) {
            if (in_array($workflow['key'], ['KeyId', 'KeyName']))
                $entity = $this->cfos->ds($workflow['cfo'])->fetchOneByKey(strval($value));
            else
                $entity = $this->cfos->ds($workflow['cfo'])->fetchOne('*', [$workflow['key'] => $value])[0] ?? null;
            if ($this->cfos->ds($workflow['cfo'])->error) {
                $this->workflows_report($workflow['id'], ['error'=>$this->cfos->ds($workflow['cfo'])->errorMsg]);
                return false;
            }
            //check the datastore entity exists
            if(!$entity) {
                $this->workflows_report($workflow['id'],['error'=>"Error in workflows[{$_i}].updateDataInCFO.{$workflow['cfo']}. Key [{$workflow['key']}='{$value}'] not found."]);
                return false;
            }

            if (isset($workflow['data']['KeyName'])) unset($workflow['data']['KeyName']);
            if (isset($workflow['data']['KeyId'])) unset($workflow['data']['KeyId']);
            if(is_array($workflow['data']))
                $entity = array_merge($entity, $workflow['data']);
            $this->cfos->ds($workflow['cfo'])->createEntities($entity);
            if ($this->cfos->ds($workflow['cfo'])->error) {
                $this->workflows_report($workflow['id'], ['error'=>$this->cfos->ds($workflow['cfo'])->errorMsg]);
                return false;
            }
            $this->workflows_report($workflow['id'], [$workflow['cfo'] => ['key' => $workflow['key'], 'value' => $value, 'data' => $workflow['data'],'namespace'=>$this->cfos->ds($workflow['cfo'])->namespace]]);

        }
        //endregion
        //region ELSE error
        else {
            $this->workflows_report($workflow['id'],['error'=>$workflow['cfo'].' is not a type db:ds']);
            return false;
        }
        //endregion

        //region EVALUATE to process $workflow['message']
        $this->processWorkFlowMessage($workflow,$data);
        //endregion

        return true;
    }

    /**
     * Process $workflow to send emails
     * @param array $workflow
     * @param array $data
     * @param string $id
     * @param string $cfo
     * @param $_i
     * @param $hook_type
     * @return void
     * @throws Mandrill_Error
     */
    private function sendEmail(array &$workflow,array &$data,string &$id,string $cfo,$_i,$hook_type) {

        //region CHECK $workflow['action'] and $workflow['active']
        if(($workflow['action']??'')!='sendEmail') return;
        if(!($workflow['active']??false)) return;
        //endregion

        //region CHECK from $workflow: template, subject, to, conditional
        if(!($workflow['template']??'')) {$this->workflows_report($workflow['id'],'Missing [template] in workflow '.$hook_type."[$_i]",'cfo_workflow_error'); }
        if(!($workflow['subject']??'')) {$this->workflows_report($workflow['id'],'Missing [subject] in workflow '.$hook_type."[$_i]",'cfo_workflow_error'); }
        if(!key_exists('to',$workflow)) {$this->workflows_report($workflow['id'],'Missing [to] in workflow '.$hook_type."[$_i]",'cfo_workflow_error'); }
        if(!($workflow['template']??'') || !($workflow['subject']??'') || !key_exists('to',$workflow) )
            return false;
        //endregion

        //region SETUP $this->workFlows and Mandrill SETUP
        if(!$this->workFlows) {

            //check $this->cfos->integrationKey
            if(!$this->cfos->integrationKey) {
                $this->workflows_report($workflow['id'],['error'=>'missing cfo integrationKey configuration']);
                return;
            }

            // read $this->workFlows
            $this->workFlows = $this->core->loadClass('WorkFlows',$this->cfos);
            $config = $this->cfos->ds('CloudFrameWorkModulesConfigs')->fetchOneByKey('email');
            if ($this->cfos->ds('CloudFrameWorkModulesConfigs')->error) {
                $this->workflows_report($workflow['id'],['error'=>$this->cfos->ds('CloudFrameWorkModulesConfigs')->errorMsg]);
                return;
            }
            $this->workflows_report($workflow['id'],['namespace'=>$this->cfos->ds('CloudFrameWorkModulesConfigs')->namespace]);

            if (!$config) {
                $config = ['KeyName' => 'email', 'DateUpdating' => 'now', 'Title' => 'Email Configuration', 'Description' => 'CLOUD-CHANNELS EMAIL Configuration', 'Config' => ['mandrill_api_key' => null]];
                $this->cfos->ds('CloudFrameWorkModulesConfigs')->createEntities($config);
                if ($this->cfos->ds('CloudFrameWorkModulesConfigs')->error) {
                    $this->workflows_report($workflow['id'],['error'=>$this->cfos->ds('CloudFrameWorkModulesConfigs')->errorMsg]);
                    return;
                }
            }
            if (!($config['Config']['mandrill_api_key'] ?? null)) {
                $this->workflows_report($workflow['id'],['error'=>'Missing [CLOUD-CHANNELS/config/mandrill_api_key] in workflow ' . $hook_type . "[$_i]"]);
                return;
            }
            if ($config['Config']['mandrill_api_key'] ?? null) {
                $this->workFlows->setMandrillApiKey($config['Config']['mandrill_api_key']);
            }
            if(is_array($config['Config']['variables']??null))
                $data = array_merge($config['Config']['variables'],$data);
        }
        //endregion

        //region evaluate if there is $workflow['variables'] to add from low-code
        if(is_array($workflow['variables']??null)) {
            foreach ($workflow['variables'] as $var_key=>$variable) {
                $data[$var_key] = $this->core->replaceCloudFrameworkTagsAndVariables($variable,$data);
            }
        }
        //endregion

        //region SET $params
        $params = [
            'cat'=>"CFO/{$cfo}/$hook_type",
            'slug'=>$workflow['template'],
            'from'=>$this->core->replaceCloudFrameworkTagsAndVariables($workflow['from']??'support@cloudframework.io',$data),
            'to'=>$this->core->replaceCloudFrameworkTagsAndVariables($workflow['to'],$data),
            'subject'=>$this->core->replaceCloudFrameworkTagsAndVariables($workflow['subject'],$data),
            'data'=>$data
        ];
        //endregion

        //region VALIDATE $params['to']
        if($tos = explode(',',$params['to']??'')) {
            foreach ($tos as $_ito => $to) {
                if (!$to) {
                    unset($tos[$_ito]);
                    continue;
                }
                $tos[$_ito] = trim($to);
                if (!$this->core->is->validEmail(trim($to))) {
                    $this->workflows_report($workflow['id'], 'Wrong email [to] in workflow: ' . $to);
                    return;
                }
            }
            $params['to'] = array_values($tos);
        }
        if(!($params['to']??'')) {
            $this->workflows_report($workflow['id'],'Empty substituted email [to:'.$workflow['to'].'] in workflow');
            return;
        }
        //endregion

        //region EVALUATE attachments
        $attachments = [];
        if(is_array($workflow['attachments']??null)) foreach ($workflow['attachments'] as $_local_i=>$attachment) {
            if(!($attachment['source']??null)) {
                $this->workflows_report($workflow['id'],'Has an attachment['.$_local_i.'] definition without source attribute');
                continue;
            }
            if(!$source=$this->core->replaceCloudFrameworkTagsAndVariables($attachment['source'],$data)) {
                $this->workflows_report($workflow['id'],'Has an attachment['.$_local_i.'] with a source replacement empty for ['.$attachment['source'].']');
                continue;
            }
            $name = basename($source);
            $name_parts = explode('.',$name);
            $extension = array_pop($name_parts);
            if(($content = @file_get_contents($source)) === false) {
                $this->workflows_report($workflow['id'],'Error in attachment['.$_local_i.'] reading file  ['.$source.']');
                continue;
            }
            if($attachment['name']??null) {
                if($attachment_name = $this->core->replaceCloudFrameworkTagsAndVariables($attachment['name'],$data)) {
                    $name = "{$attachment_name}.{$extension}";
                }
            }
            $attachments[] = [
                'type'=>'application/'.$extension,
                'name'=> $name,
                'content'=> base64_encode($content),
            ];
            unset($content);

        }
        if($attachments) {
            $params['attachments']=$attachments;
        }
        //endregion

        //region SEND EMAIL
        $linkedObject = $this->core->replaceCloudFrameworkTagsAndVariables($workflow['linkedObject']??$cfo,$data);
        $linkedId = $this->core->replaceCloudFrameworkTagsAndVariables($workflow['linkedId']??$id,$data);
        $result = $this->workFlows->sendPlatformEmail($params,'Mandrill',$linkedObject,$linkedId);
        //endregion

        //region IF ERROR RETURN ERROR INFO
        if(!$result) {
            $this->workflows_report($workflow['id'],$this->workFlows->errorMsg,'cfo_workflow_error');

            if($workflow['error_message']??null) {
                if(is_string($workflow['error_message']))
                    $workflow['error_message'] = ['title'=>$workflow['error_message']];
                if($workflow['error_message']['title']??null) {
                    $this->messages[] = [
                        'title' => $this->core->replaceCloudFrameworkTagsAndVariables($workflow['error_message']['title']??'',$data),
                        'type'=>$workflow['error_message']['type']??'error',
                        'description'=>$this->core->replaceCloudFrameworkTagsAndVariables($workflow['error_message']['description']??null,$data),
                        'time'=>$workflow['error_message']['time']??-1,
                        'url'=>$workflow['error_message']['url']??null,
                    ];
                }
            }
            return;
        }
        //endregion

        //region REPORT $this->workflows_report
        //$this->workflows_report($workflow['id'],"workflow email sent for template [{$workflow['template']}] in {$hook_type}[{$_i}]",'cfo_workflow_ok');
        $this->workflows_report($workflow['id'],['email'=>[
            'from'=>$params['from'],
            'to'=>$params['to'],
            'subject'=>$params['subject'],
            'slug'=>$workflow['template'],
            'result'=>$result['result']??$result
        ]
        ]);
        //endregion

        //region PROCESS $workflow['message']
        $this->processWorkFlowMessage($workflow,$data);
        //endregion

    }

    /**
     * Process $workflow to insert a record in a CFO
     * @param array $workflow
     * @param array $data
     * @param $_i
     * @param $hook_type
     * @return true|void|null
     */
    private function setLocalizationLang(array &$workflow,array &$data, $_i)
    {
        //region CHECK $workflow['action'] and $workflow['active']
        if (!($workflow['active'] ?? false)) return;
        if (($workflow['action'] ?? '') != 'setLocalizationLang') return $this->workflows_report($workflow['id'],'processSetLocalization has receive a wrong [action]','workflow_error');
        if (!($workflow['value'] ?? '')) return $this->workflows_report($workflow['id'],'setLocalizationLang() has receive an empty [value]','workflow_error');
        //endregion

        //region SET $value APPLYING replaceCloudFrameworkTagsAndVariables to $workflow['value']
        $value = $this->core->replaceCloudFrameworkTagsAndVariables($workflow['value'],$data);
        if(!$value) {
            $this->workflows_report($workflow['id'],"Error in workflows[{$_i}].value. value [{$workflow['value']}] is empty.", 'cfo_workflow_error');
            $workflow['active'] = false;
            return false;
        }
        //endregion

        //region SET setDefaultLang to $value
        $this->core->localization->setDefaultLang($value);
        $this->workflows_report($workflow['id'],"Localization lang set to [{$value}]");
        //endregion

        return true;
    }

    /**
     * Process Hook in $workflow
     * @param array $workflow
     * @param array $data
     * @param $_i
     * @param $hook_type
     * @return true|void|null
     */
    private function hook(array &$workflow,array &$data,$_i,$hook_type)
    {

        //region CHECK $workflow['action','active','url','method']
        if (!($workflow['active'] ?? false)) return;
        if (($workflow['action'] ?? '') != 'hook') return $this->workflows_report($workflow['id'],'processHookInCFOWorkflow has receive a wrong [action]','workflow_error');
        if (!($workflow['url'] ?? '')) return $this->workflows_report($workflow['id'],'processHookInCFOWorkflow misses [url]','workflow_error');
        if (!in_array($workflow['method'] ?? '',['GET','POST','PUT','DELETE'])) return $this->workflows_report($workflow['id'],'processHookInCFOWorkflow has received a wrong [method]. Valid values are: GET,POST,PUT,DELETE','workflow_error');
        //endregion

        //region IF $workflow['data'] FEED apply $this->core->replaceCloudFrameworkTagsAndVariables
        if(is_array($workflow['data'] ?? null))
            foreach ($workflow['data'] as $key=>$datum) if($datum && !is_array($datum)) {
                $workflow['data'][$key] = $this->core->replaceCloudFrameworkTagsAndVariables($datum,$data);
            }
        //endregion

        //region INIT $hook,$method,$hook_headers
        $hook = ['url' => $this->core->replaceCloudFrameworkTagsAndVariables($workflow['url'],$data,true)];
        $method = (isset($workflow['method']))?strtoupper($workflow['method']):'POST';
        if(!in_array($method,['GET','POST','POST','PUT','DELETE'])) $method='POST';
        // Default Headers
        $hook_headers = [
            'X-WEB-KEY'=> $this->web_key,
            'X-DS-TOKEN' => $this->dstoken
        ];
        // Search for Special Tags in the headers
        if(isset($hook['headers'])) {
            if(is_array($hook['headers'])) foreach ($hook['headers'] as $i=>$hook_header) {
                $hook['headers'][$i] = $this->core->replaceCloudFrameworkTagsAndVariables($hook_header,$data);
            }
            $hook_headers = $hook['headers'];
        }
        //endregion

        //region EXECUTE hook
        $data_to_send = (is_array($workflow['data'] ?? null))?$workflow['data']:$data;
        // add in data _user id
        $data_to_send['_user'] = $this->dstoken_data['User']['KeyName'];
        // add in url user id
        $hook['url'].= (strpos($hook['url'],'?')?'&':'?').'_user='.urlencode($this->dstoken_data['User']['KeyName'] ?? '').'&_type='.urlencode($hook_type ?? '');
        if($method=='GET') {
            $trigger_ret = $this->core->request->get_json_decode($hook['url'], null,$hook_headers);
        }elseif($method=='POST') {
            $trigger_ret = $this->core->request->post_json_decode($hook['url'], $data_to_send,$hook_headers,true);
        }elseif($method=='PUT') {
            $trigger_ret = $this->core->request->put_json_decode($hook['url'], $data_to_send,$hook_headers,true);
        }elseif($method=='DELETE') {
            $trigger_ret = $this->core->request->delete_json_decode($hook['url'],$hook_headers);
        }

        //hide X-DS-TOKEN for logs
        if(isset($hook_headers['X-DS-TOKEN'])) $hook_headers['X-DS-TOKEN'] = substr($hook_headers['X-DS-TOKEN'],0,60).'*********';
        if($this->core->request->error) {
            $this->workflows_report($workflow['id'],'Error calling ['.$hook['url'].']','workflow_insertDataInCFO');
            $this->workflows_report($workflow['id'],[
                    'url'=>$hook['url'],
                    'data'=>$data_to_send,
                    'error'=>$this->core->request->errorMsg,'cfo_workflow_error']
            );
            return false;

        }

        $this->workflows_report($workflow['id'],'Success calling ['.$hook['url'].']','workflow_insertDataInCFO');
        $this->workflows_report($workflow['id'],[
            'data'=>$data_to_send,
            'return'=>$trigger_ret
        ]);
        //endregion

        return true;
    }

    /**
     * Add a workflow report
     * @param $message
     * @param string $log
     * @return void
     */
    private function workflows_report($key,$message) {
        if(is_array($message)) $this->logs[$key] = array_merge($this->logs[$key]??[],$message);
        else $this->logs[$key][$key][] = $message;
    }

    /**
     * Search for a model object
     *
     * @param string $object The object to search for
     * @return array|null Returns the model object if found, otherwise returns null
     */
    private function searchModel($object)
    {
        if($model = $this->core->model->models['ds:' . $object]??null) return $model;
        elseif($model = $this->core->model->models['db:' . $object]??null) return $model;
        elseif($model = $this->core->model->models['bq:' . $object]??null) return $model;
        else return null;
    }

    /**
     * Add an error in the class
     * @param string $code Code of error
     * @param mixed $value
     * @return bool Always return null to facilitate other return functions
     */
    function addError(string $code,$value)
    {

        $this->error = true;
        $this->errorCode = $code;
        $this->errorMsg[] = $value;

        return false;
    }


}