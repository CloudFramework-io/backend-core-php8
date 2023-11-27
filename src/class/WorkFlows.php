<?php


/**
 * [$cfos = $this->core->loadClass('CFOs');] Class CFOs to handle CFO app for CloudFrameworkInterface
 * https://www.notion.so/cloudframework/CFI-PHP-Class-c26b2a1dd2254ddd9e663f2f8febe038
 * Mandrill references: https://mailchimp.com/developer/transactional/api/
 * last_update: 202201
 * X-Mandrill-Signature: mYCSmflkBKULrfItXfIsmmpht8Q=
 * @package CoreClasses
 */
class WorkFlows
{

    var $version = '20230122';
    /** @var Core7 */
    var $core;
    /** @var Mandrill */
    var $mandrill;

    /** @var CFOs $cfos */
    var $cfos = null;


    var $error = false;                 // When error true
    var $errorMsg = [];                 // When error array of messages

    /**
     * DataSQL constructor.
     * @param Core $core
     * @param array $model where [0] is the table name and [1] is the model ['model'=>[],'mapping'=>[], etc..]
     */
    function __construct(Core7 &$core, $params = [])
    {
        $this->core = $core;
        //region Create a
        require_once $this->core->system->root_path.'/vendor/mandrill/mandrill/src/Mandrill.php'; //Not required with Composer

        if(($params['mandrill_api_key']??null)) $this->setMandrillApiKey($params['mandrill_api_key']);
    }

    /**
     * SET API for mandrill interation and SETUP $this->mandrill
     * @param $apiKey
     * @throws Mandrill_Error
     */
    public function setMandrillApiKey($apiKey) {
        $this->mandrill = new Mandrill($apiKey);
    }

    /**
     * SET API for mandrill interation and SETUP $this->mandrill
     * @param $label
     * @return array|void if there is no error it returns the array of templates in the server
     */
    public function getMandrillTemplates($label=null) {
        if(!$this->mandrill) return $this->addError('Missing Mandrill API_KEY. use function setMandrillApiKey($pau_key)');
        try {
            $templates = $this->mandrill->templates->getList($label);
            /**
            [0] => slug
            [1] => name
            [2] => code
            [3] => publish_code
            [4] => published_at
            [5] => created_at
            [6] => updated_at
            [7] => draft_updated_at
            [8] => publish_name
            [9] => labels
            [10] => text
            [11] => publish_text
            [12] => subject
            [13] => publish_subject
            [14] => from_email
            [15] => publish_from_email
            [16] => from_name
            [17] => publish_from_name
             */
            return $templates;
        } catch (Mandrill_Error $e) {
            return $this->addError($e->getMessage());
        }
    }

    /**
     * Retrive a email template with if $slug
     * @param $slug
     * @return array|void if there is no error it returns the array of templates in the server
     */
    public function getMandrillTemplate($slug)
    {
        if(!$this->mandrill) return $this->addError('Missing Mandrill API_KEY. use function setMandrillApiKey($pau_key)');
        try {
            $template = $this->mandrill->templates->info($slug);
        } catch (Mandrill_Error $e) {
            return $this->addError($e->getMessage());
        }

        return $template;

    }

    /**
     * Retrive a email template with if $slug
     * @param $slug
     * @param $data
     * @return string|void HTML of the template or false if ERROR
     */
    public function renderMandrillTemplate(string $slug,array $data)
    {
        if(!$this->mandrill) return $this->addError('Missing Mandrill API_KEY. use function setMandrillApiKey($pau_key)');
        try {
            $vars = [];
            if(is_array($data)) foreach ($data as $key=>$value) {
                $vars[] = ['name'=>$key,'content'=>$value];
            }
            if(is_array($data['email_template_vars']??null)) foreach ($data['email_template_vars'] as $key=>$value) {
                $vars[] = ['name'=>$key,'content'=>$value];
            }

            $template = $this->mandrill->templates->render($slug,[],$vars);
        } catch (Mandrill_Error $e) {
            return $this->addError($e->getMessage());
        }

        return $template['html'];

    }

    /**
     * Retrive Mandrill WebHooks
     * @return array|void if there is no error it returns the array of templates in the server
     */
    public function getMandrillWebHooks()
    {
        if(!$this->mandrill) return $this->addError('Missing Mandrill API_KEY. use function setMandrillApiKey($pau_key)');
        try {
            $webhooks = $this->mandrill->webhooks->getList();
        } catch (Mandrill_Error $e) {
            return $this->addError($e->getMessage());
        }

        return $webhooks;

    }

    /**
     * Retrive Mandrill Domains
     * @return array|void if there is no error it returns the array of templates in the server
     * [{
     * [0] => domain
     * [1] => created_at
     * [2] => last_tested_at
     * [3] => spf
     * [4] => dkim
     * [5] => verified_at
     * [6] => valid_signing
     * [7] => verify_txt_key
     * }]
     */
    public function getMandrillDomains()
    {
        if(!$this->mandrill) return $this->addError('Missing Mandrill API_KEY. use function setMandrillApiKey($pau_key)');
        try {
            $domains = $this->mandrill->senders->domains();
        } catch (Mandrill_Error $e) {
            return $this->addError($e->getMessage());
        }

        return $domains;

    }

    /**
     * Retrive Mandrill Senders used in the email marketing
     * @return array|void if there is no error it returns the array of templates in the server
     * [{
     * [0] => sent
     * [1] => hard_bounces
     * [2] => soft_bounces
     * [3] => rejects
     * [4] => complaints
     * [5] => unsubs
     * [6] => opens
     * [7] => clicks
     * [8] => unique_opens
     * [9] => unique_clicks
     * [10] => reputation
     * [11] => address
     * }]
     */
    public function getMandrillSenders()
    {
        if(!$this->mandrill) return $this->addError('Missing Mandrill API_KEY. use function setMandrillApiKey($pau_key)');
        try {
            $senders = $this->mandrill->senders->getList();
        } catch (Mandrill_Error $e) {
            return $this->addError($e->getMessage());
        }

        return $senders;

    }



    /**
     * Retrive Mandrill message info
     * @return array|void if there is no error it returns the array of templates in the server
     * [{
     * [0] => ts
     *   [1] => _id
     *   [2] => state
     *   [3] => subject
     *   [4] => email
     *   [5] => tags
     *   [6] => opens
     *   [7] => clicks
     *   [8] => smtp_events
     *   [9] => resends
     *   [10] => sender
     *   [11] => template
     *   [12] => opens_detail
     *   [13] => clicks_detail
     * }]
     */
    public function getMandrillMessageInfo(string $id)
    {
        if(!$this->mandrill) return $this->addError('Missing Mandrill API_KEY. use function setMandrillApiKey($pau_key)');
        try {
            $info = $this->mandrill->messages->info($id);
        } catch (Mandrill_Error $e) {
            return $this->addError($e->getMessage());
        }
        return $info;
    }


    /**
     * Retrive Mandrill message content
     * @return array|void if there is no error it returns the array of templates in the server
     * [{
     *    [0] => subject
     *    [1] => from_email
     *    [2] => from_name
     *    [3] => tags
     *    [4] => to
     *    [5] => html
     *    [6] => headers
     *    [7] => attachments
     *    [8] => text
     *    [9] => ts
     *    [10] => _id
     * }]
     */
    public function getMandrillMessageContent(string $id)
    {
        if(!$this->mandrill) return $this->addError('Missing Mandrill API_KEY. use function setMandrillApiKey($pau_key)');
        try {
            $content = $this->mandrill->messages->content($id);
        } catch (Mandrill_Error $e) {
            return $this->addError($e->getMessage());
        }
        return $content;
    }

    /**
     * Retrieve an email template from the ERP
     * @param CFOs $cfos
     * @param string $slug
     * @param string $type
     */
    public function getERPEmailTemplate(CFOs &$cfos,string $slug,string $type='Mandrill') {

        $this->cfos = $cfos;


        $dsTemplate = $this->cfos->ds('CloudFrameWorkEmailTemplates')->fetchOneByKey($slug);
        if($this->cfos->ds('CloudFrameWorkEmailTemplates')->error) return $this->addError($this->cfos->ds('CloudFrameWorkEmailTemplates')->errorMsg);

        if($type=='Mandrill' && is_object($this->mandrill)) {
            if(!$dsTemplate) {
                if (!$mandrillTemplate = $this->getMandrillTemplate($slug)) return;
                $template = $this->getEntityFromMandrillTemplate([], $mandrillTemplate);
                $dsTemplate = $this->cfos->ds('CloudFrameWorkEmailTemplates')->createEntities($template)[0] ?? null;
                if ($this->cfos->ds('CloudFrameWorkEmailTemplates')->error) return $this->addError($this->cfos->ds('CloudFrameWorkEmailTemplates')->errorMsg);
//                $template = $this->getEntityFromMandrillTemplate($dsTemplate,$mandrillTemplate);
//                if($template['DateUpdating'] != $dsTemplate['DateUpdating']) {
//                    $this->cfos->ds('CloudFrameWorkEmailTemplates')->createEntities($template);
//                    if($this->cfos->ds('CloudFrameWorkEmailTemplates')->error) return $this->addError($this->cfos->ds('CloudFrameWorkEmailTemplates')->errorMsg);
//                }
//                $dsTemplate = $template;
            }
        }

        return $dsTemplate;

    }



    /**
     * Retrieve an email template from the ERP
     * @param CFOs $cfos
     * @param string $slug
     * @param string $type
     */
    public function getERPEmailMessage(CFOs &$cfos,string $id,string $type='Mandrill',$update_processing=null)
    {
        $this->cfos = $cfos;
        $dsEmeail = $this->cfos->ds('CloudFrameWorkEmails')->fetchOne('*',['EngineId'=>$id])[0]??null;
        if($this->cfos->ds('CloudFrameWorkEmails')->error) return $this->addError($this->cfos->ds('CloudFrameWorkEmails')->errorMsg);
        if($type=='Mandrill' && is_object($this->mandrill)) {
            if(!$dsEmeail) {
                if (!$email_info = $this->getMandrillMessageInfo($id)) return;
                if (!$email = $this->getMandrillMessageContent($id)) return;
                $entity = $this->getEntityFromMandrillMessage([], $email,$email_info,$update_processing);
                $dsEmeail = $this->cfos->ds('CloudFrameWorkEmails')->createEntities($entity)[0]??null;
                if($this->cfos->ds('CloudFrameWorkEmails')->error) return $this->addError($this->cfos->ds('CloudFrameWorkEmails')->errorMsg);
            }
        }
        if(!$dsEmeail) return $this->addError('EmailMessage not found: '.$id);
        return $dsEmeail;
    }

        /**
     * @param CFOs $cfo class to hangle ERP CFO models
     * @param array $params {
     *    Parameters by reference to send an email.
     *      - slug string Template Id to send
     *      - from string email from sender. [optional] if the Mandrill Template has DefaultFromEmail
     *      - name string [optional] name from sender.
     *      - subject string email subject. [optional] if the Mandrill Template has DefaultSubject
     *      - to string|array email/s to send the email. If it is string the emails has to be separated by ','. If it is an array it has to be an array
     *           of objects with the following structure: ['email'=>(string),'name'=>(optional string)
     *      - cc string|array [optional] email/s to send the email in cc. If it is string the emails has to be separated by ','. If it is an array it has to be an array
     *           of objects with the following structure: ['email'=>(string),'name'=>(optional string)
     *      - bcc string [optional] email to send a copy in bcc
     *      - reply_to string [optional] email to redirect the replies of the email
     *      - cat string [optional] Category for the email. If it is not sent it will take the Category of the email template
     *      - data array [optional] array of objects [key=>value] to be sent as variables to merge with the template
     *      - tags array [optional] array tags to add to the emial [tag1,tag2..]
     *      - attachments array [optional] array objects to be sent as attachments. Format of each object: ['type'=>'{mime-type}(example:application/pdf)','name'=>'{filename}(example:file.pdf)','content'=>base64_encode({file-content})];
     * }
     * @param string $type [optional] has to value: Mandrill
     */
    public function sendERPEmail(CFOs &$cfos,array &$params,string $type='Mandrill') {
        if($type!='Mandrill') return $this->addError('sendEmail($type,array $params) only $type="Mandrill" is supported');
        $this->cfos = $cfos;
        switch ($type) {
            case "Mandrill":
                if(!$slug = $params['slug']??null) return $this->addError('sendEmail($params) missing slug in $params because the template does not have a default from email');
                if(!$from = $params['from']??null) return $this->addError('sendEmail($params) missing from in $params because the template does not have a default from email');
                $from_name = $params['name']??null;
                if(!$to = $params['to']??null) return $this->addError('sendEmail($params) missing to in $params because the template does not have a default from email');
                if(!$subject = $params['subject']??null) return $this->addError('sendEmail($params) missing subject in $params because the template does not have a default from email');
                $tags = $params['tags']??null;
                $data = $params['data']??[];
                $cc = $params['cc']??null;
                $reply_to = $params['reply_to']??null;
                $bcc = $params['bcc']??null;

                if(!$template = $this->getERPEmailTemplate($this->cfos,$slug)) return;
                $cat = $params['cat']??($template['Cat']??'NOT-DEFINED');
                $html = $this->renderMandrillTemplate($slug,$data);

                if(!is_array($to))
                    $to = explode(',',$to??'');
                if(!is_array($tags))
                    $tags = explode(',',$tags??'');
                if(!is_array($cc))
                    $cc = explode(',',$cc??'');
                $submission = [
                    "Cat"=>$cat,
                    "DateInsertion"=>'now',
                    "From"=>$from,
                    "To"=>$to,
                    "Cc"=>$cc,
                    "Subject"=>$subject,
                    "EmailTemplateId"=>$slug,
                    "Tags"=>$tags,
                    "EngineType"=>'Mandrill',
                    "TemplateHTML"=>$template['TemplateHTML'],
                    "TemplateTXT"=>$template['TemplateTXT'],
                    "DateProcessing"=>null,
                    "StatusProcessing"=>'initiated',
                    "JSONProcessing"=>['Reply-To'=>$reply_to,'bcc'=>$bcc,'TemplateVariables'=>$data,'Result'=>null],
                ];
                $dsSubmission = $this->cfos->ds('CloudFrameWorkEmailsSubmissions')->createEntities($submission)[0]??null;
                if($this->cfos->ds('CloudFrameWorkEmailTemplates')->error) return $this->addError($this->cfos->ds('CloudFrameWorkEmailTemplates')->errorMsg);

                $result = $this->sendMandrillEmail($params);
                $dsSubmission['StatusProcessing'] = $result['success']?'success':'error';
                $dsSubmission['JSONProcessing']['Result'] = $result;
                $dsSubmission['DateProcessing'] = "now";
                $dsSubmission = $this->cfos->ds('CloudFrameWorkEmailsSubmissions')->createEntities($dsSubmission)[0]??null;
                if($this->cfos->ds('CloudFrameWorkEmailTemplates')->error) return $this->addError($this->cfos->ds('CloudFrameWorkEmailTemplates')->errorMsg);

                if($result['success']) foreach ( $result['result'] as $i=>$item) {
                    $email = [
                        "Cat"=>$dsSubmission['Cat'],
                        "SubmissionId"=>$dsSubmission['KeyId'],
                        "EngineTemplate"=>$slug,
                        "EngineType"=>'Mandrill',
                        "EngineId"=>$item['_id'],
                        "Type"=>'OUT',
                        "DateInsertion"=>'now',
                        "From"=>$from,
                        "To"=>$item['email']??$to,
                        "Subject"=>$subject,
                        "Tags"=>$tags,
                        "Opens"=>0,
                        "Clicks"=>0,
                        "BODY_HTML"=>utf8_encode($html),
                        "DateProcessing"=>"now",
                        "UpdateProcessing"=>"now",
                        "StatusProcessing"=>$item['status']??'unknown',
                        "JSONProcessing"=>['Result'=>$item,'Info'=>[]],
                    ];
                    $dsEmail = $this->cfos->ds('CloudFrameWorkEmails')->createEntities($email)[0]??null;
                    if($this->cfos->ds('CloudFrameWorkEmails')->error) {
                        $result['result'][$i]['CloudFrameWorkEmails'] = $this->cfos->ds('CloudFrameWorkEmails')->errorMsg;
                    } else {
                        $result['result'][$i]['CloudFrameWorkEmails'] = strval($dsEmail['KeyId']);
                    }
                }
                return($result);
                break;
        }
    }


    /**
     * SET API for mandrill interation and SETUP $this->mandrill
     * @param array $params {
     *      info to be sent in the email
     *      - from string email from sender. [optional] if the Mandrill Template has DefaultFromEmail
     *      - name string name from sender. [optional] if the Mandrill Template has DefaultFromEmail
     *      - subject string email subject. [optional] if the Mandrill Template has DefaultSubject
     *      - to string|array email/s to send the email. If it is string the emails has to be separated by ','. If it is an array it has to be an array
     *           of objects with the following structure: ['email'=>(string),'name'=>(optional string)
     *      - cc string|array [optional] email/s to send the email in cc. If it is string the emails has to be separated by ','. If it is an array it has to be an array
     *           of objects with the following structure: ['email'=>(string),'name'=>(optional string)
     *      - bcc string [optional] email to send a copy in bcc
     *      - reply_to string [optional] email to redirect the replies of the email
     *      - data array [optional] array of objects [key=>value] to be sent as variables to merge with the template
     *      - tags array [optional] array tags to add to the emial [tag1,tag2..]
     *      - attachments array [optional] array objects to be sent as attachments. Format of each object: ['type'=>'{mime-type}(example:application/pdf)','name'=>'{filename}(example:file.pdf)','content'=>base64_encode({file-content})];
     * }
     * @throws Mandrill_Error
     */
    public function sendMandrillEmail(array &$params) {

        if(!$this->mandrill) return $this->addError('Missing Mandrill API_KEY. use function setMandrillApiKey($pau_key)');
        if(!($slug = $params['slug']??null)) return $this->addError('sendMandrillEmail($params) missing slug in $params because the template does not have a default from email');

        if(!$template = $this->getMandrillTemplate($slug)) return;
        if(!$from = $params['from']??($template['DefaultFromEmail']??null)) return $this->addError('sendMandrillEmail($params) missing email in $params because the template does not have a default from email');
        if(!$subject = $params['subject']??($template['DefaultSubject']??null)) return $this->addError('sendMandrillEmail($params) missing subject in $params because the template does not have a default subject');
        $from_name = $params['name']??($template['DefaultFromName']??'');

        if(!$emails_to = $params['to']??null) return $this->addError('sendMandrillEmail($params) missing to in $params');
        if(!is_array($emails_to)) $emails_to = explode(',',$emails_to);

        $emails_cc = $params['cc']??[];
        if($emails_cc && !is_array($emails_cc)) $emails_cc = explode(',',$emails_cc);

        $email_bcc = $params['bcc']??null;
        $reply_to = $params['reply_to']??null;

        $email_data= $params['data']??[];
        if(!is_array($email_data)) $email_data=[];
        $tags = $params['tags']??[];
        if(!is_array($tags)) $tags=explode(',',$tags);

        $headers = [];
        if($reply_to && $this->core->is->validEmail($reply_to))
            $headers['Reply-To'] =$reply_to;

        try {
            $message = array(
                'subject' => $subject,
                'from_email' => $from,
                'from_name' => $from_name,
                'headers' => $headers,
                'important' => false,
                'track_opens' => null,
                'track_clicks' => null,
                'auto_text' => null,
                'auto_html' => null,
                'inline_css' => null,
                'url_strip_qs' => null,
                'preserve_recipients' => null,
                'view_content_link' => null,
                'bcc_address' => ($email_bcc && $this->core->is->validEmail($email_bcc))?$email_bcc:null,
                'tracking_domain' => null,
                'signing_domain' => null,
                'return_path_domain' => null,
                'merge' => true,
                'tags' => $tags?:null,
                //'google_analytics_domains' => array($domain),
                //'google_analytics_campaign' => $domain,
                //'metadata' => array('website' => $domain)
            );



            //region to: into $message['to']
            $message['to'] = [];
            foreach ($emails_to as $email_to) {
                if(is_array($email_to)) {
                    if(!($email_to['email']??null)) return $this->addError('sendMandrillEmail($params) Wrong $params["to"] array. Missing email attribute');
                    $message['to'][] = ['email' => $email_to['email'], 'name' => $email_to['name'] ?? $email_to['email'], 'type' => 'to'];
                }else
                    $message['to'][] = ['email'=>$email_to,'name'=> $email_to,'type'=>'to'];
            }
            //endregion

            //region cc: into $message['to']
            foreach ($emails_cc as $email_cc) {
                if(is_array($email_cc)) {
                    if(!($email_cc['email']??null)) return $this->addError('sendMandrillEmail($params) Wrong $params["to"] array. Missing email attribute');
                    $message['to'][] = ['email' => $email_cc['email'], 'name' => $email_cc['name'] ?? $email_cc['email'], 'type' => 'cc'];
                }else
                    $message['to'][] = ['email'=>$email_cc,'name'=> $email_cc,'type'=>'cc'];
            }
            //endregion

            //region ADD Attachments
            if(($params['attachments']??null) ) {
                $message['attachments'] = $params['attachments'];
            }
            //endregion
             //region Add $email_data into the email template
            $template_content=[];
            $message['global_merge_vars']=[];
            if(is_array($email_data)) foreach ($email_data as $key=>$value) {
                $template_content[] = ['name'=>$key,'content'=>$value];
                $message['global_merge_vars'][] = ['name'=>$key,'content'=>$value];
            }
            //endregion
            $async = false;
            $ip_pool = 'Main Pool';
//            $send_at = date("Y-m-d h:m:i");
//            $result = $this->mandrill->messages->sendTemplate($slug, $template_content, $message, $async, $ip_pool, $send_at);
            $result = $this->mandrill->messages->sendTemplate($slug, $template_content, $message, $async, $ip_pool);
            return ['success'=>true,'result'=>$result];
        } catch (Error $e) {
            return ['success'=>false,'result'=>$e->getMessage()];
            return $this->addError($e->getMessage());
        }
    }


    /**
     * Add an error in the class
     * @param $value
     */
    function addError($value)
    {
        $this->error = true;
        $this->errorMsg[] = $value;
    }

    /**
     * Return an array with the structure of ds:CloudFrameWorkEmailTemplates taking Mandrill $template array info
     * @param array $entity
     * @param array $template
     * @return array value of $entity modified with template variables
     */
    private function getEntityFromMandrillTemplate(array $entity,array $template) {

        $entity['KeyName']=$template['slug'];
        $entity['TemplateDescription']=$template['name'];
        $entity['Labels']=$template['labels'];
        $entity['TemplateAcive']=true;
        $entity['DefaultSubject']=$template['publish_subject'];
        $entity['DefaultFromEmail']=$template['publish_from_email'];
        $entity['DefaultFromName']=$template['publish_from_name'];
        $entity['PublishedDate']=$template['published_at'];
        $entity['DateInsertion']=$template['created_at'];
        $entity['DateUpdating']=substr($template['updated_at']??'',0,19);
        $entity['Type']='Mandrill';
        $entity['TemplateHTML']=utf8_encode($template['publish_code']??'');
        $entity['TemplateTXT']=utf8_encode($template['publish_text']??'');
        $entity['TemplateURL']="https://mandrillapp.com/templates/code?id=".urlencode($template['slug']);
        $entity['TemplateVariables']=[];

        //region extract Variables
        $code = utf8_encode($template['publish_code']??'');
        do {

            $found = null;
            preg_match("/\*\|([A-z0-9_ ]*)\|\*/", $code, $found);
            if($found) {
                if(!strpos($found[1],':'))
                    $entity['TemplateVariables'][] = $found[1];
                $code = str_replace($found[0],$found[1],$code);
            }
        } while ($found);
        //endregion

        return $entity;
    }



    /**
     * Return an array with the structure of ds:CloudFrameWorkEmails taking Mandrill $message array info
     * @param array $entity
     * @param array $message
     * @param array $info
     * @return array value of $entity modified with template variables
     */
    private function getEntityFromMandrillMessage(array $entity,array $message, array $info=[],$update_processing=null) {

        if(!isset($entity["Cat"]))
            $entity["Cat"]='WEBHOOK-CREATED';
        if(!isset($entity["SubmissionId"]))
            $entity["SubmissionId"]=null;
        $entity["EmailTemplateId"]=$info['template']??null;
        $entity["EngineType"]='Mandrill';
        $entity["EngineId"]=$message['_id'];
        $entity["EngineTemplate"]=$info['template']??null;
        $entity["Type"]='OUT';
        $entity["DateInsertion"]=date('Y-m-d H:i:s',$message['ts']);
        $entity["From"]=$message['from_email'];
        $entity["To"]=$message['to']['email'];
        $entity["Subject"]=$message['subject'];
        $entity["Tags"]=$message['tags'];
        $entity["Opens"]=$info['opens']??0;
        $entity["Clicks"]=$info['clicks']??0;
        $entity["BODY_HTML"]=utf8_encode($message['html']);
        $entity["BODY_TXT"]=utf8_encode($message['text']);
        $entity["DateProcessing"]=date('Y-m-d H:i:s',$message['ts']);
        $entity["UpdateProcessing"]=$update_processing;
        $entity["StatusProcessing"]=$info['state']??'unknown';
        $entity["JSONProcessing"]['Result']=['email'=>$message['to'],"status"=>($info['state']??'unknown'),'_id'=>$message['_id']];
        $entity["JSONProcessing"]['Info']=$info;
        return $entity;
    }

}