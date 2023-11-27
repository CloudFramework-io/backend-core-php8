<?php

/**
 * [$cfGoogle = $this->core->loadClass('CFGoogle');] Class to facilitate Google APIs integration like vision
 * @package LabClasses
 */
class CFGoogle
{
    var $core;
    var $error = false;
    var $errorMsg = [];
    var $client;
    var $scope;
    var $type = 'installed';

    function __construct(Core7 &$core,$type='installed')
    {
        if($type) $this->type = $type;

        $this->core = $core;
        if(!is_dir($this->core->system->root_path.'/vendor/google/apiclient')) {
            $this->addError('Missing Google Client libreries. Execute from your document root: composer require google/apiclient:^2.0');
        } else {
            $this->client = new Google_Client();
            $this->client->setApplicationName('GoogleCloudFrameWork');

            // Read id and secret based on installed credentials
            $this->client_secret = $this->core->config->get('Google_Client');
            if(!is_array($this->client_secret))
                $this->addError('Missing Google_Client config var with the credentials from Google. Get JSON OAUTH 2.0 credentials file from: https://console.developers.google.com/apis/credentials');
            else {
                if(!isset($this->client_secret[$this->type])) {
                    if($this->type=='developer') $this->type.=' config var for API';
                    else $this->type.=' config array for Oauth 2.0 client ID';
                    $this->addError("Missing Google_Client:{$this->type} Key. Go to https://console.cloud.google.com/apis/credentials and specify the right credentials");
                } else {
                    switch ($this->type) {
                        case "web":
                            $this->client->setAuthConfig(['web'=>$this->client_secret['web']]);
                            break;
                        case "installed":
                            $this->client->setAuthConfig(['installed'=>$this->client_secret['installed']]);
                            break;
                        case "developer":
                            $this->client->setDeveloperKey($this->client_secret['developer']);
                            break;
                        default:
                            die('Wrong Google $type credentials');
                            break;

                    }
                }
            }
        }
    }

    function addError($value)
    {
        $this->error = true;
        $this->errorMsg[] = $value;
    }

    function verifyToken($id_token, $uid=null) {
        $ret =$this->core->request->get_json_decode('https://www.googleapis.com/oauth2/v3/tokeninfo',['id_token'=>$id_token]);
        if(isset($ret['error'])) return($this->addError($ret));

        if($uid && $uid != $ret['sub']) return($this->addError('uid does not match with login_provider_indetifier'));

        if($this->client->getClientId() != $ret['aud']) {
            $this->core->logs->add('This token has not been generated with internal system client_id');
        }
        return $ret;
    }
}

class GoogleVision extends CFGoogle
{
    function analyze($gcsurl,$options=[]) {

        if($this->error) return;

        $optParams = [];
        $this->client->setScopes([Google_Service_Vision::CLOUD_PLATFORM]);

        $service = new Google_Service_Vision($this->client);
        $body = new Google_Service_Vision_BatchAnnotateImagesRequest();

        $features = [];

        if(!$options || in_array('FACE_DETECTION',$options)) {
            $feature = new Google_Service_Vision_Feature();
            $feature->setType('FACE_DETECTION');
            $feature->setMaxResults(100);
            $features[] = $feature;
        }

        if(!$options || in_array('LANDMARK_DETECTION',$options)) {
            $feature = new Google_Service_Vision_Feature();
            $feature->setType('LANDMARK_DETECTION');
            $feature->setMaxResults(100);
            $features[] = $feature;
        }

        if(!$options || in_array('LOGO_DETECTION',$options)) {
            $feature = new Google_Service_Vision_Feature();
            $feature->setType('LOGO_DETECTION');
            $feature->setMaxResults(100);
            $features[] = $feature;
        }

        if(!$options || in_array('LABEL_DETECTION',$options)) {
            $feature = new Google_Service_Vision_Feature();
            $feature->setType('LABEL_DETECTION');
            $feature->setMaxResults(100);
            $features[] = $feature;
        }

        if(!$options || in_array('TEXT_DETECTION',$options)) {
            $feature = new Google_Service_Vision_Feature();
            $feature->setType('TEXT_DETECTION');
            $feature->setMaxResults(200);
            $features[] = $feature;
        }

        if(!$options || in_array('SAFE_SEARCH_DETECTION',$options)) {
            $feature = new Google_Service_Vision_Feature();
            $feature->setType('SAFE_SEARCH_DETECTION');
            $feature->setMaxResults(100);
            $features[] = $feature;
        }

        if(!$options || in_array('IMAGE_PROPERTIES',$options)) {
            $feature = new Google_Service_Vision_Feature();
            $feature->setType('IMAGE_PROPERTIES');
            $feature->setMaxResults(100);
            $features[] = $feature;
        }


        $src = new Google_Service_Vision_ImageSource();
        $src->setGcsImageUri($gcsurl);
        $image = new Google_Service_Vision_Image();
        $image->setSource($src);


        $payload = new Google_Service_Vision_AnnotateImageRequest();
        $payload->setFeatures($features);
        $payload->setImage($image);

        $body->setRequests([$payload]);

        /** @var $res \Google_Service_Vision_BatchAnnotateImagesResponse */
        try {
            $res = $service->images->annotate($body, $optParams);
        } catch (Exception $e) {
            return($this->addError($e->getMessage()));
        }

        $ret=[];
        /** @var Google_Service_Vision_AnnotateImageResponse $item */
        foreach ($res->getResponses() as $item) {
            if(null !== $item->getError()) {
                return($this->addError($item->getError()->message));
            }
            //_printe($item->toSimpleObject());
            /** @var Google_Service_Vision_FaceAnnotation $faceAnnotation */
            if($faceAnnotations=$item->getFaceAnnotations())
                foreach ($faceAnnotations as $faceAnnotation) {
                    $ret['faceAnnotations'][] = ['confidence'=>$faceAnnotation->detectionConfidence
                        ,'joy'=>$faceAnnotation->joyLikelihood
                        ,'sorrow'=>$faceAnnotation->sorrowLikelihood
                        ,'anger'=>$faceAnnotation->angerLikelihood
                        ,'surpise'=>$faceAnnotation->surpriseLikelihood
                        ,'exposed'=>$faceAnnotation->underExposedLikelihood
                        ,'blurred'=>$faceAnnotation->blurredLikelihood
                        ,'headWear'=>$faceAnnotation->headwearLikelihood
                        ,'rollAngle'=>$faceAnnotation->rollAngle
                        ,'tiltAngle'=>$faceAnnotation->tiltAngle
                        ,'panAngle'=>$faceAnnotation->panAngle

                    ];
                }

            /** @var Google_Service_Vision_EntityAnnotation $landmarkAnnotation */
            if($landmarkAnnotations = $item->getLandmarkAnnotations())
                foreach ($landmarkAnnotations as $landmarkAnnotation) {
                    $ret['landmarkAnnotations'][] = ['description'=>$landmarkAnnotation->description,'score'=>$landmarkAnnotation->score];
                }

            /** @var Google_Service_Vision_EntityAnnotation $logoAnnotation */
            if($logoAnnotations = $item->getLogoAnnotations())
                foreach ($logoAnnotations as $logoAnnotation) {
                    $ret['logoAnnotations'][] = ['description'=>$logoAnnotation->description,'score'=>$logoAnnotation->score];
                }

            /** @var Google_Service_Vision_EntityAnnotation $labelAnnotation */
            if($labelAnnotations=$item->getLabelAnnotations())
                foreach ($labelAnnotations as $labelAnnotation) {
                    $ret['labelAnnotations'][] = ['description'=>$labelAnnotation->description,'score'=>$labelAnnotation->score];
                }

            /** @var Google_Service_Vision_EntityAnnotation $textAnnotation */
            if($textAnnotations=$item->getTextAnnotations())
                foreach ($textAnnotations as $textAnnotation) {
                    $annotation = ['description'=>$textAnnotation->description,'vertices'=>[]];
                    /** @var Google_Service_Vision_Vertex $vertex */
                    foreach ($textAnnotation->getBoundingPoly()->vertices as $vertex) {
                        $annotation['vertices'][] = ['x'=>$vertex->getX(),'y'=>$vertex->getY()];
                    }
                    $ret['textAnnotations'][] = $annotation;
                }

            /** @var Google_Service_Vision_SafeSearchAnnotation $safe */
            $safe = $item->getSafeSearchAnnotation();
            if($safe) $ret['safeSearchAnnotation'][] = ['adult'=>$safe->getAdult(),'spoof'=>$safe->getSpoof(),'medical'=>$safe->getMedical(),'violence'=>$safe->getViolence()];

            /** @var Google_Service_Vision_ImageProperties $image */
            $image = $item->getImagePropertiesAnnotation();
            if($image) {
                /** @var  Google_Service_Vision_ColorInfo $dominantColor */
                foreach ($image->getDominantColors() as $dominantColor) {
                    /** @var  Google_Service_Vision_Color $color */
                    $color = $dominantColor->getColor();
                    if(is_object($color))
                        $colors[] = ['color'=>[$color->getRed(),$color->getGreen(),$color->getBlue(),$color->getAlpha()],'score'=>$dominantColor->score];
                }
                if($safe) $ret['imageProperties'][] = ['colors'=>$colors];

            }

        }

        return $ret;

    }

    function analyze_pdf($gcsurl) {

        if($this->error) return;

        $optParams = [];
        $this->client->setScopes([Google_Service_Vision::CLOUD_PLATFORM]);

        $service = new Google_Service_Vision($this->client);
        //$body = new Google_Service_Vision_BatchAnnotateImagesRequest();
        $body = new Google_Service_Vision_BatchAnnotateFilesRequest();

        // Feature of the OCR
        $features = [];
        $feature = new Google_Service_Vision_Feature();
        $feature->setType('DOCUMENT_TEXT_DETECTION');
        //$feature->setMaxResults(200);
        $features[] = $feature;

        //$src = new Google_Service_Vision_ImageSource();
        // $src->setGcsImageUri($gcsurl);
        //$image = new Google_Service_Vision_Image();
        //$image->setSource($src);

        //$payload = new Google_Service_Vision_AnnotateImageRequest();
        //$payload->setFeatures($features);
        //$payload->setImage($image);

        $gcsSource = new Google_Service_Vision_GcsSource();
        $gcsSource->setUri($gcsurl);

        $inputConfig = new Google_Service_Vision_InputConfig();
        $inputConfig->setGcsSource($gcsSource);
        $inputConfig->setMimeType('application/pdf');

        $payload = new Google_Service_Vision_AnnotateFileRequest();
        $payload->setFeatures($features);
        $payload->setInputConfig($inputConfig);
        $body->setRequests([$payload]);


        /** $res Google_Service_Vision_BatchAnnotateFilesResponse */
        try {
            $res = $service->files->annotate($body,$optParams);
        } catch (Exception $e) {
            _printe($e->getMessage());
            return($this->addError('Excepción capturada: ',  $e->getMessage()));
        }

        /** @var $itemFile Google_Service_Vision_AnnotateFileResponse $item */
        foreach ($res->getResponses() as $itemFile) {
            if(null !== $itemFile->getError()) {
                return($this->addError($itemFile->getError()->message));
            }

            /** @var $item Google_Service_Vision_AnnotateImageResponse $item */
            foreach ($itemFile->getResponses() as $item) {
                //avoid descriptions if $item->getFullTextAnnotation() returns null
                if($item->getFullTextAnnotation())
                    $ret['textAnnotations'][] = ['description'=>$item->getFullTextAnnotation()->getText()];
            }
        }
        return $ret;

    }


    function check($image='gs://cloudframework-public/api-vision/face.jpg') {

        return($this->analyze($image));

    }
}