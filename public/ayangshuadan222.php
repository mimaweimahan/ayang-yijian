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

// [ 统一后台入口文件 ]
namespace think;

// 定义入口类型
$admin_entries = ['ayangshuadan222.php']; // 超级管理员入口
$proxy_entries = [
    'jd07ia5X2Sde.php',
    'n67HSixJRfXh.php', 
    'y8P2htrFED3Q.php',
    'HYzrM3xwCNwx.php',
    'RQMfJS4EQewD.php',
    'sdDGAZQ5dF3K.php'
];

$current_script = basename($_SERVER['SCRIPT_NAME']);
$all_entries = array_merge($admin_entries, $proxy_entries);

if (!in_array($current_script, $all_entries)) {
    http_response_code(404);
    exit('Access Denied');
}

require __DIR__ . '/../vendor/autoload.php';

// 执行HTTP应用并响应
$http = (new App())->http;
$http->name('admin');

// 设置入口类型标识到请求参数中
if (in_array($current_script, $proxy_entries)) {
    // 代理入口 - 通过请求参数传递
    $_GET['entry_type'] = 'proxy';
    $_GET['proxy_entry'] = $current_script;
    
    // 如果URL中没有参数，重定向到带参数的URL
    if (empty($_SERVER['QUERY_STRING'])) {
        $redirect_url = $_SERVER['REQUEST_URI'] . '?entry_type=proxy&proxy_entry=' . $current_script;
        header('Location: ' . $redirect_url);
        exit;
    }
} else {
    // 超级管理员入口
    $_GET['entry_type'] = 'admin';
    $_GET['proxy_entry'] = '';
}

$response = $http->run();

$response->send();

$http->end($response);
