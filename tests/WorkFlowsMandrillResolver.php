<?php
// CLOUD Documentum:
//   DevGroup: /cf/products/cloud-platform
//   Process: /cloud-platform · SubProcess: 4693650502582272
//   DevUnit: /cloud-development/frameworks/php, /cloud-channels/email/cfmail
//   Activities: 6088723519504384
/**
 * Isolated tests of WorkFlows::setMandrillApiKeyFromPlatformConfig(), the resolver of the Mandrill key of a platform.
 *
 * Contract under test (task 6088723519504384): the key comes from cloud-channels (own secret of the platform) or from
 * the [cloudframework] account with the subaccount declared by the platform. The plain text key of the legacy
 * ds:CloudFrameWorkModulesConfigs/email.Config.mandrill_api_key is NEVER used, even when it is the only key present.
 *
 * Self-contained: test doubles for Core7 (security + logs), CFOs (ds) and the Mailchimp client. No network, no
 * Datastore, no vendor/ autoload and no real key: every key is a synthetic string that is never printed.
 *
 * Run from the repository root:
 *   php tests/WorkFlowsMandrillResolver.php
 * Exit code 0 when every check passes, 1 otherwise.
 */

//region DOUBLES
// Minimal stand-in of the Mailchimp client: the typed property WorkFlows::$mandrill needs this class name
if(!class_exists('MailchimpTransactional\ApiClient')) {
    eval('namespace MailchimpTransactional; class ApiClient { private $k = null; function setApiKey($k){ $this->k = $k; } function getApiKey(){ return $this->k; } }');
}
include_once __DIR__.'/../src/class/WorkFlows.php';

class FakeDS {
    public $namespace; public $error = false; public $errorMsg = [];
    /** @var array [namespace][KeyName] => entity */
    public $rows;
    function __construct(string $namespace, array $rows) { $this->namespace = $namespace; $this->rows = $rows; }
    function fetchOneByKey($key) { return $this->rows[$this->namespace][$key] ?? null; }
}
class FakeCFOs {
    public $ds;
    function __construct(FakeDS $ds) { $this->ds = $ds; }
    function ds($entity) { return $this->ds; }
}
class FakeSecurity {
    public $isDev = false; public $error = false; public $errorMsg = [];
    /** @var array ["platform|secret.var"] => key */
    public $secrets;
    public $lookups = [];
    function __construct(array $secrets) { $this->secrets = $secrets; }
    function setDevEnvironment($dev = true) { $this->isDev = (bool)$dev; }
    function getPlatformSecretVar($var, $secret, $platform_id) {
        $this->lookups[] = $platform_id.'|'.$secret.'.'.$var;
        $key = $this->secrets[$platform_id.'|'.$secret.'.'.$var] ?? null;
        if(!$key) { $this->error = true; $this->errorMsg[] = 'not found'; }
        return $key;
    }
}
class FakeLogs { public $lines = []; function add($msg, $group = '', $level = '') { $this->lines[] = (string)$msg; } }
class FakeCore { public $security; public $logs; function __construct(FakeSecurity $s) { $this->security = $s; $this->logs = new FakeLogs(); } }

const LEGACY_KEY = 'synthetic-legacy-key-must-never-be-used';
const OWN_KEY = 'synthetic-own-key-of-the-tenant';
const CENTRAL_KEY = 'synthetic-key-of-the-cloudframework-account';

/**
 * Builds a WorkFlows over the doubles. $tenant_rows are the ModulesConfigs rows of the tenant namespace.
 * @return array [WorkFlows, FakeCore]
 */
function build(string $namespace, array $tenant_rows, array $secrets, bool $central_active = true): array {
    $rows = [
        $namespace => $tenant_rows,
        'cloudframework' => ['cloud-channels' => ['Active' => $central_active, 'Config' => ['email' => ['mandrill-api-key-secret' => 'cfo-secrets.mandrill']]]],
    ];
    $core = new FakeCore(new FakeSecurity($secrets + ['cloudframework|cfo-secrets.mandrill' => CENTRAL_KEY]));
    $wf = (new ReflectionClass('WorkFlows'))->newInstanceWithoutConstructor();
    $wf->core = $core;
    $wf->cfos = new FakeCFOs(new FakeDS($namespace, $rows));
    $wf->mandrill = new MailchimpTransactional\ApiClient();
    return [$wf, $core];
}
function legacy_email(): array { return ['Active' => true, 'Config' => ['mandrill_api_key' => LEGACY_KEY, 'variables' => ['company' => 'X']]]; }
//endregion

//region HELPERS
$checks = 0; $failures = 0;
function check(string $name, bool $ok): void {
    global $checks, $failures;
    $checks++; if(!$ok) $failures++;
    echo ($ok ? '  ok   ' : '  FAIL ').$name."\n";
}
function no_key_in_logs(FakeCore $core, $wf): bool {
    $text = implode("\n", $core->logs->lines).json_encode($wf->errorMsg);
    foreach ([LEGACY_KEY, OWN_KEY, CENTRAL_KEY, 'cfo-secrets.mandrill'] as $needle) if(strpos($text, $needle) !== false) return false;
    return true;
}
//endregion

echo "WorkFlows::setMandrillApiKeyFromPlatformConfig()\n";

//region 1. Own secret of the platform wins, and the legacy key next to it is ignored
[$wf, $core] = build('tenant-a', [
    'cloud-channels' => ['Active' => true, 'Config' => ['email' => ['mandrill-api-key-secret' => 'cfo-secrets.mandrill']]],
    'email' => legacy_email(),
], ['tenant-a|cfo-secrets.mandrill' => OWN_KEY]);
$ret = $wf->setMandrillApiKeyFromPlatformConfig();
check('own secret: source cloud-channels', is_array($ret) && $ret['source'] === 'cloud-channels');
check('own secret: the key set is the own key', $wf->mandrill->getApiKey() === OWN_KEY);
check('own secret: legacy variables are still returned', is_array($ret) && ($ret['variables']['company'] ?? null) === 'X');
check('own secret: no key nor secret reference in logs or errors', no_key_in_logs($core, $wf));
//endregion

//region 2. NEGATIVE: only the legacy key exists (cloud-channels missing) -> central account, never the legacy key
[$wf, $core] = build('tenant-b', ['email' => legacy_email()], []);
$ret = $wf->setMandrillApiKeyFromPlatformConfig();
check('legacy only: resolves through the [cloudframework] account', is_array($ret) && $ret['source'] === 'cloudframework-fallback');
check('legacy only: the legacy key is NOT the key set', $wf->mandrill->getApiKey() !== LEGACY_KEY && $wf->mandrill->getApiKey() === CENTRAL_KEY);
check('legacy only: no legacy-email notes', is_array($ret) && !array_filter($ret['notes'], fn($n) => strpos($n, 'legacy') !== false));
check('legacy only: no key in logs or errors', no_key_in_logs($core, $wf));
//endregion

//region 3. NEGATIVE: cloud-channels INACTIVE with own pointer + legacy key -> central account without subaccount
[$wf, $core] = build('tenant-c', [
    'cloud-channels' => ['Active' => false, 'Config' => ['email' => ['mandrill-api-key-secret' => 'cfo-secrets.mandrill', 'provider' => 'cloudframework-subaccount', 'subaccount' => 'tenant-c']]],
    'email' => legacy_email(),
], ['tenant-c|cfo-secrets.mandrill' => OWN_KEY]);
$ret = $wf->setMandrillApiKeyFromPlatformConfig();
check('inactive cloud-channels: ignored, central account without subaccount', is_array($ret) && $ret['source'] === 'cloudframework-fallback' && $ret['subaccount'] === null);
check('inactive cloud-channels: neither own nor legacy key', $wf->mandrill->getApiKey() === CENTRAL_KEY);
check('inactive cloud-channels: notes say cloud-channels-missing', is_array($ret) && in_array('cloud-channels-missing', $ret['notes']));
//endregion

//region 4. Group A tenant: subaccount declared + legacy key -> central account WITH the subaccount
[$wf, $core] = build('tenant-d', [
    'cloud-channels' => ['Active' => true, 'Config' => ['email' => ['provider' => 'cloudframework-subaccount', 'subaccount' => 'tenant-d']]],
    'email' => legacy_email(),
], []);
$ret = $wf->setMandrillApiKeyFromPlatformConfig();
check('subaccount: source cloudframework-subaccount', is_array($ret) && $ret['source'] === 'cloudframework-subaccount');
check('subaccount: the subaccount travels to the caller', is_array($ret) && $ret['subaccount'] === 'tenant-d');
check('subaccount: central key, not the legacy one', $wf->mandrill->getApiKey() === CENTRAL_KEY);
check('subaccount: the subaccount id is not logged', strpos(implode("\n", $core->logs->lines), 'tenant-d') === false);
//endregion

//region 5. NEGATIVE: own pointer that does not resolve + legacy key -> central account, never the legacy key
[$wf, $core] = build('tenant-e', [
    'cloud-channels' => ['Active' => true, 'Config' => ['email' => ['mandrill-api-key-secret' => 'cfo-secrets.mandrill']]],
    'email' => legacy_email(),
], []);
$ret = $wf->setMandrillApiKeyFromPlatformConfig();
check('unresolved own secret: central account', is_array($ret) && $ret['source'] === 'cloudframework-fallback');
check('unresolved own secret: notes say cloud-channels-key-secret-not-resolved', is_array($ret) && in_array('cloud-channels-key-secret-not-resolved', $ret['notes']));
check('unresolved own secret: the legacy key is NOT the key set', $wf->mandrill->getApiKey() === CENTRAL_KEY);
//endregion

//region 6. NEGATIVE: nothing resolves (central inactive) + legacy key -> error, and the legacy key is not set
[$wf, $core] = build('tenant-f', ['email' => legacy_email()], [], false);
$ret = $wf->setMandrillApiKeyFromPlatformConfig();
check('nothing resolves: returns false with an error', $ret === false && $wf->error === true);
check('nothing resolves: the legacy key was never set', $wf->mandrill->getApiKey() !== LEGACY_KEY);
check('nothing resolves: error made of fixed codes, no key', no_key_in_logs($core, $wf) && strpos(json_encode($wf->errorMsg), 'cloudframework-key-secret-not-resolved') !== false);
//endregion

//region 7. _dev namespace: secrets looked up with the BASE platform id and the DEV state restored
[$wf, $core] = build('tenant-g_dev', [
    'cloud-channels' => ['Active' => true, 'Config' => ['email' => ['mandrill-api-key-secret' => 'cfo-secrets.mandrill']]],
], ['tenant-g|cfo-secrets.mandrill' => OWN_KEY]);
$ret = $wf->setMandrillApiKeyFromPlatformConfig();
check('_dev: own secret resolved with the base platform id', is_array($ret) && $ret['source'] === 'cloud-channels' && in_array('tenant-g|cfo-secrets.mandrill', $core->security->lookups));
check('_dev: previous DEV state of the security layer restored', $core->security->isDev === false);
//endregion

echo "\n".($checks - $failures)."/$checks checks passed\n";
exit($failures ? 1 : 0);
