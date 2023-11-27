<?php
/**
 * QUEUE calls end-points
 * This feature allows in a very easy way to queue calls to end-points adding /queue at the beginning of the url
 * It uses GCP Task Manager as engine to queue your calls.
 *
 * More info:
 *  - https://cloud.google.com/tasks/docs/creating-appengine-tasks
 *  - https://github.com/GoogleCloudPlatform/php-docs-samples/blob/master/appengine/php72/tasks/snippets/src/create_task.php
 *  - https://console.developers.google.com/apis/api/cloudtasks.googleapis.com/overview?project={{PROJECT-ID}}
 *
 * Headers sent by a task:
 *  - X-CloudTasks-QueueName	The name of the queue.
 *  - X-CloudTasks-TaskName	The "short" name of the task, or, if no name was specified at creation, a unique system-generated id. This is the my-task-id value in the complete task name, ie, task_name = projects/my-project-id/locations/my-location/queues/my-queue-id/tasks/my-task-id.
 *  - X-CloudTasks-TaskRetryCount	The number of times this task has been retried. For the first attempt, this value is 0. This number includes attempts where the task failed due to 5XX error codes and never reached the execution phase.
 *  - X-CloudTasks-TaskExecutionCount	The total number of times that the task has received a response from the handler. Since Cloud Tasks deletes the task once a successful response has been received, all previous handler responses were failures. This number does not include failures due to 5XX error codes.
 *  - X-CloudTasks-TaskETA	The schedule time of the task, specified in seconds since January 1st 1970.
 *
 * Last-update: 2021-01
 * @type {RESTFul}
 */
// Task to use in background
use Google\Cloud\Tasks\V2\AppEngineHttpRequest;
use Google\Cloud\Tasks\V2\AppEngineRouting;
use Google\Cloud\Tasks\V2\CloudTasksClient;
use Google\Cloud\Tasks\V2\HttpMethod;
use Google\Cloud\Tasks\V2\Task;


class API extends RESTful
{
    function main()
    {

        // Allow ajax calls
        $this->sendCorsHeaders();
        if(isset($this->formParams['_raw_input_'])) unset($this->formParams['_raw_input_']);


        // CALL URL and wait until the response is received
        if (isset($this->formParams['_interactive'])) {

            //Delete variable
            unset($this->formParams['interactive']);

            // In interactive we use CloudService Class to send and receive data with http...
            //$_url = str_replace('/queue/', '/', urldecode($this->core->system->url['host_url_uri']));
            if(!strpos($this->core->system->url['host_url_uri'],'/queue/'))
                return $this->setErrorFromCodelib('params-error','call does not include /queue/. pattern');
            if(strpos($this->core->system->url['host_url_uri'],'/queue/queue'))
                return $this->setErrorFromCodelib('params-error','call does not allow /queue/queue. pattern');
            $_url = str_replace('/queue/', '/', ($this->core->system->url['host_url_uri']));


            // Requires to create a complete URL
            $value['url_queued'] = $_url;
            $value['method'] = $this->method;
            $value['interative'] = true;
            $value['headers'] = $this->getHeadersToResend((isset($this->formParams['_extra_headers']))?$this->formParams['_extra_headers']:null);

            // Add formParms to be send deleteing special parameters
            unset($this->formParams['_interactive']);
            unset($this->formParams['_extra_headers']);
            $value['formParams'] = $this->formParams;


            // Avoid to send automatica Headers.
            $this->core->request->automaticHeaders = false;
            switch ($this->method) {
                case "GET":
                    $value['data_received'] = $this->core->request->get($_url, $this->formParams, $value['headers']);
                    break;
                case "POST":
                    $value['data_received'] = $this->core->request->post($_url, $this->formParams, $value['headers']);
                    break;
                case "PUT":
                    $value['data_received'] = $this->core->request->put($_url, $this->formParams, $value['headers']);
                    break;
                case "DELETE":
                    $value['data_received'] = $this->core->request->delete($_url,$value['headers']);
                    break;
            }

            // Data Received
            if ($value['data_received'] === false) $value['data_received'] = $this->core->errors->data;
            else $value['data_received'] = json_decode($value['data_received']);

        } // RUN THE TASK
        else {

            //region VERIFY PROJECT_ID,LOCATION_ID,QUEUE_ID or return error if they can not be set
            if(!getenv('PROJECT_ID')) return($this->setErrorFromCodelib('system-error','missing PROJECT_ID env_var'));

            // use: gcloud tasks locations list to get valid locations
            if(!getenv('LOCATION_ID')) return($this->setErrorFromCodelib('system-error','missing LOCATION_ID env_var'));

            // default if empty
            if(!getenv('QUEUE_ID')) return($this->setErrorFromCodelib('system-error','missing QUEUE_ID env_var'));
            //endregion

            //region ADD in formParams cloudframework_queued variables to be include in the task call
            $this->formParams['cloudframework_queued'] = true;
            $this->formParams['cloudframework_queued_id'] = uniqid('queue', true);
            $this->formParams['cloudframework_queued_ip'] = $this->core->system->ip;
            $this->formParams['cloudframework_queued_fingerprint'] = json_encode($this->core->system->getRequestFingerPrint(), JSON_PRETTY_PRINT);
            //endregion

            // In interactive we use CloudService Class to send and receive data with http...
            //$_url = str_replace('/queue/', '/', urldecode($this->core->system->url['host_url_uri']));
            if(strpos($this->core->system->url['url_uri'],'/queue/')===false)
                return $this->setErrorFromCodelib('params-error','call does not include /queue/. pattern');
            if(strpos($this->core->system->url['url_uri'],'/queue/queue')===0)
                return $this->setErrorFromCodelib('params-error','call does not allow /queue/queue. pattern');
            $_url = str_replace('/queue/', '/', ($this->core->system->url['url_uri']));


            //region CREATE $client, $queueName and $task to be queued
            $client = new CloudTasksClient();
            $queueName = $client->queueName(getenv('PROJECT_ID'),getenv('LOCATION_ID'),getenv('QUEUE_ID'));

            // Create an App Engine Http Request Object.
            $httpRequest = new AppEngineHttpRequest();


            // Add special vars to the url if the method is not POST,PUT,PATCH
            if(!in_array($this->method,["POST","PUT","PATCH"])) {
                $_url.=(strpos($_url,'?'))?'&':'?';
                $_url.="cloudframework_queued=1&cloudframework_queued_id={$this->formParams['cloudframework_queued_id']}&cloudframework_queued_ip={$this->formParams['cloudframework_queued_ip']}";
            }

            // The path of the HTTP request to the App Engine service.
            $httpRequest->setRelativeUri($_url);


            $payload = json_encode($this->formParams);

            // POST is the default HTTP method, but any HTTP method can be used.
            switch ($this->method) {
                case "GET":
                    $httpRequest->setHttpMethod(HttpMethod::GET);
                    break;
                case "POST":
                    $httpRequest->setHttpMethod(HttpMethod::POST);
                    // Setting a body value is only compatible with HTTP POST and PUT requests.
                    if (isset($payload)) {
                        $httpRequest->setBody($payload);
                    }
                    break;
                case "PUT":
                    $httpRequest->setHttpMethod(HttpMethod::PUT);
                    // Setting a body value is only compatible with HTTP POST and PUT requests.
                    if (isset($payload)) {
                        $httpRequest->setBody($payload);
                    }
                    break;
                case "PATCH":
                    $httpRequest->setHttpMethod(HttpMethod::PATCH);
                    // Setting a body value is only compatible with HTTP POST and PUT requests.
                    if (isset($payload)) {
                        $httpRequest->setBody($payload);
                    }
                    break;
                case "DELETE":
                    $httpRequest->setHttpMethod(HttpMethod::DELETE);
                    break;
                case "OPTIONS":
                    $httpRequest->setHttpMethod(HttpMethod::OPTIONS);
                    break;
            }

            $httpRequest->setHeaders($this->getHeadersToResend((isset($this->formParams['_extra_headers']))?$this->formParams['_extra_headers']:null));

            // Route to the service when _appengine_service form params is sent.
            $_appengine_service = (isset($this->formParams['_appengine_service']))?$this->formParams['_appengine_service']:null;
            if($_appengine_service) {
                $appEngineRouting = new AppEngineRouting();
                $appEngineRouting->setService($_appengine_service);
                $httpRequest->setAppEngineRouting($appEngineRouting);
                $this->formParams['cloudframework_queued_service'] = $_appengine_service;
            }

            // Create a Cloud Task object.
            $task = new Task();
            $task->setAppEngineHttpRequest($httpRequest);
            if(isset($this->formParams['_at_seconds']) && is_numeric($this->formParams['_at_seconds'])){
                $timestamp = new Google\Protobuf\Timestamp();
                $timestamp->setSeconds(time() + $this->formParams['_at_seconds']);
                $timestamp->setNanos(0);
                $task->setScheduleTime($timestamp);
            }
            //endregion

            //region CALL $client->createTask($queueName, $task)
            $response = $client->createTask($queueName, $task);
            $this->core->logs->add('Task created: '.$response->getName(),'task_created');
            //endregion

            //region SET $value to be returned
            $value['url_queued'] = $_url;
            $value['method'] = $this->method;
            $value['interative'] = false;
            $value['data_sent'] = $this->formParams;
            //endregion

        }

        $this->addReturnData($value);
    }
}
