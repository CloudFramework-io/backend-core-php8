<?php
/**
 * Isolated tests of what RESTful::send() writes to syslog, since 8.5.20. The error line carries only the status, the
 * code and the number of errors, and the line of a queued or cron call only the status and the code. errors->data and
 * the response may hold what the caller sent or what a service answered, so they stay in the response and out of the logs.
 *
 * Self-contained: no external network, no Datastore, no Redis, no vendor/ autoload and no config.json of an application.
 * Every value is synthetic (SYNTH_*).
 *
 * Run from the repository root:
 *   php tests/RESTfulSysLog.php
 * Point it to another src/ (e.g. an older version, to see the suite fail) with:
 *   CF_TEST_SRC=/path/to/src php tests/RESTfulSysLog.php
 * Exit code 0 when every check passes, 1 otherwise.
 */

//region ENVIRONMENT: clean env vars, temporary root and a logger that captures syslog lines
foreach (['CF_ENCRYPT_KEY','CF_ENCRYPT_SECRET','CF_CACHE_ENCRYPT_KEY','CF_CACHE_ENCRYPT_IV','PROJECT_ID','REDIS_HOST','REDIS_PORT'] as $var) putenv($var);
unset($_SERVER['GAE_SERVICE'], $_SERVER['HTTP_REFERER'], $_SERVER['HTTP_X_APPENGINE_CRON']);

$tmp_root = sys_get_temp_dir().'/cf-core-restful-test-'.getmypid();
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

$core = new Core7($tmp_root);
ob_end_clean();

/** A RESTful with no errors and an empty syslog */
function new_api(): RESTful {
    global $core, $syslog_lines;
    $syslog_lines = [];
    $core->errors->lines = 0;
    $core->errors->data = [];
    return new RESTful($core);
}
/** The lines sent by send() with the label of its error line */
function restful_lines(): array {
    global $syslog_lines;
    return array_values(array_filter($syslog_lines, fn($line) => str_contains((string)$line[1], 'CloudFramework RESTFul: ')));
}
/** The json of the error line, or [] when it is not there */
function restful_json(): array {
    $lines = restful_lines();
    if(!$lines) return [];
    return json_decode(substr($lines[0][1], strlen('CloudFramework RESTFul: ')), true) ?: [];
}
//endregion

//region a) an error with values
section('a) an error with values');
$api = new_api();
$api->setError(['client_secret'=>'SYNTH_SECRET_1','answer'=>'SYNTH_ANSWER_2'], 503, 'system-error');
$response = $api->send(false, true);
check('there is one error line', count(restful_lines()) === 1);
check('it is sent as an error', (restful_lines()[0][0] ?? '') === 'error');
check('it does not hold the values', !str_contains(json_encode($syslog_lines), 'SYNTH_'), substr(json_encode($syslog_lines), 0, 200));
check('it does not hold the keys of the data', !str_contains(json_encode($syslog_lines), 'client_secret'));
check('it says the status', (restful_json()['status'] ?? null) === 503);
check('it says the code', (restful_json()['code'] ?? null) === 'system-error');
check('it says the number of errors', (restful_json()['lines'] ?? null) === 1);
check('the client still receives the data', str_contains((string)$response, 'SYNTH_SECRET_1'));
//endregion

//region b) setErrorFromCodelib with the extra message
section('b) setErrorFromCodelib with a message');
$api = new_api();
$api->setErrorFromCodelib('params-error', 'SYNTH_MESSAGE_3');
$response = $api->send(false, true);
check('the message is not in the line', !str_contains(json_encode($syslog_lines), 'SYNTH_'));
check('the code of the library is in the line', (restful_json()['code'] ?? null) === 'params-error');
check('its status is in the line', (restful_json()['status'] ?? null) === $api->getReturnStatus());
check('the client still receives the message', str_contains((string)$response, 'SYNTH_MESSAGE_3'));
//endregion

//region c) several errors and data of the response with the same names
section('c) several errors, and returnData with the names status and code');
$api = new_api();
$api->setError('SYNTH_FIRST_4', 400, 'first-error');
$core->errors->add(['SYNTH_SECOND_5']);
$api->returnData = ['status'=>'SYNTH_STATUS_6', 'code'=>'SYNTH_CODE_7'];
$api->send(false, true);
check('the number of errors is 2', (restful_json()['lines'] ?? null) === 2);
check('no value is in the line', !str_contains(json_encode($syslog_lines), 'SYNTH_'));
check('the status is the one of the API, not the one of returnData', (restful_json()['status'] ?? null) === 400);
check('the code is the one of the API, not the one of returnData', (restful_json()['code'] ?? null) === 'first-error');
//endregion

//region d) a response with no errors
section('d) a response with no errors');
$api = new_api();
$api->addReturnData(['value'=>'SYNTH_OK_8']);
$api->send(false, true);
check('there is no error line', restful_lines() === []);
//endregion

//region e) a queued call
section('e) a queued call');
$api = new_api();
$api->formParams = ['cloudframework_queued'=>1];
$api->addReturnData(['value'=>'SYNTH_QUEUED_9']);
$response = $api->send(false, true);
check('the response is not in the logs', !str_contains(json_encode($syslog_lines), 'SYNTH_'), substr(json_encode($syslog_lines), 0, 200));
check('the line of the queue is written', (bool)array_filter($syslog_lines, fn($line) => str_starts_with((string)$line[1], 'RESULT FROM QUEUE:')));
check('it is a debug line', (bool)array_filter($syslog_lines, fn($line) => $line[0] === 'debug' && str_starts_with((string)$line[1], 'RESULT FROM QUEUE:')));
check('it says the status', (bool)array_filter($syslog_lines, fn($line) => str_contains((string)$line[1], '"status":200')));
check('the caller still receives the response', str_contains((string)$response, 'SYNTH_QUEUED_9'));

$api = new_api();
$api->formParams = ['cloudframework_queued'=>0];
$api->addReturnData(['value'=>'SYNTH_QUEUED_10']);
$api->send(false, true);
check('a flag with no value does not write the response', !str_contains(json_encode($syslog_lines), 'SYNTH_'));
check('it does not write a line with an empty title', !array_filter($syslog_lines, fn($line) => str_starts_with((string)$line[1], ': ')));
//endregion

//region f) a queued call that ends with an error
section('f) a queued call that ends with an error');
$api = new_api();
$api->formParams = ['cloudframework_queued'=>1];
$api->setError(['client_secret'=>'SYNTH_SECRET_11'], 503, 'system-error');
$response = $api->send(false, true);
check('the error is not in the logs', !str_contains(json_encode($syslog_lines), 'SYNTH_'), substr(json_encode($syslog_lines), 0, 200));
check('its keys are not in the logs', !str_contains(json_encode($syslog_lines), 'client_secret'));
check('it is sent to the queue in queued_return_error', str_contains((string)$response, 'queued_return_error') && str_contains((string)$response, 'SYNTH_SECRET_11'));
check('the queue receives a 200, to avoid recalls', $api->getReturnStatus() === 200);
//endregion

echo "\n".($failures ? "FAILED: {$failures} of {$checks} checks" : "PASSED: {$checks} checks")."\n";
exec('rm -rf '.escapeshellarg($tmp_root));
exit($failures ? 1 : 0);
