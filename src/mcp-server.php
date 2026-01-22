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

// Load default WellKnown class from framework
include_once(__DIR__ . "/mcp/WellKnown.php");

// Check for custom WellKnown class in project (overrides default)
$wellKnownClass = 'WellKnown';  // Default framework class
$wellKnownFile = $root_path . '/mcp/WellKnown.php';
if (file_exists($wellKnownFile)) {
    require_once $wellKnownFile;
    if (class_exists('App\\Mcp\\WellKnown')) {
        $wellKnownClass = 'App\\Mcp\\WellKnown';
    }
}

// Calculate server URL once for endpoints that need it
$serverUrl = $request->getUri()->getScheme() . '://' . $request->getUri()->getHost();
if ($request->getUri()->getPort() && !in_array($request->getUri()->getPort(), [80, 443])) {
    $serverUrl .= ':' . $request->getUri()->getPort();
}

// Get supported endpoints from WellKnown class (custom or default)
$wellKnownEndpoints = method_exists($wellKnownClass, 'getSupportedEndpoints')
    ? $wellKnownClass::getSupportedEndpoints()
    : WellKnown::getSupportedEndpoints();

// Handle .well-known endpoints
if (isset($wellKnownEndpoints[$requestPath])) {
    $endpoint = $wellKnownEndpoints[$requestPath];
    $method = $endpoint['method'];
    $args = ($endpoint['needsServerUrl'] ?? false) ? [$serverUrl] : [];

    // Check if custom class has the method, otherwise use default
    if (method_exists($wellKnownClass, $method)) {
        $metadata = call_user_func_array([$wellKnownClass, $method], $args);
    } else {
        $metadata = call_user_func_array(['WellKnown', $method], $args);
    }

    $response = $psr17Factory->createResponse(200)
        ->withHeader('Content-Type', 'application/json')
        ->withHeader('Access-Control-Allow-Origin', '*')
        ->withHeader('Cache-Control', 'public, max-age=3600')
        ->withBody($psr17Factory->createStream(json_encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)));
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