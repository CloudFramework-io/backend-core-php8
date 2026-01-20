<?php

namespace App\CFMcp;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Capability\Attribute\McpResource;
use Mcp\Capability\Attribute\McpPrompt;

class Auth extends \MCPCore7
{

    private $apiAuth='https://api.cloudframework.io/core/signin';
    private $oauthServer = 'https://api.cloudframework.io/cloud-solutions/directory/mcp-oauth';

    //region TOOLS

    //region set_token
    /**
     * Validates a provided token, fetches user details associated with the token,
     * and updates session information. If the token is invalid or user details
     * cannot be retrieved, returns an appropriate error message.
     *
     * @param string $token The token string to be validated and processed.
     * @return string|array Returns the user's KeyName if validation and processing are successful,
     *                      or an error message if the process fails.
     */
    #[McpTool(name: 'set_dstoken')]
    public function set_token(string $token): string|array
    {

        if(!strpos($token,'__')) return "Error: Token is not valid";
        list($platform,$foom) = explode('__',$token,2);
        $_SESSION['dstoken'] = null;

        if(!($this->secrets['api_login_integration_key']??null))
            if(!$this->readSecrets())
                return "Error [{$this->errorCode}] ".json_encode($this->errorMsg);

        $this->core->user->loadPlatformUserWithToken($token,$this->secrets['api_login_integration_key']);
        if($this->core->user->error) return "Error: token [{$token}] is not valid: ".json_encode($this->core->user->errorMsg);
        $_SESSION['dstoken'] = $token;
        return $this->core->user->id;


    }
    //endregion

    //region clean_dstoken
    /**
     * Clean de current dstoken associated to an user
     *
     * @return string Returns an empty string to indicate the session has been cleared.
     */
    #[McpTool(name: 'clean_dstoken')]
    public function clean_dstoken(): string
    {
        $_SESSION['dstoken'] = null;
        $this->core->user->token = null;
        //        $_SESSION['oauth_state'] = null;
        //        $_SESSION['oauth_code_verifier'] = null;

        return "ok";
    }
    //endregion

    //region clean_session
    /**
     * Clears all session variables and resets user authentication state.
     * Use this to completely log out and start fresh.
     *
     * @return string Returns "ok" to indicate the session has been cleared.
     */
    #[McpTool(name: 'clean_session')]
    public function clean_session(): string
    {
        // Clear all session variables
        $_SESSION = [];

        // Reset user state
        $this->core->user->reset();

        return "ok";
    }
    //endregion

    //region refresh_dstoken
    /**
     * Clean de current dstoken associated to an user
     *
     * @return string Returns an empty string to indicate the session has been cleared.
     */
    #[McpTool(name: 'refresh_dstoken')]
    public function refresh_dstoken(): string
    {
        if(empty($_SESSION['token'])) return "error: no access_token not found";
        $_SESSION['dstoken'] = null;
        $this->validateMCPOAuthToken($_SESSION['token']);
        if(!$this->core->user->isAuth()) {
            return "error: current  access_token can not generate a valid dstoken";
        }
        return "ok";
    }
    //endregion

    //region clean_token
    /**
     * Clears the current session data by resetting platform, user, and token
     * information to their initial states.
     *
     * @return string Returns an empty string to indicate the session has been cleared.
     */
    #[McpTool(name: 'test_dstoken')]
    public function test_dstoken(): string
    {

        if(!$dstoken = $this->core->user->token) return "Error: No token provided";
        if(!$user = $this->core->user->id) return "Error: No id loaded";

        if(!($this->secrets['api_login_integration_key']??null))
            if(!$this->readSecrets())
                return "Error [{$this->errorCode}] ".json_encode($this->errorMsg);

        $this->core->user->loadPlatformUserWithToken($dstoken,$this->secrets['api_login_integration_key']);
        if($this->core->user->error) return "Error: token [{$dstoken}] is not valid: ".json_encode($this->core->user->errorMsg);
        if($user != $this->core->user->id) return "Error: token [{$dstoken}] is not valid for user [{$user}]";

        return $this->core->user->id;
    }
    //endregion

    //region get_platform_user (Tool version of resource)
    /**
     * Get the current authenticated platform user information.
     * Returns user ID and profile data. Requires prior authentication via set_dstoken or OAuth.
     *
     * @return array User data with id and profile, or error if not authenticated
     */
    #[McpTool(name: 'get_platform_user')]
    public function getPlatformUserTool(): array
    {
        return $this->platform_user();
    }
    //endregion

    //region get_authenticate_dstoken_prompt (Tool version of prompt)
    /**
     * Get instructions for authenticating with CLOUD Platform using a dstoken.
     * Returns a structured prompt to guide through the authentication process.
     *
     * @param string $dstoken The dstoken provided by the user for authentication
     * @return array Authentication prompt with instructions
     */
    #[McpTool(name: 'get_authenticate_prompt')]
    public function getAuthenticatePromptTool(string $dstoken): array
    {
        $prompt = $this->authenticateDstokenPrompt($dstoken);
        return [
            'type' => 'prompt',
            'name' => 'authenticate_dstoken',
            'description' => 'Use these instructions to authenticate with CLOUD Platform',
            'messages' => $prompt
        ];
    }
    //endregion

    //region oauth_start
    /**
     * Starts an OAuth 2.1 authentication flow with CLOUD Platform.
     * Returns a URL that the user must open in their browser to authenticate.
     * After authentication, use oauth_complete with the authorization code received.
     * 
     * Uses PKCE (Proof Key for Code Exchange) for enhanced security.
     *
     * @param string $platform The platform identifier (e.g., 'cloudframework')
     * @param string $redirect_uri The redirect URI for the OAuth callback (default: urn:ietf:wg:oauth:2.0:oob for manual copy)
     * @return array Contains auth_url to open in browser, state for verification, and instructions
     */
    #[McpTool(name: 'oauth_start')]
    public function oauthStart(string $platform = 'cloudframework', string $redirect_uri = 'urn:ietf:wg:oauth:2.0:oob'): array
    {
        // Generate PKCE code verifier and challenge
        $codeVerifier = $this->generateCodeVerifier();
        $codeChallenge = $this->generateCodeChallenge($codeVerifier);
        
        // Generate state for CSRF protection
        $state = bin2hex(random_bytes(16));
        
        // Store in session for later verification
        $_SESSION['oauth_state'] = $state;
        $_SESSION['oauth_code_verifier'] = $codeVerifier;
        $_SESSION['oauth_platform'] = $platform;
        $_SESSION['oauth_redirect_uri'] = $redirect_uri;
        
        // Build authorization URL
        $authParams = [
            'response_type' => 'code',
            'client_id' => 'cloudia-mcp',
            'redirect_uri' => $redirect_uri,
            'scope' => 'openid profile email projects tasks',
            'state' => $state,
            'code_challenge' => $codeChallenge,
            'code_challenge_method' => 'S256',
            'platform' => $platform
        ];
        
        $authUrl = $this->oauthServer . '/authorize?' . http_build_query($authParams);

        return [
            'status' => 'pending_user_action',
            'action_required' => 'open_url_in_browser',

            // URL prominente para Claude Desktop
            'url' => $authUrl,
            'auth_url' => $authUrl,  // Alias por compatibilidad

            // Mensaje para mostrar al usuario
            'user_message' => "Para autenticarte, abre esta URL en tu navegador:\n\n{$authUrl}\n\nDespués de iniciar sesión, recibirás un código de autorización. Copia ese código y dímelo para completar la autenticación.",

            // Instrucciones para Claude
            'assistant_instructions' => [
                'Show the URL prominently to the user',
                'Ask the user to open the URL in their browser',
                'Wait for the user to provide the authorization code',
                'Once received, call oauth_complete(code) with the provided code'
            ],

            // Pasos para el usuario
            'steps' => [
                '1. Abre la URL de arriba en tu navegador',
                '2. Inicia sesión con tus credenciales de CLOUD Platform',
                '3. Autoriza el acceso cuando se te solicite',
                '4. Copia el código de autorización que recibirás',
                '5. Pega el código aquí para completar la autenticación'
            ],

            'next_action' => [
                'tool' => 'oauth_complete',
                'parameter' => 'code',
                'description' => 'Call oauth_complete with the authorization code provided by the user'
            ],

            'state' => $state
        ];
    }
    //endregion


    //region oauth_complete
    /**
     * Completes the OAuth 2.1 authentication flow by exchanging the authorization code for tokens.
     * Must be called after oauth_start and user authentication.
     *
     * @param string $code The authorization code received after user authentication
     * @param string $state Optional state parameter for verification (uses session state if not provided)
     * @return array Contains access_token, user info, or error message
     */
    #[McpTool(name: 'oauth_complete')]
    public function oauthComplete(string $code, string $state = ''): array
    {
        // Verify state if provided
        if (!empty($state) && $state !== ($_SESSION['oauth_state'] ?? '')) {
            return ['error' => true, 'message' => 'State mismatch - possible CSRF attack'];
        }
        
        // Get stored values
        $codeVerifier = $_SESSION['oauth_code_verifier'] ?? null;
        $redirectUri = $_SESSION['oauth_redirect_uri'] ?? 'urn:ietf:wg:oauth:2.0:oob';
        $platform = $_SESSION['oauth_platform'] ?? 'cloudframework';
        
        if (!$codeVerifier) {
            return ['error' => true, 'message' => 'No pending OAuth flow. Call oauth_start first.'];
        }
        
        // Exchange code for token
        $tokenParams = [
            'grant_type' => 'authorization_code',
            'client_id' => 'cloudia-mcp',
            'code' => $code,
            'redirect_uri' => $redirectUri,
            'code_verifier' => $codeVerifier
        ];
        
        $response = $this->core->request->post_json_decode(
            $this->oauthServer . '/token',
            $tokenParams,
            ['Content-Type' => 'application/x-www-form-urlencoded']
        );
        
        if ($this->core->request->error) {
            return [
                'error' => true,
                'message' => 'Token exchange failed',
                'details' => $this->core->request->errorMsg
            ];
        }
        
        if (isset($response['error'])) {
            return [
                'error' => true,
                'message' => $response['error_description'] ?? $response['error'],
                'error_code' => $response['error']
            ];
        }
        
        // Store token in session
        $accessToken = $response['access_token'] ?? null;
        $refreshToken = $response['refresh_token'] ?? null;
        if ($accessToken) {
            $_SESSION['token'] = $accessToken;
            $_SESSION['platform'] = $platform;
            if($refreshToken) $_SESSION['refresh_token'] = $refreshToken;

            
            // Clear OAuth flow data
            $_SESSION['oauth_state'] = null;
            $_SESSION['oauth_code_verifier'] = null;
            
            // Try to get user info
            $userInfo = $this->fetchUserInfo($accessToken);
            if ($userInfo && !isset($userInfo['error'])) {
                $_SESSION['user'] = $userInfo;
                
                // Set core user
                $this->core->user->id = $userInfo['KeyName'] ?? $userInfo['email'] ?? 'unknown';
                $this->core->user->token = $accessToken;
                $this->core->user->data = ['User' => $userInfo];
                $this->core->user->namespace = $platform;
                $this->core->user->isAuth = true;
            }
            
            return [
                'success' => true,
                'message' => 'Authentication successful',
                'user' => $userInfo ?? null,
                'platform' => $platform,
                'token_type' => $response['token_type'] ?? 'Bearer',
                'expires_in' => $response['expires_in'] ?? null
            ];
        }
        
        return ['error' => true, 'message' => 'No access token received'];
    }
    //endregion

    //region oauth_status
    /**
     * Check the current OAuth authentication status.
     * Returns whether there's an active session, pending OAuth flow, or no authentication.
     *
     * @return array Current authentication status and user info if authenticated
     */
    #[McpTool(name: 'oauth_status')]
    public function oauthStatus(): array
    {
        $hasPendingOAuth = !empty($_SESSION['oauth_state']);
        $hasToken = !empty($_SESSION['token']);
        $isAuthenticated = $this->core->user->isAuth();
        
        $status = [
            'authenticated' => $isAuthenticated,
            'has_token' => $hasToken,
            'pending_oauth_flow' => $hasPendingOAuth,
            'platform' => $_SESSION['platform'] ?? null
        ];
        
        if ($isAuthenticated) {
            $status['user'] = [
                'id' => $this->core->user->id,
                'namespace' => $this->core->user->namespace
            ];
        }
        
        if ($hasPendingOAuth) {
            $status['oauth_state'] = $_SESSION['oauth_state'];
            $status['instructions'] = 'Complete authentication by calling oauth_complete with the authorization code';
        }
        
        if (!$isAuthenticated && !$hasPendingOAuth) {
            $status['instructions'] = 'Start authentication by calling oauth_start or set_dstoken';
        }
        
        return $status;
    }
    //endregion

    //region oauth_refresh
    /**
     * Refresh the OAuth access token using a refresh token.
     * Only works if a refresh token was provided during initial authentication.
     *
     * @param string $refresh_token The refresh token to use
     * @return array New access token or error message
     */
    #[McpTool(name: 'oauth_refresh')]
    public function oauthRefresh(string $current_access_token='',string $refresh_token=''): array
    {
        if(empty($current_access_token) && empty($_SESSION['token']))
            return ['error'=>true,'message'=>'Error: no refresh token provided'];
        if(empty($refresh_token) && empty($_SESSION['refresh_token']))
            return ['error'=>true,'message'=>'Error: no refresh token provided'];

        $tokenParams = [
            'grant_type' => 'refresh_token',
            'client_id' => 'cloudia-mcp',
            'access_token' => ($current_access_token??'')?:$_SESSION['token'],
            'refresh_token' => $refresh_token??$_SESSION['refresh_token']
        ];
        
        $response = $this->core->request->post_json_decode(
            $this->oauthServer . '/token',
            $tokenParams,
            ['Content-Type' => 'application/x-www-form-urlencoded']
        );
        
        if ($this->core->request->error) {
            return [
                'error' => true,
                'message' => 'Token refresh failed',
                'details' => $this->core->request->errorMsg
            ];
        }
        
        if (isset($response['error'])) {
            return [
                'error' => true,
                'message' => $response['error_description'] ?? $response['error'],
            ];
        }
        
        $accessToken = $response['access_token'] ?? null;
        if ($accessToken) {
            $_SESSION['token'] = $accessToken;
            return [
                'success' => true,
                'message' => 'Token refreshed successfully',
                'token_type' => $response['token_type'] ?? 'Bearer',
                'expires_in' => $response['expires_in'] ?? null,
                'access_token' => $accessToken
            ];
        }
        
        return ['error' => true, 'message' => 'No access token in refresh response'];
    }
    //endregion

    //region get_oauth_config (Tool version of resource)
    /**
     * Get the OAuth 2.1 server configuration for CLOUD Platform.
     * Returns endpoints, supported scopes, grant types, and authentication methods.
     *
     * @return array OAuth server configuration
     */
    #[McpTool(name: 'get_oauth_config')]
    public function getOAuthConfigTool(): array
    {
        return $this->oauthConfig();
    }
    //endregion

    //region session_ping
    /**
     * Ping the MCP session to verify it is active and renew it.
     * Use this tool periodically to keep the session alive and prevent expiration.
     * If the session has expired, the client must reconnect.
     *
     * @return array Session status with remaining TTL
     */
    #[McpTool(name: 'session_ping')]
    public function sessionPing(): array
    {
        // Touch session to renew TTL
        $_SESSION['mcp_last_ping'] = time();
        
        $sessionStart = $_SESSION['mcp_timestamp'] ?? time();
        $ttl = 86400; // 24 hours // Default TTL
        $elapsed = time() - $sessionStart;
        $remaining = max(0, $ttl - $elapsed);
        
        return [
            'status' => 'active',
            'session_id' => session_id(),
            'authenticated' => $this->core->user->isAuth(),
            'user' => $this->core->user->id ?? null,
            'session_age_seconds' => $elapsed,
            'ttl_remaining_seconds' => $remaining,
            'last_ping' => date('Y-m-d H:i:s'),
            'message' => $remaining > 300 ? 'Session healthy' : 'Session expiring soon, consider reconnecting'
        ];
    }
    //endregion

    //region session_restore
    /**
     * Attempt to restore authentication after session loss.
     * This tool tries to re-authenticate using:
     * 1. OAuth token from Authorization header (automatic)
     * 2. Previously stored dstoken
     * 3. Provided token parameter
     *
     * Call this tool when you receive "Session not found or has expired" error.
     *
     * @param string $token Optional token to use for restoration (dstoken or OAuth token)
     * @return array Restoration status and user info if successful
     */
    #[McpTool(name: 'session_restore')]
    public function sessionRestore(string $token = ''): array
    {
        // Check if already authenticated via header
        if ($this->core->user->isAuth()) {
            return [
                'status' => 'already_authenticated',
                'method' => 'oauth_header',
                'user' => $this->core->user->id,
                'platform' => $this->core->user->namespace,
                'message' => 'Session restored automatically via OAuth header'
            ];
        }
        
        // Try with provided token
        if (!empty($token)) {
            $result = $this->set_token($token);
            if (is_string($result) && strpos($result, 'Error') === false) {
                return [
                    'status' => 'restored',
                    'method' => 'provided_token',
                    'user' => $result,
                    'message' => 'Session restored with provided token'
                ];
            } elseif (is_array($result) && !isset($result['error'])) {
                return [
                    'status' => 'restored',
                    'method' => 'provided_token',
                    'user' => $this->core->user->id,
                    'message' => 'Session restored with provided token'
                ];
            }
        }
        
        // Check for stored dstoken in session
        $storedToken = $_SESSION['dstoken'] ?? $_SESSION['token'] ?? null;
        if ($storedToken) {
            $result = $this->set_token($storedToken);
            if (is_string($result) && strpos($result, 'Error') === false) {
                return [
                    'status' => 'restored',
                    'method' => 'stored_token',
                    'user' => $result,
                    'message' => 'Session restored with stored token'
                ];
            }
        }
        
        // No way to restore
        return [
            'status' => 'failed',
            'error' => true,
            'message' => 'Could not restore session. Please authenticate again using oauth_start or set_dstoken',
            'options' => [
                '1. Call oauth_start() to begin OAuth authentication',
                '2. Call set_dstoken(token) with a valid dstoken',
                '3. Configure mcp-remote with --header "Authorization: Bearer TOKEN"'
            ]
        ];
    }
    //endregion

    //endregion

    //region RESOURCES

    //region platform_user
    /**
     * Returns the current authenticated user information.
     * Provides read-only access to user data including ID and profile.
     *
     * @return array User data with id and profile, or error if not authenticated
     */
    #[McpResource(uri: 'auth://platform/user', name: 'platform_user', description: 'Current authenticated platform user information')]
    public function platform_user(): array
    {
        if(!$this->core->user->isAuth())
            return ['error'=>true,'message'=>'Error: use set_dstoken, oauth_start/oauth_complete, or send OAuth token in header'];
        elseif(empty($this->core->user->data['User']))
            return ['error'=>true,'message'=>'Error: missing user data info'];
        else
            return [
                'id'=>$this->core->user->id ?? 'Error: missing',
                'user'=>$this->core->user->data['User'],
            ];
    }
    //endregion

    //region oauth_config
    /**
     * Returns the OAuth configuration for CLOUD Platform.
     * Includes endpoints and supported features.
     *
     * @return array OAuth server configuration
     */
    #[McpResource(uri: 'auth://oauth/config', name: 'oauth_config', description: 'OAuth 2.1 server configuration for CLOUD Platform')]
    public function oauthConfig(): array
    {
        return [
            'issuer' => 'https://api.cloudframework.io',
            'authorization_endpoint' => $this->oauthServer . '/authorize',
            'token_endpoint' => $this->oauthServer . '/token',
            'userinfo_endpoint' => $this->oauthServer . '/userinfo',
            'registration_endpoint' => $this->oauthServer . '/register',
            'scopes_supported' => ['openid', 'profile', 'email', 'projects', 'tasks'],
            'response_types_supported' => ['code'],
            'grant_types_supported' => ['authorization_code', 'refresh_token'],
            'code_challenge_methods_supported' => ['S256'],
            'token_endpoint_auth_methods_supported' => ['none', 'client_secret_basic']
        ];
    }
    //endregion

    //region oauth_status resource
    /**
     * Returns the current OAuth authentication status as a resource.
     * Provides read-only access to session state and user info.
     *
     * @return array Current authentication status
     */
    #[McpResource(uri: 'auth://oauth/status', name: 'oauth_status', description: 'Current OAuth authentication status including session state and user info')]
    public function oauthStatusResource(): array
    {
        return $this->oauthStatus();
    }
    //endregion

    //endregion

    //region PROMPTS

    //region authenticate_dstoken
    /**
     * Generates a prompt to guide the user through CLOUD Platform authentication using a dstoken.
     *
     * @param string $dstoken The dstoken provided by the user for authentication
     * @return array Prompt messages for dstoken authentication
     */
    #[McpPrompt(name: 'authenticate_dstoken', description: 'Authenticate with CLOUD Platform using a dstoken')]
    public function authenticateDstokenPrompt(string $dstoken): array
    {
        return [
            [
                'role' => 'assistant',
                'content' => 'I will help you authenticate with CLOUD Platform using your dstoken. The dstoken is a security token that allows access to CloudFramework services and APIs.'
            ],
            [
                'role' => 'user',
                'content' => "Please authenticate me with CLOUD Platform using this dstoken: {$dstoken}\n\nUse the set_dstoken tool to validate and establish the session. After authentication, confirm my user identity by reading the platform_user resource."
            ]
        ];
    }
    //endregion

    //region authenticate_oauth
    /**
     * Generates a prompt to guide the user through OAuth 2.1 authentication with CLOUD Platform.
     *
     * @param string $platform The platform to authenticate with (default: cloudframework)
     * @return array Prompt messages for OAuth authentication flow
     */
    #[McpPrompt(name: 'authenticate_oauth', description: 'Authenticate with CLOUD Platform using OAuth 2.1 flow')]
    public function authenticateOAuthPrompt(string $platform = 'cloudframework'): array
    {
        return [
            [
                'role' => 'assistant',
                'content' => 'I will help you authenticate with CLOUD Platform using OAuth 2.1. This is a secure authentication flow that requires you to sign in through your browser.'
            ],
            [
                'role' => 'user',
                'content' => "Please start the OAuth authentication process for platform: {$platform}\n\nSteps:\n1. Call oauth_start to get the authorization URL\n2. I will open the URL in my browser and sign in\n3. After signing in, I'll receive an authorization code\n4. Call oauth_complete with the code to finish authentication\n5. Confirm my identity with get_platform_user"
            ]
        ];
    }
    //endregion

    //endregion

    //region PRIVATE METHODS

    /**
     * Generate a random code verifier for PKCE
     * @return string
     */
    private function generateCodeVerifier(): string
    {
        $randomBytes = random_bytes(32);
        return rtrim(strtr(base64_encode($randomBytes), '+/', '-_'), '=');
    }

    /**
     * Generate code challenge from verifier using S256
     * @param string $codeVerifier
     * @return string
     */
    private function generateCodeChallenge(string $codeVerifier): string
    {
        $hash = hash('sha256', $codeVerifier, true);
        return rtrim(strtr(base64_encode($hash), '+/', '-_'), '=');
    }

    /**
     * Fetch user info from OAuth server
     * @param string $accessToken
     * @return array|null
     */
    private function fetchUserInfo(string $accessToken): ?array
    {
        $response = $this->core->request->get_json_decode(
            $this->oauthServer . '/userinfo',
            null,
            ['Authorization' => 'Bearer ' . $accessToken]
        );
        
        if ($this->core->request->error) {
            return null;
        }
        
        return $response['data'] ?? $response;
    }

    //endregion

}
