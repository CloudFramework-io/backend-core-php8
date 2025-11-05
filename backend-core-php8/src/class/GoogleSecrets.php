<?php
/*
 */

use Google\Cloud\SecretManager\V1\Replication;
use Google\Cloud\SecretManager\V1\Replication\Automatic;
use Google\Cloud\SecretManager\V1\Secret;
use Google\Cloud\SecretManager\V1\SecretManagerServiceClient;
use Google\Cloud\SecretManager\V1\SecretPayload;


/*
 * Based on: https://cloud.google.com/secret-manager/docs/reference/libraries#client-libraries-usage-php
 */

if (!defined ("_GoogleSecrets_CLASS_") ) {
    define("_GoogleSecrets_CLASS_", TRUE);

    /**
     * [$gsecrets = $this->core->loadClass('GoogleSecrets');] Class to facilitate GoogleSecrets integration
     * @package LabClasses
     */
    class GoogleSecrets
    {

        var $core;
        var $error = false;
        var $errorMsg = [];

        var $client = null;
        var $projectPath = null;
        var $project_id = null;

        function __construct(Core7 &$core)
        {
            $this->core = $core;
            if(getenv('PROJECT_ID')) $this->project_id = getenv('PROJECT_ID');
            if($this->core->config->get('core.gcp.project_id')) $this->project_id = $this->core->config->get('core.gcp.project_id');
            if($this->core->config->get('core.gcp.secrets.project_id')) $this->project_id = $this->core->config->get('core.gcp.secrets.project_id');

            if(!$this->project_id) return($this->addError('Missing PROJECT_ID env_var or core.gcp.project_id, core.gcp.secrets.project_id config vars'));
            $this->client = new SecretManagerServiceClient();
            $this->projectPath = $this->client->projectName($this->project_id);
        }

        /**
         * Create a secret
         * @param $secretId
         * @return Secret|void
         * @throws \Google\ApiCore\ApiException
         */
        public function createSecret($secretId) {
            if($this->error) return;
            try {
                $secret = $this->client->createSecret($this->projectPath, $secretId,
                    new Secret([
                        'replication' => new Replication([
                            'automatic' => new Automatic(),
                        ]),
                    ])
                );
            } catch (Exception $e) {
                return($this->addError($e->getMessage()));
            }
            return $secret;
        }


        public function getSecret($secretId,$version='latest') {
            if($this->error) return;
            try {
                $secretName = $this->client->secretVersionName($this->project_id, $secretId,$version);
                $response = $this->client->accessSecretVersion($secretName);
                return($response->getPayload()->getData());
            } catch (Exception $e) {
                return($this->addError($e->getMessage()));
            }
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
}
