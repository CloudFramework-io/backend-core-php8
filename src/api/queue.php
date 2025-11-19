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
use Google\Cloud\Tasks\V2\Client\CloudTasksClient;
use Google\Cloud\Tasks\V2\CreateTaskRequest;
use Google\Cloud\Tasks\V2\HttpMethod;
use Google\Cloud\Tasks\V2\Task;


/**
 * Class API
 *
 * Represents an API class that provides functionality for handling HTTP requests, managing CORS headers,
 * and creating tasks using Google Cloud Tasks. It supports both interactive and queued task processing methods.
 */
class API extends RESTful
{
    /**
     * Handles the processing and routing of HTTP requests, either interactively or through Cloud Tasks.
     *
     * The method processes incoming requests by sending CORS headers, parsing form parameters,
     * and determining the appropriate action based on the request data. Depending on specific conditions,
     * it either handles the request interactively using the Cloud Service or queues the request
     * as a task to be executed later via Google Cloud Tasks.
     *
     * If processed interactively, it validates and modifies the URL, sends requests to the appropriate endpoints,
     * and retrieves data. In queued mode, it performs validations, appends metadata, builds a task with correct
     * HTTP settings, and schedules it with Google Cloud Tasks.
     *
     * @return bool false if any error. Set an array in the responseContains data about the processed request, including method, queued status,
     *               request URL, headers, sent and/or received data, or errors if encountered.
     */
    function main()
    {

        //region INIT $response, Cors and unset [_raw_input_] form data
        $response=[];
        $this->sendCorsHeaders();
        if(isset($this->formParams['_raw_input_'])) unset($this->formParams['_raw_input_']);
        //endregion

        //region VERIFY PROJECT_ID,LOCATION_ID,QUEUE_ID or return error if they can not be set
        if(!$project_id = $this->getFormParamater('_project_id')?:getenv('PROJECT_ID'))
            return($this->setErrorFromCodelib('system-error','missing PROJECT_ID form param or env_var'));

        if(!$location_id = $this->getFormParamater('_location_id')?:getenv('LOCATION_ID'))
            return($this->setErrorFromCodelib('system-error','missing LOCATION_ID form param or env_var'));

        if(!$queue_id = $this->getFormParamater('_queue_id')?:getenv('QUEUE_ID'))
            return($this->setErrorFromCodelib('system-error','missing QUEUE_ID form param or env_var'));
        //endregion


        //region IF isset(_interactive) CALL with no queue
        if (isset($this->formParams['_interactive'])) {

            //region SET $_url and VERIFY the call initial call includes /queue but no /queue/queue to avoid infinite loop
            // In interactive we use CloudService Class to send and receive data with http...
            //$_url = str_replace('/queue/', '/', urldecode($this->core->system->url['host_url_uri']));
            if(!strpos($this->core->system->url['host_url_uri'],'/queue/'))
                return $this->setErrorFromCodelib('params-error','call does not include /queue/. pattern');
            if(strpos($this->core->system->url['host_url_uri'],'/queue/queue'))
                return $this->setErrorFromCodelib('params-error','call does not allow /queue/queue. pattern');
            $_url = str_replace('/queue/', '/', ($this->core->system->url['host_url_uri']));
            //endregion

            //region ADD in $response variables: [url_queued,method,interative,headers,formParams]. Use $this->formParams['_extra_headers'] to add more headers to the call
            $response['url_queued'] = $_url;
            $response['method'] = $this->method;
            $response['interative'] = true;
            $response['headers'] = $this->getHeadersToResend((isset($this->formParams['_extra_headers']))?$this->formParams['_extra_headers']:null);

            // Add formParms to be send deleteing special parameters
            unset($this->formParams['_interactive']);
            unset($this->formParams['_extra_headers']);
            $response['formParams'] = $this->formParams;
            //endregion

            //region CALL to the $_url using the proper method and ADD in $response['data_received'] the response or the error
            $this->core->request->automaticHeaders = false;
            switch ($this->method) {
                case "GET":
                    $response['data_received'] = $this->core->request->get($_url, $this->formParams, $response['headers']);
                    break;
                case "POST":
                    $response['data_received'] = $this->core->request->post($_url, $this->formParams, $response['headers']);
                    break;
                case "PUT":
                    $response['data_received'] = $this->core->request->put($_url, $this->formParams, $response['headers']);
                    break;
                case "DELETE":
                    $response['data_received'] = $this->core->request->delete($_url,$response['headers']);
                    break;
            }

            // Data Received
            if ($response['data_received'] === false) $response['data_received'] = $this->core->errors->data;
            else $response['data_received'] = json_decode($response['data_received']);
            //endregion

            //region SEND $response to returnData and RETURN true
            return $this->addReturnData($response);
            //endregion
        }
        //endregion

        //region ELSE DO the call with CloudTasks

        //region ADD in formParams [cloudframework_queued],[cloudframework_queued_id],[cloudframework_queued_ip],[cloudframework_queued_fingerprint] variables
        $this->formParams['cloudframework_queued'] = true;
        $this->formParams['cloudframework_queued_id'] = uniqid('queue', true);
        $this->formParams['cloudframework_queued_ip'] = $this->core->system->ip;
        $this->formParams['cloudframework_queued_fingerprint'] = json_encode($this->core->system->getRequestFingerPrint(), JSON_PRETTY_PRINT);
        //endregion

        //region SET $_url and VERIFY the call initial call includes /queue but no /queue/queue to avoid infinite loop
        // In interactive we use CloudService Class to send and receive data with http...
        //$_url = str_replace('/queue/', '/', urldecode($this->core->system->url['host_url_uri']));
        if(strpos($this->core->system->url['url_uri'],'/queue/')===false)
            return $this->setErrorFromCodelib('params-error','call does not include /queue/. pattern');
        if(strpos($this->core->system->url['url_uri'],'/queue/queue')===0)
            return $this->setErrorFromCodelib('params-error','call does not allow /queue/queue. pattern');
        $_url = str_replace('/queue/', '/', ($this->core->system->url['url_uri']));
        //endregion

        //region ADD in $_url for GET Calls "cloudframework_queued=1&cloudframework_queued_id={$this->formParams['cloudframework_queued_id']}&cloudframework_queued_ip={$this->formParams['cloudframework_queued_ip']}"
        // Add special vars to the url if the method is not POST,PUT,PATCH
        if(!in_array($this->method,["POST","PUT","PATCH"])) {
            $_url.=(strpos($_url,'?'))?'&':'?';
            $_url.="cloudframework_queued=1&cloudframework_queued_id={$this->formParams['cloudframework_queued_id']}&cloudframework_queued_ip={$this->formParams['cloudframework_queued_ip']}";
        }
        //endregion

        //region INIT $httpRequest (as object of AppEngineHttpRequest) and SET setRelativeUri($_utl)
        $httpRequest = new AppEngineHttpRequest();
        $httpRequest->setRelativeUri($_url);
        //endregion

        //region SET $payload and $httpRequest->setHttpMethod(..) based on the method to prepare
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
        //endregion

        //region ADD to $httpRequest $this->formParams['_extra_headers'] if it exists
        $httpRequest->setHeaders($this->getHeadersToResend((isset($this->formParams['_extra_headers']))?$this->formParams['_extra_headers']:null));
        //endregion

        //region EVALUATE TO SET $this->formParams['_appengine_service'] to redirect to the right microservice
        // Route to the service when _appengine_service form params is sent.
        $_appengine_service = (isset($this->formParams['_appengine_service']))?$this->formParams['_appengine_service']:null;
        if($_appengine_service) {
            $appEngineRouting = new AppEngineRouting();
            $appEngineRouting->setService($_appengine_service);
            $httpRequest->setAppEngineRouting($appEngineRouting);
            $this->formParams['cloudframework_queued_service'] = $_appengine_service;
        }
        //endregion

        //region CREATE $task and evaluate to add $this->formParams['_at_seconds'] in the configuration
        $parent = CloudTasksClient::queueName($project_id,$location_id,$queue_id);
        $tasksClient = new CloudTasksClient();
        $task = new Task();

        $task->setAppEngineHttpRequest($httpRequest);
        if(isset($this->formParams['_at_seconds']) && is_numeric($this->formParams['_at_seconds'])){
            $timestamp = new Google\Protobuf\Timestamp();
            $timestamp->setSeconds(time() + $this->formParams['_at_seconds']);
            $timestamp->setNanos(0);
            $task->setScheduleTime($timestamp);
        }
        //endregion

        //region CALL $client->createTask($queueName, $task) and FEED $responseTask
        $request = (new CreateTaskRequest())
            ->setParent($parent)
            ->setTask($task)
            ->setResponseView(Task\View::FULL);
        $responseTask = $tasksClient->createTask($request);
        $this->core->logs->add('Task created: '.$responseTask->getName(),'task_created');
        //endregion

        //region UPDATE $response
        $response['url_queued'] = $_url;
        $response['method'] = $this->method;
        $response['interative'] = false;
        $response['PROJECT_ID'] = $project_id;
        $response['LOCATION_ID'] = $location_id;
        $response['QUEUE_ID'] = $queue_id;
        $response['service'] = $_appengine_service??'default';
        $response['data_sent'] = $this->formParams;
        //endregion

        //region SEND $response to returnData and RETURN true
        return $this->addReturnData($response);
        //endregion

        //endregion

    }
}
