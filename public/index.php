<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006-2019 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: liu21st <liu21st@gmail.com>
// +----------------------------------------------------------------------

// [ 应用入口文件 ]

namespace think;
// 允许所有域名跨域访问

require __DIR__ . '/../vendor/autoload.php';
//  echo '<pre>' ;  var_dump($_SERVER['REQUEST_URI']);

// 执行HTTP应用并响应
$http = (new App())->http;
if(in_array($_SERVER['REQUEST_URI'],['/admin','admin/index','admin/index/index'])){
    $http->name('/');
}
$response = $http->run();

$response->send();

$http->end($response);
