<?php
/**
 * https://cloudframework.io
 * Script Template
 */
class Script extends Scripts2020
{

    var $script = '';   // value of the script to execute: training/hello
    var $method = '';   // value of the method in the script world
    /**
     * This function is executed as the main method of the class
     */
    function main()
    {


        // We take parameter 2 to stablish the method to call when you execute: composer script hello/parameter-1/parameter-2/..
        // If the parameter 2 is empty we assign default by default :)
        $this->script = "{$this->params[0]}/{$this->params[1]}";
        $this->method = (isset($this->params[2])) ? $this->params[2] : 'default';
        // we convert - by _ because a method name does not allow '-' symbol

        //Call internal ENDPOINT_{$method}
        if (!$this->useFunction('METHOD_' .  str_replace('-', '_', $this->method))) {
            return ($this->setErrorFromCodelib('params-error', "/{$this->script}/{$this->method} is not implemented"));
        }
    }

    /**
     * This method is called from the main method taking the parameters of command line: composer script hello
     */
    function METHOD_default()
    {
        $this->sendTerminal("Available methods (use {$this->script}/{method}):");
        $this->sendTerminal(" - {$this->script}/hello");
    }

    /**
     * This method is called from the main method taking the parameters of command line: composer script hello/test
     */
    function METHOD_world()
    {
        $this->sendTerminal('This is a Hello World :)');

    }
}