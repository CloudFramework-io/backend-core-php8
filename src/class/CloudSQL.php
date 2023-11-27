<?php

/**
 * @ignore
 */
class CloudSQLError extends Exception {
    public function __construct() {
        list(
            $this->code,
            $this->message,
            $this->file,
            $this->line) = func_get_args();
    }
}

// CloudSQL Class v10
if (!defined ("_MYSQLI_CLASS_") ) {
    define ("_MYSQLI_CLASS_", TRUE);


    /**
     * @ignore
     */
    class CloudSQLQueryObject {

        var $data = array();
        var $table = '';
        var $selectFields = '';
        var $order = '';
        var $extraWhere = '';

        function __construct ($data,$table='') {
            $this->data = $data;
            $this->table = $table;
        }
    }

    /**
     *  [$sql = $this->core->loadClass('CloudSQL')] Class to handle CloudSQL (mysql) interactions.
     *  Feel free to use a distribute it.
     *  last-update 2021-11-17
     * @package CoreClasses
     */
    class CloudSQL {
        // Base variables
        var $_error=[];                      // Holds the last error
        var $_lastRes=false;                                        // Holds the last result set
        var $_lastQuery='';                                        // Holds the last result set
        var $_lastExecutionMicrotime = 0;
        var $_lastInsertId='';                                        // Holds the last result set
        var $_affectedRows=null;
        var $result;                                                // Holds the MySQL query result
        var $records;                                                // Holds the total number of records returned
        var $affected;                                        // Holds the total number of records affected
        var $rawResults;                                // Holds raw 'arrayed' results
        var $arrayedResult;                        // Holds an array of the result

        /** @var mysqli*/
        var $_db;
        var $_dbserver;        // MySQL Hostname
        var $_dbuser;        // MySQL Username
        var $_dbpassword;        // MySQL Password
        var $_dbdatabase;        // MySQL Database
        var $_dbsocket;        // MySQL Database
        var $_dbport = '3306';        // MySQL Database
        var $_dbcharset = '';        // MySQL Charset. Ex: utf8mb4
        var $_dbtype = 'mysql';
        var $_limit = 10000;
        var $_page = 0;
        var $_qObject = array();
        var $_cloudDependences = array();
        var $_cloudReferalFields = array();
        var $_cloudAutoSelectFields = array();
        var $_cloudWhereFields = array();
        var $_cloudFilterWhereFields = array();
        var $_cloudFilterToAvoidCalculation = array();
        var $_queryFieldTypes = array();
        var $cfmode = true;
        var $_dbProxy = null;
        var $_dbProxyHeaders = null;
        var $_onlyCreateQuery = false;

        protected $core = null;

        protected $_dblink=false;                // Database Connection Link
        var $_debug=false;

        /**
         * CLOUD SQL Constructor
         * @param Core7 $core
         * @param string $h
         * @param string $u
         * @param string $p
         * @param string $db
         * @param string $port
         * @param string $socket
         * @param string $charset
         */
        function __construct (Core7 &$core,$h='',$u='',$p='',$db='',$port='3306',$socket='',$charset='') {

            $this->core = $core;

            if(strlen($h??'')) {
                $this->_dbserver = trim($h??'');
                $this->_dbuser = trim($u??'');
                $this->_dbpassword = trim($p??'');
                $this->_dbdatabase = trim($db??'');
                $this->_port = trim($port??'');
                $this->_dbsocket = trim($socket??'');
                $this->_dbcharset = trim($charset??'');
            }  else if(strlen( trim($this->core->config->get("dbServer")??''))  || trim($this->core->config->get("dbSocket")??'')) {
                // It load from $this->core->config->get("vars"): dbServer,dbUser,dbPassword,dbName,dbSocket,dbPort,dbPort
                $this->loadCoreConfigVars();
                // It rewrites: $this->_dbserver, $this->_dbuser,$this->_dbpassword,$this->_dbdatabase ,$this->_dbsocket, $this->_dbport
            }
            if(!strlen(($this->_dbserver??'').($this->_dbsocket??''))) $this->_dbserver='127.0.0.1';
            if(!strlen($this->_dbdatabase??'')) $this->_dbdatabase='mysql';

            /*
            set_error_handler(create_function(
                '$errno, $errstr, $errfile, $errline',
                'throw new CloudSQLError($errno, $errstr, $errfile, $errline);'
            ),E_WARNING);
            */
        }

        function loadCoreConfigVars() {
            $this->_dbserver = trim($this->core->config->get("dbServer")??'');
            $this->_dbuser = trim($this->core->config->get("dbUser")??'');
            $this->_dbpassword = trim($this->core->config->get("dbPassword")??'');
            $this->_dbdatabase = trim($this->core->config->get("dbName")??'');
            $this->_dbsocket = trim($this->core->config->get("dbSocket")??'');
            $this->_dbProxy = trim($this->core->config->get("dbProxy")??'');
            $this->_dbProxyHeaders = $this->core->config->get("dbProxyHeaders")??'';
            $this->_dbcharset = ($this->core->config->get("dbCharset")??null)?$this->core->config->get("dbCharset"):'';
            if(strlen(trim($this->core->config->get("dbPort")??'')))
                $this->_dbport = trim($this->core->config->get("dbPort")??'');

        }

        function setConf($var,$value) {
            switch ($var) {
                case 'dbServer':$this->_dbserver = $value; break;
                case 'dbUser':$this->_dbuser = $value; break;
                case 'dbPassword':$this->_dbpassword = $value; break;
                case 'dbName':$this->_dbdatabase = $value; break;
                case 'dbSocket':$this->_dbsocket = $value; break;
                case 'dbPort':$this->_dbport = $value; break;
                case 'dbCharset':$this->_dbcharset = $value; break;
                case 'dbProxy':$this->_dbProxy = $value; break;
                case 'dbProxyHeaders':$this->_dbProxyHeaders = $value; break;
                default:
                    $this->setError('Unknown "confVar". Please use: dbServer, dbUer, dbPassword, dbName, dbSocket, dbPort, dbCharset, dbProxyHeaders');
                    break;
            }
        }

        function getConf($var) {
            $ret ='';
            switch ($var) {
                case 'dbServer':$ret = $this->_dbserver; break;
                case 'dbUer':$ret = $this->_dbuser; break;
                case 'dbPassword':$ret = $this->_dbpassword; break;
                case 'dbName':$ret = $this->_dbdatabase; break;
                case 'dbSocket':$ret = $this->_dbsocket; break;
                case 'dbPort':$ret = $this->_dbport; break;
                case 'dbCharset':$ret = $this->_dbcharset; break;
                case 'dbProxy':$ret = $this->_dbProxy; break;
                case 'dbProxyHeaders':$ret = $this->_dbProxyHeaders; break;
                default:
                    $ret = 'Unknown "confVar". Please use: dbServer, dbUer, dbPassword, dbName, dbSocket, dbPort, dbProxyHeaders';
                    break;
            }
            return($ret);
        }

        /**
         * @param string $h Host
         * @param string $u User
         * @param string $p Password
         * @param string $db DB Name
         * @param string $port Port. Default 3306
         * @param string $socket Socket
         * @param string $charset Allow to specify a charste. Ex:
         * @return bool True if connection is ok.
         */
        function connect($h='',$u='',$p='',$db='',$port="3306",$socket='',$charset='') {

            if($this->_dbProxy) return true; // avoid to stablish a connection with dbproxy
            if($this->_dblink)  return($this->_dblink); // Optimize current connection.

            if(strlen($h)) {
                $this->_dbserver = $h;
                $this->_dbuser = $u;
                $this->_dbpassword = $p;
                $this->_dbdatabase = $db;
                $this->_dbport = $port;
                $this->_dbsocket = $socket;
                $this->_dbcharset = $charset;
            }

            if(strlen($this->_dbserver??'') || strlen($this->_dbsocket??'')) {
                try {
                    if(strlen($this->_dbsocket??''))
                        $this->_db = new mysqli(null, $this->_dbuser, $this->_dbpassword, $this->_dbdatabase, 0,$this->_dbsocket);
                    else
                        $this->_db = new mysqli($this->_dbserver, $this->_dbuser, $this->_dbpassword, $this->_dbdatabase, $this->_dbport);

                    if($this->_db->connect_error)  $this->setError('Connect Error to: '.((strlen($this->_dbsocket))?$this->_dbsocket:$this->_dbserver).' (' . $this->_db->connect_errno . ') '. $this->_db->connect_error);
                    else $this->_dblink = true;

                    if($this->_dbcharset)
                        $this->_db->set_charset($this->_dbcharset);

                } catch (Exception $e) {
                    $err = 'Connect Error to: '.((strlen($this->_dbsocket??''))?$this->_dbsocket:$this->_dbserver);
                    if(is_object($this->_db)) $err.=' (' . $this->_db->connect_errno . ') ';
                    if(isset($mysqli) && is_object($mysqli)) $err .=$mysqli->connect_error;
                    if($this->core->is->development()) {
                        $err.= " [User:{$this->_dbuser}  Password:".substr($this->_dbpassword??'',0,2).'***]';
                    }
                    $this->setError($err);
                }
            } else {
                $this->setError("No DB server or DB name provided. ");
            }
            $this->core->__p->add('db connect. Class:'.__CLASS__,__FILE__);

            // Read dates with current timezone.
            // This will only do automatic conversions for TIMESTAMP fields and affect the results of NOW() and CURDATE() functions, but not DATE, TIME, and DATETIME fields
            //if(!$this->error()) $this->command("set time_zone='%s'",array(date("P")));

            return($this->_dblink);
        }


        // It requires at least query argument
        /*
         * Execute a Query
         */
        function getDataFromQuery() {

            $this->_lastExecutionMicrotime = 0;
            $_q = $this->_buildQuery(func_get_args());
            if($this->_onlyCreateQuery) return [];
            $start_global_time = microtime(true);

            if($this->error()) {
                return(false);
            } else {
                $ret=array();

                // Do a call a CloudFrameworkProxy
                if($this->_dbProxy) {
                    $this->core->__p->add('getDataFromQueryProxy ',$_q,'note');
                    $ret = $this->core->request->post($this->_dbProxy,['q'=>$_q],$this->_dbProxyHeaders);
                    if($this->core->request->error) {
                        $this->core->__p->add('getDataFromQueryProxy ','','endnote');
                        $this->setError($this->core->request->errorMsg);
                        $this->core->request->reset();
                        return(false);
                    }
                    $ret = unserialize(gzuncompress($ret));
                    $this->core->__p->add('getDataFromQueryProxy ','','endnote');
                    $this->_lastExecutionMicrotime = round(microtime(true)-$start_global_time,4);
                    return $ret;
                }
                // Do a call Directly to DB connection
                else {
                    $this->core->__p->add('getDataFromQuery ',$_q,'note');
                    try {
                        if( ($this->_lastRes = $this->_db->query($_q)) ) {

                            $this->_affectedRows = $this->_db->affected_rows;
                            if(is_object($this->_lastRes)){
                                while ($fila = $this->_lastRes->fetch_assoc( )) {
                                    $ret[] = $fila;
                                }

                                $this->_lastRes->close();
                            }
                            $this->_lastRes = false;
                        } else {
                            $this->setError('Query Error [$q]: ' . $this->_db->error);
                        }
                        $this->core->__p->add('getDataFromQuery ','','endnote');
                        $this->_lastExecutionMicrotime = round(microtime(true)-$start_global_time,4);
                        return($ret);
                    } catch (Exception $e) {
                        $this->core->__p->add('getDataFromQuery ','','endnote');
                        $this->setError('Query Error [$q]: ' . $e->getMessage());
                        $this->_lastExecutionMicrotime = round(microtime(true)-$start_global_time,4);
                        return(false);
                    }
                }
            }
        }

        // It requires at least query argument
        function command() {
            $this->_lastExecutionMicrotime = 0;
            $_q = $this->_buildQuery(func_get_args());
            if($this->_onlyCreateQuery) return true;
            $start_global_time = microtime(true);

            $this->core->__p->add('command ',$_q,'note');
            if($this->error()) {
                return(false);
            } else {

                // Do a call a CloudFrameworkProxy
                if($this->_dbProxy) {
                    $this->core->__p->add('commandProxy ',$_q,'note');
                    $ret = $this->core->request->post($this->_dbProxy,['q'=>$_q],$this->_dbProxyHeaders);
                    if($this->core->request->error) {
                        $this->core->__p->add('getDataFromQueryProxy ','','endnote');
                        $this->setError('QueryProxy Error [$q]: ' . json_encode($this->core->request->errorMsg));
                        $this->core->request->reset();
                        return(false);
                    }
                    $ret = unserialize(gzuncompress($ret));
                    $this->core->__p->add('commandProxy ','','endnote');
                    $this->_lastExecutionMicrotime = round(microtime(true)-$start_global_time,4);
                    return $ret;
                }
                else {
                    if( ($this->_lastRes = $this->_db->query($_q)) ) {
                        $_ok=true;
                        $this->_lastInsertId = $this->_db->insert_id;
                        $this->_affectedRows = $this->_db->affected_rows;
                        if(is_object($this->_lastRes)) {
                            $this->_lastRes->close();
                        }
                        $this->_lastRes = false;
                    } else {
                        $_ok = false;
                        $this->setError('Query Error [$q]: ' .  $this->_db->error);
                    }
                    $this->core->__p->add('command ','','endnote');
                    $this->_lastExecutionMicrotime = round(microtime(true)-$start_global_time,4);
                    return($_ok);
                }

            }
        }

        /**
         * Check if the table exist avoiding to consult the schema because it is very slow.
         * @param $table
         * @return bool
         */
        function tableExists($table) {

            // Check if a db connection exists
            if(!$this->_dbProxy && !$this->_dblink) {
                $this->setError('connection with db not stablished');
                return false;
            }

            $this->_lastExecutionMicrotime = 0;
            $_q = $this->_buildQuery(array("SELECT 1 FROM %s",$table));
            if($this->_onlyCreateQuery) return true;
            $start_global_time = microtime(true);

            if($this->_dbProxy) {
                $this->core->__p->add('tableExistsProxy ',$_q,'note');
                $ret = $this->core->request->post($this->_dbProxy,['q'=>$_q],$this->_dbProxyHeaders);
                if($this->core->request->error) {
                    $this->core->__p->add('tableExistsProxy ','','endnote');
                    $this->setError('QueryProxy Error [$q]: ' . json_encode($this->core->request->errorMsg));
                    $this->core->request->reset();
                    return(false);
                }
                $ret = unserialize(gzuncompress($ret));
                $this->core->__p->add('tableExistsProxy ','','endnote');
                $this->_lastExecutionMicrotime = round(microtime(true)-$start_global_time,4);
                if( $ret ) {
                    return true;
                } else {
                    return false;
                }
            }
            else {
                if( ($this->_lastRes = $this->_db->query($_q)) ) {
                    $this->_lastExecutionMicrotime = round(microtime(true)-$start_global_time,4);
                    return true;
                } else {
                    $this->_lastExecutionMicrotime = round(microtime(true)-$start_global_time,4);
                    return false;
                }
            }

            /*
            $_q = "SELECT count(*) TOT FROM INFORMATION_SCHEMA.TABLES t WHERE t.TABLE_SCHEMA='%s' AND TABLE_NAME = '%s' ";
            $tmp = $this->getDataFromQuery($_q,$this->_dbdatabase,$table );
            return($tmp[0][TOT]==1);
            */

        }

        /**
         * Build a Query receiving a string with %s as first parameter and an array of values as second paramater
         * @param $args
         * @return array|false|mixed|string|string[]|null
         */
        function _buildQuery($args) {

            if(!$this->_dbProxy && !$this->_dblink ) {
                $this->setError("No db connection");
                return false;
            }
            if(!is_array($args)) {
                $this->setError("_buildQuery requires an array");
                return(false);
            }


            $qreturn = "";

            $q = array_shift($args);

            if(!strlen($q)) {
                $this->setError("Function requires at least the query parameter");
                return(false);
            } else {
                $n_percentsS = substr_count($q,'%s');
                if(count($args)==1 && is_array($args[0])) {
                    $params = $args[0];

                } else {
                    if(count($args)==1 && !strlen($args[0]??'')) $params = array();
                    else $params = $args;
                }
                unset($args);


                if(count($params) && count($params) != $n_percentsS) {
                    $this->setError("Number of %s ($n_percentsS) doesn't count match with number of arguments (".count($params)."). Query: $q -> ".print_r($params,true));
                    return(false);
                } else {
                    if(!count($params) || $n_percentsS == 0 ) $qreturn = $q;
                    else {
                        $qreturn = $this->joinQueryValues($q, $params);
                    }
                }
            }

            $this->_lastQuery = $qreturn;
            return($qreturn);
        }

        /**
         * Join $values array with $q string wich contains %s elements
         * @param $q
         * @param $values
         * @return array|mixed|string|string[]|null
         */
        function joinQueryValues($q,$values) {
            if(!is_array($values)) $values = array($values);
            if(count($values)==0) return($q); // Empty array to join.

            $joins = array();
            foreach ($values as $key => $value) {
                if(strpos($value,'\\')) {
                    $value = str_replace('\\','\\\\',$value);
                }
                $joins[] = $this->scapeValue($value);
            }

            // Execute replacements
            if($joins)foreach ($joins as $join) {
                if(strpos($join,'$0')!=false) {
                    $join = str_replace('$0','__dollar_cero__',$join);
                    $q = preg_replace('/%s/',$join,$q,1);
                    $q = str_replace('__dollar_cero__','$0',$q);
                } else {
                    $q = preg_replace('/%s/',$join,$q,1);
                }
            }

            //return string with replacements
            return($q);
        }

        /**
         * Apply $this->_db->real_escape_string or addslashes if there is not db objext
         * @param $value
         * @return string
         */
        function scapeValue($value) {
            if(is_object($this->_db))
                return($this->_db->real_escape_string($value));
            else
                return(addslashes($value));
        }

        /**
         * Return a safe query taking the following fields:
         * @param $search
         * @param false $fields
         * @param string $joints
         * @param string $operators
         * @return array|false|mixed|string|string[]|null
         */
        function getQueryFromSearch ($search,$fields=false,$joints="=",$operators="AND") {
            $ret = '1=1';
            if( strlen($search) && $fields !== false )  {
                if(!is_array($fields)) $fields = explode(",", $fields);
                $q = $fields[0]." $joints '%s'";
                $data[] = $search;

                if(is_array($joints)) $last_join = array_shift($joints);
                else $last_join = $joints;

                if(is_array($operators)) $last_op = array_shift($operators);
                else $last_op = $operators;

                for ($i=1,$tr=count($fields); $i < $tr; $i++) if(strlen($fields[$i])) {
                    $q .= " $last_op ".$fields[$i]." $last_join '%s'";
                    $data[] = $search;

                    //looking if they have sent more operators
                    if(is_array($operators)) {
                        $op = array_shift($operators);
                        if(strlen($op)) $last_op = $op;
                    }

                    //looking if they have sent more operators
                    if(is_array($joints)) {
                        $jo= array_shift($joints);
                        if(strlen($jo)) $last_join = $jo;
                    }

                }
                $ret = $this->_buildQuery(array($q,$data));
            }
            return($ret);
        }

        /**
         * Close the opened connection
         */
        function close() {
            if($this->_dblink )  $this->_db->close();
            $this->_dblink = false;
        }

        /**
         * Return true if there is any error
         * @return bool
         */
        function error() {return(count($this->_error)>0);}

        /** Return the last error produced. It will be an array */
        function getError() {return($this->_error);}

        /** Assign an error */
        function setError($err) {
            $this->_error[] = $err;
            //$this->core->errors->add($err);
            //if($this->_debug) _print($err);

        }

        /**
         * Asssign the Database to work with
         * @param $db
         */
        function setDB($db) {$this->_dbdatabase = $db;}

        /** Return last query executed */
        function getQuery() {return( $this->_lastQuery);}

        /** Return last id of the inserted record */
        function getInsertId() {return( $this->_lastInsertId);}

        /** Return the number of affected rows in the las query */
        function getAffectedRows() {return( $this->_affectedRows);}


        /*
         *  OBJECT QUERIES
         */

        function initQueryObject($id,$data=array(),$mixValue='') {
            $this->_qObject[$id]['data'] = $data;
            $this->_qObject[$id]['mixValue'] = $mixValue;
        }

        /**
         * Define When I want to build a query but I do not want to be executed I call this method with true value
         * @param $boolean
         */
        function onlyCreateQuery($boolean) {$this->_onlyCreateQuery = $boolean;}

        /**
         * Return $this->_qObject[$id]
         * @param $id
         */
        function getQueryObject($id) {$this->_qObject[$id];}

        function setQueryObjectSelectFields($id,$value) {
            if(is_array($value)) $value = implode(",",$value);
            $this->_qObject[$id]['selectFields'] = $value;
        }

        function setQueryObjectOrder($id,$value) { $this->_qObject[$id]['order'] = $value; }
        function setQueryObjectTable($id,$value) { $this->_qObject[$id]['table'] = $value; }
        function setQueryObjectData($id,$value) { $this->_qObject[$id]['data'] = $value;  }

        function setQueryObjectWhere($id,$q,$v='') {
            $this->_qObject[$id]['where'] = array();
            $this->addQueryObjectWhere($id,$q,$v);
        }

        function addQueryObjectWhere($id,$q,$v='') { $this->_qObject[$id]['where'][] = $q; }

        function addFieldDependence($field,$dependence) { $this->_cloudDependences[$field] .= $dependence; }
        function setFieldDependence($field,$dependence) {
            unset($this->_cloudDependences[$field]);
            $this->addFieldDependence($field,$dependence);
        }
        function getFieldDependence($field) {
            if(is_string($this->_cloudDependences[$field])) return $this->_cloudDependences[$field];
            else return(false);
        }

        function addReferalField($field,$referal) { $this->_cloudReferalFields[$field] .= $referal; }
        function setReferalField($field,$referal) {
            unset($this->_cloudReferalFields[$field]);
            $this->addReferalField($field,$referal);
        }
        function getReferalField($field) {
            if( isset($this->_cloudReferalFields[$field]) && strlen($this->_cloudReferalFields[$field])) return $this->_cloudReferalFields[$field];
            else return(false);
        }

        function setAutoSelectField($field) { $this->_cloudAutoSelectFields[$field]= true; }
        function unsetAutoSelectField($field) { unset($this->_cloudAutoSelectFields[$field]);}
        function isAutoSelectField($field) {
            if( isset($this->_cloudAutoSelectFields[$field]) && $this->_cloudAutoSelectFields[$field]) return true;
            else return(false);
        }

        function addWhereField($field,$where) { $this->_cloudWhereFields[$field] .= $where; }
        function setWhereField($field,$where) {
            unset($this->_cloudWhereFields[$field]);
            $this->addWhereField($field,$where);
        }
        function getWhereField($field) {
            if(is_string($this->_cloudWhereFields[$field])) return $this->_cloudWhereFields[$field];
            else return(false);
        }

        function addFilterWhereField($field,$where,$values=array()) {
            if(!strlen(trim($where))) return;

            if(strlen(trim($this->_cloudFilterWhereFields[$field]))) $this->_cloudFilterWhereFields[$field] .= ' AND ';
            $this->_cloudFilterWhereFields[$field] .= $this->joinQueryValues('R.'.$where,$values);
        }
        function setFilterWhereField($field,$where,$values=array()) {
            if(strlen(trim($where))) {
                unset($this->_clouFilterWhereFields[$field]);
                $this->addFilterWhereField($field,$where,$values);
            }
        }
        function getFilterWhereField($field) {
            if(is_string($this->_cloudFilterWhereFields[$field])) return $this->_cloudFilterWhereFields[$field];
            else return(false);
        }

        function avoidFilterCalculation($field) {
            $this->_cloudFilterToAvoidCalculation[$field] = true;
        }

        function isAvoidFilterCalculation($field) {
            return(isset($this->_cloudFilterToAvoidCalculation[$field]));
        }

        function getSecuredSqlString($ret) {
            if(strlen($ret)) {
                $ret = str_ireplace("delete ", '', $ret);
                $ret = str_ireplace(";", '', $ret);
                $ret = str_ireplace("insert ", '', $ret);
                $ret = str_ireplace("from ", '', $ret);
                $ret = str_ireplace("replace ", '', $ret);
                $ret = str_ireplace("truncate ", '', $ret);
                $ret = str_ireplace("drop ", '', $ret);
                $ret = str_ireplace('[[', '', $ret);
            }
            return($ret);

        }

        function setLimit($limit) { $this->_limit = $limit;}
        function getLimit() { reurn($this->_limit);}
        function setPage($page) { $this->_page = $page;}
        function getPage() { reurn($this->_page);}

        /*
         * $action could be: getFieldTypes, getObjectFields ..
         * $data: array with field values or table where condition
         * $mixValue: if is_string is the name of the table elseif is_array are the values from a table where in $data
         */
        function cloudFrameWork($action,$data='',$mixValue='',$order='',$selectFields='*',$page=0) {

            if(!strlen($selectFields)) $selectFields='*';
            if(!is_numeric($page)) $page=0;
            $table=''; // We have to calculate $table

            // Analyze if possibles params come from _qObject array
            if(is_string($data) && is_array($this->_qObject[$data]['data'])) {
                $id = $data;
                $data = $this->_qObject[$id]['data'];
                $mixValue = $this->_qObject[$id]['mixValue'];
                $table = $this->_qObject[$id]['table'];
                $order = $this->_qObject[$id]['order'];
                $page = $this->_qObject[$id]['page'];
                $selectFields = $this->_qObject[$id]['selectFields'];
            }

            if( !strlen($table) && (!is_array($data) || count($data)==0)) {
                $this->setError('Required at least no empty array $data in cloudFrameWork function.');
                return false;
            } elseif(count($data)>1 && is_array($mixValue)) {
                $this->setError('if $data has more than 1 row then $mixValue can not be an array');
                return false;
            } elseif(is_array($data)) {
                $allFields = array_keys($data);
            }

            //verify we have a db connection ready
            $_requireConnection = !$this->_dblink;
            if($_requireConnection) $this->connect();
            if($this->error()) return false;

            // figuring out the table to work with
            if(!strlen($table)) {
                // if $mixValue is string with data then it has to be the table;
                if(is_string($mixValue) && strlen($mixValue)>0) {
                    $table = $mixValue;
                    $mixValue='';
                    // If not we have to find out the name of table from data
                }
                else {
                    // In CloudFramWorkd all fields has to have the following structure: (tableName-no-ending-with-s)_fieldname.
                    // if there is no _ we assum it is a table
                    list($tmpTable,$foo) = explode("_",$allFields[0],2);
                    if(!strlen($foo) ) {
                        // if the first field have no _ then it will be the table and no more fields are allowed
                        if(count($allFields) == 1) $table = $tmpTable;
                        else {
                            $this->setError("I can not figure out the name of the table to query.");
                            return false;
                        }
                        // if it has a _ then let's see if the view of the table exist
                    } else if(strlen($foo)) {
                        // it it is a Rel table
                        if($tmpTable=='Rel') $tmpTable = $allFields[0];
                        else $tmpTable.="s";

                        // Check if table exists
                        if($this->tableExists($tmpTable)) $table = $tmpTable;
                        else {
                            $this->setError("$tmpTable is not a right table.");
                            return false;
                        }
                    }
                }
            }




            // Here $table now has a value.
            // Let's see if $data[$table] has a where conditions in it count
            $_where = '';
            $_tableInFirstField = false;
            if( count($allFields) == 1 && $allFields[0] == $table ) {
                // If $table is_array then $table will have the values of $where
                if(is_array($mixValue) || strlen($mixValue)) $_where = $this->joinQueryValues($data[$table],$mixValue);
                else $_where = $data[$table];
                $_tableInFirstField =true;
            }



            if(strpos($table, "Rel_") !== false)
                $_relTable = true;

            if($action == 'insert' || $action == "replace" || $action == 'update' || $action == 'delete'
                || $action == 'insertRecord' || $action == "replaceRecord"  ||  $action == "updateRecord" || $action =="getFieldTypes")
                if($this->cfmode) $table ="CF_".$table;

            // Field Types of the table
            // Reading from cache
            if(!isset($this->_queryFieldTypes[$table])) {
                $this->_queryFieldTypes[$table] = $this->core->cache->get('cloudFrameWork_show_colums_'.$this->_dbdatabase.'_'.$table,3600);

                // If GET params _nocache or _reloadDBFields are passed the reload from DB the columns
                if(!is_array($this->_queryFieldTypes[$table]) || isset($_GET['_nocache']) || isset($_GET['_reloadDBFields'])) {
                    $this->_queryFieldTypes[$table] = $this->getDataFromQuery("SHOW COLUMNS FROM %s", $table);
                    $this->core->cache->set('cloudFrameWork_show_colums_'.$table,$this->_queryFieldTypes[$table]);
                }
            }
            if($this->error()) return(false);
            $types = $this->_queryFieldTypes[$table];

            for($k=0,$tr3=count($types);$k<$tr3;$k++) {
                $fieldTypes[$types[$k]['Field']]['type'] = $types[$k]['Type'];
                $fieldTypes[$types[$k]['Field']]['isNum'] = (preg_match("/(int|numb|deci|bit|double|real|float)/i", $types[$k]['Type']));
                $fieldTypes[$types[$k]['Field']]['isKey'] = ($types[$k]['Key']=="PRI");

                // IN CFMODE the '_' char has a meaning
                $foo = explode("_", $types[$k]['Field'],3);

                if($this->cfmode && strlen($foo[2]) && $foo[2]=='Id') {

                    $fieldTypes[$types[$k]['Field']]['isRel'] = true;
                    $fieldTypes[$types[$k]['Field']]['relField'] = $foo[1].'_'.$foo[2];

                } else $fieldTypes[$types[$k]['Field']]['isRel'] = false;

            }

            // analyze if the Where has _anyfield
            if(strpos($_where,"_anyfield=")!== false) {

                list($_foo,$_search) = explode("_anyfield=", $_where,2);
                $_where = "(".$this->getQueryFromSearch("%$_search%", array_keys($fieldTypes),"LIKE","OR").")";
            }
            if($_where == '%') $_where = '1=1';

            if(strlen($_where)) $tables[$table]['selectWhere'] = $_where;
            else $tables[$table]['selectWhere']='';


            $tables[$table]['init'] = 1;

            if(!$_tableInFirstField)
                for($i=-1,$j=0,$tr2=count($allFields);$j<$tr2;$j++) if($allFields[$j] != $table) {

                    $field = $allFields[$j];

                    if(!isset($fieldTypes) || !isset($fieldTypes[$field]['type']) || !$fieldTypes[$field]['type']) {
                        if(!isset($fieldTypes)) $fieldTypes = [];
                        $this->setError("Wrong data array. $field does not exist in table {$table}: ".implode(',',array_keys($fieldTypes)));
                        return(false);
                    }

                    $sep = (isset($tables[$table]['insertFields']) && (strlen($tables[$table]['insertFields']))?",":"");
                    $and = (isset($tables[$table]['selectWhere']) && (strlen($tables[$table]['selectWhere']))?" AND ":"");


                    if(!isset($tables[$table]['updateFields'])) $tables[$table]['updateFields']='';

                    if(is_array($data[$field]??'')) $data[$field] = json_encode($data[$field]);
                    if(strlen($data[$field]??'') && $data[$field] !=='NULL')
                        $tables[$table]['updateFields'] .= $sep.$field."=".(($fieldTypes[$field]['isNum'])?"%s":"'%s'");
                    else {
                        $data[$field] = 'NULL';
                        $tables[$table]['updateFields'] .= $sep.$field."=%s";
                    }

                    if(!isset($tables[$table]['insertFields'])) $tables[$table]['insertFields']='';
                    $tables[$table]['insertFields'] .= $sep.$field;

                    if(!isset($tables[$table]['insertPercents'])) $tables[$table]['insertPercents']='';
                    $tables[$table]['insertPercents'] .= $sep.(($fieldTypes[$field]['isNum'])?"%s":(($data[$field] === 'NULL')?"%s":"'%s'"));

                    if($fieldTypes[$field]['isKey']) {
                        if(!isset($tables[$table]['updateWhereFields'])) $tables[$table]['updateWhereFields']='';
                        if(strlen($tables[$table]['updateWhereFields'])) $tables[$table]['updateWhereFields'].=' AND ';
                        $tables[$table]['updateWhereFields'] .= $field."=".(($fieldTypes[$field]['isNum'])?"%s":"'%s'");
                        $tables[$table]['updateWhereValues'][] = $data[$field];
                    }
                    // SELECT WHERE CONSTRUCTION

                    if(strval($data[$field]) !='%') {

                        $_extra='';
                        if(strval($data[$field])=="_empty_") {
                            $tables[$table]['selectWhere'] .= $and." ($field IS NULL OR LENGTH($field)=0) ";
                        } else if(strval($data[$field])=="_noempty_") {
                            $tables[$table]['selectWhere'] .= $and." ($field IS NOT NULL AND LENGTH($field)>0) ";
                        } else {
                            // Allow especial query values
                            if(stripos(strval($data[$field]),'[[')===0) {
                                list($joint,$data[$field]) = explode(' ', $data[$field],2);
                                $joint = ' '.$this->getSecuredSqlString($joint).' ';
                            } else {
                                $joint = ' = ';
                                $_selecWhereFieldError = false;
                                if(strpos(strval($data[$field]), '%')!==false) $joint = ' LIKE ';
                                else if($fieldTypes[$field]['isNum']) {
                                    if(!is_numeric(trim(strval($data[$field])))) {
                                        $joint=' ';
                                    }
                                }
                            }

                            // Evaluating OR values
                            while(strpos(strval($data[$field]),' _or_')!== false) {
                                list($orvalue,$data[$field]) = explode(' _or_', strval($data[$field]),2);
                                $tables[$table]['values'][] = $orvalue;
                                $_extra.=$field.$joint.(($fieldTypes[$field]['isNum'])?"%s":"'%s'").' OR ';
                                list($joint,$data[$field]) = explode(' ', $data[$field],2);
                                if(!strlen($joint)) $joint=' = ';
                                $joint = ' '.$this->getSecuredSqlString($joint).' ';
                            }

                            // Evaluating AND values
                            while(strpos(strval($data[$field]),' _and_')!== false) {
                                list($orvalue,$data[$field] ) = explode(' _and_', strval($data[$field]),2);
                                $tables[$table]['values'][] = $orvalue;
                                $_extra.=$field.$joint.(($fieldTypes[$field]['isNum'])?"%s":"'%s'").' AND ';
                                list($joint,$data[$field]) = explode(' ', $data[$field],2);
                                if(!strlen($joint)) $joint=' = ';
                                $joint = ' '.$this->getSecuredSqlString($joint).' ';
                            }

                            if(!$_selecWhereFieldError ) {
                                $tables[$table]['selectWhere'] .= $and.'('.$_extra.$field.$joint.(($fieldTypes[$field]['isNum'])?"%s":"'%s'").')';
                                $tables[$table]['values'][] = $data[$field];
                            }

                        }
                    }

                }

            // -- Execute the commands
            foreach ($tables as $key => $value) {
                switch ($action) {
                    case 'getFieldTypes':
                        return($fieldTypes);
                        break;
                    case 'getObjectFields':
                        $_infields = "'".implode("','",array_keys($fieldTypes))."'";
                        $_q = "SELECT DirectoryObjectField_Name, DirectoryObjectField_DefaultName FROM DirectoryObjectFields WHERE DirectoryObjectField_Name IN ($_infields)";
                        $_f = $this->getDataFromQuery($_q);
                        if($this->error()) return false;

                        $_ret = array();
                        for ($i=0,$tr=count($_f); $i <  $tr; $i++) {
                            $_ret[$_f[$i]['DirectoryObjectField_Name']] = $_f[$i]['DirectoryObjectField_DefaultName'];
                        }
                        unset($_f);
                        return($_ret);
                        break;
                    case 'insertRecord':
                    case 'insert':
                    case 'replaceRecord':
                    case 'replace':
                        if($action == 'insertRecord' || $action == 'insert') $act = "insert";
                        else $act = 'replace';
                        //echo($action." into $key (".$value['insertFields'].") values  (".$value['insertPercents'].")");
                        return($this->command($act." into $key (".$value['insertFields'].") values  (".$value['insertPercents'].")",$value['values']));
                        break;
                    case 'getRecords':
                    case 'getDistinctRecords':
                    case 'getPagedRecords':
                    case 'getPagedDistinctRecords':
                    case 'getRecordsForEdit':
                    case 'getRecordsToExplore':

                        if(!strlen($value['selectWhere'])) $value['selectWhere'] = "1=1";

                        if(!strlen($table)) $table = $key;
                        if(strlen($order)) $order = " ORDER BY ".$order;
                        // case 'getPagedRecords':
                        // case 'getPagedDistinctRecords':
                        if($action == "getPagedRecords" || $action == "getPagedDistinctRecords" ) {

                            $_q = "select count(*) TOTAL from (select ".(($action == "getPagedDistinctRecords")?'distinct ':'')."$selectFields from $table main where ".$value['selectWhere'].$order;
                            $_q .=") __totRows";
                            $ret = $this->getDataFromQuery($_q,$value['values']);
                            $tot = $ret[0]['TOTAL'];

                            $ret = array();
                            $ret['num_rows'] = $tot;
                            $ret['page_limit'] = (intval($this->_limit))?$this->_limit:200;
                            $ret['num_pages'] = round($tot/$this->_limit,0);
                            if( ($ret['num_pages'] * $this->_limit) < $tot) $ret['num_pages']++;
                            if($this->_page >= $ret['num_pages'] && $this->_page >0) $this->_page = $ret['num_pages']-1;
                            $ret['current_page'] = $this->_page;
                            $ret['offset'] = ($this->_page * $this->_limit).",$this->_limit";
                            $_q = "select ".(($action == "getPagedDistinctRecords")?'distinct ':'')."$selectFields from $table main where ".$value['selectWhere'].$order;
                            $_q .=" limit ".$ret['offset'];

                            $ret2 = $this->getDataFromQuery($_q,$value['values']);
                            $ret['num_rows_in_page'] = $this->_affectedRows;
                            $ret['rows'] = $ret2;
                            unset($ret2);
                            return($ret);
                        }
                        // case 'getRecords':
                        // case 'getDistinctRecords':
                        elseif($action == "getRecords" || $action == "getDistinctRecords") {
                            $_q = "select ".(($action == "getDistinctRecords")?'distinct ':'')."$selectFields from $table main where ".$value['selectWhere'].$order;
                            if($this->_limit) $_q .=" limit ".$this->_limit;
                            return($this->getDataFromQuery($_q,$value['values']));

                        }
                        // case 'getRecordsForEdit':
                        // case 'getRecordsToExplore':
                        else {

                            // Eplore types evaluting to cache
                            for($k=0,$tr3=count($types);$k<$tr3;$k++) {

                                if(preg_match("/(int|numb|deci)/i", $types[$k]['Type']))
                                    $_ret[$types[$k]['Field']]['type'] = 'text';
                                else if(preg_match("/(text)/i", $types[$k]['Type']))
                                    $_ret[$types[$k]['Field']]['type'] = 'textarea';
                                else
                                    $_ret[$types[$k]['Field']]['type'] = 'text';

                                list($foo,$field,$rels) = explode("_", $types[$k]['Field'],3);

                                if(($field=="Id" && $rels=="" && !$_relTable) || ($_relTable && $foo=="Id"))
                                    $_ret[$types[$k]['Field']]['type'] = "key";
                                else if($rels=='Id'   || ($_relTable && strlen($field))) {

                                    // Getting Rel data to this field
                                    $_ret[$types[$k]['Field']]['type'] = "rel";

                                    if($_relTable) {
                                        $reltable=$foo."s";
                                        $_f= $foo;
                                    } else {
                                        $reltable=$field."s";
                                        $_f= $field;
                                    }

                                    // Fields dependences and WhereConditions
                                    $_fqWhere = '';
                                    if(($dependences = $this->getFieldDependence($types[$k]['Field'])) !== false)  $_fqWhere .=  ' (R.'.$dependences.')';

                                    if(($fieldwheres = $this->getWhereField($types[$k]['Field'])) !== false) {
                                        if(strlen($_fqWhere)) $_fqWhere .= ' AND ';
                                        $_fqWhere .=  ' ('.$fieldwheres.')';
                                    }

                                    if(($fieldwheres = $this->getFilterWhereField($types[$k]['Field'])) !== false) {
                                        if(strlen($_fqWhere)) $_fqWhere .= ' AND ';
                                        $_fqWhere .=  ' ('.$fieldwheres.')';
                                    }

                                    $_refField = str_replace('_Id', '_Name', $types[$k]['Field']);
                                    if($this->getReferalField($types[$k]['Field']) !==false ) {
                                        $selectFields .=',CONCAT_WS(" - ",'.$this->getReferalField($types[$k]['Field']).') '.$_refField;
                                        $_refFields = 'CONCAT_WS(" - ",R.'.str_replace(',', ',R.', $this->getReferalField($types[$k]['Field'])).') Name';
                                    } else {
                                        $selectFields .=','.$_f.'_Name '.$_refField;
                                        $_refFields = 'R.'.$_f.'_Name Name';
                                    }
                                    // include all referal Fields in the query.
                                    //$_refFields = 'R.'.str_replace(',', ',R.', $this->getReferalField($types[$k]['Field']));



                                    $_fn = 'R.'.$_f.'_Id Id,'.$_refFields;
                                    if(!strlen($_fqWhere )) $_fqWhere .=  '1=1';
                                    // $_fq = " SELECT DISTINCT $_fn FROM  $table R  WHERE $_fqWhere ";
                                    $_fq = " SELECT DISTINCT $_fn FROM  $reltable R LEFT JOIN  $table P ON (R.".$_f."_Id = P.".$types[$k]['Field'].") WHERE $_fqWhere ";

                                    if($this->isAvoidFilterCalculation($types[$k]['Field'])) {
                                        $_ret[$types[$k]['Field']]['relData'] = array();
                                    } else {
                                        $relData = $this->core->cache->get('getRecordsForEdit_'.md5($_fq),3600);
                                        if(!is_array($relData) || isset($_GET['nocache']) || isset($_GET['reload'])) {
                                            $relData = $this->getDataFromQuery($_fq);
                                            if ($this->error()) return false;
                                            $this->core->cache->set('getRecordsForEdit_'.md5($_fq),$relData);
                                        }
                                        $_ret[$types[$k]['Field']]['relData'] =$relData;
                                    }

                                } else if($this->isAutoSelectField($types[$k]['Field'])) {
                                    $_fqWhere = '';
                                    if(($dependences = $this->getFieldDependence($types[$k]['Field'])) !== false)  $_fqWhere .=  ' ('.$dependences.')';
                                    $_fn = $types[$k]['Field'].' AS Id,'.$types[$k]['Field'].' AS Name';
                                    if(!strlen($_fqWhere )) $_fqWhere .=  '1=1';

                                    $_fq = " SELECT DISTINCT $_fn FROM  $table  WHERE $_fqWhere ";
                                    $relData = $this->core->cache->get('getRecordsForEdit_'.md5($_fq),3600);
                                    if(!is_array($relData) || isset($_GET['nocache']) || isset($_GET['reload'])) {
                                        $relData = $this->getDataFromQuery($_fq);
                                        if($this->error()) return false;
                                        $this->core->cache->set('getRecordsForEdit_'.md5($_fq),$relData);
                                    }
                                    $_ret[$types[$k]['Field']]['relData'] =$relData;

                                }

                                // add where to Global Query:
                                if(($fieldwheres = $this->getWhereField($types[$k]['Field'])) !== false) {
                                    $value['selectWhere'] .= ' AND   ('.$fieldwheres.')';
                                }

                            }

                            // Let see how many rows it has
                            $nrows = $this->getDataFromQuery("select count(1) TOT from $table main where ".$value['selectWhere'],$value['values']);
                            if($this->error()) return false;
                            $_ret['totRows'] = $nrows[0]['TOT'];


                            // if($action == "getRecordsForEdit") $this->_limit = 50;
                            if($this->_limit <= 0) $this->_limit = 10000;
                            $_ret['totPages'] = round($nrows[0]['TOT']/$this->_limit,0);
                            if($_ret['totPages']*$this->_limit < $nrows[0]['TOT']) $_ret['totPages']++;

                            if($page >= $_ret['totPages'] ) $page = $_ret['totPages']-1 ;
                            if($page < 0 ) $page=0;

                            $_ret['currentPage'] = $page;
                            $_ret['totRowsInPage'] = ($this->_limit < $_ret['totRows'])?$this->_limit:$_ret['totRows'];
                            $_ret['offset'] = $page * $this->_limit;

                            $data = $this->getDataFromQuery("select $selectFields from $table main where ".$value['selectWhere'].$order." limit ".$_ret['offset'].','.$this->_limit,$value['values']);
                            if($this->error()) return false;
                            $_ret['fields'] = array_keys($fieldTypes);
                            for($i=0,$tr=count($data);$i<$tr;$i++)
                                $data[$i]['_hash'] = $this->getHashFromArray($data[$i]);


                            $_ret['data'] = $data;

                            unset($data);
                            return($_ret);
                        }
                        break;
                    case 'updateRecord':
                    case 'update':

                        $_q = "UPDATE $key SET ".$tables[$table]['updateFields']." WHERE ".$tables[$table]['updateWhereFields'];
                        if(!strlen($tables[$table]['updateWhereFields']) || !is_array($value['updateWhereValues']))  $this->setError("No UPDATE condition in $_q with fieldtypes: ".json_encode($fieldTypes).' IN TYPES '.json_encode($types));
                        else  $this->command($_q,array_merge($value['values'],$value['updateWhereValues']));
                        if($this->error()) return false;

                        break;
                    case 'deleteRecord':
                    case 'delete':
                        $_q = "DELETE FROM $key  WHERE ". $tables[$table]['selectWhere'];
                        if(!strlen($tables[$table]['selectWhere']) || !is_array($tables[$table]['values']))  $this->setError("No DELETE condition in $_q");
                        else {
                            $this->command($_q,$value['values']);
                        }
                        if($this->error()) return false;
                        else return $this->_affectedRows;

                        break;
                    default:
                        $this->setError('Unknown action: '.$action);
                        break;
                }

            }
            if($_requireConnection) $this->close();
        }

        function getHashFromArray($arr) {
            if(!isset($arr)) $arr=array();
            return(md5(implode('', $arr)));
        }

        /**
         * Return the CloudFrameWork-io Model from a DB table
         * @deprecated
         * @param $table
         * @return array where array['model'] is the JSON model if array['table_exists]===true
         */
        function getModelFromTable($table) {


            if($this->tableExists($table)) {
                $tmp['explain'] = $this->getDataFromQuery("SHOW FULL COLUMNS FROM %s", $table);
                $tmp['SQL_FIELDS'] = [];
                $tmp['indexes'] = $this->getDataFromQuery('SHOW INDEX FROM %s;',array($table));
                $tmp['SQL'] = $this->getDataFromQuery('SHOW CREATE TABLE %s;',array($table))[0];
                $tmp['TABLE_SCHEMA'] = $this->getDataFromQuery('SELECT * FROM information_schema.TABLES WHERE TABLE_NAME = "%s"',array($table))[0];
                $tmp['triggers'] = $this->getDataFromQuery('SHOW TRIGGERS IN `%s` LIKE "%s";',array($this->_dbdatabase,$table));
                $tmp['TRIGGERS'] = [];
                $tmp['INDEXES'] = [];
                if($tmp['triggers']) foreach ($tmp['triggers'] as $trigger) {
                    $tmp['TRIGGERS'][$trigger['Event'].':'.$trigger['Trigger']] = $trigger;
                }
                if($tmp['indexes']) foreach ($tmp['indexes'] as $index) {
                    $tmp['INDEXES'][$index['Key_name'].':'.$index['Column_name']] = $index;
                }

                foreach ($tmp['explain'] as $key => $value) {

                    $tmp['SQL_FIELDS'][$value['Field']] = $value;

                    // TYPE OF THE FIELD
                    $tmp['Fields'][$value['Field']]['type'] = $value['Type'];

                    // IS NULLABLE
                    if ($value['Null'] == 'NO')
                        $tmp['Fields'][$value['Field']]['null'] = false;

                    // Let's see if the field is Key, Unique or Index
                    if (strlen($value['Key']))
                        if ($value['Key'] == 'PRI') $tmp['Fields'][$value['Field']]['key'] = true;
                        elseif($value['Key'] == 'MUL') $tmp['Fields'][$value['Field']]['index'] = true;
                        elseif($value['Key'] == 'UNI') $tmp['Fields'][$value['Field']]['unique'] = true;

                    // Default value
                    if(!($value['Null'] == 'NO' && $value['Default']===null))
                        $tmp['Fields'][$value['Field']]['default'] = $value['Default'];

                    if (strlen($value['Extra']))
                        $tmp['Fields'][$value['Field']]['extra'] = $value['Extra'];

                    // Comment field
                    $tmp['Fields'][$value['Field']]['description'] = $value['Comment'];
                }

                // CHECK if there is multiple Uniques id's
                $indexes = [];
                foreach($tmp['indexes'] as $index=>$indexValues) {
                    if($indexValues['Key_name']=='PRIMARY') $indexes[$indexValues['Column_name']]['primary'][] = $indexValues['Column_name'];
                    elseif($indexValues['Non_unique']=="0") $indexes[$indexValues['Key_name']]['unique'][] = $indexValues['Column_name'];
                    elseif($indexValues['Non_unique']=="1") $indexes[$indexValues['Key_name']]['index'][] = $indexValues['Column_name'];
                }
                if(count($indexes))
                    foreach($indexes as $index=>$indexValues) {
                        if(isset($indexValues['unique']) && count($indexValues['unique'])>1)
                            foreach($indexValues['unique'] as $i=>$indexField)
                                $tmp['Fields'][$indexField]['unique'] = $index;
                        elseif(isset($indexValues['index']) && count($indexValues['index'])>1)
                            foreach($indexValues['index'] as $i=>$indexField)
                                $tmp['Fields'][$indexField]['index'] = $index;
                    }

                return (['table_exists'=>true,'model'=>['table' => $table
                    , 'description' => $tmp['TABLE_SCHEMA']['TABLE_COMMENT']
                    , 'engine' => $tmp['TABLE_SCHEMA']['ENGINE']
                    , 'fields' => $tmp['Fields']
                ]
                    , 'TABLE_SCHEMA' => $tmp['TABLE_SCHEMA']
                    , 'SQL_FIELDS' => $tmp['SQL_FIELDS']
                    , 'INDEXES' => $tmp['INDEXES']
                    , 'TRIGGERS' => $tmp['TRIGGERS']
                    , 'SQL' => (isset($tmp['SQL']['Create Table']))?$tmp['SQL']['Create Table']:null
                ]);
            } else
                return(['table_exists'=> false]);
        }

        function getSimpleModelFromTable($table) {

            $fields = ['model'=>[],'mapWithEntity'=>$table,'mapping'=>[]];
            $table = $this->getModelFromTable($table);
            if(isset($table['model']['fields'])) foreach ($table['model']['fields'] as $field=>$values) {
                $fields['model'][$field][0] = $values['type'];
                $fields['model'][$field][1] = (preg_match('/(varchar|varbinary|char|json)/',$values['type']))?'string':((preg_match('/(timestamp|datetime)/',$values['type']))?'datetime':((preg_match('/(date)/',$values['type']))?'date':'integer'));

                if(isset($values['key']) && $values['key']) $fields['model'][$field][1].='|isKey';
                if(isset($values['null']) && $values['null']===false) $fields['model'][$field][1].='|mandatory';
                else $fields['model'][$field][1].='|allowNull';

                if(strpos($values['type'],'varchar')!==false)
                    $fields['model'][$field][1].='|maxlength='.preg_replace('/[^0-9]/','',explode('varchar',$values['type'],2)[1]);
                if(strpos($values['type'],'varbinary')!==false)
                    $fields['model'][$field][1].='|maxlength='.preg_replace('/[^0-9]/','',explode('varbinary',$values['type'],2)[1]);

                if(isset($values['index']) && $values['index']) $fields['model'][$field][1].='|isIndex';
                if(isset($values['default']) && strlen($values['default'])) $fields['model'][$field][1].='|defaultvalue='.$values['default'];
                $fields['model'][$field][1].='|description='.$values['description'];

                // Mapping
                $fields['mapping'][$field] = ['field'=>$field
                    ,'type'=>(preg_match('/(varchar|varbinary|char)/',$values['type']))?'string':((preg_match('/(timestamp|datetime)/',$values['type']))?'datetime':((preg_match('/(date)/',$values['type']))?'date':'integer'))
                    ,'validation'=>$fields['model'][$field][1]];
            }
            return $fields;
        }

        /**
         * @deprecated
         * @param $table
         * @return array
         */
        function getInterfaceModelFromTable($table) {

            $cfo = [
                'KeyName'=>$table
                ,'type'=>'db'
                ,'entity'=>$table
                ,'extends'=>null
                ,'GroupName'=>'CFOs'
                ,'model'=>[
                    'model'=>[]
                    ,'sql'=>[]
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
                    'object'=>$table
                    ,'name'=>$table.' table record'
                    ,'plural'=>$table.' table records'
                    ,'ico'=>'building'
                    ,'modal_size'=>'xl'
                    ,'secret'=>$this->options['keyFile']??null
                    ,'dbName'=>null
                    ,'dbProxy'=>null
                    ,'filters'=>[]
                    ,'buttons'=>[['title'=>"Insert {$table} Record",'type'=>'api-insert'],['title'=>"Bulk {$table} Records",'type'=>'api-bulk']]
                    ,'views'=>['default'=>['name'=>'Default View','all_fields'=>true,'server_fields'=>null,'server_order'=>null,'server_where'=>null,'server_limit'=>200,'fields'=>[]]]
                    ,'display_fields'=>null
                    ,'update_fields'=>null
                    ,'insert_fields'=>null
                    ,'copy_fields'=>null
                    ,'delete_fields'=>null
                    ,'hooks'=>['on.insert'=>[],'on.update'=>[],'on.delete'=>[]]
                ]
            ];

            //get the $table['model']['fields'] from the $table
            $table = $this->getModelFromTable($table);
            $cfo['model']['sql'] = $table;
            unset($cfo['model']['sql']['model']);

            //region assign $fields['model'] taking MYSQL field types from $table
            if(isset($table['model']['fields'])) foreach ($table['model']['fields'] as $field=>$values) {

                $is_key=false;
                $cfo['model']['model'][$field][0] = $values['type'];
                $cfo['model']['model'][$field][1] = (preg_match('/(varchar|varbinary|char|json|text)/',$values['type']))?'string':
                    ((preg_match('/(timestamp|datetime)/',$values['type']))?'datetime':
                        ((preg_match('/(date)/',$values['type']))?'date':
                            ((preg_match('/(decimal|float)/',$values['type']))?'float':
                                'integer')));

                if(isset($values['key']) && $values['key']) {
                    $is_key = true;
                    $cfo['model']['model'][$field][1].='|isKey';
                }
                if(isset($values['null']) && $values['null']===false && ($values['extra']??'')!='auto_increment') $cfo['model']['model'][$field][1].='|mandatory';
                else $cfo['model']['model'][$field][1].='|allowNull';

                if(strpos($values['type'],'varchar')!==false)
                    $cfo['model']['model'][$field][1].='|maxlength='.preg_replace('/[^0-9]/','',explode('varchar',$values['type'],2)[1]);
                if(strpos($values['type'],'varbinary')!==false)
                    $cfo['model']['model'][$field][1].='|maxlength='.preg_replace('/[^0-9]/','',explode('varbinary',$values['type'],2)[1]);

                if(isset($values['index']) && $values['index']) $cfo['model']['model'][$field][1].='|isIndex';
                if(isset($values['default']) && strlen($values['default'])) $cfo['model']['model'][$field][1].='|defaultvalue='.$values['default'];
                $cfo['model']['model'][$field][1].='|description='.$values['description'];

                // Mapping
                $cfo['securityAndFields']['fields'][$field] = ['name'=>$field];
                if($cfo['model']['model'][$field][0]=='date') {
                    $cfo['securityAndFields']['fields'][$field]['type'] = 'date';
                    $cfo['interface']['filters'][] = [
                        'field'=>$field,
                        'field_name'=>$field,
                        'type'=>'date',
                        'placeholder'=>"{$field} date or range: 2020-01/2020-03"
                    ];
                }
                if($cfo['model']['model'][$field][0]=='datetime') {
                    $cfo['securityAndFields']['fields'][$field]['type'] = 'datetime';
                    $cfo['interface']['filters'][] = [
                        'field'=>$field,
                        'field_name'=>$field,
                        'type'=>'datetime',
                        'placeholder'=>"{$field} date or range: 2020-01/2020-03"
                    ];
                }

                $cfo['interface']['views']['default']['fields'][$field] = ['field'=>$field];
                $cfo['interface']['insert_fields'][$field] = ['field'=>$field];
                $cfo['interface']['display_fields'][$field] = ['field'=>$field];
                $cfo['interface']['update_fields'][$field] = ['field'=>$field];

                if($is_key) {
                    $cfo['interface']['views']['default']['fields'][$field]['display_cfo']=true;
                    $cfo['interface']['update_fields'][$field]['read_only'] = true;
                    $cfo['interface']['delete_fields'][$field]= ['field'=>$field];
                }

            }
            //endregion
            return $cfo;
        }



    }
}