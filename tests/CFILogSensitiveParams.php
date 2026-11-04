<?php
/**
 * Isolated tests of the masking of sensitive query parameters in the logs, since 8.5.16:
 * CoreSystem::maskSensitiveParamsInUrl() / getSensitiveParams() / maskUrl(), getRequestFingerPrint() and
 * CFILog::add() / bitacora() (Datastore replaced by an in-memory double that captures the entities).
 *
 * Self-contained: no network, no Datastore, no Redis, no vendor/ autoload and no config.json of an application.
 * Every code, state and token is synthetic (SYNTH_*).
 *
 * Run from the repository root:
 *   php tests/CFILogSensitiveParams.php
 * Point it to another src/ (e.g. an older version, to see the suite fail) with:
 *   CF_TEST_SRC=/path/to/src php tests/CFILogSensitiveParams.php
 * Exit code 0 when every check passes, 1 otherwise.
 */

//region ENVIRONMENT: clean env vars, temporary root
foreach (['CF_ENCRYPT_KEY','CF_ENCRYPT_SECRET','CF_CACHE_ENCRYPT_KEY','CF_CACHE_ENCRYPT_IV','PROJECT_ID','REDIS_HOST','REDIS_PORT','GOOGLE_APPLICATION_CREDENTIALS'] as $env_var) putenv($env_var);
unset($_SERVER['GAE_SERVICE'], $_SERVER['HTTP_REFERER']);
unset($_REQUEST['cloudframework_queued_fingerprint']);

$tmp_root = sys_get_temp_dir().'/cf-core-logmask-test-'.getmypid();
@mkdir($tmp_root.'/local_data/cache',0700,true);
file_put_contents($tmp_root.'/config.json','{}'); // app config.json included by src/config.json
ini_set('session.save_path',$tmp_root);
ob_start(); // Core7 starts a PHP session in terminal mode: no output may be sent before it

$php_warnings = [];
set_error_handler(function ($errno, $errstr, $errfile, $errline) use (&$php_warnings) {
    $php_warnings[] = "[$errno] $errstr ($errfile:$errline)";
    return true;
});

$src = rtrim(getenv('CF_TEST_SRC') ?: __DIR__.'/../src', '/');
include_once $src.'/Core7.php';
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
function flush_output(): void { if(ob_get_level()) { ob_end_flush(); } }

const DEFAULT_NAMES = ['code','state','token','access_token','id_token','password','key','secret'];
const SSO_PATH = '/erp/erp-users/1.0/test/google/sso';
const SSO_URI = SSO_PATH.'?code=SYNTH_CODE_123&state=SYNTH_STATE_456&scope=email';
const SSO_MASKED = SSO_PATH.'?code=***&state=***&scope=email';

$has_pure = method_exists('CoreSystem','maskSensitiveParamsInUrl');
/** Calls the pure function, or returns a sentinel when it does not exist in this src/ */
function mask(?string $url, ?array $names = null, ?string $mask = null) {
    global $has_pure;
    if(!$has_pure) return '<<maskSensitiveParamsInUrl() does not exist>>';
    $names = $names ?? DEFAULT_NAMES;
    return ($mask === null) ? CoreSystem::maskSensitiveParamsInUrl($url, $names) : CoreSystem::maskSensitiveParamsInUrl($url, $names, $mask);
}
function check_mask(string $name, ?string $url, ?string $expected, ?array $names = null): void {
    $got = mask($url, $names);
    check($name, $got === $expected, 'expected '.var_export($expected,true).' got '.var_export($got,true));
}
function new_core(string $root, array $config = []): Core7 {
    $core = new Core7($root);
    foreach ($config as $key=>$value) $core->config->set($key,$value);
    return $core;
}
/** In-memory replacement of the DataStore objects used by CFILog: captures every entity written */
class CapturingDataStore {
    var $error = false;
    var $errorMsg = null;
    var $captured = [];
    function createEntities($entity) { $this->captured[] = $entity; return [$entity + ['KeyId'=>'1']]; }
}
//endregion

$core = new_core($tmp_root);
flush_output();
echo "src: $src\n";

//region a) pure function CoreSystem::maskSensitiveParamsInUrl()
section('a) CoreSystem::maskSensitiveParamsInUrl() (pure function)');
check('CoreSystem::maskSensitiveParamsInUrl() exists', $has_pure);
check('CoreSystem::LOG_SENSITIVE_PARAMS holds the 8 default names', defined('CoreSystem::LOG_SENSITIVE_PARAMS') && CoreSystem::LOG_SENSITIVE_PARAMS === DEFAULT_NAMES);

check_mask('SSO callback: code and state masked, scope kept', SSO_URI, SSO_MASKED);
foreach (DEFAULT_NAMES as $i => $name)
    check_mask("default name '$name' is masked", "/p?$name=SYNTH_VALUE_$i&other=1", "/p?$name=***&other=1");
check_mask('upper/mixed case names (CODE, Access_Token)', '/p?CODE=SYNTH_A&Access_Token=SYNTH_B&x=1', '/p?CODE=***&Access_Token=***&x=1');
check_mask('url-encoded names (access%5Ftoken, id%5Ftoken) masked, names not re-encoded', '/p?access%5Ftoken=SYNTH_A&id%5Ftoken=SYNTH_B', '/p?access%5Ftoken=***&id%5Ftoken=***');
check_mask('array suffix (code[], code[0], code%5B%5D)', '/p?code[]=SYNTH_A&code[0]=SYNTH_B&code%5B%5D=SYNTH_C', '/p?code[]=***&code[0]=***&code%5B%5D=***');
check_mask("'.' and ' ' normalized to '_' (access.token, access+token, access%20token)", '/p?access.token=SYNTH_A&access+token=SYNTH_B&access%20token=SYNTH_C', '/p?access.token=***&access+token=***&access%20token=***');
check_mask('empty value (code=) is left as it is', '/p?code=&scope=email', '/p?code=&scope=email');
check_mask('parameter without = is left as it is', '/p?code&state=SYNTH_STATE_456', '/p?code&state=***');
check_mask('repeated parameter (code=a&code=b) masked every time', '/p?code=SYNTH_A&code=SYNTH_B', '/p?code=***&code=***');
check_mask('value containing = is masked entirely', '/p?code=SYNTH=A=B&x=1', '/p?code=***&x=1');
check_mask('fragment with query form after a query', '/cb?x=1#access_token=SYNTH_TOKEN&token_type=bearer', '/cb?x=1#access_token=***&token_type=bearer');
check_mask('fragment with query form, no query', '/cb#access_token=SYNTH_TOKEN&token_type=bearer', '/cb#access_token=***&token_type=bearer');
check_mask('hash route with query inside the fragment', '/app#/route?token=SYNTH_TOKEN&tab=2', '/app#/route?token=***&tab=2');
check_mask('plain fragment is left as it is', '/p?x=1#section-2', '/p?x=1#section-2');
check_mask('absolute URL', 'https://host.example'.SSO_URI, 'https://host.example'.SSO_MASKED);
check_mask('absolute URL without query', 'https://host.example/p/q', 'https://host.example/p/q');
check_mask('no query', SSO_PATH, SSO_PATH);
check_mask('trailing ? without parameters', SSO_PATH.'?', SSO_PATH.'?');
$got = mask(null); check('null returns null', $has_pure && $got === null);
check_mask("'' returns ''", '', '');
check_mask('names that contain but are not sensitive (postcode, keyword, statement, codes, my_code, tokens)',
    '/p?postcode=28001&keyword=abc&statement=xyz&codes=1&my_code=2&tokens=3', '/p?postcode=28001&keyword=abc&statement=xyz&codes=1&my_code=2&tokens=3');
check_mask('non-sensitive encoded values kept byte by byte', '/p?redirect=%2Fa%3Fb%3D1&code=SYNTH_CODE_123&next=a%20b+c%2B', '/p?redirect=%2Fa%3Fb%3D1&code=***&next=a%20b+c%2B');
check_mask('order and separators kept (&&, trailing &)', '/p?a=1&&code=SYNTH_A&b=2&', '/p?a=1&&code=***&b=2&');
check_mask('$names are normalized too ("CODE ")', '/p?code=SYNTH_A&state=SYNTH_B', '/p?code=***&state=SYNTH_B', ['CODE ']);
check_mask('empty $names masks nothing', SSO_URI, SSO_URI, []);
$got = mask('/p?code=SYNTH_A', ['code'], '[redacted]');
check('custom $mask', $got === '/p?code=[redacted]', var_export($got,true));
foreach ([SSO_URI, '/cb#access_token=SYNTH_TOKEN&token_type=bearer', '/p?code[]=SYNTH_A&access.token=SYNTH_B', 'https://host.example'.SSO_URI] as $url) {
    $once = mask($url); $twice = mask($once);
    check("idempotent: $url", $has_pure && $once === $twice, var_export([$once,$twice],true));
}
//endregion

//region b) configuration core.log.sensitive_params
section('b) getSensitiveParams() / maskUrl() and core.log.sensitive_params');
$has_cfg = method_exists($core->system,'getSensitiveParams') && method_exists($core->system,'maskUrl');
check('CoreSystem::getSensitiveParams() and maskUrl() exist', $has_cfg);
check('Core7 wires $core->system->core_config to $core->config', property_exists($core->system,'core_config') && $core->system->core_config === $core->config);
$params = $has_cfg ? $core->system->getSensitiveParams() : [];
check('without config: exactly the 8 defaults', $params === DEFAULT_NAMES, json_encode($params));
$m = fn(string $url) => $has_cfg ? $core->system->maskUrl($url) : '<<maskUrl() does not exist>>';
check('maskUrl() masks the SSO callback', $m(SSO_URI) === SSO_MASKED, $m(SSO_URI));

$core->config->set('core.log.sensitive_params', ['otp']);
$params = $has_cfg ? $core->system->getSensitiveParams() : [];
check("['otp'] adds otp and keeps the 8 defaults", $params === array_merge(DEFAULT_NAMES,['otp']), json_encode($params));
check("['otp']: otp masked, pin not, code still masked", $m('/p?otp=SYNTH_OTP&pin=1234&code=SYNTH_CODE_123') === '/p?otp=***&pin=1234&code=***', $m('/p?otp=SYNTH_OTP&pin=1234&code=SYNTH_CODE_123'));

$core->config->set('core.log.sensitive_params', 'otp, pin');
$params = $has_cfg ? $core->system->getSensitiveParams() : [];
check("'otp, pin' (comma-separated string) adds both", $params === array_merge(DEFAULT_NAMES,['otp','pin']), json_encode($params));
check("'otp, pin': both masked (read again at runtime)", $m('/p?otp=SYNTH_OTP&pin=SYNTH_PIN&x=1') === '/p?otp=***&pin=***&x=1', $m('/p?otp=SYNTH_OTP&pin=SYNTH_PIN&x=1'));

$core->config->set('core.log.sensitive_params', ['OTP ', 'otp', 'Code', 'session.id', '', 123, null]);
$params = $has_cfg ? $core->system->getSensitiveParams() : [];
check('names normalized, duplicates and non-strings dropped', $params === array_merge(DEFAULT_NAMES,['otp','session_id']), json_encode($params));

$core->config->set('core.log.sensitive_params', []);
$params = $has_cfg ? $core->system->getSensitiveParams() : [];
check('[] does not remove the 8 defaults', $params === DEFAULT_NAMES, json_encode($params));

$core->config->set('core.log.sensitive_params', ['otp','pin']);
check('a list without code does not stop masking code/state', $m(SSO_URI) === SSO_MASKED, $m(SSO_URI));
$core->config->set('core.log.sensitive_params', '');
check("'' (empty string): only the defaults", ($has_cfg ? $core->system->getSensitiveParams() : []) === DEFAULT_NAMES);
//endregion

//region c) getRequestFingerPrint()
section('c) getRequestFingerPrint()');
$_SERVER['HTTP_USER_AGENT'] = 'SYNTH-UA/1.0';
$_SERVER['HTTP_HOST'] = 'api.synth.example';
$_SERVER['REQUEST_URI'] = SSO_URI;
$_SERVER['HTTP_REFERER'] = 'https://accounts.synth.example/o/oauth2/auth?client_id=SYNTH_CLIENT&state=SYNTH_STATE_456&redirect_uri=%2Fsso';
$fp = $core->system->getRequestFingerPrint();
check('uri masked', ($fp['uri']??null) === SSO_MASKED, json_encode($fp['uri']??null));
check('http_referer masked, other referer parameters kept', ($fp['http_referer']??null) === 'https://accounts.synth.example/o/oauth2/auth?client_id=SYNTH_CLIENT&state=***&redirect_uri=%2Fsso', json_encode($fp['http_referer']??null));
$software = (isset($_SERVER['GAE_RUNTIME']))?('GAE_RUN_TIME:'.$_SERVER['GAE_RUNTIME'].'/'.$_SERVER['GAE_VERSION']):((isset($_SERVER['SERVER_SOFTWARE']))?$_SERVER['SERVER_SOFTWARE']:'Unknown');
check('hash = sha1(user_agent,host,software) as before', ($fp['hash']??null) === sha1(implode(',',['SYNTH-UA/1.0','api.synth.example',$software])));
$hash1 = $fp['hash']??null;
$_SERVER['REQUEST_URI'] = SSO_PATH.'?other=1';
unset($_SERVER['HTTP_REFERER']);
$fp2 = $core->system->getRequestFingerPrint();
check('hash identical with another query and no referer', $hash1 !== null && ($fp2['hash']??null) === $hash1);
check('uri without sensitive parameters unchanged', ($fp2['uri']??null) === SSO_PATH.'?other=1');
check("no referer: http_referer stays 'unknown'", ($fp2['http_referer']??null) === 'unknown');

$queued = ['user_agent'=>'SYNTH-UA/1.0','hash'=>'SYNTH_HASH','ip'=>'10.0.0.1','uri'=>SSO_URI,
    'http_referer'=>'https://synth.example/back?token=SYNTH_TOKEN&x=1',
    'local_fingerprint'=>['uri'=>'/core/logs?code=SYNTH_CODE_123','http_referer'=>'/r?state=SYNTH_STATE_456','hash'=>'SYNTH_HASH_2']];
$_REQUEST['cloudframework_queued_fingerprint'] = json_encode($queued);
$fpq = $core->system->getRequestFingerPrint();
unset($_REQUEST['cloudframework_queued_fingerprint']);
check('queued fingerprint: uri masked', ($fpq['uri']??null) === SSO_MASKED, json_encode($fpq['uri']??null));
check('queued fingerprint: http_referer masked', ($fpq['http_referer']??null) === 'https://synth.example/back?token=***&x=1', json_encode($fpq['http_referer']??null));
check('queued fingerprint: local_fingerprint uri/http_referer masked', ($fpq['local_fingerprint']['uri']??null) === '/core/logs?code=***' && ($fpq['local_fingerprint']['http_referer']??null) === '/r?state=***', json_encode($fpq['local_fingerprint']??null));
check('queued fingerprint: other keys untouched (hash, ip)', ($fpq['hash']??null) === 'SYNTH_HASH' && ($fpq['ip']??null) === '10.0.0.1' && ($fpq['local_fingerprint']['hash']??null) === 'SYNTH_HASH_2');
//endregion

//region d) CFILog::add() and bitacora()
section('d) CFILog::add() and bitacora() store masked URLs');
$_SERVER['REQUEST_URI'] = SSO_URI;
$_SERVER['HTTP_REFERER'] = 'https://accounts.synth.example/cb?code=SYNTH_CODE_123&state=SYNTH_STATE_456';
$ds_logs = new CapturingDataStore();
$ds_bitacora = new CapturingDataStore();
$core->cfiLog->dsLogs = $ds_logs;
$core->cfiLog->dsBitacora = $ds_bitacora;

$ret = $core->cfiLog->add('test-app','sso','SSO callback','GET','synth-user@synth.example');
check('add() without $rewrite_fingerprint returns the entity', is_array($ret) && ($ret['KeyId']??null) === '1', json_encode($core->cfiLog->errorMsg));
$e = $ds_logs->captured[0] ?? [];
check('add(): JSON.url masked', ($e['JSON']['url']??null) === SSO_MASKED, json_encode($e['JSON']['url']??null));
check('add(): JSON.http_referer masked', ($e['JSON']['http_referer']??null) === 'https://accounts.synth.example/cb?code=***&state=***', json_encode($e['JSON']['http_referer']??null));
check('add(): JSON.fingerprint.uri masked', ($e['JSON']['fingerprint']['uri']??null) === SSO_MASKED);

$caller_fp = ['ip'=>'10.0.0.2','uri'=>'/remote/sso?code=SYNTH_CODE_123&scope=email','http_referer'=>'https://remote.synth.example/?state=SYNTH_STATE_456'];
$ret = $core->cfiLog->add('test-app','sso','SSO callback (remote)','POST','synth-user@synth.example',['note'=>'payload'],null,$caller_fp);
check('add() with $rewrite_fingerprint returns the entity', is_array($ret));
$e = $ds_logs->captured[1] ?? [];
check('add(rewrite): fingerprint.uri of the caller masked', ($e['JSON']['fingerprint']['uri']??null) === '/remote/sso?code=***&scope=email', json_encode($e['JSON']['fingerprint']['uri']??null));
check('add(rewrite): JSON.http_referer (from the caller) masked', ($e['JSON']['http_referer']??null) === 'https://remote.synth.example/?state=***', json_encode($e['JSON']['http_referer']??null));
check('add(rewrite): fingerprint.local_fingerprint masked', ($e['JSON']['fingerprint']['local_fingerprint']['uri']??null) === SSO_MASKED);
check('add(rewrite): JSON.data untouched', ($e['JSON']['data']??null) === ['note'=>'payload']);
check('add(rewrite): Ip of the caller kept', ($e['Ip']??null) === '10.0.0.2');

$ret = $core->cfiLog->bitacora('synth-user@synth.example','accessed','test-solution','test-app','42');
check('bitacora() returns the KeyId', $ret === '1', var_export($ret,true));
$b = $ds_bitacora->captured[0] ?? [];
check('bitacora(): JSON.url masked', ($b['JSON']['url']??null) === SSO_MASKED, json_encode($b['JSON']['url']??null));
check('bitacora(): JSON.http_referer masked', ($b['JSON']['http_referer']??null) === 'https://accounts.synth.example/cb?code=***&state=***', json_encode($b['JSON']['http_referer']??null));

$all = json_encode([$ds_logs->captured, $ds_bitacora->captured], JSON_UNESCAPED_SLASHES);
check('nothing captured contains SYNTH_CODE_123', strpos($all,'SYNTH_CODE_123') === false);
check('nothing captured contains SYNTH_STATE_456', strpos($all,'SYNTH_STATE_456') === false);
check('the captured entities keep the path '.SSO_PATH, strpos($all,SSO_PATH) !== false);
check('the captured entities contain code=***', strpos($all,'code=***') !== false);
check('3 entities captured (2 logs + 1 bitacora)', count($ds_logs->captured) === 2 && count($ds_bitacora->captured) === 1);
//endregion

//region e) CoreRequest::sendLog() forwards a masked fingerprint (getLogFingerPrint())
// sendLog() itself can not run here: with CloudServiceLog set it dies on count($this->core->logs->lines) (an int, a
// TypeError in PHP 8, pre-existing at 8.5.15) and otherwise it returns before building $params. So the fingerprint it
// forwards is tested through getLogFingerPrint(), and its source is checked to use it. No network.
section('e) CoreRequest::sendLog() / getLogFingerPrint() forward a masked fingerprint');
$req = new CoreRequest($core);
$req->sendSysLogs = false;
$has_lfp = method_exists($req,'getLogFingerPrint');
check('CoreRequest::getLogFingerPrint() exists', $has_lfp);
$rm = new ReflectionMethod('CoreRequest','sendLog');
$send_log_src = implode('', array_slice(file($rm->getFileName()), $rm->getStartLine()-1, $rm->getEndLine()-$rm->getStartLine()+1));
check('sendLog() builds $params[\'fingerprint\'] with $this->getLogFingerPrint()', strpos($send_log_src, "\$params['fingerprint'] = \$this->getLogFingerPrint();") !== false);
check('sendLog() no longer forwards $_REQUEST[\'cloudframework_queued_fingerprint\'] directly', strpos($send_log_src, 'cloudframework_queued_fingerprint') === false);

// direct branch
unset($_REQUEST['cloudframework_queued_fingerprint']);
$_SERVER['REQUEST_URI'] = SSO_URI;
$_SERVER['HTTP_REFERER'] = 'https://accounts.synth.example/cb?code=SYNTH_CODE_123&state=SYNTH_STATE_456';
$lfp = $has_lfp ? $req->getLogFingerPrint() : null;
$dec = is_string($lfp) ? json_decode($lfp, true) : null;
check('direct: pretty JSON string with the masked uri and http_referer', is_array($dec) && ($dec['uri']??null) === SSO_MASKED && ($dec['http_referer']??null) === 'https://accounts.synth.example/cb?code=***&state=***' && strpos($lfp, "\n") !== false);
check('direct: no SYNTH_CODE_123 / SYNTH_STATE_456 in what sendLog() forwards', is_string($lfp) && strpos($lfp,'SYNTH_CODE_123') === false && strpos($lfp,'SYNTH_STATE_456') === false);

// queued branch
$_REQUEST['cloudframework_queued_fingerprint'] = json_encode($queued);
$lfp = $has_lfp ? $req->getLogFingerPrint() : null;
$dec = is_string($lfp) ? json_decode($lfp, true) : null;
check('queued: uri, http_referer and local_fingerprint masked', is_array($dec) && ($dec['uri']??null) === SSO_MASKED && ($dec['http_referer']??null) === 'https://synth.example/back?token=***&x=1' && ($dec['local_fingerprint']['uri']??null) === '/core/logs?code=***');
check('queued: no SYNTH_CODE_123 / SYNTH_STATE_456 / SYNTH_TOKEN in what sendLog() forwards', is_string($lfp) && strpos($lfp,'SYNTH_CODE_123') === false && strpos($lfp,'SYNTH_STATE_456') === false && strpos($lfp,'SYNTH_TOKEN') === false);
check('queued: other keys kept (hash, ip)', is_array($dec) && ($dec['hash']??null) === 'SYNTH_HASH' && ($dec['ip']??null) === '10.0.0.1');

// invalid queued fingerprint: forwarded as it arrived (previous behaviour)
$_REQUEST['cloudframework_queued_fingerprint'] = 'not-json SYNTH_RAW';
$lfp = $has_lfp ? $req->getLogFingerPrint() : false;
check('queued invalid JSON: forwarded exactly as it arrived', $lfp === 'not-json SYNTH_RAW', var_export($lfp,true));
$_REQUEST['cloudframework_queued_fingerprint'] = '"a json string, not an object"';
$lfp = $has_lfp ? $req->getLogFingerPrint() : false;
check('queued JSON that is not an object: forwarded exactly as it arrived', $lfp === '"a json string, not an object"', var_export($lfp,true));
unset($_REQUEST['cloudframework_queued_fingerprint']);
//endregion

if($php_warnings) { section('PHP warnings'); foreach (array_slice($php_warnings,0,10) as $w) echo "  $w\n"; }
check('no PHP warnings', !$php_warnings, count($php_warnings).' warnings');

echo "\n".($failures ? "FAILED: $failures of $checks checks" : "PASSED: $checks checks")."\n";
exit($failures ? 1 : 0);
