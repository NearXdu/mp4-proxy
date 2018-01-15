<?php
/**
 * Created by PhpStorm.
 * User: crlt_
 * Date: 2018/1/15
 * Time: 下午2:48
 */
require_once "vendor/autoload.php";

use Crlt_\Mp4Proxy\mp4Proxy;


$url = $_GET['url'];
$myProxy=new mp4Proxy();
$myProxy->actionNormal($url);