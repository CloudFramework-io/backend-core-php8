<?php
// Report Class v1
if (!defined ("_CloudServiceReporting_CLASS_") ) {
    define("_CloudServiceReporting_CLASS_", TRUE);

    /**
     * Class to facilitate reporting creating data cubes
     * author: hl@cloudframework.io
     * @package LabClasses
     */
    class ReportCube
    {
        var $data;
        var $reports = [];
        function __construct($data)
        {
            $this->data = $data;
            if(!is_array($this->data)) $this->data = [];
        }

        function reduce($conds,$fields='*') {
            $data = [];
            if($fields != '*') $fields = explode(',',$fields);

            if(is_array($conds)) {
                if(!is_array($conds[0])) $conds = [$conds];
                foreach ($this->data as $row) {
                    $add = true;
                    foreach ($conds as $cond)  {

                        if(!isset($row[$cond[0]])) $row[$cond[0]] = '_empty_';
                        if(!strlen($row[$cond[0]])) $row[$cond[0]] = '_empty_';
                        if(is_string($cond[2]) && !strlen($cond[2])) $cond[2]= '_empty_';
                        switch ($cond[1]) {
                            case "=":
                                if(!($row[$cond[0]]==$cond[2])) $add = false;
                                break;
                            case "!=":
                                if(!($row[$cond[0]]!=$cond[2])) $add = false;
                                break;
                            case ">":
                                if(!($row[$cond[0]]>$cond[2])) $add = false;
                                break;
                            case ">=":
                                if(!($row[$cond[0]]>=$cond[2])) $add = false;
                                break;
                            case "<":
                                if(!($row[$cond[0]]<$cond[2])) $add = false;
                                break;
                            case "<=":
                                if(!($row[$cond[0]]<=$cond[2])) $add = false;
                                break;
                            case "in":
                                if(!is_array($cond[2])) $cond[2] = [$cond[2]];
                                if(!(array_search($row[$cond[0]],$cond[2])!== false)) $add = false;
                                break;
                            case "contain":
                                $add = strpos($row[$cond[0]],$cond[2])!== false;
                                break;

                            case "not contain":
                                $add = strpos($row[$cond[0]],$cond[2])=== false;
                                break;
                            default:
                                $add = true;
                                break;
                        }
                        if(!$add) break;
                    }

                    if($add) {
                        if(is_array($fields)) foreach ($row as $key=>$value) if(!in_array($key,$fields)) unset($row[$key]);
                        $data[] = $row;
                    }
                }
            }
            return new ReportCube($data);
        }

        /* Counting functions */
        function count($filter=[]) {
            if (is_array($filter) && count($filter)) {
                if(!is_array($filter[0])) $filter = [$filter];
                $data = $this->data;
                Report::filterData($data,'*',$filter,0);
            } else {
                $data = &$this->data;
            }
            return count($data);
        }
        function sum($field,$filter=[]) {
            if (is_array($filter) && count($filter)) {
                if(!is_array($filter[0])) $filter = [$filter];
                $data = $this->data;
                Report::filterData($data,$field,$filter,0);
            } else {
                $data = &$this->data;
            }
            return array_sum(array_column($data,$field));
        }
        function countInFields($fields) { return $this->_count($fields,false);  }
        function distinctCountInFields($fields=null) { return $this->_count($fields,true); }
        private  function _count($fields=null,$distinct=false,$filter=[]) {
            if(null == $fields && (!is_array($filter) || !count($filter))) return count($this->data);
            else {
                $ret = [];
                $distinctValues = [];
                if(null == $fields) $fields='*';
                if($fields=='*') $fields = $this->fields();
                if(is_string($fields)) $fields = explode(',',$fields);
                if(!is_array($fields)) return [];
                else {



                    foreach ($this->data as $item) {

                        foreach ($fields as $field) if (strlen(trim($field))) {
                            $add = 0;
                            if (strlen($item[$field])) {
                                if ($distinct) {
                                    if (!isset($distinctValues[$field][$item[$field]])) {
                                        $distinctValues[$field][$item[$field]] = true;
                                        $add = 1;
                                    }
                                } else
                                    $add = 1;
                            }
                            $ret[$field] += $add;
                        }
                    }
                }
                return $ret;
            }
        }

        function countBy($fields,$sort='desc',$tsort='value',$limit='') {
            list($field,$others) = explode(',',$fields,2);
            $ret = [];
            foreach ($this->data as $item) {
                $ret[$item[$field]]++;
            }
            // Sorting result
            $this->_sort($ret,$sort,$tsort);
            // slice if $limit
            if(strlen($limit) && $limit >0)  $ret = array_slice($ret,0,$limit);

            // Let's see if there is more fields to call recursively
            if(!empty($others)) {
                foreach ($ret as $key=>$count) {
                    $cube = $this->reduce([$field,'=',$key]);
                    $ret[$key] = $cube->countBy($others);
                }
            }


            return $ret;
        }

        function distinctCountBy($fields,$distinct_fields,$sort='desc',$tsort='value',$limit='') {
            list($field,$others) = explode(',',$fields,2);
            $ret = [];
            if(!is_array($distinct_fields)) $distinct_fields = [$distinct_fields];

            $distinct_values = [];
            foreach ($this->data as $item) {
                $distinct_value = [];
                foreach ($distinct_fields as $distinct_field)
                    $distinct_value[$distinct_field] = $item[$distinct_field];

                $distinct_hash = sha1(json_encode($distinct_value));
                if(!isset($distinct_values[$distinct_hash])) {
                    $ret[$item[$field]]++;
                    $distinct_values[$distinct_hash] = true;
                }
            }

            // Sorting result
            $this->_sort($ret,$sort,$tsort);
            // slice if $limit
            if(strlen($limit) && $limit >0)  $ret = array_slice($ret,0,$limit);

            // Let's see if there is more fields to call recursively
            if(!empty($others)) {
                foreach ($ret as $key=>$count) {
                    $cube = $this->reduce([$field,'=',$key]);
                    $ret[$key] = $cube->distinctCountBy($others,$distinct_fields);
                }
            }


            return $ret;
        }

        function sumBy($field,$value,$sort='desc',$tsort='value',$limit='') {
            $ret = [];
            foreach ($this->data as $item) {
                $ret[$item[$field]] = $this->sum($value,[[$field,"=",$item[$field]]]);
            }
            // Sorting result
            $this->_sort($ret,$sort,$tsort);
            // slice if $limit
            if(strlen($limit) && $limit >0)  $ret = array_slice($ret,0,$limit);


            return $ret;
        }
        function showBy($field,$sort='desc',$fields='*',$limit='') {
            $ret = $this->countBy($field,$sort,'key',$limit);
            foreach (array_keys($ret) as $key_value) {
                foreach ($this->data as $row) if($row[$field] == $key_value) {
                    if(!is_array($ret[$key_value])) $ret[$key_value]=[];
                    $ret[$key_value][] = $row;
                }
            }


            return $ret;
        }

        /* Report methods */
        function addCol($report,$field,$data) { $this->add('cols',$report,$field,$data);}
        function addRow($report,$field,$data) { $this->add('rows',$report,$field,$data);}
        function addValue($report,$op,$fields='*') { $this->add('values',$report,$op,$fields);}
        private function add($type,$report,$field,$value) { $this->reports[$report][$type][] = [$field,$value]; }
        function initReport($report) {$this->reports[$report] = [];}
        function getReport($report) {
            $ret = [];
            if(!is_array($this->reports[$report])) return $ret;
            if(!is_array($this->reports[$report]['rows'])) $this->reports[$report]['rows'] = [];
            if(!is_array($this->reports[$report]['cols'])) $this->reports[$report]['cols'] = [];
            if(!is_array($this->reports[$report]['values'])) $this->reports[$report]['values'] = [['count','*']];
            $this->recursiveCell($ret,$report);
            return $ret;
        }
        private function recursiveCell(&$ret,&$report,$typeCell='rows',$query=[],$i=0) {

            $field = $data = null;
            // Aggregate Cell.
            if(isset($this->reports[$report][$typeCell][$i][0]))
                $field = &$this->reports[$report][$typeCell][$i][0];

            if(isset($this->reports[$report][$typeCell][$i][1]))
                $data = &$this->reports[$report][$typeCell][$i][1];

            // If there is data
            if(is_array($data))
                foreach ($data as $j => $row) {
                    $this->recursiveCell($ret[$row], $report, $typeCell, array_merge($query,[[$field, '=', $row]]), $i + 1);
                }

            // else If I receive rows then next will be cols
            elseif($typeCell=='rows')
                $this->recursiveCell($ret, $report, 'cols', $query, 0);

            // If I receive rows then next will be cols
            else {
                $data = $this->reduce($query);
                foreach ($this->reports[$report]['values'] as $value) {
                    switch ($value[0]) {
                        case 'count':
                            // If there is a filter
                            if (is_array($value[1])) {
                                $res = $data->reduce($value[1])->count();
                            } else {
                                $res = $data->count();
                            }

                            break;
                        case 'sum':
                            $res = $data->sum($value[1]);
                            break;
                        default:
                            $res = ['filter' => $query, 'values' => $this->reports[$report]['values']];
                            break;
                    }

                    // Assigning
                    if (!is_array($ret) && strlen($ret)) $ret = [$ret];
                    if (is_array($ret)) $ret[] = $res;
                    else $ret = $res;
                }

            }
        }




        function _sort(&$data,$sort,$tsort) {
            if(strtolower($sort)=='desc') {
                if(strtolower($tsort)=='value')
                    arsort($data);
                else
                    krsort($data);
            } else {
                if(strtolower($tsort)=='value')
                    asort($data);
                else
                    ksort($data);
            }
        }

        /* Subsets */
        function export($fields='*') {

            function replaceForExport(&$content) {
                $content = str_replace("\r","__r__",$content);
                $content = str_replace("\n","__n__",$content);
                $content = str_replace("\t","__t__",$content);
            };

            function reduceForExport($ret, $content) {
                array_walk($content,'replaceForExport');
                $ret .= implode("\t",$content)."\n";
                return $ret;
            };

            $ret = $this->_get($fields);
            $export = '';
            if(is_array($ret[0])) {
                $export = implode("\t",array_keys($ret[0]))."\n";
                $export.= array_reduce($ret,'reduceForExport');
                unset($ret);
            }
            return $export;
        }


        function get($fields='*') { return($this->_get($fields)); }
        function values($fields='*',$order='asc') { return($this->_get($fields,true,false,$order)); }
        function distinctValues($fields='*',$order='asc') { return($this->_get($fields,true,true,$order)); }
        private function _get($fields='*',$values=false,$distinct=false,$order='asc') {
            if($fields=='*') return $this->data;
            else {
                $ret = [];
                $distinctRet = [];
                if(is_string($fields)) $fields = explode(',',$fields);
                if(!is_array($fields)) return [];
                else foreach ($this->data as $item) {
                    $row = '';
                    $i=0;
                    foreach ($fields as $field) if(strlen(trim($field))){
                        $field = trim($field);
                        if($values) {
                            if($i++>0) $row.=',';
                            $row.= (strlen($item[$field]))?$item[$field]:'_empty_';
                        }else
                            $row[$field] = $item[$field];
                    }
                    if($distinct)
                        $ret[json_encode($row)] = $row;
                    else
                        $ret[] = $row;

                }
                if($order=='desc')
                    krsort($ret);
                else
                    ksort($ret);
                return array_values($ret);
            }
        }

        /* About Columns */
        function fields() {
            if(is_array($this->data[0])) return(array_keys($this->data[0]));
            elseif(is_array($this->data)) return(array_keys($this->data));
            else return [];
        }

        /* to Transformation */
        function toYear($field1,$field2) { $this->_to('year',$field1,$field2);}
        function toYearMonth($field1,$field2) { $this->_to('yearmonth',$field1,$field2);}
        function toDate($field1,$field2) { $this->_to('date',$field1,$field2);}
        function toHour($field1,$field2) { $this->_to('hour',$field1,$field2);}
        function toMin($field1,$field2) { $this->_to('min',$field1,$field2);}
        function toString($field1,$field2) { $this->_to('string',$field1,$field2);}
        private function _to($to,$field1,$field2) {
            foreach ($this->data as $i=>$item) {
                switch ($to) {
                    case 'year':
                        $this->data[$i][$field2] = substr($this->data[$i][$field1],0,4);
                        break;
                    case 'yearmonth':
                        $this->data[$i][$field2] = substr($this->data[$i][$field1],0,7);
                        break;
                    case 'date':
                        $this->data[$i][$field2] = substr($this->data[$i][$field1],0,10);
                        break;
                    case 'hour':
                        $this->data[$i][$field2] = substr($this->data[$i][$field1],11,2);
                        break;
                    case 'min':
                        $this->data[$i][$field2] = substr($this->data[$i][$field1],14,2);
                        break;
                    case 'string':
                        $this->data[$i][$field2] =$field1;
                        foreach ($item as $key=>$value) if(strpos($this->data[$i][$field2],'{{'.$key.'}}')!==false){
                            $this->data[$i][$field2] = str_replace('{{'.$key.'}}',$value,$this->data[$i][$field2]);
                        }
                        break;
                }
            }
        }
        function joinCube($field1,$field2,$data) {
            if(count($data)>0 && is_array($data[0]) && key_exists($field2,$data[0]) && count($this->data) && is_array($this->data[0]) && isset($this->data[0][$field1])) {
                $emptyRecord = array_fill_keys(array_keys($data[0]),'');
                $data = array_column($data,null,$field2);
                foreach ($this->data as $i=>$item)
                    if(is_array($data[$item[$field1]])){
                        $this->data[$i] = array_merge($this->data[$i],$data[$item[$field1]]);
                    } else {
                        $this->data[$i] = array_merge($this->data[$i],$emptyRecord);
                    }
            }
        }

        function toRange($field1,$field2,$ranges) {
            if(!is_array($ranges)) return false;
            foreach ($this->data as $i=>$item) {
                $match = false;
                foreach ($ranges as $j => $conds) {
                    foreach ($conds as $newValue => $cond) {
                        if (!is_array($cond) && $cond == "*") $match = true;
                        if (is_array($cond) && array_search($item[$field1], $cond) !== false) $match = true;
                        if ($match) {
                            $this->data[$i][$field2] = $newValue;
                            break;
                        }
                    }
                    if($match) break;
                }
                if(!$match) $this->data[$i][$field2] = '';
            }
        }
    }

    class Report
    {
        var $error = false;
        var $errorMsg = '';
        var $core = null;

        function __construct(Core7 &$core, $params)
        {
            $this->core = $core;
        }

        function setCube($cube,$data) {
            if(!strlen(trim($cube))) return false;
            $this->core->cache->set('Report_'.$cube,$data);
            return new ReportCube($data);

        }


        function resetCube($cube) {
            $this->core->cache->delete('Report_'.$cube);
        }

        function getCube($cube,$fields='*',$filter=[],$limit=0,$expire=-1) {
            if(!strlen(trim($cube))) return false;
            if(!strlen(trim($expire))) $expire=-1;
            if(!strlen(trim($limit))) $limit=0;

            $ret = false;
            if(!isset($_GET['_reloadReports'])) {
                $data = $this->core->cache->get('Report_' . $cube, $expire);
                if(is_array($data)) {
                    $this->filterData($data,$fields,$filter,$limit);
                    $ret =  new ReportCube($data);
                }
                unset($data);
            }
            return $ret;
        }

        function getCachedCube($cube,$fields='*',$filter=[],$limit=0) {
            if(!strlen(trim($cube))) return false;
            if(!strlen(trim($limit))) $limit=0;

            $ret = false;
            $data = $this->core->cache->get('Report_' . $cube);
            if(is_array($data)) {
                $this->filterData($data,$fields,$filter,$limit);
                $ret =  new ReportCube($data);
            }
            unset($data);
            return $ret;
        }

        static function filterData(&$data,$fields,$filter,$limit)
        {
            if (!is_array($fields) && strlen($fields)  && $fields != '*') $fields = explode(',', $fields);
            if (is_array($filter) && count($filter)) {
                $newdata =[];
                foreach ($data as $i => $row) {
                    $add = true;
                    foreach ($filter as $cond)  {
                        if (!isset($row[$cond[0]])) $row[$cond[0]] = '_empty_';
                        if (!strlen($row[$cond[0]])) $row[$cond[0]] = '_empty_';
                        switch ($cond[1]) {
                            case "=":
                                if (!($row[$cond[0]] == $cond[2])) $add = false;
                                break;
                            case "!=":
                                if (!($row[$cond[0]] != $cond[2])) $add = false;
                                break;
                            case ">":
                                if (!($row[$cond[0]] > $cond[2])) $add = false;
                                break;
                            case ">=":
                                if (!($row[$cond[0]] >= $cond[2])) $add = false;
                                break;
                            case "<":
                                if (!($row[$cond[0]] < $cond[2])) $add = false;
                                break;
                            case "<=":
                                if (!($row[$cond[0]] <= $cond[2])) $add = false;
                                break;
                            case "in":
                                if(!is_array($cond[2])) $cond[2] = [$cond[2]];
                                if(!(array_search($row[$cond[0]],$cond[2])!== false)) $add = false;
                                break;
                            case "contain":
                                $add = strpos($row[$cond[0]],$cond[2])!== false;
                                break;
                            case "not contain":
                                $add = strpos($row[$cond[0]],$cond[2])=== false;
                                break;
                            default:
                                $add=true;
                                break;
                        }
                        if(!$add) break;
                    }

                    // Filter info if it is necessary
                    if ($add) {
                        if (is_array($fields)) foreach ($row as $key => $value) if (!in_array($key, $fields)) unset($row[$key]);
                        $newdata[]=$row;
                    }
                    unset($data[$i]);
                }
                $data = $newdata;
            }
            if($limit>0) {
                $data = array_slice($data,0,$limit);
            }
        }

        function addError($value)
        {
            $this->error = true;
            $this->errorMsg[] = $value;
        }
    }
}
