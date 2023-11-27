<?php

/**
 * [$cfa = $this->core->loadClass('CFA');] Class CFA to handle WebApps for CloudFrameworkInterface
 * notion: xxxx
 * last_update: 20220226
 * @package CoreClasses
 */
class CFA
{
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
        if(!isset($this->labels[$label])) $this->labels[$label] = new CFACompenent();
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
        return $only_label?['components'=>$this->data['components']]:$this->data;
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
class CFACompenent
{

    var $component = null;

    public function header($title='') {
        if(!is_object($this->component) || get_class($this->component)!= 'CFACompenentHeader')
            $this->component = new CFACompenentHeader();
        if($title) $this->component->title($title);
        return($this->component);
    }

    public function boxes() {
        if(!is_object($this->component) || get_class($this->component)!= 'CFACompenentBoxes')
            $this->component = new CFACompenentBoxes();
        return($this->component);
    }

    public function html() {
        if(!is_object($this->component) || get_class($this->component)!= 'CFACompenentHTML')
            $this->component = new CFACompenentHTML();
        return($this->component);
    }

    public function cols() {
        if(!is_object($this->component) || get_class($this->component)!= 'CFACompenentCols')
            $this->component = new CFACompenentCols();
        return($this->component);
    }

    public function panels() {
        if(!is_object($this->component) || get_class($this->component)!= 'CFACompenentPanels')
            $this->component = new CFACompenentPanels();
        return($this->component);
    }

    public function divs() {
        if(!is_object($this->component) || get_class($this->component)!= 'CFACompenentDivs')
            $this->component = new CFACompenentDivs();
        return($this->component);
    }

    public function titles() {
        if(!is_object($this->component) || get_class($this->component)!= 'CFACompenentTitles')
            $this->component = new CFACompenentTitles();
        return($this->component);
    }

    public function buttons() {
        if(!is_object($this->component) || get_class($this->component)!= 'CFACompenentButtons')
            $this->component = new CFACompenentButtons();
        return($this->component);
    }

    public function formSelect() {
        if(!is_object($this->component) || get_class($this->component)!= 'CFACompenentFormSelect')
            $this->component = new CFACompenentFormSelect();
        return($this->component);
    }

    public function breadcrumb() {
        if(!is_object($this->component) || get_class($this->component)!= 'CFACompenentBreadcrumb')
            $this->component = new CFACompenentBreadcrumb();
        return($this->component);
    }

    public function pageBreadcrumb() {
        if(!is_object($this->component) || get_class($this->component)!= 'CFACompenentPageBreadcrumb')
            $this->component = new CFACompenentPageBreadcrumb();
        return($this->component);
    }

    public function tabs() {
        if(!is_object($this->component) || get_class($this->component)!= 'CFACompenentTabs')
            $this->component = new CFACompenentTabs();
        return($this->component);
    }

    public function tags() {
        if(!is_object($this->component) || get_class($this->component)!= 'CFACompenentTags')
            $this->component = new CFACompenentTags();
        return($this->component);
    }

    public function alerts() {
        if(!is_object($this->component) || get_class($this->component)!= 'CFACompenentAlerts')
            $this->component = new CFACompenentAlerts();
        return($this->component);
    }

    public function searchCards() {
        if(!is_object($this->component) || get_class($this->component)!= 'CFACompenentSearchCards')
            $this->component = new CFACompenentSearchCards();
        return($this->component);
    }

    public function searchInput() {
        if(!is_object($this->component) || get_class($this->component)!= 'CFACompenentSearchInput')
            $this->component = new CFACompenentSearchInput();
        return($this->component);
    }

    public function calendar() {
        if(!is_object($this->component) || get_class($this->component)!= 'CFACompenentCalendar')
            $this->component = new CFACompenentCalendar();
        return($this->component);
    }

    public function table() {
        if(!is_object($this->component) || get_class($this->component)!= 'CFACompenentTable')
            $this->component = new CFACompenentTable();
        return($this->component);
    }

    public function accordion() {
        if(!is_object($this->component) || get_class($this->component)!= 'CFACompenentAccordion')
            $this->component = new CFACompenentAccordion();
        return($this->component);
    }

    public function chart() {
        if(!is_object($this->component) || get_class($this->component)!= 'CFACompenentChart')
            $this->component = new CFACompenentChart();
        return($this->component);
    }

    public function jsonEditor() {
        if(!is_object($this->component) || get_class($this->component)!= 'CFACompenentJsonEditor')
            $this->component = new CFACompenentJsonEditor();
        return($this->component);
    }

    public function codeFragment() {
        if(!is_object($this->component) || get_class($this->component)!= 'CFACompenentCodeFragment')
            $this->component = new CFACompenentCodeFragment();
        return($this->component);
    }

    public function progressChart() {
        if(!is_object($this->component) || get_class($this->component)!= 'CFACompenentProgressChart')
            $this->component = new CFACompenentProgressChart();
        return($this->component);
    }

    public function boxInfo() {
        if(!is_object($this->component) || get_class($this->component)!= 'CFACompenentBoxInfo')
            $this->component = new CFACompenentBoxInfo();
        return($this->component);
    }

    public function kanban() {
        if(!is_object($this->component) || get_class($this->component)!= 'CFACompenentKanban')
            $this->component = new CFACompenentKanban();
        return($this->component);
    }
    public function peopleCard() {
        if(!is_object($this->component) || get_class($this->component)!= 'CFACompenentPeopleCard')
            $this->component = new CFACompenentPeopleCard();
        return($this->component);
    }
    public function taskTable() {
        if(!is_object($this->component) || get_class($this->component)!= 'CFACompenentTaskTable')
            $this->component = new CFACompenentTaskTable();
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
 * CFACompenentHeader Class component
 */
class CFACompenentHeader
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
    public function subtitle($data) {$this->data['subtitle'] = $data; return $this;}
    public function jsIconCall($js_function,$icon) {$this->data['js-call'] = $js_function;$this->data['js-ico'] = $icon; return $this;}
}

/**
 * CFACompenentJsonEditor Class component
 */
class CFACompenentJsonEditor
{

    var $type = 'json-editor';
    var $data = [
        'json'=>null
    ];
    public function __construct() { $this->data['id'] = uniqid('json-editor');}
    public function json($data) {$this->data['json'] = $data; return $this;}
    public function type($data) {$this->data['type'] = $data; return $this;}
}

/**
 * CFACompenentCodeFragment Class component
 */
class CFACompenentCodeFragment
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
 * CFACompenentProgressChart Class component
 */
class CFACompenentProgressChart
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
 * CFACompenentBoxInfo Class component
 */
class CFACompenentBoxInfo
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
 * CFACompenentHTML Class component
 */
class CFACompenentHTML
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
    public function textarea($data,$label='') {$this->data['html'].= "<textarea cols='90' rows='10'".(($label)?' id="'.$label.'"':'').">{$data}</textarea>";return $this;}
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
 * CFACompenentBoxes Class component
 */
class CFACompenentTitles
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
    public function addBadge($title,$color='',$border=false,$pill=false) {if(!isset($this->data[$this->index]['badges'])) $this->data[$this->index]['badges']=[]; $this->data[$this->index]['badges'][] = ['title'=>$title,'color'=>$color,'border'=>(bool)$border,'pill'=>(bool)$pill]; return $this;}
    public function addLeftPhoto($src,$alt='') {if(!isset($this->data[$this->index]['left-photos'])) $this->data[$this->index]['left-photos']=[]; $this->data[$this->index]['left-photos'][] = ['url'=>$src,'alt'=>$alt]; return $this;}
    public function addRightPhoto($src,$alt='') {if(!isset($this->data[$this->index]['right-photos'])) $this->data[$this->index]['right-photos']=[]; $this->data[$this->index]['right-photos'][] = ['url'=>$src,'alt'=>$alt]; return $this;}
    public function addPhoto($src,$alt='') {if(!isset($this->data[$this->index]['photos'])) $this->data[$this->index]['photos']=[]; $this->data[$this->index]['photos'][] = ['url'=>$src,'alt'=>$alt]; return $this;}

}
/**
 * CFACompenentBoxes Class component
 */
class CFACompenentBoxes
{

    var $type = 'boxes';
    var $index =0;
    var $data = [];

    public function add($title='') {if(isset($this->data[$this->index]) &&$this->data[$this->index]) $this->index++; if($title) $this->data[$this->index]['title']=$title; return $this;}
    public function title($data) {$this->data[$this->index]['title'] = $data; return $this;}
    public function ico($data) {$this->data[$this->index]['ico'] = $data; return $this;}
    public function color($data) {$this->data[$this->index]['color'] = $data; return $this;}
    public function total($data) {$this->data[$this->index]['total'] = $data; return $this;}
}
/**
 * CFACompenentCols Class component
 */
class CFACompenentCols
{

    var $type = 'cols';
    var $index =0;
    var $data = [];
    public function add($label='') {if(isset($this->data[$this->index]) &&$this->data[$this->index]) $this->index++; if($label) $this->data[$this->index]['label']=$label; return $this;}
    public function label($data) {$this->data[$this->index]['label'] = $data; return $this;}
    public function size($data) {$this->data[$this->index]['size'] = $data; return $this;}
}
/**
 * CFACompenentPanels Class component
 */
class CFACompenentPanels
{

    var $type = 'panels';
    var $index =0;
    var $data = [];
    public function add($label='') {if(isset($this->data[$this->index]) &&$this->data[$this->index]) $this->index++; if($label) $this->data[$this->index]['label']=$label; return $this;}
    public function label($data) {$this->data[$this->index]['label'] = $data; return $this;}
    public function size($data) {$this->data[$this->index]['size'] = $data; return $this;}
    public function locked($data) {$this->data[$this->index]['locked'] = (bool)$data; return $this;}
    public function collapse($data) {$this->data[$this->index]['collapse'] = (bool)$data; return $this;}
    public function show($data) {$this->data[$this->index]['show'] = (bool)$data; return $this;}
}
/**
 * CFACompenentDivs Class component
 */
class CFACompenentDivs
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
 * CFACompenentButtons Class component
 */
class CFACompenentButtons
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
 * CFACompenentFormSelect Class component
 */
class CFACompenentFormSelect
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
 * CFACompenentBreadcrumb Class component
 */
class CFACompenentBreadcrumb
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
 * CFACompenentBreadcrumb Class component
 */
class CFACompenentPageBreadcrumb
{
    var $type = 'pageBreadcrumb';
    var $data = [
        'label'=>null,
        'elements'=>[],
    ];
    public function label($data) {$this->data['label'] = $data; return $this;}
    public function addElement($title,$url='',$active=false) {
        if(!isset($this->data['elements'])) $this->data['elements']=[];
        $obj = new stdClass();
        $obj->title = $title;
        $obj->url = $url;
        $obj->active = $active;
        $this->data['elements'][] = $obj;
        return $this;
    }
}

/**
 * CFACompenentTabs Class component
 */
class CFACompenentTabs
{
    var $type = 'tabs';
    var $index = 0;
    var $data = [];

    public function add($label, $title, $icon = "",$active=false)
    {
        if (isset($this->data[$this->index]) && $this->data[$this->index]) $this->index++;
        $this->data[$this->index]['label'] = $label;
        $this->data[$this->index]['title'] = $title;
        if ($icon) $this->data[$this->index]['ico'] = $icon;
        if ($active) $this->data[$this->index]['active'] = true;
        return $this;
    }
    public function onclick($data) {$this->data[$this->index]['onclick'] = $data; return $this;}
}

/**
 * CFACompenentSearchCards Class component
 */
class CFACompenentSearchCards
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
 * CFACompenentSearchInput Class component
 */
class CFACompenentSearchInput
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
 * CFACompenentTags Class component
 */
class CFACompenentTags
{
    var $type = 'tags';
    var $index =0;
    var $data = [];
    public function add($label='') {if(isset($this->data[$this->index]) &&$this->data[$this->index]) $this->index++; if($label) $this->data[$this->index]['label']=$label; return $this;}
    public function label($data) {$this->data[$this->index]['label'] = $data; return $this;}
    public function title($data) {$this->data[$this->index]['title'] = $data; return $this;}
}


/**
 * CFACompenentAlerts Class component
 */
class CFACompenentAlerts
{
    var $type = 'alerts';
    var $index =0;
    var $data = [];
    public function add($label='') {if(isset($this->data[$this->index]) &&$this->data[$this->index]) $this->index++; if($label) $this->data[$this->index]['label']=$label; return $this;}
    public function label($data) {$this->data[$this->index]['label'] = $data; return $this;}
    public function content($data) {$this->data[$this->index]['content'] = $data; return $this;}
    public function color($data) {$this->data[$this->index]['color'] = $data; return $this;}
    public function icon($data) {$this->data[$this->index]['ico'] = $data; return $this;}
    public function onclick($data) {$this->data[$this->index]['onclick'] = $data; return $this;}
    public function addPhoto($src,$alt='') {if(!isset($this->data[$this->index]['photo'])) $this->data[$this->index]['photo']=[]; $this->data[$this->index]['photo'][] = ['url'=>$src,'alt'=>$alt]; return $this;}
    public function jsIconCall($js_function,$icon) {$this->data[$this->index]['js-call'] = $js_function;$this->data[$this->index]['js-ico'] = $icon; return $this;}
    public function addBadge($title,$color='',$border=false,$pill=false) {if(!isset($this->data[$this->index]['badges'])) $this->data[$this->index]['badges']=[]; $this->data[$this->index]['badges'][] = ['title'=>$title,'color'=>$color,'border'=>(bool)$border,'pill'=>(bool)$pill]; return $this;}


}

/**
 * CFACompenentCalendar Class component
 */
class CFACompenentCalendar
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
 * CFACompenentAccordion Class component
 */
class CFACompenentAccordion
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
 * CFACompenentTable Class component
 */
class CFACompenentTable
{
    var $type = 'table';
    var $index = 0;
    var $data = [];
    public function __construct($label='') { if($label) $this->data['label'] = $label;}
    public function label($label) {$this->data['label']=$label; return $this;}
    public function compact($bool=true) {$this->data['compact']=(bool)$bool; return $this;}
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
 * CFACompenentChart Class component
 */
class CFACompenentChart
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
 * CFACompenentKanban Class component
 */
class CFACompenentKanban
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
        array_push($this->data['items'][$this->boardIndex]['item'], json_decode($data));
        return $this;
    }

    public function board($data) {
        array_push( $this->data['items'], json_decode($data));
        return $this;
    }

    public function items($data) {$this->data['items'] = $data; return $this;}
}

/**
 * CFACompenentPeopleCard Class component
 */
class CFACompenentPeopleCard
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
 * CFACompenentPeopleCard Class component
 */
class CFACompenentTaskTable
{
    var $type = 'task-table';
    var $index = 0;
    var $data = [];

    public function __construct() { $this->data['uniqid'] = uniqid('people-card');}
    public function title($data) {$this->data['title'] = $data; return $this;}
    public function cols($data) {$this->data['cols'] = $data; return $this;}
    public function statuses($data) {$this->data['statuses'] = $data; return $this;}
    public function priorities($data) {$this->data['priorities'] = $data; return $this;}
    public function rows($data) {$this->data['rows'] = $data; return $this;}
}