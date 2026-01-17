<?php
$root_path = (strlen($_SERVER['DOCUMENT_ROOT'])) ? $_SERVER['DOCUMENT_ROOT'] : $_SERVER['PWD'];
require_once  $root_path.'/vendor/autoload.php';

use Mcp\Server;
use Mcp\Server\Transport\StreamableHttpTransport;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7Server\ServerRequestCreator;
use Laminas\HttpHandlerRunner\Emitter\SapiEmitter;
use Mcp\Server\Session\SessionStoreInterface;
use Symfony\Component\Uid\Uuid;

//region INIT $core = new Core7();
include_once(__DIR__ . "/Core7.php"); //
include_once(__DIR__ . "/class/RESTful.php"); //
$core = new Core7();
//endregion

class PhpSessionStore implements SessionStoreInterface
{
    private const SESSION_KEY = 'mcp_data';

    public function __construct(
        private readonly int $ttl = 3600
    ) {
    }

    private function startSessionFor(Uuid $id): void
    {
        // Close any existing session
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        // Disable session cookies - MCP uses Mcp-Session-Id header instead
        ini_set('session.use_cookies', '0');
        ini_set('session.cache_limiter', '');

        // Convert MCP UUID to a valid PHP session ID (alphanumeric)
        $phpSessionId = preg_replace('/[^a-zA-Z0-9]/', '', $id->toRfc4122());

        session_id($phpSessionId);
        session_start();
    }

    public function exists(Uuid $id): bool
    {
        $this->startSessionFor($id);

        if (!isset($_SESSION[self::SESSION_KEY])) {
            return false;
        }

        $timestamp = $_SESSION['mcp_timestamp'] ?? 0;

        if ((time() - $timestamp) > $this->ttl) {
            session_destroy();
            return false;
        }

        return true;
    }

    public function read(Uuid $id): string|false
    {
        $this->startSessionFor($id);

        if (!isset($_SESSION[self::SESSION_KEY])) {
            return false;
        }

        $timestamp = $_SESSION['mcp_timestamp'] ?? 0;

        if ((time() - $timestamp) > $this->ttl) {
            session_destroy();
            return false;
        }

        return $_SESSION[self::SESSION_KEY];
    }

    public function write(Uuid $id, string $data): bool
    {
        $this->startSessionFor($id);

        $_SESSION[self::SESSION_KEY] = $data;
        $_SESSION['mcp_timestamp'] = time();

        session_write_close();

        return true;
    }

    public function destroy(Uuid $id): bool
    {
        $this->startSessionFor($id);

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }

        return true;
    }

    public function gc(): array
    {
        // PHP's session garbage collection handles this automatically
        // based on session.gc_probability and session.gc_maxlifetime
        return [];
    }
}


// Create PSR-17 factories
$psr17Factory = new Psr17Factory();

// Create ServerRequest from globals
$creator = new ServerRequestCreator(
    $psr17Factory, // ServerRequestFactory
    $psr17Factory, // UriFactory
    $psr17Factory, // UploadedFileFactory
    $psr17Factory  // StreamFactory
);
$request = $creator->fromGlobals();

// Session store using PHP native sessions
$sessionStore = new PhpSessionStore(3600);

// Build the MCP server
$server = Server::builder()
    ->setServerInfo('HTTP Server', '1.0.1')
    ->setDiscovery($root_path, ['mcp'])
    ->setSession($sessionStore)
    ->build();

// Create streamable transport with the PSR-7 request
$transport = new StreamableHttpTransport(
    $request,
    $psr17Factory, // ResponseFactory
    $psr17Factory,  // StreamFactory
    ['Access-Control-Allow-Origin' => '']
);


// Define the class MCPCore7
class MCPCore7
{
    /** @var Core7 $core */
    protected $core;
    /** @var RESTful $api */
    protected $api;
    /** @var PhpSessionStore $session */
    protected $sessionStore;
    /** @var string $sessionId */
    protected $sessionId;
    /** @var CFOs $cfos */

    protected $cfos;
    protected $secrets = [];
    protected $platform = 'cloudframework';

    protected bool $error = false;
    protected ?string $errorCode = null;
    protected $errorMsg = [];

    public function __construct()
    {
        global $core;
        global $sessionStore;
        $this->core = &$core;
        $this->sessionStore = &$sessionStore;
        $this->api = new RESTful($this->core);

        $this->sessionId = $this->api->getHeader('MCP_SESSION_ID');
        $this->platform = $_SESSION['platform'] ?? 'cloudframework';

        //debug logs
        $this->core->logs->add($this->api->getHeaders(), 'headers');
        $this->core->logs->add($this->sessionId, 'sessionId');
        $this->core->logs->add($this->api->params, 'params');
        unset($this->api->formParams['_raw_input_']);
        $this->core->logs->add($this->api->formParams, 'formParams');
    }

    /**
     * Initializes the CFOs class instance for the current object.
     * Ensures that the necessary secrets for API integration are available, loads the CFOs class,
     * and sets up the required namespace and cache handling.
     *
     * @param string $key Optional parameter used for integration or custom key configuration.
     * @return bool Returns true if the CFOs object is successfully initialized, otherwise false on error.
     */
    public function initCFOs(): bool
    {

        // Avoid create the object multiple times
        if(is_object($this->cfos)) return true;

        //regin CHECK $this->secrets
        if($this->error) return false;
        if(!($this->secrets['api_cfos_integration_key']??null))
            if(!$this->readSecrets()) return false;
        //endregion

        //region LOADCLASS  $this->cfos
        $this->cfos = $this->core->loadClass('CFOs',$this->secrets['api_cfos_integration_key']);
        if($this->cfos->error) return($this->setErrorFromCodelib('system-error',$this->cfos->errorMsg));
        if(isset($this->formParams['_reload_cache']) || isset($this->formParams['_reload_cfos'])) $this->cfos->resetCache();
        $this->cfos->setNameSpace($this->platform);
        //endregion

        return true;
    }

    /**
     * Read secrets from cfo-secrets and update $this->secrets['api_login_integration_key'],['api_cfos_integration_key']
     * If those variable are not been assigned previously or they don't exist in cfo-secrets as vars it will generate an error
     * @return bool false on error
     */
    protected function readSecrets()
    {
        if ($this->error) return false;

        //region READ Platform Secrets [cfo-secrets]
        if (!($this->core->security->readPlatformSecretVars('cfo-secrets', $this->platform)))
            return $this->setErrorFromCodelib('secrets-error', $this->core->security->errorMsg);
        //endregion

        //region CHECK api_login_integration_key, api_cfos_integration_key secrets vars to assign them $this->secrets
        if ($this->core->security->getPlatformSecretVar('api_login_integration_key'))
            $this->secrets['api_login_integration_key'] = $this->core->security->getPlatformSecretVar('api_login_integration_key');

        if ($this->core->security->getPlatformSecretVar('api_cfos_integration_key'))
            $this->secrets['api_cfos_integration_key'] = $this->core->security->getPlatformSecretVar('api_cfos_integration_key');
        //endregion

        //region VERIFY we have a value in $this->secrets['api_login_integration_key'],$this->secrets['api_cfos_integration_key']
        if (!$this->secrets['api_login_integration_key'])
            $this->setErrorFromCodelib('configuration-error', "Missing api_login_integration_key in Platform Secret: cfo-secrets");

        if (!$this->secrets['api_cfos_integration_key'])
            $this->setErrorFromCodelib('configuration-error', "Missing api_cfos_integration_key in Platform Secret: cfo-secrets");
        //endregion

        return !$this->error;
    }


    /**
     * Sets an error in the system using a specified code and message, marking the error state as true.
     *
     * @param int|string $code The error code representing the type of error.
     * @param string $msg A detailed message describing the error.
     * @return bool Always returns false after setting the error state.
     */
    protected function setErrorFromCodelib($code, $msg)
    {
        $this->error = true;
        $this->errorCode = $code;
        $this->errorMsg[] = $msg;
        return false;
    }
}

// Run the server and get the PSR-7 response
$response = $server->run($transport);

// Emit the response to the client
$emitter = new SapiEmitter();
$emitter->emit($response);
