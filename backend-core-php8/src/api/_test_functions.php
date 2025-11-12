<?php
class API extends RESTful
{
    function main()
    {
        $this->sendCorsHeaders('GET,POST');
        $this->addReturnData([
            '$this->core->_version'=> $this->core->_version,
            '$this->core->is->development()'=> $this->core->is->development(),
            '$this->core->is->production()'=> $this->core->is->production(),
            '$this->core->config->get(\'core.api.routes\')'=> $this->core->config->get('core.api.routes'),
            '$this->core->cache->spacename'=> $this->core->cache->spacename,
            '$this->core->cache->set(\'var1\',\'value1\')'=> $this->core->cache->set('var1','value1'),

            '$this->>sendCorsHeaders("GET,POST")'=> $this->sendCorsHeaders("GET,POST")===false,
            '$this->getHeaders()'=> $this->getHeaders(),
            '$this->getHeader("X-DS-TOKEN")'=> $this->getHeader("X-DS-TOKEN"),
            '$this->getHeadersToResend()'=> $this->getHeadersToResend(),
            '$this->method'=> $this->method,
            '$this->checkMethod(\'GET,POST\')'=> $this->checkMethod('GET,POST'),
            '$this->params'=> $this->params,
            '$this->formParams'=> $this->formParams,
            '$this->updateReturnResponse([\'extra_response\'=>\'returnResponse\'])'=> $this->updateReturnResponse(['extra_php'=>'returnResponse']),

        ]);
    }
}
