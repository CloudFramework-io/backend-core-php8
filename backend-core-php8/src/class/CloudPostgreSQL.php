<?php

// CloudSQL Class v10
if (!defined ("_POSTGRESQL_CLASS_") ) {
    define("_POSTGRESQL_CLASS_", TRUE);


    /**
     *  Class to handle PostgreSQL connection
     *  Feel free to use a distribute it.
     *  last-update 2022-12-29
     * @package CoreClasses
     */
    class CloudPostgreSQL
    {
        // Base variables
        var $error = false;                      // Holds the last error
        var $errorMsg = [];                      // Holds the last error
        var $errorCode = null;                     // Holds the last error
        var $_lastRes = false;                                        // Holds the last result set
        var $_lastQuery = '';                                        // Holds the last result set
        var $_lastExecutionMicrotime = 0;
        var $_lastInsertId = '';                                        // Holds the last result set
        var $_affectedRows = null;
        var $result;                                                // Holds the PostgreSQL query result
        var $records;                                                // Holds the total number of records returned
        var $affected;                                        // Holds the total number of records affected
        var $rawResults;                                // Holds raw 'arrayed' results
        var $arrayedResult;                        // Holds an array of the result

        /** @var PDO */
        var $_db;
        var $_dbServer;        // PostgreSQL Hostname
        var $_dbUser;        // PostgreSQL Username
        var $_dbPassword;        // PostgreSQL Password
        var $_dbName;        // PostgreSQL DB name
        var $_dbPort = '5432';        // PostgreSQL Database
        var $_dbCharset = '';        // PostgreSQL Charset. Ex: utf8mb4
        var $_dbType = 'mysql';
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

        protected $_dblink = false;                // Database Connection Link
        var $_debug = false;

        /**
         * Class Constructor
         * @param Core7 $core
         * @param string[] $options
         */
        function __construct(Core7 &$core, $options =['dbServer' => '', 'dbUser' => '', 'dbPassword' => '', 'dbName' => '', 'dbPort' => '5432', 'dbCharset' => ''])
        {

            $this->core = $core;
            if (strlen($options['dbServer']??'')) {
                $this->_dbServer = trim($options['dbServer']);
                $this->_dbUser = trim($options['dbUser']??'');
                $this->_dbPassword = trim($options['dbPassword']??'');
                $this->_dbName = trim($options['dbName']??'');
                $this->_dbPort = trim($options['dbPort']??'5432');
                $this->_dbCharset = trim($options['dbCharset']??'');
            } else if (strlen(trim($this->core->config->get("dbServer"))) || trim($this->core->config->get("dbSocket"))) {
                // It load from $this->core->config->get("vars"): dbServer,dbUser,dbPassword,dbName,dbSocket,dbPort,dbPort
                $this->loadCoreConfigVars();
                // It rewrites: $this->_dbServer, $this->_dbUser,$this->_dbPassword,$this->_dbDatabase ,$this->_dbsocket, $this->_dbport
            }
            if (!strlen($this->_dbServer )) $this->_dbServer = '127.0.0.1';

        }

        /**
         * Init a PDO connection with the database
         *
         * reference: https://www.php.net/manual/en/pdo.connections.php
         * @return bool True if connection is ok.
         */
        function connect($options=[]) {

            if($options) $this->setConfiVars($options);
            if($this->_dblink)  return($this->_dblink); // Optimize current connection.


            if(strlen($this->_dbServer) && strlen($this->_dbName)) {

                try{
                    $this->_db = new PDO("pgsql:host={$this->_dbServer};dbname=$this->_dbName", $this->_dbUser, $this->_dbPassword);
                    $this->_dblink = true;

                } catch (PDOException $e) {
                    $err = 'Connect Error to: '.$this->_dbServer.'['.$e->getMessage().']';
                    if($this->core->is->development()) {
                        $err.= " [User:{$this->_dbUser}  Password:".substr($this->_dbPassword,0,2).'***]';
                    }
                    return $this->addError($e->getCode(),$err);
                }
                return $this->_dblink;


            } else {
                $this->addError("pg-missing-configuration","No DB server or DB name is not configured. ");
            }
            $this->core->__p->add('db connect. Class:'.__CLASS__,__FILE__);
            // Read dates with current timezone.
            //if(!$this->error) $this->command("set time_zone='%s'",array(date("P")));
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

            if($this->error) {
                return(false);
            } else {
                $ret=array();
                $this->core->__p->add('getDataFromQuery ',$_q,'note');
                try {
                    // execute query
                    $stmt = $this->_db->prepare($_q);
                    $stmt->execute();

                    // error control
                    if($error = $stmt->errorInfo()[2]) {
                        return $this->addError('query-error','Query Error [$q]: ' . $error[2]);
                    }

                    // retrieve data
                    $ret = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    $this->_affectedRows = count($ret);
                    $this->core->__p->add('getDataFromQuery ','','endnote');
                    $this->_lastExecutionMicrotime = round(microtime(true)-$start_global_time,4);
                    return($ret);

                } catch (Exception $e) {
                    $this->addError('query-error','Query Error [$q]: ' . $e->getMessage());
                    $this->_lastExecutionMicrotime = round(microtime(true)-$start_global_time,4);
                    $this->core->__p->add('getDataFromQuery ','','endnote');
                    return(false);
                }
            }
        }

        /**
         * Close the opened connection
         */
        function close() {
            //PDO does not requires to close the connection
            if($this->_dblink )  $this->_db->close();
            $this->_dblink = false;
        }

        /**
         * Load variables from $this->core->config
         */
        function loadCoreConfigVars() {
            $this->_dbServer = trim($this->core->config->get("dbServer"));
            $this->_dbUser = trim($this->core->config->get("dbUser"));
            $this->_dbPassword = trim($this->core->config->get("dbPassword"));
            $this->_dbName = trim($this->core->config->get("dbName"));
            $this->_dbProxy = trim($this->core->config->get("dbProxy"));
            $this->_dbCharset = ($this->core->config->get("dbCharset"))?$this->core->config->get("dbCharset"):'';
            if(strlen(trim($this->core->config->get("dbPort"))))
                $this->_dbport = trim($this->core->config->get("dbPort"));
        }

        /**
         * Set ConfigVars for connection
         */
        function setConfiVars($configVars) {
            $this->_dbServer = trim($configVars['dbServer']??'');
            $this->_dbUser = trim($configVars['dbUser']??'');
            $this->_dbPassword = trim($configVars['dbPassword']??'');
            $this->_dbName = trim($configVars['dbName']??'');
            $this->_port = trim($configVars['dbPort']??'5432');
            $this->_dbCharset = trim($configVars['dbCharset']??'');
        }

        /**
         * Build a Query receiving a string with %s as first parameter and an array of values as second paramater
         * @param $args
         * @return array|false|mixed|string|string[]|null
         */
        private function _buildQuery($args) {

            if(!is_array($args)) {
                $this->addError('query-error',"_buildQuery requires an array");
                return(false);
            }


            $q = array_shift($args);

            if(!strlen($q)) {
                $this->addError('query-error',"Function requires at least the query parameter");
                return(false);
            } else {
                $n_percentsS = substr_count($q,'%s');
                if(count($args)==1 && is_array($args[0])) {
                    $params = $args[0];

                } else {
                    if(count($args)==1 && !strlen($args[0])) $params = array();
                    else $params = $args;
                }
                unset($args);


                if(count($params) && count($params) != $n_percentsS) {
                    $this->addError('query-error',"Number of %s ($n_percentsS) doesn't count match with number of arguments (".count($params)."). Query: $q -> ".print_r($params,true));
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

            //return string with replacements
            return($q);
        }



        /**
         * Apply $this->_db->real_escape_string or addslashes if there is not db objext
         * @param $value
         * @return string
         */
        function scapeValue($value) {
            //            if(is_object($this->_db))
            //                return($this->_db->real_escape_string($value));
            //            else
            return(addslashes($value));
        }


        /**
         * Add an error in the class
         * @param string $code
         * @param $message
         */
        public function addError(string $code,$message) {
            $this->error=true;
            $this->errorCode=$code;
            $this->errorMsg[]=$message;
        }
    }
}