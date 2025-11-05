<?php

class API extends RESTful
{
    /**
     * https://www.php.net/manual/en/mongodb.installation.homebrew.php
     * composer require mongodb/mongodb:^1.6
     */
    function main()
    {

        if(!$this->core->config->get('test.mongo.uri')) return ($this->setErrorFromCodelib('not-allowed', 'missing config-var: core.uri'));
        if (!$this->core->model->mongoInit($this->core->config->get('test.mongo.uri'))) return ($this->setErrorFromCodelib('system-error', $this->core->model->errorMsg));

        $db = (isset($this->params[0]) && $this->params[0])?$this->params[0]:null;
        $collection = (isset($this->params[1]) && $this->params[1])?$this->params[1]:null;
        if(!$db) {
            $dbs = $this->core->model->mongoDB->getDatabases();
            $this->addReturnData([
                'next'=>'use _mongo/{db_name} to get mongoDabase collections',
                'dbs'=>$dbs]
            );
        } else {
            if(!$collection) {
                $collections = $this->core->model->mongoDB->getCollections($db);
                $this->addReturnData([
                        'next'=>'use _mongo/{db_name}/{collection_name} to get Records',
                        'db_name'=>$db,
                        'collections'=>$collections]
                );
            } else {
                $this->core->model->mongoDB->limit=2;
                $filter = ['phone'=>'664698102'];
                $filter = [];
                // https://docs.mongodb.com/php-library/current/reference/method/MongoDBCollection-find/
                $options = ['sort'=>['createdAt'=>-1],'limit'=>10,'projection'=>['status'=>1,'phone'=>1,'id'=>1,'_id'=>0]];
                $ret = $this->core->model->mongoDB->find($db,$collection,$filter,$options);
                $this->addReturnData([
                    'limit'=>$this->core->model->mongoDB->limit
                    ,'rows'=>count($ret)
                    ,'data'=>$ret]);
            }
        }
    }
}
