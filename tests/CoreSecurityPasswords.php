<?php
// CLOUD Documentum:
//   DevGroup: /cf/products/cloud-development
//   DevUnit: /php/classes/Core7/CoreSecurity, /cloud-development/frameworks/php
//   Activities: 6651158731161600
/**
 * Isolated tests of CoreSecurity::crypt() / checkCrypt() / needsRehash() since 8.5.27 (password_hash with bcrypt and a
 * cost of at least 12, password_verify in constant time).
 *
 * Contract under test: the hashes written by crypt() of 8.5.26 ('$2a$07$') keep verifying; new hashes are '$2y$12$'
 * (or the higher cost asked); a lower cost is raised to 12; checkCrypt() of 8.5.26 also verifies the new hashes (older
 * framework versions sharing the data); needsRehash() is true for the old hashes and false for the new ones; input over
 * 72 bytes is rejected explicitly (null / false and security-password-too-long); arrays are json_encoded as before;
 * core.security.password is used when no hash is given; recovery codes (crypt(uniqid(...))) keep working.
 *
 * Self-contained: no network, no vendor/. Passwords are synthetic.
 *
 * Run from the repository root:
 *   php tests/CoreSecurityPasswords.php
 * Exit code 0 when every check passes, 1 otherwise.
 */

//region ENVIRONMENT
foreach (['CF_CACHE_ENCRYPT_KEY','CF_CACHE_ENCRYPT_IV','PROJECT_ID','GAE_SERVICE','K_SERVICE','FUNCTION_TARGET'] as $env_var) putenv($env_var);
$tmp_root = sys_get_temp_dir().'/cf-passwords-test-'.getmypid();
@mkdir($tmp_root,0700,true);
file_put_contents($tmp_root.'/config.json','{}');
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
    if(!(error_reporting() & $errno)) return true;
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

/**
 * Hashes written by CoreSecurity::crypt() of 8.5.26 ('$2a$07$' + 22 random salt chars), generated with the source of that
 * version for this suite. Synthetic passwords.
 */
const OLD_HASHES = [
    ['synthetic-password-1','$2a$07$Z9O0V08ijv6lHvprVHvfEeWPk1R66rLf8bDQX2tlu.0CgcUjOcdyG'],
    ["Synth\u{20ac}tic p\u{e4}ssword 2",'$2a$07$K4Rs7q7DeMMQuxZNm7M7BO6wz8MpGry2EbhccCl6uiSNoe1h.FcWG'],
    ['xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx','$2a$07$OQOJkNIrRCQfyNWOTB7TwuLS70Lq/4nuOm94AMyJnfUNNTlqK4x2S'],
];
const OLD_ARRAY_INPUT = ['a'=>1,'b'=>[2,3]];
const OLD_ARRAY_HASH = '$2a$07$SWTOgkh0jGEZMMXTe9j2Ju4uf3Drr7.D7l6EVVvZ3PpOD8yY1Q4QG';

/** Literal copy of crypt() of 8.5.26 */
function legacy_crypt($input, $rounds = 7) {
    if($input && !is_string($input)) $input = json_encode($input);
    if(!$input) return null;
    if(strlen($input)>72) return null;
    $salt = "";
    $salt_chars = array_merge(range('A', 'Z'), range('a', 'z'), range(0, 9));
    for ($i = 0; $i < 22; $i++) $salt .= $salt_chars[array_rand($salt_chars)];
    return crypt($input, sprintf('$2a$%02d$', $rounds) . $salt);
}
/** Literal copy of checkCrypt() of 8.5.26 (without the config fallback) */
function legacy_check_crypt($input, $input_encrypted): bool {
    if($input && !is_string($input)) $input = json_encode($input);
    if(!$input) return false;
    if(strlen($input)>72) return false;
    if(!$input_encrypted || !is_string($input_encrypted)) return false;
    return (crypt($input, $input_encrypted) == $input_encrypted);
}
//endregion

$core = new Core7($tmp_root);
if(ob_get_level()) ob_end_flush();
$sec = $core->security;

//region a) hashes of 8.5.26 keep verifying
section('a) hashes written by 8.5.26 keep verifying');
foreach (OLD_HASHES as $i=>[$password,$hash]) {
    check("old hash #$i verifies", $sec->checkCrypt($password,$hash));
    check("old hash #$i rejects another password", !$sec->checkCrypt($password.'x',$hash) && !$sec->checkCrypt('synthetic-wrong',$hash));
    check("old hash #$i needs rehash", $sec->needsRehash($hash));
}
check('old hash of an array input verifies with the same array', $sec->checkCrypt(OLD_ARRAY_INPUT,OLD_ARRAY_HASH));
$fresh_old = legacy_crypt('synthetic-fresh-old');
check('a hash generated now with the 8.5.26 code verifies', $sec->checkCrypt('synthetic-fresh-old',$fresh_old) && strncmp($fresh_old,'$2a$07$',7)===0);
//endregion

//region b) new hashes
section('b) new hashes: bcrypt $2y$, cost >= 12');
$t = microtime(true);
$h = $sec->crypt('synthetic-password-new');
$elapsed = round(microtime(true)-$t,3);
check('format $2y$12$ and 60 characters', is_string($h) && strncmp($h,'$2y$12$',7)===0 && strlen($h)===60, (string)$h);
check('verifies', $sec->checkCrypt('synthetic-password-new',$h));
check('rejects another password', !$sec->checkCrypt('synthetic-password-neW',$h));
check('two hashes of the same password differ (salt)', $sec->crypt('same')!==$sec->crypt('same'));
check('does not need rehash', !$sec->needsRehash($h));
check('the 8.5.26 checkCrypt() also verifies it (older versions sharing data)', legacy_check_crypt('synthetic-password-new',$h) && !legacy_check_crypt('other',$h));
check('rounds 7 (old default) is raised to 12', strncmp((string)$sec->crypt('x',7),'$2y$12$',7)===0);
check('rounds 4 is raised to 12', strncmp((string)$sec->crypt('x',4),'$2y$12$',7)===0);
check("rounds '5' (string) is raised to 12", strncmp((string)$sec->crypt('x','5'),'$2y$12$',7)===0);
$h13 = $sec->crypt('x',13);
check('rounds 13 is kept', strncmp((string)$h13,'$2y$13$',7)===0 && $sec->checkCrypt('x',$h13));
check('a higher cost does not need rehash', !$sec->needsRehash((string)$h13));
check('array input json_encoded as before', $sec->checkCrypt(['k'=>'v'],(string)$sec->crypt(['k'=>'v'])) && !$sec->checkCrypt(['k'=>'w'],(string)$sec->crypt(['k'=>'v'])));
echo "         (cost 12 hash took {$elapsed} s on this machine)\n";
//endregion

//region c) needsRehash
section('c) needsRehash()');
check('$2y$ cost 10: true', $sec->needsRehash(password_hash('x',PASSWORD_BCRYPT,['cost'=>10])));
check('$2y$ cost 12: false', !$sec->needsRehash(password_hash('x',PASSWORD_BCRYPT,['cost'=>12])));
check('md5-crypt: true', $sec->needsRehash(crypt('x','$1$abcdefgh$')));
check('argon2id (if available): true', !defined('PASSWORD_ARGON2ID') || $sec->needsRehash(password_hash('x',PASSWORD_ARGON2ID)));
check('empty or garbage: true', $sec->needsRehash('') && $sec->needsRehash('not a hash'));
//endregion

//region d) empty, over 72 bytes, NUL
section('d) empty input, over 72 bytes, NUL byte');
check('empty input: crypt null, checkCrypt false', $sec->crypt('')===null && $sec->crypt(null)===null && !$sec->checkCrypt('',$h));
$syslog_before = count($syslog_lines);
check('exactly 72 bytes: hashed and verified', ($h72 = $sec->crypt(str_repeat('a',72)))!==null && $sec->checkCrypt(str_repeat('a',72),$h72));
check('73 bytes: crypt null', $sec->crypt(str_repeat('a',73))===null);
check('73 bytes: checkCrypt false (even against a hash of its first 72 bytes)', !$sec->checkCrypt(str_repeat('a',73),$h72));
check('72 bytes counted as bytes: 25 x "€" (75 bytes) refused', $sec->crypt(str_repeat("\u{20ac}",25))===null);
// the first input over 72 bytes of the request was the 73-byte variant of an old hash in section a)
$notes = array_values(array_filter($syslog_lines,fn($l)=>strpos($l[1],'security-password-too-long')!==false));
check('security-password-too-long once per request (for 5 refused inputs), level error', count($notes)===1 && $notes[0][0]==='error', json_encode($notes));
check('the note does not carry the value', strpos(json_encode($syslog_lines),str_repeat('a',20))===false && strpos(json_encode($syslog_lines),str_repeat('x',20))===false);
check('NUL byte: crypt null and note security-password-invalid', $sec->crypt("a\0b")===null && strpos(json_encode($core->logs->data),'security-password-invalid')!==false);
//endregion

//region e) config fallback and other uses
section('e) core.security.password, recovery codes, token secrets');
$core->config->set('core.security.password',$h);
check('checkCrypt without hash uses core.security.password', $sec->checkCrypt('synthetic-password-new') && !$sec->checkCrypt('other'));
$core->config->set('core.security.password',OLD_HASHES[0][1]);
check('also with an old hash in core.security.password', $sec->checkCrypt(OLD_HASHES[0][0]));
$core->config->set('core.security.password',null);
check('no hash and no config: false', !$sec->checkCrypt('x'));
check('non-string hash: false', !$sec->checkCrypt('x',['not','a','string']));
$code = uniqid('password_recovery');
$token = $sec->crypt($code);
check('recovery code: crypt(uniqid()) is a 60-char token that verifies', is_string($token) && strlen($token)===60 && $sec->checkCrypt($code,$token) && !$sec->checkCrypt($code.'x',$token));
$date_key = date('Y-m-d').'synthetic-key';
check('date + key code (portal recovery) verifies', $sec->checkCrypt($date_key,(string)$sec->crypt($date_key)));
check('a plain stored value never verifies', !$sec->checkCrypt('plain','plain'));
//endregion

//region SUMMARY
$all = json_encode($syslog_lines).json_encode($core->logs->data).json_encode($core->errors->data);
check('no synthetic password in the logs', strpos($all,'synthetic-password')===false && strpos($all,'synthetic-fresh-old')===false);
echo "\nPHP warnings/notices captured during the whole run: ".count($php_warnings)."\n";
foreach ($php_warnings as $w) echo "  - $w\n";
check('no PHP warnings', count($php_warnings)===0);
echo "\n{$checks} checks, {$failures} failures\n";
exec('rm -rf '.escapeshellarg($tmp_root));
exit($failures?1:0);
//endregion
