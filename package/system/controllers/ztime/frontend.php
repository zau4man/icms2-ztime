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
            $types = $this->request->get('types','');
            $ctypes = false;
            if($types){
                $this->setUserTypes($types);
                $ctypes = explode(',', $types);
            }
            $search = $this->request->get('search','');
            $items = $this->getFilteredCtypesItems($ctypes,$search);
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

    public function getCtypes() {

        $this->ctypes = $this->getOption('ctypes');
        $ctypes = $this->model_content->filterIn('name',$this->ctypes)->get('content_types',false,'name');
        return $ctypes;

    }

    public function actionTypes() {

        if(!$this->getOption('is_types')){
            return $this->cms_template->renderJSON([]);
        }

        $ctypes = $this->getCtypes();
        $user_types = $this->getUserTypes();
        $ctypes_names = [];
        foreach ($ctypes as $ctype){
            $ctypes_names[] = [
                'id' => $ctype['id'],
                'name' => $ctype['name'],
                'title' => $ctype['title'],
                'active' => $user_types ? (in_array($ctype['name'], $user_types) ? 1 : 0) : 1
            ];
        }

        if(count($ctypes_names) == 1){
            $ctypes_names = [];
        }

        return $this->cms_template->renderJSON($ctypes_names);
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
        $is_bar = $this->getOption('is_bar');
        return $model
                    ->orderBy('date_pub', 'DESC')
                    ->getItemById($table, $id, function($item, $model) use($ctype,$is_bar) {
                $data = $this->makeDataFromItem($item,$ctype);
                $content = cmsEventsManager::hook('html_filter', [
                    'text' => $item['content']
                ]);
                $content = string_replace_svg_icons($content);
                $data['content'] = $content;
                if($is_bar && $ctype['is_comments']){
                    $data['comments'] = $item['comments'];
                }
                if($is_bar && $ctype['options']['item_on']){
                    $data['link'] = href_to($ctype['name'], $item['slug'].'.html');
                }
                return $data;
            });
    }

    public function actionTest() {

        $this->preset = $this->getOption('preset') ? $this->getOption('preset') : 'content_list';
        $this->perpage = 10;
        $this->ctypes = $this->getOption('ctypes');
        $this->ctypes_photo = $this->filterCtypeWithPhoto();
        $this->select_fields = ['title', 'photo', 'date_pub'];
        $items = $this->getFilteredCtypesItems(false,'алмаз');
        dump($items);

    }

    public function getFilteredCtypesItems($ctypes_names = false,$search = false) {

        $ctypes = $this->getCtypes();

        $model       = cmsCore::getModel('content');
        if($search){
            $search = $model->db->escape($search);
        }
        $select_only = '';
        $ctypes_names = $ctypes_names ? $ctypes_names : $this->ctypes;

        foreach ($ctypes_names as $key => $ctype_name) {
            $is_last = empty($ctypes_names[$key + 1]);
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
                $select_only .= " FROM {#}$table $tbl_prefix WHERE ($tbl_prefix.title LIKE '%$search%') UNION SELECT ";
            }
        }

        if($search){
            $model->filterLike('title',"%{$search}%");
        }

        $model->selectOnly($select_only);
        $model->orderByRaw('date_pub DESC');
        $model->limitPage($this->page,$this->perpage);

        return $model->get($table, function($item, $model) use($ctypes) {
                $ctype = $ctypes[$item['ctype_name']];
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

    public function getUserTypes() {
        $ztime_types_text = $this->getUserTypesText();
        if($ztime_types_text){
            return explode(',', $ztime_types_text);
        }
        return false;

    }

    public function getUserTypesText() {
        $ztime_types = cmsUser::getCookie('ztime_types');
        if ($this->cms_user->is_logged) {
            $ztime_types_ups = cmsUser::getUPS('ztime_types');
            if ($ztime_types_ups) {
                return $ztime_types_ups;
            }
        }
        return $ztime_types;

    }

    public function setUserTypes($text) {
        cmsUser::setCookie('ztime_types', $text, 604800);//7 days
        if ($this->cms_user->is_logged) {
            cmsUser::setUPS('ztime_types', $text);
        }
    }

}
