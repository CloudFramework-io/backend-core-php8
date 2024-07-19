<?php

/**
 * [$cfa = $this->core->loadClass('CFA');] Class CFA to handle WebApps for CloudFrameworkInterface
 * notion: https://cloudframework.io/docs/es/developers/frontend/frontend-classes/cloudframeworkcfa%C2%A9-class
 * last_update: 20240719
 * @package CoreClasses
 */
class CFA
{
    var $version = '20240719';
    private $core;
    var $data = ['rows'=>[['label'=>'default_row']],'components'=>[]];
    var $labels=[];
    var $colors = null;

    /**
     * CFI constructor.
     * @param Core7 $core
     * @param string $bucket
     */
    function __construct(Core7 &$core, $bucket = '')
    {
        $this->core = $core;
        $this->colors = new CFAColors();
    }

    /**
     * SET rows in the CFA to structure the greed
     * @param string $row_labels labels for each row to be used in the CFA
     * @return CFA
     */
    public function rowLabels(string $row_labels){
        if(!$row_labels) return;

        $this->data['rows'] = [];
        $row_labels = explode(",",$row_labels);
        foreach ($row_labels as $label) {
            $this->data['rows'][] = ['label'=>trim($label)];
        }
        return $this;
    }

    /**
     * Add a class attribute to a specifuc row label
     * @param string $row_label
     * @param string $class
     * @return CFA
     */
    public function addClass(string $row_label, string $class){
        if(!$row_label) return;
        foreach ($this->data['rows'] as $i=>$row) {
            if($row['label']==$row_label) $this->data['rows'][$i]['class'] = $class;
        }
        return $this;
    }



    /**
     * Add a class attribute to a specifuc row label
     * @param string $row_label
     * @param string $class
     * @return CFA
     */
    public function addComponentInLabel(string $label){
        if(!isset($this->labels[$label])) $this->labels[$label] = new CFAComponent();
        return($this->labels[$label]);
    }

    /**
     * Return the CFA structure $this->data
     */
    public function getData($only_label=''){
        foreach ($this->labels as $label=>$content) if(!$only_label || $only_label==$label) {
            $this->data['components'][] = [
                'label'=>$label,
                'component'=>$content->component->type,
                'content'=>$content->component->data
            ];
        }

        // https://stackoverflow.com/questions/46305169/php-json-encode-malformed-utf-8-characters-possibly-incorrectly-encoded
        //json_encode error (5): Malformed UTF-8 characters, possibly incorrectly encoded
        $result = $only_label ? ['components' => $this->data['components']] : $this->data;
        if (is_array($result)) {
            return $result; 
        }
        return mb_convert_encoding($result, 'UTF-8', 'UTF-8');
    }

    /**
     * Return the CFA structure $this->data
     */
    public function getJSON($only_label=''){
        return(json_encode($this->getData($only_label),JSON_PRETTY_PRINT));
    }

}
/*
 * Class to handle fields in CFI
 * last_update: 20200502
 */
class CFAComponent
{

    var $component = null;

    public function header($title='') {
        if(!is_object($this->component) || get_class($this->component)!= 'CFAComponentHeader')
            $this->component = new CFAComponentHeader();
        if($title) $this->component->title($title);
        return($this->component);
    }

    public function boxes() {
        if(!is_object($this->component) || get_class($this->component)!= 'CFAComponentBoxes')
            $this->component = new CFAComponentBoxes();
        return($this->component);
    }

    public function html() {
        if(!is_object($this->component) || get_class($this->component)!= 'CFAComponentHTML')
            $this->component = new CFAComponentHTML();
        return($this->component);
    }

    public function cols() {
        if(!is_object($this->component) || get_class($this->component)!= 'CFAComponentCols')
            $this->component = new CFAComponentCols();
        return($this->component);
    }

    public function panels() {
        if(!is_object($this->component) || get_class($this->component)!= 'CFAComponentPanels')
            $this->component = new CFAComponentPanels();
        return($this->component);
    }

    public function divs() {
        if(!is_object($this->component) || get_class($this->component)!= 'CFAComponentDivs')
            $this->component = new CFAComponentDivs();
        return($this->component);
    }

    public function titles() {
        if(!is_object($this->component) || get_class($this->component)!= 'CFAComponentTitles')
            $this->component = new CFAComponentTitles();
        return($this->component);
    }

    public function buttons() {
        if(!is_object($this->component) || get_class($this->component)!= 'CFAComponentButtons')
            $this->component = new CFAComponentButtons();
        return($this->component);
    }

    public function formSelect() {
        if(!is_object($this->component) || get_class($this->component)!= 'CFAComponentFormSelect')
            $this->component = new CFAComponentFormSelect();
        return($this->component);
    }
    public function select2() {
        if(!is_object($this->component) || get_class($this->component)!= 'CFAComponentSelect2')
            $this->component = new CFAComponentSelect2();
        return($this->component);
    }

    public function formDatePicker() {
        if(!is_object($this->component) || get_class($this->component)!= 'CFAComponentFormDatePicker')
            $this->component = new CFAComponentFormDatePicker();
        return($this->component);
    }

    public function breadcrumb() {
        if(!is_object($this->component) || get_class($this->component)!= 'CFAComponentBreadcrumb')
            $this->component = new CFAComponentBreadcrumb();
        return($this->component);
    }

    public function pageBreadcrumb() {
        if(!is_object($this->component) || get_class($this->component)!= 'CFAComponentPageBreadcrumb')
            $this->component = new CFAComponentPageBreadcrumb();
        return($this->component);
    }

    public function tabs() {
        if(!is_object($this->component) || get_class($this->component)!= 'CFAComponentTabs')
            $this->component = new CFAComponentTabs();
        return($this->component);
    }

    public function tags() {
        if(!is_object($this->component) || get_class($this->component)!= 'CFAComponentTags')
            $this->component = new CFAComponentTags();
        return($this->component);
    }

    public function alerts() {
        if(!is_object($this->component) || get_class($this->component)!= 'CFAComponentAlerts')
            $this->component = new CFAComponentAlerts();
        return($this->component);
    }

    public function searchCards() {
        if(!is_object($this->component) || get_class($this->component)!= 'CFAComponentSearchCards')
            $this->component = new CFAComponentSearchCards();
        return($this->component);
    }

    public function searchInput() {
        if(!is_object($this->component) || get_class($this->component)!= 'CFAComponentSearchInput')
            $this->component = new CFAComponentSearchInput();
        return($this->component);
    }

    public function calendar() {
        if(!is_object($this->component) || get_class($this->component)!= 'CFAComponentCalendar')
            $this->component = new CFAComponentCalendar();
        return($this->component);
    }

    public function table() {
        if(!is_object($this->component) || get_class($this->component)!= 'CFAComponentTable')
            $this->component = new CFAComponentTable();
        return($this->component);
    }

    public function advancedTable() {
        if(!is_object($this->component) || get_class($this->component)!= 'CFAComponentAdvancedTable')
            $this->component = new CFAComponentAdvancedTable();
        return($this->component);
    }

    public function filters() {
        if(!is_object($this->component) || get_class($this->component)!= 'CFAComponentFilters')
            $this->component = new CFAComponentFilters();
        return($this->component);
    }

    public function accordion() {
        if(!is_object($this->component) || get_class($this->component)!= 'CFAComponentAccordion')
            $this->component = new CFAComponentAccordion();
        return($this->component);
    }

    public function chart() {
        if(!is_object($this->component) || get_class($this->component)!= 'CFAComponentChart')
            $this->component = new CFAComponentChart();
        return($this->component);
    }

    public function jsonEditor() {
        if(!is_object($this->component) || get_class($this->component)!= 'CFAComponentJsonEditor')
            $this->component = new CFAComponentJsonEditor();
        return($this->component);
    }

    public function codeFragment() {
        if(!is_object($this->component) || get_class($this->component)!= 'CFAComponentCodeFragment')
            $this->component = new CFAComponentCodeFragment();
        return($this->component);
    }

    public function progressChart() {
        if(!is_object($this->component) || get_class($this->component)!= 'CFAComponentProgressChart')
            $this->component = new CFAComponentProgressChart();
        return($this->component);
    }

    public function boxInfo() {
        if(!is_object($this->component) || get_class($this->component)!= 'CFAComponentBoxInfo')
            $this->component = new CFAComponentBoxInfo();
        return($this->component);
    }

    public function kanban() {
        if(!is_object($this->component) || get_class($this->component)!= 'CFAComponentKanban')
            $this->component = new CFAComponentKanban();
        return($this->component);
    }
    public function peopleCard() {
        if(!is_object($this->component) || get_class($this->component)!= 'CFAComponentPeopleCard')
            $this->component = new CFAComponentPeopleCard();
        return($this->component);
    }
    public function taskTable() {
        if(!is_object($this->component) || get_class($this->component)!= 'CFAComponentTaskTable')
            $this->component = new CFAComponentTaskTable();
        return($this->component);
    }

    public function form() {
        if(!is_object($this->component) || get_class($this->component)!= 'CFAComponentForm')
            $this->component = new CFAComponentForm();
        return($this->component);
    }
}
/**
 * CFAColors Class component
 * https://www.gotbootstrap.com/themes/smartadmin/4.5.1/utilities_color_pallet.html
 */
class CFAColors
{
    var $primary = 'primary';
    var $success = 'success';
    var $warning = 'warning';
    var $info = 'info';
    var $danger = 'danger';
    var $secondary = 'secondary';
}
/**
 * CFAComponentHeader Class component
 */
class CFAComponentHeader
{

    var $type = 'header';
    var $data = [
        'icon'=>null,
        'title'=>null,
        'subtitle'=>null,
        'js-call'=>null,
        'js-ico'=>null,
    ];

    public function icon($data) {$this->data['icon'] = $data; return $this;}
    public function title($data) {$this->data['title'] = $data; return $this;}
    public function addTitleStyle($data) {$this->data['title-style'] = $data; return $this;}
    public function subtitle($data) {$this->data['subtitle'] = $data; return $this;}
    public function addSubtitleStyle($data) {$this->data['subtitle-style'] = $data; return $this;}
    public function jsIconCall($js_function,$icon) {$this->data['js-call'] = $js_function;$this->data['js-ico'] = $icon; return $this;}
}

/**
 * CFAComponentJsonEditor Class component
 */
class CFAComponentJsonEditor
{

    var $type = 'json-editor';
    var $data = [
        'json'=>null
    ];
    public function __construct() { $this->data['id'] = uniqid('json-editor');}
    public function json($data) {$this->data['json'] = is_array($data)?json_encode($data):$data; return $this;}
    public function type($data) {$this->data['type'] = $data; return $this;}
}

/**
 * CFAComponentCodeFragment Class component
 */
class CFAComponentCodeFragment
{

    var $type = 'code-fragment';
    var $data = [
        'code'=>null,
        'type'=>null
    ];
    public function __construct() { $this->data['id'] = uniqid('code-fragment');}
    public function code($data) {$this->data['code'] = $data; return $this;}
    public function type($data) {$this->data['type'] = $data; return $this;}
}

/**
 * CFAComponentProgressChart Class component
 */
class CFAComponentProgressChart
{
    var $type = 'progress-chart';
    var $data = [
        'title'=>null,
        'min'=>null,
        'max'=>null,
        'value'=>null,
        'color'=>null,
        'class'=>null
    ];
    public function __construct() { $this->data['id'] = uniqid('progress-chart');}
    public function title($data) {$this->data['title'] = $data; return $this;}
    public function min($data) {$this->data['min'] = $data; return $this;}
    public function max($data) {$this->data['max'] = $data; return $this;}
    public function value($data) {$this->data['value'] = $data; return $this;}
    public function class($data) {$this->data['class'] = $data; return $this;}
}

/**
 * CFAComponentBoxInfo Class component
 */
class CFAComponentBoxInfo
{
    var $type = 'boxInfo';
    var $data = [
        'title'=>null,
        'border'=>null,
        'subtitle'=>null,
        'color'=>null,
        'class'=>null
    ];
    public function __construct() { $this->data['id'] = uniqid('box-info');}
    public function title($data) {$this->data['title'] = $data; return $this;}
    public function border($data) {$this->data['border'] = $data; return $this;}
    public function subtitle($data) {$this->data['subtitle'] = $data; return $this;}
    public function size($data) {$this->data['size'] = $data; return $this;}
    public function color($data) {$this->data['color'] = $data; return $this;}
    public function class($data) {$this->data['class'] = $data; return $this;}
}


/**
 * CFAComponentHTML Class component
 */
class CFAComponentHTML
{
    var $type = 'html';
    var $data = ['html'=>''];
    public function plain($data) {$this->data['html'].= $data;return $this;}
    public function h1($data,$label='') {$this->data['html'].= "<h1".(($label)?' id="'.$label.'"':'').">{$data}</h1>";return $this;}
    public function h2($data,$label='') {$this->data['html'].= "<h2".(($label)?' id="'.$label.'"':'').">{$data}</h2>";return $this;}
    public function h3($data,$label='') {$this->data['html'].= "<h3".(($label)?' id="'.$label.'"':'').">{$data}</h3>";return $this;}
    public function div($data,$label='',$class='') {$this->data['html'].= "<div".(($label)?' id="'.$label.'"':'').(($class)?' class="'.$class.'"':'').">{$data}</div>";return $this;}
    public function p($data,$label='') {$this->data['html'].= "<p".(($label)?' id="'.$label.'"':'').">{$data}</p>";return $this;}
    public function hr($label='') {$this->data['html'].= "<hr".(($label)?' id="'.$label.'"':'')."/>";return $this;}
    public function pre($data,$label='') {$this->data['html'].= "<pre".(($label)?' id="'.$label.'"':'').">{$data}</pre>";return $this;}
    public function textarea($data,$label='') {if(!is_string($data)) $data = json_encode($data,JSON_PRETTY_PRINT);$this->data['html'].= "<textarea cols='90' rows='10'".(($label)?' id="'.$label.'"':'').">{$data}</textarea>";return $this;}
    public function testComponents($id,$json,$php) {
        $this->data['html'].= "
            <div  class='row'>
            <div  class='col-xl-6'>
            <small>textarea id: {$id}_code</small><br/>
            <textarea cols='90' rows='10' class='json-editor' id='{$id}_code'>{$json}</textarea><br>
            <input type='button' onclick=\"CloudFrameWorkCFA.renderComponents(JSON.parse($('#{$id}_code').val()))\" value=\"CloudFrameWorkCFA.renderComponents(JSON.parse($('#{$id}_code').val()))\">
            </div>
            <div  class='col-xl-6'>
            <div  id='{$id}'>".htmlentities("<div  id='{$id}'></div>")."</div>
             </div>
             </div>
            ";
        return $this;
    }
    public function componentDocumentationIndex($id,$ico,$title,$subTitle,$onclick,$searchFilter=null) {
        $this->data['html'].= "
            <div id='{$id}_componente_documentation_index' data-filter-tags='".strtolower($searchFilter??$title)."'>
                <div class='card h-100 rounded overflow-hidden position-relative'>
                <div class='card-body p-4'>
                    <div class='d-flex align-items-center mb-g'>
                        <i style='font-size: 30px; color:var(--theme-primary);' class='subheader-icon fal fa-{$ico}'></i>
                        <div class='ml-3'>
                            <h2 class='fw-300 m-0 l-h-n'>
                                <span class='text-contrast'>{$title}</span> 
                                <small class='fw-300 m-0 l-h-n'>{$subTitle}</small>
                            </h2>
                        </div>
                    </div>
                    <div class='col'>
                        <button onclick=\"$onclick\" href='#' class='btn btn-sm btn-outline-primary waves-effect waves-themed'>View {$title} components info</button>
                    </div>
                </div>
            </div>
            ";
        return $this;
    }
}

/**
 * CFAComponentTitles Class component
 */
class CFAComponentTitles
{

    var $type = 'titles';
    var $index =0;
    var $data = [];

    public function add($title='') {if(isset($this->data[$this->index]) &&$this->data[$this->index]) $this->index++; if($title) $this->data[$this->index]['title']=$title; return $this;}
    public function type($data) {$this->data[$this->index]['type'] = $data; return $this;}
    public function icon($data) {$this->data[$this->index]['icon'] = $data; return $this;}
    public function jsIconCall($js_function,$icon) {$this->data[$this->index]['js-call'] = $js_function;$this->data[$this->index]['js-ico'] = $icon; return $this;}
    public function title($data) {$this->data[$this->index]['title'] = $data; return $this;}
    public function subtitle($data) {$this->data[$this->index]['subtitle'] = $data; return $this;}
    public function active($data) {$this->data[$this->index]['active'] = (bool)$data; return $this;}
    public function onclick($data) {$this->data[$this->index]['onclick'] = $data; return $this;}
    public function addClass($data) {$this->data[$this->index]['class'] = $data; return $this;}
    public function addStyle($data) {$this->data[$this->index]['style'] = $data; return $this;}
    public function addBadge($title,$color='',$border=false,$pill=false) {if(!isset($this->data[$this->index]['badges'])) $this->data[$this->index]['badges']=[]; $this->data[$this->index]['badges'][] = ['title'=>$title,'color'=>$color,'border'=>(bool)$border,'pill'=>(bool)$pill]; return $this;}
    public function addLeftPhoto($src,$alt='',$classes="rounded-circle") {
        if(!isset($this->data[$this->index]['left-photos'])){
            $this->data[$this->index]['left-photos']=[]; 
            $this->data[$this->index]['left-photos'][] = ['url'=>$src,'alt'=>$alt,'classes'=>$classes]; 
            return $this;
        } 
    }
    public function addRightPhoto($src,$alt='',$classes="rounded-circle") {
        if(!isset($this->data[$this->index]['right-photos'])){
            $this->data[$this->index]['right-photos']=[]; 
            $this->data[$this->index]['right-photos'][] = ['url'=>$src,'alt'=>$alt,'classes'=>$classes]; 
            return $this;
        }
    }
    public function addPhoto($src,$alt='',$classes="rounded-circle") {
        if(!isset($this->data[$this->index]['photos'])){
            $this->data[$this->index]['photos']=[]; 
            $this->data[$this->index]['photos'][] = ['url'=>$src,'alt'=>$alt,'classes'=>$classes];
            return $this;
        }
    }
}
/**
 * CFAComponentBoxes Class component
 */
class CFAComponentBoxes
{

    var $type = 'boxes';
    var $index =0;
    var $data = [];

    public function add($title='') {if(isset($this->data[$this->index]) &&$this->data[$this->index]) $this->index++; if($title) $this->data[$this->index]['title']=$title; return $this;}
    public function title($data) {$this->data[$this->index]['title'] = $data; return $this;}
    public function containerClass($data) {$this->data[$this->index]['containerClass'] = $data; return $this;}
    public function ico($data) {$this->data[$this->index]['ico'] = $data; return $this;}
    public function color($data) {$this->data[$this->index]['color'] = $data; return $this;}
    public function textColor($data) {$this->data[$this->index]['textColor'] = $data; return $this;}
    public function icoColor($data) {$this->data[$this->index]['icoColor'] = $data; return $this;}
    public function style($data) {$this->data[$this->index]['style'] = $data; return $this;}
    public function total($data) {$this->data[$this->index]['total'] = $data; return $this;}
}
/**
 * CFAComponentCols Class component
 */
class CFAComponentCols
{

    var $type = 'cols';
    var $index =0;
    var $data = [];
    public function add($label='') {if(isset($this->data[$this->index]) &&$this->data[$this->index]) $this->index++; if($label) $this->data[$this->index]['label']=$label; return $this;}
    public function label($data) {$this->data[$this->index]['label'] = $data; return $this;}
    public function size($data) {$this->data[$this->index]['size'] = $data; return $this;}
}
/**
 * CFAComponentPanels Class component
 */
class CFAComponentPanels
{

    var $type = 'panels';
    var $index =0;
    var $data = [];
    public function add(string $label='') {if(isset($this->data[$this->index]) &&$this->data[$this->index]) $this->index++; if($label) $this->data[$this->index]['label']=$label; return $this;}
    public function label(string $label) {$this->data[$this->index]['label'] = $label; return $this;}
    public function size($data) {$this->data[$this->index]['size'] = $data; return $this;}
    public function locked($locked=true) {$this->data[$this->index]['locked'] = (bool)$locked; return $this;}
    public function collapse($data) {$this->data[$this->index]['collapse'] = (bool)$data; return $this;}
    public function show($data) {$this->data[$this->index]['show'] = (bool)$data; return $this;}
}
/**
 * CFAComponentDivs Class component
 */
class CFAComponentDivs
{

    var $type = 'divs';
    var $index =0;
    var $data = [];
    public function add($label='') {if(isset($this->data[$this->index]) &&$this->data[$this->index]) $this->index++; if($label) $this->data[$this->index]['label']=$label; return $this;}
    public function label($data) {$this->data[$this->index]['label'] = $data; return $this;}
    public function class($data) {$this->data[$this->index]['class'] = $data; return $this;}
    public function hide($data) {$this->data[$this->index]['hide'] = (bool)$data; return $this;}
}
/**
 * CFAComponentButtons Class component
 */
class CFAComponentButtons
{
    var $type = 'buttons';
    var $index =0;
    var $data = [];
    public function add($label='') {if(isset($this->data[$this->index]) &&$this->data[$this->index]) $this->index++; if($label) $this->data[$this->index]['label']=$label; return $this;}
    public function label($data) {$this->data[$this->index]['label'] = $data; return $this;}
    public function title($data) {$this->data[$this->index]['title'] = $data; return $this;}
    public function icon($data) {$this->data[$this->index]['ico'] = $data; return $this;}
    public function color($data) {$this->data[$this->index]['color'] = $data; return $this;}
    public function onclick($data) {$this->data[$this->index]['onclick'] = $data; return $this;}
}
/**
 * CFAComponentFormSelect Class component
 */
class CFAComponentFormSelect
{
    var $type = 'form-select';
    var $data = [
        'label'=>null,
        'title'=>null,
        'onchange'=>null,
        'options'=>[],
    ];
    public function label($data) {$this->data['label'] = $data; return $this;}
    public function title($data) {$this->data['title'] = $data; return $this;}
    public function onchange($data) {$this->data['onchange'] = $data; return $this;}
    public function addOption($value,$option,$selected=false) {$this->data['options'][] = ['value'=>$value,'option'=>$option,'selected'=>(bool)$selected]; return $this;}
}

/**
 * CFAComponentSelect2 Class component
 */
class CFAComponentSelect2
{
    var $type = 'select2';
    var $data = [
        'label'=>null,
        'title'=>null,
        'onchange'=>null,
        'options'=>[],
        'multiple'=>false,
        'type'=>null,
        'value'=>null,
    ];
    public function __construct() { $this->data['id'] = uniqid('select2');}
    public function label($data) {$this->data['label'] = $data; return $this;}
    public function title($data) {$this->data['title'] = $data; return $this;}
    public function options($data) {$this->data['options']=$data; return $this;}
    public function onchange($data) {$this->data['onchange'] = $data; return $this;}
    public function multiple($data) {$this->data['multiple'] = $data; return $this;}
    public function type($data) {$this->data['type'] = $data; return $this;}
    public function value($data) {$this->data['value'] = $data; return $this;}
}

/**
 * CFAComponentFormDatePicker Class component
 */
class CFAComponentFormDatePicker
{
    var $type = 'form-datepicker';
    var $data = [
        'label'=>null,
        'title'=>null,
        'type'=>null,
        'placeholder'=>null,
        'showDropdowns'=>false,
        'dropDownsMinYear'=>null,
        'dropDownsMaxYear'=>null,
        'minDate'=>null,
        'maxDate'=>null,
        'ranges'=>null,
        'onchange'=>null,
        'class'=>null,
        'value'=>null,
        'options'=>[],
    ];
    public function label($data) {$this->data['label'] = $data; return $this;}
    public function title($data) {$this->data['title'] = $data; return $this;}
    public function type($data) {$this->data['type'] = $data; return $this;}
    public function placeholder($data) {$this->data['placeholder'] = $data; return $this;}
    public function showDropdowns($data) {$this->data['showDropdowns'] = (bool)$data;; return $this;}
    public function dropDownsMinYear($data) {$this->data['dropDownsMinYear'] = $data; return $this;}
    public function dropDownsMaxYear($data) {$this->data['dropDownsMaxYear'] = $data; return $this;}
    public function minDate($data) {$this->data['minDate'] = $data; return $this;}
    public function maxDate($data) {$this->data['maxDate'] = $data; return $this;}
    public function ranges($data) {$this->data['ranges'] = $data; return $this;}
    public function onchange($data) {$this->data['onchange'] = $data; return $this;}
    public function class($data) {$this->data['class'] = $data; return $this;}
    public function value($data) {$this->data['value'] = $data; return $this;}
    public function addOption($value,$option,$selected=false) {$this->data['options'][] = ['value'=>$value,'option'=>$option,'selected'=>(bool)$selected]; return $this;}
}

/**
 * CFAComponentBreadcrumb Class component
 */
class CFAComponentBreadcrumb
{
    var $type = 'breadcrumb';
    var $data = [
        'label'=>null,
        'solid'=>null,
        'color'=>null,
        'elements'=>[],
    ];
    public function label($data) {$this->data['label'] = $data; return $this;}
    public function solid($data) {$this->data['solid'] = (bool)$data; return $this;}
    public function color($data) {$this->data['color'] = $data; return $this;}
    public function textColor($data) {$this->data['text-color'] = $data; return $this;}
    public function concatTitle($data,$javascript='') {$this->data['elements'][] = ['title'=>$data,'href'=>($javascript)?"javascript:{$javascript}":null]; return $this;}
    public function concatIco($ico,$javascript="") {$this->data['elements'][] = ['ico'=>$ico,'href'=>($javascript)?"javascript:{$javascript}":null]; return $this;}
    public function concatPhoto($src,$alt="") {$this->data['elements'][] = ['photo'=>['url'=>$src,'alt'=>$alt]]; return $this;}
}
/**
 * CFAComponentBreadcrumb Class component
 */
class CFAComponentPageBreadcrumb
{
    var $type = 'pageBreadcrumb';
    var $data = [
        'label'=>null,
        'elements'=>[],
    ];
    public function label($data) {$this->data['label'] = $data; return $this;}
    public function addElement($title,$url='',$active=false) {
        if(!isset($this->data['elements'])) $this->data['elements']=[];
        $obj = [];
        $obj['title'] = $title;
        $obj['url'] = $url;
        $obj['active'] = $active;
        $this->data['elements'][] = $obj;

        return $this;
    }
}

/**
 * CFAComponentTabs Class component
 */
class CFAComponentTabs
{
    var $type = 'tabs';
    var $index = 0;
    var $data = [];

    public function type($data) {$this->data['type'] = $data; return $this;}
    public function activeColor($data) {$this->data['activeColor'] = $data; return $this;}
    public function style($data) {$this->data['style'] = $data; return $this;}

    public function add($label, $title, $icon = "", $active=false, $style=null)
    {
        if (isset($this->data['data'][$this->index]) && $this->data['data'][$this->index]) $this->index++;
        $this->data['data'][$this->index]['label'] = $label;
        $this->data['data'][$this->index]['title'] = $title;
        if ($icon) $this->data['data'][$this->index]['ico'] = $icon;
        if ($active) $this->data['data'][$this->index]['active'] = true;
        if ($style) $this->data['data'][$this->index]['style'] = $style;
        return $this;
    }
    public function onclick($data) {$this->data['data'][$this->index]['onclick'] = $data; return $this;}
}

/**
 * CFAComponentTabs Class component
 */
class CFAComponentFilters
{
    var $type = 'filters';
    var $index = 0;
    var $data = [];

    /**
     * Add a new Filter to populate with fields
     * @param string $cfa_api
     * @param $label
     * @param $title
     * @param string $icon
     * @return $this
     */
    public function add(string $cfa_api, $label, $title, $icon = "")
    {
        if (isset($this->data[$this->index]) && $this->data[$this->index]) $this->index++;
        $this->data[$this->index]['cfa_api'] = $cfa_api;
        $this->data[$this->index]['label'] = $label;
        $this->data[$this->index]['title'] = $title;
        if ($icon) $this->data[$this->index]['icon'] = $icon;
        $this->data[$this->index]['filters'] = [];
        return $this;
    }

    public function insertNewLine() {
        $this->data[$this->index]['filters'][] = [
            'new_line'=>true
        ];
        return $this;
    }

    public function insertText(string $field_title,string $field_name, $default_value='',  $placeholder='', int $size=10) {
        $this->data[$this->index]['filters'][] = [
            'text'=>[
                'field_title'=>$field_title,
                'field_name'=>$field_name,
                'placeholder'=>$placeholder,
                'default_value'=>$default_value,
                'has_defaultvalue'=>(strlen($default_value))?true:false,
                'size'=>$size,
            ]
        ];
        return $this;
    }

    public function insertSelect(string $field_title,string $field_name, $values,  string $selected='') {

        $select_values = [];
        if(is_string($values)) $values = explode(',',$values);
        foreach ($values as $i=>$value) {
            $row = [];
            if(is_string($value)) $row = ['id'=>$i,'value'=>$value];
            elseif(isset($value['id']) && isset($value['value'])) $row = $value;
            else ['id'=>json_encode($value),'value'=>json_encode($value)];
            if($selected == $row['id']) $row['selected'] = 'selected';
            $select_values[] = $row;
        }

        $this->data[$this->index]['filters'][] = [
            'select'=>[
                'field_title'=>$field_title,
                'field_name'=>$field_name,
                'values'=>$select_values
            ]
        ];
        return $this;
    }
}

/**
 * CFAComponentSearchCards Class component
 */
class CFAComponentSearchCards
{
    var $type = 'search-cards';
    var $index = 0;
    var $data = [
        'search_placeholder'=>null,
        'cards'=>[],
    ];


    public function searchPlaceHolder($data) {$this->data['search_placeholder'] = $data; return $this;}
    public function add() { if (isset($this->data['cards'][$this->index]) && $this->data['cards'][$this->index]) $this->index++;$this->data['cards'][$this->index] = [];return $this;}
    public function avatar($data) {$this->data['cards'][$this->index]['avatar'] = $data; return $this;}
    public function title($data) {$this->data['cards'][$this->index]['title'] = $data; return $this;}
    public function subtitle($data) {$this->data['cards'][$this->index]['subtitle'] = $data; return $this;}
    public function searchTags($data) {$this->data['cards'][$this->index]['tags'] = $data; return $this;}
    public function addBodyLine($title,$ico='') {if(!isset($this->data['cards'][$this->index]['lines'])) $this->data['cards'][$this->index]['lines']=[]; $this->data['cards'][$this->index]['lines'][] = ['title'=>$title,'ico'=>$ico]; return $this;}
    public function addTitleMenu($title,$javascript) {if(!isset($this->data['cards'][$this->index]['menu'])) $this->data['cards'][$this->index]['menu']=[]; $this->data['cards'][$this->index]['menu'][] = ['title'=>$title,'href'=>$javascript]; return $this;}
    public function addSubtitleBadge($title,$color,$border=false) {if(!isset($this->data['cards'][$this->index]['badges'])) $this->data['cards'][$this->index]['badges']=[]; $this->data['cards'][$this->index]['badges'][] = ['title'=>$title,'color'=>$color,'border'=>(bool)$border]; return $this;}
    public function addBottomAvatar($src,$alt='') {if(!isset($this->data['cards'][$this->index]['avatars'])) $this->data['cards'][$this->index]['avatars']=[]; $this->data['cards'][$this->index]['avatars'][] = ['url'=>$src,'alt'=>$alt]; return $this;}
    public function addBottomBrand($title,$ico,$link) {if(!isset($this->data['cards'][$this->index]['brands'])) $this->data['cards'][$this->index]['brands']=[]; $this->data['cards'][$this->index]['brands'][] = ['title'=>$title,'ico'=>$ico,'link'=>$link]; return $this;}
}

/**
 * CFAComponentSearchInput Class component
 */
class CFAComponentSearchInput
{
    var $type = 'search-input';
    var $index = 0;
    var $data = [
        'search_placeholder'=>null,
        'search_elements_wrapper'=>null
    ];
    public function __construct() { $this->data['id'] = uniqid('search-input');}
    public function searchPlaceHolder($data) {$this->data['search_placeholder'] = $data; return $this;}
    public function searchElementsWrapper($data) {$this->data['search_elements_wrapper'] = $data; return $this;}
    public function class($data) {$this->data['class'] = $data; return $this;}
}


/**
 * CFAComponentTags Class component
 */
class CFAComponentTags
{
    var $type = 'tags';
    var $index =0;
    var $data = [];
    public function add($label='') {if(isset($this->data[$this->index]) &&$this->data[$this->index]) $this->index++; if($label) $this->data[$this->index]['label']=$label; return $this;}
    public function label($data) {$this->data[$this->index]['label'] = $data; return $this;}
    public function title($data) {$this->data[$this->index]['title'] = $data; return $this;}
}


/**
 * CFAComponentAlerts Class component
 */
class CFAComponentAlerts
{
    var $type = 'alerts';
    var $index =0;
    var $data = [];
    public function add($label='') {if(isset($this->data[$this->index]) &&$this->data[$this->index]) $this->index++; if($label) $this->data[$this->index]['label']=$label; return $this;}
    public function label($data) {$this->data[$this->index]['label'] = $data; return $this;}
    public function content($data) {$this->data[$this->index]['content'] = $data; return $this;}
    public function color($data) {$this->data[$this->index]['color'] = $data; return $this;}
    public function icon($data) {$this->data[$this->index]['ico'] = $data; return $this;}
    public function class($data) {$this->data[$this->index]['class'] = $data; return $this;}
    public function onclick($data) {$this->data[$this->index]['onclick'] = $data; return $this;}
    public function addPhoto($src,$alt='') {if(!isset($this->data[$this->index]['photo'])) $this->data[$this->index]['photo']=[]; $this->data[$this->index]['photo'][] = ['url'=>$src,'alt'=>$alt]; return $this;}
    public function jsIconCall($js_function,$icon) {$this->data[$this->index]['js-call'] = $js_function;$this->data[$this->index]['js-ico'] = $icon; return $this;}
    public function addBadge($title,$color='',$border=false,$pill=false) {if(!isset($this->data[$this->index]['badges'])) $this->data[$this->index]['badges']=[]; $this->data[$this->index]['badges'][] = ['title'=>$title,'color'=>$color,'border'=>(bool)$border,'pill'=>(bool)$pill]; return $this;}


}

/**
 * CFAComponentCalendar Class component
 */
class CFAComponentCalendar
{
    var $type = 'calendar';
    var $index = 0;
    var $data = [];

    // public function events($data) {$this->data['calendar'][$this->index]['events'] = json_decode('[{"title":"Product daily CFW", "start":"2022-03-07T16:00:00", "description":"Event description", "className":"border-warning bg-warning text-dark"}]'); return $this;}
    public function __construct()
    {
        $this->data['id'] = uniqid('calendar');
    }
    public function setClass($data) {$this->data['class'] = $data; return $this;}
    public function setId($data) {$this->data['id'] = $data; return $this;}
    public function setAddButton($javascript,$symbol="+") {$this->data['addButtonJavascript'] = $javascript; $this->data['addButtonSymbol'] = $symbol;  return $this;}
    public function setDropEvent($javascript) {$this->data['addDropEvent'] = $javascript;  return $this;}
    public function setResizeEvent($javascript) {$this->data['addResizeEvent'] = $javascript;  return $this;}
    public function add($title,$start,$end='') {if(isset($this->data['events'][$this->index]) && $this->data['events'][$this->index]) $this->index++; $this->data['events'][$this->index]['title']=$title;$this->data['events'][$this->index]['start']=$start; if($end)$this->data['events'][$this->index]['end']=$end; return $this;}
    public function title($data) {$this->data['events'][$this->index]['title'] = $data; return $this;}
    public function description($data) {$this->data['events'][$this->index]['description'] = $data; return $this;}
    public function start($data) {$this->data['events'][$this->index]['start'] = $data; return $this;}
    public function end($data) {$this->data['events'][$this->index]['end'] = $data; return $this;}
    public function javascript($data) {$this->data['events'][$this->index]['url'] = "javascript:".$data; return $this;}
    public function url($data) {$this->data['events'][$this->index]['url'] = $data; return $this;}
    public function color($bg,$text='',$border='') {
        if(!$border) $border=$bg;
        if(!$text) $text='white';
        $this->data['events'][$this->index]['className'] = "bg-{$bg} border-{$border} text-{$text}"; return $this;}

}

/**
 * CFAComponentAccordion Class component
 */
class CFAComponentAccordion
{
    var $type = 'accordion';
    var $index = 0;
    var $data = [];
    public function __construct() { $this->data['id'] = uniqid('accordion');}
    public function add($label) {if(isset($this->data['cards'][$this->index]) &&$this->data['cards'][$this->index]) $this->index++; if($label) $this->data['cards'][$this->index]['label']=$label; return $this;}
    public function title($data) {$this->data['cards'][$this->index]['title'] = $data; return $this;}
    public function expanded($data) {$this->data['cards'][$this->index]['expanded'] = $data; return $this;}
    public function icon($data) {$this->data['cards'][$this->index]['icon'] = $data; return $this;}
}

/**
 * CFAComponentTable Class component
 */
class CFAComponentTable
{
    var $type = 'table';
    var $index = 0;
    var $data = [];
    public function __construct($label='') { if($label) $this->data['label'] = $label;}
    public function preHeader($preHeader) {$this->data['preHeader']=$preHeader; return $this;}
    public function label($label) {$this->data['label']=$label; return $this;}
    public function compact($bool=true) {$this->data['compact']=(bool)$bool; return $this;}
    public function headerStyle($data) {$this->data['headerStyle'] = $data; return $this;}
    public function style($data) {$this->data['style']=$data; return $this;}
    public function cols(array $cols,$color='primary-500',$attributes='') {
        $this->data['hasHeaderData']=(bool)$cols;
        $this->data['headerDataColor']=$color;
        $this->data['headerAttributes']=$attributes;

        $this->data['cols']=[];
        foreach ($cols as $i=>$col) {
            if(is_object($col)){
                $col = (array) $col;
            }
            $this->data['cols'][$i]['data'] = (is_array($col))?((isset($col['data']))?$col['data']:null):$col;
            if(is_array($col) && isset($col['html'])) $this->data['cols'][$i]['html'] = $col['html'];
            if(is_array($col) && isset($col['onclick'])) { $this->data['cols'][$i]['onclick'] = $col['onclick']; if(isset($col['class']))  $col['class'].=" cursor-pointer"; else $col['class']="cursor-pointer";}
            if(is_array($col) && isset($col['class'])) $this->data['cols'][$i]['class'] = $col['class'];
            if(is_array($col) && isset($col['color'])) $this->data['cols'][$i]['color'] = $col['color'];
            if(is_array($col) && isset($col['style'])) $this->data['cols'][$i]['style'] = $col['style'];
            if(is_array($col) && isset($col['attributes'])) $this->data['cols'][$i]['attributes'] = $col['attributes'];
            $this->data['cols'][$i]['label'] = $cell['label']??null;
            if(isset($col['class']) || isset($col['color'])) $this->data['cols'][$i]['hasClass']=true;
        }
        return $this;
    }
    public function rows(array $data,$color='',$attributes='') {
        $this->data['hasRowData']=(bool)$data;
        $this->data['rowDataColor']=$color;
        $this->data['rowAttributes']=$attributes;
        $this->data['rows']=[];
        foreach ($data as $i=>$datum) if(is_array($datum)) {
            foreach ($datum as $j=>$cell) {
                $this->data['rows'][$i]['cols'][$j]['data'] = (is_array($cell)) ? ((isset($cell['data'])) ? $cell['data'] : null) : $cell;
                if (is_array($cell) && isset($cell['html'])) $this->data['rows'][$i]['cols'][$j]['html'] = $cell['html'];
                if (is_array($cell) && isset($cell['onclick'])) { $this->data['rows'][$i]['cols'][$j]['onclick'] = $cell['onclick'];if(isset($cell['class']))  $cell['class'].=" cursor-pointer"; else $cell['class']="cursor-pointer";}
                if (is_array($cell) && isset($cell['class'])) $this->data['rows'][$i]['cols'][$j]['class'] = $cell['class'];
                if (is_array($cell) && isset($cell['color'])) $this->data['rows'][$i]['cols'][$j]['color'] = $cell['color'];
                if (is_array($cell) && isset($cell['style'])) $this->data['rows'][$i]['cols'][$j]['style'] = $cell['style'];
                if (is_array($cell) && isset($cell['link'])) $this->data['rows'][$i]['cols'][$j]['link'] = $cell['link'];
                if (is_array($cell) && isset($cell['attributes'])) $this->data['rows'][$i]['cols'][$j]['attributes'] = $cell['attributes'];
                $this->data['rows'][$i]['cols'][$j]['label'] = $cell['label'] ?? null;
                if (isset($cell['class']) || isset($cell['color'])) $this->data['rows'][$i]['cols'][$j]['hasClass'] = true;
            }
        }

        return $this;
    }
}

/**
 * CFAComponentAdvancedTable Class component
 */
class CFAComponentAdvancedTable
{
    var $type = 'advanced-table';
    var $index = 0;
    var $data = [];
    public function __construct() { $this->data['id'] =  uniqid('advanced-table-component');}
    public function id($data) {$this->data['id']=$data; return $this;}
    public function cols($data) {$this->data['cols']=$data; return $this;}
    public function rows($data) {$this->data['rows']=$data; return $this;}
    public function maxHeight($data) {$this->data['maxHeight'] = $data; return $this;}
    public function pagination($data) {$this->data['pagination'] = $data; return $this;}
    public function paginationMode($data) {$this->data['paginationMode'] = $data; return $this;}
    public function paginationPagesPerPage($data) {$this->data['paginationPagesPerPage'] = $data; return $this;}
    public function paginationPerPageDropdown($data) {$this->data['paginationPerPageDropdown'] = $data; return $this;}
    public function paginationSetCurrentPage($data) {$this->data['paginationSetCurrentPage'] = $data; return $this;}
    public function buttons($data) {$this->data['buttons'] = $data; return $this;}
    public function displaySearch($data) {$this->data['displaySearch'] = $data; return $this;}
    public function fixedHeader($data) {$this->data['fixedHeader'] = $data; return $this;}
    public function selectRows($data) {$this->data['selectRows'] = $data; return $this;}
    public function selectRowsButtons($data) {$this->data['selectRowsButtons'] = $data; return $this;}
    public function styleType($data) {$this->data['styleType'] = $data; return $this;}
}

 /**
  * CFAComponentChart Class component
  */
class CFAComponentChart
{
    var $type = 'chart';
    var $index = 0;
    var $data = [];
    
    public function __construct() { $this->data['id'] = uniqid('chart-component');}
    public function title($data) {$this->data['title'] = $data; return $this;}
    public function icon($data) {$this->data['icon'] = $data; return $this;}
    public function height($data) {$this->data['height'] = $data; return $this;}
    public function type($data) {$this->data['type'] = $data; return $this;}
    public function data($data) {$this->data['data'] = $data; return $this;}
    public function options($data) {$this->data['options'] = $data; return $this;}
    public function class($data) {$this->data['class'] = $data; return $this;}
}

 /**
  * CFAComponentKanban Class component
  */
class CFAComponentKanban
{
    var $type = 'kanban';
    var $data = [];
    var $boardIndex = 0;
    
    public function __construct() { 
        $this->data['id'] = uniqid('kanban');
        $this->data['items'] = [];
    }
    public function dragItems($data) {$this->data['config']['dragItems'] = $data; return $this;}
    public function dragBoards($data) {$this->data['config']['dragBoards'] = $data; return $this;}
    public function addButton($data=true) {$this->data['config']['addButton'] = $data; return $this;}
    public function itemClick($data=true) {$this->data['config']['itemClick'] = $data; return $this;}
    public function itemDragged($data=true) {$this->data['config']['itemDragged'] = $data; return $this;}
    public function addBoard($title='') {
        if(isset($this->data['items'][$this->boardIndex]) && $this->data['items'][$this->boardIndex]) $this->boardIndex++; if($title) $this->data['items'][$this->boardIndex]['title']=$title; return $this;
    }
    public function id($data) {$this->data['items'][$this->boardIndex]['id'] = $data; return $this;}
    public function title($data) {$this->data['items'][$this->boardIndex]['title'] = $data; return $this;}
    public function class($data) {$this->data['items'][$this->boardIndex]['class'] = $data; return $this;}
    public function dragTo($data) {$this->data['items'][$this->boardIndex]['dragTo'] = $data; return $this;}
    public function addItem($data) {
        if(!isset($this->data['items'][$this->boardIndex]['item'])){$this->data['items'][$this->boardIndex]['item'] = [];}
        array_push($this->data['items'][$this->boardIndex]['item'], json_decode($data,true));
        return $this;
    }

    public function board($data) {
        array_push( $this->data['items'], json_decode($data,true));
        return $this;
    }

    public function items($data) {$this->data['items'] = $data; return $this;}
}

/**
  * CFAComponentPeopleCard Class component
  */
  class CFAComponentPeopleCard
  {
      var $type = 'people-card';
      var $index = 0;
      var $data = [];
      
      public function __construct() { $this->data['id'] = uniqid('people-card');}
      public function searchFilter($data) {$this->data['searchFilter'] = $data; return $this;}
      public function name($data) {$this->data['name'] = $data; return $this;}
      public function surname($data) {$this->data['surname'] = $data; return $this;}
      public function second_surname($data) {$this->data['second_surname'] = $data; return $this;}
      public function fullname($data) {$this->data['fullname'] = $data; return $this;}
      public function email($data) {$this->data['email'] = $data; return $this;}
      public function phone($data) {$this->data['phone'] = $data; return $this;}
      public function avatar($data) {$this->data['avatar'] = $data; return $this;}
      public function linkedin($data) {$this->data['linkedin'] = $data; return $this;}
      public function videos($data) {$this->data['videos'] = $data; return $this;}
      public function user_id($data) {$this->data['user_id'] = $data; return $this;}
      public function level0($data) {$this->data['level0'] = $data; return $this;}
      public function reports_to($data) {$this->data['reports_to'] = $data; return $this;}
      public function areas($data) {$this->data['areas'] = $data; return $this;}
      public function departments($data) {$this->data['departments'] = $data; return $this;}
      public function position($data) {$this->data['position'] = $data; return $this;}
      public function position_id($data) {$this->data['position_id'] = $data; return $this;}
      public function position_name($data) {$this->data['position_name'] = $data; return $this;}
      public function positions($data) {$this->data['positions'] = $data; return $this;}
      public function competencies($data) {$this->data['competencies'] = $data; return $this;}
      public function responsibilities($data) {$this->data['responsibilities'] = $data; return $this;}
      public function tasks($data) {$this->data['tasks'] = $data; return $this;}
      public function skills($data) {$this->data['skills'] = $data; return $this;}
      public function edit_event($data) {$this->data['edit_event'] = $data; return $this;}
      public function color($data) {$this->data['color'] = $data; return $this;}
      public function class($data) {$this->data['class'] = $data; return $this;}
  }
/**
  * CFAComponentPeopleCard Class component
  */
  class CFAComponentTaskTable
  {
      var $type = 'task-table';
      var $index = 0;
      var $data = [];
      
      public function __construct() { $this->data['uniqid'] = uniqid('people-card');}
      public function title($data) {$this->data['title'] = $data; return $this;}
      public function cols($data) {$this->data['cols'] = $data; return $this;}
      public function statuses($data) {$this->data['statuses'] = $data; return $this;}
      public function priorities($data) {$this->data['priorities'] = $data; return $this;}
      public function cats($data) {$this->data['cats'] = $data; return $this;}
      public function tags($data) {$this->data['tags'] = $data; return $this;}
      public function default_view($data) {$this->data['default_view'] = $data; return $this;}
      public function rows($data) {$this->data['rows'] = $data; return $this;}
  }
/**
  * CFAComponentForm Class component
  */
  class CFAComponentForm
  {
      var $type = 'form';
      var $index = 0;
      var $data = [];
      
      public function __construct() { $this->data['uniqid'] = uniqid('form');}
      public function label($data) {$this->data[$this->index]['label'] = $data; return $this;}
      public function add($label='') {if(isset($this->data[$this->index]) &&$this->data[$this->index]) $this->index++; if($label) $this->data[$this->index]['label']=$label; return $this;}
  }