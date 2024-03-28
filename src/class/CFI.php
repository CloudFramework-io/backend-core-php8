<?php

/**
 * [$cfi = $this->core->loadClass('CFI');] Class CFI to handle CFO apps for CloudFrameworkInterface
 * https://cloudframework.io/docs/es/cfis/aplicaciones-low-code
 * last_update: 20240224
 * @package CoreClasses
 */
class CFI
{
    private $version = '20240224';
    private $core;
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
     * Change title of the App
     * @param bool $required
     */
    public function requireReloadOnReturn(bool $required=true) {$this->json_object['required_reload']=$required;}

    /**
     * Change title of the App
     * @param $title
     */
    public function addTab($title,$icon='home') {
        $index = count($this->json_object['tabs']);
        $this->json_object['tabs'][]=['title'=>$title,'ico'=>$icon,'active'=>($index==0?true:false),'index'=>$index];
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
    public function button($button_title='Button',string $align='right') { return $this->getButton($button_title);}

    /**
     * set the title for close button
     * @param $title
     */
    public function closeButton($title) { $this->json_object['close']=$title;}

    /**
     * Set if the CFO list view has to be reloaded
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

    private $cfi;
    private $field;
    var $object;


    /**
     * CFI constructor.
     * @param Core7 $core
     * @param string $bucket
     */
    function __construct (CFI &$cfi, $field)
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
        $this->cfi->json_object['fields'][$this->field]['value'] = $value; return $this;
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
    public function apiValues(string $api,string $fields) {
        $this->cfi->json_object['fields'][$this->field]['type'] = 'api_select_partial';
        $this->cfi->json_object['fields'][$this->field]['api'] = $api;
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
        $this->cfi->json_object['fields'][$this->field]['name'] = 'action';

        if(!isset($this->cfi->json_object['fields'][$this->field]['external_apis']) || !is_array($this->cfi->json_object['fields'][$this->field]['external_apis'])) $this->cfi->json_object['fields'][$this->field]['external_apis'] = [];
        $external = ['title'=>$title,'url'=>$url,'method'=>$method];
        if($js_condition) $external['js_condition'] = $js_condition;
        $this->cfi->json_object['fields'][$this->field]['external_apis'][] = $external;

        return $this;
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
        return $this;}

    /**
     * Set if the field to type boolean
     * @param string $title optional title
     * @return CFIField $this
     */
    public function boolean($title='') {
        $this->cfi->json_object['fields'][$this->field]['type'] = 'boolean';
        if($title) $this->cfi->json_object['fields'][$this->field]['name'] = $title;
        return $this;}

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
    public function iframe($height=400,$url='') {
        $this->cfi->json_object['fields'][$this->field]['type'] = 'iframe';
        $this->cfi->json_object['fields'][$this->field]['iframe_height'] = $height;
        if($url) $this->cfi->json_object['fields'][$this->field]['iframe_url'] =$url;
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
            $this->object = new CFIServerDocuments($this->cfi->json_object['fields'][$this->field]);
        if($bucket)
            $this->object->bucket($bucket);
        if($folder)
            $this->object->folder($folder);
        return $this->object;
    }
}

/*
 * Class to handle field type server_documents
 * https://cloudframework.io/documentation/public/es/objetos-de-datos-cfos/type/server_documents
 * last_update: 20200502
 */
class CFIServerDocuments
{
    var $field;

    /**
     * CFI constructor.
     * @param array $field
     */
    function __construct(array &$field)
    {
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
     * It says if it allows multiple files
     * @param bool $allow_multiple
     * @return CFIServerDocuments $this
     */
    public function multiple(bool $allow_multiple=true) {$this->field['multiple']=$allow_multiple;return $this;}

    /**
     * It says what kind of files, or extensions will be accepted
     * @param string $files_type example: image/*,application/pdf,.psd
     * @return CFIServerDocuments $this
     */
    public function acceptedFiles(string $files_type) {$this->field['accepted_files']=$files_type;return $this;}

    /**
     * It says the max file size accepted (in bytes). By default is 5242880 bytes
     * @param int $size example: 5242880
     * @return CFIServerDocuments $this
     */
    public function maxFileSize(int $size) {$this->field['max_file_size']=$size;return $this;}
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
    public function onclick($js) { $this->button['js'] = $js; $this->button['type'] = 'onclick'; return $this;}


    /**
     * Assign url to call an external API without avoiding the form sending
     * @param $url
     * @param string $method optinal var to assign the type of call: GET, POST, PUT, DELETE
     * @return CFIButton $this
     */
    public function apiUrl($url,$method='GET') {
        $this->button['method'] = strtoupper($method);$this->button['url'] = $url;
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