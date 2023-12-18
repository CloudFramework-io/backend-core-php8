# PHP8.X BACKEND FRAMEWORK.
CLOUDRAMEWORK PHP8 CORE FRAMEWORK TO DEVELOP BACKEND APIs and BACKEND SCRIPTs optimized for GOOGLE APPENGINE, COMPUTE ENGINE, CLOUD FUNCTIONS, KUBERNETS and other server technologies.

It Assumes you have installed in your server ^PHP8.1 and ^Python3.X

## Github project and Package can be found 
* [https://github.com/CloudFramework-io/backend-core-php8](https://github.com/CloudFramework-io/backend-core-php8/tree/main)
* https://packagist.org/packages/cloudframework-io/backend-core-php8

## First time Installation and running a local server with Hello World APIs
First step is to install the basic library
```shell
composer require cloudframework-io/backend-core-php8
# if you have problem with GRPC extensions you can use:
# composer require cloudframework-io/backend-core-php8 --ignore-platform-req=ext-grpc
```

Now you need to create your basic file structure
```shell
php vendor/cloudframework-io/backend-core-php8/install.php
# It will create the following structure:
 - mkdir ./local_data/cache
 - Copying /api examples
 - Copying /scripts examples
 - Copying composer.json
 - Copying .gitignore
 - Rewriting composer.json
 - Copying app.yaml for GCP appengine
 - Copying .gcloudignore
 - Copying README.md
 - Copying php.ini
```

NOW YOU CAN LAUNCH your LOCAL SERVER with your FIRST API Hello world
```shell
composer server
# And you can add a browser and navigate to:
# http://localhost:8080/
# http://localhost:8080/training/hello
# http://localhost:8080/training 
```
The response of http://localhost:8080/ will be a JSON
```json
{
    "success": true,
    "status": 200,
    "code": "ok",
    "time_zone": "UTC",
    "data": {
        "end-point /index [current]": "This end-point defined in <document-root>/api/index.php",
        "end-point /training/hello": "Advanced API Structure of Hello World in  <document-root>/api/training/hello-advanced.php",
        "Current Url Parameters: $this->params": [],
        "Current formParameters: $this->formParams": []
    },
    "logs": [
    "[syslog:info] CoreCache: init(). type: directory",
    "[syslog:info] RESTful: Url: [GET] http://localhost:8080/"
    ]
}
```
Explore the files <document-root>/api/training/hello.php and hello-advanced.php to see a basic structure of an API.

## Configure your project to be deployed in GCP (Google Appengine) and/or to interact with CloudFramework CLOUD-PLATFORM
Before to setup your local development environment be sure you have installed [GCP SDK](https://cloud.google.com/sdk/docs/install-sdk) in your computer.

Run the following composer command to configure your development environment to 
```shell
composer setup
```
### MAC Development Environment
If you are a mac user with zsh shell, the setup will ask you about to configure .zshrc with some variables, aliases and function to facilitate your development life.
```
> php vendor/cloudframework-io/backend-core-php8/runscript.php _setup
CloudFramWork (CFW) Core7.Script 8.1.x
 - Looking for scripts of the framework because the name start with [_]
 - root_path: /Users/adrianmartinez/Develoment/GCP/api.cloudframework.io/appengine/core24
 - app_path: ./vendor/cloudframework-io/backend-core-php8/scripts
 - script: ./vendor/cloudframework-io/backend-core-php8/scripts/_setup.php
 - Initial Logs:
   #[syslog:info] CoreSession: init(). id [CloudFrameworkScripts]
   #[syslog:info] CoreCache: init(). type: directory

##########################

SHELL configuration
 - current shell: [/bin/zsh]
   # Do you want auto configuration of [/Users/adrianmartinez/.zshrc] [yes, no] (yes) :
```
If your answer is [yes] in will add some aliases and functions:
```
# Analyzing /Users/adrianmartinez/.zshrc
     . Adding header [# BEGIN CloudFramework ALIASES AND FUNCTIONS]
     . Found tag [# END CloudFramework ALIASES AND FUNCTIONS]
   # Adding alias [# Cloudframework ALIASES]
   # Adding alias [alias cfserve='composer run-script serve']
   # Adding alias [alias cfdeploy='composer run-script deploy']
   # Adding alias [alias cfcredentials='composer run-script install-development-credentials']
   # Adding alias [alias cfscript='composer run-script script']
   # Adding alias [alias cffront="python cf_http_dev.py 5000 'Pragma: no-cache' 'Cache-Control: no-cache' 'Expires: 0'  'Access-Control-Allow-Origin: *'"]
   # Adding alias [alias cfgen_password='openssl rand -base64 21']
   # Adding alias [alias cfreload_source='source ~/.zshrc']
   # Adding alias [# Cloudframework Functions]
   # Adding function [function gcp () {]
   # Adding function [function gsecret () {]
   # File updated. Execute [source ~/.zshrc]
```

### CLOUD-PLATFORM configuration
If you are a CloudFramwork Customer you can configure your development environment connected with your Platform. Don't worry, this option is totally optional and it is nor required.
```shell
CORE ./config.json
 - GCP configuration config vars
   # core.erp.platform_id: CF/ERP Platform Id (empty if you do not have any access) :
```

### Google GCP Configuration
If you are interested in deploying your APIs under APPENGINE then you need to provide GCP configuration
```shell
   # core.gcp.project_id: DEFAULT PROJECT_ID for GCP (<default-project>>) :
   # core.gcp.datastore.on: Activate Datastore access [true, false] (false) :
   # core.gcp.datastore.project_id: Default project_id for datastore. Empty if it is the same than core.gcp.project_id :
   # core.gcp.datastorage.on: Activate Storage access [true, false] (false) :
   # core.gcp.datastorage.project_id: Default project_id for datastorage. Empty if it is the same than core.gcp.project_id :
   # core.gcp.bigquery.on: Activate Bigquery access [true, false] (false) :
   # core.gcp.bigquery.project_id: Default project_id for bigquery. Empty if it is the same than core.gcp.project_id :
   # core.cache.cache_path: Development Cache Path (<document-root>/local_data/cache) :
- Updated ./config.json
##########################
Script: OK
```