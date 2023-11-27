<?php
class API extends RESTful
{
    function main()
    {
        $this->checkMethod('POST');
        $this->checkMandatoryFormParam('user');
        $this->checkMandatoryFormParam('password');
        if(!$this->error) {
            $data = ['info'=>'It generate the enconding to send in Authorization for Basic Authentication'];
            $data['Autorization'] = ['user'=>$this->formParams['user']
                ,'password'=>$this->formParams['password']
                ,'Authorization'=>'Basic '.base64_encode($this->formParams['user'].':'.$this->formParams['password'])];

        }
        $this->addReturnData($data);
    }
}
