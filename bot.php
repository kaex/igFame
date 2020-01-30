<?php
error_reporting(0);
DEFINE('ROOTDIR', __DIR__);
require ROOTDIR . '/src/autoloader.php';
$loader = new autoloader();
$loader->addNamespace('xosad', ROOTDIR . '/src/xosad');
$loader->register();
use xosad\igFame;
$i = new igFame();
$i->startCore();
