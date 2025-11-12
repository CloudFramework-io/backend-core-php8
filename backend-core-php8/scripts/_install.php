<?php
/**
 * https://cloudframework.io
 * Script to install basic structure
 */
class Script extends Scripts2020
{
    /**
     * This function is executed as the main method of the class
     */
    function main()
    {
        if(!($script = strtolower($this->getParams(1)))) return($this->addError('Missing script to create. Use _create/{script_name}'));
        $show_script_path = str_replace($this->core->system->root_path,".",$this->core->system->script_path);
        if(!is_dir($this->core->system->script_path)) return($this->addError('script_path does not exist. Execute: '.$show_script_path));
        if(is_file($this->core->system->script_path.'/'.$script.'.php')) return($this->addError("script already exist:{$show_script_path}/{$script}.php"));
        if(preg_match('/[^a-z0-9_\-]/',$script)) return($this->addError("{$script} name is incorrect. Only is allowed the following chars: [a-z0-9_\-]"));
        $this->sendTerminal("  - Copying template to {$show_script_path}/{$script}.php");
        copy(__DIR__ . '/../scripts-dist/hello.php',$this->core->system->script_path."/{$script}.php");
        $this->sendTerminal("  - Everything is ok :)");
        $this->sendTerminal("  - Execute: cfscript {$script}/hello");
    }
}