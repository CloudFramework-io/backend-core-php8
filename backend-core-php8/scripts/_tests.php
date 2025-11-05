<?php

/**
 * https://cloudframework.io
 * Script Template
 */
class Script extends Scripts2020
{
    /**
     * This function is executed as the main method of the class
     */
    function main()
    {
        // We take parameter 1 to stablish the method to call when you execute: composer script hello/parameter-1/parameter-2/..
        // If the parameter 1 is empty we assign default by default :)
        $method = (isset($this->params[1])) ? $this->params[1] : 'default';
        // we convert - by _ because a method name does not allow '-' symbol
        $method = str_replace('-', '_', $method);

        //Call internal ENDPOINT_{$method}
        if (!$this->useFunction('METHOD_' . $method)) {
            return ($this->setErrorFromCodelib('params-error', "/{$method} is not implemented"));
        }
    }

    /**
     * Show default tests
     */
    function METHOD_default()
    {
        $this->sendTerminal("Executing {$this->params[0]}/default");
        $this->sendTerminal(' - $this->sendTerminal("hello"); -> '."hello");
        $this->sendTerminal(' - $this->core->system->root_path -> '.$this->core->system->root_path);
        $this->sendTerminal(' - $this->core->config->get(\'core.scripts.path\')-> '.str_replace($this->core->system->root_path,'.',$this->core->config->get('core.scripts.path')));
        $this->sendTerminal(' - $this->core->system->script_path -> '.str_replace($this->core->system->root_path,'.',$this->core->system->script_path));
        $this->sendTerminal(' - $this->core->request->defaultServiceUrl(\'/_version\') ->' . $this->core->request->defaultServiceUrl());
        $this->sendTerminal(' - $this->core->request->get(\'/_version\') ->'."\n----");
        $ret = $this->core->request->get('/_version');
        $this->sendTerminal($ret."\n----");
        /*
        $cfscript = shell_exec('alias cfscript');
        if(!$cfscript) $this->sendTerminal(" - Missing alias 'cfscript'. Add in your ./zshrc the following line: alias cfscript='composer run-script script'");
        elseif($cfscript!="cfscript='composer run-script script'") $this->sendTerminal(" - Alias 'cfscript' has a wrong value. Change in your ./zshrc the following line: alias cfscript='composer run-script script'");
        else $this->sendTerminal(" - Alias 'cfscript' found");
        */

    }

}
