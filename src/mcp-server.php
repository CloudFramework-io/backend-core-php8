<?php
/**
 * MCP SERVER WITH SSO SUPPORT
 */
$root_path = (strlen($_SERVER['DOCUMENT_ROOT'])) ? $_SERVER['DOCUMENT_ROOT'] : $_SERVER['PWD'];
require_once  $root_path.'/vendor/autoload.php';

use Mcp\Server;
use Mcp\Server\Transport\StreamableHttpTransport;
use Mcp\Capability\Attribute\McpTool;
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

/**
 * Handle OAuth-related endpoints and validation
 */
$requestPath = $request->getUri()->getPath();
$requestMethod = $request->getMethod();

// Handle CORS preflight for all endpoints
if ($requestMethod === 'OPTIONS') {
    $response = $psr17Factory->createResponse(204)
        ->withHeader('Access-Control-Allow-Origin', '*')
        ->withHeader('Access-Control-Allow-Methods', 'GET, POST, DELETE, OPTIONS')
        ->withHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization, Mcp-Session-Id, Mcp-Protocol-Version')
        ->withHeader('Access-Control-Max-Age', '86400');
    (new SapiEmitter())->emit($response);
    exit;
}

// OAuth Protected Resource Metadata (RFC 8707)
if ($requestPath === '/.well-known/oauth-protected-resource') {
    $serverUrl = $request->getUri()->getScheme() . '://' . $request->getUri()->getHost();
    if ($request->getUri()->getPort() && !in_array($request->getUri()->getPort(), [80, 443])) {
        $serverUrl .= ':' . $request->getUri()->getPort();
    }

    $metadata = [
        'resource' => $serverUrl,
        'authorization_servers' => [
            'https://api.cloudframework.io'  // CloudFramework OAuth server
        ],
        'bearer_methods_supported' => ['header'],
        'resource_documentation' => 'https://docs.cloudframework.io/mcp',
        'resource_signing_alg_values_supported' => ['RS256'],
        'resource_name' => 'CloudFramework MCP Server',
        'resource_policy_uri' => 'https://cloudframework.io/privacy'
    ];

    $response = $psr17Factory->createResponse(200)
        ->withHeader('Content-Type', 'application/json')
        ->withHeader('Access-Control-Allow-Origin', '*')
        ->withBody($psr17Factory->createStream(json_encode($metadata, JSON_PRETTY_PRINT)));
    (new SapiEmitter())->emit($response);
    exit;
}

// OAuth Authorization Server Metadata (for clients that need it)
if ($requestPath === '/.well-known/oauth-authorization-server') {
    $metadata = [
        'issuer' => 'https://api.cloudframework.io',
        'registration_endpoint' => 'https://api.cloudframework.io/cloud-solutions/directory/mcp-oauth/register',
        'authorization_endpoint' => 'https://api.cloudframework.io/cloud-solutions/directory/mcp-oauth/authorize',
        'token_endpoint' => 'https://api.cloudframework.io/cloud-solutions/directory/mcp-oauth/token',
        'token_endpoint_auth_methods_supported' => ['none', 'client_secret_basic'],
        'grant_types_supported' => ['authorization_code', 'refresh_token'],
        'response_types_supported' => ['code'],
        'code_challenge_methods_supported' => ['S256'],  // PKCE required
        'scopes_supported' => ['openid', 'profile', 'email', 'projects', 'tasks'],
        'service_documentation' => 'https://docs.cloudframework.io/oauth'
    ];

    $response = $psr17Factory->createResponse(200)
        ->withHeader('Content-Type', 'application/json')
        ->withHeader('Access-Control-Allow-Origin', '*')
        ->withBody($psr17Factory->createStream(json_encode($metadata, JSON_PRETTY_PRINT)));
    (new SapiEmitter())->emit($response);
    exit;
}

//region Define MCPCore7 class BEFORE discovery (required for classes that extend it)
/**
 * MCPCore7 - Base class for MCP capability handlers
 * Provides access to Core7, CFOs, and authentication helpers
 */
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

        // Authorization Token
        if($token = substr($this->api->getHeader('Authorization') ?? '',7)) {
            if ($_SESSION['token'] == $token && !empty($_SESSION['user']['KeyName'])) {
                $this->core->user->token = $_SESSION['token'];
                $this->core->user->id = $_SESSION['user']['KeyName'];
                $this->core->user->data = ['User' => $_SESSION['user']];
                $this->core->user->namespace = $this->platform = $_SESSION['platform']??'cloudframework';
                $this->core->user->isAuth = true;

            } else {
                $this->validateOAuthToken();
                if(!$this->core->user->isAuth())
                    $this->error = true;
                    $this->errorMsg = ['Invalid OAuth token'];
            }
        }
        //X-DS-TOKEN
        elseif($dstoken = $_SESSION['dstoken']??null) {
            $this->readSecrets();
            if(!empty($this->secrets['api_login_integration_key'])) {
                $this->core->user->loadPlatformUserWithToken($dstoken, $this->secrets['api_login_integration_key']);
                if ($this->core->user->error) {
                    $_SESSION['dstoken'] = null;
                    $_SESSION['user'] = null;
                    return "Error: dstoken [{$dstoken}] is not valid: " . json_encode($this->core->user->errorMsg);
                }
            }
        }

        //debug logs
        $this->core->logs->add($this->sessionId, 'sessionId');
        $this->core->logs->add($this->api->getHeaders(), 'headers');
        $this->core->logs->add($this->core->user->id??'no-user', 'user');
        $this->core->logs->add($this->core->user->getPrivileges(), 'privileges');
        if ($this->api->params) $this->core->logs->add($this->api->params, 'params');
        if ($this->api->formParams) {
            unset($this->api->formParams['_raw_input_']);
            $this->core->logs->add($this->api->formParams, 'formParams');
        }
    }

    /**
     * Validate OAuth Bearer token from Authorization header
     * Supports both MCP OAuth tokens and CloudFramework tokens
     */
    protected function validateOAuthToken(): void
    {
        $authHeader = $this->api->getHeader('Authorization') ?? '';

        if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
            return; // No Bearer token, allow request to proceed (tools can check auth status)
        }

        $token = substr($authHeader, 7);
        if (empty($token)) {
            return;
        }

        // Extract platform from token if present (format: platform__token_data)
        if (strpos($token, '__') !== false) {
            list($this->platform,) = explode('__', $token, 2);
        }

        // Check if this is an MCP OAuth token (contains __mcp_)
        if (strpos($token, '__mcp_') !== false) {
            $this->validateMCPOAuthToken($token);
        } else {
            $this->validateCloudFrameworkToken($token);
        }
    }

    /**
     * Validate MCP OAuth token against the OAuth API
     */
    protected function validateMCPOAuthToken(string $token): void
    {
        $response = $this->core->request->get_json_decode(
            "https://api.cloudframework.io/cloud-solutions/directory/mcp-oauth/validate",
            null,
            ['Authorization' => 'Bearer ' . $token]
        );

        if ($this->core->request->error) {
            $this->core->logs->add('MCP OAuth validation failed: ' . json_encode($this->core->request->errorMsg), 'oauth-error');
            return;
        }

        if (!($response['data']['valid'] ?? false)) {
            $this->core->logs->add('MCP OAuth token invalid: ' . ($response['data']['error'] ?? 'unknown'), 'oauth-error');
            return;
        }

        $_SESSION['token'] = $token;
        $_SESSION['user'] = $response['data']['data'];
        $_SESSION['platform'] = $response['data']['platform'] ?? 'cloudframework';;

        $this->core->user->id = $_SESSION['user']['KeyName'];
        $this->core->user->token = $_SESSION['token'];
        $this->core->user->data = ['User' => $_SESSION['user']];
        $this->core->user->namespace = $this->platform = $_SESSION['platform'];
        $this->core->user->isAuth = true;


        $this->core->logs->add('MCP OAuth authenticated: ' . ($this->oauthUser['KeyName'] ?? 'unknown'), 'oauth');
    }

    /**
     * Validate CloudFramework token against signin API
     */
    protected function validateCloudFrameworkToken(string $token): void
    {
        if (!$this->readSecrets()) {
            return;
        }

        $response = $this->core->request->get_json_decode(
            "https://api.cloudframework.io/core/signin/{$this->platform}/check",
            null,
            [
                'X-WEB-KEY' => 'mcp-oauth',
                'X-DS-TOKEN' => $token,
                'X-EXTRA-INFO' => $this->secrets['api_login_integration_key']
            ]
        );

        if ($this->core->request->error) {
            $this->core->logs->add('CloudFramework token validation failed: ' . json_encode($this->core->request->errorMsg), 'oauth-error');
            return;
        }

        if (!isset($response['data']['User']['KeyName'])) {
            $this->core->logs->add('CloudFramework token response missing user data', 'oauth-error');
            return;
        }

        $_SESSION['token'] = $token;
        $_SESSION['user'] = $response['data'];
        $_SESSION['platform'] = $response['data']['platform'] ?? 'cloudframework';;

        $this->core->user->id = $_SESSION['user']['User']['KeyName'];
        $this->core->user->token = $_SESSION['token'];
        $this->core->user->data = ['User' => $_SESSION['user']['User']];
        $this->core->user->namespace = $this->platform = $_SESSION['platform'];
        $this->core->user->isAuth = true;

        $this->core->logs->add('CloudFramework authenticated: ' .  $this->core->user->id, 'oauth');
    }

    /**
     * Initializes the CFOs class instance for the current object.
     * @return bool Returns true if the CFOs object is successfully initialized
     */
    public function initCFOs(): bool
    {
        // Avoid create the object multiple times
        if (is_object($this->cfos)) return true;

        if ($this->error) return false;
        if (!($this->secrets['api_cfos_integration_key'] ?? null))
            if (!$this->readSecrets()) return false;

        $this->cfos = $this->core->loadClass('CFOs', $this->secrets['api_cfos_integration_key']);
        if ($this->cfos->error) return ($this->setErrorFromCodelib('system-error', $this->cfos->errorMsg));
        if (isset($this->formParams['_reload_cache']) || isset($this->formParams['_reload_cfos'])) $this->cfos->resetCache();
        $this->cfos->setNameSpace($this->platform);

        return true;
    }

    /**
     * Read secrets from cfo-secrets
     * @return bool false on error
     */
    protected function readSecrets()
    {
        if ($this->error) return false;

        if (!($this->core->security->readPlatformSecretVars('cfo-secrets', $this->platform)))
            return $this->setErrorFromCodelib('secrets-error', $this->core->security->errorMsg);

        if ($this->core->security->getPlatformSecretVar('api_login_integration_key'))
            $this->secrets['api_login_integration_key'] = $this->core->security->getPlatformSecretVar('api_login_integration_key');

        if ($this->core->security->getPlatformSecretVar('api_cfos_integration_key'))
            $this->secrets['api_cfos_integration_key'] = $this->core->security->getPlatformSecretVar('api_cfos_integration_key');

        if (!($this->secrets['api_login_integration_key'] ?? null))
            $this->setErrorFromCodelib('configuration-error', "Missing api_login_integration_key in Platform Secret: cfo-secrets");

        if (!($this->secrets['api_cfos_integration_key'] ?? null))
            $this->setErrorFromCodelib('configuration-error', "Missing api_cfos_integration_key in Platform Secret: cfo-secrets");

        return !$this->error;
    }

    /**
     * Sets an error using a code and message
     * @return bool Always returns false
     */
    protected function setErrorFromCodelib($code, $msg)
    {
        $this->error = true;
        $this->errorCode = $code;
        $this->errorMsg[] = $msg;
        return false;
    }
}
//endregion

// Session store using PHP native sessions
$sessionStore = new PhpSessionStore(3600);

// Build the MCP server
$server = Server::builder()
    ->setServerInfo('HTTP Server', '1.0.1')
    ->setDiscovery($root_path, ['mcp','vendor/cloudframework-io/backend-core-php8/src/mcp'])
    ->setSession($sessionStore)
    ->build();

// Create streamable transport with the PSR-7 request
$transport = new StreamableHttpTransport(
    $request,
    $psr17Factory, // ResponseFactory
    $psr17Factory,  // StreamFactory
    ['Access-Control-Allow-Origin' => '']
);

// Run the server and get the PSR-7 response
$response = $server->run($transport);

// Emit the response to the client
$emitter = new SapiEmitter();
$emitter->emit($response);