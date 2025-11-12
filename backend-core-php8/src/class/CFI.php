<?php
/**
 * [$cfi = $this->core->loadClass('CFI');] Class CFI to handle CFO apps for CloudFrameworkInterface
 * https://cloudframework.io/docs/es/cfis/aplicaciones-low-code
 * last_update: 20240822
 * @package CoreClasses
 */
class CFI
{
    private $version = '20241016';
    /** @var Core7 $core */
    var $core;
    private $fields = [];
    private $buttons = [];

    var $json_object=['title'=>'Pending'
        ,'allow_copy'=>false
        ,'allow_delete'=>false
        ,'allow_display'=>false
        ,'allow_update'=>false
        ,'tabs'=>[]
        ,'fields'=>[]
        ,'buttons'=>[]
        ,'close'=>'Cancel'
    ];

    /**
     * CFI constructor.
     * @param Core7 $core
     * @param string $bucket
     */
    function __construct (Core7 &$core, $bucket='')
    {
        $this->core = $core;
        $this->initApp('Set a title for the CFO');
    }

    /**
     * Init a CFI app
     * @param $title
     */
    public function initApp($title) {
        $this->json_object=[
            'title'=>$title,
            'allow_copy'=>false,
            'allow_delete'=>false,
            'allow_display'=>false,
            'allow_update'=>false,
            'tabs'=>[],
            'fields'=>[],
            'buttons'=>[],
            'close'=>'Cancel'
        ];
        $this->json_object['title']=$title;
        $this->fields = [];
    }

    /**
     * Return the App structure: $this->json_object
     */
    public function returnData(){return $this->getApp();}
    public function getApp() { return $this->json_object;}

    /**
     * Change title of the App
     * @param $title
     */
    public function setTile($title) {$this->json_object['title']=$title;}

    /**
     * Add required_reload element in the return to tell CFO to reload the list
     * @param bool $required
     */
    public function requireReloadOnReturn(bool $required=true) {$this->json_object['required_reload']=$required;}

    /**
     * Change title of the App
     * @param $title
     */
    public function addTab($title,$icon='home',bool|null $active=null) {
        $index = count($this->json_object['tabs']);
        if($active===null) $active=($index==0?true:false);
        $this->json_object['tabs'][]=['title'=>$title,'ico'=>$icon,'active'=>$active,'index'=>$index];
    }

    /**
     * @param $field
     * @return CFIField
     */
    private function getField($field) {
        if(!isset($this->fields[$field])) $this->fields[$field] = new CFIField($this, $field);
        return $this->fields[$field];
    }

    /**
     * Return a CFIField field
     * @param $field
     * @return CFIField
     */
    public function field($field) { return $this->getField($field);}

    /**
     * Delete a field
     * @param $field
     */
    public function delete($field) { if(isset($this->fields[$field])) unset($this->fields[$field]); if(isset($this->json_object['fields'][$field])) unset($this->json_object['fields'][$field]);}

    /**
     * Internal method to return a button
     * @param $button
     * @param string $align where to show the button in the bottom: right or left
     * @return CFIButton
     */
    private function getButton($button) {
        if(!isset($this->buttons[$button])) $this->buttons[$button] = new CFIButton($this, $button);
        return $this->buttons[$button];
    }

    /**
     * Return a CFIButton $button
     * @param $button_title
     * @param string $align where to show the button in the bottom: right or left
     * @return CFIButton
     */
    public function button($button_title='Button',string $align='right') {
        $button = $this->getButton($button_title);
        if($align) $button->align($align);
        return $button;
    }

    /**
     * set the title for close button
     * @param $title
     */
    public function closeButton($title) { $this->json_object['close']=$title;}

    /**
     * Add required_reload element in the return to tell CFO to reload the list
     * @param bool $reload default is true
     */
    public function reloadCFO(bool $reload=true) { $this->json_object['required_reload'] = $reload;}

    /**
     * Change All the fields fields to readonly
     * @param array $fields array of fields to change. If empty it will change every field.
     */
    public function changeFieldsToReadOnly(array $fields = []) {
        if(!$fields) $fields = array_keys($this->fields);
        foreach ($fields as $field) if(isset($this->fields[$field])){
            $this->fields[$field]->readOnly();
        }
    }
    /**
     * Change All the fields fields to readonly
     * @param array $fields array of fields to change. If empty it will change every field.
     */
    public function changeFieldsToDisabled(array $fields = []) {
        if(!$fields) $fields = array_keys($this->fields);
        foreach ($fields as $field) if(isset($this->fields[$field])){
            $this->fields[$field]->disabled();
        }
    }
}

/*
 * Class to handle fields in CFI
 * last_update: 20200502
 */
class CFIField {

    /** @var CFI $cfi */
    private $cfi;
    private $field;
    var $object;


    /**
     * CFI constructor.
     * @param CFI $cfi
     * @param string $field
     */
    function __construct (CFI &$cfi, string $field)
    {
        $this->cfi = $cfi;
        $this->field = $field;
        $this->cfi->json_object['fields'][$this->field] = ['field'=>$field];
    }

    /**
     * Set a value for the field
     * @param $value
     * @return CFIField $this
     */
    public function value($value) {
        $this->cfi->json_object['fields'][$this->field]['value'] = $value;
        $this->cfi->json_object['fields'][$this->field]['defaultvalue'] = $value; return $this;
    }

    /**
     * Set a date type
     * @param $value
     * @return CFIField $this
     */
    public function date($title='') {
        $this->cfi->json_object['fields'][$this->field]['type'] = 'date';
        if($title)
            $this->cfi->json_object['fields'][$this->field]['name'] = $title;
        return $this;
    }

    /**
     * Set a title for the field
     * @param $title
     * @return CFIField $this
     */
    public function title($title) { $this->cfi->json_object['fields'][$this->field]['name'] = $title; return $this;}


    /**
     * Set a ecn reference to show a help
     * @param string $route id of the ecm page
     * @param string $icon optional to indicate what type of icon to show. default: question-circle
     * @return CFIField $this
     */
    public function ecmHelp(string $route,string $icon='question-circle') {
        $this->cfi->json_object['fields'][$this->field]['ecm'] = $route;
        if($icon) $this->cfi->json_object['fields'][$this->field]['ecm_icon'] = $icon;
        return $this;}

    /**
     * Set a placeholder for the field
     * @param $title
     * @return CFIField $this
     */
    public function placeHolder($title) { $this->cfi->json_object['fields'][$this->field]['placeholder'] = $title; return $this;}

    /**
     * Set a title for the field
     * @param int $n_tab Number of tab the field has to be shown 0..n
     * @return CFIField $this
     */
    public function tab(int $n_tab) {
        $n_tab = intval($n_tab);
        if(isset($this->cfi->json_object['tabs'][$n_tab]))
            $this->cfi->json_object['fields'][$this->field]['tab'] = $n_tab;
        return $this;
    }

    /**
     * Set if the field to readonly
     * @param boolean $read_only optional params. By default true
     * @return CFIField $this
     */
    public function readOnly($read_only=true) { $this->cfi->json_object['fields'][$this->field]['read_only'] = $read_only; return $this;}

    /**
     * Set if we will create a new row after he field
     * @param boolean $new_row optional params. By default true
     * @return CFIField $this
     */
    public function newRow($new_row=true) { $this->cfi->json_object['fields'][$this->field]['new_row'] = $new_row; return $this;}

    /**
     * Set if we will create a new row after he field
     * @param boolean $allow_empty optional params. By default true
     * @return CFIField $this
     */
    public function allowEmpty($allow_empty=true) { $this->cfi->json_object['fields'][$this->field]['allow_empty'] = $allow_empty; return $this;}

    /**
     * Set if the field to disabled and it will not be sent in the form submit
     * @param boolean $read_only optional params. By default true
     * @return CFIField $this
     */
    public function disabled($disabled=true) { $this->cfi->json_object['fields'][$this->field]['disabled'] = $disabled; return $this;}

    /**
     * Set if the field is virtual
     * @param boolean $virtual optional params. By default true
     * @return CFIField $this
     */
    public function virtual($virtual=true) { $this->cfi->json_object['fields'][$this->field]['virtual'] = $virtual; return $this;}

    /**
     * Set if the field has to be represented as an image
     * @param bool $image
     * @param int $image_with_pixels
     * @param int $image_height_pixels
     * @return CFIField $this
     */
    public function image($image=true,int $image_with_pixels=0,int $image_height_pixels=0) {
        $this->cfi->json_object['fields'][$this->field]['image'] = $image;
        if($image_with_pixels) $this->cfi->json_object['fields'][$this->field]['image_width'] = $image_with_pixels;
        if($image_height_pixels) $this->cfi->json_object['fields'][$this->field]['image_height'] = $image_height_pixels;

        return $this;
    }
    /**
     * Set and external_values field
     * @param string $api Url to send the query..
     *     'It normally starts with /cfi/<cfo-name>?fields=<fields-to-show-separated-by-comma>
     * @param string $fields Fields to show
     * @return CFIField $this
     */
    public function apiValues(string $api,string $fields='',int $limit=10,bool $datastore_search=false) {
        $this->cfi->json_object['fields'][$this->field]['type'] = 'api_select_partial';
        if($fields && !strpos($api,'fields=')) {
            $api.= ((!strpos($api,'?'))?'?':'&').'fields='.$fields;
        }
        if($limit && !strpos($api,'server_limit==')) {
            $api.= ((!strpos($api,'?'))?'?':'&').'server_limit='.$limit;
        }
        if($datastore_search && !strpos($api,'_search=')) {
            $api.= ((!strpos($api,'?'))?'?':'&').'_search=1';
        }
        $this->cfi->json_object['fields'][$this->field]['api'] = $api;
        return $this;
    }
    /**
     * Set and external_values field
     * @param string $api Url to send the query..
     *     'It normally starts with /cfi/<cfo-name>?fields=<fields-to-show-separated-by-comma>
     * @param string $fields Fields to show
     * @return CFIField $this
     */
    public function CFOExternalValues(string $cfo,string $fields='',int $limit=10,bool $_search=false,string $id_field='',array $vars=[]) {
        if(strpos($cfo,'/')===false) $cfo = "/core/cfo/cfi/{$cfo}/fields";
        if(strpos($cfo,'http')===false) $cfo = "https://api.cloudframework.io{$cfo}";
        //if(strpos($cfo,'http')===false) $cfo = "http://localhost:9999{$cfo}";
        $cfo.=(strpos($cfo,'?')===false)?'?':'&';
        if($fields) $cfo.="fields=".urlencode($fields).'&';
        if($id_field) $cfo.="id_field=".urlencode($id_field).'&';
        if($limit>0) $cfo.="server_limit=".urlencode($limit).'&';
        if($_search) $cfo.="_search=1&";
        if($vars) foreach ($vars as $var_key=>$var_value) {
            $cfo.="{$var_key}=".urlencode($var_value).'&';
        }
        $this->cfi->json_object['fields'][$this->field]['type'] = 'api_select_partial';
        $this->cfi->json_object['fields'][$this->field]['api'] = $cfo;
        return $this;
    }

    /**
     * Set if the field has to be represented as an image
     * @param bool $image
     * @param int $image_with_pixels
     * @param int $image_height_pixels
     * @return CFIField $this
     */
    public function addExternalAPI($title,$url,$method='POST',$js_condition=null) {

        $this->cfi->json_object['fields'][$this->field]['type'] = 'virtual';
        $this->cfi->json_object['fields'][$this->field]['name'] = ' ';

        if(!isset($this->cfi->json_object['fields'][$this->field]['external_apis']) || !is_array($this->cfi->json_object['fields'][$this->field]['external_apis'])) $this->cfi->json_object['fields'][$this->field]['external_apis'] = [];
        $external = ['title'=>$title,'url'=>$url,'method'=>$method];
        if($js_condition) $external['js_condition'] = $js_condition;
        $external['submit_form'] = true;
        $this->cfi->json_object['fields'][$this->field]['external_apis'][] = $external;


        return $this;
    }


    /**
     * Set if the field has to be represented as an image
     * @param bool $image
     * @param int $image_with_pixels
     * @param int $image_height_pixels
     * @return CFIFieldButton $this
     */
    public function button($title,$url='',$method='POST',$js_condition=null)
    {
        if(!isset($this->cfi->json_object['fields'][$this->field])) $this->cfi->json_object['fields'][$this->field] =[];
        if(!key_exists('name',$this->cfi->json_object['fields'][$this->field])) $this->cfi->json_object['fields'][$this->field]['name'] = '';

        if(!is_object($this->object) || get_class($this->object) != 'CFIFieldButton') {
            $this->object = new CFIFieldButton($this->cfi->core, $this->cfi->json_object['fields'][$this->field]);
        }
        $this->object->title($title);
        if($url) $this->object->url($url);
        return $this->object;

    }


    /**
     * Set if the field has to be represented as an image
     * @param bool $image
     * @param int $image_with_pixels
     * @param int $image_height_pixels
     * @return CFIFieldButton $this
     */
    public function virtualElements($title='')
    {
        if(!isset($this->cfi->json_object['fields'][$this->field])) $this->cfi->json_object['fields'][$this->field] =[];
        if(!is_object($this->object) || get_class($this->object) != 'CFIVirtualElements') {
            $this->object = new CFIVirtualElements($this->cfi->core, $this->cfi->json_object['fields'][$this->field]);
        }
        if($title) $this->object->title($title);
        return $this->object;

    }

    /**
     * Set if the field to type json
     * @param string $title optional title
     * @return CFIField $this
     */
    public function json($title='') {
        $this->cfi->json_object['fields'][$this->field]['type'] = 'json';
        if($title) $this->cfi->json_object['fields'][$this->field]['name'] = $title;
        return $this;
    }

    /**
     * prepend_icon to be shown in the field
     * @param string $icon icon with fa- prefix. Example: fa-users
     * @return CFIField $this
     */
    public function leftIcon($icon='') {
        $this->cfi->json_object['fields'][$this->field]['prepend_icon'] = $icon;
        return $this;
    }

    /**
     * append_icon to be shown in the field
     * @param string $icon icon with fa- prefix. Example: fa-users
     * @return CFIField $this
     */
    public function rightIcon($icon='') {
        $this->cfi->json_object['fields'][$this->field]['append_icon'] = $icon;
        return $this;
    }

    /**
     * Set if the field to type textarea
     * @param string $title optional title
     * @return CFIField $this
     */
    public function textarea($title='') {
        $this->cfi->json_object['fields'][$this->field]['type'] = 'textarea';
        if($title) $this->cfi->json_object['fields'][$this->field]['name'] = $title;
        return $this;}


    /**
     * Set if the field to type html
     * @param string $title optional title
     * @return CFIField $this
     */
    public function html($title='') {
        $this->cfi->json_object['fields'][$this->field]['type'] = 'html';
        if($title) $this->cfi->json_object['fields'][$this->field]['name'] = $title;
        return $this;
    }

    /**
     * Set if the field to type boolean
     * @param string $title optional title
     * @return CFIField $this
     */
    public function boolean($title='') {
        $this->cfi->json_object['fields'][$this->field]['type'] = 'boolean';
        if($title) $this->cfi->json_object['fields'][$this->field]['name'] = $title;
        return $this;
    }

    /**
     * Set a checkbox field with values and default value
     *
     * @param array $values An array of values for the checkbox
     * @param array|string $defaultvalue (optional) Default value(s) for the checkbox, defaults to an empty array
     *
     * @return CFIField The CFIField instance
     */
    public function checkbox(array $values,array|string $value=[]) {
        $this->cfi->json_object['fields'][$this->field]['type'] = 'checkbox';
        $this->cfi->json_object['fields'][$this->field]['values'] = $values;
        if($value)
            $this->cfi->json_object['fields'][$this->field]['value'] = $value;
        return $this;
    }

    /**
     * Set if the field to type select
     * @return CFIField $this
     */
    public function select(array $values,$defaultvalue='') {
        $this->cfi->json_object['fields'][$this->field]['type'] = 'select';
        $this->cfi->json_object['fields'][$this->field]['values'] = $values;
        if($defaultvalue)
            $this->cfi->json_object['fields'][$this->field]['defaultvalue'] = $defaultvalue;
        return $this;
    }

    /**
     * Set if the field to type autocomplete
     * @return CFIField $this
     */
    public function autocomplete(array $values,$defaultvalue='',$allow_add=false) {
        $this->cfi->json_object['fields'][$this->field]['type'] = 'autocomplete';
        $this->cfi->json_object['fields'][$this->field]['values'] = $values;
        $this->cfi->json_object['fields'][$this->field]['defaultvalue'] = $defaultvalue;
        if($allow_add)
            $this->cfi->json_object['fields'][$this->field]['allow_add'] = true;
        return $this;
    }

    /**
     * Set if the field to type select
     * @return CFIField $this
     */
    public function link(string $url='') {
        $this->cfi->json_object['fields'][$this->field]['type'] = 'virtual';
        $this->cfi->json_object['fields'][$this->field]['link'] = true;
        if($url) {
            if(!($this->cfi->json_object['fields'][$this->field]['value']??null))
                $this->cfi->json_object['fields'][$this->field]['value'] = $url;
            else
                $this->cfi->json_object['fields'][$this->field]['link_content'] = $url;
        }
        return $this;
    }

    /**
     * Set if the field to type iframe
     * @param $height integer optinal iframe height: default 400
     * @return CFIField $this
     */
    public function iframe(int $height=400,string $url='',string $content='') {
        $this->cfi->json_object['fields'][$this->field]['type'] = 'iframe';
        $this->cfi->json_object['fields'][$this->field]['iframe_height'] = $height;
        if($url) $this->cfi->json_object['fields'][$this->field]['iframe_url'] =$url;
        if($content) $this->cfi->json_object['fields'][$this->field]['iframe_content'] =$content;
        return $this;
    }


    /**
     * Set if the url for certain types like iframe
     * @param $value
     * @return CFIField $this
     */
    public function url($value) {
        if($this->cfi->json_object['fields'][$this->field]['type'] = 'iframe') {
            $this->cfi->json_object['fields'][$this->field]['iframe_url'] =$value;
        } else {
            $this->cfi->json_object['fields'][$this->field]['url'] =$value;
        }
        return $this;
    }

    /**
     * Set if the url for certain types like iframe
     * @param $value string content to be included in the iframe.Normally a HTML
     * @return CFIField $this
     */
    public function content($value) {
        if($this->cfi->json_object['fields'][$this->field]['type'] = 'iframe') {
            $this->cfi->json_object['fields'][$this->field]['iframe_content'] =$value;
        }
        return $this;
    }

    /**
     * Set how-many cols will be set for the field
     * @param float $n_cols number of columns to feed: 1,1.5.,2,3
     * @return CFIField $this
     */
    public function cols(float $n_cols) {
        $n_cols = (!in_array(floatval($n_cols),[1,2,3,1.5]))?1:floatval($n_cols);
        $class = "";
        if($n_cols==1.5) $class = "col col-6 ";
        elseif($n_cols==2) $class = "col col-9 ";
        elseif($n_cols==3) $class = "col col-12 ";
        $this->cfi->json_object['fields'][$this->field]['section_class'] = $class;
        return $this;
    }

    /**
     * Set a header before de field for a better structure of the information.
     * @param string $title
     * @param string $tag
     * @param string $class
     * @param string $style
     * @return CFIField $this
     */
    public function header(string $title,$tag='h1',string $class='_alert-success',string $style='') {
        $this->cfi->json_object['fields'][$this->field]['header']['title'] = $title;
        $this->cfi->json_object['fields'][$this->field]['header']['tag'] = $tag;
        $this->cfi->json_object['fields'][$this->field]['header']['class'] = $class;
        $this->cfi->json_object['fields'][$this->field]['header']['style'] = $style;
        return $this;
    }

    /**
     * Set a header before de field for a better structure of the information.
     * @param string $title
     * @param string $tag
     * @param string $class
     * @param string $style
     * @return CFIField $this
     */
    public function cfo(string $cfo,$id_field='',$api='',$update=false) {
        $this->cfi->json_object['fields'][$this->field]['cfo'] = $cfo;
        if($id_field) $this->cfi->json_object['fields'][$this->field]['id_field'] = $id_field;
        if($api) $this->cfi->json_object['fields'][$this->field]['api'] = $api;
        if($update) $this->cfi->json_object['fields'][$this->field]['update_cfo'] = true;
        else $this->cfi->json_object['fields'][$this->field]['display_cfo'] = true;
        return $this;
    }


    /**
     * Set if the field to type html
     * @param string $bucket optional bucket path as base of files
     * @param string $folder optional folder to add to $folder. It shouldn't starts with '/'
     * @return CFIServerDocuments $this->object
     */
    public function serverDocuments(string $bucket='',string $folder='') {
        if(!isset($this->cfi->json_object['fields'][$this->field])) $this->cfi->json_object['fields'][$this->field] =[];
        if(!is_object($this->object) || get_class($this->object) != 'CFIServerDocuments')
            $this->object = new CFIServerDocuments($this->cfi->core, $this->cfi->json_object['fields'][$this->field]);
        if($bucket)
            $this->object->bucket($bucket);
        if($folder)
            $this->object->folder($folder);
        return $this->object;
    }

    /**
     * Set if the field to type html
     * @param string $bucket bucket gs://path as base of public files
     * @param string $title optional folder to add to $folder. It shouldn't starts with '/'
     * @return CFIServerDocuments $this->object
     */
    public function publicImage(string $bucket='',string $title='') {
        if(!isset($this->cfi->json_object['fields'][$this->field])) $this->cfi->json_object['fields'][$this->field] =[];
        if($title) $this->cfi->json_object['fields'][$this->field]['name'] = $title;
        if(!is_object($this->object) || get_class($this->object) != 'CFIPublicImage')
            $this->object = new CFIPublicImage($this->cfi->core, $this->cfi->json_object['fields'][$this->field]);
        if($bucket)
            $this->object->bucket($bucket);
        if($title)
            $this->object->title($title);
        return $this->object;
    }

    /**
     * Add onchange property to the field
     * @return CFIField $this
     */
    public function onchange(string $js) {
        $this->cfi->json_object['fields'][$this->field]['onchange'] = $js;
        return $this;
    }

    /**
     * Add onclick property to the field
     * @return CFIField $this
     */
    public function onclick(string $js) {
        $this->cfi->json_object['fields'][$this->field]['onclick'] = $js;
        return $this;
    }
}

/*
 * Class to handle field type server_documents
 * https://cloudframework.io/docs/es/development/modelos-de-datos/cfos/05-tipo-de-campos/server_documents/
 * last_update: 20200502
 */
class CFIServerDocuments
{
    var $field;
    /** @var Core7 $core */
    private $core;

    /**
     * CFI constructor.
     * @param array $field
     */
    function __construct(Core7 &$core,array &$field)
    {
        $this->core = $core;
        $this->field = &$field;
        $this->field['type']='server_documents';
    }

    /**
     * Assign the bucket where to store the files. IT should starts with gs:// ot it will add it to $bucket
     * @param string $buket
     * @return CFIServerDocuments $this
     */
    public function bucket(string $buket) {
        if(strpos($buket,'gs://')!==0) $buket="gs://{$buket}";
        $this->field['bucket']=$buket;
        return $this;
    }

    /**
     * Process an array of files with the structure of CloudFrameWorkDocuments
     * and add it as documents in the field
     * @param array $files
     * @return CFIServerDocuments $this
     */
    public function processCloudFrameworkDocuments(array $files) {
        foreach ($files as $file) {
            $doc = [
                'id'=>$file['KeyName']??null,
                'date'=>$file['DateInsertion']??null,
                'name'=>$file['doc_name']??null,
                'size'=>$file['doc_size']??null,
                'file_type'=>$file['doc_filetype']??null,
                'url'=>$file['doc_url']??null,
                'url_delete'=>$file['doc_url']??null,
                'url_edit'=>$file['doc_url']??null,
            ];
            $this->addDocument($doc);
        }
        return $this;
    }


    /**
     * Assign default values to the document definitions
     * @param array $doc Document structure
     *  $doc['id'] Id of the document
     *  $doc['date'] Date of creation
     *  $doc['name'] Name of the document
     *  $doc['size'] Size of the document
     *  $doc['file_type'] File type of the document
     *  $doc['url'] URL to download the document
     *  $doc['url_delete'] URL to delete the document
     *  $doc['url_edit'] URL to update properties of the document
     * @return CFIServerDocuments $this
     */
    public function addDocument(array $doc) {
        if(!isset($this->field['uploaded_documents']))
            $this->field['uploaded_documents']=[];
        $doc = [
            'id'=>$doc['id']??null,
            'date'=>$doc['date']??null,
            'name'=>$doc['name']??null,
            'size'=>$doc['size']??null,
            'file_type'=>$doc['file_type']??null,
            'url'=>$doc['url']??null,
            'url_delete'=>$doc['url_delete']??null,
            'url_edit'=>$doc['url_edit']??null,
        ];
        $this->field['uploaded_documents'][] = $doc;
        return $this;
    }

    /**
     * Assign the folder where to store the files inside $this->field['bucket']
     * @param string $folder
     * @return CFIServerDocuments $this
     */
    public function folder(string $folder) {$this->field['folder']=$folder;return $this;}


    /**
     * Add bigFiles property to the field to allow Big Upload Files
     * @param string $path It has to start with '/path'
     * @return CFIServerDocuments $this
     */
    public function bigFilesPath(string $path) {$this->field['bigFiles']=$path;return $this;}

    /**
     * It says if it allows multiple files
     * @param bool $allow_multiple
     * @return CFIServerDocuments $this
     */
    public function multiple(bool $allow_multiple=true) {$this->field['multiple']=$allow_multiple;return $this;}

    /**
     * Set the allow_delete field value of the object.
     *
     * @param bool $allow Indicates whether deletion is allowed or not. Default value is true.
     * @return CFIServerDocuments $this
     */
    public function allowDelete(bool $allow=true) {$this->field['allow_delete']=$allow;return $this;}

    /**
     * Set the allow_edit field value of the object.
     *
     * @param bool $allow Indicates whether file name edit is allowed or not. Default value is true.
     * @return CFIServerDocuments $this
     */
    public function allowEdit(bool $allow=true) {$this->field['allow_edit']=$allow;return $this;}

    /**
     * Set the allow_view field value of the object.
     *
     * @param bool $allow Indicates whether view file is allowed or not. Default value is true.
     * @return CFIServerDocuments $this
     */
    public function allowView(bool $allow=true) {$this->field['allow_view']=$allow;return $this;}

    /**
     * Read documents from a specific Cloud Framework Object (CFO) and add them to the specified field.
     *
     * @param string $cfo The name of the Cloud Framework Object (CFO).
     * @param string $field The name of the field to add the documents to.
     * @param string $id The ID of the specific document.
     * @param string $url The URL to the Cloud Framework API endpoint. Default is 'https://api.cloudframework.io/core/cfo/cfi'.
     * @param array $headers Additional headers to be sent with the API request. By Default is an empty array so it will send X-WEB-KEY and X-DS-TOKEN from headers if it exists
     */
    public function readDocsFromCFO(string $cfo,string $field,string $id, $url = 'https://api.cloudframework.io/core/cfo/cfi',array $headers=[]) {

        //region SET $url_to_get_docs_from_cfo, $endpoint_to_get_url_for_uploading
        $url_to_get_docs_from_cfo = $url.'/'.urlencode($cfo).'/documents/'.urlencode($id).'/'.urlencode($field);
        $endpoint_to_get_url_for_uploading = $url.'/'.urlencode($cfo).'/documents_url_to_upload/'.urlencode($id).'/'.urlencode($field).'?multiple='.($this->field['multiple']??false)?'true':'';
        //endregion

        //region SET attribute with $this->endPointToGetUrlForUploading
        $this->endPointToGetUrlForUploading($endpoint_to_get_url_for_uploading);
        //endregion

        //region VERIFY $headers and READ $docs from endpoint $url_to_get_docs_from_cfo
        if(!$headers) $headers = ['X-WEB-KEY'=>$this->core->system->getHeader('X-WEB-KEY'),'X-DS-TOKEN'=>$this->core->system->getHeader('X-DS-TOKEN')];
        $docs = $this->core->request->get_json_decode($url_to_get_docs_from_cfo,null,$headers);
        //endregion

        //region ADD $docs result in the field
        if($this->core->request->error) {
            $this->field['error_reading_files'] = $this->core->request->errorMsg;
            $this->core->request->reset();
        }
        else {
            foreach ($docs['data']['docs']??[] as $doc) {
                if(!($this->field['allow_delete']??null)) unset($doc['url_delete']);
                if(!($this->field['allow_edit']??null)) unset($doc['url_edit']);
                if(!($this->field['allow_view']??null)) unset($doc['url']);
                $this->addDocument($doc);
            }
        }
        //endregion
    }

    /**
     * It says what kind of files, or extensions will be accepted
     * @param string $files_type example: image/*,application/pdf,.psd
     * @return CFIServerDocuments $this
     */
    public function acceptedFiles(string $files_type) {$this->field['accepted_files']=$files_type;return $this;}


    /**
     * Method to tell what ENDPOINT to call for Uploading URL
     * @param string $url_to_upload ENDPOINT to get the URL to upload a file
     * @return CFIServerDocuments $this
     */
    public function endPointToGetUrlForUploading(string $url_to_upload) {$this->field['endpoint_to_get_url_for_uploading']=$url_to_upload;return $this;}

    /**
     * It says the max file size accepted (in bytes). By default is 5242880 bytes
     * @param int $size max file size in bytes example: 5242880
     * @return CFIServerDocuments $this
     */
    public function maxFileSize(int $size) {$this->field['max_file_size']=$size;return $this;}
}

/*
 * Class to handle field type public_image
 * https://cloudframework.io/docs/es/development/modelos-de-datos/cfos/05-tipo-de-campos/public_image/?_refresh_cache
 * last_update: 20200502
 */
class CFIFieldButton
{
    var $field;
    /** @var Core7 $core */
    private $core;
    private $index=0;

    /**
     * CFI constructor.
     * @param array $field
     */
    function __construct(Core7 &$core, array &$field)
    {
        $this->core = $core;
        $this->field = &$field;
        $this->field['type'] = 'virtual';
        $this->field['external_apis'] = [];
    }

    public function addButton($title) { $this->index++; $this->field['external_apis'][$this->index]['title']=$title;return $this;}
    public function title(string $title){ $this->field['external_apis'][$this->index]['title'] = $title;return $this;}
    public function method(string $method){ $this->field['external_apis'][$this->index]['method'] = $method;return $this;}
    public function url(string $url){ $this->field['external_apis'][$this->index]['url'] = $url;return $this;}
    public function color(string $color){ $this->field['external_apis'][$this->index]['color'] = $color;return $this;}
    public function ico(string $ico){ $this->field['external_apis'][$this->index]['ico'] = $ico;return $this;}
    public function submitForm(bool $bool=true){ $this->field['external_apis'][$this->index]['submit_form'] = $bool;return $this;}
    public function jsConfirm(string $msg){ $this->field['external_apis'][$this->index]['js_confirm'] = $msg;return $this;}
    public function onClick(string $onclick){ $this->field['external_apis'][$this->index]['onclick'] = $onclick;return $this;}
}

/*
 * Class to handle field type public_image
 * https://cloudframework.io/docs/es/development/modelos-de-datos/cfos/05-tipo-de-campos/public_image/?_refresh_cache
 * last_update: 20200502
 */
class CFIVirtualElements
{
    var $field;
    /** @var Core7 $core */
    private $core;
    private $index=0;

    /**
     * CFI constructor.
     * @param array $field
     */
    function __construct(Core7 &$core, array &$field)
    {
        $this->core = $core;
        $this->field = &$field;
        $this->field['type'] = 'virtual';
        $this->field['virtual_elements'] = [];
    }

    /**
     * Apply a title to the field
     * @param string $title
     * @return $this
     */
    public function title(string $title): static
    { $this->field['name'] = $title;return $this;}

    /**
     * The virtual element will be shown as a button
     * @param string $color optional color (default will be 'default') of the button: info, success, warning, default, primary, sencondary
     * @param string $icon optional icon
     * @return CFIVirtualElements
     */
    public function button(string $color='default',string $icon=''): static
    {
        $this->field['virtual_elements'][$this->index]['button'] = $color;
        if($icon) $this->field['virtual_elements'][$this->index]['ico'] = $icon;
        return $this;
    }

    /**
     * Javascript condition to be evaluated in frontend
     * @param string $js_condition js condition to be evaluated in the frontend
     * @return CFIVirtualElements
     */
    public function jsCondition(string $js_condition): static
    {
        $this->field['virtual_elements'][$this->index]['js_condition'] = $js_condition;
        return $this;
    }

    /**
     * Add a virtual_elements with type=calculate
     * @param string $field name of the field in the data
     * @param string $type type of calculation. Allowed values: 'count'
     * @return CFIVirtualElements
     */
    public function addTypeCalculate(string $field,string $type): static
    {
        $this->addType('calculate');
        $this->field['virtual_elements'][$this->index]['calculate'] = $type;
        $this->field['virtual_elements'][$this->index][$type] = $field;
        return $this;
    }

    /**
     * Add a virtual_elements with type=value
     * @param string $value value to apply. The value admits {{variable}} substitute strings
     * @return CFIVirtualElements
     */
    public function addTypeValue(string $value): static
    {
        $this->addType('value');
        $this->field['virtual_elements'][$this->index]['value'] = $value;
        return $this;
    }

    /**
     * Add a virtual_elements with type=onClick
     * @param string $onclick js value to be executed onClick
     * @return CFIVirtualElements
     */
    public function addTypeOnClick(string $onclick): static
    {
        $this->addType('onClick');
        $this->field['virtual_elements'][$this->index]['onClick'] = $onclick;
        return $this;
    }

    /**
     * add an attribute onClick on the currect virtual_element
     * @param string $onclick js value to be executed onClick
     * @return CFIVirtualElements
     */
    public function onClick(string $onclick): static
    {
        $this->field['virtual_elements'][$this->index]['onClick'] = $onclick;
        return $this;
    }

    /**
     * Add a virtual_elements with type=ico
     * @param string $icon icon the be used (without fal fa- prefix))
     * @return CFIVirtualElements
     */
    public function addTypeCheckbox(string $title,string $value,bool $checked = false,string $onClick=''): static
    {
        $this->addType('checkbox');
        if($value) $this->field['virtual_elements'][$this->index]['checkbox_title'] = $title;
        if($value) $this->field['virtual_elements'][$this->index]['checkbox_value'] = $value;
        if($value) $this->field['virtual_elements'][$this->index]['checkbox_checked'] = $checked;
        if($onClick) $this->field['virtual_elements'][$this->index]['checkbox_onclick'] = $onClick;
        return $this;
    }

    /**
     * Add a virtual_elements with type=ico
     * @param string $icon icon the be used (without fal fa- prefix))
     * @return CFIVirtualElements
     */
    public function addTypeIcon(string $icon): static
    {
        $this->addType('ico');
        $this->field['virtual_elements'][$this->index]['ico'] = $icon;
        return $this;
    }

    /**
     * add an attribute ico on the currect virtual_element
     * @param string $icon to be shown (without fal fa- prefix))
     * @return CFIVirtualElements
     */
    public function icon(string $icon): static
    {
        $this->field['virtual_elements'][$this->index]['ico'] = $icon;
        return $this;
    }

    /**
     * Add a virtual_elements with type=image
     * @param string $src url of the image
     * @return CFIVirtualElements
     */
    public function addTypeImage(string $src): static
    {
        $this->addType('image');
        $this->field['virtual_elements'][$this->index]['src'] = $src;
        return $this;
    }

    /**
     * Add a virtual_elements with type=image
     * @param string $src url of the image
     * @return CFIVirtualElements
     */
    public function addTypeAvatar(string $src): static
    {
        $this->addType('avatar');
        $this->field['virtual_elements'][$this->index]['src'] = $src;
        return $this;
    }

    /**
     * Apply an update type to virtual_elements with CFO information.
     *
     * @param string $cfo The CFO information to be added.
     * @param mixed $id_value The ID value for the update operation.
     * @param string $fields (Optional) Additional fields to be updated.
     *
     * @return CFIVirtualElements
     */
    public function addTypeUpdateCFO(string $cfo,mixed $id_value, string $fields=''): static
    {
        $this->addType('update');
        $this->field['virtual_elements'][$this->index]['cfo'] = $cfo;
        $this->field['virtual_elements'][$this->index]['id_value'] = $id_value;
        if($fields) $this->field['virtual_elements'][$this->index]['cfo_fields'] = $fields;
        return $this;
    }

    /**
     * Apply an update type to virtual_elements with CFO information.
     *
     * @param string $cfo The CFO information to be added.
     * @param mixed $id_value The ID value for the update operation.
     * @param string $fields (Optional) Additional fields to be updated.
     *
     * @return CFIVirtualElements
     */
    public function addTypeDisplayCFO(string $cfo,mixed $id_value, string $fields=''): static
    {
        $this->addType('display');
        $this->field['virtual_elements'][$this->index]['cfo'] = $cfo;
        $this->field['virtual_elements'][$this->index]['id_value'] = $id_value;
        if($fields) $this->field['virtual_elements'][$this->index]['cfo_fields'] = $fields;
        return $this;
    }

    /**
     * Add a virtual_elements with type=display
     * It allow to Display the current CFO
     * @param string $idField optinal field name to be used as Id of the entity. You can leave ot empty if it is ds:Object
     * @param string $fields optional fields separated by ',' to be send to the CFO to only represent those fields to be displayed
     * @return CFIVirtualElements
     */
    public function addTypeDisplay(string $idField='',string $fields=''): static
    {
        $this->addType('update');
        if($idField) $this->field['virtual_elements'][$this->index]['id_field'] = $idField;
        if($fields) $this->field['virtual_elements'][$this->index]['fields'] = '';
        return $this;
    }

    /**
     * Apply a type a virtual_elements
     * @param string $type type of virtual_element. Allowed values: 'calculate',
     * @return CFIVirtualElements
     */
    public function addType($type): static
    {
        if($this->field['virtual_elements'][$this->index]??null) $this->index++;
        $this->field['virtual_elements'][$this->index] = [];
        $this->field['virtual_elements'][$this->index]['type'] = $type;
        return $this;
    }

}
/*
 * Class to handle field type public_image
 * https://cloudframework.io/docs/es/development/modelos-de-datos/cfos/05-tipo-de-campos/public_image/?_refresh_cache
 * last_update: 20200502
 */
class CFIPublicImage
{
    var $field;
    /** @var Core7 $core */
    private $core;

    /**
     * CFI constructor.
     * @param array $field
     */
    function __construct(Core7 &$core,array &$field)
    {
        $this->core = $core;
        $this->field = &$field;
        $this->field['type']='public_image';
    }

    /**
     * Assign the bucket where to store the public files. IT should starts with gs:// ot it will add it to $bucket
     * @param string $buket
     * @return CFIPublicImage $this
     */
    public function bucket(string $buket) {
        if(strpos($buket,'gs://')!==0) $buket="gs://{$buket}";
        $this->field['bucket']=$buket;
        return $this;
    }

    /**
     * Set a title for the field
     * @param $title
     * @return CFIPublicImage $this
     */
    public function title($title) { $this->field['name'] = $title; return $this;}


    /**
     * Set a title for the field
     * @param bool $allowed
     * @return CFIPublicImage $this
     */
    public function allowEmpty(bool $allowed=true) { $this->field['allow_empty'] = $allowed; return $this;}

    /**
     * Set a title for the field
     * @param $value
     * @return CFIPublicImage $this
     */
    public function value($value) { $this->field['defaultvalue'] = $this->field['value'] = $value; return $this;}

    /**
     * Set a title for the field
     * @param bool $active
     * @return CFIPublicImage $this
     */
    public function zoom(bool $active=true) { $this->field['zoom'] = $active; return $this;}

    /**
     * Show the image instead the URL to the image
     * @param bool $active
     * @return CFIPublicImage $this
     */
    public function showAsImage(bool $active=true) { $this->field['image'] = $active; return $this;}

    /**
     * Show the image as an avatar in lists
     * @param bool $active
     * @return CFIPublicImage $this
     */
    public function showAvatar(bool $active=true) { $this->field['image'] = $active?:$this->field['image']; $this->field['image_type'] = $active?'avatar':null; return $this;}


}


/*
 * Class to handle buttons in CFI
 * last_update: 20200502
 */
class CFIButton {

    private $cfi;
    private $button;

    /**
     * CFI constructor.
     * @param Core7 $core
     * @param string $bucket
     */
    function __construct (CFI &$cfi, $button)
    {
        $this->cfi = $cfi;
        $this->button = $button;
        $this->cfi->json_object['buttons'][] = ['title'=>$button,'type'=>'form'];
        $this->button = &$this->cfi->json_object['buttons'][count($this->cfi->json_object['buttons'])-1];
    }

    /**
     * Set a value for the field
     * @param $value
     * @return CFIButton $this
     */
    public function title($title) { $this->button['title'] = $title; return $this;}

    /**
     * Set button color
     * @param $value
     * @return CFIButton $this
     */
    public function color($color) { $this->button['color'] = $color; return $this;}

    /**
     * Set button ico
     * @param $ico
     * @return CFIButton $this
     */
    public function ico($ico) { $this->button['ico'] = $ico; return $this;}

    /**
     * Set button align
     * @param $align
     * @return CFIButton $this
     */
    public function align($align) { $this->button['align'] = $align; return $this;}

    /**
     * Type of Button
     * @param string $type Type of button: form, api
     * @return CFIButton $this
     */
    public function type(string $type) {
        if(in_array($type,['form','api'])) $this->button['type'] = $type;
        return $this;
    }

    /**
     * Assign url and method for an API call
     * @param $url
     * @param string $method optinal var to assign the type of call: GET, POST, PUT, DELETE
     * @return CFIButton $this
     */
    public function url($url,$method='GET') { $this->button['method'] = strtoupper($method);$this->button['url'] = $url; return $this;}


    /**
     * Set button align
     * @param $align
     * @return CFIButton $this
     */
    public function onclick($js) {
        $this->button['js'] = $js; $this->button['type'] = 'onclick'; return $this;
    }


    /**
     * Assign url to call an external API without avoiding the form sending
     * @param $url
     * @param string $method optional var to assign the type of call: GET, POST, PUT, DELETE
     * @return CFIButton $this
     */
    public function apiUrl($url,$method='GET') {
        $this->button['method'] = strtoupper($method);
        $this->button['url'] = $url;
        $this->button['type'] = 'api';
        return $this;
    }

    /**
     * Assign url to call an external API without avoiding the form sending
     * @param $url
     * @param string $method optinal var to assign the type of call: GET, POST, PUT, DELETE
     * @return CFIButton $this
     */
    public function formUrl($url,$method='GET') {
        $this->button['method'] = strtoupper($method);$this->button['url'] = $url;
        $this->button['type'] = 'form';
        return $this;
    }

}