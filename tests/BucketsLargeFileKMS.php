<?php
// CLOUD Documentum:
//   DevGroup: /cf/products/cloud-development
//   DevUnit: /cloud-development/frameworks/php
//   Activities: 6651158731161600
/**
 * Isolated tests of Buckets::encryptLargeFile() / decryptLargeFile() since 8.5.27, when the data key (DEK) is wrapped
 * with GoogleKMS instead of a KeyManagementServiceClient of Buckets.
 *
 * Contract under test: the format of the file does not change by a single byte of header
 * ([DEK length, 4 bytes big endian][wrapped DEK][IV, 12][GCM tag, 16][ciphertext]); a file encrypted with the code of
 * 8.5.26 decrypts with the new code and vice versa; the KMS call is the same (the cryptoKey, no AAD); initKMS() accepts
 * the same options (serviceAccount as array, JSON string or file path, and refuses anything else); a failure of KMS is
 * an error of the object, never an exception, and no target file is written.
 *
 * Self-contained: Cloud KMS is a fake client injected with GoogleKMS::$client_factory, the Google\Cloud\Kms\V1 classes
 * are stubs defined here, local temporary files replace gs:// paths and the Buckets object is built without its
 * constructor (no Cloud Storage). No network, no vendor/.
 *
 * Run from the repository root:
 *   php tests/BucketsLargeFileKMS.php
 * Exit code 0 when every check passes, 1 otherwise.
 */

//region STUBS of the Cloud KMS client library
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
            function __construct(array $options = []) { throw new \RuntimeException('stub: the real client must never be built in this suite'); }
            static function cryptoKeyName($p,$l,$r,$k) { return "projects/$p/locations/$l/keyRings/$r/cryptoKeys/$k"; }
        }
    }
}
//endregion

namespace {

use Google\Cloud\Kms\V1\EncryptRequest;
use Google\Cloud\Kms\V1\DecryptRequest;

//region ENVIRONMENT
foreach (['CF_CACHE_ENCRYPT_KEY','CF_CACHE_ENCRYPT_IV','PROJECT_ID','GOOGLE_APPLICATION_CREDENTIALS','GAE_SERVICE','K_SERVICE','FUNCTION_TARGET'] as $env_var) putenv($env_var);
$tmp_root = sys_get_temp_dir().'/cf-buckets-kms-test-'.getmypid();
@mkdir($tmp_root.'/files',0700,true);
file_put_contents($tmp_root.'/config.json','{}');
ini_set('session.save_path',$tmp_root);
ob_start();

$logger = new class { function __call($level,$args) {} };
$php_warnings = [];
set_error_handler(function ($errno, $errstr, $errfile, $errline) use (&$php_warnings) {
    if(!(error_reporting() & $errno)) return true; // silenced with @
    $php_warnings[] = "[$errno] $errstr ($errfile:$errline)";
    return true;
});

include_once __DIR__.'/../src/Core7.php';
include_once __DIR__.'/../src/class/GoogleKMS.php';
include_once __DIR__.'/../src/class/Buckets.php';
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

class FakeApiException extends \Exception {
    private $status;
    function __construct(string $status, int $code) { $this->status = $status; parent::__construct('synthetic '.$status,$code); }
    function getStatus() { return $this->status; }
}
class FakeEncryptResponse { function __construct(private $name, private $ciphertext) {} function getName() { return $this->name; } function getCiphertext() { return $this->ciphertext; } }
class FakeDecryptResponse { function __construct(private $plaintext) {} function getPlaintext() { return $this->plaintext; } }
/** Fake Cloud KMS (same scheme as tests/GoogleKMSAndV3.php). $mode: ok | unavailable | permission */
class FakeKMSClient {
    public $mode = 'ok'; public $calls = 0; public $requests = [];
    private function key(string $resource): string { return hash('sha256','synthetic-kms|'.$resource,true); }
    function encrypt($request, array $options = []) {
        $this->calls++; $this->requests[] = $request;
        if($this->mode==='unavailable') throw new FakeApiException('UNAVAILABLE',14);
        if($this->mode==='permission') throw new FakeApiException('PERMISSION_DENIED',7);
        $iv = random_bytes(12); $tag = '';
        $ct = openssl_encrypt($request->getPlaintext(),'aes-256-gcm',$this->key($request->getName()),OPENSSL_RAW_DATA,$iv,$tag,(string)$request->getAdditionalAuthenticatedData(),16);
        return new FakeEncryptResponse($request->getName().'/cryptoKeyVersions/1',$iv.$tag.$ct);
    }
    function decrypt($request, array $options = []) {
        $this->calls++; $this->requests[] = $request;
        if($this->mode==='unavailable') throw new FakeApiException('UNAVAILABLE',14);
        if($this->mode==='permission') throw new FakeApiException('PERMISSION_DENIED',7);
        $raw = $request->getCiphertext();
        $plain = openssl_decrypt(substr($raw,28),'aes-256-gcm',$this->key($request->getName()),OPENSSL_RAW_DATA,substr($raw,0,12),substr($raw,12,16),(string)$request->getAdditionalAuthenticatedData());
        if($plain===false) throw new FakeApiException('INVALID_ARGUMENT',3);
        return new FakeDecryptResponse($plain);
    }
}
$fake = new FakeKMSClient();
$factory_options = [];
GoogleKMS::$client_factory = function (array $options) use (&$fake, &$factory_options) { $factory_options[] = $options; return $fake; };

const KMS_OPTIONS = ['projectId'=>'synthetic-project','location'=>'europe-west1','keyRing'=>'synthetic-ring','keyId'=>'synthetic-files'];
const KEY_NAME = 'projects/synthetic-project/locations/europe-west1/keyRings/synthetic-ring/cryptoKeys/synthetic-files';

/** A Buckets object without its constructor (no Cloud Storage), with $core set */
function new_bucket(Core7 $core): Buckets {
    $ref = new ReflectionClass('Buckets');
    $b = $ref->newInstanceWithoutConstructor();
    $ref->getProperty('core')->setValue($b,$core);
    return $b;
}

/**
 * Literal copy of Buckets::encryptLargeFile() of 8.5.26 (with its own KMS client): the reference of the format.
 * $client plays the KeyManagementServiceClient of that version.
 */
function legacy_encrypt_large_file($client, string $keyName, string $sourceGsPath, string $targetGsPath): bool {
    $dek = random_bytes(32);
    $iv = random_bytes(openssl_cipher_iv_length('aes-256-gcm'));
    $encryptRequest = (new EncryptRequest())->setName($keyName)->setPlaintext($dek);
    $kmsResponse = $client->encrypt($encryptRequest);
    $encryptedDek = $kmsResponse->getCiphertext();
    $sourceHandle = fopen($sourceGsPath, 'rb');
    $targetHandle = fopen($targetGsPath, 'wb');
    fwrite($targetHandle, pack('N', strlen($encryptedDek)));
    fwrite($targetHandle, $encryptedDek);
    fwrite($targetHandle, $iv);
    $plaintext = stream_get_contents($sourceHandle);
    $tag = '';
    $ciphertext = openssl_encrypt($plaintext,'aes-256-gcm',$dek,OPENSSL_RAW_DATA,$iv,$tag,'',16);
    fwrite($targetHandle, $tag);
    fwrite($targetHandle, $ciphertext);
    fclose($sourceHandle);
    fclose($targetHandle);
    return true;
}
/** Literal copy of Buckets::decryptLargeFile() of 8.5.26 */
function legacy_decrypt_large_file($client, string $keyName, string $sourceGsPath, string $targetGsPath): bool {
    $sourceHandle = fopen($sourceGsPath, 'rb');
    $dekLength = unpack('N', fread($sourceHandle, 4))[1];
    $encryptedDek = fread($sourceHandle, $dekLength);
    $iv = fread($sourceHandle, openssl_cipher_iv_length('aes-256-gcm'));
    $tag = fread($sourceHandle, 16);
    $decryptRequest = (new DecryptRequest())->setName($keyName)->setCiphertext($encryptedDek);
    $dek = $client->decrypt($decryptRequest)->getPlaintext();
    $ciphertext = stream_get_contents($sourceHandle);
    $decryptedData = openssl_decrypt($ciphertext,'aes-256-gcm',$dek,OPENSSL_RAW_DATA,$iv,$tag);
    if ($decryptedData === false) return false;
    file_put_contents($targetGsPath, $decryptedData);
    fclose($sourceHandle);
    return true;
}
function f(string $name): string { global $tmp_root; return $tmp_root.'/files/'.$name; }
//endregion

$core = new Core7($tmp_root);
if(ob_get_level()) ob_end_flush();

$payloads = ['empty'=>'','small'=>'synthetic file content á €','binary'=>random_bytes(4096),'large'=>random_bytes(3*1024*1024+7)];

//region a) initKMS
section('a) initKMS() accepts the same options');
$b = new_bucket($core);
check('missing projectId: params-error', $b->initKMS(['location'=>'l','keyRing'=>'r','keyId'=>'k'])===false && $b->errorCode==='params-error');
$b = new_bucket($core);
check('missing keyId: params-error', $b->initKMS(['projectId'=>'p','location'=>'l','keyRing'=>'r'])===false && $b->errorCode==='params-error');
$b = new_bucket($core);
check('complete options: true', $b->initKMS(KMS_OPTIONS)===true && !$b->error);
check('getKMSKeyName() unchanged', $b->getKMSKeyName()===KEY_NAME);
$b = new_bucket($core);
check('serviceAccount invalid string: same params-error message', $b->initKMS(KMS_OPTIONS+['serviceAccount'=>'not-json-and-not-a-file'])===false && $b->errorCode==='params-error' && in_array('serviceAccount is not a valid JSON string nor an existing file path',$b->errorMsg,true));
file_put_contents(f('none-src'),'synthetic');
$factory_options = [];
$b = new_bucket($core);
$b->initKMS(KMS_OPTIONS+['serviceAccount'=>['type'=>'service_account','client_email'=>'synthetic@example.invalid']]);
$b->encryptLargeFile(f('none-src'),f('none-dst')); // forces the client
check('serviceAccount array forwarded as credentials', ($factory_options[0]['credentials']['client_email']??'')==='synthetic@example.invalid', json_encode($factory_options));
$factory_options = [];
$b = new_bucket($core);
$b->initKMS(KMS_OPTIONS+['serviceAccount'=>'{"type":"service_account","client_email":"json@example.invalid"}']);
$b->encryptLargeFile(f('none-src'),f('none-dst'));
check('serviceAccount JSON string forwarded decoded', ($factory_options[0]['credentials']['client_email']??'')==='json@example.invalid');
file_put_contents(f('sa.json'),'{"type":"service_account"}');
$factory_options = [];
$b = new_bucket($core);
$b->initKMS(KMS_OPTIONS+['serviceAccount'=>f('sa.json')]);
$b->encryptLargeFile(f('none-src'),f('none-dst'));
check('serviceAccount file path forwarded as the path', ($factory_options[0]['credentials']??'')===f('sa.json'));
$b = new_bucket($core);
check('encryptLargeFile without initKMS: params-error', $b->encryptLargeFile(f('x'),f('y'))===false && $b->errorCode==='params-error');
$b = new_bucket($core);
check('decryptLargeFile without initKMS: params-error', $b->decryptLargeFile(f('x'),f('y'))===false && $b->errorCode==='params-error');
//endregion

//region b) format and compatibility with 8.5.26
section('b) format of the file and compatibility with 8.5.26 in both directions');
foreach ($payloads as $label=>$content) {
    file_put_contents(f("src-$label"),$content);

    // old -> new
    @unlink(f("old-enc-$label")); @unlink(f("new-dec-$label"));
    legacy_encrypt_large_file($fake,KEY_NAME,f("src-$label"),f("old-enc-$label"));
    $b = new_bucket($core); $b->initKMS(KMS_OPTIONS);
    $ok = $b->decryptLargeFile(f("old-enc-$label"),f("new-dec-$label"));
    check("[$label] encrypted by 8.5.26, decrypted by 8.5.27", $ok && file_get_contents(f("new-dec-$label"))===$content, json_encode($b->errorMsg));

    // new -> old
    @unlink(f("new-enc-$label")); @unlink(f("old-dec-$label"));
    $b = new_bucket($core); $b->initKMS(KMS_OPTIONS);
    $fake->requests = [];
    $ok = $b->encryptLargeFile(f("src-$label"),f("new-enc-$label"));
    $wrap_request = $fake->requests[0] ?? null;
    check("[$label] encrypted by 8.5.27, decrypted by 8.5.26", $ok && legacy_decrypt_large_file($fake,KEY_NAME,f("new-enc-$label"),f("old-dec-$label")) && file_get_contents(f("old-dec-$label"))===$content, json_encode($b->errorMsg));
    check("[$label] the wrap request: the cryptoKey, no AAD", $wrap_request && $wrap_request->getName()===KEY_NAME && $wrap_request->aad_set===false && strlen($wrap_request->getPlaintext())===32);

    // header layout
    $raw = file_get_contents(f("new-enc-$label"));
    $len = unpack('N',substr($raw,0,4))[1];
    check("[$label] layout: [4 len][wrapped DEK][12 iv][16 tag][ciphertext]", strlen($raw)===4+$len+12+16+strlen($content));
    $raw_old = file_get_contents(f("old-enc-$label"));
    check("[$label] same header structure and size as 8.5.26", strlen($raw_old)===strlen($raw) && unpack('N',substr($raw_old,0,4))[1]===$len);
}
//endregion

//region c) failures
section('c) failures: errors of the object, no exception, no target written');
file_put_contents(f('src-fail'),'payload of the failure tests');
$fake->mode = 'unavailable'; $fake->calls = 0;
$b = new_bucket($core); $b->initKMS(KMS_OPTIONS);
@unlink(f('enc-fail'));
check('KMS unavailable: encryptLargeFile false, kms-unavailable', $b->encryptLargeFile(f('src-fail'),f('enc-fail'))===false && $b->errorCode==='kms-unavailable');
check('KMS unavailable: 2 attempts (one retry)', $fake->calls===2);
check('KMS unavailable: no target file written', !file_exists(f('enc-fail')));
$fake->mode = 'ok';
$b = new_bucket($core); $b->initKMS(KMS_OPTIONS);
$b->encryptLargeFile(f('src-fail'),f('enc-ok'));
$fake->mode = 'permission'; $fake->calls = 0;
$b = new_bucket($core); $b->initKMS(KMS_OPTIONS);
@unlink(f('dec-fail'));
check('KMS permission denied: decryptLargeFile false, kms-permission-denied, 1 attempt', $b->decryptLargeFile(f('enc-ok'),f('dec-fail'))===false && $b->errorCode==='kms-permission-denied' && $fake->calls===1);
check('KMS permission denied: no target file written', !file_exists(f('dec-fail')));
$fake->mode = 'ok';
$raw = file_get_contents(f('enc-ok'));
$raw[strlen($raw)-1] = chr(ord($raw[strlen($raw)-1]) ^ 1);
file_put_contents(f('enc-tampered'),$raw);
$b = new_bucket($core); $b->initKMS(KMS_OPTIONS);
check('tampered ciphertext: false with the integrity error', $b->decryptLargeFile(f('enc-tampered'),f('dec-tampered'))===false && in_array('Decryption failed (Integrity check failed).',$b->errorMsg,true));
check('errorMsg never holds the content', strpos(json_encode($b->errorMsg),'payload of the failure tests')===false);
//endregion

//region SUMMARY
echo "\nPHP warnings/notices captured during the whole run: ".count($php_warnings)."\n";
foreach ($php_warnings as $w) echo "  - $w\n";
check('no PHP warnings', count($php_warnings)===0);
echo "\n{$checks} checks, {$failures} failures\n";
exec('rm -rf '.escapeshellarg($tmp_root));
exit($failures?1:0);
//endregion
}
