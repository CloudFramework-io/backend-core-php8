<?php
class API extends RESTful
{
    function main()
    {

        // Check
        if (!$this->checkMethod('GET,POST')) return;

        //Call internal ENDPOINT_{$this->params[2]}
        $end_point = str_replace('-', '_', isset($this->params[0]) ? $this->params[0] : 'default');
        if (!$this->useFunction('ENDPOINT_' . $end_point)) {
            return ($this->setErrorFromCodelib('params-error', "/{$end_point} is not implemented"));
        }

    }

    /*
     * Default concept
     */
    public function ENDPOINT_default() {

    }
}
