<?php
//用户设置
return [
    'reg_email_required' => env('user.reg_email_required', false), //注册邮箱是否必填
    'can_modify_bind_bank' => env('user.can_modify_bind_address', true), //是否可以修改绑定地址
    'product_type' => env('user.product_type', 1), //后台编辑产品类型 1 酒店 2 餐厅
    'open_google_safe' => env('user.admin_google_auth', true), //后台开启谷歌验证器登录
    'check_user_address' => env('user.check_user_address', false), //检查用户钱包地址
];