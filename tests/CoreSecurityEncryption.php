<?php
/**
 * Isolated tests of the CoreSecurity encryption formats (v1 AES-256-CBC / v2 AES-256-GCM), the key
 * resolution order, CoreCache encryption and the encrypted daily subkeys cache. Since 8.5.25 there are no
 * built-in default keys: encrypt() without keys fails and v1 is read only with configured or legacy keys (section l).
 *
 * Self-contained: synthetic keys only, no network, no Redis (CoreCache in a temporary directory),
 * no vendor/ autoload and no config.json of an application.
 *
 * Run from the repository root:
 *   php tests/CoreSecurityEncryption.php
 * Exit code 0 when every check passes, 1 otherwise.
 */

//region ENVIRONMENT: clean env vars, temporary root and a logger that captures syslog warnings
foreach (['CF_ENCRYPT_KEY','CF_ENCRYPT_SECRET','CF_LEGACY_ENCRYPT_KEY','CF_LEGACY_ENCRYPT_SECRET','CF_CACHE_ENCRYPT_KEY','CF_CACHE_ENCRYPT_IV','PROJECT_ID','REDIS_HOST','REDIS_PORT','GOOGLE_APPLICATION_CREDENTIALS'] as $env_var) putenv($env_var);
unset($_SERVER['GAE_SERVICE']);

$tmp_root = sys_get_temp_dir().'/cf-core-encryption-test-'.getmypid();
@mkdir($tmp_root.'/local_data/cache',0700,true);
file_put_contents($tmp_root.'/config.json','{}'); // app config.json included by src/config.json
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
function flush_output(): void { if(ob_get_level()) { ob_end_flush(); } }

/** Literal copy of the v1 CBC of encrypt() (the reference for byte-to-byte compatibility). Since 8.5.25 it has no
 *  built-in default keys: the keys come from the arguments or from the config values passed in $config_* */
function legacy_encrypt($text, $secret_key, $secret_iv, $config_key = null, $config_secret = null) {
    if(!$text) return $text;
    $encrypt_method = "AES-256-CBC";
    if(!$secret_key)
        $secret_key = (string)$config_key;
    if(!$secret_iv)
        $secret_iv = (string)$config_secret;
    $key = hash('sha256', $secret_key);
    $iv = substr(hash('sha256', $secret_iv), 0, 16);
    return openssl_encrypt($text, $encrypt_method, $key, 0, $iv);
}

/** Spy on getEnvVar(): counts calls and serves values from an array (no getenv, no GCP) */
class SpyConfig extends CoreConfig {
    public $env_calls = [];
    public $env_values = [];
    public $on_get_env_var = null;
    public function getEnvVar($var, $gcp_project_id = '', $gcp_secret_id = '') {
        $this->env_calls[] = $var;
        if(is_callable($this->on_get_env_var)) ($this->on_get_env_var)($var);
        return $this->env_values[$var] ?? null;
    }
    /** env_vars secret reload (8.5.14): never GCP; counts calls and delegates to $on_reload */
    public $reload_calls = [];
    public $on_reload = null;
    public function readEnvVarsFromGCPSecrets($gpc_project_id = '', $gpc_secret_id = '', $reload = false) {
        $this->reload_calls[] = (bool)$reload;
        return is_callable($this->on_reload) ? ($this->on_reload)($this) : false;
    }
}

function new_core(string $root): Core7 {
    $core = new Core7($root);
    return $core;
}
/** New CoreSecurity after changing config (the constructor reads the config) */
function reset_security(Core7 $core): CoreSecurity {
    $core->security = new CoreSecurity($core);
    return $core->security;
}
function count_logs(Core7 $core, string $needle): int {
    $n = 0;
    foreach ($core->logs->data as $line) if(strpos(is_string($line)?$line:json_encode($line),$needle)!==false) $n++;
    return $n;
}
function count_syslog(array $lines, string $needle): int {
    $n = 0;
    foreach ($lines as $line) if(strpos($line[1],$needle)!==false) $n++;
    return $n;
}
//endregion

$texts = ['a','hello world','{"json":true,"n":123}',str_repeat('0123456789abcdef',700),"línea con acentos ñ €",random_bytes(64),gzcompress(serialize(['x'=>1,'y'=>[1,2,3]]))];
$key_pairs = [
    ['synthetic-key-1','synthetic-iv-1'],
    ['k','i'],
    [str_repeat('K',80),str_repeat('I',80)],
    ['synthetic-key-2__daily-subkey','synthetic-iv-2__daily-subiv'],
];

$core = new_core($tmp_root); // first Core7: starts the session before any output
flush_output();

//region a) encrypt() without opt-in == previous CBC implementation, byte to byte
section('a) encrypt() without opt-in produces exactly the previous CBC output');
$core = new_core($tmp_root);
$sec = reset_security($core);
$same = true; $detail = '';
foreach ($texts as $t) foreach ($key_pairs as [$k,$i]) {
    if($sec->encrypt($t,$k,$i)!==legacy_encrypt($t,$k,$i)) { $same = false; $detail = "explicit keys [$k]"; }
}
check('explicit keys: '.(count($texts)*count($key_pairs)).' combinations identical', $same, $detail);

$same = true;
foreach ($texts as $t) if($sec->encrypt($t)!==false) $same = false;
check('no keys, no config, no env: encrypt() returns false (no built-in default since 8.5.25)', $same);

$same = true;
foreach ($texts as $t) if($sec->encrypt($t,'only-key-synthetic')!==false) $same = false;
check('explicit key + no iv anywhere: false', $same);

$core->config->set('core.security.encrypt_secret','cfg-secret-synthetic');
$same = true;
foreach ($texts as $t) if($sec->encrypt($t,'only-key-synthetic')!==legacy_encrypt($t,'only-key-synthetic','',null,'cfg-secret-synthetic')) $same = false;
check('explicit key + iv resolved from config: identical', $same);

$core->config->set('core.security.encrypt_key','cfg-key-synthetic');
$core->config->set('core.security.encrypt_secret','cfg-secret-synthetic');
$same = true;
foreach ($texts as $t) if($sec->encrypt($t)!==legacy_encrypt($t,'','','cfg-key-synthetic','cfg-secret-synthetic')) $same = false;
check('config keys: identical', $same);

foreach (['v1','V2','',null,'2'] as $format) {
    $core->config->set('core.security.encrypt_format',$format);
    $out = $sec->encrypt('hello world','synthetic-key-1','synthetic-iv-1');
    check('encrypt_format='.var_export($format,true).' still writes CBC', $out===legacy_encrypt('hello world','synthetic-key-1','synthetic-iv-1'));
}
check('empty text is returned unchanged', $sec->encrypt('','k','i')==='' && $sec->encrypt(null,'k','i')===null);
//endregion

//region b) round trip v1 and v2
section('b) round trip v1 and v2');
$core = new_core($tmp_root);
$sec = reset_security($core);
$ok_v1 = true; $ok_v2 = true; $ok_v2_cfg = true;
foreach ($texts as $t) foreach ($key_pairs as [$k,$i]) {
    if($sec->decrypt($sec->encrypt($t,$k,$i),$k,$i)!==$t) $ok_v1 = false;
    $v2 = $sec->encryptV2($t,$k,$i);
    if(strncmp($v2,'v2:',3)!==0 || $sec->decrypt($v2,$k,$i)!==$t || $sec->decryptV2($v2,$k,$i)!==$t) $ok_v2 = false;
}
check('v1 round trip', $ok_v1);
check('v2 round trip (encryptV2 + decrypt/decryptV2)', $ok_v2);
$core->config->set('core.security.encrypt_format','v2');
$core->config->set('core.security.encrypt_key','cfg-key-synthetic');
$core->config->set('core.security.encrypt_secret','cfg-secret-synthetic');
foreach ($texts as $t) {
    $enc = $sec->encrypt($t);
    if(strncmp($enc,'v2:',3)!==0 || $sec->decrypt($enc)!==$t) $ok_v2_cfg = false;
}
check('encrypt_format=v2: encrypt() writes v2 and decrypt() reads it (resolved keys)', $ok_v2_cfg);
$v1_old = legacy_encrypt('stored before 8.5.12','synthetic-key-1','synthetic-iv-1');
check('encrypt_format=v2: a v1 value stored before is still readable', $sec->decrypt($v1_old,'synthetic-key-1','synthetic-iv-1')==='stored before 8.5.12');
$v2_raw = base64_decode(substr($sec->encryptV2('x','k','i'),3),true);
check('v2 layout: 12 iv + 16 tag + ciphertext', strlen($v2_raw)===12+16+1);
check('v2 key: HKDF-SHA256(key."\0".iv, 32, cloudframework-encrypt-v2) decrypts it independently',
    openssl_decrypt(substr($v2_raw,28),'aes-256-gcm',hash_hkdf('sha256',"k\0i",32,'cloudframework-encrypt-v2'),OPENSSL_RAW_DATA,substr($v2_raw,0,12),substr($v2_raw,12,16))==='x');
check('v2: the secret_iv is part of the key (other iv does not decrypt)', $sec->decryptV2($sec->encryptV2('x','k','i'),'k','other-iv')===false);
//endregion

//region c) two v2 encryptions of the same text differ and both decrypt
section('c) v2 is not deterministic');
$e1 = $sec->encryptV2('same text','synthetic-key-1','synthetic-iv-1');
$e2 = $sec->encryptV2('same text','synthetic-key-1','synthetic-iv-1');
check('two v2 outputs differ', $e1!==$e2);
check('both decrypt', $sec->decrypt($e1,'synthetic-key-1','synthetic-iv-1')==='same text' && $sec->decrypt($e2,'synthetic-key-1','synthetic-iv-1')==='same text');
//endregion

//region d) tampered v2 returns false without PHP warnings
section('d) tampered v2 returns false without PHP warnings');
$warnings_before = count($php_warnings);
$k='synthetic-key-1'; $i='synthetic-iv-1';
$good = $sec->encryptV2('sensitive synthetic payload',$k,$i);
$b64 = substr($good,3);
$raw = base64_decode($b64,true);
// one byte of the base64 text (ciphertext area)
$pos = strlen($b64)-6;
$flip_char = $b64; $flip_char[$pos] = ($b64[$pos]==='A')?'B':'A';
$flip_tag = $raw; $flip_tag[14] = chr(ord($raw[14]) ^ 0x01);
$flip_iv = $raw; $flip_iv[0] = chr(ord($raw[0]) ^ 0x80);
$flip_ct = $raw; $flip_ct[30] = chr(ord($raw[30]) ^ 0x01);
$cases = [
    'one base64 char changed' => 'v2:'.$flip_char,
    'tag byte changed' => 'v2:'.base64_encode($flip_tag),
    'iv byte changed' => 'v2:'.base64_encode($flip_iv),
    'ciphertext byte changed' => 'v2:'.base64_encode($flip_ct),
    'truncated ciphertext' => 'v2:'.base64_encode(substr($raw,0,strlen($raw)-3)),
    'truncated below 28 bytes' => 'v2:'.base64_encode(substr($raw,0,27)),
    'exactly 28 bytes (no ciphertext)' => 'v2:'.base64_encode(substr($raw,0,28)),
    'corrupted base64' => 'v2:@@@not*base64###',
    'empty after prefix' => 'v2:',
    'wrong key' => $good,
];
foreach ($cases as $name=>$value) {
    $res = ($name==='wrong key')?$sec->decrypt($value,'other-key','other-iv'):$sec->decrypt($value,$k,$i);
    check($name.' -> false', $res===false, 'got '.var_export($res,true));
}
check('decryptV2() of a non v2 text -> false', $sec->decryptV2(legacy_encrypt('x',$k,$i),$k,$i)===false);
check('no PHP warnings/notices in d)', count($php_warnings)===$warnings_before, implode("\n",array_slice($php_warnings,$warnings_before)));
//endregion

//region e) legacy CBC still readable, note only once per request
section('e) legacy CBC read and its note once per request');
$core = new_core($tmp_root);
$sec = reset_security($core);
$old = legacy_encrypt('legacy value','synthetic-key-1','synthetic-iv-1');
$r1 = $sec->decrypt($old,'synthetic-key-1','synthetic-iv-1');
$r2 = $sec->decrypt($old,'synthetic-key-1','synthetic-iv-1');
$core->config->set('core.security.legacy_encrypt_key','legacy-key-synthetic');
$core->config->set('core.security.legacy_encrypt_secret','legacy-secret-synthetic');
$r3 = $sec->decrypt(legacy_encrypt('legacy default','legacy-key-synthetic','legacy-secret-synthetic'));
check('legacy CBC decrypts (explicit and legacy keys)', $r1==='legacy value' && $r2==='legacy value' && $r3==='legacy default');
check('note legacy-cbc-format-read logged exactly once', count_logs($core,'legacy-cbc-format-read')===1, 'count='.count_logs($core,'legacy-cbc-format-read'));
check('the legacy note is not sent to syslog', count_syslog($syslog_lines,'legacy-cbc-format-read')===0);
$core = new_core($tmp_root);
$sec = reset_security($core);
$sec->decrypt($sec->encryptV2('only v2','k','i'),'k','i');
check('reading only v2 leaves no legacy note', count_logs($core,'legacy-cbc-format-read')===0);
//endregion

//region f) key resolution
section('f) key resolution order and recursion');
// f.1 config first
$core = new_core($tmp_root);
$spy = new SpyConfig($core, __DIR__.'/../src/config.json');
$core->config = $spy;
$spy->set('core.security.encrypt_key','cfg-key-synthetic');
$spy->set('core.security.encrypt_secret','cfg-secret-synthetic');
$spy->env_values = ['CF_ENCRYPT_KEY'=>'env-key-synthetic','CF_ENCRYPT_SECRET'=>'env-secret-synthetic'];
$sec = reset_security($core);
$enc = $sec->encrypt('abc');
check('config keys win over env vars', $enc===legacy_encrypt('abc','cfg-key-synthetic','cfg-secret-synthetic'));
check('with config keys getEnvVar() is never called', count($spy->env_calls)===0, implode(',',$spy->env_calls));

// f.2 env vars (getEnvVar) when no config
$core = new_core($tmp_root);
$spy = new SpyConfig($core, __DIR__.'/../src/config.json');
$core->config = $spy;
$spy->env_values = ['CF_ENCRYPT_KEY'=>'env-key-synthetic','CF_ENCRYPT_SECRET'=>'env-secret-synthetic'];
$sec = reset_security($core);
$syslog_before = count($syslog_lines);
$e1 = $sec->encrypt('abc'); $e2 = $sec->encrypt('def'); $d = $sec->decrypt($e1);
check('env vars used when there is no config', $e1===legacy_encrypt('abc','env-key-synthetic','env-secret-synthetic') && $d==='abc');
check('getEnvVar() called once per variable and memorized', $spy->env_calls===['CF_ENCRYPT_KEY','CF_ENCRYPT_SECRET'], implode(',',$spy->env_calls));
check('no default-key warning when env vars are used', count_syslog(array_slice($syslog_lines,$syslog_before),'security-default-encrypt-key-used')===0);

// f.3 decrypt/encrypt with explicit keys never calls getEnvVar
$spy->env_calls = [];
$sec2 = reset_security($core);
$sec2->decrypt($sec2->encrypt('abc','explicit-key','explicit-iv'),'explicit-key','explicit-iv');
$sec2->decrypt($sec2->encryptV2('abc','explicit-key','explicit-iv'),'explicit-key','explicit-iv');
check('explicit keys: getEnvVar() not called', count($spy->env_calls)===0, implode(',',$spy->env_calls));

// f.4 only getenv() (real CoreConfig::getEnvVar)
putenv('CF_ENCRYPT_KEY=getenv-key-synthetic');
putenv('CF_ENCRYPT_SECRET=getenv-secret-synthetic');
$core = new_core($tmp_root);
$sec = reset_security($core);
check('only getenv(): env vars used', $sec->encrypt('abc')===legacy_encrypt('abc','getenv-key-synthetic','getenv-secret-synthetic'));
putenv('CF_ENCRYPT_KEY'); putenv('CF_ENCRYPT_SECRET');

// f.5 env_vars secret already loaded in config data (getEnvVar second source, no GCP call)
$core = new_core($tmp_root);
$core->config->data['env_vars'] = ['CF_ENCRYPT_KEY'=>'secret-env-key-synthetic','CF_ENCRYPT_SECRET'=>'secret-env-secret-synthetic'];
$sec = reset_security($core);
check('env_vars secret (already loaded) used', $sec->encrypt('abc')===legacy_encrypt('abc','secret-env-key-synthetic','secret-env-secret-synthetic'));

// f.6 nothing: explicit failure + one error per request (no built-in default since 8.5.25)
$core = new_core($tmp_root);
$sec = reset_security($core);
$syslog_before = count($syslog_lines);
$e = $sec->encrypt('abc'); $d = $sec->decrypt('v2:AAAA'); $sec->encrypt('xyz');
$new_lines = array_slice($syslog_lines,$syslog_before);
check('nothing configured: encrypt() and decrypt() of v2 return false', $e===false && $d===false);
check('error security-encrypt-key-missing sent once (syslog error)', count_syslog($new_lines,'security-encrypt-key-missing')===1 && ($new_lines[0][0]??'')==='error', json_encode($new_lines));
check('no security-default-encrypt-key-used warning any more', count_syslog($new_lines,'security-default-encrypt-key-used')===0);

// f.7 recursion: getEnvVar() that encrypts/decrypts without keys and with explicit keys (as CoreCache does)
$core = new_core($tmp_root);
$spy = new SpyConfig($core, __DIR__.'/../src/config.json');
$core->config = $spy;
$sec = reset_security($core);
$depth = 0; $max_depth = 0;
$spy->on_get_env_var = function($var) use ($core, &$depth, &$max_depth) {
    $depth++; $max_depth = max($max_depth,$depth);
    $core->security->decrypt($core->security->encrypt('inner','explicit-key','explicit-iv'),'explicit-key','explicit-iv');
    $core->security->encrypt('inner without keys');
    $depth--;
};
$spy->env_values = ['CF_ENCRYPT_KEY'=>'env-key-synthetic','CF_ENCRYPT_SECRET'=>'env-secret-synthetic'];
$out = $sec->encrypt('outer');
check('re-entrant encrypt() inside getEnvVar() does not recurse', $max_depth===1, 'max_depth='.$max_depth.' calls='.implode(',',$spy->env_calls));
check('outer value still uses the env keys (re-entrant default not memorized)', $out===legacy_encrypt('outer','env-key-synthetic','env-secret-synthetic'));

// f.8 cache keys: config -> getenv, no built-in default since 8.5.13
putenv('CF_CACHE_ENCRYPT_KEY=env-cache-key-synthetic');
putenv('CF_CACHE_ENCRYPT_IV=env-cache-iv-synthetic');
$core = new_core($tmp_root);
check('CoreSecurity cache keys from getenv()', $core->security->cache_key==='env-cache-key-synthetic' && $core->security->cache_iv==='env-cache-iv-synthetic' && !$core->security->cache_keys_missing);
check('CoreConfig cache keys from getenv()', $core->config->cache_secret_key==='env-cache-key-synthetic' && $core->config->cache_secret_iv==='env-cache-iv-synthetic');
$core->config->set('core.gcp.secrets.cache_encrypt_key','cfg-cache-key-synthetic');
$core->config->set('core.gcp.secrets.cache_encrypt_iv','cfg-cache-iv-synthetic');
$sec = reset_security($core);
check('CoreSecurity cache keys: config wins over getenv()', $sec->cache_key==='cfg-cache-key-synthetic' && $sec->cache_iv==='cfg-cache-iv-synthetic');
putenv('CF_CACHE_ENCRYPT_KEY'); putenv('CF_CACHE_ENCRYPT_IV');
$rc = new ReflectionClass('CoreSecurity');
check('no built-in default cache constants in CoreSecurity', !$rc->hasConstant('DEFAULT_CACHE_ENCRYPT_KEY') && !$rc->hasConstant('DEFAULT_CACHE_ENCRYPT_IV'));
check('cache_key_is_default / cache_iv_is_default removed', !$rc->hasProperty('cache_key_is_default') && !$rc->hasProperty('cache_iv_is_default'));

// nothing configured: empty keys, flagged, encrypted cache neither read nor written
$core = new_core($tmp_root);
check('CoreSecurity cache keys: empty and flagged when nothing', $core->security->cache_key==='' && $core->security->cache_iv==='' && $core->security->cache_keys_missing===true);
check('CoreConfig cache keys: empty when nothing', $core->config->cache_secret_key==='' && $core->config->cache_secret_iv==='');
$f8_dir = $tmp_root.'/local_data/cache/f8';
$core->cache->activateCacheFile($f8_dir);
// a plain entry left by an old version must not be read
$core->cache->set('Core7.CoreSecurity.default',['leaked'=>'plain-value-synthetic']);
$files_before = count(glob($f8_dir.'/*'));
$sec = reset_security($core);
$syslog_before = count($syslog_lines);
check('missing keys: a plain CoreSecurity entry is not read', $sec->getCache('leaked')===null);
$sec->updateCache('a',1); $sec->updateCache('b',2,'other'); $sec->resetCache('x');
check('missing keys: values still usable inside the request', $sec->getCache('a')===1);
check('missing keys: CoreSecurity writes nothing to the cache', count(glob($f8_dir.'/*'))===$files_before);
check('error security-cache-key-missing sent once (CoreSecurity)', count_syslog(array_slice($syslog_lines,$syslog_before),'security-cache-key-missing: set core.gcp.secrets.cache_encrypt_key/cache_encrypt_iv or the env vars CF_CACHE_ENCRYPT_KEY/CF_CACHE_ENCRYPT_IV. The encrypted cache of CoreSecurity')===1);
check('no security-default-cache-key-used warning any more', count_syslog(array_slice($syslog_lines,$syslog_before),'security-default-cache-key-used')===0);

// only one of the pair configured is the same as nothing
$core->config->set('core.gcp.secrets.cache_encrypt_key','cfg-cache-key-synthetic');
$core->config->set('core.gcp.secrets.cache_encrypt_iv','');
$sec = reset_security($core);
check('CoreSecurity cache: key without iv -> flagged missing', $sec->cache_keys_missing===true);
$core->config->set('core.gcp.secrets.cache_encrypt_key','');

// CoreConfig (holds env_vars): without keys never stored, not even in plain text
$core->cache->set('Core7.CoreConfig',['env_vars'=>'plain-env-synthetic']);
$core->config->cache = null;
$files_before = count(glob($f8_dir.'/*'));
$syslog_before = count($syslog_lines);
check('missing keys: a plain CoreConfig entry is not read', $core->config->getCache('env_vars')===null);
$core->config->updateCache('env_vars:x',['SECRET'=>'env-secret-value-synthetic']);
$core->config->resetCache();
$core->config->updateCache('k',1);
check('missing keys: CoreConfig value usable inside the request', $core->config->getCache('k')===1);
check('missing keys: CoreConfig writes nothing to the cache', count(glob($f8_dir.'/*'))===$files_before);
$all_f8 = ''; foreach (glob($f8_dir.'/*') as $f) $all_f8 .= file_get_contents($f).@gzuncompress(file_get_contents($f));
check('missing keys: the env secret is nowhere in the cache files', strpos($all_f8,'env-secret-value-synthetic')===false);
check('error security-cache-key-missing logged once (CoreConfig)', count_syslog(array_slice($syslog_lines,$syslog_before),'The cache of CoreConfig (env_vars included) is neither read nor written')===1);

// CoreConfig with keys: written, encrypted and read back
$core->config->cache_secret_key = 'cfg-cache-key-synthetic'; $core->config->cache_secret_iv = 'cfg-cache-iv-synthetic';
$core->config->cache = null;
$core->config->updateCache('env_vars:y',['SECRET'=>'env-secret-value-synthetic']);
$core->config->cache = null;
check('with keys: CoreConfig cache read back in the next request', ($core->config->getCache('env_vars:y')['SECRET']??null)==='env-secret-value-synthetic');
$cfg_file = $f8_dir.'/'.preg_replace('/[\/\.;]/','_',$core->cache->spacename.'-Core7.CoreConfig');
$raw = file_get_contents($cfg_file); $dec = @gzuncompress($raw);
check('with keys: CoreConfig cache stored encrypted', is_file($cfg_file) && strpos($raw.$dec,'env-secret-value-synthetic')===false);

// daily subkeys without cache keys: endpoint called, nothing cached
$core = new_core($tmp_root);
$core->cache->activateCacheFile($tmp_root.'/local_data/cache/f8-subkeys');
$sk_calls = 0;
$core->request = new class($core,['success'=>true,'data'=>['key'=>'SYNTHSUBKEYf8','iv'=>'SYNTHSUBIVf8']],$sk_calls) extends CoreRequest {
    private $payload; private $calls;
    function __construct(Core7 &$core, $payload, &$calls) { parent::__construct($core); $this->payload = $payload; $this->calls = &$calls; }
    public function get_json_decode(string $route, $data = null, $extra_headers = null, $send_in_json = false) { $this->calls++; $this->error = false; return $this->payload; }
};
$sec = reset_security($core);
$ok = $sec->readERPDeveloperEncryptedSubKeys('synthetic-platform','developer@example.test');
check('missing keys: subkeys read from the endpoint', $ok===true && $sk_calls===1);
check('missing keys: subkeys not cached', count(glob($tmp_root.'/local_data/cache/f8-subkeys/*'))===0);
$sec->updateCache('after-subkeys',1);
check('missing keys: still nothing cached after the subkey is appended to the empty base key', $sec->cache_keys_missing===true && count(glob($tmp_root.'/local_data/cache/f8-subkeys/*'))===0);
//endregion

//region g) CoreCache with keys
section('g) CoreCache set/get with keys');
$core = new_core($tmp_root);
$core->cache->activateCacheFile($tmp_root.'/local_data/cache/g');
$warnings_before = count($php_warnings);
$obj = ['token'=>'synthetic-token-value','n'=>42,'list'=>[1,2,3]];
$core->cache->set('g-v1',$obj,null,'cache-key-synthetic','cache-iv-synthetic');
check('v1 cache round trip', $core->cache->get('g-v1',-1,'','cache-key-synthetic','cache-iv-synthetic')===$obj);
check('v1 cache wrong key -> null', $core->cache->get('g-v1',-1,'','wrong-key','wrong-iv')===null);
$core->cache->set('g-v1b',$obj,null,'cache-key-synthetic','cache-iv-synthetic');
$core->config->set('core.security.encrypt_format','v2');
$core->cache->set('g-v2',$obj,null,'cache-key-synthetic','cache-iv-synthetic');
check('v2 cache round trip', $core->cache->get('g-v2',-1,'','cache-key-synthetic','cache-iv-synthetic')===$obj);
check('v1 entry readable after enabling v2', $core->cache->get('g-v1b',-1,'','cache-key-synthetic','cache-iv-synthetic')===$obj);
check('v2 cache wrong key -> null', $core->cache->get('g-v2',-1,'','wrong-key','wrong-iv')===null);
$raw_v2 = file_get_contents($tmp_root.'/local_data/cache/g/'.preg_replace('/[\/\.;]/','_',$core->cache->spacename.'-g-v2'));
$info_v2 = unserialize(unserialize(gzuncompress($raw_v2)));
check('v2 entry stored with v2: prefix', is_array($info_v2) && strncmp($info_v2['_data_'],'v2:',3)===0);
check('no PHP warnings/exceptions in g)', count($php_warnings)===$warnings_before, implode("\n",array_slice($php_warnings,$warnings_before)));
//endregion

//region h) daily subkeys cached encrypted, legacy plain entry deleted
section('h) daily subkeys');
$subkeys = ['success'=>true,'data'=>['key'=>'SYNTHSUBKEY7f3a9c','iv'=>'SYNTHSUBIV2b8e1d']];
foreach (['v1'=>null,'v2'=>'v2'] as $label=>$format) {
    $cache_dir = $tmp_root.'/local_data/cache/h-'.$label;
    $core = new_core($tmp_root);
    $core->config->set('core.gcp.secrets.cache_encrypt_key','base-cache-key-synthetic');
    $core->config->set('core.gcp.secrets.cache_encrypt_iv','base-cache-iv-synthetic');
    if($format) $core->config->set('core.security.encrypt_format',$format);
    $sec = reset_security($core);
    $core->cache->activateCacheFile($cache_dir);
    $calls = 0;
    $core->request = new class($core,$subkeys,$calls) extends CoreRequest {
        private $payload; private $calls;
        function __construct(Core7 &$core, $payload, &$calls) { parent::__construct($core); $this->payload = $payload; $this->calls = &$calls; }
        public function get_json_decode(string $route, $data = null, $extra_headers = null, $send_in_json = false) { $this->calls++; $this->error = false; return $this->payload; }
    };
    $user = 'developer@example.test';
    // a legacy plain entry written by a version < 8.5.12
    $core->cache->set('/my-daily-encryption-subkeys/'.$user,$subkeys,date('Y-m-d'));
    $legacy_file = $cache_dir.'/'.preg_replace('/[\/\.;]/','_',$core->cache->spacename.'-/my-daily-encryption-subkeys/'.$user);
    $v2_file = $cache_dir.'/'.preg_replace('/[\/\.;]/','_',$core->cache->spacename.'-/my-daily-encryption-subkeys-v2/'.$user);
    check("[$label] legacy plain entry present before", is_file($legacy_file));

    $ok = $sec->readERPDeveloperEncryptedSubKeys('synthetic-platform',$user);
    check("[$label] readERPDeveloperEncryptedSubKeys() ok and endpoint called once", $ok===true && $calls===1);
    check("[$label] subkeys appended to the cache keys", $sec->cache_key==='base-cache-key-synthetic__SYNTHSUBKEY7f3a9c' && $sec->cache_iv==='base-cache-iv-synthetic__SYNTHSUBIV2b8e1d');
    check("[$label] new entry exists", is_file($v2_file));
    $raw = file_get_contents($v2_file);
    $decoded = gzuncompress($raw);
    $all_raw = $raw.$decoded.print_r(unserialize(unserialize($decoded)),true);
    check("[$label] raw entry does not contain the subkey key/iv in clear", strpos($all_raw,'SYNTHSUBKEY7f3a9c')===false && strpos($all_raw,'SYNTHSUBIV2b8e1d')===false);
    $info = unserialize(unserialize($decoded));
    check("[$label] stored format ".($format?'v2':'v1 (CBC)'), $format?strncmp($info['_data_'],'v2:',3)===0:(is_string($info['_data_']) && strncmp($info['_data_'],'v2:',3)!==0));
    check("[$label] legacy plain entry deleted", !is_file($legacy_file));

    // same request: guard last_key_cache, no second call and no double append
    $sec->readERPDeveloperEncryptedSubKeys('synthetic-platform',$user);
    check("[$label] guard last_key_cache keeps one call and one append", $calls===1 && substr_count($sec->cache_key,'__')===1);

    // next request: new CoreSecurity reads the encrypted entry with the BASE keys
    $sec_next = reset_security($core);
    $ok = $sec_next->readERPDeveloperEncryptedSubKeys('synthetic-platform',$user);
    check("[$label] next request reads the cached entry without calling the endpoint", $ok===true && $calls===1 && $sec_next->cache_key===$sec->cache_key);

    // a different base key can not read it: cache miss and endpoint called again, no exception
    $core->config->set('core.gcp.secrets.cache_encrypt_key','another-base-key-synthetic');
    $sec_other = reset_security($core);
    $ok = $sec_other->readERPDeveloperEncryptedSubKeys('synthetic-platform',$user);
    check("[$label] other base key -> cache miss -> endpoint called again", $ok===true && $calls===2);

    // delete of a missing legacy entry is silent
    $warnings_before = count($php_warnings);
    $core->cache->delete('/my-daily-encryption-subkeys/'.$user);
    check("[$label] deleting a missing legacy entry raises nothing", count($php_warnings)===$warnings_before);
}
//endregion

//region i) strict mode (core.security.encrypt_require_key, 8.5.13)
section('i) strict mode');
/** Core with a SpyConfig (no getenv, no GCP) and strict mode on */
function strict_core(string $root, array $config = [], array $env = []): array {
    $core = new_core($root);
    $spy = new SpyConfig($core, __DIR__.'/../src/config.json');
    $core->config = $spy;
    $spy->set('core.security.encrypt_require_key',true);
    foreach ($config as $k=>$v) $spy->set($k,$v);
    $spy->env_values = $env;
    return [$core,$spy,reset_security($core)];
}
$cfg_keys = ['core.security.encrypt_key'=>'strict-key-synthetic','core.security.encrypt_secret'=>'strict-secret-synthetic'];

// i.1 accepted values of the flag, and the ones that do not activate it
foreach ([true,1,'1','true'] as $v) {
    $c = new_core($tmp_root); $c->config->set('core.security.encrypt_require_key',$v);
    check('strict mode active with '.var_export($v,true), reset_security($c)->isEncryptStrictMode());
}
foreach ([false,0,'0','yes','',null] as $v) {
    $c = new_core($tmp_root); $c->config->set('core.security.encrypt_require_key',$v);
    check('strict mode NOT active with '.var_export($v,true), !reset_security($c)->isEncryptStrictMode());
}

// i.2 strict + configured keys: writes v2, round trip, not deterministic, not readable with the default keys
[$core,$spy,$sec] = strict_core($tmp_root,$cfg_keys);
$e1 = $sec->encrypt('mcp-token-synthetic'); $e2 = $sec->encrypt('mcp-token-synthetic');
check('strict: encrypt() writes v2', is_string($e1) && strncmp($e1,'v2:',3)===0, (string)$e1);
check('strict: round trip', $sec->decrypt($e1)==='mcp-token-synthetic');
check('strict: two encryptions differ', $e1!==$e2);
$plain_core = new_core($tmp_root); $plain_sec = reset_security($plain_core);
check('strict v2 value can NOT be read by a CoreSecurity without keys', $plain_sec->decrypt($e1)===false);
check('strict: encrypt_format=v1 does not downgrade to v1', (function() use ($tmp_root,$cfg_keys) {
    [$c,$s,$x] = strict_core($tmp_root,$cfg_keys+['core.security.encrypt_format'=>'v1']);
    return strncmp((string)$x->encrypt('abc'),'v2:',3)===0; })());

// i.3 strict + no keys anywhere: explicit failure, error once, never the default keys
[$core,$spy,$sec] = strict_core($tmp_root);
$syslog_before = count($syslog_lines);
$r1 = $sec->encrypt('abc'); $r2 = $sec->decrypt(legacy_encrypt('abc','legacy-key-synthetic','legacy-secret-synthetic')); $r3 = $sec->encryptV2('abc'); $r4 = $sec->decryptV2('v2:AAAA');
$new_lines = array_slice($syslog_lines,$syslog_before);
check('strict without keys: encrypt() returns false', $r1===false);
check('strict without keys: decrypt() of a v1 value without legacy keys returns false', $r2===false);
check('strict without keys: encryptV2()/decryptV2() return false', $r3===false && $r4===false);
check('strict without keys: error security-encrypt-key-missing sent once (syslog error)', count_syslog($new_lines,'security-encrypt-key-missing')===1 && ($new_lines[0][0]??'')==='error', json_encode($new_lines));
check('strict without keys: no default-key warning', count_syslog($new_lines,'security-default-encrypt-key-used')===0);

// i.4 strict + env_vars secret not readable (getEnvVar returns null): same explicit failure
[$core,$spy,$sec] = strict_core($tmp_root,[],['CF_ENCRYPT_KEY'=>null,'CF_ENCRYPT_SECRET'=>null]);
check('strict, secret unreadable: encrypt() returns false', $sec->encrypt('abc')===false);
check('strict, secret unreadable: getEnvVar() was asked', in_array('CF_ENCRYPT_KEY',$spy->env_calls,true));

// i.5 strict + only one of the pair: fails too (never completes a configured pair with anything else)
[$core,$spy,$sec] = strict_core($tmp_root,['core.security.encrypt_key'=>'strict-key-synthetic']);
check('strict with only encrypt_key: encrypt() returns false', $sec->encrypt('abc')===false);

// i.6 strict + keys from env vars (the api.cloudframework.io case: CF_ENCRYPT_* in the env_vars secret)
[$core,$spy,$sec] = strict_core($tmp_root,[],['CF_ENCRYPT_KEY'=>'env-key-synthetic','CF_ENCRYPT_SECRET'=>'env-secret-synthetic']);
$e = $sec->encrypt('abc');
check('strict with env keys: v2 round trip', strncmp((string)$e,'v2:',3)===0 && $sec->decrypt($e)==='abc');

// i.7 strict: a v1 value written with the LEGACY keys is readable when they are configured (migration path), with one warning
$legacy_cfg = ['core.security.legacy_encrypt_key'=>'legacy-key-synthetic','core.security.legacy_encrypt_secret'=>'legacy-secret-synthetic'];
[$core,$spy,$sec] = strict_core($tmp_root,$cfg_keys+$legacy_cfg);
$syslog_before = count($syslog_lines);
$legacy_value = legacy_encrypt('old-mcp-token-synthetic','legacy-key-synthetic','legacy-secret-synthetic');
$read1 = $sec->decrypt($legacy_value); $read2 = $sec->decrypt(legacy_encrypt('second-old-token','legacy-key-synthetic','legacy-secret-synthetic'));
$new_lines = array_slice($syslog_lines,$syslog_before);
check('strict: legacy-key v1 value is read', $read1==='old-mcp-token-synthetic' && $read2==='second-old-token');
check('strict: warning security-legacy-key-read sent once', count_syslog($new_lines,'security-legacy-key-read')===1, json_encode($new_lines));
$migrated = $sec->encrypt($read1);
check('strict: re-encrypted value is v2 with the configured keys', strncmp((string)$migrated,'v2:',3)===0 && $sec->decrypt($migrated)==='old-mcp-token-synthetic' && $plain_sec->decrypt($migrated)===false);
[$core,$spy,$sec] = strict_core($tmp_root,$cfg_keys);
check('strict: without legacy keys the same v1 value is not read', $sec->decrypt($legacy_value)===false);

// i.8 strict: a v1 value written with the CONFIGURED keys is read with them, without the legacy warning
[$core,$spy,$sec] = strict_core($tmp_root,$cfg_keys);
$syslog_before = count($syslog_lines);
check('strict: configured-key v1 value is read', $sec->decrypt(legacy_encrypt('cfg-v1','strict-key-synthetic','strict-secret-synthetic'))==='cfg-v1');
check('strict: no legacy-read warning for configured-key v1', count_syslog(array_slice($syslog_lines,$syslog_before),'security-legacy-key-read')===0);

// i.9 strict + EXPLICIT keys (CoreCache path): v2 written, and NO fallback to the legacy keys
[$core,$spy,$sec] = strict_core($tmp_root,$cfg_keys+$legacy_cfg);
$ex = $sec->encrypt('cache-value','explicit-cache-key','explicit-cache-iv');
check('strict explicit keys: v2 written and read', strncmp((string)$ex,'v2:',3)===0 && $sec->decrypt($ex,'explicit-cache-key','explicit-cache-iv')==='cache-value');
check('strict explicit keys: a legacy-key v1 value is NOT read with the legacy keys', $sec->decrypt(legacy_encrypt('x','legacy-key-synthetic','legacy-secret-synthetic'),'explicit-cache-key','explicit-cache-iv')!=='x');
check('strict explicit keys: getEnvVar() not called', count($spy->env_calls)===0, implode(',',$spy->env_calls));

// i.10 tampered strict v2 value returns false
[$core,$spy,$sec] = strict_core($tmp_root,$cfg_keys);
$t = $sec->encrypt('abc'); $raw = base64_decode(substr($t,3)); $raw[strlen($raw)-1] = chr(ord($raw[strlen($raw)-1]) ^ 1);
check('strict: tampered v2 returns false', $sec->decrypt('v2:'.base64_encode($raw))===false);

// i.11 without strict mode the keys behave the same since 8.5.25: explicit failure, no default warning
$core = new_core($tmp_root); $sec = reset_security($core);
$syslog_before = count($syslog_lines);
check('no strict: encrypt() without keys returns false, as in strict mode', $sec->encrypt('abc')===false);
$new_lines = array_slice($syslog_lines,$syslog_before);
check('no strict: error security-encrypt-key-missing, no default warning', count_syslog($new_lines,'security-encrypt-key-missing')===1 && count_syslog($new_lines,'security-default-encrypt-key-used')===0, json_encode($new_lines));
//endregion

//region k) strict mode: env_vars secret read again when the cached copy lacks CF_ENCRYPT_* (8.5.14)
section('k) strict mode: reload of the env_vars secret');
$secret_cfg = ['core.gcp.secrets.env_vars'=>'synthetic-env-vars-secret'];
$fresh_keys = function(SpyConfig $spy) { $spy->env_values = ['CF_ENCRYPT_KEY'=>'fresh-key-synthetic','CF_ENCRYPT_SECRET'=>'fresh-secret-synthetic']; return true; };

// k.1 stale cached copy (no keys), the reload brings them: works, one reload with reload=true, one warning, no error
[$core,$spy,$sec] = strict_core($tmp_root,$secret_cfg,['CF_ENCRYPT_KEY'=>null,'CF_ENCRYPT_SECRET'=>null]);
$spy->on_reload = $fresh_keys;
$syslog_before = count($syslog_lines);
$e = $sec->encrypt('abc'); $e2 = $sec->encrypt('def');
$new_lines = array_slice($syslog_lines,$syslog_before);
check('reload: encrypt() writes v2 with the reloaded keys', strncmp((string)$e,'v2:',3)===0 && $sec->decrypt($e)==='abc' && $sec->decrypt($e2)==='def');
check('reload: the value is readable with the reloaded keys only', $sec->decrypt($e,'fresh-key-synthetic','fresh-secret-synthetic')==='abc');
check('reload: secret read again once, bypassing the cache', $spy->reload_calls===[true], json_encode($spy->reload_calls));
check('reload: warning security-encrypt-env-vars-reloaded once, names the secret', count_syslog($new_lines,'security-encrypt-env-vars-reloaded')===1 && count_syslog($new_lines,'synthetic-env-vars-secret')===1, json_encode($new_lines));
check('reload: no security-encrypt-key-missing error', count_syslog($new_lines,'security-encrypt-key-missing')===0);
check('reload: the warning never contains a key value', count_syslog($new_lines,'fresh-key-synthetic')===0 && count_syslog($new_lines,'fresh-secret-synthetic')===0);

// k.2 the secret still lacks the keys: explicit failure, only ONE reload per request, error once
[$core,$spy,$sec] = strict_core($tmp_root,$secret_cfg,['CF_ENCRYPT_KEY'=>null,'CF_ENCRYPT_SECRET'=>null]);
$spy->on_reload = function(SpyConfig $spy) { return true; };
$syslog_before = count($syslog_lines);
$r1 = $sec->encrypt('abc'); $r2 = $sec->decrypt(legacy_encrypt('abc','legacy-key-synthetic','legacy-secret-synthetic')); $r3 = $sec->encrypt('again');
$new_lines = array_slice($syslog_lines,$syslog_before);
check('reload without keys: encrypt()/decrypt() return false', $r1===false && $r2===false && $r3===false);
check('reload without keys: secret read again only once in the request', count($spy->reload_calls)===1, json_encode($spy->reload_calls));
check('reload without keys: error security-encrypt-key-missing once', count_syslog($new_lines,'security-encrypt-key-missing')===1, json_encode($new_lines));

// k.3 the reload fails (secret unreadable) or throws: explicit failure, no warning of reload
foreach (['fails'=>function() { return false; }, 'throws'=>function() { throw new \RuntimeException('synthetic GCP failure'); }] as $label=>$fn) {
    [$core,$spy,$sec] = strict_core($tmp_root,$secret_cfg,['CF_ENCRYPT_KEY'=>null,'CF_ENCRYPT_SECRET'=>null]);
    $spy->on_reload = $fn;
    $syslog_before = count($syslog_lines);
    $r = $sec->encrypt('abc');
    $new_lines = array_slice($syslog_lines,$syslog_before);
    check("reload $label: encrypt() returns false", $r===false);
    check("reload $label: error key-missing, no reloaded warning", count_syslog($new_lines,'security-encrypt-key-missing')===1 && count_syslog($new_lines,'security-encrypt-env-vars-reloaded')===0, json_encode($new_lines));
}

// k.4 no env_vars secret configured: nothing to reload
[$core,$spy,$sec] = strict_core($tmp_root,[],['CF_ENCRYPT_KEY'=>null,'CF_ENCRYPT_SECRET'=>null]);
$spy->on_reload = $fresh_keys;
check('no secret configured: no reload and encrypt() returns false', $sec->encrypt('abc')===false && $spy->reload_calls===[]);

// k.5 keys already in the cached copy, or in config, or explicit: no reload
[$core,$spy,$sec] = strict_core($tmp_root,$secret_cfg,['CF_ENCRYPT_KEY'=>'env-key-synthetic','CF_ENCRYPT_SECRET'=>'env-secret-synthetic']);
$spy->on_reload = $fresh_keys;
$e = $sec->encrypt('abc');
check('keys in the cached copy: no reload', $spy->reload_calls===[] && $sec->decrypt($e)==='abc');
[$core,$spy,$sec] = strict_core($tmp_root,$secret_cfg+$cfg_keys);
$spy->on_reload = $fresh_keys;
$sec->encrypt('abc');
check('keys in config: no reload', $spy->reload_calls===[]);
[$core,$spy,$sec] = strict_core($tmp_root,$secret_cfg,['CF_ENCRYPT_KEY'=>null,'CF_ENCRYPT_SECRET'=>null]);
$spy->on_reload = $fresh_keys;
$sec->encrypt('cache-value','explicit-cache-key','explicit-cache-iv');
check('explicit keys (CoreCache): no reload', $spy->reload_calls===[]);

// k.6 one key in config and the other missing: the reload completes the pair
[$core,$spy,$sec] = strict_core($tmp_root,$secret_cfg+['core.security.encrypt_key'=>'strict-key-synthetic'],['CF_ENCRYPT_SECRET'=>null]);
$spy->on_reload = function(SpyConfig $spy) { $spy->env_values = ['CF_ENCRYPT_SECRET'=>'fresh-secret-synthetic']; return true; };
$e = $sec->encrypt('abc');
check('half pair: reload completes it, config key kept', $sec->decrypt($e,'strict-key-synthetic','fresh-secret-synthetic')==='abc' && count($spy->reload_calls)===1);

// k.7 without strict mode: since 8.5.25 the reload happens too (there are no default keys to fall back to)
$core = new_core($tmp_root); $spy = new SpyConfig($core, __DIR__.'/../src/config.json'); $core->config = $spy;
foreach ($secret_cfg as $kk=>$vv) $spy->set($kk,$vv);
$spy->on_reload = $fresh_keys; $sec = reset_security($core);
check('no strict: reload once and CBC with the reloaded keys', $sec->encrypt('abc')===legacy_encrypt('abc','fresh-key-synthetic','fresh-secret-synthetic') && $spy->reload_calls===[true], json_encode($spy->reload_calls));
//endregion

//region l) 8.5.25: no built-in default keys; v1 is read with the legacy keys only when they are configured
section('l) 8.5.25: no built-in default keys, legacy keys only when configured');
/** Core with a SpyConfig (no getenv, no GCP) WITHOUT strict mode */
function plain_core(string $root, array $config = [], array $env = []): array {
    $core = new_core($root);
    $spy = new SpyConfig($core, __DIR__.'/../src/config.json');
    $core->config = $spy;
    foreach ($config as $k=>$v) $spy->set($k,$v);
    $spy->env_values = $env;
    return [$core,$spy,reset_security($core)];
}
$l_syslog_start = count($syslog_lines);
$legacy_v1 = legacy_encrypt('legacy-v1-synthetic','legacy-key-synthetic','legacy-secret-synthetic');
$other_v2 = (function() use ($tmp_root) { $c = new_core($tmp_root); return reset_security($c)->encryptV2('v2-synthetic','v2-key-synthetic','v2-iv-synthetic'); })();

// l.1 no keys anywhere: encrypt() fails explicitly, with and without core.security.encrypt_require_key
foreach (['no strict'=>[],'strict'=>['core.security.encrypt_require_key'=>true]] as $label=>$extra) {
    [$core,$spy,$sec] = plain_core($tmp_root,$extra);
    $syslog_before = count($syslog_lines);
    $r1 = $sec->encrypt('abc'); $r2 = $sec->encryptV2('abc'); $r3 = $sec->decrypt($other_v2); $r4 = $sec->encrypt('again');
    $new_lines = array_slice($syslog_lines,$syslog_before);
    check("[$label] without keys: encrypt(), encryptV2() and decrypt() of v2 return false", $r1===false && $r2===false && $r3===false && $r4===false);
    check("[$label] without keys: error security-encrypt-key-missing once (syslog error)", count_syslog($new_lines,'security-encrypt-key-missing [key,secret]')===1 && ($new_lines[0][0]??'')==='error', json_encode($new_lines));
    check("[$label] without keys: no default-key warning", count_syslog($new_lines,'security-default-encrypt-key-used')===0);
}

// l.2 v1 without legacy keys: false + error security-legacy-key-missing once
[$core,$spy,$sec] = plain_core($tmp_root);
$syslog_before = count($syslog_lines);
$r1 = $sec->decrypt($legacy_v1); $r2 = $sec->decrypt(legacy_encrypt('second','legacy-key-synthetic','legacy-secret-synthetic'));
$new_lines = array_slice($syslog_lines,$syslog_before);
check('no keys, no legacy keys: v1 returns false', $r1===false && $r2===false);
check('no keys, no legacy keys: error security-legacy-key-missing once (syslog error)', count_syslog($new_lines,'security-legacy-key-missing [key,secret]')===1 && count_syslog($new_lines,'security-legacy-key-missing')===1 && ($new_lines[count($new_lines)-1][0]??'')==='error', json_encode($new_lines));
[$core,$spy,$sec] = plain_core($tmp_root,$cfg_keys);
$syslog_before = count($syslog_lines);
check('configured keys that do not read it, no legacy keys: v1 returns false', $sec->decrypt($legacy_v1)===false);
check('configured keys, no legacy keys: error security-legacy-key-missing once', count_syslog(array_slice($syslog_lines,$syslog_before),'security-legacy-key-missing')===1);
[$core,$spy,$sec] = plain_core($tmp_root,$cfg_keys);
$syslog_before = count($syslog_lines);
check('a v1 value written with the configured keys is read with them', $sec->decrypt(legacy_encrypt('cfg-v1','strict-key-synthetic','strict-secret-synthetic'))==='cfg-v1');
check('... without legacy notes', count_syslog(array_slice($syslog_lines,$syslog_before),'security-legacy-key')===0);

// l.3 legacy keys in config and no configured keys: v1 read with one warning; never used to encrypt or to read v2
[$core,$spy,$sec] = plain_core($tmp_root,$legacy_cfg);
$syslog_before = count($syslog_lines);
$r1 = $sec->decrypt($legacy_v1); $r2 = $sec->decrypt(legacy_encrypt('second','legacy-key-synthetic','legacy-secret-synthetic'));
$new_lines = array_slice($syslog_lines,$syslog_before);
check('legacy keys in config: v1 values read', $r1==='legacy-v1-synthetic' && $r2==='second');
check('legacy keys in config: warning security-legacy-key-read once (syslog warning)', count_syslog($new_lines,'security-legacy-key-read')===1 && ($new_lines[count($new_lines)-1][0]??'')==='warning', json_encode($new_lines));
$syslog_before = count($syslog_lines);
check('legacy keys are never used to encrypt: encrypt() returns false', $sec->encrypt('abc')===false && $sec->encryptV2('abc')===false);
check('... with the error security-encrypt-key-missing', count_syslog(array_slice($syslog_lines,$syslog_before),'security-encrypt-key-missing')===1);
check('legacy keys are never used to read v2', $sec->decrypt($sec->encryptV2('x','legacy-key-synthetic','legacy-secret-synthetic'))===false);

// l.4 configured keys that can not read it + legacy keys: read with the legacy keys
[$core,$spy,$sec] = plain_core($tmp_root,$cfg_keys+$legacy_cfg);
check('configured keys first, then legacy keys', $sec->decrypt($legacy_v1)==='legacy-v1-synthetic' && $sec->decrypt(legacy_encrypt('cfg-v1','strict-key-synthetic','strict-secret-synthetic'))==='cfg-v1');

// l.5 legacy keys from the environment (getEnvVar: getenv + env_vars secret), memorized
[$core,$spy,$sec] = plain_core($tmp_root,[],['CF_LEGACY_ENCRYPT_KEY'=>'legacy-key-synthetic','CF_LEGACY_ENCRYPT_SECRET'=>'legacy-secret-synthetic']);
$r1 = $sec->decrypt($legacy_v1); $r2 = $sec->decrypt($legacy_v1);
check('legacy keys from env vars: v1 read', $r1==='legacy-v1-synthetic' && $r2==='legacy-v1-synthetic');
check('legacy env vars read once and memorized', count(array_keys($spy->env_calls,'CF_LEGACY_ENCRYPT_KEY',true))===1 && count(array_keys($spy->env_calls,'CF_LEGACY_ENCRYPT_SECRET',true))===1, implode(',',$spy->env_calls));

// l.6 precedence: config over env, each value independently; a half pair is never completed
[$core,$spy,$sec] = plain_core($tmp_root,$legacy_cfg,['CF_LEGACY_ENCRYPT_KEY'=>'env-legacy-key-synthetic','CF_LEGACY_ENCRYPT_SECRET'=>'env-legacy-secret-synthetic']);
check('legacy config wins over legacy env vars', $sec->decrypt($legacy_v1)==='legacy-v1-synthetic' && $sec->decrypt(legacy_encrypt('env-v1','env-legacy-key-synthetic','env-legacy-secret-synthetic'))===false);
check('legacy config present: CF_LEGACY_* never asked', !in_array('CF_LEGACY_ENCRYPT_KEY',$spy->env_calls,true) && !in_array('CF_LEGACY_ENCRYPT_SECRET',$spy->env_calls,true), implode(',',$spy->env_calls));
[$core,$spy,$sec] = plain_core($tmp_root,['core.security.legacy_encrypt_key'=>'legacy-key-synthetic'],['CF_LEGACY_ENCRYPT_SECRET'=>'legacy-secret-synthetic']);
check('legacy key from config + legacy secret from env', $sec->decrypt($legacy_v1)==='legacy-v1-synthetic');
[$core,$spy,$sec] = plain_core($tmp_root,['core.security.legacy_encrypt_key'=>'legacy-key-synthetic']);
$syslog_before = count($syslog_lines);
check('half legacy pair: false', $sec->decrypt($legacy_v1)===false);
check('half legacy pair: error names the missing value', count_syslog(array_slice($syslog_lines,$syslog_before),'security-legacy-key-missing [secret]')===1);
[$core,$spy,$sec] = plain_core($tmp_root,['core.security.encrypt_key'=>'strict-key-synthetic']+$legacy_cfg);
check('half configured pair is never mixed with a legacy value: v1 read with the legacy pair only', $sec->decrypt($legacy_v1)==='legacy-v1-synthetic' && $sec->decrypt(legacy_encrypt('mixed','strict-key-synthetic','legacy-secret-synthetic'))===false);

// l.7 real getenv() (CoreConfig::getEnvVar)
putenv('CF_LEGACY_ENCRYPT_KEY=legacy-key-synthetic'); putenv('CF_LEGACY_ENCRYPT_SECRET=legacy-secret-synthetic');
$core = new_core($tmp_root); $sec = reset_security($core);
check('legacy keys from getenv(): v1 read', $sec->decrypt($legacy_v1)==='legacy-v1-synthetic');
putenv('CF_LEGACY_ENCRYPT_KEY'); putenv('CF_LEGACY_ENCRYPT_SECRET');
$core = new_core($tmp_root); $sec = reset_security($core);
check('getenv() cleared: v1 not read', $sec->decrypt($legacy_v1)===false);

// l.8 the env_vars secret is read again when the cached copy lacks the legacy keys (same reload as the configured keys)
[$core,$spy,$sec] = plain_core($tmp_root,$secret_cfg,['CF_LEGACY_ENCRYPT_KEY'=>null,'CF_LEGACY_ENCRYPT_SECRET'=>null]);
$spy->on_reload = function(SpyConfig $spy) { $spy->env_values = ['CF_LEGACY_ENCRYPT_KEY'=>'legacy-key-synthetic','CF_LEGACY_ENCRYPT_SECRET'=>'legacy-secret-synthetic']; return true; };
$syslog_before = count($syslog_lines);
$r1 = $sec->decrypt($legacy_v1); $r2 = $sec->decrypt(legacy_encrypt('second','legacy-key-synthetic','legacy-secret-synthetic'));
$new_lines = array_slice($syslog_lines,$syslog_before);
check('reload: legacy keys brought by the reload, v1 read', $r1==='legacy-v1-synthetic' && $r2==='second');
check('reload: secret read again once, bypassing the cache', $spy->reload_calls===[true], json_encode($spy->reload_calls));
check('reload: no legacy-missing error', count_syslog($new_lines,'security-legacy-key-missing')===0, json_encode($new_lines));
[$core,$spy,$sec] = plain_core($tmp_root,$secret_cfg);
$spy->on_reload = function(SpyConfig $spy) { return true; };
$r1 = $sec->encrypt('abc'); $r2 = $sec->decrypt($legacy_v1); $r3 = $sec->decrypt($legacy_v1);
check('reload without any key: everything false, only ONE reload in the request', $r1===false && $r2===false && $r3===false && count($spy->reload_calls)===1, json_encode($spy->reload_calls));

// l.9 explicit keys (CoreCache): the legacy keys are never used and nothing is read from the environment
[$core,$spy,$sec] = plain_core($tmp_root,$legacy_cfg);
$syslog_before = count($syslog_lines);
check('explicit keys: a legacy-key v1 value is not read with the legacy keys', $sec->decrypt($legacy_v1,'explicit-cache-key','explicit-cache-iv')!=='legacy-v1-synthetic');
check('explicit keys: v1 with the same explicit keys still read', $sec->decrypt(legacy_encrypt('cache-v1','explicit-cache-key','explicit-cache-iv'),'explicit-cache-key','explicit-cache-iv')==='cache-v1');
check('explicit keys: getEnvVar() not called and no legacy note', count($spy->env_calls)===0 && count_syslog(array_slice($syslog_lines,$syslog_before),'security-legacy-key')===0, implode(',',$spy->env_calls));

// l.10 strict mode stays accepted: it keeps writing v2 and reads the legacy keys the same way
[$core,$spy,$sec] = strict_core($tmp_root,$cfg_keys+$legacy_cfg);
$e = $sec->encrypt('abc');
check('strict: still writes v2 and reads the legacy v1', strncmp((string)$e,'v2:',3)===0 && $sec->decrypt($e)==='abc' && $sec->decrypt($legacy_v1)==='legacy-v1-synthetic');

// l.11 no note of this section carries a key value
$l_lines = json_encode(array_slice($syslog_lines,$l_syslog_start));
$leaks = [];
foreach (['legacy-key-synthetic','legacy-secret-synthetic','env-legacy-key-synthetic','strict-key-synthetic','strict-secret-synthetic','explicit-cache-key','v2-key-synthetic'] as $value) if(strpos($l_lines,$value)!==false) $leaks[] = $value;
check('no key value in any note of section l', !$leaks, implode(',',$leaks));

// l.12 no built-in encryption default in src/: no DEFAULT_ENCRYPT constant in any file, none in CoreSecurity
$hits = [];
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(realpath(__DIR__.'/../src'),FilesystemIterator::SKIP_DOTS));
foreach ($it as $file) {
    if(!$file->isFile()) continue;
    $content = file_get_contents($file->getPathname());
    if(preg_match('/const\s+DEFAULT_ENCRYPT|DEFAULT_ENCRYPT_(KEY|SECRET)/i',$content)) $hits[] = substr($file->getPathname(),strlen(realpath(__DIR__.'/..'))+1);
}
check('no const DEFAULT_ENCRYPT* (nor a reference to it) in any file of src/', !$hits, implode(',',$hits));
$rc = new ReflectionClass('CoreSecurity');
check('CoreSecurity has no DEFAULT_ENCRYPT_KEY / DEFAULT_ENCRYPT_SECRET constant', !$rc->hasConstant('DEFAULT_ENCRYPT_KEY') && !$rc->hasConstant('DEFAULT_ENCRYPT_SECRET'));
//endregion

//region j) mandatory cache keys at the entry points (8.5.13)
section('j) mandatory cache keys at the entry points');
putenv('CF_CACHE_ENCRYPT_KEY'); putenv('CF_CACHE_ENCRYPT_IV');
$core = new_core($tmp_root);
$err = $core->getStartupError();
check('getStartupError(): security-cache-key-missing without keys', is_array($err) && $err['code']==='security-cache-key-missing');
$core->config->set('core.gcp.secrets.cache_encrypt_key','cfg-cache-key-synthetic');
$core->config->set('core.gcp.secrets.cache_encrypt_iv','cfg-cache-iv-synthetic');
reset_security($core);
check('getStartupError(): null with both keys', $core->getStartupError()===null);
$core->config->set('core.gcp.secrets.cache_encrypt_iv','');
reset_security($core);
check('getStartupError(): error with only the key', is_array($core->getStartupError()));

// terminal: a child process without keys stops with exit code 1 and the detail in STDERR
$j_root = $tmp_root.'/j-app';
@mkdir($j_root,0700,true);
file_put_contents($j_root.'/config.json','{}');
$child = $j_root.'/child.php';
file_put_contents($child, '<?php ob_start(); ini_set("session.save_path",'.var_export($j_root,true).'); include_once '.var_export(realpath(__DIR__.'/../src/Core7.php'),true).'; $core = new Core7('.var_export($j_root,true).'); $core->exitOnStartupError(); echo "SERVED";');
$run = function(array $env) use ($child) {
    $proc = proc_open(['php',$child],[1=>['pipe','w'],2=>['pipe','w']],$pipes,null,$env);
    $out = stream_get_contents($pipes[1]); $errout = stream_get_contents($pipes[2]);
    fclose($pipes[1]); fclose($pipes[2]);
    return [proc_close($proc),$out,$errout];
};
$base_env = ['PATH'=>getenv('PATH'),'HOME'=>getenv('HOME'),'PWD'=>$j_root]; // CoreCache reads $_SERVER['PWD'] in terminal mode
[$code,$out,$errout] = $run($base_env);
check('terminal without keys: exit code 1, nothing served', $code===1 && strpos($out,'SERVED')===false, "code=$code");
check('terminal without keys: detail in STDERR', strpos($errout,'security-cache-key-missing')!==false);
[$code,$out,$errout] = $run($base_env + ['CF_CACHE_ENCRYPT_KEY'=>'env-cache-key-synthetic','CF_CACHE_ENCRYPT_IV'=>'env-cache-iv-synthetic']);
check('terminal with keys (env): served, exit code 0', $code===0 && strpos($out,'SERVED')!==false, "code=$code");

// HTTP: the real dispatcher behind php -S answers 503 with a generic message
$http_root = $tmp_root.'/j-http';
@mkdir($http_root,0700,true);
file_put_contents($http_root.'/config.json','{}');
@mkdir($http_root.'/vendor',0700,true);
file_put_contents($http_root.'/vendor/autoload.php','<?php // stub: the framework repository has no vendor/');
$serve = function(array $env) use ($http_root) {
    $sock = stream_socket_server('tcp://127.0.0.1:0'); $port = (int)explode(':',stream_socket_get_name($sock,false))[1]; fclose($sock);
    $proc = proc_open(['php','-S','127.0.0.1:'.$port,'-t',$http_root,realpath(__DIR__.'/../src/dispatcher.php')],[1=>['file','/dev/null','w'],2=>['file','/dev/null','w']],$pipes,$http_root,$env);
    $body = false; $status = '';
    for($i=0;$i<50 && $body===false;$i++) {
        usleep(100000);
        $ctx = stream_context_create(['http'=>['ignore_errors'=>true,'timeout'=>5]]);
        $body = @file_get_contents('http://127.0.0.1:'.$port.'/_version',false,$ctx);
        $status = $http_response_header[0] ?? '';
    }
    proc_terminate($proc); proc_close($proc);
    return [$status,(string)$body];
};
[$status,$body] = $serve($base_env);
check('HTTP without keys: 503', strpos($status,' 503')!==false, "status=$status");
check('HTTP without keys: generic message, no configuration detail', strpos($body,'"code":"system-error"')!==false && strpos($body,'cache_encrypt')===false && strpos($body,'CF_CACHE')===false, $body);
[$status,$body] = $serve($base_env + ['CF_CACHE_ENCRYPT_KEY'=>'env-cache-key-synthetic','CF_CACHE_ENCRYPT_IV'=>'env-cache-iv-synthetic']);
check('HTTP with keys: not stopped by the startup check', $status!=='' && strpos($status,' 503')===false && strpos($body,'The service is not correctly configured')===false, "status=$status");
//endregion

//region SUMMARY
echo "\nversion: ".(new_core($tmp_root))->_version."\n";
echo "PHP warnings/notices captured during the whole run: ".count($php_warnings)."\n";
foreach ($php_warnings as $w) echo "  - $w\n";
echo "\n{$checks} checks, {$failures} failures\n";
// cleanup of the temporary root
exec('rm -rf '.escapeshellarg($tmp_root));
exit($failures?1:0);
//endregion
