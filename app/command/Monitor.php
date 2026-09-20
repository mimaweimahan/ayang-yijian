<?php
declare (strict_types=1);

namespace app\command;

use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\facade\Db;
use think\facade\Log;

class Monitor extends Command
{
    protected function configure()
    {
        $this->setName('monitor')
            ->setDescription('订单与钱包流水重复监控')
            ->addOption('uid', null, \think\console\input\Option::VALUE_OPTIONAL, '目标用户ID，仅检测该用户账变重复')
            ->addOption('minutes', null, \think\console\input\Option::VALUE_OPTIONAL, '回溯分钟数，仅在指定uid时生效，默认1440(24小时)');
    }

    protected function execute(Input $input, Output $output)
    {
        $uid = $input->getOption('uid');
        if ($uid) {
            // 针对单个用户检测：同一秒内重复账变（按 uid, order_id, type, create_time 聚合）
            $minutes = (int)($input->getOption('minutes') ?: 1440);
            $sinceTs = time() - $minutes * 60;

            $dupPerSecond = Db::table('mod_wallet_log')
                ->field('uid, order_id, type, create_time, COUNT(*) cnt, GROUP_CONCAT(id) ids')
                ->where('uid', (int)$uid)
                ->where('create_time', '>=', $sinceTs)
                ->whereIn('type', [3,4,5,8,9])
                ->group('uid, order_id, type, create_time')
                ->having('cnt > 1')
                ->order('create_time desc')
                ->limit(500)
                ->select();

            if (!empty($dupPerSecond)) {
                Log::error('[monitor-user] uid=' . $uid . ' dup-per-second: ' . json_encode($dupPerSecond, JSON_UNESCAPED_UNICODE));
            }
            $output->writeln('monitor user done: uid=' . $uid . ', minutes=' . $minutes . ', dupPerSecond=' . count($dupPerSecond));
            return 0;
        }

        // 全局模式
        // 1) 同号重复订单
        $dupOrders = Db::table('mod_order')
            ->field('order_no, COUNT(*) cnt')
            ->group('order_no')
            ->having('cnt > 1')
            ->limit(100)
            ->select();
        if (!empty($dupOrders)) {
            Log::error('[monitor] duplicate order_no: ' . json_encode($dupOrders, JSON_UNESCAPED_UNICODE));
        }

        // 2) 同一用户多未完成订单
        $multiUnfinished = Db::table('mod_order')
            ->field('uid, COUNT(*) unfinished')
            ->where('status', 1)
            ->group('uid')
            ->having('unfinished > 1')
            ->limit(100)
            ->select();
        if (!empty($multiUnfinished)) {
            Log::error('[monitor] multiple unfinished orders: ' . json_encode($multiUnfinished, JSON_UNESCAPED_UNICODE));
        }

        // 3) 钱包流水重复 (order_id,type,uid)
        $dupWallet = Db::table('mod_wallet_log')
            ->field('order_id, type, uid, COUNT(*) cnt')
            ->whereIn('type', [3,4,5,8,9])
            ->group('order_id, type, uid')
            ->having('cnt > 1')
            ->limit(100)
            ->select();
        if (!empty($dupWallet)) {
            Log::error('[monitor] duplicate wallet logs: ' . json_encode($dupWallet, JSON_UNESCAPED_UNICODE));
        }

        // 4) 全量用户：同一秒内重复账变（最近60分钟窗口，避免扫全表）
        $sinceTsAll = time() - 60 * 60;
        $dupPerSecondAll = Db::table('mod_wallet_log')
            ->field('uid, order_id, type, create_time, COUNT(*) cnt, GROUP_CONCAT(id) ids')
            ->where('create_time', '>=', $sinceTsAll)
            ->whereIn('type', [3,4,5,8,9])
            ->group('uid, order_id, type, create_time')
            ->having('cnt > 1')
            ->order('create_time desc')
            ->limit(500)
            ->select();
        if (!empty($dupPerSecondAll)) {
            Log::error('[monitor] dup-per-second (all users, last 60m): ' . json_encode($dupPerSecondAll, JSON_UNESCAPED_UNICODE));
        }

        // 控制台输出简报
        $output->writeln('monitor done: '
            . 'dupOrders=' . count($dupOrders)
            . ', multiUnfinished=' . count($multiUnfinished)
            . ', dupWallet=' . count($dupWallet)
            . ', dupPerSecondAll=' . count($dupPerSecondAll));
        return 0;
    }
}


