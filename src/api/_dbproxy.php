<?php

/**
 * It allows receive external connection to perform queries based on a security
 * Class API for _dbproxy
 */
class API extends RESTful
{
    /* @var $db CloudSQL */
    var $db;
    var $external_api = 'https://api.cloudframework.io/core/signin';
    var $user_spacename = '';
    var $external_integration_key = '';
    var $dstoken_data = [];
    var $proxy = [];


    function main()
    {
        ini_set('memory_limit', '512M');
        if (!$this->checkMethod('POST')) return;
        if (!$this->checkMandatoryFormParams('q')) return;
        if (!$this->checkMandatoryParam(0, 'Mising name of proxy')) return;
        $this->proxy = $this->core->config->get('core.db.proxy');
        if (!$this->proxy || !isset($this->proxy[$this->params[0]])) return ($this->setErrorFromCodelib('params-error', 'core.db.proxy does not have any match proxy'));
        $this->proxy = $this->proxy[$this->params[0]];



        if (!$this->checkSecurity()) return;
        if (!$this->dbSettings()) return;
        if (!$this->secureQuery($this->formParams['q'])) return;

        $this->db->connect();
        if($this->db->error()) return($this->setErrorFromCodelib('params-error',$this->db->getError()));

        $ret = $this->db->getDataFromQuery($this->formParams['q']);
        if($this->db->error()) return($this->setErrorFromCodelib('params-error',$this->db->getError()));
        $this->db->close();

        echo gzcompress(serialize($ret));
        exit;
    }

    function checkSecurity()
    {

        if (!isset($this->proxy['security']['type']) || $this->proxy['security']['type'] != 'X-DS-TOKEN') return ($this->setErrorFromCodelib('system-error', 'proxy does not have security attribute configured correctly'));

        //region Check if X-DS-TOKEN has been sent
        if (!strlen($this->getHeader('X-DS-TOKEN'))) return ($this->setErrorFromCodelib('params-error', 'missing X-DS-TOKEN header'));
        $this->dstoken = $this->getHeader('X-DS-TOKEN');
        if (!strlen($this->getHeader('X-EXTRA-INFO'))) return ($this->setErrorFromCodelib('params-error', 'missing X-EXTRA-INFO header'));
        $this->external_integration_key = $this->getHeader('X-EXTRA-INFO');
        //endregion

        //region SET $this->dstoken_data Checking X-DS-TOKEN with $this->external_api. Error the info is not en session or external_api returns error
        if (!strpos($this->dstoken, '__')) return ($this->setErrorFromCodelib('params-error', 'wrong X-DS-TOKEN format'));
        list($this->user_spacename, $foo) = explode('__', $this->dstoken, 2);
        $this->updateReturnResponse(['user_spacename' => $this->user_spacename]);

        $haskey = sha1($this->dstoken);
        $this->core->session->init($haskey);

        if ($haskey == $this->core->session->get('hashkey') && !isset($this->formParams['_reload'])) {
            $this->dstoken_data = $this->core->session->get('dstoken_data');
        } else {

            //Call for external integration
            $external_api = $this->core->request->post_json_decode(
                $this->external_api . '/' . $this->user_spacename . '/check?_update&from_dbproxy'
                , ['Fingerprint' => $this->core->system->getRequestFingerPrint()]
                , ['X-WEB-KEY' => 'Production'
                , 'X-DS-TOKEN' => $this->dstoken
                ,'X-EXTRA-INFO'=>$this->external_integration_key
            ]);

            if ($this->core->request->error) {
                $this->core->session->delete('hashkey');
                $this->core->session->delete('dstoken_data');
                return ($this->setErrorFromCodelib('security-error', $external_api));
            } else {
                $this->dstoken_data = $external_api['data'];
                $this->core->session->set('hashkey', $haskey);
                $this->core->session->set('dstoken_data', $this->dstoken_data);
            }
        };
        //endregion

        //region CHECK $this->checkAppSecurity($this->proxy['security']
        if (!$this->checkAppSecurity($this->proxy['security'])) return;
        //endregion

        return true;

    }

    /**
     * Check $security match with user $this->dstoken_data['User']['UserPrivileges'], $this->dstoken_data['User']['UserOrganizations'] or $this->dstoken_data['User']['UserSuperAdmin']
     * @param $security
     * @param bool $sendAPIError
     * @return bool|void
     */
    protected function checkAppSecurity($security, $sendAPIError = true)
    {

        //region Update credentials in returnData to facilitate security debug
        $this->returnData['UserSuperAdmin'] = (isset($this->dstoken_data['User']['UserSuperAdmin'])) ? $this->dstoken_data['User']['UserSuperAdmin'] : null;
        $this->returnData['user_privileges'] = (isset($this->dstoken_data['User']['UserPrivileges'])) ? $this->dstoken_data['User']['UserPrivileges'] : null;
        $this->returnData['user_organizations'] = (isset($this->dstoken_data['User']['UserOrganizations'])) ? $this->dstoken_data['User']['UserOrganizations'] : null;
        if ($sendAPIError)
            $this->returnData['app_security'] = $security;
        //endregion

        //if there is no security then any user can access. Then just return true;
        if (!$security) return true;

        //check spacenames security
        if (isset($security['spacenames']) && is_array($security['spacenames']) && $security['spacenames']) {
            if (!in_array(str_replace('_dev', '', $this->spacename), $security['spacenames'])) {
                if ($sendAPIError) return ($this->setErrorFromCodelib('not-allowed', 'Your spacename [' . $this->spacename . '] in not included in security'));
                else return false;
            }
        }

        //check user_spacenames security
        if (isset($security['user_spacenames']) && $security['user_spacenames']) {
            $user_spacenames = (is_array($security['user_spacenames'])) ? $security['user_spacenames'] : explode(',', $security['user_spacenames']);
            if (!in_array($this->returnData['user_spacename'], $user_spacenames)) {
                if ($sendAPIError) return ($this->setErrorFromCodelib('not-allowed', 'Your user_spacename does not have rights to access this CFO'));
                else return false;
            }
        }

        //check user_privileges security
        $sec_error = '';
        if (isset($security['user_privileges']) && $security['user_privileges'] && is_array($security['user_privileges'])) {

            $match = false;
            if (isset($this->dstoken_data['User']['UserPrivileges']) && is_array($this->dstoken_data['User']['UserPrivileges']))
                foreach ($this->dstoken_data['User']['UserPrivileges'] as $userPrivilege) {
                    if (in_array($userPrivilege, $security['user_privileges'])) $match = true;
                }
            if (isset($this->dstoken_data['User']['UserSuperAdmin']) && $this->dstoken_data['User']['UserSuperAdmin'] && in_array("_superadmin_", $security['user_privileges'])) $match = true;

            if (!$match) {
                $sec_error = 'user_privileges';
                //if($sendAPIError) return($this->setErrorFromCodelib('not-allowed','Your privileges does not match with privileges app: '.json_encode($security['user_privileges'])));
                //else return false;
            } else {
                return true;
            }
        }

        //check user_organizations security
        if (isset($security['user_organizations']) && $security['user_organizations'] && is_array($security['user_organizations'])) {

            $match = false;
            if (isset($this->dstoken_data['User']['UserOrganizations']) && is_array($this->dstoken_data['User']['UserOrganizations']))
                foreach ($this->dstoken_data['User']['UserOrganizations'] as $userOrganization) {
                    if (in_array($userOrganization, $security['user_organizations'])) $match = true;
                }

            if (!$match) {
                $sec_error = 'user_organizations';
                // if($sendAPIError) return($this->setErrorFromCodelib('not-allowed','Your privileges does not match with privileges app: '.json_encode($security['user_organizations'])));
                // else return false;
            } else {
                return true;
            }

        }

        if ($sec_error) {
            if ($sendAPIError) return ($this->setErrorFromCodelib('not-allowed', 'Your privileges does not match with ' . $sec_error . ' privilege app: ' . json_encode($security)));
            else return false;
        }

        return true;
    }

    /**
     * Set DB parameter from the proxy
     * @return bool|void
     */
    protected function dbSettings()
    {
        if($this->core->config->get('core.gcp.secrets.env_vars') && !isset($this->core->config->data['env_vars']) && !$this->core->config->readEnvVarsFromGCPSecrets())
            return($this->setErrorFromCodelib('system-error','Error reading env_vars from GCP Secrets'));

        $this->db = $this->core->loadClass('CloudSQL');
        $config_vars = ["dbServer","dbUser", "dbPassword", "dbName", "dbPort", "dbSocket","dbCharset"];
        foreach ($config_vars as $config_var) {
            if(isset($this->proxy[$config_var])) {
                $value = (isset($this->core->config->data['env_vars'][$this->proxy[$config_var]]))?$this->core->config->data['env_vars'][$this->proxy[$config_var]]:$this->proxy[$config_var];
                $this->db->setConf($config_var,$value);
            }
        }

        return true;

    }

    /**
     * Check if the query has any delete, trucate, drop etc.. forbidden string
     * @param $q
     * @return bool|void
     */
    protected function secureQuery($q)
    {

        $risk = ['DELETE ','TRUNCATE ','DROP '];
        foreach ($risk as $item) {
            if(stripos($q,$item)!==false) return($this->setErrorFromCodelib('not-allowed','your query has not allowed strings: '.$item));
        }

        return true;

    }
}
