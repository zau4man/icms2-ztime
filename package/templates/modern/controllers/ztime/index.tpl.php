<?php
$this->setPageTitle($title);
$this->addBreadCrumb($title);
$this->addControllerCSS('ztime');
$this->addControllerJS('vue.global.prod');
$this->addControllerJS('ztime');
?>
<div id="ztime" data-href="<?php echo href_to('ztime'); ?>"><?php echo LANG_ZTIME_LOADING; ?></div>