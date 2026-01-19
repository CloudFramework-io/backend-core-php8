<?php
use Mcp\Server\Session\SessionStoreInterface;
use Symfony\Component\Uid\Uuid;

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
                if(!$this->core->user->isAuth()) {
                    $this->error = true;
                    $this->errorMsg = ['Invalid OAuth token'];
                }
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
        $_SESSION['platform'] = $response['data']['platform'] ?? 'cloudframework';

        $this->core->user->id = $_SESSION['user']['KeyName'];
        $this->core->user->token = $_SESSION['token'];
        $this->core->user->data = ['User' => $_SESSION['user']];
        $this->core->user->namespace = $this->platform = $_SESSION['platform'];
        $this->core->user->isAuth = true;


        $this->core->logs->add('MCP OAuth authenticated: ' . $this->core->user->id, 'oauth');
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
        $_SESSION['platform'] = $response['data']['platform'] ?? 'cloudframework';

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

