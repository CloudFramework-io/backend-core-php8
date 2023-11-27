<?php
class API extends RESTful
{
    /** @var  Buckets $buckets */
    private $buckets;
    function main()
    {

        $this->sendCorsHeaders('GET,POST');
        if(!$this->checkMethod('GET,POST')) return;

        if(!$this->core->config->get('bucketUploadPathTest')) return($this->setErrorFromCodelib('system-error','missing bucketUploadPathTest config var'));
        $this->buckets = $this->core->loadClass('Buckets',$this->core->config->get('bucketUploadPathTest'));
        if($this->buckets->error) return($this->setErrorFromCodelib('system-error',$this->buckets->errorMsg));

        // Call endpoints
        if(!$this->useFunction('ENDPOINT_'.$this->params[0]))  return($this->setErrorFromCodelib('params-error','use [POST]/_upload/uploadUrl'));

    }

    /**
     * get uploadUrl to send files
     */
    public function ENDPOINT_uploadUrl() {

        // To pass info using session
        $this->core->session->init();

        $public = (isset($this->formParams['public']) && $this->formParams['public'])?true:false;
        $ssl = (isset($this->formParams['ssl']) && $this->formParams['ssl'])?true:false;
        $apply_hash_to_filenames = (isset($this->formParams['apply_hash_to_filenames']) && $this->formParams['apply_hash_to_filenames'])?true:false;
        $allowed_content_types = (isset($this->formParams['allowed_content_types']) && $this->formParams['allowed_content_types'])?$this->formParams['allowed_content_types']:'';
        $allowed_extensions = (isset($this->formParams['allowed_extensions']) && $this->formParams['allowed_extensions'])?$this->formParams['allowed_extensions']:'';

        $upload_properties = ['public'=>$public,'ssl'=>$ssl,'apply_hash_to_filenames'=>$apply_hash_to_filenames,'allowed_content_types'=>$allowed_content_types,'allowed_extensions'=>$allowed_extensions];
        $this->core->session->set('_uploadProperties',$upload_properties);

        // The return URL once the files have been sent to process those files
        $retUrl = str_replace('uploadUrl','manageFiles',$this->core->system->url['host_url']);

        // Gather uploadUrl
        $ret = array_merge(['uploadUrl' => $this->buckets->getUploadUrl($retUrl)],$this->buckets->vars,['uploadProperties'=>$upload_properties]);


        // return the data
        $this->addReturnData(['UploadInfo'=>$ret]);
    }

    /**
     * get uploadUrl to send files
     */
    public function ENDPOINT_uploadUrlV2() {

        $url = $this->buckets->getSignedUploadUrl('upload/file.pdf');

        $this->addReturnData(['UploadInfo'=>['url'=>$url]]);

    }
    /**
     * get uploadUrl to send files
     */
    public function ENDPOINT_downloadUrlV2() {


        $url = $this->buckets->getSignedDownloadUrl('upload/file.txt',['saveAsName'=>'test2.pdf','responseType'=>'application/pdf','responseDisposition'=>'inline']);
        $url = $this->buckets->getSignedDownloadUrl('upload/file.txt',['saveAsName'=>'test2.pdf','responseDisposition'=>'inline']);
        $this->addReturnData(['DownloadInfo'=>['url'=>$url]]);

    }

    /**
     * get uploadUrl to send files
     */
    public function ENDPOINT_info() {

        $publicUrl = $this->buckets->getPublicUrl('upload/file.txt','application/pdf');
        $info = $this->buckets->getInfo('upload/file.txt');
        $this->addReturnData(['FileInfo'=>['publicUrl'=>$publicUrl,'info'=>$info]]);

    }


    /**
     * Process File Uploads
     */
    public function ENDPOINT_manageFiles() {

        // To allow receive parameters for the upload from uploadUrl
        $this->core->session->init();
        $upload_properties =$this->core->session->get('_uploadProperties');
        if(!$upload_properties) $upload_properties=[];


        // Rewrite properties
        if(isset($this->formParams['public'])) $upload_properties['public']=($this->formParams['public'])?true:false;
        if(isset($this->formParams['ssl'])) $upload_properties['ssl']=($this->formParams['ssl'])?true:false;
        if(isset($this->formParams['apply_hash_to_filenames'])) $upload_properties['apply_hash_to_filenames']=($this->formParams['apply_hash_to_filenames'])?true:false;
        if(isset($this->formParams['allowed_content_types'])) $upload_properties['allowed_content_types']=$this->formParams['allowed_content_types'];
        if(isset($this->formParams['allowed_extensions'])) $upload_properties['allowed_extensions']=$this->formParams['allowed_extensions'];


        // Allow to specify a destination in development environment.
        $dest = '';
        if($this->core->is->development()) {
            if($this->formParams['destination']) {
                $dest = $this->formParams['destination'];
            }
        }

        // ManageUploads
        $ret = $this->buckets->manageUploadFiles($dest,$upload_properties);
        if($this->buckets->error) {
            if($this->buckets->code!='') return($this->setErrorFromCodelib('not-allowed',$this->buckets->errorMsg));
            else return($this->setErrorFromCodelib('system-error',$this->buckets->errorMsg));
        }

        $this->addReturnData($ret );
    }
}
