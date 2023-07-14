<?php
class ztime extends cmsFrontend {

    public $ctypes;
    public $ctypes_photo;
    public $preset;
    public $select_fields;
    public $limit;
    public $page;
    public $perpage;
    protected $useOptions = true;

    public function actionIndex() {

        //тут навесить проверок корректности настроек компонента
        //например, имен полей

        return $this->cms_template->render('index', [
            'title' => $this->getOption('title') ? $this->getOption('title') : 'Тайм-лента'
        ]);

    }

    /*
     * $type_id - id записи при item, или страницы при list
     */
    public function actionContent($type = 'list', $type_id = false) {

        $this->preset = $this->getOption('preset') ? $this->getOption('preset') : 'content_list';
        $this->perpage = 10;
        $this->ctypes = $this->getOption('ctypes');
        $this->ctypes_photo = $this->filterCtypeWithPhoto();
        $this->select_fields = ['title', 'photo', 'date_pub'];

        if ($type === 'list') {

            $this->page = $type_id ? $type_id : 1;//номер страницы

            $items = $this->getFilteredCtypesItems();
            return $this->cms_template->renderJSON($items);
        }
        if ($type === 'item'){
            if(!$type_id){
                return cmsCore::error404();
            }
            list($ctype_name, $id) = explode('_',$type_id);//id записи
            if(!in_array($ctype_name, $this->ctypes)){
                return cmsCore::error404();
            }

            $item = $this->getFilteredCtypesItem($ctype_name,$id);
            return $this->cms_template->renderJSON($item);
        }

    }

    public function filterCtypeWithPhoto() {
        $ctypes_photo = [];
        foreach ($this->ctypes as $ctype_name){
            $table_fields = $this->model_content->table_prefix . $ctype_name . '_fields';
            $fields = $this->model_content->get($table_fields,false,'name');
            if(key_exists('photo', $fields)){
                $ctypes_photo[] = $ctype_name;
            }
        }
        return $ctypes_photo;
    }

    public function getFilteredCtypesItem($ctype_name,$id) {
        $model       = cmsCore::getModel('content');
        $table = $model->table_prefix . $ctype_name;
        $ctype = $model->getContentTypeByName($ctype_name);
        return $model
                    ->orderBy('date_pub', 'DESC')
                    ->getItemById($table, $id, function($item, $model) use($ctype) {
                $data = $this->makeDataFromItem($item,$ctype);
                $content = cmsEventsManager::hook('html_filter', [
                    'text' => $item['content']
                ]);
                $content = string_replace_svg_icons($content);
                $data['content'] = $content;
                return $data;
            });
    }

    public function getFilteredCtypesItems() {

        $model       = cmsCore::getModel('content');
        $select_only = '';

        foreach ($this->ctypes as $key => $ctype_name) {
            $is_last = empty($this->ctypes[$key + 1]);
            $table   = $model->table_prefix . $ctype_name;

            $fields     = [];
            $tbl_prefix = $is_last ? 'i' : $ctype_name;

            foreach ($this->select_fields as $field) {
                if(($field === 'photo') && !in_array($ctype_name, $this->ctypes_photo)){
                    $fields[] = "null AS photo";
                    continue;
                }
                $fields[] = "$tbl_prefix.$field AS $field";
            }
            $fields[]    = "$tbl_prefix.id AS id";
            $fields[]    = "'$ctype_name' AS ctype_name";
            $select_only .= implode(', ', $fields);
            if (!$is_last) {
                $select_only .= " FROM {#}$table $tbl_prefix UNION SELECT ";
            }
        }

        $model->selectOnly($select_only);
        $model->orderByRaw('date_pub DESC');
        $model->limitPage($this->page,$this->perpage);

        return $model->get($table, function($item, $model) {
                $ctype = $model->getContentTypeByName($item['ctype_name']);
                return $this->makeDataFromItem($item,$ctype);
            }, false);

    }

    public function makeDataFromItem($item,$ctype) {
        $preset = $this->preset;
        $data = [
            'id'    => $ctype['name'] . '_' .$item['id'],
            'title' => $item['title'],
            'date'  => $this->getDateText($item['date_pub']),
            'type' => $ctype['title']
        ];
        if (!empty($item['photo'])) {
            $photos = cmsModel::yamlToArray($item['photo']);
            if (!empty($photos[$preset])) {
                $data['image'] = $photos[$preset];
            }
        }
        return $data;

    }

    public function getDateText($date) {
        $today = date('Y-m-d');
        if($today === date('Y-m-d', strtotime($date))){
            return string_date_format($date, true);
        }
        return string_date_format($date);
    }

}
