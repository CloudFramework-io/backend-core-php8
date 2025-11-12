<?php
class API extends RESTful
{
    function main()
    {
        $this->checkMethod('GET,POST');
        $this->checkMandatoryFormParam('s');
        if(!$this->error) {
            $data = ['info'=>'the {{crypt}} output is what you store in your config files avoinding to show the real info in plain text'];
            if(!isset($this->formParams['c'])) $this->formParams['c'] = $this->core->security->crypt($this->formParams['s']);
                $data['crypt'] = ['source'=>$this->formParams['s']
                    ,'crypt'=>$this->formParams['c']
                    ,'$this->core->security->checkPassword(source,crypt)'=>$this->core->security->checkCrypt($this->formParams['s'],$this->formParams['c'])];

        }
        $this->addReturnData($data);
    }
}