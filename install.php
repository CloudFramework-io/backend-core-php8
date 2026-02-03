<?php
/**
 *  Create a file/directory structure to start a backend-core-php8 project allowing to run a local server.
 *  Execute from <document-root>:
 *  * `php vendor/cloudframework-io/backend-core-php8/install.php appengine` for GCP App Engine
 *  * `php vendor/cloudframework-io/backend-core-php8/install.php replit` for REPLIT.COM PHP Web server
 *  * `php vendor/cloudframework-io/backend-core-php8/install.php mcp` to see MCP setup instructions
 */

$_root_path = (strlen($_SERVER['DOCUMENT_ROOT'])) ? $_SERVER['DOCUMENT_ROOT'] : $_SERVER['PWD'];

echo "---------\n";

$replit = ($argv[1]??'') == 'replit';
$appengine = ($argv[1]??'') == 'appengine';
$cloudia = ($argv[1]??'') == 'cloudia';
$mcp = ($argv[1]??'') == 'mcp';

if($replit)
    echo "Installing CloudFramework PHP 8.4 for replit\n";
elseif($appengine)
    echo "Installing CloudFramework for PHP 8.4 for GCP Appengine\n";
elseif($cloudia)
    echo "Installing CloudIA for PHP 8.4\n";
elseif($mcp)
    echo "MCP (Model Context Protocol) Setup Instructions\n";
else{
    echo "Missing parameter. Use [php vendor/cloudframework-io/backend-core-php8/install.php appengine|replit|mcp|cloudia]\n";
}
echo "---------\n";

// MCP only shows instructions, skip file operations
if($mcp) {
    // MCP instructions are shown at the end of the file
} elseif($replit || $appengine || $cloudia) {
    echo " - mkdir ./local_data/cache\n";
    if(!is_dir("./local_data")) mkdir($_root_path.'/local_data');
    if(!is_dir("./local_data/cache")) mkdir($_root_path.'/local_data/cache');
    if(!is_dir("./local_data/cache")) die('ERROR trying to create [./local_data/cache]. Verify privileges');
}

if($cloudia) {
    echo " - Creating buckets to backup contents /buckets/backups\n";
    if (!is_dir("./buckets")) mkdir('buckets');
    if (!is_dir("./buckets/backups")) mkdir('buckets/backups');
    echo " - Rewriting composer.json\n";
    copy("vendor/cloudframework-io/backend-core-php8/install/composer-cloudia-dist.json", "./composer.json");

    if (!is_file('./config.json')) {
        echo " - Copying config.json\n";
        copy("vendor/cloudframework-io/backend-core-php8/install/config-cloudia-dist.json", "./config.json");
    } else echo " - Already exist config.json\n";

    echo " - Execute: composer credentials\n";
    echo "   It will authenticate your GCP user and it will store your credentials in local_data/application_default_credentials.json\n\n";
    echo " - Execute: composer script _cloudia/auth\n";
    echo "   It will require an [Integration Key] you have to receive from your platform admin or go to https://{your-platform}.cloudframework.app/app.html#__cfo/CloudFrameWorkAPIKeys\n\n";

    echo " - Execute: composer script _cloudia/processes/backup-from-remote\n";
    echo "   It will download your knowledge database\n\n";
}

if($replit || $appengine) {
    echo " - Copying /api examples\n";
    if (!is_dir("./api")) mkdir('api');
    shell_exec("cp -Ra vendor/cloudframework-io/backend-core-php8/install/api-dist/* api");

    echo " - Copying /scripts examples\n";
    if (!is_dir("./scripts")) mkdir('scripts');
    shell_exec("cp -Ra vendor/cloudframework-io/backend-core-php8/install/scripts-dist/* scripts");

    if (!is_file('./config.json')) {
        echo " - Copying config.json\n";
        copy("vendor/cloudframework-io/backend-core-php8/install/config-dist.json", "./config.json");
    } else echo " - Already exist config.json\n";

    if (!is_file('./.gitignore')) {
        echo " - Copying .gitignore\n";
        copy("vendor/cloudframework-io/backend-core-php8/.gitignore", "./.gitignore");
    } else echo " - Already exist .gitignore\n";
}

if($appengine) {
    echo " - Rewriting composer.json\n";
    copy("vendor/cloudframework-io/backend-core-php8/install/composer-dist.json", "./composer.json");

    if(!is_file('./app.yaml')) {
        echo " - Copying app.yaml for GCP appengine\n";
        copy("vendor/cloudframework-io/backend-core-php8/install/app-dist.yaml", "./app.yaml");
    } else echo " - Already exist app.yaml\n";

    if(!is_file('./app-dev.yaml')) {
        echo " - Copying app-dev.yaml for GCP appengine (development service)\n";
        copy("vendor/cloudframework-io/backend-core-php8/install/app-dev.yaml", "./app-dev.yaml");
    } else echo " - Already exist app-dev.yaml\n";

    if(!is_file('./.gcloudignore')) {
        echo " - Copying .gcloudignore\n";
        copy("vendor/cloudframework-io/backend-core-php8/install/.gcloudignore", "./.gcloudignore");
    } else echo " - Already exist .gcloudignore\n";

    if(!is_file('./README.md')) {
        echo " - Copying README.md\n";
        copy("vendor/cloudframework-io/backend-core-php8/install/README-dist.md", "./README.md");
    } else echo " - Already exist README.md\n";

    if(!is_file('./php.ini')) {
        echo " - Copying php.ini\n";
        copy("vendor/cloudframework-io/backend-core-php8/install/php-dist.ini", "./php.ini");
    } else echo " - Already exist php.ini\n";

} elseif($replit) {
    echo " - Creating index.php for replit\n";
    shell_exec("echo \"<?php\nif(getenv('CF_GOOGLE_APPLICATION_CREDENTIALS')) {\n  putenv('GOOGLE_APPLICATION_CREDENTIALS=/tmp/credentials.json');\n  if(!is_file('/tmp/credentials.json'))\n    file_put_contents('/tmp/credentials.json',getenv('CF_GOOGLE_APPLICATION_CREDENTIALS'));\n}\ninclude 'vendor/cloudframework-io/backend-core-php8/src/dispatcher.php';\" > i.php");
}

// MCP Setup Instructions
if($mcp) {
    // Check if local composer.json only has cloudframework-io/backend-core-php8 dependency
    $autoInstall = false;
    $composerPath = $_root_path . '/composer.json';

    if(is_file($composerPath)) {
        $composerContent = json_decode(file_get_contents($composerPath), true);
        if($composerContent && isset($composerContent['require'])) {
            $requires = $composerContent['require'];
            // Remove php version requirement if present
            unset($requires['php']);
            // Check if only cloudframework-io/backend-core-php8 is present
            if(count($requires) === 1 && isset($requires['cloudframework-io/backend-core-php8'])) {
                $autoInstall = true;
            }
        }
    }

    if($autoInstall) {
        echo "\n";
        echo "=== AUTO-INSTALLING MCP ENVIRONMENT ===\n";
        echo "Detected composer.json with only cloudframework-io/backend-core-php8 dependency.\n";
        echo "Proceeding with automatic MCP setup...\n\n";

        // Step 1: Create local_data/cache directory
        echo " - Creating ./local_data/cache\n";
        if(!is_dir($_root_path.'/local_data')) mkdir($_root_path.'/local_data');
        if(!is_dir($_root_path.'/local_data/cache')) mkdir($_root_path.'/local_data/cache');
        if(!is_dir($_root_path.'/local_data/cache')) die('ERROR trying to create [./local_data/cache]. Verify privileges');

        // Step 2: Create mcp directory
        echo " - Creating ./mcp directory for your tools\n";
        if(!is_dir($_root_path.'/mcp')) mkdir($_root_path.'/mcp');

        // Step 3: Copy composer-mcp-dist.json
        echo " - Copying composer-mcp-dist.json to composer.json\n";
        copy(__DIR__.'/install/composer-mcp-dist.json', $composerPath);

        // Step 4: Copy config.json if not exists
        if(!is_file($_root_path.'/config.json')) {
            echo " - Copying config-dist.json to config.json\n";
            copy(__DIR__.'/install/config-dist.json', $_root_path.'/config.json');
        } else {
            echo " - Already exists config.json\n";
        }

        // Step 5: Copy .gitignore if not exists
        if(!is_file($_root_path.'/.gitignore')) {
            echo " - Copying .gitignore\n";
            copy(__DIR__.'/.gitignore', $_root_path.'/.gitignore');
        } else {
            echo " - Already exists .gitignore\n";
        }

        // Step 6: Copy .gcloudignore if not exists
        if(!is_file($_root_path.'/.gcloudignore')) {
            echo " - Copying .gcloudignore\n";
            copy(__DIR__.'/install/.gcloudignore', $_root_path.'/.gcloudignore');
        } else {
            echo " - Already exists .gcloudignore\n";
        }

        // Step 7: Copy app-dev.yaml if not exists
        if(!is_file($_root_path.'/app-dev.yaml')) {
            echo " - Copying app-dev.yaml for GCP App Engine deployment\n";
            copy(__DIR__.'/install/app-dev.yaml', $_root_path.'/app-dev.yaml');
        } else {
            echo " - Already exists app-dev.yaml\n";
        }

        echo "\n=== MCP SETUP COMPLETE ===\n\n";
        echo "Next steps:\n";
        echo "   1. Run: composer update\n";
        echo "   2. Run: composer serve (Server at http://localhost:8000)\n";
        echo "   3. Optional: composer inspector-local (MCP Inspector)\n";
        echo "   4. Edit app-dev.yaml to configure your GCP project settings before deploying\n";
        echo "---------\n";

    } else {
        // Show instructions only
        echo "\n";
        echo "=== FILES TO COPY FOR MCP ===\n\n";

        echo "1. COMPOSER FILE (use instead of standard composer.json):\n";
        echo "   FROM: vendor/cloudframework-io/backend-core-php8/install/composer-mcp-dist.json\n";
        echo "   TO:   ./composer.json\n\n";

        echo "2. FRAMEWORK MCP FILES (included automatically via composer autoload):\n";
        echo "   - vendor/cloudframework-io/backend-core-php8/src/mcp-server.php (MCP server entry point)\n";
        echo "   - vendor/cloudframework-io/backend-core-php8/src/mcp/MCPCore7.php (MCP Core class)\n";
        echo "   - vendor/cloudframework-io/backend-core-php8/src/mcp/Auth.php (MCP Authentication)\n\n";

        echo "3. YOUR MCP TOOLS DIRECTORY (create this):\n";
        echo "   CREATE: ./mcp/ (your custom MCP tools go here)\n\n";

        echo "=== SETUP STEPS ===\n\n";

        echo "Step 1: Copy composer-mcp-dist.json as your composer.json\n";
        echo "   cp vendor/cloudframework-io/backend-core-php8/install/composer-mcp-dist.json ./composer.json\n\n";

        echo "Step 2: Create mcp directory for your tools\n";
        echo "   mkdir -p ./mcp\n\n";

        echo "Step 3: Create config.json if not exists\n";
        echo "   cp vendor/cloudframework-io/backend-core-php8/install/config-dist.json ./config.json\n\n";

        echo "Step 4: Install dependencies\n";
        echo "   composer install\n\n";

        echo "Step 5: Run MCP server locally\n";
        echo "   composer serve\n";
        echo "   (Server runs at http://localhost:8000)\n\n";

        echo "Step 6: Test with MCP Inspector (optional)\n";
        echo "   composer inspector-local\n\n";

        echo "=== REQUIRED DEPENDENCIES ===\n";
        echo "   - mcp/sdk: ^0.3.0\n";
        echo "   - nyholm/psr7: ^1.8\n";
        echo "   - nyholm/psr7-server: ^1.1\n";
        echo "   - laminas/laminas-httphandlerrunner: ^2.10\n";
        echo "   - cloudframework-io/backend-core-php8: ^8.4.27\n\n";

        echo "=== PSR-4 AUTOLOAD NAMESPACES ===\n";
        echo "   - App\\\\         -> . (your project root)\n";
        echo "   - App\\\\Mcp\\\\     -> mcp/ (your custom MCP tools)\n";
        echo "   - App\\\\CFMcp\\\\   -> vendor/cloudframework-io/backend-core-php8/src/mcp/\n\n";

        echo "=== DOCUMENTATION ===\n";
        echo "   See: https://cloudframework.io/docs/es/developers/php-framework/backend-core-php8\n";
        echo "---------\n";
    }
}