<?php
$_root_path = (strlen($_SERVER['DOCUMENT_ROOT'])) ? $_SERVER['DOCUMENT_ROOT'] : $_SERVER['PWD'];

echo "---------\n";

$replit = ($argv[1]??'') == 'replit';
$appengine = ($argv[1]??'') == 'appengine';

if($replit)
    echo "Installing CloudFramework PHP 8.1 for replit\n";
else
    echo "Installing CloudFramework for PHP 8.1 for GCP Appengine\n";
echo "---------\n";

echo " - mkdir ./local_data/cache\n";
if(!is_dir("./local_data")) mkdir($_root_path.'/local_data');
if(!is_dir("./local_data/cache")) mkdir($_root_path.'/local_data/cache');
if(!is_dir("./local_data/cache")) die('ERROR trying to create [./local_data/cache]. Verify privileges');

echo " - Copying /api examples\n";
if(!is_dir("./api")) mkdir('api');
shell_exec("cp -Ra vendor/cloudframework-io/backend-core-php8/install/api-dist/* api");

echo " - Copying /scripts examples\n";
if(!is_dir("./scripts")) mkdir('scripts');
shell_exec("cp -Ra vendor/cloudframework-io/backend-core-php8/install/scripts-dist/* scripts");

if(!is_file('./config.json')) {
    echo " - Copying composer.json\n";
    copy("vendor/cloudframework-io/backend-core-php8/install/config-dist.json", "./config.json");
} else echo " - Already exist config.json\n";

if(!is_file('./.gitignore')) {
    echo " - Copying .gitignore\n";
    copy("vendor/cloudframework-io/backend-core-php8/.gitignore", "./.gitignore");
} else echo " - Already exist .gitignore\n";


if(!$replit) {
    echo " - Rewriting composer.json\n";
    copy("vendor/cloudframework-io/backend-core-php8/install/composer-dist.json", "./composer.json");

    if(!is_file('./app.yaml')) {
        echo " - Copying app.yaml for GCP appengine\n";
        copy("vendor/cloudframework-io/backend-core-php8/install/app-dist.yaml", "./app.yaml");
    } else echo " - Already exist app.yaml\n";

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

} else {
    echo " - Creating index.php for replit\n";
    shell_exec('echo "<?php include \"vendor/cloudframework-io/backend-core-php8/src/dispatcher.php\";" > index.php');

}