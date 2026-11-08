<?php
// CLOUD Documentum:
//   DevGroup: /cf/products/cloud-development
//   DevUnit: /php/classes/Core7/CoreSecurity, /php/classes/Core7/CoreCache, /cloud-development/frameworks/php
//   Activities: 5630949161435136
/**
 * Isolated tests of the per-machine LOCAL cache keys, since 8.5.22 (CoreSecurity::useLocalCacheKeys()).
 *
 * Contract under test: when no cache key is configured (config-vars and env vars), the framework generates or reuses
 * {root_path}/local_data/cache-encrypt.json ONLY when it is not App Engine, Cloud Run nor Cloud Functions, the cache is
 * the file cache and local_data already exists and is writable. Otherwise security-cache-key-missing is kept.
 *
 * Self-contained: every application root is a temporary directory (never a real local_data), no network, no Redis,
 * no vendor/ autoload. The generated keys are only compared, never printed.
 *
 * Run from the repository root:
 *   php tests/CoreSecurityLocalCacheKeys.php
 * Exit code 0 when every check passes, 1 otherwise.
 */

//region ENVIRONMENT
$runtime_vars = ['GAE_SERVICE','K_SERVICE','FUNCTION_TARGET'];
foreach (array_merge(['CF_ENCRYPT_KEY','CF_ENCRYPT_SECRET','CF_CACHE_ENCRYPT_KEY','CF_CACHE_ENCRYPT_IV','PROJECT_ID','REDIS_HOST','REDIS_PORT','GOOGLE_APPLICATION_CREDENTIALS'],$runtime_vars) as $env_var) putenv($env_var);
foreach ($runtime_vars as $v) unset($_SERVER[$v]);

$tmp_root = sys_get_temp_dir().'/cf-core-local-cache-keys-test-'.getmypid();
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

/**
 * Creates a temporary application root. The file cache folder is OUTSIDE local_data, so that the type of cache and the
 * existence of local_data can be tested independently.
 */
function make_app(string $name, array $config = [], bool $with_local_data = true, bool $file_cache = true): string {
    global $tmp_root;
    $root = $tmp_root.'/'.$name;
    @mkdir($root,0700,true);
    if($file_cache) $config['core.cache.cache_path'] = $root.'/cache-dir';
    file_put_contents($root.'/config.json',json_encode($config,JSON_UNESCAPED_SLASHES)); // included by src/config.json
    if($with_local_data) @mkdir($root.'/local_data',0700);
    return $root;
}
function keys_file(string $root): string { return $root.'/local_data/cache-encrypt.json'; }
function perms(string $file): string { clearstatcache(); return substr(sprintf('%o',fileperms($file)),-4); }
function count_syslog(array $lines, string $needle): int {
    $n = 0;
    foreach ($lines as $line) if(strpos($line[1],$needle)!==false) $n++;
    return $n;
}
/** true when no log line (request logs or syslog) contains the value or any 8-character fragment of it */
function logs_free_of(string $value, Core7 $core, array $syslog): bool {
    $all = json_encode($core->logs->data).json_encode($core->errors->data).json_encode($syslog);
    for($i=0;$i+8<=strlen($value);$i++) if(strpos($all,substr($value,$i,8))!==false) return false;
    return true;
}
function reset_security(Core7 $core): CoreSecurity {
    $core->security = new CoreSecurity($core);
    return $core->security;
}
//endregion

//region a) negative: managed runtimes
section('a) managed runtimes never generate');

// full construction with GAE_SERVICE (App Engine): development() is false, nothing is generated
$root = make_app('a-gae');
$_SERVER['GAE_SERVICE'] = 'synthetic-service';
$core = new Core7($root);
check('GAE_SERVICE: no file generated', !file_exists(keys_file($root)));
check('GAE_SERVICE: keys stay missing (security-cache-key-missing)', $core->security->cache_keys_missing===true && is_array($core->getStartupError()));
unset($_SERVER['GAE_SERVICE']);

// full construction with K_SERVICE in the environment (Cloud Run): development() is TRUE and the file cache is active
$root = make_app('a-krun');
putenv('K_SERVICE=synthetic-run-service');
$core = new Core7($root);
check('K_SERVICE: development() is true and the cache is the file cache (the case development() does not cover)', $core->is->development() && $core->cache->type==='directory');
check('K_SERVICE: no file generated', !file_exists(keys_file($root)));
check('K_SERVICE: keys stay missing', $core->security->cache_keys_missing===true && $core->config->cache_secret_key==='');
putenv('K_SERVICE');

// full construction with FUNCTION_TARGET in the environment (Cloud Functions)
$root = make_app('a-func');
putenv('FUNCTION_TARGET=syntheticFunction');
$core = new Core7($root);
check('FUNCTION_TARGET: no file generated', !file_exists(keys_file($root)));
check('FUNCTION_TARGET: keys stay missing', $core->security->cache_keys_missing===true);
putenv('FUNCTION_TARGET');

// isolated: every other condition holds, only the runtime variable blocks (in $_SERVER and in getenv())
$root = make_app('a-isolated',[],false);         // without local_data the constructor generates nothing
$core = new Core7($root);
mkdir($root.'/local_data',0700);                 // now everything holds except the runtime variable under test
foreach ($runtime_vars as $v) {
    $_SERVER[$v] = 'synthetic';
    check("$v in \$_SERVER: useLocalCacheKeys() refuses", reset_security($core)->useLocalCacheKeys()===false && !file_exists(keys_file($root)));
    unset($_SERVER[$v]);
    putenv("$v=synthetic");
    check("$v in getenv(): useLocalCacheKeys() refuses", reset_security($core)->useLocalCacheKeys()===false && !file_exists(keys_file($root)));
    putenv($v);
}
check('control: without the runtime variables the same core generates', reset_security($core)->useLocalCacheKeys()===true && is_file(keys_file($root)));
//endregion

//region b) negative: cache that is not the file cache
section('b) cache that is not the file cache');
$root = make_app('b-memory',[],true,false);
$core = new Core7($root);
check('memory/Redis cache type: the cache is not directory', $core->cache->type!=='directory');
check('memory/Redis cache type: no file generated, keys missing', !file_exists(keys_file($root)) && $core->security->cache_keys_missing===true);

$root = make_app('b-isolated',[],false);
$core = new Core7($root);
mkdir($root.'/local_data',0700);
$core->cache->activateMemory();
check('isolated: cache switched to memory -> refuses', reset_security($core)->useLocalCacheKeys()===false && !file_exists(keys_file($root)));
$core->cache->type = 'datastore'; // no vendor/ here: the type alone is what useLocalCacheKeys() looks at
check('isolated: cache of type datastore -> refuses', reset_security($core)->useLocalCacheKeys()===false && !file_exists(keys_file($root)));
//endregion

//region c) negative: local_data
section('c) local_data absent or not writable');
$root = make_app('c-absent',[],false);
$core = new Core7($root);
check('local_data absent: the cache is the file cache (only local_data is missing)', $core->cache->type==='directory');
check('local_data absent: not created by the framework', !is_dir($root.'/local_data'));
check('local_data absent: keys missing', $core->security->cache_keys_missing===true);

$root = make_app('c-readonly');
chmod($root.'/local_data',0500);
$core = new Core7($root);
$is_root_user = function_exists('posix_geteuid') && posix_geteuid()===0;
check('local_data not writable: no file, keys missing'.($is_root_user?' (running as root: is_writable is always true)':''), $is_root_user || (!file_exists(keys_file($root)) && $core->security->cache_keys_missing===true));
chmod($root.'/local_data',0700);
//endregion

//region d) negative: keys already configured
section('d) keys already in the configuration or the environment');
$root = make_app('d-config',['core.gcp.secrets.cache_encrypt_key'=>'cfg-cache-key-synthetic','core.gcp.secrets.cache_encrypt_iv'=>'cfg-cache-iv-synthetic']);
$core = new Core7($root);
check('keys in config: used, no file generated', $core->security->cache_key==='cfg-cache-key-synthetic' && $core->config->cache_secret_key==='cfg-cache-key-synthetic' && !file_exists(keys_file($root)));

$root = make_app('d-half',['core.gcp.secrets.cache_encrypt_key'=>'cfg-cache-key-synthetic']);
$core = new Core7($root);
check('only the key in config (half pair): not completed with a generated value, keys missing', !file_exists(keys_file($root)) && $core->security->cache_keys_missing===true);

$root = make_app('d-env');
putenv('CF_CACHE_ENCRYPT_KEY=env-cache-key-synthetic'); putenv('CF_CACHE_ENCRYPT_IV=env-cache-iv-synthetic');
$core = new Core7($root);
check('keys in the environment: used, no file generated', $core->security->cache_key==='env-cache-key-synthetic' && $core->config->cache_secret_iv==='env-cache-iv-synthetic' && !file_exists(keys_file($root)));
putenv('CF_CACHE_ENCRYPT_KEY'); putenv('CF_CACHE_ENCRYPT_IV');

// the configuration also wins over an existing local file
$root = make_app('d-config-over-file',['core.gcp.secrets.cache_encrypt_key'=>'cfg-cache-key-synthetic','core.gcp.secrets.cache_encrypt_iv'=>'cfg-cache-iv-synthetic']);
file_put_contents(keys_file($root),json_encode(['key'=>base64_encode(random_bytes(32)),'iv'=>base64_encode(random_bytes(32))]));
$core = new Core7($root);
check('keys in config win over local_data/cache-encrypt.json', $core->security->cache_key==='cfg-cache-key-synthetic');
//endregion

//region e) negative: invalid file
section('e) invalid local_data/cache-encrypt.json is never overwritten');
$invalid_contents = [
    'not json' => '{not json',
    'empty' => '',
    'missing iv' => json_encode(['key'=>base64_encode(random_bytes(32))]),
    'short key' => json_encode(['key'=>base64_encode(random_bytes(16)),'iv'=>base64_encode(random_bytes(32))]),
    'not base64' => json_encode(['key'=>str_repeat('*',44),'iv'=>base64_encode(random_bytes(32))]),
];
foreach ($invalid_contents as $label=>$content) {
    $root = make_app('e-'.md5($label));
    file_put_contents(keys_file($root),$content);
    $syslog_before = count($syslog_lines);
    $core = new Core7($root);
    $new_lines = array_slice($syslog_lines,$syslog_before);
    check("invalid file ($label): not overwritten", file_get_contents(keys_file($root))===$content);
    check("invalid file ($label): keys missing, startup error kept", $core->security->cache_keys_missing===true && is_array($core->getStartupError()));
    check("invalid file ($label): error logged once", count_syslog($new_lines,'security-cache-key-local-file-invalid')===1);
}
//endregion

//region f) positive: generation, permissions, reuse
section('f) generation and reuse');
$root = make_app('f-generate');
$syslog_before = count($syslog_lines);
$core = new Core7($root);
$new_lines = array_slice($syslog_lines,$syslog_before);
$file = keys_file($root);
$stored = json_decode((string)@file_get_contents($file),true);
check('generated: local_data/cache-encrypt.json exists', is_file($file));
check('generated: permissions 0600', perms($file)==='0600', 'perms='.perms($file));
check('generated: key and iv are the base64 of 32 random bytes', strlen((string)base64_decode($stored['key']??'',true))===32 && strlen((string)base64_decode($stored['iv']??'',true))===32 && $stored['key']!==$stored['iv']);
check('generated: CoreSecurity uses the file keys', $core->security->cache_key===$stored['key'] && $core->security->cache_iv===$stored['iv'] && $core->security->cache_keys_missing===false);
check('generated: CoreConfig uses the same keys', $core->config->cache_secret_key===$stored['key'] && $core->config->cache_secret_iv===$stored['iv']);
check('generated: no startup error', $core->getStartupError()===null);
check('generated: one log line', count_syslog($new_lines,'security-cache-key-local-generated')===1 && count_syslog($new_lines,'security-cache-key-local')===1);
check('generated: no security-cache-key-missing', count_syslog($new_lines,'security-cache-key-missing')===0);
check('generated: logs carry neither the key nor the iv nor any fragment', logs_free_of($stored['key'],$core,$syslog_lines) && logs_free_of($stored['iv'],$core,$syslog_lines));
check('generated: no temporary file left in local_data', count(glob($root.'/local_data/.cache-encrypt.json.*'))===0 && count(glob($root.'/local_data/*'))===1);

$syslog_before = count($syslog_lines);
$core2 = new Core7($root);
$new_lines = array_slice($syslog_lines,$syslog_before);
check('second instance: same keys reused', $core2->security->cache_key===$stored['key'] && $core2->security->cache_iv===$stored['iv']);
check('second instance: file unchanged', json_decode(file_get_contents($file),true)===$stored);
check('second instance: one "used" log line, no "generated"', count_syslog($new_lines,'security-cache-key-local-used')===1 && count_syslog($new_lines,'security-cache-key-local-generated')===0);

// first process wins: a second write never replaces the file
$m = new ReflectionMethod('CoreSecurity','writeLocalCacheKeysFile');
$before = file_get_contents($file);
check('first wins: a second write returns false and leaves the file as it was', $m->invoke($core2->security,$root.'/local_data',$file)===false && file_get_contents($file)===$before && count(glob($root.'/local_data/.cache-encrypt.json.*'))===0);

// concurrent processes on a fresh root: all of them end with the keys of the single file
$root = make_app('f-race');
$child = $tmp_root.'/race-child.php';
for($i=0;$i<6;$i++) @mkdir($tmp_root.'/race-sessions/'.$i,0700,true); // one session folder per child: the terminal session of Core7 is locked per folder, and the parent holds its own
file_put_contents($child, '<?php ob_start(); ini_set("session.save_path",'.var_export($tmp_root.'/race-sessions',true).'."/".$argv[1]); include_once '.var_export(realpath(__DIR__.'/../src/Core7.php'),true).';'
    .' $core = new Core7('.var_export($root,true).'); $f = json_decode((string)@file_get_contents('.var_export(keys_file($root),true).'),true);'
    .' $r = (!$core->security->cache_keys_missing && $core->security->cache_key===($f["key"]??null) && $core->security->cache_iv===($f["iv"]??null))?"SAME":"DIFFERENT"; ob_end_clean(); echo $r;');
$env = ['PATH'=>getenv('PATH'),'HOME'=>getenv('HOME'),'PWD'=>$root];
$procs = [];
for($i=0;$i<6;$i++) {                                   // all started before any is read: they run concurrently
    $pipes = [];
    $proc = proc_open(['php',$child,(string)$i],[1=>['pipe','w'],2=>['pipe','w']],$pipes,null,$env);
    $procs[] = [$proc,$pipes];
}
$results = [];
foreach ($procs as [$proc,$pipes]) {
    if(!is_resource($proc)) { $results[] = 'NOPROC'; continue; }
    $results[] = trim(stream_get_contents($pipes[1]));
    stream_get_contents($pipes[2]);
    fclose($pipes[1]); fclose($pipes[2]);
    proc_close($proc);
}
check('concurrent processes: every process uses the keys of the single file', count($results)===6 && count(array_unique($results))===1 && $results[0]==='SAME', implode(',',$results));
check('concurrent processes: one file with permissions 0600 and no temporary files', is_file(keys_file($root)) && perms(keys_file($root))==='0600' && count(glob($root.'/local_data/.cache-encrypt.json.*'))===0);
//endregion

//region g) positive: cache round trip with the generated keys
section('g) encrypted cache round trip with the generated keys');
$root = make_app('g-roundtrip');
$core = new Core7($root);
$obj = ['token'=>'synthetic-token-value-g','n'=>42];
$core->cache->set('g-entry',$obj,null,$core->security->cache_key,$core->security->cache_iv);
$core_b = new Core7($root);
check('CoreCache: written by one instance, read by the next with the reused keys', $core_b->cache->get('g-entry',-1,'',$core_b->security->cache_key,$core_b->security->cache_iv)===$obj);
check('CoreCache: wrong keys do not read it', $core_b->cache->get('g-entry',-1,'','wrong-key','wrong-iv')===null);

$core->security->updateCache('sec-var','synthetic-security-value-g');
$core_c = new Core7($root);
check('CoreSecurity cache: written and read back across instances', $core_c->security->getCache('sec-var')==='synthetic-security-value-g');

$core->config->updateCache('env_vars:g',['SECRET'=>'synthetic-env-secret-g']);
$core_d = new Core7($root);
check('CoreConfig cache: written and read back across instances', ($core_d->config->getCache('env_vars:g')['SECRET']??null)==='synthetic-env-secret-g');
$all = ''; foreach (glob($root.'/cache-dir/*') as $f) $all .= file_get_contents($f).@gzuncompress(file_get_contents($f));
check('cache files never hold the values in plain text', $all!=='' && strpos($all,'synthetic-env-secret-g')===false && strpos($all,'synthetic-security-value-g')===false && strpos($all,'synthetic-token-value-g')===false);
//endregion

//region SUMMARY
echo "\nversion: ".$core->_version."\n";
echo "PHP warnings/notices captured during the whole run: ".count($php_warnings)."\n";
foreach ($php_warnings as $w) echo "  - $w\n";
echo "\n{$checks} checks, {$failures} failures\n";
exec('rm -rf '.escapeshellarg($tmp_root));
exit($failures?1:0);
//endregion
