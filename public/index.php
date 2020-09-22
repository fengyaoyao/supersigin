<?php
// +----------------------------------------------------------------------
// | ThinkCMF [ WE CAN DO IT MORE SIMPLE ]
// +----------------------------------------------------------------------
// | Copyright (c) 2013-2017 http://www.thinkcmf.com All rights reserved.
// +----------------------------------------------------------------------
// | Author: 老猫 <zxxjjforever@163.com>
// +----------------------------------------------------------------------
//phpinfo();die();
// [ 入口文件 ]
//echo '123';die();
// 调试模式开关
//define("APP_DEBUG", 1);
define("APP_DEBUG", false);

define("APP_ROOT",dirname(__FILE__));

// 定义CMF根目录,可更改此目录
define('CMF_ROOT', __DIR__ . '/../');

// 定义应用目录
define('APP_PATH', CMF_ROOT . 'app/');

// 定义CMF核心包目录
define('CMF_PATH', CMF_ROOT . 'simplewind/cmf/');

// 定义插件目录
define('PLUGINS_PATH', __DIR__ . '/plugins/');

// 定义扩展目录
define('EXTEND_PATH', CMF_ROOT . 'simplewind/extend/');
define('VENDOR_PATH', CMF_ROOT . 'simplewind/vendor/');

// 定义应用的运行时目录
define('RUNTIME_PATH', CMF_ROOT . 'data/runtime/');

// 定义CMF 版本号
define('THINKCMF_VERSION', '5.0.24');
//echo 'index';die();

//增加xss过滤
require('htmLawed.php');
$_GET = !empty($_GET) ? getAddslashesForInput($_GET) : [];
$_POST = !empty($_POST) ? getAddslashesForInput($_POST) : [];
$_REQUEST = !empty($_REQUEST) ? getAddslashesForInput($_REQUEST) : [];
$_SERVER = !empty($_SERVER) ? getAddSlashes($_SERVER) : [];


// 加载框架基础文件
require CMF_ROOT . 'simplewind/thinkphp/base.php';
//echo 'index111111111';die();
// 执行应用
\think\App::run()->send();
