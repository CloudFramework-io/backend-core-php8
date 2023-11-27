<?php
//region SET $_root_path and autolad
$_root_path = (strlen($_SERVER['DOCUMENT_ROOT'])) ? $_SERVER['DOCUMENT_ROOT'] : $_SERVER['PWD'];
// Autoload libraries
require_once  $_root_path.'/vendor/autoload.php';
//endregion

//region INIT $core = new Core7();
include_once(__DIR__ . "/Core7.php"); //
$core = new Core7();
//endregion

//region SET $logger
// https://cloud.google.com/logging/docs/setup/php
use Google\Cloud\Logging\LoggingClient;
$logger = null;
if(getenv('PROJECT_ID') && $core->is->production()) {
    $logger = LoggingClient::psrBatchLogger('app');
}
//endregion

//region IF core.erp.platform_id READ user for service account.
if($core->config->get('core.erp.platform_id') && !$core->config->get('core.erp.user_id.'.$core->config->get('core.erp.platform_id'))) {
    $config_erp_user_tag = 'core.erp.user_id.'.$core->config->get('core.erp.platform_id');
    if(!($user = $core->cache->get($config_erp_user_tag))) {
        $core->logs->add('Because it is not in cache, execute $core->security->getGoogleEmailAccount() to get the default GCP user','CloudFrameWork.ERP');
        $user = $core->security->getGoogleEmailAccount();
        if($user) {
            $core->cache->set($config_erp_user_tag,$user);
        }
    }
    if($core->is->development() && !$user) {
        echo "You have configured core.erp.platform_id=".$core->config->get('core.erp.platform_id')."\n";
        echo "but you do not have an ACTIVE gcloud auth user. Execute 'gcloud auth login' to activate an account to be used as ERP user access\n";
        echo "-----\n\n";
        exit;
    }
    $core->config->set($config_erp_user_tag,$user);
    $core->logs->add("'{$config_erp_user_tag}' config var assigned for ERP user to [{$user}]",'CloudFrameWork.ERP');

}
//endregion

//region EVALUATE GOOGLE_APPLICATION_CREDENTIALS env_var
if($core->is->development() && !getenv('GOOGLE_APPLICATION_CREDENTIALS') && is_file($core->system->root_path.'/local_data/application_default_credentials.json'))
    putenv("GOOGLE_APPLICATION_CREDENTIALS={$core->system->root_path}/local_data/application_default_credentials.json");
//endregion

//region SET $datastore if $core->config->get('core.datastore.on') || $core->config->get('core.gcp.datastore.on')
// Load DataStoreClient to optimize calls
use Google\Cloud\Datastore\DatastoreClient;
$datastore = null;
$project_id = $core->config->get("core.gcp.datastore.project_id")?:($core->config->get("core.gcp.project_id")?:getenv('PROJECT_ID'));
if($project_id && ($core->config->get('core.datastore.on') || $core->config->get('core.gcp.datastore.on'))) {

    //2021-02-25: Fix to force rest transport instead of grpc because it crash for certain content.
    if(isset($_GET['_fix_datastore_transport'])) $core->config->set('core.datastore.transport','rest');
    $transport = ($core->config->get('core.datastore.transport')=='grpc')?'grpc':'rest';
    $datastore = new DatastoreClient(['transport'=>$transport,'projectId'=>$project_id]);
}
//endregion

//region RUN $core->dispatch();
$core->dispatch();
//endregion

//region EVALUATE ?__p GET parameter when we are in a script
// Apply performance parameter
if (isset($_GET['__p'])) {
    _print($core->__p->data['info']);

    if ($core->errors->lines)
        _print($core->errors->data);

    if ($core->logs->lines)
        _print($core->logs->data);
}
//endregion
