<?php
// CLOUD Documentum:
//   DevGroup: /cf/products/cloud-development
//   DevUnit: /php/classes/Core7/CoreSecurity, /php/classes/Core7/CoreCache, /cloud-development/frameworks/php
//   Activities: 5630949161435136
/**
 * Isolated tests of the cache keys read from a FIXED Secret Manager version, since 8.5.23
 * (core.gcp.secrets.cache_keys_secret, CoreSecurity::resolveMissingCacheKeys() / useCacheKeysSecret()).
 *
 * Contract under test: when neither the configuration nor the environment brings the cache keys, a managed runtime
 * (GAE_SERVICE, K_SERVICE or FUNCTION_TARGET) with core.gcp.secrets.cache_keys_secret uses the copy of the instance
 * or, without a valid copy, reads the version once, writes the copy (0600) and uses it. A version that is not a fixed
 * number is never read; a failed read or an invalid content writes no copy and is not retried for 60 seconds; without
 * the config-var or outside a managed runtime the 8.5.22 local file applies.
 *
 * Self-contained: Secret Manager is replaced by an injected reader (CoreSecurity::$cache_keys_secret_reader), the copy
 * of the instance lives in temporary folders (CoreSecurity::$cache_keys_instance_dir, APCu disabled with
 * CoreSecurity::$cache_keys_use_apcu = false), no network, no Redis, no vendor/ autoload. Keys are synthetic, random per
 * run, and only compared, never printed.
 *
 * Run from the repository root:
 *   php tests/CoreSecurityCacheKeysSecret.php
 * Exit code 0 when every check passes, 1 otherwise.
 */

//region ENVIRONMENT
$runtime_vars = ['GAE_SERVICE','K_SERVICE','FUNCTION_TARGET'];
foreach (array_merge(['CF_ENCRYPT_KEY','CF_ENCRYPT_SECRET','CF_CACHE_ENCRYPT_KEY','CF_CACHE_ENCRYPT_IV','PROJECT_ID','REDIS_HOST','REDIS_PORT','GOOGLE_APPLICATION_CREDENTIALS'],$runtime_vars) as $env_var) putenv($env_var);
foreach ($runtime_vars as $v) unset($_SERVER[$v]);

$tmp_root = sys_get_temp_dir().'/cf-core-cache-keys-secret-test-'.getmypid();
@mkdir($tmp_root,0700,true);
ini_set('session.save_path',$tmp_root);
ob_start(); // Core7 starts a PHP session in terminal mode: no output may be sent before it

$syslog_lines = [];
$logger = new class($syslog_lines) {
    private $lines;
    function __construct(array &$lines) { $this->lines = &$lines; }
    function __call($level,$args) { $this->lines[] = [$level,$args[0]??'']; }
};

$php_warnings = [];
set_error_handler(function ($errno, $errstr, $errfile, $errline) use (&$php_warnings) {
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

const VERSION = 'projects/synthetic-project/secrets/synthetic-cache-keys/versions/3';

// synthetic secret pair, random per run (never printed)
$secret_keys = ['key'=>base64_encode(random_bytes(32)),'iv'=>base64_encode(random_bytes(32))];
$secret_content = json_encode($secret_keys);

// fake Secret Manager reader
$reads = 0; $reader_mode = 'ok'; $reader_names = [];
CoreSecurity::$cache_keys_secret_reader = function (string $version_name) use (&$reads,&$reader_mode,&$reader_names,&$secret_content) {
    $reads++;
    $reader_names[] = $version_name;
    if($reader_mode==='fail') return false;
    if($reader_mode==='throw') throw new RuntimeException('synthetic-permission-denied');
    return $secret_content;
};
CoreSecurity::$cache_keys_use_apcu = false;

/** Temporary application root. The file cache folder is outside local_data. */
function make_app(string $name, array $config = [], bool $with_local_data = false, bool $file_cache = false): string {
    global $tmp_root;
    $root = $tmp_root.'/'.$name;
    @mkdir($root,0700,true);
    if($file_cache) { $config['core.cache.cache_path'] = $root.'/cache-dir'; @mkdir($root.'/cache-dir',0700); }
    file_put_contents($root.'/config.json',json_encode($config,JSON_UNESCAPED_SLASHES)); // included by src/config.json
    if($with_local_data) @mkdir($root.'/local_data',0700);
    return $root;
}
/** A new folder for the copy of the instance (a fresh "instance") */
function new_instance_dir(string $name): string {
    global $tmp_root;
    $dir = $tmp_root.'/instance-'.$name;
    @mkdir($dir,0700,true);
    CoreSecurity::$cache_keys_instance_dir = $dir;
    return $dir;
}
function hash_short(): string { return substr(hash('sha256',VERSION),0,16); }
function copy_file(string $dir): string { return $dir.'/cf-cache-keys-'.hash_short().'.json'; }
function fail_file(string $dir): string { return $dir.'/cf-cache-keys-'.hash_short().'.failed'; }
function perms(string $file): string { clearstatcache(); return substr(sprintf('%o',fileperms($file)),-4); }
function managed(string $how = 'gae'): void {
    unmanaged();
    if($how==='gae') $_SERVER['GAE_SERVICE'] = 'synthetic-service';
    elseif($how==='run') putenv('K_SERVICE=synthetic-run-service');
    elseif($how==='function') $_SERVER['FUNCTION_TARGET'] = 'synthetic-function';
}
function unmanaged(): void {
    global $runtime_vars;
    foreach ($runtime_vars as $v) { unset($_SERVER[$v]); putenv($v); }
}
function count_lines(array $lines, string $needle): int {
    $n = 0;
    foreach ($lines as $line) if(strpos($line[1],$needle)!==false) $n++;
    return $n;
}
function request_logs_contain(Core7 $core, string $needle): bool {
    return strpos(json_encode($core->logs->data,JSON_UNESCAPED_SLASHES),$needle)!==false;
}
/** every log produced in the run, for the final leak check */
$all_logs = '';
function build(string $root, ?array &$new_lines = null): Core7 {
    global $syslog_lines, $all_logs;
    $before = count($syslog_lines);
    $core = new Core7($root);
    $new_lines = array_slice($syslog_lines,$before);
    $all_logs .= json_encode($core->logs->data,JSON_UNESCAPED_SLASHES).json_encode($core->errors->data,JSON_UNESCAPED_SLASHES);
    return $core;
}
function no_temp_files(string $dir): bool { return count(glob($dir.'/.cf-cache-keys-*'))===0; }
function uses_secret_keys(Core7 $core): bool {
    global $secret_keys;
    return !$core->security->cache_keys_missing
        && $core->security->cache_key===$secret_keys['key'] && $core->security->cache_iv===$secret_keys['iv']
        && $core->config->cache_secret_key===$secret_keys['key'] && $core->config->cache_secret_iv===$secret_keys['iv']
        && $core->getStartupError()===null;
}
//endregion

//region a) negative: the version must be a fixed number
section('a) a version that is not a fixed number is never read');
foreach ([
    'latest' => 'projects/synthetic-project/secrets/synthetic-cache-keys/versions/latest',
    'not a number' => 'projects/synthetic-project/secrets/synthetic-cache-keys/versions/v3',
    'zero' => 'projects/synthetic-project/secrets/synthetic-cache-keys/versions/0',
    'short secret name' => 'synthetic-cache-keys',
    'no version' => 'projects/synthetic-project/secrets/synthetic-cache-keys',
] as $label=>$name) {
    managed();
    $dir = new_instance_dir('a-'.md5($label));
    $reads_before = $reads;
    $core = build(make_app('a-'.md5($label),['core.gcp.secrets.cache_keys_secret'=>$name]),$new);
    check("$label: the secret is not read", $reads===$reads_before);
    check("$label: keys missing, startup error kept", $core->security->cache_keys_missing===true && is_array($core->getStartupError()));
    check("$label: security-cache-keys-secret-version-required logged once", count_lines($new,'security-cache-keys-secret-version-required')===1);
    check("$label: the configured value is not logged", strpos(json_encode($core->logs->data,JSON_UNESCAPED_SLASHES).json_encode($new,JSON_UNESCAPED_SLASHES),$name)===false);
    check("$label: no copy written", count(glob($dir.'/*'))===0);
}
// a value that is not a string is not a version name
managed();
new_instance_dir('a-array');
$reads_before = $reads;
$core = build(make_app('a-array',['core.gcp.secrets.cache_keys_secret'=>['name'=>VERSION]]),$new);
check('not a string: not read, version-required logged, keys missing', $reads===$reads_before && count_lines($new,'security-cache-keys-secret-version-required')===1 && $core->security->cache_keys_missing===true);
// outside a managed runtime a wrong version is reported but the local file (8.5.22) still applies
unmanaged();
new_instance_dir('a-local');
$reads_before = $reads;
$root = make_app('a-local',['core.gcp.secrets.cache_keys_secret'=>'projects/synthetic-project/secrets/synthetic-cache-keys/versions/latest'],true,true);
$core = build($root,$new);
check('latest in local: not read, reported, and the local file is used', $reads===$reads_before && count_lines($new,'security-cache-keys-secret-version-required')===1
    && !$core->security->cache_keys_missing && is_file($root.'/local_data/cache-encrypt.json'));
//endregion

//region b) negative: not a managed runtime
section('b) outside a managed runtime the secret is not read and A0 (local file) applies');
unmanaged();
$dir = new_instance_dir('b');
$reads_before = $reads;
$root = make_app('b-local',['core.gcp.secrets.cache_keys_secret'=>VERSION],true,true);
$core = build($root,$new);
$local = json_decode((string)@file_get_contents($root.'/local_data/cache-encrypt.json'),true);
check('local: the secret is not read', $reads===$reads_before);
check('local: keys from local_data/cache-encrypt.json, also in CoreConfig', is_array($local) && $core->security->cache_key===$local['key'] && $core->security->cache_iv===$local['iv']
    && $core->config->cache_secret_key===$local['key'] && $core->config->cache_secret_iv===$local['iv']);
check('local: one log line with source=local-file', count_lines($new,'source=local-file')===1);
check('local: no copy of the instance written', count(glob($dir.'/*'))===0);

$reads_before = $reads;
$core = build(make_app('b-memory',['core.gcp.secrets.cache_keys_secret'=>VERSION],false,false),$new);
check('local without local_data nor file cache: not read, keys missing (security-cache-key-missing)', $reads===$reads_before && $core->security->cache_keys_missing===true && is_array($core->getStartupError()));
//endregion

//region c) negative: configuration and environment win
section('c) configuration and environment win over the secret');
managed();
new_instance_dir('c');
$reads_before = $reads;
$core = build(make_app('c-config',['core.gcp.secrets.cache_keys_secret'=>VERSION,'core.gcp.secrets.cache_encrypt_key'=>'cfg-cache-key-synthetic','core.gcp.secrets.cache_encrypt_iv'=>'cfg-cache-iv-synthetic']));
check('keys in config: the secret is not read and the config keys are used', $reads===$reads_before && $core->security->cache_key==='cfg-cache-key-synthetic' && $core->config->cache_secret_key==='cfg-cache-key-synthetic');

putenv('CF_CACHE_ENCRYPT_KEY=env-cache-key-synthetic'); putenv('CF_CACHE_ENCRYPT_IV=env-cache-iv-synthetic');
$core = build(make_app('c-env',['core.gcp.secrets.cache_keys_secret'=>VERSION]));
check('keys in env: the secret is not read and the env keys are used', $reads===$reads_before && $core->security->cache_key==='env-cache-key-synthetic' && $core->security->cache_iv==='env-cache-iv-synthetic');
putenv('CF_CACHE_ENCRYPT_KEY'); putenv('CF_CACHE_ENCRYPT_IV');

$core = build(make_app('c-half',['core.gcp.secrets.cache_keys_secret'=>VERSION,'core.gcp.secrets.cache_encrypt_key'=>'cfg-cache-key-synthetic']));
check('half pair in config: the secret is not read and the pair is not completed', $reads===$reads_before && $core->security->cache_keys_missing===true);
//endregion

//region d) negative: Secret Manager fails
section('d) Secret Manager fails: no copy, failure marked, no retry for 60 seconds');
managed();
$dir = new_instance_dir('d');
$root = make_app('d-fail',['core.gcp.secrets.cache_keys_secret'=>VERSION]);
$reader_mode = 'fail';
$reads_before = $reads;
$core = build($root,$new);
check('failed read: read once with the exact version name', $reads===$reads_before+1 && end($reader_names)===VERSION);
check('failed read: keys missing, startup error kept', $core->security->cache_keys_missing===true && is_array($core->getStartupError()));
check('failed read: no copy written', !file_exists(copy_file($dir)));
check('failed read: failure marked with permissions 0600', is_file(fail_file($dir)) && perms(fail_file($dir))==='0600');
check('failed read: security-cache-keys-secret-unavailable logged once (warning)', count_lines($new,'security-cache-keys-secret-unavailable')===1 && ($new[0][0]??'')==='warning');

$core = build($root,$new);
check('within 60 s: not read again', $reads===$reads_before+1);
check('within 60 s: keys missing and unavailable logged', $core->security->cache_keys_missing===true && count_lines($new,'security-cache-keys-secret-unavailable')===1);

file_put_contents(fail_file($dir),(string)(time()-CoreSecurity::CACHE_KEYS_SECRET_RETRY_SECONDS-1));
$core = build($root,$new);
check('after 60 s: read again (still failing, marked again)', $reads===$reads_before+2 && $core->security->cache_keys_missing===true && is_file(fail_file($dir))
    && (time()-(int)file_get_contents(fail_file($dir)))<5);

$reader_mode = 'ok';
file_put_contents(fail_file($dir),(string)(time()-CoreSecurity::CACHE_KEYS_SECRET_RETRY_SECONDS-1));
$core = build($root,$new);
check('after 60 s with the secret back: read, keys set, mark removed, copy written', $reads===$reads_before+3 && uses_secret_keys($core) && !file_exists(fail_file($dir)) && is_file(copy_file($dir)));

// a reader that throws counts as a failed read
$dir = new_instance_dir('d-throw');
$reader_mode = 'throw';
$reads_before = $reads;
$core = build($root,$new);
check('exception in the read: keys missing, no copy, failure marked', $reads===$reads_before+1 && $core->security->cache_keys_missing===true && !file_exists(copy_file($dir)) && is_file(fail_file($dir)));
check('exception in the read: the cause is logged', count_lines($new,'synthetic-permission-denied')===1);
$reader_mode = 'ok';

// the real reader without the Secret Manager SDK (no vendor/ in this suite): a failed read, never a fatal error
if(!class_exists('Google\Cloud\SecretManager\V1\Client\SecretManagerServiceClient',false)) {
    $dir = new_instance_dir('d-real');
    $saved = CoreSecurity::$cache_keys_secret_reader;
    CoreSecurity::$cache_keys_secret_reader = null;
    $core = build($root,$new);
    CoreSecurity::$cache_keys_secret_reader = $saved;
    check('real reader without the SDK: keys missing, no copy, failure marked, unavailable logged', $core->security->cache_keys_missing===true && !file_exists(copy_file($dir))
        && is_file(fail_file($dir)) && count_lines($new,'security-cache-keys-secret-unavailable')===1);
    $gs = new GoogleSecrets($core);
    check('GoogleSecrets::getSecretByName rejects a name that is not a full version name', $gs->getSecretByName('synthetic-cache-keys')===false && $gs->errorCode==='params-error');
}
//endregion

//region e) negative: invalid content
section('e) invalid content of the version: no copy, failure marked');
managed();
foreach ([
    'not json' => '{not json',
    'empty' => '',
    'missing iv' => json_encode(['key'=>base64_encode(random_bytes(32))]),
    'short key' => json_encode(['key'=>base64_encode(random_bytes(16)),'iv'=>base64_encode(random_bytes(32))]),
    'not base64' => json_encode(['key'=>str_repeat('*',44),'iv'=>base64_encode(random_bytes(32))]),
    'old format names' => json_encode(['cache_encrypt_key'=>base64_encode(random_bytes(32)),'cache_encrypt_iv'=>base64_encode(random_bytes(32))]),
] as $label=>$content) {
    $dir = new_instance_dir('e-'.md5($label));
    $saved_content = $secret_content;
    $secret_content = $content;
    $reads_before = $reads;
    $core = build(make_app('e-'.md5($label),['core.gcp.secrets.cache_keys_secret'=>VERSION]),$new);
    $secret_content = $saved_content;
    check("invalid content ($label): read once, keys missing", $reads===$reads_before+1 && $core->security->cache_keys_missing===true);
    check("invalid content ($label): no copy, failure marked, unavailable logged once", !file_exists(copy_file($dir)) && is_file(fail_file($dir))
        && count_lines($new,'security-cache-keys-secret-unavailable')===1);
}
//endregion

//region f) negative: corrupt copy of the instance
section('f) a corrupt copy is read again and replaced');
managed();
foreach ([
    'not json' => '{corrupt',
    'invalid pair' => json_encode(['key'=>'short','iv'=>'short']),
] as $label=>$content) {
    $dir = new_instance_dir('f-'.md5($label));
    file_put_contents(copy_file($dir),$content);
    $reads_before = $reads;
    $core = build(make_app('f-'.md5($label),['core.gcp.secrets.cache_keys_secret'=>VERSION]),$new);
    $copy = json_decode((string)@file_get_contents(copy_file($dir)),true);
    check("corrupt copy ($label): read once and the secret keys are used", $reads===$reads_before+1 && uses_secret_keys($core));
    check("corrupt copy ($label): replaced by a valid copy with permissions 0600", ($copy['key']??null)===$secret_keys['key'] && ($copy['iv']??null)===$secret_keys['iv'] && perms(copy_file($dir))==='0600');
    check("corrupt copy ($label): the replacement is logged", count_lines($new,'has been replaced')===1);
}
// a symlink in place of the copy is never followed
$dir = new_instance_dir('f-link');
$target = $tmp_root.'/f-link-target.json';
file_put_contents($target,json_encode(['key'=>base64_encode(random_bytes(32)),'iv'=>base64_encode(random_bytes(32))]));
$target_content = file_get_contents($target);
symlink($target,copy_file($dir));
$reads_before = $reads;
$core = build(make_app('f-link',['core.gcp.secrets.cache_keys_secret'=>VERSION]));
check('symlink in place of the copy: ignored, the secret is read and the link replaced by a regular file', $reads===$reads_before+1 && uses_secret_keys($core)
    && !is_link(copy_file($dir)) && is_file(copy_file($dir)) && file_get_contents($target)===$target_content);
//endregion

//region g) positive: first request and second instance
section('g) first request reads once and writes the copy; the next instance reads nothing');
managed();
$dir = new_instance_dir('g');
$root = make_app('g-first',['core.gcp.secrets.cache_keys_secret'=>VERSION]);
$reads_before = $reads;
$core = build($root,$new);
$copy = json_decode((string)@file_get_contents(copy_file($dir)),true);
check('first request: exactly one read of the exact version', $reads===$reads_before+1 && end($reader_names)===VERSION);
check('first request: CoreSecurity and CoreConfig get the secret keys, no startup error', uses_secret_keys($core));
check('first request: copy written with permissions 0600 and the same keys', is_file(copy_file($dir)) && perms(copy_file($dir))==='0600'
    && ($copy['key']??null)===$secret_keys['key'] && ($copy['iv']??null)===$secret_keys['iv'] && ($copy['version']??null)===VERSION);
check('first request: no temporary files and no failure mark', no_temp_files($dir) && !file_exists(fail_file($dir)));
check('first request: one log line with source=secret-manager-read (syslog info)', count_lines($new,'source=secret-manager-read')===1 && ($new[0][0]??'')==='info');

$reads_before = $reads;
$core2 = build($root,$new);
check('second instance: zero reads', $reads===$reads_before);
check('second instance: the same keys in CoreSecurity and CoreConfig', uses_secret_keys($core2));
check('second instance: one request log line with source=instance-copy, nothing in syslog', request_logs_contain($core2,'source=instance-copy') && count_lines($new,'security-cache-keys-secret')===0);
check('second instance: the copy is unchanged', json_decode(file_get_contents(copy_file($dir)),true)===$copy);

managed('run');
$dir = new_instance_dir('g-run');
$reads_before = $reads;
$core = build($root);
check('Cloud Run (K_SERVICE in the environment): read once and copy written', $reads===$reads_before+1 && uses_secret_keys($core) && is_file(copy_file($dir)));
managed('function');
$dir = new_instance_dir('g-function');
$reads_before = $reads;
$core = build($root);
check('Cloud Functions (FUNCTION_TARGET in $_SERVER): read once and copy written', $reads===$reads_before+1 && uses_secret_keys($core) && is_file(copy_file($dir)));
//endregion

//region h) positive: encrypted round trip
section('h) encrypted round trip of CoreCache, CoreSecurity and CoreConfig with the secret keys');
managed('run'); // Cloud Run: managed runtime with the file cache, so the cache persists between instances
$dir = new_instance_dir('h');
$root = make_app('h-roundtrip',['core.gcp.secrets.cache_keys_secret'=>VERSION],false,true);
$core = build($root);
check('round trip: the instance uses the file cache and the secret keys', $core->cache->type==='directory' && uses_secret_keys($core));
$obj = ['token'=>'synthetic-token-value-h','n'=>42];
$core->cache->set('h-entry',$obj,null,$core->security->cache_key,$core->security->cache_iv);
$reads_before = $reads;
$core_b = build($root);
check('round trip: the next instance reads nothing from Secret Manager', $reads===$reads_before && uses_secret_keys($core_b));
check('CoreCache: written by one instance, read by the next', $core_b->cache->get('h-entry',-1,'',$core_b->security->cache_key,$core_b->security->cache_iv)===$obj);
check('CoreCache: wrong keys do not read it', $core_b->cache->get('h-entry',-1,'','wrong-key','wrong-iv')===null);
$core->security->updateCache('sec-var','synthetic-security-value-h');
$core_c = build($root);
check('CoreSecurity cache: written and read back across instances', $core_c->security->getCache('sec-var')==='synthetic-security-value-h');
$core->config->updateCache('env_vars:h',['SECRET'=>'synthetic-env-secret-h']);
$core_d = build($root);
check('CoreConfig cache: written and read back across instances', ($core_d->config->getCache('env_vars:h')['SECRET']??null)==='synthetic-env-secret-h');
$all = ''; foreach (glob($root.'/cache-dir/*') as $f) $all .= file_get_contents($f).@gzuncompress(file_get_contents($f));
check('cache files never hold the values in plain text', $all!=='' && strpos($all,'synthetic-env-secret-h')===false && strpos($all,'synthetic-security-value-h')===false && strpos($all,'synthetic-token-value-h')===false);
unmanaged();
//endregion

//region i) logs never carry the keys
section('i) no log line carries the key, the iv or any 8-character fragment of them');
$everything = $all_logs.json_encode($syslog_lines,JSON_UNESCAPED_SLASHES);
$leak = false;
foreach ([$secret_keys['key'],$secret_keys['iv']] as $value) {
    for($i=0;$i+8<=strlen($value);$i++) if(strpos($everything,substr($value,$i,8))!==false) { $leak = true; break 2; }
    $escaped = substr(json_encode($value),1,-1); // the same check over the JSON-escaped form ("/" as "\/")
    for($i=0;$i+8<=strlen($escaped);$i++) if(strpos($everything,substr($escaped,$i,8))!==false) { $leak = true; break 2; }
}
check('logs (request logs, errors and syslog of every instance built) free of key and iv fragments', $everything!=='' && !$leak);
check('the source of the keys was logged in the run (secret-manager-read, instance-copy, local-file)', strpos($everything,'source=secret-manager-read')!==false
    && strpos($everything,'source=instance-copy')!==false && strpos($everything,'source=local-file')!==false);
//endregion

//region SUMMARY
CoreSecurity::$cache_keys_secret_reader = null;
CoreSecurity::$cache_keys_instance_dir = '/tmp';
CoreSecurity::$cache_keys_use_apcu = null;
echo "\nversion: ".$core->_version."\n";
echo "PHP warnings/notices captured during the whole run: ".count($php_warnings)."\n";
foreach ($php_warnings as $w) echo "  - $w\n";
echo "\n{$checks} checks, {$failures} failures\n";
exec('rm -rf '.escapeshellarg($tmp_root));
exit($failures?1:0);
//endregion
