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
        if(!($api = strtolower($this->getParams(1)))) return($this->addError('Missing script to create. Use _create_api/{script_name}'));
        $show_api_path = str_replace($this->core->system->root_path,".",$this->core->system->api_path);
        if(!is_dir($this->core->system->api_path)) return($this->addError('api_path does not exist. Execute: '.$show_api_path));
        if(is_file($this->core->system->api_path.'/'.$api.'.php')) return($this->addError("script already exist:{$show_api_path}/{$api}.php"));
        if(preg_match('/[^a-z0-9_\-]/',$api)) return($this->addError("{$api} name is incorrect. Only is allowed the following chars: [a-z0-9_\-]"));
        $this->sendTerminal("  - Copying template to {$show_api_path}/{$api}.php");
        copy(__DIR__ . '/../install/api-dist/template.php',$this->core->system->api_path."/{$api}.php");
        $this->sendTerminal("  - Everything is ok :)");
        $this->sendTerminal("  - execute: [composer serve]");
        $this->sendTerminal("  - Go to: http://localhost:8080/{$api}/default?var1=value1");
    }
}