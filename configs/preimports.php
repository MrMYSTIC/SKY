<?php
//Core system
import(CONFIGS_DIR.'/configure.php');
import(PLUGINS_CLASS);
import(EVENT_CLASS);
import(CONFIGS_DIR.'/plugins.php');
import(PRELOADER);
import(ERROR_CLASS);
import(CONTROLLER_CLASS);
import(MAILER_CLASS);
import(SESSION_CLASS);
Event::PublishActionHook('/preimports/after/');
?>