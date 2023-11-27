<?php


use Google\Cloud\BigQuery\BigQueryClient;
use Google\Cloud\BigQuery\Timestamp;
use Google\Cloud\Core\ExponentialBackoff;

if (!defined ("_DATABQCLIENT_CLASS_") ) {
    define("_DATABQCLIENT_CLASS_", TRUE);

    /**
     * [$bq = $this->core->loadClass('DataBQ');]  Class to handle Bigquery datasets:tables with CloudFramework models
     *
     * https://cloud.google.com/bigquery/docs/reference/libraries#client-libraries-install-php
     * @package CoreClasses
     */
    class DataBQ
    {
        var $core = null;                   // Core7 reference
        var $project_id = null;             // project_id
        var $_version = '20230905';
        /** @var BigQueryClient|null  */
        var $client = null;                 // BQ Client
        // table to write Data
        var $dataset_name = null;           //dataset name to be used
        var $table_name = null;             //table name to be used
        /** @var \Google\Cloud\BigQuery\Dataset $dataset */
        var $dataset=null;                  // Dataset apply data
        /** @var \Google\Cloud\BigQuery\Table $table */
        var $table=null;
        var $key = null;

        var $error = false;
        var $errorMsg = [];
        var $options = [];
        var $entity_schema = null;
        var $fields = [];
        var $mapping = [];
        private $use_mapping = false;
        var $limit = 0;
        var $page = 0;
        var $offset = 0;
        var $order = '';
        var $_last_query=null;
        var $_last_query_time=0;
        var $_lastExecutionMicrotime = 0;
        var $_only_create_query = false;
        var $debug = false;
        private $joins = [];
        private $queryFields = '';
        private $queryWhere = [];
        private $extraWhere = '';
        private $virtualFields = [];
        private $groupBy = '';
        private $view = null;

        /**
         * @param Core7 $core
         * @param $params
         * [0] = dataset_name
         * [1] = cfo_schema
         * [3] = $options = [projectId, KeyFile,..]
         */
        function __construct(Core7 &$core, $params)
        {
            $this->core = $core;
            $this->core->__p->add('DataBQ new instance ', $params[0]??'', 'note');
            if($this->core->is->development()) $this->debug = true;

            //region INIT $this->dataset_name,$this->table_name if isset($params[0]) and strpos($params[0],'.')
            if(isset($params[0])){
                if(strpos($params[0],'.'))
                    list($this->dataset_name,$this->table_name) = explode('.',$params[0],2);
                else $this->dataset_name = $params[0];
            }
            //endregion

            //region SET $this->entity_schema if isset($params[1])
            if(isset($params[1])) $this->processSchema($params[1]);
            //endregion

            //region SET $this->options and read $params[2] if it exist
            $this->options = (isset($params[2]) && is_array($params[2])) ? $params[2] : [];
            $this->project_id = $this->core->gc_project_id;
            if(isset($this->options['projectId'])) $this->project_id = $this->options['projectId'];
            else $this->options['projectId'] = $this->project_id;
            //endregion

            //region SET $this->client and ($this->dataset, $this->table if $this->dataset_name and $this->table_name exist)
            try {
                if($this->options['keyFile']['project_id']??null) {
                    $this->project_id = $this->options['keyFile']['project_id'];
                    $this->options['projectId'] = $this->options['keyFile']['project_id'];
                }
                $this->client = new BigQueryClient($this->options);
                if($this->dataset_name) {
                    $this->dataset = $this->client->dataset($this->dataset_name);
                    if($this->table_name) {
                        $this->table =$this->dataset->table($this->table_name);
                    }
                }
            } catch (Exception $e) {
                return($this->addError($e->getMessage()));
            }
            //endregion

            $this->core->__p->add('DataBQ new instance ', '', 'endnote');
            return true;

        }

        /**
         * Process Schema
         * @param $schema
         * @return bool|void
         */
        private function processSchema($schema) {
            if(!is_array($schema)) return;
            $this->entity_schema = $schema;
            if(isset($this->entity_schema['model'])) foreach ($this->entity_schema['model'] as $field => $item) {
                if(in_array(strtolower($item[0]),['key','keyid','keyname']) || (isset($item[1]) && stripos($item[1],'isKey')!==false)) {
                    if($this->key) return($this->addError('There is two keys in the model: '.$this->key.','.$field));
                    $this->key = $field;
                }
                $this->fields[$field] = $item[0]??'string';
            }

            return true;
        }

        /**
         * Just a test query to know if the service works
         * @return array|void
         */
        function test() {

            $query = 'SELECT id, view_count FROM `bigquery-public-data.stackoverflow.posts_questions` limit 10';
            return ($this->_query($query));


        }

        /**
         * Define When I want to build a query but I do not want to be executed I call this method with true value
         * @param $boolean
         */
        function onlyCreateQuery($boolean) {$this->_only_create_query = $boolean;}


        /**
         * Return datasets associated to the project
         * @return array|void
         */
        public function getDataSets() {
            try {
                $datasets = $this->client->datasets();
                $ret = [];
                foreach ($datasets as $dataset) {
                    $ret[] = $dataset->id();
                }
                return $ret;
            } catch (Exception $e) {
                $error = json_decode($e->getMessage(),true);
                $this->addError(['bigquery'=>$error]);
            }
        }

        /**
         * Return datasets associated to the project
         * @return array|void
         */
        public function getDataSetInfo($dataset_name=null) {
            if(!$dataset_name) $dataset_name = $this->dataset_name;
            try {
                $dataset = null;
                if($dataset_name != $this->dataset_name || !is_object($this->dataset)) {
                    $dataset = $this->client->dataset($dataset_name);
                } else {
                    $dataset = &$this->dataset;
                }
                return $dataset->info();

            } catch (Exception $e) {
                $error = json_decode($e->getMessage(),true);
                $this->addError(['bigquery'=>$error]);
            }
        }

        /**
         * Return datasets associated to the project
         * @return array|void
         */
        public function getDataSetTables($dataset_name=null) {
            if(!$dataset_name) $dataset_name = $this->dataset_name;
            try {
                $dataset = null;
                if($dataset_name != $this->dataset_name) {
                    $dataset = $this->client->dataset($dataset_name);
                } else {
                    $dataset = &$this->dataset;
                }
                $tables = $dataset->tables();
                $ret = [];
                if($tables) foreach ($tables as $table) {
                    $ret[] = $table->id();
                }
                return $ret;
            } catch (Exception $e) {
                $error = json_decode($e->getMessage(),true);
                $this->addError(['bigquery'=>$error]);
            }
        }

        /**
         * Return datasets associated to the project
         * @return array|void
         */
        public function getDataSetTableInfo($dataset_name=null,$table_name=null) {
            if(!$dataset_name) $dataset_name = $this->dataset_name;
            if(!$table_name) $table_name = $this->table_name;
            try {
                $dataset = null;
                if($dataset_name != $this->dataset_name) {
                    $dataset = $this->client->dataset($dataset_name);
                    $table = $dataset->table($table_name);
                } else {
                    $dataset = &$this->dataset;
                    $table = null;
                    if($table_name != $this->table_name) {
                        $table = $dataset->table($table_name);
                    } else {
                        $table = &$this->table;
                    }
                }
                return ['table'=>$table->id(),'id'=>$this->project_id.':'.$dataset_name.':'.$table->id(),'query'=>'SELECT * FROM `'.$this->project_id.'.'.$dataset_name.'.'.$table->id().'` limit 10','info'=>$table->info()];

            } catch (Exception $e) {
                $error = json_decode($e->getMessage(),true);
                $this->addError(['bigquery'=>$error]);
            }
        }

        /**
         * Return datasets associated to the project
         * @return array|void
         */
        public function createDataSetTableInfo(array $fields, $dataset_name=null,$table_name=null) {
            if(!$dataset_name) $dataset_name = $this->dataset_name;
            if(!$table_name) $table_name = $this->table_name;
            try {
                $dataset = null;
                if($dataset_name != $this->dataset_name) {
                    $dataset = $this->client->dataset($dataset_name);
                    $table = $dataset->table($table_name);
                } else {
                    $dataset = &$this->dataset;
                }

                $table = $dataset->createTable($table_name, ['schema' => $fields]);
                return(['table'=>'`'.$this->project_id.'.'.$dataset_name.'.'.$table->id().'`','info'=>$table->info()]);

            } catch (Exception $e) {
                $error = json_decode($e->getMessage(),true);
                $this->addError(['bigquery'=>$error]);
            }
        }

        /**
         * Execute a Query with a title
         * @param $title
         * @param $_q
         * @param array $params
         * @return array|void
         */
        public function dbQuery($title,$_q,$params=[]) {
            return $this->_query($_q,$params);
        }
        public function query($title,$_q,$params=[]) {
            return $this->_query($_q,$params);
        }


        /**
         * Feed a table in Bigquery
         * @param $title
         * @param $table_id
         * @param $data
         * @return array|void
         */
        public function dbFeed($title,$table_id,&$data) {
            return $this->_feed($table_id,$data);
        }


        /**
         * Feed a table with $data
         * @param $table_id
         * @param $data
         * @return bool|void
         */
        private function _feed($table_id, &$data) {
            if(!is_object($this->dataset)) return($this->addError('_feed requires a dataset_name when you instances the class'));

            try {
                /** @var \Google\Cloud\BigQuery\Table $table */
                $table = $this->dataset->table($table_id);
                $table_id = $table->id();
            }  catch (Exception $e) {
                $error = json_decode($e->getMessage(),true);
                return($this->addError(['bigquery'=>$error]));
            }

            //region PREPARE $data
            $bq_data=[];
            foreach ($data as $i=>$datum) {
                $bq_data[] = ['data'=>&$data[$i]];
            }
            //endregion

            try {
                $insertResponse = $table->insertRows($bq_data);
                if (!$insertResponse->isSuccessful()) {
                    foreach ($insertResponse->failedRows() as $row) {
                        foreach ($row['errors'] as $error) {
                            $this->addError($error);
                        }
                    }
                    return;
                }
            }  catch (Exception $e) {
                $error = json_decode($e->getMessage(),true);
                return($this->addError(['bigquery'=>$error]));
            }

            return true;
        }

        /**
         * Execute a query in BigQuery
         * @param $q
         * @return array|void
         */
        private function _query($q,$params=[]) {
            $start_global_time = microtime(true);
            $this->core->__p->add('DataBQ._query '. substr($q,0,10).'..', '','note');

            $n_percentsS = substr_count($q,'%s');
            if($params) {
                if(!is_array($params) || count($params)!= $n_percentsS) {
                    return $this->addError("Number of %s ($n_percentsS) doesn't count match with number of arguments (".count($params)."). Query: $q -> ".print_r($params,true));
                }
                foreach ($params as $param) {
                    $q = preg_replace('/%s/',$param??'',$q,1);
                }
            }

            $this->_last_query = $q;
            if($this->_only_create_query) return [];

            try {

                /*
                $jobConfig = $this->client->query($q);
                $job = $this->client->startQuery($jobConfig);

                $backoff = new ExponentialBackoff(20);
                $backoff->execute(function () use ($job) {
                    $job->reload();
                    if (!$job->isComplete()) {
                        return($this->addError('Job has not yet completed'));
                    }
                });
                $queryResults = $job->queryResults();
                */
                $jobConfig = $this->client->query($q);
                $queryResults = $this->client->runQuery($jobConfig);
                $i = 0;
                $ret=[];
                //_printe($queryResults->rows()->current());

                foreach ($queryResults as $row) {
                    foreach ($row as $key => $value) {
                        if(is_object($value)) {
                            if(get_class($value)=='Google\Cloud\BigQuery\Timestamp') {
                                /** @var Google\Cloud\BigQuery\Timestamp $row[$key] */
                                $row[$key] = $value->formatAsString();
                            }elseif(get_class($value)=='Google\Cloud\BigQuery\Date') {
                                /** @var Google\Cloud\BigQuery\Date $row[$key] */
                                $row[$key] = $value->formatAsString();
                            }elseif(get_class($value)=='Google\Cloud\BigQuery\Numeric') {
                                /** @var Google\Cloud\BigQuery\Numeric $row[$key] */
                                $row[$key] = $value->get();
                            }elseif(get_class($value)=='DateTime') {
                                /** @var DateTime $row[$key] */
                                $row[$key] = $value->format('Y-m-d H:i:s');
                            }
                            else {
                                $this->_last_query_time = round(microtime(true)-$start_global_time,4);
                                if($this->debug)
                                    $this->core->logs->add("DataBQ.fetch({$this->_last_query}) [".$this->_last_query_time." secs]",'DataBQ');
                                return($this->addError($key.' field is of unknown class: '.get_class($value)));
                            }
                        } elseif(is_array($value)) {
                            if(isset($value['fields'])) $row[$key] = $value['fields'];
                        }

                    }
                    $ret[] = $row;
                }
                $this->core->__p->add('DataBQ._query '. substr($q,0,10).'..', '', 'endnote');
                $this->_last_query_time = round(microtime(true)-$start_global_time,4);
                if($this->debug)
                    $this->core->logs->add("DataBQ.fetch({$this->_last_query}) [".$this->_last_query_time." secs]",'DataBQ');

                return $ret;
            } catch (Exception $e) {
                return($this->addError($e->getMessage()));
            }

        }

        /**
         * Reset init values
         */
        public function reset() {

            $this->use_mapping = false;
            $this->limit = 0;
            $this->page = 0;
            $this->offset = 0;
            $this->order = '';
            $this->joins = [];
            $this->queryFields = '';
            $this->queryWhere = [];
            $this->virtualFields = [];
            $this->groupBy = '';
            $this->view = null;
            $this->error = false;
            $this->errorMsg = [];

        }

        /**
         * Return the fields defined for the table in the schema
         * @return array|null
         */
        function getFields() {
            return $this->fields?array_keys($this->fields):['*'];
        }

        /**
         * Return the mapped field namesdefined in the schema mapping
         * @return array
         */
        function getMappingFields() {
            return array_values($this->mapping);
        }

        /**
         * Return the fields ready for a SQL query
         * @param  array|null fields to show
         * @return array|null
         */
        function getSQLSelectFields($fields=null) {
            if(null === $fields || empty($fields)) {
                $fields = $this->getFields();
            }

            $ret='';
            foreach ($fields as $i=>$field) {
                if($ret) $ret.=',';
                if(strpos($field,'(')===false && isset($this->entity_schema['model'][$field][0])) {
                    $ret.='`'.$this->project_id.'.'.$this->dataset_name.'.'.$this->table_name.'`.'.$field;
                } else{
                    $ret.=$field;
                }
            }

            return $ret;
            //$this->dataset_name.'.'.implode(','.$this->dataset_name.'.',$fields);

        }

        /**
         * Return one record based on a key
         * @param $key can ba an string or number
         * @param null $fields if null $fields = $this->getFields()
         */
        function fetchOneByKey($key, $fields=null) {
            if(is_array($key)) return;
            $ret = $this->fetchByKeys([$key],$fields);
            if($ret) $ret= $ret[0];
            return $ret;
        }

        /**
         * Return the tuplas with the $keyWhere including $fields
         * @param $keysWhere
         * @param null $fields if null $fields = $this->getFields()
         */
        function fetchByKeys($keysWhere, $fields=null) {
            if($this->error) return;
            if(!$this->key) return($this->addError('fetchByKeys($keysWhere, $fields=null) has been called but there is no key in the data model'));

            // Keys to find
            if(!is_array($keysWhere)) $keysWhere = [$keysWhere];

            // Where condition for the SELECT
            $where = " `{$this->project_id}.{$this->dataset_name}.{$this->table_name}`.{$this->key} IN ( ";
            $params = [];
            $values = '';
            foreach ($keysWhere as $keyWhere) {
                if($values) $values.=', ';
                if($this->entity_schema['model'][$this->key][0]=='integer') $values.='%s';
                else $values.="'%s'";
                $params[] = $keyWhere;
            }

            $where.= "{$values} )";

            // Fields to returned
            $sqlFields = $this->getQuerySQLFields($fields);
            $from = $this->getQuerySQLFroms();

            // Query
            $_sql = "SELECT {$sqlFields} FROM {$from} WHERE {$where}";
            if(!$sqlFields) return($this->addError('No fields to select found: '.json_encode($fields)));
            return $this->_query($_sql,$params);

        }

        /**
         * Set a limit in the select query or fetch method.
         * @param int $limit
         */
        function setLimit($limit) {
            $this->limit = intval($limit);
        }

        /**
         * Set a page in the select query or fetch method.
         * @param int $page
         */
        function setPage($page) {
            $this->page = intval($page);
        }


        /**
         * Set a offset in the select query or fetch method.
         * @param int $offset
         */
        function setOffset($offset) {
            $this->offset = intval($offset);
        }

        /**
         * Defines the fields to return in a query. If empty it will return all of them
         * @param $fields
         */
        function setQueryFields($fields) {
            $this->queryFields = $fields;
        }

        /**
         * Array with key=>value
         * Especial values:
         *              '__null__'
         *              '__notnull__'
         *              '__empty__'
         *              '__notempty__'
         * @param Array $keysWhere
         */
        function setQueryWhere($keysWhere) {
            if(empty($keysWhere) ) return($this->addError('setQueryWhere($keysWhere) $keyWhere can not be empty'));
            $this->queryWhere = $keysWhere;
        }


        /**
         * Array with key=>value
         * Especial values:
         *              '__null__'
         *              '__notnull__'
         *              '__empty__'
         *              '__notempty__'
         * @param Array $keysWhere
         */
        function addQueryWhere($keysWhere) {
            if(empty($keysWhere) ) return($this->addError('setQueryWhere($keysWhere) $keyWhere can not be empty'));
            if(!is_array($keysWhere)) return($this->addError('setQueryWhere($keysWhere) $keyWhere is not an array'));
            $this->queryWhere = array_merge($this->queryWhere ,$keysWhere);
        }

        // Allows to add an extra where to be added in all calls
        function setExtraWhere($extraWhere) {
            $this->extraWhere = $extraWhere;
        }

        function getExtraWhere() {
            return($this->extraWhere);
        }

        /**
         * Return [record_structure]
         * @param array $keysWhere
         * @param null $fields
         * @return array|void
         */
        function fetchOne($keysWhere=[], $fields=null, $groupBy=null,$params=[]) {
            $this->limit = 1;
            $ret = $this->fetch($keysWhere, $fields, $params);
            if($ret) $ret=$ret[0];
            return($ret);
        }
        /**
         * Return records [0..n][record_structure] from the db object
         * @param array|string $keysWhere
         * @param null $fields
         * @return array|void
         */
        function fetch($keysWhere=[], $fields=null, $groupBy=null, $params=[]) {

            if($this->error) return false;
            if(!is_array($params)) $params=[];
            //--- WHERE
            // Array with key=>value or empty
            if(is_array($keysWhere) ) {
                list($where, $_params) = $this->getQuerySQLWhereAndParams($keysWhere);
                if($this->error) return;
                $params = array_merge($params,$_params);
            }

            // String
            elseif(is_string($keysWhere) && !empty($keysWhere)) {
                $where =$keysWhere;
            } else {
                return($this->addError('fetch($keysWhere,$fields=null) $keyWhere has a wrong value'));
            }

            // --- FIELDS
            $distinct = '';
            if(is_string($fields) && strpos($fields,'DISTINCT ')===0) {
                $fields = str_replace('DISTINCT ','',$fields);
                $distinct = 'DISTINCT ';
            }
            $sqlFields = $this->getQuerySQLFields($fields);

            // virtual fields
            if(is_array($this->virtualFields) && count($this->virtualFields))
                foreach ($this->virtualFields as $field=>$value) {
                    $sqlFields.=",{$value} as {$field}";
                }

            // --- QUERY
            $from = $this->getQuerySQLFroms();
            $SQL = "SELECT {$distinct}{$sqlFields} FROM {$from}";


            // add extraWhere to all calls
            if($this->extraWhere) {
                if($where) $where.=" AND ".$this->extraWhere;
                else  $where=$this->extraWhere;
            }

            // add SQL where condition
            if($where) {
                $SQL.=" WHERE {$where}";
            }

            // --- GROUP BY
            if(!$groupBy && $this->groupBy) $groupBy =$this->groupBy;
            if($groupBy) {
                $SQL .= " GROUP BY  {$groupBy}";
            }

            // --- ORDER BY
            if($this->order) $SQL.= " ORDER BY {$this->order}";
            if($this->limit) {
                $SQL.= " limit {$this->limit}";
                if($this->page) {
                    $this->offset = $this->limit*$this->page;
                }
                if($this->offset) {
                    $SQL .= " offset {$this->offset}";
                }
            }

            if(!$sqlFields) return($this->addError('No fields to select found: '.json_encode($fields)));

            $ret= $this->_query($SQL,$params);
            if($this->error) return;
            return($ret);
        }

        /**
         * Update a record in db
         * It requires to add always the following condition: and _created < TIMESTAMP_SUB(_created, INTERVAL 30 MINUTE)
         * because you can not update or delete records inserted before 30' has passed
         * @param $data
         * @return bool|null|void
         */
        public function update($data,$record_readed=[]) {
            if(!is_array($data) ) return($this->addError('update($data) $data has to be an array with key->value'));
            if(!isset($this->entity_schema['model'])) return $this->addError('update($data) there is no model defined');
            if(!$this->key) return $this->addError('update($data) there is no $this->key defined');
            if(!isset($data[$this->key]) || !$data[$this->key]) return $this->addError('update($data) missing key field in $data: '.$this->key);
            if(count($data)<2) return $this->addError('update($data) there is no fields to update ');

            if(!$record_readed) {
                $record_readed = $this->fetchByKeys($data[$this->key]);
                if ($this->error) return ($this->addError('update($data) error calling $this->fetchByKeys($data[$this->key])'));
            }
            if(!$record_readed) return($this->addError('update($data) contains a key that does not exist in the dataset: '.$this->key.'='.$data[$this->key]));

            foreach ($data as $field=>$value) if(!isset($this->entity_schema['model'][$field])) unset($data[$field]);
            $_sql = "UPDATE `{$this->project_id}.{$this->dataset_name}.{$this->table_name}` SET ";
            $set = "";
            $params = [];
            foreach ($data as $field=>$value) if($field!=$this->key) {
                if(isset($this->entity_schema['model']) && !isset($this->entity_schema['model'][$field]))
                    return $this->addError('update($data) has received a field not included in the model: '.$field);

                if($set) $set.=', ';
                $set.= " {$field}=";

                //region EVALUATE string values VS boolean or number values
                if(isset($this->entity_schema['model'][$field][0]) &&  in_array($this->entity_schema['model'][$field][0],['integer','float','boolean'])) {
                    $set.='%s';
                } else {
                    $set.='"%s"';
                }
                //endregion

                //region modify boolean and numeric values values
                if(($this->entity_schema['model'][$field][0]??null)=='boolean') {
                    if($value===null) $value='NULL';
                    else $value=$value?'true':'false';
                }elseif(!strlen($value??'')) {
                    if(in_array($this->entity_schema['model'][$field][0]??null,['integer','float'])) {
                        $value='NULL';
                    }
                }
                //endregion
                $params[] = $value;

            }
            $set.=',_updated=CURRENT_TIMESTAMP()';

            $_sql.=$set." WHERE {$this->key}=";
            if(isset($this->entity_schema['model'][$this->key][0]) &&  in_array($this->entity_schema['model'][$this->key][0],['integer','float','boolean'])) $_sql.='%s';
            else $_sql.='"%s"';
            $params[] = $data[$this->key];

            return $this->_query($_sql,$params);

        }

        /**
         * Update a record in db
         * It requires to add always the following condition: and _created < TIMESTAMP_SUB(_created, INTERVAL 30 MINUTE)
         * because you can not update or delete records inserted before 30' has passed
         * @param $data
         * @return bool|null|void
         */
        public function softDelete($key) {
            if(!$key ) return($this->addError('softDelete($key) $key has to be an array with key->value'));
            if(!isset($this->entity_schema['model'])) return $this->addError('softDelete($key) there is no model defined');
            if(!$this->key) return $this->addError('softDelete($key) there is no $this->key defined');
            $_sql = "UPDATE `{$this->project_id}.{$this->dataset_name}.{$this->table_name}` SET _deleted=CURRENT_TIMESTAMP() WHERE {$this->key}=";
            if(isset($this->entity_schema['model'][$this->key][0]) &&  in_array($this->entity_schema['model'][$this->key][0],['integer','float','boolean'])) $_sql.='%s';
            else $_sql.='"%s"';
            $params[] = $key;
            return $this->_query($_sql,$params);
        }

        /**
         * Upsert a record in db
         * @param $data
         * @return bool|null|void
         */
        public function upsert($data)
        {
            return $this->insert($data,true);
        }

        /**
         * Insert a record in db. If $upsert is true and the record exist then update it
         * @param $data
         * @param bool $upsert
         * @return bool|null|void
         */
        public function insert($data, $upsert=false) {
            if(!is_array($data) ) return($this->addError('insert($data) $data has to be an array with key->value'));
            if(!isset($this->entity_schema['model'])) return $this->addError('insert($data) there is no model defined');

            if($this->key) {
                if(!isset($data[$this->key]) || !$data[$this->key]) return $this->addError('insert($data) missing key field in $data: '.$this->key);
                $record_readed = $this->fetchByKeys($data[$this->key]);
                if($this->error) return($this->addError('insert($data) error calling $this->fetchByKeys($data[$this->key])'));
                if($record_readed) {
                    if($upsert) return $this->update($data,$record_readed);
                    else return($this->addError('insert($data) contains a key that already exist in the dataset: '.$this->key.'='.$data[$this->key]));
                }
            }

            foreach ($data as $field=>$value) if(!isset($this->entity_schema['model'][$field])) unset($data[$field]);
            $_sql = "INSERT INTO  `{$this->project_id}.{$this->dataset_name}.{$this->table_name}` (".implode(',',array_keys($data)).",_created,_updated) values(";
            $set = "";
            $params = [];
            foreach ($data as $field=>$value) {

                if($set) $set.=', ';

                //region EVALUATE string values VS boolean or number values
                if(isset($this->entity_schema['model'][$field][0]) &&  in_array($this->entity_schema['model'][$field][0],['integer','float','boolean'])) $set.='%s';
                else $set.='"%s"';
                //endregion

                //region modify boolean and numeric values values
                if(($this->entity_schema['model'][$field][0]??null)=='boolean') {
                    if($value===null) $value='NULL';
                    else $value=$value?'true':'false';
                }elseif(!strlen($value??'')) {
                    if(in_array($this->entity_schema['model'][$field][0]??null,['integer','float'])) {
                        $value='NULL';
                    }
                }
                //endregion
                $params[] = $value;

            }
            $_sql.=$set.',CURRENT_TIMESTAMP(),CURRENT_TIMESTAMP())';
            return $this->_query($_sql,$params);

        }

        /**
         * Streaming Insert a record in db. It couldn't be deleted or updated before 30' after insertion
         * @param $data
         * @return bool|null|void
         */
        public function insertWithStreamingBuffer($data) {
            if(!is_array($data) ) return($this->addError('insert($data) $data has to be an array with key->value'));
            if(!isset($this->entity_schema['model'])) return $this->addError('insert($data) there is no model defined');
            if(!$this->table) return $this->addError('insert($data) there is $this->table initiated');
            if(!$data || !is_array($data)) return($this->addError('insert($data) $data has received an empty or non array value'));
            if(!isset($data[0])) $data = [$data];

            //region PREPARE $bq_data from $data to be inserted and adding _created field
            $bq_data = [];
            foreach ($data as $i=>$record) {
                // Delete unused records
                foreach ($record as $key=>$datum)
                    if(!isset($this->entity_schema['model'][$key])) unset($data[$i][$key]);

                if(!$data[$i]) return($this->addError('insert($data) $data has received fields to be inserted'));
                $data[$i]['_updated'] = 'AUTO';
                $data[$i]['_created'] = 'AUTO';

                $bq_data[] = ['data'=>&$data[$i]];
            }
            //endregion

            //region INSERT ROWS
            try {
                $insertResponse = $this->table->insertRows($bq_data);
                if (!$insertResponse->isSuccessful())  {
                    foreach ($insertResponse->failedRows() as $row) {
                        foreach ($row['errors'] as $error) {
                            return $this->addError($error);
                        }
                    }
                }
            }  catch (Exception $e) {
                $error = $e->getMessage();
                return($this->addError(['bigquery'=>$error]));
            }
            //endregion

            return $bq_data;
        }

        /**
         * Insert a record in dataset.table
         * @param $data
         * @return bool|null|void
         */
        public function _insertWithStreamingBuffer($data) {
            if(!$this->table ) return($this->addError('insert($data) called but there is not $this->table assigned'));
            if(!is_array($data) ) return($this->addError('insert($data) $data has to be an array with key->value'));
            if(!isset($data[0])) $data = [$data];

            //region PREPARE $bq_data from $data to be inserted and adding _created field
            $bq_data = [];
            $keys = [];
            foreach ($data as $i=>$foo) {
                //region ADD _created field required from CLOUDFRAMEWORK
                $data[$i]['_created'] = 'AUTO';
                $data[$i]['_updated'] = 'AUTO';
                //endregion
                $bq_data[] = ['data'=>&$data[$i]];
                if($this->key){
                    if(!isset($data[$i][$this->key])) return($this->addError('insert($data) missing key field in $data: '.$this->key));
                    $keys[]= $data[$i][$this->key];
                }
            }
            //endregion

            //region IF $keys verify the records does not exist in the table
            if($keys) {
                $data = $this->fetchByKeys($keys,$this->key);
                if($this->error) return($this->addError(' insert($data) error calling $this->fetchByKeys($keys)'));
                if($data) return($this->addError(['error'=>'There are records with the same ids','data'=>$data]));
            }
            //endregion

            //region INSERT $bq_data
            try {
                $insertResponse = $this->table->insertRows($bq_data);
                if (!$insertResponse->isSuccessful()) {
                    foreach ($insertResponse->failedRows() as $row) {
                        foreach ($row['errors'] as $error) {
                            $this->addError($error);
                        }
                    }
                    return;
                }
            }  catch (Exception $e) {
                $error = json_decode($e->getMessage(),true);
                return($this->addError($error));
            }
            //endregion

            return($data);
        }

        /**
         * Delete a record in db
         * @param $data
         * @return bool|null|void
         */
        public function delete($data) {
            if(!is_array($data) ) return($this->addError('delete($data) $data has to be an array with key->value'));
            if(!isset($this->entity_schema['model']))
                return $this->addError('delete(&$data) there is no model defined');

            $_sql = "DELETE FROM `{$this->project_id}.{$this->dataset_name}.{$this->table_name}` WHERE ";
            $where = "";
            $params = [];
            foreach ($data as $field=>$value) {
                if(isset($this->entity_schema['model']) && !isset($this->entity_schema['model'][$field]))
                    return $this->addError('delete($data) has received a field not included in the model: '.$field);

                if($where) $where.=' and ';
                $where.= " {$field}=";
                if(isset($this->entity_schema['model'][$field][0]) && in_array($this->entity_schema['model'][$field][0],['integer','float','boolean'])) $where.='%s';
                else $where.='"%s"';
                $params[] = $value;

            }
            //endregion
            $_sql.=$where;
            return $this->_query($_sql,$params);
        }


        /** About Order */
        function unsetOrder() {$this->order='';}
        /**
         * Set Order into a query with a field
         * @param $field
         * @param $type
         */
        function setOrder($field, $type='ASC') {$this->unsetOrder(); $this->addOrder($field, $type);}
        /**
         * Add Order into a query with a new field
         * @param $field
         * @param $type
         */
        function addOrder($field, $type='ASC') {

            // Let's convert from Mapping into SQL fields
            if(strtolower($field)=='rand()') $this->order = $field;
            elseif(strpos($field,'.')!==null ) $this->order = $field.((strtoupper(trim($type))=='DESC')?' DESC':' ASC');
            else {
                if($this->use_mapping) {
                    if(isset($this->entity_schema['mapping'][$field]['field'])) $field = $this->entity_schema['mapping'][$field]['field'];
                }

                if(isset($this->fields[$field]))  {
                    if(strlen($this->order)) $this->order.=', ';
                    $this->order.= '`'.$this->table_name.'`.'.$field.((strtoupper(trim($type))=='DESC')?' DESC':' ASC');
                } else {
                    $this->addError($field.' does not exist to order by');
                }
            }

        }


        function getQuerySQLWhereAndParams($keysWhere=[]) {
            if(!is_array($keysWhere) ) return($this->addError('getQuerySQLWhereAndParams($keysWhere) $keyWhere has to be an array with key->value'));

            // Where condition for the SELECT
            $where = ''; $params = [];

            // Custom query rewrites previous where.
            if(!count($keysWhere)) $keysWhere = $this->queryWhere;

            // Loop the wheres
            if(is_array($keysWhere))
                foreach ($keysWhere as $key=>$value) {

                    // Complex query
                    if(strpos($key,'(')!== false || strpos($key,'%')!== false || stripos($key,' and ') || stripos($key,' or ')) {
                        if($where) $where.=' AND ';
                        $where.= $key;


                        // Avoid params
                        if($value===null) continue;

                        // Verify $value is array of values
                        if(!is_array($value)) $value = [$value];

                        // Add new params
                        $params = array_merge($params,$value);
                        continue;
                    }
                    // Simple where
                    else {
                        // TODO: support >,>=,<,<=
                        if(!isset($this->fields[$key])) return($this->addError('fetch($keysWhere, $fields=null) $keyWhere contains a wrong field: '.$key));
                    }

                    if($where) $where.=' AND ';

                    //region SET $is_date,$field
                    $is_date = isset($this->entity_schema['model'][$key][0]) && in_array($this->entity_schema['model'][$key][0],['date', 'datetime', 'datetimeiso','timestamp']);
                    $field = "`{$this->project_id}.{$this->dataset_name}.{$this->table_name}`.{$key}";
                    //endregion

                    switch (strval($value)) {
                        case "__null__":
                            $where.="{$field} IS NULL";
                            break;
                        case "__notnull__":
                            $where.="{$field} IS NOT NULL";
                            break;
                        case "__empty__":
                            if($is_date) $where.="{$field} IS NULL";
                            else $where.="{$field} = ''";
                            break;
                        case "__noempty__":
                            if($is_date) $where.="{$field} IS NOT NULL";
                            $where.="{$field} != ''";
                            break;
                        default:
                            // IN
                            if(is_array($value)) {
                                if(in_array($this->fields[$key],['integer','float'])) {
                                    $where.="{$field} IN (%s)";
                                    $params[] = implode(',',$value);
                                }
                                else {
                                    // Securing slashed
                                    $value = array_map(function($str) {
                                        return addslashes($str);
                                    }, $value);

                                    // Add an IN
                                    $where.="{$field} IN ('".implode("','",$value)."')";
                                    //$params[] = implode("','",$value);
                                }
                            }
                            // =
                            else {
                                //region IF $is_date create a special query a continue;
                                if($is_date) {
                                    // Evaluate a date field
                                    if(strpos($value,'/')===false) {
                                        $from = $value;
                                        $to = null;
                                    } else {
                                        list($from,$to) = explode("/",$value,2);
                                    }

                                    if(strlen($from) == 4) {
                                        $field = "FORMAT_DATE('%Y',`{$this->project_id}.{$this->dataset_name}.{$this->table_name}`.{$key})";
                                    } elseif(strlen($from) == 7) {
                                        $field = "FORMAT_DATE('%Y-%m',`{$this->project_id}.{$this->dataset_name}.{$this->table_name}`.{$key})";
                                    } elseif(strlen($from) == 10) {
                                        $field = "FORMAT_DATE('%Y-%m-%d',`{$this->project_id}.{$this->dataset_name}.{$this->table_name}`.{$key})";
                                    } else {
                                        break;
                                    }
                                    if($to===null) {
                                        $where.="{$field} = '%s'";
                                        $params[] = $from;
                                    } else {
                                        $where.="({$field} >= '%s'";
                                        $params[] = $from;
                                        if($to) {
                                            if(strlen($to) == 4) {
                                                $field = "FORMAT_DATE('%Y',`{$this->project_id}.{$this->dataset_name}.{$this->table_name}`.{$key})";
                                            } elseif(strlen($to) == 7) {
                                                $field = "FORMAT_DATE('%Y-%m',`{$this->project_id}.{$this->dataset_name}.{$this->table_name}`.{$key})";
                                            } elseif(strlen($to) == 10) {
                                                $field = "FORMAT_DATE('%Y-%m-%d',`{$this->project_id}.{$this->dataset_name}.{$this->table_name}`.{$key})";
                                            } else {
                                                break;
                                            }
                                            $where.=" AND {$field} <= '%s')";
                                            $params[] = $to;
                                        }
                                    }
                                    break;
                                }
                                //endregion
                                //region ELSE evaluate operators for ['integer','float','boolean'] value types
                                else {
                                    $op = '=';
                                    if(in_array($this->fields[$key],['integer','float','boolean'])) {

                                        //region EVALUATE $op: >=,>,<=,<,!=
                                        // Add operators
                                        if(strpos($value,'>=')===0) {
                                            $op='>=';
                                            $value = str_replace('>=','',$value);
                                        }elseif(strpos($value,'<=')===0) {
                                            $op='<=';
                                            $value = str_replace('<=','',$value);
                                        }elseif(strpos($value,'>')===0) {
                                            $op='>';
                                            $value = str_replace('>','',$value);
                                        }elseif(strpos($value,'<')===0) {
                                            $op='<';
                                            $value = str_replace('<','',$value);
                                        }elseif(strpos($value,'!=')===0) {
                                            $op='!=';
                                            $value = str_replace('!=','',$value);
                                        }
                                        //endregion

                                        //region EVALUATE boolean value
                                        if($this->fields[$key]=='boolean') $value = ($value)?'true':'false';
                                        //endregion

                                        //region SET $where
                                        $where.="{$field} {$op} %s";
                                        //endregion
                                    }
                                    else {
                                        if(strpos($value,'%')!==false) {
                                            if(strpos($value,'!=')===0) {
                                                $op = 'not like';
                                                $value = str_replace('!=', '', $value);
                                            } else {
                                                $op = 'like';
                                            }
                                        } elseif(strpos($value,'!=')===0) {
                                            $op='!=';
                                            $value = str_replace('!=','',$value);
                                        }

                                        $where.="{$field} {$op} '%s'";
                                    }

                                    //region ASSIGN $value to $params[]
                                    $params[] = $value;
                                    //endregion
                                }
                                //endregion
                            }

                            break;

                    }

                }

            // Search into Joins queries
            foreach ($this->joins as $join) {
                /** @var DataBQ $object */
                $object = $join[1];
                list($joinWhere,$joinParams) = $object->getQuerySQLWhereAndParams();
                if($joinWhere) {

                    if($where) $where.=' AND ';
                    $where.=$joinWhere;

                    $params=array_merge($params,$joinParams);

                }
            }
            return [$where,$params];
        }

        function getQuerySQLFields($fields=null) {
            if(!$fields) $fields=$this->queryFields;
            if($fields && is_string($fields)) $fields = explode(',',$fields);

            $ret =  $this->getSQLSelectFields($fields);

            foreach ($this->joins as $i=>$join) {

                /** @var DataBQ $object */
                $object = $join[1];
                $ret.=','.str_replace('`'.$object->project_id.'.'.$object->dataset_name.'.'.$object->table_name.'`.',"_j{$i}.",$object->getQuerySQLFields());

            }

            return $ret;
        }

        function getQuerySQLFroms() {
            $from = "`{$this->project_id}.{$this->dataset_name}.{$this->table_name}`";
            foreach ($this->joins as $i=>$join) {
                /** @var DataBQ $object */
                $object = $join[1];
                $from.=" {$join[0]}  JOIN `$this->project_id}.{$this->dataset_name}.{$object->table_name}` _j{$i} ON (`{$this->project_id}.{$this->dataset_name}.$this->table_name}`.{$join[2]} = _j{$i}.{$join[3]})";
            }

            return $from;
        }

        /**
         * Active or deactive mapping of fields
         * @param bool $use
         */
        public function useMapping($use=true) {
            $this->use_mapping = $use;
        }

        public function setView($view) {
            if(!is_string($view) && null !==$view) return($this->addError('setView($view), Wrong value'));

            $this->view = $view;
        }

        /**
         * @param $type Could be inner or left
         * @param DataBQ $object
         * @param $first_field string field of the local object to join with
         * @param $join_field string field of the join object to match
         * @param $extraon string any other extra condition
         */
        function join ($type, DataBQ &$object, $first_field, $join_field,$extraon=null) {
            $this->joins[] = [$type,$object, $first_field, $join_field,$extraon];
        }

        /**
         * @param $group String The group by fields
         */
        function setGroupBy ($group) {
            $this->groupBy = $group;
        }

        /**
         * @param $field String virtual field name
         * @param $value String value or other field
         */
        function addVirtualField ($field,$value) {
            $this->virtualFields[$field] = $value;
        }

        /**
         * @param $field String virtual field name
         * @param $value String value or other field
         */
        function setVirtualField ($field,$value) {
            $this->virtualFields = [$field=>$value];
        }


        /**
         * Add an error in the class
         */
        function addError($value)
        {
            $this->error = true;
            if(!is_array($this->errorMsg)) $this->errorMsg = [$this->errorMsg];
            $this->errorMsg[] = $value;
        }

        /**
         * Return last query executed
         * @return null|string
         */
        function getDBQuery() {
            return $this->_last_query;
        }

        /**
         * Return last time spent y last query
         * @return int|null
         */
        function getDBQueryTime() {
            return($this->_last_query_time);
        }

        /**
         * Return an array of the mapped fields ready to insert or update Validating the info
         * @param $data
         * @param array $dictionaries
         * @return array
         */
        function getValidatedArrayFromData(&$data, $all=true, &$dictionaries=[]) {

            if(!is_array($data) || !count($data)) return($this->addError('getCheckedArrayToInsert: empty or not valid data'));

            $schema_to_validate = [];
            foreach ($this->entity_schema['model'] as $field=>$item) {
                $schema_to_validate[$field] = ['type'=>$item[0],'validation'=>$item[1]??''];
            }

            $dataValidated = [];
            foreach ($schema_to_validate as $field=>$value) {
                if((isset($data[$field]) || $all) && isset($value['validation']) && stripos($value['validation'],'internal')===false) {
                    if(isset($data[$field]))
                        $dataValidated[$field] = $data[$field];
                    else
                        $dataValidated[$field] = null;
                }

            }
            if(!count($dataValidated)) return($this->addError('getValidatedArrayFromData: We did not found fields to validate into the data'));

            /* @var $dv DataValidation */
            $dv = $this->core->loadClass('DataValidation');
            if(!$dv->validateModel($schema_to_validate,$dataValidated,$dictionaries,$all)) {
                $this->addError($this->table_name.': error validating Data in Model.: {'.$dv->field.'}. '.$dv->errorMsg);
            }

            return ($dataValidated);
        }

        public function getValidatedRecordToInsert(&$data) {

        }

        /**
         * Return the json form model to be used in validations in front-end
         * @return mixed|null
         */
        public function getFormModelWithMapData() {
            $fields = [];
            foreach ($this->entity_schema['model'] as $key=>$attr) {

                $type = $attr[0];
                //db: types conversions
                if(strpos($type,'int')===0) $type = "integer";
                elseif(strpos($type,'var')===0) $type = "string";
                elseif(strpos($type,'bit')===0) {
                    $type = "integer";
                    if(!isset($attr[1])) $attr[1] = '';
                    $attr[1] = "values:0,1|".$attr[1];
                }

                $field = ['type'=> $type,'db_type'=>$attr[0]];
                $field['validation'] = (isset($attr[1]))?$attr[1]:null;
                if(strpos($field['validation'],'hidden')!==false)
                    continue;
                $fields[$key] = $field;
            }
            return ($fields);
        }

        /**
         * Return a structure with bigquery squema based on CF model
         * @return array
         */
        public function getBigQueryStructureFromModel() {

            $bq_structure = [];
            if(!isset($this->entity_schema['model']))
                return $this->addError('There is not model [$this->entity_schema]');

            foreach ($this->entity_schema['model'] as $field_name=>$item) {
                $field = ["name"=>$field_name];
                switch (strtolower($item[0])) {
                    case "string":
                        $field['type'] = "STRING";
                        break;
                    case "timestamp":
                        $field['type'] = "TIMESTAMP";
                        break;
                    case "boolean":
                        $field['type'] = "BOOLEAN";
                        break;
                    case "integer":
                        $field['type'] = "INTEGER";
                        break;
                    case "float":
                        $field['type'] = "FLOAT";
                        break;
                    case "date":
                        $field['type'] = "DATE";
                        break;
                    case "datetime":
                        $field['type'] = "DATETIME";
                        break;
                    default:
                        $field['type'] = "STRING";
                        break;
                }
                $field['mode'] = (($item[1]??null) && stripos($item[1],'allownull'))?'NULLABLE':'REQUIRED';
                $field['description'] = "";
                if(($item[1]??null) && stripos($item[1],'description:')) {
                    list($foo, $description) = explode('description:',$item[1],2);
                    $description = preg_replace('/\|.*/','',$description);
                    $field['description'] = $description;
                }

                $bq_structure[] = $field;
            }

            if(!isset($this->entity_schema['model']['_created']))
                $bq_structure[] = ['name'=>'_created','mode'=>'NULLABLE','type'=>"TIMESTAMP",'description'=>"Internal Field for CFO"];
            if(!isset($this->entity_schema['model']['_updated']))
                $bq_structure[] = ['name'=>'_updated','mode'=>'NULLABLE','type'=>"TIMESTAMP",'description'=>"Internal Field for CFO"];
            if(!isset($this->entity_schema['model']['_deleted']))
                $bq_structure[] = ['name'=>'_deleted','mode'=>'NULLABLE','type'=>"TIMESTAMP",'description'=>"Internal Field for CFO"];
            return ['fields'=>$bq_structure];
        }

        /**
         * Return a structure with bigquery squema based on CF model
         * @return array
         */
        public function checkBigQueryStructure($fix=false) {


            $ret = ['project'=>$this->project_id,'field_analysis'=>[]];

            $datasetInfo = $this->getDataSetInfo();
            if($this->error) {
                if($fix && ($this->errorMsg[0]['bigquery']['error']['code']??0)==404) {
                    $this->error=false;
                    $this->errorMsg = [];
                    try {
                        $this->dataset = $this->client->createDataset($this->dataset_name);
                        $datasetInfo = $this->getDataSetInfo();
                        if($this->error) {
                            $ret['field_analysis'] ="{$this->dataset_name} DATASET CAN NOT BE CREATED IN PROJECT ".$this->project_id;
                            $ret['dataset'] = ["name"=>$this->dataset_name,'error'=>$this->errorMsg];
                            return $ret;
                        }
                    } catch (Exception $e) {
                        $error = json_decode($e->getMessage(),true);
                        $this->addError(['bigquery'=>$error]);
                        $ret['field_analysis'] ="{$this->dataset_name} DATASET CAN NOT BE CREATED IN PROJECT ".$this->project_id;
                        $ret['dataset'] = ["name"=>$this->dataset_name,'error'=>$this->errorMsg];
                        return $ret;
                    }

                } else {
                    $ret['field_analysis'] ="{$this->dataset_name} DATASET DOES NOT EXIST. Send [fix=1] to repair";
                    $ret['dataset'] = ["name"=>$this->dataset_name,'error'=>$this->errorMsg];
                    return $ret;
                }
            }

            $ret['dataset'] = $datasetInfo;
            $tableInfo = $this->getDataSetTableInfo();
            if($this->error) {
                if($fix && ($this->errorMsg[0]['bigquery']['error']['code']??0)==404) {
                    $this->error = false;
                    $this->errorMsg = [];
                    $create_table = $this->createDataSetTableInfo($this->getBigQueryStructureFromModel());
                    if($this->error) {
                        $ret['table'] = ["name"=>$this->table_name,'error'=>$this->errorMsg];
                    } else {
                        $ret['table']['info'] = $create_table['info'];
                    }
                } else {
                    $ret['field_analysis'] ="{$this->dataset_name}.{$this->table_name} TABLE DOES NOT EXIST. Send [fix=1] to repair";
                    $ret['table'] = ["name"=>$this->table_name,'error'=>$this->errorMsg];
                }
            } else {
                $ret['table'] = $tableInfo;
            }

            if(!$this->error) {
                $ret['field_analysis'] =[];

                $structure_from_model = [];
                $structure_from_bq = [];
                foreach ($this->getBigQueryStructureFromModel()['fields'] as $field) $structure_from_model[$field['name']] = $field;
                foreach ($ret['table']['info']['schema']['fields'] as $field) $structure_from_bq[$field['name']] = $field;

                foreach ($structure_from_model as $field=>$item) {

                    $ret['field_analysis'][$field] = "OK";
                    if(!isset($structure_from_bq[$field])) $ret['field_analysis'][$field] = 'ERROR: Field does not exist';
                    else if(($structure_from_bq[$field]['type']??null) != $item['type']) $ret['field_analysis'][$field] = 'ERROR: type does not match';
                    else if(($structure_from_bq[$field]['mode']??null) != $item['mode']) $ret['field_analysis'][$field] = 'ERROR: mode does not match';
                }

            }

            return $ret;
        }


        /**
         * Return a structure with bigquery squema based on CF model
         * @return array
         */
        public function getInterfaceModelFromDatasetTable()
        {
            $cfo = [
                'KeyName'=>"{$this->dataset_name}.{$this->table_name}"
                ,'type'=>'bq'
                ,'entity'=>"{$this->dataset_name}.{$this->table_name}"
                ,'extends'=>null
                ,'GroupName'=>'CFOs'
                ,'model'=>[
                    'model'=>[]
                    ,'bq'=>[]
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
                    'fields'=>[]]
                ,'interface'=>[
                    'object'=>"{$this->dataset_name}.{$this->table_name}"
                    ,'name'=>$this->table_name.' Report'
                    ,'plural'=>$this->table_name.' Reports'
                    ,'ico'=>'building'
                    ,'modal_size'=>'xl'
                    ,'secret'=>$this->options['keyFile']??null
                    ,'filters'=>[]
                    ,'buttons'=>[]
                    ,'views'=>['default'=>['name'=>'Default View','all_fields'=>true,'server_fields'=>null,'server_order'=>null,'server_where'=>null,'server_limit'=>200,'fields'=>[]]]
                    ,'display_fields'=>null
                    ,'update_fields'=>null
                    ,'insert_fields'=>null
                    ,'copy_fields'=>null
                    ,'delete_fields'=>null
                    ,'hooks'=>['on.insert'=>[],'on.update'=>[],'on.delete'=>[]]
                ]
            ];

            $is_key = null;
            if($bq_structure = $this->getDataSetTableInfo()['info']['schema']['fields']??null) {
                foreach ($bq_structure as $item) {
                    $cfo['model']['bq'][$item['name']] = $item;

                    $attributues = "index|";
                    if(in_array($item['name'],['id','KeyName','KeyId'])) {
                        $attributues.='isKey|';
                        $is_key=$item['name'];
                    }
                    if($item['mode']=='NULLABLE') $attributues.="allowNull|";
                    $attributues.="description:".str_replace('|',',',$item['description']??'').'|';
                    $cfo['model']['model'][$item['name']] = [strtolower($item['type']),$attributues];

                    $cfo['securityAndFields']['fields'][$item['name']] = ['name'=>$item['name']];
                    if(in_array($item['type'],['DATE','DATETIME','JSON'])) $cfo['securityAndFields']['fields'][$item['name']]['type'] = strtolower($item['type']);
                    $cfo['securityAndFields']['fields'][$item['name']] = ['name'=>$item['name']];

                    if($item['type']=='RECORD') {
                        $cfo['model']['model'][$item['name']][0] = 'json';
                        $cfo['model']['model'][$item['name']][1].= 'record:'.json_encode($item['fields']);
                        $cfo['securityAndFields']['fields'][$item['name']]['type'] = 'json';
                        $cfo['securityAndFields']['fields'][$item['name']]['record'] = json_encode($item['fields']);
                    } else {
                        $cfo['interface']['views']['default']['fields'][$item['name']] = ['field'=>$item['name']];
                    }
                    if($item['mode']=='NULLABLE') $cfo['securityAndFields']['fields'][$item['name']]['allow_empty'] = true;
                }
            }

            if($is_key) {
                $cfo['interface']['buttons'] = [['title'=>"Insert {$this->table_name} Report row",'type'=>'api-insert'],['title'=>"Bulk {$this->table_name} Report rows",'type'=>'api-bulk']];
                $cfo['interface']['display_fields'] = $cfo['interface']['views']['default']['fields'];
                $cfo['interface']['insert_fields'] = $cfo['interface']['views']['default']['fields'];
                $cfo['interface']['update_fields'] = $cfo['interface']['views']['default']['fields'];
                $cfo['interface']['copy_fields'] = $cfo['interface']['views']['default']['fields'];
                $cfo['interface']['delete_fields'] = [$is_key =>$cfo['interface']['views']['default']['fields'][$is_key]];
                $cfo['interface']['views']['default']['fields'][$is_key]['display_cfo'] = true;
                $cfo['interface']['update_fields'][$is_key]['read_only'] = true;

            }



            return $cfo;
        }
    }
}

include_once __DIR__.'/ValueMapper.php';