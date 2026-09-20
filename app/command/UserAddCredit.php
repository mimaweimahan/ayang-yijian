<?php
declare (strict_types=1);

namespace app\command;

use app\common\model\User;
use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\facade\Db;

class UserAddCredit extends Command
{
    protected function configure()
    {
        // 指令配置
        $this->setName('userAddCredit')
            ->setDescription('每天自动增加用户信用分');
    }

    protected function execute(Input $input, Output $output)
    {
        //每天加一
        $addCreditNum = 1;
        $currentTime = time(); //当前时间
        $where = [];
        $where[] = ['status', '=', 1];
        $userList = User::where($where)->field(['id', 'status', 'auto_add_credit_time', 'username', 'credit_score'])->select();
        $addUserIds = [];
        //循环判断上次增加积分的时间
        foreach ($userList as $Key => $value) {
            if (($currentTime - $value['auto_add_credit_time']) > 86000 && $value['credit_score'] < 1000) {
                $addUserIds[] = $value['id'];
            }
        }
        Db::table('mod_user')->whereIn('id', $addUserIds)
            ->inc('credit_score', $addCreditNum)
            ->update(['auto_add_credit_time' => $currentTime]);
        $outMsg = '增加积分用户数量: ' . count($addUserIds);
        // 指令输出
        $output->writeln($outMsg);
    }
}
