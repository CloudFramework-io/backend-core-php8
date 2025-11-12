<?php
/**
 * CloudFrameworkTests
 * last-update: 2020-10-10
 * https://www.notion.so/cloudframework/Designing-API-Tests-from-CloudFramework-afc8d166610f4b8e98742b98c504053f
 */

//region SET $rootPath and Autload libraries
$rootPath = exec('pwd');
require_once  $rootPath.'/vendor/autoload.php';
//endregion

//region CHECK local_data directory or create it
if(!is_dir($rootPath.'/local_data')) {
    @mkdir($rootPath.'/local_data');
    if(!is_dir($rootPath.'/local_data')) die('I cannot create ./local_data'."\n\n");
}
if(!is_dir($rootPath.'/local_data/cache')) {
    @mkdir($rootPath.'/local_data/cache');
    if(!is_dir($rootPath.'/local_data/cache')) die('I cannot create ./local_data/cache'."\n\n");
}
//endregion

//region SET $core Core7 object
include_once __DIR__.'/src/Core7.php';
$core = new Core7($rootPath);
//endregion

//region SET $script & $formParams

$script = [];
$path='';
$formParams = '';
if(count($argv)>1) {
    if(strpos($argv[1],'?'))
        list($script, $formParams) = explode('?', str_replace('..', '', $argv[1]), 2);
    else {
        $script = $argv[1];
        $formParams = '';
    }
    $script = explode('/', $script);
}
//endregion

echo "CloudFramworkTest v202010\nroot_path: {$rootPath}\n";
echo "------------------------------\n";

// Evaluate if you have access to source script
if((isset($argv[1]) && $argv[1]=='update') || (!is_file($rootPath.'/local_data/test.cf') && !is_file($rootPath.'/buckets/cloudframework.io/test_script.php'))) {
    echo "Downloading CloudFrameworkTest last version\n";
    $file =$core->request->get('https://api.cloudframework.io/core/tests/_download');
    if($core->request->error) {
        die("\nERROR downloading file\n".$file."\n\n");
    }
    file_put_contents($rootPath.'/local_data/test.cf',$file);
    if(!is_file($rootPath.'/local_data/test.cf')) die("\nERROR writting file\n\n");
    die('Last version downloaded'."\n\n");
}

$script_file = (is_file($rootPath.'/local_data/test.cf'))?'/local_data/test.cf':'/buckets/cloudframework.io/test_script.php';
echo "Using {$script_file}\n";
include_once $rootPath.$script_file;
if(!class_exists('Script')) die('The script does not include a "Class Script'."\nUse:\n-------\n<?php\nclass Script extends Scripts {\n\tfunction main() { }\n}\n-------\n\n");
/** @var Script $script */

$run = new Script($core,$argv);
$run->params = $script;

if(strlen($formParams))
    parse_str($formParams,$run->formParams);

if(!method_exists($run,'main')) die('The class Script does not include the method "main()'."\n\n");

try {
    $run->main();
} catch (Exception $e) {
    $run->sendTerminal(error_get_last());
    $run->sendTerminal($e->getMessage());
}
echo "------------------------------\n";
if($core->errors->data) {
    $run->sendTerminal('Test: ERROR');
}
else $run->sendTerminal('Test: OK');
