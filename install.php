<?php

function install_package() {

    $core = cmsCore::getInstance();

    //установка компонента
    if (!$core->db->getRowsCount('controllers', "name = 'ztime'")) {
        $core->db->query("INSERT INTO `{#}controllers` (`title`, `name`, `slug`, `is_enabled`, `options`, `author`, `url`, `version`, `is_backend`, `is_external`, `files`, `addon_id`) VALUES ('Ztime', 'ztime', NULL, 1, '', 'Zau4man', 'http://www.zau4man.ru', '1.0.0', 1, NULL, NULL, NULL);");
    }

    //обновление
    $core->db->query("UPDATE `{#}controllers` SET `version` = '1.0.1' WHERE `name` = 'ztime';");

    return true;
}