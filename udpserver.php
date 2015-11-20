<?php
date_default_timezone_set("asia/shanghai");

include __DIR__.'/core/Server.php';

error_reporting(E_ALL ^ E_NOTICE);

use core\Server;

define('BASEDIR',__DIR__);
spl_autoload_register('autoload');
function autoload($classname){
    $filename = BASEDIR.'/'.str_replace('\\','/',$classname).'.php';
    if (file_exists($filename)) {
        include_once "$filename";
    } else {
        echo '文件'.$filename.'不存在'.PHP_EOL;
    }
}

$webserver = new Server();

$webserver->run('0.0.0.0', 9501);