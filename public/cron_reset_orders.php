<?php
/**
 * 用户订单重置定时任务脚本
 * 直接复制 Login::ordert() 方法的逻辑
 * 使用方法：php cron_reset_orders.php
 * 或添加到crontab：1 12 * * * /usr/bin/php /path/to/cron_reset_orders.php
 * （现网为每天 12:01，见 deploy/cron/crontab.json）
 */

// 设置时区
date_default_timezone_set('Asia/Shanghai');

// 定义项目根目录
define('ROOT_PATH', __DIR__);

// 加载ThinkPHP框架
require ROOT_PATH . '/vendor/autoload.php';

// 启动应用
$app = new \think\App();
$app->initialize();

use think\facade\Db;

// 日志文件路径
$log_file = ROOT_PATH . '/runtime/cron_reset_orders.log';

/**
 * 写入日志
 */
function writeLog($message)
{
    global $log_file;
    $time = date('Y-m-d H:i:s');
    $log_message = "[{$time}] {$message}" . PHP_EOL;
    
    // 写入文件
    file_put_contents($log_file, $log_message, FILE_APPEND);
    
    // 同时输出到控制台
    echo $log_message;
}

try {
    writeLog('开始执行用户订单重置任务...');
    
    // 直接复制 Login::ordert() 方法的逻辑
    $userorder = Db::name('user')->where('order_total','>',0)->where('gd',0)->select()->toArray();
    $data = $userorder;
    
    if(!$data){
        writeLog('重置失败 - 没有需要重置的数据');
        exit(0);
    }
    
    writeLog('找到 ' . count($data) . ' 个需要重置的用户');
    
    foreach ($userorder as $k=>$v){
        $res = Db::name('user')->where('id',$v['id'])->where('gd',0)->update(['order_total'=>'0']);
        //  Db::name('graborders')->where('uid',$v['id'])->update(['status'=>'2']);
    }
        
    if($res){
        writeLog('重置成功 - 共重置 ' . count($data) . ' 个用户');
    }else{
        writeLog('重置失败');
    }
    
    exit(0);
    
} catch (Exception $e) {
    writeLog('执行失败: ' . $e->getMessage());
    exit(1);
} 