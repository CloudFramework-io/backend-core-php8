<?php
/** @var Core7 $this */

// Check if core.api.extra_path is defined
if(!$this->config->get('core.api.extra_path')) return ($this->errors->add('{core.api.extra_path} is not defined'));

// SET $endpoint assuming $this->system->url['parts'][1] is the end-point
$endpoint = (array_key_exists(1,$this->system->url['parts']))?$this->system->url['parts'][1]:'';
if(!$endpoint) return($this->errors->add(['API requires and end end-point. Use: /_eval/{end-point}']));

// SET $file_path && $file_content
$file_path = $this->config->get('core.api.extra_path') . "/{$endpoint}.php";
$file_content='';
if(isset($_GET['_reset_eval_cache']) || !($file_content = $this->cache->get($file_path))) {
    if(!is_file($file_path)) {
        if($file_content) $this->cache->delete($file_path);
        return ($this->errors->add('{core.api.extra_path}' . "/{$endpoint} does exist"));
    }
    $file_content = file_get_contents($file_path);
    $this->cache->set($file_path,$file_content);
}

// Execute the php content
eval('?>'.$file_content);
