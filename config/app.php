<?php
// +----------------------------------------------------------------------
// | 应用设置
// +----------------------------------------------------------------------

return [
    // 应用地址
    'app_host' => env('app.host', ''),
    // 应用的命名空间
    'app_namespace' => '',
    // 是否启用路由
    'with_route' => true,
    // 开启应用快速访问
    'app_express' => true,
    // 默认应用
    'default_app' => 'api',
    // 默认时区
    'default_timezone' => env('app.default_timezone', 'America/New_York'),
    // 应用映射（自动多应用模式有效）
    'app_map' => [],
    // 域名绑定（自动多应用模式有效）
    'domain_bind' => [],
    // 禁止URL访问的应用列表（自动多应用模式有效）
    'deny_app_list' => ['common'],
    // 异常页面的模板文件
    'exception_tmpl' => app()->getThinkPath() . 'tpl/think_exception.tpl',
    // 错误显示信息,非调试模式有效
    'error_message' => '页面错误！请稍后再试～',
    // 显示错误信息
    'show_error_msg' => false,
    // 前端 H5 公网根（无尾斜杠），用于邀请注册链接；前后端分离时在 .env 设置 FRONTEND_INVITE_BASE
    'frontend_invite_base' => env('app.frontend_invite_base', ''),
];
