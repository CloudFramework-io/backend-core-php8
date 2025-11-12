<?php
/*
 * CloudFramework Mongo Class
 * https://www.php.net/manual/en/mongodb.installation.homebrew.php
 * https://www.mongodb.com/developer/quickstart/php-setup/
 * Mac:
 *     pecl install mongodb
 *     php -i | grep mongodb
 *     composer require mongodb/mongodb:^1.9
 * @package InDevelopment
 */

if (!defined ("_MONGODB_CLASS_") ) {
    define("_MONGODB_CLASS_", TRUE);

    class DataMongoDB
    {

        /** @var Core7  */
        protected $core;
        var $error=false;                      // Holds the last error
        var $errorMsg=[];                      // Holds the last error

        /** @var MongoDB\Client $_client */
        protected $_client = null;              // Database Connection Link
        var $uri = '';                          // uri to connect
        var $_debug = false;
        var $_collections = [];
        var $_lastQuery = null;

        // Query Variables
        var $limit = 100;
        var $page = 0;

        // Syslogs
        var $sendSysLogs = true;


        function __construct(Core7 &$core, $uri = '')
        {
            $this->core = $core;
            if($uri) $this->uri = $uri;
            else $this->uri = $this->core->config->get('mongo.uri');
        }

        /**
         * Stablish a connection with MONGODB
         * @param string $h Host
         * @param string $u User
         * @param string $p Password
         * @param string $db DB Name
         * @param string $port Port. Default 3306
         * @param string $socket Socket
         * @return bool True if connection is ok.
         */
        function connect($uri='')
        {

            //region VALIDATE previous connections and params
            if ($this->_client) return true; // Optimize current connection.
            if($uri) $this->uri = $uri;
            if(!$this->uri) return($this->addError('Missing uri of connection'));
            //endregion

            //region INIT Logs
            $uri_to_show = preg_replace('/\/\/[^\/]*/','//***:***.****.****.****',$this->uri);
            $_time = microtime(TRUE);
            if($this->sendSysLogs)
                $this->core->logs->sendToSysLog("DataMongoDB->connect {$uri_to_show}");
            $this->core->__p->add('DataMongoDB->connect: ', "{$uri_to_show}", 'note');
            //endregion

            //region CONNECT MONGO
            $this->_client = new MongoDB\Client($this->uri);
            //endregion

            //region END Logs
            $this->core->__p->add('DataMongoDB->connect: ', null, 'endnote');
            if($this->sendSysLogs) {
                $_time = round(microtime(TRUE) -$_time,4);
                $this->core->logs->sendToSysLog("end DataMongoDB->connect: {$_time} secs",(($this->error)?'debug':'info'));
            }
            //endregion

            return true;

        }

        /**
         * Get the list of databases of a Mongo Conection
         * @return array|void
         */
        public function getDatabases() {
            if(!$this->connect()) return;
            $dbs =  $this->_client->listDatabases();
            $ret = [];
            foreach ($dbs as $db) {
                $ret[] = $db->getName();
            }
            return $ret;
        }

        /**
         * Get the list of collections of a Mongo Database
         * @return array|void
         */
        public function getCollections($db) {
            if(!$this->connect()) return;

            //region INIT Logs
            $_time = microtime(TRUE);
            if($this->sendSysLogs)
                $this->core->logs->sendToSysLog("DataMongoDB->getCollections {$db}");
            $this->core->__p->add('DataMongoDB->getCollections: ', "{$db}", 'note');
            //endregion

            $db=$this->_client->selectDatabase($db);
            $collections = $db->listCollections();
            $ret = [];
            foreach ($collections as $collection) {
                $ret[] = $collection->getName();
            }

            //region END Logs
            $this->core->__p->add('DataMongoDB->getCollections: ', null, 'endnote');
            if($this->sendSysLogs) {
                $_time = round(microtime(TRUE) -$_time,4);
                $this->core->logs->sendToSysLog("end DataMongoDB->getCollections: {$_time} secs",(($this->error)?'debug':'info'));
            }
            //endregion
            return $ret;
        }

        /**
         * Execute a find action over a collection and return only the keys
         * @param $db
         * @param $collection
         * @param $filter
         * @param array $options [sort=array,skip=>integer,limit=>integer,comment=>string,returnKey=>boolean,]. More info in https://docs.mongodb.com/php-library/current/reference/method/MongoDBCollection-find/
         * @return array|void
         */
        public function getIndexes($db,$collection) {

            //region INIT Logs
            $_time = microtime(TRUE);
            if($this->sendSysLogs)
                $this->core->logs->sendToSysLog("DataMongoDB->getIndexes {$db}.{$collection}");
            $this->core->__p->add('DataMongoDB->getIndexes: ', "{$db}.{$collection}", 'note');
            //endregion

            //region SET $mongo_collection and prepare $options,$filter
            /** @var \MongoDB\Collection $mongo_collection */
            // Aim a collection
            if(!($mongo_collection = $this->connectWithCollection($db,$collection))) return;
            //endregion

            //region set $indexes = $mongo_collection->listIndexes(); and transform results into $ret
            $indexes = $mongo_collection->listIndexes();
            $ret=[];
            if(is_object($indexes)) foreach ($indexes as $index) {
                $ret[]=['name'=>$index->getName(),'key'=>$index->getKey()];
            }
            //endregion

            //region END Logs
            $this->core->__p->add('DataMongoDB->getIndexes: ', null, 'endnote');
            if($this->sendSysLogs) {
                $_time = round(microtime(TRUE) -$_time,4);
                $this->core->logs->sendToSysLog("end DataMongoDB->getIndexes {$db}.{$collection}: {$_time} secs",(($this->error)?'debug':'info'));
            }
            //endregion

            //region RETURN $ret
            return $ret;
            //endregion
        }

        /**
         * Execute a find action over a $id
         * @param $db
         * @param $collection
         * @param $id String
         * @param array $options [projection=>array,sort=array,skip=>integer,limit=>integer,comment=>string,returnKey=>boolean,]. More info in https://docs.mongodb.com/php-library/current/reference/method/MongoDBCollection-find/
         * @return array|null
         */
        public function getById($db,$collection,$id) {
            $ret= $this->find($db,$collection,['_id'=>new MongoDB\BSON\ObjectID($id)]);
            if($ret) return $ret[0];
            else return null;
        }

        /**
         * Execute a find action over a collection and return only the keys
         * @param $db
         * @param $collection
         * @param $filter
         * @param array $options [sort=array,skip=>integer,limit=>integer,comment=>string,returnKey=>boolean,]. More info in https://docs.mongodb.com/php-library/current/reference/method/MongoDBCollection-find/
         * @return array|void
         */
        public function findIds($db,$collection,$filter=[],$options = []) {
            if(!is_array($options)) $options=[];
            $options['projection']=['_id'=>1];
            return($this->find($db,$collection,$filter,$options));
        }

        /**
         * Return the last query value set in a find o update operation
         * @return null|strig
         */
        public function getLastQuery() {
            return $this->_lastQuery;
        }

        /**
         * Execute a find action over a collection
         * @param $db
         * @param $collection
         * @param $filter
         * @param array $options [projection=>array,sort=array,skip=>integer,limit=>integer,comment=>string,returnKey=>boolean,]. More info in https://docs.mongodb.com/php-library/current/reference/method/MongoDBCollection-find/
         * @return array|void
         */
        public function find($db,$collection,$filter=[],$options = []) {

            //region INIT Logs
            $_time = microtime(TRUE);
            if($this->sendSysLogs)
                $this->core->logs->sendToSysLog("DataMongoDB->find {$db}.{$collection} ".(($filter) ? '{with filter}' : '{no filter}')." limit={$this->limit}, page={$this->page}");
            $this->core->__p->add('DataMongoDB->find: ', "{$db}.{$collection} " .(($filter) ? '{with filter}' : '{no filter}')." limit={$this->limit}, page={$this->page}", 'note');
            //endregion

            //region SET $mongo_collection and prepare $options,$filter
            /** @var \MongoDB\Collection $mongo_collection */
            // Aim a collection
            if(!($mongo_collection = $this->connectWithCollection($db,$collection))) return;

            // Set limit to find
            if(!isset($options['limit']))
                $options = $options+['limit'=>$this->limit];

            // Set Page of size $this->limit
            if($this->page && !isset($options['skip']))
                $options = $options+['skip'=>$this->limit*$this->page];

            // Execute find.
            if(!is_array($filter)) $filter=[];
            //endregion

            //region EXECUTE $ret = $mongo_collection->find($filter,$options)->toArray();
            $ret = $mongo_collection->find($filter,$options)->toArray();
            $this->_lastQuery = "{$db}.{$collection} where ".json_encode($filter);
            //endregion

            //region TRANSFORM $ret the result into a simple array
            foreach ($ret as $i=>$foo) {
                $this->transformTypes($ret[$i]);
            }
            //endregion

            //region END Logs
            $this->core->__p->add('DataMongoDB->find: ', null, 'endnote');
            if($this->sendSysLogs) {
                $_time = round(microtime(TRUE) -$_time,4);
                $this->core->logs->sendToSysLog("end DataMongoDB->find {$db}.{$collection} ".(($filter) ? '{with filter}' : '{no filter}')." limit={$this->limit}, page={$this->page}: {$_time} secs",(($this->error)?'debug':'info'));
            }
            //endregion

            return($ret);
        }

        /**
         * Execute an insertion of one or multiple documents
         * https://docs.mongodb.com/php-library/current/reference/method/MongoDBCollection-insertOne/
         * @param $db
         * @param $collection
         * @param $documents
         * @return array|void
         */
        public function insertDocuments($db,$collection,$documents) {

            //region VERIFY $documents and transform it into an array[0..n]
            if(!$documents || !is_array($documents)) return ($this->addError(' insert($db,$collection,$document) $document is empty or not an array'));
            // if $documents is not an array 0..n convert into it
            if(!isset($documents[0])) $documents = [$documents[0]];
            //endregion

            //region INIT Logs
            $_time = microtime(TRUE);
            if($this->sendSysLogs)
                $this->core->logs->sendToSysLog("DataMongoDB->insertDocuments {$db}.{$collection} ".(count($documents) . ' documents'));
            $this->core->__p->add('DataMongoDB->insertDocuments: ', "{$db}.{$collection} " .(count($documents) . ' document(s)'), 'note');
            //endregion

            //region TRANSFORM _id fields and datefields into MongoDB\BSON\ObjectId or MongoDB\BSON\UTCDateTime
            foreach ($documents as $i=>$foo) {
                $this->prepareTypes($documents[$i]);
            }
            //endregion

            //region SET $mongo_collection
            /** @var \MongoDB\Collection $mongo_collection */
            $mongo_collection = $this->connectWithCollection($db,$collection);
            //endregion

            //region INSERT $documents and GET $ids
            $insertManyResults =$mongo_collection->insertMany($documents);
            $ids = $insertManyResults->getInsertedIds();
            //endregion

            //region INSERT $ids in $documents
            foreach ($ids as $i=>$id) {
                $documents[$i]['_id'] = $id->jsonSerialize()['$oid'];
                $this->transformTypes($documents[$i]);
            }
            //endregion

            //region END Logs
            if($this->sendSysLogs){
                $_time = round(microtime(TRUE) -$_time,4);
                $this->core->logs->sendToSysLog("end DataMongoDB->insertDocuments: {$_time} secs");
            }
            $this->core->__p->add('DataMongoDB->insertDocuments: ', null, 'endnote');
            //endregion

            //region RETURN $documents inserted with their ids
            return($documents);
            //endregion

        }


        /**
         * Execute an update of one document. This document requires a _id field
         * https://docs.mongodb.com/php-library/current/reference/method/MongoDBCollection-insertOne/
         * @param $db
         * @param $collection
         * @param $update_info
         * @return array|void
         */
        public function updateDocumentWithId($db,$collection,$update_info) {

            //region VERIFY $update_info and transform it into an array[0..n]
            if(!$update_info || !is_array($update_info) || !isset($update_info['_id']) || count($update_info)<2) return ($this->addError(' update($db,$collection,$update_info) $update_info is empty or not an array or it does not have a _id field or does not have more than one attribute'));
            //endregion

            //region INIT Logs
            $_time = microtime(TRUE);
            if($this->sendSysLogs)
                $this->core->logs->sendToSysLog("DataMongoDB->updateDocumentWithId {$db}.{$collection}({$update_info['_id']}) with ".((count($update_info)-1) . ' field(s)'));
            $this->core->__p->add('DataMongoDB->updateDocumentWithId: ', "{$db}.{$collection}({$update_info['_id']}) with " .((count($update_info)-1) . ' field(s)'), 'note');
            //endregion

            //region SET $id, extract _id from $update_info and transform date fields
            $id = new MongoDB\BSON\ObjectId($update_info['_id']);
            unset($update_info['_id']);
            $this->prepareTypes($update_info);
            //endregion

            //region SET $mongo_collection
            /** @var \MongoDB\Collection $mongo_collection */
            $mongo_collection = $this->connectWithCollection($db,$collection);
            //endregion

            //region UPDATE $update_info with _id=$id
            $updateResult =$mongo_collection->updateOne(['_id'=>$id],['$set' =>$update_info]);
            $total_modified = $updateResult->getModifiedCount();
            $total_matched = $updateResult->getMatchedCount();
            $this->_lastQuery = "{$db}.{$collection} where ".json_encode(['_id'=>$id]);

            //endregion

            //region END Logs
            if($this->sendSysLogs){
                $_time = round(microtime(TRUE) -$_time,4);
                $this->core->logs->sendToSysLog("end DataMongoDB->updateDocumentWithId, total_matched: {$total_matched},  total_modified: {$total_modified} in {$_time} secs");
            }
            $this->core->__p->add('DataMongoDB->updateDocumentWithId: ', "total_matched: {$total_matched},  total_modified: {$total_modified}", 'endnote');
            //endregion

            //region RETURN true
            return true;
            //endregion

        }

        /**
         * Execute a deletion based on id
         * https://docs.mongodb.com/php-library/current/reference/method/MongoDBCollection-deleteOne/
         * @param $db
         * @param $collection
         * @param $documents
         * @param array $options [projection=>array,sort=array,skip=>integer,limit=>integer,comment=>string,returnKey=>boolean,]. More info in https://docs.mongodb.com/php-library/current/reference/method/MongoDBCollection-find/
         * @return array|void
         */
        public function deleteById($db,$collection,$id) {
            if(!$id || !is_string($id)) return ($this->addError(' deleteById($db,$collection,$id) $id is empty or not is a string'));


            //region INIT Logs
            $_time = microtime(TRUE);
            if($this->sendSysLogs)
                $this->core->logs->sendToSysLog("DataMongoDB->deleteById {$db}.{$collection}({$id})");
            $this->core->__p->add('DataMongoDB->deleteById: ', "{$db}.{$collection}({$id})", 'note');
            //endregion

            /** @var \MongoDB\Collection $mongo_collection */
            $mongo_collection = $this->connectWithCollection($db,$collection);
            $deleteResult = $mongo_collection->deleteOne(['_id' => new MongoDB\BSON\ObjectID($id)]);

            //region END Logs
            if($this->sendSysLogs){
                $_time = round(microtime(TRUE) -$_time,4);
                $this->core->logs->sendToSysLog("end DataMongoDB->deleteById: {$_time} secs");
            }
            $this->core->__p->add('DataMongoDB->deleteById: ', null, 'endnote');
            //endregion

            return $deleteResult->getDeletedCount();

        }

         /**
         * Transform _id fields and date contents into MongoDB objects
         * @param array $entity
         * @param int $level
         * @return void
         */
        private function prepareTypes(&$entity,$level=0) {
            if(!is_array($entity)) return $entity;
            foreach ($entity as $i=>$item) {
                if(is_array($item)) {
                    $this->prepareTypes($entity[$i],$level+1);
                }
                elseif(is_string($item) && $i==='_id') {
                    $entity[$i] = new MongoDB\BSON\ObjectId($item);
                }
                elseif(is_string($item) && preg_match('/[0-9][0-9][0-9][0-9]-[0-9][0-9]-[0-9][0-9]T[0-9][0-9]:[0-9][0-9]:[0-9][0-9]\.[0-9][0-9][0-9]Z$/',$item)) {
                    $entity[$i] = new MongoDB\BSON\UTCDateTime(new DateTime($item));
                }
            }
        }

        /**
         * Transform Mongo objects in arrays, strings , numbers
         * @param array $entity
         * @param int $level
         * @return void
         */
        private function transformTypes(&$entity,$level=0) {
            if(!is_array($entity)) return $entity;
            $ret = [];
            foreach ($entity as $i=>$item) {
                if(is_array($item)) {
                    $this->transformTypes($entity[$i],$level+1);
                }
                elseif(is_object($item)) {
                    switch (get_class($item)) {
                        case "MongoDB\BSON\ObjectId":
                            /** @var  MongoDB\BSON\ObjectId $item */
                            $entity[$i] = $item->jsonSerialize()['$oid'];
                            break;
                        case "MongoDB\BSON\UTCDateTime":
                            /** @var  MongoDB\BSON\UTCDateTime $item */
                            $entity[$i] = $item->toDateTime()->format('Y-m-d\TH:i:s.v\Z');
                            break;
                    }
                }
            }
        }

        /**
         * Return a Collection to operate with
         * @param $db
         * @param $collection
         * @return mixed|\MongoDB\Collection
         */
        private function connectWithCollection($db, $collection) {
            if(isset($this->_collections["{$db}_{$collection}"])) return($this->_collections["{$db}_{$collection}"]);

            $db = $this->_client->selectDatabase($db);
            $this->_collections["{$db}_{$collection}"] = $db->selectCollection($collection,[
                'typeMap' => [
                    'root' => 'array',
                    'document' => 'array',
                    'array' => 'array',
                ]]);

            return($this->_collections["{$db}_{$collection}"]);
        }

        /**
         * Error Functions
         * @return bool
         */
        function addError($err) {
            $this->errorMsg[] = $err;
            $this->error = true;
        }
    }
}
