<?php
/**
 * Default WellKnown metadata for CloudFramework MCP Server
 *
 * This class provides default OAuth/OpenID Connect discovery endpoint metadata.
 * Projects can override this by creating their own mcp/WellKnown.php
 * with namespace App\Mcp\WellKnown
 *
 * Supported endpoints:
 * - /.well-known/oauth-protected-resource (RFC 8707)
 * - /.well-known/oauth-authorization-server (RFC 8414)
 * - /.well-known/openid-configuration (OpenID Connect Discovery)
 * - /.well-known/jwks.json (JSON Web Key Set)
 * - /.well-known/mcp.json (MCP Server metadata)
 */

class WellKnown
{
    /** @var string CloudFramework OAuth server base URL */
    protected const AUTH_SERVER = 'https://api.cloudframework.io';

    /** @var string OAuth endpoints base path */
    protected const OAUTH_PATH = '/cloud-solutions/directory/mcp-oauth';

    /**
     * OAuth Protected Resource Metadata (RFC 8707)
     * Endpoint: /.well-known/oauth-protected-resource
     *
     * @param string $serverUrl Base URL of the server (e.g., https://example.cloudframework.io)
     * @return array Protected resource metadata
     */
    public static function getProtectedResourceMetadata(string $serverUrl): array
    {
        return [
            //            'resource' => $serverUrl,
            //            'authorization_servers' => [
            //                self::AUTH_SERVER
            //            ],
            //            'bearer_methods_supported' => ['header'],
            //            'resource_documentation' => 'https://docs.cloudframework.io/mcp',
            //            'resource_signing_alg_values_supported' => ['RS256'],
            //            'resource_name' => 'CloudFramework MCP Server',
            //            'resource_policy_uri' => 'https://cloudframework.io/privacy'
        ];
    }

    /**
     * OAuth Authorization Server Metadata (RFC 8414)
     * Endpoint: /.well-known/oauth-authorization-server
     *
     * @return array Authorization server metadata
     */
    public static function getAuthorizationServerMetadata(): array
    {
        return [
            //            'issuer' => self::AUTH_SERVER,
            //            'authorization_endpoint' => self::AUTH_SERVER . self::OAUTH_PATH . '/authorize',
            //            'token_endpoint' => self::AUTH_SERVER . self::OAUTH_PATH . '/token',
            //            'userinfo_endpoint' => self::AUTH_SERVER . self::OAUTH_PATH . '/userinfo',
            //            'revocation_endpoint' => self::AUTH_SERVER . self::OAUTH_PATH . '/revoke',
            //            'introspection_endpoint' => self::AUTH_SERVER . self::OAUTH_PATH . '/introspect',
            //            'jwks_uri' => self::AUTH_SERVER . '/.well-known/jwks.json',
            //            'token_endpoint_auth_methods_supported' => ['none', 'client_secret_basic', 'client_secret_post'],
            //            'grant_types_supported' => ['authorization_code', 'refresh_token'],
            //            'response_types_supported' => ['code'],
            //            'response_modes_supported' => ['query', 'fragment'],
            //            'code_challenge_methods_supported' => ['S256'],  // PKCE required
            //            'scopes_supported' => ['openid', 'profile', 'email', 'offline_access', 'projects', 'tasks'],
            //            'claims_supported' => ['sub', 'iss', 'aud', 'exp', 'iat', 'name', 'email', 'email_verified'],
            //            'service_documentation' => 'https://docs.cloudframework.io/oauth'
        ];
    }

    /**
     * OpenID Connect Discovery Metadata
     * Endpoint: /.well-known/openid-configuration
     *
     * @return array OpenID Connect configuration
     */
    public static function getOpenIDConfiguration(): array
    {
        return [
            //            'issuer' => self::AUTH_SERVER,
            //            'authorization_endpoint' => self::AUTH_SERVER . self::OAUTH_PATH . '/authorize',
            //            'token_endpoint' => self::AUTH_SERVER . self::OAUTH_PATH . '/token',
            //            'userinfo_endpoint' => self::AUTH_SERVER . self::OAUTH_PATH . '/userinfo',
            //            'jwks_uri' => self::AUTH_SERVER . '/.well-known/jwks.json',
            //            'revocation_endpoint' => self::AUTH_SERVER . self::OAUTH_PATH . '/revoke',
            //            'introspection_endpoint' => self::AUTH_SERVER . self::OAUTH_PATH . '/introspect',
            //            'registration_endpoint' => null,  // Dynamic registration not supported
            //            'scopes_supported' => ['openid', 'profile', 'email', 'offline_access', 'projects', 'tasks'],
            //            'response_types_supported' => ['code'],
            //            'response_modes_supported' => ['query', 'fragment'],
            //            'grant_types_supported' => ['authorization_code', 'refresh_token'],
            //            'subject_types_supported' => ['public'],
            //            'id_token_signing_alg_values_supported' => ['RS256'],
            //            'token_endpoint_auth_methods_supported' => ['none', 'client_secret_basic', 'client_secret_post'],
            //            'code_challenge_methods_supported' => ['S256'],
            //            'claims_supported' => [
            //                'sub',
            //                'iss',
            //                'aud',
            //                'exp',
            //                'iat',
            //                'auth_time',
            //                'nonce',
            //                'name',
            //                'given_name',
            //                'family_name',
            //                'email',
            //                'email_verified',
            //                'picture',
            //                'locale'
            //            ],
            //            'claims_parameter_supported' => false,
            //            'request_parameter_supported' => false,
            //            'request_uri_parameter_supported' => false,
            //            'service_documentation' => 'https://docs.cloudframework.io/oauth'
        ];
    }

    /**
     * JSON Web Key Set (JWKS)
     * Endpoint: /.well-known/jwks.json
     *
     * Returns the public keys used to verify JWT signatures.
     * In production, these should be the actual RSA public keys.
     *
     * @return array JWKS structure
     */
    public static function getJWKS(): array
    {
        return [
            //            'keys' => [
            //                [
            //                    'kty' => 'RSA',
            //                    'use' => 'sig',
            //                    'alg' => 'RS256',
            //                    'kid' => 'cloudframework-mcp-key-1',
            //                    // Note: In production, replace with actual public key values
            //                    // These are placeholders that should be overridden by the project
            //                    'n' => '',  // RSA modulus (base64url encoded)
            //                    'e' => 'AQAB'  // RSA exponent (base64url encoded, typically "AQAB" for 65537)
            //                ]
            //            ]
        ];
    }

    /**
     * MCP Server Metadata
     * Endpoint: /.well-known/mcp.json
     *
     * Custom metadata for MCP (Model Context Protocol) servers.
     *
     * @param string $serverUrl Base URL of the server
     * @return array MCP server metadata
     */
    public static function getMCPMetadata(string $serverUrl): array
    {
        return [
            //            'name' => 'CloudFramework MCP Server',
            //            'version' => '1.0.0',
            //            'protocol_version' => '2024-11-05',
            //            'description' => 'MCP Server powered by CloudFramework',
            //            'homepage' => 'https://cloudframework.io',
            //            'documentation' => 'https://docs.cloudframework.io/mcp',
            //            'endpoints' => [
            //                'mcp' => $serverUrl . '/mcp',
            //                'oauth_protected_resource' => $serverUrl . '/.well-known/oauth-protected-resource',
            //                'oauth_authorization_server' => $serverUrl . '/.well-known/oauth-authorization-server',
            //                'openid_configuration' => $serverUrl . '/.well-known/openid-configuration'
            //            ],
            //            'capabilities' => [
            //                'tools' => true,
            //                'resources' => true,
            //                'prompts' => true,
            //                'logging' => true
            //            ],
            //            'authentication' => [
            //                'type' => 'oauth2',
            //                'authorization_server' => self::AUTH_SERVER,
            //                'pkce_required' => true
            //            ]
        ];
    }

    /**
     * Get all supported well-known endpoints
     *
     * Override this method in your project's WellKnown.php to:
     * - Add custom endpoints (e.g., /.well-known/ai-plugin.json)
     * - Remove endpoints you don't need
     * - Change the method mapping
     *
     * @return array Endpoint configuration: path => ['method' => string, 'needsServerUrl' => bool]
     */
    public static function getSupportedEndpoints(): array
    {
        return [
            '/.well-known/oauth-protected-resource' => [
                'method' => 'getProtectedResourceMetadata',
                'needsServerUrl' => true
            ],
            '/.well-known/oauth-authorization-server' => [
                'method' => 'getAuthorizationServerMetadata',
                'needsServerUrl' => false
            ],
            '/.well-known/openid-configuration' => [
                'method' => 'getOpenIDConfiguration',
                'needsServerUrl' => false
            ],
            '/.well-known/jwks.json' => [
                'method' => 'getJWKS',
                'needsServerUrl' => false
            ],
            '/.well-known/mcp.json' => [
                'method' => 'getMCPMetadata',
                'needsServerUrl' => true
            ]
        ];
    }
}
