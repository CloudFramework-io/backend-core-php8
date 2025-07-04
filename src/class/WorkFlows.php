<?php
/**
 * [$workFlows = $this->core->loadClass('WorkFlows',$cfos);] Class to facilitate Email Workflows
 * https://cloudframework.io/docs/es/developers/php-framework/backend-core-php8/03-classes/WorkFlows
 * Mandrill references: https://mailchimp.com/developer/transactional/api/
 * last_update: 20240826
 * @package CoreClasses
 */
// require_once $this->system->root_path.'/vendor/mandrill/mandrill/src/Mandrill.php'; //Not required with Composer
class WorkFlows
{

    var $version = '20240826';
    /** @var Core7 */
    var $core;
    /** @var MailchimpTransactional\ApiClient $mandrill */
    var \MailchimpTransactional\ApiClient $mandrill;

    /** @var CFOs $cfos */
    var $cfos;


    var $error = false;                 // When error true
    var $errorMsg = [];                 // When error array of messages

    /**
     * DataSQL constructor.
     * @param Core $core
     * @param array $model where [0] is the table name and [1] is the model ['model'=>[],'mapping'=>[], etc..]
     */
    function __construct(Core7 &$core, CFOs &$cfos)
    {
        $this->core = $core;
        $this->cfos = $cfos;
        if(!class_exists( 'MailchimpTransactional\ApiClient' ) ) {
            return $this->addError('Missing MailchimpTransactional\ApiClient. use composer require mailchimp/transactional');
        }
        $this->mandrill = new MailchimpTransactional\ApiClient();
    }

    /**
     * SET API for mandrill interation and SETUP $this->mandrill
     * @param $apiKey
     */
    public function setMandrillApiKey($apiKey) {
        try {
            $this->mandrill->setApiKey($apiKey);
        } catch (Exception $e) {
            $this->addError($e->getMessage());
        }
    }

    /**
     * Retrieve a list of Mandrill templates with their details.
     * Based on https://mailchimp.com/developer/marketing/api/templates/list-templates/
     *
     * @param array $options Optional parameters to filter the list of templates.
     * * slug
     * * name
     * * code
     * * publish_code
     * * published_at
     * * created_at
     * * updated_at
     * * draft_updated_at
     * * publish_name
     * * labels
     * * text
     * * publish_text
     * * subject
     * * publish_subject
     * * from_email
     * * publish_from_email
     * * from_name
     * * publish_from_name
     * * is_broken_template
     * @return array|false An array of templates with their properties if successful, false if an error occurs. Each template is an array with the following keys:
     *
     */
    public function getMandrillTemplates(array $options=[]) {
        if(!$this->mandrill) return $this->addError('Missing Mandrill API_KEY. use function setMandrillApiKey($pau_key)');
        try {
            $templates = $this->mandrill->templates->list($options);
            $ret=[];
            foreach ($templates as $template) {
                $ret[] = get_object_vars($template);
            }
            return $ret;
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
        if(!$this->mandrill->getApiKey()) return $this->addError('Missing Mandrill API_KEY. use function setMandrillApiKey($pau_key)');
        try {
            $template = $this->mandrill->templates->info(['name'=>$slug]);
            $template = $this->core->jsonDecode($this->core->jsonEncode($template),true);

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
        if(!$this->mandrill->getApiKey()) return $this->addError('Missing Mandrill API_KEY. use function setMandrillApiKey($pau_key)');
        try {
            $vars = [];
            if(is_array($data)) foreach ($data as $key=>$value) {
                $vars[] = ['name'=>$key,'content'=>$value];
            }
            if(is_array($data['email_template_vars']??null)) foreach ($data['email_template_vars'] as $key=>$value) {
                $vars[] = ['name'=>$key,'content'=>$value];
            }
            $template = $this->mandrill->templates->render([
                'template_name'=>$slug,
                'template_content'=>[],
                'merge_vars'=>$vars]);
            $template = $this->core->jsonDecode($this->core->jsonEncode($template),true);

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
        if(!$this->mandrill->getApiKey()) return $this->addError('Missing Mandrill API_KEY. use function setMandrillApiKey($pau_key)');
        try {
            $webhooks = $this->mandrill->webhooks->list();
            $webhooks = $this->core->jsonDecode($this->core->jsonEncode($webhooks),true);

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
        if(!$this->mandrill->getApiKey()) return $this->addError('Missing Mandrill API_KEY. use function setMandrillApiKey($pau_key)');
        try {
            $domains = $this->mandrill->senders->domains();
            $domains = $this->core->jsonDecode($this->core->jsonEncode($domains),true);

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
        if(!$this->mandrill->getApiKey()) return $this->addError('Missing Mandrill API_KEY. use function setMandrillApiKey($pau_key)');
        try {
            $senders = $this->mandrill->senders->list();
            $senders = $this->core->jsonDecode($this->core->jsonEncode($senders),true);

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
        if(!$this->mandrill->getApiKey()) return $this->addError('Missing Mandrill API_KEY. use function setMandrillApiKey($pau_key)');
        try {
            $info = $this->mandrill->messages->info(['id'=>$id]);
            $info = $this->core->jsonDecode($this->core->jsonEncode($info),true);
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
        if(!$this->mandrill->getApiKey()) return $this->addError('Missing Mandrill API_KEY. use function setMandrillApiKey($pau_key)');
        try {
            $content = $this->mandrill->messages->content(['id'=>$id]);
            $content = $this->core->jsonDecode($this->core->jsonEncode($content),true);
        } catch (Mandrill_Error $e) {
            return $this->addError($e->getMessage());
        }
        return $content;
    }

    /**
     * Retrieve an email template from the ERP
     * @param string $slug
     * @param string $type
     */
    public function getERPEmailTemplate(string $slug,string $type='Mandrill') {
        if(!$this->mandrill->getApiKey()) return $this->addError('getERPEmailTemplate(...) has been call without calling previously setMandrillApiKey(...)');
        $this->cfos->useCFOSecret(true);
        $dsTemplate = $this->cfos->ds('CloudFrameWorkEmailTemplates')->fetchOneByKey($slug);
        if($this->cfos->ds('CloudFrameWorkEmailTemplates')->error) return $this->addError($this->cfos->ds('CloudFrameWorkEmailTemplates')->errorMsg);
        if($type=='Mandrill') {
            //if the template does no exist in CloudFrameWorkEmailTemplates or the DateUpdating is older than 24h reload from mandrill
            if(!$dsTemplate || date('Y-m-d', strtotime($dsTemplate['DateUpdating']. ' + 1 days')) < date('Y-m-d')) {
                if (!$mandrillTemplate = $this->getMandrillTemplate($slug)) return;
                $entity = $this->getEntityTransformedWithMandrillTemplateData($dsTemplate, $mandrillTemplate);
                $dsTemplate = $this->cfos->ds('CloudFrameWorkEmailTemplates')->createEntities($entity)[0] ?? null;
                if ($this->cfos->ds('CloudFrameWorkEmailTemplates')->error) return $this->addError($this->cfos->ds('CloudFrameWorkEmailTemplates')->errorMsg);
            }
        }
        return $dsTemplate;
    }

    /**
     * Retrieve an email template from the ERP
     * @param string $slug
     * @param string $type
     */
    public function getERPEmailMessage(string $id,string $type='Mandrill',$update_processing=null)
    {
        if(!$this->mandrill->getApiKey()) return $this->addError('getERPEmailMessage(...) has been call without calling previously setMandrillApiKey(...)');
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
     * Use method sendPlatformEmail
     * @deprecated
     * @param array $params
     * @param string $type
     * @param string $linked_object
     * @param string $linked_id
     * @return void
     * @throws Mandrill_Error
     */
    public function sendERPEmail(array &$params,string $type='Mandrill',string $linked_object='',string $linked_id='')
    {
        return $this->sendPlatformEmail($params,$type,$linked_object,$linked_id);
    }
    /**
     * Send an email using CLOUDFRAMEWORK CLOUD-CHANNELS/EMAIL
     * @param array $params {
     *    Parameters by reference to send an email.
     *      - slug string Template Id to send
     *      - html string optionally to slug you can send this variable with the HTML to send
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
     *      - important [optional] boolean if it is true then the email will send 'important' attribute
     *      - attachments array [optional] array objects to be sent as attachments. Format of each object: ['type'=>'{mime-type}(example:application/pdf)','name'=>'{filename}(example:file.pdf)','content'=>base64_encode({file-content})];
     *      - async [optional] boolean if it is true then the email will be sent asynchronously
     *      - preserve_recipients [optional] boolean if it is true then the email will preserve the recipients headers instead to appear emails separated
     * }
     * @param string $type [optional] has to value: Mandrill
     * @param string $linked_object [optional] add this value to the ds:CloudFrameWorkEmailsSubmissions.LinkedObject
     * @param string $linked_id [optional] add this value to the ds:CloudFrameWorkEmailsSubmissions.LinkedId
     * @return bool|void
     */
    public function sendPlatformEmail(array &$params,string $type='Mandrill',string $linked_object='',string $linked_id='')
    {
        if($type!='Mandrill') return $this->addError('sendPlatformEmail(...) has received a worng $type. [Mandrill] is the valid value');
        if(!$this->mandrill->getApiKey()) return $this->addError('sendPlatformEmail(...) has been call without calling previously setMandrillApiKey(...)');

        switch ($type) {
            case "Mandrill":

                //region INIT $slug, $from, $to, $subject mandatory values from $params
                $html = $params['html']??null;
                if(!($slug = $params['slug']??null) && !$html) return $this->addError('sendEmail($params) missing [slug] or [html] in $params to define the Email Body');
                if(!$from = $params['from']??null) return $this->addError('sendEmail($params) missing from in $params because the template does not have a default from email');
                if(!$to = $params['to']??null) return $this->addError('sendEmail($params) missing to in $params because the template does not have a default from email');
                if(!$subject = $params['subject']??null) return $this->addError('sendEmail($params) missing subject in $params because the template does not have a default from email');
                if(!is_array($to)) {
                    $to = array_filter(explode(',', $to ?? ''));
                }
                //endregion

                //region INIT $from_name,$tags,$cc,$reply_to,$bcc,$data optional values from $params
                $from_name = $params['name']??null;
                $tags = $params['tags']??null;
                $cc = $params['cc']??null;
                $bcc = $params['bcc']??null;
                $reply_to = $params['reply_to']??null;
                $data = $params['data']??[];
                $important = $params['important']??null;
                if(!is_array($tags))
                    $tags = array_filter(explode(',',$tags??''));
                if(!is_array($cc))
                    $cc = array_filter(explode(',',$cc??''));
                //endregion

                //region INIT $cat from $template['Cat']
                $cat = $params['cat']??($template['Cat']??'NOT-DEFINED');
                //endregion

                //region IF $slug READ $template and rewrite $html
                $template=null;
                if($slug) {
                    if (!$template = $this->getERPEmailTemplate($slug)) return;
                    $html = $this->renderMandrillTemplate($slug,$data);
                } else {
                    $html = $this->core->replaceCloudFrameworkTagsAndVariables($html,$data);
                }
                //endregion

                //region INIT $submission[] with previous INITIATED variables
                $submission = [
                    "Cat"=>$cat,
                    "DateInsertion"=>'now',
                    "From"=>$from,
                    "To"=>$to,
                    "Cc"=>$cc,
                    "Bcc"=>$bcc,
                    "Subject"=>$subject,
                    "EmailTemplateId"=>$slug,
                    "Tags"=>$tags,
                    "EngineType"=>'Mandrill',
                    "TemplateHTML"=>$template['TemplateHTML']??$html,
                    "TemplateTXT"=>$template['TemplateTXT']??null,
                    "DateProcessing"=>null,
                    "StatusProcessing"=>'initiated',
                    "LinkedObject"=>$linked_object?:null,
                    "LinkedId"=>$linked_id?:null,
                    "JSONProcessing"=>[
                        'Reply-To'=>$reply_to,
                        'bcc'=>$bcc,
                        'TemplateVariables'=>$data,
                        'important'=>$important,
                        'attachments'=>null,
                        'Result'=>null],
                ];
                if(is_array($params['attachments']??null)) {
                    $submission['JSONProcessing']['attachments'] = array_column($params['attachments'],'name');
                }
                //endregion

                //region SET $dsSubmission CREATING $submission into CloudFrameWorkEmailsSubmissions
                $this->cfos->useCFOSecret(true);
                $dsSubmission = $this->cfos->ds('CloudFrameWorkEmailsSubmissions')->createEntities($submission)[0]??null;
                if($this->cfos->ds('CloudFrameWorkEmailsSubmissions')->error) {
                    return $this->addError($this->cfos->ds('CloudFrameWorkEmailsSubmissions')->errorMsg);
                }
                //endregion

                //region SET $result of the email CALLING $this->sendMandrillEmail($params)
                $result = $this->sendMandrillEmail($params);
                //endregion

                //region UPDATE $dsSubmission with $result into CloudFrameWorkEmailsSubmissions
                $dsSubmission['StatusProcessing'] = ($result['success']??null)?'success':'error';
                $dsSubmission['JSONProcessing']['Result'] = $result;
                $dsSubmission['DateProcessing'] = "now";

                $dsSubmission = $this->cfos->ds('CloudFrameWorkEmailsSubmissions')->createEntities($dsSubmission)[0]??null;
                if($this->cfos->ds('CloudFrameWorkEmailsSubmissions')->error) {
                    return $this->addError($this->cfos->ds('CloudFrameWorkEmailsSubmissions')->errorMsg);
                }
                //endregion

                //region IF $result['success'] CREATE CloudFrameWorkEmails with each $result['result']
                if($result && ($result['success']??null) && is_array($result['result']??null)) {
                    foreach ($result['result'] as $i => $item) {
                        $item = (array)$item;
                        $result['result'][$i] = $item;
                        $email = [
                            "Cat" => $dsSubmission['Cat'],
                            "SubmissionId" => $dsSubmission['KeyId'],
                            "EngineTemplate" => $slug,
                            "EngineType" => 'Mandrill',
                            "EngineId" => $item['_id'],
                            "Type" => 'OUT',
                            "DateInsertion" => 'now',
                            "From" => $from,
                            "To" => $item['email'] ?? $to,
                            "Subject" => $subject,
                            "Tags" => $tags,
                            "Opens" => 0,
                            "Clicks" => 0,
                            "BODY_HTML" => $this->core->utf8Encode($html),
                            "DateProcessing" => "now",
                            "UpdateProcessing" => "now",
                            "StatusProcessing" => $item['status'] ?? 'unknown',
                            "JSONProcessing" => ['Result' => $item, 'Info' => []],
                        ];
                        $dsEmail = $this->cfos->ds('CloudFrameWorkEmails')->createEntities($email)[0] ?? null;
                        if ($this->cfos->ds('CloudFrameWorkEmails')->error) {
                            $result['result'][$i]['CloudFrameWorkEmails'] = $this->cfos->ds('CloudFrameWorkEmails')->errorMsg;
                        } else {
                            $result['result'][$i]['CloudFrameWorkEmails'] = strval($dsEmail['KeyId']);
                        }
                    }
                }
                //endregion

                //region RETURN $result
                return($result);
                //endregion

                break;
        }

        return false;
    }


    /**
     * SET API for mandrill interation and SETUP $this->mandrill
     * @param array $params {
     *      info to be sent in the email
     *      * slug string template to use for the Body of the content
     *      * html string optionally you can send the body content in this variable
     *      * from string email from sender. [optional] if the Mandrill Template has DefaultFromEmail
     *      * name string name from sender. [optional] if the Mandrill Template has DefaultFromEmail
     *      * subject string email subject. [optional] if the Mandrill Template has DefaultSubject
     *      * to string|array email/s to send the email. If it is string the emails has to be separated by ','. If it is an array it has to be an array
     *           of objects with the following structure: ['email'=>(string),'name'=>(optional string)
     *      * cc string|array [optional] email/s to send the email in cc. If it is string the emails has to be separated by ','. If it is an array it has to be an array
     *           of objects with the following structure: ['email'=>(string),'name'=>(optional string)
     *      * bcc string [optional] email to send a copy in bcc
     *      * reply_to string [optional] email to redirect the replies of the email
     *      * data array [optional] array of objects [key=>value] to be sent as variables to merge with the template
     *      * tags array [optional] array tags to add to the email [tag1,tag2..]
     *      * preserve_recipients boolean if it is true then the email will preserve the recipients headers instead to appear emails separated
     *      * important [optional] boolean if it is true then the email will send 'important' attribute
     *      * async [optional] boolean if it is true then the email will be sent asynchronously
     *      * attachments array [optional] array objects to be sent as attachments. Format of each object: ['type'=>'{mime-type}(example:application/pdf)','name'=>'{filename}(example:file.pdf)','content'=>base64_encode({file-content})];
     * }
     * @return array the array will contain 'success' with true or false value.
     */
    public function sendMandrillEmail(array &$params) {

        if(!$this->mandrill->getApiKey()) return $this->addError('Missing Mandrill API_KEY. use function setMandrillApiKey($pau_key)');
        $html =  $params['html']??null;
        if(!($slug = $params['slug']??null) && !$html) return $this->addError('sendMandrillEmail($params) missing [slug] or [html] in $params for the Body of the Email');

        if($slug)
            if(!$template = $this->getMandrillTemplate($slug)) return;
        else $template = $html;

        if(!$from = $params['from']??($template['DefaultFromEmail']??null)) return $this->addError('sendMandrillEmail($params) missing email in $params because the template does not have a default from email');
        if(!$subject = $params['subject']??($template['DefaultSubject']??null)) return $this->addError('sendMandrillEmail($params) missing subject in $params because the template does not have a default subject');
        $from_name = $params['name']??($template['from_name']??'');

        if(!$emails_to = $params['to']??null) return $this->addError('sendMandrillEmail($params) missing to in $params');
        if(!is_array($emails_to)) $emails_to = array_filter(explode(',',$emails_to));

        $emails_cc = $params['cc']??[];
        if($emails_cc && !is_array($emails_cc)) $emails_cc = array_filter(explode(',',$emails_cc));

        $email_bcc = $params['bcc']??null;
        if($email_bcc && is_array($email_bcc)) $email_bcc = implode(',',$email_bcc);

        $reply_to = $params['reply_to']??null;
        $email_data= $params['data']??[];
        if(!is_array($email_data)) $email_data=[];
        $tags = $params['tags']??[];
        if(!is_array($tags)) $tags=explode(',',$tags);

        $important = ($params['important']??null)?true:false;


        $headers = [];
        if($reply_to && $this->core->is->validEmail($reply_to))
            $headers['Reply-To'] =$reply_to;

        try {
            $message = array(
                'subject' => $subject,
                'from_email' => $from,
                'from_name' => $from_name,
                'headers' => $headers,
                'important' => $important,
                'track_opens' => null, //true by default
                'track_clicks' => null, //true by default
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
            if(!$slug)
                $message['html'] = $html;

            // It will preserve to: emails, and cc: emails to the receivers
            if($params['preserve_recipients']??null)
                $message['preserve_recipients'] = true;  // It shows in the email the to: emails and cc: emails


            //region to: into $message['to']
            $message['to'] = [];
            foreach ($emails_to as $email_to) {
                if(is_array($email_to)) {
                    if(!($email_to['email']??null)) {
                        $this->addError('sendMandrillEmail($params) Wrong $params["to"] array. Missing email attribute');
                        return ['success'=>false,'result'=>'sendMandrillEmail($params) Wrong $params["to"] array. Missing email attribute'];
                    }
                    $message['to'][] = ['email' => $email_to['email'], 'name' => $email_to['name'] ?? $email_to['email'], 'type' => 'to'];
                } else
                    $message['to'][] = ['email'=>$email_to,'name'=> $email_to,'type'=>'to'];
            }
            //endregion

            //region cc: into $message['to']
            foreach ($emails_cc as $email_cc) {
                if(is_array($email_cc)) {
                    if(!($email_cc['email']??null)) {
                        $this->addError('sendMandrillEmail($params) Wrong $params["to"] array. Missing email attribute');
                        return ['success'=>false,'result'=>'sendMandrillEmail($params) Wrong $params["to"] array. Missing email attribute'];

                    }
                    $message['to'][] = ['email' => $email_cc['email'], 'name' => $email_cc['name'] ?? $email_cc['email'], 'type' => 'cc'];
                } else
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
            $async = ($params['async']??null)?true:false;
            $ip_pool = 'Main Pool';
            $body = [
                'template_name'=>$slug,
                'template_content'=>$template_content,
                'message'=>$message,
                'async'=>$async,
                'ip_pool'=>$ip_pool,
            ];
            if($slug) {
                $body['template_name'] = $slug;
                $body['template_content'] = $template_content;
                $result = $this->mandrill->messages->sendTemplate($body);
            } else {
                $result = $this->mandrill->messages->send($body);
            }

            //$result = $this->mandrill->messages->sendTemplate($slug, $template_content, $message, $async, $ip_pool);
            $result = $this->core->jsonDecode($this->core->jsonEncode($result),true);

            return ['success'=>true,'result'=>$result];
        } catch (Error $e) {
            return ['success'=>false,'result'=>$e->getMessage()];
        }
    }


    /**
     * Add an error in the class
     * @param $value
     * @return false to facilitate the return or other functions
     */
    function addError($value)
    {
        $this->error = true;
        $this->errorMsg[] = $value;
        return false;
    }

    /**
     * Return an array with the structure of ds:CloudFrameWorkEmailTemplates taking Mandrill $template array info
     * @param array $entity
     * @param array $template
     * @return array value of $entity modified with template variables
     */
    private function getEntityTransformedWithMandrillTemplateData(array $entity, array $template) {

        $entity['KeyName']=$template['slug'];
        $entity['TemplateDescription']=$template['name'];
        $entity['Labels']=$template['labels'];
        $entity['TemplateAcive']=true;
        $entity['DefaultSubject']=$template['publish_subject'];
        $entity['DefaultFromEmail']=$template['publish_from_email'];
        $entity['DefaultFromName']=$template['publish_from_name'];
        $entity['PublishedDate']=$template['published_at'];
        $entity['TemplateUpdatedAt']=substr($template['updated_at']??'',0,19);
        if(!isset($entity['DateInsertion']))
            $entity['DateInsertion']=$template['created_at'];
        $entity['DateUpdating']="now";
        $entity['Type']='Mandrill';
        $entity['TemplateHTML']=$this->core->utf8Encode($template['publish_code']??'');
        $entity['TemplateTXT']=$this->core->utf8Encode($template['publish_text']??'');
        $entity['TemplateURL']="https://mandrillapp.com/templates/code?id=".urlencode($template['slug']);
        $entity['TemplateVariables']=[];

        //region extract Variables
        $code = $this->core->utf8Encode($template['publish_code']??'');
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

        if($message['to'])
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
        $entity["BODY_HTML"]=$this->core->utf8Encode($message['html']);
        $entity["BODY_TXT"]=$this->core->utf8Encode($message['text']);
        $entity["DateProcessing"]=date('Y-m-d H:i:s',$message['ts']);
        $entity["UpdateProcessing"]=$update_processing;
        $entity["StatusProcessing"]=$info['state']??'unknown';
        $entity["JSONProcessing"]['Result']=['email'=>$message['to'],"status"=>($info['state']??'unknown'),'_id'=>$message['_id']];
        $entity["JSONProcessing"]['Info']=$info;
        return $entity;
    }

}