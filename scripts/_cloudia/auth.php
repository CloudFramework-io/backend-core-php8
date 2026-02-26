<?php

class Script extends CoreScripts
{

    /** @var string Platform ID for CFO operations (default: cloudframework) */
    var $platform_id = '';

    /** @var array HTTP headers for API authentication */
    var $headers = [];

    function main()
    {

        //region SET $this->platform_id from configuration
        $this->platform_id = $this->core->config->get('core.erp.platform_id');
        if (!$this->platform_id) return $this->addError('config-error', 'core.platform_id is not defined');
        $this->sendTerminal("Platform ID: {$this->platform_id}");
        $auth_code = $this->formParams['authenticator_code'] ?? null;
        $reset = ($this->formParams['_reset']??$auth_code)?true:false;
        //endregion


        //region AUTHENTICATE user and SET $this->headers (authentication headers for API requests)
        if($reset) $this->resetPlatformToken($this->platform_id);
        if (!$this->authPlatformUserWithLocalAccessToken($this->platform_id,'','',$auth_code)) {
            $this->sendTerminal("Authentication failed");
            if($this->core->user->errorCode=='google-authenticator') {
                $this->sendTerminal("ERROR. IT REQUIRES ?authenticator_code=xxxx to authenticate using 2fa");
            } else {
                $this->sendTerminal($this->errorMsg);
            }
            $this->reset();
            return false;
        }
        //endregion

        //region EXECUTE METHOD_{$method} (dynamic method execution based on command)
        $method = ($this->params[2] ?? 'default');
        $this->sendTerminal(" - method: {$method}");

        $user = $this->core->security->getGoogleEmailAccount();
        $this->sendTerminal("GOOGLE-EMAIL-ACCOUNT: ".$user);

        if (!$this->useFunction('METHOD_' . str_replace('-', '_', $method))) {
            return $this->addError("   #/{$method} is not implemented");
        }
        if(!$this->error) {
            $this->core->logs->reset();
        }
        //endregion
    }

    public function METHOD_default() {
        $this->sendTerminal("Available commands:");
        $this->sendTerminal("  /info              - Return authenticated user email");
        $this->sendTerminal("  /x-ds-token        - Return your token to connect with your EaaS");
        $this->sendTerminal("  /access-token      - Return your Google Access Token");
        $this->sendTerminal("Send ?authenticator_code=xxxx to authenticate using 2fa");
        $this->sendTerminal("Send ?_reset to reset your token and re-authenticate. It requires authcode");
    }

    public function METHOD_info() {
        $email = $this->core->security->getGoogleEmailAccount();
        $this->sendTerminal("Authenticated user: {$email}");
    }

    public function METHOD_x_ds_token() {
        $this->sendTerminal("X-DS-TOKEN: {$this->core->user->token}");
    }

    public function METHOD_access_token() {
        $user = $this->core->security->getGoogleEmailAccount();
        $this->sendTerminal("GOOGLE-ACCESS-TOKEN: ".$this->getUserGoogleAccessToken($user)['token']??'error');
    }
}
