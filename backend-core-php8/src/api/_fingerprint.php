<?php
class API extends RESTful
{

    function main()
    {
        $this->addReturnData($this->core->system->getRequestFingerPrint('geodata'));
    }
}
