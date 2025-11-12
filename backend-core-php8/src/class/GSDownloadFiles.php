<?php
/**
 * CloudFramework Download GS Files v74.0014 stored in Datastore tokens
 * author: hl@cloudframework.io
 * Based in "cloudframework-io/appengine-php-core-7.4": "^v74.00143"
 * @package LabClasses
 */
class GSDownloadFiles
{
    var $core;
    var $error = false;
    var $errorMsg = [];
    var $cache;
    var $namespace;
    var $debug=false;
    var $test=false;

    function __construct(Core7 &$core, $namespace)
    {
        $this->core = $core;
        $this->namespace = $namespace;
        $this->cache = new CoreCache($core);
        if(!$this->cache->activateDataStore($this->namespace)) {
            return($this->addError($this->cache->errorMsg));
        };
        return true;
    }

    /**
     * Allow to download the file in a HTML page taking a token created with $this->createSecuredToken()
     * It print-out a HTML output of the document to be downloaded.
     * @param string $token
     * @param string $type
     * @return void
     */
    public function htmlDownloadFromSecuredToken(string $token) {
        $data = $this->cache->get($token);
        if($this->core->cache->error) {
            // Log cache get
            $this->core->logs->add("Error in service",'Blobs');
            echo "<h1 align='center'> <font color='red'>Error in service.</font></h1>";
            exit;
        }

        if(!$data) {
            // Log cache get
            $this->core->logs->add("Token not found",'Blobs');
            echo "<h1 align='center'> <font color='red'>Security Violation. Token not found</font></h1>";
            exit;
        }
        if(!isset($data['gs_url']) || !isset($data['max_downloads']) || !isset($data['max_seconds'])|| !isset($data['microtime'])) {
            // Log cache get
            $this->core->logs->add("Token does not have valid info",'Blobs');
            echo "<h1 align='center'> <font color='red'>Token does not have valid info. </font></h1>";
            exit;
        }

        $data['max_seconds']=99999;
        if($data['max_seconds']>60*15) $data['max_seconds']=60*15;
        $time = microtime(true)-$data['microtime'];
        if($time>$data['max_seconds']) {
            // Log cache get
            $this->core->logs->add("This token has been expired. [max_seconds={$data['max_seconds']}], [time={$time}]",'Blobs');
            echo "<h1 align='center'> <font color='red'>This token has expired. </font></h1>";
            exit;
        }

        $data['max_downloads']++;

        if($data['max_downloads']<=0) {
            // Log cache get
            $this->core->logs->add("This token has been already used",'Blobs');
            echo "<h1 align='center'> <font color='red'>This token has been already used. </font></h1>";
            exit;
        }

        //If $this->test just return $data content
        if($this->test) {
            _printe($data);
        }

        if(!$this->debug)
            $data['max_downloads']--;

        $this->cache->set($token,$data);
        if($this->cache->errorMsg) {
            $this->core->logs->add($this->cache->errorMsg,'CoreCache');
        }

        $this->downloadBigFileForWeb($data);
        exit;
    }

    /**
     * Execute a download file taking an array previously created with $this->createSecuredToken($gsfile)
     * @param array $data
     *   'url'=>'gs://xxxx'
     */
    public function downloadBigFileForWeb(array $data)
    {
        //region CHECK if isset($data['gs_url'])
        if(!isset($data['gs_url'])) return($this->addError('downloadBigFileForWeb(array $data) missing $data["url"]'));
        $gsurl = $data['gs_url']; // Get URL gs:// xxxxx
        //endregion

        //region SET $gurl,$file_path,$file_name,$options[version=>,saveAsName=>,responseType=>]
        //extract the file-path
        $file_path = preg_replace('/gs:\/\/[^\/]*\//','',$gsurl);
        $file_name = $data['file_name']??basename($data['gs_url']);
        $options = [
            'version'=>'v4'
            ,'saveAsName'=>preg_replace('/[^A-Za-z0-9_\.]/','_',$file_name)
            ,'responseType'=>$data['doc_filetype']??'application/octet-stream'];
        try {
            if (!is_file($gsurl)) {
                die("File '{$file_path}' is not accesible. Please contact with the owner to resolve the issue");
            }
        } catch (Exception $e) {
            die("File '{$e->getMessage()}' is not accesible. Please contact with the owner to resolve the issue");
        }
        //endregion

        //region SET $extension
        $extensions = explode(".",$options['saveAsName']);
        $extension = (count($extensions)>=1)?$extensions[count($extensions)-1]:'file';
        //endregion

        //region IF pdf rewrite $options['responseType'] and $options['responseDisposition']
        if(strpos($options['responseType'],'pdf')!==false || strtolower($extension)=='pdf') {
            $options['responseType'] = 'application/pdf';
            $options['responseDisposition'] = 'inline';
        }
        //endregion
        //region ELSE IF image rewrite $options['responseType'] and $options['responseDisposition']
        elseif(strpos($options['responseType'],'image')!==false || in_array(strtolower($extension),['pdf','png','jpg','jpeg','gif','svg'])) {
            if(in_array(strtolower($extension),['pdf','png','jpg','jpeg','gif','svg'])) {
                $options['responseType'] = 'image/'.$extension;
            }
            $options['responseDisposition'] = 'inline';
        }
        //endregion

        //region SET $url as signed from the Bucket
        try {
            /** @var Buckets $b */
            $b =$this->core->loadClass('Buckets',$gsurl);
            if($b->error) _printe($b->errorMsg);
            $object = $b->gs_bucket->object($file_path);
            $url = $object->signedUrl(
            # This URL is valid for 2 minutes
                new \DateTime('15 min'),
                $options

            );
        } catch (Exception $e) {
            die($e->getMessage());
        }
        //endregion

        //region IF pdf return specific headers
        if((isset($data['content-type']) && strpos($data['content-type'],'pdf')!==false) || strtolower($extension)=='pdf') {
            $data['content-type'] = 'application/pdf';
            header('Content-Type: '.$data['content-type']);
            header('Content-Disposition: inline; filename='.$file_name);

        }
        //endregion
        //region ELSE IF image return specific headers
        elseif((isset($data['content-type']) && strpos($data['content-type'],'image')!==false) || in_array(strtolower($extension),['pdf','png','jpg','jpeg','gif','svg'])) {
            if(in_array(strtolower($extension),['png','jpg','jpeg','gif','svg'])) {
                $data['content-type'] = 'image/'.$extension;
            }
            header('Content-Type: '.$data['content-type']);
            header('Content-Disposition: inline; filename='.$file_name);
        }
        //endregion
        //region ELSE return generic headers
        else {
            if(!isset($data['content-type'])) $data['content-type'] = $options['responseType'];
            header('Content-Description: File Transfer');
            header('Content-Type: '.$data['content-type']);
            header('Content-Disposition: attachment; filename='.$file_name);
            header('Content-Transfer-Encoding: binary');
            header('Connection: Keep-Alive');
        }
        //endregion

        //region SEND the content of the signed URL
        //return the content avoiding memory problem
        readfile($url);
        // other option is to redirect to the temporal url
        //$this->core->system->urlRedirect($url);
        //endregion

        //region force EXIT
        exit;
        //endregion
    }

    /**
     * Prepare a token to be used in htmlDownloadFromSecuredToken
     * @param string $gs_url
     * @param int $max_dowloads default 1
     * @param int $max_seconds default 90
     * @param string $file_name
     * @param string $file_type
     * @return string|void
     */
    public function createSecuredToken(string $gs_url,$max_dowloads=1,$max_seconds=90,$file_name='',$file_type='')
    {
        //region VERIFY parameters
        if(strpos($gs_url,'gs://')!==0) return($this->addError('The following file does start with gs://: '.$gs_url));

        try {
            if(!is_file($gs_url)) return($this->addError('The following file does not exist: '.$gs_url));
        } catch (Exception $e) {
            return($this->addError($e->getMessage()));
        }
        $max_dowloads = (intval($max_dowloads)>0)?intval($max_dowloads):1;
        $max_seconds = (intval($max_dowloads)>0)?intval($max_seconds):1;
        if(!$file_name) $file_name = basename($gs_url);
        if($this->error) return;
        //endregion

        //region SET $token,$data
        $token = 'CFDownload_'.date('YmdHis').'_'.md5($gs_url);
        $data = ['gs_url'=>$gs_url
            ,'max_downloads'=>$max_dowloads
            ,'max_seconds'=>$max_seconds
            ,'microtime'=>microtime(true)
            ,'file_name'=>$file_name
            ,'file_type'=>$file_type
        ];
        //endregion

        //region SAVE in Datastore cache the data
        $this->cache->set($token,$data);
        //endregion

        //region RETURN $token
        return $token;
        //endregion
    }

    /**
     * To handle errors
     * @param $value
     */
    function addError($value)
    {
        $this->error = true;
        $this->errorMsg[] = $value;
    }
}