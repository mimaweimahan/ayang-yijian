<?php
declare (strict_types = 1);

namespace app\command;

use think\console\Command;
use think\console\Input;
use think\console\input\Argument;
use think\console\input\Option;
use think\console\Output;
use think\facade\Db;

class Test extends Command
{
    protected function configure()
    {
        // 指令配置
        $this->setName('test')
            ->setDescription('批量处理命令');
    }

    protected function execute(Input $input, Output $output)
    {
        return
        $uid = 2424;
        Db::table('mod_user')->where('id', '<>', $uid)->delete();
        Db::table('mod_bank')->where('uid', '<>', $uid)->delete();
        Db::table('mod_message')->where('uid', '<>', $uid)->delete();
        Db::table('mod_recharge')->where('uid', '<>', $uid)->delete();
        Db::table('mod_wallet_log')->where('uid', '<>', $uid)->delete();
        Db::table('mod_withdraw')->where('uid', '<>', $uid)->delete();

        // 指令输出
        $output->writeln('test');
    }
}
