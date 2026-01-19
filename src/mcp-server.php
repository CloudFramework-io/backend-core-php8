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
use Symfony\Component\Uid\Uuid;

//region INIT $core = new Core7();
include_once(__DIR__ . "/Core7.php"); //
include_once(__DIR__ . "/class/RESTful.php"); //
include_once(__DIR__ . "/mcp/MCPCore7.php"); //
include_once(__DIR__ . "/mcp/Auth.php"); // Cargar explícitamente para el discovery
$core = new Core7();
//endregion

// Configure session settings before any session starts (must be before headers sent)
if (!headers_sent()) {
    ini_set('session.use_cookies', '0');
    ini_set('session.cache_limiter', '');
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

//endregion

// Session store using PHP native sessions
$sessionStore = new PhpSessionStore(86400);

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