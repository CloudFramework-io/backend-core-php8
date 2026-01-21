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
        $oauthToken = substr($this->api->getHeader('Authorization') ?? '',7);
        if(!$oauthToken && !empty($_SESSION['token'] ))
            $oauthToken = $_SESSION['token'];
        if($oauthToken && strpos($oauthToken, '__mcp_') !== false) {
            if (($_SESSION['token'] ?? null) !== $oauthToken
                || !$this->initUserFromSession()
            ) {
                $this->validateMCPOAuthToken($oauthToken);
            }
        }
        //X-DS-TOKEN
        elseif($dstoken = $_SESSION['dstoken']??null) {
            $this->readSecrets();
            if(!empty($this->secrets['api_login_integration_key'])) {
                $this->core->user->loadPlatformUserWithToken($dstoken, $this->secrets['api_login_integration_key']);
                if ($this->core->user->error) {
                    $this->error = true;
                    $this->errorCode = 'dstoken-auth-error';
                    $this->errorMsg = $this->core->user->errorMsg;
                }
            }
        }

        // debug logs
        //                if($this->core->is->development())
        //                    $this->showLogs();

    }

    /**
     * Initialize user from the current session data.
     * @return bool true if the user is successfully initialized, false otherwise
     */
    private function initUserFromSession(): bool
    {
        $this->core->user->reset();
        if ( !empty($_SESSION['user'])
            && !empty($_SESSION['data'])
            && !empty($_SESSION['platform'])
        ) {
            $this->core->user->token = $_SESSION['dstoken']??null;
            $this->core->user->id = $_SESSION['user'];
            $this->core->user->data = ['User'=>$_SESSION['data']];
            $this->core->user->namespace = $_SESSION['platform'];
            $this->core->user->isAuth = true;
            return true;
        }
        return false;
    }

    //    /**
    //     * Logs session details, headers, user information, privileges, and request parameters.
    //     * @return void
    //     */
    //            private function showLogs(): void
    //            {
    //                $this->core->logs->add($this->sessionId, 'sessionId');
    //                $this->core->logs->add($this->api->getHeaders(), 'headers');
    //                $this->core->logs->add($_SESSION, 'session');
    //                $this->core->logs->add($this->core->user->id??'no-user', 'user');
    //                $this->core->logs->add($this->core->user->getPrivileges(), 'privileges');
    //                if ($this->api->params) $this->core->logs->add($this->api->params, 'params');
    //                if ($this->api->formParams) {
    //                    unset($this->api->formParams['_raw_input_']);
    //                    $this->core->logs->add($this->api->formParams, 'formParams');
    //                }
    //            }

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
     * Validates the MCP OAuth token and initializes the user session upon successful authentication.
     *
     * @param string $oauthToken The OAuth token to be validated.
     * @return bool
     */
    protected function validateMCPOAuthToken(string $oauthToken): bool
    {
        $params = [];
        if(empty($_SESSION['token'])
            || empty($_SESSION['dstoken'])
            || $_SESSION['token'] === $oauthToken  ) {
            $params['dstoken'] = 1;
        }
        $response = $this->core->request->get_json_decode(
            "https://api.cloudframework.io/cloud-solutions/directory/mcp-oauth/validate",
            $params,
            ['Authorization' => 'Bearer ' . $oauthToken]
        );

        if ($this->core->request->error) {
            $this->core->logs->add('MCP OAuth validation failed: ' . json_encode($this->core->request->errorMsg), 'oauth-access_token_verification-error');
            $this->error = true;
            $this->errorCode = 'oauth-access_token_verification-error';
            $this->errorMsg = ['MCP OAuth validation failed: ' . json_encode($this->core->request->errorMsg)];
            return false;
        }

        if (!($response['data']['valid'] ?? false)) {
            $this->core->logs->add('MCP OAuth token invalid: ' . ($response['data']['error'] ?? 'unknown'), 'oauth-access_token_verification-error');
            $this->error = true;
            $this->errorCode = 'oauth-access_token_verification-error';
            $this->errorMsg = ['MCP OAuth token invalid: ' . ($response['data']['error'] ?? 'unknown')];
            return false;
        }

        if (!($response['data']['user'] ?? false)) {
            $this->core->logs->add('MCP OAuth token verification error. It missed [user] attribute','oauth-access_token_verification-error');
            $this->error = true;
            $this->errorCode = 'oauth-access_token_verification-error';
            $this->errorMsg = ['MCP OAuth token verification error. It missed [user] attribute'];
            return false;
        }
        if (!($response['data']['data'] ?? false)) {
            $this->core->logs->add('MCP OAuth token verification error. It missed [data] attribute','oauth-access_token_verification-error');
            $this->error = true;
            $this->errorCode = 'oauth-access_token_verification-error';
            $this->errorMsg = ['MCP OAuth token verification error. It missed [data] attribute'];
            return false;
        }
        if (!($response['data']['platform'] ?? false)) {
            $this->core->logs->add('MCP OAuth token verification error. It missed [platform] attribute','oauth-access_token_verification-error');
            $this->error = true;
            $this->errorCode = 'oauth-access_token_verification-error';
            $this->errorMsg = ['MCP OAuth token verification error. It missed [platform] attribute'];
            return false;
        }
        if (!empty($params['dstoken']) &&  !($response['data']['dstoken'] ?? false)) {
            $this->core->logs->add('MCP OAuth token verification error. It missed [dstoken] attribute','oauth-access_token_verification-error');
            $this->error = true;
            $this->errorCode = 'oauth-access_token_verification-error';
            $this->errorMsg = ['MCP OAuth token verification error. It missed [dstoken] attribute'];
            return false;
        }

        $_SESSION['token'] = $oauthToken;
        $_SESSION['user'] = $response['data']['user'];
        $_SESSION['data'] = $response['data']['data'];
        $_SESSION['platform'] = $response['data']['platform'] ?? 'cloudframework';
        if(!empty($params['dstoken']))
            $_SESSION['dstoken'] = $response['data']['dstoken'];

        $this->initUserFromSession();

        $this->core->logs->add('MCP OAuth AccessToken authenticated: ' . $this->core->user->id, 'oauth');

        return true;
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
     * Initializes the CFOs object.
     * Ensures the object is only created once, loads necessary secrets,
     * and configures the CFOs instance for the current platform.
     *
     * @return bool Returns true if initialization is successful, false on error.
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

/**
 * Class PhpSessionStore
 * Implements a session management system based on PHP sessions, using a UUID as a session identifier.
 * Provides methods for starting sessions, checking existence, reading, writing, and destroying session data.
 */
class PhpSessionStore implements SessionStoreInterface
{
    private const SESSION_KEY = 'mcp_data';

    /**
     * Constructor method for the class.
     *
     * @param int $ttl Time-to-live value in seconds. Default is 3600.
     * @return void
     */
    public function __construct(
        private readonly int $ttl = 3600
    ) {
    }

    /**
     * Starts a new session for the provided UUID.
     * Closes any existing active session and initializes a new session using a
     * sanitized version of the provided UUID as the session ID.
     *
     * @param Uuid $id The UUID used to generate a valid PHP session ID.
     * @return void
     */
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

    /**
     * Checks whether a session exists and is valid for the provided UUID.
     * Auto-creates the session if it doesn't exist to avoid "Session not found" errors.
     *
     * @param Uuid $id The UUID used to identify the session.
     * @return bool Always returns true (auto-creates session if needed).
     */
    public function exists(Uuid $id): bool
    {
        $this->startSessionFor($id);

        // Auto-create session if it doesn't exist
        if (!isset($_SESSION[self::SESSION_KEY])) {
            $_SESSION[self::SESSION_KEY] = '{}';
            $_SESSION['mcp_timestamp'] = time();
            return true;
        }

        $timestamp = $_SESSION['mcp_timestamp'] ?? 0;

        // Renew expired sessions instead of destroying them
        if ((time() - $timestamp) > $this->ttl) {
            $_SESSION[self::SESSION_KEY] = '{}';
            $_SESSION['mcp_timestamp'] = time();
        }

        return true;
    }

    /**
     * Reads the stored session value for the provided UUID.
     * Auto-creates the session if it doesn't exist to avoid "Session not found" errors.
     *
     * @param Uuid $id The UUID used to identify and access the session.
     * @return string The session value (auto-creates with empty JSON if not exists).
     */
    public function read(Uuid $id): string|false
    {
        $this->startSessionFor($id);

        // Auto-create session if it doesn't exist
        if (!isset($_SESSION[self::SESSION_KEY])) {
            $_SESSION[self::SESSION_KEY] = '{}';
            $_SESSION['mcp_timestamp'] = time();
            return $_SESSION[self::SESSION_KEY];
        }

        $timestamp = $_SESSION['mcp_timestamp'] ?? 0;

        // Renew expired sessions instead of destroying them
        if ((time() - $timestamp) > $this->ttl) {
            $_SESSION[self::SESSION_KEY] = '{}';
            $_SESSION['mcp_timestamp'] = time();
        }

        return $_SESSION[self::SESSION_KEY];
    }

    /**
     * Writes data to the session for the given UUID.
     * Initiates a session for the provided UUID, stores the given data and a timestamp
     * in the session, and closes the session after writing.
     *
     * @param Uuid $id The UUID used to identify and start a specific session.
     * @param string $data The data to be written to the session.
     * @return bool Returns true upon successfully writing data to the session.
     */
    public function write(Uuid $id, string $data): bool
    {
        $this->startSessionFor($id);

        $_SESSION[self::SESSION_KEY] = $data;
        $_SESSION['mcp_timestamp'] = time();

        session_write_close();

        return true;
    }

    /**
     * Destroys the active session associated with the provided UUID.
     * Starts a session for the given UUID before attempting to destroy it,
     * ensuring the correct session is targeted.
     *
     * @param Uuid $id The UUID used to identify the session to be destroyed.
     * @return bool Returns true after the session is successfully destroyed.
     */
    public function destroy(Uuid $id): bool
    {
        $this->startSessionFor($id);

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }

        return true;
    }

    /**
     * Performs garbage collection for PHP sessions automatically.
     *
     * PHP's session garbage collection operates based on the configuration
     * settings for session.gc_probability and session.gc_maxlifetime.
     *
     * @return array Returns an empty array as the result of garbage collection.
     */
    public function gc(): array
    {
        // PHP's session garbage collection handles this automatically
        // based on session.gc_probability and session.gc_maxlifetime
        return [];
    }
}

