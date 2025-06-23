<?php
/**
 * [$cfos = $this->core->loadClass('CFOs',$integrationKey);] Class CFOs to handle CFO app for CloudFrameworkInterface
 * https://www.notion.so/cloudframework/CFI-PHP-Class-c26b2a1dd2254ddd9e663f2f8febe038
 * last_update: 20240725
 * @package CoreClasses
 */
class CFOs {

    var $version = '20250623_1';
    /** @var Core7  */
    var $core;
    /** @var string $integrationKey To connect with the ERP */
    var $integrationKey='';

    var $error = false;                 // When error true
    var $errorCode = null;                   // Code of error
    var $errorMsg = [];                 // When error array of messages

    /** @var CoreCache $globalCache */
    var $globalCache;                 // When error array of messages

    var $namespace = 'default';
    var $project_id = null;
    var $service_account = null;
    var $last_cfo = '';
    var $cfoObjects = [];

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
    /** @var CFOApi $api */
    var $api = null;


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
        $this->api = new CFOApi($core);
        $this->globalCache = new CoreCache($core,'global_cache');
        //region Create a
    }

    /**
     * Retrieve or create a CFO object of the class CFO\{$cfo} created as code in cfo folder
     *
     * @param string $cfo The identifier or name of the CFO object to retrieve or create.
     * @return mixed Returns the CFO object if successfully retrieved or created, or a failure response if an error occurs.
     */
    function getCFOCodeObject($cfo)
    {

        //if object has been previously created return it
        if (key_exists($cfo, $this->cfoObjects)) return $this->cfoObjects[$cfo];

        //if the class has not been previously loaded try to load it
        if(!class_exists("CFO\\{$cfo}\ClassObject")) {
            if (is_file($this->core->system->app_path . "/cfos/" . $cfo . ".php"))
                include_once($this->core->system->app_path . "/cfos/" . $cfo . ".php");
            else {
                $bucket_path = $this->core->config->get('core.api.extra_path');
                if ($bucket_path)
                    @include_once($bucket_path . "/cfos/" . $cfo . ".php");
            }
        }

        //if the class already does not exist RETURN error
        if(!class_exists("CFO\\{$cfo}\ClassObject"))
            return $this->addError('params-error',"cfos/{$cfo}.php does not exist");

        //set the object
        $this->cfoObjects[$cfo] = new ('CFO\\'.$cfo.'\ClassObject')($this->core, $this);

        //return the object
        return $this->cfoObjects[$cfo];
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
     * @param string $cfoId
     * @param string $namespace
     * @param string $project_id
     * @param array $service_account Optional parameter to
     * @return bool
     */
    public function dsInit (string $cfoId, string $namespace='', string $project_id='', array $service_account=[])
    {

        //region IF (!$service_account and $this->service_account ) SET $service_account = $this->service_account
        if(!$service_account && $this->service_account && is_array($this->service_account)) $service_account = $this->service_account;
        //endregion

        //region IF (!$service_account && !$this->avoid_secrets) READ $model to verify $model['data']['secret'] exist
        if(!$service_account && !$this->avoid_secrets) {
            $model = ($this->core->model->models['ds:' . $cfoId] ?? null);
            if (!$model) {
                if(!$this->readCFOs($cfoId)) {
                    $this->createFooDatastoreObject($cfoId);
                    $this->dsObjects[$cfoId]->error = true;
                    $this->dsObjects[$cfoId]->errorMsg = $this->errorMsg;
                    return false;
                }
                $model = ($this->core->model->models['ds:' . $cfoId] ?? null);
            }
            if (($service_account_secret = ($model['data']['secret'] ?? ($model['data']['interface']['secret']??null)))) {
                if (is_string($service_account_secret)) {
                    if (!$service_account = $this->getCFOSecret($service_account_secret)) {
                        $this->core->logs->add("CFO {$cfoId} has a secret and it does not exist in CFOs->secrets[]. Set the secret value or call CFOs->avoidSecrets(true).", 'CFOs_warning');
                        $this->createFooDatastoreObject($cfoId);
                        $this->dsObjects[$cfoId]->error = true;
                        $this->dsObjects[$cfoId]->errorMsg = 'CFO ['.$cfoId.'] hash a secret ['.$service_account_secret.'] and it does not exist in CFOs->secrets. Programmer has to include [CFOs->setSecret(\''.$service_account_secret.'\', array secret] or to include [CFOs->setServiceAccount(array service_account])';
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
                $this->createFooDatastoreObject($cfoId);
                $this->dsObjects[$cfoId]->error = true;
                $this->dsObjects[$cfoId]->errorMsg = "In CFO[{$cfoId}] there service_account configured does not have private_key or project_id";
                return false;
            }
            $options['keyFile'] = $service_account;
            $options['projectId'] = $service_account['project_id'];
        }
        //endregion

        //region SET $this->dsObjects[$object] = $this->core->model->getModelObject('ds:'.$object,$options);
        $this->dsObjects[$cfoId] = $this->core->model->getModelObject('ds:'.$cfoId,$options);
        if($this->core->model->error) {
            //Return a Foo object instead to avoid exceptions in the execution
            $this->createFooDatastoreObject($cfoId);
            if(is_object($this->dsObjects[$cfoId]??null)) {
                $this->dsObjects[$cfoId]->error = true;
                $this->dsObjects[$cfoId]->errorMsg = $this->core->model->errorMsg;
            } else {
                $this->core->logs->add('Error creating Foo datastore Object when error','error_CFOs_dsInit');
            }
            return false;
        }
        //endregion


        return true;
    }

    /**
     * Fetches the DataStore object for a given CFO ID
     *
     * @param string $cfoId The CFO ID for which to retrieve the DataStore object
     * @return DataStore The DataStore object associated with the provided CFO ID
     */
    public function ds (string $cfoId): DataStore
    {
        if(!isset($this->dsObjects[$cfoId]))
            $this->dsInit($cfoId);

        $this->last_cfo = $cfoId;
        return $this->dsObjects[$cfoId];
    }

    /**
     * Initialize BigQuery integration
     *
     * @param string $cfoId CFO ID for the BigQuery integration
     * @param string $project_id (optional) Project ID for BigQuery integration
     * @param array $service_account (optional) Service account information for authentication
     *
     * @return bool Returns true if initialization is successful, false otherwise
     */
    public function bqInit (string $cfoId, string $project_id='', array $service_account=[])
    {

        //region IF (!$service_account and $this->service_account ) SET $service_account = $this->service_account
        if(!$service_account && $this->service_account && is_array($this->service_account)) $service_account = $this->service_account;
        //endregion

        //region IF (!$service_account && !$this->avoid_secrets) READ $model to verify $model['data']['secret'] exist
        if(!$service_account && !$this->avoid_secrets) {
            $model = ($this->core->model->models['bq:' . $cfoId] ?? null);
            if (!$model) {
                if(!$this->readCFOs($cfoId)) {
                    $this->createFooBQObject($cfoId);
                    $this->bqObjects[$cfoId]->error = true;
                    $this->bqObjects[$cfoId]->errorMsg = $this->errorMsg;
                    return false;
                }
                $model = ($this->core->model->models['bq:' . $cfoId] ?? null);
            }

            if (($service_account_secret = ($model['data']['secret'] ?? null))) {
                if (is_string($service_account_secret)) {
                    if (!$service_account = $this->getCFOSecret($service_account_secret)) {
                        $this->core->logs->add("CFO {$cfoId} has a secret and it does not exist in CFOs->secrets[]. Set the secret value or call CFOs->avoidSecrets(true).", 'CFOs_warning');
                        $this->createFooBQObject($cfoId);
                        $this->bqObjects[$cfoId]->error = true;
                        $this->bqObjects[$cfoId]->errorMsg = 'CFO ['.$cfoId.'] hash a secret ['.$service_account_secret.'] and it does not exist in CFOs->secrets. Programmer has to include [CFOs->setSecret(\''.$service_account_secret.'\', array secret] or to include [CFOs->setServiceAccount(array service_account])';
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
                $this->createFooBQObject($cfoId);
                $this->bqObjects[$cfoId]->error = true;
                $this->bqObjects[$cfoId]->errorMsg = "In CFO[{$cfoId}] there service_account configured does not have private_key or project_id";
                return false;
            }
            $options['keyFile'] = $service_account;
            $options['projectId'] = $service_account['project_id'];
        }
        //endregion

        //region SET $this->bqObjects[$object] = $this->core->model->getModelObject('bq:'.$object,$options);
        $this->bqObjects[$cfoId] = $this->core->model->getModelObject('bq:'.$cfoId,$options);
        if($this->core->model->error) {
            if(!is_object($this->bqObjects[$cfoId]))
                $this->createFooBQObject($cfoId);
            $this->bqObjects[$cfoId]->error = true;
            $this->bqObjects[$cfoId]->errorMsg = $this->core->model->errorMsg;
        }
        //endregion

        return true;
    }

    /**
     * Return a bq $object
     * @param $cfoId
     * @return DataBQ
     */
    public function bq (string $cfoId): DataBQ
    {
        if(!isset($this->bqObjects[$cfoId]))
            $this->bqInit($cfoId);

        $this->last_cfo = $cfoId;
        return $this->bqObjects[$cfoId];
    }

    /**
     * Initialize a bq $object
     * @param string $cfoId
     * @param string $connection name of the connection
     * @param array $db_credentials optional credentials for the connection
     * @return bool
     */
    public function dbInit (string $cfoId, $connection='default', array $db_credentials = [])
    {

        //region EVALUATE IF $this->dbObjects[$object]) exist to create it
        if(!isset($this->dbObjects[$cfoId])) {

            //region READ $model
            $model = ($this->core->model->models['db:' . $cfoId] ?? null);
            if (!$model) {
                if(!$this->readCFOs($cfoId)) {
                    $this->createFooDBObject($cfoId);
                    $this->dbObjects[$cfoId]->error = true;
                    $this->dbObjects[$cfoId]->errorMsg = $this->errorMsg;
                    return false;
                }
                $model = ($this->core->model->models['db:' . $cfoId] ?? null);
            }
            //endregion

            //region EVALUATE IF $model['data']['secret'] exist to update $db_connection
            if (!$db_credentials && ($service_account_secret = ($model['data']['secret'] ?? null)) && !$this->avoid_secrets) {
                if (is_string($service_account_secret)) {
                    if(!$db_credentials = $this->getCFOSecret($service_account_secret)) {
                        $this->core->logs->add("CFO {$cfoId} has a secret and it does not exist in CFOs->secrets[]. Use the CFOs->setSecret()  or set CFOs->useCFOSecret(true).", 'CFOs_warning');
                        $this->createFooDBObject($cfoId);
                        $this->dbObjects[$cfoId]->error = true;
                        $this->dbObjects[$cfoId]->errorMsg = ['CFO ['.$cfoId.'] has a secret ['.$service_account_secret.'] and it does not exist in CFOs->secrets. Programmer has to include [CFOs->setSecret(\''.$service_account_secret.'\', array secret] or add it in CLOUD-DEVELOPMENT/Secrets'];
                        if($this->error) $this->dbObjects[$cfoId]->errorMsg[] = $this->errorMsg;
                        return false;
                    }

                } else {
                    $db_credentials = $service_account_secret;
                }
            }
            //endregion

            //region INIT $this->dbObjects[$object]
            $this->dbObjects[$cfoId] = $this->core->model->getModelObject('db:'.$cfoId,['cf_models_api_key'=>$this->integrationKey]);
            if($this->core->model->error) {
                if(!is_object($this->dbObjects[$cfoId]))
                    $this->createFooDBObject($cfoId);
                $this->dbObjects[$cfoId]->error = true;
                $this->dbObjects[$cfoId]->errorMsg = $this->core->model->errorMsg;
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
     * Initialize or retrieve a database connection object for a given CFO ID
     * @param string $cfoId The ID of the CFO
     * @param string $connection The name of the connection to use (default: 'default')
     * @return DataSQL Returns the DataSQL object associated with the CFO ID
     */
    public function db (string $cfoId, $connection='default'): DataSQL
    {
        if(!isset($this->dbObjects[$cfoId]))
            $this->dbInit($cfoId,$connection);

        $this->last_cfo = $cfoId;
        return $this->dbObjects[$cfoId];
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
     * Execute a Direct query inside a $connection
     * @param $q
     * @param null $params
     * @param string $connection
     * @return array|false
     */
    public function bqQuery ($q,$params=null)
    {
        //region INIT $this->bqObjects['internal'] IF it does not exist
        if(!isset($this->bqObjects['no-dataset'])) {
            $options = [];
            if($this->service_account)
                $options['keyFile'] = $this->service_account;
            $this->createFooBQObject('no-dataset',$options);
            if($this->error) return false;
        }
        //endregion

        /** @var DataBQ $bq */
        $bq = &$this->bqObjects['no-dataset'];
        $result = $bq->dbQuery('Custom Query',$q,$params);
        if($bq->error) return $this->addError('bq-error',$bq->errorMsg);
        return($result);
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
    public function createFooBQObject($object,array $options=[]) {
        if(!isset($this->bqObjects[$object])) {
            $model = json_decode('{
                                    "KeyName": ["string","index|minlength:4"]
                                  }',true);
            $this->bqObjects[$object] = $this->core->loadClass('DataBQ',[$object,$model,$options]);
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
     * Toggle the usage of CFO secret
     * @param bool $use Whether to enable the use of CFO secret (default: true)
     * @return void
     */
    function useCFOSecret(bool $use=true) {
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
     * Reset the cache for the specified cache key or clear the entire cache if no key is provided.
     *
     * @param string $cfo Cache key to reset the cache for. (Optional, default: '')
     *
     * @return void
     */
    function resetCache(string $cfo='') {
        $this->core->model->resetCache($cfo='');
    }

    /**
     * Reset error status and clear error details in the class
     *
     * @return void No return value as it resets the error state without returning any specific value
     */
    function resetError() {
        $this->error = true;
        $this->errorCode = null;
        $this->errorMsg[] = [];
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
 * Represents a class responsible for managing CFO workflows, ensuring appropriate
 * execution of defined workflows linked with CFOs during specific events.
 */
class CFOWorkFlows {

    /** @var Core7  */
    var $core;
    /** @var CFOs  */
    var $cfos;
    var $version = '202408081';
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
     * @param string $cfo The CFO used with $data. If empty it takes $this->cfos->last_cfo
     *
     * @return bool return true on success and false on error
     */
    public function process(string $event, array &$data, $id, string $cfo='') {

        //region INIT $_time, $this->logs and VERIFY $cfo value. If Empty try to set $this->cfos->last_cfo
        $_time = microtime(true);
        $this->logs = [];
        $this->messages = [];
        if(!$cfo) $cfo = $this->cfos->last_cfo;
        if(!$cfo) return $this->addError('params-error','Missing a $cfo value because $this->cfos->last_cfo is empty');
        //endregion

        //region SEARCH $model for $cfo
        if(!$model=$this->searchModel($cfo)) {
            return $this->addError('not-found',"NOT FOUND [{$cfo}]");
        }
        //endregion

        //region READ $this->cfoWorkFlows[$cfo] from $model['data']['workFlows'] and ds:CloudFrameWorkCFOWorkFlows
        if(!($this->cfoWorkFlows[$cfo]??null)) {

            //region SET $workFlows from $model['data']['workFlows']['workflows']
            $workFlows = $model['data']['workFlows']['workflows'] ?? [];
            //endregion

            //region EVALUATE TO READ EXTERNAL WORKFLOWS

            //if $workFlows has @CFO_Id syntax then  read them from ds:CloudFrameWorkCFOWorkFlows where CFO Id = @CFO_Id
            $external_workflow_id = null;
            if(is_string($workFlows) && strpos($workFlows,'@')===0) {
                $external_workflow_id = substr($workFlows,1);
                $workFlows=[];
                if(!$this->mergeExternalWorkFlows($workFlows,$external_workflow_id)) return false;
            }

            //if hasExternalWorkFlows read them from ds:CloudFrameWorkCFOWorkFlows where CFO Id = $cfo
            if ($model['data']['hasExternalWorkFlows'] ?? null && $cfo!=$external_workflow_id) {
                if(!$this->mergeExternalWorkFlows($workFlows,$cfo)) return false;
            }
            //endregion

            //region UPDATE $this->cfoWorkFlows[$cfo] = $workFlows
            $this->cfoWorkFlows[$cfo] = $workFlows;
            unset($workFlows);  // free memory
            //endregion

        }
        //endregion

        //region EVALUATE to MERGE in $this->cfoWorkFlows[$cfo][$event] <= $this->cfoWorkFlows[$cfo]['common']
        if(is_array($this->cfoWorkFlows[$cfo]['common']??null)) {
            if(!is_array($this->cfoWorkFlows[$cfo][$event]??null)) $this->cfoWorkFlows[$cfo][$event] = [];
            $_common = [];
            foreach ($this->cfoWorkFlows[$cfo]['common'] as $_i=>$workflow) {
                $_index_common='common: '.$_i;
                if(isset($this->cfoWorkFlows[$cfo][$event][$_index_common])) $_index_common.=' '.md5(json_encode($workflow));
                $_common[$_index_common] = &$this->cfoWorkFlows[$cfo]['common'][$_i];
            }
            $this->cfoWorkFlows[$cfo][$event] = array_merge($_common,$this->cfoWorkFlows[$cfo][$event]);
        }
        //endregion

        //region LOOP $this->cfoWorkFlows[$cfo][$event] to EXECUTE WORKFLOWS
        if(is_array($this->cfoWorkFlows[$cfo][$event]??null))
            foreach ($this->cfoWorkFlows[$cfo][$event] as $_i=>$workflow)  {

                if(!is_array($workflow) || !isset($workflow['action'])) {
                    $this->workflows_report("[{$cfo}.{$event}.{$_i}]",'Is not an array with [action] parameter to be evaluated as workflow');
                    break;
                }

                //region EVALUATE $workflow['conditional'] and $workflow['active'] and SET $workflow['id'] if it does not exist
                if(!$this->evaluateCondition($workflow,$data,$event,$_i)) continue;
                //endregion

                //region PROCESS $workflow['action'] == ['readRelations','sendEmail','updateCFOData','insertCFOData','setLocalizationLang','hook']
                if (($workflow['action']??null) == 'workflows' && is_array($workflow['workflows']??null)) {
                    $this->workflows_report($workflow['id'],['workflows'=>[]]);
                    foreach ($workflow['workflows'] as $_j=>$sub_workflow) {
                        if($this->evaluateCondition($sub_workflow,$data,$event,$_j))
                        {
                            $this->evaluateWorkflow($sub_workflow,$data,$id,$event,$_i,$cfo);
                        }

                        if(isset($this->logs[$sub_workflow['id']])) {
                            $this->logs[$workflow['id']]['workflows'][$sub_workflow['id']] = $this->logs[$sub_workflow['id']];
                            unset($this->logs[$sub_workflow['id']]);

                        }
                    }
                }
                else {
                    $this->evaluateWorkflow($workflow,$data,$id,$event,$_i,$cfo);
                }
                //endregion

            }
        //endregion

        //region SET $this->logs['time'] with microtime(true)-$_time
        $this->logs['time'] = round(microtime(true)-$_time,4);
        //endregion

        //region RETURN true
        return true;
        //endregion

    }

    /**
     * Merge external workflows into the given array of workflows.
     *
     * @param array $workFlows The array of workflows to merge into (passed by reference).
     * @param string $external_workflow_id The ID of the external workflow.
     *
     * @return bool Returns true on success, false on error.
     */
    private function mergeExternalWorkFlows(array &$workFlows, string $external_workflow_id)
    {


        //region READ $external_workflows from ds:CloudFrameWorkCFOWorkFlows a CACHE IT
        $external_workflows = $this->cfos->ds('CloudFrameWorkCFOWorkFlows')->fetchAll('*', ['CFOId' => $external_workflow_id,'Active'=>true]);
        if ($this->cfos->ds('CloudFrameWorkCFOWorkFlows')->error) {
            $this->workflows_report('reading-CloudFrameWorkCFOWorkFlows', $this->cfos->ds('CloudFrameWorkCFOWorkFlows')->error);
            return false;
        }
        //endregion

        //region MERGE in $workFlows <=$external_workflows
        if ($external_workflows) foreach ($external_workflows as $item) if ($item['Active']) {
            if (is_array($item['JSON'] ?? null))
                foreach ($item['JSON'] as $event_type => $events) {
                    if (!isset($workFlows[$event_type])) $workFlows[$event_type] = [];
                    if (is_array($events)) foreach ($events as $title => $event_data) {
                        $workFlows[$event_type][] = array_merge(['title' => $title], $event_data);
                    }
                }
        }
        //endregion
        return true;
    }
    /**
     * Evaluate the condition of a given workflow
     *
     * @param array $workflow The workflow to evaluate
     * @param array $data The data used in the evaluation
     * @param string $event The event name
     * @param int $_i The index of the workflow
     * @return bool The result of the condition evaluation. Returns true if the condition is met, false otherwise.
     */
    private function evaluateCondition(&$workflow, &$data, $event, $_i)
    {

        //region VERIFY $workflow['id'] is set or create it
        if(!($workflow['id']??null)) $workflow['id'] = md5(json_encode($workflow));
        $workflow['id']="{$workflow['id']} [{$workflow['action']},{$_i}]";
        //endregion

        //region IGNORE workflows not active
        if(!($workflow['active']??null)) return false;
        //endregion

        if(!($workflow['conditional']??'')) return true;

        if(!is_string($workflow['conditional'])) {
            $this->workflows_report($workflow['id'],'Error [conditional] is not an string in workflow '.$event."[$_i]");
            return false;
        }

        $_eval = false;
        try {
            $_eval_expression = '$_eval = ('.str_replace(';','',$this->core->replaceCloudFrameworkTagsAndVariables($workflow['conditional'],$data)).');';
            eval($_eval_expression);
        } catch (Throwable $t) {
            $this->workflows_report($workflow['id'],"Error of expression in attribute [conditional] [{$_eval_expression}] in workflow {$event}[{$_i}]: ".$t->getMessage());
        }
        if(!$_eval) {
            $this->workflows_report($workflow['id'],['result'=>'[conditional] has returned [false] in workflow.'.$workflow['action'].' '.$event."[$_i]"]);
            $this->workflows_report($workflow['id'],['conditional'=>$workflow['conditional'],'condition_evaluation'=>$_eval_expression]);
            $this->workflows_report($workflow['id'],['conditional_values'=>$_eval_expression]);
        }
        else {
            $this->workflows_report($workflow['id'],['result'=>'[conditional] has returned [true] in workflow.'.$workflow['action'].' '.$event."[$_i]"]);
            $this->workflows_report($workflow['id'],['conditional'=>$workflow['conditional']]);
            $this->workflows_report($workflow['id'],['conditional_values'=>$_eval_expression]);
        }

        return $_eval;
    }

    /**
     * Evaluate the workflow action and perform the corresponding operations
     *
     * @param array $workflow The workflow data
     * @param mixed $data The data to be processed
     * @param int $id The ID of the workflow
     * @param string $event The event trigger
     * @param int $_i Index parameter
     * @param mixed $cfo CFO parameter
     * @return void
     */
    private function evaluateWorkflow(&$workflow, &$data, $id, $event, $_i, $cfo)
    {
        if ($workflow['action'] == 'setVariables') {
            $this->setVariables($workflow, $data, $_i);
        }
        elseif ($workflow['action'] == 'readRelations') {
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
    }


    /**
     * Process $workflow to set variables in the data model
     * @param array $workflow
     * @param array $data
     * @param $_i
     * @param $hook_type
     * @return true|void|null
     */
    private function setVariables(array &$workflow, array &$data, $_i)
    {

        //region CHECK $workflow['action'] and $workflow['active']
        if (!($workflow['active'] ?? false)) return;
        if (($workflow['action'] ?? '') != 'setVariables') return $this->workflows_report($workflow['id'],'setVaraibles() has receive a wrong [action]');
        //endregion

        //region
        //region READ $workflow['variables']
        if(is_array(($workflow['variables'] ?? false))) {
            //loop $workflow['relations'] in $relation where $relation['cfo'] exists
            foreach ($workflow['variables'] as $var => $content) {
                $workflow['variables'][$var] = $data[$var] = $content ? $this->core->replaceCloudFrameworkTagsAndVariables($content, $data) : $content;

            }
        }
        $this->workflows_report($workflow['id'], ['variables' => $workflow['variables']]);
        //endregion

        //region EVALUATE to process $workflow['message']
        $this->processWorkFlowMessage($workflow,$data);
        //endregion

        return true;
    }


    /**
     * Process $workflow to insert a record in a CFO
     * @param array $workflow
     * @param array $data
     * @param $_i
     * @param $hook_type
     * @return bool
     */
    private function readRelations(array &$workflow,array &$data, $_i)
    {

        //region CHECK $workflow['action'] and $workflow['active']
        if (!($workflow['active'] ?? false)) return;
        if (($workflow['action'] ?? '') != 'readRelations') return $this->workflows_report($workflow['id'],'readRelations() has receive a wrong [action]');
        //endregion

        //region READ $workflow['relations']
        if(is_array(($workflow['relations'] ?? false))) {

            //region CHECK $this->cfos->integrationKey exists
            if(!$this->cfos->integrationKey) {
                $this->workflows_report($workflow['id'],'missing cfo integrationKey configuration', 'cfo_workflow_error');
                return false;
            }
            //endregion

            //region LOOP $workflow['relations'] in $relation where $relation['cfo'] exists
            foreach ($workflow['relations'] as $relation) if (($relation['cfo'] ?? null) )
            {

                //region INIT $_time
                $_time = microtime(true);
                //endregion

                //region VERIFY $relation['cfo'] model exists
                if (!$models = $this->cfos->readCFOs($relation['cfo'])) {
                    $this->cfos->errorMsg[] = "Error in workflows[{$_i}].relation";
                    $this->workflows_report($workflow['id'],$this->cfos->errorMsg);
                    $workflow['active'] = false;
                    break;
                }
                //endregion

                //region SET $_output_variable
                $_output_variable = ($relation['output_variable']??null)?:$relation['cfo'];
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
                        } else {
                            $this->workflows_report($workflow['id'], '$model is not DataBaseTables nor DataStoreEntities');
                            $workflow['active'] = false;
                        }

                        //region REPORT $record if $workflow['active']
                        if($workflow['active']) {
                            $data[$_output_variable] = $record;
                            $_report = [$_output_variable => array_merge(['CFO'=>$relation['cfo'],'Keys' => array_keys($record)], ['WHERE' => [$relation['key'] => $value]])];
                            if ($models['DataStoreEntities'] ?? null)
                                $_report[$_output_variable]['namespace'] = $this->cfos->ds($relation['cfo'])->namespace;
                            $_report[$_output_variable]['time'] = round(microtime(true)-$_time,4);
                            $this->workflows_report($workflow['id'], $_report);
                            if(!$record && ($relation['not_found_message']??null)) {
                                $this->workflows_report($workflow['id'], ['message' => $this->core->replaceCloudFrameworkTagsAndVariables($relation['not_found_message'], $data)]);
                            }
                        }
                        //endregion
                    }
                }
                //endregion

                //region ELSE if $relation has 'group_field' field execute a count query over $relation['cfo']
                elseif($relation['group_field'] ?? null){
                    if($relation['where']) $relation['where'] = $this->core->replaceCloudFrameworkTagsAndVariables($relation['where'], $data);
                    //database
                    if ($models['DataBaseTables']??null) {
                        $this->cfos->db($relation['cfo'])->setGroupBy($relation['group_field']);
                        $records = $this->cfos->db($relation['cfo'])->fetch($relation['where'],$relation['group_field']);
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
                    //datastore
                    elseif ($models['DataStoreEntities']??null) {
                        //region EVALUATE to apply namespace
                        if($relation['namespace']??null) {
                            $this->cfos->ds($relation['cfo'])->namespace = $this->core->replaceCloudFrameworkTagsAndVariables($relation['namespace'], $data) ?: $this->cfos->ds($relation['cfo'])->namespace;
                        }
                        //endregion

                        //region QUERY into $total the count. IF ERROR SET $workflow['active'] = false;
                        $records = $this->cfos->ds($relation['cfo'])->fetchAll('*',$relation['where'],$relation['order']??null);
                        if ($this->cfos->ds($relation['cfo'])->error) {
                            $this->cfos->ds($relation['cfo'])->errorMsg[] = "Error in workflows[{$_i}].relation";
                            $this->workflows_report($workflow['id'], $this->cfos->ds($relation['cfo'])->errorMsg);
                            $workflow['active'] = false;
                            if($workflow['error_message']??null) $this->workflows_report($workflow['id'],['message'=>$this->core->replaceCloudFrameworkTagsAndVariables($workflow['error_message'],$data)]);
                        } else {
                            if($workflow['message']??null) $this->workflows_report($workflow['id'],['message'=>$this->core->replaceCloudFrameworkTagsAndVariables($workflow['message'],$data)]);
                        }
                        //endregion
                    } else {
                        $this->workflows_report($workflow['id'], '$model is not DataBaseTables nor DataStoreEntities');
                        $workflow['active'] = false;
                    }

                    //region REPORT IF $workflow['active'] report the result
                    if($workflow['active']) {
                        $data[$_output_variable] = [];
                        if(is_array($records)) foreach ($records as $record) if(isset($record[$relation['group_field']])){
                            if(!in_array($record[$relation['group_field']],$data[$_output_variable])) $data[$_output_variable][] =$record[$relation['group_field']];
                        }
                        $total_results = 0;
                        if($data[$_output_variable]) {
                            $total_results = count($data[$_output_variable]);
                            $data[$_output_variable] = implode(',', $data[$_output_variable]);
                        }
                        $_report = [$_output_variable =>['CFO'=>$relation['cfo'],'group_field'=>$relation['group_field'],'value' => "{$total_results} results separated by [,]",'WHERE' => $relation['where']]];
                        if ($models['DataStoreEntities'] ?? null)
                            $_report[$_output_variable]['namespace'] = $this->cfos->ds($relation['cfo'])->namespace;

                        $_report[$_output_variable]['time'] = round(microtime(true)-$_time,4);
                        $this->workflows_report($workflow['id'], $_report);
                    }
                    //endregion
                }
                //endregion

                //region ELSE if $relation has 'count' field execute a count query over $relation['cfo']
                elseif( ($relation['count'] ?? null)) {
                    if(!isset($relation['where'])) $relation['where']=[];
                    if($relation['where']) $relation['where'] = $this->core->replaceCloudFrameworkTagsAndVariables($relation['where'], $data);

                    //database
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
                    //datastore
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
                    //else error
                    else {
                        $this->workflows_report($workflow['id'], '$model is not DataBaseTables nor DataStoreEntities');
                        $workflow['active'] = false;
                    }

                    //region REPORT result if $workflow['active']
                    if($workflow['active']) {
                        $record = [$relation['count'] => $total];
                        $data[$_output_variable] = $total;
                        $_report = [$_output_variable =>array_merge(['CFO'=>$relation['cfo'],'total' => $total], ['WHERE' => $relation['where']])];
                        if ($models['DataStoreEntities'] ?? null)
                            $_report[$_output_variable]['namespace'] = $this->cfos->ds($relation['cfo'])->namespace;
                        $_report[$_output_variable]['time'] = round(microtime(true)-$_time,4);
                        $this->workflows_report($workflow['id'], $_report);
                    }
                    //endregion
                }
                //endregion

                //region ELSE error and SET $workflow['active'] = false;
                else {
                    $this->workflows_report($workflow['id'],"Error in workflows[{$_i}].relation.{$relation['cfo']}. [key/value] = [{$relation['key']}/{$relation['value']}] is empty in some attribute");
                    $workflow['active'] = false;
                    break;
                }
                //endregion
            }
            //endregion

            //region RETURN false if !$workflow['active'] because any error has been produced
            if (!$workflow['active']) return false;
            //endregion
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
        $workflow['data'] = $this->core->replaceCloudFrameworkTagsAndVariables($workflow['data'],$data);

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

        //region SET $_output_variable
        $_output_variable = ($workflow['output_variable']??null)?:$workflow['cfo'];
        $_time = microtime(true);
        //endregion

        //region INSERT $workflow['data'] in $workflow['cfo']
        if($models['DataBaseTables']??null) {
            $workflow['data']['_key_'] = $this->cfos->db($workflow['cfo'])->insert($workflow['data']);
            if($this->cfos->db($workflow['cfo'])->error) {
                $this->cfos->db($workflow['cfo'])->errorMsg[] = "Error in workflows[{$_i}].insertDataInCFO";
                $this->workflows_report($workflow['id'],['error'=>$this->cfos->db($workflow['cfo'])->errorMsg]);
                return false;
            }
            $entity = &$workflow['data'];
            $this->workflows_report($workflow['id'],[$_output_variable=>['CFO'=>$workflow['cfo'],'data'=>$entity,'time'=>round(microtime(true)-$_time,4)]]);
        }
        elseif($models['DataStoreEntities']??null) {
            if($workflow['namespace']??null) $this->cfos->ds($workflow['cfo'])->namespace=$workflow['namespace'];
            $entity = $this->cfos->ds($workflow['cfo'])->createEntities($workflow['data'])[0]??null;
            if($this->cfos->ds($workflow['cfo'])->error) {
                $this->workflows_report($workflow['id'],['error'=>$this->cfos->ds($workflow['cfo'])->errorMsg,'namespace'=>$this->cfos->ds($workflow['cfo'])->namespace,'time'=>round(microtime(true)-$_time,4)]);
                return false;
            } else {
                $this->workflows_report($workflow['id'],[$_output_variable=>['CFO'=>$workflow['cfo'],'data'=>$entity,'namespace'=>$this->cfos->ds($workflow['cfo'])->namespace,'time'=>round(microtime(true)-$_time,4)]]);
            }
        }
        else {
            $this->workflows_report($workflow['id'],$workflow['cfo'].' is not a type db:ds');
            return false;
        }
        //endregion

        $data[$_output_variable] = $entity;

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

        //region SET $_output_variable
        $_output_variable = ($workflow['output_variable']??null)?:$workflow['cfo'];
        $_time = microtime(true);
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
            $this->workflows_report($workflow['id'],[$_output_variable=>['CFO'=>$workflow['cfo'],'data'=>$workflow['data']]]);

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
            $this->workflows_report($workflow['id'],[$_output_variable=>['CFO'=>$workflow['cfo'],'key' => $workflow['key'], 'value' => $value,'data'=>$entity,'namespace'=>$this->cfos->ds($workflow['cfo'])->namespace,'time'=>round(microtime(true)-$_time,4)]]);

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
        if(!($workflow['from']??'')) {$this->workflows_report($workflow['id'],'Missing [from] in workflow '.$hook_type."[$_i]",'cfo_workflow_error'); }
        if(!($workflow['to']??'')) {$this->workflows_report($workflow['id'],'Missing [to] in workflow '.$hook_type."[$_i]",'cfo_workflow_error'); }
        if(!($workflow['subject']??'')) {$this->workflows_report($workflow['id'],'Missing [subject] in workflow '.$hook_type."[$_i]",'cfo_workflow_error'); }
        if(!($workflow['template']??'')) {$this->workflows_report($workflow['id'],'Missing [template] in workflow '.$hook_type."[$_i]",'cfo_workflow_error'); }
        if(!($workflow['from']??'') || !($workflow['to']??'') || !($workflow['template']??'') || !($workflow['subject']??'') )
            return false;
        //endregion

        //region INIT $_time
        $_time = microtime(true);
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

        //region SET $params to be used in the Email
        $params = [
            'cat'=>"CFO/{$cfo}/$hook_type",
            'slug'=>$workflow['template'],
            'to'=>$this->core->replaceCloudFrameworkTagsAndVariables($workflow['to'],$data),
            'subject'=>$this->core->replaceCloudFrameworkTagsAndVariables($workflow['subject'],$data),
            'data'=>$data
        ];
        //If the workflow does not have from, then the template has to have any default from defined
        if($workflow['from']??null)
            $params['from'] = $this->core->replaceCloudFrameworkTagsAndVariables($workflow['from'],$data);
        //If the workflow does not have from, then the template has to have any default from defined
        if($workflow['cc']??null)
            $params['cc'] = $this->core->replaceCloudFrameworkTagsAndVariables($workflow['cc'],$data);

        //If the workflow does not have from, then the template has to have any default from defined
        if($workflow['bcc']??null)
            $params['bcc'] = $this->core->replaceCloudFrameworkTagsAndVariables($workflow['bcc'],$data);

        //evaluate to send the email asynchronously
        if($workflow['async']??null)
            $params['async'] = true;
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

        //region VALIDATE $params['cc']
        if(($params['cc']??null)) {
            $ccs = (is_array($params['cc']))?$params['cc']:explode(',',$params['cc']);
            foreach ($ccs as $_icc => $cc) {
                if (!$cc) {
                    unset($ccs[$_icc]);
                    continue;
                }
                $ccs[$_icc] = trim($cc);
                if (!$this->core->is->validEmail(trim($cc))) {
                    $this->workflows_report($workflow['id'], 'Wrong email [cc] in workflow: ' . $cc);
                    return;
                }
                //do not repeat in cc: emails of to:
                if(in_array($ccs[$_icc],$params['to'])) unset($ccs[$_icc]);
            }
            $params['cc'] = array_values($ccs);
            if($params['cc'])
                $params['preserve_recipients'] = true;
        }
        //endregion

        //region VALIDATE $params['bcc']
        if(($params['bcc'] ?? null)) {
            $bccs = (is_array($params['bcc'])) ? $params['bcc'] : explode(',', $params['bcc']);
            foreach ($bccs as $_ibcc => $bcc) {
                if (!$bcc) {
                    unset($bccs[$_ibcc]);
                    continue;
                }
                $bccs[$_ibcc] = trim($bcc);
                if (!$this->core->is->validEmail(trim($bcc))) {
                    $this->workflows_report($workflow['id'], 'Wrong email [bcc] in workflow: ' . $bcc);
                    return;
                }
                //do not repeat in bcc: emails of to:
                if(in_array($bccs[$_ibcc],$params['to'])) unset($bccs[$_ibcc]);
            }
            $params['bcc'] = array_values($bccs);
            if($params['bcc'])
                $params['preserve_recipients'] = true;
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
            'cc'=>$params['cc']??null,
            'subject'=>$params['subject'],
            'slug'=>$workflow['template'],
            'result'=>$result['result']??$result,
            'time'=>round(microtime(true)-$_time,4)
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
            'X-WEB-KEY'=> $this->core->system->getHeader('X-WEB-KEY'),
            'X-DS-TOKEN' => $this->core->system->getHeader('X-DS-TOKEN')
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
        $data_to_send['_user'] = $this->core->user->id??'unknown';
        // add in url user id
        $hook['url'].= (strpos($hook['url'],'?')?'&':'?').'_user='.urlencode($this->core->user->id ?? '').'&_type='.urlencode($hook_type ?? '');
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

        //region EVALUATE to process $workflow['message']
        $this->processWorkFlowMessage($workflow,$data);
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

/**
 * Represents a class responsible for interacting with the CFO API.
 * It allows for performing API calls to retrieve, update, display, or read data related to CFO entities.
 */
class CFOApi {
    /** @var Core7  */
    var $core;

    /** @var string $api URL to be used in API CALLS */
    protected $apiUrl = 'https://api.cloudframework.io/core/cfo/cfi';

    /** @var array $headers headers to be used in API Calls */
    protected $headers = ['X-WEB-KEY'=>null,'X-DS-TOKEN'];

    // Error Variables
    var $error = false;                 // When error true
    var $errorCode = null;                   // Code of error
    var $errorMsg = [];                 // When error array of messages


    /**
     * DataSQL constructor.
     * @param Core $core
     */
    function __construct(Core7 &$core)
    {
        $this->core = $core;
        $this->headers['X-WEB-KEY'] = $this->core->system->getHeader('X-WEB-KEY');
        $this->headers['X-DS-TOKEN'] = $this->core->system->getHeader('X-DS-TOKEN');
    }

    /**
     * Set the headers to be used in API Calls
     *
     * @param array $headers The headers to be set.
     *
     * @return void
     */
    public function setHeaders(array $headers)
    {
        $this->headers = $headers;
    }

    /**
     * Set the api URL to be used in API Calls
     *
     * @param string $apiUrl The headers to be set.
     *
     * @return void
     */
    public function setAPIUrl(string $apiUrl)
    {
        $this->apiUrl = $apiUrl;
    }

    /**
     * GET the structure to Display the data for a given CFO and ID
     *
     * @param string $cfo The CFO value
     * @param string|int $id The ID value
     * @param array $params optional array to send parameters or filters
     * @return array|false Returns the data for the given CFO and ID
     */
    public function view(string $cfo, string $view='default',$filters=[]): bool|array
    {
        $url = $this->apiUrl.'/'.urlencode($cfo).'?cfo_view='.urlencode($view);
        $result = $this->core->request->get_json_decode($url,$filters,$this->headers);
        if($this->core->request->error) {
            $this->addError('api-error',$this->core->request->errorMsg);
            $this->core->request->reset();
            return false;
        }
        return $result['data']??$result;

    }

    /**
     * GET the structure to Display the data for a given CFO and ID
     *
     * @param string $cfo The CFO value
     * @param string|int $id The ID value
     * @param array $params optional array to send parameters or filters
     * @return array|false Returns the data for the given CFO and ID
     */
    public function display(string $cfo, string|int $id,$params=[]): bool|array
    {
        return $this->getCFOEntity('display',$cfo,$id,$params);
    }

    /**
     * GET the structure to Display the data for a given CFO and ID
     *
     * @param string $cfo The CFO value
     * @param string|int $id The ID value
     * @param array $params optional array to send parameters or filters
     * @return array|false Returns the data for the given CFO and ID or false if error
     */
    public function update(string $cfo, string|int $id,$params=[]): bool|array
    {
        return $this->getCFOEntity('update',$cfo,$id,$params);
    }

    /**
     * Get CFO entity from API
     *
     * @param string $command The command to execute: display|update
     * @param string $cfo CFO value
     * @param string|int $id ID of the entity
     * @param array $params Additional parameters to pass in the request (default [])
     *
     * @return array|false Returns the data of the CFO entity if successful, otherwise null
     */
    private function getCFOEntity(string $command, string $cfo, string|int $id, $params=[]): bool|array
    {
        //region VERIFY $headers and READ $docs from endpoint $url_to_get_docs_from_cfo
        $url = $this->apiUrl.'/'.urlencode($cfo).'/'.$command.'/'.urlencode($id);
        $result = $this->core->request->get_json_decode($url,$params,$this->headers);
        if($this->core->request->error) {
            $this->addError('api-error',$this->core->request->errorMsg);
            $this->core->request->reset();
            return false;
        }
        //endregion

        return $result['data']??$result;
    }

    /**
     * Get CFO entities from API
     *
     * @param string $cfo CFO value
     * @param array $params Additional parameters to pass in the request (default [])
     *
     * @return array|false Returns the data of the CFO entity if successful, otherwise null
     */
    private function getCFOEntities(string $cfo, array $params=[]): bool|array
    {
        //region VERIFY $headers and READ $docs from endpoint $url_to_get_docs_from_cfo
        $url = $this->apiUrl.'/'.urlencode($cfo);
        $result = $this->core->request->get_json_decode($url,$params,$this->headers);
        if($this->core->request->error) {
            $this->addError('api-error',$this->core->request->errorMsg);
            $this->core->request->reset();
            return false;
        }
        //endregion

        return $result['data']??$result;
    }

    /**
     * Read data from $cfo with $id
     *
     * @param string $cfo The CFO value
     * @param string|int $id The ID value
     * @return array|bool Returns an array of data if found, otherwise returns false
     */
    public function readEntity(string $cfo, string|int $id): bool|array
    {
        if(!$result = $this->getCFOEntity('display',$cfo,$id)) return false;
        return $result['data']??[];
    }

    /**
     * Read data from $cfo
     *
     * @param string $cfo The CFO value
     * @param array $params Additional parameters to pass in the request (default [])
     * @return array|bool Returns an array of data if found, otherwise returns false
     */
    public function readEntities(string $cfo, array $params=[]): bool|array
    {
        if(!$result = $this->getCFOEntities($cfo,$params)) return false;
        return $result['data']??[];
    }

    /**
     * Read data from $cfo
     *
     * @param string $cfo The CFO value
     * @param string|int $id The ID value
     * @param int $id The ID value
     * @return array|bool Returns an array of data if found, otherwise returns false
     */
    public function updateEntity(string $cfo, string|int $id, array $data)
    {
        //region VERIFY $headers and READ $docs from endpoint $url_to_get_docs_from_cfo
        $url = $this->apiUrl.'/'.urlencode($cfo).'/'.urlencode($id);
        $result = $this->core->request->put_json_decode($url,$data,$this->headers);
        if($this->core->request->error) {
            $this->addError('api-error',$this->core->request->errorMsg);
            $this->core->request->reset();
            return false;
        }
        //endregion

        return $result['data']??$result;
    }

    /**
     * Add an error in the class
     * @param string $code Code of error
     * @param mixed $value
     * @return bool Always return null to facilitate other return functions
     */
    private function addError(string $code,$value)
    {
        $this->error = true;
        $this->errorCode = $code;
        $this->errorMsg[] = $value;

        return false;
    }
}

/**
 * Class CFOClassObjects
 * Provides functionality for managing CFO objects and interacting with the data layer
 * while supporting error handling and data validation.
 */
class CFOClassObjects {

    protected \Core7 $core;
    protected \CFOs $cfos;
    protected $fields;
    protected string $object = '';
    protected string $type = '';

    // ---- Error Variables ----
    var bool $error = false;
    var $errorCode = null;
    var $errorMsg = [];

    /**
     * Constructor of the class
     *
     * @param \Core7 $core Instance of Core7 object
     * @param \CFOs $cfos Instance of CFOs object
     */
    function __construct(\Core7 &$core, \CFOs &$cfos, &$fields)
    {
        $this->core = $core;
        $this->cfos = $cfos;
        $this->fields = $fields;
        if(!$this->object = ($this->fields->cfoInfo['object'] ?? ''))
            $this->addError('programming-conflict','Missing [object] property in CFO\\{object}\\Fields class');

        if(!$this->type = ($this->fields->cfoInfo['type'] ?? ''))
            $this->addError('programming-conflict','Missing [type] property in CFO\\{object}\\Fields class');

    }




    /**
     * Toggle the usage of CFO secret
     * @param bool $use Whether to enable the use of CFO secret (default: true)
     * @return void
     */
    function useCFOSecret(bool $use=true) {
        $this->cfos->useCFOSecret($use);
    }


    /**
     * Searches for records in the data source based on the specified criteria and options.
     *
     * @param array $where An associative array defining the search criteria. The keys should
     *                     correspond to valid field names, and the values are the filter values.
     * @param array $options Optional settings for the search. Supported keys include:
     *                       - 'fields': The specific fields to include in the result (default: '*').
     *                       - 'limit': The maximum number of results to retrieve (default: 0, no limit).
     *                       - 'page': The page number for paginated results (default: 0).
     *                       - 'cursor': A numeric cursor for result navigation (default: 0).
     * @return mixed The search result if successful, or false if an error occurred. Errors can
     *               include invalid fields in the criteria, unsupported field operations, or
     *               issues with the data source.
     */
    public function search(array $where, array $options=[]): mixed
    {
        // return false if any previous erro
        if($this->error) return false;

        // verify search fields are part of the fields
        foreach ($where as $key => $value) {
            if(!$field = $this->fields->cfoInfo['fields'][$key]??null) return $this->addError('params-error','Unknown field: '.$key);
            if(key_exists('allow_search',$field) && !$field['allow_search']) return $this->addError('params-error','This field is not allowed on search: '.$key);
        }

        $fields = $options['fields'] ?? '*';
        $limit = (is_numeric($options['limit']??null))?intval($options['limit']):0;
        $page = (is_numeric($options['page']??null))?intval($options['page']):0;
        $cursor = (is_numeric($options['cursor']??null))?intval($options['cursor']):0;

        // execute the search
        switch ($this->type) {
            case 'db':
                $this->cfos->db($this->object)->setLimit($limit>0?:0);
                $this->cfos->db($this->object)->setPage($page>0?:0);

                $ret = $this->cfos->db($this->object)->fetch($where,$fields);
                if($this->cfos->db($this->object)->error)
                    return $this->addError('database-error',$this->cfos->db($this->object)->error);
                break;

            default:
                return $this->addError('programming-conflict','Not supported type: '.$this->type);
        }
        return $ret;
    }

    /**
     * Inserts a new record into the database based on the provided data.
     *
     * @param array $data An associative array of data to be inserted. If not provided, previously loaded data will be inserted
     * @return array|false The inserted record as an associative array if successful. Returns false if an error occurs or the verification fails.
     */
    public function insertEntity(array $data=[])
    {

        if($data) $this->fields->set($data);
        if(!$data_to_insert = $this->verifyDataToInsert()) return false;

        switch ($this->type) {
            case 'db':
                $id = $this->cfos->db($this->object)->insert($data_to_insert);
                if($this->cfos->db($this->object)->error)
                    return $this->addError('database-error',$this->cfos->db($this->object)->error);
                if(!$record = $this->readEntity($id)) return false;
            default:
                return $this->addError('programming-conflict','Not supported type: '.$this->type);
        }

        return $record;
    }

    /**
     * Retrieves a single record by its primary key.
     *
     * @param string|int $id The unique identifier of the record to retrieve.
     * @param string $fields optional paramater to define what fields retrieve
     * @return array|false The data of the record if found and successfully retrieved. Returns false if an error occurs or the record is not found.
     */
    public function readEntity(string|int $id, string $fields='')
    {
        switch ($this->type) {
            case 'db':
                $data = $this->cfos->db($this->object)->fetchOneByKey($id,$fields);
                if($this->cfos->db($this->object)->error)
                    return $this->addError('database-error',$this->cfos->db($this->object)->error);
                if(!$data) {
                    return $this->addError('not-found','Entity not found: '.$id);
                }
                $this->fields->set($data);
                break;
            default:
                return $this->addError('programming-conflict','Not supported type: '.$this->type);
        }
        return $this->fields->getData();
    }

    /**
     * Reloads the current entity based on its unique identifier.
     *
     * @return mixed The reloaded entity object if successful, or false if an error occurs. Errors may include
     *               a missing key field in the class definition or a missing value for the key field.
     */
    public function reload()
    {
        if(!$idField = $this->fields->key())
            return $this->addError('conflict', 'Missing key field in CFO class definition');
        if(!$id_read_value = $this->fields->getData($idField))
            return $this->addError('params-error', 'Missing key value in CFO class definition');

        return $this->readEntity($id_read_value);
    }

    /**
     * Updates an entity with the given data if the data is valid.
     *
     * @param array $data An associative array of the data to be updated. The keys correspond
     *                    to the entity's fields, and the values are the new values for those fields.
     * @return mixed Returns the result of the update operation if the data is valid; otherwise,
     *               returns false if the data cannot be verified or an error occurs.
     */
    public function updateEntity(array $data=[])
    {
        if(!$data_to_update = $this->verifyDataToUpdate($data)) return false;

        switch ($this->type) {
            case 'db':
                $this->cfos->db($this->object)->update($data_to_update);
                if($this->cfos->db($this->object)->error)
                    return $this->addError('database-error',$this->cfos->db($this->object)->error);

                break;
            default:
                return $this->addError('programming-conflict','Not supported type: '.$this->type);
        }
        return $this->reload();
    }

    public function deleteEntity(int|string $id='')
    {

        //region READ $id if not empty
        if(!$idField = $this->fields->key()) return $this->addError('params-error', 'Missing key field in CFO class definition to delete it');
        $id_read_value = $this->fields->getData($idField);
        if($id) {
            if(!$this->readEntity($id)) return false;
            $id_read_value = $id;
        }
        if(!$id && !$id_read_value) return $this->addError('params-error', 'Missing id value to delete.');
        //endregion

        $data_to_delete = [
            $idField=>  $id_read_value
        ];
        switch ($this->type) {
            case 'db':
                $this->cfos->db($this->object)->delete($data_to_delete);
                if($this->cfos->db($this->object)->error)
                    return $this->addError('database-error',$this->cfos->db($this->object)->error);
                break;
            default:
                return $this->addError('programming-conflict','Not supported type: '.$this->type);
        }

        return $this->fields->getData();
    }

    /**
     * Verifies the data to be inserted by checking mandatory fields and ensuring all fields comply with insertion rules.
     *
     * @return array|false The validated data to insert if successful. Returns false if an error occurs during validation.
     */
    public function verifyDataToUpdate(array $data)
    {

        //region SET $id_read_value,$id_data_value VERIFYING that $this->fields have any key and the id value from read entity and $data
        if(!$idField = $this->fields->key()) return $this->addError('params-error', 'Missing key field in CFO class definition');
        if(!$data) return $this->addError('params-error','Missing data to update');
        $id_read_value = $this->fields->getData($idField);
        $id_data_value = ($data[$idField]??null);
        //endregion

        //region CHECK if $id_read_value and $id_data_value are empty to RETURN an error
        if(!$id_read_value && !$id_data_value)
            return $this->addError('params-error',"missing field [{$idField}]  with id value to apply an update");
        //endregion

        //region IF !$id_read_value && $id_data_value THEN $this->readEntity($id_data_value)
        if(!$id_read_value && $id_data_value) {
            if (!$this->readEntity($id_data_value)) return false;
            $id_read_value = $id_data_value;
        }
        //endregion

        //region CHECK if $id_read_value!=$id_data_value when both have a value to RETURN an error
        if($id_read_value && $id_data_value && $id_data_value!=$id_read_value)
            return $this->addError('params-error','data contains different value for key field ['.$idField.'] than value previously read');
        //endregion

        //region CHECK if we have received mandatory fields on update
        $mandatoryFields = $this->getMandatoryFieldsOnUpdate();
        foreach ($mandatoryFields as $field) {
            if(!key_exists($field,$data)) {
                return $this->addError('params-error', 'Missing mandatory field: ' . $field);
            }
        }
        //endregion

        //region FEED and RETURN $data_to_update VERIFYING $data

        //init array to update
        $data_to_update = [
            $idField => $id_read_value
        ];

        //loop $data to verify its information.
        foreach ($data as $field => $value) {

            //if the field is not part of the fields return an error
            if(!$fieldDefinition = $this->fields->cfoInfo['fields'][$field]??null) {
                return $this->addError('params-error', 'Unknown field: ' . $field);
            }

            // do not allow to update fields with property allow_on_update===false
            if(key_exists('allow_on_update',$fieldDefinition) && !$fieldDefinition['allow_on_update']) {
                // if we have received it but the value is the same the avoid error.
                //if($value == $this->fields->getData($field)) continue;
                //else return an error. You are trying to change the value
                return $this->addError('params-error', "The field [{$field}] is not allowed on update.");
            }
            $data_to_update[$field] = $value;
        }

        //return error oif there is not data to update
        if(count($data_to_update) == 1) return $this->addError('params-error','No data to update');

        //return the final array to update
        return $data_to_update;
        //endregion

    }

    /**
     * Verifies the data to be inserted by checking mandatory fields and ensuring all fields comply with insertion rules.
     *
     * @return array|false The validated data to insert if successful. Returns false if an error occurs during validation.
     */
    public function verifyDataToInsert()
    {

        if(!$this->fields->key()) return $this->addError('params-error', 'Missing key field in CFO class definition');


        $mandatoryFields = $this->getMandatoryFieldsOnInsert();
        foreach ($mandatoryFields as $field) {
            if($this->fields->getData($field)===null) {
                return $this->addError('params-error', 'Missing mandatory field: ' . $field);
            }
        }

        $data = $this->fields->getData();
        foreach ($data as $key => $value) {

            if(!$field = $this->fields->cfoInfo['fields'][$key]??null) {
                return $this->addError('params-error', 'Unknown field: ' . $key);
            }
            if(key_exists('allow_on_insert',$field) && !$field['allow_on_insert']) {
                return $this->addError('params-error', 'This field is not allowed on insert: ' . $key);
            }
        }

        return $data;

    }



    /**
     * Retrieves a list of mandatory field names that are required during the insert operation.
     *
     * @return array List of mandatory field keys required on insert
     */
    public function getMandatoryFieldsOnInsert(): array
    {
        $ret = [];
        foreach ($this->fields->cfoInfo['fields'] as $key => $value) {
            if($value['mandatory_on_insert']??null) $ret[] = $key;
        }
        return $ret;
    }

    /**
     * Retrieves a list of mandatory field names that are required during the insert operation.
     *
     * @return array List of mandatory field keys required on insert
     */
    public function getMandatoryFieldsOnUpdate(): array
    {
        $ret = [];
        foreach ($this->fields->cfoInfo['fields'] as $key => $value) {
            if($value['mandatory_on_update']??null) $ret[] = $key;
        }
        return $ret;
    }

    /**
     * Retrieves a list of allowed fields names on update a record
     *
     * @return array List of mandatory field keys required on insert
     */
    public function getAllowedFieldsOnUpdate(): array
    {
        $ret = [];
        foreach ($this->fields->cfoInfo['fields'] as $key => $value) {
            if(!key_exists('allow_on_update',$value) || $value['allow_on_update']) $ret[] = $key;
        }
        return $ret;
    }

    /**
     * Retrieves a list of allowed fields names on insert a record
     *
     * @return array List of mandatory field keys required on insert
     */
    public function getAllowedFieldsOnInsert(): array
    {
        $ret = [];
        foreach ($this->fields->cfoInfo['fields'] as $key => $value) {
            if(!key_exists('allow_on_insert',$value) || $value['allow_on_insert']) $ret[] = $key;
        }
        return $ret;
    }


    /**
     * Add an error in the class
     * @param string $code
     * @param $message
     * @return false to facilitate the return of other functions
     */
    private function addError(string $code,$message): bool
    {
        $this->error=true;
        $this->errorCode=$code;
        $this->errorMsg[]=$message;
        return false;
    }

}

/**
 * Represents a class responsible for managing CFO-related fields and their data.
 * This class provides methods to manipulate and retrieve data while adhering to field definitions.
 */
class CFOClassFields
{
    var $cfoInfo = [];

    /**
     * Sets the value of a specified field if it exists within the defined fields.
     *
     * @param string $field The name of the field to set the value for.
     * @param mixed $value The value to be assigned to the specified field.
     * @return self Returns the current instance for method chaining after setting the field value.
     */
    protected function setFieldValue(string $field, $value)
    {
        if(key_exists($field,$this->cfoInfo['fields']??[])) {
            $this->data[$field]=$value;
            $this->{$field}=$value;
        }
        return $this;
    }


    /**
     * Retrieves the value associated with the 'key' in the cfoInfo array.
     * Returns null if the 'key' is not set.
     *
     * @return mixed|null The value of the 'key' or null if not set.
     */
    public function key() {
        return $this->cfoInfo['key']??null;
    }

    /**
     * Retrieves the value associated with the 'object' in the cfoInfo array.
     * Returns null if the 'object' is not set.
     *
     * @return mixed|null The value of the 'key' or null if not set.
     */
    public function object() {
        return $this->cfoInfo['object']??null;
    }


    /**
     * Populates the data array with values from the input array based on defined fields.
     *
     * @param array $data An associative array containing the input data to be processed.
     * @return array The resulting array with populated field values.
     */
    public function set(array $data): array
    {
        $this->reset();
        return $this->update($data);
    }

    /**
     * Resets the data array by clearing all its contents.
     *
     * @return self Returns the current instance for method chaining after resetting the data.
     */
    public function reset()
    {
        $this->data = [];
        foreach ($this->cfoInfo['fields'] as $field => $info) {
            $this->{$field} = null;
        }
        return $this;
    }

    /**
     * Updates the data array with values from the input array for matching fields.
     *
     * @param array $data An associative array containing the input data to be updated.
     * @return array The updated data array with values from the input array for valid fields.
     */
    public function update(array $data): array
    {
        foreach ($data as $field => $info) {
            if(!key_exists($field,$this->cfoInfo['fields'])) continue;
            $this->data[$field] = $info;
            $this->{$field} = $info;
        }
        return $this->data;
    }

    /**
     * Checks if a specific field exists within the `fields` array of the `cfoInfo` property.
     *
     * @param string $field The key to check for existence in the `fields` array.
     * @return bool Returns true if the specified key exists in the `fields` array, otherwise false.
     */
    public function exists(string $field) {
        return key_exists($field,$this->cfoInfo['fields']??[]);
    }

    /**
     * Retrieves data stored within the object's `data` property.
     *
     * @param string $field The specific key to retrieve from the `data` array. If not provided, the entire `data` property is returned.
     * @return mixed Returns the value associated with the specified key, or the entire `data` property if no key is provided. If the key does not exist, returns null.
     */
    public function getData(string $field='') {
        if(!$field) return $this->data;
        else return $this->data[$field]??null;
    }

    /**
     * Retrieves the field names of the CFO object
     *
     * @return array Returns an array of strings
     */
    public function getFieldsNames() {
        return array_keys($this->cfoInfo['fields']);
    }

    /**
     * Reduces the given array based on the allowed fields.
     * @param array $data The input array to be filtered.
     * @return array The filtered array containing only the defined fields.
     */
    public function getArrayReducedWithFieldsData(array $data)
    {
        foreach ($data as $field => $value) {
            if(!is_array($this->cfoInfo['fields'][$field]??null)) unset($data[$field]);;
        }
        return $data;
    }

    /**
     * Reduces the given array based on the allowed fields.
     * @param array $data The input array to be filtered.
     * @return array The filtered array containing only the defined fields.
     */
    public function getArrayReducedWithFieldsDataOnUpdate(array $data)
    {
        $data = $this->getArrayReducedWithFieldsData($data);

        foreach ($data as $field => $value) {
            if(key_exists('allow_on_update',$this->cfoInfo['fields'][$field])
                && !$this->cfoInfo['fields'][$field]['allow_on_update'])
                unset($data[$field]);
        }
        return $data;
    }

    /**
     * Reduces the given array based on the allowed fields.
     * @param array $data The input array to be filtered.
     * @return array The filtered array containing only the defined fields.
     */
    public function getArrayReducedWithFieldsDataOnInsert(array $data)
    {
        $data = $this->getArrayReducedWithFieldsData($data);

        foreach ($data as $field => $value) {
            if(key_exists('allow_on_insert',$this->cfoInfo['fields'][$field])
                && !$this->cfoInfo['fields'][$field]['allow_on_insert'])
                unset($data[$field]);
        }
        return $data;
    }
}