<?php
/**
 * MCP SERVER WITH SSO SUPPORT
 */
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

//region OAuth 2.1 Resource Server Implementation
/**
 * MCPOAuthValidator - Validates OAuth 2.1 tokens for MCP Resource Server
 * Implements RFC 8707 (Resource Indicators) and MCP Authorization spec
 */
class MCPOAuthValidator
{
    private Core7 $core;
    private array $secrets = [];
    private string $platform = 'cloudframework';
    private string $authApiUrl = 'https://api.cloudframework.io/core/signin';

    public bool $error = false;
    public ?string $errorCode = null;
    public array $errorMsg = [];
    public ?array $user = null;

    public function __construct(Core7 $core)
    {
        $this->core = $core;
    }

    /**
     * Validate an OAuth token against CloudFramework's auth API or MCP OAuth Datastore
     * @param string $token The Bearer token to validate
     * @return bool True if token is valid
     */
    public function validateToken(string $token): bool
    {
        if (empty($token)) {
            $this->error = true;
            $this->errorCode = 'invalid_token';
            $this->errorMsg[] = 'Token is empty';
            return false;
        }

        // Extract platform from token if present (format: platform__token_data)
        if (strpos($token, '__') !== false) {
            list($this->platform, ) = explode('__', $token, 2);
        }

        // Check if this is an MCP OAuth token (contains __mcp_)
        if (strpos($token, '__mcp_') !== false) {
            return $this->validateMCPOAuthToken($token);
        }

        // Read secrets for API validation
        if (!$this->readSecrets()) {
            return false;
        }

        // Validate token with CloudFramework API
        $response = $this->core->request->get_json_decode(
            "{$this->authApiUrl}/{$this->platform}/check",
            null,
            [
                'X-WEB-KEY' => 'mcp-oauth',
                'X-DS-TOKEN' => $token,
                'X-EXTRA-INFO' => $this->secrets['api_login_integration_key']
            ]
        );

        if ($this->core->request->error) {
            $this->error = true;
            $this->errorCode = 'invalid_token';
            $this->errorMsg[] = 'Token validation failed: ' . json_encode($this->core->request->errorMsg);
            return false;
        }

        if (!isset($response['data']['User']['KeyName'])) {
            $this->error = true;
            $this->errorCode = 'invalid_token';
            $this->errorMsg[] = 'Token response missing user data';
            return false;
        }

        $this->user = $response['data']['User'];
        return true;
    }

    /**
     * Validate an MCP OAuth token against Datastore
     * @param string $token The MCP OAuth token to validate
     * @return bool True if token is valid
     */
    private function validateMCPOAuthToken(string $token): bool
    {
        // Validate via API endpoint
        $response = $this->core->request->get_json_decode(
            "https://api.cloudframework.io/cloud-solutions/directory/mcp-oauth/validate",
            null,
            ['Authorization' => 'Bearer ' . $token]
        );

        if ($this->core->request->error) {
            $this->error = true;
            $this->errorCode = 'invalid_token';
            $this->errorMsg[] = 'MCP OAuth token validation failed: ' . json_encode($this->core->request->errorMsg);
            return false;
        }

        if (!($response['data']['valid'] ?? false)) {
            $this->error = true;
            $this->errorCode = 'invalid_token';
            $this->errorMsg[] = $response['data']['error'] ?? 'Token is invalid or expired';
            return false;
        }

        // Set user from token data
        $this->user = [
            'KeyName' => $response['data']['client_id'] ?? 'mcp_oauth_user',
            'scope' => $response['data']['scope'] ?? ''
        ];

        return true;
    }

    /**
     * Read platform secrets for API authentication
     */
    private function readSecrets(): bool
    {
        if (!$this->core->security->readPlatformSecretVars('cfo-secrets', $this->platform)) {
            $this->error = true;
            $this->errorCode = 'server_error';
            $this->errorMsg[] = 'Failed to read secrets: ' . json_encode($this->core->security->errorMsg);
            return false;
        }

        $this->secrets['api_login_integration_key'] = $this->core->security->getPlatformSecretVar('api_login_integration_key');

        if (empty($this->secrets['api_login_integration_key'])) {
            $this->error = true;
            $this->errorCode = 'server_error';
            $this->errorMsg[] = 'Missing api_login_integration_key in secrets';
            return false;
        }

        return true;
    }

    /**
     * Get the validated user data
     */
    public function getUser(): ?array
    {
        return $this->user;
    }

    /**
     * Get the platform from the token
     */
    public function getPlatform(): string
    {
        return $this->platform;
    }
}


// Validate OAuth token for MCP requests
$authHeader = $request->getHeaderLine('Authorization');
$oauthValidator = new MCPOAuthValidator($core);
$oauthUser = null;

if ($authHeader && str_starts_with($authHeader, 'Bearer ')) {
    $token = substr($authHeader, 7);

    if ($oauthValidator->validateToken($token)) {
        $oauthUser = $oauthValidator->getUser();
        // Store in a way that MCP handlers can access
        $GLOBALS['mcp_oauth_user'] = $oauthUser;
        $GLOBALS['mcp_oauth_token'] = $token;
        $GLOBALS['mcp_oauth_platform'] = $oauthValidator->getPlatform();
    } else {
        // Invalid token - return 401
        $errorResponse = [
            'error' => $oauthValidator->errorCode,
            'error_description' => implode('; ', $oauthValidator->errorMsg)
        ];

        $response = $psr17Factory->createResponse(401)
            ->withHeader('WWW-Authenticate', 'Bearer realm="mcp", error="invalid_token", error_description="The token is invalid or expired"')
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Access-Control-Allow-Origin', '*')
            ->withBody($psr17Factory->createStream(json_encode($errorResponse)));
        (new SapiEmitter())->emit($response);
        exit;
    }
}
// Note: If no Authorization header, we allow the request to proceed
// The MCP tools can check authentication status and respond accordingly
// This allows tools like 'set_token' to work without prior auth
//endregion

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
        $this->platform = $_SESSION['platform'] ?? 'cloudframework';

        // Auto-initialize from OAuth if available (Bearer token was validated)
        if (isset($GLOBALS['mcp_oauth_user']) && isset($GLOBALS['mcp_oauth_token'])) {
            $_SESSION['user'] = $GLOBALS['mcp_oauth_user'];
            $_SESSION['token'] = $GLOBALS['mcp_oauth_token'];
            $_SESSION['platform'] = $GLOBALS['mcp_oauth_platform'] ?? 'cloudframework';
            $this->platform = $_SESSION['platform'];
        }

        //debug logs
        $this->core->logs->add($this->api->getHeaders(), 'headers');
        $this->core->logs->add($this->sessionId, 'sessionId');
        $this->core->logs->add($this->api->params, 'params');
        unset($this->api->formParams['_raw_input_']);
        $this->core->logs->add($this->api->formParams, 'formParams');
    }

    /**
     * Check if the request was authenticated via OAuth Bearer token
     * @return bool True if OAuth authentication is present
     */
    public function isOAuthAuthenticated(): bool
    {
        return isset($GLOBALS['mcp_oauth_user']) && isset($GLOBALS['mcp_oauth_token']);
    }

    /**
     * Get the OAuth authenticated user data
     * @return array|null User data or null if not authenticated
     */
    public function getOAuthUser(): ?array
    {
        return $GLOBALS['mcp_oauth_user'] ?? null;
    }

    /**
     * Initializes the CFOs class instance for the current object.
     * @return bool Returns true if the CFOs object is successfully initialized
     */
    public function initCFOs(): bool
    {
        // Avoid create the object multiple times
        if(is_object($this->cfos)) return true;

        if($this->error) return false;
        if(!($this->secrets['api_cfos_integration_key']??null))
            if(!$this->readSecrets()) return false;

        $this->cfos = $this->core->loadClass('CFOs',$this->secrets['api_cfos_integration_key']);
        if($this->cfos->error) return($this->setErrorFromCodelib('system-error',$this->cfos->errorMsg));
        if(isset($this->formParams['_reload_cache']) || isset($this->formParams['_reload_cfos'])) $this->cfos->resetCache();
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

        if (!$this->secrets['api_login_integration_key'])
            $this->setErrorFromCodelib('configuration-error', "Missing api_login_integration_key in Platform Secret: cfo-secrets");

        if (!$this->secrets['api_cfos_integration_key'])
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

// Run the server and get the PSR-7 response
$response = $server->run($transport);

// Emit the response to the client
$emitter = new SapiEmitter();
$emitter->emit($response);