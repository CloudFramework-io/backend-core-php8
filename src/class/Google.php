<?php
// https://developers.google.com/drive/v3/web/quickstart/php
// composer require google/apiclient
// Instagram Class v1

if (!defined ("_Google_CLASS_") ) {
    define("_Google_CLASS_", TRUE);


    /**
     * [$google = $this->core->loadClass('Google');] Class to facilitate Google integration
     * @package LabClasses
     */
    class Google
    {
        var $core;
        var $error = false;
        var $errorMsg = [];
        var $client;
        var $scope;
        var $type = 'installed';

        function __construct(Core7 &$core,$type='installed')
        {
            if($type) $this->type = $type;

            $this->core = $core;
            if(!is_dir($this->core->system->root_path.'/vendor/google/apiclient')) {
                $this->addError('Missing Google Client libreries. Execute from your document root: php composer.phar require google/apiclient:^2.0');
                $this->addError('You can find composer.phar from: curl https://getcomposer.org/composer.phar');
            } else {
                $this->client = new Google_Client();
                $this->client->setApplicationName('GoogleCloudFrameWork');

                // Read id and secret based on installed credentials
                $this->client_secret = $this->core->config->get('Google_Client');
                if(!is_array($this->client_secret))
                    $this->addError('Missing Google_Client config var with the credentials from Google. Get JSON OAUTH 2.0 credentials file from: https://console.developers.google.com/apis/credentials');
                else {
                    if(!isset($this->client_secret[$this->type])) {
                        if($this->type=='developer') $this->type.=' config var for API';
                        else $this->type.=' config array for Oauth 2.0 client ID';
                        $this->addError("Missing Google_Client:{$this->type} Key. Go to https://console.cloud.google.com/apis/credentials and specify the right credentials");
                    } else {
                        switch ($this->type) {
                            case "web":
                                $this->client->setAuthConfig(['web'=>$this->client_secret['web']]);
                                break;
                            case "installed":
                                $this->client->setAuthConfig(['installed'=>$this->client_secret['installed']]);
                                break;
                            case "developer":
                                $this->client->setDeveloperKey($this->client_secret['developer']);
                                break;
                            default:
                                die('Wrong Google $type credentials');
                                break;

                        }
                    }
                }
            }
        }

        function addError($value)
        {
            $this->error = true;
            $this->errorMsg[] = $value;
        }

        function verifyToken($id_token, $uid=null) {
            $ret =$this->core->request->get_json_decode('https://www.googleapis.com/oauth2/v3/tokeninfo',['id_token'=>$id_token]);
            if(isset($ret['error'])) return($this->addError($ret));

            if($uid && $uid != $ret['sub']) return($this->addError('uid does not match with login_provider_indetifier'));

            if($this->client->getClientId() != $ret['aud']) {
                $this->core->logs->add('This token has not been generated with internal system client_id');
            }
            return $ret;
        }
    }
}
