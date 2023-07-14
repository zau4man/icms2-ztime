<?php

class formZtimeOptions extends cmsForm {

    public function init() {

        $ctypes = cmsCore::getModel('content')->getContentTypes();

        $preset_generator = function (){
            static $presets = null;
            if($presets === null){
                $presets = cmsCore::getModel('images')->getPresetsList(true);
                $presets['original'] = LANG_PARSER_IMAGE_SIZE_ORIGINAL;
            }
            return $presets;
        };

        return array(
            array(
                'type'   => 'fieldset',
                'title'  => LANG_OPTIONS,
                'childs' => array(
                    new fieldListMultiple('ctypes', [
                        'title'     => LANG_ZTIME_OPTIONS_CTYPES,
                        'hint'      => LANG_ZTIME_OPTIONS_CTYPES_HINT,
                        'default' => 0,
                        'generator' => function () use ($ctypes) {
                            $items = [];
                            if ($ctypes) {
                                foreach ($ctypes as $ctype) {
                                    $items[$ctype['name']] = $ctype['title'];
                                }
                            }
                            return $items;
                        },
                        'rules' => [
                            ['required']
                        ]
                    ]),
                    new fieldList('preset', [
                        'title'     => LANG_ZTIME_OPTIONS_PRESET,
                        'default'   => 'small',
                        'generator' => function () use($preset_generator) {
                            return $preset_generator();
                        },
                        'rules' => [
                            ['required']
                        ]
                    ]),
                    new fieldString('title', [
                        'title'     => LANG_ZTIME_OPTIONS_TITLE,
                        'default'   => 'Тайм-лента',
                        'rules' => [
                            ['required']
                        ]
                    ])
                )
            )
        );

    }
}
