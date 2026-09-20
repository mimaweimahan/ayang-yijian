<?php
declare (strict_types=1);

namespace app\command;

use app\common\model\Order;
use app\common\model\Recharge;
use app\common\model\Stat;
use app\common\model\User;
use app\common\model\Withdraw;
use think\console\Command;
use think\console\Input;
use think\console\Output;

class Census extends Command
{
    protected function configure()
    {
        // 指令配置
        $this->setName('createCensus')
            ->setDescription('生成昨日统计数据命令');
    }

    protected function execute(Input $input, Output $output)
    {
        $yesterday = date('Ymd', strtotime('-1 day'));
        $yesterday_start = strtotime($yesterday);
        $yesterday_end = $yesterday_start + 86399;

        $info = Stat::where(['day' => $yesterday])->field('id')->find();
        if ($info) {
            $id = $info['id'];
        } else {
            $id = 0;
        }

        $data['user_num'] = User::whereBetween('create_time', "{$yesterday_start},{$yesterday_end}")->count();
        $data['vip_num'] = User::whereBetween('update_time', "{$yesterday_start},{$yesterday_end}")->where('level_id', '>', 1)->count();
        $data['active_num'] = User::whereBetween('login_time', "{$yesterday_start},{$yesterday_end}")->count();
        $extra['order_total'] = Order::whereBetween('create_time', "{$yesterday_start},{$yesterday_end}")->count();
        $extra['order_price'] = Order::whereBetween('create_time', "{$yesterday_start},{$yesterday_end}")->sum('price');
        $extra['recharge_count'] = Recharge::whereBetween('create_time', "{$yesterday_start},{$yesterday_end}")->count();
        $extra['with_count'] = Withdraw::whereBetween('create_time', "{$yesterday_start},{$yesterday_end}")->count();
        $extra['comm'] = Order::whereBetween('create_time', "{$yesterday_start},{$yesterday_end}")->sum('commission');
        $data['extra'] = json_encode($extra);
        if ($id) {
            $res = Stat::update($data, ['id' => $id]);
        } else {
            $data['day'] = $yesterday;
            $res = Stat::create($data);
        }

        // 指令输出
        $output->writeln("{$yesterday}==统计结束-{$res}");
    }
}
