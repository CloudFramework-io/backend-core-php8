<?php
// CLOUD Documentum:
//   DevGroup: /cf/products/cloud-development
//   DevUnit: /php/classes/Core7/CoreSecurity, /php/classes/Core7/CoreCache, /cloud-development/frameworks/php
//   Activities: 6651158731161600
/**
 * Isolated tests of the rotation of the cache keys without cuts, since 8.5.27: the PREVIOUS cache keys, only to read
 * (core.gcp.secrets.cache_encrypt_key_previous / cache_encrypt_iv_previous, CF_CACHE_ENCRYPT_KEY_PREVIOUS /
 * CF_CACHE_ENCRYPT_IV_PREVIOUS, or the fixed Secret Manager version core.gcp.secrets.cache_keys_secret_previous).
 *
 * Contract under test: CoreCache::get() reads with the current keys and, only when they can not read the entry and the
 * keys given are the current cache keys of CoreSecurity (also with the daily subkeys appended), with the previous keys;
 * an entry read that way is returned and NOT deleted; set() always writes with the current keys; keys of an application
 * are never mapped; without previous keys everything is as in 8.5.26. No KMS in the cache.
 *
 * Self-contained: several Core7 (one per "service") share a file cache folder, Secret Manager is an injected reader
 * (CoreSecurity::$cache_keys_secret_reader), the copy of the instance lives in a temporary folder, APCu is disabled.
 * No network, no Redis, no vendor/. Keys are synthetic.
 *
 * Run from the repository root:
 *   php tests/CoreCachePreviousKeys.php
 * Exit code 0 when every check passes, 1 otherwise.
 */

//region ENVIRONMENT
$runtime_vars = ['GAE_SERVICE','K_SERVICE','FUNCTION_TARGET'];
$cache_env = ['CF_CACHE_ENCRYPT_KEY','CF_CACHE_ENCRYPT_IV','CF_CACHE_ENCRYPT_KEY_PREVIOUS','CF_CACHE_ENCRYPT_IV_PREVIOUS'];
foreach (array_merge(['CF_ENCRYPT_KEY','CF_ENCRYPT_SECRET','CF_KMS_DATA_KEYS','CF_KMS_DATA_KEY_PRIMARY','PROJECT_ID','REDIS_HOST','REDIS_PORT','GOOGLE_APPLICATION_CREDENTIALS'],$cache_env,$runtime_vars) as $env_var) putenv($env_var);
foreach ($runtime_vars as $v) unset($_SERVER[$v]);

$tmp_root = sys_get_temp_dir().'/cf-cache-previous-keys-test-'.getmypid();
@mkdir($tmp_root.'/shared-cache',0700,true);
ini_set('session.save_path',$tmp_root);
ob_start();

$syslog_lines = [];
$logger = new class($syslog_lines) {
    private $lines;
    function __construct(array &$lines) { $this->lines = &$lines; }
    function __call($level,$args) { $this->lines[] = [$level,$args[0]??'']; }
};
$php_warnings = [];
set_error_handler(function ($errno, $errstr, $errfile, $errline) use (&$php_warnings) {
    if(!(error_reporting() & $errno)) return true; // silenced with @
    $php_warnings[] = "[$errno] $errstr ($errfile:$errline)";
    return true;
});

include_once __DIR__.'/../src/Core7.php';
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
function count_lines(array $lines, string $needle): int {
    $n = 0;
    foreach ($lines as $line) if(strpos($line[1],$needle)!==false) $n++;
    return $n;
}
function logs_text(Core7 $core): string { return json_encode($core->logs->data,JSON_UNESCAPED_SLASHES).json_encode($core->errors->data,JSON_UNESCAPED_SLASHES); }

// synthetic pairs, random per run (never printed)
$old = ['key'=>base64_encode(random_bytes(32)),'iv'=>base64_encode(random_bytes(32))];
$new = ['key'=>base64_encode(random_bytes(32)),'iv'=>base64_encode(random_bytes(32))];

/** One "service": its own application root, the shared cache folder, current and (optional) previous cache keys */
$app_n = 0;
function service(?array $current, ?array $previous = null, array $extra = []): Core7 {
    global $tmp_root, $app_n, $all_logs;
    $root = $tmp_root.'/app-'.(++$app_n);
    @mkdir($root,0700,true);
    $config = ['core.cache.cache_path'=>$tmp_root.'/shared-cache'];
    if($current) { $config['core.gcp.secrets.cache_encrypt_key'] = $current['key']; $config['core.gcp.secrets.cache_encrypt_iv'] = $current['iv']; }
    if($previous) { $config['core.gcp.secrets.cache_encrypt_key_previous'] = $previous['key']; $config['core.gcp.secrets.cache_encrypt_iv_previous'] = $previous['iv']; }
    file_put_contents($root.'/config.json',json_encode($config+$extra,JSON_UNESCAPED_SLASHES));
    $core = new Core7($root);
    // in a (simulated) managed runtime CoreCache does not take core.cache.cache_path: the same folder and namespace
    if($core->cache->type!=='directory') {
        $core->cache->activateCacheFile($tmp_root.'/shared-cache');
        $core->cache->setNameSpace('cloudframework');
    }
    return $core;
}
function put(Core7 $c, string $key, $value): bool { return $c->cache->set($key,$value,null,$c->security->cache_key,$c->security->cache_iv); }
function read(Core7 $c, string $key) { return $c->cache->get($key,-1,'',$c->security->cache_key,$c->security->cache_iv); }
function raw_exists(Core7 $c, string $key): bool { return $c->cache->get($key)!==null || $c->cache->cache->get($c->cache->spacename.'-'.$key)!==null; }
//endregion

$first = service($old); // first Core7: starts the session before any output
if(ob_get_level()) ob_end_flush();

//region a) without previous keys: as in 8.5.26
section('a) without previous keys everything is as in 8.5.26');
$svc_old = service($old);
$svc_new = service($new);
check('both services share the cache folder and namespace', $svc_old->cache->dir===$svc_new->cache->dir && $svc_old->cache->spacename===$svc_new->cache->spacename);
put($svc_old,'a-entry',['v'=>1]);
check('the old service reads its entry', read($svc_old,'a-entry')===['v'=>1]);
check('the new service (no previous) can not read it: null', read($svc_new,'a-entry')===null);
check('the entry is still there for the old service (a failed decryption is a miss)', read($svc_old,'a-entry')===['v'=>1]);
check('previousCacheKeysFor() is null without previous keys', $svc_new->security->previousCacheKeysFor($new['key'],$new['iv'])===null);
check('no previous-key note', strpos(logs_text($svc_new),'security-cache-previous')===false);
//endregion

//region b) current = new, previous = old
section('b) current = new, previous = old: the old entries are read and kept');
put($svc_old,'b-entry',['v'=>'written with the old keys']);
$svc_rot = service($new,$old);
check('read with the previous keys', read($svc_rot,'b-entry')===['v'=>'written with the old keys']);
check('errorSecurity stays false', $svc_rot->cache->errorSecurity===false);
check('the entry is not deleted: the old service still reads it', read($svc_old,'b-entry')===['v'=>'written with the old keys']);
check('note security-cache-previous-key-read once', substr_count(logs_text($svc_rot),'security-cache-previous-key-read')===1 && (read($svc_rot,'b-entry')!==null) && substr_count(logs_text($svc_rot),'security-cache-previous-key-read')===1);
put($svc_rot,'b-entry',['v'=>'rewritten with the new keys']);
check('set() writes with the current (new) keys', read($svc_new,'b-entry')===['v'=>'rewritten with the new keys']);
check('the old service (no previous) can not read the new entry', read($svc_old,'b-entry')===null);
$svc_phase1 = service($old,$new);
check('phase 1 of the procedure (current old, previous new) reads the new entry', read($svc_phase1,'b-entry')===['v'=>'rewritten with the new keys']);
check('entries written with the current keys are read without the previous ones', (put($svc_rot,'b2',[2])||true) && read($svc_rot,'b2')===[2]);
//endregion

//region c) the daily subkeys are mapped, the keys of an application never
section('c) daily subkeys mapped; keys of an application never mapped');
$sub_key = '__synthetic-daily-subkey'; $sub_iv = '__synthetic-daily-subiv';
$svc_old->cache->set('c-sub',['s'=>1],null,$old['key'].$sub_key,$old['iv'].$sub_iv);
check('previousCacheKeysFor maps the suffix', $svc_rot->security->previousCacheKeysFor($new['key'].$sub_key,$new['iv'].$sub_iv)===[$old['key'].$sub_key,$old['iv'].$sub_iv]);
check('an entry written with old + subkey is read with new + subkey', $svc_rot->cache->get('c-sub',-1,'',$new['key'].$sub_key,$new['iv'].$sub_iv)===['s'=>1]);
check('base pair mapped to the previous base pair', $svc_rot->security->previousCacheKeysFor($new['key'],$new['iv'])===[$old['key'],$old['iv']]);
check('key with suffix and iv without: not mapped', $svc_rot->security->previousCacheKeysFor($new['key'].$sub_key,$new['iv'])===null);
check('keys of an application: not mapped', $svc_rot->security->previousCacheKeysFor('app-key-synthetic','app-iv-synthetic')===null);
$svc_old->cache->set('c-app',['app'=>1],null,'app-key-synthetic','app-iv-synthetic');
check('an entry of an application with other keys: null as before', $svc_rot->cache->get('c-app',-1,'','app-other-key','app-other-iv')===null);
check('the config cache keys of CoreConfig are the mapped ones', $svc_rot->security->previousCacheKeysFor($svc_rot->config->cache_secret_key,$svc_rot->config->cache_secret_iv)===[$old['key'],$old['iv']]);
//endregion

//region d) v2 entries (encrypt_format v2) and garbage
section('d) v2 entries and an entry that no key reads');
$svc_old_v2 = service($old,null,['core.security.encrypt_format'=>'v2']);
put($svc_old_v2,'d-v2',['format'=>'v2']);
check('the stored entry is v2', strpos((string)$svc_old_v2->cache->cache->get($svc_old_v2->cache->spacename.'-d-v2'),'v2:')!==false);
check('a v2 entry is read with the previous keys', read($svc_rot,'d-v2')===['format'=>'v2']);
$third = ['key'=>base64_encode(random_bytes(32)),'iv'=>base64_encode(random_bytes(32))];
$svc_third = service($third);
put($svc_third,'d-third',['x'=>3]);
check('an entry neither current nor previous can read: null', read($svc_rot,'d-third')===null);
check('and it is not deleted by a failed decryption (as before)', read($svc_third,'d-third')===['x'=>3]);
//endregion

//region e) environment variables, half pairs, same pair
section('e) environment variables, half pairs, previous equal to current');
putenv('CF_CACHE_ENCRYPT_KEY_PREVIOUS='.$old['key']); putenv('CF_CACHE_ENCRYPT_IV_PREVIOUS='.$old['iv']);
$svc_env = service($new);
put($svc_old,'e-entry',['env'=>1]);
check('previous keys from CF_CACHE_ENCRYPT_*_PREVIOUS', read($svc_env,'e-entry')===['env'=>1]);
putenv('CF_CACHE_ENCRYPT_IV_PREVIOUS');
$syslog_before = count($syslog_lines);
$svc_half = service($new);
check('half pair (env key without iv): not used', read($svc_half,'e-entry')===null);
check('note security-cache-previous-key-incomplete (warning), without values', count_lines(array_slice($syslog_lines,$syslog_before),'security-cache-previous-key-incomplete')===1 && strpos(json_encode(array_slice($syslog_lines,$syslog_before)),$old['key'])===false);
putenv('CF_CACHE_ENCRYPT_KEY_PREVIOUS');
$svc_same = service($new,$new);
check('previous equal to current: ignored', $svc_same->security->previousCacheKeysFor($new['key'],$new['iv'])===null);
$svc_cfg_wins = service($new,$old);
putenv('CF_CACHE_ENCRYPT_KEY_PREVIOUS=env-other'); putenv('CF_CACHE_ENCRYPT_IV_PREVIOUS=env-other');
check('the config wins over the env', $svc_cfg_wins->security->previousCacheKeysFor($new['key'],$new['iv'])===[$old['key'],$old['iv']]);
putenv('CF_CACHE_ENCRYPT_KEY_PREVIOUS'); putenv('CF_CACHE_ENCRYPT_IV_PREVIOUS');
//endregion

//region f) previous keys from a fixed Secret Manager version
section('f) core.gcp.secrets.cache_keys_secret_previous');
const PREV_VERSION = 'projects/synthetic-project/secrets/synthetic-cache-keys/versions/2';
$reads = 0; $reader_mode = 'ok';
CoreSecurity::$cache_keys_secret_reader = function (string $version_name) use (&$reads,&$reader_mode,$old) {
    $reads++;
    if($reader_mode==='fail') return false;
    return ($version_name===PREV_VERSION)?json_encode($old):false;
};
CoreSecurity::$cache_keys_use_apcu = false;
$instance_dir = $tmp_root.'/instance'; @mkdir($instance_dir,0700);
CoreSecurity::$cache_keys_instance_dir = $instance_dir;
$copy_file = $instance_dir.'/cf-cache-keys-'.substr(hash('sha256',PREV_VERSION),0,16).'.json';

$svc_sm = service($new,null,['core.gcp.secrets.cache_keys_secret_previous'=>PREV_VERSION]);
put($svc_old,'f-entry',['sm'=>1]);
check('outside a managed runtime: not read', read($svc_sm,'f-entry')===null && $reads===0);
$_SERVER['GAE_SERVICE'] = 'synthetic-service';
$svc_sm = service($new,null,['core.gcp.secrets.cache_keys_secret_previous'=>PREV_VERSION]);
check('the version is not read while nothing fails to decrypt', (put($svc_sm,'f-own',[1])||true) && read($svc_sm,'f-own')===[1] && $reads===0);
check('managed runtime: read from Secret Manager on the first failed entry', read($svc_sm,'f-entry')===['sm'=>1] && $reads===1);
clearstatcache();
check('copy of the instance written with 0600', is_file($copy_file) && substr(sprintf('%o',fileperms($copy_file)),-4)==='0600');
check('read once per request', read($svc_sm,'d-v2')===['format'=>'v2'] && $reads===1);
$svc_sm2 = service($new,null,['core.gcp.secrets.cache_keys_secret_previous'=>PREV_VERSION]);
check('next request: the copy of the instance, no Secret Manager read', read($svc_sm2,'f-entry')===['sm'=>1] && $reads===1);
check('note source=instance-copy', strpos(logs_text($svc_sm2),'security-cache-keys-secret-previous-used: source=instance-copy')!==false);
@unlink($copy_file);
$reader_mode = 'fail';
$svc_fail = service($new,null,['core.gcp.secrets.cache_keys_secret_previous'=>PREV_VERSION]);
check('failed read: the entry is a miss', read($svc_fail,'f-entry')===null && $reads===2);
check('no copy written after a failure', !is_file($copy_file));
$svc_fail2 = service($new,null,['core.gcp.secrets.cache_keys_secret_previous'=>PREV_VERSION]);
check('a recent failure is not retried', read($svc_fail2,'f-entry')===null && $reads===2);
$reader_mode = 'ok';
$syslog_before = count($syslog_lines);
$svc_latest = service($new,null,['core.gcp.secrets.cache_keys_secret_previous'=>'projects/synthetic-project/secrets/synthetic-cache-keys/versions/latest']);
check('version latest: never read', read($svc_latest,'f-entry')===null && $reads===2);
check('note security-cache-keys-secret-version-required', count_lines(array_slice($syslog_lines,$syslog_before),'cache_keys_secret_previous must be the full name of a fixed version')===1);
unset($_SERVER['GAE_SERVICE']);
CoreSecurity::$cache_keys_secret_reader = null;
//endregion

//region g) nothing of KMS in the cache, no key values in the logs
section('g) no KMS in the cache, no key values in the logs');
$svc_kms = service($new,$old,['core.gcp.kms.data_keys'=>['a'=>'projects/p/locations/l/keyRings/r/cryptoKeys/k'],'core.gcp.kms.data_key_primary'=>'a']);
put($svc_kms,'g-entry',['kms'=>'never']);
$stored = (string)$svc_kms->cache->cache->get($svc_kms->cache->spacename.'-g-entry');
check('a cache entry is never v3 even with KMS configured', strpos($stored,'v3:')===false && read($svc_kms,'g-entry')===['kms'=>'never']);
$everything = json_encode($syslog_lines);
foreach ([$svc_rot,$svc_env,$svc_half,$svc_sm,$svc_sm2,$svc_fail,$svc_latest] as $c) $everything .= logs_text($c);
$leak = false;
foreach ([$old['key'],$old['iv'],$new['key'],$new['iv']] as $v) if(strpos($everything,$v)!==false || strpos($everything,substr($v,0,12))!==false) $leak = true;
check('no cache key value (or a fragment) in any log', !$leak);
//endregion

//region SUMMARY
echo "\nPHP warnings/notices captured during the whole run: ".count($php_warnings)."\n";
foreach ($php_warnings as $w) echo "  - $w\n";
check('no PHP warnings', count($php_warnings)===0);
echo "\n{$checks} checks, {$failures} failures\n";
exec('rm -rf '.escapeshellarg($tmp_root));
exit($failures?1:0);
//endregion
