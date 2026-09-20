<?php

use think\facade\Route;

Route::get('/', function () {
    // return redirect('/h5/index.html');
    return "<div style='text-align: center;margin-top: 25em'><h1>1</h1></div>";
});
Route::miss(function () {
    return "<div style='text-align: center;margin-top: 25em'><h1>Route does not exist</h1></div>";
});
Route::get('index', 'Home/index'); //获取文章数据
Route::get('getArticle', 'Index/getArticle'); //获取文章数据
Route::any('login', 'Index/login')->allowCrossDomain();//登录
Route::post('register', 'Index/doRegister');//注册
Route::post('logout', 'Index/logout');//退出
Route::post('getIndex', 'Index/getIndex');//获取首页数据
Route::any('goodsList', 'Index/goodsList');//获取首页数据
Route::post('noticeList', 'Index/noticeList');//获取首页数据
Route::post('getCustomer', 'Index/getCustomer');//获取客服列表
Route::post('getVipList', 'Index/getVipList');//获取VIP列表
Route::post('buyVip', 'Index/buyVip');//购买VIP
Route::post('getMesList', 'Index/getMesList');//获取消息列表
Route::post('setMesRead', 'Index/setMesRead');//设置消息已读
Route::post('getAppList', 'Index/getAppList');//获取app滚动列表
Route::post('getAppInfo', 'Index/getAppInfo');//获取app滚动详情
Route::post('getAgreement', 'Index/getAgreement');//获取协议

Route::post('getUserInfo', 'Account/getUserInfo');//获取用户基本数据
Route::post('sign', 'Account/sign');//签到
Route::post('changePass', 'Account/changePass');//更改密码
Route::post('getCard', 'Account/getCard');//获取银行信息
Route::post('setCard', 'Account/setCard');//设置银行信息
Route::post('setWithData', 'Account/setWithData');//申请提现
Route::post('getWithList', 'Account/getWithList');//提现列表
Route::post('invest', 'Account/invest');//申请提现
Route::post('getWalletList', 'Account/getWalletList');//钱包记录列表
Route::post('getRechargeList', 'Account/getRechargeList');//后台充值记录


Route::any('setOrder', 'Trade/setOrder');//生成订单
Route::post('commitOrder', 'Trade/commitOrder');//提交订单-分佣
Route::post('getOrderList', 'Trade/getOrderList');//订单列表
Route::post('getOrderInfo', 'Trade/getOrderInfo');//订单详情

Route::any('getList', 'Jiang/getList');//奖品列表
Route::any('kaiJiang', 'Jiang/kaiJiang');//奖品列表
