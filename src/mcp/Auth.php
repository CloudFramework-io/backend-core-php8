<?php

namespace App\CFMcp;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Capability\Attribute\McpResource;
use Mcp\Capability\Attribute\McpPrompt;

class Auth extends \MCPCore7
{

    private $apiAuth='https://api.cloudframework.io/core/signin';

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

    //region user
    /**
     * Clears the current session data by resetting platform, user, and token
     * information to their initial states.
     *
     * @return string Returns an empty string to indicate the session has been cleared.
     */
    #[McpTool(name: 'clean_dstoken')]
    public function clean_token(): string
    {
        $_SESSION['dstoken'] = null;

        return "ok";
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
            return ['error'=>true,'message'=>'Error: use set_dstoken first or send OAuth token in header'];
        else
            return [
                'id'=>$this->core->user->id ?? 'Error: missing',
                'user'=>$this->core->user->data['User'],
            ];
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

    //endregion

}