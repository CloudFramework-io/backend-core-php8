<?php
/**
 * Use $this->core->user object to handle authentication authentication
 */
class API extends RESTful
{
    var $basic_user='test';                 // User to use in the login
    var $basic_password='password';         // Password to use in the login
    var $basic_namespace='_apis';           // Namespace to associate the users
    var $basic_max_tokens = 3;              // Max tokens to be store for namespace_user
    var $basic_expiration_time = 3600*24;   // Time to expire a basic token


    var $erp_auth_point = '';

    /**
     * Main function
     */
    function main()
    {
        //You can restrict methods in main level
        if (!$this->checkMethod('GET,POST,PUT,DELETE')) return;

        //Call internal ENDPOINT_$end_point
        $this->end_point = str_replace('-', '_', ($this->params[0] ?? 'default'));
        if (!$this->useFunction('ENDPOINT_' . str_replace('-', '_', $this->end_point))) {
            return ($this->setErrorFromCodelib('params-error', "/{$this->service}/{$this->end_point} is not implemented"));
        }
    }

    /**
     * Endpoint to add a default feature. We suggest to use this endpoint to explain
     * how to use other endpoints
     */
    public function ENDPOINT_default()
    {
        // return Data in json format by default
        $this->addReturnData(
            [
                "end-point /default [current]"=>"use /{$this->service}/default"
                ,"end-point /hello"=>"use /{$this->service}/basic/auth"
                ,"end-point /hello"=>"use /{$this->service}/basic/check"
                ,"end-point /hello"=>"use /{$this->service}/erp/auth"
                ,"end-point /hello"=>"use /{$this->service}/erp/check"
            ]);
    }

    /**
     * Endpoint to add a default feature. We suggest to use this endpoint to explain
     * how to use other endpoints
     */
    public function ENDPOINT_basic()
    {
        //region SET $action = (auth|check)
        if(!$action = $this->checkMandatoryParam(1,'Missing right action. /basic/(auth|check|logout)',['auth','check','logout'])) return;
        //endregion

        //region CALL $this->basic_auth() or $this->basic_check()
        switch ($action) {
            case "auth":
                return($this->basic_auth());
            case "check":
                return($this->basic_check());
            case "logout":
                return($this->basic_logout());
            default:
                return($this->setErrorFromCodelib('params-error','/basic/'.$action.' is not supported'));
        }
        //endregion
    }

    /**
     * Execute a basic auth
     * how to use other endpoints
     */
    private function basic_auth()
    {
        //region CHECK method is POST
        if(!$this->checkMethod('POST')) return;
        //endregion

        //region VERIFY user,password with a basic algorithm
        if(!$this->checkMandatoryFormParams(['user','password'])) return;
        if($this->formParams['user']!=$this->basic_user) return($this->setErrorFromCodelib('security-error','wrong user. Use {"user":"test","password":"password"}'));
        if($this->formParams['password']!=$this->basic_password) return($this->setErrorFromCodelib('security-error','wrong password. Use {"user":"test","password":"password"}'));
        //endregion

        //region CREATE $token for $this->param['user] in $this->namespace and assign a data to be stored
        $data = ['updated_at'=>date('Y-m-d H:i:s')];                // Assign the data you think is relevant
        $this->core->user->maxTokens = $this->basic_max_tokens;            // Define the Max tokens to keep in memory. 10 by default. When it exceeds the number the first token is deleted
        $this->core->user->expirationTime = $this->basic_expiration_time;  // Define the when the tokens has to expire 3600 seconds by defaults

        // Create the token in cache memory
        if(!$this->core->user->createUserToken($this->formParams['user'],$this->basic_namespace,$data))
            return $this->setErrorFromCodelib('system-error',$this->core->user->errorMsg);

        //endregion

        //region SET response to 201 and return Data in json format by default
        $this->ok = 201;
        $this->addReturnData(
            [
                'token'=>$this->core->user->token,
                'expires'=>$this->core->user->tokenExpiration,
                'max_tokens'=>$this->core->user->maxTokens,
                'expiration_time'=>$this->core->user->expirationTime,
                'data'=>$this->core->user->data,
            ]);
        //endregion

    }

    /**
     * Verify a token sent through X-WEB-KEY header
     * how to use other endpoints
     */
    private function basic_check()
    {

        //region CHECK method is GET
        if(!$this->checkMethod('GET')) return;
        //endregion

        //region SET $token from $this->getHeader('X-WEB-KEY')
        if(!$token = $this->getHeader('X-WEB-KEY')) return($this->setErrorFromCodelib('params-error','Missing X-WEB-KEY header'));
        //endregion

        //region VERIFY $token is right
        $this->core->user->maxTokens = $this->basic_max_tokens;            // Define the Max tokens to keep in memory. 10 by default. When it exceeds the number the first token is deleted
        $this->core->user->expirationTime = $this->basic_expiration_time;  // Define the when the tokens has to expire 3600 seconds by defaults

        if(!$this->core->user->checkUserToken($token))  return $this->setErrorFromCodelib('security-error',$this->core->user->errorMsg);
        //endregion

        //region RETURN user Data in json format by default
        $this->addReturnData(
            [
                '$this->core->user->isAuth()'=>$this->core->user->isAuth(),
                '$this->core->user->id'=>$this->core->user->id,
                '$this->core->user->namespace'=>$this->core->user->namespace,
                '$this->core->user->token'=>$this->core->user->token,
                '$this->core->user->activeTokens'=>$this->core->user->activeTokens,
                '$this->core->user->maxTokens'=>$this->core->user->maxTokens,
                '$this->core->user->expirationTime'=>$this->core->user->expirationTime,
                '$this->core->user->tokenExpiration'=>round($this->core->user->tokenExpiration),
                '$this->core->user->data'=>$this->core->user->data,
            ]);
    }

    /**
     * Verify a token sent through X-WEB-KEY header
     * how to use other endpoints
     */
    private function basic_logout()
    {

        //region CHECK method is GET
        if(!$this->checkMethod('GET')) return;
        //endregion

        //region SET $token from $this->getHeader('X-WEB-KEY')
        if(!$token = $this->getHeader('X-WEB-KEY')) return($this->setErrorFromCodelib('params-error','Missing X-WEB-KEY header'));
        //endregion

        //region LOGOUT $token
        if(!$this->core->user->logoutUserToken($token,isset($this->formParams['_delete_all_tokens'])))  return $this->setErrorFromCodelib('security-error',$this->core->user->errorMsg);
        //endregion

        //region RETURN user Data in json format by default
        $this->addReturnData(
            [
                '$this->core->user->isAuth()'=>$this->core->user->isAuth(),
                '_delete_all_tokens'=>isset($this->formParams['_delete_all_tokens'])
            ]);
    }



    /**
     * Endpoint to add a default feature. We suggest to use this endpoint to explain
     * how to use other endpoints
     */
    public function ENDPOINT_erp()
    {
        //region SET $action = (auth|check)
        if(!$action = $this->checkMandatoryParam(1,'Missing right action. /erp/(auth||logout)',['auth','check','logout'])) return;
        //endregion

        //region CALL $this->basic_auth() or $this->basic_check()
        switch ($action) {
            case "auth":
                return($this->erp_auth());
            case "check":
                return($this->erp_check());
            case "logout":
                return($this->erp_logout());
            default:
                return($this->setErrorFromCodelib('params-error','/erp/'.$action.' is not supported'));
        }
        //endregion
    }



    /**
     * Execute a basic auth
     * how to use other endpoints
     */
    private function erp_auth()
    {
        //region CHECK method is POST
        if(!$this->checkMethod('POST')) return;
        //endregion

        //region CHECK params 'user','password','namespace','integration_key'
        if(!$this->checkMandatoryFormParams(['user','password','namespace','integration_key'])) return;
        //endregion

        //region CALL $this->core->user->setERPToken
        $user = $this->formParams['user'];
        $password = $this->formParams['password'];
        $namespace = $this->formParams['namespace'];
        $integration_key = $this->formParams['integration_key'];

        $this->core->user->maxTokens = $this->basic_max_tokens;            // Define the Max tokens to keep in memory. 10 by default. When it exceeds the number the first token is deleted
        $this->core->user->expirationTime = $this->basic_expiration_time;  // Define the when the tokens has to expire 3600 seconds by defaults
        if(!$this->core->user->loginERP($user,$password,$namespace,$integration_key)) return ($this->setErrorFromCodelib('params-error',$this->core->user->errorMsg));
        //endregion

        //region SET response to 201 and return Data in json format by default
        $this->ok = 201;
        $this->addReturnData(
            [
                'token'=>$this->core->user->token,
                'expires'=>$this->core->user->tokenExpiration,
                'max_tokens'=>$this->core->user->maxTokens,
                'expiration_time'=>$this->core->user->expirationTime,
                'data'=>$this->core->user->data
            ]);
        //endregion

    }

    /**
     * Verify a ERP token sent through headers  X-DS-TOKEN and X-EXTRA-INFO
     * how to use other endpoints
     */
    private function erp_check()
    {

        //region CHECK method is GET
        if(!$this->checkMethod('GET')) return;
        //endregion

        //region SET $token from $token, $integration_key
        if(!$token = $this->getHeader('X-DS-TOKEN')) return($this->setErrorFromCodelib('params-error','Missing X-DS-TOKEN header'));
        if(!$integration_key = $this->getHeader('X-EXTRA-INFO')) return($this->setErrorFromCodelib('params-error','Missing X-EXTRA-INFO header'));
        $refresh = isset($this->formParams['_update']);
        //endregion

        //region VERIFY $token is right
        $this->core->user->maxTokens = $this->basic_max_tokens;            // Define the Max tokens to keep in memory. 10 by default. When it exceeds the number the first token is deleted
        $this->core->user->expirationTime = $this->basic_expiration_time;  // Define the when the tokens has to expire 3600 seconds by defaults
        if(!$this->core->user->checkERPToken($token,$integration_key,$refresh))  return $this->setErrorFromCodelib('security-error',$this->core->user->errorMsg);
        //endregion

        //region RETURN user Data in json format by default
        $this->addReturnData(
            [
                '$this->core->user->isAuth()'=>$this->core->user->isAuth(),
                '$this->core->user->id'=>$this->core->user->id,
                '$this->core->user->namespace'=>$this->core->user->namespace,
                '$this->core->user->token'=>$this->core->user->token,
                '$this->core->user->activeTokens'=>$this->core->user->activeTokens,
                '$this->core->user->maxTokens'=>$this->core->user->maxTokens,
                '$this->core->user->expirationTime'=>$this->core->user->expirationTime,
                '$this->core->user->tokenExpiration'=>round($this->core->user->tokenExpiration),
                '$this->core->user->data'=>$this->core->user->data,
            ]);
    }

    /**
     * Verify a token sent through X-WEB-KEY header
     * how to use other endpoints
     */
    private function erp_logout()
    {

        //region CHECK method is GET
        if(!$this->checkMethod('GET')) return;
        //endregion

        //region SET $token from $token, $integration_key
        if(!$token = $this->getHeader('X-DS-TOKEN')) return($this->setErrorFromCodelib('params-error','Missing X-DS-TOKEN header'));
        if(!$integration_key = $this->getHeader('X-EXTRA-INFO')) return($this->setErrorFromCodelib('params-error','Missing X-EXTRA-INFO header'));
        //endregion

        //region LOGOUT $token
        if(!$this->core->user->logoutERPToken($token,isset($this->formParams['_delete_all_tokens'])))  return $this->setErrorFromCodelib('security-error',$this->core->user->errorMsg);
        //endregion

        //region RETURN user Data in json format by default
        $this->addReturnData(
            [
                '$this->core->user->isAuth()'=>$this->core->user->isAuth(),
                '_delete_all_tokens'=>isset($this->formParams['_delete_all_tokens'])
            ]);
    }

}