<?php

use Google\Cloud\Datastore\DatastoreClient;
use Google\Cloud\Datastore\Key;
use Google\Cloud\Datastore\Query\Query;
use Google\Cloud\Datastore\Transaction;
use Google\Cloud\Datastore\Query\Aggregation;

if (!defined ("_DATASTORECLIENT_CLASS_") ) {
    define("_DATASTORECLIENT_CLASS_", TRUE);

    /**
     * [$sql = $this->core->loadClass('DataStore');] Class to Handle Datastore databases with CloudFramework models
     *
     * * https://cloud.google.com/datastore/docs/concepts/entities
     * * https://cloud.google.com/datastore/docs/concepts/queries#datastore-datastore-limit-gql
     * * https://googleapis.github.io/google-cloud-php/#/docs/google-cloud/v0.121.0/datastore/datastoreclient
     * * https://github.com/GoogleCloudPlatform/php-docs-samples/blob/master/datastore/api/src/functions/concepts.php
     * @package CoreClasses
     */
    class DataStore
    {
        protected $_version = '20250307';
        /** @var Core7 $core */
        private $core = null;                   // Core7 reference
        /** @var DatastoreClient|null  */
        var $datastore = null;              // DatastoreClient
        var $error = false;                 // When error true
        var $errorCode = 503;               // Default Error Code
        var $errorMsg = [];                 // When error array of messages
        var $options = [];
        var $entity_name = null;
        var $schema = [];
        var $lastQuery = '';
        var $limit = 0;
        var $cursor = '';
        var $last_cursor;
        var $default_time_zone_to_read = 'UTC';
        var $default_time_zone_to_write = 'UTC';
        /** @var CoreCache $cache */
        var $cache = null;
        var $useCache = false;
        var $cacheSecretKey = '';
        var $cacheSecretIV = '';
        var $cache_data = null;
        var $project_id = '';
        var $namespace = 'default';
        var $service_account = null;
        var $debug = false;
        var $transformReadedEntities = true; // Transform readed entities

        /**
         * Cloudframework Datastore Constructor
         * @param Core7 $core
         * @param array $params [
         *
         * * @type string $entity_name Name of the Datastore entity. Optionally you can pass it as $params[0],
         * * @type string $namespace [optional] Namspace of the Datastore entity. Optionally you can pass it as $params[1],
         * * @type array $schema [optional] Eschema to define a CloudFramework Model for the Entity. Optionally you can pass it as $params[2]
         * * @type array $options [optional] Optons for security and transport. Optionally you can pass it as $params[3]: [
         *
         *   * @tyoe string $namespaceId [optional]
         *   * @tyoe string $projectId [optional]
         *   * @tyoe string $transport [optional] can be: grpc or rest. to stablish server protocol. Bu default is $core->config->get('core.datastore.transport') or rest
         *   * @tyoe array $keyFile [optional] you can pass a server token generated in GCP to stablish access permissions
         *
         *   ]
         *
         * ]
         */
        function __construct(Core7 &$core, array $params)
        {

            //region SET $this->core and START $this->core->__p performance
            $this->core = $core;
            $this->core->__p->add('DataStore new instance ', ($params['entity_name']??null)?:($params[0]??null), 'note');
            //endregion

            //region SET $this->debug to true IF $this->core->is->development()
            if($this->core->is->development()) {$this->debug = true;}
            //endregion

            //region SET $this->entity_name from mandatory $params['entity_name']
            $this->entity_name = ($params['entity_name']??null)?:($params[0]??null);
            if(!$this->entity_name) return $this->addError('Missing [entity_name] en $params');
            //endregion

            //region SET $this->namespace from optional $params['namespace']. If it is not sent it will be 'default'
            $this->namespace = ($params['namespace']??null)?:($params[1]??'default');
            //endregion

            //region SET $this->schema baaed on $params['schema']
            $this->loadSchema( $this->entity_name, ($params['schema']??null)?:($params[2]??null)); // Prepare $this->schema
            //endregion

            //region SET $options from $params['options']
            $options = ($params['options']??[])?:($params[3]??[]);
            if(!is_array($options)) $options = [];
            //endregion

            //region SET $this->datastore, $this->project_id
            if($this->core->gc_project_id && !($options['projectId']??null)) $options['projectId'] = $this->core->gc_project_id;
            if(!isset($options['namespaceId'])) $options['namespaceId'] = $this->namespace;
            if(!isset($options['transport']) || !$options['transport'])
                $options['transport'] = ($core->config->get('core.datastore.transport')=='grpc')?'grpc':'rest';

            // ALLOW ACCESS to GLOBAL $datastore in order to increase performance
            global $datastore;

            // SET $this->project_id
            $this->project_id = ($core->config->get("core.gcp.datastore.project_id"))?:getenv('PROJECT_ID');

            // ASSIGN $this->datastore to global $datastore o to a new DatastoreClient
            if((($options['projectId']??null) && $this->project_id!=$options['projectId']) || ($options['keyFile']??null) || !is_object($datastore)) {
                try {
                    $this->datastore = new DatastoreClient($options);
                    $this->project_id = $options['projectId'];
                    if(isset($options['keyFile'])) $this->service_account = $options['keyFile']['client_email']??null;
                } catch (Exception $e) {
                    return($this->addError($e->getMessage()));
                }
            } else {
                $this->datastore = &$datastore;
            }
            //endregion

            //region SET $this->default_time_zone_to_read
            if(isset($this->core->system->time_zone[0]) && $this->core->system->time_zone[0]) {
                $this->default_time_zone_to_read = $this->core->system->time_zone[0];
            }
            //endregion

            //region END  $this->core->__p performance
            $this->core->__p->add('DataStore new instance ', '', 'endnote');
            return true;
            //endregion

        }

        /**
         * Update the connection settings for the Datastore client.
         *
         * @param array $options An associative array containing service account for Datastore connetion
         *                       Possible options include:
         *                       - 'namespace': The namespace to use.
         *                       - 'project_id': The project ID to connect to.
         *                       - 'private_key': The key file information for authentication.
         *
         * @return bool If an error occurs during the update, it will add an error message and return null.
         */
        public function updateServiceAccount(array $serviceAccount)
        {

            //region VERIFY $serviceAccount private_key && project_id
            if (!($serviceAccount['private_key']??null) ||
                !($serviceAccount['project_id']??null)
            ) {
                return $this->addError(__FUNCTION__, ': Missing [private_key or project_id] en $serviceAccount');
            }
            //endregion

            //region INIT $options and UPDATE $this->namespace, $this->project_id, $this->service_account
            $options = [];
            $options['keyFile'] = $serviceAccount;
            $options['projectId'] = $serviceAccount['project_id'];
            $options['namespace'] = $serviceAccount['namespace']??$this->namespace;
            $options['transport'] = ($this->core->config->get('core.datastore.transport')=='grpc')?'grpc':'rest';
            $this->namespace = $options['namespace'];
            $this->project_id = $options['projectId'];
            $this->service_account = $options['keyFile']['client_email']??null;
            //endregion

            //region CREATE $this->datastore with credentials
            try {
                $this->datastore = new DatastoreClient($options);
            } catch (Exception $e) {
                return($this->addError($e->getMessage()));
            }
            //endregion

            return true;
        }

        /**
         * Set $this->useCache to true or false
         * @param boolean $activate
         * @param string $secretKey optionally you can pass a secretKey for Encryption
         * @param string $secretIV optionally you can pass a secretIV for Encryption
         */
        function activateCache($activate=true,$secretKey='',$secretIV=''){
            $this->useCache = ($activate)?true:false;
            $this->cacheSecretKey = $secretKey;
            $this->cacheSecretIV = $secretIV;
            if($this->useCache) $this->initCache();
            else $this->cache = null;
        }
        function deactivateCache($activate=true) { $this->activateCache(false);}


        /**
         * Creates a new entity in the datastore.
         *
         * @param array $data The data required to create the entity.
         * @param bool $transaction Indicates whether the creation should be part of a transaction. Defaults to false.
         * @return mixed The created entity. If an error occurs, it may return false or null.
         */
        function createEntity($data,$transaction=false)
        {
            $ret =  $this->createEntities([$data],$transaction);
            return $ret?$ret[0]:$ret;
        }

        /**
         * Creates one or multiple entities in the datastore using the provided data.
         *
         * @param array $data The data to create entities, must be an array or multidimensional array containing the entity data.
         * @param bool $transaction Optional. Indicates whether the operation should be executed within a transaction. Defaults to false.
         * @return array|false An array of created entities or false if an error occurs.
         */
        function createEntities($data,$transaction=false)
        {
            if ($this->error) return false;
            if (!is_array($data)) return($this->setError('No data received'));

            // Init performance
            $this->core->__p->add('createEntities: ', $this->entity_name, 'note');
            $ret = [];
            $entities = [];

            // converting $data into n,n dimmensions
            if (!array_key_exists(0, $data) || !is_array($data[0])) $data = [$data];

            // Establish the default time zone to write
            $tz = new DateTimeZone($this->default_time_zone_to_write);

            // loop the array into $row
            foreach ($data as $row) {
                $record = [];
                $schema_key = null;
                $schema_keyname = null;

                if (!is_array($row)) return $this->setError('Wrong data structure');
                // Loading info from Data. $i can be numbers 0..n or indexes.
                foreach ($row as $_key => $value) {

                    //$i = strtolower($i);  // Let's work with lowercase

                    if($value && preg_match('/(\||^)unique(\||$)/', $this->schema['props'][$_key][2]??'')) {
                        $_search = $this->fetchAll('__key__',[$_key=>$value]);
                        if($this->error) return;
                        if($_search) {

                            $_id = $row['KeyId']??($row['KeyName']??null);
                            $_key_name =  $_id?(($row['KeyId']??'')?'KeyId':'KeyName'):'';
                            if(!$_id) {
                                $this->errorCode = 400;
                                return $this->addError("The value of the unique field [$_key] already exist [".count($_search)."] entities");
                            }
                            else {
                                if(count($_search)>1) {
                                    $this->errorCode = 400;
                                    return $this->addError("The value of the unique field [$_key] already exist in other entities");
                                }
                                elseif($_search[0][$_key_name]!=$_id)  {
                                    $this->errorCode = 400;
                                    return $this->addError("The value of the unique field [$_key] already exist in other entity");
                                }
                            }
                        }
                    }
                    // If the key or keyname is passed instead the schema key|keyname let's create
                    if (strtolower($_key) == 'key' || strtolower($_key) == 'keyid' || strtolower($_key) == 'keyname') {
                        $this->schema['props'][$_key][0] = $_key;
                        $this->schema['props'][$_key][1] = (strtolower($_key) == 'keyname') ? strtolower($_key) : 'key';
                    }

                    // Only use those variables that appears in the schema except key && keyname
                    if (!isset($this->schema['props'][$_key])) continue;

                    // if the field is key or keyname feed $schema_key or $schema_keyname
                    if ($this->schema['props'][$_key][1] == 'key') {
                        $schema_key = preg_replace('/[^0-9]/', '', $value);
                        if (!strlen($schema_key)) return $this->setError('wrong Key value');

                    }
                    elseif ($this->schema['props'][$_key][1] == 'keyname') {
                        $schema_keyname = $value;

                        // else explore the data.
                    }
                    elseif ($this->schema['props'][$_key][1] == 'boolean') {
                        $record[$this->schema['props'][$_key][0]] = ($value)?true:false;
                        // else explore the data.
                    }
                    else {
                        if (is_string($value)) {
                            // date & datetime values
                            if ($this->schema['props'][$_key][1] == 'list') {
                                if (strlen($value)) {
                                    $value = explode(',',$value);
                                }
                            }
                            elseif ($this->schema['props'][$_key][1] == 'date' || $this->schema['props'][$_key][1] == 'datetime' || $this->schema['props'][$_key][1] == 'datetimeiso') {
                                if (strlen($value)) {
                                    // Fix the problem when value is returned as microtime
                                    try {
                                        // Convert to DateTime Object for Datastore
                                        $value_time = new DateTime($value, new DateTimeZone($this->default_time_zone_to_read));

                                        // Evaluate to change TimeZone if  $this->default_time_zone_to_read!=$this->default_time_zone_to_write
                                        if($this->default_time_zone_to_read!=$this->default_time_zone_to_write) $value = $value_time->setTimezone(new DateTimeZone($this->default_time_zone_to_write));
                                        else $value = $value_time;

                                    } catch (Exception $e) {
                                        return $this->setError('field {' . $this->schema['props'][$_key][0] . '} has a wrong date format: ' . $value);
                                    }
                                } else {
                                    $value = null;
                                }
                                // geo values
                            } elseif ($this->schema['props'][$_key][1] == 'geo') {
                                if (!strlen($value)) $value = '0.00,0.00';
                                list($lat, $long) = explode(',', $value, 2);
                                $value = new Geopoint($lat, $long);
                            } elseif ($this->schema['props'][$_key][1] == 'json') {
                                if (!strlen($value)) {
                                    $value = '{}';
                                } else {
                                    json_decode($value); // Let's see if we receive a valid JSON
                                    if (json_last_error() !== JSON_ERROR_NONE) $value = $this->core->jsonEncode($value, JSON_PRETTY_PRINT);
                                }
                            } elseif ($this->schema['props'][$_key][1] == 'zip') {
                                $value = mb_convert_encoding(gzcompress($value), 'UTF-8', 'ISO-8859-1');
                                //$value = utf8_encode(gzcompress($value));
                            } elseif ($this->schema['props'][$_key][1] == 'jsonzip') {
                                if (!strlen($value)) {
                                    $value = null;
                                } else {
                                    $value = json_decode($value); // Let's see if we receive a valid JSON
                                    if (!json_last_error()) {
                                        $value = $this->core->jsonEncode($value);
                                        $value = mb_convert_encoding(gzcompress($value), 'UTF-8', 'ISO-8859-1');
                                    } else {
                                        return ($this->setError($this->entity_name . ': ' . $this->schema['props'][$_key][0] . ' has produced an error in JSON conversion'));
                                    }
                                }
                            } elseif ($this->schema['props'][$_key][1] == 'integer') {
                                $value = intval($value);
                            } elseif ($this->schema['props'][$_key][1] == 'float') {
                                $value =  floatval($value);
                            }
                        }
                        else {
                            if ($this->schema['props'][$_key][1] == 'json') {
                                if (is_array($value) || is_object($value)) {
                                    $value = $this->core->jsonEncode($value, JSON_PRETTY_PRINT);
                                } elseif (!strlen($value??'')) {
                                    $value = '{}';
                                }
                            } elseif ($this->schema['props'][$_key][1] == 'jsonzip') {
                                if (is_array($value) || is_object($value)) {
                                    $value = $this->core->jsonEncode($value);
                                    if (!json_last_error()) {
                                        $value = mb_convert_encoding(gzcompress($value), 'UTF-8', 'ISO-8859-1');
                                    } else {
                                        return ($this->setError($this->entity_name . ': ' . $this->schema['props'][$_key][0] . ' has produced an error in JSON conversion'));
                                    }
                                } elseif (!strlen($value??'')) {
                                    $value = null;
                                } else {
                                    return ($this->setError($this->entity_name . ': ' . $this->schema['props'][$_key][0] . ' has received a no supported value'));
                                }
                            } elseif ($this->schema['props'][$_key][1] == 'zip') {
                                if($value)
                                    return ($this->setError($this->entity_name . ': ' . $this->schema['props'][$_key][0] . ' has received a no string value'));

                            } elseif ($this->schema['props'][$_key][1] == 'txt') {
                                if($value)
                                    return ($this->setError($this->entity_name . ': ' . $this->schema['props'][$_key][0] . ' has received a no string value'));

                            } elseif ($this->schema['props'][$_key][1] == 'string') {
                                if(is_numeric($value)) $value = strval($value);
                                else if($value) return ($this->setError($this->entity_name . ': ' . $this->schema['props'][$_key][0] . ' has received a no string value: '.json_encode($value)));
                            }
                        }
                        $record[$this->schema['props'][$_key][0]] = $value;
                    }
                }

                //Complete info in the rest of inf
                if (!$this->error && count($record)) {
                    try {
                        if (null !== $schema_key) {
                            $key = $this->datastore->key($this->entity_name, $schema_key,['namespaceId'=>$this->namespace]);
                            $entity = $this->datastore->entity($key,$record,['excludeFromIndexes'=>(isset($this->schema['excludeFromIndexes']))?$this->schema['excludeFromIndexes']:[]]);
                        } elseif (null !== $schema_keyname) {
                            $key = $this->datastore->key($this->entity_name, $schema_keyname,['identifierType' => Key::TYPE_NAME, 'namespaceId'=>$this->namespace]);
                            $entity = $this->datastore->entity($key,$record,['excludeFromIndexes'=>(isset($this->schema['excludeFromIndexes']))?$this->schema['excludeFromIndexes']:[]]);
                        } else {
                            $key = $this->datastore->key($this->entity_name, null,['namespaceId'=>$this->namespace]);
                            $entity = $this->datastore->entity($key,$record,['excludeFromIndexes'=>(isset($this->schema['excludeFromIndexes']))?$this->schema['excludeFromIndexes']:[]]);
                        }
                        $entities[] = $entity;
                    } catch (Exception $e) {
                        $this->setError($e->getMessage());
                        $ret = false;
                    }
                } else {
                    if(!$this->error) ($this->setError($this->entity_name . ': Structure of the data does not include any field to match with schema'));
                    return;
                }
            }

            // Bulk insertion
            if (!$this->error && count($entities)) try {
                $this->resetCache(); // Delete Cache for next queries..

                // Write entities
                try {
                    if($transaction) {
                        /** @var Transaction $transaction */
                        $transaction = $this->datastore->transaction();
                        $res = $transaction->upsertBatch($entities);
                    } else {
                        $res = $this->datastore->upsertBatch($entities);
                    }
                } catch (Exception $e) {
                    return($this->addError(['$this->datastore->upsertBatch',$e->getMessage()]));
                }

                // Gather Keys
                $keys = [];
                foreach ($entities as &$entity) {
                    $keys[] = $entity->key();
                }
                try {
                    $entities = ['found'=>[]];
                    $chunk_keys = array_chunk($keys,1000);
                    foreach ($chunk_keys as $chunk_key) {
                        $search = $this->datastore->lookupBatch($chunk_key);
                        if($search['found']??null)
                            $entities['found'] = array_merge($entities['found'],$search['found']);
                        unset($search);
                    }
                } catch (Exception $e) {
                    return($this->addError(['$this->datastore->lookupBatch',$e->getMessage()]));
                }
                $ret = [];
                if($entities['found']) $ret = $this->transformResult($entities['found']);
            } catch (Exception $e) {
                $this->setError($e->getMessage());
                $ret = false;
            }

            $this->core->__p->add('createEntities: ', '', 'endnote');
            return ($ret);
        }

        /**
         * Return array with the schema
         * format:
         * { "field1":["type"(,"index|..other validations")]
         * { "field2":["type"(,"index|..other validations")]
         */
        private function loadSchema($entity, $schema)
        {
            $ret = $entity;
            $this->schema['data'] = (is_array($schema)) ? $schema : [];
            $this->schema['props'] = ['__fields' => []];

            if (is_array($schema)) {
                $i = 0;
                if (isset($this->schema['data']['model']) && is_array($this->schema['data']['model'])) $data = $this->schema['data']['model'];
                else $data = $this->schema['data'];
                foreach ($data as $key => $props) {
                    if (!strlen($key)) {
                        $this->setError('Schema of ' . $entity . ' with empty key');
                        return false;
                    } elseif ($key == '__model') {
                        $this->setError('Schema of ' . $entity . ' with not allowed key: __model');
                        return false;
                    }
                    if (!is_array($props)) $props = ['string', ''];
                    else $props[0] = strtolower($props[0]);
                    // true / false index
                    if (isset($props[1]))
                        $index = (stripos($props[1], 'index') !== false);
                    else $index = false;
                    switch ($props[0]) {
                        case "key":
                        case "keyname":
                        case "date":
                        case "datetime":
                        case "datetimeiso":
                        case "float":
                        case "integer":
                        case "boolean":
                        case "bool":
                        case "list":
                        case "emails":
                        case "geo":
                            break;
                        case "json":
                        case "zip":
                        case "jsonzip":
                        case "txt":
                            $index = false;
                            break;
                        default:
                            break;
                    }

                    if (count($props) == 1) $props[1] = null;
                    $this->schema['props'][$i++] = [$key, $props[0], $props[1]];
                    $this->schema['props'][$key] = [$key, $props[0], $props[1]];
                    $this->schema['props']['__model'][$key] = ['type' => $props[0], 'validation' => $props[1]];
                    if(!$index) {
                        $this->schema['excludeFromIndexes'][] = $key;
                    }
                }
            }
            return $this->schema;
        }

        /**
         * Fill an array based in the model structure and  mapped data
         * @param $data
         * @param array $dictionaries
         * @return array
         */
        function getEntityTemplate($transform_keys = true)
        {
            $entity = $entity = array_flip(array_keys($this->schema['props']['__model']));
            foreach ($entity as $key => $foo) {
                $entity[$key] = null;
                if ($transform_keys && in_array($this->schema['props']['__model'][$key]['type'], ['key', 'keyname'])) {
                    if ($this->schema['props']['__model'][$key]['type'] == 'key') $entity['KeyId'] = null;
                    else $entity['KeyName'] = null;
                    unset($entity[$key]);
                }
            }
            return ($entity);
        }

        /**
         * Fill an array based in the model structure and  mapped data
         * @param $data
         * @param array $dictionaries
         * @return array
         */
        function getCheckedRecordWithMapData($data, $all = true, &$dictionaries = [])
        {

            // patch $this->schema['props']['__model']['KeyId']
            if(isset($this->schema['props']['__model']['KeyId']) && !isset($data['KeyId'])) {
                unset($this->schema['props']['__model']['KeyId']);
            }

            //set $entity
            $entity = array_flip(array_keys($this->schema['props']['__model']));
            if (!is_array($data)) $data = [];

            // If there is not mapdata.. Use the model fields to mapdata
            if (!isset($this->schema['data']['mapData']) || !count($this->schema['data']['mapData'])) {
                foreach ($entity as $key => $foo) {
                    $this->schema['data']['mapData'][$key] = $key;
                }
            }


            // Explore the entity
            foreach ($entity as $key => $foo) {
                $key_exist = true;
                if (isset($this->schema['data']['mapData'][$key])) {
                    $array_index = explode('.', $this->schema['data']['mapData'][$key]); // Find potential . array separators
                    if (isset($data[$array_index[0]])) {
                        $value = $data[$array_index[0]];
                    } else {
                        $value = null;
                        $key_exist = false;
                    }
                    $value = (isset($data[$array_index[0]])) ? $data[$array_index[0]] : '';
                    // Explore potential subarrays
                    for ($i = 1, $tr = count($array_index); $i < $tr; $i++) {
                        if (isset($value[$array_index[$i]]))
                            $value = $value[$array_index[$i]];
                        else {
                            $key_exist = false;
                            $value = null;
                            break;
                        }
                    }
                    // Assign Value
                    $entity[$key] = $value;
                } else {
                    $key_exist = false;
                    $entity[$key] = null;
                }
                if (!$key_exist && !$all) unset($entity[$key]);

            }
            /* @var $dv DataValidation */
            $dv = $this->core->loadClass('DataValidation');
            if (!$dv->validateModel($this->schema['props']['__model'], $entity, $dictionaries, $all)) {
                $this->setError($this->entity_name . ': error validating Data in Model.: {' . $dv->field . '}. ' . $dv->errorMsg);
            }

            // recover Ids
            if (array_key_exists('KeyId', $data)) $entity['KeyId'] = $data['KeyId'];
            if (array_key_exists('KeyName', $data)) $entity['KeyName'] = $data['KeyName'];

            return ($entity);
        }

        function getFormModelWithMapData()
        {
            $entity = $this->schema['props']['__model'];
            foreach ($entity as $key => $attr) {
                if (!isset($attr['validation']))
                    unset($entity[$key]['validation']);
                elseif (strpos($attr['validation'], 'hidden') !== false)
                    unset($entity[$key]);
            }
            return ($entity);
        }

        function transformEntityInMapData($entity)
        {
            $map = (isset($this->schema['data']['mapData']))?$this->schema['data']['mapData']:null;
            $transform = [];


            if (!is_array($map)) $transform = $entity;
            else foreach ($map as $key => $item) {
                $array_index = explode('.', $item); // Find potental . array separators
                if (count($array_index) == 1) $transform[$array_index[0]] = (isset($entity[$key])) ? $entity[$key] : '';
                elseif (!isset($transform[$array_index[0]])) {
                    $transform[$array_index[0]] = [];
                }

                for ($i = 1, $tr = count($array_index); $i < $tr; $i++) {
                    $transform[$array_index[0]][$array_index[$i]] = (isset($entity[$key])) ? $entity[$key] : '';
                }

            }
            return $transform;
        }

        /**
         * fetchOne, fetchAll, fetchLimit call to fetch($type)
         * @param string $fields
         * @param null $where
         * @param null $order
         * @return array|bool
         */
        function fetchOne($fields = '*', $where = null, $order = null)
        {
            return $this->fetch('one', $fields, $where, $order);
        }
        function fetchAll($fields = '*', $where = null, $order = null)
        {
            return $this->fetch('all', $fields, $where, $order, null);
        }
        function fetchLimit($fields = '*', $where = null, $order = null, $limit = null)
        {
            if (!strlen($limit)) $limit = 100;
            else $limit = intval($limit);
            return $this->fetch('all', $fields, $where, $order, $limit);
        }

        /**
         * Execute a Datastore Query taking the following parameters
         * @param string $type
         * @param string $fields
         * @param null $where
         * @param null $order
         * @param null $limit
         * @return array|false|void
         * @throws Exception
         */
        function fetch($type = 'one', $fields = '*', $where = null, $order = null, $limit = null)
        {

            //region VERIFY $this->error is false and init $time for Performance
            //If the class has any error just return
            if ($this->error) return false;

            // Performance microtime
            $time = microtime(true);
            //endregion

            //region VERIFY and SANETIZE params: $type , $fields, $where, $order, $limit
            if(!is_string($type)) return($this->addError('fetch($type = "one", $fields = "*", $where = null, $order = null, $limit = null) has received $type as not string'));
            if(!is_string($fields)) return($this->addError('fetch($type = "one", $fields = "*", $where = null, $order = null, $limit = null) has received $fields as not string'));
            if(is_array($order))  $order = implode(', ',$order);
            if($limit) $limit = intval($limit);
            //endregion

            //region INIT Performance
            $this->core->__p->add('fetch: '.$this->entity_name, $type . ' fields:' . $fields . ' where:' . $this->core->jsonEncode($where) . ' order:' . $order . ' limit:' . $limit, 'note');
            //endregion

            //region INIT $_q, ret and UPDATE  $limit, $where, $order
            $ret = [];
            if (!is_string($fields) || !strlen($fields)) $fields = '*';
            if (!strlen($limit??'')) $limit = $this->limit;
            if ($type=='one') $limit = 1;

            $bindings=[];
            $_q = 'SELECT ' . $fields . ' FROM ' . $this->entity_name;
            // Where construction
            if (is_array($where)) {
                $i = 0;
                foreach ($where as $key => $value) {

                    //region SET $comp,$fieldname and EVALUATE comparators in $key
                    //assign default comparator
                    $comp = '=';

                    //evaluate if there is any comparator in $key
                    if (is_string($key) && preg_match('/[=><]/', $key)) {
                        unset($where[$key]);
                        if (strpos($key, '>=') === 0 || strpos($key, '<=') === 0 || strpos($key, '!=') === 0) {
                            $comp = substr($key, 0, 2);
                            $key = trim(substr($key, 2));
                        } else {
                            $comp = substr($key, 0, 1);
                            $key = trim(substr($key, 1));
                        }

                        if (!array_key_exists($key, $where)) {
                            $idkey = null;
                            $where[$key . $idkey] = $value;
                        } else {
                            $idkey = "_2";
                            $where[$key . $idkey] = $value;

                        }
                    }
                    else {
                            $idkey = null;
                    }

                    //assign original $fieldname from $key
                    $fieldname = $key;
                    //endregion

                    //region EVALUATE values for type ['date', 'datetime', 'datetimeiso']
                    // In the WHERE Conditions we have to transform date formats into date objects.
                    // SELECT * FROM PremiumContracts WHERE PremiumStartDate >= DATETIME("2020-03-01T00:00:00z") AND PremiumStartDate <= DATETIME("2020-03-01T23:59:59z")
                    if (array_key_exists($key, $this->schema['props'])
                        && in_array($this->schema['props'][$key][1], ['date', 'datetime', 'datetimeiso'])) {

                        if($value===null || $value=='__null__') $value = null;
                        elseif(stripos($value,'now')!==false) $value = str_ireplace('now',date('Y-m-d'),$value);
                        elseif($value=='__notnull__') {
                            $value = '>0001-01-01 00:00:00';
                        }

                        if (is_string($value) && $value && preg_match('/[=><]/', $value)) {
                            if (strpos($value, '>=') === 0 || strpos($value, '<=') === 0) {
                                $comp = substr($value, 0, 2);
                                $value=substr($value,2);
                            }
                            else {
                                $comp = substr($value, 0, 1);
                                $value=substr($value,1);
                            }

                        }

                        // Allow Smart date ranges where comp = '='
                        if($comp=='=' && $value) {
                            //apply filters
                            if(strpos($value,'/')===false) {
                                $from = $value;
                                $to = $value;
                            } else {
                                list($from,$to) = explode("/",$value,2);
                            }

                            if(strlen($from) == 4) {
                                $from.='-01-01 00:00:00';
                            } elseif(strlen($from) == 7) {
                                $from.='-01 00:00:00';
                            } elseif(strlen($from) == 10) {
                                $from.=' 00:00:00';
                            }

                            if(strlen($to) == 4) {
                                $to.='-12-31 23:59:59';
                            } elseif(strlen($to) == 7) {
                                list($year,$month) = explode("-",$to,2);
                                if(!in_array($month,['04','06','09','11'])) {
                                    $to.='-30 23:59:59';
                                }elseif($month=='02') {
                                    //TODO Años bisiestos.
                                    $to.='-28 23:59:59';
                                } else {
                                    $to.='-31 23:59:59';
                                }

                            } elseif(strlen($to) == 10) {
                                $to.=' 23:59:59';
                            }

                            $bindings[$key.'_from']=new DateTime($from);
                            $where[$key] = new DateTime($to);

                            if ($i == 0) $_q .= " WHERE $fieldname >= @{$key}_from AND $fieldname <= @{$key}";
                            else $_q .= " AND $fieldname >= @{$key}_from AND $fieldname <= @{$key}";

                            //assign $order to avoid conflicts
                            $order="{$fieldname} DESC";
                        }
                        else {
                            $where[$key] = ($value)?new DateTime($value):null;
                            if ($i == 0) $_q .= " WHERE $fieldname {$comp} @{$key}";
                            else $_q .= " AND $fieldname {$comp} @{$key}";

                        }
                    }
                    //endregion

                    //region EVALUATE other field types
                    else {

                        //region IF $value starts with '>=<' use it as $comp
                        if (is_string($value) && preg_match('/[=><]/', $value)) {
                            if (strpos($value, '>=') === 0 || strpos($value, '<=') === 0) {
                                $comp = substr($value, 0, 2);
                                $value=substr($value,2);
                            }
                            else {
                                $comp = substr($value, 0, 1);
                                $value=substr($value,1);
                            }
                        }
                        //endregion

                        //region IF $value is string but field is integer or float transform value into integer or float
                        if (is_string($value??'') && in_array(($this->schema['props'][$key][1]??''), ['integer','float'])) {

                            if($value=='__null__' || $value===null) $where[$key] = null;
                            elseif($value=='__notnull__') {
                                if ($i == 0) $_q .= " WHERE $fieldname <= @{$key}";
                                else $_q .= " AND $fieldname <= @{$key}";

                                if($this->schema['props'][$key][1]  == 'integer')
                                    $where[$key] = 9223372036854775807;
                                else
                                    $where[$key] = 9223372036854775807.00;

                                $i++;
                                $bindings[$key]=$where[$key];
                                continue;
                            }
                            else {
                                if($this->schema['props'][$key][1]  == 'integer')
                                    $where[$key] = $value = intval($value);
                                else
                                    $where[$key] = $value = floatval($value);
                            }
                        }
                        //endregion

                        //region IF SPECIAL SEARCH for values ending in % let's emulate a like string search
                        if($value!==null && is_string($value) && preg_match('/\%$/',$value) && strlen(trim($value))>1) {
                            if($order && strpos($order,$key)===false) $order = "{$key},{$order}";
                            $value = preg_replace('/\%$/','',$value);
                            $bindings[$key.'_from']=$value;
                            if ($i == 0) $_q .= " WHERE $fieldname >= @{$key}_from AND $fieldname <= @{$key}_to";
                            else $_q .= " AND $fieldname >= @{$key}_from AND $fieldname < @{$key}_to";
                            $key .= '_to';
                            $value_to = $value.'z';  //122 = z
                            $where[$key] = $value_to;

                        }
                        //endregion
                        //region IF value is __null__ or __notnull__
                        elseif($value!==null && is_string($value) && in_array($value,['__null__','__notnull__'])) {
                            if($value=='__null__') {
                                if ($i == 0) $_q .= " WHERE $fieldname = @{$key}";
                                else $_q .= " AND $fieldname = @{$key}";
                                $where[$key] = $value = null;
                            }
                            else {
                                if ($i == 0) $_q .= " WHERE $fieldname >= @{$key}";
                                else $_q .= " AND $fieldname >= @{$key}";
                                $where[$key] = ' ';
                            }
                        }
                        //endregion
                        //region IF field is 'list' and there is any ',' in $value use it as a separator of AND values
                        elseif(is_string($value) && strpos($value,',') && ($this->schema['props'][$key][1]??'')=='list') {
                            $values = explode(",",$value);
                            $_sub_q='';
                            foreach ($values as $_value) if(is_string($_value) && ($_value = trim($_value))){

                                //add AND separator for multiple conditions
                                if($_sub_q) $_sub_q.= " AND ";

                                //evaluate to apply a query with %$ search
                                if(preg_match('/\%$/',$_value)) {
                                    $_value = preg_replace('/\%$/','',$_value);
                                    $_sub_q .="{$fieldname} >= '{$_value}' AND {$fieldname} <= '{$_value}z'";
                                }
                                // else apply simple query
                                else {
                                    //apply query condition as an equals of string
                                    $_sub_q .= "{$fieldname} = '{$_value}'";
                                }

                            }

                            if($_sub_q) {
                                if ($i == 0) $_q .= " WHERE ";
                                else $_q .= " AND ";
                                $_q.=$_sub_q;
                                $i++;
                            }
                            continue;
                        }
                        //endregion
                        elseif(is_array($value)) {

                            // continue on empty array
                            if(!$value) continue;

                            if(in_array(($this->schema['props'][$key][1]??''), ['integer','float']))
                                $values = implode(",",$value);
                            else
                                $values = "'".implode("','",$value)."'";

                            if ($i == 0) $_q .= " WHERE $fieldname IN ARRAY({$values})";
                            else $_q .= " AND $fieldname IN ARRAY ({$values})";

                            $i++;
                            continue;
                        }
                        //endregion
                        //region ELSE set normal to search
                        else {
                            $key = $key . $idkey;
                            if ($i == 0) $_q .= " WHERE $fieldname {$comp} @{$key}";
                            else $_q .= " AND $fieldname {$comp} @{$key}";
                        }
                        //endregion
                    }
                    //endregion

                    $i++;
                    $bindings[$key]=$where[$key];
                }
            } elseif (strlen($where??'')) {
                $_q .= " WHERE $where";
                $where = null;
            }
            if (strlen($order??'')) $_q .= " ORDER BY $order";
            //endregion

            //region apply limit. 200 by default
            if (intval($limit)>0) {
                $_q .= " LIMIT @limit";
                $bindings['limit'] = intval($limit);
            } else {
                $this->limit = 200;
                $_q .= " LIMIT @limit";
                $bindings['limit'] = $this->limit;
            }
            //endregion

            //region ADD to $_q $this->cursosr and UPDATE $this->lastQuery
            if ($this->cursor) {
                $_q .= " OFFSET @offset";
                $bindings['offset'] = $this->datastore->cursor(base64_decode($this->cursor)); // cursor has had to be previously encoded
            }
            $this->lastQuery = $_q . ' /  bindings=' .  $this->core->jsonEncode($bindings) . ' / taking where=' . ((is_array($where)) ? ' ' . $this->core->jsonEncode($where) : '') ;
            //endregion

            //region EXECUTE QUERY into $result
            try {
                $query = $this->datastore->gqlQuery($_q,['allowLiterals'=>true,'bindings'=>$bindings]);
                $result = $this->datastore->runQuery($query,['namespaceId'=>$this->namespace]);
            } catch (Exception $e) {
                $this->setError([__LINE__.':$this->datastore->runQuery',$e->getMessage()]);
                $this->addError('fetch');
            }
            //endregion

            //region PROCESS $result into $ret
            if(!$this->error) {
                try {
                    $ret = $this->transformResult($result);
                    if ($this->debug)
                        $this->core->logs->add($this->entity_name . ".fetch({$this->lastQuery}) [" . (round(microtime(true) - $time, 4)) . " secs]", 'DataStore');
                } catch (Exception $e) {
                    $this->setError([__LINE__ . ':$this->transformResult', $e->getMessage()]);
                    $this->addError('fetch');
                }
            }
            //endregion

            //region END performance
            $this->core->__p->add('fetch: '.$this->entity_name, '', 'endnote');
            //endregion
            return $ret;
        }

        /**
         * Return entities by Keys
         * @param array|string $keys  if string can be values separated by ','. If array can be an array of string,integer elements
         * @return array
         */
        function fetchByKeys($keys)
        {

            //Validate $keys
            if(!$keys) return $this->addError('fetchByKeys($keys) has received an empty value');
            if(is_string($keys)) $keys = array_map('trim',explode(',',$keys));

            //Analyze execution time
            $time = microtime(true);

            //Global performance
            $this->core->__p->add('ds:fetchByKeys: '.$this->entity_name,  ' keys:' . $this->core->jsonEncode($keys),'note');
            $ret = [];
            $entities_keys = [];
            try {
                foreach ($keys as $key) {
                    // force type TYPE_NAME if there is a field KeyName
                    if(isset($this->schema['data']['model']['KeyName'])) {
                        $entities_keys[] = $this->datastore->key($this->entity_name, strval($key),['identifierType' => Key::TYPE_NAME,'namespaceId'=>$this->namespace]);
                    } else {
                        $entities_keys[] = $this->datastore->key($this->entity_name, $key,['namespaceId'=>$this->namespace]);
                    }
                }

                //TODO: verify there is no more than 1000 keys to split the work in different loops
                $result = $this->datastore->lookupBatch($entities_keys);

                // $result['found'] is an array of entities.
                if (isset($result['found'])) {
                    $ret = $this->transformResult($result['found']);

                if($this->debug)
                    $this->core->logs->add($this->entity_name.".fetchByKeys('\$key=".(json_encode($keys))."') [".(round(microtime(true)-$time,4))." secs]",'DataStore');

                } else {
                    $this->core->__p->add('ds:fetchByKeys: '.$this->entity_name,  '','endnote');
                    return([]);
                }
            } catch (Exception $e) {
                $this->setError($e->getMessage());
                $this->addError('query');
            }
            $this->core->__p->add('ds:fetchByKeys: '.$this->entity_name,  '','endnote');
            return $ret;
        }

        /**
         * Return entities by key
         * @param integer|string $key
         * @return array|void with the element
         */
        function fetchOneByKey($key)
        {
            if(!$key || (!is_string($key) && !is_numeric($key)) || !strlen($key)) return;
            //Analyze execution time
            $time = microtime(true);
            $this->core->__p->add('ds:fetchOneByKey: '.$this->entity_name,  ' key:' . $key,'note');
            try {


                // force type TYPE_NAME if there is a field KeyName
                if(isset($this->schema['data']['model']['KeyName'])) {
                    $key_entity = $this->datastore->key($this->entity_name, $key,['identifierType' => Key::TYPE_NAME,'namespaceId'=>$this->namespace]);
                } else {
                    $key_entity = $this->datastore->key($this->entity_name, $key,['namespaceId'=>$this->namespace]);
                }


                $result = $this->datastore->lookup($key_entity);

                // $result['found'] is an array of entities.
                if ($result) {
                    $result = [$result];
                    $result = $this->transformResult($result)[0];
                    $this->core->__p->add('ds:fetchOneByKey: '.$this->entity_name,  '','endnote');
                } else {
                    $this->core->__p->add('ds:fetchOneByKey: '.$this->entity_name,  '','endnote');
                }

                if($this->debug)
                    $this->core->logs->add($this->entity_name.".fetchOneByKey('\$key=$key') [".(round(microtime(true)-$time,4))." secs]",'DataStore');

                return($result??[]);
            } catch (Exception $e) {
                $this->setError($e->getMessage());
                $this->addError('query');
            }
            $this->core->__p->add('ds:fetchOneByKey: '.$this->entity_name,  '','endnote');

        }

        /**
         * Transform Result of a query
         * @param $result
         * @return array|void
         */
        private function transformResult(&$result) {

            if(!is_array($result) && !is_object($result)) return;

            $ret = [];
            $i=0;
            set_error_handler("__warning_handler_datastore", E_WARNING);
            //            if(is_object($result) && class_basename($result)=='EntityIterator') {
            //                /** @var EntityIterator $result */
            //                $entty =$result->current();
            //                _printe(class_basename($result),$entty);
            //
            //            }
            // Security control to avoid more than 10K entities
            if($this->limit>10000) $this->limit=10000;
            try {

                /** @var Google\Cloud\Datastore\Entity $entity */
                foreach ($result as $entity) {

                    // assure we do not return more than $this->limit records.
                    if($this->limit && $i++>=$this->limit) break;

                    $row =  $entity->get();
                    $this->last_cursor = ($entity->cursor())?base64_encode($entity->cursor()):null;
                    if(isset($entity->key()->path()[0]['id'])) {
                        $row['KeyId'] = $entity->key()->path()[0]['id'];
                    } else {
                        $row['KeyName'] = $entity->key()->path()[0]['name'];
                    }

                    //set TimeZone to convert date objects
                    $tz = new DateTimeZone($this->default_time_zone_to_read);

                    // Transform result
                    if($this->transformReadedEntities)
                        foreach ($row as $key => $value) {
                            // Update Types: Geppoint, JSON, Datetime
                            if (!isset($this->schema['props'][$key]))
                                $row[$key] = $value;
                            elseif ($value instanceof Geopoint)
                                $row[$key] = $value->getLatitude() . ',' . $value->getLongitude();
                            elseif ($this->schema['props'][$key][1] == 'json') {
                                // if is_array($value) then it is an EMBEDED ENTITY
                                if(is_array($value)) $row[$key] = $value;
                                else $row[$key] = json_decode($value, true);
                            }elseif ($this->schema['props'][$key][1] == 'zip') {
                                if($value)
                                    $row[$key] = (mb_detect_encoding($value) == "UTF-8") ? gzuncompress(mb_convert_encoding($value, 'ISO-8859-1', 'UTF-8')) : $value;
                                else
                                    $row[$key] = null;
                            } elseif ($this->schema['props'][$key][1] == 'jsonzip') {
                                if($value) {
                                    $row[$key] = (mb_detect_encoding($value) == "UTF-8") ? gzuncompress(mb_convert_encoding($value, 'ISO-8859-1', 'UTF-8')) : $value;
                                    if(is_array($value)) $row[$key] = $value;
                                    else $row[$key] = json_decode($row[$key], true);
                                } else
                                    $row[$key] = null;

                            }elseif ($this->schema['props'][$key][1] == 'txt')
                                $row[$key] = $value;
                            elseif ($value instanceof DateTimeImmutable) {
                                // Change timezone of the object
                                if($tz) $value = $value->setTimezone($tz)??$value;
                                if ($this->schema['props'][$key][1] == 'date') {
                                    $row[$key] = $value->format('Y-m-d');
                                } elseif ($this->schema['props'][$key][1] == 'datetime')
                                    $row[$key] = $value->format('Y-m-d H:i:s');
                                elseif ($this->schema['props'][$key][1] == 'datetimeiso')
                                    $row[$key] = $value->format('c');

                            } elseif ($this->schema['props'][$key][1] == 'integer')
                                $row[$key] = intval($value);
                            elseif ($this->schema['props'][$key][1] == 'float')
                                $row[$key] = floatval($value);
                            elseif ($this->schema['props'][$key][1] == 'datetime' && is_numeric($value) && $value) {
                                $row[$key] = date('Y-m-d H:i:s',(int)($value/1000000));
                            }
//                    elseif ($this->schema['props'][$key][1] == 'boolean')
//                        $row[$key] = ($value)?true:false;
                        }
                    $ret[] = $row;
                }
            } catch (TypeError $e) {
                //DO NONTING: google/cloud-datastore/src/Operation.php has an error in google/cloud-datastore/src/Operation.php line 527 allowing to pass a null into a count function
            }
            restore_error_handler();

            return $ret;
        }

        /**
         * Execute a Datastore aggregation sum Query over $field
         *  * https://cloud.google.com/datastore/docs/aggregation-queries
         * @param string $field
         * @param array $where
         * @return integer|null If error it will return null
         */
        function sum(string $field , array $where = [])
        {

            //If the class has any error just return
            if ($this->error) return false;

            // Performance microtime
            $time = microtime(true);
            $this->core->__p->add('sum: '.$this->entity_name,  ' field:' . $field . ' where:' . $this->core->jsonEncode($where) , 'note');
            $ret = null;
            try {
                $query = $this->datastore->query();
                $query->kind($this->entity_name);

                $bindings=[];
                // Where construction
                if (is_array($where)) $this->processWhere($where,$query);
                $this->lastQuery = "sum({$field}) where ".json_encode($query->queryObject());

                //region RUN $query->aggregation(Aggregation::sum($field)->alias('total')); AND return result
                $_q = json_encode($query->queryObject());
                $aggregationQuery = $query->aggregation(Aggregation::sum($field)->alias('total'));
                /** @var Google\Cloud\Datastore\Query\AggregationQueryResult $res */
                $res = $this->datastore->runAggregationQuery($aggregationQuery,['namespaceId'=>$this->namespace]);
                if($this->debug)
                    $this->core->logs->add($this->entity_name.".sum({$field}) [".(round(microtime(true)-$time,4))." secs]",'DataStore');
                $ret=$res->get('total');
                //endregion
            }
            catch (Exception $e) {
                $this->lastQuery = "SELECT sum({$field}) where ".json_encode($where);
                $this->setError($e->getMessage());
                $this->addError('fetch');
            }
            $this->core->__p->add('sum: '.$this->entity_name, '', 'endnote');
            return $ret;
        }


        /**
         * Execute a Datastore aggregation avg Query over $field
         *  * https://cloud.google.com/datastore/docs/aggregation-queries
         * @param string $field
         * @param array $where
         * @return integer|null If error it will return null
         */
        function avg(string $field , array $where = [])
        {

            //If the class has any error just return
            if ($this->error) return false;

            // Performance microtime
            $time = microtime(true);
            $this->core->__p->add('avg: '.$this->entity_name,  ' field:' . $field . ' where:' . $this->core->jsonEncode($where) , 'note');
            $ret = null;
            try {
                $query = $this->datastore->query();
                $query->kind($this->entity_name);

                $bindings=[];
                // Where construction
                if (is_array($where)) $this->processWhere($where,$query);
                $this->lastQuery = "SELECT avg({$field}) where ".json_encode($query->queryObject());

                //region RUN $query->aggregation(Aggregation::sum($field)->alias('total')); AND return result
                $_q = json_encode($query->queryObject());
                $aggregationQuery = $query->aggregation(Aggregation::avg($field)->alias('total'));
                /** @var Google\Cloud\Datastore\Query\AggregationQueryResult $res */
                $res = $this->datastore->runAggregationQuery($aggregationQuery,['namespaceId'=>$this->namespace]);
                if($this->debug)
                    $this->core->logs->add($this->entity_name.".avg({$field}) [".(round(microtime(true)-$time,4))." secs]",'DataStore');
                $ret=$res->get('total');
                //endregion
            }
            catch (Exception $e) {
                $this->lastQuery = "SELECT avg({$field}) where ".json_encode($where);
                $this->setError($e->getMessage());
                $this->addError('fetch');
            }
            $this->core->__p->add('avg: '.$this->entity_name, '', 'endnote');
            return $ret;
        }

        /**
         * Execute a Datastore aggregation sum Query over $field
         *  * https://cloud.google.com/datastore/docs/aggregation-queries
         *  * https://cloud.google.com/php/docs/reference/cloud-datastore/1.22.1/Query.Aggregation
         * @param string $field
         * @param array $where
         * @return integer|null If error it will return null
         */
        function count(array $where = [])
        {

            //If the class has any error just return
            if ($this->error) return false;

            // Performance microtime
            $time = microtime(true);
            $this->core->__p->add('count: '.$this->entity_name,  ' where:' . $this->core->jsonEncode($where) , 'note');
            $ret = null;
            try {
                $query = $this->datastore->query();
                $query->kind($this->entity_name);

                $bindings=[];
                // Where construction
                if (is_array($where)) $this->processWhere($where,$query);
                $this->lastQuery = "SELECT count(*) where ".json_encode($query->queryObject());

                //region RUN $query->aggregation(Aggregation::sum($field)->alias('total')); AND return result
                $_q = json_encode($query->queryObject());
                $aggregationQuery = $query->aggregation(Aggregation::count()->alias('total'));
                /** @var Google\Cloud\Datastore\Query\AggregationQueryResult $res */
                $res = $this->datastore->runAggregationQuery($aggregationQuery,['namespaceId'=>$this->namespace]);
                $ret=$res->get('total');
                if($this->debug)
                    $this->core->logs->add($this->entity_name.".count({$this->lastQuery}) = {$ret} [".(round(microtime(true)-$time,4))." secs]",'DataStore');
                //endregion
            }
            catch (Exception $e) {
                $this->lastQuery = "SELECT count(*) where ".json_encode($where);
                $this->setError($e->getMessage());
                $this->addError('fetch');
            }
            $this->core->__p->add('count: '.$this->entity_name, '', 'endnote');
            return $ret;
        }

        /**
         * Process $where conditions for count,sum,avg
         * @param array $where
         * @param Query $query
         * @return void
         */
        private function processWhere(array &$where,Query &$query) {
            if ($where) {
                $i = 0;
                foreach ($where as $key => $value) {
                    $comp = '=';

                    if (is_string($key) && preg_match('/[=><]/', $key)) {
                        unset($where[$key]);
                        if (strpos($key, '>=') === 0 || strpos($key, '<=') === 0 || strpos($key, '!=') === 0) {
                            $comp = substr($key, 0, 2);
                            $key = trim(substr($key, 2));
                        } else {
                            $comp = substr($key, 0, 1);
                            $key = trim(substr($key, 1));
                        }

                        if (!array_key_exists($key, $where)) {
                            $idkey = null;
                            $where[$key . $idkey] = $value;
                        } else {
                            $idkey = "_2";
                            $where[$key . $idkey] = $value;

                        }
                    }
                    else {
                        $idkey = null;
                    }
                    $fieldname = $key;

                    // In the WHERE Conditions we have to transform date formats into date objects.
                    // SELECT * FROM PremiumContracts WHERE PremiumStartDate >= DATETIME("2020-03-01T00:00:00z") AND PremiumStartDate <= DATETIME("2020-03-01T23:59:59z")
                    if (array_key_exists($key, $this->schema['props'])
                        && in_array($this->schema['props'][$key][1], ['date', 'datetime', 'datetimeiso']))
                    {

                        if(stripos($value,'now')!==false) $value = str_ireplace('now',date('Y-m-d'),$value);
                        if (is_string($value) && preg_match('/[=><]/', $value)) {
                            if (strpos($value, '>=') === 0 || strpos($value, '<=') === 0) {
                                $comp = substr($value, 0, 2);
                                $value=substr($value,2);
                            }
                            else {
                                $comp = substr($value, 0, 1);
                                $value=substr($value,1);
                            }

                        }

                        // Allow Smart date ranges where comp = '='
                        if($comp=='=' && $value) {
                            //apply filters
                            if(strpos($value,'/')===false) {
                                $from = $value;
                                $to = $value;
                            } else {
                                list($from,$to) = explode("/",$value,2);
                            }
                            if(strlen($from) == 4) {
                                $from.='-01-01 00:00:00';
                            } elseif(strlen($from) == 7) {
                                $from.='-01 00:00:00';
                            } elseif(strlen($from) == 10) {
                                $from.=' 00:00:00';
                            }
                            if(strlen($to) == 4) {
                                $to.='-12-31 23:59:59';
                            } elseif(strlen($to) == 7) {
                                $to = date("Y-m-t 23:59:59", strtotime($to.'-01'));
                            } elseif(strlen($to) == 10) {
                                $to.=' 23:59:59';
                            }
                            $query->filter($fieldname,'>=',new DateTime($from));
                            $query->filter($fieldname,'<=',new DateTime($to));
                        }
                        else {
                            if($value)
                                $query->filter($fieldname,$comp,new DateTime($value));
                        }
                    }
                    else {

                        //region IF $value starts with '>=<' use it as $comp
                        if (is_string($value) && preg_match('/[=><]/', $value)) {
                            if (strpos($value, '>=') === 0 || strpos($value, '<=') === 0) {
                                $comp = substr($value, 0, 2);
                                $value=substr($value,2);
                            }
                            else {
                                $comp = substr($value, 0, 1);
                                $value=substr($value,1);
                            }
                        }
                        //endregion

                        //region IF $value is string but field is integer or float transform value into integer or float
                        if (is_string($value??'')
                            && array_key_exists($key, $this->schema['props'])
                            && in_array($this->schema['props'][$key][1], ['integer','float'])) {

                            if($value=='__null__' || $value===null) {
                                $comp='=';
                                $where[$key] = $value =  null;
                            }
                            elseif($value=='__notnull__') {
                                $comp='<=';
                                if($this->schema['props'][$key][1]  == 'integer')
                                    $where[$key] = $value = 9223372036854775807;
                                else
                                    $where[$key] = $value = 9223372036854775807.00;
                            }
                            else {
                                if($this->schema['props'][$key][1]  == 'integer')
                                    $where[$key] = $value = intval($value);
                                else
                                    $where[$key] = $value = floatval($value);
                            }

                        }
                        //endregion


                        //region IF SPECIAL SEARCH for values ending in % let's emulate a like string search
                        if(is_string($value) && preg_match('/\%$/',$value) && strlen(trim($value))>1) {
                            $value = preg_replace('/\%$/','',$value);
                            $query->filter($fieldname,'>=',$value);
                            $value_to = $value.'z';  //122 = z
                            $query->filter($fieldname,'<=',$value_to);
                        }
                        //endregion
                        //region IF value is ARRAY and the type is not list convert it into IN ARRAY
                        elseif(is_array($value)){
                            // Generate and AND condition for list
                            foreach ($value as $item) {
                                $query->filter($fieldname,'=',$item);
                            }
                            //$query->filter($fieldname,'IN',$value);
                        }
                        //endregion
                        //region ELSE set normal to search
                        else {
                            $query->filter($fieldname,$comp,$value);
                        }
                        //endregion
                    }
                }
            }
        }


        /**
         * Assign cache $result based on $key
         * @param $key
         * @param $result
         */
        function setCache($key, $result)
        {
            if(!$this->useCache) return;
            if ($this->cache === null) $this->initCache();
            $this->cache_data[$key] = gzcompress(serialize($result));
            $this->cache->set($this->entity_name . '_' . $this->namespace, $this->cache_data,null, $this->cacheSecretKey, $this->cacheSecretIV);
        }

        /**
         * Return a cache key previously set
         * @param $key
         * @return mixed|null
         */
        function getCache($key)
        {
            if(!$this->useCache) return;
            if ($this->cache === null) $this->initCache();
            if (isset($this->cache_data[$key])) {
                return (unserialize(gzuncompress($this->cache_data[$key])));
            } else {
                return null;
            }
        }

        /**
         * Reset the cache.
         * @param $key
         * @param $result
         */
        function resetCache()
        {
            if ($this->cache === null) $this->cache = new CoreCache($this->core,'CF_DATASTORE');
            $this->cache_data = [];
            $this->cache->delete($this->entity_name . '_' . $this->namespace);
        }

        /**
         * Init cache of the object
         */
        function initCache()
        {
            if(!$this->useCache) return;
            if ($this->cache === null) $this->cache = new CoreCache($this->core,'CF_DATASTORE');
            $this->cache_data = $this->cache->get($this->entity_name . '_' . $this->namespace,-1,'',$this->cacheSecretKey, $this->cacheSecretIV);
            if (!is_array($this->cache_data)) $this->cache_data = [];
        }

        /**
         * Returns the number of records of the entity based on a condition.
         * Uses cache to return results
         * @param null $where
         * @param string $distinct
         * @return int|mixed|null
         */
        function fetchCount($where = null, $distinct = '__key__')
        {
            $hash = sha1($this->core->jsonEncode($where) . $distinct);
            $total = $this->getCache('total_' . $hash);
            if ($total === null) {
                $data = $this->fetchAll($distinct, $where);
                if(is_array($data)) {
                    $total = count($data);
                    $this->setCache('total_' . $hash, $total);
                }
            }
            return $total;
        }

        /**
         * Delete using a where condition
         * @param $where
         * @return array|bool|void
         */
        function delete($where)
        {
            $ret = $this->fetchAll('__key__',$where);
            $keys = [];
            if($ret) {
                foreach ($ret as $item) if(isset($item['KeyId']) || isset($item['KeyName'])) {
                    $keys[] = (isset($item['KeyId']))?$item['KeyId']:$item['KeyName'];
                }
                if($keys) {
                    // Performance microtime
                    $time = microtime(true);
                    $delete = $this->deleteByKeys($keys);
                    if($this->debug)
                        $this->core->logs->add($this->entity_name.".delete('".(implode(',',$keys))."') [".(round(microtime(true)-$time,4))." secs]",'DataStore');
                }

            }
            $this->resetCache(); // Delete Cache for next queries..
            return $ret;
        }

        /**
         * Delete receiving Keys
         * @param $keys array|string if string can be values separated by ','. If array can be an array of string,integer elements
         * @return array|void
         */
        function deleteByKeys($keys)
        {
            // If not $keys return
            if(!$keys) return;

            // Performance microtime
            $time = microtime(true);

            //Convert into an array
            if (!is_array($keys)) $keys = explode(',', $keys);
            $entities_keys = [];
            try {
                foreach ($keys as $key) {
                    // force type TYPE_NAME if there is a field KeyName
                    if(isset($this->schema['data']['model']['KeyName'])) {
                        $entities_keys[] = $this->datastore->key($this->entity_name, $key,['identifierType' => Key::TYPE_NAME,'namespaceId'=>$this->namespace]);
                    } else {
                        $entities_keys[] = $this->datastore->key($this->entity_name, $key,['namespaceId'=>$this->namespace]);
                    }
                }
                $ret = $this->datastore->deleteBatch($entities_keys);
                if($this->debug)
                    $this->core->logs->add($this->entity_name.".deleteByKeys('".(implode(',',$keys))."') [".(round(microtime(true)-$time,4))." secs]",'DataStore');

            } catch (Exception $e) {
                $this->setError($e->getMessage());
                $this->addError('query');
            }
            $this->resetCache(); // Delete Cache for next queries..
            return $ret;
        }

        /**
         * Execute a Manual Query
         * @param $_q
         * @param $bindings
         * @return array|void
         */
        function query($_q, $bindings=[])
        {
            // Performance microtime
            $time = microtime(true);
            $ret = [];
            $this->lastQuery = $_q . ' /  bindings=' .  $this->core->jsonEncode($bindings)  ;
            try {
                $query = $this->datastore->gqlQuery($_q,['allowLiterals'=>true,'bindings'=>$bindings]);
                $result = $this->datastore->runQuery($query,['namespaceId'=>$this->namespace]);
                $ret = $this->transformResult($result);
                if($this->debug)
                    $this->core->logs->add($this->entity_name.".query(\$query='{$_q}') [".(round(microtime(true)-$time,4))." secs]",'DataStore');

            } catch (Exception $e) {
                $this->setError($e->getMessage());
                $this->addError('fetch');
            }
            return $ret;
        }

        /**
         * Set an error message and add it to the error messages array
         *
         * @param mixed $value The error message to be added.
         *
         * @return boolean Always returns false to facilitate return value of the caller
         */
        function setError($value): bool
        {
            $this->errorMsg = [];
            return $this->addError($value);
        }

        /**
         * Add an error message to the class instance and set error flag to true.
         *
         * @param mixed $value The error message to be added.
         * @return boolean Always returns false to facilitate return value of the caller
         */
        function addError($value): bool
        {
            $this->error = true;
            $this->errorMsg[] = $value;
            $this->core->errors->add(['DataStore' => $value]);
            return false;
        }
    }
}
