# Development
Following [CloudFramework Instructions](https://www.notion.so/cloudframework/appengine-php-core20-74c573448dc94ebba7e51fc86b8ad9cb) to start programming in localhost execute:
```shell
# lines you have executed
composer require cloudframework-io/backend-core-php8
```
# Install for replit
You can see examples to use the framework in: https://replit.com/@cloudframework/php
```shell
php vendor/cloudframework-io/backend-core-php8/install.php replit
```

Now you can run the replit instance

# Install for appengine

```shell
php vendor/cloudframework-io/backend-core-php8/install.php appengine

# create temporal local data directory: ./local_data/cache
composer clean

# To develop locally with CloudFramework ERP and to interact With Datastore, Datastorage, etc.. products you need to apply your 
# GCP credentials. Todo do that:
# It is necessary that your user have right credentials to access the different solutions
# This step is optional
composer credentials

# To work with APIs. Try now http://localhost:8080/training/hello
# you will find a file api/training/hello.php
composer serve

# To work with Scripts. Try now: composer script hello
# composer script {name-of-script-under-the-directory-scripts-wihout.php}
# you will find a file scripts/training/hello.php 
composer script training/hello
```

## Deploy your project in GCP Appengine Standard
### Assumptions
We understand you know what is GCP Appengine standard and you have the [I AM] provileges
to create a project and/or to deploy into a project.

### Steps
* [Create a GCP project](https://console.cloud.google.com/projectcreate) and let's say your project id will be `{my-project}`
* Execute:
```
gcloud app deploy app.yaml --project={my-project}
# First time it will ask for appengine location. We suggest to use euro-west
# it wil show: Deployed service [default] to [https://{my-project}.ew.r.appspot.com]
```
* Now you can browse: https://{my-project}.ew.r.appspot.com/training/hello
```
{
  "success": true,
  "status": 200,
  "code": "ok",
  "data": "hello World"
}
```