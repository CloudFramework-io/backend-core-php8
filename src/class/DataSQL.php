<?php

/**
 * [$sql = $this->core->loadClass('DataSQL');] Class to Handle SQL databases with CloudFramework models
 * @package CoreClasses
 */
class DataSQL
{
    /** @var Core7  */
    var $core;
    var $error = false;
    var $errorMsg = '';
    var $entity_schema = null;
    var $entity_name = null;
    var $keys = [];
    var $fields = [];
    var $mapping = [];
    private $use_mapping = false;
    var $limit = 0;
    var $page = 0;
    var $offset = 0;
    var $order = '';
    var $debug = false;
    var $dbConnections = [];
    var $dbCredentials = [];
    var $default_time_zone_to_read = 'UTC';
    var $default_time_zone_to_write = 'UTC';
    private $joins = [];
    private $queryFields = '';
    private $queryWhere = [];
    private $extraWhere = '';
    private $virtualFields = [];
    private $groupBy = '';
    private $view = null;

    /**
     * DataSQL constructor.
     * @param Core7 $core
     * @param array $params where [0] is the table name and [1] is the model ['model'=>[],'mapping'=>[], etc..], 2 optionally is the connection name to have more than one connection
     */
    function __construct(Core7 &$core, array $params)
    {

        //region SET $this->core = core
        $this->core = $core;
        //endregion

        //region SET $this->debug = true; by default when we are developing in localhost
        if($this->core->is->development()) {
            $this->debug = true;
        }
        //endregion

        //region CHECK $params has the right structure
        if(!isset($params[0]) || !isset($params[1]['model']))
            return($this->addError('Missing $params with the folling structure: [{table_name},[{model=>[]}]]'));
        //endregion

        //region SET $this->entity_name
        $this->entity_name = $params[0];
        if(!is_string($this->entity_name) || !strlen($this->entity_name)) return($this->addError('Missing schema name '));
        //endregion

        //region SET $this->entity_schema and verify that exist any Key in the model
        $this->entity_schema = $params[1];
        if(!is_array($this->entity_schema)) return($this->addError('Missing schema in '.$this->entity_name));
        foreach ($this->entity_schema['model'] as $field =>$item) {
            if(stripos($item[1],'isKey')!==false) {
                $this->keys[] = [$field,(stripos($item[0],'int')!== false
                    || stripos($item[0],'bit')!== false
                    || stripos($item[0],'float')!== false
                    || stripos($item[0],'double')!== false
                    || stripos($item[0],'decimal')!== false
                    || stripos($item[0],'number')!== false)?'int':'char'];
            }

            // Detect numbers
            $this->fields[$field] = (stripos($item[0],'int')!== false
                || stripos($item[0],'bit')!== false
                || stripos($item[0],'float')!== false
                || stripos($item[0],'double')!== false
                || stripos($item[0],'number')!== false
                || stripos($item[0],'decimal')!== false
            )?'int':'char';
        }
        if(!count($this->keys)) return($this->addError('Missing Keys in the schema: '.$this->entity_name));
        //endregion

        //region SET Default TimeZone from Core
        $this->default_time_zone_to_read = $this->core->system->time_zone[0];
        //endregion

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
        $this->errorMsg = '';
    }

    /**
     * Return the fields defined for the table in the schema
     * @return array|null
     */
    function getFields() {
        return array_keys($this->fields);
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
            if($this->use_mapping)
                $fields = $this->getMappingFields();
            else
                $fields = $this->getFields();
        }
        if(!$this->use_mapping || !count($this->mapping)) {
            $ret='';
            foreach ($fields as $i=>$field) {
                if($ret) $ret.=',';
                if(strpos($field,'(')!==false) {
                    if(strpos($field,'(*')===false)
                        $ret.=str_replace('(','('.$this->entity_name.'.',$field);
                    else
                        $ret.=$field;
                } else {
                    //JSON workaround https://bugs.php.net/bug.php?id=70384
                    if(isset($this->entity_schema['model'][$field][0]) && $this->entity_schema['model'][$field][0]=='json') {
                        $ret.='CAST('.$this->entity_name.'.'.$field.' as CHAR) as '.$field;
                    }elseif(isset($this->entity_schema['model'][$field][0])) {
                        $ret.=$this->entity_name.'.'.$field;
                    }else{
                        $ret.=$field;
                    }
                }

            }
            return $ret;
            //$this->entity_name.'.'.implode(','.$this->entity_name.'.',$fields);
        }
        else {
            $ret = '';
            foreach ($this->mapping as $field=>$fieldMapped) {
                if(null != $fields && !in_array($fieldMapped,$fields)) continue;

                if($this->view && (!isset($this->entity_schema['mapping'][$fieldMapped]['views']) || !in_array($this->view,$this->entity_schema['mapping'][$fieldMapped]['views']))) continue;
                if($ret) $ret.=',';
                $ret .= "{$this->entity_name}.{$field} AS {$fieldMapped}";
            }
            return $ret;
        }
    }

    /**
     * Return one record based on a key
     * @param $key can ba an string or number
     * @param string $fields if null $fields = $this->getFields()
     */
    function fetchOneByKey($key, string $fields='') {
        if(is_array($key) || !$key) return [];
        $ret = $this->fetchByKeys([$key],$fields);
        if($ret) $ret= $ret[0];
        return $ret;
    }

    /**
     * Return the tuplas with the $keyWhere including $fields
     * @param $keysWhere
     * @param string $fields if null $fields = $this->getFields()
     */
    function fetchByKeys($keysWhere, $fields='') {
        if($this->error) return;

        // Keys to find
        if(!is_array($keysWhere)) $keysWhere = [$keysWhere];

        // Where condition for the SELECT
        $where = ''; $params = [];
        foreach ($this->keys as $i=>$key) {

            if($where) $where.=' AND ';
            $where.=" {$this->entity_name}.{$key[0]} IN ( ";
            $values = '';
            foreach ($keysWhere as $keyWhere) {
                if(!is_array($keyWhere)) $keyWhere = [$keyWhere];
                if($values) $values.=', ';
                if($key[1]=='int') $values.='%s';
                else $values.="'%s'";
                $params[] = $keyWhere[$i];
            }

            $where.= "{$values} )";
        }

        // Fields to returned
        $sqlFields = $this->getQuerySQLFields($fields);
        $from = $this->getQuerySQLFroms();

        // Query
        $SQL = "SELECT {$sqlFields} FROM {$from} WHERE {$where}";
        if(!$sqlFields) return($this->addError('No fields to select found: '.json_encode($fields)));

        return $this->core->model->dbQuery($this->entity_name.' fetch by querys: '.json_encode($keysWhere),$SQL,$params,$this->entity_schema['model']);

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
    function fetchOne($keysWhere=[], $fields=null, $params=[]) {
        $this->limit = 1;
        $ret = $this->fetch($keysWhere, $fields, $params);
        if($ret) $ret=$ret[0];
        return($ret);
    }
    /**
     * Return records [0..n][record_structure] from the db object
     * @param array|string $keysWhere where condition can be a string or array [key=>value, key=>value]
     * @param null $fields fields to be returned
     * @return array|void
     */
    function fetch($keysWhere=[], $fields=null, $params=[]) {

        if($this->error) return false;

        //region SET $where
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
        //endregion

        //region SET $sqlFields
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
        //endregion

        //region SET $SQL with $where && $sqlFields
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
        if($this->groupBy) {
            $SQL .= " GROUP BY  {$this->groupBy}";
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
        //endregion

        //region READ $ret with $SQL
        $ret= $this->core->model->dbQuery($this->entity_name.' fetch by querys: '.json_encode($keysWhere),$SQL,$params,$this->entity_schema['model']);

        if($this->core->model->error) $this->addError($this->core->model->errorMsg);
        //endregion

        //region EVALUATE to change datetime & timestamp to read timezone
        if($ret && $this->default_time_zone_to_write!=$this->default_time_zone_to_read)
            foreach ($ret as $i=>$item)
                $this->convertDateFieldsReadValuesIntoTimeZoneValues($ret[$i]);
        //endregion

        return($ret);
    }

    /**
     * Update a record in db
     * @param $data
     * @return bool|null|void
     */
    public function update(&$data) {
        if(!is_array($data) ) return($this->addError('update($data) $data has to be an array with key->value'));

        if(!$this->convertDateFieldsToWriteValuesIntoTimeZoneValues($data)) return;

        // Let's convert from Mapping into SQL fields
        if($this->use_mapping) {
            $mapdata = $data;
            $data = [];
            foreach ($mapdata as $key=>$value) {
                if(!isset($this->entity_schema['mapping'][$key]['field'])) return($this->addError('update($data) $data contains a wrong mapped key: '.$key));
                $data[$this->entity_schema['mapping'][$key]['field']] = $value;
            }
        }

        $ret= $this->core->model->dbUpdate($this->entity_name.' update record: '.$this->entity_name,$this->entity_name,$data);
        if($this->core->model->error) $this->addError($this->core->model->errorMsg);
        return($ret);

    }

    /**
     * Update a record in db
     * @param $data
     * @return bool|null|void
     */
    public function upsert($data) {
        if(!is_array($data) ) return($this->addError('upsert($data) $data has to be an array with key->value'));

        if(!$this->convertDateFieldsToWriteValuesIntoTimeZoneValues($data)) return;

        // Let's convert from Mapping into SQL fields
        if($this->use_mapping) {
            $mapdata = $data;
            $data = [];
            foreach ($mapdata as $key=>$value) {
                if(!isset($this->entity_schema['mapping'][$key]['field'])) return($this->addError('upsert($data) $data contains a wrong mapped key: '.$key));
                $data[$this->entity_schema['mapping'][$key]['field']] = $value;
            }
        }

        $ret= $this->core->model->dbUpSert($this->entity_name.' upsert record: '.$this->entity_name,$this->entity_name,$data);
        if($this->core->model->error) $this->addError($this->core->model->errorMsg);
        return($ret);

    }

    /**
     * Update a record in db
     * @param $data
     * @return bool|null|void
     */
    public function insert($data) {
        if(!is_array($data) ) return($this->addError('insert($data) $data has to be an array with key->value'));

        if(!$this->convertDateFieldsToWriteValuesIntoTimeZoneValues($data)) return;

        // Let's convert from Mapping into SQL fields
        if($this->use_mapping) {
            $mapdata = $data;
            $data = [];
            foreach ($mapdata as $key=>$value) {
                if(!isset($this->entity_schema['mapping'][$key]['field'])) return($this->addError('insert($data) $data contains a wrong mapped key: '.$key));
                $data[$this->entity_schema['mapping'][$key]['field']] = $value;
            }
        }

        try {
            $ret= $this->core->model->dbInsert($this->entity_name.' insert record: '.$this->entity_name,$this->entity_name,$data);
            if($this->core->model->error) return $this->addError($this->core->model->errorMsg);
        }catch (Exception $e) {
            return $this->addError($e->getMessage());
        }
        return($ret);

    }

    /**
     * Delete a record in db
     * @param $data
     * @return bool|null|void
     */
    public function delete($data) {
        if(!is_array($data) ) return($this->addError('delete($data) $data has to be an array with key->value'));

        // Let's convert from Mapping into SQL fields
        if($this->use_mapping) {
            $mapdata = $data;
            $data = [];
            foreach ($mapdata as $key=>$value) {
                if(!isset($this->entity_schema['mapping'][$key]['field'])) return($this->addError('delete($data) $data contains a wrong mapped key: '.$key));
                $data[$this->entity_schema['mapping'][$key]['field']] = $value;
            }
        }

        $ret= $this->core->model->dbDelete($this->entity_name.' delete record: '.$this->entity_name,$this->entity_name,$data);
        if($this->core->model->error) $this->addError($this->core->model->errorMsg);
        return($ret);

    }

    /**
     * Convert "now" values in ['date','datetime','timestamp'] into TimeZoneValue before to update or insert
     * @param $data
     * @return bool
     */
    private function convertDateFieldsToWriteValuesIntoTimeZoneValues(&$data) {
        try {
            $dt = new DateTime("now", new DateTimeZone($this->default_time_zone_to_write));
            $dt_r = new DateTime("now", new DateTimeZone($this->default_time_zone_to_read));
            $tz_r = new DateTimeZone($this->default_time_zone_to_read);
            $tz_w = new DateTimeZone($this->default_time_zone_to_write);


            foreach ($data as $key=>$datum) if($datum && in_array(($this->entity_schema['model'][$key][0]??null),['date','datetime','timestamp']) && $datum){
                if($datum=='now') {
                    if ($this->entity_schema['model'][$key][0] == 'date')
                        $data[$key] = $dt_r->format('Y-m-d');
                    else
                        $data[$key] = $dt->format('Y-m-d H:i:s');
                } elseif($this->default_time_zone_to_read != $this->default_time_zone_to_write && in_array(($this->entity_schema['model'][$key][0]??null),['datetime','timestamp'])) {
                    $dt_w = new DateTime($datum, $tz_r);
                    $dt_w->setTimezone($tz_w);
                    $data[$key] = $dt_w->format('Y-m-d H:i:s');
                }
            }
            return true;
        } catch (Exception $e) {
            $this->addError($e->getMessage());
            return false;
        }
    }

    /**
     * Convert "now" values in ['date','datetime','timestamp'] into TimeZoneValue
     * @param $data
     */
    private function convertDateFieldsReadValuesIntoTimeZoneValues(&$data) {
        if($this->default_time_zone_to_read == $this->default_time_zone_to_write) return true;

        try {
            $tz_w = new DateTimeZone($this->default_time_zone_to_write);
            $tz_r = new DateTimeZone($this->default_time_zone_to_read);
            foreach ($data as $key=>$datum) if($datum && in_array(($this->entity_schema['model'][$key][0]??null),['datetime','timestamp'])) {
                $dt = new DateTime($datum, $tz_w);
                $dt->setTimezone($tz_r);
                $data[$key] = $dt->format('Y-m-d H:i:s');
            }
            return true;
        } catch (Exception $e) {
            $this->addError($e->getMessage());
            return false;
        }
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
                $this->order.= $this->entity_name.'.'.$field.((strtoupper(trim($type))=='DESC')?' DESC':' ASC');
            } else {
                $this->addError($field.' does not exist to order by');
            }
        }

    }


    /**
     * Build a query taking $keysWhere and applying rules depending of each field type
     * @param array $keysWhere
     * @return array|void
     */
    function getQuerySQLWhereAndParams($keysWhere=[]) {
        if(!is_array($keysWhere) ) return($this->addError('getQuerySQLWhereAndParams($keysWhere) $keyWhere has to be an array with key->value'));

        // Where condition for the SELECT
        $where = ''; $params = [];

        // Custom query rewrites previous where.
        if(!count($keysWhere)) $keysWhere = $this->queryWhere;

        // Create Time zones
        $tz_r = new DateTimeZone($this->default_time_zone_to_read);
        $tz_w = new DateTimeZone($this->default_time_zone_to_write);

        // Loop the wheres
        if(is_array($keysWhere))
        foreach ($keysWhere as $key=>$value) {

            //region IF $key is a query. Ex: ['(a=>%s)'=>[5]] add it in $where and CONTINUE to next foreach
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
            //endregion

            //region INIT $comp and EVALUATE to add in $where.=' AND'
            $comp = '=';  // default comparator;
            if($where) $where.=' AND ';
            //endregion


            //region EVALUATE if $key includes
            if (preg_match('/[=><]/', $key)) {
                if (strpos($key, '>=') === 0 || strpos($key, '<=') === 0 || strpos($key, '!=') === 0) {
                    $comp = substr($key, 0, 2);
                    $key = trim(substr($key, 2));
                } elseif (strpos($key, '>') === 0 || strpos($key, '<') === 0 || strpos($key, '<') === 0) {
                    $comp = substr($key, 0, 1);
                    $key = trim(substr($key, 1));
                }
            }
            //endregion

            // Simple where
            if($this->use_mapping) {
                if(!isset($this->entity_schema['mapping'][$key]['field'])) return($this->addError('fetch($keysWhere, $fields=null) $keyWhere contains a wrong mapped key: '.$key));
                $key = $this->entity_schema['mapping'][$key]['field'];
            } else {
                if(!isset($this->fields[$key])) return($this->addError('fetch($keysWhere, $fields=null) $keyWhere contains a wrong key: '.$key));
            }


            switch ((!is_array($value))?strval($value):'default') {
                case "__null__":
                    $where.="{$this->entity_name}.{$key} IS NULL";
                    break;
                case "__notnull__":
                    $where.="{$this->entity_name}.{$key} IS NOT NULL";
                    break;
                case "__empty__":
                    $where.="{$this->entity_name}.{$key} = ''";
                    break;
                case "__noempty__":
                    $where.="{$this->entity_name}.{$key} != ''";
                    break;
                default:
                    // IN
                    if(is_array($value)) {
                        if($this->fields[$key]=='int') {
                            $where.="{$this->entity_name}.{$key} IN (%s)";
                            $params[] = implode(',',$value);
                        }
                        else {
                            // Securing slashed
                            $value = array_map(function($str) {
                                return addslashes($str);
                            }, $value);

                            // Add an IN
                            $where.="{$this->entity_name}.{$key} IN ('".implode("','",$value)."')";
                            //$params[] = implode("','",$value);

                        }
                    }
                    // =
                    else {
                        if($this->fields[$key]=='int') {
                            // ALLOW to change comparator with the value
                            if(strpos($value,'>=')===0) {
                                $comp='>=';
                                $value = str_replace('>=','',$value);
                            }elseif(strpos($value,'<=')===0) {
                                $comp='<=';
                                $value = str_replace('<=','',$value);
                            }elseif(strpos($value,'>')===0) {
                                $comp='>';
                                $value = str_replace('>','',$value);
                            }elseif(strpos($value,'<')===0) {
                                $comp='<';
                                $value = str_replace('<','',$value);
                            }elseif(strpos($value,'!=')===0) {
                                $comp='!=';
                                $value = str_replace('!=','',$value);
                            }

                            if(preg_match('/[^0-9\+\-\.]/',$value))
                                $value = preg_replace('/[^0-9\+\-\.]/','',$value);

                            if(strlen($value))
                                $where.="{$this->entity_name}.{$key} {$comp} %s";
                            else {
                                break;
                            }
                        }
                        else {
                            if(strpos($value,'%')!==false) {
                                if(strpos($value,'>=')===0) {
                                    $comp='>=';
                                    $value = str_replace('>=','',$value);
                                }elseif(strpos($value,'<=')===0) {
                                    $comp='<=';
                                    $value = str_replace('<=','',$value);
                                }elseif(strpos($value,'>')===0) {
                                    $comp='>';
                                    $value = str_replace('>','',$value);
                                }elseif(strpos($value,'<')===0) {
                                    $comp='<';
                                    $value = str_replace('<','',$value);
                                }elseif(strpos($value,'!=')===0) {
                                    $comp='!=';
                                    $value = str_replace('!=','',$value);
                                }

                                if($comp=='!=') $comp='not like';
                                else $comp='like';
                            }elseif(in_array(($this->entity_schema['model'][$key][0]??null),['date','datetime','timestamp'])) {

                                if(strpos($value,'>=')===0) {
                                    $comp='>=';
                                    $value = str_replace('>=','',$value);
                                }elseif(strpos($value,'<=')===0) {
                                    $comp='<=';
                                    $value = str_replace('<=','',$value);
                                }elseif(strpos($value,'>')===0) {
                                    $comp='>';
                                    $value = str_replace('>','',$value);
                                }elseif(strpos($value,'<')===0) {
                                    $comp='<';
                                    $value = str_replace('<','',$value);
                                }elseif(strpos($value,'!=')===0) {
                                    $comp='!=';
                                    $value = str_replace('!=','',$value);
                                }

                                if($this->default_time_zone_to_read != $this->default_time_zone_to_write) {
                                    try {
                                        $dt_w = new DateTime($value, $tz_r);
                                        $dt_w->setTimezone($tz_w);
                                        if($this->entity_schema['model'][$key][0]=='date')
                                            $value = $dt_w->format('Y-m-d');
                                        else
                                            $value = $dt_w->format('Y-m-d H:i:s');
                                    } catch (Exception $e) {
                                        $this->addError($e->getMessage());
                                        return false;
                                    }
                                }
                            }
                            $where.="{$this->entity_name}.{$key} {$comp} '%s'";
                        }

                        $params[] = $value;
                    }

                    break;

            }

        }

        // Search into Joins queries
        foreach ($this->joins as $join) {
            /** @var DataSQL $object */
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
        if($ret=='*') $ret=$this->entity_name.'.*';

        foreach ($this->joins as $i=>$join) {

            /** @var DataSQL $object */
            $object = $join[1];
            $ret.=','.str_replace($object->entity_name.'.',"_j{$i}.",$object->getQuerySQLFields());

        }

        return $ret;
    }

    function getQuerySQLFroms() {
        $from = $this->entity_name;
        foreach ($this->joins as $i=>$join) {
            /** @var DataSQL $object */
            $object = $join[1];
            $from.=" {$join[0]}  JOIN {$object->entity_name} _j{$i} ON ({$this->entity_name}.{$join[2]} = _j{$i}.{$join[3]})";
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
     * @param DataSQL $object
     * @param $first_field string field of the local object to join with
     * @param $join_field string field of the join object to match
     * @param $extraon string any other extra condition
     */
    function join ($type, DataSQL &$object, $first_field, $join_field,$extraon=null) {
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
        $this->core->errors->add(['DataSQL'=>$value]);
    }

    /**
     * Return the last query executed
     * @return string|null
     */
    function getDBQuery() {
        if(!is_object($this->core->model->db)) return null;

        return($this->core->model->db->getQuery());
    }

    /**
     * Return last time spent y last query
     * @return int|null
     */
    function getDBQueryTime() {
        if(!is_object($this->core->model->db)) return null;

        return($this->core->model->db->_lastExecutionMicrotime);
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
        if($this->use_mapping) {
            if(!isset($this->entity_schema['mapping']) || !count($this->entity_schema['mapping'])) return($this->addError('getCheckedArrayToInsert: There is not mapping into '.$this->entity_name));
            $schema_to_validate =  $this->entity_schema['mapping'];
        }
        else foreach ($this->entity_schema['model'] as $field=>$item) {
            list($type,$foo) = explode('|',$item[1],2);
            $schema_to_validate[$field] = ['type'=>$type,'validation'=>$item[1]];
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
            $this->addError($this->entity_name.': error validating Data in Model.: {'.$dv->field.'}. '.$dv->errorMsg);
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
     * Return the json schema based on the table in the database
     * @return mixed|null
     */
    public function getSimpleModelFromTable() {
        if(!$this->core->model->dbInit()) return;
        return($this->core->model->db->getSimpleModelFromTable($this->entity_name));
    }

}
