<?php
// CLOUD Documentum:
//   DevGroup: /cf/products/cloud-development
//   DevUnit: /php/classes/Core7/CoreSecurity, /cloud-development/frameworks/php
//   Activities: 6651158731161600
/**
 * Isolated tests of GoogleKMS (src/class/GoogleKMS.php) and of the v3 format of CoreSecurity (Cloud KMS), since 8.5.27.
 *
 * Contract under test:
 *  - GoogleKMS: encrypt/decrypt with AAD, version taken from EncryptResponse.name, one client reused, REST transport,
 *    3 s per attempt with the retries of the library disabled, ONE retry only on transient errors, never on a permission
 *    or AAD error, no plaintext/ciphertext/AAD in errorMsg, invalid credentials refused.
 *  - CoreSecurity: v3:<alias>:<N>:<base64> written only without explicit keys and with a primary data key; AAD
 *    cloudframework:<usage>; unknown alias refused without calling KMS; fail closed (false, security-kms-unavailable once
 *    per request, never a v2 value); without the new configuration everything as in 8.5.26; explicit keys (CoreCache)
 *    never use KMS; encryptionVersionOf() and reEncrypt().
 *
 * Self-contained: Cloud KMS is replaced by a fake client injected with GoogleKMS::$client_factory, and the classes
 * Google\Cloud\Kms\V1\* are stubs defined here (no vendor/ autoload). No network, no Redis. Keys are synthetic.
 *
 * Run from the repository root:
 *   php tests/GoogleKMSAndV3.php
 * Exit code 0 when every check passes, 1 otherwise.
 */

//region STUBS of the Cloud KMS client library (only what GoogleKMS and Buckets use)
namespace Google\Cloud\Kms\V1 {
    if(!class_exists(EncryptRequest::class)) {
        class EncryptRequest {
            private $name = ''; private $plaintext = ''; private $aad = ''; public $aad_set = false;
            function setName($v) { $this->name = $v; return $this; }
            function getName() { return $this->name; }
            function setPlaintext($v) { $this->plaintext = $v; return $this; }
            function getPlaintext() { return $this->plaintext; }
            function setAdditionalAuthenticatedData($v) { $this->aad = $v; $this->aad_set = true; return $this; }
            function getAdditionalAuthenticatedData() { return $this->aad; }
        }
        class DecryptRequest {
            private $name = ''; private $ciphertext = ''; private $aad = ''; public $aad_set = false;
            function setName($v) { $this->name = $v; return $this; }
            function getName() { return $this->name; }
            function setCiphertext($v) { $this->ciphertext = $v; return $this; }
            function getCiphertext() { return $this->ciphertext; }
            function setAdditionalAuthenticatedData($v) { $this->aad = $v; $this->aad_set = true; return $this; }
            function getAdditionalAuthenticatedData() { return $this->aad; }
        }
    }
}
namespace Google\Cloud\Kms\V1\Client {
    if(!class_exists(KeyManagementServiceClient::class)) {
        class KeyManagementServiceClient {
            public static $constructed = 0;
            function __construct(array $options = []) { self::$constructed++; throw new \RuntimeException('stub: the real client must never be built in this suite'); }
            static function cryptoKeyName($p,$l,$r,$k) { return "projects/$p/locations/$l/keyRings/$r/cryptoKeys/$k"; }
        }
    }
}
//endregion

namespace {

//region ENVIRONMENT
foreach (['CF_ENCRYPT_KEY','CF_ENCRYPT_SECRET','CF_LEGACY_ENCRYPT_KEY','CF_LEGACY_ENCRYPT_SECRET','CF_CACHE_ENCRYPT_KEY','CF_CACHE_ENCRYPT_IV','CF_KMS_DATA_KEYS','CF_KMS_DATA_KEY_PRIMARY','PROJECT_ID','REDIS_HOST','REDIS_PORT','GOOGLE_APPLICATION_CREDENTIALS','GAE_SERVICE','K_SERVICE','FUNCTION_TARGET'] as $env_var) putenv($env_var);
unset($_SERVER['GAE_SERVICE']);

$tmp_root = sys_get_temp_dir().'/cf-kms-v3-test-'.getmypid();
@mkdir($tmp_root.'/local_data/cache',0700,true);
file_put_contents($tmp_root.'/config.json','{}');
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
include_once __DIR__.'/../src/class/GoogleKMS.php';
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

/** An exception like Google\ApiCore\ApiException: getStatus() returns the gRPC status name */
class FakeApiException extends \Exception {
    private $status;
    function __construct(string $status, int $code) { $this->status = $status; parent::__construct('{"message":"synthetic '.$status.'"}',$code); }
    function getStatus() { return $this->status; }
    function getBasicMessage() { return 'synthetic '.$this->status; }
}
class FakeEncryptResponse { function __construct(private $name, private $ciphertext) {} function getName() { return $this->name; } function getCiphertext() { return $this->ciphertext; } }
class FakeDecryptResponse { function __construct(private $plaintext) {} function getPlaintext() { return $this->plaintext; } }

/**
 * Fake Cloud KMS: AES-256-GCM with a key derived from the key resource and the version, the version written in the
 * ciphertext (like Cloud KMS, decrypt finds it by itself) and the AAD authenticated.
 * $mode: ok | unavailable | unavailable-once | deadline | permission | not-found
 */
class FakeKMSClient {
    public $mode = 'ok';
    public $versions = [];          // resource => current primary version number
    public $calls = ['encrypt'=>0,'decrypt'=>0];
    public $last_options = null;
    public $last_request = null;
    private $failed_once = false;
    private function key(string $resource, int $version): string { return hash('sha256','synthetic-kms|'.$resource.'|'.$version,true); }
    private function maybeFail(): void {
        if($this->mode==='unavailable') throw new FakeApiException('UNAVAILABLE',14);
        if($this->mode==='deadline') throw new FakeApiException('DEADLINE_EXCEEDED',4);
        if($this->mode==='permission') throw new FakeApiException('PERMISSION_DENIED',7);
        if($this->mode==='not-found') throw new FakeApiException('NOT_FOUND',5);
        if($this->mode==='unavailable-once' && !$this->failed_once) { $this->failed_once = true; throw new FakeApiException('UNAVAILABLE',14); }
    }
    function encrypt($request, array $options = []) {
        $this->calls['encrypt']++;
        $this->last_options = $options; $this->last_request = $request;
        $this->maybeFail();
        $resource = $request->getName();
        $version = $this->versions[$resource] ?? 1;
        $iv = random_bytes(12); $tag = '';
        $ct = openssl_encrypt($request->getPlaintext(),'aes-256-gcm',$this->key($resource,$version),OPENSSL_RAW_DATA,$iv,$tag,(string)$request->getAdditionalAuthenticatedData(),16);
        return new FakeEncryptResponse($resource.'/cryptoKeyVersions/'.$version, pack('N',$version).$iv.$tag.$ct);
    }
    function decrypt($request, array $options = []) {
        $this->calls['decrypt']++;
        $this->last_options = $options; $this->last_request = $request;
        $this->maybeFail();
        $raw = $request->getCiphertext();
        if(strlen($raw)<32) throw new FakeApiException('INVALID_ARGUMENT',3);
        $version = unpack('N',substr($raw,0,4))[1];
        $plain = openssl_decrypt(substr($raw,32),'aes-256-gcm',$this->key($request->getName(),$version),OPENSSL_RAW_DATA,substr($raw,4,12),substr($raw,16,16),(string)$request->getAdditionalAuthenticatedData());
        if($plain===false) throw new FakeApiException('INVALID_ARGUMENT',3);
        return new FakeDecryptResponse($plain);
    }
    function reset(): void { $this->calls = ['encrypt'=>0,'decrypt'=>0]; $this->mode = 'ok'; $this->failed_once = false; }
    function total(): int { return $this->calls['encrypt']+$this->calls['decrypt']; }
}

$fake = new FakeKMSClient();
$factory_calls = []; // client options received by the factory
GoogleKMS::$client_factory = function (array $options) use (&$fake, &$factory_calls) { $factory_calls[] = $options; return $fake; };

/** Literal copy of the v1 CBC of encrypt() (8.5.26): the reference for "identical to before" */
function legacy_encrypt($text, $secret_key, $secret_iv) {
    $key = hash('sha256', $secret_key);
    $iv = substr(hash('sha256', $secret_iv), 0, 16);
    return openssl_encrypt($text, "AES-256-CBC", $key, 0, $iv);
}

const RES_A = 'projects/synthetic-project/locations/europe-west1/keyRings/synthetic-ring/cryptoKeys/data-a';
const RES_B = 'projects/synthetic-project/locations/europe-west1/keyRings/synthetic-ring/cryptoKeys/data-b';

function new_core(string $root): Core7 { return new Core7($root); }
function reset_security(Core7 $core): CoreSecurity { $core->security = new CoreSecurity($core); return $core->security; }
/** Core with the data keys of v1/v2 and, optionally, the Cloud KMS configuration */
function kms_core(?string $primary = 'a', ?array $keys = ['a'=>RES_A,'b'=>RES_B]): Core7 {
    global $tmp_root;
    $core = new_core($tmp_root);
    $core->config->set('core.security.encrypt_key','cfg-key-synthetic');
    $core->config->set('core.security.encrypt_secret','cfg-secret-synthetic');
    if($keys!==null) $core->config->set('core.gcp.kms.data_keys',$keys);
    if($primary!==null) $core->config->set('core.gcp.kms.data_key_primary',$primary);
    reset_security($core);
    return $core;
}
function count_syslog(array $lines, string $needle): int {
    $n = 0;
    foreach ($lines as $line) if(strpos($line[1],$needle)!==false) $n++;
    return $n;
}
function logs_text(Core7 $core): string { return json_encode($core->logs->data,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE).json_encode($core->errors->data,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE); }
$all_logs = '';
//endregion

$core = new_core($tmp_root); // first Core7: starts the session before any output
flush_output();

//region a) GoogleKMS: encrypt / decrypt, AAD, version, call options, one client
section('a) GoogleKMS: round trip, AAD, version, call options, one client');
$fake->reset(); $factory_calls = [];
$kms = new GoogleKMS($core);
$res = $kms->encrypt('synthetic plaintext á','cloudframework:default',RES_A);
check('encrypt returns ciphertext and version', is_array($res) && is_string($res['ciphertext']) && $res['ciphertext']!=='' && $res['version']==='1', json_encode($kms->errorMsg));
check('the AAD is sent to Cloud KMS', $fake->last_request->getAdditionalAuthenticatedData()==='cloudframework:default');
check('call options: 3000 ms per attempt and retries of the library disabled',
    ($fake->last_options['timeoutMillis']??null)===3000 && ($fake->last_options['retrySettings']['retriesEnabled']??null)===false && ($fake->last_options['retrySettings']['noRetriesRpcTimeoutMillis']??null)===3000, json_encode($fake->last_options));
check('decrypt with the same AAD', $kms->decrypt($res['ciphertext'],'cloudframework:default',RES_A)==='synthetic plaintext á');
check('decrypt with another AAD: false, kms-invalid-argument, 1 attempt (never retried)',
    $kms->decrypt($res['ciphertext'],'cloudframework:other',RES_A)===false && $kms->errorCode==='kms-invalid-argument' && $kms->lastAttempts===1 && $kms->lastStatus==='INVALID_ARGUMENT');
check('the error resets on the next call', $kms->decrypt($res['ciphertext'],'cloudframework:default',RES_A)==='synthetic plaintext á' && !$kms->error && !$kms->errorMsg);
$fake->versions[RES_A] = 4;
$res4 = $kms->encrypt('rotated','',RES_A);
check('version from EncryptResponse.name after a rotation (4)', ($res4['version']??'')==='4');
check('without AAD: no AAD set on the request', $fake->last_request->aad_set===false);
check('a ciphertext of version 1 still decrypts after the rotation', $kms->decrypt($res['ciphertext'],'cloudframework:default',RES_A)==='synthetic plaintext á');
$fake->versions[RES_A] = 1;
check('one client for the instance: the factory was called once', count($factory_calls)===1, 'calls='.count($factory_calls));
check('REST transport in the client options', ($factory_calls[0]['transport']??'')==='rest', json_encode($factory_calls[0]??null));
check('wrapKey/unwrapKey round trip (no AAD)', ($w = $kms->wrapKey(str_repeat("\x01",32),RES_B))!==false && $kms->unwrapKey($w,RES_B)===str_repeat("\x01",32) && $fake->last_request->aad_set===false);
$calls_before = $fake->total();
check('invalid key resource: params-error, no call', $kms->encrypt('x','',RES_A.'/cryptoKeyVersions/1')===false && $kms->errorCode==='params-error' && $fake->total()===$calls_before);
check('decrypt with a key resource of a version: params-error, no call', $kms->decrypt('x','','projects/p/locations/l/keyRings/r')===false && $kms->errorCode==='params-error' && $fake->total()===$calls_before);
check('plaintext over 64 KiB: params-error, no call', $kms->encrypt(str_repeat('a',65537),'',RES_A)===false && $kms->errorCode==='params-error' && $fake->total()===$calls_before);
check('exactly 64 KiB is sent', $kms->encrypt(str_repeat('a',65536),'',RES_A)!==false && $fake->total()===$calls_before+1);
//endregion

//region b) GoogleKMS: retries only on transient errors
section('b) GoogleKMS: one retry, only on transient errors');
$fake->reset();
$kms = new GoogleKMS($core);
$fake->mode = 'unavailable-once';
$r = $kms->encrypt('payload','',RES_A);
check('UNAVAILABLE once: the retry succeeds (2 attempts)', $r!==false && $kms->lastAttempts===2 && $fake->calls['encrypt']===2);
$fake->reset(); $fake->mode = 'unavailable';
check('UNAVAILABLE always: false after exactly 2 attempts, kms-unavailable, transient', $kms->encrypt('payload','',RES_A)===false && $fake->calls['encrypt']===2 && $kms->errorCode==='kms-unavailable' && $kms->lastTransient===true);
$fake->reset(); $fake->mode = 'deadline';
check('DEADLINE_EXCEEDED: 2 attempts', $kms->decrypt('anything-long-enough-for-the-fake-0123456789','',RES_A)===false && $fake->calls['decrypt']===2 && $kms->errorCode==='kms-unavailable');
$fake->reset(); $fake->mode = 'permission';
check('PERMISSION_DENIED: 1 attempt, kms-permission-denied', $kms->encrypt('payload','',RES_A)===false && $fake->calls['encrypt']===1 && $kms->errorCode==='kms-permission-denied' && $kms->lastTransient===false);
$fake->reset(); $fake->mode = 'not-found';
check('NOT_FOUND: 1 attempt, kms-not-found', $kms->encrypt('payload','',RES_A)===false && $fake->calls['encrypt']===1 && $kms->errorCode==='kms-not-found');
$fake->reset(); $fake->mode = 'unavailable';
$kms->encrypt('SECRET-PLAINTEXT-MARK','AAD-MARK',RES_A);
$msg = json_encode($kms->errorMsg);
check('errorMsg has no plaintext nor AAD', strpos($msg,'SECRET-PLAINTEXT-MARK')===false && strpos($msg,'AAD-MARK')===false, $msg);
$fake->reset();
//endregion

//region c) GoogleKMS: credentials and missing library
section('c) GoogleKMS: credentials and missing client library');
$factory_calls = [];
$kms = new GoogleKMS($core,['credentials'=>['type'=>'service_account','client_email'=>'synthetic@example.invalid']]);
$kms->encrypt('x','',RES_A);
check('array credentials forwarded to the client', ($factory_calls[0]['credentials']['client_email']??'')==='synthetic@example.invalid');
$factory_calls = [];
$kms = new GoogleKMS($core);
check('JSON string credentials accepted', $kms->setCredentials('{"type":"service_account"}')===true);
$kms->encrypt('x','',RES_A);
check('JSON credentials forwarded decoded', ($factory_calls[0]['credentials']['type']??'')==='service_account');
$cred_file = $tmp_root.'/synthetic-sa.json'; file_put_contents($cred_file,'{"type":"service_account"}');
$kms = new GoogleKMS($core);
check('file path credentials accepted', $kms->setCredentials($cred_file)===true);
$factory_calls = [];
$kms = new GoogleKMS($core,['credentials'=>'not-json-and-not-a-file']);
$calls_before = $fake->total();
check('invalid credentials: params-error in the constructor', $kms->error && $kms->errorCode==='params-error');
check('invalid credentials: every call fails, no client created', $kms->encrypt('x','',RES_A)===false && $kms->errorCode==='params-error' && count($factory_calls)===0 && $fake->total()===$calls_before);
$saved_factory = GoogleKMS::$client_factory;
GoogleKMS::$client_factory = null;
$kms = new GoogleKMS($core);
check('real client failing to build: kms-client-error, no exception', $kms->encrypt('x','',RES_A)===false && $kms->errorCode==='kms-client-error');
GoogleKMS::$client_factory = $saved_factory;
//endregion

//region d) CoreSecurity v3: round trip, format, usage
section('d) CoreSecurity v3: round trip, format, usage (AAD)');
$fake->reset();
$core = kms_core();
$sec = $core->security;
$texts = ['a','hello world','{"json":true}',"línea con acentos ñ €",random_bytes(64),str_repeat('x',5000)];
$ok = true; $format_ok = true;
foreach ($texts as $t) {
    $e = $sec->encrypt($t);
    if(!is_string($e) || !preg_match('#^v3:a:1:[A-Za-z0-9+/]+={0,2}$#',$e)) $format_ok = false;
    if($sec->decrypt($e)!==$t) $ok = false;
}
check('encrypt() without keys writes v3:a:1:<base64>', $format_ok);
check('v3 round trip', $ok);
check('the AAD of an empty usage is cloudframework:default', $fake->last_request->getAdditionalAuthenticatedData()==='cloudframework:default');
$e = $sec->encrypt('bound to a usage','','','user-token');
check('usage user-token: AAD cloudframework:user-token', $fake->last_request->getAdditionalAuthenticatedData()==='cloudframework:user-token');
check('decrypt with the same usage', $sec->decrypt($e,'','','user-token')==='bound to a usage');
$syslog_before = count($syslog_lines);
check('decrypt with another usage: false', $sec->decrypt($e,'','','other-usage')===false);
check('decrypt without usage: false', $sec->decrypt($e)===false);
check('a wrong usage leaves security-kms-decrypt-failed (not unavailable)', count_syslog(array_slice($syslog_lines,$syslog_before),'security-kms-decrypt-failed')===1 && count_syslog(array_slice($syslog_lines,$syslog_before),'security-kms-unavailable')===0);
$calls_before = $fake->total();
check('invalid usage: encrypt false, no KMS call', $sec->encrypt('x','','','Bad Usage')===false && $fake->total()===$calls_before);
check('invalid usage: decrypt false, no KMS call', $sec->decrypt($e,'','','x/y')===false && $fake->total()===$calls_before);
check('usage of 41 characters refused', $sec->encrypt('x','','',str_repeat('a',41))===false);
check('note security-encrypt-usage-invalid without the value', strpos(logs_text($core),'security-encrypt-usage-invalid')!==false && strpos(logs_text($core),'Bad Usage')===false);
check('empty text unchanged (as before)', $sec->encrypt('')==='' && $sec->encrypt(null)===null && $sec->decrypt('')==='');
check('two encryptions of the same text differ', $sec->encrypt('same')!==$sec->encrypt('same'));
$fake->versions[RES_A] = 7;
$e7 = $sec->encrypt('after rotation');
check('after a rotation in KMS the value carries the new version (v3:a:7:)', strncmp($e7,'v3:a:7:',7)===0 && $sec->decrypt($e7)==='after rotation');
check('values of version 1 still decrypt', $sec->decrypt($sec->encrypt('x'))==='x');
$fake->versions[RES_A] = 1;
check('v3 is written also with encrypt_format=v2', (function() use ($core,$sec) { $core->config->set('core.security.encrypt_format','v2'); $r = strncmp((string)$sec->encrypt('x'),'v3:a:',5)===0; $core->config->set('core.security.encrypt_format',null); return $r; })());
check('encryptV2() keeps writing v2 (explicit API)', strncmp((string)$sec->encryptV2('x'),'v2:',3)===0);
$big = str_repeat('b',65537);
$calls_before = $fake->total();
check('plaintext over 64 KiB: false, no KMS call, no v2', $sec->encrypt($big)===false && $fake->total()===$calls_before);
check('note security-kms-plaintext-too-large', strpos(logs_text($core),'security-kms-plaintext-too-large')!==false);
$all_logs .= logs_text($core);
//endregion

//region e) unknown alias, malformed values, misconfiguration
section('e) unknown alias, malformed v3, misconfiguration: no KMS call');
$fake->reset();
$core = kms_core();
$sec = $core->security;
$valid = $sec->encrypt('x');
$calls_before = $fake->total();
$unknown = 'v3:zz:1:'.substr($valid,strlen('v3:a:1:'));
check('unknown alias: false', $sec->decrypt($unknown)===false);
check('unknown alias: the client is not called', $fake->total()===$calls_before);
check('note security-kms-unknown-alias', strpos(logs_text($core),'security-kms-unknown-alias')!==false);
foreach (['v3:','v3:a:1:','v3:a:0:QUJD','v3:A:1:QUJD','v3:abcdefghi:1:QUJD','v3:a:1:@@@','v3:a:x:QUJD','v3:a:1'] as $bad)
    if($sec->decrypt($bad)!==false) check('malformed '.$bad.' refused', false);
check('malformed v3 values refused without calling KMS', $fake->total()===$calls_before);
check('note security-kms-format-invalid', strpos(logs_text($core),'security-kms-format-invalid')!==false);
check('v3 with explicit keys: false, no KMS call', $sec->decrypt($valid,'k','i')===false && $sec->decrypt($valid,'k')===false && $fake->total()===$calls_before);
check('note security-kms-explicit-keys', strpos(logs_text($core),'security-kms-explicit-keys')!==false);
$all_logs .= logs_text($core);

$core = kms_core('zz');
check('primary alias not in data_keys: encrypt false, no KMS call, no v2', $core->security->encrypt('x')===false && $fake->total()===$calls_before);
check('note security-kms-config-invalid', strpos(logs_text($core),'security-kms-config-invalid')!==false);
$core = kms_core('BAD!');
check('primary alias with invalid characters: false', $core->security->encrypt('x')===false && $fake->total()===$calls_before);
$core = kms_core('a',['a'=>'projects/p/locations/l/keyRings/r/cryptoKeys/k/cryptoKeyVersions/1']);
check('data key that is a key version: refused (false)', $core->security->encrypt('x')===false && $fake->total()===$calls_before);
$core = kms_core('a',['a'=>RES_A,'Bad'=>RES_B,'toolongalias'=>RES_B]);
check('invalid entries of data_keys are ignored, valid ones work', strncmp((string)$core->security->encrypt('x'),'v3:a:1:',7)===0);
$core = kms_core('a',null);
$core->config->set('core.gcp.kms.data_keys',json_encode(['a'=>RES_A]));
reset_security($core);
check('data_keys as a JSON string in the config', strncmp((string)$core->security->encrypt('x'),'v3:a:1:',7)===0);
$all_logs .= logs_text($core);
//endregion

//region f) KMS down: fail closed
section('f) Cloud KMS down: fail closed, never v2');
$fake->reset();
$core = kms_core();
$sec = $core->security;
$valid = $sec->encrypt('stored while KMS was up');
$fake->mode = 'unavailable';
$fake->calls = ['encrypt'=>0,'decrypt'=>0];
$syslog_before = count($syslog_lines);
$e1 = $sec->encrypt('a'); $e2 = $sec->encrypt('b');
$d1 = $sec->decrypt($valid);
check('encrypt returns false (twice)', $e1===false && $e2===false);
check('decrypt returns false', $d1===false);
check('each call: exactly 2 attempts (one retry)', $fake->calls['encrypt']===4 && $fake->calls['decrypt']===2, json_encode($fake->calls));
$new_lines = array_slice($syslog_lines,$syslog_before);
check('security-kms-unavailable once per request, level error', count_syslog($new_lines,'security-kms-unavailable')===1 && ($new_lines[0][0]??'')==='error', json_encode($new_lines));
check('no v2 and no v1 written as a fallback', !is_string($e1) && !is_string($e2));
$fake->mode = 'permission'; $fake->calls = ['encrypt'=>0,'decrypt'=>0];
check('PERMISSION_DENIED: false after 1 attempt', $sec->encrypt('a')===false && $fake->calls['encrypt']===1);
$fake->reset();
check('KMS back: the same instance works again', $sec->decrypt($valid)==='stored while KMS was up');
$core2 = kms_core();
$fake->mode = 'unavailable-once';
check('a transient failure followed by success: the value is v3 and decrypts', strncmp((string)($x = $core2->security->encrypt('retry')),'v3:a:1:',7)===0 && ($fake->reset() || true) && $core2->security->decrypt($x)==='retry');
check('a success after the retry leaves no unavailable note', strpos(logs_text($core2),'security-kms-unavailable')===false);
$all_logs .= logs_text($core).logs_text($core2);
//endregion

//region g) without the new configuration: identical to 8.5.26
section('g) without the new configuration everything is as in 8.5.26');
$fake->reset(); $factory_calls = [];
$core = kms_core(null,null);
$sec = $core->security;
$same = true;
foreach ($texts as $t) if($sec->encrypt($t)!==legacy_encrypt($t,'cfg-key-synthetic','cfg-secret-synthetic')) $same = false;
check('encrypt() without keys == v1 CBC of 8.5.26 with the config keys', $same);
$core->config->set('core.security.encrypt_format','v2');
$v2 = $sec->encrypt('x');
check('encrypt_format v2 still writes v2', strncmp((string)$v2,'v2:',3)===0 && $sec->decrypt($v2)==='x');
$core->config->set('core.security.encrypt_format',null);
check('the KMS client is never created', count($factory_calls)===0 && $fake->total()===0);
$core = kms_core(null);
check('data_keys without primary: v1 as before, no KMS', $core->security->encrypt('x')===legacy_encrypt('x','cfg-key-synthetic','cfg-secret-synthetic') && $fake->total()===0);
$core = kms_core(null,null);
check('a v3 value without data_keys: false (unknown alias), no KMS', $core->security->decrypt('v3:a:1:QUJDREVGR0g=')===false && $fake->total()===0);
$core = new_core($tmp_root); reset_security($core);
check('no data keys at all: encrypt() false as in 8.5.25+', $core->security->encrypt('x')===false && $fake->total()===0);
$all_logs .= logs_text($core);
//endregion

//region h) explicit keys (CoreCache) never use KMS
section('h) explicit keys (CoreCache) never use KMS');
$fake->reset();
$core = kms_core();
$sec = $core->security;
check('encrypt with explicit keys == v1 CBC of 8.5.26', $sec->encrypt('x','synthetic-key-1','synthetic-iv-1')===legacy_encrypt('x','synthetic-key-1','synthetic-iv-1'));
check('encrypt with ONE explicit key resolves the other as before (no v3)', $sec->encrypt('x','only-key')===legacy_encrypt('x','only-key','cfg-secret-synthetic'));
check('decrypt with explicit keys of a v1 value', $sec->decrypt(legacy_encrypt('x','k','i'),'k','i')==='x');
$cache_dir = $tmp_root.'/cache-h'; @mkdir($cache_dir,0700);
$core->cache->activateCacheFile($cache_dir);
$core->cache->set('kms-h',['a'=>1],null,'cache-key-synthetic','cache-iv-synthetic');
check('CoreCache set/get with keys while v3 is configured', $core->cache->get('kms-h',-1,'','cache-key-synthetic','cache-iv-synthetic')===['a'=>1]);
check('no KMS call in this section', $fake->total()===0, json_encode($fake->calls));
//endregion

//region i) environment variables
section('i) CF_KMS_DATA_KEYS / CF_KMS_DATA_KEY_PRIMARY');
$fake->reset();
putenv('CF_KMS_DATA_KEYS='.json_encode(['e'=>RES_B]));
putenv('CF_KMS_DATA_KEY_PRIMARY=e');
$core = kms_core(null,null);
$e = $core->security->encrypt('from env');
check('env configuration: v3:e:1:', strncmp((string)$e,'v3:e:1:',7)===0 && $core->security->decrypt($e)==='from env');
check('the env resource is used', $fake->last_request->getName()===RES_B);
$core = kms_core('a',['a'=>RES_A]);
check('the config wins over the env', strncmp((string)$core->security->encrypt('x'),'v3:a:1:',7)===0 && $fake->last_request->getName()===RES_A);
putenv('CF_KMS_DATA_KEYS'); putenv('CF_KMS_DATA_KEY_PRIMARY');
//endregion

//region j) encryptionVersionOf
section('j) encryptionVersionOf()');
$fake->reset();
$core = kms_core();
$sec = $core->security;
$v1 = legacy_encrypt('x','cfg-key-synthetic','cfg-secret-synthetic');
check("v1 -> 'v1'", $sec->encryptionVersionOf($v1)==='v1');
check("v2 -> 'v2'", $sec->encryptionVersionOf($sec->encryptV2('x'))==='v2');
check("v3 -> 'v3:a:1'", $sec->encryptionVersionOf($sec->encrypt('x'))==='v3:a:1');
check("v3 of an alias not configured -> 'v3:zz:9' (no lookup)", $sec->encryptionVersionOf('v3:zz:9:QUJD')==='v3:zz:9');
foreach (['',null,123,[],'plain text','v2:','v2:@@','v3:a:1:','v3:A:1:QUJD','QUJD'] as $bad)
    if($sec->encryptionVersionOf($bad)!==null) check('null for '.json_encode($bad), false);
check('null for empty, non-strings, plain text and malformed values', true);
check('encryptionVersionOf never calls KMS', $fake->calls['decrypt']===0);
//endregion

//region k) reEncrypt
section('k) reEncrypt()');
$fake->reset();
$core = kms_core();
$sec = $core->security;
$v2 = $sec->encryptV2('from v2');
$r = $sec->reEncrypt($v2);
check('v2 -> v3 of the primary', strncmp((string)$r,'v3:a:1:',7)===0 && $sec->decrypt($r)==='from v2');
$r = $sec->reEncrypt($v1);
check('v1 -> v3 of the primary', strncmp((string)$r,'v3:a:1:',7)===0 && $sec->decrypt($r)==='x');
$core_b = kms_core('b');
$old = $core_b->security->encrypt('from alias b','','','user-token');
check('a value of alias b', strncmp((string)$old,'v3:b:1:',7)===0);
$r = $sec->reEncrypt($old,'user-token');
check('v3 of an old alias -> v3 of the primary, with the same usage', strncmp((string)$r,'v3:a:1:',7)===0 && $sec->decrypt($r,'','','user-token')==='from alias b' && $sec->decrypt($r)===false);
$current = $sec->encrypt('already primary');
$calls_before = $fake->total();
check('v3 of the primary: returned unchanged, no KMS call', $sec->reEncrypt($current)===$current && $fake->total()===$calls_before);
$fake->versions[RES_A] = 2;
check('v3 of the primary with an older KMS version: unchanged (no metadata read)', $sec->reEncrypt($current)===$current && $fake->total()===$calls_before);
$fake->versions[RES_A] = 1;
check('wrong usage: false, nothing invented', $sec->reEncrypt($old,'other-usage')===false);
check('a value that can not be decrypted: false', $sec->reEncrypt(legacy_encrypt('x','other-key','other-iv'))===false);
check('not an encrypted value: false', $sec->reEncrypt('plain text')===false && $sec->reEncrypt('')===false);
$fake->mode = 'unavailable';
check('KMS down: false', $sec->reEncrypt($v2)===false);
$fake->reset();
$core_np = kms_core(null);
check('without a primary key: false and security-kms-not-configured', $core_np->security->reEncrypt($v2)===false && strpos(logs_text($core_np),'security-kms-not-configured')!==false);
$all_logs .= logs_text($core).logs_text($core_b).logs_text($core_np);
//endregion

//region l) no sensitive data in the logs
section('l) no plaintext in any log of the run');
foreach (['synthetic plaintext','bound to a usage','stored while KMS was up','from alias b','SECRET-PLAINTEXT-MARK','cfg-key-synthetic','cfg-secret-synthetic'] as $needle)
    if(strpos($all_logs,$needle)!==false) check('log leak: '.$needle, false);
$syslog_text = json_encode($syslog_lines);
check('no plaintext nor data key in the request logs', true);
check('no plaintext nor data key in syslog', strpos($syslog_text,'SECRET-PLAINTEXT-MARK')===false && strpos($syslog_text,'cfg-key-synthetic')===false && strpos($syslog_text,'stored while KMS was up')===false);
check('the real KeyManagementServiceClient was never used successfully', \Google\Cloud\Kms\V1\Client\KeyManagementServiceClient::$constructed<=1);
//endregion

//region SUMMARY
echo "\nversion: ".(new_core($tmp_root))->_version."\n";
echo "PHP warnings/notices captured during the whole run: ".count($php_warnings)."\n";
foreach ($php_warnings as $w) echo "  - $w\n";
check('no PHP warnings', count($php_warnings)===0);
echo "\n{$checks} checks, {$failures} failures\n";
exec('rm -rf '.escapeshellarg($tmp_root));
exit($failures?1:0);
//endregion
}
