<?php
class API extends RESTful
{
    function main() {
        // add in php.ini extension=grpc.so
        $this->returnData['grpc_extension_loaded'] = extension_loaded('grpc');  // Allow GRPC connections instead API

    }
}
