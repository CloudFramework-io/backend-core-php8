<?php
// CLOUD Documentum:
//   DevGroup: /cf/products/cloud-development
//   DevUnit: /cloud-development/frameworks/php
//   Activities: 4566689232715776
/**
 * Isolated tests of the IP of the client, since 8.5.28: CoreSystem::getClientIP() returns the IP that the platform gives
 * (X-Appengine-User-Ip in App Engine, the last element of X-Forwarded-For in Cloud Run and Cloud Functions, REMOTE_ADDR
 * outside a managed runtime), always a valid IP, never the text the caller writes in X-Forwarded-For. The chain the
 * caller declared is kept apart in the fingerprint (forwarded_for). The IP allow lists (checkIPs(), checkAllowedIPs())
 * compare exactly or by CIDR with CoreSecurity::ipInList(), IPv4 and IPv6. The IP and the fingerprint of a queued call
 * are read from the request only in a real task of a queue.
 *
 * Self-contained: no external network, no Datastore, no Redis, no vendor/ autoload and no config.json of an application.
 * Every value is synthetic and every IP is from the documentation ranges (RFC 5737, RFC 3849).
 *
 * Run from the repository root:
 *   php tests/CoreSystemClientIP.php
 * Point it to another src/ (e.g. 8.5.27, to see the suite fail) with:
 *   CF_TEST_SRC=/path/to/src php tests/CoreSystemClientIP.php
 * Exit code 0 when every check passes, 1 otherwise.
 */

//region ENVIRONMENT: clean env vars and a temporary root
foreach (['CF_ENCRYPT_KEY','CF_ENCRYPT_SECRET','CF_CACHE_ENCRYPT_KEY','CF_CACHE_ENCRYPT_IV','PROJECT_ID','REDIS_HOST','REDIS_PORT','GAE_SERVICE','K_SERVICE','FUNCTION_TARGET'] as $var) putenv($var);
unset($_SERVER['GAE_SERVICE'], $_SERVER['K_SERVICE'], $_SERVER['FUNCTION_TARGET'], $_SERVER['HTTP_X_APPENGINE_USER_IP'],
    $_SERVER['HTTP_X_FORWARDED_FOR'], $_SERVER['HTTP_X_APPENGINE_QUEUENAME'], $_SERVER['REMOTE_ADDR']);

$tmp_root = sys_get_temp_dir().'/cf-core-client-ip-test-'.getmypid();
@mkdir($tmp_root.'/local_data/cache',0700,true);
file_put_contents($tmp_root.'/config.json','{}');
ini_set('session.save_path',$tmp_root);
ob_start(); // Core7 starts a PHP session in terminal mode: no output may be sent before it

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

$core = new Core7($tmp_root);
ob_end_clean();

/**
 * Sets the runtime and the request headers of the next call in $_SERVER.
 * @param string $runtime 'local' | 'gae' | 'cloudrun' | 'functions'
 * @param array $server values for $_SERVER
 */
function scenario(string $runtime, array $server = []): void {
    unset($_SERVER['GAE_SERVICE'], $_SERVER['K_SERVICE'], $_SERVER['FUNCTION_TARGET'], $_SERVER['HTTP_X_APPENGINE_USER_IP'],
        $_SERVER['HTTP_X_FORWARDED_FOR'], $_SERVER['HTTP_X_APPENGINE_QUEUENAME'], $_SERVER['REMOTE_ADDR'],
        $_REQUEST['cloudframework_queued_fingerprint'], $_REQUEST['cloudframework_queued_ip']);
    if($runtime === 'gae') $_SERVER['GAE_SERVICE'] = 'synth-service';
    if($runtime === 'cloudrun') $_SERVER['K_SERVICE'] = 'synth-service';
    if($runtime === 'functions') $_SERVER['FUNCTION_TARGET'] = 'synth-function';
    foreach ($server as $key => $value) $_SERVER[$key] = $value;
}
/** getClientIP() of the CoreSystem, as the framework calls it */
function client_ip(): string {
    global $core;
    return (string)$core->system->getClientIP();
}
/** CoreSecurity::ipInList(), or null when the src under test does not have it (an older version) */
function in_list(string $ip, $allows): ?bool {
    return method_exists('CoreSecurity','ipInList') ? CoreSecurity::ipInList($ip, $allows) : null;
}
/** the private CoreSecurity::checkIPs() with the IP of the client set to $ip */
function check_ips(string $ip, $allows): bool {
    global $core;
    $core->system->ip = $ip;
    $m = new ReflectionMethod($core->security, 'checkIPs');
    return (bool)$m->invoke($core->security, $allows);
}
//endregion

//region a) App Engine
section('a) App Engine: X-Appengine-User-Ip, never what the caller writes in X-Forwarded-For');
scenario('gae', ['HTTP_X_APPENGINE_USER_IP'=>'198.51.100.9', 'HTTP_X_FORWARDED_FOR'=>'198.51.100.9']);
check('a normal request gives the IP of the platform', client_ip() === '198.51.100.9', client_ip());
scenario('gae', ['HTTP_X_APPENGINE_USER_IP'=>'198.51.100.9', 'HTTP_X_FORWARDED_FOR'=>'203.0.113.7,198.51.100.9']);
check('an IP written by the caller in front is ignored', client_ip() === '198.51.100.9', client_ip());
scenario('gae', ['HTTP_X_APPENGINE_USER_IP'=>'198.51.100.9', 'HTTP_X_FORWARDED_FOR'=>'hola/../x,198.51.100.9']);
check('free text written by the caller is ignored', client_ip() === '198.51.100.9', client_ip());
scenario('gae', ['HTTP_X_APPENGINE_USER_IP'=>'2001:db8::9', 'HTTP_X_FORWARDED_FOR'=>'203.0.113.7, 2001:db8::9']);
check('IPv6 of the platform', client_ip() === '2001:db8::9', client_ip());
scenario('gae', ['HTTP_X_FORWARDED_FOR'=>'203.0.113.7,198.51.100.9']);
check('without X-Appengine-User-Ip: the last element of X-Forwarded-For', client_ip() === '198.51.100.9', client_ip());
scenario('gae', ['HTTP_X_APPENGINE_USER_IP'=>'not-an-ip', 'HTTP_X_FORWARDED_FOR'=>'203.0.113.7,198.51.100.9']);
check('an invalid X-Appengine-User-Ip falls back to the last element', client_ip() === '198.51.100.9', client_ip());
scenario('gae', ['HTTP_X_FORWARDED_FOR'=>'198.51.100.9,hola']);
check('a last element that is not an IP gives unknown', client_ip() === 'unknown', client_ip());
scenario('gae');
check('nothing at all gives unknown, never localhost', client_ip() === 'unknown', client_ip());
scenario('gae', ['REMOTE_ADDR'=>'198.51.100.200', 'HTTP_X_APPENGINE_USER_IP'=>'198.51.100.9']);
check('REMOTE_ADDR (the internal front end) is not used in App Engine', client_ip() === '198.51.100.9', client_ip());
//endregion

//region b) Cloud Run and Cloud Functions
section('b) Cloud Run and Cloud Functions: the last element of X-Forwarded-For');
scenario('cloudrun', ['HTTP_X_FORWARDED_FOR'=>'203.0.113.7, 198.51.100.9']);
check('Cloud Run: the element the Google front end appends', client_ip() === '198.51.100.9', client_ip());
scenario('cloudrun', ['HTTP_X_FORWARDED_FOR'=>'203.0.113.7, 2001:db8::9']);
check('Cloud Run: IPv6', client_ip() === '2001:db8::9', client_ip());
scenario('cloudrun', ['HTTP_X_APPENGINE_USER_IP'=>'203.0.113.7', 'HTTP_X_FORWARDED_FOR'=>'198.51.100.9']);
check('Cloud Run: X-Appengine-User-Ip is not trusted there (the caller can write it)', client_ip() === '198.51.100.9', client_ip());
scenario('functions', ['HTTP_X_FORWARDED_FOR'=>'198.51.100.9']);
check('Cloud Functions: the last element', client_ip() === '198.51.100.9', client_ip());
scenario('cloudrun');
check('Cloud Run without the header gives unknown', client_ip() === 'unknown', client_ip());
//endregion

//region c) local
section('c) outside a managed runtime: REMOTE_ADDR');
scenario('local', ['REMOTE_ADDR'=>'198.51.100.9', 'HTTP_X_FORWARDED_FOR'=>'203.0.113.7']);
check('REMOTE_ADDR, not X-Forwarded-For', client_ip() === '198.51.100.9', client_ip());
scenario('local', ['REMOTE_ADDR'=>'127.0.0.1']);
check('127.0.0.1 gives localhost', client_ip() === 'localhost', client_ip());
scenario('local', ['REMOTE_ADDR'=>'::1']);
check('::1 gives localhost', client_ip() === 'localhost', client_ip());
scenario('local', ['HTTP_X_FORWARDED_FOR'=>'203.0.113.7']);
check('a terminal (no REMOTE_ADDR) gives localhost, also with X-Forwarded-For', client_ip() === 'localhost', client_ip());
//endregion

//region d) forwarded_for in the fingerprint
section('d) the chain the caller declared, apart and decided by nothing');
$has_ff = method_exists('CoreSystem','forwardedForIPs');
check('CoreSystem::forwardedForIPs() exists', $has_ff);
if($has_ff) {
    scenario('gae', ['HTTP_X_FORWARDED_FOR'=>'203.0.113.7, hola/../x , 2001:db8::7,198.51.100.9']);
    check('only valid IPs, in order', CoreSystem::forwardedForIPs() === ['203.0.113.7','2001:db8::7','198.51.100.9'], json_encode(CoreSystem::forwardedForIPs()));
    scenario('gae', ['HTTP_X_FORWARDED_FOR'=>implode(',', array_map(fn($i)=>"203.0.113.$i", range(1,15)))]);
    check('at most 10 IPs', count(CoreSystem::forwardedForIPs()) === 10);
    scenario('gae');
    check('no header gives []', CoreSystem::forwardedForIPs() === []);
}
scenario('gae', ['HTTP_X_APPENGINE_USER_IP'=>'198.51.100.9', 'HTTP_X_FORWARDED_FOR'=>'203.0.113.7,198.51.100.9']);
$core->system->ip = client_ip();
$fp = $core->system->getRequestFingerPrint();
check('fingerprint: ip is the IP of the platform', ($fp['ip'] ?? null) === '198.51.100.9', json_encode($fp['ip'] ?? null));
check('fingerprint: forwarded_for keeps the declared chain', ($fp['forwarded_for'] ?? null) === '203.0.113.7, 198.51.100.9', json_encode($fp['forwarded_for'] ?? null));
scenario('gae', ['HTTP_X_APPENGINE_USER_IP'=>'198.51.100.9', 'HTTP_X_FORWARDED_FOR'=>'198.51.100.9']);
$core->system->ip = client_ip();
$fp = $core->system->getRequestFingerPrint();
check('fingerprint: no forwarded_for when it only repeats ip', !array_key_exists('forwarded_for', $fp), json_encode($fp['forwarded_for'] ?? null));
//endregion

//region e) queued IP and fingerprint
section('e) cloudframework_queued_fingerprint / cloudframework_queued_ip only in a real task of a queue');
$queued = json_encode(['ip'=>'203.0.113.66','user_agent'=>'SYNTH_QUEUED_AGENT']);
scenario('gae', ['HTTP_X_APPENGINE_USER_IP'=>'198.51.100.9']);
$_REQUEST['cloudframework_queued_fingerprint'] = $queued;
$core->system->ip = client_ip();
$fp = $core->system->getRequestFingerPrint();
check('App Engine, request from the internet: the queued fingerprint is ignored', ($fp['ip'] ?? null) === '198.51.100.9' && ($fp['user_agent'] ?? null) !== 'SYNTH_QUEUED_AGENT', json_encode($fp));
scenario('gae', ['HTTP_X_APPENGINE_USER_IP'=>'198.51.100.9', 'HTTP_X_APPENGINE_QUEUENAME'=>'default']);
$_REQUEST['cloudframework_queued_fingerprint'] = $queued;
$fp = $core->system->getRequestFingerPrint();
check('App Engine, task of a queue: the queued fingerprint is used', ($fp['ip'] ?? null) === '203.0.113.66', json_encode($fp));
scenario('cloudrun', ['HTTP_X_FORWARDED_FOR'=>'198.51.100.9', 'HTTP_X_APPENGINE_QUEUENAME'=>'default']);
$_REQUEST['cloudframework_queued_fingerprint'] = $queued;
$core->system->ip = client_ip();
$fp = $core->system->getRequestFingerPrint();
check('Cloud Run: X-AppEngine-QueueName is not trusted (the caller can write it)', ($fp['ip'] ?? null) === '198.51.100.9', json_encode($fp));
if(method_exists('CoreSystem','isQueuedTask')) {
    scenario('local');
    check('local: isQueuedTask() is true (development)', CoreSystem::isQueuedTask() === true);
    scenario('gae');
    check('App Engine without X-AppEngine-QueueName: isQueuedTask() is false', CoreSystem::isQueuedTask() === false);
} else check('CoreSystem::isQueuedTask() exists', false);
scenario('local');
//endregion

//region f) CoreSecurity::ipInList()
section('f) CoreSecurity::ipInList(): exact or CIDR, IPv4 and IPv6');
check('CoreSecurity::ipInList() exists', method_exists('CoreSecurity','ipInList'));
$cases = [
    ['exact IPv4',                                    '203.0.113.7',  '198.51.100.1,203.0.113.7', true],
    ['an entry no longer matches inside another IP', '11.2.3.45',    '1.2.3.4',                  false],
    ['a forged chain never matches',                  '203.0.113.7,198.51.100.9', '203.0.113.7',  false],
    ['CIDR /24',                                      '198.51.100.77','198.51.100.0/24',          true],
    ['out of the CIDR',                               '198.51.101.77','198.51.100.0/24',          false],
    ['CIDR /32',                                      '198.51.100.77','198.51.100.77/32',         true],
    ['CIDR /0',                                       '203.0.113.7',  '0.0.0.0/0',                true],
    ['CIDR /25 (bits inside a byte)',                 '198.51.100.130','198.51.100.128/25',       true],
    ['CIDR /25 the other half',                       '198.51.100.12','198.51.100.128/25',        false],
    ['IPv6 exact in another notation',                '2001:db8:0:0:0:0:0:9', '2001:db8::9',      true],
    ['IPv6 CIDR',                                     '2001:db8:abcd::1', '2001:db8::/32',        true],
    ['IPv6 out of the CIDR',                          '2001:db9::1',  '2001:db8::/32',            false],
    ['IPv4 against an IPv6 range',                    '203.0.113.7',  '2001:db8::/32',            false],
    ['* allows any IP',                               '203.0.113.7',  '*',                        true],
    ['* allows also an invalid IP',                   'unknown',      '*',                        true],
    ['an invalid IP matches nothing else',            'unknown',      'unknown,203.0.113.7',      false],
    ['localhost entry and localhost',                 'localhost',    'localhost',                true],
    ['localhost entry and ::1',                       '::1',          'localhost',                true],
    ['localhost inside 127.0.0.0/8',                  'localhost',    '127.0.0.0/8',              true],
    ['legacy prefix ending in .',                     '198.51.100.9', '198.51.100.',              true],
    ['legacy prefix does not match another network',  '198.51.10.9',  '198.51.100.',              false],
    ['legacy prefix only at the start of the IP',     '11.2.3.4',     '1.2.',                     false],
    ['regex characters in an entry are not a pattern','203.0.113.7',  '203.0.113.(7|8),.*',       false],
    ['invalid CIDR is ignored',                       '203.0.113.7',  '203.0.113.0/40,203.0.113.0/x', false],
    ['array of entries',                              '203.0.113.7',  [' 198.51.100.1 ', '203.0.113.7'], true],
    ['empty list',                                    '203.0.113.7',  '',                         false],
];
foreach ($cases as [$name, $ip, $allows, $expected]) {
    $got = in_list($ip, $allows);
    check($name, $got === $expected, 'got '.json_encode($got));
}
//endregion

//region g) checkIPs() and checkAllowedIPs()
section('g) checkIPs() (Basic Auth, server keys) and checkAllowedIPs() use the same comparator');
check('checkIPs: the allowed IP', check_ips('203.0.113.7', '203.0.113.7') === true);
check('checkIPs: an IP containing the allowed one does not pass', check_ips('203.0.113.70', '203.0.113.7') === false);
scenario('gae', ['HTTP_X_APPENGINE_USER_IP'=>'198.51.100.9', 'HTTP_X_FORWARDED_FOR'=>'203.0.113.7,198.51.100.9']);
check('checkIPs: writing the allowed IP in X-Forwarded-For does not pass', check_ips(client_ip(), '203.0.113.7') === false);
check('checkIPs: a regex entry does not throw nor match', check_ips('203.0.113.7', '(') === false);
check('checkAllowedIPs: empty list allows, as before', $core->security->checkAllowedIPs('', '203.0.113.7') === true);
check('checkAllowedIPs: one CIDR without comma', $core->security->checkAllowedIPs('203.0.113.0/24', '203.0.113.7') === true);
check('checkAllowedIPs: IPv6 range', $core->security->checkAllowedIPs('198.51.100.1,2001:db8::/32', '2001:db8::1') === true);
check('checkAllowedIPs: a forged chain does not pass', $core->security->checkAllowedIPs('203.0.113.7', '203.0.113.7,198.51.100.9') === false);
check('checkAllowedIPs: default IP is the one of the client', (function() use ($core) { $core->system->ip = '203.0.113.7'; return $core->security->checkAllowedIPs('203.0.113.0/24'); })() === true);
//endregion

echo "\n".($failures ? "FAILED: {$failures} of {$checks} checks" : "PASSED: {$checks} checks")."\n";
exec('rm -rf '.escapeshellarg($tmp_root));
exit($failures ? 1 : 0);
