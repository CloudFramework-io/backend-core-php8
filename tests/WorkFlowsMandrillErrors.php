<?php
// CLOUD Documentum:
//   DevGroup: /cf/products/cloud-platform
//   Process: /cloud-platform · SubProcess: 4693650502582272
//   DevUnit: /cloud-development/frameworks/php, /cloud-channels/email/cfmail
//   Activities: 4671542084763648
/**
 * Isolated tests of the Mandrill error guard of WorkFlows (since 8.5.24, task 4671542084763648).
 *
 * Contract under test: the Mailchimp Transactional client RETURNS the GuzzleHttp RequestException on an HTTP error of the
 * API instead of throwing it. Every WorkFlows method that calls Mandrill turns that return (or a thrown Throwable) into
 * an error of the class with the status, name and message that Mandrill sent, and never with the request, the API key or
 * a recipient. A failed render stops sendPlatformEmail() before it writes anything, and a failed send is never reported
 * as a success.
 *
 * Self-contained: test doubles for Core7, CFOs and the Mailchimp client. No network, no Datastore, no vendor/ autoload.
 *
 * Run from the repository root:
 *   php tests/WorkFlowsMandrillErrors.php
 * Exit code 0 when every check passes, 1 otherwise.
 */

//region DOUBLES
const SYNTH_KEY = 'synthetic-mandrill-key-must-never-appear';
const RECIPIENT = 'client.recipient@example.test';

/** Stand-in of the Mailchimp client: the typed property WorkFlows::$mandrill needs this class name */
if(!class_exists('MailchimpTransactional\ApiClient')) {
    eval('namespace MailchimpTransactional; class ApiClient { public $templates; public $messages; public $webhooks; public $senders; private $k = null; function setApiKey($k){ $this->k = $k; } function getApiKey(){ return $this->k; } }');
}
include_once __DIR__.'/../src/class/WorkFlows.php';

/** One API group of the client (templates, messages...): each method returns, or throws, what the test configured */
class FakeApiGroup {
    public $returns = []; public $calls = [];
    function __call($method, $args) {
        $this->calls[] = $method;
        $value = $this->returns[$method] ?? null;
        if($value instanceof Closure) return $value();
        return $value;
    }
}
class FakeResponse {
    private $status; private $body;
    function __construct(int $status, string $body) { $this->status = $status; $this->body = $body; }
    function getStatusCode() { return $this->status; }
    function getBody() { return $this->body; }
}
/** Same shape as GuzzleHttp\Exception\RequestException: its message quotes the request, getResponse() has the body */
class FakeRequestException extends RuntimeException {
    private $response;
    function __construct(int $status, array $body) {
        parent::__construct('Client error: `POST https://mandrillapp.com/api/1.0/x.json` request {"key":"'.SYNTH_KEY.'","to":"'.RECIPIENT.'"}');
        $this->response = new FakeResponse($status, json_encode($body));
    }
    function getResponse() { return $this->response; }
}
/** A network failure: a Throwable without response */
class FakeConnectException extends RuntimeException {}

class FakeDS {
    public $error = false; public $errorMsg = []; public $rows; public $writes = 0;
    function __construct(array $rows) { $this->rows = $rows; }
    function fetchOneByKey($key) { return $this->rows[$key] ?? null; }
    function fetchOne($fields, $where) { return []; }
    function createEntities($entity) { $this->writes++; return [$entity + ['KeyId' => 1]]; }
}
class FakeCFOs {
    public $ds;
    function __construct(FakeDS $ds) { $this->ds = $ds; }
    function useCFOSecret($v = true) {}
    function ds($entity) { return $this->ds; }
}
class FakeIs { function validEmail($e) { return (bool)filter_var($e, FILTER_VALIDATE_EMAIL); } }
class FakeLogs { public $lines = []; function add($msg, $group = '', $level = '') { $this->lines[] = is_string($msg) ? $msg : json_encode($msg); } }
class FakeCore {
    public $is; public $logs;
    function __construct() { $this->is = new FakeIs(); $this->logs = new FakeLogs(); }
    function jsonEncode($v) { return json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); }
    function jsonDecode($v, $assoc = true) { return json_decode($v, true); }
    function utf8Encode($v) { return $v; }
    function replaceCloudFrameworkTagsAndVariables($html, $data) { return $html; }
}

/** @return array [WorkFlows, FakeDS, ApiClient] with a fresh cached template 'tpl-ok' */
function build(): array {
    $ds = new FakeDS(['tpl-ok' => ['KeyName' => 'tpl-ok', 'DateUpdating' => date('Y-m-d H:i:s'), 'TemplateHTML' => '<p>x</p>', 'Cat' => 'TEST']]);
    $wf = (new ReflectionClass('WorkFlows'))->newInstanceWithoutConstructor();
    $wf->core = new FakeCore();
    $wf->cfos = new FakeCFOs($ds);
    $client = new MailchimpTransactional\ApiClient();
    foreach (['templates', 'messages', 'webhooks', 'senders'] as $group) $client->$group = new FakeApiGroup();
    $client->setApiKey(SYNTH_KEY);
    $wf->mandrill = $client;
    return [$wf, $ds, $client];
}
function unknown_template(): FakeRequestException { return new FakeRequestException(500, ['status' => 'error', 'code' => 5, 'name' => 'Unknown_Template', 'message' => 'No such template "tpl-ok"']); }
//endregion

//region HELPERS
$checks = 0; $failures = 0;
function check(string $name, bool $ok): void {
    global $checks, $failures;
    $checks++; if(!$ok) $failures++;
    echo ($ok ? '  ok   ' : '  FAIL ').$name."\n";
}
/** Nothing of the request (key, recipient, URL quoted by Guzzle) in the errors nor in a value returned */
function clean($wf, $returned = null): bool {
    $text = json_encode($wf->errorMsg).json_encode($returned).implode("\n", $wf->core->logs->lines);
    foreach ([SYNTH_KEY, RECIPIENT, 'mandrillapp.com', 'Client error'] as $needle) if(strpos($text, $needle) !== false) return false;
    return true;
}
function errors($wf): string { return json_encode($wf->errorMsg); }
function run(callable $fn) { try { return $fn(); } catch (Throwable $e) { return 'THROWN '.get_class($e); } }
//endregion

echo "WorkFlows: Mandrill errors returned or thrown by the client\n";

//region 1. getMandrillTemplate: returned exception -> false with the Mandrill name and status
[$wf, , $client] = build();
$client->templates->returns['info'] = unknown_template();
$ret = run(fn() => $wf->getMandrillTemplate('tpl-ok'));
check('getMandrillTemplate: returns false', $ret === false);
check('getMandrillTemplate: error with status and Mandrill name', $wf->error === true && strpos(errors($wf), 'Mandrill error 500 Unknown_Template') !== false);
check('getMandrillTemplate: no key, recipient nor request in the error', clean($wf));
//endregion

//region 2. getMandrillTemplate: POSITIVE, a real template is returned as an array
[$wf, , $client] = build();
$client->templates->returns['info'] = (object)['slug' => 'tpl-ok', 'code' => '<p>x</p>', 'from_email' => 'noreply@example.test'];
$ret = run(fn() => $wf->getMandrillTemplate('tpl-ok'));
check('getMandrillTemplate ok: array with the template', is_array($ret) && ($ret['slug'] ?? null) === 'tpl-ok' && $wf->error === false);
//endregion

//region 3. renderMandrillTemplate: returned exception, empty answer and POSITIVE
[$wf, , $client] = build();
$client->templates->returns['render'] = unknown_template();
$ret = run(fn() => $wf->renderMandrillTemplate('tpl-ok', ['a' => 1]));
check('render error: returns false (never a fatal nor a null html)', $ret === false);
check('render error: error with the Mandrill message', strpos(errors($wf), 'renderMandrillTemplate: Mandrill error 500 Unknown_Template: No such template') !== false && clean($wf));

[$wf, , $client] = build();
$client->templates->returns['render'] = (object)['other' => 1];
$ret = run(fn() => $wf->renderMandrillTemplate('tpl-ok', []));
check('render without html: returns false with "no html"', $ret === false && strpos(errors($wf), 'Mandrill returned no html') !== false);

[$wf, , $client] = build();
$client->templates->returns['render'] = (object)['html' => '<p>hello</p>'];
$ret = run(fn() => $wf->renderMandrillTemplate('tpl-ok', []));
check('render ok: returns the html', $ret === '<p>hello</p>' && $wf->error === false);
//endregion

//region 4. sendPlatformEmail: a failed render stops BEFORE writing the submission or sending
[$wf, $ds, $client] = build();
$client->templates->returns['render'] = unknown_template();
$params = ['slug' => 'tpl-ok', 'from' => 'noreply@example.test', 'to' => RECIPIENT, 'subject' => 'S'];
$ret = run(fn() => $wf->sendPlatformEmail($params));
check('sendPlatformEmail render error: returns false', $ret === false);
check('sendPlatformEmail render error: nothing written in Datastore', $ds->writes === 0);
check('sendPlatformEmail render error: nothing sent', !in_array('sendTemplate', $client->messages->calls) && !in_array('send', $client->messages->calls));
check('sendPlatformEmail render error: error with the Mandrill name, without recipients', strpos(errors($wf), 'Unknown_Template') !== false && clean($wf));
//endregion

//region 5. sendMandrillEmail: returned exception on send -> success false, never a success with an empty result
[$wf, , $client] = build();
$client->templates->returns['info'] = (object)['slug' => 'tpl-ok'];
$client->messages->returns['sendTemplate'] = new FakeRequestException(500, ['status' => 'error', 'code' => -2, 'name' => 'ValidationError', 'message' => 'Validation error: recipient '.RECIPIENT.' is not valid']);
$params = ['slug' => 'tpl-ok', 'from' => 'noreply@example.test', 'to' => RECIPIENT, 'subject' => 'S'];
$ret = run(fn() => $wf->sendMandrillEmail($params));
check('send error: success false', is_array($ret) && $ret['success'] === false);
check('send error: result says the Mandrill name', is_array($ret) && is_string($ret['result'] ?? null) && strpos($ret['result'], 'sendMandrillEmail: Mandrill error 500 ValidationError') !== false);
check('send error: email address in the Mandrill message masked', is_array($ret) && is_string($ret['result'] ?? null) && strpos($ret['result'], '[email]') !== false && clean($wf, $ret));
//endregion

//region 6. sendMandrillEmail: thrown network failure -> success false, class only
[$wf, , $client] = build();
$client->templates->returns['info'] = (object)['slug' => 'tpl-ok'];
$client->messages->returns['sendTemplate'] = function() { throw new FakeConnectException('cURL error 28 for https://mandrillapp.com key='.SYNTH_KEY); };
$params = ['slug' => 'tpl-ok', 'from' => 'noreply@example.test', 'to' => RECIPIENT, 'subject' => 'S'];
$ret = run(fn() => $wf->sendMandrillEmail($params));
check('network error: caught, success false', is_array($ret) && $ret['success'] === false);
check('network error: only the class of the exception', is_array($ret) && is_string($ret['result'] ?? null) && strpos($ret['result'], 'Mandrill error (FakeConnectException)') !== false && clean($wf, $ret));
//endregion

//region 7. sendMandrillEmail: template read error -> success false with the reason (was a bare null)
[$wf, , $client] = build();
$client->templates->returns['info'] = unknown_template();
$params = ['slug' => 'tpl-ok', 'from' => 'noreply@example.test', 'to' => RECIPIENT, 'subject' => 'S'];
$ret = run(fn() => $wf->sendMandrillEmail($params));
check('template error on send: success false with the reason', is_array($ret) && $ret['success'] === false && is_string($ret['result'] ?? null) && strpos($ret['result'], 'Unknown_Template') !== false);
check('template error on send: nothing sent', !in_array('sendTemplate', $client->messages->calls));
//endregion

//region 8. sendMandrillEmail: POSITIVE
[$wf, , $client] = build();
$client->templates->returns['info'] = (object)['slug' => 'tpl-ok'];
$client->messages->returns['sendTemplate'] = [(object)['email' => RECIPIENT, 'status' => 'sent', '_id' => 'abc']];
$params = ['slug' => 'tpl-ok', 'from' => 'noreply@example.test', 'to' => RECIPIENT, 'subject' => 'S'];
$ret = run(fn() => $wf->sendMandrillEmail($params));
check('send ok: success true with the Mandrill result', is_array($ret) && $ret['success'] === true && ($ret['result'][0]['status'] ?? null) === 'sent' && $wf->error === false);
//endregion

//region 9. The other reads: returned exception -> false with an error
$reads = [
    ['getMandrillTemplates', 'templates', 'list', []],
    ['getMandrillWebHooks', 'webhooks', 'list', []],
    ['getMandrillDomains', 'senders', 'domains', []],
    ['getMandrillSenders', 'senders', 'list', []],
    ['getMandrillMessageInfo', 'messages', 'info', ['abc']],
    ['getMandrillMessageContent', 'messages', 'content', ['abc']],
];
foreach ($reads as [$method, $group, $call, $args]) {
    [$wf, , $client] = build();
    $client->$group->returns[$call] = new FakeRequestException(401, ['status' => 'error', 'code' => -1, 'name' => 'Invalid_Key', 'message' => 'Invalid API key']);
    $ret = run(fn() => $wf->$method(...$args));
    check("$method: false with the Mandrill error", $ret === false && strpos(errors($wf), "$method: Mandrill error 401 Invalid_Key") !== false && clean($wf));
}
//endregion

echo "\n".($checks - $failures)."/$checks checks passed\n";
exit($failures ? 1 : 0);
