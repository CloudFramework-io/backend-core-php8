<?php
// CLOUD Documentum:
//   DevGroup: /cf/products/cloud-development
//   DevUnit: /cloud-development/frameworks/php
//   Activities: 4822239115739136
/**
 * Isolated tests of the debug output of an API response, since 8.5.26: the log lines (logs), the trace of the profiler
 * (?__p) and the echo of the request (?__debug) are returned only outside a managed runtime or to an IP of
 * core.api.debug.allowed_ips compared exactly with the IP that the platform gives (X-Appengine-User-Ip in App Engine),
 * never with X-Forwarded-For, with no exception for cron calls or /queue/ URLs (ASVS 4.0.3 4.1.4).
 *
 * Self-contained: no external network, no Datastore, no Redis, no vendor/ autoload and no config.json of an application.
 * Every value is synthetic (SYNTH_*) and every IP is from the documentation ranges (RFC 5737, RFC 3849).
 *
 * Run from the repository root:
 *   php tests/RESTfulDebugOutput.php
 * Point it to another src/ (e.g. an older version, to see the suite fail) with:
 *   CF_TEST_SRC=/path/to/src php tests/RESTfulDebugOutput.php
 * Exit code 0 when every check passes, 1 otherwise.
 */

//region ENVIRONMENT: clean env vars and a temporary root
foreach (['CF_ENCRYPT_KEY','CF_ENCRYPT_SECRET','CF_CACHE_ENCRYPT_KEY','CF_CACHE_ENCRYPT_IV','PROJECT_ID','REDIS_HOST','REDIS_PORT','GAE_SERVICE','K_SERVICE','FUNCTION_TARGET'] as $var) putenv($var);
unset($_SERVER['GAE_SERVICE'], $_SERVER['K_SERVICE'], $_SERVER['FUNCTION_TARGET'], $_SERVER['HTTP_REFERER'], $_SERVER['HTTP_X_APPENGINE_CRON'],
    $_SERVER['HTTP_X_APPENGINE_USER_IP'], $_SERVER['HTTP_X_FORWARDED_FOR'], $_GET['__p']);

$tmp_root = sys_get_temp_dir().'/cf-core-restful-debug-test-'.getmypid();
@mkdir($tmp_root.'/local_data/cache',0700,true);
file_put_contents($tmp_root.'/config.json','{}'); // app config.json included by src/config.json
ini_set('session.save_path',$tmp_root);
ob_start(); // Core7 starts a PHP session in terminal mode: no output may be sent before it

$src = rtrim(getenv('CF_TEST_SRC') ?: __DIR__.'/../src', '/');
include_once $src.'/Core7.php';
include_once $src.'/class/RESTful.php';
//endregion

//region HELPERS
$checks = 0; $failures = 0;
function check(string $name, bool $ok, string $detail = ''): void {
    global $checks, $failures;
    $checks++;
    if(!$ok) $failures++;
    echo ($ok?'  ok   ':'  FAIL ').$name.(($detail && !$ok)?"\n         ".$detail:'')."\n";
}
function section(string $title): void { echo "\n".$title."\n"; }
/** A method of CoreSecurity, or 'missing' when the src under test does not have it (an older version) */
function sec(string $method) {
    global $core;
    return method_exists($core->security, $method) ? $core->security->$method() : 'missing';
}

$core = new Core7($tmp_root);
ob_end_clean();
$core->system->url['url'] = '/synth/endpoint';
$core->system->user_agent = 'SYNTH_USER_AGENT'; // a terminal has none, and ?__debug reads it

/**
 * Sets the runtime and the request of the next call.
 * @param string $runtime 'local' | 'gae' | 'cloudrun'
 * @param array $server HTTP_* values for $_SERVER
 * @param array $config config-vars of the debug IPs (the three names are reset to null first)
 */
function scenario(string $runtime, array $server = [], array $config = []): void {
    global $core;
    unset($_SERVER['GAE_SERVICE'], $_SERVER['K_SERVICE'], $_SERVER['FUNCTION_TARGET'], $_SERVER['HTTP_X_APPENGINE_CRON'],
        $_SERVER['HTTP_X_APPENGINE_USER_IP'], $_SERVER['HTTP_X_FORWARDED_FOR'], $_SERVER['REMOTE_ADDR']);
    if($runtime === 'gae') $_SERVER['GAE_SERVICE'] = 'synth-service';
    if($runtime === 'cloudrun') $_SERVER['K_SERVICE'] = 'synth-service';
    foreach ($server as $key => $value) $_SERVER[$key] = $value;
    foreach (['core.api.debug.allowed_ips','restful.logs.allowed_ips','core_api_logs_allowed_ips'] as $var) $core->config->set($var, null);
    foreach ($config as $var => $value) $core->config->set($var, $value);
    $core->system->url['url'] = '/synth/endpoint';
}

/** send() of a RESTful with one log line, asking for ?__p and ?__debug; returns the decoded response */
function call(array $form = ['__p'=>1,'__debug'=>1]): array {
    global $core;
    $core->errors->lines = 0;
    $core->errors->data = [];
    $core->logs->lines = 0;
    $core->logs->data = [];
    $core->logs->add('SYNTH_LOG_LINE_1');
    $api = new RESTful($core);
    $api->formParams = $form;
    $api->addReturnData(['value'=>'SYNTH_DATA_2']);
    return json_decode((string)$api->send(false, true), true) ?: [];
}
function no_debug(array $r): bool {
    return !array_key_exists('logs',$r) && !array_key_exists('__p',$r) && !array_key_exists('__debug',$r)
        && !str_contains(json_encode($r), 'SYNTH_LOG_LINE_1');
}
function all_debug(array $r): bool {
    return array_key_exists('logs',$r) && array_key_exists('__p',$r) && array_key_exists('__debug',$r)
        && str_contains(json_encode($r['logs']), 'SYNTH_LOG_LINE_1');
}
//endregion

//region a) local
section('a) local server or local script');
scenario('local');
$r = call();
check('the logs, __p and __debug are returned', all_debug($r), substr(json_encode($r),0,300));
check('the data is returned', ($r['data']['value'] ?? null) === 'SYNTH_DATA_2' || str_contains(json_encode($r),'SYNTH_DATA_2'));
check('debugOutputAllowed() is true', sec('debugOutputAllowed') === true);
$r = call([]);
check('without ?__p nor ?__debug only the logs are added', array_key_exists('logs',$r) && !array_key_exists('__p',$r) && !array_key_exists('__debug',$r));
//endregion

//region b) App Engine without configured IPs
section('b) App Engine, no IP configured');
scenario('gae', ['HTTP_X_APPENGINE_USER_IP'=>'198.51.100.7']);
$r = call();
check('no logs, no __p, no __debug', no_debug($r), substr(json_encode($r),0,300));
check('the data is still returned', str_contains(json_encode($r),'SYNTH_DATA_2'));
check('no message with the IP of the caller', !str_contains(json_encode($r),'198.51.100.7') && !str_contains(json_encode($r),'allowed_ips'));
check('the default of src/config.json (localhost,127.0.0.1) opens nothing', sec('debugOutputAllowed') === false);
scenario('gae', ['HTTP_X_FORWARDED_FOR'=>'127.0.0.1'], ['restful.logs.allowed_ips'=>'localhost,127.0.0.1']);
check('X-Forwarded-For 127.0.0.1 does not open it', no_debug(call()));
//endregion

//region c) App Engine with configured IPs
section('c) App Engine, core.api.debug.allowed_ips');
$allowed = ['core.api.debug.allowed_ips'=>['203.0.113.10','2001:db8::10']];
scenario('gae', ['HTTP_X_APPENGINE_USER_IP'=>'203.0.113.10'], $allowed);
$r = call();
check('an allowed IP in X-Appengine-User-Ip receives everything', all_debug($r), substr(json_encode($r),0,300));
check('trustedClientIP() is X-Appengine-User-Ip', sec('trustedClientIP') === '203.0.113.10');
scenario('gae', ['HTTP_X_APPENGINE_USER_IP'=>'198.51.100.7','HTTP_X_FORWARDED_FOR'=>'203.0.113.10'], $allowed);
check('an allowed IP only in X-Forwarded-For receives nothing', no_debug(call()));
scenario('gae', ['HTTP_X_FORWARDED_FOR'=>'203.0.113.10'], $allowed);
check('without X-Appengine-User-Ip it receives nothing', no_debug(call()));
check('trustedClientIP() is empty then', sec('trustedClientIP') === '');
scenario('gae', ['HTTP_X_APPENGINE_USER_IP'=>'203.0.113.1'], $allowed);
check('a prefix of an allowed IP (203.0.113.1) receives nothing', no_debug(call()));
scenario('gae', ['HTTP_X_APPENGINE_USER_IP'=>'203.0.113.100'], $allowed);
check('an allowed IP as prefix of the caller (203.0.113.100) receives nothing', no_debug(call()));
scenario('gae', ['HTTP_X_APPENGINE_USER_IP'=>'2001:0db8:0:0:0:0:0:10'], $allowed);
check('an IPv6 written in another form is the same IP', all_debug(call()));
scenario('gae', ['HTTP_X_APPENGINE_USER_IP'=>'SYNTH not an ip'], ['core.api.debug.allowed_ips'=>'SYNTH not an ip']);
check('a value that is not an IP opens nothing', no_debug(call()));
scenario('gae', ['HTTP_X_APPENGINE_USER_IP'=>' 203.0.113.10 '], ['core.api.debug.allowed_ips'=>' 198.51.100.7 , 203.0.113.10 ']);
check('a comma separated string with spaces is read', all_debug(call()));
//endregion

//region d) legacy names and wildcards
section('d) legacy config-vars and wildcards');
scenario('gae', ['HTTP_X_APPENGINE_USER_IP'=>'203.0.113.10'], ['restful.logs.allowed_ips'=>'203.0.113.10']);
check('restful.logs.allowed_ips is still read', all_debug(call()));
scenario('gae', ['HTTP_X_APPENGINE_USER_IP'=>'203.0.113.10'], ['core_api_logs_allowed_ips'=>'203.0.113.10']);
check('core_api_logs_allowed_ips is still read', all_debug(call()));
scenario('gae', ['HTTP_X_APPENGINE_USER_IP'=>'198.51.100.7'], ['restful.logs.allowed_ips'=>'*']);
check('* opens nothing', no_debug(call()));
scenario('gae', ['HTTP_X_APPENGINE_USER_IP'=>'198.51.100.7'], ['core.api.debug.allowed_ips'=>['203.0.113.0/24']]);
check('a range opens nothing', no_debug(call()));
check('debugAllowedIPs() keeps only valid IPs', (function() use ($core) {
    scenario('gae', [], ['core.api.debug.allowed_ips'=>['*','localhost','203.0.113.0/24','203.0.113.10'], 'restful.logs.allowed_ips'=>'203.0.113.10,198.51.100.7']);
    return sec('debugAllowedIPs') === ['203.0.113.10','198.51.100.7'];
})());
//endregion

//region e) cron and /queue/ are not exceptions
section('e) cron calls and /queue/ URLs');
scenario('gae', ['HTTP_X_APPENGINE_USER_IP'=>'198.51.100.7','HTTP_X_APPENGINE_CRON'=>'true'], ['restful.logs.allowed_ips'=>'203.0.113.10']);
check('a cron call receives nothing', no_debug(call()));
foreach (['/queue/synth', '/synth/endpoint/queue/synth'] as $url) {
    scenario('gae', ['HTTP_X_APPENGINE_USER_IP'=>'198.51.100.7'], ['restful.logs.allowed_ips'=>'203.0.113.10']);
    $core->system->url['url'] = $url;
    check("{$url} receives nothing", no_debug(call()));
}
//endregion

//region f) other managed runtimes
section('f) Cloud Run and Cloud Functions');
scenario('cloudrun', ['HTTP_X_APPENGINE_USER_IP'=>'203.0.113.10','REMOTE_ADDR'=>'203.0.113.10','HTTP_X_FORWARDED_FOR'=>'203.0.113.10'], $allowed);
check('Cloud Run receives nothing even with an allowed IP in every header', no_debug(call()));
check('trustedClientIP() is empty in Cloud Run', sec('trustedClientIP') === '');
scenario('local', [], $allowed);
putenv('FUNCTION_TARGET=synth');
$_SERVER['HTTP_X_APPENGINE_USER_IP'] = '203.0.113.10';
check('a managed runtime declared only in getenv() also counts', no_debug(call()) && (method_exists('CoreSecurity','managedRuntime') && CoreSecurity::managedRuntime()));
putenv('FUNCTION_TARGET');
//endregion

//region g) ?__p=N of the profiler
section('g) ?__p=N dump of the profiler');
scenario('gae', ['HTTP_X_APPENGINE_USER_IP'=>'203.0.113.10'], $allowed);
$_GET['__p'] = (string)$core->__p->data['lastIndex'];
$core->__p->add('SYNTH_PROFILER_LINE', '', 'note');
check('in a managed runtime it does not stop the request (the test is still running)', true);
unset($_GET['__p']);
check('the line was added', in_array('SYNTH_PROFILER_LINE', array_map('strval', $core->__p->data['info'] ?? []), true)
    || str_contains(json_encode($core->__p->data['info'] ?? []), 'SYNTH_PROFILER_LINE'));
//endregion

echo "\n".($failures ? "FAILED: {$failures} of {$checks} checks" : "PASSED: {$checks} checks")."\n";
exec('rm -rf '.escapeshellarg($tmp_root));
exit($failures ? 1 : 0);
