<?php
/**
 * Isolated tests of the TLS certificate verification of CoreRequest (call() through streams and getCurl() through cURL)
 * and of the Firebase class, since 8.5.15.
 *
 * Self-contained: a temporary test CA and certificates generated with the openssl command, and local TLS servers on
 * 127.0.0.1 started in a child PHP process. No external network, no Redis, no vendor/ autoload and no config.json of an
 * application. Every key and token is synthetic.
 *
 * Run from the repository root (needs the openssl command, 3.4 or later, and the curl and openssl extensions):
 *   php tests/CoreRequestTLS.php
 * Exit code 0 when every check passes, 1 otherwise.
 */

//region ENVIRONMENT: clean env vars, temporary root and a logger that captures syslog warnings
foreach (['CF_ENCRYPT_KEY','CF_ENCRYPT_SECRET','CF_CACHE_ENCRYPT_KEY','CF_CACHE_ENCRYPT_IV','PROJECT_ID','REDIS_HOST','REDIS_PORT','GOOGLE_APPLICATION_CREDENTIALS'] as $env_var) putenv($env_var);
unset($_SERVER['GAE_SERVICE']);

$tmp_root = sys_get_temp_dir().'/cf-core-tls-test-'.getmypid();
@mkdir($tmp_root.'/local_data/cache',0700,true);
@mkdir($tmp_root.'/certs',0700,true);
file_put_contents($tmp_root.'/config.json','{}'); // app config.json included by src/config.json
ini_set('session.save_path',$tmp_root);
ob_start(); // Core7 starts a PHP session in terminal mode: no output may be sent before it

$syslog_lines = [];
$logger = new class($syslog_lines) {
    private $lines;
    function __construct(array &$lines) { $this->lines = &$lines; }
    function __call($level,$args) { $this->lines[] = [$level,$args[0]??'']; }
};

// The application's error handler: CoreRequest::call() must keep it in the chain
$php_warnings = [];
set_error_handler(function ($errno, $errstr, $errfile, $errline) use (&$php_warnings) {
    $php_warnings[] = "[$errno] $errstr ($errfile:$errline)";
    return true;
});

include_once __DIR__.'/../src/Core7.php';
include_once __DIR__.'/../src/class/Firebase.php';
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
function count_syslog(array $lines, string $needle): int {
    $n = 0;
    foreach ($lines as $line) if(strpos($line[1],$needle)!==false) $n++;
    return $n;
}
function sh(string $cmd): void {
    exec($cmd.' 2>&1', $out, $rc);
    if($rc!==0) { fwrite(STDERR, "command failed: $cmd\n".implode("\n",$out)."\n"); exit(1); }
}
/** New Core7 with a clean request object and synthetic configuration */
function new_core(string $root, array $config = []): Core7 {
    $core = new Core7($root);
    foreach ($config as $key=>$value) $core->config->set($key,$value);
    $core->request = new CoreRequest($core);
    $core->request->sendSysLogs = false;
    $core->request->automaticHeaders = false;
    return $core;
}
function reset_errors(Core7 $core): void { $core->request->error = false; $core->request->errorMsg = []; }
function tls_error(Core7 $core): bool { return strpos(json_encode($core->request->errorMsg),'tls-verify-failed')!==false; }
//endregion

//region CERTIFICATES: test CA, a second CA nobody trusts, and one certificate per case
$c = $tmp_root.'/certs';
$ext_localhost = "$c/localhost.ext"; file_put_contents($ext_localhost,"subjectAltName=DNS:localhost\nbasicConstraints=CA:FALSE\n");
$ext_other = "$c/other.ext"; file_put_contents($ext_other,"subjectAltName=DNS:other.invalid\nbasicConstraints=CA:FALSE\n");
sh("openssl req -x509 -newkey rsa:2048 -nodes -keyout $c/ca.key -out $c/ca.pem -days 2 -subj '/CN=CF Test CA'");
sh("openssl req -x509 -newkey rsa:2048 -nodes -keyout $c/ca2.key -out $c/ca2.pem -days 2 -subj '/CN=CF Untrusted CA'");
$certs = [
    'valid'      => ['ca'=>'ca',  'ext'=>$ext_localhost, 'dates'=>'-days 2'],
    'expired'    => ['ca'=>'ca',  'ext'=>$ext_localhost, 'dates'=>'-not_before 20200101000000Z -not_after 20200102000000Z'],
    'wrongname'  => ['ca'=>'ca',  'ext'=>$ext_other,     'dates'=>'-days 2'],
    'untrusted'  => ['ca'=>'ca2', 'ext'=>$ext_localhost, 'dates'=>'-days 2'],
];
foreach ($certs as $name=>$cfg) {
    // PHP streams fall back to the CN when the SAN does not match (cURL does not), so the wrong-name certificate is wrong in both
    $cn = ($name==='wrongname') ? 'other.invalid' : 'localhost';
    sh("openssl req -newkey rsa:2048 -nodes -keyout $c/$name.key -out $c/$name.csr -subj '/CN=$cn'");
    sh("openssl x509 -req -in $c/$name.csr -CA $c/{$cfg['ca']}.pem -CAkey $c/{$cfg['ca']}.key -CAcreateserial -out $c/$name.crt {$cfg['dates']} -extfile {$cfg['ext']}");
    file_put_contents("$c/$name.pem", file_get_contents("$c/$name.crt").file_get_contents("$c/$name.key"));
}
sh("openssl req -x509 -newkey rsa:2048 -nodes -keyout $c/selfsigned.key -out $c/selfsigned.crt -days 2 -subj '/CN=localhost' -addext 'subjectAltName=DNS:localhost'");
file_put_contents("$c/selfsigned.pem", file_get_contents("$c/selfsigned.crt").file_get_contents("$c/selfsigned.key"));
$cases = ['valid','expired','wrongname','untrusted','selfsigned'];
//endregion

//region SERVERS: one TLS server per certificate in a child PHP process; it echoes the request headers as JSON
$server_php = $tmp_root.'/server.php';
file_put_contents($server_php, <<<'PHP'
<?php
[$self, $cert_dir, $ports_file, $stop_file] = $argv;
$servers = [];
foreach (['valid','expired','wrongname','untrusted','selfsigned'] as $name) {
    $ctx = stream_context_create(['ssl'=>['local_cert'=>"$cert_dir/$name.pem",'verify_peer'=>false]]);
    $s = stream_socket_server('tls://127.0.0.1:0', $errno, $errstr, STREAM_SERVER_BIND|STREAM_SERVER_LISTEN, $ctx);
    if(!$s) { fwrite(STDERR,"$name: $errstr\n"); exit(1); }
    $servers[$name] = $s;
}
$ports = [];
foreach ($servers as $name=>$s) $ports[$name] = (int)substr(strrchr(stream_socket_get_name($s,false),':'),1);
file_put_contents($ports_file, json_encode($ports));
$until = time()+120;
while (time() < $until && !file_exists($stop_file)) {
    $read = array_values($servers); $w = $e = null;
    if(!@stream_select($read, $w, $e, 1)) continue;
    foreach ($read as $s) {
        $conn = @stream_socket_accept($s, 2);   // the TLS handshake happens here; a client that rejects the certificate makes it fail
        if(!$conn) continue;
        stream_set_timeout($conn, 2);
        $head = '';
        while (!feof($conn) && strpos($head,"\r\n\r\n")===false) { $chunk = fread($conn, 8192); if($chunk===false || $chunk==='') break; $head .= $chunk; }
        $lines = explode("\r\n", substr($head, 0, (int)strpos($head,"\r\n\r\n")));
        $request_line = array_shift($lines);
        $body = json_encode(['ok'=>true,'request'=>$request_line,'headers'=>$lines]);
        @fwrite($conn, "HTTP/1.1 200 OK\r\nContent-Type: application/json\r\nContent-Length: ".strlen($body)."\r\nConnection: close\r\n\r\n".$body);
        @fclose($conn);
    }
}
PHP);
$ports_file = $tmp_root.'/ports.json'; $stop_file = $tmp_root.'/stop';
$server = proc_open([PHP_BINARY, $server_php, $c, $ports_file, $stop_file], [1=>['file','/dev/null','w'],2=>['file',$tmp_root.'/server.err','w']], $pipes);
for ($i=0; $i<100 && !is_file($ports_file); $i++) usleep(50000);
$ports = json_decode(@file_get_contents($ports_file) ?: 'null', true);
if(!is_array($ports) || count($ports)!==5) { fwrite(STDERR,"TLS servers did not start: ".@file_get_contents($tmp_root.'/server.err')."\n"); exit(1); }
register_shutdown_function(function () use ($stop_file, $server, $tmp_root) {
    @touch($stop_file);
    if(is_resource($server)) proc_close($server);
    exec('rm -rf '.escapeshellarg($tmp_root));
});
function url(string $case, string $path = '/echo'): string { global $ports; return "https://localhost:{$ports[$case]}{$path}"; }
//endregion

$core = new_core($tmp_root); // first Core7: starts the session before any output
flush_output();
echo "CoreRequest TLS verification (".$core->_version.")\n";

//region a) default configuration: certificates are verified (the test CA is not in the system store)
section('a) default configuration: the certificate is verified');
$core = new_core($tmp_root);
check('default_options verify_peer and verify_peer_name are true', ($core->request->default_options['ssl']['verify_peer']??null)===true && ($core->request->default_options['ssl']['verify_peer_name']??null)===true);
foreach ($cases as $case) {
    reset_errors($core);
    $ret = $core->request->get(url($case));
    check("stream: $case is rejected without the test CA (tls-verify-failed)", $ret===false && tls_error($core), json_encode($core->request->errorMsg));
    reset_errors($core);
    $ret = $core->request->getCurl(url($case));
    check("curl: $case is rejected without the test CA (tls-verify-failed)", $ret===false && tls_error($core), json_encode($core->request->errorMsg));
}
//endregion

//region b) CA bundle configured: only the valid certificate is accepted
section('b) core.request.ssl.cafile = test CA: valid works, every bad certificate fails');
$core = new_core($tmp_root, ['core.request.ssl.cafile'=>"$c/ca.pem"]);
foreach ($cases as $case) {
    foreach (['stream'=>'get','curl'=>'getCurl'] as $label=>$method) {
        reset_errors($core);
        $ret = $core->request->$method(url($case));
        $ok_body = is_string($ret) && strpos($ret,'"ok":true')!==false;
        if($case==='valid') check("$label: valid certificate works", $ok_body && !$core->request->error, json_encode($core->request->errorMsg));
        else check("$label: $case fails with tls-verify-failed", $ret===false && tls_error($core), json_encode($core->request->errorMsg));
    }
}
reset_errors($core);
$ret = $core->request->post_json_decode(url('valid'), ['a'=>1], null, true);
check('stream: post_json_decode against the valid certificate', ($ret['ok']??false)===true && strpos($ret['request']??'','POST ')===0, json_encode($ret));
$warnings_before = count($php_warnings);
reset_errors($core);
$core->request->get(url('expired'));
check('the application error handler still receives the warnings of the failed call', count($php_warnings) > $warnings_before && strpos(implode(' ',array_slice($php_warnings,$warnings_before)),'certificate verify failed')!==false);
$probe = function () {}; $previous = set_error_handler($probe); restore_error_handler();
check('call() restores the error handler of the application', $previous !== null && $previous !== $probe);
check('the tls-verify-failed error names the host, not the URL', strpos(json_encode($core->request->errorMsg),'host localhost')!==false && strpos(json_encode($core->request->errorMsg),'tls-verify-failed: the TLS certificate of host localhost could not be verified (file_get_contents(https')===false);
reset_errors($core);
$ret = $core->request->get_json_decode(url('selfsigned'));
check('stream: get_json_decode against a self-signed certificate fails', $core->request->error && tls_error($core), json_encode($core->request->errorMsg));
check('no warning security-tls-verify-disabled while verifying', count_syslog($syslog_lines,'security-tls-verify-disabled')===0, json_encode($syslog_lines));
//endregion

//region c) explicit exception for one call: works, leaves a trace, does not stay for the next calls
section('c) $extra_headers[\'ssl\'] applies to one call only and leaves a trace');
foreach (['stream'=>'get','curl'=>'getCurl'] as $label=>$method) {
    $core = new_core($tmp_root);
    $before = $core->request->default_options;
    $syslog_before = count($syslog_lines);
    reset_errors($core);
    $insecure = ['ssl'=>['verify_peer'=>false,'verify_peer_name'=>false]];
    $ret = $method==='get' ? $core->request->get(url('selfsigned','/echo?token=secret-token-synthetic'), null, $insecure)
                           : $core->request->getCurl(url('selfsigned','/echo?token=secret-token-synthetic'), null, 'GET', $insecure);
    check("$label: the explicit exception reaches the self-signed host", is_string($ret) && strpos($ret,'"ok":true')!==false, json_encode($core->request->errorMsg));
    check("$label: the ssl key is not sent as an HTTP header", is_string($ret) && stripos($ret,'ssl:')===false, (string)$ret);
    $new_lines = array_slice($syslog_lines,$syslog_before);
    check("$label: warning security-tls-verify-disabled once, with the host", count_syslog($new_lines,'security-tls-verify-disabled [verify_peer,verify_peer_name] for host localhost')===1 && ($new_lines[0][0]??'')==='warning', json_encode($new_lines));
    check("$label: the warning never contains the URL query", count_syslog($new_lines,'secret-token-synthetic')===0);
    check("$label: default_options unchanged by the call", $core->request->default_options===$before, json_encode($core->request->default_options));
    reset_errors($core);
    $ret = $method==='get' ? $core->request->get(url('selfsigned')) : $core->request->getCurl(url('selfsigned'));
    check("$label: the next call without exception verifies again", $ret===false && tls_error($core), json_encode($core->request->errorMsg));
    $syslog_before = count($syslog_lines);
    reset_errors($core);
    $method==='get' ? $core->request->get(url('selfsigned'), null, $insecure) : $core->request->getCurl(url('selfsigned'), null, 'GET', $insecure);
    check("$label: one warning per host and request", count_syslog(array_slice($syslog_lines,$syslog_before),'security-tls-verify-disabled')===0);
}
$core = new_core($tmp_root);
reset_errors($core);
$ret = $core->request->get(url('valid'), null, ['ssl'=>['not_a_parameter'=>1]]);
check('stream: an unsupported ssl parameter returns false with an error', $ret===false && strpos(json_encode($core->request->errorMsg),'not_a_parameter')!==false);
reset_errors($core);
$ret = $core->request->getCurl(url('valid'), null, 'GET', ['ssl'=>['not_a_parameter'=>1]]);
check('curl: an unsupported ssl parameter returns false with an error', $ret===false && strpos(json_encode($core->request->errorMsg),'not_a_parameter')!==false);
reset_errors($core);
$ret = $core->request->get(url('selfsigned'), null, ['ssl'=>['verify_peer'=>0,'verify_peer_name'=>0]]);
check('stream: 0 (not only false) also counts as an explicit exception and is traced', is_string($ret) && count_syslog($syslog_lines,'security-tls-verify-disabled')>=3);
//endregion

//region d) configuration opt-out and request-level parameters
section('d) core.request.ssl_verify and addSSLParameter()');
$syslog_before = count($syslog_lines);
$core = new_core($tmp_root, ['core.request.ssl_verify'=>false]);
reset_errors($core);
$ret = $core->request->get(url('selfsigned'));
check('stream: core.request.ssl_verify=false reaches the self-signed host', is_string($ret) && strpos($ret,'"ok":true')!==false, json_encode($core->request->errorMsg));
reset_errors($core);
$ret = $core->request->getCurl(url('selfsigned'));
check('curl: core.request.ssl_verify=false reaches the self-signed host', is_string($ret) && strpos($ret,'"ok":true')!==false, json_encode($core->request->errorMsg));
check('core.request.ssl_verify=false leaves the warning', count_syslog(array_slice($syslog_lines,$syslog_before),'security-tls-verify-disabled')===1);
foreach (['false','0',0,null,''] as $value) {
    $core = new_core($tmp_root, ['core.request.ssl_verify'=>$value]);
    reset_errors($core);
    $ret = $core->request->get(url('selfsigned'));
    check('core.request.ssl_verify='.json_encode($value).' does not turn the verification off', $ret===false && tls_error($core));
}
$core = new_core($tmp_root);
$core->request->addSSLParameter('cafile', "$c/ca.pem");
reset_errors($core);
$ret = $core->request->get(url('valid'));
check('stream: addSSLParameter(cafile) applies to the request (CloudAEAT pattern)', is_string($ret) && strpos($ret,'"ok":true')!==false, json_encode($core->request->errorMsg));
reset_errors($core);
$ret = $core->request->getCurl(url('valid'));
check('curl: addSSLParameter(cafile) is honoured by getCurl()', is_string($ret) && strpos($ret,'"ok":true')!==false, json_encode($core->request->errorMsg));
reset_errors($core);
$ret = $core->request->get(url('expired'));
check('addSSLParameter(cafile) still rejects the expired certificate', $ret===false && tls_error($core));
check('addSSLParameter() rejects an unsupported parameter', $core->request->addSSLParameter('not_a_parameter', 1)===false);
$core = new_core($tmp_root, ['core.request.ssl.cafile'=>"$c/ca2.pem"]);
$core->request->addSSLParameter('cafile', "$c/ca.pem");
reset_errors($core);
$ret = $core->request->get(url('valid'));
check('addSSLParameter(cafile) takes precedence over core.request.ssl.cafile', is_string($ret) && strpos($ret,'"ok":true')!==false);
//endregion

//region e) Firebase class
section('e) Firebase verifies the certificate');
$core = new_core($tmp_root, ['core.request.ssl.cafile'=>"$c/ca.pem"]);
$fb = new Firebase($core, [url('valid','/'), 'synthetic-token']);
$ret = $fb->get('path');
check('Firebase: valid certificate works', is_string($ret) && strpos($ret,'"ok":true')!==false, var_export($ret,true));
foreach (['selfsigned','expired','wrongname'] as $case) {
    $fb = new Firebase($core, [url($case,'/'), 'synthetic-token']);
    $ret = $fb->get('path');
    check("Firebase: $case is rejected", $ret===false);
}
$core = new_core($tmp_root);
$fb = new Firebase($core, [url('valid','/'), 'synthetic-token']);
check('Firebase: without the test CA the valid certificate is rejected (default verifies)', $fb->get('path')===false);
//endregion

echo "\n".($failures ? "FAILED: $failures of $checks checks" : "PASSED: $checks checks")."\n";
exit($failures ? 1 : 0);
