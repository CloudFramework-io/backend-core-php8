<?php
/**
 * [$cfos = $this->core->loadClass('CFOs');] Class CFOs to handle CFO app for CloudFrameworkInterface
 * https://www.notion.so/cloudframework/CFI-PHP-Class-c26b2a1dd2254ddd9e663f2f8febe038
 * last_update: 202201
 * @package CoreClasses
 */
class CFOs {

    /** @var Core7  */
    var $core;
    var $version = '202301051';
    /** @var string $integrationKey To connect with the ERP */
    var $integrationKey='';
    var $error = false;                 // When error true
    var $errorMsg = [];                 // When error array of messages
    var $namespace = 'default';
    var $project_id = null;
    var $service_account = null;
    var $db_connection = null;
    var $keyId = null;
    var $dsObjects = [];
    var $bqObjects = [];
    var $dbObjects = [];
    /** @var CloudSQL $lastDBObject */
    var $lastDBObject = null;
    var $secrets = [];
    var $avoid_secrets = true;   // SET


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
        //region Create a
    }


    /**
     * @param $cfos
     * @return array|void if there is no error return an array with the model structure
     */
    public function readCFOs ($cfos)
    {
        $models = $this->core->model->readModelsFromCloudFramework($cfos,$this->integrationKey);
        if($this->core->model->error) {
            return $this->addError($this->core->model->errorMsg[0]??$this->core->model->errorMsg);
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
            if (($service_account_secret = ($model['data']['secret'] ?? null))) {
                if (is_string($service_account_secret)) {
                    if (!$service_account = $this->readSecret($service_account_secret)) {
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
            //$this->addError($this->core->model->errorMsg);
            //Return a Foo object instead to avoid exceptions in the execution
            $this->createFooDatastoreObject($object);
            $this->dsObjects[$object]->error = true;
            $this->dsObjects[$object]->errorMsg = $this->core->model->errorMsg;
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
                    if (!$service_account = $this->readSecret($service_account_secret)) {
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
                    if(!$db_credentials = $this->readSecret($service_account_secret)) {
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
        if($this->core->model->error) $this->addError($this->core->model->errorMsg);
        return($ret);
    }

    /**
     * @param string $object
     * @return CloudSQL
     */
    public function dbConnection (string $connection='default'): CloudSQL
    {
        if(!$connection) $connection='default';

        if(!isset($this->core->model->dbConnections[$connection]))
            $this->addError("connection [$connection] has not previously defined");

        $this->core->model->dbInit($connection);
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

        if(!$this->core->model->dbInit($connection)) {
            $this->addError($this->core->model->errorMsg);
            return false;
        }
        else return true;

    }

    /**
     * @param string $secret
     * @return array|void return the secret array of there is not errors
     */
    public function readSecret(string $secret)
    {
        if(isset($this->secrets[$secret])) return $this->secrets[$secret];
        if($this->avoid_secrets) return [];
        if(!strpos($secret,'.')) return $this->addError("secret [{$secret}] has a wrong format");
        list($secret_id, $var_id ) = explode('.',$secret,2);
        if(!$this->secrets[$secret] = $this->core->security->getERPSecretVar($var_id,$secret_id,$this->namespace)) return($this->addError($this->core->security->errorMsg));
        return $this->secrets[$secret];
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
            if ($this->dsObjects[$object]->error) return($this->addError($this->dsObjects[$object]->errorMsg));
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
            if ($this->bqObjects[$object]->error) return($this->addError($this->dsObjects[$object]->errorMsg));
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
            if ($this->dbObjects[$object]->error) return($this->addError($this->dbObjects[$object]->errorMsg));
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
     * @param $value
     */
    function addError($value)
    {
        $this->error = true;
        $this->errorMsg[] = $value;
    }

}