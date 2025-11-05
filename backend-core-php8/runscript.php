<?php
$time = microtime(true);
//region SET $root_path
$rootPath = exec('pwd');
// Autoload libraries
require_once  $rootPath.'/vendor/autoload.php';
//endregion

//region CREATE $core
include_once __DIR__.'/src/Core7.php';
$core = new Core7($rootPath);
echo "CloudFramWork (CFW) Core7.Script ".$core->_version."\n";
//endregion

//region IF core.erp.platform_id READ user email/id account
if($core->config->get('core.erp.platform_id')) {
    echo " - ERP Integration [on] because 'core.erp.platform_id' config-var exist: [".$core->config->get('core.erp.platform_id')."]\n";
    $config_erp_user_tag = 'core.erp.user_id.' . $core->config->get('core.erp.platform_id');

    if (!$core->config->get($config_erp_user_tag)) {
        if (!($user = $core->cache->get($config_erp_user_tag))) {
            echo ' - Because it is not in cache, execute $core->security->getGoogleEmailAccount() to get the default GCP user' . "\n";
            $user = $core->security->getGoogleEmailAccount();
            if ($user) {
                $core->cache->set($config_erp_user_tag, $user);
            }
        }
        if (!$user) {
            echo "You have configured core.erp.platform_id=" . $core->config->get('core.erp.platform_id') . "\n";
            echo "but you do not have an ACTIVE gcloud auth user. Execute 'gcloud auth login' to activate an account to be used as ERP user access\n";
            echo "-----\n\n";
            exit;
        }
        $core->config->set($config_erp_user_tag, $user);
        echo " - ERP user dynamically loaded in '{$config_erp_user_tag}' config-var assigned [{$user}]\n";
    } else {
        echo " - ERP user defined in '{$config_erp_user_tag}' config-var [".$core->config->get($config_erp_user_tag)."]\n";
    }
}
//endregion

//region EVALUATE GOOGLE_APPLICATION_CREDENTIALS env_var
if(!getenv('GOOGLE_APPLICATION_CREDENTIALS') && is_file($core->system->root_path.'/local_data/application_default_credentials.json'))
    putenv("GOOGLE_APPLICATION_CREDENTIALS={$core->system->root_path}/local_data/application_default_credentials.json");
//endregion

//region SET GLOBAL $datastore if $core->config->get('core.datastore.on') || $core->config->get('core.gcp.datastore.on')
// Load DataStoreClient to optimize calls
use Google\Cloud\Datastore\DatastoreClient;
$datastore = null;
$project_id = $core->config->get("core.gcp.datastore.project_id")?:($core->config->get("core.gcp.project_id")?:getenv('PROJECT_ID'));
if($project_id && ($core->config->get('core.datastore.on') || $core->config->get('core.gcp.datastore.on'))) {
    $transport = ($core->config->get('core.datastore.transport')=='grpc')?'grpc':'rest';
    $datastore = new DatastoreClient(['transport'=>$transport,'projectId'=>$project_id]);
}
//endregion

//region SET $script, $script_name, $path, $show_path
$script = [];
$path='';
if(count($argv)>1) {
    // allow to specify the endpoint with a space
    if(isset($argv[2]) && strpos($argv[2],'-')!==0 && strpos($argv[1],'/')===false && strpos($argv[1],'?')===false) $argv[1].='/'.$argv[2];

    if(strpos($argv[1],'?'))
        list($script, $formParams) = explode('?', str_replace('..', '', $argv[1]), 2);
    else {
        $script = $argv[1];
        $formParams = '';
    }
    $script = explode('/', $script);
    $script_name = $script[0];
    if($script_name[0]=='_') {
        echo " - Looking for scripts of the framework because the name start with [_]\n";
        $path = __DIR__.'/scripts';
    } else {
        $path =($core->config->get('core.scripts.path')?$core->config->get('core.scripts.path'):$core->system->app_path.'/scripts');
    }
    if(is_dir($path.'/'.$script_name)) {
        $path.="/{$script[0]}";
        $script_name =(isset($script[1]))?$script[1]:null;
    }
}
$show_path = str_replace($rootPath,'.',$path);
//endregion

//region CHECK if $script_name is empty
echo " - root_path: {$rootPath}\n - app_path: {$show_path}".(($core->config->get('core.scripts.path'))?" set in config var: 'core.scripts.path'":'')."\n";
if(!isset($script_name) || !$script_name) die (' - !!! Missing Script name: Use php vendor/cloudframework-io/appengine-php-core/runscript.php {script_name}[/params[?formParams]] [--options]'."\n\n");
echo " - script: {$show_path}/{$script_name}.php\n";
echo " - Initial Logs:\n";
echo "   #".implode("\n   #",$core->logs->data)."\n";
$core->logs->reset();
//endregion


echo "\n##########################\n";

//region SET $options,
$options = ['performance'=>in_array('--p',$argv)];
//endregion

//region VERIFY if the script exist
if(!is_file($path.'/'.$script_name.'.php')) die(" - !!!Script not found. Create it with: composer script _create/<your-script-name>\n");
include_once $path.'/'.$script_name.'.php';
if(!class_exists('Script')) die(' - !!!The script does not include a "Class Script'."\nUse:\n-------\n<?php\nclass Script extends Scripts2020 {\n\tfunction main() { }\n}\n-------\n\n");
/** @var Script $script */
//endregion

//region SET $run = new Script($core,$argv); and verify if the method main exist
$run = new Script($core,$argv);
$run->params = $script;
if(strlen($formParams))
    parse_str($formParams,$run->formParams);

if(!method_exists($run,'main')) die(' - !!!The class Script does not include the method "main()'."\n\n");
//endregion

//region TRY $run->main();
try {
    if(!isset($run->formParams['__p'])) $core->__p->active = false;
    $core->__p->add('Running Script',$show_path.'/'.$script_name,"note");
    $run->main();
    $core->__p->add('Running Script','',"endnote");

} catch (Exception $e) {

    $run->addError(error_get_last());
    $run->addError($e->getMessage());
}
echo "\n##########################\n";
//endregion

//region EVALUATE to show logs and errors and end the scrpit

if($core->logs->lines && ($core->errors->lines || isset($run->formParams['__logs']))) {
    $run->sendTerminal("\n----------- LOGS -------------");
    $run->sendTerminal($core->logs->data);
    $run->sendTerminal("-----------/LOGS -------------");

}
if($options['performance'] || isset($run->formParams['__p'])) {
    $run->sendTerminal("\n-------- PERFORMANCE ---------");
    $run->sendTerminal($core->__p->data['info']);
    $run->sendTerminal("--------/PERFORMANCE ---------");
}
if($core->errors->lines) {
    $run->sendTerminal("\n----------- ERRORS -------------");
    echo "ERRORS:\n";
    foreach ($core->errors->data as $datum) {
        echo " - ".json_encode($datum)."\n";

    }
    $run->sendTerminal("\n-----------/ERRORS -------------");
    $run->sendTerminal('Script: Error');
}
else $run->sendTerminal('Script: OK');
echo 'SCRIPT execution time: '.round(microtime(true)-$time,4).'secs'."\n";
//endregion
