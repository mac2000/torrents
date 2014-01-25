<?php
require_once 'vendor/autoload.php';
require_once 'functions.php';
require_once 'config.php';

$arr = is_cli() ? $argv : $_REQUEST;
$key = is_cli() ? 1 : 'url';
$arg = isset($arr[$key]) ? $arr[$key] : null;

$feeds = $arg ? array($arg) : get_feeds();

foreach ($feeds as $url) {
    try {
    	sleep(1);
        check_feed($url);
    } catch (Exception $ex) {
        echo $ex->getMessage() . gnl();
    }
}

